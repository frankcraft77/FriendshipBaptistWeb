<?php
/**
 * Website editor — core helpers.
 *
 * Editing model (v3 — automatic, structure-based; NO hand-placed markers):
 *   • Per page, the editor looks ONLY inside the <main> element. Each
 *     top-level <section> (or <article>) inside <main> becomes ONE rich-text
 *     field, labeled from its data-edit-label / aria-label / first heading.
 *     A <main> with no sections becomes a single field. Pages become
 *     editable automatically — nothing needs to be tagged by hand.
 *   • Everything outside <main> (header, nav, footer, scripts) is ignored
 *     for per-page editing. The shared layout marks <header> and <footer>
 *     once with data-edit-shared; the "Header & Footer" screen edits those
 *     once and applies the save to every page file.
 *   • Non-text elements inside a section (buttons, icons/SVGs, images,
 *     forms, embeds, scripts, navigation) are shown as locked chips in the
 *     editor and are PRESERVED UNTOUCHED on save — they are re-extracted
 *     from the live file and put back in place, so the user can only ever
 *     change the text around them.
 *
 * Safety model:
 *   • Only .html files inside EDITOR_SITE_DIR can be read or written.
 *   • Submitted HTML passes a whitelist sanitizer (text/structure tags and
 *     harmless attributes only; javascript: links, event handlers, and any
 *     user-supplied scripts/embeds are stripped). Locked elements are never
 *     taken from user input — only from the trusted file on disk.
 *   • Every write: timestamped backup first (last 10 kept), then temp file
 *     + atomic rename — a failed save can never corrupt a page.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';

/**
 * Editor release version. Bump this whenever editor.js / editor.css change:
 * it is appended to their URLs (?v=…) so browsers and hosting caches can
 * never keep serving an old copy of the script after an upload.
 */
const EDITOR_VERSION = '3.1';

/* ── Error handling: never show raw PHP errors to the user ─────────────── */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/._data/error.log');

function editor_data_dir(): string
{
    $dir = __DIR__ . '/._data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}

function editor_backup_dir(): string
{
    $dir = __DIR__ . '/._backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}

/** HTML-escape helper used for ALL output of untrusted plain values. */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ── Session & login ────────────────────────────────────────────────────── */

function editor_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('fba_editor');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();

    $limit = EDITOR_SESSION_MINUTES * 60;
    if (!empty($_SESSION['logged_in'])) {
        if (isset($_SESSION['last_seen']) && (time() - (int) $_SESSION['last_seen']) > $limit) {
            session_unset();
            session_destroy();
            editor_session_start();
            $_SESSION['flash_error'] = 'You were logged out after a period of inactivity. Please log in again.';
        } else {
            $_SESSION['last_seen'] = time();
        }
    }
}

function is_logged_in(): bool
{
    return !empty($_SESSION['logged_in']);
}

function lockout_state(): array
{
    $file = editor_data_dir() . '/lockout.json';
    $state = ['fails' => 0, 'until' => 0];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $state = array_merge($state, $decoded);
        }
    }
    return $state;
}

function lockout_save(array $state): void
{
    @file_put_contents(editor_data_dir() . '/lockout.json', json_encode($state), LOCK_EX);
}

function lockout_active(): int
{
    $state = lockout_state();
    return max(0, (int) $state['until'] - time());
}

function record_login_failure(): void
{
    $state = lockout_state();
    $state['fails'] = (int) $state['fails'] + 1;
    if ($state['fails'] >= EDITOR_MAX_LOGIN_FAILS) {
        $state['until'] = time() + EDITOR_LOCKOUT_MINUTES * 60;
        $state['fails'] = 0;
    }
    lockout_save($state);
}

function record_login_success(): void
{
    lockout_save(['fails' => 0, 'until' => 0]);
}

function try_login(string $password): bool
{
    if (EDITOR_PASSWORD_HASH === '' || !password_verify($password, EDITOR_PASSWORD_HASH)) {
        record_login_failure();
        return false;
    }
    record_login_success();
    session_regenerate_id(true);
    $_SESSION['logged_in'] = true;
    $_SESSION['last_seen'] = time();
    return true;
}

/* ── CSRF protection ────────────────────────────────────────────────────── */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

/* ── Path safety ────────────────────────────────────────────────────────── */

function site_dir(): string
{
    $real = realpath(EDITOR_SITE_DIR);
    if ($real === false) {
        throw new RuntimeException('The site folder in config.php does not exist.');
    }
    return $real;
}

/** Untrusted relative page path → safe absolute path, or null. */
function safe_page_path(string $rel): ?string
{
    if ($rel === '' || str_contains($rel, "\0") || str_contains($rel, '..')) {
        return null;
    }
    if (preg_match('/\.html$/i', $rel) !== 1 || $rel[0] === '/' || $rel[0] === '\\') {
        return null;
    }
    $abs = realpath(site_dir() . DIRECTORY_SEPARATOR . $rel);
    if ($abs === false || !is_file($abs)) {
        return null;
    }
    if (!str_starts_with($abs, site_dir() . DIRECTORY_SEPARATOR)) {
        return null;
    }
    if (preg_match('/\.html$/i', $abs) !== 1) {
        return null;
    }
    return $abs;
}

/* ── Page discovery ─────────────────────────────────────────────────────── */

/** All site pages (Home first, A→Z after). */
function list_pages(): array
{
    $base = site_dir();
    $pages = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            function (SplFileInfo $file): bool {
                $name = $file->getFilename();
                if ($name[0] === '.' || $name[0] === '_') {
                    return false;
                }
                if ($file->isDir() && strtolower($name) === 'editor') {
                    return false;
                }
                return true;
            }
        )
    );
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') {
            continue;
        }
        if ($file->getSize() > 3 * 1024 * 1024) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
        $pages[] = [
            'rel' => $rel,
            'name' => page_friendly_name($file->getPathname(), $rel),
            'where' => page_friendly_where($rel),
            'sheet_managed' => str_starts_with($rel, 'churches/'),
        ];
    }
    usort($pages, function (array $a, array $b): int {
        if ($a['rel'] === 'index.html') return -1;
        if ($b['rel'] === 'index.html') return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    return $pages;
}

function page_friendly_name(string $abs, string $rel): string
{
    $head = (string) file_get_contents($abs, false, null, 0, 4096);
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $head, $m)) {
        $title = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = preg_replace('/\s+[—|–-]\s+[^—|–-]*$/u', '', $title) ?: $title;
        if ($title !== '') {
            return $rel === 'index.html' ? 'Home page' : $title;
        }
    }
    if ($rel === 'index.html') {
        return 'Home page';
    }
    $slug = basename(dirname($rel));
    if ($slug === '.' || $slug === '') {
        $slug = preg_replace('/\.html$/i', '', basename($rel));
    }
    return ucfirst(str_replace(['-', '_'], ' ', $slug));
}

function page_friendly_where(string $rel): string
{
    $dir = dirname($rel);
    if ($dir === '.' || $dir === '') {
        return 'Main site';
    }
    return str_replace('/', ' › ', $dir);
}

/* ── HTML parsing (DOMDocument, UTF-8 safe) ─────────────────────────────── */

/** Load a page. Returns [DOMDocument, md5-of-file] (md5 = staleness guard). */
function load_page_dom(string $abs): array
{
    $html = file_get_contents($abs);
    if ($html === false) {
        throw new RuntimeException('Could not read the page file.');
    }
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();
    foreach ($dom->childNodes as $node) {
        if ($node->nodeType === XML_PI_NODE) {
            $dom->removeChild($node);
            break;
        }
    }
    $dom->encoding = 'UTF-8';
    return [$dom, md5($html)];
}

/** Decode libxml's entity-encoding of non-ASCII text back to raw UTF-8. */
function decode_text_entities(string $html): string
{
    return preg_replace_callback(
        '/(<(script|style)\b[^>]*>.*?<\/\2\s*>)|(&(?:#\d+|#x[0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]{1,31});)/s',
        function (array $m): string {
            if ($m[1] !== '') {
                return $m[1];
            }
            $entity = $m[3];
            $decoded = html_entity_decode($entity, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $entity || strpbrk($decoded, "<>&\"'") !== false) {
                return $entity;
            }
            return $decoded;
        },
        $html
    );
}

function dom_to_html(DOMDocument $dom): string
{
    $out = $dom->saveHTML();
    if ($out === false) {
        throw new RuntimeException('Could not rebuild the page.');
    }
    $out = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $out);
    return decode_text_entities($out);
}

function inner_html(DOMElement $el): string
{
    $out = '';
    foreach ($el->childNodes as $child) {
        $out .= $el->ownerDocument->saveHTML($child);
    }
    return decode_text_entities($out);
}

/* ── Protected (non-text) elements ──────────────────────────────────────── */
/* These are never editable: shown as locked chips in the editor, and on
   save the ORIGINALS are re-extracted from the live file and put back —
   user input can position them but never define or delete their content. */

const PROTECTED_TAGS = ['script', 'style', 'svg', 'iframe', 'img', 'picture',
    'video', 'audio', 'canvas', 'object', 'embed', 'form', 'input', 'select',
    'textarea', 'button', 'nav', 'noscript', 'template', 'dialog'];

function is_protected_el(DOMElement $el): bool
{
    $tag = strtolower($el->nodeName);
    if (in_array($tag, PROTECTED_TAGS, true)) {
        return true;
    }
    // Links styled as buttons are buttons to the user — lock them too.
    if ($tag === 'a' && preg_match('/(^|\s)btn(\s|$)/', $el->getAttribute('class'))) {
        return true;
    }
    return false;
}

function protected_chip_word(DOMElement $el): string
{
    return match (strtolower($el->nodeName)) {
        'a', 'button' => 'button',
        'svg' => 'icon',
        'img', 'picture' => 'photo',
        'iframe' => 'embedded content',
        'form' => 'form',
        'input', 'select', 'textarea' => 'form box',
        'nav' => 'menu',
        'script' => 'page code',
        'video', 'audio' => 'media',
        default => 'locked item',
    };
}

/**
 * All protected elements inside $root, in document order (not descending
 * into protected elements). The same walk is used when building the editor
 * view and when saving, so chip numbers always line up.
 */
function collect_protected(DOMElement $root): array
{
    $list = [];
    $walk = function (DOMNode $node) use (&$walk, &$list): void {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if (is_protected_el($child)) {
                    $list[] = $child;
                    continue;
                }
                $walk($child);
            }
        }
    };
    $walk($root);
    return $list;
}

/**
 * The editable view of a region: its inner HTML with every protected
 * element replaced by a numbered, locked chip. This is what goes into the
 * contenteditable box.
 */
function editor_display_html(DOMElement $region): string
{
    $tmp = new DOMDocument();
    $tmp->encoding = 'UTF-8';
    $copy = $tmp->importNode($region, true);
    $tmp->appendChild($copy);

    $i = 0;
    $freeze = function (DOMNode $node) use (&$freeze, &$i, $tmp): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if (is_protected_el($child)) {
                    $chip = $tmp->createElement('span');
                    $chip->setAttribute('class', 'edit-locked');
                    $chip->setAttribute('contenteditable', 'false');
                    $chip->setAttribute('data-lock', (string) $i);
                    $chip->appendChild($tmp->createTextNode(protected_chip_word($child)));
                    $node->replaceChild($chip, $child);
                    $i++;
                    continue;
                }
                $freeze($child);
            }
        }
    };
    $freeze($copy);

    // Sanitize the display too, so what the user sees is exactly the
    // normalized form that a no-change save would produce.
    return sanitize_fragment_html(inner_html($copy));
}

/* ── Sanitizer ──────────────────────────────────────────────────────────── */
/* Whitelist for user-submitted rich text. Structure/wrapper tags keep the
   page's own classes so layout survives a round-trip; anything executable
   or interactive that a USER types is dropped (pre-existing interactive
   elements come back via the locked-chip mechanism instead).             */

const ALLOWED_TAGS = ['p', 'br', 'hr', 'strong', 'em', 'b', 'i', 'u', 's',
    'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote',
    'span', 'div', 'section', 'article', 'aside', 'figure', 'figcaption',
    'label', 'small', 'sup', 'sub', 'address'];

function style_value_safe(string $value): bool
{
    return stripos($value, 'expression(') === false
        && stripos($value, 'javascript') === false
        && strlen($value) <= 800
        // allow only var()/color-mix()/gradient url-free values
        && !preg_match('/url\s*\(/i', $value);
}

function href_value_safe(string $value): bool
{
    $v = trim($value);
    if ($v === '' || strlen($v) > 2000) {
        return false;
    }
    if (preg_match('#^(https?:)?//#i', $v) || preg_match('#^(mailto|tel):#i', $v)) {
        return true;
    }
    return !preg_match('#^[a-z][a-z0-9+.-]*:#i', $v); // relative / anchor
}

/** Sanitize an untrusted HTML fragment. Locked chips survive as tokens. */
function sanitize_fragment_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><div id="__sanitize_root__">' . $html . '</div>');
    libxml_clear_errors();
    $root = (new DOMXPath($doc))->query('//div[@id="__sanitize_root__"]')->item(0);
    if (!($root instanceof DOMElement)) {
        return '';
    }

    $out = new DOMDocument();
    $holder = $out->createElement('div');
    $out->appendChild($holder);
    sanitize_children($root, $holder, $out);

    $clean = '';
    foreach ($holder->childNodes as $child) {
        $clean .= $out->saveHTML($child);
    }
    return trim(decode_text_entities($clean));
}

function sanitize_children(DOMNode $src, DOMNode $target, DOMDocument $out): void
{
    foreach ($src->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            $target->appendChild($out->createTextNode($child->nodeValue ?? ''));
            continue;
        }
        if (!($child instanceof DOMElement)) {
            continue; // comments etc. dropped
        }
        $tag = strtolower($child->nodeName);

        // Locked chip token: keep exactly, with only its data-lock number.
        if ($tag === 'span' && $child->hasAttribute('data-lock')
            && preg_match('/^\d{1,4}$/', $child->getAttribute('data-lock'))) {
            $chip = $out->createElement('span');
            $chip->setAttribute('class', 'edit-locked');
            $chip->setAttribute('contenteditable', 'false');
            $chip->setAttribute('data-lock', $child->getAttribute('data-lock'));
            $chip->appendChild($out->createTextNode($child->textContent));
            $target->appendChild($chip);
            continue;
        }

        if (!in_array($tag, ALLOWED_TAGS, true)) {
            // User-typed script/iframe/img/etc. is dropped entirely;
            // harmless unknown wrappers keep their contents.
            if (in_array($tag, PROTECTED_TAGS, true)) {
                continue;
            }
            sanitize_children($child, $target, $out);
            continue;
        }

        $el = $out->createElement($tag);
        foreach (['id', 'class', 'title', 'role', 'for'] as $attr) {
            if ($child->hasAttribute($attr)) {
                $el->setAttribute($attr, $child->getAttribute($attr));
            }
        }
        // data-* and aria-* attributes are inert — keep them so the page's
        // own hooks (church cards, labels) survive a round-trip.
        foreach ($child->attributes as $attrNode) {
            $name = strtolower($attrNode->nodeName);
            if (str_starts_with($name, 'data-') || str_starts_with($name, 'aria-')) {
                if ($name !== 'data-lock') {
                    $el->setAttribute($attrNode->nodeName, $attrNode->nodeValue ?? '');
                }
            }
        }
        if ($child->hasAttribute('style') && style_value_safe($child->getAttribute('style'))) {
            $el->setAttribute('style', $child->getAttribute('style'));
        }
        if ($tag === 'a' && $child->hasAttribute('href') && href_value_safe($child->getAttribute('href'))) {
            $el->setAttribute('href', $child->getAttribute('href'));
            if ($child->getAttribute('target') === '_blank') {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener');
            }
        }
        $target->appendChild($el);
        sanitize_children($child, $el, $out);
    }
}

/** Whitespace-insensitive comparison key for "did this section change?". */
function fragment_signature(string $html): string
{
    return preg_replace('/\s+/u', ' ', trim($html)) ?? $html;
}

/**
 * Replace a region's content with sanitized user HTML, restoring every
 * protected element from the live file. Returns true if anything changed.
 */
function apply_region_edit(DOMElement $region, string $rawHtml): bool
{
    $clean = sanitize_fragment_html($rawHtml);
    if (trim(strip_tags($clean)) === '' && !str_contains($clean, 'data-lock')) {
        // An empty submission would wipe the section (usually a JS failure,
        // not intent) — treat as "no change" rather than blanking the page.
        return false;
    }
    if (fragment_signature($clean) === fragment_signature(editor_display_html($region))) {
        return false;
    }

    $protected = collect_protected($region);
    $doc = $region->ownerDocument;

    // Parse the clean fragment.
    $tmp = new DOMDocument();
    libxml_use_internal_errors(true);
    $tmp->loadHTML('<?xml encoding="utf-8" ?><div id="__frag__">' . $clean . '</div>');
    libxml_clear_errors();
    $frag = (new DOMXPath($tmp))->query('//div[@id="__frag__"]')->item(0);
    if (!($frag instanceof DOMElement)) {
        return false;
    }

    // Clear the region (protected nodes stay referenced in $protected).
    while ($region->firstChild) {
        $region->removeChild($region->firstChild);
    }

    /** @var array<int, DOMNode> $placed */
    $placed = [];
    $build = function (DOMNode $src, DOMNode $target) use (&$build, $doc, $protected, &$placed): void {
        foreach ($src->childNodes as $child) {
            if ($child instanceof DOMElement
                && strtolower($child->nodeName) === 'span'
                && $child->hasAttribute('data-lock')) {
                $idx = (int) $child->getAttribute('data-lock');
                if (isset($protected[$idx]) && !isset($placed[$idx])) {
                    $target->appendChild($protected[$idx]); // the original node
                    $placed[$idx] = $protected[$idx];
                }
                continue; // duplicates / unknown numbers are dropped
            }
            if ($child instanceof DOMElement) {
                $el = $doc->importNode($child, false);
                $target->appendChild($el);
                $build($child, $el);
            } elseif ($child->nodeType === XML_TEXT_NODE) {
                $target->appendChild($doc->createTextNode($child->nodeValue ?? ''));
            }
        }
    };
    $build($frag, $region);

    // Anything the user deleted still comes back: locked elements may be
    // moved but never removed. Missing ones are re-inserted keeping their
    // original relative order.
    foreach ($protected as $idx => $node) {
        if (isset($placed[$idx])) {
            continue;
        }
        $anchor = null;
        for ($j = $idx - 1; $j >= 0; $j--) {
            if (isset($placed[$j])) {
                $anchor = $placed[$j];
                break;
            }
        }
        if ($anchor !== null && $anchor->parentNode !== null) {
            $anchor->parentNode->insertBefore($node, $anchor->nextSibling);
        } else {
            $region->insertBefore($node, $region->firstChild);
        }
        $placed[$idx] = $node;
    }

    return true;
}

/* ── Field discovery (automatic — no markers needed) ────────────────────── */

/** The page's <main> element, or null. */
function find_main(DOMDocument $dom): ?DOMElement
{
    $main = $dom->getElementsByTagName('main')->item(0);
    return $main instanceof DOMElement ? $main : null;
}

/**
 * Editable regions of a page: the top-level <section>/<article> children of
 * <main> — or <main> itself when it has none. Returns [] when the page has
 * no <main> at all.
 */
function page_regions(DOMDocument $dom): array
{
    $main = find_main($dom);
    if ($main === null) {
        return [];
    }
    $regions = [];
    foreach ($main->childNodes as $child) {
        if ($child instanceof DOMElement
            && in_array(strtolower($child->nodeName), ['section', 'article'], true)) {
            $regions[] = $child;
        }
    }
    return $regions ?: [$main];
}

/** Friendly label for a region: data-edit-label → aria-label → heading → n. */
function region_label(DOMElement $region, int $n): string
{
    foreach (['data-edit-label', 'aria-label'] as $attr) {
        $v = trim($region->getAttribute($attr));
        if ($v !== '') {
            return $v;
        }
    }
    $xpath = new DOMXPath($region->ownerDocument);
    foreach ($xpath->query('.//h1|.//h2|.//h3', $region) as $h) {
        $text = preg_replace('/\s+/u', ' ', trim($h->textContent));
        if ($text !== '') {
            return mb_strlen($text) > 60 ? mb_substr($text, 0, 60) . '…' : $text;
        }
    }
    return 'Section ' . $n;
}

/**
 * Build the edit-form fields for a page: one rich-text field per region.
 * Field ids are s0, s1… by document order (guarded by the file hash).
 */
function collect_page_fields(DOMDocument $dom): array
{
    $fields = [];
    foreach (page_regions($dom) as $i => $region) {
        $fields[] = [
            'id' => 's' . $i,
            'label' => region_label($region, $i + 1),
            'html' => editor_display_html($region),
        ];
    }
    return $fields;
}

/**
 * Shared header/footer fields, read from the home page (they are identical
 * on every page; per-page differences like the highlighted current nav link
 * live inside protected <nav> elements, which are preserved per file).
 */
function collect_shared_fields(): array
{
    $fields = [];
    foreach (list_pages() as $page) {
        $abs = safe_page_path($page['rel']);
        if ($abs === null) {
            continue;
        }
        [$dom] = load_page_dom($abs);
        foreach ((new DOMXPath($dom))->query('//*[@data-edit-shared]') as $el) {
            /** @var DOMElement $el */
            $key = trim($el->getAttribute('data-edit-shared'));
            if ($key === '' || preg_match('/^[\w-]+$/', $key) !== 1 || isset($fields[$key])) {
                continue;
            }
            $fields[$key] = [
                'id' => $key,
                'label' => trim($el->getAttribute('data-edit-label')) ?: $key,
                'html' => editor_display_html($el),
            ];
        }
        if ($fields) {
            break; // first page (Home) has them all — no need to scan more
        }
    }
    return array_values($fields);
}

/* ── Saving ─────────────────────────────────────────────────────────────── */

/** Save one page's submitted sections. Returns how many changed. */
function save_page(string $abs, string $expectedHash, array $posted): int
{
    [$dom, $hash] = load_page_dom($abs);
    if (!hash_equals($expectedHash, $hash)) {
        throw new RuntimeException(
            'This page changed since you opened it (maybe in another window). ' .
            'Nothing was saved — please go back, reopen the page, and try again.'
        );
    }

    $changed = 0;
    foreach (page_regions($dom) as $i => $region) {
        $id = 's' . $i;
        if (!array_key_exists($id, $posted)) {
            continue;
        }
        $raw = (string) $posted[$id];
        if (strlen($raw) > 400000) {
            throw new RuntimeException('One of the sections is too large to save.');
        }
        if (apply_region_edit($region, $raw)) {
            $changed++;
        }
    }

    if ($changed === 0) {
        return 0;
    }
    backup_page($abs);
    atomic_write($abs, dom_to_html($dom));
    return $changed;
}

/**
 * Save shared header/footer sections to EVERY page containing them.
 * Locked elements (nav, logo…) are restored per file, so each page keeps
 * its own "you are here" nav highlighting. Returns [sections, files].
 */
function save_shared(array $posted): array
{
    $updates = [];
    foreach ($posted as $key => $raw) {
        $key = (string) $key;
        if (preg_match('/^[\w-]+$/', $key) !== 1) {
            continue;
        }
        if (strlen((string) $raw) > 400000) {
            throw new RuntimeException('One of the sections is too large to save.');
        }
        $updates[$key] = (string) $raw;
    }
    if (!$updates) {
        return [0, 0];
    }

    $filesChanged = 0;
    $sectionsChanged = [];
    foreach (list_pages() as $page) {
        $abs = safe_page_path($page['rel']);
        if ($abs === null) {
            continue;
        }
        [$dom] = load_page_dom($abs);
        $dirty = false;
        foreach ((new DOMXPath($dom))->query('//*[@data-edit-shared]') as $el) {
            /** @var DOMElement $el */
            $key = trim($el->getAttribute('data-edit-shared'));
            if (!isset($updates[$key])) {
                continue;
            }
            if (apply_region_edit($el, $updates[$key])) {
                $dirty = true;
                $sectionsChanged[$key] = true;
            }
        }
        if ($dirty) {
            backup_page($abs);
            atomic_write($abs, dom_to_html($dom));
            $filesChanged++;
        }
    }
    return [count($sectionsChanged), $filesChanged];
}

function atomic_write(string $abs, string $content): void
{
    $tmp = $abs . '.tmp-' . bin2hex(random_bytes(6));
    $bytes = @file_put_contents($tmp, $content);
    if ($bytes === false || $bytes !== strlen($content)) {
        @unlink($tmp);
        throw new RuntimeException('The changes could not be written (the server may be out of space). The page was NOT modified.');
    }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $abs)) {
        @unlink($tmp);
        throw new RuntimeException('The changes could not be moved into place. The page was NOT modified.');
    }
}

/* ── Backups & restore ──────────────────────────────────────────────────── */

function backup_prefix(string $rel): string
{
    return str_replace('/', '__', $rel);
}

function backup_page(string $abs): void
{
    $rel = str_replace('\\', '/', substr($abs, strlen(site_dir()) + 1));
    // Fixed-width microsecond suffix: names are unique even for saves in the
    // same second, and sorting filenames always equals sorting by time.
    $micro = sprintf('%06d', (int) (fmod(microtime(true), 1) * 1e6));
    $name = backup_prefix($rel) . '.' . date('Y-m-d_His') . '.' . $micro . '.bak';
    if (!@copy($abs, editor_backup_dir() . '/' . $name)) {
        throw new RuntimeException('A backup copy could not be made, so nothing was saved. Please try again.');
    }
    prune_backups($rel);
}

function prune_backups(string $rel): void
{
    $files = backups_for($rel);
    foreach (array_slice($files, EDITOR_BACKUPS_TO_KEEP) as $old) {
        @unlink($old);
    }
}

function backups_for(string $rel): array
{
    $pattern = editor_backup_dir() . '/' . backup_prefix($rel) . '.*.bak';
    $files = glob($pattern) ?: [];
    rsort($files);
    return $files;
}

function restore_latest_backup(string $abs, string $rel): void
{
    $backups = backups_for($rel);
    if (!$backups) {
        throw new RuntimeException('There is no earlier version of this page to go back to yet.');
    }
    $latest = $backups[0];
    $content = file_get_contents($latest);
    if ($content === false) {
        throw new RuntimeException('The earlier version could not be read.');
    }
    backup_page($abs);
    atomic_write($abs, $content);
    @unlink($latest);
}

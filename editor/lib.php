<?php
/**
 * Website editor — core helpers.
 *
 * Editing model (v2 — section-based):
 *   • Templates mark whole text sections with data-edit-section="Label".
 *     Each marked section becomes ONE rich-text field in the editor.
 *   • Content repeated on every page (footer blocks) is marked
 *     data-edit-shared="key" + data-edit-label="Label". Those never appear
 *     on page screens; they are edited once, on the "Header & footer"
 *     screen, and the save is applied to every page file.
 *   • Anything unmarked (navigation, buttons, maps, church-data) is not
 *     editable at all — the editor cannot break layout or scripts.
 *
 * Safety model (unchanged from v1):
 *   • Only .html files inside EDITOR_SITE_DIR can be read or written.
 *   • Submitted HTML passes a strict whitelist sanitizer (text tags only,
 *     safe link addresses, no scripts/styles/embeds/event handlers).
 *   • Every write: timestamped backup first (last 10 kept), then temp file
 *     + atomic rename — a failed save can never corrupt a page.
 */

declare(strict_types=1);

require __DIR__ . '/config.php';

/* ── Error handling: never show raw PHP errors to the user ─────────────── */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/._data/error.log');

/** Directory for the editor's private files (lockout counter, error log). */
function editor_data_dir(): string
{
    $dir = __DIR__ . '/._data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}

/** Directory where page backups are stored. */
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

    // Idle timeout: quietly log out after EDITOR_SESSION_MINUTES of inactivity.
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

/** Simple sitewide brute-force brake: N wrong passwords → short lockout. */
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

/**
 * Turn an untrusted relative page path (e.g. "about/index.html") into a safe
 * absolute path, or null if it is anything other than an existing .html file
 * inside the site folder.
 */
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

/**
 * Find all site pages: every .html file in the site folder, skipping the
 * editor itself, backups, and hidden folders. Sorted with Home first.
 */
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
                    return false; // hidden / private folders (._backups, _astro…)
                }
                if ($file->isDir() && strtolower($name) === 'editor') {
                    return false; // never edit the editor itself
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
        ];
    }
    usort($pages, function (array $a, array $b): int {
        if ($a['rel'] === 'index.html') return -1;
        if ($b['rel'] === 'index.html') return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    return $pages;
}

/** Friendly page name: prefer the <title>, trimmed of the site-name suffix. */
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

/** Plain-terms location, e.g. "churches › bethel-baptist-snead". */
function page_friendly_where(string $rel): string
{
    $dir = dirname($rel);
    if ($dir === '.' || $dir === '') {
        return 'Main site';
    }
    return str_replace('/', ' › ', $dir);
}

/* ── HTML parsing (DOMDocument, UTF-8 safe) ─────────────────────────────── */

/**
 * Load a page into a DOMDocument without mangling UTF-8 text.
 * Returns [DOMDocument, md5-of-file]. The md5 is embedded in the edit form
 * and re-checked on save so edits are never applied to a file that changed
 * after the form was opened.
 */
function load_page_dom(string $abs): array
{
    $html = file_get_contents($abs);
    if ($html === false) {
        throw new RuntimeException('Could not read the page file.');
    }
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    libxml_use_internal_errors(true); // modern HTML5 tags trigger harmless warnings
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

/**
 * libxml writes non-ASCII characters as HTML entities (— becomes &mdash; and
 * so on). Decode them back to raw UTF-8, keeping the structural escapes
 * (&amp; &lt; &gt; &quot; &#39;) and skipping <script>/<style> contents.
 */
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

/** Serialize a whole document back to an HTML string (UTF-8, prolog stripped). */
function dom_to_html(DOMDocument $dom): string
{
    $out = $dom->saveHTML();
    if ($out === false) {
        throw new RuntimeException('Could not rebuild the page.');
    }
    $out = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $out);
    return decode_text_entities($out);
}

/** The inner HTML of an element (its contents, not the element itself). */
function inner_html(DOMElement $el): string
{
    $out = '';
    foreach ($el->childNodes as $child) {
        $out .= $el->ownerDocument->saveHTML($child);
    }
    return decode_text_entities($out);
}

/* ── Editable sections ──────────────────────────────────────────────────── */

/**
 * The page's own editable sections, in document order:
 * elements marked data-edit-section, excluding anything inside a shared
 * (header/footer) region. Each becomes one rich-text field.
 *
 * Field ids are r0, r1… by document order — deterministic on an unchanged
 * file (guarded by the md5 check), which is how submitted fields find their
 * way back to the right section.
 */
function collect_section_fields(DOMDocument $dom): array
{
    $xpath = new DOMXPath($dom);
    $fields = [];
    $labelCounts = [];
    $i = 0;
    foreach ($xpath->query('//*[@data-edit-section]') as $el) {
        /** @var DOMElement $el */
        $index = $i++;
        if (has_ancestor_with_attr($el, 'data-edit-shared') || has_ancestor_with_attr($el->parentNode, 'data-edit-section')) {
            continue; // inside shared content or a nested section — skip
        }
        $label = trim($el->getAttribute('data-edit-section')) ?: 'Text section';
        $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;
        if ($labelCounts[$label] > 1) {
            $label .= ' (' . $labelCounts[$label] . ')';
        }
        $fields[] = [
            'id' => 'r' . $index,
            'label' => $label,
            'html' => sanitize_fragment_html(inner_html($el)),
        ];
    }
    return $fields;
}

/** True if the node or any ancestor element carries the given attribute. */
function has_ancestor_with_attr(?DOMNode $node, string $attr): bool
{
    for ($n = $node; $n instanceof DOMElement; $n = $n->parentNode) {
        if ($n->hasAttribute($attr)) {
            return true;
        }
    }
    return false;
}

/**
 * Shared (header/footer) sections gathered across the whole site, keyed by
 * their data-edit-shared value. The first page that contains a key provides
 * its label and current content (pages are scanned Home-first, and the
 * footer is identical everywhere anyway).
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
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//*[@data-edit-shared]') as $el) {
            /** @var DOMElement $el */
            $key = trim($el->getAttribute('data-edit-shared'));
            if ($key === '' || preg_match('/^[\w-]+$/', $key) !== 1 || isset($fields[$key])) {
                continue;
            }
            $fields[$key] = [
                'id' => $key,
                'label' => trim($el->getAttribute('data-edit-label')) ?: $key,
                'html' => sanitize_fragment_html(inner_html($el)),
            ];
        }
    }
    return array_values($fields);
}

/* ── Sanitizer ──────────────────────────────────────────────────────────── */
/* Submitted rich text passes through this whitelist. Anything not listed
   is either unwrapped (harmless wrappers keep their text) or dropped
   entirely (scripts, styles, embeds…). Event handlers never survive
   because only the attributes below are copied.                          */

const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'b', 'i', 'u', 's', 'a',
    'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'span'];
const DROPPED_TAGS = ['script', 'style', 'svg', 'iframe', 'object', 'embed',
    'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'img',
    'video', 'audio', 'canvas', 'noscript', 'template', 'base'];

/** Is this style attribute value harmless? (colors/fonts yes; url()/expression() no) */
function style_value_safe(string $value): bool
{
    return stripos($value, 'url(') === false
        && stripos($value, 'expression(') === false
        && stripos($value, 'javascript') === false
        && strlen($value) <= 500;
}

/** Is this link target safe? http(s), mailto, tel, and site-relative only. */
function href_value_safe(string $value): bool
{
    $v = trim($value);
    if ($v === '' || strlen($v) > 2000) {
        return false;
    }
    if (preg_match('#^(https?:)?//#i', $v) || preg_match('#^(mailto|tel):#i', $v)) {
        return true;
    }
    // Relative link (starts with /, ./, #, ?, or a plain path) with no scheme.
    return !preg_match('#^[a-z][a-z0-9+.-]*:#i', $v);
}

/**
 * Sanitize an untrusted HTML fragment against the whitelist.
 * Returns clean fragment HTML (UTF-8), safe to place into a page.
 */
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

/** Walk source children, copying only whitelisted structure into $target. */
function sanitize_children(DOMNode $src, DOMNode $target, DOMDocument $out): void
{
    foreach ($src->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            $target->appendChild($out->createTextNode($child->nodeValue ?? ''));
            continue;
        }
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue; // comments, PIs etc. are dropped
        }
        /** @var DOMElement $child */
        $tag = strtolower($child->nodeName);
        if (in_array($tag, DROPPED_TAGS, true)) {
            continue; // dangerous element — dropped with its contents
        }
        if (!in_array($tag, ALLOWED_TAGS, true)) {
            // Unknown wrapper (div, figure…): keep its contents, lose the tag.
            sanitize_children($child, $target, $out);
            continue;
        }
        $el = $out->createElement($tag);
        // Copy only known-harmless attributes.
        if ($child->hasAttribute('class')) {
            $class = $child->getAttribute('class');
            if (strlen($class) <= 300 && preg_match('/^[\w\s\/\[\]().:%#!-]*$/u', $class)) {
                $el->setAttribute('class', $class);
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

/** Replace an element's children with a sanitized HTML fragment. */
function set_inner_html(DOMElement $el, string $cleanFragment): void
{
    while ($el->firstChild) {
        $el->removeChild($el->firstChild);
    }
    if (trim($cleanFragment) === '') {
        return;
    }
    $tmp = new DOMDocument();
    libxml_use_internal_errors(true);
    $tmp->loadHTML('<?xml encoding="utf-8" ?><div id="__frag__">' . $cleanFragment . '</div>');
    libxml_clear_errors();
    $frag = (new DOMXPath($tmp))->query('//div[@id="__frag__"]')->item(0);
    if (!($frag instanceof DOMElement)) {
        return;
    }
    foreach (iterator_to_array($frag->childNodes) as $child) {
        $el->appendChild($el->ownerDocument->importNode($child, true));
    }
}

/** Whitespace-insensitive comparison key for "did this section change?". */
function fragment_signature(string $html): string
{
    return preg_replace('/\s+/u', ' ', trim($html)) ?? $html;
}

/* ── Saving ─────────────────────────────────────────────────────────────── */

/**
 * Apply submitted section values to one page and save it.
 * Returns the number of sections that actually changed.
 */
function save_page(string $abs, string $expectedHash, array $posted): int
{
    [$dom, $hash] = load_page_dom($abs);
    if (!hash_equals($expectedHash, $hash)) {
        throw new RuntimeException(
            'This page changed since you opened it (maybe in another window). ' .
            'Nothing was saved — please go back, reopen the page, and try again.'
        );
    }

    $xpath = new DOMXPath($dom);
    $changed = 0;
    $i = 0;
    foreach ($xpath->query('//*[@data-edit-section]') as $el) {
        /** @var DOMElement $el */
        $id = 'r' . $i++;
        if (!array_key_exists($id, $posted)) {
            continue;
        }
        if (has_ancestor_with_attr($el, 'data-edit-shared') || has_ancestor_with_attr($el->parentNode, 'data-edit-section')) {
            continue;
        }
        $raw = (string) $posted[$id];
        if (strlen($raw) > 200000) {
            throw new RuntimeException('One of the sections is too large to save.');
        }
        $clean = sanitize_fragment_html($raw);
        if (fragment_signature($clean) === fragment_signature(sanitize_fragment_html(inner_html($el)))) {
            continue; // unchanged
        }
        set_inner_html($el, $clean);
        $changed++;
    }

    if ($changed === 0) {
        return 0;
    }
    backup_page($abs);
    atomic_write($abs, dom_to_html($dom));
    return $changed;
}

/**
 * Apply shared (header/footer) section values to EVERY page that contains
 * them. Each modified file is backed up first. Returns [sections, files].
 */
function save_shared(array $posted): array
{
    // Sanitize each submitted shared value once, keyed by its marker.
    $updates = [];
    foreach ($posted as $key => $raw) {
        $key = (string) $key;
        if (preg_match('/^[\w-]+$/', $key) !== 1) {
            continue;
        }
        if (strlen((string) $raw) > 200000) {
            throw new RuntimeException('One of the sections is too large to save.');
        }
        $updates[$key] = sanitize_fragment_html((string) $raw);
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
        $xpath = new DOMXPath($dom);
        $dirty = false;
        foreach ($xpath->query('//*[@data-edit-shared]') as $el) {
            /** @var DOMElement $el */
            $key = trim($el->getAttribute('data-edit-shared'));
            if (!isset($updates[$key])) {
                continue;
            }
            if (fragment_signature($updates[$key]) === fragment_signature(sanitize_fragment_html(inner_html($el)))) {
                continue;
            }
            set_inner_html($el, $updates[$key]);
            $dirty = true;
            $sectionsChanged[$key] = true;
        }
        if ($dirty) {
            backup_page($abs);
            atomic_write($abs, dom_to_html($dom));
            $filesChanged++;
        }
    }
    return [count($sectionsChanged), $filesChanged];
}

/** Write content to a temp file in the same folder, then rename into place. */
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

/** Backup file prefix for a page, with the path flattened into the name. */
function backup_prefix(string $rel): string
{
    return str_replace('/', '__', $rel);
}

/** Copy the current file into ._backups/ with a timestamp; prune old ones. */
function backup_page(string $abs): void
{
    $rel = str_replace('\\', '/', substr($abs, strlen(site_dir()) + 1));
    $name = backup_prefix($rel) . '.' . date('Y-m-d_His') . '.bak';
    if (!@copy($abs, editor_backup_dir() . '/' . $name)) {
        throw new RuntimeException('A backup copy could not be made, so nothing was saved. Please try again.');
    }
    prune_backups($rel);
}

/** Keep only the newest EDITOR_BACKUPS_TO_KEEP backups for a page. */
function prune_backups(string $rel): void
{
    $files = backups_for($rel);
    foreach (array_slice($files, EDITOR_BACKUPS_TO_KEEP) as $old) {
        @unlink($old);
    }
}

/** All backups for a page, newest first. */
function backups_for(string $rel): array
{
    $pattern = editor_backup_dir() . '/' . backup_prefix($rel) . '.*.bak';
    $files = glob($pattern) ?: [];
    rsort($files);
    return $files;
}

/**
 * Restore the newest backup of a page. The current version is backed up
 * first, so pressing Undo again brings the change back.
 */
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

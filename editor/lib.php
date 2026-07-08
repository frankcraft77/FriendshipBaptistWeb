<?php
/**
 * Website editor — core helpers.
 * Plain PHP, no dependencies. Everything here is deliberately conservative:
 * the editor may only touch .html files inside EDITOR_SITE_DIR, it only ever
 * changes the *text* inside existing elements (never tags/attributes), it
 * backs up before every write, and it writes via temp-file + rename so a
 * failed save can never destroy a page.
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
        // Extra layer: refuse to serve anything from this folder.
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

/** HTML-escape helper used for ALL output of untrusted values. */
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
        // 'secure' is left to the server; Hostinger serves HTTPS by default.
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
/* The editor may only ever read/write .html files inside EDITOR_SITE_DIR.  */

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
    // realpath() resolved symlinks/dots — confirm it is still inside the site.
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
 * Find all editable pages: every .html file in the site folder, skipping the
 * editor itself, backups, and hidden folders. Returns a list of
 * ['rel' => ..., 'name' => ..., 'where' => ...] sorted with Home first.
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
                    return false; // hidden / private folders (._backups, ._data, _astro…)
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
            continue; // unusually huge file — leave it alone
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
        // "About — Friendship Baptist Association" → "About"
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
 * and re-checked on save so we never apply edits to a file that changed
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
    // The XML prolog trick tells libxml the bytes are UTF-8; we remove the
    // prolog node right after parsing so it never appears in the saved file.
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

/** Serialize the DOM back to an HTML string (UTF-8, prolog stripped). */
function dom_to_html(DOMDocument $dom): string
{
    $out = $dom->saveHTML();
    if ($out === false) {
        throw new RuntimeException('Could not rebuild the page.');
    }
    // Belt & braces: drop the encoding prolog if libxml re-added it.
    $out = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $out);

    // libxml writes non-ASCII characters as HTML entities (— becomes &mdash;
    // and so on), which would silently reformat the whole document. Decode
    // them back to the raw UTF-8 the build produced. The five structural
    // escapes (&amp; &lt; &gt; &quot; &#39;) are kept as-is so nothing a user
    // typed can ever turn back into markup, and <script>/<style> contents are
    // skipped entirely (the serializer leaves them raw already).
    return preg_replace_callback(
        '/(<(script|style)\b[^>]*>.*?<\/\2\s*>)|(&(?:#\d+|#x[0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]{1,31});)/s',
        function (array $m): string {
            if ($m[1] !== '') {
                return $m[1]; // script/style block — untouched
            }
            $entity = $m[3];
            $decoded = html_entity_decode($entity, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $entity || strpbrk($decoded, "<>&\"'") !== false) {
                return $entity; // unknown entity or structural escape — keep
            }
            return $decoded;
        },
        $out
    );
}

/* ── Finding editable text ──────────────────────────────────────────────── */

/**
 * Elements whose text is offered for editing in fallback mode (pages without
 * data-editable markers, or "show everything" mode).
 */
const FALLBACK_TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'li', 'a', 'span', 'blockquote', 'figcaption'];

/** Tags whose contents must never be offered as editable text. */
const NEVER_EDIT_INSIDE = ['script', 'style', 'title', 'noscript', 'template', 'head'];

/**
 * Walk the whole document once, in document order, numbering every
 * non-whitespace text node. The numbering is deterministic, so the same walk
 * on an unchanged file (guarded by the md5 check) yields the same numbers —
 * that is how form fields find their way back to the exact text node.
 *
 * Returns: list of ['index' => int, 'node' => DOMText, 'parent' => DOMElement]
 */
function walk_text_nodes(DOMDocument $dom): array
{
    $found = [];
    $index = 0;
    $body = $dom->getElementsByTagName('body')->item(0) ?? $dom->documentElement;
    if ($body === null) {
        return $found;
    }
    $walk = function (DOMNode $node) use (&$walk, &$found, &$index): void {
        if ($node->nodeType === XML_ELEMENT_NODE
            && in_array(strtolower($node->nodeName), NEVER_EDIT_INSIDE, true)) {
            return;
        }
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                if (trim($child->nodeValue ?? '') !== '') {
                    $found[] = [
                        'index' => $index,
                        'node' => $child,
                        'parent' => $node,
                    ];
                }
                $index++; // count every text node, even whitespace, for stability
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $walk($child);
            }
        }
    };
    $walk($body);
    return $found;
}

/** True if $el or any ancestor carries data-editable. */
function inside_editable(DOMNode $el): ?DOMElement
{
    for ($n = $el; $n instanceof DOMElement; $n = $n->parentNode) {
        if ($n->hasAttribute('data-editable')) {
            return $n;
        }
        if (!($n->parentNode instanceof DOMElement)) {
            break;
        }
    }
    return null;
}

/** Human word for a tag, used to label fallback fields. */
function tag_word(string $tag): string
{
    return match (strtolower($tag)) {
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => 'Heading',
        'p' => 'Paragraph',
        'li' => 'List item',
        'a' => 'Link',
        'blockquote' => 'Quote',
        'figcaption' => 'Caption',
        default => 'Text',
    };
}

/**
 * Build the list of editable form fields for a page.
 *
 * $mode 'marked' → only text inside data-editable elements (preferred; used
 *                  automatically when the page has any markers).
 * $mode 'all'    → text of common text elements (h1–h6, p, li, a, span, …).
 *
 * Each field: ['id' => 't12', 'label' => ..., 'value' => trimmed text,
 *              'long' => bool]
 */
function collect_fields(DOMDocument $dom, string $mode): array
{
    $fields = [];
    $labelCounts = [];
    foreach (walk_text_nodes($dom) as $item) {
        /** @var DOMElement $parent */
        $parent = $item['parent'];
        // Whitespace runs collapse when a browser renders HTML, so collapsing
        // them in the form too is faithful — and far easier to read/edit.
        $text = normalize_ws($item['node']->nodeValue ?? '');

        $marked = inside_editable($parent);
        if ($mode === 'marked') {
            if ($marked === null) {
                continue;
            }
            $label = $marked->getAttribute('data-editable-label');
            if ($label === '') {
                $label = tag_word($marked->nodeName) . ' — “' . snippet($text) . '”';
            }
        } else {
            if (!in_array(strtolower($parent->nodeName), FALLBACK_TAGS, true)) {
                continue;
            }
            $label = tag_word($parent->nodeName) . ' — “' . snippet($text) . '”';
        }

        // A marked element with several text parts gets "(part 2)", "(part 3)"…
        $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;
        if ($labelCounts[$label] > 1) {
            $label .= ' (part ' . $labelCounts[$label] . ')';
        }

        $fields[] = [
            'id' => 't' . $item['index'],
            'label' => $label,
            'value' => $text,
            'long' => mb_strlen($text) > 90,
        ];
    }
    return $fields;
}

/** Collapse whitespace runs to single spaces and trim (HTML-rendering-faithful). */
function normalize_ws(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

/** Short preview used inside auto-generated field labels. */
function snippet(string $text): string
{
    $text = preg_replace('/\s+/u', ' ', $text);
    return mb_strlen($text) > 40 ? mb_substr($text, 0, 40) . '…' : $text;
}

/** Does this page have any data-editable markers? */
function page_has_markers(DOMDocument $dom): bool
{
    $xpath = new DOMXPath($dom);
    return $xpath->query('//*[@data-editable]')->length > 0;
}

/* ── Saving ─────────────────────────────────────────────────────────────── */

/**
 * Apply submitted field values to the page and save it.
 * - Only text nodes change; tags and attributes are never touched.
 * - The current file is backed up first.
 * - Writing is temp-file + atomic rename, so a failure cannot corrupt the page.
 *
 * Returns the number of fields that actually changed.
 */
function save_page(string $abs, string $expectedHash, string $mode, array $posted): int
{
    [$dom, $hash] = load_page_dom($abs);
    if (!hash_equals($expectedHash, $hash)) {
        throw new RuntimeException(
            'This page changed since you opened it (maybe in another window). ' .
            'Nothing was saved — please go back, reopen the page, and try again.'
        );
    }

    $changed = 0;
    foreach (walk_text_nodes($dom) as $item) {
        $id = 't' . $item['index'];
        if (!array_key_exists($id, $posted)) {
            continue;
        }
        // Re-apply the same eligibility rules as when the form was built, so a
        // hand-crafted POST cannot address text the form never offered.
        $parent = $item['parent'];
        if ($mode === 'marked') {
            if (inside_editable($parent) === null) {
                continue;
            }
        } elseif (!in_array(strtolower($parent->nodeName), FALLBACK_TAGS, true)) {
            continue;
        }

        $new = (string) $posted[$id];
        if (mb_strlen($new) > 8000) {
            throw new RuntimeException('One of the text fields is too long to save.');
        }
        // These fields are plain text within one element — line breaks typed
        // in the box become spaces, matching how the page would render them.
        $new = normalize_ws($new);

        $old = $item['node']->nodeValue ?? '';
        if (normalize_ws($old) === $new) {
            continue; // unchanged (ignoring whitespace runs, as a browser would)
        }
        // Preserve the original surrounding whitespace so the file's
        // indentation/formatting stays exactly as the build produced it.
        preg_match('/^\s*/', $old, $pre);
        preg_match('/\s*$/', $old, $post);
        $item['node']->nodeValue = ($pre[0] ?? '') . $new . ($post[0] ?? '');
        $changed++;
    }

    if ($changed === 0) {
        return 0;
    }

    backup_page($abs);
    atomic_write($abs, dom_to_html($dom));
    return $changed;
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
    rsort($files); // timestamped names sort chronologically
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
    backup_page($abs);           // current state becomes the newest backup
    atomic_write($abs, $content); // then the old version takes its place
    @unlink($latest);             // consumed — prevents undo ping-ponging on itself
}

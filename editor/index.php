<?php
/**
 * Website editor — the whole app.
 * Screens: login → page list → edit a page (one rich-text field per marked
 * section) → save / undo. A special "Header & footer" screen edits the
 * content that repeats on every page and applies the change site-wide.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

editor_session_start();

$action = (string) ($_REQUEST['action'] ?? '');
$notice = '';
$error = '';

if (!empty($_SESSION['flash_error'])) {
    $error = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
if (!empty($_SESSION['flash_notice'])) {
    $notice = (string) $_SESSION['flash_notice'];
    unset($_SESSION['flash_notice']);
}

/* ── Actions ────────────────────────────────────────────────────────────── */

try {
    if ($action === 'logout') {
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $wait = lockout_active();
        if ($wait > 0) {
            $error = 'Too many attempts. Please wait ' . ceil($wait / 60) . ' minutes and try again.';
        } elseif (try_login((string) ($_POST['password'] ?? ''))) {
            header('Location: index.php');
            exit;
        } else {
            $error = "That password didn't work. Try again.";
        }
    }

    // Save one page's sections
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
        if (!csrf_valid()) {
            $error = 'That form had expired. Your changes were not saved — please try again.';
        } else {
            $rel = (string) ($_POST['page'] ?? '');
            $abs = safe_page_path($rel);
            if ($abs === null) {
                $error = 'That page could not be found.';
            } else {
                $changed = save_page($abs, (string) ($_POST['filehash'] ?? ''), $_POST['field'] ?? []);
                $_SESSION['flash_notice'] = $changed > 0
                    ? 'Saved. Your changes are live on the website. (A backup of the previous version was kept.)'
                    : 'Nothing had changed, so nothing was saved.';
                header('Location: index.php?action=edit&page=' . rawurlencode($rel));
                exit;
            }
        }
    }

    // Save shared header/footer sections → applied to every page
    if ($action === 'save-shared' && $_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
        if (!csrf_valid()) {
            $error = 'That form had expired. Your changes were not saved — please try again.';
        } else {
            [$sections, $files] = save_shared($_POST['field'] ?? []);
            $_SESSION['flash_notice'] = $sections > 0
                ? "Saved. The change is live on all $files pages of the website. (Backups were kept.)"
                : 'Nothing had changed, so nothing was saved.';
            header('Location: index.php?action=edit-shared');
            exit;
        }
    }

    // Undo last change to one page
    if ($action === 'restore' && $_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
        if (!csrf_valid()) {
            $error = 'That form had expired — please try again.';
        } else {
            $rel = (string) ($_POST['page'] ?? '');
            $abs = safe_page_path($rel);
            if ($abs === null) {
                $error = 'That page could not be found.';
            } else {
                restore_latest_backup($abs, $rel);
                $_SESSION['flash_notice'] = 'Done — the page was put back to its previous version. (Pressing Undo again brings the change back.)';
                header('Location: index.php?action=edit&page=' . rawurlencode($rel));
                exit;
            }
        }
    }
} catch (Throwable $ex) {
    error_log('[editor] ' . $ex->getMessage());
    $error = $ex instanceof RuntimeException
        ? $ex->getMessage()
        : 'Sorry — something went wrong on the server. Nothing was changed.';
}

/* ── Shared page chrome ─────────────────────────────────────────────────── */

function page_head(string $title): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex, nofollow">';
    echo '<title>' . e($title) . '</title>';
    echo '<link rel="stylesheet" href="editor.css">';
    echo '</head><body>';
}

function page_foot(): void
{
    echo '<script src="editor.js"></script></body></html>';
}

function show_messages(string $notice, string $error): void
{
    if ($notice !== '') {
        echo '<div class="msg msg-ok" role="status">' . e($notice) . '</div>';
    }
    if ($error !== '') {
        echo '<div class="msg msg-bad" role="alert">' . e($error) . '</div>';
    }
}

function show_warning(): void
{
    echo '<p class="warning">These edits change the live website right away. If the website is ever rebuilt and re-uploaded from the original design files, edits made here will be replaced. For permanent changes, ask your developer to update the original files too.</p>';
}

/** One rich-text field: label + toolbar + editable area (synced by editor.js). */
function render_rich_field(string $id, string $label, string $html): void
{
    echo '<div class="field">';
    echo '<label id="label-' . e($id) . '">' . e($label) . '</label>';
    echo '<div class="rte-toolbar" data-for="' . e($id) . '">';
    echo '<button type="button" data-cmd="bold" title="Bold"><strong>B</strong></button>';
    echo '<button type="button" data-cmd="italic" title="Italic"><em>I</em></button>';
    echo '<button type="button" data-cmd="formatH2" title="Heading">Heading</button>';
    echo '<button type="button" data-cmd="formatP" title="Normal text">Normal</button>';
    echo '<button type="button" data-cmd="insertUnorderedList" title="Bulleted list">&bull; list</button>';
    echo '<button type="button" data-cmd="createLink" title="Add a link">link</button>';
    echo '<button type="button" data-cmd="removeFormat" title="Remove formatting">clear</button>';
    echo '</div>';
    // The sanitized section HTML is intentionally rendered (not escaped):
    // it is the page's own content, re-sanitized server-side on display.
    echo '<div class="rte" contenteditable="true" id="' . e($id) . '" aria-labelledby="label-' . e($id) . '">' . $html . '</div>';
    echo '<input type="hidden" name="field[' . e($id) . ']" data-rte-for="' . e($id) . '">';
    echo '</div>';
}

/* ── Screen: login ──────────────────────────────────────────────────────── */

if (!is_logged_in()) {
    page_head('Log in — Website editor');
    echo '<main class="wrap wrap-narrow">';
    echo '<h1>Friendship Baptist Association<br><span class="subtitle">Website editor</span></h1>';
    show_messages($notice, $error);
    if (EDITOR_PASSWORD_HASH === '') {
        echo '<div class="msg msg-bad">No password has been set up yet. Open <strong>make-password.php</strong> in your browser to create one (see the README).</div>';
    }
    echo '<form method="post" action="index.php?action=login" class="card">';
    echo '<label for="password">Password</label>';
    echo '<input type="password" id="password" name="password" autocomplete="current-password" autofocus required>';
    echo '<button type="submit" class="btn">Log in</button>';
    echo '</form>';
    echo '</main>';
    page_foot();
    exit;
}

/* ── Screen: edit the shared header & footer ────────────────────────────── */

if ($action === 'edit-shared') {
    $fields = collect_shared_fields();

    page_head('Edit: header & footer');
    echo '<main class="wrap">';
    echo '<p class="topbar"><a href="index.php">&larr; Back to pages</a>';
    echo '<a class="right" href="index.php?action=logout">Log out</a></p>';
    echo '<h1>Header &amp; footer</h1>';
    echo '<p class="hint">This text appears at the bottom of <strong>every</strong> page. Saving here updates the whole website at once.</p>';
    show_warning();
    show_messages($notice, $error);

    if (!$fields) {
        echo '<p>No shared sections were found on the site.</p>';
    } else {
        echo '<form method="post" action="index.php?action=save-shared" id="edit-form">';
        echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
        foreach ($fields as $f) {
            render_rich_field($f['id'], $f['label'], $f['html']);
        }
        echo '<div class="savebar"><button type="submit" class="btn">Save changes</button>';
        echo '<span class="savebar-note">Applies to every page. Backups are kept.</span></div>';
        echo '</form>';
    }
    echo '</main>';
    page_foot();
    exit;
}

/* ── Screen: edit one page ──────────────────────────────────────────────── */

if ($action === 'edit') {
    $rel = (string) ($_GET['page'] ?? '');
    $abs = safe_page_path($rel);
    if ($abs === null) {
        $_SESSION['flash_error'] = 'That page could not be found.';
        header('Location: index.php');
        exit;
    }

    $isChurchPage = str_starts_with($rel, 'churches/');
    [$dom, $filehash] = load_page_dom($abs);
    $fields = $isChurchPage ? [] : collect_page_fields($dom);
    $niceName = page_friendly_name($abs, $rel);
    $hasBackups = count(backups_for($rel)) > 0;

    page_head('Edit: ' . $niceName);
    echo '<main class="wrap">';
    echo '<p class="topbar"><a href="index.php">&larr; Back to pages</a>';
    echo '<a class="right" href="index.php?action=logout">Log out</a></p>';
    echo '<h1>' . e($niceName) . '</h1>';
    show_warning();
    show_messages($notice, $error);

    if (!$fields) {
        if ($isChurchPage) {
            echo '<div class="msg msg-ok" style="background:#eef2f7;border-color:#b9c6d8;color:#2c3542;">';
            echo 'This church page is built from the <strong>church spreadsheet</strong> — to change its text, edit that church\'s row in the Google Sheet and rebuild the site (see the main README). That way the change is permanent.';
            echo '</div>';
        } else {
            echo '<p>This page is edited elsewhere — it has no regular text content of its own.</p>';
        }
    } else {
        echo '<form method="post" action="index.php?action=save" id="edit-form">';
        echo '<noscript><p class="msg msg-bad">This editor needs JavaScript turned on to save changes.</p></noscript>';
        echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
        echo '<input type="hidden" name="page" value="' . e($rel) . '">';
        echo '<input type="hidden" name="filehash" value="' . e($filehash) . '">';
        foreach ($fields as $f) {
            render_rich_field($f['id'], $f['label'], $f['html']);
        }
        echo '<div class="savebar"><button type="submit" class="btn">Save changes</button>';
        echo '<span class="savebar-note">A backup is kept every time you save.</span></div>';
        echo '</form>';
    }

    if ($hasBackups) {
        echo '<form method="post" action="index.php?action=restore" class="undo-form" data-confirm="Put this page back to its previous version?">';
        echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
        echo '<input type="hidden" name="page" value="' . e($rel) . '">';
        echo '<button type="submit" class="btn btn-quiet">Undo last change to this page</button>';
        echo '</form>';
    }

    echo '</main>';
    page_foot();
    exit;
}

/* ── Screen: page list (default) ────────────────────────────────────────── */

page_head('Website editor — choose a page');
echo '<main class="wrap">';
echo '<p class="topbar"><span></span><a class="right" href="index.php?action=logout">Log out</a></p>';
echo '<h1>Choose a page to edit</h1>';
show_warning();
show_messages($notice, $error);

echo '<ul class="pagelist">';
// The shared header/footer content, edited once for the whole site.
echo '<li><a href="index.php?action=edit-shared">';
echo '<strong>Header &amp; footer</strong>';
echo '<span>Text that repeats on every page — edited here once</span>';
echo '</a></li>';

$allPages = list_pages();
foreach ($allPages as $p) {
    if ($p['sheet_managed']) {
        continue;
    }
    echo '<li><a href="index.php?action=edit&page=' . rawurlencode($p['rel']) . '">';
    echo '<strong>' . e($p['name']) . '</strong>';
    echo '<span>' . e($p['where']) . '</span>';
    echo '</a></li>';
}
echo '</ul>';

$churchPages = array_filter($allPages, fn($p) => $p['sheet_managed']);
if ($churchPages) {
    echo '<h2 class="grouphead">Church pages</h2>';
    echo '<p class="hint">These are built from the church spreadsheet — edit a church\'s row in the Google Sheet (and rebuild) to change them.</p>';
    echo '<ul class="pagelist pagelist-muted">';
    foreach ($churchPages as $p) {
        echo '<li><a href="index.php?action=edit&page=' . rawurlencode($p['rel']) . '">';
        echo '<strong>' . e($p['name']) . '</strong>';
        echo '<span>edited in the church spreadsheet</span>';
        echo '</a></li>';
    }
    echo '</ul>';
}
echo '</main>';
page_foot();

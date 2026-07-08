<?php
/**
 * Website editor — the whole app.
 * Screens: login → page list → edit form → save / undo.
 * All heavy lifting lives in lib.php; this file is routing + HTML.
 */

declare(strict_types=1);

require __DIR__ . '/lib.php';

editor_session_start();

$action = (string) ($_REQUEST['action'] ?? '');
$notice = '';       // green "it worked" message
$error = '';        // red "something went wrong" message

if (!empty($_SESSION['flash_error'])) {
    $error = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
if (!empty($_SESSION['flash_notice'])) {
    $notice = (string) $_SESSION['flash_notice'];
    unset($_SESSION['flash_notice']);
}

/* ── Actions (POST first, then which screen to show) ────────────────────── */

try {
    // Log out
    if ($action === 'logout') {
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit;
    }

    // Log in
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

    // Save changes
    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
        if (!csrf_valid()) {
            $error = 'That form had expired. Your changes were not saved — please try again.';
        } else {
            $rel = (string) ($_POST['page'] ?? '');
            $abs = safe_page_path($rel);
            $mode = ($_POST['mode'] ?? '') === 'all' ? 'all' : 'marked';
            if ($abs === null) {
                $error = 'That page could not be found.';
            } else {
                $changed = save_page($abs, (string) ($_POST['filehash'] ?? ''), $mode, $_POST['field'] ?? []);
                $_SESSION['flash_notice'] = $changed > 0
                    ? 'Saved. Your changes are live on the website. (A backup of the previous version was kept.)'
                    : 'Nothing had changed, so nothing was saved.';
                header('Location: index.php?action=edit&page=' . rawurlencode($rel) . ($mode === 'all' ? '&mode=all' : ''));
                exit;
            }
        }
    }

    // Undo last change
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

/* ── Screen rendering ───────────────────────────────────────────────────── */

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

/* ── Screen: edit a page ────────────────────────────────────────────────── */

if ($action === 'edit') {
    $rel = (string) ($_GET['page'] ?? '');
    $abs = safe_page_path($rel);
    if ($abs === null) {
        $_SESSION['flash_error'] = 'That page could not be found.';
        header('Location: index.php');
        exit;
    }

    [$dom, $filehash] = load_page_dom($abs);
    $hasMarkers = page_has_markers($dom);
    $mode = (($_GET['mode'] ?? '') === 'all' || !$hasMarkers) ? 'all' : 'marked';
    $fields = collect_fields($dom, $mode);
    $niceName = page_friendly_name($abs, $rel);
    $hasBackups = count(backups_for($rel)) > 0;

    page_head('Edit: ' . $niceName);
    echo '<main class="wrap">';
    echo '<p class="topbar"><a href="index.php">&larr; Back to pages</a>';
    echo '<a class="right" href="index.php?action=logout">Log out</a></p>';

    echo '<h1>' . e($niceName) . '</h1>';
    echo '<p class="warning">These edits change the live website right away. If the website is ever rebuilt and re-uploaded from the original design files, edits made here will be replaced. For permanent changes, ask your developer to update the original files too.</p>';

    show_messages($notice, $error);

    if ($hasMarkers && $mode === 'marked') {
        echo '<p class="hint">Don&rsquo;t see the text you need? <a href="index.php?action=edit&page=' . rawurlencode($rel) . '&mode=all">Show every piece of text on this page</a>.</p>';
    } elseif ($hasMarkers && $mode === 'all') {
        echo '<p class="hint">Showing every piece of text. <a href="index.php?action=edit&page=' . rawurlencode($rel) . '">Show only the main fields instead</a>.</p>';
    }

    if (!$fields) {
        echo '<p>This page has no editable text.</p>';
    } else {
        echo '<form method="post" action="index.php?action=save" id="edit-form">';
        echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
        echo '<input type="hidden" name="page" value="' . e($rel) . '">';
        echo '<input type="hidden" name="mode" value="' . e($mode) . '">';
        echo '<input type="hidden" name="filehash" value="' . e($filehash) . '">';

        foreach ($fields as $f) {
            echo '<div class="field">';
            echo '<label for="' . e($f['id']) . '">' . e($f['label']) . '</label>';
            if ($f['long']) {
                echo '<textarea id="' . e($f['id']) . '" name="field[' . e($f['id']) . ']" rows="3">' . e($f['value']) . '</textarea>';
            } else {
                echo '<input type="text" id="' . e($f['id']) . '" name="field[' . e($f['id']) . ']" value="' . e($f['value']) . '">';
            }
            echo '</div>';
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
echo '<p class="warning">These edits change the live website right away. If the website is ever rebuilt and re-uploaded from the original design files, edits made here will be replaced. For permanent changes, ask your developer to update the original files too.</p>';
show_messages($notice, $error);

$pages = list_pages();
if (!$pages) {
    echo '<p>No pages were found. Check that the editor folder sits inside the website folder (see the README).</p>';
} else {
    echo '<ul class="pagelist">';
    foreach ($pages as $p) {
        echo '<li><a href="index.php?action=edit&page=' . rawurlencode($p['rel']) . '">';
        echo '<strong>' . e($p['name']) . '</strong>';
        echo '<span>' . e($p['where']) . '</span>';
        echo '</a></li>';
    }
    echo '</ul>';
}
echo '</main>';
page_foot();

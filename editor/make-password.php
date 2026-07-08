<?php
/**
 * One-time helper: turns the password you choose into the scrambled code
 * (a "hash") that goes in config.php. It never stores or sends anything —
 * it only shows you the code to copy.
 *
 * IMPORTANT: delete this file from the server once your password is set.
 */

declare(strict_types=1);
ini_set('display_errors', '0');

$hash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $pw = (string) $_POST['password'];
    if (strlen($pw) >= 8) {
        $hash = password_hash($pw, PASSWORD_DEFAULT);
    }
}

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Set editor password</title>
  <link rel="stylesheet" href="editor.css">
</head>
<body>
<main class="wrap wrap-narrow">
  <h1>Set your editor password</h1>

  <?php if ($hash !== ''): ?>
    <div class="msg msg-ok">
      <p><strong>Step 1.</strong> Copy this whole line of code:</p>
      <p style="word-break: break-all; font-family: monospace; font-size: 0.85rem; background: #fdf9f1; padding: 0.6rem; border-radius: 8px;"><?= esc($hash) ?></p>
      <p><strong>Step 2.</strong> Open <strong>config.php</strong> (in the editor
      folder, using Hostinger's file manager) and paste it between the quotes:</p>
      <p style="font-family: monospace; font-size: 0.8rem;">define('EDITOR_PASSWORD_HASH', '<em>paste here</em>');</p>
      <p><strong>Step 3.</strong> Delete this file (<strong>make-password.php</strong>) from the server.</p>
    </div>
  <?php else: ?>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
      <div class="msg msg-bad">Please choose a password of at least 8 characters.</div>
    <?php endif; ?>
    <form method="post" class="card">
      <label for="password">Choose a password (at least 8 characters)</label>
      <input type="text" id="password" name="password" autocomplete="off" autofocus required minlength="8">
      <button type="submit" class="btn">Create the code</button>
    </form>
    <p class="hint">Nothing is saved by this page — it only shows you a code to
    copy into config.php. Delete this file when you're done.</p>
  <?php endif; ?>
</main>
</body>
</html>

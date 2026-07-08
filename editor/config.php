<?php
/**
 * Website editor — settings.
 * This is the ONE file you customize. Two things to check:
 *
 *   1) EDITOR_PASSWORD_HASH — your login password, in scrambled (hashed) form.
 *      To set it: open  yoursite.com/editor/make-password.php  in a browser,
 *      type the password you want, and copy the long code it gives you
 *      between the quotes below. (Then delete make-password.php.)
 *
 *   2) EDITOR_SITE_DIR — the folder that holds the website's files.
 *      The default (the folder just above /editor/) is correct when this
 *      editor folder sits inside public_html next to the site. Leave it alone
 *      unless a developer tells you otherwise.
 */

// Paste the generated password code between the quotes:
define('EDITOR_PASSWORD_HASH', '');

// Folder containing the site's .html files (default: one level up from /editor/).
define('EDITOR_SITE_DIR', dirname(__DIR__));

// Log out automatically after this many minutes of inactivity.
define('EDITOR_SESSION_MINUTES', 30);

// How many backup copies to keep per page.
define('EDITOR_BACKUPS_TO_KEEP', 10);

// After this many wrong passwords in a row, pause logins for this many minutes.
define('EDITOR_MAX_LOGIN_FAILS', 5);
define('EDITOR_LOCKOUT_MINUTES', 15);

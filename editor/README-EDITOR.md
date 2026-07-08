# Website editor — setup & how to use

A small, password-protected tool for changing the **text** on the website
without touching any code. It runs right on the Hostinger hosting — nothing
to install on your computer.

> ⚠️ **Please read this first**
> These edits change the live website files. If the site is ever rebuilt and
> re-uploaded from the original design files, edits made here will be
> replaced. For permanent changes, also tell your developer so they can
> update the original files. (The editor shows this same reminder on every
> screen.)

---

## 1. Put the editor on the server (one time)

1. Log in to Hostinger and open **hPanel → File Manager**.
2. Open the **`public_html`** folder (where the website's files live).
3. Upload the whole **`editor`** folder into `public_html`, so you end up
   with `public_html/editor/` containing `index.php`, `config.php`, and the
   other files. (Easiest: upload `editor.zip` and use **Extract**.)

## 2. Set your password (one time)

1. In your web browser, go to: `https://YOUR-SITE.org/editor/make-password.php`
2. Type the password you want to use (at least 8 characters) and press
   **Create the code**. You'll see a long line of scrambled code — that's
   your password in safe, encoded form. Copy it.
3. Back in Hostinger's File Manager, open `public_html/editor/` and
   right-click **config.php → Edit**. Paste the code between the quotes on
   this line, then save:

   ```php
   define('EDITOR_PASSWORD_HASH', 'PASTE-THE-CODE-HERE');
   ```

4. **Delete `make-password.php`** from the server (right-click → Delete).
   You won't need it again unless you want to change the password later —
   in that case, re-upload it, repeat these steps, and delete it again.

## 3. Everyday use

1. Go to `https://YOUR-SITE.org/editor/` and log in.
2. Pick the page you want to change from the list (pages are shown by
   name — "Home page", "About", and so on).
3. Change the text in the boxes. Each box is one piece of text on the page,
   labeled so you know what it is.
4. Press **Save changes** (the button stays at the bottom of the screen).
   You'll see: *"Saved. Your changes are live."* — and they are; refresh the
   website to see them.
5. Made a mistake? Press **"Undo last change to this page"** and confirm.
   The page goes back to how it was before your last save. (Pressing Undo
   again brings the change back, so nothing is ever lost by trying.)

Good to know:

- A **backup is kept automatically every time you save** (the last 10 per
  page), in a private folder the public can't see.
- The editor **only changes text**. It cannot move things around, change
  colors, or break the page layout — those need the original design files.
- You'll be logged out automatically after 30 minutes of inactivity.
- It works fine on a phone or tablet.

---

## For the developer

### Marking text as editable (recommended)

By default the editor falls back to listing *all* text in common elements
(headings, paragraphs, links…), which works but is noisy. For a clean,
labeled form, add markers in the Astro templates to each element whose text
should be editable:

```html
<h1 data-editable data-editable-label="Homepage headline">
  Churches together, for Blount County and the world
</h1>
<p data-editable data-editable-label="Homepage intro sentence">…</p>
```

- When a page contains any `data-editable` elements, the editor shows **only
  those**, using your labels — with a "show every piece of text" link as an
  escape hatch.
- Only the element's **text** is editable; tags and attributes are never
  touched. If a marked element contains child elements (e.g. a `<br>` or a
  link), each text fragment becomes its own field, so structure is always
  preserved.
- Rebuilding the site (`npm run build`) bakes the markers into the HTML the
  editor reads — nothing else to configure.

### How saving works (safety model)

- Pages are parsed with `DOMDocument` (UTF-8-safe); only targeted text nodes
  are modified; everything is HTML-escaped on display and set as text
  content on save, so form input can never inject markup or script.
- Requires PHP 8.0+ (Hostinger's defaults are newer than that).
- The first save of a page applies two harmless serializer normalizations
  (a newline after the doctype, lowercased SVG attribute names such as
  `viewbox`) — both are valid HTML and do not affect rendering; HTML parsers
  case-adjust SVG attributes automatically.
- Every save: backup to `editor/._backups/` (timestamped, last 10 kept,
  pruned) → write to a temp file → atomic `rename()` over the original. A
  failed write can never corrupt a page.
- A hash of the file is embedded in the form and checked on save, so edits
  are never applied to a file that changed after the form was opened.
- The editor refuses any path that isn't an existing `.html` file inside the
  site folder (no `..`, no absolute paths, symlinks resolved and re-checked).

### Security notes

- Login: PHP session + `password_hash()`/`password_verify()`; only the hash
  lives in `config.php`. CSRF token on every write. 30-minute idle timeout.
  5 wrong passwords → 15-minute lockout.
- `editor/.htaccess` denies web access to `config.php`, `lib.php`, backups,
  and logs; the `._backups/` and `._data/` folders also get their own
  deny-all `.htaccess` when created.
- **Optional second layer:** Hostinger supports directory password
  protection. In hPanel, look for **Website → Password Protect Directories**
  and put a password on the `/editor` folder — visitors then need that
  password *before* they even reach the editor's own login. Equivalent
  manual setup: an `.htpasswd` file plus this in `editor/.htaccess`:

  ```apache
  AuthType Basic
  AuthName "Editor"
  AuthUserFile /home/USERNAME/.htpasswd
  Require valid-user
  ```

  (Generate the `.htpasswd` line with any "htpasswd generator", and keep the
  file outside `public_html`.)

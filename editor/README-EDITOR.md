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
   To change the password later, re-upload it, repeat, and delete it again.

## 3. Everyday use

1. Go to `https://YOUR-SITE.org/editor/` and log in.
2. Pick what to edit:
   - **Header & footer** (top of the list) — the text that repeats at the
     top and bottom of every page. Change it here once and it updates the
     whole website.
   - **Any page** — shows only that page's own text, one comfortable box
     per section ("Top of page — headline and intro", "Welcome letter",
     and so on). Menus, buttons, and the footer never appear here, so
     nothing can be broken by accident.
3. Edit the text right in the box. Each box has simple formatting buttons:
   **B** (bold), *I* (italic), Heading, Normal, • list, link, and clear.
   Little 🔒 chips ("button", "icon", "form"…) mark parts of the page that
   can't be changed here — the text around them edits freely, and the real
   buttons and pictures stay exactly as they are.
4. Press **Save changes** (the button stays at the bottom of the screen).
   You'll see: *"Saved. Your changes are live."* — refresh the website to
   see them.
5. Made a mistake? Press **"Undo last change to this page"** and confirm.
   (Pressing Undo again brings the change back, so nothing is ever lost by
   trying.)

Good to know:

- A **backup is kept automatically every time you save** (the last 10 per
  page). Header & footer saves back up every page they touch.
- **Church pages** are built from the church spreadsheet — the editor will
  point you there instead, so those edits are permanent and survive
  rebuilds.
- You'll be logged out automatically after 30 minutes of inactivity.
- It works fine on a phone or tablet.

---

## For the developer

### No manual tagging — pages are editable automatically

The editor reads the site's own HTML structure; **nothing needs to be
marked page-by-page**:

- Per page, it looks only inside the `<main>` element. Each top-level
  `<section>` (or `<article>`) inside `<main>` becomes **one rich-text
  field**. A `<main>` with no sections becomes a single field. Any new page
  the build produces is instantly editable.
- Field labels come from the section's `data-edit-label` or `aria-label`
  if present (the templates set friendly ones), otherwise from the
  section's first heading — so even unlabeled sections get sensible names.
- Everything outside `<main>` (header, nav, footer, scripts) is ignored on
  page screens. The shared layout marks `<header data-edit-shared="header">`
  and `<footer data-edit-shared="footer">` once; the **Header & Footer**
  screen edits those and its save loops over every `.html` file, updating
  the matching region in each (every touched file is backed up first).
- `churches/*` pages are Sheet-managed: the picker segregates them and
  their edit screen points the user to the Google Sheet.

### Non-text elements are preserved, not stripped

Buttons, icons/SVGs, images, forms, embeds, scripts, and navigation inside
a section appear in the editor as small locked chips (🔒 button, 🔒 icon…).
They cannot be edited, and on save the **originals are re-extracted from
the live file and put back** — even if a chip was deleted in the browser,
the real element is restored (order preserved). User input can never
define, alter, or remove these elements; it can only edit the text around
them.

### Saving & sanitization

- Submitted rich text passes a server-side whitelist: text/structure tags
  (`p br headings lists a strong em span div section article figure …`)
  with only harmless attributes (`id`, `class`, safe `style`, `data-*`,
  `aria-*`, safe `href`). User-typed `script`/`iframe`/`img`/event handlers
  and `javascript:` links are stripped. The rich-text UI is a small
  vendored `contenteditable` toolbar (editor.js) — no CDN, no npm.
- Every write: timestamped backup to `editor/._backups/` (last 10 per page,
  microsecond-unique names) → temp file → atomic `rename()`. Page saves
  carry a file hash so a stale form can't clobber newer changes. An empty
  submission is treated as "no change" — it can never blank a section.
- Requires PHP 8.0+ (Hostinger's defaults are newer). The first save of a
  page applies two harmless serializer normalizations (a newline after the
  doctype, lowercased SVG attribute names) — valid HTML, no rendering
  change.

### Security

- PHP session login (`password_hash`/`password_verify`; only the hash is
  stored), CSRF token on every write, 30-minute idle timeout, 5 wrong
  passwords → 15-minute lockout, strict path rules (existing `.html` inside
  the site folder only; traversal and symlink escapes rejected).
- `editor/.htaccess` denies web access to `config.php`, `lib.php`, backups,
  and logs; `._backups/` and `._data/` get their own deny-all `.htaccess`.
- **Optional second layer:** Hostinger's **Password Protect Directories**
  on `/editor`, or the classic `.htpasswd` approach:

  ```apache
  AuthType Basic
  AuthName "Editor"
  AuthUserFile /home/USERNAME/.htpasswd
  Require valid-user
  ```

  (Keep `.htpasswd` outside `public_html`.)

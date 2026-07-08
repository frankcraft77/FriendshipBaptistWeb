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
     bottom of every page. Change it here once and it updates the whole
     website.
   - **Any page** — shows only that page's own text, one comfortable box
     per section ("Top of page — headline and intro", "Welcome letter",
     and so on). Menus, buttons, and the footer never appear here, so
     nothing can be broken by accident.
3. Edit the text right in the box. Each box has simple formatting buttons:
   **B** (bold), *I* (italic), • list, link, and clear.
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

### How editable sections are defined

The editor shows exactly the regions the templates mark — nothing else:

```html
<!-- One page section = one rich-text field. The attribute value is the
     label the user sees. Use class="contents" if the wrapper would
     otherwise disturb a flex/grid layout. -->
<div class="contents" data-edit-section="Top of page — headline and intro">
  <p class="eyebrow">About us</p>
  <h1>One family of churches, one mission</h1>
  <p class="lede">…</p>
</div>

<!-- Content repeated on every page (footer blocks). Edited once on the
     "Header & footer" screen; the save loops over every .html file and
     updates the matching region in each. -->
<div data-edit-shared="footer-about"
     data-edit-label="Footer — about the association (bottom of every page)">
  …
</div>
```

Rules of thumb:

- Mark **text-only containers** (headings, paragraphs, lists, links,
  spans). Don't include buttons, icons/SVGs, or images inside a marked
  region — the sanitizer strips anything that isn't text markup, so those
  would be lost on the first save of that section.
- Unmarked pages show a friendly "no editable sections" note; `churches/*`
  pages point the user at the Google Sheet instead.
- Rebuilding the site (`npm run build`) bakes the markers into the HTML;
  nothing else to configure.

### Saving & sanitization

- Submitted rich text passes a strict server-side whitelist: only
  `p br strong em b i u s a ul ol li h1–h4 blockquote span`; only `class`,
  harmless `style` values, and safe `href`s (`http/https/mailto/tel/`
  relative — never `javascript:`) survive; `script/style/svg/iframe/img/…`
  are dropped, unknown wrappers are unwrapped, and event-handler attributes
  never pass because attributes are copied from a whitelist.
- Every write: timestamped backup to `editor/._backups/` (last 10 per page,
  pruned) → temp file → atomic `rename()`. A failed save can't corrupt a
  page. Page saves also carry a file hash so a form opened before an
  external change can't clobber it.
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

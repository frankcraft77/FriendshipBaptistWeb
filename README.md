# Friendship Baptist Association — Website

A fast, low-maintenance public website for the **Friendship Baptist
Association** (Blount County, Alabama): a browsable directory of member
churches with a map and "Churches Near Me" search, a full mini-site for every
church, and complete association pages — all maintainable by a non-technical
owner through a Google Sheet.

---

## 👋 Start here — a 2-minute tour (for the owner)

Open the site and click through these, in order. **Every word of page copy is
editable sample content** written so you can see a *finished* site and react
to it — nothing is final, and the red "Sample content" banner disappears with
one switch when you're ready.

1. **Home** — the front door: mission, welcome, and quick paths to everything.
2. **Find a Church** — the centerpiece. Six real member churches are loaded.
   Try "Use my location" and the town filter. (Address autocomplete and the
   map pins switch on when the free Google Maps key is added — 15 minutes,
   instructions below.)
3. **Click any church card** — every church gets its own full page, in its
   own color, generated from one spreadsheet row. Sparse rows still look
   complete; richer rows grow richer pages.
4. **`/themes`** — a template church page with a dropdown to preview all 12
   color themes you can assign to churches.
5. **About, Ministries, Events, Get Involved, News, Resources, Give,
   Contact** — realistic sample copy throughout, marked with small "sample"
   tags where content is invented.

---

## How church information gets updated (no code, ever)

The church directory lives in a **Google Sheet** you edit like any
spreadsheet. The site reads it when it's built.

### One-time setup
1. Create a Google Sheet whose first row has these column headers (same as
   `src/data/churches.csv`):

   `name, address, latitude, longitude, photo, service_times, pastor, phone,
   email, website, facebook, slug, theme, about, photos, pastor_bio,
   pastor_photo, plan_your_visit, beliefs, ministries, denomination_note,
   founded, fun_fact, giving_url, livestream_url, calendar_url`

   Only **name** and **address** are required — everything else can be blank
   and can be filled in later. A church's page automatically grows new
   sections as its cells get filled in.
2. In the Sheet: **File → Share → Publish to web → select "Comma-separated
   values (.csv)" → Publish**, and copy the link.
3. Paste that link into `.env` as `CHURCHES_SHEET_CSV_URL`.

> **Different column names?** No problem — the header-to-field mapping is one
> list in `src/config.ts` (`COLUMN_MAP`); adjust it once and done.

### Everyday updates
Edit the Sheet → rebuild/redeploy the site (see Deployment below). That's it.

### Column cheat-sheet
| Column | What it does |
|---|---|
| `name`, `address` | Required. Address drives the map pin and directions. |
| `latitude`, `longitude` | Optional — leave blank and the build geocodes the address automatically (results cached in `src/data/geocache.json`). |
| `photo` | Main photo: a full URL (Cloudinary) **or** a filename placed in `public/churches/`. Blank = elegant placeholder. |
| `service_times` | Free text; separate services with `;` to get a neat list. |
| `pastor`, `phone`, `email`, `website`, `facebook` | Contact block; each shows only if filled. |
| `slug` | The page address (`/churches/<slug>`); auto-generated from the name if blank. |
| `theme` | Color theme keyword — see **Themes** below. Blank = default blue. |
| `about`, `plan_your_visit`, `beliefs`, `pastor_bio` | Longer text sections; each appears only when filled. |
| `photos`, `ministries` | Lists — separate items with `\|` or `,`. |
| `pastor_photo` | Pastor headshot (URL or filename). |
| `founded`, `fun_fact`, `denomination_note` | History / fun details section. |
| `giving_url`, `livestream_url`, `calendar_url` | Add a Give button, a Watch section (YouTube links embed automatically), or the church's own calendar. |

### The safety net (CSV fallback)
A copy of the directory is committed at `src/data/churches.csv`. If the
Google Sheet is unreachable or malformed at build time, the site
automatically builds from this file instead — it never breaks. To refresh
the fallback: in the Sheet, **File → Download → .csv**, and replace
`src/data/churches.csv` with the downloaded file.

---

## Church photos (Cloudinary — recommended)

Photos are uploaded through **Cloudinary** (free), a point-and-click media
site — no code, no file transfers:

1. Sign up at cloudinary.com (free tier is plenty) and open **Media Library**.
2. Upload the photo (from phone or computer). Tip: name it after the church's
   slug, e.g. `bethel-baptist-snead`.
3. Click the photo → **Copy URL**.
4. Paste that URL into the church's `photo` cell (or add to `photos`,
   separated by `|`) in the Google Sheet.
5. Rebuild/redeploy — done. The site automatically serves right-sized,
   optimized versions of Cloudinary images, so huge phone photos are fine.

*Alternative without Cloudinary:* put image files in the repo's
`public/churches/` folder and type just the filename in the `photo` cell —
this works but requires repo access, so Cloudinary is the recommended path.

---

## Themes — giving each church its color

There are **12 built-in color themes**. Type one of these keywords in a
church's `theme` column:

`blue` (default) · `forest` · `teal` · `burgundy` · `gold` · `plum` · `sky` ·
`sage` · `clay` · `navy` · `rose` · `slate`

The keyword recolors the church's whole page plus its card and map pin in
the directory. Preview them all on the **`/themes`** page.

**Adding a 13th theme** takes one copy-paste: open `src/styles/theme.css`,
copy the template block at the bottom, give it a keyword and an accent color
(keep it dark enough to read on cream — the file explains), and use the new
keyword in the Sheet. It automatically appears in the `/themes` dropdown.

---

## Google Maps key (turns on the map + address search)

The Find-a-Church map, colored pins, and address autocomplete need one free
Google API key:

1. Go to [console.cloud.google.com](https://console.cloud.google.com), create
   a project (e.g. "FBA Website").
2. Under **APIs & Services → Library**, enable three APIs: **Maps JavaScript
   API**, **Places API**, and **Geocoding API**.
3. Under **Credentials**, create an **API key**. Restrict it to your website's
   domain (Application restrictions → Websites).
4. Put it in `.env` as `PUBLIC_GOOGLE_MAPS_API_KEY` and rebuild.

Optionally set `GOOGLE_GEOCODING_API_KEY` (can be the same key) so the build
converts church addresses to exact map coordinates; they're cached in
`src/data/geocache.json`. The six demo churches ship with approximate seeded
coordinates so the map works even before any key exists — real geocoding
replaces them automatically once the key is set.

**No key?** The page shows the full church list with a friendly notice —
nothing breaks.

---

## Events calendar

Put the association's Google Calendar ID in `.env` as
`PUBLIC_GOOGLE_CALENDAR_ID` (find it in Google Calendar → Settings → your
calendar → **Integrate calendar → Calendar ID**; make the calendar public).
The Events page embeds it responsively. Without it, the page shows sample
event cards and a notice.

## Contact form

The form is static-host friendly. Create a free form at
[formspree.io](https://formspree.io), copy the form's endpoint URL, and set
it as `PUBLIC_CONTACT_FORM_ENDPOINT` in `.env`. Until then the page shows
the office contact details and an email button instead.

## Online giving (decision pending)

The Give page is a tasteful "coming soon" with a clearly marked slot in
`src/pages/give.astro` for a provider embed. Three good options when ready:

| Provider | Good fit when… |
|---|---|
| **Tithe.ly** | You want church-giving-specific tools and quick setup. |
| **Stripe Payment Links** | You want lowest fees and just a simple "Give" button. |
| **PayPal Giving Fund** | You want donors to use PayPal and waived fees for verified charities. |

To enable any of them the association needs: its **EIN / 501(c)(3) status**,
a **bank account** for deposits, and a decision on whether gifts go to the
association or are routed to individual churches.

---

## Domain name

The association doesn't have a domain yet. Check these (in order) at a
registrar like Cloudflare Registrar or Namecheap — prefer `.org`:

1. `friendshipbaptistassociation.org` ← recommended first choice
2. `fbaonline.org` (short fallback)
3. `friendshipbaptistassoc.org`, `friendshipassociation.org`,
   `fba-alabama.org`, `friendshipbaptistal.org`, `blountbaptists.org`

Once purchased, set `PUBLIC_SITE_URL` in `.env` to the domain and rebuild
(this fixes canonical/social links), then point the domain at your hosting
(Hostinger: hPanel → Domains; HTTPS is automatic).

---

## Deployment

### Path 1 — Hostinger manual upload (simplest)

1. On a computer with the project: `npm install && npm run build`
   (first time only: also copy `.env.example` to `.env` and fill in any keys
   you have). This produces a `dist/` folder — the complete website.
2. Log into Hostinger **hPanel → File Manager**, open **`public_html`**.
3. Delete any old site files inside `public_html`.
4. Upload the **contents** of `dist/` (not the folder itself) into
   `public_html`. Easiest: zip the contents of `dist/`, upload the zip, and
   use File Manager's **Extract**, making sure `index.html` ends up directly
   inside `public_html`.
5. Visit your domain — the site is live.
6. *(Optional)* Also upload the repo's **`editor/`** folder into
   `public_html` — a small password-protected tool for editing the site's
   text right on the server, no rebuild needed for quick word changes.
   Setup steps: `editor/README-EDITOR.md`. (Remember: rebuilding and
   re-uploading the site replaces those on-server edits.)

> **Remember:** the site reads the Google Sheet **at build time**. Updating
> churches = re-run `npm run build` and re-upload `dist/`. If that gets
> tiresome, use Path 2.

### Path 2 — Git-based auto-deploy (optional)

Connect this GitHub repo to **Netlify** or **Cloudflare Pages** (both free):
build command `npm run build`, output directory `dist`, and add your `.env`
values as environment variables in their dashboard. Every push rebuilds the
site automatically, and both services can also rebuild on a schedule or via
a "build hook" URL — which makes Google Sheet edits go live without touching
anything. Point the domain at Netlify/Cloudflare instead of Hostinger in
that case.

---

## For developers

```bash
npm install     # once
npm run dev     # local dev server at localhost:4321
npm run build   # static build → dist/
npm run preview # serve the built dist/ locally
```

- **Stack:** Astro 5 (static output) + Tailwind CSS 4, no backend.
- **Design tokens:** all color/type/shape in `src/styles/theme.css`
  (12 per-church presets as `[data-theme]` blocks); structure and
  `color-mix()`-derived depth in `src/styles/style.css`. Components never
  hardcode colors.
- **Data layer:** `src/lib/churches.ts` (Sheet fetch → validation → CSV
  fallback → geocode cache). Column mapping in `src/config.ts`.
- **Sample content:** flip `SAMPLE_CONTENT` to `false` in `src/config.ts` to
  remove the banner and all "sample" tags once real content is in. Editable
  copy lives in `src/content/` (markdown + one data file), not in components.
- **Environment:** copy `.env.example` → `.env`. Every variable is optional;
  each missing one degrades gracefully (see the file's comments).
- The theme showcase (`/themes`) is `noindex` and excluded from the sitemap.
- Beta checklist: see `BETA-TESTING.md`. Original build brief: `docs/PLAN.md`.

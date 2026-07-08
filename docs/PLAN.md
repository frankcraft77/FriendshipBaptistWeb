# Friendship Baptist Association — Website Build Plan

> **For:** Claude Code (autonomous, no-approval session in a fresh GitHub repo)
> **Project:** Public website for the Friendship Baptist Association (Blount County, Alabama — based in Oneonta, AL)
> **Association type:** Southern Baptist Convention–affiliated local association of ~24 churches
> **Prepared as a build brief.** Paste this whole file into Claude Code as the project spec. Sections marked `⚠️ OWNER TO PROVIDE` contain content the site owner will supply later — scaffold them with clearly labeled placeholders and `coming soon` states so the site can launch without them.
> **Ship it fully working now.** Do not wait for owner input. Build the complete site seeded with the included **`churches.example.csv`** (6 real churches, pre-themed), self-verify against the §15 beta checklist, and produce a static `dist/` that's **upload-ready for Hostinger** (§16). The goal is a live, clickable demo — including the theme showcase mini-site — ready to show the owner in the morning.
> **Companion file:** `churches.example.csv` ships with this plan and is the seed/fallback data. See §14.

---

## 1. Project summary

Build a fast, low-maintenance public website for the Friendship Baptist Association. The site's #1 job is to **connect people and visitors to the 24 local member churches** via a browsable directory with **both a map view and a list view**. Secondary goals, in priority order:

1. **Connect & inform member churches** (highest priority)
2. **Share the AMS/Director of Missions' vision & mission**
3. **Promote events & mobilize volunteers**
4. **Enable online giving** (lowest priority — undecided, scaffold as placeholder)

The site owner (an AMS / Director of Missions) is **not technical**. Everything about content maintenance must be doable by a non-coder editing a spreadsheet or a simple CMS — never by touching code.

---

## 2. Tech stack

- **Framework:** Astro (static-first, excellent performance, cheap/free hosting, ideal for content sites and works well with Claude Code).
- **Styling:** Tailwind CSS, layered over the owner's design-token system (a `theme.css` of flat color/type/shape tokens with gradients derived via `color-mix()`). See §10 — the site must match the web app's design language.
- **Hosting:** Netlify or Cloudflare Pages (both have generous free tiers, Git-based deploys, easy custom-domain setup).
- **Content management:**
  - **Church directory** → Google Sheets as the live source of truth, with a **committed CSV fallback** (see §4). Includes a `theme` column for per-church color themes.
  - **Editorial content** (welcome letter, ministry pages, static copy) → Markdown/MDX files in the repo, OR a lightweight Git-based CMS (Decap/Netlify CMS) if the owner wants in-browser editing. Recommend Decap CMS so the non-technical owner can edit without code.
  - **Church photos** → Cloudinary (visual upload dashboard for a non-technical owner; auto-optimizes images). URLs referenced from the Sheet. See §4.9.
- **Maps/location:** Google Maps JavaScript API + Geocoding API + **Places API** (for address autocomplete in the Churches Near Me tool). See §4.3, §4.6.
- **Calendar:** Embed the association's existing Google Calendar (see §6).
- **No heavy backend.** Keep it static + client-side data fetching where needed.

---

## 3. Site map / pages

1. **Home** — hero, one-line mission, welcome from the AMS (excerpt), quick links to Find a Church / Events / Give, featured churches or map preview. Ships with realistic sample copy (§5A).
2. **Find a Church / "Churches Near Me"** (the centerpiece) — searchable address tool (Google Places Autocomplete) + geolocation showing the visitor relative to the 24 church pins, with map view + list view. See §4.3.
3. **Church landing pages** — a full mini-site per church at `/churches/<slug>`, generated from the directory data, with progressive enrichment sections. See §4.4.
4. **About** — mission/vision, brief history (organized 1955), "what is an association?" explainer, officers/staff, and the AMS welcome letter + bio. Ships with sample copy (§5A). `⚠️ OWNER TO PROVIDE` final text.
5. **Ministries / What We Do** — sample cards for typical associational ministries (church planting, pastor care, missions, VBS, etc.). See §5A.
6. **Events** — embedded Google Calendar + sample example-event cards. See §6, §5A.
7. **Missions / Get Involved** — sample cooperative-missions and volunteer content. See §5A.
8. **News / Updates** (optional) — sample blog-style posts showing how updates could be shared. See §5A.
9. **Give** — online giving. **Undecided** — scaffold as placeholder / "coming soon." See §7.
10. **Resources** — for pastors/member churches (documents, forms, links). Sample linked-document placeholders. See §5A. `⚠️ OWNER TO PROVIDE` real items.
11. **Contact** — association office info (Oneonta, AL address, phone, email, contact form).
12. **Theme showcase / template** (`/themes`) — a demo "fake church" mini-site with a dropdown to preview all 12 color themes. Kept out of main nav / search indexing. See §4.8.

All non-directory pages ship with **realistic sample content to inspire the owner** (§5A), behind a single `SAMPLE_CONTENT` flag and a dismissible "sample content" banner.

Global: responsive header nav + footer with address, quick links, and social links.

---

## 4. Church directory (highest-priority feature)

### 4.1 Data source: Google Sheets primary, CSV fallback

Build the directory to read church data from a **published Google Sheet**, with a **local CSV committed to the repo as an automatic fallback** if the Sheet is unreachable at build time.

- **Primary:** Google Sheet published to the web (File → Share → Publish to web → CSV), fetched at build time. This lets the non-technical owner edit churches in a familiar spreadsheet and re-deploy (or rebuild on a schedule) to update the site.
- **Fallback:** `src/data/churches.csv` committed to the repo. If the Google Sheet fetch fails or returns malformed data, the build uses this file so the site never breaks.
- Implement a single data-loading module that: (1) tries the Sheet, (2) validates the rows, (3) falls back to the CSV, (4) logs which source was used.
- Add a short **README section** documenting how the owner updates the Sheet and how to refresh the committed CSV fallback.

> **Note:** The owner will provide the actual spreadsheet (and its exact column headers) before handoff. Build the loader to be **column-driven** and easy to remap. Use the schema below as the assumed default and centralize the column-name mapping in one config object so it can be adjusted to match the real headers in one place.

### 4.2 Assumed church schema (confirm against owner's real headers)

| Field | Notes |
|---|---|
| `name` | Church name (required) |
| `address` | Full street address, Oneonta/Blount County, AL (required) |
| `latitude`, `longitude` | For map pins. If the sheet only has an address, geocode at build time and cache results. |
| `photo` | Image URL or filename (see §4.5) |
| `service_times` | Free text (e.g., "Sunday School 9:30a; Worship 10:45a; Wed 6:00p") |
| `pastor` | Pastor name (optional) |
| `phone` | Contact phone |
| `email` | Contact email |
| `website` | Church website URL (optional) |
| `facebook` | Facebook page URL (optional) |
| `slug` | URL slug for the church's landing page; auto-generate from name if absent |
| `theme` | Color-theme keyword (one of the 12 presets, e.g. `blue`, `forest`, `burgundy`) that sets the church's mini-site and card/pin colors. See §4.7. Blank → default theme. |

**Extended (mini-site) columns — all optional, all CSV/Sheet-driven so they can be added later without code changes:**

| Field | Notes |
|---|---|
| `about` | Longer "about this church" paragraph(s). Support basic line breaks. |
| `photos` | Additional photo URLs/filenames for a gallery. Pipe- or comma-separated (e.g., `img1.jpg \| img2.jpg`). |
| `pastor_bio` | Short bio for the pastor. |
| `pastor_photo` | Pastor headshot URL/filename. |
| `plan_your_visit` | "What to expect" / visitor info (parking, dress, kids, etc.). |
| `beliefs` | Short statement of faith / distinctives, or a link. |
| `ministries` | List of ministries/programs (pipe- or comma-separated). |
| `denomination_note` | Any affiliation detail beyond SBC/association. |
| `founded` | Year founded (fun/history detail). |
| `fun_fact` | Free-form custom/fun detail about the church. |
| `giving_url` | Church's own giving link, if any. |
| `livestream_url` | Sermon/livestream link (YouTube/Facebook), if any. |
| `calendar_url` | Church's own public Google Calendar embed/ID, if any. |

The confirmed required display fields per the owner are: **name, address, photo, Google Maps location, service times, and contact information.** Everything else is optional enrichment that renders **only when present** — a church with just the required fields still gets a complete, good-looking page.

### 4.3 "Churches Near Me" tool — Find a Church page

The centerpiece is an interactive **"Churches Near Me"** tool that helps a visitor find the closest of the 24 member churches. It has both a **map view** and a **list view** (toggle; remember the user's choice for the session).

**Address search (Google Places Autocomplete):**
- A prominent **"Type in your address"** search box using **Google Places Autocomplete** — as the visitor types (e.g. "123 Main St, Oneonta"), Google suggests completions.
- On selection, geocode the chosen place to lat/lng, drop a distinct **"you are here" pin** on the map, and compute each church's **distance** from that point.
- Also offer a **"Use my current location"** button (browser Geolocation API) as a one-tap alternative to typing.
- Once a location is set, **sort churches by nearest first** and show the distance (miles) on each card and pin info window.
- Requires the **Places API** enabled in Google Cloud (in addition to Maps JavaScript + Geocoding). Document this in the README.

**Map view:**
- A single interactive Google Map showing all 24 church pins, plus the visitor's location pin once set.
- **Each church pin is colored by that church's assigned theme** (see §4.7) so the map is visually varied and each church's brand carries through.
- Clicking a pin opens an info window: church name, photo thumbnail, address, distance (if location set), and a link to its mini-site.
- Auto-fit/zoom the map to show the visitor plus the nearest few churches when a location is set.

**List view:**
- Responsive cards: photo, name, address, service times, quick contact, distance (if set). **Each card is accented in the church's assigned theme color.**
- Each card links to the church's mini-site and offers a "Get directions" link.

**Filtering:** allow filtering/sorting by town/city in addition to distance, so visitors can also browse by area.

**Graceful degradation:** if no Maps/Places key is present or geolocation is denied, fall back to the plain sortable list with a friendly notice rather than erroring.

### 4.4 Church landing pages (full mini-site per church)

Every church gets its own **dedicated landing page** at a clean URL (`/churches/<slug>`) — a full mini-site, not just a directory row. It must look complete and polished from the required fields alone, then **progressively reveal richer sections as optional columns get filled in** via the Sheet/CSV over time. Build every section to **render only when its data is present** (conditional blocks), so churches can enrich their page later without any code changes and without leaving empty scaffolding on sparse pages.

**Per-church theme:** each mini-site is displayed in the church's **assigned color theme** (see §4.7), set by a keyword in the CSV. The theme colors the hero, buttons, accents, and section styling so each church's page feels individually branded while sharing the same layout.

**Always-present core (from required fields):**
- **Hero:** large church photo with the name overlaid; address and a primary "Get directions" button (opens Google Maps directions).
- **Service times**, prominently placed near the top.
- **Contact block:** all available contact info (pastor, phone, email, website, Facebook) with click-to-call and click-to-email.
- **Embedded Google Map** centered on the church.
- **"Back to directory"** link and prev/next church navigation.

**Progressive enrichment sections (each renders only if the matching column has data):**
- **About this church** — from `about`.
- **Photo gallery** — from `photos` (responsive grid/lightbox); falls back to just the hero photo if empty.
- **Meet the pastor** — `pastor_bio` + `pastor_photo`.
- **Plan your visit** — `plan_your_visit` ("what to expect," parking, kids, dress).
- **What we believe** — `beliefs`.
- **Ministries & programs** — `ministries`.
- **Fun facts / history** — `founded` and `fun_fact` (the "fun custom details" the owner wants to add later).
- **Watch / livestream** — embed or link from `livestream_url`.
- **Give to this church** — button from `giving_url`.
- **This church's events** — embed from `calendar_url` if provided.

**Implementation notes:**
- Drive the entire page from one church record; centralize which sections exist and their order in a single config/component list so sections are easy to add, reorder, or hide.
- Parse multi-value fields (`photos`, `ministries`) on a delimiter (support both `|` and `,`); trim whitespace; ignore blanks.
- Sensible **placeholder image** for the hero when no photo yet; never render a broken image.
- Keep SEO/shareability in mind: per-page `<title>`, meta description from `about`, and Open Graph tags using the hero photo, so a church can share its page link on social media and it previews nicely.
- Each landing page should be self-contained enough that a church could treat the URL as their primary web presence if they don't have their own site.

### 4.5 Photos

The owner will confirm whether photos are **image URLs in the sheet** or **files to host**. Build to support **both**: if a photo value is a full URL, use it directly; if it's a filename, resolve it against a local `public/churches/` image folder. Apply the same resolution logic to the **gallery** (`photos`) and **pastor photo** fields, not just the primary `photo`. Provide a sensible **placeholder image** for churches with no photo yet, and lazy-load gallery images for performance.

### 4.6 Google Maps API

The map requires a Google Maps JavaScript API key (and Geocoding API if geocoding addresses at build time).
- Include setup instructions in the README: create a Google Cloud project, enable **Maps JavaScript API** + **Geocoding API**, create an API key, and restrict it to the site's domain.
- Read the key from an environment variable (`PUBLIC_GOOGLE_MAPS_API_KEY`); never hardcode it. Provide a `.env.example`.
- If no key is present at build time, the map view should degrade gracefully to the list view with a friendly notice rather than erroring.

### 4.7 Per-church color themes (12 presets, CSV-driven)

The site supports **multiple themes**: a set of **12 accent color combinations**, and each church is displayed in one of them across both its mini-site and its card/pin in the Churches Near Me tool.

- **Assignment via CSV:** add a `theme` column to the Sheet/CSV. Its value is a **keyword** (e.g. `blue`, `forest`, `burgundy`, `gold`, `teal`, `plum`, `sky`, `sage`, `clay`, `navy`, `rose`, `slate`) that maps to one of the 12 presets. If blank or unrecognized, fall back to the site's default theme.
- **Where it applies:** the church's mini-site (hero, buttons, pills, accents, section styling) and its representation in the Churches Near Me tool (list card accent + map pin color).
- **Implementation:** define the 12 presets as named token sets in `theme.css` (each preset overrides `--accent` and its coordinated companions on the light base palette from §10). Apply a church's preset by setting a `data-theme="<keyword>"` attribute (or equivalent scoped class) on its page/card root, with CSS selectors like `[data-theme="forest"] { --accent: …; --accent-contrast: …; }`. This keeps all color in `theme.css` and requires no component changes to add or adjust themes.
- **Extensibility:** the owner must be able to **add new color combinations** by appending a new preset block to `theme.css` (a new `[data-theme="…"]` block) and then using that keyword in the CSV. Document this clearly in the README with a copy-paste template block.
- All 12 presets must meet **WCAG AA contrast** on the light base surfaces; verify accent-on-cream and text-on-accent for each.

### 4.8 Theme showcase / template mini-site

Build a **non-real "template" church mini-site** used to preview and choose themes:

- A demo page (e.g. `/themes` or `/template`) rendering a full mini-site with placeholder ("fake church") content.
- A **dropdown of all available color themes**; selecting one live-updates the whole template page to that theme (by swapping the `data-theme` attribute). This lets the owner see each of the 12 (and any newly added) presets applied to a real page layout before assigning them to churches.
- The dropdown is **populated from the same list of presets defined in `theme.css`**, so newly added themes appear automatically.
- Clearly label this page as a preview/template (not a real church) and keep it out of the main nav / search indexing.

### 4.9 Photo handling & non-technical admin (Cloudinary)

Older, non-technical users must be able to **upload a photo and assign it to a specific church** without touching code or Git. Recommended approach — **Cloudinary** (simplest for a non-techie):

- **Why Cloudinary:** the owner logs into a normal web dashboard (cloudinary.com) with a visual **media library** — upload from phone or computer, no Git, no build step, no code. Free tier is ample for this use. It **auto-optimizes and resizes** images, which matters because congregation members' phone photos are often very large.
- **Assign-to-church workflow (keep it dead simple, document in README):**
  1. Owner uploads the photo in Cloudinary and names it with the church's **slug** (e.g. `oak-grove-baptist`) — or copies the image's Cloudinary URL.
  2. Owner pastes that URL (or the slug-based filename) into the church's `photo` (or `photos`) cell in the Google Sheet — the existing source of truth.
  3. Site rebuilds/refreshes and the new photo appears. No code, no deploy knowledge needed.
- **Build support:** the photo-resolution logic (§4.5) already handles full URLs; ensure it handles Cloudinary URLs and can apply Cloudinary transformation parameters (size/crop) for thumbnails vs. hero images. Read any Cloudinary cloud name from an env var.
- **Fallback option (documented, not default):** if the owner prefers not to use Cloudinary, the same `photo` column can point to files placed in `public/churches/` — but that requires repo access, so Cloudinary is the recommended path for a non-technical maintainer.
- **Note on scope:** this keeps the **Google Sheet as the single source of truth** for church data (per owner decision); Cloudinary is only the image host + upload UI, not a second data store.

---

## 5. AMS / Director of Missions content

**Known association facts (from public sources — verify with the owner):**
- **Association:** Friendship Baptist Association — a group of Southern Baptist churches serving Blount County, AL; affiliated with the Alabama Baptist State Convention (ALSBOM).
- **Organized:** 1955.
- **Associational Mission Strategist (AMS):** Dale Wood.
- **Office:** 898 Springville Boulevard, Oneonta, AL.
- **Facebook:** facebook.com/friendshipba

Use these to pre-fill the About/Contact scaffolding (clearly marked as "verify"). Still `⚠️ OWNER TO PROVIDE`: the AMS bio, photo, welcome letter, exact office phone/email/hours, and the association mission/vision statement.

Scaffold the **About** page and the homepage welcome section with clearly labeled placeholder copy and a placeholder headshot so the layout is complete and ready to drop real content in. Structure the welcome as a short personal letter (this is the norm on SBC associational sites) with a longer bio beneath.

---

## 5A. Inspirational filler content — a fully "finished-looking" static association site

Beyond the church directory and mini-sites, build out the **association site itself as a complete, realistic static site with written filler content on every page** — not empty scaffolding. The purpose is to **help the owner (Dale Wood) envision the final site and react to concrete copy** rather than a blank template. This is a thinking/decision aid.

**Golden rule — label it clearly.** All of this is *sample content meant to inspire, not final copy.* Make that obvious without making the site look broken:
- Add a subtle, dismissible **"Sample content — for planning purposes"** banner sitewide (easy for Claude Code to remove later with one flag/variable).
- Where a paragraph is invented filler, keep it realistic and on-brand; do not fabricate specific facts (real names beyond the verified ones, fake statistics, fake event dates presented as real, fake quotes from real people). Invent *plausible* content, clearly generic where specifics would otherwise mislead.

**Write realistic, editable filler for each page so the owner can see a "finished" site:**

- **Home:** a warm hero headline and subhead; a one-line mission; a short "Welcome from our Associational Mission Strategist" excerpt (generic letter, signed "Dale Wood, AMS" — clearly sample); 3–4 "what we do" highlight blurbs (e.g. church support, missions, pastor care, events); a "Find a church near you" call-to-action feeding the tool; a preview strip of member churches.
- **About:** a fuller sample welcome letter; a short "Who we are" section (Southern Baptist churches serving Blount County; organized 1955; affiliated with ALSBOM — the verified facts); a plain-language mission/vision draft the owner can rewrite; a "What is a Baptist association?" explainer for visitors; an officers/staff section with labeled placeholder roles (Moderator, Clerk, Treasurer, AMS) and headshot placeholders.
- **Ministries / What We Do:** sample cards for typical associational ministries — church planting & revitalization, pastor & staff care, missions & disaster relief, VBS and youth support, women's/men's ministries, benevolence — each with a short generic description the owner can keep, cut, or edit.
- **Events:** the real Google Calendar embed, plus 2–3 clearly-sample "example event" cards (e.g. "Associational Annual Meeting," "Pastor Appreciation Lunch," "VBS Training") marked *sample* so the owner sees how events will look.
- **Missions / Get Involved:** a sample section on cooperative missions, volunteer opportunities, and how churches partner together — generic but evocative, to prompt the owner's ideas.
- **News / Updates (optional):** a simple blog-style list with 2–3 sample posts (e.g. "Welcome to our new website," "Highlights from this year's revival") to show how the owner could share updates. Keep it optional and easy to disable.
- **Resources:** sample linked-document placeholders (annual meeting minutes, church profile update form, ministry request form, giving report) with obviously-sample links, showing how a real resource library would appear.
- **Give:** the §7 "coming soon" treatment, plus sample copy explaining cooperative giving so the owner can decide direction.
- **Contact:** the verified office info (898 Springville Blvd, Oneonta; Facebook), a working contact form, and a map — with phone/email/hours as clearly-labeled placeholders to confirm.

**Implementation:**
- Store all filler copy in **easily editable Markdown/MDX or a single content file per page**, not hardcoded in components, so the owner (or a follow-up Claude Code pass) can replace sample text without touching layout.
- Mark every invented block with an inline comment (e.g. `{/* SAMPLE COPY — replace */}`) and, where visible, keep the tone realistic so the page *reads* finished.
- Provide a single **`SAMPLE_CONTENT` flag** that toggles the sitewide "sample content" banner and any "example" tags, so flipping one switch turns the inspiration site into the real one as content gets finalized.
- These pages are part of the shipped static build (§16) and the morning demo (§15) — the owner should be able to click through a site that *feels* complete.

---

## 6. Events

- Embed the association's **existing Google Calendar** on the Events page (owner will provide the calendar's public embed URL / calendar ID).
- Read the calendar ID from config/env so it's easy to swap.
- Optionally surface the **next few upcoming events** on the homepage by reading the same public calendar feed (nice-to-have; fall back to just the embed if it complicates the build).
- Ensure the embed is responsive on mobile.

---

## 7. Online giving (undecided — scaffold only)

Giving is **not yet decided** (whether it's giving to the association / Cooperative Program vs. routing donors to individual churches, and whether a 501(c)(3) account/processor exists). Do **not** integrate a payment processor yet.

- Build a **Give** page with a clean "coming soon" / informational placeholder and a clearly commented, swappable section where a provider embed (Tithe.ly, Stripe, or PayPal Giving) can later be dropped in.
- Keep the nav link present but visually indicate it's informational for now.
- Add a short note in the README listing the three provider options and what info the owner must gather to enable real giving (EIN/501(c)(3) status, bank account, chosen processor).

---

## 8. Resources & Contact

- **Resources:** placeholder structure for pastor/member-church materials (documents, forms, links, meeting minutes, annual reports). `⚠️ OWNER TO PROVIDE` actual items. Build as a simple linked-document list that's easy to extend. Everything public for v1 (no members-only login) unless the owner later requests gating — leave a note that auth could be added later.
- **Contact:** association office address in Oneonta, AL, phone, email, and a simple contact form (use a static-friendly form handler such as Netlify Forms or Formspree — no backend server required). `⚠️ OWNER TO PROVIDE` exact office address, hours, phone, email.

---

## 9. Domain name — options to check

The association does **not** have a domain yet. Below is a ranked list of candidates to check for availability at a registrar (Cloudflare Registrar and Namecheap are good low-cost options). Prefer **.org** for a nonprofit/ministry; grab the matching **.com** too if available. Availability can't be guaranteed here — check each at the registrar.

1. `friendshipbaptistassociation.org`
2. `friendshipbaptist.org` *(may be taken by a church of the same name — check)*
3. `fbaonline.org`
4. `friendshipbaptistassoc.org`
5. `friendshipassociation.org`
6. `fba-alabama.org`
7. `friendshipbaptistal.org`
8. `blountbaptists.org` *(descriptive, county-based alternative)*
9. `friendshipbaptistnetwork.org`
10. `fbachurches.org`

Recommendation: try `friendshipbaptistassociation.org` first; if long, `fbaonline.org` is a clean short fallback. Once chosen, the README should document pointing the domain's DNS at the host (Netlify/Cloudflare Pages) and enabling HTTPS (automatic on both).

---

## 10. Design language

**Overall aesthetic: modern, clean, and elegant — IMB-inspired, carried by typography and whitespace.** The reference feel is imb.org: modern, mission-driven, confident, with full-width mission statements and clear calls-to-action. But because this is a rural 24-church association **without a professional photography budget**, achieve that feel primarily through **elegant typography, generous whitespace, and restraint** — not heavy imagery. Photography is used *opportunistically* (only where a church supplies a good image); every section must look intentional and elegant when there is no photo at all. Reference points reviewed: imb.org (modern/mission-driven — primary), sbts.edu (heritage/serif elegance), mbts.edu (clean/contemporary).

**Aesthetic principles:**
- **Type-led elegance:** a **serif + sans pairing** — an elegant serif for headlines and display (gravitas, heritage, elegance) paired with a clean sans-serif for UI, navigation, and body/reading text (modern, legible). This mirrors the note in the token system (`--font-body` serif, `--font-ui` sans) and extends it with a distinct display serif for large headings. Suggested pairings (self-host via `@font-face`): headline serif such as *Cormorant Garamond*, *EB Garamond*, or *Playfair Display*; body/UI sans such as *Inter*, *Source Sans 3*, or system-UI. Keep to **two families** (plus the existing Georgia body) — restraint reads as elegance.
- **Generous whitespace:** large section padding, roomy line-height, and confident margins. Let sections breathe; avoid dense, cramped layouts. Whitespace is the primary "luxury" signal here.
- **Full-width, mission-driven hero (IMB-style) without requiring a photo:** a large serif headline stating the association's mission/purpose, a short supporting line, and 2–3 clear calls-to-action (Find a Church / Events / Give). If no hero photo is available, use an elegant typographic hero on a warm cream/blue field with a subtle derived gradient — not a stock image.
- **Confident calls-to-action & clear structure:** borrow IMB's clarity — obvious primary actions, well-defined sections, generous type scale, strong visual hierarchy. Modern, not busy.
- **Restraint over ornament:** minimal borders, few accent colors per view, subtle depth from the token system's `color-mix()` gradients rather than decoration. Elegant = quiet.
- **Photography strategy:** treat images as enhancement, never load-bearing. Where a church has a photo, present it well (consistent aspect ratios, soft rounded corners per `--radius`, tasteful overlays for hero text legibility). Where there's none, fall back to refined typographic layouts and the placeholder treatment — never a broken or empty-looking block.
- **Motion:** subtle, tasteful only (gentle fade/slide on scroll, smooth hover states). Nothing flashy; elegance is calm.

**Avoid the generic-AI look (important):** the target is *IMB-modern*, not the common AI-default aesthetic of a warm-cream page with a high-contrast serif and a terracotta/clay accent — that combination reads as templated. This site uses a warm cream base *only as one neutral surface*, deliberately paired with a **cool, mission-driven deep-blue** accent and confident IMB-style structure to steer away from that cliché. Likewise avoid the other AI defaults (near-black + acid-accent; hairline-rule broadsheet columns). Spend the "boldness budget" on one signature element — e.g. the elegant serif hero lockup or the themed church cards — and keep everything else quiet and disciplined. Every color and type choice should feel chosen for *this* association (a 24-church Blount County fellowship), not applied from a template.

This aesthetic layers **on top of** the token architecture below — the serif/sans system and whitespace are consistent across all 12 color themes.

---

This site must share the **design language of the owner's existing web app** so the two feel like one product family. The app uses a deliberate token architecture that this site should adopt:

**Architecture to replicate:**
- A fixed structural stylesheet (`style.css`) that is **never edited per project** — it defines layout, components, and depth.
- A single **`theme.css`** file holding ~13 flat color tokens plus type and shape tokens. **All color lives here.**
- **Gradients and depth are derived automatically** from the flat tokens using CSS `color-mix()` (e.g. card surfaces blend `--bg-card` toward white at the top and toward `--bg` at the bottom; buttons/accents get a glow from `--accent`). Do **not** hand-author gradients — pick good flat colors and let the system generate depth.
- Adapt this token system to Astro + Tailwind: expose the same tokens as CSS custom properties (and map them into the Tailwind theme) so components reference `var(--accent)` etc. Keep a `theme.css` equivalent as the single source of truth so the owner can reskin without touching components.
- **Add display-type tokens:** introduce a `--font-display` (headline serif) alongside the existing `--font-body` (serif) and `--font-ui` (sans) so the serif+sans system is token-driven and themeable.

**Key difference from the app:** the web app ships a **dark** theme (deep navy `--bg: #0d1018`) with an **amber** accent. The public association site should use the **same token system but a light, welcoming theme with a recolored accent** — better for daylight phone use, older congregation members, and first-time visitors. Use a **deep blue accent** (trustworthy/traditional for a Baptist association) on a warm cream background.


**Association theme tokens (light) — use as the site's `theme.css`:**

```css
:root {
  /* Surfaces — warm cream, lighter card, subtle inset */
  --bg:             #f4ecdd;   /* page background (warm cream)            */
  --bg-card:        #fdf9f1;   /* card surface (a step lighter than bg)   */
  --bg-inset:       #efe4d0;   /* input fields / wells inside cards       */
  --card-border:    #ddc9a6;   /* subtle card outline                     */

  /* Text — deep warm brown-navy for readability on cream */
  --text:           #2c3542;   /* main reading text                       */
  --text-muted:     #6d7a8a;   /* secondary text, icons, placeholders     */

  /* Accent — deep blue (recolored from the app's amber) */
  --accent:         #2f5d8f;   /* recolors buttons, pills, progress, focus */
  --accent-contrast:#fdf9f1;   /* text on top of the accent color          */

  --progress-track: #e3d5ba;   /* inactive progress track                 */
  --error:          #c0492c;   /* inline validation                       */

  /* Type — elegant serif+sans system (see §10 aesthetic direction) */
  --font-display:   "Cormorant Garamond", "EB Garamond", Georgia, serif;  /* headlines */
  --font-body:      Georgia, "Iowan Old Style", "Times New Roman", serif;   /* reading text */
  --font-ui:        "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; /* UI/nav */

  /* Shape */
  --radius:         20px;
}
```

**12-preset multi-theme system (drives per-church themes — see §4.7):** define **12 named accent presets** as `[data-theme="<keyword>"]` blocks in `theme.css`, each overriding `--accent` (and `--accent-contrast` where needed) on the shared light base. Applying a preset requires only setting `data-theme` on a page/card root — no component changes. A suggested starting set of 12 (all tuned to read well on the cream surfaces; verify WCAG AA each):

| Keyword | Accent | | Keyword | Accent |
|---|---|---|---|---|
| `blue` (default) | `#2f5d8f` | | `sky` | `#3f7fb3` |
| `forest` | `#3f7d5a` | | `sage` | `#5c8060` |
| `teal` | `#2f7d86` | | `clay` | `#b06a44` |
| `burgundy` | `#8f3f4d` | | `navy` | `#2b3f66` |
| `gold` | `#c68a3c` | | `rose` | `#b05770` |
| `plum` | `#6f4d84` | | `slate` | `#4f6373` |

Provide each as a complete preset block (accent + any companion overrides) with a **copy-paste template** so the owner can add a 13th, 14th, etc. by appending a new `[data-theme="…"]` block and using that keyword in the CSV. The theme showcase page (§4.8) reads this same preset list so new themes appear in its dropdown automatically.

**Accent alternates note:** the presets above intentionally include the earlier alternates (forest, teal, burgundy, gold) plus additions to reach 12. Keep `--accent-contrast` light on all of them.

**Principles (carry over from the app + church-site needs):**
- Mobile-first: base styles are the phone layout; many visitors browse on phones for service times and directions.
- Warm, trustworthy, welcoming — a ministry serving small-town Blount County churches and visitors seeking a church home.
- Accessible: strong contrast (verify every one of the 12 accents meets WCAG AA on cream), readable type, semantic HTML, alt text on every image.
- Fast: static generation, optimized/lazy-loaded images, minimal JS beyond the map/directory.
- If the owner later provides a logo, pull its primary color into a preset — the whole site (or a church) recolors from that one token.

---

## 11. Build order (suggested)

1. Scaffold Astro + Tailwind project, global layout (header/nav/footer), and the theme-token system (light base + the 12 `data-theme` presets in `theme.css`).
2. Build the **church directory data layer** (Google Sheets → CSV fallback, column-mapping config incl. `theme`, validation, geocoding cache). This is the core — do it early.
3. Build the **Churches Near Me tool** (§4.3: Places Autocomplete address search, geolocation, distance sort, map + list, theme-colored pins/cards) and the **per-church mini-sites** (§4.4) with conditional enrichment sections and per-church themes.
4. Build the **theme showcase / template mini-site** (§4.8) with the theme dropdown.
5. Wire up **Cloudinary** photo handling (§4.9) and document the upload-and-assign workflow.
6. Home page (hero, mission line, welcome excerpt, quick links, map/directory preview).
7. Build out the **inspirational static association site** (§5A): write realistic sample copy for About, Ministries, Events, Missions, News, Resources, Give, and Contact, stored in editable content files behind the `SAMPLE_CONTENT` flag + banner.
8. About, Events (Google Calendar embed), Contact (with form).
9. Give and Resources as scaffolded placeholders with sample content.
9. README with all owner-facing instructions (updating the Sheet incl. `theme` keywords, refreshing CSV, Maps/Places API key, Cloudinary uploads, adding new themes, calendar ID, domain/DNS, deploy).
10. Deploy to Netlify/Cloudflare Pages; wire up the domain once chosen.

---

## 12. Owner to-provide checklist (collect before or during build)

- [ ] The churches spreadsheet + **exact column headers** (to confirm the directory schema, incl. a `theme` column)
- [ ] A `theme` keyword assigned to each church (or leave blank for default)
- [ ] AMS name, title, bio, photo, welcome letter
- [ ] Association mission/vision statement + brief history
- [ ] Oneonta office address, hours, phone, email
- [ ] Google Calendar public embed URL / calendar ID
- [ ] Google Maps API key with **Maps JavaScript + Geocoding + Places** APIs enabled
- [ ] Cloudinary account (free) for church photo uploads
- [ ] Giving decision (association vs. per-church; processor; 501(c)(3)/EIN status)
- [ ] Chosen domain name
- [ ] Logo / brand colors (if any) — can seed a theme preset
- [ ] Resources/documents for the Resources page

---

## 13. Guardrails for the build

- Never hardcode secrets (API keys, calendar IDs) — use env vars and provide `.env.example`.
- The site must build and render **even when** the Google Sheet, Maps key, or calendar is missing — degrade gracefully with placeholders and notices.
- Keep all content a non-technical owner needs to edit in either the **Google Sheet** or **clearly labeled Markdown/CMS entries** — not buried in components.
- Centralize the directory **column mapping** and **theme tokens** so both can be adjusted in one place.
- Write a thorough, plain-English **README** aimed at a non-technical maintainer.

---

## 14. Example data — ship with 6 real churches (do NOT wait for the owner)

Bundle a **ready-to-use example CSV** so the site is fully populated and demoable out of the box, before the owner supplies the full 24. Use the file `churches.example.csv` (provided alongside this plan) as the committed CSV fallback / seed data, and wire the site to render from it immediately.

The example contains **6 real Friendship Baptist Association churches** in Blount County, AL, each pre-assigned a distinct color theme so the multi-theme system is visible at a glance:

| Church | Location | Theme |
|---|---|---|
| Bethel Baptist Church | Snead | `blue` |
| Blountsville Baptist Church | Blountsville | `forest` |
| Brooksville Baptist Church | Blountsville | `teal` |
| Fowler Springs Baptist Church | Blountsville | `burgundy` |
| Blount Springs Baptist Church | Hayden | `gold` |
| County Line Baptist Church | Trafford | `plum` |

**Data provenance & handling:**
- **Names, addresses, and (where present) phone numbers are real**, cross-referenced from public church directories and the association's public listing.
- **Service times, `about` text, ministries, and some details are representative placeholders** clearly marked (e.g. `[PLACEHOLDER]`, `[VERIFY]`) — the owner must confirm/correct them. Do not present them to end users as authoritative until verified.
- **Latitude/longitude are intentionally left blank** — the build must **geocode each address** (via the Geocoding API) and cache the coordinates so the map and "Churches Near Me" distances work with the example data.
- **Photos are blank** — render the placeholder church image for all six so the layout looks complete; the owner adds real photos later via Cloudinary (§4.9).
- Keep the example CSV's header row **identical** to the schema in §4.2 so swapping in the owner's real Google Sheet/CSV is a drop-in replacement.

---

## 15. Beta testing

Before showing the site to the owner and before public launch, run a structured beta-test pass. Include a short **`BETA-TESTING.md`** checklist in the repo and confirm each item:

**Functional**
- [ ] Site builds cleanly from the example CSV with zero errors, and again from a Google Sheet source.
- [ ] CSV → Sheet fallback works: temporarily break the Sheet URL and confirm the committed CSV renders.
- [ ] All 6 example churches generate a mini-site at `/churches/<slug>` with no broken sections.
- [ ] Progressive sections correctly hide when a column is empty and show when filled.
- [ ] "Churches Near Me": address autocomplete returns suggestions; selecting one drops a visitor pin and sorts churches by distance.
- [ ] "Use my location" works and handles permission denial gracefully.
- [ ] Map and list views both render; toggling preserves state; pins/cards show each church's theme color.
- [ ] Each of the 12 themes renders correctly; the theme showcase dropdown swaps them live.
- [ ] Adding a new `[data-theme]` block in `theme.css` + using its keyword in the CSV produces a new working theme.
- [ ] Google Calendar embed loads on Events; Give and Resources show tasteful "coming soon" placeholders.
- [ ] Every association page (Home, About, Ministries, Events, Missions, News, Resources, Give, Contact) renders with realistic sample content — no empty or "lorem ipsum" pages.
- [ ] The `SAMPLE_CONTENT` flag toggles the sitewide "sample content" banner and "example" tags cleanly; site still looks finished with it on.
- [ ] No invented content is presented as verified fact (no fake stats, real-person quotes, or real-looking event dates without a "sample" label).
- [ ] Contact form submits (or is clearly wired to the chosen handler).

**Quality**
- [ ] Aesthetic: the site reads as modern, clean, and elegant (IMB-inspired) — serif display headlines + sans UI, generous whitespace, and sections that look intentional **even with no photos**. No cramped or "templated" blocks.
- [ ] Mobile-first check at 360–390px width: nav, tool, cards, and mini-sites are all usable one-handed.
- [ ] Accessibility: every one of the 12 accent themes meets **WCAG AA** contrast for text-on-accent and accent-on-cream; images have alt text; headings are ordered; keyboard navigation works.
- [ ] Performance: images lazy-load and are optimized; Lighthouse performance and accessibility both ≥ 90 on the home and Find-a-Church pages.
- [ ] Graceful degradation: with no Maps/Places key present, the tool falls back to a sortable list with a friendly notice instead of erroring.
- [ ] SEO: per-page titles/meta/Open Graph on mini-sites; the template/showcase page is excluded from indexing.
- [ ] No secrets committed; `.env.example` present and accurate.

**Owner-preview readiness (for the morning demo)**
- [ ] Home, Find a Church (with the 6 churches on the map), at least 6 working mini-sites, and the theme showcase page are all live and clickable.
- [ ] Every association page reads as a "finished" site with realistic sample content, so the owner can react to real copy and envision the final result (§5A).
- [ ] A one-paragraph "what to look at" note at the top of the README so the owner can self-tour, plus a note that all page copy is editable sample content.

---

## 16. Deployment — upload-ready for Hostinger

The final output must be **ready to deploy to the owner's Hostinger server** and demoable the next morning. This project will be dropped into a fresh GitHub repo and run in Claude Code with **no manual approvals**, so the build should proceed end-to-end autonomously and produce a deployable result.

**Target: Hostinger.** Hostinger commonly serves **static file hosting via `public_html`**. Astro builds to a static `dist/` folder, which is ideal. Produce the build so it can be uploaded directly:

- Configure Astro for a **static build** (`output: 'static'`) that outputs to `dist/`.
- Set the correct **`site`** URL and base path for the chosen domain so canonical URLs, sitemap, and Open Graph tags are right.
- Run `npm run build` and confirm `dist/` contains the full static site (home, Find a Church, all 6 mini-sites, theme showcase, events, contact, and placeholder Give/Resources).
- **Provide two clear deployment paths in the README**, written for a non-technical user:
  1. **Manual upload (simplest for Hostinger shared hosting):** step-by-step for zipping `dist/` and uploading its **contents** into `public_html` via Hostinger's **hPanel File Manager** (or FTP), including where files go and how to set the domain.
  2. **Git-based auto-deploy (optional):** if the owner prefers, connect the GitHub repo to Netlify/Cloudflare Pages for automatic rebuilds — noting the tradeoff that live Google-Sheet updates require a rebuild trigger, which Netlify/Cloudflare can automate but a manual Hostinger upload cannot.
- Include a short note on **build-time data**: because the site reads the Google Sheet at build time, updating churches means re-running the build and re-uploading (manual path) or triggering a rebuild (Git path). Document this plainly.
- Include **`.env.example`** listing every variable the build needs (`PUBLIC_GOOGLE_MAPS_API_KEY`, Places-enabled key, Cloudinary cloud name, Google Sheet CSV URL, calendar ID, `site` URL) with brief instructions for obtaining each.
- Ensure the site works when opened as static files (no server-side runtime dependency); all interactivity (map, tool, theme switcher) is client-side.

**Autonomous-run expectations (no-approval Claude Code session):**
- Scaffold, install dependencies, build, and self-verify against the §15 beta checklist without pausing for approvals.
- Commit logical, well-messaged increments.
- If an external credential is missing (e.g. Maps key), do **not** block — build with graceful fallbacks and clearly flag in the README exactly which keys the owner must add to make maps/photos fully live.
- Leave the repo in a state where `npm install && npm run build` yields an upload-ready `dist/` on the first try.

---

## 17. Final deliverables checklist (what "done" looks like)

- [ ] Working Astro site building from `churches.example.csv` (6 real churches, 6 themed mini-sites).
- [ ] Full inspirational static association site — every page populated with realistic, editable sample content behind a `SAMPLE_CONTENT` flag (§5A).
- [ ] "Churches Near Me" tool with Places autocomplete + geolocation + distance sorting.
- [ ] 12-theme system in `theme.css`, per-church via CSV `theme` column, extensible with a documented template.
- [ ] Theme showcase / template mini-site with a live theme dropdown — ready to show the owner.
- [ ] Cloudinary photo workflow documented for a non-technical user.
- [ ] Google Calendar embed; Give & Resources scaffolded; Contact form.
- [ ] Design language matching the web app (light theme, deep-blue default accent, same token architecture) **and** the modern/elegant IMB-inspired aesthetic — serif+sans typography, whitespace-led, photos optional (§10).
- [ ] `BETA-TESTING.md` completed; `README.md` written for a non-technical maintainer with the morning-demo tour note.
- [ ] Static `dist/` build verified and Hostinger upload instructions included.
- [ ] `.env.example` present; no secrets committed.

# Beta-testing checklist

Status as of the pre-demo verification pass (automated browser tests +
Lighthouse against the built site). Items needing owner-provided keys are
marked **pending key** — their code paths exist and their graceful fallbacks
are what was verified.

## Functional

- [x] Site builds cleanly from the example CSV with zero errors (21 pages).
- [ ] Build from a live Google Sheet source — **pending the owner's Sheet URL**
      (loader implemented; set `CHURCHES_SHEET_CSV_URL` and rebuild to verify).
- [x] CSV → Sheet fallback works: verified by pointing
      `CHURCHES_SHEET_CSV_URL` at an unreachable URL — build logged the
      failure and rendered all churches from the committed CSV.
- [x] All 6 example churches generate a mini-site at `/churches/<slug>` with
      no broken sections.
- [x] Progressive sections hide when a column is empty and show when filled
      (e.g. Blountsville has a fun_fact section; churches without one don't;
      Blount Springs/County Line have no phone and render no phone row).
- [ ] Address autocomplete returns suggestions — **pending Maps key** (Places
      Autocomplete wired; without a key the input is disabled with a notice).
- [x] Selecting a location sorts churches by distance: verified via browser
      geolocation with a simulated position near Trafford — County Line
      sorted first with a "0.4 mi"-style distance pill on every card.
- [x] "Use my location" works and permission denial shows a friendly notice
      (fallback branch verified).
- [x] Map and list views both render; toggling preserves state for the
      session (sessionStorage); pins/cards carry each church's theme color
      (pin SVGs generated from the same `theme.css` accent list; verified
      no-key fallback keeps users on the list with a notice).
- [x] Each of the 12 themes renders correctly; the `/themes` dropdown swaps
      them live (automated test: data-theme attribute + computed styles
      change; 12 options present).
- [x] Adding a new `[data-theme]` block in `theme.css` + using its keyword in
      the CSV produces a new working theme — presets and the showcase
      dropdown are parsed from `theme.css` itself, so new blocks flow
      through automatically.
- [ ] Google Calendar embed loads on Events — **pending calendar ID**
      (embed wired to `PUBLIC_GOOGLE_CALENDAR_ID`; notice + sample cards
      shown without it).
- [x] Give and Resources show tasteful "coming soon" placeholders.
- [x] Every association page (Home, About, Ministries, Events, Missions,
      News, Resources, Give, Contact) renders with realistic sample content —
      no empty or lorem-ipsum pages.
- [x] The `SAMPLE_CONTENT` flag toggles the sitewide banner and "sample"
      tags; banner dismissal persists across pages (localStorage, verified).
- [x] No invented content presented as verified fact — sample events carry no
      real dates, sample posts are self-labelled, unverified office details
      carry "confirm" tags, and demo service times/descriptions are marked
      `[PLACEHOLDER]`/`[VERIFY]` in the data.
- [x] Contact form is clearly wired: submits to
      `PUBLIC_CONTACT_FORM_ENDPOINT` when configured; shows contact details
      and an email button until then.

## Quality

- [x] Aesthetic: serif display headlines (Cormorant Garamond) + sans UI
      (Inter), generous whitespace, and photo-free sections that still look
      intentional (typographic hero, gradient placeholder art, accent bands).
- [x] Mobile-first at 390px: nav (hamburger, verified opening), tool, cards,
      and mini-sites usable; zero horizontal overflow on all key pages
      (automated check).
- [x] Accessibility: Lighthouse a11y **100** (Find a Church) / **96+**
      (Home) after fixes; all 12 accents were contrast-checked (several
      were darkened from the draft palette to clear WCAG AA 4.5:1 on cream
      and for light text on accent); images have alt text; headings ordered;
      keyboard focus styles present.
- [x] Performance: Lighthouse performance 97 (Home), 90–91 (Find a Church)
      on the static build; images lazy-load; the only JS is the directory
      tool, nav toggle, and banner.
- [x] Graceful degradation with no Maps/Places key: sortable, filterable
      list + friendly notice (verified in browser).
- [x] SEO: per-page titles/descriptions, canonical URLs, Open Graph on
      mini-sites (hero photo when present), JSON-LD Church schema, sitemap;
      `/themes` is noindex and excluded from the sitemap.
- [x] No secrets committed; `.env.example` present and documented; `.env`
      gitignored.

## Owner-preview readiness

- [x] Home, Find a Church (6 churches listed; map ready to light up with a
      key), 6 working mini-sites, and the theme showcase are live and
      clickable.
- [x] Every association page reads as a finished site with realistic sample
      copy for the owner to react to.
- [x] README opens with a "start here" tour note for the owner and states
      that all page copy is editable sample content.

## Known limitations to mention to the owner

- The six demo churches use **approximate seeded map coordinates**; adding
  the (free) Geocoding key replaces them with exact rooftop positions
  automatically at the next build.
- Demo service times, about text, and ministries are **representative
  placeholders** pending each church's confirmation (marked in the data).
- Office phone/email/hours on Contact are placeholders awaiting
  confirmation.

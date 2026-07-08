/**
 * Site-wide configuration — the one file to adjust when wiring up real
 * content, keys, and the owner's actual spreadsheet.
 */

/**
 * SAMPLE CONTENT FLAG
 * While true, the site shows a dismissible "sample content" banner and small
 * "sample" tags on invented copy (events, news posts, resource documents…).
 * Flip to false once the owner has replaced the placeholder copy — that one
 * change turns the inspiration site into the real one.
 */
export const SAMPLE_CONTENT = true;

/** Association facts — verified from public sources unless marked. */
export const ASSOCIATION = {
  name: 'Friendship Baptist Association',
  shortName: 'Friendship Baptist',
  tagline: 'Churches together for Blount County',
  organized: 1955,
  amsName: 'Dale Wood',
  amsTitle: 'Associational Mission Strategist',
  officeAddress: '898 Springville Boulevard, Oneonta, AL 35121',
  officeCity: 'Oneonta, Alabama',
  // ⚠️ OWNER TO PROVIDE — placeholders until confirmed:
  officePhone: '(205) 000-0000', // [PLACEHOLDER — confirm office phone]
  officeEmail: 'office@example.org', // [PLACEHOLDER — confirm office email]
  officeHours: 'Monday–Thursday, 9:00 AM – 3:00 PM', // [PLACEHOLDER — confirm]
  facebook: 'https://www.facebook.com/friendshipba',
  churchCount: 24,
};

/**
 * COLUMN MAPPING — spreadsheet header → internal field name.
 * If the owner's real Google Sheet uses different column headers, remap them
 * here (left side = header as it appears in the sheet, lowercase) and
 * nothing else in the codebase needs to change.
 */
export const COLUMN_MAP: Record<string, string> = {
  name: 'name',
  address: 'address',
  latitude: 'latitude',
  longitude: 'longitude',
  photo: 'photo',
  service_times: 'serviceTimes',
  pastor: 'pastor',
  phone: 'phone',
  email: 'email',
  website: 'website',
  facebook: 'facebook',
  slug: 'slug',
  theme: 'theme',
  about: 'about',
  photos: 'photos',
  pastor_bio: 'pastorBio',
  pastor_photo: 'pastorPhoto',
  plan_your_visit: 'planYourVisit',
  beliefs: 'beliefs',
  ministries: 'ministries',
  denomination_note: 'denominationNote',
  founded: 'founded',
  fun_fact: 'funFact',
  giving_url: 'givingUrl',
  livestream_url: 'livestreamUrl',
  calendar_url: 'calendarUrl',
};

/** Environment-driven settings (all optional; every feature degrades gracefully). */
export const ENV = {
  /** Published-to-web CSV URL of the live Google Sheet (primary data source). */
  sheetCsvUrl: import.meta.env.CHURCHES_SHEET_CSV_URL || '',
  /** Browser Maps key (Maps JavaScript + Places). PUBLIC_ = exposed to client. */
  mapsApiKey: import.meta.env.PUBLIC_GOOGLE_MAPS_API_KEY || '',
  /** Build-time-only Geocoding key; never shipped to the browser. */
  geocodingApiKey: import.meta.env.GOOGLE_GEOCODING_API_KEY || '',
  /** Association Google Calendar ID for the Events page embed. */
  calendarId: import.meta.env.PUBLIC_GOOGLE_CALENDAR_ID || '',
  /** Cloudinary cloud name for optimized church photos. */
  cloudinaryCloudName: import.meta.env.PUBLIC_CLOUDINARY_CLOUD_NAME || '',
  /** Static-friendly form handler endpoint (e.g. Formspree) for Contact. */
  contactFormEndpoint: import.meta.env.PUBLIC_CONTACT_FORM_ENDPOINT || '',
};

/** Center of the service area — used as the default map center. (Oneonta, AL) */
export const MAP_DEFAULT_CENTER = { lat: 33.9481, lng: -86.4728 };
export const MAP_DEFAULT_ZOOM = 10;

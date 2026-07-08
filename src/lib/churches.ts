/**
 * Church directory data layer.
 *
 * Source of truth: the owner's Google Sheet (published-to-web CSV URL in
 * CHURCHES_SHEET_CSV_URL). If the Sheet is unset, unreachable, or returns
 * malformed data, the build automatically falls back to the committed
 * spreadsheet at src/data/churches.csv — the site never breaks.
 *
 * Column names are mapped through COLUMN_MAP in src/config.ts, so matching
 * the owner's real spreadsheet headers is a one-place change.
 *
 * Coordinates: explicit latitude/longitude columns win; otherwise addresses
 * are geocoded at build time (Google Geocoding API, when a key is present)
 * and cached in src/data/geocache.json. The demo data ships with seeded
 * approximate coordinates so the map works with no key at all.
 */
import fs from 'node:fs';
import path from 'node:path';
import { parseCsvRecords } from './csv';
import { normalizeTheme } from './themes';
import { COLUMN_MAP, ENV } from '../config';
import fallbackCsv from '../data/churches.csv?raw';

export interface Church {
  name: string;
  address: string;
  lat: number | null;
  lng: number | null;
  /** true when coordinates are seeded estimates, not real geocoding output */
  approxLocation: boolean;
  photo: string;
  serviceTimes: string;
  pastor: string;
  phone: string;
  email: string;
  website: string;
  facebook: string;
  slug: string;
  theme: string;
  about: string;
  photos: string[];
  pastorBio: string;
  pastorPhoto: string;
  planYourVisit: string;
  beliefs: string;
  ministries: string[];
  denominationNote: string;
  founded: string;
  funFact: string;
  givingUrl: string;
  livestreamUrl: string;
  calendarUrl: string;
  /** town parsed from the address, for browse-by-area filtering */
  city: string;
}

const GEOCACHE_PATH = path.join(process.cwd(), 'src/data/geocache.json');

interface GeocacheEntry {
  lat: number;
  lng: number;
  approximate?: boolean;
  source?: string;
}

function slugify(text: string): string {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function splitMulti(value: string): string[] {
  if (!value.trim()) return [];
  return value
    .split(/[|,]/)
    .map((s) => s.trim())
    .filter(Boolean);
}

function parseCity(address: string): string {
  // "87927 US Highway 278, Snead, AL 35952" → "Snead"
  const parts = address.split(',').map((s) => s.trim());
  return parts.length >= 2 ? parts[parts.length - 2].replace(/\s+AL.*$/i, '') : '';
}

function loadGeocache(): Record<string, GeocacheEntry> {
  try {
    const raw = JSON.parse(fs.readFileSync(GEOCACHE_PATH, 'utf8'));
    return raw.entries || {};
  } catch {
    return {};
  }
}

function saveGeocache(entries: Record<string, GeocacheEntry>) {
  try {
    const raw = JSON.parse(fs.readFileSync(GEOCACHE_PATH, 'utf8'));
    raw.entries = entries;
    fs.writeFileSync(GEOCACHE_PATH, JSON.stringify(raw, null, 2) + '\n');
  } catch (err) {
    console.warn('[churches] could not persist geocache:', err);
  }
}

async function geocodeAddress(address: string, key: string): Promise<GeocacheEntry | null> {
  const url =
    'https://maps.googleapis.com/maps/api/geocode/json?address=' +
    encodeURIComponent(address) +
    '&key=' +
    encodeURIComponent(key);
  try {
    const res = await fetch(url);
    if (!res.ok) return null;
    const data = await res.json();
    const loc = data?.results?.[0]?.geometry?.location;
    if (typeof loc?.lat === 'number' && typeof loc?.lng === 'number') {
      return { lat: loc.lat, lng: loc.lng, approximate: false, source: 'google-geocoding' };
    }
  } catch (err) {
    console.warn(`[churches] geocoding failed for "${address}":`, err);
  }
  return null;
}

/** Map raw CSV records (already lowercase-header-keyed) into Church objects. */
function normalizeRecords(records: Record<string, string>[]): Church[] {
  const churches: Church[] = [];
  const seenSlugs = new Set<string>();

  for (const rec of records) {
    const get = (header: string) => rec[header] ?? '';
    // Apply the column mapping: build an internal-field-keyed view.
    const f: Record<string, string> = {};
    for (const [header, field] of Object.entries(COLUMN_MAP)) {
      f[field] = get(header);
    }

    if (!f.name?.trim() || !f.address?.trim()) {
      console.warn('[churches] skipping row missing required name/address:', rec);
      continue;
    }

    let slug = f.slug?.trim() ? slugify(f.slug) : slugify(f.name);
    while (seenSlugs.has(slug)) slug = `${slug}-2`;
    seenSlugs.add(slug);

    const lat = parseFloat(f.latitude);
    const lng = parseFloat(f.longitude);

    churches.push({
      name: f.name.trim(),
      address: f.address.trim(),
      lat: Number.isFinite(lat) ? lat : null,
      lng: Number.isFinite(lng) ? lng : null,
      approxLocation: false,
      photo: f.photo?.trim() || '',
      serviceTimes: f.serviceTimes?.trim() || '',
      pastor: f.pastor?.trim() || '',
      phone: f.phone?.trim() || '',
      email: f.email?.trim() || '',
      website: f.website?.trim() || '',
      facebook: f.facebook?.trim() || '',
      slug,
      theme: normalizeTheme(f.theme),
      about: f.about?.trim() || '',
      photos: splitMulti(f.photos || ''),
      pastorBio: f.pastorBio?.trim() || '',
      pastorPhoto: f.pastorPhoto?.trim() || '',
      planYourVisit: f.planYourVisit?.trim() || '',
      beliefs: f.beliefs?.trim() || '',
      ministries: splitMulti(f.ministries || ''),
      denominationNote: f.denominationNote?.trim() || '',
      founded: f.founded?.trim() || '',
      funFact: f.funFact?.trim() || '',
      givingUrl: f.givingUrl?.trim() || '',
      livestreamUrl: f.livestreamUrl?.trim() || '',
      calendarUrl: f.calendarUrl?.trim() || '',
      city: parseCity(f.address),
    });
  }
  return churches;
}

/** Basic sanity check that fetched Sheet data looks like the church table. */
function looksValid(records: Record<string, string>[]): boolean {
  if (records.length === 0) return false;
  const first = records[0];
  return 'name' in first && 'address' in first;
}

async function fetchSheetRecords(): Promise<Record<string, string>[] | null> {
  if (!ENV.sheetCsvUrl) return null;
  try {
    const res = await fetch(ENV.sheetCsvUrl, { redirect: 'follow' });
    if (!res.ok) {
      console.warn(`[churches] Sheet fetch returned HTTP ${res.status}; using CSV fallback.`);
      return null;
    }
    const text = await res.text();
    const records = parseCsvRecords(text);
    if (!looksValid(records)) {
      console.warn('[churches] Sheet data malformed (missing name/address headers); using CSV fallback.');
      return null;
    }
    return records;
  } catch (err) {
    console.warn('[churches] Sheet fetch failed; using CSV fallback:', err);
    return null;
  }
}

/** Fill in coordinates from the geocache and (when a key exists) the Geocoding API. */
async function applyGeocoding(churches: Church[]): Promise<void> {
  const cache = loadGeocache();
  let cacheDirty = false;
  const key = ENV.geocodingApiKey;

  for (const church of churches) {
    if (church.lat !== null && church.lng !== null) continue; // explicit coords win

    let entry = cache[church.address];

    // Upgrade approximate seed entries to real geocoding when a key exists.
    if (key && (!entry || entry.approximate)) {
      const fresh = await geocodeAddress(church.address, key);
      if (fresh) {
        cache[church.address] = fresh;
        entry = fresh;
        cacheDirty = true;
      }
    }

    if (entry) {
      church.lat = entry.lat;
      church.lng = entry.lng;
      church.approxLocation = !!entry.approximate;
    } else {
      console.warn(`[churches] no coordinates for "${church.name}" (${church.address}) — pin omitted from map.`);
    }
  }

  if (cacheDirty) saveGeocache(cache);
}

let cached: { churches: Church[]; source: 'sheet' | 'csv' } | null = null;

/**
 * Load, validate, and geocode the church directory.
 * Result is cached for the duration of the build.
 */
export async function loadChurches(): Promise<{ churches: Church[]; source: 'sheet' | 'csv' }> {
  if (cached) return cached;

  let source: 'sheet' | 'csv' = 'csv';
  let records = await fetchSheetRecords();
  if (records) {
    source = 'sheet';
  } else {
    records = parseCsvRecords(fallbackCsv);
  }

  const churches = normalizeRecords(records);
  await applyGeocoding(churches);

  console.log(`[churches] loaded ${churches.length} churches from ${source === 'sheet' ? 'Google Sheet' : 'committed CSV fallback'}.`);
  cached = { churches, source };
  return cached;
}

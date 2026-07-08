/// <reference types="astro/client" />

interface ImportMetaEnv {
  readonly PUBLIC_SITE_URL?: string;
  readonly PUBLIC_GOOGLE_MAPS_API_KEY?: string;
  readonly GOOGLE_GEOCODING_API_KEY?: string;
  readonly CHURCHES_SHEET_CSV_URL?: string;
  readonly PUBLIC_GOOGLE_CALENDAR_ID?: string;
  readonly PUBLIC_CLOUDINARY_CLOUD_NAME?: string;
  readonly PUBLIC_CONTACT_FORM_ENDPOINT?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

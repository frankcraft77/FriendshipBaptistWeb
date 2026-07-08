// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import tailwindcss from '@tailwindcss/vite';

// The production URL for the site. Set PUBLIC_SITE_URL in .env once the
// domain is chosen (see README "Domain name"). The default below is the
// first-choice candidate domain and is safe to build with before purchase.
const SITE_URL = process.env.PUBLIC_SITE_URL || 'https://friendshipbaptistassociation.org';

export default defineConfig({
  site: SITE_URL,
  output: 'static',
  integrations: [
    sitemap({
      // Keep the theme showcase/template page out of search indexing.
      filter: (page) => !page.includes('/themes'),
    }),
  ],
  vite: {
    plugins: [tailwindcss()],
  },
});

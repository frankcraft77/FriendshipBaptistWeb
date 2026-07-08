/**
 * Theme presets, read directly from theme.css at build time.
 * Because this parses the real stylesheet, adding a new [data-theme="…"]
 * block in theme.css automatically makes the keyword valid in the
 * spreadsheet AND adds it to the /themes showcase dropdown.
 */
import themeCss from '../styles/theme.css?raw';

export interface ThemePreset {
  keyword: string;
  accent: string;
}

function parsePresets(css: string): ThemePreset[] {
  // Strip comments so the copy-paste template block is not picked up.
  const stripped = css.replace(/\/\*[\s\S]*?\*\//g, '');
  const presets: ThemePreset[] = [];
  const blockRe = /\[data-theme="([\w-]+)"\]\s*\{([^}]*)\}/g;
  let m: RegExpExecArray | null;
  while ((m = blockRe.exec(stripped)) !== null) {
    const accentMatch = m[2].match(/--accent\s*:\s*([^;]+);/);
    presets.push({
      keyword: m[1],
      accent: accentMatch ? accentMatch[1].trim() : '#2f5d8f',
    });
  }
  return presets;
}

export const THEME_PRESETS: ThemePreset[] = parsePresets(themeCss);

export const THEME_KEYWORDS = THEME_PRESETS.map((p) => p.keyword);

export const DEFAULT_THEME = 'blue';

/** Map of keyword → accent color, for map pins and client-side use. */
export const THEME_ACCENTS: Record<string, string> = Object.fromEntries(
  THEME_PRESETS.map((p) => [p.keyword, p.accent])
);

/** Validate a spreadsheet theme value; unknown/blank falls back to default. */
export function normalizeTheme(value: string | undefined): string {
  const v = (value || '').trim().toLowerCase();
  return THEME_KEYWORDS.includes(v) ? v : DEFAULT_THEME;
}

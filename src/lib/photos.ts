/**
 * Photo resolution — supports both hosting styles (see README "Photos"):
 *   • Full URLs (Cloudinary or anywhere else) are used as-is, with automatic
 *     Cloudinary optimization parameters injected when applicable.
 *   • Bare filenames resolve against /public/churches/ in the repo.
 * Blank values return null so components can render the placeholder
 * treatment instead of a broken image.
 */

const CLOUDINARY_UPLOAD_SEGMENT = '/image/upload/';

export function isUrl(value: string): boolean {
  return /^https?:\/\//i.test(value);
}

/**
 * Resolve a photo cell value to a usable image URL, or null when empty.
 * `width` hints Cloudinary to serve an appropriately sized, auto-format,
 * auto-quality rendition (phones upload huge photos; this keeps them fast).
 */
export function resolvePhoto(value: string | undefined, width = 1200): string | null {
  const v = (value || '').trim();
  if (!v) return null;
  if (isUrl(v)) {
    if (v.includes('res.cloudinary.com') && v.includes(CLOUDINARY_UPLOAD_SEGMENT)) {
      // Inject transformation only if the URL doesn't already carry one.
      const after = v.split(CLOUDINARY_UPLOAD_SEGMENT)[1] || '';
      const hasTransform = /^([a-z]+_[^/]+,?)+\//.test(after);
      if (!hasTransform) {
        return v.replace(
          CLOUDINARY_UPLOAD_SEGMENT,
          `${CLOUDINARY_UPLOAD_SEGMENT}f_auto,q_auto,w_${width}/`
        );
      }
    }
    return v;
  }
  // Bare filename → local folder committed to the repo.
  return `/churches/${v}`;
}

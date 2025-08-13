/* Auto-register every image in /resources/brand */
const files = import.meta.glob('/resources/brand/*.{png,svg,webp}', {
  eager: true,
  query: '?url',
  import: 'default',
}) as Record<string, string>;

export type BrandKey = string;

/* Map: base filename (no extension, lowercased, dashed) -> url */
export const BRAND_ASSETS: Record<BrandKey, string> = Object.fromEntries(
  Object.entries(files).map(([k, url]) => {
    const base = k.split('/').pop()!.replace(/\.[^.]+$/, '');
    const key = base.toLowerCase().replace(/\s+/g, '-');
    return [key, url as string];
  })
);

/* Helpful picks (fallbacks if specific keys not found) */
export const PICK = {
  icon: Object.entries(BRAND_ASSETS).find(([k]) => /icon|shield|mark|logo/.test(k))?.[1],
  stacked: Object.entries(BRAND_ASSETS).find(([k]) => /stack|lockup|text.*icon/.test(k))?.[1],
  wordmark: Object.entries(BRAND_ASSETS).find(([k]) => /wordmark|text-only|type/.test(k))?.[1],
};
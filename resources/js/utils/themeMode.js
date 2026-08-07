export const ADMIN_THEME_MODE_KEY = 'admin-theme';
export const OWNER_THEME_MODE_KEY = 'owner-theme';

export function resolveThemeMode(mode) {
  if (mode === 'dark' || mode === 'light') return mode;
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyDocumentThemeMode(mode) {
  const resolvedMode = resolveThemeMode(mode);
  document.documentElement.classList.toggle('dark', resolvedMode === 'dark');
  document.documentElement.classList.toggle('light', resolvedMode === 'light');
  document.documentElement.setAttribute('data-theme', resolvedMode);
  return resolvedMode;
}

export function themeModeKeyForPath(path = window.location.pathname) {
  if (/^\/admin(?:\/|$)/.test(path)) return ADMIN_THEME_MODE_KEY;
  if (/^\/(?:owner|staff)(?:\/|$)/.test(path)) return OWNER_THEME_MODE_KEY;
  return null;
}

export function applyThemeModeForPath(path = window.location.pathname) {
  const storageKey = themeModeKeyForPath(path);
  if (!storageKey) return applyDocumentThemeMode('light');
  return applyDocumentThemeMode(localStorage.getItem(storageKey) || 'system');
}

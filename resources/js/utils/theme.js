function getContrastColor(hex) {
  if (!hex) return '#ffffff';
  let c = hex.replace(/^#/, '');
  if (c.length === 3) {
    c = c.split('').map(x => x + x).join('');
  }
  const r = parseInt(c.slice(0, 2), 16);
  const g = parseInt(c.slice(2, 4), 16);
  const b = parseInt(c.slice(4, 6), 16);
  const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
  return (yiq >= 150) ? '#18181b' : '#ffffff';
}

export function applyCustomThemeStyles() {
  const themeDataStr = localStorage.getItem('admin-custom-theme');
  let styleEl = document.getElementById('admin-custom-theme-style');
  
  if (!themeDataStr) {
    if (styleEl) styleEl.remove();
    return;
  }
  
  try {
    const themeData = JSON.parse(themeDataStr);
    let cssContent = '';
    
    if (themeData.radius) {
      cssContent += `
:root {
  --admin-radius: ${themeData.radius} !important;
  --admin-radius-lg: calc(${themeData.radius} + 4px) !important;
}
`;
    }

    if (themeData.font_size) {
      const fontSize = themeData.font_size || '14px';
      let fontScale = '1';
      if (fontSize === '12px') fontScale = '0.857';
      else if (fontSize === '13px') fontScale = '0.929';
      else if (fontSize === '15px') fontScale = '1.071';
      else if (fontSize === '16px') fontScale = '1.143';
      cssContent += `
.sg-shell-admin,
body.sg-admin-theme-scope {
  ${themeData.font_family ? `--admin-font-family: ${themeData.font_family} !important;` : ''}
  --admin-font-size: ${fontSize} !important;
  --admin-font-size-scale: ${fontScale} !important;
  zoom: var(--admin-font-size-scale, 1) !important;
}
`;
    }
    
    if (themeData.light) {
      const l = themeData.light;
      const primaryText = getContrastColor(l.primary);
      const blueText = getContrastColor(l.secondary || '#2563eb');
      const surfaceText = getContrastColor(l.background || l.card);
      cssContent += `
:root:not([data-theme="dark"]) {
  ${l.primary ? `--admin-primary: ${l.primary} !important;` : ''}
  --admin-primary-text: ${primaryText} !important;
  ${l.secondary ? `--admin-blue: ${l.secondary} !important;` : ''}
  --admin-blue-text: ${blueText} !important;
  ${l.accent ? `--admin-hover: ${l.accent} !important;` : ''}
  ${l.muted ? `--admin-muted: ${l.muted} !important;` : ''}
  ${l.destructive ? `--admin-danger: ${l.destructive} !important;` : ''}
  ${l.border ? `--admin-border: ${l.border} !important;` : ''}
  ${l.card ? `--admin-surface: ${l.card} !important;` : ''}
  ${l.background ? `--admin-bg: ${l.background} !important;` : ''}
  ${l.background || l.card ? `--admin-text: ${surfaceText} !important;` : ''}
  
  /* Derived variables */
  --admin-primary-soft: color-mix(in srgb, var(--admin-primary) 10%, transparent) !important;
  --admin-primary-dark: color-mix(in srgb, var(--admin-primary) 80%, black) !important;
  --admin-primary-light: color-mix(in srgb, var(--admin-primary) 80%, white) !important;
  --admin-bg-gradient: linear-gradient(180deg, var(--admin-surface), var(--admin-bg)) !important;
  --admin-topbar-bg: color-mix(in srgb, var(--admin-surface) 82%, transparent) !important;
  --admin-border-soft: color-mix(in srgb, var(--admin-border) 60%, transparent) !important;
  --admin-surface-muted: color-mix(in srgb, var(--admin-surface) 95%, var(--admin-primary)) !important;
  --admin-faint: color-mix(in srgb, var(--admin-muted) 78%, var(--admin-text)) !important;
  --admin-primary-ring: color-mix(in srgb, var(--admin-primary) 22%, transparent) !important;
  --admin-success: var(--admin-primary) !important;
  --admin-success-soft: var(--admin-primary-soft) !important;
  --admin-success-text: var(--admin-primary-dark) !important;
  --admin-blue-soft: color-mix(in srgb, var(--admin-blue) 12%, transparent) !important;
  --admin-danger-soft: color-mix(in srgb, var(--admin-danger) 12%, transparent) !important;
  --admin-danger-text: color-mix(in srgb, var(--admin-danger) 80%, black) !important;
}
`;
    }
    
    if (themeData.dark) {
      const d = themeData.dark;
      const primaryText = getContrastColor(d.primary);
      const blueText = getContrastColor(d.secondary || '#3b82f6');
      const surfaceText = getContrastColor(d.background || d.card);
      cssContent += `
[data-theme="dark"] {
  ${d.primary ? `--admin-primary: ${d.primary} !important;` : ''}
  --admin-primary-text: ${primaryText} !important;
  ${d.secondary ? `--admin-blue: ${d.secondary} !important;` : ''}
  --admin-blue-text: ${blueText} !important;
  ${d.accent ? `--admin-hover: ${d.accent} !important;` : ''}
  ${d.muted ? `--admin-muted: ${d.muted} !important;` : ''}
  ${d.destructive ? `--admin-danger: ${d.destructive} !important;` : ''}
  ${d.border ? `--admin-border: ${d.border} !important;` : ''}
  ${d.card ? `--admin-surface: ${d.card} !important;` : ''}
  ${d.background ? `--admin-bg: ${d.background} !important;` : ''}
  ${d.background || d.card ? `--admin-text: ${surfaceText} !important;` : ''}
  
  /* Derived variables */
  --admin-primary-soft: color-mix(in srgb, var(--admin-primary) 15%, transparent) !important;
  --admin-primary-dark: color-mix(in srgb, var(--admin-primary) 80%, black) !important;
  --admin-primary-light: color-mix(in srgb, var(--admin-primary) 80%, white) !important;
  --admin-bg-gradient: linear-gradient(180deg, var(--admin-surface), var(--admin-bg)) !important;
  --admin-topbar-bg: color-mix(in srgb, var(--admin-surface) 82%, transparent) !important;
  --admin-border-soft: color-mix(in srgb, var(--admin-border) 60%, transparent) !important;
  --admin-surface-muted: color-mix(in srgb, var(--admin-surface) 95%, var(--admin-primary)) !important;
  --admin-faint: color-mix(in srgb, var(--admin-muted) 78%, var(--admin-text)) !important;
  --admin-primary-ring: color-mix(in srgb, var(--admin-primary) 22%, transparent) !important;
  --admin-success: var(--admin-primary) !important;
  --admin-success-soft: var(--admin-primary-soft) !important;
  --admin-success-text: var(--admin-primary-dark) !important;
  --admin-blue-soft: color-mix(in srgb, var(--admin-blue) 16%, transparent) !important;
  --admin-danger-soft: color-mix(in srgb, var(--admin-danger) 14%, transparent) !important;
  --admin-danger-text: color-mix(in srgb, var(--admin-danger) 80%, white) !important;
}
`;
    }
    
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = 'admin-custom-theme-style';
      document.head.appendChild(styleEl);
    }
    styleEl.textContent = cssContent;
  } catch (e) {
    console.error('Failed to parse custom theme', e);
  }
}

const AUTH_THEME_STYLE_ID = 'auth-theme-style';
const AUTH_THEME_STORAGE_KEY = 'admin-custom-theme';
const AUTH_THEME_DEFAULTS = {
  radius: '8px',
  font_size: '14px',
  font_family: "'Outfit', sans-serif",
  light: {
    primary: '#18181b',
    muted: '#71717a',
    border: '#e4e4e7',
    card: '#ffffff',
    background: '#fafafa',
  },
  dark: {
    primary: '#fafafa',
    muted: '#a1a1aa',
    border: '#27272a',
    card: '#09090b',
    background: '#09090b',
  },
};

function normalizeAuthHex(value, fallback) {
  if (typeof value !== 'string') return fallback;
  const hex = value.trim();
  if (/^#[0-9a-f]{6}$/i.test(hex)) return hex;
  if (/^#[0-9a-f]{3}$/i.test(hex)) {
    return '#' + hex.slice(1).split('').map((char) => char + char).join('');
  }
  return fallback;
}

function getAuthThemeMode() {
  const savedMode = localStorage.getItem('admin-theme');
  if (savedMode === 'light' || savedMode === 'dark') return savedMode;
  if (savedMode === 'system') {
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  return 'dark';
}

function getAuthThemeData(themeData = {}) {
  const mode = getAuthThemeMode();
  const defaults = AUTH_THEME_DEFAULTS[mode];
  const configured = themeData[mode] || {};
  const fontSize = ['12px', '13px', '14px', '15px', '16px'].includes(themeData.font_size)
    ? themeData.font_size
    : AUTH_THEME_DEFAULTS.font_size;
  const fontFamily = typeof themeData.font_family === 'string'
    && /^[\w\s,'"-]+$/.test(themeData.font_family)
    ? themeData.font_family
    : AUTH_THEME_DEFAULTS.font_family;

  return {
    radius: ['0px', '4px', '8px', '12px', '16px'].includes(themeData.radius)
      ? themeData.radius
      : AUTH_THEME_DEFAULTS.radius,
    fontSize,
    fontFamily,
    primary: normalizeAuthHex(configured.primary, defaults.primary),
    muted: normalizeAuthHex(configured.muted, defaults.muted),
    border: normalizeAuthHex(configured.border, defaults.border),
    surface: normalizeAuthHex(configured.card, defaults.card),
    background: normalizeAuthHex(configured.background, defaults.background),
  };
}

function authFontScale(fontSize) {
  return ({ '12px': '0.857', '13px': '0.929', '15px': '1.071', '16px': '1.143' })[fontSize] || '1';
}

export function applyAuthThemeStyles(themeData = null) {
  let resolvedTheme = themeData;
  if (!resolvedTheme) {
    try {
      resolvedTheme = JSON.parse(localStorage.getItem(AUTH_THEME_STORAGE_KEY) || '{}');
    } catch {
      resolvedTheme = {};
    }
  }

  const theme = getAuthThemeData({ ...AUTH_THEME_DEFAULTS, ...resolvedTheme });
  const style = document.getElementById(AUTH_THEME_STYLE_ID) || document.createElement('style');
  style.id = AUTH_THEME_STYLE_ID;
  style.textContent = `
.auth-layout {
  --auth-primary: ${theme.primary};
  --auth-primary-text: ${getContrastColor(theme.primary)};
  --auth-primary-soft: color-mix(in srgb, var(--auth-primary) 14%, transparent);
  --auth-primary-dark: color-mix(in srgb, var(--auth-primary) 82%, black);
  --auth-background: ${theme.background};
  --auth-surface: ${theme.surface};
  --auth-text: ${getContrastColor(theme.background)};
  --auth-muted: ${theme.muted};
  --auth-border: ${theme.border};
  --auth-radius: ${theme.radius};
  --auth-font-family: ${theme.fontFamily};
  --auth-font-scale: ${authFontScale(theme.fontSize)};
  zoom: var(--auth-font-scale, 1) !important;
  background: var(--auth-background) !important;
  color: var(--auth-text) !important;
  font-family: var(--auth-font-family) !important;
}
.auth-layout [class~="bg-zinc-950"] { background-color: var(--auth-surface) !important; }
.auth-layout > [class~="bg-zinc-950"],
.auth-layout > [class~="bg-zinc-950"] [class~="bg-zinc-950"] { background-color: var(--auth-background) !important; }
.auth-layout [class~="bg-zinc-900"] { background-color: var(--auth-surface) !important; }
.auth-layout [class~="bg-zinc-800"] { background-color: var(--auth-primary-dark) !important; }
.auth-layout [class~="text-zinc-100"],
.auth-layout [class~="text-white"] { color: var(--auth-text) !important; }
.auth-layout [class~="text-zinc-200"] { color: var(--auth-text) !important; }
.auth-layout [class~="text-zinc-400"],
.auth-layout [class~="text-zinc-500"] { color: var(--auth-muted) !important; }
.auth-layout [class~="border-zinc-900"],
.auth-layout [class~="border-zinc-800"],
.auth-layout [class~="border-zinc-700"],
.auth-layout [class~="border-zinc-600"] { border-color: var(--auth-border) !important; }
.auth-layout input {
  background-color: var(--auth-surface) !important;
  border-color: var(--auth-border) !important;
  border-radius: var(--auth-radius) !important;
  color: var(--auth-text) !important;
  -webkit-text-fill-color: var(--auth-text) !important;
  caret-color: var(--auth-primary) !important;
}
.auth-layout input::placeholder { color: var(--auth-muted) !important; }
.auth-layout input:focus {
  border-color: var(--auth-primary) !important;
  box-shadow: 0 0 0 3px var(--auth-primary-soft) !important;
}
.auth-layout [class~="rounded-md"] { border-radius: var(--auth-radius) !important; }
.auth-layout [class~="rounded-lg"] { border-radius: calc(var(--auth-radius) + 2px) !important; }
.auth-layout form > button[type="submit"] {
  background-color: var(--auth-primary) !important;
  border-color: var(--auth-primary) !important;
  color: var(--auth-primary-text) !important;
}
.auth-layout form > button[type="submit"]:hover:not(:disabled) {
  background-color: var(--auth-primary-dark) !important;
  border-color: var(--auth-primary-dark) !important;
}
.auth-layout [class~="hover:!bg-zinc-800"]:hover,
.auth-layout [class~="hover:bg-zinc-800"]:hover { background-color: var(--auth-primary-dark) !important; }
.auth-layout [class~="hover:!border-zinc-600"]:hover,
.auth-layout [class~="hover:border-zinc-600"]:hover { border-color: var(--auth-primary-dark) !important; }
.auth-layout [class~="hover:text-zinc-200"]:hover,
.auth-layout [class~="hover:text-zinc-300"]:hover { color: var(--auth-text) !important; }
.auth-layout input:-webkit-autofill,
.auth-layout input:-webkit-autofill:focus,
.auth-layout input:-webkit-autofill:active {
  -webkit-text-fill-color: var(--auth-text) !important;
  box-shadow: 0 0 0 1000px var(--auth-surface) inset !important;
  -webkit-box-shadow: 0 0 0 1000px var(--auth-surface) inset !important;
}
`;
  if (!style.parentNode) document.head.appendChild(style);
}

export async function loadPublicThemeStyles() {
  try {
    const response = await fetch('/api/ui-theme', { headers: { Accept: 'application/json' } });
    if (!response.ok) return;
    const themeData = await response.json();
    localStorage.setItem(AUTH_THEME_STORAGE_KEY, JSON.stringify(themeData));
    applyAuthThemeStyles(themeData);
  } catch {
    // The cached/default theme is already applied; auth must remain usable offline.
  }
}

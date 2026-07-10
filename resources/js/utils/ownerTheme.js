function normalizeHex(hex, fallback) {
  if (typeof hex !== 'string') return fallback;
  const value = hex.trim();
  if (/^#[0-9a-f]{6}$/i.test(value)) return value;
  if (/^#[0-9a-f]{3}$/i.test(value)) {
    return '#' + value.slice(1).split('').map((char) => char + char).join('');
  }
  return fallback;
}

function contrastColor(hex) {
  const value = normalizeHex(hex, '#22a653').replace('#', '');
  const r = parseInt(value.slice(0, 2), 16);
  const g = parseInt(value.slice(2, 4), 16);
  const b = parseInt(value.slice(4, 6), 16);
  const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
  return yiq >= 150 ? '#101c15' : '#ffffff';
}

export const OWNER_THEME_SCOPE_CLASS = 'sg-owner-theme-scope';

export const OWNER_THEME_DEFAULTS = {
  active_theme_id: 'owner-zinc',
  sidebar_style: 'one-level',
  radius: '8px',
  presets: [
    {
      id: 'owner-zinc',
      name: 'Zinc',
      color: '#18181b',
      light: { primary: '#18181b', secondary: '#27272a', accent: '#f4f4f5', muted: '#71717a', destructive: '#ef4444', border: '#e4e4e7', card: '#ffffff', background: '#fafafa' },
      dark: { primary: '#fafafa', secondary: '#27272a', accent: '#27272a', muted: '#a1a1aa', destructive: '#ef4444', border: '#27272a', card: '#09090b', background: '#09090b' },
    },
  ],
  custom_themes: [],
};

export function mergeOwnerThemeSettings(settings = {}) {
  return {
    ...OWNER_THEME_DEFAULTS,
    ...settings,
    presets: Array.isArray(settings.presets) && settings.presets.length ? settings.presets : OWNER_THEME_DEFAULTS.presets,
    custom_themes: Array.isArray(settings.custom_themes) ? settings.custom_themes : [],
    radius: settings.radius || OWNER_THEME_DEFAULTS.radius,
    sidebar_style: settings.sidebar_style || OWNER_THEME_DEFAULTS.sidebar_style,
  };
}

export function getOwnerThemePreset(settings = {}) {
  const merged = mergeOwnerThemeSettings(settings);
  const allPresets = [...merged.presets, ...merged.custom_themes];
  return allPresets.find((preset) => preset.id === merged.active_theme_id) || allPresets[0] || OWNER_THEME_DEFAULTS.presets[0];
}

function cssVarsForMode(mode) {
  const primary = normalizeHex(mode.primary, '#18181b');
  const secondary = normalizeHex(mode.secondary, '#2563eb');
  const accent = normalizeHex(mode.accent, '#f4f4f5');
  const muted = normalizeHex(mode.muted, '#71717a');
  const danger = normalizeHex(mode.destructive, '#dc2626');
  const border = normalizeHex(mode.border, '#e4e4e7');
  const surface = normalizeHex(mode.card, '#ffffff');
  const background = normalizeHex(mode.background, '#fafafa');
  const primaryText = contrastColor(primary);
  const isDarkBackground = contrastColor(background) === '#ffffff';
  const floatingBg = isDarkBackground
    ? 'color-mix(in srgb, ' + background + ' 82%, ' + primary + ')'
    : 'color-mix(in srgb, ' + primary + ' 34%, #111827)';
  const floatingHover = isDarkBackground
    ? 'color-mix(in srgb, ' + background + ' 68%, ' + primary + ')'
    : 'color-mix(in srgb, ' + primary + ' 48%, #111827)';
  const floatingActive = isDarkBackground
    ? 'color-mix(in srgb, ' + background + ' 54%, ' + primary + ')'
    : 'color-mix(in srgb, ' + primary + ' 60%, #111827)';
  const floatingPanelBg = isDarkBackground
    ? 'color-mix(in srgb, ' + background + ' 92%, ' + surface + ')'
    : 'color-mix(in srgb, ' + surface + ' 12%, #18181b)';
  const floatingFg = '#ffffff';

  return [
    '--admin-primary: ' + primary + ' !important;',
    '--admin-primary-text: ' + primaryText + ' !important;',
    '--admin-blue: ' + secondary + ' !important;',
    '--admin-hover: ' + accent + ' !important;',
    '--admin-text: ' + contrastColor(background) + ' !important;',
    '--sg-text: var(--admin-text) !important;',
    '--admin-muted: ' + muted + ' !important;',
    '--admin-faint: color-mix(in srgb, ' + muted + ' 78%, transparent) !important;',
    '--admin-danger: ' + danger + ' !important;',
    '--admin-danger-text: ' + danger + ' !important;',
    '--admin-border: ' + border + ' !important;',
    '--admin-border-soft: color-mix(in srgb, ' + border + ' 58%, transparent) !important;',
    '--admin-surface: ' + surface + ' !important;',
    '--admin-card-bg: ' + surface + ' !important;',
    '--admin-bg: ' + background + ' !important;',
    '--admin-bg-soft: color-mix(in srgb, ' + background + ' 82%, ' + surface + ') !important;',
    '--admin-surface-muted: color-mix(in srgb, ' + surface + ' 90%, ' + primary + ') !important;',
    '--admin-primary-soft: color-mix(in srgb, ' + primary + ' 14%, transparent) !important;',
    '--admin-primary-light: color-mix(in srgb, ' + primary + ' 76%, white) !important;',
    '--admin-primary-dark: color-mix(in srgb, ' + primary + ' 76%, black) !important;',
    '--admin-primary-ring: color-mix(in srgb, ' + primary + ' 24%, transparent) !important;',
    '--admin-focus-border: ' + primary + ' !important;',
    '--admin-focus-ring: color-mix(in srgb, ' + primary + ' 22%, transparent) !important;',
    '--admin-floating-bg: ' + floatingBg + ' !important;',
    '--admin-floating-fg: ' + floatingFg + ' !important;',
    '--admin-floating-border: color-mix(in srgb, ' + border + ' 72%, ' + primary + ') !important;',
    '--admin-floating-hover: ' + floatingHover + ' !important;',
    '--admin-floating-active: ' + floatingActive + ' !important;',
    '--admin-floating-panel-bg: ' + floatingPanelBg + ' !important;',
    '--admin-success: ' + primary + ' !important;',
    '--admin-success-soft: color-mix(in srgb, ' + primary + ' 14%, transparent) !important;',
    '--admin-success-text: color-mix(in srgb, ' + primary + ' 72%, black) !important;',
    '--admin-sidebar: ' + surface + ' !important;',
    '--admin-bg-gradient: linear-gradient(180deg, color-mix(in srgb, ' + surface + ' 82%, transparent), ' + background + ') !important;',
    '--admin-topbar-bg: color-mix(in srgb, ' + surface + ' 86%, transparent) !important;',
    '--admin-topbar-border: color-mix(in srgb, ' + border + ' 72%, transparent) !important;',
    '--admin-header-gradient: linear-gradient(135deg, ' + surface + ', color-mix(in srgb, ' + background + ' 78%, ' + surface + ')) !important;',
  ].join('\n    ');
}

export function buildOwnerThemeCss(settings = {}) {
  const merged = mergeOwnerThemeSettings(settings);
  const preset = getOwnerThemePreset(merged);
  const radius = merged.radius || '8px';
  const light = cssVarsForMode(preset.light || OWNER_THEME_DEFAULTS.presets[0].light);
  const dark = cssVarsForMode(preset.dark || OWNER_THEME_DEFAULTS.presets[0].dark);
  const ownerScope = '.sg-shell-owner,\nbody.' + OWNER_THEME_SCOPE_CLASS;
  const ownerLightScope = ':root:not([data-theme="dark"]) .sg-shell-owner,\n'
    + ':root:not([data-theme="dark"]) body.' + OWNER_THEME_SCOPE_CLASS + ',\n'
    + 'body.' + OWNER_THEME_SCOPE_CLASS + ':not([data-theme="dark"])';
  const ownerDarkScope = '[data-theme="dark"] .sg-shell-owner,\n'
    + '[data-theme="dark"] body.' + OWNER_THEME_SCOPE_CLASS + ',\n'
    + 'body.' + OWNER_THEME_SCOPE_CLASS + '[data-theme="dark"]';
  return ownerScope + ' {\n  --admin-radius: ' + radius + ' !important;\n  --admin-radius-lg: calc(' + radius + ' + 4px) !important;\n}\n'
    + ownerLightScope + ' {\n  ' + light + '\n}\n'
    + ownerDarkScope + ' {\n  ' + dark + '\n}\n';
}

export function enableOwnerThemeScope() {
  document.body?.classList.add(OWNER_THEME_SCOPE_CLASS);
}

export function disableOwnerThemeScope() {
  document.body?.classList.remove(OWNER_THEME_SCOPE_CLASS);
}

export function applyOwnerTheme(settings = {}) {
  let styleEl = document.getElementById('owner-custom-theme-style');
  if (!styleEl) {
    styleEl = document.createElement('style');
    styleEl.id = 'owner-custom-theme-style';
    document.head.appendChild(styleEl);
  }
  styleEl.textContent = buildOwnerThemeCss(settings);
}

export function applyOwnerThemeFromStorage() {
  const saved = localStorage.getItem('owner-custom-theme');
  if (saved) {
    try {
      const parsed = JSON.parse(saved);
      const settings = {
        active_theme_id: 'owner-custom',
        radius: parsed.radius || '8px',
        presets: [],
        custom_themes: [
          {
            id: 'owner-custom',
            name: 'Custom',
            light: parsed.light,
            dark: parsed.dark
          }
        ]
      };
      applyOwnerTheme(settings);
    } catch (e) {
      console.error('Failed to apply owner theme from storage', e);
    }
  }
}

export function clearOwnerTheme() {
  document.getElementById('owner-custom-theme-style')?.remove();
  disableOwnerThemeScope();
}

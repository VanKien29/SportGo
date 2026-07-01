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
    
    if (themeData.light) {
      const l = themeData.light;
      cssContent += `
:root {
  ${l.primary ? `--admin-primary: ${l.primary} !important;` : ''}
  ${l.secondary ? `--admin-blue: ${l.secondary} !important;` : ''}
  ${l.accent ? `--admin-hover: ${l.accent} !important;` : ''}
  ${l.muted ? `--admin-muted: ${l.muted} !important;` : ''}
  ${l.destructive ? `--admin-danger: ${l.destructive} !important;` : ''}
  ${l.border ? `--admin-border: ${l.border} !important;` : ''}
  ${l.card ? `--admin-surface: ${l.card} !important;` : ''}
  ${l.background ? `--admin-bg: ${l.background} !important;` : ''}
  
  /* Derived variables */
  --admin-primary-soft: color-mix(in srgb, var(--admin-primary) 10%, transparent) !important;
  --admin-primary-dark: color-mix(in srgb, var(--admin-primary) 80%, black) !important;
  --admin-primary-light: color-mix(in srgb, var(--admin-primary) 80%, white) !important;
  --admin-bg-gradient: linear-gradient(180deg, var(--admin-surface), var(--admin-bg)) !important;
  --admin-topbar-bg: color-mix(in srgb, var(--admin-surface) 82%, transparent) !important;
  --admin-border-soft: color-mix(in srgb, var(--admin-border) 60%, transparent) !important;
  --admin-surface-muted: color-mix(in srgb, var(--admin-surface) 95%, var(--admin-primary)) !important;
}
`;
    }
    
    if (themeData.dark) {
      const d = themeData.dark;
      cssContent += `
[data-theme="dark"] {
  ${d.primary ? `--admin-primary: ${d.primary} !important;` : ''}
  ${d.secondary ? `--admin-blue: ${d.secondary} !important;` : ''}
  ${d.accent ? `--admin-hover: ${d.accent} !important;` : ''}
  ${d.muted ? `--admin-muted: ${d.muted} !important;` : ''}
  ${d.destructive ? `--admin-danger: ${d.destructive} !important;` : ''}
  ${d.border ? `--admin-border: ${d.border} !important;` : ''}
  ${d.card ? `--admin-surface: ${d.card} !important;` : ''}
  ${d.background ? `--admin-bg: ${d.background} !important;` : ''}
  
  /* Derived variables */
  --admin-primary-soft: color-mix(in srgb, var(--admin-primary) 15%, transparent) !important;
  --admin-primary-dark: color-mix(in srgb, var(--admin-primary) 80%, black) !important;
  --admin-primary-light: color-mix(in srgb, var(--admin-primary) 80%, white) !important;
  --admin-bg-gradient: linear-gradient(180deg, var(--admin-surface), var(--admin-bg)) !important;
  --admin-topbar-bg: color-mix(in srgb, var(--admin-surface) 82%, transparent) !important;
  --admin-border-soft: color-mix(in srgb, var(--admin-border) 60%, transparent) !important;
  --admin-surface-muted: color-mix(in srgb, var(--admin-surface) 95%, var(--admin-primary)) !important;
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

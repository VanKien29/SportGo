export const ICON_REGISTRY = {
  dashboard: [
    ['rect', { x: 3, y: 3, width: 7, height: 7, rx: 1 }],
    ['rect', { x: 14, y: 3, width: 7, height: 7, rx: 1 }],
    ['rect', { x: 14, y: 14, width: 7, height: 7, rx: 1 }],
    ['rect', { x: 3, y: 14, width: 7, height: 7, rx: 1 }],
  ],
  menu: [
    ['path', { d: 'M4 6h16M4 12h16M4 18h16' }],
  ],
  moreHorizontal: [
    ['circle', { cx: 5, cy: 12, r: 1 }],
    ['circle', { cx: 12, cy: 12, r: 1 }],
    ['circle', { cx: 19, cy: 12, r: 1 }],
  ],
  users: [
    ['path', { d: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2' }],
    ['circle', { cx: 9, cy: 7, r: 4 }],
    ['path', { d: 'M22 21v-2a4 4 0 0 0-3-3.87' }],
    ['path', { d: 'M16 3.13a4 4 0 0 1 0 7.75' }],
  ],
  shield: [
    ['path', { d: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z' }],
  ],
  shieldCheck: [
    ['path', { d: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z' }],
    ['path', { d: 'm9 12 2 2 4-4' }],
  ],
  key: [
    ['circle', { cx: 7.5, cy: 15.5, r: 5.5 }],
    ['path', { d: 'm21 2-9.6 9.6' }],
    ['path', { d: 'm15.5 7.5 3 3L22 7l-3-3' }],
  ],
  sliders: [
    ['line', { x1: 4, y1: 21, x2: 4, y2: 14 }],
    ['line', { x1: 4, y1: 10, x2: 4, y2: 3 }],
    ['line', { x1: 12, y1: 21, x2: 12, y2: 12 }],
    ['line', { x1: 12, y1: 8, x2: 12, y2: 3 }],
    ['line', { x1: 20, y1: 21, x2: 20, y2: 16 }],
    ['line', { x1: 20, y1: 12, x2: 20, y2: 3 }],
    ['line', { x1: 2, y1: 14, x2: 6, y2: 14 }],
    ['line', { x1: 10, y1: 8, x2: 14, y2: 8 }],
    ['line', { x1: 18, y1: 16, x2: 22, y2: 16 }],
  ],
  fileText: [
    ['path', { d: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z' }],
    ['path', { d: 'M14 2v6h6' }],
    ['path', { d: 'M16 13H8' }],
    ['path', { d: 'M16 17H8' }],
    ['path', { d: 'M10 9H8' }],
  ],
  layers: [
    ['path', { d: 'm12 2 9 5-9 5-9-5 9-5Z' }],
    ['path', { d: 'm3 12 9 5 9-5' }],
    ['path', { d: 'm3 17 9 5 9-5' }],
  ],
  image: [
    ['rect', { x: 3, y: 3, width: 18, height: 18, rx: 2 }],
    ['circle', { cx: 8.5, cy: 8.5, r: 1.5 }],
    ['path', { d: 'm21 15-5-5L5 21' }],
  ],
  building: [
    ['rect', { x: 4, y: 2, width: 16, height: 20, rx: 2 }],
    ['path', { d: 'M9 22v-4h6v4' }],
    ['path', { d: 'M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01' }],
  ],
  calendar: [
    ['rect', { x: 3, y: 4, width: 18, height: 18, rx: 2 }],
    ['path', { d: 'M16 2v4M8 2v4M3 10h18' }],
  ],
  creditCard: [
    ['rect', { x: 2, y: 5, width: 20, height: 14, rx: 2 }],
    ['path', { d: 'M2 10h20' }],
  ],
  qrCode: [
    ['rect', { x: 3, y: 3, width: 7, height: 7, rx: 1 }],
    ['rect', { x: 14, y: 3, width: 7, height: 7, rx: 1 }],
    ['rect', { x: 3, y: 14, width: 7, height: 7, rx: 1 }],
    ['path', { d: 'M14 14h2v2h-2zM18 14h3M14 18h3M19 18h2v3h-3M14 21h1' }],
  ],
  banknote: [
    ['rect', { x: 2, y: 6, width: 20, height: 12, rx: 2 }],
    ['circle', { cx: 12, cy: 12, r: 3 }],
    ['path', { d: 'M6 12h.01M18 12h.01' }],
  ],
  messageWarning: [
    ['path', { d: 'M21 15a4 4 0 0 1-4 4H7l-4 4V5a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z' }],
    ['path', { d: 'M12 7v4M12 15h.01' }],
  ],
  star: [
    ['path', { d: 'M12 2 15.1 8.3 22 9.3l-5 4.9 1.2 6.8L12 17.8 5.8 21 7 14.2 2 9.3l6.9-1L12 2Z' }],
  ],
  bell: [
    ['path', { d: 'M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8' }],
    ['path', { d: 'M13.73 21a2 2 0 0 1-3.46 0' }],
  ],
  settings: [
    ['path', { d: 'M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.52a2 2 0 0 1-1 1.72l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.72v-.52a2 2 0 0 1 1-1.72l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2Z' }],
    ['circle', { cx: 12, cy: 12, r: 3 }],
  ],
  eye: [
    ['path', { d: 'M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z' }],
    ['circle', { cx: 12, cy: 12, r: 3 }],
  ],
  pencil: [
    ['path', { d: 'M17 3a2.85 2.85 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z' }],
    ['path', { d: 'm15 5 4 4' }],
  ],
  trash: [
    ['path', { d: 'M3 6h18' }],
    ['path', { d: 'M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2' }],
    ['path', { d: 'M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6' }],
    ['path', { d: 'M10 11v6M14 11v6' }],
  ],
  copy: [
    ['rect', { x: 9, y: 9, width: 13, height: 13, rx: 2 }],
    ['path', { d: 'M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1' }],
  ],
  rocket: [
    ['path', { d: 'M4.5 16.5c-1.5 1.3-2 3.2-2 5 1.8 0 3.7-.5 5-2' }],
    ['path', { d: 'M9 15 15 9' }],
    ['path', { d: 'M15 2c3 0 5 0 7 1-1 2-1 4-1 7L11 20l-7-7Z' }],
    ['circle', { cx: 16, cy: 8, r: 2 }],
  ],
  archive: [
    ['rect', { x: 3, y: 3, width: 18, height: 4, rx: 1 }],
    ['path', { d: 'M5 7v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7' }],
    ['path', { d: 'M10 12h4' }],
  ],
  history: [
    ['path', { d: 'M3 12a9 9 0 1 0 3-6.7' }],
    ['path', { d: 'M3 3v6h6' }],
    ['path', { d: 'M12 7v5l3 2' }],
  ],
  search: [
    ['circle', { cx: 11, cy: 11, r: 8 }],
    ['path', { d: 'm21 21-4.3-4.3' }],
  ],
  filter: [
    ['path', { d: 'M3 4h18l-7 8v6l-4 2v-8Z' }],
  ],
  refresh: [
    ['path', { d: 'M21 12a9 9 0 0 1-15.5 6.2' }],
    ['path', { d: 'M3 12A9 9 0 0 1 18.5 5.8' }],
    ['path', { d: 'M3 20v-6h6M21 4v6h-6' }],
  ],
  plus: [
    ['path', { d: 'M12 5v14M5 12h14' }],
  ],
  x: [
    ['path', { d: 'M18 6 6 18M6 6l12 12' }],
  ],
  check: [
    ['path', { d: 'm20 6-11 11-5-5' }],
  ],
  chevronUp: [
    ['path', { d: 'm18 15-6-6-6 6' }],
  ],
  chevronDown: [
    ['path', { d: 'm6 9 6 6 6-6' }],
  ],
  chevronRight: [
    ['path', { d: 'm9 18 6-6-6-6' }],
  ],
  chevronLeft: [
    ['path', { d: 'm15 18-6-6 6-6' }],
  ],
  lock: [
    ['rect', { x: 3, y: 11, width: 18, height: 11, rx: 2 }],
    ['path', { d: 'M7 11V7a5 5 0 0 1 10 0v4' }],
  ],
  unlock: [
    ['rect', { x: 3, y: 11, width: 18, height: 11, rx: 2 }],
    ['path', { d: 'M7 11V7a5 5 0 0 1 9.9-1' }],
  ],
  alert: [
    ['path', { d: 'm21.7 18-8-14a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3Z' }],
    ['path', { d: 'M12 9v4M12 17h.01' }],
  ],
  circleCheck: [
    ['circle', { cx: 12, cy: 12, r: 10 }],
    ['path', { d: 'm9 12 2 2 4-4' }],
  ],
  circleX: [
    ['circle', { cx: 12, cy: 12, r: 10 }],
    ['path', { d: 'm15 9-6 6M9 9l6 6' }],
  ],
  clock: [
    ['circle', { cx: 12, cy: 12, r: 10 }],
    ['path', { d: 'M12 6v6l4 2' }],
  ],
  power: [
    ['path', { d: 'M18.36 6.64a9 9 0 1 1-12.73 0' }],
    ['line', { x1: 12, y1: 2, x2: 12, y2: 12 }],
  ],
  'arrow-left': [
    ['path', { d: 'm12 19-7-7 7-7' }],
    ['path', { d: 'M19 12H5' }],
  ],
  'pause-circle': [
    ['circle', { cx: 12, cy: 12, r: 10 }],
    ['line', { x1: 10, y1: 15, x2: 10, y2: 9 }],
    ['line', { x1: 14, y1: 15, x2: 14, y2: 9 }],
  ],
  tag: [
    ['path', { d: 'M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z' }],
    ['circle', { cx: 7.5, cy: 7.5, r: 1.5 }],
  ],
  rotateCcw: [
    ['path', { d: 'M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8' }],
    ['path', { d: 'M3 3v5h5' }],
  ],
  eyeOff: [
    ['path', { d: 'M9.88 9.88a3 3 0 1 0 4.24 4.24' }],
    ['path', { d: 'M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68' }],
    ['path', { d: 'M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61' }],
    ['line', { x1: 2, y1: 2, x2: 22, y2: 22 }],
  ],
};

export const ICON_ALIASES = {
  policy: 'fileText',
  roles: 'shieldCheck',
  role: 'shieldCheck',
  court: 'layers',
  venue: 'building',
  finance: 'banknote',
  payment: 'creditCard',
  receipt: 'creditCard',
  notification: 'bell',
  warning: 'alert',
  publish: 'rocket',
  edit: 'pencil',
  delete: 'trash',
  view: 'eye',
  clone: 'copy',
  stop: 'power',
  back: 'arrow-left',
  arrowLeft: 'arrow-left',
};

export const NAV_ICON_MAP = {
  dashboard: 'dashboard',
  users: 'users',
  roles: 'shieldCheck',
  policies: 'fileText',
  courtTypes: 'layers',
  banners: 'image',
  venue: 'building',
  booking: 'calendar',
  finance: 'banknote',
  moderation: 'messageWarning',
  audit: 'history',
  settings: 'settings',
};

export const ACTION_ICON_MAP = {
  view: 'eye',
  detail: 'eye',
  edit: 'pencil',
  delete: 'trash',
  clone: 'copy',
  publish: 'rocket',
  archive: 'archive',
  history: 'history',
  permissions: 'shieldCheck',
  users: 'users',
  filter: 'filter',
  refresh: 'refresh',
  create: 'plus',
  close: 'x',
  save: 'check',
  lock: 'lock',
  unlock: 'unlock',
};

export const STATUS_ICON_MAP = {
  active: 'circleCheck',
  draft: 'pencil',
  inactive: 'clock',
  archived: 'archive',
  pending: 'clock',
  approved: 'circleCheck',
  rejected: 'circleX',
  locked: 'lock',
};

export function resolveIconName(name) {
  return ICON_ALIASES[name] || name || 'alert';
}

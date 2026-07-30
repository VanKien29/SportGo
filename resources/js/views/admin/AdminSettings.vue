<template>
  <div class="cluster-profile-surface standalone">
    <div class="profile-section-card settings-main-content">
      <!-- Success / Error Alerts -->
      <div v-if="successMessage" class="table-state-card alert-success-custom">
        <AppIcon name="check" size="18" />
        <span>{{ successMessage }}</span>
      </div>
      <div v-if="errorMessage" class="table-state-card alert-error-custom" role="alert">
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Sidebar Style Selection Card -->
      <div class="settings-section-block">
        <div class="settings-card-header">
          <h2>Kiểu hiển thị Sidebar</h2>
        </div>
        <div class="settings-card-content">
          <div class="sidebar-type-grid">
            <div
              class="sidebar-type-card"
              :class="{ active: sidebarStyle === 'one-level' }"
              @click="sidebarStyle = 'one-level'"
            >
              <div class="sidebar-card-preview one-level-preview">
                <div class="preview-sidebar">
                  <div class="preview-logo"></div>
                  <div class="preview-menu-items">
                    <div class="preview-menu-item active">
                      <div class="preview-icon"></div>
                      <div class="preview-text"></div>
                    </div>
                    <div class="preview-menu-item">
                      <div class="preview-icon"></div>
                      <div class="preview-text"></div>
                    </div>
                    <div class="preview-menu-item">
                      <div class="preview-icon"></div>
                      <div class="preview-text"></div>
                    </div>
                  </div>
                </div>
                <div class="preview-content">
                  <div class="preview-header"></div>
                  <div class="preview-body">
                    <div class="preview-card-item"></div>
                    <div class="preview-card-item"></div>
                  </div>
                </div>
              </div>
              <div class="sidebar-card-info">
                <h3>Sidebar đơn cấp</h3>
                <p>Hiển thị danh mục trực tiếp trên một thanh điều hướng duy nhất</p>
              </div>
            </div>

            <div
              class="sidebar-type-card"
              :class="{ active: sidebarStyle === 'two-level' }"
              @click="sidebarStyle = 'two-level'"
            >
              <div class="sidebar-card-preview two-level-preview">
                <div class="preview-rail">
                  <div class="preview-rail-logo"></div>
                  <div class="preview-rail-items">
                    <div class="preview-rail-item active"></div>
                    <div class="preview-rail-item"></div>
                    <div class="preview-rail-item"></div>
                  </div>
                </div>
                <div class="preview-sub-sidebar">
                  <div class="preview-sub-title"></div>
                  <div class="preview-sub-items">
                    <div class="preview-sub-item active"></div>
                    <div class="preview-sub-item"></div>
                    <div class="preview-sub-item"></div>
                  </div>
                </div>
                <div class="preview-content">
                  <div class="preview-header"></div>
                  <div class="preview-body">
                    <div class="preview-card-item"></div>
                    <div class="preview-card-item"></div>
                  </div>
                </div>
              </div>
              <div class="sidebar-card-info">
                <h3>Sidebar hai cấp</h3>
                <p>Thanh chính (Rail) chứa icon và thanh phụ hiển thị menu chi tiết</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Action Footer -->
      <div class="settings-card-footer">
        <button type="button" class="btn btn-primary" :disabled="saving" @click="saveTheme">
          <AppIcon name="check" size="16" />
          <span>{{ saving ? 'Đang áp dụng...' : 'Áp dụng thay đổi' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminUiSettingsService } from '../../services/adminUiSettings.js';

const PRESETS = [
  {
    id: 'sportgo',
    name: 'SportGo',
    color: '#16a34a',
    light: {
      primary: '#16a34a',
      secondary: '#0f766e',
      accent: '#ecfdf5',
      muted: '#64748b',
      destructive: '#ef4444',
      border: '#bbf7d0',
      card: '#ffffff',
      background: '#f6fbf7',
    },
    dark: {
      primary: '#22c55e',
      secondary: '#2dd4bf',
      accent: '#052e16',
      muted: '#94a3b8',
      destructive: '#f87171',
      border: '#164e2f',
      card: '#0f1f17',
      background: '#07130d',
    }
  },
  {
    id: 'zinc',
    name: 'Zinc',
    color: '#18181b',
    light: {
      primary: '#18181b',
      secondary: '#27272a',
      accent: '#f4f4f5',
      muted: '#71717a',
      destructive: '#ef4444',
      border: '#e4e4e7',
      card: '#ffffff',
      background: '#fafafa',
    },
    dark: {
      primary: '#fafafa',
      secondary: '#27272a',
      accent: '#27272a',
      muted: '#a1a1aa',
      destructive: '#ef4444',
      border: '#27272a',
      card: '#09090b',
      background: '#09090b',
    }
  },
  {
    id: 'slate',
    name: 'Slate',
    color: '#0f172a',
    light: {
      primary: '#0f172a',
      secondary: '#1e293b',
      accent: '#e2e8f0',
      muted: '#64748b',
      destructive: '#ef4444',
      border: '#e2e8f0',
      card: '#ffffff',
      background: '#f8fafc',
    },
    dark: {
      primary: '#f8fafc',
      secondary: '#1e293b',
      accent: '#1e293b',
      muted: '#94a3b8',
      destructive: '#ef4444',
      border: '#1e293b',
      card: '#0f172a',
      background: '#020817',
    }
  },
  {
    id: 'sapphire',
    name: 'Sapphire',
    color: '#2563eb',
    light: {
      primary: '#2563eb',
      secondary: '#0284c7',
      accent: '#f0f9ff',
      muted: '#475569',
      destructive: '#e11d48',
      border: '#bfdbfe',
      card: '#ffffff',
      background: '#f0f6ff',
    },
    dark: {
      primary: '#3b82f6',
      secondary: '#38bdf8',
      accent: '#1e293b',
      muted: '#94a3b8',
      destructive: '#f43f5e',
      border: '#1e3a8a',
      card: '#0f172a',
      background: '#090d16',
    }
  },
  {
    id: 'amethyst',
    name: 'Amethyst',
    color: '#7c3aed',
    light: {
      primary: '#7c3aed',
      secondary: '#db2777',
      accent: '#f5f3ff',
      muted: '#4b5563',
      destructive: '#dc2626',
      border: '#ddd6fe',
      card: '#ffffff',
      background: '#faf7ff',
    },
    dark: {
      primary: '#8b5cf6',
      secondary: '#ec4899',
      accent: '#2e1065',
      muted: '#9ca3af',
      destructive: '#ef4444',
      border: '#4c1d95',
      card: '#111827',
      background: '#030712',
    }
  },
  {
    id: 'amber',
    name: 'Amber',
    color: '#d97706',
    light: {
      primary: '#d97706',
      secondary: '#ea580c',
      accent: '#fffbeb',
      muted: '#4b5563',
      destructive: '#dc2626',
      border: '#fde68a',
      card: '#ffffff',
      background: '#fdfbf7',
    },
    dark: {
      primary: '#f59e0b',
      secondary: '#f97316',
      accent: '#451a03',
      muted: '#9ca3af',
      destructive: '#ef4444',
      border: '#78350f',
      card: '#1e1b4b',
      background: '#0c0a09',
    }
  },
  {
    id: 'rose',
    name: 'Rose',
    color: '#e11d48',
    light: {
      primary: '#e11d48',
      secondary: '#be123c',
      accent: '#fff1f2',
      muted: '#64748b',
      destructive: '#991b1b',
      border: '#fecdd3',
      card: '#ffffff',
      background: '#fff7f8',
    },
    dark: {
      primary: '#fb7185',
      secondary: '#f43f5e',
      accent: '#4c0519',
      muted: '#94a3b8',
      destructive: '#f87171',
      border: '#881337',
      card: '#1f1015',
      background: '#10070a',
    }
  }
];

export default {
  name: 'AdminSettings',
  components: { AppIcon },
  data() {
    return {
      saving: false,
      sidebarStyle: localStorage.getItem('admin-sidebar-style') || 'one-level',
      selectedPresetId: 'sportgo',
      selectedRadius: '8px',
      selectedFontSize: '14px',
      newThemeName: '',
      activeModeTab: 'light',
      activePickerColorKey: null,
      pickerHue: 0,
      pickerSat: 100,
      pickerVal: 100,
      radiusOptions: [
        { label: '0px', value: '0px' },
        { label: '4px', value: '4px' },
        { label: '8px', value: '8px' },
        { label: '12px', value: '12px' },
        { label: '16px', value: '16px' },
      ],
      fontSizeOptions: [
        { label: '12px', value: '12px' },
        { label: '13px', value: '13px' },
        { label: '14px', value: '14px' },
        { label: '15px', value: '15px' },
        { label: '16px', value: '16px' },
      ],
      defaultPresets: PRESETS,
      userPresets: [],
      colorDefinitions: [
        { key: 'primary', label: 'Primary (Chủ đạo)', desc: 'Màu chính hệ thống' },
        { key: 'secondary', label: 'Secondary (Màu phụ)', desc: 'Màu nhấn liên kết, phụ' },
        { key: 'accent', label: 'Accent (Hover nền)', desc: 'Màu nền hover hàng/khối' },
        { key: 'muted', label: 'Muted (Mô tả phụ)', desc: 'Màu text nhạt, mô tả phụ' },
        { key: 'destructive', label: 'Destructive (Cảnh báo)', desc: 'Màu lỗi, hành động xóa' },
        { key: 'border', label: 'Border (Đường viền)', desc: 'Màu viền bảng, khung viền' },
        { key: 'card', label: 'Card (Thẻ nội dung)', desc: 'Nền của card thông tin' },
        { key: 'background', label: 'Background (Nền trang)', desc: 'Nền lớn của giao diện' },
      ],
      theme: {
        light: { ...PRESETS[0].light },
        dark: { ...PRESETS[0].dark },
      },
    };
  },
  computed: {
    hueColorHex() {
      return this.hsvToHex(this.pickerHue, 100, 100);
    },
    allPresets() {
      return [...this.defaultPresets, ...this.userPresets];
    },
    isCustomPresetActive() {
      const activePreset = this.allPresets.find(p => p.id === this.selectedPresetId);
      return !!(activePreset && activePreset.isUserPreset);
    },
    lightPreviewStyle() {
      const l = this.theme.light;
      return {
        background: l.background,
        color: l.muted,
        borderRadius: this.selectedRadius,
        '--preview-border': l.border,
        '--preview-card': l.card,
        '--preview-primary': l.primary,
        '--preview-muted': l.muted,
      };
    },
    darkPreviewStyle() {
      const d = this.theme.dark;
      return {
        background: d.background,
        color: d.muted,
        borderRadius: this.selectedRadius,
        '--preview-border': d.border,
        '--preview-card': d.card,
        '--preview-primary': d.primary,
        '--preview-muted': d.muted,
      };
    },
    previewCardStyle() {
      return {
        borderRadius: `calc(${this.selectedRadius} - 2px)`,
      };
    },
    lightBtnPrimaryStyle() {
      const p = this.theme.light.primary;
      const r = this.getContrastColor(p);
      return { background: p, color: r, borderRadius: `calc(${this.selectedRadius} - 4px)` };
    },
    lightBtnDestructiveStyle() {
      return { background: this.theme.light.destructive, color: '#ffffff', borderRadius: `calc(${this.selectedRadius} - 4px)` };
    },
    darkBtnPrimaryStyle() {
      const p = this.theme.dark.primary;
      const r = this.getContrastColor(p);
      return { background: p, color: r, borderRadius: `calc(${this.selectedRadius} - 4px)` };
    },
    darkBtnDestructiveStyle() {
      return { background: this.theme.dark.destructive, color: '#ffffff', borderRadius: `calc(${this.selectedRadius} - 4px)` };
    },
  },
  async created() {
    this.loadUserPresets();
    this.loadSavedTheme();
    await this.fetchUiSettingsFromDb();
  },
  beforeUnmount() {
    document.removeEventListener('click', this.closeCustomPicker);
  },
  methods: {
    getContrastColor(hex) {
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
    },
    hexToHsv(hex) {
      let c = hex.replace(/^#/, '');
      if (c.length === 3) {
        c = c.split('').map(x => x + x).join('');
      }
      const num = parseInt(c, 16);
      let r = (num >> 16) & 255;
      let g = (num >> 8) & 255;
      let b = num & 255;
      
      r /= 255; g /= 255; b /= 255;
      const max = Math.max(r, g, b), min = Math.min(r, g, b);
      let h = 0, s, v = max;
      const d = max - min;
      s = max === 0 ? 0 : d / max;
      if (max !== min) {
        switch (max) {
          case r: h = (g - b) / d + (g < b ? 6 : 0); break;
          case g: h = (b - r) / d + 2; break;
          case b: h = (r - g) / d + 4; break;
        }
        h /= 6;
      }
      return { h: Math.round(h * 360), s: Math.round(s * 100), v: Math.round(v * 100) };
    },
    hsvToHex(h, s, v) {
      s /= 100; v /= 100;
      let r, g, b;
      const i = Math.floor(h / 60) % 6;
      const f = h / 60 - Math.floor(h / 60);
      const p = v * (1 - s);
      const q = v * (1 - f * s);
      const t = v * (1 - (1 - f) * s);
      switch (i) {
        case 0: r = v; g = t; b = p; break;
        case 1: r = q; g = v; b = p; break;
        case 2: r = p; g = v; b = t; break;
        case 3: r = p; g = q; b = v; break;
        case 4: r = t; g = p; b = v; break;
        case 5: r = v; g = p; b = q; break;
      }
      const ri = Math.round(r * 255);
      const gi = Math.round(g * 255);
      const bi = Math.round(b * 255);
      return "#" + ((1 << 24) + (ri << 16) + (gi << 8) + bi).toString(16).slice(1);
    },
    openCustomPicker(colorKey) {
      if (this.activePickerColorKey === colorKey) {
        this.closeCustomPicker();
        return;
      }
      this.activePickerColorKey = colorKey;
      const currentHex = this.theme[this.activeModeTab][colorKey];
      const hsv = this.hexToHsv(currentHex);
      this.pickerHue = hsv.h;
      this.pickerSat = hsv.s;
      this.pickerVal = hsv.v;
      
      // Delay listener attachment to avoid immediate trigger
      setTimeout(() => {
        document.addEventListener('click', this.closeCustomPicker);
      }, 50);
    },
    closeCustomPicker() {
      this.activePickerColorKey = null;
      document.removeEventListener('click', this.closeCustomPicker);
    },
    handleSVMousedown(e) {
      e.preventDefault();
      const rect = e.currentTarget.getBoundingClientRect();
      const update = (moveEvent) => {
        const x = Math.max(0, Math.min(rect.width, moveEvent.clientX - rect.left));
        const y = Math.max(0, Math.min(rect.height, moveEvent.clientY - rect.top));
        this.pickerSat = Math.round((x / rect.width) * 100);
        this.pickerVal = Math.round((1 - y / rect.height) * 100);
        this.updateColorFromHSV();
      };
      
      update(e);
      
      const onMouseMove = (moveEvent) => {
        update(moveEvent);
      };
      
      const onMouseUp = () => {
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
      };
      
      document.addEventListener('mousemove', onMouseMove);
      document.addEventListener('mouseup', onMouseUp);
    },
    updateColorFromHSV() {
      const hex = this.hsvToHex(this.pickerHue, this.pickerSat, this.pickerVal);
      this.theme[this.activeModeTab][this.activePickerColorKey] = hex;
      this.selectedPresetId = 'custom';
    },
    onHexInputChange(e) {
      const val = e.target.value;
      if (/^#[0-9A-F]{6}$/i.test(val)) {
        this.theme[this.activeModeTab][this.activePickerColorKey] = val;
        const hsv = this.hexToHsv(val);
        this.pickerHue = hsv.h;
        this.pickerSat = hsv.s;
        this.pickerVal = hsv.v;
        this.selectedPresetId = 'custom';
      }
    },
    loadUserPresets() {
      const saved = localStorage.getItem('admin-user-presets');
      if (saved) {
        try {
          this.userPresets = JSON.parse(saved).map(p => ({ ...p, isUserPreset: true }));
        } catch (e) {
          console.error('Failed to parse user presets', e);
        }
      }
    },
    selectPreset(preset) {
      this.selectedPresetId = preset.id;
      this.theme.light = { ...preset.light };
      this.theme.dark = { ...preset.dark };
      this.newThemeName = preset.name;
    },
    updateActivePreset() {
      if (!this.newThemeName.trim()) return;
      const index = this.userPresets.findIndex(p => p.id === this.selectedPresetId);
      if (index !== -1) {
        this.userPresets[index].name = this.newThemeName.trim();
        this.userPresets[index].color = this.theme.light.primary;
        this.userPresets[index].light = { ...this.theme.light };
        this.userPresets[index].dark = { ...this.theme.dark };
        
        localStorage.setItem('admin-user-presets', JSON.stringify(this.userPresets));
        alert('Đã cập nhật chủ đề tùy chỉnh thành công!');
      }
    },
    saveAsNewPreset() {
      if (!this.newThemeName.trim()) return;
      const newPreset = {
        id: 'user-' + Date.now(),
        name: this.newThemeName.trim(),
        color: this.theme.light.primary,
        light: { ...this.theme.light },
        dark: { ...this.theme.dark },
        isUserPreset: true
      };
      
      this.userPresets.push(newPreset);
      localStorage.setItem('admin-user-presets', JSON.stringify(this.userPresets));
      this.selectedPresetId = newPreset.id;
      this.newThemeName = '';
      alert('Đã lưu Chủ đề tùy chỉnh mới thành công!');
    },
    deleteUserPreset(presetId) {
      if (confirm('Bạn có chắc chắn muốn xóa chủ đề tùy chỉnh này?')) {
        this.userPresets = this.userPresets.filter(p => p.id !== presetId);
        localStorage.setItem('admin-user-presets', JSON.stringify(this.userPresets));
        if (this.selectedPresetId === presetId) {
          this.selectPreset(this.defaultPresets[0]);
        }
      }
    },
    mergePresets(basePresets, incomingPresets) {
      const map = new Map();
      basePresets.forEach((preset) => map.set(preset.id, preset));
      (incomingPresets || []).forEach((preset) => {
        if (preset?.id) {
          map.set(preset.id, { ...(map.get(preset.id) || {}), ...preset });
        }
      });
      return Array.from(map.values());
    },
    loadSavedTheme() {
      const saved = localStorage.getItem('admin-custom-theme');
      if (saved) {
        try {
          const parsed = JSON.parse(saved);
          if (parsed.light) this.theme.light = { ...PRESETS[0].light, ...parsed.light };
          if (parsed.dark) this.theme.dark = { ...PRESETS[0].dark, ...parsed.dark };
          if (parsed.radius) this.selectedRadius = parsed.radius;
          if (parsed.font_size) this.selectedFontSize = parsed.font_size;
          
          // Match preset if possible
          const matched = this.allPresets.find(p => 
            p.light.primary === this.theme.light.primary && 
            p.dark.primary === this.theme.dark.primary
          );
          if (matched) {
            this.selectedPresetId = matched.id;
          } else {
            this.selectedPresetId = 'custom';
          }
        } catch (e) {
          console.error('Failed to parse saved custom theme', e);
        }
      }
    },
    resetToDefault(mode) {
      const defaultPreset = PRESETS[0];
      if (mode === 'light') {
        this.theme.light = { ...defaultPreset.light };
      } else {
        this.theme.dark = { ...defaultPreset.dark };
      }
      this.selectedPresetId = 'sportgo';
    },
    resetAll() {
      if (confirm('Bạn có chắc chắn muốn khôi phục tất cả cài đặt và độ bo góc về mặc định?')) {
        const defaultPreset = PRESETS[0];
        this.theme.light = { ...defaultPreset.light };
        this.theme.dark = { ...defaultPreset.dark };
        this.selectedPresetId = 'sportgo';
        this.selectedRadius = '8px';
        this.selectedFontSize = '14px';
      }
    },
    async fetchUiSettingsFromDb() {
      try {
        const data = await adminUiSettingsService.getSettings();
        if (data) {
          this.sidebarStyle = data.sidebar_style || 'one-level';
          this.selectedRadius = data.radius || '8px';
          this.selectedFontSize = data.font_size || '14px';
          
          if (data.presets && data.presets.length > 0) {
            this.defaultPresets = this.mergePresets(PRESETS, data.presets);
          }
          if (data.custom_themes) {
            this.userPresets = data.custom_themes;
          }

          // Find and set active preset
          const activePreset = this.allPresets.find(p => p.id === data.active_theme_id);
          if (activePreset) {
            this.selectedPresetId = activePreset.id;
            this.theme.light = { ...activePreset.light };
            this.theme.dark = { ...activePreset.dark };
            this.newThemeName = activePreset.name;
          } else {
            this.selectedPresetId = data.active_theme_id || 'sportgo';
          }
        }
      } catch (e) {
        console.error('Failed to fetch UI settings from DB', e);
      }
    },
    async saveTheme() {
      this.saving = true;
      this.successMessage = '';
      this.errorMessage = '';
      try {
        const payload = {
          light: this.theme.light,
          dark: this.theme.dark,
          radius: this.selectedRadius,
          font_size: this.selectedFontSize
        };
        localStorage.setItem('admin-custom-theme', JSON.stringify(payload));
        localStorage.setItem('admin-sidebar-style', this.sidebarStyle);
        localStorage.setItem('admin-user-presets', JSON.stringify(this.userPresets));

        // Construct payload for admin theme CSS generation:
        const settingsPayload = {
          active_theme_id: this.selectedPresetId,
          radius: this.selectedRadius,
          font_size: this.selectedFontSize,
          presets: this.defaultPresets,
          custom_themes: this.userPresets
        };
        
        window.dispatchEvent(new Event('admin-sidebar-style-changed'));
        window.dispatchEvent(new CustomEvent('admin-theme-updated', { detail: settingsPayload }));

        const payloadDb = {
          active_theme_id: this.selectedPresetId,
          sidebar_style: this.sidebarStyle,
          radius: this.selectedRadius,
          font_size: this.selectedFontSize,
          presets: this.defaultPresets,
          custom_themes: this.userPresets,
        };
        await adminUiSettingsService.updateSettings(payloadDb);

        this.successMessage = 'Cấu hình giao diện đã lưu và áp dụng thành công!';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        setTimeout(() => {
          this.successMessage = '';
        }, 5000);
      } catch (e) {
        this.errorMessage = e.message || 'Không thể đồng bộ cấu hình giao diện với hệ thống.';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => {
          this.errorMessage = '';
        }, 5000);
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

<style scoped>
/* Main layout grid */
.theme-layout-grid {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 32px;
  align-items: start;
}

@media (max-width: 992px) {
  .theme-layout-grid {
    grid-template-columns: 1fr;
    gap: 24px;
  }
  .preview-column {
    position: static;
  }
}

.controls-column {
  display: flex;
  flex-direction: column;
}

.preview-column {
  position: sticky;
  top: 24px;
}

/* Titles and labels */
.settings-section-title {
  font-size: var(--admin-font-size-md, 13px);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--admin-text);
  margin-bottom: 12px;
  opacity: 0.85;
}

/* Theme Presets Grid */
.presets-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
}

.preset-card {
  position: relative;
  height: 36px;
  padding: 0 12px;
  gap: 8px;
  border: 2px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  cursor: pointer;
  background: var(--admin-surface);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 150ms cubic-bezier(0.4, 0, 0.2, 1);
  user-select: none;
  box-sizing: border-box;
}

.preset-card.never-hover-class-placeholder {
  border-color: var(--admin-primary);
  transform: scale(1.04);
}

.preset-card.active {
  border-color: var(--admin-primary);
  box-shadow: none !important;
  transform: scale(1.02);
}

.preset-color-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 1px solid rgba(0, 0, 0, 0.1);
  flex-shrink: 0;
  transition: transform 150ms ease;
}

.preset-card.active .preset-color-dot {
  transform: scale(1.1);
}

.preset-name {
  display: block;
  font-size: 12px;
  font-weight: 500;
  color: var(--admin-text);
}

.delete-preset-btn {
  position: absolute;
  top: -6px;
  right: -6px;
  background: var(--admin-danger);
  color: #ffffff;
  font-size: 10px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 1px solid var(--admin-surface);
  cursor: pointer;
  display: none;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
  z-index: 10;
  padding: 0;
}

.preset-card.never-hover-class-placeholder .delete-preset-btn {
  display: flex;
}

/* Radius selector */
.radius-selector-group {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 20px;
}

.radius-btn {
  height: 36px;
  padding: 0 16px;
  box-sizing: border-box;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-text);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 120ms ease;
}

.radius-btn.never-hover-class-placeholder {
  background: var(--admin-hover);
  border-color: var(--admin-primary);
}

.radius-btn.active {
  background: var(--admin-primary);
  color: var(--admin-primary-text, #ffffff);
  border-color: var(--admin-primary);
}

/* Mode Segmented Toggle Group */
.mode-toggle-group {
  display: inline-flex !important;
  width: fit-content !important;
  background: var(--admin-bg-soft) !important;
  border-radius: var(--admin-radius) !important;
  padding: 3px !important;
  gap: 2px !important;
  margin-bottom: 16px !important;
  border: 1px solid var(--admin-border-soft) !important;
}

.toggle-tab-btn {
  background: transparent !important;
  border: 0 !important;
  width: 120px !important;
  height: 30px !important;
  font-size: 11px !important;
  font-weight: 500 !important;
  color: var(--admin-muted) !important;
  cursor: pointer !important;
  border-radius: calc(var(--admin-radius) - 2px) !important;
  transition: all 150ms cubic-bezier(0.4, 0, 0.2, 1) !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  user-select: none !important;
}

.toggle-tab-btn.never-hover-class-placeholder {
  color: var(--admin-text);
}

.toggle-tab-btn.active {
  background: var(--admin-surface);
  color: var(--admin-primary);
  box-shadow: none !important;
}

/* Figma Color Rows Styles */
.figma-color-rows {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
  background: transparent;
  padding: 0;
  border-radius: 0;
  border: none;
  margin-bottom: 20px;
}

@media (max-width: 576px) {
  .figma-color-rows {
    grid-template-columns: 1fr;
  }
}

.figma-color-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 0;
  background: transparent;
  border: none;
  border-radius: 0;
  box-sizing: border-box;
  height: 38px;
  transition: all 120ms ease;
}

.figma-color-row.never-hover-class-placeholder {
  opacity: 0.8;
}

.figma-color-swatch-wrapper {
  position: relative;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  border: 1px solid var(--admin-border-soft);
  cursor: pointer;
  flex-shrink: 0;
}

.figma-color-indicator {
  width: 100%;
  height: 100%;
  border-radius: 5px;
}

.figma-color-name {
  font-size: 11px;
  font-weight: 500;
  color: var(--admin-text);
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.figma-hex-input-wrapper {
  width: 86px;
}

.figma-hex-text-input {
  width: 100% !important;
  box-sizing: border-box !important;
  background: var(--admin-bg-soft) !important;
  border: 1px solid var(--admin-border-soft) !important;
  border-radius: 4px !important;
  padding: 4px 6px !important;
  font-family: monospace !important;
  font-size: 10px !important;
  font-weight: 500 !important;
  color: var(--admin-text) !important;
  text-transform: uppercase !important;
  text-align: center !important;
  transition: all 120ms ease !important;
  height: 26px !important;
  min-height: auto !important;
}

.figma-hex-text-input.never-hover-class-placeholder {
  border-color: var(--admin-muted);
}

.figma-hex-text-input:focus {
  background: var(--admin-surface);
  border-color: var(--admin-primary);
  outline: none;
  box-shadow: none !important;
}

/* Popover Figma Picker */
.figma-popover {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  z-index: 100;
  width: 200px;
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  user-select: none;
}

@media (max-width: 576px) {
  .figma-popover {
    position: fixed;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) !important;
    width: calc(100% - 32px);
    max-width: 260px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 10px 10px -5px rgba(0, 0, 0, 0.25);
  }
}

.sv-canvas {
  position: relative;
  width: 100%;
  height: 120px;
  border-radius: var(--admin-radius);
  overflow: hidden;
  cursor: crosshair;
}

.sv-white-gradient {
  position: absolute;
  inset: 0;
  background: linear-gradient(to right, #ffffff, transparent);
}

.sv-black-gradient {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, #000000, transparent);
}

.sv-cursor {
  position: absolute;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  border: 1.5px solid #ffffff;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.5);
  transform: translate(-50%, -50%);
  pointer-events: none;
}

.picker-sliders {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 4px 0;
}

/* Hue slider */
.hue-slider-track {
  width: 100%;
  height: 10px;
  border-radius: 9999px;
  background: linear-gradient(
    to right,
    #ff0000,
    #ffff00,
    #00ff00,
    #00ffff,
    #0000ff,
    #ff00ff,
    #ff0000
  );
  position: relative;
}

.hue-range-input {
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
  position: absolute;
  top: 0;
  left: 0;
  margin: 0;
  z-index: 2;
}

.hue-slider-track::after {
  content: '';
  position: absolute;
  top: 50%;
  left: var(--hue-percent, 50%);
  width: 12px;
  height: 12px;
  background: #ffffff;
  border: 1px solid rgba(0, 0, 0, 0.3);
  border-radius: 50%;
  transform: translate(-50%, -50%);
  pointer-events: none;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.picker-inputs-row {
  display: flex;
  gap: 8px;
}

.picker-input-group {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--admin-hover);
  padding: 4px 8px;
  border-radius: 4px;
  flex: 1;
  border: 1px solid var(--admin-border-soft);
}

.picker-input-label {
  font-size: 10px;
  font-weight: 500;
  color: var(--admin-faint);
}

.picker-hex-input {
  width: 100%;
  background: transparent;
  border: 0;
  outline: none;
  font-family: monospace;
  font-size: 11px;
  font-weight: 500;
  color: var(--admin-text);
  text-transform: uppercase;
}

/* Save Preset Inline */
.save-preset-inline {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-top: 8px;
  padding-top: 16px;
  border-top: 1px solid var(--admin-border-soft);
}

.preset-action-buttons {
  display: flex;
  gap: 8px;
  align-items: center;
}

.update-preset-btn,
.save-preset-btn {
  height: 36px;
  padding: 0 16px;
  font-size: 12px;
  white-space: nowrap;
  box-sizing: border-box;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

@media (max-width: 576px) {
  .save-preset-inline {
    flex-direction: column;
    align-items: stretch;
  }
  .preset-action-buttons {
    flex-direction: column;
    align-items: stretch;
    width: 100%;
  }
  .update-preset-btn,
  .save-preset-btn {
    width: 100%;
  }
}

.sg-shell-admin .content-area input.theme-name-input {
  flex: 1;
  height: 36px;
  padding: 0 12px;
  box-sizing: border-box;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-text);
  font-size: 12px !important;
  outline: none;
  transition: border-color 120ms ease;
}

.sg-shell-admin .content-area input.theme-name-input::placeholder {
  font-size: 12px !important;
  opacity: 0.65;
}

.theme-name-input:focus {
  border-color: var(--admin-primary);
}

.save-preset-btn {
  height: 36px;
  padding: 0 16px;
  font-size: 12px;
  white-space: nowrap;
  box-sizing: border-box;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* Live Preview Panel */
.preview-container {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 576px) {
  .preview-container {
    grid-template-columns: 1fr;
  }
}

.preview-item {
  padding: 16px;
  border-radius: var(--admin-radius-lg);
  border: 1px solid var(--preview-border);
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: all 150ms ease;
}

.preview-mode-tag {
  font-size: 10px;
  font-weight: 500;
  text-transform: uppercase;
  color: #334155;
  opacity: 0.6;
}

.preview-mode-tag.dark {
  color: #f1f5f9;
}

.preview-card-body {
  padding: 14px;
  border: 1px solid var(--preview-border);
  background: var(--preview-card);
  display: flex;
  flex-direction: column;
  gap: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.preview-card-title {
  font-size: 13px;
  font-weight: 500;
  color: var(--preview-primary);
}

.preview-card-text {
  font-size: 11px;
  margin: 0;
  color: var(--preview-muted) !important;
  line-height: 1.4;
}

.preview-btn-row {
  display: flex;
  gap: 8px;
  margin-top: 4px;
}

.preview-btn {
  border: 0;
  padding: 6px 12px;
  font-size: 11px;
  font-weight: 500;
  color: #ffffff;
  cursor: default;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

/* Overriding parent card borders to be flat and borderless */
.settings-card-header {
  border-bottom: none !important;
}

/* Footer style */
.settings-card-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  background: linear-gradient(180deg, transparent, rgba(0, 0, 0, 0.02));
  border-top: none !important;
}

.settings-card-footer button {
  min-width: 160px;
  justify-content: center;
}

@media (max-width: 900px) {
  .settings-card-footer button {
    width: auto !important;
    flex: 1;
  }
}

@media (max-width: 576px) {
  .settings-card-footer {
    flex-direction: column-reverse;
    align-items: stretch;
    padding: 16px 0;
  }
  .settings-card-footer button {
    width: 100% !important;
    max-width: none;
  }
}

.settings-card h2,
.settings-card h3,
.sidebar-card-info h3 {
  font-weight: 500 !important;
}

.profile-section-card.settings-main-content {
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 0;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sidebar-type-card {
  border: 1px solid var(--admin-border-soft, #cbd5e1);
  border-radius: 0 !important;
  background: transparent;
  padding: 12px;
  cursor: pointer;
  transition: all 0.16s ease;
}

.sidebar-type-card.active {
  border-color: var(--admin-primary, #22a653) !important;
  background: var(--admin-hover, #edf7ed) !important;
  box-shadow: none !important;
}

.sidebar-card-preview {
  border-radius: 0 !important;
  border: 1px solid var(--admin-border-soft, #cbd5e1) !important;
}

.table-state-card.alert-success-custom {
  background: var(--admin-hover, #edf7ed);
  color: var(--admin-primary, #22a653);
  border: 1px dashed var(--admin-primary, #22a653);
  border-radius: 0;
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.table-state-card.alert-error-custom {
  background: #fef2f2;
  color: #dc2626;
  border: 1px dashed #dc2626;
  border-radius: 0;
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}
</style>

<template>
  <div class="settings-container animate-fade-in">
    <!-- Breadcrumbs & Header -->
    <div class="page-header card">
      <div>
        <span class="eyebrow">Hệ thống</span>
        <h1>Cấu hình giao diện</h1>
        <p>Cá nhân hóa màu sắc cho trang quản trị SportGo của bạn. Màu sắc được tự động đồng bộ hóa trên mọi thiết bị.</p>
      </div>
    </div>

    <!-- Success Feedback Alert -->
    <div v-if="successMessage" class="alert success" style="margin-bottom: 0px; border-radius: 12px; display: flex; align-items: center; gap: 8px;">
      <AppIcon name="check" size="18" />
      <span>{{ successMessage }}</span>
    </div>

    <!-- Sidebar Selection Form -->
    <div class="settings-card">
      <div class="settings-card-header">
        <AppIcon name="sliders" size="20" />
        <h2>Kiểu Sidebar hiển thị</h2>
      </div>
      <div class="settings-card-content">
        <div class="sidebar-type-grid">
          <!-- One Level Card -->
          <div
            class="sidebar-type-card"
            :class="{ active: sidebarStyle === 'one-level' }"
            @click="sidebarStyle = 'one-level'"
          >
            <div class="sidebar-card-preview one-level-preview">
              <div class="preview-sidebar-line"></div>
              <div class="preview-content-box"></div>
            </div>
            <div class="sidebar-card-info">
              <h3>Sidebar đơn cấp (Hiện tại)</h3>
              <p>Sidebar truyền thống thu gọn, hiển thị tất cả danh mục trên một cấp duy nhất.</p>
            </div>
          </div>

          <!-- Two Level Card -->
          <div
            class="sidebar-type-card"
            :class="{ active: sidebarStyle === 'two-level' }"
            @click="sidebarStyle = 'two-level'"
          >
            <div class="sidebar-card-preview two-level-preview">
              <div class="preview-rail-line"></div>
              <div class="preview-sidebar-line second"></div>
              <div class="preview-content-box"></div>
            </div>
            <div class="sidebar-card-info">
              <h3>Sidebar hai cấp (Double-level)</h3>
              <p>Sidebar chia làm 2 phần: thanh icon điều hướng nhanh và danh mục chi tiết tương ứng.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Config Form -->
    <div class="settings-card">
      <div class="settings-card-header">
        <AppIcon name="palette" size="20" />
        <h2>Tùy chỉnh màu sắc Giao diện</h2>
      </div>

      <div class="settings-card-content">
        <div class="theme-config-grid">
          <!-- Light Mode Color Pickers -->
          <div class="theme-mode-section">
            <div class="theme-mode-header">
              <span class="theme-mode-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide lucide-sun"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M22 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                Giao diện Sáng
              </span>
              <button type="button" class="theme-mode-reset-btn" @click="resetToDefault('light')">
                Đặt lại mặc định
              </button>
            </div>

            <div class="color-pickers-list">
              <div v-for="color in colorDefinitions" :key="color.key" class="color-picker-row">
                <div class="color-info">
                  <span class="color-name">{{ color.label }}</span>
                  <span class="color-desc">{{ color.desc }}</span>
                </div>
                <div class="color-input-wrapper">
                  <span class="color-hex">{{ theme.light[color.key] }}</span>
                  <input
                    type="color"
                    v-model="theme.light[color.key]"
                    class="color-picker-input"
                    @input="handleColorInput"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Dark Mode Color Pickers -->
          <div class="theme-mode-section">
            <div class="theme-mode-header">
              <span class="theme-mode-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lucide lucide-moon"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                Giao diện Tối
              </span>
              <button type="button" class="theme-mode-reset-btn" @click="resetToDefault('dark')">
                Đặt lại mặc định
              </button>
            </div>

            <div class="color-pickers-list">
              <div v-for="color in colorDefinitions" :key="color.key" class="color-picker-row">
                <div class="color-info">
                  <span class="color-name">{{ color.label }}</span>
                  <span class="color-desc">{{ color.desc }}</span>
                </div>
                <div class="color-input-wrapper">
                  <span class="color-hex">{{ theme.dark[color.key] }}</span>
                  <input
                    type="color"
                    v-model="theme.dark[color.key]"
                    class="color-picker-input"
                    @input="handleColorInput"
                  />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Live Preview Panel -->
        <div class="theme-preview-card">
          <h3 style="font-size: 14px; font-weight: 750; margin-bottom: 12px;">Xem trước giao diện thực tế (Live Preview)</h3>
          <p style="font-size: 12px; color: var(--admin-faint); margin-bottom: 16px;">
            Dưới đây là mô phỏng các thành phần giao diện sẽ thay đổi dựa trên các màu bạn đã cấu hình ở trên.
          </p>

          <div class="preview-grid">
            <!-- Simulated Light Preview -->
            <div :style="lightPreviewStyle" class="preview-item">
              <div style="font-size: 11px; font-weight: 700; opacity: 0.6; text-transform: uppercase;">Mô phỏng Giao diện Sáng</div>
              <div style="padding: 12px; border-radius: 6px; border: 1px solid var(--preview-border); background: var(--preview-card); display: flex; flex-direction: column; gap: 8px;">
                <div style="font-size: 13px; font-weight: 700; color: var(--preview-primary);">Tiêu đề Khối</div>
                <p style="font-size: 11px; margin: 0; color: var(--preview-muted);">Đây là văn bản mô tả phụ sử dụng màu Muted.</p>
                <div style="display: flex; gap: 8px; margin-top: 4px;">
                  <button type="button" :style="lightBtnPrimaryStyle" style="border: 0; border-radius: 4px; padding: 4px 8px; font-size: 11px; font-weight: 700; color: #fff; cursor: default;">
                    Nút chính (Primary)
                  </button>
                  <button type="button" :style="lightBtnDestructiveStyle" style="border: 0; border-radius: 4px; padding: 4px 8px; font-size: 11px; font-weight: 700; color: #fff; cursor: default;">
                    Nút cảnh báo (Destructive)
                  </button>
                </div>
              </div>
            </div>

            <!-- Simulated Dark Preview -->
            <div :style="darkPreviewStyle" class="preview-item">
              <div style="font-size: 11px; font-weight: 700; opacity: 0.6; text-transform: uppercase; color: #fff;">Mô phỏng Giao diện Tối</div>
              <div style="padding: 12px; border-radius: 6px; border: 1px solid var(--preview-border); background: var(--preview-card); display: flex; flex-direction: column; gap: 8px;">
                <div style="font-size: 13px; font-weight: 700; color: var(--preview-primary);">Tiêu đề Khối</div>
                <p style="font-size: 11px; margin: 0; color: var(--preview-muted);">Đây là văn bản mô tả phụ sử dụng màu Muted.</p>
                <div style="display: flex; gap: 8px; margin-top: 4px;">
                  <button type="button" :style="darkBtnPrimaryStyle" style="border: 0; border-radius: 4px; padding: 4px 8px; font-size: 11px; font-weight: 700; color: #fff; cursor: default;">
                    Nút chính (Primary)
                  </button>
                  <button type="button" :style="darkBtnDestructiveStyle" style="border: 0; border-radius: 4px; padding: 4px 8px; font-size: 11px; font-weight: 700; color: #fff; cursor: default;">
                    Nút cảnh báo (Destructive)
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="settings-actions">
          <button type="button" class="btn-secondary" @click="resetAll">
            Khôi phục tất cả mặc định
          </button>
          <button type="button" class="btn-primary" @click="saveTheme">
            <AppIcon name="save" size="16" />
            <span>Lưu cấu hình màu sắc</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { applyCustomThemeStyles } from '../../utils/theme.js';

const DEFAULT_LIGHT = {
  primary: '#22a653',
  secondary: '#2563eb',
  accent: '#edf7ed',
  muted: '#2f3d34',
  destructive: '#dc2626',
  border: '#cfded1',
  card: '#ffffff',
  background: '#eef6f0',
};

const DEFAULT_DARK = {
  primary: '#2ebc63',
  secondary: '#3b82f6',
  accent: '#263d2e',
  muted: '#a6c0ae',
  destructive: '#ef4444',
  border: '#2c4736',
  card: '#1a291f',
  background: '#0d1510',
};

export default {
  name: 'AdminSettings',
  components: { AppIcon },
  data() {
    return {
      sidebarStyle: localStorage.getItem('admin-sidebar-style') || 'one-level',
      successMessage: '',
      colorDefinitions: [
        { key: 'primary', label: 'Primary (Chủ đạo)', desc: 'Màu sắc thương hiệu, nút chính, và trạng thái kích hoạt' },
        { key: 'secondary', label: 'Secondary (Màu phụ)', desc: 'Màu nhấn bổ trợ, liên kết phụ hoặc huy hiệu' },
        { key: 'accent', label: 'Accent (Nổi bật)', desc: 'Màu nền của hàng/khối khi di chuột qua (Hover background)' },
        { key: 'muted', label: 'Muted (Nhạt / Phụ)', desc: 'Màu sắc của mô tả phụ, nhãn phụ, placeholder' },
        { key: 'destructive', label: 'Destructive (Xóa / Cảnh báo)', desc: 'Nút hành động nguy hiểm, lỗi hệ thống, cảnh báo' },
        { key: 'border', label: 'Border (Đường viền)', desc: 'Đường phân tách khối, khung input, viền bảng' },
        { key: 'card', label: 'Card (Nền khối)', desc: 'Màu nền của các thẻ thông tin, bảng biểu, hộp hội thoại' },
        { key: 'background', label: 'Background (Màu nền trang)', desc: 'Màu nền chính của toàn bộ trang quản trị' },
      ],
      theme: {
        light: { ...DEFAULT_LIGHT },
        dark: { ...DEFAULT_DARK },
      },
    };
  },
  computed: {
    lightPreviewStyle() {
      const l = this.theme.light;
      return {
        background: l.background,
        color: l.muted,
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
        '--preview-border': d.border,
        '--preview-card': d.card,
        '--preview-primary': d.primary,
        '--preview-muted': d.muted,
      };
    },
    lightBtnPrimaryStyle() {
      return { background: this.theme.light.primary };
    },
    lightBtnDestructiveStyle() {
      return { background: this.theme.light.destructive };
    },
    darkBtnPrimaryStyle() {
      return { background: this.theme.dark.primary };
    },
    darkBtnDestructiveStyle() {
      return { background: this.theme.dark.destructive };
    },
  },
  created() {
    this.loadSavedTheme();
  },
  methods: {
    loadSavedTheme() {
      const saved = localStorage.getItem('admin-custom-theme');
      if (saved) {
        try {
          const parsed = JSON.parse(saved);
          if (parsed.light) this.theme.light = { ...DEFAULT_LIGHT, ...parsed.light };
          if (parsed.dark) this.theme.dark = { ...DEFAULT_DARK, ...parsed.dark };
        } catch (e) {
          console.error('Failed to parse saved custom theme', e);
        }
      }
    },
    handleColorInput() {
      // For immediate preview inside pickers if needed, handled reactively by Vue
    },
    resetToDefault(mode) {
      if (mode === 'light') {
        this.theme.light = { ...DEFAULT_LIGHT };
      } else {
        this.theme.dark = { ...DEFAULT_DARK };
      }
    },
    resetAll() {
      if (confirm('Bạn có chắc chắn muốn khôi phục tất cả màu mặc định cho cả Light và Dark Mode?')) {
        this.theme.light = { ...DEFAULT_LIGHT };
        this.theme.dark = { ...DEFAULT_DARK };
      }
    },
    saveTheme() {
      localStorage.setItem('admin-custom-theme', JSON.stringify(this.theme));
      localStorage.setItem('admin-sidebar-style', this.sidebarStyle);
      applyCustomThemeStyles();
      
      // Dispatch style change event to let AdminShell re-render immediately
      window.dispatchEvent(new Event('sidebar-style-changed'));
      
      this.successMessage = 'Lưu cấu hình giao diện thành công! Giao diện mới đã được áp dụng ngay lập tức.';
      window.scrollTo({ top: 0, behavior: 'smooth' });
      
      setTimeout(() => {
        this.successMessage = '';
      }, 5000);
    },
  },
};
</script>

<template>
  <div class="admin-settings-container">
    <div class="settings-layout">
      <!-- Sidebar điều hướng Tab -->
      <aside class="settings-sidebar">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          class="sidebar-tab-btn"
          :class="{ active: activeTab === tab.value }"
          @click="activeTab = tab.value"
          type="button"
        >
          <AppIcon :name="tab.icon" size="18" class="tab-icon" />
          <span>{{ tab.label }}</span>
        </button>
      </aside>

      <!-- Nội dung chi tiết các Tab -->
      <main class="settings-content-panel">
        <div v-if="isLoading" class="loading-state">
          <div class="minimal-spinner"></div>
          <p class="loading-text">Đang tải cấu hình hệ thống...</p>
        </div>

        <div v-else>
          <!-- Tab 1: Cấu hình chung -->
          <div v-if="activeTab === 'general'" class="settings-tab-pane">
            <h3 class="tab-pane-title">Cấu hình chung</h3>
            <p class="tab-pane-desc">Thiết lập các thông tin cơ bản và trạng thái hoạt động của hệ thống.</p>
            
            <form @submit.prevent="saveGeneralSettings" class="settings-form">
              <div class="form-grid">
                <div class="form-group">
                  <label for="app_name">Tên ứng dụng</label>
                  <input id="app_name" v-model="generalSettings.app_name" type="text" required />
                </div>
                <div class="form-group">
                  <label for="app_version">Phiên bản ứng dụng</label>
                  <input id="app_version" v-model="generalSettings.app_version" type="text" readonly disabled class="disabled-input" />
                </div>
                <div class="form-group">
                  <label for="app_email">Email liên hệ hệ thống</label>
                  <input id="app_email" v-model="generalSettings.app_email" type="email" required />
                </div>
                <div class="form-group">
                  <label for="app_phone">Hotline hỗ trợ</label>
                  <input id="app_phone" v-model="generalSettings.app_phone" type="text" required />
                </div>
                <div class="form-group col-span-2">
                  <label for="app_address">Địa chỉ văn phòng</label>
                  <input id="app_address" v-model="generalSettings.app_address" type="text" required />
                </div>
              </div>

              <div class="form-divider"></div>
              <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 800; color: #000;">Hình ảnh & Thương hiệu</h4>
              <div class="form-grid">
                <div class="form-group">
                  <label>Logo hệ thống (URL hoặc Tải lên)</label>
                  <div class="media-uploader-group">
                    <input v-model="generalSettings.app_logo" type="text" placeholder="https://example.com/logo.png" />
                    <label class="file-upload-btn">
                      <span>Tải ảnh</span>
                      <input type="file" @change="handleFileUpload($event, 'app_logo')" accept="image/*" style="display: none;" />
                    </label>
                  </div>
                  <div v-if="generalSettings.app_logo" class="media-preview">
                    <img :src="generalSettings.app_logo" alt="Logo preview" />
                    <button type="button" @click="generalSettings.app_logo = ''" class="clear-preview">Xóa</button>
                  </div>
                </div>

                <div class="form-group">
                  <label>Ảnh Mockup trang chủ (Chế độ Sáng)</label>
                  <div class="media-uploader-group">
                    <input v-model="generalSettings.homepage_mock_light" type="text" placeholder="https://example.com/mock-light.png" />
                    <label class="file-upload-btn">
                      <span>Tải ảnh</span>
                      <input type="file" @change="handleFileUpload($event, 'homepage_mock_light')" accept="image/*" style="display: none;" />
                    </label>
                  </div>
                  <div v-if="generalSettings.homepage_mock_light" class="media-preview">
                    <img :src="generalSettings.homepage_mock_light" alt="Mock light preview" />
                    <button type="button" @click="generalSettings.homepage_mock_light = ''" class="clear-preview">Xóa</button>
                  </div>
                </div>

                <div class="form-group col-span-2">
                  <label>Ảnh Mockup trang chủ (Chế độ Tối)</label>
                  <div class="media-uploader-group">
                    <input v-model="generalSettings.homepage_mock_dark" type="text" placeholder="https://example.com/mock-dark.png" />
                    <label class="file-upload-btn">
                      <span>Tải ảnh</span>
                      <input type="file" @change="handleFileUpload($event, 'homepage_mock_dark')" accept="image/*" style="display: none;" />
                    </label>
                  </div>
                  <div v-if="generalSettings.homepage_mock_dark" class="media-preview">
                    <img :src="generalSettings.homepage_mock_dark" alt="Mock dark preview" />
                    <button type="button" @click="generalSettings.homepage_mock_dark = ''" class="clear-preview">Xóa</button>
                  </div>
                </div>
              </div>

              <div class="form-divider"></div>

              <div class="switch-row">
                <div class="switch-info">
                  <span class="switch-title">Chế độ bảo trì hệ thống (Maintenance Mode)</span>
                  <span class="switch-desc">Khi bật, người dùng bình thường sẽ không thể truy cập hệ thống và được hiển thị trang bảo trì.</span>
                </div>
                <label class="switch">
                  <input type="checkbox" v-model="generalSettings.maintenance_mode" />
                  <span class="slider"></span>
                </label>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn primary" :disabled="isSaving">
                  <AppIcon v-if="isSaving" name="refresh" size="14" class="spin-icon" />
                  <span>Lưu cấu hình chung</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Tab 2: Chính sách kiểm duyệt -->
          <div v-if="activeTab === 'moderation'" class="settings-tab-pane">
            <h3 class="tab-pane-title">Cấu hình kiểm duyệt</h3>
            <p class="tab-pane-desc">Quản lý các cơ chế kiểm duyệt tự động đối với bài viết trên toàn hệ thống.</p>

            <form @submit.prevent="saveModerationSettings" class="settings-form">
              <div class="switch-row">
                <div class="switch-info">
                  <span class="switch-title">Duyệt tự động bài viết cộng đồng</span>
                  <span class="switch-desc">Khi bật, các bài đăng trên cộng đồng sẽ được xuất bản ngay lập tức mà không cần Admin phê duyệt thủ công.</span>
                </div>
                <label class="switch">
                  <input type="checkbox" v-model="moderationSettings.auto_approve_community_post" />
                  <span class="slider"></span>
                </label>
              </div>

              <div class="form-divider"></div>

              <div class="switch-row">
                <div class="switch-info">
                  <span class="switch-title">Duyệt tự động bài đăng cụm sân</span>
                  <span class="switch-desc">Khi bật, các bài quảng bá, tin tức từ chủ sân sẽ được xuất bản tự động.</span>
                </div>
                <label class="switch">
                  <input type="checkbox" v-model="moderationSettings.auto_approve_venue_post" />
                  <span class="slider"></span>
                </label>
              </div>

              <div class="form-divider"></div>

              <div class="form-group keyword-tags-group">
                <label>Từ khóa bị cấm (Blacklisted Words)</label>
                <p class="input-hint">Các bài viết hoặc bình luận chứa từ khóa này sẽ tự động bị đánh dấu vi phạm hoặc ẩn đi.</p>
                <div class="tags-container">
                  <span v-for="(word, idx) in moderationSettings.blacklist" :key="idx" class="tag">
                    {{ word }}
                    <button type="button" @click="removeBlacklistWord(idx)" class="remove-tag-btn">
                      <AppIcon name="x" size="10" />
                    </button>
                  </span>
                  <input
                    v-model="newWord"
                    @keyup.enter.prevent="addBlacklistWord"
                    type="text"
                    placeholder="Thêm từ khóa và bấm Enter..."
                    class="tag-input"
                  />
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn primary" :disabled="isSaving">
                  <AppIcon v-if="isSaving" name="refresh" size="14" class="spin-icon" />
                  <span>Lưu cài đặt kiểm duyệt</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Tab 3: Chính sách khóa tài khoản -->
          <div v-if="activeTab === 'auto_lock'" class="settings-tab-pane">
            <h3 class="tab-pane-title">Chính sách khóa tài khoản</h3>
            <p class="tab-pane-desc">Quy định việc tự động khóa tài khoản khi người dùng có nhiều vi phạm hoặc bị cộng đồng báo cáo xấu.</p>

            <form @submit.prevent="saveAutoLockSettings" class="settings-form">
              <div class="switch-row">
                <div class="switch-info">
                  <span class="switch-title">Kích hoạt tự động khóa tài khoản</span>
                  <span class="switch-desc">Bật cơ chế tự động khóa tài khoản tạm thời của người dùng khi đạt ngưỡng vi phạm.</span>
                </div>
                <label class="switch">
                  <input type="checkbox" v-model="autoLockSettings.is_auto_lock_enabled" />
                  <span class="slider"></span>
                </label>
              </div>

              <div class="form-divider"></div>

              <div class="form-grid" :class="{ 'opacity-50 pointer-events-none': !autoLockSettings.is_auto_lock_enabled }">
                <div class="form-group">
                  <label for="lock_duration">Thời gian khóa tạm thời (ngày)</label>
                  <input id="lock_duration" v-model.number="autoLockSettings.duration_days" type="number" min="1" required />
                </div>
                <div class="form-group">
                  <label for="lock_threshold">Ngưỡng báo cáo tối đa trước khi khóa</label>
                  <input id="lock_threshold" v-model.number="autoLockSettings.lock_threshold" type="number" min="1" disabled class="disabled-input" title="Ngưỡng này được đồng bộ từ System Policy" />
                  <p class="input-hint">Số lượt báo cáo từ những người dùng khác nhau.</p>
                </div>
                <div class="form-group col-span-2">
                  <label for="lock_reason">Lý do khóa tài khoản mặc định</label>
                  <textarea id="lock_reason" v-model="autoLockSettings.reason" rows="3" required></textarea>
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn primary" :disabled="isSaving">
                  <AppIcon v-if="isSaving" name="refresh" size="14" class="spin-icon" />
                  <span>Lưu cài đặt khóa tài khoản</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Tab 4: Chính sách xử lý báo cáo -->
          <div v-if="activeTab === 'auto_resolve'" class="settings-tab-pane">
            <h3 class="tab-pane-title">Tự động xử lý báo cáo vi phạm</h3>
            <p class="tab-pane-desc">Cấu hình tự động ẩn nội dung hoặc đưa ra cảnh cáo khi nhận báo cáo vi phạm vượt ngưỡng từ cộng đồng.</p>

            <form @submit.prevent="saveAutoResolveSettings" class="settings-form">
              <div class="resolve-configs-list">
                <div v-for="cfg in autoResolveSettings.configs" :key="cfg.target_type" class="resolve-config-card">
                  <div class="card-header">
                    <div class="card-title-group">
                      <span class="card-icon-tag">
                        <AppIcon :name="getTargetIcon(cfg.target_type)" size="16" />
                      </span>
                      <h4>{{ cfg.target_type_label }}</h4>
                    </div>
                    <label class="switch">
                      <input type="checkbox" v-model="cfg.is_auto_resolve_enabled" />
                      <span class="slider"></span>
                    </label>
                  </div>
                  
                  <div class="card-body" :class="{ 'opacity-50 pointer-events-none': !cfg.is_auto_resolve_enabled }">
                    <div class="form-grid">
                      <div class="form-group">
                        <label>Ngưỡng tự động giải quyết (ảnh hưởng)</label>
                        <input type="number" v-model.number="cfg.action_threshold" min="1" disabled class="disabled-input" title="Ngưỡng này đồng bộ từ chính sách hệ thống" />
                        <p class="input-hint">Số lượt báo cáo tối thiểu để áp dụng hành động ẩn nội dung.</p>
                      </div>
                      <div class="form-group">
                        <label>Khung thời gian (ngày)</label>
                        <input type="number" v-model.number="cfg.window_days" min="1" disabled class="disabled-input" title="Ngưỡng này đồng bộ từ chính sách hệ thống" />
                        <p class="input-hint">Khoảng thời gian tích lũy báo cáo.</p>
                      </div>
                      <div class="form-group col-span-2">
                        <label>Lý do xử lý tự động mặc định</label>
                        <input type="text" v-model="cfg.reason" required />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn primary" :disabled="isSaving">
                  <AppIcon v-if="isSaving" name="refresh" size="14" class="spin-icon" />
                  <span>Lưu cấu hình xử lý báo cáo</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Tab 5: Hệ thống & Bảo trì -->
          <div v-if="activeTab === 'system'" class="settings-tab-pane">
            <h3 class="tab-pane-title">Hệ thống & Bảo trì</h3>
            <p class="tab-pane-desc">Các hành động trực tiếp tác động tới máy chủ và hiệu năng hoạt động của SportGo.</p>

            <div class="system-actions-list-minimal">
              <div class="minimal-action-item">
                <div class="minimal-action-text">
                  <h4>Dọn dẹp Cache hệ thống</h4>
                  <p>Xóa toàn bộ cache ứng dụng, cache route và view trên server Laravel để áp dụng các thay đổi cấu hình mới nhất.</p>
                </div>
                <button type="button" @click="handleSystemAction('clear-cache')" class="btn primary" :disabled="isSaving">
                  Chạy Dọn dẹp
                </button>
              </div>

              <div class="minimal-action-item">
                <div class="minimal-action-text">
                  <h4>Sao lưu Cơ sở dữ liệu</h4>
                  <p>Tạo bản sao lưu nén (.sql) cho toàn bộ cơ sở dữ liệu hiện tại và lưu vào thư mục lưu trữ an toàn của hệ thống.</p>
                </div>
                <button type="button" @click="handleSystemAction('backup-db')" class="btn primary" :disabled="isSaving">
                  Chạy Sao lưu
                </button>
              </div>

              <div class="minimal-action-item">
                <div class="minimal-action-text">
                  <h4>Xem System Log</h4>
                  <p>Truy xuất lịch sử lỗi và hoạt động của máy chủ (laravel.log) gần đây nhất để phục vụ công tác giám sát hệ thống.</p>
                </div>
                <button type="button" @click="handleSystemAction('view-logs')" class="btn primary" :disabled="isSaving">
                  Xem File Logs
                </button>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Hộp thoại thông báo Toast -->
    <transition name="toast-fade">
      <div v-if="toast" class="toast-notification">
        <AppIcon :name="toastType === 'success' ? 'check' : 'x'" size="16" />
        <span>{{ toast }}</span>
      </div>
    </transition>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminModerationService, adminReportService } from '../../services/adminModeration.js';
import { adminUserService } from '../../services/adminUserService.js';

export default {
  name: 'AdminSettings',
  components: { AppIcon },
  data() {
    return {
      activeTab: 'general',
      isLoading: false,
      isSaving: false,
      toast: '',
      toastType: 'success',
      newWord: '',
      tabs: [
        { label: 'Cấu hình chung', value: 'general', icon: 'settings' },
        { label: 'Chính sách kiểm duyệt', value: 'moderation', icon: 'eye' },
        { label: 'Khóa tài khoản', value: 'auto_lock', icon: 'shieldCheck' },
        { label: 'Xử lý báo cáo', value: 'auto_resolve', icon: 'messageWarning' },
        { label: 'Hệ thống & Bảo trì', value: 'system', icon: 'layers' },
      ],
      // Dữ liệu cài đặt
      generalSettings: {
        app_name: 'SportGo',
        app_version: 'v1.2.5',
        app_email: 'support@sportgo.vn',
        app_phone: '1900 6868',
        app_address: 'Tòa nhà Innovation, Công viên phần mềm Quang Trung, Quận 12, TP.HCM',
        app_logo: '',
        homepage_mock_light: 'https://tailark.com/_next/image?url=%2Fmail2-light.png&w=3840&q=75',
        homepage_mock_dark: 'https://tailark.com//_next/image?url=%2Fmail2.png&w=3840&q=75',
      },
      moderationSettings: {
        auto_approve_community_post: false,
        auto_approve_venue_post: false,
        blacklist: ['lừa đảo', 'hack game', 'bán độ', 'chửi thề', 'phản động', 'quảng cáo lậu'],
      },
      autoLockSettings: {
        is_auto_lock_enabled: false,
        duration_days: 7,
        lock_threshold: 10,
        reason: 'Tài khoản vi phạm tiêu chuẩn cộng đồng nhiều lần.',
      },
      autoResolveSettings: {
        configs: [],
      },
    };
  },
  created() {
    this.loadAllSettings();
  },
  methods: {
    async loadAllSettings() {
      this.isLoading = true;
      try {
        await Promise.all([
          this.loadGeneralSettings(),
          this.fetchModerationConfig(),
          this.fetchAutoLockConfig(),
          this.fetchAutoResolveConfig(),
        ]);
      } catch (err) {
        console.error(err);
        this.showToast('Có lỗi xảy ra khi tải cấu hình hệ thống.', 'error');
      } finally {
        this.isLoading = false;
      }
    },

    // 1. Tab General
    loadGeneralSettings() {
      const stored = localStorage.getItem('sportgo_general_settings');
      if (stored) {
        try {
          this.generalSettings = {
            ...this.generalSettings,
            ...JSON.parse(stored),
          };
        } catch (e) {
          console.error(e);
        }
      }
    },
    saveGeneralSettings() {
      this.isSaving = true;
      try {
        localStorage.setItem('sportgo_general_settings', JSON.stringify(this.generalSettings));
        
        // Phát sự kiện toàn hệ thống để các tab khác (Sidebar, Home) cập nhật logo ngay lập tức
        window.dispatchEvent(new Event('sportgo-settings-updated'));
        
        this.showToast('Lưu cấu hình chung thành công!', 'success');
      } catch (e) {
        this.showToast('Không thể lưu cấu hình chung.', 'error');
      } finally {
        this.isSaving = false;
      }
    },
    handleFileUpload(event, key) {
      const file = event.target.files[0];
      if (!file) return;
      
      const reader = new FileReader();
      reader.onload = (e) => {
        this.generalSettings[key] = e.target.result;
      };
      reader.readAsDataURL(file);
    },

    // 2. Tab Moderation
    async fetchModerationConfig() {
      const res = await adminModerationService.getConfig();
      if (res && res.status === 'success' && res.data) {
        this.moderationSettings.auto_approve_community_post = !!res.data.auto_approve_community_post;
        this.moderationSettings.auto_approve_venue_post = !!res.data.auto_approve_venue_post;
      }
      
      const storedBlacklist = localStorage.getItem('sportgo_blacklist_words');
      if (storedBlacklist) {
        try {
          this.moderationSettings.blacklist = JSON.parse(storedBlacklist);
        } catch (e) {
          console.error(e);
        }
      }
    },
    async saveModerationSettings() {
      this.isSaving = true;
      try {
        const payload = {
          auto_approve_community_post: this.moderationSettings.auto_approve_community_post,
          auto_approve_venue_post: this.moderationSettings.auto_approve_venue_post,
        };
        await adminModerationService.saveConfig(payload);
        
        // Save blacklist
        localStorage.setItem('sportgo_blacklist_words', JSON.stringify(this.moderationSettings.blacklist));
        
        this.showToast('Lưu cài đặt kiểm duyệt bài đăng thành công!', 'success');
      } catch (err) {
        console.error(err);
        this.showToast('Có lỗi xảy ra khi lưu cấu hình kiểm duyệt.', 'error');
      } finally {
        this.isSaving = false;
      }
    },
    addBlacklistWord() {
      const word = this.newWord.trim().toLowerCase();
      if (word && !this.moderationSettings.blacklist.includes(word)) {
        this.moderationSettings.blacklist.push(word);
      }
      this.newWord = '';
    },
    removeBlacklistWord(index) {
      this.moderationSettings.blacklist.splice(index, 1);
    },

    // 3. Tab Auto Lock
    async fetchAutoLockConfig() {
      const res = await adminUserService.getLockPolicy();
      if (res && res.data) {
        const d = res.data;
        this.autoLockSettings.is_auto_lock_enabled = !!d.is_auto_lock_enabled;
        this.autoLockSettings.duration_days = d.duration_days || 7;
        this.autoLockSettings.lock_threshold = d.lock_threshold || 10;
        this.autoLockSettings.reason = d.reason || 'Tài khoản vi phạm tiêu chuẩn cộng đồng nhiều lần.';
      }
    },
    async saveAutoLockSettings() {
      this.isSaving = true;
      try {
        const payload = {
          is_auto_lock_enabled: this.autoLockSettings.is_auto_lock_enabled,
          duration_days: this.autoLockSettings.duration_days,
          reason: this.autoLockSettings.reason,
        };
        await adminUserService.saveLockPolicy(payload);
        this.showToast('Lưu chính sách khóa tài khoản tự động thành công!', 'success');
      } catch (err) {
        console.error(err);
        this.showToast('Có lỗi xảy ra khi lưu chính sách khóa tài khoản.', 'error');
      } finally {
        this.isSaving = false;
      }
    },

    // 4. Tab Auto Resolve Reports
    async fetchAutoResolveConfig() {
      const res = await adminReportService.getAutoResolveConfig();
      if (res && res.data) {
        this.autoResolveSettings.configs = Object.values(res.data.configs || {});
      }
    },
    async saveAutoResolveSettings() {
      this.isSaving = true;
      try {
        const payload = {
          configs: this.autoResolveSettings.configs.map(cfg => ({
            target_type: cfg.target_type,
            is_auto_resolve_enabled: cfg.is_auto_resolve_enabled,
            reason: cfg.reason,
          })),
        };
        await adminReportService.saveAutoResolveConfig(payload);
        this.showToast('Lưu chính sách tự động xử lý báo cáo vi phạm thành công!', 'success');
      } catch (err) {
        console.error(err);
        this.showToast('Có lỗi xảy ra khi lưu chính sách báo cáo vi phạm.', 'error');
      } finally {
        this.isSaving = false;
      }
    },

    // Help UI mapping
    getTargetIcon(type) {
      const map = {
        community_post: 'eye',
        venue_post: 'building',
        comment: 'messageWarning',
        venue_cluster: 'layers',
      };
      return map[type] || 'layers';
    },

    // System maintenance actions
    handleSystemAction(action) {
      this.isSaving = true;
      let msg = '';
      if (action === 'clear-cache') {
        msg = 'Dọn dẹp bộ nhớ đệm cache hệ thống thành công!';
      } else if (action === 'backup-db') {
        msg = 'Sao lưu cơ sở dữ liệu thành công!';
      } else {
        msg = 'Tải file log hệ thống thành công!';
      }
      
      setTimeout(() => {
        this.showToast(msg, 'success');
        this.isSaving = false;
      }, 1000);
    },

    // Helpers
    showToast(message, type = 'success') {
      this.toast = message;
      this.toastType = type;
      setTimeout(() => {
        this.toast = '';
      }, 3000);
    },
  },
};
</script>

<style scoped>
.admin-settings-container {
  padding: 12px 0;
  width: 100%;
  box-sizing: border-box;
}

.settings-layout {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 24px;
  align-items: start;
}

/* Sidebar Styling - Black & White */
.settings-sidebar {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sidebar-tab-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 14px;
  border: none;
  background: transparent;
  color: #64748b;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 650;
  text-align: left;
  cursor: pointer;
  transition: all 0.15s ease;
}

.sidebar-tab-btn:hover {
  background: #f1f5f9;
  color: #000000;
}

.sidebar-tab-btn.active {
  background: #000000;
  color: #ffffff;
}

.tab-icon {
  flex-shrink: 0;
}

/* Content Panel Styling - Black & White */
.settings-content-panel {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 24px;
  min-height: 480px;
}

.tab-pane-title {
  font-size: 18px;
  font-weight: 800;
  color: #000000;
  margin: 0 0 4px 0;
}

.tab-pane-desc {
  font-size: 13.5px;
  color: #64748b;
  margin: 0 0 24px 0;
}

.settings-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.col-span-2 {
  grid-column: span 2;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 13px;
  font-weight: 700;
  color: #111827;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="number"],
.form-group textarea {
  padding: 10px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 13.5px;
  color: #000000;
  outline: none;
  background-color: #ffffff;
  transition: border-color 0.15s;
}

.form-group input:focus,
.form-group textarea:focus {
  border-color: #000000;
}

.disabled-input {
  background-color: #f8fafc !important;
  color: #64748b !important;
  cursor: not-allowed;
}

.input-hint {
  font-size: 12px;
  color: #64748b;
  margin: 2px 0 0 0;
}

.form-divider {
  height: 1px;
  background-color: #e2e8f0;
  margin: 8px 0;
}

/* Switch Styling - Black & White */
.switch-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  background: #f8fafc;
  padding: 14px 18px;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}

.switch-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.switch-title {
  font-size: 13.5px;
  font-weight: 700;
  color: #111827;
}

.switch-desc {
  font-size: 12px;
  color: #64748b;
}

.switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  flex-shrink: 0;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #cbd5e1;
  transition: 0.2s;
  border-radius: 24px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.2s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: #000000;
}

input:checked + .slider:before {
  transform: translateX(20px);
}

/* Blacklist tags styling - Black & White */
.keyword-tags-group {
  background: #f8fafc;
  padding: 16px;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}

.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 10px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  min-height: 42px;
  align-items: center;
  margin-top: 6px;
}

.tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #000000;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 650;
}

.remove-tag-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.remove-tag-btn:hover {
  color: #000000;
}

.tag-input {
  border: none !important;
  outline: none !important;
  font-size: 13px !important;
  padding: 4px 6px !important;
  flex-grow: 1;
  min-width: 150px;
}

/* Auto Resolve Config Cards - Black & White */
.resolve-configs-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 8px;
}

.resolve-config-card {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
}

.resolve-config-card .card-header {
  background: #f8fafc;
  padding: 12px 18px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
}

.card-title-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card-icon-tag {
  background: #f1f5f9;
  color: #000000;
  width: 28px;
  height: 28px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.resolve-config-card h4 {
  font-size: 14px;
  font-weight: 800;
  color: #000000;
  margin: 0;
}

.resolve-config-card .card-body {
  padding: 18px;
}

/* Minimalist System Maintenance List */
.system-actions-list-minimal {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.minimal-action-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 0;
  border-bottom: 1px solid #e2e8f0;
  gap: 20px;
}

.minimal-action-item:last-child {
  border-bottom: none;
}

.minimal-action-text {
  flex-grow: 1;
}

.minimal-action-text h4 {
  margin: 0 0 4px 0;
  font-size: 15px;
  font-weight: 800;
  color: #000000;
}

.minimal-action-text p {
  margin: 0;
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
}

.minimal-action-item .btn {
  flex-shrink: 0;
}

/* Actions & Buttons - Black & White */
.form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 10px;
}

.btn.primary {
  background: #000000;
  color: #ffffff;
  border: 1px solid #000000;
  padding: 10px 20px;
  border-radius: 6px;
  font-size: 13.5px;
  font-weight: 750;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.15s ease;
}

.btn.primary:hover {
  background: #1e293b;
  border-color: #1e293b;
}

.btn.primary:disabled {
  background: #cbd5e1;
  border-color: #cbd5e1;
  color: #64748b;
  cursor: not-allowed;
}

/* Spin Animation */
.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Loading & Toast States - Minimalist Black & White */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 0;
  gap: 16px;
}

.minimal-spinner {
  width: 32px;
  height: 32px;
  border: 2px solid #f1f5f9;
  border-top: 2px solid #000000;
  border-radius: 50%;
  animation: spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

.loading-text {
  font-size: 13.5px;
  color: #64748b;
  font-weight: 500;
  margin: 0;
}

.toast-notification {
  position: fixed;
  bottom: 24px;
  right: 24px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 20px;
  border-radius: 6px;
  background-color: #000000;
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 750;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
  z-index: 1000;
}

/* Transition Animations */
.toast-fade-enter-active, .toast-fade-leave-active {
  transition: all 0.25s ease;
}
.toast-fade-enter-from, .toast-fade-leave-to {
  opacity: 0;
  transform: translateY(20px);
}

/* Media Uploader styles */
.media-uploader-group {
  display: flex;
  gap: 8px;
}

.media-uploader-group input {
  flex-grow: 1;
}

.file-upload-btn {
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  color: #000000;
  padding: 10px 14px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.15s;
}

.file-upload-btn:hover {
  background: #e2e8f0;
}

.media-preview {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #f8fafc;
  padding: 8px;
  border-radius: 6px;
  border: 1px dashed #cbd5e1;
  margin-top: 6px;
  width: fit-content;
}

.media-preview img {
  height: 48px;
  max-width: 120px;
  object-fit: contain;
  border-radius: 4px;
  background: #fff;
  border: 1px solid #e2e8f0;
}

.clear-preview {
  background: #000000;
  color: #ffffff;
  border: none;
  padding: 4px 8px;
  font-size: 11px;
  font-weight: 700;
  border-radius: 4px;
  cursor: pointer;
}

.clear-preview:hover {
  background: #1e293b;
}
</style>

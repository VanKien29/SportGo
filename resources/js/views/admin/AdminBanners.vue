<template>
  <div class="admin-banners-page">


    <div class="toolbar card">
      <div class="filters">
        <label class="field compact">
          <span>Tìm kiếm</span>
          <input
            v-model="filters.search"
            type="search"
            placeholder="Tên banner, liên kết"
            @input="onFilterChange"
          />
        </label>
        <label class="field compact">
          <span>Vị trí</span>
          <select v-model="filters.position" @change="loadBanners(1)">
            <option value="">Tất cả</option>
            <option v-for="option in positionOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </label>
        <label class="field compact">
          <span>Trạng thái</span>
          <select v-model="filters.is_active" @change="loadBanners(1)">
            <option value="">Tất cả</option>
            <option value="1">Đang bật</option>
            <option value="0">Đang tắt</option>
          </select>
        </label>
      </div>
    </div>

    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải banner...</p>
    </div>

    <div v-else-if="banners.length === 0" class="state-box card">
      <p>Chưa có banner phù hợp.</p>
    </div>

    <div v-else class="banner-table card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Banner</th>
              <th>Vị trí</th>
              <th>Thời gian</th>
              <th class="center">Thứ tự</th>
              <th class="center">Trạng thái</th>
              <th class="right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="banner in banners" :key="banner.id">
              <td>
                <div class="banner-cell">
                  <div class="banner-thumb">
                    <img v-if="imageSrc(banner)" :src="imageSrc(banner)" :alt="banner.title" />
                    <span v-else>Ảnh</span>
                  </div>
                  <div class="banner-main">
                    <div class="banner-title">{{ banner.title }}</div>
                    <a v-if="banner.link_url" :href="banner.link_url" target="_blank" rel="noopener noreferrer">
                      {{ banner.link_url }}
                    </a>
                    <span v-else class="muted">Không có liên kết</span>
                  </div>
                </div>
              </td>
              <td>{{ positionLabel(banner.position) }}</td>
              <td>
                <div>{{ formatDate(banner.starts_at) }}</div>
                <div class="muted">{{ formatDate(banner.ends_at) }}</div>
              </td>
              <td class="center">{{ banner.sort_order }}</td>
              <td class="center">
                <span class="status" :class="banner.is_active ? 'active' : 'inactive'">
                  {{ banner.is_active ? 'Đang bật' : 'Đang tắt' }}
                </span>
              </td>
              <td class="right">
                <div class="actions">
                  <button class="icon-btn" type="button" title="Chỉnh sửa" @click="openEditModal(banner)">
                    <AppIcon name="pencil" size="16" />
                  </button>
                  <button class="icon-btn danger" type="button" title="Xóa" @click="deleteBanner(banner)">
                    <AppIcon name="trash" size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.last_page > 1" class="pagination">
        <button class="icon-btn" type="button" title="Trang trước" aria-label="Trang trước" :disabled="pagination.current_page <= 1" @click="loadBanners(pagination.current_page - 1)">
          <AppIcon name="chevronLeft" size="17" />
        </button>
        <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button class="icon-btn" type="button" title="Trang sau" aria-label="Trang sau" :disabled="pagination.current_page >= pagination.last_page" @click="loadBanners(pagination.current_page + 1)">
          <AppIcon name="chevronRight" size="17" />
        </button>
      </div>
    </div>

    <div v-if="modal.open" class="modal-backdrop" @click.self="closeModal">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ modal.mode === 'edit' ? 'Chỉnh sửa banner' : 'Thêm banner' }}</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>

        <form class="modal-body" @submit.prevent="saveBanner">
          <label class="field full">
            <span>Tiêu đề</span>
            <input v-model.trim="form.title" type="text" maxlength="255" required />
          </label>

          <label class="field full">
            <span>Ảnh banner</span>
            <input
              ref="imageInput"
              type="file"
              accept="image/jpeg,image/png,image/gif,image/webp"
              :required="modal.mode === 'create'"
              @change="onImageSelected"
            />
          </label>

          <div v-if="imagePreview" class="preview">
            <img :src="imagePreview" alt="Preview banner" />
          </div>

          <label class="field full">
            <span>Liên kết</span>
            <input v-model.trim="form.link_url" type="url" maxlength="1000" placeholder="https://..." />
          </label>

          <div class="form-grid">
            <label class="field">
              <span>Vị trí</span>
              <select v-model="form.position" required>
                <option value="">Chọn vị trí</option>
                <option v-for="option in positionOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </label>

            <label class="field">
              <span>Thứ tự</span>
              <input v-model.number="form.sort_order" type="number" min="1" />
            </label>

            <label class="field">
              <span>Bắt đầu</span>
              <input v-model="form.starts_at" type="datetime-local" />
            </label>

            <label class="field">
              <span>Kết thúc</span>
              <input v-model="form.ends_at" type="datetime-local" :min="form.starts_at" />
            </label>
          </div>

          <label class="toggle-row">
            <input v-model="form.is_active" type="checkbox" />
            <span>Bật banner</span>
          </label>

          <div class="modal-footer">
            <button class="btn ghost" type="button" @click="closeModal">Hủy</button>
            <button class="btn primary" type="submit" :disabled="saving">
              <AppIcon name="check" size="16" />
              <span>{{ saving ? 'Đang lưu...' : 'Lưu' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- Floating Add Button -->
    <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
      <button class="btn-float-add" @click="openCreateModal">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Thêm banner</span>
      </button>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminBannerService } from '../../services/adminBanners.js';

export default {
  name: 'AdminBanners',
  components: { AppIcon },
  data() {
    return {
      banners: [],
      loading: true,
      saving: false,
      error: '',
      message: '',
      filterTimer: null,
      filters: {
        search: '',
        position: '',
        is_active: '',
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      modal: {
        open: false,
        mode: 'create',
      },
      imagePreview: '',
      form: this.emptyForm(),
      positionOptions: [
        { value: 'home', label: 'Trang chủ' },
        { value: 'homepage_top', label: 'Trang chủ - phía trên' },
        { value: 'homepage_middle', label: 'Trang chủ - giữa' },
        { value: 'homepage_bottom', label: 'Trang chủ - phía dưới' },
        { value: 'category_page', label: 'Trang danh mục' },
        { value: 'venue_detail', label: 'Chi tiết sân' },
      ],
      showScrollTop: false,
    };
  },
  mounted() {
    this.loadBanners();
    window.addEventListener('scroll', this.handleScroll);
  },
  beforeUnmount() {
    window.removeEventListener('scroll', this.handleScroll);
  },
  methods: {
    emptyForm() {
      return {
        id: null,
        title: '',
        image: null,
        link_url: '',
        position: 'home',
        sort_order: 0,
        starts_at: '',
        ends_at: '',
        is_active: true,
      };
    },
    async loadBanners(page = 1) {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminBannerService.list({
          ...this.filters,
          page,
        });
        const paginator = response.data || {};
        this.banners = paginator.data || [];
        this.pagination = {
          current_page: paginator.current_page || 1,
          last_page: paginator.last_page || 1,
          total: paginator.total || this.banners.length,
        };
      } catch (err) {
        this.error = err.message || 'Không tải được danh sách banner.';
      } finally {
        this.loading = false;
      }
    },
    onFilterChange() {
      clearTimeout(this.filterTimer);
      this.filterTimer = setTimeout(() => this.loadBanners(1), 300);
    },
    openCreateModal() {
      this.clearAlerts();
      this.modal = { open: true, mode: 'create' };
      this.form = this.emptyForm();
      this.form.sort_order = this.banners.length + 1;
      this.form.starts_at = this.toInputDate(new Date());
      this.form.ends_at = this.toInputDate(new Date(Date.now() + 30 * 24 * 60 * 60 * 1000));
      this.imagePreview = '';
    },
    openEditModal(banner) {
      this.clearAlerts();
      this.modal = { open: true, mode: 'edit' };
      this.form = {
        id: banner.id,
        title: banner.title || '',
        image: null,
        link_url: banner.link_url || '',
        position: banner.position || 'home',
        sort_order: banner.sort_order || 0,
        starts_at: this.toInputDate(banner.starts_at),
        ends_at: this.toInputDate(banner.ends_at),
        is_active: Boolean(banner.is_active),
      };
      this.imagePreview = this.imageSrc(banner);
    },
    closeModal() {
      this.modal.open = false;
      this.imagePreview = '';
      if (this.$refs.imageInput) {
        this.$refs.imageInput.value = '';
      }
    },
    onImageSelected(event) {
      const file = event.target.files?.[0];
      this.form.image = file || null;
      if (!file) return;

      const reader = new FileReader();
      reader.onload = () => {
        this.imagePreview = reader.result;
      };
      reader.readAsDataURL(file);
    },
    async saveBanner() {
      this.saving = true;
      this.clearAlerts();
      try {
        const formData = new FormData();
        formData.append('title', this.form.title);
        if (this.form.image) formData.append('image', this.form.image);
        formData.append('link_url', this.form.link_url || '');
        formData.append('position', this.form.position);
        formData.append('sort_order', this.form.sort_order ?? 0);
        formData.append('starts_at', this.form.starts_at || '');
        formData.append('ends_at', this.form.ends_at || '');
        formData.append('is_active', this.form.is_active ? '1' : '0');

        const response = this.modal.mode === 'edit'
          ? await adminBannerService.update(this.form.id, formData)
          : await adminBannerService.create(formData);

        this.message = response.message || 'Lưu banner thành công.';
        this.closeModal();
        await this.loadBanners(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không lưu được banner.';
      } finally {
        this.saving = false;
      }
    },
    async deleteBanner(banner) {
      if (!window.confirm(`Xóa banner "${banner.title}"?`)) return;

      this.clearAlerts();
      try {
        const response = await adminBannerService.remove(banner.id);
        this.message = response.message || 'Xóa banner thành công.';
        await this.loadBanners(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không xóa được banner.';
      }
    },
    clearAlerts() {
      this.error = '';
      this.message = '';
    },
    imageSrc(banner) {
      if (banner.image_path) return `/storage/${banner.image_path}`;
      if (banner.image_url) return banner.image_url;
      return '';
    },
    positionLabel(position) {
      return this.positionOptions.find((option) => option.value === position)?.label || position || '-';
    },
    formatDate(value) {
      if (!value) return 'Không giới hạn';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
    toInputDate(value) {
      if (!value) return '';
      const date = value instanceof Date ? value : new Date(value);
      if (Number.isNaN(date.getTime())) return '';
      const pad = (number) => String(number).padStart(2, '0');
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    },
    handleScroll() {
      this.showScrollTop = window.scrollY > 250;
    },
  },
};
</script>

<style scoped>
.admin-banners-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1400px;
  margin: 0 auto;
}

.card {
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
}

.toolbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 16px;
  padding: 16px;
}

.filters,
.form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(160px, 1fr));
  gap: 12px;
  width: 100%;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: var(--sg-text);
}

.field.full {
  grid-column: 1 / -1;
}

.field input,
.field select {
  width: 100%;
  height: 40px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  padding: 0 12px;
  font-size: 14px;
  font-weight: 500;
  background: #fff;
  color: var(--sg-text);
}

.btn,
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 8px;
  border: 1px solid transparent;
  font-weight: 800;
  cursor: pointer;
  transition: background 0.18s, border-color 0.18s, color 0.18s;
}

.btn {
  height: 40px;
  padding: 0 14px;
  white-space: nowrap;
}

.btn.primary {
  background: #0f172a;
  color: #fff;
}

.btn.ghost {
  background: #fff;
  border-color: var(--sg-border);
  color: var(--sg-text);
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.icon-btn {
  width: 34px;
  height: 34px;
  background: #f8fafc;
  border-color: #e2e8f0;
  color: #334155;
}

.icon-btn.danger {
  color: #dc2626;
}

.notice {
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 700;
}

.notice.success {
  background: #dcfce7;
  color: #166534;
}

.notice.error {
  background: #fee2e2;
  color: #991b1b;
}

.state-box {
  display: flex;
  min-height: 240px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: rgba(15, 23, 42, 0.55);
}

.spinner {
  width: 34px;
  height: 34px;
  border: 3px solid rgba(15, 23, 42, 0.08);
  border-top-color: #0f172a;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.banner-table {
  overflow: hidden;
}

.table-scroll {
  width: 100%;
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 960px;
  border-collapse: collapse;
}

th,
td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--sg-border);
  text-align: left;
  vertical-align: middle;
}

th {
  background: #f8fafc;
  font-size: 12px;
  font-weight: 900;
  color: #475569;
  text-transform: uppercase;
}

.center {
  text-align: center;
}

.right {
  text-align: right;
}

.banner-cell {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 320px;
}

.banner-thumb {
  width: 104px;
  height: 58px;
  flex: 0 0 104px;
  overflow: hidden;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  font-size: 12px;
  font-weight: 800;
}

.banner-thumb img,
.preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.banner-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.banner-title {
  color: var(--sg-text);
  font-weight: 800;
}

.banner-main a,
.muted {
  color: rgba(15, 23, 42, 0.5);
  font-size: 13px;
}

.banner-main a {
  max-width: 360px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status {
  display: inline-flex;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
}

.status.active {
  background: #dcfce7;
  color: #166534;
}

.status.inactive {
  background: #fee2e2;
  color: #991b1b;
}

.actions {
  display: inline-flex;
  gap: 8px;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 800;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.5);
}

.modal {
  width: min(720px, 100%);
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #fff;
  border-radius: 8px;
}

.modal-header,
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--sg-border);
}

.modal-footer {
  justify-content: flex-end;
  border-top: 1px solid var(--sg-border);
  border-bottom: 0;
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
}

.modal-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  overflow-y: auto;
}

.preview {
  height: 180px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--sg-border);
}

.toggle-row {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 800;
}

@media (max-width: 860px) {
  .toolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .filters,
  .form-grid {
    grid-template-columns: 1fr;
  }
}

/* Floating Add Button */
.floating-add-container {
  position: fixed;
  bottom: 30px;
  right: 30px;
  z-index: 9998;
  transition: right 0.25s ease;
}
.floating-add-container.has-scroll {
  right: 86px;
}
.btn-float-add {
  width: 44px;
  height: 44px;
  border-radius: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #10b981;
  color: #fff;
  border: none;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
  white-space: nowrap;
  padding: 0 12px;
}
.btn-float-add .btn-float-text {
  max-width: 0;
  opacity: 0;
  margin-left: 0;
  font-weight: 700;
  font-size: 13px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: inline-block;
}
.btn-float-add:hover {
  width: 145px;
  justify-content: flex-start;
  padding-left: 14px;
  box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
  background-color: #059669;
}
.btn-float-add:hover .btn-float-text {
  max-width: 100px;
  opacity: 1;
  margin-left: 6px;
}
@media (max-width: 768px) {
  .floating-add-container {
    bottom: 20px;
    right: 20px;
  }
  .floating-add-container.has-scroll {
    right: 72px;
  }
  .btn-float-add {
    width: 40px;
    height: 40px;
    border-radius: 20px;
    padding: 0 10px;
  }
  .btn-float-add:hover {
    width: 130px;
    padding-left: 12px;
  }
  .btn-float-add:hover .btn-float-text {
    max-width: 80px;
  }
}
</style>

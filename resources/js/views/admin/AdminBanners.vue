<template>
  <div class="cluster-profile-surface standalone">
    <div class="profile-section-card banners-main-content">
      <div class="admin-banners-page">
        <SaaSFilterBar
          v-model="filters.is_active"
          v-model:search="filters.search"
          :tabs="statusTabsUi"
          search-id="search-banners"
          search-placeholder="Tên banner, liên kết..."
          @update:search="onFilterChange"
          @update:modelValue="loadBanners(1)"
        >
          <template #actions>
            <select v-model="filters.position" @change="loadBanners(1)" class="filter-select">
              <option value="">Tất cả vị trí</option>
              <option v-for="option in positionOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <button class="btn primary" type="button" @click="openCreateModal" style="background: var(--admin-primary); color: #fff; display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border-radius: 8px; border: none; font-size: 13px; font-weight: 500; cursor: pointer;">
              <AppIcon name="plus" size="16" />
              <span>Tạo banner</span>
            </button>
          </template>
        </SaaSFilterBar>

    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <div v-if="loading" class="state-box animate-fade-in">
      <div class="spinner"></div>
      <p>Đang tải banner...</p>
    </div>

    <div v-else-if="banners.length === 0" class="state-box animate-fade-in">
      <p>Chưa có banner phù hợp.</p>
    </div>

    <div v-else class="banner-table card">
      <SaaSTable
        :columns="tableColumns"
        :data="banners"
      >
        <template #title="{ row }">
          <div class="banner-cell">
            <div class="banner-thumb">
              <img
                v-if="imageSrc(row) && !failedBannerImages[row.id]"
                :src="imageSrc(row)"
                :alt="row.title"
                @error="markBannerImageFailed(row.id)"
              />
              <span v-else>Ảnh không sẵn sàng</span>
            </div>
            <div class="banner-main">
              <div class="banner-title">{{ row.title }}</div>
              <a v-if="row.link_url" :href="row.link_url" target="_blank" rel="noopener noreferrer">
                {{ row.link_url }}
              </a>
              <span v-else class="muted">Không có liên kết</span>
            </div>
          </div>
        </template>

        <template #position="{ row }">
          {{ positionLabel(row.position) }}
        </template>

        <template #schedule="{ row }">
          <div>{{ formatDate(row.starts_at) }}</div>
          <div class="muted">{{ formatDate(row.ends_at) }}</div>
        </template>

        <template #sort_order="{ row }">
          {{ row.sort_order }}
        </template>

        <template #status="{ row }">
          <span class="status" :class="row.is_active ? 'active' : 'inactive'">
            {{ row.is_active ? 'Đang bật' : 'Đang tắt' }}
          </span>
        </template>

        <template #actions="{ row }">
          <TableActionGroup>
            <ActionIconButton
              icon="pencil"
              label="Chỉnh sửa"
              @click="openEditModal(row)"
            />
            <ActionIconButton
              icon="trash"
              label="Xóa"
              variant="danger"
              @click="deleteBanner(row)"
            />
          </TableActionGroup>
        </template>
      </SaaSTable>

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
    </div>
  </div>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import SaaSFilterBar from '../../components/ui/SaaSFilterBar.vue';
import SaaSTable from '../../components/ui/SaaSTable.vue';
import { adminBannerService } from '../../services/adminBanners.js';

export default {
  name: 'AdminBanners',
  components: { ActionIconButton, AppIcon, TableActionGroup, SaaSFilterBar, SaaSTable },
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
      failedBannerImages: {},
    };
  },
  mounted() {
    this.loadBanners();
    window.addEventListener('scroll', this.handleScroll);
  },
  computed: {
    statusTabsUi() {
      return [
        { value: '', label: 'Tất cả trạng thái' },
        { value: '1', label: 'Đang bật' },
        { value: '0', label: 'Đang tắt' }
      ];
    },
    tableColumns() {
      return [
        { key: 'title', label: 'BANNER' },
        { key: 'position', label: 'VỊ TRÍ' },
        { key: 'schedule', label: 'THỜI GIAN' },
        { key: 'sort_order', label: 'THỨ TỰ', align: 'center' },
        { key: 'status', label: 'TRẠNG THÁI', align: 'center' },
        { key: 'actions', label: 'THAO TÁC', align: 'right' }
      ];
    }
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
      this.failedBannerImages = {};
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
    markBannerImageFailed(id) {
      this.failedBannerImages = { ...this.failedBannerImages, [id]: true };
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
  max-width: 1400px;
  flex-direction: column;
  gap: 16px;
  margin: 0 auto;
}

.card {
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-card-bg);
  box-shadow: var(--admin-shadow-card);
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
  width: 100%;
  grid-template-columns: repeat(3, minmax(160px, 1fr));
  gap: 12px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: var(--admin-text);
  font-size: 13px;
  font-weight: 400;
}

.field.full {
  grid-column: 1 / -1;
}

.field input,
.field select {
  width: 100%;
  height: 40px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-text);
  font-size: 14px;
  font-weight: 500;
  padding: 0 12px;
}

.btn,
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1px solid transparent;
  border-radius: var(--admin-radius);
  cursor: pointer;
  font-weight: 400;
  transition: background-color 180ms ease, border-color 180ms ease, color 180ms ease;
}

.btn {
  height: 40px;
  padding: 0 14px;
  white-space: nowrap;
}

.btn.primary {
  background: var(--admin-primary);
  color: var(--admin-primary-text);
}

.btn.ghost {
  border-color: var(--admin-border);
  background: var(--admin-surface);
  color: var(--admin-text);
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.icon-btn {
  width: 34px;
  height: 34px;
  border-color: var(--admin-border);
  background: var(--admin-surface);
  color: var(--admin-muted);
}

.icon-btn.never-hover-class-placeholder {
  border-color: var(--admin-primary);
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
}

.icon-btn.danger {
  color: var(--admin-danger-text);
}

.icon-btn.danger.never-hover-class-placeholder {
  border-color: var(--admin-danger);
  background: var(--admin-danger-hover);
  color: var(--admin-danger-hover-text);
}

.notice {
  border-radius: var(--admin-radius);
  font-size: 14px;
  font-weight: 400;
  padding: 12px 14px;
}

.notice.success {
  background: var(--admin-success-soft);
  color: var(--admin-success-text);
}

.notice.error {
  background: var(--admin-danger-soft);
  color: var(--admin-danger-text);
}

.state-box {
  display: flex;
  min-height: 240px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--admin-faint);
}

.spinner {
  width: 34px;
  height: 34px;
  border: 3px solid var(--admin-primary-soft);
  border-top-color: var(--admin-primary);
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
  border-bottom: 1px solid var(--admin-border-soft);
  padding: 14px 16px;
  text-align: left;
  vertical-align: middle;
}

th {
  background: var(--admin-surface);
  color: var(--admin-muted);
  font-size: 12px;
  font-weight: 400;
  text-transform: uppercase;
}

tbody tr.never-hover-class-placeholder {
  background: var(--admin-hover);
}

.center {
  text-align: center;
}

.right {
  text-align: right;
}

.banner-cell {
  display: flex;
  min-width: 320px;
  align-items: center;
  gap: 12px;
}

.banner-thumb {
  display: flex;
  width: 104px;
  height: 58px;
  flex: 0 0 104px;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
  font-size: 12px;
  font-weight: 400;
}

.banner-thumb img,
.preview img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.banner-main {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 4px;
}

.banner-title {
  color: var(--admin-text);
  font-weight: 400;
}

.banner-main a,
.muted {
  color: var(--admin-faint);
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
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
}

.status.active {
  color: var(--admin-success-text);
}

.status.inactive {
  color: var(--admin-danger-text);
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
  color: var(--admin-muted);
  font-size: 13px;
  font-weight: 400;
  padding: 12px 16px;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  background: color-mix(in srgb, var(--admin-bg) 62%, transparent);
  backdrop-filter: blur(8px);
  padding: 20px;
}

.modal {
  display: flex;
  width: min(720px, 100%);
  max-height: 92vh;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-card-bg);
  box-shadow: var(--admin-shadow-lg);
}

.modal-header,
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border-bottom: 1px solid var(--admin-border);
  background: var(--admin-surface-muted);
  padding: 14px 18px;
}

.modal-footer {
  justify-content: flex-end;
  border-top: 1px solid var(--admin-border);
  border-bottom: 0;
}

.modal-header h3 {
  margin: 0;
  color: var(--admin-text);
  font-size: 18px;
}

.modal-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow-y: auto;
  padding: 18px;
}

.preview {
  height: 180px;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
}

.toggle-row {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--admin-text);
  font-weight: 400;
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

.profile-section-card.banners-main-content {
  background: var(--admin-surface, #ffffff);
  border: none !important;
  border-radius: 0;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.profile-section-card.banners-main-content :is(.banner-table, .toolbar, .card, .state-box, .admin-banners-page, .saas-table-container) {
  border: none !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  padding: 0 !important;
  margin-bottom: 0 !important;
}
</style>

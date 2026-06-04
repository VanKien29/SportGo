<template>
  <section class="banner-management">
    <div class="toolbar">
      <div>
        <h2>Quản lý Banner</h2>
        <p>Tạo và quản lý các banner quảng cáo trên hệ thống.</p>
      </div>
      <button class="btn sg-primary" @click="openCreateModal">
        Thêm banner mới
      </button>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <div class="banners-section">
      <div v-if="loading" class="loading-state">Đang tải dữ liệu...</div>
      <div v-else-if="banners.length === 0" class="empty-state">
        Chưa có banner nào. <button class="btn-text" @click="openCreateModal">Tạo ngay</button>
      </div>
      
      <div v-else class="banners-grid">
        <div v-for="banner in banners" :key="banner.id" class="banner-card">
          <div class="banner-image">
            <img v-if="banner.image_path" :src="'/storage/' + banner.image_path" :alt="banner.title" />
            <div v-else class="image-placeholder">Chưa có ảnh</div>
          </div>
          
          <div class="banner-info">
            <div class="banner-header">
              <h3>{{ banner.title }}</h3>
              <span :class="['status-badge', banner.is_active ? 'active' : 'inactive']">
                {{ banner.is_active ? 'Hoạt động' : 'Tạm ngưng' }}
              </span>
            </div>
            
            <div class="banner-meta">
              <p><strong>Vị trí:</strong> {{ formatPosition(banner.position) }}</p>
              <p><strong>Thời hạn:</strong> {{ formatDate(banner.starts_at) }} - {{ formatDate(banner.ends_at) }}</p>
              <p v-if="banner.link_url"><strong>Link:</strong> <a :href="banner.link_url" target="_blank">{{ banner.link_url }}</a></p>
              <p><strong>Thứ tự:</strong> {{ banner.sort_order }}</p>
            </div>

            <div class="banner-actions">
              <button class="btn btn-sm primary" @click="openEditModal(banner)">Chỉnh sửa</button>
              <button class="btn btn-sm danger" @click="deleteBanner(banner.id)">Xóa</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Create/Edit -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal large">
        <div class="modal-header">
          <h3>{{ isEditing ? 'Chỉnh sửa banner' : 'Thêm banner mới' }}</h3>
          <button class="btn-close" @click="closeModal">&times;</button>
        </div>
        <form @submit.prevent="saveBanner">
          <div class="form-grid">
            <div class="form-group full-width">
              <label>Tiêu đề *</label>
              <input v-model="form.title" type="text" placeholder="Nhập tiêu đề banner" required />
            </div>

            <div class="form-group full-width">
              <label>Ảnh banner *</label>
              <div class="file-upload">
                <input 
                  ref="imageInput"
                  type="file" 
                  accept="image/*" 
                  @change="onImageSelected"
                  :required="!isEditing"
                />
                <div v-if="imagePreview" class="image-preview">
                  <img :src="imagePreview" :alt="form.title" />
                  <button type="button" class="btn-remove" @click="removeImage">Xóa</button>
                </div>
                <p class="hint">Dung lượng tối đa: 5MB. Định dạng: JPEG, PNG, GIF</p>
              </div>
            </div>

            <div class="form-group full-width">
              <label>Liên kết (URL)</label>
              <input v-model="form.link_url" type="url" placeholder="https://..." />
            </div>

            <div class="form-group">
              <label>Vị trí hiển thị *</label>
              <select v-model="form.position" required>
                <option value="">-- Chọn vị trí --</option>
                <option value="homepage_top">Trang chủ - Phía trên</option>
                <option value="homepage_middle">Trang chủ - Giữa</option>
                <option value="homepage_bottom">Trang chủ - Phía dưới</option>
                <option value="category_page">Trang danh mục</option>
                <option value="venue_detail">Chi tiết sân</option>
              </select>
            </div>

            <div class="form-group">
              <label>Thứ tự hiển thị</label>
              <input v-model.number="form.sort_order" type="number" min="0" />
            </div>

            <div class="form-group">
              <label>Ngày bắt đầu *</label>
              <input v-model="form.starts_at" type="datetime-local" required />
            </div>

            <div class="form-group">
              <label>Ngày kết thúc *</label>
              <input v-model="form.ends_at" type="datetime-local" required />
            </div>

            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="form.is_active" />
                Kích hoạt ngay
              </label>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn secondary" @click="closeModal">Hủy</button>
            <button type="submit" class="btn sg-primary" :disabled="saving">
              {{ saving ? 'Đang lưu...' : (isEditing ? 'Cập nhật' : 'Tạo mới') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>

<script>
import { api, apiFormData } from '../../services/api.js';

export default {
  name: 'BannerManagement',
  data() {
    return {
      banners: [],
      loading: false,
      saving: false,
      showModal: false,
      isEditing: false,
      error: '',
      success: '',
      imagePreview: null,
      form: {
        id: null,
        title: '',
        image: null,
        link_url: '',
        position: '',
        sort_order: 0,
        starts_at: '',
        ends_at: '',
        is_active: true,
      }
    };
  },
  mounted() {
    this.loadBanners();
  },
  methods: {
    async loadBanners() {
      this.loading = true;
      try {
        const response = await api('/api/admin/banners');
        this.banners = response.data?.data ?? response.data ?? [];
      } catch (e) {
        this.error = 'Lỗi khi tải danh sách banner: ' + e.message;
      } finally {
        this.loading = false;
      }
    },
    openCreateModal() {
      this.isEditing = false;
      this.form = {
        id: null,
        title: '',
        image: null,
        link_url: '',
        position: '',
        sort_order: this.banners.length,
        starts_at: this.getDateTime(new Date()),
        ends_at: this.getDateTime(new Date(Date.now() + 7*24*60*60*1000)),
        is_active: true,
      };
      this.imagePreview = null;
      this.showModal = true;
    },
    openEditModal(banner) {
      this.isEditing = true;
      this.form = {
        id: banner.id,
        title: banner.title,
        image: null,
        link_url: banner.link_url || '',
        position: banner.position,
        sort_order: banner.sort_order,
        starts_at: this.toDateTimeLocal(banner.starts_at),
        ends_at: this.toDateTimeLocal(banner.ends_at),
        is_active: banner.is_active,
      };
      this.imagePreview = banner.image_path ? '/storage/' + banner.image_path : null;
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
      this.imagePreview = null;
      this.$refs.imageInput?.reset?.();
    },
    onImageSelected(event) {
      const file = event.target.files[0];
      if (file) {
        this.form.image = file;
        const reader = new FileReader();
        reader.onload = e => {
          this.imagePreview = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    },
    removeImage() {
      this.form.image = null;
      this.imagePreview = null;
    },
    async saveBanner() {
      this.saving = true;
      this.error = '';
      this.success = '';
      try {
        const formData = new FormData();
        formData.append('title', this.form.title);
        if (this.form.image) formData.append('image', this.form.image);
        formData.append('link_url', this.form.link_url);
        formData.append('position', this.form.position);
        formData.append('sort_order', this.form.sort_order);
        formData.append('starts_at', this.form.starts_at);
        formData.append('ends_at', this.form.ends_at);
        formData.append('is_active', this.form.is_active ? '1' : '0');

        let data;
        if (this.isEditing) {
          data = await apiFormData(`/api/admin/banners/${this.form.id}`, formData, { method: 'PATCH' });
        } else {
          data = await apiFormData('/api/admin/banners', formData);
        }

        this.success = data.message || 'Lưu banner thành công!';
        this.closeModal();
        this.loadBanners();
      } catch (e) {
        this.error = e.message || 'Lỗi khi lưu banner';
      } finally {
        this.saving = false;
      }
    },
    async deleteBanner(id) {
      if (confirm('Bạn có chắc chắn muốn xóa banner này?')) {
        try {
          const response = await api(`/api/admin/banners/${id}`, { method: 'DELETE' });
          this.success = 'Xóa banner thành công!';
          this.loadBanners();
        } catch (e) {
          this.error = 'Lỗi khi xóa banner: ' + e.message;
        }
      }
    },
    formatPosition(position) {
      const map = {
        'homepage_top': 'Trang chủ - Phía trên',
        'homepage_middle': 'Trang chủ - Giữa',
        'homepage_bottom': 'Trang chủ - Phía dưới',
        'category_page': 'Trang danh mục',
        'venue_detail': 'Chi tiết sân',
      };
      return map[position] || position;
    },
    formatDate(date) {
      return new Date(date).toLocaleDateString('vi-VN');
    },
    getDateTime(date) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');
      return `${year}-${month}-${day}T${hours}:${minutes}`;
    },
    toDateTimeLocal(value) {
      if (!value) return '';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '';
      return this.getDateTime(date);
    }
  }
};
</script>

<style scoped>
.banner-management {
  padding: 2rem;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
  gap: 2rem;
}

.toolbar h2 {
  margin: 0;
  font-size: 1.75rem;
  color: #333;
}

.toolbar p {
  margin: 0.5rem 0 0 0;
  color: #666;
}

.alert {
  padding: 1rem;
  border-radius: 4px;
  margin-bottom: 1rem;
}

.alert.error {
  background: #fee;
  color: #c33;
  border: 1px solid #fcc;
}

.alert.success {
  background: #efe;
  color: #3c3;
  border: 1px solid #cfc;
}

.banners-section {
  margin-top: 2rem;
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 2rem;
  background: #f5f5f5;
  border-radius: 4px;
  color: #666;
}

.banners-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.banner-card {
  border: 1px solid #ddd;
  border-radius: 8px;
  overflow: hidden;
  background: white;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  transition: box-shadow 0.3s;
}

.banner-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.banner-image {
  width: 100%;
  height: 180px;
  background: #f0f0f0;
  overflow: hidden;
}

.banner-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.image-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  background: #e0e0e0;
  color: #999;
}

.banner-info {
  padding: 1rem;
}

.banner-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.banner-header h3 {
  margin: 0;
  font-size: 1rem;
  flex: 1;
}

.status-badge {
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
  border-radius: 3px;
  font-weight: bold;
}

.status-badge.active {
  background: #e8f5e9;
  color: #2e7d32;
}

.status-badge.inactive {
  background: #ffebee;
  color: #c62828;
}

.banner-meta {
  font-size: 0.85rem;
  margin: 0.5rem 0;
  color: #666;
}

.banner-meta p {
  margin: 0.3rem 0;
}

.banner-meta a {
  color: #1976d2;
  text-decoration: none;
}

.banner-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
}

.btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.2s;
}

.btn-sm {
  padding: 0.4rem 0.8rem;
  font-size: 0.85rem;
  flex: 1;
}

.btn.primary {
  background: #1976d2;
  color: white;
}

.btn.primary:hover {
  background: #1565c0;
}

.btn.danger {
  background: #d32f2f;
  color: white;
}

.btn.danger:hover {
  background: #c62828;
}

.btn.sg-primary {
  background: #2196f3;
  color: white;
  padding: 0.7rem 1.5rem;
}

.btn.sg-primary:hover:not(:disabled) {
  background: #1976d2;
}

.btn.sg-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  border-radius: 8px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
  max-width: 600px;
  width: 90%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.modal.large {
  max-width: 700px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #eee;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.3rem;
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #999;
}

.modal-footer {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid #eee;
  justify-content: flex-end;
}

form {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #333;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-family: inherit;
  font-size: 0.95rem;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  outline: none;
  border-color: #1976d2;
  box-shadow: 0 0 0 2px rgba(25, 118, 210, 0.1);
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.checkbox-label input {
  width: auto;
}

.file-upload {
  border: 2px dashed #ddd;
  border-radius: 4px;
  padding: 1rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
}

.file-upload:hover {
  border-color: #1976d2;
  background: #f5f9ff;
}

.file-upload input[type="file"] {
  width: 100%;
  cursor: pointer;
}

.image-preview {
  position: relative;
  margin-top: 1rem;
}

.image-preview img {
  max-width: 100%;
  max-height: 200px;
  border-radius: 4px;
}

.btn-remove {
  position: absolute;
  top: 0;
  right: 0;
  background: #d32f2f;
  color: white;
  border: none;
  padding: 0.3rem 0.6rem;
  border-radius: 0 4px 0 4px;
  cursor: pointer;
  font-size: 0.85rem;
}

.hint {
  font-size: 0.85rem;
  color: #999;
  margin-top: 0.5rem;
}
</style>

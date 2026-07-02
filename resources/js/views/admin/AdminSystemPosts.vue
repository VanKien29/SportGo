<template>
  <div class="admin-system-posts-page">
    <div class="page-header">
      <h2>Quản lý Tin tức hệ thống</h2>
      <p class="subtitle">Thêm và cập nhật các thông báo, sự kiện, hướng dẫn cho Khách hàng</p>
    </div>

    <div class="toolbar card">
      <div class="filters">
        <label class="field compact">
          <span>Tìm kiếm</span>
          <input
            v-model="filters.keyword"
            type="search"
            placeholder="Tiêu đề bài viết..."
            @input="onFilterChange"
          />
        </label>
        <label class="field compact">
          <span>Danh mục</span>
          <select v-model="filters.category" @change="loadPosts(1)">
            <option value="">Tất cả danh mục</option>
            <option value="announcement">Thông báo</option>
            <option value="guide">Hướng dẫn</option>
            <option value="news">Tin tức</option>
            <option value="event">Sự kiện</option>
          </select>
        </label>
        <label class="field compact">
          <span>Trạng thái</span>
          <select v-model="filters.status" @change="loadPosts(1)">
            <option value="">Tất cả trạng thái</option>
            <option value="published">Đã xuất bản</option>
            <option value="draft">Bản nháp</option>
            <option value="hidden">Đã ẩn</option>
          </select>
        </label>
      </div>
    </div>

    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải bài viết...</p>
    </div>

    <div v-else-if="posts.length === 0" class="state-box card">
      <p>Chưa có bài viết nào phù hợp.</p>
    </div>

    <div v-else class="post-table card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Bài viết</th>
              <th>Danh mục</th>
              <th>Trạng thái</th>
              <th class="center">Lượt xem</th>
              <th>Ngày đăng</th>
              <th class="right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="post in posts" :key="post.id">
              <td>
                <div class="post-cell">
                  <div class="post-thumb">
                    <img v-if="post.thumbnail_path" :src="post.thumbnail_path" :alt="post.title" />
                    <span v-else>Ảnh</span>
                  </div>
                  <div class="post-main">
                    <div class="post-title">{{ post.title }}</div>
                    <div class="post-desc muted">{{ post.short_description }}</div>
                  </div>
                </div>
              </td>
              <td>{{ getCategoryName(post.category) }}</td>
              <td>
                <span class="status" :class="post.status">
                  {{ getStatusName(post.status) }}
                </span>
              </td>
              <td class="center">{{ post.view_count || 0 }}</td>
              <td>
                <div v-if="post.published_at">{{ formatDate(post.published_at) }}</div>
                <div v-else class="muted">-</div>
              </td>
              <td class="right">
                <div class="actions">
                  <button class="icon-btn" type="button" title="Chỉnh sửa" @click="openEditModal(post)">
                    <AppIcon name="pencil" size="16" />
                  </button>
                  <button class="icon-btn danger" type="button" title="Xóa" @click="deletePost(post)">
                    <AppIcon name="trash" size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.last_page > 1" class="pagination">
        <button class="icon-btn" type="button" :disabled="pagination.current_page <= 1" @click="loadPosts(pagination.current_page - 1)">
          <AppIcon name="chevronLeft" size="17" />
        </button>
        <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button class="icon-btn" type="button" :disabled="pagination.current_page >= pagination.last_page" @click="loadPosts(pagination.current_page + 1)">
          <AppIcon name="chevronRight" size="17" />
        </button>
      </div>
    </div>

    <div v-if="modal.open" class="modal-backdrop" @click.self="closeModal">
      <div class="modal" style="max-width: 900px; width: 900px; padding: 0;">
        <div class="modal-header" style="padding: 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
          <h3 style="margin: 0; font-size: 20px; font-weight: 800; display: flex; align-items: center;">
            <AppIcon name="edit" size="20" style="color: #10b981; margin-right: 8px;" />
            {{ modal.mode === 'edit' ? 'Chỉnh sửa bài viết' : 'Thêm bài viết mới' }}
          </h3>
          <button class="icon-btn close-btn" type="button" @click="closeModal" style="border: none; background: transparent; cursor: pointer;">
            <AppIcon name="x" size="20" />
          </button>
        </div>

        <form @submit.prevent="savePost">
          <div class="modal-body" style="padding: 24px; display: flex; flex-direction: column; gap: 0; max-height: 70vh; overflow-y: auto;">
            
            <div style="display: flex; flex-direction: row; gap: 24px;">
              <!-- Left Form -->
              <div style="flex: 2; min-width: 0; display: flex; flex-direction: column; gap: 16px;">
                <label class="field" style="display: flex; flex-direction: column; gap: 6px;">
                  <span style="font-size: 13px; font-weight: 700; color: #475569;">Tiêu đề bài viết <span style="color: #ef4444;">*</span></span>
                  <input v-model.trim="form.title" type="text" maxlength="255" required style="padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px;" placeholder="Tiêu đề ấn tượng (5-200 ký tự)" />
                </label>

                <label class="field" style="display: flex; flex-direction: column; gap: 6px;">
                  <span style="font-size: 13px; font-weight: 700; color: #475569;">Mô tả ngắn <span style="color: #ef4444;">*</span></span>
                  <textarea v-model.trim="form.short_description" rows="2" maxlength="500" required style="padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit;" placeholder="Tóm tắt nội dung hấp dẫn người đọc..."></textarea>
                </label>

                <div class="field" style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
                  <span style="font-size: 13px; font-weight: 700; color: #475569;">Nội dung chi tiết <span style="color: #ef4444;">*</span></span>
                  <div style="border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; flex: 1; min-height: 350px;">
                    <RichTextEditor v-model="form.content" placeholder="Viết nội dung bài viết..." style="min-height: 350px;" />
                  </div>
                </div>
              </div>

              <!-- Right Sidebar -->
              <div style="flex: 1; display: flex; flex-direction: column; gap: 16px; background: #f8fafc; padding: 16px; border-radius: 16px;">
                <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
                  <span style="font-size: 13px; font-weight: 700; color: #475569;">Ảnh đại diện (Thumbnail)</span>
                  <div class="upload-zone" style="aspect-ratio: 16/10; border: 2px dashed #cbd5e1; border-radius: 12px; position: relative; cursor: pointer; overflow: hidden; background: white;" @click="!imagePreview && $refs.imageInput.click()">
                    <div v-if="imagePreview" style="position: absolute; inset: 0;">
                      <img :src="imagePreview" style="width: 100%; height: 100%; object-fit: cover;" />
                      <button type="button" @click.stop="clearThumbnail" style="position: absolute; top: 8px; right: 8px; background: rgba(239,68,68,0.9); color: white; border: none; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10;">
                        <AppIcon name="trash" size="14" />
                      </button>
                    </div>
                    <div v-else style="position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: center; align-items: center; color: #94a3b8;">
                      <AppIcon name="upload" size="24" style="margin-bottom: 8px;" />
                      <span style="font-size: 13px; font-weight: 700;">Tải ảnh lên</span>
                    </div>
                    <input type="file" ref="imageInput" style="display: none;" @change="onImageSelected" accept="image/jpeg,image/png,image/gif,image/webp" />
                  </div>
                </div>

                <label class="field compact" style="display: flex; flex-direction: column; gap: 6px;">
                  <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #94a3b8;">Danh mục <span style="color: #ef4444;">*</span></span>
                  <CustomSelect v-model="form.category" :options="categoryOptions" placeholder="-- Chọn danh mục --" />
                </label>

                <label class="field compact" style="display: flex; flex-direction: column; gap: 6px;">
                  <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #94a3b8;">Trạng thái <span style="color: #ef4444;">*</span></span>
                  <CustomSelect v-model="form.status" :options="statusOptions" placeholder="-- Chọn trạng thái --" />
                </label>
              </div>
            </div>

          </div>

          <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc;">
            <button class="btn ghost" type="button" @click="closeModal" style="padding: 10px 20px;">Hủy bỏ</button>
            <button class="btn primary" type="submit" :disabled="saving" style="background: #0f172a; color: white; padding: 10px 20px;">
              <AppIcon v-if="!saving" name="send" size="16" style="margin-right: 8px;" />
              <span>{{ saving ? 'Đang lưu...' : 'Lưu bài viết' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Floating Add Button -->
    <div class="floating-add-container">
      <button class="btn-float-add" @click="openCreateModal">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Thêm bài viết</span>
      </button>
    </div>
  </div>
</template>

<script>
import { api } from '../../services/api.js';
import AppIcon from '../../components/AppIcon.vue';
import RichTextEditor from '../../components/RichTextEditor.vue';
import CustomSelect from '../../components/CustomSelect.vue';

export default {
  name: 'AdminSystemPosts',
  components: { AppIcon, RichTextEditor, CustomSelect },
  data() {
    return {
      posts: [],
      loading: true,
      saving: false,
      filters: {
        keyword: '',
        category: '',
        status: ''
      },
      pagination: {
        current_page: 1,
        last_page: 1
      },
      filterTimeout: null,
      
      modal: {
        open: false,
        mode: 'create'
      },
      form: {
        id: null,
        title: '',
        short_description: '',
        category: 'announcement',
        status: 'published'
      },
      imageFile: null,
      imagePreview: null,
      categoryOptions: [
        { value: 'announcement', label: 'Thông báo' },
        { value: 'guide', label: 'Hướng dẫn' },
        { value: 'news', label: 'Tin tức' },
        { value: 'event', label: 'Sự kiện' }
      ],
      statusOptions: [
        { value: 'published', label: 'Đã xuất bản (Hiện cho Khách)' },
        { value: 'draft', label: 'Bản nháp' },
        { value: 'hidden', label: 'Đã ẩn' }
      ]
    };
  },
  created() {
    this.loadPosts();
  },
  methods: {
    async loadPosts(page = 1) {
      this.loading = true;
      try {
        const params = new URLSearchParams({
          page,
          per_page: 15,
          ...this.filters
        });
        const res = await api(`/api/admin/system-posts?${params}`);
        this.posts = res.data;
        this.pagination = {
          current_page: res.current_page,
          last_page: res.last_page
        };
      } catch (err) {
        console.error(err);
      } finally {
        this.loading = false;
      }
    },
    onFilterChange() {
      clearTimeout(this.filterTimeout);
      this.filterTimeout = setTimeout(() => {
        this.loadPosts(1);
      }, 500);
    },
    openCreateModal() {
      this.modal.mode = 'create';
      this.form = {
        id: null,
        title: '',
        short_description: '',
        category: 'announcement',
        status: 'published',
        content: ''
      };
      this.imageFile = null;
      this.imagePreview = null;
      this.modal.open = true;
    },
    openEditModal(post) {
      this.modal.mode = 'edit';
      this.form = {
        id: post.id,
        title: post.title,
        short_description: post.short_description,
        category: post.category,
        status: post.status,
        content: post.content || ''
      };
      this.imageFile = null;
      this.imagePreview = post.thumbnail_path || null;
      this.modal.open = true;
    },
    closeModal() {
      this.modal.open = false;
    },
    onImageSelected(e) {
      const file = e.target.files[0];
      if (file) {
        this.imageFile = file;
        this.imagePreview = URL.createObjectURL(file);
      }
    },
    clearThumbnail() {
      this.imageFile = null;
      this.imagePreview = null;
      if (this.$refs.imageInput) {
        this.$refs.imageInput.value = '';
      }
    },
    async savePost() {
      this.saving = true;
      try {
        const formData = new FormData();
        formData.append('title', this.form.title);
        formData.append('short_description', this.form.short_description);
        formData.append('category', this.form.category);
        formData.append('status', this.form.status);
        formData.append('content', this.form.content);
        
        if (this.imageFile) {
          formData.append('thumbnail', this.imageFile);
        }

        if (this.modal.mode === 'create') {
          await api('/api/admin/system-posts', {
            method: 'POST',
            body: formData
          });
        } else {
          // Send as POST with _method=PUT to support multipart/form-data
          formData.append('_method', 'PUT');
          await api(`/api/admin/system-posts/${this.form.id}`, {
            method: 'POST',
            body: formData
          });
        }
        
        this.closeModal();
        this.loadPosts(this.pagination.current_page);
      } catch (err) {
        alert('Có lỗi xảy ra: ' + (err.message || 'Vui lòng thử lại.'));
      } finally {
        this.saving = false;
      }
    },
    async deletePost(post) {
      if (!confirm(`Bạn có chắc chắn muốn xóa bài viết "${post.title}"?`)) return;
      try {
        await api(`/api/admin/system-posts/${post.id}`, { method: 'DELETE' });
        this.loadPosts(this.pagination.current_page);
      } catch (err) {
        alert('Xóa thất bại: ' + err.message);
      }
    },
    getCategoryName(cat) {
      const cats = {
        announcement: 'Thông báo',
        guide: 'Hướng dẫn',
        news: 'Tin tức',
        event: 'Sự kiện'
      };
      return cats[cat] || cat;
    },
    getStatusName(status) {
      const st = {
        published: 'Đã xuất bản',
        draft: 'Bản nháp',
        hidden: 'Đã ẩn'
      };
      return st[status] || status;
    },
    formatDate(dateStr) {
      if (!dateStr) return '';
      const d = new Date(dateStr);
      return `${d.getDate().toString().padStart(2, '0')}/${(d.getMonth() + 1).toString().padStart(2, '0')}/${d.getFullYear()} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
    }
  }
};
</script>

<style scoped>
.admin-system-posts-page {
  padding: 24px;
}
.page-header {
  margin-bottom: 24px;
}
.page-header h2 {
  font-size: 24px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 4px;
}
.subtitle {
  color: #64748b;
  font-size: 14px;
}
.card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid rgba(0,0,0,0.05);
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
  margin-bottom: 20px;
}
.toolbar {
  padding: 16px;
}
.filters {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.field span {
  font-size: 13px;
  font-weight: 500;
  color: #475569;
}
.field input, .field select, .field textarea {
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
}
.field input:focus, .field select:focus, .field textarea:focus {
  border-color: #3b82f6;
}
.field.compact input, .field.compact select {
  min-width: 200px;
}
.field.full {
  width: 100%;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 15px;
}
.table-scroll {
  overflow-x: auto;
}
table {
  width: 100%;
  border-collapse: collapse;
}
th, td {
  padding: 16px;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
}
th {
  background: #f8fafc;
  font-weight: 600;
  font-size: 13px;
  color: #475569;
  text-transform: uppercase;
}
.center { text-align: center; }
.right { text-align: right; }
.post-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}
.post-thumb {
  width: 60px;
  height: 45px;
  border-radius: 6px;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  color: #94a3b8;
  font-size: 12px;
  flex-shrink: 0;
}
.post-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.post-main {
  max-width: 300px;
}
.post-title {
  font-weight: 600;
  color: #0f172a;
  font-size: 14px;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.post-desc {
  font-size: 13px;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.status {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 500;
}
.status.published {
  background: #dcfce7;
  color: #166534;
}
.status.draft {
  background: #f1f5f9;
  color: #475569;
}
.status.hidden {
  background: #fee2e2;
  color: #991b1b;
}
.muted {
  color: #64748b;
  font-size: 13px;
}
.actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}
.icon-btn {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
}
.icon-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}
.icon-btn.danger:hover {
  background: #fee2e2;
  border-color: #fca5a5;
  color: #ef4444;
}
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 16px;
  border-top: 1px solid #f1f5f9;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}
.modal {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 500px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.modal.large {
  max-width: 800px;
}
.modal-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.modal-header h3 {
  font-size: 18px;
  font-weight: 600;
  margin: 0;
}
.modal-body {
  padding: 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 15px;
}
.modal-footer {
  padding: 16px 20px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
}
.btn {
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  border: none;
}
.btn.ghost {
  background: transparent;
  color: #475569;
}
.btn.ghost:hover {
  background: #f1f5f9;
}
.btn.primary {
  background: #3b82f6;
  color: #fff;
}
.btn.primary:hover {
  background: #2563eb;
}
.btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
.preview {
  margin-top: 10px;
  max-width: 200px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}
.preview img {
  width: 100%;
  display: block;
}
.floating-add-container {
  position: fixed;
  bottom: 30px;
  right: 30px;
  z-index: 100;
}
.btn-float-add {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 20px;
  background: #0f172a;
  color: #fff;
  border-radius: 999px;
  border: none;
  font-weight: 600;
  font-size: 15px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.3);
  transition: all 0.2s;
}
.btn-float-add:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.4);
}
.quill-wrapper {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  overflow: hidden;
}
.quill-wrapper :deep(.ql-container) {
  min-height: 250px;
  font-size: 15px;
  font-family: inherit;
}
</style>

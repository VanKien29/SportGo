<template>
  <div class="cluster-profile-surface standalone">
    <div class="profile-section-card system-posts-main-content">
      <section class="admin-system-posts-page">
        <header class="page-header">
          <div>
            <h2>Quản lý Tin tức hệ thống</h2>
            <p class="subtitle">Thêm và cập nhật các thông báo, sự kiện, hướng dẫn cho Khách hàng</p>
          </div>
        </header>

        <SaaSFilterBar
          v-model="filters.status"
          v-model:search="filters.keyword"
          :tabs="statusTabsUi"
          search-id="search-system-posts"
          search-placeholder="Tiêu đề bài viết..."
          @update:search="onFilterChange"
          @update:modelValue="loadPosts(1)"
        >
          <template #actions>
            <select v-model="filters.category" @change="loadPosts(1)" class="filter-select">
              <option value="">Tất cả danh mục</option>
              <option value="announcement">Thông báo</option>
              <option value="guide">Hướng dẫn</option>
              <option value="news">Tin tức</option>
              <option value="event">Sự kiện</option>
            </select>
            <button class="btn primary" type="button" @click="openCreateModal" style="background: var(--admin-primary); color: #fff; display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border-radius: 8px; border: none; font-size: 13px; font-weight: 500; cursor: pointer;">
              <AppIcon name="plus" size="16" />
              <span>Viết bài mới</span>
            </button>
          </template>
        </SaaSFilterBar>

    <div v-if="loading" class="state-box animate-fade-in">
      <div class="spinner"></div>
      <p>Đang tải bài viết...</p>
    </div>

    <div v-else-if="posts.length === 0" class="state-box animate-fade-in">
      <p>Chưa có bài viết nào phù hợp.</p>
    </div>

    <div v-else class="post-table card">
      <SaaSTable
        :columns="tableColumns"
        :data="posts"
      >
        <template #title="{ row }">
          <div class="post-cell">
            <div class="post-thumb">
              <img v-if="row.thumbnail_path" :src="row.thumbnail_path" :alt="row.title" />
              <span v-else>Ảnh</span>
            </div>
            <div class="post-main">
              <div class="post-title">{{ row.title }}</div>
              <div class="post-desc muted">{{ row.short_description }}</div>
            </div>
          </div>
        </template>

        <template #category="{ row }">
          {{ getCategoryName(row.category) }}
        </template>

        <template #status="{ row }">
          <span class="status" :class="row.status">
            {{ getStatusName(row.status) }}
          </span>
        </template>

        <template #views="{ row }">
          {{ row.view_count || 0 }}
        </template>

        <template #published_at="{ row }">
          <div v-if="row.published_at">{{ formatDate(row.published_at) }}</div>
          <div v-else class="muted">-</div>
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
              @click="deletePost(row)"
            />
          </TableActionGroup>
        </template>
      </SaaSTable>

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
      <div class="modal modal-post-editor">
        <div class="modal-header">
          <h3 class="modal-title">
            <AppIcon name="edit" size="20" class="modal-title-icon" />
            {{ modal.mode === 'edit' ? 'Chỉnh sửa bài viết' : 'Thêm bài viết mới' }}
          </h3>
          <button class="icon-btn close-btn" type="button" @click="closeModal">
            <AppIcon name="x" size="20" />
          </button>
        </div>

        <form @submit.prevent="savePost">
          <div class="modal-body">
            
            <div class="modal-layout">
              <!-- Left Form -->
              <div class="modal-main-column">
                <label class="field">
                  <span class="field-label">Tiêu đề bài viết <span class="required-mark">*</span></span>
                  <input v-model.trim="form.title" type="text" maxlength="255" required placeholder="Tiêu đề ấn tượng (5-200 ký tự)" />
                </label>

                <label class="field">
                  <span class="field-label">Mô tả ngắn <span class="required-mark">*</span></span>
                  <textarea v-model.trim="form.short_description" rows="2" maxlength="500" required placeholder="Tóm tắt nội dung hấp dẫn người đọc..."></textarea>
                </label>

                <div class="field editor-field">
                  <span class="field-label">Nội dung chi tiết <span class="required-mark">*</span></span>
                  <div class="rich-editor-frame">
                    <RichTextEditor v-model="form.content" placeholder="Viết nội dung bài viết..." class="post-editor" />
                  </div>
                </div>
              </div>

              <!-- Right Sidebar -->
              <div class="modal-sidebar">
                <div class="field">
                  <span class="field-label">Ảnh đại diện (Thumbnail)</span>
                  <div class="upload-zone" @click="!imagePreview && $refs.imageInput.click()">
                    <div v-if="imagePreview" class="upload-preview">
                      <img :src="imagePreview" class="upload-preview-img" />
                      <button type="button" class="upload-clear-btn" @click.stop="clearThumbnail">
                        <AppIcon name="trash" size="14" />
                      </button>
                    </div>
                    <div v-else class="upload-empty">
                      <AppIcon name="upload" size="24" class="upload-icon" />
                      <span class="upload-label">Tải ảnh lên</span>
                    </div>
                    <input type="file" ref="imageInput" class="sr-only-input" @change="onImageSelected" accept="image/jpeg,image/png,image/gif,image/webp" />
                  </div>
                </div>

                <label class="field compact">
                  <span class="field-label field-label-compact">Danh mục <span class="required-mark">*</span></span>
                  <CustomSelect v-model="form.category" :options="categoryOptions" placeholder="-- Chọn danh mục --" />
                </label>

                <label class="field compact">
                  <span class="field-label field-label-compact">Trạng thái <span class="required-mark">*</span></span>
                  <CustomSelect v-model="form.status" :options="statusOptions" placeholder="-- Chọn trạng thái --" />
                </label>
              </div>
            </div>

          </div>

          <div class="modal-footer">
            <button class="btn ghost" type="button" @click="closeModal">Hủy bỏ</button>
            <button class="btn primary" type="submit" :disabled="saving">
              <AppIcon v-if="!saving" name="send" size="16" class="btn-icon" />
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
      </section>
    </div>
  </div>
</template>

<script>
import { api } from '../../services/api.js';
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import SaaSFilterBar from '../../components/ui/SaaSFilterBar.vue';
import SaaSTable from '../../components/ui/SaaSTable.vue';
import RichTextEditor from '../../components/RichTextEditor.vue';
import CustomSelect from '../../components/CustomSelect.vue';

export default {
  name: 'AdminSystemPosts',
  components: { ActionIconButton, AppIcon, TableActionGroup, SaaSFilterBar, SaaSTable, RichTextEditor, CustomSelect },
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
  computed: {
    statusTabsUi() {
      return [
        { value: '', label: 'Tất cả trạng thái' },
        { value: 'published', label: 'Đã xuất bản' },
        { value: 'draft', label: 'Bản nháp' },
        { value: 'hidden', label: 'Đã ẩn' }
      ];
    },
    tableColumns() {
      return [
        { key: 'title', label: 'BÀI VIẾT' },
        { key: 'category', label: 'DANH MỤC' },
        { key: 'status', label: 'TRẠNG THÁI' },
        { key: 'views', label: 'LƯỢT XEM', align: 'center' },
        { key: 'published_at', label: 'NGÀY ĐĂNG' },
        { key: 'actions', label: 'THAO TÁC', align: 'right' }
      ];
    }
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
  padding: 0;
}

.page-header {
  margin-bottom: 24px;
}

.page-header h2 {
  margin: 0 0 4px;
  color: var(--admin-text);
  font-size: 24px;
  font-weight: 400;
}

.subtitle,
.muted,
.post-desc {
  color: var(--admin-faint);
  font-size: 13px;
}

.card {
  margin-bottom: 20px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-card-bg);
  box-shadow: var(--admin-shadow-card);
}

.toolbar {
  padding: 16px;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label,
.field span {
  color: var(--admin-muted);
  font-size: 13px;
  font-weight: 400;
}

.field-label-compact {
  color: var(--admin-faint);
  font-size: 11px;
  font-weight: 400;
  text-transform: uppercase;
}

.required-mark {
  color: var(--admin-danger);
}

.field input,
.field select,
.field textarea {
  min-height: 40px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-text);
  font: inherit;
  font-size: 14px;
  outline: none;
  padding: 10px 14px;
  transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
}

.field textarea {
  resize: vertical;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  border-color: var(--admin-primary);
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}

.field.compact input,
.field.compact select {
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

th,
td {
  border-bottom: 1px solid var(--admin-border-soft);
  padding: 16px;
  text-align: left;
  vertical-align: middle;
}

th {
  background: var(--admin-surface);
  color: var(--admin-muted);
  font-size: 13px;
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

.post-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.post-thumb {
  display: flex;
  width: 60px;
  height: 45px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
  font-size: 12px;
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
  margin-bottom: 4px;
  overflow: hidden;
  color: var(--admin-text);
  font-size: 14px;
  font-weight: 400;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.post-desc {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status {
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
}

.status.published {
  color: var(--admin-success-text);
}

.status.draft {
  color: var(--admin-blue);
}

.status.hidden {
  color: var(--admin-danger-text);
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.icon-btn {
  display: inline-flex;
  width: 32px;
  height: 32px;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-muted);
  cursor: pointer;
  transition: background-color 180ms ease, border-color 180ms ease, color 180ms ease, transform 180ms ease;
}

.icon-btn.never-hover-class-placeholder {
  border-color: var(--admin-primary);
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
  transform: translateY(-1px);
}

.icon-btn.danger {
  color: var(--admin-danger-text);
}

.icon-btn.danger.never-hover-class-placeholder {
  border-color: var(--admin-danger);
  background: var(--admin-danger-hover);
  color: var(--admin-danger-hover-text);
}

.close-btn {
  border-color: transparent;
  background: transparent;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  border-top: 1px solid var(--admin-border);
  padding: 16px;
  color: var(--admin-muted);
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: color-mix(in srgb, var(--admin-bg) 68%, transparent);
  backdrop-filter: blur(8px);
}

.modal {
  display: flex;
  width: 100%;
  max-width: 500px;
  max-height: 90vh;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-card-bg);
  box-shadow: var(--admin-shadow-lg);
}

.modal-post-editor {
  width: min(900px, calc(100vw - 40px));
  max-width: 900px;
}

.modal.large {
  max-width: 800px;
}

.modal-header,
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--admin-border);
  background: var(--admin-surface-muted);
  padding: 16px 20px;
}

.modal-header h3,
.modal-title {
  display: flex;
  align-items: center;
  margin: 0;
  color: var(--admin-text);
  font-size: 18px;
  font-weight: 400;
}

.modal-title-icon,
.btn-icon {
  margin-right: 8px;
  color: var(--admin-primary-dark);
}

.modal-body {
  display: flex;
  max-height: 70vh;
  flex-direction: column;
  gap: 0;
  overflow-y: auto;
  padding: 24px;
}

.modal-layout {
  display: flex;
  flex-direction: row;
  gap: 24px;
}

.modal-main-column {
  display: flex;
  min-width: 0;
  flex: 2;
  flex-direction: column;
  gap: 16px;
}

.editor-field {
  flex: 1;
}

.rich-editor-frame {
  flex: 1;
  min-height: 350px;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
}

.post-editor {
  min-height: 350px;
}

.modal-sidebar {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 16px;
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface-muted);
  padding: 16px;
}

.upload-zone {
  position: relative;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  border: 2px dashed var(--admin-border);
  border-radius: var(--admin-radius-lg);
  background: var(--admin-surface);
  cursor: pointer;
}

.upload-preview,
.upload-empty {
  position: absolute;
  inset: 0;
}

.upload-preview-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.upload-clear-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 10;
  display: flex;
  width: 32px;
  height: 32px;
  align-items: center;
  justify-content: center;
  border: 1px solid color-mix(in srgb, var(--admin-danger) 42%, transparent);
  border-radius: 999px;
  background: var(--admin-danger-soft);
  color: var(--admin-danger-text);
  cursor: pointer;
}

.upload-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--admin-faint);
}

.upload-icon {
  margin-bottom: 8px;
}

.upload-label {
  font-size: 13px;
  font-weight: 400;
}

.sr-only-input {
  display: none;
}

.modal-footer {
  justify-content: flex-end;
  gap: 12px;
  border-top: 1px solid var(--admin-border);
  border-bottom: 0;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 40px;
  border: 1px solid transparent;
  border-radius: var(--admin-radius);
  padding: 10px 20px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 400;
}

.btn.ghost {
  border-color: var(--admin-border);
  background: var(--admin-surface);
  color: var(--admin-text);
}

.btn.ghost.never-hover-class-placeholder {
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
}

.btn.primary {
  border-color: var(--admin-primary);
  background: var(--admin-primary);
  color: var(--admin-primary-text);
}

.btn.primary.never-hover-class-placeholder {
  background: var(--admin-primary-dark);
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

.preview {
  max-width: 200px;
  margin-top: 10px;
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
}

.preview img {
  display: block;
  width: 100%;
}

.floating-add-container {
  position: fixed;
  right: 30px;
  bottom: 30px;
  z-index: 100;
}

.btn-float-add {
  display: flex;
  align-items: center;
  gap: 8px;
  border: none;
  border-radius: 999px;
  background: var(--admin-primary);
  color: var(--admin-primary-text);
  box-shadow: 0 4px 12px var(--admin-primary-ring);
  cursor: pointer;
  font-size: 15px;
  font-weight: 400;
  padding: 14px 20px;
  transition: transform 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
}

.btn-float-add.never-hover-class-placeholder {
  background: var(--admin-primary-dark);
  box-shadow: 0 6px 16px var(--admin-primary-ring);
  transform: translateY(-2px);
}

.quill-wrapper {
  overflow: hidden;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
}

.quill-wrapper :deep(.ql-container) {
  min-height: 250px;
  font-family: inherit;
  font-size: 15px;
}

@media (max-width: 900px) {
  .modal-layout {
    flex-direction: column;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }
}

.profile-section-card.system-posts-main-content {
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 0;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.profile-section-card.system-posts-main-content :is(.post-table, .toolbar, .card, .state-box, .admin-system-posts-page, .saas-table-container, .table-scroll) {
  border: none !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  padding: 0 !important;
  margin-bottom: 0 !important;
}
</style>

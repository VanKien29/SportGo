<template>
  <div class="posts-page">
    <div style="display: flex; justify-content: flex-end; margin-bottom: 16px;">
      <button class="btn primary" type="button" @click="openCreateModal">
        <AppIcon name="plus" size="16" />
        <span>Tạo bài đăng mới</span>
      </button>
    </div>

    <!-- Alert Notices -->
    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <div class="filter-toolbar card">
      <!-- Tabs -->
      <div class="tabs-header">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          class="tab-btn"
          :class="{ active: activeTab === tab.value }"
          type="button"
          @click="changeTab(tab.value)"
        >
          <AppIcon :name="tab.icon" size="16" />
          <span>{{ tab.label }}</span>
        </button>
      </div>

      <!-- Dropdown filter by cluster -->
      <div class="filters">
        <label class="field compact">
          <span>Lọc theo cụm sân</span>
          <select v-model="filterClusterId" @change="loadPosts(1)">
            <option value="">Tất cả cụm sân</option>
            <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">
              {{ cluster.name }}
            </option>
          </select>
        </label>
      </div>
    </div>

    <!-- Loading Screen -->
    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải danh sách bài viết...</p>
    </div>

    <!-- Empty Screen -->
    <div v-else-if="posts.length === 0" class="state-box card">
      <AppIcon name="fileText" size="36" />
      <p>Không tìm thấy bài viết nào.</p>
    </div>

    <!-- Posts Grid/List -->
    <div v-else class="posts-list">
      <div v-for="post in posts" :key="post.id" class="post-card card">
        <div class="post-header">
          <div class="post-meta-info">
            <span class="cluster-badge">{{ post.venue_cluster?.name }}</span>
            <span class="post-time">{{ formatDate(post.created_at) }}</span>
          </div>
          <span class="status-badge" :class="getStatusClass(post.status)">
            {{ getStatusLabel(post.status) }}
          </span>
        </div>

        <div class="post-body">
          <p class="post-content">{{ post.content }}</p>

          <!-- Hashtags -->
          <div v-if="post.hashtags && post.hashtags.length > 0" class="hashtags-list">
            <span v-for="tag in post.hashtags" :key="tag.id" class="hashtag">
              #{{ tag.name }}
            </span>
          </div>

          <!-- Media Gallery -->
          <div v-if="post.media && post.media.length > 0" class="media-gallery">
            <div
              v-for="med in post.media"
              :key="med.id"
              class="media-item"
              @click="openLightbox(med.file_path)"
            >
              <img :src="formatImageUrl(med.file_path)" alt="post media" />
            </div>
          </div>
        </div>

        <!-- Rejection Reason -->
        <div v-if="post.status === 'rejected' && post.status_reason" class="reason-banner error">
          <AppIcon name="messageWarning" size="16" />
          <span><strong>Lý do từ chối:</strong> {{ post.status_reason }}</span>
        </div>

        <!-- Lock Warning -->
        <div v-if="post.status === 'hidden'" class="reason-banner warning">
          <AppIcon name="lock" size="16" />
          <span>Bài đăng này đã bị khóa/ẩn bởi Quản trị viên và không thể sửa/xóa.</span>
        </div>

        <div class="post-footer">
          <div class="post-stats">
            <span title="Lượt xem"><AppIcon name="eye" size="14" /> {{ post.view_count || 0 }}</span>
            <span title="Lượt thích"><AppIcon name="heart" size="14" /> {{ post.like_count || 0 }}</span>
            <span title="Bình luận"><AppIcon name="messageSquare" size="14" /> {{ post.comment_count || 0 }}</span>
          </div>

          <div class="post-actions" v-if="post.status !== 'hidden'">
            <button class="btn ghost btn-sm" type="button" @click="openEditModal(post)">
              <AppIcon name="edit" size="14" />
              <span>Sửa</span>
            </button>
            <button class="btn ghost danger btn-sm" type="button" @click="confirmDelete(post)">
              <AppIcon name="trash" size="14" />
              <span>Xóa</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="pagination-bar">
        <button
          class="btn ghost btn-sm"
          type="button"
          :disabled="pagination.current_page <= 1"
          @click="loadPosts(pagination.current_page - 1)"
        >
          Trước
        </button>
        <span class="page-info">Trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button
          class="btn ghost btn-sm"
          type="button"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="loadPosts(pagination.current_page + 1)"
        >
          Sau
        </button>
      </div>
    </div>

    <!-- MODAL TẠO / SỬA BÀI ĐĂNG -->
    <div v-if="formModal.open" class="modal-backdrop" @mousedown="handleBackdropMousedown" @click="handleBackdropClick">
      <div class="modal medium" @mousedown.stop>
        <div class="modal-header">
          <h3>{{ formModal.isEdit ? 'Chỉnh sửa bài đăng' : 'Tạo bài đăng mới' }}</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>

        <form @submit.prevent="submitForm">
          <div class="modal-body form-grid">
            <label class="field">
              <span>Cụm sân áp dụng <span class="required">*</span></span>
              <select v-model="form.venue_cluster_id" required :disabled="formModal.isEdit">
                <option value="" disabled>-- Chọn cụm sân --</option>
                <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">
                  {{ cluster.name }}
                </option>
              </select>
            </label>

            <label class="field">
              <span>Nội dung bài viết <span class="required">*</span></span>
              <textarea
                v-model="form.content"
                rows="6"
                placeholder="Nhập nội dung quảng bá, thông báo hoặc giải đấu... Thêm hashtags bằng cách nhập #ten_hashtag"
                required
              ></textarea>
            </label>

            <!-- Existing Images (Edit mode only) -->
            <div v-if="formModal.isEdit && form.existingMedia.length > 0" class="image-uploader-section">
              <span>Hình ảnh hiện tại</span>
              <div class="media-preview-grid">
                <div v-for="med in form.existingMedia" :key="med.id" class="preview-item">
                  <img :src="formatImageUrl(med.file_path)" alt="existing media" />
                  <button
                    class="remove-btn"
                    type="button"
                    title="Xóa hình này"
                    @click="markMediaForDeletion(med.id)"
                  >
                    <AppIcon name="x" size="12" />
                  </button>
                </div>
              </div>
            </div>

            <!-- New Image Uploads -->
            <div class="image-uploader-section">
              <span>Tải lên hình ảnh mới (Tối đa 5MB/ảnh)</span>
              <div class="upload-area" @click="triggerFileInput">
                <AppIcon name="image" size="24" class="muted" />
                <p>Click để chọn hình ảnh đính kèm bài viết</p>
                <input
                  ref="fileInput"
                  type="file"
                  multiple
                  accept="image/*"
                  class="hidden-file-input"
                  @change="handleFileSelect"
                />
              </div>

              <!-- New files preview -->
              <div v-if="form.newImages.length > 0" class="media-preview-grid">
                <div v-for="(img, idx) in form.newImages" :key="idx" class="preview-item">
                  <img :src="img.previewUrl" alt="new upload preview" />
                  <button
                    class="remove-btn"
                    type="button"
                    title="Hủy chọn"
                    @click="removeNewImage(idx)"
                  >
                    <AppIcon name="x" size="12" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn ghost" type="button" @click="closeModal" :disabled="saving">Hủy</button>
            <button class="btn primary" type="submit" :disabled="saving || !form.venue_cluster_id || !form.content">
              <span>{{ saving ? 'Đang lưu...' : 'Lưu lại' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- LIGHTBOX XEM ẢNH LỚN -->
    <div v-if="lightbox.open" class="lightbox-backdrop" @click="lightbox.open = false">
      <img :src="formatImageUrl(lightbox.img)" alt="lightbox zoom" class="lightbox-img" />
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { ownerPostService } from '../../services/ownerPosts.js';
import { venueClusterService } from '../../services/venueClusters.js';

export default {
  name: 'OwnerPosts',
  components: { AppIcon },
  data() {
    return {
      posts: [],
      clusters: [],
      loading: true,
      saving: false,
      message: '',
      error: '',
      activeTab: 'all',
      filterClusterId: '',
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      tabs: [
        { label: 'Tất cả', value: 'all', icon: 'layers' },
        { label: 'Chờ duyệt', value: 'pending_review', icon: 'clock' },
        { label: 'Đã duyệt', value: 'published', icon: 'check' },
        { label: 'Bị từ chối', value: 'rejected', icon: 'messageWarning' },
        { label: 'Bị ẩn', value: 'hidden', icon: 'lock' },
      ],
      formModal: {
        open: false,
        isEdit: false,
        postId: null,
      },
      form: {
        venue_cluster_id: '',
        content: '',
        existingMedia: [],
        newImages: [], // Array of objects { file, previewUrl }
        deleted_media_ids: [],
      },
      lightbox: {
        open: false,
        img: '',
      },
      mousedownWasOnBackdrop: false,
    };
  },
  async mounted() {
    await this.loadClusters();
    await this.loadPosts();
  },
  methods: {
    async loadClusters() {
      try {
        const response = await venueClusterService.getClusters();
        this.clusters = response.data || [];
      } catch (err) {
        this.error = err.message || 'Không thể tải danh sách cụm sân.';
      }
    },
    async loadPosts(page = 1) {
      this.loading = true;
      this.clearAlerts();
      try {
        const params = {
          page,
          venue_cluster_id: this.filterClusterId,
        };
        if (this.activeTab !== 'all') {
          params.status = this.activeTab;
        }

        const response = await ownerPostService.list(params);
        const paginator = response.data || {};
        this.posts = paginator.data || [];
        this.pagination = {
          current_page: paginator.current_page || 1,
          last_page: paginator.last_page || 1,
          total: paginator.total || this.posts.length,
        };
      } catch (err) {
        this.error = err.message || 'Không thể tải danh sách bài viết.';
      } finally {
        this.loading = false;
      }
    },
    changeTab(tabValue) {
      this.activeTab = tabValue;
      this.loadPosts(1);
    },
    clearAlerts() {
      this.error = '';
      this.message = '';
    },
    formatDate(value) {
      if (!value) return '-';
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
    formatImageUrl(path) {
      if (!path) return '/images/default-thumbnail.png';
      return path.startsWith('http') ? path : `/storage/${path}`;
    },
    getStatusLabel(status) {
      const map = {
        pending_review: 'Chờ duyệt',
        published: 'Đã duyệt',
        rejected: 'Bị từ chối',
        hidden: 'Bị ẩn/Khóa',
      };
      return map[status] || status;
    },
    getStatusClass(status) {
      const map = {
        pending_review: 'status-pending',
        published: 'status-approved',
        rejected: 'status-rejected',
        hidden: 'status-hidden',
      };
      return map[status] || '';
    },
    openLightbox(filePath) {
      this.lightbox.img = filePath;
      this.lightbox.open = true;
    },

    // Modal actions
    openCreateModal() {
      this.clearAlerts();
      this.form = {
        venue_cluster_id: this.clusters[0]?.id || '',
        content: '',
        existingMedia: [],
        newImages: [],
        deleted_media_ids: [],
      };
      this.formModal.isEdit = false;
      this.formModal.postId = null;
      this.formModal.open = true;
    },
    openEditModal(post) {
      this.clearAlerts();
      this.form = {
        venue_cluster_id: post.venue_cluster_id,
        content: post.content,
        existingMedia: [...(post.media || [])],
        newImages: [],
        deleted_media_ids: [],
      };
      this.formModal.isEdit = true;
      this.formModal.postId = post.id;
      this.formModal.open = true;
    },
    closeModal() {
      // Revoke preview URLs to avoid memory leaks
      this.form.newImages.forEach((img) => URL.revokeObjectURL(img.previewUrl));
      this.formModal.open = false;
    },
    handleBackdropMousedown(event) {
      this.mousedownWasOnBackdrop = event.target === event.currentTarget;
    },
    handleBackdropClick(event) {
      if (this.mousedownWasOnBackdrop && event.target === event.currentTarget) {
        this.closeModal();
      }
      this.mousedownWasOnBackdrop = false;
    },

    // Upload & Form handlers
    triggerFileInput() {
      this.$refs.fileInput.click();
    },
    handleFileSelect(event) {
      const files = Array.from(event.target.files || []);
      files.forEach((file) => {
        if (file.size > 5 * 1024 * 1024) {
          alert(`File ${file.name} vượt quá 5MB. Vui lòng chọn ảnh dung lượng nhỏ hơn.`);
          return;
        }
        const previewUrl = URL.createObjectURL(file);
        this.form.newImages.push({ file, previewUrl });
      });
      // Clear value so the same file can be selected again if removed
      event.target.value = '';
    },
    removeNewImage(index) {
      const removed = this.form.newImages.splice(index, 1)[0];
      if (removed) {
        URL.revokeObjectURL(removed.previewUrl);
      }
    },
    markMediaForDeletion(mediaId) {
      this.form.deleted_media_ids.push(mediaId);
      this.form.existingMedia = this.form.existingMedia.filter((m) => m.id !== mediaId);
    },
    async submitForm() {
      this.saving = true;
      this.clearAlerts();

      const formData = new FormData();
      formData.append('venue_cluster_id', this.form.venue_cluster_id);
      formData.append('content', this.form.content);

      // Append new files
      this.form.newImages.forEach((img) => {
        formData.append('images[]', img.file);
      });

      // Append deleted media IDs
      if (this.formModal.isEdit) {
        this.form.deleted_media_ids.forEach((id) => {
          formData.append('deleted_media_ids[]', id);
        });
      }

      try {
        if (this.formModal.isEdit) {
          const res = await ownerPostService.update(this.formModal.postId, formData);
          this.message = res.message || 'Cập nhật bài viết thành công.';
        } else {
          const res = await ownerPostService.create(formData);
          this.message = res.message || 'Đăng bài viết thành công.';
        }
        this.closeModal();
        await this.loadPosts(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Lưu bài viết thất bại. Vui lòng kiểm tra lại dữ liệu.';
      } finally {
        this.saving = false;
      }
    },
    async confirmDelete(post) {
      if (confirm('Bạn có chắc chắn muốn xóa bài viết này không? Hành động này không thể hoàn tác.')) {
        this.clearAlerts();
        try {
          await ownerPostService.delete(post.id);
          this.message = 'Xóa bài viết thành công.';
          await this.loadPosts(this.pagination.current_page);
        } catch (err) {
          this.error = err.message || 'Xóa bài viết thất bại.';
        }
      }
    },
  },
};
</script>

<style scoped>
.posts-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.page-header h2 {
  font-size: 24px;
  font-weight: 850;
  color: var(--admin-text);
  margin: 0;
}

.muted {
  color: var(--admin-muted);
  margin: 4px 0 0;
  font-size: 14px;
}

.card {
  background: var(--admin-surface, #fff);
  border: 1px solid var(--admin-border);
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.filter-toolbar {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 16px;
}

.tabs-header {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 12px;
  flex-wrap: wrap;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: 0;
  background: transparent;
  color: var(--admin-muted);
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  border-radius: 8px;
  transition: all 0.2s;
}

.tab-btn:hover {
  background: var(--admin-surface-muted);
  color: var(--admin-text);
}

.tab-btn.active {
  background: #e6f4ea;
  color: #059669;
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
  font-size: 13px;
  font-weight: 800;
  color: var(--admin-text);
  min-width: 240px;
}

.field.compact {
  flex-direction: row;
  align-items: center;
  gap: 10px;
}

.field.compact select {
  min-width: 200px;
}

.field select,
.field textarea {
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 14px;
  font-weight: 500;
  background: var(--admin-surface, #fff);
  color: var(--admin-text);
  outline: none;
  transition: border-color 0.15s;
}

.field select:focus,
.field textarea:focus {
  border-color: #059669;
}

.field select {
  height: 38px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 38px;
  padding: 0 16px;
  border-radius: 8px;
  font-weight: 800;
  font-size: 14px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.15s;
  white-space: nowrap;
}

.btn.primary {
  background: #059669;
  color: #fff;
}

.btn.primary:hover {
  background: #047857;
}

.btn.ghost {
  background: var(--admin-surface, #fff);
  border-color: var(--admin-border);
  color: var(--admin-text);
}

.btn.ghost:hover {
  background: var(--admin-surface-muted);
}

.btn.ghost.danger {
  color: #dc2626;
  border-color: #fca5a5;
}

.btn.ghost.danger:hover {
  background: #fef2f2;
}

.btn-sm {
  height: 30px;
  padding: 0 10px;
  font-size: 13px;
  border-radius: 6px;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.icon-btn {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--admin-surface-muted);
  border: 0;
  border-radius: 50%;
  color: var(--admin-faint);
  cursor: pointer;
  transition: all 0.15s;
}

.icon-btn:hover {
  background: var(--admin-border);
  color: var(--admin-text);
}

.notice {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 800;
}

.notice.success {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.notice.error {
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.state-box {
  display: flex;
  min-height: 240px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--admin-muted);
  text-align: center;
  padding: 32px;
}

.spinner {
  width: 30px;
  height: 30px;
  border: 3px solid rgba(5, 150, 105, 0.1);
  border-top-color: #059669;
  border-radius: 50%;
  animation: spin 1s infinite linear;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Posts List & Cards */
.posts-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.post-card {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.post-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.post-meta-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.cluster-badge {
  background: var(--admin-surface-muted);
  color: var(--admin-text);
  font-weight: 800;
  font-size: 12px;
  padding: 4px 8px;
  border-radius: 6px;
}

.post-time {
  color: var(--admin-faint);
  font-size: 12px;
}

.status-badge {
  font-size: 12px;
  font-weight: 850;
  padding: 4px 10px;
  border-radius: 999px;
  text-transform: uppercase;
}

.status-pending {
  background: #fef3c7;
  color: #d97706;
}

.status-approved {
  background: #dcfce7;
  color: #15803d;
}

.status-rejected {
  background: #fee2e2;
  color: #b91c1c;
}

.status-hidden {
  background: var(--admin-surface-muted);
  color: var(--admin-muted);
}

.post-content {
  font-size: 15px;
  line-height: 1.6;
  color: var(--admin-text);
  margin: 0;
  white-space: pre-wrap;
}

.hashtags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}

.hashtag {
  color: #059669;
  font-weight: 700;
  font-size: 13px;
}

/* Media Gallery */
.media-gallery {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

.media-item {
  width: 120px;
  height: 120px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #f1f5f9;
  cursor: pointer;
  transition: transform 0.2s;
}

.media-item:hover {
  transform: scale(1.03);
}

.media-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.reason-banner {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border-radius: 8px;
  font-size: 14px;
  line-height: 1.5;
}

.reason-banner.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fee2e2;
}

.reason-banner.warning {
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fef3c7;
}

.post-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid #f1f5f9;
  padding-top: 12px;
  flex-wrap: wrap;
  gap: 12px;
}

.post-stats {
  display: flex;
  gap: 16px;
  color: var(--admin-muted);
  font-size: 13px;
}

.post-stats span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.post-actions {
  display: flex;
  gap: 8px;
}

.pagination-bar {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 24px;
}

.page-info {
  font-size: 14px;
  font-weight: 700;
  color: var(--admin-faint);
}

/* Modals */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 16px;
}

.modal {
  background: var(--admin-surface, #fff);
  border-radius: 16px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal.medium {
  max-width: 600px;
}

.modal-header {
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  font-size: 18px;
  font-weight: 850;
  color: var(--admin-text);
  margin: 0;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.required {
  color: #dc2626;
}

.image-uploader-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.image-uploader-section > span {
  font-size: 13px;
  font-weight: 800;
  color: var(--admin-text);
}

.upload-area {
  border: 2px dashed var(--admin-border);
  border-radius: 10px;
  padding: 24px;
  text-align: center;
  cursor: pointer;
  background: var(--admin-surface-muted);
  transition: all 0.15s;
}

.upload-area:hover {
  border-color: #059669;
  background: #f0fdf4;
}

.upload-area p {
  margin: 8px 0 0;
  font-size: 13px;
  color: var(--admin-muted);
  font-weight: 700;
}

.hidden-file-input {
  display: none;
}

.media-preview-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 8px;
}

.preview-item {
  position: relative;
  width: 90px;
  height: 90px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--admin-border);
}

.preview-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.remove-btn {
  position: absolute;
  top: 4px;
  right: 4px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: rgba(15, 23, 42, 0.75);
  color: #fff;
  border: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
}

.remove-btn:hover {
  background: rgba(220, 38, 38, 0.9);
}

.modal-footer {
  padding: 16px 20px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

/* Lightbox */
.lightbox-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 110;
}

.lightbox-img {
  max-width: 90%;
  max-height: 90%;
  border-radius: 4px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}
</style>

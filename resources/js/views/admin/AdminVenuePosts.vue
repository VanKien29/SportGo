<template>
  <div class="page venue-posts-page">
    <header class="page-header sg-page-header">
      <div class="sg-page-heading">
        <nav class="sg-breadcrumbs" aria-label="Breadcrumb">
          <router-link to="/admin/dashboard">Dashboard</router-link>
          <span>/</span>
          <span>Kiểm duyệt bài viết</span>
        </nav>
        <h2>Kiểm duyệt bài viết</h2>
        <p>Kiểm tra, phê duyệt, từ chối hoặc quản lý các bài viết đăng tải từ chủ sân.</p>
      </div>

      <div class="sg-page-actions">
        <button class="icon-btn sg-primary-action" type="button" title="Làm mới" aria-label="Làm mới" @click="loadPosts(1)">
          <AppIcon name="refresh" size="16" />
          <span>Làm mới</span>
        </button>
      </div>
    </header>

    <div class="toolbar card sg-filter-panel">
      <div class="filters">
        <label class="field compact">
          <span>Tiêu đề bài viết</span>
          <input
            v-model="filters.keyword"
            type="search"
            placeholder="Nhập tiêu đề tìm kiếm..."
            @input="onFilterChange"
          />
        </label>
        <label class="field compact">
          <span>Tác giả</span>
          <input
            v-model="filters.author"
            type="search"
            placeholder="Tên hoặc tài khoản..."
            @input="onFilterChange"
          />
        </label>
        <label class="field compact">
          <span>Danh mục</span>
          <select v-model="filters.post_type" @change="loadPosts(1)">
            <option value="">Tất cả danh mục</option>
            <option value="promotion">Khuyến mãi</option>
            <option value="tournament">Giải đấu</option>
            <option value="news">Tin tức</option>
            <option value="notice">Thông báo</option>
            <option value="recruitment">Tuyển dụng</option>
          </select>
        </label>
        <label class="field compact">
          <span>Trạng thái</span>
          <select v-model="filters.status" @change="loadPosts(1)">
            <option value="">Tất cả trạng thái</option>
            <option value="pending_review">Chờ duyệt</option>
            <option value="published">Đã duyệt (Xuất bản)</option>
            <option value="rejected">Từ chối</option>
            <option value="hidden">Đã ẩn</option>
          </select>
        </label>
      </div>
    </div>

    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <div v-if="loading" class="state-box card sg-state-box">
      <div class="spinner"></div>
      <p>Đang tải danh sách bài viết...</p>
    </div>

    <div v-else-if="posts.length === 0" class="state-box card sg-state-box">
      <p>Không có bài viết phù hợp.</p>
    </div>

    <div v-else class="applications-table card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th class="center" style="width: 60px">STT</th>
              <th style="width: 80px">Ảnh</th>
              <th>Tiêu đề bài viết</th>
              <th>Tác giả / Cơ sở</th>
              <th>Danh mục</th>
              <th class="center">Trạng thái</th>
              <th class="center" @click="toggleSort('view_count')" style="cursor: pointer; user-select: none;">
                <div style="display:inline-flex; align-items:center; gap:4px;">
                  Lượt xem
                  <AppIcon v-if="sorting.by === 'view_count'" :name="sorting.order === 'asc' ? 'chevronUp' : 'chevronDown'" size="12" />
                </div>
              </th>
              <th class="right" @click="toggleSort('created_at')" style="cursor: pointer; user-select: none;">
                <div style="display:inline-flex; align-items:center; justify-content:flex-end; gap:4px;">
                  Ngày tạo
                  <AppIcon v-if="sorting.by === 'created_at'" :name="sorting.order === 'asc' ? 'chevronUp' : 'chevronDown'" size="12" />
                </div>
              </th>
              <th class="right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(post, index) in posts" :key="post.id">
              <td class="center font-bold muted">{{ (pagination.current_page - 1) * pagination.per_page + index + 1 }}</td>
              <td>
                <div class="thumbnail">
                  <img
                    v-if="hasThumbnail(post)"
                    :src="getThumbnail(post)"
                    alt="Thumbnail"
                    @error="handleThumbnailError(post.id)"
                  />
                  <div v-else class="placeholder"><AppIcon name="image" size="16" /></div>
                </div>
              </td>
              <td>
                <div class="main-title">{{ post.title }}</div>
                <div class="muted">{{ formattedTagsList(post) }}</div>
              </td>
              <td>
                <div class="main-title">{{ post.author?.full_name || post.author?.username || '—' }}</div>
                <div class="muted">{{ post.venue_cluster?.name || '—' }}</div>
              </td>
              <td>
                <span class="badge category-badge">{{ formatPostType(post.post_type) }}</span>
              </td>
              <td class="center">
                <span class="status" :class="statusClass(post.status)">
                  {{ statusLabel(post.status) }}
                </span>
              </td>
              <td class="center font-bold">{{ post.view_count || 0 }}</td>
              <td class="right muted">{{ formatDate(post.created_at) }}</td>
              <td class="right">
                <div class="actions">
                  <button class="icon-btn" type="button" title="Kiểm duyệt / Xem" @click="openReviewModal(post)">
                    <AppIcon name="eye" size="16" />
                  </button>
                  <a :href="`/posts/${post.slug}`" target="_blank" class="icon-btn" title="Xem trên web" style="display:inline-flex; align-items:center; justify-content:center; text-decoration:none;">
                    <AppIcon name="externalLink" size="16" />
                  </a>
                  <button v-if="post.deleted_at" class="icon-btn" type="button" title="Khôi phục" @click="confirmRestore(post)">
                    <AppIcon name="refresh" size="16" />
                  </button>
                  <button v-else class="icon-btn danger" type="button" title="Xóa" @click="confirmDelete(post)">
                    <AppIcon name="trash" size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination" v-if="pagination.total > 0">
        <span class="muted" style="margin-right: auto;">Đang hiển thị {{ posts.length }} bài viết (Tổng {{ pagination.total }})</span>
        <button class="icon-btn" type="button" title="Trang trước" :disabled="pagination.current_page <= 1" @click="loadPosts(pagination.current_page - 1)">
          <AppIcon name="chevronLeft" size="17" />
        </button>
        <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button class="icon-btn" type="button" title="Trang sau" :disabled="pagination.current_page >= pagination.last_page" @click="loadPosts(pagination.current_page + 1)">
          <AppIcon name="chevronRight" size="17" />
        </button>
      </div>
    </div>

    <!-- Review Modal -->
    <div v-if="modal.open" class="modal-backdrop" @click.self="closeModal">
      <div class="modal large">
        <div class="modal-header">
          <h3>Chi tiết &amp; Kiểm duyệt bài viết</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>

        <div v-if="modalLoading" class="modal-state">
          <div class="spinner"></div>
          <p>Đang tải chi tiết bài viết...</p>
        </div>

        <div v-else-if="selectedPost" class="modal-body detail-grid">
          <!-- Left: Content Preview -->
          <section class="detail-section" style="grid-column: span 1">
            <h4>Nội dung bài viết</h4>
            <div class="post-preview">
              <span class="badge category-badge" style="margin-left:0; margin-bottom:8px;">{{ formatPostType(selectedPost.post_type) }}</span>
              <h5 class="post-title">{{ selectedPost.title }}</h5>
              <p class="post-desc">{{ selectedPost.short_description || 'Không có mô tả ngắn' }}</p>
              <div class="post-content" v-html="selectedPost.content || '<p>Không có nội dung</p>'"></div>
            </div>
          </section>

          <!-- Right: Metadata & Actions -->
          <section class="detail-section" style="grid-column: span 1">
            <h4>Thông tin chung</h4>
            <dl>
              <dt>Tác giả</dt>
              <dd>{{ selectedPost.author?.full_name || selectedPost.author?.username || '—' }}</dd>
              
              <dt>Cơ sở</dt>
              <dd>{{ selectedPost.venue_cluster?.name || '—' }}</dd>
              
              <dt>Trạng thái</dt>
              <dd>
                <span class="status" :class="statusClass(selectedPost.status)">
                  {{ statusLabel(selectedPost.status) }}
                </span>
              </dd>
              
              <dt>Lượt xem</dt>
              <dd>{{ selectedPost.view_count || 0 }}</dd>
              
              <dt>Thẻ (Tags)</dt>
              <dd>{{ formattedTagsList(selectedPost) || '—' }}</dd>
              
              <dt>Meta Title</dt>
              <dd>{{ selectedPost.meta_title || '—' }}</dd>
              
              <dt>Meta Desc</dt>
              <dd>{{ selectedPost.meta_description || '—' }}</dd>
            </dl>

            <div v-if="selectedPost.status_reason" class="notice error" style="margin-top: 16px;">
              <strong>Lý do từ chối/ẩn trước đó:</strong>
              <p style="margin: 4px 0 0; font-weight: normal;">{{ selectedPost.status_reason }}</p>
            </div>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid var(--sg-border);" />

            <h4>Quyết định kiểm duyệt</h4>
            <div class="field" style="margin-bottom: 12px;">
              <label style="font-size: 13px; font-weight: 800; color: rgba(15,23,42,0.5); display:block; margin-bottom: 8px;">Lý do / Phản hồi (Bắt buộc nếu từ chối/ẩn)</label>
              <textarea
                v-model="reviewReason"
                placeholder="Nhập nội dung phản hồi cho chủ sân..."
                rows="3"
                style="width:100%; resize:vertical; padding:10px; border:1px solid var(--sg-border); border-radius:6px; font-family:inherit;"
              ></textarea>
            </div>

            <div v-if="modalError" class="notice error" style="margin-bottom:12px;">{{ modalError }}</div>

            <div class="action-buttons">
              <button
                v-if="selectedPost.status !== 'published'"
                class="btn primary"
                type="button"
                :disabled="submitting"
                @click="updateStatus('published')"
              >
                <AppIcon name="check" size="16" />
                <span>Phê duyệt &amp; Xuất bản</span>
              </button>
              
              <button
                v-if="selectedPost.status === 'pending_review'"
                class="btn danger"
                type="button"
                :disabled="submitting"
                @click="updateStatus('rejected')"
              >
                <AppIcon name="x" size="16" />
                <span>Từ chối bài viết</span>
              </button>

              <button
                v-if="selectedPost.status === 'published'"
                class="btn ghost"
                type="button"
                :disabled="submitting"
                @click="updateStatus('hidden')"
              >
                <AppIcon name="eyeOff" size="16" />
                <span>Ẩn bài viết</span>
              </button>
            </div>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminVenuePostService } from '../../services/adminVenuePostService.js';

export default {
  name: 'AdminVenuePosts',
  components: { AppIcon },

  data() {
    return {
      posts: [],
      loading: true,
      error: '',
      message: '',
      filterTimer: null,
      filters: {
        keyword: '',
        author: '',
        status: 'pending_review',
        post_type: '',
      },
      sorting: {
        by: 'created_at',
        order: 'desc'
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
      },
      modal: { open: false },
      selectedPost: null,
      modalLoading: false,
      modalError: '',
      reviewReason: '',
      submitting: false,
      brokenThumbnails: new Set(),
    };
  },

  mounted() {
    this.loadPosts();
  },

  methods: {
    async loadPosts(page = 1) {
      this.loading = true;
      this.error = '';
      try {
        const res = await adminVenuePostService.list({ 
          ...this.filters, 
          sort_by: this.sorting.by,
          sort_order: this.sorting.order,
          page 
        });
        const paginator = res || {};
        this.posts = paginator.data || [];
        this.brokenThumbnails = new Set();
        this.pagination = {
          current_page: paginator.current_page || 1,
          last_page: paginator.last_page || 1,
          per_page: paginator.per_page || 15,
          total: paginator.total || 0,
        };
      } catch (err) {
        this.error = err.message || 'Không tải được danh sách bài viết kiểm duyệt.';
      } finally {
        this.loading = false;
      }
    },

    onFilterChange() {
      clearTimeout(this.filterTimer);
      this.filterTimer = setTimeout(() => this.loadPosts(1), 400);
    },

    toggleSort(field) {
      if (this.sorting.by === field) {
        this.sorting.order = this.sorting.order === 'desc' ? 'asc' : 'desc';
      } else {
        this.sorting.by = field;
        this.sorting.order = 'desc';
      }
      this.loadPosts(1);
    },

    async openReviewModal(post) {
      this.clearAlerts();
      this.selectedPost = null;
      this.modalLoading = true;
      this.modalError = '';
      this.reviewReason = '';
      this.modal.open = true;

      try {
        const res = await adminVenuePostService.show(post.id);
        this.selectedPost = res.data;
        this.reviewReason = this.selectedPost.status_reason || '';
      } catch (err) {
        this.modalError = err.message || 'Không thể tải chi tiết bài viết này.';
      } finally {
        this.modalLoading = false;
      }
    },

    closeModal() {
      this.modal.open = false;
      this.selectedPost = null;
      this.reviewReason = '';
      this.modalError = '';
    },

    async updateStatus(status) {
      if (['rejected', 'hidden'].includes(status) && !this.reviewReason.trim()) {
        this.modalError = 'Vui lòng nhập lý do/phản hồi trước khi thực hiện thao tác này.';
        return;
      }
      this.submitting = true;
      this.modalError = '';
      try {
        await adminVenuePostService.approve(this.selectedPost.id, {
          status,
          reason: this.reviewReason.trim(),
        });
        this.message = `Đã chuyển trạng thái bài viết thành "${this.statusLabel(status)}" thành công.`;
        this.closeModal();
        await this.loadPosts(this.pagination.current_page);
      } catch (err) {
        if (err.response && err.response.status === 422) {
          this.modalError = err.response.data.message || 'Lỗi kiểm duyệt bài viết.';
        } else {
          this.modalError = err.message || 'Có lỗi xảy ra, vui lòng thử lại.';
        }
      } finally {
        this.submitting = false;
      }
    },

    async confirmDelete(post) {
      if (!window.confirm(`Bạn có chắc chắn muốn xóa bài viết "${post.title}" của chủ sân?\nBài viết sẽ bị ẩn khỏi website.`)) return;
      this.clearAlerts();
      try {
        await adminVenuePostService.remove(post.id);
        this.message = 'Bài viết đã được xóa mềm thành công.';
        await this.loadPosts(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không xóa được bài viết này.';
      }
    },

    async confirmRestore(post) {
      if (!window.confirm(`Bạn có chắc chắn muốn khôi phục bài viết "${post.title}"?`)) return;
      this.clearAlerts();
      try {
        await adminVenuePostService.restore(post.id);
        this.message = 'Bài viết đã được khôi phục thành công.';
        await this.loadPosts(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không khôi phục được bài viết này.';
      }
    },

    clearAlerts() {
      this.error = '';
      this.message = '';
    },

    formatDate(value) {
      if (!value) return '—';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    },

    getThumbnail(post) {
      if (!post.media?.length) return '';
      const thumb = post.media.find((m) => m.collection === 'thumbnail') || post.media[0];
      return this.normalizeMediaUrl(thumb);
    },

    hasThumbnail(post) {
      return Boolean(this.getThumbnail(post)) && !this.brokenThumbnails.has(post.id);
    },

    handleThumbnailError(postId) {
      this.brokenThumbnails = new Set([...this.brokenThumbnails, postId]);
    },

    normalizeMediaUrl(media) {
      const rawPath = media?.url || media?.file_url || media?.full_url || media?.file_path || media?.path || '';
      if (!rawPath) return '';

      const path = String(rawPath).trim().replace(/\\/g, '/');
      if (!path) return '';
      if (/^(https?:)?\/\//i.test(path) || path.startsWith('data:') || path.startsWith('blob:')) return path;
      if (path.startsWith('/storage/')) return path;
      if (path.startsWith('storage/')) return `/${path}`;
      if (path.startsWith('/')) return path;

      return `/storage/${path.replace(/^public\//, '')}`;
    },

    formattedTagsList(post) {
      if (!post.hashtags?.length) return '';
      return post.hashtags.map(t => '#' + t.name).join(', ');
    },

    formatPostType(type) {
      const map = {
        promotion: 'Khuyến mãi',
        tournament: 'Giải đấu',
        news: 'Tin tức',
        notice: 'Thông báo',
        recruitment: 'Tuyển dụng',
      };
      return map[type] || type || '—';
    },

    statusLabel(status) {
      const map = {
        draft: 'Bản nháp',
        pending_review: 'Chờ duyệt',
        published: 'Đã xuất bản',
        rejected: 'Từ chối',
        hidden: 'Đã ẩn',
      };
      return map[status] || status;
    },

    statusClass(status) {
      const map = {
        draft: 'status-draft',
        pending_review: 'status-pending',
        published: 'status-approved',
        rejected: 'status-rejected',
        hidden: 'status-hidden'
      };
      return map[status] || 'status-draft';
    }
  },
};
</script>

<style scoped>
.thumbnail {
  width: 60px;
  height: 40px;
  border-radius: 6px;
  overflow: hidden;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.thumbnail .placeholder {
  color: #94a3b8;
}

.category-badge {
  background: #e0e7ff;
  color: #3730a3;
}

.toolbar {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 16px;
  padding: 16px;
}
.filters {
  width: 100%;
  display: grid;
  grid-template-columns: minmax(220px, 1.4fr) repeat(3, minmax(150px, 1fr));
  gap: 12px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 800;
  color: var(--sg-text);
}
.field input,
.field select,
.field textarea {
  width: 100%;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  padding: 0 12px;
  font-size: 14px;
  font-weight: 500;
  background: #fff;
  color: var(--sg-text);
}
.field input:focus,
.field select:focus,
.field textarea:focus {
  border-color: #0f172a;
  outline: none;
}
.field input,
.field select {
  height: 40px;
}

.applications-table {
  overflow: hidden;
}
.table-scroll {
  width: 100%;
  overflow-x: auto;
}
table {
  width: 100%;
  min-width: 980px;
  border-collapse: collapse;
}
th, td {
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
.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 800;
}

/* Custom Status extensions */
.status-draft,
.status-hidden {
  background: #f1f5f9;
  color: #475569;
}

.icon-btn[disabled] {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Modal styles copied from AdminPartnerApplications for consistency */
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
  width: min(760px, 100%);
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: #fff;
  border-radius: 8px;
}

.modal.large {
  width: min(980px, 100%);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid var(--sg-border);
}
.modal-header h3 {
  margin: 0;
  font-size: 18px;
}
.modal-body {
  padding: 18px;
  overflow-y: auto;
}
.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
.detail-section {
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  padding: 14px;
  min-width: 0;
}
.detail-section h4 {
  margin: 0 0 12px;
  font-size: 14px;
  font-weight: 900;
  color: var(--sg-text);
}
dl {
  display: grid;
  grid-template-columns: 100px 1fr;
  gap: 8px 12px;
  margin: 0;
}
dt {
  color: rgba(15, 23, 42, 0.5);
  font-size: 13px;
  font-weight: 800;
}
dd {
  margin: 0;
  color: var(--sg-text);
  font-size: 14px;
  font-weight: 600;
}

/* Specific post preview styling */
.post-preview {
  background: #f8fafc;
  border-radius: 6px;
  padding: 16px;
}
.post-title {
  font-size: 18px;
  font-weight: 900;
  color: var(--sg-text);
  margin: 0 0 8px;
}
.post-desc {
  font-size: 14px;
  font-style: italic;
  color: #64748b;
  margin: 0 0 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e2e8f0;
}
.post-content {
  font-size: 14px;
  line-height: 1.6;
  color: #334155;
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

.btn.danger {
  background: #dc2626;
  color: #fff;
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.action-buttons {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.icon-btn {
  width: 34px;
  height: 34px;
  background: #f8fafc;
  border-color: #e2e8f0;
  color: #334155;
}

.icon-btn.sg-primary-action {
  width: auto;
  height: 40px;
  padding: 0 14px;
}

.icon-btn.approve {
  color: #15803d;
}

.icon-btn.danger {
  color: #dc2626;
}

.notice {
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 800;
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
  min-height: 168px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: rgba(15, 23, 42, 0.55);
}

.modal-state {
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

.center {
  text-align: center;
}

.right {
  text-align: right;
}

.main-title {
  color: var(--sg-text);
  font-weight: 800;
}

.muted {
  color: rgba(15, 23, 42, 0.5);
  font-size: 13px;
}

.status {
  display: inline-flex;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 900;
}

.status-pending,
.status-reviewing {
  background: #fef3c7;
  color: #92400e;
}

.status-approved {
  background: #dcfce7;
  color: #166534;
}

.status-rejected,
.status-cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.actions {
  display: inline-flex;
  gap: 8px;
}

@media (max-width: 900px) {
  .toolbar {
    flex-direction: column;
  }
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>

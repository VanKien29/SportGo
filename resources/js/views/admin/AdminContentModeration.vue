<template>
  <div class="moderation-page">
    <!-- Toolbar bộ lọc và chuyển tab -->
    <div class="toolbar card">
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

      <!-- Lọc theo Trạng thái -->
      <nav class="status-tabs" aria-label="Lọc nhanh trạng thái bài viết">
        <button
          v-for="st in statusTabs"
          :key="st.value"
          :class="{ active: filters.status === st.value }"
          type="button"
          @click="setStatus(st.value)"
        >
          {{ st.label }}
        </button>
      </nav>

      <div class="filters">
        <label class="field compact">
          <span>Tìm kiếm</span>
          <input
            v-model="filters.search"
            type="search"
            :placeholder="searchPlaceholder"
            @input="onFilterChange"
          />
        </label>




        <button class="btn ghost btn-refresh" type="button" @click="refresh">
          <AppIcon name="refresh" size="16" />
          <span>Làm mới</span>
        </button>
      </div>
    </div>

    <!-- Thông báo kết quả -->
    <div v-if="message" class="notice success">{{ message }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <!-- Màn hình loading -->
    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải danh sách bài viết...</p>
    </div>

    <!-- Màn hình trống -->
    <div v-else-if="items.length === 0" class="state-box card">
      <p>Không có bài viết nào phù hợp.</p>
    </div>

    <!-- Bảng hiển thị danh sách -->
    <div v-else class="moderation-table card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Tác giả</th>
              <th v-if="activeTab === 'system_posts'">Tiêu đề</th>
              <th>Nội dung</th>
              <th v-if="activeTab !== 'system_posts'">Hashtag</th>
              <th v-if="activeTab !== 'system_posts'">Ảnh / File</th>
              <th>Trạng thái</th>
              <th>Thời gian tạo</th>
              <th class="right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>
                <div class="main-title">{{ item.author?.full_name || item.author?.username || '-' }}</div>
                <div class="muted">{{ item.author?.phone || item.author?.email || '' }}</div>
                <div v-if="activeTab === 'venue_posts' && item.venue_cluster" class="badge-cluster">
                  {{ item.venue_cluster?.name }}
                </div>
              </td>
              <td v-if="activeTab === 'system_posts'">
                <div class="main-title">{{ item.title || '-' }}</div>
              </td>
              <td>
                <div class="content-preview">{{ item.content }}</div>
              </td>
              <td v-if="activeTab !== 'system_posts'">
                <div class="hashtags-list">
                  <span v-for="tag in item.hashtags" :key="tag.id" class="tag">
                    #{{ tag.name }}
                  </span>
                  <span v-if="!item.hashtags?.length" class="muted">-</span>
                </div>
              </td>
              <td v-if="activeTab !== 'system_posts'">
                <div v-if="item.media?.length" class="media-preview-box">
                  <img :src="item.media[0].file_path" alt="preview" class="media-thumb" />
                  <span v-if="item.media.length > 1" class="media-count">+{{ item.media.length - 1 }}</span>
                </div>
                <span v-else class="muted">Không có</span>
              </td>
              <td>
                <span class="status" :class="getStatusClass(item.status)">
                  {{ getStatusLabel(item.status) }}
                </span>
              </td>
              <td>{{ formatDate(item.created_at) }}</td>
              <td class="right">
                <div class="actions">
                  <button class="icon-btn" type="button" title="Xem chi tiết" @click="openDetail(item)">
                    <AppIcon name="eye" size="16" />
                  </button>
                  <button
                    v-if="['pending', 'pending_review', 'draft'].includes(item.status)"
                    class="icon-btn approve"
                    type="button"
                    title="Duyệt bài"
                    @click="approvePostDirect(item)"
                  >
                    <AppIcon name="check" size="16" />
                  </button>
                  <button
                    v-if="item.status !== 'hidden' && item.status !== 'rejected'"
                    class="icon-btn danger"
                    type="button"
                    title="Từ chối/Ẩn/Xóa"
                    @click="openActionModal(item)"
                  >
                    <AppIcon name="x" size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Phân trang -->
      <div v-if="pagination.last_page > 1" class="pagination">
        <button
          class="btn ghost"
          type="button"
          :disabled="pagination.current_page <= 1"
          @click="loadData(pagination.current_page - 1)"
        >
          Trước
        </button>
        <span>{{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button
          class="btn ghost"
          type="button"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="loadData(pagination.current_page + 1)"
        >
          Sau
        </button>
      </div>
    </div>

    <!-- MODAL CHI TIẾT BÀI VIẾT (Facebook Style) -->
    <div v-if="detailModal.open && activeItem" class="modal-backdrop fb-backdrop" @click.self="closeDetail">
      <div class="fb-modal">
        <header class="fb-header">
          <div class="fb-header-spacer"></div>
          <h3>
            <template v-if="activeTab === 'system_posts'">Bài đăng hệ thống</template>
            <template v-else>Bài viết của {{ activeItem.author?.full_name || activeItem.author?.username || 'Người dùng' }}</template>
          </h3>
          <div class="fb-header-right">
            <button class="fb-close-btn" type="button" @click="closeDetail" title="Đóng">
              <AppIcon name="x" size="20" />
            </button>
          </div>
        </header>

        <div class="fb-body">
          <div class="fb-post" :class="{ 'is-hidden': activeItem.status === 'hidden' }">
            <div class="post-status-banner" :class="getStatusClass(activeItem.status)">
              <AppIcon name="alert" size="16" v-if="activeItem.status === 'hidden'" />
              Trạng thái bài viết: <strong>{{ getStatusLabel(activeItem.status) }}</strong>
            </div>

            <div class="fb-post-header">
              <div class="fb-post-avatar">
                <img v-if="activeItem.author?.avatar_url" :src="activeItem.author.avatar_url" />
                <div v-else-if="activeTab !== 'system_posts'" class="fb-avatar-text">{{ initials(activeItem.author?.full_name || activeItem.author?.username || '?') }}</div>
                <div v-else class="fb-avatar-text">SG</div>
              </div>
              <div class="fb-post-meta">
                <strong>
                  <template v-if="activeTab === 'system_posts'">Hệ thống SportGo</template>
                  <template v-else>{{ activeItem.author?.full_name || activeItem.author?.username || '-' }}</template>
                </strong>
                <span>{{ formatDate(activeItem.created_at) }}</span>
              </div>
            </div>

            <h5 v-if="activeTab === 'system_posts' && activeItem.title" style="margin: 0 0 10px; font-size: 16px; font-weight: 800; color: #0f172a;">
              {{ activeItem.title }}
            </h5>
            <p class="fb-post-text">{{ activeItem.content }}</p>

            <div v-if="activeItem.media && activeItem.media.length" class="fb-media-container">
              <img v-for="m in activeItem.media" :key="m.id" :src="m.file_path || m.url" style="cursor: pointer;" @click="openLightbox(m.file_path || m.url)" />
            </div>

            <div v-if="activeTab !== 'system_posts'" class="fb-stats">
              <span><AppIcon name="heart" size="18" /> {{ activeItem.like_count || 0 }}</span>
              <div class="fb-stats-right">
                <span>{{ activeItem.comment_count || postComments.length }} bình luận</span>
                <span>0 chia sẻ</span>
              </div>
            </div>

            <!-- Nút thao tác bài viết -->
            <div class="fb-moderation-actions" style="margin-top: 12px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
              <div v-if="activeItem.status !== 'hidden' && activeItem.status !== 'rejected'" style="margin-bottom: 12px;">
                <label style="display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; font-weight: 800; color: #475569;">
                  <span>Lý do ẩn/từ chối/gỡ (Bắt buộc nếu ẩn/từ chối/gỡ):</span>
                  <textarea
                    v-model.trim="actionForm.reason"
                    rows="3"
                    style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; font-weight: 500;"
                    placeholder="Nhập lý do kiểm duyệt gửi tới tác giả..."
                  ></textarea>
                </label>
              </div>
              <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button class="btn secondary" type="button" @click="closeDetail">Đóng</button>
                <button
                  v-if="activeItem.status !== 'hidden' && activeItem.status !== 'rejected'"
                  class="btn danger"
                  type="button"
                  :disabled="savingAction || !actionForm.reason"
                  @click="submitAction('delete')"
                >
                  <AppIcon name="trash" size="16" />
                  <span>Gỡ bài</span>
                </button>
                <button
                  v-if="['pending', 'pending_review', 'draft'].includes(activeItem.status)"
                  class="btn warning"
                  type="button"
                  :disabled="savingAction || !actionForm.reason"
                  @click="submitAction('reject')"
                >
                  <AppIcon name="x" size="16" />
                  <span>Từ chối</span>
                </button>
                <button
                  v-if="activeItem.status !== 'hidden' && activeItem.status !== 'rejected'"
                  class="btn warning"
                  type="button"
                  :disabled="savingAction || !actionForm.reason"
                  @click="submitAction('hide')"
                >
                  <AppIcon name="eyeOff" size="16" />
                  <span>Ẩn bài</span>
                </button>
                <button
                  v-if="activeItem.status !== 'published'"
                  class="btn primary"
                  type="button"
                  :disabled="savingAction"
                  @click="submitAction('approve')"
                >
                  <AppIcon name="check" size="16" />
                  <span>Duyệt hiển thị</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Danh sách bình luận -->
          <div v-if="activeTab !== 'system_posts'" class="fb-comments">
            <div v-if="loadingComments" style="text-align: center; color: #64748b; padding: 20px 0;">
              Đang tải bình luận...
            </div>
            <template v-else>
              <div v-for="c in postComments" :key="c.id" class="fb-comment-group">
                <div class="fb-comment-row" :id="`comment-${c.id}`">
                  <div class="fb-comment-avatar">
                    <img v-if="c.user_avatar" :src="c.user_avatar" />
                    <div v-else class="fb-avatar-text">{{ initials(c.user_name) }}</div>
                  </div>
                  <div class="fb-comment-content" :class="{ 'is-hidden': c.status === 'hidden' }">
                    <div class="fb-bubble">
                      <strong>{{ c.user_name }}</strong>
                      <p>{{ c.content }}</p>
                      <span class="fb-bubble-status" :class="c.status" v-if="c.status !== 'visible' && c.status !== 'published'">
                        {{ getStatusLabel(c.status) }}
                      </span>
                    </div>
                    <div class="fb-comment-footer">
                      <span>{{ timeAgo(c.created_at) }}</span>
                      <span>Thích</span>
                      <span>Phản hồi</span>
                      <div class="fb-comment-tools">
                        <button v-if="c.status === 'hidden'" title="Mở ẩn bình luận" @click="actionComment(c, 'unhide')">
                          <AppIcon name="eye" size="14" />
                        </button>
                        <button v-else title="Ẩn bình luận" @click="actionComment(c, 'hide')">
                          <AppIcon name="eyeOff" size="14" />
                        </button>
                        <button title="Xóa bình luận" class="tool-danger" @click="actionComment(c, 'delete')">
                          <AppIcon name="trash" size="14" />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Phản hồi lồng nhau -->
                <div v-if="c.replies && c.replies.length" class="fb-comment-replies">
                  <div v-for="reply in c.replies" :key="reply.id" class="fb-comment-row reply-row" :id="`comment-${reply.id}`">
                    <div class="fb-comment-avatar small">
                      <img v-if="reply.user_avatar" :src="reply.user_avatar" />
                      <div v-else class="fb-avatar-text">{{ initials(reply.user_name) }}</div>
                    </div>
                    <div class="fb-comment-content" :class="{ 'is-hidden': reply.status === 'hidden' }">
                      <div class="fb-bubble">
                        <strong>{{ reply.user_name }}</strong>
                        <p>{{ reply.content }}</p>
                        <span class="fb-bubble-status" :class="reply.status" v-if="reply.status !== 'visible' && reply.status !== 'published'">
                          {{ getStatusLabel(reply.status) }}
                        </span>
                      </div>
                      <div class="fb-comment-footer">
                        <span>{{ timeAgo(reply.created_at) }}</span>
                        <span>Thích</span>
                        <span>Phản hồi</span>
                        <div class="fb-comment-tools">
                          <button v-if="reply.status === 'hidden'" title="Mở ẩn" @click="actionComment(reply, 'unhide')">
                            <AppIcon name="eye" size="14" />
                          </button>
                          <button v-else title="Ẩn" @click="actionComment(reply, 'hide')">
                            <AppIcon name="eyeOff" size="14" />
                          </button>
                          <button title="Xóa" class="tool-danger" @click="actionComment(reply, 'delete')">
                            <AppIcon name="trash" size="14" />
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-if="postComments.length === 0" class="fb-no-comments">Chưa có bình luận nào.</div>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL XỬ LÝ NHANH HÀNH ĐỘNG CHO POST (Từ chối, Ẩn, Xóa) -->
    <div v-if="actionModal.open" class="modal-backdrop" @mousedown="handleBackdropMousedown" @click="handleBackdropClick($event, closeActionModal)">
      <div class="modal small" @mousedown.stop>
        <div class="modal-header">
          <h3>Xử lý kiểm duyệt nội dung</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeActionModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>

        <div class="modal-body">
          <p class="muted">Chọn hành động xử lý bài viết vi phạm:</p>
          <div class="action-options-group">
            <label v-if="['pending', 'pending_review', 'draft'].includes(activeItem.status)" class="action-radio">
              <input v-model="quickActionType" type="radio" value="reject" />
              <span>Từ chối bài viết (Yêu cầu nhập lý do)</span>
            </label>
            <label class="action-radio">
              <input v-model="quickActionType" type="radio" value="hide" />
              <span>Ẩn bài viết khỏi hệ thống (Yêu cầu nhập lý do)</span>
            </label>
            <label class="action-radio">
              <input v-model="quickActionType" type="radio" value="delete" />
              <span>Ẩn & Gỡ bài viết vĩnh viễn (Yêu cầu nhập lý do)</span>
            </label>
          </div>

          <label class="field">
            <span>Lý do xử lý (Bắt buộc)</span>
            <textarea
              v-model.trim="actionForm.reason"
              rows="4"
              placeholder="Nhập lý do gửi tới người chơi..."
            ></textarea>
          </label>
        </div>

        <div class="modal-footer">
          <button class="btn ghost" type="button" @click="closeActionModal">Hủy</button>
          <button
            class="btn danger"
            type="button"
            :disabled="savingAction || !actionForm.reason"
            @click="submitQuickAction"
          >
            <span>Xác nhận xử lý</span>
          </button>
        </div>
      </div>
    </div>

    <!-- LIGHTBOX XEM HÌNH ẢNH TO -->
    <div v-if="lightbox.open" class="lightbox-backdrop" @click="lightbox.open = false">
      <img :src="lightbox.img" alt="zoom" class="lightbox-img" />
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminModerationService } from '../../services/adminModeration.js';
import { adminUserService } from '../../services/adminUserService.js';

export default {
  name: 'AdminContentModeration',
  components: { AppIcon },
  data() {
    return {
      activeTab: 'community_posts',
      items: [],
      loading: true,
      savingAction: false,
      error: '',
      message: '',
      filterTimer: null,
      filters: {
        search: '',
        status: 'all',
      },
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      tabs: [
        { label: 'Bài viết cộng đồng', value: 'community_posts', icon: 'users' },
        { label: 'Bài đăng cụm sân', value: 'venue_posts', icon: 'building' },
        { label: 'Bài đăng hệ thống', value: 'system_posts', icon: 'fileText' },
      ],
      statusTabs: [
        { label: 'Tất cả bài đăng', value: 'all' },
        { label: 'Bài đăng đã duyệt', value: 'published' },
        { label: 'Bài đăng chờ duyệt', value: 'pending' },
        { label: 'Bài đăng đã ẩn', value: 'hidden' },
      ],
      detailModal: { open: false },
      actionModal: { open: false },
      activeItem: null,
      actionForm: {
        reason: '',
      },
      quickActionType: 'reject',
      lightbox: {
        open: false,
        img: '',
      },
      mousedownWasOnBackdrop: false,
      autoApproveEnabled: false,
      autoApproveInterval: null,
      modalTab: 'post',
      postComments: [],
      loadingComments: false,
      activeCommentFilter: 'all',
      autoApproveConfig: {
        auto_approve_community_post: false,
        auto_approve_venue_post: false,
      },
      commentFiltersList: [
        { label: 'Tất cả bình luận', value: 'all' },
        { label: 'Đang hiển thị', value: 'visible' },
        { label: 'Đang bị ẩn', value: 'hidden' },
        { label: 'Bị báo cáo', value: 'reported' },
        { label: 'Cần theo dõi', value: 'attention' },
        { label: 'Đạt/Gần đạt ngưỡng', value: 'threshold' }
      ],
    };
  },
  computed: {
    searchPlaceholder() {
      return 'Tìm nội dung bài đăng, tác giả...';
    },
    filteredComments() {
      if (!this.postComments) return [];
      return this.postComments.filter(comment => {
        if (this.activeCommentFilter === 'visible') {
          return comment.status !== 'hidden';
        }
        if (this.activeCommentFilter === 'hidden') {
          return comment.status === 'hidden';
        }
        if (this.activeCommentFilter === 'reported') {
          return comment.is_reported;
        }
        if (this.activeCommentFilter === 'attention') {
          return comment.needs_attention;
        }
        if (this.activeCommentFilter === 'threshold') {
          return comment.threshold_reached || comment.near_threshold;
        }
        return true;
      });
    },
  },
  mounted() {
    this.loadData();
    this.fetchAutoApproveConfig();
  },
  beforeUnmount() {
    this.stopAutoApprove();
  },
  methods: {
    handleBackdropMousedown(event) {
      this.mousedownWasOnBackdrop = event.target === event.currentTarget;
    },
    handleBackdropClick(event, closeFn) {
      if (this.mousedownWasOnBackdrop && event.target === event.currentTarget) {
        closeFn();
      }
      this.mousedownWasOnBackdrop = false;
    },
    async loadData(page = 1) {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminModerationService.getQueue({
          type: this.activeTab,
          status: this.filters.status,
          search: this.filters.search,
          page,
        });
        const paginator = response.data || {};
        this.items = paginator.data || [];
        this.pagination = {
          current_page: paginator.current_page || 1,
          last_page: paginator.last_page || 1,
          total: paginator.total || this.items.length,
        };
      } catch (err) {
        this.error = err.message || 'Không thể tải danh sách bài viết.';
      } finally {
        this.loading = false;
      }
    },
    changeTab(tabValue) {
      this.activeTab = tabValue;
      if (this.filters.status !== 'pending') {
        this.autoApproveEnabled = false;
        this.stopAutoApprove();
      }
      this.clearAlerts();
      this.filters.search = '';
      this.loadData(1);
    },
    setStatus(status) {
      this.filters.status = status;
      if (status !== 'pending') {
        this.autoApproveEnabled = false;
        this.stopAutoApprove();
      }
      this.clearAlerts();
      this.loadData(1);
    },
    onFilterChange() {
      clearTimeout(this.filterTimer);
      this.filterTimer = setTimeout(() => this.loadData(1), 300);
    },
    refresh() {
      this.loadData(this.pagination.current_page);
    },
    clearAlerts() {
      this.error = '';
      this.message = '';
    },
    async openDetail(item) {
      this.clearAlerts();
      this.activeItem = item;
      this.actionForm.reason = '';
      this.detailModal.open = true;
      this.modalTab = 'post';
      this.postComments = [];
      this.activeCommentFilter = 'all';
      this.loadingComments = true;
      try {
        if (this.activeTab === 'community_posts') {
          const res = await adminUserService.postDetail(item.id);
          if (res.data) {
            this.activeItem = {
              ...item,
              ...res.data,
              author: {
                ...item.author,
                full_name: res.data.author_name || item.author?.full_name,
                avatar_url: res.data.author_avatar || item.author?.avatar_url,
              }
            };
            this.postComments = res.data.comments || [];
          }
        } else {
          this.postComments = [];
        }
      } catch (err) {
        console.error('Không thể tải chi tiết bài viết:', err);
        this.error = 'Không thể tải chi tiết bài viết và bình luận.';
      } finally {
        this.loadingComments = false;
      }
    },
    closeDetail() {
      this.detailModal.open = false;
    },
    async fetchAutoApproveConfig() {
      try {
        const res = await adminModerationService.getConfig();
        if (res.status === 'success') {
          this.autoApproveConfig = res.data;
        }
      } catch (err) {
        console.error('Lỗi khi tải cấu hình duyệt tự động:', err);
      }
    },
    async saveAutoApproveConfig() {
      try {
        await adminModerationService.saveConfig(this.autoApproveConfig);
        this.message = 'Cập nhật cấu hình duyệt tự động thành công.';
        setTimeout(() => this.message = '', 3000);
      } catch (err) {
        this.error = 'Không thể lưu cấu hình duyệt tự động.';
        setTimeout(() => this.error = '', 3000);
      }
    },
    async loadModalComments() {
      if (!this.activeItem || this.activeTab !== 'community_posts') return;
      this.modalTab = 'comments';
      this.loadingComments = true;
      try {
        const res = await adminUserService.postDetail(this.activeItem.id);
        this.postComments = res.data?.comments || [];
      } catch (err) {
        console.error('Không thể tải bình luận bài đăng:', err);
      } finally {
        this.loadingComments = false;
      }
    },
    async actionComment(comment, action) {
      if (!confirm(`Bạn có chắc chắn muốn thực hiện hành động này đối với bình luận của ${comment.user_name}?`)) {
        return;
      }
      try {
        const res = await adminUserService.processContentAction('comment', comment.id, action);
        this.message = res.message || 'Thực thi thành công.';
        setTimeout(() => this.message = '', 3000);
        await this.loadModalComments();
      } catch (err) {
        this.error = err.message || 'Lỗi khi xử lý bình luận.';
        setTimeout(() => this.error = '', 3000);
      }
    },
    openActionModal(item) {
      this.clearAlerts();
      this.activeItem = item;
      this.actionForm.reason = '';
      this.quickActionType = ['pending', 'pending_review', 'draft'].includes(item.status) ? 'reject' : 'hide';
      this.actionModal.open = true;
    },
    closeActionModal() {
      this.actionModal.open = false;
    },
    openLightbox(filePath) {
      this.lightbox.img = filePath;
      this.lightbox.open = true;
    },

    // Duyệt trực tiếp bài viết ở danh sách
    async approvePostDirect(item) {
      this.clearAlerts();
      this.savingAction = true;
      try {
        await adminModerationService.approvePost(this.activeTab, item.id);
        this.message = 'Duyệt hiển thị bài viết thành công.';
        await this.loadData(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Duyệt bài viết thất bại.';
        if (this.autoApproveEnabled) {
          this.autoApproveEnabled = false;
          this.stopAutoApprove();
        }
      } finally {
        this.savingAction = false;
      }
    },
    toggleAutoApprove() {
      if (this.autoApproveEnabled) {
        if (this.filters.status !== 'pending') {
          this.setStatus('pending');
        }
        this.startAutoApprove();
      } else {
        this.stopAutoApprove();
      }
      this.$emit('auto-approve-changed', this.autoApproveEnabled);
    },
    startAutoApprove() {
      this.stopAutoApprove();
      this.autoApproveInterval = setInterval(async () => {
        if (this.loading || this.savingAction) {
          return;
        }

        // 1. Kiểm tra tab hiện tại trước để ưu tiên duyệt
        let targetItem = this.items.find(item => ['pending', 'pending_review', 'draft'].includes(item.status));
        let targetType = this.activeTab;

        // 2. Nếu tab hiện tại không có bài viết chờ duyệt, quét qua các tab khác trong nền
        if (!targetItem) {
          const allTypes = ['community_posts', 'venue_posts', 'system_posts'];
          const otherTypes = allTypes.filter(t => t !== this.activeTab);

          for (const type of otherTypes) {
            try {
              const response = await adminModerationService.getQueue({
                type: type,
                status: 'pending',
                page: 1,
              });
              const paginator = response.data || {};
              const list = paginator.data || [];
              const found = list.find(item => ['pending', 'pending_review', 'draft'].includes(item.status));
              if (found) {
                targetItem = found;
                targetType = type;
                break; // Tìm thấy bài chờ duyệt thì dừng quét để tiến hành duyệt bài này
              }
            } catch (err) {
              console.error(`Lỗi quét tự động duyệt cho tab ${type}:`, err);
            }
          }
        }

        // 3. Nếu tìm thấy bài viết chờ duyệt ở bất kỳ tab nào, tiến hành duyệt
        if (targetItem) {
          this.savingAction = true;
          try {
            await adminModerationService.approvePost(targetType, targetItem.id);
            // Nếu bài được duyệt thuộc tab hiện tại, load lại danh sách để cập nhật màn hình
            if (targetType === this.activeTab) {
              await this.loadData(this.pagination.current_page);
            }
          } catch (err) {
            console.error('Duyệt tự động bài viết thất bại:', err);
          } finally {
            this.savingAction = false;
          }
        } else {
          // Nếu không còn bài viết chờ duyệt nào ở tất cả các tab, reload tab hiện tại để kiểm tra lại
          await this.loadData(1);
        }
      }, 5000);
    },
    stopAutoApprove() {
      if (this.autoApproveInterval) {
        clearInterval(this.autoApproveInterval);
        this.autoApproveInterval = null;
      }
      this.autoApproveEnabled = false;
      this.$emit('auto-approve-changed', false);
    },

    // Hành động xử lý nhanh cho Post
    async submitQuickAction() {
      if (!this.activeItem) return;
      this.savingAction = true;
      this.clearAlerts();
      try {
        if (this.quickActionType === 'reject') {
          await adminModerationService.rejectPost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Đã từ chối bài viết thành công.';
        } else if (this.quickActionType === 'hide') {
          await adminModerationService.hidePost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Đã ẩn bài viết thành công.';
        } else if (this.quickActionType === 'delete') {
          await adminModerationService.deletePost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Đã gỡ bỏ bài viết thành công.';
        }
        this.closeActionModal();
        await this.loadData(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Không thể thực thi hành động.';
      } finally {
        this.savingAction = false;
      }
    },

    // Xử lý kiểm duyệt từ trong Modal chi tiết
    async submitAction(actionType) {
      if (!this.activeItem) return;
      this.savingAction = true;
      this.clearAlerts();
      try {
        if (actionType === 'approve') {
          await adminModerationService.approvePost(this.activeTab, this.activeItem.id);
          this.message = 'Duyệt hiển thị bài viết thành công.';
        } else if (actionType === 'reject') {
          await adminModerationService.rejectPost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Từ chối bài viết thành công.';
        } else if (actionType === 'hide') {
          await adminModerationService.hidePost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Ẩn bài viết thành công.';
        } else if (actionType === 'delete') {
          await adminModerationService.deletePost(this.activeTab, this.activeItem.id, this.actionForm.reason);
          this.message = 'Gỡ bỏ bài viết thành công.';
        }
        this.closeDetail();
        await this.loadData(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Kiểm duyệt bài viết thất bại.';
      } finally {
        this.savingAction = false;
      }
    },

    getStatusLabel(status) {
      const map = {
        published: 'Đã duyệt',
        pending_review: 'Chờ duyệt',
        pending: 'Chờ duyệt',
        draft: 'Nháp / Chờ duyệt',
        hidden: 'Đã ẩn',
        rejected: 'Bị từ chối',
      };
      return map[status] || status || 'Chờ duyệt';
    },
    getStatusClass(status) {
      const map = {
        published: 'active',
        pending_review: 'pending_verify',
        pending: 'pending_verify',
        draft: 'pending_verify',
        hidden: 'locked',
        rejected: 'locked',
      };
      return map[status] || 'pending_verify';
    },
    getContentAuthor(item) {
      return item?.author || {};
    },
    getContentText(item) {
      return item?.content || '';
    },
    getContentMedia(item) {
      return item?.media || [];
    },
    getContentHashtags(item) {
      return item?.hashtags || [];
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
    initials(name) {
      return String(name || 'SG').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
    },
    timeAgo(dateParam) {
      if (!dateParam) return '';
      const date = new Date(dateParam);
      const now = new Date();
      const seconds = Math.round((now - date) / 1000);
      const minutes = Math.round(seconds / 60);
      const hours = Math.round(minutes / 60);
      const days = Math.round(hours / 24);
      if (seconds < 60) return `${seconds} giây trước`;
      if (minutes < 60) return `${minutes} phút trước`;
      if (hours < 24) return `${hours} giờ trước`;
      if (days < 7) return `${days} ngày trước`;
      return date.toLocaleDateString('vi-VN');
    },
  },
};
</script>

<style scoped>
@import "../../../css/admin/moderation.css";

.moderation-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 100%;
  margin: 0 auto;
}

.card {
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
}

.toolbar {
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
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: 0;
  background: transparent;
  color: #64748b;
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.2s;
}

.tab-btn:hover {
  background: #f8fafc;
  color: #0f172a;
}

.tab-btn.active {
  background: #f1f5f9;
  color: #0f172a;
}

.filters {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 800;
  color: var(--sg-text);
  min-width: 200px;
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

.field input,
.field select {
  height: 40px;
}

.field textarea {
  padding-top: 10px;
  resize: vertical;
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
  transition: all 0.15s;
}

.btn {
  height: 40px;
  padding: 0 16px;
  white-space: nowrap;
}

.btn.primary {
  background: #0f172a;
  color: #fff;
}

.btn.primary:hover {
  background: #1e293b;
}

.btn.ghost {
  background: #fff;
  border-color: var(--sg-border);
  color: var(--sg-text);
}

.btn.ghost:hover {
  background: #f8fafc;
}

.btn.danger {
  background: #dc2626;
  color: #fff;
}

.btn.danger:hover {
  background: #b91c1c;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.icon-btn {
  width: 34px;
  height: 34px;
  background: #f8fafc;
  border-color: #e2e8f0;
  color: #334155;
}

.icon-btn:hover {
  background: #f1f5f9;
}

.icon-btn.approve {
  color: #15803d;
}

.icon-btn.approve:hover {
  background: #f0fdf4;
}

.icon-btn.danger {
  color: #dc2626;
}

.icon-btn.danger:hover {
  background: #fef2f2;
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
  border: 1px solid #bbf7d0;
}

.notice.error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.state-box {
  display: flex;
  min-height: 240px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #64748b;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(15, 23, 42, 0.08);
  border-top-color: #0f172a;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.moderation-table {
  overflow: hidden;
}

.table-scroll {
  width: 100%;
  overflow-x: auto;
}

table {
  width: 100%;
  min-width: 1000px;
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
  font-size: 11px;
  font-weight: 900;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.main-title {
  color: var(--sg-text);
  font-weight: 800;
}

.muted {
  color: #94a3b8;
  font-size: 12px;
}

.content-preview,
.desc-text {
  max-width: 300px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  font-size: 14px;
}

.reported-brief {
  display: flex;
  align-items: center;
  gap: 8px;
}

.text-truncate {
  max-width: 240px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.badge {
  display: inline-flex;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.badge.type-comment {
  background: #e0f2fe;
  color: #0369a1;
}

.badge.type-venue {
  background: #f3e8ff;
  color: #6b21a8;
}

.badge.type-community {
  background: #dcfce7;
  color: #166534;
}

.badge-cluster {
  display: inline-block;
  margin-top: 4px;
  padding: 2px 6px;
  background: #f1f5f9;
  color: #475569;
  font-size: 11px;
  font-weight: 800;
  border-radius: 4px;
}

.tag {
  display: inline-block;
  background: #f1f5f9;
  color: #0f172a;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 12px;
  margin-right: 4px;
  margin-bottom: 4px;
}

.media-preview-box {
  position: relative;
  width: 48px;
  height: 48px;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--sg-border);
}

.media-thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.media-count {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 900;
}

.status {
  display: inline-flex;
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
}

.status-rejected {
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
  padding: 16px;
  border-top: 1px solid var(--sg-border);
  background: #f8fafc;
}

/* MODAL & DETAIL GRID */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  width: min(1000px, 94vw);
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.modal.small {
  width: min(550px, 92vw);
}

.modal-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--sg-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 900;
  color: #0f172a;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
  flex: 1;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1.2fr 0.8fr;
  gap: 20px;
}

@media (max-width: 768px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}

.detail-card {
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  padding: 16px;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-card.highlight {
  background: #f8fafc;
  border-color: #e2e8f0;
}

.section-title {
  margin: 0;
  font-size: 15px;
  font-weight: 900;
  color: #0f172a;
  border-left: 3px solid #10b981;
  padding-left: 8px;
}

.reported-comment-context {
  background: #fdf2f8;
  border: 1px solid #fbcfe8;
  border-radius: 6px;
  padding: 12px;
}

.context-label {
  font-size: 11px;
  font-weight: 900;
  color: #9d174d;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.parent-post-quote {
  margin: 0;
  font-style: italic;
  font-size: 13px;
  color: #475569;
}

.parent-post-quote cite {
  display: block;
  font-style: normal;
  font-weight: 800;
  font-size: 11px;
  margin-top: 4px;
  color: #64748b;
}

.author-block {
  display: flex;
  align-items: center;
  gap: 12px;
}

.author-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid var(--sg-border);
}

.content-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.6;
  color: #0f172a;
  white-space: pre-line;
}

.media-gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
  gap: 8px;
  margin-top: 12px;
}

.media-item {
  aspect-ratio: 1;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid var(--sg-border);
  cursor: zoom-in;
}

.media-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.2s;
}

.media-item img:hover {
  transform: scale(1.05);
}

.hashtags-box {
  margin-top: 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.hashtag-item {
  background: #f1f5f9;
  color: #0f172a;
  font-size: 12px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 4px;
}

.report-meta {
  margin: 0;
  display: grid;
  grid-template-columns: auto 1fr;
  row-gap: 8px;
  column-gap: 12px;
  font-size: 13.5px;
}

.report-meta dt {
  font-weight: 800;
  color: #64748b;
}

.report-meta dd {
  margin: 0;
  color: #0f172a;
}

.desc-box {
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 6px;
  padding: 8px 12px;
  font-style: italic;
  max-height: 100px;
  overflow-y: auto;
}

.resolve-form,
.action-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 8px;
}

.action-options-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 12px 0;
}

.action-radio {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #334155;
  cursor: pointer;
}

.modal-footer {
  padding: 16px 20px;
  border-top: 1px solid var(--sg-border);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  background: #f8fafc;
  border-bottom-left-radius: 12px;
  border-bottom-right-radius: 12px;
}



.text-danger {
  color: #dc2626 !important;
}

.text-warning {
  color: #d97706 !important;
}

/* LIGHTBOX */
.lightbox-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.9);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  cursor: zoom-out;
}

.lightbox-img {
  max-width: 90vw;
  max-height: 90vh;
  object-fit: contain;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  border-radius: 4px;
}

/* AUTO APPROVE TOGGLE */
.auto-approve-wrapper {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #f1f5f9;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
}

.switch {
  position: relative;
  display: inline-block;
  width: 40px;
  height: 22px;
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
  transition: .4s;
  border-radius: 22px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 16px;
  width: 16px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .4s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: #10b981;
}

input:focus + .slider {
  box-shadow: 0 0 1px #10b981;
}

input:checked + .slider:before {
  transform: translateX(18px);
}

.switch-label {
  font-size: 13px;
  font-weight: 700;
  color: #334155;
}

/* Custom styling overrides for alignment and modern aesthetics */
.sg-shell-admin .content-area .moderation-page .toolbar.card {
  display: flex !important;
  flex-direction: column !important;
  align-items: stretch !important;
  gap: 16px !important;
  padding: 20px !important;
}

.sg-shell-admin .content-area .moderation-page .filters {
  display: flex !important;
  flex-direction: row !important;
  align-items: flex-end !important;
  justify-content: flex-start !important;
  gap: 16px !important;
  width: 100% !important;
  flex-wrap: wrap !important;
}

.moderation-page .filters > .field.compact {
  flex: 1 1 240px !important;
  max-width: 320px !important;
  margin-bottom: 0 !important;
}

.moderation-page .auto-approve-wrapper {
  height: 42px !important;
  display: inline-flex !important;
  align-items: center !important;
  padding: 0 16px !important;
  background: #f8fafc !important;
  border: 1px solid var(--admin-border) !important;
  border-radius: var(--admin-radius) !important;
  margin-bottom: 0 !important;
  box-sizing: border-box !important;
}

.moderation-page .btn-refresh {
  height: 42px !important;
  min-height: 42px !important;
  margin-left: auto !important;
}

.status-tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 8px;
}

.status-tabs button {
  border: 1px solid #dbe3ef;
  background: #fff;
  border-radius: 8px;
  padding: 8px 14px;
  font-weight: 800;
  cursor: pointer;
  font-size: 13px;
  color: #475569;
  transition: all 0.15s;
}

.status-tabs button.active {
  background: #dcfce7;
  border-color: #22c55e;
  color: #166534;
}

.status-tabs button:hover:not(.active) {
  background: #f8fafc;
  border-color: #cbd5e1;
  color: #0f172a;
}

.status.active {
  background: #dcfce7;
  color: #166534;
}

.status.locked {
  background: #fee2e2;
  color: #b91c1c;
}

.status.pending_verify {
  background: #fef3c7;
  color: #92400e;
}
</style>

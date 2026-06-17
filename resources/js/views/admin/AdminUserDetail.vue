<template>
  <section class="user-detail">
    <header class="page-head">
      <div>
        <RouterLink class="back-link" to="/admin/users">← Quay lại danh sách</RouterLink>
        <h2>{{ profile.full_name || 'Chi tiết tài khoản' }}</h2>
      </div>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="loading" class="state-card">Đang tải chi tiết tài khoản...</div>

    <template v-else-if="detail">
      <div class="detail-layout">
        <!-- SIDEBAR TRÁI -->
        <aside class="sidebar-panel">
          <div class="avatar">{{ initials(profile.full_name || profile.username) }}</div>
          <strong class="sidebar-name">{{ profile.full_name || '-' }}</strong>
          <span class="sidebar-meta">{{ profile.email || '-' }}</span>
          <span class="sidebar-meta">Tham gia: {{ date(profile.created_at) }}</span>

          <div class="sidebar-stats">
            <div class="sidebar-stat">
              <span>Report chưa xử lý</span>
              <strong :class="{ 'text-red': (detail.reports_summary?.reports_14_days || 0) >= 3 }">
                {{ detail.reports_summary?.reports_14_days || 0 }}
              </strong>
              <span v-if="(detail.reports_summary?.reports_14_days || 0) >= 3" class="badge-report">⚠ Cảnh báo</span>
            </div>
            <div class="sidebar-stat">
              <span>Trạng thái</span>
              <span class="status" :class="profile.status">{{ profile.status_label }}</span>
              <small v-if="profile.status === 'locked' && profile.locked_until" class="lock-until">đến {{ dateTime(profile.locked_until) }}</small>
              <small v-else-if="profile.status === 'locked'" class="lock-until">Vĩnh viễn</small>
            </div>
          </div>

          <div class="sidebar-actions">
            <button v-if="profile.status !== 'locked'" class="btn danger" type="button" @click="openLockModal">
              Khóa tài khoản
            </button>
            <button v-else class="btn" type="button" @click="openUnlockModal">
              Mở khóa tài khoản
            </button>
          </div>
        </aside>

        <!-- CONTENT PHẢI + TABS -->
        <div class="content-panel">
          <nav class="tabs" aria-label="Tab chi tiết tài khoản">
            <button v-for="tab in tabs" :key="tab.value" type="button" :class="{ active: activeTab === tab.value }" @click="switchTab(tab.value)">
              {{ tab.label }}
            </button>
          </nav>

          <!-- Tab Tổng quan -->
          <section v-if="activeTab === 'overview'" class="panel">
            <h3>Tổng quan</h3>
            <div class="info-grid">
              <InfoItem label="Họ tên" :value="profile.full_name" />
              <InfoItem label="Username" :value="profile.username" />
              <InfoItem label="Email" :value="profile.email" />
              <InfoItem label="Số điện thoại" :value="profile.phone" />
              <InfoItem label="Trạng thái" :value="profile.status_label" />
              <InfoItem label="Vai trò hiện tại" :value="profile.role_labels?.join(', ')" />
              <InfoItem label="Ngày tạo" :value="dateTime(profile.created_at)" />
              <InfoItem label="Cập nhật gần nhất" :value="dateTime(profile.updated_at)" />
              <InfoItem label="Lý do khóa" :value="profile.status_reason" />
              <InfoItem label="Người khóa" :value="profile.locked_by_name" />
              <InfoItem label="Khóa từ" :value="dateTime(profile.locked_at)" />
              <InfoItem label="Khóa đến" :value="dateTime(profile.locked_until)" />
            </div>
          </section>

          <!-- Tab Bình luận -->
          <section v-if="activeTab === 'comments'" class="panel">
            <h3>Bình luận của người dùng</h3>
            <div v-if="commentsLoading" class="state">Đang tải bình luận...</div>
            <template v-else>
              <div class="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Nội dung</th>
                      <th>Bài viết</th>
                      <th>Số reply</th>
                      <th>Ngày đăng</th>
                      <th>Thao tác</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="comments.length === 0">
                      <td colspan="5" class="state">Chưa có bình luận.</td>
                    </tr>
                    <tr v-for="comment in comments" :key="comment.id">
                      <td>{{ truncate(comment.content, 100) }}</td>
                      <td>{{ comment.post_content ? truncate(comment.post_content, 50) : '-' }}</td>
                      <td>{{ comment.replies_count || 0 }}</td>
                      <td>{{ dateTime(comment.created_at) }}</td>
                      <td>
                        <button class="btn-sm" type="button" @click="openCommentDetail(comment.id)">Xem chi tiết</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <footer class="pagination" v-if="commentsMeta.total > 0">
                <span>{{ comments.length }} / {{ commentsMeta.total }}</span>
                <div>
                  <button class="btn-sm" :disabled="commentsMeta.current_page <= 1" @click="loadComments(commentsMeta.current_page - 1)">‹</button>
                  <span>{{ commentsMeta.current_page }} / {{ commentsMeta.last_page }}</span>
                  <button class="btn-sm" :disabled="commentsMeta.current_page >= commentsMeta.last_page" @click="loadComments(commentsMeta.current_page + 1)">›</button>
                </div>
              </footer>
            </template>
          </section>

          <!-- Tab Bài đăng -->
          <section v-if="activeTab === 'posts'" class="panel">
            <h3>Bài đăng của người dùng</h3>
            <div v-if="postsLoading" class="state">Đang tải bài đăng...</div>
            <template v-else>
              <div class="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Nội dung (tiêu đề)</th>
                      <th>Số bình luận</th>
                      <th>Số like</th>
                      <th>Trạng thái</th>
                      <th>Ngày đăng</th>
                      <th>Thao tác</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="posts.length === 0">
                      <td colspan="6" class="state">Chưa có bài đăng.</td>
                    </tr>
                    <tr v-for="post in posts" :key="post.id">
                      <td>{{ truncate(post.content, 80) }}</td>
                      <td>{{ post.comment_count || 0 }}</td>
                      <td>{{ post.like_count || 0 }}</td>
                      <td>
                        <span class="status" :class="post.status">{{ postStatusLabel(post.status) }}</span>
                      </td>
                      <td>{{ dateTime(post.created_at) }}</td>
                      <td>
                        <RouterLink class="btn-sm" :to="{ name: 'admin-post-detail', params: { id: post.id } }">Xem chi tiết</RouterLink>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <footer class="pagination" v-if="postsMeta.total > 0">
                <span>{{ posts.length }} / {{ postsMeta.total }}</span>
                <div>
                  <button class="btn-sm" :disabled="postsMeta.current_page <= 1" @click="loadPosts(postsMeta.current_page - 1)">‹</button>
                  <span>{{ postsMeta.current_page }} / {{ postsMeta.last_page }}</span>
                  <button class="btn-sm" :disabled="postsMeta.current_page >= postsMeta.last_page" @click="loadPosts(postsMeta.current_page + 1)">›</button>
                </div>
              </footer>
            </template>
          </section>

          <!-- Tab Lịch sử khóa -->
          <section v-if="activeTab === 'lock-history'" class="panel">
            <h3>Lịch sử khóa / mở khóa</h3>
            <div v-if="lockLogsLoading" class="state">Đang tải lịch sử...</div>
            <template v-else>
              <div class="timeline">
                <article v-for="log in lockLogs" :key="log.id" class="timeline-item" :class="log.action">
                  <div class="timeline-icon">{{ log.action === 'locked' ? '🔒' : '🔓' }}</div>
                  <div class="timeline-body">
                    <strong>{{ log.action_label }}</strong>
                    <span v-if="log.reason">{{ log.reason }}</span>
                    <span class="timeline-meta">
                      {{ log.performer_label }} · {{ dateTime(log.created_at) }}
                      <template v-if="log.action === 'locked'"> · {{ log.lock_until_label }}</template>
                    </span>
                    <span v-if="log.auto_triggered" class="badge-auto">Tự động</span>
                  </div>
                </article>
                <p v-if="lockLogs.length === 0" class="muted">Chưa có lịch sử khóa/mở khóa.</p>
              </div>
              <footer class="pagination" v-if="lockLogsMeta.total > 0">
                <span>{{ lockLogs.length }} / {{ lockLogsMeta.total }}</span>
                <div>
                  <button class="btn-sm" :disabled="lockLogsMeta.current_page <= 1" @click="loadLockLogs(lockLogsMeta.current_page - 1)">‹</button>
                  <span>{{ lockLogsMeta.current_page }} / {{ lockLogsMeta.last_page }}</span>
                  <button class="btn-sm" :disabled="lockLogsMeta.current_page >= lockLogsMeta.last_page" @click="loadLockLogs(lockLogsMeta.current_page + 1)">›</button>
                </div>
              </footer>
            </template>
          </section>

          <!-- Tab Cảnh báo & Báo cáo (giữ nguyên từ bản cũ) -->
          <section v-if="activeTab === 'warnings'" class="panel">
            <h3>Cảnh báo & báo cáo</h3>
            <p class="notice">{{ detail.warning_summary?.message }}</p>
            <div class="metric-row">
              <Metric label="Report 7 ngày" :value="detail.warning_summary?.reports_7_days || 0" />
              <Metric label="Report 14 ngày" :value="detail.warning_summary?.reports_14_days || 0" />
              <Metric label="Report 30 ngày" :value="detail.warning_summary?.reports_30_days || 0" />
              <Metric label="Khiếu nại mở" :value="detail.warning_summary?.complaints_open || 0" />
            </div>
          </section>

          <!-- Tab Audit log (giữ nguyên) -->
          <section v-if="activeTab === 'audit'" class="panel">
            <h3>Lịch sử thao tác / Audit log</h3>
            <div class="list-box">
              <article v-for="log in detail.audit_logs" :key="log.id">
                <strong>{{ log.action_label }}</strong>
                <span>{{ log.actor_name || 'Hệ thống' }} · {{ dateTime(log.created_at) }}</span>
                <span v-if="log.reason">Lý do: {{ log.reason }}</span>
              </article>
              <p v-if="!detail.audit_logs.length" class="muted">Chưa có audit log.</p>
            </div>
          </section>
        </div>
      </div>
    </template>

    <!-- Modal khóa tài khoản -->
    <div v-if="showLockModal" class="modal-backdrop" @click.self="showLockModal = false">
      <form class="modal" @submit.prevent="submitLock">
        <h3>Khóa tài khoản</h3>
        <p class="muted">{{ profile.full_name || profile.username }}</p>
        <label>
          <span>Lý do khóa *</span>
          <textarea v-model.trim="lockForm.reason" rows="4" required placeholder="Nhập lý do khóa tài khoản"></textarea>
        </label>
        <label>
          <span>Thời hạn khóa</span>
          <select v-model="lockForm.duration_hours">
            <option :value="1">1 giờ</option>
            <option :value="24">24 giờ</option>
            <option :value="168">7 ngày</option>
            <option :value="720">30 ngày</option>
            <option :value="null">Vĩnh viễn</option>
          </select>
        </label>
        <footer>
          <button type="button" class="btn secondary" @click="showLockModal = false">Hủy</button>
          <button type="submit" class="btn danger" :disabled="saving">Xác nhận khóa</button>
        </footer>
      </form>
    </div>

    <!-- Modal mở khóa (confirm dialog) -->
    <div v-if="showUnlockModal" class="modal-backdrop" @click.self="showUnlockModal = false">
      <form class="modal" @submit.prevent="submitUnlock">
        <h3>Mở khóa tài khoản</h3>
        <p class="muted">{{ profile.full_name || profile.username }}</p>
        <p>Bạn có chắc chắn muốn mở khóa tài khoản này?</p>
        <label>
          <span>Lý do mở khóa *</span>
          <textarea v-model.trim="unlockForm.reason" rows="3" required placeholder="Nhập lý do mở khóa"></textarea>
        </label>
        <footer>
          <button type="button" class="btn secondary" @click="showUnlockModal = false">Hủy</button>
          <button type="submit" class="btn" :disabled="saving">Xác nhận mở khóa</button>
        </footer>
      </form>
    </div>

    <!-- Modal chi tiết comment -->
    <div v-if="commentDetailData" class="modal-backdrop" @click.self="commentDetailData = null">
      <div class="modal modal-lg">
        <h3>Chi tiết bình luận</h3>
        <div class="comment-detail-body">
          <div class="comment-main">
            <strong>{{ commentDetailData.user_name }}</strong>
            <p>{{ commentDetailData.content }}</p>
            <small>{{ dateTime(commentDetailData.created_at) }} {{ commentDetailData.is_edited ? '(đã chỉnh sửa)' : '' }}</small>
          </div>
          <div v-if="commentDetailData.post" class="comment-post-link">
            <span>Bài viết gốc:</span>
            <p>{{ truncate(commentDetailData.post.content, 200) }}</p>
          </div>
          <div v-if="commentDetailData.replies && commentDetailData.replies.length" class="comment-replies">
            <h4>Trả lời ({{ commentDetailData.replies.length }})</h4>
            <article v-for="reply in commentDetailData.replies" :key="reply.id" class="reply-item">
              <strong>{{ reply.user_name }}</strong>
              <p>{{ reply.content }}</p>
              <small>{{ dateTime(reply.created_at) }}</small>
            </article>
          </div>
        </div>
        <footer>
          <button type="button" class="btn secondary" @click="commentDetailData = null">Đóng</button>
        </footer>
      </div>
    </div>
  </section>
</template>

<script>
import { adminUserService } from '../../services/adminUserService.js';

const InfoItem = {
  props: { label: String, value: [String, Number] },
  template: '<div class="info-item"><span>{{ label }}</span><strong>{{ value || "-" }}</strong></div>',
};

const Metric = {
  props: { label: String, value: [String, Number] },
  template: '<div class="metric"><span>{{ label }}</span><strong>{{ value }}</strong></div>',
};

export default {
  name: 'AdminUserDetail',
  components: { InfoItem, Metric },
  data() {
    return {
      detail: null,
      activeTab: 'overview',
      loading: false,
      saving: false,
      error: '',
      success: '',

      // Lock/Unlock modals
      showLockModal: false,
      showUnlockModal: false,
      lockForm: { reason: '', duration_hours: 24 },
      unlockForm: { reason: '' },

      // Comments tab
      comments: [],
      commentsMeta: { current_page: 1, last_page: 1, total: 0 },
      commentsLoading: false,

      // Posts tab
      posts: [],
      postsMeta: { current_page: 1, last_page: 1, total: 0 },
      postsLoading: false,

      // Lock logs tab
      lockLogs: [],
      lockLogsMeta: { current_page: 1, last_page: 1, total: 0 },
      lockLogsLoading: false,

      // Comment detail modal
      commentDetailData: null,
      commentDetailLoading: false,

      tabs: [
        { value: 'overview', label: 'Tổng quan' },
        { value: 'comments', label: 'Bình luận' },
        { value: 'posts', label: 'Bài đăng' },
        { value: 'lock-history', label: 'Lịch sử khóa' },
        { value: 'warnings', label: 'Cảnh báo' },
        { value: 'audit', label: 'Audit log' },
      ],
    };
  },
  computed: {
    profile() {
      return this.detail?.profile || {};
    },
  },
  mounted() {
    this.loadDetail();
  },
  methods: {
    switchTab(tab) {
      this.activeTab = tab;
      if (tab === 'comments' && this.comments.length === 0) this.loadComments(1);
      if (tab === 'posts' && this.posts.length === 0) this.loadPosts(1);
      if (tab === 'lock-history' && this.lockLogs.length === 0) this.loadLockLogs(1);
    },

    async loadDetail() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminUserService.show(this.$route.params.id);
        const data = response.data || {};
        if (!data.profile && data.user) data.profile = data.user;
        data.warning_summary = data.warning_summary || {};
        data.reports_summary = data.reports_summary || { recent: [] };
        data.complaints_summary = data.complaints_summary || { recent: [] };
        data.wallet_summary = data.wallet_summary || { ledgers: [] };
        data.booking_summary = data.booking_summary || {};
        data.recent_bookings = data.recent_bookings || [];
        data.roles = data.roles || [];
        data.permission_revokes = data.permission_revokes || [];
        data.audit_logs = data.audit_logs || [];
        this.detail = data;
      } catch (err) {
        this.error = err.message || 'Không tải được chi tiết tài khoản.';
      } finally {
        this.loading = false;
      }
    },

    async loadComments(page = 1) {
      this.commentsLoading = true;
      try {
        // Sử dụng API show để lấy comments (data đã có từ detail)
        // Nếu chưa có API riêng /users/{id}/comments, dùng data từ detail
        const response = await adminUserService.show(this.$route.params.id);
        const allComments = response.data?.comments || [];
        // Manual pagination
        const perPage = 20;
        const start = (page - 1) * perPage;
        this.comments = allComments.slice(start, start + perPage);
        this.commentsMeta = {
          current_page: page,
          last_page: Math.ceil(allComments.length / perPage) || 1,
          total: allComments.length,
        };
      } catch (err) {
        this.error = err.message || 'Không tải được bình luận.';
      } finally {
        this.commentsLoading = false;
      }
    },

    async loadPosts(page = 1) {
      this.postsLoading = true;
      try {
        const response = await adminUserService.show(this.$route.params.id);
        const allPosts = response.data?.posts || [];
        const perPage = 20;
        const start = (page - 1) * perPage;
        this.posts = allPosts.slice(start, start + perPage);
        this.postsMeta = {
          current_page: page,
          last_page: Math.ceil(allPosts.length / perPage) || 1,
          total: allPosts.length,
        };
      } catch (err) {
        this.error = err.message || 'Không tải được bài đăng.';
      } finally {
        this.postsLoading = false;
      }
    },

    async loadLockLogs(page = 1) {
      this.lockLogsLoading = true;
      try {
        const response = await adminUserService.lockLogs(this.$route.params.id, page);
        this.lockLogs = response.data || [];
        this.lockLogsMeta = response.meta || { current_page: 1, last_page: 1, total: 0 };
      } catch (err) {
        this.error = err.message || 'Không tải được lịch sử khóa.';
      } finally {
        this.lockLogsLoading = false;
      }
    },

    async openCommentDetail(commentId) {
      this.commentDetailLoading = true;
      try {
        const response = await adminUserService.commentDetail(commentId);
        this.commentDetailData = response.data || {};
      } catch (err) {
        this.error = err.message || 'Không tải được chi tiết bình luận.';
      } finally {
        this.commentDetailLoading = false;
      }
    },

    openLockModal() {
      this.lockForm = { reason: '', duration_hours: 24 };
      this.showLockModal = true;
    },
    openUnlockModal() {
      this.unlockForm = { reason: '' };
      this.showUnlockModal = true;
    },

    async submitLock() {
      this.saving = true;
      this.error = '';
      try {
        const response = await adminUserService.lockUser(this.profile.id, {
          reason: this.lockForm.reason,
          duration_hours: this.lockForm.duration_hours,
        });
        this.success = response.message || 'Khóa tài khoản thành công.';
        this.showLockModal = false;
        await this.loadDetail();
        this.lockLogs = [];
        if (this.activeTab === 'lock-history') this.loadLockLogs(1);
      } catch (err) {
        this.error = err.message || 'Không thể khóa tài khoản.';
      } finally {
        this.saving = false;
      }
    },

    async submitUnlock() {
      this.saving = true;
      this.error = '';
      try {
        const response = await adminUserService.unlockUser(this.profile.id, {
          reason: this.unlockForm.reason,
        });
        this.success = response.message || 'Mở khóa tài khoản thành công.';
        this.showUnlockModal = false;
        await this.loadDetail();
        this.lockLogs = [];
        if (this.activeTab === 'lock-history') this.loadLockLogs(1);
      } catch (err) {
        this.error = err.message || 'Không thể mở khóa tài khoản.';
      } finally {
        this.saving = false;
      }
    },

    truncate(text, length) {
      if (!text) return '-';
      return text.length > length ? text.substring(0, length) + '...' : text;
    },
    postStatusLabel(status) {
      return { published: 'Công khai', draft: 'Nháp', hidden: 'Đã ẩn', visible: 'Công khai', pending: 'Chờ duyệt' }[status] || status || '-';
    },
    initials(name) {
      return String(name || 'SG').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    dateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
  },
};
</script>

<style scoped>
.user-detail { display: grid; gap: 16px; }
.page-head { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; }
.page-head h2 { margin: 6px 0; }
.page-head p, .muted, small { margin: 0; color: #64748b; }
.back-link { color: #15803d; font-weight: 800; text-decoration: none; }

/* Layout sidebar + content */
.detail-layout { display: grid; grid-template-columns: 280px 1fr; gap: 16px; align-items: start; }

/* Sidebar */
.sidebar-panel {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;
  display: flex; flex-direction: column; gap: 12px; align-items: center; text-align: center;
  position: sticky; top: 16px;
}
.avatar { width: 64px; height: 64px; border-radius: 50%; display: grid; place-items: center; background: #16a34a; color: #fff; font-weight: 900; font-size: 22px; }
.sidebar-name { font-size: 16px; }
.sidebar-meta { font-size: 13px; color: #64748b; }
.sidebar-stats { width: 100%; display: grid; gap: 10px; }
.sidebar-stat { padding: 10px; background: #f8fafc; border-radius: 8px; display: grid; gap: 4px; text-align: center; }
.sidebar-stat span { font-size: 12px; color: #64748b; }
.sidebar-stat strong { font-size: 16px; }
.text-red { color: #b91c1c; }
.sidebar-actions { width: 100%; display: grid; gap: 8px; }

/* Content */
.content-panel { display: grid; gap: 16px; min-width: 0; }

.panel, .state-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; }
.panel { display: grid; gap: 14px; }
.panel h3, .panel h4 { margin: 0; }

.tabs, .metric-row { display: flex; gap: 8px; flex-wrap: wrap; }
.tabs button { border: 1px solid #dbe3ef; background: #fff; border-radius: 8px; padding: 10px 14px; font-weight: 800; cursor: pointer; }
.tabs button.active { background: #dcfce7; border-color: #22c55e; color: #166534; }

.info-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
:deep(.info-item), .metric { display: grid; gap: 6px; padding: 12px; background: #f8fafc; border-radius: 10px; }
:deep(.info-item span), .metric span { color: #64748b; font-size: 13px; }
.metric strong { font-size: 20px; }

.notice { margin: 0; padding: 12px; border-radius: 10px; background: #f0fdf4; color: #166534; font-weight: 700; }
.list-box { display: grid; gap: 10px; }
.list-box article { display: grid; gap: 6px; padding: 12px; background: #f8fafc; border-radius: 10px; }

.table-wrap { overflow: auto; }
table { width: 100%; border-collapse: collapse; min-width: 600px; }
th, td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
.state { color: #64748b; text-align: center; padding: 20px; }

/* Timeline */
.timeline { display: grid; gap: 12px; }
.timeline-item { display: flex; gap: 12px; padding: 14px; background: #f8fafc; border-radius: 10px; border-left: 4px solid #e2e8f0; }
.timeline-item.locked { border-left-color: #ef4444; }
.timeline-item.unlocked { border-left-color: #22c55e; }
.timeline-icon { font-size: 20px; }
.timeline-body { display: grid; gap: 4px; }
.timeline-meta { font-size: 12px; color: #64748b; }
.badge-auto { display: inline-flex; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; background: #dbeafe; color: #1e40af; width: fit-content; }
.badge-report { display: inline-flex; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; background: #fee2e2; color: #b91c1c; }

/* Buttons */
.btn { border: 0; border-radius: 8px; font-weight: 800; cursor: pointer; padding: 10px 14px; background: #dcfce7; color: #166534; }
.btn.secondary { background: #f1f5f9; color: #0f172a; }
.btn.danger { background: #fee2e2; color: #b91c1c; }
.btn-sm { border: 1px solid #dbe3ef; background: #fff; border-radius: 6px; padding: 6px 10px; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; color: #334155; }

.status { border-radius: 999px; padding: 4px 8px; font-size: 12px; font-weight: 800; background: #e2e8f0; }
.status.active, .status.visible, .status.published { background: #dcfce7; color: #166534; }
.status.locked, .status.hidden { background: #fee2e2; color: #b91c1c; }
.status.pending_verify, .status.pending, .status.draft { background: #fef3c7; color: #92400e; }
.lock-until { display: block; color: #b91c1c; font-size: 11px; }

.alert { padding: 12px; border-radius: 10px; font-weight: 700; }
.error { background: #fee2e2; color: #b91c1c; }
.success { background: #dcfce7; color: #166534; }
.pagination { display: flex; justify-content: space-between; gap: 12px; align-items: center; color: #64748b; font-size: 13px; }
.pagination div { display: flex; gap: 8px; align-items: center; }

/* Modals */
.modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.56); display: grid; place-items: center; z-index: 500; padding: 20px; }
.modal { width: min(640px, calc(100vw - 32px)); padding: 22px; background: #fff; border-radius: 12px; display: grid; gap: 16px; }
.modal-lg { width: min(800px, calc(100vw - 32px)); max-height: 80vh; overflow-y: auto; }
.modal h3 { margin: 0; }
.modal footer { display: flex; justify-content: flex-end; gap: 10px; }
label { display: grid; gap: 6px; font-weight: 800; }
input, select, textarea { border: 1px solid #dbe3ef; border-radius: 8px; padding: 10px; font: inherit; }
textarea { resize: vertical; }

/* Comment detail modal */
.comment-detail-body { display: grid; gap: 14px; }
.comment-main { display: grid; gap: 6px; padding: 14px; background: #f8fafc; border-radius: 10px; }
.comment-main p { margin: 0; white-space: pre-wrap; }
.comment-post-link { padding: 12px; background: #f0fdf4; border-radius: 8px; }
.comment-post-link p { margin: 4px 0 0; color: #334155; }
.comment-replies { display: grid; gap: 8px; }
.comment-replies h4 { margin: 0; }
.reply-item { padding: 10px; background: #f8fafc; border-radius: 8px; display: grid; gap: 4px; }
.reply-item p { margin: 0; }

@media (max-width: 900px) {
  .detail-layout { grid-template-columns: 1fr; }
  .sidebar-panel { position: static; }
  .info-grid { grid-template-columns: 1fr; }
}
</style>

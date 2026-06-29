<template>
  <div class="matchmaking-page">
 
    <!-- Alerts -->
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
 
      <!-- Filter and Search -->
      <div class="filters-row">
        <label class="field compact search-field">
          <span>Tìm kiếm</span>
          <input
            v-model="searchQuery"
            type="search"
            placeholder="Tìm theo tiêu đề, người đăng..."
            @input="onSearchInput"
          />
        </label>
 
        <label class="field compact select-field">
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
      <p>Đang tải danh sách bài giao lưu...</p>
    </div>
 
    <!-- Empty Screen -->
    <div v-else-if="posts.length === 0" class="state-box card">
      <AppIcon name="fileText" size="36" />
      <p>Không tìm thấy bài giao lưu nào.</p>
    </div>
 
    <!-- Matchmaking Posts Table -->
    <div v-else class="table-container card">
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Người đăng</th>
              <th>Thông tin buổi chơi</th>
              <th>Yêu cầu ghép cặp</th>
              <th>Trạng thái</th>
              <th>Booking liên quan</th>
              <th class="right">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="post in posts" :key="post.id" class="post-row">
              <td>
                <div class="author-cell">
                  <strong>{{ post.author?.full_name || post.author?.username || 'Người chơi' }}</strong>
                  <span class="muted small">{{ post.author?.phone || 'Không có SĐT' }}</span>
                  <span class="muted small">{{ post.author?.email || '' }}</span>
                </div>
              </td>
              <td>
                <div class="info-cell">
                  <div class="post-title">{{ post.title }}</div>
                  <p class="post-desc" v-if="post.description">{{ post.description }}</p>
                  <div class="post-time-location">
                    <AppIcon name="clock" size="14" class="muted-icon" />
                    <span>
                      {{ formatDate(post.booking?.booking_date) }}
                      ({{ formatTime(post.booking?.start_time) }} - {{ formatTime(post.booking?.end_time) }})
                    </span>
                  </div>
                  <div class="post-court">
                    <AppIcon name="building" size="14" class="muted-icon" />
                    <span>{{ post.booking?.venueCluster?.name }} · <strong>{{ post.booking?.venueCourt?.name }}</strong></span>
                  </div>
                </div>
              </td>
              <td>
                <div class="needed-cell">
                  <span class="needed-badge">Cần thêm: <strong>{{ post.needed_players }} người</strong></span>
                  <span class="cost-badge" v-if="post.cost_per_player > 0">
                    Chi phí: {{ formatCurrency(post.cost_per_player) }}/người
                  </span>
                </div>
              </td>
              <td>
                <span class="status-badge" :class="getStatusClass(post.status)">
                  {{ getStatusLabel(post.status) }}
                </span>
                <div v-if="post.status_reason" class="status-reason" :title="post.status_reason">
                  Lý do: {{ post.status_reason }}
                </div>
              </td>
              <td>
                <div v-if="post.booking" class="booking-link-cell">
                  <span class="booking-code">Mã: {{ post.booking.booking_code }}</span>
                  <router-link
                    :to="{
                      name: 'owner-counter-booking',
                      query: {
                        venue_cluster_id: post.booking.venue_cluster_id,
                        booking_date: post.booking.booking_date,
                        venue_court_id: post.booking.venue_court_id,
                        booking_id: post.booking.id,
                        booking_code: post.booking.booking_code,
                      }
                    }"
                    class="btn-link"
                  >
                    Xem lịch đặt sân
                  </router-link>
                </div>
                <span v-else class="muted">-</span>
              </td>
              <td class="right">
                <div class="actions-cell" v-if="post.status === 'open' || post.status === 'full'">
                  <button class="btn ghost btn-sm" type="button" @click="openHideModal(post)">
                    <span>Ẩn bài</span>
                  </button>
                  <button class="btn ghost danger btn-sm" type="button" @click="openReportModal(post)">
                    <span>Báo cáo</span>
                  </button>
                </div>
                <span v-else class="muted">-</span>
              </td>
            </tr>
          </tbody>
        </table>
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
 
    <!-- MODAL ẨN BÀI GIAO LƯU -->
    <div v-if="hideModal.open" class="modal-backdrop" @mousedown="handleBackdropMousedown" @click="handleBackdropClick($event, closeHideModal)">
      <div class="modal small" @mousedown.stop>
        <div class="modal-header">
          <h3>Ẩn bài viết giao lưu</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeHideModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>
        <form @submit.prevent="submitHide">
          <div class="modal-body form-grid">
            <p class="warning-text">Lưu ý: Hành động này sẽ chuyển trạng thái bài viết giao lưu thành <strong>Đóng</strong> và không thể hoàn tác.</p>
            <label class="field">
              <span>Lý do ẩn bài viết <span class="required">*</span></span>
              <textarea
                v-model="hideForm.reason"
                rows="4"
                placeholder="Nhập lý do ẩn gửi tới người chơi..."
                required
              ></textarea>
            </label>
          </div>
          <div class="modal-footer">
            <button class="btn ghost" type="button" @click="closeHideModal" :disabled="saving">Hủy</button>
            <button class="btn primary" type="submit" :disabled="saving || !hideForm.reason">
              <span>{{ saving ? 'Đang lưu...' : 'Ẩn bài' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
 
    <!-- MODAL BÁO CÁO VI PHẠM -->
    <div v-if="reportModal.open" class="modal-backdrop" @mousedown="handleBackdropMousedown" @click="handleBackdropClick($event, closeReportModal)">
      <div class="modal small" @mousedown.stop>
        <div class="modal-header">
          <h3>Báo cáo vi phạm bài giao lưu</h3>
          <button class="icon-btn" type="button" title="Đóng" @click="closeReportModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>
        <form @submit.prevent="submitReport">
          <div class="modal-body form-grid">
            <label class="field">
              <span>Lý do vi phạm <span class="required">*</span></span>
              <select v-model="reportForm.reason" required>
                <option value="" disabled>-- Chọn lý do --</option>
                <option value="spam">Spam quảng cáo</option>
                <option value="offensive">Nội dung phản cảm</option>
                <option value="fake">Thông tin giả mạo</option>
                <option value="harassment">Quấy rối / Đả kích</option>
                <option value="other">Lý do khác</option>
              </select>
            </label>
            <label class="field">
              <span>Mô tả chi tiết</span>
              <textarea
                v-model="reportForm.description"
                rows="4"
                placeholder="Mô tả cụ thể vi phạm để quản trị viên xử lý..."
              ></textarea>
            </label>
          </div>
          <div class="modal-footer">
            <button class="btn ghost" type="button" @click="closeReportModal" :disabled="saving">Hủy</button>
            <button class="btn primary danger" type="submit" :disabled="saving || !reportForm.reason">
              <span>{{ saving ? 'Đang gửi...' : 'Gửi báo cáo' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
 
<script>
import AppIcon from '../../components/AppIcon.vue';
import { ownerMatchmakingService } from '../../services/ownerMatchmaking.js';
import { venueClusterService } from '../../services/venueClusters.js';
 
export default {
  name: 'OwnerMatchmaking',
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
      searchQuery: '',
      searchTimer: null,
      pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },
      tabs: [
        { label: 'Tất cả', value: 'all', icon: 'layers' },
        { label: 'Đang mở', value: 'open', icon: 'clock' },
        { label: 'Đã đủ', value: 'full', icon: 'circleCheck' },
        { label: 'Đã đóng', value: 'closed', icon: 'lock' },
        { label: 'Đã hủy', value: 'cancelled', icon: 'circleX' },
      ],
      hideModal: {
        open: false,
        postId: null,
      },
      hideForm: {
        reason: '',
      },
      reportModal: {
        open: false,
        postId: null,
      },
      reportForm: {
        reason: '',
        description: '',
      },
      mousedownWasOnBackdrop: false,
    };
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.onClusterChangedEvent);
    await this.loadClusters();
    await this.loadPosts();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.onClusterChangedEvent);
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
          search: this.searchQuery,
        };
        if (this.activeTab !== 'all') {
          params.status = this.activeTab;
        }
 
        const response = await ownerMatchmakingService.list(params);
        const paginator = response.data || {};
        this.posts = paginator.data || [];
        this.pagination = {
          current_page: paginator.current_page || 1,
          last_page: paginator.last_page || 1,
          total: paginator.total || this.posts.length,
        };
      } catch (err) {
        this.error = err.message || 'Không thể tải danh sách bài giao lưu.';
      } finally {
        this.loading = false;
      }
    },
    onClusterChangedEvent() {
      this.loadPosts(1);
    },
    changeTab(tabValue) {
      this.activeTab = tabValue;
      this.loadPosts(1);
    },
    onSearchInput() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => {
        this.loadPosts(1);
      }, 400);
    },
    clearAlerts() {
      this.error = '';
      this.message = '';
    },
    formatDate(value) {
      if (!value) return '-';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      });
    },
    formatTime(time) {
      return (time || '').slice(0, 5);
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(Number(amount || 0));
    },
    getStatusLabel(status) {
      const map = {
        open: 'Đang mở',
        full: 'Đã đủ người',
        closed: 'Đã đóng',
        cancelled: 'Đã hủy',
      };
      return map[status] || status;
    },
    getStatusClass(status) {
      const map = {
        open: 'status-open',
        full: 'status-full',
        closed: 'status-closed',
        cancelled: 'status-cancelled',
      };
      return map[status] || '';
    },
 
    // Backdrop selection logic
    handleBackdropMousedown(event) {
      this.mousedownWasOnBackdrop = event.target === event.currentTarget;
    },
    handleBackdropClick(event, closeFn) {
      if (this.mousedownWasOnBackdrop && event.target === event.currentTarget) {
        closeFn();
      }
      this.mousedownWasOnBackdrop = false;
    },
 
    // Modal Hide logic
    openHideModal(post) {
      this.clearAlerts();
      this.hideForm.reason = '';
      this.hideModal.postId = post.id;
      this.hideModal.open = true;
    },
    closeHideModal() {
      this.hideModal.open = false;
    },
    async submitHide() {
      this.saving = true;
      this.clearAlerts();
      try {
        await ownerMatchmakingService.hide(this.hideModal.postId, this.hideForm);
        this.message = 'Ẩn bài giao lưu thành công.';
        this.closeHideModal();
        await this.loadPosts(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Ẩn bài giao lưu thất bại.';
      } finally {
        this.saving = false;
      }
    },
 
    // Modal Report logic
    openReportModal(post) {
      this.clearAlerts();
      this.reportForm.reason = '';
      this.reportForm.description = '';
      this.reportModal.postId = post.id;
      this.reportModal.open = true;
    },
    closeReportModal() {
      this.reportModal.open = false;
    },
    async submitReport() {
      this.saving = true;
      this.clearAlerts();
      try {
        await ownerMatchmakingService.report(this.reportModal.postId, this.reportForm);
        this.message = 'Gửi báo cáo vi phạm thành công. Admin sẽ sớm xem xét xử lý.';
        this.closeReportModal();
        await this.loadPosts(this.pagination.current_page);
      } catch (err) {
        this.error = err.message || 'Gửi báo cáo thất bại.';
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>
 
<style scoped>
/* ===================================================
   Matchmaking Page - CSS Variables Design System
   =================================================== */
 
.matchmaking-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1280px;
  margin: 0 auto;
}
 
/* ---- Notice banners ---- */
.notice {
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
}
 
.notice.success {
  background: var(--admin-primary-soft);
  color: var(--admin-text);
  border: 1px solid var(--admin-border);
}
 
.notice.error {
  background: var(--admin-danger-soft);
  color: var(--admin-danger-text);
  border: 1px solid var(--admin-danger);
}
.page-header h2 {
  font-size: 24px;
  font-weight: 850;
  color: #0f172a;
  margin: 0;
}
 
.muted {
  color: #64748b;
  margin: 4px 0 0;
  font-size: 14px;
}
 
.muted-icon {
  color: #94a3b8;
}
 
/* ---- Card base ---- */
.card {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 12px;
  box-shadow: var(--admin-shadow-card);
}
 
/* ---- Filter toolbar ---- */
.filter-toolbar {
  display: flex;
  flex-direction: column;
  gap: 0;
  overflow: hidden;
}
 
.tabs-header {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--admin-border);
  padding: 12px 16px 0;
  flex-wrap: wrap;
  background: var(--admin-surface);
}
 
.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 8px 14px;
  border: 0;
  background: transparent;
  color: var(--admin-faint);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  border-radius: 8px 8px 0 0;
  transition: color 0.18s, background 0.18s;
  position: relative;
  bottom: -1px;
  border-bottom: 2px solid transparent;
}
 
.tab-btn:hover {
  background: var(--admin-hover);
  color: var(--admin-text);
}
 
.tab-btn.active {
  color: var(--admin-text);
  font-weight: 900;
  border-bottom-color: var(--admin-text);
  background: transparent;
}
 
.filters-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px 16px;
  background: var(--admin-surface-muted);
  border-top: 1px solid var(--admin-border);
}
 
.field {
  display: flex;
  flex-direction: column;
  gap: 5px;
  font-size: 11px;
  font-weight: 700;
  color: var(--admin-faint);
  letter-spacing: 0.03em;
  text-transform: uppercase;
}
 
.field.compact {
  flex-direction: row;
  align-items: center;
  gap: 10px;
}
 
.search-field input {
  min-width: 260px;
  height: 36px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 0 12px;
  font-size: 13px;
  font-weight: 500;
  background: var(--admin-surface);
  color: var(--admin-text);
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
}
 
.search-field input:focus {
  border-color: var(--admin-blue);
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}
 
.select-field select {
  min-width: 180px;
  height: 36px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 0 10px;
  font-size: 13px;
  font-weight: 500;
  background: var(--admin-surface);
  color: var(--admin-text);
  outline: none;
  transition: border-color 0.15s;
}
 
.select-field select:focus {
  border-color: var(--admin-blue);
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}
 
/* ---- States ---- */
.state-box {
  display: flex;
  min-height: 220px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--admin-faint);
  text-align: center;
  padding: 32px;
}
 
.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid var(--admin-border);
  border-top-color: var(--admin-text);
  border-radius: 50%;
  animation: spin 0.9s infinite linear;
}
 
@keyframes spin {
  to { transform: rotate(360deg); }
}
 
/* ---- Table ---- */
.table-container {
  padding: 0;
  overflow: hidden;
}
 
.table-scroll {
  overflow-x: auto;
}
 
table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1000px;
}
 
th, td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid var(--admin-border);
  font-size: 13px;
  vertical-align: middle;
}
 
th {
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
  font-weight: 900;
  font-size: 10px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}
 
.post-row:hover {
  background: var(--admin-hover);
}
 
tbody tr:last-child td {
  border-bottom: 0;
}
 
/* ---- Author cell ---- */
.author-cell {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
 
.author-cell strong {
  color: var(--admin-text);
  font-weight: 700;
  font-size: 13px;
}
 
.muted {
  color: var(--admin-faint);
  font-size: 12px;
}
 
.muted-icon {
  color: var(--admin-faint);
}
 
/* ---- Info cell ---- */
.info-cell {
  display: flex;
  flex-direction: column;
  gap: 5px;
  max-width: 380px;
}
 
.post-title {
  font-weight: 800;
  color: var(--admin-text);
  font-size: 14px;
  line-height: 1.3;
}
 
.post-desc {
  margin: 0;
  color: var(--admin-faint);
  font-size: 12px;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
 
.post-time-location, .post-court {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--admin-muted);
}
 
/* ---- Needed cell ---- */
.needed-cell {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
 
.needed-badge {
  display: inline-block;
  background: var(--admin-blue-soft);
  color: var(--admin-blue);
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  width: fit-content;
}
 
.cost-badge {
  display: inline-block;
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  width: fit-content;
  border: 1px solid var(--admin-border);
}
 
/* ---- Status badges ---- */
.status-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 800;
  padding: 3px 10px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
 
.status-open {
  background: var(--admin-warning-soft);
  color: var(--admin-warning);
}
 
.status-full {
  background: var(--admin-primary-soft);
  color: var(--admin-text);
}
 
.status-closed {
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
  border: 1px solid var(--admin-border);
}
 
.status-cancelled {
  background: var(--admin-danger-soft);
  color: var(--admin-danger-text);
}
 
.status-reason {
  margin-top: 4px;
  font-size: 11px;
  color: var(--admin-danger);
  max-width: 150px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
 
/* ---- Booking link cell ---- */
.booking-link-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
 
.booking-code {
  font-weight: 700;
  font-size: 12px;
  color: var(--admin-text);
  font-family: monospace;
  background: var(--admin-surface-muted);
  padding: 2px 6px;
  border-radius: 4px;
  border: 1px solid var(--admin-border);
  width: fit-content;
}
 
.btn-link {
  color: var(--admin-blue);
  font-weight: 700;
  font-size: 12px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
 
.btn-link:hover {
  text-decoration: underline;
}
 
/* ---- Actions ---- */
.right {
  text-align: right;
}
 
.actions-cell {
  display: inline-flex;
  gap: 6px;
  justify-content: flex-end;
}
 
/* ---- Buttons ---- */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 34px;
  padding: 0 14px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.15s;
  white-space: nowrap;
}
 
.btn.primary {
  background: var(--admin-primary);
  color: var(--admin-bg);
  border-color: var(--admin-primary);
}
 
.btn.primary:hover {
  background: var(--admin-primary-light);
}
 
.btn.primary.danger {
  background: var(--admin-danger);
  color: #fff;
  border-color: var(--admin-danger);
}
 
.btn.primary.danger:hover {
  opacity: 0.85;
}
 
.btn.ghost {
  background: var(--admin-surface);
  border-color: var(--admin-border);
  color: var(--admin-text);
}
 
.btn.ghost:hover {
  background: var(--admin-hover);
}
 
.btn.ghost.danger {
  color: var(--admin-danger);
  border-color: var(--admin-danger);
}
 
.btn.ghost.danger:hover {
  background: var(--admin-danger-soft);
}
 
.btn-sm {
  height: 28px;
  padding: 0 10px;
  font-size: 12px;
  border-radius: 6px;
}
 
.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
 
/* ---- Icon button ---- */
.icon-btn {
  width: 30px;
  height: 30px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--admin-surface-muted);
  border: 1px solid var(--admin-border);
  border-radius: 50%;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
}
 
.icon-btn:hover {
  background: var(--admin-hover);
  color: var(--admin-text);
}
 
/* ---- Pagination ---- */
.pagination-bar {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  padding: 14px 16px;
  border-top: 1px solid var(--admin-border);
}
 
.page-info {
  font-size: 13px;
  font-weight: 700;
  color: #475569;
}
 
/* ====== MODAL ====== */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(3px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 500;
  padding: 16px;
}
 
.modal {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 14px;
  box-shadow: var(--admin-shadow-lg);
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
 
.modal.small {
  max-width: 460px;
}
 
.modal-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--admin-border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
 
.modal-header h3 {
  font-size: 16px;
  font-weight: 800;
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
  color: var(--admin-danger);
}
 
.warning-text {
  margin: 0;
  color: var(--admin-warning);
  background: var(--admin-warning-soft);
  border: 1px solid var(--admin-warning);
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.5;
}
 
.form-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.field textarea,
.field select {
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 500;
  background: var(--admin-surface);
  color: var(--admin-text);
  outline: none;
  transition: border-color 0.15s;
  width: 100%;
}

.field textarea:focus,
.field select:focus {
  border-color: var(--admin-blue);
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}


.modal-footer {
  padding: 14px 20px;
  border-top: 1px solid var(--admin-border);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background: var(--admin-surface-muted);
}

.small {
  font-size: 12px;
}

</style>

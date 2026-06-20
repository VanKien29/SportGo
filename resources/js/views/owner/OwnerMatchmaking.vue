<template>
  <div class="matchmaking-page">
    <div class="page-header">
      <div class="header-left">
        <h2>Giao lưu tại sân</h2>
        <p class="muted">Theo dõi và quản lý các hoạt động giao lưu bắt cặp từ người chơi tại các cụm sân của bạn.</p>
      </div>
    </div>
 
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
.matchmaking-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1280px;
  margin: 0 auto;
}
 
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
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
 
.card {
  background: #fff;
  border: 1px solid #e2e8f0;
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
  color: #64748b;
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  border-radius: 8px;
  transition: all 0.2s;
}
 
.tab-btn:hover {
  background: #f8fafc;
  color: #0f172a;
}
 
.tab-btn.active {
  background: #e6f4ea;
  color: #059669;
}
 
.filters-row {
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
  color: #334155;
}
 
.field.compact {
  flex-direction: row;
  align-items: center;
  gap: 10px;
}
 
.search-field input {
  min-width: 280px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 14px;
  font-weight: 500;
  outline: none;
  transition: border-color 0.15s;
}
 
.search-field input:focus {
  border-color: #059669;
}
 
.select-field select {
  min-width: 200px;
  height: 38px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 14px;
  font-weight: 500;
  background: #fff;
  outline: none;
  transition: border-color 0.15s;
}
 
.select-field select:focus {
  border-color: #059669;
}
 
.state-box {
  display: flex;
  min-height: 240px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #64748b;
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
 
/* Table Styles */
.table-container {
  padding: 8px;
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
  padding: 14px 16px;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
  font-size: 14px;
}
 
th {
  background: #f8fafc;
  color: #334155;
  font-weight: 850;
}
 
.post-row:hover {
  background: #f8fafc;
}
 
.author-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
 
.author-cell strong {
  color: #0f172a;
}
 
.small {
  font-size: 12px;
}
 
.info-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 400px;
}
 
.post-title {
  font-weight: 800;
  color: #0f172a;
  font-size: 15px;
}
 
.post-desc {
  margin: 0;
  color: #475569;
  font-size: 13px;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
 
.post-time-location, .post-court {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #334155;
}
 
.needed-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
 
.needed-badge {
  display: inline-block;
  background: #e0f2fe;
  color: #0369a1;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  width: fit-content;
}
 
.cost-badge {
  display: inline-block;
  background: #f1f5f9;
  color: #475569;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  width: fit-content;
}
 
.status-badge {
  display: inline-block;
  font-size: 12px;
  font-weight: 850;
  padding: 4px 10px;
  border-radius: 999px;
  text-transform: uppercase;
}
 
.status-open {
  background: #fef3c7;
  color: #d97706;
}
 
.status-full {
  background: #dcfce7;
  color: #15803d;
}
 
.status-closed {
  background: #f1f5f9;
  color: #64748b;
}
 
.status-cancelled {
  background: #fee2e2;
  color: #b91c1c;
}
 
.status-reason {
  margin-top: 4px;
  font-size: 12px;
  color: #dc2626;
  max-width: 150px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
 
.booking-link-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
 
.booking-code {
  font-weight: 700;
  color: #0f172a;
}
 
.btn-link {
  color: #059669;
  font-weight: 800;
  font-size: 13px;
  text-decoration: underline;
}
 
.btn-link:hover {
  color: #047857;
}
 
.right {
  text-align: right;
}
 
.actions-cell {
  display: inline-flex;
  gap: 8px;
  justify-content: flex-end;
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
 
.btn.primary.danger {
  background: #dc2626;
  color: #fff;
}
 
.btn.primary.danger:hover {
  background: #b91c1c;
}
 
.btn.ghost {
  background: #fff;
  border-color: #cbd5e1;
  color: #334155;
}
 
.btn.ghost:hover {
  background: #f8fafc;
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
  background: #f1f5f9;
  border: 0;
  border-radius: 50%;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
}
 
.icon-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
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
 
.pagination-bar {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 16px;
  padding: 12px 0 4px;
}
 
.page-info {
  font-size: 14px;
  font-weight: 700;
  color: #475569;
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
  z-index: 500;
  padding: 16px;
}
 
.modal {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
 
.modal-header h3 {
  font-size: 18px;
  font-weight: 850;
  color: #0f172a;
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
 
.warning-text {
  margin: 0;
  color: #92400e;
  background: #fffbeb;
  border: 1px solid #fef3c7;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.4;
}
 
.form-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
 
.field textarea, .field select {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 14px;
  font-weight: 500;
  background: #fff;
  color: #0f172a;
  outline: none;
  transition: border-color 0.15s;
}
 
.field textarea:focus, .field select:focus {
  border-color: #059669;
}
 
.modal-footer {
  padding: 16px 20px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
</style>

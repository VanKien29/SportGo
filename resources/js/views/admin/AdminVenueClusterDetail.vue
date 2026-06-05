<template>
  <div class="avcd-page">
    <!-- ── Back + Header ── -->
    <div class="avcd-header card">
      <button class="btn-back" @click="$router.push({ name: 'admin-venue-clusters' })">
        ← Quay lại
      </button>
      <div class="avcd-title-row">
        <div>
          <h2 class="avcd-title">{{ cluster?.name || '...' }}</h2>
          <p class="avcd-sub">{{ cluster?.address }}</p>
        </div>
        <div class="avcd-actions" v-if="cluster">
          <span class="status-badge" :class="`status-${cluster.status}`">
            {{ statusLabel(cluster.status) }}
          </span>
          <button
            v-if="cluster.status !== 'locked'"
            id="btn-lock-cluster"
            class="btn btn-danger"
            @click="openLockModal"
          >Khóa cụm sân</button>
          <button
            v-else
            id="btn-unlock-cluster"
            class="btn btn-success"
            :disabled="unlocking"
            @click="handleUnlock"
          >{{ unlocking ? 'Đang mở...' : 'Mở khóa' }}</button>
        </div>
      </div>
    </div>

    <!-- ── Loading / Error ── -->
    <div v-if="loading" class="state-box card">
      <div class="spinner"></div>
      <p>Đang tải dữ liệu cụm sân...</p>
    </div>
    <div v-else-if="error" class="state-box card error-box">
      <p>{{ error }}</p>
      <button class="btn btn-outline" @click="loadDetail">Thử lại</button>
    </div>

    <!-- ── Main content ── -->
    <template v-else-if="cluster">
      <!-- Tabs -->
      <div class="avcd-tabs card">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="tab-btn"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >{{ tab.label }}</button>
      </div>

      <!-- ┌ Tab: Thông tin ──────────────────────────────────────────── -->
      <div v-if="activeTab === 'info'" class="avcd-card card">
        <h3 class="section-title">Thông tin cụm sân</h3>
        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Tên cụm sân</span>
            <span class="info-value">{{ cluster.name }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Địa chỉ</span>
            <span class="info-value">{{ cluster.address }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Số điện thoại</span>
            <span class="info-value">{{ cluster.phone_contact || '—' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Chủ sân</span>
            <span class="info-value">
              {{ cluster.owner?.full_name }}
              <span class="muted">(@{{ cluster.owner?.username }})</span>
            </span>
          </div>
          <div class="info-item">
            <span class="info-label">Email chủ sân</span>
            <span class="info-value">{{ cluster.owner?.email || '—' }}</span>
          </div>
          <div class="info-item">
            <span class="info-label">Rating</span>
            <span class="info-value">{{ Number(cluster.rating_avg || 0).toFixed(1) }} / 5 ({{ cluster.rating_count }} lượt)</span>
          </div>
          <div class="info-item">
            <span class="info-label">Tọa độ</span>
            <span class="info-value">{{ cluster.latitude }}, {{ cluster.longitude }}</span>
          </div>
          <div class="info-item full-width" v-if="cluster.description">
            <span class="info-label">Mô tả</span>
            <span class="info-value">{{ cluster.description }}</span>
          </div>
          <div class="info-item full-width" v-if="cluster.amenities && cluster.amenities.length">
            <span class="info-label">Tiện ích</span>
            <div class="amenity-chips">
              <span v-for="(a, i) in cluster.amenities" :key="i" class="amenity-chip">{{ a }}</span>
            </div>
          </div>
          <div class="info-item full-width" v-if="cluster.status === 'locked'">
            <span class="info-label">Lý do khóa</span>
            <span class="info-value lock-reason">{{ cluster.status_reason }}</span>
          </div>
          <div class="info-item" v-if="cluster.locked_at">
            <span class="info-label">Khóa lúc</span>
            <span class="info-value">{{ formatDate(cluster.locked_at) }}</span>
          </div>
          <div class="info-item" v-if="cluster.locked_until">
            <span class="info-label">Khóa đến</span>
            <span class="info-value">{{ formatDate(cluster.locked_until) }}</span>
          </div>
          <div class="info-item" v-if="cluster.locked_by">
            <span class="info-label">Khóa bởi</span>
            <span class="info-value">{{ cluster.locked_by?.full_name || cluster.locked_by }}</span>
          </div>
        </div>

        <div v-if="cluster.map_url" class="map-link">
          <a :href="cluster.map_url" target="_blank" rel="noopener" class="btn btn-outline">
            Xem trên Google Maps
          </a>
        </div>
      </div>

      <!-- ┌ Tab: Sân con ──────────────────────────────────────────── -->
      <div v-if="activeTab === 'courts'" class="avcd-card card">
        <h3 class="section-title">Danh sách sân con ({{ (cluster.courts || []).length }})</h3>
        <div v-if="!cluster.courts || cluster.courts.length === 0" class="empty-section">
          Chưa có sân con nào.
        </div>
        <table v-else class="simple-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Tên sân</th>
              <th>Loại sân</th>
              <th class="text-center">Thứ tự</th>
              <th class="text-center">Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(court, idx) in cluster.courts" :key="court.id">
              <td class="muted">{{ idx + 1 }}</td>
              <td class="fw-bold">{{ court.name }}</td>
              <td>{{ court.court_type?.name || '—' }}</td>
              <td class="text-center">{{ court.sort_order ?? '—' }}</td>
              <td class="text-center">
                <span class="status-badge" :class="court.status === 'active' ? 'status-active' : 'status-locked'">
                  {{ court.status === 'active' ? 'Hoạt động' : court.status }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ┌ Tab: Booking ──────────────────────────────────────────── -->
      <div v-if="activeTab === 'bookings'" class="avcd-card card">
        <h3 class="section-title">Lịch sử đặt sân (20 gần nhất)</h3>
        <div v-if="!bookings || bookings.length === 0" class="empty-section">Chưa có lượt đặt sân.</div>
        <table v-else class="simple-table">
          <thead>
            <tr>
              <th>Mã booking</th>
              <th>Khách hàng</th>
              <th>Sân</th>
              <th>Ngày</th>
              <th>Giờ</th>
              <th class="text-right">Tổng tiền</th>
              <th class="text-center">Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in bookings" :key="b.id">
              <td class="mono">{{ b.booking_code }}</td>
              <td>
                <div class="fw-bold">{{ b.customer?.full_name || '—' }}</div>
                <div class="muted">{{ b.customer?.phone }}</div>
              </td>
              <td>{{ b.venue_court?.name || '—' }}</td>
              <td>{{ formatDate(b.booking_date, false) }}</td>
              <td class="mono">{{ b.start_time }} – {{ b.end_time }}</td>
              <td class="text-right fw-bold">{{ formatCurrency(b.total_price) }}</td>
              <td class="text-center">
                <span class="booking-status" :class="`bs-${b.status}`">{{ b.status }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ┌ Tab: Phí ──────────────────────────────────────────── -->
      <div v-if="activeTab === 'fees'" class="avcd-card card">
        <h3 class="section-title">Phí nền tảng</h3>
        <div v-if="!fees || fees.length === 0" class="empty-section">Chưa có bản ghi phí.</div>
        <table v-else class="simple-table">
          <thead>
            <tr>
              <th>Gói</th>
              <th class="text-center">Số sân</th>
              <th>Kỳ</th>
              <th>Hạn thanh toán</th>
              <th class="text-right">Số tiền</th>
              <th class="text-right">Đã trả</th>
              <th class="text-center">Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in fees" :key="f.id">
              <td>{{ f.tier?.name || '—' }}</td>
              <td class="text-center">{{ f.court_count }}</td>
              <td class="muted">{{ formatDate(f.period_start, false) }} – {{ formatDate(f.period_end, false) }}</td>
              <td :class="isOverdue(f) ? 'overdue' : ''">{{ formatDate(f.due_date, false) }}</td>
              <td class="text-right fw-bold">{{ formatCurrency(f.amount_due) }}</td>
              <td class="text-right">{{ formatCurrency(f.amount_paid) }}</td>
              <td class="text-center">
                <span class="status-badge" :class="`fee-${f.status}`">{{ f.status }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ┌ Tab: Lịch sử khóa ──────────────────────────────────────── -->
      <div v-if="activeTab === 'lock_history'" class="avcd-card card">
        <h3 class="section-title">Lịch sử khóa / mở khóa</h3>
        <div v-if="!lockHistory || lockHistory.length === 0" class="empty-section">Chưa có lịch sử khóa.</div>
        <div v-else class="audit-list">
          <div v-for="log in lockHistory" :key="log.id" class="audit-item">
            <div class="audit-action" :class="`action-${log.action.includes('unlock') ? 'unlock' : 'lock'}`">
              {{ log.action }}
            </div>
            <div class="audit-meta">
              <span>{{ log.actor?.full_name || 'Hệ thống' }}</span>
              <span class="muted">{{ formatDate(log.created_at) }}</span>
            </div>
            <div v-if="log.reason || log.new_values?.status_reason" class="audit-reason">
              Lý do: {{ log.reason || log.new_values?.status_reason }}
            </div>
          </div>
        </div>
      </div>

      <!-- ┌ Tab: Yêu cầu quy mô ──────────────────────────────────────── -->
      <div v-if="activeTab === 'approvals'" class="avcd-card card">
        <h3 class="section-title">Yêu cầu mở rộng / thu hẹp quy mô</h3>

        <div class="approval-tabs">
          <button
            class="tab-sm"
            :class="{ active: approvalFilter === '' }"
            @click="approvalFilter = ''"
          >Tất cả</button>
          <button
            class="tab-sm"
            :class="{ active: approvalFilter === 'pending' }"
            @click="approvalFilter = 'pending'"
          >Chờ duyệt</button>
          <button
            class="tab-sm"
            :class="{ active: approvalFilter === 'approved' }"
            @click="approvalFilter = 'approved'"
          >Đã duyệt</button>
          <button
            class="tab-sm"
            :class="{ active: approvalFilter === 'rejected' }"
            @click="approvalFilter = 'rejected'"
          >Từ chối</button>
        </div>

        <div v-if="filteredApprovals.length === 0" class="empty-section">Không có yêu cầu nào.</div>

        <div v-else class="approval-list">
          <div
            v-for="req in filteredApprovals"
            :key="req.id"
            class="approval-card"
            :class="`approval-${req.status}`"
          >
            <div class="approval-row">
              <div>
                <div class="approval-name fw-bold">{{ req.name }}</div>
                <div class="muted">Loại: {{ req.court_type?.name || '—' }}</div>
                <div class="muted">Yêu cầu bởi: {{ req.requested_by?.full_name || '—' }} · {{ formatDate(req.created_at) }}</div>
                <div v-if="req.reviewed_by" class="muted">
                  Xử lý bởi: {{ req.reviewed_by?.full_name }} · {{ formatDate(req.reviewed_at) }}
                </div>
                <div v-if="req.status_reason" class="reason-text">Lý do: {{ req.status_reason }}</div>
              </div>
              <div class="approval-right">
                <span class="status-badge" :class="`status-${req.status}`">
                  {{ approvalStatusLabel(req.status) }}
                </span>
                <div v-if="req.status === 'pending'" class="approval-btns">
                  <button
                    class="btn btn-success btn-sm"
                    :disabled="processingId === req.id"
                    @click="handleApprove(req)"
                  >{{ processingId === req.id ? '...' : 'Duyệt' }}</button>
                  <button
                    class="btn btn-danger btn-sm"
                    :disabled="processingId === req.id"
                    @click="openRejectModal(req)"
                  >Từ chối</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ── Modal: Khóa cụm sân ── -->
    <div v-if="showLockModal" class="modal-backdrop" @click.self="closeLockModal">
      <form class="modal-box card" @submit.prevent="handleLock">
        <div class="modal-header">
          <h3>Khóa cụm sân</h3>
          <button type="button" class="btn-close" @click="closeLockModal">×</button>
        </div>
        <div class="modal-body">
          <div v-if="lockError" class="alert-error">{{ lockError }}</div>
          <label class="form-label">
            Lý do khóa <span class="required">*</span>
            <textarea
              v-model="lockForm.status_reason"
              rows="4"
              required
              placeholder="Nhập lý do khóa cụm sân..."
              class="form-control"
            ></textarea>
          </label>
          <label class="form-label">
            Khóa đến (để trống = khóa vĩnh viễn)
            <input
              v-model="lockForm.locked_until"
              type="datetime-local"
              class="form-control"
            />
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="closeLockModal">Hủy</button>
          <button type="submit" class="btn btn-danger" :disabled="locking">
            {{ locking ? 'Đang khóa...' : 'Xác nhận khóa' }}
          </button>
        </div>
      </form>
    </div>

    <!-- ── Modal: Từ chối yêu cầu ── -->
    <div v-if="rejectTarget" class="modal-backdrop" @click.self="closeRejectModal">
      <form class="modal-box card" @submit.prevent="handleReject">
        <div class="modal-header">
          <h3>Từ chối yêu cầu</h3>
          <button type="button" class="btn-close" @click="closeRejectModal">×</button>
        </div>
        <div class="modal-body">
          <p class="muted">Yêu cầu: <strong>{{ rejectTarget.name }}</strong></p>
          <div v-if="rejectError" class="alert-error">{{ rejectError }}</div>
          <label class="form-label">
            Lý do từ chối <span class="required">*</span>
            <textarea
              v-model="rejectReason"
              rows="4"
              required
              placeholder="Nhập lý do từ chối..."
              class="form-control"
            ></textarea>
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="closeRejectModal">Hủy</button>
          <button type="submit" class="btn btn-danger" :disabled="rejecting">
            {{ rejecting ? 'Đang từ chối...' : 'Xác nhận từ chối' }}
          </button>
        </div>
      </form>
    </div>

    <!-- ── Global alert ── -->
    <transition name="fade">
      <div v-if="globalMsg" class="global-alert" :class="globalMsgType">
        {{ globalMsg }}
      </div>
    </transition>
  </div>
</template>

<script>
import { adminVenueClusterService } from '../../services/adminVenueClusterService.js';

export default {
  name: 'AdminVenueClusterDetail',
  data() {
    return {
      cluster: null,
      bookings: [],
      fees: [],
      lockHistory: [],
      approvalRequests: [],
      loading: true,
      error: '',

      activeTab: 'info',
      tabs: [
        { key: 'info', label: 'Thông tin' },
        { key: 'courts', label: 'Sân con' },
        { key: 'bookings', label: 'Booking' },
        { key: 'fees', label: 'Phí' },
        { key: 'lock_history', label: 'Lịch sử khóa' },
        { key: 'approvals', label: 'Yêu cầu quy mô' },
      ],

      approvalFilter: '',
      processingId: null,

      // Lock modal
      showLockModal: false,
      locking: false,
      lockError: '',
      lockForm: { status_reason: '', locked_until: '' },

      // Unlock
      unlocking: false,

      // Reject modal
      rejectTarget: null,
      rejectReason: '',
      rejectError: '',
      rejecting: false,

      // Global message
      globalMsg: '',
      globalMsgType: 'msg-success',
      globalTimer: null,
    };
  },
  computed: {
    filteredApprovals() {
      if (!this.approvalFilter) return this.approvalRequests;
      return this.approvalRequests.filter((r) => r.status === this.approvalFilter);
    },
  },
  mounted() {
    this.loadDetail();
  },
  methods: {
    async loadDetail() {
      this.loading = true;
      this.error = '';
      try {
        const res = await adminVenueClusterService.show(this.$route.params.id);
        const data = res.data;
        this.cluster = data.cluster;
        this.bookings = data.bookings || [];
        this.fees = data.fees || [];
        this.lockHistory = data.lock_history || [];
        this.approvalRequests = data.approval_requests || [];
      } catch (err) {
        this.error = err.message || 'Không tải được dữ liệu.';
      } finally {
        this.loading = false;
      }
    },

    // ── Lock / Unlock ──
    openLockModal() {
      this.lockForm = { status_reason: '', locked_until: '' };
      this.lockError = '';
      this.showLockModal = true;
    },
    closeLockModal() {
      this.showLockModal = false;
    },
    async handleLock() {
      this.locking = true;
      this.lockError = '';
      try {
        const payload = { status_reason: this.lockForm.status_reason };
        if (this.lockForm.locked_until) payload.locked_until = this.lockForm.locked_until;
        const res = await adminVenueClusterService.lock(this.cluster.id, payload);
        this.cluster = res.cluster;
        this.closeLockModal();
        this.showMsg('Khóa cụm sân thành công.', 'msg-success');
        await this.loadDetail();
      } catch (err) {
        this.lockError = err.message || 'Khóa không thành công.';
      } finally {
        this.locking = false;
      }
    },
    async handleUnlock() {
      if (!confirm('Mở khóa cụm sân này?')) return;
      this.unlocking = true;
      try {
        const res = await adminVenueClusterService.unlock(this.cluster.id);
        this.cluster = res.cluster;
        this.showMsg('Mở khóa thành công.', 'msg-success');
        await this.loadDetail();
      } catch (err) {
        this.showMsg(err.message || 'Mở khóa không thành công.', 'msg-error');
      } finally {
        this.unlocking = false;
      }
    },

    // ── Approve / Reject ──
    async handleApprove(req) {
      if (!confirm(`Duyệt yêu cầu "${req.name}"?`)) return;
      this.processingId = req.id;
      try {
        const res = await adminVenueClusterService.approveRequest(this.cluster.id, req.id);
        const idx = this.approvalRequests.findIndex((r) => r.id === req.id);
        if (idx !== -1) this.approvalRequests.splice(idx, 1, res.request);
        this.showMsg('Duyệt yêu cầu thành công.', 'msg-success');
        // Reload để cập nhật danh sách sân con
        await this.loadDetail();
      } catch (err) {
        this.showMsg(err.message || 'Duyệt không thành công.', 'msg-error');
      } finally {
        this.processingId = null;
      }
    },
    openRejectModal(req) {
      this.rejectTarget = req;
      this.rejectReason = '';
      this.rejectError = '';
    },
    closeRejectModal() {
      this.rejectTarget = null;
    },
    async handleReject() {
      this.rejecting = true;
      this.rejectError = '';
      try {
        const res = await adminVenueClusterService.rejectRequest(
          this.cluster.id,
          this.rejectTarget.id,
          { status_reason: this.rejectReason },
        );
        const idx = this.approvalRequests.findIndex((r) => r.id === this.rejectTarget.id);
        if (idx !== -1) this.approvalRequests.splice(idx, 1, res.request);
        this.closeRejectModal();
        this.showMsg('Đã từ chối yêu cầu.', 'msg-success');
      } catch (err) {
        this.rejectError = err.message || 'Từ chối không thành công.';
      } finally {
        this.rejecting = false;
      }
    },

    // ── Helpers ──
    statusLabel(status) {
      return { pending: 'Chờ duyệt', active: 'Hoạt động', locked: 'Đã khóa' }[status] || status;
    },
    approvalStatusLabel(status) {
      return { pending: 'Chờ duyệt', approved: 'Đã duyệt', rejected: 'Từ chối', cancelled: 'Hủy' }[status] || status;
    },
    formatDate(val, showTime = true) {
      if (!val) return '—';
      const d = new Date(val);
      if (showTime) return d.toLocaleString('vi-VN');
      return d.toLocaleDateString('vi-VN');
    },
    formatCurrency(val) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
    },
    isOverdue(fee) {
      if (!fee.due_date || fee.status === 'paid') return false;
      return new Date(fee.due_date) < new Date();
    },
    showMsg(msg, type = 'msg-success') {
      clearTimeout(this.globalTimer);
      this.globalMsg = msg;
      this.globalMsgType = type;
      this.globalTimer = setTimeout(() => { this.globalMsg = ''; }, 3500);
    },
  },
};
</script>

<style scoped>
.avcd-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid var(--sg-border);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  padding: 20px 24px;
}

/* Header */
.avcd-header { display: flex; flex-direction: column; gap: 12px; }
.btn-back {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  color: rgba(15, 23, 42, 0.5);
  padding: 0;
  width: fit-content;
  transition: color 0.15s;
}
.btn-back:hover { color: var(--sg-text); }
.avcd-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  flex-wrap: wrap;
}
.avcd-title { font-size: 22px; font-weight: 800; margin: 0; }
.avcd-sub { margin: 4px 0 0; font-size: 14px; color: rgba(15, 23, 42, 0.5); }
.avcd-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Tabs */
.avcd-tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  padding: 14px 20px;
}
.tab-btn {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid var(--sg-border);
  background: var(--sg-surface, #f8fafc);
  color: rgba(15, 23, 42, 0.6);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.18s;
}
.tab-btn.active {
  background: #0f172a;
  border-color: #0f172a;
  color: #fff;
}
.tab-btn:not(.active):hover { background: #f1f5f9; }

/* Card */
.avcd-card {}
.section-title {
  font-size: 16px;
  font-weight: 800;
  margin: 0 0 18px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--sg-border);
}

/* Info grid */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.info-item { display: flex; flex-direction: column; gap: 4px; }
.full-width { grid-column: 1 / -1; }
.info-label { font-size: 12px; font-weight: 700; color: rgba(15, 23, 42, 0.4); text-transform: uppercase; letter-spacing: 0.5px; }
.info-value { font-size: 14px; color: var(--sg-text); }
.muted { color: rgba(15, 23, 42, 0.45); font-size: 13px; }
.lock-reason { color: #dc2626; font-weight: 600; }
.amenity-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.amenity-chip {
  padding: 4px 10px;
  background: #f1f5f9;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}
.map-link { margin-top: 18px; }

/* Status badges */
.status-badge {
  display: inline-flex;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}
.status-pending { background: #fef3c7; color: #92400e; }
.status-active  { background: #dcfce7; color: #166534; }
.status-locked  { background: #fee2e2; color: #991b1b; }
.status-approved { background: #dcfce7; color: #166534; }
.status-rejected { background: #fee2e2; color: #991b1b; }
.status-cancelled { background: #f3f4f6; color: #6b7280; }
.fee-paid { background: #dcfce7; color: #166534; }
.fee-unpaid, .fee-overdue { background: #fee2e2; color: #991b1b; }
.fee-partial { background: #fef3c7; color: #92400e; }

/* Buttons */
.btn {
  padding: 9px 18px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.18s;
}
.btn-sm { padding: 6px 12px; font-size: 13px; }
.btn-outline { background: transparent; border-color: var(--sg-border); color: var(--sg-text); }
.btn-outline:hover { background: #f1f5f9; }
.btn-danger { background: #dc2626; color: #fff; }
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-danger:disabled { opacity: 0.55; cursor: not-allowed; }
.btn-success { background: #16a34a; color: #fff; }
.btn-success:hover:not(:disabled) { background: #15803d; }
.btn-success:disabled { opacity: 0.55; cursor: not-allowed; }

/* Tables */
.simple-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
.simple-table th,
.simple-table td {
  padding: 12px 16px;
  border-bottom: 1px solid var(--sg-border);
  text-align: left;
}
.simple-table th {
  background: var(--sg-surface, #f8fafc);
  font-weight: 700;
  font-size: 13px;
}
.fw-bold { font-weight: 700; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.mono { font-family: monospace; }
.overdue { color: #dc2626; font-weight: 700; }

/* Booking status */
.booking-status {
  display: inline-flex;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  background: #f1f5f9;
  color: #334155;
}
.bs-confirmed { background: #dcfce7; color: #166534; }
.bs-pending { background: #fef3c7; color: #92400e; }
.bs-cancelled { background: #fee2e2; color: #991b1b; }
.bs-completed { background: #e0f2fe; color: #0369a1; }

/* State box */
.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  gap: 14px;
  text-align: center;
  color: rgba(15, 23, 42, 0.5);
}
.error-box { color: #dc2626; background: #fef2f2; border-color: #fecaca; }
.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid rgba(0, 0, 0, 0.08);
  border-top-color: #0f172a;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.empty-section { padding: 40px 0; text-align: center; color: rgba(15, 23, 42, 0.4); font-size: 14px; }

/* Audit */
.audit-list { display: flex; flex-direction: column; gap: 12px; }
.audit-item {
  padding: 14px 16px;
  border-radius: 10px;
  border: 1px solid var(--sg-border);
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.audit-action { font-weight: 700; font-size: 14px; }
.action-lock { color: #dc2626; }
.action-unlock { color: #16a34a; }
.audit-meta { display: flex; gap: 16px; font-size: 13px; }
.audit-reason { font-size: 13px; color: rgba(15, 23, 42, 0.6); font-style: italic; }

/* Approvals */
.approval-tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
.tab-sm {
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid var(--sg-border);
  background: #f8fafc;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.tab-sm.active { background: #0f172a; border-color: #0f172a; color: #fff; }
.approval-list { display: flex; flex-direction: column; gap: 12px; }
.approval-card {
  padding: 16px;
  border-radius: 10px;
  border: 1px solid var(--sg-border);
  background: #f8fafc;
  transition: box-shadow 0.18s;
}
.approval-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
.approval-pending { border-left: 3px solid #f59e0b; }
.approval-approved { border-left: 3px solid #22c55e; }
.approval-rejected { border-left: 3px solid #ef4444; }
.approval-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
.approval-right { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
.approval-btns { display: flex; gap: 8px; }
.approval-name { margin-bottom: 4px; }
.reason-text { font-size: 13px; color: #dc2626; font-style: italic; margin-top: 4px; }

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(4px);
  display: grid;
  place-items: center;
  z-index: 600;
  padding: 20px;
}
.modal-box {
  width: min(520px, calc(100vw - 32px));
  padding: 0;
  overflow: hidden;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.2);
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--sg-border);
}
.modal-header h3 { margin: 0; font-size: 18px; font-weight: 800; }
.btn-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: rgba(15, 23, 42, 0.4);
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: grid;
  place-items: center;
}
.btn-close:hover { background: #f1f5f9; }
.modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--sg-border);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background: var(--sg-surface, #f8fafc);
}
.form-label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  font-weight: 700;
  color: var(--sg-text);
}
.form-control {
  padding: 10px 14px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  outline: none;
  color: var(--sg-text);
  background: #fff;
  transition: border-color 0.18s;
}
.form-control:focus { border-color: #0f172a; }
.required { color: #ef4444; }
.alert-error {
  padding: 10px 14px;
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
}

/* Global alert */
.global-alert {
  position: fixed;
  bottom: 28px;
  right: 28px;
  padding: 14px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 700;
  z-index: 999;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
}
.msg-success { background: #166534; color: #fff; }
.msg-error   { background: #dc2626; color: #fff; }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@media (max-width: 640px) {
  .info-grid { grid-template-columns: 1fr; }
}
</style>

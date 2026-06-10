<template>
  <section class="user-detail">
    <header class="page-head">
      <div>
        <RouterLink class="back-link" to="/admin/users">
          <AppIcon name="arrowLeft" size="16" />
          Quay lại danh sách
        </RouterLink>
        <h2>{{ profile.full_name || 'Chi tiết tài khoản' }}</h2>
        <p>{{ profile.username || '-' }} · {{ profile.email || profile.phone || 'Chưa có thông tin liên hệ' }}</p>
      </div>
      <div v-if="detail" class="head-actions">
        <button v-if="profile.status === 'locked'" class="btn primary" type="button" @click="openUnlockModal">
          <AppIcon name="unlock" size="16" />
          Mở khóa
        </button>
        <button v-else class="btn danger" type="button" @click="openLockModal">
          <AppIcon name="lock" size="16" />
          Khóa tài khoản
        </button>
      </div>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="loading" class="state-card">
      <span class="spinner"></span>
      Đang tải chi tiết tài khoản...
    </div>

    <template v-else-if="detail">
      <section class="summary-card">
        <div class="avatar">{{ initials(profile.full_name || profile.username) }}</div>
        <div class="summary-main">
          <strong>{{ profile.full_name || 'Chưa cập nhật tên' }}</strong>
          <span>@{{ profile.username || '-' }} · {{ profile.email || profile.phone || 'Chưa có liên hệ' }}</span>
        </div>
        <span class="badge" :class="statusTone(profile.status)">{{ profile.status_label || statusLabel(profile.status) }}</span>
        <span class="badge neutral">{{ profile.primary_role_label || 'Chưa gán vai trò' }}</span>
        <span class="badge" :class="warningTone(detail.warning_summary?.level)">{{ detail.warning_summary?.label || 'Bình thường' }}</span>
      </section>

      <section class="quick-stats">
        <Metric label="Số dư ví" :value="wallet.balance_formatted || money(wallet.balance)" />
        <Metric label="Tổng booking" :value="bookingSummary.total || 0" />
        <Metric label="Số report" :value="detail.reports_summary?.total || 0" />
        <Metric label="Mức cảnh báo" :value="detail.warning_summary?.label || 'Bình thường'" />
        <Metric label="Hoạt động gần nhất" :value="dateTime(profile.updated_at)" />
      </section>

      <nav class="tabs" aria-label="Tab chi tiết tài khoản">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          type="button"
          :class="{ active: activeTab === tab.value }"
          @click="activeTab = tab.value"
        >
          {{ tab.label }}
        </button>
      </nav>

      <section v-if="activeTab === 'overview'" class="panel">
        <h3>Tổng quan</h3>
        <div class="info-grid">
          <InfoItem label="Họ tên" :value="profile.full_name" />
          <InfoItem label="Username" :value="profile.username" />
          <InfoItem label="Email" :value="profile.email" />
          <InfoItem label="Số điện thoại" :value="profile.phone" />
          <InfoItem label="Trạng thái" :value="profile.status_label || statusLabel(profile.status)" />
          <InfoItem label="Vai trò hiện tại" :value="joinText(profile.role_labels)" />
          <InfoItem label="Ngày tạo" :value="dateTime(profile.created_at)" />
          <InfoItem label="Cập nhật gần nhất" :value="dateTime(profile.updated_at)" />
          <InfoItem label="Lý do khóa" :value="profile.status_reason" />
          <InfoItem label="Người khóa" :value="profile.locked_by_name" />
          <InfoItem label="Khóa từ" :value="dateTime(profile.locked_at)" />
          <InfoItem label="Khóa đến" :value="dateTime(profile.locked_until)" />
        </div>
      </section>

      <section v-if="activeTab === 'warnings'" class="panel">
        <h3>Cảnh báo & báo cáo</h3>
        <p class="notice">{{ detail.warning_summary?.message || 'Tài khoản chưa có dấu hiệu rủi ro đáng chú ý.' }}</p>
        <div class="risk-meter">
          <div>
            <strong>{{ detail.warning_summary?.label || 'Bình thường' }}</strong>
            <span>{{ riskProgress }}% ngưỡng theo dõi</span>
          </div>
          <div class="progress"><i :style="{ width: `${riskProgress}%` }"></i></div>
        </div>
        <div class="metric-row">
          <Metric label="Report 7 ngày" :value="detail.warning_summary?.reports_7_days || 0" />
          <Metric label="Report 14 ngày" :value="detail.warning_summary?.reports_14_days || 0" />
          <Metric label="Report 30 ngày" :value="detail.warning_summary?.reports_30_days || 0" />
          <Metric label="Khiếu nại mở" :value="detail.warning_summary?.complaints_open || 0" />
        </div>

        <div class="two-columns">
          <div>
            <h4>Report gần đây</h4>
            <div class="list-box">
              <article v-for="report in recentReports" :key="report.id">
                <strong>{{ report.reason_label || report.reason || 'Báo cáo' }}</strong>
                <span>{{ report.description || 'Không có mô tả.' }}</span>
                <small>{{ report.status_label || statusLabel(report.status) }} · {{ dateTime(report.created_at) }}</small>
              </article>
              <p v-if="recentReports.length === 0" class="muted">Chưa có report gần đây.</p>
            </div>
          </div>

          <div>
            <h4>Khiếu nại liên quan</h4>
            <div class="list-box">
              <article v-for="complaint in recentComplaints" :key="complaint.id">
                <strong>{{ complaint.status_label || statusLabel(complaint.status) }}</strong>
                <span>{{ complaint.content || complaint.description || 'Không có nội dung.' }}</span>
                <small>{{ dateTime(complaint.created_at) }}</small>
              </article>
              <p v-if="recentComplaints.length === 0" class="muted">Chưa có khiếu nại liên quan.</p>
            </div>
          </div>
        </div>
      </section>

      <section v-if="activeTab === 'wallet'" class="panel">
        <h3>Ví người dùng</h3>
        <p class="muted">Màn này chỉ phục vụ kiểm tra. Admin không chỉnh số dư trực tiếp tại đây.</p>
        <div class="metric-row">
          <Metric label="Số dư" :value="money(wallet.balance)" />
          <Metric label="Đang giữ/khóa" :value="money(wallet.locked_balance)" />
          <Metric label="Trạng thái ví" :value="wallet.status_label || 'Chưa có ví'" />
        </div>
        <h4>Lịch sử giao dịch ví</h4>
        <div class="list-box">
          <article v-for="ledger in walletLedgers" :key="ledger.id">
            <strong>{{ ledger.type_label || ledger.type || 'Giao dịch ví' }} · {{ money(ledger.amount) }}</strong>
            <span>Số dư sau giao dịch: {{ money(ledger.balance_after) }} · {{ ledger.status_label || statusLabel(ledger.status) }}</span>
            <small>{{ dateTime(ledger.created_at) }}</small>
          </article>
          <p v-if="walletLedgers.length === 0" class="muted">Chưa có biến động ví.</p>
        </div>
      </section>

      <section v-if="activeTab === 'bookings'" class="panel">
        <h3>Lịch sử booking</h3>
        <div class="metric-row">
          <Metric label="Tổng booking" :value="bookingSummary.total || 0" />
          <Metric label="Hoàn tất" :value="bookingSummary.completed || 0" />
          <Metric label="Đã hủy" :value="bookingSummary.cancelled || 0" />
          <Metric label="Tổng tiền đã thanh toán" :value="money(bookingSummary.paid_total)" />
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Mã booking</th>
                <th>Sân / cụm sân</th>
                <th>Ngày giờ</th>
                <th>Tổng tiền</th>
                <th>Booking</th>
                <th>Thanh toán</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="recentBookings.length === 0">
                <td colspan="6" class="empty-cell">Chưa có booking.</td>
              </tr>
              <tr v-for="booking in recentBookings" :key="booking.id">
                <td>{{ booking.booking_code || '-' }}</td>
                <td>
                  <strong>{{ booking.venue_cluster_name || '-' }}</strong>
                  <span>{{ booking.venue_court_name || '' }}</span>
                </td>
                <td>{{ date(booking.booking_date) }} {{ booking.start_time || '' }} - {{ booking.end_time || '' }}</td>
                <td>{{ money(booking.total_price) }}</td>
                <td>{{ booking.status_label || statusLabel(booking.status) }}</td>
                <td>{{ booking.payment_status_label || statusLabel(booking.payment_status) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="activeTab === 'roles'" class="panel">
        <h3>Vai trò & quyền</h3>
        <p class="notice">Nhân viên sân và chủ sân là vai trò nghiệp vụ, không trộn với nhóm quyền admin hệ thống.</p>
        <div class="list-box">
          <article v-for="role in detail.roles || []" :key="role.id || role.name">
            <strong>{{ role.label || role.display_name || role.name }}</strong>
            <span>Phạm vi: {{ scopeText(role) }}</span>
          </article>
          <p v-if="!(detail.roles || []).length" class="muted">Tài khoản chưa có vai trò.</p>
        </div>

        <h4>Quyền bị thu hồi</h4>
        <div class="list-box">
          <article v-for="revoke in detail.permission_revokes || []" :key="revoke.id">
            <strong>{{ revoke.permission || 'Quyền đã thu hồi' }}</strong>
            <span>{{ revoke.reason || 'Không có lý do.' }}</span>
            <small>{{ revoke.revoked_by_name || 'Hệ thống' }} · {{ dateTime(revoke.created_at) }}</small>
          </article>
          <p v-if="!(detail.permission_revokes || []).length" class="muted">Chưa có quyền bị thu hồi.</p>
        </div>
      </section>

      <section v-if="activeTab === 'audit'" class="panel">
        <h3>Lịch sử thao tác</h3>
        <div class="list-box">
          <article v-for="log in detail.audit_logs || []" :key="log.id">
            <strong>{{ log.action_label || log.human_message || 'Đã cập nhật tài khoản' }}</strong>
            <span>{{ log.actor_name || 'Hệ thống' }} · {{ dateTime(log.created_at) }}</span>
            <span v-if="log.reason">Lý do: {{ log.reason }}</span>

            <div v-if="hasSummary(log)" class="change-grid">
              <div>
                <b>Trước</b>
                <p v-for="item in log.old_values_summary || []" :key="`old-${log.id}-${item.field}`">
                  {{ item.field }}: {{ item.value }}
                </p>
              </div>
              <div>
                <b>Sau</b>
                <p v-for="item in log.new_values_summary || []" :key="`new-${log.id}-${item.field}`">
                  {{ item.field }}: {{ item.value }}
                </p>
              </div>
            </div>

            <details>
              <summary>Xem dữ liệu kỹ thuật</summary>
              <pre>{{ formatJson({ old: log.technical_old_values || log.old_values, new: log.technical_new_values || log.new_values }) }}</pre>
            </details>
          </article>
          <p v-if="!(detail.audit_logs || []).length" class="muted">Chưa có audit log.</p>
        </div>
      </section>
    </template>

    <div v-if="actionTarget" class="modal-backdrop" @click.self="closeActionModal">
      <form class="modal" @submit.prevent="submitAccountAction">
        <header class="modal-head">
          <div>
            <p class="eyebrow">{{ actionType === 'lock' ? 'Khóa tài khoản' : 'Mở khóa tài khoản' }}</p>
            <h3>{{ profile.full_name || profile.username }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeActionModal">
            <AppIcon name="x" size="16" />
          </button>
        </header>

        <template v-if="actionType === 'lock'">
          <div class="segmented" aria-label="Chọn loại khóa">
            <button
              v-for="type in lockTypes"
              :key="type.value"
              type="button"
              :class="{ active: actionForm.lock_type === type.value }"
              @click="actionForm.lock_type = type.value"
            >
              {{ type.label }}
            </button>
          </div>
          <label v-if="actionForm.lock_type === 'temporary'">
            <span>Khóa đến</span>
            <input v-model="actionForm.locked_until" type="datetime-local" required />
          </label>
        </template>

        <label>
          <span>{{ actionType === 'lock' ? 'Lý do khóa' : 'Lý do mở khóa' }}</span>
          <textarea v-model.trim="actionForm.reason" rows="4" required />
        </label>

        <footer>
          <button type="button" class="btn secondary" @click="closeActionModal">Hủy</button>
          <button type="submit" class="btn" :class="{ danger: actionType === 'lock', primary: actionType !== 'lock' }" :disabled="saving">
            {{ saving ? 'Đang xử lý...' : 'Xác nhận' }}
          </button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
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
  components: { AppIcon, InfoItem, Metric },
  data() {
    return {
      detail: null,
      activeTab: 'overview',
      loading: false,
      saving: false,
      error: '',
      success: '',
      actionTarget: null,
      actionType: 'lock',
      actionForm: { lock_type: 'temporary', locked_until: '', reason: '' },
      tabs: [
        { value: 'overview', label: 'Tổng quan' },
        { value: 'warnings', label: 'Cảnh báo & báo cáo' },
        { value: 'wallet', label: 'Ví người dùng' },
        { value: 'bookings', label: 'Lịch sử booking' },
        { value: 'roles', label: 'Vai trò & quyền' },
        { value: 'audit', label: 'Lịch sử thao tác' },
      ],
      lockTypes: [
        { value: 'temporary', label: 'Tạm thời' },
        { value: 'permanent', label: 'Vĩnh viễn' },
        { value: 'auto', label: 'Tự động' },
      ],
    };
  },
  computed: {
    profile() {
      return this.detail?.profile || {};
    },
    wallet() {
      return this.detail?.wallet_summary || {};
    },
    walletLedgers() {
      return this.wallet.ledgers || [];
    },
    bookingSummary() {
      return this.detail?.booking_summary || {};
    },
    recentBookings() {
      return this.detail?.recent_bookings || [];
    },
    recentReports() {
      return this.detail?.reports_summary?.recent || [];
    },
    recentComplaints() {
      return this.detail?.complaints_summary?.recent || [];
    },
    riskProgress() {
      const reports = Number(this.detail?.warning_summary?.reports_14_days || 0);
      const complaints = Number(this.detail?.warning_summary?.complaints_open || 0);
      return Math.min(100, Math.round(((reports + complaints) / 5) * 100));
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
        const response = await adminUserService.show(this.$route.params.id);
        this.detail = response.data;
      } catch (error) {
        this.error = error.message || 'Không tải được chi tiết tài khoản.';
      } finally {
        this.loading = false;
      }
    },
    openLockModal() {
      this.actionTarget = this.profile;
      this.actionType = 'lock';
      this.actionForm = {
        lock_type: 'temporary',
        locked_until: this.inputDate(new Date(Date.now() + 24 * 60 * 60 * 1000)),
        reason: '',
      };
    },
    openUnlockModal() {
      this.actionTarget = this.profile;
      this.actionType = 'unlock';
      this.actionForm = { lock_type: 'temporary', locked_until: '', reason: '' };
    },
    closeActionModal() {
      this.actionTarget = null;
    },
    async submitAccountAction() {
      this.saving = true;
      this.error = '';
      try {
        const response = this.actionType === 'lock'
          ? await adminUserService.lock(this.profile.id, {
              lock_type: this.actionForm.lock_type,
              status_reason: this.actionForm.reason,
              locked_until: this.actionForm.lock_type === 'temporary' ? this.actionForm.locked_until : null,
            })
          : await adminUserService.unlock(this.profile.id, { reason: this.actionForm.reason });

        this.success = response.message || 'Đã cập nhật trạng thái tài khoản.';
        this.closeActionModal();
        await this.loadDetail();
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật trạng thái tài khoản.';
      } finally {
        this.saving = false;
      }
    },
    hasSummary(log) {
      return (log.old_values_summary || []).length || (log.new_values_summary || []).length;
    },
    initials(name) {
      return String(name || 'SG').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
    },
    joinText(values) {
      return Array.isArray(values) && values.length ? values.join(', ') : '-';
    },
    scopeText(role) {
      if (!role.scope_type || role.scope_type === 'global') return 'Toàn hệ thống';
      if (role.scope_type === 'venue') return `Cụm sân ${role.scope_id || ''}`;
      return `${role.scope_type}: ${role.scope_id || '-'}`;
    },
    statusLabel(status) {
      return {
        active: 'Đang hoạt động',
        locked: 'Đã khóa',
        pending_verify: 'Chờ xác thực',
        deactivated: 'Đã vô hiệu hóa',
        pending: 'Chờ xử lý',
        paid: 'Đã thanh toán',
        failed: 'Thất bại',
        completed: 'Hoàn tất',
        cancelled: 'Đã hủy',
      }[status] || 'Không xác định';
    },
    statusTone(status) {
      return {
        active: 'success',
        locked: 'danger',
        pending_verify: 'warning',
        deactivated: 'muted',
      }[status] || 'muted';
    },
    warningTone(level) {
      return {
        normal: 'success',
        watch: 'warning',
        near_lock: 'warning',
        lock_suggested: 'danger',
      }[level] || 'success';
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(value || 0));
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    dateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
    inputDate(value) {
      const date = new Date(value);
      return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    },
    formatJson(value) {
      return JSON.stringify(value || {}, null, 2);
    },
  },
};
</script>

<style scoped>
.user-detail { display: grid; gap: 16px; }
.page-head, .summary-card, .head-actions { display: flex; justify-content: space-between; gap: 14px; align-items: center; }
.page-head { align-items: flex-start; }
.page-head h2 { margin: 6px 0; }
.page-head p, .muted, small { margin: 0; color: #64748b; }
.back-link { display: inline-flex; align-items: center; gap: 6px; color: #15803d; font-weight: 900; text-decoration: none; }
.summary-card, .panel, .state-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; }
.summary-card { justify-content: flex-start; border-left: 4px solid #16a34a; }
.summary-main { display: grid; gap: 4px; margin-right: auto; }
.summary-main span { color: #64748b; }
.avatar { width: 60px; height: 60px; border-radius: 14px; display: grid; place-items: center; background: #0f172a; color: #bbf7d0; font-weight: 900; font-size: 20px; }
.quick-stats { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
.tabs, .metric-row, .segmented { display: flex; gap: 8px; flex-wrap: wrap; }
.tabs button, .segmented button { border: 1px solid #dbe3ef; background: #fff; border-radius: 8px; padding: 10px 14px; font-weight: 800; cursor: pointer; color: #334155; }
.tabs button.active, .segmented button.active { background: #dcfce7; border-color: #22c55e; color: #166534; }
.panel { display: grid; gap: 14px; }
.panel h3, .panel h4 { margin: 0; }
.info-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
:deep(.info-item), .metric { display: grid; gap: 6px; padding: 12px; background: #f8fafc; border-radius: 10px; }
:deep(.info-item span), .metric span { color: #64748b; font-size: 13px; }
.metric strong { font-size: 20px; }
.notice { margin: 0; padding: 12px; border-radius: 10px; background: #f0fdf4; color: #166534; font-weight: 800; }
.risk-meter { display: grid; gap: 8px; padding: 14px; border-radius: 10px; background: #fffbeb; border: 1px solid #fde68a; }
.risk-meter div:first-child { display: flex; justify-content: space-between; gap: 12px; align-items: center; color: #92400e; }
.progress { height: 10px; border-radius: 999px; overflow: hidden; background: #fde68a; }
.progress i { display: block; height: 100%; border-radius: inherit; background: #f97316; transition: width .2s ease; }
.two-columns { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.list-box { display: grid; gap: 10px; }
.list-box article { display: grid; gap: 6px; padding: 12px; background: #f8fafc; border-radius: 10px; }
.table-wrap { overflow: auto; border: 1px solid #e2e8f0; border-radius: 10px; }
table { width: 100%; border-collapse: collapse; min-width: 820px; }
th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
th { background: #f8fafc; color: #334155; font-size: 12px; text-transform: uppercase; }
td span { display: block; margin-top: 4px; color: #64748b; }
.empty-cell { text-align: center; color: #64748b; font-weight: 800; }
.change-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.change-grid p { margin: 4px 0 0; color: #475569; }
details { border-top: 1px solid #e2e8f0; padding-top: 8px; }
summary { cursor: pointer; font-weight: 800; color: #475569; }
pre { max-height: 260px; overflow: auto; background: #0f172a; color: #e2e8f0; border-radius: 8px; padding: 12px; }
.btn, .icon-btn { border: 0; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 800; cursor: pointer; }
.btn { padding: 10px 14px; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #f1f5f9; color: #334155; }
.btn.danger { background: #dc2626; color: #fff; }
.icon-btn { width: 36px; height: 36px; border: 1px solid #dbe3ef; background: #fff; color: #334155; }
.badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 900; white-space: nowrap; }
.badge.success { background: #dcfce7; color: #166534; }
.badge.danger { background: #fee2e2; color: #991b1b; }
.badge.warning { background: #fef3c7; color: #92400e; }
.badge.muted, .badge.neutral { background: #f1f5f9; color: #475569; }
.alert { border-radius: 10px; padding: 12px 14px; font-weight: 800; }
.alert.error { background: #fef2f2; color: #991b1b; }
.alert.success { background: #f0fdf4; color: #166534; }
.spinner { width: 18px; height: 18px; border: 2px solid #bbf7d0; border-top-color: #16a34a; border-radius: 50%; display: inline-block; margin-right: 8px; animation: spin .8s linear infinite; vertical-align: middle; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .45); display: grid; place-items: center; z-index: 50; padding: 20px; }
.modal { width: min(560px, 100%); background: #fff; border-radius: 14px; padding: 18px; display: grid; gap: 14px; box-shadow: 0 24px 80px rgba(15, 23, 42, .22); }
.modal-head, .modal footer { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
.modal h3 { margin: 0; }
.modal label { display: grid; gap: 7px; color: #334155; font-weight: 800; }
.modal input, .modal textarea { width: 100%; border: 1px solid #dbe3ef; border-radius: 8px; padding: 10px 12px; font: inherit; background: #fff; color: #0f172a; }
.eyebrow { margin: 0 0 6px; color: #15803d; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 900px) {
  .page-head, .summary-card { display: grid; }
  .info-grid, .two-columns, .change-grid, .quick-stats { grid-template-columns: 1fr; }
}
</style>

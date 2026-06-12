<template>
  <section class="user-detail">
    <header class="page-head">
      <div>
        <RouterLink class="back-link" to="/admin/users">← Quay lại danh sách</RouterLink>
        <h2>{{ profile.full_name || 'Chi tiết tài khoản' }}</h2>
        <p>{{ profile.username || '-' }} · {{ profile.email || profile.phone || 'Chưa có thông tin liên hệ' }}</p>
      </div>
      <div class="head-actions" v-if="detail">
        <button v-if="profile.status === 'locked'" class="btn" type="button" @click="openUnlockModal">Mở khóa</button>
        <button v-else class="btn danger" type="button" @click="openLockModal">Khóa tài khoản</button>
      </div>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="loading" class="state-card">Đang tải chi tiết tài khoản...</div>

    <template v-else-if="detail">
      <section class="summary-card">
        <div class="avatar">{{ initials(profile.full_name || profile.username) }}</div>
        <div>
          <strong>{{ profile.full_name || '-' }}</strong>
          <span>{{ profile.primary_role_label || 'Chưa gán vai trò' }}</span>
        </div>
        <span class="status" :class="profile.status">{{ profile.status_label }}</span>
        <span class="warning" :class="detail.warning_summary?.level">{{ detail.warning_summary?.label }}</span>
      </section>

      <nav class="tabs" aria-label="Tab chi tiết tài khoản">
        <button v-for="tab in tabs" :key="tab.value" type="button" :class="{ active: activeTab === tab.value }" @click="activeTab = tab.value">
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

      <section v-if="activeTab === 'warnings'" class="panel">
        <h3>Cảnh báo & báo cáo</h3>
        <p class="notice">{{ detail.warning_summary?.message }}</p>
        <div class="metric-row">
          <Metric label="Report 7 ngày" :value="detail.warning_summary?.reports_7_days || 0" />
          <Metric label="Report 14 ngày" :value="detail.warning_summary?.reports_14_days || 0" />
          <Metric label="Report 30 ngày" :value="detail.warning_summary?.reports_30_days || 0" />
          <Metric label="Khiếu nại mở" :value="detail.warning_summary?.complaints_open || 0" />
        </div>
        <h4>Report gần đây</h4>
        <div class="list-box">
          <article v-for="report in detail.reports_summary.recent" :key="report.id">
            <strong>{{ report.reason }} · {{ report.status_label }}</strong>
            <span>{{ report.description || 'Không có mô tả' }}</span>
            <small>{{ dateTime(report.created_at) }}</small>
          </article>
          <p v-if="!detail.reports_summary.recent.length" class="muted">Chưa có report gần đây.</p>
        </div>
        <h4>Khiếu nại liên quan</h4>
        <div class="list-box">
          <article v-for="complaint in detail.complaints_summary.recent" :key="complaint.id">
            <strong>{{ complaint.status_label }}</strong>
            <span>{{ complaint.content || 'Không có nội dung' }}</span>
            <small>{{ dateTime(complaint.created_at) }}</small>
          </article>
          <p v-if="!detail.complaints_summary.recent.length" class="muted">Chưa có khiếu nại liên quan.</p>
        </div>
      </section>

      <section v-if="activeTab === 'wallet'" class="panel">
        <h3>Ví người dùng</h3>
        <div class="metric-row">
          <Metric label="Số dư" :value="money(detail.wallet_summary.balance)" />
          <Metric label="Đang giữ" :value="money(detail.wallet_summary.locked_balance)" />
          <Metric label="Trạng thái ví" :value="detail.wallet_summary.status_label" />
        </div>
        <h4>Lịch sử biến động ví</h4>
        <div class="list-box">
          <article v-for="ledger in detail.wallet_summary.ledgers" :key="ledger.id">
            <strong>{{ ledger.type_label }} · {{ money(ledger.amount) }}</strong>
            <span>Số dư sau giao dịch: {{ money(ledger.balance_after) }} · {{ ledger.status_label }}</span>
            <small>{{ dateTime(ledger.created_at) }}</small>
          </article>
          <p v-if="!detail.wallet_summary.ledgers.length" class="muted">Chưa có biến động ví.</p>
        </div>
      </section>

      <section v-if="activeTab === 'bookings'" class="panel">
        <h3>Lịch sử booking</h3>
        <div class="metric-row">
          <Metric label="Tổng booking" :value="detail.booking_summary.total" />
          <Metric label="Hoàn tất" :value="detail.booking_summary.completed" />
          <Metric label="Đã hủy" :value="detail.booking_summary.cancelled" />
          <Metric label="Tổng tiền hợp lệ" :value="money(detail.booking_summary.paid_total)" />
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Mã booking</th>
                <th>Cụm sân</th>
                <th>Ngày chơi</th>
                <th>Tổng tiền</th>
                <th>Booking</th>
                <th>Thanh toán</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!detail.recent_bookings.length">
                <td colspan="6" class="state">Chưa có booking.</td>
              </tr>
              <tr v-for="booking in detail.recent_bookings" :key="booking.id">
                <td>{{ booking.booking_code }}</td>
                <td>{{ booking.venue_cluster_name || '-' }}</td>
                <td>{{ date(booking.booking_date) }}</td>
                <td>{{ money(booking.total_price) }}</td>
                <td>{{ booking.status_label }}</td>
                <td>{{ booking.payment_status_label }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="activeTab === 'roles'" class="panel">
        <h3>Vai trò & quyền</h3>
        <p class="notice">Nhân viên sân và chủ sân là vai trò nghiệp vụ, không trộn với nhóm quyền admin hệ thống.</p>
        <div class="list-box">
          <article v-for="role in detail.roles" :key="role.id">
            <strong>{{ role.label }}</strong>
            <span>Scope: {{ scopeText(role) }}</span>
          </article>
          <p v-if="!detail.roles.length" class="muted">Tài khoản chưa có vai trò.</p>
        </div>
        <h4>Quyền bị thu hồi</h4>
        <div class="list-box">
          <article v-for="revoke in detail.permission_revokes" :key="revoke.id">
            <strong>{{ revoke.permission || 'Quyền đã thu hồi' }}</strong>
            <span>{{ revoke.reason || 'Không có lý do' }}</span>
            <small>{{ revoke.revoked_by_name || 'Hệ thống' }} · {{ dateTime(revoke.created_at) }}</small>
          </article>
          <p v-if="!detail.permission_revokes.length" class="muted">Chưa có quyền bị thu hồi.</p>
        </div>
      </section>

      <section v-if="activeTab === 'audit'" class="panel">
        <h3>Lịch sử thao tác / Audit log</h3>
        <div class="list-box">
          <article v-for="log in detail.audit_logs" :key="log.id">
            <strong>{{ log.action_label }}</strong>
            <span>{{ log.actor_name || 'Hệ thống' }} · {{ dateTime(log.created_at) }}</span>
            <span v-if="log.reason">Lý do: {{ log.reason }}</span>
            <div class="change-grid">
              <div>
                <b>Trước</b>
                <p v-for="item in log.old_values_summary" :key="`old-${log.id}-${item.field}`">{{ item.field }}: {{ item.value }}</p>
              </div>
              <div>
                <b>Sau</b>
                <p v-for="item in log.new_values_summary" :key="`new-${log.id}-${item.field}`">{{ item.field }}: {{ item.value }}</p>
              </div>
            </div>
            <details>
              <summary>Xem dữ liệu kỹ thuật</summary>
              <pre>{{ formatJson({ old: log.technical_old_values, new: log.technical_new_values }) }}</pre>
            </details>
          </article>
          <p v-if="!detail.audit_logs.length" class="muted">Chưa có audit log.</p>
        </div>
      </section>
    </template>

    <div v-if="actionTarget" class="modal-backdrop" @click.self="closeActionModal">
      <form class="modal" @submit.prevent="submitAccountAction">
        <h3>{{ actionType === 'lock' ? 'Khóa tài khoản' : 'Mở khóa tài khoản' }}</h3>
        <p class="muted">{{ profile.full_name || profile.username }}</p>
        <template v-if="actionType === 'lock'">
          <div class="segmented">
            <button v-for="type in lockTypes" :key="type.value" type="button" :class="{ active: actionForm.lock_type === type.value }" @click="actionForm.lock_type = type.value">
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
          <textarea v-model.trim="actionForm.reason" rows="4" required></textarea>
        </label>
        <footer>
          <button type="button" class="btn secondary" @click="closeActionModal">Hủy</button>
          <button type="submit" class="btn" :class="{ danger: actionType === 'lock' }" :disabled="saving">
            Xác nhận
          </button>
        </footer>
      </form>
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
      actionTarget: null,
      actionType: 'lock',
      actionForm: { lock_type: 'temporary', locked_until: '', reason: '' },
      tabs: [
        { value: 'overview', label: 'Tổng quan' },
        { value: 'warnings', label: 'Cảnh báo & báo cáo' },
        { value: 'wallet', label: 'Ví người dùng' },
        { value: 'bookings', label: 'Lịch sử booking' },
        { value: 'roles', label: 'Vai trò & quyền' },
        { value: 'audit', label: 'Audit log' },
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
        this.success = response.message;
        this.closeActionModal();
        await this.loadDetail();
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật trạng thái tài khoản.';
      } finally {
        this.saving = false;
      }
    },
    initials(name) {
      return String(name || 'SG').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
    },
    scopeText(role) {
      if (!role.scope_type || role.scope_type === 'global') return 'Toàn hệ thống';
      if (role.scope_type === 'venue') return `Cụm sân ${role.scope_id}`;
      return `${role.scope_type}: ${role.scope_id || '-'}`;
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
.user-detail {
  display: grid;
  gap: 16px;
}

.page-head,
.summary-card,
.head-actions {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: center;
}

.page-head {
  align-items: flex-start;
}

.page-head h2 {
  margin: 6px 0;
}

.page-head p,
.muted,
small {
  margin: 0;
  color: #64748b;
}

.back-link {
  color: #15803d;
  font-weight: 800;
  text-decoration: none;
}

.summary-card,
.panel,
.state-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 18px;
}

.summary-card {
  justify-content: flex-start;
}

.avatar {
  width: 54px;
  height: 54px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: #16a34a;
  color: #fff;
  font-weight: 900;
}

.summary-card div {
  display: grid;
  gap: 4px;
  margin-right: auto;
}

.tabs,
.metric-row,
.segmented {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.tabs button,
.segmented button {
  border: 1px solid #dbe3ef;
  background: #fff;
  border-radius: 8px;
  padding: 10px 14px;
  font-weight: 800;
  cursor: pointer;
}

.tabs button.active,
.segmented button.active {
  background: #dcfce7;
  border-color: #22c55e;
  color: #166534;
}

.panel {
  display: grid;
  gap: 14px;
}

.panel h3,
.panel h4 {
  margin: 0;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

:deep(.info-item),
.metric {
  display: grid;
  gap: 6px;
  padding: 12px;
  background: #f8fafc;
  border-radius: 10px;
}

:deep(.info-item span),
.metric span {
  color: #64748b;
  font-size: 13px;
}

.metric strong {
  font-size: 20px;
}

.notice {
  margin: 0;
  padding: 12px;
  border-radius: 10px;
  background: #f0fdf4;
  color: #166534;
  font-weight: 700;
}

.list-box {
  display: grid;
  gap: 10px;
}

.list-box article {
  display: grid;
  gap: 6px;
  padding: 12px;
  background: #f8fafc;
  border-radius: 10px;
}

.change-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.change-grid p {
  margin: 4px 0 0;
  color: #475569;
}

details {
  border-top: 1px solid #e2e8f0;
  padding-top: 8px;
}

summary {
  cursor: pointer;
  font-weight: 800;
  color: #475569;
}

pre {
  max-height: 260px;
  overflow: auto;
  background: #0f172a;
  color: #e2e8f0;
  border-radius: 8px;
  padding: 12px;
}

.table-wrap {
  overflow: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 860px;
}

th,
td {
  padding: 12px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
}

.state,
.state-card {
  color: #64748b;
  text-align: center;
}

.btn {
  border: 0;
  border-radius: 8px;
  font-weight: 800;
  cursor: pointer;
  padding: 10px 14px;
  background: #dcfce7;
  color: #166534;
}

.btn.secondary {
  background: #f1f5f9;
  color: #0f172a;
}

.btn.danger {
  background: #fee2e2;
  color: #b91c1c;
}

.status,
.warning {
  border-radius: 999px;
  padding: 6px 10px;
  font-size: 12px;
  font-weight: 800;
  background: #e2e8f0;
}

.status.active,
.warning.normal {
  background: #dcfce7;
  color: #166534;
}

.status.locked,
.status.deactivated,
.warning.lock_suggested {
  background: #fee2e2;
  color: #b91c1c;
}

.status.pending_verify,
.warning.watch,
.warning.near_lock {
  background: #fef3c7;
  color: #92400e;
}

.alert {
  padding: 12px;
  border-radius: 10px;
  font-weight: 700;
}

.error {
  background: #fee2e2;
  color: #b91c1c;
}

.success {
  background: #dcfce7;
  color: #166534;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.56);
  display: grid;
  place-items: center;
  z-index: 500;
  padding: 20px;
}

.modal {
  width: min(640px, calc(100vw - 32px));
  padding: 22px;
  background: #fff;
  border-radius: 12px;
  display: grid;
  gap: 16px;
}

label {
  display: grid;
  gap: 6px;
  font-weight: 800;
}

input,
textarea {
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  padding: 10px;
  font: inherit;
}

textarea {
  resize: vertical;
}

.modal footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

@media (max-width: 900px) {
  .page-head,
  .summary-card {
    flex-direction: column;
    align-items: flex-start;
  }

  .info-grid,
  .change-grid {
    grid-template-columns: 1fr;
  }
}
</style>

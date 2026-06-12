<template>
  <div class="bookings-page">
    <section class="page-head">
      <div>
        <h1>Lịch booking</h1>
        <p>Theo dõi booking theo ngày, sân con và trạng thái; xử lý xác nhận, từ chối, hủy, check-in, hoàn thành, đổi sân.</p>
      </div>
      <router-link class="primary-link" to="/owner/counter-booking">
        <AppIcon name="plus" size="16" />
        <span>Tạo booking tại quầy</span>
      </router-link>
    </section>

    <section class="filters">
      <label>
        <span>Cụm sân</span>
        <select v-model="filters.venue_cluster_id" @change="onClusterChange">
          <option value="">Tất cả</option>
          <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
        </select>
      </label>
      <label>
        <span>Sân con</span>
        <select v-model="filters.venue_court_id" @change="loadBookings">
          <option value="">Tất cả</option>
          <option v-for="court in courts" :key="court.id" :value="court.id">{{ court.name }}</option>
        </select>
      </label>
      <label>
        <span>Ngày chơi</span>
        <input v-model="filters.booking_date" type="date" @change="loadBookings" />
      </label>
      <label>
        <span>Trạng thái</span>
        <select v-model="filters.status" @change="loadBookings">
          <option value="">Tất cả</option>
          <option value="pending_approval">Chờ duyệt</option>
          <option value="pending_payment">Chờ thanh toán</option>
          <option value="confirmed">Đã xác nhận</option>
          <option value="checked_in">Đã check-in</option>
          <option value="completed">Hoàn thành</option>
          <option value="cancelled">Đã hủy</option>
          <option value="rejected">Từ chối</option>
        </select>
      </label>
      <button class="icon-btn" type="button" title="Tải lại" aria-label="Tải lại" @click="loadBookings">
        <AppIcon name="refresh" size="17" />
      </button>
    </section>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="notice" class="alert success">{{ notice }}</div>

    <section class="table-card">
      <div v-if="loading" class="state-card">Đang tải booking...</div>
      <div v-else-if="bookings.length === 0" class="state-card">Chưa có booking phù hợp.</div>
      <table v-else>
        <thead>
          <tr>
            <th>Mã</th>
            <th>Khách</th>
            <th>Sân</th>
            <th>Ngày / giờ</th>
            <th>Loại</th>
            <th>Thanh toán</th>
            <th>Trạng thái</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="booking in bookings" :key="booking.id">
            <td class="strong">{{ booking.booking_code }}</td>
            <td>
              <strong>{{ customerName(booking) }}</strong>
              <small>{{ customerPhone(booking) }}</small>
            </td>
            <td>
              <strong>{{ booking.venue_court?.name || '-' }}</strong>
              <small v-if="booking.requested_venue_court_id !== booking.venue_court_id">
                Yêu cầu: {{ booking.requested_venue_court?.name || '-' }}
              </small>
            </td>
            <td>
              <strong>{{ formatDate(booking.booking_date) }}</strong>
              <small>{{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}</small>
            </td>
            <td>{{ booking.booking_type === 'recurring' ? 'Cố định' : 'Lẻ' }}</td>
            <td>
              <strong>{{ paymentLabel(booking.payment_option) }}</strong>
              <small>{{ paymentSummary(booking) }}</small>
            </td>
            <td><span class="status-chip" :class="booking.status">{{ statusLabel(booking.status) }}</span></td>
            <td class="actions">
              <TableActionGroup>
                <ActionIconButton v-if="canCollectPayment(booking)" icon="banknote" label="Thu tiền" variant="primary" @click="openCollectPayment(booking)" />
                <ActionIconButton
                  icon="check"
                  label="Xác nhận"
                  variant="success"
                  :disabled="bookingHasPendingTransfer(booking)"
                  @click="updateStatus(booking, 'confirm')"
                />
                <ActionIconButton icon="clock" label="Check-in" @click="updateStatus(booking, 'check_in')" />
                <ActionIconButton icon="circleCheck" label="Hoàn thành" @click="updateStatus(booking, 'complete')" />
                <ActionIconButton icon="pencil" label="Đổi sân" @click="openChangeCourt(booking)" />
                <ActionIconButton icon="x" label="Từ chối" variant="danger" @click="updateStatus(booking, 'reject')" />
                <ActionIconButton icon="trash" label="Hủy" variant="danger" @click="updateStatus(booking, 'cancel')" />
              </TableActionGroup>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <div v-if="changeCourtBooking" class="modal-backdrop" @click.self="closeChangeCourt">
      <form class="modal-panel" @submit.prevent="saveChangeCourt">
        <header>
          <h2>Đổi sân thực tế</h2>
          <ActionIconButton icon="x" label="Đóng" variant="ghost" @click="closeChangeCourt" />
        </header>
        <label>
          <span>Sân mới</span>
          <select v-model="changeCourtForm.venue_court_id" required>
            <option v-for="court in changeCourtOptions" :key="court.id" :value="court.id">{{ court.name }} · {{ court.court_type?.name }}</option>
          </select>
        </label>
        <label>
          <span>Lý do đổi sân</span>
          <textarea v-model.trim="changeCourtForm.court_changed_reason" rows="4" required></textarea>
        </label>
        <footer>
          <button type="button" class="ghost-btn" @click="closeChangeCourt">Hủy</button>
          <button class="primary-link" type="submit" :disabled="savingChangeCourt">Lưu đổi sân</button>
        </footer>
      </form>
    </div>

    <div v-if="collectBooking" class="modal-backdrop" @click.self="closeCollectPayment">
      <form class="modal-panel collect-panel" @submit.prevent="submitCollectPayment">
        <header>
          <div>
            <h2>Thu tiền booking</h2>
            <p>{{ collectBooking.booking_code }} · {{ customerName(collectBooking) }}</p>
          </div>
          <ActionIconButton icon="x" label="Đóng" variant="ghost" @click="closeCollectPayment" />
        </header>

        <dl class="collect-summary">
          <div>
            <dt>Tổng tiền</dt>
            <dd>{{ formatCurrency(collectBooking.total_price) }}</dd>
          </div>
          <div>
            <dt>Đã thu</dt>
            <dd>{{ formatCurrency(paidAmount(collectBooking)) }}</dd>
          </div>
          <div class="highlight">
            <dt>Còn phải thu</dt>
            <dd>{{ formatCurrency(outstandingAmount(collectBooking)) }}</dd>
          </div>
        </dl>

        <label>
          <span>Số tiền thu</span>
          <input v-model.number="collectForm.amount" type="number" min="1000" step="1000" />
        </label>

        <div class="method-row">
          <button type="button" :class="{ active: collectForm.payment_method === 'cash' }" @click="collectForm.payment_method = 'cash'">
            <AppIcon name="banknote" size="16" />
            <span>Tiền mặt</span>
          </button>
          <button type="button" :class="{ active: collectForm.payment_method === 'sepay' }" @click="collectForm.payment_method = 'sepay'">
            <AppIcon name="creditCard" size="16" />
            <span>Chuyển khoản</span>
          </button>
        </div>

        <div v-if="collectQr" class="collect-qr">
          <img :src="collectQr.qr_url" alt="Mã chuyển khoản" />
          <div>
            <span>Nội dung chuyển khoản</span>
            <button type="button" @click="copyText(collectQr.transfer_content)">{{ collectQr.transfer_content }}</button>
          </div>
          <div>
            <span>Số tiền</span>
            <strong>{{ formatCurrency(collectQr.payment?.amount) }}</strong>
          </div>
          <small>Hệ thống sẽ tự cập nhật khi ngân hàng xác nhận thanh toán.</small>
        </div>

        <footer>
          <button type="button" class="ghost-btn" @click="closeCollectPayment">Đóng</button>
          <button class="primary-link" type="submit" :disabled="collectingPayment">
            {{ collectForm.payment_method === 'sepay' ? 'Tạo thông tin chuyển khoản' : 'Xác nhận thu' }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script>
import { ownerBookingService } from '../../services/ownerBookings.js';
import { venueClusterService } from '../../services/venueClusters.js';
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';

export default {
  name: 'OwnerBookings',
  components: { ActionIconButton, AppIcon, TableActionGroup },
  data() {
    return {
      clusters: [],
      courts: [],
      bookings: [],
      filters: {
        venue_cluster_id: '',
        venue_court_id: '',
        booking_date: new Date().toISOString().split('T')[0],
        status: '',
      },
      loading: true,
      error: '',
      notice: '',
      changeCourtBooking: null,
      changeCourtOptions: [],
      changeCourtForm: {
        venue_court_id: '',
        court_changed_reason: '',
      },
      savingChangeCourt: false,
      collectBooking: null,
      collectForm: {
        payment_method: 'cash',
        amount: 0,
      },
      collectQr: null,
      collectingPayment: false,
      collectPollInterval: null,
    };
  },
  async created() {
    await this.loadClusters();
    await this.loadBookings();
  },
  beforeUnmount() {
    this.clearCollectPolling();
  },
  methods: {
    async loadClusters() {
      const response = await venueClusterService.getClusters();
      this.clusters = response.data || [];
    },
    async onClusterChange() {
      this.filters.venue_court_id = '';
      this.courts = [];
      if (this.filters.venue_cluster_id) {
        const response = await venueClusterService.getCourts(this.filters.venue_cluster_id);
        this.courts = response.data || [];
      }
      await this.loadBookings();
    },
    async loadBookings() {
      this.loading = true;
      this.error = '';
      this.notice = '';
      try {
        const response = await ownerBookingService.list(this.filters);
        this.bookings = response.data || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải booking.';
      } finally {
        this.loading = false;
      }
    },
    async updateStatus(booking, action) {
      const needsReason = ['reject', 'cancel'].includes(action);
      const status_reason = needsReason ? prompt('Nhập lý do bắt buộc:') : null;
      if (needsReason && !status_reason) return;

      this.error = '';
      this.notice = '';
      try {
        await ownerBookingService.updateStatus(booking.id, { action, status_reason });
        this.notice = 'Đã cập nhật trạng thái booking.';
        await this.loadBookings();
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật booking.';
      }
    },
    async openChangeCourt(booking) {
      this.changeCourtBooking = booking;
      this.changeCourtForm = {
        venue_court_id: booking.venue_court_id,
        court_changed_reason: '',
      };
      const response = await venueClusterService.getCourts(booking.venue_cluster_id, { status: 'active' });
      this.changeCourtOptions = response.data || [];
    },
    closeChangeCourt() {
      this.changeCourtBooking = null;
      this.changeCourtOptions = [];
    },
    async saveChangeCourt() {
      if (!this.changeCourtBooking) return;
      this.savingChangeCourt = true;
      this.error = '';
      this.notice = '';

      try {
        await ownerBookingService.changeCourt(this.changeCourtBooking.id, this.changeCourtForm);
        this.notice = 'Đã đổi sân thực tế.';
        await this.loadBookings();
        this.closeChangeCourt();
      } catch (error) {
        this.error = error.message || 'Không thể đổi sân.';
      } finally {
        this.savingChangeCourt = false;
      }
    },
    openCollectPayment(booking) {
      this.collectBooking = booking;
      this.collectForm = {
        payment_method: 'cash',
        amount: this.outstandingAmount(booking),
      };
      this.collectQr = null;
      this.clearCollectPolling();
    },
    closeCollectPayment() {
      this.collectBooking = null;
      this.collectQr = null;
      this.clearCollectPolling();
    },
    async submitCollectPayment() {
      if (!this.collectBooking || this.collectingPayment) return;

      this.collectingPayment = true;
      this.error = '';
      this.notice = '';

      try {
        const response = await ownerBookingService.collectPayment(this.collectBooking.id, {
          payment_method: this.collectForm.payment_method,
          amount: this.collectForm.amount,
        });

        if (this.collectForm.payment_method === 'sepay') {
          this.collectQr = response.payment_qr || null;
          this.notice = 'Đã tạo thông tin chuyển khoản.';
          this.startCollectPolling();
        } else {
          this.notice = 'Đã ghi nhận thu tiền tại quầy.';
          await this.loadBookings();
          this.closeCollectPayment();
        }
      } catch (error) {
        this.error = error.message || 'Không thể ghi nhận thu tiền.';
      } finally {
        this.collectingPayment = false;
      }
    },
    startCollectPolling() {
      this.clearCollectPolling();
      this.collectPollInterval = setInterval(() => {
        this.refreshCollectBooking();
      }, 5000);
    },
    async refreshCollectBooking() {
      if (!this.collectBooking) return;

      try {
        const response = await ownerBookingService.show(this.collectBooking.id);
        const booking = response.data || response;
        this.collectBooking = booking;

        if (this.outstandingAmount(booking) <= 0) {
          this.notice = 'Chuyển khoản đã được ghi nhận.';
          await this.loadBookings();
          this.closeCollectPayment();
        }
      } catch {
        this.clearCollectPolling();
      }
    },
    clearCollectPolling() {
      if (this.collectPollInterval) {
        clearInterval(this.collectPollInterval);
        this.collectPollInterval = null;
      }
    },
    canCollectPayment(booking) {
      return booking.source === 'counter'
        && !['cancelled', 'expired', 'rejected'].includes(booking.status)
        && this.outstandingAmount(booking) > 0;
    },
    bookingHasPendingTransfer(booking) {
      return (booking.payments || []).some((payment) => payment.method === 'sepay' && payment.status === 'pending');
    },
    paidAmount(booking) {
      return (booking.payments || [])
        .filter((payment) => payment.status === 'paid')
        .reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
    },
    outstandingAmount(booking) {
      return Math.max(Number(booking.total_price || 0) - this.paidAmount(booking), 0);
    },
    paymentSummary(booking) {
      const paid = this.paidAmount(booking);
      const outstanding = this.outstandingAmount(booking);

      if (outstanding <= 0) return `Đã thu đủ ${this.formatCurrency(paid)}`;
      if (this.bookingHasPendingTransfer(booking)) return `Đang chờ chuyển khoản · còn ${this.formatCurrency(outstanding)}`;
      if (paid > 0) return `Đã thu ${this.formatCurrency(paid)} · còn ${this.formatCurrency(outstanding)}`;
      return `Còn phải thu ${this.formatCurrency(outstanding)}`;
    },
    async copyText(text) {
      if (!text) return;

      try {
        await navigator.clipboard.writeText(text);
        this.notice = 'Đã sao chép nội dung chuyển khoản.';
      } catch {
        this.error = 'Không thể sao chép nội dung chuyển khoản.';
      }
    },
    customerName(booking) {
      return booking.customer?.full_name || booking.customer?.username || booking.walk_in_name || 'Khách vãng lai';
    },
    customerPhone(booking) {
      return booking.customer?.phone || booking.walk_in_phone || '-';
    },
    statusLabel(status) {
      return {
        pending_approval: 'Chờ duyệt',
        pending_payment: 'Chờ thanh toán',
        confirmed: 'Đã xác nhận',
        checked_in: 'Đã check-in',
        completed: 'Hoàn thành',
        cancelled: 'Đã hủy',
        rejected: 'Từ chối',
        expired: 'Hết hạn',
      }[status] || status;
    },
    paymentLabel(option) {
      return {
        full_payment: 'Thanh toán hết',
        deposit: 'Đặt cọc',
        no_prepay: 'Không trả trước',
      }[option] || option;
    },
    formatDate(value) {
      if (!value) return '-';
      return new Intl.DateTimeFormat('vi-VN').format(new Date(value));
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
  },
};
</script>

<style scoped>
.bookings-page {
  display: grid;
  gap: 18px;
  width: 100%;
  max-width: none;
  margin: 0 auto;
}

.page-head,
.filters,
.table-card,
.state-card,
.modal-panel,
.alert {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.page-head {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  align-items: end;
  padding: 20px;
}

.page-head h1,
.modal-panel h2 {
  margin: 0;
  color: #0f172a;
  font-weight: 900;
}

.page-head p {
  margin: 6px 0 0;
  color: #64748b;
}

.filters {
  display: grid;
  grid-template-columns: 1fr 1fr 160px 180px auto;
  gap: 12px;
  align-items: end;
  padding: 16px;
}

label {
  display: grid;
  gap: 7px;
}

label span {
  color: #334155;
  font-size: 13px;
  font-weight: 900;
}

input,
select,
textarea {
  width: 100%;
  padding: 11px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #0f172a;
  font: inherit;
}

.primary-link,
.ghost-btn {
  height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 0 14px;
  border-radius: 8px;
  font-weight: 900;
}

.primary-link {
  background: #16a34a;
  color: #fff;
}

.ghost-btn {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
}

.alert,
.state-card {
  padding: 14px;
  font-weight: 900;
}

.alert.error {
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.alert.success {
  border-color: #bbf7d0;
  background: #dcfce7;
  color: #166534;
}

.state-card {
  text-align: center;
  color: #64748b;
}

.table-card {
  overflow: auto;
}

table {
  width: 100%;
  min-width: 1120px;
  border-collapse: collapse;
}

th,
td {
  padding: 12px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  vertical-align: top;
  font-size: 14px;
}

th {
  background: #f8fafc;
  color: #334155;
  font-weight: 900;
}

td small {
  display: block;
  margin-top: 4px;
  color: #64748b;
}

.strong,
td strong {
  color: #0f172a;
  font-weight: 900;
}

.status-chip {
  padding: 5px 9px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.status-chip.confirmed,
.status-chip.completed,
.status-chip.checked_in {
  background: #dcfce7;
  color: #166534;
}

.status-chip.pending_payment,
.status-chip.pending_approval {
  background: #fef3c7;
  color: #92400e;
}

.status-chip.cancelled,
.status-chip.rejected {
  background: #fee2e2;
  color: #991b1b;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: flex-end;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgba(15, 23, 42, 0.48);
}

.modal-panel {
  width: min(520px, 100%);
  display: grid;
  gap: 16px;
  padding: 20px;
}

.modal-panel header p {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 13px;
  font-weight: 700;
}

.collect-panel {
  width: min(620px, 100%);
}

.collect-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}

.collect-summary div {
  padding: 12px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #f7fbf5;
}

.collect-summary dt {
  color: #647265;
  font-size: 12px;
  font-weight: 800;
}

.collect-summary dd {
  margin: 6px 0 0;
  color: #16231a;
  font-weight: 900;
}

.collect-summary .highlight {
  border-color: rgba(47, 158, 68, 0.45);
  background: #e8f7ec;
}

.method-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.method-row button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
  color: #344238;
  font-weight: 900;
}

.method-row button.active {
  border-color: #2f9e44;
  background: #2f9e44;
  color: #fff;
}

.collect-qr {
  display: grid;
  gap: 10px;
  justify-items: start;
  padding: 14px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #f7fbf5;
}

.collect-qr img {
  width: 220px;
  max-width: 100%;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
}

.collect-qr div {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  width: 100%;
  color: #647265;
}

.collect-qr button {
  border: 0;
  background: transparent;
  color: #216b34;
  font-weight: 900;
  text-decoration: underline;
}

.collect-qr strong {
  color: #16231a;
}

.collect-qr small {
  color: #647265;
  font-weight: 700;
}

.modal-panel header,
.modal-panel footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

@media (max-width: 980px) {
  .page-head,
  .filters,
  .collect-summary,
  .method-row {
    display: grid;
    grid-template-columns: 1fr;
  }
}
</style>

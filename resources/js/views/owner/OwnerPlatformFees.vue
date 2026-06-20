<template>
  <section class="fee-page">

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="loading" class="state-card">Đang tải dữ liệu phí nền tảng...</div>
    <div v-else-if="!clusterId" class="state-card">Vui lòng chọn cụm sân ở thanh bên để xem phí.</div>

    <template v-else>
      <div class="quick-payment-bar">
        <div v-if="summary.overdue" class="payment-attention">
          <span class="attention-icon">!</span>
          <div>
            <strong>{{ summary.overdue }} kỳ phí quá hạn · {{ money(overdueAmount) }}</strong>
            <small>Thanh toán kỳ cũ nhất trước để tránh gián đoạn hoạt động.</small>
          </div>
          <button
            class="overdue-payment-btn"
            type="button"
            :disabled="submitting || !oldestOverdueFee"
            @click="payOverdue"
          >
            Thanh toán ngay
          </button>
        </div>

        <div v-else-if="dueSoonCount" class="payment-attention due-soon">
          <span class="attention-icon">i</span>
          <div>
            <strong>{{ dueSoonCount }} kỳ phí sắp đến hạn</strong>
            <small>Bạn có thể thanh toán tại bảng lịch sử kỳ phí bên dưới.</small>
          </div>
        </div>

        <div class="advance-payment">
          <div class="advance-copy">
            <strong>Thanh toán trước</strong>
            <small>Chọn cụm sân và kỳ hạn cần gia hạn</small>
          </div>
          <button type="button" :disabled="submitting" @click="openAdvancePlanner">
            Chọn sân & kỳ hạn
          </button>
        </div>
      </div>

      <div class="summary-grid">
        <article class="summary-card primary-card">
          <span>Tổng cần thanh toán</span>
          <strong>{{ money(summary.outstanding_amount) }}</strong>
          <small>{{ summary.pending + summary.overdue }} kỳ chưa hoàn tất</small>
        </article>
        <article class="summary-card">
          <span>Chờ thanh toán</span>
          <strong>{{ summary.pending }}</strong>
          <small>Kỳ còn trong hạn</small>
        </article>
        <article class="summary-card">
          <span>Quá hạn</span>
          <strong class="danger-text">{{ summary.overdue }}</strong>
          <small>Cần xử lý sớm</small>
        </article>
        <article class="summary-card">
          <span>Tổng số kỳ</span>
          <strong>{{ summary.total }}</strong>
          <small>{{ venueName || 'Cụm sân đang chọn' }}</small>
        </article>
      </div>

      <article v-if="paymentAccount" class="bank-card">
        <div>
          <p class="eyebrow">THÔNG TIN THANH TOÁN</p>
          <h3>Tài khoản nhận phí của SportGo</h3>
          <p class="muted">Mỗi kỳ phí sẽ có QR và mã chuyển khoản riêng để hệ thống tự xác nhận.</p>
        </div>
        <dl>
          <div><dt>Ngân hàng</dt><dd>{{ paymentAccount.bank_name }}</dd></div>
          <div><dt>Số tài khoản</dt><dd>{{ paymentAccount.account_number }}</dd></div>
          <div><dt>Chủ tài khoản</dt><dd>{{ paymentAccount.account_holder_name }}</dd></div>
        </dl>
      </article>

      <article class="table-card">
        <div class="table-head">
          <div>
            <h3>Lịch sử kỳ phí</h3>
            <p>Dữ liệu được tính theo kỳ phí và hạn đóng trên hệ thống.</p>
          </div>
          <div class="table-actions">
            <button class="refresh-btn" type="button" :disabled="loading" @click="loadFees">Làm mới</button>
            <select v-model="statusFilter">
              <option value="">Tất cả trạng thái</option>
              <option value="pending">Chờ thanh toán</option>
              <option value="overdue">Quá hạn</option>
              <option value="paid">Đã thanh toán</option>
              <option value="cancelled">Đã hủy</option>
            </select>
          </div>
        </div>

        <div v-if="!filteredFees.length" class="empty-state">Chưa có kỳ phí phù hợp.</div>
        <div v-else class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Kỳ phí</th>
                <th>Hạn đóng</th>
                <th>Số sân</th>
                <th>Số tiền</th>
                <th>Trạng thái</th>
                <th>Thanh toán</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="fee in filteredFees" :key="fee.id">
                <td>
                  <strong>{{ date(fee.period_start) }} - {{ date(fee.period_end) }}</strong>
                  <small>{{ cycleLabel(fee) }} · {{ fee.tier?.name || 'Theo cấu hình' }}</small>
                </td>
                <td>
                  <strong :class="{ 'danger-text': fee.effective_status === 'overdue' }">{{ date(fee.due_date) }}</strong>
                  <small v-if="fee.warning_level === 'due_soon'">Còn {{ fee.days_until_due }} ngày</small>
                  <small v-if="fee.effective_status === 'overdue'" class="danger-text">Quá hạn {{ Math.abs(fee.days_until_due) }} ngày</small>
                </td>
                <td>{{ fee.court_count }}</td>
                <td>
                  <strong>{{ money(fee.amount_due) }}</strong>
                  <small v-if="fee.amount_paid">Đã ghi nhận {{ money(fee.amount_paid) }}</small>
                </td>
                <td><span class="status-pill" :class="fee.effective_status">{{ statusLabel(fee.effective_status) }}</span></td>
                <td>
                  <span v-if="fee.effective_status === 'paid'" class="auto-status paid">Tự động xác nhận</span>
                  <span v-else class="auto-status">QR ngân hàng</span>
                  <small v-if="fee.payment?.code">Mã: {{ fee.payment.code }}</small>
                </td>
                <td class="action-cell">
                  <button v-if="canPay(fee)" class="submit-btn" type="button" :disabled="submitting" @click="openPaymentModal(fee)">
                    Thanh toán
                  </button>
                  <span v-else-if="fee.effective_status === 'paid'" class="paid-at">{{ paidAt(fee.paid_at) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </template>

    <div v-if="advancePlannerOpen" class="modal-backdrop" @click.self="closeAdvancePlanner">
      <section class="advance-modal">
        <header>
          <div>
            <p class="eyebrow">THANH TOÁN TRƯỚC</p>
            <h3>Chọn cụm sân và kỳ hạn</h3>
            <p>Phí nền tảng được tính theo toàn bộ sân con trong từng cụm.</p>
          </div>
          <button type="button" class="close-btn" @click="closeAdvancePlanner">×</button>
        </header>

        <div v-if="plannerLoading" class="planner-state">Đang kiểm tra phí của các cụm sân...</div>
        <div v-else-if="plannerError" class="alert error">{{ plannerError }}</div>
        <template v-else>
          <div v-if="clustersWithDebt.length" class="planner-warning">
            <span class="attention-icon">!</span>
            <div>
              <strong>{{ clustersWithDebt.length }} cụm sân còn phí chưa thanh toán</strong>
              <small>Bạn có muốn xử lý các khoản này trước không? Cụm đang nợ chưa thể tạo thêm kỳ trả trước.</small>
            </div>
          </div>

          <div class="cluster-plan-list">
            <article
              v-for="cluster in clusterPlans"
              :key="cluster.id"
              class="cluster-plan"
              :class="{ selected: cluster.id === clusterId, blocked: !cluster.can_prepay }"
            >
              <div class="cluster-plan-head">
                <div>
                  <strong>{{ cluster.name }}</strong>
                  <small>{{ cluster.court_count }} sân con · {{ cluster.tier_name || 'Chưa có bậc phí' }}</small>
                </div>
                <span v-if="cluster.id === clusterId" class="current-cluster">Đang xem</span>
              </div>

              <div v-if="cluster.outstanding_count" class="cluster-debt">
                <div>
                  <strong>{{ money(cluster.outstanding_amount) }}</strong>
                  <small>
                    {{ cluster.outstanding_count }} kỳ chưa thanh toán
                    <template v-if="cluster.overdue_count"> · {{ cluster.overdue_count }} quá hạn</template>
                  </small>
                </div>
                <button
                  type="button"
                  class="debt-btn"
                  :disabled="submitting || !cluster.oldest_outstanding"
                  @click="payClusterOutstanding(cluster)"
                >
                  Thanh toán khoản còn thiếu
                </button>
              </div>

              <div v-else-if="cluster.can_prepay" class="cluster-prepay">
                <div class="month-options" role="group" :aria-label="`Kỳ hạn cho ${cluster.name}`">
                  <button
                    v-for="months in advanceMonthOptions"
                    :key="months"
                    type="button"
                    :class="{ active: plannerMonths[cluster.id] === months }"
                    @click="selectPlannerMonths(cluster.id, months)"
                  >
                    {{ months }} tháng
                  </button>
                </div>
                <div class="plan-total">
                  <span>Dự kiến</span>
                  <strong>{{ money(estimatedAdvanceAmount(cluster)) }}</strong>
                </div>
                <button
                  type="button"
                  class="create-plan-btn"
                  :disabled="submitting"
                  @click="payInAdvance(cluster)"
                >
                  Tạo mã QR
                </button>
              </div>

              <p v-else class="block-reason">{{ cluster.prepay_block_reason }}</p>
            </article>
          </div>
        </template>
      </section>
    </div>

    <div v-if="paymentModal" class="modal-backdrop" @click.self="closePaymentModal">
      <section class="payment-modal">
        <header>
          <div>
            <p class="eyebrow">THANH TOÁN TỰ ĐỘNG</p>
            <strong v-if="paymentModal.title" class="modal-purpose">{{ paymentModal.title }}</strong>
            <h3>{{ date(paymentModal.fee.period_start) }} - {{ date(paymentModal.fee.period_end) }}</h3>
            <small class="modal-venue">Cụm sân: {{ paymentModal.venue_name || venueName || 'Đang chọn' }}</small>
          </div>
          <button type="button" class="close-btn" @click="closePaymentModal">×</button>
        </header>

        <div class="amount-box">
          <span>Số tiền cần chuyển</span>
          <strong>{{ money(paymentModal.amount) }}</strong>
        </div>

        <div class="qr-payment">
          <img :src="paymentModal.qr_url" alt="QR thanh toán phí nền tảng">
          <div>
            <strong>Quét QR để chuyển khoản</strong>
            <span>{{ paymentModal.payment_account.bank_name }} · {{ paymentModal.payment_account.account_number }}</span>
            <span>Chủ tài khoản: {{ paymentModal.payment_account.account_holder_name }}</span>
            <span>Nội dung: <b>{{ paymentModal.transfer_content }}</b></span>
            <button class="copy-btn" type="button" @click="copyTransferContent">Sao chép nội dung</button>
          </div>
        </div>

        <p class="review-note auto-note">
          <span class="poll-dot"></span>
          Đang chờ giao dịch từ ngân hàng. Sau khi chuyển đúng số tiền và nội dung, kỳ phí sẽ tự chuyển sang “Đã thanh toán”.
        </p>

        <footer>
          <button class="cancel-btn" type="button" @click="closePaymentModal">Đóng</button>
        </footer>
      </section>
    </div>
  </section>
</template>

<script>
import { ownerPlatformFeeService } from '../../services/ownerPlatformFees.js';

export default {
  name: 'OwnerPlatformFees',
  data() {
    return {
      fees: [],
      summary: { total: 0, pending: 0, overdue: 0, outstanding_amount: 0 },
      paymentAccount: null,
      venueName: '',
      loading: true,
      submitting: false,
      error: '',
      success: '',
      statusFilter: '',
      advanceMonthOptions: [1, 3, 6, 9],
      advancePlannerOpen: false,
      plannerLoading: false,
      plannerError: '',
      clusterPlans: [],
      plannerMonths: {},
      paymentModal: null,
      paymentPollInterval: null,
      clusterId: localStorage.getItem('selected_cluster') || '',
    };
  },
  computed: {
    dueSoonCount() {
      return this.fees.filter((fee) => fee.warning_level === 'due_soon').length;
    },
    overdueAmount() {
      return this.fees
        .filter((fee) => fee.effective_status === 'overdue')
        .reduce((total, fee) => total + Number(fee.amount_remaining || 0), 0);
    },
    filteredFees() {
      if (!this.statusFilter) return this.fees;
      return this.fees.filter((fee) => fee.effective_status === this.statusFilter);
    },
    oldestOverdueFee() {
      return this.fees
        .filter((fee) => fee.effective_status === 'overdue' && this.canPay(fee))
        .sort((first, second) => String(first.due_date).localeCompare(String(second.due_date)))[0] || null;
    },
    clustersWithDebt() {
      return this.clusterPlans.filter((cluster) => cluster.outstanding_count > 0);
    },
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.handleClusterChanged);
    await this.loadFees();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleClusterChanged);
    this.clearPaymentPolling();
  },
  methods: {
    async handleClusterChanged(event) {
      this.closePaymentModal();
      this.clusterId = event.detail?.id || localStorage.getItem('selected_cluster') || '';
      await this.loadFees();
    },
    async loadFees() {
      this.loading = true;
      this.error = '';
      this.success = '';

      if (!this.clusterId) {
        this.loading = false;
        return;
      }

      try {
        const response = await ownerPlatformFeeService.list(this.clusterId);
        this.fees = response.data || [];
        this.summary = response.summary || this.summary;
        this.paymentAccount = response.payment_account || null;
        this.venueName = response.venue_cluster?.name || '';
      } catch (error) {
        this.error = error.message || 'Không thể tải dữ liệu phí nền tảng.';
      } finally {
        this.loading = false;
      }
    },
    async openPaymentModal(fee) {
      await this.openPaymentModalForVenue(fee, this.venueName);
    },
    async payOverdue() {
      if (!this.oldestOverdueFee) return;
      await this.openPaymentModal(this.oldestOverdueFee);
    },
    async openAdvancePlanner() {
      this.advancePlannerOpen = true;
      this.plannerLoading = true;
      this.plannerError = '';

      try {
        const response = await ownerPlatformFeeService.overview();
        this.clusterPlans = response.data || [];
        this.plannerMonths = this.clusterPlans.reduce((months, cluster) => {
          months[cluster.id] = this.plannerMonths[cluster.id] || 1;
          return months;
        }, {});
      } catch (error) {
        this.plannerError = error.message || 'Không thể kiểm tra phí của các cụm sân.';
      } finally {
        this.plannerLoading = false;
      }
    },
    closeAdvancePlanner() {
      if (this.submitting) return;
      this.advancePlannerOpen = false;
    },
    selectPlannerMonths(clusterId, months) {
      this.plannerMonths = { ...this.plannerMonths, [clusterId]: months };
    },
    estimatedAdvanceAmount(cluster) {
      const months = this.plannerMonths[cluster.id] || 1;
      return cluster.estimated_amounts?.[String(months)] || cluster.monthly_amount * months;
    },
    async payClusterOutstanding(cluster) {
      if (!cluster.oldest_outstanding) return;
      this.advancePlannerOpen = false;
      await this.openPaymentModalForVenue(cluster.oldest_outstanding, cluster.name);
    },
    async openPaymentModalForVenue(fee, venueName) {
      this.submitting = true;
      this.error = '';
      this.success = '';

      try {
        const response = await ownerPlatformFeeService.createPayment(fee.id);
        this.paymentModal = {
          fee: response.data,
          amount: response.amount,
          qr_url: response.qr_url,
          transfer_content: response.transfer_content,
          payment_account: response.payment_account,
          venue_name: venueName,
        };
        this.startPaymentPolling();
      } catch (error) {
        this.error = error.message || 'Không thể tạo QR thanh toán phí nền tảng.';
      } finally {
        this.submitting = false;
      }
    },
    async payInAdvance(cluster) {
      this.submitting = true;
      this.error = '';
      this.success = '';
      const months = this.plannerMonths[cluster.id] || 1;

      try {
        const response = await ownerPlatformFeeService.createAdvancePayment(cluster.id, months);
        this.advancePlannerOpen = false;
        this.paymentModal = {
          fee: response.data,
          amount: response.amount,
          qr_url: response.qr_url,
          transfer_content: response.transfer_content,
          payment_account: response.payment_account,
          title: `Thanh toán trước ${months} tháng`,
          venue_name: cluster.name,
        };
        this.startPaymentPolling();
      } catch (error) {
        this.error = error.message || 'Không thể tạo QR thanh toán trước phí nền tảng.';
      } finally {
        this.submitting = false;
      }
    },
    closePaymentModal() {
      this.clearPaymentPolling();
      this.paymentModal = null;
    },
    startPaymentPolling() {
      this.clearPaymentPolling();
      this.paymentPollInterval = window.setInterval(this.refreshPaymentStatus, 4000);
    },
    clearPaymentPolling() {
      if (!this.paymentPollInterval) return;
      window.clearInterval(this.paymentPollInterval);
      this.paymentPollInterval = null;
    },
    async refreshPaymentStatus() {
      if (!this.paymentModal?.fee?.id) return;

      try {
        const response = await ownerPlatformFeeService.detail(this.paymentModal.fee.id);
        const fee = response.data;
        if (fee.effective_status !== 'paid') return;

        this.clearPaymentPolling();
        this.paymentModal = null;
        await this.loadFees();
        this.success = 'Thanh toán đã được ngân hàng xác nhận tự động.';
      } catch {
        // Giữ polling; lỗi mạng tạm thời không làm gián đoạn QR đang hiển thị.
      }
    },
    async copyTransferContent() {
      try {
        await navigator.clipboard.writeText(this.paymentModal?.transfer_content || '');
        this.success = 'Đã sao chép nội dung chuyển khoản.';
      } catch {
        this.error = 'Không thể sao chép nội dung chuyển khoản.';
      }
    },
    canPay(fee) {
      return ['pending', 'overdue'].includes(fee.effective_status) && Number(fee.amount_remaining) > 0;
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(value || 0);
    },
    date(value) {
      if (!value) return 'Chưa cập nhật';
      return new Intl.DateTimeFormat('vi-VN').format(new Date(`${value}T00:00:00`));
    },
    cycleLabel(fee) {
      return fee.period_months === 12 ? 'Theo năm' : `${fee.period_months || 1} tháng`;
    },
    statusLabel(status) {
      return {
        pending: 'Chờ thanh toán',
        overdue: 'Quá hạn',
        paid: 'Đã thanh toán',
        cancelled: 'Đã hủy',
      }[status] || status;
    },
    paidAt(value) {
      if (!value) return 'Đã hoàn tất';
      return new Intl.DateTimeFormat('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'short',
      }).format(new Date(value));
    },
  },
};
</script>

<style scoped>
.fee-page{display:grid;gap:18px;max-width:1280px}.quick-payment-bar{display:flex;align-items:stretch;justify-content:space-between;gap:0;overflow:hidden;border:1px solid #dbe4df;border-radius:14px;background:#fff;box-shadow:0 5px 18px rgba(15,23,42,.035)}.payment-attention{display:flex;align-items:center;gap:11px;min-width:0;flex:1;padding:13px 16px;background:#fff7f7;color:#9f1239}.payment-attention>div{display:grid;gap:3px;min-width:0}.payment-attention strong,.advance-copy strong{font-size:13px}.payment-attention small,.advance-copy small{color:#64748b;font-size:11px}.advance-copy small b{color:#334155;font-weight:850}.payment-attention.due-soon{background:#fffbeb;color:#92400e}.attention-icon{display:grid;place-items:center;width:27px;height:27px;flex:0 0 27px;border:2px solid currentColor;border-radius:50%;font-weight:900}.overdue-payment-btn,.advance-payment button{height:38px;border:0;border-radius:9px;padding:0 14px;font:inherit;font-weight:850;cursor:pointer;white-space:nowrap}.overdue-payment-btn{margin-left:auto;background:#dc2626;color:#fff}.quick-payment-bar button:disabled{opacity:.5;cursor:not-allowed}.advance-payment{display:grid;grid-template-columns:minmax(150px,1fr) 92px auto;align-items:center;gap:9px;width:min(500px,46%);margin-left:auto;padding:11px 14px;border-left:1px solid #e2e8f0}.advance-copy{display:grid;gap:2px;min-width:0}.advance-copy small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.advance-payment select{width:92px;min-width:92px;height:38px;border:1px solid #cbd5e1;border-radius:9px;padding:0 26px 0 10px;background:#fff;color:#0f172a;font:inherit;font-weight:750;white-space:nowrap}.advance-payment button{background:#059669;color:#fff}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.table-head,.payment-modal header,.payment-modal footer{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.table-head h3,.bank-card h3,.payment-modal h3{margin:0;color:#0f172a}.table-head p,.muted{margin:6px 0 0;color:#64748b}.eyebrow{margin:0 0 6px;color:#059669;font-size:11px;font-weight:900;letter-spacing:.11em}.refresh-btn,.submit-btn,.cancel-btn,.close-btn{border:0;border-radius:9px;font:inherit;font-weight:800;cursor:pointer}.refresh-btn,.cancel-btn{padding:10px 14px;background:#f1f5f9;color:#334155}.submit-btn{padding:9px 13px;background:#059669;color:#fff}.submit-btn:disabled,.refresh-btn:disabled{opacity:.55;cursor:not-allowed}.state-card,.table-card,.bank-card,.summary-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px}.state-card{padding:34px;text-align:center;color:#64748b}.alert{border-radius:12px;padding:14px 16px;font-weight:750}.alert.error{background:#fee2e2;color:#991b1b}.alert.success{background:#dcfce7;color:#166534}.summary-grid{display:grid;grid-template-columns:1.45fr repeat(3,1fr);gap:14px}.summary-card{display:grid;gap:7px;padding:19px}.summary-card span,.summary-card small{color:#64748b}.summary-card strong{font-size:24px;color:#0f172a}.primary-card{border-color:#a7f3d0;background:linear-gradient(135deg,#ecfdf5,#fff)}.primary-card strong{color:#047857}.danger-text{color:#dc2626!important}.bank-card{display:flex;justify-content:space-between;gap:24px;padding:20px}.bank-card dl{display:grid;grid-template-columns:repeat(3,minmax(130px,1fr));gap:24px;margin:0}.bank-card dl div{display:grid;gap:5px}.bank-card dt{color:#64748b;font-size:12px}.bank-card dd{margin:0;color:#0f172a;font-weight:850}.table-card{overflow:hidden}.table-head{padding:18px 20px;border-bottom:1px solid #e2e8f0}.table-actions{display:flex;align-items:center;gap:12px}.table-head select{border:1px solid #cbd5e1;border-radius:9px;padding:9px 12px;background:#fff;font:inherit;color:#334155}.table-wrap{overflow:auto}table{width:100%;min-width:1050px;border-collapse:collapse}th,td{padding:14px 16px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}th{background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.04em}td{color:#334155;font-size:13px}td strong,td small,td a{display:block}td small{margin-top:5px;color:#64748b}td a{margin-top:5px;color:#047857;font-weight:750;text-decoration:none}.status-pill{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:850}.status-pill.pending{background:#fef3c7;color:#92400e}.status-pill.overdue{background:#fee2e2;color:#991b1b}.status-pill.paid{background:#dcfce7;color:#166534}.status-pill.cancelled{background:#e2e8f0;color:#475569}.action-cell{text-align:right}.empty-state{padding:40px;text-align:center;color:#64748b}.modal-backdrop{position:fixed;inset:0;z-index:600;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.58)}.payment-modal{display:grid;gap:16px;width:min(570px,calc(100vw - 32px));padding:22px;border-radius:16px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.28)}.close-btn{padding:2px 8px;background:transparent;color:#64748b;font-size:25px}.amount-box{display:flex;justify-content:space-between;align-items:center;padding:14px;border-radius:10px;background:#ecfdf5;color:#065f46}.amount-box strong{font-size:20px}.review-note{margin:0;padding:12px;border-radius:9px;background:#f8fafc;color:#475569;font-size:13px;line-height:1.5}.payment-modal footer{justify-content:flex-end}.payment-modal .cancel-btn{padding:9px 14px}@media(max-width:1100px){.quick-payment-bar{display:grid}.advance-payment{width:100%;margin-left:0;border-top:1px solid #e2e8f0;border-left:0}.summary-grid{grid-template-columns:repeat(2,1fr)}.bank-card{display:grid}.bank-card dl{grid-template-columns:repeat(3,1fr)}}@media(max-width:680px){.payment-attention{display:grid;grid-template-columns:auto 1fr}.overdue-payment-btn{grid-column:1/3;width:100%;margin:4px 0 0}.advance-payment{grid-template-columns:minmax(0,1fr) auto}.advance-copy{grid-column:1/3}.advance-payment select{width:100%;min-width:0}.advance-payment button{width:auto}.table-head{display:grid;gap:12px}.table-actions{display:grid;grid-template-columns:1fr;gap:8px}.summary-grid{grid-template-columns:1fr}.bank-card dl{grid-template-columns:1fr;gap:12px}.refresh-btn,.table-head select{width:100%}}
.modal-purpose{display:block;margin:0 0 5px;color:#047857;font-size:13px}.modal-venue{display:block;margin-top:5px;color:#64748b;font-weight:700}.qr-payment{display:grid;grid-template-columns:150px 1fr;gap:16px;align-items:center;padding:14px;border:1px solid #a7f3d0;border-radius:12px;background:#f0fdf4}.qr-payment img{display:block;width:150px;height:150px;border-radius:8px;background:#fff;object-fit:contain}.qr-payment div{display:grid;gap:7px;color:#475569;font-size:13px}.qr-payment strong{color:#065f46;font-size:15px}.copy-btn{justify-self:start;border:0;padding:0;background:transparent;color:#047857;font:inherit;font-weight:850;text-decoration:underline;cursor:pointer}.auto-status{display:inline-flex;border-radius:999px;padding:5px 9px;background:#dbeafe;color:#1d4ed8;font-size:11px;font-weight:850}.auto-status.paid{background:#dcfce7;color:#166534}.paid-at{color:#64748b;font-size:12px;font-weight:750}.auto-note{display:flex;align-items:center;gap:9px;background:#eff6ff;color:#1e40af}.poll-dot{width:9px;height:9px;flex:0 0 9px;border-radius:50%;background:#2563eb;box-shadow:0 0 0 0 rgba(37,99,235,.45);animation:poll-pulse 1.5s infinite}@keyframes poll-pulse{70%{box-shadow:0 0 0 8px rgba(37,99,235,0)}100%{box-shadow:0 0 0 0 rgba(37,99,235,0)}}@media(max-width:560px){.qr-payment{grid-template-columns:1fr}.qr-payment img{margin:auto}}
.advance-payment{grid-template-columns:minmax(190px,1fr) auto;width:min(430px,42%)}.advance-payment>button{justify-self:end}.advance-modal{display:grid;gap:16px;width:min(850px,calc(100vw - 32px));max-height:calc(100vh - 40px);overflow:auto;padding:22px;border-radius:16px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.28)}.advance-modal header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.advance-modal h3{margin:0;color:#0f172a}.advance-modal header p:last-child{margin:6px 0 0;color:#64748b;font-size:13px}.planner-state{padding:34px;text-align:center;color:#64748b}.planner-warning{display:flex;align-items:center;gap:12px;padding:13px 15px;border:1px solid #fde68a;border-radius:11px;background:#fffbeb;color:#92400e}.planner-warning>div{display:grid;gap:3px}.planner-warning small{color:#78716c}.cluster-plan-list{display:grid;gap:10px}.cluster-plan{display:grid;gap:13px;padding:15px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.cluster-plan.selected{border-color:#6ee7b7;box-shadow:0 0 0 2px rgba(16,185,129,.08)}.cluster-plan.blocked{background:#fffdfd}.cluster-plan-head,.cluster-debt,.cluster-prepay{display:flex;align-items:center;justify-content:space-between;gap:14px}.cluster-plan-head>div,.cluster-debt>div{display:grid;gap:4px}.cluster-plan-head small,.cluster-debt small{color:#64748b;font-size:12px}.current-cluster{border-radius:999px;padding:5px 9px;background:#dcfce7;color:#047857;font-size:11px;font-weight:850}.cluster-debt{padding:11px 12px;border-radius:10px;background:#fff1f2;color:#9f1239}.cluster-debt small{color:#9f1239}.debt-btn,.create-plan-btn{height:38px;border:0;border-radius:9px;padding:0 13px;font:inherit;font-weight:850;cursor:pointer;white-space:nowrap}.debt-btn{background:#dc2626;color:#fff}.create-plan-btn{background:#059669;color:#fff}.month-options{display:flex;gap:6px}.month-options button{min-width:58px;height:36px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;color:#475569;font:inherit;font-weight:800;cursor:pointer;white-space:nowrap}.month-options button.active{border-color:#059669;background:#ecfdf5;color:#047857;box-shadow:0 0 0 1px #059669}.plan-total{display:grid;gap:2px;margin-left:auto;text-align:right}.plan-total span{color:#64748b;font-size:11px}.plan-total strong{color:#0f172a}.block-reason{margin:0;padding:10px 12px;border-radius:9px;background:#f8fafc;color:#64748b;font-size:12px}.advance-modal button:disabled{opacity:.5;cursor:not-allowed}@media(max-width:1100px){.advance-payment{width:100%}}@media(max-width:680px){.advance-payment{grid-template-columns:1fr}.advance-payment>button{justify-self:stretch;width:100%}.cluster-plan-head,.cluster-debt,.cluster-prepay{display:grid}.cluster-debt .debt-btn,.cluster-prepay .create-plan-btn{width:100%}.month-options{display:grid;grid-template-columns:repeat(4,1fr)}.month-options button{min-width:0;width:100%}.plan-total{margin-left:0;text-align:left}}
</style>

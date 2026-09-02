<template>
  <div class="sg-client-page wallet-white-page">
    <PublicNavbar />

    <main class="wallet-white-main">
      <div class="wallet-layout-grid">
        <!-- LEFT SIDEBAR NAVIGATION -->
        <ClientAccountNav />

        <!-- SKELETON LOADING STATE -->
        <div v-if="loading" class="w2-white-content">
          <div class="rf-nav-bar">
            <button type="button" class="back-link" @click="$router.push({ name: 'client-refunds' })">
              <AppIcon name="arrowLeft" :size="16" />
              <span>Tất cả yêu cầu hoàn tiền</span>
            </button>
          </div>
          <div class="rf-skeleton-wrapper">
            <div class="sk-line sk-title"></div>
            <div class="sk-line sk-sub"></div>
            <div class="sk-grid">
              <div class="sk-box"></div>
              <div class="sk-box"></div>
              <div class="sk-box"></div>
            </div>
          </div>
        </div>

        <!-- ERROR STATE -->
        <div v-else-if="error" class="w2-white-content">
          <div class="rf-nav-bar">
            <button type="button" class="back-link" @click="$router.push({ name: 'client-refunds' })">
              <AppIcon name="arrowLeft" :size="16" />
              <span>Tất cả yêu cầu hoàn tiền</span>
            </button>
          </div>
          <div class="rf-state-card rf-error">
            <AppIcon name="alert" :size="24" />
            <div>
              <h3>Không tải được thông tin hoàn tiền</h3>
              <p>{{ error }}</p>
              <button class="w2-btn w2-btn--primary" type="button" @click="load">Thử lại</button>
            </div>
          </div>
        </div>

        <!-- MAIN REFUND DETAIL CONTENT -->
        <div v-else-if="refund" class="w2-white-content">
          <!-- TOP BREADCRUMB / BACK LINK -->
          <div class="rf-nav-bar">
            <button type="button" class="back-link" @click="$router.push({ name: 'client-refunds' })">
              <AppIcon name="arrowLeft" :size="16" />
              <span>Tất cả yêu cầu hoàn tiền</span>
            </button>
          </div>

          <!-- PAGE HEADER -->
          <div class="sg3-page-head">
            <div>
              <p class="sg3-kicker">Chi tiết yêu cầu hoàn tiền</p>
              <h1 class="page-head-title">
                {{ refund.booking?.booking_code || refund.booking?.code || `Yêu cầu #${refund.id}` }}
              </h1>
              <p class="page-head-desc">
                Khởi tạo ngày {{ formatDate(refund.created_at) }}
              </p>
            </div>
            <div class="head-status-wrapper">
              <span class="sg3-status-pill" :class="statusClass(refund.status)">
                {{ statusLabel(refund.status) }}
              </span>
            </div>
          </div>

          <!-- TOP METRICS SUMMARY STRIP -->
          <section class="rf-metrics-strip" aria-label="Tóm tắt yêu cầu">
            <article class="rf-metric-card">
              <span class="rf-metric-label">Số tiền hoàn</span>
              <strong class="rf-metric-val is-green">{{ money(refund.refund_amount ?? refund.amount) }}</strong>
            </article>
            <article class="rf-metric-card">
              <span class="rf-metric-label">Phương thức nhận</span>
              <strong class="rf-metric-val">{{ destinationLabel(refund.refund_destination) }}</strong>
            </article>
            <article class="rf-metric-card">
              <span class="rf-metric-label">Trạng thái</span>
              <strong class="rf-metric-val" :class="statusTextClass(refund.status)">{{ statusLabel(refund.status) }}</strong>
            </article>
          </section>

          <!-- WORKSPACE GRID -->
          <div class="refund-detail-workspace">
            <div class="rf-main-column">
              <!-- INFORMATIONAL CARD -->
              <article class="rf-card">
                <h2 class="rf-card-title">Thông tin giao dịch & Hoàn tiền</h2>
                <dl class="rf-detail-dl">
                  <div class="rf-dl-row">
                    <dt>Mã đơn đặt sân</dt>
                    <dd>
                      <router-link v-if="bookingId" :to="{ name:'booking-detail', params:{ id: bookingId } }" class="rf-link">
                        {{ refund.booking?.booking_code || refund.booking?.code || `#${bookingId}` }}
                      </router-link>
                      <span v-else>-</span>
                    </dd>
                  </div>

                  <div class="rf-dl-row">
                    <dt>Cụm sân</dt>
                    <dd>{{ refund.booking?.venue_cluster?.name || refund.booking?.venueCluster?.name || 'Booking SportGo' }}</dd>
                  </div>

                  <div class="rf-dl-row" v-if="refund.booking?.venue_court?.name || refund.booking?.venueCourt?.name">
                    <dt>Sân thi đấu</dt>
                    <dd>{{ refund.booking?.venue_court?.name || refund.booking?.venueCourt?.name }}</dd>
                  </div>

                  <div class="rf-dl-row">
                    <dt>Số tiền hoàn trả</dt>
                    <dd class="is-highlight">{{ money(refund.refund_amount ?? refund.amount) }}</dd>
                  </div>

                  <div class="rf-dl-row">
                    <dt>Hình thức nhận tiền</dt>
                    <dd>{{ destinationLabel(refund.refund_destination) }}</dd>
                  </div>

                  <div class="rf-dl-row">
                    <dt>Lý do hoàn tiền / hủy</dt>
                    <dd>{{ refund.reason || 'Theo chính sách hủy & hoàn tiền của hệ thống' }}</dd>
                  </div>

                  <div class="rf-dl-row">
                    <dt>Cập nhật lần cuối</dt>
                    <dd>{{ formatDate(refund.updated_at || refund.created_at) }}</dd>
                  </div>
                </dl>
              </article>

              <!-- TIMELINE CARD -->
              <article class="rf-card">
                <h2 class="rf-card-title">Lịch sử xử lý yêu cầu</h2>
                <div v-if="!histories.length" class="rf-empty-inline">
                  Chưa có thông tin cập nhật tiến trình.
                </div>
                <ol v-else class="rf-timeline">
                  <li v-for="item in histories" :key="item.id" class="rf-timeline-item">
                    <div class="rf-timeline-badge" :class="statusClass(item.new_status || item.status)"></div>
                    <div class="rf-timeline-content">
                      <div class="rf-timeline-header">
                        <strong>{{ statusLabel(item.new_status || item.status) }}</strong>
                        <small>{{ formatDate(item.created_at) }}</small>
                      </div>
                      <p class="rf-timeline-note">{{ item.note || item.reason || 'Hệ thống cập nhật trạng thái yêu cầu.' }}</p>
                    </div>
                  </li>
                </ol>
              </article>
            </div>

            <!-- SIDEBAR COLUMN -->
            <aside class="rf-side-column">
              <div class="rf-card rf-guide-card">
                <div class="rf-guide-head">
                  <div>
                    <h3 class="rf-guide-title">Quy trình hoàn tiền</h3>
                    <p class="rf-guide-sub">Các bước xử lý tự động</p>
                  </div>
                </div>

                <ol class="rf-steps-list">
                  <li>
                    <strong>1. Gửi yêu cầu</strong>
                    <span>Yêu cầu được khởi tạo dựa trên lịch hủy đơn của bạn.</span>
                  </li>
                  <li>
                    <strong>2. Chủ sân / Admin xác nhận</strong>
                    <span>Hệ thống đối soát với chính sách hủy sân & phê duyệt.</span>
                  </li>
                  <li>
                    <strong>3. Nhận tiền hoàn</strong>
                    <span>Tiền được hoàn vào Ví SportGo hoặc tài khoản ngân hàng.</span>
                  </li>
                </ol>

                <div class="rf-action-group">
                  <router-link v-if="bookingId" :to="{ name:'booking-detail', params:{ id: bookingId } }" class="w2-btn w2-btn--outline w-full">
                    <AppIcon name="calendar" :size="16" />
                    <span>Xem đơn đặt sân</span>
                  </router-link>
                  <router-link to="/wallet" class="w2-btn w2-btn--primary w-full">
                    <AppIcon name="wallet" :size="16" />
                    <span>Ví SportGo</span>
                  </router-link>
                </div>
              </div>
            </aside>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import ClientAccountNav from '../../components/ClientAccountNav.vue';
import { bookingService } from '../../services/bookingService.js';

export default {
  name: 'ClientRefundDetail',
  components: { AppIcon, PublicNavbar, ClientAccountNav },
  data() {
    return {
      refund: null,
      loading: true,
      error: '',
    };
  },
  computed: {
    bookingId() {
      return this.refund?.booking_id || this.refund?.booking?.id || null;
    },
    histories() {
      return this.refund?.status_histories || this.refund?.statusHistories || [];
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = '';
      try {
        this.refund = await bookingService.getRefund(this.$route.params.id);
      } catch (error) {
        this.error = error.message || 'Không thể tải chi tiết yêu cầu hoàn tiền. Vui lòng thử lại.';
      } finally {
        this.loading = false;
      }
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(Number(value || 0));
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
    destinationLabel(destination) {
      return (
        {
          user_wallet: 'Ví SportGo',
          wallet: 'Ví SportGo',
          original_payment: 'Thanh toán gốc',
          bank_account: 'Tài khoản ngân hàng',
          cash: 'Tiền mặt',
        }[destination] || 'Không xác định'
      );
    },
    statusLabel(status) {
      return (
        {
          pending_owner_confirmation: 'Chờ chủ sân xác nhận',
          completed: 'Đã hoàn tiền',
          completed_cash: 'Đã hoàn tiền mặt',
          owner_rejected: 'Chủ sân từ chối',
        }[status] || status || 'Chưa cập nhật'
      );
    },
    statusClass(status) {
      return `status-${status || 'pending'}`;
    },
    statusTextClass(status) {
      if (['completed', 'completed_cash'].includes(status)) {
        return 'is-green';
      }
      if (['owner_rejected'].includes(status)) {
        return 'is-red';
      }
      return 'is-amber';
    },
  },
};
</script>

<style scoped>
* {
  font-weight: 400 !important;
}

.wallet-white-page {
  min-height: 100vh;
  background: #ffffff;
}

.wallet-white-main {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 24px 32px 60px !important;
  color: #0f172a;
}

.wallet-layout-grid {
  display: flex;
  gap: 32px;
  align-items: flex-start;
  width: 100%;
}

.w2-white-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.rf-nav-bar {
  margin-bottom: -4px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: none;
  background: transparent;
  color: #15803d;
  font-size: 13.5px;
  cursor: pointer;
  padding: 0;
  transition: opacity 0.15s ease;
}

.back-link:hover {
  opacity: 0.8;
  text-decoration: underline;
}

.sg3-page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.sg3-kicker {
  font-size: 12px;
  color: #475569;
  letter-spacing: 0.05em;
  margin-bottom: 4px;
}

.page-head-title {
  font-size: 24px;
  color: #0f172a;
  margin: 0 0 6px;
}

.page-head-desc {
  font-size: 13.5px;
  color: #475569;
  margin: 0;
}

.head-status-wrapper {
  padding-top: 4px;
}

.sg3-status-pill {
  font-size: 13px;
  padding: 4px 12px;
  border-radius: 9999px;
  background: #f1f5f9;
  color: #475569;
  display: inline-block;
}

.sg3-status-pill.status-completed,
.sg3-status-pill.status-completed_cash {
  background: #dcfce7;
  color: #15803d;
}

.sg3-status-pill.status-pending_owner_confirmation {
  background: #fef3c7;
  color: #b45309;
}

.sg3-status-pill.status-owner_rejected {
  background: #fee2e2;
  color: #dc2626;
}

.rf-metrics-strip {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.rf-metric-card {
  padding: 16px 20px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.rf-metric-label {
  font-size: 12px;
  color: #64748b;
}

.rf-metric-val {
  font-size: 16px;
  color: #0f172a;
}

.rf-metric-val.is-green {
  color: #15803d;
}

.rf-metric-val.is-amber {
  color: #b45309;
}

.rf-metric-val.is-red {
  color: #dc2626;
}

.refund-detail-workspace {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 24px;
  align-items: flex-start;
}

.rf-main-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.rf-card {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 24px;
  background: #ffffff;
}

.rf-card-title {
  font-size: 16px;
  color: #0f172a;
  margin: 0 0 16px 0;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.rf-detail-dl {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin: 0;
}

.rf-dl-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  font-size: 14px;
}

.rf-dl-row dt {
  color: #64748b;
}

.rf-dl-row dd {
  margin: 0;
  color: #0f172a;
  text-align: right;
}

.rf-dl-row dd.is-highlight {
  color: #15803d;
}

.rf-link {
  color: #15803d;
  text-decoration: none;
}

.rf-link:hover {
  text-decoration: underline;
}

.rf-empty-inline {
  color: #64748b;
  font-size: 13.5px;
  padding: 12px 0;
}

.rf-timeline {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.rf-timeline-item {
  display: flex;
  gap: 14px;
  position: relative;
}

.rf-timeline-item:not(:last-child)::after {
  content: '';
  position: absolute;
  left: 6px;
  top: 18px;
  bottom: -18px;
  width: 2px;
  background: #e2e8f0;
}

.rf-timeline-badge {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #cbd5e1;
  margin-top: 3px;
  flex-shrink: 0;
}

.rf-timeline-badge.status-completed,
.rf-timeline-badge.status-completed_cash,
.rf-timeline-badge.status-approved {
  background: #15803d;
}

.rf-timeline-badge.status-pending,
.rf-timeline-badge.status-processing {
  background: #d97706;
}

.rf-timeline-badge.status-rejected,
.rf-timeline-badge.status-cancelled {
  background: #dc2626;
}

.rf-timeline-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.rf-timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;

  color: #0f172a;
}

.rf-timeline-header small {
  font-size: 12px;
  color: #64748b;
}

.rf-timeline-note {
  margin: 0;
  font-size: 13px;
  color: #475569;
}

.rf-side-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.rf-guide-card {
  background: #ffffff;
}

.rf-guide-head {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.rf-guide-title {
  font-size: 15px;
  color: #0f172a;
  margin: 0;
}

.rf-guide-sub {
  font-size: 12px;
  color: #64748b;
  margin: 0;
}

.rf-steps-list {
  list-style: none;
  margin: 0 0 20px 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.rf-steps-list li {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.rf-steps-list strong {
  font-size: 13px;
  color: #0f172a;
}

.rf-steps-list span {
  font-size: 12px;
  color: #64748b;
  line-height: 1.4;
}

.rf-action-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.w2-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 9px 16px;
  font-size: 13.5px;
  border-radius: 6px;
  cursor: pointer;
  text-decoration: none;
  border: 1px solid transparent;
  transition: all 0.15s ease;
}

.w2-btn--primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.w2-btn--primary:hover {
  background: #166534;
}

.w2-btn--outline {
  background: #ffffff;
  color: #334155;
  border-color: #cbd5e1;
}

.w2-btn--outline:hover {
  background: #f8fafc;
  color: #0f172a;
}

.w-full {
  width: 100%;
}

.rf-skeleton-wrapper {
  padding: 30px 0;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sk-line {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: sk-loading 1.5s infinite;
  border-radius: 4px;
}

.sk-title {
  height: 28px;
  width: 40%;
}

.sk-sub {
  height: 16px;
  width: 20%;
}

.sk-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-top: 12px;
}

.sk-box {
  height: 90px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: sk-loading 1.5s infinite;
  border-radius: 8px;
}

@keyframes sk-loading {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.rf-state-card {
  padding: 40px;
  text-align: center;
  border-radius: 8px;
  background: #fff5f5;
  border: 1px solid #fee2e2;
  color: #dc2626;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.rf-state-card h3 {
  margin: 0 0 4px 0;
  font-size: 16px;
}

.rf-state-card p {
  margin: 0 0 16px 0;
  font-size: 13.5px;
  color: #64748b;
}

@media (max-width: 900px) {
  .refund-detail-workspace {
    grid-template-columns: 1fr;
  }
  .rf-metrics-strip {
    grid-template-columns: 1fr;
  }
}
</style>

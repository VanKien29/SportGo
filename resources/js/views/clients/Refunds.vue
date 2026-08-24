<template>
  <div class="w2-white-content">
    <div class="sg3-page-head">
            <div>
              <p class="sg3-kicker">Tài chính cá nhân</p>
              <h1 class="page-head-title">Yêu cầu hoàn tiền</h1>
              <p class="page-head-desc">Theo dõi trạng thái và số tiền hoàn từ các đơn đặt sân của bạn.</p>
            </div>
            <router-link class="w2-btn w2-btn--primary" to="/bookings">
              <AppIcon name="calendar" :size="16" />
              <span>Xem lịch đặt sân</span>
            </router-link>
          </div>

          <section v-if="loading" class="sg3-empty">
            <div>
              <strong>Đang tải yêu cầu hoàn tiền...</strong>
            </div>
          </section>

          <section v-else-if="error" class="sg3-error">
            <div>
              <strong>Không tải được yêu cầu hoàn tiền</strong>
              <p>{{ error }}</p>
              <button class="w2-btn w2-btn--primary" type="button" @click="load">Thử lại</button>
            </div>
          </section>

          <section v-else class="sg3-card sg3-request-card">
            <header class="rf-card-head">
              <span><strong>{{ total }}</strong> yêu cầu hoàn tiền</span>
              <button class="w2-btn w2-btn--outline" type="button" @click="load">Làm mới</button>
            </header>

            <div v-if="!refunds.length" class="sg3-empty sg3-empty--inline">
              <div>
                <strong>Chưa có yêu cầu hoàn tiền nào</strong>
                <p>Những khoản hoàn từ hủy booking sẽ hiển thị tại đây.</p>
              </div>
            </div>

            <article v-for="refund in refunds" :key="refund.id" class="sg3-request-row">
              <div class="rf-info-col">
                <strong class="rf-code-title">{{ refund.booking?.booking_code || refund.booking?.code || `Yêu cầu #${refund.id}` }}</strong>
                <span class="rf-venue-name">{{ refund.booking?.venue_cluster?.name || refund.booking?.venueCluster?.name || "Booking SportGo" }}</span>
                <small class="rf-date-text">{{ formatDate(refund.created_at) }}</small>
              </div>

              <div class="sg3-request-amount">
                <strong class="rf-amount-val">{{ money(refund.refund_amount ?? refund.amount) }}</strong>
                <span class="sg3-status-pill" :class="statusClass(refund.status)">{{ statusLabel(refund.status) }}</span>
              </div>

              <router-link :to="{name:'client-refund-detail',params:{id:refund.id}}" class="rf-arrow-link" aria-label="Xem chi tiết">
                <AppIcon name="chevronRight" :size="18" />
              </router-link>
            </article>

            <footer v-if="lastPage>1" class="sg3-pagination">
              <button class="w2-btn w2-btn--outline" type="button" :disabled="page<=1" @click="goPage(page-1)">Trang trước</button>
              <span>Trang {{ page }} / {{ lastPage }}</span>
              <button class="w2-btn w2-btn--outline" type="button" :disabled="page>=lastPage" @click="goPage(page+1)">Trang sau</button>
            </footer>
    </section>
  </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import { bookingService } from "../../services/bookingService.js";

export default {
  name: "ClientRefunds",
  components: { AppIcon },
  data() {
    return {
      refunds: [],
      page: 1,
      lastPage: 1,
      total: 0,
      loading: true,
      error: "",
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = "";
      try {
        const response = await bookingService.listRefunds({ page: this.page });
        this.refunds = response.data || [];
        this.page = Number(response.current_page || this.page);
        this.lastPage = Number(response.last_page || 1);
        this.total = Number(response.total || this.refunds.length);
      } catch (error) {
        this.error = error.message || "Vui lòng thử lại.";
      } finally {
        this.loading = false;
      }
    },
    goPage(page) {
      this.page = page;
      this.load();
    },
    money(value) {
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
        maximumFractionDigits: 0,
      }).format(Number(value || 0));
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleString("vi-VN") : "-";
    },
    statusLabel(status) {
      return (
        {
          pending: "Đang xử lý",
          pending_owner_confirmation: "Chờ chủ sân xác nhận",
          owner_confirmed: "Chủ sân đã xác nhận",
          admin_processing: "SportGo đang xử lý",
          approved: "Đã duyệt",
          processing: "Đang chuyển tiền",
          completed: "Đã hoàn tiền",
          completed_cash: "Đã hoàn tiền mặt",
          rejected: "Từ chối",
          owner_rejected: "Chủ sân từ chối",
          failed: "Hoàn thất bại",
          cancelled: "Đã hủy",
        }[status] || status || "Chưa cập nhật"
      );
    },
    statusClass(status) {
      return `status-${status || "pending"}`;
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
  gap: 24px;
}

.sg3-page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  padding-bottom: 12px;
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

.w2-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13.5px;
  border-radius: 4px;
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

.sg3-card,
.sg3-empty,
.sg3-error {
  border: none !important;
  box-shadow: none !important;
  background: transparent !important;
  padding: 0 !important;
  border-radius: 0 !important;
}

.sg3-empty--inline {
  padding: 40px 0 !important;
  text-align: center;
}

.rf-card-head,
.sg3-request-card > header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 0 12px 0 !important;
  font-size: 14px;
  color: #0f172a;
}

.sg3-request-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 0 !important;
  gap: 16px;
}

.rf-info-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.rf-code-title {
  font-size: 15px;
  color: #0f172a;
}

.rf-venue-name {
  font-size: 13px;
  color: #475569;
}

.rf-date-text {
  font-size: 12px;
  color: #64748b;
}

.sg3-request-amount {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.rf-amount-val {
  font-size: 16px;
  color: #0f172a;
}

.sg3-status-pill {
  font-size: 13px;
  color: #475569;
  background: transparent;
  border: none;
  padding: 0;
}

.sg3-status-pill.status-completed,
.sg3-status-pill.status-completed_cash,
.sg3-status-pill.status-approved,
.sg3-status-pill.status-owner_confirmed {
  color: #15803d;
}

.sg3-status-pill.status-pending,
.sg3-status-pill.status-pending_owner_confirmation,
.sg3-status-pill.status-admin_processing,
.sg3-status-pill.status-processing {
  color: #d97706;
}

.sg3-status-pill.status-rejected,
.sg3-status-pill.status-owner_rejected,
.sg3-status-pill.status-failed,
.sg3-status-pill.status-cancelled {
  color: #dc2626;
}

.rf-arrow-link {
  color: #64748b;
  display: flex;
  align-items: center;
}

.sg3-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding-top: 24px;
  font-size: 13.5px;
  color: #334155;
}
</style>

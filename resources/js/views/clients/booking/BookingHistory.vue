<template>
  <div class="bh-page">
    <PublicNavbar />

    <main class="bh-main">
      <!-- TOP CONTROL BAR: STATUS TABS & NEW BOOKING ACTION -->
      <div class="bh-top-bar">
        <nav class="bh-status-tabs" aria-label="Bộ lọc trạng thái">
          <button
            v-for="filter in statusFilters"
            :key="filter.value"
            type="button"
            class="bh-tab-btn"
            :class="{ active: statusGroup === filter.value }"
            @click="changeStatusGroup(filter.value)"
          >
            {{ filter.label }}
          </button>
        </nav>

        <router-link :to="{ name: 'booking-create' }" class="bh-btn bh-btn--primary">
          <AppIcon name="plus" aria-hidden="true" :size="16" />
          <span>Đặt sân mới</span>
        </router-link>
      </div>

      <!-- FILTER & SEARCH BAR -->
      <form class="bh-search-bar" @submit.prevent="applyFilters">
        <div class="bh-field">
          <label for="searchInput">Mã đơn đặt sân</label>
          <input
            id="searchInput"
            v-model.trim="searchInput"
            type="search"
            placeholder="Ví dụ: BK123456"
          />
        </div>

        <div class="bh-field">
          <label>Từ ngày</label>
          <ClientDatePicker v-model="fromDate" placeholder="Từ ngày" />
        </div>

        <div class="bh-field">
          <label>Đến ngày</label>
          <ClientDatePicker v-model="toDate" placeholder="Đến ngày" />
        </div>

        <div class="bh-search-actions">
          <button type="submit" class="bh-btn bh-btn--primary">
            <span>Tìm kiếm</span>
          </button>
          <button type="button" class="bh-btn bh-btn--outline" @click="resetFilters">
            <span>Đặt lại</span>
          </button>
        </div>
      </form>

      <!-- BOOKINGS LIST SECTION -->
      <section class="bh-content-area">
        <!-- LOADING STATE -->
        <div v-if="loading" class="bh-state">
          <span class="bh-spinner" aria-hidden="true"></span>
          <span>Đang đồng bộ dữ liệu lịch đặt sân...</span>
        </div>

        <!-- ERROR STATE -->
        <div v-else-if="error" class="bh-state bh-state--error">
          <strong>Không thể tải dữ liệu lịch đặt sân</strong>
          <p>{{ error }}</p>
          <button type="button" class="bh-btn bh-btn--outline" @click="loadBookings">Thử lại</button>
        </div>

        <!-- EMPTY STATE -->
        <div v-else-if="bookings.length === 0" class="bh-state">
          <strong>Bạn chưa có lịch đặt sân nào</strong>
          <p>Hãy bắt đầu tìm cụm sân và đặt khung giờ chơi thể thao của bạn ngay hôm nay.</p>
          <router-link :to="{ name: 'booking-create' }" class="bh-btn bh-btn--primary">
            <span>Đặt sân ngay</span>
          </router-link>
        </div>

        <!-- BOOKINGS FLAT LIST (FRAMELESS) -->
        <div v-else class="bh-booking-list">
          <article v-for="booking in bookings" :key="booking.id" class="bh-row">
            <div class="bh-row-head">
              <div>
                <span class="bh-code-tag">#{{ booking.booking_code }}</span>
                <h2 class="bh-venue-title">{{ clusterName(booking) }}</h2>
              </div>
              <span class="bh-status-pill" :class="booking.status">
                {{ statusLabel(booking.status) }}
              </span>
            </div>

            <div class="bh-row-details">
              <div class="bh-detail-item">
                <span class="bh-detail-label">Sân & Khung giờ</span>
                <strong class="bh-detail-val">{{ courtText(booking) }}</strong>
                <p class="bh-detail-sub">{{ formatDate(booking.booking_date) }} · {{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}</p>
              </div>

              <div class="bh-detail-item">
                <span class="bh-detail-label">Tổng tiền</span>
                <strong class="bh-detail-val bh-detail-val--green">{{ formatCurrency(booking.total_price) }}</strong>
                <p class="bh-detail-sub">Thanh toán: {{ paymentStatusLabel(booking.payment_status) }}</p>
              </div>

              <div class="bh-row-actions">
                <router-link
                  :to="{ name: 'booking-detail', params: { id: booking.id } }"
                  class="bh-btn bh-btn--outline"
                >
                  <span>Chi tiết</span>
                </router-link>

                <button
                  v-if="booking.can_cancel"
                  type="button"
                  class="bh-btn bh-btn--danger"
                  :disabled="cancellingId === booking.id"
                  @click="openCancelModal(booking)"
                >
                  <span>{{ cancellingId === booking.id ? "Đang hủy..." : "Hủy booking" }}</span>
                </button>
              </div>
            </div>
          </article>
        </div>

        <!-- PAGINATION -->
        <div v-if="totalPages > 1" class="bh-pagination">
          <button
            type="button"
            class="bh-btn bh-btn--outline"
            :disabled="currentPage <= 1"
            @click="changePage(currentPage - 1)"
          >
            Trang trước
          </button>
          <span class="bh-page-num">Trang {{ currentPage }} / {{ totalPages }}</span>
          <button
            type="button"
            class="bh-btn bh-btn--outline"
            :disabled="currentPage >= totalPages"
            @click="changePage(currentPage + 1)"
          >
            Trang sau
          </button>
        </div>
      </section>
    </main>

    <!-- CANCELLATION & REFUND POLICY MODAL -->
    <Teleport to="body">
      <div v-if="showCancelModal" class="bh-modal-backdrop" @click.self="closeCancelModal">
        <div class="bh-modal">
          <div class="bh-modal-head">
            <h3>Xác nhận hủy booking</h3>
            <button type="button" class="bh-modal-close" @click="closeCancelModal">✕</button>
          </div>

          <div class="bh-modal-body">
            <p class="bh-modal-desc">
              Bạn đang yêu cầu hủy đơn đặt sân <strong>#{{ cancelTarget?.booking_code }}</strong> tại {{ clusterName(cancelTarget) }}.
            </p>

            <div class="bh-refund-preview">
              <span>Chính sách hoàn tiền:</span>
              <strong v-if="loadingPreview">Đang tính toán số tiền hoàn...</strong>
              <strong v-else class="bh-refund-amount">
                Hoàn {{ previewData?.refund_percentage || 100 }}% ({{ formatCurrency(previewData?.refund_amount || cancelTarget?.total_price) }}) vào Ví SportGo
              </strong>
            </div>

            <div class="bh-field">
              <label for="cancelReason">Lý do hủy đơn</label>
              <select id="cancelReason" v-model="cancelReason">
                <option value="Khách hàng thay đổi kế hoạch">Khách hàng thay đổi kế hoạch</option>
                <option value="Thời tiết không thuận lợi">Thời tiết không thuận lợi</option>
                <option value="Muốn đặt lại giờ chơi khác">Muốn đặt lại giờ chơi khác</option>
                <option value="Lý do cá nhân khác">Lý do cá nhân khác</option>
              </select>
            </div>

            <div v-if="cancelError" class="bh-alert bh-alert--error">
              {{ cancelError }}
            </div>
          </div>

          <div class="bh-modal-foot">
            <button type="button" class="bh-btn bh-btn--outline" @click="closeCancelModal">Quay lại</button>
            <button
              type="button"
              class="bh-btn bh-btn--danger"
              :disabled="cancellingId === cancelTarget?.id"
              @click="confirmCancelBooking"
            >
              <span>{{ cancellingId === cancelTarget?.id ? "Đang hủy..." : "Xác nhận hủy đơn" }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script>
import AppIcon from "../../../components/AppIcon.vue";
import ClientDatePicker from "../../../components/ClientDatePicker.vue";
import PublicNavbar from "../../../components/PublicNavbar.vue";
import { bookingService } from "../../../services/bookingService.js";

export default {
  name: "BookingHistory",
  components: { AppIcon, ClientDatePicker, PublicNavbar },
  data() {
    return {
      bookings: [],
      loading: true,
      error: "",
      currentPage: 1,
      totalPages: 1,
      statusGroup: "all",
      searchInput: "",
      fromDate: "",
      toDate: "",
      statusFilters: [
        { label: "Tất cả", value: "all" },
        { label: "Sắp tới / Đã xác nhận", value: "upcoming" },
        { label: "Chờ thanh toán", value: "pending" },
        { label: "Hoàn thành", value: "completed" },
        { label: "Đã hủy", value: "cancelled" },
      ],
      // CANCELLATION MODAL STATE
      showCancelModal: false,
      cancelTarget: null,
      previewData: null,
      loadingPreview: false,
      cancelReason: "Khách hàng thay đổi kế hoạch",
      cancellingId: null,
      cancelError: "",
    };
  },
  mounted() {
    this.loadBookings();
  },
  methods: {
    async loadBookings() {
      this.loading = true;
      this.error = "";
      try {
        const params = {
          page: this.currentPage,
          status_group: this.statusGroup !== "all" ? this.statusGroup : undefined,
          search: this.searchInput || undefined,
          from_date: this.fromDate || undefined,
          to_date: this.toDate || undefined,
        };
        const response = await bookingService.listBookings(params);
        const payload = response.data || response;
        this.bookings = Array.isArray(payload.data) ? payload.data : Array.isArray(payload) ? payload : [];
        this.currentPage = Number(payload.meta?.current_page || payload.current_page || 1);
        this.totalPages = Number(payload.meta?.last_page || payload.last_page || 1);
      } catch (err) {
        this.error = err.message || "Không thể tải lịch sử đặt sân.";
      } finally {
        this.loading = false;
      }
    },
    changeStatusGroup(group) {
      this.statusGroup = group;
      this.currentPage = 1;
      this.loadBookings();
    },
    applyFilters() {
      this.currentPage = 1;
      this.loadBookings();
    },
    resetFilters() {
      this.searchInput = "";
      this.fromDate = "";
      this.toDate = "";
      this.statusGroup = "all";
      this.currentPage = 1;
      this.loadBookings();
    },
    changePage(page) {
      if (page < 1 || page > this.totalPages) return;
      this.currentPage = page;
      this.loadBookings();
    },
    async openCancelModal(booking) {
      this.cancelTarget = booking;
      this.showCancelModal = true;
      this.previewData = null;
      this.cancelError = "";
      this.loadingPreview = true;
      try {
        this.previewData = await bookingService.previewCancellation(booking.id);
      } catch (e) {
        // Fallback preview
        this.previewData = { refund_percentage: 100, refund_amount: booking.total_price };
      } finally {
        this.loadingPreview = false;
      }
    },
    closeCancelModal() {
      this.showCancelModal = false;
      this.cancelTarget = null;
      this.previewData = null;
      this.cancelError = "";
    },
    async confirmCancelBooking() {
      if (!this.cancelTarget) return;
      const targetId = this.cancelTarget.id;
      this.cancellingId = targetId;
      this.cancelError = "";
      try {
        await bookingService.cancelBooking(targetId, this.cancelReason);
        this.closeCancelModal();
        this.loadBookings();
      } catch (err) {
        this.cancelError = err.message || "Không thể thực hiện hủy booking.";
      } finally {
        this.cancellingId = null;
      }
    },
    clusterName(booking) {
      return (
        booking.venue_cluster?.name ||
        booking.venueCluster?.name ||
        booking.court?.venue_cluster?.name ||
        "Cụm sân thể thao"
      );
    },
    courtText(booking) {
      if (booking.items?.length > 0) {
        const names = booking.items.map((it) => it.court?.name || it.venue_court?.name).filter(Boolean);
        return names.join(", ") || "Sân thể thao";
      }
      return booking.court?.name || booking.venue_court?.name || "Sân thể thao";
    },
    formatDate(dateStr) {
      if (!dateStr) return "-";
      return new Date(dateStr).toLocaleDateString("vi-VN");
    },
    formatTime(timeStr) {
      if (!timeStr) return "";
      return timeStr.substring(0, 5);
    },
    formatCurrency(val) {
      return `${new Intl.NumberFormat("vi-VN").format(Number(val || 0))} đ`;
    },
    statusLabel(status) {
      const map = {
        pending_approval: "Chờ duyệt sân",
        pending_payment: "Chờ thanh toán",
        confirmed: "Đã xác nhận",
        checked_in: "Đang chơi",
        completed: "Hoàn thành",
        cancelled: "Đã hủy",
        expired: "Đã hết hạn",
        rejected: "Từ chối",
      };
      return map[status] || status;
    },
    paymentStatusLabel(pStatus) {
      const map = {
        pending: "Chờ thanh toán",
        paid: "Đã thanh toán",
        partially_refunded: "Hoàn tiền một phần",
        refunded: "Đã hoàn tiền",
        not_required: "Thanh toán tại sân",
        failed: "Thanh toán thất bại",
      };
      return map[pStatus] || pStatus || "Chưa thanh toán";
    },
  },
};
</script>

<style scoped>
.bh-page {
  min-height: 100vh;
  background: #ffffff;
}

.bh-main {
  max-width: 1100px;
  margin: 0 auto;
  padding: 24px 16px 60px;
}

/* TOP CONTROL BAR */
.bh-top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.bh-status-tabs {
  display: flex;
  align-items: center;
  gap: 20px;
  overflow-x: auto;
}

.bh-tab-btn {
  background: transparent;
  border: none;
  font-size: 14px;
  font-weight: 500;
  color: #1e293b;
  cursor: pointer;
  padding: 8px 0;
  border-bottom: 2px solid transparent;
  transition: color 0.15s ease, border-color 0.15s ease;
}

.bh-tab-btn.active {
  color: #15803d;
  border-bottom-color: #15803d;
}

.bh-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  border-radius: 4px;
  cursor: pointer;
  text-decoration: none;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #0f172a;
}

.bh-btn--primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.bh-btn--outline {
  background: #ffffff;
  color: #0f172a;
  border-color: #cbd5e1;
}

.bh-btn--danger {
  background: #dc2626;
  color: #ffffff;
  border-color: #dc2626;
}

/* SEARCH BAR */
.bh-search-bar {
  display: flex;
  align-items: flex-end;
  gap: 16px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}

.bh-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.bh-field label {
  font-size: 12.5px;
  font-weight: 500;
  color: #1e293b;
}

.bh-field input,
.bh-field select {
  padding: 8px 12px;
  font-size: 13.5px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #ffffff;
  color: #0f172a;
  outline: none;
  font-family: inherit;
}

.bh-field input:focus,
.bh-field select:focus {
  border-color: #15803d;
}

.bh-search-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* BOOKING LIST ROWS (FRAMELESS) */
.bh-booking-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.bh-row {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-bottom: 20px;
  border-bottom: 1px solid #f1f5f9;
}

.bh-row-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.bh-code-tag {
  font-size: 12px;
  color: #15803d;
  font-weight: 500;
}

.bh-venue-title {
  font-size: 16.5px;
  font-weight: 500;
  color: #0f172a;
  margin: 2px 0 0;
}

.bh-status-pill {
  font-size: 12.5px;
  font-weight: 500;
  color: #15803d;
}

.bh-status-pill.cancelled,
.bh-status-pill.rejected,
.bh-status-pill.expired {
  color: #dc2626;
}

.bh-status-pill.pending_payment,
.bh-status-pill.pending_approval {
  color: #d97706;
}

.bh-row-details {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
}

.bh-detail-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.bh-detail-label {
  font-size: 12px;
  color: #1e293b;
}

.bh-detail-val {
  font-size: 14.5px;
  font-weight: 500;
  color: #0f172a;
}

.bh-detail-val--green {
  color: #15803d;
}

.bh-detail-sub {
  font-size: 12.5px;
  color: #1e293b;
  margin: 0;
}

.bh-row-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* STATES */
.bh-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 16px;
  text-align: center;
  gap: 8px;
  color: #1e293b;
}

.bh-state strong {
  font-size: 16px;
  color: #0f172a;
}

.bh-spinner {
  width: 24px;
  height: 24px;
  border: 2px solid #e2e8f0;
  border-top-color: #15803d;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.bh-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin-top: 32px;
}

.bh-page-num {
  font-size: 13.5px;
  color: #0f172a;
}

/* MODAL STYLES */
.bh-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 16px;
}

.bh-modal {
  background: #ffffff;
  border-radius: 6px;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.bh-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px 8px;
}

.bh-modal-head h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
}

.bh-modal-close {
  background: transparent;
  border: none;
  font-size: 16px;
  color: #64748b;
  cursor: pointer;
}

.bh-modal-body {
  padding: 12px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.bh-modal-desc {
  font-size: 13px;
  color: #1e293b;
  margin: 0;
  line-height: 1.5;
}

.bh-refund-preview {
  background: #ffffff;
  border: 1px dashed #cbd5e1;
  border-radius: 4px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}

.bh-refund-amount {
  color: #15803d;
  font-size: 14px;
}

.bh-alert {
  padding: 10px 14px;
  font-size: 13px;
  border-radius: 4px;
}

.bh-alert--error {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.bh-modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 8px 20px 20px;
  background: #ffffff;
}
</style>

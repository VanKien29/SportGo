<template>
  <div class="sg-client-page wallet-white-page">
    <PublicNavbar />

    <main class="wallet-white-main">
      <div class="wallet-layout-grid">
        <!-- LEFT SIDEBAR NAVIGATION -->
        <ClientAccountNav />

        <!-- RIGHT PAGE CONTENT -->
        <div class="w2-white-content">
          <!-- PAGE HEADER -->
          <div class="sg3-page-head">
            <div>
              <p class="sg3-kicker">Lịch sử hoạt động</p>
              <h1 class="page-head-title">Lịch đặt sân của tôi</h1>
              <p class="page-head-desc">Quản lý và theo dõi danh sách các đơn đặt sân thể thao của bạn.</p>
            </div>
            <router-link :to="{ name: 'booking-create' }" class="w2-btn w2-btn--primary">
              <span>Đặt sân mới</span>
            </router-link>
          </div>

          <!-- STATUS FILTER TABS & FILTER WORKSPACE -->
          <div class="w2-toolbar">
            <div class="w2-tabs" role="tablist" aria-label="Lọc trạng thái booking">
              <button
                v-for="filter in statusFilters"
                :key="filter.value"
                type="button"
                class="w2-tab"
                :class="{ 'is-active': statusGroup === filter.value }"
                @click="changeStatusGroup(filter.value)"
              >
                {{ filter.label }}
              </button>
            </div>

            <div class="bh-toolbar-actions">
              <span class="bh-result-count">{{ totalBookings }} booking</span>
              <button type="button" class="w2-btn w2-btn--outline" :class="{ 'is-active': showAdvancedFilters }" @click="showAdvancedFilters = !showAdvancedFilters">
                Bộ lọc <span v-if="activeFilterCount" class="bh-filter-count">{{ activeFilterCount }}</span>
              </button>
            </div>
          </div>

          <form class="bh-filter-panel" :class="{ 'is-open': showAdvancedFilters }" @submit.prevent="applyFilters">
            <div class="bh-filter-field bh-filter-field--search">
              <label for="bookingSearch">Tìm theo mã booking</label>
              <input id="bookingSearch" v-model.trim="searchInput" type="search" class="w2-search-input" placeholder="Nhập mã booking..." />
            </div>
            <div class="bh-filter-field">
              <label>Khoảng ngày</label>
              <div class="bh-date-range">
                <ClientDatePicker v-model="fromDate" placeholder="Từ ngày" />
                <ClientDatePicker v-model="toDate" placeholder="Đến ngày" />
              </div>
            </div>
            <div class="bh-filter-field">
              <label for="bookingType">Loại booking</label>
              <select id="bookingType" v-model="bookingType" class="bh-select-inline">
                <option value="">Tất cả</option>
                <option value="single">Booking lẻ</option>
                <option value="recurring">Lịch cố định</option>
              </select>
            </div>
            <div class="bh-filter-field">
              <label for="paymentStatus">Thanh toán</label>
              <select id="paymentStatus" v-model="paymentStatus" class="bh-select-inline">
                <option value="">Tất cả</option>
                <option value="pending">Chờ thanh toán</option>
                <option value="paid">Đã thanh toán</option>
                <option value="not_required">Thanh toán tại sân</option>
                <option value="refunded">Đã hoàn tiền</option>
                <option value="failed">Thanh toán lỗi</option>
              </select>
            </div>
            <div class="bh-filter-panel-actions">
              <button type="submit" class="w2-btn w2-btn--primary">Áp dụng</button>
              <button v-if="activeFilterCount" type="button" class="w2-btn w2-btn--outline" @click="resetFilters">Xóa lọc</button>
            </div>
          </form>
          <p v-if="filterError" class="bh-filter-error">{{ filterError }}</p>

          <!-- BOOKINGS CONTENT AREA -->
          <section class="bh-content-area">
            <!-- SKELETON LOADING STATE -->
            <div v-if="loading" class="w2-skeleton-wrapper">
              <div v-for="n in 3" :key="n" class="w2-sk-row">
                <div class="w2-sk-circle"></div>
                <div class="w2-sk-col">
                  <div class="w2-sk-line w2-sk-text1"></div>
                  <div class="w2-sk-line w2-sk-text2"></div>
                </div>
              </div>
            </div>

            <!-- ERROR STATE -->
            <div v-else-if="error" class="w2-state-card w2-error">
              <div>
                <span>Không thể tải dữ liệu lịch đặt sân</span>
                <p>{{ error }}</p>
                <button type="button" class="w2-btn w2-btn--primary" @click="loadBookings">Thử lại</button>
              </div>
            </div>

            <!-- EMPTY STATE -->
            <div v-else-if="bookings.length === 0" class="w2-empty-ledger">
              <span class="w2-empty-title">Bạn chưa có đơn đặt sân nào</span>
              <p>Hãy bắt đầu tìm cụm sân và chọn khung giờ chơi phù hợp.</p>
              <router-link :to="{ name: 'booking-create' }" class="w2-btn w2-btn--primary">
                <span>Đặt sân ngay</span>
              </router-link>
            </div>

            <!-- BOOKINGS LIST ROWS -->
            <div v-else class="bh-booking-list">
              <article v-for="booking in bookings" :key="booking.id" class="bh-booking-row">
                <div class="bh-row-left">
                  <div class="bh-row-header">
                    <span class="bh-code-tag">#{{ booking.booking_code }}</span>
                    <strong class="bh-venue-title">{{ clusterName(booking) }}</strong>
                  </div>

                  <div class="bh-court-info">
                    <span>Sân &amp; Giờ: <strong>{{ courtText(booking) }}</strong></span>
                    <small class="bh-time-sub">{{ formatDate(booking.booking_date) }} · {{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}</small>
                  </div>
                </div>

                <div class="bh-row-right">
                  <div class="bh-price-block">
                    <span class="bh-total-price">{{ formatCurrency(booking.total_price) }}</span>
                    <span class="bh-payment-tag">{{ paymentStatusLabel(booking.payment_status) }}</span>
                  </div>

                  <div class="bh-status-block">
                    <span class="sg3-status-pill" :class="booking.status">
                      {{ statusLabel(booking.status) }}
                    </span>
                  </div>

                  <div class="bh-actions-block">
                    <router-link
                      :to="{ name: 'booking-detail', params: { id: booking.id } }"
                      class="w2-btn w2-btn--outline"
                    >
                      <span>Chi tiết</span>
                    </router-link>

                    <button
                      v-if="booking.can_cancel"
                      type="button"
                      class="w2-btn w2-btn--outline is-danger"
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
            <div v-if="totalPages > 1" class="sg3-pagination">
              <button
                type="button"
                class="w2-btn w2-btn--outline"
                :disabled="currentPage <= 1"
                @click="changePage(currentPage - 1)"
              >
                Trang trước
              </button>
              <span>Trang {{ currentPage }} / {{ totalPages }}</span>
              <button
                type="button"
                class="w2-btn w2-btn--outline"
                :disabled="currentPage >= totalPages"
                @click="changePage(currentPage + 1)"
              >
                Trang sau
              </button>
            </div>
          </section>
        </div>
      </div>
    </main>

    <!-- CANCELLATION MODAL -->
    <Teleport to="body">
      <div v-if="showCancelModal" class="bh-modal-backdrop" @click.self="closeCancelModal">
        <div class="bh-modal">
          <div class="bh-modal-head">
            <h3>Hủy đơn đặt sân &amp; Hoàn tiền</h3>
            <button type="button" class="bh-modal-close" @click="closeCancelModal">✕</button>
          </div>

          <div class="bh-modal-body">
            <p class="bh-modal-desc">
              Bạn đang yêu cầu hủy đơn đặt sân <strong>#{{ cancelTarget?.code || cancelTarget?.booking_code || cancelTarget?.id }}</strong>.
            </p>

            <div v-if="loadingPreview" class="bh-sk-preview">
              <span>Đang tính toán chính sách hoàn tiền...</span>
            </div>

            <div v-else-if="previewData" class="bh-policy-box">
              <div class="bh-policy-row">
                <span>Tỷ lệ hoàn tiền:</span>
                <strong>{{ previewData.refund_percentage }}%</strong>
              </div>
              <div class="bh-policy-row">
                <span>Số tiền sẽ hoàn vào Ví:</span>
                <strong class="is-green">{{ formatCurrency(previewData.refund_amount) }}</strong>
              </div>
            </div>

            <div class="bh-form-group">
              <label for="cancelReason">Lý do hủy đơn</label>
              <select id="cancelReason" v-model="cancelReason" class="bh-select">
                <option value="Khách hàng thay đổi kế hoạch">Khách hàng thay đổi kế hoạch</option>
                <option value="Thời tiết không thuận lợi">Thời tiết không thuận lợi</option>
                <option value="Đặt nhầm giờ / nhầm sân">Đặt nhầm giờ / nhầm sân</option>
                <option value="Lý do cá nhân khác">Lý do cá nhân khác</option>
              </select>
            </div>

            <div v-if="cancelError" class="bh-alert bh-alert--error">
              {{ cancelError }}
            </div>
          </div>

          <div class="bh-modal-foot">
            <button type="button" class="w2-btn w2-btn--outline" @click="closeCancelModal">Quay lại</button>
            <button
              type="button"
              class="w2-btn w2-btn--outline is-danger"
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
import ClientDatePicker from "../../../components/ClientDatePicker.vue";
import PublicNavbar from "../../../components/PublicNavbar.vue";
import ClientAccountNav from "../../../components/ClientAccountNav.vue";
import { bookingService } from "../../../services/bookingService.js";

export default {
  name: "BookingHistory",
  components: { ClientDatePicker, PublicNavbar, ClientAccountNav },
  data() {
    return {
      bookings: [],
      loading: true,
      error: "",
      currentPage: 1,
      totalPages: 1,
      totalBookings: 0,
      statusGroup: "all",
      searchInput: "",
      fromDate: "",
      toDate: "",
      bookingType: "",
      paymentStatus: "",
      showAdvancedFilters: true,
      filterError: "",
      statusFilters: [
        { label: "Tất cả", value: "all" },
        { label: "Sắp tới", value: "upcoming" },
        { label: "Hoàn thành", value: "completed" },
        { label: "Đã hủy", value: "cancelled" },
        { label: "Đã hoàn tiền", value: "refunded" },
      ],
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
  computed: {
    activeFilterCount() {
      return [this.searchInput, this.fromDate, this.toDate, this.bookingType, this.paymentStatus]
        .filter(Boolean).length;
    },
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
          booking_type: this.bookingType || undefined,
          payment_status: this.paymentStatus || undefined,
        };
        const response = await bookingService.listBookings(params);
        const payload = response.data || response;
        this.bookings = Array.isArray(payload.data) ? payload.data : Array.isArray(payload) ? payload : [];
        this.currentPage = Number(payload.meta?.current_page || payload.current_page || 1);
        this.totalPages = Number(payload.meta?.last_page || payload.last_page || 1);
        this.totalBookings = Number(payload.meta?.total || payload.total || this.bookings.length);
      } catch (err) {
        this.error = err.message || "Không thể tải lịch sử đặt sân.";
      } finally {
        this.loading = false;
      }
    },
    changeStatusGroup(val) {
      this.statusGroup = val;
      this.currentPage = 1;
      this.loadBookings();
    },
    applyFilters() {
      this.filterError = "";
      if (this.fromDate && this.toDate && this.fromDate > this.toDate) {
        this.filterError = "Ngày bắt đầu không được sau ngày kết thúc.";
        return;
      }
      this.currentPage = 1;
      this.loadBookings();
    },
    resetFilters() {
      this.searchInput = "";
      this.fromDate = "";
      this.toDate = "";
      this.bookingType = "";
      this.paymentStatus = "";
      this.filterError = "";
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
        this.cancelError = err.message || "Không thể hủy đơn đặt sân.";
      } finally {
        this.cancellingId = null;
      }
    },
    clusterName(booking) {
      return (
        booking.venue_cluster?.name ||
        booking.venueCluster?.name ||
        booking.cluster_name ||
        "Cụm sân SportGo"
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

.w2-btn--outline {
  background: #ffffff;
  color: #0f172a;
  border-color: #cbd5e1;
}

.w2-btn--outline.is-danger {
  color: #dc2626;
  border-color: #fca5a5;
}

.w2-btn--outline.is-danger:hover {
  background: #fef2f2;
}

.w2-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding-bottom: 12px;
  flex-wrap: wrap;
}

.bh-toolbar-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-left: auto;
}

.bh-result-count {
  color: #64748b;
  font-size: 12.5px;
  white-space: nowrap;
}

.w2-btn.is-active {
  border-color: #15803d;
  color: #15803d;
  background: #f0fdf4;
}

.bh-filter-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 4px;
  border-radius: 9px;
  background: #15803d;
  color: #ffffff;
  font-size: 11px;
}

.bh-filter-panel {
  display: grid;
  grid-template-columns: minmax(180px, 1.1fr) minmax(260px, 1.4fr) minmax(140px, .8fr) minmax(160px, .9fr) auto;
  gap: 12px;
  align-items: end;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #f8fafc;
}

.bh-filter-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.bh-filter-field label { color: #475569; font-size: 12px; }
.bh-filter-field .w2-search-input { width: 100%; box-sizing: border-box; }
.bh-date-range { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.bh-select-inline { width: 100%; min-height: 36px; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 4px; background: #ffffff; color: #0f172a; font-size: 13px; }
.bh-filter-panel-actions { display: flex; align-items: center; gap: 8px; }
.bh-filter-error { margin: -14px 0 0; color: #b91c1c; font-size: 12.5px; }

.w2-tabs {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
}

.w2-tab {
  display: inline-flex;
  align-items: center;
  padding: 7px 14px;
  font-size: 13.5px;
  color: #334155;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 4px;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.w2-tab.is-active {
  color: #15803d;
  background: #ffffff;
  border-color: #15803d;
}

.bh-filter-form {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.w2-search-input {
  padding: 8px 12px;
  font-size: 13.5px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  outline: none;
  background: #ffffff;
  color: #0f172a;
  width: 170px;
}

.bh-date-picker-wrap {
  width: 140px;
}

/* BOOKING LIST ROWS */
.bh-booking-list {
  display: flex;
  flex-direction: column;
}

.bh-booking-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 0;
  gap: 20px;
}

.bh-row-left {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.bh-row-header {
  display: flex;
  align-items: center;
  gap: 10px;
}

.bh-code-tag {
  font-family: monospace;
  font-size: 12.5px;
  background: #f8fafc;
  padding: 2px 7px;
  border-radius: 4px;
  border: 1px solid #e2e8f0;
  color: #0f172a;
}

.bh-venue-title {
  font-size: 15px;
  color: #0f172a;
  margin: 0;
}

.bh-court-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13.5px;
  color: #334155;
}

.bh-time-sub {
  font-size: 12px;
  color: #64748b;
}

.bh-row-right {
  display: flex;
  align-items: center;
  gap: 24px;
}

.bh-price-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}

.bh-total-price {
  font-size: 16px;
  color: #0f172a;
}

.bh-payment-tag {
  font-size: 11.5px;
  color: #64748b;
}

.sg3-status-pill {
  font-size: 13px;
  color: #475569;
  background: transparent;
  border: none;
  padding: 0;
}

.sg3-status-pill.confirmed,
.sg3-status-pill.completed,
.sg3-status-pill.checked_in {
  color: #15803d;
}

.sg3-status-pill.pending,
.sg3-status-pill.pending_approval,
.sg3-status-pill.pending_payment {
  color: #d97706;
}

.sg3-status-pill.cancelled,
.sg3-status-pill.rejected,
.sg3-status-pill.expired {
  color: #dc2626;
}

.bh-actions-block {
  display: flex;
  align-items: center;
  gap: 8px;
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

/* SKELETON LOADING STATE */
.w2-skeleton-wrapper {
  flex: 1;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.w2-sk-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 0;
  border-bottom: 1px solid #f1f5f9;
}

.w2-sk-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: w2SkShimmer 1.5s infinite;
}

.w2-sk-col {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.w2-sk-line {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: w2SkShimmer 1.5s infinite;
  border-radius: 4px;
}

.w2-sk-text1 { width: 45%; height: 16px; }
.w2-sk-text2 { width: 28%; height: 12px; }

@keyframes w2SkShimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

@media (max-width: 1000px) {
  .bh-filter-panel { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .bh-filter-field--search { grid-column: span 2; }
  .bh-filter-panel-actions { grid-column: span 2; }
}

@media (max-width: 680px) {
  .bh-toolbar-actions { width: 100%; margin-left: 0; justify-content: space-between; }
  .bh-filter-panel { grid-template-columns: 1fr; }
  .bh-filter-field--search,
  .bh-filter-panel-actions { grid-column: auto; }
  .bh-date-range { grid-template-columns: 1fr; }
}

.w2-state-card {
  flex: 1;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 48px 24px;
  background: transparent;
  border: none;
  gap: 16px;
  color: #0f172a;
}

.w2-empty-ledger {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 48px 24px;
  color: #334155;
  gap: 10px;
}

.w2-empty-title {
  font-size: 16px;
  color: #0f172a;
}

.bh-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.65);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 3000;
  padding: 16px;
}

.bh-modal {
  background: #ffffff;
  border-radius: 8px;
  width: 100%;
  max-width: 480px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #cbd5e1;
  color: #0f172a;
}

.bh-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px 14px;
  border-bottom: 1px solid #f1f5f9;
}

.bh-modal-head h3 {
  margin: 0;
  font-size: 17px;
  color: #0f172a;
}

.bh-modal-close {
  background: transparent;
  border: none;
  font-size: 14px;
  color: #475569;
  cursor: pointer;
}

.bh-modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.bh-policy-box {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13.5px;
}

.bh-policy-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: #334155;
}

.is-green {
  color: #15803d;
}

.bh-form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.bh-form-group label {
  font-size: 13px;
  color: #0f172a;
}

.bh-select {
  padding: 9px 12px;
  font-size: 13.5px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #ffffff;
  color: #0f172a;
  outline: none;
}

.bh-alert {
  padding: 10px 12px;
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
  padding: 14px 24px 18px;
  border-top: 1px solid #f1f5f9;
}
</style>

<template>
  <div class="booking-history-page sg-client-page sg3-history-page">
    <PublicNavbar />

    <main class="history-main sg-client-shell">
      <section class="history-header">
        <div>
          <nav class="breadcrumbs" aria-label="Điều hướng booking">
            <router-link to="/venues">Tìm sân</router-link>
            <AppIcon name="chevronRight" aria-hidden="true" />
            <strong>Lịch sử đặt sân</strong>
          </nav>
          <p class="eyebrow sg-client-eyebrow">Lịch sử booking</p>
          <h1>Lịch sử đặt sân</h1>
          <p>Theo dõi các đơn sân sắp tới, đã hoàn tất, đã hủy và đã hoàn tiền.</p>
        </div>
        <router-link :to="{ name: 'booking-create' }" class="primary-action sg-client-button sg-client-button--primary">
          <AppIcon name="plus" aria-hidden="true" />
          Đặt sân mới
        </router-link>
      </section>

      <ClientAccountNav />

      <section class="filters" aria-label="Lọc booking">
        <button
          v-for="filter in statusFilters"
          :key="filter.value"
          type="button"
          :class="{ active: statusGroup === filter.value }"
          :aria-pressed="statusGroup === filter.value"
          @click="changeStatusGroup(filter.value)"
        >
          {{ filter.label }}
        </button>
      </section>

      <form class="history-filters" @submit.prevent="applyFilters">
        <label>
          <span>Tìm theo mã booking</span>
          <input v-model.trim="searchInput" type="search" placeholder="Ví dụ: BK123456" />
        </label>
        <label>
          <span>Từ ngày</span>
          <input v-model="fromDate" type="date" />
        </label>
        <label>
          <span>Đến ngày</span>
          <input v-model="toDate" type="date" />
        </label>
        <label>
          <span>Loại booking</span>
          <select v-model="bookingType">
            <option value="">Tất cả</option>
            <option value="single">Đặt lẻ</option>
            <option value="recurring">Đặt cố định</option>
          </select>
        </label>
        <label>
          <span>Thanh toán</span>
          <select v-model="paymentStatus">
            <option value="">Tất cả</option>
            <option value="pending">Chờ thanh toán</option>
            <option value="paid">Đã thanh toán</option>
            <option value="refunded">Đã hoàn tiền</option>
            <option value="not_required">Thanh toán tại sân</option>
          </select>
        </label>
        <button type="submit" class="sg-client-button sg-client-button--primary">
          <AppIcon name="search" aria-hidden="true" />
          Áp dụng
        </button>
        <button type="button" class="sg-client-button" @click="resetFilters">
          <AppIcon name="refresh" aria-hidden="true" />
          Xóa lọc
        </button>
      </form>

      <section class="history-panel sg-client-card">
        <div v-if="loading" class="state sg-client-state">
          <span class="spinner" aria-hidden="true"></span>
          Đang tải lịch sử booking...
        </div>

        <div v-else-if="error" class="state error sg-client-state">
          <AppIcon name="alert" aria-hidden="true" />
          <strong>Không tải được lịch sử đặt sân</strong>
          <span>{{ error }}</span>
          <button type="button" class="sg-client-button" @click="loadBookings">
            <AppIcon name="refresh" aria-hidden="true" />
            Thử lại
          </button>
        </div>

        <div v-else-if="bookings.length === 0" class="state empty sg-client-state">
          <AppIcon name="calendar" aria-hidden="true" />
          <strong>Chưa có booking phù hợp.</strong>
          <span>Thử đổi bộ lọc hoặc đặt sân mới để bắt đầu.</span>
          <router-link :to="{ name: 'booking-create' }" class="sg-client-button sg-client-button--primary">
            <AppIcon name="plus" aria-hidden="true" />
            Đặt sân mới
          </router-link>
        </div>

        <div v-else class="booking-list">
          <article v-for="booking in bookings" :key="booking.id" class="booking-card sg-client-card">
            <div class="booking-topline">
              <div>
                <span class="code">#{{ booking.booking_code }}</span>
                <h2>{{ clusterName(booking) }}</h2>
              </div>
              <span class="status-badge" :class="booking.status">
                {{ statusLabel(booking.status) }}
              </span>
            </div>

            <div class="booking-meta">
              <div>
                <span>Sân</span>
                <strong>{{ courtText(booking) }}</strong>
                <small v-if="booking.items?.length > 1">{{ booking.items.length }} khung giờ/sân</small>
              </div>
              <div>
                <span>Thời gian chơi</span>
                <strong>{{ formatDate(booking.booking_date) }} · {{ formatTime(booking.start_time) }}-{{ formatTime(booking.end_time) }}</strong>
              </div>
              <div>
                <span>Thành tiền</span>
                <strong>{{ formatCurrency(booking.total_price) }}</strong>
              </div>
              <div>
                <span>Thanh toán</span>
                <strong>{{ paymentStatusLabel(booking.payment_status) }}</strong>
              </div>
            </div>

            <div v-if="booking.has_court_change || booking.has_partial_cancellation" class="booking-flags">
              <span v-if="booking.has_court_change">Đã đổi sân</span>
              <span v-if="booking.has_partial_cancellation">Có khung bị hủy/gián đoạn</span>
            </div>

            <div class="booking-actions">
              <router-link :to="{ name: 'booking-detail', params: { id: booking.id } }" class="ghost-action primary-detail">
                <AppIcon name="eye" aria-hidden="true" />
                Xem chi tiết
              </router-link>
              <router-link
                v-if="venueId(booking)"
                :to="{ name: 'venue-detail', params: { id: venueId(booking) } }"
                class="ghost-action"
              >
                <AppIcon name="mapPin" aria-hidden="true" />
                Xem sân
              </router-link>
              <router-link v-if="venueId(booking)" :to="rebookLocation(booking)" class="ghost-action">
                <AppIcon name="rotateCcw" aria-hidden="true" />
                Đặt thêm lịch
              </router-link>
              <router-link
                v-if="booking.booking_type === 'recurring' && booking.recurring_group_code"
                :to="{ name: 'booking-recurring-group', params: { groupCode: booking.recurring_group_code } }"
                class="ghost-action"
              >
                <AppIcon name="calendar" aria-hidden="true" />
                Xem cả chuỗi
              </router-link>
              <button
                v-if="booking.can_cancel"
                type="button"
                class="danger-action"
                :disabled="cancellingId === booking.id"
                @click="cancelBooking(booking)"
              >
                <AppIcon name="circleX" aria-hidden="true" />
                {{ cancellingId === booking.id ? 'Đang hủy...' : 'Hủy booking' }}
              </button>
            </div>
          </article>
        </div>

        <div v-if="lastPage > 1" class="pagination">
          <button type="button" :disabled="page <= 1" aria-label="Trang trước" @click="changePage(page - 1)">
            <AppIcon name="chevronLeft" aria-hidden="true" />
            Trước
          </button>
          <span>Trang {{ page }} / {{ lastPage }}</span>
          <button type="button" :disabled="page >= lastPage" aria-label="Trang sau" @click="changePage(page + 1)">
            Sau
            <AppIcon name="chevronRight" aria-hidden="true" />
          </button>
        </div>
      </section>
    </main>

    <ConfirmActionModal
      :is-open="Boolean(cancelTarget)"
      title="Hủy booking này?"
      :description="cancelDescription"
      confirm-text="Xác nhận hủy"
      require-reason
      reason-label="Lý do hủy"
      reason-placeholder="Nêu ngắn gọn lý do để sân và SportGo hỗ trợ đúng chính sách"
      initial-reason="Khách hàng thay đổi kế hoạch"
      :loading="Boolean(cancellingId)"
      :error="cancelError"
      @close="closeCancelModal"
      @confirm="confirmCancellation"
    />
  </div>
</template>

<script>
import AppIcon from "../../../components/AppIcon.vue";
import ClientAccountNav from "../../../components/ClientAccountNav.vue";
import ConfirmActionModal from "../../../components/ConfirmActionModal.vue";
import PublicNavbar from "../../../components/PublicNavbar.vue";
import { bookingService } from "../../../services/bookingService.js";

export default {
  name: "BookingHistory",
  components: { AppIcon, ClientAccountNav, ConfirmActionModal, PublicNavbar },
  data() {
    return {
      bookings: [],
      loading: false,
      error: "",
      statusGroup: this.$route.query.status_group || "all",
      page: Number(this.$route.query.page || 1),
      searchInput: this.$route.query.search || "",
      fromDate: this.$route.query.from_date || "",
      toDate: this.$route.query.to_date || "",
      bookingType: this.$route.query.booking_type || "",
      paymentStatus: this.$route.query.payment_status || "",
      lastPage: 1,
      cancellingId: "",
      cancelTarget: null,
      cancelError: "",
      statusFilters: [
        { value: "all", label: "Tất cả" },
        { value: "upcoming", label: "Sắp tới" },
        { value: "completed", label: "Hoàn tất" },
        { value: "cancelled", label: "Đã hủy" },
        { value: "refunded", label: "Đã hoàn tiền" },
      ],
    };
  },
  watch: {
    "$route.query": {
      handler(query) {
        this.statusGroup = query.status_group || "all";
        this.page = Number(query.page || 1);
        this.searchInput = query.search || "";
        this.fromDate = query.from_date || "";
        this.toDate = query.to_date || "";
        this.bookingType = query.booking_type || "";
        this.paymentStatus = query.payment_status || "";
        this.loadBookings();
      },
      immediate: true,
    },
  },
  computed: {
    cancelDescription() {
      if (!this.cancelTarget) return "";
      return `Booking #${this.cancelTarget.booking_code} tại ${this.clusterName(this.cancelTarget)} sẽ được hủy và hoàn tiền theo chính sách hiện hành.`;
    },
  },
  methods: {
    async loadBookings() {
      this.loading = true;
      this.error = "";

      try {
        const response = await bookingService.listBookings({
          status_group: this.statusGroup,
          page: this.page,
          per_page: 10,
          search: this.searchInput,
          from_date: this.fromDate,
          to_date: this.toDate,
          booking_type: this.bookingType,
          payment_status: this.paymentStatus,
        });
        this.bookings = response.data || [];
        this.lastPage = Number(response.last_page || 1);
      } catch (error) {
        this.error = error.message || "Không thể tải lịch sử booking.";
      } finally {
        this.loading = false;
      }
    },
    changeStatusGroup(statusGroup) {
      this.$router.push({
        name: "booking-history",
        query: this.filterQuery({ status_group: statusGroup, page: 1 }),
      });
    },
    applyFilters() {
      this.$router.push({
        name: "booking-history",
        query: this.filterQuery({ status_group: this.statusGroup, page: 1 }),
      });
    },
    resetFilters() {
      this.searchInput = "";
      this.fromDate = "";
      this.toDate = "";
      this.bookingType = "";
      this.paymentStatus = "";
      this.applyFilters();
    },
    filterQuery(base = {}) {
      return {
        ...base,
        ...(this.searchInput ? { search: this.searchInput } : {}),
        ...(this.fromDate ? { from_date: this.fromDate } : {}),
        ...(this.toDate ? { to_date: this.toDate } : {}),
        ...(this.bookingType ? { booking_type: this.bookingType } : {}),
        ...(this.paymentStatus ? { payment_status: this.paymentStatus } : {}),
      };
    },
    changePage(page) {
      this.$router.push({
        name: "booking-history",
        query: this.filterQuery({ status_group: this.statusGroup, page }),
      });
    },
    cancelBooking(booking) {
      this.cancelTarget = booking;
      this.cancelError = "";
    },
    closeCancelModal() {
      if (this.cancellingId) return;
      this.cancelTarget = null;
      this.cancelError = "";
    },
    async confirmCancellation(reason) {
      if (!this.cancelTarget || this.cancellingId) return;
      this.cancellingId = this.cancelTarget.id;
      this.cancelError = "";

      try {
        await bookingService.cancelBooking(this.cancelTarget.id, reason);
        this.cancelTarget = null;
        await this.loadBookings();
      } catch (requestError) {
        this.cancelError = requestError.message || "Không thể hủy booking này.";
      } finally {
        this.cancellingId = "";
      }
    },
    clusterName(booking) {
      return booking.venue_cluster?.name
        || booking.venue_court?.venue_cluster?.name
        || "Cụm sân";
    },
    courtText(booking) {
      if (booking.items?.length) {
        const names = [...new Set(booking.items.map(item => item.venue_court?.name).filter(Boolean))];
        if (names.length) return names.length > 2 ? `${names.slice(0, 2).join(', ')} và ${names.length - 2} sân khác` : names.join(', ');
      }
      const court = booking.venue_court?.name || "Sân";
      const type = booking.venue_court?.court_type?.name;
      return type ? `${court} (${type})` : court;
    },
    venueId(booking) {
      return booking.venue_cluster?.id
        || booking.venue_court?.venue_cluster?.id
        || booking.venue_cluster_id
        || null;
    },
    rebookLocation(booking) {
      const query = { venue_cluster_id: this.venueId(booking) };
      const courtTypeId = booking.venue_court?.court_type?.id;
      if (courtTypeId) query.court_type_id = courtTypeId;
      return { name: "booking-create", query };
    },
    statusLabel(status) {
      return {
        pending_approval: "Chờ duyệt",
        pending_payment: "Chờ thanh toán",
        confirmed: "Đã xác nhận",
        checked_in: "Đã check-in",
        completed: "Hoàn tất",
        cancelled: "Đã hủy",
        expired: "Hết hạn",
        rejected: "Bị từ chối",
      }[status] || status;
    },
    paymentStatusLabel(status) {
      return {
        pending: "Chờ thanh toán",
        paid: "Đã thanh toán",
        failed: "Thất bại",
        refunded: "Đã hoàn tiền",
        not_required: "Thanh toán tại sân",
      }[status] || status || "Chưa có";
    },
    formatDate(value) {
      if (!value) return "-";
      const dateOnly = String(value).includes("T") ? String(value).split("T")[0] : String(value);
      const [year, month, day] = dateOnly.split("-");
      return day && month && year ? `${day}/${month}/${year}` : value;
    },
    formatTime(value) {
      return value ? String(value).slice(0, 5) : "--:--";
    },
    formatCurrency(value) {
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
        maximumFractionDigits: 0,
      }).format(Number(value || 0));
    },
  },
};
</script>

<style scoped>
.booking-history-page {
  min-height: 100vh;
  background: #f8fafc;
}

.booking-flags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px;
}

.booking-flags span {
  padding: 5px 9px;
  border-radius: 999px;
  background: #e8f3ff;
  color: #2563a6;
  font-size: 12px;
}

.history-main {
  max-width: 1120px;
  margin: 0 auto;
  padding: 104px 24px 56px;
}

.history-header {
  display: flex;
  justify-content: space-between;
  gap: 24px;
  align-items: flex-end;
  margin-bottom: 24px;
}

.eyebrow {
  margin: 0 0 8px;
  font-size: 12px;
  font-weight: 800;
  color: #059669;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.history-header h1 {
  margin: 0;
  font-size: 32px;
  font-weight: 900;
  color: #0f172a;
}

.history-header p:not(.eyebrow) {
  margin: 8px 0 0;
  color: #64748b;
}

.primary-action,
.ghost-action,
.danger-action,
.filters button,
.pagination button {
  border-radius: 8px;
  font-weight: 800;
  transition: .16s ease;
}

.primary-action {
  display: inline-flex;
  padding: 12px 18px;
  background: #059669;
  color: #fff;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 16px;
}

.history-filters {
  display: grid;
  grid-template-columns: minmax(190px, 1.5fr) repeat(4, minmax(130px, 1fr)) auto auto;
  gap: 12px;
  align-items: end;
  margin: 18px 0;
  padding: 16px;
  border: 1px solid #d9eee1;
  border-radius: 12px;
  background: #fff;
}

.history-filters label { display: grid; gap: 6px; }
.history-filters label span { color: #64748b; font-size: 12px; font-weight: 600; }
.history-filters input, .history-filters select { min-width: 0; height: 40px; padding: 0 10px; border: 1px solid #bce8ca; border-radius: 8px; background: #fff; color: #163225; font: inherit; }
.history-filters button { white-space: nowrap; }

@media (max-width: 1050px) {
  .history-filters { grid-template-columns: repeat(3, minmax(150px, 1fr)); }
}

@media (max-width: 620px) {
  .history-filters { grid-template-columns: 1fr; }
}

.filters button {
  padding: 10px 14px;
  border: 1px solid #dbe4ef;
  background: #fff;
  color: #475569;
}

.filters button.active {
  border-color: #059669;
  background: #ecfdf5;
  color: #047857;
}

.history-panel {
  border: 1px solid #e2e8f0;
  background: #fff;
  border-radius: 12px;
  padding: 18px;
}

.state {
  display: grid;
  place-items: center;
  gap: 10px;
  min-height: 220px;
  color: #64748b;
  text-align: center;
}

.state.error {
  color: #b91c1c;
  background: #fef2f2;
  border-radius: 8px;
}

.state.empty strong {
  color: #0f172a;
}

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #d1fae5;
  border-top-color: #059669;
  border-radius: 50%;
  animation: spin .8s linear infinite;
}

.booking-list {
  display: grid;
  gap: 14px;
}

.booking-card {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 18px;
  background: #fff;
}

.booking-topline,
.booking-actions,
.pagination {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
}

.code {
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.booking-card h2 {
  margin: 4px 0 0;
  font-size: 18px;
  font-weight: 900;
  color: #0f172a;
}

.status-badge {
  padding: 6px 10px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.status-badge.confirmed,
.status-badge.completed,
.status-badge.checked_in {
  background: #dcfce7;
  color: #166534;
}

.status-badge.pending_payment,
.status-badge.pending_approval {
  background: #fef3c7;
  color: #92400e;
}

.status-badge.cancelled,
.status-badge.expired,
.status-badge.rejected {
  background: #fee2e2;
  color: #991b1b;
}

.booking-meta {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin: 18px 0;
  padding: 16px;
  border-radius: 8px;
  background: #f8fafc;
}

.booking-meta span {
  display: block;
  margin-bottom: 4px;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

.booking-meta strong {
  color: #0f172a;
  font-size: 14px;
}

.ghost-action,
.danger-action,
.pagination button {
  padding: 9px 12px;
  border: 1px solid #dbe4ef;
  background: #fff;
  color: #0f172a;
}

.danger-action {
  border-color: #fecaca;
  color: #b91c1c;
}

.danger-action:disabled,
.pagination button:disabled {
  opacity: .55;
  cursor: not-allowed;
}

.pagination {
  margin-top: 18px;
  justify-content: center;
  color: #64748b;
  font-weight: 800;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 820px) {
  .history-header,
  .booking-topline,
  .booking-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .booking-meta {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 520px) {
  .history-main { padding-inline: 16px; }
  .booking-meta { grid-template-columns: 1fr; }
}
</style>

<style>
/* Dark Mode Support for Booking History */
.dark .booking-history-page {
  background-color: #09090b !important;
  color: #f8fafc !important;
  min-height: 100vh;
}

.dark .booking-history-page .history-header h1 {
  color: #f8fafc !important;
}

.dark .booking-history-page .history-header p {
  color: #a1a1aa !important;
}

.dark .booking-history-page .history-header .eyebrow {
  color: #10b981 !important;
}

.dark .filters button {
  background: #18181b !important;
  border-color: #27272a !important;
  color: #a1a1aa !important;
}

.dark .filters button.active {
  border-color: #059669 !important;
  background: #064e3b !important;
  color: #34d399 !important;
}

.dark .history-panel {
  border-color: #27272a !important;
  background: #18181b !important;
}

.dark .state {
  color: #a1a1aa !important;
}

.dark .state.empty strong {
  color: #f8fafc !important;
}

.dark .booking-card {
  border-color: #27272a !important;
  background: #09090b !important;
}

.dark .booking-card h2 {
  color: #f8fafc !important;
}

.dark .status-badge {
  background: #27272a !important;
  color: #a1a1aa !important;
}

.dark .status-badge.confirmed,
.dark .status-badge.completed,
.dark .status-badge.checked_in {
  background: #064e3b !important;
  color: #34d399 !important;
}

.dark .status-badge.pending_payment,
.dark .status-badge.pending_approval {
  background: #78350f !important;
  color: #fcd34d !important;
}

.dark .status-badge.cancelled,
.dark .status-badge.expired,
.dark .status-badge.rejected {
  background: #7f1d1d !important;
  color: #fca5a5 !important;
}

.dark .booking-meta {
  background: #18181b !important;
  border: 1px solid #27272a !important;
}

.dark .booking-meta span {
  color: #a1a1aa !important;
}

.dark .booking-meta strong {
  color: #e2e8f0 !important;
}

.dark .ghost-action,
.dark .pagination button {
  border-color: #27272a !important;
  background: #18181b !important;
  color: #f8fafc !important;
}

.dark .ghost-action:hover,
.dark .pagination button:hover {
  background: #27272a !important;
}

.dark .danger-action {
  border-color: #7f1d1d !important;
  color: #f87171 !important;
  background: #18181b !important;
}

.dark .danger-action:hover {
  background: #7f1d1d !important;
  color: #fecaca !important;
}
</style>

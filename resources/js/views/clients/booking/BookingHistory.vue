<template>
  <div class="booking-history-page sg-client-page">
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
        query: { status_group: statusGroup, page: 1 },
      });
    },
    changePage(page) {
      this.$router.push({
        name: "booking-history",
        query: { status_group: this.statusGroup, page },
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

<style scoped src="../../../../css/client-booking-history.css"></style>

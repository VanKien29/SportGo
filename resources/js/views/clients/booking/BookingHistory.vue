<template>
  <div class="booking-history-page sg-client-page sg3-history-page">
    <PublicNavbar />

    <main class="history-main sg3-container sg3-profile-main">
      <section class="sg3-page-head">
        <div>
          <nav class="sg3-breadcrumbs" aria-label="Điều hướng tài khoản">
            <router-link to="/profile">Tài khoản</router-link>
            <span>/</span>
            <strong>Lịch đặt</strong>
          </nav>
          <p class="sg3-kicker">Lịch đặt của tôi</p>
          <h1>Lịch sử đặt sân</h1>
          <p>Theo dõi các đơn sân sắp tới, đã hoàn tất, đã hủy và đã hoàn tiền.</p>
        </div>
        <router-link :to="{ name: 'booking-create' }" class="sg3-button sg3-button--primary">
          <AppIcon name="plus" aria-hidden="true" />
          Đặt sân mới
        </router-link>
      </section>

      <section class="booking-overview-strip" aria-label="Tổng quan lịch đặt">
        <article><span class="booking-overview-icon"><AppIcon name="calendar" :size="16" /></span><div><small>Đang hiển thị</small><strong>{{ bookings.length }} booking</strong></div></article>
        <article><span class="booking-overview-icon booking-overview-icon--blue"><AppIcon name="clock" :size="16" /></span><div><small>Sắp tới</small><strong>{{ upcomingCount }} buổi</strong></div></article>
        <article><span class="booking-overview-icon booking-overview-icon--amber"><AppIcon name="rotateCcw" :size="16" /></span><div><small>Đang hoàn / hủy</small><strong>{{ refundCount }} yêu cầu</strong></div></article>
        <router-link class="booking-overview-help" to="/refunds"><AppIcon name="arrowRight" :size="15" /> Mở trung tâm hoàn/hủy</router-link>
      </section>

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

      <section class="history-panel">
        <div v-if="loading" class="state sg3-empty sg-client-state">
          <span class="spinner" aria-hidden="true"></span>
          Đang tải lịch sử booking...
        </div>

        <div v-else-if="error" class="state error sg3-error sg-client-state">
          <AppIcon name="alert" aria-hidden="true" />
          <strong>Không tải được lịch sử đặt sân</strong>
          <span>{{ error }}</span>
          <button type="button" class="sg-client-button" @click="loadBookings">
            <AppIcon name="refresh" aria-hidden="true" />
            Thử lại
          </button>
        </div>

        <div v-else-if="bookings.length === 0" class="state empty sg3-empty sg-client-state">
          <AppIcon name="calendar" aria-hidden="true" />
          <strong>Chưa có booking phù hợp.</strong>
          <span>Thử đổi bộ lọc hoặc đặt sân mới để bắt đầu.</span>
          <router-link :to="{ name: 'booking-create' }" class="sg-client-button sg-client-button--primary">
            <AppIcon name="plus" aria-hidden="true" />
            Đặt sân mới
          </router-link>
        </div>

        <div v-else class="booking-list">
          <article v-for="booking in bookings" :key="booking.id" class="booking-card sg3-card">
            <header class="booking-card__header">
              <div class="booking-card__date"><AppIcon name="calendar" :size="18" /><span><small>Ngày chơi</small><strong>{{ formatDate(booking.booking_date) }}</strong></span></div>
              <div class="booking-card__identity"><span class="code">#{{ booking.booking_code }}</span><h2>{{ clusterName(booking) }}</h2><small>{{ courtText(booking) }}<template v-if="booking.items?.length > 1"> · {{ booking.items.length }} khung giờ/sân</template></small></div>
              <div class="booking-card__status"><span class="status-badge" :class="booking.status">{{ statusLabel(booking.status) }}</span><span v-if="booking.matchmaking" class="booking-matchmaking-chip"><AppIcon name="users" :size="14" /> Giao lưu {{ booking.matchmaking.label }}</span></div>
            </header>

            <div class="booking-card__body">
              <div class="booking-time-rail" aria-label="Khung giờ chơi"><div><small>Bắt đầu</small><strong>{{ formatTime(booking.start_time) }}</strong></div><span class="booking-time-rail__line"><i></i></span><div><small>Kết thúc</small><strong>{{ formatTime(booking.end_time) }}</strong></div></div>
              <div class="booking-card__details"><div class="booking-detail-row"><span class="booking-detail-row__icon"><AppIcon name="layers" :size="15" /></span><span><small>Sân đã chọn</small><strong>{{ courtText(booking) }}</strong></span></div><div class="booking-detail-row"><span class="booking-detail-row__icon booking-detail-row__icon--blue"><AppIcon name="creditCard" :size="15" /></span><span><small>Thanh toán</small><strong>{{ paymentStatusLabel(booking.payment_status) }}</strong></span></div><div class="booking-detail-row"><span class="booking-detail-row__icon booking-detail-row__icon--muted"><AppIcon name="clock" :size="15" /></span><span><small>Thời lượng</small><strong>{{ durationLabel(booking) }}</strong></span></div></div>
            </div>

            <div v-if="booking.matchmaking" class="booking-matchmaking-panel">
              <div><span class="booking-matchmaking-panel__icon"><AppIcon name="users" :size="17" /></span><div><strong>Buổi này đang tuyển giao lưu</strong><small>Đã ghép {{ booking.matchmaking.approved_players }} người · còn cần {{ booking.matchmaking.needed_players }} người</small></div></div>
              <router-link :to="`/matchmaking-posts/${booking.matchmaking.id}/manage`" class="ghost-action"><AppIcon name="arrowRight" :size="15" /> Xem danh sách</router-link>
            </div>

            <div v-if="booking.has_court_change || booking.has_partial_cancellation" class="booking-flags">
              <span v-if="booking.has_court_change">Đã đổi sân</span>
              <span v-if="booking.has_partial_cancellation">Có khung bị hủy/gián đoạn</span>
            </div>

            <footer class="booking-actions">
              <button type="button" class="ghost-action primary-detail" @click="selectedBooking = booking">
                <AppIcon name="eye" aria-hidden="true" />
                Xem nhanh
              </button>
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
              <button v-if="canRequestRefund(booking)" type="button" class="ghost-action refund-action" @click="openRefundRequest(booking)">
                <AppIcon name="rotateCcw" aria-hidden="true" />
                Yêu cầu hoàn tiền
              </button>
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
            </footer>
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

    <RefundRequestModal
      :is-open="Boolean(refundRequestBooking)"
      :booking="refundRequestBooking"
      :loading="refundRequestLoading"
      :error="refundRequestError"
      @close="closeRefundRequest"
      @submit="submitRefundRequest"
    />

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

    <Teleport to="body">
      <div v-if="selectedBooking" class="booking-quick-view" role="dialog" aria-modal="true" aria-labelledby="booking-quick-view-title" @click.self="selectedBooking = null">
        <section class="booking-quick-view__panel">
          <header class="booking-quick-view__header">
            <div>
              <span class="code">#{{ selectedBooking.booking_code }}</span>
              <h2 id="booking-quick-view-title">{{ clusterName(selectedBooking) }}</h2>
            </div>
            <button type="button" class="booking-quick-view__close" aria-label="Đóng xem nhanh" @click="selectedBooking = null">
              <AppIcon name="x" aria-hidden="true" />
            </button>
          </header>

          <div class="booking-quick-view__status">
            <span :class="['status-badge', selectedBooking.status]">{{ statusLabel(selectedBooking.status) }}</span>
            <span>{{ paymentStatusLabel(selectedBooking.payment_status) }}</span>
          </div>

          <dl class="booking-quick-view__grid">
            <div><dt>Sân</dt><dd>{{ courtText(selectedBooking) }}</dd></div>
            <div><dt>Ngày chơi</dt><dd>{{ formatDate(selectedBooking.booking_date) }}</dd></div>
            <div><dt>Khung giờ</dt><dd>{{ formatTime(selectedBooking.start_time) }} - {{ formatTime(selectedBooking.end_time) }}</dd></div>
            <div><dt>Thành tiền</dt><dd>{{ formatCurrency(selectedBooking.total_price) }}</dd></div>
          </dl>

          <div v-if="selectedBooking.has_court_change || selectedBooking.has_partial_cancellation" class="booking-flags">
            <span v-if="selectedBooking.has_court_change">Đã đổi sân</span>
            <span v-if="selectedBooking.has_partial_cancellation">Có khung bị hủy/gián đoạn</span>
          </div>

          <footer class="booking-quick-view__actions">
            <button type="button" class="sg-client-button" @click="selectedBooking = null">Đóng</button>
            <button v-if="selectedBooking.can_cancel" type="button" class="danger-action" @click="cancelFromQuickView">
              <AppIcon name="circleX" aria-hidden="true" /> Hủy booking
            </button>
            <router-link :to="{ name: 'booking-detail', params: { id: selectedBooking.id } }" class="sg-client-button sg-client-button--primary" @click="selectedBooking = null">
              Xem chi tiết đầy đủ
            </router-link>
          </footer>
        </section>
      </div>
    </Teleport>
  </div>
</template>

<script>
import AppIcon from "../../../components/AppIcon.vue";
import ConfirmActionModal from "../../../components/ConfirmActionModal.vue";
import PublicNavbar from "../../../components/PublicNavbar.vue";
import RefundRequestModal from "../../../components/RefundRequestModal.vue";
import { bookingService } from "../../../services/bookingService.js";

export default {
  name: "BookingHistory",
  components: { AppIcon, ConfirmActionModal, PublicNavbar, RefundRequestModal },
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
      selectedBooking: null,
      refundRequestBooking: null,
      refundRequestLoading: false,
      refundRequestError: "",
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
    upcomingCount() {
      return this.bookings.filter((booking) => ['pending_approval', 'pending_payment', 'confirmed', 'checked_in'].includes(booking.status)).length;
    },
    refundCount() {
      return this.bookings.filter((booking) => booking.refunds?.length || ['cancelled', 'expired', 'rejected'].includes(booking.status)).length;
    },
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
    cancelFromQuickView() {
      const booking = this.selectedBooking;
      this.selectedBooking = null;
      if (booking) this.cancelBooking(booking);
    },
    canRequestRefund(booking) {
      if (!booking || !['cancelled', 'rejected'].includes(booking.status)) return false;
      if (Number(booking.paid_amount || 0) <= 0) return false;
      const blockingStatuses = ['pending_owner_confirmation', 'owner_confirmed', 'admin_processing', 'processing', 'completed', 'completed_cash'];
      return !(booking.refunds || []).some((refund) => blockingStatuses.includes(refund.status));
    },
    openRefundRequest(booking) {
      this.refundRequestBooking = booking;
      this.refundRequestError = "";
    },
    closeRefundRequest() {
      if (this.refundRequestLoading) return;
      this.refundRequestBooking = null;
      this.refundRequestError = "";
    },
    async submitRefundRequest(payload) {
      if (this.refundRequestLoading) return;
      this.refundRequestLoading = true;
      this.refundRequestError = "";
      try {
        await bookingService.requestRefund(payload);
        this.refundRequestBooking = null;
        await this.loadBookings();
      } catch (error) {
        this.refundRequestError = error.message || "Không thể gửi yêu cầu hoàn tiền.";
      } finally {
        this.refundRequestLoading = false;
      }
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
    durationLabel(booking) {
      const toMinutes = (value) => {
        const [hour, minute] = String(value || "").slice(0, 5).split(":").map(Number);
        return Number.isFinite(hour) && Number.isFinite(minute) ? (hour * 60) + minute : null;
      };
      const start = toMinutes(booking.start_time);
      const end = toMinutes(booking.end_time);
      if (start === null || end === null || end <= start) return "Theo khung giờ";
      return `${end - start} phút`;
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
  background: var(--sg3-soft);
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
  padding-bottom: 64px;
}

.ghost-action,
.danger-action,
.filters button,
.pagination button {
  border-radius: 8px;
  font-weight: 400;
  transition: .16s ease;
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

.booking-quick-view {
  position: fixed;
  z-index: 1400;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 30, 22, .48);
}

.booking-quick-view__panel {
  width: min(620px, 100%);
  max-height: min(720px, calc(100vh - 40px));
  overflow: auto;
  padding: 24px;
  border: 1px solid #bcdcc7;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 24px 70px rgba(15, 30, 22, .2);
}

.booking-quick-view__header,
.booking-quick-view__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
}

.booking-quick-view__header { padding-bottom: 18px; border-bottom: 1px solid #e0ece3; }
.booking-quick-view__header h2 { margin: 5px 0 0; color: #14261d; font-size: 22px; }
.booking-quick-view__close { display: grid; width: 38px; height: 38px; place-items: center; border: 1px solid #bedbc7; border-radius: 8px; background: #fff; color: #315044; cursor: pointer; }
.booking-quick-view__status { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 18px 0; color: #66756d; font-size: 13px; }
.booking-quick-view__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin: 0; }
.booking-quick-view__grid div { min-width: 0; padding: 14px; border: 1px solid #e0ece3; border-radius: 9px; background: #f8fbf9; }
.booking-quick-view__grid dt { color: #718078; font-size: 12px; }
.booking-quick-view__grid dd { margin: 6px 0 0; overflow-wrap: anywhere; color: #14261d; font-weight: 800; }
.booking-quick-view__actions { justify-content: flex-end; margin-top: 20px; padding-top: 18px; border-top: 1px solid #e0ece3; }

@media (max-width: 620px) {
  .booking-quick-view__panel { padding: 18px; }
  .booking-quick-view__grid { grid-template-columns: 1fr; }
  .booking-quick-view__actions { align-items: stretch; flex-direction: column-reverse; }
  .booking-quick-view__actions > * { width: 100%; }
}

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
  border: 0;
  background: transparent;
  border-radius: 0;
  padding: 0;
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
  border: 1px solid var(--sg3-line);
  border-radius: var(--sg3-radius);
  padding: 0;
  overflow: hidden;
  background: var(--sg3-panel);
  box-shadow: var(--sg3-shadow);
}

.booking-actions,
.pagination {
  display: flex;
  gap: 12px;
  align-items: center;
}

.booking-actions {
  justify-content: flex-start;
  flex-wrap: wrap;
  min-height: 72px;
  padding: 16px 20px 20px;
  border-top: 1px solid var(--sg3-line);
  background: var(--sg3-panel);
}

.booking-card__header {
  display: grid;
  grid-template-columns: 150px minmax(0, 1fr) auto;
  gap: 18px;
  align-items: center;
  padding: 18px 20px;
  border-bottom: 1px solid #e5edf0;
  background: #fbfefc;
}

.booking-card__date,
.booking-detail-row,
.booking-card__status {
  display: flex;
  align-items: center;
  gap: 9px;
}

.booking-card__date {
  color: #087642;
}

.booking-card__date > span,
.booking-card__identity,
.booking-card__status {
  display: grid;
  gap: 4px;
}

.booking-card__date small,
.booking-card__identity > small,
.booking-detail-row small,
.booking-time-rail small {
  color: #64748b;
  font-size: 11px;
}

.booking-card__date strong {
  color: #0f172a;
  font-size: 14px;
}

.booking-card__identity h2 {
  margin: 2px 0 0;
}

.booking-card__status {
  justify-items: end;
}

.booking-card__body {
  display: grid;
  grid-template-columns: 190px minmax(0, 1fr);
  gap: 24px;
  padding: 20px;
}

.booking-time-rail {
  display: grid;
  grid-template-columns: 1fr 30px 1fr;
  align-items: center;
  gap: 8px;
  padding: 14px;
  border: 1px solid #d9eee1;
  border-radius: 10px;
  background: #f4fbf6;
}

.booking-time-rail > div {
  display: grid;
  gap: 5px;
}

.booking-time-rail > div:last-child { text-align: right; }
.booking-time-rail strong { color: #087642; font-size: 20px; letter-spacing: -.04em; }
.booking-time-rail__line { position: relative; display: block; height: 2px; background: #a9dcbc; }
.booking-time-rail__line i { position: absolute; left: 50%; top: 50%; width: 8px; height: 8px; transform: translate(-50%, -50%); border: 2px solid #0b9b55; border-radius: 50%; background: #fff; }

.booking-card__details { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 11px; align-items: center; }
.booking-detail-row { min-width: 0; }
.booking-detail-row > span:last-child { display: grid; min-width: 0; gap: 4px; }
.booking-detail-row strong { overflow: hidden; color: #0f172a; font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
.booking-detail-row__icon { display: grid; width: 32px; height: 32px; flex: 0 0 auto; place-items: center; border-radius: 9px; background: #e7f7ec; color: #087642; }
.booking-detail-row__icon--blue { background: #eaf3ff; color: #2b69a7; }
.booking-detail-row__icon--muted { background: #f1f5f3; color: #64748b; }

.code {
  color: #64748b;
  font-size: 12px;
  font-weight: 400;
}

.booking-card h2 {
  margin: 4px 0 0;
  font-size: 18px;
  font-weight: 400;
  color: #0f172a;
}

.status-badge {
  padding: 6px 10px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
  font-size: 12px;
  font-weight: 400;
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
  font-weight: 400;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 820px) {
  .booking-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .booking-card__header { grid-template-columns: 1fr auto; }
  .booking-card__date { grid-column: 1 / -1; }
  .booking-card__body { grid-template-columns: 1fr; }
  .booking-card__details { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 520px) {
  .booking-card__header { grid-template-columns: 1fr; gap: 12px; }
  .booking-card__status { justify-items: start; }
  .booking-card__body { padding: 15px; }
  .booking-card__details { grid-template-columns: 1fr; }
}
</style>

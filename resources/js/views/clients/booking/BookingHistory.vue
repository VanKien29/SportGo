<template>
  <div class="booking-history-page">
    <PublicNavbar />

    <main class="history-main">
      <section class="history-header">
        <div>
          <p class="eyebrow">Lịch sử booking</p>
          <h1>Lịch sử đặt sân</h1>
          <p>Theo dõi các đơn sân sắp tới, đã hoàn tất, đã hủy và đã hoàn tiền.</p>
        </div>
        <router-link :to="{ name: 'booking-create' }" class="primary-action">
          Đặt sân mới
        </router-link>
      </section>

      <section class="filters" aria-label="Lọc booking">
        <button
          v-for="filter in statusFilters"
          :key="filter.value"
          type="button"
          :class="{ active: statusGroup === filter.value }"
          @click="changeStatusGroup(filter.value)"
        >
          {{ filter.label }}
        </button>
      </section>

      <section class="history-panel">
        <div v-if="loading" class="state">
          <span class="spinner" aria-hidden="true"></span>
          Đang tải lịch sử booking...
        </div>

        <div v-else-if="error" class="state error">
          {{ error }}
        </div>

        <div v-else-if="bookings.length === 0" class="state empty">
          <strong>Chưa có booking phù hợp.</strong>
          <span>Thử đổi bộ lọc hoặc đặt sân mới để bắt đầu.</span>
        </div>

        <div v-else class="booking-list">
          <article v-for="booking in bookings" :key="booking.id" class="booking-card">
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
              <router-link :to="{ name: 'booking-detail', params: { id: booking.id } }" class="ghost-action">
                Xem chi tiết
              </router-link>
              <button
                v-if="booking.can_cancel"
                type="button"
                class="danger-action"
                :disabled="cancellingId === booking.id"
                @click="cancelBooking(booking)"
              >
                {{ cancellingId === booking.id ? 'Đang hủy...' : 'Hủy booking' }}
              </button>
            </div>
          </article>
        </div>

        <div v-if="lastPage > 1" class="pagination">
          <button type="button" :disabled="page <= 1" @click="changePage(page - 1)">Trước</button>
          <span>Trang {{ page }} / {{ lastPage }}</span>
          <button type="button" :disabled="page >= lastPage" @click="changePage(page + 1)">Sau</button>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
import PublicNavbar from "../../../components/PublicNavbar.vue";
import { bookingService } from "../../../services/bookingService.js";

export default {
  name: "BookingHistory",
  components: { PublicNavbar },
  data() {
    return {
      bookings: [],
      loading: false,
      error: "",
      statusGroup: this.$route.query.status_group || "all",
      page: Number(this.$route.query.page || 1),
      lastPage: 1,
      cancellingId: "",
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
    async cancelBooking(booking) {
      const reason = window.prompt("Nhập lý do hủy booking:", "Khách hàng yêu cầu hủy");
      if (reason === null) return;

      this.cancellingId = booking.id;
      this.error = "";

      try {
        await bookingService.cancelBooking(booking.id, reason.trim());
        await this.loadBookings();
      } catch (error) {
        this.error = error.message || "Không thể hủy booking này.";
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

<template>
  <div class="bd-page">
    <PublicNavbar />

    <main class="bd-main">
      <!-- LOADING STATE -->
      <div v-if="loading" class="bd-state">
        <span class="bd-spinner" aria-hidden="true"></span>
        <span>Đang đồng bộ chi tiết đơn đặt sân...</span>
      </div>

      <!-- ERROR / EMPTY STATE -->
      <div v-else-if="!booking || loadError" class="bd-state bd-state--error">
        <strong>Không tìm thấy thông tin booking</strong>
        <p>{{ loadError || "Đơn đặt sân này không tồn tại hoặc đã bị gỡ khỏi hệ thống." }}</p>
        <div class="bd-actions-row">
          <button type="button" class="bd-btn bd-btn--outline" @click="loadBooking">Thử lại</button>
          <router-link :to="{ name: 'booking-history' }" class="bd-btn bd-btn--primary">
            Về lịch sử đặt sân
          </router-link>
        </div>
      </div>

      <!-- MAIN BOOKING CONTENT (FRAMELESS & NO BADGES/PILLS) -->
      <div v-else class="bd-content">
        <!-- TOP BREADCRUMBS & ACTIONS -->
        <div class="bd-top-bar">
          <div class="bd-title-group">
            <h1 class="bd-title">Chi tiết đơn đặt #{{ booking.booking_code }}</h1>
          </div>

          <div class="bd-actions-row">
            <button
              v-if="venueId"
              type="button"
              class="bd-btn bd-btn--primary"
              :disabled="startingChat"
              @click="chatWithOwner"
            >
              <span>{{ startingChat ? "Đang kết nối..." : "Chat hỗ trợ với sân" }}</span>
            </button>

            <router-link
              v-if="venueId"
              :to="{ name: 'venue-detail', params: { id: venueId } }"
              class="bd-btn bd-btn--outline"
            >
              Xem sân
            </router-link>

            <router-link
              v-if="venueId"
              :to="rebookLocation"
              class="bd-btn bd-btn--outline"
            >
              Đặt thêm lịch
            </router-link>

            <router-link
              :to="{ name: 'client-complaint-create', query: { booking_id: booking.id } }"
              class="bd-btn bd-btn--outline"
            >
              Gửi khiếu nại
            </router-link>
          </div>
        </div>

        <!-- STATUS HEADER ROW (PLAIN TEXT, NO PILL BADGES) -->
        <div class="bh-status-row">
          <div class="bh-status-info">
            <span class="bh-status-label">Trạng thái đơn:</span>
            <strong class="bh-status-value" :class="booking.status">{{ statusLabel }}</strong>
          </div>
          <p class="bh-status-desc">{{ statusDescription }}</p>
        </div>

        <!-- 2-COLUMN DETAILS GRID -->
        <div class="bd-grid">
          <!-- LEFT COLUMN: BOOKING SESSION INFO -->
          <div class="bd-col-main">
            <div class="bd-sec-head">
              <h3>Thông tin buổi chơi</h3>
            </div>

            <div class="bd-info-list">
              <div class="bd-info-row">
                <span class="bd-label">Cụm sân thể thao</span>
                <strong class="bd-val">{{ venueCluster?.name || "Cụm sân thể thao" }}</strong>
              </div>

              <div class="bd-info-row">
                <span class="bd-label">Địa chỉ cụm sân</span>
                <span class="bd-val">{{ venueAddress }}</span>
              </div>

              <div class="bd-info-row">
                <span class="bd-label">Sân & Khung giờ</span>
                <strong class="bd-val">{{ courtText }}</strong>
              </div>

              <div class="bd-info-row">
                <span class="bd-label">Ngày chơi</span>
                <strong class="bd-val">{{ formatDate(booking.booking_date) }}</strong>
              </div>

              <div class="bd-info-row">
                <span class="bd-label">Thời gian</span>
                <span class="bd-val">{{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }} ({{ booking.duration_minutes || 60 }} phút)</span>
              </div>

              <div class="bd-info-row">
                <span class="bd-label">Hình thức đặt</span>
                <span class="bd-val">{{ bookingTypeLabel }}</span>
              </div>
            </div>

            <!-- SLOTS BREAKDOWN LIST -->
            <div v-if="booking.items?.length" class="bd-slots-section">
              <h4 class="bd-sub-title">Danh sách các khung giờ</h4>
              <div class="bd-slots-list">
                <div v-for="item in booking.items" :key="item.id" class="bd-slot-row">
                  <div>
                    <strong>{{ item.venue_court?.name || "Sân" }}</strong>
                    <p>{{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }}</p>
                  </div>
                  <span class="bd-slot-status" :class="item.status">{{ itemStatusLabel(item.status) }}</span>
                </div>
              </div>
            </div>

            <!-- REASON / NOTE IF ANY -->
            <div v-if="booking.status_reason" class="bd-note-block">
              <strong>Ghi chú từ hệ thống / Chủ sân:</strong>
              <p>{{ booking.status_reason }}</p>
            </div>
          </div>

          <!-- RIGHT COLUMN: PAYMENT & ACTIONS -->
          <div class="bd-col-side">
            <!-- COUNTDOWN TIMER (IF PENDING PAYMENT) -->
            <div v-if="booking.status === 'pending_payment'" class="bd-timer-section">
              <span class="bd-timer-label">Giữ chỗ tạm thời trong:</span>
              <strong class="bd-timer-val">{{ formattedTimer }}</strong>
              <p>Hoàn tất thanh toán trước khi hết giờ để giữ lịch chơi.</p>
            </div>

            <div class="bd-sec-head">
              <h3>Thông tin chi phí</h3>
            </div>

            <div class="bd-price-list">
              <div class="bd-price-row">
                <span class="bd-label">Tổng giá trị đơn</span>
                <strong class="bd-price-val">{{ formatCurrency(booking.total_price) }}</strong>
              </div>

              <div class="bd-price-row">
                <span class="bd-label">Hình thức thanh toán</span>
                <span class="bd-val">{{ paymentOptionLabel }}</span>
              </div>

              <div v-if="booking.status === 'pending_payment'" class="bd-price-row bd-price-row--req">
                <span class="bd-label">Số tiền cần thanh toán</span>
                <strong class="bd-price-val bd-price-val--green">{{ formatCurrency(booking.required_payment_amount) }}</strong>
              </div>
            </div>

            <!-- PAYMENT HISTORY (IF ANY) -->
            <div v-if="booking.payments?.length" class="bd-pay-history">
              <h4 class="bd-sub-title">Lịch sử thanh toán</h4>
              <div v-for="payment in booking.payments" :key="payment.id" class="bd-pay-item">
                <div>
                  <strong>{{ payment.method === "wallet" ? "Ví SportGo" : payment.method === "sepay" ? "Chuyển khoản QR" : payment.method }}</strong>
                  <small>{{ paymentStatusLabel(payment.status) }}</small>
                </div>
                <strong class="bd-pay-amt">{{ formatCurrency(payment.amount) }}</strong>
              </div>
            </div>

            <!-- CANCELLATION ACTION BUTTON -->
            <div v-if="canRequestCancellation" class="bd-cancel-action">
              <button
                type="button"
                class="bd-btn bd-btn--danger bd-btn--full"
                :disabled="cancellingBooking"
                @click="openCancelBookingModal"
              >
                <span>{{ cancellingBooking ? "Đang xử lý hủy..." : "Yêu cầu hủy đơn & Hoàn tiền" }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- CANCELLATION MODAL -->
    <Teleport to="body">
      <div v-if="showCancelBookingModal" class="bd-modal-backdrop" @click.self="closeCancelBookingModal">
        <div class="bd-modal">
          <div class="bd-modal-head">
            <h3>Xác nhận hủy đơn đặt sân</h3>
            <button type="button" class="bd-modal-close" @click="closeCancelBookingModal">✕</button>
          </div>

          <div class="bd-modal-body">
            <p class="bd-modal-desc">
              Bạn đang yêu cầu hủy đơn <strong>#{{ booking?.booking_code }}</strong>.
            </p>

            <div class="bd-refund-preview">
              <span>Chính sách hoàn tiền:</span>
              <strong>{{ cancelDescription }}</strong>
            </div>

            <div class="bd-field">
              <label for="bdCancelReason">Lý do hủy</label>
              <select id="bdCancelReason" v-model="cancelReason">
                <option value="Khách hàng thay đổi kế hoạch">Khách hàng thay đổi kế hoạch</option>
                <option value="Thời tiết không thuận lợi">Thời tiết không thuận lợi</option>
                <option value="Muốn đổi giờ chơi khác">Muốn đổi giờ chơi khác</option>
                <option value="Lý do cá nhân khác">Lý do cá nhân khác</option>
              </select>
            </div>

            <div v-if="cancelBookingError" class="bd-alert bd-alert--error">
              {{ cancelBookingError }}
            </div>
          </div>

          <div class="bd-modal-foot">
            <button type="button" class="bd-btn bd-btn--outline" @click="closeCancelBookingModal">Hủy bỏ</button>
            <button
              type="button"
              class="bd-btn bd-btn--danger"
              :disabled="cancellingBooking"
              @click="cancelBooking"
            >
              <span>{{ cancellingBooking ? "Đang xử lý..." : "Xác nhận hủy đơn" }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script>
import PublicNavbar from "../../../components/PublicNavbar.vue";
import { bookingService } from "../../../services/bookingService.js";
import { chatService } from "../../../services/chat.service.js";

export default {
  name: "BookingDetail",
  components: { PublicNavbar },
  data() {
    return {
      booking: null,
      loading: true,
      loadError: "",
      cancellingBooking: false,
      cancelBookingError: "",
      startingChat: false,
      timeLeft: 0,
      timerInterval: null,
      showCancelBookingModal: false,
      cancelReason: "Khách hàng thay đổi kế hoạch",
    };
  },
  computed: {
    formattedTimer() {
      const totalSeconds = Math.max(0, Math.floor(Number(this.timeLeft) || 0));
      const minutes = Math.floor(totalSeconds / 60);
      const seconds = totalSeconds % 60;
      return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
    },
    statusDescription() {
      if (!this.booking) return "";
      const map = {
        confirmed: "Đơn của bạn đã được xác nhận. Hẹn gặp lại bạn tại sân chơi!",
        pending_payment: "Vui lòng hoàn tất thanh toán trước khi hết giờ để giữ chỗ.",
        pending_approval: "Chủ sân đang duyệt đơn đặt của bạn.",
        checked_in: "Bạn đã check-in tại sân. Chúc bạn có buổi chơi hiệu quả.",
        completed: "Buổi chơi đã hoàn tất. Cảm ơn bạn đã sử dụng SportGo.",
        rejected: "Booking không được sân chấp nhận. Vui lòng chọn khung giờ khác.",
        expired: "Đơn đã quá hạn thanh toán. Sân đã được giải phóng để người khác có thể đặt.",
        cancelled: "Đơn đặt sân này đã bị hủy bỏ.",
      };
      return map[this.booking.status] || "";
    },
    statusLabel() {
      if (!this.booking) return "";
      const map = {
        confirmed: "Đã xác nhận",
        pending_payment: "Chờ thanh toán",
        pending_approval: "Chờ duyệt sân",
        checked_in: "Đang chơi",
        completed: "Hoàn thành",
        rejected: "Bị từ chối",
        expired: "Đã hết hạn",
        cancelled: "Đã hủy",
      };
      return map[this.booking.status] || this.booking.status;
    },
    paymentOptionLabel() {
      if (!this.booking) return "";
      const map = {
        full_payment: "Thanh toán trực tuyến",
        deposit: "Đặt cọc giữ chỗ",
        wallet: "Thanh toán bằng ví SportGo",
        no_prepay: "Thanh toán tại sân",
      };
      return map[this.booking.payment_option] || this.booking.payment_option;
    },
    venueCluster() {
      return this.booking?.venue_court?.venue_cluster || this.booking?.venue_cluster || null;
    },
    venueId() {
      return this.venueCluster?.id || this.booking?.venue_cluster_id || null;
    },
    venueAddress() {
      return this.venueCluster?.full_address || this.venueCluster?.address || "Đang cập nhật";
    },
    courtText() {
      const courtName = this.booking?.venue_court?.name || "Sân thể thao";
      const courtType = this.booking?.venue_court?.court_type?.name;
      return courtType ? `${courtName} (${courtType})` : courtName;
    },
    bookingTypeLabel() {
      return this.booking?.booking_type === "recurring" ? "Đặt cố định" : "Đặt lẻ";
    },
    rebookLocation() {
      const query = { venue_cluster_id: this.venueId };
      const courtTypeId = this.booking?.venue_court?.court_type?.id;
      if (courtTypeId) query.court_type_id = courtTypeId;
      return { name: "booking-create", query };
    },
    canRequestCancellation() {
      if (!["pending_approval", "confirmed"].includes(this.booking?.status)) return false;
      const date = String(this.booking?.booking_date || "").split("T")[0];
      const time = String(this.booking?.start_time || "").slice(0, 5);
      const startsAt = new Date(`${date}T${time}:00`);
      return Number.isNaN(startsAt.getTime()) || startsAt > new Date();
    },
    cancelDescription() {
      return "Quy định hủy sân: Hoàn lại 100% số tiền vào Ví SportGo khi hủy trước giờ chơi.";
    },
  },
  async mounted() {
    await this.loadBooking();
  },
  beforeUnmount() {
    this.clearTimer();
  },
  methods: {
    async loadBooking() {
      const id = this.$route.params.id;
      this.loading = true;
      this.loadError = "";
      try {
        const res = await bookingService.getBooking(id);
        this.booking = res;
        this.timeLeft = Math.max(0, Math.floor(Number(res.time_left_seconds) || 0));

        if (this.booking.status === "pending_payment" && this.timeLeft > 0) {
          this.startTimer();
        } else {
          this.clearTimer();
        }
      } catch (err) {
        this.booking = null;
        this.loadError = err.message || "Không thể tải thông tin booking.";
      } finally {
        this.loading = false;
      }
    },
    startTimer() {
      this.clearTimer();
      this.timerInterval = setInterval(() => {
        if (this.timeLeft > 0) {
          this.timeLeft--;
        } else {
          this.clearTimer();
          if (this.booking) {
            this.booking.status = "expired";
          }
        }
      }, 1000);
    },
    clearTimer() {
      if (this.timerInterval) {
        clearInterval(this.timerInterval);
        this.timerInterval = null;
      }
    },
    openCancelBookingModal() {
      this.showCancelBookingModal = true;
      this.cancelBookingError = "";
    },
    closeCancelBookingModal() {
      this.showCancelBookingModal = false;
      this.cancelBookingError = "";
    },
    async cancelBooking() {
      this.cancellingBooking = true;
      this.cancelBookingError = "";
      try {
        await bookingService.cancelBooking(this.booking.id, this.cancelReason);
        this.closeCancelBookingModal();
        await this.loadBooking();
      } catch (err) {
        this.cancelBookingError = err.message || "Không thể thực hiện hủy booking.";
      } finally {
        this.cancellingBooking = false;
      }
    },
    itemStatusLabel(status) {
      const map = {
        active: "Đã giữ chỗ",
        pending_approval: "Chờ duyệt",
        pending_payment: "Chờ thanh toán",
        confirmed: "Đã xác nhận",
        checked_in: "Đang chơi",
        completed: "Hoàn thành",
        cancelled: "Đã hủy",
        rejected: "Bị từ chối",
        expired: "Đã hết hạn",
      };
      return map[status] || status;
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
    async chatWithOwner() {
      if (!this.venueId) return;
      this.startingChat = true;
      try {
        const ownerId = this.venueCluster?.owner_id || this.booking?.venue_cluster?.owner_id;
        const res = await chatService.startConversation({
          user_id: ownerId,
          venue_cluster_id: this.venueId,
        });
        if (res && res.id) {
          this.$router.push({
            name: "chat",
            query: { conversation_id: res.id, booking_id: this.booking.id },
          });
        }
      } catch (err) {
        console.error("Không thể mở hội thoại chat với sân", err);
      } finally {
        this.startingChat = false;
      }
    },
  },
};
</script>

<style scoped>
.bd-page {
  min-height: 100vh;
  background: #ffffff;
}

.bd-main {
  max-width: 1100px;
  margin: 0 auto;
  padding: 24px 16px 60px;
}

/* TOP BAR */
.bd-top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.bd-back-link {
  font-size: 13px;
  color: #15803d;
  text-decoration: none;
  font-weight: 500;
}

.bd-title {
  font-size: 20px;
  font-weight: 500;
  color: #0f172a;
  margin: 4px 0 0;
}

.bd-actions-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.bd-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
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

.bd-btn--primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.bd-btn--outline {
  background: #ffffff;
  color: #0f172a;
  border-color: #cbd5e1;
}

.bd-btn--danger {
  background: #dc2626;
  color: #ffffff;
  border-color: #dc2626;
}

.bd-btn--full {
  width: 100%;
}

/* STATUS ROW (PLAIN TEXT ONLY) */
.bh-status-row {
  margin-bottom: 32px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.bh-status-info {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14.5px;
}

.bh-status-label {
  color: #1e293b;
}

.bh-status-value {
  font-weight: 500;
  color: #15803d;
}

.bh-status-value.cancelled,
.bh-status-value.rejected,
.bh-status-value.expired {
  color: #dc2626;
}

.bh-status-value.pending_payment,
.bh-status-value.pending_approval {
  color: #d97706;
}

.bh-status-desc {
  font-size: 13px;
  color: #475569;
  margin: 0;
}

/* 2-COLUMN GRID (FRAMELESS) */
.bd-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 40px;
}

@media (max-width: 850px) {
  .bd-grid {
    grid-template-columns: 1fr;
  }
}

.bd-sec-head h3 {
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 16px;
}

.bd-info-list,
.bd-price-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.bd-info-row,
.bd-price-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  font-size: 13.5px;
}

.bd-label {
  color: #1e293b;
}

.bd-val {
  color: #0f172a;
}

.bd-price-val {
  font-size: 15px;
  color: #0f172a;
}

.bd-price-val--green {
  color: #15803d;
  font-weight: 500;
}

.bd-slots-section,
.bd-pay-history {
  margin-top: 24px;
}

.bd-sub-title {
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 12px;
}

.bd-slots-list,
.bd-pay-history {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.bd-slot-row,
.bd-pay-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
  padding-bottom: 8px;
  border-bottom: 1px solid #f1f5f9;
}

.bd-slot-status {
  color: #15803d;
  font-size: 12.5px;
}

.bd-slot-status.cancelled {
  color: #dc2626;
}

.bd-note-block {
  margin-top: 20px;
  font-size: 13px;
  color: #1e293b;
}

.bd-timer-section {
  margin-bottom: 24px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.bd-timer-label {
  font-size: 12.5px;
  color: #d97706;
}

.bd-timer-val {
  font-size: 22px;
  font-weight: 500;
  color: #d97706;
}

.bd-timer-section p {
  font-size: 12.5px;
  color: #64748b;
  margin: 0;
}

.bd-cancel-action {
  margin-top: 24px;
}

/* STATES & SPINNER */
.bd-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 16px;
  text-align: center;
  gap: 12px;
  color: #1e293b;
}

.bd-spinner {
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

/* MODAL */
.bd-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 16px;
}

.bd-modal {
  background: #ffffff;
  border-radius: 6px;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.bd-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px 8px;
}

.bd-modal-head h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
}

.bd-modal-close {
  background: transparent;
  border: none;
  font-size: 16px;
  color: #64748b;
  cursor: pointer;
}

.bd-modal-body {
  padding: 12px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.bd-modal-desc {
  font-size: 13px;
  color: #1e293b;
  margin: 0;
}

.bd-refund-preview {
  background: #ffffff;
  border: 1px dashed #cbd5e1;
  border-radius: 4px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}

.bd-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.bd-field label {
  font-size: 12.5px;
  font-weight: 500;
  color: #1e293b;
}

.bd-field select {
  padding: 8px 12px;
  font-size: 13.5px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #ffffff;
  color: #0f172a;
  outline: none;
}

.bd-alert {
  padding: 10px 14px;
  font-size: 13px;
  border-radius: 4px;
}

.bd-alert--error {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fecaca;
}

.bd-modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 8px 20px 20px;
  background: #ffffff;
}
</style>

<template>
  <div class="bd-container">
    <PublicNavbar />

    <main v-if="!loading" class="bd-main">
      <div v-if="booking" class="bd-content">
        <!-- HEADER & ACTIONS BAR -->
        <header class="bd-head">
          <nav class="bd-breadcrumbs">
            <router-link :to="{ name: 'booking-history' }" class="bd-back-link">
              <AppIcon name="arrowLeft" aria-hidden="true" />
              <span>Quay lại lịch sử đặt sân</span>
            </router-link>
            <span class="bd-crumb-sep">/</span>
            <strong class="bd-code">#{{ booking.booking_code }}</strong>
          </nav>

          <div class="bd-head-actions">
            <router-link
              v-if="venueId"
              :to="{ name: 'venue-detail', params: { id: venueId } }"
              class="bd-btn bd-btn--outline"
            >
              <AppIcon name="mapPin" aria-hidden="true" />
              <span>Xem sân</span>
            </router-link>

            <router-link
              v-if="venueId"
              :to="rebookLocation"
              class="bd-btn bd-btn--primary"
            >
              <AppIcon name="rotateCcw" aria-hidden="true" />
              <span>Đặt thêm lịch</span>
            </router-link>

            <router-link
              :to="{ name: 'client-complaint-create', query: { booking_id: booking.id } }"
              class="bd-btn bd-btn--outline"
            >
              <AppIcon name="messageSquare" aria-hidden="true" />
              <span>Gửi khiếu nại</span>
            </router-link>
          </div>
        </header>

        <!-- MAIN 2-COLUMN GRID -->
        <div class="bd-grid">
          <!-- LEFT COLUMN: BOOKING SESSION INFO -->
          <section class="bd-col-main">
            <!-- PLAY SESSION DETAILS CARD -->
            <article class="bd-card">
              <div class="bd-card-head">
                <div>
                  <span class="bd-card-eyebrow">THÔNG TIN BUỔI CHƠI</span>
                  <h2 class="bd-card-title">Chi tiết đơn đặt #{{ booking.booking_code }}</h2>
                </div>
                <span class="bd-badge" :class="booking.status">{{ statusLabel }}</span>
              </div>

              <div class="bd-info-grid">
                <div class="bd-info-item">
                  <span class="bd-info-label">Cụm sân</span>
                  <strong class="bd-info-val">{{ venueCluster?.name || "Đang cập nhật" }}</strong>
                </div>

                <div class="bd-info-item">
                  <span class="bd-info-label">Địa chỉ</span>
                  <span class="bd-info-val">{{ venueAddress }}</span>
                </div>

                <div class="bd-info-item">
                  <span class="bd-info-label">Sân chơi</span>
                  <strong class="bd-info-val">{{ courtText }}</strong>
                </div>

                <div class="bd-info-item">
                  <span class="bd-info-label">Ngày chơi</span>
                  <strong class="bd-info-val">{{ formatDate(booking.booking_date) }}</strong>
                </div>

                <div class="bd-info-item">
                  <span class="bd-info-label">Khung giờ</span>
                  <strong class="bd-info-val">{{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}</strong>
                </div>

                <div class="bd-info-item">
                  <span class="bd-info-label">Thời lượng</span>
                  <span class="bd-info-val">{{ booking.duration_minutes ? booking.duration_minutes + " phút" : "-" }}</span>
                </div>

                <div class="bd-info-item">
                  <span class="bd-info-label">Hình thức đặt</span>
                  <span class="bd-info-val">{{ bookingTypeLabel }}</span>
                </div>
              </div>

              <!-- SLOTS BREAKDOWN LIST (FLAT ROWS) -->
              <div v-if="booking.items?.length" class="bd-items-section">
                <h3 class="bd-sub-head">Các khung giờ trong booking</h3>
                <div class="bd-items-list">
                  <div v-for="item in booking.items" :key="item.id" class="bd-item-row">
                    <div class="bd-item-info">
                      <strong>{{ item.venue_court?.name || "Sân" }}</strong>
                      <span>{{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }}</span>
                    </div>
                    <span class="bd-badge-mini" :class="item.status">{{ itemStatusLabel(item.status) }}</span>
                  </div>
                </div>
              </div>
            </article>

            <!-- STATUS NOTE / REASON (IF REJECTED OR CANCELLED OR UPDATED) -->
            <article v-if="booking.status_reason" class="bd-card bd-card--note">
              <AppIcon name="alert" aria-hidden="true" />
              <div>
                <strong>Cập nhật từ chủ sân / hệ thống:</strong>
                <p>{{ booking.status_reason }}</p>
              </div>
            </article>
          </section>

          <!-- RIGHT COLUMN: PAYMENT & ACTIONS -->
          <aside class="bd-col-side">
            <!-- COUNTDOWN TIMER CARD (IF PENDING PAYMENT) -->
            <article v-if="booking.status === 'pending_payment'" class="bd-card bd-card--timer">
              <div class="bd-timer-body">
                <span class="bd-card-eyebrow">Giữ chỗ tạm thời</span>
                <h3 class="bd-timer-title">Thời gian thanh toán còn lại</h3>
                <p class="bd-timer-desc">Hoàn tất thanh toán trước khi hết giờ để giữ lịch đặt sân.</p>
              </div>
              <div class="bd-timer-clock">{{ formattedTimer }}</div>
            </article>

            <!-- PAYMENT SUMMARY CARD -->
            <article class="bd-card">
              <div class="bd-card-head">
                <div>
                  <span class="bd-card-eyebrow">THANH TOÁN</span>
                  <h2 class="bd-card-title">Thông tin chi phí</h2>
                </div>
              </div>

              <div class="bd-price-rows">
                <div class="bd-price-row">
                  <span>Tổng tiền sân:</span>
                  <strong class="bd-price-total">{{ formatCurrency(booking.total_price) }}</strong>
                </div>

                <div class="bd-price-row">
                  <span>Hình thức thanh toán:</span>
                  <span class="bd-price-method">{{ paymentOptionLabel }}</span>
                </div>

                <div v-if="booking.status === 'pending_payment'" class="bd-price-row bd-price-row--highlight">
                  <span>Cần thanh toán ngay:</span>
                  <strong class="bd-price-req">{{ formatCurrency(booking.required_payment_amount) }}</strong>
                </div>
              </div>

              <!-- PAYMENT HISTORY (IF ANY) -->
              <div v-if="booking.payments?.length" class="bd-payment-history">
                <h3 class="bd-sub-head">Lịch sử thanh toán</h3>
                <div v-for="payment in booking.payments" :key="payment.id" class="bd-pay-row">
                  <div>
                    <span class="bd-pay-method">
                      {{ payment.method === "wallet" ? "Ví SportGo" : payment.method === "sepay" ? "Chuyển khoản QR" : payment.method }}
                    </span>
                    <small class="bd-pay-status">{{ paymentStatusLabel(payment.status) }}</small>
                  </div>
                  <strong class="bd-pay-amount">{{ formatCurrency(payment.amount) }}</strong>
                </div>
              </div>

              <!-- SEPAY QR CODE PANEL (IF PENDING PAYMENT) -->
              <div v-if="booking.status === 'pending_payment'" class="bd-sepay-area">
                <button
                  v-if="!sepayPayment"
                  class="bd-btn bd-btn--primary bd-btn--full"
                  type="button"
                  :disabled="creatingSepay || timeLeft <= 0"
                  @click="createSepayPayment"
                >
                  <AppIcon name="qrCode" aria-hidden="true" />
                  <span>{{ creatingSepay ? "Đang tạo QR thanh toán..." : "Tạo QR thanh toán ngay" }}</span>
                </button>

                <p v-if="sepayError" class="bd-error-text">{{ sepayError }}</p>

                <button
                  v-if="!sepayPayment"
                  class="bd-btn bd-btn--danger-link bd-btn--full"
                  type="button"
                  :disabled="cancellingPayment"
                  @click="showCancelPaymentModal = true"
                >
                  <AppIcon name="circleX" aria-hidden="true" />
                  <span>{{ cancellingPayment ? "Đang hủy thanh toán..." : "Hủy thanh toán" }}</span>
                </button>

                <!-- SEPAY TRANSFER DETAILS -->
                <div v-if="sepayPayment" class="bd-sepay-details">
                  <div class="bd-qr-box">
                    <img :src="sepayPayment.qr_url" alt="QR thanh toán SePay" />
                  </div>

                  <div class="bd-transfer-rows">
                    <div class="bd-transfer-row">
                      <span>Ngân hàng:</span>
                      <strong>
                        {{ sepayPayment.payment_account?.bank_name
                            || sepayPayment.payment_account?.bank_code
                            || "SePay" }}
                      </strong>
                    </div>

                    <div class="bd-transfer-row">
                      <span>Số tài khoản:</span>
                      <strong>
                        {{ sepayPayment.payment_account?.account_number
                            || sepayPayment.payment_account?.account_number_masked }}
                      </strong>
                    </div>

                    <div class="bd-transfer-row">
                      <span>Chủ tài khoản:</span>
                      <strong>{{ sepayPayment.payment_account?.account_holder_name || "Đang cập nhật" }}</strong>
                    </div>

                    <div class="bd-transfer-row">
                      <span>Nội dung CK:</span>
                      <button
                        class="bd-copy-btn"
                        type="button"
                        @click="copyText(sepayPayment.transfer_content)"
                      >
                        <code>{{ sepayPayment.transfer_content }}</code>
                        <AppIcon name="copy" aria-hidden="true" />
                      </button>
                    </div>

                    <div class="bd-transfer-row">
                      <span>Số tiền:</span>
                      <strong class="bd-price-req">{{ formatCurrency(sepayPayment.payment?.amount) }}</strong>
                    </div>
                  </div>

                  <div class="bd-waiting-bar">
                    <span class="bd-spinner" aria-hidden="true"></span>
                    <span>Đang tự động xác nhận chuyển khoản...</span>
                  </div>

                  <button
                    class="bd-btn bd-btn--danger-link bd-btn--full"
                    type="button"
                    :disabled="cancellingPayment"
                    @click="showCancelPaymentModal = true"
                  >
                    <AppIcon name="circleX" aria-hidden="true" />
                    <span>{{ cancellingPayment ? "Đang hủy thanh toán..." : "Hủy thanh toán" }}</span>
                  </button>
                </div>
              </div>

              <!-- CANCEL BOOKING BUTTON -->
              <div v-if="canRequestCancellation" class="bd-cancel-booking-wrap">
                <button
                  type="button"
                  class="bd-btn bd-btn--danger-link bd-btn--full"
                  :disabled="cancellingBooking"
                  @click="openCancelBookingModal"
                >
                  <AppIcon name="circleX" aria-hidden="true" />
                  <span>{{ cancellingBooking ? "Đang xử lý hủy..." : "Yêu cầu hủy booking" }}</span>
                </button>
              </div>
            </article>
          </aside>
        </div>
      </div>

      <!-- EMPTY STATE -->
      <section v-else class="bd-empty-state">
        <AppIcon name="calendar" aria-hidden="true" />
        <h2>Không tải được booking</h2>
        <p>{{ loadError || "Không tìm thấy thông tin đơn đặt sân." }}</p>
        <div class="bd-empty-actions">
          <button v-if="loadError" type="button" class="bd-btn bd-btn--outline" @click="loadBooking">
            <AppIcon name="refresh" aria-hidden="true" />
            <span>Thử lại</span>
          </button>
          <router-link :to="{ name: 'booking-history' }" class="bd-btn bd-btn--outline">
            <AppIcon name="history" aria-hidden="true" />
            <span>Về lịch sử</span>
          </router-link>
          <router-link :to="{ name: 'booking-create' }" class="bd-btn bd-btn--primary">
            <AppIcon name="plus" aria-hidden="true" />
            <span>Đặt sân mới</span>
          </router-link>
        </div>
      </section>
    </main>

    <!-- LOADING STATE -->
    <main v-else class="bd-loading-state">
      <span class="bd-spinner bd-spinner--lg" aria-hidden="true"></span>
      <p>Đang tải thông tin đơn đặt sân...</p>
    </main>

    <!-- CONFIRM CANCEL PAYMENT MODAL -->
    <ConfirmActionModal
      :is-open="showCancelPaymentModal"
      title="Hủy thanh toán và booking?"
      description="Sân đang được giữ cho giao dịch này. Nếu tiếp tục, thanh toán chờ sẽ bị hủy và khung giờ được giải phóng cho người khác."
      confirm-text="Hủy thanh toán"
      :loading="cancellingPayment"
      :error="sepayError"
      @close="showCancelPaymentModal = false"
      @confirm="cancelPayment"
    />

    <!-- CONFIRM CANCEL BOOKING MODAL -->
    <ConfirmActionModal
      :is-open="showCancelBookingModal"
      title="Yêu cầu hủy booking?"
      :description="cancelDescription"
      confirm-text="Xác nhận hủy"
      require-reason
      reason-label="Lý do hủy"
      reason-placeholder="Nêu ngắn gọn lý do để sân và SportGo hỗ trợ đúng chính sách"
      initial-reason="Khách hàng thay đổi kế hoạch"
      :loading="cancellingBooking || loadingCancelPreview"
      :error="cancelBookingError"
      @close="closeCancelBookingModal"
      @confirm="cancelBooking"
    />
  </div>
</template>

<script>
import AppIcon from "../../../components/AppIcon.vue";
import ConfirmActionModal from "../../../components/ConfirmActionModal.vue";
import PublicNavbar from "../../../components/PublicNavbar.vue";
import { bookingService } from "../../../services/bookingService.js";

export default {
  name: "BookingDetail",
  components: { AppIcon, ConfirmActionModal, PublicNavbar },
  data() {
    return {
      booking: null,
      loading: true,
      loadError: "",
      creatingSepay: false,
      cancellingPayment: false,
      cancellingBooking: false,
      sepayPayment: null,
      sepayError: "",
      cancelBookingError: "",
      timeLeft: 0,
      timerInterval: null,
      paymentPollInterval: null,
      showCancelPaymentModal: false,
      showCancelBookingModal: false,
      loadingCancelPreview: false,
      cancelPreviewData: null,
    };
  },
  computed: {
    formattedTimer() {
      const totalSeconds = Math.max(0, Math.floor(Number(this.timeLeft) || 0));
      const minutes = Math.floor(totalSeconds / 60);
      const seconds = totalSeconds % 60;
      return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
    },
    statusIcon() {
      const map = {
        confirmed: "circleCheck",
        pending_payment: "clock",
        pending_approval: "clock",
        checked_in: "circleCheck",
        completed: "circleCheck",
        rejected: "circleX",
        expired: "clock",
        cancelled: "circleX",
      };
      return map[this.booking?.status] || "alert";
    },
    statusTitle() {
      if (!this.booking) return "";
      const map = {
        confirmed: "Đặt sân thành công",
        pending_payment: "Đơn chờ thanh toán",
        pending_approval: "Chờ chủ sân duyệt",
        checked_in: "Đã check-in",
        completed: "Buổi chơi đã hoàn tất",
        rejected: "Booking bị từ chối",
        expired: "Đơn đã hết hạn",
        cancelled: "Đơn đã bị hủy",
      };
      return map[this.booking.status] || "Trạng thái không xác định";
    },
    statusDescription() {
      if (!this.booking) return "";
      const map = {
        confirmed: "Đơn của bạn đã được xác nhận. Hẹn gặp lại bạn tại sân chơi!",
        pending_payment: "Vui lòng hoàn tất thanh toán trước khi hết giờ để giữ chỗ.",
        pending_approval: "Chủ sân đang kiểm tra thông tin cấu hình và duyệt đơn đặt của bạn.",
        checked_in: "Bạn đã check-in tại sân. Chúc bạn có buổi chơi hiệu quả.",
        completed: "Buổi chơi đã hoàn tất. Cảm ơn bạn đã sử dụng SportGo.",
        rejected: "Booking không được sân chấp nhận. Vui lòng chọn khung giờ khác.",
        expired: "Đơn đã quá hạn thanh toán. Sân đã được giải phóng để người khác có thể đặt.",
        cancelled: "Đơn đặt sân này đã bị hủy bỏ bởi hệ thống hoặc người dùng.",
      };
      return map[this.booking.status] || "";
    },
    statusLabel() {
      if (!this.booking) return "";
      const map = {
        confirmed: "Đã xác nhận",
        pending_payment: "Chờ thanh toán",
        pending_approval: "Chờ duyệt",
        checked_in: "Đã check-in",
        completed: "Hoàn tất",
        rejected: "Bị từ chối",
        expired: "Hết hạn",
        cancelled: "Đã hủy",
      };
      return map[this.booking.status] || this.booking.status;
    },
    paymentOptionLabel() {
      if (!this.booking) return "";
      const map = {
        full_payment: "Thanh toán hết trực tuyến",
        deposit: "Đặt cọc giữ chỗ",
        wallet: "Thanh toán bằng ví SportGo",
        no_prepay: "Thanh toán trực tiếp tại sân",
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
      const courtName = this.booking?.venue_court?.name || "Sân";
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
      if (this.loadingCancelPreview) return "Đang kiểm tra chính sách hủy và số tiền dự kiến hoàn...";
      const result = this.cancelPreviewData;
      if (!result) return "Booking sẽ được xử lý theo chính sách hiện hành.";
      const percent = result.refund_percent ?? result.refund_percentage;
      const amount = result.refund_amount;
      const parts = [];
      if (result.summary) parts.push(result.summary);
      if (percent !== undefined && percent !== null) parts.push(`Dự kiến hoàn ${percent}% số tiền đã thanh toán.`);
      if (amount !== undefined && amount !== null) parts.push(`Số tiền dự kiến hoàn: ${this.formatCurrency(amount)}.`);
      return parts.join(" ") || "Booking sẽ được xử lý theo chính sách hiện hành.";
    },
  },
  async mounted() {
    await this.loadBooking();
  },
  beforeUnmount() {
    this.clearTimer();
    this.clearPaymentPolling();
  },
  methods: {
    async loadBooking() {
      const id = this.$route.params.id;
      this.loading = true;
      this.loadError = "";
      try {
        const res = await bookingService.getBooking(id);
        this.booking = res;
        this.timeLeft = this.normalizeTimeLeft(res.time_left_seconds);

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
    normalizeTimeLeft(value) {
      return Math.max(0, Math.floor(Number(value) || 0));
    },
    clearTimer() {
      if (this.timerInterval) {
        clearInterval(this.timerInterval);
        this.timerInterval = null;
      }
    },
    async createSepayPayment() {
      if (!this.booking || this.creatingSepay || this.timeLeft <= 0) return;
      this.creatingSepay = true;
      this.sepayError = "";

      try {
        const res = await bookingService.createSepayPayment(this.booking.id);
        this.sepayPayment = res;
        this.startPaymentPolling();
      } catch (err) {
        this.sepayError = err.message || "Không thể tạo thông tin thanh toán SePay.";
      } finally {
        this.creatingSepay = false;
      }
    },
    async refreshBookingStatus() {
      if (!this.booking) return;

      try {
        const res = await bookingService.getBooking(this.booking.id);
        this.booking = res;
        this.timeLeft = this.normalizeTimeLeft(res.time_left_seconds);

        if (this.booking.status !== "pending_payment") {
          this.clearPaymentPolling();
          this.clearTimer();
          this.sepayPayment = null;
        }
      } catch (err) {
        this.sepayError = err.message || "Không thể kiểm tra trạng thái thanh toán.";
      }
    },
    startPaymentPolling() {
      this.clearPaymentPolling();
      this.paymentPollInterval = setInterval(() => {
        this.refreshBookingStatus();
      }, 5000);
    },
    async cancelPayment() {
      if (!this.booking || this.cancellingPayment) return;
      this.cancellingPayment = true;
      this.sepayError = "";

      try {
        const res = await bookingService.cancelPayment(this.booking.id);
        this.booking = res.booking || this.booking;
        this.timeLeft = 0;
        this.sepayPayment = null;
        this.showCancelPaymentModal = false;
        this.clearTimer();
        this.clearPaymentPolling();
      } catch (err) {
        this.sepayError = err.message || "Không thể hủy thanh toán.";
      } finally {
        this.cancellingPayment = false;
      }
    },
    closeCancelBookingModal() {
      if (this.cancellingBooking) return;
      this.showCancelBookingModal = false;
      this.cancelBookingError = "";
    },
    async openCancelBookingModal() {
      if (!this.booking || this.loadingCancelPreview || this.cancellingBooking) return;
      this.cancelBookingError = "";
      this.cancelPreviewData = null;
      this.loadingCancelPreview = true;
      this.showCancelBookingModal = true;
      try {
        this.cancelPreviewData = await bookingService.previewCancellation(this.booking.id);
      } catch (error) {
        this.cancelBookingError = error.message || "Không thể kiểm tra chính sách hủy booking.";
      } finally {
        this.loadingCancelPreview = false;
      }
    },
    async cancelBooking(reason) {
      if (!this.booking || this.cancellingBooking) return;
      this.cancellingBooking = true;
      this.cancelBookingError = "";
      try {
        await bookingService.cancelBooking(this.booking.id, reason);
        this.showCancelBookingModal = false;
        await this.loadBooking();
      } catch (error) {
        this.cancelBookingError = error.message || "Không thể hủy booking này.";
      } finally {
        this.cancellingBooking = false;
      }
    },
    clearPaymentPolling() {
      if (this.paymentPollInterval) {
        clearInterval(this.paymentPollInterval);
        this.paymentPollInterval = null;
      }
    },
    async copyText(text) {
      if (!text) return;
      try {
        await navigator.clipboard.writeText(text);
      } catch {
        this.sepayError = "Không thể sao chép nội dung chuyển khoản.";
      }
    },
    formatDate(dateStr) {
      if (!dateStr) return "";
      const raw = String(dateStr);
      const dateOnly = raw.includes("T") ? raw.split("T")[0] : raw;
      const [year, month, day] = dateOnly.split("-");
      return `${day}/${month}/${year}`;
    },
    formatTime(value) {
      return value ? String(value).slice(0, 5) : "--:--";
    },
    formatCurrency(val) {
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
        maximumFractionDigits: 0,
      }).format(val || 0);
    },
    itemStatusLabel(status) {
      return (
        {
          active: "Đang giữ",
          moved: "Đã đổi sân",
          cancelled: "Đã hủy",
          interrupted: "Bị gián đoạn",
        }[status] || status || "Đang xử lý"
      );
    },
    paymentStatusLabel(status) {
      return (
        {
          pending: "Chờ thanh toán",
          paid: "Đã thanh toán",
          failed: "Thất bại",
          refunded: "Đã hoàn tiền",
        }[status] || status || "Đang xử lý"
      );
    },
  },
};
</script>

<style scoped>
.bd-container {
  min-height: 100vh;
  background: #f8fafc;
}

.bd-main {
  max-width: 1100px;
  margin: 0 auto;
  padding: 24px 16px 60px;
}

/* HEADER & BREADCRUMBS */
.bd-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  gap: 16px;
  flex-wrap: wrap;
}

.bd-breadcrumbs {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
}

.bd-back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #475569;
  text-decoration: none;
  font-weight: 500;
}

.bd-back-link:hover {
  color: #0f172a;
}

.bd-crumb-sep {
  color: #94a3b8;
}

.bd-code {
  color: #0f172a;
  font-weight: 500;
}

.bd-head-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

/* BUTTON UTILITIES - ELEGANT & CLEAN */
.bd-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  border-radius: 6px;
  cursor: pointer;
  text-decoration: none;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #1e293b;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.bd-btn--primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.bd-btn--outline {
  background: #ffffff;
  color: #1e293b;
  border-color: #e2e8f0;
}

.bd-btn--danger-link {
  background: transparent;
  color: #dc2626;
  border: none;
  padding: 8px 0;
  box-shadow: none;
}

.bd-btn--full {
  width: 100%;
}

.bd-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* STATUS BANNER CARD - HARMONIOUS & ELEGANT */
.bd-status-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 24px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.bd-status-icon {
  font-size: 24px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #475569;
}

.bd-status-icon.confirmed,
.bd-status-icon.completed,
.bd-status-icon.checked_in {
  background: #f0fdf4;
  color: #15803d;
}

.bd-status-icon.pending_approval,
.bd-status-icon.pending_payment,
.bd-status-icon.expired {
  background: #fffbeb;
  color: #d97706;
}

.bd-status-icon.rejected,
.bd-status-icon.cancelled {
  background: #fef2f2;
  color: #dc2626;
}

.bd-status-body {
  flex: 1;
}

.bd-status-eyebrow {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  font-weight: 500;
  display: block;
}

.bd-status-title {
  font-size: 17px;
  font-weight: 500;
  color: #0f172a;
  margin: 2px 0;
}

.bd-status-desc {
  font-size: 13px;
  color: #475569;
  margin: 0;
}

.bd-status-badge {
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 500;
  border-radius: 20px;
  background: #f1f5f9;
  color: #475569;
  white-space: nowrap;
}

.bd-status-badge.confirmed,
.bd-status-badge.completed,
.bd-status-badge.checked_in {
  background: #dcfce7;
  color: #15803d;
}

.bd-status-badge.pending_approval,
.bd-status-badge.pending_payment {
  background: #fef3c7;
  color: #b45309;
}

.bd-status-badge.rejected,
.bd-status-badge.cancelled {
  background: #fee2e2;
  color: #dc2626;
}

/* MAIN 2-COLUMN GRID */
.bd-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 24px;
}

@media (max-width: 900px) {
  .bd-grid {
    grid-template-columns: 1fr;
  }
}

.bd-col-main,
.bd-col-side {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* CARDS - SOFT & CLEAN (NO HARD SQUARE BORDERS) */
.bd-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.bd-card--note {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  background: #ffffff;
  border-color: #e2e8f0;
  color: #334155;
}

.bd-card--note p {
  margin: 4px 0 0;
  font-size: 13px;
  color: #475569;
}

.bd-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 20px;
  gap: 12px;
}

.bd-card-eyebrow {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #15803d;
  font-weight: 500;
  display: block;
}

.bd-card-title {
  font-size: 16.5px;
  font-weight: 500;
  color: #0f172a;
  margin: 4px 0 0;
}

.bd-badge {
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 500;
  border-radius: 20px;
  background: #f1f5f9;
  color: #475569;
}

.bd-badge.confirmed,
.bd-badge.completed {
  background: #dcfce7;
  color: #15803d;
}

.bd-badge.pending_approval,
.bd-badge.pending_payment {
  background: #fef3c7;
  color: #b45309;
}

/* INFO GRID */
.bd-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 18px 24px;
  margin-bottom: 20px;
}

.bd-info-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.bd-info-label {
  font-size: 12px;
  color: #64748b;
}

.bd-info-val {
  font-size: 13.5px;
  color: #0f172a;
  font-weight: 500;
}

/* SLOTS BREAKDOWN (FLAT CLEAN ROWS - NO FLOATING BOXES) */
.bd-items-section {
  border-top: 1px solid #f1f5f9;
  padding-top: 18px;
}

.bd-sub-head {
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 12px;
}

.bd-items-list {
  display: flex;
  flex-direction: column;
}

.bd-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #f1f5f9;
}

.bd-item-row:last-child {
  border-bottom: none;
}

.bd-item-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.bd-item-info strong {
  font-size: 13px;
  color: #0f172a;
  font-weight: 500;
}

.bd-item-info span {
  font-size: 12px;
  color: #64748b;
}

.bd-badge-mini {
  font-size: 11.5px;
  padding: 2px 8px;
  border-radius: 12px;
  background: #f1f5f9;
  color: #475569;
}

.bd-badge-mini.active {
  background: #dcfce7;
  color: #15803d;
}

/* TIMER CARD */
.bd-card--timer {
  background: #ffffff;
  border-color: #fde68a;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.bd-timer-title {
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
  margin: 2px 0;
}

.bd-timer-desc {
  font-size: 12px;
  color: #64748b;
  margin: 0;
}

.bd-timer-clock {
  font-size: 22px;
  font-weight: 500;
  color: #dc2626;
  font-family: monospace;
  letter-spacing: 1px;
}

/* PRICE ROWS */
.bd-price-rows {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 16px;
}

.bd-price-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13.5px;
  color: #475569;
}

.bd-price-row--highlight {
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}

.bd-price-total {
  font-size: 16px;
  color: #0f172a;
  font-weight: 500;
}

.bd-price-req {
  font-size: 17px;
  color: #15803d;
  font-weight: 500;
}

.bd-price-method {
  color: #0f172a;
  font-weight: 500;
}

/* PAYMENT HISTORY */
.bd-payment-history {
  border-top: 1px solid #f1f5f9;
  padding-top: 14px;
  margin-top: 14px;
}

.bd-pay-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12.5px;
  padding: 6px 0;
}

.bd-pay-method {
  color: #334155;
}

.bd-pay-status {
  display: block;
  font-size: 11px;
  color: #64748b;
}

.bd-pay-amount {
  color: #15803d;
  font-weight: 500;
}

/* SEPAY QR AREA */
.bd-sepay-area {
  border-top: 1px solid #f1f5f9;
  padding-top: 16px;
  margin-top: 16px;
}

.bd-sepay-details {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.bd-qr-box {
  display: flex;
  justify-content: center;
  padding: 12px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
}

.bd-qr-box img {
  max-width: 170px;
  height: auto;
  display: block;
}

.bd-transfer-rows {
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-size: 12.5px;
  padding: 8px 0;
}

.bd-transfer-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.bd-transfer-row span {
  color: #64748b;
}

.bd-transfer-row strong {
  color: #0f172a;
  font-weight: 500;
}

.bd-copy-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  padding: 2px 8px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  color: #0f172a;
}

.bd-waiting-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #15803d;
  background: #f0fdf4;
  padding: 8px 12px;
  border-radius: 4px;
}

.bd-cancel-booking-wrap {
  border-top: 1px solid #f1f5f9;
  padding-top: 12px;
  margin-top: 12px;
}

/* ERROR TEXT */
.bd-error-text {
  font-size: 12px;
  color: #dc2626;
  margin: 6px 0;
}

/* SPINNER */
.bd-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid #bbf7d0;
  border-top-color: #15803d;
  border-radius: 50%;
  animation: bdSpin 0.7s linear infinite;
  display: inline-block;
}

.bd-spinner--lg {
  width: 28px;
  height: 28px;
  border-width: 3px;
}

@keyframes bdSpin {
  to {
    transform: rotate(360deg);
  }
}

/* EMPTY & LOADING STATES */
.bd-empty-state,
.bd-loading-state {
  text-align: center;
  padding: 60px 20px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  max-width: 500px;
  margin: 40px auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}

.bd-empty-state h2 {
  font-size: 17px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
}

.bd-empty-state p {
  font-size: 13.5px;
  color: #64748b;
  margin: 0;
}

.bd-empty-actions {
  display: flex;
  gap: 10px;
  margin-top: 8px;
}
</style>

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

            <button
              v-if="canAddInVenueServices"
              type="button"
              class="bd-btn bd-btn--primary"
              @click="openServicesModal"
            >
              🥤 Gọi thêm dịch vụ tại sân
            </button>

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

            <!-- ATTACHED SERVICES LIST -->
            <div v-if="booking.services?.length" class="bd-slots-section">
              <h4 class="bd-sub-title">Dịch vụ đi kèm tại sân</h4>
              <div class="bd-slots-list">
                <div v-for="srv in booking.services" :key="srv.id" class="bd-slot-row">
                  <div>
                    <strong>{{ srv.service_name }}</strong>
                    <p>Số lượng: {{ srv.quantity }} {{ srv.unit || 'lượt' }} x {{ formatPrice(srv.unit_price) }}</p>
                  </div>
                  <strong class="bd-val" style="color: #15803d;">{{ formatPrice(srv.total_price) }}</strong>
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

            <!-- QR CHECK-IN TICKET -->
            <div v-if="['confirmed', 'checked_in'].includes(booking.status)" class="bd-ticket-panel">
              <div class="bd-ticket-head">
                <span class="bd-ticket-kicker">VÉ NHẬN SÂN ĐIỆN TỬ</span>
                <strong class="bd-ticket-code">#{{ booking.booking_code }}</strong>
              </div>
              <div class="bd-ticket-qr-wrap">
                <img
                  :src="`https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(booking.booking_code)}`"
                  :alt="`Mã QR ${booking.booking_code}`"
                  class="bd-ticket-qr"
                  loading="lazy"
                />
              </div>
              <p class="bd-ticket-hint">
                Đưa mã QR này cho nhân viên tại quầy để nhận sân nhanh chóng.
              </p>
            </div>

            <!-- ONLINE PAYMENT -->
            <section v-if="canPayOnline" class="bd-payment-panel" aria-labelledby="bd-payment-title">
              <div class="bd-payment-head">
                <div>
                  <h4 id="bd-payment-title">Thanh toán chuyển khoản</h4>
                  <p>Quét mã QR hoặc nhập thông tin bên dưới để thanh toán.</p>
                </div>
                <button
                  v-if="!paymentInfo && !paymentLoading"
                  type="button"
                  class="bd-btn bd-btn--primary bd-payment-retry"
                  @click="loadPaymentInfo"
                >
                  Hiện mã QR
                </button>
              </div>

              <div v-if="paymentLoading" class="bd-payment-loading">
                <span class="bd-spinner" aria-hidden="true"></span>
                <span>Đang tạo mã QR...</span>
              </div>

              <div v-else-if="paymentError" class="bd-alert bd-alert--error">
                <span>{{ paymentError }}</span>
                <button type="button" class="bd-link-btn" @click="loadPaymentInfo">Thử lại</button>
              </div>

              <div v-else-if="paymentInfo" class="bd-payment-content">
                <div class="bd-payment-amount">
                  <span>Số tiền cần chuyển</span>
                  <strong>{{ formatCurrency(paymentAmount) }}</strong>
                </div>

                <div class="bd-payment-body">
                  <div class="bd-qr-wrap">
                    <img
                      v-if="paymentInfo.qr_url && !qrImageError"
                      :src="paymentInfo.qr_url"
                      alt="Mã QR thanh toán đơn đặt sân"
                      @error="qrImageError = true"
                    />
                    <div v-else class="bd-qr-fallback">
                      <strong>Không tải được ảnh QR</strong>
                      <span>Vui lòng nhập thông tin chuyển khoản bên cạnh.</span>
                    </div>
                  </div>

                  <div class="bd-bank-details">
                    <div>
                      <span>Ngân hàng</span>
                      <strong>{{ paymentAccount.bank_name || paymentAccount.bank_code || "-" }}</strong>
                    </div>
                    <div>
                      <span>Số tài khoản</span>
                      <button type="button" @click="copyPaymentValue(paymentAccount.account_number, 'Số tài khoản')">
                        {{ paymentAccount.account_number || "-" }}
                      </button>
                    </div>
                    <div>
                      <span>Chủ tài khoản</span>
                      <strong>{{ paymentAccount.account_holder_name || "-" }}</strong>
                    </div>
                    <div>
                      <span>Nội dung chuyển khoản</span>
                      <button type="button" @click="copyPaymentValue(paymentInfo.transfer_content, 'Nội dung chuyển khoản')">
                        {{ paymentInfo.transfer_content || "-" }}
                      </button>
                    </div>
                  </div>
                </div>

                <p class="bd-payment-note">
                  Chuyển đúng số tiền và nội dung để hệ thống tự động xác nhận thanh toán.
                </p>
                <p v-if="copySuccess" class="bd-copy-success">{{ copySuccess }}</p>
              </div>
            </section>

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

    <!-- ADD EXTRA SERVICES MODAL -->
    <Teleport to="body">
      <div v-if="showServicesModal" class="bd-modal-overlay" @click.self="showServicesModal = false">
        <div class="bd-modal-content">
          <div class="bd-modal-head">
            <h3>🥤 Gọi thêm dịch vụ tại sân</h3>
            <button type="button" class="bd-modal-close" @click="showServicesModal = false">✕</button>
          </div>
          <div class="bd-modal-body">
            <div v-if="loadingVenueServices" class="bd-modal-loading">Đang tải danh sách dịch vụ tại sân...</div>
            <div v-else-if="!availableVenueServices.length" class="bd-modal-empty">Cụm sân này chưa niêm yết dịch vụ tại sân.</div>
            <div v-else class="bd-modal-services-list">
              <div v-for="srv in availableVenueServices" :key="srv.id" class="cbw-service-item">
                <div class="cbw-srv-info">
                  <strong class="cbw-srv-name">{{ srv.name }}</strong>
                  <span class="cbw-srv-price">{{ formatPrice(srv.price) }} / {{ srv.unit || 'lượt' }}</span>
                </div>
                <div class="cbw-srv-qty">
                  <button type="button" class="cbw-qty-btn" :disabled="!extraServiceQty(srv.id)" @click="updateExtraQty(srv, -1)">-</button>
                  <span class="cbw-qty-val">{{ extraServiceQty(srv.id) }}</span>
                  <button type="button" class="cbw-qty-btn" @click="updateExtraQty(srv, 1)">+</button>
                </div>
              </div>
            </div>
            <p v-if="addServicesError" class="bd-modal-error">{{ addServicesError }}</p>
          </div>
          <div class="bd-modal-foot">
            <button type="button" class="bd-btn bd-btn--outline" @click="showServicesModal = false">Hủy bỏ</button>
            <button
              type="button"
              class="bd-btn bd-btn--primary"
              :disabled="!extraServicesTotal || submittingExtra"
              @click="submitExtraServices"
            >
              <span>{{ submittingExtra ? 'Đang gửi...' : `Xác nhận & Gửi (${formatPrice(extraServicesTotal)})` }}</span>
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
import { venueService } from "../../../services/venues.js";

export default {
  name: "BookingDetail",
  components: { PublicNavbar },
  data() {
    return {
      booking: null,
      loading: true,
      loadError: "",
      bookingLoadController: null,
      cancellingBooking: false,
      cancelBookingError: "",
      startingChat: false,
      timeLeft: 0,
      timerInterval: null,
      showCancelBookingModal: false,
      cancelReason: "Khách hàng thay đổi kế hoạch",
      paymentInfo: null,
      paymentLoading: false,
      paymentError: "",
      qrImageError: false,
      copySuccess: "",
      showServicesModal: false,
      loadingVenueServices: false,
      availableVenueServices: [],
      extraServicesMap: {},
      submittingExtra: false,
      addServicesError: "",
    };
  },
  computed: {
    canAddInVenueServices() {
      return ["confirmed", "checked_in", "pending_approval", "pending_payment"].includes(this.booking?.status);
    },
    extraServicesTotal() {
      return Object.values(this.extraServicesMap).reduce((sum, item) => sum + (item.quantity * item.price), 0);
    },
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
        no_show: "Booking đã quá giờ cho phép nhưng chưa ghi nhận check-in.",
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
        no_show: "Không check-in",
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
    canPayOnline() {
      return this.booking?.status === "pending_payment"
        && Number(this.booking?.required_payment_amount || 0) > 0
        && this.booking?.payment_option !== "wallet"
        && this.timeLeft > 0;
    },
    paymentAccount() {
      return this.paymentInfo?.payment_account || this.paymentInfo?.system_bank_account || {};
    },
    paymentAmount() {
      return this.paymentInfo?.payment?.amount || this.booking?.required_payment_amount || 0;
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
    this.bookingLoadController?.abort();
  },
  watch: {
    "$route.params.id"(nextId, previousId) {
      if (nextId && nextId !== previousId) {
        this.loadBooking();
      }
    },
  },
  methods: {
    extraServiceQty(serviceId) {
      return this.extraServicesMap[serviceId]?.quantity || 0;
    },
    updateExtraQty(srv, delta) {
      const current = this.extraServicesMap[srv.id]?.quantity || 0;
      const next = Math.max(0, current + delta);
      if (next === 0) {
        const copy = { ...this.extraServicesMap };
        delete copy[srv.id];
        this.extraServicesMap = copy;
      } else {
        this.extraServicesMap = {
          ...this.extraServicesMap,
          [srv.id]: {
            service_id: srv.id,
            name: srv.name,
            price: Number(srv.price || 0),
            unit: srv.unit || "lượt",
            quantity: next,
          },
        };
      }
    },
    async openServicesModal() {
      this.showServicesModal = true;
      this.addServicesError = "";
      if (!this.availableVenueServices.length && this.venueId) {
        this.loadingVenueServices = true;
        try {
          const res = await venueService.show(this.venueId);
          this.availableVenueServices = res.data?.services || res.services || [];
        } catch (e) {
          this.addServicesError = "Không tải được danh sách dịch vụ.";
        } finally {
          this.loadingVenueServices = false;
        }
      }
    },
    async submitExtraServices() {
      if (!this.extraServicesTotal) return;
      this.submittingExtra = true;
      this.addServicesError = "";
      try {
        const services = Object.values(this.extraServicesMap).map(item => ({
          service_id: item.service_id,
          quantity: item.quantity
        }));
        await bookingService.addServices(this.booking.id, { services });
        this.showServicesModal = false;
        this.extraServicesMap = {};
        await this.loadBooking();
      } catch (err) {
        this.addServicesError = err.message || "Không thể thêm dịch vụ. Vui lòng thử lại.";
      } finally {
        this.submittingExtra = false;
      }
    },
    async loadBooking() {
      const id = this.$route.params.id;
      if (!id) {
        this.booking = null;
        this.loadError = "Không xác định được mã booking cần xem.";
        this.loading = false;
        return;
      }

      this.bookingLoadController?.abort();
      const controller = new AbortController();
      this.bookingLoadController = controller;
      this.loading = true;
      this.loadError = "";
      try {
        const res = await bookingService.getBooking(id, { signal: controller.signal });
        if (controller.signal.aborted) return;
        this.booking = res;
        this.timeLeft = Math.max(0, Math.floor(Number(res.time_left_seconds) || 0));

        if (this.booking.status === "pending_payment" && this.timeLeft > 0) {
          this.startTimer();
        } else {
          this.clearTimer();
        }

        // Không tạo mã QR trong lúc tải trang. API này có thể chậm do phải
        // tạo payment record/QR; người dùng chỉ cần gọi khi bấm "Hiện mã QR".
        if (!this.canPayOnline) {
          this.paymentInfo = null;
          this.paymentError = "";
        }
      } catch (err) {
        if (controller.signal.aborted) return;
        this.booking = null;
        this.loadError = err.status === 404
          ? "Đơn đặt sân này không còn tồn tại. Vui lòng mở lại booking từ Lịch sử đặt sân."
          : (err.message || "Không thể tải thông tin booking.");
      } finally {
        if (this.bookingLoadController === controller) {
          this.bookingLoadController = null;
          this.loading = false;
        }
      }
    },
    async loadPaymentInfo() {
      if (!this.booking?.id || !this.canPayOnline || this.paymentLoading) return;

      this.paymentLoading = true;
      this.paymentError = "";
      this.qrImageError = false;
      try {
        this.paymentInfo = await bookingService.createSepayPayment(this.booking.id);
      } catch (err) {
        this.paymentInfo = null;
        this.paymentError = err.message || "Không thể tạo mã QR thanh toán.";
      } finally {
        this.paymentLoading = false;
      }
    },
    async copyPaymentValue(value, label) {
      if (!value) return;

      try {
        await navigator.clipboard.writeText(String(value));
        this.copySuccess = `Đã sao chép ${label.toLowerCase()}.`;
        window.setTimeout(() => {
          this.copySuccess = "";
        }, 2200);
      } catch (err) {
        this.copySuccess = `Không thể sao chép ${label.toLowerCase()}.`;
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
    paymentStatusLabel(status) {
      const map = {
        pending: "Đang chờ xử lý",
        paid: "Đã thanh toán",
        failed: "Thanh toán thất bại",
        cancelled: "Đã hủy",
        refunded: "Đã hoàn tiền",
        partially_refunded: "Đã hoàn một phần",
      };
      return map[status] || status || "Chưa xác định";
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
        const res = await chatService.startConversation({
          type: "venue_contact",
          venue_id: this.venueId,
          venue_cluster_id: this.venueId,
        });
        if (res && res.id) {
          this.$router.push({
            path: "/chat",
            query: { conversation_id: res.id, booking_id: this.booking.id, venue_id: this.venueId },
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
  color: #5c7e6e;
  text-decoration: none;
  font-weight: 600;
}

.bd-title {
  font-size: 20px;
  font-weight: 700;
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
  padding: 8px 18px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 999px;
  cursor: pointer;
  text-decoration: none;
  border: 1.5px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  transition: all 0.15s ease;
}

.bd-btn--primary {
  background: #54656f;
  color: #ffffff;
  border: none;
  box-shadow: 0 4px 14px rgba(84, 101, 111, 0.25);
}

.bd-btn--primary:hover:not(:disabled) {
  background: #405059;
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(84, 101, 111, 0.35);
}

.bd-btn--outline {
  background: #ffffff;
  color: #475569;
  border-color: #cbd5e1;
}

.bd-btn--outline:hover {
  border-color: #54656f;
  color: #0f172a;
  background: #f8fafc;
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
  font-weight: 600;
  color: #5c7e6e;
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

.bd-ticket-panel {
  margin-top: 24px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 10px;
}

.bd-ticket-head {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.bd-ticket-kicker {
  font-size: 11px;
  font-weight: 500;
  color: #15803d;
  letter-spacing: 0.5px;
}

.bd-ticket-code {
  font-size: 15px;
  color: #0f172a;
}

.bd-ticket-qr-wrap {
  background: #ffffff;
  padding: 8px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bd-ticket-qr {
  width: 140px;
  height: 140px;
  object-fit: contain;
}

.bd-ticket-hint {
  font-size: 12px;
  color: #64748b;
  margin: 0;
  line-height: 1.4;
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

/* PAYMENT */
.bd-payment-panel {
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
}

.bd-payment-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.bd-payment-head h4 {
  margin: 0 0 4px;
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.bd-payment-head p,
.bd-payment-note {
  margin: 0;
  font-size: 12px;
  line-height: 1.5;
  color: #64748b;
}

.bd-payment-retry {
  flex: 0 0 auto;
  padding: 7px 10px;
  font-size: 12px;
}

.bd-payment-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 64px;
  font-size: 12.5px;
  color: #475569;
}

.bd-payment-loading .bd-spinner {
  width: 18px;
  height: 18px;
}

.bd-payment-content {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.bd-payment-amount {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 4px;
  font-size: 12.5px;
  color: #166534;
}

.bd-payment-amount strong {
  font-size: 15px;
  color: #15803d;
}

.bd-payment-body {
  display: grid;
  grid-template-columns: 150px 1fr;
  gap: 14px;
  align-items: center;
}

.bd-qr-wrap {
  width: 150px;
  height: 150px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  background: #ffffff;
  overflow: hidden;
}

.bd-qr-wrap img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.bd-qr-fallback {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px;
  text-align: center;
  font-size: 11px;
  line-height: 1.4;
  color: #64748b;
}

.bd-qr-fallback strong {
  color: #b45309;
}

.bd-bank-details {
  display: flex;
  flex-direction: column;
  gap: 9px;
  min-width: 0;
}

.bd-bank-details > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.bd-bank-details span {
  font-size: 11px;
  color: #64748b;
}

.bd-bank-details strong,
.bd-bank-details button {
  overflow-wrap: anywhere;
  font-size: 12.5px;
  font-weight: 600;
  color: #0f172a;
}

.bd-bank-details button,
.bd-link-btn {
  padding: 0;
  border: 0;
  background: transparent;
  color: #15803d;
  text-align: left;
  cursor: pointer;
}

.bd-bank-details button:hover,
.bd-link-btn:hover {
  text-decoration: underline;
}

.bd-payment-note {
  padding-top: 2px;
}

.bd-copy-success {
  margin: -6px 0 0;
  font-size: 12px;
  color: #15803d;
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

.bd-alert--error {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  font-size: 12px;
}

@media (max-width: 420px) {
  .bd-payment-body {
    grid-template-columns: 1fr;
  }

  .bd-qr-wrap {
    justify-self: center;
  }
}

.bd-modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 8px 20px 20px;
  background: #ffffff;
}

.bd-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 16px;
}

.bd-modal-content {
  background: #ffffff;
  border-radius: 8px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.bd-modal-services-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 320px;
  overflow-y: auto;
}

.bd-modal-loading,
.bd-modal-empty {
  padding: 24px 0;
  text-align: center;
  color: #64748b;
  font-size: 13.5px;
}

.bd-modal-error {
  color: #dc2626;
  font-size: 13px;
  margin: 4px 0 0 0;
}
</style>

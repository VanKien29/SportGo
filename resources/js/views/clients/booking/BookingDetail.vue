<template>
    <div class="detail-container sg-client-page sg3-booking-detail-page">
        <PublicNavbar />

        <main v-if="!loading" class="detail-main sg-client-shell">
            <div v-if="booking" class="detail-content">
                <header class="detail-head">
                    <nav class="breadcrumbs" aria-label="Điều hướng booking">
                        <router-link :to="{ name: 'booking-history' }">
                            <AppIcon name="arrowLeft" aria-hidden="true" />
                            Quay lại lịch sử đặt sân
                        </router-link>
                        <AppIcon name="chevronRight" aria-hidden="true" />
                        <strong>#{{ booking.booking_code }}</strong>
                    </nav>
                    <div class="detail-head__actions">
                        <router-link
                            v-if="venueId"
                            :to="{ name: 'venue-detail', params: { id: venueId } }"
                            class="sg-client-button"
                        >
                            <AppIcon name="mapPin" aria-hidden="true" />
                            Xem sân
                        </router-link>
                        <router-link
                            v-if="venueId"
                            :to="rebookLocation"
                            class="sg-client-button sg-client-button--primary"
                        >
                            <AppIcon name="rotateCcw" aria-hidden="true" />
                            Đặt thêm lịch
                        </router-link>
                        <router-link
                            :to="{ name: 'client-complaint-create', query: { booking_id: booking.id } }"
                            class="sg-client-button"
                        >
                            <AppIcon name="messageSquare" aria-hidden="true" />
                            Gửi khiếu nại
                        </router-link>
                        <button v-if="canRequestRefund" type="button" class="sg-client-button refund-request-link" @click="openRefundRequest">
                            <AppIcon name="rotateCcw" aria-hidden="true" />
                            Yêu cầu hoàn tiền
                        </button>
                        <button v-if="canEditSchedule" type="button" class="sg-client-button" @click="openScheduleEditor">
                            <AppIcon name="pencil" aria-hidden="true" />
                            Đổi lịch / đổi sân
                        </button>
                    </div>
                </header>

                <section class="status-banner sg-client-card" :class="statusClass">
                    <div class="banner-icon">
                        <AppIcon :name="statusIcon" aria-hidden="true" />
                    </div>
                    <div class="banner-text">
                        <span class="eyebrow sg-client-eyebrow">Trạng thái booking</span>
                        <h1>{{ statusTitle }}</h1>
                        <p>{{ statusDescription }}</p>
                    </div>
                    <span class="badge" :class="booking.status">{{ statusLabel }}</span>
                </section>

                <section v-if="booking.matchmaking" class="booking-social-banner sg-client-card">
                    <span class="booking-social-banner__icon"><AppIcon name="users" :size="21" /></span>
                    <div><span class="eyebrow sg-client-eyebrow">Tuyển giao lưu</span><h2>Đã ghép {{ booking.matchmaking.approved_players }}/{{ booking.matchmaking.total_players }} người</h2><p>Còn cần {{ booking.matchmaking.needed_players }} người cho buổi chơi này. Bạn có thể xem và xử lý danh sách yêu cầu.</p></div>
                    <router-link class="sg-client-button sg-client-button--primary" :to="`/matchmaking-posts/${booking.matchmaking.id}/manage`">Quản lý danh sách <AppIcon name="arrowRight" :size="15" /></router-link>
                </section>

                <section v-if="showScheduleEditor" class="schedule-edit-panel sg-client-card">
                    <header>
                        <div>
                            <span class="eyebrow sg-client-eyebrow">Điều chỉnh booking</span>
                            <h2>Đổi sân hoặc khung giờ</h2>
                        </div>
                        <button type="button" class="sg-client-button" @click="closeScheduleEditor">Đóng</button>
                    </header>
                    <div class="schedule-edit-grid">
                        <label><span>Sân</span><select v-model="editForm.venue_court_id" class="sg-client-input"><option v-for="court in editCourtOptions" :key="court.id" :value="court.id">{{ court.name }} · {{ court.court_type?.name || 'Sân thể thao' }}</option></select></label>
                        <label><span>Ngày chơi</span><input v-model="editForm.booking_date" class="sg-client-input" type="date" :min="today" /></label>
                        <label><span>Bắt đầu</span><input v-model="editForm.start_time" class="sg-client-input" type="time" step="1800" /></label>
                        <label><span>Kết thúc</span><input v-model="editForm.end_time" class="sg-client-input" type="time" step="1800" /></label>
                        <label class="schedule-edit-reason"><span>Lý do điều chỉnh</span><textarea v-model.trim="editForm.reason" class="sg-client-input" rows="3" maxlength="1000" placeholder="Nêu lý do để lưu lại lịch sử thay đổi"></textarea></label>
                    </div>
                    <p v-if="editError" class="error-msg">{{ editError }}</p>
                    <button type="button" class="sg-client-button sg-client-button--primary" :disabled="editingSchedule" @click="saveScheduleEdit">{{ editingSchedule ? 'Đang cập nhật...' : 'Lưu thay đổi' }}</button>
                </section>

                <div class="detail-grid">
                    <section class="info-section">
                        <article class="card info-card sg-client-card">
                            <header class="card-header-simple">
                                <div>
                                    <span class="eyebrow sg-client-eyebrow">Thông tin buổi chơi</span>
                                    <h2>Chi tiết đơn đặt #{{ booking.booking_code }}</h2>
                                </div>
                                <span class="badge" :class="booking.status">{{ statusLabel }}</span>
                            </header>

                            <dl class="info-list">
                                <div class="info-item">
                                    <dt>Cụm sân</dt>
                                    <dd>{{ venueCluster?.name || "Đang cập nhật" }}</dd>
                                </div>
                                <div class="info-item">
                                    <dt>Địa chỉ</dt>
                                    <dd>{{ venueAddress }}</dd>
                                </div>
                                <div class="info-item">
                                    <dt>Sân chơi</dt>
                                    <dd>{{ courtText }}</dd>
                                </div>
                                <div class="info-item">
                                    <dt>Ngày chơi</dt>
                                    <dd>{{ formatDate(booking.booking_date) }}</dd>
                                </div>
                                <div class="info-item">
                                    <dt>Khung giờ</dt>
                                    <dd>{{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}</dd>
                                </div>
                                <div class="info-item">
                                    <dt>Thời lượng</dt>
                                    <dd>{{ booking.duration_minutes ? booking.duration_minutes + " phút" : "-" }}</dd>
                                </div>
                                <div class="info-item">
                                    <dt>Hình thức đặt</dt>
                                    <dd>{{ bookingTypeLabel }}</dd>
                                </div>
                            </dl>

                            <div v-if="booking.items?.length" class="booking-items-list">
                                <div class="booking-items-heading"><h3>Các khung giờ trong booking</h3><span v-if="canCancelItems" class="booking-items-hint">Chọn khung muốn hủy</span></div>
                                <article v-for="item in booking.items" :key="item.id" class="booking-item-row">
                                    <div>
                                        <input v-if="canCancelItems" v-model="selectedCancelItemIds" type="checkbox" :value="item.id" :disabled="!['active', 'moved'].includes(item.status || 'active')" aria-label="Chọn khung giờ" />
                                        <strong>{{ item.venue_court?.name || "Sân" }}</strong>
                                        <span>{{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }}</span>
                                    </div>
                                    <span class="badge" :class="item.status">{{ itemStatusLabel(item.status) }}</span>
                                </article>
                                <div v-if="canCancelItems && selectedCancelItemIds.length" class="partial-cancel-box">
                                    <textarea v-model.trim="partialCancelReason" class="sg-client-input" rows="2" maxlength="1000" placeholder="Lý do hủy các khung đã chọn"></textarea>
                                    <p v-if="partialError" class="error-msg">{{ partialError }}</p>
                                    <button type="button" class="sg-client-button" :disabled="partialSubmitting" @click="cancelSelectedItems">{{ partialSubmitting ? 'Đang xử lý...' : 'Hủy các khung đã chọn' }}</button>
                                </div>
                            </div>
                        </article>

                        <article v-if="booking.status_reason" class="status-note sg-client-card">
                            <AppIcon name="alert" aria-hidden="true" />
                            <div>
                                <strong>Cập nhật về đơn đặt sân</strong>
                                <p>{{ booking.status_reason }}</p>
                            </div>
                        </article>
                    </section>

                    <aside class="payment-section" :class="{ 'has-qr': sepayPayment }">
                        <article
                            v-if="booking.status === 'pending_payment'"
                            class="card countdown-card sg-client-card"
                        >
                            <div class="countdown-copy">
                                <span class="eyebrow sg-client-eyebrow">Giữ chỗ tạm thời</span>
                                <h2>Thời gian thanh toán còn lại</h2>
                                <p>Hoàn tất thanh toán trước khi thời gian kết thúc để sân không bị giải phóng.</p>
                            </div>
                            <div class="timer" aria-live="polite">{{ formattedTimer }}</div>
                        </article>

                        <article class="card price-card sg-client-card">
                            <header class="price-card__head">
                                <span class="eyebrow sg-client-eyebrow">Thanh toán</span>
                                <h2>Thông tin chi phí</h2>
                            </header>

                            <dl class="price-rows">
                                <div class="price-row">
                                    <dt>Tổng tiền sân</dt>
                                    <dd>{{ formatCurrency(booking.total_price) }}</dd>
                                </div>
                                <div class="price-row">
                                    <dt>Hình thức thanh toán</dt>
                                    <dd>{{ paymentOptionLabel }}</dd>
                                </div>
                                <div v-if="booking.status === 'pending_payment'" class="price-row highlighted">
                                    <dt>Số tiền cần trả ngay</dt>
                                    <dd>{{ formatCurrency(booking.required_payment_amount) }}</dd>
                                </div>
                            </dl>

                            <div v-if="booking.payments?.length" class="payment-history-list">
                                <h3>Lịch sử thanh toán</h3>
                                <div v-for="payment in booking.payments" :key="payment.id" class="payment-history-row">
                                    <span>{{ payment.method === "wallet" ? "Ví SportGo" : payment.method === "sepay" ? "Chuyển khoản" : payment.method }}</span>
                                    <strong>{{ formatCurrency(payment.amount) }}</strong>
                                    <small>{{ paymentStatusLabel(payment.status) }}</small>
                                    <button v-if="payment.status === 'paid'" type="button" class="payment-receipt-link" @click="openPaymentReceipt(payment)">Biên lai</button>
                                    <button v-else-if="payment.status === 'failed' && booking.status === 'pending_payment'" type="button" class="payment-receipt-link" @click="createSepayPayment">Thanh toán lại</button>
                                </div>
                            </div>
                            <p v-if="receiptError" class="error-msg">{{ receiptError }}</p>

                            <div v-if="booking.status === 'pending_payment'" class="sepay-box">
                                <button
                                    v-if="!sepayPayment"
                                    class="btn-sepay"
                                    type="button"
                                    :disabled="creatingSepay || timeLeft <= 0"
                                    @click="createSepayPayment"
                                >
                                    <AppIcon name="qrCode" aria-hidden="true" />
                                    {{ creatingSepay ? "Đang tạo QR..." : "Tạo QR thanh toán" }}
                                </button>

                                <p v-if="sepayError" class="error-msg">{{ sepayError }}</p>

                                <button
                                    v-if="!sepayPayment"
                                    class="btn-cancel-payment"
                                    type="button"
                                    :disabled="cancellingPayment"
                                    @click="showCancelPaymentModal = true"
                                >
                                    <AppIcon name="circleX" aria-hidden="true" />
                                    {{ cancellingPayment ? "Đang hủy thanh toán..." : "Hủy thanh toán" }}
                                </button>

                                <div v-if="sepayPayment" class="sepay-panel">
                                    <div class="qr-wrap">
                                        <img :src="sepayPayment.qr_url" alt="QR thanh toán SePay" />
                                    </div>
                                    <dl class="transfer-info">
                                        <div class="transfer-row">
                                            <dt>Ngân hàng</dt>
                                            <dd>
                                                {{ sepayPayment.payment_account?.bank_name
                                                    || sepayPayment.payment_account?.bank_code
                                                    || "SePay" }}
                                            </dd>
                                        </div>
                                        <div class="transfer-row">
                                            <dt>Số tài khoản</dt>
                                            <dd>
                                                {{ sepayPayment.payment_account?.account_number
                                                    || sepayPayment.payment_account?.account_number_masked }}
                                            </dd>
                                        </div>
                                        <div class="transfer-row">
                                            <dt>Chủ tài khoản</dt>
                                            <dd>{{ sepayPayment.payment_account?.account_holder_name || "Đang cập nhật" }}</dd>
                                        </div>
                                        <div class="transfer-row">
                                            <dt>Nội dung</dt>
                                            <dd>
                                                <button
                                                    class="copy-value"
                                                    type="button"
                                                    @click="copyText(sepayPayment.transfer_content)"
                                                >
                                                    {{ sepayPayment.transfer_content }}
                                                    <AppIcon name="copy" aria-hidden="true" />
                                                </button>
                                            </dd>
                                        </div>
                                        <div class="transfer-row">
                                            <dt>Số tiền</dt>
                                            <dd>{{ formatCurrency(sepayPayment.payment?.amount) }}</dd>
                                        </div>
                                    </dl>
                                    <div class="payment-waiting">
                                        <span class="mini-spinner" aria-hidden="true"></span>
                                        <span>Đang chờ hệ thống xác nhận thanh toán.</span>
                                    </div>
                                    <button
                                        class="btn-cancel-payment"
                                        type="button"
                                        :disabled="cancellingPayment"
                                        @click="showCancelPaymentModal = true"
                                    >
                                        <AppIcon name="circleX" aria-hidden="true" />
                                        {{ cancellingPayment ? "Đang hủy thanh toán..." : "Hủy thanh toán" }}
                                    </button>
                                </div>
                            </div>

                            <button
                                v-if="canRequestCancellation"
                                type="button"
                                class="btn-cancel-booking"
                                :disabled="cancellingBooking"
                                @click="openCancelBookingModal"
                            >
                                <AppIcon name="circleX" aria-hidden="true" />
                                {{ cancellingBooking ? "Đang hủy booking..." : "Yêu cầu hủy booking" }}
                            </button>
                        </article>
                    </aside>
                </div>
            </div>

            <section v-else class="detail-empty sg-client-state">
                <AppIcon name="calendar" aria-hidden="true" />
                <strong>Không tải được booking</strong>
                <p>{{ loadError || "Không tìm thấy thông tin đơn đặt sân." }}</p>
                <div class="empty-actions">
                    <button v-if="loadError" type="button" class="sg-client-button" @click="loadBooking">
                        <AppIcon name="refresh" aria-hidden="true" />
                        Thử lại
                    </button>
                    <router-link :to="{ name: 'booking-history' }" class="sg-client-button">
                        <AppIcon name="history" aria-hidden="true" />
                        Về lịch sử
                    </router-link>
                    <router-link :to="{ name: 'booking-create' }" class="sg-client-button sg-client-button--primary">
                        <AppIcon name="plus" aria-hidden="true" />
                        Đặt sân mới
                    </router-link>
                </div>
            </section>
        </main>

        <main v-else class="detail-loading sg-client-state">
            <span class="spinner" aria-hidden="true"></span>
            <p>Đang tải thông tin đơn đặt sân...</p>
        </main>

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

        <RefundRequestModal
            :is-open="showRefundRequestModal"
            :booking="booking"
            :loading="requestingRefund"
            :error="refundRequestError"
            @close="closeRefundRequest"
            @submit="submitRefundRequest"
        />
    </div>
</template>

<script>
import AppIcon from "../../../components/AppIcon.vue";
import ConfirmActionModal from "../../../components/ConfirmActionModal.vue";
import PublicNavbar from "../../../components/PublicNavbar.vue";
import RefundRequestModal from "../../../components/RefundRequestModal.vue";
import { bookingService } from "../../../services/bookingService.js";

export default {
    name: "BookingDetail",
    components: { AppIcon, ConfirmActionModal, PublicNavbar, RefundRequestModal },
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
            showRefundRequestModal: false,
            requestingRefund: false,
            refundRequestError: "",
            showScheduleEditor: false,
            editingSchedule: false,
            editError: "",
            editForm: { venue_court_id: "", booking_date: "", start_time: "", end_time: "", reason: "" },
            selectedCancelItemIds: [],
            partialCancelReason: "",
            partialSubmitting: false,
            partialError: "",
            receiptError: "",
        };
    },
    computed: {
        today() {
            return new Date().toLocaleDateString("en-CA");
        },
        formattedTimer() {
            const totalSeconds = Math.max(
                0,
                Math.floor(Number(this.timeLeft) || 0),
            );
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
        },
        statusClass() {
            if (!this.booking) return "";
            return this.booking.status;
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
                cancelled: "Đơn Đã Bị Hủy",
            };
            return map[this.booking.status] || "Trạng thái không xác định";
        },
        statusDescription() {
            if (!this.booking) return "";
            const map = {
                confirmed:
                    "Đơn của bạn đã được xác nhận. Hẹn gặp lại bạn tại sân chơi!",
                pending_payment: "Vui lòng hoàn tất thanh toán để giữ chỗ.",
                pending_approval:
                    "Chủ sân đang kiểm tra thông tin cấu hình và duyệt đơn đặt của bạn.",
                checked_in: "Bạn đã check-in tại sân. Chúc bạn có buổi chơi hiệu quả.",
                completed: "Buổi chơi đã hoàn tất. Cảm ơn bạn đã sử dụng SportGo.",
                rejected: "Booking không được sân chấp nhận. Vui lòng chọn khung giờ khác.",
                expired:
                    "Đơn đã quá hạn thanh toán. Sân đã được giải phóng để người khác có thể đặt.",
                cancelled:
                    "Đơn đặt sân này đã bị hủy bỏ bởi hệ thống hoặc người dùng.",
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
            return (
                map[this.booking.payment_option] || this.booking.payment_option
            );
        },
        venueCluster() {
            return this.booking?.venue_court?.venue_cluster
                || this.booking?.venue_cluster
                || null;
        },
        venueId() {
            return this.venueCluster?.id
                || this.booking?.venue_cluster_id
                || null;
        },
        venueAddress() {
            return this.venueCluster?.full_address
                || this.venueCluster?.address
                || "Đang cập nhật";
        },
        courtText() {
            const courtName = this.booking?.venue_court?.name || "Sân";
            const courtType = this.booking?.venue_court?.court_type?.name;
            return courtType ? `${courtName} (${courtType})` : courtName;
        },
        bookingTypeLabel() {
            return this.booking?.booking_type === "recurring"
                ? "Đặt cố định"
                : "Đặt lẻ";
        },
        rebookLocation() {
            const query = { venue_cluster_id: this.venueId };
            const courtTypeId = this.booking?.venue_court?.court_type?.id;
            if (courtTypeId) query.court_type_id = courtTypeId;
            return { name: "booking-create", query };
        },
        editCourtOptions() {
            return this.venueCluster?.venue_courts || [];
        },
        canEditSchedule() {
            return ["pending_approval", "pending_payment"].includes(this.booking?.status)
                && !(this.booking?.payments || []).some(payment => payment.status === "paid");
        },
        canCancelItems() {
            const activeCount = (this.booking?.items || []).filter(item => ["active", "moved"].includes(item.status || "active")).length;
            return activeCount > 1 && ["confirmed", "pending_approval", "pending_payment"].includes(this.booking?.status);
        },
        canRequestCancellation() {
            if (!["pending_approval", "confirmed"].includes(this.booking?.status)) return false;
            const date = String(this.booking?.booking_date || "").split("T")[0];
            const time = String(this.booking?.start_time || "").slice(0, 5);
            const startsAt = new Date(`${date}T${time}:00`);
            return Number.isNaN(startsAt.getTime()) || startsAt > new Date();
        },
        canRequestRefund() {
            if (!this.booking || !['cancelled', 'rejected'].includes(this.booking.status)) return false;
            if (Number(this.booking.paid_amount || 0) <= 0) return false;
            const blockingStatuses = ['pending_owner_confirmation', 'owner_confirmed', 'admin_processing', 'processing', 'completed', 'completed_cash'];
            return !(this.booking.refunds || []).some((refund) => blockingStatuses.includes(refund.status));
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

                if (
                    this.booking.status === "pending_payment" &&
                    this.timeLeft > 0
                ) {
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
                    // Khi đếm ngược về 0, chuyển trạng thái đơn đặt sân sang expired
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
            if (!this.booking || this.creatingSepay || this.timeLeft <= 0)
                return;

            this.creatingSepay = true;
            this.sepayError = "";

            try {
                const res = await bookingService.createSepayPayment(
                    this.booking.id,
                );
                this.sepayPayment = res;
                this.startPaymentPolling();
            } catch (err) {
                this.sepayError =
                    err.message || "Không thể tạo thông tin thanh toán SePay.";
            } finally {
                this.creatingSepay = false;
            }
        },
        async openPaymentReceipt(payment) {
            if (!payment?.id) return;
            this.receiptError = "";
            try {
                const response = await bookingService.getPaymentReceipt(payment.id);
                if (response.receipt?.view_url) {
                    window.open(response.receipt.view_url, "_blank", "noopener,noreferrer");
                    return;
                }
                this.receiptError = "Biên lai đang được chuẩn bị.";
            } catch (error) {
                this.receiptError = error.message || "Không thể mở biên lai.";
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
                this.sepayError =
                    err.message || "Không thể kiểm tra trạng thái thanh toán.";
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
        openRefundRequest() {
            this.refundRequestError = "";
            this.showRefundRequestModal = true;
        },
        openScheduleEditor() {
            const item = this.booking?.items?.[0];
            this.editForm = {
                venue_court_id: item?.venue_court_id || this.booking?.venue_court_id || "",
                booking_date: String(this.booking?.booking_date || "").split("T")[0],
                start_time: String(item?.start_time || this.booking?.start_time || "").slice(0, 5),
                end_time: String(item?.end_time || this.booking?.end_time || "").slice(0, 5),
                reason: "Khách hàng muốn điều chỉnh lịch chơi",
            };
            this.editError = "";
            this.showScheduleEditor = true;
        },
        closeScheduleEditor() {
            if (this.editingSchedule) return;
            this.showScheduleEditor = false;
            this.editError = "";
        },
        async saveScheduleEdit() {
            if (!this.booking || this.editingSchedule) return;
            this.editingSchedule = true;
            this.editError = "";
            try {
                await bookingService.rescheduleBooking(this.booking.id, {
                    ...this.editForm,
                    start_time: `${this.editForm.start_time}:00`,
                    end_time: `${this.editForm.end_time}:00`,
                });
                this.showScheduleEditor = false;
                await this.loadBooking();
            } catch (error) {
                this.editError = error.message || "Không thể đổi lịch booking.";
            } finally {
                this.editingSchedule = false;
            }
        },
        async cancelSelectedItems() {
            if (!this.booking || !this.selectedCancelItemIds.length || this.partialSubmitting) return;
            if (!this.partialCancelReason || this.partialCancelReason.length < 5) {
                this.partialError = "Vui lòng nhập lý do hủy tối thiểu 5 ký tự.";
                return;
            }
            this.partialSubmitting = true;
            this.partialError = "";
            try {
                await bookingService.cancelBookingItems(this.booking.id, {
                    booking_item_ids: this.selectedCancelItemIds,
                    reason: this.partialCancelReason,
                });
                this.selectedCancelItemIds = [];
                this.partialCancelReason = "";
                await this.loadBooking();
            } catch (error) {
                this.partialError = error.message || "Không thể hủy các khung đã chọn.";
            } finally {
                this.partialSubmitting = false;
            }
        },
        closeRefundRequest() {
            if (this.requestingRefund) return;
            this.showRefundRequestModal = false;
            this.refundRequestError = "";
        },
        async submitRefundRequest(payload) {
            if (this.requestingRefund) return;
            this.requestingRefund = true;
            this.refundRequestError = "";
            try {
                await bookingService.requestRefund(payload);
                this.showRefundRequestModal = false;
                await this.loadBooking();
            } catch (error) {
                this.refundRequestError = error.message || "Không thể gửi yêu cầu hoàn tiền.";
            } finally {
                this.requestingRefund = false;
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
            return {
                active: "Đang giữ",
                moved: "Đã đổi sân",
                cancelled: "Đã hủy",
                interrupted: "Bị gián đoạn",
                cancelled_by_customer: "Khách đã hủy",
            }[status] || status || "Đang xử lý";
        },
        paymentStatusLabel(status) {
            return {
                pending: "Chờ thanh toán",
                paid: "Đã thanh toán",
                failed: "Thất bại",
                refunded: "Đã hoàn tiền",
            }[status] || status || "Đang xử lý";
        },
    },
};
</script>

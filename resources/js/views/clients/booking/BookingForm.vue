<template>
    <div class="booking-container">
        <PublicNavbar />

        <main class="booking-main">
            <div class="booking-grid" v-if="!loadingInit">
                <div class="form-section">
                    <div class="card schedule-card" v-if="selectedClusterId">
                        <div class="card-header schedule-header">
                            <div>
                                <span class="card-icon">L</span>
                                <h2>Lịch & Đặt sân</h2>
                            </div>
                        </div>

                        <div class="schedule-controls">
                            <div class="form-group">
                                <label for="cluster">Cụm sân</label>
                                <select
                                    id="cluster"
                                    v-model="selectedClusterId"
                                    @change="onClusterChange"
                                    class="form-control"
                                >
                                    <option value="" disabled>
                                        -- Chọn cụm sân --
                                    </option>
                                    <option
                                        v-for="c in clusters"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="date">Ngày đặt sân</label>
                                <input
                                    type="date"
                                    id="date"
                                    v-model="bookingDate"
                                    :min="minDate"
                                    @change="onDateChange"
                                    class="form-control"
                                />
                            </div>
                            <div class="form-group">
                                <label for="schedule_court_type"
                                    >Loại sân</label
                                >
                                <select
                                    id="schedule_court_type"
                                    v-model="selectedScheduleCourtTypeId"
                                    @change="onScheduleCourtTypeChange"
                                    class="form-control"
                                >
                                    <option value="">Tất cả loại sân</option>
                                    <option
                                        v-for="type in clusterCourtTypes"
                                        :key="type.id"
                                        :value="String(type.id)"
                                    >
                                        {{ type.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="schedule-legend">
                            <span><i class="legend-free"></i> Trống</span>
                            <span
                                ><i class="legend-busy"></i> Không thể đặt</span
                            >
                            <span
                                ><i class="legend-selected"></i> Đang chọn</span
                            >
                            <em>* Chọn 1 hoặc nhiều ô trống liên tiếp.</em>
                        </div>

                        <div v-if="scheduleLoading" class="schedule-state">
                            Đang tải lịch trống...
                        </div>
                        <div
                            v-else-if="scheduleError"
                            class="schedule-state error"
                        >
                            {{ scheduleError }}
                        </div>
                        <div
                            v-else-if="scheduleCourts.length === 0"
                            class="schedule-state"
                        >
                            Không có sân đang hoạt động cho bộ lọc này.
                        </div>
                        <div v-else class="schedule-wrap">
                            <div
                                class="schedule-grid"
                                :style="scheduleGridStyle"
                            >
                                <div class="schedule-head sticky-col">
                                    Sân \ Giờ
                                </div>
                                <div
                                    v-for="slot in scheduleSlots"
                                    :key="slot.start_time"
                                    class="schedule-head time-head"
                                >
                                    {{ slot.label }}
                                </div>

                                <template
                                    v-for="court in scheduleCourts"
                                    :key="court.id"
                                >
                                    <div class="schedule-court sticky-col">
                                        <strong>{{ court.name }}</strong>
                                        <span>{{
                                            court.court_type?.name
                                        }}</span>
                                    </div>
                                    <button
                                        v-for="(slot, index) in scheduleSlots"
                                        :key="court.id + '-' + slot.start_time"
                                        type="button"
                                        class="schedule-cell"
                                        :class="{
                                            busy: isSlotBusy(court.id, slot),
                                            past: isSlotPast(slot),
                                            selected: isSlotSelected(
                                                court.id,
                                                index,
                                            ),
                                        }"
                                        :title="slotTitle(court, slot, index)"
                                        :disabled="isSlotBusy(court.id, slot)"
                                        @click="
                                            selectScheduleSlot(court, index)
                                        "
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: Chọn phương thức thanh toán -->
                    <div class="card" v-if="selectedCourtId && isAvailable">
                        <div class="card-header">
                            <span class="card-icon">3</span>
                            <h2>Chọn hình thức thanh toán</h2>
                        </div>
                        <div class="card-body">
                            <div class="payment-options">
                                <!-- Không trả trước -->
                                <label
                                    v-if="config.allow_no_prepay"
                                    class="payment-option-card"
                                    :class="{
                                        active: paymentOption === 'no_prepay',
                                    }"
                                >
                                    <input
                                        type="radio"
                                        v-model="paymentOption"
                                        value="no_prepay"
                                        class="hidden-radio"
                                    />
                                    <div class="option-info">
                                        <span class="option-title"
                                            >Không trả trước</span
                                        >
                                        <span class="option-desc"
                                            >Thanh toán trực tiếp tại sân khi
                                            đến chơi.</span
                                        >
                                    </div>
                                </label>

                                <!-- Đặt cọc -->
                                <label
                                    v-if="config.allow_deposit"
                                    class="payment-option-card"
                                    :class="{
                                        active: paymentOption === 'deposit',
                                    }"
                                >
                                    <input
                                        type="radio"
                                        v-model="paymentOption"
                                        value="deposit"
                                        class="hidden-radio"
                                    />
                                    <div class="option-info">
                                        <span class="option-title"
                                            >Đặt cọc trước ({{
                                                config.deposit_percent || 30
                                            }}%)</span
                                        >
                                        <span class="option-desc"
                                            >Đặt cọc online để giữ chỗ, phần còn
                                            lại trả tại sân.</span
                                        >
                                    </div>
                                </label>

                                <!-- Thanh toán hết -->
                                <label
                                    v-if="config.allow_full_payment"
                                    class="payment-option-card"
                                    :class="{
                                        active:
                                            paymentOption === 'full_payment',
                                    }"
                                >
                                    <input
                                        type="radio"
                                        v-model="paymentOption"
                                        value="full_payment"
                                        class="hidden-radio"
                                    />
                                    <div class="option-info">
                                        <span class="option-title"
                                            >Thanh toán trực tuyến 100%</span
                                        >
                                        <span class="option-desc"
                                            >Trả toàn bộ tiền online nhanh gọn,
                                            giữ chỗ tức thì.</span
                                        >
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cột phải: Tổng quan đơn đặt -->
                <div class="summary-section">
                    <div class="sticky-card">
                        <div class="card summary-card">
                            <h2>Thông tin đặt sân</h2>
                            <div class="divider"></div>

                            <div class="summary-details">
                                <div class="summary-row">
                                    <span class="label">Cụm sân:</span>
                                    <span class="val">{{
                                        currentCluster?.name || "-"
                                    }}</span>
                                </div>
                                <div class="summary-row">
                                    <span class="label">Sân con:</span>
                                    <span class="val">{{
                                        currentCourt?.name || "-"
                                    }}</span>
                                </div>
                                <div class="summary-row">
                                    <span class="label">Ngày chơi:</span>
                                    <span class="val">{{
                                        formatDate(bookingDate)
                                    }}</span>
                                </div>
                                <div class="summary-row">
                                    <span class="label">Khung giờ:</span>
                                    <span
                                        class="val"
                                        v-if="startTime && endTime"
                                        >{{ startTime }} - {{ endTime }}</span
                                    >
                                    <span class="val" v-else>-</span>
                                </div>
                                <div class="summary-row">
                                    <span class="label">Thời lượng:</span>
                                    <span class="val" v-if="durationMinutes"
                                        >{{ durationMinutes }} phút</span
                                    >
                                    <span class="val" v-else>-</span>
                                </div>
                            </div>

                            <div class="divider"></div>

                            <div class="price-details" v-if="durationMinutes">
                                <div class="summary-row">
                                    <span class="label">Đơn giá:</span>
                                    <span class="val font-semibold"
                                        >{{ formatCurrency(hourlyRate) }} /
                                        giờ</span
                                    >
                                </div>
                                <div
                                    class="summary-row"
                                    v-if="membershipDiscountAmount > 0"
                                >
                                    <span class="label">Giá gốc:</span>
                                    <span class="val">{{
                                        formatCurrency(originalPrice)
                                    }}</span>
                                </div>
                                <div
                                    class="summary-row discount-row"
                                    v-if="membershipDiscountAmount > 0"
                                >
                                    <span class="label">
                                        Giảm hạng {{ membershipTierLabel }}
                                        <small>({{ membershipDiscountPercent }}%)</small>
                                    </span>
                                    <span class="val"
                                        >-{{
                                            formatCurrency(membershipDiscountAmount)
                                        }}</span
                                    >
                                </div>
                                <div class="summary-row total-row">
                                    <span class="label">Tổng tiền:</span>
                                    <span class="val price">{{
                                        formatCurrency(totalPrice)
                                    }}</span>
                                </div>
                                <div
                                    class="summary-row deposit-row"
                                    v-if="paymentOption !== 'no_prepay'"
                                >
                                    <span class="label">Cần trả trước:</span>
                                    <span class="val required-price">{{
                                        formatCurrency(requiredPaymentAmount)
                                    }}</span>
                                </div>
                            </div>

                            <div class="error-msg" v-if="submitError">
                                {{ submitError }}
                            </div>

                            <button
                                class="btn-submit"
                                :disabled="!canSubmit || submitting"
                                @click="submitBooking"
                            >
                                <span
                                    v-if="submitting"
                                    class="spinner-small inline-block"
                                ></span>
                                <span v-else>Xác nhận đặt sân</span>
                            </button>

                            <p
                                class="hold-notice"
                                v-if="paymentOption !== 'no_prepay'"
                            >
                                * Hệ thống sẽ tạm giữ sân trong vòng
                                <strong>{{ config.slot_hold_minutes || 20 }} phút</strong> để bạn thực hiện thanh
                                toán trực tuyến.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div class="loading-state" v-else>
                <div class="spinner"></div>
                <p>Đang tải danh sách sân chơi...</p>
            </div>
        </main>
    </div>
</template>

<script>
import PublicNavbar from "../../../components/PublicNavbar.vue";
import { bookingService } from "../../../services/bookingService.js";
import { getAuth } from "../../../stores/auth.js";

export default {
    name: "BookingForm",
    components: { PublicNavbar },
    data() {
        return {
            clusters: [],
            selectedClusterId: "",
            selectedCourtId: "",
            bookingDate: new Date().toLocaleDateString("en-CA"),
            startTime: "08:00:00",
            endTime: "09:00:00",
            paymentOption: "no_prepay",

            loadingInit: true,
            checkingAvailability: false,
            availabilityChecked: false,
            isAvailable: false,
            submitting: false,
            submitError: null,
            fetchedHourlyRate: 0,
            pricePreview: null,

            selectedScheduleCourtTypeId: "",
            scheduleLoading: false,
            scheduleError: null,
            scheduleSlots: [],
            scheduleCourts: [],
            scheduleBusyIntervals: [],
            scheduleSlotStatuses: [],
            selectedGridCourtId: "",
            selectedSlotIndexes: [],

            timeOptions: [
                "05:00:00",
                "05:30:00",
                "06:00:00",
                "06:30:00",
                "07:00:00",
                "07:30:00",
                "08:00:00",
                "08:30:00",
                "09:00:00",
                "09:30:00",
                "10:00:00",
                "10:30:00",
                "11:00:00",
                "11:30:00",
                "12:00:00",
                "12:30:00",
                "13:00:00",
                "13:30:00",
                "14:00:00",
                "14:30:00",
                "15:00:00",
                "15:30:00",
                "16:00:00",
                "16:30:00",
                "17:00:00",
                "17:30:00",
                "18:00:00",
                "18:30:00",
                "19:00:00",
                "19:30:00",
                "20:00:00",
                "20:30:00",
                "21:00:00",
                "21:30:00",
                "22:00:00",
            ],
        };
    },
    computed: {
        minDate() {
            return new Date().toLocaleDateString("en-CA");
        },
        currentCluster() {
            return this.clusters.find((c) => c.id === this.selectedClusterId);
        },
        availableCourts() {
            return this.currentCluster?.venue_courts || [];
        },
        currentCourt() {
            return this.availableCourts.find(
                (c) => c.id === this.selectedCourtId,
            );
        },
        clusterCourtTypes() {
            const map = new Map();
            this.availableCourts.forEach((court) => {
                if (court.court_type?.id) {
                    map.set(String(court.court_type.id), {
                        id: court.court_type.id,
                        name: court.court_type.name,
                    });
                }
            });

            return [...map.values()].sort((a, b) =>
                a.name.localeCompare(b.name, "vi"),
            );
        },
        startTimeOptions() {
            return this.timeOptions.slice(0, -1);
        },
        endTimeOptions() {
            return this.timeOptions.slice(1);
        },
        scheduleGridStyle() {
            return {
                gridTemplateColumns: `132px repeat(${this.scheduleSlots.length}, 36px)`,
            };
        },
        config() {
            return (
                this.currentCluster?.booking_config || {
                    allow_full_payment: true,
                    allow_deposit: true,
                    allow_no_prepay: true,
                    deposit_percent: 30,
                    slot_hold_minutes: 20,
                    reminder_before_minutes: 30,
                }
            );
        },
        durationMinutes() {
            if (!this.startTime || !this.endTime) return 0;
            const startParts = this.startTime.split(":").map(Number);
            const endParts = this.endTime.split(":").map(Number);
            const diff =
                endParts[0] * 60 +
                endParts[1] -
                (startParts[0] * 60 + startParts[1]);
            return diff > 0 ? diff : 0;
        },
        selectedSlotDetails() {
            if (
                !this.selectedGridCourtId ||
                this.selectedSlotIndexes.length === 0
            )
                return [];

            return this.selectedSlotIndexes
                .map((index) => this.scheduleSlots[index])
                .filter(Boolean)
                .map((slot) => this.slotStatus(this.selectedGridCourtId, slot))
                .filter(Boolean);
        },
        hourlyRate() {
            return this.fetchedHourlyRate > 0 ? this.fetchedHourlyRate : 10000;
        },
        basePrice() {
            if (this.selectedSlotDetails.length > 0) {
                return this.selectedSlotDetails.reduce(
                    (sum, slot) => sum + Number(slot.price || 0),
                    0,
                );
            }

            return (this.durationMinutes / 60) * this.hourlyRate;
        },
        originalPrice() {
            return Number(this.pricePreview?.original_amount ?? this.basePrice);
        },
        membershipDiscount() {
            return this.pricePreview?.membership_discount || null;
        },
        membershipDiscountAmount() {
            return Number(
                this.pricePreview?.membership_discount_amount ??
                    this.membershipDiscount?.discount_amount ??
                    0,
            );
        },
        membershipTierLabel() {
            return this.membershipDiscount?.tier_label || "thành viên";
        },
        membershipDiscountPercent() {
            return Number(this.membershipDiscount?.discount_percent || 0);
        },
        totalPrice() {
            return Number(
                this.pricePreview?.final_amount ??
                    Math.max(this.basePrice - this.membershipDiscountAmount, 0),
            );
        },
        requiredPaymentAmount() {
            if (this.paymentOption === "full_payment") {
                return this.totalPrice;
            }
            if (this.paymentOption === "deposit") {
                const percent = this.config.deposit_percent || 30;
                return this.totalPrice * (percent / 100);
            }
            return 0;
        },
        canSubmit() {
            return (
                this.selectedClusterId &&
                this.selectedCourtId &&
                this.bookingDate &&
                this.startTime &&
                this.endTime &&
                this.durationMinutes > 0 &&
                this.isAvailable &&
                !this.checkingAvailability
            );
        },
    },
    async mounted() {
        // Check login state
        const auth = getAuth();
        if (!auth) {
            this.$router.push("/login");
            return;
        }

        try {
            const res = await bookingService.getInitData();
            this.clusters = res.clusters || [];
            if (this.clusters.length > 0) {
                this.selectedClusterId = this.clusters[0].id;
                this.onClusterChange();
            }
        } catch (err) {
            console.error(err);
        } finally {
            this.loadingInit = false;
        }
    },
    methods: {
        onClusterChange() {
            this.selectedCourtId = "";
            this.selectedScheduleCourtTypeId = "";
            this.isAvailable = false;
            this.availabilityChecked = false;
            this.clearGridSelection();
            if (this.availableCourts.length > 0) {
                this.selectedCourtId = this.availableCourts[0].id;
                this.checkAvailability();
            }
            this.loadSchedule();
        },
        onDateChange() {
            this.clearGridSelection();
            this.checkAvailability();
            this.loadSchedule();
        },
        onScheduleCourtTypeChange() {
            this.clearGridSelection();
            this.loadSchedule();
        },
        onTimeChange() {
            this.clearGridSelection();
            this.checkAvailability();
        },
        async loadSchedule() {
            if (!this.selectedClusterId || !this.bookingDate) return;

            this.scheduleLoading = true;
            this.scheduleError = null;

            try {
                const params = {
                    venue_cluster_id: this.selectedClusterId,
                    booking_date: this.bookingDate,
                };

                if (this.selectedScheduleCourtTypeId) {
                    params.court_type_id = this.selectedScheduleCourtTypeId;
                }

                const res = await bookingService.getSchedule(params);
                this.scheduleSlots = res.time_slots || [];
                this.scheduleCourts = res.courts || [];
                this.scheduleBusyIntervals = res.busy_intervals || [];
                this.scheduleSlotStatuses = res.slot_statuses || [];
            } catch (err) {
                this.scheduleError = err.message || "Không thể tải lịch trống.";
            } finally {
                this.scheduleLoading = false;
            }
        },
        slotStatus(courtId, slot) {
            return this.scheduleSlotStatuses.find(
                (status) =>
                    status.venue_court_id === courtId &&
                    status.start_time === slot.start_time,
            );
        },
        isSlotBusy(courtId, slot) {
            if (this.isSlotPast(slot)) return true;

            const status = this.slotStatus(courtId, slot);
            if (status) return !status.is_available;

            const slotStart = this.timeToMinutes(slot.start_time);
            const slotEnd = this.timeToMinutes(slot.end_time);

            return this.scheduleBusyIntervals.some((interval) => {
                if (interval.venue_court_id !== courtId) return false;

                const busyStart = this.timeToMinutes(interval.start_time);
                const busyEnd = this.timeToMinutes(interval.end_time);
                return busyStart < slotEnd && busyEnd > slotStart;
            });
        },
        isSlotSelected(courtId, index) {
            return (
                this.selectedGridCourtId === courtId &&
                this.selectedSlotIndexes.includes(index)
            );
        },
        async selectScheduleSlot(court, index) {
            const slot = this.scheduleSlots[index];
            if (!slot || this.isSlotBusy(court.id, slot)) return;

            let nextIndexes = [index];
            if (
                this.selectedGridCourtId === court.id &&
                this.selectedSlotIndexes.length > 0
            ) {
                const min = Math.min(...this.selectedSlotIndexes);
                const max = Math.max(...this.selectedSlotIndexes);

                if (index === max + 1) {
                    nextIndexes = this.range(min, index);
                } else if (index === min - 1) {
                    nextIndexes = this.range(index, max);
                }

                if (!this.isRangeFree(court.id, nextIndexes)) {
                    nextIndexes = [index];
                }
            }

            this.selectedGridCourtId = court.id;
            this.selectedSlotIndexes = nextIndexes;
            this.selectedCourtId = court.id;

            const firstIndex = Math.min(...nextIndexes);
            const lastIndex = Math.max(...nextIndexes);
            this.startTime = this.scheduleSlots[firstIndex].start_time;
            this.endTime = this.scheduleSlots[lastIndex].end_time;

            await this.checkAvailability();
        },
        isRangeFree(courtId, indexes) {
            return indexes.every((index) => {
                const slot = this.scheduleSlots[index];
                return slot && !this.isSlotBusy(courtId, slot);
            });
        },
        range(start, end) {
            return Array.from(
                { length: end - start + 1 },
                (_, offset) => start + offset,
            );
        },
        slotTitle(court, slot, index) {
            if (this.isSlotPast(slot))
                return `${court.name}: ${slot.label} đã qua giờ đặt`;
            if (this.isSlotBusy(court.id, slot))
                return `${court.name}: ${slot.label} đã bận`;
            if (this.isSlotSelected(court.id, index))
                return `${court.name}: ${slot.label} đang chọn`;
            return `${court.name}: ${slot.label} còn trống`;
        },
        clearGridSelection() {
            this.selectedGridCourtId = "";
            this.selectedSlotIndexes = [];
        },
        timeToMinutes(time) {
            const [hour, minute] = (time || "00:00")
                .slice(0, 5)
                .split(":")
                .map(Number);
            return hour * 60 + minute;
        },
        isSlotPast(slot) {
            if (!slot || this.bookingDate !== this.minDate) return false;

            const now = new Date();
            const currentMinutes = now.getHours() * 60 + now.getMinutes();

            return this.timeToMinutes(slot.start_time) <= currentMinutes;
        },
        async checkAvailability() {
            if (
                !this.selectedCourtId ||
                !this.bookingDate ||
                !this.startTime ||
                !this.endTime
            )
                return;

            const diff =
                this.timeToMinutes(this.endTime) -
                this.timeToMinutes(this.startTime);

            if (diff <= 0) {
                this.isAvailable = false;
                this.availabilityChecked = true;
                this.pricePreview = null;
                return;
            }

            this.checkingAvailability = true;
            this.submitError = null;

            try {
                const res = await bookingService.checkAvailability({
                    venue_court_id: this.selectedCourtId,
                    booking_date: this.bookingDate,
                    start_time: this.startTime,
                    end_time: this.endTime,
                });
                this.isAvailable = res.available;
                this.fetchedHourlyRate = res.hourly_rate || 0;
                this.pricePreview = res.available
                    ? {
                          ...(res.price_preview || {}),
                          membership_discount: res.membership_discount || null,
                      }
                    : null;

                // Auto select allowed payment option if current becomes invalid
                if (this.isAvailable) {
                    if (
                        this.paymentOption === "no_prepay" &&
                        !this.config.allow_no_prepay
                    ) {
                        this.paymentOption = this.config.allow_deposit
                            ? "deposit"
                            : "full_payment";
                    } else if (
                        this.paymentOption === "deposit" &&
                        !this.config.allow_deposit
                    ) {
                        this.paymentOption = this.config.allow_full_payment
                            ? "full_payment"
                            : "no_prepay";
                    } else if (
                        this.paymentOption === "full_payment" &&
                        !this.config.allow_full_payment
                    ) {
                        this.paymentOption = this.config.allow_deposit
                            ? "deposit"
                            : "no_prepay";
                    }
                }
            } catch (err) {
                console.error(err);
                this.isAvailable = false;
                this.pricePreview = null;
            } finally {
                this.checkingAvailability = false;
                this.availabilityChecked = true;
            }
        },
        async submitBooking() {
            if (!this.canSubmit) return;

            this.submitting = true;
            this.submitError = null;

            try {
                const res = await bookingService.createBooking({
                    venue_court_id: this.selectedCourtId,
                    booking_date: this.bookingDate,
                    start_time: this.startTime,
                    end_time: this.endTime,
                    payment_option: this.paymentOption,
                });

                // Chuyển hướng sang trang chi tiết đặt chỗ
                this.$router.push({
                    name: "booking-detail",
                    params: { id: res.id },
                });
            } catch (err) {
                this.submitError =
                    err.message || "Có lỗi xảy ra khi gửi yêu cầu đặt sân.";
            } finally {
                this.submitting = false;
            }
        },
        formatDate(dateStr) {
            if (!dateStr) return "";
            const [year, month, day] = dateStr.split("-");
            return `${day}/${month}/${year}`;
        },
        formatCurrency(val) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(val || 0);
        },
    },
};
</script>

<style scoped>
.booking-container {
    min-height: 100vh;
    background: var(--sg-surface);
    overflow-x: hidden;
}

.booking-main {
    width: min(100%, 1440px);
    margin: 0 auto;
    padding: 88px 16px 36px;
}

.booking-header {
    margin-bottom: 32px;
}

.page-title {
    font-size: 32px;
    font-weight: 800;
    color: var(--sg-dark);
    letter-spacing: -0.5px;
}

.page-desc {
    font-size: 15px;
    color: var(--sg-text-muted);
    margin-top: 8px;
}

.booking-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 260px;
    gap: 20px;
    align-items: start;
}

.form-section,
.summary-section {
    min-width: 0;
}

.card {
    background: var(--sg-white);
    border-radius: var(--sg-radius);
    border: 1px solid var(--sg-border);
    padding: 18px;
    margin-bottom: 18px;
    box-shadow: var(--sg-shadow);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.card-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--sg-green);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}

.card-header h2 {
    font-size: 18px;
    font-weight: 700;
    color: var(--sg-dark);
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--sg-text);
    margin-bottom: 6px;
}

.form-control {
    width: 100%;
    height: 42px;
    border-radius: var(--sg-radius-sm);
    border: 1px solid var(--sg-border);
    padding: 0 14px;
    font-size: 14px;
    color: var(--sg-text);
    background: var(--sg-white);
    transition: var(--sg-transition);
}

.form-control:focus {
    outline: none;
    border-color: var(--sg-green);
    box-shadow: 0 0 0 3px var(--sg-green-pale);
}

.time-range-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.availability-status {
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
}

.schedule-card {
    overflow: hidden;
    min-width: 0;
}

.schedule-header {
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.schedule-header > div:first-child {
    display: flex;
    align-items: center;
    gap: 12px;
}

.schedule-filter {
    width: 220px;
}

.schedule-filter label {
    display: block;
    margin-bottom: 6px;
    color: var(--sg-text);
    font-size: 12px;
    font-weight: 700;
}

.schedule-controls {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 160px 200px;
    gap: 14px;
    margin-bottom: 14px;
    padding: 12px;
    border-radius: var(--sg-radius-sm);
    background: var(--sg-surface);
}

.schedule-legend {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    color: var(--sg-text-muted);
    font-size: 12px;
}

.schedule-legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
}

.schedule-legend i {
    width: 14px;
    height: 14px;
    display: inline-block;
    border-radius: 4px;
    border: 1px solid var(--sg-border);
}

.legend-free {
    background: #fff;
}

.legend-busy {
    background: #e5e7eb;
}

.legend-selected {
    background: var(--sg-green);
}

.schedule-state {
    padding: 28px 16px;
    border-radius: var(--sg-radius-sm);
    background: var(--sg-surface);
    color: var(--sg-text-muted);
    font-size: 13px;
    font-weight: 700;
    text-align: center;
}

.schedule-state.error {
    background: #fef2f2;
    color: var(--sg-danger);
}

.schedule-wrap {
    overflow: auto;
    max-width: 100%;
    border: 1px solid var(--sg-border);
    border-radius: var(--sg-radius-sm);
    background: #fff;
    overscroll-behavior-x: contain;
}

.schedule-grid {
    display: grid;
    min-width: max-content;
}

.schedule-head,
.schedule-court,
.schedule-cell {
    min-height: 32px;
    border-right: 1px solid var(--sg-border);
    border-bottom: 1px solid var(--sg-border);
}

.schedule-head {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    color: #334155;
    font-size: 10px;
    font-weight: 800;
}

.time-head {
    min-width: 36px;
}

.sticky-col {
    position: sticky;
    left: 0;
    z-index: 2;
}

.schedule-court {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
    padding: 6px 8px;
    background: #fff;
}

.schedule-court strong {
    color: var(--sg-dark);
    font-size: 11px;
    font-weight: 800;
}

.schedule-court span {
    color: var(--sg-text-muted);
    font-size: 10px;
    white-space: nowrap;
}

.schedule-cell {
    width: 36px;
    min-width: 36px;
    background: #fff;
    transition:
        background 0.16s ease,
        box-shadow 0.16s ease;
}

.schedule-cell:not(:disabled):hover {
    background: #dcfce7;
    box-shadow: inset 0 0 0 2px var(--sg-green);
}

.schedule-cell.busy {
    background: #e5e7eb;
    cursor: not-allowed;
}

.schedule-cell.past {
    background:
        repeating-linear-gradient(
            -45deg,
            #f1f5f9,
            #f1f5f9 6px,
            #e2e8f0 6px,
            #e2e8f0 12px
        );
    cursor: not-allowed;
}

.schedule-cell.selected {
    background: var(--sg-green);
    box-shadow: inset 0 0 0 2px var(--sg-green-dark);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: var(--sg-radius-sm);
    font-size: 13px;
    font-weight: 600;
}

.status-badge.success {
    background: var(--sg-green-pale);
    color: var(--sg-green-dark);
}

.status-badge.danger {
    background: #fef2f2;
    color: var(--sg-danger);
}

.payment-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.payment-option-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border-radius: var(--sg-radius-sm);
    border: 1px solid var(--sg-border);
    cursor: pointer;
    transition: var(--sg-transition);
}

.payment-option-card:hover {
    background: var(--sg-surface);
    border-color: var(--sg-green-light);
}

.payment-option-card.active {
    background: var(--sg-green-pale);
    border-color: var(--sg-green);
}

.option-info {
    display: flex;
    flex-direction: column;
}

.option-title {
    font-weight: 700;
    font-size: 14px;
    color: var(--sg-dark);
}

.option-desc {
    font-size: 12px;
    color: var(--sg-text-muted);
    margin-top: 4px;
}

.hidden-radio {
    display: none;
}

/* Summary Panel */
.sticky-card {
    position: sticky;
    top: 84px;
}

.summary-card h2 {
    font-size: 18px;
    font-weight: 700;
    color: var(--sg-dark);
    margin-bottom: 16px;
}

.divider {
    height: 1px;
    background: var(--sg-border);
    margin: 16px 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 14px;
}

.summary-row .label {
    color: var(--sg-text-muted);
}

.summary-row .val {
    font-weight: 600;
    color: var(--sg-dark);
}

.total-row {
    margin-top: 16px;
    font-size: 16px;
}

.total-row .price {
    font-size: 20px;
    font-weight: 800;
    color: var(--sg-dark);
}

.discount-row .label,
.discount-row .val {
    color: #047857;
    font-weight: 800;
}

.discount-row small {
    color: #059669;
    font-size: 11px;
    font-weight: 800;
}

.deposit-row {
    margin-top: 8px;
    font-size: 14px;
}

.deposit-row .required-price {
    font-size: 16px;
    font-weight: 800;
    color: var(--sg-green-dark);
}

.btn-submit {
    width: 100%;
    height: 48px;
    border-radius: var(--sg-radius);
    background: var(--sg-green);
    color: #fff;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 24px;
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
    transition: var(--sg-transition);
}

.btn-submit:hover:not(:disabled) {
    background: var(--sg-green-dark);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(34, 197, 94, 0.4);
}

.btn-submit:disabled {
    background: var(--sg-border);
    color: var(--sg-text-muted);
    box-shadow: none;
    cursor: not-allowed;
}

.hold-notice {
    font-size: 12px;
    color: var(--sg-text-muted);
    margin-top: 14px;
    line-height: 1.5;
    text-align: center;
}

.error-msg {
    padding: 10px 14px;
    background: #fef2f2;
    border-radius: var(--sg-radius-sm);
    color: var(--sg-danger);
    font-size: 13px;
    font-weight: 500;
    margin-top: 14px;
    border: 1px solid rgba(239, 68, 68, 0.1);
}

/* Loading state */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 0;
    color: var(--sg-text-muted);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--sg-border);
    border-top-color: var(--sg-green);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 16px;
}

.spinner-small {
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 900px) {
    .booking-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    .sticky-card {
        position: static;
    }
    .schedule-controls {
        grid-template-columns: 1fr;
    }
}
</style>

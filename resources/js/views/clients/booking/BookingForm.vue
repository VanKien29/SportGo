<template>
    <div class="booking-container">
        <PublicNavbar theme="dark" />

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
                                <div
                                    class="summary-row discount-row"
                                    v-if="venueVoucherDiscountAmount > 0"
                                >
                                    <span class="label">
                                        Voucher sân
                                        <small>({{ selectedVenueVoucher?.code }})</small>
                                    </span>
                                    <span class="val"
                                        >-{{
                                            formatCurrency(venueVoucherDiscountAmount)
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="summary-row discount-row"
                                    v-if="vipVoucherDiscountAmount > 0"
                                >
                                    <span class="label">
                                        Voucher VIP
                                        <small>({{ selectedVipVoucher?.code }})</small>
                                    </span>
                                    <span class="val"
                                        >-{{
                                            formatCurrency(vipVoucherDiscountAmount)
                                        }}</span
                                    >
                                </div>
                                <button
                                    type="button"
                                    class="btn-voucher-summary"
                                    :disabled="!isAvailable || voucherLoading"
                                    @click="openVoucherModal"
                                >
                                    <span>
                                        {{
                                            selectedVoucherCount > 0
                                                ? `Đổi voucher (${selectedVoucherCount})`
                                                : "Chọn voucher"
                                        }}
                                    </span>
                                    <strong v-if="voucherTotalDiscountAmount > 0">
                                        -{{ formatCurrency(voucherTotalDiscountAmount) }}
                                    </strong>
                                    <small v-else-if="voucherLoading">
                                        Đang tải...
                                    </small>
                                    <small v-else>
                                        {{ totalEligibleVoucherCount }} mã phù hợp
                                    </small>
                                </button>
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

        <div
            v-if="voucherModalOpen"
            class="voucher-modal-backdrop"
            @click.self="voucherModalOpen = false"
        >
            <div class="voucher-modal">
                <div class="voucher-modal-header">
                    <div>
                        <h2>Chọn voucher</h2>
                        <p>{{ totalEligibleVoucherCount }} mã phù hợp</p>
                    </div>
                    <button
                        type="button"
                        class="voucher-modal-close"
                        @click="voucherModalOpen = false"
                    >
                        ×
                    </button>
                </div>

                <div v-if="voucherLoading" class="voucher-state">
                    Đang tải voucher phù hợp...
                </div>
                <div v-else-if="voucherError" class="voucher-state error">
                    {{ voucherError }}
                </div>
                <div v-else class="voucher-table-wrap">
                    <section class="voucher-table-section">
                        <div class="voucher-table-title">
                            <strong>Voucher sân</strong>
                            <button
                                type="button"
                                :disabled="!selectedVenueVoucherId"
                                @click="selectedVenueVoucherId = ''"
                            >
                                Không dùng
                            </button>
                        </div>
                        <table class="voucher-table">
                            <thead>
                                <tr>
                                    <th>Mã</th>
                                    <th>Tên voucher</th>
                                    <th>Giảm</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="voucher in sortedVenueVouchers"
                                    :key="voucher.id"
                                    :class="{
                                        active:
                                            selectedVenueVoucherId ===
                                            voucher.id,
                                    }"
                                >
                                    <td>
                                        <strong>{{ voucher.code }}</strong>
                                    </td>
                                    <td>
                                        <span>{{ voucher.name || voucher.code }}</span>
                                        <small>{{ voucher.discount_label }}</small>
                                    </td>
                                    <td class="voucher-table-discount">
                                        -{{ formatCurrency(discountForVoucher(voucher, amountAfterMembership)) }}
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="voucher-select-btn"
                                            @click="selectedVenueVoucherId = voucher.id"
                                        >
                                            {{
                                                selectedVenueVoucherId ===
                                                voucher.id
                                                    ? "Đã chọn"
                                                    : "Chọn"
                                            }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="sortedVenueVouchers.length === 0">
                                    <td colspan="4" class="voucher-table-empty">
                                        Chưa có voucher sân phù hợp.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="voucher-table-section">
                        <div class="voucher-table-title">
                            <strong>Voucher VIP</strong>
                            <button
                                type="button"
                                :disabled="!selectedVipVoucherId"
                                @click="selectedVipVoucherId = ''"
                            >
                                Không dùng
                            </button>
                        </div>
                        <table class="voucher-table">
                            <thead>
                                <tr>
                                    <th>Mã</th>
                                    <th>Tên voucher</th>
                                    <th>Giảm</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="voucher in sortedVipVouchers"
                                    :key="voucher.id"
                                    :class="{
                                        active:
                                            selectedVipVoucherId ===
                                            voucher.id,
                                    }"
                                >
                                    <td>
                                        <strong>{{ voucher.code }}</strong>
                                    </td>
                                    <td>
                                        <span>{{ voucher.name || voucher.code }}</span>
                                        <small>{{ voucher.discount_label }}</small>
                                    </td>
                                    <td class="voucher-table-discount">
                                        -{{ formatCurrency(discountForVoucher(voucher, amountAfterVenueVoucher)) }}
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="voucher-select-btn"
                                            @click="selectedVipVoucherId = voucher.id"
                                        >
                                            {{
                                                selectedVipVoucherId ===
                                                voucher.id
                                                    ? "Đã chọn"
                                                    : "Chọn"
                                            }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="sortedVipVouchers.length === 0">
                                    <td colspan="4" class="voucher-table-empty">
                                        Chưa có voucher VIP phù hợp.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>

                <div class="voucher-modal-footer">
                    <span v-if="voucherTotalDiscountAmount > 0">
                        Đang giảm {{ formatCurrency(voucherTotalDiscountAmount) }}
                    </span>
                    <span v-else>Chưa áp dụng voucher</span>
                    <button type="button" @click="voucherModalOpen = false">
                        Xong
                    </button>
                </div>
            </div>
        </div>
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
            voucherLoading: false,
            voucherError: "",
            eligibleVenueVouchers: [],
            eligibleVipVouchers: [],
            selectedVenueVoucherId: "",
            selectedVipVoucherId: "",
            voucherModalOpen: false,

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
        amountAfterMembership() {
            return Number(
                this.pricePreview?.final_amount ??
                    Math.max(this.basePrice - this.membershipDiscountAmount, 0),
            );
        },
        selectedVenueVoucher() {
            return (
                this.eligibleVenueVouchers.find(
                    (voucher) => voucher.id === this.selectedVenueVoucherId,
                ) || null
            );
        },
        selectedVipVoucher() {
            return (
                this.eligibleVipVouchers.find(
                    (voucher) => voucher.id === this.selectedVipVoucherId,
                ) || null
            );
        },
        sortedVenueVouchers() {
            return [...this.eligibleVenueVouchers].sort(
                (a, b) =>
                    this.discountForVoucher(b, this.amountAfterMembership) -
                    this.discountForVoucher(a, this.amountAfterMembership),
            );
        },
        sortedVipVouchers() {
            return [...this.eligibleVipVouchers].sort(
                (a, b) =>
                    this.discountForVoucher(b, this.amountAfterVenueVoucher) -
                    this.discountForVoucher(a, this.amountAfterVenueVoucher),
            );
        },
        totalEligibleVoucherCount() {
            return (
                this.eligibleVenueVouchers.length +
                this.eligibleVipVouchers.length
            );
        },
        selectedVoucherCount() {
            return (
                (this.selectedVenueVoucherId ? 1 : 0) +
                (this.selectedVipVoucherId ? 1 : 0)
            );
        },
        venueVoucherDiscountAmount() {
            return this.discountForVoucher(
                this.selectedVenueVoucher,
                this.amountAfterMembership,
            );
        },
        amountAfterVenueVoucher() {
            return Math.max(
                this.amountAfterMembership - this.venueVoucherDiscountAmount,
                0,
            );
        },
        vipVoucherDiscountAmount() {
            return this.discountForVoucher(
                this.selectedVipVoucher,
                this.amountAfterVenueVoucher,
            );
        },
        voucherTotalDiscountAmount() {
            return (
                this.venueVoucherDiscountAmount +
                this.vipVoucherDiscountAmount
            );
        },
        totalPrice() {
            return Math.max(
                this.amountAfterVenueVoucher - this.vipVoucherDiscountAmount,
                0,
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
            
            const queryCluster = this.$route.query.cluster;
            const queryDate = this.$route.query.date;
            const queryCourtType = this.$route.query.court_type;

            if (queryDate) {
                this.bookingDate = queryDate;
            }

            if (queryCluster && this.clusters.some(c => String(c.id) === String(queryCluster))) {
                this.selectedClusterId = Number(queryCluster);
            } else if (this.clusters.length > 0) {
                this.selectedClusterId = this.clusters[0].id;
            }

            if (this.selectedClusterId) {
                if (queryCourtType) {
                    this.selectedScheduleCourtTypeId = String(queryCourtType);
                }
                this.onClusterChange(!!queryCourtType);
            }
        } catch (err) {
            console.error(err);
        } finally {
            this.loadingInit = false;
        }
    },
    methods: {
        onClusterChange(keepCourtType = false) {
            this.selectedCourtId = "";
            if (!keepCourtType) {
                this.selectedScheduleCourtTypeId = "";
            }
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
                this.clearVoucherSelection();
                return;
            }

            this.checkingAvailability = true;
            this.submitError = null;
            this.clearVoucherSelection();

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

                    await this.loadEligibleVouchers();
                }
            } catch (err) {
                console.error(err);
                this.isAvailable = false;
                this.pricePreview = null;
                this.clearVoucherSelection();
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
                    venue_voucher_id: this.selectedVenueVoucherId || null,
                    vip_voucher_id: this.selectedVipVoucherId || null,
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
        async loadEligibleVouchers() {
            if (
                !this.selectedCourtId ||
                !this.bookingDate ||
                !this.startTime ||
                !this.endTime ||
                !this.isAvailable
            ) {
                this.clearVoucherSelection();
                return;
            }

            this.voucherLoading = true;
            this.voucherError = "";

            try {
                const res = await bookingService.eligibleVouchers({
                    venue_court_id: this.selectedCourtId,
                    booking_date: this.bookingDate,
                    start_time: this.startTime,
                    end_time: this.endTime,
                });

                this.eligibleVenueVouchers = res.venue_vouchers || [];
                this.eligibleVipVouchers = res.vip_vouchers || [];

                if (!this.selectedVenueVoucher) {
                    this.selectedVenueVoucherId = "";
                }
                if (!this.selectedVipVoucher) {
                    this.selectedVipVoucherId = "";
                }
            } catch (err) {
                this.voucherError =
                    err.message || "Không thể tải danh sách voucher phù hợp.";
                this.eligibleVenueVouchers = [];
                this.eligibleVipVouchers = [];
                this.selectedVenueVoucherId = "";
                this.selectedVipVoucherId = "";
            } finally {
                this.voucherLoading = false;
            }
        },
        async openVoucherModal() {
            if (!this.isAvailable) return;

            this.voucherModalOpen = true;
            if (
                !this.voucherLoading &&
                this.totalEligibleVoucherCount === 0 &&
                !this.voucherError
            ) {
                await this.loadEligibleVouchers();
            }
        },
        clearVoucherSelection() {
            this.voucherError = "";
            this.eligibleVenueVouchers = [];
            this.eligibleVipVouchers = [];
            this.selectedVenueVoucherId = "";
            this.selectedVipVoucherId = "";
            this.voucherModalOpen = false;
        },
        discountForVoucher(voucher, amount) {
            const baseAmount = Number(amount || 0);
            if (!voucher || baseAmount <= 0) return 0;
            if (Number(voucher.min_order_amount || 0) > baseAmount) return 0;

            let discount = 0;
            if (voucher.discount_type === "percent") {
                discount =
                    baseAmount * (Number(voucher.discount_value || 0) / 100);
                if (voucher.max_discount_amount !== null) {
                    discount = Math.min(
                        discount,
                        Number(voucher.max_discount_amount || 0),
                    );
                }
            } else {
                discount = Number(voucher.discount_value || 0);
            }

            return Math.max(Math.min(discount, baseAmount), 0);
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
    background: #09090b;
    color: #ffffff;
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
    color: #ffffff;
    letter-spacing: -0.5px;
}

.page-desc {
    font-size: 15px;
    color: rgba(255, 255, 255, 0.4);
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
    background: rgba(255, 255, 255, 0.03);
    border-radius: var(--sg-radius);
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 18px;
    margin-bottom: 18px;
    box-shadow: none;
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
    background: #ffffff;
    color: #09090b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}

.card-header h2 {
    font-size: 18px;
    font-weight: 700;
    color: #ffffff;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    margin-bottom: 6px;
}

.form-control {
    width: 100%;
    height: 42px;
    border-radius: var(--sg-radius-sm);
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 0 14px;
    font-size: 14px;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.04);
    transition: var(--sg-transition);
}

.form-control:focus {
    outline: none;
    border-color: rgba(255, 255, 255, 0.25);
    box-shadow: none;
}

.form-control option {
    background: #18181b;
    color: #ffffff;
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
    color: #ffffff;
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
    background: rgba(255, 255, 255, 0.02);
}

.schedule-legend {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    color: rgba(255, 255, 255, 0.4);
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
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.legend-free {
    background: rgba(255, 255, 255, 0.03);
}

.legend-busy {
    background: rgba(255, 255, 255, 0.1);
}

.legend-selected {
    background: #ffffff;
}

.schedule-state {
    padding: 28px 16px;
    border-radius: var(--sg-radius-sm);
    background: rgba(255, 255, 255, 0.02);
    color: rgba(255, 255, 255, 0.4);
    font-size: 13px;
    font-weight: 700;
    text-align: center;
}

.schedule-state.error {
    background: rgba(239, 68, 68, 0.05);
    color: var(--sg-danger);
}

.schedule-wrap {
    overflow: auto;
    max-width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: var(--sg-radius-sm);
    background: rgba(255, 255, 255, 0.02);
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
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.schedule-head {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.6);
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
    background: rgba(255, 255, 255, 0.02);
}

.schedule-court strong {
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
}

.schedule-court span {
    color: rgba(255, 255, 255, 0.4);
    font-size: 10px;
    white-space: nowrap;
}

.schedule-cell {
    width: 36px;
    background: rgba(255, 255, 255, 0.03);
    border: none;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    transition: background 0.15s;
}

.schedule-cell:hover:not(.busy) {
    background: rgba(255, 255, 255, 0.15);
}

.schedule-cell.busy {
    background: rgba(255, 255, 255, 0.08);
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
    background: #ffffff;
    box-shadow: inset 0 0 0 2px #ffffff;
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
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

.status-badge.danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--sg-danger);
}

.voucher-picker-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.voucher-column {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.voucher-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    font-size: 13px;
    color: var(--sg-dark);
}

.voucher-heading span {
    color: var(--sg-text-muted);
    font-weight: 700;
}

.voucher-card {
    width: 100%;
    min-height: 78px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 4px 12px;
    align-items: center;
    padding: 12px;
    border-radius: var(--sg-radius-sm);
    border: 1px solid var(--sg-border);
    background: #fff;
    text-align: left;
    transition: var(--sg-transition);
}

.voucher-card:hover {
    background: var(--sg-surface);
    border-color: var(--sg-green-light);
}

.voucher-card.active {
    background: var(--sg-green-pale);
    border-color: var(--sg-green);
}

.voucher-name {
    min-width: 0;
    color: var(--sg-dark);
    font-size: 13px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.voucher-meta {
    min-width: 0;
    color: var(--sg-text-muted);
    font-size: 12px;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.voucher-discount {
    grid-row: 1 / span 2;
    grid-column: 2;
    color: #047857;
    font-size: 13px;
    font-weight: 900;
    white-space: nowrap;
}

.voucher-empty,
.voucher-state {
    margin: 0;
    padding: 14px;
    border-radius: var(--sg-radius-sm);
    background: var(--sg-surface);
    color: var(--sg-text-muted);
    font-size: 13px;
    font-weight: 700;
}

.voucher-state.error {
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
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.02);
    cursor: pointer;
    transition: var(--sg-transition);
}

.payment-option-card:hover {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.2);
}

.payment-option-card.active {
    background: rgba(255, 255, 255, 0.08);
    border-color: #ffffff;
}

.option-info {
    display: flex;
    flex-direction: column;
}

.option-title {
    font-weight: 700;
    font-size: 14px;
    color: #ffffff;
}

.option-desc {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.4);
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
    color: #ffffff;
    margin-bottom: 16px;
}

.divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.08);
    margin: 16px 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 14px;
}

.summary-row .label {
    color: rgba(255, 255, 255, 0.4);
}

.summary-row .val {
    font-weight: 600;
    color: #ffffff;
}

.total-row {
    margin-top: 16px;
    font-size: 16px;
}

.total-row .price {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
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
    color: #ffffff;
}

.btn-voucher-summary {
    width: 100%;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    margin: 4px 0 12px;
    border-radius: var(--sg-radius-sm);
    border: 1px solid var(--sg-green-light);
    background: var(--sg-green-pale);
    color: var(--sg-dark);
    text-align: left;
    transition: var(--sg-transition);
}

.btn-voucher-summary:hover:not(:disabled) {
    border-color: var(--sg-green);
    box-shadow: 0 4px 14px rgba(34, 197, 94, 0.12);
}

.btn-voucher-summary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-voucher-summary span,
.btn-voucher-summary strong {
    font-size: 13px;
    font-weight: 900;
}

.btn-voucher-summary strong {
    color: #047857;
    white-space: nowrap;
}

.btn-voucher-summary small {
    color: var(--sg-text-muted);
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.btn-submit {
    width: 100%;
    height: 48px;
    border-radius: var(--sg-radius);
    background: #ffffff;
    color: #09090b;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 24px;
    box-shadow: none;
    transition: var(--sg-transition);
}

.btn-submit:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.88);
    transform: translateY(-1px);
}

.btn-submit:disabled {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.3);
    box-shadow: none;
    cursor: not-allowed;
}

.hold-notice {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.3);
    margin-top: 14px;
    line-height: 1.5;
    text-align: center;
}

.error-msg {
    padding: 10px 14px;
    background: rgba(239, 68, 68, 0.05);
    border-radius: var(--sg-radius-sm);
    color: var(--sg-danger);
    font-size: 13px;
    font-weight: 500;
    margin-top: 14px;
}

.voucher-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(15, 23, 42, 0.45);
}

.voucher-modal {
    width: min(860px, 100%);
    max-height: min(760px, calc(100vh - 36px));
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: var(--sg-radius);
    background: #fff;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
}

.voucher-modal-header,
.voucher-modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 18px;
    border-bottom: 1px solid var(--sg-border);
}

.voucher-modal-header h2 {
    margin: 0;
    color: var(--sg-dark);
    font-size: 18px;
    font-weight: 900;
}

.voucher-modal-header p,
.voucher-modal-footer span {
    margin: 4px 0 0;
    color: var(--sg-text-muted);
    font-size: 12px;
    font-weight: 800;
}

.voucher-modal-close {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    color: var(--sg-text-muted);
    font-size: 28px;
    line-height: 1;
}

.voucher-modal-footer {
    border-top: 1px solid var(--sg-border);
    border-bottom: 0;
}

.voucher-modal-footer button {
    min-width: 86px;
    height: 38px;
    border-radius: var(--sg-radius-sm);
    background: var(--sg-green);
    color: #fff;
    font-size: 13px;
    font-weight: 900;
}

.voucher-table-wrap {
    overflow: auto;
    padding: 14px 18px 18px;
}

.voucher-table-section + .voucher-table-section {
    margin-top: 18px;
}

.voucher-table-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}

.voucher-table-title strong {
    color: var(--sg-dark);
    font-size: 14px;
    font-weight: 900;
}

.voucher-table-title button {
    color: var(--sg-green-dark);
    font-size: 12px;
    font-weight: 900;
}

.voucher-table-title button:disabled {
    color: var(--sg-text-muted);
    cursor: not-allowed;
}

.voucher-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    border: 1px solid var(--sg-border);
    border-radius: var(--sg-radius-sm);
    overflow: hidden;
}

.voucher-table th,
.voucher-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--sg-border);
    color: var(--sg-text);
    font-size: 12px;
    text-align: left;
    vertical-align: middle;
}

.voucher-table th {
    background: var(--sg-surface);
    color: var(--sg-text-muted);
    font-weight: 900;
}

.voucher-table tr:last-child td {
    border-bottom: 0;
}

.voucher-table tr.active td {
    background: var(--sg-green-pale);
}

.voucher-table td span,
.voucher-table td small {
    display: block;
}

.voucher-table td span {
    color: var(--sg-dark);
    font-weight: 800;
}

.voucher-table td small {
    margin-top: 3px;
    color: var(--sg-text-muted);
    font-weight: 700;
}

.voucher-table-discount {
    color: #047857 !important;
    font-weight: 900;
    white-space: nowrap;
}

.voucher-select-btn {
    min-width: 76px;
    height: 32px;
    border-radius: var(--sg-radius-sm);
    border: 1px solid var(--sg-green-light);
    color: var(--sg-green-dark);
    font-size: 12px;
    font-weight: 900;
}

.voucher-table tr.active .voucher-select-btn {
    background: var(--sg-green);
    color: #fff;
    border-color: var(--sg-green);
}

.voucher-table-empty {
    color: var(--sg-text-muted) !important;
    font-weight: 800;
    text-align: center !important;
}

/* Loading state */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 0;
    color: rgba(255, 255, 255, 0.4);
}

.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(255, 255, 255, 0.1);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 16px;
}

.spinner-small {
    width: 18px;
    height: 18px;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-top-color: #000000;
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
    .voucher-picker-grid {
        grid-template-columns: 1fr;
    }
    .voucher-modal-backdrop {
        align-items: flex-end;
        padding: 10px;
    }
    .voucher-modal {
        max-height: calc(100vh - 20px);
    }
    .voucher-table {
        min-width: 620px;
    }
}
</style>

<template>
    <div class="booking-container">
        <PublicNavbar />

        <main class="booking-main">
            <header class="booking-hero" v-if="!loadingInit">
                <div>
                    <router-link to="/venues" class="booking-breadcrumb">
                        Danh sách sân
                    </router-link>
                    <span>/</span>
                    <strong>Đặt sân</strong>
                </div>
                <h1>{{ currentCluster?.name || "Đặt sân trực tuyến" }}</h1>
                <p>
                    Chọn ngày, sân và khung giờ phù hợp. Giá được cập nhật ngay
                    theo từng khung đã chọn.
                </p>
            </header>

            <nav
                v-if="!loadingInit"
                class="booking-stepper"
                aria-label="Các bước đặt sân"
            >
                <button
                    v-for="step in bookingSteps"
                    :key="step.number"
                    type="button"
                    :class="{
                        active: currentStep === step.number,
                        complete: currentStep > step.number,
                    }"
                    @click="scrollToStep(step.number)"
                >
                    <span>{{ currentStep > step.number ? "✓" : step.number }}</span>
                    <div>
                        <strong>{{ step.title }}</strong>
                        <small>{{ step.description }}</small>
                    </div>
                </button>
            </nav>

            <div class="booking-grid" v-if="!loadingInit">
                <div class="form-section">
                    <div
                        ref="scheduleStep"
                        class="card schedule-card"
                        v-if="selectedClusterId"
                    >
                        <div class="card-header schedule-header">
                            <div>
                                <span class="card-icon">1</span>
                                <div>
                                    <h2>Chọn ngày và lịch sân</h2>
                                    <p>
                                        Chọn các ô liên tiếp trên cùng một sân.
                                    </p>
                                </div>
                            </div>
                            <div class="schedule-context">
                                <span>{{ operatingHoursLabel }}</span>
                                <span>
                                    {{ minimumDurationMinutes }} phút tối thiểu
                                </span>
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
                                    :disabled="isClusterLocked"
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
                                <small v-if="isClusterLocked" class="locked-hint">
                                    Đang đặt sân cho cụm sân đã chọn.
                                </small>
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
                            <span><i class="legend-booked"></i> Đã đặt</span>
                            <span><i class="legend-locked"></i> Khóa sân</span>
                            <span><i class="legend-past"></i> Đã qua giờ</span>
                            <span
                                ><i class="legend-selected"></i> Đang chọn</span
                            >
                        </div>

                        <div
                            v-if="dateValidationError"
                            class="booking-inline-alert error"
                        >
                            {{ dateValidationError }}
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
                                            booked:
                                                slotVisualState(court.id, slot) ===
                                                'booked',
                                            locked:
                                                slotVisualState(court.id, slot) ===
                                                'locked',
                                            past: isSlotPast(slot),
                                            'too-soon': isSlotTooSoon(slot),
                                            selected: isSlotSelected(
                                                court.id,
                                                index,
                                            ),
                                        }"
                                        :data-tooltip="
                                            slotTitle(court, slot, index)
                                        "
                                        :title="slotTitle(court, slot, index)"
                                        :disabled="
                                            isSlotDisabled(court.id, slot)
                                        "
                                        @click="
                                            selectScheduleSlot(court, index)
                                        "
                                    >
                                        <span
                                            v-if="
                                                !isSlotDisabled(court.id, slot)
                                            "
                                            class="slot-price"
                                        >
                                            {{
                                                formatCompactCurrency(
                                                    slotStatus(court.id, slot)
                                                        ?.price,
                                                )
                                            }}
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div
                            v-if="selectionValidationMessage"
                            class="duration-notice"
                            :class="{
                                error: Boolean(selectionValidationError),
                            }"
                        >
                            <strong>
                                {{
                                    selectionValidationError
                                        ? "Cần điều chỉnh lựa chọn"
                                        : "Khung giờ hợp lệ"
                                }}
                            </strong>
                            <span>{{ selectionValidationMessage }}</span>
                        </div>
                    </div>

                    <!-- Card 3: Chọn phương thức thanh toán -->
                    <div
                        ref="paymentStep"
                        class="card payment-card-section"
                        v-if="selectedCourtId && isAvailable"
                    >
                        <div class="card-header">
                            <span class="card-icon">3</span>
                            <div>
                                <h2>Thanh toán và giữ sân</h2>
                                <p>
                                    Chọn số tiền cần thanh toán để hoàn tất đặt
                                    sân.
                                </p>
                            </div>
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
                    <div ref="summaryStep" class="sticky-card">
                        <div class="card summary-card">
                            <div class="summary-heading">
                                <span class="card-icon">2</span>
                                <div>
                                    <h2>Tóm tắt booking</h2>
                                    <small>Cập nhật theo lựa chọn của bạn</small>
                                </div>
                            </div>
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
                                <div class="slot-breakdown">
                                    <div class="slot-breakdown-head">
                                        <strong>Chi tiết khung giờ</strong>
                                        <span>{{ selectedSlotDetails.length }} ô</span>
                                    </div>
                                    <div
                                        v-for="slot in selectedSlotDetails"
                                        :key="slot.start_time"
                                        class="slot-breakdown-row"
                                    >
                                        <span>
                                            {{ shortTime(slot.start_time) }} -
                                            {{ shortTime(slot.end_time) }}
                                        </span>
                                        <strong>{{
                                            formatCurrency(slot.price)
                                        }}</strong>
                                    </div>
                                </div>
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

                            <div
                                class="error-msg"
                                v-else-if="selectionValidationError"
                            >
                                {{ selectionValidationError }}
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
            lockedClusterId: "",
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
            scheduleOperatingHours: null,
            selectedGridCourtId: "",
            selectedSlotIndexes: [],
            routeSelectionApplied: false,
            selectionInteractionError: "",
            routeSelection: {
                venueCourtId: "",
                startTime: "",
                endTime: "",
            },

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
        bookingSteps() {
            return [
                {
                    number: 1,
                    title: "Ngày và sân",
                    description: "Chọn lịch còn trống",
                },
                {
                    number: 2,
                    title: "Kiểm tra giá",
                    description: "Xem chi tiết booking",
                },
                {
                    number: 3,
                    title: "Xác nhận",
                    description: "Voucher và thanh toán",
                },
            ];
        },
        currentStep() {
            if (this.isAvailable && this.selectedSlotIndexes.length) return 3;
            if (this.selectedSlotIndexes.length) return 2;
            return 1;
        },
        minDate() {
            return new Date().toLocaleDateString("en-CA");
        },
        dateValidationError() {
            if (!this.bookingDate) return "Vui lòng chọn ngày chơi.";
            if (this.bookingDate < this.minDate) {
                return "Ngày đặt không được trong quá khứ.";
            }
            return "";
        },
        currentCluster() {
            return this.clusters.find((c) => c.id === this.selectedClusterId);
        },
        isClusterLocked() {
            return Boolean(this.lockedClusterId);
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
                gridTemplateColumns: `160px repeat(${this.scheduleSlots.length}, 52px)`,
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
        minimumDurationMinutes() {
            return Number(this.config.min_duration_minutes || 30);
        },
        maximumDurationMinutes() {
            const value = Number(this.config.max_duration_minutes || 0);
            return value > 0 ? value : null;
        },
        minimumAdvanceMinutes() {
            return Number(this.config.min_advance_booking_minutes || 0);
        },
        operatingHoursLabel() {
            const hours = this.scheduleOperatingHours;
            if (!hours?.is_open) return "Sân đóng cửa";
            return `${this.shortTime(hours.open_time)} - ${this.shortTime(
                hours.close_time,
            )}`;
        },
        selectionValidationError() {
            if (this.selectionInteractionError) {
                return this.selectionInteractionError;
            }
            if (!this.selectedSlotIndexes.length) return "";
            if (this.durationMinutes < this.minimumDurationMinutes) {
                return `Thời lượng tối thiểu là ${this.minimumDurationMinutes} phút.`;
            }
            if (
                this.maximumDurationMinutes &&
                this.durationMinutes > this.maximumDurationMinutes
            ) {
                return `Thời lượng tối đa là ${this.maximumDurationMinutes} phút.`;
            }
            if (
                this.selectedSlotIndexes.some((index) =>
                    this.isSlotTooSoon(this.scheduleSlots[index]),
                )
            ) {
                return `Cần đặt trước ít nhất ${this.minimumAdvanceMinutes} phút.`;
            }
            if (this.availabilityChecked && !this.isAvailable) {
                return "Khung giờ này vừa được đặt hoặc không còn khả dụng.";
            }
            return "";
        },
        selectionValidationMessage() {
            if (this.selectionValidationError) {
                return this.selectionValidationError;
            }
            if (!this.selectedSlotIndexes.length) {
                return `Chọn tối thiểu ${this.minimumDurationMinutes} phút trên cùng một sân.`;
            }

            const maximum = this.maximumDurationMinutes
                ? `, tối đa ${this.maximumDurationMinutes} phút`
                : "";
            return `${this.durationMinutes} phút đã chọn${maximum}.`;
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
                !this.dateValidationError &&
                !this.selectionValidationError &&
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
            const query = this.$route.query || {};
            const requestedClusterId = query.venue_cluster_id || query.cluster || "";
            const requestedCourtId = query.venue_court_id || "";
            const queryCourtType = query.court_type || "";

            if (query.booking_date || query.date) {
                this.bookingDate = String(query.booking_date || query.date);
            }
            if (query.start_time) {
                this.startTime = this.normalizeTimeParam(query.start_time);
            }
            if (query.end_time) {
                this.endTime = this.normalizeTimeParam(query.end_time);
            }

            this.routeSelection = {
                venueCourtId: requestedCourtId,
                startTime: this.startTime,
                endTime: this.endTime,
            };

            const res = await bookingService.getInitData();
            this.clusters = res.clusters || [];
            if (this.clusters.length > 0) {
                const requestedCluster = this.clusters.find(
                    (cluster) => String(cluster.id) === String(requestedClusterId),
                );
                this.lockedClusterId = query.venue_cluster_id ? (requestedCluster?.id || "") : "";
                this.selectedClusterId = requestedCluster?.id || this.clusters[0].id;
                if (queryCourtType) {
                    this.selectedScheduleCourtTypeId = String(queryCourtType);
                }
                this.onClusterChange({
                    keepCourtType: Boolean(queryCourtType),
                    preferredCourtId: requestedCourtId,
                });
            }
        } catch (err) {
            console.error(err);
        } finally {
            this.loadingInit = false;
        }
    },
    methods: {
        onClusterChange(options = {}) {
            let keepCourtType = false;
            let preferredCourtId = "";

            if (typeof options === "boolean") {
                keepCourtType = options;
            } else if (typeof options === "string") {
                preferredCourtId = options;
            } else if (options && typeof options === "object") {
                keepCourtType = Boolean(options.keepCourtType);
                preferredCourtId = options.preferredCourtId || "";
            }

            if (this.lockedClusterId && this.selectedClusterId !== this.lockedClusterId) {
                this.selectedClusterId = this.lockedClusterId;
            }

            this.selectedCourtId = "";
            if (!keepCourtType) {
                this.selectedScheduleCourtTypeId = "";
            }
            this.isAvailable = false;
            this.availabilityChecked = false;
            this.clearGridSelection();
            this.loadSchedule();
        },
        onDateChange() {
            this.clearGridSelection();
            this.isAvailable = false;
            this.availabilityChecked = false;
            this.pricePreview = null;
            this.clearVoucherSelection();
            if (this.dateValidationError) return;
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
                this.scheduleOperatingHours = res.operating_hours || null;
                this.applyRouteSelection();
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
        isSlotTooSoon(slot) {
            if (
                !slot ||
                this.bookingDate !== this.minDate ||
                this.minimumAdvanceMinutes <= 0
            ) {
                return false;
            }

            const now = new Date();
            const requiredMinutes =
                now.getHours() * 60 +
                now.getMinutes() +
                this.minimumAdvanceMinutes;

            return this.timeToMinutes(slot.start_time) < requiredMinutes;
        },
        isSlotDisabled(courtId, slot) {
            return (
                this.isSlotBusy(courtId, slot) ||
                this.isSlotPast(slot) ||
                this.isSlotTooSoon(slot)
            );
        },
        slotVisualState(courtId, slot) {
            const status = this.slotStatus(courtId, slot);
            if (status?.busy_source === "slot_lock") return "locked";
            if (status && !status.is_available) return "booked";
            if (status?.is_available) return "free";

            const interval = this.scheduleBusyIntervals.find((item) => {
                if (item.venue_court_id !== courtId) return false;
                return (
                    this.timeToMinutes(item.start_time) <
                        this.timeToMinutes(slot.end_time) &&
                    this.timeToMinutes(item.end_time) >
                        this.timeToMinutes(slot.start_time)
                );
            });

            if (!interval) return "free";
            return interval.source === "slot_lock" ? "locked" : "booked";
        },
        isSlotSelected(courtId, index) {
            return (
                this.selectedGridCourtId === courtId &&
                this.selectedSlotIndexes.includes(index)
            );
        },
        async selectScheduleSlot(court, index) {
            const slot = this.scheduleSlots[index];
            if (!slot || this.isSlotDisabled(court.id, slot)) return;

            let nextIndexes = [index];
            this.selectionInteractionError = "";
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
                } else if (!this.selectedSlotIndexes.includes(index)) {
                    this.selectionInteractionError =
                        "Chỉ được chọn các khung giờ liên tiếp trên cùng một sân.";
                    return;
                }

                if (!this.isRangeFree(court.id, nextIndexes)) {
                    this.selectionInteractionError =
                        "Khoảng giờ vừa chọn có ô không còn khả dụng.";
                    return;
                }
            }

            const nextDuration = nextIndexes.length * 30;
            if (
                this.maximumDurationMinutes &&
                nextDuration > this.maximumDurationMinutes
            ) {
                this.selectionInteractionError = `Thời lượng tối đa là ${this.maximumDurationMinutes} phút.`;
                return;
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
                return slot && !this.isSlotDisabled(courtId, slot);
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
                return `${court.name} · ${slot.label}: đã quá thời gian đặt`;
            if (this.isSlotTooSoon(slot)) {
                return `${court.name} · ${slot.label}: cần đặt trước ít nhất ${this.minimumAdvanceMinutes} phút`;
            }
            if (this.isSlotBusy(court.id, slot)) {
                const status = this.slotStatus(court.id, slot);
                if (this.slotVisualState(court.id, slot) === "locked") {
                    return `${court.name} · ${slot.label}: ${
                        status?.lock_reason || "sân đang tạm khóa"
                    }`;
                }
                return `${court.name} · ${slot.label}: đã có booking`;
            }
            if (this.isSlotSelected(court.id, index))
                return `${court.name} · ${slot.label}: đang chọn`;
            return `${court.name} · ${slot.label}: còn trống · ${this.formatCurrency(
                this.slotStatus(court.id, slot)?.price || 0,
            )}`;
        },
        scrollToStep(step) {
            const target =
                step === 1
                    ? this.$refs.scheduleStep
                    : step === 2
                      ? this.$refs.summaryStep
                      : this.$refs.paymentStep || this.$refs.summaryStep;
            target?.scrollIntoView({ behavior: "smooth", block: "start" });
        },
        clearGridSelection() {
            this.selectedGridCourtId = "";
            this.selectedSlotIndexes = [];
            this.selectedCourtId = "";
            this.isAvailable = false;
            this.availabilityChecked = false;
            this.pricePreview = null;
            this.selectionInteractionError = "";
        },
        applyRouteSelection() {
            if (this.routeSelectionApplied || !this.routeSelection.venueCourtId) {
                return;
            }

            const matchedCourt = this.scheduleCourts.find(
                (court) =>
                    String(court.id) ===
                    String(this.routeSelection.venueCourtId),
            );
            if (!matchedCourt) {
                this.routeSelectionApplied = true;
                return;
            }

            const firstIndex = this.scheduleSlots.findIndex(
                (slot) => slot.start_time === this.routeSelection.startTime,
            );
            const lastIndex = this.scheduleSlots.findIndex(
                (slot) => slot.end_time === this.routeSelection.endTime,
            );

            if (firstIndex < 0 || lastIndex < firstIndex) {
                this.routeSelectionApplied = true;
                return;
            }

            const indexes = this.range(firstIndex, lastIndex);
            if (!this.isRangeFree(matchedCourt.id, indexes)) {
                this.routeSelectionApplied = true;
                return;
            }

            this.selectedGridCourtId = matchedCourt.id;
            this.selectedCourtId = matchedCourt.id;
            this.selectedSlotIndexes = indexes;
            this.startTime = this.routeSelection.startTime;
            this.endTime = this.routeSelection.endTime;
            this.routeSelectionApplied = true;
            this.checkAvailability();
        },
        normalizeTimeParam(value) {
            const raw = String(value || "").trim();
            if (/^\d{2}:\d{2}$/.test(raw)) return `${raw}:00`;
            if (/^\d{2}:\d{2}:\d{2}$/.test(raw)) return raw;
            return raw;
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
        shortTime(time) {
            return String(time || "").slice(0, 5);
        },
        formatCompactCurrency(value) {
            const amount = Number(value || 0);
            if (amount >= 1000000) {
                return `${(amount / 1000000).toLocaleString("vi-VN", {
                    maximumFractionDigits: 1,
                })}tr`;
            }
            if (amount >= 1000) {
                return `${(amount / 1000).toLocaleString("vi-VN", {
                    maximumFractionDigits: 0,
                })}k`;
            }
            return amount > 0 ? String(amount) : "";
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

.form-control:disabled {
    color: #64748b;
    background: #f1f5f9;
    cursor: not-allowed;
}

.locked-hint {
    display: block;
    margin-top: 6px;
    color: #059669;
    font-size: 12px;
    font-weight: 700;
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

/* Booking flow redesign */
.booking-container {
    --booking-bg: #090d0b;
    --booking-card: #111713;
    --booking-card-soft: #151d18;
    --booking-border: rgba(255, 255, 255, 0.1);
    --booking-muted: rgba(237, 247, 240, 0.62);
    --booking-text: #f5fbf6;
    --booking-accent: #22c55e;
    --booking-accent-dark: #16a34a;
    background:
        radial-gradient(circle at 12% 0%, rgba(34, 197, 94, 0.09), transparent 30%),
        var(--booking-bg);
    color: var(--booking-text);
}

.booking-main {
    width: min(100%, 1480px);
    padding: 94px 24px 48px;
}

.booking-hero {
    max-width: 820px;
    margin-bottom: 22px;
}

.booking-hero > div {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--booking-muted);
    font-size: 12px;
    font-weight: 700;
}

.booking-breadcrumb {
    color: #86efac;
    text-decoration: none;
}

.booking-hero h1 {
    margin: 10px 0 7px;
    color: var(--booking-text);
    font-size: clamp(26px, 3vw, 38px);
    font-weight: 850;
    letter-spacing: 0;
}

.booking-hero p {
    max-width: 68ch;
    margin: 0;
    color: var(--booking-muted);
    font-size: 14px;
    line-height: 1.6;
}

.booking-stepper {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1px;
    margin-bottom: 20px;
    overflow: hidden;
    border: 1px solid var(--booking-border);
    border-radius: 8px;
    background: var(--booking-border);
}

.booking-stepper button {
    min-width: 0;
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr);
    align-items: center;
    gap: 10px;
    padding: 13px 15px;
    border: 0;
    background: var(--booking-card);
    color: var(--booking-muted);
    text-align: left;
    cursor: pointer;
}

.booking-stepper button > span {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 50%;
    font-size: 12px;
    font-weight: 900;
}

.booking-stepper button div {
    min-width: 0;
    display: grid;
    gap: 2px;
}

.booking-stepper strong,
.booking-stepper small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.booking-stepper strong {
    color: var(--booking-text);
    font-size: 13px;
}

.booking-stepper small {
    color: var(--booking-muted);
    font-size: 11px;
}

.booking-stepper button.active {
    background: #17271c;
}

.booking-stepper button.active > span,
.booking-stepper button.complete > span {
    border-color: var(--booking-accent);
    background: var(--booking-accent);
    color: #052e16;
}

.booking-grid {
    grid-template-columns: minmax(0, 1fr) minmax(320px, 360px);
    gap: 20px;
}

.card {
    border: 1px solid var(--booking-border);
    border-radius: 8px;
    background: var(--booking-card);
}

.card-header {
    align-items: flex-start;
}

.card-header > div {
    min-width: 0;
}

.card-header h2 {
    margin: 0;
    color: var(--booking-text);
    font-size: 18px;
}

.card-header p {
    margin: 4px 0 0;
    color: var(--booking-muted);
    font-size: 12px;
    line-height: 1.45;
}

.schedule-context {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
}

.schedule-context span {
    padding: 5px 8px;
    border: 1px solid rgba(34, 197, 94, 0.2);
    border-radius: 999px;
    background: rgba(34, 197, 94, 0.07);
    color: #bbf7d0;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
}

.card-icon {
    flex: 0 0 auto;
    border-radius: 7px;
    background: var(--booking-accent);
    color: #052e16;
}

.schedule-controls {
    grid-template-columns: minmax(220px, 1fr) minmax(160px, 0.65fr) minmax(180px, 0.8fr);
    padding: 14px;
    border: 1px solid var(--booking-border);
    background: var(--booking-card-soft);
}

.schedule-controls .form-group {
    min-width: 0;
    margin: 0;
}

.form-group label {
    color: var(--booking-muted);
}

.form-control {
    box-sizing: border-box;
    border-color: var(--booking-border);
    background: #0d120f;
    color: var(--booking-text);
}

.form-control:focus {
    border-color: var(--booking-accent);
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
}

.form-control:disabled {
    background: rgba(255, 255, 255, 0.04);
    color: var(--booking-muted);
}

.schedule-legend {
    gap: 8px 16px;
    color: var(--booking-muted);
}

.schedule-legend i {
    width: 13px;
    height: 13px;
    border-color: rgba(255, 255, 255, 0.16);
}

.legend-free {
    background: rgba(34, 197, 94, 0.15);
    box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.55);
}

.legend-booked {
    background: rgba(239, 68, 68, 0.25);
}

.legend-locked {
    background: repeating-linear-gradient(
        -45deg,
        #4b5563,
        #4b5563 3px,
        #1f2937 3px,
        #1f2937 6px
    );
}

.legend-past {
    background: repeating-linear-gradient(
        -45deg,
        rgba(255, 255, 255, 0.12),
        rgba(255, 255, 255, 0.12) 3px,
        transparent 3px,
        transparent 6px
    );
}

.legend-selected {
    background: var(--booking-accent);
}

.booking-inline-alert,
.duration-notice {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 12px 0;
    padding: 10px 12px;
    border: 1px solid rgba(34, 197, 94, 0.24);
    border-radius: 7px;
    background: rgba(34, 197, 94, 0.08);
    color: #bbf7d0;
    font-size: 12px;
}

.duration-notice {
    justify-content: space-between;
}

.booking-inline-alert.error,
.duration-notice.error {
    border-color: rgba(248, 113, 113, 0.28);
    background: rgba(239, 68, 68, 0.08);
    color: #fecaca;
}

.schedule-wrap {
    border-color: var(--booking-border);
    border-radius: 8px;
    background: #0c110e;
    scrollbar-color: rgba(134, 239, 172, 0.46) transparent;
}

.schedule-head,
.schedule-court,
.schedule-cell {
    min-height: 48px;
    border-color: var(--booking-border);
}

.schedule-head {
    background: #151d18;
    color: var(--booking-muted);
    font-size: 11px;
}

.time-head {
    min-width: 52px;
}

.schedule-court {
    padding: 8px 10px;
    background: #111713;
}

.schedule-court strong {
    color: var(--booking-text);
    font-size: 12px;
}

.schedule-court span {
    color: var(--booking-muted);
    font-size: 10px;
}

.schedule-cell {
    position: relative;
    width: 52px;
    min-width: 52px;
    min-height: 48px;
    background: rgba(34, 197, 94, 0.08);
    border-color: var(--booking-border);
}

.schedule-cell:hover:not(:disabled) {
    background: rgba(34, 197, 94, 0.2);
    box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.62);
}

.schedule-cell.booked {
    background: rgba(239, 68, 68, 0.17);
}

.schedule-cell.locked {
    background: repeating-linear-gradient(
        -45deg,
        rgba(100, 116, 139, 0.38),
        rgba(100, 116, 139, 0.38) 6px,
        rgba(30, 41, 59, 0.72) 6px,
        rgba(30, 41, 59, 0.72) 12px
    );
}

.schedule-cell.past,
.schedule-cell.too-soon {
    background: repeating-linear-gradient(
        -45deg,
        rgba(255, 255, 255, 0.055),
        rgba(255, 255, 255, 0.055) 6px,
        transparent 6px,
        transparent 12px
    );
}

.schedule-cell.selected {
    background: var(--booking-accent);
    box-shadow: inset 0 0 0 2px #86efac;
}

.slot-price {
    display: block;
    color: #bbf7d0;
    font-size: 9px;
    font-weight: 850;
    opacity: 0;
    transition: opacity 0.15s;
}

.schedule-cell:hover .slot-price,
.schedule-cell.selected .slot-price {
    opacity: 1;
}

.schedule-cell.selected .slot-price {
    color: #052e16;
}

.schedule-cell[data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 50%;
    bottom: calc(100% + 8px);
    z-index: 8;
    width: max-content;
    max-width: 260px;
    padding: 7px 9px;
    border: 1px solid var(--booking-border);
    border-radius: 6px;
    background: #020604;
    color: #f0fdf4;
    font-size: 11px;
    font-weight: 650;
    line-height: 1.4;
    text-align: left;
    white-space: normal;
    transform: translateX(-50%);
    opacity: 0;
    pointer-events: none;
}

.schedule-cell[data-tooltip]:hover::after {
    opacity: 1;
}

.payment-options {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.payment-option-card {
    min-width: 0;
    min-height: 108px;
    border-color: var(--booking-border);
    background: var(--booking-card-soft);
}

.payment-option-card.active {
    border-color: var(--booking-accent);
    background: rgba(34, 197, 94, 0.1);
    box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.18);
}

.option-title {
    color: var(--booking-text);
}

.option-desc {
    color: var(--booking-muted);
    line-height: 1.45;
}

.sticky-card {
    top: 88px;
}

.summary-card {
    padding: 18px;
}

.summary-heading {
    display: flex;
    align-items: center;
    gap: 10px;
}

.summary-heading h2 {
    margin: 0;
    color: var(--booking-text);
    font-size: 17px;
}

.summary-heading small {
    display: block;
    margin-top: 3px;
    color: var(--booking-muted);
    font-size: 11px;
}

.summary-row {
    align-items: flex-start;
    gap: 14px;
}

.summary-row .label {
    flex: 0 0 42%;
    color: var(--booking-muted);
}

.summary-row .val {
    min-width: 0;
    color: var(--booking-text);
    text-align: right;
    overflow-wrap: anywhere;
}

.slot-breakdown {
    display: grid;
    gap: 7px;
    margin-bottom: 14px;
    padding: 11px 12px;
    border: 1px solid var(--booking-border);
    border-radius: 7px;
    background: var(--booking-card-soft);
}

.slot-breakdown-head,
.slot-breakdown-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.slot-breakdown-head {
    padding-bottom: 7px;
    border-bottom: 1px solid var(--booking-border);
    color: var(--booking-muted);
    font-size: 11px;
}

.slot-breakdown-row {
    color: var(--booking-muted);
    font-size: 11px;
}

.slot-breakdown-row strong {
    color: var(--booking-text);
}

.divider {
    background: var(--booking-border);
}

.btn-voucher-summary {
    border-color: rgba(34, 197, 94, 0.34);
    background: rgba(34, 197, 94, 0.08);
    color: var(--booking-text);
}

.btn-voucher-summary small {
    color: var(--booking-muted);
}

.btn-submit {
    border: 1px solid var(--booking-accent);
    background: var(--booking-accent);
    color: #052e16;
}

.btn-submit:hover:not(:disabled) {
    background: #4ade80;
}

.btn-submit:disabled {
    border-color: var(--booking-border);
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.35);
}

.hold-notice {
    color: var(--booking-muted);
}

@media (max-width: 1080px) {
    .booking-grid {
        grid-template-columns: minmax(0, 1fr) 310px;
    }

    .payment-options {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .booking-main {
        padding-inline: 14px;
    }

    .booking-grid {
        grid-template-columns: 1fr;
    }

    .summary-section {
        order: 2;
    }

    .summary-card {
        border-radius: 12px 12px 0 0;
    }
}

@media (max-width: 680px) {
    .booking-main {
        padding-top: 82px;
    }

    .booking-hero h1 {
        font-size: 26px;
    }

    .booking-stepper {
        grid-template-columns: 1fr;
    }

    .booking-stepper button {
        padding: 10px 12px;
    }

    .booking-stepper small {
        white-space: normal;
    }

    .card {
        padding: 14px;
    }

    .schedule-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .schedule-context {
        justify-content: flex-start;
    }

    .schedule-controls {
        grid-template-columns: 1fr;
    }

    .duration-notice {
        align-items: flex-start;
        flex-direction: column;
        gap: 4px;
    }

    .payment-options {
        grid-template-columns: 1fr;
    }

    .payment-option-card {
        min-height: auto;
    }

    .voucher-modal-backdrop {
        padding: 0;
    }

    .voucher-modal {
        max-height: 92dvh;
        border-radius: 12px 12px 0 0;
    }
}
</style>

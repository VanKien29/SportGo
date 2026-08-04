<template>
    <div class="owner-counter-page">
        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="notice" class="alert success">{{ notice }}</div>



        <section v-if="activeTab === 'counter'" class="counter-board">
            <div
                class="schedule-panel"
                :class="{ 'is-loading': counterScheduleLoading }"
            >
                <div class="panel-head compact">
                    <div>
                        <h2>{{ counterScheduleTitle }}</h2>
                        <p>{{ currentScheduleLabel }}</p>
                    </div>
                </div>

                <div class="filters schedule-filters counter-toolbar">
                    <label class="schedule-filter-field cluster-field">
                        <span>Cụm sân</span>
                        <div class="schedule-filter-readonly">
                            {{ selectedCluster?.name || "-" }}
                        </div>
                    </label>
                    <div class="schedule-filter-field date-field">
                        <span>Ngày chơi</span>
                        <div class="counter-date-range">
                            <button
                                type="button"
                                class="date-nav-btn"
                                aria-label="Ngày trước"
                                @click="shiftCounterDate(-1)"
                            >
                                <AppIcon name="chevronLeft" size="15" />
                            </button>
                            <div class="date-picker-wrap">
                                <button
                                    type="button"
                                    class="date-range-trigger"
                                    :class="{ open: counterDatePickerOpen }"
                                    @click="
                                        counterDatePickerOpen =
                                            !counterDatePickerOpen
                                    "
                                >
                                    <AppIcon name="calendar" size="16" />
                                    <span>{{ counterDateRangeLabel }}</span>
                                </button>
                                <div
                                    v-if="counterDatePickerOpen"
                                    class="counter-date-popover"
                                >
                                    <MiniCalendar
                                        mode="range"
                                        :start-date="form.booking_date"
                                        :end-date="form.booking_end_date"
                                        :min-date="today"
                                        @update:start-date="
                                            handleCounterStartDateUpdate
                                        "
                                        @update:end-date="
                                            handleCounterEndDateUpdate
                                        "
                                        @range-change="
                                            handleCounterRangeChange
                                        "
                                    />
                                </div>
                            </div>
                            <button
                                type="button"
                                class="date-nav-btn"
                                aria-label="Ngày sau"
                                @click="shiftCounterDate(1)"
                            >
                                <AppIcon name="chevronRight" size="15" />
                            </button>
                            <button
                                type="button"
                                class="today-btn"
                                @click="setCounterDateToday"
                            >
                                Hôm nay
                            </button>
                        </div>
                    </div>
                    <label class="schedule-filter-field type-field">
                        <span>Loại sân</span>
                        <select
                            v-model="selectedCourtTypeId"
                            @change="loadSchedule"
                        >
                            <option value="">Tất cả</option>
                            <option
                                v-for="type in courtTypeOptions"
                                :key="type.id"
                                :value="type.id"
                            >
                                {{ type.name }}
                            </option>
                        </select>
                    </label>
                </div>

                <p v-if="selectionError" class="selection-error">
                    {{ selectionError }}
                </p>

                <div
                    v-if="counterScheduleLoading"
                    class="schedule-loading-box"
                    role="status"
                    aria-label="Đang tải lịch sân"
                >
                    <div class="schedule-skeleton-head">
                        <span></span>
                        <span></span>
                    </div>
                    <div class="schedule-skeleton-toolbar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="schedule-skeleton-summary">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="schedule-skeleton-tabs">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="schedule-skeleton-grid">
                        <span v-for="item in 28" :key="item"></span>
                    </div>
                </div>
                <div v-else-if="scheduleError" class="state-card error-state">
                    {{ scheduleError }}
                </div>
                <div v-else-if="!scheduleCourts.length" class="state-card">
                    Không có sân phù hợp với bộ lọc hiện tại.
                </div>
                <div v-else class="time-board">
                    <div class="selected-court-strip">
                        <div>
                            <span>Sân đã chọn</span>
                            <strong>{{ selectedCourtText }}</strong>
                        </div>
                        <div>
                            <span>Khung giờ</span>
                            <strong>{{
                                hasCounterSelection
                                    ? selectedTimeText
                                    : "Chưa chọn"
                            }}</strong>
                        </div>
                        <div>
                            <span>Tổng tiền</span>
                            <strong>{{ formatCurrency(counterTotalAmount) }}</strong>
                        </div>
                    </div>

                    <div class="period-row">


                        <div class="legend">
                            <span><i></i>Lịch trống</span>
                            <span><i class="selected"></i>Đang chọn</span>
                            <span
                                ><i class="booked-paid"></i>Đã thanh toán</span
                            >
                            <span><i class="booked-online"></i>Đặt online</span>
                            <span
                                ><i class="booked-counter"></i>Chờ chuyển
                                khoản</span
                            >
                            <span><i class="pay-later"></i>Thu sau</span>
                            <span><i class="overdue"></i>Quá hạn</span>
                            <span><i class="locked"></i>Khóa sân</span>
                        </div>
                    </div>

                    <div
                        class="slot-matrix"
                        role="grid"
                        aria-label="Bảng chọn sân và khung giờ"
                        :style="slotMatrixStyle"
                    >
                        <div class="matrix-head sticky-col" role="columnheader">
                            Sân / giờ
                        </div>
                        <div
                            v-for="slot in activePeriodSlots"
                            :key="slot.start_time"
                            class="matrix-head time-head"
                            role="columnheader"
                        >
                            {{ formatTime(slot.start_time) }}
                        </div>

                        <template
                            v-for="court in scheduleCourts"
                            :key="court.id"
                        >
                            <div
                                class="matrix-court sticky-col"
                                role="rowheader"
                            >
                                <strong>{{ court.name }}</strong>
                                <span>{{ court.court_type?.name || "-" }}</span>
                            </div>
                            <button
                                v-for="slot in activePeriodSlots"
                                :key="`${court.id}-${slot.start_time}`"
                                type="button"
                                class="time-slot"
                                role="gridcell"
                                :aria-pressed="isSlotSelected(court.id, slot)"
                                :aria-label="slotActionTitle(court, slot)"
                                :class="slotButtonClass(court.id, slot)"
                                :disabled="isSlotDisabled(court.id, slot)"
                                :title="slotActionTitle(court, slot)"
                                @click="toggleSlot(court, slot)"
                            ></button>
                        </template>
                    </div>
                </div>
            </div>

            <div
                v-if="hasCounterSelection || selectedOccupiedInterval"
                class="counter-bottom-bar"
            >
                <div>
                    <strong v-if="selectedOccupiedInterval">Đang xem lịch đã đặt</strong>
                    <strong v-else>{{ selectedCourtText }} · {{ selectedDurationText }}</strong>
                    <span v-if="selectedOccupiedInterval">{{ occupiedPanelSubtitle }}</span>
                    <span v-else>{{ selectedTimeText }} · {{ formatCurrency(counterTotalAmount) }}</span>
                </div>
                <button
                    type="button"
                    class="primary-btn"
                    @click="counterDrawerOpen = true"
                >
                    {{ selectedOccupiedInterval ? "Xem chi tiết" : "Tiếp theo" }}
                </button>
            </div>

            <button
                v-if="counterDrawerOpen"
                type="button"
                class="counter-drawer-backdrop"
                aria-label="Đóng thông tin booking"
                @click="counterDrawerOpen = false"
            ></button>

            <aside class="booking-side" :class="{ open: counterDrawerOpen }">
                <button
                    type="button"
                    class="drawer-close-btn"
                    aria-label="Đóng thông tin booking"
                    @click="counterDrawerOpen = false"
                >
                    <AppIcon name="x" size="18" />
                </button>
                <section v-if="!selectedOccupiedInterval" class="side-section">
                    <div class="section-title muted">
                        <h2>Thông tin booking</h2>
                    </div>
                    <div v-if="!hasCounterSelection" class="empty-summary">
                        Chưa có khung giờ được chọn.
                    </div>
                    <dl v-else class="summary-list">
                        <div
                            v-for="[label, value] in counterSummaryRows"
                            :key="label"
                        >
                            <dt>{{ label }}</dt>
                            <dd>{{ value }}</dd>
                        </div>
                    </dl>
                </section>

                <template v-if="selectedOccupiedInterval">
                    <section class="side-section occupied-detail">
                        <div class="section-title muted">
                            <h2>{{ occupiedPanelTitle }}</h2>
                            <p>{{ occupiedPanelSubtitle }}</p>
                        </div>
                        <div
                            v-if="selectedBusyBooking"
                            class="booking-status-strip"
                        >
                            <span
                                class="status-badge"
                                :class="`tone-${bookingStatusTone(selectedBusyBooking.status)}`"
                            >
                                {{
                                    bookingStatusLabel(
                                        selectedBusyBooking.status,
                                    )
                                }}
                            </span>
                            <span
                                class="status-badge"
                                :class="`tone-${paymentStateTone(bookingPaymentState(selectedBusyBooking))}`"
                            >
                                {{
                                    paymentStateLabel(
                                        bookingPaymentState(
                                            selectedBusyBooking,
                                        ),
                                    )
                                }}
                            </span>
                        </div>
                        <dl class="summary-list">
                            <div
                                v-for="[label, value] in occupiedSummaryRows"
                                :key="label"
                            >
                                <dt>{{ label }}</dt>
                                <dd>
                                    <span
                                        v-if="isBadgeValue(value)"
                                        class="status-badge"
                                        :class="`tone-${value.tone}`"
                                    >
                                        {{ value.text }}
                                    </span>
                                    <template v-else>{{ value }}</template>
                                </dd>
                            </div>
                        </dl>
                        <div v-if="selectedBusyBooking" class="status-actions">
                            <button
                                v-if="
                                    selectedBusyBooking.status ===
                                    'pending_approval'
                                "
                                class="secondary-btn compact action-success"
                                type="button"
                                :disabled="bookingActionLoading"
                                @click="
                                    openBookingActionConfirm('status', {
                                        action: 'confirm',
                                    })
                                "
                            >
                                <AppIcon name="check" size="15" />
                                <span>Xác nhận</span>
                            </button>
                            <button
                                v-if="selectedBookingOutstanding > 0"
                                class="secondary-btn compact action-cash"
                                type="button"
                                :disabled="bookingActionLoading"
                                @click="
                                    openBookingActionConfirm('collect', {
                                        method: 'cash',
                                    })
                                "
                            >
                                <AppIcon name="banknote" size="15" />
                                <span>Thu tiền mặt</span>
                            </button>
                            <button
                                v-if="selectedBookingOutstanding > 0"
                                class="secondary-btn compact action-transfer"
                                type="button"
                                :disabled="bookingActionLoading"
                                @click="openSelectedBookingPaymentQr"
                            >
                                <AppIcon name="qrCode" size="15" />
                                <span>Chuyển khoản</span>
                            </button>
                            <button
                                v-if="
                                    [
                                        'pending_approval',
                                        'pending_payment',
                                        'confirmed',
                                    ].includes(selectedBusyBooking.status)
                                "
                                class="secondary-btn compact danger"
                                type="button"
                                :disabled="bookingActionLoading"
                                @click="
                                    openBookingActionConfirm('status', {
                                        action: 'cancel',
                                    })
                                "
                            >
                                <AppIcon name="trash" size="15" />
                                <span>Hủy booking</span>
                            </button>
                        </div>
                    </section>
                </template>

                <template v-else>
                    <section
                        class="side-section"
                        :class="{ disabled: !hasCounterSelection }"
                    >
                        <div class="section-title muted">
                            <h2>Khách hàng</h2>
                        </div>
                        <label>
                            <span>Tên khách</span>
                            <input
                                v-model.trim="form.walk_in_name"
                                type="text"
                                autocomplete="name"
                                minlength="2"
                                maxlength="100"
                                required
                                :aria-invalid="
                                    contactTouched.name &&
                                    Boolean(walkInNameError)
                                "
                                :class="{
                                    invalid:
                                        contactTouched.name && walkInNameError,
                                }"
                                placeholder="Nhập tên khách"
                                @input="handleContactInput('name')"
                                @blur="validateContactField('name')"
                            />
                            <small
                                v-if="contactTouched.name && walkInNameError"
                                class="field-error"
                            >
                                {{ walkInNameError }}
                            </small>
                        </label>
                        <label>
                            <span>Số điện thoại</span>
                            <input
                                v-model.trim="form.walk_in_phone"
                                type="tel"
                                autocomplete="tel"
                                inputmode="tel"
                                maxlength="15"
                                required
                                :aria-invalid="
                                    contactTouched.phone &&
                                    Boolean(walkInPhoneError)
                                "
                                :class="{
                                    invalid:
                                        contactTouched.phone &&
                                        walkInPhoneError,
                                }"
                                placeholder="Nhập số điện thoại"
                                @input="handleContactInput('phone')"
                                @blur="validateContactField('phone')"
                            />
                            <small
                                v-if="contactTouched.phone && walkInPhoneError"
                                class="field-error"
                            >
                                {{ walkInPhoneError }}
                            </small>
                        </label>
                    </section>

                    <section
                        v-if="canShowCounterVouchers"
                        class="side-section"
                        :class="{ disabled: !hasCounterSelection }"
                    >
                        <div class="section-title muted">
                            <h2>Voucher</h2>
                        </div>
                        <div class="voucher-picker">
                            <div class="voucher-code-row">
                                <input
                                    v-model.trim="voucherCodeInput"
                                    type="text"
                                    placeholder="Nhập mã voucher"
                                    :disabled="!hasCounterSelection"
                                    @keyup.enter="applyVoucherCode"
                                />
                                <button
                                    class="secondary-btn compact"
                                    type="button"
                                    :disabled="
                                        !hasCounterSelection || voucherLoading
                                    "
                                    @click="applyVoucherCode"
                                >
                                    Áp dụng
                                </button>
                            </div>
                            <small v-if="voucherError" class="field-error">{{
                                voucherError
                            }}</small>
                            <div
                                v-if="eligibleVouchers.length"
                                class="voucher-list"
                            >
                                <button
                                    v-for="voucher in eligibleVouchers"
                                    :key="voucher.id"
                                    type="button"
                                    :class="{
                                        active:
                                            selectedVoucherId === voucher.id,
                                    }"
                                    @click="selectVoucher(voucher)"
                                >
                                    <span>
                                        <strong>{{ voucher.code }}</strong>
                                        <small>{{ voucher.name }}</small>
                                    </span>
                                    <em
                                        >-{{
                                            formatCurrency(
                                                voucher.discount_amount,
                                            )
                                        }}</em
                                    >
                                </button>
                            </div>
                            <small
                                v-else-if="
                                    hasCounterSelection && !voucherLoading
                                "
                                class="voucher-empty"
                            >
                                Chưa có voucher đủ điều kiện cho khung này.
                            </small>
                        </div>
                    </section>

                    <section
                        class="side-section"
                        :class="{ disabled: !hasCounterSelection }"
                    >
                        <div class="section-title muted">
                            <h2>Thu tiền</h2>
                        </div>
                        <div class="payment-list">
                            <label
                                v-for="option in counterCollectionOptions"
                                :key="option.value"
                                class="payment-card"
                                :class="{
                                    active:
                                        form.collection_mode === option.value,
                                }"
                            >
                                <input
                                    v-model="form.collection_mode"
                                    type="radio"
                                    :value="option.value"
                                    @change="applyCounterCollectionMode"
                                />
                                <span>
                                    {{ option.label }}
                                </span>
                                <strong>{{
                                    formatCurrency(option.amount)
                                }}</strong>
                            </label>
                        </div>
                    </section>

                    <button
                        class="primary-btn full"
                        type="button"
                        :disabled="submitting || !canSubmitCounter"
                        @click="submitCounter"
                    >
                        <AppIcon name="plus" size="16" />
                        <span>{{
                            submitting ? "Đang tạo..." : "Tạo booking"
                        }}</span>
                    </button>
                </template>
            </aside>
        </section>

        <section v-else-if="activeTab === 'recurring'" class="recurring-panel">
            <div class="form-card">


                <div class="form-grid recurring-form-grid">
                    <div class="readonly-field">
                        <span>Cụm sân</span>
                        <strong>{{ selectedCluster?.name || "Chưa chọn cụm sân" }}</strong>
                    </div>
                    <label>
                        <span>Loại sân</span>
                        <select
                            v-model="selectedCourtTypeId"
                            @change="loadSchedule"
                        >
                            <option value="">Tất cả loại sân</option>
                            <option
                                v-for="type in courtTypeOptions"
                                :key="type.id"
                                :value="type.id"
                            >
                                {{ type.name }}
                            </option>
                        </select>
                    </label>
                    <label>
                        <span>Tên khách</span>
                        <input
                            v-model.trim="form.walk_in_name"
                            type="text"
                            autocomplete="name"
                            minlength="2"
                            maxlength="100"
                            required
                            :aria-invalid="
                                contactTouched.name && Boolean(walkInNameError)
                            "
                            :class="{
                                invalid: contactTouched.name && walkInNameError,
                            }"
                            placeholder="Nhập tên khách"
                            @blur="validateContactField('name')"
                        />
                        <small
                            v-if="contactTouched.name && walkInNameError"
                            class="field-error"
                        >
                            {{ walkInNameError }}
                        </small>
                    </label>
                    <label>
                        <span>Số điện thoại</span>
                        <input
                            v-model.trim="form.walk_in_phone"
                            type="tel"
                            autocomplete="tel"
                            inputmode="tel"
                            maxlength="15"
                            required
                            :aria-invalid="
                                contactTouched.phone &&
                                Boolean(walkInPhoneError)
                            "
                            :class="{
                                invalid:
                                    contactTouched.phone && walkInPhoneError,
                            }"
                            placeholder="Nhập số điện thoại"
                            @blur="validateContactField('phone')"
                        />
                        <small
                            v-if="contactTouched.phone && walkInPhoneError"
                            class="field-error"
                        >
                            {{ walkInPhoneError }}
                        </small>
                    </label>
                    <div class="calendar-range-field">
                        <span>Khoảng ngày</span>
                        <MiniCalendar
                            mode="range"
                            :start-date="form.recurring_start_date"
                            :end-date="form.recurring_end_date"
                            :min-date="today"
                            @update:start-date="val => { form.recurring_start_date = val; }"
                            @update:end-date="val => { form.recurring_end_date = val; }"
                        />
                    </div>
                    <label>
                        <span>Kiểu lặp</span>
                        <select v-model="form.recurrence_type">
                            <option value="daily">Hàng ngày</option>
                            <option value="weekly">Hàng tuần</option>
                            <option value="monthly">Hàng tháng</option>
                        </select>
                    </label>
                    <label>
                        <span>Lặp mỗi</span>
                        <select v-model.number="form.recurrence_interval">
                            <option
                                v-for="value in 12"
                                :key="value"
                                :value="value"
                            >
                                {{ value }}
                            </option>
                        </select>
                    </label>
                </div>
                <div
                    v-if="form.recurrence_type === 'weekly'"
                    class="recurring-weekday-planner"
                >
                    <div class="weekday-planner-head">
                        <div>
                            <strong>Lịch theo từng thứ</strong>
                            <span>Chọn thứ, rồi chọn sân và khung giờ riêng cho thứ đó ở bảng bên dưới.</span>
                        </div>
                        <div class="weekday-planner-actions">
                            <button
                                type="button"
                                :disabled="!activeRecurringWeekdayKeys.length"
                                @click="applyActiveWeekdayScheduleToSelected"
                            >
                                Áp dụng cho thứ đã chọn
                            </button>
                            <button
                                type="button"
                                :disabled="!activeRecurringWeekdayKeys.length"
                                @click="clearActiveWeekdaySchedule"
                            >
                                Xóa giờ thứ này
                            </button>
                        </div>
                    </div>
                    <button
                        v-for="day in weekDays"
                        :key="day.value"
                        type="button"
                        class="weekday-plan-card"
                        :class="{
                            selected: form.recurrence_days_of_week.includes(
                                day.value,
                            ),
                            active: recurringActiveWeekday === day.value,
                        }"
                        @click="toggleRecurringWeekday(day.value)"
                    >
                        <span class="weekday-name">{{ day.label }}</span>
                        <strong>{{ recurringWeekdayTimeText(day.value) }}</strong>
                        <small>{{ recurringWeekdayCourtText(day.value) }}</small>
                    </button>
                </div>
                <p class="recurring-helper">
                    Hệ thống sẽ tự tạo các buổi rơi vào ngày/thứ đã chọn trong
                    khoảng từ ngày - đến ngày, theo chu kỳ lặp bên trên.
                </p>

                <section class="recurring-schedule-board">
                    <div class="section-title muted">
                        <h2>Chọn sân và khung giờ cố định</h2>
                    </div>

                    <div class="schedule-summary compact">
                        <div>
                            <span>Sân đã chọn</span>
                            <strong>{{ selectedCourtText }}</strong>
                        </div>
                        <div>
                            <span>Khung giờ</span>
                            <strong>{{ recurringTimeText }}</strong>
                        </div>
                        <div>
                            <span>Giá mỗi buổi</span>
                            <strong>{{
                                formatCurrency(recurringUnitTotal)
                            }}</strong>
                        </div>
                    </div>

                    <p v-if="selectionError" class="selection-error">
                        {{ selectionError }}
                    </p>

                    <div
                        v-if="form.recurrence_type === 'weekly'"
                        class="active-weekday-note"
                    >
                        <strong>Đang chỉnh {{ recurringActiveWeekdayLabel }}</strong>
                        <span>
                            Các ô chọn trong bảng chỉ áp dụng cho {{ recurringActiveWeekdayLabel }}.
                        </span>
                    </div>

                    <div class="period-row">


                        <div class="legend">
                            <span><i></i>Trống</span>
                            <span><i class="selected"></i>Khung cố định</span>
                            <span
                                ><i class="booked-paid"></i>Đã thanh toán</span
                            >
                            <span><i class="booked-online"></i>Chờ online</span>
                            <span><i class="booked-counter"></i>Chờ CK</span>
                            <span><i class="pay-later"></i>Thu sau</span>
                            <span><i class="overdue"></i>Quá hạn</span>
                            <span><i class="locked"></i>Khóa sân</span>
                        </div>
                    </div>

                    <div
                        class="slot-matrix recurring-slot-matrix"
                        role="grid"
                        aria-label="Bảng chọn sân và khung giờ cố định"
                        :style="slotMatrixStyle"
                    >
                        <div class="matrix-head sticky-col" role="columnheader">
                            Sân / giờ
                        </div>
                        <div
                            v-for="slot in activePeriodSlots"
                            :key="slot.start_time"
                            class="matrix-head time-head"
                            role="columnheader"
                        >
                            {{ formatTime(slot.start_time) }}
                        </div>

                        <template
                            v-for="court in scheduleCourts"
                            :key="court.id"
                        >
                            <div
                                class="matrix-court sticky-col"
                                role="rowheader"
                            >
                                <strong>{{ court.name }}</strong>
                                <span>{{ court.court_type?.name || "-" }}</span>
                            </div>
                            <button
                                v-for="slot in activePeriodSlots"
                                :key="`${court.id}-${slot.start_time}`"
                                type="button"
                                class="time-slot"
                                role="gridcell"
                                :aria-pressed="isSlotSelected(court.id, slot)"
                                :aria-label="slotActionTitle(court, slot)"
                                :class="slotButtonClass(court.id, slot)"
                                :disabled="isSlotDisabled(court.id, slot)"
                                :title="slotActionTitle(court, slot)"
                                @click="toggleSlot(court, slot)"
                            ></button>
                        </template>
                    </div>
                </section>

                <section
                    v-if="form.recurrence_type === 'monthly'"
                    class="month-day-picker"
                >
                    <div class="month-day-head">
                        <div>
                            <strong>Ngày lặp trong tháng</strong>
                            <span>Chọn trực tiếp các ngày khách muốn đặt lịch.</span>
                        </div>
                        <div class="month-day-actions">
                            <button type="button" @click="setMonthDays([1, 15])">
                                1 & 15
                            </button>
                            <button type="button" @click="setMonthDays([15, 30])">
                                15 & 30
                            </button>
                            <button type="button" @click="setMonthDays([])">
                                Xóa chọn
                            </button>
                        </div>
                    </div>
                    <div class="month-day-grid">
                        <button
                            v-for="day in 31"
                            :key="day"
                            type="button"
                            :class="{ active: selectedMonthDays.includes(day) }"
                            @click="toggleMonthDay(day)"
                        >
                            {{ day }}
                        </button>
                    </div>
                </section>

                <section class="recurring-payment">
                    <div class="section-title muted">
                        <h2>Thu tiền</h2>
                    </div>

                    <div class="payment-list recurring-payment-list">
                        <label
                            v-for="option in recurringPaymentOptions"
                            :key="option.value"
                            class="payment-card"
                            :class="{
                                active: form.payment_option === option.value,
                            }"
                        >
                            <input
                                v-model="form.payment_option"
                                type="radio"
                                :value="option.value"
                                @change="syncPaidState"
                            />
                            <span>
                                {{ option.label }}
                            </span>
                        </label>
                    </div>

                    <div
                        v-if="form.payment_option !== 'no_prepay'"
                        class="recurring-collect-actions"
                    >
                        <button
                            type="button"
                            :class="{ active: form.payment_method === 'cash' }"
                            @click="setRecurringPaymentMethod('cash')"
                        >
                            <AppIcon name="banknote" size="15" />
                            <span>Tiền mặt</span>
                        </button>
                        <button
                            type="button"
                            :class="{
                                active: form.payment_method === 'bank_transfer',
                            }"
                            @click="setRecurringPaymentMethod('bank_transfer')"
                        >
                            <AppIcon name="qrCode" size="15" />
                            <span>Chuyển khoản</span>
                        </button>
                    </div>

                    <div v-else class="inline-note">
                        Lịch sẽ được tạo trước, tiền thu sau từng buổi khi khách
                        đến chơi.
                    </div>
                </section>

                <div class="form-actions">
                    <button
                        class="primary-btn"
                        type="button"
                        :disabled="submitting || !canSubmitRecurring"
                        @click="submitRecurring"
                    >
                        <AppIcon name="calendar" size="16" />
                        <span>{{
                            submitting ? "Đang tạo..." : "Tạo lịch cố định"
                        }}</span>
                    </button>
                </div>
            </div>

            <aside class="preview-box recurring-detail-box">
                <div class="preview-head">
                    <span>LỊCH CỐ ĐỊNH</span>
                    <strong
                        >{{ recurringPreview.length }} buổi sẽ được tạo</strong
                    >
                    <small v-if="!recurringPreview.length">
                        Chọn ngày lặp và khung giờ để xem trước.
                    </small>
                </div>

                <dl class="summary-list recurring-summary-list">
                    <div>
                        <dt>Cụm sân</dt>
                        <dd>{{ selectedCluster?.name || "-" }}</dd>
                    </div>
                    <div>
                        <dt>Sân</dt>
                        <dd>{{ selectedCourtText }}</dd>
                    </div>
                    <div>
                        <dt>Khách</dt>
                        <dd>{{ normalizedWalkInName || "-" }}</dd>
                    </div>
                    <div>
                        <dt>SĐT</dt>
                        <dd>{{ normalizedWalkInPhone || "-" }}</dd>
                    </div>
                    <div>
                        <dt>Khung giờ</dt>
                        <dd>{{ recurringTimeText }}</dd>
                    </div>
                    <div>
                        <dt>Chu kỳ lặp</dt>
                        <dd>{{ recurringPatternText }}</dd>
                    </div>
                    <div>
                        <dt>Giá mỗi buổi</dt>
                        <dd>{{ formatCurrency(recurringUnitTotal) }}</dd>
                    </div>
                    <div>
                        <dt>Tổng tiền gốc</dt>
                        <dd>{{ formatCurrency(recurringTotalAmount) }}</dd>
                    </div>
                    <div>
                        <dt>Tổng cần thu</dt>
                        <dd>{{ formatCurrency(recurringPayableTotal) }}</dd>
                    </div>
                    <div>
                        <dt>Cần thu</dt>
                        <dd>{{ formatCurrency(recurringRequiredAmount) }}</dd>
                    </div>
                    <div>
                        <dt>Phương thức</dt>
                        <dd>{{ recurringCollectionLabel }}</dd>
                    </div>
                    <div>
                        <dt>Thanh toán</dt>
                        <dd>{{ paymentOptionLabel(form.payment_option) }}</dd>
                    </div>
                </dl>

                <div
                    v-if="recurringPreview.length"
                    class="recurring-preview-panel"
                >
                    <div class="preview-panel-head">
                        <strong>Kiểm tra chuỗi lịch</strong>
                        <span v-if="recurringPreviewLoading"
                            >Đang kiểm tra...</span
                        >
                        <span v-else-if="recurringPreviewResult"
                            >Đã kiểm tra</span
                        >
                        <span v-else>Chưa kiểm tra</span>
                    </div>

                    <div class="preview-stat-grid">
                        <div>
                            <span>Tổng buổi</span>
                            <strong>{{ recurringPreviewStats.total }}</strong>
                        </div>
                        <div class="ok">
                            <span>Trống</span>
                            <strong>{{
                                recurringPreviewStats.available
                            }}</strong>
                        </div>
                        <div
                            :class="{ danger: recurringPreviewStats.conflict }"
                        >
                            <span>Trùng</span>
                            <strong>{{
                                recurringPreviewStats.conflict
                            }}</strong>
                        </div>
                    </div>

                    <p v-if="recurringPreviewError" class="preview-warning">
                        {{ recurringPreviewError }}
                    </p>

                    <div class="recurring-preview-list">
                        <article
                            v-for="row in recurringPreviewRows.slice(0, 18)"
                            :key="row.date"
                            :class="`status-${row.status}`"
                        >
                            <div>
                                <strong
                                    >{{ row.weekday }} · {{ row.label }}</strong
                                >
                                <small v-if="row.status === 'conflict'">
                                    {{ row.conflicts.length }} khung trùng, bấm
                                    tạo để chọn cách xử lý
                                </small>
                                <small v-else-if="row.status === 'available'">
                                    Các khung đã chọn còn trống
                                </small>
                                <small v-else> Chờ kiểm tra từ hệ thống </small>
                            </div>
                            <span>
                                {{
                                    row.status === "conflict"
                                        ? "Trùng"
                                        : row.status === "available"
                                          ? "Trống"
                                          : "Chờ"
                                }}
                            </span>
                        </article>
                    </div>
                    <small v-if="recurringPreviewRows.length > 18">
                        Còn {{ recurringPreviewRows.length - 18 }} buổi khác.
                    </small>
                </div>
            </aside>
        </section>

        <section v-else-if="activeTab === 'bookingList'" class="recurring-list-panel">
            <div class="filters booking-list-filters">
                <label>
                    <span>Sân con</span>
                    <select
                        v-model="bookingListFilters.venue_court_id"
                        @change="loadBookingList"
                    >
                        <option value="">Tất cả</option>
                        <option
                            v-for="court in courts"
                            :key="court.id"
                            :value="court.id"
                        >
                            {{ court.name }}
                        </option>
                    </select>
                </label>
                <label>
                    <span>Ngày chơi</span>
                    <input
                        v-model="bookingListFilters.booking_date"
                        type="date"
                        @change="loadBookingList"
                    />
                </label>
                <label>
                    <span>Nguồn đặt</span>
                    <select
                        v-model="bookingListFilters.source"
                        @change="loadBookingList"
                    >
                        <option value="">Tất cả</option>
                        <option value="online">Online</option>
                        <option value="counter">Tại quầy</option>
                    </select>
                </label>
                <label>
                    <span>Trạng thái</span>
                    <select
                        v-model="bookingListFilters.status"
                        @change="loadBookingList"
                    >
                        <option value="">Tất cả</option>
                        <option value="pending_approval">Chờ duyệt</option>
                        <option value="pending_payment">Chờ thanh toán</option>
                        <option value="confirmed">Đã xác nhận</option>
                        <option value="checked_in">Đã check-in</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </label>
                <label>
                    <span>Tìm kiếm</span>
                    <input
                        v-model.trim="bookingListFilters.q"
                        type="search"
                        placeholder="Mã booking, khách, SĐT"
                        @keyup.enter="loadBookingList"
                    />
                </label>
                <button
                    class="secondary-btn"
                    type="button"
                    @click="loadBookingList"
                >
                    <AppIcon name="search" size="16" />
                    <span>Lọc</span>
                </button>
            </div>

            <div v-if="bookingListLoading" class="table-skeleton">
                <div
                    v-for="row in 4"
                    :key="row"
                    class="table-skeleton-row"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <div v-else-if="!bookingList.length" class="state-card">
                Chưa có booking phù hợp.
            </div>
            <div v-else class="recurring-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Mã / khách</th>
                            <th>Sân & giờ</th>
                            <th>Nguồn đặt</th>
                            <th>Thanh toán</th>
                            <th>Trạng thái</th>
                            <th class="money-col">Còn thu</th>
                            <th class="action-col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="booking in bookingList" :key="booking.id">
                            <td>
                                <span class="group-code">{{
                                    booking.booking_code
                                }}</span>
                                <strong>{{
                                    bookingCustomerName(booking)
                                }}</strong>
                                <small>{{ bookingCustomerPhone(booking) }}</small>
                            </td>
                            <td>
                                <strong>{{ bookingCourtText(booking) }}</strong>
                                <small>
                                    {{ formatDate(booking.booking_date) }} ·
                                    {{ bookingTimeText(booking) }}
                                </small>
                            </td>
                            <td>
                                <span
                                    class="source-pill"
                                    :class="booking.source"
                                >
                                    {{ bookingSourceLabel(booking.source) }}
                                </span>
                            </td>
                            <td>
                                <strong>{{
                                    paymentOptionLabel(booking.payment_option)
                                }}</strong>
                                <small>
                                    Đã thu
                                    {{ formatCurrency(paidAmount(booking)) }}
                                    / {{ formatCurrency(booking.total_price) }}
                                </small>
                            </td>
                            <td>
                                <span
                                    class="status-badge"
                                    :class="`tone-${bookingStatusTone(booking.status)}`"
                                >
                                    {{ bookingStatusLabel(booking.status) }}
                                </span>
                            </td>
                            <td class="money-col">
                                <strong
                                    :class="{
                                        paid:
                                            bookingOutstandingAmount(booking) <=
                                            0,
                                    }"
                                >
                                    {{
                                        formatCurrency(
                                            bookingOutstandingAmount(booking),
                                        )
                                    }}
                                </strong>
                            </td>
                            <td class="action-col">
                                <button
                                    type="button"
                                    class="secondary-btn compact"
                                    @click="openBookingListDetail(booking)"
                                >
                                    <AppIcon name="eye" size="15" />
                                    <span>Chi tiết</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-else-if="activeTab === 'recurringList'" class="recurring-list-panel">
            <div class="filters recurring-list-filters">
                <label>
                    <span>Cụm sân</span>
                    <select
                        v-model="selectedClusterId"
                        @change="handleClusterChange"
                    >
                        <option
                            v-for="cluster in clusters"
                            :key="cluster.id"
                            :value="cluster.id"
                        >
                            {{ cluster.name }}
                        </option>
                    </select>
                </label>
                <label>
                    <span>Sân con</span>
                    <select
                        v-model="recurringGroupFilters.venue_court_id"
                        @change="loadRecurringGroups"
                    >
                        <option value="">Tất cả</option>
                        <option
                            v-for="court in courts"
                            :key="court.id"
                            :value="court.id"
                        >
                            {{ court.name }}
                        </option>
                    </select>
                </label>
                <label>
                    <span>Trạng thái</span>
                    <select
                        v-model="recurringGroupFilters.status"
                        @change="loadRecurringGroups"
                    >
                        <option value="">Tất cả</option>
                        <option value="pending_payment">Chờ thanh toán</option>
                        <option value="confirmed">Đã xác nhận</option>
                        <option value="checked_in">Đã check-in</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </label>
                <label>
                    <span>Tìm kiếm</span>
                    <input
                        v-model.trim="recurringGroupFilters.q"
                        type="search"
                        placeholder="Mã nhóm, khách, SĐT"
                        @keyup.enter="loadRecurringGroups"
                    />
                </label>
                <button
                    class="secondary-btn"
                    type="button"
                    @click="loadRecurringGroups"
                >
                    <AppIcon name="search" size="16" />
                    <span>Lọc</span>
                </button>
            </div>

            <div v-if="recurringGroupsLoading" class="state-card">
                Đang tải booking cố định...
            </div>
            <div v-else-if="!recurringGroups.length" class="state-card">
                Chưa có nhóm lịch cố định phù hợp.
            </div>
            <div v-else class="recurring-table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Mã / khách</th>
                            <th>Lịch lặp</th>
                            <th>Sân & giờ</th>
                            <th>Thanh toán</th>
                            <th class="money-col">Còn thu</th>
                            <th class="action-col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="group in recurringGroups"
                            :key="group.recurring_group_code"
                        >
                            <td>
                                <span class="group-code">{{
                                    group.recurring_group_code
                                }}</span>
                                <strong>{{
                                    recurringGroupCustomer(group)
                                }}</strong>
                                <small>{{ recurringGroupPhone(group) }}</small>
                            </td>
                            <td>
                                <strong>{{
                                    recurringGroupPatternText(group)
                                }}</strong>
                                <small>
                                    {{ group.booking_count }} buổi ·
                                    {{ formatDate(group.start_date) }} -
                                    {{ formatDate(group.end_date) }}
                                </small>
                                <div class="fixed-date-chips">
                                    <span
                                        v-for="occurrence in recurringGroupDateChips(
                                            group,
                                        )"
                                        :key="
                                            occurrence.booking_id ||
                                            occurrence.booking_date
                                        "
                                        :class="occurrenceChipClass(occurrence)"
                                        :title="occurrenceChipTitle(occurrence)"
                                    >
                                        {{
                                            formatShortDate(
                                                occurrence.booking_date,
                                            )
                                        }}
                                    </span>
                                    <em
                                        v-if="
                                            recurringGroupHiddenDateCount(
                                                group,
                                            ) > 0
                                        "
                                    >
                                        +{{
                                            recurringGroupHiddenDateCount(group)
                                        }}
                                    </em>
                                </div>
                            </td>
                            <td>
                                <strong>{{
                                    recurringGroupTimeText(group)
                                }}</strong>
                                <small>
                                    {{
                                        (group.court_names || []).join(", ") ||
                                        "-"
                                    }}
                                </small>
                            </td>
                            <td>
                                <strong>{{
                                    paymentOptionLabel(group.payment_option)
                                }}</strong>
                                <small>{{
                                    recurringGroupStatusSummary(group) ||
                                    "Chưa có trạng thái"
                                }}</small>
                            </td>
                            <td class="money-col">
                                <strong
                                    :class="{
                                        paid:
                                            Number(
                                                group.outstanding_amount || 0,
                                            ) <= 0,
                                    }"
                                >
                                    {{
                                        formatCurrency(group.outstanding_amount)
                                    }}
                                </strong>
                                <small>
                                    Tổng {{ formatCurrency(group.total_price) }}
                                    · Đã thu
                                    {{ formatCurrency(group.paid_amount) }}
                                </small>
                            </td>
                            <td class="action-col">
                                <button
                                    type="button"
                                    class="secondary-btn compact"
                                    @click="openRecurringGroupDetail(group)"
                                >
                                    <AppIcon name="eye" size="15" />
                                    <span>Chi tiết</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Teleport to="body">
            <div v-if="recurringGroupDetail" class="modal-backdrop">
                <section class="confirm-modal recurring-detail-modal">
                    <div class="modal-head">
                        <div>
                            <span>LỊCH CỐ ĐỊNH</span>
                            <h2>
                                {{ recurringGroupDetail.recurring_group_code }}
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="icon-btn"
                            @click="closeRecurringGroupDetail"
                        >
                            <AppIcon name="x" size="18" />
                        </button>
                    </div>

                    <div class="recurring-detail-body">
                        <div class="recurring-detail-main">
                            <div class="detail-hero">
                                <div>
                                    <span>Khách hàng</span>
                                    <strong>{{
                                        recurringGroupCustomer(
                                            recurringGroupDetail,
                                        )
                                    }}</strong>
                                    <small>{{
                                        recurringGroupPhone(
                                            recurringGroupDetail,
                                        )
                                    }}</small>
                                </div>
                                <span
                                    class="status-badge"
                                    :class="{
                                        'tone-paid':
                                            Number(
                                                recurringGroupDetail.outstanding_amount ||
                                                    0,
                                            ) <= 0,
                                        'tone-pending':
                                            Number(
                                                recurringGroupDetail.outstanding_amount ||
                                                    0,
                                            ) > 0,
                                    }"
                                >
                                    {{
                                        Number(
                                            recurringGroupDetail.outstanding_amount ||
                                                0,
                                        ) <= 0
                                            ? "Đã thu đủ"
                                            : "Còn phải thu"
                                    }}
                                </span>
                            </div>

                            <dl class="summary-list confirm-summary">
                                <div
                                    v-for="[
                                        label,
                                        value,
                                    ] in recurringGroupDetailRows"
                                    :key="label"
                                >
                                    <dt>{{ label }}</dt>
                                    <dd>{{ value }}</dd>
                                </div>
                            </dl>
                        </div>

                        <section
                            v-if="recurringGroupDetail.occurrences?.length"
                            class="recurring-occurrence-panel"
                        >
                            <div class="occurrence-head">
                                <strong>Các buổi trong chuỗi</strong>
                                <span>
                                    {{
                                        recurringGroupDetail.occurrences.length
                                    }}
                                    buổi
                                    <template
                                        v-if="
                                            recurringGroupIssueCount(
                                                recurringGroupDetail,
                                            )
                                        "
                                    >
                                        ·
                                        {{
                                            recurringGroupIssueCount(
                                                recurringGroupDetail,
                                            )
                                        }}
                                        buổi bị hủy/ảnh hưởng
                                    </template>
                                </span>
                            </div>
                            <div class="occurrence-list">
                                <article
                                    v-for="occurrence in recurringGroupDetail.occurrences"
                                    :key="occurrence.booking_id"
                                    :class="occurrenceCardClass(occurrence)"
                                >
                                    <div>
                                        <strong>
                                            {{
                                                formatDate(
                                                    occurrence.booking_date,
                                                )
                                            }}
                                        </strong>
                                        <small>
                                            {{ occurrenceTimeText(occurrence) }}
                                        </small>
                                        <small v-if="occurrence.status_reason">
                                            {{ occurrence.status_reason }}
                                        </small>
                                    </div>
                                    <div class="occurrence-state-group">
                                        <span
                                            class="occurrence-state"
                                            :class="`state-${occurrenceOperationalState(occurrence)}`"
                                        >
                                            {{ occurrenceStatusLabel(occurrence) }}
                                        </span>
                                        <span
                                            class="occurrence-payment"
                                            :class="`payment-${occurrencePaymentState(occurrence)}`"
                                        >
                                            {{ occurrencePaymentLabel(occurrence) }}
                                        </span>
                                    </div>
                                </article>
                            </div>
                        </section>
                    </div>

                    <div class="modal-actions">
                        <button
                            class="secondary-btn"
                            type="button"
                            @click="closeRecurringGroupDetail"
                        >
                            Đóng
                        </button>
                        <button
                            class="secondary-btn"
                            type="button"
                            :disabled="
                                recurringGroupCollecting ===
                                    recurringGroupDetail.recurring_group_code ||
                                Number(
                                    recurringGroupDetail.outstanding_amount ||
                                        0,
                                ) <= 0
                            "
                            @click="
                                openRecurringGroupCollectConfirm(
                                    recurringGroupDetail,
                                    'cash',
                                )
                            "
                        >
                            <AppIcon name="banknote" size="15" />
                            Thu tiền mặt
                        </button>
                        <button
                            class="primary-btn"
                            type="button"
                            :disabled="
                                recurringGroupCollecting ===
                                    recurringGroupDetail.recurring_group_code ||
                                Number(
                                    recurringGroupDetail.outstanding_amount ||
                                        0,
                                ) <= 0
                            "
                            @click="
                                openRecurringGroupCollectConfirm(
                                    recurringGroupDetail,
                                    'bank_transfer',
                                )
                            "
                        >
                            <AppIcon name="creditCard" size="15" />
                            Chuyển khoản
                        </button>
                    </div>
                </section>
            </div>

            <div v-if="bookingListDetail" class="modal-backdrop">
                <section class="confirm-modal booking-detail-modal">
                    <div class="modal-head">
                        <div>
                            <span>BOOKING</span>
                            <h2>{{ bookingListDetail.booking_code }}</h2>
                        </div>
                        <button
                            type="button"
                            class="icon-btn"
                            @click="closeBookingListDetail"
                        >
                            <AppIcon name="x" size="18" />
                        </button>
                    </div>

                    <div class="booking-status-strip">
                        <span
                            class="status-badge"
                            :class="`tone-${bookingStatusTone(bookingListDetail.status)}`"
                        >
                            {{ bookingStatusLabel(bookingListDetail.status) }}
                        </span>
                        <span
                            class="status-badge"
                            :class="`tone-${paymentStateTone(bookingPaymentState(bookingListDetail))}`"
                        >
                            {{
                                paymentStateLabel(
                                    bookingPaymentState(bookingListDetail),
                                )
                            }}
                        </span>
                    </div>

                    <dl class="summary-list confirm-summary">
                        <div
                            v-for="[label, value] in bookingListDetailRows"
                            :key="label"
                        >
                            <dt>{{ label }}</dt>
                            <dd>{{ value }}</dd>
                        </div>
                    </dl>

                    <div class="modal-actions">
                        <button
                            class="secondary-btn"
                            type="button"
                            @click="closeBookingListDetail"
                        >
                            Đóng
                        </button>
                    </div>
                </section>
            </div>

            <div
                v-if="bookingActionConfirm"
                class="modal-backdrop booking-action-modal-backdrop"
            >
                <section class="confirm-modal">
                    <div class="modal-head">
                        <div>
                            <span>BOOKING TẠI QUẦY</span>
                            <h2>{{ bookingActionConfirm.title }}</h2>
                        </div>
                        <button
                            type="button"
                            class="icon-btn"
                            @click="closeBookingActionConfirm"
                        >
                            <AppIcon name="x" size="18" />
                        </button>
                    </div>

                    <p class="conflict-help">
                        {{ bookingActionConfirm.message }}
                    </p>

                    <dl class="summary-list confirm-summary">
                        <div
                            v-for="[label, value] in bookingActionConfirmRows"
                            :key="label"
                        >
                            <dt>{{ label }}</dt>
                            <dd>{{ value }}</dd>
                        </div>
                    </dl>

                    <label
                        v-if="
                            bookingActionConfirm.kind === 'status' &&
                            bookingActionConfirm.action === 'cancel'
                        "
                        class="field-stack confirm-reason-field"
                    >
                        <span>Lý do hủy</span>
                        <textarea
                            v-model.trim="bookingActionConfirm.reason"
                            rows="3"
                            maxlength="1000"
                            placeholder="Ví dụ: sân cần bảo trì, khách đổi lịch theo thỏa thuận..."
                        ></textarea>
                        <small>
                            Nếu booking đã thanh toán, hệ thống sẽ hoàn 100%
                            tiền cho khách.
                        </small>
                    </label>

                    <div class="modal-actions">
                        <button
                            class="secondary-btn"
                            type="button"
                            :disabled="bookingActionLoading"
                            @click="closeBookingActionConfirm"
                        >
                            Đóng
                        </button>
                        <button
                            class="primary-btn"
                            :class="{
                                danger:
                                    bookingActionConfirm.variant === 'danger',
                            }"
                            type="button"
                            :disabled="
                                bookingActionLoading ||
                                (bookingActionConfirm.kind === 'status' &&
                                    bookingActionConfirm.action === 'cancel' &&
                                    !bookingActionConfirm.reason)
                            "
                            @click="confirmBookingAction"
                        >
                            {{
                                bookingActionLoading
                                    ? "Đang xử lý..."
                                    : bookingActionConfirm.confirmLabel
                            }}
                        </button>
                    </div>
                </section>
            </div>
            <div v-if="recurringGroupConfirm" class="modal-backdrop">
                <section class="confirm-modal">
                    <div class="modal-head">
                        <div>
                            <span>LỊCH CỐ ĐỊNH</span>
                            <h2>{{ recurringGroupConfirm.title }}</h2>
                        </div>
                        <button
                            type="button"
                            class="icon-btn"
                            @click="closeRecurringGroupConfirm"
                        >
                            <AppIcon name="x" size="18" />
                        </button>
                    </div>

                    <p class="conflict-help">
                        {{ recurringGroupConfirm.message }}
                    </p>

                    <dl class="summary-list confirm-summary">
                        <div
                            v-for="[label, value] in recurringGroupConfirmRows"
                            :key="label"
                        >
                            <dt>{{ label }}</dt>
                            <dd>{{ value }}</dd>
                        </div>
                    </dl>

                    <div class="modal-actions">
                        <button
                            class="secondary-btn"
                            type="button"
                            :disabled="Boolean(recurringGroupCollecting)"
                            @click="closeRecurringGroupConfirm"
                        >
                            Đóng
                        </button>
                        <button
                            class="primary-btn"
                            type="button"
                            :disabled="Boolean(recurringGroupCollecting)"
                            @click="confirmRecurringGroupCollect"
                        >
                            {{
                                recurringGroupCollecting
                                    ? "Đang xử lý..."
                                    : recurringGroupConfirm.confirmLabel
                            }}
                        </button>
                    </div>
                </section>
            </div>

            <div v-if="counterQr && qrModalOpen" class="modal-backdrop qr-modal-backdrop">
                <section class="qr-modal">
                    <div class="modal-head">
                        <div>
                            <span>CHUYỂN KHOẢN</span>
                            <h2>Thông tin thanh toán</h2>
                        </div>
                        <button
                            type="button"
                            class="icon-btn"
                            @click="closeQrModal"
                        >
                            <AppIcon name="x" size="18" />
                        </button>
                    </div>

                    <img :src="counterQr.qr_url" alt="Mã chuyển khoản" />
                    <dl class="summary-list confirm-summary">
                        <div>
                            <dt>Nội dung</dt>
                            <dd>
                                <button
                                    type="button"
                                    class="copy-value"
                                    @click="
                                        copyText(counterQr.transfer_content)
                                    "
                                >
                                    {{ counterQr.transfer_content }}
                                </button>
                            </dd>
                        </div>
                        <div>
                            <dt>Số tiền</dt>
                            <dd>
                                {{ formatCurrency(counterQr.payment?.amount) }}
                            </dd>
                        </div>
                        <div>
                            <dt>Tài khoản</dt>
                            <dd>
                                {{
                                    counterQr.payment_account?.account_number ||
                                    "-"
                                }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <div v-if="recurringConflict" class="modal-backdrop">
                <section class="conflict-modal">
                    <div class="modal-head">
                        <div>
                            <span>LỊCH CỐ ĐỊNH</span>
                            <h2>
                                Trùng lịch ở
                                {{ recurringConflict.conflict_count }} buổi
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="icon-btn"
                            @click="closeRecurringConflict"
                        >
                            <AppIcon name="x" size="18" />
                        </button>
                    </div>

                    <p class="conflict-help">
                        Chọn sân thay thế cho từng ngày, bỏ riêng ngày đó, hoặc
                        hủy tạo toàn bộ chuỗi.
                    </p>

                    <div class="conflict-list">
                        <article
                            v-for="conflict in recurringConflict.conflicts"
                            :key="conflict.key || conflict.date"
                        >
                            <div>
                                <strong>{{ formatDate(conflict.date) }}</strong>
                                <span
                                    >{{
                                        conflict.current_court?.name ||
                                        "Sân hiện tại"
                                    }}
                                    · {{ formatTime(conflict.start_time) }} -
                                    {{ formatTime(conflict.end_time) }}</span
                                >
                            </div>
                            <select
                                v-model="
                                    conflictSelections[
                                        conflict.key || conflict.date
                                    ]
                                "
                            >
                                <option value="skip">
                                    Bỏ booking ngày này
                                </option>
                                <option
                                    v-for="court in conflictAlternativeCourts(
                                        conflict,
                                    )"
                                    :key="court.id"
                                    :value="court.id"
                                >
                                    Đổi sang {{ court.name
                                    }}{{
                                        court.court_type?.name
                                            ? ` · ${court.court_type.name}`
                                            : ""
                                    }}
                                </option>
                            </select>
                            <small
                                v-if="
                                    !conflictAlternativeCourts(conflict).length
                                "
                                >Không có sân thay thế trống trong khung
                                này.</small
                            >
                        </article>
                    </div>

                    <div class="modal-actions">
                        <button
                            class="secondary-btn"
                            type="button"
                            @click="closeRecurringConflict"
                        >
                            Quay lại
                        </button>
                        <button
                            class="secondary-btn"
                            type="button"
                            :disabled="submitting"
                            @click="submitRecurringSkipConflicts"
                        >
                            Bỏ các ngày trùng
                        </button>
                        <button
                            class="primary-btn"
                            type="button"
                            :disabled="submitting"
                            @click="submitRecurringConflictChoices"
                        >
                            Tạo theo lựa chọn
                        </button>
                    </div>
                </section>
            </div>
        </Teleport>
    </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import MiniCalendar from "../../components/MiniCalendar.vue";
import { ownerBookingService } from "../../services/ownerBookings.js";
import { ownerBookingConfigService } from "../../services/ownerBookingConfigs.js";
import { venueClusterService } from "../../services/venueClusters.js";

function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

function toWeekDayIndex(date) {
    return (date.getDay() + 6) % 7;
}

const BOOKING_DAY_START = 0;
const BOOKING_DAY_END = 24 * 60;
const SLOT_STEP_MINUTES = 30;
const WALK_IN_NAME_PATTERN = /^[\p{L}\p{M}][\p{L}\p{M}\s.'-]*$/u;
const WALK_IN_PHONE_PATTERN = /^(?:\+84|0)(?:3|5|7|8|9)\d{8}$/;
const SLOT_PERIODS = [
    {
        key: "morning",
        label: "Sáng",
        range: "06:00 - 12:00",
        start: 6 * 60,
        end: 12 * 60,
    },
    {
        key: "afternoon",
        label: "Chiều",
        range: "12:00 - 18:00",
        start: 12 * 60,
        end: 18 * 60,
    },
    {
        key: "evening",
        label: "Tối",
        range: "18:00 - 22:00",
        start: 18 * 60,
        end: 22 * 60,
    },
];

export default {
    name: "OwnerCounterBooking",
    components: { AppIcon, MiniCalendar },
    data() {
        const now = new Date();
        const today = toIsoDate(now);

        return {
            today,
            activeTab: "counter",
            bootstrapping: true,
            clusterLoading: false,
            clusters: [],
            courts: [],
            selectedClusterId: "",
            selectedClusterDetail: null,
            selectedCourtTypeId: "",
            counterDatePickerOpen: false,
            scheduleSlots: [],
            scheduleCourts: [],
            scheduleSlotStatuses: [],
            scheduleBusyIntervals: [],
            selectedGridCourtId: "",
            selectedSlotKeys: [],
            recurringActiveWeekday: toWeekDayIndex(now),
            recurringWeekdaySlotKeys: {
                [toWeekDayIndex(now)]: [],
            },
            timePeriods: SLOT_PERIODS,
            activeTimePeriod: "morning",
            scheduleLoading: false,
            scheduleError: "",
            selectionError: "",
            monthDaysInput: "1",
            form: {
                venue_court_id: "",
                walk_in_name: "",
                walk_in_phone: "",
                booking_date: today,
                booking_end_date: today,
                recurring_start_date: today,
                recurring_end_date: today,
                recurrence_type: "weekly",
                recurrence_interval: 1,
                recurrence_days_of_week: [toWeekDayIndex(now)],
                start_time: "08:00",
                end_time: "09:00",
                payment_option: "full_payment",
                collection_mode: "cash",
                is_paid: true,
                payment_method: "cash",
            },
            weekDays: [
                { value: 0, label: "T2" },
                { value: 1, label: "T3" },
                { value: 2, label: "T4" },
                { value: 3, label: "T5" },
                { value: 4, label: "T6" },
                { value: 5, label: "T7" },
                { value: 6, label: "CN" },
            ],
            recurringPaymentMethods: [
                { value: "cash", label: "Tiền mặt", icon: "banknote" },
                {
                    value: "bank_transfer",
                    label: "Chuyển khoản",
                    icon: "creditCard",
                },
            ],
            submitting: false,
            error: "",
            notice: "",
            counterQr: null,
            counterQrBookingId: "",
            counterQrPollInterval: null,
            qrModalOpen: false,
            counterDrawerOpen: false,
            selectedOccupiedInterval: null,
            selectedBusyBooking: null,
            selectedBusyBookingLoading: false,
            bookingActionLoading: false,
            bookingActionConfirm: null,
            bookingList: [],
            bookingListLoading: false,
            bookingListDetail: null,
            bookingListFilters: {
                venue_court_id: "",
                booking_date: "",
                source: "",
                status: "",
                q: "",
            },
            recurringGroups: [],
            recurringGroupsLoading: false,
            recurringGroupCollecting: "",
            recurringGroupConfirm: null,
            recurringGroupDetail: null,
            recurringGroupFilters: {
                venue_court_id: "",
                status: "",
                q: "",
            },
            eligibleVouchers: [],
            selectedVoucherId: "",
            voucherCodeInput: "",
            voucherLoading: false,
            voucherError: "",
            voucherRequestId: 0,
            recurringConflict: null,
            recurringPreviewLoading: false,
            recurringPreviewResult: null,
            recurringPreviewError: "",
            recurringPreviewTimer: null,
            recurringPreviewRequestId: 0,
            conflictSelections: {},
            contactTouched: {
                name: false,
                phone: false,
            },
        };
    },
    computed: {
        selectedCluster() {
            return (
                this.clusters.find(
                    (cluster) =>
                        String(cluster.id) === String(this.selectedClusterId),
                ) || null
            );
        },
        selectedRecurringCourt() {
            return (
                this.courts.find(
                    (court) =>
                        String(court.id) === String(this.form.venue_court_id),
                ) || null
            );
        },
        hasCounterSelection() {
            return this.activeTab === "recurring"
                ? this.hasRecurringSelection
                : Boolean(this.selectedSlotKeys.length);
        },
        counterScheduleLoading() {
            return (
                this.activeTab === "counter" &&
                (this.bootstrapping ||
                    this.clusterLoading ||
                    this.scheduleLoading)
            );
        },
        isViewingPastScheduleDate() {
            return (
                this.activeTab === "counter" &&
                this.form.booking_date &&
                this.form.booking_date < this.today
            );
        },
        activeScheduleDate() {
            return this.activeTab === "recurring"
                ? this.form.recurring_start_date
                : this.form.booking_date;
        },
        operatingStartMinutes() {
            const configured =
                this.selectedClusterDetail?.booking_config?.fixed_open_time;
            return configured
                ? this.timeToMinutes(configured)
                : BOOKING_DAY_START;
        },
        operatingEndMinutes() {
            const configured =
                this.selectedClusterDetail?.booking_config?.fixed_close_time;
            return configured ? this.timeToMinutes(configured) : BOOKING_DAY_END;
        },
        dynamicTimePeriods() {
            const slotStarts = this.scheduleSlots.map((slot) =>
                this.timeToMinutes(slot.start_time),
            );
            const slotEnds = this.scheduleSlots.map((slot) =>
                this.timeToMinutes(slot.end_time),
            );
            const open = slotStarts.length
                ? Math.min(...slotStarts)
                : this.operatingStartMinutes;
            const close = Math.max(
                slotEnds.length ? Math.max(...slotEnds) : this.operatingEndMinutes,
                open + SLOT_STEP_MINUTES,
            );
            const configuredPeriods =
                this.selectedClusterDetail?.booking_config?.custom_time_periods;

            let raw = [];
            if (Array.isArray(configuredPeriods) && configuredPeriods.length > 0) {
                raw = configuredPeriods.map((p, idx) => ({
                    key: `custom_${idx}`,
                    label: p.label,
                    start: this.timeToMinutes(p.start_time),
                    end: this.timeToMinutes(p.end_time),
                }));
            } else {
                raw = [
                    {
                        key: "late_night",
                        label: "Khuya",
                        start: open,
                        end: Math.min(close, 6 * 60),
                    },
                    {
                        key: "morning",
                        label: "Sáng",
                        start: Math.max(open, 6 * 60),
                        end: Math.min(close, 12 * 60),
                    },
                    {
                        key: "afternoon",
                        label: "Chiều",
                        start: Math.max(open, 12 * 60),
                        end: Math.min(close, 18 * 60),
                    },
                    {
                        key: "evening",
                        label: "Tối",
                        start: Math.max(open, 18 * 60),
                        end: Math.min(close, 22 * 60),
                    },
                    {
                        key: "night",
                        label: "Đêm",
                        start: Math.max(open, 22 * 60),
                        end: close,
                    },
                ];
            }

            const periods = raw
                .filter(
                    (period) =>
                        period.end > period.start &&
                        period.start < close &&
                        period.end > open,
                )
                .map((period) => {
                    const clampedStart = Math.max(period.start, open);
                    const clampedEnd = Math.min(period.end, close);
                    return {
                        ...period,
                        start: clampedStart,
                        end: clampedEnd,
                        range: `${this.minutesToTime(clampedStart)} - ${this.minutesToTime(clampedEnd)}`,
                    };
                });

            if (periods.length > 1) {
                periods.push({
                    key: "all",
                    label: "Cả ngày",
                    start: open,
                    end: close,
                    range: `${this.minutesToTime(open)} - ${this.minutesToTime(close)}`,
                });
            }

            return periods.length
                ? periods
                : [
                      {
                          key: "all",
                          label: "Cả ngày",
                          start: open,
                          end: close,
                          range: `${this.minutesToTime(open)} - ${this.minutesToTime(close)}`,
                      },
                  ];
        },
        courtTypeOptions() {
            const map = new Map();
            this.courts.forEach((court) => {
                if (court.court_type?.id)
                    map.set(court.court_type.id, court.court_type);
            });
            return [...map.values()].sort((a, b) =>
                a.name.localeCompare(b.name),
            );
        },
        bookableScheduleSlots() {
            return this.scheduleSlots;
        },
        slotPeriods() {
            return this.dynamicTimePeriods.map((period) => ({
                ...period,
                slots: this.bookableScheduleSlots.filter((slot) => {
                    const start = this.timeToMinutes(slot.start_time);
                    return start >= period.start && start < period.end;
                }),
            }));
        },
        activePeriod() {
            return (
                this.dynamicTimePeriods.find(
                    (period) => period.key === this.activeTimePeriod,
                ) || this.dynamicTimePeriods[0]
            );
        },
        activePeriodSlots() {
            const period = this.activePeriod;
            return this.bookableScheduleSlots.filter((slot) => {
                const start = this.timeToMinutes(slot.start_time);
                return start >= period.start && start < period.end;
            });
        },
        slotMatrixStyle() {
            return {
                gridTemplateColumns: `minmax(128px, 0.85fr) repeat(${this.activePeriodSlots.length}, minmax(28px, 1fr))`,
            };
        },
        activeSelectionKeys() {
            if (
                this.activeTab !== "recurring" ||
                this.form.recurrence_type !== "weekly"
            )
                return this.selectedSlotKeys;

            return (
                this.recurringWeekdaySlotKeys[this.recurringActiveWeekday] || []
            );
        },
        activeRecurringWeekdayKeys() {
            return this.recurringWeekdaySlotKeys[this.recurringActiveWeekday] || [];
        },
        recurringSelectedDays() {
            return [...this.form.recurrence_days_of_week].sort(
                (a, b) => a - b,
            );
        },
        recurringActiveWeekdayLabel() {
            return (
                this.weekDays.find(
                    (day) => day.value === this.recurringActiveWeekday,
                )?.label || "thứ đang chọn"
            );
        },
        recurringDayRanges() {
            return this.recurringSelectedDays.reduce((result, day) => {
                const ranges = this.slotRangesFromKeys(
                    this.recurringWeekdaySlotKeys[day] || [],
                );
                if (ranges.length) result[day] = ranges;
                return result;
            }, {});
        },
        hasRecurringSelection() {
            if (this.form.recurrence_type !== "weekly")
                return Boolean(this.selectedSlotKeys.length);

            return Object.keys(this.recurringDayRanges).length > 0;
        },
        recurringScheduleSummaries() {
            return this.recurringSelectedDays.map((day) => ({
                day,
                label: this.weekDays.find((item) => item.value === day)?.label,
                ranges: this.recurringDayRanges[day] || [],
            }));
        },
        recurringPerDayTotals() {
            return this.recurringSelectedDays.reduce((result, day) => {
                result[day] = this.slotEntriesFromKeys(
                    this.recurringWeekdaySlotKeys[day] || [],
                ).reduce((total, entry) => {
                    const status = this.slotStatus(entry.courtId, entry.slot);
                    return total + Number(status?.price || 0);
                }, 0);
                return result;
            }, {});
        },
        recurringSelectedCourtText() {
            if (this.form.recurrence_type !== "weekly")
                return this.selectedCourtText;

            const names = new Set();
            Object.values(this.recurringDayRanges).forEach((ranges) => {
                ranges.forEach((range) => {
                    if (range.court?.name) names.add(range.court.name);
                });
            });

            const courtNames = [...names];
            if (!courtNames.length) return "Chưa chọn";
            if (courtNames.length <= 2) return courtNames.join(", ");
            return `${courtNames.length} sân`;
        },
        recurringScheduleText() {
            if (this.form.recurrence_type !== "weekly")
                return this.hasCounterSelection
                    ? this.selectedTimeText
                    : "Chưa chọn";

            const filled = this.recurringScheduleSummaries.filter(
                (item) => item.ranges.length,
            );
            if (!filled.length) return "Chưa chọn";
            if (filled.length === 1) {
                return `${filled[0].label}: ${this.rangeListText(filled[0].ranges)}`;
            }

            return `${filled.length} thứ đã có khung giờ`;
        },
        recurringBaseTotal() {
            if (this.form.recurrence_type !== "weekly")
                return this.recurringUnitTotal * this.recurringPreview.length;

            return this.recurringPreview.reduce((total, date) => {
                const day = this.dayIndex(this.parseDate(date));
                return total + Number(this.recurringPerDayTotals[day] || 0);
            }, 0);
        },
        selectedMonthDays() {
            return this.monthDaysInput
                .split(",")
                .map((item) => Number(item.trim()))
                .filter((day) => day >= 1 && day <= 31)
                .sort((a, b) => a - b);
        },
        selectedSlotEntries() {
            return this.slotEntriesFromKeys(this.activeSelectionKeys);
        },
        selectedDurationMinutes() {
            return this.selectedSlotEntries.reduce((total, entry) => {
                return (
                    total +
                    Math.max(
                        this.timeToMinutes(entry.slot.end_time) -
                            this.timeToMinutes(entry.slot.start_time),
                        0,
                    )
                );
            }, 0);
        },
        selectedDurationText() {
            if (!this.selectedDurationMinutes) return "0 phút";

            const hours = Math.floor(this.selectedDurationMinutes / 60);
            const minutes = this.selectedDurationMinutes % 60;
            if (!hours) return `${minutes} phút`;
            if (!minutes) return `${hours} giờ`;
            return `${hours} giờ ${minutes} phút`;
        },
        selectedSlotRanges() {
            return this.slotRangesFromKeys(this.activeSelectionKeys);
        },
        selectedCourtText() {
            if (
                this.activeTab === "recurring" &&
                this.form.recurrence_type === "weekly"
            )
                return this.recurringSelectedCourtText;

            const courtNames = [
                ...new Set(
                    this.selectedSlotEntries.map((entry) => entry.court.name),
                ),
            ];
            if (!courtNames.length) return "Chưa chọn";
            if (courtNames.length <= 2) return courtNames.join(", ");
            return `${courtNames.length} sân`;
        },
        selectedTimeText() {
            if (!this.hasCounterSelection) return "-";
            return this.rangeListText(this.selectedSlotRanges);
        },
        recurringTimeText() {
            return this.recurringScheduleText;
        },
        selectedTotal() {
            return this.selectedSlotEntries.reduce((total, entry) => {
                const status = this.slotStatus(entry.courtId, entry.slot);
                return total + Number(status?.price || 0);
            }, 0);
        },
        counterBookingDates() {
            const start = this.parseDate(this.form.booking_date);
            const end = this.parseDate(
                this.form.booking_end_date || this.form.booking_date,
            );
            if (!start || !end || end < start) return [];

            const dates = [];
            for (
                let current = new Date(start);
                current <= end && dates.length < 31;
                current.setDate(current.getDate() + 1)
            ) {
                dates.push(this.formatIsoDate(current));
            }
            return dates;
        },
        counterDateCount() {
            return Math.max(this.counterBookingDates.length, 1);
        },
        counterDateRangeLabel() {
            if (this.counterDateCount <= 1) {
                return this.formatDate(this.form.booking_date);
            }
            return `${this.formatDate(this.form.booking_date)} - ${this.formatDate(this.form.booking_end_date)} (${this.counterDateCount} ngày)`;
        },
        counterTotalAmount() {
            return this.selectedTotal * this.counterDateCount;
        },
        recurringUnitTotal() {
            return this.activeTab === "recurring" ? this.selectedTotal : 0;
        },
        recurringTotalAmount() {
            return this.recurringBaseTotal;
        },
        voucherBaseAmount() {
            return this.activeTab === "counter" ? this.selectedTotal : 0;
        },
        selectedVoucher() {
            return (
                this.eligibleVouchers.find(
                    (voucher) => voucher.id === this.selectedVoucherId,
                ) || null
            );
        },
        voucherUnitDiscount() {
            return Math.min(
                Number(this.selectedVoucher?.discount_amount || 0),
                this.voucherBaseAmount,
            );
        },
        voucherDiscountAmount() {
            return this.activeTab === "counter" ? this.voucherUnitDiscount : 0;
        },
        counterVoucherDiscountAmount() {
            return this.voucherDiscountAmount * this.counterDateCount;
        },
        counterPayableTotal() {
            return Math.max(
                this.counterTotalAmount - this.counterVoucherDiscountAmount,
                0,
            );
        },
        recurringUnitPayableTotal() {
            return this.recurringUnitTotal;
        },
        recurringPayableTotal() {
            return (
                this.recurringUnitPayableTotal * this.recurringPreview.length
            );
        },
        recurringRequiredAmount() {
            if (this.form.payment_option === "no_prepay") return 0;

            return this.recurringPayableTotal;
        },
        recurringCollectionLabel() {
            if (this.form.payment_option === "no_prepay") return "Thu sau";

            return (
                {
                    cash: "Tiền mặt",
                    bank_transfer: "Chuyển khoản",
                }[this.form.payment_method] || "-"
            );
        },
        recurringPatternText() {
            const interval = Number(this.form.recurrence_interval || 1);
            const every = interval > 1 ? `${interval} ` : "";

            if (this.form.recurrence_type === "daily") {
                return `Lặp mỗi ${every}ngày`;
            }

            if (this.form.recurrence_type === "weekly") {
                const days = this.weekDays
                    .filter((day) =>
                        this.form.recurrence_days_of_week.includes(day.value),
                    )
                    .map((day) => day.label)
                    .join(", ");

                return `Lặp mỗi ${every}tuần${days ? ` · vào ${days}` : ""}`;
            }

            return `Lặp mỗi ${every}tháng · ngày ${this.monthDaysInput || "-"}`;
        },
        depositPercent() {
            return Number(
                this.selectedClusterDetail?.booking_config?.deposit_percent ||
                    30,
            );
        },
        counterCollectionOptions() {
            const options = [
                {
                    value: "cash",
                    label: "Tiền mặt",
                    amount: this.counterPayableTotal,
                },
                {
                    value: "transfer",
                    label: "Chuyển khoản",
                    amount: this.counterPayableTotal,
                },
                {
                    value: "later",
                    label: "Thu sau",
                    amount: this.counterPayableTotal,
                },
            ];

            return this.counterDateCount > 1
                ? options.filter((option) => option.value !== "transfer")
                : options;
        },
        paymentOptions() {
            const config = this.selectedClusterDetail?.booking_config || {};
            const baseAmount =
                this.activeTab === "recurring"
                    ? this.recurringPayableTotal
                    : this.counterPayableTotal;
            const options = [
                {
                    value: "full_payment",
                    label: "Thanh toán đủ",
                    amount: baseAmount,
                    enabled: config.allow_full_payment !== false,
                },
                {
                    value: "deposit",
                    label: `Đặt cọc ${this.depositPercent}%`,
                    amount: Math.round(
                        (baseAmount * this.depositPercent) / 100,
                    ),
                    enabled: config.allow_deposit !== false,
                },
                {
                    value: "no_prepay",
                    label: "Thu sau / ghi nợ",
                    amount: 0,
                    enabled: config.allow_no_prepay !== false,
                },
            ];

            return options.filter((option) => option.enabled);
        },
        recurringPaymentOptions() {
            const descriptions = {
                full_payment: "Ghi nhận thu đủ cho từng buổi trong nhóm lịch.",
                no_prepay: "Tạo lịch trước, thu tiền sau khi khách đến chơi.",
            };

            return this.paymentOptions
                .filter((option) =>
                    ["full_payment", "no_prepay"].includes(option.value),
                )
                .map((option) => ({
                    ...option,
                    description: descriptions[option.value] || option.label,
                }));
        },
        counterSummaryRows() {
            const rows = [
                ["Cụm sân", this.selectedCluster?.name || "-"],
                ["Sân", this.selectedCourtText],
                ["Ngày", this.counterDateRangeLabel],
                ["Giờ", this.selectedTimeText],
                ["Thời lượng", this.selectedDurationText],
                ["Tổng tiền", this.formatCurrency(this.counterTotalAmount)],
            ];

            if (this.selectedVoucher) {
                rows.push([
                    `Voucher ${this.selectedVoucher.code}`,
                    `-${this.formatCurrency(this.counterVoucherDiscountAmount)}`,
                ]);
                rows.push([
                    "Cần thu",
                    this.formatCurrency(this.counterPayableTotal),
                ]);
            }

            return rows;
        },
        currentScheduleLabel() {
            return `${this.selectedCluster?.name || "Cụm sân"} · ${this.counterDateRangeLabel}`;
        },
        counterScheduleTitle() {
            return this.counterDateCount > 1
                ? "Lịch sân ngày bắt đầu"
                : "Lịch sân trong ngày";
        },
        selectedBookingOutstanding() {
            if (!this.selectedBusyBooking) return 0;
            const total = Number(this.selectedBusyBooking.total_price || 0);
            return Math.max(
                total - this.paidAmount(this.selectedBusyBooking),
                0,
            );
        },
        bookingListDetailRows() {
            const booking = this.bookingListDetail;
            if (!booking) return [];

            return [
                ["Mã booking", booking.booking_code || "-"],
                ["Nguồn đặt", this.bookingSourceLabel(booking.source)],
                [
                    "Khách",
                    `${this.bookingCustomerName(booking)} · ${this.bookingCustomerPhone(booking)}`,
                ],
                ["Cụm sân", this.selectedCluster?.name || "-"],
                ["Sân", this.bookingCourtText(booking)],
                ["Ngày", this.formatDate(booking.booking_date)],
                ["Khung giờ", this.bookingTimeText(booking)],
                ["Trạng thái", this.bookingStatusLabel(booking.status)],
                ["Hình thức", this.paymentOptionLabel(booking.payment_option)],
                ["Tổng tiền", this.formatCurrency(booking.total_price)],
                ["Đã thu", this.formatCurrency(this.paidAmount(booking))],
                [
                    "Còn thu",
                    this.formatCurrency(this.bookingOutstandingAmount(booking)),
                ],
            ];
        },
        occupiedPanelTitle() {
            if (this.selectedBusyBookingLoading) return "Đang tải booking";
            if (this.selectedBusyBooking) return "Thông tin booking";
            if (this.selectedOccupiedInterval?.source === "slot_lock")
                return "Thông tin khóa sân";
            return "Khung giờ đã đặt";
        },
        occupiedPanelSubtitle() {
            const interval = this.selectedOccupiedInterval;
            if (!interval) return "";

            const currentRange = `${this.formatTime(interval.start_time)} - ${this.formatTime(interval.end_time)}`;
            if (this.selectedBusyBooking) {
                return `Đang xem ${currentRange}`;
            }

            return currentRange;
        },
        occupiedSummaryRows() {
            const interval = this.selectedOccupiedInterval;
            const booking = this.selectedBusyBooking;

            if (!interval) return [];

            if (booking) {
                const rows = [
                    ["Mã booking", booking.booking_code || "-"],
                    ["Nguồn đặt", this.bookingSourceLabel(booking.source)],
                    ["Khách", this.bookingCustomerName(booking)],
                    ["Sân", this.bookingCourtText(booking)],
                    [
                        "Ngày",
                        this.formatDate(
                            booking.booking_date || this.form.booking_date,
                        ),
                    ],
                    [
                        "Đang xem",
                        `${this.courtNameById(interval.venue_court_id)} · ${this.formatTime(interval.start_time)} - ${this.formatTime(interval.end_time)}`,
                    ],
                    ["Toàn bộ khung", this.bookingTimeText(booking)],
                    [
                        "Hình thức",
                        this.paymentOptionLabel(booking.payment_option),
                    ],
                    ["Tổng tiền", this.formatCurrency(booking.total_price)],
                    ["Đã thu", this.formatCurrency(this.paidAmount(booking))],
                    [
                        "Còn thu",
                        this.formatCurrency(this.selectedBookingOutstanding),
                    ],
                ];

                if (booking.booking_type === "recurring") {
                    rows.splice(
                        1,
                        0,
                        ["Mã cố định", booking.recurring_group_code || "-"],
                        [
                            "Hiệu lực",
                            `${this.formatDate(booking.recurring_start_date)} - ${this.formatDate(booking.recurring_end_date)}`,
                        ],
                        ["Chu kỳ", this.recurringBookingPatternText(booking)],
                    );
                }

                if (booking.source === "online") {
                    rows.splice(3, 0, [
                        "Tài khoản",
                        booking.customer?.email ||
                            booking.customer?.username ||
                            "-",
                    ]);
                } else {
                    rows.splice(3, 0, [
                        "SĐT khách",
                        this.bookingCustomerPhone(booking),
                    ]);
                }

                return rows;
            }

            return [
                ["Sân", this.courtNameById(interval.venue_court_id)],
                ["Ngày", this.formatDate(this.form.booking_date)],
                [
                    "Giờ",
                    `${this.formatTime(interval.start_time)} - ${this.formatTime(interval.end_time)}`,
                ],
                [
                    "Loại",
                    interval.source === "slot_lock" ? "Khóa sân" : "Đã đặt",
                ],
                ["Lý do", interval.reason || interval.lock_reason || "-"],
            ];
        },
        bookingActionConfirmRows() {
            const booking = this.selectedBusyBooking;

            if (!booking) return [];

            return [
                ["Mã booking", booking.booking_code || "-"],
                [
                    "Khách",
                    `${this.bookingCustomerName(booking)} · ${this.bookingCustomerPhone(booking)}`,
                ],
                ["Sân", this.bookingCourtText(booking)],
                ["Giờ", this.bookingTimeText(booking)],
                ["Tổng tiền", this.formatCurrency(booking.total_price)],
                [
                    "Còn thu",
                    this.formatCurrency(this.selectedBookingOutstanding),
                ],
            ];
        },
        recurringGroupConfirmRows() {
            const group = this.recurringGroupConfirm?.group;
            if (!group) return [];

            return this.buildRecurringGroupRows(group);
        },
        recurringGroupDetailRows() {
            if (!this.recurringGroupDetail) return [];

            return this.buildRecurringGroupRows(this.recurringGroupDetail);
        },
        recurringPreview() {
            if (this.activeTab !== "recurring") return [];
            const start = this.parseDate(this.form.recurring_start_date);
            const end = this.parseDate(this.form.recurring_end_date);
            if (!start || !end || end < start) return [];

            const dates = [];
            const selectedMonthDays = this.monthDaysInput
                .split(",")
                .map((item) => Number(item.trim()))
                .filter((day) => day >= 1 && day <= 31);

            for (
                let date = new Date(start);
                date <= end && dates.length <= 130;
                date.setDate(date.getDate() + 1)
            ) {
                const current = new Date(date);
                const dayDiff = Math.floor((current - start) / 86400000);
                const weekDiff = Math.floor(dayDiff / 7);
                const monthDiff =
                    (current.getFullYear() - start.getFullYear()) * 12 +
                    (current.getMonth() - start.getMonth());
                let match = false;

                if (this.form.recurrence_type === "daily") {
                    match = dayDiff % this.form.recurrence_interval === 0;
                } else if (this.form.recurrence_type === "weekly") {
                    match =
                        weekDiff % this.form.recurrence_interval === 0 &&
                        this.form.recurrence_days_of_week.includes(
                            this.dayIndex(current),
                        );
                } else {
                    match =
                        monthDiff % this.form.recurrence_interval === 0 &&
                        selectedMonthDays.includes(current.getDate());
                }

                if (match) dates.push(this.formatIsoDate(current));
            }

            return dates;
        },
        recurringPreviewConflictMap() {
            return (this.recurringPreviewResult?.conflicts || []).reduce(
                (result, conflict) => {
                    const date = conflict.date;
                    if (!date) return result;
                    if (!result[date]) result[date] = [];
                    result[date].push(conflict);
                    return result;
                },
                {},
            );
        },
        recurringPreviewRows() {
            const backendDates =
                this.recurringPreviewResult?.dates?.length &&
                this.sameDateSet(
                    this.recurringPreviewResult.dates,
                    this.recurringPreview,
                )
                    ? this.recurringPreviewResult.dates
                    : this.recurringPreview;

            return backendDates.map((date) => {
                const conflicts = this.recurringPreviewConflictMap[date] || [];

                return {
                    date,
                    label: this.formatDate(date),
                    weekday: this.weekdayLabel(date),
                    status: conflicts.length
                        ? "conflict"
                        : this.recurringPreviewResult
                          ? "available"
                          : "pending",
                    conflicts,
                };
            });
        },
        recurringPreviewStats() {
            const total = this.recurringPreviewRows.length;
            const conflict = this.recurringPreviewRows.filter(
                (row) => row.status === "conflict",
            ).length;

            return {
                total,
                available: this.recurringPreviewResult
                    ? Math.max(total - conflict, 0)
                    : 0,
                conflict,
            };
        },
        normalizedWalkInName() {
            return String(this.form.walk_in_name || "")
                .trim()
                .replace(/\s+/g, " ");
        },
        normalizedWalkInPhone() {
            return String(this.form.walk_in_phone || "")
                .trim()
                .replace(/[\s().-]+/g, "");
        },
        walkInNameError() {
            if (!this.normalizedWalkInName) return "Vui lòng nhập tên khách.";
            if (this.normalizedWalkInName.length < 2)
                return "Tên khách phải có ít nhất 2 ký tự.";
            if (this.normalizedWalkInName.length > 100)
                return "Tên khách không được vượt quá 100 ký tự.";
            if (!WALK_IN_NAME_PATTERN.test(this.normalizedWalkInName))
                return "Tên khách chỉ được chứa chữ cái và dấu câu thông dụng.";
            return "";
        },
        walkInPhoneError() {
            if (!this.normalizedWalkInPhone)
                return "Vui lòng nhập số điện thoại khách.";
            if (!WALK_IN_PHONE_PATTERN.test(this.normalizedWalkInPhone))
                return "Nhập số Việt Nam hợp lệ, ví dụ 0901234567.";
            return "";
        },
        canSubmitCounter() {
            return (
                this.hasCounterSelection &&
                !this.walkInNameError &&
                !this.walkInPhoneError &&
                this.form.payment_option &&
                !this.submitting
            );
        },
        hasValidCounterContact() {
            return !this.walkInNameError && !this.walkInPhoneError;
        },
        canShowCounterVouchers() {
            return (
                this.contactTouched.name &&
                this.contactTouched.phone &&
                this.hasValidCounterContact
            );
        },
        canSubmitRecurring() {
            const weeklyReady =
                this.form.recurrence_type !== "weekly" ||
                this.recurringSelectedDays.every(
                    (day) => (this.recurringDayRanges[day] || []).length,
                );

            return (
                this.hasRecurringSelection &&
                weeklyReady &&
                !this.walkInNameError &&
                !this.walkInPhoneError &&
                this.form.payment_option &&
                this.recurringPreview.length > 0 &&
                !this.submitting
            );
        },
    },
    watch: {
        "form.recurring_start_date"() {
            if (this.activeTab === "recurring")
                this.handleRecurringStartDateChange();
            this.queueRecurringPreview();
        },
        "form.recurring_end_date"() {
            if (this.activeTab === "recurring") {
                this.syncRecurringEndDate();
                this.queueRecurringPreview();
            }
        },
        "form.recurrence_type"() {
            if (this.activeTab === "recurring") this.clearVoucherSelection();
            this.queueRecurringPreview();
        },
        "form.recurrence_interval"() {
            if (this.activeTab === "recurring") this.clearVoucherSelection();
            this.queueRecurringPreview();
        },
        "form.recurrence_days_of_week": {
            deep: true,
            handler() {
                if (this.activeTab === "recurring")
                    this.clearVoucherSelection();
                this.syncRecurringWeekdayState();
                this.queueRecurringPreview();
            },
        },
        monthDaysInput() {
            if (this.activeTab === "recurring") this.clearVoucherSelection();
            this.queueRecurringPreview();
        },
        selectedSlotKeys: {
            deep: true,
            handler() {
                this.queueRecurringPreview();
            },
        },
        recurringWeekdaySlotKeys: {
            deep: true,
            handler() {
                this.queueRecurringPreview();
            },
        },
        activeTab() {
            this.queueRecurringPreview();
        },
        "$route.name"() {
            this.handleRouteModeChange();
        },
        "$route.query.tab"() {
            this.handleRouteModeChange();
        },
        "$route.query.view"() {
            this.handleRouteModeChange();
        },
    },
    async created() {
        this.syncActiveTabFromRoute();
        await this.loadOwnerData();
    },
    mounted() {
        window.addEventListener(
            "owner-cluster-changed",
            this.handleExternalClusterChange,
        );
    },
    beforeUnmount() {
        window.removeEventListener(
            "owner-cluster-changed",
            this.handleExternalClusterChange,
        );
        this.clearCounterQrPolling();
        clearTimeout(this.recurringPreviewTimer);
    },
    methods: {
        syncActiveTabFromRoute() {
            if (this.$route.name === "staff-booking-list") {
                this.activeTab = "bookingList";
                return;
            }

            if (this.$route.query?.view === "list") {
                this.activeTab = "bookingList";
                return;
            }

            if (this.$route.query?.tab === "recurring") {
                this.activeTab = "recurring";
                return;
            }

            this.activeTab = "counter";
        },
        async handleRouteModeChange() {
            const before = this.activeTab;
            this.syncActiveTabFromRoute();

            if (before === this.activeTab || !this.selectedClusterId) return;

            await this.refreshActiveTab();
        },
        sameDateSet(a = [], b = []) {
            if (a.length !== b.length) return false;
            return [...a].sort().join("|") === [...b].sort().join("|");
        },
        weekdayLabel(date) {
            const parsed = this.parseDate(date);
            if (!parsed) return "";

            return (
                this.weekDays.find((day) => day.value === this.dayIndex(parsed))
                    ?.label || ""
            );
        },
        queueRecurringPreview(delay = 260) {
            clearTimeout(this.recurringPreviewTimer);

            if (this.activeTab !== "recurring") return;

            this.recurringPreviewTimer = setTimeout(
                () => this.loadRecurringPreview(),
                delay,
            );
        },
        async loadRecurringPreview() {
            if (
                this.activeTab !== "recurring" ||
                !this.hasCounterSelection ||
                !this.recurringPreview.length
            ) {
                this.recurringPreviewResult = null;
                this.recurringPreviewError = "";
                this.recurringPreviewLoading = false;
                return;
            }

            const requestId = ++this.recurringPreviewRequestId;
            this.recurringPreviewLoading = true;
            this.recurringPreviewError = "";

            try {
                const response = await ownerBookingService.previewRecurring(
                    this.recurringPayload(),
                );
                if (requestId !== this.recurringPreviewRequestId) return;

                this.recurringPreviewResult = response.data || response;
            } catch (error) {
                if (requestId !== this.recurringPreviewRequestId) return;

                this.recurringPreviewResult = null;
                this.recurringPreviewError =
                    error.message || "Chưa kiểm tra được lịch cố định.";
            } finally {
                if (requestId === this.recurringPreviewRequestId) {
                    this.recurringPreviewLoading = false;
                }
            }
        },
        async setActiveTab(tab) {
            this.activeTab = tab;
            this.error = "";
            this.notice = "";
            this.selectionError = "";
            this.clearVoucherSelection();
            this.syncPaymentOption();

            if (tab === "recurringList") {
                await this.loadRecurringGroups();
                return;
            }

            if (tab === "bookingList") {
                await this.loadBookingList();
                return;
            }

            await this.loadSchedule();
        },
        async refreshActiveTab() {
            if (this.activeTab === "bookingList") {
                await this.loadBookingList();
                return;
            }

            if (this.activeTab === "recurringList") {
                await this.loadRecurringGroups();
                return;
            }

            await this.loadSchedule();
        },
        routeBookingFocusQuery() {
            return {
                id: this.$route.query.booking_id || "",
                code: this.$route.query.booking_code || "",
            };
        },
        hasRouteBookingFocus() {
            const focus = this.routeBookingFocusQuery();
            return Boolean(focus.id || focus.code);
        },
        async shiftCounterDate(days) {
            const current = this.parseDate(this.form.booking_date) || new Date();
            const end =
                this.parseDate(this.form.booking_end_date) || new Date(current);
            const rangeDays = Math.max(
                Math.round((end.getTime() - current.getTime()) / 86400000),
                0,
            );
            current.setDate(current.getDate() + days);
            this.form.booking_date = this.formatIsoDate(current);
            const shiftedEnd = new Date(current);
            shiftedEnd.setDate(shiftedEnd.getDate() + rangeDays);
            this.form.booking_end_date = this.formatIsoDate(shiftedEnd);
            this.counterDatePickerOpen = false;
            await this.handleScheduleDateChange();
        },
        async setCounterDateToday() {
            this.form.booking_date = this.today;
            this.form.booking_end_date = this.today;
            this.counterDatePickerOpen = false;
            await this.handleScheduleDateChange();
        },
        async handleCounterStartDateUpdate(value) {
            if (!value || value === this.form.booking_date) return;

            this.form.booking_date = value;
            await this.handleScheduleDateChange();
        },
        handleCounterEndDateUpdate(value) {
            if (!value || value === this.form.booking_end_date) return;

            this.form.booking_end_date = value;
            this.handleCounterEndDateChange();
        },
        handleCounterRangeChange() {
            this.counterDatePickerOpen = false;
        },
        async handleScheduleDateChange() {
            if (
                !this.form.booking_end_date ||
                this.form.booking_end_date < this.form.booking_date
            ) {
                this.form.booking_end_date = this.form.booking_date;
            }
            this.handleCounterEndDateChange();
            await this.loadSchedule();
        },
        handleCounterEndDateChange() {
            if (this.form.booking_end_date < this.form.booking_date) {
                this.form.booking_end_date = this.form.booking_date;
            }
            if (this.counterDateCount > 1 && this.form.collection_mode === "transfer") {
                this.form.collection_mode = "cash";
                this.applyCounterCollectionMode();
            }
            this.clearVoucherSelection();
            if (this.canShowCounterVouchers && this.hasCounterSelection) {
                void this.loadEligibleVouchers();
            }
        },
        async handleRecurringStartDateChange() {
            this.syncRecurringEndDate();
            this.clearVoucherSelection();
            await this.loadSchedule();
        },
        syncRecurringEndDate() {
            if (
                this.form.recurring_start_date &&
                this.form.recurring_end_date < this.form.recurring_start_date
            ) {
                this.form.recurring_end_date = this.form.recurring_start_date;
            }
        },
        async loadOwnerData() {
            this.error = "";
            const preferredClusterId =
                this.$route.query.venue_cluster_id ||
                localStorage.getItem("selected_cluster") ||
                "";
            let preferredClusterLoad = null;

            if (preferredClusterId) {
                this.selectedClusterId = preferredClusterId;
                this.applyRouteBookingFilters();
                preferredClusterLoad = this.handleClusterChange()
                    .then(() => true)
                    .catch(() => false);
            }

            try {
                const response = await venueClusterService.getClusters();
                this.clusters = response.data || [];
                const preferredCluster = this.clusters.find(
                    (cluster) =>
                        String(cluster.id) === String(preferredClusterId),
                );
                const resolvedClusterId =
                    preferredCluster?.id || this.clusters[0]?.id || "";
                const preferredLoaded = preferredClusterLoad
                    ? await preferredClusterLoad
                    : false;

                this.selectedClusterId = resolvedClusterId;
                this.applyRouteBookingFilters();

                if (
                    !preferredLoaded ||
                    String(resolvedClusterId) !== String(preferredClusterId)
                ) {
                    await this.handleClusterChange();
                }
            } catch (error) {
                this.error = error.message || "Không thể tải dữ liệu cụm sân.";
            } finally {
                this.bootstrapping = false;
            }
        },
        applyRouteBookingFilters() {
            if (!this.hasRouteBookingFocus()) return;

            this.activeTab = "counter";

            if (this.$route.query.booking_date) {
                this.form.booking_date = String(
                    this.$route.query.booking_date,
                ).slice(0, 10);
            }
        },
        async handleClusterChange() {
            this.selectedCourtTypeId = "";
            this.selectedSlotKeys = [];
            this.selectedGridCourtId = "";
            this.form.venue_court_id = "";

            if (!this.selectedClusterId) return;
            this.clusterLoading = true;
            localStorage.setItem("selected_cluster", this.selectedClusterId);
            this.notifyOwnerClusterChanged();

            try {
                const requests = [
                    this.loadClusterDetail(),
                    this.loadCourts(),
                ];

                if (this.activeTab === "recurringList") {
                    this.recurringGroupFilters.venue_court_id = "";
                    requests.push(this.loadRecurringGroups());
                } else if (this.activeTab === "bookingList") {
                    this.bookingListFilters.venue_court_id = "";
                    requests.push(this.loadBookingList());
                } else {
                    requests.push(this.loadSchedule());
                }

                await Promise.all(requests);
                this.syncPaymentOption();
            } finally {
                this.clusterLoading = false;
            }
        },
        async handleExternalClusterChange(event) {
            const clusterId =
                event?.detail?.id || localStorage.getItem("selected_cluster");

            if (
                !clusterId ||
                String(clusterId) === String(this.selectedClusterId)
            ) {
                return;
            }

            this.selectedClusterId = clusterId;
            await this.handleClusterChange();
        },
        notifyOwnerClusterChanged() {
            window.dispatchEvent(
                new CustomEvent("owner-cluster-changed", {
                    detail: this.selectedCluster || {
                        id: this.selectedClusterId,
                    },
                }),
            );
        },
        async loadClusterDetail() {
            try {
                const response = await ownerBookingConfigService.list();
                this.selectedClusterDetail = (response.data || []).find(
                    (cluster) => String(cluster.id) === String(this.selectedClusterId),
                ) || null;
            } catch {
                this.selectedClusterDetail = null;
            }
        },
        async loadCourts() {
            const response = await venueClusterService.getCourts(
                this.selectedClusterId,
                { status: "active" },
            );
            this.courts = response.data || [];
            const routeCourtId = this.hasRouteBookingFocus()
                ? this.$route.query.venue_court_id
                : "";
            const routeCourt = routeCourtId
                ? this.courts.find(
                      (court) => String(court.id) === String(routeCourtId),
                  )
                : null;
            this.form.venue_court_id =
                routeCourt?.id || this.courts[0]?.id || "";
        },
        async loadSchedule() {
            if (!this.selectedClusterId) return;

            this.scheduleLoading = true;
            this.scheduleError = "";
            this.selectionError = "";
            this.selectedSlotKeys = [];
            this.selectedGridCourtId = "";
            this.selectedOccupiedInterval = null;
            this.selectedBusyBooking = null;
            this.bookingActionConfirm = null;
            this.clearVoucherSelection();

            try {
                const response = await ownerBookingService.schedule({
                    venue_cluster_id: this.selectedClusterId,
                    booking_date:
                        this.activeTab === "recurring"
                            ? this.form.recurring_start_date
                            : this.form.booking_date,
                    court_type_id: this.selectedCourtTypeId,
                    booking_type:
                        this.activeTab === "recurring" ? "recurring" : "single",
                });

                this.scheduleSlots = response.time_slots || [];
                this.scheduleCourts = response.courts || [];
                this.scheduleSlotStatuses = response.slot_statuses || [];
                this.scheduleBusyIntervals = response.busy_intervals || [];

                this.ensureActiveTimePeriod();
                this.syncCounterRangeFields();
                this.scheduleLoading = false;
                void this.focusRouteBooking();
            } catch (error) {
                this.scheduleError = error.message || "Không thể tải lịch sân.";
                this.scheduleLoading = false;
            }
        },
        async focusRouteBooking() {
            if (!this.hasRouteBookingFocus() || this.activeTab !== "counter") {
                return;
            }

            const focus = this.routeBookingFocusQuery();
            const interval = this.scheduleBusyIntervals.find((item) => {
                const matchesId =
                    focus.id && String(item.booking_id) === String(focus.id);
                const matchesCode =
                    focus.code &&
                    String(item.booking_code) === String(focus.code);

                return matchesId || matchesCode;
            });

            if (!interval) {
                this.notice = focus.code
                    ? `Đã mở lịch nhưng chưa tìm thấy booking ${focus.code} trong ngày/sân này.`
                    : "Đã mở lịch nhưng chưa tìm thấy booking cần xem.";
                return;
            }

            const court = this.scheduleCourts.find(
                (item) => String(item.id) === String(interval.venue_court_id),
            ) || { id: interval.venue_court_id };
            const slot = this.scheduleSlots.find(
                (item) => item.start_time === interval.start_time,
            ) || {
                start_time: interval.start_time,
                end_time: interval.end_time,
            };

            this.activeTimePeriod = this.periodKeyForTime(interval.start_time);
            await this.openOccupiedSlot(court, slot);
            this.scrollSelectedBookingIntoView();
        },
        periodKeyForTime(time) {
            const minutes = this.timeToMinutes(time);
            const period = this.dynamicTimePeriods.find(
                (item) => minutes >= item.start && minutes < item.end,
            );

            return period?.key || this.activeTimePeriod;
        },
        ensureActiveTimePeriod() {
            if (
                !this.dynamicTimePeriods.some(
                    (period) => period.key === this.activeTimePeriod,
                )
            ) {
                this.activeTimePeriod =
                    this.dynamicTimePeriods[0]?.key || "morning";
            }
        },
        scrollSelectedBookingIntoView() {
            this.$nextTick(() => {
                const target = document.querySelector(".occupied-detail");
                target?.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        },
        async loadEligibleVouchers(code = "") {
            this.voucherError = "";
            const firstRange = this.selectedSlotRanges[0];
            const courtId =
                firstRange?.venue_court_id || this.form.venue_court_id;

            if (
                !this.selectedClusterId ||
                !courtId ||
                this.voucherBaseAmount <= 0 ||
                !this.canShowCounterVouchers
            ) {
                this.eligibleVouchers = [];
                this.selectedVoucherId = "";
                return;
            }

            const requestId = ++this.voucherRequestId;
            this.voucherLoading = true;

            try {
                const response = await ownerBookingService.eligibleVouchers({
                    venue_cluster_id: this.selectedClusterId,
                    venue_court_id: courtId,
                    booking_type:
                        this.activeTab === "recurring" ? "recurring" : "single",
                    amount: this.voucherBaseAmount,
                    usage_count:
                        this.activeTab === "recurring"
                            ? Math.max(this.recurringPreview.length, 1)
                            : this.counterDateCount,
                    voucher_code: code || "",
                    walk_in_phone: this.normalizedWalkInPhone,
                });

                if (requestId !== this.voucherRequestId) return;

                this.eligibleVouchers = response.data || [];

                if (
                    this.selectedVoucherId &&
                    !this.eligibleVouchers.some(
                        (voucher) => voucher.id === this.selectedVoucherId,
                    )
                ) {
                    this.selectedVoucherId = "";
                }

                if (code) {
                    const matched = this.eligibleVouchers[0];
                    if (matched) {
                        this.selectedVoucherId = matched.id;
                        this.voucherCodeInput = matched.code;
                    } else {
                        this.voucherError =
                            "Mã voucher không đủ điều kiện cho booking này.";
                    }
                }
            } catch (error) {
                if (requestId !== this.voucherRequestId) return;
                this.eligibleVouchers = [];
                this.selectedVoucherId = "";
                this.voucherError =
                    error.message || "Không thể kiểm tra voucher.";
            } finally {
                if (requestId === this.voucherRequestId) {
                    this.voucherLoading = false;
                }
            }
        },
        selectVoucher(voucher) {
            if (!voucher?.id) return;
            this.selectedVoucherId =
                this.selectedVoucherId === voucher.id ? "" : voucher.id;
            if (this.selectedVoucherId) {
                this.voucherCodeInput = voucher.code || "";
                this.voucherError = "";
            }
        },
        async applyVoucherCode() {
            const code = this.voucherCodeInput.trim();
            if (!code) {
                this.selectedVoucherId = "";
                await this.loadEligibleVouchers();
                return;
            }

            await this.loadEligibleVouchers(code);
        },
        clearVoucherSelection() {
            this.eligibleVouchers = [];
            this.selectedVoucherId = "";
            this.voucherCodeInput = "";
            this.voucherError = "";
            this.voucherLoading = false;
            this.voucherRequestId += 1;
        },
        handleVoucherSubmitError(error) {
            const message = String(error?.message || "");
            const fieldErrors = error?.data?.errors || {};
            const hasVoucherError =
                Boolean(fieldErrors.voucher_code || fieldErrors.voucher_id) ||
                message.toLowerCase().includes("voucher");

            if (!hasVoucherError) return;

            this.selectedVoucherId = "";
            this.voucherError =
                message ||
                "Voucher không còn đủ điều kiện. Vui lòng chọn voucher khác hoặc bỏ áp dụng voucher.";
            this.loadEligibleVouchers();
        },
        async loadRecurringGroups() {
            if (!this.selectedClusterId) return;

            this.recurringGroupsLoading = true;
            this.error = "";

            try {
                const response = await ownerBookingService.recurringGroups({
                    venue_cluster_id: this.selectedClusterId,
                    ...this.recurringGroupFilters,
                });
                this.recurringGroups = response.data || [];
            } catch (error) {
                this.error =
                    error.message || "Không thể tải danh sách lịch cố định.";
                this.recurringGroups = [];
            } finally {
                this.recurringGroupsLoading = false;
            }
        },
        async loadBookingList() {
            if (!this.selectedClusterId) return;

            this.bookingListLoading = true;
            this.error = "";

            try {
                const response = await ownerBookingService.list({
                    venue_cluster_id: this.selectedClusterId,
                    booking_type: "single",
                    ...this.bookingListFilters,
                });
                this.bookingList = response.data || [];
            } catch (error) {
                this.error =
                    error.message || "Không thể tải danh sách booking.";
                this.bookingList = [];
            } finally {
                this.bookingListLoading = false;
            }
        },
        openBookingListDetail(booking) {
            this.bookingListDetail = booking;
        },
        closeBookingListDetail() {
            this.bookingListDetail = null;
        },
        slotStatus(courtId, slot) {
            if (!slot) return null;
            return (
                this.scheduleSlotStatuses.find(
                    (status) =>
                        String(status.venue_court_id) === String(courtId) &&
                        status.start_time === slot.start_time,
                ) || null
            );
        },
        busyInterval(courtId, slot) {
            if (!slot) return null;
            return this.scheduleBusyIntervals.find(
                (item) =>
                    String(item.venue_court_id) === String(courtId) &&
                    this.timeToMinutes(item.start_time) <
                        this.timeToMinutes(slot.end_time) &&
                    this.timeToMinutes(item.end_time) >
                        this.timeToMinutes(slot.start_time),
            );
        },
        isSlotBusy(courtId, slot) {
            return Boolean(this.busyInterval(courtId, slot));
        },
        isScheduleUnavailable(courtId, slot) {
            const status = this.slotStatus(courtId, slot);
            return Boolean(
                status &&
                    !status.is_available &&
                    status.slot_status !== "too_early",
            );
        },
        slotKey(courtId, slot) {
            return `${courtId}|${slot?.start_time || ""}`;
        },
        slotEntriesFromKeys(keys = []) {
            return keys
                .map((key) => {
                    const [courtId, startTime] = key.split("|");
                    const court = this.scheduleCourts.find(
                        (item) => String(item.id) === String(courtId),
                    );
                    const slot = this.bookableScheduleSlots.find(
                        (item) => item.start_time === startTime,
                    );
                    return court && slot ? { courtId, court, slot } : null;
                })
                .filter(Boolean)
                .sort((a, b) => {
                    const courtSort = a.court.name.localeCompare(b.court.name);
                    if (courtSort !== 0) return courtSort;
                    return (
                        this.timeToMinutes(a.slot.start_time) -
                        this.timeToMinutes(b.slot.start_time)
                    );
                });
        },
        slotRangesFromKeys(keys = []) {
            const ranges = [];

            this.slotEntriesFromKeys(keys).forEach(({ courtId, court, slot }) => {
                const current = ranges[ranges.length - 1];
                const slotStart = this.timeToMinutes(slot.start_time);
                const slotEnd = this.timeToMinutes(slot.end_time);
                if (
                    !current ||
                    current.venue_court_id !== courtId ||
                    slotStart > this.timeToMinutes(current.end_time)
                ) {
                    ranges.push({
                        venue_court_id: courtId,
                        court,
                        start_time: slot.start_time,
                        end_time: slot.end_time,
                    });
                    return;
                }

                if (slotEnd > this.timeToMinutes(current.end_time)) {
                    current.end_time = slot.end_time;
                }
            });

            return ranges;
        },
        rangeListText(ranges = []) {
            if (!ranges.length) return "-";

            return ranges
                .map(
                    (range) =>
                        `${range.court?.name || "Sân"}: ${this.formatTime(range.start_time)} - ${this.formatTime(range.end_time)}`,
                )
                .join(", ");
        },
        recurringWeekdayTimeText(day) {
            const ranges = this.recurringDayRanges[day] || [];
            if (!ranges.length) return "Chưa chọn giờ";
            return ranges
                .map(
                    (range) =>
                        `${this.formatTime(range.start_time)} - ${this.formatTime(range.end_time)}`,
                )
                .join(", ");
        },
        recurringWeekdayCourtText(day) {
            const ranges = this.recurringDayRanges[day] || [];
            const names = [...new Set(ranges.map((range) => range.court?.name))].filter(Boolean);
            if (!names.length) return "Chưa chọn sân";
            if (names.length <= 2) return names.join(", ");
            return `${names.length} sân`;
        },
        toggleRecurringWeekday(day) {
            const selected = this.form.recurrence_days_of_week.includes(day);
            if (selected && this.form.recurrence_days_of_week.length > 1) {
                this.form.recurrence_days_of_week =
                    this.form.recurrence_days_of_week.filter(
                        (item) => item !== day,
                    );
                if (this.recurringActiveWeekday === day) {
                    this.recurringActiveWeekday =
                        this.form.recurrence_days_of_week[0] ?? day;
                }
                return;
            }

            if (!selected) {
                this.form.recurrence_days_of_week = [
                    ...this.form.recurrence_days_of_week,
                    day,
                ].sort((a, b) => a - b);
            }

            this.recurringActiveWeekday = day;
            if (!this.recurringWeekdaySlotKeys[day]) {
                this.recurringWeekdaySlotKeys = {
                    ...this.recurringWeekdaySlotKeys,
                    [day]: [],
                };
            }
        },
        syncRecurringWeekdayState() {
            const selected = this.recurringSelectedDays;
            if (!selected.length) return;

            const next = {};
            selected.forEach((day) => {
                next[day] = this.recurringWeekdaySlotKeys[day] || [];
            });
            this.recurringWeekdaySlotKeys = next;
            if (!selected.includes(this.recurringActiveWeekday)) {
                this.recurringActiveWeekday = selected[0];
            }
        },
        applyActiveWeekdayScheduleToSelected() {
            const source = this.activeRecurringWeekdayKeys;
            if (!source.length) return;

            const next = { ...this.recurringWeekdaySlotKeys };
            this.recurringSelectedDays.forEach((day) => {
                next[day] = [...source];
            });
            this.recurringWeekdaySlotKeys = next;
            this.queueRecurringPreview();
        },
        clearActiveWeekdaySchedule() {
            this.recurringWeekdaySlotKeys = {
                ...this.recurringWeekdaySlotKeys,
                [this.recurringActiveWeekday]: [],
            };
            this.queueRecurringPreview();
        },
        toggleMonthDay(day) {
            const next = this.selectedMonthDays.includes(day)
                ? this.selectedMonthDays.filter((item) => item !== day)
                : [...this.selectedMonthDays, day];
            this.setMonthDays(next);
        },
        setMonthDays(days = []) {
            this.monthDaysInput = [...new Set(days)]
                .filter((day) => day >= 1 && day <= 31)
                .sort((a, b) => a - b)
                .join(", ");
        },
        isSlotDisabled(courtId, slot) {
            if (!courtId || !slot) return true;

            if (!this.isSlotBusy(courtId, slot) && this.isScheduleUnavailable(courtId, slot)) {
                return true;
            }

            if (
                (this.isViewingPastScheduleDate ||
                    this.isSlotInPastForActiveDate(slot)) &&
                !this.isSlotBusy(courtId, slot)
            ) {
                return true;
            }

            return false;
        },
        isSlotSelected(courtId, slot) {
            return this.activeSelectionKeys.includes(this.slotKey(courtId, slot));
        },
        slotButtonClass(courtId, slot) {
            const selected = this.isSlotSelected(courtId, slot);
            const busy = this.isSlotBusy(courtId, slot);
            const scheduleUnavailable = !busy && this.isScheduleUnavailable(courtId, slot);
            const interval = this.busyInterval(courtId, slot);
            const tone = interval
                ? this.paymentStateTone(this.intervalPaymentState(interval))
                : "";
            const viewing =
                this.selectedOccupiedInterval &&
                String(this.selectedOccupiedInterval.venue_court_id) ===
                    String(courtId) &&
                this.timeToMinutes(this.selectedOccupiedInterval.start_time) <
                    this.timeToMinutes(slot.end_time) &&
                this.timeToMinutes(this.selectedOccupiedInterval.end_time) >
                    this.timeToMinutes(slot.start_time);

            return {
                selected,
                busy: busy && !scheduleUnavailable,
                unavailable: scheduleUnavailable,
                viewing,
                locked: tone === "locked",
                "booked-paid": tone === "paid",
                "booked-online": tone === "online",
                "booked-counter": tone === "counter",
                "pay-later": tone === "later",
                overdue: tone === "overdue",
            };
        },
        slotPriceLabel(courtId, slot) {
            const interval = this.busyInterval(courtId, slot);
            const status = this.slotStatus(courtId, slot);

            if (interval) {
                if (interval.source === "slot_lock") return "Khóa sân";
                return this.paymentStateLabel(
                    this.intervalPaymentState(interval),
                );
            }
            if (!status || !status.is_available) {
                return "Không thể chọn";
            }

            return this.formatCurrency(status.price);
        },
        slotActionTitle(court, slot) {
            if (!slot) return "";
            const start = this.formatTime(slot.start_time);
            const end = this.formatTime(slot.end_time);
            const courtName = court?.name || "Sân";
            const selected = this.isSlotSelected(court?.id, slot);
            const interval = this.busyInterval(court?.id, slot);

            if (this.isSlotDisabled(court?.id, slot)) {
                if (
                    (this.isViewingPastScheduleDate ||
                        this.isSlotInPastForActiveDate(slot)) &&
                    !interval
                ) {
                    return `${courtName} · ${start} - ${end} đã quá thời hạn đặt.`;
                }

                if (interval?.source === "booking") {
                    const customer = this.intervalCustomerName(interval);
                    const phone = this.intervalCustomerPhone(interval);
                    const code = interval.booking_code || "Booking";
                    const paid =
                        Number(interval.outstanding_amount || 0) <= 0
                            ? "đã thanh toán"
                            : "còn thu";

                    return `${courtName} · ${start} - ${end} · ${code} · ${customer}${phone ? ` · ${phone}` : ""} · ${paid}`;
                }

                if (interval?.source === "slot_lock") {
                    return `${courtName} · ${start} - ${end} bị khóa${interval.reason ? ` · ${interval.reason}` : ""}`;
                }

                const status = this.slotStatus(court?.id, slot);
                if (status && !status.is_available) {
                    return `${courtName} · ${start} - ${end} không thể chọn`;
                }

                return `${courtName} · ${start} - ${end} không thể chọn`;
            }

            return selected
                ? `Bỏ chọn ${courtName} · ${start} - ${end}`
                : `Chọn ${courtName} · ${start} - ${end} · ${this.slotPriceLabel(court?.id, slot)}`;
        },
        isSlotInPastForActiveDate(slot) {
            if (!slot || !this.activeScheduleDate) return false;
            const date = String(this.activeScheduleDate).slice(0, 10);

            if (date < this.today) return true;
            if (date > this.today) return false;

            const now = new Date();
            const nowMinutes = now.getHours() * 60 + now.getMinutes();

            return this.timeToMinutes(slot.start_time) <= nowMinutes;
        },
        intervalCustomerName(interval) {
            return (
                interval?.customer?.full_name ||
                interval?.customer?.username ||
                interval?.walk_in_name ||
                "Khách vãng lai"
            );
        },
        intervalCustomerPhone(interval) {
            return interval?.customer?.phone || interval?.walk_in_phone || "";
        },
        syncCounterRangeFields() {
            this.selectionError = "";
            const ranges = this.selectedSlotRanges;

            if (!ranges.length) {
                this.form.start_time = "06:00";
                this.form.end_time = "06:30";
                this.form.venue_court_id = "";
                this.selectedGridCourtId = "";
                return;
            }

            const starts = ranges.map((range) =>
                this.timeToMinutes(range.start_time),
            );
            const ends = ranges.map((range) =>
                this.timeToMinutes(range.end_time),
            );
            this.form.start_time = this.minutesToTime(Math.min(...starts));
            this.form.end_time = this.minutesToTime(Math.max(...ends));
            this.form.venue_court_id = ranges[0].venue_court_id;
            this.selectedGridCourtId = ranges[0].venue_court_id;
        },
        toggleSlot(court, slot) {
            if (this.isSlotBusy(court?.id, slot)) {
                this.openOccupiedSlot(court, slot);
                return;
            }

            if (this.activeTab === "recurring") {
                this.selectRecurringSlot(court, slot);
                return;
            }

            const key = this.slotKey(court.id, slot);
            this.selectionError = "";
            this.selectedOccupiedInterval = null;
            this.selectedBusyBooking = null;
            this.counterDrawerOpen = false;
            this.selectedSlotKeys = this.selectedSlotKeys.includes(key)
                ? this.selectedSlotKeys.filter((item) => item !== key)
                : [...this.selectedSlotKeys, key];
            this.syncCounterRangeFields();
            this.loadEligibleVouchers();
        },
        selectRecurringSlot(court, slot) {
            if (!court?.id || !slot) return;

            const clickedKey = this.slotKey(court.id, slot);
            const day = this.recurringActiveWeekday;
            const currentKeys = this.recurringWeekdaySlotKeys[day] || [];
            this.selectionError = "";
            this.recurringWeekdaySlotKeys = {
                ...this.recurringWeekdaySlotKeys,
                [day]: currentKeys.includes(clickedKey)
                    ? currentKeys.filter((item) => item !== clickedKey)
                    : [...currentKeys, clickedKey],
            };
            this.syncCounterRangeFields();
            this.loadEligibleVouchers();
        },
        async openOccupiedSlot(court, slot) {
            const interval = this.busyInterval(court?.id, slot);
            this.selectedSlotKeys = [];
            this.syncCounterRangeFields();
            this.selectedOccupiedInterval = interval
                ? {
                      ...interval,
                      venue_court_id: court?.id || interval.venue_court_id,
                  }
                : {
                      venue_court_id: court?.id,
                      start_time: slot.start_time,
                      end_time: slot.end_time,
                      source: "busy",
                  };
            this.selectedBusyBooking = null;
            this.counterDrawerOpen = true;
            this.counterQr = null;
            this.qrModalOpen = false;
            this.clearCounterQrPolling();

            if (!this.selectedOccupiedInterval?.booking_id) return;

            this.selectedBusyBookingLoading = true;
            try {
                const response = await ownerBookingService.show(
                    this.selectedOccupiedInterval.booking_id,
                );
                this.selectedBusyBooking = response.data || response;
            } catch (error) {
                this.error =
                    error.message || "Không thể tải thông tin booking.";
            } finally {
                this.selectedBusyBookingLoading = false;
            }
        },
        validateContactField(field) {
            this.contactTouched[field] = true;

            if (field === "name") {
                this.form.walk_in_name = this.normalizedWalkInName;
            } else {
                this.form.walk_in_phone = this.normalizedWalkInPhone;
            }

            if (this.canShowCounterVouchers && this.hasCounterSelection) {
                void this.loadEligibleVouchers();
            } else {
                this.clearVoucherSelection();
            }
        },
        handleContactInput(field) {
            this.contactTouched[field] = false;
            this.clearVoucherSelection();
        },
        async submitCounter() {
            if (!this.canSubmitCounter) return;
            this.submitting = true;
            this.error = "";
            this.notice = "";
            this.counterQr = null;
            this.qrModalOpen = false;
            this.clearCounterQrPolling();

            try {
                const timeRanges = this.selectedSlotRanges.map((range) => ({
                    venue_court_id: range.venue_court_id,
                    start_time: this.withSeconds(
                        this.formatTime(range.start_time),
                    ),
                    end_time: this.withSeconds(this.formatTime(range.end_time)),
                }));
                const firstRange = [...timeRanges].sort(
                    (a, b) =>
                        this.timeToMinutes(a.start_time) -
                        this.timeToMinutes(b.start_time),
                )[0];
                const lastRange = [...timeRanges].sort(
                    (a, b) =>
                        this.timeToMinutes(b.end_time) -
                        this.timeToMinutes(a.end_time),
                )[0];
                const response = await ownerBookingService.createCounter({
                    venue_court_id: firstRange.venue_court_id,
                    walk_in_name: this.form.walk_in_name,
                    walk_in_phone: this.form.walk_in_phone,
                    booking_date: this.form.booking_date,
                    booking_dates: this.counterBookingDates,
                    start_time: firstRange.start_time,
                    end_time: lastRange.end_time,
                    time_ranges: timeRanges,
                    payment_option:
                        this.form.collection_mode === "later"
                            ? "no_prepay"
                            : "full_payment",
                    is_paid: this.form.collection_mode === "cash",
                    payment_method:
                        this.form.collection_mode === "transfer"
                            ? "sepay"
                            : "cash",
                    voucher_id: this.selectedVoucher?.id || null,
                    voucher_code: this.selectedVoucher?.code || null,
                });

                this.notice =
                    response.message ||
                    (this.counterDateCount > 1
                        ? `Đã tạo ${this.counterDateCount} booking tại quầy.`
                        : "Đã tạo booking tại quầy.");

                if (response.payment_qr) {
                    this.counterQr = response.payment_qr;
                    this.counterDrawerOpen = false;
                    this.qrModalOpen = true;
                    this.counterQrBookingId = response.data?.id || "";
                    this.startCounterQrPolling();
                }
                this.counterDrawerOpen = false;
                this.selectedSlotKeys = [];
                this.selectedGridCourtId = "";
                this.syncCounterRangeFields();
                this.clearVoucherSelection();
                await this.loadSchedule();
            } catch (error) {
                this.handleVoucherSubmitError(error);
                this.error = error.message || "Không thể tạo booking tại quầy.";
            } finally {
                this.submitting = false;
            }
        },
        async submitRecurring() {
            if (!this.canSubmitRecurring) return;
            this.normalizeRecurringTime();
            this.submitting = true;
            this.error = "";
            this.notice = "";

            try {
                await this.createRecurringWithPayload(this.recurringPayload());
            } catch (error) {
                if (error.status === 409 && error.data?.conflicts?.length) {
                    this.openRecurringConflict(error.data);
                } else {
                    this.handleVoucherSubmitError(error);
                    this.error = error.message || "Không thể tạo lịch cố định.";
                }
            } finally {
                this.submitting = false;
            }
        },
        recurringPayload(extra = {}) {
            const usesWeekdayRanges =
                this.activeTab === "recurring" &&
                this.form.recurrence_type === "weekly" &&
                this.hasRecurringSelection;
            const weekdayRangeGroups = usesWeekdayRanges
                ? this.recurringSelectedDays
                      .map((day) => ({
                          day,
                          ranges: this.recurringDayRanges[day] || [],
                      }))
                      .filter((item) => item.ranges.length)
                : [];
            const selectedRanges =
                usesWeekdayRanges
                    ? weekdayRangeGroups[0]?.ranges || []
                    : this.selectedSlotRanges;
            const timeRanges = selectedRanges.map((range) => ({
                venue_court_id: range.venue_court_id,
                start_time: this.withSeconds(this.formatTime(range.start_time)),
                end_time: this.withSeconds(this.formatTime(range.end_time)),
            }));
            const firstRange = [...timeRanges].sort(
                (a, b) =>
                    this.timeToMinutes(a.start_time) -
                    this.timeToMinutes(b.start_time),
            )[0];
            const lastRange = [...timeRanges].sort(
                (a, b) =>
                    this.timeToMinutes(b.end_time) -
                    this.timeToMinutes(a.end_time),
            )[0];

            const payload = {
                venue_court_id:
                    firstRange?.venue_court_id || this.form.venue_court_id,
                walk_in_name: this.form.walk_in_name,
                walk_in_phone: this.form.walk_in_phone,
                start_time:
                    firstRange?.start_time ||
                    this.withSeconds(this.form.start_time),
                end_time:
                    lastRange?.end_time || this.withSeconds(this.form.end_time),
                time_ranges: timeRanges,
                payment_option: this.form.payment_option,
                is_paid:
                    this.form.payment_option !== "no_prepay"
                        ? this.form.is_paid
                        : false,
                payment_method: this.form.payment_method,
                recurring_start_date: this.form.recurring_start_date,
                recurring_end_date: this.form.recurring_end_date,
                recurrence_type: this.form.recurrence_type,
                recurrence_interval: this.form.recurrence_interval,
                venue_cluster_id: this.selectedClusterId,
                ...extra,
            };

            if (this.form.recurrence_type === "weekly") {
                payload.recurrence_days_of_week =
                    this.form.recurrence_days_of_week;
                payload.weekday_time_ranges = weekdayRangeGroups.map(
                    ({ day, ranges }) => ({
                        day_of_week: day,
                        time_ranges: ranges.map(
                            (range) => ({
                                venue_court_id: range.venue_court_id,
                                start_time: this.withSeconds(
                                    this.formatTime(range.start_time),
                                ),
                                end_time: this.withSeconds(
                                    this.formatTime(range.end_time),
                                ),
                            }),
                        ),
                    }),
                );
            }

            if (this.form.recurrence_type === "monthly") {
                payload.recurrence_days_of_month = this.monthDaysInput
                    .split(",")
                    .map((item) => Number(item.trim()))
                    .filter(Boolean);
            }

            return payload;
        },
        async createRecurringWithPayload(payload) {
            const response = await ownerBookingService.createRecurring(payload);
            const skipped = Number(response.data?.skipped_count || 0);
            const switched = Number(response.data?.switched_count || 0);
            const extras = [
                skipped ? `bỏ ${skipped} buổi trùng` : "",
                switched ? `đổi sân ${switched} buổi` : "",
            ].filter(Boolean);

            this.notice = `Đã tạo ${response.data?.created_count || this.recurringPreview.length} buổi cố định${extras.length ? `, ${extras.join(", ")}` : ""}.`;
            this.recurringConflict = null;
            this.conflictSelections = {};
            this.clearVoucherSelection();
            await this.loadSchedule();
        },
        openRecurringConflict(data) {
            const selections = {};
            (data.conflicts || []).forEach((conflict) => {
                const alternatives = this.conflictAlternativeCourts(conflict);
                selections[conflict.key || conflict.date] =
                    alternatives?.[0]?.id || "skip";
            });
            this.recurringConflict = data;
            this.conflictSelections = selections;
        },
        conflictAlternativeCourts(conflict) {
            const currentCourtTypeId = conflict?.current_court?.court_type?.id;
            const alternatives = Array.isArray(conflict?.alternatives)
                ? conflict.alternatives
                : [];

            if (!currentCourtTypeId) {
                return alternatives;
            }

            return alternatives.filter(
                (court) =>
                    String(court?.court_type?.id) ===
                    String(currentCourtTypeId),
            );
        },
        closeRecurringConflict() {
            this.recurringConflict = null;
            this.conflictSelections = {};
        },
        async submitRecurringSkipConflicts() {
            if (!this.recurringConflict || this.submitting) return;
            this.submitting = true;
            this.error = "";
            this.notice = "";

            try {
                await this.createRecurringWithPayload(
                    this.recurringPayload({ conflict_resolution: "skip" }),
                );
            } catch (error) {
                this.handleVoucherSubmitError(error);
                this.error = error.message || "Không thể tạo lịch cố định.";
            } finally {
                this.submitting = false;
            }
        },
        async submitRecurringConflictChoices() {
            if (!this.recurringConflict || this.submitting) return;
            this.submitting = true;
            this.error = "";
            this.notice = "";

            const overrides = (this.recurringConflict.conflicts || []).map(
                (conflict) => {
                    const value =
                        this.conflictSelections[
                            conflict.key || conflict.date
                        ];
                    if (!value || value === "skip") {
                        return {
                            date: conflict.date,
                            key: conflict.key || null,
                            action: "skip",
                        };
                    }

                    return {
                        date: conflict.date,
                        key: conflict.key || null,
                        action: "switch",
                        venue_court_id: value,
                    };
                },
            );

            try {
                await this.createRecurringWithPayload(
                    this.recurringPayload({
                        conflict_resolution: "mixed",
                        conflict_overrides: overrides,
                    }),
                );
            } catch (error) {
                this.handleVoucherSubmitError(error);
                this.error = error.message || "Không thể tạo lịch cố định.";
            } finally {
                this.submitting = false;
            }
        },
        syncPaymentOption() {
            const availableOptions =
                this.activeTab === "recurring"
                    ? this.recurringPaymentOptions
                    : this.paymentOptions;

            if (
                !availableOptions.some(
                    (option) => option.value === this.form.payment_option,
                )
            ) {
                this.form.payment_option =
                    availableOptions[0]?.value || "no_prepay";
            }
            this.syncPaidState();
        },
        syncPaidState() {
            if (this.form.payment_option === "no_prepay") {
                this.form.is_paid = false;
                this.form.payment_method = "cash";
                if (this.activeTab === "counter")
                    this.form.collection_mode = "later";
            } else if (this.activeTab === "recurring") {
                this.form.is_paid = true;
                if (
                    !["cash", "bank_transfer"].includes(
                        this.form.payment_method,
                    )
                ) {
                    this.form.payment_method = "cash";
                }
            } else if (
                this.form.collection_mode === "later" &&
                this.form.is_paid
            ) {
                this.form.collection_mode = "cash";
            }
        },
        setRecurringPaid(isPaid) {
            this.form.is_paid = isPaid;
            if (!isPaid) {
                this.form.payment_method = "cash";
            }
        },
        setRecurringPaymentMethod(method) {
            this.form.payment_method = method;
            this.form.is_paid = true;
        },
        applyCounterCollectionMode() {
            if (this.form.collection_mode === "later") {
                this.form.payment_option = "no_prepay";
                this.form.payment_method = "cash";
                this.form.is_paid = false;
                return;
            }

            this.form.payment_option = "full_payment";
            this.form.payment_method =
                this.form.collection_mode === "transfer" ? "sepay" : "cash";
            this.form.is_paid = this.form.collection_mode === "cash";
        },
        normalizeRecurringTime() {
            let start = this.timeToMinutes(this.form.start_time);
            let end = this.timeToMinutes(this.form.end_time);
            const open = this.operatingStartMinutes;
            const close = this.operatingEndMinutes;

            if (start < open) start = open;
            if (start >= close)
                start = close - SLOT_STEP_MINUTES;
            if (end > close) end = close;
            if (end <= start) end = Math.min(start + 60, close);
            if (end <= start) {
                start = Math.max(open, end - SLOT_STEP_MINUTES);
            }

            this.form.start_time = this.minutesToTime(start);
            this.form.end_time = this.minutesToTime(end);
        },
        startCounterQrPolling() {
            this.clearCounterQrPolling();
            if (!this.counterQrBookingId) return;

            this.counterQrPollInterval = setInterval(() => {
                this.refreshCounterQrBooking();
            }, 5000);
        },
        async refreshCounterQrBooking() {
            if (!this.counterQrBookingId) return;

            try {
                const response = await ownerBookingService.show(
                    this.counterQrBookingId,
                );
                const booking = response.data || response;
                const paidAmount = this.paidAmount(booking);

                if (
                    paidAmount >= Number(booking.total_price || 0) ||
                    booking.status !== "pending_payment"
                ) {
                    this.counterQr = null;
                    this.qrModalOpen = false;
                    this.counterQrBookingId = "";
                    this.clearCounterQrPolling();
                    await this.loadSchedule();
                }
            } catch {
                this.clearCounterQrPolling();
            }
        },
        clearCounterQrPolling() {
            if (this.counterQrPollInterval) {
                clearInterval(this.counterQrPollInterval);
                this.counterQrPollInterval = null;
            }
        },
        closeQrModal() {
            this.qrModalOpen = false;
        },
        paidAmount(booking) {
            return (booking?.payments || [])
                .filter((payment) => payment.status === "paid")
                .reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
        },
        bookingOutstandingAmount(booking) {
            return Math.max(
                Number(booking?.total_price || 0) - this.paidAmount(booking),
                0,
            );
        },
        bookingCustomerName(booking) {
            return (
                booking?.customer?.full_name ||
                booking?.customer?.username ||
                booking?.walk_in_name ||
                "Khách vãng lai"
            );
        },
        bookingCustomerPhone(booking) {
            return booking?.customer?.phone || booking?.walk_in_phone || "-";
        },
        isBadgeValue(value) {
            return (
                value &&
                typeof value === "object" &&
                Object.prototype.hasOwnProperty.call(value, "text") &&
                Object.prototype.hasOwnProperty.call(value, "tone")
            );
        },
        bookingSourceLabel(source) {
            return (
                {
                    online: "Đặt online",
                    counter: "Tại quầy",
                }[source] ||
                source ||
                "-"
            );
        },
        intervalPaymentState(interval) {
            if (!interval) return "available";
            if (interval.source === "slot_lock") return "locked";
            if (interval.source !== "booking") return "locked";

            const outstanding = Number(interval.outstanding_amount || 0);
            if (interval.status === "completed" || outstanding <= 0) {
                return "paid";
            }

            const overdue = this.isBookingTimePast({
                booking_date:
                    this.activeTab === "recurring"
                        ? this.form.recurring_start_date
                        : this.form.booking_date,
                end_time: interval.end_time,
            });

            if (interval.payment_option === "no_prepay") {
                return overdue ? "pay_later_overdue" : "pay_later";
            }

            if (interval.booking_source === "online") {
                return overdue ? "online_overdue" : "online_pending";
            }

            return overdue
                ? "counter_transfer_overdue"
                : "counter_transfer_pending";
        },
        bookingPaymentState(booking) {
            if (!booking) return "unknown";
            const outstanding =
                this.selectedBusyBooking?.id === booking.id
                    ? this.selectedBookingOutstanding
                    : Math.max(
                          Number(booking.total_price || 0) -
                              this.paidAmount(booking),
                          0,
                      );

            if (booking.status === "completed" || outstanding <= 0) {
                return "paid";
            }

            const overdue = this.isBookingTimePast(booking);

            if (booking.payment_option === "no_prepay") {
                return overdue ? "pay_later_overdue" : "pay_later";
            }

            if (booking.source === "online") {
                return overdue ? "online_overdue" : "online_pending";
            }

            return overdue
                ? "counter_transfer_overdue"
                : "counter_transfer_pending";
        },
        paymentStateTone(state) {
            return (
                {
                    paid: "paid",
                    online_pending: "online",
                    counter_transfer_pending: "counter",
                    pay_later: "later",
                    online_overdue: "overdue",
                    counter_transfer_overdue: "overdue",
                    pay_later_overdue: "overdue",
                    locked: "locked",
                }[state] || "neutral"
            );
        },
        paymentStateLabel(state) {
            return (
                {
                    paid: "Đã thanh toán",
                    online_pending: "Chờ thanh toán online",
                    counter_transfer_pending: "Chờ chuyển khoản",
                    pay_later: "Thu sau",
                    online_overdue: "Online quá hạn",
                    counter_transfer_overdue: "Chuyển khoản quá hạn",
                    pay_later_overdue: "Quá hạn thu tiền",
                    locked: "Khóa sân",
                }[state] || "-"
            );
        },
        isBookingTimePast(booking) {
            const date = booking?.booking_date || this.form.booking_date;
            if (!date) return false;

            const rawDate = String(date).slice(0, 10);
            return rawDate < this.today;
        },
        bookingStatusTone(status) {
            return (
                {
                    pending_approval: "review",
                    pending_payment: "pending",
                    confirmed: "confirmed",
                    checked_in: "checked-in",
                    completed: "paid",
                    cancelled: "cancelled",
                    rejected: "rejected",
                    expired: "overdue",
                }[status] || "neutral"
            );
        },
        bookingCourtText(booking) {
            const courtNames = [
                ...new Set(
                    this.bookingTimeSegments(booking).map(
                        (segment) => segment.court_name,
                    ),
                ),
            ].filter(Boolean);

            if (!courtNames.length) {
                return this.courtNameById(booking?.venue_court_id);
            }

            return courtNames.join(", ");
        },
        bookingTimeText(booking) {
            const segments = this.bookingTimeSegments(booking);
            if (!segments.length) return "-";

            const grouped = new Map();
            segments.forEach((segment) => {
                const key =
                    segment.venue_court_id || segment.court_name || "court";
                if (!grouped.has(key)) {
                    grouped.set(key, {
                        court_name: segment.court_name,
                        ranges: [],
                    });
                }
                grouped.get(key).ranges.push({
                    start_time: segment.start_time,
                    end_time: segment.end_time,
                });
            });

            const courtGroups = [...grouped.values()].map((group) => {
                const ranges = this.mergeTimeRanges(group.ranges)
                    .map(
                        (range) =>
                            `${this.formatTime(range.start_time)} - ${this.formatTime(range.end_time)}`,
                    )
                    .join(", ");

                return grouped.size > 1
                    ? `${group.court_name || "Sân"}: ${ranges}`
                    : ranges;
            });

            return courtGroups.join(" · ");
        },
        bookingTimeSegments(booking) {
            const items = Array.isArray(booking?.items) ? booking.items : [];
            const segments = items.length
                ? items.map((item) => ({
                      venue_court_id: item.venue_court_id,
                      court_name:
                          item.venue_court?.name ||
                          this.courtNameById(item.venue_court_id),
                      start_time: item.start_time,
                      end_time: item.end_time,
                  }))
                : [
                      {
                          venue_court_id: booking?.venue_court_id,
                          court_name: this.courtNameById(
                              booking?.venue_court_id,
                          ),
                          start_time: booking?.start_time,
                          end_time: booking?.end_time,
                      },
                  ];

            return segments
                .filter((segment) => segment.start_time && segment.end_time)
                .sort((a, b) => {
                    const courtSort = String(a.court_name || "").localeCompare(
                        String(b.court_name || ""),
                    );
                    if (courtSort !== 0) return courtSort;

                    return (
                        this.timeToMinutes(a.start_time) -
                        this.timeToMinutes(b.start_time)
                    );
                });
        },
        mergeTimeRanges(ranges) {
            return [...ranges]
                .filter((range) => range.start_time && range.end_time)
                .sort(
                    (a, b) =>
                        this.timeToMinutes(a.start_time) -
                        this.timeToMinutes(b.start_time),
                )
                .reduce((merged, range) => {
                    const current = merged[merged.length - 1];
                    if (!current || current.end_time !== range.start_time) {
                        merged.push({ ...range });
                        return merged;
                    }

                    current.end_time = range.end_time;
                    return merged;
                }, []);
        },
        courtNameById(courtId) {
            return (
                this.scheduleCourts.find(
                    (court) => String(court.id) === String(courtId),
                )?.name ||
                this.courts.find(
                    (court) => String(court.id) === String(courtId),
                )?.name ||
                "-"
            );
        },
        bookingStatusLabel(status) {
            return (
                {
                    pending_approval: "Chờ duyệt",
                    pending_payment: "Chờ thanh toán",
                    confirmed: "Đã xác nhận",
                    checked_in: "Đã check-in",
                    completed: "Hoàn thành",
                    cancelled: "Đã hủy",
                    rejected: "Từ chối",
                    expired: "Hết hạn",
                }[status] ||
                status ||
                "-"
            );
        },
        openBookingActionConfirm(kind, payload = {}) {
            if (!this.selectedBusyBooking) return;

            const amount = this.formatCurrency(this.selectedBookingOutstanding);
            const configs = {
                status: {
                    confirm: {
                        title: "Xác nhận booking",
                        message:
                            "Booking sẽ được chuyển sang trạng thái đã xác nhận.",
                        confirmLabel: "Xác nhận",
                    },
                    cancel: {
                        title: "Hủy booking",
                        message:
                            "Booking sẽ bị hủy và khung giờ được trả lại lịch sân.",
                        confirmLabel: "Hủy booking",
                        variant: "danger",
                    },
                }[payload.action],
                collect: {
                    title: "Thu tiền mặt",
                    message: `Ghi nhận đã thu tiền mặt ${amount} cho booking này.`,
                    confirmLabel: "Xác nhận đã thu",
                },
                transfer: {
                    title: "Tạo chuyển khoản",
                    message: `Tạo hoặc mở lại QR chuyển khoản ${amount} cho booking này.`,
                    confirmLabel: "Mở QR chuyển khoản",
                },
            };

            const config = kind === "status" ? configs.status : configs[kind];
            if (!config) return;

            this.bookingActionConfirm = {
                kind,
                ...payload,
                reason: "",
                variant: "default",
                ...config,
            };
            this.counterDrawerOpen = false;
        },
        closeBookingActionConfirm() {
            if (this.bookingActionLoading) return;
            this.bookingActionConfirm = null;
        },
        async confirmBookingAction() {
            const action = this.bookingActionConfirm;
            if (!action || this.bookingActionLoading) return;

            if (action.kind === "status") {
                await this.updateSelectedBookingStatus(action.action);
            } else if (action.kind === "collect") {
                await this.collectSelectedBooking(action.method || "cash");
            } else if (action.kind === "transfer") {
                await this.openSelectedBookingPaymentQr();
            }
        },
        async updateSelectedBookingStatus(action) {
            if (!this.selectedBusyBooking?.id || this.bookingActionLoading) {
                return;
            }

            this.bookingActionLoading = true;
            this.error = "";
            this.notice = "";

            try {
                const payload = { action };
                if (action === "cancel") {
                    const reason = (
                        this.bookingActionConfirm?.reason || ""
                    ).trim();
                    if (!reason) {
                        this.error = "Vui lòng nhập lý do hủy booking.";
                        return;
                    }
                    payload.status_reason = reason;
                }
                const response = await ownerBookingService.updateStatus(
                    this.selectedBusyBooking.id,
                    payload,
                );
                this.selectedBusyBooking = response.data || response;
                this.notice = "Đã cập nhật trạng thái booking.";
                this.bookingActionConfirm = null;
                this.counterDrawerOpen = false;
                await this.loadSchedule();
            } catch (error) {
                this.error = error.message || "Không thể cập nhật booking.";
            } finally {
                this.bookingActionLoading = false;
            }
        },
        async collectSelectedBooking(method) {
            if (!this.selectedBusyBooking?.id || this.bookingActionLoading) {
                return;
            }

            this.bookingActionLoading = true;
            this.error = "";
            this.notice = "";

            try {
                const response = await ownerBookingService.collectPayment(
                    this.selectedBusyBooking.id,
                    { payment_method: method },
                );
                this.selectedBusyBooking = response.data || response;
                this.bookingActionConfirm = null;
                this.counterDrawerOpen = false;
                await this.loadSchedule();
            } catch (error) {
                this.error = error.message || "Không thể ghi nhận thu tiền.";
            } finally {
                this.bookingActionLoading = false;
            }
        },
        async openSelectedBookingPaymentQr() {
            if (!this.selectedBusyBooking?.id || this.bookingActionLoading) {
                return;
            }

            this.bookingActionLoading = true;
            this.error = "";
            this.notice = "";

            try {
                const response = await ownerBookingService.collectPayment(
                    this.selectedBusyBooking.id,
                    { payment_method: "sepay" },
                );
                this.counterQr = response.payment_qr || null;
                this.counterDrawerOpen = false;
                this.qrModalOpen = Boolean(this.counterQr);
                this.counterQrBookingId =
                    response.data?.id || this.selectedBusyBooking.id;
                this.selectedBusyBooking =
                    response.data || this.selectedBusyBooking;
                this.bookingActionConfirm = null;
                this.startCounterQrPolling();
            } catch (error) {
                this.error =
                    error.message || "Không thể mở thông tin thanh toán.";
            } finally {
                this.bookingActionLoading = false;
            }
        },
        recurringGroupCustomer(group) {
            return (
                group?.customer?.full_name ||
                group?.customer?.username ||
                group?.walk_in_name ||
                "Khách vãng lai"
            );
        },
        recurringGroupPhone(group) {
            return group?.customer?.phone || group?.walk_in_phone || "-";
        },
        paymentOptionLabel(value) {
            return (
                {
                    full_payment: "Thanh toán đủ",
                    deposit: "Đặt cọc",
                    no_prepay: "Thu sau",
                    mixed: "Nhiều hình thức",
                }[value] ||
                value ||
                "-"
            );
        },
        recurringGroupStatusSummary(group) {
            const labels = {
                upcoming: "sắp diễn ra",
                "in-progress": "đang diễn ra",
                ended: "đã kết thúc",
                partial: "hủy một phần",
                cancelled: "đã hủy",
            };
            const order = [
                "in-progress",
                "upcoming",
                "ended",
                "partial",
                "cancelled",
            ];
            const counts = (group?.occurrences || []).reduce(
                (result, occurrence) => {
                    const state = this.occurrenceOperationalState(occurrence);
                    result[state] = Number(result[state] || 0) + 1;
                    return result;
                },
                {},
            );

            return order
                .filter((status) => Number(counts[status] || 0) > 0)
                .map((status) => `${counts[status]} ${labels[status]}`)
                .join(" · ");
        },
        recurringGroupDateChips(group, limit = 6) {
            return (group?.occurrences || []).slice(0, limit);
        },
        recurringGroupHiddenDateCount(group, limit = 6) {
            return Math.max((group?.occurrences || []).length - limit, 0);
        },
        recurringGroupIssueCount(group) {
            return (group?.occurrences || []).filter(
                (occurrence) =>
                    occurrence.status === "cancelled" ||
                    Number(occurrence.cancelled_item_count || 0) > 0,
            ).length;
        },
        occurrenceChipClass(occurrence) {
            const isPartial =
                occurrence.status !== "cancelled" &&
                Number(occurrence.cancelled_item_count || 0) > 0 &&
                Number(occurrence.active_item_count || 0) > 0;

            return {
                issue:
                    !isPartial &&
                    (occurrence.status === "cancelled" ||
                        Number(occurrence.cancelled_item_count || 0) > 0),
                partial: isPartial,
            };
        },
        occurrenceChipTitle(occurrence) {
            return `${this.formatDate(occurrence.booking_date)} · ${this.occurrenceStatusLabel(occurrence)}`;
        },
        occurrenceCardClass(occurrence) {
            const state = this.occurrenceOperationalState(occurrence);

            return {
                cancelled: state === "cancelled",
                partial: state === "partial",
                upcoming: state === "upcoming",
                "in-progress": state === "in-progress",
                ended: state === "ended",
            };
        },
        occurrenceOperationalState(occurrence) {
            const bookingStatus = String(occurrence?.status || "");
            const cancelledItems = Number(
                occurrence?.cancelled_item_count || 0,
            );
            const activeItems = Number(occurrence?.active_item_count || 0);

            if (["cancelled", "rejected", "expired"].includes(bookingStatus)) {
                return "cancelled";
            }

            if (cancelledItems > 0 && activeItems > 0) {
                return "partial";
            }

            if (cancelledItems > 0) {
                return "cancelled";
            }

            const startsAt = this.occurrenceDateTime(occurrence, "start_time");
            const endsAt = this.occurrenceDateTime(occurrence, "end_time");
            const now = Date.now();

            if (!startsAt || !endsAt || now < startsAt.getTime()) {
                return "upcoming";
            }

            return now < endsAt.getTime() ? "in-progress" : "ended";
        },
        occurrenceDateTime(occurrence, field) {
            const date = this.parseDate(occurrence?.booking_date);
            const time = this.formatTime(occurrence?.[field]);
            if (!date || !/^\d{2}:\d{2}$/.test(time)) return null;

            const minutes = this.timeToMinutes(time);
            if (!Number.isFinite(minutes)) return null;

            date.setHours(0, minutes, 0, 0);
            return date;
        },
        occurrenceStatusLabel(occurrence) {
            const state = this.occurrenceOperationalState(occurrence);

            if (state === "cancelled") {
                if (occurrence.has_interrupted_by_emergency) {
                    return "Dừng do sự cố sân";
                }
                return occurrence.has_cancelled_by_maintenance
                    ? "Hủy do khóa sân"
                    : "Đã hủy";
            }

            if (state === "partial") {
                return "Hủy một phần";
            }

            const labels = {
                upcoming: "Sắp diễn ra",
                "in-progress": "Đang diễn ra",
                ended: "Đã kết thúc",
            };

            return labels[state] || "Sắp diễn ra";
        },
        occurrencePaymentState(occurrence) {
            const total = Math.max(Number(occurrence?.total_price || 0), 0);
            const paid = Math.max(Number(occurrence?.paid_amount || 0), 0);

            if (total <= 0) return "free";
            if (paid + 0.01 >= total) return "paid";
            if (paid > 0) return "partial";
            return "unpaid";
        },
        occurrencePaymentLabel(occurrence) {
            return (
                {
                    free: "Không cần thu",
                    paid: "Đã thu đủ",
                    partial: "Đã thu một phần",
                    unpaid: "Chưa thu",
                }[this.occurrencePaymentState(occurrence)] || "Chưa thu"
            );
        },
        occurrenceTimeText(occurrence) {
            const items = Array.isArray(occurrence?.items)
                ? occurrence.items
                : [];

            if (!items.length) {
                return `${this.formatTime(occurrence?.start_time)} - ${this.formatTime(occurrence?.end_time)}`;
            }

            return items
                .map((item) => {
                    const time = `${this.formatTime(item.start_time)} - ${this.formatTime(item.end_time)}`;
                    const itemStatus = String(item.status || "active");
                    const status =
                        itemStatus === "interrupted_by_emergency"
                            ? " · dừng do sự cố"
                            : itemStatus.startsWith("cancelled_")
                              ? " · hủy"
                              : "";

                    return `${item.court_name || "Sân"} ${time}${status}`;
                })
                .join(" · ");
        },
        recurringGroupTimeText(group) {
            const ranges = Array.isArray(group?.time_ranges)
                ? group.time_ranges
                : [];

            if (!ranges.length) {
                return `${this.formatTime(group?.start_time)} - ${this.formatTime(group?.end_time)}`;
            }

            const grouped = new Map();
            ranges.forEach((range) => {
                const key = range.venue_court_id || range.court_name || "court";
                if (!grouped.has(key)) {
                    grouped.set(key, {
                        court_name: range.court_name,
                        ranges: [],
                    });
                }
                grouped.get(key).ranges.push({
                    start_time: range.start_time,
                    end_time: range.end_time,
                });
            });

            return [...grouped.values()]
                .map((item) => {
                    const timeText = this.mergeTimeRanges(item.ranges)
                        .map(
                            (range) =>
                                `${this.formatTime(range.start_time)} - ${this.formatTime(range.end_time)}`,
                        )
                        .join(", ");

                    return grouped.size > 1
                        ? `${item.court_name || "Sân"}: ${timeText}`
                        : timeText;
                })
                .join(" · ");
        },
        recurringBookingPatternText(booking) {
            const interval = Number(booking?.recurrence_interval || 1);
            const every = interval > 1 ? `${interval} ` : "";

            if (booking?.recurrence_type === "daily") {
                return `Lặp mỗi ${every}ngày`;
            }

            if (booking?.recurrence_type === "weekly") {
                const days = (booking.recurrence_days_of_week || [])
                    .map(
                        (value) =>
                            this.weekDays.find(
                                (day) => Number(day.value) === Number(value),
                            )?.label,
                    )
                    .filter(Boolean)
                    .join(", ");

                return `Lặp mỗi ${every}tuần${days ? ` · vào ${days}` : ""}`;
            }

            if (booking?.recurrence_type === "monthly") {
                return `Lặp mỗi ${every}tháng · ngày ${(booking.recurrence_days_of_month || []).join(", ") || "-"}`;
            }

            return "-";
        },
        recurringGroupPatternText(group) {
            const interval = Number(group?.recurrence_interval || 1);
            const every = interval > 1 ? `${interval} ` : "";

            if (group?.recurrence_type === "daily") {
                return `Lặp mỗi ${every}ngày`;
            }

            if (group?.recurrence_type === "weekly") {
                const days = (group.recurrence_days_of_week || [])
                    .map(
                        (value) =>
                            this.weekDays.find(
                                (day) => Number(day.value) === Number(value),
                            )?.label,
                    )
                    .filter(Boolean)
                    .join(", ");

                return `Lặp mỗi ${every}tuần${days ? ` · vào ${days}` : ""}`;
            }

            if (group?.recurrence_type === "monthly") {
                return `Lặp mỗi ${every}tháng · ngày ${(group.recurrence_days_of_month || []).join(", ") || "-"}`;
            }

            return "-";
        },
        openRecurringGroupDetail(group) {
            if (!group?.recurring_group_code) return;
            this.recurringGroupDetail = group;
        },
        closeRecurringGroupDetail() {
            this.recurringGroupDetail = null;
        },
        buildRecurringGroupRows(group) {
            return [
                ["Mã nhóm", group?.recurring_group_code || "-"],
                [
                    "Khách",
                    `${this.recurringGroupCustomer(group)} · ${this.recurringGroupPhone(group)}`,
                ],
                ["Cụm sân", group?.venue_cluster_name || "-"],
                ["Lịch lặp", this.recurringGroupPatternText(group)],
                [
                    "Ngày",
                    `${this.formatDate(group?.start_date)} - ${this.formatDate(group?.end_date)}`,
                ],
                [
                    "Sân",
                    Array.isArray(group?.court_names) &&
                    group.court_names.length
                        ? group.court_names.join(", ")
                        : "-",
                ],
                ["Khung giờ", this.recurringGroupTimeText(group)],
                ["Số buổi", `${group?.booking_count || 0} buổi`],
                ["Hình thức", this.paymentOptionLabel(group?.payment_option)],
                [
                    "Trạng thái",
                    this.recurringGroupStatusSummary(group) ||
                        "Chưa có trạng thái",
                ],
                ["Tổng bill", this.formatCurrency(group?.total_price)],
                ["Đã thu", this.formatCurrency(group?.paid_amount)],
                ["Còn thu", this.formatCurrency(group?.outstanding_amount)],
            ];
        },
        openRecurringGroupCollectConfirm(group, method) {
            if (!group?.recurring_group_code) return;

            this.recurringGroupConfirm = {
                group,
                method,
                title:
                    method === "cash"
                        ? "Xác nhận thu tiền mặt"
                        : "Xác nhận chuyển khoản",
                message: `Ghi nhận ${this.formatCurrency(group.outstanding_amount)} cho nhóm lịch cố định này.`,
                confirmLabel:
                    method === "cash"
                        ? "Xác nhận đã thu"
                        : "Xác nhận chuyển khoản",
            };
        },
        closeRecurringGroupConfirm() {
            if (this.recurringGroupCollecting) return;
            this.recurringGroupConfirm = null;
        },
        async confirmRecurringGroupCollect() {
            const confirm = this.recurringGroupConfirm;
            if (!confirm || this.recurringGroupCollecting) return;

            const ok = await this.collectRecurringGroup(
                confirm.group,
                confirm.method,
            );
            if (ok) {
                this.recurringGroupConfirm = null;
                this.recurringGroupDetail = null;
            }
        },
        async collectRecurringGroup(group, method) {
            if (!group?.recurring_group_code || this.recurringGroupCollecting) {
                return false;
            }

            this.recurringGroupCollecting = group.recurring_group_code;
            this.error = "";
            this.notice = "";

            try {
                await ownerBookingService.collectRecurringGroupPayment(
                    group.recurring_group_code,
                    { payment_method: method },
                );
                this.notice = "Đã ghi nhận thu tiền cho nhóm lịch cố định.";
                await this.loadRecurringGroups();
                return true;
            } catch (error) {
                this.error =
                    error.message || "Không thể thu tiền nhóm lịch cố định.";
                return false;
            } finally {
                this.recurringGroupCollecting = "";
            }
        },
        async copyText(text) {
            if (!text) return;

            try {
                await navigator.clipboard.writeText(text);
                this.notice = "Đã sao chép nội dung chuyển khoản.";
            } catch {
                this.error = "Không thể sao chép nội dung chuyển khoản.";
            }
        },
        withSeconds(time) {
            return time.length === 5 ? `${time}:00` : time;
        },
        formatTime(time) {
            return (time || "").slice(0, 5);
        },
        timeToMinutes(time) {
            const [hour, minute] = this.formatTime(time).split(":").map(Number);
            return (hour || 0) * 60 + (minute || 0);
        },
        minutesToTime(minutes) {
            if (minutes >= 1440) return "24:00";
            return `${String(Math.floor(minutes / 60)).padStart(2, "0")}:${String(minutes % 60).padStart(2, "0")}`;
        },
        dayIndex(value) {
            const date = value instanceof Date ? value : new Date(value);
            return toWeekDayIndex(date);
        },
        parseDate(value) {
            if (!value) return null;
            const raw = String(value);
            const date = raw.includes("T")
                ? new Date(raw)
                : new Date(`${raw}T00:00:00`);
            return Number.isNaN(date.getTime()) ? null : date;
        },
        formatIsoDate(value) {
            const date = value instanceof Date ? value : new Date(value);
            return toIsoDate(date);
        },
        formatDate(value) {
            const date = this.parseDate(value);
            if (!date) return "-";
            return new Intl.DateTimeFormat("vi-VN").format(date);
        },
        formatShortDate(value) {
            const date = this.parseDate(value);
            if (!date) return "-";
            return new Intl.DateTimeFormat("vi-VN", {
                day: "2-digit",
                month: "2-digit",
            }).format(date);
        },
        formatCurrency(amount) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
                maximumFractionDigits: 0,
            }).format(Number(amount || 0));
        },
    },
};
</script>

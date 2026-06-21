<template>
    <div class="owner-counter-page">
        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="notice" class="alert success">{{ notice }}</div>

        <div class="tabs-and-actions">
            <div class="tabs">
                <button
                    type="button"
                    :class="{ active: activeTab === 'counter' }"
                    @click="setActiveTab('counter')"
                >
                    <AppIcon name="plus" size="16" />
                    <span>Booking tại quầy</span>
                </button>
                <button
                    type="button"
                    :class="{ active: activeTab === 'recurring' }"
                    @click="setActiveTab('recurring')"
                >
                    <AppIcon name="calendar" size="16" />
                    <span>Đặt lịch cố định</span>
                </button>
                <button
                    type="button"
                    :class="{ active: activeTab === 'recurringList' }"
                    @click="setActiveTab('recurringList')"
                >
                    <AppIcon name="fileText" size="16" />
                    <span>Danh sách cố định</span>
                </button>
            </div>
            <button class="secondary-btn" type="button" @click="loadSchedule">
                <AppIcon name="refresh" size="16" />
                <span>Tải lại lịch</span>
            </button>
        </div>

        <section v-if="activeTab === 'counter'" class="counter-board">
            <div class="schedule-panel">
                <div class="panel-head compact">
                    <div>
                        <h2>Lịch sân trong ngày</h2>
                        <p>{{ currentScheduleLabel }}</p>
                    </div>
                    <button
                        class="icon-btn"
                        type="button"
                        title="Tải lại lịch"
                        @click="loadSchedule"
                    >
                        <AppIcon name="refresh" size="17" />
                    </button>
                </div>

                <div class="filters schedule-filters">
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
                        <span>Ngày chơi</span>
                        <input
                            v-model="form.booking_date"
                            type="date"
                            @change="handleScheduleDateChange"
                        />
                    </label>
                    <label>
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

                <div v-if="scheduleLoading" class="state-card compact-state">
                    Đang tải lịch sân...
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
                            <strong>{{ formatCurrency(selectedTotal) }}</strong>
                        </div>
                    </div>

                    <div class="period-row">
                        <div class="period-tabs">
                            <button
                                v-for="period in timePeriods"
                                :key="period.key"
                                type="button"
                                :class="{
                                    active: activeTimePeriod === period.key,
                                }"
                                @click="activeTimePeriod = period.key"
                            >
                                <strong>{{ period.label }}</strong>
                                <span>{{ period.range }}</span>
                            </button>
                        </div>

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

            <aside class="booking-side">
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
                                placeholder="Nguyễn Văn A"
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
                                placeholder="0901234567"
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
                <div class="panel-head compact">
                    <div>
                        <h2>Lịch cố định</h2>
                        <p>Nhóm lịch sẽ dùng cùng mã cố định để dễ theo dõi.</p>
                    </div>
                </div>

                <div class="form-grid">
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
                            placeholder="Nguyễn Văn A"
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
                            placeholder="0901234567"
                            @blur="validateContactField('phone')"
                        />
                        <small
                            v-if="contactTouched.phone && walkInPhoneError"
                            class="field-error"
                        >
                            {{ walkInPhoneError }}
                        </small>
                    </label>
                    <label>
                        <span>Từ ngày</span>
                        <input
                            v-model="form.recurring_start_date"
                            type="date"
                            :min="today"
                        />
                    </label>
                    <label>
                        <span>Đến ngày</span>
                        <input
                            v-model="form.recurring_end_date"
                            type="date"
                            :min="form.recurring_start_date || today"
                        />
                    </label>
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

                    <div class="period-row">
                        <div class="period-tabs">
                            <button
                                v-for="period in timePeriods"
                                :key="period.key"
                                type="button"
                                :class="{
                                    active: activeTimePeriod === period.key,
                                }"
                                @click="activeTimePeriod = period.key"
                            >
                                <strong>{{ period.label }}</strong>
                                <span>{{ period.range }}</span>
                            </button>
                        </div>

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

                <div v-if="form.recurrence_type === 'weekly'" class="day-grid">
                    <label
                        v-for="day in weekDays"
                        :key="day.value"
                        :class="{
                            selected: form.recurrence_days_of_week.includes(
                                day.value,
                            ),
                        }"
                    >
                        <input
                            v-model="form.recurrence_days_of_week"
                            type="checkbox"
                            :value="day.value"
                        />
                        <span>{{ day.label }}</span>
                    </label>
                </div>

                <label
                    v-if="form.recurrence_type === 'monthly'"
                    class="month-days"
                >
                    <span>Ngày trong tháng</span>
                    <input
                        v-model="monthDaysInput"
                        type="text"
                        placeholder="1, 15, 30"
                    />
                </label>

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

        <section v-else class="recurring-list-panel">
            <div class="list-toolbar">
                <div>
                    <h2>Danh sách booking cố định</h2>
                    <p>
                        Theo dõi theo nhóm lịch, khách đặt, sân sử dụng và số
                        tiền còn phải thu.
                    </p>
                </div>
                <button
                    class="icon-btn"
                    type="button"
                    title="Tải lại"
                    @click="loadRecurringGroups"
                >
                    <AppIcon name="refresh" size="17" />
                </button>
            </div>

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
                                    <span>{{
                                        occurrenceStatusLabel(occurrence)
                                    }}</span>
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

            <div v-if="bookingActionConfirm" class="modal-backdrop">
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

            <div v-if="counterQr && qrModalOpen" class="modal-backdrop">
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
                            <select v-model="conflictSelections[conflict.date]">
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
import { ownerBookingService } from "../../services/ownerBookings.js";
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

const BOOKING_DAY_START = 6 * 60;
const BOOKING_DAY_END = 22 * 60;
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
    components: { AppIcon },
    data() {
        const now = new Date();
        const today = toIsoDate(now);

        return {
            today,
            activeTab: "counter",
            clusters: [],
            courts: [],
            selectedClusterId: "",
            selectedClusterDetail: null,
            selectedCourtTypeId: "",
            scheduleSlots: [],
            scheduleCourts: [],
            scheduleSlotStatuses: [],
            scheduleBusyIntervals: [],
            selectedGridCourtId: "",
            selectedSlotKeys: [],
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
            selectedOccupiedInterval: null,
            selectedBusyBooking: null,
            selectedBusyBookingLoading: false,
            bookingActionLoading: false,
            bookingActionConfirm: null,
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
            return Boolean(this.selectedSlotKeys.length);
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
            return this.scheduleSlots.filter((slot) => {
                const start = this.timeToMinutes(slot.start_time);
                const end = this.timeToMinutes(slot.end_time);
                return start >= BOOKING_DAY_START && end <= BOOKING_DAY_END;
            });
        },
        slotPeriods() {
            return this.timePeriods.map((period) => ({
                ...period,
                slots: this.bookableScheduleSlots.filter((slot) => {
                    const start = this.timeToMinutes(slot.start_time);
                    return start >= period.start && start < period.end;
                }),
            }));
        },
        activePeriod() {
            return (
                this.timePeriods.find(
                    (period) => period.key === this.activeTimePeriod,
                ) || this.timePeriods[0]
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
        selectedSlotEntries() {
            return this.selectedSlotKeys
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
            const ranges = [];

            this.selectedSlotEntries.forEach(({ courtId, court, slot }) => {
                const current = ranges[ranges.length - 1];
                if (
                    !current ||
                    current.venue_court_id !== courtId ||
                    current.end_time !== slot.start_time
                ) {
                    ranges.push({
                        venue_court_id: courtId,
                        court,
                        start_time: slot.start_time,
                        end_time: slot.end_time,
                    });
                    return;
                }

                current.end_time = slot.end_time;
            });

            return ranges;
        },
        selectedCourtText() {
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
            return this.selectedSlotRanges
                .map(
                    (range) =>
                        `${range.court?.name || "Sân"}: ${this.formatTime(range.start_time)} - ${this.formatTime(range.end_time)}`,
                )
                .join(", ");
        },
        recurringTimeText() {
            return this.hasCounterSelection
                ? this.selectedTimeText
                : "Chưa chọn";
        },
        selectedTotal() {
            return this.selectedSlotEntries.reduce((total, entry) => {
                const status = this.slotStatus(entry.courtId, entry.slot);
                return total + Number(status?.price || 0);
            }, 0);
        },
        recurringUnitTotal() {
            return this.activeTab === "recurring" ? this.selectedTotal : 0;
        },
        recurringTotalAmount() {
            return this.recurringUnitTotal * this.recurringPreview.length;
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
        counterPayableTotal() {
            return Math.max(this.selectedTotal - this.voucherDiscountAmount, 0);
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
            return [
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
                ["Ngày", this.formatDate(this.form.booking_date)],
                ["Giờ", this.selectedTimeText],
                ["Thời lượng", this.selectedDurationText],
                ["Tổng tiền", this.formatCurrency(this.selectedTotal)],
            ];

            if (this.selectedVoucher) {
                rows.push([
                    `Voucher ${this.selectedVoucher.code}`,
                    `-${this.formatCurrency(this.voucherDiscountAmount)}`,
                ]);
                rows.push([
                    "Cần thu",
                    this.formatCurrency(this.counterPayableTotal),
                ]);
            }

            return rows;
        },
        currentScheduleLabel() {
            return `${this.selectedCluster?.name || "Cụm sân"} · ${this.formatDate(this.form.booking_date)}`;
        },
        selectedBookingOutstanding() {
            if (!this.selectedBusyBooking) return 0;
            const total = Number(this.selectedBusyBooking.total_price || 0);
            return Math.max(
                total - this.paidAmount(this.selectedBusyBooking),
                0,
            );
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
        canSubmitRecurring() {
            const start = this.timeToMinutes(this.form.start_time);
            const end = this.timeToMinutes(this.form.end_time);

            return (
                this.hasCounterSelection &&
                !this.walkInNameError &&
                !this.walkInPhoneError &&
                this.form.payment_option &&
                start >= BOOKING_DAY_START &&
                end <= BOOKING_DAY_END &&
                end > start &&
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
        activeTab() {
            this.queueRecurringPreview();
        },
    },
    async created() {
        await this.loadOwnerData();
    },
    beforeUnmount() {
        this.clearCounterQrPolling();
        clearTimeout(this.recurringPreviewTimer);
    },
    methods: {
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
        async handleScheduleDateChange() {
            await this.loadSchedule();
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

            try {
                const response = await venueClusterService.getClusters();
                this.clusters = response.data || [];
                this.selectedClusterId =
                    this.$route.query.venue_cluster_id ||
                    localStorage.getItem("selected_cluster") ||
                    this.clusters[0]?.id ||
                    "";
                this.applyRouteBookingFilters();
                await this.handleClusterChange();
            } catch (error) {
                this.error = error.message || "Không thể tải dữ liệu cụm sân.";
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
            localStorage.setItem("selected_cluster", this.selectedClusterId);

            await Promise.all([this.loadClusterDetail(), this.loadCourts()]);
            this.syncPaymentOption();
            if (this.activeTab === "recurringList") {
                this.recurringGroupFilters.venue_court_id = "";
                await this.loadRecurringGroups();
            } else {
                await this.loadSchedule();
            }
        },
        async loadClusterDetail() {
            try {
                const response = await venueClusterService.getClusterDetails(
                    this.selectedClusterId,
                );
                this.selectedClusterDetail = response.data || null;
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
            const period = this.timePeriods.find((item) => {
                const [start, end] = item.range.split(" - ");
                return (
                    minutes >= this.timeToMinutes(start) &&
                    minutes < this.timeToMinutes(end)
                );
            });

            return period?.key || this.activeTimePeriod;
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
                this.voucherBaseAmount <= 0
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
                            : 1,
                    voucher_code: code || "",
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
            const status = this.slotStatus(courtId, slot);
            return !status || !status.is_available;
        },
        slotKey(courtId, slot) {
            return `${courtId}|${slot?.start_time || ""}`;
        },
        isSlotDisabled(courtId, slot) {
            if (!courtId || !slot) return true;

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
            return this.selectedSlotKeys.includes(this.slotKey(courtId, slot));
        },
        slotButtonClass(courtId, slot) {
            const selected = this.isSlotSelected(courtId, slot);
            const busy = this.isSlotBusy(courtId, slot);
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
                busy,
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
            if (!status || !status.is_available) return "Đã đặt";

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

                return `${courtName} · ${start} - ${end} đã đặt`;
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
            this.selectedSlotKeys = this.selectedSlotKeys.includes(key)
                ? this.selectedSlotKeys.filter((item) => item !== key)
                : [...this.selectedSlotKeys, key];
            this.syncCounterRangeFields();
            this.loadEligibleVouchers();
        },
        selectRecurringSlot(court, slot) {
            if (!court?.id || !slot) return;

            const clickedKey = this.slotKey(court.id, slot);
            this.selectionError = "";
            this.selectedSlotKeys = this.selectedSlotKeys.includes(clickedKey)
                ? this.selectedSlotKeys.filter((item) => item !== clickedKey)
                : [...this.selectedSlotKeys, clickedKey];
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
                return;
            }

            this.form.walk_in_phone = this.normalizedWalkInPhone;
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

                if (response.payment_qr) {
                    this.counterQr = response.payment_qr;
                    this.qrModalOpen = true;
                    this.counterQrBookingId = response.data?.id || "";
                    this.startCounterQrPolling();
                }
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
            const timeRanges = this.selectedSlotRanges.map((range) => ({
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
                ...extra,
            };

            if (this.form.recurrence_type === "weekly") {
                payload.recurrence_days_of_week =
                    this.form.recurrence_days_of_week;
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
                selections[conflict.date] = alternatives?.[0]?.id || "skip";
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
                    const value = this.conflictSelections[conflict.date];
                    if (!value || value === "skip") {
                        return { date: conflict.date, action: "skip" };
                    }

                    return {
                        date: conflict.date,
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

            if (start < BOOKING_DAY_START) start = BOOKING_DAY_START;
            if (start >= BOOKING_DAY_END)
                start = BOOKING_DAY_END - SLOT_STEP_MINUTES;
            if (end > BOOKING_DAY_END) end = BOOKING_DAY_END;
            if (end <= start) end = Math.min(start + 60, BOOKING_DAY_END);
            if (end <= start) {
                start = Math.max(BOOKING_DAY_START, end - SLOT_STEP_MINUTES);
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
                    pending_approval: "pending",
                    pending_payment: "pending",
                    confirmed: "confirmed",
                    checked_in: "confirmed",
                    completed: "paid",
                    cancelled: "cancelled",
                    rejected: "cancelled",
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
                pending_approval: "chờ duyệt",
                pending_payment: "chờ thanh toán",
                confirmed: "xác nhận",
                checked_in: "check-in",
                completed: "hoàn thành",
                cancelled: "đã hủy",
                rejected: "từ chối",
                expired: "hết hạn",
            };

            return Object.entries(group?.status_counts || {})
                .filter(([, count]) => Number(count) > 0)
                .map(
                    ([status, count]) => `${count} ${labels[status] || status}`,
                )
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
            return {
                cancelled:
                    occurrence.status === "cancelled" ||
                    Number(occurrence.active_item_count || 0) === 0,
                partial:
                    Number(occurrence.cancelled_item_count || 0) > 0 &&
                    Number(occurrence.active_item_count || 0) > 0,
            };
        },
        occurrenceStatusLabel(occurrence) {
            if (occurrence.status === "cancelled") {
                return occurrence.has_cancelled_by_maintenance
                    ? "Hủy do khóa sân"
                    : "Đã hủy";
            }

            if (
                Number(occurrence.cancelled_item_count || 0) > 0 &&
                Number(occurrence.active_item_count || 0) > 0
            ) {
                return "Hủy một phần";
            }

            if (Number(occurrence.cancelled_item_count || 0) > 0) {
                return occurrence.has_cancelled_by_maintenance
                    ? "Hủy do khóa sân"
                    : "Đã hủy";
            }

            const labels = {
                pending_payment: "Chờ thanh toán",
                confirmed: "Đã xác nhận",
                checked_in: "Đã check-in",
                completed: "Hoàn thành",
                pending_approval: "Chờ duyệt",
            };

            return labels[occurrence.status] || "Hoạt động";
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
                    const status = String(item.status || "active").startsWith(
                        "cancelled_",
                    )
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

<style scoped>
.owner-counter-page {
    display: grid;
    gap: 18px;
}

.counter-board {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 16px;
    align-items: start;
}

.recurring-panel {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 16px;
    align-items: start;
}

.recurring-list-panel {
    display: grid;
    gap: 14px;
    padding: 18px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.list-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.list-toolbar h2 {
    margin: 0;
    color: #16231a;
    font-size: 18px;
    font-weight: 850;
}

.list-toolbar p {
    margin: 4px 0 0;
    color: #607267;
    font-size: 13px;
}

.recurring-list-filters {
    display: grid;
    grid-template-columns:
        minmax(180px, 1fr) minmax(150px, 0.8fr) minmax(150px, 0.8fr)
        minmax(220px, 1.2fr) auto;
    gap: 10px;
    align-items: end;
}

.recurring-group-list {
    display: grid;
    gap: 10px;
}

.recurring-table-card {
    overflow: auto;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.recurring-table-card table {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
}

.recurring-table-card th,
.recurring-table-card td {
    padding: 12px 14px;
    border-bottom: 1px solid #e4eee4;
    text-align: left;
    vertical-align: top;
}

.recurring-table-card th {
    background: #f2f7ef;
    color: #526458;
    font-size: 11px;
    font-weight: 850;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.recurring-table-card tbody tr:last-child td {
    border-bottom: 0;
}

.recurring-table-card td {
    color: #263a2d;
    font-size: 13px;
}

.recurring-table-card td > strong,
.recurring-table-card td > small {
    display: block;
}

.recurring-table-card td > strong {
    margin-top: 5px;
    color: #203428;
    font-size: 13px;
    font-weight: 850;
    line-height: 1.35;
}

.recurring-table-card td > small {
    margin-top: 4px;
    color: #607267;
    font-size: 12px;
    line-height: 1.35;
}

.fixed-date-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 8px;
}

.fixed-date-chips span,
.fixed-date-chips em {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 22px;
    border-radius: 999px;
    padding: 3px 7px;
    background: #ecfdf5;
    color: #15803d;
    font-size: 11px;
    font-style: normal;
    font-weight: 900;
    line-height: 1;
}

.fixed-date-chips span.issue {
    background: #fee2e2;
    color: #b91c1c;
}

.fixed-date-chips span.partial {
    background: #fef3c7;
    color: #b45309;
}

.fixed-date-chips em {
    background: #eef2f7;
    color: #475569;
}

.recurring-occurrence-panel {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
    gap: 10px;
    min-height: 0;
    padding: 12px;
    border: 1px solid #d9e8d9;
    border-radius: 9px;
    background: #fbfefc;
}

.occurrence-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.occurrence-head strong {
    color: #16231a;
    font-size: 13px;
    font-weight: 900;
}

.occurrence-head span {
    color: #607267;
    font-size: 12px;
    font-weight: 750;
}

.occurrence-list {
    display: grid;
    align-content: start;
    gap: 7px;
    min-height: 0;
    overflow-y: auto;
    padding-right: 4px;
}

.occurrence-list article {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
}

.occurrence-list article.cancelled {
    border-color: #fecaca;
    background: #fff7f7;
}

.occurrence-list article.partial {
    border-color: #fde68a;
    background: #fffbeb;
}

.occurrence-list article > div {
    display: grid;
    gap: 3px;
    min-width: 0;
}

.occurrence-list strong {
    color: #1f3326;
    font-size: 13px;
    font-weight: 900;
}

.occurrence-list small {
    color: #607267;
    font-size: 12px;
    line-height: 1.35;
}

.occurrence-list article > span {
    flex: 0 0 auto;
    border-radius: 999px;
    padding: 5px 9px;
    background: #dcfce7;
    color: #15803d;
    font-size: 11px;
    font-weight: 900;
}

.occurrence-list article.cancelled > span {
    background: #fee2e2;
    color: #b91c1c;
}

.occurrence-list article.partial > span {
    background: #fef3c7;
    color: #b45309;
}

.recurring-table-card strong.paid {
    color: #0f7a31;
}

.money-col {
    text-align: right !important;
}

.action-col {
    width: 120px;
    text-align: right !important;
    white-space: nowrap;
}

.recurring-group-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px 18px;
    align-items: start;
    padding: 14px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fbfdfb;
}

.group-card-head {
    grid-column: 1 / -1;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e4eee4;
}

.recurring-group-card > div,
.group-main {
    display: grid;
    gap: 4px;
    min-width: 0;
}

.group-info-grid {
    display: grid !important;
    grid-template-columns: minmax(180px, 0.8fr) minmax(240px, 1.25fr) minmax(
            150px,
            0.7fr
        );
    gap: 12px;
}

.group-money-grid {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(95px, 1fr));
    gap: 8px;
    min-width: 330px;
}

.group-money-grid > div {
    padding: 9px 10px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.recurring-group-card span {
    color: #607267;
    font-size: 12px;
    font-weight: 750;
}

.recurring-group-card strong {
    overflow-wrap: anywhere;
    color: #16231a;
    font-size: 14px;
    font-weight: 850;
}

.recurring-group-card strong.paid {
    color: #0f7a31;
}

.recurring-group-card small {
    color: #607267;
    font-size: 12px;
    line-height: 1.35;
}

.group-code {
    width: fit-content;
    padding: 3px 8px;
    border-radius: 999px;
    background: #e8f7ec;
    color: #0f7a31 !important;
    font-weight: 850 !important;
}

.group-actions {
    grid-column: 2;
    display: flex !important;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.secondary-btn.compact {
    min-height: 34px;
    padding: 8px 10px;
    font-size: 12px;
}

.recurring-detail-modal {
    width: min(1100px, calc(100vw - 32px));
    max-height: min(780px, calc(100vh - 40px));
    grid-template-rows: auto minmax(0, 1fr) auto;
    overflow: hidden;
}

.recurring-detail-body {
    display: grid;
    grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.25fr);
    gap: 14px;
    min-height: 0;
}

.recurring-detail-main {
    display: grid;
    align-content: start;
    gap: 14px;
    min-height: 0;
    overflow-y: auto;
    padding-right: 2px;
}

.detail-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    min-width: 0;
    padding: 12px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #f7fbf5;
}

.recurring-detail-modal .confirm-summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    align-content: start;
    max-height: none;
    overflow: visible;
}

.recurring-detail-modal .confirm-summary div {
    display: grid;
    gap: 4px;
    min-width: 0;
    padding: 10px;
    border: 1px solid #e1ece1;
    border-radius: 8px;
    background: #fff;
}

.recurring-detail-modal .confirm-summary dt,
.recurring-detail-modal .confirm-summary dd {
    text-align: left;
}

.recurring-detail-modal .confirm-summary dd {
    overflow-wrap: anywhere;
    word-break: break-word;
    white-space: normal;
}

.recurring-detail-modal .modal-actions {
    margin-top: 0;
    padding-top: 12px;
    border-top: 1px solid #e4eee4;
}

@media (max-width: 860px) {
    .recurring-detail-modal {
        width: min(720px, calc(100vw - 24px));
        overflow: auto;
    }

    .recurring-detail-body {
        grid-template-columns: 1fr;
        overflow-y: auto;
    }

    .recurring-detail-main,
    .occurrence-list {
        overflow: visible;
    }

    .recurring-occurrence-panel {
        min-height: 280px;
    }
}

@media (max-width: 640px) {
    .recurring-detail-modal .confirm-summary {
        grid-template-columns: 1fr;
    }
}

.detail-hero div {
    display: grid;
    gap: 3px;
}

.detail-hero span:not(.status-badge) {
    color: #607267;
    font-size: 12px;
    font-weight: 800;
}

.detail-hero strong {
    color: #16231a;
    font-size: 15px;
    font-weight: 850;
}

.detail-hero small {
    color: #607267;
    font-size: 12px;
}

.schedule-panel,
.booking-side,
.form-card,
.preview-box,
.alert {
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.schedule-panel,
.booking-side,
.form-card,
.preview-box {
    padding: 18px;
}

.form-card {
    display: grid;
    gap: 16px;
}

.recurring-schedule-board {
    display: grid;
    gap: 12px;
    padding: 14px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fbfdfb;
}

.schedule-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.schedule-summary > div {
    display: grid;
    gap: 4px;
    min-height: 58px;
    padding: 10px 12px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.schedule-summary span {
    color: #607267;
    font-size: 12px;
    font-weight: 750;
}

.schedule-summary strong {
    overflow-wrap: anywhere;
    color: #16231a;
    font-size: 14px;
    font-weight: 850;
}

.recurring-slot-matrix {
    background: #fff;
}

.panel-head.compact {
    margin-bottom: 14px;
}

.panel-head h2,
.section-title h2 {
    margin: 0;
    color: #16231a;
    font-size: 17px;
    font-weight: 800;
}

.panel-head p {
    margin: 4px 0 0;
    color: #607267;
    font-size: 13px;
}

.section-title p {
    margin: 4px 0 0;
    color: #607267;
    font-size: 13px;
    line-height: 1.45;
}

.schedule-filters,
.form-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.booking-picker {
    display: grid;
    grid-template-columns: minmax(240px, 1.3fr) minmax(220px, 1fr) minmax(
            130px,
            0.5fr
        );
    gap: 12px;
    align-items: end;
    margin-top: 12px;
    padding: 14px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #f7fbf5;
}

.duration-pill {
    display: grid;
    gap: 6px;
    min-height: 42px;
    padding: 9px 12px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.selection-help {
    display: grid;
    gap: 5px;
    min-height: 42px;
    padding: 9px 12px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.selection-help span {
    color: #607267;
    font-size: 11px;
    font-weight: 800;
}

.selection-help strong {
    color: #16231a;
    font-size: 14px;
    font-weight: 850;
}

.selection-help small {
    color: #607267;
    font-size: 12px;
    font-weight: 650;
    line-height: 1.35;
}

.duration-pill span {
    color: #607267;
    font-size: 11px;
    font-weight: 800;
}

.duration-pill strong {
    color: #16231a;
    font-size: 14px;
    font-weight: 850;
}

.duration-pill.active {
    border-color: rgba(47, 158, 68, 0.45);
    background: #e8f7ec;
}

label {
    display: grid;
    gap: 7px;
}

label span,
.summary-list dt {
    color: #223127;
    font-size: 13px;
    font-weight: 760;
}

input,
select {
    width: 100%;
}

input.invalid {
    border-color: #dc2626 !important;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08) !important;
}

.field-error {
    color: #b91c1c;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.35;
}

.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin: 14px 0;
    color: #475b4d;
    font-size: 12px;
    font-weight: 700;
}

.legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.legend i {
    width: 12px;
    height: 12px;
    border: 1px solid #b9cbbb;
    border-radius: 3px;
    background: #fff;
}

.legend i.selected {
    border-color: #2f9e44;
    background: #2f9e44;
}

.day-grid label {
    border: 1px solid #d0d7de;
    background: #fff;
    color: #334155;
    border-radius: 8px;
    padding: 8px 12px;
    cursor: pointer;
}

.day-grid label.selected {
    border-color: #2f9e44;
    background: #2f9e44;
    color: #fff;
}

.day-grid label.selected span {
    color: #fff;
}

.legend i.booked-paid {
    border-color: #98d8a7;
    background: #dff7e6;
}

.legend i.booked-online {
    border-color: #9fc5ff;
    background: #e7f0ff;
}

.legend i.booked-counter {
    border-color: #c7b7ff;
    background: #f0ebff;
}

.legend i.pay-later {
    border-color: #f0c46b;
    background: #fff4ce;
}

.legend i.overdue {
    border-color: #f4a8a8;
    background: #ffe4e4;
}

.legend i.locked {
    border-color: #c4cec4;
    background: #e3e8e3;
}

.selection-error {
    margin: 0 0 12px;
    color: #991b1b;
    font-size: 13px;
    font-weight: 800;
}

.time-board {
    display: grid;
    gap: 12px;
}

.selected-court-strip {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    padding: 12px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #f7fbf5;
    margin-top: 12px;
}

.selected-court-strip div {
    display: grid;
    gap: 3px;
}

.selected-court-strip span {
    color: #607267;
    font-size: 12px;
    font-weight: 750;
}

.selected-court-strip strong {
    color: #16231a;
    font-size: 14px;
    font-weight: 850;
}

.tabs-and-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
}

.tabs-and-actions .tabs {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.tabs-and-actions .tabs button {
    margin-right: 0;
    white-space: nowrap;
}

.tabs-and-actions .secondary-btn {
    flex: 0 0 auto;
    margin-left: auto;
}

.tabs button {
    margin-right: 8px;
}
.period-tabs {
    display: flex;
    flex-wrap: wrap;
}

.period-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin: 14px 0;
}

.period-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.legend {
    display: grid;
    grid-template-columns: repeat(4, max-content);
    grid-auto-rows: 18px;
    align-items: center;
    justify-content: end;
    gap: 4px 12px;
    margin: 1px 0 0 auto;
    padding: 0;
    border: 0;
    background: transparent;
    color: #475b4d;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
}

.legend span {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
}

.period-tabs button {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-height: 38px;
    padding: 8px 12px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
    color: #344238;
    font-weight: 850;
}

.period-tabs button.active {
    border-color: #2f9e44;
    background: #2f9e44;
    color: #fff;
}

.period-tabs span {
    font-size: 12px;
    font-weight: 700;
    opacity: 0.8;
}

.slot-matrix {
    display: grid;
    overflow-x: auto;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.matrix-head,
.matrix-court,
.time-slot {
    min-height: 36px;
    border-right: 1px solid #e4eee4;
    border-bottom: 1px solid #e4eee4;
}

.matrix-head {
    display: grid;
    place-items: center;
    background: #f2f7ef;
    color: #334238;
    font-size: 11px;
    font-weight: 850;
}

.matrix-court {
    display: grid;
    align-content: center;
    gap: 2px;
    padding: 6px 10px;
    background: #fff;
}

.matrix-court strong {
    color: #16231a;
    font-size: 12px;
    font-weight: 850;
}

.matrix-court span {
    color: #607267;
    font-size: 11px;
    font-weight: 700;
}

.sticky-col {
    position: sticky;
    left: 0;
    z-index: 2;
}

.matrix-head.sticky-col {
    z-index: 3;
}

.time-slot {
    padding: 0;
    border-radius: 0;
    background: #fff;
    transition:
        background 0.16s ease,
        box-shadow 0.16s ease;
}

.time-slot:hover:not(:disabled) {
    background: #e8f7ec;
    box-shadow: inset 0 0 0 1px rgba(47, 158, 68, 0.4);
}

.time-slot.selected {
    background: #2f9e44;
    box-shadow: inset 0 0 0 1px #2f9e44;
}

.time-slot.busy {
    background: #eef3ee;
}

.time-slot.booked-paid {
    background: #dff7e6;
}

.time-slot.booked-online {
    background: #e7f0ff;
}

.time-slot.booked-counter {
    background: #f0ebff;
}

.time-slot.pay-later {
    background: #fff4ce;
}

.time-slot.overdue {
    background: #ffe4e4;
}

.time-slot.locked {
    background: #e3e8e3;
}

.time-slot.viewing {
    box-shadow: inset 0 0 0 2px #166534;
}

.time-slot:disabled {
    cursor: not-allowed;
    opacity: 0.72;
}

.modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: grid;
    place-items: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0.46);
}

.conflict-modal {
    width: min(900px, calc(100vw - 32px));
    max-height: min(720px, calc(100vh - 40px));
    display: grid;
    gap: 14px;
    overflow: auto;
    padding: 20px;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 18px 60px rgba(15, 23, 42, 0.22);
}

.confirm-modal,
.qr-modal {
    width: min(520px, 100%);
    display: grid;
    gap: 14px;
    padding: 20px;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 18px 60px rgba(15, 23, 42, 0.22);
}

.qr-modal {
    width: min(460px, 100%);
}

.qr-modal > img {
    width: min(260px, 100%);
    justify-self: center;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.confirm-summary {
    padding: 12px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #f7fbf5;
}

.copy-value {
    padding: 0;
    border: 0;
    background: transparent;
    color: #0f7a31;
    font-weight: 850;
    text-align: right;
    cursor: pointer;
}

.qr-waiting {
    margin: 0;
    color: #607267;
    font-size: 13px;
    line-height: 1.45;
    text-align: center;
}

.primary-btn.danger {
    border-color: #dc2626;
    background: #dc2626;
}

.modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid #d9e8d9;
}

.modal-head span {
    color: #0f7a31;
    font-size: 12px;
    font-weight: 850;
}

.modal-head h2 {
    margin: 4px 0 0;
    color: #16231a;
    font-size: 20px;
    font-weight: 900;
}

.conflict-help {
    margin: 0;
    color: #475b4d;
    font-size: 14px;
    line-height: 1.5;
}

.conflict-list {
    display: grid;
    gap: 10px;
}

.conflict-list article {
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    gap: 12px;
    align-items: center;
    padding: 12px;
    border: 1px solid #f1d0d0;
    border-radius: 8px;
    background: #fff7f7;
}

.conflict-list article > div {
    display: grid;
    gap: 4px;
    min-width: 0;
}

.conflict-list strong {
    color: #16231a;
    font-size: 14px;
    font-weight: 850;
}

.conflict-list span,
.conflict-list small {
    color: #607267;
    font-size: 12px;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.conflict-list select {
    min-width: 0;
    width: 100%;
}

@media (max-width: 720px) {
    .conflict-list article {
        grid-template-columns: 1fr;
    }
}

.modal-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 8px;
    border-top: 1px solid #d9e8d9;
}

.modal-actions .primary-btn,
.modal-actions .secondary-btn {
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 850;
    line-height: 1.25;
    cursor: pointer;
}

.modal-actions .secondary-btn {
    border: 1px solid #d6e2d8;
    background: #fff;
    color: #344238;
}

.modal-actions .primary-btn {
    border: 1px solid #16a34a;
    background: #16a34a;
    color: #fff;
}

.modal-actions .primary-btn.danger {
    border-color: #dc2626;
    background: #dc2626;
}

.confirm-reason-field {
    margin-top: 14px;
}

.confirm-reason-field span {
    color: #334155;
    font-size: 13px;
    font-weight: 800;
}

.confirm-reason-field textarea {
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    color: #0f172a;
    font: inherit;
    line-height: 1.5;
    min-height: 92px;
    padding: 10px 12px;
    resize: vertical;
}

.confirm-reason-field textarea:focus {
    border-color: #22c55e;
    box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.14);
    outline: none;
}

.confirm-reason-field small {
    color: #64748b;
    font-size: 12px;
    line-height: 1.45;
}

.modal-actions .primary-btn:disabled,
.modal-actions .secondary-btn:disabled {
    opacity: 0.58;
    cursor: not-allowed;
}

.booking-side {
    position: sticky;
    top: 88px;
    display: grid;
    gap: 12px;
}

.side-section {
    display: grid;
    gap: 10px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e4eee4;
}

.side-section.disabled {
    opacity: 0.56;
    pointer-events: none;
}

.occupied-detail {
    border-bottom: 0;
}

.status-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.status-actions .secondary-btn.compact {
    min-height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 9px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 850;
    line-height: 1.25;
    white-space: normal;
}

.status-actions .action-success {
    border-color: #16a34a;
    background: #16a34a;
    color: #fff;
}

.status-actions .action-primary {
    border-color: #16a34a;
    background: #16a34a;
    color: #fff;
}

.status-actions .action-cash {
    border-color: #16a34a;
    background: #16a34a;
    color: #fff;
}

.status-actions .action-transfer {
    border-color: #16a34a;
    background: #16a34a;
    color: #fff;
}

.secondary-btn.danger {
    border-color: #f3b4b4;
    background: #fff5f5;
    color: #b91c1c;
}

.empty-summary {
    display: grid;
    place-items: center;
    min-height: 78px;
    padding: 14px;
    border: 1px dashed #b9cbbb;
    border-radius: 8px;
    color: #607267;
    text-align: center;
}

.summary-list {
    display: grid;
    gap: 8px;
    margin: 0;
}

.summary-list div {
    display: flex;
    justify-content: space-between;
    gap: 14px;
}

.summary-list dd {
    margin: 0;
    color: #16231a;
    font-weight: 800;
    text-align: right;
}

.booking-status-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 0 0 8px;
    border-bottom: 1px solid #edf3ea;
    background: transparent;
}

.booking-status-strip .status-badge {
    min-height: 28px;
}

.occupied-detail .summary-list {
    gap: 0;
}

.occupied-detail .summary-list div {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    min-width: 0;
    padding: 7px 0;
    border-bottom: 1px solid #edf3ea;
}

.occupied-detail .summary-list dt {
    color: #6b7d70;
    font-size: 12px;
    font-weight: 850;
    line-height: 1.35;
}

.occupied-detail .summary-list dd {
    color: #263a2d;
    font-size: 13px;
    line-height: 1.35;
    text-align: right;
    overflow-wrap: anywhere;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 26px;
    padding: 5px 10px;
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    line-height: 1.15;
    white-space: nowrap;
}

.status-badge.tone-paid {
    border-color: #b7ebc6;
    background: #dcfce7;
    color: #166534;
}

.status-badge.tone-online {
    border-color: #bfdbfe;
    background: #dbeafe;
    color: #1d4ed8;
}

.status-badge.tone-counter {
    border-color: #ddd6fe;
    background: #ede9fe;
    color: #5b21b6;
}

.status-badge.tone-later {
    border-color: #fde68a;
    background: #fef3c7;
    color: #92400e;
}

.status-badge.tone-overdue {
    border-color: #fecaca;
    background: #fee2e2;
    color: #b91c1c;
}

.status-badge.tone-pending {
    border-color: #fed7aa;
    background: #ffedd5;
    color: #c2410c;
}

.status-badge.tone-confirmed {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
}

.status-badge.tone-cancelled {
    border-color: #e5e7eb;
    background: #f3f4f6;
    color: #4b5563;
}

.status-badge.tone-locked,
.status-badge.tone-neutral {
    border-color: #d9e8d9;
    background: #f7fbf5;
    color: #475b4d;
}

.payment-list {
    display: grid;
    gap: 8px;
}

.payment-card {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    padding: 11px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.payment-card.active {
    border-color: #2f9e44;
    background: #e8f7ec;
}

.payment-card input {
    width: 16px;
    height: 16px;
    accent-color: #2f9e44;
}

.payment-card strong {
    color: #216b34;
}

.payment-card small {
    display: block;
    margin-top: 4px;
    color: #607267;
    font-size: 12px;
    font-weight: 650;
    line-height: 1.35;
}

.inline-note {
    padding: 10px 12px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #f7fbf5;
    color: #475b4d;
    font-size: 13px;
    font-weight: 700;
}

.recurring-payment {
    display: grid;
    gap: 12px;
    padding: 14px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #f7fbf5;
}

.recurring-payment-list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.recurring-payment-list .payment-card {
    grid-template-columns: auto minmax(0, 1fr);
    background: #fff;
}

.voucher-section {
    background: #fff;
}

.voucher-picker {
    display: grid;
    gap: 9px;
}

.voucher-code-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
}

.voucher-code-row input {
    min-width: 0;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    padding: 10px 12px;
    color: #1f2f25;
    font-weight: 720;
}

.voucher-list {
    display: grid;
    gap: 8px;
}

.voucher-list button {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    width: 100%;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
    padding: 10px 11px;
    text-align: left;
    cursor: pointer;
}

.voucher-list button.active {
    border-color: #16a34a;
    background: #ecfdf3;
}

.voucher-list strong {
    display: block;
    color: #14532d;
    font-size: 13px;
    font-weight: 900;
}

.voucher-list small,
.voucher-empty {
    color: #607267;
    font-size: 12px;
    font-weight: 700;
}

.voucher-list em {
    color: #15803d;
    font-style: normal;
    font-weight: 900;
}

.recurring-collect-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.recurring-collect-actions button {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid #16a34a;
    border-radius: 8px;
    background: #fff;
    color: #15803d;
    font-size: 14px;
    font-weight: 850;
    cursor: pointer;
}

.recurring-collect-actions button.active {
    background: #16a34a;
    color: #fff;
}

.settlement-card {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    padding: 12px;
    /* border: 1px solid #d9e8d9; */
    border-radius: 8px;
    /* background: #fff; */
}

.segmented-field {
    display: grid;
    gap: 7px;
}

.segmented-field > span {
    color: #223127;
    font-size: 13px;
    font-weight: 760;
}

.segmented-field > div {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.segmented-field button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 38px;
    padding: 8px 12px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
    color: #344238;
    font-weight: 850;
}

.segmented-field button.active {
    border-color: #2f9e44;
    background: #2f9e44;
    color: #fff;
}

.qr-section {
    border-bottom: 0;
    padding-bottom: 0;
}

.qr-section img {
    width: min(210px, 100%);
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.qr-info {
    display: grid;
    gap: 8px;
}

.qr-info div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #475b4d;
    font-size: 13px;
}

.qr-info button {
    border: 0;
    background: transparent;
    color: #216b34;
    font-weight: 850;
    text-decoration: underline;
}

.qr-info strong {
    color: #16231a;
}

.day-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.day-grid label {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    min-width: 48px;
    padding: 8px 14px;
    border: 1px solid #d9e8d9;
    border-radius: 999px;
    background: #fff;
    color: #425246;
    font-weight: 900;
    cursor: pointer;
    transition:
        border-color 0.16s ease,
        background 0.16s ease,
        color 0.16s ease,
        box-shadow 0.16s ease,
        transform 0.16s ease;
}

.day-grid label:hover {
    border-color: #86efac;
    background: #f0fdf4;
    transform: translateY(-1px);
}

.day-grid label.selected {
    border-color: #2f9e44;
    background: #2f9e44;
    color: #fff;
    box-shadow: 0 6px 14px rgba(47, 158, 68, 0.18);
}

.day-grid input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}

.day-grid span {
    color: inherit;
}

.month-days {
    max-width: 320px;
}

.recurring-helper {
    margin: -6px 0 4px;
    color: #607267;
    font-size: 13px;
    line-height: 1.45;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
}

.preview-box {
    position: sticky;
    top: 88px;
    display: grid;
    gap: 10px;
}

.preview-head {
    display: grid;
    gap: 4px;
    padding-bottom: 10px;
    border-bottom: 1px solid #d9e8d9;
}

.preview-head > span {
    color: #0f7a31;
    font-size: 12px;
    font-weight: 850;
}

.preview-box strong {
    color: #16231a;
    font-size: 18px;
    font-weight: 850;
}

.preview-box span,
.preview-box small {
    color: #607267;
}

.recurring-summary-list {
    gap: 8px;
}

.recurring-summary-list div {
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #edf3ea;
}

.recurring-summary-list dt {
    color: #607267;
    font-size: 12px;
}

.recurring-summary-list dd {
    font-size: 13px;
    overflow-wrap: anywhere;
}

.preview-dates {
    display: grid;
    gap: 8px;
}

.preview-dates > strong {
    color: #405347;
    font-size: 12px;
    text-transform: uppercase;
}

.recurring-preview-panel {
    display: grid;
    gap: 10px;
    padding-top: 4px;
}

.preview-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.preview-panel-head strong {
    color: #1f3326;
    font-size: 13px;
    font-weight: 900;
    text-transform: uppercase;
}

.preview-panel-head span {
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
}

.preview-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.preview-stat-grid > div {
    display: grid;
    gap: 3px;
    min-width: 0;
    padding: 9px;
    border: 1px solid #dce8de;
    border-radius: 8px;
    background: #fff;
}

.preview-stat-grid span {
    color: #607267;
    font-size: 11px;
    font-weight: 750;
}

.preview-stat-grid strong {
    color: #16231a;
    font-size: 18px;
    font-weight: 900;
}

.preview-stat-grid .ok strong {
    color: #15803d;
}

.preview-stat-grid .danger {
    border-color: #fecaca;
    background: #fff7f7;
}

.preview-stat-grid .danger strong {
    color: #dc2626;
}

.preview-warning {
    margin: 0;
    padding: 9px 10px;
    border: 1px solid #fed7aa;
    border-radius: 8px;
    background: #fff7ed;
    color: #9a3412;
    font-size: 12px;
    font-weight: 750;
    line-height: 1.45;
}

.recurring-preview-list {
    display: grid;
    gap: 7px;
    max-height: 260px;
    overflow: auto;
    padding-right: 2px;
}

.recurring-preview-list article {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 9px 10px;
    border: 1px solid #dfeade;
    border-radius: 8px;
    background: #fff;
}

.recurring-preview-list article > div {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.recurring-preview-list strong {
    color: #1f3326;
    font-size: 13px;
    font-weight: 900;
}

.recurring-preview-list small {
    color: #607267;
    font-size: 11px;
    line-height: 1.35;
}

.recurring-preview-list article > span {
    flex: 0 0 auto;
    border-radius: 999px;
    padding: 4px 8px;
    background: #eef2f7;
    color: #475569;
    font-size: 11px;
    font-weight: 900;
}

.recurring-preview-list .status-available {
    border-color: #bbf7d0;
    background: #f0fdf4;
}

.recurring-preview-list .status-available > span {
    background: #dcfce7;
    color: #15803d;
}

.recurring-preview-list .status-conflict {
    border-color: #fecaca;
    background: #fff7f7;
}

.recurring-preview-list .status-conflict > span {
    background: #fee2e2;
    color: #b91c1c;
}

.preview-list {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.preview-list span {
    padding: 5px 8px;
    border-radius: 999px;
    background: #e8f7ec;
    color: #216b34;
    font-size: 12px;
    font-weight: 750;
}

.primary-btn.full {
    width: 100%;
}

.state-card {
    padding: 22px;
    color: #607267;
    text-align: center;
}

.error-state {
    color: #991b1b;
}

.alert {
    padding: 13px 14px;
    font-weight: 800;
}

.alert.error {
    border-color: #f0b9b9;
    background: #fff5f5;
    color: #991b1b;
}

.alert.success {
    border-color: #bfe8ca;
    background: #e8f7ec;
    color: #216b34;
}

/* Keep this after the shared modal rules so the recurring detail modal
   does not collapse back to the generic 520px confirm modal width. */
.confirm-modal.recurring-detail-modal {
    width: min(1100px, calc(100vw - 32px));
    max-height: min(780px, calc(100vh - 40px));
    grid-template-rows: auto minmax(0, 1fr) auto;
    overflow: hidden;
}

.confirm-modal.recurring-detail-modal .recurring-detail-body {
    grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.25fr);
}

.confirm-modal.recurring-detail-modal .modal-actions {
    flex-wrap: wrap;
}

@media (max-width: 860px) {
    .confirm-modal.recurring-detail-modal {
        width: min(720px, calc(100vw - 24px));
        overflow: auto;
    }

    .confirm-modal.recurring-detail-modal .recurring-detail-body {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 1080px) {
    .counter-board,
    .recurring-panel {
        grid-template-columns: 1fr;
    }

    .booking-side,
    .preview-box {
        position: static;
    }
}

@media (max-width: 820px) {
    .tabs-and-actions {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }

    .tabs-and-actions .secondary-btn {
        width: 100%;
        justify-content: center;
    }

    .schedule-filters,
    .form-grid,
    .booking-picker,
    .selected-court-strip,
    .schedule-summary,
    .settlement-card,
    .recurring-payment-list {
        grid-template-columns: 1fr;
    }

    .period-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .legend {
        justify-content: flex-start;
    }
}
</style>

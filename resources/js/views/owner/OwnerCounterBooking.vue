<template>
    <div class="owner-counter-page">
        <section class="page-head">
            <div>
                <h1>Booking tại quầy</h1>
                <p>
                    Quản lý lịch sân trong ngày, tạo booking vãng lai và lịch cố
                    định cho khách quen.
                </p>
            </div>
            <button class="secondary-btn" type="button" @click="loadSchedule">
                <AppIcon name="refresh" size="16" />
                <span>Tải lại lịch</span>
            </button>
        </section>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="notice" class="alert success">{{ notice }}</div>

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
                            @change="loadSchedule"
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

                <div
                    v-if="scheduleLoading || !scheduleCourts.length"
                    class="state-card"
                >
                    Đang tải lịch sân...
                </div>
                <div v-else-if="scheduleError" class="state-card error-state">
                    {{ scheduleError }}
                </div>
                <!-- <div v-else-if="!scheduleCourts.length" class="state-card">
                    Không có sân phù hợp với bộ lọc hiện tại.
                </div> -->
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
                            @change="handleRecurringStartDateChange"
                        />
                    </label>
                    <label>
                        <span>Đến ngày</span>
                        <input
                            v-model="form.recurring_end_date"
                            type="date"
                            :min="form.recurring_start_date || today"
                            @change="syncRecurringEndDate"
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
                        <span>Chu kỳ</span>
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
                        <dt>Kiểu lặp</dt>
                        <dd>{{ recurringPatternText }}</dd>
                    </div>
                    <div>
                        <dt>Giá mỗi buổi</dt>
                        <dd>{{ formatCurrency(recurringUnitTotal) }}</dd>
                    </div>
                    <div>
                        <dt>Tổng tiền</dt>
                        <dd>{{ formatCurrency(recurringTotalAmount) }}</dd>
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

                <div v-if="recurringPreview.length" class="preview-list">
                    <span
                        v-for="date in recurringPreview.slice(0, 18)"
                        :key="date"
                        >{{ formatDate(date) }}</span
                    >
                </div>
                <small v-if="recurringPreview.length > 18"
                    >Còn {{ recurringPreview.length - 18 }} buổi khác.</small
                >
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
            <div v-else class="recurring-group-list">
                <article
                    v-for="group in recurringGroups"
                    :key="group.recurring_group_code"
                    class="recurring-group-card"
                >
                    <div class="group-main">
                        <span class="group-code">{{
                            group.recurring_group_code
                        }}</span>
                        <strong>{{ recurringGroupCustomer(group) }}</strong>
                        <small>{{ recurringGroupPhone(group) }}</small>
                    </div>
                    <div>
                        <span>Sân</span>
                        <strong>{{
                            (group.court_names || []).join(", ") || "-"
                        }}</strong>
                        <small
                            >{{ group.booking_count }} buổi ·
                            {{ formatDate(group.start_date) }} -
                            {{ formatDate(group.end_date) }}</small
                        >
                    </div>
                    <div>
                        <span>Khung giờ</span>
                        <strong
                            >{{ recurringGroupTimeText(group) }}</strong
                        >
                        <small>{{
                            paymentOptionLabel(group.payment_option)
                        }}</small>
                    </div>
                    <div>
                        <span>Bill nhóm</span>
                        <strong>{{ formatCurrency(group.total_price) }}</strong>
                        <small
                            >Đã thu
                            {{ formatCurrency(group.paid_amount) }}</small
                        >
                    </div>
                    <div>
                        <span>Còn thu</span>
                        <strong
                            :class="{
                                paid:
                                    Number(group.outstanding_amount || 0) <= 0,
                            }"
                        >
                            {{ formatCurrency(group.outstanding_amount) }}
                        </strong>
                        <small>{{ recurringGroupStatusSummary(group) }}</small>
                    </div>
                    <div class="group-actions">
                        <button
                            type="button"
                            class="secondary-btn compact"
                            :disabled="
                                recurringGroupCollecting ===
                                    group.recurring_group_code ||
                                Number(group.outstanding_amount || 0) <= 0
                            "
                            @click="
                                openRecurringGroupCollectConfirm(group, 'cash')
                            "
                        >
                            <AppIcon name="banknote" size="15" />
                            <span>Thu tiền mặt</span>
                        </button>
                        <button
                            type="button"
                            class="secondary-btn compact"
                            :disabled="
                                recurringGroupCollecting ===
                                    group.recurring_group_code ||
                                Number(group.outstanding_amount || 0) <= 0
                            "
                            @click="
                                collectRecurringGroup(group, 'bank_transfer')
                            "
                        >
                            <AppIcon name="creditCard" size="15" />
                            <span>Chuyển khoản</span>
                        </button>
                    </div>
                </article>
            </div>
        </section>

        <Teleport to="body">
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
                            :disabled="bookingActionLoading"
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
                    <p class="qr-waiting">
                        Đang chờ SePay ghi nhận giao dịch. Màn này sẽ tự đóng
                        khi thanh toán thành công.
                    </p>
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
                                    v-for="court in conflict.alternatives"
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
                            <small v-if="!conflict.alternatives?.length"
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
                            Hủy toàn bộ
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
            recurringGroupFilters: {
                venue_court_id: "",
                status: "",
                q: "",
            },
            recurringConflict: null,
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
        recurringRequiredAmount() {
            if (this.form.payment_option === "no_prepay") return 0;
            if (this.form.payment_option === "deposit") {
                return Math.round(
                    (this.recurringTotalAmount * this.depositPercent) / 100,
                );
            }

            return this.recurringTotalAmount;
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
            const every =
                Number(this.form.recurrence_interval || 1) > 1
                    ? `${this.form.recurrence_interval} `
                    : "";

            if (this.form.recurrence_type === "daily") {
                return `${every}ngày/lần`;
            }

            if (this.form.recurrence_type === "weekly") {
                const days = this.weekDays
                    .filter((day) =>
                        this.form.recurrence_days_of_week.includes(day.value),
                    )
                    .map((day) => day.label)
                    .join(", ");

                return `${every}tuần/lần${days ? ` · ${days}` : ""}`;
            }

            return `${every}tháng/lần · ngày ${this.monthDaysInput || "-"}`;
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
                    amount: this.selectedTotal,
                },
                {
                    value: "transfer",
                    label: "Chuyển khoản",
                    amount: this.selectedTotal,
                },
                {
                    value: "later",
                    label: "Thu sau",
                    amount: this.selectedTotal,
                },
            ];
        },
        paymentOptions() {
            const config = this.selectedClusterDetail?.booking_config || {};
            const baseAmount =
                this.activeTab === "recurring"
                    ? this.recurringTotalAmount
                    : this.selectedTotal;
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
                deposit: `Ghi nhận tiền cọc ${this.depositPercent}% theo chính sách hiện tại.`,
                no_prepay: "Tạo lịch trước, thu tiền sau khi khách đến chơi.",
            };

            return this.paymentOptions.map((option) => ({
                ...option,
                description: descriptions[option.value] || option.label,
            }));
        },
        counterSummaryRows() {
            return [
                ["Cụm sân", this.selectedCluster?.name || "-"],
                ["Sân", this.selectedCourtText],
                ["Ngày", this.formatDate(this.form.booking_date)],
                ["Giờ", this.selectedTimeText],
                ["Thời lượng", this.selectedDurationText],
                ["Tổng tiền", this.formatCurrency(this.selectedTotal)],
            ];
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

            return [
                ["Mã nhóm", group.recurring_group_code || "-"],
                [
                    "Khách",
                    `${this.recurringGroupCustomer(group)} · ${this.recurringGroupPhone(group)}`,
                ],
                ["Cụm sân", group.venue_cluster_name || "-"],
                ["Sân", (group.court_names || []).join(", ") || "-"],
                [
                    "Ngày",
                    `${this.formatDate(group.start_date)} - ${this.formatDate(group.end_date)}`,
                ],
                ["Khung giờ", this.recurringGroupTimeText(group)],
                ["Số buổi", `${group.booking_count || 0} buổi`],
                ["Hình thức", this.paymentOptionLabel(group.payment_option)],
                ["Tổng bill", this.formatCurrency(group.total_price)],
                ["Đã thu", this.formatCurrency(group.paid_amount)],
                ["Còn thu", this.formatCurrency(group.outstanding_amount)],
            ];
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
    async created() {
        await this.loadOwnerData();
    },
    beforeUnmount() {
        this.clearCounterQrPolling();
    },
    methods: {
        async setActiveTab(tab) {
            this.activeTab = tab;
            this.error = "";
            this.notice = "";
            this.selectionError = "";

            if (tab === "recurringList") {
                await this.loadRecurringGroups();
                return;
            }

            await this.loadSchedule();
        },
        async handleRecurringStartDateChange() {
            this.syncRecurringEndDate();
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
                await this.handleClusterChange();
            } catch (error) {
                this.error = error.message || "Không thể tải dữ liệu cụm sân.";
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
            this.form.venue_court_id = this.courts[0]?.id || "";
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
            } catch (error) {
                this.scheduleError = error.message || "Không thể tải lịch sân.";
            } finally {
                this.scheduleLoading = false;
            }
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
                this.isViewingPastScheduleDate &&
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
                if (this.isViewingPastScheduleDate && !interval) {
                    return `${courtName} · ${start} - ${end} là lịch quá khứ, chỉ dùng để xem.`;
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
        },
        selectRecurringSlot(court, slot) {
            if (!court?.id || !slot) return;

            const clickedKey = this.slotKey(court.id, slot);
            this.selectionError = "";
            this.selectedSlotKeys = this.selectedSlotKeys.includes(clickedKey)
                ? this.selectedSlotKeys.filter((item) => item !== clickedKey)
                : [...this.selectedSlotKeys, clickedKey];
            this.syncCounterRangeFields();
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
                await this.loadSchedule();
            } catch (error) {
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
            await this.loadSchedule();
        },
        openRecurringConflict(data) {
            const selections = {};
            (data.conflicts || []).forEach((conflict) => {
                selections[conflict.date] =
                    conflict.alternatives?.[0]?.id || "skip";
            });
            this.recurringConflict = data;
            this.conflictSelections = selections;
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
                this.error = error.message || "Không thể tạo lịch cố định.";
            } finally {
                this.submitting = false;
            }
        },
        syncPaymentOption() {
            if (
                !this.paymentOptions.some(
                    (option) => option.value === this.form.payment_option,
                )
            ) {
                this.form.payment_option =
                    this.paymentOptions[0]?.value || "no_prepay";
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
                    payload.status_reason = "Chủ sân hủy booking tại quầy.";
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
                await this.loadSchedule();
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
            const every =
                Number(booking?.recurrence_interval || 1) > 1
                    ? `${booking.recurrence_interval} `
                    : "";

            if (booking?.recurrence_type === "daily") {
                return `${every}ngày/lần`;
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

                return `${every}tuần/lần${days ? ` · ${days}` : ""}`;
            }

            if (booking?.recurrence_type === "monthly") {
                return `${every}tháng/lần · ngày ${(booking.recurrence_days_of_month || []).join(", ") || "-"}`;
            }

            return "-";
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
                await this.loadSchedule();
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

.recurring-group-card {
    display: grid;
    grid-template-columns:
        minmax(180px, 1.2fr) minmax(220px, 1.3fr) minmax(140px, 0.8fr)
        minmax(130px, 0.7fr) minmax(150px, 0.8fr) auto;
    gap: 14px;
    align-items: center;
    padding: 14px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fbfdfb;
}

.recurring-group-card > div {
    display: grid;
    gap: 4px;
    min-width: 0;
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
    width: min(760px, 100%);
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
    grid-template-columns: minmax(210px, 1fr) minmax(240px, 1fr);
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
}

.modal-actions {
    display: flex;
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
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.recurring-payment-list .payment-card {
    grid-template-columns: auto minmax(0, 1fr);
    background: #fff;
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
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 8px 12px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.day-grid label.selected {
    border-color: #2f9e44;
    background: #e8f7ec;
    color: #216b34;
}

.day-grid input {
    width: 15px;
    height: 15px;
    accent-color: #2f9e44;
}

.month-days {
    max-width: 320px;
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

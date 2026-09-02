<template>
    <div class="owner-counter-page">
        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="notice" class="alert success">{{ notice }}</div>

        <!-- Single Unified Surface Container -->
        <div class="cluster-profile-surface standalone">
            <!-- Nav Tabs integrated directly at top of card -->
            <div v-if="!isBookingListRoute" class="hero-integrated-tabs">
                <AppTabs
                    :tabs="counterTabs"
                    :model-value="activeTab"
                    @update:model-value="setActiveTab"
                />
            </div>
            <!-- SECTION: Lịch sân trong ngày & Đặt booking tại quầy -->
            <div v-if="activeTab === 'counter'" class="profile-section-card">
                <!-- Toolbar lọc ngày chơi và loại sân (Không bọc viền ngoài) -->
                <div class="counter-schedule-toolbar" style="background: transparent; border: none; padding: 0 0 16px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <label style="font-size: 13.5px; font-weight: 500; color: #0f172a; white-space: nowrap;">Ngày chơi:</label>
                        <div class="counter-date-range" style="display: flex; align-items: center; gap: 6px;">
                            <button
                                type="button"
                                class="date-nav-btn"
                                aria-label="Ngày trước"
                                style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; cursor: pointer;"
                                @click="shiftCounterDate(-1)"
                            >
                                <AppIcon name="chevronLeft" size="15" />
                            </button>

                            <div class="date-picker-wrap" style="position: relative;">
                                <button
                                    type="button"
                                    class="date-range-trigger"
                                    :class="{ open: counterDatePickerOpen }"
                                    style="height: 36px; display: inline-flex; align-items: center; gap: 8px; padding: 0 14px; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; font-size: 13.5px; font-weight: 500; cursor: pointer;"
                                    @click="counterDatePickerOpen = !counterDatePickerOpen"
                                >
                                    <AppIcon name="calendar" size="16" />
                                    <span>{{ counterDateRangeLabel }}</span>
                                    <AppIcon name="chevronDown" size="14" style="color: #64748b;" />
                                </button>
                                <div
                                    v-if="counterDatePickerOpen"
                                    class="counter-date-popover"
                                    style="position: absolute; top: 100%; left: 0; margin-top: 6px; z-index: 1000;"
                                >
                                    <MiniCalendar
                                        mode="range"
                                        :dual-month="false"
                                        :start-date="form.booking_date"
                                        :end-date="form.booking_end_date"
                                        :min-date="today"
                                        @update:start-date="handleCounterStartDateUpdate"
                                        @update:end-date="handleCounterEndDateUpdate"
                                        @range-change="handleCounterRangeChange"
                                    />
                                </div>
                            </div>

                            <button
                                type="button"
                                class="date-nav-btn"
                                aria-label="Ngày sau"
                                style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; cursor: pointer;"
                                @click="shiftCounterDate(1)"
                            >
                                <AppIcon name="chevronRight" size="15" />
                            </button>

                            <button
                                type="button"
                                class="today-btn"
                                style="height: 36px; padding: 0 14px; border-radius: 6px; border: 1px solid #16a34a; background: #f0fdf4; color: #16a34a; font-size: 13px; font-weight: 500; cursor: pointer;"
                                @click="setCounterDateToday"
                            >
                                Hôm nay
                            </button>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px; position: relative;">
                        <label style="font-size: 13.5px; font-weight: 500; color: #0f172a; white-space: nowrap;">Loại sân:</label>
                        <div class="custom-court-type-dropdown" style="position: relative;">
                            <button
                                type="button"
                                class="court-type-trigger-btn"
                                style="height: 36px; display: inline-flex; align-items: center; justify-content: space-between; gap: 10px; padding: 0 14px; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; font-size: 13.5px; font-weight: 500; cursor: pointer; min-width: 170px; outline: none;"
                                @click="courtTypeDropdownOpen = !courtTypeDropdownOpen"
                            >
                                <span>{{ selectedCourtTypeName }}</span>
                                <AppIcon name="chevronDown" size="14" style="color: #64748b;" />
                            </button>

                            <div
                                v-if="courtTypeDropdownOpen"
                                class="custom-dropdown-menu"
                                style="position: absolute; top: calc(100% + 4px); right: 0; min-width: 220px; z-index: 1000; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.12); padding: 4px 0; max-height: 260px; overflow-y: auto;"
                            >
                                <div
                                    class="dropdown-item"
                                    :class="{ active: !selectedCourtTypeId }"
                                    style="padding: 8px 14px; font-size: 13.5px; color: #0f172a; cursor: pointer; font-weight: 500; display: flex; align-items: center; justify-content: space-between;"
                                    @click="selectCourtType('')"
                                >
                                    <span>Tất cả loại sân</span>
                                    <AppIcon v-if="!selectedCourtTypeId" name="check" size="14" style="color: #16a34a;" />
                                </div>
                                <div
                                    v-for="type in courtTypeOptions"
                                    :key="type.id"
                                    class="dropdown-item"
                                    :class="{ active: String(selectedCourtTypeId) === String(type.id) }"
                                    style="padding: 8px 14px; font-size: 13.5px; color: #0f172a; cursor: pointer; font-weight: 500; display: flex; align-items: center; justify-content: space-between;"
                                    @click="selectCourtType(type.id)"
                                >
                                    <span>{{ type.name }}</span>
                                    <AppIcon v-if="String(selectedCourtTypeId) === String(type.id)" name="check" size="14" style="color: #16a34a;" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="selectionError" class="selection-error">
                    {{ selectionError }}
                </p>

                <!-- Loading skeleton -->
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
                <div v-else-if="!counterScheduleLoading && !scheduleCourts.length" class="state-card">
                    Không có sân phù hợp với bộ lọc hiện tại.
                </div>
                <div v-else>
                    <div class="period-tabs-bar" style="margin-bottom: 10px;">
                        <div class="period-tabs" role="tablist">
                            <button
                                v-for="period in dynamicTimePeriods"
                                :key="period.key"
                                type="button"
                                :class="{ active: activeTimePeriod === period.key }"
                                @click="activeTimePeriod = period.key"
                            >
                                <strong>{{ period.label }}</strong>
                                <span>({{ period.range }})</span>
                            </button>
                        </div>
                    </div>

                    <!-- Transposed table: time=rows, courts=cols -->
                    <div class="time-row-matrix-wrap">
                        <table class="time-row-matrix" role="grid" aria-label="Bảng chọn sân và khung giờ">
                            <thead>
                                <tr>
                                    <th class="trm-corner" role="columnheader">KHUNG GIỜ</th>
                                    <th
                                        v-for="court in scheduleCourts"
                                        :key="court.id"
                                        class="trm-court-head"
                                        role="columnheader"
                                    >
                                        <strong>{{ court.name }}</strong>
                                        <span>{{ court.court_type?.name || "-" }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!activePeriodSlots.length">
                                    <td :colspan="scheduleCourts.length + 1" style="text-align: center; padding: 40px 16px; color: #64748b; background: #f8fafc; font-size: 14px;">
                                        Ca này nằm ngoài giờ hoạt động của sân ({{ currentScheduleLabel || 'sân đóng cửa' }}). Không có khung giờ chơi.
                                    </td>
                                </tr>
                                <tr
                                    v-else
                                    v-for="slot in activePeriodSlots"
                                    :key="slot.start_time"
                                    role="row"
                                >
                                    <td class="trm-time-cell" role="rowheader">
                                        {{ formatTime(slot.start_time) }} – {{ formatTime(slot.end_time) }}
                                    </td>
                                    <td
                                        v-for="court in scheduleCourts"
                                        :key="`${court.id}-${slot.start_time}`"
                                        class="trm-slot-cell"
                                        role="gridcell"
                                    >
                                        <button
                                            type="button"
                                            class="trm-slot-btn"
                                            :class="slotButtonClass(court.id, slot)"
                                            :disabled="isSlotDisabled(court.id, slot)"
                                            :aria-pressed="isSlotSelected(court.id, slot)"
                                            :aria-label="slotActionTitle(court, slot)"
                                            :title="slotActionTitle(court, slot)"
                                            @click="toggleSlot(court, slot)"
                                        >
                                            <span v-if="isSlotSelected(court.id, slot)">+ Đặt sân</span>
                                            <span v-else-if="!isSlotDisabled(court.id, slot)" class="trm-empty-hint">+ Đặt sân</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            <div
                v-if="hasCounterSelection || selectedOccupiedInterval"
                class="counter-bottom-bar"
            >
                <div>
                    <strong v-if="selectedOccupiedInterval"
                        >Đang xem lịch đã đặt</strong
                    >
                    <strong v-else
                        >{{ selectedCourtText }} ·
                        {{ selectedDurationText }}</strong
                    >
                    <span v-if="selectedOccupiedInterval">{{
                        occupiedPanelSubtitle
                    }}</span>
                    <span v-else
                        >{{ selectedTimeText }} ·
                        {{ formatCurrency(counterTotalAmount) }}</span
                    >
                </div>
                <button
                    type="button"
                    class="primary-btn"
                    @click="counterDrawerOpen = true"
                >
                    {{
                        selectedOccupiedInterval ? "Xem chi tiết" : "Tiếp theo"
                    }}
                </button>
            </div>

            <Teleport to="body">
                <button
                    v-if="counterDrawerOpen"
                    type="button"
                    class="counter-drawer-backdrop"
                    aria-label="Đóng thông tin booking"
                    @click="counterDrawerOpen = false"
                ></button>

                <aside
                    ref="counterDrawer"
                    class="booking-side"
                    :class="{ open: counterDrawerOpen }"
                >
                    <button
                        type="button"
                        class="drawer-close-btn"
                        aria-label="Đóng thông tin booking"
                        @click="counterDrawerOpen = false"
                    >
                        <AppIcon name="x" size="18" />
                    </button>
                    <section
                        v-if="!selectedOccupiedInterval"
                        class="side-section"
                    >
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
                                    v-for="[
                                        label,
                                        value,
                                    ] in occupiedSummaryRows"
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
                            <div
                                v-if="selectedBusyBooking"
                                class="status-actions"
                            >
                                <button
                                    v-if="
                                        [
                                            'pending_approval',
                                            'pending_payment',
                                        ].includes(selectedBusyBooking.status) &&
                                        selectedBusyBooking.payment_option ===
                                            'no_prepay'
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
                                    v-if="
                                        selectedBusyBooking.status ===
                                            'confirmed' &&
                                        selectedBookingOutstanding <= 0
                                    "
                                    class="secondary-btn compact action-success"
                                    type="button"
                                    :disabled="bookingActionLoading"
                                    @click="
                                        openBookingActionConfirm('status', {
                                            action: 'check_in',
                                        })
                                    "
                                >
                                    <AppIcon name="clock" size="15" />
                                    <span>Check-in</span>
                                </button>
                                <button
                                    v-if="
                                        selectedBusyBooking.status ===
                                            'checked_in' &&
                                        selectedBookingOutstanding <= 0
                                    "
                                    class="secondary-btn compact action-success"
                                    type="button"
                                    :disabled="bookingActionLoading"
                                    @click="
                                        openBookingActionConfirm('status', {
                                            action: 'complete',
                                        })
                                    "
                                >
                                    <AppIcon name="circleCheck" size="15" />
                                    <span>Hoàn thành</span>
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
                                            action:
                                                selectedBusyBooking.status ===
                                                'pending_approval'
                                                    ? 'reject'
                                                    : 'cancel',
                                        })
                                    "
                                >
                                    <AppIcon name="trash" size="15" />
                                    <span>{{
                                        selectedBusyBooking.status ===
                                        "pending_approval"
                                            ? "Từ chối booking"
                                            : "Hủy booking"
                                    }}</span>
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
                                            contactTouched.name &&
                                            walkInNameError,
                                    }"
                                    placeholder="Nhập tên khách"
                                    @input="handleContactInput('name')"
                                    @blur="validateContactField('name')"
                                />
                                <small
                                    v-if="
                                        contactTouched.name && walkInNameError
                                    "
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
                                    v-if="
                                        contactTouched.phone && walkInPhoneError
                                    "
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
                                            !hasCounterSelection ||
                                            voucherLoading
                                        "
                                        @click="applyVoucherCode"
                                    >
                                        Áp dụng
                                    </button>
                                </div>
                                <small
                                    v-if="voucherError"
                                    class="field-error"
                                    >{{ voucherError }}</small
                                >
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
                                                selectedVoucherId ===
                                                voucher.id,
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
                                            form.collection_mode ===
                                            option.value,
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
            </Teleport>
        </div>

        <div v-else-if="activeTab === 'recurring'" class="recurring-panel profile-section-card">
            <div class="form-card">


                <div class="form-grid recurring-form-grid">
                    <div class="calendar-range-field">
                        <span>{{ recurringTemplateLabel }}</span>
                        <div
                            v-if="form.recurrence_type !== 'daily'"
                            class="recurring-calendar-mode"
                            role="group"
                            aria-label="Chế độ chọn ngày"
                        >
                            <button
                                type="button"
                                :class="{
                                    active: recurringCalendarMode === 'start',
                                }"
                                @click="recurringCalendarMode = 'start'"
                            >
                                Ngày bắt đầu
                            </button>
                            <button
                                type="button"
                                :class="{
                                    active:
                                        recurringCalendarMode === 'schedule',
                                }"
                                @click="recurringCalendarMode = 'schedule'"
                            >
                                Ngày có lịch
                            </button>
                        </div>
                        <MiniCalendar
                            :mode="
                                recurringCalendarMode === 'schedule'
                                    ? 'multiple'
                                    : 'single'
                            "
                            :model-value="form.recurring_start_date"
                            :selected-dates="recurringSelectedDates"
                            :min-date="
                                recurringCalendarMode === 'schedule'
                                    ? form.recurring_start_date
                                    : today
                            "
                            :max-date="
                                recurringCalendarMode === 'schedule'
                                    ? recurringTemplateEndDate
                                    : ''
                            "
                            :highlight-start-date="form.recurring_start_date"
                            :highlight-end-date="recurringTemplateEndDate"
                            @update:model-value="updateRecurringStartDate"
                            @update:selected-dates="
                                updateRecurringSelectedDates
                            "
                            @select="handleRecurringCalendarSelect"
                        />
                        <small class="recurring-calendar-note">
                            {{ recurringCalendarNote }}
                        </small>
                    </div>
                    <div class="recurring-form-fields">
                        <div class="custom-field-wrap" style="display: flex; flex-direction: column; gap: 6px;">
                            <span class="field-label" style="font-size: 13.5px; font-weight: 500; color: #0f172a;">Loại sân</span>
                            <div class="custom-court-type-dropdown" style="position: relative;">
                                <button
                                    type="button"
                                    class="court-type-trigger-btn"
                                    style="width: 100%; height: 38px; display: inline-flex; align-items: center; justify-content: space-between; gap: 10px; padding: 0 14px; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; font-size: 13.5px; font-weight: 500; cursor: pointer; outline: none;"
                                    @click="recurringCourtTypeDropdownOpen = !recurringCourtTypeDropdownOpen"
                                >
                                    <span>{{ selectedCourtTypeName }}</span>
                                    <AppIcon name="chevronDown" size="14" style="color: #64748b;" />
                                </button>

                                <div
                                    v-if="recurringCourtTypeDropdownOpen"
                                    class="custom-dropdown-menu"
                                    style="position: absolute; top: calc(100% + 4px); left: 0; right: 0; min-width: 200px; z-index: 1000; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.12); padding: 4px 0; max-height: 240px; overflow-y: auto;"
                                >
                                    <div
                                        class="dropdown-item"
                                        :class="{ active: !selectedCourtTypeId }"
                                        style="padding: 8px 14px; font-size: 13.5px; color: #0f172a; cursor: pointer; font-weight: 500; display: flex; align-items: center; justify-content: space-between;"
                                        @click="selectCourtType('')"
                                    >
                                        <span>Tất cả loại sân</span>
                                        <AppIcon v-if="!selectedCourtTypeId" name="check" size="14" style="color: #16a34a;" />
                                    </div>
                                    <div
                                        v-for="type in courtTypeOptions"
                                        :key="type.id"
                                        class="dropdown-item"
                                        :class="{ active: String(selectedCourtTypeId) === String(type.id) }"
                                        style="padding: 8px 14px; font-size: 13.5px; color: #0f172a; cursor: pointer; font-weight: 500; display: flex; align-items: center; justify-content: space-between;"
                                        @click="selectCourtType(type.id)"
                                    >
                                        <span>{{ type.name }}</span>
                                        <AppIcon v-if="String(selectedCourtTypeId) === String(type.id)" name="check" size="14" style="color: #16a34a;" />
                                    </div>
                                </div>
                            </div>
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
                                @blur="validateContactField('phone')"
                            />
                            <small
                                v-if="contactTouched.phone && walkInPhoneError"
                                class="field-error"
                            >
                                {{ walkInPhoneError }}
                            </small>
                        </label>

                        <div class="custom-field-wrap" style="display: flex; flex-direction: column; gap: 6px;">
                            <span class="field-label" style="font-size: 13.5px; font-weight: 500; color: #0f172a;">Loại chu kỳ</span>
                            <div class="custom-court-type-dropdown" style="position: relative;">
                                <button
                                    type="button"
                                    class="court-type-trigger-btn"
                                    style="width: 100%; height: 38px; display: inline-flex; align-items: center; justify-content: space-between; gap: 10px; padding: 0 14px; border-radius: 6px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; font-size: 13.5px; font-weight: 500; cursor: pointer; outline: none;"
                                    @click="recurrenceTypeDropdownOpen = !recurrenceTypeDropdownOpen"
                                >
                                    <span>{{ recurrenceTypeName }}</span>
                                    <AppIcon name="chevronDown" size="14" style="color: #64748b;" />
                                </button>

                                <div
                                    v-if="recurrenceTypeDropdownOpen"
                                    class="custom-dropdown-menu"
                                    style="position: absolute; top: calc(100% + 4px); left: 0; right: 0; min-width: 180px; z-index: 1000; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.12); padding: 4px 0;"
                                >
                                    <div
                                        class="dropdown-item"
                                        :class="{ active: form.recurrence_type === 'daily' }"
                                        style="padding: 8px 14px; font-size: 13.5px; color: #0f172a; cursor: pointer; font-weight: 500; display: flex; align-items: center; justify-content: space-between;"
                                        @click="selectRecurrenceType('daily')"
                                    >
                                        <span>Hàng ngày</span>
                                        <AppIcon v-if="form.recurrence_type === 'daily'" name="check" size="14" style="color: #16a34a;" />
                                    </div>
                                    <div
                                        class="dropdown-item"
                                        :class="{ active: form.recurrence_type === 'weekly' }"
                                        style="padding: 8px 14px; font-size: 13.5px; color: #0f172a; cursor: pointer; font-weight: 500; display: flex; align-items: center; justify-content: space-between;"
                                        @click="selectRecurrenceType('weekly')"
                                    >
                                        <span>Chu kỳ 7 ngày</span>
                                        <AppIcon v-if="form.recurrence_type === 'weekly'" name="check" size="14" style="color: #16a34a;" />
                                    </div>
                                    <div
                                        class="dropdown-item"
                                        :class="{ active: form.recurrence_type === 'monthly' }"
                                        style="padding: 8px 14px; font-size: 13.5px; color: #0f172a; cursor: pointer; font-weight: 500; display: flex; align-items: center; justify-content: space-between;"
                                        @click="selectRecurrenceType('monthly')"
                                    >
                                        <span>Chu kỳ 30 ngày</span>
                                        <AppIcon v-if="form.recurrence_type === 'monthly'" name="check" size="14" style="color: #16a34a;" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <label>
                            <span>{{ recurringCountLabel }}</span>
                            <input
                                v-model.number="form.recurrence_count"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                :max="maxRecurringCycleCount"
                                step="1"
                                @blur="normalizeRecurringCount"
                            />
                        </label>
                    </div>
                </div>

                <div
                    class="recurring-planner-workspace"
                    :class="{
                        'has-date-planner': form.recurrence_type !== 'daily',
                    }"
                >
                    <div
                        v-if="form.recurrence_type !== 'daily'"
                        class="recurring-date-planner"
                    >
                    <div class="weekday-planner-head">
                        <div>
                            <strong>Lịch theo từng ngày</strong>
                            <span
                                >Chọn ngày để chỉnh sân và giờ ở bảng bên dưới.</span
                            >
                        </div>
                        <div class="weekday-planner-actions">
                            <button
                                type="button"
                                :disabled="
                                    !activeRecurringDateKeys.length ||
                                    recurringSelectedDates.length < 2
                                "
                                @click="applyActiveDateScheduleToSelected"
                            >
                                Áp dụng tất cả
                            </button>
                            <button
                                type="button"
                                :disabled="!activeRecurringDateKeys.length"
                                @click="clearActiveDateSchedule"
                            >
                                Xóa giờ
                            </button>
                        </div>
                    </div>
                    <div
                        class="recurring-date-list"
                        :class="{
                            dragging: recurringDateDrag.active,
                        }"
                        @pointerdown="startRecurringDateDrag"
                        @pointermove="moveRecurringDateDrag"
                        @pointerup="finishRecurringDateDrag"
                        @pointercancel="finishRecurringDateDrag"
                        @click.capture="preventRecurringDateDragClick"
                    >
                        <button
                            v-for="date in recurringSelectedDates"
                            :key="date"
                            type="button"
                            class="recurring-date-card"
                            :class="{
                                active: recurringActiveDate === date,
                                complete: (
                                    recurringDateRanges[date] || []
                                ).length,
                            }"
                            @click="selectRecurringDate(date)"
                        >
                            <span class="recurring-date-value">{{
                                formatDate(date)
                            }}</span>
                            <strong>{{
                                recurringDateTimeText(date)
                            }}</strong>
                            <small>{{
                                recurringDateCourtText(date)
                            }}</small>
                        </button>
                    </div>
                    </div>

                    <p class="recurring-helper">
                        {{ recurringHelperText }}
                    </p>

                    <section class="recurring-schedule-board">
                    <div class="section-title muted">
                        <h2>Chọn sân và khung giờ cố định</h2>
                    </div>

                    <p v-if="selectionError" class="selection-error">
                        {{ selectionError }}
                    </p>

                    <div
                        v-if="hasRecurringSelection"
                        class="active-weekday-note"
                    >
                        <strong
                            >Đang chỉnh
                            {{ formatDate(recurringActiveDate) }}</strong
                        >
                        <span>
                            Các ô trong bảng chỉ áp dụng cho ngày này.
                        </span>
                    </div>

                    <div class="period-row">
                        <div class="period-tabs" role="tablist">
                            <button
                                v-for="period in dynamicTimePeriods"
                                :key="period.key"
                                type="button"
                                :class="{ active: activeTimePeriod === period.key }"
                                @click="activeTimePeriod = period.key"
                            >
                                <strong>{{ period.label }}</strong>
                                <span>({{ period.range }})</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="!activePeriodSlots.length" class="state-card" style="margin-top: 10px;">
                        Ca này nằm ngoài giờ hoạt động của sân ({{ currentScheduleLabel || 'sân đóng cửa' }}). Không có khung giờ chơi.
                    </div>
                    <div v-else class="time-row-matrix-wrap" style="margin-top: 10px;">
                        <table class="time-row-matrix" role="grid" aria-label="Bảng chọn sân và khung giờ cố định">
                            <thead>
                                <tr>
                                    <th class="trm-corner" role="columnheader">KHUNG GIỜ</th>
                                    <th
                                        v-for="court in scheduleCourts"
                                        :key="court.id"
                                        class="trm-court-head"
                                        role="columnheader"
                                    >
                                        <strong>{{ court.name }}</strong>
                                        <span>{{ court.court_type?.name || "-" }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="slot in activePeriodSlots"
                                    :key="slot.start_time"
                                    role="row"
                                >
                                    <td class="trm-time-cell" role="rowheader">
                                        {{ formatTime(slot.start_time) }} – {{ formatTime(slot.end_time) }}
                                    </td>
                                    <td
                                        v-for="court in scheduleCourts"
                                        :key="`${court.id}-${slot.start_time}`"
                                        class="trm-slot-cell"
                                        role="gridcell"
                                    >
                                        <button
                                            type="button"
                                            class="trm-slot-btn"
                                            :class="slotButtonClass(court.id, slot)"
                                            :disabled="isSlotDisabled(court.id, slot)"
                                            :aria-pressed="isSlotSelected(court.id, slot)"
                                            :aria-label="slotActionTitle(court, slot)"
                                            :title="slotActionTitle(court, slot)"
                                            @click="toggleSlot(court, slot)"
                                        >
                                            <span v-if="isSlotSelected(court.id, slot)">Khung cố định</span>
                                            <span v-else-if="!isSlotDisabled(court.id, slot)" class="trm-empty-hint">+ Đặt sân</span>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    </section>
                </div>

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

                <div class="form-actions recurring-form-actions">
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
        </div>

        <template v-else-if="activeTab === 'bookingList'">
            <!-- Part 1: Top Hero Surface for Nav Tabs (matching OwnerVenueClusters.vue) -->
            <div class="cluster-hero-surface">
                <div class="hero-integrated-tabs">
                    <div
                        class="booking-list-mode-tabs"
                        role="tablist"
                        aria-label="Loại danh sách booking"
                    >
                        <button
                            type="button"
                            :class="{ active: bookingListMode === 'single' }"
                            @click="setBookingListMode('single')"
                        >
                            <span>Booking lẻ</span>
                        </button>
                        <button
                            type="button"
                            :class="{ active: bookingListMode === 'recurring' }"
                            @click="setBookingListMode('recurring')"
                        >
                            <span>Booking cố định</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Part 2: Separate Content Card below for Title & Table (matching OwnerVenueClusters.vue) -->
            <div class="recurring-list-panel profile-section-card">
            <form
                v-if="bookingListMode === 'single'"
                class="booking-list-filters"
                @submit.prevent="applyBookingListFilters"
            >
                <label class="booking-list-filter-search">
                    <span>Tìm booking hoặc khách</span>
                    <input
                        v-model.trim="bookingListFilters.q"
                        type="search"
                        placeholder="Mã booking, tên, SĐT..."
                    />
                </label>
                <label>
                    <span>Ngày chơi</span>
                    <input
                        v-model="bookingListFilters.booking_date"
                        type="date"
                    />
                </label>
                <label>
                    <span>Sân con</span>
                    <select v-model="bookingListFilters.venue_court_id">
                        <option value="">Tất cả sân</option>
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
                    <span>Nguồn booking</span>
                    <select v-model="bookingListFilters.source">
                        <option value="">Tất cả nguồn</option>
                        <option value="online">Đặt online</option>
                        <option value="counter">Tại quầy</option>
                    </select>
                </label>
                <label>
                    <span>Trạng thái</span>
                    <select v-model="bookingListFilters.status">
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending_approval">Chờ duyệt</option>
                        <option value="pending_payment">Chờ thanh toán</option>
                        <option value="confirmed">Đã xác nhận</option>
                        <option value="checked_in">Đã check-in</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="no_show">Không check-in</option>
                        <option value="cancelled">Đã hủy</option>
                        <option value="rejected">Từ chối</option>
                        <option value="expired">Hết hạn</option>
                    </select>
                </label>
                <div class="booking-list-filter-actions">
                    <button class="primary-btn" type="submit" :disabled="bookingListLoading">
                        <AppIcon name="search" size="15" />
                        <span>Lọc danh sách</span>
                    </button>
                    <button
                        class="secondary-btn"
                        type="button"
                        :disabled="bookingListLoading"
                        @click="resetBookingListFilters"
                    >
                        Xóa lọc
                    </button>
                </div>
            </form>

            <form
                v-else
                class="recurring-list-filters"
                @submit.prevent="applyBookingListFilters"
            >
                <label class="booking-list-filter-search">
                    <span>Tìm booking hoặc khách</span>
                    <input
                        v-model.trim="recurringGroupFilters.q"
                        type="search"
                        placeholder="Mã booking, tên, SĐT..."
                    />
                </label>
                <label>
                    <span>Sân con</span>
                    <select v-model="recurringGroupFilters.venue_court_id">
                        <option value="">Tất cả sân</option>
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
                    <select v-model="recurringGroupFilters.status">
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending_approval">Chờ duyệt</option>
                        <option value="pending_payment">Chờ thanh toán</option>
                        <option value="confirmed">Đã xác nhận</option>
                        <option value="checked_in">Đã check-in</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="no_show">Không check-in</option>
                        <option value="cancelled">Đã hủy</option>
                        <option value="expired">Hết hạn</option>
                        <option value="rejected">Từ chối</option>
                    </select>
                </label>
                <div class="booking-list-filter-actions">
                    <button class="primary-btn" type="submit" :disabled="recurringGroupsLoading">
                        <AppIcon name="search" size="15" />
                        <span>Lọc danh sách</span>
                    </button>
                    <button
                        class="secondary-btn"
                        type="button"
                        :disabled="recurringGroupsLoading"
                        @click="resetBookingListFilters"
                    >
                        Xóa lọc
                    </button>
                </div>
            </form>
            <template v-if="bookingListMode === 'single'">
                <div v-if="bookingListLoading" class="table-skeleton">
                    <div v-for="row in 4" :key="row" class="table-skeleton-row">
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
                            <tr
                                v-for="booking in bookingList"
                                :key="booking.id"
                            >
                                <td>
                                    <span class="group-code">{{
                                        booking.booking_code
                                    }}</span>
                                    <strong>{{
                                        bookingCustomerName(booking)
                                    }}</strong>
                                    <small>{{
                                        bookingCustomerPhone(booking)
                                    }}</small>
                                </td>
                                <td>
                                    <strong>{{
                                        bookingCourtText(booking)
                                    }}</strong>
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
                                        paymentOptionLabel(
                                            booking.payment_option,
                                        )
                                    }}</strong>
                                    <small>
                                        Đã thu
                                        {{
                                            formatCurrency(paidAmount(booking))
                                        }}
                                        /
                                        {{
                                            formatCurrency(booking.total_price)
                                        }}
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
                                                bookingOutstandingAmount(
                                                    booking,
                                                ) <= 0,
                                        }"
                                    >
                                        {{
                                            formatCurrency(
                                                bookingOutstandingAmount(
                                                    booking,
                                                ),
                                            )
                                        }}
                                    </strong>
                                </td>
                                <td class="action-col">
                                    <button
                                        type="button"
                                        class="secondary-btn compact"
                                        @click.stop="openBookingListDetail(booking)"
                                    >
                                        <AppIcon name="eye" size="15" />
                                        <span>Chi tiết</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <template v-else>
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
                                    <small>{{
                                        recurringGroupPhone(group)
                                    }}</small>
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
                                            :class="
                                                occurrenceChipClass(occurrence)
                                            "
                                            :title="
                                                occurrenceChipTitle(occurrence)
                                            "
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
                                                recurringGroupHiddenDateCount(
                                                    group,
                                                )
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
                                            (group.court_names || []).join(
                                                ", ",
                                            ) || "-"
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
                                                    group.outstanding_amount ||
                                                        0,
                                                ) <= 0,
                                        }"
                                    >
                                        {{
                                            formatCurrency(
                                                group.outstanding_amount,
                                            )
                                        }}
                                    </strong>
                                    <small>
                                        Tổng
                                        {{ formatCurrency(group.total_price) }}
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
            </template>
        </div>
    </template>
</div>

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
                            v-if="canApproveBooking(bookingListDetail)"
                            class="primary-btn"
                            type="button"
                            :disabled="bookingActionLoading"
                            @click="approveBookingFromList"
                        >
                            <AppIcon name="check" size="15" />
                            {{ bookingActionLoading ? "Đang xử lý..." : "Duyệt booking" }}
                        </button>
                        <button
                            v-if="bookingListDetail.status === 'pending_approval'"
                            class="secondary-btn danger"
                            type="button"
                            :disabled="bookingActionLoading"
                            @click="
                                openBookingListAction(
                                    bookingListDetail,
                                    'reject',
                                )
                            "
                        >
                            <AppIcon name="trash" size="15" />
                            Từ chối
                        </button>
                        <button
                            v-if="
                                bookingListDetail.status === 'confirmed' &&
                                bookingOutstandingAmount(bookingListDetail) <= 0
                            "
                            class="secondary-btn action-success"
                            type="button"
                            :disabled="bookingActionLoading"
                            @click="
                                openBookingListAction(
                                    bookingListDetail,
                                    'check_in',
                                )
                            "
                        >
                            <AppIcon name="clock" size="15" />
                            Check-in
                        </button>
                        <button
                            v-if="canCollectBookingPayment(bookingListDetail)"
                            class="secondary-btn action-cash"
                            type="button"
                            :disabled="bookingActionLoading"
                            @click="
                                openBookingListPayment(
                                    bookingListDetail,
                                    'cash',
                                )
                            "
                        >
                            <AppIcon name="banknote" size="15" />
                            Tiền mặt
                        </button>
                        <button
                            v-if="canCollectBookingPayment(bookingListDetail)"
                            class="secondary-btn action-transfer"
                            type="button"
                            :disabled="bookingActionLoading"
                            @click="
                                openBookingListPayment(
                                    bookingListDetail,
                                    'transfer',
                                )
                            "
                        >
                            <AppIcon name="qrCode" size="15" />
                            Chuyển khoản
                        </button>
                        <button
                            v-if="
                                bookingListDetail.status === 'checked_in' &&
                                !canCollectBookingPayment(bookingListDetail)
                            "
                            class="secondary-btn action-success"
                            type="button"
                            :disabled="bookingActionLoading"
                            @click="
                                openBookingListAction(
                                    bookingListDetail,
                                    'complete',
                                )
                            "
                        >
                            <AppIcon name="circleCheck" size="15" />
                            Hoàn thành
                        </button>
                        <button
                            v-if="
                                ['pending_payment', 'confirmed'].includes(
                                    bookingListDetail.status,
                                )
                            "
                            class="secondary-btn danger"
                            type="button"
                            :disabled="bookingActionLoading"
                            @click="
                                openBookingListAction(
                                    bookingListDetail,
                                    'cancel',
                                )
                            "
                        >
                            <AppIcon name="trash" size="15" />
                            Hủy booking
                        </button>
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
                            ['cancel', 'reject'].includes(
                                bookingActionConfirm.action,
                            )
                        "
                        class="field-stack confirm-reason-field"
                    >
                        <span>
                            {{
                                bookingActionConfirm.action === 'reject'
                                    ? 'Lý do từ chối'
                                    : 'Lý do hủy'
                            }}
                        </span>
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
                                    ['cancel', 'reject'].includes(
                                        bookingActionConfirm.action,
                                    ) &&
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

            <div
                v-if="counterQr && qrModalOpen"
                class="modal-backdrop qr-modal-backdrop"
            >
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
import AppTabs from "../../components/common/AppTabs.vue";
import { ownerBookingService } from "../../services/ownerBookings.js";
import { venueClusterService } from "../../services/venueClusters.js";
import { businessDateString, businessDateTime, businessMinutes, businessWeekDayIndex } from "../../utils/businessTime.js";

function toIsoDate(date) {
    return businessDateString(date);
}

function toWeekDayIndex(date) {
    return (businessWeekDayIndex(date) + 6) % 7;
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
    components: { AppIcon, MiniCalendar, AppTabs },
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
            courtTypeDropdownOpen: false,
            recurringCourtTypeDropdownOpen: false,
            recurrenceTypeDropdownOpen: false,
            scheduleSlots: [],
            scheduleCourts: [],
            scheduleSlotStatuses: [],
            scheduleBusyIntervals: [],
            selectedGridCourtId: "",
            selectedSlotKeys: [],
            recurringSelectedDates: [today],
            recurringActiveDate: today,
            recurringDateSlotKeys: {
                [today]: [],
            },
            recurringCalendarMode: "start",
            recurringDateDrag: {
                active: false,
                moved: false,
                pointerId: null,
                startX: 0,
                scrollLeft: 0,
            },
            resettingRecurringBuilder: false,
            timePeriods: SLOT_PERIODS,
            activeTimePeriod: "morning",
            scheduleLoading: false,
            scheduleError: "",
            selectionError: "",
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
                recurrence_count: 1,
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
            bookingListMode: "single",
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
        counterTabs() {
            return [
                { key: "counter", label: "Booking tại quầy" },
                { key: "recurring", label: "Đặt lịch cố định" },
            ];
        },
        isStaffRoute() {
            return this.$route.path.startsWith("/staff");
        },
        bookingListRouteName() {
            return this.isStaffRoute
                ? "staff-booking-list"
                : "owner-booking-list";
        },
        counterBookingRouteName() {
            return this.isStaffRoute
                ? "staff-counter-booking"
                : "owner-counter-booking";
        },
        counterBookingPath() {
            return this.isStaffRoute
                ? "/staff/counter-booking"
                : "/owner/counter-booking";
        },
        isBookingListRoute() {
            return (
                this.$route.name === "owner-booking-list" ||
                this.$route.name === "staff-booking-list"
            );
        },
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
            if (this.activeTab !== "recurring") return this.form.booking_date;
            return this.usesExplicitRecurringDates
                ? this.recurringActiveDate
                : this.form.recurring_start_date;
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
            return configured
                ? this.timeToMinutes(configured)
                : BOOKING_DAY_END;
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
                slotEnds.length
                    ? Math.max(...slotEnds)
                    : this.operatingEndMinutes,
                open + SLOT_STEP_MINUTES,
            );
            const configuredPeriods =
                this.selectedClusterDetail?.booking_config?.custom_time_periods;

            let periods = [];
            if (Array.isArray(configuredPeriods) && configuredPeriods.length > 0) {
                periods = configuredPeriods
                    .filter((p) => p.label && p.start_time && p.end_time)
                    .map((p, idx) => {
                        const start = this.timeToMinutes(p.start_time);
                        const end = this.timeToMinutes(p.end_time);
                        return {
                            key: `custom_${idx}`,
                            label: p.label,
                            start,
                            end,
                            range: `${this.minutesToTime(start)} - ${this.minutesToTime(end)}`,
                        };
                    })
                    .filter((period) => period.end > period.start);
            } else {
                const raw = [
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

                periods = raw
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
            }

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
            if (!this.usesExplicitRecurringDates) return this.selectedSlotKeys;
            return this.recurringDateSlotKeys[this.recurringActiveDate] || [];
        },
        usesExplicitRecurringDates() {
            return this.activeTab === "recurring";
        },
        recurringCycleDays() {
            return (
                {
                    daily: 1,
                    weekly: 7,
                    monthly: 30,
                }[this.form.recurrence_type] || 1
            );
        },
        recurringTemplateEndDate() {
            return this.addDaysToIso(
                this.form.recurring_start_date,
                this.recurringCycleDays - 1,
            );
        },
        recurringTemplateLabel() {
            if (this.form.recurrence_type === "daily") return "Ngày mẫu";
            return `Lịch mẫu ${this.recurringCycleDays} ngày`;
        },
        recurringCountLabel() {
            if (this.form.recurrence_type === "daily") {
                return "Số ngày áp dụng";
            }
            return `Số chu kỳ ${this.recurringCycleDays} ngày`;
        },
        recurringCalendarNote() {
            if (this.form.recurrence_type === "daily") {
                return `Lịch giờ của ${this.formatDate(this.form.recurring_start_date)} sẽ áp dụng cho ${this.form.recurrence_count || 1} ngày liên tiếp.`;
            }

            if (this.recurringCalendarMode === "start") {
                return `Bấm ngày muốn bắt đầu chu kỳ. Đang chọn ${this.formatDate(this.form.recurring_start_date)}.`;
            }

            return `${this.recurringSelectedDates.length} ngày có lịch trong mẫu ${this.formatDate(this.form.recurring_start_date)} - ${this.formatDate(this.recurringTemplateEndDate)}.`;
        },
        recurringHelperText() {
            if (this.form.recurrence_type === "daily") {
                return "Chọn sân và giờ một lần; hệ thống áp dụng lịch này cho số ngày liên tiếp đã nhập.";
            }
            return `Chỉ các ngày được chọn trong mẫu ${this.recurringCycleDays} ngày mới được nhân sang những chu kỳ tiếp theo.`;
        },
        maxRecurringCycleCount() {
            const sessionsPerCycle = Math.max(
                this.recurringSelectedDates.length,
                1,
            );
            const cycleLimit =
                {
                    daily: 130,
                    weekly: 52,
                    monthly: 12,
                }[this.form.recurrence_type] || 1;
            return Math.max(
                Math.min(Math.floor(130 / sessionsPerCycle), cycleLimit),
                1,
            );
        },
        normalizedRecurringCount() {
            const value = Number(this.form.recurrence_count);
            if (!Number.isFinite(value)) return 1;

            return Math.min(
                Math.max(Math.trunc(value), 1),
                this.maxRecurringCycleCount,
            );
        },
        hasValidRecurringCount() {
            const value = Number(this.form.recurrence_count);
            return (
                this.form.recurrence_count !== "" &&
                Number.isInteger(value) &&
                value >= 1 &&
                value <= this.maxRecurringCycleCount
            );
        },
        activeRecurringDateKeys() {
            return this.recurringDateSlotKeys[this.recurringActiveDate] || [];
        },
        recurringDateRanges() {
            return this.recurringSelectedDates.reduce((result, date) => {
                const ranges = this.slotRangesFromKeys(
                    this.recurringDateSlotKeys[date] || [],
                );
                if (ranges.length) result[date] = ranges;
                return result;
            }, {});
        },
        recurringConcreteScheduleGroups() {
            const count = this.normalizedRecurringCount;
            const groups = [];

            for (let cycle = 0; cycle < count; cycle += 1) {
                this.recurringSelectedDates.forEach((templateDate) => {
                    const ranges = this.recurringDateRanges[templateDate] || [];
                    groups.push({
                        templateDate,
                        date: this.addDaysToIso(
                            templateDate,
                            cycle * this.recurringCycleDays,
                        ),
                        ranges,
                    });
                });
            }

            return groups.sort((a, b) => a.date.localeCompare(b.date));
        },
        hasRecurringSelection() {
            if (!this.usesExplicitRecurringDates) {
                return Boolean(this.selectedSlotKeys.length);
            }
            return Object.keys(this.recurringDateRanges).length > 0;
        },
        recurringScheduleSummaries() {
            return this.recurringSelectedDates.map((date) => ({
                date,
                label: this.formatDate(date),
                ranges: this.recurringDateRanges[date] || [],
            }));
        },
        recurringPerDateTotals() {
            return this.recurringSelectedDates.reduce((result, date) => {
                result[date] = this.slotEntriesFromKeys(
                    this.recurringDateSlotKeys[date] || [],
                ).reduce((total, entry) => {
                    const status = this.slotStatus(entry.courtId, entry.slot);
                    return total + Number(status?.price || 0);
                }, 0);
                return result;
            }, {});
        },
        recurringSelectedCourtText() {
            const names = new Set();
            Object.values(this.recurringDateRanges).forEach((ranges) => {
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
            const filled = this.recurringScheduleSummaries.filter(
                (item) => item.ranges.length,
            );
            if (!filled.length) return "Chưa chọn";
            if (filled.length === 1) {
                return `${filled[0].label}: ${this.rangeListText(filled[0].ranges)}`;
            }

            return `${filled.length}/${this.recurringSelectedDates.length} ngày đã có khung giờ`;
        },
        recurringBaseTotal() {
            const templateTotal = Object.values(
                this.recurringPerDateTotals,
            ).reduce((total, amount) => total + Number(amount || 0), 0);
            return templateTotal * this.normalizedRecurringCount;
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
                this.usesExplicitRecurringDates
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
        selectedCourtTypeName() {
            if (!this.selectedCourtTypeId) return "Tất cả loại sân";
            const found = (this.courtTypeOptions || []).find(
                (t) => String(t.id) === String(this.selectedCourtTypeId),
            );
            return found ? found.name : "Tất cả loại sân";
        },
        recurrenceTypeName() {
            return (
                {
                    daily: "Hàng ngày",
                    weekly: "Chu kỳ 7 ngày",
                    monthly: "Chu kỳ 30 ngày",
                }[this.form.recurrence_type] || "Chu kỳ 7 ngày"
            );
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
            return this.recurringBaseTotal;
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
            if (this.form.recurrence_type === "daily") {
                return `${this.normalizedRecurringCount} ngày liên tiếp`;
            }

            return `${this.normalizedRecurringCount} chu kỳ ${this.recurringCycleDays} ngày · ${this.recurringSelectedDates.length} ngày có lịch mỗi chu kỳ`;
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
            const booking =
                this.bookingActionConfirm?.booking || this.selectedBusyBooking;

            if (!booking) return [];

            const outstanding = this.bookingActionConfirm?.booking
                ? this.bookingOutstandingAmount(booking)
                : this.selectedBookingOutstanding;

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
                    this.formatCurrency(outstanding),
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
            return this.recurringConcreteScheduleGroups.map(
                (group) => group.date,
            );
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
            const explicitDatesReady =
                !this.usesExplicitRecurringDates ||
                (this.recurringSelectedDates.length > 0 &&
                    this.recurringSelectedDates.every(
                        (date) => (this.recurringDateRanges[date] || []).length,
                    ));

            return (
                this.hasRecurringSelection &&
                explicitDatesReady &&
                this.hasValidRecurringCount &&
                !this.walkInNameError &&
                !this.walkInPhoneError &&
                this.form.payment_option &&
                this.recurringPreview.length > 0 &&
                !this.submitting
            );
        },
    },
    watch: {
        dynamicTimePeriods: {
            handler() {
                this.ensureActiveTimePeriod();
            },
            immediate: true,
        },
        "form.recurring_start_date"(newDate, oldDate) {
            if (
                this.activeTab === "recurring" &&
                !this.resettingRecurringBuilder
            ) {
                void this.rebaseRecurringTemplate(oldDate, newDate);
            }
        },
        "form.recurring_end_date"() {
            if (
                this.activeTab === "recurring" &&
                !this.usesExplicitRecurringDates
            ) {
                this.syncRecurringEndDate();
                this.queueRecurringPreview();
            }
        },
        "form.recurrence_type"() {
            if (this.activeTab === "recurring") {
                this.recurringCalendarMode = "start";
                void this.syncRecurringTemplateCycle();
            }
        },
        "form.recurrence_count"(value) {
            if (this.resettingRecurringBuilder) return;

            if (value === "" || value === null || value === undefined) {
                this.form.recurring_end_date =
                    this.form.recurring_start_date;
                this.recurringPreviewResult = null;
                this.recurringPreviewError = "";
                this.clearVoucherSelection();
                return;
            }

            this.form.recurring_end_date =
                this.recurringPreview.at(-1) || this.form.recurring_start_date;
            this.clearVoucherSelection();
            this.queueRecurringPreview();
        },
        selectedSlotKeys: {
            deep: true,
            handler() {
                this.queueRecurringPreview();
            },
        },
        recurringDateSlotKeys: {
            deep: true,
            handler() {
                if (this.resettingRecurringBuilder) return;
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
        counterDrawerOpen(isOpen) {
            if (!isOpen) return;

            this.$nextTick(() => {
                if (this.$refs.counterDrawer) {
                    this.$refs.counterDrawer.scrollTop = 0;
                }
            });
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
            if (this.$route.name === "owner-booking-list") {
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

            if (this.activeTab === "bookingList") {
                this.activeTab = "counter";
            }
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
            const explicitDatesComplete =
                !this.usesExplicitRecurringDates ||
                this.recurringSelectedDates.every(
                    (date) => (this.recurringDateRanges[date] || []).length,
                );
            if (
                this.activeTab !== "recurring" ||
                !this.hasCounterSelection ||
                !this.recurringPreview.length ||
                !explicitDatesComplete
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

            if (tab === "bookingList") {
                await this.loadCurrentBookingList();
                return;
            }

            await this.loadSchedule();
        },
        async setBookingListMode(mode) {
            if (this.bookingListMode === mode) return;

            this.bookingListMode = mode;
            await this.loadCurrentBookingList();
        },
        async loadCurrentBookingList() {
            if (this.bookingListMode === "recurring") {
                await this.loadRecurringGroups();
                return;
            }

            await this.loadBookingList();
        },
        async applyBookingListFilters() {
            await this.loadCurrentBookingList();
        },
        async resetBookingListFilters() {
            this.bookingListFilters = {
                venue_court_id: "",
                booking_date: "",
                source: "",
                status: "",
                q: "",
            };
            this.recurringGroupFilters = {
                venue_court_id: "",
                status: "",
                q: "",
            };

            await this.loadCurrentBookingList();
        },
        async refreshActiveTab() {
            if (this.activeTab === "bookingList") {
                await this.loadCurrentBookingList();
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
        async selectCourtType(typeId) {
            this.selectedCourtTypeId = typeId;
            this.courtTypeDropdownOpen = false;
            this.recurringCourtTypeDropdownOpen = false;
            await this.loadSchedule();
        },
        selectRecurrenceType(typeVal) {
            this.form.recurrence_type = typeVal;
            this.recurrenceTypeDropdownOpen = false;
        },
        hasRouteBookingFocus() {
            const focus = this.routeBookingFocusQuery();
            return Boolean(focus.id || focus.code);
        },
        async shiftCounterDate(days) {
            const current =
                this.parseDate(this.form.booking_date) || new Date();
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
        async handleNativeDateChange(val) {
            if (!val) return;
            this.form.booking_date = val;
            this.form.booking_end_date = val;
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
            if (
                this.counterDateCount > 1 &&
                this.form.collection_mode === "transfer"
            ) {
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
                const response = await venueClusterService.getClusters({ compact: 1 });
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
                const requests = [this.loadClusterDetail(), this.loadCourts()];

                if (this.activeTab === "bookingList") {
                    this.bookingListFilters.venue_court_id = "";
                    this.recurringGroupFilters.venue_court_id = "";
                    requests.push(this.loadCurrentBookingList());
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
                    booking_date: this.activeScheduleDate,
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
                const firstWithSlots = this.dynamicTimePeriods.find((period) => {
                    if (period.key === "all") return false;
                    return this.scheduleSlots.some((slot) => {
                        const start = this.timeToMinutes(slot.start_time);
                        return start >= period.start && start < period.end;
                    });
                });
                this.activeTimePeriod =
                    firstWithSlots?.key || this.dynamicTimePeriods[0]?.key || "all";
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
        openBookingListAction(booking, action) {
            if (!booking?.id) return;

            this.openBookingActionConfirm("status", { action, booking });
        },
        openBookingListPayment(booking, method) {
            if (!booking?.id) return;

            this.openBookingActionConfirm(
                method === "transfer" ? "transfer" : "collect",
                {
                    booking,
                    ...(method === "transfer" ? {} : { method: "cash" }),
                },
            );
        },
        canCollectBookingPayment(booking) {
            if (!booking || booking.status === "pending_approval") {
                return false;
            }

            const paymentOption =
                booking.effective_payment_option || booking.payment_option;

            return (
                !(booking.status === "pending_payment" &&
                    paymentOption === "no_prepay") &&
                ![
                    "cancelled",
                    "expired",
                    "rejected",
                    "no_show",
                    "completed",
                ].includes(booking.status) &&
                this.bookingOutstandingAmount(booking) > 0
            );
        },
        canApproveBooking(booking) {
            if (!booking) return false;
            if (booking.status === 'pending_approval') {
                const deadline = booking.approval_deadline_at
                    || (booking.slot_locks || [])
                        .filter((lock) => lock.lock_type === 'auto')
                        .sort((a, b) => new Date(a.expires_at) - new Date(b.expires_at))[0]?.expires_at;
                return !deadline || new Date(deadline).getTime() > Date.now();
            }
            if (booking.status !== 'pending_payment') return false;
            if (booking.payment_option === 'no_prepay') return true;

            return booking.payment_option === 'deposit'
                && (Number(booking.required_payment_amount || 0) <= 0
                    || this.paidAmount(booking) + 0.01 >= Number(booking.required_payment_amount || 0));
        },
        async approveBookingFromList() {
            const booking = this.bookingListDetail;
            if (!this.canApproveBooking(booking) || this.bookingActionLoading) return;

            this.bookingActionLoading = true;
            this.error = '';
            this.notice = '';
            try {
                const response = await ownerBookingService.updateStatus(booking.id, {
                    action: 'confirm',
                });
                this.bookingListDetail = response.data || response;
                this.notice = 'Đã duyệt booking.';
                await Promise.all([this.loadBookingList(), this.loadSchedule()]);
                this.bookingListDetail = null;
            } catch (error) {
                this.error = error.message || 'Không thể xác nhận booking.';
            } finally {
                this.bookingActionLoading = false;
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

            this.slotEntriesFromKeys(keys).forEach(
                ({ courtId, court, slot }) => {
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
                },
            );

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
        addDaysToIso(date, days = 0) {
            const parsed = this.parseDate(date);
            if (!parsed) return "";
            const next = new Date(parsed);
            next.setDate(next.getDate() + Number(days || 0));
            return this.formatIsoDate(next);
        },
        daysBetweenIso(startDate, endDate) {
            const start = this.parseDate(startDate);
            const end = this.parseDate(endDate);
            if (!start || !end) return 0;
            return Math.round((end - start) / 86400000);
        },
        updateRecurringStartDate(date) {
            if (!date || date < this.today) return;
            this.form.recurring_start_date = date;
        },
        activateRecurringSchedulePicker() {
            if (this.form.recurrence_type !== "daily") {
                this.recurringCalendarMode = "schedule";
            }
        },
        normalizeRecurringCount() {
            this.form.recurrence_count = this.normalizedRecurringCount;
        },
        async rebaseRecurringTemplate(oldStart, newStart) {
            if (!newStart || newStart < this.today) return;

            const previousActiveDate = this.recurringActiveDate;
            const sourceStart = oldStart || newStart;
            const nextSelected = [];
            const nextSlotKeys = {};

            this.recurringSelectedDates.forEach((date) => {
                const offset = this.daysBetweenIso(sourceStart, date);
                if (offset < 0 || offset >= this.recurringCycleDays) return;
                const rebasedDate = this.addDaysToIso(newStart, offset);
                nextSelected.push(rebasedDate);
                nextSlotKeys[rebasedDate] =
                    this.recurringDateSlotKeys[date] || [];
            });

            if (this.form.recurrence_type === "daily" || !nextSelected.length) {
                nextSelected.splice(0, nextSelected.length, newStart);
                nextSlotKeys[newStart] =
                    this.recurringDateSlotKeys[previousActiveDate] ||
                    nextSlotKeys[newStart] ||
                    [];
            }

            this.recurringSelectedDates = [...new Set(nextSelected)].sort();
            this.recurringDateSlotKeys = nextSlotKeys;
            const previousOffset = this.daysBetweenIso(
                sourceStart,
                previousActiveDate,
            );
            const rebasedActive = this.addDaysToIso(newStart, previousOffset);
            this.recurringActiveDate = this.recurringSelectedDates.includes(
                rebasedActive,
            )
                ? rebasedActive
                : this.recurringSelectedDates[0];
            this.form.recurring_end_date =
                this.recurringPreview.at(-1) || newStart;
            this.clearVoucherSelection();
            this.queueRecurringPreview();

            if (this.activeTab === "recurring") await this.loadSchedule();
        },
        async syncRecurringTemplateCycle() {
            const start = this.form.recurring_start_date || this.today;
            const end = this.recurringTemplateEndDate;
            const activeSchedule = [...this.activeRecurringDateKeys];
            let selected = this.recurringSelectedDates.filter(
                (date) => date >= start && date <= end,
            );

            if (this.form.recurrence_type === "daily" || !selected.length) {
                selected = [start];
            }

            const nextSlotKeys = {};
            selected.forEach((date) => {
                nextSlotKeys[date] =
                    this.recurringDateSlotKeys[date] ||
                    (date === start ? activeSchedule : []);
            });
            this.recurringSelectedDates = selected;
            this.recurringDateSlotKeys = nextSlotKeys;
            this.recurringActiveDate = selected.includes(
                this.recurringActiveDate,
            )
                ? this.recurringActiveDate
                : selected[0];
            if (this.form.recurrence_count !== "") {
                this.form.recurrence_count = this.normalizedRecurringCount;
            }
            this.form.recurring_end_date =
                this.recurringPreview.at(-1) || start;
            this.clearVoucherSelection();
            this.queueRecurringPreview();

            if (this.activeTab === "recurring") await this.loadSchedule();
        },
        async updateRecurringSelectedDates(dates = []) {
            const start = this.form.recurring_start_date;
            const end = this.recurringTemplateEndDate;
            let selected = [...new Set(dates)]
                .filter((date) => /^\d{4}-\d{2}-\d{2}$/.test(date))
                .filter((date) => date >= start && date <= end)
                .sort()
                .slice(0, 130);
            if (this.form.recurrence_type === "daily") selected = [start];
            const previousActiveDate = this.recurringActiveDate;
            const nextSlotKeys = {};

            selected.forEach((date) => {
                nextSlotKeys[date] = this.recurringDateSlotKeys[date] || [];
            });

            this.recurringSelectedDates = selected;
            this.recurringDateSlotKeys = nextSlotKeys;
            this.recurringActiveDate = selected.includes(previousActiveDate)
                ? previousActiveDate
                : selected[0] || "";

            if (this.form.recurrence_count !== "") {
                this.form.recurrence_count = this.normalizedRecurringCount;
            }
            this.form.recurring_end_date =
                this.recurringPreview.at(-1) || start;

            this.clearVoucherSelection();
            this.queueRecurringPreview();

            if (
                this.activeTab === "recurring" &&
                this.recurringActiveDate &&
                this.recurringActiveDate !== previousActiveDate
            ) {
                await this.loadSchedule();
            }
        },
        async handleRecurringCalendarSelect(date) {
            if (this.recurringCalendarMode === "start") {
                this.activateRecurringSchedulePicker();
                return;
            }

            if (
                this.form.recurrence_type === "daily" ||
                !this.recurringSelectedDates.includes(date)
            ) {
                return;
            }

            await this.selectRecurringDate(date);
        },
        async selectRecurringDate(date) {
            if (!date || date === this.recurringActiveDate) return;
            this.recurringActiveDate = date;
            await this.loadSchedule();
        },
        startRecurringDateDrag(event) {
            if (event.pointerType === "mouse" && event.button !== 0) return;

            const scroller = event.currentTarget;
            this.recurringDateDrag.active = true;
            this.recurringDateDrag.moved = false;
            this.recurringDateDrag.pointerId = event.pointerId;
            this.recurringDateDrag.startX = event.clientX;
            this.recurringDateDrag.scrollLeft = scroller.scrollLeft;
            scroller.setPointerCapture?.(event.pointerId);
        },
        moveRecurringDateDrag(event) {
            if (
                !this.recurringDateDrag.active ||
                this.recurringDateDrag.pointerId !== event.pointerId
            ) {
                return;
            }

            const delta = event.clientX - this.recurringDateDrag.startX;
            if (!this.recurringDateDrag.moved && Math.abs(delta) < 5) return;

            this.recurringDateDrag.moved = true;
            event.preventDefault();
            event.currentTarget.scrollLeft =
                this.recurringDateDrag.scrollLeft - delta;
        },
        finishRecurringDateDrag(event) {
            if (
                !this.recurringDateDrag.active ||
                this.recurringDateDrag.pointerId !== event.pointerId
            ) {
                return;
            }

            const scroller = event.currentTarget;
            if (scroller.hasPointerCapture?.(event.pointerId)) {
                scroller.releasePointerCapture(event.pointerId);
            }

            this.recurringDateDrag.active = false;
            this.recurringDateDrag.pointerId = null;
            window.setTimeout(() => {
                this.recurringDateDrag.moved = false;
            }, 0);
        },
        preventRecurringDateDragClick(event) {
            if (!this.recurringDateDrag.moved) return;

            event.preventDefault();
            event.stopPropagation();
        },
        applyActiveDateScheduleToSelected() {
            const source = this.activeRecurringDateKeys;
            if (!source.length) return;

            const next = {};
            this.recurringSelectedDates.forEach((date) => {
                next[date] = [...source];
            });
            this.recurringDateSlotKeys = next;
            this.queueRecurringPreview();
        },
        clearActiveDateSchedule() {
            if (!this.recurringActiveDate) return;
            this.recurringDateSlotKeys = {
                ...this.recurringDateSlotKeys,
                [this.recurringActiveDate]: [],
            };
            this.queueRecurringPreview();
        },
        recurringDateTimeText(date) {
            const ranges = this.recurringDateRanges[date] || [];
            if (!ranges.length) return "Chưa chọn giờ";
            return ranges
                .map(
                    (range) =>
                        `${this.formatTime(range.start_time)} - ${this.formatTime(range.end_time)}`,
                )
                .join(", ");
        },
        recurringDateCourtText(date) {
            const ranges = this.recurringDateRanges[date] || [];
            const names = [
                ...new Set(ranges.map((range) => range.court?.name)),
            ].filter(Boolean);
            if (!names.length) return "Chưa chọn sân";
            if (names.length <= 2) return names.join(", ");
            return `${names.length} sân`;
        },
        isSlotDisabled(courtId, slot) {
            if (!courtId || !slot) return true;

            if (
                !this.isSlotBusy(courtId, slot) &&
                this.isScheduleUnavailable(courtId, slot)
            ) {
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
            return this.activeSelectionKeys.includes(
                this.slotKey(courtId, slot),
            );
        },
        slotButtonClass(courtId, slot) {
            const selected = this.isSlotSelected(courtId, slot);
            const busy = this.isSlotBusy(courtId, slot);
            const scheduleUnavailable =
                !busy && this.isScheduleUnavailable(courtId, slot);
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

            const nowMinutes = businessMinutes();

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
            if (!this.usesExplicitRecurringDates) {
                this.selectionError = "";
                this.selectedSlotKeys = this.selectedSlotKeys.includes(
                    clickedKey,
                )
                    ? this.selectedSlotKeys.filter(
                          (item) => item !== clickedKey,
                      )
                    : [...this.selectedSlotKeys, clickedKey];
                this.syncCounterRangeFields();
                this.loadEligibleVouchers();
                return;
            }

            const date = this.recurringActiveDate;
            if (!date) {
                this.selectionError = "Vui lòng chọn ngày áp dụng trước.";
                return;
            }
            const currentKeys = this.recurringDateSlotKeys[date] || [];
            this.selectionError = "";
            this.recurringDateSlotKeys = {
                ...this.recurringDateSlotKeys,
                [date]: currentKeys.includes(clickedKey)
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
            const usesDateRanges =
                this.usesExplicitRecurringDates && this.hasRecurringSelection;
            const dateRangeGroups = usesDateRanges
                ? this.recurringConcreteScheduleGroups.filter(
                      (item) => item.ranges.length,
                  )
                : [];
            const selectedRanges = usesDateRanges
                ? dateRangeGroups[0]?.ranges || []
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
                recurring_start_date:
                    this.recurringPreview[0] || this.form.recurring_start_date,
                recurring_end_date:
                    this.recurringPreview.at(-1) ||
                    this.form.recurring_start_date,
                recurrence_type: this.form.recurrence_type,
                recurrence_interval: usesDateRanges
                    ? 1
                    : this.form.recurrence_interval,
                venue_cluster_id: this.selectedClusterId,
                ...extra,
            };

            if (usesDateRanges) {
                payload.recurring_dates = [...this.recurringPreview];
                payload.date_time_ranges = dateRangeGroups.map(
                    ({ date, ranges }) => ({
                        date,
                        time_ranges: ranges.map((range) => ({
                            venue_court_id: range.venue_court_id,
                            start_time: this.withSeconds(
                                this.formatTime(range.start_time),
                            ),
                            end_time: this.withSeconds(
                                this.formatTime(range.end_time),
                            ),
                        })),
                    }),
                );
            }

            return payload;
        },
        async resetRecurringBuilder() {
            const resetDate = this.today;
            this.resettingRecurringBuilder = true;
            clearTimeout(this.recurringPreviewTimer);
            this.recurringPreviewRequestId += 1;

            this.form.walk_in_name = "";
            this.form.walk_in_phone = "";
            this.form.venue_court_id = "";
            this.form.recurring_start_date = resetDate;
            this.form.recurring_end_date = resetDate;
            this.form.recurrence_count = 1;
            this.form.start_time = "08:00";
            this.form.end_time = "09:00";
            this.form.payment_option = "full_payment";
            this.form.payment_method = "cash";
            this.form.is_paid = true;

            this.recurringSelectedDates = [resetDate];
            this.recurringActiveDate = resetDate;
            this.recurringDateSlotKeys = { [resetDate]: [] };
            this.recurringCalendarMode = "start";
            this.recurringDateDrag.active = false;
            this.recurringDateDrag.moved = false;
            this.recurringDateDrag.pointerId = null;
            this.activeTimePeriod = "morning";
            this.selectedSlotKeys = [];
            this.selectedGridCourtId = "";
            this.selectionError = "";
            this.contactTouched = { name: false, phone: false };
            this.recurringPreviewLoading = false;
            this.recurringPreviewResult = null;
            this.recurringPreviewError = "";
            this.recurringConflict = null;
            this.conflictSelections = {};
            this.clearVoucherSelection();

            await this.$nextTick();
            this.resettingRecurringBuilder = false;
            this.syncPaymentOption();
        },
        async createRecurringWithPayload(payload) {
            const response = await ownerBookingService.createRecurring(payload);
            const skipped = Number(response.data?.skipped_count || 0);
            const switched = Number(response.data?.switched_count || 0);
            const createdCount = Number(
                response.data?.created_count ?? this.recurringPreview.length,
            );
            const extras = [
                skipped ? `bỏ ${skipped} buổi trùng` : "",
                switched ? `đổi sân ${switched} buổi` : "",
            ].filter(Boolean);

            this.notice = `Đã tạo ${createdCount} buổi cố định${extras.length ? `, ${extras.join(", ")}` : ""}.`;
            await this.resetRecurringBuilder();
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
                        this.conflictSelections[conflict.key || conflict.date];
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
            if (start >= close) start = close - SLOT_STEP_MINUTES;
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

                const paymentCompleted =
                    paidAmount + 0.01 >= Number(booking.total_price || 0);
                const paymentCancelled = [
                    "cancelled",
                    "expired",
                    "rejected",
                    "no_show",
                ].includes(booking.status);

                if (paymentCompleted || paymentCancelled) {
                    this.counterQr = null;
                    this.qrModalOpen = false;
                    this.counterQrBookingId = "";
                    this.clearCounterQrPolling();
                    this.bookingListDetail = null;
                    await Promise.all([
                        this.loadBookingList(),
                        this.loadSchedule(),
                    ]);
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
            if (booking?.paid_amount !== undefined && booking?.paid_amount !== null) {
                return Number(booking.paid_amount) || 0;
            }

            return (booking?.payments || [])
                .filter((payment) => payment.status === "paid")
                .reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
        },
        bookingOutstandingAmount(booking) {
            if (
                booking?.outstanding_amount !== undefined &&
                booking?.outstanding_amount !== null
            ) {
                return Math.max(Number(booking.outstanding_amount) || 0, 0);
            }

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
                    no_show: "Không check-in",
                    cancelled: "Đã hủy",
                    rejected: "Từ chối",
                    expired: "Hết hạn",
                }[status] ||
                status ||
                "-"
            );
        },
        openBookingActionConfirm(kind, payload = {}) {
            const booking = payload.booking || this.selectedBusyBooking;
            if (!booking) return;

            const amount = this.formatCurrency(
                this.bookingOutstandingAmount(booking),
            );
            const configs = {
                status: {
                    confirm: {
                        title: "Xác nhận booking",
                        message:
                            "Booking sẽ được chuyển sang trạng thái đã xác nhận.",
                        confirmLabel: "Xác nhận",
                    },
                    reject: {
                        title: "Từ chối booking",
                        message:
                            "Booking sẽ bị từ chối và lý do sẽ được gửi cho khách hàng.",
                        confirmLabel: "Từ chối booking",
                        variant: "danger",
                    },
                    check_in: {
                        title: "Check-in khách",
                        message:
                            "Xác nhận khách đã đến và bắt đầu lượt chơi.",
                        confirmLabel: "Xác nhận check-in",
                    },
                    complete: {
                        title: "Hoàn thành lượt chơi",
                        message:
                            "Xác nhận lượt chơi đã kết thúc và đóng booking.",
                        confirmLabel: "Hoàn thành",
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
            const targetBooking =
                this.bookingActionConfirm?.booking || this.selectedBusyBooking;
            if (!targetBooking?.id || this.bookingActionLoading) {
                return;
            }

            const isBookingListAction = Boolean(
                this.bookingActionConfirm?.booking,
            );

            this.bookingActionLoading = true;
            this.error = "";
            this.notice = "";

            try {
                const payload = { action };
                if (["cancel", "reject"].includes(action)) {
                    const reason = (
                        this.bookingActionConfirm?.reason || ""
                    ).trim();
                    if (!reason) {
                        this.error = ["cancel", "reject"].includes(action)
                            ? "Vui lòng nhập lý do thao tác với booking."
                            : "Vui lòng nhập lý do hủy booking.";
                        return;
                    }
                    payload.status_reason = reason;
                }
                const response = await ownerBookingService.updateStatus(
                    targetBooking.id,
                    payload,
                );
                const updatedBooking = response.data || response;
                if (isBookingListAction) {
                    this.bookingListDetail = updatedBooking;
                } else {
                    this.selectedBusyBooking = updatedBooking;
                }
                this.notice = "Đã cập nhật trạng thái booking.";
                this.bookingActionConfirm = null;
                this.counterDrawerOpen = false;
                await Promise.all([
                    this.loadBookingList(),
                    this.loadSchedule(),
                ]);
                if (isBookingListAction) {
                    this.bookingListDetail = null;
                }
            } catch (error) {
                this.error = error.message || "Không thể cập nhật booking.";
            } finally {
                this.bookingActionLoading = false;
            }
        },
        async collectSelectedBooking(method) {
            const targetBooking =
                this.bookingActionConfirm?.booking || this.selectedBusyBooking;
            if (!targetBooking?.id || this.bookingActionLoading) {
                return;
            }

            const isBookingListAction = Boolean(
                this.bookingActionConfirm?.booking,
            );

            this.bookingActionLoading = true;
            this.error = "";
            this.notice = "";

            try {
                const response = await ownerBookingService.collectPayment(
                    targetBooking.id,
                    { payment_method: method },
                );
                const updatedBooking = response.data || response;
                if (isBookingListAction) {
                    this.bookingListDetail = updatedBooking;
                } else {
                    this.selectedBusyBooking = updatedBooking;
                }
                this.bookingActionConfirm = null;
                this.counterDrawerOpen = false;
                this.notice = "Đã ghi nhận thanh toán thành công.";
                await Promise.all([
                    this.loadBookingList(),
                    this.loadSchedule(),
                ]);
                if (isBookingListAction) {
                    this.bookingListDetail = null;
                }
            } catch (error) {
                this.error = error.message || "Không thể ghi nhận thu tiền.";
            } finally {
                this.bookingActionLoading = false;
            }
        },
        async openSelectedBookingPaymentQr() {
            const targetBooking =
                this.bookingActionConfirm?.booking || this.selectedBusyBooking;
            if (!targetBooking?.id || this.bookingActionLoading) {
                return;
            }

            const isBookingListAction = Boolean(
                this.bookingActionConfirm?.booking,
            );

            this.bookingActionLoading = true;
            this.error = "";
            this.notice = "";

            try {
                const response = await ownerBookingService.collectPayment(
                    targetBooking.id,
                    { payment_method: "sepay" },
                );
                this.counterQr = response.payment_qr || null;
                this.counterDrawerOpen = false;
                this.qrModalOpen = Boolean(this.counterQr);
                this.counterQrBookingId =
                    response.data?.id || targetBooking.id;
                const updatedBooking = response.data || targetBooking;
                if (isBookingListAction) {
                    this.bookingListDetail = updatedBooking;
                } else {
                    this.selectedBusyBooking = updatedBooking;
                }
                this.bookingActionConfirm = null;
                this.startCounterQrPolling();
                await Promise.all([
                    this.loadBookingList(),
                    this.loadSchedule(),
                ]);
                if (isBookingListAction) {
                    this.bookingListDetail = null;
                }
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
            const date = String(occurrence?.booking_date || "").slice(0, 10);
            const time = this.formatTime(occurrence?.[field]);
            if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(time)) return null;

            const minutes = this.timeToMinutes(time);
            if (!Number.isFinite(minutes)) return null;

            const result = businessDateTime(date, time);
            return Number.isNaN(result.getTime()) ? null : result;
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
            if (group?.recurring_dates?.length) {
                return `${group.recurring_dates.length} ngày được chọn cụ thể`;
            }

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
                : (() => {
                    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw);
                    if (!match) return new Date(NaN);
                    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 12);
                })();
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
    padding: 10px;
    box-sizing: border-box;
}

.counter-board {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.recurring-panel {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 18px;
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
    margin-top: 0px;
    color: var(--admin-text, #16231a);
    font-size: 18px;
    font-weight: 400;
}

.list-toolbar p {
    margin: 4px 0 0;
    color: #607267;
    font-size: 13px;
}

.compact-list-toolbar {
    padding-top: 10px;
    border-top: 1px solid #e4eee4;
}

.booking-list-mode-tabs {
    display: inline-flex;
    width: fit-content;
    gap: 4px;
    padding: 4px;
    border: 1px solid #d5e4d6;
    border-radius: 8px;
    background: #f6fbf7;
}

.booking-list-mode-tabs button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 36px;
    border: 0;
    border-radius: 6px;
    padding: 0 14px;
    background: transparent;
    color: #5d6d63;
    font-size: 13px;
    font-weight: 400;
    cursor: pointer;
}

.booking-list-mode-tabs button.active {
    background: #16a34a;
    color: #fff;
    box-shadow: 0 8px 20px rgba(22, 163, 74, 0.16);
}

.recurring-list-filters {
    display: grid;
    grid-template-columns:
        minmax(180px, 1fr) minmax(150px, 0.8fr) minmax(150px, 0.8fr)
        minmax(220px, 1.2fr) auto;
    gap: 10px;
    align-items: end;
}


.schedule-skeleton,
.table-skeleton {
    display: grid;
    gap: 14px;
    padding: 16px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.skeleton-summary,
.skeleton-tabs {
    display: grid;
    gap: 10px;
}

.skeleton-summary {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.skeleton-tabs {
    grid-template-columns: repeat(3, minmax(120px, 160px));
}

.skeleton-summary span,
.skeleton-tabs span,
.skeleton-matrix span,
.table-skeleton-row span {
    display: block;
    overflow: hidden;
    position: relative;
    border-radius: 999px;
    background: #edf2f0;
}

.skeleton-summary span::after,
.skeleton-tabs span::after,
.skeleton-matrix span::after,
.table-skeleton-row span::after {
    content: "";
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.72) 48%,
        transparent 100%
    );
    animation: skeleton-shimmer 1.35s ease-in-out infinite;
}

.skeleton-summary span {
    height: 64px;
    border-radius: 12px;
    background: #f0f4f1;
}

.skeleton-tabs span {
    height: 38px;
    background: #edf2f0;
}

.skeleton-matrix {
    display: grid;
    grid-template-columns: 120px repeat(2, minmax(0, 1fr));
    gap: 14px 18px;
    overflow: hidden;
    padding: 16px;
    border-radius: 10px;
    background: #f8faf9;
}

.skeleton-matrix span {
    height: 22px;
    align-self: center;
}

.skeleton-matrix span:nth-child(3n + 1) {
    height: 36px;
    border-radius: 10px;
}

.skeleton-matrix span:nth-child(3n + 2) {
    width: 78%;
}

.skeleton-matrix span:nth-child(3n) {
    width: 54%;
}

.table-skeleton-row {
    display: grid;
    grid-template-columns: 1.2fr 1fr 0.8fr;
    gap: 18px;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid #e4eee4;
}

.table-skeleton-row:last-child {
    border-bottom: 0;
}

.table-skeleton-row span {
    height: 18px;
}

.table-skeleton-row span:nth-child(n + 4) {
    display: none;
}

.table-skeleton-row span:nth-child(1) {
    height: 36px;
    border-radius: 10px;
}

.table-skeleton-row span:nth-child(2) {
    width: 82%;
}

.table-skeleton-row span:nth-child(3) {
    width: 58%;
}

@keyframes skeleton-shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
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
    font-weight: 400;
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
    font-weight: 400;
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
    font-weight: 400;
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
    font-weight: 400;
}

.occurrence-head span {
    color: #607267;
    font-size: 12px;
    font-weight: 400;
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
    font-weight: 400;
}

.occurrence-list small {
    color: #607267;
    font-size: 12px;
    line-height: 1.35;
}

.occurrence-list .occurrence-state-group {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 5px;
}

.occurrence-state-group > span {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    border-radius: 4px;
    padding: 5px 9px;
    font-size: 11px;
    font-weight: 500;
    line-height: 1.15;
    white-space: nowrap;
}

.occurrence-state.state-upcoming {
    background: var(--admin-info-soft, #e8eef5);
    color: var(--admin-info, #45637d);
}

.occurrence-state.state-in-progress {
    background: var(--admin-success-soft, #e2f0e7);
    color: var(--admin-success-text, #35684a);
}

.occurrence-state.state-ended {
    background: var(--admin-surface-muted, #edf0ee);
    color: var(--admin-muted, #5d6b61);
}

.occurrence-state.state-cancelled {
    background: var(--admin-danger-soft, #fee2e2);
    color: var(--admin-danger, #b91c1c);
}

.occurrence-state.state-partial {
    background: var(--admin-warning-soft, #fef3c7);
    color: var(--admin-warning, #b45309);
}

.occurrence-payment {
    border: 1px solid var(--admin-border-soft, #dfe8e1);
    background: var(--admin-surface, #fff);
}

.occurrence-payment.payment-paid,
.occurrence-payment.payment-free {
    border-color: #b8d8c3;
    color: var(--admin-success-text, #437257);
}

.occurrence-payment.payment-partial {
    border-color: #e5d2a0;
    color: var(--admin-warning, #806525);
}

.occurrence-payment.payment-unpaid {
    border-color: #d7ded9;
    color: var(--admin-muted, #68766d);
}

.recurring-table-card strong.paid {
    color: #0f7a31;
}

.source-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 26px;
    border-radius: 999px;
    padding: 5px 10px;
    background: #ecfdf5;
    color: #15803d;
    font-size: 12px;
    font-weight: 400;
    white-space: nowrap;
}

.source-pill.online {
    background: #dbeafe;
    color: #1d4ed8;
}

.source-pill.counter {
    background: #fef3c7;
    color: #92400e;
}

.money-col {
    text-align: right;
}

.action-col {
    width: 120px;
    text-align: right;
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
    display: grid;
    grid-template-columns: minmax(180px, 0.8fr) minmax(240px, 1.25fr) minmax(
            150px,
            0.7fr
        );
    gap: 12px;
}

.group-money-grid {
    display: grid;
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
    font-weight: 400;
}

.recurring-group-card strong {
    overflow-wrap: anywhere;
    color: #16231a;
    font-size: 14px;
    font-weight: 400;
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
    color: #0f7a31;
    font-weight: 400;
}

.group-actions {
    grid-column: 2;
    display: flex;
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

.recurring-detail-modal .confirm-summary,
.booking-detail-modal .confirm-summary {
    display: flex;
    flex-direction: column;
    gap: 0;
    padding: 0;
    border: none;
    background: transparent;
    box-shadow: none;
    margin: 8px 0;
}

.recurring-detail-modal .confirm-summary div,
.booking-detail-modal .confirm-summary div {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 5px 0;
    border: none;
    border-bottom: none;
    background: transparent;
    border-radius: 0;
    box-shadow: none;
}

.recurring-detail-modal .confirm-summary div:last-child,
.booking-detail-modal .confirm-summary div:last-child {
    border-bottom: none;
}

.recurring-detail-modal .confirm-summary dt,
.booking-detail-modal .confirm-summary dt {
    color: #64748b;
    font-size: 13px;
    font-weight: 500;
    text-align: left;
    white-space: nowrap;
    margin: 0;
}

.recurring-detail-modal .confirm-summary dd,
.booking-detail-modal .confirm-summary dd {
    color: #0f172a;
    font-size: 13.5px;
    font-weight: 600;
    text-align: right;
    margin: 0;
    overflow-wrap: break-word;
    word-break: normal;
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

    .occurrence-list article {
        align-items: flex-start;
        flex-direction: column;
    }

    .occurrence-list .occurrence-state-group {
        justify-content: flex-start;
    }
}

.detail-hero div {
    display: grid;
    gap: 3px;
}

.detail-hero span:not(.status-badge) {
    color: #607267;
    font-size: 12px;
    font-weight: 400;
}

.detail-hero strong {
    color: #16231a;
    font-size: 15px;
    font-weight: 400;
}

.detail-hero small {
    color: #607267;
    font-size: 12px;
}

.booking-side,
.form-card,
.preview-box,
.alert {
    border: 1px solid var(--admin-border-soft, #e2e8f0);
    border-radius: 10px;
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

.recurring-panel .form-card {
    gap: 16px;
    padding: 0;
    border: 0;
    background: transparent;
}

.schedule-panel,
.recurring-panel .panel-head.compact,
.recurring-form-grid,
.recurring-day-grid,
.recurring-helper,
.recurring-schedule-board,
.recurring-payment {
    border: none;
    border-radius: 0;
    background: transparent;
    padding: 0;
}

.recurring-panel .panel-head.compact {
    margin-bottom: 0;
    padding: 16px 18px;
}

.recurring-schedule-board {
    display: grid;
    gap: 12px;
    padding: 16px;
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
    font-weight: 400;
}

.schedule-summary strong {
    overflow-wrap: anywhere;
    color: #16231a;
    font-size: 14px;
    font-weight: 400;
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
    font-weight: 400;
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

.recurring-form-grid {
    grid-template-columns: minmax(260px, 300px) minmax(0, 1fr);
    align-items: start;
    gap: 20px;
    padding: 18px 22px;
}

.recurring-form-fields {
    min-width: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    align-content: start;
    gap: 10px 16px;
}

.recurring-form-fields > label,
.recurring-form-fields > .readonly-field {
    min-width: 0;
    align-self: start;
}

.recurring-form-grid .calendar-range-field {
    grid-area: auto;
    max-width: 300px;
    align-self: start;
}

.recurring-form-fields > .recurring-month-day-picker {
    grid-column: 1 / -1;
    min-width: 0;
    grid-template-columns: minmax(190px, 240px) minmax(0, 1fr);
    gap: 8px 12px;
    padding: 10px 12px;
}

.recurring-month-day-picker .month-day-head {
    grid-column: 1 / -1;
    align-items: center;
}

.recurring-month-day-picker .month-day-actions {
    flex-wrap: nowrap;
}

.recurring-month-day-picker .month-day-actions button {
    min-height: 30px;
    padding: 0 9px;
    white-space: nowrap;
}

.recurring-month-day-picker .month-day-add {
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
}

.recurring-month-day-picker .month-day-selection {
    min-width: 0;
    display: grid;
    align-content: end;
    gap: 5px;
}

.recurring-month-day-picker .month-day-empty {
    padding: 9px 10px;
}

.schedule-filters {
    width: 100%;
    max-width: 100%;
    grid-template-columns: minmax(170px, 210px) minmax(500px, 1fr) minmax(
            170px,
            210px
        );
    align-items: end;
    justify-content: space-between;
    padding: 0;
    border: none;
    border-radius: 0;
    background: transparent;
}

.counter-toolbar .schedule-filter-field {
    min-width: 0;
}

.date-stepper {
    display: grid;
    grid-template-columns: 36px minmax(135px, 1fr) auto minmax(
            135px,
            1fr
        ) 36px auto;
    gap: 8px;
    align-items: center;
}

.date-range-separator {
    color: var(--admin-muted, #64748b);
    font-size: 12px;
    font-weight: 400;
    white-space: nowrap;
}

.date-stepper button {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    border: 1px solid #cfe3cf;
    border-radius: 8px;
    background: #fff;
    color: #31443a;
    font: inherit;
    font-size: 13px;
    font-weight: 400;
    cursor: pointer;
}

.date-stepper button.never-hover-class-placeholder {
    border-color: #22c55e;
    background: #f0fdf4;
    color: #166534;
}

.date-stepper .today-btn {
    padding: 0 12px;
    white-space: nowrap;
}

.date-stepper input {
    min-height: 38px;
    border: 1px solid #cfe3cf;
    border-radius: 8px;
    background: #fff;
    color: #16231a;
    font-weight: 400;
}

.counter-date-range {
    position: relative;
    display: grid;
    grid-template-columns: 36px minmax(220px, 1fr) 36px auto;
    gap: 8px;
    align-items: center;
}

.date-picker-wrap {
    position: relative;
    min-width: 0;
}

.date-nav-btn,
.date-range-trigger,
.counter-date-range .today-btn {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid #cfe3cf;
    border-radius: 8px;
    background: #fff;
    color: #16231a;
    font: inherit;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}

.date-range-trigger {
    width: 100%;
    justify-content: flex-start;
    padding: 0 12px;
    text-align: left;
}

.date-range-trigger.open {
    border-color: var(--admin-primary, #16a34a);
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
}

.date-range-trigger span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.counter-date-range .today-btn {
    padding: 0 12px;
    white-space: nowrap;
}

.counter-date-popover {
    position: absolute;
    z-index: 1000;
    top: calc(100% + 6px);
    left: 0;
    padding: 0 !important;
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
}

:global([data-theme="dark"] .owner-counter-page .date-nav-btn),
:global([data-theme="dark"] .owner-counter-page .date-range-trigger),
:global([data-theme="dark"] .owner-counter-page .counter-date-range .today-btn) {
    border-color: var(--admin-border, #164e2f);
    background: var(--admin-surface, #0f1f17);
    color: var(--admin-text, #f4fff7);
}

:global([data-theme="dark"] .owner-counter-page .counter-date-popover) {
    border: none !important;
    background: transparent !important;
}

.counter-date-popover :is(.mini-cal-card, .mini-cal) {
    max-width: 100%;
}

.readonly-field {
    display: grid;
    gap: 8px;
    padding: 10px 12px;
    min-height: 58px;
    border: 1px solid #cfe3cf;
    border-radius: 8px;
    background: #f7fbf5;
}

.readonly-field span {
    color: #607267;
    font-size: 12px;
    font-weight: 400;
}

.readonly-field strong {
    color: #16231a;
    font-size: 14px;
    font-weight: 400;
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
    font-weight: 400;
}

.selection-help strong {
    color: #16231a;
    font-size: 14px;
    font-weight: 400;
}

.selection-help small {
    color: #607267;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.35;
}

.duration-pill span {
    color: #607267;
    font-size: 11px;
    font-weight: 400;
}

.duration-pill strong {
    color: #16231a;
    font-size: 14px;
    font-weight: 400;
}

.duration-pill.active {
    border-color: var(--admin-primary, #000000);
    background: var(--admin-primary-soft, #f3f4f6);
}

label {
    display: grid;
    gap: 7px;
}

label span,
.summary-list dt {
    color: #223127;
    font-size: 13px;
    font-weight: 400;
}

input,
select {
    width: 100%;
}

input.invalid {
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08);
}

.field-error {
    color: #b91c1c;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.35;
}

.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin: 14px 0;
    color: #475b4d;
    font-size: 12px;
    font-weight: 400;
}

.legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* ===== Harmonized Clean SaaS Status Colors ===== */
.legend i {
    width: 12px;
    height: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 3px;
    background: #ffffff;
}

.legend i.selected {
    border-color: #059669;
    background: #10b981;
}

.legend i.booked-paid {
    border-color: #94a3b8;
    background: #e2e8f0;
}

.legend i.booked-online {
    border-color: #93c5fd;
    background: #dbeafe;
}

.legend i.booked-counter {
    border-color: #fcd34d;
    background: #fef3c7;
}

.legend i.pay-later {
    border-color: #c084fc;
    background: #f3e8ff;
}

.legend i.overdue {
    border-color: #fca5a5;
    background: #fee2e2;
}

.legend i.locked {
    border-color: #cbd5e1;
    background: #f1f5f9;
}

.selection-error {
    margin: 0 0 12px;
    color: #991b1b;
    font-size: 13px;
    font-weight: 400;
}

.time-board {
    display: grid;
    gap: 12px;
}

.selected-court-strip {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 0;
    border: none;
    border-radius: 0;
    background: transparent;
    margin-top: 16px;
    margin-bottom: 12px;
}

.selected-court-strip div {
    display: grid;
    gap: 3px;
}

.selected-court-strip span {
    color: #607267;
    font-size: 12px;
    font-weight: 400;
}

.selected-court-strip strong {
    color: #16231a;
    font-size: 14px;
    font-weight: 400;
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

.tabs-and-actions .tabs button,
.tabs-and-actions .tabs .tab-nav-link {
    margin-right: 0;
    white-space: nowrap;
}

.tabs-and-actions .secondary-btn {
    flex: 0 0 auto;
    margin-left: auto;
}

.tabs button,
.tabs .tab-nav-link {
    margin-right: 8px;
}

.tab-nav-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    border: 1px solid #d5e4d6;
    border-radius: 8px;
    padding: 0 14px;
    background: #fff;
    color: #24362a;
    font-size: 14px;
    font-weight: 400;
    text-decoration: none;
}

.tab-nav-link:hover {
    border-color: #16a34a;
    color: #087c35;
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
    font-weight: 400;
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
    gap: 8px;
    height: 38px;
    min-height: 38px;
    padding: 0 16px;
    border-radius: var(--admin-radius, 8px);
    border: 1px solid var(--admin-border, #cbd5e1);
    background: var(--admin-surface, #ffffff);
    color: var(--admin-text, #475569);
    font-size: 13px;
    font-weight: 400;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.18s ease;
    user-select: none;
}

.period-tabs button:hover:not(.active) {
    background: var(--admin-hover, #f1f5f9);
    color: var(--admin-text, #0f172a);
}

.period-tabs button.active {
    background: var(--admin-accent, #10b981);
    color: #ffffff;
    border-color: var(--admin-accent, #10b981);
    font-weight: 500;
}

.period-tabs button strong {
    font-weight: 600;
}

.period-tabs button span {
    font-size: 12px;
    font-weight: 400;
    opacity: 0.85;
}

.slot-matrix {
    display: grid;
    overflow-x: auto;
    border: none;
    border-radius: 0;
    background: transparent;
}

.matrix-head,
.matrix-court,
.time-slot {
    min-height: 36px;
    border-right: 1px solid var(--admin-border-soft, #f1f5f9);
    border-bottom: 1px solid var(--admin-border-soft, #f1f5f9);
}

.matrix-head {
    display: grid;
    place-items: center;
    background: #f8fafc;
    color: #475569;
    font-size: 11.5px;
    font-weight: 600;
}

.matrix-court {
    display: grid;
    align-content: center;
    gap: 2px;
    padding: 6px 10px;
    background: #fff;
    border-right: 2px solid #e2e8f0;
}

.matrix-court strong {
    color: #0f172a;
    font-size: 12px;
    font-weight: 600;
}

.matrix-court span {
    color: #64748b;
    font-size: 11px;
    font-weight: 400;
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
    background: #ffffff;
    transition:
        background 0.16s ease,
        box-shadow 0.16s ease;
}

.time-slot.selected {
    background: #10b981;
    color: #ffffff;
    box-shadow: inset 0 0 0 1px #059669;
}

.time-slot.busy {
    background: #f1f5f9;
}

.time-slot.unavailable {
    background: #f8fafc;
}

.time-slot.booked-paid {
    background: #e2e8f0;
    color: #334155;
}

.time-slot.booked-online {
    background: #dbeafe;
    color: #1e40af;
}

.time-slot.booked-counter {
    background: #fef3c7;
    color: #92400e;
}

.time-slot.pay-later {
    background: #f3e8ff;
    color: #6b21a8;
}

.time-slot.overdue {
    background: #fee2e2;
    color: #991b1b;
}

.time-slot.locked {
    background: #f1f5f9;
    color: #64748b;
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
    font-weight: 400;
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
    padding-bottom: 0;
    border-bottom: none;
}

.modal-head span {
    color: #0f7a31;
    font-size: 12px;
    font-weight: 400;
}

.modal-head h2 {
    margin: 4px 0 0;
    color: #16231a;
    font-size: 20px;
    font-weight: 400;
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
    font-weight: 400;
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
    font-weight: 400;
    line-height: 1.25;
    cursor: pointer;
}

.modal-actions .secondary-btn {
    border: 1px solid var(--admin-border, #e5e7eb);
    background: #fff;
    color: var(--admin-text, #000000);
}

.modal-actions .primary-btn {
    border: 1px solid var(--admin-primary, #000000);
    background: var(--admin-primary, #000000);
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
    font-weight: 400;
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
    border-color: var(--admin-primary, #000000);
    box-shadow: 0 0 0 3px var(--admin-primary-ring, rgba(0, 0, 0, 0.1));
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
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 10001;
    isolation: isolate;
    box-sizing: border-box;
    width: min(600px, calc(100vw - 24px));
    height: 100dvh;
    display: grid;
    align-content: start;
    gap: 12px;
    padding: 18px 20px 24px;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #101c15);
    border-left: 1px solid var(--admin-border, #cfded1);
    box-shadow: -16px 0 46px rgba(15, 23, 42, 0.16);
    transform: translateX(106%);
    visibility: hidden;
    pointer-events: none;
    transition:
        transform 0.22s ease,
        visibility 0s linear 0.22s;
}

.booking-side.open {
    transform: translateX(0);
    visibility: visible;
    pointer-events: auto;
    transition-delay: 0s;
}

.counter-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 10000;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
    border: 0;
    background: rgba(15, 23, 42, 0.34);
    cursor: default;
}

.drawer-close-btn {
    position: sticky;
    top: 0;
    justify-self: end;
    z-index: 3;
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border: 1px solid var(--admin-border, #cfded1);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
    color: var(--admin-muted, #2f3d34);
    cursor: pointer;
}

.drawer-close-btn:hover {
    border-color: var(--admin-primary, #22a653);
    background: var(--admin-primary-soft, #e2f6e8);
    color: var(--admin-primary-dark, #15733a);
}

.booking-side .section-title h2 {
    color: var(--admin-text, #101c15);
}

.booking-side .side-section {
    border-color: var(--admin-border-soft, #e3ece4);
}

.booking-side .side-section > label > span,
.booking-side .summary-list dt {
    color: var(--admin-faint, #45564a);
}

.booking-side .summary-list dd {
    color: var(--admin-text, #101c15);
}

.booking-side input {
    min-height: 42px;
    border: 1px solid var(--admin-border, #cfded1);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #101c15);
    font: inherit;
}

.booking-side input:focus {
    border-color: var(--admin-primary, #22a653);
    box-shadow: 0 0 0 3px var(--admin-primary-ring, rgba(34, 166, 83, 0.22));
    outline: none;
}

.booking-side input::placeholder {
    color: var(--admin-faint, #64748b);
}

.booking-side .primary-btn,
.booking-side .secondary-btn {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border-radius: 8px;
    padding: 9px 14px;
    font: inherit;
    font-size: 13px;
    font-weight: 400;
    cursor: pointer;
}

.booking-side .primary-btn {
    border: 1px solid var(--admin-primary, #22a653);
    background: var(--admin-primary, #22a653);
    color: var(--admin-primary-text, #fff);
}

.booking-side .primary-btn:hover {
    border-color: var(--admin-primary-dark, #15733a);
    background: var(--admin-primary-dark, #15733a);
}

.booking-side .secondary-btn {
    border: 1px solid var(--admin-border, #cfded1);
    background: var(--admin-surface, #fff);
    color: var(--admin-muted, #2f3d34);
}

.booking-side .secondary-btn:hover {
    border-color: var(--admin-primary, #22a653);
    background: var(--admin-primary-soft, #e2f6e8);
    color: var(--admin-primary-dark, #15733a);
}

.booking-side :is(.primary-btn, .secondary-btn):disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.counter-bottom-bar {
    position: static;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-top: 12px;
    padding: 12px 14px;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    background: rgba(240, 253, 244, 0.96);
    box-shadow: 0 12px 36px rgba(22, 101, 52, 0.15);
    backdrop-filter: blur(8px);
}

.counter-bottom-bar div {
    min-width: 0;
    display: grid;
    gap: 3px;
}

.counter-bottom-bar strong,
.counter-bottom-bar span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.counter-bottom-bar strong {
    color: #14532d;
    font-size: 14px;
    font-weight: 400;
}

.counter-bottom-bar span {
    color: #475569;
    font-size: 12px;
    font-weight: 400;
}

.calendar-range-field {
    display: grid;
    gap: 8px;
    max-width: 276px;
}

.calendar-range-field > span {
    color: #223127;
    font-size: 13px;
    font-weight: 400;
}

.schedule-filters .mini-cal,
.calendar-range-field .mini-cal {
    max-width: 100%;
}

.side-section {
    min-width: 0;
    display: grid;
    gap: 10px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e4eee4;
}

.side-section > label {
    min-width: 0;
    display: grid;
    gap: 6px;
}

.side-section > label > span {
    color: #526458;
    font-size: 12px;
    font-weight: 400;
}

.side-section > label > input {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
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
    font-weight: 400;
    line-height: 1.25;
    white-space: normal;
}

.status-actions .action-success {
    border-color: var(--admin-primary, #000000);
    background: var(--admin-primary, #000000);
    color: #fff;
}

.status-actions .action-primary {
    border-color: var(--admin-primary, #000000);
    background: var(--admin-primary, #000000);
    color: #fff;
}

.status-actions .action-cash {
    border-color: var(--admin-primary, #000000);
    background: var(--admin-primary, #000000);
    color: #fff;
}

.status-actions .action-transfer {
    border-color: var(--admin-primary, #000000);
    background: var(--admin-primary, #000000);
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
    display: grid;
    grid-template-columns: minmax(100px, 0.36fr) minmax(0, 0.64fr);
    align-items: flex-start;
    gap: 14px;
    min-width: 0;
}

.summary-list dt {
    color: #607267;
    font-size: 12px;
    font-weight: 400;
}

.summary-list dd {
    min-width: 0;
    max-width: none;
    margin: 0;
    color: #16231a;
    font-weight: 400;
    text-align: right;
    overflow-wrap: anywhere;
}

.booking-status-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 4px 0;
    border-bottom: none;
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
    font-weight: 400;
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
    font-weight: 400;
    line-height: 1.15;
    white-space: nowrap;
}

.status-badge.tone-paid {
    border-color: #a7cbb4;
    background: #e2f0e7;
    color: #35684a;
}

.status-badge.tone-online {
    border-color: #a9bdcc;
    background: #e5edf3;
    color: #3f6178;
}

.status-badge.tone-counter {
    border-color: #bbb4cb;
    background: #ebe9f1;
    color: #5c5570;
}

.status-badge.tone-later {
    border-color: #d1bd86;
    background: #f3eddc;
    color: #765f2d;
}

.status-badge.tone-overdue {
    border-color: #d3aaa4;
    background: #f3e3e0;
    color: #854f48;
}

.status-badge.tone-pending {
    border-color: #d1bd86;
    background: #f3eddc;
    color: #765f2d;
}

.status-badge.tone-confirmed {
    border-color: #a9bdcc;
    background: #e5edf3;
    color: #3f6178;
}

.status-badge.tone-review {
    border-color: #bbb4cb;
    background: #ebe9f1;
    color: #5c5570;
}

.status-badge.tone-checked-in {
    border-color: #9fc6c0;
    background: #e0efed;
    color: #356b65;
}

.status-badge.tone-rejected {
    border-color: #d7b1ad;
    background: #f2e5e3;
    color: #80534e;
}

.status-badge.tone-cancelled {
    border-color: #c4cbc7;
    background: #ecefed;
    color: #59635d;
}

.status-badge.tone-locked,
.status-badge.tone-neutral {
    border-color: #c7ceca;
    background: #f0f2f1;
    color: #59635d;
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
    border: 1px solid var(--admin-border, #cfded1);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #101c15);
}

.payment-card.active {
    border-color: var(--admin-primary, #000000);
    background: var(--admin-primary-soft, #f3f4f6);
}

.payment-card input {
    width: 16px;
    height: 16px;
    accent-color: var(--admin-primary, #000000);
}

.payment-card strong {
    color: var(--admin-text, #101c15);
}

.payment-card small {
    display: block;
    margin-top: 4px;
    color: #607267;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.35;
}

.inline-note {
    padding: 10px 12px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #f7fbf5;
    color: #475b4d;
    font-size: 13px;
    font-weight: 400;
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
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    border: 1px solid var(--admin-border, #cfded1);
    border-radius: 8px;
    padding: 10px 12px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #101c15);
    font-weight: 400;
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
    border: 1px solid var(--admin-border, #cfded1);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
    padding: 10px 11px;
    text-align: left;
    cursor: pointer;
    overflow: hidden;
}

.voucher-list button.active {
    border-color: var(--admin-primary, #22a653);
    background: var(--admin-primary-soft, #e2f6e8);
}

.voucher-list strong {
    display: block;
    color: var(--admin-primary-dark, #15733a);
    font-size: 13px;
    font-weight: 400;
}

.voucher-list small,
.voucher-empty {
    color: var(--admin-faint, #45564a);
    font-size: 12px;
    font-weight: 400;
}

.voucher-list em {
    min-width: 0;
    color: var(--admin-primary-dark, #15733a);
    font-style: normal;
    font-weight: 400;
    overflow-wrap: anywhere;
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
    font-weight: 400;
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
    font-weight: 400;
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
    font-weight: 400;
}

.segmented-field button.active {
    border-color: var(--admin-primary, #000000);
    background: var(--admin-primary, #000000);
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
    color: var(--admin-muted, #475b4d);
    font-size: 13px;
}

.qr-info button {
    border: 0;
    background: transparent;
    color: var(--admin-success-text, #216b34);
    font-weight: 500;
    text-decoration: underline;
}

.qr-info strong {
    color: var(--admin-text, #16231a);
}

.day-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.recurring-day-grid {
    padding: 12px 16px;
}

.recurring-weekday-planner {
    display: grid;
    grid-template-columns: repeat(7, minmax(112px, 1fr));
    gap: 10px;
    padding: 14px 16px 16px;
    border: 1px solid var(--admin-success, #d8eadb);
    border-radius: 10px;
    background: var(--admin-surface-muted, #fbfefc);
}

.recurring-date-planner {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 10px;
    padding: 14px 16px 16px;
    border: 1px solid var(--admin-border, #d8eadb);
    border-radius: 8px;
    background: var(--admin-surface-muted, #fbfefc);
}

.recurring-form-fields > .recurring-date-planner--inline {
    grid-column: 1 / -1;
    grid-template-columns: minmax(0, 1fr);
    padding: 10px 12px 12px;
}

.recurring-date-list {
    min-width: 0;
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding: 2px;
    cursor: grab;
    scrollbar-width: none;
    -ms-overflow-style: none;
    touch-action: pan-y;
    overscroll-behavior-inline: contain;
    -webkit-overflow-scrolling: touch;
    user-select: none;
}

.recurring-date-list::-webkit-scrollbar {
    display: none;
}

.recurring-date-list.dragging {
    cursor: grabbing;
}

.recurring-date-list .recurring-date-card {
    flex: 0 0 168px;
    min-height: 76px;
    cursor: grab;
}

.recurring-date-list.dragging .recurring-date-card {
    cursor: grabbing;
}

.recurring-date-card {
    min-height: 88px;
    display: grid;
    align-content: start;
    gap: 5px;
    padding: 10px;
    border: 1px solid var(--admin-border-soft, #e2e8f0);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #1f2f25);
    text-align: left;
    cursor: pointer;
}

.recurring-date-card.complete {
    background: var(--admin-success-soft, #f0fdf4);
    border-color: #86efac;
}

.recurring-date-card.active {
    border-color: var(--admin-primary, #16a34a);
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.16);
}

.recurring-date-card .recurring-date-value {
    width: fit-content;
    padding: 3px 7px;
    border-radius: 4px;
    background: #e8f7ec;
    color: #15803d;
    font-size: 12px;
    font-weight: 500;
}

.recurring-date-card strong {
    font-size: 13px;
    line-height: 1.35;
}

.recurring-date-card small,
.recurring-calendar-note {
    color: var(--admin-muted, #64756b);
    font-size: 12px;
    line-height: 1.35;
}

.recurring-calendar-mode {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 4px;
    padding: 4px;
    border: 1px solid var(--admin-border, #d9e8d9);
    border-radius: 8px;
    background: var(--admin-surface-muted, #f6faf7);
}

.recurring-calendar-mode button {
    min-height: 32px;
    padding: 0 8px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--admin-muted, #64756b);
    font: inherit;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
}

.recurring-calendar-mode button.active {
    background: var(--admin-surface, #fff);
    color: var(--admin-success-text, #166534);
    box-shadow: 0 0 0 1px var(--admin-border, #cfe3cf);
}

.weekday-planner-head {
    grid-column: 1 / -1;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 14px;
    padding-bottom: 4px;
}

.weekday-planner-head strong {
    display: block;
    color: #16231a;
    font-size: 15px;
    font-weight: 400;
}

.weekday-planner-head span {
    display: block;
    margin-top: 2px;
    color: #64756b;
    font-size: 12px;
    font-weight: 400;
}

.weekday-planner-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.weekday-planner-actions button {
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid #cfe3cf;
    border-radius: 8px;
    background: #fff;
    color: #216b34;
    font: inherit;
    font-size: 12px;
    font-weight: 400;
    cursor: pointer;
}

.weekday-planner-actions button.never-hover-class-placeholder:not(:disabled) {
    border-color: #22c55e;
    background: #f0fdf4;
}

.weekday-planner-actions button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.weekday-plan-card {
    min-height: 92px;
    display: grid;
    align-content: start;
    gap: 5px;
    padding: 10px;
    border: 1px solid #d9e8d9;
    border-radius: 10px;
    background: #fff;
    color: #1f2f25;
    text-align: left;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.15s ease;
}

.weekday-plan-card.never-hover-class-placeholder {
    transform: translateY(-1px);
    border-color: #86d19a;
}

.weekday-plan-card.selected {
    background: #f0fdf4;
    border-color: #86efac;
}

.weekday-plan-card.active {
    border-color: #16a34a;
    box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.16);
}

.weekday-plan-card .weekday-name {
    width: fit-content;
    padding: 4px 8px;
    border-radius: 999px;
    background: #e8f7ec;
    color: #15803d;
    font-size: 12px;
    font-weight: 400;
}

.weekday-plan-card strong {
    color: #132017;
    font-size: 13px;
    font-weight: 400;
    line-height: 1.35;
}

.weekday-plan-card small {
    color: #64756b;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.35;
}

.active-weekday-note {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    background: #f0fdf4;
}

.active-weekday-note strong {
    color: #166534;
    font-size: 13px;
    font-weight: 400;
}

.active-weekday-note span {
    color: #557063;
    font-size: 12px;
    font-weight: 400;
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
    font-weight: 400;
    cursor: pointer;
    transition:
        border-color 0.16s ease,
        background 0.16s ease,
        color 0.16s ease,
        box-shadow 0.16s ease,
        transform 0.16s ease;
}

.day-grid label.never-hover-class-placeholder {
    border-color: #86efac;
    background: #f0fdf4;
    transform: translateY(-1px);
}

.day-grid label.selected {
    border-color: var(--admin-primary, #000000);
    background: var(--admin-primary, #000000);
    color: #fff;
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
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

.month-day-picker {
    display: grid;
    gap: 10px;
    padding: 16px;
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.month-day-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
}

.month-day-head strong {
    display: block;
    color: #16231a;
    font-size: 15px;
    font-weight: 400;
}

.month-day-head span {
    display: block;
    margin-top: 2px;
    color: #64756b;
    font-size: 12px;
    font-weight: 400;
}

.month-day-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.month-day-actions button {
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid #cfe3cf;
    border-radius: 8px;
    background: #fff;
    color: #216b34;
    font: inherit;
    font-size: 12px;
    font-weight: 400;
    cursor: pointer;
}

.month-day-actions button:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.month-day-actions button.never-hover-class-placeholder {
    border-color: #22c55e;
    background: #f0fdf4;
}

.month-day-summary {
    color: var(--admin-muted, #64756b);
    font-size: 12px;
    font-weight: 500;
}

.month-day-add {
    display: grid;
    grid-template-columns: minmax(180px, 260px) auto;
    align-items: end;
    gap: 10px;
}

.month-day-add label {
    display: grid;
    gap: 6px;
}

.month-day-add label > span {
    color: var(--admin-muted, #64756b);
    font-size: 12px;
    font-weight: 500;
}

.month-day-add select,
.month-day-add button {
    min-height: 38px;
    border: 1px solid var(--admin-border, #cfe3cf);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #16231a);
    font: inherit;
    font-size: 13px;
    font-weight: 500;
}

.month-day-add select {
    padding: 0 10px;
}

.month-day-add button {
    padding: 0 14px;
    color: var(--admin-success-text, #166534);
    cursor: pointer;
}

.month-day-add button.never-hover-class-placeholder {
    border-color: var(--admin-success, #86efac);
    background: var(--admin-success-soft, #f0fdf4);
}

.month-day-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.month-day-chip {
    min-height: 34px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 0 10px;
    border: 1px solid var(--admin-primary, #16a34a);
    border-radius: 8px;
    background: var(--admin-success-soft, #f0fdf4);
    color: var(--admin-success-text, #166534);
    font: inherit;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}

.month-day-chip.never-hover-class-placeholder {
    border-color: var(--admin-primary, #16a34a);
    background: var(--admin-surface, #fff);
    box-shadow: 0 8px 16px rgba(22, 163, 74, 0.16);
}

.month-day-empty {
    margin: 0;
    padding: 10px 12px;
    border: 1px dashed var(--admin-border, #cfe3cf);
    border-radius: 8px;
    color: var(--admin-muted, #64756b);
    font-size: 13px;
}

.recurring-helper {
    margin: 0;
    padding: 11px 14px;
    color: #607267;
    font-size: 13px;
    line-height: 1.45;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
}

.recurring-form-actions {
    padding: 0 16px 16px;
}

.preview-box {
    position: sticky;
    top: 88px;
    display: grid;
    gap: 10px;
    max-height: calc(100vh - 112px);
    overflow: auto;
}

.recurring-detail-box {
    padding: 16px;
    background: #fbfdfb;
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
    font-weight: 400;
}

.preview-box strong {
    color: #16231a;
    font-size: 18px;
    font-weight: 400;
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
    font-weight: 400;
    text-transform: uppercase;
}

.preview-panel-head span {
    color: #64748b;
    font-size: 12px;
    font-weight: 400;
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
    font-weight: 400;
}

.preview-stat-grid strong {
    color: #16231a;
    font-size: 18px;
    font-weight: 400;
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
    font-weight: 400;
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
    font-weight: 400;
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
    font-weight: 400;
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
    font-weight: 400;
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
    font-weight: 400;
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
    .recurring-panel {
        grid-template-columns: 1fr;
    }

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

    .recurring-form-grid {
        grid-template-columns: 1fr;
    }

    .recurring-form-fields {
        grid-template-columns: 1fr;
    }

    .recurring-form-fields > .recurring-month-day-picker {
        grid-template-columns: 1fr;
    }

    .recurring-month-day-picker .month-day-actions {
        flex-wrap: wrap;
    }

    .recurring-month-day-picker .month-day-add {
        grid-template-columns: 1fr;
    }

    .counter-date-range {
        grid-template-columns: 36px minmax(0, 1fr) 36px;
    }

    .counter-date-range .today-btn {
        grid-column: 1 / -1;
        width: 100%;
    }

    .calendar-range-field {
        max-width: 100%;
    }

    .recurring-form-grid .calendar-range-field {
        max-width: 100%;
    }

    .weekday-planner-head,
    .month-day-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .weekday-planner-actions,
    .month-day-actions {
        justify-content: flex-start;
    }

    .recurring-weekday-planner {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .month-day-add {
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

@media (max-width: 560px) {
    .booking-side {
        width: 100vw;
        max-width: none;
        padding: 14px 14px 22px;
        border: 0;
        border-radius: 0;
    }

    .drawer-close-btn {
        top: 0;
    }

    .summary-list div {
        grid-template-columns: minmax(88px, 0.3fr) minmax(0, 0.7fr);
        gap: 10px;
    }

    .summary-list dd {
        font-size: 13px;
    }

    .voucher-code-row {
        grid-template-columns: minmax(0, 1fr) 88px;
    }

    .payment-card {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .payment-card strong {
        grid-column: auto;
        justify-self: start;
    }
}
/* UI rules */
.owner-counter-page .tabs-and-actions {
    align-items: center;
    padding-bottom: 2px;
}
.owner-counter-page .tabs {
    gap: 8px;
    padding: 0;
    border: 0;
    background: transparent;
}
.owner-counter-page .tabs button,
.owner-counter-page .tab-nav-link {
    border: 0;
    border-radius: 4px;
    background: transparent;
    color: var(--admin-muted);
    font-weight: 500;
    box-shadow: none;
}
.owner-counter-page .tabs button.active,
.owner-counter-page .tab-nav-link:hover {
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
    font-weight: 500;
}
.owner-counter-page .counter-board,
.owner-counter-page .recurring-panel {
    gap: 20px;
}
.owner-counter-page .schedule-panel,
.owner-counter-page .booking-side,
.owner-counter-page .form-card,
.owner-counter-page .preview-box,
.owner-counter-page .recurring-list-panel,
.owner-counter-page .recurring-panel {
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
}
.owner-counter-page .profile-section-card {
    border: 0 !important;
    background: #ffffff !important;
    box-shadow: none !important;
    padding: 10px !important;
}
.owner-counter-page .schedule-filters,
.owner-counter-page .recurring-schedule-board,
.owner-counter-page .recurring-payment,
.owner-counter-page .recurring-weekday-planner,
.owner-counter-page .selected-court-strip > div,
.owner-counter-page .schedule-summary > div {
    border: 0;
    border-radius: 4px;
    background: var(--admin-bg-soft);
    box-shadow: none;
}
.owner-counter-page h2,
.owner-counter-page h3 {
    font-weight: 500;
}
.owner-counter-page strong,
.owner-counter-page button,
.owner-counter-page label {
    font-weight: 500;
}
.owner-counter-page .status-badge,
.owner-counter-page .source-pill,
.owner-counter-page .fixed-date-chips span,
.owner-counter-page .fixed-date-chips em {
    border-radius: 4px;
    font-weight: 500;
}
.owner-counter-page .legend i {
    border-radius: 3px;
}
.owner-counter-page .matrix-head {
    font-weight: 500;
}
.owner-counter-page .matrix-court strong {
    font-weight: 500;
}
.owner-counter-page .secondary-btn,
.owner-counter-page .primary-btn {
    font-weight: 500;
    border-radius: 4px;
}
.owner-counter-page .modal-panel,
.owner-counter-page .recurring-detail-modal,
.owner-counter-page .conflict-modal,
.owner-counter-page .qr-modal {
    border: 0;
    border-radius: 0;
    box-shadow: var(--admin-shadow-lg);
}
.owner-counter-page .source-pill,
.owner-counter-page .status-badge,
.owner-counter-page .fixed-date-chips span,
.owner-counter-page .fixed-date-chips em,
.owner-counter-page .occurrence-list article > span {
    border-radius: 4px;
    font-weight: 500;
}

.owner-counter-page .schedule-panel {
    padding: 20px;
    border: 1px solid var(--admin-border, #d9e8d9);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
}

.owner-counter-page .counter-toolbar {
    gap: 14px;
    margin-bottom: 16px;
    padding: 16px;
    border: 1px solid var(--admin-border, #d9e8d9);
    border-radius: 8px;
    background: #f7fcf8;
}

.owner-counter-page .time-board {
    gap: 16px;
}

.owner-counter-page .selected-court-strip {
    gap: 12px;
    margin-top: 16px;
    padding: 14px;
    border: 1px solid var(--admin-border, #d9e8d9);
    border-radius: 8px;
    background: #f8fcf9;
}

.owner-counter-page .selected-court-strip > div {
    min-height: 58px;
    padding: 10px 12px;
    border: 1px solid var(--admin-border-soft, #e4eee4);
    border-radius: 6px;
    background: #fff;
}

.schedule-skeleton {
    min-height: 430px;
    gap: 18px;
    padding: 18px;
    border: 1px solid var(--admin-border, #d9e8d9);
    border-radius: 8px;
    background: #fff;
}

.skeleton-matrix {
    min-height: 260px;
    gap: 16px 20px;
    padding: 18px;
}

.booking-side {
    background: #fff;
    color: #101c15;
    border-left: 1px solid #cfded1;
}

.booking-side .side-section {
    gap: 12px;
    padding-bottom: 16px;
}

.owner-counter-page
    .booking-side
    input:not([type="checkbox"]):not([type="radio"]),
.owner-counter-page .booking-side select,
.owner-counter-page .booking-side textarea,
.owner-counter-page .form-card input:not([type="checkbox"]):not([type="radio"]),
.owner-counter-page .form-card select,
.owner-counter-page .form-card textarea,
.owner-counter-page
    .voucher-code-row
    input:not([type="checkbox"]):not([type="radio"]) {
    background: #fff;
    background-color: #fff;
    background-image: none;
    color: #101c15;
    -webkit-text-fill-color: #101c15;
    caret-color: #101c15;
    color-scheme: light;
}

.owner-counter-page
    .booking-side
    input:not([type="checkbox"]):not([type="radio"]):disabled,
.owner-counter-page .booking-side select:disabled,
.owner-counter-page .booking-side textarea:disabled,
.owner-counter-page
    .form-card
    input:not([type="checkbox"]):not([type="radio"]):disabled,
.owner-counter-page .form-card select:disabled,
.owner-counter-page .form-card textarea:disabled {
    background: #f4f7f4;
    color: #66766b;
    -webkit-text-fill-color: #66766b;
}

.owner-counter-page .booking-side input::placeholder,
.owner-counter-page .booking-side textarea::placeholder,
.owner-counter-page .form-card input::placeholder,
.owner-counter-page .form-card textarea::placeholder,
.owner-counter-page .voucher-code-row input::placeholder {
    color: #7b8a80;
    -webkit-text-fill-color: #7b8a80;
    opacity: 1;
}

.owner-counter-page
    .booking-side
    input:not([type="checkbox"]):not([type="radio"]):-webkit-autofill,
.owner-counter-page
    .booking-side
    input:not([type="checkbox"]):not([type="radio"]):-webkit-autofill:hover,
.owner-counter-page
    .booking-side
    input:not([type="checkbox"]):not([type="radio"]):-webkit-autofill:focus,
.owner-counter-page
    .booking-side
    input:not([type="checkbox"]):not([type="radio"]):-webkit-autofill:active,
.owner-counter-page
    .form-card
    input:not([type="checkbox"]):not([type="radio"]):-webkit-autofill,
.owner-counter-page
    .form-card
    input:not([type="checkbox"]):not([type="radio"]):-webkit-autofill:hover,
.owner-counter-page
    .form-card
    input:not([type="checkbox"]):not([type="radio"]):-webkit-autofill:focus,
.owner-counter-page
    .form-card
    input:not([type="checkbox"]):not([type="radio"]):-webkit-autofill:active {
    background: #fff;
    background-color: #fff;
    background-image: none;
    -webkit-text-fill-color: #101c15;
    caret-color: #101c15;
    box-shadow: 0 0 0 1000px #fff inset;
    -webkit-box-shadow: 0 0 0 1000px #fff inset;
    color-scheme: light;
    transition:
        background-color 9999s ease-out 0s,
        color 9999s ease-out 0s;
}

.owner-counter-page
    .booking-side
    input:not([type="checkbox"]):not([type="radio"]):autofill,
.owner-counter-page
    .form-card
    input:not([type="checkbox"]):not([type="radio"]):autofill {
    background: #fff;
    background-color: #fff;
    background-image: none;
    color: #101c15;
    -webkit-text-fill-color: #101c15;
    box-shadow: 0 0 0 1000px #fff inset;
    -webkit-box-shadow: 0 0 0 1000px #fff inset;
    color-scheme: light;
}

.payment-card {
    min-height: 54px;
    border-color: #cfded1;
    background: #fff;
    cursor: pointer;
}

.payment-card.active {
    border-color: #22a653;
    background: #e8f7ee;
    box-shadow: inset 3px 0 0 #22a653;
}

.booking-side .payment-card {
    position: relative;
    grid-template-columns: minmax(0, 1fr) auto;
}

.booking-side .payment-card input[type="radio"] {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: 0;
    opacity: 0;
    pointer-events: none;
}

.payment-card span,
.payment-card strong {
    color: #101c15;
}

.qr-modal-backdrop,
.booking-action-modal-backdrop {
    z-index: 10020;
    background: rgba(15, 23, 42, 0.58);
}

.qr-modal-backdrop .qr-modal {
    border: 1px solid #d9e8d9;
    border-radius: 8px;
    background: #fff;
}

.owner-counter-page {
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* ===== Single Unified Surface Card ===== */
.cluster-profile-surface.standalone {
    display: flex;
    flex-direction: column;
    gap: 0 !important;
    background: var(--admin-surface, #ffffff);
    border-radius: 0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.hero-integrated-tabs {
    padding: 16px 24px 0 24px;
    background: var(--admin-surface, #ffffff);
}

.profile-section-card {
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.profile-section-card + .profile-section-card {
    border-top: none;
}

.tab-section-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.header-inline-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.header-type-select {
    height: 38px;
    border-radius: 8px;
    border: 1px solid var(--admin-border-soft, #cbd5e1);
    padding: 0 12px;
    background: #ffffff;
    font-size: 13.5px;
    color: var(--admin-text, #0f172a);
    cursor: pointer;
}

.tab-section-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 400;
    color: var(--admin-text, #0f172a);
}

.section-subtitle {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--admin-muted, #64748b);
}

.counter-toolbar-flat {
    display: flex;
    align-items: flex-end;
    justify-content: flex-start;
    gap: 24px;
    padding: 0;
    border: none;
    background: transparent;
}

.counter-toolbar-flat .schedule-filter-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.counter-toolbar-flat .schedule-filter-field > span {
    font-size: 12px;
    font-weight: 500;
    color: var(--admin-muted, #64748b);
}

.counter-toolbar-flat .type-field select {
    min-width: 160px;
    height: 38px;
    border-radius: 8px;
    border: 1px solid var(--admin-border-soft, #cbd5e1);
    padding: 0 12px;
    background: #ffffff;
    font-size: 13.5px;
    color: var(--admin-text, #0f172a);
}

.counter-date-range {
    display: flex;
    align-items: center;
    gap: 6px;
}

.custom-court-type-dropdown .court-type-trigger-btn:hover {
    background: #f8fafc !important;
    border-color: #94a3b8 !important;
}

.custom-court-type-dropdown .court-type-trigger-btn:focus,
.custom-court-type-dropdown .court-type-trigger-btn:focus-visible {
    outline: none !important;
    box-shadow: none !important;
    border-color: #cbd5e1 !important;
}

.custom-dropdown-menu .dropdown-item:hover {
    background: #f1f5f9;
    color: #16a34a;
}

.custom-dropdown-menu .dropdown-item.active {
    background: #f0fdf4;
    color: #16a34a;
}

.counter-date-range .date-nav-btn {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid var(--admin-border-soft, #cbd5e1);
    background: #ffffff;
    color: var(--admin-text, #334155);
    cursor: pointer;
    transition: all 0.15s ease;
}

.counter-date-range .date-nav-btn:hover {
    background: var(--admin-hover, #f8fafc);
    border-color: var(--admin-border, #94a3b8);
}

.counter-date-range .date-range-trigger {
    height: 38px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 14px;
    border-radius: 8px;
    border: 1px solid var(--admin-border-soft, #cbd5e1);
    background: #ffffff;
    color: var(--admin-text, #0f172a);
    font-size: 13.5px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}

.counter-date-range .date-range-trigger:hover {
    background: var(--admin-hover, #f8fafc);
    border-color: var(--admin-border, #94a3b8);
}

.counter-date-range .date-range-trigger:focus,
.counter-date-range .date-range-trigger:focus-visible,
.counter-date-range .date-range-trigger.open,
.counter-date-range .date-nav-btn:focus,
.counter-date-range .date-nav-btn:focus-visible,
.counter-date-range .today-btn:focus,
.counter-date-range .today-btn:focus-visible {
    outline: none !important;
    box-shadow: none !important;
    border-color: #cbd5e1 !important;
}

.counter-date-range .today-btn {
    height: 38px;
    padding: 0 14px;
    border-radius: 8px;
    border: 1px solid var(--admin-border-soft, #cbd5e1);
    background: #ffffff;
    color: var(--admin-text, #334155);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
}

.counter-date-range .today-btn:hover {
    background: var(--admin-hover, #f8fafc);
    border-color: var(--admin-border, #94a3b8);
}

.selection-summary-inline {
    display: flex;
    align-items: center;
    gap: 28px;
    padding: 12px 16px;
    border-radius: 8px;
    background: var(--admin-hover, #f8fafc);
    border: 1px solid var(--admin-border-soft, #f1f5f9);
    margin-top: 12px;
    margin-bottom: 12px;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
}

.summary-label {
    color: var(--admin-muted, #64748b);
}

.summary-value {
    color: var(--admin-text, #0f172a);
    font-weight: 500;
}

/* ===== Modern Recurring Tab Styling ===== */
.recurring-panel {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.recurring-panel .form-card,
.owner-counter-page .form-card {
    border: none;
    border-radius: 0;
    background: transparent;
    padding: 0;
    box-shadow: none;
}

.recurring-form-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 32px;
    align-items: start;
    padding: 0;
    border: none;
    border-radius: 0;
    background: transparent;
}

.calendar-range-field,
.recurring-form-grid .calendar-range-field {
    flex: 0 0 auto;
    width: fit-content;
    max-width: 100%;
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: transparent;
    border-radius: 0;
    padding: 0;
    border: none;
    box-shadow: none;
}

.recurring-form-fields {
    flex: 1 1 320px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
}

.recurring-date-planner {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 24px;
    background: transparent;
    border-radius: 0;
    padding: 0;
    border: none;
    box-shadow: none;
}

.calendar-range-field > span {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--admin-text, #0f172a);
}

.recurring-calendar-mode {
    display: flex;
    background: transparent;
    border-radius: 0;
    padding: 0;
    border: none;
    gap: 8px;
}

.recurring-calendar-mode button {
    padding: 6px 14px;
    font-size: 12.5px;
    font-weight: 500;
    border-radius: 6px;
    border: 1px solid var(--admin-border-soft, #cbd5e1);
    background: #ffffff;
    color: var(--admin-muted, #475569);
    cursor: pointer;
    transition: all 0.15s ease;
}

.recurring-calendar-mode button.active {
    background: var(--admin-accent, #10b981);
    border-color: var(--admin-accent, #10b981);
    color: #ffffff;
}

.recurring-helper,
.recurring-schedule-board,
.recurring-payment,
.recurring-form-actions {
    padding: 0;
    border: none;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}

/* ===== Matrix Skeleton Shimmer Loading ===== */
.schedule-loading-box {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 12px 0;
    width: 100%;
}

.skeleton-matrix-wrap {
    display: flex;
    flex-direction: column;
    gap: 8px;
    overflow: hidden;
    width: 100%;
}

.skeleton-matrix-header {
    display: flex;
    gap: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
}

.skeleton-matrix-header .skeleton-pill {
    flex: 1;
    height: 24px;
    border-radius: 6px;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.5s infinite;
}

.skeleton-matrix-row {
    display: flex;
    gap: 8px;
    align-items: center;
}

.skeleton-court-name {
    width: 120px;
    height: 36px;
    border-radius: 6px;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.5s infinite;
    flex-shrink: 0;
}

.skeleton-cell {
    flex: 1;
    height: 36px;
    border-radius: 6px;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.5s infinite;
}

@keyframes skeleton-shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.recurring-form-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.recurring-form-fields label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 500;
    color: var(--admin-muted, #64748b);
}

.recurring-form-fields input[type="text"],
.recurring-form-fields input[type="tel"],
.recurring-form-fields input[type="number"],
.recurring-form-fields select {
    height: 38px;
    border-radius: 8px;
    border: 1px solid var(--admin-border-soft, #cbd5e1);
    padding: 0 12px;
    background: #ffffff;
    font-size: 13.5px;
    color: var(--admin-text, #0f172a);
    transition: border-color 0.15s ease;
}

.recurring-form-fields input:focus,
.recurring-form-fields select:focus {
    outline: none;
    border-color: var(--admin-accent, #10b981);
}

.recurring-date-planner {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 8px;
}

.weekday-planner-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.weekday-planner-head strong {
    font-size: 14px;
    color: var(--admin-text, #0f172a);
}

.weekday-planner-head span {
    font-size: 12.5px;
    color: var(--admin-muted, #64748b);
    display: block;
}

.recurring-date-list {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 6px;
}

.recurring-date-card {
    min-width: 140px;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--admin-border-soft, #cbd5e1);
    background: #ffffff;
    text-align: left;
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.recurring-date-card.active {
    border-color: var(--admin-accent, #10b981);
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15);
}

.recurring-date-card.complete {
    background: #f0fdf4;
    border-color: #86efac;
}

/* ===== Synchronized 1:1 with OwnerVenueClusters.vue ===== */
.cluster-hero-surface {
    background: transparent;
    border: none;
}

.hero-integrated-tabs {
    padding: 0;
    border-top: none !important;
    border-bottom: none !important;
}

.booking-list-mode-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 0;
    background: transparent;
    border: none;
    padding: 0;
    box-shadow: none;
}

.booking-list-mode-tabs button {
    height: 38px;
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 0 16px;
    border-radius: 8px;
    border: 1px solid var(--admin-border, #cbd5e1);
    background: var(--admin-surface, #ffffff);
    color: var(--admin-text, #334155);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.18s ease;
}

.booking-list-mode-tabs button:hover:not(.active) {
    background: var(--admin-hover, #f1f5f9);
    color: var(--admin-text, #0f172a);
}

.booking-list-mode-tabs button.active {
    background: var(--admin-accent, #10b981);
    border-color: var(--admin-accent, #10b981);
    color: #ffffff;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2);
}

.booking-list-filters,
.recurring-list-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px;
    margin-bottom: 20px;
    background: transparent;
    border: none;
    padding: 0;
    box-shadow: none;
    border-radius: 0;
}

.booking-list-filters label,
.recurring-list-filters label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--admin-muted, #475569);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.booking-list-filters input[type="text"],
.booking-list-filters input[type="date"],
.booking-list-filters input[type="search"],
.booking-list-filters select,
.recurring-list-filters select,
.recurring-list-filters input {
    height: 38px;
    border-radius: 8px;
    border: 1px solid var(--admin-border-soft, #cbd5e1);
    padding: 0 12px;
    background: var(--admin-surface, #ffffff);
    font-size: 13.5px;
    color: var(--admin-text, #0f172a);
    transition: all 0.15s ease;
}

.booking-list-filters input:focus,
.booking-list-filters select:focus,
.recurring-list-filters select:focus {
    outline: none;
    border-color: var(--admin-accent, #10b981);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.booking-list-filter-search {
    flex: 1 1 260px;
    min-width: 230px;
}

.booking-list-filters > label:not(.booking-list-filter-search),
.recurring-list-filters > label:not(.booking-list-filter-search) {
    flex: 0 1 180px;
    min-width: 160px;
}

.booking-list-filter-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
}

.booking-list-filter-actions button {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    white-space: nowrap;
}

.recurring-table-card {
    border-radius: 0;
    border: none;
    overflow: hidden;
    background: transparent;
}

.recurring-list-panel .state-card {
    border: none;
    background: transparent;
    color: var(--admin-muted, #64748b);
    text-align: center;
    padding: 40px 16px;
    box-shadow: none;
    font-size: 14px;
}

.recurring-table-card table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}

.recurring-table-card th {
    background: var(--admin-hover, #f8fafc);
    color: var(--admin-muted, #475569);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 12px 16px;
    border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
    text-align: left;
}

.recurring-table-card td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--admin-border-soft, #f1f5f9);
    color: var(--admin-text, #0f172a);
    vertical-align: middle;
}

.recurring-table-card tr:last-child td {
    border-bottom: none;
}

.recurring-table-card tr:hover td {
    background: var(--admin-hover, #f8fafc);
}

.source-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 500;
}

.source-pill.online {
    background: #dbeafe;
    color: #1e40af;
}

.source-pill.counter {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.tone-emerald {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.tone-amber {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.tone-violet {
    background: #f3e8ff;
    color: #6b21a8;
}

.status-badge.tone-rose {
    background: #fee2e2;
    color: #991b1b;
}

.status-badge.tone-slate {
    background: #e2e8f0;
    color: #334155;
}
/* ===== Responsive Multi-Device Optimization (Mobile & Tablet) ===== */
@media (max-width: 768px) {
    .cluster-hero-surface,
    .hero-integrated-tabs {
        padding: 12px 16px 0 16px;
    }

    .profile-section-card {
        padding: 16px;
    }

    .recurring-table-card {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .recurring-table-card table {
        min-width: 680px;
    }
}

/* ===== Time-Row Matrix (transposed: time=rows, courts=cols) ===== */
.time-row-matrix-wrap {
    min-height: 530px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid var(--admin-border-soft, #e2e8f0);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
    margin: 0;
    padding: 0;
}

.time-row-matrix {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin: 0;
    padding: 0;
}

.time-row-matrix .trm-corner {
    position: sticky;
    left: 0;
    z-index: 3;
    min-width: 110px;
    width: 110px;
    padding: 8px 12px;
    background: var(--admin-bg-soft, #f8fafc);
    color: var(--admin-muted, #64748b);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-align: left;
    border-right: 1px solid var(--admin-border-soft, #e2e8f0);
    border-bottom: 2px solid var(--admin-border-soft, #e2e8f0);
}

.time-row-matrix .trm-court-head {
    min-width: 140px;
    padding: 8px 10px;
    background: var(--admin-bg-soft, #f8fafc);
    border-left: 1px solid var(--admin-border-soft, #e2e8f0);
    border-bottom: 2px solid var(--admin-border-soft, #e2e8f0);
    text-align: left;
}

.time-row-matrix .trm-court-head strong {
    display: block;
    color: var(--admin-text, #1e293b);
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.time-row-matrix .trm-court-head span {
    display: block;
    color: var(--admin-muted, #64748b);
    font-size: 11px;
    font-weight: 400;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.time-row-matrix tbody tr {
    border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
    transition: background 0.1s;
}

.time-row-matrix .trm-time-cell {
    position: sticky;
    left: 0;
    z-index: 2;
    min-width: 110px;
    width: 110px;
    padding: 0 12px;
    height: 44px;
    background: var(--admin-surface, #fff);
    color: var(--admin-muted, #64748b);
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
    border-right: 1px solid var(--admin-border-soft, #e2e8f0);
}

.time-row-matrix .trm-slot-cell {
    padding: 0;
    border-left: 1px solid var(--admin-border-soft, #e2e8f0);
}

.time-row-matrix .trm-slot-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 44px;
    padding: 0 6px;
    border: none;
    border-radius: 0;
    background: transparent;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
    font-size: 12px;
    color: var(--admin-primary, #000);
}

.time-row-matrix .trm-slot-btn:not(:disabled):hover {
    background: var(--admin-hover, #f1f5f9);
}

.time-row-matrix .trm-slot-btn:disabled {
    cursor: not-allowed;
}

.time-row-matrix .trm-empty-hint {
    opacity: 0;
    color: var(--admin-primary, #000);
    font-size: 11px;
    font-weight: 400;
    transition: opacity 0.15s;
}

.time-row-matrix tbody tr:hover .trm-slot-btn:not(:disabled) .trm-empty-hint {
    opacity: 0.45;
}

/* Slot state colors — same tokens as slot-matrix */
.time-row-matrix .trm-slot-btn.selected {
    background: var(--admin-primary, #000);
    box-shadow: inset 0 0 0 1px var(--admin-primary, #000);
}

.time-row-matrix .trm-slot-btn.selected span {
    color: #fff;
}

.time-row-matrix .trm-slot-btn.booked-paid    { background: #e2f0e7; }
.time-row-matrix .trm-slot-btn.booked-online  { background: #e5edf3; }
.time-row-matrix .trm-slot-btn.booked-counter { background: #ebe9f1; }
.time-row-matrix .trm-slot-btn.pay-later      { background: #f3eddc; }
.time-row-matrix .trm-slot-btn.overdue        { background: #f3e3e0; }
.time-row-matrix .trm-slot-btn.locked         { background: #e9ecea; cursor: not-allowed; }
.time-row-matrix .trm-slot-btn.viewing        { box-shadow: inset 0 0 0 2px #166534; }
.time-row-matrix .trm-slot-btn.unavailable    { background: #f8fafc; }

/* Dark mode */
:global([data-theme="dark"]) .time-row-matrix-wrap {
    border-color: var(--admin-border-soft);
    background: var(--admin-surface);
}
:global([data-theme="dark"]) .time-row-matrix .trm-corner,
:global([data-theme="dark"]) .time-row-matrix .trm-court-head {
    background: var(--admin-bg-soft);
}
:global([data-theme="dark"]) .time-row-matrix .trm-time-cell {
    background: var(--admin-surface);
}

/* Remove gap/padding between top tabs and section toolbar */
.sg-shell-admin .content-area .owner-counter-page .cluster-profile-surface.standalone {
    gap: 0 !important;
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

.sg-shell-admin .content-area .owner-counter-page .hero-integrated-tabs {
    padding-bottom: 0 !important;
}

.sg-shell-admin .content-area .owner-counter-page .profile-section-card {
    padding: 10px !important;
    gap: 8px !important;
}

.sg-shell-admin .content-area .owner-counter-page .counter-toolbar {
    margin-bottom: 0 !important;
}

.sg-shell-admin .content-area .owner-counter-page .period-row {
    margin: 0 !important;
}

.sg-shell-admin .content-area .owner-counter-page {
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}

:global(html) {
    overflow-y: scroll !important;
    scrollbar-gutter: stable;
}

/* Clean modern Recurring Tab layout fixes */
.recurring-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
    width: 100%;
}

.recurring-detail-box {
    width: 100% !important;
    max-width: 100% !important;
    background: transparent !important;
    border: none !important;
    border-radius: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
    margin-top: 16px;
}

.recurring-detail-box .preview-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 0 !important;
    border-top: none !important;
    border-bottom: none !important;
}

.recurring-detail-box .preview-head span {
    font-size: 11px;
    font-weight: 600;
    color: #16a34a;
    background: #f0fdf4;
    padding: 3px 8px;
    border-radius: 4px;
    text-transform: uppercase;
}

.recurring-detail-box .preview-head strong {
    font-size: 14.5px;
    font-weight: 500;
    color: #0f172a;
}

.recurring-summary-list {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
    gap: 16px 24px !important;
    background: transparent !important;
    border: none !important;
    border-radius: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
}

.recurring-summary-list div {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    gap: 4px !important;
    border-top: none !important;
    border-bottom: none !important;
    padding: 0 !important;
    background: transparent !important;
}

.recurring-summary-list dt {
    font-size: 12px !important;
    color: #64748b !important;
    font-weight: 500 !important;
    border: none !important;
    text-align: center !important;
}

.recurring-summary-list dd {
    font-size: 14px !important;
    color: #0f172a !important;
    font-weight: 500 !important;
    margin: 0 !important;
    border: none !important;
    text-align: center !important;
}

.recurring-payment-list {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 12px !important;
    margin-top: 10px !important;
}

.recurring-payment-list .payment-card {
    flex: 1 !important;
    min-width: 180px !important;
    padding: 12px 16px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    background: #ffffff !important;
    cursor: pointer !important;
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    font-size: 13.5px !important;
    font-weight: 500 !important;
    color: #0f172a !important;
    transition: all 0.15s ease !important;
}

.recurring-payment-list .payment-card.active {
    background: #f0fdf4 !important;
    border-color: #16a34a !important;
    color: #16a34a !important;
}

.recurring-collect-actions {
    display: flex !important;
    gap: 12px !important;
    margin-top: 12px !important;
}

.recurring-collect-actions button {
    flex: 1 !important;
    height: 38px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
    background: #ffffff !important;
    color: #0f172a !important;
    font-size: 13.5px !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
}

.recurring-collect-actions button.active {
    background: #f0fdf4 !important;
    border-color: #16a34a !important;
    color: #16a34a !important;
}

.recurring-form-actions {
    margin-top: 18px !important;
    display: flex !important;
    justify-content: flex-end !important;
}

.recurring-form-actions .primary-btn {
    height: 40px !important;
    padding: 0 24px !important;
    border-radius: 6px !important;
    background: #16a34a !important;
    color: #ffffff !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    border: none !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    transition: background 0.15s ease !important;
}

.recurring-form-actions .primary-btn:hover {
    background: #15803d !important;
}

.recurring-form-actions .primary-btn:disabled {
    background: #94a3b8 !important;
    cursor: not-allowed !important;
}
</style>

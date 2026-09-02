<template>
  <div class="cbw-page">
    <PublicNavbar />

    <main class="cbw-main">


      <!-- PROGRESS STEPS -->
      <div class="cbw-steps">
        <div
          v-for="step in steps"
          :key="step.id"
          class="cbw-step"
          :class="{ 'is-active': activeStep === step.id, 'is-done': activeStep > step.id }"
        >
          <div class="cbw-step-num">
            <template v-if="activeStep > step.id">✓</template>
            <template v-else>{{ step.id }}</template>
          </div>
          <div class="cbw-step-info">
            <span class="cbw-step-label">{{ step.label }}</span>
            <span class="cbw-step-hint">{{ step.hint }}</span>
          </div>
        </div>
      </div>

      <!-- MODE NAVIGATION TABS (PHẲNG, TỐI GIẢN) -->
      <div class="cbw-mode-nav">
        <button
          type="button"
          class="cbw-mode-tab"
          :class="{ 'is-active': bookingMode === 'single' }"
          @click="setBookingMode('single')"
        >
          <span>Đặt theo ngày (Lịch lẻ)</span>
        </button>
      </div>

      <!-- INITIAL LOADING STATE -->
      <div v-if="initialLoading" class="cbw-loading">
        <div class="cbw-spinner"></div>
        <span>Đang chuẩn bị bảng lịch sân...</span>
      </div>

      <!-- MAIN WORKSPACE LAYOUT: SINGLE BOOKING MODE -->
      <div v-show="!initialLoading && bookingMode === 'single'" class="cbw-workspace" :class="{ 'is-fullwidth': !selectedSlotKeys.length }">
        <!-- LEFT COLUMN: TIME-ROW MATRIX -->
        <div class="cbw-schedule-panel">
          <!-- CLEAN COMPACT TOOLBAR -->
          <div class="cbw-toolbar">
            <!-- DATE SELECTOR (CUSTOM MINI CALENDAR PICKER) -->
            <div class="cbw-date-selector">
              <span class="cbw-date-lbl">Ngày chơi:</span>
              <AdminDatePicker
                :model-value="bookingDate"
                placeholder="Chọn ngày chơi"
                @update:model-value="onCustomDateSelect"
              />
              <div class="cbw-quick-dates">
                <button
                  type="button"
                  class="cbw-quick-btn"
                  :class="{ 'is-active': bookingDate === today }"
                  @click="setDateQuick(today)"
                >Hôm nay</button>
                <button
                  type="button"
                  class="cbw-quick-btn"
                  :class="{ 'is-active': bookingDate === tomorrow }"
                  @click="setDateQuick(tomorrow)"
                >Ngày mai</button>
                <button
                  type="button"
                  class="cbw-quick-btn"
                  :class="{ 'is-active': bookingDate === dayAfterTomorrow }"
                  @click="setDateQuick(dayAfterTomorrow)"
                >Ngày kia</button>
              </div>
            </div>

            <!-- CA CHƠI TABS (SÁNG / CHIỀU / TỐI) -->
            <div v-if="dynamicTimePeriods.length" class="cbw-period-tabs">
              <button
                v-for="period in dynamicTimePeriods"
                :key="period.key"
                type="button"
                class="cbw-period-btn"
                :class="{ 'is-active': activePeriodKey === period.key }"
                @click="activePeriod = period.key"
              >
                <span>{{ period.label }}</span>
                <span class="cbw-period-range">({{ period.range }})</span>
              </button>
            </div>
          </div>

          <!-- SCHEDULE MATRIX (TIME-ROW FORMAT: ROWS = TIME SLOTS, COLS = COURTS) -->
          <div v-if="scheduleLoading" class="cbw-skel">
            <span v-for="i in 20" :key="i"></span>
          </div>

          <div v-else-if="scheduleError" class="cbw-state-msg cbw-state-msg--error">
            {{ scheduleError }}
          </div>

          <div v-else-if="bookingBlocked" class="cbw-access-blocked" role="alert">
            <strong>{{ bookingAccess.title }}</strong>
            <span>{{ bookingAccess.message }}</span>
          </div>

          <div v-else-if="!courts.length" class="cbw-state-msg">
            Không có sân nào phù hợp với bộ lọc hiện tại.
          </div>

          <div v-else class="cbw-matrix-wrap">
            <table class="cbw-matrix">
              <thead>
                <tr>
                  <th class="cbw-th-corner">KHUNG GIỜ</th>
                  <th v-for="court in courts" :key="court.id" class="cbw-th-court" :class="{ 'is-maintenance': court.status === 'maintenance' }">
                    <div class="cbw-court-header-main">
                      <strong>{{ court.name }}</strong>
                      <span v-if="court.status === 'maintenance'" class="cbw-court-badge-maintenance">Bảo trì</span>
                    </div>
                    <span>{{ court.court_type?.name || 'Sân thể thao' }}</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!activePeriodSlots.length">
                  <td :colspan="courts.length + 1" class="cbw-td-empty">
                    Ca này nằm ngoài giờ hoạt động của sân hoặc không có khung giờ chơi.
                  </td>
                </tr>

                <tr v-else v-for="slotInfo in activePeriodSlots" :key="slotInfo.slot.start_time">
                  <!-- TIME COLUMN (STICKY LEFT) -->
                  <td class="cbw-td-time">
                    {{ shortTime(slotInfo.slot.start_time) }} – {{ shortTime(slotInfo.slot.end_time) }}
                  </td>

                  <!-- COURT COLUMNS (MERGED WITH ROWSPAN) -->
                  <template v-for="court in courts" :key="`${court.id}-${slotInfo.slot.start_time}`">
                    <td
                      v-if="!isSlotCoveredByMerge(court.id, slotInfo)"
                      :rowspan="getSlotRowspan(court.id, slotInfo)"
                      class="cbw-td-slot"
                      :class="{
                        'is-merged-selected': isSelectedSlot(court.id, slotInfo.slot) && getSlotRowspan(court.id, slotInfo) > 1,
                        'is-single-selected': isSelectedSlot(court.id, slotInfo.slot) && getSlotRowspan(court.id, slotInfo) === 1,
                      }"
                    >
                      <!-- ĐÃ CHỌN (GỘP THÀNH 1 Ô DUY NHẤT CAO TRỌN VẸN CẢ DẢI SLOT) -->
                      <button
                        v-if="isSelectedSlot(court.id, slotInfo.slot)"
                        type="button"
                        class="cbw-slot-btn is-selected"
                        :class="{ 'is-merged-btn': getSlotRowspan(court.id, slotInfo) > 1 }"
                        :title="mergedSlotTitle(court, slotInfo)"
                        @click="toggleMergedSlot(court, slotInfo)"
                      >
                        <div v-if="getSlotRowspan(court.id, slotInfo) > 1" class="cbw-merged-slot-card">
                          <span class="cbw-merged-time">
                            {{ shortTime(slotInfo.slot.start_time) }} – {{ shortTime(getMergedEndSlot(court.id, slotInfo).end_time) }}
                          </span>
                          <span class="cbw-merged-badge">
                            Đã chọn ({{ formatDurationMinutes(getMergedDuration(court.id, slotInfo)) }})
                          </span>
                          <span class="cbw-merged-unselect-text">
                            ✕ Hủy chọn
                          </span>
                        </div>
                        <div v-else class="cbw-single-slot-card">
                          <span class="cbw-single-time">
                            {{ shortTime(slotInfo.slot.start_time) }} – {{ shortTime(slotInfo.slot.end_time) }}
                          </span>
                          <span class="cbw-slot-label">Đã chọn</span>
                          <span class="cbw-single-unselect-text">✕ Hủy</span>
                        </div>
                      </button>

                      <!-- TRẠNG THÁI KHÁC (CHƯA CHỌN / TRỐNG / ĐÃ ĐẶT / KHÓA) -->
                      <div
                        v-else
                        class="cbw-slot-cell-wrapper"
                      >
                        <button
                          type="button"
                          class="cbw-slot-btn"
                          :class="slotClasses(court.id, slotInfo.slot)"
                          :disabled="slotDisabled(court.id, slotInfo.slot)"
                          @click="toggleSlot(court, slotInfo.index)"
                        >
                          <span v-if="!slotDisabled(court.id, slotInfo.slot)" class="cbw-slot-price">
                            {{ compactMoney(slotStatus(court.id, slotInfo.slot)?.price) }}
                          </span>
                          <span v-else class="cbw-slot-disabled-text">
                            {{ slotDisabledLabel(court.id, slotInfo.slot) }}
                          </span>
                        </button>

                        <!-- BONG BÓNG LÝ DO KHÓA KHI HOVER -->
                        <div
                          v-if="isSlotLockedOrMaintenance(court.id, slotInfo.slot)"
                          class="cbw-bubble-tooltip"
                          :class="{ 'drop-down': slotInfo.index <= 1 }"
                          role="tooltip"
                        >
                          <div class="cbw-bubble-arrow"></div>
                          <div class="cbw-bubble-header">
                            <span class="cbw-bubble-badge" :class="getLockBadgeClass(court.id, slotInfo.slot)">
                              {{ getLockBadgeLabel(court.id, slotInfo.slot) }}
                            </span>
                            <span class="cbw-bubble-time">
                              {{ shortTime(slotInfo.slot.start_time) }} – {{ shortTime(slotInfo.slot.end_time) }}
                            </span>
                          </div>
                          <div class="cbw-bubble-body">
                            <span class="cbw-bubble-label">Lý do:</span>
                            <span class="cbw-bubble-reason">{{ getLockReasonText(court.id, slotInfo.slot) }}</span>
                          </div>
                        </div>
                      </div>
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="!scheduleLoading && courts.length" class="cbw-status-legend" aria-label="Chú thích trạng thái khung giờ">
            <span><i class="cbw-legend-dot cbw-legend-dot--available"></i>Trống</span>
            <span><i class="cbw-legend-dot cbw-legend-dot--selected"></i>Đã chọn</span>
            <span><i class="cbw-legend-dot cbw-legend-dot--booked"></i>Đã đặt</span>
            <span><i class="cbw-legend-dot cbw-legend-dot--pending"></i>Chờ xác nhận</span>
            <span><i class="cbw-legend-dot cbw-legend-dot--payment"></i>Chờ thanh toán</span>
            <span><i class="cbw-legend-dot cbw-legend-dot--locked"></i>Đang khóa</span>
            <span><i class="cbw-legend-dot cbw-legend-dot--maintenance"></i>Bảo trì</span>
            <span><i class="cbw-legend-dot cbw-legend-dot--past"></i>Đã qua / quá sát giờ</span>
          </div>

          <!-- SELECTION FEEDBACK BAR -->
          <div v-if="selectedSlotKeys.length || validationError" class="cbw-sel-bar" :class="{ 'cbw-sel-bar--error': validationError }">
            <div class="cbw-sel-info">
              <strong>{{ validationError ? 'Lưu ý điều chỉnh:' : selectionTitle }}</strong>
              <span>{{ validationError || selectionHint }}</span>
            </div>
            <div v-if="selectedSlotKeys.length" class="cbw-sel-actions">
              <button type="button" class="cbw-clear-btn" @click="clearSelection">Bỏ chọn tất cả</button>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: BOOKING SUMMARY & PAYMENT PANEL -->
        <div v-if="selectedSlotKeys.length" class="cbw-summary-panel" ref="summaryPanel" tabindex="-1">
          <div class="cbw-summary-header">
            <span class="cbw-summary-label">THÔNG TIN ĐẶT SÂN</span>
            <h2 class="cbw-summary-title">{{ summaryTitle }}</h2>
            <p class="cbw-summary-sub">{{ summarySubtitle }}</p>
          </div>

          <div class="cbw-divider"></div>

          <!-- SUMMARY FACTS -->
          <div class="cbw-facts">
            <div class="cbw-fact-row">
              <span>Cụm sân:</span>
              <strong>{{ currentCluster?.name || '-' }}</strong>
            </div>
            <div class="cbw-fact-row">
              <span>Ngày chơi:</span>
              <strong>{{ formatDate(bookingDate) }}</strong>
            </div>
            <div class="cbw-fact-row">
              <span>Khung giờ:</span>
              <strong>{{ selectedTimeText }}</strong>
            </div>
            <div class="cbw-fact-row">
              <span>Tổng thời lượng:</span>
              <strong>{{ durationText }}</strong>
            </div>
          </div>

          <!-- PRICE DETAILS LIST -->
          <template v-if="selectedPriceRows.length">
            <div class="cbw-divider"></div>
            <div class="cbw-price-rows">
              <div class="cbw-price-head">Chi tiết tiền sân:</div>
              <div v-for="row in selectedPriceRows" :key="`${row.courtId}-${row.startTime}`" class="cbw-price-row">
                <span>{{ row.courtName }} ({{ shortTime(row.startTime) }} - {{ shortTime(row.endTime) }})</span>
                <strong>{{ money(row.amount) }}</strong>
              </div>
            </div>
          </template>

          <!-- VOUCHER & DISCOUNT SECTION -->
          <template v-if="selectedSlotKeys.length && !validationError">
            <div class="cbw-divider"></div>
            <div class="cbw-discount-section">
              <div v-if="membershipDiscount > 0" class="cbw-discount-row">
                <span>Ưu đãi thành viên:</span>
                <strong class="cbw-discount-val">-{{ money(membershipDiscount) }}</strong>
              </div>

              <template v-if="isLoggedIn">
                <button type="button" class="cbw-voucher-btn" @click="voucherOpen = !voucherOpen">
                  <span>{{ selectedVoucherCount ? `${selectedVoucherCount} voucher đã chọn` : 'Chọn Voucher giảm giá' }}</span>
                  <strong v-if="voucherDiscount" class="cbw-discount-val">-{{ money(voucherDiscount) }}</strong>
                  <span v-else class="cbw-voucher-count">{{ eligibleVouchers.length }} mã có sẵn</span>
                </button>
                <p v-if="voucherNotice" class="cbw-voucher-notice">{{ voucherNotice }}</p>

                <div v-if="voucherOpen && eligibleVouchers.length" class="cbw-voucher-list">
                  <button
                    v-for="v in eligibleVouchers"
                    :key="v.id"
                    type="button"
                    class="cbw-voucher-item"
                    :class="{ 'is-active': voucherSelected(v) }"
                    @click="toggleVoucher(v)"
                  >
                    <div>
                      <strong>{{ v.code }}</strong>
                      <span>{{ v.name }}</span>
                    </div>
                    <strong>-{{ money(voucherValue(v, voucherBaseAmount(v))) }}</strong>
                  </button>
                </div>
                <p v-if="voucherOpen && !eligibleVouchers.length" class="cbw-voucher-empty">Không có voucher nào phù hợp cho đơn này.</p>
              </template>
            </div>

            <!-- PAYMENT OPTIONS -->
            <div class="cbw-divider"></div>
            <div class="cbw-payment-section">
              <p class="cbw-payment-title">Hình thức thanh toán</p>

              <p v-if="isLoggedIn && walletBalance !== null" class="cbw-wallet-bal">
                Số dư Ví SportGo: <strong>{{ money(walletBalance) }}</strong>
              </p>

              <div class="cbw-payment-opts">
                <label
                  v-for="opt in paymentOptions"
                  :key="opt.value"
                  class="cbw-payment-opt"
                  :class="{ 'is-active': paymentOption === opt.value, 'is-disabled': opt.disabled }"
                >
                  <input type="radio" v-model="paymentOption" :value="opt.value" :disabled="opt.disabled" />
                  <div>
                    <strong>{{ opt.label }}</strong>
                    <span>{{ opt.hint }}</span>
                  </div>
                </label>
              </div>
            </div>
          </template>

          <!-- TOTAL & SUBMIT BUTTON -->
          <div class="cbw-divider"></div>

          <div class="cbw-total-row">
            <span>Tổng số tiền:</span>
            <strong class="cbw-total-val">{{ money(total) }}</strong>
          </div>

          <div v-if="selectedSlotKeys.length && paymentOption !== 'no_prepay'" class="cbw-required-row">
            <span>Cần thanh toán ngay:</span>
            <strong>{{ money(requiredAmount) }}</strong>
          </div>

          <p v-if="submitError" class="cbw-submit-error">{{ submitError }}</p>

          <button
            type="button"
            class="cbw-submit-btn"
            :disabled="!canSubmit || submitting"
            @click="submit"
          >
            <template v-if="submitting">Đang xử lý tạo đơn...</template>
            <template v-else-if="!isLoggedIn && selectedSlotKeys.length">Đăng nhập để xác nhận đặt sân</template>
            <template v-else>Xác nhận đặt sân ngay</template>
          </button>

          <p v-if="selectedSlotKeys.length" class="cbw-hold-note">
            <template v-if="['no_prepay', 'deposit'].includes(paymentOption)">
              * Booking sẽ chờ chủ sân duyệt tối đa 30 phút, nhưng có thể ngắn hơn nếu gần giờ bắt đầu. Nếu hết hạn chưa được duyệt, booking tự hủy và sân được mở lại.
            </template>
            <template v-else-if="paymentOption !== 'no_prepay'">
              * Sân sẽ được giữ tạm thời {{ config.slot_hold_minutes || 20 }} phút để bạn hoàn tất thanh toán.
            </template>
          </p>
        </div>
      </div>

      <!-- MAIN WORKSPACE LAYOUT: RECURRING BOOKING MODE (FLAT, CLEAN, WHITE) -->
      <div v-show="!initialLoading && bookingMode === 'recurring'" class="cbw-workspace cbw-workspace--recurring">
        <!-- LEFT COLUMN: RECURRING PLANNER (FLAT UNIFIED SECTION) -->
        <div class="cbw-rec-planner">
          <!-- SECTION 1: KHOẢNG THỜI GIAN -->
          <div class="cbw-rec-sec">
            <div class="cbw-rec-sec-head">
              <h3 class="cbw-rec-sec-title">1. Khoảng thời gian chơi</h3>
              <div class="cbw-rec-quick-presets">
                <button type="button" class="cbw-rec-preset-btn" @click="selectRecurringPreset(1)">1 tháng</button>
                <button type="button" class="cbw-rec-preset-btn" @click="selectRecurringPreset(2)">2 tháng</button>
                <button type="button" class="cbw-rec-preset-btn" @click="selectRecurringPreset(3)">3 tháng</button>
              </div>
            </div>

            <div class="cbw-rec-form-row">
              <div class="cbw-rec-form-group">
                <label class="cbw-rec-form-lbl">Từ ngày:</label>
                <AdminDatePicker
                  :model-value="recurringForm.recurring_start_date"
                  placeholder="Từ ngày"
                  @update:model-value="val => { recurringForm.recurring_start_date = val; recurringPreviewResult = null; }"
                />
              </div>

              <div class="cbw-rec-form-group">
                <label class="cbw-rec-form-lbl">Đến ngày:</label>
                <AdminDatePicker
                  :model-value="recurringForm.recurring_end_date"
                  placeholder="Đến ngày"
                  @update:model-value="val => { recurringForm.recurring_end_date = val; recurringPreviewResult = null; }"
                />
              </div>

              <div class="cbw-rec-form-group">
                <label class="cbw-rec-form-lbl">Kiểu chu kỳ:</label>
                <ClientCustomSelect
                  v-model="recurringForm.recurrence_type"
                  :options="recurrenceTypeOptions"
                  @change="recurringPreviewResult = null"
                />
              </div>
            </div>
          </div>

          <!-- SECTION 2: CHỌN THỨ TRONG TUẦN & KHUNG GIỜ -->
          <div v-if="recurringForm.recurrence_type === 'weekly'" class="cbw-rec-sec">
            <div class="cbw-rec-sec-head">
              <h3 class="cbw-rec-sec-title">2. Chọn các thứ trong tuần & Khung giờ</h3>
            </div>

            <!-- WEEKDAYS FLAT LIST -->
            <div class="cbw-weekday-row">
              <button
                v-for="d in weekDaysList"
                :key="d.value"
                type="button"
                class="cbw-day-box"
                :class="{
                  'is-selected': isRecurringWeekdaySelected(d.value),
                  'is-active': recurringActiveWeekday === d.value
                }"
                @click="toggleRecurringWeekday(d.value)"
              >
                <span class="cbw-day-box-short">{{ d.short }}</span>
                <span class="cbw-day-box-lbl">{{ d.label }}</span>
                <span v-if="isRecurringWeekdaySelected(d.value)" class="cbw-day-box-status">
                  {{ getWeekdayScheduleSummary(d.value) }}
                </span>
                <span v-else class="cbw-day-box-empty">Chưa chọn</span>
              </button>
            </div>

            <!-- SCHEDULE ROW FOR CURRENT SELECTED WEEKDAY -->
            <div v-if="isRecurringWeekdaySelected(recurringActiveWeekday)" class="cbw-day-sched-block">
              <div class="cbw-day-sched-head">
                <span class="cbw-day-sched-title">
                  Khung giờ & Sân cho <strong>{{ getWeekdayLabel(recurringActiveWeekday) }}</strong>:
                </span>
                <button type="button" class="cbw-copy-link" @click="copyScheduleToAllSelectedWeekdays">
                  Áp dụng khung giờ & sân này cho tất cả thứ đã chọn
                </button>
              </div>

              <div class="cbw-rec-form-row">
                <div class="cbw-rec-form-group">
                  <label class="cbw-rec-form-lbl">Bắt đầu:</label>
                  <ClientCustomSelect
                    v-model="getWeekdaySchedule(recurringActiveWeekday).start_time"
                    :options="formattedTimeOptions"
                    icon="clock"
                    placeholder="Chọn giờ"
                    @change="recurringPreviewResult = null"
                  />
                </div>

                <div class="cbw-rec-form-group">
                  <label class="cbw-rec-form-lbl">Kết thúc:</label>
                  <ClientCustomSelect
                    v-model="getWeekdaySchedule(recurringActiveWeekday).end_time"
                    :options="formattedTimeOptions"
                    icon="clock"
                    placeholder="Chọn giờ"
                    @change="recurringPreviewResult = null"
                  />
                </div>

                <div class="cbw-rec-form-group">
                  <label class="cbw-rec-form-lbl">Sân:</label>
                  <ClientCustomSelect
                    v-model="getWeekdaySchedule(recurringActiveWeekday).venue_court_id"
                    :options="courtOptions"
                    icon="court"
                    placeholder="Chọn sân"
                    @change="recurringPreviewResult = null"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- SECTION 3: KIỂM TRA LỊCH TRỐNG & BUỔI CHƠI -->
          <div class="cbw-rec-sec cbw-rec-sec--last">
            <div class="cbw-rec-sec-head">
              <h3 class="cbw-rec-sec-title">3. Kiểm tra tính khả dụng của lịch</h3>
            </div>

            <div class="cbw-rec-check-btn-wrap">
              <button
                type="button"
                class="cbw-rec-btn-action"
                :disabled="recurringPreviewLoading || !recurringCanSubmit"
                @click="runPreviewRecurring"
              >
                <template v-if="recurringPreviewLoading">Đang kiểm tra các buổi trong chu kỳ...</template>
                <template v-else>Kiểm tra lịch & Xem trước buổi chơi</template>
              </button>
            </div>

            <p v-if="recurringPreviewError" class="cbw-rec-msg-error">{{ recurringPreviewError }}</p>

            <!-- PREVIEW STATUS & CONFLICTS -->
            <div v-if="recurringPreviewResult" class="cbw-rec-result-area">
              <div v-if="!recurringPreviewResult.conflict_count" class="cbw-rec-msg-success">
                ✓ Tất cả <strong>{{ recurringPreviewResult.total_dates }} buổi</strong> trong chu kỳ đều sẵn sàng và không bị trùng lịch.
              </div>

              <div v-else class="cbw-rec-msg-warn">
                <span>⚠️ Có <strong>{{ recurringPreviewResult.conflict_count }} buổi</strong> bị trùng hoặc sân bận. Vui lòng chọn cách xử lý bên dưới:</span>
              </div>

              <!-- CONFLICT TABLE -->
              <div v-if="recurringPreviewResult.conflicts && recurringPreviewResult.conflicts.length" class="cbw-conflict-table">
                <div v-for="cf in recurringPreviewResult.conflicts" :key="cf.date" class="cbw-conflict-row">
                  <div class="cbw-conflict-date">
                    <strong>{{ formatDate(cf.date) }}</strong>
                    <span>({{ cf.start_time ? shortTime(cf.start_time) : '' }} – {{ cf.end_time ? shortTime(cf.end_time) : '' }})</span>
                  </div>

                  <div class="cbw-conflict-controls">
                    <label class="cbw-conflict-radio">
                      <input
                        type="radio"
                        :name="`cf-${cf.date}`"
                        value="skip"
                        :checked="getConflictAction(cf.date) === 'skip'"
                        @change="setConflictOverride(cf.date, 'skip')"
                      />
                      <span>Bỏ qua ngày này</span>
                    </label>

                    <label v-if="cf.alternatives && cf.alternatives.length" class="cbw-conflict-radio">
                      <input
                        type="radio"
                        :name="`cf-${cf.date}`"
                        value="switch"
                        :checked="getConflictAction(cf.date) === 'switch'"
                        @change="setConflictOverride(cf.date, 'switch', cf.alternatives[0]?.id)"
                      />
                      <span>Đổi sang sân:</span>
                      <select
                        class="cbw-conflict-select"
                        :value="getConflictAltCourtId(cf.date) || cf.alternatives[0]?.id"
                        @change="e => setConflictOverride(cf.date, 'switch', Number(e.target.value))"
                      >
                        <option v-for="alt in cf.alternatives" :key="alt.id" :value="alt.id">{{ alt.name }}</option>
                      </select>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: RECURRING SUMMARY & CHECKOUT -->
        <div class="cbw-summary-panel" ref="summaryPanel" tabindex="-1">
          <div class="cbw-summary-header">
            <span class="cbw-summary-label">THÔNG TIN ĐẶT SÂN CỐ ĐỊNH</span>
            <h2 class="cbw-summary-title">{{ currentCluster?.name || 'Cụm sân' }}</h2>
            <p class="cbw-summary-sub">Gói đặt cố định theo chu kỳ</p>
          </div>

          <div class="cbw-divider"></div>

          <!-- SUMMARY FACTS -->
          <div class="cbw-facts">
            <div class="cbw-fact-row">
              <span>Cụm sân:</span>
              <strong>{{ currentCluster?.name || '-' }}</strong>
            </div>
            <div class="cbw-fact-row">
              <span>Khoảng ngày:</span>
              <strong>{{ formatDate(recurringForm.recurring_start_date) }} – {{ formatDate(recurringForm.recurring_end_date) }}</strong>
            </div>
            <div class="cbw-fact-row">
              <span>Chu kỳ:</span>
              <strong>{{ recurringForm.recurrence_type === 'weekly' ? 'Hàng tuần' : 'Hàng ngày' }} ({{ recurringForm.recurrence_days_of_week?.length || 0 }} buổi/tuần)</strong>
            </div>
            <div class="cbw-fact-row">
              <span>Tổng số buổi:</span>
              <strong style="color: #15803d; font-size: 15px;">{{ recurringEstimatedSessions }} buổi</strong>
            </div>
          </div>

          <!-- PAYMENT OPTIONS -->
          <div class="cbw-divider"></div>
          <div class="cbw-payment-section">
            <p class="cbw-payment-title">Hình thức thanh toán</p>
            <div class="cbw-payment-opts">
              <label class="cbw-payment-opt" :class="{ 'is-active': recurringForm.payment_option === 'no_prepay' }">
                <input type="radio" v-model="recurringForm.payment_option" value="no_prepay" />
                <div>
                  <strong>Thanh toán tại sân</strong>
                  <span>Trả theo từng buổi hoặc thanh toán tại quầy</span>
                </div>
              </label>
              <label class="cbw-payment-opt" :class="{ 'is-active': recurringForm.payment_option === 'full_payment' }">
                <input type="radio" v-model="recurringForm.payment_option" value="full_payment" />
                <div>
                  <strong>Thanh toán Online (QR SePay)</strong>
                  <span>Giữ trọn vẹn toàn bộ các buổi trong chu kỳ</span>
                </div>
              </label>
            </div>
          </div>

          <!-- TOTAL & SUBMIT BUTTON -->
          <div class="cbw-divider"></div>

          <div class="cbw-total-row">
            <span>Tạm tính chu kỳ:</span>
            <strong class="cbw-total-val">{{ money(recurringEstimatedTotal) }}</strong>
          </div>

          <p v-if="recurringSubmitError" class="cbw-submit-error">{{ recurringSubmitError }}</p>

          <button
            type="button"
            class="cbw-submit-btn"
            :disabled="!recurringCanSubmit || recurringSubmitting"
            @click="submitRecurring"
          >
            <template v-if="recurringSubmitting">Đang xử lý tạo lịch cố định...</template>
            <template v-else-if="!isLoggedIn">Đăng nhập để xác nhận đặt sân</template>
            <template v-else>Xác nhận đặt lịch cố định</template>
          </button>
        </div>
      </div>
    </main>
  </div>
</template>

<script>
import PublicNavbar from "../../../components/PublicNavbar.vue";
import AdminDatePicker from "../../../components/AdminDatePicker.vue";
import ClientCustomSelect from "../../../components/ClientCustomSelect.vue";
import { bookingService } from "../../../services/bookingService.js";
import { getAuth } from "../../../stores/auth.js";
import echo from "../../../echo.js";
import { addCalendarDays, addCalendarMonths, businessDateString, businessMinutes } from "../../../utils/businessTime.js";
import { useToast } from "vue-toastification";

export default {
  name: "ClientBookingWorkspace",
  components: { PublicNavbar, AdminDatePicker, ClientCustomSelect },
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      steps: [
        { id: 1, label: "Chọn sân & lịch", hint: "Xem ô giờ trống trên ma trận" },
        { id: 2, label: "Kiểm tra giá", hint: "Tính giá & mã ưu đãi" },
        { id: 3, label: "Xác nhận đặt", hint: "Chọn cách thức thanh toán" },
      ],
      clusters: [],
      clusterId: "",
      clusterLocked: false,
      bookingAccess: { can_book: true, code: "available", title: "", message: "" },
      restrictionNoticeKey: "",
      courtTypeId: "",
      bookingDate: businessDateString(),
      initialLoading: true,
      scheduleLoading: false,
      scheduleRequestId: 0,
      scheduleError: "",
      slots: [],
      courts: [],
      statuses: [],
      operatingHours: null,
      activePeriod: "",
      selectedSlotKeys: [],
      selectionError: "",
      availabilityRequestId: 0,
      checking: false,
      available: true,
      preview: null,
      paymentOption: "no_prepay",
      walletBalance: null,
      _venueVouchers: [],
      _vipVouchers: [],
      selectedVenueVoucherId: "",
      selectedVipVoucherId: "",
      voucherOpen: false,
      voucherNotice: "",
      submitting: false,
      submitError: "",
      routeSelection: null,
      lastScheduleFetchedAt: 0,
      scheduleSyncInterval: null,
      echoChannelName: null,
      bookingMode: "single",
      recurringForm: {
        recurring_start_date: "",
        recurring_end_date: "",
        recurrence_type: "weekly",
        recurrence_interval: 1,
        recurrence_days_of_week: [1, 3, 5],
        venue_court_id: null,
        start_time: "18:00:00",
        end_time: "20:00:00",
        weekday_schedules: {},
        conflict_resolution: "abort",
        conflict_overrides: {},
        payment_option: "no_prepay",
      },
      recurringActiveWeekday: 1,
      recurringPreviewLoading: false,
      recurringPreviewResult: null,
      recurringPreviewError: "",
      recurringSubmitting: false,
      recurringSubmitError: "",
    };
  },
  computed: {
    weekDaysList() {
      return [
        { value: 1, label: "Thứ Hai", short: "T2" },
        { value: 2, label: "Thứ Ba", short: "T3" },
        { value: 3, label: "Thứ Tư", short: "T4" },
        { value: 4, label: "Thứ Năm", short: "T5" },
        { value: 5, label: "Thứ Sáu", short: "T6" },
        { value: 6, label: "Thứ Bảy", short: "T7" },
        { value: 0, label: "Chủ Nhật", short: "CN" },
      ];
    },
    timeOptions() {
      const list = [];
      for (let h = 5; h <= 23; h++) {
        const hh = String(h).padStart(2, "0");
        list.push(`${hh}:00:00`);
        list.push(`${hh}:30:00`);
      }
      list.push("24:00:00");
      return list;
    },
    formattedTimeOptions() {
      return this.timeOptions.map(t => ({
        value: t,
        label: this.shortTime(t),
      }));
    },
    courtOptions() {
      return this.courts.map(c => {
        const rawType = c.court_type?.name || "";
        const cleanSub = rawType ? rawType.replace(/\s*\((.*?)\)/g, " · $1") : "";
        return {
          value: c.id,
          label: c.name,
          sublabel: cleanSub || rawType || null,
        };
      });
    },
    recurrenceTypeOptions() {
      return [
        { value: "weekly", label: "Hàng tuần (Các thứ cố định)" },
        { value: "daily", label: "Hàng ngày liên tục" },
      ];
    },
    recurringActiveCourt() {
      const courtId = this.recurringForm.venue_court_id || this.courts[0]?.id;
      return this.courts.find(c => String(c.id) === String(courtId)) || this.courts[0] || null;
    },
    recurringEstimatedSessions() {
      if (this.recurringPreviewResult?.total_dates !== undefined) {
        let skipped = 0;
        if (this.recurringPreviewResult.conflicts?.length) {
          this.recurringPreviewResult.conflicts.forEach(cf => {
            const action = this.getConflictAction(cf.date);
            if (action === "skip") skipped++;
          });
        }
        return Math.max(this.recurringPreviewResult.total_dates - skipped, 0);
      }
      if (!this.recurringForm.recurring_start_date || !this.recurringForm.recurring_end_date) return 0;
      const s = new Date(this.recurringForm.recurring_start_date);
      const e = new Date(this.recurringForm.recurring_end_date);
      if (e < s) return 0;
      const daysDiff = Math.floor((e - s) / (1000 * 60 * 60 * 24)) + 1;
      const weeks = Math.max(daysDiff / 7, 1);
      return Math.round(weeks * (this.recurringForm.recurrence_days_of_week?.length || 1));
    },
    recurringSingleSessionAmount() {
      const sched = this.recurringForm.weekday_schedules[this.recurringActiveWeekday] || {
        venue_court_id: this.recurringForm.venue_court_id || this.courts[0]?.id,
        start_time: this.recurringForm.start_time,
        end_time: this.recurringForm.end_time,
      };
      const durationH = Math.max((this.minutes(sched.end_time) - this.minutes(sched.start_time)) / 60, 0.5);
      const court = this.courts.find(c => String(c.id) === String(sched.venue_court_id)) || this.courts[0];
      const rate = Number(court?.price_per_hour || court?.base_price || 120000);
      return Math.round(rate * durationH);
    },
    recurringEstimatedTotal() {
      return Math.round(this.recurringSingleSessionAmount * this.recurringEstimatedSessions);
    },
    recurringCanSubmit() {
      if (this.bookingBlocked) return false;
      if (!this.recurringForm.recurring_start_date || !this.recurringForm.recurring_end_date) return false;
      if (this.recurringForm.recurrence_type === "weekly" && !this.recurringForm.recurrence_days_of_week?.length) return false;
      if (this.recurringPreviewResult?.conflicts?.length && this.recurringForm.conflict_resolution === "abort") return false;
      return true;
    },
    today() {
      return businessDateString();
    },
    tomorrow() {
      return addCalendarDays(this.today, 1);
    },
    dayAfterTomorrow() {
      return addCalendarDays(this.today, 2);
    },
    isLoggedIn() {
      return Boolean(getAuth());
    },
    currentCluster() {
      return this.clusters.find(c => String(c.id) === String(this.clusterId)) || null;
    },
    bookingBlocked() {
      return this.bookingAccess?.can_book === false;
    },
    backTarget() {
      const r = String(this.$route.query.return_to || "");
      if (r.startsWith("/venues") && !r.startsWith("//")) return r;
      if (this.clusterId) return `/venues/${this.clusterId}`;
      return "/venues";
    },
    config() {
      return {
        min_duration_minutes: 30,
        max_duration_minutes: null,
        min_advance_booking_minutes: 30,
        slot_hold_minutes: 20,
        allow_full_payment: true,
        allow_deposit: true,
        allow_no_prepay: true,
        deposit_percent: 30,
        ...(this.currentCluster?.booking_config || {}),
      };
    },
    courtTypes() {
      const m = new Map();
      (this.currentCluster?.venue_courts || []).forEach(c => {
        if (c.court_type?.id) m.set(String(c.court_type.id), c.court_type);
      });
      return [...m.values()];
    },
    minDuration() {
      return Number(this.config.min_duration_minutes || 30);
    },
    maxDuration() {
      const v = Number(this.config.max_duration_minutes || 0);
      return v > 0 ? v : null;
    },
    minAdvance() {
      return Number(this.config.min_advance_booking_minutes || 0);
    },
    configText() {
      const parts = [];
      if (this.operatingHours?.is_open) {
        parts.push(`Giờ mở cửa: ${this.shortTime(this.operatingHours.open_time)} – ${this.shortTime(this.operatingHours.close_time)}`);
      } else if (this.operatingHours) {
        parts.push("Sân đóng cửa ngày này");
      }
      parts.push(`Đặt tối thiểu ${this.minDuration} phút`);
      if (this.maxDuration) parts.push(`Tối đa ${this.maxDuration} phút`);
      if (this.minAdvance) parts.push(`Cần đặt trước ${this.minAdvance} phút`);
      if (this.config.slot_hold_minutes) parts.push(`Giữ chỗ ${this.config.slot_hold_minutes} phút`);
      return parts.join(" · ");
    },
    dynamicTimePeriods() {
      const defs = [
        { key: "morning", label: "Ca Sáng", from: 0, to: 12 * 60 },
        { key: "afternoon", label: "Ca Chiều", from: 12 * 60, to: 18 * 60 },
        { key: "evening", label: "Ca Tối", from: 18 * 60, to: 24 * 60 },
      ];
      return defs
        .map(p => {
          const infos = this.slots
            .map((slot, index) => ({ slot, index }))
            .filter(({ slot }) => {
              const s = this.minutes(slot.start_time);
              return s >= p.from && s < p.to;
            });
          if (!infos.length) return null;
          return {
            ...p,
            slotInfos: infos,
            range: `${this.shortTime(infos[0].slot.start_time)} – ${this.shortTime(infos[infos.length - 1].slot.end_time)}`,
          };
        })
        .filter(Boolean);
    },
    activePeriodKey() {
      const ps = this.dynamicTimePeriods;
      if (!ps.length) return "";
      return ps.some(p => p.key === this.activePeriod) ? this.activePeriod : ps[0].key;
    },
    activePeriodSlots() {
      return this.dynamicTimePeriods.find(p => p.key === this.activePeriodKey)?.slotInfos || [];
    },
    selectedSlotEntries() {
      return this.slotEntriesFromKeys(this.selectedSlotKeys);
    },
    selectedSlotRanges() {
      return this.slotRangesFromKeys(this.selectedSlotKeys);
    },
    selectedCourts() {
      const m = new Map();
      this.selectedSlotEntries.forEach(e => m.set(String(e.court.id), e.court));
      return [...m.values()];
    },
    selectedCourt() {
      return this.selectedCourts[0] || null;
    },
    selectedDetails() {
      return this.selectedSlotEntries
        .map(e => ({ ...e, status: this.slotStatus(e.courtId, e.slot) }))
        .filter(e => e.status);
    },
    selectedPriceRows() {
      return this.selectedSlotRanges.map(range => {
        const amount = this.selectedDetails
          .filter(
            e =>
              String(e.courtId) === String(range.venue_court_id) &&
              this.minutes(e.slot.start_time) >= this.minutes(range.start_time) &&
              this.minutes(e.slot.end_time) <= this.minutes(range.end_time)
          )
          .reduce((s, e) => s + Number(e.status?.price || 0), 0);
        return {
          courtId: range.venue_court_id,
          courtName: range.court?.name || "Sân",
          startTime: range.start_time,
          endTime: range.end_time,
          amount,
        };
      });
    },
    duration() {
      return this.selectedSlotEntries.reduce(
        (s, e) => s + Math.max(this.minutes(e.slot.end_time) - this.minutes(e.slot.start_time), 0),
        0
      );
    },
    durationText() {
      if (!this.duration) return "0 phút";
      const h = Math.floor(this.duration / 60);
      const m = this.duration % 60;
      if (!h) return `${m} phút`;
      if (!m) return `${h} giờ`;
      return `${h} giờ ${m} phút`;
    },
    selectedTimeText() {
      return this.rangeListText(this.selectedSlotRanges);
    },
    summaryTitle() {
      if (!this.selectedSlotRanges.length) return "Chưa chọn khung giờ nào";
      if (this.selectedCourts.length === 1) return this.selectedCourts[0].name;
      return `${this.selectedCourts.length} sân đã chọn`;
    },
    summarySubtitle() {
      if (!this.selectedSlotRanges.length) return "Vui lòng bấm chọn ô giờ trên bảng lịch để đặt.";
      if (this.selectedSlotRanges.length === 1) return `${this.selectedCourt?.court_type?.name || "Sân thể thao"} · ${this.durationText}`;
      return `${this.selectedSlotRanges.length} khung giờ · ${this.durationText}`;
    },
    selectionTitle() {
      if (this.checking) return "Đang đối chiếu khung giờ...";
      if (!this.selectedSlotRanges.length) return "Hãy chọn khung giờ trống trên ma trận";
      if (this.selectedSlotRanges.length === 1) return `${this.selectedCourt?.name} (${this.durationText})`;
      return `${this.selectedSlotRanges.length} khung giờ (${this.selectedCourts.length} sân)`;
    },
    selectionHint() {
      if (this.checking) return "Đang kiểm tra tính khả dụng và áp dụng giá tốt nhất...";
      return this.selectedSlotRanges.length
        ? `${this.selectedTimeText} · Tạm tính: ${this.money(this.baseAmount)}`
        : `Mỗi sân cần chọn liền nhau đủ tối thiểu ${this.minDuration} phút.`;
    },
    validationError() {
      if (this.selectionError) return this.selectionError;
      if (!this.selectedSlotKeys.length) return "";
      if (this.maxDuration && this.selectedSlotEntries.length) {
        const starts = this.selectedSlotEntries.map(e => this.minutes(e.slot.start_time));
        const ends = this.selectedSlotEntries.map(e => this.minutes(e.slot.end_time));
        const span = Math.max(...ends) - Math.min(...starts);
        if (span > this.maxDuration) {
          return `Một booking chỉ được đặt tối đa ${this.maxDuration} phút (khoảng bạn chọn kéo dài ${span} phút).`;
        }
      }
      const bad = this.selectedCourts.find(c => {
        const mins = this.selectedSlotEntries
          .filter(e => String(e.courtId) === String(c.id))
          .reduce((s, e) => s + Math.max(this.minutes(e.slot.end_time) - this.minutes(e.slot.start_time), 0), 0);
        return mins < this.minDuration || (this.maxDuration && mins > this.maxDuration);
      });
      if (bad) {
        const mins = this.selectedSlotEntries
          .filter(e => String(e.courtId) === String(bad.id))
          .reduce((s, e) => s + Math.max(this.minutes(e.slot.end_time) - this.minutes(e.slot.start_time), 0), 0);
        if (mins < this.minDuration) return `${bad.name} yêu cầu thời lượng đặt tối thiểu ${this.minDuration} phút (hiện có ${mins} phút).`;
        return `${bad.name} chỉ được đặt tối đa ${this.maxDuration} phút.`;
      }
      if (this.paymentOption === "wallet" && this.walletBalance !== null && this.walletBalance < this.total) {
        return "Số dư ví SportGo không đủ cho booking này.";
      }
      if (!this.available && !this.checking) return "Khung giờ bạn chọn đã bị người khác đặt hoặc tạm khóa.";
      return "";
    },
    baseAmount() {
      return this.selectedDetails.reduce((s, e) => s + Number(e.status?.price || 0), 0);
    },
    membershipDiscount() {
      return Number(this.preview?.membership_discount_amount || 0);
    },
    afterMembership() {
      return Number(this.preview?.final_amount ?? Math.max(this.baseAmount - this.membershipDiscount, 0));
    },
    eligibleVouchers() {
      return [...(this._venueVouchers || []), ...this.vipVouchers];
    },
    venueVouchers() {
      return this._venueVouchers || [];
    },
    vipVouchers() {
      const rem = Math.max(this.afterMembership - this.venueVoucherDiscount, 0);
      if (rem <= 0) return [];
      return (this._vipVouchers || []).filter(v => Number(v.min_order_amount || 0) <= rem);
    },
    venueVoucher() {
      return this.venueVouchers.find(v => String(v.id) === String(this.selectedVenueVoucherId));
    },
    vipVoucher() {
      return this.vipVouchers.find(v => String(v.id) === String(this.selectedVipVoucherId));
    },
    selectedVoucherCount() {
      return [this.venueVoucher, this.vipVoucher].filter(Boolean).length;
    },
    venueVoucherDiscount() {
      return this.voucherValue(this.venueVoucher, this.afterMembership);
    },
    vipVoucherDiscount() {
      return this.voucherValue(this.vipVoucher, Math.max(this.afterMembership - this.venueVoucherDiscount, 0));
    },
    voucherDiscount() {
      return this.venueVoucherDiscount + this.vipVoucherDiscount;
    },
    total() {
      return Math.max(this.afterMembership - this.voucherDiscount, 0);
    },
    requiredAmount() {
      if (this.paymentOption === "full_payment" || this.paymentOption === "wallet") return this.total;
      if (this.paymentOption === "deposit") return this.total * (Number(this.config.deposit_percent || 30) / 100);
      return 0;
    },
    paymentOptions() {
      return [
        this.config.allow_no_prepay !== false && { value: "no_prepay", label: "Thanh toán tại sân", hint: "Chờ chủ sân duyệt tối đa 30 phút; có thể ngắn hơn nếu gần giờ chơi" },
        this.config.allow_deposit !== false && { value: "deposit", label: `Đặt cọc trước ${this.config.deposit_percent || 30}%`, hint: "Quét QR sau khi tạo đơn; nếu chủ sân duyệt trước khi cọc, đơn chuyển sang trả sau" },
        this.config.allow_full_payment !== false && { value: "full_payment", label: "Thanh toán 100% online", hint: "Chuyển khoản qua cổng SePay/QR" },
        this.config.allow_full_payment !== false && this.walletBalance !== null && {
          value: "wallet",
          label: "Thanh toán bằng Ví SportGo",
          hint: this.walletBalance >= this.total ? `Số dư: ${this.money(this.walletBalance)}` : "Số dư ví không đủ",
          disabled: this.walletBalance < this.total,
        },
      ].filter(Boolean);
    },
    canSubmit() {
      return !this.bookingBlocked && this.selectedSlotKeys.length > 0 && !this.validationError && !this.submitting;
    },
    activeStep() {
      if (this.selectedSlotKeys.length && !this.validationError) return 3;
      if (this.selectedSlotKeys.length) return 2;
      return 1;
    },
  },
  async mounted() {
    window.addEventListener("pageshow", this.onPageShow);
    window.addEventListener("focus", this.onWindowFocus);

    // Fallback silent sync timer every 45s
    this.scheduleSyncInterval = setInterval(() => {
      if (!document.hidden && !this.initialLoading && !this.scheduleLoading) {
        this.loadSchedule({ silent: true });
      }
    }, 45000);

    await this.initialize();
    this.subscribeScheduleChannel();
  },
  beforeUnmount() {
    window.removeEventListener("pageshow", this.onPageShow);
    window.removeEventListener("focus", this.onWindowFocus);

    if (this.scheduleSyncInterval) {
      clearInterval(this.scheduleSyncInterval);
      this.scheduleSyncInterval = null;
    }

    this.unsubscribeScheduleChannel();
  },
  activated() {
    this.subscribeScheduleChannel();
    this.loadSchedule({ silent: true });
  },
  deactivated() {
    this.unsubscribeScheduleChannel();
  },
  methods: {
    onPageShow(e) {
      if (e.persisted) this.loadSchedule({ silent: true });
    },
    onWindowFocus() {
      if (this.initialLoading) return;
      // Throttle: Only silent refresh if more than 15s since last fetch
      const now = Date.now();
      if (now - this.lastScheduleFetchedAt > 15000) {
        this.loadSchedule({ silent: true });
      }
    },
    subscribeScheduleChannel() {
      if (!echo || !this.clusterId) return;
      const channelName = `venue-cluster.${this.clusterId}`;
      if (this.echoChannelName === channelName) return;

      this.unsubscribeScheduleChannel();

      try {
        this.echoChannelName = channelName;
        echo.channel(channelName)
          .listen('.booking.schedule.updated', (event) => {
            // If the broadcast is for current booking date or unspecified (whole cluster), silently sync schedule
            if (!event.booking_date || event.booking_date === this.bookingDate) {
              this.loadSchedule({ silent: true });
            }
          });
      } catch (err) {
        console.warn("Could not subscribe to booking schedule channel:", err);
      }
    },
    unsubscribeScheduleChannel() {
      if (this.echoChannelName && echo) {
        try {
          echo.leave(this.echoChannelName);
        } catch {}
        this.echoChannelName = null;
      }
    },
    async initialize() {
      try {
        const q = this.$route.query || {};
        const res = await bookingService.getInitData();

        if (getAuth()) {
          try {
            const wr = await bookingService.getWallet();
            this.walletBalance = Number(wr?.wallet?.balance || 0);
          } catch {
            this.walletBalance = null;
          }
        } else {
          this.walletBalance = null;
        }

        this.clusters = res.clusters || [];
        const reqId = q.venue_cluster_id || q.cluster;
        const requestedCluster = this.clusters.find(c => String(c.id) === String(reqId));
        const firstBookableCluster = this.clusters.find(c => c.booking_access?.can_book !== false && c.status === "active");
        this.clusterId = requestedCluster?.id || firstBookableCluster?.id || this.clusters[0]?.id || "";
        this.clusterLocked = Boolean(reqId);
        this.bookingAccess = requestedCluster?.booking_access || this.bookingAccess;

        const reqDate = String(q.booking_date || q.date || this.today);
        this.bookingDate = reqDate >= this.today ? reqDate : this.today;
        this.courtTypeId = String(q.court_type_id || q.court_type || "");

        this.routeSelection = (q.venue_court_id || q.court)
          ? {
              courtId: q.venue_court_id || q.court,
              start: this.normalizeTime(q.start_time),
              end: this.normalizeTime(q.end_time),
            }
          : null;

        this.ensurePaymentOption();
        await this.loadSchedule();
      } catch (err) {
        this.scheduleError = (err?.message && !err.message.includes("<") && !err.message.includes("Phiên đăng nhập"))
          ? err.message
          : "Không thể khởi tạo dữ liệu sân. Vui lòng thử lại.";
      } finally {
        this.initialLoading = false;
      }
    },
    applyBookingAccess(access = null) {
      if (!access) return;

      this.bookingAccess = {
        can_book: access.can_book !== false,
        code: access.code || "available",
        status: access.status || null,
        access_mode: access.access_mode || "full",
        title: access.title || "Cụm sân đang bị khóa",
        message: access.message || "Cụm sân hiện không nhận booking mới.",
        reason: access.reason || null,
      };

      if (!this.bookingBlocked) {
        this.restrictionNoticeKey = "";
        return;
      }

      this.availabilityRequestId += 1;
      this.checking = false;
      this.selectedSlotKeys = [];
      this.selectionError = "";
      this.available = false;
      this.preview = null;
      this._venueVouchers = [];
      this._vipVouchers = [];
      this.selectedVenueVoucherId = "";
      this.selectedVipVoucherId = "";
      this.voucherNotice = "";

      const noticeKey = `${this.clusterId}:${this.bookingAccess.code}:${this.bookingAccess.reason || this.bookingAccess.message}`;
      if (this.restrictionNoticeKey !== noticeKey) {
        this.restrictionNoticeKey = noticeKey;
        this.toast.error(this.bookingAccess.message);
      }
    },
    async loadSchedule(options = {}) {
      const isSilent = Boolean(options.silent);
      if (!this.clusterId || !this.bookingDate) return;
      const rid = ++this.scheduleRequestId;
      const prevKeys = [...this.selectedSlotKeys];

      if (!isSilent) {
        this.availabilityRequestId += 1;
        this.checking = false;
        this.available = true;
        this.preview = null;
        this._venueVouchers = [];
        this._vipVouchers = [];
        this.selectedVenueVoucherId = "";
        this.selectedVipVoucherId = "";
        this.voucherNotice = "";
        this.scheduleLoading = true;
        this.scheduleError = "";
      }

      try {
        const params = { venue_cluster_id: this.clusterId, booking_date: this.bookingDate };
        if (this.courtTypeId) params.court_type_id = this.courtTypeId;
        const res = await bookingService.getSchedule(params);
        if (rid !== this.scheduleRequestId) return;

        this.slots = res.time_slots || [];
        this.courts = res.courts || [];
        this.statuses = res.slot_statuses || [];
        this.operatingHours = res.operating_hours || null;
        this.applyBookingAccess(res.booking_access || this.currentCluster?.booking_access);
        this.lastScheduleFetchedAt = Date.now();
        this.ensureActivePeriod();

        if (this.bookingBlocked) {
          return;
        }

        if (prevKeys.length) {
          this.selectedSlotKeys = prevKeys;
          const entries = this.slotEntriesFromKeys(prevKeys);
          const invalidEntries = entries.filter(e => this.slotDisabled(e.courtId, e.slot));

          if (invalidEntries.length > 0) {
            // Keep valid ones, filter out disabled
            const validKeys = entries
              .filter(e => !this.slotDisabled(e.courtId, e.slot))
              .map(e => `${e.courtId}:${e.slot.start_time}`);

            this.selectedSlotKeys = validKeys;
            this.selectionError = "Một số khung giờ bạn đang chọn vừa có khách khác đặt hoặc cập nhật.";
            if (validKeys.length > 0 && this.isLoggedIn) {
              await this.checkAvailability();
            }
          } else if (!isSilent && this.isLoggedIn) {
            await this.checkAvailability();
          }
        } else if (!isSilent) {
          await this.applyRouteSelection();
        }
      } catch (err) {
        if (rid !== this.scheduleRequestId) return;
        if (!isSilent) {
          this.scheduleError = (err?.message && !err.message.includes("<") && !err.message.includes("Phiên đăng nhập"))
            ? err.message
            : "Không thể tải lịch sân. Vui lòng thử lại.";
        }
      } finally {
        if (rid === this.scheduleRequestId && !isSilent) {
          this.scheduleLoading = false;
        }
      }
    },
    async changeCluster() {
      this.courtTypeId = "";
      this.ensurePaymentOption();
      this.clearSelection();
      this.subscribeScheduleChannel();
      await this.loadSchedule();
    },
    async changeDate() {
      this.clearSelection();
      await this.loadSchedule();
    },
    async changeCourtType() {
      this.clearSelection();
      await this.loadSchedule();
    },
    ensureActivePeriod() {
      const ps = this.dynamicTimePeriods;
      if (!ps.length) {
        this.activePeriod = "";
        return;
      }
      if (ps.some(p => p.key === this.activePeriod)) return;
      const nowM = businessMinutes();
      const cur =
        this.bookingDate === this.today
          ? ps.find(p => p.slotInfos.some(({ slot }) => this.minutes(slot.start_time) <= nowM && this.minutes(slot.end_time) > nowM))
          : null;
      this.activePeriod = (cur || ps[0]).key;
    },
    setActivePeriodByIndex(index) {
      const p = this.dynamicTimePeriods.find(p => p.slotInfos.some(s => s.index === index));
      if (p) this.activePeriod = p.key;
    },
    shiftDate(days) {
      const next = addCalendarDays(this.bookingDate, days);
      if (next < this.today) return;
      if (next !== this.bookingDate) {
        this.bookingDate = next;
        this.changeDate();
      }
    },
    slotStatus(courtId, slot) {
      const s = String(slot?.start_time || "").slice(0, 5);
      return this.statuses.find(x => String(x.venue_court_id) === String(courtId) && String(x.start_time).slice(0, 5) === s);
    },
    slotPast(slot) {
      if (this.bookingDate !== this.today) return false;
      return this.minutes(slot.start_time) <= businessMinutes();
    },
    slotTooSoon(slot) {
      if (this.bookingDate !== this.today || !this.minAdvance) return false;
      return this.minutes(slot.start_time) < businessMinutes() + this.minAdvance;
    },
    slotDisabled(courtId, slot) {
      const court = this.courts.find(c => String(c.id) === String(courtId));
      if (court?.status === 'maintenance') return true;
      const status = this.slotStatus(courtId, slot);
      if (status?.slot_status === 'maintenance') return true;
      return this.bookingBlocked || this.slotPast(slot) || this.slotTooSoon(slot) || status?.is_available === false;
    },
    slotDisabledLabel(courtId, slot) {
      const court = this.courts.find(c => String(c.id) === String(courtId));
      const status = this.slotStatus(courtId, slot);
      if (court?.status === 'maintenance' || status?.slot_status === 'maintenance') return "Bảo trì";
      if (status?.busy_source === "slot_lock" || status?.slot_status === "locked") return "Đã khóa";
      if (this.slotPast(slot)) return "Đã qua";
      if (this.slotTooSoon(slot)) return "Quá sát giờ";
      if (status?.status_label) return status.status_label;
      if (status?.is_available === false) return "Đã đặt";
      return "-";
    },
    isSlotLocked(courtId, slot) {
      const status = this.slotStatus(courtId, slot);
      return status?.busy_source === "slot_lock" || status?.slot_status === "locked";
    },
    isCourtMaintenance(courtId, slot) {
      const court = this.courts.find(c => String(c.id) === String(courtId));
      if (court?.status === "maintenance") return true;
      const status = this.slotStatus(courtId, slot);
      return status?.slot_status === "maintenance";
    },
    isSlotLockedOrMaintenance(courtId, slot) {
      return this.isSlotLocked(courtId, slot) || this.isCourtMaintenance(courtId, slot);
    },
    getLockBadgeLabel(courtId, slot) {
      if (this.isCourtMaintenance(courtId, slot)) return "Bảo trì";
      return "Đã khóa";
    },
    getLockBadgeClass(courtId, slot) {
      if (this.isCourtMaintenance(courtId, slot)) return "maintenance";
      return "locked";
    },
    getLockReasonText(courtId, slot) {
      const status = this.slotStatus(courtId, slot);
      if (this.isCourtMaintenance(courtId, slot)) {
        return status?.unavailable_reason || "Sân đang trong quá trình bảo trì, tạm ngưng nhận lịch.";
      }
      return status?.lock_reason || status?.unavailable_reason || "Chủ sân đang tạm khóa khung giờ này.";
    },
    isSelectedSlot(courtId, slot) {
      return this.selectedSlotKeys.includes(this.slotKey(courtId, slot));
    },
    isSlotCoveredByMerge(courtId, slotInfo) {
      if (!this.isSelectedSlot(courtId, slotInfo.slot)) return false;
      const list = this.activePeriodSlots;
      const currIdx = list.findIndex(item => item.index === slotInfo.index);
      if (currIdx <= 0) return false;

      const prevItem = list[currIdx - 1];
      return prevItem && prevItem.index === slotInfo.index - 1 && this.isSelectedSlot(courtId, prevItem.slot);
    },
    getSlotRowspan(courtId, slotInfo) {
      if (!this.isSelectedSlot(courtId, slotInfo.slot)) return 1;
      const list = this.activePeriodSlots;
      const currIdx = list.findIndex(item => item.index === slotInfo.index);
      if (currIdx === -1) return 1;

      let span = 1;
      for (let i = currIdx + 1; i < list.length; i++) {
        const nextItem = list[i];
        if (nextItem.index === list[i - 1].index + 1 && this.isSelectedSlot(courtId, nextItem.slot)) {
          span += 1;
        } else {
          break;
        }
      }
      return span;
    },
    getMergedEndSlot(courtId, slotInfo) {
      const span = this.getSlotRowspan(courtId, slotInfo);
      if (span <= 1) return slotInfo.slot;
      const list = this.activePeriodSlots;
      const currIdx = list.findIndex(item => item.index === slotInfo.index);
      return list[currIdx + span - 1]?.slot || slotInfo.slot;
    },
    getMergedDuration(courtId, slotInfo) {
      const endSlot = this.getMergedEndSlot(courtId, slotInfo);
      return Math.max(0, this.minutes(endSlot.end_time) - this.minutes(slotInfo.slot.start_time));
    },
    mergedSlotTitle(court, slotInfo) {
      const endSlot = this.getMergedEndSlot(court.id, slotInfo);
      const duration = this.formatDurationMinutes(this.getMergedDuration(court.id, slotInfo));
      return `${court.name} · ${this.shortTime(slotInfo.slot.start_time)} – ${this.shortTime(endSlot.end_time)} (${duration}) · Nhấp để hủy chọn dải sân này`;
    },
    async toggleMergedSlot(court, slotInfo) {
      const list = this.activePeriodSlots;
      const currIdx = list.findIndex(item => item.index === slotInfo.index);
      const span = this.getSlotRowspan(court.id, slotInfo);

      const keysToRemove = [];
      for (let i = 0; i < span; i++) {
        const item = list[currIdx + i];
        if (item) {
          keysToRemove.push(this.slotKey(court.id, item.slot));
        }
      }

      this.selectionError = "";
      this.selectedSlotKeys = this.selectedSlotKeys.filter(k => !keysToRemove.includes(k));
      if (this.isLoggedIn) {
        await this.checkAvailability();
      }
    },
    formatDurationMinutes(mins) {
      if (!mins) return "0 phút";
      const h = Math.floor(mins / 60);
      const m = mins % 60;
      if (!h) return `${m} phút`;
      if (!m) return `${h} giờ`;
      return `${h}g${m}p`;
    },
    slotClasses(courtId, slot) {
      const status = this.slotStatus(courtId, slot);
      const slotStatus = status?.slot_status;
      const court = this.courts.find(c => String(c.id) === String(courtId));
      const isMaintenance = court?.status === "maintenance" || slotStatus === "maintenance";

      return {
        "is-selected": this.isSelectedSlot(courtId, slot),
        "is-maintenance": isMaintenance,
        "is-booked": !isMaintenance && (slotStatus === "booked" || (status?.is_available === false && status?.busy_source !== "slot_lock")),
        "is-locked": !isMaintenance && (slotStatus === "locked" || status?.busy_source === "slot_lock"),
        "is-status-available": slotStatus === "available",
        "is-status-maintenance": isMaintenance,
        "is-status-pending-approval": slotStatus === "pending_approval",
        "is-status-pending-payment": slotStatus === "pending_payment",
        "is-status-locked": slotStatus === "locked",
        "is-status-busy": slotStatus === "busy",
        "is-status-past": slotStatus === "past",
        "is-status-too-early": slotStatus === "too_early",
        "is-past": this.slotPast(slot) || this.slotTooSoon(slot),
      };
    },
    slotTitle(court, slot) {
      const status = this.slotStatus(court.id, slot);
      if (court.status === "maintenance" || status?.slot_status === "maintenance") {
        return `${court.name} đang trong thời gian bảo trì, không thể đặt sân.`;
      }
      if (this.bookingBlocked) return this.bookingAccess.message;
      if (this.slotPast(slot)) return "Khung giờ đã trôi qua.";
      if (this.slotTooSoon(slot)) return `Cần đặt trước ít nhất ${this.minAdvance} phút.`;
      if (status?.busy_source === "slot_lock") return status.lock_reason || "Sân đang được chủ sân giữ lịch.";
      if (status?.is_available === false) return "Sân đã có người khác đặt.";
      return `${court.name} · ${this.shortTime(slot.start_time)}–${this.shortTime(slot.end_time)} · ${this.money(status?.price)}`;
    },
    async toggleSlot(court, index) {
      const slot = this.slots[index];
      if (this.bookingBlocked) {
        this.toast.error(this.bookingAccess.message);
        return;
      }
      const courtObj = this.courts.find(c => String(c.id) === String(court.id)) || court;
      if (courtObj?.status === 'maintenance') {
        this.toast.warning(`${courtObj.name} đang trong thời gian bảo trì, không thể đặt sân.`);
        return;
      }
      if (this.slotDisabled(court.id, slot)) return;
      this.selectionError = "";
      const key = this.slotKey(court.id, slot);
      const nextKeys = this.selectedSlotKeys.includes(key)
        ? this.selectedSlotKeys.filter(k => k !== key)
        : [...this.selectedSlotKeys, key];
      const nextEntries = this.slotEntriesFromKeys(nextKeys);
      const courtMins = nextEntries
        .filter(e => String(e.courtId) === String(court.id))
        .reduce((s, e) => s + Math.max(this.minutes(e.slot.end_time) - this.minutes(e.slot.start_time), 0), 0);

      if (this.maxDuration && courtMins > this.maxDuration) {
        this.selectionError = `${court.name} chỉ cho phép đặt tối đa ${this.maxDuration} phút mỗi lần.`;
        return;
      }
      this.selectedSlotKeys = nextKeys;
      if (this.isLoggedIn) {
        await this.checkAvailability();
      }
    },
    clearSelection() {
      this.availabilityRequestId += 1;
      this.checking = false;
      this.selectedSlotKeys = [];
      this.selectionError = "";
      this.available = true;
      this.preview = null;
      this._venueVouchers = [];
      this._vipVouchers = [];
      this.selectedVenueVoucherId = "";
      this.selectedVipVoucherId = "";
      this.voucherNotice = "";
    },
    async checkAvailability() {
      if (!this.isLoggedIn) return;
      const rid = ++this.availabilityRequestId;
      const ranges = this.selectedSlotRanges.map(r => ({ ...r }));
      if (!ranges.length) {
        this.available = true;
        this.preview = null;
        this._venueVouchers = [];
        this._vipVouchers = [];
        this.checking = false;
        return;
      }
      this.checking = true;
      try {
        const responses = await Promise.all(
          ranges.map(r =>
            bookingService.checkAvailability({
              venue_court_id: r.venue_court_id,
              booking_date: this.bookingDate,
              start_time: r.start_time,
              end_time: r.end_time,
            })
          )
        );
        if (rid !== this.availabilityRequestId) return;
        const blockedAccess = responses.find(response => response?.booking_access?.can_book === false)?.booking_access;
        if (blockedAccess) {
          this.applyBookingAccess(blockedAccess);
          return;
        }
        this.available = responses.every(r => Boolean(r.available));
        const orig = responses.reduce((s, r) => s + Number(r.price_preview?.original_amount || r.total_price || 0), 0);
        const disc = responses.reduce((s, r) => s + Number(r.membership_discount?.discount_amount || r.price_preview?.membership_discount_amount || 0), 0);
        this.preview = { original_amount: orig, membership_discount_amount: disc, final_amount: Math.max(orig - disc, 0) };
        if (this.available) await this.loadVouchers(rid, ranges[0]);
      } catch (err) {
        if (rid !== this.availabilityRequestId) return;
        // Bắt lỗi không làm vỡ trải nghiệm
        this.available = !this.bookingBlocked;
      } finally {
        if (rid === this.availabilityRequestId) this.checking = false;
      }
    },
    async loadVouchers(rid, firstRange) {
      if (!firstRange || !this.isLoggedIn) return;
      try {
        const res = await bookingService.eligibleVouchers({
          venue_court_id: firstRange.venue_court_id,
          booking_date: this.bookingDate,
          start_time: firstRange.start_time,
          end_time: firstRange.end_time,
          amount: this.baseAmount,
        });
        if (rid !== this.availabilityRequestId) return;
        this._venueVouchers = res.venue_vouchers || [];
        this._vipVouchers = res.vip_vouchers || [];
      } catch {
        if (rid !== this.availabilityRequestId) return;
        this._venueVouchers = [];
        this._vipVouchers = [];
      }
    },
    voucherSelected(v) {
      return v.owner_type === "venue"
        ? String(this.selectedVenueVoucherId) === String(v.id)
        : String(this.selectedVipVoucherId) === String(v.id);
    },
    toggleVoucher(v) {
      const already = this.voucherSelected(v);
      this.voucherNotice = "";
      if (v.owner_type === "venue") {
        this.selectedVenueVoucherId = already ? "" : v.id;
        if (this.selectedVipVoucherId && !this.vipVouchers.some(x => String(x.id) === String(this.selectedVipVoucherId))) {
          this.selectedVipVoucherId = "";
          this.voucherNotice = "Voucher VIP đã được gỡ do giá trị đơn hàng sau giảm giá voucher sân không đủ điều kiện.";
        }
        return;
      }
      this.selectedVipVoucherId = already ? "" : v.id;
    },
    voucherBaseAmount(v) {
      return v?.owner_type === "venue" ? this.afterMembership : Math.max(this.afterMembership - this.venueVoucherDiscount, 0);
    },
    voucherValue(v, amount = this.afterMembership) {
      if (!v) return 0;
      let val = v.discount_type === "percent" ? (Number(amount) * Number(v.discount_value || 0)) / 100 : Number(v.discount_value || 0);
      if (v.max_discount_amount != null) val = Math.min(val, Number(v.max_discount_amount));
      return Math.min(Math.max(val, 0), Number(amount));
    },
    ensurePaymentOption() {
      const allowed = this.paymentOptions.map(o => o.value);
      if (!allowed.includes(this.paymentOption)) this.paymentOption = allowed[0] || "no_prepay";
    },
    async applyRouteSelection() {
      if (!this.routeSelection) return;
      const court = this.courts.find(c => String(c.id) === String(this.routeSelection.courtId));
      const s = this.minutes(this.routeSelection.start);
      const e = this.minutes(this.routeSelection.end);
      const sel = this.slots
        .map((slot, index) => ({ slot, index }))
        .filter(({ slot }) => this.minutes(slot.start_time) >= s && this.minutes(slot.end_time) <= e)
        .map(({ index }) => index);
      this.routeSelection = null;
      if (!court || !sel.length || sel.some(i => this.slotDisabled(court.id, this.slots[i]))) return;
      this.selectedSlotKeys = sel.map(i => this.slotKey(court.id, this.slots[i]));
      this.setActivePeriodByIndex(sel[0]);
      if (this.isLoggedIn) {
        await this.checkAvailability();
      }
    },
    setDateQuick(d) {
      this.bookingDate = d;
      this.onDateChange();
    },
    async onCustomDateSelect(newDate) {
      if (!newDate) return;
      this.bookingDate = newDate;
      await this.onDateChange();
    },
    async onDateChange() {
      this.clearSelection();
      await this.loadSchedule();
    },
    async submit() {
      if (this.bookingBlocked) {
        this.toast.error(this.bookingAccess.message);
        return;
      }
      if (!this.canSubmit) return;
      if (!getAuth()) {
        this.submitError = "";
        this.$router.push({ name: "login", query: { redirect: this.$route.fullPath } });
        return;
      }
      this.submitting = true;
      this.submitError = "";
      try {
        const ranges = this.selectedSlotRanges.map(r => ({
          venue_court_id: r.venue_court_id,
          start_time: r.start_time,
          end_time: r.end_time,
        }));
        const first = ranges[0];
        const booking = await bookingService.createBooking({
          venue_court_id: first.venue_court_id,
          booking_date: this.bookingDate,
          start_time: first.start_time,
          end_time: first.end_time,
          ...(ranges.length > 1 ? { time_ranges: ranges } : {}),
          payment_option: this.paymentOption,
          venue_voucher_id: this.venueVoucher?.id || null,
          vip_voucher_id: this.vipVoucher?.id || null,
        });
        this.toast.success("Đặt sân thành công. Đang mở chi tiết đơn...");
        this.$router.push({ name: "booking-detail", params: { id: booking.id } });
      } catch (err) {
        const message = err.message || "Không thể tạo đơn đặt sân. Vui lòng thử lại.";
        this.submitError = message;
        this.toast.error(message);
        // Refresh in the background so a failed submit does not keep the
        // submit button spinning while the availability matrix reloads.
        void this.loadSchedule();
      } finally {
        this.submitting = false;
      }
    },
    slotKey(courtId, slot) {
      return `${courtId}|${slot?.start_time || ""}`;
    },
    slotEntriesFromKeys(keys = []) {
      return keys
        .map(key => {
          const [courtId, startTime] = key.split("|");
          const court = this.courts.find(c => String(c.id) === String(courtId));
          const slot = this.slots.find(s => s.start_time === startTime);
          return court && slot ? { courtId, court, slot } : null;
        })
        .filter(Boolean)
        .sort((a, b) => {
          const cs = String(a.court.name || "").localeCompare(String(b.court.name || ""));
          return cs !== 0 ? cs : this.minutes(a.slot.start_time) - this.minutes(b.slot.start_time);
        });
    },
    slotRangesFromKeys(keys = []) {
      const ranges = [];
      this.slotEntriesFromKeys(keys).forEach(({ courtId, court, slot }) => {
        const cur = ranges[ranges.length - 1];
        if (!cur || String(cur.venue_court_id) !== String(courtId) || cur.end_time !== slot.start_time) {
          ranges.push({ venue_court_id: courtId, court, start_time: slot.start_time, end_time: slot.end_time });
          return;
        }
        cur.end_time = slot.end_time;
      });
      return ranges;
    },
    rangeListText(ranges = []) {
      if (!ranges.length) return "Chưa chọn";
      if (ranges.length <= 2) {
        return ranges.map(r => `${r.court?.name || "Sân"}: ${this.shortTime(r.start_time)}–${this.shortTime(r.end_time)}`).join("; ");
      }
      return `${ranges.length} khung giờ trên ${this.selectedCourts.length} sân`;
    },
    minutes(time) {
      const [h, m] = String(time || "00:00").slice(0, 5).split(":").map(Number);
      return h * 60 + m;
    },
    normalizeTime(value) {
      const raw = String(value || "");
      return /^\d{2}:\d{2}$/.test(raw) ? `${raw}:00` : raw;
    },
    shortTime(value) {
      return String(value || "").slice(0, 5);
    },
    formatDate(value) {
      if (!value) return "-";
      const [y, m, d] = value.split("-");
      return `${d}/${m}/${y}`;
    },
    money(value) {
      return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(Number(value || 0));
    },
    compactMoney(value) {
      const n = Number(value || 0);
      return n >= 1000 ? `${Math.round(n / 1000)}k` : n || "";
    },
    setBookingMode(mode) {
      this.bookingMode = mode;
      if (mode === "recurring" && !this.recurringForm.recurring_start_date) {
        this.initRecurringDates();
      }
    },
    initRecurringDates() {
      const startStr = this.tomorrow;
      const endStr = addCalendarMonths(startStr, 1);

      this.recurringForm.recurring_start_date = startStr;
      this.recurringForm.recurring_end_date = endStr;
      this.recurringForm.venue_court_id = this.courts[0]?.id || null;
      this.recurringForm.recurrence_days_of_week = [1, 3, 5];
      this.recurringActiveWeekday = 1;
    },
    selectRecurringPreset(months) {
      const startDate = this.recurringForm.recurring_start_date || this.tomorrow;
      this.recurringForm.recurring_end_date = addCalendarMonths(startDate, months);
      this.recurringPreviewResult = null;
    },
    isRecurringWeekdaySelected(day) {
      return (this.recurringForm.recurrence_days_of_week || []).includes(day);
    },
    getWeekdayLabel(day) {
      return this.weekDaysList.find(d => d.value === day)?.label || `Thứ ${day}`;
    },
    toggleRecurringWeekday(day) {
      const list = [...(this.recurringForm.recurrence_days_of_week || [])];
      const idx = list.indexOf(day);
      if (idx >= 0) {
        if (list.length > 1) {
          list.splice(idx, 1);
        }
      } else {
        list.push(day);
      }
      this.recurringForm.recurrence_days_of_week = list.sort((a, b) => (a === 0 ? 7 : a) - (b === 0 ? 7 : b));
      this.recurringActiveWeekday = day;
      this.recurringPreviewResult = null;
    },
    getWeekdaySchedule(day) {
      if (!this.recurringForm.weekday_schedules[day]) {
        this.recurringForm.weekday_schedules[day] = {
          venue_court_id: this.recurringForm.venue_court_id || this.courts[0]?.id,
          start_time: this.recurringForm.start_time || "18:00:00",
          end_time: this.recurringForm.end_time || "20:00:00",
        };
      }
      return this.recurringForm.weekday_schedules[day];
    },
    getWeekdayScheduleSummary(day) {
      const s = this.getWeekdaySchedule(day);
      const court = this.courts.find(c => String(c.id) === String(s.venue_court_id)) || this.courts[0];
      return `${this.shortTime(s.start_time)}–${this.shortTime(s.end_time)} · ${court?.name || "Sân"}`;
    },
    copyScheduleToAllSelectedWeekdays() {
      const activeSched = this.getWeekdaySchedule(this.recurringActiveWeekday);
      (this.recurringForm.recurrence_days_of_week || []).forEach(day => {
        this.recurringForm.weekday_schedules[day] = { ...activeSched };
      });
      this.recurringPreviewResult = null;
    },
    recurringPayload() {
      const f = this.recurringForm;
      const courtId = f.venue_court_id || this.courts[0]?.id;

      const weekdayTimeRanges = [];
      if (f.recurrence_type === "weekly") {
        (f.recurrence_days_of_week || []).forEach(day => {
          const sched = f.weekday_schedules[day] || {
            venue_court_id: courtId,
            start_time: f.start_time,
            end_time: f.end_time,
          };
          weekdayTimeRanges.push({
            day_of_week: day,
            time_ranges: [{
              venue_court_id: sched.venue_court_id || courtId,
              start_time: sched.start_time || f.start_time,
              end_time: sched.end_time || f.end_time,
            }],
          });
        });
      }

      const overrides = Object.entries(f.conflict_overrides || {}).map(([date, ov]) => ({
        date,
        action: ov.action || "skip",
        venue_court_id: ov.action === "switch" ? ov.venue_court_id : undefined,
      }));

      return {
        venue_cluster_id: this.clusterId,
        venue_court_id: courtId,
        recurring_start_date: f.recurring_start_date,
        recurring_end_date: f.recurring_end_date,
        recurrence_type: f.recurrence_type,
        recurrence_interval: f.recurrence_interval || 1,
        recurrence_days_of_week: f.recurrence_type === "weekly" ? f.recurrence_days_of_week : undefined,
        start_time: f.start_time,
        end_time: f.end_time,
        weekday_time_ranges: weekdayTimeRanges.length ? weekdayTimeRanges : undefined,
        payment_option: f.payment_option || "no_prepay",
        conflict_resolution: f.conflict_resolution || "abort",
        conflict_overrides: overrides.length ? overrides : undefined,
      };
    },
    async runPreviewRecurring() {
      this.recurringPreviewLoading = true;
      this.recurringPreviewError = "";
      try {
        const payload = this.recurringPayload();
        const res = await bookingService.previewRecurringBooking(payload);
        this.recurringPreviewResult = res.data || res;
      } catch (err) {
        this.recurringPreviewResult = null;
        this.recurringPreviewError = err?.message || "Chưa kiểm tra được lịch cố định. Vui lòng kiểm tra lại cấu hình.";
      } finally {
        this.recurringPreviewLoading = false;
      }
    },
    getConflictAction(date) {
      return this.recurringForm.conflict_overrides?.[date]?.action || (this.recurringForm.conflict_resolution === "skip" ? "skip" : "skip");
    },
    getConflictAltCourtId(date) {
      return this.recurringForm.conflict_overrides?.[date]?.venue_court_id || null;
    },
    setConflictOverride(date, action, altCourtId = null) {
      if (!this.recurringForm.conflict_overrides) {
        this.recurringForm.conflict_overrides = {};
      }
      this.recurringForm.conflict_overrides[date] = {
        action,
        venue_court_id: altCourtId,
      };
      this.recurringForm.conflict_resolution = "mixed";
    },
    async submitRecurring() {
      if (this.bookingBlocked) {
        this.toast.error(this.bookingAccess.message);
        return;
      }
      if (!this.recurringCanSubmit) return;
      if (!getAuth()) {
        this.recurringSubmitError = "";
        this.$router.push({ name: "login", query: { redirect: this.$route.fullPath } });
        return;
      }
      this.recurringSubmitting = true;
      this.recurringSubmitError = "";

      try {
        const payload = this.recurringPayload();
        const res = await bookingService.createRecurringBooking(payload);
        const result = res.data || res;
        const groupCode = result.recurring_group_code;
        const firstBooking = result.first_booking || result.bookings?.[0];

        if (payload.payment_option === "full_payment" && firstBooking?.id) {
          try {
            const payRes = await bookingService.createSepayPayment(firstBooking.id);
            if (payRes?.payment_url) {
              this.toast.success("Đơn đặt lịch cố định đã được tạo. Đang chuyển sang thanh toán...");
              window.location.href = payRes.payment_url;
              return;
            }
          } catch {}
        }

        if (groupCode) {
          this.toast.success("Đã tạo lịch đặt sân cố định thành công.");
          this.$router.push({ path: "/bookings/history", query: { group: groupCode } });
        } else {
          this.toast.success("Đã tạo lịch đặt sân cố định thành công.");
          this.$router.push({ path: "/bookings/history" });
        }
      } catch (err) {
        const message = err?.message || "Không thể tạo lịch cố định. Vui lòng thử lại.";
        this.recurringSubmitError = message;
        this.toast.error(message);
      } finally {
        this.recurringSubmitting = false;
      }
    },
  },
};
</script>

<style scoped>
/* ===== BASE LAYOUT ===== */
.cbw-page {
  min-height: 100vh;
  background: #ffffff;
  color: #0f172a;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  font-size: 14px;
}

.cbw-page strong {
  font-weight: 500;
}

.cbw-main {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 24px 32px 60px !important;
}

/* ===== STEP PROGRESS INDICATOR ===== */
.cbw-steps {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  padding: 0;
  margin-bottom: 24px;
  background: transparent;
  border: none;
  box-shadow: none;
}

.cbw-step {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  flex: 1;
  max-width: 320px;
  padding: 8px 16px;
  opacity: 0.45;
  transition: opacity 0.2s ease;
  position: relative;
  border: none;
}

.cbw-step.is-active,
.cbw-step.is-done {
  opacity: 1;
}

.cbw-step-num {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #0f172a;
  font-size: 13px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.cbw-step.is-active .cbw-step-num {
  background: #15803d;
  color: #ffffff;
}

.cbw-step.is-done .cbw-step-num {
  background: #15803d;
  color: #ffffff;
}

.cbw-step-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cbw-step-label {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
  line-height: 1.2;
}

.cbw-step-hint {
  font-size: 12px;
  color: #475569;
  line-height: 1.3;
}

/* ===== LOADING SPINNER ===== */
.cbw-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  padding: 100px 0;
  color: #0f172a;
  font-size: 14px;
}

.cbw-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e2e8f0;
  border-top-color: #15803d;
  border-radius: 50%;
  animation: cbw-spin 0.75s linear infinite;
}

@keyframes cbw-spin {
  to { transform: rotate(360deg); }
}

/* ===== MODE NAVIGATION TABS (SOLID CLEAN) ===== */
.cbw-mode-nav {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 24px;
  border: none;
}

.cbw-mode-tab {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 18px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  color: #334155;
  font-size: 14px;
  font-weight: 500;
  line-height: 1.4;
  cursor: pointer;
  box-sizing: border-box;
  transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.cbw-mode-tab:hover:not(.is-active) {
  color: #0f172a;
  border-color: #cbd5e1;
}

.cbw-mode-tab.is-active {
  color: #ffffff;
  background: #15803d;
  border-color: #15803d;
  font-weight: 500;
}

/* ===== WORKSPACE GRID LAYOUT ===== */
.cbw-workspace {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 32px;
  align-items: start;
  min-width: 0;
  min-height: 540px;
}

.cbw-workspace.is-fullwidth {
  grid-template-columns: minmax(0, 1fr);
}

.cbw-workspace--recurring {
  grid-template-columns: minmax(0, 1fr) 360px !important;
}

/* ===== CLEAN COMPACT TOOLBAR ===== */
.cbw-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.cbw-date-selector {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.cbw-date-lbl {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
}

.cbw-quick-dates {
  display: flex;
  gap: 6px;
}

.cbw-quick-btn {
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 400;
  color: #1e293b;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.cbw-quick-btn:hover {
  border-color: #15803d;
  color: #15803d;
}

.cbw-quick-btn.is-active {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
  font-weight: 500;
}

.cbw-period-tabs {
  display: flex;
  gap: 6px;
}

.cbw-period-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  font-size: 13px;
  color: #1e293b;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 400;
  transition: all 0.15s ease;
}

.cbw-period-btn:hover:not(.is-active) {
  border-color: #15803d;
  color: #15803d;
}

.cbw-period-btn.is-active {
  color: #ffffff;
  background: #15803d;
  border-color: #15803d;
  font-weight: 500;
}

.cbw-period-range {
  font-size: 11.5px;
  opacity: 0.9;
  font-weight: 400;
}

/* ===== STATE MESSAGES ===== */
.cbw-state-msg {
  text-align: center;
  padding: 50px 16px;
  color: #1e293b;
  font-size: 14px;
  font-weight: 400;
}

.cbw-state-msg--error {
  color: #dc2626;
}

.cbw-access-blocked {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0 0 14px;
  padding: 14px 16px;
  color: #991b1b;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  line-height: 1.5;
}

.cbw-access-blocked strong {
  font-size: 14px;
}

.cbw-access-blocked span {
  font-size: 13px;
}

/* ===== MATRIX TABLE ===== */
.cbw-schedule-panel {
  background: transparent;
  border: none;
  border-radius: 0;
  padding: 0;
  box-shadow: none;
  min-width: 0;
  overflow: hidden;
}

.cbw-matrix-wrap {
  overflow-x: auto;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  margin-bottom: 14px;
  max-height: 560px;
  width: 100%;
  max-width: 100%;
}

.cbw-matrix {
  width: 100%;
  border-collapse: collapse;
  min-width: 550px;
}

.cbw-th-corner {
  padding: 10px 14px;
  text-align: left;
  font-size: 12px;
  font-weight: 500;
  color: #1e293b;
  background: #ffffff;
  white-space: nowrap;
  position: sticky;
  top: 0;
  left: 0;
  z-index: 3;
  border-right: 1px solid #f1f5f9;
}

.cbw-th-court {
  padding: 10px 14px;
  text-align: center;
  background: #ffffff;
  min-width: 140px;
  white-space: nowrap;
  position: sticky;
  top: 0;
  z-index: 2;
  border-right: 1px solid #f1f5f9;
}

.cbw-th-court strong {
  display: block;
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
}

.cbw-th-court span {
  display: block;
  font-size: 11.5px;
  color: #475569;
  font-weight: 400;
  margin-top: 2px;
}

.cbw-td-time {
  padding: 0 14px;
  font-size: 12.5px;
  font-weight: 500;
  color: #0f172a;
  background: #ffffff;
  white-space: nowrap;
  position: sticky;
  left: 0;
  z-index: 1;
  border-right: 1px solid #f1f5f9;
  height: 44px;
  vertical-align: middle;
}

.cbw-td-slot {
  padding: 4px;
  vertical-align: stretch;
  border-right: 1px solid #f1f5f9;
}

.cbw-td-empty {
  padding: 40px 16px;
  text-align: center;
  color: #334155;
  font-size: 13.5px;
  font-weight: 400;
}

/* ===== SLOT CELL WRAPPER & BUBBLE TOOLTIP ===== */
.cbw-slot-cell-wrapper {
  position: relative;
  width: 100%;
  height: 100%;
  display: flex;
}

.cbw-bubble-tooltip {
  position: absolute;
  bottom: calc(100% + 9px);
  left: 50%;
  transform: translateX(-50%) translateY(4px);
  min-width: 190px;
  max-width: 260px;
  padding: 9px 12px;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  box-shadow: 0 10px 25px -4px rgba(15, 23, 42, 0.15), 0 4px 6px -2px rgba(15, 23, 42, 0.08);
  color: #1e293b;
  font-family: inherit;
  font-size: 12px;
  line-height: 1.45;
  pointer-events: none;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.15s ease, transform 0.15s ease, visibility 0.15s;
  z-index: 100;
  text-align: left;
}

.cbw-bubble-tooltip.drop-down {
  bottom: auto;
  top: calc(100% + 9px);
  transform: translateX(-50%) translateY(-4px);
}

.cbw-slot-cell-wrapper:hover .cbw-bubble-tooltip {
  opacity: 1;
  visibility: visible;
  transform: translateX(-50%) translateY(0);
}

.cbw-bubble-arrow {
  position: absolute;
  top: 100%;
  left: 50%;
  margin-top: -5px;
  width: 10px;
  height: 10px;
  background: #ffffff;
  border-right: 1px solid #cbd5e1;
  border-bottom: 1px solid #cbd5e1;
  transform: translateX(-50%) rotate(45deg);
}

.cbw-bubble-tooltip.drop-down .cbw-bubble-arrow {
  top: 0;
  margin-top: -5px;
  border-right: none;
  border-bottom: none;
  border-left: 1px solid #cbd5e1;
  border-top: 1px solid #cbd5e1;
}

.cbw-bubble-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 6px;
  padding-bottom: 6px;
  border-bottom: 1px solid #f1f5f9;
}

.cbw-bubble-badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 2px 7px;
  border-radius: 4px;
}

.cbw-bubble-badge.locked {
  background: #f3e8ff;
  color: #7c3aed;
  border: 1px solid #d8b4fe;
}

.cbw-bubble-badge.maintenance {
  background: #fef3c7;
  color: #b45309;
  border: 1px solid #fde68a;
}

.cbw-bubble-time {
  font-size: 11.5px;
  color: #64748b;
  font-weight: 600;
}

.cbw-bubble-body {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.cbw-bubble-label {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
}

.cbw-bubble-reason {
  font-size: 12.5px;
  color: #0f172a;
  word-break: break-word;
  font-weight: 500;
  line-height: 1.4;
}

/* ===== SLOT BUTTONS ===== */
.cbw-slot-btn {
  width: 100%;
  height: 100%;
  min-height: 38px;
  background: #f0fdf4;
  border: 1px solid #c8ead2;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  font-size: 12.5px;
  font-weight: 400;
  color: #15803d;
  transition: all 0.15s ease;
}

.cbw-slot-btn:hover:not(:disabled):not(.is-selected) {
  background: #dcfce7;
  color: #166534;
}

.cbw-slot-btn.is-status-available {
  background: #e8f8ee;
  border-color: #86efac;
  color: #15803d;
}

.cbw-slot-btn.is-booked,
.cbw-slot-btn.is-status-booked {
  background: #dbeafe;
  border-color: #93c5fd;
  color: #1e40af;
  cursor: not-allowed;
}

.cbw-slot-btn.is-status-pending-approval {
  background: #fef3c7;
  border-color: #fcd34d;
  color: #92400e;
  cursor: not-allowed;
}

.cbw-slot-btn.is-status-pending-payment {
  background: #ffedd5;
  border-color: #fdba74;
  color: #9a3412;
  cursor: not-allowed;
}

.cbw-slot-btn.is-locked,
.cbw-slot-btn.is-status-locked {
  background: #ede9fe;
  border-color: #c4b5fd;
  color: #5b21b6;
  cursor: not-allowed;
}

.cbw-slot-btn.is-status-busy {
  background: #fee2e2;
  border-color: #fca5a5;
  color: #991b1b;
  cursor: not-allowed;
}

.cbw-slot-btn.is-maintenance,
.cbw-slot-btn.is-status-maintenance {
  background-color: #fffbeb !important;
  border-color: #f59e0b !important;
  color: #b45309 !important;
  cursor: not-allowed !important;
  background-image: repeating-linear-gradient(
    -45deg,
    rgba(245, 158, 11, 0.12),
    rgba(245, 158, 11, 0.12) 6px,
    transparent 6px,
    transparent 12px
  ) !important;
}

.cbw-slot-btn.is-maintenance .cbw-slot-disabled-text,
.cbw-slot-btn.is-status-maintenance .cbw-slot-disabled-text {
  color: #b45309 !important;
  font-weight: 700 !important;
  font-size: 11px !important;
  letter-spacing: 0.02em;
}

.cbw-court-header-main {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  flex-wrap: wrap;
}

.cbw-court-badge-maintenance {
  display: inline-block;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 1px 5px;
  border-radius: 4px;
  background: #fef3c7;
  color: #b45309;
  border: 1px solid #fde68a;
}

.cbw-th-court.is-maintenance {
  background: #fefce8 !important;
  border-bottom: 2px solid #f59e0b !important;
}

.cbw-slot-btn.is-past,
.cbw-slot-btn.is-status-past,
.cbw-slot-btn.is-status-too-early {
  background: #f1f5f9;
  border-color: #cbd5e1;
  color: #64748b;
  cursor: not-allowed;
}

.cbw-td-slot.is-merged-selected {
  padding: 3px 4px !important;
  height: 1px !important;
  vertical-align: stretch !important;
}

.cbw-td-slot.is-single-selected {
  padding: 3px 4px !important;
}

.cbw-slot-btn.is-selected {
  background: #15803d !important;
  border: 1.5px solid #166534 !important;
  color: #ffffff !important;
  font-weight: 600 !important;
  border-radius: 6px !important;
  transition: all 0.15s ease !important;
  cursor: pointer !important;
}

.cbw-slot-btn.is-merged-btn {
  width: 100% !important;
  height: 100% !important;
  min-height: 100% !important;
  box-sizing: border-box !important;
  background: #15803d !important;
  border: 1.5px solid #166534 !important;
  border-radius: 8px !important;
  padding: 10px 8px !important;
  display: flex !important;
  flex-direction: column !important;
  justify-content: center !important;
  align-items: center !important;
  gap: 6px !important;
  box-shadow: 0 3px 8px rgba(21, 128, 61, 0.25) !important;
  transition: all 0.15s ease !important;
}

.cbw-slot-btn.is-merged-btn:hover {
  background: #dc2626 !important;
  border-color: #b91c1c !important;
  box-shadow: 0 3px 8px rgba(220, 38, 38, 0.3) !important;
}

.cbw-merged-slot-card {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 6px !important;
  text-align: center !important;
  width: 100% !important;
}

.cbw-merged-time {
  font-size: 13.5px !important;
  font-weight: 700 !important;
  color: #ffffff !important;
  letter-spacing: 0.3px !important;
  white-space: nowrap !important;
}

.cbw-merged-badge {
  display: inline-block !important;
  background: rgba(255, 255, 255, 0.25) !important;
  border: 1px solid rgba(255, 255, 255, 0.4) !important;
  border-radius: 12px !important;
  padding: 3px 10px !important;
  font-size: 11px !important;
  font-weight: 600 !important;
  color: #ffffff !important;
  white-space: nowrap !important;
}

.cbw-merged-unselect-text {
  display: none !important;
  font-size: 12px !important;
  font-weight: 700 !important;
  color: #ffffff !important;
  text-transform: uppercase !important;
  letter-spacing: 0.4px !important;
}

.cbw-slot-btn.is-merged-btn:hover .cbw-merged-badge {
  display: none !important;
}

.cbw-slot-btn.is-merged-btn:hover .cbw-merged-unselect-text {
  display: inline-block !important;
}

.cbw-single-slot-card {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 2px !important;
  text-align: center !important;
  width: 100% !important;
}

.cbw-single-time {
  font-size: 10px !important;
  color: rgba(255, 255, 255, 0.9) !important;
  font-weight: 500 !important;
}

.cbw-single-unselect-text {
  display: none !important;
  font-size: 11px !important;
  font-weight: 700 !important;
  color: #ffffff !important;
}

.cbw-slot-btn.is-selected:not(.is-merged-btn):hover {
  background: #dc2626 !important;
  border-color: #b91c1c !important;
}

.cbw-slot-btn.is-selected:not(.is-merged-btn):hover .cbw-single-time,
.cbw-slot-btn.is-selected:not(.is-merged-btn):hover .cbw-slot-label {
  display: none !important;
}

.cbw-slot-btn.is-selected:not(.is-merged-btn):hover .cbw-single-unselect-text {
  display: block !important;
}

.cbw-slot-label {
  font-size: 12px;
  font-weight: 600;
  color: #ffffff;
}

.cbw-slot-price {
  font-size: 12px;
  font-weight: 500;
  color: #15803d;
}

.cbw-slot-disabled-text {
  font-size: 11.5px;
  color: currentColor;
  font-weight: 500;
}

.cbw-slot-btn.is-locked .cbw-slot-disabled-text,
.cbw-slot-btn.is-status-locked .cbw-slot-disabled-text {
  color: #5b21b6;
  font-size: 11px;
  font-weight: 600;
}

.cbw-status-legend {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 18px;
  margin-top: 14px;
  color: #334155;
  font-size: 12px;
  font-weight: 500;
}

.cbw-status-legend span {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  white-space: nowrap;
}

.cbw-legend-dot {
  width: 14px;
  height: 14px;
  border: 1.5px solid transparent;
  border-radius: 3px;
  flex: 0 0 auto;
}

.cbw-legend-dot--available { background: #86efac; border-color: #16a34a; }
.cbw-legend-dot--selected { background: #15803d; border-color: #14532d; }
.cbw-legend-dot--booked { background: #93c5fd; border-color: #2563eb; }
.cbw-legend-dot--pending { background: #fcd34d; border-color: #d97706; }
.cbw-legend-dot--payment { background: #fdba74; border-color: #ea580c; }
.cbw-legend-dot--locked { background: #c4b5fd; border-color: #7c3aed; }
.cbw-legend-dot--maintenance { background: #fde68a; border-color: #d97706; }
.cbw-legend-dot--past { background: #cbd5e1; border-color: #64748b; }

/* ===== SELECTION FEEDBACK BAR ===== */
.cbw-sel-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: #ffffff;
  border: 1px solid #15803d;
  border-radius: 8px;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 14px;
}

.cbw-sel-bar--error {
  background: #fef2f2;
  border-color: #fecaca;
}

.cbw-sel-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cbw-sel-info strong {
  font-size: 13.5px;
  color: #0f172a;
  font-weight: 500;
}

.cbw-sel-info span {
  font-size: 12.5px;
  color: #0f172a;
}

.cbw-clear-btn {
  background: transparent;
  border: none;
  padding: 4px 0;
  font-size: 13px;
  font-weight: 500;
  color: #dc2626;
  cursor: pointer;
  transition: opacity 0.15s ease;
}

.cbw-clear-btn:hover {
  opacity: 0.8;
}

/* ===== RIGHT SUMMARY PANEL ===== */
.cbw-summary-panel {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 24px 20px;
  background: #ffffff;
  position: sticky;
  top: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  box-shadow: none;
}

.cbw-summary-header {
  margin-bottom: 0;
}

.cbw-summary-label {
  display: inline-block;
  font-size: 11px;
  font-weight: 500;
  color: #15803d;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.cbw-summary-title {
  font-size: 15.5px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 4px;
}

.cbw-summary-sub {
  font-size: 12.5px;
  color: #334155;
  margin: 0;
  line-height: 1.5;
}

.cbw-divider {
  display: none;
}

/* ===== FACTS ===== */
.cbw-facts {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cbw-fact-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  gap: 8px;
}

.cbw-fact-row span {
  color: #334155;
  font-weight: 400;
  flex-shrink: 0;
}

.cbw-fact-row strong {
  color: #0f172a;
  font-weight: 500;
  text-align: right;
}

/* ===== PRICE ROWS ===== */
.cbw-price-rows {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cbw-price-head {
  font-size: 12px;
  font-weight: 500;
  color: #0f172a;
  margin-bottom: 2px;
}

.cbw-price-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12.5px;
  gap: 8px;
  padding: 4px 0;
  border: none;
}

.cbw-price-row span {
  color: #1e293b;
  font-weight: 400;
  flex: 1;
  min-width: 0;
  line-height: 1.3;
}

.cbw-price-row strong {
  color: #0f172a;
  font-weight: 500;
  flex-shrink: 0;
}

/* ===== DISCOUNT & VOUCHER ===== */
.cbw-discount-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cbw-discount-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
}

.cbw-discount-val {
  color: #15803d;
  font-weight: 500;
}

.cbw-voucher-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: 10px 13px;
  font-size: 13px;
  color: #0f172a;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  cursor: pointer;
  text-align: left;
  font-weight: 400;
  transition: border-color 0.15s;
}

.cbw-voucher-btn:hover {
  border-color: #15803d;
}

.cbw-voucher-count {
  font-size: 11.5px;
  color: #334155;
  font-weight: 400;
}

.cbw-voucher-notice {
  font-size: 12px;
  color: #b45309;
  margin: 0;
}

.cbw-voucher-list {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.cbw-voucher-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 13px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  cursor: pointer;
  font-size: 13px;
  color: #0f172a;
  text-align: left;
  transition: border-color 0.15s;
}

.cbw-voucher-item:hover:not(.is-active) {
  border-color: #15803d;
}

.cbw-voucher-item.is-active {
  border-color: #15803d;
  background: #ffffff;
}

.cbw-voucher-item > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cbw-voucher-item > div strong {
  font-weight: 500;
  color: #0f172a;
  font-size: 13px;
}

.cbw-voucher-item > div span {
  font-size: 11.5px;
  color: #334155;
}

.cbw-voucher-item > strong {
  color: #15803d;
  font-weight: 500;
  flex-shrink: 0;
}

.cbw-voucher-empty {
  font-size: 12.5px;
  color: #334155;
  margin: 0;
  text-align: center;
  padding: 8px;
}

/* ===== PAYMENT SECTION ===== */
.cbw-payment-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.cbw-payment-title {
  font-size: 12.5px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
}

.cbw-wallet-bal {
  font-size: 12.5px;
  color: #1e293b;
  margin: 0;
  padding: 8px 12px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
}

.cbw-wallet-bal strong {
  color: #15803d;
  font-weight: 500;
}

.cbw-payment-opts {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cbw-payment-opt {
  display: flex;
  align-items: flex-start;
  gap: 11px;
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  cursor: pointer;
  transition: border-color 0.15s;
}

.cbw-payment-opt:hover:not(.is-active):not(.is-disabled) {
  border-color: #15803d;
}

.cbw-payment-opt input[type="radio"] {
  width: 16px;
  height: 16px;
  margin-top: 3px;
  accent-color: #15803d;
  flex-shrink: 0;
  cursor: pointer;
}

.cbw-payment-opt > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cbw-payment-opt > div strong {
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
  line-height: 1.3;
}

.cbw-payment-opt > div span {
  font-size: 11.5px;
  color: #334155;
  line-height: 1.4;
}

.cbw-payment-opt.is-active {
  border-color: #15803d;
  background: #ffffff;
}

.cbw-payment-opt.is-active > div strong {
  color: #15803d;
}

.cbw-payment-opt.is-disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

/* ===== TOTAL & SUBMIT ===== */
.cbw-total-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 14px;
  color: #0f172a;
  margin-bottom: 4px;
  font-weight: 500;
}

.cbw-total-val {
  font-size: 18px;
  font-weight: 600;
  color: #15803d;
}

.cbw-required-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12.5px;
  color: #1e293b;
  margin-bottom: 14px;
  background: #ffffff;
  border: 1px solid #f1f5f9;
  border-radius: 4px;
  padding: 8px 12px;
}

.cbw-required-row strong {
  color: #0f172a;
  font-weight: 500;
}

.cbw-submit-error {
  font-size: 12.5px;
  color: #dc2626;
  margin: 0 0 10px;
  padding: 8px 12px;
  background: #fef2f2;
  border-radius: 6px;
  border: 1px solid #fecaca;
}

.cbw-submit-btn {
  width: 100%;
  padding: 12px;
  background: #15803d;
  color: #ffffff;
  font-size: 14px;
  font-weight: 500;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.15s;
}

.cbw-submit-btn:hover:not(:disabled) {
  background: #166534;
}

.cbw-submit-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.cbw-hold-note {
  font-size: 11.5px;
  color: #475569;
  margin: 10px 0 0;
  text-align: center;
  line-height: 1.5;
}

/* ===== SKELETON LOADING ===== */
.cbw-skel {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 8px;
  padding: 16px;
}

.cbw-skel span {
  height: 44px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: cbw-shimmer 1.4s infinite;
}

@keyframes cbw-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ===== RECURRING PLANNER (FLAT, UNIFIED) ===== */
.cbw-rec-planner {
  display: flex;
  flex-direction: column;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 24px 28px;
  gap: 28px;
}

.cbw-rec-sec {
  padding-bottom: 0;
  margin-bottom: 0;
  border: none;
}

.cbw-rec-sec--last {
  padding-bottom: 0;
  margin-bottom: 0;
  border: none;
}

.cbw-rec-sec-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 12px;
}

.cbw-rec-sec-title {
  margin: 0;
  font-size: 15px;
  font-weight: 500;
  color: #0f172a;
}

.cbw-rec-quick-presets {
  display: flex;
  align-items: center;
  gap: 6px;
}

.cbw-rec-preset-btn {
  background: transparent;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 5px 12px;
  font-size: 12.5px;
  font-weight: 400;
  color: #1e293b;
  cursor: pointer;
  transition: all 0.15s ease;
}

.cbw-rec-preset-btn:hover {
  border-color: #15803d;
  color: #15803d;
}

.cbw-rec-form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
}

.cbw-rec-form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cbw-rec-form-lbl {
  font-size: 12.5px;
  font-weight: 400;
  color: #0f172a;
}

.cbw-rec-input {
  height: 38px;
  padding: 0 10px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #ffffff;
  font-size: 13.5px;
  color: #0f172a;
  outline: none;
  transition: border-color 0.15s ease;
}

.cbw-rec-input:focus {
  border-color: #15803d;
}

/* WEEKDAYS FLAT ROW */
.cbw-weekday-row {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 8px;
  margin-bottom: 20px;
}

@media (max-width: 900px) {
  .cbw-weekday-row {
    grid-template-columns: repeat(4, 1fr);
  }
}
@media (max-width: 550px) {
  .cbw-weekday-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

.cbw-day-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 10px 4px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
  cursor: pointer;
  transition: all 0.15s ease;
  min-height: 72px;
}

.cbw-day-box:hover {
  border-color: #cbd5e1;
}

.cbw-day-box.is-selected {
  border-color: #15803d;
  background: #ffffff;
}

.cbw-day-box-short {
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
}

.cbw-day-box.is-selected .cbw-day-box-short {
  color: #15803d;
}

.cbw-day-box-lbl {
  font-size: 11px;
  color: #334155;
  font-weight: 400;
  margin-top: 2px;
}

.cbw-day-box-status {
  font-size: 10.5px;
  font-weight: 400;
  color: #15803d;
  margin-top: 4px;
  text-align: center;
  line-height: 1.2;
}

.cbw-day-box-empty {
  font-size: 10.5px;
  color: #475569;
  margin-top: 4px;
}

/* DAY SCHEDULE SETTINGS */
.cbw-day-sched-block {
  padding-top: 14px;
  border: none;
  margin-top: 10px;
}

.cbw-day-sched-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  flex-wrap: wrap;
  gap: 8px;
}

.cbw-day-sched-title {
  font-size: 13px;
  color: #0f172a;
  font-weight: 500;
}

.cbw-copy-link {
  background: transparent;
  border: none;
  color: #15803d;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
  transition: opacity 0.15s ease;
}

.cbw-copy-link:hover {
  opacity: 0.8;
}

/* ACTION & RESULT */
.cbw-rec-check-btn-wrap {
  margin-bottom: 12px;
}

.cbw-rec-btn-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 40px;
  background: #15803d;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s ease;
}

.cbw-rec-btn-action:hover:not(:disabled) {
  background: #166534;
}

.cbw-rec-btn-action:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.cbw-rec-msg-success {
  font-size: 13.5px;
  color: #15803d;
  font-weight: 400;
  margin-top: 8px;
}

.cbw-rec-msg-warn {
  font-size: 13.5px;
  color: #b45309;
  font-weight: 400;
  margin-top: 8px;
}

.cbw-rec-msg-error {
  font-size: 13px;
  color: #dc2626;
  font-weight: 400;
  margin-top: 8px;
}

/* CONFLICT TABLE */
.cbw-conflict-table {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
  border: none;
  padding-top: 0;
}

.cbw-conflict-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border: 1px solid #fde68a;
  border-radius: 6px;
  background: #fffbeb;
  gap: 12px;
  flex-wrap: wrap;
}

.cbw-conflict-date {
  font-size: 13px;
  color: #78350f;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
}

.cbw-conflict-controls {
  display: flex;
  align-items: center;
  gap: 16px;
}

.cbw-conflict-radio {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: #78350f;
  font-weight: 400;
  cursor: pointer;
}

.cbw-conflict-select {
  height: 28px;
  padding: 0 6px;
  border: 1px solid #d97706;
  border-radius: 4px;
  background: #ffffff;
  font-size: 12px;
  color: #0f172a;
}

/* ===== RESPONSIVE STYLES ===== */
@media (max-width: 1024px) {
  .cbw-workspace {
    grid-template-columns: 1fr;
  }
  .cbw-summary-panel {
    position: static;
  }
}

@media (max-width: 640px) {
  .cbw-main {
    padding: 16px 14px 60px;
  }
  .cbw-steps {
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
  }
  .cbw-step {
    max-width: 100%;
    justify-content: flex-start;
  }
  .cbw-filters {
    grid-template-columns: 1fr;
  }
  .cbw-period-tabs {
    width: 100%;
  }
  .cbw-period-btn {
    flex: 1;
    justify-content: center;
  }
}
</style>

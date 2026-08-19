<template>
  <div class="pos-workspace">
    <!-- 1. TOP OPERATIONAL BAR: DATE + TIME PERIOD + SEARCH -->
    <header class="pos-top-bar">
      <div class="pos-top-left">
        <!-- Date Switcher -->
        <div class="pos-date-group">
          <div class="pos-date-nav">
            <button
              type="button"
              class="pos-nav-btn"
              title="Ngày trước"
              aria-label="Ngày trước"
              @click="shiftDate(-1)"
            >
              <AppIcon name="chevronLeft" :size="16" />
            </button>
            <button
              type="button"
              class="pos-chip-btn"
              :class="{ active: isToday(filters.booking_date) }"
              @click="setToday"
            >
              Hôm nay
            </button>
            <button
              type="button"
              class="pos-chip-btn"
              :class="{ active: isTomorrow(filters.booking_date) }"
              @click="setTomorrow"
            >
              Ngày mai
            </button>
            <button
              type="button"
              class="pos-nav-btn"
              title="Ngày sau"
              aria-label="Ngày sau"
              @click="shiftDate(1)"
            >
              <AppIcon name="chevronRight" :size="16" />
            </button>
          </div>

          <!-- MiniCalendar Dropdown Trigger -->
          <div class="pos-cal-dropdown">
            <button
              type="button"
              class="pos-cal-btn"
              :class="{ active: showCalDropdown }"
              @click="showCalDropdown = !showCalDropdown"
            >
              <AppIcon name="calendar" :size="15" />
              <span>{{ formattedCurrentDate }}</span>
              <AppIcon name="chevronDown" :size="13" />
            </button>
            <div v-if="showCalDropdown" class="pos-cal-popover">
              <MiniCalendar
                mode="single"
                :model-value="filters.booking_date"
                @update:model-value="onDateSelect"
              />
            </div>
          </div>
        </div>

        <!-- Shift / Time Period Tabs -->
        <div class="pos-period-tabs">
          <button
            v-for="period in timePeriods"
            :key="period.key"
            type="button"
            class="pos-period-btn"
            :class="{ active: activeTimePeriod === period.key }"
            @click="activeTimePeriod = period.key"
          >
            <span>{{ period.label }}</span>
            <small>{{ period.range }}</small>
          </button>
        </div>
      </div>

      <!-- Right: Search & Quick Refresh -->
      <div class="pos-top-right">
        <div class="pos-search-box">
          <AppIcon name="search" :size="13" class="pos-search-icon" />
          <input
            v-model.trim="searchKeyword"
            type="text"
            placeholder="Tìm tên, SĐT, mã (F2)..."
            maxlength="60"
          />
          <button
            v-if="searchKeyword"
            type="button"
            class="pos-search-clear"
            aria-label="Xóa tìm kiếm"
            @click="searchKeyword = ''"
          >
            ×
          </button>
        </div>

        <button
          type="button"
          class="pos-btn-refresh"
          title="Tải lại dữ liệu (F3)"
          @click="loadBookings"
        >
          <AppIcon name="rotateCw" :size="13" :class="{ 'animate-spin': loading || scheduleLoading }" />
          <span>Tải lại</span>
        </button>
      </div>
    </header>

    <!-- 2. HIGH-IMPACT OPERATIONAL KPI STRIP -->
    <section class="pos-kpi-strip">
      <div class="pos-kpi-cell is-playing">
        <span class="pos-kpi-val">{{ kpiStats.playingCount }}</span>
        <span class="pos-kpi-label">Đang trong sân</span>
      </div>
      <div class="pos-kpi-cell is-confirmed">
        <span class="pos-kpi-val">{{ kpiStats.confirmedCount }}</span>
        <span class="pos-kpi-label">Chờ check-in</span>
      </div>
      <div class="pos-kpi-cell is-pending">
        <span class="pos-kpi-val">{{ kpiStats.pendingCount }}</span>
        <span class="pos-kpi-label">Chờ xử lý / Đợi cọc</span>
      </div>
      <div class="pos-kpi-cell is-unpaid">
        <span class="pos-kpi-val">{{ formatCurrency(kpiStats.unpaidAmount) }}</span>
        <span class="pos-kpi-label">Chưa thu tiền ({{ kpiStats.unpaidCount }} đơn)</span>
      </div>
      <div class="pos-kpi-cell is-revenue">
        <span class="pos-kpi-val">{{ formatCurrency(kpiStats.collectedAmount) }}</span>
        <span class="pos-kpi-label">Đã thu hôm nay</span>
      </div>
    </section>

    <!-- Alerts / Flash Notices -->
    <div v-if="error" class="pos-alert is-error">
      <span>{{ error }}</span>
      <button type="button" @click="error = ''">×</button>
    </div>
    <div v-if="notice" class="pos-alert is-success">
      <span>{{ notice }}</span>
      <button type="button" @click="notice = ''">×</button>
    </div>

    <!-- 3. INTERACTIVE OPERATIONAL MATRIX -->
    <section class="pos-matrix-container">
      <div v-if="loading || scheduleLoading" class="pos-state-box">
        <div class="pos-spinner"></div>
        <p>Đang đồng bộ dữ liệu thời gian thực...</p>
      </div>
      <div v-else-if="scheduleError" class="pos-state-box is-error">
        <img :src="'/images/staff/pos_terminal_3d.jpg'" alt="3D POS Terminal" class="pos-3d-terminal-img" />
        <div class="pos-3d-empty-info">
          <h4>Không thể kết nối dữ liệu sân</h4>
          <p>{{ scheduleError }}</p>
          <button type="button" class="pos-btn-retry" @click="loadBookings">Thử lại</button>
        </div>
      </div>
      <div v-else-if="!visibleCourts.length" class="pos-state-box">
        <img :src="'/images/staff/pos_terminal_3d.jpg'" alt="3D POS Terminal" class="pos-3d-terminal-img" />
        <div class="pos-3d-empty-info">
          <h4>Chưa có dữ liệu sân thi đấu</h4>
          <p>Cụm sân này hiện chưa có danh sách sân hoặc bạn chưa được phân quyền truy cập sân.</p>
        </div>
      </div>

      <!-- Live Terminal Grid -->
      <div v-else class="pos-grid-scroller">
        <table class="pos-table">
          <thead>
            <tr>
              <th class="pos-th-time">Khung Giờ</th>
              <th
                v-for="court in visibleCourts"
                :key="court.id"
                class="pos-th-court"
              >
                <div class="pos-court-header">
                  <strong class="pos-court-name">{{ court.name }}</strong>
                  <span class="pos-court-type">{{ court.court_type?.name || 'Sân chuẩn' }}</span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="slot in verticalHourlySlots"
              :key="slot.start"
              class="pos-tr-row"
            >
              <!-- Monospace Time Mark -->
              <td class="pos-td-time">
                <span class="pos-time-mark">{{ slot.label }}</span>
              </td>

              <!-- Court Slot Interactive Cells -->
              <td
                v-for="court in visibleCourts"
                :key="`${court.id}-${slot.start}`"
                class="pos-td-cell"
                :class="{ 'is-highlight': isSlotHighlighted(court.id, slot) }"
                @click="onCellClick(court, slot)"
              >
                <!-- Booked or Locked Block -->
                <div
                  v-if="getBlockForCourtAndSlot(court.id, slot)"
                  class="pos-slot-card"
                  :class="[
                    getBlockForCourtAndSlot(court.id, slot).kindClass,
                    { 'is-selected': selectedTimelineItem?.key === getBlockForCourtAndSlot(court.id, slot).key }
                  ]"
                  @click.stop="selectTimelineItem(getBlockForCourtAndSlot(court.id, slot))"
                >
                  <div class="pos-slot-card-accent"></div>
                  <div class="pos-slot-card-content">
                    <div class="pos-slot-top">
                      <strong class="pos-slot-title">
                        {{ getBlockForCourtAndSlot(court.id, slot).title }}
                      </strong>
                      <span
                        v-if="getBlockForCourtAndSlot(court.id, slot).type === 'booking'"
                        class="pos-slot-code"
                      >
                        #{{ getBlockForCourtAndSlot(court.id, slot).booking?.booking_code || '' }}
                      </span>
                    </div>

                    <div class="pos-slot-bottom">
                      <span class="pos-slot-time">
                        {{ getBlockForCourtAndSlot(court.id, slot).timeLabel }}
                      </span>
                      <span
                        v-if="getBlockForCourtAndSlot(court.id, slot).statusPill"
                        class="pos-slot-status-text"
                        :class="getBlockForCourtAndSlot(court.id, slot).statusPill.type"
                      >
                        {{ getBlockForCourtAndSlot(court.id, slot).statusPill.label }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Empty Slot: Instant Walk-in Quick Book -->
                <div v-else class="pos-empty-slot">
                  <span class="pos-empty-action">+ Đặt nhanh</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 4. MODAL / DRAWER: VIEW & ACTION ON BOOKING -->
    <Teleport to="body">
      <div
        v-if="drawerMode"
        class="pos-drawer-backdrop"
        @click.self="closeDrawer"
      >
        <!-- A. VIEW & QUICK ACTIONS DRAWER -->
        <aside v-if="drawerMode === 'view'" class="pos-drawer-sheet">
          <header class="pos-drawer-head">
            <div>
              <span class="pos-drawer-kicker">THÔNG TIN ĐẶT SÂN</span>
              <h3 class="pos-drawer-title">{{ selectedTimelineItem?.title }}</h3>
              <p class="pos-drawer-subtitle">
                {{ selectedTimelineItem?.timeLabel }} · {{ selectedTimelineItem?.courtName }}
              </p>
            </div>
            <button
              type="button"
              class="pos-drawer-close-btn"
              aria-label="Đóng"
              @click="closeDrawer"
            >
              ✕
            </button>
          </header>

          <div class="pos-drawer-body">
            <template v-if="selectedTimelineBooking">
              <!-- Clear Information Grid -->
              <div class="pos-info-list">
                <div class="pos-info-row">
                  <span class="pos-info-label">Mã đặt sân</span>
                  <strong class="pos-info-val">#{{ selectedTimelineBooking.booking_code }}</strong>
                </div>
                <div class="pos-info-row">
                  <span class="pos-info-label">Khách hàng</span>
                  <strong class="pos-info-val">{{ customerName(selectedTimelineBooking) }}</strong>
                </div>
                <div class="pos-info-row">
                  <span class="pos-info-label">Số điện thoại</span>
                  <strong class="pos-info-val">{{ customerPhone(selectedTimelineBooking) }}</strong>
                </div>
                <div class="pos-info-row">
                  <span class="pos-info-label">Trạng thái</span>
                  <strong class="pos-info-val" :class="selectedTimelineBooking.status">
                    {{ statusLabel(selectedTimelineBooking.status) }}
                  </strong>
                </div>
                <div class="pos-info-row">
                  <span class="pos-info-label">Tổng tiền</span>
                  <strong class="pos-info-val is-money">
                    {{ formatCurrency(selectedTimelineBooking.total_price) }}
                  </strong>
                </div>
                <div class="pos-info-row">
                  <span class="pos-info-label">Đã thanh toán</span>
                  <strong class="pos-info-val is-paid">
                    {{ formatCurrency(paidAmount(selectedTimelineBooking)) }}
                  </strong>
                </div>
                <div
                  v-if="outstandingAmount(selectedTimelineBooking) > 0"
                  class="pos-info-row is-due"
                >
                  <span class="pos-info-label">Còn phải thu</span>
                  <strong class="pos-info-val is-outstanding">
                    {{ formatCurrency(outstandingAmount(selectedTimelineBooking)) }}
                  </strong>
                </div>
              </div>

              <!-- FAST POS ACTION BUTTONS -->
              <div class="pos-actions-stack">
                <!-- 1. Check-in Button -->
                <button
                  v-if="selectedTimelineBooking.status === 'confirmed'"
                  type="button"
                  class="pos-action-btn is-checkin"
                  :disabled="updatingStatus"
                  @click="runBookingAction(selectedTimelineBooking, 'check_in')"
                >
                  <AppIcon name="clock" :size="18" />
                  <span>Check-in khách vào sân</span>
                </button>

                <!-- 2. Collect Payment Button -->
                <button
                  v-if="canCollectPayment(selectedTimelineBooking)"
                  type="button"
                  class="pos-action-btn is-collect"
                  @click="runBookingAction(selectedTimelineBooking, 'collect')"
                >
                  <AppIcon name="banknote" :size="18" />
                  <span>Thu tiền ({{ formatCurrency(outstandingAmount(selectedTimelineBooking)) }})</span>
                </button>

                <!-- 3. Complete Button -->
                <button
                  v-if="selectedTimelineBooking.status === 'checked_in' && !canCollectPayment(selectedTimelineBooking)"
                  type="button"
                  class="pos-action-btn is-complete"
                  :disabled="updatingStatus"
                  @click="runBookingAction(selectedTimelineBooking, 'complete')"
                >
                  <AppIcon name="circleCheck" :size="18" />
                  <span>Hoàn thành lượt chơi</span>
                </button>

                <!-- 4. Change Court Button -->
                <button
                  v-if="canChangeCourt(selectedTimelineBooking)"
                  type="button"
                  class="pos-action-btn is-change"
                  @click="openChangeCourt(selectedTimelineBooking)"
                >
                  <AppIcon name="pencil" :size="16" />
                  <span>Đổi sân thực tế</span>
                </button>

                <!-- 5. Reject / Cancel Button -->
                <button
                  v-if="['pending_approval', 'pending_payment', 'confirmed'].includes(selectedTimelineBooking.status)"
                  type="button"
                  class="pos-action-btn is-cancel"
                  @click="openStatusAction(selectedTimelineBooking, selectedTimelineBooking.status === 'pending_approval' ? 'reject' : 'cancel')"
                >
                  <AppIcon name="trash" :size="16" />
                  <span>{{ selectedTimelineBooking.status === 'pending_approval' ? 'Từ chối đơn' : 'Hủy booking' }}</span>
                </button>
              </div>
            </template>

            <!-- Locked Slot Details -->
            <template v-else>
              <div class="pos-info-list">
                <div class="pos-info-row">
                  <span class="pos-info-label">Trạng thái</span>
                  <strong class="pos-info-val">Khóa sân</strong>
                </div>
                <div class="pos-info-row">
                  <span class="pos-info-label">Lý do</span>
                  <strong class="pos-info-val">{{ selectedTimelineItem?.subtitle || 'Bảo trì / Đóng sân' }}</strong>
                </div>
                <div class="pos-info-row">
                  <span class="pos-info-label">Khung giờ</span>
                  <strong class="pos-info-val">{{ selectedTimelineItem?.timeLabel }}</strong>
                </div>
              </div>
            </template>
          </div>
        </aside>

        <!-- B. QUICK WALK-IN BOOKING DRAWER -->
        <aside v-else-if="drawerMode === 'create'" class="pos-drawer-sheet is-create">
          <header class="pos-drawer-head">
            <div>
              <span class="pos-drawer-kicker">ĐẶT SÂN NHANH TẠI QUẦY (WALK-IN)</span>
              <h3 class="pos-drawer-title">{{ createSlot?.court?.name }}</h3>
              <p class="pos-drawer-subtitle">{{ formatDate(filters.booking_date) }} · {{ createSlot?.slot?.label }}</p>
            </div>
            <button
              type="button"
              class="pos-drawer-close-btn"
              aria-label="Đóng"
              @click="closeDrawer"
            >
              ✕
            </button>
          </header>

          <form class="pos-drawer-body" @submit.prevent="submitCounterBooking">
            <!-- Customer Information -->
            <div class="pos-field-group">
              <label class="pos-field">
                <span class="pos-field-label">Tên khách hàng <strong class="req">*</strong></span>
                <input
                  v-model.trim="counterForm.walk_in_name"
                  type="text"
                  class="pos-input"
                  placeholder="Nhập tên khách vãng lai..."
                  required
                  maxlength="100"
                />
              </label>

              <label class="pos-field">
                <span class="pos-field-label">Số điện thoại <strong class="req">*</strong></span>
                <input
                  v-model.trim="counterForm.walk_in_phone"
                  type="tel"
                  class="pos-input"
                  placeholder="Ví dụ: 0901234567"
                  required
                  maxlength="15"
                />
              </label>
            </div>

            <!-- Payment Mode Selector -->
            <div class="pos-field">
              <span class="pos-field-label">Hình thức thanh toán</span>
              <div class="pos-mode-grid">
                <button
                  type="button"
                  class="pos-mode-card"
                  :class="{ active: counterForm.collection_mode === 'cash' }"
                  @click="counterForm.collection_mode = 'cash'"
                >
                  <AppIcon name="banknote" :size="18" />
                  <strong>Tiền mặt</strong>
                  <small>Thu ngay tại quầy</small>
                </button>
                <button
                  type="button"
                  class="pos-mode-card"
                  :class="{ active: counterForm.collection_mode === 'transfer' }"
                  @click="counterForm.collection_mode = 'transfer'"
                >
                  <AppIcon name="creditCard" :size="18" />
                  <strong>VietQR SePay</strong>
                  <small>Quét mã đối soát tự động</small>
                </button>
                <button
                  type="button"
                  class="pos-mode-card"
                  :class="{ active: counterForm.collection_mode === 'later' }"
                  @click="counterForm.collection_mode = 'later'"
                >
                  <AppIcon name="clock" :size="18" />
                  <strong>Thu sau</strong>
                  <small>Ghi nhận thu sau trận</small>
                </button>
              </div>
            </div>

            <!-- VietQR SePay Waiting Container -->
            <div v-if="counterQr" class="pos-qr-container">
              <img :src="counterQr.qr_url" alt="VietQR SePay" class="pos-qr-image" />
              <div class="pos-qr-details">
                <span>Nội dung CK:</span>
                <button type="button" class="pos-qr-copy-btn" @click="copyText(counterQr.transfer_content)">
                  {{ counterQr.transfer_content }} (Bấm để copy)
                </button>
                <span>Số tiền: <strong>{{ formatCurrency(counterQr.payment?.amount) }}</strong></span>
              </div>
              <small class="pos-qr-status">Đang đợi khách quét mã chuyển khoản...</small>
            </div>

            <!-- Footer Confirm Buttons -->
            <div class="pos-drawer-foot">
              <button type="button" class="pos-btn-plain" @click="closeDrawer">Hủy</button>
              <button
                type="submit"
                class="pos-btn-primary"
                :disabled="!counterFormValid || counterSubmitting"
              >
                {{ counterSubmitting ? 'Đang tạo đơn...' : 'Xác nhận tạo đơn' }}
              </button>
            </div>
          </form>
        </aside>
      </div>
    </Teleport>

    <!-- 5. CHANGE COURT MODAL -->
    <Teleport to="body">
      <div v-if="changeCourtBooking" class="pos-drawer-backdrop" @click.self="closeChangeCourt">
        <form class="pos-modal-box" @submit.prevent="saveChangeCourt">
          <header class="pos-modal-head">
            <div>
              <h3>Đổi Sân Thực Tế</h3>
              <p>Chuyển booking sang sân trống khác cùng khung giờ</p>
            </div>
            <button type="button" class="pos-drawer-close-btn" @click="closeChangeCourt">✕</button>
          </header>
          <div class="pos-modal-body">
            <label class="pos-field">
              <span class="pos-field-label">Chọn sân đích mới</span>
              <select v-model="changeCourtForm.venue_court_id" class="pos-input" required>
                <option v-for="court in changeCourtOptions" :key="court.id" :value="court.id">
                  {{ court.name }} · {{ court.court_type?.name }}
                </option>
              </select>
            </label>
            <label class="pos-field">
              <span class="pos-field-label">Lý do đổi sân</span>
              <textarea
                v-model.trim="changeCourtForm.court_changed_reason"
                class="pos-input"
                rows="3"
                placeholder="Ví dụ: Khách yêu cầu chuyển sang sân gần khán đài..."
                required
              ></textarea>
            </label>
          </div>
          <footer class="pos-modal-foot">
            <button type="button" class="pos-btn-plain" @click="closeChangeCourt">Hủy</button>
            <button type="submit" class="pos-btn-primary" :disabled="savingChangeCourt">
              {{ savingChangeCourt ? 'Đang lưu...' : 'Xác nhận đổi sân' }}
            </button>
          </footer>
        </form>
      </div>
    </Teleport>

    <!-- 6. COLLECT PAYMENT MODAL (TIỀN MẶT / SEPAY QR) -->
    <Teleport to="body">
      <div v-if="collectBooking" class="pos-drawer-backdrop" @click.self="closeCollectPayment">
        <form class="pos-modal-box is-collect" @submit.prevent="submitCollectPayment">
          <header class="pos-modal-head">
            <div>
              <h3>Thu Tiền Booking #{{ collectBooking.booking_code }}</h3>
              <p>{{ customerName(collectBooking) }} · {{ customerPhone(collectBooking) }}</p>
            </div>
            <button type="button" class="pos-drawer-close-btn" @click="closeCollectPayment">✕</button>
          </header>

          <div class="pos-modal-body">
            <div class="pos-collect-stats">
              <div class="pos-cstat">
                <span>Tổng tiền</span>
                <strong>{{ formatCurrency(collectBooking.total_price) }}</strong>
              </div>
              <div class="pos-cstat">
                <span>Đã thu</span>
                <strong class="is-paid">{{ formatCurrency(paidAmount(collectBooking)) }}</strong>
              </div>
              <div class="pos-cstat is-due">
                <span>Còn lại</span>
                <strong class="is-outstanding">{{ formatCurrency(outstandingAmount(collectBooking)) }}</strong>
              </div>
            </div>

            <label class="pos-field">
              <span class="pos-field-label">Số tiền thu lần này</span>
              <input
                v-model.number="collectForm.amount"
                type="number"
                class="pos-input"
                min="1000"
                step="1000"
                :disabled="collectForm.payment_method === 'sepay' && !!pendingTransfer(collectBooking)"
                required
              />
            </label>

            <div class="pos-field">
              <span class="pos-field-label">Phương thức thu</span>
              <div class="pos-mode-grid">
                <button
                  type="button"
                  class="pos-mode-card"
                  :class="{ active: collectForm.payment_method === 'cash' }"
                  @click="collectForm.payment_method = 'cash'"
                >
                  <AppIcon name="banknote" :size="18" />
                  <strong>Tiền mặt</strong>
                </button>
                <button
                  type="button"
                  class="pos-mode-card"
                  :class="{ active: collectForm.payment_method === 'sepay' }"
                  @click="collectForm.payment_method = 'sepay'"
                >
                  <AppIcon name="creditCard" :size="18" />
                  <strong>VietQR SePay</strong>
                </button>
              </div>
            </div>

            <!-- VietQR SePay Image & Polling Notice -->
            <div v-if="collectQr" class="pos-qr-container">
              <img :src="collectQr.qr_url" alt="Mã VietQR" class="pos-qr-image" />
              <div class="pos-qr-details">
                <span>Nội dung chuyển khoản:</span>
                <button type="button" class="pos-qr-copy-btn" @click="copyText(collectQr.transfer_content)">
                  {{ collectQr.transfer_content }} (Bấm để copy)
                </button>
                <span>Số tiền: <strong>{{ formatCurrency(collectQr.payment?.amount) }}</strong></span>
              </div>
              <small class="pos-qr-status">Hệ thống đang tự động đối soát ngân hàng...</small>
            </div>
          </div>

          <footer class="pos-modal-foot">
            <button type="button" class="pos-btn-plain" @click="closeCollectPayment">Đóng</button>
            <button type="submit" class="pos-btn-primary" :disabled="collectingPayment">
              {{ collectSubmitLabel() }}
            </button>
          </footer>
        </form>
      </div>
    </Teleport>

    <!-- 7. STATUS ACTION MODAL (REJECT / CANCEL) -->
    <Teleport to="body">
      <div v-if="statusActionBooking" class="pos-drawer-backdrop" @click.self="closeStatusAction">
        <form class="pos-modal-box is-danger" @submit.prevent="submitStatusAction">
          <header class="pos-modal-head">
            <div>
              <h3>{{ statusActionTitle() }}</h3>
              <p>#{{ statusActionBooking.booking_code }} · {{ customerName(statusActionBooking) }}</p>
            </div>
            <button type="button" class="pos-drawer-close-btn" @click="closeStatusAction">✕</button>
          </header>
          <div class="pos-modal-body">
            <p class="pos-danger-note">
              {{ statusAction === 'reject'
                ? 'Booking sẽ bị từ chối và khung sân sẽ được giải phóng ngay lập tức.'
                : 'Booking sẽ bị hủy. Nếu đã thanh toán, hệ thống sẽ tự tạo yêu cầu hoàn tiền.' }}
            </p>
            <label class="pos-field">
              <span class="pos-field-label">Lý do {{ statusAction === 'reject' ? 'từ chối' : 'hủy đơn' }}</span>
              <textarea
                v-model.trim="statusActionReason"
                class="pos-input"
                rows="3"
                maxlength="1000"
                placeholder="Nhập lý do chi tiết..."
                required
              ></textarea>
            </label>
          </div>
          <footer class="pos-modal-foot">
            <button type="button" class="pos-btn-plain" @click="closeStatusAction">Đóng</button>
            <button type="submit" class="pos-btn-danger" :disabled="updatingStatus">
              {{ statusAction === 'reject' ? 'Xác nhận từ chối' : 'Xác nhận hủy đơn' }}
            </button>
          </footer>
        </form>
      </div>
    </Teleport>
  </div>
</template>

<script>
import { ownerBookingService } from '../../services/ownerBookings.js';
import { venueClusterService } from '../../services/venueClusters.js';
import { ownerStaffShiftService } from '../../services/ownerStaffShiftService.js';
import AppIcon from '../../components/AppIcon.vue';
import MiniCalendar from '../../components/MiniCalendar.vue';
import SportIllustration from '../../components/common/SportIllustration.vue';

function localIsoDate(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export default {
  name: 'StaffBookingsPOS',
  components: { AppIcon, MiniCalendar, SportIllustration },
  data() {
    return {
      clusters: [],
      courts: [],
      bookings: [],
      filters: {
        venue_cluster_id: '',
        venue_court_id: '',
        booking_date: localIsoDate(),
        status: '',
      },
      searchKeyword: '',
      loading: true,
      scheduleLoading: false,
      scheduleError: '',
      scheduleSlots: [],
      scheduleCourts: [],
      scheduleBusyIntervals: [],
      scheduleSlotStatuses: [],
      selectedTimelineItem: null,
      activeTimePeriod: 'business',
      error: '',
      notice: '',
      showCalDropdown: false,

      // Shift attendance state
      shiftLoading: false,
      todayShift: null,
      attendanceSubmitting: false,

      // Drawer states
      drawerMode: null, // null | 'view' | 'create'
      createSlot: null,
      counterForm: {
        walk_in_name: '',
        walk_in_phone: '',
        collection_mode: 'cash',
      },
      counterSubmitting: false,
      counterQr: null,
      counterPollInterval: null,

      // Change court & Collect payment states
      changeCourtBooking: null,
      changeCourtOptions: [],
      changeCourtForm: {
        venue_court_id: '',
        court_changed_reason: '',
      },
      savingChangeCourt: false,

      collectBooking: null,
      collectForm: {
        payment_method: 'cash',
        amount: 0,
      },
      collectQr: null,
      collectingPayment: false,
      collectPollInterval: null,

      statusActionBooking: null,
      statusAction: '',
      statusActionReason: '',
      updatingStatus: false,

      timePeriods: [
        { key: 'business', label: 'Giờ mở cửa', start: 360, end: 1380, range: '06:00 - 23:00' },
        { key: 'morning', label: 'Ca sáng', start: 360, end: 720, range: '06:00 - 12:00' },
        { key: 'afternoon', label: 'Ca chiều', start: 720, end: 1080, range: '12:00 - 18:00' },
        { key: 'evening', label: 'Ca tối', start: 1080, end: 1380, range: '18:00 - 23:00' },
        { key: 'full24h', label: 'Cả ngày 24h', start: 0, end: 1440, range: '00:00 - 24:00' },
      ],
    };
  },
  computed: {
    formattedCurrentDate() {
      if (!this.filters.booking_date) return '';
      const date = new Date(`${this.filters.booking_date}T00:00:00`);
      const days = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
      const dayName = days[date.getDay()];
      return `${dayName}, ${this.formatDate(this.filters.booking_date)}`;
    },
    visibleCourts() {
      return this.scheduleCourts.filter((court) => {
        if (!this.filters.venue_court_id) return true;
        return String(court.id) === String(this.filters.venue_court_id);
      });
    },
    activePeriod() {
      return this.timePeriods.find((period) => period.key === this.activeTimePeriod) || this.timePeriods[0];
    },
    verticalHourlySlots() {
      const slots = [];
      const step = 60;
      const period = this.activePeriod;

      for (let minutes = period.start; minutes < period.end; minutes += step) {
        slots.push({
          start: minutes,
          end: minutes + step,
          label: `${this.minutesToTime(minutes)} - ${this.minutesToTime(minutes + step)}`,
          timeLabel: `${this.minutesToTime(minutes)} - ${this.minutesToTime(minutes + step)}`,
        });
      }
      return slots;
    },
    kpiStats() {
      const allBlocks = this.timelineBlocks.filter((b) => b.type === 'booking');
      let playing = 0;
      let confirmed = 0;
      let pending = 0;
      let unpaid = 0;
      let unpaidMoney = 0;
      let collectedMoney = 0;

      for (const b of allBlocks) {
        const booking = b.booking;
        if (!booking) continue;
        if (booking.status === 'checked_in') playing++;
        if (booking.status === 'confirmed') confirmed++;
        if (['pending_approval', 'pending_payment'].includes(booking.status)) pending++;

        const outstanding = this.outstandingAmount(booking);
        if (outstanding > 0 && !['cancelled', 'rejected'].includes(booking.status)) {
          unpaid++;
          unpaidMoney += outstanding;
        }
        collectedMoney += this.paidAmount(booking);
      }

      return {
        playingCount: playing,
        confirmedCount: confirmed,
        pendingCount: pending,
        unpaidCount: unpaid,
        unpaidAmount: unpaidMoney,
        collectedAmount: collectedMoney,
      };
    },
    timelineBlocks() {
      const bookingBlocks = this.bookings.flatMap((booking) => {
        return this.bookingRanges(booking).map((range) => this.makeBookingBlock(booking, range)).filter(Boolean);
      });

      const bookingKeys = new Set(bookingBlocks.map((block) => `${block.courtId}|${block.start}|${block.end}`));
      const lockBlocks = this.scheduleBusyIntervals
        .filter((interval) => interval.source === 'slot_lock' && interval.status === 'manual')
        .map((interval) => this.makeLockBlock(interval))
        .filter((block) => block && !bookingKeys.has(`${block.courtId}|${block.start}|${block.end}`));

      return [...bookingBlocks, ...lockBlocks]
        .filter((block) => block.end > this.activePeriod.start && block.start < this.activePeriod.end)
        .sort((a, b) => a.start - b.start || a.end - b.end || a.title.localeCompare(b.title));
    },
    selectedTimelineBooking() {
      if (!this.selectedTimelineItem || this.selectedTimelineItem.type !== 'booking') return null;
      return this.bookings.find((booking) => String(booking.id) === String(this.selectedTimelineItem.bookingId)) || this.selectedTimelineItem.booking || null;
    },
    counterFormValid() {
      return this.counterForm.walk_in_name.trim().length >= 2 && this.counterForm.walk_in_phone.trim().length >= 8;
    },
    shiftDisplayName() {
      if (!this.todayShift) return '';
      return this.todayShift.shift?.name || `${this.formatTime(this.todayShift.start_time)} - ${this.formatTime(this.todayShift.end_time)}`;
    },
  },
  async mounted() {
    this.syncClusterFromStorage();
    window.addEventListener('owner-cluster-changed', this.handleClusterChanged);
    await Promise.all([this.loadClusters(), this.loadTodayShift()]);
    await this.loadBookings();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleClusterChanged);
    this.clearCollectPolling();
    this.clearCounterPolling();
  },
  methods: {
    syncClusterFromStorage() {
      this.filters.venue_cluster_id = localStorage.getItem('selected_cluster') || '';
    },
    handleClusterChanged() {
      this.syncClusterFromStorage();
      this.loadTodayShift();
      this.loadBookings();
    },
    isToday(iso) {
      return iso === localIsoDate();
    },
    isTomorrow(iso) {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      return iso === localIsoDate(tomorrow);
    },
    setToday() {
      this.filters.booking_date = localIsoDate();
      this.loadBookings();
    },
    setTomorrow() {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      this.filters.booking_date = localIsoDate(tomorrow);
      this.loadBookings();
    },
    shiftDate(days) {
      const current = new Date(`${this.filters.booking_date}T00:00:00`);
      current.setDate(current.getDate() + days);
      this.filters.booking_date = localIsoDate(current);
      this.loadBookings();
    },
    onDateSelect(val) {
      this.filters.booking_date = val;
      this.showCalDropdown = false;
      this.loadBookings();
    },

    async loadClusters() {
      try {
        const response = await venueClusterService.getClusters({ compact: 1 });
        this.clusters = response.data || [];
        if (!this.filters.venue_cluster_id && this.clusters.length) {
          this.filters.venue_cluster_id = String(this.clusters[0].id);
        }
      } catch {
        this.clusters = [];
      }
    },

    async loadTodayShift() {
      this.shiftLoading = true;
      try {
        const today = localIsoDate();
        const res = await ownerStaffShiftService.mySchedules({ start_date: today, end_date: today });
        const schedules = res.data || [];
        this.todayShift = schedules.find((s) => String(s.date) === today) || null;
      } catch {
        this.todayShift = null;
      } finally {
        this.shiftLoading = false;
      }
    },

    async loadBookings() {
      this.loading = true;
      this.scheduleLoading = true;
      this.scheduleError = '';
      this.error = '';

      try {
        const [bookingsRes, scheduleRes] = await Promise.all([
          ownerBookingService.list({
            venue_cluster_id: this.filters.venue_cluster_id || undefined,
            venue_court_id: this.filters.venue_court_id || undefined,
            booking_date: this.filters.booking_date || undefined,
          }),
          ownerBookingService.getSchedule({
            venue_cluster_id: this.filters.venue_cluster_id || undefined,
            booking_date: this.filters.booking_date || undefined,
            booking_type: 'single',
          }),
        ]);

        this.bookings = bookingsRes.data || [];
        this.scheduleCourts = scheduleRes.courts || [];
        this.scheduleBusyIntervals = scheduleRes.busy_intervals || [];
        this.scheduleSlotStatuses = scheduleRes.statuses || [];
      } catch (err) {
        this.scheduleError = err.message || 'Không thể tải lịch sân.';
        this.bookings = [];
        this.scheduleCourts = [];
      } finally {
        this.loading = false;
        this.scheduleLoading = false;
      }
    },

    getBlockForCourtAndSlot(courtId, slot) {
      return this.timelineBlocks.find(
        (block) => String(block.courtId) === String(courtId) && block.start <= slot.start && block.end > slot.start
      ) || null;
    },

    isSlotHighlighted(courtId, slot) {
      if (!this.searchKeyword) return false;
      const block = this.getBlockForCourtAndSlot(courtId, slot);
      if (!block) return false;
      const kw = this.searchKeyword.toLowerCase();
      return (
        block.title.toLowerCase().includes(kw) ||
        (block.booking?.booking_code || '').toLowerCase().includes(kw) ||
        (this.customerPhone(block.booking) || '').includes(kw)
      );
    },

    makeBookingBlock(booking, range) {
      const courtId = range.venueCourtId || booking.venue_court_id;
      if (!courtId) return null;

      const start = this.timeToMinutes(range.startTime);
      const end = this.timeToMinutes(range.endTime);
      if (end <= start) return null;

      const customer = this.customerName(booking);
      const paymentState = this.paymentState(booking);

      let statusPill = null;
      if (booking.status === 'checked_in') {
        statusPill = { type: 'playing', label: 'Đang chơi' };
      } else if (paymentState !== 'paid') {
        statusPill = { type: 'unpaid', label: 'Chưa thu' };
      } else {
        statusPill = { type: 'confirmed', label: 'Đã xong' };
      }

      return {
        key: `booking-${booking.id}-${courtId}-${range.startTime}-${range.endTime}`,
        type: 'booking',
        bookingId: booking.id,
        booking,
        courtId,
        courtName: range.courtName || this.courtName(courtId),
        start,
        end,
        title: customer || booking.booking_code || 'Khách đặt sân',
        subtitle: `${booking.booking_code || 'Booking'} · ${this.statusLabel(booking.status)}`,
        timeLabel: `${this.formatTime(range.startTime)} - ${this.formatTime(range.endTime)}`,
        kindClass: this.timelineBookingClass(booking),
        statusPill,
      };
    },

    makeLockBlock(interval) {
      const start = this.timeToMinutes(interval.start_time);
      const end = this.timeToMinutes(interval.end_time);
      if (!interval.venue_court_id || end <= start) return null;

      return {
        key: `lock-${interval.schedule_lock_id || `${interval.venue_court_id}-${interval.start_time}`}`,
        type: 'lock',
        courtId: interval.venue_court_id,
        courtName: this.courtName(interval.venue_court_id),
        start,
        end,
        title: 'Khóa sân',
        subtitle: interval.reason || 'Bảo trì / Đóng sân',
        timeLabel: `${this.formatTime(interval.start_time)} - ${this.formatTime(interval.end_time)}`,
        kindClass: 'is-locked-block',
        statusPill: { type: 'locked', label: 'Khóa sân' },
      };
    },

    timelineBookingClass(booking) {
      if (booking.status === 'checked_in') return 'is-playing-block';
      if (['pending_approval', 'pending_payment'].includes(booking.status)) return 'is-pending-block';
      if (['cancelled', 'rejected', 'expired'].includes(booking.status)) return 'is-cancelled-block';
      return 'is-confirmed-block';
    },

    onCellClick(court, slot) {
      const existing = this.getBlockForCourtAndSlot(court.id, slot);
      if (existing) {
        this.selectTimelineItem(existing);
      } else {
        this.openCreateDrawer(court, slot);
      }
    },

    selectTimelineItem(block) {
      this.selectedTimelineItem = block;
      this.drawerMode = 'view';
      this.createSlot = null;
    },

    openCreateDrawer(court, slot) {
      this.createSlot = { court, slot };
      this.counterForm = {
        walk_in_name: '',
        walk_in_phone: '',
        collection_mode: 'cash',
      };
      this.counterQr = null;
      this.selectedTimelineItem = null;
      this.drawerMode = 'create';
    },

    closeDrawer() {
      this.drawerMode = null;
      this.selectedTimelineItem = null;
      this.createSlot = null;
      this.counterQr = null;
      this.clearCounterPolling();
    },

    async submitCounterBooking() {
      if (!this.counterFormValid || this.counterSubmitting || !this.createSlot) return;
      this.counterSubmitting = true;
      this.error = '';
      this.notice = '';

      const slot = this.createSlot.slot;
      const court = this.createSlot.court;

      const payload = {
        venue_court_id: court.id,
        booking_date: this.filters.booking_date,
        start_time: `${this.minutesToTime(slot.start)}:00`,
        end_time: `${this.minutesToTime(slot.end)}:00`,
        payment_option: this.counterForm.collection_mode === 'later' ? 'no_prepay' : 'full_payment',
        is_paid: this.counterForm.collection_mode === 'cash',
        payment_method: this.counterForm.collection_mode === 'transfer' ? 'sepay' : (this.counterForm.collection_mode === 'cash' ? 'cash' : null),
        walk_in_name: this.counterForm.walk_in_name.trim(),
        walk_in_phone: this.counterForm.walk_in_phone.trim(),
      };

      try {
        const response = await ownerBookingService.storeCounter(payload);
        if (this.counterForm.collection_mode === 'transfer' && response.payment_qr) {
          this.counterQr = response.payment_qr;
          this.startCounterPolling(response.data?.id);
        } else {
          this.notice = 'Đã tạo đơn đặt sân tại quầy thành công!';
          await this.loadBookings();
          this.closeDrawer();
        }
      } catch (err) {
        this.error = err.message || 'Không thể tạo đặt sân tại quầy.';
      } finally {
        this.counterSubmitting = false;
      }
    },

    startCounterPolling(bookingId) {
      this.clearCounterPolling();
      if (!bookingId) return;
      this.counterPollInterval = setInterval(async () => {
        try {
          const res = await ownerBookingService.show(bookingId);
          const booking = res.data || res;
          if (this.outstandingAmount(booking) <= 0 || booking.status === 'confirmed') {
            this.notice = 'Chuyển khoản SePay thành công!';
            await this.loadBookings();
            this.closeDrawer();
          }
        } catch {
          this.clearCounterPolling();
        }
      }, 4000);
    },

    clearCounterPolling() {
      if (this.counterPollInterval) {
        clearInterval(this.counterPollInterval);
        this.counterPollInterval = null;
      }
    },

    async runBookingAction(booking, action) {
      if (action === 'collect') {
        this.openCollectPayment(booking);
        return;
      }
      if (action === 'change_court') {
        this.openChangeCourt(booking);
        return;
      }
      if (['reject', 'cancel'].includes(action)) {
        this.openStatusAction(booking, action);
        return;
      }

      this.updatingStatus = true;
      this.error = '';
      this.notice = '';
      try {
        await ownerBookingService.updateStatus(booking.id, { action });
        this.notice = 'Đã cập nhật trạng thái đơn đặt sân!';
        await this.loadBookings();
        this.closeDrawer();
      } catch (err) {
        this.error = err.message || 'Không thể cập nhật trạng thái.';
      } finally {
        this.updatingStatus = false;
      }
    },

    canCollectPayment(booking) {
      return (
        !['cancelled', 'expired', 'rejected'].includes(booking.status) &&
        this.outstandingAmount(booking) > 0
      );
    },

    canChangeCourt(booking) {
      return (
        ['pending_approval', 'pending_payment', 'confirmed'].includes(booking.status) &&
        this.bookingRanges(booking).length <= 1
      );
    },

    async openChangeCourt(booking) {
      this.changeCourtBooking = booking;
      this.changeCourtForm = {
        venue_court_id: booking.venue_court_id,
        court_changed_reason: '',
      };
      try {
        const response = await venueClusterService.getCourts(booking.venue_cluster_id, { status: 'active' });
        this.changeCourtOptions = response.data || [];
      } catch {
        this.changeCourtOptions = [];
      }
    },

    closeChangeCourt() {
      this.changeCourtBooking = null;
      this.changeCourtOptions = [];
    },

    async saveChangeCourt() {
      if (!this.changeCourtBooking) return;
      this.savingChangeCourt = true;
      this.error = '';
      try {
        await ownerBookingService.changeCourt(this.changeCourtBooking.id, this.changeCourtForm);
        this.notice = 'Đã đổi sân thực tế thành công!';
        await this.loadBookings();
        this.closeChangeCourt();
        this.closeDrawer();
      } catch (err) {
        this.error = err.message || 'Không thể đổi sân.';
      } finally {
        this.savingChangeCourt = false;
      }
    },

    openCollectPayment(booking) {
      const pendingTransfer = this.pendingTransfer(booking);
      this.collectBooking = booking;
      this.collectForm = {
        payment_method: pendingTransfer ? 'sepay' : 'cash',
        amount: pendingTransfer ? Number(pendingTransfer.amount) : this.outstandingAmount(booking),
      };
      this.collectQr = null;
      this.clearCollectPolling();
    },

    closeCollectPayment() {
      this.collectBooking = null;
      this.collectQr = null;
      this.clearCollectPolling();
    },

    async submitCollectPayment() {
      if (!this.collectBooking || this.collectingPayment) return;
      this.collectingPayment = true;
      this.error = '';
      try {
        const response = await ownerBookingService.collectPayment(this.collectBooking.id, {
          payment_method: this.collectForm.payment_method,
          amount: this.collectForm.amount,
        });

        if (this.collectForm.payment_method === 'sepay') {
          this.collectQr = response.payment_qr || null;
          this.startCollectPolling();
        } else {
          this.notice = 'Đã ghi nhận thu tiền tại quầy thành công!';
          await this.loadBookings();
          this.closeCollectPayment();
          this.closeDrawer();
        }
      } catch (err) {
        this.error = err.message || 'Không thể ghi nhận thu tiền.';
      } finally {
        this.collectingPayment = false;
      }
    },

    startCollectPolling() {
      this.clearCollectPolling();
      this.collectPollInterval = setInterval(async () => {
        if (!this.collectBooking) return;
        try {
          const res = await ownerBookingService.show(this.collectBooking.id);
          const booking = res.data || res;
          this.collectBooking = booking;
          if (this.outstandingAmount(booking) <= 0) {
            this.notice = 'Chuyển khoản SePay đã được ghi nhận!';
            await this.loadBookings();
            this.closeCollectPayment();
            this.closeDrawer();
          }
        } catch {
          this.clearCollectPolling();
        }
      }, 4000);
    },

    clearCollectPolling() {
      if (this.collectPollInterval) {
        clearInterval(this.collectPollInterval);
        this.collectPollInterval = null;
      }
    },

    collectSubmitLabel() {
      if (this.collectForm.payment_method !== 'sepay') return 'Xác nhận thu tiền mặt';
      return this.pendingTransfer(this.collectBooking) ? 'Xem lại mã VietQR' : 'Tạo mã VietQR SePay';
    },

    openStatusAction(booking, action) {
      this.statusActionBooking = booking;
      this.statusAction = action;
      this.statusActionReason = '';
    },

    closeStatusAction() {
      this.statusActionBooking = null;
      this.statusAction = '';
      this.statusActionReason = '';
    },

    async submitStatusAction() {
      if (!this.statusActionBooking || !this.statusActionReason) return;
      this.updatingStatus = true;
      this.error = '';
      try {
        await ownerBookingService.updateStatus(this.statusActionBooking.id, {
          action: this.statusAction,
          status_reason: this.statusActionReason,
        });
        this.notice = this.statusAction === 'reject' ? 'Đã từ chối booking!' : 'Đã hủy booking!';
        await this.loadBookings();
        this.closeStatusAction();
        this.closeDrawer();
      } catch (err) {
        this.error = err.message || 'Không thể thao tác đơn.';
      } finally {
        this.updatingStatus = false;
      }
    },

    statusActionTitle() {
      return this.statusAction === 'reject' ? 'Từ chối đơn đặt sân' : 'Hủy booking đặt sân';
    },

    bookingRanges(booking) {
      if (booking.items?.length) {
        return booking.items.map((item) => ({
          key: item.id,
          venueCourtId: item.venue_court_id,
          courtName: item.venue_court?.name || '-',
          startTime: item.start_time,
          endTime: item.end_time,
        }));
      }
      return [
        {
          key: booking.id,
          venueCourtId: booking.venue_court_id,
          courtName: booking.venue_court?.name || '-',
          startTime: booking.start_time,
          endTime: booking.end_time,
        },
      ];
    },

    courtName(courtId) {
      return this.scheduleCourts.find((c) => String(c.id) === String(courtId))?.name || '-';
    },

    customerName(booking) {
      return booking?.customer?.full_name || booking?.customer?.username || booking?.walk_in_name || 'Khách vãng lai';
    },

    customerPhone(booking) {
      return booking?.customer?.phone || booking?.walk_in_phone || '-';
    },

    paidAmount(booking) {
      return (booking?.payments || [])
        .filter((p) => p.status === 'paid')
        .reduce((sum, p) => sum + Number(p.amount || 0), 0);
    },

    outstandingAmount(booking) {
      return Math.max(Number(booking?.total_price || 0) - this.paidAmount(booking), 0);
    },

    paymentState(booking) {
      if (this.outstandingAmount(booking) <= 0) return 'paid';
      if (this.bookingHasPendingTransfer(booking)) return 'pending';
      if (this.paidAmount(booking) > 0) return 'partial';
      return 'unpaid';
    },

    bookingHasPendingTransfer(booking) {
      return !!this.pendingTransfer(booking);
    },

    pendingTransfer(booking) {
      return (booking?.payments || []).find((p) => p.method === 'sepay' && p.status === 'pending') || null;
    },

    statusLabel(status) {
      return {
        pending_approval: 'Chờ duyệt',
        pending_payment: 'Chờ thanh toán',
        confirmed: 'Đã xác nhận',
        checked_in: 'Đang chơi',
        completed: 'Hoàn thành',
        no_show: 'Vắng mặt',
        cancelled: 'Đã hủy',
        rejected: 'Đã từ chối',
        expired: 'Hết hạn',
      }[status] || status;
    },

    formatDate(value) {
      if (!value) return '-';
      return new Intl.DateTimeFormat('vi-VN').format(new Date(`${value}T00:00:00`));
    },

    formatTime(time) {
      return (time || '').slice(0, 5);
    },

    timeToMinutes(value) {
      const [h, m] = this.formatTime(value).split(':').map(Number);
      return (h || 0) * 60 + (m || 0);
    },

    minutesToTime(mins) {
      const h = Math.floor(mins / 60);
      const m = mins % 60;
      return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    },

    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(Number(amount || 0));
    },

    async copyText(text) {
      if (!text) return;
      try {
        await navigator.clipboard.writeText(text);
        this.notice = 'Đã copy nội dung chuyển khoản!';
      } catch {
        this.error = 'Không thể sao chép.';
      }
    },
  },
};
</script>

<style scoped>
.pos-workspace {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  padding: 20px 24px;
  box-sizing: border-box;
  background: #ffffff;
  min-height: calc(100vh - 56px);
}

/* 1. TOP OPERATIONAL BAR */
.pos-top-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.pos-top-left {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.pos-date-group {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pos-date-nav {
  display: flex;
  align-items: center;
  gap: 2px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  padding: 2px;
  border-radius: 6px;
  height: 32px;
  box-sizing: border-box;
}

.pos-nav-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border: none;
  background: transparent;
  color: #334155;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.12s ease;
}

.pos-nav-btn:hover {
  background: #f0fdf4;
  color: #087642;
}

.pos-chip-btn {
  border: 1px solid transparent;
  background: transparent;
  padding: 0 10px;
  height: 26px;
  display: inline-flex;
  align-items: center;
  font-size: 12.5px;
  font-weight: 500;
  color: #334155;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.12s ease;
}

.pos-chip-btn:hover {
  color: #087642;
  background: #f0fdf4;
}

.pos-chip-btn.active {
  background: #087642;
  color: #ffffff;
  border-color: #087642;
  font-weight: 500;
}

/* CALENDAR POPOVER TRIGGER */
.pos-cal-dropdown {
  position: relative;
}

.pos-cal-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  padding: 0 10px;
  height: 32px;
  border-radius: 6px;
  font-size: 12.5px;
  font-weight: 500;
  color: #0f172a;
  cursor: pointer;
  box-sizing: border-box;
  transition: all 0.12s ease;
}

.pos-cal-btn:hover,
.pos-cal-btn.active {
  border-color: #087642;
  color: #087642;
  background: #f0fdf4;
}

.pos-cal-popover {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  z-index: 100;
  background: #ffffff;
  border-radius: 8px;
  box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
  padding: 10px;
  border: 1px solid #e5e7eb;
}

/* PERIOD TABS */
.pos-period-tabs {
  display: flex;
  align-items: center;
  gap: 2px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  padding: 2px;
  border-radius: 6px;
  height: 32px;
  box-sizing: border-box;
}

.pos-period-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0 10px;
  height: 26px;
  border: 1px solid transparent;
  background: transparent;
  border-radius: 4px;
  color: #334155;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.12s ease;
}

.pos-period-btn small {
  font-size: 10.5px;
  color: inherit;
  opacity: 0.8;
}

.pos-period-btn:hover {
  color: #087642;
  background: #f0fdf4;
}

.pos-period-btn.active {
  background: #087642;
  color: #ffffff;
  border-color: #087642;
  font-weight: 500;
}

/* TOP RIGHT SEARCH & REFRESH */
.pos-top-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pos-search-box {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  padding: 0 10px;
  height: 32px;
  border-radius: 6px;
  width: 195px;
  box-sizing: border-box;
  transition: all 0.12s ease;
}

.pos-search-box:focus-within {
  border-color: #087642;
  box-shadow: 0 0 0 2px rgba(8, 118, 66, 0.15);
}

.pos-search-icon {
  color: #64748b;
  flex-shrink: 0;
}

.pos-search-box input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 12.5px;
  height: 100%;
  width: 100%;
  color: #0f172a;
  font-weight: 400;
}

.pos-search-box input::placeholder {
  color: #94a3b8;
  font-size: 12px;
}

.pos-search-clear {
  border: none;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  font-size: 15px;
  padding: 0;
  line-height: 1;
}

.pos-btn-refresh {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0 10px;
  height: 32px;
  background: #ffffff;
  color: #0f172a;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  box-sizing: border-box;
  transition: all 0.12s ease;
  white-space: nowrap;
}

.pos-btn-refresh:hover {
  background: #087642;
  border-color: #087642;
  color: #ffffff;
}

/* 2. OPERATIONAL KPI STRIP */
.pos-kpi-strip {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
}

.pos-kpi-cell {
  background: #ffffff;
  border-radius: 8px;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  border: 1px solid #e5e7eb;
}

.pos-kpi-val {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: #0f172a;
  line-height: 1.2;
}

.pos-kpi-cell.is-playing .pos-kpi-val { color: #087642; }
.pos-kpi-cell.is-confirmed .pos-kpi-val { color: #d97706; }
.pos-kpi-cell.is-pending .pos-kpi-val { color: #0284c7; }
.pos-kpi-cell.is-unpaid .pos-kpi-val { color: #dc2626; }
.pos-kpi-cell.is-revenue .pos-kpi-val { color: #087642; }

.pos-kpi-label {
  font-size: 11.5px;
  color: #64748b;
  font-weight: 500;
}

/* FLASH ALERTS */
.pos-alert {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
}

.pos-alert.is-error {
  background: #fee2e2;
  color: #dc2626;
  border: 1px solid #fca5a5;
}

.pos-alert.is-success {
  background: #f0fdf4;
  color: #087642;
  border: 1px solid #bbf7d0;
}

.pos-alert button {
  border: none;
  background: transparent;
  font-size: 16px;
  cursor: pointer;
  color: inherit;
}

/* 3. OPERATIONAL MATRIX TERMINAL */
.pos-matrix-container {
  flex: 1;
  background: #ffffff;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}

.pos-state-box {
  padding: 60px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  color: #64748b;
  text-align: center;
  font-size: 13px;
}

.pos-3d-terminal-img {
  width: 130px;
  height: 130px;
  object-fit: contain;
  filter: drop-shadow(0 12px 24px rgba(8, 118, 66, 0.15));
  animation: float-slow 3s ease-in-out infinite alternate;
}

@keyframes float-slow {
  from { transform: translateY(0); }
  to { transform: translateY(-4px); }
}

.pos-3d-empty-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 4px;
}

.pos-3d-empty-info h4 {
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
}

.pos-3d-empty-info p {
  font-size: 12.5px;
  color: #64748b;
  margin: 0;
  max-width: 400px;
}

.pos-btn-retry {
  margin-top: 6px;
  background: #087642;
  color: #ffffff;
  border: none;
  padding: 7px 16px;
  border-radius: 6px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
}

.pos-btn-retry:hover {
  background: #065f35;
}

.pos-spinner {
  width: 28px;
  height: 28px;
  border: 2px solid #e5e7eb;
  border-top-color: #087642;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.pos-grid-scroller {
  overflow-x: auto;
  overflow-y: visible;
}

.pos-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 2px;
  table-layout: fixed;
}

.pos-th-time {
  width: 105px;
  background: #ffffff;
  border-bottom: 2px solid #087642;
  padding: 10px 8px;
  font-size: 11.5px;
  font-weight: 700;
  color: #0f172a;
  text-align: center;
  position: sticky;
  left: 0;
  z-index: 20;
}

.pos-th-court {
  background: #ffffff;
  border-bottom: 2px solid #087642;
  padding: 10px 12px;
  text-align: left;
  min-width: 180px;
}

.pos-court-header {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}

.pos-court-name {
  font-size: 13.5px;
  font-weight: 600;
  color: #0f172a;
}

.pos-court-type {
  font-size: 11px;
  color: #64748b;
  font-weight: 500;
}

/* ROW & CELL */
.pos-td-time {
  background: #ffffff;
  border-right: 1px solid #e5e7eb;
  padding: 6px 4px;
  text-align: center;
  position: sticky;
  left: 0;
  z-index: 10;
}

.pos-time-mark {
  font-family: ui-monospace, SFMono-Regular, monospace;
  font-size: 11px;
  font-weight: 600;
  color: #0f172a;
}

.pos-td-cell {
  height: 58px;
  padding: 1px;
  vertical-align: stretch;
  cursor: pointer;
}

.pos-td-cell.is-highlight {
  outline: 2px solid #087642;
}

/* EMPTY SLOT */
.pos-empty-slot {
  width: 100%;
  height: 100%;
  border-radius: 4px;
  background: #ffffff;
  border: 1px dashed #cbd5e1;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.12s ease;
  min-height: 52px;
}

.pos-empty-action {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  opacity: 0;
  transition: opacity 0.12s ease;
}

.pos-td-cell:hover .pos-empty-slot {
  background: #f0fdf4;
  border-color: #087642;
}

.pos-td-cell:hover .pos-empty-action {
  opacity: 1;
  color: #087642;
}

/* BOOKED / LOCKED SLOT CARD */
.pos-slot-card {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 52px;
  border-radius: 4px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  display: flex;
  overflow: hidden;
  transition: border-color 0.12s ease;
}

.pos-slot-card:hover {
  border-color: #087642;
}

.pos-slot-card.is-selected {
  border-color: #087642;
  box-shadow: 0 0 0 1px #087642;
}

.pos-slot-card-accent {
  width: 3px;
  flex-shrink: 0;
  background: #64748b;
}

.pos-slot-card.is-playing-block .pos-slot-card-accent { background: #087642; }
.pos-slot-card.is-confirmed-block .pos-slot-card-accent { background: #d97706; }
.pos-slot-card.is-pending-block .pos-slot-card-accent { background: #0284c7; }
.pos-slot-card.is-locked-block .pos-slot-card-accent { background: #0f172a; }

.pos-slot-card-content {
  flex: 1;
  padding: 5px 8px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  overflow: hidden;
}

.pos-slot-top {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 4px;
}

.pos-slot-title {
  font-size: 12px;
  font-weight: 600;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pos-slot-code {
  font-size: 10px;
  font-family: monospace;
  font-weight: 600;
  color: #64748b;
}

.pos-slot-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
}

.pos-slot-time {
  font-size: 10px;
  color: #64748b;
  font-weight: 500;
}

.pos-slot-status-text {
  font-size: 9.5px;
  font-weight: 600;
}

.pos-slot-status-text.playing { color: #087642; }
.pos-slot-status-text.unpaid { color: #dc2626; }
.pos-slot-status-text.confirmed { color: #087642; }

/* 4. MODAL & OFF-CANVAS DRAWER */
.pos-drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(2px);
  z-index: 9999;
  display: flex;
  justify-content: flex-end;
}

.pos-drawer-sheet {
  width: 100%;
  max-width: 420px;
  background: #ffffff;
  height: 100%;
  box-shadow: -8px 0 25px rgba(0, 0, 0, 0.12);
  display: flex;
  flex-direction: column;
  animation: slide-left 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  border-left: 1px solid #e5e7eb;
}

@keyframes slide-left {
  from { transform: translateX(100%); }
  to { transform: translateX(0); }
}

.pos-drawer-head {
  padding: 20px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  border-bottom: 1px solid #e5e7eb;
}

.pos-drawer-kicker {
  font-size: 10.5px;
  font-weight: 700;
  color: #087642;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.pos-drawer-title {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 3px 0 2px;
}

.pos-drawer-subtitle {
  font-size: 12.5px;
  color: #64748b;
  margin: 0;
}

.pos-drawer-close-btn {
  background: transparent;
  border: none;
  font-size: 18px;
  color: #64748b;
  cursor: pointer;
  padding: 2px;
}

.pos-drawer-close-btn:hover {
  color: #0f172a;
}

.pos-drawer-body {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* INFO LIST */
.pos-info-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pos-info-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  font-size: 13px;
}

.pos-info-label {
  color: #64748b;
}

.pos-info-val {
  font-weight: 600;
  color: #0f172a;
}

.pos-info-val.is-money {
  font-size: 14px;
  font-weight: 700;
}

.pos-info-val.is-paid {
  color: #087642;
}

.pos-info-val.is-outstanding {
  color: #dc2626;
  font-size: 14px;
  font-weight: 700;
}

.pos-info-row.is-due {
  padding-top: 6px;
}

/* ACTION BUTTONS STACK */
.pos-actions-stack {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: auto;
}

.pos-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 11px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: all 0.12s ease;
}

.pos-action-btn.is-checkin {
  background: #087642;
  color: #ffffff;
}

.pos-action-btn.is-checkin:hover {
  background: #065f35;
}

.pos-action-btn.is-collect {
  background: #d97706;
  color: #ffffff;
}

.pos-action-btn.is-collect:hover {
  background: #b45309;
}

.pos-action-btn.is-complete {
  background: #0284c7;
  color: #ffffff;
}

.pos-action-btn.is-change {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  color: #0f172a;
}

.pos-action-btn.is-change:hover {
  background: #f0fdf4;
  border-color: #087642;
  color: #087642;
}

.pos-action-btn.is-cancel {
  background: #fee2e2;
  color: #dc2626;
  border: 1px solid #fca5a5;
}

.pos-action-btn.is-cancel:hover {
  background: #fecaca;
}

/* FORM FIELDS */
.pos-field-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pos-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.pos-field-label {
  font-size: 12.5px;
  font-weight: 600;
  color: #0f172a;
}

.pos-field-label .req {
  color: #dc2626;
}

.pos-input {
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 9px 12px;
  font-size: 13.5px;
  color: #0f172a;
  outline: none;
  transition: all 0.12s ease;
}

.pos-input:focus {
  border-color: #087642;
  box-shadow: 0 0 0 2px rgba(8, 118, 66, 0.15);
}

/* PAYMENT MODES */
.pos-mode-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.pos-mode-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 3px;
  padding: 10px 6px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.12s ease;
}

.pos-mode-card:hover {
  background: #f0fdf4;
  border-color: #087642;
}

.pos-mode-card.active {
  background: #f0fdf4;
  border-color: #087642;
  color: #087642;
}

.pos-mode-card strong {
  font-size: 12px;
  font-weight: 600;
}

.pos-mode-card small {
  font-size: 10px;
  color: inherit;
  opacity: 0.8;
}

/* QR CONTAINER */
.pos-qr-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  padding: 14px;
  border-radius: 8px;
}

.pos-qr-image {
  width: 150px;
  height: 150px;
  border-radius: 6px;
  background: #ffffff;
}

.pos-qr-copy-btn {
  background: #ffffff;
  border: 1px solid #087642;
  color: #087642;
  padding: 5px 10px;
  border-radius: 4px;
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
}

.pos-qr-status {
  font-size: 11px;
  color: #087642;
  font-weight: 600;
}

/* DRAWER FOOTER */
.pos-drawer-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  margin-top: auto;
  padding-top: 14px;
}

.pos-btn-plain {
  background: transparent;
  border: none;
  color: #64748b;
  font-size: 13px;
  font-weight: 600;
  padding: 10px 16px;
  cursor: pointer;
}

.pos-btn-primary {
  background: #087642;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  padding: 10px 20px;
  cursor: pointer;
  transition: background 0.12s ease;
}

.pos-btn-primary:hover:not(:disabled) {
  background: #065f35;
}

.pos-btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* MODAL BOX */
.pos-modal-box {
  width: 100%;
  max-width: 480px;
  background: #ffffff;
  margin: auto;
  border-radius: 12px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #e5e7eb;
}

.pos-modal-head {
  padding: 16px 20px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.pos-modal-head h3 {
  font-size: 16px;
  font-weight: 700;
  margin: 0 0 2px;
  color: #0f172a;
}

.pos-modal-head p {
  font-size: 12px;
  color: #64748b;
  margin: 0;
}

.pos-modal-body {
  padding: 0 20px 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.pos-collect-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  padding: 10px;
  border-radius: 6px;
}

.pos-cstat {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pos-cstat span {
  font-size: 10.5px;
  color: #64748b;
}

.pos-cstat strong {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
}

.pos-cstat.is-due strong {
  color: #dc2626;
}

.pos-modal-foot {
  padding: 14px 20px;
  background: #ffffff;
  border-top: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}

.pos-btn-danger {
  background: #dc2626;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  padding: 9px 18px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.pos-danger-note {
  font-size: 12.5px;
  color: #dc2626;
  background: #fee2e2;
  border: 1px solid #fca5a5;
  padding: 10px;
  border-radius: 6px;
  margin: 0;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
  .pos-kpi-strip {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 640px) {
  .pos-workspace {
    padding: 12px;
  }
  .pos-kpi-strip {
    grid-template-columns: repeat(2, 1fr);
  }
  .pos-search-box {
    width: 100%;
  }
}
</style>

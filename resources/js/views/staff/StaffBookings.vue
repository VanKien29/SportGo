<template>
    <div class="pos-workspace">
        <!-- 1. OPERATIONAL TOOLBAR: DATE + TIME PERIOD + ACTIONS -->
        <header class="pos-top-toolbar">
            <div class="pos-toolbar-left">
                <!-- Date Navigator -->
                <div class="pos-date-nav-wrap">
                    <button
                        type="button"
                        class="btn-date-nav-arrow"
                        title="Ngày trước"
                        aria-label="Ngày trước"
                        @click="shiftDate(-1)"
                    >
                        <AppIcon name="chevronLeft" :size="15" />
                    </button>
                    <button
                        type="button"
                        class="btn-date-nav-today"
                        :class="{ active: isToday(filters.booking_date) }"
                        @click="setToday"
                    >
                        HÔM NAY
                    </button>
                    <button
                        type="button"
                        class="btn-date-nav-arrow"
                        title="Ngày sau"
                        aria-label="Ngày sau"
                        @click="shiftDate(1)"
                    >
                        <AppIcon name="chevronRight" :size="15" />
                    </button>
                </div>

                <!-- Custom Date Dropdown Trigger -->
                <div class="pos-cal-dropdown">
                    <button
                        type="button"
                        class="pos-cal-btn"
                        :class="{ active: showCalDropdown }"
                        title="Mở lịch chọn ngày"
                        @click="toggleCalDropdown"
                    >
                        <AppIcon name="calendar" :size="15" />
                        <span>{{ formattedCurrentDate }}</span>
                    </button>
                    <div v-if="showCalDropdown" class="pos-cal-popover">
                        <MiniCalendar
                            mode="single"
                            :model-value="filters.booking_date"
                            @update:model-value="onDateSelect"
                        />
                    </div>
                </div>

                <!-- Custom Shift / Time Period Dropdown Trigger -->
                <div class="pos-period-wrapper">
                    <button
                        type="button"
                        class="pos-period-btn"
                        :class="{ active: showPeriodDropdown }"
                        title="Chọn khung giờ / ca làm việc"
                        @click="togglePeriodDropdown"
                    >
                        <AppIcon name="clock" :size="14" />
                        <span class="pos-period-btn-label">
                            {{ activePeriod.label }} ({{ activePeriod.range }})
                        </span>
                        <AppIcon
                            name="chevronDown"
                            :size="12"
                            class="pos-period-arrow"
                            :class="{ 'is-open': showPeriodDropdown }"
                        />
                    </button>

                    <!-- Custom Popover Menu -->
                    <div v-if="showPeriodDropdown" class="pos-period-popover">
                        <button
                            v-for="period in timePeriods"
                            :key="period.key"
                            type="button"
                            class="pos-period-option"
                            :class="{ active: activeTimePeriod === period.key }"
                            @click="selectTimePeriod(period.key)"
                        >
                            <div class="period-opt-info">
                                <strong>{{ period.label }}</strong>
                                <small>{{ period.range }}</small>
                            </div>
                            <AppIcon
                                v-if="activeTimePeriod === period.key"
                                name="check"
                                :size="14"
                                class="period-opt-check"
                            />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Toolbar: Search, Mode Switch, Quick Actions, Refresh -->
            <div class="pos-toolbar-right">
                <!-- Search Box -->
                <div class="pos-search-box">
                    <AppIcon name="search" :size="14" class="pos-search-icon" />
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

                <!-- Action Row (View Mode, Fast Actions, Refresh) -->
                <div class="pos-toolbar-actions-row">
                    <!-- View Mode Switcher: Matrix vs Danh sách -->
                    <div class="pos-view-switcher">
                        <button
                            type="button"
                            class="pos-view-btn"
                            :class="{ active: viewMode === 'matrix' }"
                            title="Xem bảng lịch ma trận thời gian trong ngày"
                            @click="viewMode = 'matrix'"
                        >
                            <AppIcon name="grid" :size="13" />
                            <span>Ma trận</span>
                        </button>
                        <button
                            type="button"
                            class="pos-view-btn"
                            :class="{ active: viewMode === 'list' }"
                            title="Xem danh sách đơn đặt sân"
                            @click="viewMode = 'list'"
                        >
                            <AppIcon name="list" :size="13" />
                            <span>Danh sách</span>
                        </button>
                    </div>

                    <!-- Fast Action: Scan QR Ticket -->
                    <button
                        type="button"
                        class="pos-btn-action is-qr"
                        title="Quét mã QR vé đặt sân"
                        @click="showQrScannerModal = true"
                    >
                        <AppIcon name="qrCode" :size="14" />
                        <span>Quét vé</span>
                    </button>

                    <!-- Utility: Refresh -->
                    <button
                        type="button"
                        class="pos-btn-icon-refresh"
                        title="Tải lại dữ liệu (F3)"
                        @click="loadBookings"
                    >
                        <AppIcon
                            name="rotateCw"
                            :size="14"
                            :class="{
                                'animate-spin': loading || scheduleLoading,
                            }"
                        />
                    </button>
                </div>
            </div>
        </header>

        <!-- 2. STREAMLINED CAPACITY & SHIFT BAR (STYLE 4 - NO BOXES, NO DOTS) -->
        <section class="pos-capacity-bar">
            <!-- Left: Visual Capacity Meter -->
            <div
                class="pos-capacity-meter-wrap"
                title="Tỷ lệ công suất sân tức thời"
            >
                <div class="pos-cap-header">
                    <span class="pos-cap-label">Công suất sân</span>
                    <strong class="pos-cap-val"
                        >{{ liveOccupancyPercent }}%</strong
                    >
                    <span class="pos-cap-sub"
                        >({{ kpiStats.playingCount }}/{{
                            visibleCourts.length
                        }}
                        sân)</span
                    >
                </div>

                <!-- Visual Track Bar -->
                <div class="pos-cap-track">
                    <div
                        class="pos-cap-fill"
                        :style="{ width: `${liveOccupancyPercent}%` }"
                    ></div>
                </div>
            </div>

            <div class="pos-bar-sep"></div>

            <!-- Center: Streamlined Operational & Financial Metrics -->
            <div class="pos-metrics-cluster">
                <div
                    class="pos-metric-cell"
                    v-if="kpiStats.confirmedCount > 0"
                    title="Số đơn đã xác nhận chờ check-in"
                >
                    <span class="pos-metric-title">Chờ vào sân</span>
                    <strong class="pos-metric-num is-blue">{{
                        kpiStats.confirmedCount
                    }}</strong>
                </div>

                <div
                    class="pos-metric-cell"
                    v-if="kpiStats.pendingCount > 0"
                    title="Số đơn cần xác nhận / chờ cọc"
                >
                    <span class="pos-metric-title">Chờ cọc</span>
                    <strong class="pos-metric-num is-amber">{{
                        kpiStats.pendingCount
                    }}</strong>
                </div>

                <div
                    class="pos-metric-cell"
                    v-if="kpiStats.unpaidAmount > 0"
                    title="Tổng tiền còn nợ / thu sau"
                >
                    <span class="pos-metric-title">Chưa thu</span>
                    <strong class="pos-metric-num is-red">{{
                        formatCurrency(kpiStats.unpaidAmount)
                    }}</strong>
                </div>

                <div
                    class="pos-metric-cell"
                    title="Tổng tiền mặt và chuyển khoản đã thực thu hôm nay"
                >
                    <span class="pos-metric-title">Đã thu ca</span>
                    <strong class="pos-metric-num is-green">{{
                        formatCurrency(kpiStats.collectedAmount)
                    }}</strong>
                </div>
            </div>

            <!-- Right Action: Analytics Modal Trigger -->
            <button
                type="button"
                class="pos-btn-analytics"
                title="Xem phân tích công suất, biểu đồ nhiệt và cơ cấu tiền két"
                @click="showAnalyticsModal = true"
            >
                <AppIcon name="barChart" :size="14" />
                <span>Báo cáo ca</span>
            </button>
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
                <div class="pos-state-icon-badge is-error">
                    <AppIcon name="alertCircle" :size="36" />
                </div>
                <div class="pos-3d-empty-info">
                    <h4>Không thể kết nối dữ liệu sân</h4>
                    <p>{{ scheduleError }}</p>
                    <button
                        type="button"
                        class="pos-btn-retry"
                        @click="loadBookings"
                    >
                        Thử lại
                    </button>
                </div>
            </div>
            <div v-else-if="!visibleCourts.length" class="pos-state-box">
                <div class="pos-state-icon-badge">
                    <AppIcon name="grid" :size="36" />
                </div>
                <div class="pos-3d-empty-info">
                    <h4>Chưa có dữ liệu sân thi đấu</h4>
                    <p>
                        Cụm sân này hiện chưa có danh sách sân hoặc bạn chưa
                        được phân quyền truy cập sân.
                    </p>
                </div>
            </div>

      <!-- A. LIST VIEW (DANH SÁCH ĐƠN ĐẶT SÂN) -->
      <div v-else-if="viewMode === 'list'" class="pos-list-container">
        <table class="pos-data-table">
          <thead>
            <tr>
              <th>Mã đơn</th>
              <th>Khách hàng</th>
              <th>SĐT</th>
              <th>Sân</th>
              <th>Khung giờ</th>
              <th>Trạng thái</th>
              <th>Tổng tiền</th>
              <th>Còn nợ</th>
              <th class="th-actions">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="b in filteredBookingsList"
              :key="b.id"
              class="tr-clickable"
              @click="openBookingDrawerFromList(b)"
            >
              <td><strong class="mono-code">#{{ b.booking_code }}</strong></td>
              <td><strong>{{ customerName(b) }}</strong></td>
              <td>{{ customerPhone(b) || '—' }}</td>
              <td>{{ b.venue_court?.name || b.court?.name || courtName(b.venue_court_id) || '—' }}</td>
              <td>{{ formatTime(b.start_time) }} - {{ formatTime(b.end_time) }}</td>
              <td>
                <span class="list-status-pill" :class="b.status">
                  {{ statusLabel(b.status) }}
                </span>
              </td>
              <td>{{ formatCurrency(b.total_price || b.final_amount || b.total_amount) }}</td>
              <td>
                <strong :class="{ 'text-danger': outstandingAmount(b) > 0 }">
                  {{ outstandingAmount(b) > 0 ? formatCurrency(outstandingAmount(b)) : '0đ' }}
                </strong>
              </td>
              <td class="td-actions" @click.stop>
                <button
                  v-if="b.status === 'pending_approval'"
                  type="button"
                  class="btn-table-action is-checkin"
                  @click="runBookingAction(b, 'confirm')"
                >
                  Duyệt
                </button>
                <button
                  v-if="b.status === 'pending_approval'"
                  type="button"
                  class="btn-table-action is-danger"
                  @click="openStatusAction(b, 'reject')"
                >
                  Từ chối
                </button>
                <button
                  v-if="b.status === 'confirmed'"
                  type="button"
                  class="btn-table-action is-checkin"
                  @click="quickCheckIn(b)"
                >
                  Check-in
                </button>
                <button
                  v-if="canCollectPayment(b)"
                  type="button"
                  class="btn-table-action is-collect"
                  @click="openCollectPayment(b)"
                >
                  Thu tiền
                </button>
                <button
                  type="button"
                  class="btn-table-action"
                  @click="openBookingDrawerFromList(b)"
                >
                  Chi tiết
                </button>
              </td>
            </tr>
            <tr v-if="!filteredBookingsList.length">
              <td colspan="9" class="td-empty-list">Không tìm thấy đơn đặt sân nào phù hợp.</td>
            </tr>
          </tbody>
        </table>
      </div>

            <!-- B. Live Terminal Grid (Matrix View) -->
            <div v-else class="pos-grid-scroller">
                <table class="pos-table">
                    <thead>
                        <tr>
                            <th class="pos-th-time">Khung giờ</th>
                            <th
                                v-for="court in visibleCourts"
                                :key="court.id"
                                class="pos-th-court"
                            >
                                <div class="pos-court-header">
                                    <strong class="pos-court-name">{{
                                        formatCourtShortName(court.name)
                                    }}</strong>
                                    <span class="pos-court-type">{{
                                        formatCourtTypeName(court)
                                    }}</span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="slot in verticalHourlySlots"
                            :key="slot.start"
                            :id="`slot-row-${Math.floor(slot.start / 60)}`"
                            class="pos-tr-row"
                            :class="{
                                'is-jump-highlight':
                                    highlightedHour ===
                                    Math.floor(slot.start / 60),
                            }"
                        >
                            <!-- Monospace Time Mark -->
                            <td
                                class="pos-td-time"
                                :class="{ 'is-past-time': isSlotPast(slot) }"
                            >
                                <span class="pos-time-mark">{{
                                    slot.label
                                }}</span>
                            </td>

                            <!-- Court Slot Interactive Cells with Smart Rowspan -->
                            <template
                                v-for="court in visibleCourts"
                                :key="`${court.id}-${slot.start}`"
                            >
                                <td
                                    v-if="
                                        getSlotCellInfo(court.id, slot)
                                            .shouldRender
                                    "
                                    :rowspan="
                                        getSlotCellInfo(court.id, slot).rowspan
                                    "
                                    class="pos-td-cell"
                                    :class="{
                                        'is-highlight': isSlotHighlighted(
                                            court.id,
                                            slot,
                                        ),
                                        'is-booked-cell':
                                            getSlotCellInfo(court.id, slot)
                                                .type === 'block',
                                        'is-past-slot':
                                            !getSlotCellInfo(court.id, slot)
                                                .block && isSlotPast(slot),
                                    }"
                                    @click="onCellClick(court, slot)"
                                >
                                    <!-- Booked or Locked Block -->
                                    <div
                                        v-if="
                                            getSlotCellInfo(court.id, slot)
                                                .block
                                        "
                                        class="pos-booking-card"
                                        :class="[
                                            getSlotCellInfo(court.id, slot)
                                                .block.kindClass,
                                            {
                                                'is-selected':
                                                    selectedTimelineItem?.key ===
                                                    getSlotCellInfo(
                                                        court.id,
                                                        slot,
                                                    ).block.key,
                                            },
                                        ]"
                                        @click.stop="
                                            selectTimelineItem(
                                                getSlotCellInfo(court.id, slot)
                                                    .block,
                                            )
                                        "
                                    >
                                        <div
                                            class="pos-booking-status-stripe"
                                        ></div>
                                        <div class="pos-booking-body">
                                            <div class="pos-booking-row-1">
                                                <strong
                                                    class="pos-booking-customer"
                                                >
                                                    {{
                                                        getSlotCellInfo(
                                                            court.id,
                                                            slot,
                                                        ).block.title
                                                    }}
                                                </strong>
                                                <span
                                                    v-if="
                                                        getSlotCellInfo(
                                                            court.id,
                                                            slot,
                                                        ).block.statusPill
                                                    "
                                                    class="pos-booking-status-badge"
                                                    :class="
                                                        getSlotCellInfo(
                                                            court.id,
                                                            slot,
                                                        ).block.statusPill.type
                                                    "
                                                >
                                                    {{
                                                        getSlotCellInfo(
                                                            court.id,
                                                            slot,
                                                        ).block.statusPill.label
                                                    }}
                                                </span>
                                            </div>

                                            <div class="pos-booking-row-2">
                                                <span class="pos-booking-time">
                                                    <AppIcon
                                                        name="clock"
                                                        :size="11"
                                                    />
                                                    <span>{{
                                                        getSlotCellInfo(
                                                            court.id,
                                                            slot,
                                                        ).block.timeLabel
                                                    }}</span>
                                                </span>
                                                <span
                                                    v-if="
                                                        customerPhone(
                                                            getSlotCellInfo(
                                                                court.id,
                                                                slot,
                                                            ).block.booking,
                                                        ) &&
                                                        customerPhone(
                                                            getSlotCellInfo(
                                                                court.id,
                                                                slot,
                                                            ).block.booking,
                                                        ) !== '-'
                                                    "
                                                    class="pos-booking-phone"
                                                >
                                                    <AppIcon
                                                        name="phone"
                                                        :size="11"
                                                    />
                                                    <span>{{
                                                        customerPhone(
                                                            getSlotCellInfo(
                                                                court.id,
                                                                slot,
                                                            ).block.booking,
                                                        )
                                                    }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Empty Slot: Clean, inviting hover state -->
                                    <div
                                        v-else
                                        class="pos-empty-slot"
                                        :class="{ 'is-past': isSlotPast(slot) }"
                                        :title="
                                            isSlotPast(slot)
                                                ? 'Khung giờ đã qua'
                                                : 'Bấm để đặt sân nhanh'
                                        "
                                    >
                                        <span
                                            v-if="!isSlotPast(slot)"
                                            class="pos-empty-hint"
                                            >+ Đặt sân</span
                                        >
                                        <span
                                            v-else
                                            class="pos-empty-past-hover-hint"
                                            >Đã qua</span
                                        >
                                    </div>
                                </td>
                            </template>
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
                            <span class="pos-drawer-kicker"
                                >THÔNG TIN ĐẶT SÂN</span
                            >
                            <h3 class="pos-drawer-title">
                                {{ selectedTimelineItem?.title }}
                            </h3>
                            <p class="pos-drawer-subtitle">
                                {{ selectedTimelineItem?.timeLabel }} ·
                                {{ selectedTimelineItem?.courtName }}
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
                                    <span class="pos-info-label"
                                        >Mã đặt sân</span
                                    >
                                    <strong class="pos-info-val"
                                        >#{{
                                            selectedTimelineBooking.booking_code
                                        }}</strong
                                    >
                                </div>
                                <div class="pos-info-row">
                                    <span class="pos-info-label"
                                        >Khách hàng</span
                                    >
                                    <strong class="pos-info-val">{{
                                        customerName(selectedTimelineBooking)
                                    }}</strong>
                                </div>
                                <div class="pos-info-row">
                                    <span class="pos-info-label"
                                        >Số điện thoại</span
                                    >
                                    <strong class="pos-info-val">{{
                                        customerPhone(selectedTimelineBooking)
                                    }}</strong>
                                </div>
                                <div class="pos-info-row">
                                    <span class="pos-info-label"
                                        >Trạng thái</span
                                    >
                                    <strong
                                        class="pos-info-val"
                                        :class="selectedTimelineBooking.status"
                                    >
                                        {{
                                            statusLabel(
                                                selectedTimelineBooking.status,
                                            )
                                        }}
                                    </strong>
                                </div>
                                <div class="pos-info-row">
                                    <span class="pos-info-label"
                                        >Tổng tiền</span
                                    >
                                    <strong class="pos-info-val is-money">
                                        {{
                                            formatCurrency(
                                                selectedTimelineBooking.total_price,
                                            )
                                        }}
                                    </strong>
                                </div>
                                <div class="pos-info-row">
                                    <span class="pos-info-label"
                                        >Đã thanh toán</span
                                    >
                                    <strong class="pos-info-val is-paid">
                                        {{
                                            formatCurrency(
                                                paidAmount(
                                                    selectedTimelineBooking,
                                                ),
                                            )
                                        }}
                                    </strong>
                                </div>
                                <div
                                    v-if="
                                        outstandingAmount(
                                            selectedTimelineBooking,
                                        ) > 0
                                    "
                                    class="pos-info-row is-due"
                                >
                                    <span class="pos-info-label"
                                        >Còn phải thu</span
                                    >
                                    <strong class="pos-info-val is-outstanding">
                                        {{
                                            formatCurrency(
                                                outstandingAmount(
                                                    selectedTimelineBooking,
                                                ),
                                            )
                                        }}
                                    </strong>
                                </div>
                                <div
                                    v-if="
                                        outstandingAmount(
                                            selectedTimelineBooking,
                                        ) > 0
                                    "
                                    class="pos-info-row is-due"
                                >
                                    <span class="pos-info-label"
                                        >Tình trạng thanh toán</span
                                    >
                                    <strong class="pos-info-val is-outstanding">
                                        {{
                                            paymentStateLabel(
                                                selectedTimelineBooking,
                                            )
                                        }}
                                    </strong>
                                </div>
                            </div>

                            <!-- FAST POS ACTION BUTTONS -->
                            <div class="pos-actions-stack">
                                <!-- 0. Confirm/Approve Button (if pending_approval) -->
                                <button
                                    v-if="
                                        selectedTimelineBooking.status ===
                                        'pending_approval'
                                    "
                                    type="button"
                                    class="pos-action-btn is-checkin"
                                    :disabled="updatingStatus"
                                    @click="
                                        runBookingAction(
                                            selectedTimelineBooking,
                                            'confirm',
                                        )
                                    "
                                >
                                    <AppIcon name="circleCheck" :size="18" />
                                    <span>Duyệt & Xác nhận đơn</span>
                                </button>

                                <!-- 1. Check-in Button -->
                                <button
                                    v-if="
                                        selectedTimelineBooking.status ===
                                        'confirmed'
                                    "
                                    type="button"
                                    class="pos-action-btn is-checkin"
                                    :disabled="updatingStatus"
                                    @click="
                                        runBookingAction(
                                            selectedTimelineBooking,
                                            'check_in',
                                        )
                                    "
                                >
                                    <AppIcon name="clock" :size="18" />
                                    <span>Check-in khách vào sân</span>
                                </button>

                                <!-- 2. Collect Payment Button -->
                                <button
                                    v-if="
                                        canCollectPayment(
                                            selectedTimelineBooking,
                                        )
                                    "
                                    type="button"
                                    class="pos-action-btn is-collect"
                                    @click="
                                        runBookingAction(
                                            selectedTimelineBooking,
                                            'collect',
                                        )
                                    "
                                >
                                    <AppIcon name="banknote" :size="18" />
                                    <span
                                        >Thu tiền ({{
                                            formatCurrency(
                                                outstandingAmount(
                                                    selectedTimelineBooking,
                                                ),
                                            )
                                        }})</span
                                    >
                                </button>

                                <!-- 3. Complete Button -->
                                <button
                                    v-if="
                                        selectedTimelineBooking.status ===
                                            'checked_in' &&
                                        !canCollectPayment(
                                            selectedTimelineBooking,
                                        )
                                    "
                                    type="button"
                                    class="pos-action-btn is-complete"
                                    :disabled="updatingStatus"
                                    @click="
                                        runBookingAction(
                                            selectedTimelineBooking,
                                            'complete',
                                        )
                                    "
                                >
                                    <AppIcon name="circleCheck" :size="18" />
                                    <span>Hoàn thành lượt chơi</span>
                                </button>

                                <!-- 4. Change Court Button -->
                                <button
                                    v-if="
                                        canChangeCourt(selectedTimelineBooking)
                                    "
                                    type="button"
                                    class="pos-action-btn is-change"
                                    @click="
                                        openChangeCourt(selectedTimelineBooking)
                                    "
                                >
                                    <AppIcon name="pencil" :size="16" />
                                    <span>Đổi sân thực tế</span>
                                </button>

                                <!-- 5. Reject / Cancel Button -->
                                <button
                                    v-if="
                                        [
                                            'pending_approval',
                                            'pending_payment',
                                            'confirmed',
                                        ].includes(
                                            selectedTimelineBooking.status,
                                        )
                                    "
                                    type="button"
                                    class="pos-action-btn is-cancel"
                                    @click="
                                        openStatusAction(
                                            selectedTimelineBooking,
                                            selectedTimelineBooking.status ===
                                                'pending_approval'
                                                ? 'reject'
                                                : 'cancel',
                                        )
                                    "
                                >
                                    <AppIcon name="trash" :size="16" />
                                    <span>{{
                                        selectedTimelineBooking.status ===
                                        "pending_approval"
                                            ? "Từ chối đơn"
                                            : "Hủy booking"
                                    }}</span>
                                </button>
                            </div>
                        </template>

                        <!-- Locked Slot Details -->
                        <template v-else>
                            <div class="pos-info-list">
                                <div class="pos-info-row">
                                    <span class="pos-info-label"
                                        >Trạng thái</span
                                    >
                                    <strong class="pos-info-val"
                                        >Khóa sân</strong
                                    >
                                </div>
                                <div class="pos-info-row">
                                    <span class="pos-info-label">Lý do</span>
                                    <strong class="pos-info-val">{{
                                        selectedTimelineItem?.subtitle ||
                                        "Bảo trì / Đóng sân"
                                    }}</strong>
                                </div>
                                <div class="pos-info-row">
                                    <span class="pos-info-label"
                                        >Khung giờ</span
                                    >
                                    <strong class="pos-info-val">{{
                                        selectedTimelineItem?.timeLabel
                                    }}</strong>
                                </div>
                            </div>
                        </template>
                    </div>
                </aside>

                <!-- B. QUICK WALK-IN BOOKING DRAWER -->
                <aside
                    v-else-if="drawerMode === 'create'"
                    class="pos-drawer-sheet is-create"
                >
                    <header class="pos-drawer-head">
                        <div>
                            <span class="pos-drawer-kicker"
                                >ĐẶT SÂN NHANH TẠI QUẦY (WALK-IN)</span
                            >
                            <h3 class="pos-drawer-title">
                                {{ createSlot?.court?.name }}
                            </h3>
                            <p class="pos-drawer-subtitle">
                                {{ formatDate(filters.booking_date) }} ·
                                {{ createSlot?.slot?.label }}
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

                    <form
                        class="pos-drawer-body"
                        @submit.prevent="submitCounterBooking"
                    >
                        <div
                            v-if="counterError"
                            class="pos-drawer-error-banner"
                        >
                            {{ counterError }}
                        </div>

                        <!-- Customer Information -->
                        <div class="pos-field-group">
                            <label class="pos-field">
                                <span class="pos-field-label"
                                    >Tên khách hàng
                                    <strong class="req">*</strong></span
                                >
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
                                <span class="pos-field-label"
                                    >Số điện thoại
                                    <strong class="req">*</strong></span
                                >
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
                            <span class="pos-field-label"
                                >Hình thức thanh toán</span
                            >
                            <div class="pos-mode-grid">
                                <button
                                    type="button"
                                    class="pos-mode-card"
                                    :class="{
                                        active:
                                            counterForm.collection_mode ===
                                            'cash',
                                    }"
                                    @click="
                                        counterForm.collection_mode = 'cash'
                                    "
                                >
                                    <AppIcon name="banknote" :size="18" />
                                    <strong>Tiền mặt</strong>
                                    <small>Thu ngay tại quầy</small>
                                </button>
                                <button
                                    type="button"
                                    class="pos-mode-card"
                                    :class="{
                                        active:
                                            counterForm.collection_mode ===
                                            'transfer',
                                    }"
                                    @click="
                                        counterForm.collection_mode = 'transfer'
                                    "
                                >
                                    <AppIcon name="creditCard" :size="18" />
                                    <strong>VietQR SePay</strong>
                                    <small>Quét mã đối soát tự động</small>
                                </button>
                                <button
                                    type="button"
                                    class="pos-mode-card"
                                    :class="{
                                        active:
                                            counterForm.collection_mode ===
                                            'later',
                                    }"
                                    @click="
                                        counterForm.collection_mode = 'later'
                                    "
                                >
                                    <AppIcon name="clock" :size="18" />
                                    <strong>Thu sau</strong>
                                    <small>Ghi nhận thu sau trận</small>
                                </button>
                            </div>
                        </div>

                        <!-- VietQR SePay Waiting Container -->
                        <div v-if="counterQr" class="pos-qr-container">
                            <img
                                :src="counterQr.qr_url"
                                alt="VietQR SePay"
                                class="pos-qr-image"
                            />
                            <div class="pos-qr-details">
                                <span>Nội dung CK:</span>
                                <button
                                    type="button"
                                    class="pos-qr-copy-btn"
                                    @click="
                                        copyText(counterQr.transfer_content)
                                    "
                                >
                                    {{ counterQr.transfer_content }} (Bấm để
                                    copy)
                                </button>
                                <span
                                    >Số tiền:
                                    <strong>{{
                                        formatCurrency(
                                            counterQr.payment?.amount,
                                        )
                                    }}</strong></span
                                >
                            </div>
                            <small class="pos-qr-status"
                                >Đang đợi khách quét mã chuyển khoản...</small
                            >
                        </div>

                        <!-- Footer Confirm Buttons -->
                        <div class="pos-drawer-foot">
                            <button
                                type="button"
                                class="pos-btn-plain"
                                @click="closeDrawer"
                            >
                                Hủy
                            </button>
                            <button
                                type="submit"
                                class="pos-btn-primary"
                                :disabled="
                                    !counterFormValid || counterSubmitting
                                "
                            >
                                {{
                                    counterSubmitting
                                        ? "Đang tạo đơn..."
                                        : "Xác nhận tạo đơn"
                                }}
                            </button>
                        </div>
                    </form>
                </aside>
            </div>
        </Teleport>

        <!-- 5. CHANGE COURT MODAL -->
        <Teleport to="body">
            <div
                v-if="changeCourtBooking"
                class="pos-drawer-backdrop"
                @click.self="closeChangeCourt"
            >
                <form class="pos-modal-box" @submit.prevent="saveChangeCourt">
                    <header class="pos-modal-head">
                        <div>
                            <h3>Đổi Sân Thực Tế</h3>
                            <p>
                                Chuyển booking sang sân trống khác cùng khung
                                giờ
                            </p>
                        </div>
                        <button
                            type="button"
                            class="pos-drawer-close-btn"
                            @click="closeChangeCourt"
                        >
                            ✕
                        </button>
                    </header>
                    <div class="pos-modal-body">
                        <div class="pos-field">
                            <span class="pos-field-label"
                                >Chọn sân đích mới
                                <span class="req">*</span></span
                            >
                            <div class="pos-court-select-grid">
                                <button
                                    v-for="court in changeCourtOptions"
                                    :key="court.id"
                                    type="button"
                                    class="pos-court-select-card"
                                    :class="{
                                        active:
                                            String(
                                                changeCourtForm.venue_court_id,
                                            ) === String(court.id),
                                    }"
                                    @click="
                                        changeCourtForm.venue_court_id =
                                            court.id
                                    "
                                >
                                    <div class="court-opt-text">
                                        <strong>{{ court.name }}</strong>
                                        <span>{{
                                            court.court_type?.name ||
                                            "Sân tiêu chuẩn"
                                        }}</span>
                                    </div>
                                    <AppIcon
                                        v-if="
                                            String(
                                                changeCourtForm.venue_court_id,
                                            ) === String(court.id)
                                        "
                                        name="check"
                                        :size="14"
                                        class="court-opt-check"
                                    />
                                </button>
                            </div>
                        </div>
                        <label class="pos-field">
                            <span class="pos-field-label"
                                >Lý do đổi sân <span class="req">*</span></span
                            >
                            <textarea
                                v-model.trim="
                                    changeCourtForm.court_changed_reason
                                "
                                class="pos-input"
                                rows="3"
                                placeholder="Ví dụ: Khách yêu cầu chuyển sang sân gần khán đài..."
                                required
                            ></textarea>
                        </label>
                    </div>
                    <footer class="pos-modal-foot">
                        <button
                            type="button"
                            class="pos-btn-plain"
                            @click="closeChangeCourt"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            class="pos-btn-primary"
                            :disabled="savingChangeCourt"
                        >
                            {{
                                savingChangeCourt
                                    ? "Đang lưu..."
                                    : "Xác nhận đổi sân"
                            }}
                        </button>
                    </footer>
                </form>
            </div>
        </Teleport>

        <!-- 6. COLLECT PAYMENT MODAL (TIỀN MẶT / SEPAY QR) -->
        <Teleport to="body">
            <div
                v-if="collectBooking"
                class="pos-drawer-backdrop"
                @click.self="closeCollectPayment"
            >
                <form
                    class="pos-modal-box is-collect"
                    @submit.prevent="submitCollectPayment"
                >
                    <header class="pos-modal-head">
                        <div>
                            <h3>
                                Thu Tiền Booking #{{
                                    collectBooking.booking_code
                                }}
                            </h3>
                            <p>
                                {{ customerName(collectBooking) }} ·
                                {{ customerPhone(collectBooking) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="pos-drawer-close-btn"
                            @click="closeCollectPayment"
                        >
                            ✕
                        </button>
                    </header>

                    <div class="pos-modal-body">
                        <div class="pos-collect-stats">
                            <div class="pos-cstat">
                                <span>Tổng tiền</span>
                                <strong>{{
                                    formatCurrency(collectBooking.total_price)
                                }}</strong>
                            </div>
                            <div class="pos-cstat">
                                <span>Đã thu</span>
                                <strong class="is-paid">{{
                                    formatCurrency(paidAmount(collectBooking))
                                }}</strong>
                            </div>
                            <div class="pos-cstat is-due">
                                <span>Còn lại</span>
                                <strong class="is-outstanding">{{
                                    formatCurrency(
                                        outstandingAmount(collectBooking),
                                    )
                                }}</strong>
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
                :readonly="isPayLaterBooking(collectBooking)"
                :disabled="collectForm.payment_method === 'sepay' && !!pendingTransfer(collectBooking)"
                required
              />
            </label>
            <p v-if="isPayLaterBooking(collectBooking)" class="pos-collect-hint">
              Booking trả sau chỉ được xác nhận một lần với đủ toàn bộ số tiền còn phải thu.
            </p>

                        <div class="pos-field">
                            <span class="pos-field-label">Phương thức thu</span>
                            <div class="pos-mode-grid">
                                <button
                                    type="button"
                                    class="pos-mode-card"
                                    :class="{
                                        active:
                                            collectForm.payment_method ===
                                            'cash',
                                    }"
                                    @click="collectForm.payment_method = 'cash'"
                                >
                                    <AppIcon name="banknote" :size="18" />
                                    <strong>Tiền mặt</strong>
                                </button>
                                <button
                                    type="button"
                                    class="pos-mode-card"
                                    :class="{
                                        active:
                                            collectForm.payment_method ===
                                            'sepay',
                                    }"
                                    @click="
                                        collectForm.payment_method = 'sepay'
                                    "
                                >
                                    <AppIcon name="creditCard" :size="18" />
                                    <strong>VietQR SePay</strong>
                                </button>
                            </div>
                        </div>

                        <!-- VietQR SePay Image & Polling Notice -->
                        <div v-if="collectQr" class="pos-qr-container">
                            <img
                                :src="collectQr.qr_url"
                                alt="Mã VietQR"
                                class="pos-qr-image"
                            />
                            <div class="pos-qr-details">
                                <span>Nội dung chuyển khoản:</span>
                                <button
                                    type="button"
                                    class="pos-qr-copy-btn"
                                    @click="
                                        copyText(collectQr.transfer_content)
                                    "
                                >
                                    {{ collectQr.transfer_content }} (Bấm để
                                    copy)
                                </button>
                                <span
                                    >Số tiền:
                                    <strong>{{
                                        formatCurrency(
                                            collectQr.payment?.amount,
                                        )
                                    }}</strong></span
                                >
                            </div>
                            <small class="pos-qr-status"
                                >Hệ thống đang tự động đối soát ngân
                                hàng...</small
                            >
                        </div>
                    </div>

                    <footer class="pos-modal-foot">
                        <button
                            type="button"
                            class="pos-btn-plain"
                            @click="closeCollectPayment"
                        >
                            Đóng
                        </button>
                        <button
                            type="submit"
                            class="pos-btn-primary"
                            :disabled="collectingPayment"
                        >
                            {{ collectSubmitLabel() }}
                        </button>
                    </footer>
                </form>
            </div>
        </Teleport>

        <!-- 7. STATUS ACTION MODAL (REJECT / CANCEL) -->
        <Teleport to="body">
            <div
                v-if="statusActionBooking"
                class="pos-drawer-backdrop"
                @click.self="closeStatusAction"
            >
                <form
                    class="pos-modal-box is-danger"
                    @submit.prevent="submitStatusAction"
                >
                    <header class="pos-modal-head">
                        <div>
                            <h3>{{ statusActionTitle() }}</h3>
                            <p>
                                #{{ statusActionBooking.booking_code }} ·
                                {{ customerName(statusActionBooking) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="pos-drawer-close-btn"
                            @click="closeStatusAction"
                        >
                            ✕
                        </button>
                    </header>
                    <div class="pos-modal-body">
                        <p class="pos-danger-note">
                            {{
                                statusAction === "reject"
                                    ? "Booking sẽ bị từ chối và khung sân sẽ được giải phóng ngay lập tức."
                                    : "Booking sẽ bị hủy. Nếu đã thanh toán, hệ thống sẽ tự tạo yêu cầu hoàn tiền."
                            }}
                        </p>
                        <label class="pos-field">
                            <span class="pos-field-label"
                                >Lý do
                                {{
                                    statusAction === "reject"
                                        ? "từ chối"
                                        : "hủy đơn"
                                }}</span
                            >
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
                        <button
                            type="button"
                            class="pos-btn-plain"
                            @click="closeStatusAction"
                        >
                            Đóng
                        </button>
                        <button
                            type="submit"
                            class="pos-btn-danger"
                            :disabled="updatingStatus"
                        >
                            {{
                                statusAction === "reject"
                                    ? "Xác nhận từ chối"
                                    : "Xác nhận hủy đơn"
                            }}
                        </button>
                    </footer>
                </form>
            </div>
        </Teleport>

        <!-- 5. ANALYTICS MODAL (OPERATIONAL REPORT & RECONCILIATION) -->
        <Teleport to="body">
            <div
                v-if="showAnalyticsModal"
                class="analytics-modal-backdrop"
                @click.self="showAnalyticsModal = false"
            >
                <div class="analytics-modal-dialog">
                    <header class="analytics-modal-head">
                        <div class="head-titles">
                            <h3>Báo cáo phân tích vận hành &amp; Ca trực</h3>
                            <span class="head-subtitle"
                                >{{ selectedClusterName }} ·
                                {{ formattedCurrentDate }}</span
                            >
                        </div>
                        <button
                            type="button"
                            class="analytics-close-btn"
                            @click="showAnalyticsModal = false"
                        >
                            ✕
                        </button>
                    </header>

                    <div class="analytics-modal-body">
                        <!-- TOP ROW: 2 COLUMN METRIC CARDS -->
                        <div class="analytics-top-grid">
                            <!-- A. Live Utilization Radial Meter -->
                            <div class="analytic-card">
                                <div class="gauge-ring-wrap">
                                    <svg class="gauge-svg" viewBox="0 0 36 36">
                                        <path
                                            class="gauge-bg"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        />
                                        <path
                                            class="gauge-fill"
                                            :stroke-dasharray="`${liveOccupancyPercent}, 100`"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        />
                                    </svg>
                                    <span class="gauge-num"
                                        >{{ liveOccupancyPercent }}%</span
                                    >
                                </div>
                                <div class="gauge-meta">
                                    <span class="analytic-card-label"
                                        >Công suất sân tức thì</span
                                    >
                                    <div class="gauge-val-highlight">
                                        {{ kpiStats.playingCount }} /
                                        {{ visibleCourts.length }} sân
                                    </div>
                                    <span class="gauge-desc"
                                        >Đang có khách thi đấu thực tế</span
                                    >
                                </div>
                            </div>

                            <!-- C. Cash vs SePay QR Breakdown Ratio -->
                            <div class="analytic-card is-revenue-card">
                                <div class="cash-card-head">
                                    <span class="analytic-card-label"
                                        >Cơ cấu thu ca</span
                                    >
                                    <strong class="cash-total">{{
                                        formatCurrency(paymentBreakdown.total)
                                    }}</strong>
                                </div>
                                <div class="cash-segmented-bar">
                                    <div
                                        class="seg-fill is-qr"
                                        :style="{
                                            width: `${paymentBreakdown.qrPct}%`,
                                        }"
                                        :title="`VietQR: ${formatCurrency(paymentBreakdown.qr)} (${paymentBreakdown.qrPct}%)`"
                                    ></div>
                                    <div
                                        class="seg-fill is-cash"
                                        :style="{
                                            width: `${paymentBreakdown.cashPct}%`,
                                        }"
                                        :title="`Tiền két: ${formatCurrency(paymentBreakdown.cash)} (${paymentBreakdown.cashPct}%)`"
                                    ></div>
                                </div>
                                <div class="cash-metrics-row">
                                    <div class="cash-sub-metric">
                                        <span class="sub-dot is-qr"></span>
                                        <span class="sub-name"
                                            >VietQR ({{
                                                paymentBreakdown.qrPct
                                            }}%):</span
                                        >
                                        <span class="sub-val">{{
                                            formatCurrency(paymentBreakdown.qr)
                                        }}</span>
                                    </div>
                                    <div class="cash-sub-metric">
                                        <span class="sub-dot is-cash"></span>
                                        <span class="sub-name"
                                            >Tiền két ({{
                                                paymentBreakdown.cashPct
                                            }}%):</span
                                        >
                                        <span class="sub-val">{{
                                            formatCurrency(
                                                paymentBreakdown.cash,
                                            )
                                        }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- B. Peak-Hour Occupancy Heatmap Strip (FULL WIDTH) -->
                        <div class="analytic-card is-heatmap">
                            <div class="heat-card-head">
                                <span class="analytic-card-label"
                                    >Mật độ lấp đầy theo khung giờ</span
                                >
                                <div class="heat-legend">
                                    <span class="legend-item"
                                        ><span class="legend-dot is-peak"></span
                                        >&ge;85%</span
                                    >
                                    <span class="legend-item"
                                        ><span class="legend-dot is-high"></span
                                        >60-84%</span
                                    >
                                    <span class="legend-item"
                                        ><span
                                            class="legend-dot is-medium"
                                        ></span
                                        >30-59%</span
                                    >
                                    <span class="legend-item"
                                        ><span class="legend-dot is-low"></span
                                        >&lt;30%</span
                                    >
                                </div>
                            </div>
                            <div class="heat-bars-grid">
                                <div
                                    v-for="slot in hourlyOccupancyHeatmap"
                                    :key="slot.hour"
                                    class="heat-bar-col"
                                    :title="`${slot.label}: ${slot.bookedCount}/${slot.totalCourts} sân (${slot.percent}%)`"
                                >
                                    <div class="heat-bar-track">
                                        <div
                                            class="heat-bar-fill"
                                            :class="`is-${slot.level}`"
                                            :style="{
                                                height: `${Math.max(slot.percent, 8)}%`,
                                            }"
                                        ></div>
                                    </div>
                                    <span class="heat-hour-label"
                                        >{{ slot.hour }}h</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- 7. QR TICKET SCANNER MODAL -->
        <StaffQrScannerModal
            :is-open="showQrScannerModal"
            :cluster-id="filters.venue_cluster_id"
            @close="showQrScannerModal = false"
            @checked-in="onQrCheckedIn"
            @collect-requested="openCollectPayment"
        />
    </div>
</template>

<script>
import { ownerBookingService } from "../../services/ownerBookings.js";
import { ownerBookingConfigService } from "../../services/ownerBookingConfigs.js";
import { venueClusterService } from "../../services/venueClusters.js";
import { ownerStaffShiftService } from "../../services/ownerStaffShiftService.js";
import AppIcon from "../../components/AppIcon.vue";
import MiniCalendar from "../../components/MiniCalendar.vue";
import StaffQrScannerModal from "../../components/staff/StaffQrScannerModal.vue";
import { playSuccessChime } from "../../utils/audioChime.js";
import {
    addCalendarDays,
    businessDateLabel,
    businessDateString,
    businessMinutes,
    businessTimeString,
} from "../../utils/businessTime.js";

function localIsoDate(date = new Date()) {
    return businessDateString(date);
}

export default {
    name: "StaffBookingsPOS",
    components: { AppIcon, MiniCalendar, StaffQrScannerModal },
    data() {
        return {
            clusters: [],
            courts: [],
            bookings: [],
            filters: {
                venue_cluster_id: "",
                venue_court_id: "",
                booking_date: localIsoDate(),
                status: "",
            },
            searchKeyword: "",
            loading: true,
            scheduleLoading: false,
            scheduleError: "",
            scheduleSlots: [],
            scheduleCourts: [],
            scheduleBusyIntervals: [],
            scheduleSlotStatuses: [],
            scheduleOperatingHours: null,
            bookingConfigs: {},
            selectedTimelineItem: null,
            activeTimePeriod: "business",
            viewMode: "matrix", // 'matrix' | 'arena' | 'list'
            currentClockTime: "",
            clockTimer: null,
            highlightedHour: null,
            error: "",
            notice: "",
            showCalDropdown: false,
            showPeriodDropdown: false,
            showQrScannerModal: false,
            showAnalyticsModal: false,

            // Shift attendance state
            shiftLoading: false,
            todayShift: null,
            attendanceSubmitting: false,

            // Drawer states
            drawerMode: null, // null | 'view' | 'create'
            createSlot: null,
            counterForm: {
                walk_in_name: "",
                walk_in_phone: "",
                collection_mode: "cash",
            },
            counterError: "",
            counterSubmitting: false,
            counterQr: null,
            counterPollInterval: null,

            // Change court & Collect payment states
            changeCourtBooking: null,
            changeCourtOptions: [],
            changeCourtForm: {
                venue_court_id: "",
                court_changed_reason: "",
            },
            savingChangeCourt: false,

            collectBooking: null,
            collectForm: {
                payment_method: "cash",
                amount: 0,
            },
            collectQr: null,
            collectingPayment: false,
            collectPollInterval: null,

            statusActionBooking: null,
            statusAction: "",
            statusActionReason: "",
            updatingStatus: false,

            timePeriods: [
                {
                    key: "business",
                    label: "Giờ mở cửa",
                    start: 360,
                    end: 1380,
                    range: "06:00 - 23:00",
                },
                {
                    key: "morning",
                    label: "Ca sáng",
                    start: 360,
                    end: 720,
                    range: "06:00 - 12:00",
                },
                {
                    key: "afternoon",
                    label: "Ca chiều",
                    start: 720,
                    end: 1080,
                    range: "12:00 - 18:00",
                },
                {
                    key: "evening",
                    label: "Ca tối",
                    start: 1080,
                    end: 1380,
                    range: "18:00 - 23:00",
                },
                {
                    key: "full24h",
                    label: "Cả ngày 24h",
                    start: 0,
                    end: 1440,
                    range: "00:00 - 24:00",
                },
            ],
        };
    },
    computed: {
        formattedCurrentDate() {
            if (!this.filters.booking_date) return "";
            const date = new Date(`${this.filters.booking_date}T00:00:00`);
            const days = [
                "Chủ Nhật",
                "Thứ Hai",
                "Thứ Ba",
                "Thứ Tư",
                "Thứ Năm",
                "Thứ Sáu",
                "Thứ Bảy",
            ];
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
            return (
                this.timePeriods.find(
                    (period) => period.key === this.activeTimePeriod,
                ) || this.timePeriods[0]
            );
        },
        bookingDurationMinutes() {
            const configured = Number(
                this.bookingConfigs[String(this.filters.venue_cluster_id)]
                    ?.min_duration_minutes,
            );
            return Number.isInteger(configured) &&
                configured >= 30 &&
                configured <= 120 &&
                configured % 30 === 0
                ? configured
                : 30;
        },
        verticalHourlySlots() {
            const slots = [];
            const step = this.bookingDurationMinutes;
            const period = this.activePeriod;

            for (
                let minutes = period.start;
                minutes < period.end;
                minutes += step
            ) {
                const end = Math.min(minutes + step, period.end);
                if (end <= minutes) continue;
                slots.push({
                    start: minutes,
                    end,
                    label: `${this.minutesToTime(minutes)} - ${this.minutesToTime(end)}`,
                    timeLabel: `${this.minutesToTime(minutes)} - ${this.minutesToTime(end)}`,
                });
            }
            return slots;
        },
        kpiStats() {
            const allBlocks = this.timelineBlocks.filter(
                (b) => b.type === "booking",
            );
            let playing = 0;
            let confirmed = 0;
            let pending = 0;
            let unpaid = 0;
            let unpaidMoney = 0;
            let collectedMoney = 0;

            for (const b of allBlocks) {
                const booking = b.booking;
                if (!booking) continue;
                if (booking.status === "checked_in") playing++;
                if (booking.status === "confirmed") confirmed++;
                if (
                    ["pending_approval", "pending_payment"].includes(
                        booking.status,
                    )
                )
                    pending++;

                const outstanding = this.outstandingAmount(booking);
                if (
                    outstanding > 0 &&
                    !["cancelled", "rejected"].includes(booking.status)
                ) {
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
                return this.bookingRanges(booking)
                    .map((range) => this.makeBookingBlock(booking, range))
                    .filter(Boolean);
            });

            const bookingKeys = new Set(
                bookingBlocks.map(
                    (block) => `${block.courtId}|${block.start}|${block.end}`,
                ),
            );
            const lockBlocks = this.scheduleBusyIntervals
                .filter(
                    (interval) =>
                        interval.source === "slot_lock" &&
                        interval.status === "manual",
                )
                .map((interval) => this.makeLockBlock(interval))
                .filter(
                    (block) =>
                        block &&
                        !bookingKeys.has(
                            `${block.courtId}|${block.start}|${block.end}`,
                        ),
                );

            return [...bookingBlocks, ...lockBlocks]
                .filter(
                    (block) =>
                        block.end > this.activePeriod.start &&
                        block.start < this.activePeriod.end,
                )
                .sort(
                    (a, b) =>
                        a.start - b.start ||
                        a.end - b.end ||
                        a.title.localeCompare(b.title),
                );
        },
        selectedTimelineBooking() {
            if (
                !this.selectedTimelineItem ||
                this.selectedTimelineItem.type !== "booking"
            )
                return null;
            return (
                this.bookings.find(
                    (booking) =>
                        String(booking.id) ===
                        String(this.selectedTimelineItem.bookingId),
                ) ||
                this.selectedTimelineItem.booking ||
                null
            );
        },
        counterFormValid() {
            return (
                this.counterForm.walk_in_name.trim().length >= 2 &&
                this.counterForm.walk_in_phone.trim().length >= 8
            );
        },
        shiftDisplayName() {
            if (!this.todayShift) return "";
            return (
                this.todayShift.shift?.name ||
                `${this.formatTime(this.todayShift.start_time)} - ${this.formatTime(this.todayShift.end_time)}`
            );
        },
        liveOccupancyPercent() {
            const total = this.visibleCourts.length;
            if (!total) return 0;
            return Math.min(
                Math.round((this.kpiStats.playingCount / total) * 100),
                100,
            );
        },
        hourlyOccupancyHeatmap() {
            const courts = this.visibleCourts;
            const totalCourts = courts.length;
            if (!totalCourts) return [];

            let startHour = 6;
            let endHour = 23;

            if (this.scheduleOperatingHours) {
                if (this.scheduleOperatingHours.open_time) {
                    startHour = parseInt(
                        this.scheduleOperatingHours.open_time.split(":")[0],
                        10,
                    );
                }
                if (this.scheduleOperatingHours.close_time) {
                    const closeH = parseInt(
                        this.scheduleOperatingHours.close_time.split(":")[0],
                        10,
                    );
                    const closeM = parseInt(
                        this.scheduleOperatingHours.close_time.split(":")[1] ||
                            "0",
                        10,
                    );
                    endHour =
                        closeM > 0 ? closeH : Math.max(closeH - 1, startHour);
                }
            }

            const blocks = this.timelineBlocks.filter(
                (b) => b.type === "booking",
            );
            const hours = [];

            for (let h = startHour; h <= endHour; h++) {
                const slotStart = h * 60;
                const slotEnd = (h + 1) * 60;

                const bookedCourts = new Set();
                for (const b of blocks) {
                    if (
                        b.start < slotEnd &&
                        b.end > slotStart &&
                        !["cancelled", "rejected"].includes(b.booking?.status)
                    ) {
                        bookedCourts.add(String(b.courtId));
                    }
                }

                const count = bookedCourts.size;
                const percent = Math.min(
                    Math.round((count / totalCourts) * 100),
                    100,
                );
                let level = "empty";
                if (percent >= 85) level = "peak";
                else if (percent >= 60) level = "high";
                else if (percent >= 30) level = "medium";
                else if (percent > 0) level = "low";

                hours.push({
                    hour: h,
                    label: `${String(h).padStart(2, "0")}:00`,
                    bookedCount: count,
                    totalCourts,
                    percent,
                    level,
                });
            }
            return hours;
        },
        paymentBreakdown() {
            let cash = 0;
            let qr = 0;
            for (const booking of this.bookings) {
                if (!booking.payments || !Array.isArray(booking.payments))
                    continue;
                for (const p of booking.payments) {
                    if (p.status === "paid") {
                        const amt = Number(p.amount) || 0;
                        if (p.method === "cash") cash += amt;
                        else qr += amt;
                    }
                }
            }
            const total = cash + qr;
            const cashPct = total > 0 ? Math.round((cash / total) * 100) : 0;
            const qrPct = total > 0 ? 100 - cashPct : 0;

            return {
                cash,
                qr,
                total,
                cashPct,
                qrPct,
            };
        },
        selectedClusterName() {
            const c = this.clusters.find(
                (cl) => String(cl.id) === String(this.filters.venue_cluster_id),
            );
            return c?.name || "Cụm sân";
        },
        filteredBookingsList() {
            if (!this.bookings || !Array.isArray(this.bookings)) return [];
            let list = [...this.bookings];
            if (this.searchKeyword) {
                const kw = this.searchKeyword.toLowerCase();
                list = list.filter((b) => {
                    const code = (b.booking_code || "").toLowerCase();
                    const name = this.customerName(b).toLowerCase();
                    const phone = (this.customerPhone(b) || "").toLowerCase();
                    const court = (b.court?.name || "").toLowerCase();
                    return (
                        code.includes(kw) ||
                        name.includes(kw) ||
                        phone.includes(kw) ||
                        court.includes(kw)
                    );
                });
            }
            return list.sort((a, b) =>
                (a.start_time || "").localeCompare(b.start_time || ""),
            );
        },
    },
    async mounted() {
        this.syncClusterFromStorage();
        window.addEventListener(
            "owner-cluster-changed",
            this.handleClusterChanged,
        );
        document.addEventListener("click", this.handleOutsideClick);
        await this.loadClusters();
        await this.loadBookingConfigs();
        await this.loadTodayShift();
        await this.loadBookings();
    },
    beforeUnmount() {
        window.removeEventListener(
            "owner-cluster-changed",
            this.handleClusterChanged,
        );
        document.removeEventListener("click", this.handleOutsideClick);
        this.clearCollectPolling();
        this.clearCounterPolling();
    },
    methods: {
        handleOutsideClick(e) {
            if (!e.target.closest(".pos-cal-dropdown")) {
                this.showCalDropdown = false;
            }
            if (!e.target.closest(".pos-period-wrapper")) {
                this.showPeriodDropdown = false;
            }
        },
        toggleCalDropdown() {
            this.showCalDropdown = !this.showCalDropdown;
            if (this.showCalDropdown) {
                this.showPeriodDropdown = false;
            }
        },
        togglePeriodDropdown() {
            this.showPeriodDropdown = !this.showPeriodDropdown;
            if (this.showPeriodDropdown) {
                this.showCalDropdown = false;
            }
        },
        selectTimePeriod(key) {
            this.activeTimePeriod = key;
            this.showPeriodDropdown = false;
        },
        updateClock() {
            this.currentClockTime = businessTimeString();
        },
        async quickCheckIn(booking) {
            if (!booking) return;
            await this.runBookingAction(booking, "check_in");
        },
        openBookingDrawerFromList(booking) {
            const block = this.timelineBlocks.find(
                (b) => String(b.bookingId) === String(booking.id),
            ) || {
                key: `booking-${booking.id}`,
                type: "booking",
                bookingId: booking.id,
                booking,
                courtId: booking.venue_court_id,
                courtName:
                    booking.court?.name ||
                    this.courtName(booking.venue_court_id),
                start: this.timeToMinutes(booking.start_time),
                end: this.timeToMinutes(booking.end_time),
                title: this.customerName(booking),
                subtitle: `${booking.booking_code} · ${this.statusLabel(booking.status)}`,
                timeLabel: `${this.formatTime(booking.start_time)} - ${this.formatTime(booking.end_time)}`,
                kindClass: this.timelineBookingClass(booking),
            };
            this.selectTimelineItem(block);
        },
        formatCourtShortName(name) {
            if (!name) return "Sân";
            return (
                name
                    .replace(
                        /^Sân\s+(cầu lông|pickleball|tennis|bóng đá)?\s*/i,
                        "",
                    )
                    .trim() || name
            );
        },
        formatCourtTypeName(court) {
            return court.court_type?.name || "Sân tiêu chuẩn";
        },
        toggleInfographic() {
            this.isInfographicCollapsed = !this.isInfographicCollapsed;
            localStorage.setItem(
                "pos_infographic_collapsed",
                this.isInfographicCollapsed ? "1" : "0",
            );
        },
        jumpToHour(h) {
            this.highlightedHour = h;
            this.$nextTick(() => {
                const el = document.getElementById(`slot-row-${h}`);
                if (el) {
                    el.scrollIntoView({ behavior: "smooth", block: "center" });
                }
            });
            setTimeout(() => {
                if (this.highlightedHour === h) {
                    this.highlightedHour = null;
                }
            }, 2500);
        },
        syncClusterFromStorage() {
            this.filters.venue_cluster_id =
                localStorage.getItem("selected_cluster") || "";
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
            return iso === addCalendarDays(localIsoDate(), 1);
        },
        setToday() {
            this.filters.booking_date = localIsoDate();
            this.loadBookings();
        },
        setTomorrow() {
            this.filters.booking_date = addCalendarDays(localIsoDate(), 1);
            this.loadBookings();
        },
        shiftDate(days) {
            this.filters.booking_date = addCalendarDays(
                this.filters.booking_date,
                days,
            );
            this.loadBookings();
        },
        onDateSelect(val) {
            this.filters.booking_date = val;
            this.showCalDropdown = false;
            this.loadBookings();
        },

        async loadClusters() {
            try {
                const response = await venueClusterService.getClusters({
                    compact: 1,
                });
                this.clusters = response.data || [];
                if (!this.filters.venue_cluster_id && this.clusters.length) {
                    this.filters.venue_cluster_id = String(this.clusters[0].id);
                    localStorage.setItem(
                        "selected_cluster",
                        this.filters.venue_cluster_id,
                    );
                }
            } catch {
                this.clusters = [];
            }
        },

        async loadBookingConfigs() {
            try {
                const response = await ownerBookingConfigService.list();
                this.bookingConfigs = (response.data || []).reduce(
                    (configs, cluster) => {
                        configs[String(cluster.id)] =
                            cluster.booking_config || null;
                        return configs;
                    },
                    {},
                );
            } catch {
                this.bookingConfigs = {};
            }
        },

        async loadTodayShift() {
            this.shiftLoading = true;
            try {
                const today = localIsoDate();
                const res = await ownerStaffShiftService.mySchedules({
                    start_date: today,
                    end_date: today,
                });
                const schedules = res.data || [];
                this.todayShift =
                    schedules.find((s) => String(s.date) === today) || null;
            } catch {
                this.todayShift = null;
            } finally {
                this.shiftLoading = false;
            }
        },

        async loadBookings() {
            this.loading = true;
            this.scheduleLoading = true;
            this.scheduleError = "";
            this.error = "";

            if (!this.filters.venue_cluster_id) {
                this.syncClusterFromStorage();
            }
            if (!this.filters.venue_cluster_id && this.clusters.length) {
                this.filters.venue_cluster_id = String(this.clusters[0].id);
            }

            if (!this.filters.venue_cluster_id) {
                this.loading = false;
                this.scheduleLoading = false;
                return;
            }

            try {
                const [bookingsRes, scheduleRes] = await Promise.all([
                    ownerBookingService.list({
                        venue_cluster_id: this.filters.venue_cluster_id,
                        venue_court_id:
                            this.filters.venue_court_id || undefined,
                        booking_date: this.filters.booking_date || undefined,
                    }),
                    ownerBookingService.getSchedule({
                        venue_cluster_id: this.filters.venue_cluster_id,
                        booking_date: this.filters.booking_date || undefined,
                        booking_type: "single",
                    }),
                ]);

                this.bookings = bookingsRes.data || [];
                this.scheduleCourts = scheduleRes.courts || [];
                this.scheduleBusyIntervals = scheduleRes.busy_intervals || [];
                this.scheduleSlotStatuses = scheduleRes.statuses || [];
                this.scheduleOperatingHours =
                    scheduleRes.operating_hours || null;

                // Dynamically sync business period with cluster operating hours
                if (
                    this.scheduleOperatingHours &&
                    this.scheduleOperatingHours.open_time &&
                    this.scheduleOperatingHours.close_time
                ) {
                    const openM = this.timeToMinutes(
                        this.scheduleOperatingHours.open_time,
                    );
                    const closeM = this.timeToMinutes(
                        this.scheduleOperatingHours.close_time,
                    );
                    if (closeM > openM && this.timePeriods[0]) {
                        this.timePeriods[0].start = openM;
                        this.timePeriods[0].end = closeM;
                        this.timePeriods[0].range = `${this.formatTime(this.scheduleOperatingHours.open_time)} - ${this.formatTime(this.scheduleOperatingHours.close_time)}`;
                    }
                }
            } catch (err) {
                this.scheduleError = err.message || "Không thể tải lịch sân.";
                this.bookings = [];
                this.scheduleCourts = [];
            } finally {
                this.loading = false;
                this.scheduleLoading = false;
            }
        },

        getSlotCellInfo(courtId, slot) {
            const block = this.timelineBlocks.find(
                (b) =>
                    String(b.courtId) === String(courtId) &&
                    b.start < slot.end &&
                    b.end > slot.start,
            );

            if (!block) {
                return {
                    type: "empty",
                    shouldRender: true,
                    rowspan: 1,
                    block: null,
                };
            }

            // Find the first visible slot that intersects with this block
            const firstVisibleSlot = this.verticalHourlySlots.find(
                (s) => s.start < block.end && s.end > block.start,
            );

            const isFirstSlot =
                firstVisibleSlot && firstVisibleSlot.start === slot.start;

            if (isFirstSlot) {
                // Calculate how many visible slots this block spans across
                const spanningSlots = this.verticalHourlySlots.filter(
                    (s) => s.start >= slot.start && s.start < block.end,
                );
                const rowspan = Math.max(spanningSlots.length, 1);

                return {
                    type: "block",
                    shouldRender: true,
                    rowspan,
                    block,
                };
            }

            // If covered by a previous slot's rowspan, do not render <td>
            return {
                type: "covered",
                shouldRender: false,
                rowspan: 0,
                block,
            };
        },

        getBlockForCourtAndSlot(courtId, slot) {
            return (
                this.timelineBlocks.find(
                    (block) =>
                        String(block.courtId) === String(courtId) &&
                        block.start < slot.end &&
                        block.end > slot.start,
                ) || null
            );
        },

        isSlotHighlighted(courtId, slot) {
            if (!this.searchKeyword) return false;
            const block = this.getBlockForCourtAndSlot(courtId, slot);
            if (!block) return false;
            const kw = this.searchKeyword.toLowerCase();
            return (
                block.title.toLowerCase().includes(kw) ||
                (block.booking?.booking_code || "")
                    .toLowerCase()
                    .includes(kw) ||
                (this.customerPhone(block.booking) || "").includes(kw)
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

            const currentMinutes = businessMinutes();
            const isTodayBooking = this.isToday(this.filters.booking_date);

            let statusPill = null;
            let turnoverClass = "";
            if (booking.status === "checked_in") {
                if (paymentState === "overdue") {
                    statusPill = {
                        type: "overtime",
                        label: "Quá hạn thanh toán",
                    };
                    turnoverClass = "is-overtime";
                } else if (isTodayBooking && currentMinutes > end) {
                    const overMins = currentMinutes - end;
                    statusPill = {
                        type: "overtime",
                        label: `Quá ${overMins}p`,
                    };
                    turnoverClass = "is-overtime";
                } else if (
                    isTodayBooking &&
                    end - currentMinutes <= 10 &&
                    end - currentMinutes > 0
                ) {
                    const remainingMins = end - currentMinutes;
                    statusPill = {
                        type: "ending-soon",
                        label: `Còn ${remainingMins}p`,
                    };
                    turnoverClass = "is-ending-soon";
                } else {
                    statusPill = { type: "playing", label: "Đang chơi" };
                }
            } else if (booking.status === "completed") {
                statusPill = { type: "confirmed", label: "Hoàn tất" };
            } else if (booking.status === "pending_approval") {
                statusPill = { type: "unpaid", label: "Chờ duyệt" };
            } else if (booking.status === "pending_payment") {
                statusPill = { type: "unpaid", label: "Chờ thanh toán" };
            } else if (paymentState === "paid") {
                statusPill = { type: "confirmed", label: "Đã thanh toán" };
            } else if (paymentState === "partial") {
                statusPill = { type: "ending-soon", label: "Đã cọc" };
            } else {
                statusPill = { type: "unpaid", label: "Chưa thu" };
            }

            return {
                key: `booking-${booking.id}-${courtId}-${range.startTime}-${range.endTime}`,
                type: "booking",
                bookingId: booking.id,
                booking,
                courtId,
                courtName: range.courtName || this.courtName(courtId),
                start,
                end,
                title: customer || booking.booking_code || "Khách đặt sân",
                subtitle: `${booking.booking_code || "Booking"} · ${this.statusLabel(booking.status)}`,
                timeLabel: `${this.formatTime(range.startTime)} - ${this.formatTime(range.endTime)}`,
                kindClass:
                    `${this.timelineBookingClass(booking)} ${turnoverClass}`.trim(),
                statusPill,
            };
        },

        makeLockBlock(interval) {
            const start = this.timeToMinutes(interval.start_time);
            const end = this.timeToMinutes(interval.end_time);
            if (!interval.venue_court_id || end <= start) return null;

            return {
                key: `lock-${interval.schedule_lock_id || `${interval.venue_court_id}-${interval.start_time}`}`,
                type: "lock",
                courtId: interval.venue_court_id,
                courtName: this.courtName(interval.venue_court_id),
                start,
                end,
                title: "Khóa sân",
                subtitle: interval.reason || "Bảo trì / Đóng sân",
                timeLabel: `${this.formatTime(interval.start_time)} - ${this.formatTime(interval.end_time)}`,
                kindClass: "is-locked-block",
                statusPill: { type: "locked", label: "Khóa sân" },
            };
        },

        timelineBookingClass(booking) {
            if (booking.status === "checked_in") return "is-playing-block";
            if (
                ["pending_approval", "pending_payment"].includes(booking.status)
            )
                return "is-pending-block";
            if (["cancelled", "rejected", "expired"].includes(booking.status))
                return "is-cancelled-block";
            return "is-confirmed-block";
        },

        isSlotPast(slot) {
            if (!this.filters.booking_date) return false;
            const today = localIsoDate();
            if (this.filters.booking_date < today) return true;
            if (this.filters.booking_date > today) return false;
            const nowMinutes = businessMinutes();
            return slot.start <= nowMinutes;
        },

        onCellClick(court, slot) {
            const existing = this.getBlockForCourtAndSlot(court.id, slot);
            if (existing) {
                this.selectTimelineItem(existing);
                return;
            }
            if (this.isSlotPast(slot)) {
                this.error =
                    "Khung giờ này đã kết thúc trong quá khứ. Không thể tạo đơn đặt sân mới.";
                return;
            }
            this.openCreateDrawer(court, slot);
        },

        selectTimelineItem(block) {
            this.selectedTimelineItem = block;
            this.drawerMode = "view";
            this.createSlot = null;
        },

        openCreateDrawer(court, slot) {
            this.createSlot = { court, slot };
            this.counterForm = {
                walk_in_name: "",
                walk_in_phone: "",
                collection_mode: "cash",
            };
            this.counterError = "";
            this.counterQr = null;
            this.selectedTimelineItem = null;
            this.drawerMode = "create";
        },

        closeDrawer() {
            this.drawerMode = null;
            this.selectedTimelineItem = null;
            this.createSlot = null;
            this.counterQr = null;
            this.counterError = "";
            this.clearCounterPolling();
        },

        async submitCounterBooking() {
            if (
                !this.counterFormValid ||
                this.counterSubmitting ||
                !this.createSlot
            )
                return;
            this.counterSubmitting = true;
            this.counterError = "";
            this.error = "";
            this.notice = "";

            const slot = this.createSlot.slot;
            const court = this.createSlot.court;

            const payload = {
                venue_court_id: court.id,
                booking_date: this.filters.booking_date,
                start_time: `${this.minutesToTime(slot.start)}:00`,
                end_time: `${this.minutesToTime(slot.end)}:00`,
                payment_option:
                    this.counterForm.collection_mode === "later"
                        ? "no_prepay"
                        : "full_payment",
                is_paid: this.counterForm.collection_mode === "cash",
                payment_method:
                    this.counterForm.collection_mode === "transfer"
                        ? "sepay"
                        : this.counterForm.collection_mode === "cash"
                          ? "cash"
                          : null,
                walk_in_name: this.counterForm.walk_in_name.trim(),
                walk_in_phone: this.counterForm.walk_in_phone.trim(),
            };

            try {
                const response =
                    await ownerBookingService.storeCounter(payload);
                if (
                    this.counterForm.collection_mode === "transfer" &&
                    response.payment_qr
                ) {
                    this.counterQr = response.payment_qr;
                    this.startCounterPolling(response.data?.id);
                } else {
                    this.notice = "Đã tạo đơn đặt sân tại quầy thành công!";
                    playSuccessChime();
                    this.closeDrawer();
                    await this.loadBookings();
                }
            } catch (err) {
                this.counterError =
                    err.message || "Không thể tạo đặt sân tại quầy.";
                this.error = this.counterError;
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
                    if (
                        this.outstandingAmount(booking) <= 0 ||
                        booking.status === "confirmed"
                    ) {
                        this.notice = "Chuyển khoản SePay thành công!";
                        playSuccessChime();
                        this.closeDrawer();
                        await this.loadBookings();
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
            if (action === "collect") {
                this.openCollectPayment(booking);
                return;
            }
            if (action === "change_court") {
                this.openChangeCourt(booking);
                return;
            }
            if (["reject", "cancel"].includes(action)) {
                this.openStatusAction(booking, action);
                return;
            }
            if (action === "check_in") {
                const outstanding = this.outstandingAmount(booking);
                if (outstanding > 0) {
                    const formattedDue = this.formatCurrency(outstanding);
                    const proceed = confirm(
                        `Đơn đặt sân này còn thiếu ${formattedDue}. Bạn có muốn tiếp tục Check-in cho khách vào sân (thu sau) không?\n\n- Nhấn "OK" để tiếp tục Check-in vào sân.\n- Nhấn "Hủy" để chuyển sang màn hình Thu tiền ngay.`,
                    );
                    if (!proceed) {
                        this.openCollectPayment(booking);
                        return;
                    }
                }
            }

            this.updatingStatus = true;
            this.error = "";
            this.notice = "";
            try {
                await ownerBookingService.updateStatus(booking.id, { action });
                this.notice = "Đã cập nhật trạng thái đơn đặt sân!";
                this.closeDrawer();
                await this.loadBookings();
            } catch (err) {
                this.error = err.message || "Không thể cập nhật trạng thái.";
            } finally {
                this.updatingStatus = false;
            }
        },

    canCollectPayment(booking) {
      return (
        booking.status !== 'pending_approval' &&
        !(booking.status === 'pending_payment' && (booking.effective_payment_option || booking.payment_option) === 'no_prepay') &&
        !['cancelled', 'expired', 'rejected', 'no_show'].includes(booking.status) &&
        this.outstandingAmount(booking) > 0
      );
    },

    isPayLaterBooking(booking) {
      return booking?.status === 'confirmed'
        && (booking?.effective_payment_option || booking?.payment_option) === 'no_prepay';
    },

        canChangeCourt(booking) {
            return (
                ["pending_approval", "pending_payment", "confirmed"].includes(
                    booking.status,
                ) && this.bookingRanges(booking).length <= 1
            );
        },

        async openChangeCourt(booking) {
            this.changeCourtBooking = booking;
            this.changeCourtForm = {
                venue_court_id: booking.venue_court_id,
                court_changed_reason: "",
            };
            try {
                const response = await venueClusterService.getCourts(
                    booking.venue_cluster_id,
                    { status: "active" },
                );
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
            this.error = "";
            try {
                await ownerBookingService.changeCourt(
                    this.changeCourtBooking.id,
                    this.changeCourtForm,
                );
                this.notice = "Đã đổi sân thực tế thành công!";
                this.closeChangeCourt();
                this.closeDrawer();
                await this.loadBookings();
            } catch (err) {
                this.error = err.message || "Không thể đổi sân.";
            } finally {
                this.savingChangeCourt = false;
            }
        },

        openCollectPayment(booking) {
            this.showQrScannerModal = false;
            const pendingTransfer = this.pendingTransfer(booking);
            this.collectBooking = booking;
            this.collectForm = {
                payment_method: pendingTransfer ? "sepay" : "cash",
                amount: pendingTransfer
                    ? Number(pendingTransfer.amount)
                    : this.outstandingAmount(booking),
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
      if (this.isPayLaterBooking(this.collectBooking)) {
        this.collectForm.amount = this.outstandingAmount(this.collectBooking);
      }
      this.collectingPayment = true;
      this.error = '';
      try {
        const response = await ownerBookingService.collectPayment(this.collectBooking.id, {
          payment_method: this.collectForm.payment_method,
          amount: this.collectForm.amount,
        });

                if (this.collectForm.payment_method === "sepay") {
                    this.collectQr = response.payment_qr || null;
                    this.startCollectPolling();
                } else {
                    this.notice = "Đã ghi nhận thanh toán thành công!";
                    await this.loadBookings();
                    this.closeCollectPayment();
                    this.closeDrawer();
                    await this.loadBookings();
                }
            } catch (err) {
                this.error = err.message || "Không thể ghi nhận thu tiền.";
            } finally {
                this.collectingPayment = false;
            }
        },

        startCollectPolling() {
            this.clearCollectPolling();
            this.collectPollInterval = setInterval(async () => {
                if (!this.collectBooking) return;
                try {
                    const res = await ownerBookingService.show(
                        this.collectBooking.id,
                    );
                    const booking = res.data || res;
                    this.collectBooking = booking;
                    if (this.outstandingAmount(booking) <= 0) {
                        this.notice = "Chuyển khoản SePay đã được ghi nhận!";
                        this.closeCollectPayment();
                        this.closeDrawer();
                        await this.loadBookings();
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
      if (this.collectForm.payment_method !== 'sepay' && this.isPayLaterBooking(this.collectBooking)) {
        return 'Xác nhận đã nhận đủ tiền mặt';
      }
      if (this.collectForm.payment_method !== 'sepay') return 'Xác nhận thu tiền mặt';
      return this.pendingTransfer(this.collectBooking) ? 'Xem lại mã VietQR' : 'Tạo mã VietQR SePay';
    },

        openStatusAction(booking, action) {
            this.statusActionBooking = booking;
            this.statusAction = action;
            this.statusActionReason = "";
        },

        closeStatusAction() {
            this.statusActionBooking = null;
            this.statusAction = "";
            this.statusActionReason = "";
        },

        async submitStatusAction() {
            if (!this.statusActionBooking || !this.statusActionReason) return;
            this.updatingStatus = true;
            this.error = "";
            try {
                await ownerBookingService.updateStatus(
                    this.statusActionBooking.id,
                    {
                        action: this.statusAction,
                        status_reason: this.statusActionReason,
                    },
                );
                this.notice =
                    this.statusAction === "reject"
                        ? "Đã từ chối booking!"
                        : "Đã hủy booking!";
                this.closeStatusAction();
                this.closeDrawer();
                await this.loadBookings();
            } catch (err) {
                this.error = err.message || "Không thể thao tác đơn.";
            } finally {
                this.updatingStatus = false;
            }
        },

        statusActionTitle() {
            return this.statusAction === "reject"
                ? "Từ chối đơn đặt sân"
                : "Hủy booking đặt sân";
        },

        bookingRanges(booking) {
            if (booking.items?.length) {
                return booking.items.map((item) => ({
                    key: item.id,
                    venueCourtId: item.venue_court_id,
                    courtName: item.venue_court?.name || "-",
                    startTime: item.start_time,
                    endTime: item.end_time,
                }));
            }
            return [
                {
                    key: booking.id,
                    venueCourtId: booking.venue_court_id,
                    courtName: booking.venue_court?.name || "-",
                    startTime: booking.start_time,
                    endTime: booking.end_time,
                },
            ];
        },

        courtName(courtId) {
            return (
                this.scheduleCourts.find(
                    (c) => String(c.id) === String(courtId),
                )?.name || "-"
            );
        },

        customerName(booking) {
            return (
                booking?.customer?.full_name ||
                booking?.customer?.username ||
                booking?.walk_in_name ||
                "Khách vãng lai"
            );
        },

        customerPhone(booking) {
            return booking?.customer?.phone || booking?.walk_in_phone || "-";
        },

        paidAmount(booking) {
            return (booking?.payments || [])
                .filter((p) => p.status === "paid")
                .reduce((sum, p) => sum + Number(p.amount || 0), 0);
        },

        outstandingAmount(booking) {
            return Math.max(
                Number(booking?.total_price || 0) - this.paidAmount(booking),
                0,
            );
        },

        paymentState(booking) {
            if (this.outstandingAmount(booking) <= 0) return "paid";
            if (
                booking?.settlement_status === "overdue" ||
                this.isSettlementOverdue(booking)
            )
                return "overdue";
            if (this.bookingHasPendingTransfer(booking)) return "pending";
            if (booking?.settlement_status) return booking.settlement_status;
            if (this.paidAmount(booking) > 0) return "partial";
            return "unpaid";
        },

        isSettlementOverdue(booking) {
            if (
                !booking?.booking_date ||
                !booking?.end_time ||
                !["checked_in", "completed"].includes(booking.status)
            ) {
                return false;
            }

            const endAt = new Date(
                `${String(booking.booking_date).slice(0, 10)}T${String(booking.end_time).slice(0, 8)}`,
            );
            return (
                !Number.isNaN(endAt.getTime()) &&
                Date.now() > endAt.getTime() + 15 * 60 * 1000
            );
        },

        paymentStateLabel(booking) {
            return (
                {
                    paid: "Đã đủ",
                    pending: "Chờ chuyển khoản",
                    partial: "Còn thiếu",
                    unpaid: "Chưa thanh toán",
                    overdue: "Quá hạn thanh toán",
                }[this.paymentState(booking)] || "Chưa xác định"
            );
        },

        bookingHasPendingTransfer(booking) {
            return !!this.pendingTransfer(booking);
        },

        pendingTransfer(booking) {
            return (
                (booking?.payments || []).find(
                    (p) => p.method === "sepay" && p.status === "pending",
                ) || null
            );
        },

        statusLabel(status) {
            return (
                {
                    pending_approval: "Chờ duyệt",
                    pending_payment: "Chờ thanh toán",
                    confirmed: "Đã xác nhận",
                    checked_in: "Đang chơi",
                    completed: "Hoàn thành",
                    no_show: "Vắng mặt",
                    cancelled: "Đã hủy",
                    rejected: "Đã từ chối",
                    expired: "Hết hạn",
                }[status] || status
            );
        },

        formatDate(value) {
            return businessDateLabel(value) || "-";
        },

        formatTime(time) {
            return (time || "").slice(0, 5);
        },

        timeToMinutes(value) {
            const [h, m] = this.formatTime(value).split(":").map(Number);
            return (h || 0) * 60 + (m || 0);
        },

        minutesToTime(mins) {
            const h = Math.floor(mins / 60);
            const m = mins % 60;
            return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
                maximumFractionDigits: 0,
            }).format(Number(amount || 0));
        },

        onRetailOrderCompleted(orderData) {
            this.showQuickRetailModal = false;
            this.notice = `Đã ghi nhận bán dịch vụ ${this.formatCurrency(orderData.totalAmount)} (${orderData.paymentMethod === "cash" ? "Tiền mặt" : "VietQR"})!`;
            playSuccessChime();
        },

        async onQrCheckedIn(booking) {
            this.showQrScannerModal = false;
            this.notice = `Đã Check-in vé #${booking.booking_code} vào sân thành công!`;
            playSuccessChime();
            await this.loadBookings();
        },

        async copyText(text) {
            if (!text) return;
            try {
                await navigator.clipboard.writeText(text);
                this.notice = "Đã copy nội dung chuyển khoản!";
            } catch {
                this.error = "Không thể sao chép.";
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

/* 1. TOP OPERATIONAL TOOLBAR */
.pos-top-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.pos-toolbar-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.pos-date-nav-wrap {
    display: flex;
    align-items: center;
    gap: 2px;
    background: #f1f5f9;
    border: none;
    padding: 3px;
    border-radius: 8px;
    height: 36px;
    box-sizing: border-box;
}

.btn-date-nav-arrow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 30px;
    border: none;
    background: transparent;
    color: #475569;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.12s ease;
}

.btn-date-nav-arrow:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.btn-date-nav-today {
    border: none;
    background: transparent;
    padding: 0 12px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.12s ease;
}

.btn-date-nav-today:hover {
    color: #0f172a;
}

.btn-date-nav-today.active {
    background: #166534;
    color: #ffffff;
    box-shadow: 0 1px 2px rgba(22, 101, 52, 0.15);
}

/* CALENDAR POPOVER TRIGGER */
.pos-cal-dropdown {
    position: relative;
}

.pos-cal-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f8fafc;
    border: none;
    padding: 0 14px;
    height: 36px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #0f172a;
    cursor: pointer;
    box-sizing: border-box;
    transition: all 0.15s ease;
}

.pos-cal-btn:hover,
.pos-cal-btn.active {
    background: #f1f5f9;
    color: #166534;
}

.pos-cal-popover {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    z-index: 1000;
    background: transparent;
    border: none;
    box-shadow: none;
    padding: 0;
}

/* CUSTOM PERIOD DROPDOWN */
.pos-period-wrapper {
    position: relative;
}

.pos-period-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f8fafc;
    border: none;
    padding: 0 14px;
    height: 36px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    color: #0f172a;
    cursor: pointer;
    box-sizing: border-box;
    transition: all 0.15s ease;
}

.pos-period-btn:hover,
.pos-period-btn.active {
    background: #f1f5f9;
    color: #166534;
}

.pos-period-arrow {
    color: #64748b;
    transition: transform 0.2s ease;
}

.pos-period-arrow.is-open {
    transform: rotate(180deg);
    color: #166534;
}

.pos-period-popover {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    width: 230px;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
    padding: 6px;
    z-index: 100;
    display: flex;
    flex-direction: column;
    gap: 3px;
    border: 1px solid #cbd5e1;
    animation: popover-drop 0.15s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes popover-drop {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.pos-period-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 10px;
    border-radius: 6px;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: all 0.12s ease;
}

.pos-period-option:hover {
    background: #f1f5f9;
}

.pos-period-option.active {
    background: #f4f8f5;
    color: #166534;
}

.period-opt-info {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.period-opt-info strong {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}

.pos-period-option.active .period-opt-info strong {
    color: #166534;
}

.period-opt-info small {
    font-size: 11.5px;
    color: #475569;
    font-weight: 500;
}

.period-opt-check {
    color: #166534;
    flex-shrink: 0;
}

/* CUSTOM COURT SELECT CARDS GRID */
.pos-court-select-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
    max-height: 200px;
    overflow-y: auto;
    padding-right: 2px;
}

.pos-court-select-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 12px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    cursor: pointer;
    text-align: left;
    transition: all 0.12s ease;
}

.pos-court-select-card:hover {
    background: #f8fafc;
    border-color: #94a3b8;
}

.pos-court-select-card.active {
    background: #f4f8f5;
    border-color: #166534;
    color: #166534;
}

.court-opt-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.court-opt-text strong {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}

.pos-court-select-card.active .court-opt-text strong {
    color: #166534;
}

.court-opt-text span {
    font-size: 11.5px;
    color: #475569;
    font-weight: 500;
}

.court-opt-check {
    color: #166534;
    flex-shrink: 0;
}

/* RIGHT TOOLBAR */
.pos-toolbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.pos-toolbar-actions-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.pos-search-box {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0 12px;
    height: 36px;
    box-sizing: border-box;
    width: 240px;
    transition: all 0.15s ease;
}

.pos-search-box:focus-within {
    background: #ffffff;
    border-color: #166534;
    box-shadow: 0 0 0 3px rgba(22, 101, 52, 0.1);
}

.pos-search-icon {
    color: #64748b;
    flex-shrink: 0;
}

.pos-search-box input {
    border: none;
    background: transparent;
    outline: none;
    font-size: 13px;
    font-weight: 500;
    height: 100%;
    width: 100%;
    color: #0f172a;
}

.pos-search-box input::placeholder {
    color: #64748b;
    font-size: 12.5px;
}

.pos-search-clear {
    border: none;
    background: transparent;
    color: #475569;
    cursor: pointer;
    font-size: 16px;
    padding: 0;
    line-height: 1;
}

/* VIEW MODE SWITCHER */
.pos-view-switcher {
    display: inline-flex;
    align-items: center;
    background: #f1f5f9;
    padding: 3px;
    border-radius: 8px;
    border: none;
    height: 36px;
    box-sizing: border-box;
    flex-shrink: 0;
}

.pos-view-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0 10px;
    height: 30px;
    font-size: 12.5px;
    font-weight: 500;
    color: #475569;
    background: transparent;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: all 0.12s ease;
}

.pos-view-btn:hover {
    color: #0f172a;
}

.pos-view-btn.active {
    background: #ffffff;
    color: #166534;
    font-weight: 600;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

/* FAST ACTION BUTTONS */
.pos-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 14px;
    height: 36px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    box-sizing: border-box;
    transition: all 0.12s ease;
    white-space: nowrap;
}

.pos-btn-action.is-qr {
    background: #166534;
    color: #ffffff;
}

.pos-btn-action.is-qr:hover {
    background: #14532d;
}

.pos-btn-icon-refresh {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: #f8fafc;
    color: #166534;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    box-sizing: border-box;
    transition: all 0.12s ease;
    flex-shrink: 0;
}

.pos-btn-icon-refresh:hover {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #14532d;
}

/* 2. STREAMLINED CAPACITY & METRICS BAR (STYLE 4 - NO BOXES, NO DOTS) */
.pos-capacity-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    background: transparent;
    border: none;
    padding: 0 2px 10px 2px;
    margin-bottom: 2px;
    width: 100%;
    box-sizing: border-box;
}

.pos-capacity-meter-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 180px;
}

.pos-cap-header {
    display: flex;
    align-items: baseline;
    gap: 6px;
    white-space: nowrap;
}

.pos-cap-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #64748b;
}

.pos-cap-val {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
}

.pos-cap-sub {
    font-size: 11px;
    font-weight: 500;
    color: #94a3b8;
}

.pos-cap-track {
    width: 100%;
    height: 6px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.pos-cap-fill {
    height: 100%;
    background: linear-gradient(90deg, #8da89b, #5c7e6e);
    border-radius: 999px;
    transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.pos-bar-sep {
    width: 1px;
    height: 24px;
    background: #e2e8f0;
    flex-shrink: 0;
}

.pos-metrics-cluster {
    display: flex;
    align-items: center;
    gap: 18px;
    flex: 1;
    min-width: 0;
    overflow-x: auto;
    scrollbar-width: none;
}

.pos-metrics-cluster::-webkit-scrollbar {
    display: none;
}

.pos-metric-cell {
    display: flex;
    flex-direction: column;
    gap: 1px;
    white-space: nowrap;
    flex-shrink: 0;
}

.pos-metric-title {
    font-size: 10.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: #64748b;
}

.pos-metric-num {
    font-size: 13.5px;
    font-weight: 700;
    color: #0f172a;
}

.pos-metric-num.is-blue {
    color: #0284c7;
}
.pos-metric-num.is-amber {
    color: #d97706;
}
.pos-metric-num.is-red {
    color: #dc2626;
}
.pos-metric-num.is-green {
    color: #5c7e6e;
}

.pos-btn-analytics {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ffffff;
    border: 1.5px solid #cbd5e1;
    padding: 6px 14px;
    height: 34px;
    border-radius: 999px;
    font-size: 12.5px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: all 0.15s ease;
    white-space: nowrap;
    flex-shrink: 0;
}

.pos-btn-analytics:hover {
    background: #f8fafc;
    border-color: #54656f;
    color: #0f172a;
}

/* FLASH ALERTS */
.pos-alert {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 600;
}

.pos-alert.is-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #f87171;
}

.pos-alert.is-success {
    background: #f0fdf4;
    color: #065f46;
    border: 1px solid #86efac;
}

.pos-alert button {
    border: none;
    background: transparent;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    color: inherit;
}

/* 3. OPERATIONAL VIEW CONTAINER */
.pos-matrix-container {
    flex: 1;
    background: transparent;
    border: none;
    overflow: visible;
}

.pos-state-box {
    padding: 60px 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    color: #334155;
    text-align: center;
    font-size: 13.5px;
    font-weight: 500;
}

.pos-3d-empty-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 4px;
}

.pos-3d-empty-info h4 {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.pos-3d-empty-info p {
    font-size: 13.5px;
    color: #334155;
    margin: 0;
    max-width: 420px;
}

.pos-btn-retry {
    margin-top: 8px;
    background: #087642;
    color: #ffffff;
    border: none;
    padding: 8px 18px;
    border-radius: 6px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
}

.pos-btn-retry:hover {
    background: #065f35;
}

.pos-spinner {
    width: 28px;
    height: 28px;
    border: 2px solid #cbd5e1;
    border-top-color: #087642;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.pos-grid-scroller {
    overflow-x: auto;
    overflow-y: hidden;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    -webkit-overflow-scrolling: touch;
    touch-action: pan-x pan-y;
    overscroll-behavior-x: contain;
    transform: translateZ(0);
    will-change: scroll-position;
}

.pos-table {
    width: max-content;
    min-width: 100%;
    border-collapse: separate;
    border-spacing: 2px;
    table-layout: fixed;
}

.pos-th-time {
    width: 110px;
    min-width: 110px;
    max-width: 110px;
    background: #f8fafc;
    border-bottom: 2px solid #087642;
    padding: 12px 8px;
    font-size: 12.5px;
    font-weight: 700;
    color: #0f172a;
    text-align: center;
    position: sticky;
    left: 0;
    z-index: 20;
    white-space: nowrap;
    transform: translateZ(0);
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.03);
}

.pos-th-court {
    background: #f8fafc;
    border-bottom: 2px solid #087642;
    padding: 12px 14px;
    text-align: left;
    width: 180px;
    min-width: 180px;
    max-width: 180px;
}

.pos-court-header {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.pos-court-name {
    font-size: 14.5px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
}

.pos-court-type {
    font-size: 12px;
    color: #334155;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

/* ROW & CELL */
.pos-td-time {
    background: #f8fafc;
    border-right: 1px solid #cbd5e1;
    padding: 8px 4px;
    text-align: center;
    position: sticky;
    left: 0;
    z-index: 10;
    width: 110px;
    min-width: 110px;
    max-width: 110px;
    transform: translateZ(0);
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.03);
}

.pos-time-mark {
    font-family: ui-monospace, SFMono-Regular, monospace;
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
}

.pos-td-time.is-past-time .pos-time-mark {
    color: #94a3b8;
    font-weight: 500;
}

.pos-td-cell {
    height: 60px;
    padding: 1px;
    vertical-align: stretch;
    cursor: pointer;
    width: 180px;
    min-width: 180px;
    max-width: 180px;
}

.pos-td-cell.is-highlight {
    outline: 2px solid #087642;
}

/* EMPTY SLOT */
.pos-empty-slot {
    width: 100%;
    height: 100%;
    border-radius: 5px;
    background: #ffffff;
    border: 1px dashed #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.12s ease;
    min-height: 54px;
}

.pos-empty-slot.is-past {
    background-color: #f8fafc;
    background-image: repeating-linear-gradient(
        -45deg,
        transparent,
        transparent 6px,
        rgba(226, 232, 240, 0.45) 6px,
        rgba(226, 232, 240, 0.45) 12px
    );
    border: 1px solid #edf2f7;
    cursor: not-allowed;
}

.pos-empty-past-hover-hint {
    font-size: 11px;
    font-weight: 600;
    color: #94a3b8;
    opacity: 0;
    transition: opacity 0.15s ease;
}

.pos-empty-hint {
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    opacity: 0;
    transition: opacity 0.12s ease;
}

/* BOOKING CARDS */
.pos-booking-card {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 54px;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    display: flex;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition: all 0.12s ease;
}

.pos-booking-status-stripe {
    width: 5px;
    flex-shrink: 0;
    background: #64748b;
}

.pos-booking-card.is-playing-block {
    background: #f0fdf4;
    border-color: #86efac;
}
.pos-booking-card.is-playing-block .pos-booking-status-stripe {
    background: #059669;
}

.pos-booking-card.is-confirmed-block {
    background: #eff6ff;
    border-color: #93c5fd;
}
.pos-booking-card.is-confirmed-block .pos-booking-status-stripe {
    background: #2563eb;
}

.pos-booking-card.is-pending-block {
    background: #fefce8;
    border-color: #fde047;
}
.pos-booking-card.is-pending-block .pos-booking-status-stripe {
    background: #d97706;
}

.pos-booking-card.is-overtime {
    background: #fef2f2;
    border-color: #fca5a5;
}
.pos-booking-card.is-overtime .pos-booking-status-stripe {
    background: #dc2626;
}

.pos-booking-card.is-locked-block {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.pos-booking-card.is-locked-block .pos-booking-status-stripe {
    background: #475569;
}

@media (hover: hover) {
    .pos-td-cell:hover .pos-empty-slot:not(.is-past) {
        background: #f0fdf4;
        border-color: #087642;
    }

    .pos-td-cell:hover .pos-empty-slot:not(.is-past) .pos-empty-hint {
        opacity: 1;
        color: #087642;
    }

    .pos-td-cell:hover .pos-empty-slot.is-past {
        background-color: #f1f5f9;
    }

    .pos-td-cell:hover .pos-empty-slot.is-past .pos-empty-past-hover-hint {
        opacity: 0.85;
    }

    .pos-booking-card:hover {
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        transform: scale(1.01);
        z-index: 5;
    }
}

.pos-booking-card.is-selected {
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
    transform: scale(1.01);
    z-index: 5;
}

.pos-booking-body {
    flex: 1;
    padding: 6px 8px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
    gap: 3px;
    min-width: 0;
}

.pos-booking-row-1 {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    min-width: 0;
}

.pos-booking-customer {
    font-size: 12.5px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
}

.pos-booking-status-badge {
    font-size: 10px;
    font-weight: 600;
    padding: 1.5px 5px;
    border-radius: 4px;
    white-space: nowrap;
    flex-shrink: 0;
    line-height: 1.2;
}

.pos-booking-status-badge.playing {
    background: #dcfce7;
    color: #15803d;
}
.pos-booking-status-badge.confirmed {
    background: #dbeafe;
    color: #1d4ed8;
}
.pos-booking-status-badge.unpaid {
    background: #fee2e2;
    color: #b91c1c;
}
.pos-booking-status-badge.ending-soon {
    background: #fef3c7;
    color: #b45309;
}
.pos-booking-status-badge.overtime {
    background: #fee2e2;
    color: #b91c1c;
    font-weight: 700;
}

.pos-booking-row-2 {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    min-width: 0;
}

.pos-booking-time {
    font-size: 11px;
    font-weight: 600;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
    flex-shrink: 0;
}

.pos-booking-phone {
    font-size: 11px;
    font-weight: 500;
    color: #475569;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 80px;
}

/* 4. MODAL & OFF-CANVAS DRAWER */
.pos-drawer-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
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
    box-shadow: -8px 0 25px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    animation: slide-left 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    border-left: 1px solid #cbd5e1;
}

@keyframes slide-left {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(0);
    }
}

.pos-drawer-head {
    padding: 18px 20px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    border-bottom: 1px solid #e2e8f0;
}

.pos-drawer-kicker {
    font-size: 11px;
    font-weight: 700;
    color: #087642;
    letter-spacing: 0.5px;
}

.pos-drawer-title {
    font-size: 17px;
    font-weight: 700;
    color: #0f172a;
    margin: 3px 0 2px;
}

.pos-drawer-subtitle {
    font-size: 13px;
    font-weight: 500;
    color: #334155;
    margin: 0;
}

.pos-drawer-close-btn {
    background: transparent;
    border: none;
    font-size: 20px;
    font-weight: 700;
    color: #475569;
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

.pos-drawer-error-banner {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #b91c1c;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.4;
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
    font-size: 13.5px;
}

.pos-info-label {
    color: #334155;
    font-weight: 500;
}

.pos-info-val {
    font-weight: 600;
    color: #0f172a;
}

.pos-info-val.is-money {
    font-size: 14.5px;
    font-weight: 700;
}

.pos-info-val.is-paid {
    color: #087642;
}

.pos-info-val.is-outstanding {
    color: #dc2626;
    font-size: 14.5px;
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
    font-size: 13.5px;
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
    border: 1px solid #cbd5e1;
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
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}

.pos-field-label .req {
    color: #dc2626;
}

.pos-input {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 9px 12px;
    font-size: 13.5px;
    font-weight: 500;
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
    border: 1px solid #cbd5e1;
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
    font-size: 12.5px;
    font-weight: 600;
}

.pos-mode-card small {
    font-size: 10.5px;
    color: inherit;
    opacity: 0.9;
}

/* QR CONTAINER */
.pos-qr-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
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
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.pos-qr-status {
    font-size: 12px;
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
    font-weight: 500;
    padding: 10px 16px;
    cursor: pointer;
}

.pos-btn-primary {
    background: #166534;
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
    background: #14532d;
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
    border-radius: 10px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.pos-modal-head {
    padding: 16px 20px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    border-bottom: 1px solid #f1f5f9;
}

.pos-modal-head h3 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 2px;
    color: #0f172a;
}

.pos-modal-head p {
    font-size: 13px;
    color: #475569;
    font-weight: 400;
    margin: 0;
}

.pos-modal-body {
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.pos-collect-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    padding: 12px;
    border-radius: 8px;
}

.pos-cstat {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.pos-cstat span {
    font-size: 12px;
    color: #475569;
    font-weight: 400;
}

.pos-cstat strong {
    font-size: 14.5px;
    font-weight: 600;
    color: #0f172a;
}

.pos-cstat.is-due strong {
    color: #dc2626;
}

.pos-modal-foot {
    padding: 14px 20px;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
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
    font-weight: 500;
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

/* 5. ANALYTICS MODAL DIALOG */
.analytics-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(2px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}

.analytics-modal-dialog {
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.2);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #cbd5e1;
    animation: pop-in 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    box-sizing: border-box;
}

@keyframes pop-in {
    from {
        opacity: 0;
        transform: scale(0.97);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.analytics-modal-head {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    flex-shrink: 0;
}

.head-titles h3 {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.head-subtitle {
    font-size: 12.5px;
    color: #64748b;
    font-weight: 500;
    margin-top: 2px;
    display: block;
}

.analytics-close-btn {
    background: transparent;
    border: none;
    font-size: 18px;
    color: #64748b;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.analytics-close-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.analytics-modal-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.analytics-top-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.analytic-card {
    display: flex;
    align-items: center;
    gap: 16px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 18px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    box-sizing: border-box;
}

.analytic-card.is-revenue-card {
    flex-direction: column;
    align-items: stretch;
    justify-content: space-between;
    gap: 12px;
}

.analytic-card.is-heatmap {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
}

.analytic-card-label {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    letter-spacing: 0.1px;
}

.gauge-ring-wrap {
    position: relative;
    width: 58px;
    height: 58px;
    min-width: 58px;
    flex-shrink: 0;
}

.gauge-svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.gauge-bg {
    fill: none;
    stroke: #e2e8f0;
    stroke-width: 3.5;
}

.gauge-fill {
    fill: none;
    stroke: #166534;
    stroke-width: 3.5;
    stroke-linecap: round;
    transition: stroke-dasharray 0.3s ease;
}

.gauge-num {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
}

.gauge-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.gauge-val-highlight {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
}

.gauge-desc {
    font-size: 12px;
    color: #64748b;
    font-weight: 400;
}

.heat-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}

.heat-legend {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11.5px;
    color: #64748b;
    font-weight: 500;
    white-space: nowrap;
}

.legend-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.legend-dot.is-peak {
    background: #e11d48;
}
.legend-dot.is-high {
    background: #f59e0b;
}
.legend-dot.is-medium {
    background: #10b981;
}
.legend-dot.is-low {
    background: #94a3b8;
}

.heat-bars-grid {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    height: 76px;
    padding-top: 6px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
}

.heat-bar-col {
    flex: 1;
    min-width: 22px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    height: 100%;
    flex-shrink: 0;
}

.heat-bar-track {
    flex: 1;
    width: 100%;
    max-width: 22px;
    background: #f1f5f9;
    border-radius: 4px;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.heat-bar-fill {
    width: 100%;
    border-radius: 3px;
    transition: height 0.2s ease;
}

.heat-bar-fill.is-peak {
    background: #e11d48;
}
.heat-bar-fill.is-high {
    background: #f59e0b;
}
.heat-bar-fill.is-medium {
    background: #10b981;
}
.heat-bar-fill.is-low {
    background: #cbd5e1;
}

.heat-hour-label {
    font-size: 11px;
    font-weight: 500;
    color: #64748b;
    white-space: nowrap;
}

.cash-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 8px;
}

.cash-total {
    font-size: 17px;
    font-weight: 700;
    color: #166534;
    white-space: nowrap;
}

.cash-segmented-bar {
    display: flex;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
    background: #e2e8f0;
    width: 100%;
}

.seg-fill {
    height: 100%;
    transition: width 0.2s ease;
}
.seg-fill.is-qr {
    background: #166534;
}
.seg-fill.is-cash {
    background: #0284c7;
}

.cash-metrics-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 10px;
    flex-wrap: wrap;
}

.cash-sub-metric {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    white-space: nowrap;
}

.sub-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}
.sub-dot.is-qr {
    background: #166534;
}
.sub-dot.is-cash {
    background: #0284c7;
}

.sub-name {
    color: #64748b;
    font-weight: 500;
}
.sub-val {
    color: #0f172a;
    font-weight: 700;
}

.pos-tr-row.is-jump-highlight td {
    background: #ecfdf5 !important;
    transition: background 0.3s ease;
}

/* 7. LIST VIEW (DANH SÁCH) */
.pos-list-container {
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    overflow-x: auto;
}

.pos-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
    text-align: left;
}

.pos-data-table th {
    background: #f1f5f9;
    color: #0f172a;
    font-size: 12px;
    font-weight: 700;
    padding: 12px 14px;
    border-bottom: 2px solid #cbd5e1;
    white-space: nowrap;
}

.pos-data-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #e2e8f0;
    color: #0f172a;
    font-weight: 500;
    white-space: nowrap;
}

.pos-data-table .tr-clickable {
    cursor: pointer;
    transition: background 0.1s ease;
}

.pos-data-table .tr-clickable:hover {
    background: #f8fafc;
}

.mono-code {
    font-family: ui-monospace, SFMono-Regular, monospace;
    font-size: 12.5px;
    color: #087642;
    font-weight: 700;
}

.list-status-pill {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11.5px;
    font-weight: 700;
    background: #f1f5f9;
    color: #334155;
}

.list-status-pill.checked_in {
    background: #dcfce7;
    color: #15803d;
}
.list-status-pill.confirmed {
    background: #dbeafe;
    color: #1d4ed8;
}
.list-status-pill.pending_payment {
    background: #fef3c7;
    color: #b45309;
}
.list-status-pill.cancelled {
    background: #fee2e2;
    color: #b91c1c;
}

.td-actions {
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-table-action {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #0f172a;
    font-size: 12px;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.12s ease;
}

.btn-table-action:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

.btn-table-action.is-checkin {
    background: #087642;
    color: #ffffff;
    border-color: #087642;
}

.btn-table-action.is-checkin:hover {
    background: #065f35;
}

.btn-table-action.is-danger {
  background: #fff1f2;
  color: #be123c;
  border-color: #fecdd3;
}

.btn-table-action.is-danger:hover {
  background: #ffe4e6;
  border-color: #fda4af;
}

.btn-table-action.is-collect {
    background: #d97706;
    color: #ffffff;
    border-color: #d97706;
}

.btn-table-action.is-collect:hover {
    background: #b45309;
}

.td-empty-list {
    text-align: center;
    color: #475569;
    font-weight: 500;
    padding: 40px !important;
}

/* ========================================================= */
/* COMPREHENSIVE RESPONSIVE BREAKPOINTS                      */
/* ========================================================= */
@media (max-width: 1024px) {
    .pos-workspace {
        padding: 14px 16px 72px 16px;
        gap: 14px;
    }
    .pos-top-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    .pos-toolbar-left {
        width: 100%;
        justify-content: space-between;
    }
    .pos-toolbar-right {
        width: 100%;
    }
    .pos-search-box {
        width: 100%;
        max-width: 100%;
    }
    .pos-toolbar-actions-row {
        width: 100%;
        justify-content: space-between;
    }
}

@media (max-width: 768px) {
    .pos-workspace {
        padding: 10px 12px 70px 12px;
        gap: 12px;
    }
    .pos-toolbar-left {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        width: 100%;
    }
    .pos-date-nav-wrap {
        flex: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 2px;
    }
    .btn-date-nav-today {
        padding: 0 10px;
        font-size: 12px;
        white-space: nowrap;
    }
    .pos-cal-btn {
        flex: 1;
        min-width: 130px;
        padding: 0 10px;
        font-size: 12px;
        justify-content: center;
        white-space: nowrap;
    }
    .pos-period-btn {
        flex: none;
        padding: 0 10px;
        font-size: 12px;
        justify-content: center;
        white-space: nowrap;
    }
    .pos-toolbar-actions-row {
        display: flex;
        align-items: center;
        gap: 6px;
        width: 100%;
    }
    .pos-view-switcher {
        flex-shrink: 0;
    }
    .pos-view-btn {
        padding: 0 8px;
        font-size: 12px;
        white-space: nowrap;
        justify-content: center;
    }
    .pos-btn-action.is-qr {
        flex: 1;
        padding: 0 8px;
        font-size: 12px;
        white-space: nowrap;
        justify-content: center;
    }
    .pos-btn-icon-refresh {
        width: 36px;
        height: 36px;
        flex-shrink: 0;
    }
    .pos-capacity-bar {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        padding: 0 0 6px 0;
    }
    .pos-bar-sep {
        display: none;
    }
    .pos-metrics-cluster {
        width: 100%;
        gap: 14px;
        padding: 2px 0;
    }
    .pos-btn-analytics {
        width: 100%;
        justify-content: center;
        padding: 8px;
        height: 36px;
    }
    .pos-th-time {
        width: 86px;
        min-width: 86px;
        max-width: 86px;
        padding: 8px 2px;
        font-size: 11px;
        white-space: nowrap;
    }
    .pos-td-time {
        width: 86px;
        min-width: 86px;
        max-width: 86px;
        padding: 6px 2px;
    }
    .pos-time-mark {
        font-family: ui-monospace, SFMono-Regular, monospace;
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
        display: block;
        text-align: center;
        letter-spacing: -0.3px;
    }
    .pos-th-court,
    .pos-td-cell {
        width: 175px;
        min-width: 175px;
        max-width: 175px;
    }
    .pos-th-court {
        padding: 8px 10px;
    }
    .pos-court-name {
        font-size: 13.5px;
    }
    .pos-court-type {
        font-size: 11px;
        max-width: 165px;
    }
}

@media (max-width: 480px) {
    .pos-toolbar-left {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        gap: 6px;
        width: 100%;
    }
    .pos-date-nav-wrap {
        flex: 1;
        min-width: 140px;
    }
    .pos-cal-btn {
        width: 100%;
    }
    .pos-period-btn {
        flex: 1;
        min-width: 120px;
    }
    .pos-toolbar-actions-row {
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        overflow-x: auto;
        scrollbar-width: none;
        gap: 6px;
        width: 100%;
    }
    .pos-toolbar-actions-row::-webkit-scrollbar {
        display: none;
    }
    .pos-view-switcher {
        flex-shrink: 0;
    }
    .pos-btn-action.is-qr {
        flex-shrink: 0;
        padding: 0 10px;
    }
    .pos-btn-icon-refresh {
        flex-shrink: 0;
    }
}

@media (max-width: 768px) {
    .analytics-modal-backdrop {
        padding: 10px;
    }
    .analytics-modal-dialog {
        max-height: 88vh;
        border-radius: 12px;
    }
    .analytics-modal-head {
        padding: 12px 14px;
    }
    .head-titles h3 {
        font-size: 15px;
    }
    .analytics-modal-body {
        padding: 12px;
        gap: 12px;
    }
    .analytics-top-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .analytic-card {
        padding: 14px;
        gap: 12px;
    }
    .heat-card-head {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    .heat-legend {
        gap: 8px;
    }
    .pos-cal-popover {
        left: auto;
        right: 0;
        max-width: calc(100vw - 16px);
    }
    .pos-period-popover {
        left: auto;
        right: 0;
        max-width: calc(100vw - 16px);
    }
    .pos-court-popover {
        left: auto;
        right: 0;
        max-width: calc(100vw - 16px);
    }
}
</style>

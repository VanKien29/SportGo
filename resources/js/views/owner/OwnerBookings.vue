<template>
  <div class="bookings-page">
    <!-- ── Floating Add Button ─────────────────────────── -->
    <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
      <router-link class="btn-float-add" to="/owner/counter-booking" title="Tạo booking tại quầy">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Tạo booking</span>
      </router-link>
    </div>

    <!-- ── Alerts ─────────────────────────────────────── -->
    <div v-if="error" class="alert alert--error">{{ error }}</div>
    <div v-if="notice" class="alert alert--success">{{ notice }}</div>

    <!-- ── Main 3-column layout ───────────────────────── -->
    <div class="bk-layout">

      <!-- LEFT: Calendar + Filters -->
      <aside class="bk-sidebar">
        <div class="bk-sidebar__cal-card">
          <MiniCalendar
            mode="single"
            :model-value="filters.booking_date"
            :dots="calendarDots"
            @update:model-value="onCalendarDatePick"
          />
        </div>

        <!-- Filters -->
        <div class="bk-filter-card">
          <p class="bk-filter-card__label">Bộ lọc</p>
          <label class="bk-field">
            <span>Cụm sân</span>
            <select v-model="filters.venue_cluster_id" @change="onClusterChange">
              <option value="">Tất cả</option>
              <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
            </select>
          </label>
          <label class="bk-field">
            <span>Sân con</span>
            <select v-model="filters.venue_court_id" @change="loadBookings">
              <option value="">Tất cả</option>
              <option v-for="court in courts" :key="court.id" :value="court.id">{{ court.name }}</option>
            </select>
          </label>
          <label class="bk-field">
            <span>Trạng thái</span>
            <select v-model="filters.status" @change="loadBookings">
              <option value="">Tất cả</option>
              <option value="pending_approval">Chờ duyệt</option>
              <option value="pending_payment">Chờ thanh toán</option>
              <option value="confirmed">Đã xác nhận</option>
              <option value="checked_in">Đã check-in</option>
              <option value="completed">Hoàn thành</option>
              <option value="cancelled">Đã hủy</option>
              <option value="rejected">Từ chối</option>
            </select>
          </label>
          <button class="bk-reload-btn" type="button" @click="loadBookings">
            <AppIcon name="refresh" size="15" />
            <span>Tải lại</span>
          </button>
        </div>
      </aside>

      <!-- CENTER: Timeline -->
      <main class="bk-main">
        <!-- Header: ngày + metrics + legend -->
        <div class="bk-timeline-header">
          <div class="bk-timeline-header__top">
            <div>
              <h2 class="bk-timeline-header__title">Lịch sân trong ngày</h2>
              <p class="bk-timeline-header__sub">{{ scheduleSubtitle }}</p>
            </div>
            <div class="bk-legend">
              <span><i class="bk-legend__dot bk-legend__dot--confirmed"></i>Đã xác nhận</span>
              <span><i class="bk-legend__dot bk-legend__dot--pending"></i>Chờ xử lý</span>
              <span><i class="bk-legend__dot bk-legend__dot--playing"></i>Đang chơi</span>
              <span><i class="bk-legend__dot bk-legend__dot--lock"></i>Khóa sân</span>
            </div>
          </div>

          <!-- Period tabs + metrics -->
          <div class="bk-period-row">
            <div class="bk-period-tabs">
              <button
                v-for="period in timePeriods"
                :key="period.key"
                type="button"
                class="bk-period-tab"
                :class="{ 'bk-period-tab--active': activeTimePeriod === period.key }"
                @click="activeTimePeriod = period.key"
              >
                <strong>{{ period.label }}</strong>
                <span>{{ period.range }}</span>
              </button>
            </div>
            <div class="bk-metrics">
              <div v-for="metric in scheduleMetrics" :key="metric.label" class="bk-metric">
                <span>{{ metric.label }}</span>
                <strong>{{ metric.value }}</strong>
              </div>
            </div>
          </div>
        </div>

        <!-- Timeline body -->
        <div v-if="loading || scheduleLoading" class="bk-state">Đang tải lịch sân...</div>
        <div v-else-if="scheduleError" class="bk-state bk-state--error">{{ scheduleError }}</div>
        <div v-else-if="!timelineRows.length" class="bk-state">Chưa có sân phù hợp với bộ lọc hiện tại.</div>
        <div v-else class="bk-timeline-board">
          <div class="bk-timeline-scroller">
            <!-- Axis -->
            <div class="bk-timeline-axis" :style="{ minWidth: timelineMinWidth }">
              <div class="bk-axis-court">Sân / giờ</div>
              <div class="bk-axis-track">
                <span
                  v-for="tick in timelineTicks"
                  :key="tick.value"
                  class="bk-axis-tick"
                  :style="{ left: `${tick.left}%` }"
                >{{ tick.label }}</span>
              </div>
            </div>

            <!-- Rows -->
            <article
              v-for="row in timelineRows"
              :key="row.court.id"
              class="bk-timeline-row"
              :style="{ minWidth: timelineMinWidth }"
            >
              <div class="bk-court-meta">
                <strong>{{ row.court.name }}</strong>
                <span>{{ courtOptionLabel(row.court) }}</span>
              </div>
              <div class="bk-timeline-track">
                <span
                  v-for="tick in timelineTicks"
                  :key="`${row.court.id}-${tick.value}`"
                  class="bk-track-gridline"
                  :style="{ left: `${tick.left}%` }"
                ></span>
                <span v-if="!row.blocks.length" class="bk-empty-track">Trống trong khoảng này</span>
                <button
                  v-for="block in row.blocks"
                  :key="block.key"
                  type="button"
                  class="bk-block"
                  :class="[block.kindClass, { 'bk-block--active': selectedTimelineItem?.key === block.key, 'bk-block--compact': block.compact }]"
                  :style="block.style"
                  :title="block.titleText"
                  @click="selectTimelineItem(block)"
                >
                  <span class="bk-block__time">{{ block.timeLabel }}</span>
                  <strong>{{ block.title }}</strong>
                  <small>{{ block.subtitle }}</small>
                </button>
              </div>
            </article>
          </div>
        </div>
      </main>

      <!-- RIGHT: Slide-in detail panel -->
      <aside class="bk-detail" :class="{ 'bk-detail--open': selectedTimelineItem }">
        <template v-if="selectedTimelineItem">
          <div class="bk-detail__header">
            <div>
              <p class="bk-detail__eyebrow">{{ selectedTimelineItem.type === 'booking' ? 'BOOKING' : 'KHÓA LỊCH' }}</p>
              <h3 class="bk-detail__title">{{ selectedTimelineItem.title }}</h3>
              <p class="bk-detail__sub">{{ selectedTimelineItem.timeLabel }} · {{ selectedTimelineItem.courtName }}</p>
            </div>
            <button type="button" class="bk-detail__close" @click="selectedTimelineItem = null" aria-label="Đóng">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div v-if="selectedTimelineBooking" class="bk-detail__chips">
            <span class="bk-chip" :class="selectedTimelineBooking.status">{{ statusLabel(selectedTimelineBooking.status) }}</span>
            <span class="bk-chip bk-chip--payment" :class="paymentState(selectedTimelineBooking)">{{ paymentStateLabel(selectedTimelineBooking) }}</span>
          </div>

          <dl class="bk-detail__list">
            <div v-for="row in selectedTimelineRows" :key="row.label">
              <dt>{{ row.label }}</dt>
              <dd>{{ row.value }}</dd>
            </div>
          </dl>

          <div v-if="selectedTimelineBooking" class="bk-detail__actions">
            <ActionIconButton
              v-if="primaryAction(selectedTimelineBooking)"
              :icon="primaryAction(selectedTimelineBooking).icon"
              :label="primaryAction(selectedTimelineBooking).label"
              :variant="primaryAction(selectedTimelineBooking).variant"
              @click="runBookingAction(selectedTimelineBooking, primaryAction(selectedTimelineBooking).key)"
            />
            <button
              v-for="action in secondaryActions(selectedTimelineBooking)"
              :key="action.key"
              type="button"
              class="bk-action-btn"
              :class="{ 'bk-action-btn--danger': action.variant === 'danger' }"
              @click="runBookingAction(selectedTimelineBooking, action.key)"
            >
              <AppIcon :name="action.icon" size="15" />
              <span>{{ action.label }}</span>
            </button>
          </div>
        </template>
        <template v-else>
          <div class="bk-detail__empty">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#c8d8cc" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <p>Chọn một block trên lịch</p>
            <small>Thông tin khách, trạng thái booking và thanh toán sẽ hiện ở đây.</small>
          </div>
        </template>
      </aside>
    </div>

    <!-- ── Teleport: action menu ───────────────────────── -->
    <Teleport to="body">
      <button
        v-if="actionMenu.booking"
        class="action-menu-dismiss"
        type="button"
        aria-label="Đóng menu thao tác"
        @click="closeActionMenu"
      ></button>
      <div
        v-if="actionMenu.booking"
        class="row-action-menu"
        :style="{ top: `${actionMenu.top}px`, left: `${actionMenu.left}px` }"
        role="menu"
      >
        <button
          v-for="action in secondaryActions(actionMenu.booking)"
          :key="action.key"
          type="button"
          :class="{ danger: action.variant === 'danger' }"
          role="menuitem"
          @click="runBookingAction(actionMenu.booking, action.key)"
        >
          <AppIcon :name="action.icon" size="17" />
          <span>{{ action.label }}</span>
        </button>
      </div>
    </Teleport>

    <!-- ── Modal: Đổi sân ─────────────────────────────── -->
    <div v-if="changeCourtBooking" class="bk-modal-backdrop" @click.self="closeChangeCourt">
      <form class="bk-modal" @submit.prevent="saveChangeCourt">
        <header class="bk-modal__header">
          <h2>Đổi sân thực tế</h2>
          <ActionIconButton icon="x" label="Đóng" variant="ghost" @click="closeChangeCourt" />
        </header>
        <label class="bk-field">
          <span>Sân mới</span>
          <select v-model="changeCourtForm.venue_court_id" required>
            <option v-for="court in changeCourtOptions" :key="court.id" :value="court.id">{{ court.name }} · {{ court.court_type?.name }}</option>
          </select>
        </label>
        <label class="bk-field">
          <span>Lý do đổi sân</span>
          <textarea v-model.trim="changeCourtForm.court_changed_reason" rows="4" required></textarea>
        </label>
        <footer class="bk-modal__footer">
          <button type="button" class="bk-btn bk-btn--ghost" @click="closeChangeCourt">Hủy</button>
          <button class="bk-btn bk-btn--primary" type="submit" :disabled="savingChangeCourt">Lưu đổi sân</button>
        </footer>
      </form>
    </div>

    <!-- ── Modal: Thu tiền ────────────────────────────── -->
    <div v-if="collectBooking" class="bk-modal-backdrop" @click.self="closeCollectPayment">
      <form class="bk-modal bk-modal--wide" @submit.prevent="submitCollectPayment">
        <header class="bk-modal__header">
          <div>
            <h2>Thu tiền booking</h2>
            <p>{{ collectBooking.booking_code }} · {{ customerName(collectBooking) }}</p>
          </div>
          <ActionIconButton icon="x" label="Đóng" variant="ghost" @click="closeCollectPayment" />
        </header>

        <dl class="bk-collect-summary">
          <div>
            <dt>Tổng tiền</dt>
            <dd>{{ formatCurrency(collectBooking.total_price) }}</dd>
          </div>
          <div>
            <dt>Đã thu</dt>
            <dd>{{ formatCurrency(paidAmount(collectBooking)) }}</dd>
          </div>
          <div class="bk-collect-summary__highlight">
            <dt>Còn phải thu</dt>
            <dd>{{ formatCurrency(outstandingAmount(collectBooking)) }}</dd>
          </div>
        </dl>

        <label class="bk-field">
          <span>Số tiền thu</span>
          <input
            v-model.number="collectForm.amount"
            type="number"
            min="1000"
            step="1000"
            :disabled="collectForm.payment_method === 'sepay' && !!pendingTransfer(collectBooking)"
          />
        </label>

        <div class="bk-method-row">
          <button type="button" class="bk-method-btn" :class="{ 'bk-method-btn--active': collectForm.payment_method === 'cash' }" @click="collectForm.payment_method = 'cash'">
            <AppIcon name="banknote" size="16" />
            <span>Tiền mặt</span>
          </button>
          <button type="button" class="bk-method-btn" :class="{ 'bk-method-btn--active': collectForm.payment_method === 'sepay' }" @click="collectForm.payment_method = 'sepay'">
            <AppIcon name="creditCard" size="16" />
            <span>Chuyển khoản</span>
          </button>
        </div>

        <div v-if="collectQr" class="bk-collect-qr">
          <img :src="collectQr.qr_url" alt="Mã chuyển khoản" />
          <div class="bk-collect-qr__info">
            <div>
              <span>Nội dung chuyển khoản</span>
              <button type="button" @click="copyText(collectQr.transfer_content)">{{ collectQr.transfer_content }}</button>
            </div>
            <div>
              <span>Số tiền</span>
              <strong>{{ formatCurrency(collectQr.payment?.amount) }}</strong>
            </div>
            <small>Hệ thống sẽ tự cập nhật khi ngân hàng xác nhận thanh toán.</small>
          </div>
        </div>

        <footer class="bk-modal__footer">
          <button type="button" class="bk-btn bk-btn--ghost" @click="closeCollectPayment">Đóng</button>
          <button class="bk-btn bk-btn--primary" type="submit" :disabled="collectingPayment">
            {{ collectSubmitLabel() }}
          </button>
        </footer>
      </form>
    </div>

    <!-- ── Modal: Hủy / Từ chối ───────────────────────── -->
    <div v-if="statusActionBooking" class="bk-modal-backdrop" @click.self="closeStatusAction">
      <form class="bk-modal" @submit.prevent="submitStatusAction">
        <header class="bk-modal__header">
          <div>
            <h2>{{ statusActionTitle() }}</h2>
            <p>{{ statusActionBooking.booking_code }} · {{ customerName(statusActionBooking) }}</p>
          </div>
          <ActionIconButton icon="x" label="Đóng" variant="ghost" @click="closeStatusAction" />
        </header>
        <p class="bk-status-warning">
          {{ statusAction === 'reject'
            ? 'Booking sẽ bị từ chối và giải phóng khung sân.'
            : 'Booking sẽ bị hủy và giao dịch đang chờ, nếu có, sẽ không còn hiệu lực.' }}
        </p>
        <label class="bk-field">
          <span>Lý do {{ statusAction === 'reject' ? 'từ chối' : 'hủy' }}</span>
          <textarea v-model.trim="statusActionReason" rows="4" maxlength="1000" required></textarea>
        </label>
        <footer class="bk-modal__footer">
          <button type="button" class="bk-btn bk-btn--ghost" @click="closeStatusAction">Đóng</button>
          <button class="bk-btn bk-btn--danger" type="submit" :disabled="updatingStatus">
            {{ statusAction === 'reject' ? 'Xác nhận từ chối' : 'Xác nhận hủy' }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script>
import { ownerBookingService } from '../../services/ownerBookings.js';
import { venueClusterService } from '../../services/venueClusters.js';
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import MiniCalendar from '../../components/MiniCalendar.vue';

function localIsoDate(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export default {
  name: 'OwnerBookings',
  components: { ActionIconButton, AppIcon, MiniCalendar },
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
      holdClock: Date.now(),
      holdClockInterval: null,
      actionMenu: {
        booking: null,
        top: 0,
        left: 0,
      },
      statusActionBooking: null,
      statusAction: '',
      statusActionReason: '',
      updatingStatus: false,
      showScrollTop: false,
      timePeriods: [
        { key: 'business', label: 'Cả ngày', start: 360, end: 1320, range: '06:00 - 22:00' },
        { key: 'morning', label: 'Sáng', start: 360, end: 720, range: '06:00 - 12:00' },
        { key: 'afternoon', label: 'Chiều', start: 720, end: 1080, range: '12:00 - 18:00' },
        { key: 'evening', label: 'Tối', start: 1080, end: 1320, range: '18:00 - 22:00' },
      ],
    };
  },
  computed: {
    activePeriod() {
      return this.timePeriods.find((period) => period.key === this.activeTimePeriod) || this.timePeriods[0];
    },
    timelineStart() {
      return this.activePeriod.start;
    },
    timelineEnd() {
      return this.activePeriod.end;
    },
    timelineDuration() {
      return Math.max(this.timelineEnd - this.timelineStart, 30);
    },
    timelineMinWidth() {
      return this.activeTimePeriod === 'business' ? '1180px' : '860px';
    },
    timelineTicks() {
      const ticks = [];
      const step = this.activeTimePeriod === 'business' ? 120 : 60;
      for (let minutes = this.timelineStart; minutes <= this.timelineEnd; minutes += step) {
        ticks.push({
          value: minutes,
          label: this.minutesToTime(minutes),
          left: ((minutes - this.timelineStart) / this.timelineDuration) * 100,
        });
      }
      return ticks;
    },
    scheduleSubtitle() {
      const clusterText = this.filters.venue_cluster_id
        ? this.clusters.find((cluster) => String(cluster.id) === String(this.filters.venue_cluster_id))?.name || 'Cụm sân'
        : 'Tất cả cụm sân';
      return `${clusterText} · ${this.formatDate(this.filters.booking_date)}`;
    },
    scheduleMetrics() {
      const bookingBlocks = this.timelineBlocks.filter((block) => block.type === 'booking');
      const paidCount = bookingBlocks.filter((block) => block.booking && this.paymentState(block.booking) === 'paid').length;
      const pendingPaymentCount = bookingBlocks.filter((block) => block.booking && this.paymentState(block.booking) !== 'paid').length;

      return [
        { label: 'Khung booking', value: bookingBlocks.length },
        { label: 'Đã thanh toán', value: paidCount },
        { label: 'Cần thu/đợi CK', value: pendingPaymentCount },
        { label: 'Khóa sân', value: this.timelineBlocks.filter((block) => block.type === 'lock').length },
      ];
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
        .filter((block) => block.end > this.timelineStart && block.start < this.timelineEnd)
        .sort((a, b) => a.start - b.start || a.end - b.end || a.title.localeCompare(b.title));
    },
    timelineRows() {
      const visibleCourts = this.scheduleCourts.filter((court) => {
        if (!this.filters.venue_court_id) return true;
        return String(court.id) === String(this.filters.venue_court_id);
      });

      return visibleCourts.map((court) => ({
        court,
        blocks: this.timelineBlocks.filter((block) => String(block.courtId) === String(court.id)),
      }));
    },
    selectedTimelineBooking() {
      if (!this.selectedTimelineItem || this.selectedTimelineItem.type !== 'booking') return null;
      return this.bookings.find((booking) => String(booking.id) === String(this.selectedTimelineItem.bookingId)) || this.selectedTimelineItem.booking || null;
    },
    selectedTimelineRows() {
      const item = this.selectedTimelineItem;
      if (!item) return [];

      const booking = this.selectedTimelineBooking;
      if (!booking) {
        return [
          { label: 'Sân', value: item.courtName },
          { label: 'Khung giờ', value: item.timeLabel },
          { label: 'Lý do', value: item.reason || '-' },
        ];
      }

      return [
        { label: 'Khách', value: `${this.customerName(booking)} · ${this.customerPhone(booking)}` },
        { label: 'Mã booking', value: booking.booking_code || '-' },
        { label: 'Sân', value: item.courtName },
        { label: 'Khung giờ', value: item.timeLabel },
        { label: 'Loại booking', value: booking.booking_type === 'recurring' ? 'Lịch cố định' : 'Lẻ' },
        { label: 'Hình thức', value: this.paymentLabel(booking.payment_option) },
        { label: 'Thanh toán', value: this.paymentSummary(booking) },
        { label: 'Tổng tiền', value: this.formatCurrency(booking.total_price) },
        { label: 'Nguồn', value: booking.source === 'counter' ? 'Tại quầy' : 'Online' },
      ];
    },
    calendarDots() {
      // Tổng hợp ngày có booking và lock để hiện chấm trên calendar
      const dots = {};
      this.bookings.forEach((booking) => {
        const date = booking.booking_date || booking.items?.[0]?.booking_date;
        if (!date) return;
        if (!dots[date]) dots[date] = [];
        if (!dots[date].includes('booking')) dots[date].push('booking');
      });
      this.scheduleBusyIntervals
        .filter((i) => i.source === 'slot_lock')
        .forEach((i) => {
          const date = this.filters.booking_date;
          if (!date) return;
          if (!dots[date]) dots[date] = [];
          if (!dots[date].includes('lock')) dots[date].push('lock');
        });
      return dots;
    },
  },
  watch: {
    activeTimePeriod() {
      this.refreshSelectedTimeline();
    },
  },
  async mounted() {
    window.addEventListener('scroll', this.handleScroll);
    await this.loadClusters();

    const query = this.$route.query;
    if (query.venue_cluster_id) {
      this.filters.venue_cluster_id = query.venue_cluster_id;
      try {
        const response = await venueClusterService.getCourts(query.venue_cluster_id);
        this.courts = response.data || [];
      } catch (err) {
        console.error('Không thể tải danh sách sân con cho cụm:', err);
      }
    }
    if (query.booking_date) {
      this.filters.booking_date = query.booking_date;
    }
    if (query.venue_court_id) {
      this.filters.venue_court_id = query.venue_court_id;
    }
    if (query.status) {
      this.filters.status = query.status;
    }

    await this.loadBookings();
    this.holdClockInterval = setInterval(() => {
      this.holdClock = Date.now();
    }, 30000);
  },
  beforeUnmount() {
    window.removeEventListener('scroll', this.handleScroll);
    this.clearCollectPolling();
    this.closeActionMenu();
    if (this.holdClockInterval) clearInterval(this.holdClockInterval);
  },
  methods: {
    async loadClusters() {
      const response = await venueClusterService.getClusters();
      this.clusters = response.data || [];
    },
    onCalendarDatePick(date) {
      this.filters.booking_date = date;
      this.loadBookings();
    },
    async onClusterChange() {
      this.filters.venue_court_id = '';
      this.courts = [];
      if (this.filters.venue_cluster_id) {
        const response = await venueClusterService.getCourts(this.filters.venue_cluster_id);
        this.courts = response.data || [];
      }
      await this.loadBookings();
    },
    async loadBookings() {
      this.loading = true;
      this.error = '';
      this.notice = '';
      try {
        const response = await ownerBookingService.list(this.filters);
        this.bookings = response.data || [];
        await this.loadSchedule();
        this.refreshSelectedTimeline();
      } catch (error) {
        this.error = error.message || 'Không thể tải booking.';
      } finally {
        this.loading = false;
      }
    },
    async loadSchedule() {
      this.scheduleLoading = true;
      this.scheduleError = '';

      try {
        const clusterIds = this.filters.venue_cluster_id
          ? [this.filters.venue_cluster_id]
          : this.clusters
            .filter((cluster) => cluster.status === 'active')
            .map((cluster) => cluster.id);

        if (!clusterIds.length) {
          this.scheduleSlots = [];
          this.scheduleCourts = [];
          this.scheduleBusyIntervals = [];
          this.scheduleSlotStatuses = [];
          return;
        }

        const responses = await Promise.all(clusterIds.map((clusterId) => ownerBookingService.schedule({
          venue_cluster_id: clusterId,
          booking_date: this.filters.booking_date,
          booking_type: 'single',
        })));

        const slotsByKey = new Map();
        const courts = [];
        const intervals = [];
        const statuses = [];

        responses.forEach((response, index) => {
          const cluster = this.clusters.find((item) => String(item.id) === String(clusterIds[index]));
          (response.time_slots || []).forEach((slot) => {
            slotsByKey.set(`${slot.start_time}-${slot.end_time}`, slot);
          });
          (response.courts || []).forEach((court) => {
            courts.push({ ...court, cluster_name: cluster?.name || court.cluster_name || '' });
          });
          (response.busy_intervals || []).forEach((interval) => {
            intervals.push({ ...interval, cluster_name: cluster?.name || '' });
          });
          (response.slot_statuses || []).forEach((status) => statuses.push(status));
        });

        this.scheduleSlots = [...slotsByKey.values()].sort((a, b) => this.timeToMinutes(a.start_time) - this.timeToMinutes(b.start_time));
        this.scheduleCourts = courts;
        this.scheduleBusyIntervals = intervals;
        this.scheduleSlotStatuses = statuses;
      } catch (error) {
        this.scheduleSlots = [];
        this.scheduleCourts = [];
        this.scheduleBusyIntervals = [];
        this.scheduleSlotStatuses = [];
        this.scheduleError = error.message || 'Không thể tải lịch sân.';
      } finally {
        this.scheduleLoading = false;
      }
    },
    makeBookingBlock(booking, range) {
      const courtId = range.venueCourtId || booking.venue_court_id;
      if (!courtId) return null;

      const start = this.timeToMinutes(range.startTime);
      const end = this.timeToMinutes(range.endTime);
      if (end <= start) return null;

      const metrics = this.timelineBlockMetrics(start, end);
      const payment = this.paymentStateLabel(booking);
      const status = this.statusLabel(booking.status);
      const customer = this.customerName(booking);
      const phone = this.customerPhone(booking);

      return {
        key: `booking-${booking.id}-${courtId}-${range.startTime}-${range.endTime}`,
        type: 'booking',
        bookingId: booking.id,
        booking,
        courtId,
        courtName: range.courtName || this.courtName(courtId),
        start,
        end,
        title: customer || booking.booking_code || 'Booking',
        subtitle: `${booking.booking_code || 'Booking'} · ${status} · ${payment}`,
        timeLabel: `${this.formatTime(range.startTime)} - ${this.formatTime(range.endTime)}`,
        titleText: `${customer || 'Khách'}${phone ? ` · ${phone}` : ''} · ${booking.booking_code || 'Booking'} · ${range.courtName || this.courtName(courtId)} · ${status} · ${payment}`,
        style: metrics.style,
        compact: metrics.compact,
        kindClass: this.timelineBookingClass(booking),
      };
    },
    makeLockBlock(interval) {
      const start = this.timeToMinutes(interval.start_time);
      const end = this.timeToMinutes(interval.end_time);
      if (!interval.venue_court_id || end <= start) return null;

      const metrics = this.timelineBlockMetrics(start, end);

      return {
        key: `lock-${interval.schedule_lock_id || `${interval.venue_court_id}-${interval.start_time}`}`,
        type: 'lock',
        courtId: interval.venue_court_id,
        courtName: this.courtName(interval.venue_court_id),
        start,
        end,
        title: 'Khóa sân',
        subtitle: interval.reason || 'Không nhận khách',
        reason: interval.reason || '',
        timeLabel: `${this.formatTime(interval.start_time)} - ${this.formatTime(interval.end_time)}`,
        titleText: `Khóa sân · ${interval.reason || 'Không có lý do'}`,
        style: metrics.style,
        compact: metrics.compact,
        kindClass: 'block-lock',
      };
    },
    timelineBlockMetrics(start, end) {
      const clippedStart = Math.max(start, this.timelineStart);
      const clippedEnd = Math.min(end, this.timelineEnd);
      const left = ((clippedStart - this.timelineStart) / this.timelineDuration) * 100;
      const width = Math.max(((clippedEnd - clippedStart) / this.timelineDuration) * 100, 1.4);

      return {
        style: {
          left: `${left}%`,
          width: `calc(${width}% - 6px)`,
        },
        compact: width < 10,
      };
    },
    timelineBookingClass(booking) {
      if (booking.status === 'checked_in') return 'block-playing';
      if (['pending_approval', 'pending_payment'].includes(booking.status)) return 'block-pending';
      if (['cancelled', 'rejected', 'expired'].includes(booking.status)) return 'block-muted';
      return 'block-confirmed';
    },
    selectTimelineItem(block) {
      this.selectedTimelineItem = block;
    },
    refreshSelectedTimeline() {
      const blocks = this.timelineBlocks;
      if (!blocks.length) {
        this.selectedTimelineItem = null;
        return;
      }

      const currentKey = this.selectedTimelineItem?.key;
      this.selectedTimelineItem = blocks.find((block) => block.key === currentKey) || blocks[0];
    },
    async updateStatus(booking, action, statusReason = null) {
      if (this.updatingStatus) return;
      this.updatingStatus = true;
      this.error = '';
      this.notice = '';
      try {
        await ownerBookingService.updateStatus(booking.id, {
          action,
          status_reason: statusReason,
        });
        this.notice = 'Đã cập nhật trạng thái booking.';
        await this.loadBookings();
        this.closeStatusAction();
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật booking.';
      } finally {
        this.updatingStatus = false;
      }
    },
    primaryAction(booking) {
      if (booking.status === 'pending_approval') {
        return { key: 'confirm', label: 'Xác nhận booking', icon: 'check', variant: 'success' };
      }
      if (booking.status === 'confirmed') {
        return { key: 'check_in', label: 'Check-in', icon: 'clock', variant: 'success' };
      }
      if (booking.status === 'checked_in') {
        if (this.canCollectPayment(booking)) {
          return { key: 'collect', label: 'Thu tiền trước khi hoàn thành', icon: 'banknote', variant: 'primary' };
        }
        return { key: 'complete', label: 'Hoàn thành', icon: 'circleCheck', variant: 'success' };
      }
      if (this.canCollectPayment(booking)) {
        return { key: 'collect', label: 'Thu tiền', icon: 'banknote', variant: 'primary' };
      }
      return null;
    },
    secondaryActions(booking) {
      const primaryKey = this.primaryAction(booking)?.key;
      const actions = [];

      if (this.canCollectPayment(booking) && primaryKey !== 'collect') {
        actions.push({ key: 'collect', label: 'Thu tiền', icon: 'banknote', variant: 'primary' });
      }
      if (this.canChangeCourt(booking)) {
        actions.push({ key: 'change_court', label: 'Đổi sân', icon: 'pencil', variant: 'secondary' });
      }
      if (booking.status === 'pending_approval') {
        actions.push({ key: 'reject', label: 'Từ chối booking', icon: 'x', variant: 'danger' });
      }
      if (['pending_approval', 'pending_payment', 'confirmed'].includes(booking.status)) {
        actions.push({ key: 'cancel', label: 'Hủy booking', icon: 'trash', variant: 'danger' });
      }

      return actions;
    },
    canChangeCourt(booking) {
      return ['pending_approval', 'pending_payment', 'confirmed'].includes(booking.status)
        && this.bookingRanges(booking).length <= 1;
    },
    runBookingAction(booking, action) {
      this.closeActionMenu();

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

      this.updateStatus(booking, action);
    },
    toggleActionMenu(booking, event) {
      if (this.actionMenu.booking?.id === booking.id) {
        this.closeActionMenu();
        return;
      }

      const rect = event.currentTarget.getBoundingClientRect();
      const menuWidth = 220;
      const left = Math.min(
        Math.max(12, rect.right - menuWidth),
        window.innerWidth - menuWidth - 12,
      );
      const estimatedHeight = Math.max(this.secondaryActions(booking).length * 42 + 12, 54);
      const openAbove = rect.bottom + estimatedHeight > window.innerHeight - 12;

      this.actionMenu = {
        booking,
        top: openAbove ? Math.max(12, rect.top - estimatedHeight - 6) : rect.bottom + 6,
        left,
      };
    },
    closeActionMenu() {
      this.actionMenu = { booking: null, top: 0, left: 0 };
    },
    openStatusAction(booking, action) {
      this.statusActionBooking = booking;
      this.statusAction = action;
      this.statusActionReason = '';
    },
    closeStatusAction() {
      if (this.updatingStatus) return;
      this.statusActionBooking = null;
      this.statusAction = '';
      this.statusActionReason = '';
    },
    submitStatusAction() {
      if (!this.statusActionBooking || !this.statusActionReason) return;
      this.updateStatus(this.statusActionBooking, this.statusAction, this.statusActionReason);
    },
    async openChangeCourt(booking) {
      this.changeCourtBooking = booking;
      this.changeCourtForm = {
        venue_court_id: booking.venue_court_id,
        court_changed_reason: '',
      };
      const response = await venueClusterService.getCourts(booking.venue_cluster_id, { status: 'active' });
      this.changeCourtOptions = response.data || [];
    },
    closeChangeCourt() {
      this.changeCourtBooking = null;
      this.changeCourtOptions = [];
    },
    async saveChangeCourt() {
      if (!this.changeCourtBooking) return;
      this.savingChangeCourt = true;
      this.error = '';
      this.notice = '';

      try {
        await ownerBookingService.changeCourt(this.changeCourtBooking.id, this.changeCourtForm);
        this.notice = 'Đã đổi sân thực tế.';
        await this.loadBookings();
        this.closeChangeCourt();
      } catch (error) {
        this.error = error.message || 'Không thể đổi sân.';
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
      this.notice = '';

      try {
        const response = await ownerBookingService.collectPayment(this.collectBooking.id, {
          payment_method: this.collectForm.payment_method,
          amount: this.collectForm.amount,
        });

        if (this.collectForm.payment_method === 'sepay') {
          this.collectQr = response.payment_qr || null;
          this.notice = response.payment_qr?.reused
            ? 'Đã mở lại thông tin chuyển khoản đang chờ.'
            : 'Đã tạo thông tin chuyển khoản.';
          this.startCollectPolling();
        } else {
          this.notice = 'Đã ghi nhận thu tiền tại quầy.';
          await this.loadBookings();
          this.closeCollectPayment();
        }
      } catch (error) {
        this.error = error.message || 'Không thể ghi nhận thu tiền.';
      } finally {
        this.collectingPayment = false;
      }
    },
    startCollectPolling() {
      this.clearCollectPolling();
      this.collectPollInterval = setInterval(() => {
        this.refreshCollectBooking();
      }, 5000);
    },
    async refreshCollectBooking() {
      if (!this.collectBooking) return;

      try {
        const response = await ownerBookingService.show(this.collectBooking.id);
        const booking = response.data || response;
        this.collectBooking = booking;

        if (this.outstandingAmount(booking) <= 0) {
          this.notice = 'Chuyển khoản đã được ghi nhận.';
          await this.loadBookings();
          this.closeCollectPayment();
        }
      } catch {
        this.clearCollectPolling();
      }
    },
    clearCollectPolling() {
      if (this.collectPollInterval) {
        clearInterval(this.collectPollInterval);
        this.collectPollInterval = null;
      }
    },
    canCollectPayment(booking) {
      return booking.source === 'counter'
        && !['cancelled', 'expired', 'rejected'].includes(booking.status)
        && this.outstandingAmount(booking) > 0;
    },
    bookingHasPendingTransfer(booking) {
      return !!this.pendingTransfer(booking);
    },
    pendingTransfer(booking) {
      return (booking?.payments || []).find((payment) => payment.method === 'sepay' && payment.status === 'pending') || null;
    },
    paidAmount(booking) {
      return (booking.payments || [])
        .filter((payment) => payment.status === 'paid')
        .reduce((sum, payment) => sum + Number(payment.amount || 0), 0);
    },
    outstandingAmount(booking) {
      return Math.max(Number(booking.total_price || 0) - this.paidAmount(booking), 0);
    },
    paymentSummary(booking) {
      const paid = this.paidAmount(booking);
      const outstanding = this.outstandingAmount(booking);

      if (outstanding <= 0) return this.formatCurrency(paid);
      const amountText = paid > 0
        ? `đã thu ${this.formatCurrency(paid)}, còn ${this.formatCurrency(outstanding)}`
        : `còn ${this.formatCurrency(outstanding)}`;
      const holdText = this.paymentHoldLabel(booking);

      return holdText ? `${amountText} · ${holdText}` : amountText;
    },
    paymentHoldLabel(booking) {
      if (booking.status !== 'pending_payment') return '';

      const activeLock = (booking.slot_locks || [])
        .filter((lock) => new Date(lock.expires_at).getTime() > this.holdClock)
        .sort((a, b) => new Date(a.expires_at) - new Date(b.expires_at))[0];

      if (!activeLock) return 'chưa có hạn giữ chỗ';

      const seconds = Math.max(Math.ceil((new Date(activeLock.expires_at).getTime() - this.holdClock) / 1000), 0);
      const minutes = Math.floor(seconds / 60);
      const remainingSeconds = seconds % 60;

      return `giữ chỗ còn ${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
    },
    paymentState(booking) {
      if (this.outstandingAmount(booking) <= 0) return 'paid';
      if (this.bookingHasPendingTransfer(booking)) return 'pending';
      if (this.paidAmount(booking) > 0) return 'partial';
      return 'unpaid';
    },
    paymentStateLabel(booking) {
      return {
        paid: 'Đã thanh toán',
        pending: 'Chờ chuyển khoản',
        partial: 'Thanh toán một phần',
        unpaid: 'Chưa thanh toán',
      }[this.paymentState(booking)];
    },
    async copyText(text) {
      if (!text) return;

      try {
        await navigator.clipboard.writeText(text);
        this.notice = 'Đã sao chép nội dung chuyển khoản.';
      } catch {
        this.error = 'Không thể sao chép nội dung chuyển khoản.';
      }
    },
    customerName(booking) {
      return booking.customer?.full_name || booking.customer?.username || booking.walk_in_name || 'Khách vãng lai';
    },
    customerPhone(booking) {
      return booking.customer?.phone || booking.walk_in_phone || '-';
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

      return [{
        key: booking.id,
        venueCourtId: booking.venue_court_id,
        courtName: booking.venue_court?.name || '-',
        startTime: booking.start_time,
        endTime: booking.end_time,
      }];
    },
    courtName(courtId) {
      return this.scheduleCourts.find((court) => String(court.id) === String(courtId))?.name || '-';
    },
    courtOptionLabel(court) {
      return [court.cluster_name, court.court_type?.name].filter(Boolean).join(' · ') || court.court_type?.name || '-';
    },
    statusLabel(status) {
      return {
        pending_approval: 'Chờ duyệt',
        pending_payment: 'Chờ thanh toán',
        confirmed: 'Đã xác nhận',
        checked_in: 'Đã check-in',
        completed: 'Hoàn thành',
        cancelled: 'Đã hủy',
        rejected: 'Từ chối',
        expired: 'Hết hạn',
      }[status] || status;
    },
    paymentLabel(option) {
      return {
        full_payment: 'Thanh toán đủ',
        deposit: 'Đặt cọc',
        no_prepay: 'Thu sau',
      }[option] || option;
    },
    statusActionTitle() {
      return this.statusAction === 'reject' ? 'Từ chối booking' : 'Hủy booking';
    },
    collectSubmitLabel() {
      if (this.collectForm.payment_method !== 'sepay') return 'Xác nhận thu';
      return this.pendingTransfer(this.collectBooking)
        ? 'Xem lại thông tin chuyển khoản'
        : 'Tạo thông tin chuyển khoản';
    },
    formatDate(value) {
      if (!value) return '-';
      return new Intl.DateTimeFormat('vi-VN').format(new Date(value));
    },
    formatTime(time) {
      return (time || '').slice(0, 5);
    },
    timeToMinutes(value) {
      const [hour, minute] = this.formatTime(value).split(':').map(Number);
      return (hour || 0) * 60 + (minute || 0);
    },
    minutesToTime(totalMinutes) {
      const hour = Math.floor(totalMinutes / 60);
      const minute = totalMinutes % 60;
      return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(Number(amount || 0));
    },
    handleScroll() {
      this.showScrollTop = window.scrollY > 150;
    },
  },
};
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════
   Base
═══════════════════════════════════════════════════════════ */
.bookings-page {
  display: grid;
  gap: 12px;
  width: 100%;
}

.bk-layout {
  display: grid;
  grid-template-columns: 268px minmax(0, 1fr) 0px;
  gap: 14px;
  align-items: start;
  transition: grid-template-columns .25s ease;
}

.bk-layout:has(.bk-detail--open) {
  grid-template-columns: 268px minmax(0, 1fr) 320px;
}

/* ── Alerts ── */
.alert {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 800;
}

.alert--error {
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.alert--success {
  border: 1px solid #bbf7d0;
  background: #dcfce7;
  color: #166534;
}

/* ═══════════════════════════════════════════════════════════
   LEFT: Sidebar
═══════════════════════════════════════════════════════════ */
.bk-sidebar {
  display: grid;
  gap: 10px;
  position: sticky;
  top: 14px;
  align-self: start;
}

.bk-sidebar__cal-card {
  border: 1px solid #d8ecdb;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 4px 16px rgba(22, 163, 74, 0.06);
  overflow: hidden;
}

.bk-filter-card {
  display: grid;
  gap: 10px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
}

.bk-filter-card__label {
  margin: 0 0 2px;
  color: #64748b;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: .08em;
  text-transform: uppercase;
}

.bk-field {
  display: grid;
  gap: 5px;
}

.bk-field span {
  color: #334155;
  font-size: 12px;
  font-weight: 900;
}

.bk-field select,
.bk-field input,
.bk-field textarea {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 7px;
  background: #fff;
  color: #0f172a;
  font: inherit;
  font-size: 13px;
  transition: border-color .15s;
}

.bk-field select:focus,
.bk-field input:focus {
  outline: none;
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
}

.bk-reload-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  height: 36px;
  padding: 0 12px;
  border: 1px solid #d7e4d7;
  border-radius: 8px;
  background: #f7fbf5;
  color: #3d6645;
  font: inherit;
  font-size: 13px;
  font-weight: 900;
  cursor: pointer;
  transition: background .13s, border-color .13s;
}

.bk-reload-btn:hover {
  background: #eef8f0;
  border-color: #16a34a;
}

/* ═══════════════════════════════════════════════════════════
   CENTER: Timeline area
═══════════════════════════════════════════════════════════ */
.bk-main {
  display: grid;
  gap: 10px;
  min-width: 0;
}

.bk-timeline-header {
  padding: 16px;
  border: 1px solid #d8ecdb;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 4px 16px rgba(22, 163, 74, 0.06);
}

.bk-timeline-header__top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.bk-timeline-header__title {
  margin: 0;
  font-size: 18px;
  font-weight: 900;
  color: #0f172a;
}

.bk-timeline-header__sub {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 13px;
  font-weight: 800;
}

.bk-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.bk-legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.bk-legend__dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #cbd5e1;
}

.bk-legend__dot--confirmed { background: #16a34a; }
.bk-legend__dot--pending   { background: #f59e0b; }
.bk-legend__dot--playing   { background: #2563eb; }
.bk-legend__dot--lock      { background: #dc2626; }

.bk-period-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.bk-period-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  flex: 1;
}

.bk-period-tab {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 38px;
  padding: 6px 12px;
  border: 1px solid #d7e4d7;
  border-radius: 8px;
  background: #fff;
  color: #334155;
  font: inherit;
  cursor: pointer;
  transition: background .13s, border-color .13s, color .13s;
}

.bk-period-tab strong { font-size: 13px; }
.bk-period-tab span { opacity: .75; font-size: 12px; font-weight: 800; }

.bk-period-tab--active {
  border-color: #16a34a;
  background: #16a34a;
  color: #fff;
}

.bk-metrics {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.bk-metric {
  display: grid;
  gap: 2px;
  padding: 8px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f9fafb;
  min-width: 80px;
}

.bk-metric span {
  color: #64748b;
  font-size: 11px;
  font-weight: 800;
}

.bk-metric strong {
  color: #0f172a;
  font-size: 20px;
  font-weight: 900;
}

.bk-state {
  padding: 40px;
  text-align: center;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  color: #64748b;
  font-weight: 800;
}

.bk-state--error { color: #991b1b; }

.bk-timeline-board {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
}

.bk-timeline-scroller {
  max-width: 100%;
  overflow-x: auto;
}

.bk-timeline-axis,
.bk-timeline-row {
  display: grid;
  grid-template-columns: 160px minmax(0, 1fr);
}

.bk-timeline-axis {
  position: sticky;
  top: 0;
  z-index: 4;
  min-height: 44px;
  background: #f3f8f1;
  border-bottom: 1px solid #dfe8df;
}

.bk-axis-court,
.bk-court-meta {
  position: sticky;
  left: 0;
  z-index: 3;
  border-right: 1px solid #e2e8f0;
  background: inherit;
}

.bk-axis-court {
  display: grid;
  place-items: center;
  color: #334238;
  font-size: 12px;
  font-weight: 900;
}

.bk-axis-track,
.bk-timeline-track {
  position: relative;
}

.bk-axis-tick {
  position: absolute;
  top: 50%;
  transform: translate(-50%, -50%);
  color: #475569;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.bk-timeline-row {
  min-height: 72px;
  border-bottom: 1px solid #e2e8f0;
  background: #fff;
}

.bk-timeline-row:last-child { border-bottom: 0; }

.bk-court-meta {
  display: grid;
  align-content: center;
  gap: 3px;
  padding: 10px 12px;
  background: #fff;
}

.bk-court-meta strong {
  color: #0f172a;
  font-size: 13px;
  font-weight: 900;
}

.bk-court-meta span {
  color: #64748b;
  font-size: 11px;
  font-weight: 750;
}

.bk-timeline-track {
  min-height: 72px;
  background: linear-gradient(180deg, #fff, #fbfdfb);
}

.bk-track-gridline {
  position: absolute;
  inset-block: 0;
  width: 1px;
  background: #eef2f7;
}

.bk-empty-track {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: 12px;
  font-weight: 800;
}

.bk-block {
  position: absolute;
  top: 10px;
  bottom: 10px;
  min-width: 0;
  display: grid;
  align-content: center;
  gap: 2px;
  padding: 8px 10px;
  border: 1px solid transparent;
  border-radius: 7px;
  color: #0f172a;
  font: inherit;
  text-align: left;
  cursor: pointer;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.07);
  transition: outline .1s, box-shadow .1s;
}

.bk-block:hover,
.bk-block--active {
  outline: 2px solid rgba(22, 163, 74, 0.35);
  outline-offset: 2px;
  box-shadow: 0 6px 20px rgba(22, 163, 74, 0.14);
}

.bk-block__time,
.bk-block strong,
.bk-block small {
  display: block;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bk-block__time { font-size: 10px; font-weight: 900; opacity: .75; }
.bk-block strong { font-size: 13px; font-weight: 950; }
.bk-block small  { font-size: 11px; font-weight: 850; }
.bk-block--compact small { display: none; }

/* Block colors – giữ nguyên */
.block-confirmed { background: #dcfce7; border-color: #86efac; color: #14532d; }
.block-pending   { background: #fef3c7; border-color: #facc15; color: #713f12; }
.block-playing   { background: #dbeafe; border-color: #93c5fd; color: #1e3a8a; }
.block-muted     { background: #f1f5f9; border-color: #cbd5e1; color: #475569; }
.block-lock      { background: #fee2e2; border-color: #fca5a5; color: #7f1d1d; }

/* ═══════════════════════════════════════════════════════════
   RIGHT: Detail slide-in panel
═══════════════════════════════════════════════════════════ */
.bk-detail {
  width: 0;
  overflow: hidden;
  opacity: 0;
  pointer-events: none;
  transition: width .25s ease, opacity .2s ease;
  align-self: start;
  position: sticky;
  top: 14px;
}

.bk-detail--open {
  width: 320px;
  opacity: 1;
  pointer-events: auto;
  border: 1px solid #d8ecdb;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 8px 32px rgba(22, 163, 74, 0.08);
  overflow: visible;
}

.bk-detail__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  padding: 16px 16px 12px;
  border-bottom: 1px solid #e2e8f0;
  background: #f3f8f1;
  border-radius: 12px 12px 0 0;
}

.bk-detail__eyebrow {
  margin: 0 0 4px;
  color: #16a34a;
  font-size: 10px;
  font-weight: 950;
  letter-spacing: .1em;
}

.bk-detail__title {
  margin: 0;
  color: #0f172a;
  font-size: 16px;
  font-weight: 900;
  line-height: 1.3;
}

.bk-detail__sub {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.bk-detail__close {
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
  border: 1px solid #d7e4d7;
  border-radius: 7px;
  background: #fff;
  color: #64748b;
  cursor: pointer;
  transition: background .12s;
}

.bk-detail__close:hover { background: #f1f5f9; color: #334155; }

.bk-detail__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 12px 16px 0;
}

.bk-chip {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 3px 9px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.bk-chip.confirmed, .bk-chip.completed, .bk-chip.checked_in { background: #dcfce7; color: #166534; }
.bk-chip.pending_payment, .bk-chip.pending_approval { background: #fef3c7; color: #92400e; }
.bk-chip.cancelled, .bk-chip.rejected { background: #fee2e2; color: #991b1b; }
.bk-chip--payment.paid { background: #dcfce7; color: #166534; }
.bk-chip--payment.pending, .bk-chip--payment.partial { background: #fff7ed; color: #9a3412; }
.bk-chip--payment.unpaid { background: #fef2f2; color: #991b1b; }

.bk-detail__list {
  display: grid;
  margin: 12px 0 0;
  padding: 0 16px;
  border-top: 1px solid #e2e8f0;
}

.bk-detail__list div {
  display: grid;
  grid-template-columns: 90px minmax(0, 1fr);
  gap: 10px;
  padding: 9px 0;
  border-bottom: 1px solid #f1f5f9;
}

.bk-detail__list dt { color: #64748b; font-size: 12px; font-weight: 850; }
.bk-detail__list dd { margin: 0; color: #0f172a; font-size: 13px; font-weight: 850; overflow-wrap: anywhere; }

.bk-detail__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  padding: 12px 16px 16px;
}

.bk-action-btn {
  min-height: 34px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0 10px;
  border: 1px solid #d9e8d9;
  border-radius: 7px;
  background: #fff;
  color: #334155;
  font: inherit;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
  transition: background .12s;
}

.bk-action-btn:hover { background: #f7fbf5; }
.bk-action-btn--danger { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
.bk-action-btn--danger:hover { background: #fee2e2; }

.bk-detail__empty {
  display: grid;
  place-items: center;
  gap: 10px;
  padding: 48px 24px;
  text-align: center;
}

.bk-detail__empty p { margin: 0; color: #64748b; font-size: 14px; font-weight: 900; }
.bk-detail__empty small { color: #94a3b8; font-size: 12px; font-weight: 700; line-height: 1.5; }

/* ═══════════════════════════════════════════════════════════
   Modals
═══════════════════════════════════════════════════════════ */
.bk-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgba(15, 23, 42, 0.48);
  backdrop-filter: blur(2px);
}

.bk-modal {
  width: min(520px, 100%);
  display: grid;
  gap: 0;
  border: 1px solid #d8ecdb;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 24px 64px rgba(15, 23, 42, 0.2);
  overflow: hidden;
}

.bk-modal--wide { width: min(620px, 100%); }

.bk-modal__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 18px 20px 16px;
  border-bottom: 1px solid #e2e8f0;
  background: #f3f8f1;
}

.bk-modal__header h2 { margin: 0; font-size: 17px; font-weight: 900; color: #0f172a; }
.bk-modal__header p  { margin: 4px 0 0; color: #64748b; font-size: 13px; }

.bk-modal > .bk-field,
.bk-modal > .bk-method-row,
.bk-modal > .bk-collect-summary,
.bk-modal > .bk-collect-qr,
.bk-modal > .bk-status-warning {
  margin: 16px 20px 0;
}

.bk-modal__footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 20px;
  border-top: 1px solid #e2e8f0;
  background: #f9fafb;
  margin-top: 16px;
}

.bk-btn {
  min-height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 0 16px;
  border-radius: 8px;
  font: inherit;
  font-weight: 900;
  font-size: 14px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: background .13s;
}

.bk-btn--primary { background: #16a34a; border-color: #15803d; color: #fff; }
.bk-btn--primary:hover { background: #15803d; }
.bk-btn--primary:disabled { opacity: .55; cursor: not-allowed; }

.bk-btn--ghost { background: #fff; border-color: #cbd5e1; color: #334155; }
.bk-btn--ghost:hover { background: #f1f5f9; }

.bk-btn--danger { background: #dc2626; border-color: #dc2626; color: #fff; }
.bk-btn--danger:hover { background: #b91c1c; }
.bk-btn--danger:disabled { opacity: .55; cursor: not-allowed; }

.bk-status-warning {
  padding: 12px 14px;
  border-left: 3px solid #dc2626;
  background: #fef2f2;
  color: #7f1d1d;
  font-size: 13px;
  font-weight: 750;
  line-height: 1.5;
  border-radius: 0 6px 6px 0;
}

.bk-collect-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}

.bk-collect-summary div { padding: 12px; border: 1px solid #d9e8d9; border-radius: 8px; background: #f7fbf5; }
.bk-collect-summary dt { color: #647265; font-size: 12px; font-weight: 800; }
.bk-collect-summary dd { margin: 6px 0 0; color: #16231a; font-weight: 900; }
.bk-collect-summary__highlight { border-color: rgba(47, 158, 68, 0.45) !important; background: #e8f7ec !important; }

.bk-method-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.bk-method-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
  color: #344238;
  font: inherit;
  font-weight: 900;
  cursor: pointer;
  transition: background .13s, border-color .13s, color .13s;
}

.bk-method-btn--active { border-color: #2f9e44; background: #2f9e44; color: #fff; }

.bk-collect-qr { display: grid; gap: 10px; padding: 14px; border: 1px solid #d9e8d9; border-radius: 8px; background: #f7fbf5; }
.bk-collect-qr img { width: 200px; max-width: 100%; border: 1px solid #d9e8d9; border-radius: 8px; background: #fff; }
.bk-collect-qr__info { display: grid; gap: 8px; }
.bk-collect-qr__info div { display: flex; justify-content: space-between; gap: 14px; color: #647265; }
.bk-collect-qr__info button { border: 0; background: transparent; color: #216b34; font-weight: 900; text-decoration: underline; cursor: pointer; }
.bk-collect-qr__info small { color: #647265; font-size: 12px; }

/* ── Action menu ── */
.action-menu-dismiss {
  position: fixed;
  inset: 0;
  z-index: 1190;
  width: 100%;
  height: 100%;
  padding: 0;
  border: 0;
  background: transparent;
}

.row-action-menu {
  position: fixed;
  z-index: 1200;
  width: 220px;
  display: grid;
  gap: 4px;
  padding: 6px;
  border: 1px solid #d9e8d9;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 16px 40px rgba(22, 35, 26, 0.18);
}

.row-action-menu button {
  width: 100%;
  min-height: 38px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: #2f3d33;
  font: inherit;
  font-size: 13px;
  font-weight: 800;
  text-align: left;
  cursor: pointer;
  transition: background .12s;
}

.row-action-menu button:hover { background: #eef8ef; color: #216b34; }
.row-action-menu button.danger { color: #b42318; }
.row-action-menu button.danger:hover { background: #fef2f2; }

/* ═══════════════════════════════════════════════════════════
   Responsive
═══════════════════════════════════════════════════════════ */
@media (max-width: 1100px) {
  .bk-layout,
  .bk-layout:has(.bk-detail--open) {
    grid-template-columns: 240px minmax(0, 1fr);
  }

  .bk-detail--open {
    position: fixed;
    right: 0;
    top: 0;
    bottom: 0;
    z-index: 900;
    width: 320px !important;
    border-radius: 0;
    overflow-y: auto;
  }
}

@media (max-width: 768px) {
  .bk-layout,
  .bk-layout:has(.bk-detail--open) {
    grid-template-columns: 1fr;
  }

  .bk-sidebar { position: static; }
  .bk-metrics { display: none; }
  .bk-collect-summary { grid-template-columns: 1fr; }
}
</style>

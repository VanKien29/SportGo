<template>
  <div class="bookings-page">
    <!-- Floating Add Button -->
    <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
      <router-link class="btn-float-add" to="/owner/counter-booking" title="Tạo booking tại quầy">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Tạo booking</span>
      </router-link>
    </div>

    <section class="filters">
      <label>
        <span>Cụm sân</span>
        <select v-model="filters.venue_cluster_id" @change="onClusterChange">
          <option value="">Tất cả</option>
          <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
        </select>
      </label>
      <label>
        <span>Sân con</span>
        <select v-model="filters.venue_court_id" @change="loadBookings">
          <option value="">Tất cả</option>
          <option v-for="court in courts" :key="court.id" :value="court.id">{{ court.name }}</option>
        </select>
      </label>
      <label>
        <span>Ngày chơi</span>
        <input v-model="filters.booking_date" type="date" @change="loadBookings" />
      </label>
      <label>
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
      <button class="icon-btn" type="button" title="Tải lại" aria-label="Tải lại" @click="loadBookings">
        <AppIcon name="refresh" size="17" />
      </button>
    </section>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="notice" class="alert success">{{ notice }}</div>

    <section class="schedule-card">
      <div class="schedule-head">
        <div>
          <h2>Lịch sân trong ngày</h2>
          <p>{{ scheduleSubtitle }}</p>
        </div>
        <div class="legend">
          <span><i class="status-confirmed"></i>Đã xác nhận</span>
          <span><i class="status-pending"></i>Chờ xử lý</span>
          <span><i class="status-playing"></i>Đang chơi</span>
          <span><i class="status-lock"></i>Khóa sân</span>
        </div>
      </div>

      <div class="period-row">
        <button
          v-for="period in timePeriods"
          :key="period.key"
          type="button"
          :class="{ active: activeTimePeriod === period.key }"
          @click="activeTimePeriod = period.key"
        >
          <strong>{{ period.label }}</strong>
          <span>{{ period.range }}</span>
        </button>
      </div>

      <div class="metric-row">
        <div v-for="metric in scheduleMetrics" :key="metric.label" class="metric-card">
          <span>{{ metric.label }}</span>
          <strong>{{ metric.value }}</strong>
        </div>
      </div>

      <div v-if="loading || scheduleLoading" class="state-card">Đang tải lịch sân...</div>
      <div v-else-if="scheduleError" class="state-card error-state">{{ scheduleError }}</div>
      <div v-else-if="!timelineRows.length" class="state-card">Chưa có sân phù hợp với bộ lọc hiện tại.</div>
      <div v-else class="timeline-layout">
        <div class="timeline-board">
          <div class="timeline-scroller">
            <div class="timeline-axis" :style="{ minWidth: timelineMinWidth }">
              <div class="axis-court">Sân / giờ</div>
              <div class="axis-track">
                <span
                  v-for="tick in timelineTicks"
                  :key="tick.value"
                  class="axis-tick"
                  :style="{ left: `${tick.left}%` }"
                >
                  {{ tick.label }}
                </span>
              </div>
            </div>

            <article
              v-for="row in timelineRows"
              :key="row.court.id"
              class="timeline-row"
              :style="{ minWidth: timelineMinWidth }"
            >
              <div class="court-meta">
                <strong>{{ row.court.name }}</strong>
                <span>{{ courtOptionLabel(row.court) }}</span>
              </div>
              <div class="timeline-track">
                <span
                  v-for="tick in timelineTicks"
                  :key="`${row.court.id}-${tick.value}`"
                  class="track-gridline"
                  :style="{ left: `${tick.left}%` }"
                ></span>
                <span v-if="!row.blocks.length" class="empty-track">Trống trong khoảng này</span>
                <button
                  v-for="block in row.blocks"
                  :key="block.key"
                  type="button"
                  class="timeline-block"
                  :class="[block.kindClass, { active: selectedTimelineItem?.key === block.key, compact: block.compact }]"
                  :style="block.style"
                  :title="block.titleText"
                  @click="selectTimelineItem(block)"
                >
                  <span class="block-time">{{ block.timeLabel }}</span>
                  <strong>{{ block.title }}</strong>
                  <small>{{ block.subtitle }}</small>
                </button>
              </div>
            </article>
          </div>
        </div>

        <aside class="timeline-inspector">
          <template v-if="selectedTimelineItem">
            <p class="inspector-eyebrow">{{ selectedTimelineItem.type === 'booking' ? 'BOOKING' : 'KHÓA LỊCH' }}</p>
            <h3>{{ selectedTimelineItem.title }}</h3>
            <p class="inspector-subtitle">{{ selectedTimelineItem.timeLabel }} · {{ selectedTimelineItem.courtName }}</p>

            <div v-if="selectedTimelineBooking" class="inspector-chips">
              <span class="status-chip" :class="selectedTimelineBooking.status">{{ statusLabel(selectedTimelineBooking.status) }}</span>
              <span class="payment-chip" :class="paymentState(selectedTimelineBooking)">{{ paymentStateLabel(selectedTimelineBooking) }}</span>
            </div>

            <dl class="inspector-list">
              <div v-for="row in selectedTimelineRows" :key="row.label">
                <dt>{{ row.label }}</dt>
                <dd>{{ row.value }}</dd>
              </div>
            </dl>

            <div v-if="selectedTimelineBooking" class="inspector-actions">
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
                class="inspector-action"
                :class="{ danger: action.variant === 'danger' }"
                @click="runBookingAction(selectedTimelineBooking, action.key)"
              >
                <AppIcon :name="action.icon" size="16" />
                <span>{{ action.label }}</span>
              </button>
            </div>
          </template>
          <template v-else>
            <p class="inspector-eyebrow">CHI TIẾT</p>
            <h3>Chọn một block trên lịch</h3>
            <p class="inspector-subtitle">Thông tin khách, trạng thái booking và thanh toán sẽ hiện ở đây.</p>
          </template>
        </aside>
      </div>
    </section>

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

    <div v-if="changeCourtBooking" class="modal-backdrop" @click.self="closeChangeCourt">
      <form class="modal-panel" @submit.prevent="saveChangeCourt">
        <header>
          <h2>Đổi sân thực tế</h2>
          <ActionIconButton icon="x" label="Đóng" variant="ghost" @click="closeChangeCourt" />
        </header>
        <label>
          <span>Sân mới</span>
          <select v-model="changeCourtForm.venue_court_id" required>
            <option v-for="court in changeCourtOptions" :key="court.id" :value="court.id">{{ court.name }} · {{ court.court_type?.name }}</option>
          </select>
        </label>
        <label>
          <span>Lý do đổi sân</span>
          <textarea v-model.trim="changeCourtForm.court_changed_reason" rows="4" required></textarea>
        </label>
        <footer>
          <button type="button" class="ghost-btn" @click="closeChangeCourt">Hủy</button>
          <button class="primary-link" type="submit" :disabled="savingChangeCourt">Lưu đổi sân</button>
        </footer>
      </form>
    </div>

    <div v-if="collectBooking" class="modal-backdrop" @click.self="closeCollectPayment">
      <form class="modal-panel collect-panel" @submit.prevent="submitCollectPayment">
        <header>
          <div>
            <h2>Thu tiền booking</h2>
            <p>{{ collectBooking.booking_code }} · {{ customerName(collectBooking) }}</p>
          </div>
          <ActionIconButton icon="x" label="Đóng" variant="ghost" @click="closeCollectPayment" />
        </header>

        <dl class="collect-summary">
          <div>
            <dt>Tổng tiền</dt>
            <dd>{{ formatCurrency(collectBooking.total_price) }}</dd>
          </div>
          <div>
            <dt>Đã thu</dt>
            <dd>{{ formatCurrency(paidAmount(collectBooking)) }}</dd>
          </div>
          <div class="highlight">
            <dt>Còn phải thu</dt>
            <dd>{{ formatCurrency(outstandingAmount(collectBooking)) }}</dd>
          </div>
        </dl>

        <label>
          <span>Số tiền thu</span>
          <input
            v-model.number="collectForm.amount"
            type="number"
            min="1000"
            step="1000"
            :disabled="collectForm.payment_method === 'sepay' && !!pendingTransfer(collectBooking)"
          />
        </label>

        <div class="method-row">
          <button type="button" :class="{ active: collectForm.payment_method === 'cash' }" @click="collectForm.payment_method = 'cash'">
            <AppIcon name="banknote" size="16" />
            <span>Tiền mặt</span>
          </button>
          <button type="button" :class="{ active: collectForm.payment_method === 'sepay' }" @click="collectForm.payment_method = 'sepay'">
            <AppIcon name="creditCard" size="16" />
            <span>Chuyển khoản</span>
          </button>
        </div>

        <div v-if="collectQr" class="collect-qr">
          <img :src="collectQr.qr_url" alt="Mã chuyển khoản" />
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

        <footer>
          <button type="button" class="ghost-btn" @click="closeCollectPayment">Đóng</button>
          <button class="primary-link" type="submit" :disabled="collectingPayment">
            {{ collectSubmitLabel() }}
          </button>
        </footer>
      </form>
    </div>

    <div v-if="statusActionBooking" class="modal-backdrop" @click.self="closeStatusAction">
      <form class="modal-panel status-action-panel" @submit.prevent="submitStatusAction">
        <header>
          <div>
            <h2>{{ statusActionTitle() }}</h2>
            <p>{{ statusActionBooking.booking_code }} · {{ customerName(statusActionBooking) }}</p>
          </div>
          <ActionIconButton icon="x" label="Đóng" variant="ghost" @click="closeStatusAction" />
        </header>
        <p class="status-action-warning">
          {{ statusAction === 'reject'
            ? 'Booking sẽ bị từ chối và giải phóng khung sân.'
            : 'Booking sẽ bị hủy và giao dịch đang chờ, nếu có, sẽ không còn hiệu lực.' }}
        </p>
        <label>
          <span>Lý do {{ statusAction === 'reject' ? 'từ chối' : 'hủy' }}</span>
          <textarea v-model.trim="statusActionReason" rows="4" maxlength="1000" required></textarea>
        </label>
        <footer>
          <button type="button" class="ghost-btn" @click="closeStatusAction">Đóng</button>
          <button class="danger-btn" type="submit" :disabled="updatingStatus">
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

function localIsoDate(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export default {
  name: 'OwnerBookings',
  components: { ActionIconButton, AppIcon },
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
.bookings-page {
  display: grid;
  gap: 18px;
  width: 100%;
  max-width: none;
  margin: 0 auto;
}

.filters,
.schedule-card,
.table-card,
.state-card,
.modal-panel,
.alert {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.filters {
  display: grid;
  grid-template-columns: 1fr 1fr 160px 180px auto;
  gap: 12px;
  align-items: end;
  padding: 16px;
}

.booking-range {
  display: block;
}

.booking-range + .booking-range {
  margin-top: 7px;
  padding-top: 7px;
  border-top: 1px solid #edf2ed;
}

.schedule-card {
  display: grid;
  gap: 14px;
  padding: 16px;
  overflow: hidden;
}

.schedule-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
}

.schedule-head h2,
.timeline-inspector h3 {
  margin: 0;
  color: #0f172a;
  font-weight: 900;
}

.schedule-head p,
.inspector-subtitle {
  margin: 5px 0 0;
  color: #64748b;
  font-size: 13px;
  line-height: 1.45;
}

.legend {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 10px;
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.legend i {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: #cbd5e1;
}

.legend .status-confirmed {
  background: #16a34a;
}

.legend .status-pending {
  background: #f59e0b;
}

.legend .status-playing {
  background: #2563eb;
}

.legend .status-lock {
  background: #dc2626;
}

.period-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 12px;
}

.period-row button {
  min-height: 42px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border: 1px solid #d7e4d7;
  border-radius: 8px;
  background: #fff;
  color: #334155;
  font: inherit;
  cursor: pointer;
}

.period-row button.active {
  border-color: #16a34a;
  background: #16a34a;
  color: #fff;
}

.period-row span {
  color: inherit;
  opacity: .78;
  font-size: 12px;
  font-weight: 800;
}

.metric-row {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
}

.metric-card {
  min-height: 68px;
  display: grid;
  align-content: center;
  gap: 5px;
  padding: 12px 14px;
  background: #fbfdfb;
}

.metric-card + .metric-card {
  border-left: 1px solid #e2e8f0;
}

.metric-card span {
  color: #64748b;
  font-size: 12px;
  font-weight: 800;
}

.metric-card strong {
  color: #0f172a;
  font-size: 22px;
}

.timeline-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 16px;
  align-items: start;
}

.timeline-board {
  min-width: 0;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
}

.timeline-scroller {
  max-width: 100%;
  overflow-x: auto;
}

.timeline-axis,
.timeline-row {
  display: grid;
  grid-template-columns: 168px minmax(0, 1fr);
}

.timeline-axis {
  position: sticky;
  top: 0;
  z-index: 4;
  min-height: 44px;
  background: #f3f8f1;
  border-bottom: 1px solid #dfe8df;
}

.axis-court,
.court-meta {
  position: sticky;
  left: 0;
  z-index: 3;
  border-right: 1px solid #e2e8f0;
  background: inherit;
}

.axis-court {
  display: grid;
  place-items: center;
  color: #334238;
  font-size: 12px;
  font-weight: 900;
}

.axis-track,
.timeline-track {
  position: relative;
}

.axis-tick {
  position: absolute;
  top: 50%;
  transform: translate(-50%, -50%);
  color: #475569;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.timeline-row {
  min-height: 76px;
  border-bottom: 1px solid #e2e8f0;
  background: #fff;
}

.timeline-row:last-child {
  border-bottom: 0;
}

.court-meta {
  display: grid;
  align-content: center;
  gap: 4px;
  padding: 10px 12px;
  background: #fff;
}

.court-meta strong {
  color: #0f172a;
  font-size: 13px;
}

.court-meta span {
  color: #64748b;
  font-size: 11px;
  font-weight: 750;
  line-height: 1.35;
}

.timeline-track {
  min-height: 76px;
  background: linear-gradient(180deg, #fff, #fbfdfb);
}

.track-gridline {
  position: absolute;
  inset-block: 0;
  width: 1px;
  background: #eef2f7;
}

.empty-track {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: 12px;
  font-weight: 800;
}

.timeline-block {
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
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
}

.timeline-block:hover,
.timeline-block.active {
  outline: 2px solid var(--admin-primary, #000000);
  outline-offset: 2px;
}

.timeline-block strong,
.timeline-block small,
.block-time {
  display: block;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.timeline-block strong {
  font-size: 13px;
  font-weight: 950;
}

.timeline-block small,
.block-time {
  font-size: 11px;
  font-weight: 850;
}

.timeline-block.compact small {
  display: none;
}

.block-confirmed {
  background: #dcfce7;
  border-color: #86efac;
  color: #14532d;
}

.block-pending {
  background: #fef3c7;
  border-color: #facc15;
  color: #713f12;
}

.block-playing {
  background: #dbeafe;
  border-color: #93c5fd;
  color: #1e3a8a;
}

.block-muted {
  background: #f1f5f9;
  border-color: #cbd5e1;
  color: #475569;
}

.block-lock {
  background: #fee2e2;
  border-color: #fca5a5;
  color: #7f1d1d;
}

.timeline-inspector {
  position: sticky;
  top: 14px;
  display: grid;
  gap: 14px;
  padding: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
}

.inspector-eyebrow {
  margin: 0;
  color: #16a34a;
  font-size: 11px;
  font-weight: 950;
  letter-spacing: .08em;
}

.inspector-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.inspector-list {
  display: grid;
  gap: 0;
  margin: 0;
  border-top: 1px solid #e2e8f0;
}

.inspector-list div {
  display: grid;
  grid-template-columns: 105px minmax(0, 1fr);
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid #eef2f7;
}

.inspector-list dt {
  color: #64748b;
  font-size: 12px;
  font-weight: 850;
}

.inspector-list dd {
  min-width: 0;
  margin: 0;
  color: #0f172a;
  font-size: 13px;
  font-weight: 850;
  overflow-wrap: anywhere;
}

.inspector-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.inspector-action {
  min-height: 36px;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 0 10px;
  border: 1px solid #d9e8d9;
  border-radius: 7px;
  background: #fff;
  color: #334155;
  font: inherit;
  font-size: 12px;
  font-weight: 900;
  cursor: pointer;
}

.inspector-action.danger {
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

label {
  display: grid;
  gap: 7px;
}

label span {
  color: #334155;
  font-size: 13px;
  font-weight: 900;
}

input,
select,
textarea {
  width: 100%;
  padding: 11px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #0f172a;
  font: inherit;
}

.primary-link,
.ghost-btn {
  height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 0 14px;
  border-radius: 8px;
  font-weight: 900;
}

.primary-link {
  background: #16a34a;
  color: #fff;
}

.ghost-btn {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
}

.alert,
.state-card {
  padding: 14px;
  font-weight: 900;
}

.alert.error {
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.alert.success {
  border-color: #bbf7d0;
  background: #dcfce7;
  color: #166534;
}

.state-card {
  text-align: center;
  color: #64748b;
}

.table-card {
  overflow-x: auto;
  overflow-y: visible;
}

.bookings-page table {
  width: 100%;
  min-width: 940px !important;
  border-collapse: collapse;
}

.bookings-page th,
.bookings-page td {
  padding: 10px 12px !important;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  vertical-align: top !important;
  font-size: 14px;
}

th {
  background: #f3f8f1;
  color: #3f4f43;
  font-weight: 900;
}

tbody tr {
  transition: background .16s ease;
}

tbody tr:hover {
  background: #fbfdfb;
}

td small {
  display: block;
  margin-top: 4px;
  color: #526056;
}

.strong,
td strong {
  color: #0f172a;
  font-weight: 900;
}

.status-chip {
  padding: 5px 9px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.payment-cell {
  min-width: 170px;
}

.payment-chip {
  display: inline-flex;
  align-items: center;
  min-height: 26px;
  padding: 4px 9px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #334155;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.payment-chip.paid {
  background: #dcfce7;
  color: #166534;
}

.payment-chip.pending,
.payment-chip.partial {
  background: #fff7ed;
  color: #9a3412;
}

.payment-chip.unpaid {
  background: #fef2f2;
  color: #991b1b;
}

.status-chip.confirmed,
.status-chip.completed,
.status-chip.checked_in {
  background: #dcfce7;
  color: #166534;
}

.status-chip.pending_payment,
.status-chip.pending_approval {
  background: #fef3c7;
  color: #92400e;
}

.status-chip.cancelled,
.status-chip.rejected {
  background: #fee2e2;
  color: #991b1b;
}

.actions-heading {
  width: 84px;
  text-align: right;
}

.booking-actions-cell {
  width: 84px;
  text-align: right;
  white-space: nowrap;
}

.row-actions {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
}

.no-actions {
  color: #7a877d;
  font-size: 12px;
  font-weight: 700;
}

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
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 16px 40px rgba(22, 35, 26, 0.16);
}

.row-action-menu button {
  width: 100%;
  min-height: 38px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #2f3d33;
  font: inherit;
  font-size: 13px;
  font-weight: 800;
  text-align: left;
  cursor: pointer;
}

.row-action-menu button:hover {
  background: #eef8ef;
  color: #216b34;
}

.row-action-menu button.danger {
  color: #b42318;
}

.row-action-menu button.danger:hover {
  background: #fef2f2;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgba(15, 23, 42, 0.48);
}

.modal-panel {
  width: min(520px, 100%);
  display: grid;
  gap: 16px;
  padding: 20px;
}

.status-action-panel {
  width: min(500px, 100%);
}

.status-action-warning {
  margin: 0;
  padding: 12px 14px;
  border-left: 3px solid #dc2626;
  background: #fef2f2;
  color: #7f1d1d;
  font-size: 13px;
  font-weight: 750;
  line-height: 1.5;
}

.danger-btn {
  min-height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 14px;
  border: 1px solid #dc2626;
  border-radius: 8px;
  background: #dc2626;
  color: #fff;
  font: inherit;
  font-weight: 900;
  cursor: pointer;
}

.danger-btn:disabled {
  cursor: not-allowed;
  opacity: .55;
}

.modal-panel header p {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 13px;
  font-weight: 700;
}

.collect-panel {
  width: min(620px, 100%);
}

.collect-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}

.collect-summary div {
  padding: 12px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #f7fbf5;
}

.collect-summary dt {
  color: #647265;
  font-size: 12px;
  font-weight: 800;
}

.collect-summary dd {
  margin: 6px 0 0;
  color: #16231a;
  font-weight: 900;
}

.collect-summary .highlight {
  border-color: var(--admin-primary, #000000);
  background: var(--admin-primary-soft, #f3f4f6);
}

.method-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.method-row button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 42px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
  color: #344238;
  font-weight: 900;
}

.method-row button.active {
  border-color: var(--admin-primary, #000000);
  background: var(--admin-primary, #000000);
  color: #fff;
}

.collect-qr {
  display: grid;
  gap: 10px;
  justify-items: start;
  padding: 14px;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #f7fbf5;
}

.collect-qr img {
  width: 220px;
  max-width: 100%;
  border: 1px solid #d9e8d9;
  border-radius: 8px;
  background: #fff;
}

.collect-qr div {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  width: 100%;
  color: #647265;
}

.collect-qr button {
  border: 0;
  background: transparent;
  color: #216b34;
  font-weight: 900;
  text-decoration: underline;
}

.collect-qr strong {
  color: #16231a;
}

.collect-qr small {
  color: #647265;
  font-weight: 700;
}

.modal-panel header,
.modal-panel footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

@media (max-width: 980px) {
  .filters,
  .collect-summary,
  .method-row,
  .timeline-layout,
  .metric-row {
    display: grid;
    grid-template-columns: 1fr;
  }

  .schedule-head {
    display: grid;
  }

  .legend {
    justify-content: flex-start;
  }

  .metric-card + .metric-card {
    border-left: 0;
    border-top: 1px solid #e2e8f0;
  }

  .timeline-inspector {
    position: static;
  }
}
</style>

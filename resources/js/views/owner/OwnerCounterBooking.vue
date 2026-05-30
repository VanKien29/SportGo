<template>
  <div class="space-y-5">
    <!-- Page Header -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-black text-gray-900">Booking tại quầy</h1>
        <p class="text-gray-500 font-medium mt-1">Chọn slot trống trên bảng lịch, nhập khách vãng lai và hình thức thanh toán.</p>
      </div>
      <router-link
        :to="{ name: 'owner-bookings' }"
        class="inline-flex items-center gap-2 h-10 px-5 border border-gray-300 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors flex-shrink-0"
      >📋 Xem lịch booking</router-link>
    </div>

    <!-- Alerts -->
    <div v-if="error" class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 font-bold">{{ error }}</div>
    <div v-if="notice" class="p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 font-bold">{{ notice }}</div>

    <!-- Tabs -->
    <div class="flex gap-2">
      <button
        type="button"
        class="px-5 py-2.5 rounded-xl text-sm font-black transition-colors border"
        :class="activeTab === 'counter' ? 'bg-sportgo-light/40 border-sportgo-accent text-sportgo-accent' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
        @click="activeTab = 'counter'"
      >🏓 Đặt tại quầy</button>
      <button
        type="button"
        class="px-5 py-2.5 rounded-xl text-sm font-black transition-colors border"
        :class="activeTab === 'recurring' ? 'bg-sportgo-light/40 border-sportgo-accent text-sportgo-accent' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
        @click="activeTab = 'recurring'"
      >🔁 Đặt cố định</button>
    </div>

    <!-- Counter Booking -->
    <section v-if="activeTab === 'counter'" class="grid lg:grid-cols-[1fr_360px] gap-5 items-start">
      <!-- Schedule Panel -->
      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-sportgo-light/30 text-sportgo-accent rounded-lg flex items-center justify-center font-black text-sm">1</div>
          <h2 class="text-lg font-black text-gray-900">Chọn lịch sân</h2>
        </div>

        <!-- Filters -->
        <div class="grid sm:grid-cols-4 gap-4">
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-black text-gray-600 uppercase tracking-wider">Cụm sân</span>
            <select v-model="selectedClusterId" class="h-10 px-3 border border-gray-300 rounded-xl text-sm font-medium bg-white focus:ring-2 focus:ring-sportgo-accent focus:border-sportgo-accent" @change="handleClusterChange">
              <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
            </select>
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-black text-gray-600 uppercase tracking-wider">Ngày chơi</span>
            <input v-model="form.booking_date" type="date" :min="today" class="h-10 px-3 border border-gray-300 rounded-xl text-sm font-medium bg-white focus:ring-2 focus:ring-sportgo-accent focus:border-sportgo-accent" @change="loadSchedule" />
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-black text-gray-600 uppercase tracking-wider">Loại sân</span>
            <select v-model="selectedCourtTypeId" class="h-10 px-3 border border-gray-300 rounded-xl text-sm font-medium bg-white focus:ring-2 focus:ring-sportgo-accent focus:border-sportgo-accent" @change="loadSchedule">
              <option value="">Tất cả</option>
              <option v-for="type in courtTypeOptions" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
          </label>
          <label class="flex flex-col gap-1.5">
            <span class="text-xs font-black text-gray-600 uppercase tracking-wider">Thời lượng</span>
            <div class="h-10 px-3 border border-gray-200 rounded-xl text-sm font-bold bg-gray-50 flex items-center text-gray-700">
              {{ selectedDurationText }}
            </div>
          </label>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap gap-4 text-xs font-bold text-gray-600">
          <span class="flex items-center gap-1.5"><i class="w-3.5 h-3.5 rounded border border-gray-300 bg-white inline-block"></i> Trống</span>
          <span class="flex items-center gap-1.5"><i class="w-3.5 h-3.5 rounded bg-green-500 inline-block"></i> Đang chọn</span>
          <span class="flex items-center gap-1.5"><i class="w-3.5 h-3.5 rounded bg-amber-200 inline-block"></i> Đang giữ / Chờ TT</span>
          <span class="flex items-center gap-1.5"><i class="w-3.5 h-3.5 rounded bg-gray-300 inline-block"></i> Đã đặt</span>
        </div>

        <p v-if="selectionError" class="p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-bold">{{ selectionError }}</p>

        <div v-if="scheduleLoading" class="py-12 text-center text-gray-400 font-medium">Đang tải lịch...</div>
        <div v-else-if="scheduleError" class="py-8 text-center text-red-600 font-bold">{{ scheduleError }}</div>
        <div v-else class="overflow-auto border border-gray-200 rounded-xl bg-white">
          <div class="schedule-grid min-w-max" :style="scheduleGridStyle">
            <div class="schedule-head sticky-col">Sân \ Giờ</div>
            <div v-for="slot in scheduleSlots" :key="slot.start_time" class="schedule-head time-head">
              {{ slot.label }}
            </div>
            <template v-for="court in scheduleCourts" :key="court.id">
              <div class="schedule-court sticky-col">
                <strong class="text-xs font-black text-gray-900 block">{{ court.name }}</strong>
                <span class="text-[10px] text-gray-500">{{ court.court_type?.name || '-' }}</span>
              </div>
              <button
                v-for="(slot, index) in scheduleSlots"
                :key="`${court.id}-${slot.start_time}`"
                type="button"
                class="schedule-cell"
                :class="{ busy: isSlotBusy(court.id, slot), selected: isSlotSelected(court.id, index) }"
                :disabled="isSlotBusy(court.id, slot)"
                :title="slotTitle(court.id, slot)"
                @click="selectSlot(court, index)"
              ></button>
            </template>
          </div>
        </div>
      </div>

      <!-- Right Sidebar -->
      <aside class="bg-gray-50 border border-gray-200 rounded-2xl p-6 space-y-6 sticky top-20">
        <!-- Summary -->
        <div class="space-y-3">
          <div class="flex items-center gap-3 pb-3 border-b border-gray-200">
            <div class="w-8 h-8 bg-sportgo-light/30 text-sportgo-accent rounded-lg flex items-center justify-center font-black text-sm">2</div>
            <h2 class="text-base font-black text-gray-700">Tóm tắt dịch vụ</h2>
          </div>
          <div v-if="!hasCounterSelection" class="py-8 text-center border-2 border-dashed border-gray-200 rounded-xl text-gray-400 text-sm font-medium bg-white">
            Chọn một ô trống trên bảng lịch để bắt đầu tạo booking.
          </div>
          <div v-else class="bg-white rounded-xl border border-gray-200 p-4 space-y-2.5 text-sm">
            <div v-for="[label, val] in counterSummaryRows" :key="label" class="flex justify-between gap-3 border-b border-gray-100 pb-2 last:border-0">
              <dt class="text-gray-500 font-medium">{{ label }}</dt>
              <dd class="font-black text-gray-900 text-right">{{ val }}</dd>
            </div>
          </div>
        </div>

        <!-- Customer Info -->
        <div class="space-y-3" :class="{ 'opacity-50 pointer-events-none': !hasCounterSelection }">
          <div class="flex items-center gap-3 pb-3 border-b border-gray-200">
            <div class="w-8 h-8 bg-sportgo-light/30 text-sportgo-accent rounded-lg flex items-center justify-center font-black text-sm">3</div>
            <h2 class="text-base font-black text-gray-700">Thông tin khách</h2>
          </div>
          <div class="space-y-3">
            <label class="flex flex-col gap-1.5">
              <span class="text-xs font-bold text-gray-600">Tên khách hàng *</span>
              <input v-model.trim="form.walk_in_name" type="text" placeholder="Nguyễn Văn A" class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent focus:border-sportgo-accent" />
            </label>
            <label class="flex flex-col gap-1.5">
              <span class="text-xs font-bold text-gray-600">Số điện thoại *</span>
              <input v-model.trim="form.walk_in_phone" type="tel" placeholder="090..." class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent focus:border-sportgo-accent" />
            </label>
          </div>
        </div>

        <!-- Payment -->
        <div class="space-y-3" :class="{ 'opacity-50 pointer-events-none': !hasCounterSelection }">
          <div class="flex items-center gap-3 pb-3 border-b border-gray-200">
            <div class="w-8 h-8 bg-sportgo-light/30 text-sportgo-accent rounded-lg flex items-center justify-center font-black text-sm">4</div>
            <h2 class="text-base font-black text-gray-700">Thanh toán</h2>
          </div>
          <div class="space-y-2">
            <label
              v-for="option in paymentOptions" :key="option.value"
              class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition-colors bg-white"
              :class="form.payment_option === option.value ? 'border-sportgo-accent bg-sportgo-light/10' : 'border-gray-200 hover:border-gray-300'"
            >
              <input v-model="form.payment_option" type="radio" :value="option.value" class="text-sportgo-accent w-4 h-4" />
              <span class="font-bold text-gray-900 text-sm flex-1">{{ option.label }}</span>
              <strong class="text-sportgo-accent font-black text-sm">{{ formatCurrency(option.amount) }}</strong>
            </label>
          </div>
        </div>

        <button
          type="button"
          :disabled="submitting || !canSubmitCounter"
          class="w-full h-12 bg-sportgo-accent hover:bg-sportgo-dark text-white rounded-xl font-black text-sm transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
          @click="submitCounter"
        >{{ submitting ? 'Đang tạo...' : 'Tạo Booking Tại Quầy →' }}</button>
      </aside>
    </section>

    <!-- Recurring Booking -->
    <section v-else class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-sportgo-light/30 text-sportgo-accent rounded-lg flex items-center justify-center font-black text-sm">1</div>
        <h2 class="text-lg font-black text-gray-900">Tạo booking cố định</h2>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Cụm sân</span>
          <select v-model="selectedClusterId" class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent" @change="handleClusterChange">
            <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Sân con</span>
          <select v-model="form.venue_court_id" required class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent">
            <option v-for="court in courts" :key="court.id" :value="court.id">{{ court.name }} · {{ court.court_type?.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Tên khách</span>
          <input v-model.trim="form.walk_in_name" type="text" placeholder="Nguyễn Văn A" class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Số điện thoại</span>
          <input v-model.trim="form.walk_in_phone" type="tel" placeholder="0901234567" class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Ngày bắt đầu</span>
          <input v-model="form.recurring_start_date" type="date" :min="today" required class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Ngày kết thúc</span>
          <input v-model="form.recurring_end_date" type="date" :min="form.recurring_start_date || today" required class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Giờ bắt đầu</span>
          <input v-model="form.start_time" type="time" required class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Giờ kết thúc</span>
          <input v-model="form.end_time" type="time" required class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent" />
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Kiểu lặp</span>
          <select v-model="form.recurrence_type" class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent">
            <option value="daily">Hàng ngày</option>
            <option value="weekly">Hàng tuần</option>
            <option value="monthly">Hàng tháng</option>
          </select>
        </label>
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Hình thức TT</span>
          <select v-model="form.payment_option" required class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent">
            <option value="full_payment">Thanh toán đủ</option>
            <option value="deposit">Đặt cọc</option>
            <option value="no_prepay">Thu sau / Nợ</option>
          </select>
        </label>
      </div>

      <div v-if="form.recurrence_type === 'weekly'" class="flex flex-wrap gap-2">
        <label
          v-for="day in weekDays" :key="day.value"
          class="flex items-center gap-2 px-4 py-2 border-2 rounded-xl cursor-pointer font-bold text-sm transition-colors"
          :class="form.recurrence_days_of_week.includes(day.value) ? 'border-sportgo-accent bg-sportgo-light/20 text-sportgo-accent' : 'border-gray-200 text-gray-600 hover:border-gray-300'"
        >
          <input v-model="form.recurrence_days_of_week" type="checkbox" :value="day.value" class="hidden" />
          {{ day.label }}
        </label>
      </div>

      <label v-if="form.recurrence_type === 'monthly'" class="flex flex-col gap-1.5">
        <span class="text-xs font-bold text-gray-600 uppercase tracking-wider">Ngày trong tháng</span>
        <input v-model="monthDaysInput" type="text" placeholder="Ví dụ: 1, 15, 30" class="h-10 px-3 border border-gray-300 rounded-xl text-sm bg-white focus:ring-2 focus:ring-sportgo-accent" />
      </label>

      <div class="p-4 bg-sportgo-light/20 border border-sportgo-light rounded-xl text-sm">
        <strong class="font-black text-sportgo-accent">{{ recurringPreview.length }} buổi sẽ được tạo</strong>
        <p class="text-gray-600 font-medium mt-1">{{ recurringPreview.slice(0, 10).join(', ') }}{{ recurringPreview.length > 10 ? '...' : '' }}</p>
      </div>

      <div class="flex justify-end">
        <button
          type="button"
          :disabled="submitting || !canSubmitRecurring"
          class="h-12 px-8 bg-sportgo-accent hover:bg-sportgo-dark text-white rounded-xl font-black transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
          @click="submitRecurring"
        >{{ submitting ? 'Đang tạo...' : 'Tạo Booking Cố Định →' }}</button>
      </div>
    </section>
  </div>
</template>


<script>
import { bookingService } from '../../services/bookingService.js';
import { ownerBookingService } from '../../services/ownerBookings.js';
import { venueClusterService } from '../../services/venueClusters.js';

export default {
  name: 'OwnerCounterBooking',
  data() {
    const today = new Date().toISOString().split('T')[0];

    return {
      today,
      activeTab: 'counter',
      clusters: [],
      courts: [],
      selectedClusterId: '',
      selectedClusterDetail: null,
      selectedCourtTypeId: '',
      scheduleSlots: [],
      scheduleCourts: [],
      scheduleSlotStatuses: [],
      selectedGridCourtId: '',
      selectedSlotIndexes: [],
      selectionError: '',
      scheduleLoading: false,
      scheduleError: '',
      monthDaysInput: '1',
      form: {
        venue_court_id: '',
        walk_in_name: '',
        walk_in_phone: '',
        booking_date: today,
        recurring_start_date: today,
        recurring_end_date: today,
        recurrence_type: 'weekly',
        recurrence_interval: 1,
        recurrence_days_of_week: [],
        start_time: '08:00',
        end_time: '09:00',
        payment_option: 'full_payment',
      },
      weekDays: [
        { value: 0, label: 'Thứ 2' },
        { value: 1, label: 'Thứ 3' },
        { value: 2, label: 'Thứ 4' },
        { value: 3, label: 'Thứ 5' },
        { value: 4, label: 'Thứ 6' },
        { value: 5, label: 'Thứ 7' },
        { value: 6, label: 'Chủ nhật' },
      ],
      submitting: false,
      error: '',
      notice: '',
    };
  },
  computed: {
    selectedCluster() {
      return this.clusters.find((cluster) => cluster.id === this.selectedClusterId) || null;
    },
    selectedCourt() {
      return this.scheduleCourts.find((court) => court.id === this.selectedGridCourtId)
        || this.courts.find((court) => court.id === this.form.venue_court_id)
        || null;
    },
    selectedCounterCourt() {
      return this.scheduleCourts.find((court) => court.id === this.selectedGridCourtId) || null;
    },
    hasCounterSelection() {
      return Boolean(this.selectedCounterCourt && this.selectedSlotIndexes.length > 0);
    },
    courtTypeOptions() {
      const map = new Map();
      this.courts.forEach((court) => {
        if (court.court_type?.id) map.set(court.court_type.id, court.court_type);
      });
      return [...map.values()].sort((a, b) => a.name.localeCompare(b.name));
    },
    scheduleGridStyle() {
      return {
        gridTemplateColumns: `150px repeat(${this.scheduleSlots.length}, 34px)`,
      };
    },
    selectedDurationMinutes() {
      return this.selectedSlotIndexes.length * 30;
    },
    selectedDurationText() {
      if (!this.selectedDurationMinutes) return 'Chưa chọn';
      return `${this.selectedDurationMinutes} phút`;
    },
    selectedTimeText() {
      if (!this.selectedSlotIndexes.length) return '-';
      const indexes = [...this.selectedSlotIndexes].sort((a, b) => a - b);
      const start = this.scheduleSlots[indexes[0]]?.start_time;
      const end = this.scheduleSlots[indexes[indexes.length - 1]]?.end_time;
      return `${this.formatTime(start)} - ${this.formatTime(end)}`;
    },
    selectedTotal() {
      return this.selectedSlotIndexes.reduce((total, index) => {
        const slot = this.scheduleSlots[index];
        const status = this.slotStatus(this.selectedGridCourtId, slot);
        return total + Number(status?.price || 0);
      }, 0);
    },
    depositPercent() {
      return Number(this.selectedClusterDetail?.booking_config?.deposit_percent || 50);
    },
    paymentOptions() {
      return [
        { value: 'full_payment', label: 'Thanh toán đủ', amount: this.selectedTotal },
        { value: 'deposit', label: `Cọc ${this.depositPercent}%`, amount: Math.round(this.selectedTotal * this.depositPercent / 100) },
        { value: 'no_prepay', label: 'Thu sau / Nợ', amount: 0 },
      ];
    },
    recurringPreview() {
      if (this.activeTab !== 'recurring') return [];
      const start = new Date(this.form.recurring_start_date);
      const end = new Date(this.form.recurring_end_date);
      if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) return [];

      const dates = [];
      const selectedMonthDays = this.monthDaysInput
        .split(',')
        .map((item) => Number(item.trim()))
        .filter((day) => day >= 1 && day <= 31);

      for (let date = new Date(start); date <= end && dates.length <= 130; date.setDate(date.getDate() + 1)) {
        const current = new Date(date);
        const dayDiff = Math.floor((current - start) / 86400000);
        const weekDiff = Math.floor(dayDiff / 7);
        const monthDiff = (current.getFullYear() - start.getFullYear()) * 12 + (current.getMonth() - start.getMonth());
        let match = false;

        if (this.form.recurrence_type === 'daily') {
          match = dayDiff % this.form.recurrence_interval === 0;
        } else if (this.form.recurrence_type === 'weekly') {
          match = weekDiff % this.form.recurrence_interval === 0 && this.form.recurrence_days_of_week.includes(this.dayIndex(current));
        } else {
          match = monthDiff % this.form.recurrence_interval === 0 && selectedMonthDays.includes(current.getDate());
        }

        if (match) dates.push(current.toISOString().split('T')[0]);
      }

      return dates;
    },
    canSubmitCounter() {
      return this.hasCounterSelection
        && this.form.walk_in_name
        && this.form.walk_in_phone
        && !this.submitting;
    },
    canSubmitRecurring() {
      return this.form.venue_court_id
        && this.form.walk_in_name
        && this.form.walk_in_phone
        && this.timeToMinutes(this.form.end_time) > this.timeToMinutes(this.form.start_time)
        && this.recurringPreview.length > 0
        && !this.submitting;
    },
  },
  async created() {
    await this.loadOwnerData();
  },
  methods: {
    async loadOwnerData() {
      this.error = '';

      try {
        const response = await venueClusterService.getClusters();
        this.clusters = response.data || [];
        this.selectedClusterId = this.$route.query.venue_cluster_id || this.clusters[0]?.id || '';
        await this.handleClusterChange();
      } catch (error) {
        this.error = error.message || 'Không thể tải dữ liệu cụm sân.';
      }
    },
    async handleClusterChange() {
      this.selectedCourtTypeId = '';
      this.selectedSlotIndexes = [];
      this.selectedGridCourtId = '';
      this.form.venue_court_id = '';
      if (!this.selectedClusterId) return;

      await Promise.all([this.loadClusterDetail(), this.loadCourts()]);
      await this.loadSchedule();
    },
    async loadClusterDetail() {
      try {
        const response = await venueClusterService.getClusterDetails(this.selectedClusterId);
        this.selectedClusterDetail = response.data || null;
      } catch {
        this.selectedClusterDetail = null;
      }
    },
    async loadCourts() {
      const response = await venueClusterService.getCourts(this.selectedClusterId, { status: 'active' });
      this.courts = response.data || [];
      this.form.venue_court_id = this.courts[0]?.id || '';
    },
    async loadSchedule() {
      if (!this.selectedClusterId) return;

      this.scheduleLoading = true;
      this.scheduleError = '';
      this.selectionError = '';
      this.selectedSlotIndexes = [];
      this.selectedGridCourtId = '';

      try {
        const response = await bookingService.getSchedule({
          venue_cluster_id: this.selectedClusterId,
          booking_date: this.form.booking_date,
          court_type_id: this.selectedCourtTypeId,
          booking_type: 'single',
        });

        this.scheduleSlots = response.time_slots || [];
        this.scheduleCourts = response.courts || [];
        this.scheduleSlotStatuses = response.slot_statuses || [];
      } catch (error) {
        this.scheduleError = error.message || 'Không thể tải lịch sân.';
      } finally {
        this.scheduleLoading = false;
      }
    },
    slotStatus(courtId, slot) {
      if (!slot) return null;
      return this.scheduleSlotStatuses.find((status) => status.venue_court_id === courtId && status.start_time === slot.start_time) || null;
    },
    isSlotBusy(courtId, slot) {
      const status = this.slotStatus(courtId, slot);
      return !status || !status.is_available;
    },
    isSlotSelected(courtId, index) {
      return this.selectedGridCourtId === courtId && this.selectedSlotIndexes.includes(index);
    },
    slotTitle(courtId, slot) {
      const status = this.slotStatus(courtId, slot);
      if (!status) return 'Không có dữ liệu';
      if (!status.is_available) return 'Đã đặt hoặc đang giữ';
      return `${this.formatTime(slot.start_time)} - ${this.formatTime(slot.end_time)} · ${this.formatCurrency(status.price)}`;
    },
    selectSlot(court, index) {
      this.selectionError = '';

      if (this.selectedGridCourtId && this.selectedGridCourtId !== court.id) {
        this.selectedSlotIndexes = [];
      }

      this.selectedGridCourtId = court.id;
      const selected = [...this.selectedSlotIndexes].sort((a, b) => a - b);

      if (selected.length === 0) {
        this.selectedSlotIndexes = [index];
      } else if (selected.includes(index)) {
        const min = selected[0];
        const max = selected[selected.length - 1];

        if (index === min || index === max) {
          this.selectedSlotIndexes = selected.filter((item) => item !== index);
        } else {
          this.selectionError = 'Vui lòng chọn các khung giờ liên tiếp. Nếu muốn đặt nhiều khung giờ khác nhau, hãy tạo từng đơn đặt sân riêng.';
          return;
        }
      } else if (index === selected[0] - 1 || index === selected[selected.length - 1] + 1) {
        this.selectedSlotIndexes = [...selected, index].sort((a, b) => a - b);
      } else {
        this.selectionError = 'Vui lòng chọn các khung giờ liên tiếp. Nếu muốn đặt nhiều khung giờ khác nhau, hãy tạo từng đơn đặt sân riêng.';
        return;
      }

      const indexes = [...this.selectedSlotIndexes].sort((a, b) => a - b);
      this.form.venue_court_id = this.selectedGridCourtId;

      if (indexes.length) {
        this.form.start_time = this.formatTime(this.scheduleSlots[indexes[0]]?.start_time);
        this.form.end_time = this.formatTime(this.scheduleSlots[indexes[indexes.length - 1]]?.end_time);
      }
    },
    async submitCounter() {
      if (!this.canSubmitCounter) return;

      this.submitting = true;
      this.error = '';
      this.notice = '';

      try {
        await ownerBookingService.createCounter({
          venue_court_id: this.selectedGridCourtId,
          walk_in_name: this.form.walk_in_name,
          walk_in_phone: this.form.walk_in_phone,
          booking_date: this.form.booking_date,
          start_time: this.withSeconds(this.form.start_time),
          end_time: this.withSeconds(this.form.end_time),
          payment_option: this.form.payment_option,
        });

        this.notice = 'Đã tạo booking tại quầy.';
        this.selectedSlotIndexes = [];
        this.selectedGridCourtId = '';
        await this.loadSchedule();
      } catch (error) {
        this.error = error.message || 'Không thể tạo booking tại quầy.';
      } finally {
        this.submitting = false;
      }
    },
    async submitRecurring() {
      if (!this.canSubmitRecurring) return;

      this.submitting = true;
      this.error = '';
      this.notice = '';

      try {
        await ownerBookingService.createRecurring({
          venue_court_id: this.form.venue_court_id,
          walk_in_name: this.form.walk_in_name,
          walk_in_phone: this.form.walk_in_phone,
          start_time: this.withSeconds(this.form.start_time),
          end_time: this.withSeconds(this.form.end_time),
          payment_option: this.form.payment_option,
          recurring_start_date: this.form.recurring_start_date,
          recurring_end_date: this.form.recurring_end_date,
          recurrence_type: this.form.recurrence_type,
          recurrence_interval: this.form.recurrence_interval,
          recurrence_days_of_week: this.form.recurrence_days_of_week,
          recurrence_days_of_month: this.monthDaysInput.split(',').map((item) => Number(item.trim())).filter(Boolean),
        });

        this.notice = 'Đã tạo booking cố định.';
      } catch (error) {
        this.error = error.message || 'Không thể tạo booking cố định.';
      } finally {
        this.submitting = false;
      }
    },
    withSeconds(time) {
      return time.length === 5 ? `${time}:00` : time;
    },
    formatTime(time) {
      return (time || '').slice(0, 5);
    },
    timeToMinutes(time) {
      const [hour, minute] = this.formatTime(time).split(':').map(Number);
      return (hour || 0) * 60 + (minute || 0);
    },
    dayIndex(value) {
      const date = value instanceof Date ? value : new Date(value);
      return (date.getDay() + 6) % 7;
    },
    formatDate(value) {
      if (!value) return '-';
      return new Intl.DateTimeFormat('vi-VN').format(new Date(value));
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(Number(amount || 0));
    },
  },
};
</script>

<style scoped>
.counter-page {
  display: grid;
  gap: 18px;
  max-width: 1180px;
  margin: 0 auto;
}

.page-head,
.schedule-panel,
.booking-side,
.form-card,
.alert,
.preview-box {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.page-head {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  align-items: center;
  padding: 20px;
}

.page-head h1,
.section-title h2 {
  margin: 0;
  color: #0f172a;
  font-weight: 900;
}

.page-head p {
  margin: 6px 0 0;
  color: #64748b;
}

.ghost-link {
  height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  color: #334155;
  font-weight: 900;
}

.alert {
  padding: 13px 14px;
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

.tabs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.tabs button {
  padding: 10px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #334155;
  font-weight: 900;
}

.tabs button.active {
  border-color: #16a34a;
  background: #dcfce7;
  color: #166534;
}

.counter-board {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 16px;
  align-items: start;
}

.schedule-panel,
.booking-side,
.form-card {
  padding: 20px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.section-title span {
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: #dcfce7;
  color: #15803d;
  font-size: 13px;
  font-weight: 900;
}

.section-title.muted {
  margin-bottom: 12px;
}

.section-title.muted h2 {
  font-size: 15px;
  color: #64748b;
}

.schedule-filters,
.form-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

label {
  display: grid;
  gap: 7px;
}

label span,
.summary-list dt {
  color: #334155;
  font-size: 13px;
  font-weight: 900;
}

input,
select {
  width: 100%;
  height: 42px;
  padding: 0 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #0f172a;
}

input[readonly] {
  background: #f8fafc;
  color: #64748b;
}

.legend {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin: 16px 0;
  color: #475569;
  font-size: 12px;
  font-weight: 800;
}

.legend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.legend i {
  width: 11px;
  height: 11px;
  border: 1px solid #cbd5e1;
  border-radius: 3px;
  background: #fff;
}

.legend i.selected {
  border-color: #059669;
  background: #059669;
}

.legend i.pending {
  border-color: #fde68a;
  background: #fef3c7;
}

.legend i.busy {
  border-color: #cbd5e1;
  background: #e2e8f0;
}

.selection-error,
.state-inline.error {
  color: #b91c1c;
  font-weight: 800;
}

.state-inline {
  padding: 18px;
  color: #64748b;
  text-align: center;
}

.schedule-wrap {
  max-width: 100%;
  overflow: auto;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.schedule-grid {
  display: grid;
  min-width: max-content;
}

.schedule-head,
.schedule-court,
.schedule-cell {
  min-height: 34px;
  border-right: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
}

.schedule-head {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  color: #334155;
  font-size: 11px;
  font-weight: 900;
}

.sticky-col {
  position: sticky;
  left: 0;
  z-index: 2;
}

.schedule-court {
  display: grid;
  align-content: center;
  gap: 3px;
  padding: 8px 10px;
  background: #fff;
}

.schedule-court strong {
  color: #0f172a;
  font-size: 12px;
}

.schedule-court span {
  color: #64748b;
  font-size: 11px;
}

.schedule-cell {
  background: #fff;
}

.schedule-cell:hover:not(:disabled) {
  background: #dcfce7;
}

.schedule-cell.selected {
  background: #059669;
}

.schedule-cell.busy {
  background: #e2e8f0;
}

.schedule-cell:disabled {
  cursor: not-allowed;
}

.booking-side {
  position: sticky;
  top: 88px;
  display: grid;
  gap: 18px;
}

.side-section {
  display: grid;
  gap: 12px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e2e8f0;
}

.empty-summary {
  display: grid;
  place-items: center;
  min-height: 88px;
  padding: 16px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  color: #94a3b8;
  text-align: center;
  line-height: 1.45;
}

.summary-list {
  display: grid;
  gap: 8px;
  margin: 0;
}

.summary-list div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.summary-list dd {
  margin: 0;
  color: #0f172a;
  font-weight: 900;
  text-align: right;
}

.payment-card {
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.payment-card.active {
  border-color: #10b981;
  background: #ecfdf5;
}

.payment-card input {
  width: 16px;
  height: 16px;
  accent-color: #059669;
}

.payment-card strong {
  color: #059669;
}

.primary-btn {
  height: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 16px;
  border-radius: 8px;
  background: #16a34a;
  color: #fff;
  font-weight: 900;
}

.primary-btn.full {
  width: 100%;
}

.primary-btn:disabled {
  opacity: .58;
  cursor: not-allowed;
}

.form-card {
  display: grid;
  gap: 16px;
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
  padding: 8px 10px;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
}

.day-grid label.selected {
  border-color: #16a34a;
  background: #dcfce7;
  color: #166534;
}

.day-grid input {
  width: 16px;
  height: 16px;
  accent-color: #16a34a;
}

.preview-box {
  display: grid;
  gap: 4px;
  padding: 12px;
}

.preview-box span {
  color: #64748b;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
}

@media (max-width: 1080px) {
  .counter-board {
    grid-template-columns: 1fr;
  }

  .booking-side {
    position: static;
  }
}

@media (max-width: 780px) {
  .page-head,
  .schedule-filters,
  .form-grid {
    grid-template-columns: 1fr;
    display: grid;
  }
}
</style>

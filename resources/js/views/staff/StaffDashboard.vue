<template>
  <section class="staff-page">
    <header class="staff-page-header">
      <div>
        <p class="staff-eyebrow">Hôm nay</p>
        <h1>Ca trực của bạn</h1>
        <p>{{ formattedToday }}</p>
      </div>
      <button class="staff-icon-button" type="button" title="Tải lại lịch trực" :disabled="loading" @click="loadSchedules">
        <AppIcon name="refresh" size="17" />
      </button>
    </header>

    <p v-if="error" class="staff-alert staff-alert-error">{{ error }}</p>
    <p v-if="success" class="staff-alert staff-alert-success">{{ success }}</p>

    <div v-if="loading" class="staff-loading">Đang tải lịch trực...</div>

    <template v-else>
      <section v-if="activeSchedule" class="staff-current-shift">
        <div class="staff-current-shift-head">
          <div>
            <p class="staff-eyebrow">{{ statusLabel(activeSchedule.status) }}</p>
            <h2>{{ shiftName(activeSchedule) }}</h2>
          </div>
          <span class="staff-time">{{ timeRange(activeSchedule) }}</span>
        </div>
        <div class="staff-shift-meta">
          <span><AppIcon name="building" size="15" />{{ activeSchedule.venue_cluster?.name || 'Cụm sân được phân công' }}</span>
          <span v-if="activeSchedule.notes"><AppIcon name="fileText" size="15" />{{ activeSchedule.notes }}</span>
        </div>
        <button
          v-if="activeSchedule.status !== 'checked_out'"
          class="staff-primary-action"
          type="button"
          :disabled="actionLoading === activeSchedule.id"
          @click="handleAttendance(activeSchedule)"
        >
          <AppIcon :name="activeSchedule.status === 'checked_in' ? 'logOut' : 'check'" size="17" />
          {{ activeSchedule.status === 'checked_in' ? 'Kết thúc ca' : 'Vào ca' }}
        </button>
      </section>

      <section v-else class="staff-empty-shift">
        <AppIcon name="calendar" size="22" />
        <div>
          <h2>Không có ca trực hôm nay</h2>
          <p>Lịch trực tiếp theo sẽ hiển thị bên dưới.</p>
        </div>
      </section>

      <section class="staff-overview">
        <article>
          <span>Ca hôm nay</span>
          <strong>{{ todaySchedules.length }}</strong>
        </article>
        <article>
          <span>Ca trong tuần</span>
          <strong>{{ schedules.length }}</strong>
        </article>
        <article>
          <span>Đã hoàn thành</span>
          <strong>{{ completedCount }}</strong>
        </article>
      </section>

      <section class="staff-list-section">
        <div class="staff-section-heading">
          <div>
            <p class="staff-eyebrow">Sắp tới</p>
            <h2>Lịch trực gần nhất</h2>
          </div>
          <RouterLink to="/staff/schedules">Xem lịch tuần</RouterLink>
        </div>
        <div v-if="upcomingSchedules.length" class="staff-schedule-list">
          <article v-for="schedule in upcomingSchedules" :key="schedule.id" class="staff-schedule-row">
            <time>{{ scheduleDate(schedule.date) }}</time>
            <div>
              <strong>{{ timeRange(schedule) }}</strong>
              <span>{{ shiftName(schedule) }} · {{ schedule.venue_cluster?.name || 'Cụm sân' }}</span>
            </div>
            <span class="staff-status" :class="schedule.status">{{ statusLabel(schedule.status) }}</span>
          </article>
        </div>
        <p v-else class="staff-muted">Bạn chưa có ca trực nào trong tuần này.</p>
      </section>
    </template>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { ownerStaffShiftService } from '../../services/ownerStaffShiftService.js';

export default {
  name: 'StaffDashboard',
  components: { AppIcon },
  data() {
    return { schedules: [], loading: true, actionLoading: null, error: '', success: '' };
  },
  computed: {
    today() {
      return this.formatIsoDate(new Date());
    },
    formattedToday() {
      return new Intl.DateTimeFormat('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date());
    },
    todaySchedules() {
      return this.schedules.filter((schedule) => schedule.date === this.today);
    },
    activeSchedule() {
      return this.todaySchedules.find((schedule) => ['checked_in', 'scheduled'].includes(schedule.status)) || this.todaySchedules[0] || null;
    },
    upcomingSchedules() {
      return this.schedules.filter((schedule) => schedule.date >= this.today).slice(0, 5);
    },
    completedCount() {
      return this.schedules.filter((schedule) => schedule.status === 'checked_out').length;
    },
  },
  mounted() {
    this.loadSchedules();
  },
  methods: {
    formatIsoDate(date) {
      const offset = date.getTimezoneOffset();
      return new Date(date.getTime() - (offset * 60000)).toISOString().slice(0, 10);
    },
    async loadSchedules() {
      this.loading = true;
      this.error = '';
      try {
        const start = new Date();
        start.setDate(start.getDate() - 1);
        const end = new Date();
        end.setDate(end.getDate() + 6);
        const response = await ownerStaffShiftService.mySchedules({
          start_date: this.formatIsoDate(start),
          end_date: this.formatIsoDate(end),
        });
        this.schedules = response.data || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải lịch trực. Vui lòng thử lại.';
      } finally {
        this.loading = false;
      }
    },
    async handleAttendance(schedule) {
      this.actionLoading = schedule.id;
      this.error = '';
      this.success = '';
      try {
        if (schedule.status === 'checked_in') {
          await ownerStaffShiftService.checkOut(schedule.id);
          this.success = 'Đã kết thúc ca trực.';
        } else {
          await ownerStaffShiftService.checkIn(schedule.id);
          this.success = 'Đã vào ca thành công.';
        }
        await this.loadSchedules();
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật chấm công.';
      } finally {
        this.actionLoading = null;
      }
    },
    shiftName(schedule) {
      return schedule.shift?.name || 'Ca trực';
    },
    timeRange(schedule) {
      return `${String(schedule.start_time || '').slice(0, 5)} - ${String(schedule.end_time || '').slice(0, 5)}`;
    },
    scheduleDate(value) {
      return new Intl.DateTimeFormat('vi-VN', { weekday: 'short', day: '2-digit', month: '2-digit' }).format(new Date(`${value}T00:00:00`));
    },
    statusLabel(status) {
      return { scheduled: 'Đã lên lịch', checked_in: 'Đang trực', checked_out: 'Đã hoàn thành', absent: 'Vắng mặt', cancelled: 'Đã hủy' }[status] || 'Đã lên lịch';
    },
  },
};
</script>

<style scoped>
.staff-page { max-width: 1120px; margin: 0 auto; color: var(--admin-text); }
.staff-page-header, .staff-section-heading, .staff-current-shift-head, .staff-schedule-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.staff-page-header { margin-bottom: 24px; }
.staff-page h1, .staff-page h2, .staff-page p { margin: 0; }
.staff-page h1 { font-size: 22px; font-weight: 500; line-height: 1.25; }
.staff-page h2 { font-size: 16px; font-weight: 500; line-height: 1.35; }
.staff-page-header > div > p:last-child, .staff-muted, .staff-schedule-row span { color: var(--admin-muted); font-size: 13px; margin-top: 5px; }
.staff-eyebrow { color: var(--admin-faint); font-size: 11px; font-weight: 500; letter-spacing: .06em; text-transform: uppercase; }
.staff-icon-button { width: 38px; height: 38px; display: inline-grid; place-items: center; border: 1px solid var(--admin-border); border-radius: var(--admin-radius); background: transparent; color: var(--admin-text); cursor: pointer; }
.staff-icon-button:focus-visible, .staff-primary-action:focus-visible, .staff-list-section a:focus-visible { outline: 2px solid var(--admin-focus-border); outline-offset: 2px; }
.staff-alert { margin: 0 0 16px; padding: 10px 12px; border-radius: var(--admin-radius); font-size: 13px; }
.staff-alert-error { color: var(--admin-danger); background: var(--admin-danger-soft); }
.staff-alert-success { color: var(--admin-primary); background: var(--admin-primary-soft); }
.staff-loading, .staff-empty-shift { min-height: 124px; display: flex; align-items: center; gap: 14px; border-top: 1px solid var(--admin-border); border-bottom: 1px solid var(--admin-border); color: var(--admin-muted); }
.staff-empty-shift h2 { color: var(--admin-text); }
.staff-empty-shift p { margin-top: 4px; font-size: 13px; }
.staff-current-shift { padding: 22px 0; border-top: 1px solid var(--admin-border); border-bottom: 1px solid var(--admin-border); }
.staff-current-shift-head { align-items: flex-start; }
.staff-time { color: var(--admin-text); font-size: 16px; font-weight: 500; white-space: nowrap; }
.staff-shift-meta { display: flex; flex-wrap: wrap; gap: 12px 20px; margin-top: 16px; color: var(--admin-muted); font-size: 13px; }
.staff-shift-meta span { display: inline-flex; align-items: center; gap: 7px; }
.staff-primary-action { min-height: 42px; margin-top: 20px; padding: 0 14px; border: 1px solid var(--admin-primary); border-radius: var(--admin-radius); background: var(--admin-primary); color: var(--admin-primary-contrast); display: inline-flex; align-items: center; gap: 8px; font: inherit; cursor: pointer; }
.staff-primary-action:disabled, .staff-icon-button:disabled { opacity: .55; cursor: wait; }
.staff-overview { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1px; margin: 28px 0; border: 1px solid var(--admin-border); background: var(--admin-border); }
.staff-overview article { min-height: 92px; padding: 16px; background: var(--admin-surface); display: grid; align-content: space-between; }
.staff-overview span { color: var(--admin-muted); font-size: 12px; }
.staff-overview strong { font-size: 23px; font-weight: 500; }
.staff-list-section { padding-top: 4px; }
.staff-section-heading { margin-bottom: 12px; }
.staff-section-heading a { color: var(--admin-primary); font-size: 13px; text-decoration: none; }
.staff-schedule-list { border-top: 1px solid var(--admin-border); }
.staff-schedule-row { min-height: 74px; border-bottom: 1px solid var(--admin-border); }
.staff-schedule-row time { width: 88px; color: var(--admin-muted); font-size: 12px; text-transform: capitalize; }
.staff-schedule-row > div { flex: 1; min-width: 0; }
.staff-schedule-row strong { display: block; font-size: 14px; font-weight: 500; }
.staff-schedule-row span { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.staff-status { flex: 0 0 auto; margin: 0 !important; padding: 3px 7px; border-radius: 4px; font-size: 11px !important; }
.staff-status.scheduled { color: var(--admin-muted); background: var(--admin-bg-soft); }
.staff-status.checked_in { color: var(--admin-primary); background: var(--admin-primary-soft); }
.staff-status.checked_out { color: var(--admin-muted); background: var(--admin-bg-soft); }
@media (max-width: 640px) { .staff-page { padding-bottom: 12px; } .staff-page h1 { font-size: 20px; } .staff-overview { margin: 22px 0; } .staff-overview article { min-height: 78px; padding: 12px; } .staff-current-shift { padding: 18px 0; } .staff-schedule-row { align-items: flex-start; padding: 13px 0; } .staff-schedule-row time { width: 62px; font-size: 11px; } .staff-status { display: none; } }
</style>
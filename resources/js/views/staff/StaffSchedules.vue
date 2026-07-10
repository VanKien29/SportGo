<template>
  <section class="staff-schedules-page">
    <header class="staff-schedules-head">
      <div>
        <p class="staff-schedules-eyebrow">Lịch trực cá nhân</p>
        <h1>{{ weekLabel }}</h1>
      </div>
      <div class="staff-week-actions">
        <button type="button" title="Tuần trước" @click="shiftWeek(-1)"><AppIcon name="chevronLeft" size="17" /></button>
        <button type="button" title="Tuần này" class="staff-week-today" @click="goToCurrentWeek">Tuần này</button>
        <button type="button" title="Tuần sau" @click="shiftWeek(1)"><AppIcon name="chevronRight" size="17" /></button>
      </div>
    </header>

    <p v-if="error" class="staff-schedules-alert">{{ error }}</p>
    <div v-if="loading" class="staff-schedules-loading">Đang tải lịch trực...</div>

    <template v-else>
      <div class="staff-week-summary">
        <span>{{ schedules.length }} ca trực</span>
        <span>{{ uniqueVenues }} cụm sân</span>
      </div>
      <div class="staff-week-grid" role="list" aria-label="Lịch trực trong tuần">
        <article v-for="day in weekDays" :key="day.iso" class="staff-day" :class="{ 'is-today': day.iso === today }" role="listitem">
          <header><span>{{ day.label }}</span><strong>{{ day.date }}</strong></header>
          <div v-if="day.schedules.length" class="staff-day-shifts">
            <div v-for="schedule in day.schedules" :key="schedule.id" class="staff-day-shift">
              <span class="staff-day-time">{{ timeRange(schedule) }}</span>
              <strong>{{ shiftName(schedule) }}</strong>
              <small>{{ schedule.venue_cluster?.name || 'Cụm sân' }}</small>
              <span class="staff-day-status" :class="schedule.status">{{ statusLabel(schedule.status) }}</span>
            </div>
          </div>
          <p v-else>Không có ca</p>
        </article>
      </div>
      <section v-if="schedules.length" class="staff-schedules-list">
        <h2>Tất cả ca trong tuần</h2>
        <article v-for="schedule in schedules" :key="schedule.id" class="staff-schedule-item">
          <time>{{ fullDate(schedule.date) }}</time>
          <div><strong>{{ timeRange(schedule) }} · {{ shiftName(schedule) }}</strong><span>{{ schedule.venue_cluster?.name || 'Cụm sân được phân công' }}</span><small v-if="schedule.notes">{{ schedule.notes }}</small></div>
          <span class="staff-day-status" :class="schedule.status">{{ statusLabel(schedule.status) }}</span>
        </article>
      </section>
    </template>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { ownerStaffShiftService } from '../../services/ownerStaffShiftService.js';
export default {
  name: 'StaffSchedules', components: { AppIcon },
  data() { return { weekStart: this.getMonday(new Date()), schedules: [], loading: true, error: '' }; },
  computed: {
    today() { return this.isoDate(new Date()); },
    weekDays() { const labels = ['Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy', 'Chủ nhật']; return Array.from({ length: 7 }, (_, index) => { const date = new Date(this.weekStart); date.setDate(date.getDate() + index); const iso = this.isoDate(date); return { iso, label: labels[index], date: String(date.getDate()).padStart(2, '0'), schedules: this.schedules.filter((schedule) => schedule.date === iso) }; }); },
    weekLabel() { const end = new Date(this.weekStart); end.setDate(end.getDate() + 6); const format = new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }); return `${format.format(this.weekStart)} - ${format.format(end)}`; },
    uniqueVenues() { return new Set(this.schedules.map((schedule) => schedule.venue_cluster?.id || schedule.venue_cluster?.name).filter(Boolean)).size; },
  },
  mounted() { this.loadSchedules(); },
  methods: {
    getMonday(date) { const value = new Date(date); const offset = value.getDay() === 0 ? -6 : 1 - value.getDay(); value.setDate(value.getDate() + offset); value.setHours(0, 0, 0, 0); return value; },
    isoDate(date) { const offset = date.getTimezoneOffset(); return new Date(date.getTime() - (offset * 60000)).toISOString().slice(0, 10); },
    shiftWeek(amount) { const next = new Date(this.weekStart); next.setDate(next.getDate() + (amount * 7)); this.weekStart = next; this.loadSchedules(); },
    goToCurrentWeek() { this.weekStart = this.getMonday(new Date()); this.loadSchedules(); },
    async loadSchedules() { this.loading = true; this.error = ''; try { const end = new Date(this.weekStart); end.setDate(end.getDate() + 6); const response = await ownerStaffShiftService.mySchedules({ start_date: this.isoDate(this.weekStart), end_date: this.isoDate(end) }); this.schedules = (response.data || []).sort((a, b) => `${a.date} ${a.start_time}`.localeCompare(`${b.date} ${b.start_time}`)); } catch (error) { this.error = error.message || 'Không thể tải lịch trực. Vui lòng thử lại.'; } finally { this.loading = false; } },
    shiftName(schedule) { return schedule.shift?.name || 'Ca trực'; },
    timeRange(schedule) { return `${String(schedule.start_time || '').slice(0, 5)} - ${String(schedule.end_time || '').slice(0, 5)}`; },
    fullDate(value) { return new Intl.DateTimeFormat('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit' }).format(new Date(`${value}T00:00:00`)); },
    statusLabel(status) { return { scheduled: 'Đã lên lịch', checked_in: 'Đang trực', checked_out: 'Đã hoàn thành', absent: 'Vắng mặt', cancelled: 'Đã hủy' }[status] || 'Đã lên lịch'; },
  },
};
</script>

<style scoped>
.staff-schedules-page { max-width: 1120px; margin: 0 auto; color: var(--admin-text); }.staff-schedules-head,.staff-week-actions,.staff-schedule-item { display:flex; align-items:center; gap:8px; }.staff-schedules-head { justify-content:space-between; margin-bottom:24px; }.staff-schedules-page h1,.staff-schedules-page h2,.staff-schedules-page p { margin:0; }.staff-schedules-page h1 { font-size:22px; font-weight:500; }.staff-schedules-page h2 { font-size:16px; font-weight:500; }.staff-schedules-eyebrow { color:var(--admin-faint); font-size:11px; font-weight:500; letter-spacing:.06em; text-transform:uppercase; margin-bottom:4px !important; }.staff-week-actions button { min-width:38px; height:38px; border:1px solid var(--admin-border); border-radius:var(--admin-radius); background:transparent; color:var(--admin-text); display:inline-grid; place-items:center; font:inherit; cursor:pointer; }.staff-week-actions .staff-week-today { padding:0 12px; font-size:13px; }.staff-week-actions button:focus-visible { outline:2px solid var(--admin-focus-border); outline-offset:2px; }.staff-schedules-alert { margin-bottom:16px !important; padding:10px 12px; color:var(--admin-danger); background:var(--admin-danger-soft); border-radius:var(--admin-radius); font-size:13px; }.staff-schedules-loading { min-height:160px; display:grid; place-items:center; border-top:1px solid var(--admin-border); border-bottom:1px solid var(--admin-border); color:var(--admin-muted); font-size:13px; }.staff-week-summary { display:flex; gap:16px; padding:12px 0; border-top:1px solid var(--admin-border); border-bottom:1px solid var(--admin-border); color:var(--admin-muted); font-size:13px; }.staff-week-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); border-bottom:1px solid var(--admin-border); }.staff-day { min-height:236px; padding:14px 12px; border-right:1px solid var(--admin-border); }.staff-day:first-child { border-left:1px solid var(--admin-border); }.staff-day.is-today { background:var(--admin-primary-soft); }.staff-day header { display:flex; flex-direction:column; gap:3px; margin-bottom:14px; }.staff-day header span { color:var(--admin-muted); font-size:11px; }.staff-day header strong { font-size:18px; font-weight:500; }.staff-day > p { color:var(--admin-faint); font-size:12px; }.staff-day-shifts { display:grid; gap:12px; }.staff-day-shift { display:grid; gap:3px; }.staff-day-time { color:var(--admin-text); font-size:12px; font-weight:500; }.staff-day-shift strong { font-size:13px; font-weight:500; line-height:1.35; }.staff-day-shift small { color:var(--admin-muted); font-size:11px; line-height:1.35; }.staff-day-status { display:inline-flex; width:fit-content; margin-top:3px; padding:2px 5px; border-radius:4px; background:var(--admin-bg-soft); color:var(--admin-muted); font-size:10px; }.staff-day-status.checked_in { background:var(--admin-primary-soft); color:var(--admin-primary); }.staff-schedules-list { margin-top:28px; }.staff-schedules-list h2 { margin-bottom:10px; }.staff-schedule-item { min-height:74px; border-top:1px solid var(--admin-border); }.staff-schedule-item:last-child { border-bottom:1px solid var(--admin-border); }.staff-schedule-item time { width:120px; color:var(--admin-muted); font-size:12px; text-transform:capitalize; }.staff-schedule-item > div { flex:1; display:grid; gap:3px; min-width:0; }.staff-schedule-item strong { font-size:14px; font-weight:500; }.staff-schedule-item span,.staff-schedule-item small { color:var(--admin-muted); font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }.staff-schedule-item > .staff-day-status { flex:0 0 auto; }@media (max-width:820px) { .staff-week-grid { grid-template-columns:1fr; border-top:1px solid var(--admin-border); }.staff-day { min-height:0; padding:13px 0; border-left:0 !important; border-right:0; border-bottom:1px solid var(--admin-border); }.staff-day:last-child { border-bottom:0; }.staff-day.is-today { background:transparent; }.staff-day header { flex-direction:row; align-items:baseline; gap:8px; margin-bottom:10px; }.staff-day header strong { font-size:15px; }.staff-day-shifts { gap:10px; }.staff-day-shift { grid-template-columns:100px 1fr; column-gap:10px; }.staff-day-shift > :not(.staff-day-time) { grid-column:2; }.staff-schedules-list { margin-top:24px; } }@media (max-width:540px) { .staff-schedules-head { align-items:flex-start; flex-direction:column; gap:14px; }.staff-week-actions { width:100%; }.staff-week-actions .staff-week-today { flex:1; }.staff-schedule-item { align-items:flex-start; padding:13px 0; }.staff-schedule-item time { width:76px; font-size:11px; }.staff-schedule-item > .staff-day-status { display:none; } }
</style>
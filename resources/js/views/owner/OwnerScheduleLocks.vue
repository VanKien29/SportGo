<template>
  <section class="schedule-lock-page">
    <header class="page-head">
      <div>
        <p class="eyebrow">LỊCH SÂN</p>
        <h2>Khóa lịch theo khung giờ</h2>
        <p>Ngừng nhận khách trong thời gian bảo trì, nghỉ hoặc tổ chức sự kiện riêng.</p>
      </div>
      <button class="secondary-btn" type="button" :disabled="loading" @click="loadData">Làm mới</button>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="notice" class="alert success">{{ notice }}</div>

    <div class="content-grid">
      <article class="form-card">
        <div class="card-head">
          <div>
            <p class="eyebrow">TẠO KHÓA MỚI</p>
            <h3>Chọn khoảng không nhận khách</h3>
          </div>
        </div>

        <form @submit.prevent="createLock">
          <label>
            Sân
            <select v-model="form.venue_court_id" required>
              <option value="" disabled>Chọn sân</option>
              <option v-for="court in courts" :key="court.id" :value="court.id">
                {{ court.name }} · {{ court.court_type?.name || 'Chưa phân loại' }}
              </option>
            </select>
          </label>

          <label>
            Ngày khóa
            <input v-model="form.booking_date" type="date" :min="today" required @change="handleDateChange">
          </label>

          <div class="time-grid">
            <label>
              Giờ bắt đầu
              <input v-model="form.start_time" type="time" step="1800" required>
            </label>
            <label>
              Giờ kết thúc
              <input v-model="form.end_time" type="time" step="1800" required>
            </label>
          </div>

          <label>
            Lý do khóa
            <textarea
              v-model.trim="form.reason"
              rows="4"
              maxlength="500"
              placeholder="Ví dụ: Bảo trì mặt sân, nghỉ lễ, sự kiện nội bộ..."
              required
            />
          </label>

          <div class="form-note">
            Hệ thống sẽ từ chối nếu khoảng này trùng dù chỉ một phần với booking hoặc khoảng đã khóa.
          </div>

          <button class="primary-btn" type="submit" :disabled="saving || !canSubmit">
            {{ saving ? 'Đang khóa...' : 'Khóa khung giờ' }}
          </button>
        </form>
      </article>

      <article class="schedule-card">
        <div class="card-head schedule-headline">
          <div>
            <p class="eyebrow">TRẠNG THÁI TRONG NGÀY</p>
            <h3>{{ date(form.booking_date) }}</h3>
          </div>
          <div class="legend">
            <span><i class="available"></i>Trống</span>
            <span><i class="booking"></i>Đã đặt</span>
            <span><i class="holding"></i>Đang giữ</span>
            <span><i class="manual"></i>Đã khóa</span>
          </div>
        </div>

        <div v-if="loading" class="state">Đang tải lịch sân...</div>
        <div v-else-if="!selectedClusterId" class="state">Vui lòng chọn cụm sân ở thanh bên.</div>
        <div v-else-if="!scheduleCourts.length" class="state">Cụm sân chưa có sân đang hoạt động.</div>
        <div v-else class="schedule-wrap">
          <div class="schedule-grid" :style="scheduleGridStyle">
            <div class="grid-head sticky-col">Sân \ Giờ</div>
            <div v-for="slot in scheduleSlots" :key="slot.start_time" class="grid-head time-head">
              {{ time(slot.start_time) }}
            </div>

            <template v-for="court in scheduleCourts" :key="court.id">
              <div class="court-cell sticky-col">
                <strong>{{ court.name }}</strong>
                <span>{{ court.court_type?.name }}</span>
              </div>
              <button
                v-for="slot in scheduleSlots"
                :key="`${court.id}-${slot.start_time}`"
                class="slot-cell"
                :class="slotClass(court.id, slot)"
                :title="slotTitle(court.id, slot)"
                type="button"
                :disabled="isBusy(court.id, slot)"
                @click="pickSlot(court, slot)"
              />
            </template>
          </div>
        </div>
      </article>
    </div>

    <article class="locks-card">
      <div class="card-head">
        <div>
          <p class="eyebrow">KHÓA THỦ CÔNG</p>
          <h3>Các khoảng đã khóa trong ngày</h3>
        </div>
        <span class="count-badge">{{ locks.length }} khoảng</span>
      </div>

      <div v-if="!locks.length" class="state">Ngày này chưa có khoảng khóa thủ công.</div>
      <div v-else class="lock-list">
        <div v-for="lock in locks" :key="lock.id" class="lock-row">
          <div class="lock-time">
            <strong>{{ time(lock.start_time) }} - {{ time(lock.end_time) }}</strong>
            <span>{{ lock.venue_court?.name }}</span>
          </div>
          <p>{{ lock.reason }}</p>
          <button class="danger-btn" type="button" :disabled="deletingId === lock.id" @click="removeLock(lock)">
            {{ deletingId === lock.id ? 'Đang mở...' : 'Mở lịch' }}
          </button>
        </div>
      </div>
    </article>
  </section>
</template>

<script>
import { bookingService } from '../../services/bookingService.js';
import { ownerScheduleLockService } from '../../services/ownerScheduleLocks.js';
import { venueClusterService } from '../../services/venueClusters.js';

export default {
  name: 'OwnerScheduleLocks',
  data() {
    const today = new Date().toISOString().split('T')[0];

    return {
      today,
      selectedClusterId: localStorage.getItem('selected_cluster') || '',
      courts: [],
      locks: [],
      scheduleSlots: [],
      scheduleCourts: [],
      scheduleSlotStatuses: [],
      loading: true,
      saving: false,
      deletingId: '',
      error: '',
      notice: '',
      form: {
        venue_court_id: '',
        booking_date: today,
        start_time: '08:00',
        end_time: '09:00',
        reason: '',
      },
    };
  },
  computed: {
    canSubmit() {
      return this.form.venue_court_id
        && this.form.booking_date
        && this.form.start_time
        && this.form.end_time
        && this.form.reason
        && this.toMinutes(this.form.end_time) > this.toMinutes(this.form.start_time);
    },
    scheduleGridStyle() {
      return { gridTemplateColumns: `150px repeat(${this.scheduleSlots.length}, 34px)` };
    },
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.handleClusterChanged);
    await this.loadData();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleClusterChanged);
  },
  methods: {
    async handleClusterChanged(event) {
      this.selectedClusterId = event.detail?.id || localStorage.getItem('selected_cluster') || '';
      this.form.venue_court_id = '';
      await this.loadData();
    },
    async handleDateChange() {
      await Promise.all([this.loadSchedule(), this.loadLocks()]);
    },
    async loadData() {
      this.loading = true;
      this.error = '';

      if (!this.selectedClusterId) {
        this.loading = false;
        return;
      }

      try {
        const response = await venueClusterService.getCourts(this.selectedClusterId);
        this.courts = (response.data || []).filter((court) => court.status === 'active');
        if (!this.courts.some((court) => court.id === this.form.venue_court_id)) {
          this.form.venue_court_id = this.courts[0]?.id || '';
        }
        await Promise.all([this.loadSchedule(), this.loadLocks()]);
      } catch (error) {
        this.error = error.message || 'Không thể tải lịch sân.';
      } finally {
        this.loading = false;
      }
    },
    async loadSchedule() {
      if (!this.selectedClusterId || !this.form.booking_date) return;

      const response = await bookingService.getSchedule({
        venue_cluster_id: this.selectedClusterId,
        booking_date: this.form.booking_date,
        booking_type: 'single',
      });
      this.scheduleSlots = response.time_slots || [];
      this.scheduleCourts = response.courts || [];
      this.scheduleSlotStatuses = response.slot_statuses || [];
    },
    async loadLocks() {
      if (!this.selectedClusterId || !this.form.booking_date) return;

      const response = await ownerScheduleLockService.list({
        venue_cluster_id: this.selectedClusterId,
        booking_date: this.form.booking_date,
      });
      this.locks = response.data || [];
    },
    async createLock() {
      if (!this.canSubmit) return;

      this.saving = true;
      this.error = '';
      this.notice = '';
      try {
        const response = await ownerScheduleLockService.create({
          ...this.form,
          start_time: this.withSeconds(this.form.start_time),
          end_time: this.withSeconds(this.form.end_time),
        });
        this.notice = response.message;
        this.form.reason = '';
        await Promise.all([this.loadSchedule(), this.loadLocks()]);
      } catch (error) {
        this.error = error.message || 'Không thể khóa khung giờ.';
      } finally {
        this.saving = false;
      }
    },
    async removeLock(lock) {
      if (!window.confirm(`Mở lại ${this.time(lock.start_time)} - ${this.time(lock.end_time)} tại ${lock.venue_court?.name}?`)) return;

      this.deletingId = lock.id;
      this.error = '';
      this.notice = '';
      try {
        const response = await ownerScheduleLockService.remove(lock.id);
        this.notice = response.message;
        await Promise.all([this.loadSchedule(), this.loadLocks()]);
      } catch (error) {
        this.error = error.message || 'Không thể mở lại khung giờ.';
      } finally {
        this.deletingId = '';
      }
    },
    statusFor(courtId, slot) {
      return this.scheduleSlotStatuses.find(
        (status) => status.venue_court_id === courtId && status.start_time === slot.start_time,
      ) || null;
    },
    isBusy(courtId, slot) {
      return !this.statusFor(courtId, slot)?.is_available;
    },
    slotClass(courtId, slot) {
      const status = this.statusFor(courtId, slot);
      if (!status || status.is_available) return 'available';
      if (status.busy_source === 'booking') return 'booking';
      if (status.busy_status === 'manual') return 'manual';
      return 'holding';
    },
    slotTitle(courtId, slot) {
      const status = this.statusFor(courtId, slot);
      if (!status || status.is_available) return `${this.time(slot.start_time)} - ${this.time(slot.end_time)} · Trống`;
      if (status.busy_source === 'booking') return 'Đã có booking';
      if (status.busy_status === 'manual') return `Đã khóa: ${status.lock_reason || 'Không có lý do'}`;
      return 'Đang được giữ chỗ';
    },
    pickSlot(court, slot) {
      this.form.venue_court_id = court.id;
      this.form.start_time = this.time(slot.start_time);
      this.form.end_time = this.time(slot.end_time);
    },
    withSeconds(value) {
      return value.length === 5 ? `${value}:00` : value;
    },
    toMinutes(value) {
      const [hour, minute] = (value || '').slice(0, 5).split(':').map(Number);
      return (hour || 0) * 60 + (minute || 0);
    },
    time(value) {
      return (value || '').slice(0, 5);
    },
    date(value) {
      if (!value) return '-';
      return new Intl.DateTimeFormat('vi-VN').format(new Date(`${value}T00:00:00`));
    },
  },
};
</script>

<style scoped>
.schedule-lock-page{display:grid;gap:18px;max-width:1320px}.page-head,.card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}.page-head h2,.card-head h3{margin:0;color:#0f172a}.page-head>div>p:last-child{margin:7px 0 0;color:#64748b}.eyebrow{margin:0 0 6px;color:#059669;font-size:11px;font-weight:900;letter-spacing:.1em}.content-grid{display:grid;grid-template-columns:330px minmax(0,1fr);gap:18px;align-items:start}.form-card,.schedule-card,.locks-card{border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 8px 28px rgba(15,23,42,.04)}.form-card,.locks-card{padding:20px}.form-card form{display:grid;gap:15px;margin-top:18px}.form-card label{display:grid;gap:7px;color:#334155;font-size:13px;font-weight:850}.form-card input,.form-card select,.form-card textarea{width:100%;border:1px solid #cbd5e1;border-radius:9px;padding:10px 11px;background:#fff;color:#0f172a;font:inherit}.time-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.form-note{padding:11px;border-radius:9px;background:#f8fafc;color:#64748b;font-size:12px;line-height:1.5}.primary-btn,.secondary-btn,.danger-btn{border:0;border-radius:9px;padding:10px 14px;font:inherit;font-weight:850;cursor:pointer}.primary-btn{background:#059669;color:#fff}.secondary-btn{background:#f1f5f9;color:#334155}.danger-btn{background:#fee2e2;color:#991b1b}.primary-btn:disabled,.secondary-btn:disabled,.danger-btn:disabled{opacity:.55;cursor:not-allowed}.alert{padding:13px 15px;border-radius:10px;font-weight:800}.alert.error{background:#fee2e2;color:#991b1b}.alert.success{background:#dcfce7;color:#166534}.schedule-card{overflow:hidden}.schedule-headline{padding:18px 20px;border-bottom:1px solid #e2e8f0}.legend{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:12px;color:#64748b;font-size:11px;font-weight:800}.legend span{display:flex;align-items:center;gap:5px}.legend i{width:11px;height:11px;border-radius:3px;border:1px solid #cbd5e1}.legend .available{background:#fff}.legend .booking{background:#cbd5e1}.legend .holding{background:#fde68a}.legend .manual{background:#fca5a5}.state{padding:36px;text-align:center;color:#64748b}.schedule-wrap{max-width:100%;overflow:auto}.schedule-grid{display:grid;min-width:max-content}.grid-head,.court-cell,.slot-cell{min-height:34px;border-right:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0}.grid-head{display:grid;place-items:center;background:#f8fafc;color:#475569;font-size:10px;font-weight:900}.time-head{writing-mode:vertical-rl;transform:rotate(180deg);padding:5px 0}.sticky-col{position:sticky;left:0;z-index:2}.court-cell{display:grid;align-content:center;gap:2px;padding:6px 10px;background:#fff}.court-cell strong{color:#0f172a;font-size:12px}.court-cell span{color:#64748b;font-size:10px}.slot-cell{border-top:0;border-left:0;background:#fff;cursor:pointer}.slot-cell.available:hover{background:#d1fae5}.slot-cell.booking{background:#cbd5e1}.slot-cell.holding{background:#fde68a}.slot-cell.manual{background:#fca5a5}.slot-cell:disabled{cursor:not-allowed}.locks-card .card-head{padding-bottom:16px;border-bottom:1px solid #e2e8f0}.count-badge{padding:5px 9px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:12px;font-weight:850}.lock-list{display:grid}.lock-row{display:grid;grid-template-columns:210px 1fr auto;gap:16px;align-items:center;padding:14px 0;border-bottom:1px solid #e2e8f0}.lock-row:last-child{border-bottom:0}.lock-time{display:grid;gap:4px}.lock-time strong{color:#0f172a}.lock-time span,.lock-row p{margin:0;color:#64748b;font-size:13px}@media(max-width:1050px){.content-grid{grid-template-columns:1fr}.form-card form{grid-template-columns:repeat(2,1fr)}.form-note,.primary-btn,.form-card label:last-of-type{grid-column:1/-1}}@media(max-width:720px){.page-head,.schedule-headline{display:grid}.form-card form{grid-template-columns:1fr}.time-grid{grid-template-columns:1fr}.form-note,.primary-btn,.form-card label:last-of-type{grid-column:auto}.lock-row{grid-template-columns:1fr}.legend{justify-content:flex-start}}
</style>

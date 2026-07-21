<template>
  <section class="staff-dashboard-page">
    <p v-if="error" class="staff-alert staff-alert-error" role="alert">
      <AppIcon name="alertCircle" size="14" />
      <span>{{ error }}</span>
    </p>
    <p v-if="success" class="staff-alert staff-alert-success" role="alert">
      <AppIcon name="checkCircle" size="14" />
      <span>{{ success }}</span>
    </p>

    <div v-if="loading" class="staff-loading-state">
      <div class="spinner"></div>
      <span>Đang tải thông tin tổng quan...</span>
    </div>

    <template v-else>
      <!-- HÀNG THẺ CHỈ SỐ VẬN HÀNH (STATS OVERVIEW CARDS) -->
      <div class="stats-grid">
        <!-- Số sân đang hoạt động -->
        <article class="stat-card blue">
          <div class="stat-icon-wrapper">
            <AppIcon name="activity" size="24" />
          </div>
          <div class="stat-data">
            <strong class="stat-number">
              {{ stats.active_courts_count }} / {{ stats.total_courts_count }}
            </strong>
            <span class="stat-label">Sân hoạt động</span>
          </div>
        </article>

        <!-- Số đơn đặt hôm nay -->
        <router-link to="/staff/bookings" class="stat-card purple clickable">
          <div class="stat-icon-wrapper">
            <AppIcon name="calendar" size="24" />
          </div>
          <div class="stat-data">
            <strong class="stat-number">{{ stats.today_bookings_count }}</strong>
            <span class="stat-label">Đơn đặt hôm nay</span>
          </div>
        </router-link>

        <!-- Số khách đang chơi -->
        <article class="stat-card green">
          <div class="stat-icon-wrapper">
            <AppIcon name="users" size="24" />
          </div>
          <div class="stat-data">
            <strong class="stat-number">{{ stats.playing_now_count }}</strong>
            <span class="stat-label">Khách đang chơi</span>
          </div>
        </article>

        <!-- Số khách sắp đến -->
        <router-link to="/staff/bookings" class="stat-card orange clickable">
          <div class="stat-icon-wrapper">
            <AppIcon name="clock" size="24" />
          </div>
          <div class="stat-data">
            <strong class="stat-number">{{ stats.upcoming_bookings_count }}</strong>
            <span class="stat-label">Khách sắp đến</span>
          </div>
        </router-link>
      </div>

      <!-- KHU VỰC CA TRỰC & THÔNG BÁO -->
      <div class="dashboard-row-middle">
        <!-- CA TRỰC HÔM NAY CỦA BẠN -->
        <section class="section-box shift-card-box">
          <header class="section-box-header">
            <h3 class="section-box-title">
              <AppIcon name="calendar" size="16" />
              <span>Ca trực hôm nay của bạn</span>
            </h3>
          </header>

          <div v-if="stats.my_shift_today" class="shift-details">
            <div class="shift-info-row">
              <div class="shift-badge-name">
                <strong>{{ stats.my_shift_today.shift?.name || 'Ca trực' }}</strong>
                <span class="shift-badge-status" :class="stats.my_shift_today.status">
                  {{ statusLabel(stats.my_shift_today.status) }}
                </span>
              </div>
              <div class="shift-time-range">
                <AppIcon name="clock" size="13" />
                <span>{{ formatShiftTime(stats.my_shift_today) }}</span>
              </div>
            </div>

            <div v-if="stats.my_shift_today.notes" class="shift-notes">
              <strong>Ghi chú từ chủ sân:</strong>
              <p>{{ stats.my_shift_today.notes }}</p>
            </div>

            <div class="shift-attendance-logs" v-if="stats.my_shift_today.check_in_at || stats.my_shift_today.check_out_at">
              <div v-if="stats.my_shift_today.check_in_at" class="attendance-log-item">
                <AppIcon name="checkCircle" size="12" class="text-green" />
                <span>Đã check-in lúc: <strong>{{ formatDateTime(stats.my_shift_today.check_in_at) }}</strong></span>
              </div>
              <div v-if="stats.my_shift_today.check_out_at" class="attendance-log-item">
                <AppIcon name="checkCircle" size="12" class="text-gray" />
                <span>Đã check-out lúc: <strong>{{ formatDateTime(stats.my_shift_today.check_out_at) }}</strong></span>
              </div>
            </div>

            <div class="shift-actions">
              <button
                v-if="stats.my_shift_today.status === 'scheduled'"
                type="button"
                class="staff-btn staff-btn-primary"
                :disabled="actionLoading || !canCheckIn(stats.my_shift_today)"
                @click="handleCheckIn(stats.my_shift_today.id)"
              >
                <span v-if="actionLoading" class="spinner-mini"></span>
                <span>Check-in</span>
              </button>

              <button
                v-if="stats.my_shift_today.status === 'checked_in'"
                type="button"
                class="staff-btn staff-btn-secondary"
                :disabled="actionLoading"
                @click="handleCheckOut(stats.my_shift_today.id)"
              >
                <span v-if="actionLoading" class="spinner-mini"></span>
                <span>Check-out</span>
              </button>

              <div v-if="stats.my_shift_today.status === 'checked_out'" class="shift-completed-msg">
                <AppIcon name="checkCircle" size="14" />
                <span>Bạn đã hoàn thành ca trực hôm nay. Cảm ơn sự cố gắng của bạn!</span>
              </div>

              <p v-if="stats.my_shift_today.status === 'scheduled' && !canCheckIn(stats.my_shift_today)" class="shift-action-note">
                (Chỉ có thể check-in trước giờ bắt đầu tối đa 30 phút và trong ngày ca trực)
              </p>
            </div>
          </div>

          <div v-else class="shift-empty-state">
            <AppIcon name="info" size="24" />
            <p>Hôm nay bạn không có lịch phân công ca trực tại cụm sân này.</p>
            <router-link to="/staff/schedules" class="staff-text-link">Xem lịch trực tuần</router-link>
          </div>
        </section>

        <!-- THÔNG BÁO MỚI -->
        <section class="section-box notifications-card-box">
          <header class="section-box-header">
            <h3 class="section-box-title">
              <AppIcon name="bell" size="16" />
              <span>Thông báo mới</span>
            </h3>
            <button
              v-if="hasUnreadNotifications"
              type="button"
              class="mark-all-read-btn"
              @click="markAllNotificationsRead"
            >
              Đánh dấu tất cả đã đọc
            </button>
          </header>

          <div v-if="stats.notifications && stats.notifications.length > 0" class="notifications-list">
            <article
              v-for="notif in stats.notifications"
              :key="notif.id"
              class="notification-item"
              :class="{ 'is-unread': !notif.is_read }"
              @click="handleNotificationClick(notif)"
            >
              <div class="notification-indicator" v-if="!notif.is_read"></div>
              <div class="notification-body-content">
                <h4 class="notification-title">{{ notif.title }}</h4>
                <p class="notification-desc">{{ notif.body }}</p>
                <time class="notification-time">{{ formatTimeAgo(notif.created_at) }}</time>
              </div>
            </article>
          </div>

          <div v-else class="notification-empty-state">
            <AppIcon name="bell" size="24" />
            <p>Chưa có thông báo nào dành cho bạn.</p>
          </div>
        </section>
      </div>

      <!-- THỜI GIAN TRỐNG CỦA TỪNG SÂN (COURT AVAILABILITIES) -->
      <section class="section-box court-availabilities-box">
        <header class="section-box-header">
          <div class="header-titles">
            <h3 class="section-box-title">
              <AppIcon name="activity" size="16" />
              <span>Thời gian trống của từng sân hôm nay</span>
            </h3>
            <p class="section-box-subtitle">Cập nhật theo thời gian thực để nhân viên dễ dàng tư vấn và đặt chỗ cho khách vãng lai.</p>
          </div>
          <router-link to="/staff/counter-booking" class="staff-btn staff-btn-primary" style="text-decoration: none;">
            <AppIcon name="plus" size="14" />
            <span>Đặt sân tại quầy</span>
          </router-link>
        </header>

        <div v-if="stats.court_availabilities && stats.court_availabilities.length > 0" class="courts-grid">
          <article
            v-for="court in stats.court_availabilities"
            :key="court.court_id"
            class="court-availability-card"
            :class="{ 'is-full': court.is_fully_booked }"
          >
            <div class="court-card-header">
              <div class="court-title-info">
                <h4 class="court-name">{{ court.court_name }}</h4>
                <span class="court-type-badge">{{ court.court_type }}</span>
              </div>
              <span v-if="court.is_fully_booked" class="court-status-badge full">
                Đầy lịch
              </span>
              <span v-else class="court-status-badge available">
                Còn lịch trống
              </span>
            </div>

            <div class="court-card-body">
              <span class="slots-label">Khung giờ trống:</span>
              <div v-if="!court.is_fully_booked" class="free-slots-list">
                <span
                  v-for="(slot, idx) in court.free_slots"
                  :key="idx"
                  class="free-slot-pill"
                >
                  <AppIcon name="clock" size="10" />
                  <span>{{ slot }}</span>
                </span>
              </div>
              <p v-else class="no-slots-desc">Sân đã kín lịch hoạt động hoặc đang khóa toàn bộ khung giờ trong ngày.</p>
            </div>
          </article>
        </div>

        <div v-else class="courts-empty-state">
          <AppIcon name="info" size="24" />
          <p>Không tìm thấy sân con nào thuộc cụm sân hiện tại.</p>
        </div>
      </section>
    </template>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { staffDashboardService } from '../../services/staffDashboard.js';
import { ownerStaffShiftService } from '../../services/ownerStaffShiftService.js';
import { notificationService } from '../../services/notification.service.js';
import { getAuth } from '../../stores/auth.js';

export default {
  name: 'StaffDashboard',
  components: { AppIcon },
  data() {
    return {
      stats: {
        active_courts_count: 0,
        total_courts_count: 0,
        today_bookings_count: 0,
        playing_now_count: 0,
        upcoming_bookings_count: 0,
        court_availabilities: [],
        notifications: [],
        my_shift_today: null,
      },
      loading: true,
      actionLoading: false,
      error: '',
      success: '',
      localTasks: [
        { id: 'clean', label: 'Kiểm tra thiết bị và vệ sinh sân trước giờ chơi', done: false },
        { id: 'handover', label: 'Bàn giao ca trực & chốt số dư két tiền mặt', done: false }
      ],
    };
  },
  created() {
    const savedTasks = localStorage.getItem('staff_daily_tasks');
    if (savedTasks) {
      try {
        const parsed = JSON.parse(savedTasks);
        this.localTasks.forEach(task => {
          const match = parsed.find(t => t.id === task.id);
          if (match) {
            task.done = match.done;
          }
        });
      } catch (e) {
        // ignore
      }
    }
  },
  computed: {
    hasUnreadNotifications() {
      return (this.stats.notifications || []).some((n) => !n.is_read);
    },
  },
  mounted() {
    window.addEventListener('owner-cluster-changed', this.onClusterChanged);
    this.loadOverview();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.onClusterChanged);
  },
  methods: {
    toggleLocalTask(task) {
      task.done = !task.done;
      localStorage.setItem('staff_daily_tasks', JSON.stringify(this.localTasks));
    },
    async onClusterChanged() {
      await this.loadOverview();
    },
    async loadOverview() {
      this.loading = true;
      this.error = '';
      try {
        const clusterId = localStorage.getItem('selected_cluster');
        const response = await staffDashboardService.getOverview({
          venue_cluster_id: clusterId,
        });
        this.stats = response || {
          active_courts_count: 0,
          total_courts_count: 0,
          today_bookings_count: 0,
          playing_now_count: 0,
          upcoming_bookings_count: 0,
          court_availabilities: [],
          notifications: [],
          my_shift_today: null,
        };
      } catch (error) {
        this.error = error.message || 'Không thể tải thông tin tổng quan. Vui lòng thử lại.';
      } finally {
        this.loading = false;
      }
    },
    async handleCheckIn(scheduleId) {
      if (this.actionLoading) return;
      this.actionLoading = true;
      this.error = '';
      this.success = '';
      try {
        const response = await ownerStaffShiftService.checkIn(scheduleId);
        this.success = response.message || 'Check-in thành công!';
        await this.loadOverview();
      } catch (error) {
        this.error = error.message || 'Không thể thực hiện check-in.';
      } finally {
        this.actionLoading = false;
      }
    },
    async handleCheckOut(scheduleId) {
      if (this.actionLoading) return;
      this.actionLoading = true;
      this.error = '';
      this.success = '';
      try {
        const response = await ownerStaffShiftService.checkOut(scheduleId);
        this.success = response.message || 'Check-out thành công!';
        await this.loadOverview();
      } catch (error) {
        this.error = error.message || 'Không thể thực hiện check-out.';
      } finally {
        this.actionLoading = false;
      }
    },
    async handleNotificationClick(notif) {
      if (notif.is_read) return;
      try {
        await notificationService.markAsRead(notif.id);
        notif.is_read = true;
      } catch (error) {
        console.error('Lỗi khi đánh dấu thông báo đã đọc:', error);
      }
    },
    async markAllNotificationsRead() {
      try {
        await notificationService.markAllAsRead();
        if (this.stats.notifications) {
          this.stats.notifications.forEach((n) => {
            n.is_read = true;
          });
        }
      } catch (error) {
        console.error('Lỗi khi đánh dấu tất cả đã đọc:', error);
      }
    },
    canCheckIn(schedule) {
      if (!schedule) return false;
      const todayStr = new Date().toISOString().slice(0, 10);
      if (schedule.date !== todayStr) return false;

      // Giờ bắt đầu ca trực
      const parts = schedule.start_time.split(':');
      const startHour = parseInt(parts[0], 10);
      const startMin = parseInt(parts[1], 10);

      const shiftStart = new Date();
      shiftStart.setHours(startHour, startMin, 0, 0);

      const now = new Date();
      // Cho phép check-in trước tối đa 30 phút
      const earlyLimit = new Date(shiftStart.getTime() - 30 * 60000);

      return now >= earlyLimit;
    },
    statusLabel(status) {
      return (
        {
          scheduled: 'Đã lên lịch',
          checked_in: 'Đang trực',
          checked_out: 'Đã hoàn thành',
          absent: 'Vắng mặt',
          cancelled: 'Đã hủy',
        }[status] || 'Đã lên lịch'
      );
    },
    formatShiftTime(schedule) {
      if (!schedule) return '';
      return `${schedule.start_time.substring(0, 5)} - ${schedule.end_time.substring(0, 5)}`;
    },
    formatDateTime(dateTimeStr) {
      if (!dateTimeStr) return '';
      return new Intl.DateTimeFormat('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      }).format(new Date(dateTimeStr));
    },
    formatTimeAgo(dateTimeStr) {
      if (!dateTimeStr) return '';
      const seconds = Math.floor((new Date() - new Date(dateTimeStr)) / 1000);
      let interval = Math.floor(seconds / 31536000);
      if (interval >= 1) return interval + ' năm trước';
      interval = Math.floor(seconds / 2592000);
      if (interval >= 1) return interval + ' tháng trước';
      interval = Math.floor(seconds / 86400);
      if (interval >= 1) return interval + ' ngày trước';
      interval = Math.floor(seconds / 3600);
      if (interval >= 1) return interval + ' giờ trước';
      interval = Math.floor(seconds / 60);
      if (interval >= 1) return interval + ' phút trước';
      return 'Vừa xong';
    },
  },
};
</script>

<style scoped>
.staff-dashboard-page {
  max-width: 1120px;
  margin: 0 auto;
  color: var(--admin-text);
  padding: 16px 0;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.staff-alert {
  margin: 0;
  padding: 12px 16px;
  border-radius: var(--admin-radius);
  font-size: 13px;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 8px;
}
.staff-alert-error {
  color: var(--admin-danger);
  background: var(--admin-danger-soft);
  border: 1px solid color-mix(in srgb, var(--admin-danger) 20%, transparent);
}
.staff-alert-success {
  color: var(--admin-primary);
  background: var(--admin-primary-soft);
  border: 1px solid color-mix(in srgb, var(--admin-primary) 20%, transparent);
}

.staff-loading-state {
  min-height: 200px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--admin-muted);
  gap: 12px;
  font-size: 14px;
}

.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid var(--admin-border-soft);
  border-top-color: var(--admin-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.spinner-mini {
  width: 14px;
  height: 14px;
  border: 2px solid transparent;
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* STATS OVERVIEW CARDS */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.stat-card {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  transition: all 0.2s ease-in-out;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
  text-decoration: none;
  color: inherit;
}

.stat-card.clickable {
  cursor: pointer;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04);
}

.stat-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-card.blue .stat-icon-wrapper {
  background: color-mix(in srgb, var(--admin-primary) 10%, transparent);
  color: var(--admin-primary);
}
.stat-card.purple .stat-icon-wrapper {
  background: rgba(139, 92, 246, 0.1);
  color: rgb(139, 92, 246);
}
.stat-card.green .stat-icon-wrapper {
  background: rgba(34, 197, 94, 0.1);
  color: rgb(34, 197, 94);
}
.stat-card.orange .stat-icon-wrapper {
  background: rgba(249, 115, 22, 0.1);
  color: rgb(249, 115, 22);
}

.stat-data {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-number {
  font-size: 20px;
  font-weight: 700;
  color: var(--admin-text);
  line-height: 1;
}

.stat-label {
  font-size: 12px;
  color: var(--admin-muted);
  font-weight: 500;
}

/* MIDDLE ROW LAYOUT */
.dashboard-row-middle {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.section-box {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  display: flex;
  flex-direction: column;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}

.section-box-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--admin-border-soft);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.section-box-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--admin-text);
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}

.section-box-subtitle {
  font-size: 13px;
  color: var(--admin-muted);
  margin: 4px 0 0;
  font-weight: 400;
}

.header-titles {
  display: flex;
  flex-direction: column;
}

/* SHIFT DETAILS CARD */
.shift-details {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  flex: 1;
}

.shift-info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.shift-badge-name {
  display: flex;
  align-items: center;
  gap: 10px;
}

.shift-badge-name strong {
  font-size: 16px;
  font-weight: 700;
  color: var(--admin-text);
}

.shift-badge-status {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 12px;
  text-transform: uppercase;
}
.shift-badge-status.scheduled {
  background: var(--admin-bg-soft);
  color: var(--admin-muted);
}
.shift-badge-status.checked_in {
  background: rgba(34, 197, 94, 0.15);
  color: rgb(21, 128, 61);
}
.shift-badge-status.checked_out {
  background: var(--admin-bg-soft);
  color: var(--admin-muted);
}
.shift-badge-status.absent {
  background: var(--admin-danger-soft);
  color: var(--admin-danger);
}
.shift-badge-status.cancelled {
  background: var(--admin-bg-soft);
  color: var(--admin-muted);
}

.shift-time-range {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.todo-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding-bottom: 14px;
  border-bottom: 1px dashed var(--admin-border-soft);
  gap: 12px;
}
.todo-item:last-of-type {
  border-bottom: none;
  padding-bottom: 0;
}
.todo-item-left {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  flex: 1;
}
.todo-checkbox-styled {
  width: 18px;
  height: 18px;
  border: 2px solid var(--admin-muted, #94a3b8);
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--admin-text);
  background: var(--admin-bg-soft);
  padding: 4px 10px;
  border-radius: 6px;
}
.todo-memo-box {
  background: var(--admin-bg-soft);
  border-radius: 8px;
  padding: 12px;
  font-size: 13px;
  border-left: 3px solid var(--admin-primary);
}

.shift-notes strong {
  display: block;
  font-size: 12px;
  color: var(--admin-muted);
  margin-bottom: 4px;
}

.shift-notes p {
  margin: 0;
  color: var(--admin-text);
  line-height: 1.4;
}

.shift-attendance-logs {
  display: flex;
  flex-direction: column;
  gap: 6px;
  border-top: 1px dashed var(--admin-border-soft);
  padding-top: 14px;
}

.attendance-log-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: var(--admin-muted);
}

.text-green {
  color: rgb(34, 197, 94);
}
.text-gray {
  color: var(--admin-muted);
}

.shift-actions {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 12px;
}

.staff-btn {
  width: 100%;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  border-radius: var(--admin-radius);
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
}

.staff-btn-primary {
  background: var(--admin-primary);
  color: #ffffff;
}
.staff-btn-primary:hover:not(:disabled) {
  background: var(--admin-primary-dark);
}
.staff-btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.staff-btn-secondary {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border-soft);
  color: var(--admin-text);
}
.staff-btn-secondary:hover:not(:disabled) {
  background: var(--admin-bg-soft);
}
.staff-btn-secondary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.shift-action-note {
  font-size: 11px;
  color: var(--admin-muted);
  text-align: center;
  margin: 0;
}

.shift-completed-msg {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
  color: rgb(21, 128, 61);
  font-size: 13px;
  font-weight: 600;
  background: rgba(34, 197, 94, 0.1);
  padding: 10px;
  border-radius: var(--admin-radius);
  text-align: center;
}

.shift-empty-state {
  padding: 40px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 12px;
  color: var(--admin-muted);
  flex: 1;
}

.shift-empty-state p {
  margin: 0;
  font-size: 13px;
  max-width: 280px;
  line-height: 1.4;
}

.staff-text-link {
  color: var(--admin-primary);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
}
.staff-text-link:hover {
  text-decoration: underline;
}

/* NOTIFICATIONS CARD */
.notifications-card-box {
  flex: 1;
}

.mark-all-read-btn {
  background: transparent;
  border: none;
  color: var(--admin-primary);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}
.mark-all-read-btn:hover {
  text-decoration: underline;
}

.notifications-list {
  display: flex;
  flex-direction: column;
  max-height: 320px;
  overflow-y: auto;
  flex: 1;
}

.notification-item {
  padding: 14px 20px;
  border-bottom: 1px solid var(--admin-border-soft);
  display: flex;
  gap: 12px;
  align-items: flex-start;
  position: relative;
  cursor: pointer;
  transition: background 0.15s ease;
}

.notification-item:last-child {
  border-bottom: none;
}

.notification-item:hover {
  background: var(--admin-bg-soft);
}

.notification-item.is-unread {
  background: color-mix(in srgb, var(--admin-primary) 3%, transparent);
}

.notification-item.is-unread:hover {
  background: color-mix(in srgb, var(--admin-primary) 6%, transparent);
}

.notification-indicator {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--admin-primary);
  position: absolute;
  left: 8px;
  top: 20px;
}

.notification-body-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.notification-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--admin-text);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.notification-desc {
  font-size: 12px;
  color: var(--admin-muted);
  margin: 0;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notification-time {
  font-size: 10px;
  color: var(--admin-faint);
  margin-top: 4px;
}

.notification-empty-state {
  padding: 40px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 12px;
  color: var(--admin-muted);
  flex: 1;
}

.notification-empty-state p {
  margin: 0;
  font-size: 13px;
}

/* COURT AVAILABILITIES GRID */
.courts-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  padding: 20px;
}

.court-availability-card {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.01);
}

.court-availability-card:hover {
  border-color: color-mix(in srgb, var(--admin-primary) 30%, var(--admin-border-soft));
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.03);
}

.court-availability-card.is-full {
  background: var(--admin-bg-soft);
  opacity: 0.85;
}

.court-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.court-title-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.court-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--admin-text);
  margin: 0;
}

.court-type-badge {
  font-size: 11px;
  color: var(--admin-muted);
  font-weight: 500;
}

.court-status-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
}
.court-status-badge.available {
  background: rgba(34, 197, 94, 0.12);
  color: rgb(21, 128, 61);
}
.court-status-badge.full {
  background: var(--admin-border-soft);
  color: var(--admin-muted);
}

.court-card-body {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.slots-label {
  font-size: 12px;
  color: var(--admin-muted);
  font-weight: 600;
}

.free-slots-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  max-height: 120px;
  overflow-y: auto;
}

.free-slot-pill {
  font-size: 11px;
  font-weight: 600;
  color: var(--admin-text);
  background: var(--admin-bg-soft);
  border: 1px solid var(--admin-border-soft);
  padding: 4px 8px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.free-slot-pill:hover {
  border-color: var(--admin-primary);
  color: var(--admin-primary);
}

.no-slots-desc {
  font-size: 12px;
  color: var(--admin-faint);
  margin: 0;
  line-height: 1.4;
}

.courts-empty-state {
  padding: 60px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: var(--admin-muted);
  gap: 12px;
}

.courts-empty-state p {
  margin: 0;
  font-size: 14px;
}

/* RESPONSIVE LAYOUT */
@media (max-width: 900px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .dashboard-row-middle {
    grid-template-columns: 1fr;
  }

  .courts-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }

  .courts-grid {
    grid-template-columns: 1fr;
  }
}
</style>
<template>
  <section class="staff-dashboard-page">
    <!-- Alerts -->
    <transition name="alert-fade">
      <p v-if="error" class="staff-alert staff-alert-error" role="alert">
        <AppIcon name="alertCircle" size="14" />
        <span>{{ error }}</span>
      </p>
    </transition>
    <transition name="alert-fade">
      <p v-if="success" class="staff-alert staff-alert-success" role="alert">
        <AppIcon name="checkCircle" size="14" />
        <span>{{ success }}</span>
      </p>
    </transition>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="loading-skeleton-layout">
      <div class="skeleton-stats-row">
        <div class="skeleton-card" v-for="i in 4" :key="i"></div>
      </div>
      <div class="skeleton-middle-row">
        <div class="skeleton-panel"></div>
        <div class="skeleton-panel"></div>
      </div>
      <div class="skeleton-full"></div>
    </div>

    <template v-else>
      <!-- PAGE HEADER -->
      <header class="page-header">
        <div class="page-header-left">
          <h1 class="page-title">Tổng quan hôm nay</h1>
          <p class="page-subtitle">{{ todayLabel }}</p>
        </div>
        <router-link to="/staff/counter-booking" class="cta-btn" style="text-decoration:none;">
          <AppIcon name="plus" size="15" />
          <span>Đặt sân tại quầy</span>
        </router-link>
      </header>

      <!-- STATS OVERVIEW CARDS -->
      <div class="stats-grid">
        <article class="stat-card stat-blue">
          <div class="stat-icon-area">
            <AppIcon name="activity" size="20" />
          </div>
          <div class="stat-body">
            <strong class="stat-number">{{ stats.active_courts_count }}<span class="stat-total"> / {{ stats.total_courts_count }}</span></strong>
            <span class="stat-label">Sân hoạt động</span>
          </div>
          <div class="stat-ring stat-ring-blue"></div>
        </article>

        <router-link to="/staff/bookings" class="stat-card stat-purple clickable" style="text-decoration:none;">
          <div class="stat-icon-area">
            <AppIcon name="calendar" size="20" />
          </div>
          <div class="stat-body">
            <strong class="stat-number">{{ stats.today_bookings_count }}</strong>
            <span class="stat-label">Đơn đặt hôm nay</span>
          </div>
          <div class="stat-ring stat-ring-purple"></div>
        </router-link>

        <article class="stat-card stat-green">
          <div class="stat-icon-area">
            <AppIcon name="users" size="20" />
          </div>
          <div class="stat-body">
            <strong class="stat-number">{{ stats.playing_now_count }}</strong>
            <span class="stat-label">Khách đang chơi</span>
          </div>
          <div class="stat-ring stat-ring-green"></div>
        </article>

        <router-link to="/staff/bookings" class="stat-card stat-orange clickable" style="text-decoration:none;">
          <div class="stat-icon-area">
            <AppIcon name="clock" size="20" />
          </div>
          <div class="stat-body">
            <strong class="stat-number">{{ stats.upcoming_bookings_count }}</strong>
            <span class="stat-label">Khách sắp đến</span>
          </div>
          <div class="stat-ring stat-ring-orange"></div>
        </router-link>
      </div>

      <!-- MIDDLE ROW: SHIFT + NOTIFICATIONS -->
      <div class="dashboard-middle">

        <!-- MY SHIFT TODAY -->
        <section class="panel shift-panel">
          <header class="panel-header">
            <div class="panel-header-icon panel-icon-primary">
              <AppIcon name="calendar" size="14" />
            </div>
            <h2 class="panel-title">Ca trực hôm nay</h2>
          </header>

          <div v-if="stats.my_shift_today" class="panel-body">
            <!-- Status ribbon -->
            <div class="shift-status-bar" :class="'shift-status-' + stats.my_shift_today.status">
              <span class="shift-status-dot"></span>
              <span class="shift-status-label">{{ statusLabel(stats.my_shift_today.status) }}</span>
              <span class="shift-time-chip">
                <AppIcon name="clock" size="11" />
                {{ formatShiftTime(stats.my_shift_today) }}
              </span>
            </div>

            <!-- Shift name -->
            <div class="shift-name-row">
              <span class="shift-name">{{ stats.my_shift_today.shift?.name || 'Ca trực' }}</span>
            </div>

            <!-- Notes -->
            <div v-if="stats.my_shift_today.notes" class="shift-notes">
              <span class="shift-notes-label">Ghi chú từ chủ sân</span>
              <p>{{ stats.my_shift_today.notes }}</p>
            </div>

            <!-- Attendance logs -->
            <div v-if="stats.my_shift_today.check_in_at || stats.my_shift_today.check_out_at" class="attendance-timeline">
              <div v-if="stats.my_shift_today.check_in_at" class="timeline-item timeline-in">
                <div class="timeline-dot"></div>
                <span class="timeline-label">Check-in</span>
                <strong class="timeline-time">{{ formatDateTime(stats.my_shift_today.check_in_at) }}</strong>
              </div>
              <div v-if="stats.my_shift_today.check_out_at" class="timeline-item timeline-out">
                <div class="timeline-dot"></div>
                <span class="timeline-label">Check-out</span>
                <strong class="timeline-time">{{ formatDateTime(stats.my_shift_today.check_out_at) }}</strong>
              </div>
            </div>

            <!-- Actions -->
            <div class="shift-actions">
              <button
                v-if="stats.my_shift_today.status === 'scheduled'"
                type="button"
                class="action-btn action-btn-checkin"
                :disabled="actionLoading || !canCheckIn(stats.my_shift_today)"
                @click="handleCheckIn(stats.my_shift_today.id)"
              >
                <span v-if="actionLoading" class="spinner-mini"></span>
                <AppIcon v-else name="checkCircle" size="14" />
                <span>Check-in ngay</span>
              </button>

              <button
                v-if="stats.my_shift_today.status === 'checked_in'"
                type="button"
                class="action-btn action-btn-checkout"
                :disabled="actionLoading"
                @click="handleCheckOut(stats.my_shift_today.id)"
              >
                <span v-if="actionLoading" class="spinner-mini"></span>
                <AppIcon v-else name="logOut" size="14" />
                <span>Check-out</span>
              </button>

              <div v-if="stats.my_shift_today.status === 'checked_out'" class="shift-completed">
                <AppIcon name="checkCircle" size="16" />
                <span>Ca trực hoàn thành. Cảm ơn bạn!</span>
              </div>

              <p v-if="stats.my_shift_today.status === 'scheduled' && !canCheckIn(stats.my_shift_today)" class="checkin-hint">
                Check-in mở trước 30 phút so với giờ bắt đầu ca
              </p>
            </div>
          </div>

          <div v-else class="panel-empty">
            <div class="panel-empty-icon">
              <AppIcon name="info" size="22" />
            </div>
            <p class="panel-empty-title">Không có ca trực hôm nay</p>
            <p class="panel-empty-desc">Bạn chưa được phân công ca nào tại cụm sân này.</p>
            <router-link to="/staff/schedules" class="panel-link">Xem lịch trực tuần →</router-link>
          </div>
        </section>

        <!-- NOTIFICATIONS -->
        <section class="panel notifications-panel">
          <header class="panel-header">
            <div class="panel-header-icon panel-icon-bell">
              <AppIcon name="bell" size="14" />
            </div>
            <h2 class="panel-title">Thông báo</h2>
            <span v-if="unreadCount > 0" class="unread-badge">{{ unreadCount }}</span>
            <button
              v-if="hasUnreadNotifications"
              type="button"
              class="mark-read-btn"
              @click="markAllNotificationsRead"
            >
              Đánh dấu tất cả đã đọc
            </button>
          </header>

          <div v-if="stats.notifications && stats.notifications.length > 0" class="notif-list">
            <article
              v-for="notif in stats.notifications"
              :key="notif.id"
              class="notif-item"
              :class="{ 'is-unread': !notif.is_read }"
              @click="handleNotificationClick(notif)"
            >
              <div class="notif-unread-dot" v-if="!notif.is_read"></div>
              <div class="notif-content">
                <h4 class="notif-title">{{ notif.title }}</h4>
                <p class="notif-desc">{{ notif.body }}</p>
                <time class="notif-time">{{ formatTimeAgo(notif.created_at) }}</time>
              </div>
            </article>
          </div>

          <div v-else class="panel-empty">
            <div class="panel-empty-icon">
              <AppIcon name="bell" size="22" />
            </div>
            <p class="panel-empty-title">Chưa có thông báo</p>
            <p class="panel-empty-desc">Bạn sẽ nhận thông báo khi có cập nhật mới.</p>
          </div>
        </section>
      </div>

      <!-- COURT AVAILABILITIES -->
      <section class="panel court-panel">
        <header class="panel-header panel-header-flat">
          <div class="panel-header-icon panel-icon-primary">
            <AppIcon name="activity" size="14" />
          </div>
          <div class="panel-header-text">
            <h2 class="panel-title">Thời gian trống của từng sân</h2>
            <p class="panel-subtitle">Cập nhật theo thời gian thực — hỗ trợ tư vấn cho khách vãng lai</p>
          </div>
        </header>

        <div v-if="stats.court_availabilities && stats.court_availabilities.length > 0" class="courts-grid">
          <article
            v-for="court in stats.court_availabilities"
            :key="court.court_id"
            class="court-card"
            :class="{ 'court-full': court.is_fully_booked }"
          >
            <div class="court-card-top">
              <div class="court-info">
                <h3 class="court-name">{{ court.court_name }}</h3>
                <span class="court-type">{{ court.court_type }}</span>
              </div>
              <span class="court-status-pill" :class="court.is_fully_booked ? 'pill-full' : 'pill-free'">
                {{ court.is_fully_booked ? 'Kín lịch' : 'Còn trống' }}
              </span>
            </div>

            <div class="court-card-body">
              <p class="slots-label">Khung giờ trống</p>
              <div v-if="!court.is_fully_booked" class="slot-chips">
                <span v-for="(slot, idx) in court.free_slots" :key="idx" class="slot-chip">
                  {{ slot }}
                </span>
              </div>
              <p v-else class="no-slots-msg">Sân đã kín lịch hoặc đang khóa toàn bộ khung giờ trong ngày.</p>
            </div>
          </article>
        </div>

        <div v-else class="panel-empty panel-empty-lg">
          <div class="panel-empty-icon">
            <AppIcon name="info" size="24" />
          </div>
          <p class="panel-empty-title">Không tìm thấy sân nào</p>
          <p class="panel-empty-desc">Không tìm thấy sân con nào thuộc cụm sân hiện tại.</p>
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
    };
  },
  computed: {
    hasUnreadNotifications() {
      return (this.stats.notifications || []).some((n) => !n.is_read);
    },
    unreadCount() {
      return (this.stats.notifications || []).filter((n) => !n.is_read).length;
    },
    todayLabel() {
      return new Intl.DateTimeFormat('vi-VN', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      }).format(new Date());
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

      const parts = schedule.start_time.split(':');
      const startHour = parseInt(parts[0], 10);
      const startMin = parseInt(parts[1], 10);

      const shiftStart = new Date();
      shiftStart.setHours(startHour, startMin, 0, 0);

      const now = new Date();
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
      return `${schedule.start_time.substring(0, 5)} – ${schedule.end_time.substring(0, 5)}`;
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
/* ─── Page Shell ────────────────────────────────────── */
.staff-dashboard-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px 0 40px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  color: var(--admin-text);
}

/* ─── Alerts ─────────────────────────────────────────── */
.staff-alert {
  margin: 0;
  padding: 11px 16px;
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
  border: 1px solid color-mix(in srgb, var(--admin-danger) 18%, transparent);
}
.staff-alert-success {
  color: var(--admin-success-text);
  background: var(--admin-success-soft);
  border: 1px solid color-mix(in srgb, var(--admin-primary) 18%, transparent);
}

.alert-fade-enter-active,
.alert-fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.alert-fade-enter-from,
.alert-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* ─── Skeleton Loading ────────────────────────────────── */
@keyframes shimmer {
  0% { background-position: -600px 0; }
  100% { background-position: 600px 0; }
}

.skeleton-card,
.skeleton-panel,
.skeleton-full {
  background: linear-gradient(90deg, var(--admin-border-soft) 25%, var(--admin-bg-soft) 50%, var(--admin-border-soft) 75%);
  background-size: 600px 100%;
  animation: shimmer 1.4s ease-in-out infinite;
  border-radius: var(--admin-radius);
}

.loading-skeleton-layout { display: flex; flex-direction: column; gap: 20px; }

.skeleton-stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.skeleton-card { height: 88px; }
.skeleton-middle-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.skeleton-panel { height: 240px; }
.skeleton-full { height: 280px; }

/* ─── Page Header ────────────────────────────────────── */
.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding-bottom: 4px;
  border-bottom: 1px solid var(--admin-border-soft);
}

.page-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--admin-text);
  margin: 0 0 2px;
  letter-spacing: -0.02em;
}

.page-subtitle {
  font-size: 13px;
  color: var(--admin-muted);
  margin: 0;
  font-weight: 400;
}

.cta-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 14px;
  background: var(--admin-primary);
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  border-radius: var(--admin-radius);
  border: none;
  cursor: pointer;
  transition: background 0.18s ease;
  white-space: nowrap;
  flex-shrink: 0;
}
.cta-btn:hover {
  background: var(--admin-primary-dark);
}

/* ─── Stats Grid ─────────────────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.stat-card {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius-lg);
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  position: relative;
  overflow: hidden;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
  text-decoration: none;
  color: inherit;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.stat-card.clickable { cursor: pointer; }

/* Decorative ring in corner */
.stat-ring {
  position: absolute;
  right: -18px;
  top: -18px;
  width: 80px;
  height: 80px;
  border-radius: 50%;
  opacity: 0.08;
}
.stat-ring-blue  { background: var(--admin-primary); }
.stat-ring-purple { background: rgb(139, 92, 246); }
.stat-ring-green  { background: rgb(34, 197, 94); }
.stat-ring-orange { background: rgb(249, 115, 22); }

.stat-icon-area {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stat-blue  .stat-icon-area { background: var(--admin-primary-soft);      color: var(--admin-primary); }
.stat-purple .stat-icon-area { background: rgba(139, 92, 246, 0.1);        color: rgb(139, 92, 246); }
.stat-green  .stat-icon-area { background: rgba(34, 197, 94, 0.1);         color: rgb(22, 163, 74); }
.stat-orange .stat-icon-area { background: rgba(249, 115, 22, 0.1);        color: rgb(234, 88, 12); }

.stat-body {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
  flex: 1;
}
.stat-number {
  font-size: 22px;
  font-weight: 700;
  color: var(--admin-text);
  line-height: 1;
  letter-spacing: -0.03em;
}
.stat-total {
  font-size: 14px;
  font-weight: 500;
  color: var(--admin-muted);
  letter-spacing: 0;
}
.stat-label {
  font-size: 12px;
  color: var(--admin-muted);
  font-weight: 500;
  white-space: nowrap;
}

/* ─── Panel Base ─────────────────────────────────────── */
.panel {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius-lg);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.panel-header {
  padding: 14px 18px;
  border-bottom: 1px solid var(--admin-border-soft);
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}
.panel-header-flat {
  border-bottom: 1px solid var(--admin-border-soft);
}

.panel-header-icon {
  width: 28px;
  height: 28px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.panel-icon-primary { background: var(--admin-primary-soft); color: var(--admin-primary); }
.panel-icon-bell    { background: rgba(139, 92, 246, 0.1);  color: rgb(109, 40, 217); }

.panel-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--admin-text);
  margin: 0;
}
.panel-subtitle {
  font-size: 12px;
  color: var(--admin-muted);
  margin: 2px 0 0;
}
.panel-header-text {
  display: flex;
  flex-direction: column;
}

.panel-body {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  flex: 1;
}

/* ─── Empty States ────────────────────────────────────── */
.panel-empty {
  padding: 36px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 6px;
  flex: 1;
  color: var(--admin-muted);
}
.panel-empty-lg { padding: 52px 20px; }
.panel-empty-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: var(--admin-bg-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 6px;
}
.panel-empty-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--admin-text);
  margin: 0;
}
.panel-empty-desc {
  font-size: 12px;
  color: var(--admin-muted);
  margin: 0;
  max-width: 260px;
  line-height: 1.5;
}
.panel-link {
  margin-top: 6px;
  color: var(--admin-primary);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
}
.panel-link:hover { text-decoration: underline; }

/* ─── Middle Row ─────────────────────────────────────── */
.dashboard-middle {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

/* ─── Shift Panel ────────────────────────────────────── */
.shift-status-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 12px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
}
.shift-status-bar.shift-status-scheduled   { background: var(--admin-bg-soft);            color: var(--admin-muted); }
.shift-status-bar.shift-status-checked_in  { background: rgba(34, 197, 94, 0.1);          color: rgb(21, 128, 61); }
.shift-status-bar.shift-status-checked_out { background: var(--admin-bg-soft);            color: var(--admin-muted); }
.shift-status-bar.shift-status-absent      { background: var(--admin-danger-soft);        color: var(--admin-danger); }
.shift-status-bar.shift-status-cancelled   { background: var(--admin-bg-soft);            color: var(--admin-muted); }

.shift-status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}
.shift-status-label { flex: 1; }
.shift-time-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  opacity: 0.85;
}

.shift-name-row { }
.shift-name {
  font-size: 17px;
  font-weight: 700;
  color: var(--admin-text);
  letter-spacing: -0.01em;
}

.shift-notes {
  background: var(--admin-bg-soft);
  border-left: 2px solid var(--admin-primary);
  border-radius: 0 6px 6px 0;
  padding: 10px 12px;
  font-size: 13px;
}
.shift-notes-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: var(--admin-muted);
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.shift-notes p {
  margin: 0;
  color: var(--admin-text);
  line-height: 1.5;
}

.attendance-timeline {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 0;
  border-top: 1px dashed var(--admin-border-soft);
}
.timeline-item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
}
.timeline-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.timeline-in  .timeline-dot { background: rgb(34, 197, 94); }
.timeline-out .timeline-dot { background: var(--admin-muted); }
.timeline-label { color: var(--admin-muted); flex: 1; }
.timeline-time {
  font-size: 12px;
  font-weight: 700;
  color: var(--admin-text);
  font-variant-numeric: tabular-nums;
}

.shift-actions {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.action-btn {
  width: 100%;
  height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  font-size: 13px;
  font-weight: 600;
  border-radius: var(--admin-radius);
  cursor: pointer;
  border: none;
  transition: background 0.15s ease, opacity 0.15s ease;
}
.action-btn:disabled { opacity: 0.45; cursor: not-allowed; }

.action-btn-checkin {
  background: var(--admin-primary);
  color: #fff;
}
.action-btn-checkin:hover:not(:disabled) { background: var(--admin-primary-dark); }

.action-btn-checkout {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border-soft);
  color: var(--admin-text);
}
.action-btn-checkout:hover:not(:disabled) { background: var(--admin-bg-soft); }

.shift-completed {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  color: rgb(21, 128, 61);
  font-size: 13px;
  font-weight: 600;
  background: rgba(34, 197, 94, 0.08);
  border-radius: var(--admin-radius);
  padding: 9px 12px;
}

.checkin-hint {
  font-size: 11px;
  color: var(--admin-muted);
  text-align: center;
  margin: 0;
  line-height: 1.4;
}

.spinner-mini {
  width: 13px;
  height: 13px;
  border: 2px solid transparent;
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── Notifications Panel ────────────────────────────── */
.unread-badge {
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background: var(--admin-primary);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.mark-read-btn {
  margin-left: auto;
  background: transparent;
  border: none;
  color: var(--admin-primary);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}
.mark-read-btn:hover { text-decoration: underline; }

.notif-list {
  display: flex;
  flex-direction: column;
  max-height: 340px;
  overflow-y: auto;
  flex: 1;
}
.notif-list::-webkit-scrollbar { width: 4px; }
.notif-list::-webkit-scrollbar-thumb { background: var(--admin-border); border-radius: 2px; }

.notif-item {
  padding: 13px 18px;
  border-bottom: 1px solid var(--admin-border-soft);
  display: flex;
  gap: 10px;
  align-items: flex-start;
  position: relative;
  cursor: pointer;
  transition: background 0.12s ease;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: var(--admin-bg-soft); }
.notif-item.is-unread { background: color-mix(in srgb, var(--admin-primary) 3%, transparent); }
.notif-item.is-unread:hover { background: color-mix(in srgb, var(--admin-primary) 6%, transparent); }

.notif-unread-dot {
  position: absolute;
  left: 7px;
  top: 18px;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--admin-primary);
  flex-shrink: 0;
}
.notif-content {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}
.notif-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--admin-text);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.notif-desc {
  font-size: 12px;
  color: var(--admin-muted);
  margin: 0;
  line-height: 1.45;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.notif-time {
  font-size: 10px;
  color: var(--admin-faint);
  margin-top: 3px;
}

/* ─── Court Availabilities ───────────────────────────── */
.court-panel {}

.courts-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
  padding: 18px;
}

.court-card {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: border-color 0.15s ease;
}
.court-card:hover {
  border-color: color-mix(in srgb, var(--admin-primary) 35%, var(--admin-border-soft));
}
.court-card.court-full {
  background: var(--admin-bg-soft);
  opacity: 0.82;
}

.court-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}
.court-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.court-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--admin-text);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.court-type {
  font-size: 11px;
  color: var(--admin-muted);
  font-weight: 500;
}

.court-status-pill {
  font-size: 10px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 20px;
  white-space: nowrap;
  flex-shrink: 0;
}
.pill-free { background: rgba(34, 197, 94, 0.12); color: rgb(21, 128, 61); }
.pill-full { background: var(--admin-border-soft);  color: var(--admin-muted); }

.court-card-body { display: flex; flex-direction: column; gap: 6px; flex: 1; }
.slots-label {
  font-size: 11px;
  color: var(--admin-muted);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.slot-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  max-height: 100px;
  overflow-y: auto;
}
.slot-chips::-webkit-scrollbar { width: 3px; }
.slot-chips::-webkit-scrollbar-thumb { background: var(--admin-border); border-radius: 2px; }

.slot-chip {
  font-size: 11px;
  font-weight: 600;
  color: var(--admin-text);
  background: var(--admin-bg-soft);
  border: 1px solid var(--admin-border-soft);
  padding: 3px 7px;
  border-radius: 5px;
  font-variant-numeric: tabular-nums;
}

.no-slots-msg {
  font-size: 12px;
  color: var(--admin-faint);
  margin: 0;
  line-height: 1.45;
}

/* ─── Responsive ─────────────────────────────────────── */
@media (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .courts-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  .dashboard-middle { grid-template-columns: 1fr; }
  .skeleton-middle-row { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .courts-grid { grid-template-columns: 1fr; }
  .page-header { flex-direction: column; align-items: flex-start; }
  .cta-btn { align-self: flex-start; }
}

@media (prefers-reduced-motion: reduce) {
  .stat-card, .court-card, .notif-item, .action-btn { transition: none; }
  .spinner-mini { animation: none; }
  @keyframes shimmer { 0%, 100% { background-position: 0 0; } }
}
</style>
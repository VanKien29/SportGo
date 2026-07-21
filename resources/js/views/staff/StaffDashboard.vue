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
      <div class="skeleton-main-with-sidebar">
        <div class="skeleton-main-column">
          <div class="skeleton-middle-row">
            <div class="skeleton-panel"></div>
            <div class="skeleton-panel"></div>
          </div>
          <div class="skeleton-full"></div>
        </div>
        <div class="skeleton-sidebar"></div>
      </div>
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

      <div class="dashboard-layout">
        <!-- MAIN CONTENT COLUMN -->
        <div class="dashboard-main-content">
          <!-- MIDDLE ROW: SHIFT + NOTIFICATIONS -->
          <div class="dashboard-middle">

            <!-- TODAY'S TASKS (TO-DO LIST) -->
            <section class="panel shift-panel tasks-panel">
              <header class="panel-header">
                <h2 class="panel-title">Nhiệm vụ hôm nay</h2>
              </header>

              <div class="panel-body todo-list-body">
                <!-- Shift Check-in Task -->
                <div v-if="stats.my_shift_today" class="todo-item" :class="{ 'todo-done': stats.my_shift_today.status !== 'scheduled' }">
                  <div class="todo-item-left">
                    <span class="todo-checkbox-styled" :class="{ 'checked': stats.my_shift_today.status !== 'scheduled' }" @click="stats.my_shift_today.status === 'scheduled' && canCheckIn(stats.my_shift_today) && handleCheckIn(stats.my_shift_today.id)">
                      <span v-if="stats.my_shift_today.status !== 'scheduled'" class="checkmark">✓</span>
                    </span>
                    <div class="todo-text">
                      <span class="todo-label">Check-in ca trực: {{ stats.my_shift_today.shift?.name || 'Ca trực' }}</span>
                      <small class="todo-desc">
                        {{ formatShiftTime(stats.my_shift_today) }}
                        <span v-if="stats.my_shift_today.check_in_at" class="todo-time-stamp">
                          · Vào lúc: {{ formatDateTime(stats.my_shift_today.check_in_at) }}
                        </span>
                      </small>
                    </div>
                  </div>
                  <div v-if="stats.my_shift_today.status === 'scheduled'" class="todo-action-area">
                    <button
                      type="button"
                      class="todo-mini-btn"
                      :disabled="actionLoading || !canCheckIn(stats.my_shift_today)"
                      @click="handleCheckIn(stats.my_shift_today.id)"
                    >
                      <span v-if="actionLoading" class="spinner-mini"></span>
                      <span v-else>Check-in</span>
                    </button>
                  </div>
                </div>

                <!-- Shift Check-out Task -->
                <div v-if="stats.my_shift_today" class="todo-item" :class="{ 'todo-done': stats.my_shift_today.status === 'checked_out' }">
                  <div class="todo-item-left">
                    <span class="todo-checkbox-styled" :class="{ 'checked': stats.my_shift_today.status === 'checked_out' }" @click="stats.my_shift_today.status === 'checked_in' && handleCheckOut(stats.my_shift_today.id)">
                      <span v-if="stats.my_shift_today.status === 'checked_out'" class="checkmark">✓</span>
                    </span>
                    <div class="todo-text">
                      <span class="todo-label">Check-out ca trực</span>
                      <small class="todo-desc">
                        Kết thúc ca làm việc
                        <span v-if="stats.my_shift_today.check_out_at" class="todo-time-stamp">
                          · Ra lúc: {{ formatDateTime(stats.my_shift_today.check_out_at) }}
                        </span>
                      </small>
                    </div>
                  </div>
                  <div v-if="stats.my_shift_today.status === 'checked_in'" class="todo-action-area">
                    <button
                      type="button"
                      class="todo-mini-btn btn-danger"
                      :disabled="actionLoading"
                      @click="handleCheckOut(stats.my_shift_today.id)"
                    >
                      <span v-if="actionLoading" class="spinner-mini"></span>
                      <span v-else>Check-out</span>
                    </button>
                  </div>
                </div>

                <!-- Local Static Tasks -->
                <div v-for="task in localTasks" :key="task.id" class="todo-item" :class="{ 'todo-done': task.done }">
                  <div class="todo-item-left">
                    <span class="todo-checkbox-styled" :class="{ 'checked': task.done }" @click="toggleLocalTask(task)">
                      <span v-if="task.done" class="checkmark">✓</span>
                    </span>
                    <div class="todo-text">
                      <span class="todo-label">{{ task.label }}</span>
                      <small class="todo-desc">Nhiệm vụ vận hành hàng ngày</small>
                    </div>
                  </div>
                </div>

                <!-- Lời nhắn từ chủ sân (nếu có) -->
                <div v-if="stats.my_shift_today && stats.my_shift_today.notes" class="todo-memo-box">
                  <AppIcon name="info" size="14" class="memo-icon" />
                  <div>
                    <strong>Lưu ý từ chủ sân:</strong>
                    <p>{{ stats.my_shift_today.notes }}</p>
                  </div>
                </div>

                <!-- Case: Không có ca trực hôm nay -->
                <div v-if="!stats.my_shift_today" class="todo-no-shift-info">
                  <AppIcon name="info" size="14" />
                  <span>Hôm nay bạn không có ca trực phân bổ.</span>
                </div>
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
        </div>

        <!-- SIDEBAR COLUMN -->
        <aside class="dashboard-sidebar">
          <section class="panel stats-sidebar-panel">
            <header class="panel-header">
              <h2 class="panel-title">Chỉ số hôm nay</h2>
            </header>
            <div class="panel-body stats-sidebar-list">
              <div class="stat-sidebar-item">
                <span class="stat-sidebar-label">Sân hoạt động</span>
                <strong class="stat-sidebar-value">{{ stats.active_courts_count }} / {{ stats.total_courts_count }}</strong>
              </div>
              <router-link to="/staff/bookings" class="stat-sidebar-item clickable" style="text-decoration:none;">
                <span class="stat-sidebar-label">Đơn đặt hôm nay</span>
                <strong class="stat-sidebar-value">{{ stats.today_bookings_count }}</strong>
              </router-link>
              <div class="stat-sidebar-item">
                <span class="stat-sidebar-label">Khách đang chơi</span>
                <strong class="stat-sidebar-value">{{ stats.playing_now_count }}</strong>
              </div>
              <router-link to="/staff/bookings" class="stat-sidebar-item clickable" style="text-decoration:none;">
                <span class="stat-sidebar-label">Khách sắp đến</span>
                <strong class="stat-sidebar-value">{{ stats.upcoming_bookings_count }}</strong>
              </router-link>
            </div>
          </section>
        </aside>
      </div>
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
  padding: 0;
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
  color: var(--admin-primary-text, #fff);
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

/* ─── Dashboard Layout ────────────────────────────────── */
.dashboard-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 20px;
}
@media (max-width: 1024px) {
  .dashboard-layout {
    grid-template-columns: 1fr;
  }
}
.dashboard-main-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.dashboard-sidebar {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.stats-sidebar-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 18px;
}
.stat-sidebar-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--admin-border-soft);
  color: var(--admin-text);
}
.stat-sidebar-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}
.stat-sidebar-item.clickable {
  cursor: pointer;
  transition: opacity 0.15s ease;
}
.stat-sidebar-item.clickable:hover {
  opacity: 0.8;
}
.stat-sidebar-label {
  font-size: 13px;
  color: var(--admin-muted);
  font-weight: 500;
}
.stat-sidebar-value {
  font-size: 16px;
  font-weight: 700;
  color: var(--admin-text);
}

/* ─── Skeleton Sidebar ────────────────────────────────── */
.skeleton-main-with-sidebar {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 20px;
}
@media (max-width: 1024px) {
  .skeleton-main-with-sidebar {
    grid-template-columns: 1fr;
  }
}
.skeleton-main-column {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.skeleton-sidebar {
  height: 220px;
  background: linear-gradient(90deg, var(--admin-border-soft) 25%, var(--admin-bg-soft) 50%, var(--admin-border-soft) 75%);
  background-size: 600px 100%;
  animation: shimmer 1.4s ease-in-out infinite;
  border-radius: var(--admin-radius-lg);
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
.stat-ring-purple { background: var(--admin-blue); }
.stat-ring-green  { background: var(--admin-success); }
.stat-ring-orange { background: var(--admin-warning); }

.stat-icon-area {
  width: 40px;
  height: 40px;
  border-radius: var(--admin-radius);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stat-blue  .stat-icon-area { background: var(--admin-primary-soft);      color: var(--admin-primary); }
.stat-purple .stat-icon-area { background: var(--admin-blue-soft); color: var(--admin-blue); }
.stat-green  .stat-icon-area { background: var(--admin-success-soft); color: var(--admin-success-text); }
.stat-orange .stat-icon-area { background: var(--admin-warning-soft); color: var(--admin-warning); }

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
  border-radius: var(--admin-radius-sm, var(--admin-radius));
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.panel-icon-primary { background: var(--admin-primary-soft); color: var(--admin-primary); }
.panel-icon-bell    { background: var(--admin-blue-soft); color: var(--admin-blue); }

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
  border-radius: var(--admin-radius-lg);
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

/* ─── Task List (To-Do List) ────────────────────────── */
.todo-list-body {
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
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
  background: var(--admin-surface);
  margin-top: 2px;
}
.todo-checkbox-styled.checked {
  background: var(--admin-primary);
  border-color: var(--admin-primary);
}
.todo-checkbox-styled .checkmark {
  color: #fff;
  font-size: 11px;
  font-weight: bold;
  line-height: 1;
}
.todo-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.todo-label {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--admin-text);
  line-height: 1.35;
  transition: color 0.15s ease, text-decoration 0.15s ease;
}
.todo-done .todo-label {
  color: var(--admin-muted);
  text-decoration: line-through;
}
.todo-desc {
  font-size: 11.5px;
  color: var(--admin-muted);
}
.todo-time-stamp {
  color: var(--admin-success-text);
  font-weight: 500;
}
.todo-action-area {
  flex-shrink: 0;
}
.todo-mini-btn {
  height: 28px;
  padding: 0 10px;
  font-size: 11.5px;
  font-weight: 600;
  border-radius: var(--admin-radius-sm, 4px);
  background: var(--admin-primary);
  color: #fff;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s ease;
}
.todo-mini-btn:hover:not(:disabled) {
  background: var(--admin-primary-dark);
}
.todo-mini-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.todo-mini-btn.btn-danger {
  background: var(--admin-danger-soft, #fef2f2);
  color: var(--admin-danger, #ef4444);
  border: 1px solid var(--admin-danger-soft);
}
.todo-mini-btn.btn-danger:hover:not(:disabled) {
  background: var(--admin-danger);
  color: #fff;
}
.todo-memo-box {
  background: var(--admin-bg-soft);
  border-left: 2px solid var(--admin-primary);
  border-radius: 0 6px 6px 0;
  padding: 10px 12px;
  font-size: 12.5px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 4px;
}
.todo-memo-box .memo-icon {
  margin-top: 2px;
  color: var(--admin-primary);
}
.todo-memo-box strong {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: var(--admin-muted);
  margin-bottom: 2px;
  text-transform: uppercase;
}
.todo-memo-box p {
  margin: 0;
  color: var(--admin-text);
  line-height: 1.45;
}
.todo-no-shift-info {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--admin-muted);
  background: var(--admin-bg-soft);
  padding: 8px 12px;
  border-radius: var(--admin-radius);
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
  border-radius: var(--admin-radius);
  background: var(--admin-primary);
  color: var(--admin-primary-text, #fff);
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
.pill-free { background: rgba(34, 197, 94, 0.12); color: var(--admin-success-text); }
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
  .courts-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  .dashboard-middle { grid-template-columns: 1fr; }
  .skeleton-middle-row { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
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
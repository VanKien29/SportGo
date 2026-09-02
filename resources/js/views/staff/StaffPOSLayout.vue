<template>
  <div class="pos-app-root" :class="{ 'is-fullscreen': isFullscreen }">
    <!-- TOP CLEAN POS HEADER (Zero Clutter, No Status Dots, Refined Typography) -->
    <header class="pos-header">
      <!-- 1. Left: Clean Brand & Simple Cluster Dropdown -->
      <div class="pos-header-left">
        <RouterLink to="/staff/bookings" class="pos-brand-link" title="SportGo POS">
          <div class="pos-brand-logo">SG</div>
          <div class="pos-brand-text">
            <span class="pos-brand-title">SportGo</span>
            <span class="pos-brand-tag">POS Terminal</span>
          </div>
        </RouterLink>

        <!-- Custom Cluster Dropdown (No icon, custom popover) -->
        <div class="pos-cluster-wrapper">
          <button
            type="button"
            class="pos-cluster-trigger"
            :class="{ active: showClusterDropdown, 'is-disabled': clusters.length <= 1 }"
            :disabled="clusters.length <= 1"
            aria-label="Chọn cụm sân"
            @click="showClusterDropdown = !showClusterDropdown"
          >
            <span class="pos-cluster-current-name">
              {{ selectedCluster?.name || 'Cụm sân' }}
            </span>
            <AppIcon
              v-if="clusters.length > 1"
              name="chevronDown"
              :size="13"
              class="pos-cluster-arrow"
              :class="{ 'is-open': showClusterDropdown }"
            />
          </button>

          <!-- Custom Popover Menu -->
          <div
            v-if="showClusterDropdown && clusters.length > 1"
            class="pos-cluster-popover"
          >
            <button
              v-for="cluster in clusters"
              :key="cluster.id"
              type="button"
              class="pos-cluster-option"
              :class="{ active: String(cluster.id) === String(selectedClusterId) }"
              @click="handleSelectCluster(cluster.id)"
            >
              <span>{{ cluster.name }}</span>
              <AppIcon
                v-if="String(cluster.id) === String(selectedClusterId)"
                name="check"
                :size="14"
                class="pos-cluster-check"
              />
            </button>
          </div>
        </div>
      </div>

      <!-- 2. Center: Minimalist Flat Navigation -->
      <nav class="pos-header-nav" aria-label="Điều hướng POS">
        <RouterLink
          to="/staff/bookings"
          class="pos-tab-link"
          :class="{ active: $route.name === 'staff-bookings' || $route.name === 'staff-counter-booking' }"
        >
          <AppIcon name="dashboard" :size="15" />
          <span>Bàn làm việc</span>
          <kbd class="pos-hotkey-badge">F1</kbd>
        </RouterLink>

        <RouterLink
          to="/staff/schedules"
          class="pos-tab-link"
          :class="{ active: $route.name === 'staff-schedules' }"
        >
          <AppIcon name="calendar" :size="15" />
          <span>Lịch trực &amp; Chấm công</span>
          <kbd class="pos-hotkey-badge">F4</kbd>
        </RouterLink>

        <RouterLink
          to="/staff/chat"
          class="pos-tab-link"
          :class="{ active: $route.name === 'staff-chat' }"
        >
          <AppIcon name="messageSquare" :size="15" />
          <span>Tin nhắn</span>
        </RouterLink>
      </nav>

      <!-- 3. Right: Clean Clock, Attendance Action, Shortcuts, Fullscreen, Profile -->
      <div class="pos-header-right">
        <!-- Understated Realtime Clock -->
        <div class="pos-live-clock" :title="currentDateString">
          <span class="pos-clock-time">{{ currentTimeString }}</span>
          <span class="pos-clock-date">{{ currentDateShortString }}</span>
        </div>

        <!-- Shift Attendance Action -->
        <div class="pos-attendance-box">
          <template v-if="todaySchedule">
            <div class="pos-shift-info">
              <span class="pos-shift-name">{{ todaySchedule.shift?.name || 'Ca hôm nay' }}</span>
              <span class="pos-shift-hours">
                {{ (todaySchedule.start_time || todaySchedule.shift?.start_time || '').slice(0, 5) }} - 
                {{ (todaySchedule.end_time || todaySchedule.shift?.end_time || '').slice(0, 5) }}
              </span>
            </div>

            <button
              v-if="!todaySchedule.check_in_at"
              type="button"
              class="pos-attend-btn pos-attend-btn--in"
              :disabled="shiftActionLoading"
              @click="handleCheckIn"
            >
              <AppIcon name="logIn" :size="13" />
              <span>{{ shiftActionLoading ? 'Đang xử lý...' : 'Vào ca' }}</span>
            </button>
            <button
              v-else-if="!todaySchedule.check_out_at"
              type="button"
              class="pos-attend-btn pos-attend-btn--out"
              :disabled="shiftActionLoading"
              @click="handleCheckOut"
            >
              <AppIcon name="logOut" :size="13" />
              <span>{{ shiftActionLoading ? 'Đang xử lý...' : 'Kết ca' }}</span>
            </button>
            <div v-else class="pos-attend-done">
              <AppIcon name="check" :size="13" />
              <span>Đã hoàn thành</span>
            </div>
          </template>
          <template v-else>
            <span class="pos-no-shift">Không có ca trực</span>
          </template>
        </div>

        <!-- Shortcut Help Trigger -->
        <button
          type="button"
          class="pos-icon-btn"
          title="Phím tắt POS (F1, F2, F3, F4, ESC)"
          @click="showHotkeyModal = true"
        >
          <AppIcon name="command" :size="15" />
        </button>

        <!-- Fullscreen Trigger -->
        <button
          type="button"
          class="pos-icon-btn"
          :title="isFullscreen ? 'Thu nhỏ cửa sổ' : 'Toàn màn hình (F11)'"
          @click="toggleFullscreen"
        >
          <AppIcon :name="isFullscreen ? 'minimize' : 'maximize'" :size="15" />
        </button>

        <!-- User Profile Dropdown -->
        <div class="pos-user-wrapper">
          <button
            type="button"
            class="pos-user-btn"
            :class="{ active: showUserMenu }"
            @click="showUserMenu = !showUserMenu"
          >
            <span class="pos-user-avatar">{{ userInitial }}</span>
            <div class="pos-user-meta">
              <span class="pos-user-name">{{ userName }}</span>
              <span class="pos-user-role">Nhân viên</span>
            </div>
            <AppIcon name="chevronDown" :size="13" />
          </button>

          <!-- User Menu Dropdown -->
          <div v-if="showUserMenu" class="pos-user-popover">
            <div class="pos-popover-head">
              <strong>{{ userName }}</strong>
              <small>{{ userEmail }}</small>
            </div>
            <RouterLink
              to="/staff/schedules"
              class="pos-popover-item"
              @click="showUserMenu = false"
            >
              <AppIcon name="calendar" :size="14" />
              <span>Bảng chấm công cá nhân</span>
            </RouterLink>
            <RouterLink
              to="/"
              class="pos-popover-item"
              @click="showUserMenu = false"
            >
              <AppIcon name="externalLink" :size="14" />
              <span>Về trang khách hàng / Đặt sân</span>
            </RouterLink>
            <button
              type="button"
              class="pos-popover-item is-danger"
              @click="handleLogout"
            >
              <AppIcon name="logOut" :size="14" />
              <span>Đăng xuất tài khoản</span>
            </button>
          </div>
        </div>
      </div>
    </header>

    <div
      v-if="!todayScheduleLoading && !hasActiveShift"
      class="pos-shift-access-banner"
      role="status"
    >
      <AppIcon name="lock" :size="15" />
      <span>{{ shiftAccessMessage }}</span>
    </div>

    <!-- MAIN POS WORKSPACE -->
    <main class="pos-main-content">
      <router-view />
    </main>

    <!-- MOBILE BOTTOM NAVIGATION (Only visible on screens < 900px) -->
    <nav class="pos-mobile-bottom-nav">
      <RouterLink
        to="/staff/bookings"
        class="pos-mob-tab"
        :class="{ active: $route.name === 'staff-bookings' || $route.name === 'staff-counter-booking' }"
      >
        <AppIcon name="dashboard" :size="18" />
        <span>Bàn làm việc</span>
      </RouterLink>

      <RouterLink
        to="/staff/schedules"
        class="pos-mob-tab"
        :class="{ active: $route.name === 'staff-schedules' }"
      >
        <AppIcon name="calendar" :size="18" />
        <span>Lịch trực</span>
      </RouterLink>

      <RouterLink
        to="/staff/chat"
        class="pos-mob-tab"
        :class="{ active: $route.name === 'staff-chat' }"
      >
        <AppIcon name="messageSquare" :size="18" />
        <span>Tin nhắn</span>
      </RouterLink>
    </nav>

    <!-- HOTKEY MODAL -->
    <Teleport to="body">
      <div
        v-if="showHotkeyModal"
        class="pos-modal-backdrop"
        @click.self="showHotkeyModal = false"
      >
        <div class="pos-hotkey-dialog">
          <div class="pos-modal-head">
            <div class="pos-modal-head-title">
              <AppIcon name="command" :size="18" />
              <h3>Bảng phím tắt quầy POS</h3>
            </div>
            <button
              type="button"
              class="pos-modal-close"
              aria-label="Đóng"
              @click="showHotkeyModal = false"
            >
              ✕
            </button>
          </div>

          <div class="pos-modal-body">
            <div class="pos-hotkey-grid">
              <div class="pos-hotkey-item">
                <kbd>F1</kbd>
                <div>
                  <strong>Bàn làm việc / Đặt sân nhanh</strong>
                  <span>Chuyển nhanh về màn hình ma trận sân</span>
                </div>
              </div>

              <div class="pos-hotkey-item">
                <kbd>F2 / Ctrl+K</kbd>
                <div>
                  <strong>Tìm kiếm nhanh</strong>
                  <span>Nhảy chuột vào ô tìm Tên, SĐT, Mã đơn</span>
                </div>
              </div>

              <div class="pos-hotkey-item">
                <kbd>F3</kbd>
                <div>
                  <strong>Tải lại dữ liệu sân</strong>
                  <span>Cập nhật trạng thái sân thời gian thực</span>
                </div>
              </div>

              <div class="pos-hotkey-item">
                <kbd>F4</kbd>
                <div>
                  <strong>Lịch trực &amp; Chấm công</strong>
                  <span>Xem lịch ca và điểm danh vào/kết ca</span>
                </div>
              </div>

              <div class="pos-hotkey-item">
                <kbd>F11</kbd>
                <div>
                  <strong>Toàn màn hình</strong>
                  <span>Mở rộng tối đa không gian làm việc</span>
                </div>
              </div>

              <div class="pos-hotkey-item">
                <kbd>ESC</kbd>
                <div>
                  <strong>Đóng cửa sổ</strong>
                  <span>Đóng drawer hoặc hộp thoại đang mở</span>
                </div>
              </div>
            </div>
          </div>

          <div class="pos-modal-foot">
            <button
              type="button"
              class="pos-btn-got-it"
              @click="showHotkeyModal = false"
            >
              Đã hiểu
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- SHIFT HANDOVER & END-OF-SHIFT MODAL -->
    <ShiftHandoverModal
      :is-open="showHandoverModal"
      :schedule-id="handoverScheduleId"
      @close="showHandoverModal = false"
      @checked-out="onShiftCheckedOut"
    />
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ShiftHandoverModal from '../../components/staff/ShiftHandoverModal.vue';
import { venueClusterService } from '../../services/venueClusters.js';
import { ownerStaffShiftService } from '../../services/ownerStaffShiftService.js';
import { getAuth, logout } from '../../stores/auth.js';
import { staffNavigationSections } from '../../config/staffNavigation.js';
import { canAccessStaffRoute, firstAccessibleStaffRoute } from '../../config/permissionAccess.js';
import { BUSINESS_TIMEZONE, businessDateString, businessMinutes, businessTimeString } from '../../utils/businessTime.js';
import { useToast } from 'vue-toastification';

const SELECTED_CLUSTER_KEY = 'selected_cluster';
const CACHED_CLUSTERS_KEY = 'cached_clusters';

export default {
  name: 'StaffPOSLayout',
  components: { AppIcon, ShiftHandoverModal },
  setup() {
    return { toast: useToast() };
  },
  data() {
    let initialClusters = [];
    try {
      initialClusters = JSON.parse(localStorage.getItem(CACHED_CLUSTERS_KEY) || '[]');
    } catch (e) {}
    const savedClusterId = localStorage.getItem(SELECTED_CLUSTER_KEY) || initialClusters[0]?.id || '';

    return {
      clusters: initialClusters,
      selectedClusterId: String(savedClusterId),
      clusterLoading: false,
      todaySchedule: null,
      todayScheduleLoading: false,
      shiftActionLoading: false,
      showUserMenu: false,
      showClusterDropdown: false,
      showHotkeyModal: false,
      showHandoverModal: false,
      handoverScheduleId: null,
      isFullscreen: false,
      currentTime: new Date(),
      clockTimer: null,
      lastRestrictionNoticeKey: '',
    };
  },
  computed: {
    user() {
      return getAuth() || {};
    },
    userName() {
      return this.user.fullName || this.user.full_name || this.user.username || 'Nhân viên';
    },
    userEmail() {
      return this.user.email || '';
    },
    userInitial() {
      return this.userName.charAt(0).toUpperCase();
    },
    selectedCluster() {
      return this.clusters.find((c) => String(c.id) === String(this.selectedClusterId)) || null;
    },
    currentTimeString() {
      return businessTimeString(this.currentTime, true);
    },
    currentDateShortString() {
      const [year, month, day] = businessDateString(this.currentTime).split('-');
      return `${day}/${month}/${year}`;
    },
    currentDateString() {
      const dayName = new Intl.DateTimeFormat('vi-VN', { weekday: 'long', timeZone: BUSINESS_TIMEZONE }).format(this.currentTime);
      return `${dayName}, ${this.currentDateShortString}`;
    },
    hasActiveShift() {
      const schedule = this.todaySchedule;
      if (!schedule || schedule.check_out_at || ['checked_out', 'absent', 'cancelled'].includes(schedule.status)) {
        return false;
      }

      if (String(schedule.date).slice(0, 10) !== businessDateString(this.currentTime)) {
        return false;
      }

      const startTime = schedule.start_time || schedule.shift?.start_time || '';
      const endTime = schedule.end_time || schedule.shift?.end_time || '';
      const [startHour, startMinute] = String(startTime).split(':').map(Number);
      const [endHour, endMinute] = String(endTime).split(':').map(Number);
      const start = startHour * 60 + startMinute;
      const end = endHour * 60 + endMinute;
      const now = businessMinutes(this.currentTime);

      return Number.isFinite(start) && Number.isFinite(end) && now >= start && now < end;
    },
    shiftAccessMessage() {
      if (!this.todaySchedule) {
        return 'Bạn chưa được phân ca trực hôm nay. Các thao tác vận hành booking đang tạm khóa.';
      }

      const start = (this.todaySchedule.start_time || this.todaySchedule.shift?.start_time || '').slice(0, 5);
      const end = (this.todaySchedule.end_time || this.todaySchedule.shift?.end_time || '').slice(0, 5);
      return `Bạn đang ngoài thời gian ca trực (${start} - ${end}). Các thao tác vận hành booking đang tạm khóa.`;
    },
  },
  async mounted() {
    this.clockTimer = setInterval(() => {
      this.currentTime = new Date();
    }, 1000);

    window.addEventListener('keydown', this.handleGlobalKeydown);
    window.addEventListener('owner-cluster-changed', this.syncExternalCluster);
    window.addEventListener('focus', this.refreshClusterStatuses);
    window.addEventListener('click', this.handleOutsideClick);
    document.addEventListener('visibilitychange', this.handleVisibilityChange);
    document.addEventListener('fullscreenchange', this.onFullscreenChange);

    await this.loadClusters();
    await this.loadTodayShift();
  },
  beforeUnmount() {
    if (this.clockTimer) clearInterval(this.clockTimer);
    window.removeEventListener('keydown', this.handleGlobalKeydown);
    window.removeEventListener('owner-cluster-changed', this.syncExternalCluster);
    window.removeEventListener('focus', this.refreshClusterStatuses);
    window.removeEventListener('click', this.handleOutsideClick);
    document.removeEventListener('visibilitychange', this.handleVisibilityChange);
    document.removeEventListener('fullscreenchange', this.onFullscreenChange);
  },
  methods: {
    async loadClusters() {
      this.clusterLoading = true;
      try {
        const response = await venueClusterService.getClusters({ compact: 1 });
        this.clusters = response.data || [];
        localStorage.setItem(CACHED_CLUSTERS_KEY, JSON.stringify(this.clusters));
        const savedId = localStorage.getItem(SELECTED_CLUSTER_KEY);
        const fallback = this.clusters[0]?.id || '';
        const hasSavedCluster = this.clusters.some((cluster) => String(cluster.id) === String(savedId));
        this.selectedClusterId = String(hasSavedCluster ? savedId : fallback);
        this.persistCluster({ notify: !hasSavedCluster });
        this.ensureCurrentRouteAllowed();
        this.notifyClusterRestriction();
      } finally {
        this.clusterLoading = false;
      }
    },
    handleVisibilityChange() {
      if (!document.hidden) this.refreshClusterStatuses();
    },
    async refreshClusterStatuses() {
      if (this.clusterLoading) return;

      try {
        const response = await venueClusterService.getClusters({ compact: 1 });
        const latestById = new Map((response.data || []).map((cluster) => [String(cluster.id), cluster]));
        this.clusters = this.clusters.map((cluster) => {
          const latest = latestById.get(String(cluster.id));
          return latest
            ? { ...cluster, status: latest.status, status_reason: latest.status_reason, access_restriction: latest.access_restriction }
            : cluster;
        });
        localStorage.setItem(CACHED_CLUSTERS_KEY, JSON.stringify(this.clusters));
        this.notifyClusterRestriction();
      } catch {
        // Keep the current POS state if a background refresh is unavailable.
      }
    },
    changeCluster(clusterId) {
      this.selectedClusterId = clusterId;
      this.persistCluster();
      this.ensureCurrentRouteAllowed();
      this.notifyClusterRestriction();
      this.loadTodayShift();
    },
    persistCluster({ notify = true } = {}) {
      if (!this.selectedClusterId) return;
      localStorage.setItem(SELECTED_CLUSTER_KEY, this.selectedClusterId);
      if (!notify) return;
      window.dispatchEvent(new CustomEvent('owner-cluster-changed', {
        detail: this.selectedCluster,
      }));
    },
    syncExternalCluster(event) {
      const clusterId = event.detail?.id;
      if (!clusterId || String(clusterId) === String(this.selectedClusterId)) return;
      if (!this.clusters.some((cluster) => String(cluster.id) === String(clusterId))) return;
      this.selectedClusterId = clusterId;
      localStorage.setItem(SELECTED_CLUSTER_KEY, clusterId);
      this.ensureCurrentRouteAllowed();
      this.notifyClusterRestriction();
      this.loadTodayShift();
    },
    notifyClusterRestriction() {
      const cluster = this.selectedCluster;
      const restriction = cluster?.access_restriction;
      const status = cluster?.status;
      const isRestricted = Boolean(restriction?.access_mode === 'blocked'
        || restriction?.access_mode === 'limited'
        || ['pending', 'locked', 'termination_locked', 'termination_processing', 'partner_terminated'].includes(status));

      if (!cluster || !isRestricted) {
        this.lastRestrictionNoticeKey = '';
        return;
      }

      const reason = String(restriction?.reason || cluster.status_reason || '').trim();
      let title = 'Cụm sân đang bị hạn chế';
      if (status === 'pending') title = 'Cụm sân chưa sẵn sàng';
      else if (restriction?.access_mode === 'limited') title = 'Cụm sân đang bị giới hạn quyền';
      else if (restriction?.restriction_type === 'platform_fee_overdue') title = 'Cụm sân đang bị khóa do phí nền tảng';
      else if (['termination_locked', 'termination_processing', 'partner_terminated'].includes(status)
        || restriction?.restriction_type === 'contract_termination') title = 'Cụm sân đang chấm dứt hợp đồng';
      else if (restriction?.restriction_type === 'admin_manual') title = 'Cụm sân bị quản trị viên khóa';

      const message = `${cluster.name || 'Cụm sân'}: ${title}.${reason ? ` Lý do: ${reason.replace(/[.\s]+$/, '')}.` : ' Lý do chưa được cập nhật.'}`;
      const noticeKey = `${cluster.id}:${status}:${restriction?.access_mode || ''}:${reason}`;
      if (noticeKey === this.lastRestrictionNoticeKey) return;
      this.lastRestrictionNoticeKey = noticeKey;

      if (restriction?.access_mode === 'limited') this.toast.warning(message, { timeout: 8000 });
      else this.toast.error(message, { timeout: 8000 });
    },
    ensureCurrentRouteAllowed() {
      const auth = getAuth();
      if (canAccessStaffRoute(this.$route.name, auth, this.selectedClusterId)) return;

      const destination = firstAccessibleStaffRoute(
        auth,
        this.selectedClusterId,
        staffNavigationSections,
      );
      if (destination && destination !== this.$route.path) {
        this.$router.replace(destination);
      }
    },
    async loadTodayShift() {
      this.todayScheduleLoading = true;
      try {
        const todayStr = businessDateString(this.currentTime);

        const res = await ownerStaffShiftService.mySchedules({
          start_date: todayStr,
          end_date: todayStr,
        });
        const schedules = (res.data || []).filter((schedule) =>
          !this.selectedClusterId
          || String(schedule.venue_cluster_id) === String(this.selectedClusterId),
        );
        if (!schedules.length) {
          this.todaySchedule = null;
          return;
        }

        const nowMinutes = businessMinutes(this.currentTime);

        // Prefer the shift whose actual assigned hours contain the current time.
        // The 30-minute early check-in window must not grant operation access.
        const currentOngoing = schedules.find((s) => {
          if (s.check_out_at || ['checked_out', 'absent', 'cancelled'].includes(s.status)) return false;
          const startTime = s.start_time || s.shift?.start_time || '00:00';
          const endTime = s.end_time || s.shift?.end_time || '23:59';
          const [sh, sm] = startTime.split(':').map(Number);
          const [eh, em] = endTime.split(':').map(Number);
          return nowMinutes >= sh * 60 + sm && nowMinutes < eh * 60 + em;
        });
        if (currentOngoing) {
          this.todaySchedule = currentOngoing;
          return;
        }

        // Keep a checked-in schedule visible for attendance, but it remains
        // locked for operations when its assigned time has ended.
        const activeCheckedIn = schedules.find((s) => s.check_in_at && !s.check_out_at);
        if (activeCheckedIn) {
          this.todaySchedule = activeCheckedIn;
          return;
        }

        // 3. Find next upcoming shift today that hasn't checked in yet
        const upcomingShift = schedules.find((s) => {
          if (s.check_out_at) return false;
          const startTime = s.start_time || s.shift?.start_time || '00:00';
          const [sh, sm] = startTime.split(':').map(Number);
          return (sh * 60 + sm) > nowMinutes;
        });
        if (upcomingShift) {
          this.todaySchedule = upcomingShift;
          return;
        }

        // 4. If all shifts today are done or past, show the latest one
        this.todaySchedule = schedules[schedules.length - 1] || schedules[0] || null;
      } catch (e) {
        console.warn('Failed to load today shift:', e);
      } finally {
        this.todayScheduleLoading = false;
      }
    },
    async handleCheckIn() {
      if (!this.todaySchedule) return;
      this.shiftActionLoading = true;
      try {
        await ownerStaffShiftService.checkIn(this.todaySchedule.id);
        await this.loadTodayShift();
        window.dispatchEvent(new CustomEvent('staff-attendance-updated'));
      } catch (e) {
        alert(e.message || 'Chấm công vào ca thất bại.');
      } finally {
        this.shiftActionLoading = false;
      }
    },
    async handleCheckOut() {
      if (!this.todaySchedule) return;
      this.handoverScheduleId = this.todaySchedule.id;
      this.showHandoverModal = true;
    },
    async onShiftCheckedOut() {
      this.showHandoverModal = false;
      await this.loadTodayShift();
      window.dispatchEvent(new CustomEvent('staff-attendance-updated'));
    },
    toggleFullscreen() {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
      } else if (document.exitFullscreen) {
        document.exitFullscreen().catch(() => {});
      }
    },
    onFullscreenChange() {
      this.isFullscreen = Boolean(document.fullscreenElement);
    },
    handleSelectCluster(clusterId) {
      this.showClusterDropdown = false;
      if (String(clusterId) === String(this.selectedClusterId)) return;
      this.changeCluster(clusterId);
    },
    handleOutsideClick(e) {
      if (!e.target.closest('.pos-user-wrapper')) {
        this.showUserMenu = false;
      }
      if (!e.target.closest('.pos-cluster-wrapper')) {
        this.showClusterDropdown = false;
      }
    },
    handleGlobalKeydown(e) {
      const isInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName);

      if (e.key === 'F1') {
        e.preventDefault();
        if (this.$route.name !== 'staff-bookings') {
          this.$router.push('/staff/bookings');
        }
        setTimeout(() => {
          window.dispatchEvent(new CustomEvent('staff-pos-hotkey', { detail: 'quick-booking' }));
        }, 100);
      } else if (e.key === 'F2' || (e.ctrlKey && e.key.toLowerCase() === 'k')) {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('staff-pos-hotkey', { detail: 'search' }));
      } else if (e.key === 'F3') {
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('staff-pos-hotkey', { detail: 'refresh' }));
      } else if (e.key === 'F4') {
        e.preventDefault();
        if (this.$route.name !== 'staff-schedules') {
          this.$router.push('/staff/schedules');
        }
      } else if (e.key === 'Escape') {
        this.showHotkeyModal = false;
        this.showUserMenu = false;
        this.showClusterDropdown = false;
        window.dispatchEvent(new CustomEvent('staff-pos-hotkey', { detail: 'escape' }));
      }
    },
    async handleLogout() {
      if (confirm('Bạn có chắc chắn muốn đăng xuất khỏi hệ thống POS?')) {
        await logout();
        this.$router.push('/login');
      }
    },
  },
};
</script>

<style scoped>
.pos-app-root {
  min-height: 100vh;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  color: #0f172a;
  font-family: inherit;
  scrollbar-gutter: stable;
  overflow-x: hidden;
  max-width: 100vw;
  box-sizing: border-box;
}

.pos-app-root:has(.admin-chat-page) {
  height: 100vh;
  max-height: 100vh;
  overflow: hidden;
}

.pos-main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  min-width: 0;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  overflow-x: hidden;
}

.pos-shift-access-banner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 36px;
  padding: 8px 16px;
  color: #92400e;
  background: #fffbeb;
  border-bottom: 1px solid #fde68a;
  font-size: 12px;
  font-weight: 600;
  text-align: center;
}

.pos-main-content:has(.admin-chat-page) {
  height: calc(100vh - 56px);
  max-height: calc(100vh - 56px);
  overflow: hidden;
}

@media (max-width: 900px) {
  .pos-main-content {
    padding-bottom: 58px;
    box-sizing: border-box;
  }

  .pos-main-content:has(.admin-chat-page) {
    height: calc(100vh - 50px - 58px);
    max-height: calc(100vh - 50px - 58px);
    padding-bottom: 0;
    overflow: hidden;
  }
}

/* TOP CLEAN HEADER */
.pos-header {
  height: 56px;
  background: #ffffff;
  color: #0f172a;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  position: sticky;
  top: 0;
  z-index: 50;
  border-bottom: 1px solid #f1f5f9;
}

/* LEFT BRAND & CLUSTER SELECTOR */
.pos-header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.pos-brand-link {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: #0f172a;
}

.pos-brand-logo {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: #087642;
  color: #ffffff;
  font-weight: 700;
  font-size: 13px;
  border-radius: 8px;
}

.pos-brand-text {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}

.pos-brand-title {
  font-size: 14.5px;
  font-weight: 600;
  color: #0f172a;
}

.pos-brand-tag {
  font-size: 10px;
  font-weight: 600;
  color: #087642;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

/* CUSTOM CLUSTER DROPDOWN */
.pos-cluster-wrapper {
  position: relative;
  display: inline-flex;
}

.pos-cluster-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  background: #f8fafc;
  border: 1px solid transparent;
  border-radius: 6px;
  padding: 6px 12px;
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
  cursor: pointer;
  width: 185px;
  box-sizing: border-box;
  transition: all 0.12s ease;
}

.pos-cluster-trigger:hover:not(:disabled),
.pos-cluster-trigger.active {
  border-color: rgba(8, 118, 66, 0.2);
  color: #087642;
  background: #f0fdf4;
}

.pos-cluster-trigger.is-disabled {
  cursor: default;
}

.pos-cluster-current-name {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  text-align: left;
}

.pos-cluster-arrow {
  color: #64748b;
  flex-shrink: 0;
  transition: transform 0.15s ease;
}

.pos-cluster-arrow.is-open {
  transform: rotate(180deg);
  color: #087642;
}

.pos-cluster-popover {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  min-width: 210px;
  background: #ffffff;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
  padding: 4px;
  z-index: 100;
  display: flex;
  flex-direction: column;
  gap: 2px;
  border: 1px solid #e5e7eb;
  animation: popover-fade 0.15s ease;
}

@keyframes popover-fade {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

.pos-cluster-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 8px 10px;
  font-size: 13px;
  font-weight: 500;
  color: #0f172a;
  background: transparent;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  width: 100%;
  text-align: left;
  transition: background 0.12s ease, color 0.12s ease;
}

.pos-cluster-option:hover {
  background: #f0fdf4;
  color: #087642;
}

.pos-cluster-option.active {
  background: #087642;
  color: #ffffff;
  font-weight: 500;
}

.pos-cluster-check {
  color: #ffffff;
  flex-shrink: 0;
}

/* CENTER FLAT NAVIGATION */
.pos-header-nav {
  display: flex;
  align-items: center;
  gap: 6px;
}

.pos-tab-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #334155;
  text-decoration: none;
  background: transparent;
  border: 1px solid transparent;
  transition: all 0.12s ease;
  white-space: nowrap;
}

.pos-tab-link:hover {
  color: #087642;
  background: #f0fdf4;
  border-color: #bbf7d0;
}

.pos-tab-link.active {
  background: #087642;
  color: #ffffff;
  border-color: #087642;
  font-weight: 500;
}

.pos-hotkey-badge {
  display: inline-block;
  padding: 1px 5px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 9.5px;
  font-family: monospace;
  font-weight: 600;
  border-radius: 3px;
  line-height: 1.2;
}

.pos-tab-link.active .pos-hotkey-badge {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

/* RIGHT UTILITIES */
.pos-header-right {
  display: flex;
  align-items: center;
  gap: 14px;
}

.pos-live-clock {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  line-height: 1.2;
}

.pos-clock-time {
  font-family: ui-monospace, SFMono-Regular, monospace;
  font-size: 13.5px;
  font-weight: 700;
  color: #0f172a;
}

.pos-clock-date {
  font-size: 11.5px;
  color: #334155;
  font-weight: 500;
}

/* ATTENDANCE ACTION */
.pos-attendance-box {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.pos-shift-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  line-height: 1.2;
}

.pos-shift-name {
  font-size: 12px;
  font-weight: 600;
  color: #0f172a;
}

.pos-shift-hours {
  font-size: 11px;
  color: #334155;
  font-weight: 500;
}

.pos-attend-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  white-space: nowrap;
  flex-shrink: 0;
  transition: all 0.15s ease;
}

.pos-attend-btn--in {
  background: #166534;
  color: #ffffff;
}

.pos-attend-btn--in:hover:not(:disabled) {
  background: #14532d;
}

.pos-attend-btn--out {
  background: #dc2626;
  color: #ffffff;
}

.pos-attend-btn--out:hover:not(:disabled) {
  background: #b91c1c;
}

.pos-attend-done {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #166534;
  font-size: 11.5px;
  font-weight: 600;
  background: #f4f8f5;
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid #cbdcd0;
  white-space: nowrap;
  flex-shrink: 0;
}

.pos-no-shift {
  color: #64748b;
  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;
}

.pos-icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: #f8fafc;
  color: #334155;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.pos-icon-btn:hover {
  background: #f0fdf4;
  color: #087642;
}

/* USER PROFILE */
.pos-user-wrapper {
  position: relative;
  flex-shrink: 0;
}

.pos-user-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  padding: 4px 10px;
  border-radius: 8px;
  cursor: pointer;
  color: #0f172a;
  transition: all 0.15s ease;
  height: 38px;
  max-width: 220px;
  box-sizing: border-box;
  flex-shrink: 0;
}

.pos-user-btn:hover,
.pos-user-btn.active {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #087642;
}

.pos-user-avatar {
  width: 28px !important;
  height: 28px !important;
  min-width: 28px !important;
  max-width: 28px !important;
  flex-shrink: 0 !important;
  background: #087642;
  color: #ffffff;
  border: none;
  font-weight: 600;
  font-size: 12.5px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.pos-user-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  line-height: 1.2;
  min-width: 0;
  max-width: 140px;
  overflow: hidden;
}

.pos-user-name {
  font-size: 12.5px;
  font-weight: 600;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.pos-user-role {
  font-size: 10.5px;
  color: #64748b;
  font-weight: 500;
  white-space: nowrap;
}

.pos-user-popover {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  width: max-content;
  min-width: 235px;
  max-width: min(300px, calc(100vw - 16px));
  background: #ffffff;
  border-radius: 8px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
  padding: 6px;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  gap: 2px;
  border: 1px solid #e5e7eb;
  box-sizing: border-box;
}

.pos-popover-head {
  padding: 6px 10px;
  display: flex;
  flex-direction: column;
  border-bottom: 1px solid #f1f5f9;
  margin-bottom: 4px;
}

.pos-popover-head strong {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pos-popover-head small {
  font-size: 11.5px;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pos-popover-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  font-size: 12.5px;
  color: #334155;
  text-decoration: none;
  background: transparent;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  width: 100%;
  text-align: left;
  transition: all 0.12s ease;
  white-space: nowrap;
  box-sizing: border-box;
}

.pos-popover-item span {
  white-space: nowrap;
}

.pos-popover-item:hover {
  background: #f0fdf4;
  color: #087642;
}

.pos-popover-item.is-danger:hover {
  background: #fee2e2;
  color: #dc2626;
}

/* MAIN CONTENT */
.pos-main-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: #ffffff;
}

/* MODAL BACKDROP */
.pos-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  backdrop-filter: blur(3px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.pos-hotkey-dialog {
  background: #ffffff;
  border-radius: 12px;
  width: 100%;
  max-width: 540px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #e5e7eb;
}

.pos-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.pos-modal-head-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #087642;
}

.pos-modal-head-title h3 {
  font-size: 15px;
  font-weight: 600;
  margin: 0;
  color: #0f172a;
}

.pos-hotkey-hero {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 20px;
  background: #ffffff;
  border-bottom: 1px solid #e5e7eb;
}

.pos-hotkey-3d-img {
  width: 68px;
  height: 68px;
  object-fit: contain;
  border-radius: 8px;
  filter: drop-shadow(0 6px 14px rgba(8, 118, 66, 0.15));
  animation: float-slow 3s ease-in-out infinite alternate;
}

@keyframes float-slow {
  from { transform: translateY(0); }
  to { transform: translateY(-3px); }
}

.pos-hotkey-hero-text {
  display: flex;
  flex-direction: column;
}

.pos-hotkey-hero-kicker {
  font-size: 10px;
  font-weight: 700;
  color: #087642;
  letter-spacing: 0.05em;
}

.pos-hotkey-hero-text h4 {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  margin: 2px 0;
}

.pos-hotkey-hero-text p {
  font-size: 11.5px;
  color: #64748b;
  margin: 0;
}

.pos-modal-close {
  background: transparent;
  border: none;
  font-size: 16px;
  cursor: pointer;
  color: #64748b;
  padding: 2px;
}

.pos-modal-close:hover {
  color: #0f172a;
}

.pos-modal-body {
  padding: 16px 20px;
}

.pos-hotkey-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.pos-hotkey-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px;
  border-radius: 6px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
}

.pos-hotkey-item kbd {
  display: inline-block;
  padding: 2px 6px;
  background: #087642;
  color: #ffffff;
  font-size: 11px;
  font-family: monospace;
  font-weight: 600;
  border-radius: 4px;
  flex-shrink: 0;
}

.pos-hotkey-item div {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}

.pos-hotkey-item strong {
  font-size: 12.5px;
  font-weight: 600;
  color: #0f172a;
}

.pos-hotkey-item span {
  font-size: 11px;
  color: #64748b;
}

.pos-modal-foot {
  padding: 12px 20px;
  background: #ffffff;
  border-top: 1px solid #e5e7eb;
  display: flex;
  justify-content: flex-end;
}

.pos-btn-got-it {
  background: #087642;
  color: #ffffff;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

.pos-btn-got-it:hover {
  background: #065f35;
}

.pos-mobile-bottom-nav {
  display: none;
}

@media (max-width: 900px) {
  .pos-header {
    height: 52px;
    padding: 0 10px;
    gap: 8px;
    box-sizing: border-box;
    width: 100%;
  }
  .pos-header-left {
    gap: 8px;
    flex: 1;
    min-width: 0;
  }
  .pos-cluster-trigger {
    width: 100%;
    max-width: 130px;
  }
  .pos-header-nav {
    display: none;
  }
  .pos-header-right {
    gap: 8px;
    flex-shrink: 0;
  }
  .pos-live-clock {
    display: none;
  }
  .pos-user-meta {
    display: none;
  }
  .pos-user-btn {
    padding: 2px 4px;
    border: none;
    background: transparent;
    height: auto;
    max-width: none;
  }
  .pos-hotkey-grid {
    grid-template-columns: 1fr;
  }

  /* MOBILE BOTTOM NAV BAR */
  .pos-mobile-bottom-nav {
    display: flex;
    align-items: center;
    justify-content: space-around;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    height: 58px;
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.06);
    z-index: 99;
    padding: 4px 10px;
    box-sizing: border-box;
  }

  .pos-mob-tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    font-size: 11px;
    font-weight: 500;
    color: #64748b;
    text-decoration: none;
    padding: 4px 0;
    border-radius: 8px;
    transition: all 0.15s ease;
  }

  .pos-mob-tab.active {
    color: #166534;
    font-weight: 600;
    background: #f4f8f5;
  }

  .pos-mob-tab:hover {
    color: #166534;
  }
}

@media (max-width: 640px) {
  .pos-header {
    height: 50px;
    padding: 0 8px;
    gap: 6px;
    box-sizing: border-box;
    width: 100%;
  }

  .pos-header-left {
    gap: 6px;
    flex: 1;
    min-width: 0;
  }

  .pos-brand-text {
    display: none;
  }

  .pos-brand-logo {
    width: 30px;
    height: 30px;
    font-size: 12px;
    border-radius: 6px;
    flex-shrink: 0;
  }

  .pos-cluster-wrapper {
    flex: 1;
    min-width: 0;
    max-width: 115px;
  }

  .pos-cluster-trigger {
    max-width: 100%;
    width: 100%;
    padding: 3px 6px;
    font-size: 11.5px;
  }

  .pos-shift-info {
    display: none;
  }

  .pos-header-right {
    gap: 6px;
    flex-shrink: 0;
  }

  .pos-attendance-box {
    gap: 4px;
    flex-shrink: 0;
  }

  .pos-attend-btn,
  .pos-attend-done {
    padding: 4px 6px;
    font-size: 11px;
    gap: 3px;
    white-space: nowrap;
    flex-shrink: 0;
  }

  .pos-attend-btn span,
  .pos-attend-done span {
    display: inline;
    font-size: 11px;
    white-space: nowrap;
  }

  .pos-user-wrapper {
    flex-shrink: 0;
    position: relative;
  }

  .pos-user-btn {
    padding: 0;
    gap: 2px;
    background: transparent;
  }

  .pos-user-avatar {
    width: 28px;
    height: 28px;
    font-size: 12px;
  }

  .pos-user-meta {
    display: none;
  }

  .pos-icon-btn {
    display: none;
  }

  .pos-user-popover {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    width: 195px;
    max-width: calc(100vw - 16px);
    z-index: 1000;
  }

  .pos-cluster-popover {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    min-width: 190px;
    max-width: calc(100vw - 16px);
    z-index: 1000;
  }
}
</style>

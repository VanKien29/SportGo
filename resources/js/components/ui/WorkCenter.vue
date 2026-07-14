<template>
  <div ref="root" class="work-center">
    <button
      class="work-center-trigger"
      type="button"
      title="Công việc và thông báo"
      aria-label="Công việc và thông báo"
      :aria-expanded="open"
      data-testid="work-center-trigger"
      @click.stop="toggle"
    >
      <AppIcon name="bell" size="18" />
      <span v-if="actionCount" class="work-center-badge">{{ badgeLabel(actionCount) }}</span>
      <span v-else-if="unreadCount" class="work-center-unread-dot"></span>
    </button>

    <section v-if="open" class="work-center-panel" data-testid="work-center-panel" @click.stop>
      <header class="work-center-header">
        <div>
          <span class="work-center-eyebrow">Trung tâm công việc</span>
          <h2>{{ actionCount ? `${actionCount} việc cần xử lý` : 'Không có việc tồn đọng' }}</h2>
        </div>
        <button class="work-center-icon-btn" type="button" title="Tải lại" :disabled="loading" @click="load(true)">
          <AppIcon name="refresh" size="16" />
        </button>
      </header>

      <div class="work-center-tabs" role="tablist" aria-label="Loại nội dung">
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'tasks'"
          :class="{ active: activeTab === 'tasks' }"
          @click="activeTab = 'tasks'"
        >
          Cần làm <span>{{ actionCount }}</span>
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'notifications'"
          :class="{ active: activeTab === 'notifications' }"
          @click="activeTab = 'notifications'"
        >
          Thông báo <span>{{ unreadCount }}</span>
        </button>
      </div>

      <div class="work-center-list" role="list">
        <div v-if="loading && !visibleItems.length" class="work-center-state">Đang tải công việc...</div>
        <div v-else-if="error" class="work-center-state error">{{ error }}</div>
        <div v-else-if="!visibleItems.length" class="work-center-state">
          <AppIcon name="circleCheck" size="22" />
          <strong>{{ activeTab === 'tasks' ? 'Đã xử lý hết việc đang chờ' : 'Chưa có thông báo mới' }}</strong>
          <span>{{ activeTab === 'tasks' ? 'Các việc mới sẽ xuất hiện tại đây.' : 'Thông báo hệ thống sẽ được lưu trong mục này.' }}</span>
        </div>

        <button
          v-for="item in visibleItems"
          v-else
          :key="item.id"
          class="work-center-item"
          :class="[`priority-${item.priority}`, { unread: item.kind === 'notification' && !item.is_read }]"
          type="button"
          role="listitem"
          data-testid="work-center-item"
          @click="openItem(item)"
        >
          <span class="work-center-item-icon" :class="`category-${item.category}`">
            <AppIcon :name="categoryIcon(item.category)" size="16" />
          </span>
          <span class="work-center-item-copy">
            <span class="work-center-item-meta">
              <span>{{ categoryLabel(item.category) }}</span>
              <time>{{ formatTime(item.created_at) }}</time>
            </span>
            <strong>{{ item.title }}</strong>
            <span class="work-center-description">{{ item.description }}</span>
            <span class="work-center-action">{{ item.action_label }} <AppIcon name="chevronRight" size="13" /></span>
          </span>
        </button>
      </div>

      <footer class="work-center-footer">
        <span>Cập nhật tự động mỗi phút</span>
        <span v-if="loading">Đang đồng bộ...</span>
      </footer>
    </section>

    <Teleport to="body">
      <aside
        v-if="toastItem"
        class="work-center-toast"
        role="status"
        aria-live="polite"
        data-testid="work-center-toast"
      >
        <span class="work-center-toast-icon"><AppIcon :name="categoryIcon(toastItem.category)" size="18" /></span>
        <div>
          <small>Việc cần xử lý</small>
          <strong>{{ toastItem.title }}</strong>
          <button type="button" @click="openItem(toastItem)">{{ toastItem.action_label }}</button>
        </div>
        <button class="work-center-toast-close" type="button" title="Đóng" @click="dismissToast">
          <AppIcon name="x" size="15" />
        </button>
      </aside>
    </Teleport>
  </div>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { fetchWorkCenter, markWorkNotificationRead } from '../../services/workCenter.js';

export default {
  name: 'WorkCenter',
  components: { AppIcon },
  props: {
    audience: { type: String, required: true, validator: (value) => ['admin', 'owner'].includes(value) },
  },
  data() {
    return {
      open: false,
      activeTab: 'tasks',
      loading: false,
      error: '',
      summary: { action_count: 0, unread_notification_count: 0, categories: {} },
      tasks: [],
      notifications: [],
      toastItem: null,
      toastTimer: null,
      pollTimer: null,
    };
  },
  computed: {
    actionCount() {
      return Number(this.summary.action_count || 0);
    },
    unreadCount() {
      return Number(this.summary.unread_notification_count || 0);
    },
    visibleItems() {
      return this.activeTab === 'tasks' ? this.tasks : this.notifications;
    },
  },
  mounted() {
    document.addEventListener('pointerdown', this.closeFromOutside);
    document.addEventListener('keydown', this.closeOnEscape);
    this.load();
    this.pollTimer = window.setInterval(() => this.load(), 60_000);
  },
  beforeUnmount() {
    document.removeEventListener('pointerdown', this.closeFromOutside);
    document.removeEventListener('keydown', this.closeOnEscape);
    if (this.pollTimer) window.clearInterval(this.pollTimer);
    if (this.toastTimer) window.clearTimeout(this.toastTimer);
  },
  methods: {
    async load(force = false) {
      if (this.loading && !force) return;
      this.loading = true;
      this.error = '';
      try {
        const response = await fetchWorkCenter(this.audience);
        const data = response?.data || {};
        this.summary = data.summary || this.summary;
        this.tasks = data.tasks || [];
        this.notifications = data.notifications || [];
        this.prepareToast();
      } catch (error) {
        this.error = error.message || 'Không tải được trung tâm công việc.';
      } finally {
        this.loading = false;
      }
    },
    toggle() {
      this.open = !this.open;
      if (this.open) this.toastItem = null;
    },
    closeFromOutside(event) {
      if (this.open && !this.$refs.root?.contains(event.target)) this.open = false;
    },
    closeOnEscape(event) {
      if (event.key === 'Escape') this.open = false;
    },
    async openItem(item) {
      if (item.kind === 'notification' && item.notification_id && !item.is_read) {
        try {
          await markWorkNotificationRead(this.audience, item.notification_id);
          item.is_read = true;
          this.summary.unread_notification_count = Math.max(0, this.unreadCount - 1);
        } catch {
          // Điều hướng vẫn hữu ích ngay cả khi thao tác đánh dấu đọc tạm thời thất bại.
        }
      }

      this.open = false;
      this.toastItem = null;
      if (item.target) await this.$router.push(item.target).catch(() => {});
    },
    prepareToast() {
      const topTask = this.tasks[0];
      if (!topTask || this.open) return;
      const key = `work-center-toast:${this.audience}:${topTask.id}`;
      if (sessionStorage.getItem(key)) return;
      sessionStorage.setItem(key, 'shown');
      this.toastItem = topTask;
      if (this.toastTimer) window.clearTimeout(this.toastTimer);
      this.toastTimer = window.setTimeout(() => {
        this.toastItem = null;
        this.toastTimer = null;
      }, 10_000);
    },
    dismissToast() {
      this.toastItem = null;
      if (this.toastTimer) window.clearTimeout(this.toastTimer);
      this.toastTimer = null;
    },
    badgeLabel(value) {
      return value > 99 ? '99+' : String(value);
    },
    categoryIcon(category) {
      return {
        partner: 'fileText',
        signature: 'fileText',
        termination: 'alert',
        finance: 'banknote',
        support: 'messageSquare',
        venue: 'building',
        system: 'bell',
      }[category] || 'bell';
    },
    categoryLabel(category) {
      return {
        partner: 'Hồ sơ đối tác',
        signature: 'Ký điện tử',
        termination: 'Chấm dứt hợp tác',
        finance: 'Tài chính',
        support: 'Hỗ trợ',
        venue: 'Cụm sân',
        system: 'Hệ thống',
      }[category] || 'Công việc';
    },
    formatTime(value) {
      if (!value) return '';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '';
      const diffMinutes = Math.max(0, Math.floor((Date.now() - date.getTime()) / 60_000));
      if (diffMinutes < 1) return 'Vừa xong';
      if (diffMinutes < 60) return `${diffMinutes} phút`;
      if (diffMinutes < 1440) return `${Math.floor(diffMinutes / 60)} giờ`;
      return date.toLocaleDateString('vi-VN');
    },
  },
};
</script>

<style scoped>
.work-center {
  position: relative;
}

.work-center-trigger,
.work-center-icon-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  padding: 0;
  border: 1px solid var(--admin-border, #d9e2dc);
  border-radius: 7px;
  background: var(--admin-surface, #fff);
  color: var(--admin-text, #18231d);
  cursor: pointer;
}

.work-center-trigger:hover,
.work-center-trigger[aria-expanded='true'] {
  border-color: #1f9d5a;
  color: #147a43;
  background: #f1faf5;
}

.work-center-badge {
  position: absolute;
  top: -5px;
  right: -6px;
  display: grid;
  min-width: 19px;
  height: 19px;
  padding: 0 5px;
  place-items: center;
  border: 2px solid var(--admin-surface, #fff);
  border-radius: 10px;
  background: #d13c32;
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  line-height: 1;
}

.work-center-unread-dot {
  position: absolute;
  top: 5px;
  right: 5px;
  width: 8px;
  height: 8px;
  border: 2px solid #fff;
  border-radius: 50%;
  background: #d13c32;
}

.work-center-panel {
  position: absolute;
  z-index: 1300;
  top: calc(100% + 11px);
  right: 0;
  width: min(430px, calc(100vw - 24px));
  overflow: hidden;
  border: 1px solid #d8e2dc;
  border-radius: 8px;
  background: #fff;
  color: #17231d;
  box-shadow: 0 18px 48px rgba(26, 44, 34, 0.18);
}

.work-center-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 16px 17px 13px;
  border-bottom: 1px solid #e7ece9;
}

.work-center-eyebrow {
  display: block;
  margin-bottom: 3px;
  color: #617068;
  font-size: 11px;
  font-weight: 750;
  text-transform: uppercase;
}

.work-center-header h2 {
  margin: 0;
  font-size: 17px;
  line-height: 1.25;
}

.work-center-icon-btn {
  width: 34px;
  height: 34px;
  flex: 0 0 auto;
}

.work-center-tabs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  padding: 0 14px;
  border-bottom: 1px solid #e7ece9;
}

.work-center-tabs button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 43px;
  border: 0;
  border-bottom: 2px solid transparent;
  background: transparent;
  color: #5f6e66;
  font-weight: 700;
  cursor: pointer;
}

.work-center-tabs button.active {
  border-bottom-color: #1d9454;
  color: #116f3d;
}

.work-center-tabs button span {
  min-width: 20px;
  padding: 2px 6px;
  border-radius: 9px;
  background: #edf2ef;
  font-size: 11px;
}

.work-center-list {
  max-height: min(520px, calc(100vh - 180px));
  overflow-y: auto;
}

.work-center-item {
  display: grid;
  grid-template-columns: 34px minmax(0, 1fr);
  width: 100%;
  gap: 11px;
  padding: 13px 16px;
  border: 0;
  border-bottom: 1px solid #edf1ef;
  background: #fff;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.work-center-item:hover,
.work-center-item:focus-visible {
  outline: none;
  background: #f6faf7;
}

.work-center-item.priority-critical {
  box-shadow: inset 3px 0 #d44b3e;
}

.work-center-item.priority-high {
  box-shadow: inset 3px 0 #db942a;
}

.work-center-item.unread {
  background: #f2f8f4;
}

.work-center-item-icon,
.work-center-toast-icon {
  display: grid;
  width: 34px;
  height: 34px;
  place-items: center;
  border-radius: 7px;
  background: #eaf5ee;
  color: #147a43;
}

.work-center-item-icon.category-finance {
  background: #fff3df;
  color: #9a5d08;
}

.work-center-item-icon.category-support,
.work-center-item-icon.category-termination {
  background: #fcecea;
  color: #a83b32;
}

.work-center-item-copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 4px;
}

.work-center-item-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  color: #718078;
  font-size: 11px;
}

.work-center-item-meta span {
  font-weight: 750;
}

.work-center-item-copy strong,
.work-center-description {
  overflow-wrap: anywhere;
}

.work-center-item-copy strong {
  font-size: 13px;
  line-height: 1.35;
}

.work-center-description {
  display: -webkit-box;
  overflow: hidden;
  color: #56655d;
  font-size: 12px;
  line-height: 1.45;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.work-center-action {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  margin-top: 2px;
  color: #147a43;
  font-size: 12px;
  font-weight: 750;
}

.work-center-state {
  display: flex;
  min-height: 190px;
  padding: 28px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 7px;
  color: #66756d;
  text-align: center;
}

.work-center-state strong {
  color: #27352e;
}

.work-center-state.error {
  color: #a83b32;
}

.work-center-footer {
  display: flex;
  min-height: 36px;
  padding: 0 16px;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: #f8faf9;
  color: #7a8880;
  font-size: 10px;
}

.work-center-toast {
  position: fixed;
  z-index: 550;
  right: 22px;
  bottom: 22px;
  display: grid;
  width: min(370px, calc(100vw - 28px));
  grid-template-columns: 38px minmax(0, 1fr) 28px;
  gap: 11px;
  padding: 14px;
  border: 1px solid #d7e3dc;
  border-left: 4px solid #1d9454;
  border-radius: 8px;
  background: #fff;
  color: #17231d;
  box-shadow: 0 14px 40px rgba(23, 43, 31, 0.2);
}

.work-center-toast > div {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 3px;
}

.work-center-toast small {
  color: #6b7971;
  font-weight: 750;
}

.work-center-toast strong {
  overflow-wrap: anywhere;
  font-size: 13px;
  line-height: 1.35;
}

.work-center-toast div button {
  width: max-content;
  padding: 3px 0;
  border: 0;
  background: transparent;
  color: #147a43;
  font-weight: 750;
  cursor: pointer;
}

.work-center-toast-close {
  display: grid;
  width: 28px;
  height: 28px;
  padding: 0;
  place-items: center;
  border: 0;
  background: transparent;
  color: #647269;
  cursor: pointer;
}

@media (max-width: 640px) {
  .work-center-panel {
    position: fixed;
    top: 62px;
    right: 8px;
    left: 8px;
    width: auto;
  }

  .work-center-list {
    max-height: calc(100vh - 205px);
  }

  .work-center-toast {
    display: none;
  }
}
</style>

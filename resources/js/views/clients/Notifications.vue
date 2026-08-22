<template>
  <div class="w2-white-content">
    <div class="sg3-page-head">
            <div>
              <p class="sg3-kicker">Trung tâm cập nhật</p>
              <h1 class="page-head-title">Thông báo của tôi</h1>
              <p class="page-head-desc">Cập nhật về đơn đặt sân, thanh toán, hoàn tiền và tài khoản của bạn.</p>
            </div>
            <button
              class="w2-btn w2-btn--outline"
              type="button"
              :disabled="!unreadCount || markingAll"
              @click="markAllRead"
            >
              <AppIcon name="check" :size="16" />
              <span>Đánh dấu đã đọc tất cả</span>
            </button>
          </div>

          <section v-if="loading" class="sg3-empty">
            <div>
              <strong>Đang tải thông báo...</strong>
            </div>
          </section>

          <section v-else-if="error" class="sg3-error">
            <div>
              <strong>Không tải được thông báo</strong>
              <p>{{ error }}</p>
              <button class="w2-btn w2-btn--primary" type="button" @click="load">Thử lại</button>
            </div>
          </section>

          <section v-else class="sg3-card sg3-notification-card">
            <header class="nt-card-head">
              <span><strong>{{ unreadCount }}</strong> thông báo chưa đọc</span>
              <button class="w2-btn w2-btn--outline" type="button" @click="load">Làm mới</button>
            </header>

            <div v-if="!notifications.length" class="sg3-empty sg3-empty--inline">
              <div>
                <strong>Bạn chưa có thông báo nào</strong>
                <p>Những cập nhật mới nhất sẽ hiển thị tại đây.</p>
              </div>
            </div>

            <button
              v-for="notification in notifications"
              :key="notification.id"
              type="button"
              class="sg3-notification-row"
              :class="{ 'is-unread': !notification.is_read }"
              @click="openNotification(notification)"
            >
              <span class="nt-icon-box"><AppIcon name="bell" :size="17" /></span>
              <div class="nt-body-col">
                <strong class="nt-title">{{ notification.title || "Thông báo SportGo" }}</strong>
                <p class="nt-desc">{{ notification.body }}</p>
                <small class="nt-date-text">{{ formatDate(notification.created_at) }}</small>
              </div>
              <span v-if="!notification.is_read" class="nt-dot-unread"></span>
              <AppIcon name="chevronRight" :size="16" class="nt-arrow" />
            </button>
    </section>
  </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import { notificationService } from "../../services/notification.service.js";

export default {
  name: "ClientNotifications",
  components: { AppIcon },
  data() {
    return {
      notifications: [],
      unreadCount: 0,
      loading: true,
      error: "",
      markingAll: false,
    };
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      this.loading = true;
      this.error = "";
      try {
        const response = await notificationService.getNotifications();
        this.notifications = response.data || [];
        this.unreadCount = Number(response.unread_count || 0);
      } catch (error) {
        this.error = error.message || "Vui lòng thử lại.";
      } finally {
        this.loading = false;
      }
    },
    async markAllRead() {
      this.markingAll = true;
      try {
        await notificationService.markAllAsRead();
        this.notifications = this.notifications.map((item) => ({ ...item, is_read: true }));
        this.unreadCount = 0;
      } catch (error) {
        this.error = error.message || "Không thể cập nhật thông báo.";
      } finally {
        this.markingAll = false;
      }
    },
    async openNotification(notification) {
      if (!notification.is_read) {
        try {
          await notificationService.markAsRead(notification.id);
          notification.is_read = true;
          this.unreadCount = Math.max(0, this.unreadCount - 1);
        } catch {}
      }
      const target = notification.action_url || notification.data?.action_url;
      if (typeof target === "string" && target.startsWith("/")) {
        this.$router.push(target);
        return;
      }
      const type = `${notification.reference_type || ""} ${notification.type || ""}`.toLowerCase();
      if (type.includes("booking") && notification.reference_id)
        this.$router.push({ name: "booking-detail", params: { id: notification.reference_id } });
      else if (type.includes("refund") && notification.reference_id)
        this.$router.push({ name: "client-refund-detail", params: { id: notification.reference_id } });
      else if (type.includes("complaint") && notification.reference_id)
        this.$router.push({ name: "client-complaint-detail", params: { id: notification.reference_id } });
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleString("vi-VN") : "";
    },
  },
};
</script>

<style scoped>
* {
  font-weight: 400 !important;
}

.wallet-white-page {
  min-height: 100vh;
  background: #ffffff;
}

.wallet-white-main {
  max-width: 100% !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 24px 32px 60px !important;
  color: #0f172a;
}

.wallet-layout-grid {
  display: flex;
  gap: 32px;
  align-items: flex-start;
  width: 100%;
}

.w2-white-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sg3-page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  padding-bottom: 12px;
}

.sg3-kicker {
  font-size: 12px;
  color: #475569;
  letter-spacing: 0.05em;
  margin-bottom: 4px;
}

.page-head-title {
  font-size: 24px;
  color: #0f172a;
  margin: 0 0 6px;
}

.page-head-desc {
  font-size: 13.5px;
  color: #475569;
  margin: 0;
}

.w2-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 18px;
  font-size: 13.5px;
  font-weight: 600;
  border-radius: 999px;
  cursor: pointer;
  text-decoration: none;
  border: 1.5px solid transparent;
  transition: all 0.15s ease;
}

.w2-btn--outline {
  background: #ffffff;
  color: #475569;
  border-color: #cbd5e1;
}

.w2-btn--outline:hover:not(:disabled) {
  border-color: #54656f;
  color: #0f172a;
  background: #f8fafc;
}

.sg3-card,
.sg3-empty,
.sg3-error {
  border: none !important;
  box-shadow: none !important;
  background: transparent !important;
  padding: 0 !important;
  border-radius: 0 !important;
}

.sg3-empty--inline {
  padding: 40px 0 !important;
  text-align: center;
}

.nt-card-head,
.sg3-notification-card > header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 0 12px 0 !important;
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.sg3-notification-row {
  display: flex;
  align-items: center;
  padding: 14px 12px !important;
  gap: 16px;
  width: 100%;
  background: transparent;
  border: none;
  border-radius: 8px;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s ease;
}

.sg3-notification-row:hover {
  background: #f8fafc;
}

.sg3-notification-row.is-unread {
  background: #edf4f0;
}

.nt-icon-box {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: #edf4f0;
  color: #5c7e6e;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.sg3-notification-row.is-unread .nt-icon-box {
  background: #54656f;
  color: #ffffff;
}

.nt-body-col {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
}

.nt-title {
  font-size: 14.5px;
  font-weight: 600;
  color: #0f172a;
}

.nt-desc {
  font-size: 13.5px;
  color: #475569;
  margin: 0;
}

.nt-date-text {
  font-size: 12px;
  color: #64748b;
}

.nt-dot-unread {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #5c7e6e;
  flex-shrink: 0;
}

.nt-arrow {
  color: #94a3b8;
}
</style>

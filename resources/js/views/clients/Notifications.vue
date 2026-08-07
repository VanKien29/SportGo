<template>
  <div class="sg-client-page sg3-utility-page">
    <PublicNavbar />
    <main class="sg3-container sg3-profile-main">
      <div class="sg3-page-head">
        <div>
          <div class="sg3-breadcrumbs"><router-link to="/profile">Tài khoản</router-link><span>/</span><strong>Thông báo</strong></div>
          <p class="sg3-kicker">Trung tâm cập nhật</p>
          <h1>Thông báo</h1>
          <p>Mọi thay đổi về booking, hoàn tiền, giao lưu và tài khoản đều được gom ở một nơi.</p>
        </div>
        <button class="sg3-button sg3-button--secondary" type="button" :disabled="!unreadCount || markingAll" @click="markAllRead"><AppIcon name="check" :size="16" /> Đánh dấu đã đọc</button>
      </div>

      <section class="sg-utility-insights" aria-label="Tổng quan thông báo">
        <article class="sg3-card"><span class="sg-utility-insight-icon"><AppIcon name="bell" :size="17" /></span><div><small>Chưa đọc</small><strong>{{ unreadCount }}</strong><span>Cần bạn xem</span></div></article>
        <article class="sg3-card"><span class="sg-utility-insight-icon is-blue"><AppIcon name="layers" :size="17" /></span><div><small>Tất cả cập nhật</small><strong>{{ notifications.length }}</strong><span>Trong danh sách hiện tại</span></div></article>
        <article class="sg3-card"><span class="sg-utility-insight-icon is-amber"><AppIcon name="clock" :size="17" /></span><div><small>Cập nhật gần nhất</small><strong>{{ latestDate }}</strong><span>Luôn kiểm tra thông báo mới</span></div></article>
      </section>

      <section v-if="loading" class="sg3-empty"><div><strong>Đang tải thông báo</strong><p>Đang kiểm tra cập nhật mới nhất.</p></div></section>
      <section v-else-if="error" class="sg3-error"><div><strong>Không tải được thông báo</strong><p>{{ error }}</p><button class="sg3-button sg3-button--primary" type="button" @click="load">Thử lại</button></div></section>
      <section v-else class="sg3-card sg3-notification-card">
        <header><div><p class="sg3-kicker">Dòng thời gian</p><h2>Cập nhật của bạn</h2><span>{{ unreadCount ? `Bạn còn ${unreadCount} thông báo cần xem.` : 'Bạn đã xem hết thông báo mới.' }}</span></div><button class="sg3-button sg3-button--quiet" type="button" @click="load">Làm mới</button></header>
        <form class="notification-filters" @submit.prevent="applyFilters">
          <input v-model.trim="filters.search" class="sg-client-input" type="search" placeholder="Tìm trong tiêu đề hoặc nội dung" />
          <select v-model="filters.type" class="sg-client-input"><option value="">Tất cả loại</option><option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select>
          <select v-model="filters.read" class="sg-client-input"><option value="">Tất cả trạng thái</option><option value="false">Chưa đọc</option><option value="true">Đã đọc</option></select>
          <button class="sg3-button sg3-button--primary" type="submit">Áp dụng</button>
        </form>
        <div v-if="!notifications.length" class="sg3-empty sg3-empty--inline"><div><strong>Bạn chưa có thông báo nào</strong><p>Những cập nhật quan trọng sẽ được lưu tại đây.</p></div></div>
        <button v-for="notification in notifications" :key="notification.id" type="button" class="sg3-notification-row" :class="{ 'is-unread': !notification.is_read }" @click="openNotification(notification)">
          <span class="sg3-notification-icon"><AppIcon :name="notificationIcon(notification)" :size="17" /></span>
          <span><strong>{{ notification.title || 'Thông báo SportGo' }}</strong><span>{{ notification.body }}</span><small>{{ formatDate(notification.created_at) }}</small></span>
          <i v-if="!notification.is_read"></i><AppIcon name="chevronRight" :size="16" />
        </button>
        <footer v-if="lastPage > 1" class="sg3-pagination"><button class="sg3-button sg3-button--secondary" type="button" :disabled="page <= 1" @click="goPage(page - 1)">Trước</button><span>Trang {{ page }} / {{ lastPage }} · {{ total }}</span><button class="sg3-button sg3-button--secondary" type="button" :disabled="page >= lastPage" @click="goPage(page + 1)">Sau</button></footer>
      </section>
    </main>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import { notificationService } from '../../services/notification.service.js';

export default {
  name: 'ClientNotifications',
  components: { AppIcon, PublicNavbar },
  data() { return { notifications: [], unreadCount: 0, loading: true, error: '', markingAll: false, page: 1, lastPage: 1, total: 0, filters: { search: '', type: '', read: '' } }; },
  computed: {
    latestDate() { return this.notifications[0]?.created_at ? new Date(this.notifications[0].created_at).toLocaleDateString('vi-VN') : '—'; },
    typeOptions() { return [{ value: 'booking', label: 'Booking & thanh toán' }, { value: 'refund', label: 'Hoàn tiền' }, { value: 'matchmaking', label: 'Tuyển giao lưu' }, { value: 'complaint', label: 'Khiếu nại' }, { value: 'wallet', label: 'Ví SportGo' }, { value: 'report', label: 'Báo cáo' }, { value: 'post', label: 'Cộng đồng' }]; },
  },
  mounted() { this.load(); },
  methods: {
    async load() { this.loading = true; this.error = ''; try { const response = await notificationService.getNotifications({ page: this.page, search: this.filters.search, type: this.filters.type, read: this.filters.read }); this.notifications = response.data || []; this.page = Number(response.current_page || this.page); this.lastPage = Number(response.last_page || 1); this.total = Number(response.total || this.notifications.length); this.unreadCount = Number(response.unread_count || 0); } catch (error) { this.error = error.message || 'Vui lòng thử lại.'; } finally { this.loading = false; } },
    applyFilters() { this.page = 1; this.load(); },
    goPage(page) { this.page = page; this.load(); },
    async markAllRead() { this.markingAll = true; try { await notificationService.markAllAsRead(); this.notifications = this.notifications.map((item) => ({ ...item, is_read: true })); this.unreadCount = 0; } catch (error) { this.error = error.message || 'Không thể cập nhật thông báo.'; } finally { this.markingAll = false; } },
    async openNotification(notification) { if (!notification.is_read) { try { await notificationService.markAsRead(notification.id); notification.is_read = true; this.unreadCount = Math.max(0, this.unreadCount - 1); } catch {} } const target = notification.action_url || notification.data?.action_url; if (typeof target === 'string' && target.startsWith('/')) return this.$router.push(target); const type = `${notification.reference_type || ''} ${notification.type || ''}`.toLowerCase(); if (type.includes('booking') && notification.reference_id) this.$router.push({ name: 'booking-detail', params: { id: notification.reference_id } }); else if (type.includes('refund') && notification.reference_id) this.$router.push({ name: 'client-refund-detail', params: { id: notification.reference_id } }); else if (type.includes('complaint') && notification.reference_id) this.$router.push({ name: 'client-complaint-detail', params: { id: notification.reference_id } }); else if (type.includes('matchmaking') && notification.reference_id) this.$router.push(`/matchmaking-posts/${notification.reference_id}/manage`); },
    notificationIcon(notification) { const type = `${notification.type || ''} ${notification.reference_type || ''}`.toLowerCase(); if (type.includes('refund')) return 'rotateCcw'; if (type.includes('booking')) return 'calendar'; if (type.includes('matchmaking')) return 'users'; if (type.includes('complaint')) return 'messageSquare'; return 'bell'; },
    formatDate(value) { return value ? new Date(value).toLocaleString('vi-VN') : ''; },
  },
};
</script>

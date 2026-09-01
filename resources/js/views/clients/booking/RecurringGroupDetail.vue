<template>
  <div class="recurring-group-page sg-client-page sg3-recurring-detail-page">
    <PublicNavbar />
    <main class="sg-client-shell recurring-shell">
      <button type="button" class="back-link" @click="$router.push({ name: 'booking-history', query: { booking_type: 'recurring' } })"><AppIcon name="arrowLeft" :size="17" /> Lịch đặt cố định</button>
      <section v-if="loading" class="sg-client-card group-state"><span class="spinner"></span> Đang tải chuỗi lịch...</section>
      <section v-else-if="error" class="sg-client-card group-state" role="alert"><AppIcon name="alert" :size="20" /><span>{{ error }}</span><button type="button" class="sg-client-button" @click="load">Thử lại</button></section>
      <template v-else>
        <section class="group-header"><div><p class="sg-client-eyebrow">Lịch cố định</p><h1>{{ groupCode }}</h1><p>{{ group.summary.cluster?.name || 'Cụm sân' }} · {{ formatDate(group.summary.start_date) }} - {{ formatDate(group.summary.end_date) }}</p></div><router-link :to="{ name: 'booking-create' }" class="sg-client-button sg-client-button--primary"><AppIcon name="plus" :size="16" /> Đặt lịch mới</router-link></section>
        <section class="summary-grid"><div><span>Tổng buổi</span><strong>{{ group.summary.total }}</strong></div><div><span>Sắp tới</span><strong>{{ group.summary.upcoming }}</strong></div><div><span>Đã hoàn thành</span><strong>{{ group.summary.completed }}</strong></div><div><span>Đã hủy</span><strong>{{ group.summary.cancelled }}</strong></div><div><span>Đã thanh toán</span><strong>{{ money(group.summary.paid_amount) }}</strong></div></section>
        <section class="sg-client-card occurrence-panel"><header><div><p class="sg-client-eyebrow">Các buổi trong chuỗi</p><h2>Lịch theo từng ngày</h2></div><button type="button" class="sg-client-button" @click="load"><AppIcon name="refresh" :size="16" /> Làm mới</button></header><article v-for="item in group.items" :key="item.id" class="occurrence-row"><div><strong>{{ formatDate(item.booking_date) }} · {{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }}</strong><span>{{ item.court?.name || item.venue_court?.name || 'Sân đang cập nhật' }}</span><small>#{{ item.booking_code }}</small></div><span class="status-badge" :class="item.status">{{ statusLabel(item.status) }}</span><router-link :to="{ name: 'booking-detail', params: { id: item.id } }" class="sg-client-button sg-client-button--quiet">Chi tiết</router-link></article></section>
      </template>
    </main>
  </div>
</template>

<script>
import AppIcon from '../../../components/AppIcon.vue';
import PublicNavbar from '../../../components/PublicNavbar.vue';
import { bookingService } from '../../../services/bookingService.js';
import { businessDateLabel } from '../../../utils/businessTime.js';

export default {
  name: 'RecurringGroupDetail',
  components: { AppIcon, PublicNavbar },
  data() { return { group: null, loading: true, error: '' }; },
  computed: { groupCode() { return this.$route.params.groupCode; } },
  mounted() { this.load(); },
  methods: {
    async load() { this.loading = true; this.error = ''; try { this.group = await bookingService.getRecurringGroup(this.groupCode); } catch (error) { this.error = error.message || 'Vui lòng thử lại.'; } finally { this.loading = false; } },
    money(value) { return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0)); },
    formatDate(value) { return businessDateLabel(value) || '-'; },
    formatTime(value) { return value ? String(value).slice(0, 5) : '-'; },
    statusLabel(status) { return { pending_approval: 'Chờ duyệt', pending_payment: 'Chờ thanh toán', confirmed: 'Đã xác nhận', checked_in: 'Đang chơi', completed: 'Hoàn thành', cancelled: 'Đã hủy', expired: 'Đã hết hạn', rejected: 'Từ chối' }[status] || status || 'Chưa cập nhật'; },
  },
};
</script>

<style scoped>
.recurring-shell{padding-top:84px;padding-bottom:64px}.back-link{display:inline-flex;align-items:center;gap:8px;border:0;background:transparent;color:#087642;font-weight:700;cursor:pointer;margin-bottom:18px}.group-header{display:flex;justify-content:space-between;align-items:end;gap:24px;margin-bottom:24px}.group-header h1{margin:8px 0}.group-header p:last-child{margin:0;color:#64748b}.summary-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin:24px 0}.summary-grid>div{display:grid;gap:7px;padding:18px;background:#f1fbf5;border:1px solid #b7e6c8;border-radius:8px}.summary-grid span{font-size:13px;color:#64748b}.summary-grid strong{font-size:20px;color:#193027}.occurrence-panel{padding:24px}.occurrence-panel header{display:flex;justify-content:space-between;align-items:center;gap:16px;padding-bottom:18px;border-bottom:1px solid #d9eee1}.occurrence-panel h2{margin:6px 0 0}.occurrence-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:18px;padding:18px 0;border-bottom:1px solid #edf5ef}.occurrence-row>div{display:grid;gap:5px}.occurrence-row span,.occurrence-row small{color:#64748b}.status-badge{padding:6px 10px;border-radius:999px;background:#e4f7eb;color:#087642;font-weight:700;font-size:12px}.status-badge.cancelled,.status-badge.expired,.status-badge.rejected{background:#fff0f0;color:#b42318}.status-badge.pending_payment,.status-badge.pending_approval{background:#fff4d6;color:#8a5b00}.group-state{min-height:180px;display:flex;align-items:center;justify-content:center;gap:12px;color:#64748b}@media(max-width:800px){.group-header{align-items:stretch;flex-direction:column}.summary-grid{grid-template-columns:repeat(2,1fr)}.occurrence-row{grid-template-columns:1fr auto}.occurrence-row .sg-client-button{grid-column:2;grid-row:1 / span 2}}
</style>

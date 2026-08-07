<template>
  <div class="sg-client-page sg3-utility-page">
    <PublicNavbar />
    <main class="sg3-container sg3-profile-main">
      <div class="sg3-page-head"><div><div class="sg3-breadcrumbs"><router-link to="/profile">Tài khoản</router-link><span>/</span><strong>Hoàn / hủy</strong></div><p class="sg3-kicker">Trung tâm tài chính</p><h1>Hoàn tiền & hủy booking</h1><p>Theo dõi tiền hoàn, các booking đã hủy và từng bước xử lý ngay trong một trang.</p></div><router-link class="sg3-button sg3-button--primary" to="/bookings"><AppIcon name="calendar" :size="16" /> Mở lịch đặt sân</router-link></div>
      <section class="sg-utility-insights" aria-label="Tổng quan hoàn tiền"><article class="sg3-card"><span class="sg-utility-insight-icon"><AppIcon name="rotateCcw" :size="17" /></span><div><small>Yêu cầu hoàn</small><strong>{{ total }}</strong><span>Đang được theo dõi</span></div></article><article class="sg3-card"><span class="sg-utility-insight-icon is-amber"><AppIcon name="clock" :size="17" /></span><div><small>Đang xử lý</small><strong>{{ processingCount }}</strong><span>Chờ SportGo đối soát</span></div></article><article class="sg3-card"><span class="sg-utility-insight-icon is-blue"><AppIcon name="calendar" :size="17" /></span><div><small>Booking đã hủy</small><strong>{{ cancelledBookings.length }}</strong><span>Kiểm tra chính sách hoàn</span></div></article></section>
      <section v-if="loading" class="sg3-empty"><div><strong>Đang tải trung tâm hoàn / hủy</strong><p>Đang đồng bộ trạng thái xử lý.</p></div></section>
      <section v-else-if="error" class="sg3-error"><div><strong>Không tải được dữ liệu hoàn / hủy</strong><p>{{ error }}</p><button class="sg3-button sg3-button--primary" type="button" @click="load">Thử lại</button></div></section>
      <template v-else>
        <section class="sg3-card sg3-request-card"><header><div><p class="sg3-kicker">Dòng tiền</p><h2>Yêu cầu hoàn tiền</h2><span>Tiền hoàn được cập nhật theo từng mốc xử lý của booking.</span></div><button class="sg3-button sg3-button--quiet" type="button" @click="load">Làm mới</button></header><div v-if="!refunds.length" class="sg3-empty sg3-empty--inline"><div><strong>Chưa có yêu cầu hoàn tiền</strong><p>Khi một booking được hủy có phát sinh hoàn, yêu cầu sẽ xuất hiện ở đây.</p><router-link class="sg3-button sg3-button--secondary" to="/bookings">Xem lịch đặt</router-link></div></div><article v-for="refund in refunds" :key="refund.id" class="sg3-request-row"><div><strong>{{ refund.booking?.booking_code || refund.booking?.code || `Yêu cầu #${refund.id}` }}</strong><span>{{ refund.booking?.venue_cluster?.name || refund.booking?.venueCluster?.name || 'Booking SportGo' }}</span><small>{{ formatDate(refund.created_at) }} · {{ refund.reason || 'Theo chính sách booking' }}</small></div><div class="sg3-request-amount"><strong>{{ money(refund.refund_amount ?? refund.amount) }}</strong><span class="sg3-status-pill" :class="statusClass(refund.status)">{{ statusLabel(refund.status) }}</span></div><button type="button" class="sg3-detail-trigger" aria-label="Xem chi tiết" @click="openRefund(refund.id)"><AppIcon name="chevronRight" :size="18" /></button></article><footer v-if="lastPage > 1" class="sg3-pagination"><button class="sg3-button sg3-button--secondary" type="button" :disabled="page <= 1" @click="goPage(page - 1)">Trước</button><span>Trang {{ page }} / {{ lastPage }}</span><button class="sg3-button sg3-button--secondary" type="button" :disabled="page >= lastPage" @click="goPage(page + 1)">Sau</button></footer></section>
        <section class="sg3-card sg3-request-card sg-refund-cancel-card"><header><div><p class="sg3-kicker">Lịch sử hủy</p><h2>Booking đã hủy gần đây</h2><span>Nếu chưa thấy yêu cầu hoàn, hãy mở chi tiết booking để xem chính sách áp dụng.</span></div><router-link class="sg3-button sg3-button--quiet" to="/bookings">Xem tất cả</router-link></header><div v-if="!cancelledBookings.length" class="sg3-empty sg3-empty--inline"><div><strong>Chưa có booking đã hủy</strong><p>Các yêu cầu hủy booking sẽ được hiển thị và dẫn tới chi tiết xử lý.</p></div></div><article v-for="booking in cancelledBookings" :key="booking.id" class="sg3-request-row"><div><strong>#{{ booking.booking_code }} · {{ booking.venue_cluster?.name || 'Cụm sân' }}</strong><span>{{ formatDate(booking.booking_date) }} · {{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}</span><small>{{ booking.payment_status ? `Thanh toán: ${booking.payment_status}` : 'Đã giải phóng khung giờ' }}</small></div><div class="sg-refund-cancel-actions"><span class="sg3-status-pill status-cancelled">{{ bookingStatusLabel(booking.status) }}</span><button v-if="canRequestRefund(booking)" type="button" class="sg3-button sg3-button--secondary" @click="openRefundRequest(booking)">Yêu cầu hoàn</button></div><router-link :to="{ name: 'booking-detail', params: { id: booking.id } }" aria-label="Xem booking"><AppIcon name="chevronRight" :size="18" /></router-link></article></section>
      </template>
    </main>

    <RefundDetailModal :is-open="Boolean(selectedRefundId)" :refund-id="selectedRefundId" @close="selectedRefundId = null" />
    <RefundRequestModal :is-open="Boolean(refundRequestBooking)" :booking="refundRequestBooking" :loading="refundRequestLoading" :error="refundRequestError" @close="closeRefundRequest" @submit="submitRefundRequest" />
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import RefundDetailModal from '../../components/RefundDetailModal.vue';
import RefundRequestModal from '../../components/RefundRequestModal.vue';
import { bookingService } from '../../services/bookingService.js';

export default {
  name: 'ClientRefunds',
  components: { AppIcon, PublicNavbar, RefundDetailModal, RefundRequestModal },
  data() { return { refunds: [], cancelledBookings: [], page: 1, lastPage: 1, total: 0, loading: true, error: '', refundRequestBooking: null, refundRequestLoading: false, refundRequestError: '', selectedRefundId: null }; },
  computed: { processingCount() { return this.refunds.filter((item) => ['pending', 'pending_owner_confirmation', 'owner_confirmed', 'admin_processing', 'approved', 'processing'].includes(item.status)).length; } },
  mounted() { this.load(); },
  methods: {
    async load() { this.loading = true; this.error = ''; try { const [refundResponse, bookingResponse] = await Promise.all([bookingService.listRefunds({ page: this.page }), bookingService.listBookings({ status_group: 'cancelled', per_page: 5 })]); this.refunds = refundResponse.data || []; this.page = Number(refundResponse.current_page || this.page); this.lastPage = Number(refundResponse.last_page || 1); this.total = Number(refundResponse.total || this.refunds.length); this.cancelledBookings = bookingResponse.data || []; } catch (error) { this.error = error.message || 'Vui lòng thử lại.'; } finally { this.loading = false; } },
    openRefund(id) { this.selectedRefundId = id; },
    goPage(page) { this.page = page; this.load(); },
    canRequestRefund(booking) { if (!booking || !['cancelled', 'rejected'].includes(booking.status) || Number(booking.paid_amount || 0) <= 0) return false; const blockingStatuses = ['pending_owner_confirmation', 'owner_confirmed', 'admin_processing', 'processing', 'completed', 'completed_cash']; return !(booking.refunds || []).some((refund) => blockingStatuses.includes(refund.status)); },
    openRefundRequest(booking) { this.refundRequestBooking = booking; this.refundRequestError = ''; },
    closeRefundRequest() { if (this.refundRequestLoading) return; this.refundRequestBooking = null; this.refundRequestError = ''; },
    async submitRefundRequest(payload) { if (this.refundRequestLoading) return; this.refundRequestLoading = true; this.refundRequestError = ''; try { await bookingService.requestRefund(payload); this.refundRequestBooking = null; await this.load(); } catch (error) { this.refundRequestError = error.message || 'Không thể gửi yêu cầu hoàn tiền.'; } finally { this.refundRequestLoading = false; } },
    money(value) { return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value || 0)); },
    formatDate(value) { return value ? new Date(value).toLocaleDateString('vi-VN') : '-'; },
    formatTime(value) { return value ? String(value).slice(0, 5) : '--:--'; },
    statusLabel(status) { return { pending: 'Đang xử lý', pending_owner_confirmation: 'Chờ chủ sân xác nhận', owner_confirmed: 'Chủ sân đã xác nhận', admin_processing: 'SportGo đang xử lý', approved: 'Đã duyệt', processing: 'Đang hoàn', completed: 'Đã hoàn vào ví', completed_cash: 'Đã hoàn tiền mặt', rejected: 'Từ chối', owner_rejected: 'Chủ sân từ chối', failed: 'Hoàn thất bại', cancelled: 'Đã hủy' }[status] || status || 'Chưa cập nhật'; },
    statusClass(status) { return `status-${status || 'pending'}`; },
    bookingStatusLabel(status) { return { cancelled: 'Đã hủy', expired: 'Đã hết hạn', rejected: 'Bị từ chối' }[status] || status || 'Đã hủy'; },
  },
};
</script>

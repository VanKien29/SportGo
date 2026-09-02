<template>
  <Teleport to="body">
    <div v-if="isOpen" class="sg-detail-modal-backdrop" @click.self="close">
      <section class="sg-detail-modal" role="dialog" aria-modal="true" aria-labelledby="refund-modal-title">
        <header class="sg-detail-modal__header"><div><p class="sg3-kicker">CHI TIẾT HOÀN TIỀN</p><h2 id="refund-modal-title">{{ refund ? (refund.booking?.booking_code || refund.booking?.code || `Yêu cầu #${refund.id}`) : 'Chi tiết hoàn tiền' }}</h2><span v-if="refund">{{ refund ? statusLabel(refund.status) : '' }} · {{ refund ? formatDate(refund.created_at) : '' }}</span></div><button type="button" class="sg-detail-modal__close" aria-label="Đóng chi tiết" @click="close"><AppIcon name="x" :size="19" /></button></header>
        <div v-if="loading" class="sg-detail-modal__state"><span class="spinner"></span><strong>Đang tải chi tiết...</strong></div>
        <div v-else-if="error" class="sg-detail-modal__state is-error"><AppIcon name="alert" :size="22" /><strong>{{ error }}</strong><button type="button" class="sg3-button sg3-button--secondary" @click="load">Thử lại</button></div>
        <template v-else-if="refund">
          <div class="sg-detail-modal__body">
            <div class="sg-detail-modal__main"><div class="sg-detail-modal__metrics"><article><span>Số tiền yêu cầu</span><strong>{{ money(refund.refund_amount ?? refund.amount) }}</strong></article><article><span>Phương thức hoàn</span><strong>{{ destinationLabel(refund.refund_destination) }}</strong></article><article><span>Trạng thái</span><strong class="sg3-status-pill" :class="`status-${refund.status}`">{{ statusLabel(refund.status) }}</strong></article></div><section class="sg-detail-modal__section"><header><div><p class="sg3-kicker">Dòng thời gian</p><h3>Lịch sử xử lý</h3></div></header><div v-if="!histories.length" class="sg-detail-modal__empty">Chưa có cập nhật lịch sử.</div><ol v-else class="sg-detail-modal__refund-timeline"><li v-for="item in histories" :key="item.id"><span class="sg-detail-modal__timeline-mark"></span><div><strong>{{ statusLabel(item.new_status || item.status) }}</strong><span>{{ item.note || item.reason || 'Hệ thống cập nhật trạng thái' }}</span><small>{{ formatDate(item.created_at) }}</small></div></li></ol></section></div>
            <aside class="sg-detail-modal__aside"><div class="sg-detail-modal__aside-heading"><span class="utility-card-icon is-blue"><AppIcon name="rotateCcw" :size="18" /></span><div><p class="sg3-kicker">Theo dõi dòng tiền</p><h3>Thông tin yêu cầu</h3></div></div><dl><div><dt>Lý do</dt><dd>{{ refund.reason || 'Theo chính sách booking' }}</dd></div><div><dt>Cập nhật gần nhất</dt><dd>{{ formatDate(refund.updated_at || refund.created_at) }}</dd></div></dl><router-link v-if="bookingId" class="sg3-button sg3-button--secondary" :to="{ name:'booking-detail', params:{ id: bookingId } }" @click="close"><AppIcon name="calendar" :size="16" /> Mở booking liên quan</router-link><router-link class="sg3-button sg3-button--quiet" :to="{ name:'client-refund-detail', params:{ id: refund.id } }" @click="close">Mở trang chi tiết đầy đủ</router-link></aside>
          </div>
        </template>
      </section>
    </div>
  </Teleport>
</template>

<script>
import AppIcon from './AppIcon.vue';
import { bookingService } from '../services/bookingService.js';

export default {
  name: 'RefundDetailModal',
  components: { AppIcon },
  props: { isOpen: { type: Boolean, default: false }, refundId: { type: [Number, String], default: null } },
  emits: ['close'],
  data() { return { refund: null, loading: false, error: '' }; },
  computed: { bookingId() { return this.refund?.booking_id || this.refund?.booking?.id || null; }, histories() { return this.refund?.status_histories || this.refund?.statusHistories || []; } },
  watch: { isOpen(value) { if (value) this.load(); }, refundId(value) { if (this.isOpen && value) this.load(); } },
  mounted() { window.addEventListener('keydown', this.handleKeydown); if (this.isOpen) this.load(); },
  beforeUnmount() { window.removeEventListener('keydown', this.handleKeydown); },
  methods: {
    async load() { if (!this.refundId) return; this.loading = true; this.error = ''; try { this.refund = await bookingService.getRefund(this.refundId); } catch (error) { this.error = error.message || 'Vui lòng thử lại.'; } finally { this.loading = false; } },
    close() { this.$emit('close'); },
    handleKeydown(event) { if (event.key === 'Escape' && this.isOpen) this.close(); },
    money(value) { return new Intl.NumberFormat('vi-VN', { style:'currency', currency:'VND', maximumFractionDigits:0 }).format(Number(value || 0)); },
    formatDate(value) { return value ? new Date(value).toLocaleString('vi-VN') : '-'; },
    destinationLabel(destination) { return { user_wallet:'Ví SportGo', wallet:'Ví SportGo', original_payment:'Phương thức thanh toán gốc', bank_account:'Tài khoản ngân hàng', cash:'Tiền mặt tại sân' }[destination] || 'Không xác định'; },
    statusLabel(status) { return { pending_owner_confirmation:'Chờ chủ sân xác nhận', completed:'Đã hoàn vào ví', completed_cash:'Đã hoàn tiền mặt', owner_rejected:'Chủ sân từ chối' }[status] || status || 'Chưa cập nhật'; },
  },
};
</script>

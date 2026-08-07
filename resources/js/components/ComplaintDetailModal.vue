<template>
  <Teleport to="body">
    <div v-if="isOpen" class="sg-detail-modal-backdrop" @click.self="close">
      <section class="sg-detail-modal" role="dialog" aria-modal="true" aria-labelledby="complaint-modal-title">
        <header class="sg-detail-modal__header">
          <div><p class="sg3-kicker">HỒ SƠ HỖ TRỢ</p><h2 id="complaint-modal-title">{{ complaint ? `Khiếu nại #${complaint.id}` : 'Chi tiết khiếu nại' }}</h2><span v-if="complaint">{{ typeLabel(complaint.complaint_type) }} · {{ formatDate(complaint.created_at) }}</span></div>
          <button type="button" class="sg-detail-modal__close" aria-label="Đóng chi tiết" @click="close"><AppIcon name="x" :size="19" /></button>
        </header>
        <div v-if="loading" class="sg-detail-modal__state"><span class="spinner"></span><strong>Đang tải chi tiết...</strong></div>
        <div v-else-if="error" class="sg-detail-modal__state is-error"><AppIcon name="alert" :size="22" /><strong>{{ error }}</strong><button type="button" class="sg3-button sg3-button--secondary" @click="load">Thử lại</button></div>
        <template v-else-if="complaint">
          <div class="sg-detail-modal__body">
            <div class="sg-detail-modal__main">
              <div class="sg-detail-modal__status"><span>Trạng thái</span><strong class="sg3-status-pill" :class="`status-${complaint.status}`">{{ statusLabel(complaint.status) }}</strong></div>
              <div class="sg-detail-modal__message"><strong>Nội dung đã gửi</strong><p>{{ complaint.content }}</p></div>
              <div class="sg-detail-modal__timeline"><article class="sg-detail-modal__timeline-item"><span class="sg-detail-modal__timeline-mark"></span><div><strong>Bạn đã gửi khiếu nại</strong><p>{{ complaint.content }}</p><small>{{ formatDate(complaint.created_at) }}</small></div></article><article v-for="item in timeline" :key="item.id" class="sg-detail-modal__timeline-item"><span class="sg-detail-modal__timeline-mark"></span><div><strong>{{ item.user?.id === complaint.customer_id ? 'Bạn' : (item.user?.full_name || 'Bộ phận hỗ trợ') }}</strong><p>{{ item.content }}</p><small>{{ formatDate(item.created_at) }}</small></div></article></div>
              <form v-if="canReply" class="sg-detail-modal__reply" @submit.prevent="submitReply"><label for="complaint-modal-reply">Phản hồi</label><textarea id="complaint-modal-reply" v-model.trim="replyContent" rows="4" maxlength="4000" placeholder="Nhập nội dung trao đổi..." :disabled="sending"></textarea><p v-if="replyError" class="field-error">{{ replyError }}</p><button type="submit" class="sg3-button sg3-button--primary" :disabled="sending || replyContent.length < 2"><AppIcon name="send" :size="16" /> {{ sending ? 'Đang gửi...' : 'Gửi phản hồi' }}</button></form><div v-else class="sg-detail-modal__closed">Khiếu nại đã kết thúc, không thể gửi thêm phản hồi.</div>
            </div>
            <aside class="sg-detail-modal__aside"><div class="sg-detail-modal__aside-heading"><span class="utility-card-icon"><AppIcon name="messageSquare" :size="18" /></span><div><p class="sg3-kicker">Thông tin yêu cầu</p><h3>{{ statusLabel(complaint.status) }}</h3></div></div><dl><div><dt>Loại yêu cầu</dt><dd>{{ typeLabel(complaint.complaint_type) }}</dd></div><div><dt>Mã booking</dt><dd>{{ complaint.booking_id ? `#${complaint.booking_id}` : 'Không gắn booking' }}</dd></div><div><dt>Ngày gửi</dt><dd>{{ formatDate(complaint.created_at) }}</dd></div></dl><router-link v-if="complaint.booking_id || complaint.booking?.id" class="sg3-button sg3-button--secondary" :to="{ name:'booking-detail', params:{ id: complaint.booking_id || complaint.booking.id } }" @click="close"><AppIcon name="calendar" :size="16" /> Mở booking liên quan</router-link><router-link class="sg3-button sg3-button--quiet" :to="{ name:'client-complaint-detail', params:{ id: complaint.id } }" @click="close">Mở trang chi tiết đầy đủ</router-link></aside>
          </div>
        </template>
      </section>
    </div>
  </Teleport>
</template>

<script>
import AppIcon from './AppIcon.vue';
import { complaintService } from '../services/complaintService.js';

export default {
  name: 'ComplaintDetailModal',
  components: { AppIcon },
  props: { isOpen: { type: Boolean, default: false }, complaintId: { type: [Number, String], default: null } },
  emits: ['close'],
  data() { return { complaint: null, timeline: [], loading: false, error: '', replyContent: '', replyError: '', sending: false }; },
  computed: { canReply() { return !['resolved', 'rejected', 'closed'].includes(this.complaint?.status); } },
  watch: { isOpen(value) { if (value) this.load(); }, complaintId(value) { if (this.isOpen && value) this.load(); } },
  mounted() { window.addEventListener('keydown', this.handleKeydown); if (this.isOpen) this.load(); },
  beforeUnmount() { window.removeEventListener('keydown', this.handleKeydown); },
  methods: {
    async load() { if (!this.complaintId) return; this.loading = true; this.error = ''; try { const response = await complaintService.get(this.complaintId); const payload = response.data || {}; this.complaint = payload.complaint || null; this.timeline = payload.timeline || []; } catch (error) { this.error = error.message || 'Vui lòng thử lại.'; } finally { this.loading = false; } },
    close() { if (!this.sending) this.$emit('close'); },
    handleKeydown(event) { if (event.key === 'Escape' && this.isOpen) this.close(); },
    async submitReply() { this.replyError = ''; this.sending = true; try { const response = await complaintService.reply(this.complaint.id, this.replyContent); this.timeline.push(response.data); this.replyContent = ''; this.complaint.status = 'processing'; } catch (error) { this.replyError = error.message || 'Không thể gửi phản hồi.'; } finally { this.sending = false; } },
    typeLabel(type) { return type === 'venue' ? 'Khiếu nại cụm sân' : 'Khiếu nại hệ thống'; },
    statusLabel(status) { return { open:'Mới gửi', processing:'Đang xử lý', resolved:'Đã xử lý', rejected:'Từ chối', closed:'Đã đóng' }[status] || status || 'Chưa cập nhật'; },
    formatDate(value) { return value ? new Date(value).toLocaleString('vi-VN') : '-'; },
  },
};
</script>

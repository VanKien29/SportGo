<template>
  <Teleport to="body">
    <div v-if="isOpen" class="sg-detail-modal-backdrop" @click.self="close">
      <section class="sg-detail-modal" role="dialog" aria-modal="true" aria-labelledby="report-modal-title">
        <header class="sg-detail-modal__header"><div><p class="sg3-kicker">CHI TIẾT BÁO CÁO</p><h2 id="report-modal-title">{{ report ? `Báo cáo #${report.id}` : 'Chi tiết báo cáo' }}</h2><span v-if="report">{{ targetLabel(report.target_type) }} #{{ report.target_id }} · {{ formatDate(report.created_at) }}</span></div><button type="button" class="sg-detail-modal__close" aria-label="Đóng chi tiết" @click="close"><AppIcon name="x" :size="19" /></button></header>
        <div v-if="loading" class="sg-detail-modal__state"><span class="spinner"></span><strong>Đang tải chi tiết...</strong></div>
        <div v-else-if="error" class="sg-detail-modal__state is-error"><AppIcon name="alert" :size="22" /><strong>{{ error }}</strong><button type="button" class="sg3-button sg3-button--secondary" @click="load">Thử lại</button></div>
        <template v-else-if="report">
          <div class="sg-detail-modal__body"><div class="sg-detail-modal__main"><div class="sg-detail-modal__status"><span>Trạng thái</span><strong class="sg3-status-pill" :class="`status-${report.status}`">{{ statusLabel(report.status) }}</strong></div><section class="sg-detail-modal__section report-modal-content"><div class="report-detail-row"><span>Lý do</span><strong>{{ reasonLabel(report.reason) }}</strong></div><div class="report-detail-row"><span>Nội dung bổ sung</span><p>{{ report.description || 'Không có mô tả bổ sung.' }}</p></div><div v-if="report.evidence?.length" class="report-evidence"><span>Bằng chứng đã gửi</span><a v-for="item in report.evidence" :key="item.id" :href="item.file_path" target="_blank" rel="noopener">{{ item.file_name || 'Mở ảnh minh chứng' }}</a></div><div v-if="report.action_note" class="report-resolution"><AppIcon name="circleCheck" :size="18" /><div><strong>Phản hồi từ SportGo</strong><p>{{ report.action_note }}</p><small v-if="report.reviewed_at">Cập nhật {{ formatDate(report.reviewed_at) }}</small></div></div></section></div><aside class="sg-detail-modal__aside"><div class="sg-detail-modal__aside-heading"><span class="utility-card-icon is-blue"><AppIcon name="alert" :size="18" /></span><div><p class="sg3-kicker">Trạng thái xử lý</p><h3>{{ statusLabel(report.status) }}</h3></div></div><ol class="report-progress"><li class="is-done"><span></span><div><strong>Đã tiếp nhận</strong><small>SportGo đã ghi nhận phản ánh của bạn.</small></div></li><li :class="{ 'is-done': ['reviewing','resolved','dismissed'].includes(report.status) }"><span></span><div><strong>Kiểm duyệt nội dung</strong><small>Đội ngũ kiểm tra đối tượng và bằng chứng.</small></div></li><li :class="{ 'is-done': ['resolved','dismissed'].includes(report.status) }"><span></span><div><strong>Đã có kết quả</strong><small>Phản hồi chính thức sẽ xuất hiện ở phần bên trái.</small></div></li></ol><router-link class="sg3-button sg3-button--secondary" to="/community" @click="close"><AppIcon name="arrowLeft" :size="16" /> Về cộng đồng</router-link><router-link class="sg3-button sg3-button--quiet" :to="{ name:'client-report-detail', params:{ id: report.id } }" @click="close">Mở trang chi tiết đầy đủ</router-link></aside></div>
        </template>
      </section>
    </div>
  </Teleport>
</template>

<script>
import AppIcon from './AppIcon.vue';
import { reportService } from '../services/reportService.js';

export default {
  name: 'ReportDetailModal',
  components: { AppIcon },
  props: { isOpen: { type: Boolean, default: false }, reportId: { type: [Number, String], default: null } },
  emits: ['close'],
  data() { return { report: null, loading: false, error: '' }; },
  watch: { isOpen(value) { if (value) this.load(); }, reportId(value) { if (this.isOpen && value) this.load(); } },
  mounted() { window.addEventListener('keydown', this.handleKeydown); if (this.isOpen) this.load(); },
  beforeUnmount() { window.removeEventListener('keydown', this.handleKeydown); },
  methods: {
    async load() { if (!this.reportId) return; this.loading = true; this.error = ''; try { const response = await reportService.show(this.reportId); this.report = response.data; } catch (error) { this.error = error.message || 'Vui lòng thử lại.'; } finally { this.loading = false; } },
    close() { this.$emit('close'); },
    handleKeydown(event) { if (event.key === 'Escape' && this.isOpen) this.close(); },
    reasonLabel(reason) { return { spam:'Spam', offensive:'Nội dung phản cảm', fake:'Thông tin sai lệch', harassment:'Quấy rối', other:'Lý do khác' }[reason] || reason || 'Báo cáo'; },
    targetLabel(type) { return { venue:'Cụm sân', post:'Bài viết', venue_post:'Bài viết sân', community_post:'Bài cộng đồng', comment:'Bình luận', user:'Hồ sơ người dùng', player_post:'Bài giao lưu' }[type] || 'Nội dung'; },
    statusLabel(status) { return { pending:'Đang chờ xử lý', reviewing:'Đang kiểm duyệt', resolved:'Đã xử lý', dismissed:'Không vi phạm' }[status] || status || 'Chưa cập nhật'; },
    formatDate(value) { return value ? new Date(value).toLocaleString('vi-VN') : '-'; },
  },
};
</script>

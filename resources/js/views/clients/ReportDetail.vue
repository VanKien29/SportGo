<template>
  <div class="sg-client-page sg3-utility-page">
    <PublicNavbar />
    <main class="sg3-container sg3-profile-main report-detail-page">
      <router-link class="back-link" :to="{ name: 'client-reports' }"><AppIcon name="arrowLeft" :size="16" /> Báo cáo của tôi</router-link>
      <section v-if="loading" class="sg3-empty"><div><strong>Đang tải chi tiết</strong></div></section>
      <section v-else-if="error" class="sg3-error"><div><strong>Không tải được báo cáo</strong><p>{{ error }}</p></div></section>
      <template v-else-if="report">
        <div class="report-detail-workspace">
        <header class="sg3-page-head"><div><p class="sg3-kicker">CHI TIẾT BÁO CÁO</p><h1>Báo cáo #{{ report.id }}</h1><p>Gửi {{ formatDate(report.created_at) }} · {{ targetLabel(report.target_type) }} #{{ report.target_id }}</p></div><span class="sg3-status-pill" :class="`status-${report.status}`">{{ statusLabel(report.status) }}</span></header>
        <section class="sg3-card report-detail-card"><div class="report-detail-row"><span>Lý do</span><strong>{{ reasonLabel(report.reason) }}</strong></div><div class="report-detail-row"><span>Nội dung bổ sung</span><p>{{ report.description || 'Không có mô tả bổ sung.' }}</p></div><div v-if="report.evidence?.length" class="report-evidence"><span>Bằng chứng đã gửi</span><a v-for="item in report.evidence" :key="item.id" :href="item.file_path" target="_blank" rel="noopener">{{ item.file_name || 'Mở ảnh minh chứng' }}</a></div><div v-if="report.action_note" class="report-resolution"><AppIcon name="circleCheck" :size="18" /><div><strong>Phản hồi từ SportGo</strong><p>{{ report.action_note }}</p><small v-if="report.reviewed_at">Cập nhật {{ formatDate(report.reviewed_at) }}</small></div></div></section>
        <aside class="sg3-card report-context-card"><div class="utility-card-heading"><span class="utility-card-icon is-blue"><AppIcon name="alert" :size="18" /></span><div><p class="sg3-kicker">Trạng thái xử lý</p><h2>{{ statusLabel(report.status) }}</h2></div></div><ol class="report-progress"><li class="is-done"><span></span><div><strong>Đã tiếp nhận</strong><small>SportGo đã ghi nhận phản ánh của bạn.</small></div></li><li :class="{ 'is-done': ['reviewing','resolved','dismissed'].includes(report.status) }"><span></span><div><strong>Kiểm duyệt nội dung</strong><small>Đội ngũ kiểm tra đối tượng và bằng chứng.</small></div></li><li :class="{ 'is-done': ['resolved','dismissed'].includes(report.status) }"><span></span><div><strong>Đã có kết quả</strong><small>Phản hồi chính thức sẽ xuất hiện ở thẻ bên trái.</small></div></li></ol><router-link class="sg3-button sg3-button--secondary detail-context-action" to="/community"><AppIcon name="arrowLeft" :size="16" /> Về cộng đồng</router-link></aside>
        </div>
      </template>
    </main>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import { reportService } from '../../services/reportService.js';

export default {
  name: 'ClientReportDetail', components: { AppIcon, PublicNavbar },
  data() { return { report: null, loading: true, error: '' }; },
  async mounted() { try { const response = await reportService.show(this.$route.params.id); this.report = response.data; } catch (error) { this.error = error.message || 'Vui lòng thử lại.'; } finally { this.loading = false; } },
  methods: {
    reasonLabel(reason) { return { spam: 'Spam', offensive: 'Nội dung phản cảm', fake: 'Thông tin sai lệch', harassment: 'Quấy rối', other: 'Lý do khác' }[reason] || reason || 'Báo cáo'; },
    targetLabel(type) { return { venue: 'Cụm sân', post: 'Bài viết', venue_post: 'Bài viết sân', community_post: 'Bài cộng đồng', comment: 'Bình luận', user: 'Hồ sơ người dùng', player_post: 'Bài giao lưu' }[type] || 'Nội dung'; },
    statusLabel(status) { return { pending: 'Đang chờ xử lý', reviewing: 'Đang kiểm duyệt', resolved: 'Đã xử lý', dismissed: 'Không vi phạm' }[status] || status || 'Chưa cập nhật'; },
    formatDate(value) { return value ? new Date(value).toLocaleString('vi-VN') : '-'; },
  },
};
</script>

<style scoped>
.report-detail-workspace{display:grid;grid-template-columns:minmax(0,1fr) 292px;gap:20px;align-items:start}.report-detail-workspace>.sg3-page-head{grid-column:1 / -1;padding-top:18px}.report-detail-card{grid-column:1}.report-context-card{grid-column:2;grid-row:2;padding:21px}.utility-card-heading{display:flex;align-items:flex-start;gap:12px;margin-bottom:18px}.utility-card-heading h2{margin:3px 0 0;color:#193027;font-size:19px}.utility-card-icon{display:grid;flex:0 0 38px;width:38px;height:38px;place-items:center;border-radius:11px;background:#eaf3ff;color:#2b69a7}.report-progress{display:grid;gap:18px;margin:0;padding:0;list-style:none}.report-progress li{position:relative;display:flex;gap:11px}.report-progress li:not(:last-child)::after{position:absolute;left:5px;top:17px;width:1px;height:calc(100% + 10px);background:#d9e9df;content:""}.report-progress li>span{position:relative;z-index:1;flex:0 0 11px;width:11px;height:11px;margin-top:3px;border:2px solid #b7c9bd;border-radius:50%;background:#fff}.report-progress li.is-done>span{border-color:#0b9b55;background:#0b9b55}.report-progress strong,.report-progress small{display:block}.report-progress strong{color:#193027;font-size:13px}.report-progress small{margin-top:4px;color:#64748b;font-size:12px;line-height:1.5}.detail-context-action{width:100%;margin-top:20px}@media(max-width:820px){.report-detail-workspace{grid-template-columns:1fr}.report-detail-workspace>.sg3-page-head{grid-column:auto}.report-detail-card{grid-column:auto}.report-context-card{grid-column:auto;grid-row:auto;order:2}}
</style>

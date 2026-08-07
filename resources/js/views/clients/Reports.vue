<template>
  <div class="sg-client-page sg3-utility-page">
    <PublicNavbar />
    <main class="sg3-container sg3-profile-main">
      <div class="sg3-page-head"><div><div class="sg3-breadcrumbs"><router-link to="/profile">Tài khoản</router-link><span>/</span><strong>Báo cáo</strong></div><p class="sg3-kicker">AN TOÀN CỘNG ĐỒNG</p><h1>Báo cáo của tôi</h1><p>Theo dõi mã báo cáo, bằng chứng đã gửi và phản hồi từ đội ngũ kiểm duyệt.</p></div><router-link class="sg3-button sg3-button--secondary" to="/community"><AppIcon name="arrowLeft" :size="16" /> Về cộng đồng</router-link></div>
      <section v-if="loading" class="sg3-empty"><div><strong>Đang tải báo cáo</strong><p>Đang lấy lịch sử phản ánh của bạn.</p></div></section>
      <section v-else-if="error" class="sg3-error"><div><strong>Không tải được báo cáo</strong><p>{{ error }}</p><button class="sg3-button sg3-button--primary" type="button" @click="load">Thử lại</button></div></section>
      <div v-else class="report-list-workspace">
        <section class="sg3-card sg3-request-card"><header><div><p class="sg3-kicker">LỊCH SỬ PHẢN ÁNH</p><h2>Các báo cáo đã gửi</h2><span>{{ total }} báo cáo được ghi nhận</span></div><button class="sg3-button sg3-button--quiet" type="button" @click="load">Làm mới</button></header><div v-if="!reports.length" class="sg3-empty sg3-empty--inline"><div><strong>Bạn chưa gửi báo cáo nào</strong><p>Khi phát hiện nội dung không phù hợp, bạn có thể báo cáo ngay tại trang chi tiết.</p></div></div><article v-for="report in reports" :key="report.id" class="sg3-request-row"><div><strong>#{{ report.id }} · {{ reasonLabel(report.reason) }}</strong><span>{{ targetLabel(report.target_type) }} · {{ report.target_id }}</span><small>Gửi {{ formatDate(report.created_at) }}</small></div><span class="sg3-status-pill" :class="`status-${report.status}`">{{ statusLabel(report.status) }}</span><button type="button" class="sg3-detail-trigger" aria-label="Xem chi tiết" @click="openReport(report.id)"><AppIcon name="chevronRight" :size="18" /></button></article><footer v-if="lastPage > 1" class="sg3-pagination"><button class="sg3-button sg3-button--secondary" :disabled="page <= 1" @click="goPage(page - 1)">Trước</button><span>Trang {{ page }} / {{ lastPage }}</span><button class="sg3-button sg3-button--secondary" :disabled="page >= lastPage" @click="goPage(page + 1)">Sau</button></footer></section>
        <aside class="sg3-card utility-guide-card report-list-guide"><div class="utility-card-heading"><span class="utility-card-icon"><AppIcon name="shieldCheck" :size="18" /></span><div><p class="sg3-kicker">An toàn cộng đồng</p><h2>Khi nào nên báo cáo?</h2></div></div><ul class="report-guide-list"><li>Nội dung xúc phạm, quấy rối hoặc có dấu hiệu lừa đảo.</li><li>Thông tin sân/bài viết không đúng hoặc gây ảnh hưởng người khác.</li><li>Đính kèm bằng chứng để đội ngũ kiểm duyệt có đủ ngữ cảnh.</li></ul><router-link class="sg3-button sg3-button--secondary utility-guide-link" to="/community"><AppIcon name="arrowLeft" :size="16" /> Về cộng đồng</router-link></aside>
      </div>
    </main>
    <ReportDetailModal :is-open="Boolean(selectedReportId)" :report-id="selectedReportId" @close="selectedReportId = null" />
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import ReportDetailModal from '../../components/ReportDetailModal.vue';
import { reportService } from '../../services/reportService.js';

export default {
  name: 'ClientReports',
  components: { AppIcon, PublicNavbar, ReportDetailModal },
  data() { return { reports: [], page: 1, lastPage: 1, total: 0, loading: true, error: '', selectedReportId: null }; },
  mounted() { this.load(); },
  methods: {
    async load() { this.loading = true; this.error = ''; try { const response = await reportService.list(this.page); this.reports = response.data || []; this.page = Number(response.current_page || this.page); this.lastPage = Number(response.last_page || 1); this.total = Number(response.total || this.reports.length); } catch (error) { this.error = error.message || 'Vui lòng thử lại.'; } finally { this.loading = false; } },
    openReport(id) { this.selectedReportId = id; },
    goPage(page) { this.page = page; this.load(); },
    reasonLabel(reason) { return { spam: 'Spam', offensive: 'Nội dung phản cảm', fake: 'Thông tin sai lệch', harassment: 'Quấy rối', other: 'Lý do khác' }[reason] || reason || 'Báo cáo'; },
    targetLabel(type) { return { venue: 'Cụm sân', post: 'Bài viết', venue_post: 'Bài viết sân', community_post: 'Bài cộng đồng', comment: 'Bình luận', user: 'Hồ sơ người dùng', player_post: 'Bài giao lưu' }[type] || 'Nội dung'; },
    statusLabel(status) { return { pending: 'Đang chờ xử lý', reviewing: 'Đang kiểm duyệt', resolved: 'Đã xử lý', dismissed: 'Không vi phạm' }[status] || status || 'Chưa cập nhật'; },
    formatDate(value) { return value ? new Date(value).toLocaleString('vi-VN') : '-'; },
  },
};
</script>

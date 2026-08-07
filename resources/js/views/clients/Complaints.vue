<template>
  <div class="sg-client-page sg3-utility-page">
    <PublicNavbar />
    <main class="sg3-container sg3-profile-main">
      <div class="sg3-page-head">
        <div>
          <div class="sg3-breadcrumbs"><router-link to="/profile">Tài khoản</router-link><span>/</span><strong>Hỗ trợ</strong></div>
          <p class="sg3-kicker">Trung tâm hỗ trợ</p>
          <h1>Khiếu nại của tôi</h1>
          <p>Gửi yêu cầu có ngữ cảnh, theo dõi phản hồi và xem toàn bộ lịch sử xử lý.</p>
        </div>
        <router-link class="sg3-button sg3-button--primary" :to="{ name: 'client-complaint-create' }"><AppIcon name="plus" :size="16" /> Tạo khiếu nại</router-link>
      </div>

      <section class="sg-utility-insights" aria-label="Tổng quan khiếu nại">
        <article class="sg3-card"><span class="sg-utility-insight-icon"><AppIcon name="messageSquare" :size="17" /></span><div><small>Tổng yêu cầu</small><strong>{{ total }}</strong><span>Tất cả lịch sử hỗ trợ</span></div></article>
        <article class="sg3-card"><span class="sg-utility-insight-icon is-amber"><AppIcon name="clock" :size="17" /></span><div><small>Đang xử lý</small><strong>{{ processingCount }}</strong><span>SportGo đang tiếp nhận</span></div></article>
        <article class="sg3-card"><span class="sg-utility-insight-icon is-blue"><AppIcon name="circleCheck" :size="17" /></span><div><small>Đã khép lại</small><strong>{{ closedCount }}</strong><span>Có thể xem lại chi tiết</span></div></article>
      </section>

      <section v-if="loading" class="sg3-empty"><div><strong>Đang tải khiếu nại</strong><p>Đang lấy các yêu cầu hỗ trợ của bạn.</p></div></section>
      <section v-else-if="error" class="sg3-error"><div><strong>Không tải được khiếu nại</strong><p>{{ error }}</p><button class="sg3-button sg3-button--primary" type="button" @click="load">Thử lại</button></div></section>
      <section v-else class="sg3-card sg3-request-card">
        <header><div><p class="sg3-kicker">Hồ sơ hỗ trợ</p><h2>Yêu cầu gần đây</h2><span>{{ processingCount ? `Bạn có ${processingCount} yêu cầu đang được theo dõi.` : 'Không có yêu cầu đang chờ xử lý.' }}</span></div><button class="sg3-button sg3-button--quiet" type="button" @click="load">Làm mới</button></header>
        <div v-if="!complaints.length" class="sg3-empty sg3-empty--inline"><div><strong>Bạn chưa có khiếu nại nào</strong><p>Hãy bắt đầu từ một booking cụ thể để SportGo hỗ trợ nhanh hơn.</p><router-link class="sg3-button sg3-button--secondary" to="/bookings">Xem lịch đặt sân</router-link></div></div>
        <article v-for="complaint in complaints" :key="complaint.id" class="sg3-request-row">
          <div><strong>{{ typeLabel(complaint.complaint_type) }} · #{{ complaint.id }}</strong><span>{{ complaint.content }}</span><small>{{ complaint.venue_cluster?.name || 'Hỗ trợ hệ thống' }} · {{ formatDate(complaint.created_at) }}</small></div>
          <span class="sg3-status-pill" :class="`status-${complaint.status}`">{{ statusLabel(complaint.status) }}</span>
          <button type="button" class="sg3-detail-trigger" aria-label="Xem chi tiết" @click="openComplaint(complaint.id)"><AppIcon name="chevronRight" :size="18" /></button>
        </article>
        <footer v-if="lastPage > 1" class="sg3-pagination"><button class="sg3-button sg3-button--secondary" type="button" :disabled="page <= 1" @click="goPage(page - 1)">Trước</button><span>Trang {{ page }} / {{ lastPage }}</span><button class="sg3-button sg3-button--secondary" type="button" :disabled="page >= lastPage" @click="goPage(page + 1)">Sau</button></footer>
      </section>
    </main>
    <ComplaintDetailModal :is-open="Boolean(selectedComplaintId)" :complaint-id="selectedComplaintId" @close="selectedComplaintId = null" />
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ComplaintDetailModal from '../../components/ComplaintDetailModal.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import { complaintService } from '../../services/complaintService.js';

export default {
  name: 'ClientComplaints',
  components: { AppIcon, ComplaintDetailModal, PublicNavbar },
  data() { return { complaints: [], page: 1, lastPage: 1, total: 0, loading: true, error: '', selectedComplaintId: null }; },
  computed: { processingCount() { return this.complaints.filter((item) => ['open', 'processing'].includes(item.status)).length; }, closedCount() { return this.complaints.filter((item) => ['resolved', 'rejected', 'closed'].includes(item.status)).length; } },
  mounted() { this.load(); },
  methods: {
    async load() { this.loading = true; this.error = ''; try { const response = await complaintService.list({ page: this.page }); this.complaints = response.data || []; this.page = Number(response.current_page || this.page); this.lastPage = Number(response.last_page || 1); this.total = Number(response.total || this.complaints.length); } catch (error) { this.error = error.message || 'Vui lòng thử lại.'; } finally { this.loading = false; } },
    openComplaint(id) { this.selectedComplaintId = id; },
    goPage(page) { this.page = page; this.load(); },
    typeLabel(type) { return type === 'venue' ? 'Khiếu nại cụm sân' : 'Khiếu nại hệ thống'; },
    statusLabel(status) { return { open: 'Mới gửi', processing: 'Đang xử lý', resolved: 'Đã xử lý', rejected: 'Từ chối', closed: 'Đã đóng' }[status] || status || 'Chưa cập nhật'; },
    formatDate(value) { return value ? new Date(value).toLocaleString('vi-VN') : '-'; },
  },
};
</script>

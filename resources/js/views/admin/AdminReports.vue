<template>
  <section class="moderation-page">
    <header class="page-head">
      <div>
        <h2>Xử lý báo cáo</h2>
        <p>Kiểm duyệt nội dung và xử lý hành vi vi phạm trong cộng đồng.</p>
      </div>
      <button class="btn secondary" type="button" :disabled="loading" @click="loadReports">
        <AppIcon name="refresh" size="17" />
        Tải lại
      </button>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="stat-grid">
      <article class="stat-card"><strong>{{ summary.total || 0 }}</strong><span>Tổng báo cáo</span></article>
      <article class="stat-card warning"><strong>{{ summary.pending || 0 }}</strong><span>Chờ xử lý</span></article>
      <article class="stat-card"><strong>{{ summary.reviewing || 0 }}</strong><span>Đang kiểm duyệt</span></article>
      <article class="stat-card success"><strong>{{ summary.resolved || 0 }}</strong><span>Đã xử lý</span></article>
    </section>

    <section class="filter-panel">
      <div class="filter-bar">
        <label class="search-box">
          <AppIcon name="search" size="17" />
          <input v-model.trim="filters.keyword" placeholder="Tìm người gửi, nội dung hoặc mã đối tượng" @keyup.enter="loadReports" />
        </label>
        <select v-model="filters.target_type" @change="loadReports">
          <option value="">Tất cả đối tượng</option>
          <option v-for="item in targetTypes" :key="item.value" :value="item.value">{{ item.label }}</option>
        </select>
        <select v-model="filters.reason" @change="loadReports">
          <option value="">Tất cả lý do</option>
          <option v-for="item in reasons" :key="item.value" :value="item.value">{{ item.label }}</option>
        </select>
        <select v-model="filters.status" @change="loadReports">
          <option value="">Tất cả trạng thái</option>
          <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
        </select>
        <button class="btn primary" type="button" @click="loadReports">Lọc</button>
      </div>
    </section>

    <div v-if="loading" class="empty-state">Đang tải danh sách báo cáo...</div>
    <div v-else-if="reports.length === 0" class="empty-state">Không có báo cáo phù hợp.</div>
    <section v-else class="record-list">
      <article v-for="report in reports" :key="report.id" class="record-card">
        <header class="card-head">
          <div class="card-title">
            <strong :title="report.target_label">{{ report.target_label }}</strong>
            <span>{{ targetLabel(report.target_type) }} · {{ shortId(report.id) }}</span>
          </div>
          <div class="badge-row">
            <span class="badge" :class="report.reason">{{ reasonLabel(report.reason) }}</span>
            <span class="badge" :class="report.status">{{ statusLabel(report.status) }}</span>
          </div>
        </header>
        <p class="card-content">{{ report.description || 'Không có mô tả bổ sung.' }}</p>
        <footer class="card-footer">
          <div class="meta-item"><span>Người gửi</span><strong>{{ report.reporter?.full_name || '-' }}</strong></div>
          <div class="meta-item"><span>Thời gian</span><strong>{{ formatDateTime(report.created_at) }}</strong></div>
          <div class="meta-item"><span>Người xử lý</span><strong>{{ report.reviewed_by?.full_name || 'Chưa phân công' }}</strong></div>
          <div class="meta-item"><span>Kết quả</span><strong>{{ actionLabel(report.action_taken) }}</strong></div>
          <div class="card-actions">
            <button class="btn primary" type="button" @click="openDetail(report)">Xem và xử lý</button>
          </div>
        </footer>
      </article>
    </section>

    <div v-if="detailOpen" class="detail-backdrop" @click.self="closeDetail">
      <section class="detail-modal">
        <header class="detail-head">
          <div>
            <h3>Chi tiết báo cáo</h3>
            <p>{{ selected ? `${targetLabel(selected.target_type)} · ${shortId(selected.id)}` : 'Đang tải...' }}</p>
          </div>
          <button class="btn secondary" type="button" @click="closeDetail">Đóng</button>
        </header>

        <div v-if="detailLoading" class="empty-state">Đang tải chi tiết...</div>
        <div v-else-if="selected" class="detail-body">
          <main class="detail-main">
            <section class="detail-section">
              <h4>Thông tin báo cáo</h4>
              <div class="detail-grid">
                <div class="detail-field"><span>Người gửi</span><strong>{{ selected.reporter?.full_name || '-' }}</strong></div>
                <div class="detail-field"><span>Lý do</span><strong>{{ reasonLabel(selected.reason) }}</strong></div>
                <div class="detail-field"><span>Trạng thái</span><strong>{{ statusLabel(selected.status) }}</strong></div>
                <div class="detail-field"><span>Đối tượng</span><strong>{{ targetLabel(selected.target_type) }}</strong></div>
                <div class="detail-field"><span>Người bị báo cáo</span><strong>{{ selected.reported_user?.full_name || '-' }}</strong></div>
                <div class="detail-field"><span>Thời gian gửi</span><strong>{{ formatDateTime(selected.created_at) }}</strong></div>
              </div>
            </section>

            <section class="detail-section">
              <h4>Nội dung phản ánh</h4>
              <p class="content-box">{{ selected.description || 'Không có mô tả bổ sung.' }}</p>
            </section>

            <section class="detail-section">
              <h4>Đối tượng bị báo cáo</h4>
              <p class="content-box">{{ selected.target?.title || selected.target?.content || selected.target?.label || 'Đối tượng không còn tồn tại.' }}</p>
            </section>

            <section class="detail-section">
              <h4>Bằng chứng</h4>
              <div v-if="selected.evidence?.length" class="evidence-list">
                <a v-for="file in selected.evidence" :key="file.id" class="evidence-item" :href="mediaUrl(file.file_path)" target="_blank">
                  <strong>{{ file.file_name }}</strong>
                  <span>{{ file.mime_type }} · {{ formatFileSize(file.file_size) }}</span>
                </a>
              </div>
              <p v-else class="content-box">Không có tệp bằng chứng.</p>
            </section>

            <section class="detail-section">
              <h4>Lịch sử xử lý</h4>
              <div v-if="auditLogs.length" class="timeline">
                <article v-for="log in auditLogs" :key="log.id" class="timeline-item">
                  <strong>{{ auditLabel(log.action) }}</strong>
                  <span>{{ log.actor?.full_name || 'Hệ thống' }} · {{ formatDateTime(log.created_at) }}</span>
                </article>
              </div>
              <p v-else class="content-box">Chưa có lịch sử xử lý.</p>
            </section>
          </main>

          <aside class="side-panel">
            <h4>Thao tác xử lý</h4>
            <div class="form-stack">
              <button v-if="selected.status === 'pending'" class="btn secondary" type="button" :disabled="saving" @click="takeReview">
                Nhận kiểm duyệt
              </button>
              <label>
                Hành động
                <select v-model="form.action_taken">
                  <option value="">Chọn hành động</option>
                  <option v-for="action in selected.available_actions" :key="action" :value="action">{{ actionLabel(action) }}</option>
                </select>
              </label>
              <label v-if="['account_locked', 'venue_locked'].includes(form.action_taken)">
                Số ngày khóa
                <input v-model.number="form.lock_days" type="number" min="1" max="365" placeholder="Bỏ trống để khóa vĩnh viễn" />
              </label>
              <label>
                Ghi chú xử lý
                <textarea v-model.trim="form.action_note" rows="6" placeholder="Nêu kết quả kiểm tra và căn cứ xử lý"></textarea>
              </label>
              <div class="modal-actions">
                <button class="btn secondary" type="button" :disabled="saving" @click="submitDecision('dismissed')">Bỏ qua</button>
                <button class="btn danger" type="button" :disabled="saving || !form.action_taken" @click="submitDecision('resolved')">Xử lý vi phạm</button>
              </div>
            </div>
          </aside>
        </div>
      </section>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminReportService } from '../../services/adminModeration.js';

export default {
  name: 'AdminReports',
  components: { AppIcon },
  data() {
    return {
      reports: [],
      summary: {},
      filters: { keyword: '', target_type: '', reason: '', status: '' },
      targetTypes: [
        { value: 'post', label: 'Bài viết cộng đồng' },
        { value: 'comment', label: 'Bình luận' },
        { value: 'venue_post', label: 'Bài viết cụm sân' },
        { value: 'player_post', label: 'Bài kèo' },
        { value: 'user', label: 'Người dùng' },
        { value: 'venue', label: 'Cụm sân' },
      ],
      reasons: [
        { value: 'spam', label: 'Spam' },
        { value: 'offensive', label: 'Nội dung phản cảm' },
        { value: 'fake', label: 'Giả mạo / lừa đảo' },
        { value: 'harassment', label: 'Quấy rối' },
        { value: 'other', label: 'Khác' },
      ],
      statuses: [
        { value: 'pending', label: 'Chờ xử lý' },
        { value: 'reviewing', label: 'Đang kiểm duyệt' },
        { value: 'resolved', label: 'Đã xử lý' },
        { value: 'dismissed', label: 'Đã bỏ qua' },
      ],
      selected: null,
      auditLogs: [],
      detailOpen: false,
      detailLoading: false,
      loading: false,
      saving: false,
      error: '',
      success: '',
      form: { action_taken: '', action_note: '', lock_days: null },
    };
  },
  mounted() {
    this.loadReports();
  },
  methods: {
    async loadReports() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminReportService.list(this.filters);
        this.reports = response.data || [];
        this.summary = response.summary || {};
      } catch (error) {
        this.error = error.message;
      } finally {
        this.loading = false;
      }
    },
    async openDetail(report) {
      this.detailOpen = true;
      this.detailLoading = true;
      this.selected = null;
      this.auditLogs = [];
      try {
        const response = await adminReportService.show(report.id);
        this.selected = response.data.report;
        this.auditLogs = response.data.audit_logs || [];
        this.form = {
          action_taken: this.selected.action_taken || '',
          action_note: this.selected.action_note || '',
          lock_days: null,
        };
      } catch (error) {
        this.error = error.message;
        this.detailOpen = false;
      } finally {
        this.detailLoading = false;
      }
    },
    closeDetail() {
      this.detailOpen = false;
      this.selected = null;
    },
    async takeReview() {
      this.saving = true;
      try {
        await adminReportService.review(this.selected.id);
        await this.refreshDetail();
        this.success = 'Đã nhận kiểm duyệt báo cáo.';
        await this.loadReports();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.saving = false;
      }
    },
    async submitDecision(decision) {
      if (!this.form.action_note) {
        this.error = 'Vui lòng nhập ghi chú xử lý.';
        return;
      }
      this.saving = true;
      try {
        const response = await adminReportService.resolve(this.selected.id, { ...this.form, decision });
        this.success = response.message;
        await this.loadReports();
        await this.refreshDetail();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.saving = false;
      }
    },
    async refreshDetail() {
      const response = await adminReportService.show(this.selected.id);
      this.selected = response.data.report;
      this.auditLogs = response.data.audit_logs || [];
    },
    targetLabel(value) {
      return this.targetTypes.find((item) => item.value === value)?.label || value || '-';
    },
    reasonLabel(value) {
      return this.reasons.find((item) => item.value === value)?.label || value || '-';
    },
    statusLabel(value) {
      return this.statuses.find((item) => item.value === value)?.label || value || '-';
    },
    actionLabel(value) {
      return {
        warning: 'Cảnh báo',
        content_hidden: 'Ẩn nội dung',
        content_deleted: 'Xóa nội dung',
        account_locked: 'Khóa tài khoản',
        venue_locked: 'Khóa cụm sân',
      }[value] || (value ? value : 'Chưa xử lý');
    },
    auditLabel(value) {
      return {
        'report.reviewing': 'Nhận kiểm duyệt',
        'report.resolved': 'Xử lý báo cáo',
        'report.dismissed': 'Bỏ qua báo cáo',
        'content.hidden': 'Ẩn nội dung',
        'content.deleted': 'Xóa nội dung',
        'user.locked_by_report': 'Khóa tài khoản',
        'venue.locked_by_report': 'Khóa cụm sân',
      }[value] || value;
    },
    shortId(value) {
      return value ? `#${value.slice(0, 8)}` : '';
    },
    formatDateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
    formatFileSize(value) {
      return value ? `${Math.max(1, Math.round(value / 1024))} KB` : '0 KB';
    },
    mediaUrl(path) {
      return path?.startsWith('http') ? path : `/storage/${path}`;
    },
  },
};
</script>

<style src="../../../css/admin/moderation.css" scoped></style>

<template>
  <section class="moderation-page">
    <header class="page-head">
      <div>
        <h2>Xử lý khiếu nại</h2>
        <p>Tiếp nhận, phân công và giải quyết tranh chấp dịch vụ hoặc nền tảng.</p>
      </div>
      <button class="btn secondary" type="button" :disabled="loading" @click="loadComplaints">
        <AppIcon name="refresh" size="17" />
        Tải lại
      </button>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="stat-grid">
      <article class="stat-card"><strong>{{ summary.total || 0 }}</strong><span>Tổng khiếu nại</span></article>
      <article class="stat-card warning"><strong>{{ summary.open || 0 }}</strong><span>Chờ tiếp nhận</span></article>
      <article class="stat-card"><strong>{{ summary.processing || 0 }}</strong><span>Đang xử lý</span></article>
      <article class="stat-card success"><strong>{{ summary.resolved || 0 }}</strong><span>Đã giải quyết</span></article>
    </section>

    <section class="filter-panel">
      <div class="filter-bar">
        <label class="search-box">
          <AppIcon name="search" size="17" />
          <input v-model.trim="filters.keyword" placeholder="Tìm khách hàng, booking, cụm sân hoặc nội dung" @keyup.enter="loadComplaints" />
        </label>
        <select v-model="filters.complaint_type" @change="loadComplaints">
          <option value="">Tất cả loại</option>
          <option value="venue">Khiếu nại cụm sân</option>
          <option value="system">Khiếu nại hệ thống</option>
        </select>
        <select v-model="filters.status" @change="loadComplaints">
          <option value="">Tất cả trạng thái</option>
          <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
        </select>
        <select v-model="filters.assigned_to" @change="loadComplaints">
          <option value="">Tất cả người xử lý</option>
          <option value="unassigned">Chưa phân công</option>
          <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.full_name }}</option>
        </select>
        <button class="btn primary" type="button" @click="loadComplaints">Lọc</button>
      </div>
    </section>

    <div v-if="loading" class="empty-state">Đang tải danh sách khiếu nại...</div>
    <div v-else-if="complaints.length === 0" class="empty-state">Không có khiếu nại phù hợp.</div>
    <section v-else class="record-list">
      <article v-for="complaint in complaints" :key="complaint.id" class="record-card">
        <header class="card-head">
          <div class="card-title">
            <strong>{{ complaint.customer?.full_name || 'Khách hàng' }}</strong>
            <span>{{ typeLabel(complaint.complaint_type) }} · {{ shortId(complaint.id) }}</span>
          </div>
          <div class="badge-row">
            <span class="badge">{{ typeLabel(complaint.complaint_type) }}</span>
            <span class="badge" :class="complaint.status">{{ statusLabel(complaint.status) }}</span>
          </div>
        </header>
        <p class="card-content">{{ complaint.content }}</p>
        <footer class="card-footer">
          <div class="meta-item"><span>Booking</span><strong>{{ complaint.booking?.booking_code || 'Không liên quan' }}</strong></div>
          <div class="meta-item"><span>Cụm sân</span><strong>{{ complaint.venue_cluster?.name || 'Hệ thống' }}</strong></div>
          <div class="meta-item"><span>Người xử lý</span><strong>{{ complaint.assigned_to?.full_name || 'Chưa phân công' }}</strong></div>
          <div class="meta-item"><span>Thời gian</span><strong>{{ formatDateTime(complaint.created_at) }}</strong></div>
          <div class="card-actions">
            <button class="btn primary" type="button" @click="openDetail(complaint)">Xem và xử lý</button>
          </div>
        </footer>
      </article>
    </section>

    <div v-if="detailOpen" class="detail-backdrop" @click.self="closeDetail">
      <section class="detail-modal">
        <header class="detail-head">
          <div>
            <h3>Chi tiết khiếu nại</h3>
            <p>{{ selected ? `${typeLabel(selected.complaint_type)} · ${shortId(selected.id)}` : 'Đang tải...' }}</p>
          </div>
          <button class="btn secondary" type="button" @click="closeDetail">Đóng</button>
        </header>

        <div v-if="detailLoading" class="empty-state">Đang tải chi tiết...</div>
        <div v-else-if="selected" class="detail-body">
          <main class="detail-main">
            <section class="detail-section">
              <h4>Thông tin tiếp nhận</h4>
              <div class="detail-grid">
                <div class="detail-field"><span>Khách gửi</span><strong>{{ selected.customer?.full_name || '-' }}</strong></div>
                <div class="detail-field"><span>Liên hệ</span><strong>{{ selected.customer?.phone || selected.customer?.email || '-' }}</strong></div>
                <div class="detail-field"><span>Trạng thái</span><strong>{{ statusLabel(selected.status) }}</strong></div>
                <div class="detail-field"><span>Booking</span><strong>{{ selected.booking?.booking_code || 'Không liên quan' }}</strong></div>
                <div class="detail-field"><span>Cụm sân</span><strong>{{ selected.venue_cluster?.name || 'Hệ thống' }}</strong></div>
                <div class="detail-field"><span>Người xử lý</span><strong>{{ selected.assigned_to?.full_name || 'Chưa phân công' }}</strong></div>
              </div>
            </section>

            <section class="detail-section">
              <h4>Nội dung khiếu nại</h4>
              <p class="content-box">{{ selected.content }}</p>
            </section>

            <section v-if="selected.booking_detail" class="detail-section">
              <h4>Booking và thanh toán liên quan</h4>
              <div class="detail-grid">
                <div class="detail-field"><span>Mã booking</span><strong>{{ selected.booking_detail.booking_code }}</strong></div>
                <div class="detail-field"><span>Ngày đặt</span><strong>{{ formatDate(selected.booking_detail.booking_date) }}</strong></div>
                <div class="detail-field"><span>Khung giờ</span><strong>{{ selected.booking_detail.start_time }} – {{ selected.booking_detail.end_time }}</strong></div>
                <div class="detail-field"><span>Trạng thái</span><strong>{{ selected.booking_detail.status }}</strong></div>
                <div class="detail-field"><span>Tổng tiền</span><strong>{{ money(selected.booking_detail.total_price) }}</strong></div>
                <div class="detail-field"><span>Đã thanh toán</span><strong>{{ money(paidAmount(selected.booking_detail.payments)) }}</strong></div>
              </div>
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
            <h4>Phân công và kết quả</h4>
            <div class="form-stack">
              <label>
                Người xử lý
                <select v-model="form.assigned_to">
                  <option value="">Chọn người xử lý</option>
                  <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.full_name }}</option>
                </select>
              </label>
              <button class="btn secondary" type="button" :disabled="saving || !form.assigned_to" @click="assignComplaint">Lưu phân công</button>
              <label>
                Kết quả
                <select v-model="form.status">
                  <option value="processing">Đang xử lý</option>
                  <option value="resolved">Đã giải quyết</option>
                  <option value="rejected">Từ chối</option>
                  <option value="closed">Đóng khiếu nại</option>
                </select>
              </label>
              <label>
                Phản hồi xử lý
                <textarea v-model.trim="form.resolve_note" rows="7" placeholder="Nêu kết quả, căn cứ và hướng xử lý cho khách hàng"></textarea>
              </label>
              <button class="btn primary" type="button" :disabled="saving || !form.resolve_note" @click="resolveComplaint">Cập nhật kết quả</button>
            </div>
          </aside>
        </div>
      </section>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminComplaintService } from '../../services/adminModeration.js';

export default {
  name: 'AdminComplaints',
  components: { AppIcon },
  data() {
    return {
      complaints: [],
      summary: {},
      staff: [],
      filters: { keyword: '', complaint_type: '', status: '', assigned_to: '' },
      statuses: [
        { value: 'open', label: 'Chờ tiếp nhận' },
        { value: 'processing', label: 'Đang xử lý' },
        { value: 'resolved', label: 'Đã giải quyết' },
        { value: 'rejected', label: 'Đã từ chối' },
        { value: 'closed', label: 'Đã đóng' },
      ],
      selected: null,
      auditLogs: [],
      detailOpen: false,
      detailLoading: false,
      loading: false,
      saving: false,
      error: '',
      success: '',
      form: { assigned_to: '', status: 'processing', resolve_note: '' },
    };
  },
  mounted() {
    this.loadComplaints();
  },
  methods: {
    async loadComplaints() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminComplaintService.list(this.filters);
        this.complaints = response.data || [];
        this.summary = response.summary || {};
        this.staff = response.staff || [];
      } catch (error) {
        this.error = error.message;
      } finally {
        this.loading = false;
      }
    },
    async openDetail(complaint) {
      this.detailOpen = true;
      this.detailLoading = true;
      this.selected = null;
      try {
        const response = await adminComplaintService.show(complaint.id);
        this.selected = response.data.complaint;
        this.auditLogs = response.data.audit_logs || [];
        this.syncForm();
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
    syncForm() {
      this.form = {
        assigned_to: this.selected.assigned_to?.id || '',
        status: ['resolved', 'rejected', 'closed'].includes(this.selected.status) ? this.selected.status : 'processing',
        resolve_note: this.selected.resolve_note || '',
      };
    },
    async assignComplaint() {
      this.saving = true;
      try {
        const response = await adminComplaintService.assign(this.selected.id, this.form.assigned_to);
        this.success = response.message;
        await this.loadComplaints();
        await this.refreshDetail();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.saving = false;
      }
    },
    async resolveComplaint() {
      this.saving = true;
      try {
        const response = await adminComplaintService.resolve(this.selected.id, {
          status: this.form.status,
          resolve_note: this.form.resolve_note,
        });
        this.success = response.message;
        await this.loadComplaints();
        await this.refreshDetail();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.saving = false;
      }
    },
    async refreshDetail() {
      const response = await adminComplaintService.show(this.selected.id);
      this.selected = response.data.complaint;
      this.auditLogs = response.data.audit_logs || [];
      this.syncForm();
    },
    typeLabel(value) {
      return value === 'venue' ? 'Cụm sân' : 'Hệ thống';
    },
    statusLabel(value) {
      return this.statuses.find((item) => item.value === value)?.label || value || '-';
    },
    auditLabel(value) {
      return {
        'complaint.assigned': 'Phân công người xử lý',
        'complaint.processing': 'Cập nhật đang xử lý',
        'complaint.resolved': 'Giải quyết khiếu nại',
        'complaint.rejected': 'Từ chối khiếu nại',
        'complaint.closed': 'Đóng khiếu nại',
      }[value] || value;
    },
    paidAmount(payments = []) {
      return payments.filter((item) => item.status === 'paid').reduce((sum, item) => sum + Number(item.amount || 0), 0);
    },
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    shortId(value) {
      return value ? `#${value.slice(0, 8)}` : '';
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
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

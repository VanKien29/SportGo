<template>
  <section class="moderation-page">


    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

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
        <input v-model="filters.date_from" type="date" aria-label="Từ ngày" @change="loadComplaints" />
        <input v-model="filters.date_to" type="date" aria-label="Đến ngày" :min="filters.date_from || undefined" @change="loadComplaints" />
        <ActionIconButton icon="filter" label="Lọc danh sách" variant="primary" @click="loadComplaints" />
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
            <ActionIconButton icon="eye" label="Xem và xử lý" variant="primary" @click="openDetail(complaint)" />
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
          <ActionIconButton icon="x" label="Đóng" @click="closeDetail" />
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
            <h4>Xử lý và kết quả</h4>

            <p v-if="isTerminalStatus(selected.status)" class="content-box">
              Khiếu nại đã kết thúc với trạng thái “{{ statusLabel(selected.status) }}”.
              Kết quả được giữ lại trong lịch sử xử lý.
            </p>

            <div class="handler-info" style="margin-bottom: 12px; font-size: 13px; color: #475569;">
              <span style="font-weight: 800; display: block; margin-bottom: 4px;">Người xử lý:</span>
              <span style="background: #f1f5f9; padding: 6px 10px; border-radius: 6px; display: block; font-weight: 500;">
                {{ selected.assigned_to?.full_name || 'Hệ thống tự động gán khi cập nhật' }}
              </span>
            </div>

            <div v-if="!isTerminalStatus(selected.status)" class="form-stack">
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

    <!-- Modal Cấu hình tự động xử lý khiếu nại -->
    <div v-if="showAutoResolveModal" class="detail-backdrop" @click.self="closeAutoResolveModal">
      <div class="modal" style="max-width: 650px; background: #fff; border-radius: 12px; padding: 22px; display: grid; gap: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <h3 style="margin: 0;">Cấu hình tự động xử lý khiếu nại</h3>
        <p class="muted" style="margin: 0; color: #64748b; font-size: 14px;">Thiết lập tự động giải quyết khiếu nại của khách hàng khi vừa được gửi lên.</p>
        
        <div v-if="autoResolveLoading" class="state" style="padding: 20px; text-align: center; color: #64748b;">Đang tải cấu hình...</div>
        <template v-else-if="autoResolveConfigData">
          <!-- Chọn Đối Tượng Tab cấu hình -->
          <div class="auto-tabs" style="display: flex; gap: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 8px;">
            <button
              v-for="cfg in autoResolveConfigData.configs"
              :key="cfg.target_type"
              type="button"
              :class="{ active: activeAutoTab === cfg.target_type }"
              @click="activeAutoTab = cfg.target_type"
              style="border: 0; background: transparent; padding: 8px 12px; font-weight: 800; cursor: pointer; border-bottom: 2px solid transparent; color: #64748b; font-size: 14px;"
              :style="activeAutoTab === cfg.target_type ? 'color: #166534; border-bottom-color: #22c55e;' : ''"
            >
              {{ cfg.target_type_label }}
            </button>
          </div>

          <div v-if="currentAutoConfig" class="auto-config-body" style="display: grid; gap: 14px;">
            <!-- Cấu hình chỉnh sửa -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                <span style="color: #334155; font-size: 0.9rem; font-weight: 600;">Tự động xử lý khiếu nại:</span>
                <!-- Switch toggle -->
                <div 
                  class="toggle-slider" 
                  :class="{ on: currentAutoConfig.is_auto_resolve_enabled }" 
                  @click="currentAutoConfig.is_auto_resolve_enabled = !currentAutoConfig.is_auto_resolve_enabled"
                  style="width: 48px; height: 26px; border-radius: 13px; background: #e2e8f0; cursor: pointer; transition: background 0.2s; position: relative;"
                  :style="currentAutoConfig.is_auto_resolve_enabled ? 'background: #16a34a;' : ''"
                >
                  <div 
                    style="position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.2);"
                    :style="currentAutoConfig.is_auto_resolve_enabled ? 'transform: translateX(22px);' : ''"
                  ></div>
                </div>
              </div>
              <div v-if="currentAutoConfig.is_auto_resolve_enabled" style="display: flex; flex-direction: column; gap: 12px; margin-top: 12px; border-top: 1px solid #e2e8f0; padding-top: 12px;">
                <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 800; font-size: 13px; color: #334155;">
                  <span style="color: #64748b;">Phản hồi xử lý tự động:</span>
                  <input type="text" v-model="currentAutoConfig.reason" style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 500;" placeholder="Ví dụ: Hệ thống tự động giải quyết khiếu nại." />
                </label>
              </div>
            </div>
          </div>
          
          <div style="margin-top: 4px; padding: 10px 12px; background: #eff6ff; border-radius: 8px; font-size: 0.85rem; color: #1e40af; display: flex; align-items: flex-start; gap: 8px;">
            <AppIcon name="info" size="16" style="flex-shrink: 0; margin-top: 2px;" />
            <div>
              Khi tính năng <strong>Tự động xử lý khiếu nại</strong> được bật, các khiếu nại mới của đối tượng này khi được gửi lên sẽ được hệ thống tự động giải quyết và gửi phản hồi ngay lập tức.
            </div>
          </div>
        </template>

        <footer style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 8px;">
          <button type="button" class="btn secondary" @click="closeAutoResolveModal" style="border: 0; background: #f1f5f9; color: #334155; padding: 10px 14px; font-weight: 800; border-radius: 8px; cursor: pointer;">Hủy</button>
          <button type="button" class="btn primary" style="background: #10b981; color: white; border: 0; padding: 10px 14px; font-weight: 800; border-radius: 8px; cursor: pointer;" @click="saveAutoResolveConfig" :disabled="autoResolveSaving">Lưu cấu hình</button>
        </footer>
      </div>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ActionIconButton from '../../components/ActionIconButton.vue';
import { adminComplaintService } from '../../services/adminModeration.js';

export default {
  name: 'AdminComplaints',
  components: { AppIcon, ActionIconButton },
  data() {
    return {
      complaints: [],
      summary: {},
      staff: [],
      filters: { keyword: '', complaint_type: '', status: '', assigned_to: '', date_from: '', date_to: '' },
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
      showAutoResolveModal: false,
      autoResolveLoading: false,
      autoResolveSaving: false,
      autoResolveConfigData: null,
      activeAutoTab: 'venue',
    };
  },
  computed: {
    currentAutoConfig() {
      if (!this.autoResolveConfigData || !this.autoResolveConfigData.configs) {
        return null;
      }
      return this.autoResolveConfigData.configs[this.activeAutoTab];
    },
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
    async openAutoResolveModal() {
      this.showAutoResolveModal = true;
      this.autoResolveLoading = true;
      this.activeAutoTab = 'venue';
      try {
        const res = await adminComplaintService.getAutoResolveConfig();
        this.autoResolveConfigData = res.data;
      } catch (err) {
        this.error = 'Không thể tải cấu hình tự động xử lý khiếu nại.';
        this.showAutoResolveModal = false;
      } finally {
        this.autoResolveLoading = false;
      }
    },
    closeAutoResolveModal() {
      this.showAutoResolveModal = false;
      this.autoResolveConfigData = null;
    },
    async saveAutoResolveConfig() {
      this.autoResolveSaving = true;
      try {
        const payload = {
          configs: Object.values(this.autoResolveConfigData.configs),
        };
        await adminComplaintService.saveAutoResolveConfig(payload);
        this.success = 'Lưu cấu hình tự động xử lý khiếu nại thành công.';
        this.closeAutoResolveModal();
      } catch (err) {
        this.error = err.message || 'Lỗi khi lưu cấu hình.';
      } finally {
        this.autoResolveSaving = false;
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
    isTerminalStatus(value) {
      return ['resolved', 'rejected', 'closed'].includes(value);
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

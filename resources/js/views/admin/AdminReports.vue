<template>
  <section class="moderation-page">


    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

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
        <ActionIconButton icon="filter" label="Lọc danh sách" variant="primary" @click="loadReports" />
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
            <ActionIconButton icon="eye" label="Xem và xử lý" variant="primary" @click="openDetail(report)" />
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
          <ActionIconButton icon="x" label="Đóng" @click="closeDetail" />
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
              <button v-if="selected.status === 'resolved' || selected.status === 'dismissed'" class="btn secondary" type="button" :disabled="saving" @click="takeReview">
                Hủy thao tác & Xử lý lại
              </button>
              <label>
                Ghi chú xử lý
                <textarea v-model.trim="form.action_note" rows="6" placeholder="Nêu kết quả kiểm tra và căn cứ xử lý" :disabled="saving || selected.status === 'resolved' || selected.status === 'dismissed'"></textarea>
              </label>
              <div v-if="selected.status !== 'resolved' && selected.status !== 'dismissed'" class="modal-actions">
                <button class="btn secondary" type="button" :disabled="saving" @click="submitDecision('dismissed')">Từ chối</button>
                <button class="btn danger" type="button" :disabled="saving" @click="submitDecision('resolved')">Xác nhận</button>
              </div>
            </div>
          </aside>
        </div>
      </section>
    </div>

    <!-- Modal Cấu hình tự động xử lý báo cáo -->
    <div v-if="showAutoResolveModal" class="detail-backdrop" @click.self="closeAutoResolveModal">
      <div class="modal" style="max-width: 650px; background: #fff; border-radius: 12px; padding: 22px; display: grid; gap: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
        <h3 style="margin: 0;">Cấu hình tự động xử lý báo cáo</h3>
        <p class="muted" style="margin: 0; color: #64748b; font-size: 14px;">Thiết lập tự động ẩn nội dung hoặc khóa cụm sân khi đạt ngưỡng báo cáo vi phạm.</p>
        
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
            <!-- Thông tin chính sách (chỉ đọc) -->
            <div style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px;">
              <div style="font-weight: 700; color: #334155; margin-bottom: 10px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.3px;">Ngưỡng từ chính sách</div>
              <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="color: #64748b; font-size: 0.9rem;">Ngưỡng cảnh báo:</span>
                  <strong style="color: #d97706;">{{ currentAutoConfig.warning_threshold }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="color: #64748b; font-size: 0.9rem;">Ngưỡng thực hiện thao tác (Ẩn/Khóa):</span>
                  <strong style="color: #dc2626;">{{ currentAutoConfig.action_threshold }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="color: #64748b; font-size: 0.9rem;">Số người báo cáo khác nhau:</span>
                  <strong style="color: #2563eb;">{{ currentAutoConfig.unique_reporters_threshold }} người</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="color: #64748b; font-size: 0.9rem;">Thời gian theo dõi:</span>
                  <strong style="color: #334155;">{{ currentAutoConfig.window_days }} ngày</strong>
                </div>
              </div>
            </div>

            <!-- Cấu hình chỉnh sửa -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                <span style="color: #334155; font-size: 0.9rem; font-weight: 600;">Tự động xử lý vi phạm:</span>
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
                  <span style="color: #64748b;">Lý do xử lý tự động:</span>
                  <input type="text" v-model="currentAutoConfig.reason" style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 500;" placeholder="Ví dụ: Vi phạm tiêu chuẩn cộng đồng" />
                </label>
              </div>
            </div>
          </div>
          
          <div style="margin-top: 4px; padding: 10px 12px; background: #eff6ff; border-radius: 8px; font-size: 0.85rem; color: #1e40af; display: flex; align-items: flex-start; gap: 8px;">
            <AppIcon name="info" size="16" style="flex-shrink: 0; margin-top: 2px;" />
            <div>
              Khi số người báo cáo khác nhau đạt <strong>ngưỡng thực hiện thao tác</strong> và tự động xử lý đang bật, hệ thống sẽ tự động thực thi ẩn bài viết/bình luận hoặc khóa cụm sân.
            </div>
          </div>
          
          <div style="text-align: center; margin-top: 8px;">
            <router-link v-if="autoResolveConfigData.policy_id" :to="`/admin/policies/${autoResolveConfigData.policy_id}`" class="btn secondary" style="text-decoration: none; display: inline-block; font-size: 0.85rem; padding: 8px 12px; font-weight: 800; border-radius: 6px; background: #f1f5f9; color: #334155;">
              Chỉnh ngưỡng tại Chính sách hệ thống →
            </router-link>
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
import { adminReportService } from '../../services/adminModeration.js';

export default {
  name: 'AdminReports',
  components: { AppIcon, ActionIconButton },
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
      showAutoResolveModal: false,
      autoResolveLoading: false,
      autoResolveSaving: false,
      autoResolveConfigData: null,
      activeAutoTab: 'community_post',
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
    async openAutoResolveModal() {
      this.showAutoResolveModal = true;
      this.autoResolveLoading = true;
      this.activeAutoTab = 'community_post';
      try {
        const res = await adminReportService.getAutoResolveConfig();
        this.autoResolveConfigData = res.data;
      } catch (err) {
        this.error = 'Không thể tải cấu hình tự động xử lý báo cáo.';
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
        await adminReportService.saveAutoResolveConfig(payload);
        this.success = 'Lưu cấu hình tự động xử lý báo cáo thành công.';
        this.closeAutoResolveModal();
      } catch (err) {
        this.error = err.message || 'Lỗi khi lưu cấu hình.';
      } finally {
        this.autoResolveSaving = false;
      }
    },
  },
};
</script>

<style src="../../../css/admin/moderation.css" scoped></style>

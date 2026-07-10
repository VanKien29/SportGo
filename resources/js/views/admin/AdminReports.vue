<template>
  <div class="complaints-page">
    <div v-if="success" class="notice success">{{ success }}</div>
    <div v-if="error" class="notice error">{{ error }}</div>

    <div v-if="!detailOpen">
        <div class="filter-toolbar card" style="margin-bottom: 24px;">
          <!-- Tabs -->
          <div class="tabs-header">
            <button class="tab-btn" :class="{ active: filters.target_group === 'content' }" @click="setTargetGroup('content')">
              <AppIcon name="file-text" size="16" /> Báo cáo nội dung
            </button>
            <button class="tab-btn" :class="{ active: filters.target_group === 'user' }" @click="setTargetGroup('user')">
              <AppIcon name="user" size="16" /> Báo cáo người dùng
            </button>
            <button class="tab-btn" :class="{ active: filters.target_group === 'venue' }" @click="setTargetGroup('venue')">
              <AppIcon name="map-pin" size="16" /> Báo cáo cụm sân
            </button>
          </div>

          <!-- Filter and Search -->
          <div class="filters-row" style="display: flex; gap: 12px; align-items: center; padding: 16px;">
            <label class="field compact search-field" style="flex: 1;">
              <AppIcon name="search" size="16" />
              <input
                v-model.trim="filters.keyword"
                type="search"
                placeholder="Tìm người gửi, nội dung hoặc mã..."
                @keyup.enter="loadReports"
              />
            </label>
            <CustomSelect 
              v-if="filters.target_group === 'content'"
              v-model="filters.target_type" 
              :options="[{value: '', label: 'Tất cả đối tượng'}, ...filteredTargetTypes]" 
              @change="loadReports" 
            />
            <CustomSelect 
              v-model="filters.reason" 
              :options="[{value: '', label: 'Tất cả lý do'}, ...reasons]" 
              @change="loadReports" 
            />
            <CustomSelect 
              v-model="filters.status" 
              :options="[{value: '', label: 'Tất cả trạng thái'}, ...statuses]" 
              @change="loadReports" 
            />
            <input v-model="filters.date_from" type="date" aria-label="Từ ngày" @change="loadReports" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;" />
            <input v-model="filters.date_to" type="date" aria-label="Đến ngày" :min="filters.date_from || undefined" @change="loadReports" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px;" />
            <ActionIconButton icon="filter" label="Lọc" variant="primary" @click="loadReports" />
          </div>
        </div>

        <!-- Loading Screen -->
        <div v-if="loading" class="state-box card">
          <div class="spinner"></div>
          <p>Đang tải danh sách báo cáo...</p>
        </div>

        <!-- Empty Screen -->
        <div v-else-if="reports.length === 0" class="state-box card">
          <AppIcon name="fileText" size="36" />
          <p>Không tìm thấy báo cáo nào.</p>
        </div>

        <!-- Reports Table -->
        <div v-else class="table-container card">
          <div class="table-scroll">
            <table style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr style="text-align: left; border-bottom: 1px solid #e2e8f0;">
                  <th style="padding: 12px 16px;">Người báo cáo</th>
                  <th style="padding: 12px 16px;">Đối tượng bị báo cáo</th>
                  <th style="padding: 12px 16px;">Lý do / Nội dung</th>
                  <th style="padding: 12px 16px;">Trạng thái</th>
                  <th style="padding: 12px 16px;">Ngày tạo</th>
                  <th class="center" style="width: 120px; padding: 12px 16px; text-align: center;">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="report in reports" :key="report.id" class="complaint-row" style="border-bottom: 1px solid #f1f5f9;">
                  <td style="padding: 12px 16px;">
                    <div class="author-cell">
                      <strong>{{ report.reporter?.full_name || 'Khách hàng' }}</strong>
                      <div class="muted small" style="font-size: 12px; color: #64748b;">{{ report.reporter?.email || '' }}</div>
                    </div>
                  </td>
                  <td style="padding: 12px 16px;">
                    <div class="info-cell">
                      <div class="post-title" style="font-weight: 500;">
                        {{ report.target_label }}
                        <a v-if="getTargetUrl(report)" :href="getTargetUrl(report)" target="_blank" style="color: #2563eb; text-decoration: none; margin-left: 8px;">
                          <AppIcon name="external-link" size="12" />
                        </a>
                      </div>
                      <div class="complaint-type" style="margin-top: 4px;">
                        <span class="type-badge" style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12px;">{{ targetLabel(report.target_type) }}</span>
                      </div>
                    </div>
                  </td>
                  <td style="padding: 12px 16px;">
                    <div class="info-cell">
                      <div class="post-court" style="font-size: 13px; font-weight: 500; color: #dc2626;">
                        {{ reasonLabel(report.reason) }}
                      </div>
                      <div class="mt-1" style="font-size: 13px; color: #334155;">
                        {{ truncate(report.content, 50) }}
                      </div>
                    </div>
                  </td>
                  <td style="padding: 12px 16px;">
                    <span class="status-badge" :class="report.status" style="padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;" :style="report.status === 'resolved' ? 'background: #dcfce7; color: #166534;' : (report.status === 'processing' ? 'background: #dbeafe; color: #1e40af;' : 'background: #fef3c7; color: #92400e;')">
                      {{ statusLabel(report.status) }}
                    </span>
                  </td>
                  <td style="padding: 12px 16px; font-size: 13px;">
                    <span class="date-cell">{{ formatDateTime(report.created_at) }}</span>
                  </td>
                  <td class="center" style="padding: 12px 16px; text-align: center;">
                    <button @click="openDetail(report)" class="btn ghost btn-sm" style="padding: 6px 12px; border: 1px solid #e2e8f0; border-radius: 6px; background: white; cursor: pointer;">
                      Xem chi tiết
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
    </div>

    <!-- Detail View -->
    <div v-if="detailOpen" class="detail-view">
        <div v-if="detailLoading" class="state-box card">
          <div class="spinner"></div>
          <p>Đang tải chi tiết báo cáo...</p>
        </div>

        <template v-else-if="selected">
          <!-- Header -->
          <div class="detail-header card" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; margin-bottom: 24px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div class="header-main" style="display: flex; gap: 16px; align-items: center;">
              <button @click="closeDetail" class="btn ghost icon-only" style="background: none; border: none; cursor: pointer;">
                <AppIcon name="arrowLeft" size="24" />
              </button>
              <div>
                <h1 class="page-title" style="margin: 0; font-size: 20px; font-weight: 700;">Chi tiết báo cáo</h1>
                <p class="subtitle" style="margin: 0; color: #64748b; font-size: 14px;">
                  Mã báo cáo: <strong>{{ shortId(selected.id) }}</strong> ·
                  Tạo lúc: {{ formatDateTime(selected.created_at) }}
                </p>
              </div>
            </div>
            <span class="status-badge" :style="selected.status === 'resolved' ? 'background: #dcfce7; color: #166534; padding: 6px 12px; border-radius: 16px;' : (selected.status === 'processing' ? 'background: #dbeafe; color: #1e40af; padding: 6px 12px; border-radius: 16px;' : 'background: #fef3c7; color: #92400e; padding: 6px 12px; border-radius: 16px;')">
              {{ statusLabel(selected.status) }}
            </span>
          </div>

          <div class="detail-content" style="display: flex; gap: 24px; align-items: flex-start;">
            <!-- Sidebar: Info -->
            <div class="detail-sidebar" style="width: 320px; display: flex; flex-direction: column; gap: 16px;">
              <div class="card info-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <h3 style="margin-top: 0; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">Người gửi báo cáo</h3>
                <div class="info-row" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                  <span class="label" style="color: #64748b;">Họ tên:</span>
                  <span class="value" style="font-weight: 500;">{{ selected.reporter?.full_name || 'N/A' }}</span>
                </div>
                <div class="info-row" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                  <span class="label" style="color: #64748b;">SĐT:</span>
                  <span class="value" style="font-weight: 500;">{{ selected.reporter?.phone || 'N/A' }}</span>
                </div>
                <div class="info-row" style="display: flex; justify-content: space-between; font-size: 14px;">
                  <span class="label" style="color: #64748b;">Email:</span>
                  <span class="value" style="font-weight: 500;">{{ selected.reporter?.email || 'N/A' }}</span>
                </div>
              </div>
              
              <div class="card info-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                  <h3 style="margin-top: 0; font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">Phân công xử lý</h3>
                  <div style="display: flex; flex-direction: column; gap: 8px;">
                      <CustomSelect
                          v-model="form.assigned_to"
                          :options="[{value: '', label: 'Chưa phân công'}, ...staff.map(s => ({value: s.id, label: s.full_name}))]"
                      />
                      <button @click="assignReport" class="btn primary" style="padding: 8px; border: none; background: #3b82f6; color: white; border-radius: 6px; cursor: pointer; font-weight: 600;" :disabled="saving">Lưu phân công</button>
                  </div>
              </div>
            </div>

            <!-- Main Panel: Timeline & Form -->
            <div class="detail-main" style="flex: 1; display: flex; flex-direction: column; gap: 24px;">
              <!-- Original Report Content -->
              <div class="card complaint-box" style="background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <div class="complaint-head" style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9;">
                  <div class="avatar" style="width: 40px; height: 40px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                    <AppIcon name="flag" size="20" />
                  </div>
                  <div>
                    <div style="font-weight: 600; font-size: 15px;">{{ reasonLabel(selected.reason) }} <span style="color: #64748b; font-weight: normal;">(Báo cáo {{ targetLabel(selected.target_type) }})</span></div>
                    <div style="font-size: 13px; color: #94a3b8;">{{ formatDateTime(selected.created_at) }}</div>
                  </div>
                </div>
                <div class="complaint-body" style="font-size: 15px; line-height: 1.6; color: #334155; white-space: pre-wrap;">{{ selected.content }}</div>
                
                <div style="margin-top: 16px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 14px;">
                  <strong>Đối tượng:</strong> {{ selected.target_label }}
                  <a v-if="getTargetUrl(selected)" :href="getTargetUrl(selected)" target="_blank" style="color: #2563eb; text-decoration: none; margin-left: 8px; font-weight: 500;">
                    [Xem trực tiếp]
                  </a>
                </div>
              </div>

              <!-- Admin action form -->
              <div v-if="['open', 'processing'].includes(selected.status)" class="card reply-form" style="background: white; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <h3 style="margin-top: 0; font-size: 16px; margin-bottom: 16px;">Giải quyết báo cáo</h3>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                  <div>
                    <label style="display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px;">Ghi chú xử lý nội bộ</label>
                    <textarea v-model="form.resolve_note" placeholder="Nhập ghi chú giải quyết..." style="width: 100%; min-height: 100px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 14px; resize: vertical; box-sizing: border-box;"></textarea>
                  </div>
                  <div style="display: flex; gap: 12px; align-items: center;">
                    <CustomSelect 
                        v-model="form.decision" 
                        :options="[{value: 'take_down', label: 'Gỡ bỏ nội dung'}, {value: 'warn_user', label: 'Cảnh cáo người dùng'}, {value: 'ban_user', label: 'Khóa tài khoản'}, {value: 'ignore', label: 'Bỏ qua (báo cáo sai)'}]" 
                    />
                    <button @click="resolveReport" class="btn" style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;" :disabled="saving">Xác nhận xử lý</button>
                  </div>
                </div>
              </div>

              <!-- Timeline of Replies -->
              <div v-if="auditLogs && auditLogs.length > 0" class="timeline-section" style="margin-top: 16px;">
                <h3 style="font-size: 16px; margin-bottom: 20px; color: #334155;">Lịch sử xử lý</h3>
                <div class="timeline" style="display: flex; flex-direction: column; gap: 24px; position: relative;">
                  <div v-for="log in auditLogs" :key="log.id" class="timeline-item" style="display: flex; gap: 16px;">
                    <!-- log UI -->
                    <div class="timeline-icon" style="width: 36px; height: 36px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; z-index: 1;">
                      <AppIcon name="activity" size="16" style="color: #64748b;" />
                    </div>
                    <div class="timeline-content card" style="flex: 1; background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                      <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <strong style="font-size: 14px;">{{ log.user?.full_name || 'Hệ thống' }} <span style="font-weight: normal; color: #64748b;">đã cập nhật trạng thái</span></strong>
                        <span style="font-size: 12px; color: #94a3b8;">{{ formatDateTime(log.created_at) }}</span>
                      </div>
                      <div style="font-size: 13px; color: #64748b; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px dashed #cbd5e1;">
                        Hành động: {{ log.action }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ActionIconButton from '../../components/ActionIconButton.vue';
import CustomSelect from '../../components/CustomSelect.vue';
import { adminReportService } from '../../services/adminModeration.js';
import { adminUserService } from '../../services/adminUserService.js';

export default {
  name: 'AdminReports',
  components: { AppIcon, ActionIconButton, CustomSelect },
  data() {
    return {
      reports: [],
      summary: {},
      filters: { keyword: '', target_type: '', reason: '', status: '', date_from: '', date_to: '', target_group: 'content' },
      showLockUserModal: false,
      lockUserForm: {
        status_reason: 'Nhận quá nhiều báo cáo vi phạm',
        locked_until: '',
      },
      lockUserSaving: false,
      reportedUserViolation: null,
      userLockThreshold: 10,
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
      showScrollTop: false,
      previewImage: null,
      zoomState: {
          scale: 1,
          x: 0,
          y: 0,
          isDragging: false,
          startX: 0,
          startY: 0
      },
    };
  },
  computed: {
    filteredTargetTypes() {
      if (this.filters.target_group === 'content') {
        return this.targetTypes.filter(t => ['post', 'comment', 'venue_post', 'player_post'].includes(t.value));
      }
      return [];
    },
    currentAutoConfig() {
      if (!this.autoResolveConfigData || !this.autoResolveConfigData.configs) {
        return null;
      }
      return this.autoResolveConfigData.configs[this.activeAutoTab];
    },
  },
  async mounted() {
    this.loadReports();
    window.addEventListener('scroll', this.handleScroll);
    try {
      const policyResponse = await adminUserService.getLockPolicy();
      this.userLockThreshold = policyResponse.data?.lock_threshold || 10;
    } catch (e) {
      console.error(e);
    }
  },
  beforeUnmount() {
    window.removeEventListener('scroll', this.handleScroll);
  },
  methods: {
    truncate(text, length = 50) {
      if (!text) return '';
      return text.length > length ? text.substring(0, length) + '...' : text;
    },
    setTargetGroup(group) {
      this.filters.target_group = group;
      this.filters.target_type = ''; // Reset specific target type
      this.loadReports();
    },
    getTargetUrl(report) {
      if (!report || !report.target_id) return null;
      const id = report.target_id;
      const slug = report.target?.slug || id;
      
      switch (report.target_type) {
        case 'post':
        case 'venue_post':
        case 'player_post':
          return window.location.origin + '/community/' + slug;
        case 'comment':
          return window.location.origin + '/community/' + (report.target?.post?.slug || report.parent_id || slug);
        case 'user':
          return this.$router.resolve({ name: 'admin-user-detail', params: { id } }).href;
        case 'venue':
          return this.$router.resolve({ name: 'admin-venue-cluster-detail', params: { id } }).href;
        default:
          return null;
      }
    },
    handleScroll() {
      this.showScrollTop = window.scrollY > 250;
    },
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
      this.reportedUserViolation = null;
      try {
        const response = await adminReportService.show(report.id);
        this.selected = response.data.report;
        this.auditLogs = response.data.audit_logs || [];
        this.form = {
          action_taken: this.selected.action_taken || '',
          action_note: this.selected.action_note || '',
          lock_days: null,
        };
        
        if (this.selected.reported_user) {
          try {
            const vrResponse = await adminReportService.getViolationRecord('user', this.selected.reported_user.id);
            this.reportedUserViolation = vrResponse.data;
          } catch (e) {
            console.error('Lỗi khi lấy thông tin vi phạm:', e);
          }
        }
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
    openLockUserModal() {
      this.showLockUserModal = true;
      this.lockUserForm.status_reason = 'Nhận quá nhiều báo cáo vi phạm';
      this.lockUserForm.locked_until = '';
    },
    closeLockUserModal() {
      this.showLockUserModal = false;
    },
    async submitLockUser() {
      if (!this.selected?.reported_user?.id) return;
      this.lockUserSaving = true;
      try {
        const payload = {
          status_reason: this.lockUserForm.status_reason,
        };
        if (this.lockUserForm.locked_until) {
          const date = new Date(this.lockUserForm.locked_until);
          const isoString = date.toISOString().slice(0, 19).replace('T', ' ');
          payload.locked_until = isoString;
        }
        await adminUserService.lockUser(this.selected.reported_user.id, payload);
        this.success = 'Khóa tài khoản thành công!';
        this.closeLockUserModal();
      } catch (error) {
        this.error = error.message;
      } finally {
        this.lockUserSaving = false;
      }
    },
    isTerminalStatus(value) {
      return ['resolved', 'dismissed'].includes(value);
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
      if (!path) return '';
      if (path.startsWith('http') || path.startsWith('/storage')) return path;
      return `/storage/${path}`;
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
    openImagePreview(url) {
        this.previewImage = url;
        this.resetZoom();
    },
    closeImagePreview() {
        this.previewImage = null;
        this.resetZoom();
    },
    resetZoom() {
        this.zoomState = { scale: 1, x: 0, y: 0, isDragging: false, startX: 0, startY: 0 };
    },
    handleWheelZoom(e) {
        e.preventDefault();
        const zoomFactor = 0.15;
        const direction = e.deltaY < 0 ? 1 : -1;
        const newScale = Math.max(1, Math.min(this.zoomState.scale + direction * zoomFactor, 5));
        
        if (newScale === 1) {
            this.resetZoom();
            return;
        }

        const rect = e.target.getBoundingClientRect();
        const cursorX = e.clientX - rect.left;
        const cursorY = e.clientY - rect.top;
        
        const ratio = newScale / this.zoomState.scale;
        const diffX = cursorX * ratio - cursorX;
        const diffY = cursorY * ratio - cursorY;

        this.zoomState.x -= diffX;
        this.zoomState.y -= diffY;
        this.zoomState.scale = newScale;
    },
    startPan(e) {
        if (this.zoomState.scale <= 1) return;
        this.zoomState.isDragging = true;
        this.zoomState.startX = e.clientX - this.zoomState.x;
        this.zoomState.startY = e.clientY - this.zoomState.y;
    },
    doPan(e) {
        if (!this.zoomState.isDragging) return;
        this.zoomState.x = e.clientX - this.zoomState.startX;
        this.zoomState.y = e.clientY - this.zoomState.startY;
    },
    endPan() {
        this.zoomState.isDragging = false;
    },
  },
};
</script>

<style scoped>
.complaints-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.notice {
  padding: 12px 16px;
  border-radius: var(--admin-radius-md);
  font-size: 14px;
  font-weight: 500;
}
.notice.success {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}
.notice.error {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}

.filter-toolbar {
  display: flex;
  flex-direction: column;
}

.tabs-header {
  display: flex;
  gap: 8px;
  padding: 12px 16px;
}

.filters-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
  padding: 12px 16px;
  background: var(--admin-surface-muted);
  border-top: 1px solid var(--admin-border);
}

.field.compact {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
  font-size: 11px;
  font-weight: 700;
  color: var(--admin-faint);
  letter-spacing: 0.03em;
  text-transform: uppercase;
  white-space: nowrap;
}

.search-field {
  position: relative;
  width: 320px;
  max-width: 100%;
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 0 12px;
  height: 36px;
  gap: 8px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.search-field:focus-within {
  border-color: var(--admin-blue);
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}
.search-field input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 13px;
  font-weight: 500;
  color: var(--admin-text);
  padding: 0;
  height: 100%;
  text-transform: none;
}

.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  color: var(--admin-muted);
  gap: 16px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--admin-border);
  border-top-color: var(--admin-primary);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.table-container {
  overflow: hidden;
}

.table-scroll {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  min-width: 1000px;
}

th {
  background: var(--admin-surface-muted);
  padding: 12px 16px;
  font-size: 12px;
  font-weight: 600;
  color: var(--admin-faint);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  border-bottom: 1px solid var(--admin-border);
  white-space: nowrap;
}

td {
  padding: 16px;
  border-bottom: 1px solid var(--admin-border);
  vertical-align: top;
}

th.right, td.right {
  text-align: right;
}

th.center, td.center {
  text-align: center;
}

.complaint-row.never-hover-class-placeholder {
  background: var(--admin-surface-muted);
}

.author-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.author-cell strong {
  color: var(--admin-text);
  font-size: 14px;
}
.muted {
  color: var(--admin-muted);
}
.small {
  font-size: 12px;
}

.info-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.post-title {
  font-size: 14px;
  font-weight: 500;
  color: var(--admin-text);
  line-height: 1.4;
}

.type-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  background: var(--admin-surface-hover);
  color: var(--admin-text);
}

.post-court {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--admin-text);
}
.muted-icon {
  color: var(--admin-muted);
}

.booking-code {
  font-size: 12px;
  font-weight: 600;
  color: var(--admin-primary);
  background: rgba(59, 130, 246, 0.1);
  padding: 2px 8px;
  border-radius: 4px;
}

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}
.status-warning {
  background: rgba(245, 158, 11, 0.1);
  color: #d97706;
}
.status-info {
  background: rgba(59, 130, 246, 0.1);
  color: #2563eb;
}
.status-success {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}
.status-danger {
  background: rgba(239, 68, 68, 0.1);
  color: #dc2626;
}
.status-muted {
  background: var(--admin-surface-muted);
  color: var(--admin-muted);
}

.date-cell {
  font-size: 13px;
  color: var(--admin-muted);
}

.actions-cell {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  gap: 16px;
  border-top: 1px solid var(--admin-border);
}
.page-info {
  font-size: 14px;
  color: var(--admin-muted);
  font-weight: 500;
}
.mt-1 {
  margin-top: 4px;
}

@media (max-width: 768px) {
  .tabs-header {
    flex-wrap: nowrap;
    overflow-x: auto;
    white-space: nowrap;
    padding-bottom: 8px; /* Room for scrollbar */
  }
  .tab-btn {
    flex-shrink: 0;
  }
  .search-field {
    width: 100%;
    max-width: none;
  }
}
</style>
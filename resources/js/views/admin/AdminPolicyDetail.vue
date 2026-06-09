<template>
  <section class="admin-page">
    <button class="link-btn" type="button" @click="backToPolicies">
      ← Quay lại danh sách chính sách
    </button>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="loading" class="loading-card">Đang tải chi tiết chính sách...</div>

    <template v-if="!loading && policy">
      <header class="policy-header">
        <div>
          <p class="eyebrow">{{ getPolicyTypeLabel(policy.policy_type || policy.type) }}</p>
          <h2>{{ policy.title }}</h2>
          <p>{{ policy.business_summary }}</p>
          <div class="tag-row">
            <span class="badge" :class="getStatusBadgeClass(policy.status)">
              {{ getStatusLabel(policy.status) }}
            </span>
            <span class="badge badge-soft">Phiên bản {{ policy.version }}</span>
            <span v-if="policy.require_reaccept" class="badge badge-warning">Cần đồng ý lại</span>
            <span v-if="policy.is_overridable" class="badge badge-info">Cho sân chỉnh riêng</span>
          </div>
        </div>
        <div class="header-actions">
          <button class="btn secondary" type="button" @click="clonePolicy">Tạo phiên bản mới</button>
          <button v-if="policy.status !== 'active'" class="btn primary" type="button" @click="confirmPublishShow = true">
            Kích hoạt
          </button>
          <button v-if="policy.status === 'active'" class="btn danger" type="button" @click="confirmArchiveShow = true">
            Ngưng áp dụng
          </button>
        </div>
      </header>

      <nav class="tabs">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>

      <section v-if="activeTab === 'document'" class="panel">
        <div class="section-head with-actions">
          <div>
            <h3>Văn bản chính sách</h3>
            <p>Nội dung người dùng hoặc nhân sự vận hành cần đọc để hiểu quy định.</p>
          </div>
          <button class="btn secondary" type="button" @click="startEdit">
            {{ editingDocument ? 'Đóng chỉnh sửa' : 'Chỉnh sửa bản nháp' }}
          </button>
        </div>

        <article v-if="!editingDocument" class="document-view">
          <h1>{{ policy.title }}</h1>
          <div class="document-meta">
            <span>{{ getPolicyTypeLabel(policy.policy_type || policy.type) }}</span>
            <span>Phiên bản {{ policy.version }}</span>
            <span>{{ getStatusLabel(policy.status) }}</span>
          </div>
          <div class="document-content">{{ policy.content || 'Chưa có nội dung.' }}</div>
          <aside v-if="policy.change_summary">
            <strong>Tóm tắt thay đổi</strong>
            <p>{{ policy.change_summary }}</p>
          </aside>
          <details>
            <summary>Xem dữ liệu kỹ thuật</summary>
            <pre>{{ formatJson(policy) }}</pre>
          </details>
        </article>

        <form v-else class="detail-form" @submit.prevent="saveDocument">
          <div v-if="policy.status === 'active'" class="alert warning">
            Chính sách đang áp dụng. Nội dung quan trọng chỉ nên sửa bằng cách tạo phiên bản mới.
          </div>
          <div class="form-grid">
            <label>
              Mã chính sách
              <input v-model.trim="documentForm.key" :disabled="policy.status === 'active'" required />
            </label>
            <label>
              Phiên bản
              <input v-model.number="documentForm.version" type="number" min="1" :disabled="policy.status === 'active'" required />
            </label>
            <label>
              Loại chính sách
              <select v-model="documentForm.policy_type" :disabled="policy.status === 'active'" required>
                <option v-for="type in policyTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
              </select>
            </label>
            <label>
              Thứ tự ưu tiên
              <input v-model.number="documentForm.priority" type="number" min="0" />
            </label>
          </div>

          <label>
            Tiêu đề
            <input v-model.trim="documentForm.title" required />
          </label>
          <label>
            Nội dung chính sách
            <textarea v-model.trim="documentForm.content" rows="10" :disabled="policy.status === 'active'" required></textarea>
          </label>
          <label>
            Tóm tắt thay đổi
            <textarea v-model.trim="documentForm.change_summary" rows="3"></textarea>
          </label>
          <label class="check-row">
            <input v-model="documentForm.require_reaccept" type="checkbox" :disabled="policy.status === 'active'" />
            <span>Bắt buộc người dùng đồng ý lại</span>
          </label>
          <label class="check-row">
            <input v-model="documentForm.is_overridable" type="checkbox" :disabled="policy.status === 'active'" />
            <span>Cho sân cấu hình ghi đè nếu module hỗ trợ</span>
          </label>

          <div class="actions-right">
            <button class="btn secondary" type="button" @click="cancelEdit">Hủy</button>
            <button class="btn primary" type="submit">Lưu văn bản</button>
          </div>
        </form>
      </section>

      <section v-if="activeTab === 'actions'" class="panel">
        <div class="section-head">
          <h3>Thao tác áp dụng</h3>
          <p>Chính sách chỉ được gắn với các thao tác phù hợp loại chính sách hiện tại.</p>
        </div>

        <div class="split">
          <form class="side-form" @submit.prevent="saveBinding">
            <h4>Thêm thao tác</h4>
            <label>
              Thao tác
              <select v-model="bindingForm.action_code" required @change="applyActionCode">
                <option value="">Chọn thao tác phù hợp</option>
                <option v-for="item in filteredActionCodes" :key="item.action_code" :value="item.action_code">
                  {{ getActionLabel(item.action_code) }}
                </option>
              </select>
            </label>
            <label>
              Module
              <input v-model.trim="bindingForm.module" required />
            </label>
            <label>
              Mô tả
              <textarea v-model.trim="bindingForm.description" rows="3"></textarea>
            </label>
            <label class="check-row">
              <input v-model="bindingForm.is_active" type="checkbox" />
              <span>Đang bật</span>
            </label>
            <button class="btn primary" type="submit">Lưu thao tác</button>
          </form>

          <div class="card-list">
            <article v-if="bindings.length === 0" class="empty-card">Chưa gắn thao tác nào.</article>
            <article v-for="binding in bindings" :key="binding.id" class="simple-card">
              <header>
                <strong>{{ getActionLabel(binding.action_code) }}</strong>
                <span class="badge" :class="binding.is_active ? 'status-active' : 'status-inactive'">
                  {{ binding.is_active ? 'Đang bật' : 'Đã tắt' }}
                </span>
              </header>
              <p>{{ binding.description || 'Chưa có mô tả.' }}</p>
              <small>{{ getModuleMeta(binding.module).label }} · <code>{{ binding.action_code }}</code></small>
              <footer>
                <button
                  class="btn danger"
                  type="button"
                  :disabled="!binding.is_active"
                  @click="disableBinding(binding)"
                >
                  Tắt
                </button>
              </footer>
            </article>
          </div>
        </div>
      </section>

      <section v-if="activeTab === 'rules'" class="panel">
        <div class="section-head">
          <h3>Cấu hình xử lý tự động</h3>
          <p>
            Danh sách mẫu quy tắc đã được lọc theo loại chính sách
            {{ getPolicyTypeLabel(policy.policy_type || policy.type) }}.
          </p>
        </div>

        <div class="split">
          <form class="side-form" @submit.prevent="saveRule">
            <h4>{{ editingRuleId ? 'Sửa quy tắc' : 'Thêm quy tắc' }}</h4>
            <label>
              Mẫu quy tắc
              <select v-model="ruleTemplate" required @change="applyRuleTemplate">
                <option value="">Chọn mẫu phù hợp</option>
                <option v-for="template in filteredRuleTemplates" :key="template.rule_type" :value="template.rule_type">
                  {{ template.label || getRuleTypeLabel(template.rule_type) }}
                </option>
              </select>
            </label>
            <label>
              Tên quy tắc
              <input v-model.trim="ruleForm.rule_name" required />
            </label>
            <label>
              Thao tác áp dụng
              <select v-model="ruleForm.action_code" required>
                <option v-for="item in actionCodesForRule" :key="item.action_code" :value="item.action_code">
                  {{ getActionLabel(item.action_code) }}
                </option>
              </select>
            </label>

            <div v-if="isRefundRule" class="rule-fields">
              <label>
                Hủy trước ít nhất bao nhiêu giờ
                <input v-model.number="ruleInputs.hours_before_start" type="number" min="0" />
              </label>
              <label>
                Phần trăm hoàn tiền
                <input v-model.number="ruleInputs.refund_percent" type="number" min="0" max="100" />
              </label>
              <label class="check-row">
                <input v-model="ruleInputs.requires_owner_confirm" type="checkbox" />
                <span>Cần chủ sân xác nhận</span>
              </label>
              <label class="check-row">
                <input v-model="ruleInputs.requires_admin_confirm" type="checkbox" />
                <span>Cần admin xác nhận</span>
              </label>
            </div>

            <div v-else-if="isReportRule" class="rule-fields">
              <label>
                Số báo cáo tối thiểu
                <input v-model.number="ruleInputs.report_count" type="number" min="1" />
              </label>
              <label>
                Số người báo cáo khác nhau
                <input v-model.number="ruleInputs.unique_reporters" type="number" min="1" />
              </label>
              <label>
                Trong bao nhiêu ngày
                <input v-model.number="ruleInputs.window_days" type="number" min="1" />
              </label>
              <label>
                Hành động
                <select v-model="ruleInputs.action">
                  <option value="require_admin_review">Yêu cầu admin kiểm tra</option>
                  <option value="warning">Gửi cảnh báo</option>
                  <option value="temporary_lock">Khóa tạm thời</option>
                  <option value="permanent_lock">Khóa vĩnh viễn</option>
                </select>
              </label>
              <label v-if="ruleInputs.action === 'temporary_lock'">
                Số ngày khóa
                <input v-model.number="ruleInputs.lock_days" type="number" min="1" />
              </label>
            </div>

            <div v-else-if="ruleForm.rule_type === 'platform_fee_overdue'" class="rule-fields">
              <label>
                Quá hạn bao nhiêu ngày
                <input v-model.number="ruleInputs.overdue_days" type="number" min="1" />
              </label>
              <label>
                Hành động
                <select v-model="ruleInputs.action">
                  <option value="notify">Gửi thông báo nhắc phí</option>
                  <option value="lock_venue">Khóa cụm sân</option>
                </select>
              </label>
              <label>
                Lý do hiển thị
                <input v-model.trim="ruleInputs.reason" />
              </label>
            </div>

            <div v-else-if="ruleForm.rule_type === 'booking_auto_cancel'" class="rule-fields">
              <label>
                Tự hủy sau bao nhiêu phút
                <input v-model.number="ruleInputs.minutes_after_created" type="number" min="1" />
              </label>
            </div>

            <div v-else class="rule-fields readonly-rule">
              {{ defaultRuleSummary }}
            </div>

            <details>
              <summary>Dữ liệu kỹ thuật</summary>
              <label>
                Mã quy tắc
                <input v-model.trim="ruleForm.rule_code" />
              </label>
              <label>
                Thứ tự ưu tiên
                <input v-model.number="ruleForm.priority" type="number" min="0" />
              </label>
            </details>

            <label class="check-row">
              <input v-model="ruleForm.is_active" type="checkbox" />
              <span>Đang bật</span>
            </label>

            <div class="form-actions">
              <button v-if="editingRuleId" class="btn secondary" type="button" @click="resetRuleForm">Hủy sửa</button>
              <button class="btn primary" type="submit">{{ editingRuleId ? 'Lưu quy tắc' : 'Thêm quy tắc' }}</button>
            </div>
          </form>

          <div class="card-list">
            <article v-if="rules.length === 0" class="empty-card">Chưa có quy tắc xử lý tự động.</article>
            <article v-for="rule in rules" :key="rule.id" class="simple-card">
              <header>
                <strong>{{ rule.rule_name }}</strong>
                <span class="badge" :class="rule.is_active ? 'status-active' : 'status-inactive'">
                  {{ rule.is_active ? 'Đang bật' : 'Đã tắt' }}
                </span>
              </header>
              <p>{{ rule.business_summary || getRuleSummary(rule) }}</p>
              <small>{{ getRuleTypeLabel(rule.rule_type) }} · <code>{{ rule.rule_code }}</code></small>
              <footer>
                <button class="btn secondary" type="button" @click="editRule(rule)">Sửa</button>
                <button class="btn ghost" type="button" @click="toggleRule(rule)">
                  {{ rule.is_active ? 'Tắt' : 'Bật' }}
                </button>
              </footer>
              <details>
                <summary>Xem dữ liệu kỹ thuật</summary>
                <pre>{{ formatJson(rule) }}</pre>
              </details>
            </article>
          </div>
        </div>
      </section>

      <section v-if="activeTab === 'evaluations'" class="panel">
        <div class="section-head">
          <h3>Lịch sử đánh giá chính sách</h3>
          <p>Lịch sử hệ thống từng áp dụng quy tắc để đưa ra kết quả xử lý.</p>
        </div>
        <div v-if="evaluationLogs.length === 0" class="empty-card">Chưa có lịch sử đánh giá.</div>
        <div v-else class="timeline">
          <article v-for="log in evaluationLogs" :key="log.id" class="timeline-item">
            <div class="dot"></div>
            <div>
              <header>
                <strong>{{ safeDisplayText(log.human_result || log.human_message, getActionLabel(log.action_code)) }}</strong>
                <span>{{ formatDate(log.created_at) }}</span>
              </header>
              <small>{{ getActionLabel(log.action_code) }} · {{ log.entity_type || 'Không rõ đối tượng' }}</small>
              <details>
                <summary>Xem dữ liệu kỹ thuật</summary>
                <pre>{{ formatJson(log) }}</pre>
              </details>
            </div>
          </article>
        </div>
      </section>

      <section v-if="activeTab === 'audit'" class="panel">
        <div class="section-head">
          <h3>Lịch sử thay đổi</h3>
          <p>Các thao tác tạo, sửa, kích hoạt và thay đổi quy tắc chính sách.</p>
        </div>
        <div v-if="auditLogs.length === 0" class="empty-card">Chưa có lịch sử thay đổi.</div>
        <div v-else class="timeline">
          <article v-for="log in auditLogs" :key="log.id" class="timeline-item">
            <div class="dot"></div>
            <div>
              <header>
                <strong>{{ safeDisplayText(log.human_message, getAuditActionLabel(log.action)) }}</strong>
                <span>{{ formatDate(log.created_at) }}</span>
              </header>
              <div v-if="auditDiffs(log).length" class="diff-list">
                <div v-for="diff in auditDiffs(log)" :key="diff.field" class="diff-row">
                  <template v-if="diff.summary">
                    <strong>{{ diff.summary }}</strong>
                  </template>
                  <template v-else>
                    <strong>{{ diff.field_label || diff.fieldLabel }}</strong>
                    <span>{{ formatDisplayValue(diff.old || diff.oldLabel) }}</span>
                    <span>→</span>
                    <span>{{ formatDisplayValue(diff.new || diff.newLabel) }}</span>
                  </template>
                </div>
              </div>
              <details>
                <summary>Xem dữ liệu kỹ thuật</summary>
                <pre>{{ formatJson({ old: log.old_values, new: log.new_values }) }}</pre>
              </details>
            </div>
          </article>
        </div>
      </section>
    </template>

    <ConfirmModal
      v-model="confirmPublishShow"
      title="Kích hoạt chính sách"
      :message="`Bạn sắp kích hoạt chính sách ${policy?.title || ''}.`"
      consequence="Chính sách và các quy tắc đang bật sẽ được áp dụng trên hệ thống."
      confirm-text="Kích hoạt"
      type="warning"
      @confirm="publishPolicy"
    />

    <ConfirmModal
      v-model="confirmArchiveShow"
      title="Ngưng áp dụng chính sách"
      :message="`Bạn sắp ngưng áp dụng chính sách ${policy?.title || ''}.`"
      consequence="Các quy tắc tự động của chính sách này sẽ không còn hiệu lực."
      confirm-text="Ngưng áp dụng"
      type="danger"
      @confirm="archivePolicy"
    />
  </section>
</template>

<script>
import ConfirmModal from '../../components/ConfirmModal.vue';
import { adminPolicyService } from '../../services/adminPolicies.js';
import {
  buildAuditDiff,
  getActionLabel,
  getAuditActionLabel,
  getModuleMeta,
  getPolicyTypeLabel,
  getRuleSummary,
  getRuleTypeLabel,
  getStatusBadgeClass,
  getStatusLabel,
  POLICY_TYPE_LABELS,
} from '../../utils/labelMaps.js';

export default {
  name: 'AdminPolicyDetail',
  components: { ConfirmModal },
  data() {
    return {
      policy: null,
      bindings: [],
      rules: [],
      evaluationLogs: [],
      auditLogs: [],
      actionCodes: [],
      ruleTemplates: {},
      documentForm: {},
      bindingForm: this.defaultBindingForm(),
      ruleForm: this.defaultRuleForm(),
      ruleInputs: this.defaultRuleInputs(),
      ruleTemplate: '',
      editingDocument: false,
      editingRuleId: null,
      activeTab: this.$route.query.tab || 'document',
      loading: false,
      error: '',
      success: '',
      confirmPublishShow: false,
      confirmArchiveShow: false,
      policyTypes: Object.entries(POLICY_TYPE_LABELS).map(([value, label]) => ({ value, label })),
      tabs: [
        { key: 'document', label: 'Văn bản chính sách' },
        { key: 'actions', label: 'Thao tác áp dụng' },
        { key: 'rules', label: 'Cấu hình xử lý tự động' },
        { key: 'evaluations', label: 'Lịch sử đánh giá' },
        { key: 'audit', label: 'Lịch sử thay đổi' },
      ],
    };
  },
  computed: {
    filteredActionCodes() {
      const type = this.policy?.policy_type;
      return this.actionCodes.filter((item) => !type || item.policy_types?.includes(type) || type === 'general');
    },
    filteredRuleTemplates() {
      const type = this.policy?.policy_type;
      return Object.values(this.ruleTemplates).filter((item) => !type || item.policy_types?.includes(type) || type === 'general');
    },
    selectedTemplate() {
      return this.ruleTemplates[this.ruleTemplate] || null;
    },
    actionCodesForRule() {
      if (!this.selectedTemplate?.action_codes?.length) return this.filteredActionCodes;
      return this.filteredActionCodes.filter((item) => this.selectedTemplate.action_codes.includes(item.action_code));
    },
    isRefundRule() {
      return ['refund_by_cancel_time', 'refund_time_window'].includes(this.ruleForm.rule_type);
    },
    isReportRule() {
      return ['report_auto_lock', 'report_threshold'].includes(this.ruleForm.rule_type);
    },
    defaultRuleSummary() {
      if (this.ruleForm.rule_type === 'first_login_accept_required') {
        return 'Người dùng phải đồng ý phiên bản chính sách mới nhất trước khi tiếp tục sử dụng.';
      }
      if (this.ruleForm.rule_type === 'account_lock_manual') {
        return 'Admin phải nhập lý do khi khóa tài khoản thủ công.';
      }
      return 'Quy tắc này dùng cấu hình mặc định theo mẫu đã chọn.';
    },
  },
  mounted() {
    this.loadAll();
  },
  methods: {
    backToPolicies() {
      this.$router.push({
        name: this.$route.query.source === 'platform_fee'
          ? 'admin-platform-fee-policies'
          : 'admin-policies',
      });
    },
    getActionLabel,
    getAuditActionLabel,
    getModuleMeta,
    getPolicyTypeLabel,
    getRuleSummary,
    getRuleTypeLabel,
    getStatusBadgeClass,
    getStatusLabel,
    safeDisplayText(value, fallback = '') {
      if (!value) return fallback;
      return /[ĂÄÂÆ]|áº|á»|â€|â€™|â€œ|â€/.test(String(value)) ? fallback : value;
    },
    async loadAll() {
      this.loading = true;
      this.error = '';
      try {
        const [detail, actions, templates] = await Promise.all([
          adminPolicyService.show(this.$route.params.id),
          adminPolicyService.actionCodes(),
          adminPolicyService.ruleTemplates(),
        ]);

        const data = detail.data || {};
        this.policy = data.policy;
        this.bindings = data.action_bindings || [];
        this.rules = data.rules || [];
        this.evaluationLogs = data.evaluation_logs || [];
        this.auditLogs = data.audit_logs || [];
        this.actionCodes = actions.data || [];
        this.ruleTemplates = templates.data || {};
        this.documentForm = this.buildDocumentForm(this.policy);
      } catch (error) {
        this.error = this.safeDisplayText(error.message, 'Không tải được chi tiết chính sách.');
      } finally {
        this.loading = false;
      }
    },
    buildDocumentForm(policy) {
      return {
        key: policy.key,
        version: policy.version,
        title: policy.title,
        content: policy.content || '',
        policy_type: policy.policy_type || 'general',
        priority: policy.priority || 0,
        is_overridable: !!policy.is_overridable,
        require_reaccept: !!policy.require_reaccept,
        change_summary: policy.change_summary || '',
      };
    },
    defaultBindingForm() {
      return { module: '', action_code: '', description: '', is_active: true };
    },
    defaultRuleForm() {
      return {
        action_code: '',
        rule_code: '',
        rule_name: '',
        rule_type: '',
        decision_key: '',
        conflict_group: '',
        priority: 100,
        is_active: true,
      };
    },
    defaultRuleInputs() {
      return {
        hours_before_start: 24,
        refund_percent: 100,
        requires_owner_confirm: true,
        requires_admin_confirm: true,
        report_count: 10,
        unique_reporters: 3,
        window_days: 30,
        action: 'require_admin_review',
        lock_days: 7,
        overdue_days: 1,
        reason: 'Quá hạn phí duy trì nền tảng',
        minutes_after_created: 20,
      };
    },
    startEdit() {
      this.documentForm = this.buildDocumentForm(this.policy);
      this.editingDocument = !this.editingDocument;
    },
    cancelEdit() {
      this.documentForm = this.buildDocumentForm(this.policy);
      this.editingDocument = false;
    },
    async saveDocument() {
      await this.runAction(
        () => adminPolicyService.update(this.policy.id, this.documentForm),
        'Đã lưu văn bản chính sách.',
      );
      this.editingDocument = false;
    },
    applyActionCode() {
      const selected = this.actionCodes.find((item) => item.action_code === this.bindingForm.action_code);
      if (!selected) return;
      this.bindingForm.module = selected.module;
      this.bindingForm.description = selected.description || '';
    },
    async saveBinding() {
      await this.runAction(
        () => adminPolicyService.addBinding(this.policy.id, this.bindingForm),
        'Đã lưu thao tác áp dụng.',
      );
      this.bindingForm = this.defaultBindingForm();
    },
    async disableBinding(binding) {
      await this.runAction(
        () => adminPolicyService.disableBinding(this.policy.id, binding.id),
        'Đã tắt thao tác áp dụng.',
      );
    },
    applyRuleTemplate() {
      const template = this.ruleTemplates[this.ruleTemplate];
      if (!template) return;

      this.ruleForm = {
        ...this.defaultRuleForm(),
        rule_type: template.rule_type || this.ruleTemplate,
        rule_name: template.label || '',
        rule_code: `${template.rule_type || this.ruleTemplate}_${Date.now()}`,
        action_code: template.action_codes?.[0] || this.filteredActionCodes[0]?.action_code || '',
        decision_key: template.decision_key || '',
        conflict_group: template.conflict_group || '',
        priority: 100,
        is_active: true,
      };
      this.ruleInputs = this.inputsFromRule({ condition_json: template.condition_json, result_json: template.result_json });
    },
    editRule(rule) {
      this.editingRuleId = rule.id;
      this.ruleTemplate = rule.rule_type;
      this.ruleForm = {
        action_code: rule.action_code,
        rule_code: rule.rule_code,
        rule_name: rule.rule_name,
        rule_type: rule.rule_type,
        decision_key: rule.decision_key || '',
        conflict_group: rule.conflict_group || '',
        priority: rule.priority || 100,
        is_active: !!rule.is_active,
      };
      this.ruleInputs = this.inputsFromRule(rule);
      this.activeTab = 'rules';
    },
    inputsFromRule(rule) {
      const condition = rule.condition_json || {};
      const result = rule.result_json || {};
      return {
        ...this.defaultRuleInputs(),
        hours_before_start: condition.hours_before_start?.gte ?? condition.hours_before_start ?? 24,
        refund_percent: result.refund_percent ?? 100,
        requires_owner_confirm: result.requires_owner_confirm ?? true,
        requires_admin_confirm: result.requires_admin_confirm ?? true,
        report_count: condition.report_count?.gte ?? condition.report_count ?? 10,
        unique_reporters: condition.unique_reporters?.gte ?? condition.unique_reporters ?? 3,
        window_days: condition.window_days ?? 30,
        action: result.action || 'require_admin_review',
        lock_days: result.lock_days || 7,
        overdue_days: condition.overdue_days?.gte ?? condition.overdue_days ?? 1,
        reason: result.reason || 'Quá hạn phí duy trì nền tảng',
        minutes_after_created: condition.minutes_after_created?.gte ?? condition.minutes_after_created ?? 20,
      };
    },
    buildRulePayload() {
      const template = this.selectedTemplate || {};
      const type = this.ruleForm.rule_type;
      const payload = {
        ...this.ruleForm,
        rule_code: this.ruleForm.rule_code || `${type}_${Date.now()}`,
        decision_key: this.ruleForm.decision_key || template.decision_key || null,
        conflict_group: this.ruleForm.conflict_group || template.conflict_group || null,
        condition_json: template.condition_json || {},
        result_json: template.result_json || {},
        constraint_json: null,
        allowed_override_json: null,
      };

      if (['refund_by_cancel_time', 'refund_time_window'].includes(type)) {
        payload.condition_json = { hours_before_start: { gte: Number(this.ruleInputs.hours_before_start || 0) } };
        payload.result_json = {
          refund_percent: Number(this.ruleInputs.refund_percent || 0),
          requires_owner_confirm: !!this.ruleInputs.requires_owner_confirm,
          requires_admin_confirm: !!this.ruleInputs.requires_admin_confirm,
        };
      } else if (['report_auto_lock', 'report_threshold'].includes(type)) {
        payload.condition_json = {
          report_count: { gte: Number(this.ruleInputs.report_count || 1) },
          unique_reporters: { gte: Number(this.ruleInputs.unique_reporters || 1) },
          window_days: Number(this.ruleInputs.window_days || 1),
        };
        payload.result_json = { action: this.ruleInputs.action };
        if (this.ruleInputs.action === 'temporary_lock') {
          payload.result_json.lock_days = Number(this.ruleInputs.lock_days || 1);
        }
      } else if (type === 'platform_fee_overdue') {
        payload.condition_json = { overdue_days: { gte: Number(this.ruleInputs.overdue_days || 1) } };
        payload.result_json = { action: this.ruleInputs.action, reason: this.ruleInputs.reason };
      } else if (type === 'booking_auto_cancel') {
        payload.condition_json = { minutes_after_created: { gte: Number(this.ruleInputs.minutes_after_created || 1) } };
        payload.result_json = { action: 'cancel_booking' };
      }

      return payload;
    },
    async saveRule() {
      const payload = this.buildRulePayload();
      const action = this.editingRuleId
        ? () => adminPolicyService.updateRule(this.policy.id, this.editingRuleId, payload)
        : () => adminPolicyService.addRule(this.policy.id, payload);

      await this.runAction(action, this.editingRuleId ? 'Đã cập nhật quy tắc.' : 'Đã thêm quy tắc.');
      this.resetRuleForm();
    },
    resetRuleForm() {
      this.editingRuleId = null;
      this.ruleTemplate = '';
      this.ruleForm = this.defaultRuleForm();
      this.ruleInputs = this.defaultRuleInputs();
    },
    async toggleRule(rule) {
      await this.runAction(
        () => adminPolicyService.toggleRule(this.policy.id, rule.id),
        rule.is_active ? 'Đã tắt quy tắc.' : 'Đã bật quy tắc.',
      );
    },
    async clonePolicy() {
      await this.runAction(() => adminPolicyService.cloneVersion(this.policy.id), 'Đã tạo phiên bản mới.');
    },
    async publishPolicy() {
      await this.runAction(() => adminPolicyService.publish(this.policy.id), 'Đã kích hoạt chính sách.');
    },
    async archivePolicy() {
      await this.runAction(() => adminPolicyService.updateStatus(this.policy.id, { status: 'archived' }), 'Đã ngưng áp dụng chính sách.');
    },
    async runAction(action, fallbackMessage) {
      this.error = '';
      this.success = '';
      try {
        const response = await action();
        this.success = this.safeDisplayText(response.message, fallbackMessage);
        await this.loadAll();
        setTimeout(() => { this.success = ''; }, 3500);
      } catch (error) {
        this.error = this.safeDisplayText(error.message, 'Thao tác không thành công.');
      }
    },
    auditDiffs(log) {
      return log.changes_summary?.length ? log.changes_summary : buildAuditDiff(log.old_values, log.new_values);
    },
    formatJson(value) {
      return JSON.stringify(value, null, 2);
    },
    formatDisplayValue(value) {
      if (value === null || value === undefined || value === '') return '(trống)';
      if (typeof value === 'boolean') return value ? 'Có' : 'Không';
      if (Array.isArray(value) || typeof value === 'object') return 'Dữ liệu kỹ thuật đã thay đổi';
      return String(value);
    },
    formatDate(value) {
      if (!value) return '-';
      return new Date(value).toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
  },
};
</script>

<style scoped>
.admin-page {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.link-btn {
  align-self: flex-start;
  border: 0;
  background: transparent;
  color: #16a34a;
  font-weight: 800;
  cursor: pointer;
}

.policy-header,
.panel,
.loading-card,
.empty-card,
.simple-card,
.side-form {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
}

.policy-header {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  padding: 22px;
}

.eyebrow {
  margin: 0 0 5px;
  color: #16a34a;
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
}

h2,
h3,
h4,
p {
  margin: 0;
}

h2 {
  color: #0f172a;
  font-size: 24px;
}

.policy-header p:not(.eyebrow),
.section-head p,
.simple-card p,
.document-meta,
small {
  color: #64748b;
  line-height: 1.5;
}

.tag-row,
.header-actions,
.simple-card footer,
.form-actions,
.actions-right {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.tag-row {
  margin-top: 14px;
}

.header-actions {
  align-self: flex-start;
}

.tabs {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid #e2e8f0;
  overflow-x: auto;
}

.tabs button {
  border: 0;
  border-bottom: 3px solid transparent;
  background: transparent;
  padding: 11px 14px;
  color: #64748b;
  font-weight: 800;
  cursor: pointer;
  white-space: nowrap;
}

.tabs button.active {
  border-color: #16a34a;
  color: #16a34a;
}

.panel {
  padding: 22px;
}

.section-head {
  margin-bottom: 18px;
}

.with-actions {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  align-items: flex-start;
}

.document-view {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.document-view h1 {
  margin: 0;
  color: #0f172a;
  font-size: 28px;
}

.document-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  font-size: 13px;
}

.document-content {
  min-height: 160px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 20px;
  background: #f8fafc;
  color: #0f172a;
  line-height: 1.8;
  white-space: pre-line;
}

aside {
  border-left: 4px solid #16a34a;
  padding: 12px 14px;
  background: #f0fdf4;
  color: #166534;
}

.detail-form,
.side-form,
.card-list,
.rule-fields,
.timeline {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.split {
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 18px;
  align-items: start;
}

.side-form {
  position: sticky;
  top: 16px;
  padding: 18px;
}

label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: #334155;
  font-weight: 800;
}

input,
select,
textarea {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 10px 12px;
  color: #0f172a;
  font: inherit;
  background: #fff;
}

input:focus,
select:focus,
textarea:focus {
  outline: none;
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
}

input:disabled,
select:disabled,
textarea:disabled {
  background: #f8fafc;
  color: #94a3b8;
}

.check-row {
  flex-direction: row;
  align-items: center;
}

.check-row input {
  width: auto;
}

.actions-right {
  justify-content: flex-end;
}

.simple-card {
  padding: 16px;
}

.simple-card header {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: flex-start;
}

.simple-card p {
  margin: 8px 0;
}

.readonly-rule {
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  padding: 12px;
  color: #475569;
  background: #f8fafc;
}

.badge {
  border-radius: 999px;
  padding: 4px 9px;
  font-size: 12px;
  font-weight: 800;
}

.status-active {
  background: #dcfce7;
  color: #166534;
}

.status-draft,
.badge-info {
  background: #e0f2fe;
  color: #075985;
}

.status-inactive,
.badge-warning {
  background: #fef3c7;
  color: #92400e;
}

.status-archived,
.status-default,
.badge-soft {
  background: #f1f5f9;
  color: #475569;
}

.alert {
  border-radius: 8px;
  padding: 11px 13px;
  font-weight: 700;
}

.alert.error {
  background: #fef2f2;
  color: #991b1b;
}

.alert.success {
  background: #ecfdf5;
  color: #047857;
}

.alert.warning {
  background: #fffbeb;
  color: #92400e;
}

.loading-card,
.empty-card {
  padding: 28px;
  color: #64748b;
  text-align: center;
}

.btn {
  border: 0;
  border-radius: 8px;
  padding: 9px 13px;
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.btn.primary {
  background: #16a34a;
  color: #fff;
}

.btn.secondary {
  background: #e2e8f0;
  color: #334155;
}

.btn.ghost {
  background: #f8fafc;
  color: #334155;
}

.btn.danger {
  background: #fee2e2;
  color: #991b1b;
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

details {
  margin-top: 8px;
  color: #64748b;
}

summary {
  cursor: pointer;
  font-weight: 800;
}

pre {
  max-height: 280px;
  overflow: auto;
  border-radius: 8px;
  background: #0f172a;
  color: #e2e8f0;
  padding: 12px;
  font-size: 12px;
}

code {
  border-radius: 4px;
  background: #f1f5f9;
  padding: 1px 5px;
}

.timeline-item {
  display: grid;
  grid-template-columns: 14px 1fr;
  gap: 12px;
}

.dot {
  width: 12px;
  height: 12px;
  margin-top: 5px;
  border-radius: 999px;
  background: #16a34a;
}

.timeline-item header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  color: #0f172a;
}

.timeline-item header span {
  color: #64748b;
  font-size: 13px;
}

.diff-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 8px;
}

.diff-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  color: #475569;
  font-size: 13px;
}

@media (max-width: 1020px) {
  .split {
    grid-template-columns: 1fr;
  }

  .side-form {
    position: static;
  }
}

@media (max-width: 760px) {
  .policy-header,
  .with-actions,
  .timeline-item header {
    flex-direction: column;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>

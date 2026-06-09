<template>
  <section class="admin-page">
    <div v-if="loading" class="state-card">
      <span class="spinner"></span>
      Đang tải chi tiết chính sách...
    </div>

    <template v-else-if="!policy">
      <div class="alert error">{{ error || 'Không tìm thấy chính sách.' }}</div>
      <button class="btn secondary" type="button" @click="$router.push({ name: 'admin-policies' })">
        <AppIcon name="arrowLeft" size="16" />
        Quay lại danh sách
      </button>
    </template>

    <template v-else>
      <header class="page-head">
        <div>
          <button class="back-link" type="button" @click="$router.push({ name: 'admin-policies' })">
            <AppIcon name="arrowLeft" size="16" />
            Danh sách chính sách
          </button>
          <div class="title-row">
            <h2>{{ policy.title }}</h2>
            <span class="badge" :class="statusTone(policy.status)">{{ policy.status_label || getStatusLabel(policy.status) }}</span>
            <span class="badge muted">v{{ policy.version || 1 }}</span>
          </div>
          <p>{{ policy.policy_type_label || getPolicyTypeLabel(policy.policy_type) }} · {{ policy.business_summary_vi || 'Chính sách hệ thống SportGo.' }}</p>
        </div>
        <div class="page-actions">
          <button v-if="policy.status === 'draft'" class="btn danger-ghost" type="button" :disabled="saving" @click="confirmDelete = true">
            <AppIcon name="trash" size="16" />
            Xóa nháp
          </button>
          <button class="btn secondary" type="button" :disabled="saving" @click="clonePolicy">
            <AppIcon name="copy" size="16" />
            Tạo phiên bản mới
          </button>
          <button v-if="policy.status !== 'active'" class="btn primary" type="button" :disabled="saving" @click="publishPolicy">
            <AppIcon name="check" size="16" />
            Áp dụng ngay
          </button>
          <button v-else class="btn danger-ghost" type="button" :disabled="saving" @click="archivePolicy">
            <AppIcon name="power" size="16" />
            Ngưng áp dụng
          </button>
        </div>
      </header>

      <div v-if="success" class="alert success">{{ success }}</div>
      <div v-if="error" class="alert error">{{ error }}</div>

      <nav class="tabs" aria-label="Tab chính sách">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          <AppIcon :name="tab.icon" size="15" />
          {{ tab.label }}
          <span v-if="tab.count !== undefined" class="tab-count">{{ tab.count }}</span>
        </button>
      </nav>

      <section v-if="activeTab === 'overview'" class="panel">
        <h3>Tổng quan</h3>
        <div class="info-grid">
          <InfoItem label="Tên chính sách" :value="policy.title" />
          <InfoItem label="Nhóm chính sách" :value="policy.policy_type_label || getPolicyTypeLabel(policy.policy_type)" />
          <InfoItem label="Phiên bản" :value="`v${policy.version || 1}`" />
          <InfoItem label="Trạng thái" :value="policy.status_label || getStatusLabel(policy.status)" />
          <InfoItem label="Hiệu lực từ" :value="formatDate(policy.effective_from || policy.published_at)" />
          <InfoItem label="Yêu cầu chấp nhận lại" :value="policy.require_reaccept ? 'Có' : 'Không'" />
          <InfoItem label="Cho sân cấu hình riêng" :value="policy.is_overridable ? 'Có, trong khung hệ thống' : 'Không'" />
          <InfoItem label="Số quy tắc" :value="`${rules.length} quy tắc`" />
          <InfoItem label="Người tạo" :value="policy.created_by_name" />
          <InfoItem label="Người cập nhật" :value="policy.updated_by_name" />
          <InfoItem label="Người áp dụng" :value="policy.published_by_name" />
          <InfoItem label="Cập nhật lần cuối" :value="formatDateTime(policy.updated_at)" />
        </div>
        <div class="business-box">
          <strong>Tóm tắt nghiệp vụ</strong>
          <p>{{ policy.business_summary_vi || policy.business_summary || 'Chưa có tóm tắt nghiệp vụ.' }}</p>
        </div>
        <details class="technical-box">
          <summary>Xem dữ liệu kỹ thuật</summary>
          <pre>{{ formatJson({ key: policy.key, type: policy.type, policy_type: policy.policy_type, id: policy.id }) }}</pre>
        </details>
      </section>

      <section v-if="activeTab === 'content'" class="panel">
        <div class="section-head">
          <div>
            <h3>Nội dung chính sách</h3>
            <p>Đây là văn bản để người dùng, chủ sân hoặc admin đọc. Không hiển thị JSON ở đây.</p>
          </div>
          <button v-if="policy.can_edit_content" class="btn primary" type="button" :disabled="savingContent" @click="saveContent">
            <AppIcon name="check" size="15" />
            {{ savingContent ? 'Đang lưu...' : 'Lưu nội dung' }}
          </button>
        </div>
        <div v-if="!policy.can_edit_content" class="notice warning">
          Chính sách đang áp dụng. Hãy tạo phiên bản mới để chỉnh sửa nội dung quan trọng.
        </div>
        <textarea
          v-model="contentDraft"
          class="content-textarea"
          rows="16"
          :readonly="!policy.can_edit_content"
          placeholder="Nhập nội dung chính sách..."
        />
      </section>

      <section v-if="activeTab === 'rules'" class="panel">
        <div class="section-head">
          <div>
            <h3>Quy tắc xử lý tự động</h3>
            <p>Rule là phần hệ thống áp dụng tự động theo tình huống nghiệp vụ.</p>
          </div>
          <button class="btn primary" type="button" :disabled="policy.status === 'active'" @click="openRuleWizard">
            <AppIcon name="plus" size="15" />
            Thêm quy tắc
          </button>
        </div>

        <div v-if="policy.status === 'active'" class="notice warning">
          Chính sách đang áp dụng. Muốn thêm hoặc sửa quy tắc, hãy tạo phiên bản mới trước.
        </div>

        <div v-if="rules.length === 0" class="empty-state">
          Chưa có quy tắc nào cho chính sách này.
        </div>

        <div class="rule-table">
          <table v-if="rules.length">
            <thead>
              <tr>
                <th>Tên rule</th>
                <th>Tình huống áp dụng</th>
                <th>Điều kiện chính</th>
                <th>Kết quả khi áp dụng</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="rule in rules" :key="rule.id">
                <td>
                  <strong>{{ rule.rule_label_vi || rule.rule_type_label || rule.rule_name }}</strong>
                  <span>{{ rule.business_summary_vi || getRuleSummary(rule) }}</span>
                </td>
                <td>{{ rule.action_label_vi || getActionLabel(rule.action_code) }}</td>
                <td>{{ rule.condition_summary_vi || 'Không có điều kiện đặc biệt.' }}</td>
                <td>{{ rule.result_summary_vi || 'Không có kết quả mô tả.' }}</td>
                <td>
                  <span class="badge" :class="rule.is_active ? 'success' : 'muted'">{{ rule.is_active ? 'Đang bật' : 'Đang tắt' }}</span>
                </td>
                <td class="actions">
                  <button class="icon-btn" type="button" title="Xem chi tiết rule" @click="openRuleDetail(rule)">
                    <AppIcon name="eye" size="16" />
                  </button>
                  <button
                    class="icon-btn"
                    type="button"
                    :disabled="policy.status === 'active'"
                    :title="rule.is_active ? 'Tắt quy tắc' : 'Bật quy tắc'"
                    @click="toggleRule(rule)"
                  >
                    <AppIcon :name="rule.is_active ? 'power' : 'circleCheck'" size="16" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="activeTab === 'venue'" class="panel">
        <h3>Chính sách sân áp dụng riêng</h3>
        <p class="muted">Chỉ hiển thị các rule sân đang dựa trên chính sách này. Rule bị từ chối phải có lý do rõ.</p>
        <div class="list-box">
          <article v-for="item in venueRules" :key="item.id">
            <strong>{{ item.venue_cluster?.name || item.venue_cluster_name || 'Cụm sân' }}</strong>
            <span>{{ item.rule_name || item.rule_code || 'Rule sân' }} · {{ getStatusLabel(item.status) }}</span>
            <span v-if="item.reject_reason || item.status_reason">Lý do: {{ item.reject_reason || item.status_reason }}</span>
            <details>
              <summary>Xem dữ liệu kỹ thuật</summary>
              <pre>{{ formatJson(item) }}</pre>
            </details>
          </article>
          <p v-if="venueRules.length === 0" class="muted">Chưa có chính sách sân áp dụng riêng.</p>
        </div>
      </section>

      <section v-if="activeTab === 'evaluation'" class="panel">
        <h3>Lịch sử áp dụng</h3>
        <div class="list-box">
          <article v-for="log in evaluationLogs" :key="log.id">
            <strong>{{ log.human_result || log.action_label || 'Rule đã được kiểm tra' }}</strong>
            <span>{{ log.human_message || 'Hệ thống đã áp dụng rule theo chính sách.' }}</span>
            <small>{{ formatDateTime(log.created_at) }}</small>
            <details>
              <summary>Xem dữ liệu kỹ thuật</summary>
              <pre>{{ formatJson(log) }}</pre>
            </details>
          </article>
          <p v-if="evaluationLogs.length === 0" class="muted">Chưa có log áp dụng chính sách.</p>
        </div>
      </section>

      <section v-if="activeTab === 'audit'" class="panel">
        <h3>Lịch sử thay đổi</h3>
        <div class="list-box">
          <article v-for="log in auditLogs" :key="log.id">
            <strong>{{ log.human_message || 'Đã cập nhật chính sách' }}</strong>
            <span>{{ log.actor_name || 'Hệ thống' }} · {{ formatDateTime(log.created_at) }}</span>
            <ul v-if="(log.changes_summary || []).length" class="change-list">
              <li v-for="item in log.changes_summary" :key="`${log.id}-${item.field || item.summary}`">
                {{ item.summary || item }}
              </li>
            </ul>
            <details>
              <summary>Xem dữ liệu kỹ thuật</summary>
              <pre>{{ formatJson({ old: log.technical_old_values, new: log.technical_new_values }) }}</pre>
            </details>
          </article>
          <p v-if="auditLogs.length === 0" class="muted">Chưa có lịch sử thay đổi.</p>
        </div>
      </section>
    </template>

    <div v-if="showWizard" class="modal-bg" @click.self="closeWizard">
      <div class="modal-box">
        <header class="modal-head">
          <div>
            <p class="eyebrow">Chính sách: {{ policy?.title }}</p>
            <h3>Thêm quy tắc xử lý tự động</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeWizard">
            <AppIcon name="x" size="18" />
          </button>
        </header>

        <div class="step-bar">
          <span :class="{ active: wizardStep === 1 }">1. Tình huống</span>
          <span :class="{ active: wizardStep === 2 }">2. Điều kiện</span>
          <span :class="{ active: wizardStep === 3 }">3. Xem trước</span>
        </div>

        <section v-if="wizardStep === 1" class="wizard-body">
          <h4>Chọn tình huống áp dụng</h4>
          <p class="muted">Danh sách đã được lọc theo nhóm chính sách: {{ policy?.policy_type_label || getPolicyTypeLabel(policyType) }}.</p>
          <div v-if="loadingOptions" class="state-card">
            <span class="spinner"></span>
            Đang tải tình huống...
          </div>
          <div v-else-if="filteredActions.length === 0" class="empty-state">
            Chưa có tình huống phù hợp cho nhóm chính sách này. Vui lòng kiểm tra seeder policy_action_bindings/rule_templates.
          </div>
          <div v-else class="option-list">
            <label v-for="action in filteredActions" :key="action.action_code" class="option-card" :class="{ selected: ruleForm.action_code === action.action_code }">
              <input v-model="ruleForm.action_code" type="radio" :value="action.action_code" />
              <span>
                <strong>{{ action.label || action.action_label_vi || getActionLabel(action.action_code) }}</strong>
                <small>Tình huống nghiệp vụ mà rule sẽ chạy.</small>
              </span>
            </label>
          </div>
        </section>

        <section v-if="wizardStep === 2" class="wizard-body">
          <h4>Nhập điều kiện</h4>
          <p class="context-line">Tình huống: <strong>{{ getActionLabel(ruleForm.action_code) }}</strong></p>

          <label>
            Loại quy tắc
            <select v-model="ruleForm.rule_type" @change="syncRuleName">
              <option value="">Chọn loại quy tắc phù hợp</option>
              <option v-for="template in filteredTemplates" :key="template.rule_type" :value="template.rule_type">
                {{ template.label || template.rule_type_label || getRuleTypeLabel(template.rule_type) }}
              </option>
            </select>
          </label>

          <div v-if="ruleForm.rule_type && filteredTemplates.length === 0" class="notice warning">
            Chưa có mẫu quy tắc phù hợp cho tình huống này.
          </div>

          <div v-if="ruleForm.rule_type" class="param-grid">
            <label v-if="needsField('hours_before_start')">
              Hủy trước giờ chơi tối thiểu
              <div class="input-unit">
                <input v-model.number="formNumbers.hours_before_start" type="number" min="1" />
                <span>giờ</span>
              </div>
            </label>
            <label v-if="needsField('refund_percent')">
              Phần trăm hoàn tiền
              <div class="input-unit">
                <input v-model.number="formNumbers.refund_percent" type="number" min="0" max="100" />
                <span>%</span>
              </div>
            </label>
            <label v-if="needsField('days_before_due')">
              Nhắc trước hạn
              <div class="input-unit">
                <input v-model.number="formNumbers.days_before_due" type="number" min="1" />
                <span>ngày</span>
              </div>
            </label>
            <label v-if="needsField('overdue_days')">
              Quá hạn phí
              <div class="input-unit">
                <input v-model.number="formNumbers.overdue_days" type="number" min="1" />
                <span>ngày</span>
              </div>
            </label>
            <label v-if="needsField('report_count')">
              Số báo cáo tối thiểu
              <div class="input-unit">
                <input v-model.number="formNumbers.report_count" type="number" min="1" />
                <span>báo cáo</span>
              </div>
            </label>
            <label v-if="needsField('unique_reporters')">
              Số người báo cáo khác nhau
              <div class="input-unit">
                <input v-model.number="formNumbers.unique_reporters" type="number" min="1" />
                <span>người</span>
              </div>
            </label>
            <label v-if="needsField('window_days')">
              Theo dõi trong
              <div class="input-unit">
                <input v-model.number="formNumbers.window_days" type="number" min="1" />
                <span>ngày</span>
              </div>
            </label>
            <label v-if="needsField('transition_days')">
              Thời gian chuyển tiếp
              <div class="input-unit">
                <input v-model.number="formNumbers.transition_days" type="number" min="1" />
                <span>ngày</span>
              </div>
            </label>
            <label v-if="needsField('owner_confirm_required')" class="check-field">
              <input v-model="formBooleans.owner_confirm_required" type="checkbox" />
              Bắt buộc chủ sân xác nhận trước khi admin hoàn tiền
            </label>
            <label v-if="needsField('admin_can_complete_without_owner')" class="check-field">
              <input v-model="formBooleans.admin_can_complete_without_owner" type="checkbox" />
              Cho phép admin hoàn tất khi chủ sân chưa xác nhận
            </label>
          </div>

          <label v-if="ruleForm.rule_type">
            Tên hiển thị của quy tắc
            <input v-model.trim="ruleForm.rule_name" type="text" :placeholder="getRuleTypeLabel(ruleForm.rule_type)" />
          </label>
        </section>

        <section v-if="wizardStep === 3" class="wizard-body">
          <h4>Xem trước trước khi lưu</h4>
          <div class="preview-box">
            <strong>{{ ruleForm.rule_name || getRuleTypeLabel(ruleForm.rule_type) }}</strong>
            <p>{{ previewSummary }}</p>
          </div>
          <details class="technical-box">
            <summary>Xem dữ liệu kỹ thuật sẽ gửi API</summary>
            <pre>{{ formatJson(buildPayload(true)) }}</pre>
          </details>
        </section>

        <div v-if="wizardError" class="alert error">{{ wizardError }}</div>

        <footer class="modal-foot">
          <button class="btn secondary" type="button" @click="wizardStep === 1 ? closeWizard() : wizardStep--">
            {{ wizardStep === 1 ? 'Hủy' : 'Quay lại' }}
          </button>
          <div class="modal-actions">
            <button v-if="wizardStep < 3" class="btn primary" type="button" @click="nextWizardStep">Tiếp tục</button>
            <template v-else>
              <button class="btn secondary" type="button" :disabled="savingRule" @click="saveRule(false)">Lưu nháp</button>
              <button class="btn primary" type="button" :disabled="savingRule" @click="saveRule(true)">
                {{ savingRule ? 'Đang lưu...' : 'Lưu và bật' }}
              </button>
            </template>
          </div>
        </footer>
      </div>
    </div>

    <div v-if="selectedRule" class="modal-bg" @click.self="selectedRule = null">
      <div class="modal-box">
        <header class="modal-head">
          <div>
            <p class="eyebrow">Chi tiết rule</p>
            <h3>{{ selectedRule.rule_label_vi || selectedRule.rule_type_label || selectedRule.rule_name }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="selectedRule = null">
            <AppIcon name="x" size="18" />
          </button>
        </header>
        <div class="info-grid compact">
          <InfoItem label="Chính sách cha" :value="policy.title" />
          <InfoItem label="Tình huống áp dụng" :value="selectedRule.action_label_vi || getActionLabel(selectedRule.action_code)" />
          <InfoItem label="Trạng thái" :value="selectedRule.is_active ? 'Đang bật' : 'Đang tắt'" />
          <InfoItem label="Cho sân override" :value="hasOverride(selectedRule) ? 'Có' : 'Không'" />
        </div>
        <div class="business-box">
          <strong>Mô tả nghiệp vụ</strong>
          <p>{{ selectedRule.business_summary_vi || getRuleSummary(selectedRule) }}</p>
        </div>
        <div class="two-columns">
          <div class="business-box">
            <strong>Điều kiện</strong>
            <p>{{ selectedRule.condition_summary_vi || 'Không có điều kiện đặc biệt.' }}</p>
          </div>
          <div class="business-box">
            <strong>Kết quả</strong>
            <p>{{ selectedRule.result_summary_vi || 'Không có kết quả mô tả.' }}</p>
          </div>
        </div>
        <details class="technical-box">
          <summary>Xem dữ liệu kỹ thuật</summary>
          <pre>{{ formatJson(selectedRule.technical_detail || selectedRule) }}</pre>
        </details>
      </div>
    </div>

    <div v-if="confirmDelete" class="modal-bg" @click.self="confirmDelete = false">
      <div class="modal-box confirm-box">
        <h3>Xóa bản nháp này?</h3>
        <p>Chính sách <strong>{{ policy?.title }}</strong> và tất cả quy tắc liên quan sẽ bị xóa vĩnh viễn.</p>
        <div class="modal-foot">
          <button class="btn secondary" type="button" @click="confirmDelete = false">Hủy</button>
          <button class="btn danger" type="button" :disabled="saving" @click="deletePolicy">Xóa chính sách</button>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminPolicyService } from '../../services/adminPolicies.js';
import {
  getActionLabel,
  getPolicyTypeLabel,
  getRuleSummary,
  getRuleTypeLabel,
  getStatusLabel,
} from '../../utils/labelMaps.js';

const InfoItem = {
  props: { label: String, value: [String, Number] },
  template: '<div class="info-item"><span>{{ label }}</span><strong>{{ value || "-" }}</strong></div>',
};

export default {
  name: 'AdminPolicyDetail',
  components: { AppIcon, InfoItem },
  data() {
    return {
      loading: true,
      saving: false,
      savingContent: false,
      savingRule: false,
      loadingOptions: false,
      error: '',
      success: '',
      wizardError: '',
      activeTab: this.$route.query.tab || 'overview',
      policy: null,
      rules: [],
      venueRules: [],
      evaluationLogs: [],
      auditLogs: [],
      actionOptions: [],
      ruleTemplates: [],
      contentDraft: '',
      showWizard: false,
      wizardStep: 1,
      selectedRule: null,
      confirmDelete: false,
      ruleForm: { action_code: '', rule_type: '', rule_name: '' },
      formNumbers: {
        hours_before_start: 24,
        refund_percent: 80,
        days_before_due: 3,
        overdue_days: 7,
        report_count: 5,
        unique_reporters: 2,
        window_days: 14,
        transition_days: 30,
      },
      formBooleans: {
        owner_confirm_required: true,
        admin_can_complete_without_owner: false,
      },
    };
  },
  computed: {
    policyType() {
      return this.policy?.policy_type || this.policy?.type || 'general';
    },
    tabs() {
      return [
        { key: 'overview', label: 'Tổng quan', icon: 'eye' },
        { key: 'content', label: 'Nội dung chính sách', icon: 'fileText' },
        { key: 'rules', label: 'Quy tắc xử lý', icon: 'sliders', count: this.rules.length },
        { key: 'venue', label: 'Chính sách sân', icon: 'building', count: this.venueRules.length },
        { key: 'evaluation', label: 'Lịch sử áp dụng', icon: 'clock', count: this.evaluationLogs.length },
        { key: 'audit', label: 'Lịch sử thay đổi', icon: 'history', count: this.auditLogs.length },
      ];
    },
    filteredActions() {
      return this.actionOptions.filter((action) => (action.policy_types || []).includes(this.policyType));
    },
    filteredTemplates() {
      return this.ruleTemplates.filter((template) => {
        const policyOk = (template.policy_types || []).includes(this.policyType);
        const actionOk = !this.ruleForm.action_code || (template.action_codes || []).includes(this.ruleForm.action_code);
        return policyOk && actionOk;
      });
    },
    selectedTemplate() {
      return this.ruleTemplates.find((template) => template.rule_type === this.ruleForm.rule_type) || null;
    },
    previewSummary() {
      const type = this.ruleForm.rule_type;
      const n = this.formNumbers;
      const b = this.formBooleans;
      const summaries = {
        terms_acceptance_required: 'Người dùng/chủ sân phải chấp nhận điều khoản trước khi tiếp tục sử dụng hệ thống.',
        cancel_before_hours: `Khách chỉ được hủy booking trước giờ chơi tối thiểu ${n.hours_before_start} giờ.`,
        refund_percent_by_cancel_time: `Nếu khách hủy booking trước giờ chơi ít nhất ${n.hours_before_start} giờ, hệ thống đề xuất hoàn tối thiểu ${n.refund_percent}% số tiền đã thanh toán.`,
        owner_confirm_required_before_admin_transfer: b.owner_confirm_required
          ? 'Admin chỉ được hoàn tất hoàn tiền sau khi chủ sân đã xác nhận yêu cầu.'
          : 'Rule này đang bỏ qua bước xác nhận của chủ sân, backend sẽ chặn nếu chính sách hoàn tiền yêu cầu bước này.',
        platform_fee_overdue_warning: `Hệ thống nhắc chủ sân khi phí nền tảng sắp/quá hạn trong ${n.days_before_due} ngày.`,
        platform_fee_overdue_lock: `Nếu cụm sân quá hạn phí nền tảng ${n.overdue_days} ngày, hệ thống giới hạn quyền thao tác của chủ sân.`,
        venue_policy_override_limit: 'Chính sách riêng của sân không được thấp hơn hoặc trái khung chính sách hệ thống.',
        report_threshold_requires_review: `Nếu nội dung có ${n.report_count} báo cáo hợp lệ bởi ít nhất ${n.unique_reporters} người trong ${n.window_days} ngày, hệ thống đưa nội dung vào chờ kiểm duyệt.`,
        contract_signing_required: 'Hợp đồng chỉ có hiệu lực khi có đủ chữ ký chủ sân và SportGo.',
        partner_termination_transition_30_days: `Sau ${n.transition_days} ngày chuyển tiếp khi chấm dứt hợp đồng, hệ thống thu quyền chủ sân.`,
        partner_application_approve_requires_contract: 'Admin duyệt hồ sơ đối tác xong phải sinh hợp đồng trước khi hoàn tất hồ sơ.',
      };

      return summaries[type] || this.selectedTemplate?.business_summary_vi || 'Chọn loại quy tắc để xem trước câu nghiệp vụ.';
    },
  },
  watch: {
    activeTab(value) {
      this.$router.replace({ query: { ...this.$route.query, tab: value } }).catch(() => {});
    },
  },
  async mounted() {
    await Promise.all([this.loadDetail(), this.loadOptions()]);
  },
  methods: {
    getActionLabel,
    getPolicyTypeLabel,
    getRuleSummary,
    getRuleTypeLabel,
    getStatusLabel,

    async loadDetail() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminPolicyService.show(this.$route.params.id);
        const data = response.data || {};
        this.policy = data.policy || null;
        this.rules = data.rules || [];
        this.venueRules = data.venue_rules || [];
        this.evaluationLogs = data.evaluation_logs || [];
        this.auditLogs = data.audit_logs || [];
        this.contentDraft = this.policy?.content || '';
      } catch (error) {
        this.error = error.message || 'Không thể tải chi tiết chính sách.';
      } finally {
        this.loading = false;
      }
    },
    async loadOptions() {
      this.loadingOptions = true;
      try {
        const [actions, templates] = await Promise.all([
          adminPolicyService.actionCodes(),
          adminPolicyService.ruleTemplates(),
        ]);
        this.actionOptions = Object.values(actions.data || {});
        this.ruleTemplates = Object.values(templates.data || {});
      } catch (error) {
        this.error = error.message || 'Không thể tải mẫu quy tắc.';
      } finally {
        this.loadingOptions = false;
      }
    },
    async clonePolicy() {
      this.saving = true;
      try {
        const response = await adminPolicyService.cloneVersion(this.policy.id);
        const id = response.data?.id;
        if (id) {
          this.$router.push({ name: 'admin-policy-detail', params: { id }, query: { tab: 'rules' } });
        }
      } catch (error) {
        this.error = error.message || 'Không thể tạo phiên bản mới.';
      } finally {
        this.saving = false;
      }
    },
    async publishPolicy() {
      this.saving = true;
      try {
        const response = await adminPolicyService.publish(this.policy.id);
        this.success = response.message || 'Đã áp dụng chính sách.';
        await this.loadDetail();
      } catch (error) {
        this.error = error.message || 'Không thể áp dụng chính sách.';
      } finally {
        this.saving = false;
      }
    },
    async archivePolicy() {
      this.saving = true;
      try {
        const response = await adminPolicyService.updateStatus(this.policy.id, { status: 'archived', reason: 'Ngưng áp dụng từ màn quản trị.' });
        this.success = response.message || 'Đã ngưng áp dụng chính sách.';
        await this.loadDetail();
      } catch (error) {
        this.error = error.message || 'Không thể ngưng áp dụng chính sách.';
      } finally {
        this.saving = false;
      }
    },
    async deletePolicy() {
      this.saving = true;
      try {
        await adminPolicyService.delete(this.policy.id);
        this.$router.push({ name: 'admin-policies' });
      } catch (error) {
        this.error = error.message || 'Không thể xóa chính sách.';
      } finally {
        this.saving = false;
        this.confirmDelete = false;
      }
    },
    async saveContent() {
      this.savingContent = true;
      try {
        const response = await adminPolicyService.update(this.policy.id, {
          key: this.policy.key,
          version: this.policy.version,
          title: this.policy.title,
          content: this.contentDraft,
          policy_type: this.policy.policy_type,
          is_overridable: this.policy.is_overridable,
          require_reaccept: this.policy.require_reaccept,
          priority: this.policy.priority,
          effective_from: this.policy.effective_from,
          effective_to: this.policy.effective_to,
          change_summary: this.policy.change_summary,
        });
        this.success = response.message || 'Đã lưu nội dung chính sách.';
        await this.loadDetail();
      } catch (error) {
        this.error = error.message || 'Không thể lưu nội dung chính sách.';
      } finally {
        this.savingContent = false;
      }
    },
    openRuleWizard() {
      this.showWizard = true;
      this.wizardStep = 1;
      this.wizardError = '';
      this.ruleForm = { action_code: '', rule_type: '', rule_name: '' };
    },
    closeWizard() {
      this.showWizard = false;
      this.wizardError = '';
    },
    nextWizardStep() {
      if (this.wizardStep === 1 && !this.ruleForm.action_code) {
        this.wizardError = 'Vui lòng chọn tình huống áp dụng.';
        return;
      }
      if (this.wizardStep === 2 && !this.ruleForm.rule_type) {
        this.wizardError = 'Vui lòng chọn loại quy tắc.';
        return;
      }
      this.wizardError = '';
      this.wizardStep += 1;
    },
    syncRuleName() {
      if (!this.ruleForm.rule_type) return;
      const template = this.selectedTemplate;
      this.ruleForm.rule_name = template?.label || template?.rule_type_label || getRuleTypeLabel(this.ruleForm.rule_type);
    },
    openRuleDetail(rule) {
      this.selectedRule = rule;
    },
    needsField(field) {
      const fields = {
        cancel_before_hours: ['hours_before_start'],
        refund_percent_by_cancel_time: ['hours_before_start', 'refund_percent'],
        owner_confirm_required_before_admin_transfer: ['owner_confirm_required', 'admin_can_complete_without_owner'],
        platform_fee_overdue_warning: ['days_before_due'],
        platform_fee_overdue_lock: ['overdue_days'],
        report_threshold_requires_review: ['report_count', 'unique_reporters', 'window_days'],
        partner_termination_transition_30_days: ['transition_days'],
      };
      return (fields[this.ruleForm.rule_type] || []).includes(field);
    },
    buildPayload(active) {
      const n = this.formNumbers;
      const b = this.formBooleans;
      const type = this.ruleForm.rule_type;
      const condition = {};
      const result = {};
      let decisionKey = this.selectedTemplate?.decision_key || null;

      if (type === 'cancel_before_hours') {
        condition.hours_before_start = { gte: Number(n.hours_before_start) };
        result.can_cancel = true;
        decisionKey = decisionKey || 'can_cancel';
      }
      if (type === 'refund_percent_by_cancel_time') {
        condition.hours_before_start = { gte: Number(n.hours_before_start) };
        result.refund_percent = Number(n.refund_percent);
        result.owner_confirm_required = true;
        decisionKey = decisionKey || 'refund_percent';
      }
      if (type === 'owner_confirm_required_before_admin_transfer') {
        result.owner_confirm_required = Boolean(b.owner_confirm_required);
        result.admin_can_complete_without_owner = Boolean(b.admin_can_complete_without_owner);
        decisionKey = decisionKey || 'owner_confirm_required';
      }
      if (type === 'platform_fee_overdue_warning') {
        condition.days_before_due = Number(n.days_before_due);
        result.action = 'notify_owner';
        decisionKey = decisionKey || 'platform_fee_warning';
      }
      if (type === 'platform_fee_overdue_lock') {
        condition.overdue_days = { gte: Number(n.overdue_days) };
        result.action = 'limit_owner_access';
        result.access_mode = 'limited';
        decisionKey = decisionKey || 'owner_access_mode';
      }
      if (type === 'report_threshold_requires_review') {
        condition.report_count = { gte: Number(n.report_count) };
        condition.unique_reporters = { gte: Number(n.unique_reporters) };
        condition.window_days = Number(n.window_days);
        result.action = 'mark_pending_review';
        decisionKey = decisionKey || 'moderation_action';
      }
      if (type === 'contract_signing_required') {
        condition.owner_signed = true;
        condition.sportgo_signed = true;
        result.contract_status = 'signed_active';
        decisionKey = decisionKey || 'contract_status';
      }
      if (type === 'partner_termination_transition_30_days') {
        result.transition_days = Number(n.transition_days);
        result.action = 'revoke_owner_access';
        decisionKey = decisionKey || 'owner_access';
      }
      if (type === 'terms_acceptance_required') {
        result.require_reaccept = true;
        decisionKey = decisionKey || 'require_reaccept';
      }
      if (type === 'venue_policy_override_limit') {
        result.action = 'reject_if_below_system_minimum';
        decisionKey = decisionKey || 'venue_policy_constraint';
      }
      if (type === 'partner_application_approve_requires_contract') {
        result.action = 'generate_contract';
        result.next_status = 'approved_pending_contract';
        decisionKey = decisionKey || 'partner_application_status';
      }

      return {
        action_code: this.ruleForm.action_code,
        rule_code: `${type}_${Date.now()}`,
        rule_name: this.ruleForm.rule_name || getRuleTypeLabel(type),
        rule_type: type,
        decision_key: decisionKey,
        conflict_group: this.policyType,
        condition_json: condition,
        result_json: result,
        constraint_json: {},
        allowed_override_json: {},
        priority: 100,
        is_active: active,
      };
    },
    async saveRule(active) {
      this.wizardError = '';
      this.savingRule = true;
      try {
        await adminPolicyService.addBinding(this.policy.id, {
          module: this.policyType,
          action_code: this.ruleForm.action_code,
          description: getActionLabel(this.ruleForm.action_code),
          is_active: true,
        });
        const response = await adminPolicyService.addRule(this.policy.id, this.buildPayload(active));
        this.success = response.message || 'Đã thêm quy tắc xử lý tự động.';
        this.closeWizard();
        await this.loadDetail();
        this.activeTab = 'rules';
      } catch (error) {
        this.wizardError = error.message || 'Không thể lưu quy tắc.';
      } finally {
        this.savingRule = false;
      }
    },
    async toggleRule(rule) {
      try {
        const response = await adminPolicyService.toggleRule(this.policy.id, rule.id);
        this.success = response.message || 'Đã cập nhật trạng thái quy tắc.';
        await this.loadDetail();
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật quy tắc.';
      }
    },
    hasOverride(rule) {
      const allowed = rule.allowed_override_json;
      return Boolean(allowed && Object.keys(allowed).length);
    },
    statusTone(status) {
      return {
        active: 'success',
        draft: 'warning',
        inactive: 'muted',
        archived: 'muted',
        pending_review: 'warning',
        rejected: 'danger',
      }[String(status || '').toLowerCase()] || 'muted';
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    formatDateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
    formatJson(value) {
      return JSON.stringify(value || {}, null, 2);
    },
  },
};
</script>

<style scoped>
.admin-page { display: grid; gap: 18px; }
.page-head, .section-head, .modal-head, .modal-foot { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; }
.page-head h2, .panel h3, .wizard-body h4, .modal-box h3 { margin: 0; }
.page-head p, .section-head p, .muted, small { margin: 0; color: #64748b; }
.back-link { border: 0; background: transparent; padding: 0; display: inline-flex; align-items: center; gap: 6px; color: #15803d; font-weight: 900; cursor: pointer; }
.title-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 6px 0; }
.page-actions, .modal-actions, .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.tabs button { border: 1px solid #dbe3ef; background: #fff; border-radius: 8px; padding: 10px 12px; display: inline-flex; align-items: center; gap: 7px; font-weight: 900; color: #334155; cursor: pointer; }
.tabs button.active { background: #dcfce7; color: #166534; border-color: #22c55e; }
.tab-count { background: #f1f5f9; border-radius: 999px; padding: 2px 7px; font-size: 12px; }
.panel, .state-card, .modal-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; }
.panel { display: grid; gap: 14px; }
.info-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.info-grid.compact { grid-template-columns: repeat(2, minmax(0, 1fr)); }
:deep(.info-item), .business-box { display: grid; gap: 6px; padding: 12px; border-radius: 10px; background: #f8fafc; }
:deep(.info-item span) { color: #64748b; font-size: 13px; }
.business-box p { margin: 0; color: #334155; line-height: 1.55; }
.content-textarea, .modal-box input, .modal-box select, .modal-box textarea { width: 100%; border: 1px solid #dbe3ef; border-radius: 10px; padding: 11px 12px; font: inherit; color: #0f172a; background: #fff; }
.notice { padding: 12px; border-radius: 10px; background: #f0fdf4; color: #166534; font-weight: 800; }
.notice.warning { background: #fffbeb; color: #92400e; }
.rule-table { overflow: auto; border: 1px solid #e2e8f0; border-radius: 10px; }
table { width: 100%; border-collapse: collapse; min-width: 1080px; }
th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
th { background: #f8fafc; color: #334155; font-size: 12px; text-transform: uppercase; }
td span { display: block; margin-top: 5px; color: #64748b; }
.list-box { display: grid; gap: 10px; }
.list-box article { display: grid; gap: 7px; padding: 12px; border-radius: 10px; background: #f8fafc; }
.change-list { margin: 0; padding-left: 18px; color: #334155; }
.two-columns { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.empty-state { padding: 24px; border: 1px dashed #cbd5e1; border-radius: 12px; color: #64748b; text-align: center; font-weight: 800; }
.btn, .icon-btn { border: 0; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 900; cursor: pointer; }
.btn { padding: 10px 14px; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #f1f5f9; color: #334155; }
.btn.danger, .btn.danger-ghost { background: #dc2626; color: #fff; }
.btn.danger-ghost { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.icon-btn { width: 36px; height: 36px; border: 1px solid #dbe3ef; background: #fff; color: #334155; }
.badge { display: inline-flex; border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 900; white-space: nowrap; }
.badge.success { background: #dcfce7; color: #166534; }
.badge.warning { background: #fef3c7; color: #92400e; }
.badge.danger { background: #fee2e2; color: #991b1b; }
.badge.muted { background: #f1f5f9; color: #475569; }
.alert { border-radius: 10px; padding: 12px 14px; font-weight: 800; }
.alert.error { background: #fef2f2; color: #991b1b; }
.alert.success { background: #f0fdf4; color: #166534; }
.technical-box { border-top: 1px solid #e2e8f0; padding-top: 10px; }
summary { cursor: pointer; font-weight: 900; color: #475569; }
pre { max-height: 300px; overflow: auto; background: #0f172a; color: #e2e8f0; border-radius: 8px; padding: 12px; }
.modal-bg { position: fixed; inset: 0; background: rgba(15, 23, 42, .45); display: grid; place-items: center; z-index: 60; padding: 20px; }
.modal-box { width: min(880px, 100%); max-height: 92vh; overflow: auto; display: grid; gap: 14px; box-shadow: 0 24px 80px rgba(15, 23, 42, .22); }
.confirm-box { width: min(520px, 100%); }
.eyebrow { margin: 0 0 6px; color: #15803d; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
.step-bar { display: flex; gap: 8px; flex-wrap: wrap; }
.step-bar span { border-radius: 999px; padding: 7px 10px; background: #f1f5f9; color: #475569; font-weight: 900; }
.step-bar span.active { background: #dcfce7; color: #166534; }
.wizard-body { display: grid; gap: 12px; }
.option-list { display: grid; gap: 10px; max-height: 340px; overflow: auto; }
.option-card { display: flex; gap: 10px; align-items: flex-start; padding: 12px; border: 1px solid #dbe3ef; border-radius: 10px; cursor: pointer; }
.option-card.selected { border-color: #22c55e; background: #f0fdf4; }
.option-card span { display: grid; gap: 4px; }
.param-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.param-grid label, .modal-box label { display: grid; gap: 7px; color: #334155; font-weight: 800; }
.input-unit { display: flex; align-items: center; gap: 8px; }
.input-unit span { min-width: 64px; color: #64748b; }
.check-field { display: flex !important; flex-direction: row; align-items: center; }
.preview-box { padding: 14px; border-radius: 12px; background: #f0fdf4; color: #166534; }
.preview-box p { margin: 6px 0 0; }
.spinner { width: 18px; height: 18px; border: 2px solid #bbf7d0; border-top-color: #16a34a; border-radius: 50%; display: inline-block; margin-right: 8px; animation: spin .8s linear infinite; vertical-align: middle; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 900px) {
  .page-head, .section-head, .modal-foot { display: grid; }
  .info-grid, .info-grid.compact, .two-columns, .param-grid { grid-template-columns: 1fr; }
}
</style>

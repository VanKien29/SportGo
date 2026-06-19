<template>
  <section class="admin-page">

    <!-- Loading -->
    <div v-if="loading" class="table-state">
      <div class="spinner"></div>
      Đang tải chi tiết chính sách...
    </div>

    <!-- Error without data -->
    <template v-else-if="!policy">
      <div class="alert error">{{ error || 'Không tìm thấy chính sách.' }}</div>
      <button class="btn secondary" @click="$router.push({ name: 'admin-policies' })">← Quay lại danh sách</button>
    </template>

    <template v-else>

      <!-- Back Action Bar -->
      <div class="back-action-bar">
        <button class="back-link" @click="$router.push({ name: 'admin-policies' })">← Danh sách chính sách</button>
        <div class="back-action-buttons">
          <button v-if="policy.status === 'draft'" class="btn danger-ghost" type="button" @click="confirmDelete.show = true">
            <AppIcon name="trash" size="16" />
            Xóa nháp
          </button>
          <button class="btn secondary" type="button" @click="clonePolicy" :disabled="saving">
            <AppIcon name="copy" size="16" />
            Tạo phiên bản mới
          </button>
          <button v-if="policy.status !== 'active'" class="btn primary" type="button" @click="publishPolicy" :disabled="saving">
            <AppIcon name="check" size="16" />
            {{ saving ? 'Đang xử lý...' : 'Áp dụng ngay' }}
          </button>
          <button v-else class="btn danger-ghost" type="button" @click="archivePolicy" :disabled="saving">
            <AppIcon name="power" size="16" />
            {{ saving ? 'Đang xử lý...' : 'Ngưng áp dụng' }}
          </button>
        </div>
      </div>

      <!-- Policy Info Bar -->
      <div class="policy-info-bar">
        <div class="policy-title-row">
          <h2>{{ policy.title }}</h2>
          <span class="badge" :class="getStatusBadgeClass(policy.status)">{{ policy.status_label || getStatusLabel(policy.status) }}</span>
          <span class="badge badge-version">v{{ policy.version || 1 }}</span>
        </div>
        <p class="policy-meta">{{ policy.policy_type_label || getPolicyTypeLabel(policy.policy_type) }} · {{ policy.business_summary_vi || policy.business_summary || 'Chính sách hệ thống SportGo.' }}</p>
      </div>

      <!-- Alerts -->
      <div v-if="success" class="alert success">{{ success }}</div>
      <div v-if="error" class="alert error">{{ error }}</div>

      <!-- Tabs -->
      <div class="tab-nav">
        <button
          v-for="tab in tabs" :key="tab.key"
          class="tab-btn"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          <AppIcon :name="tab.icon" size="15" />
          {{ tab.label }}
          <span v-if="tab.count != null" class="tab-count">{{ tab.count }}</span>
        </button>
      </div>

      <!-- ─── TAB: Tổng quan ─── -->
      <div v-if="activeTab === 'overview'" class="tab-body">
        <div class="meta-grid">
          <div class="meta-item span2">
            <span class="meta-label">Mô tả nghiệp vụ</span>
            <span class="meta-value">{{ policy.business_summary_vi || policy.business_summary || '(Chưa có mô tả)' }}</span>
          </div>
          <div class="meta-item">
            <span class="meta-label">Nhóm chính sách</span>
            <span class="meta-value">{{ policy.policy_type_label || getPolicyTypeLabel(policy.policy_type) }}</span>
          </div>
          <div class="meta-item">
            <span class="meta-label">Phiên bản</span>
            <span class="meta-value">v{{ policy.version || 1 }}</span>
          </div>
          <div class="meta-item">
            <span class="meta-label">Thứ tự ưu tiên</span>
            <span class="meta-value">{{ policy.priority ?? 0 }}</span>
          </div>
          <div class="meta-item">
            <span class="meta-label">Hiệu lực từ</span>
            <span class="meta-value">{{ formatDate(policy.effective_from || policy.published_at) }}</span>
          </div>
          <div class="meta-item">
            <span class="meta-label">Yêu cầu chấp nhận lại</span>
            <span class="meta-value" :class="{ 'text-green': policy.require_reaccept }">{{ policy.require_reaccept ? 'Có' : 'Không' }}</span>
          </div>
          <div class="meta-item">
            <span class="meta-label">Cho sân cấu hình riêng</span>
            <span class="meta-value" :class="{ 'text-green': policy.is_overridable }">{{ policy.is_overridable ? 'Có (trong khung hệ thống)' : 'Không' }}</span>
          </div>
          <div class="meta-item">
            <span class="meta-label">Tổng quy tắc</span>
            <span class="meta-value">{{ rules.length }} quy tắc</span>
          </div>
        </div>
      </div>

      <!-- ─── TAB: Nội dung ─── -->
      <div v-if="activeTab === 'content'" class="tab-body">
        <div class="section-row">
          <div>
            <strong>Nội dung văn bản chính sách</strong>
            <p>Đây là nội dung người dùng và chủ sân sẽ đọc khi cần đồng ý.</p>
          </div>
          <button v-if="policy.can_edit_content" class="btn primary" @click="saveContent" :disabled="savingContent">
            <AppIcon name="check" size="15" />
            {{ savingContent ? 'Đang lưu...' : 'Lưu nội dung' }}
          </button>
        </div>
        <div v-if="!policy.can_edit_content" class="info-notice">
          <AppIcon name="lock" size="15" />
          Chính sách đang áp dụng. Hãy tạo phiên bản mới để chỉnh sửa.
        </div>
        <div class="content-editor-wrap">
          <QuillEditor
            v-if="policy.can_edit_content"
            v-model:content="contentDraft"
            contentType="html"
            theme="snow"
            :toolbar="[
              ['bold', 'italic', 'underline', 'strike'],
              [{ 'header': [1, 2, 3, false] }],
              [{ 'list': 'ordered'}, { 'list': 'bullet' }],
              ['link']
            ]"
          />
          <div v-else class="content-readonly ql-editor" v-html="contentDraft"></div>
        </div>
      </div>

      <!-- ─── TAB: Cấu hình xử lý ─── -->
      <div v-if="activeTab === 'config'" class="tab-body">
        <div class="section-row">
          <div>
            <strong>Cấu hình xử lý theo nghiệp vụ</strong>
            <p>{{ configurationSummary }}</p>
          </div>
        </div>

        <div v-if="policy.status === 'active'" class="info-notice warning">
          <AppIcon name="lock" size="15" />
          Chính sách đang áp dụng. Tạo phiên bản mới để chỉnh sửa cấu hình.
        </div>

        <div v-if="configurationType === 'text_only'" class="info-notice">
          <AppIcon name="fileText" size="15" />
          Chính sách này chỉ là nội dung hiển thị, không có xử lý tự động.
        </div>

        <article v-else-if="configurationType === 'cancel_refund_tiers'" class="config-card">
          <div class="config-head">
            <div>
              <span class="config-kicker">Hủy & hoàn booking</span>
              <h3>Bảng mốc thời gian hủy và hoàn</h3>
              <p>{{ cancelRefundConfiguration?.summary || 'Chưa cấu hình bảng mốc hủy và hoàn.' }}</p>
            </div>
            <button class="btn primary" type="button" :disabled="!canEditConfig" @click="openCancelRefundModal">
              <AppIcon name="pencil" size="15" />
              Sửa bảng mốc
            </button>
          </div>
          <div class="config-table-wrap">
            <table class="config-table">
              <thead>
                <tr>
                  <th>Mốc thời gian</th>
                  <th>Điều kiện</th>
                  <th>Kết quả</th>
                  <th>Xác nhận</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="tier in cancelRefundRows" :key="tier.key">
                  <td><strong>{{ tier.label }}</strong></td>
                  <td>{{ tier.condition }}</td>
                  <td><span class="badge" :class="tier.allow_cancel ? 'status-active' : 'status-rejected'">{{ tier.result }}</span></td>
                  <td>{{ tier.confirmation }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>

        <AdminModerationConfig v-else-if="configurationType === 'moderation_thresholds'" :policy-id="policy.id" :can-edit-config="canEditConfig" />

        <article v-else class="config-card">
          <div class="config-head">
            <div>
              <span class="config-kicker">{{ genericConfigTitle }}</span>
              <h3>{{ genericConfigHeading }}</h3>
              <p>{{ genericConfigSummary }}</p>
            </div>
            <button class="btn primary" type="button" :disabled="!canEditConfig || !genericConfigFields.length" @click="openGenericConfigModal">
              <AppIcon name="pencil" size="15" />
              Sửa cấu hình
            </button>
          </div>
          <div class="meta-grid">
            <div v-for="entry in genericConfigEntries" :key="entry.key" class="meta-item">
              <span class="meta-label">{{ entry.label }}</span>
              <span class="meta-value">{{ entry.value }}</span>
            </div>
          </div>
        </article>
      </div>



      <!-- ─── TAB: Lịch sử thay đổi ─── -->
      <div v-if="activeTab === 'audit'" class="tab-body">
        <div class="section-row">
          <div>
            <strong>Lịch sử thay đổi chính sách</strong>
            <p>Ghi nhận các thao tác chỉnh sửa, kích hoạt và tạo phiên bản mới.</p>
          </div>
        </div>

        <div v-if="!auditLogs.length" class="empty-state">
          <AppIcon name="history" size="28" />
          <span>Chưa có lịch sử thay đổi.</span>
        </div>

        <div class="audit-list">
          <div v-for="log in auditLogs" :key="log.id" class="audit-row">
            <div class="audit-dot"></div>
            <div class="audit-content">
              <strong>{{ log.human_message || 'Đã cập nhật chính sách' }}</strong>
              <span class="audit-meta">{{ formatDateTime(log.created_at) }} · {{ log.actor_name || 'Hệ thống' }}</span>
              <ul v-if="log.changes_summary?.length" class="change-list">
                <li v-for="c in log.changes_summary" :key="c.field">{{ c.summary }}</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

    </template>

    <!-- ═══════════════════════════════════
         WIZARD MODAL — Thêm quy tắc
         2 bước: Chọn tình huống → Cấu hình & Lưu
    ════════════════════════════════════════ -->
    <div v-if="showWizard" class="modal-bg" @click.self="closeWizard">
      <div class="modal-box">

        <!-- Modal header -->
        <div class="modal-head">
          <div>
            <p class="eyebrow">Chính sách: {{ policy?.title }}</p>
            <h3>Thêm quy tắc xử lý tự động</h3>
          </div>
          <button class="icon-btn" @click="closeWizard"><AppIcon name="x" size="18" /></button>
        </div>

        <!-- Step indicator (2 bước) -->
        <div class="step-bar">
          <div class="step-item" :class="{ done: wizardStep > 1, active: wizardStep === 1 }">
            <div class="step-dot">
              <AppIcon v-if="wizardStep > 1" name="check" size="12" />
              <span v-else>1</span>
            </div>
            <span>Chọn tình huống</span>
          </div>
          <div class="step-line" :class="{ done: wizardStep > 1 }"></div>
          <div class="step-item" :class="{ active: wizardStep === 2 }">
            <div class="step-dot">
              <span>2</span>
            </div>
            <span>Cấu hình & Lưu</span>
          </div>
        </div>

        <!-- ── Bước 1: Chọn tình huống ── -->
        <div v-if="wizardStep === 1" class="modal-body">
          <p class="step-hint">Chọn tình huống mà quy tắc sẽ được áp dụng trong hệ thống.</p>

          <div v-if="loadingOptions" class="table-state">
            <div class="spinner small"></div>
            Đang tải danh sách tình huống...
          </div>

          <div v-else-if="!filteredActions.length" class="empty-state small">
            <AppIcon name="alert" size="22" />
            <span>Chưa có tình huống phù hợp cho nhóm <strong>{{ policy?.policy_type_label }}</strong>.</span>
          </div>

          <div v-else class="option-list">
            <label
              v-for="action in filteredActions"
              :key="action.action_code"
              class="option-card"
              :class="{ selected: ruleForm.action_code === action.action_code }"
            >
              <input type="radio" :value="action.action_code" v-model="ruleForm.action_code" />
              <div class="option-card-body">
                <strong>{{ action.label || action.action_label_vi || getActionLabel(action.action_code) }}</strong>
                <span>Chọn tình huống nghiệp vụ này để tiếp tục cấu hình quy tắc.</span>
              </div>
            </label>
          </div>
        </div>

        <!-- ── Bước 2: Cấu hình & Lưu ── -->
        <div v-if="wizardStep === 2" class="modal-body">

          <!-- Tình huống đã chọn -->
          <div class="selected-context">
            <AppIcon name="circleCheck" size="16" />
            <span>Tình huống: <strong>{{ getActionLabel(ruleForm.action_code) }}</strong></span>
            <button class="link-btn" @click="wizardStep = 1">Thay đổi</button>
          </div>

          <!-- Chọn mẫu quy tắc -->
          <p class="field-label">Chọn loại quy tắc</p>

          <div v-if="!filteredTemplates.length" class="empty-state small">
            <AppIcon name="alert" size="20" />
            <span>Chưa có mẫu quy tắc cho tình huống này.</span>
          </div>

          <div v-else class="option-list compact">
            <label
              v-for="tpl in filteredTemplates"
              :key="tpl.rule_type"
              class="option-card"
              :class="{ selected: ruleForm.rule_type === tpl.rule_type }"
              @click="selectTemplate(tpl)"
            >
              <input type="radio" :value="tpl.rule_type" v-model="ruleForm.rule_type" />
              <div class="option-card-body">
                <strong>{{ tpl.label || getRuleTypeLabel(tpl.rule_type) }}</strong>
                <span>{{ tpl.business_summary_vi || tpl.description || '' }}</span>
              </div>
            </label>
          </div>

          <!-- Tham số (chỉ hiện khi đã chọn loại) -->
          <template v-if="ruleForm.rule_type">
            <div v-if="hasConfigFields" class="param-section">
              <p class="field-label">Điều chỉnh tham số</p>
              <div class="param-grid">
                <label v-if="needsField('hours_before_start')" class="param-field">
                  <span>Hủy trước giờ chơi tối thiểu</span>
                  <div class="input-unit">
                    <input v-model.number="formNumbers.hours_before_start" type="number" min="1" />
                    <span>giờ</span>
                  </div>
                </label>
                <label v-if="needsField('refund_percent')" class="param-field">
                  <span>Phần trăm hoàn tiền</span>
                  <div class="input-unit">
                    <input v-model.number="formNumbers.refund_percent" type="number" min="0" max="100" />
                    <span>%</span>
                  </div>
                </label>
                <label v-if="needsField('days_before_due')" class="param-field">
                  <span>Nhắc trước hạn</span>
                  <div class="input-unit">
                    <input v-model.number="formNumbers.days_before_due" type="number" min="1" />
                    <span>ngày</span>
                  </div>
                </label>
                <label v-if="needsField('overdue_days')" class="param-field">
                  <span>Quá hạn để giới hạn/khóa</span>
                  <div class="input-unit">
                    <input v-model.number="formNumbers.overdue_days" type="number" min="1" />
                    <span>ngày</span>
                  </div>
                </label>
                <label v-if="needsField('report_count')" class="param-field">
                  <span>Số báo cáo tối thiểu</span>
                  <div class="input-unit">
                    <input v-model.number="formNumbers.report_count" type="number" min="1" />
                    <span>báo cáo</span>
                  </div>
                </label>
                <label v-if="needsField('unique_reporters')" class="param-field">
                  <span>Số người báo cáo khác nhau</span>
                  <div class="input-unit">
                    <input v-model.number="formNumbers.unique_reporters" type="number" min="1" />
                    <span>người</span>
                  </div>
                </label>
                <label v-if="needsField('window_days')" class="param-field">
                  <span>Theo dõi trong</span>
                  <div class="input-unit">
                    <input v-model.number="formNumbers.window_days" type="number" min="1" />
                    <span>ngày</span>
                  </div>
                </label>
                <label v-if="needsField('transition_days')" class="param-field">
                  <span>Thời gian chuyển tiếp</span>
                  <div class="input-unit">
                    <input v-model.number="formNumbers.transition_days" type="number" min="1" />
                    <span>ngày</span>
                  </div>
                </label>
                <label v-if="needsField('owner_confirm_required')" class="param-field-check">
                  <input v-model="formBooleans.owner_confirm_required" type="checkbox" />
                  <span>Bắt buộc chủ sân xác nhận trước khi admin hoàn tiền</span>
                </label>
                <label v-if="needsField('admin_can_complete_without_owner')" class="param-field-check">
                  <input v-model="formBooleans.admin_can_complete_without_owner" type="checkbox" />
                  <span>Cho phép admin hoàn tất khi chủ sân chưa xác nhận</span>
                </label>
              </div>
            </div>

            <!-- Preview text -->
            <div class="preview-banner">
              <p class="preview-label">Câu tóm tắt nghiệp vụ</p>
              <p>{{ previewSummary }}</p>
            </div>

            <!-- Tên quy tắc -->
            <div class="name-fields">
              <label class="param-field">
                <span>Tên hiển thị của quy tắc <em>(tuỳ chỉnh)</em></span>
                <input v-model.trim="ruleForm.rule_name" type="text" :placeholder="getRuleTypeLabel(ruleForm.rule_type)" />
              </label>
            </div>
          </template>
        </div>

        <!-- Wizard error -->
        <div v-if="wizardError" class="alert error modal-alert">
          <AppIcon name="alert" size="14" />
          {{ wizardError }}
        </div>

        <!-- Wizard footer -->
        <div class="modal-foot">
          <button class="btn secondary" @click="wizardStep === 1 ? closeWizard() : (wizardStep = 1)">
            {{ wizardStep === 1 ? 'Hủy' : '← Quay lại' }}
          </button>
          <div class="modal-foot-right">
            <template v-if="wizardStep === 1">
              <button class="btn primary" @click="goStep2" :disabled="!ruleForm.action_code">
                Tiếp tục →
              </button>
            </template>
            <template v-else>
              <button class="btn secondary" @click="saveRule(false)" :disabled="savingRule || !ruleForm.rule_type">
                {{ savingRule ? 'Đang lưu...' : 'Lưu nháp' }}
              </button>
              <button class="btn primary" @click="saveRule(true)" :disabled="savingRule || !ruleForm.rule_type">
                <AppIcon name="check" size="15" />
                {{ savingRule ? 'Đang lưu...' : 'Lưu & Bật ngay' }}
              </button>
            </template>
          </div>
        </div>

      </div>
    </div>

    <!-- ═══════════════════════════════
         CONFIG MODALS
    ════════════════════════════════════ -->
    <div v-if="cancelRefundModal" class="modal-bg" @click.self="closeConfigModals">
      <form class="modal-box wide" @submit.prevent="saveCancelRefundConfig">
        <div class="modal-head">
          <div>
            <p class="eyebrow">Hủy & hoàn booking</p>
            <h3>Sửa bảng mốc hủy và hoàn</h3>
          </div>
          <button class="icon-btn" type="button" @click="closeConfigModals"><AppIcon name="x" size="18" /></button>
        </div>
        <div class="modal-body">
          <div class="edit-toolbar">
            <button class="btn secondary" type="button" @click="addCancelRefundTier">
              <AppIcon name="plus" size="15" />
              Thêm mốc
            </button>
          </div>
          <article v-for="tier in cancelRefundDraft" :key="tier.key" class="config-edit-row">
            <div class="config-edit-grid cancel-grid">
              <label>
                Từ giờ
                <input v-model.number="tier.from_hours" type="number" min="0" step="0.5" />
              </label>
              <label>
                Đến dưới giờ
                <input v-model="tier.to_hours" type="number" min="0" step="0.5" placeholder="Không giới hạn" />
              </label>
              <label>
                Cho hủy
                <select v-model="tier.allow_cancel">
                  <option :value="true">Có</option>
                  <option :value="false">Không</option>
                </select>
              </label>
              <label>
                Hoàn %
                <input v-model.number="tier.refund_percent" type="number" min="0" max="100" />
              </label>
              <label class="check-row">
                <input v-model="tier.require_owner_confirm" type="checkbox" />
                Chủ sân xác nhận
              </label>
              <label class="check-row">
                <input v-model="tier.require_admin_confirm" type="checkbox" />
                Admin hoàn tất
              </label>
              <label class="wide-field">
                Nội dung cho khách
                <input v-model.trim="tier.customer_message" maxlength="500" placeholder="Ví dụ: Yêu cầu hủy nằm ngoài khung được hoàn tiền." />
              </label>
              <button class="icon-btn danger" type="button" title="Xóa mốc" :disabled="cancelRefundDraft.length <= 2" @click="removeCancelRefundTier(tier.key)">
                <AppIcon name="trash" size="15" />
              </button>
            </div>
          </article>
          <div class="preview-banner" :class="{ invalid: cancelRefundValidation }">
            <p class="preview-label">{{ cancelRefundValidation ? 'Cần kiểm tra lại' : 'Bản xem trước' }}</p>
            <p>{{ cancelRefundValidation || cancelRefundPreview }}</p>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn secondary" type="button" @click="closeConfigModals">Hủy</button>
          <button class="btn primary" type="submit" :disabled="savingRule || !!cancelRefundValidation">
            {{ savingRule ? 'Đang lưu...' : 'Lưu bảng mốc' }}
          </button>
        </div>
      </form>
    </div>



    <div v-if="genericConfigModal" class="modal-bg" @click.self="closeConfigModals">
      <form class="modal-box wide" @submit.prevent="saveGenericConfig">
        <div class="modal-head">
          <div>
            <p class="eyebrow">{{ genericConfigTitle }}</p>
            <h3>{{ genericConfigHeading }}</h3>
          </div>
          <button class="icon-btn" type="button" @click="closeConfigModals"><AppIcon name="x" size="18" /></button>
        </div>
        <div class="modal-body">
          <div class="config-edit-grid generic-grid">
            <label v-for="field in genericConfigFields" :key="field.key" :class="{ 'wide-field': field.type === 'textarea' || field.type === 'array' }">
              <span>{{ field.label }}</span>
              <template v-if="field.type === 'boolean'">
                <span class="switch-row">
                  <input v-model="genericConfigDraft[field.key]" type="checkbox" />
                  {{ genericConfigDraft[field.key] ? 'Có' : 'Không' }}
                </span>
              </template>
              <textarea v-else-if="field.type === 'textarea'" v-model.trim="genericConfigDraft[field.key]" rows="3"></textarea>
              <input v-else-if="field.type === 'number'" v-model.number="genericConfigDraft[field.key]" type="number" min="0" />
              <input v-else v-model.trim="genericConfigDraft[field.key]" type="text" :placeholder="field.placeholder || ''" />
            </label>
          </div>
          <div class="preview-banner" :class="{ invalid: genericConfigValidation }">
            <p class="preview-label">{{ genericConfigValidation ? 'Cần kiểm tra lại' : 'Bản xem trước' }}</p>
            <p>{{ genericConfigValidation || 'Cấu hình sẽ được lưu vào rule của chính sách hiện tại.' }}</p>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn secondary" type="button" @click="closeConfigModals">Hủy</button>
          <button class="btn primary" type="submit" :disabled="savingRule || !!genericConfigValidation">
            {{ savingRule ? 'Đang lưu...' : 'Lưu cấu hình' }}
          </button>
        </div>
      </form>
    </div>

    <!-- ═══════════════════════════════
         CONFIRM DELETE MODAL
    ════════════════════════════════════ -->
    <div v-if="confirmDelete.show" class="modal-bg" @click.self="confirmDelete.show = false">
      <div class="modal-box confirm-box">
        <div class="confirm-icon">
          <AppIcon name="trash" size="24" />
        </div>
        <h3>Xóa bản nháp này?</h3>
        <p>Chính sách <strong>{{ policy?.title }}</strong> và tất cả quy tắc liên quan sẽ bị xóa vĩnh viễn.</p>
        <p class="confirm-warn">Không thể hoàn tác.</p>
        <div class="confirm-actions">
          <button class="btn secondary" @click="confirmDelete.show = false">Hủy</button>
          <button class="btn danger" @click="deletePolicy" :disabled="saving">
            {{ saving ? 'Đang xóa...' : 'Xóa chính sách' }}
          </button>
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
  getStatusBadgeClass,
  getStatusLabel,
} from '../../utils/labelMaps.js';

import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import AdminModerationConfig from '../../components/admin/AdminModerationConfig.vue';

export default {
  name: 'AdminPolicyDetail',
  components: { AppIcon, QuillEditor, AdminModerationConfig },
  data() {
    return {
      loading: true,
      saving: false,
      savingContent: false,
      savingRule: false,
      error: '',
      success: '',
      wizardError: '',
      loadingOptions: false,
      activeTab: this.$route.query.tab || 'overview',
      detail: null,
      policy: null,
      rules: [],
      venueRules: [],
      auditLogs: [],
      actionOptions: [],
      ruleTemplates: [],
      contentDraft: '',
      showWizard: false,
      wizardStep: 1,
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
      cancelRefundModal: false,
      reportModal: false,
      genericConfigModal: false,
      cancelRefundDraft: [],
      reportThresholdDraft: [],
      genericConfigDraft: {},
      confirmDelete: { show: false },
      tabs: [],
    };
  },
  computed: {
    policyType() {
      return this.policy?.policy_type || this.policy?.type || 'general';
    },
    filteredActions() {
      return this.actionOptions.filter((a) => (a.policy_types || []).includes(this.policyType));
    },
    filteredTemplates() {
      return this.ruleTemplates.filter((t) => {
        const typeOk = (t.policy_types || []).includes(this.policyType);
        const actionOk = !this.ruleForm.action_code || (t.action_codes || []).includes(this.ruleForm.action_code);
        return typeOk && actionOk;
      });
    },
    selectedTemplate() {
      return this.ruleTemplates.find((t) => t.rule_type === this.ruleForm.rule_type);
    },
    hasConfigFields() {
      if (!this.ruleForm.rule_type) return false;
      const all = ['hours_before_start', 'refund_percent', 'days_before_due', 'overdue_days', 'report_count', 'unique_reporters', 'window_days', 'transition_days', 'owner_confirm_required', 'admin_can_complete_without_owner'];
      return all.some((f) => this.needsField(f));
    },
    previewSummary() {
      const t = this.ruleForm.rule_type;
      const n = this.formNumbers;
      const b = this.formBooleans;
      const m = {
        cancel_before_hours: `Khách chỉ được hủy booking trước giờ chơi tối thiểu ${n.hours_before_start} giờ.`,
        refund_percent_by_cancel_time: `Hủy trước ${n.hours_before_start} giờ → hoàn tối thiểu ${n.refund_percent}% số tiền đã thanh toán.`,
        owner_confirm_required_before_admin_transfer: b.owner_confirm_required ? 'Admin chỉ được hoàn tiền sau khi chủ sân đã xác nhận yêu cầu.' : 'Quy tắc không bắt buộc chủ sân xác nhận.',
        platform_fee_overdue_warning: `Nhắc chủ sân khi phí nền tảng sắp/quá hạn trong ${n.days_before_due} ngày.`,
        platform_fee_overdue_lock: `Quá hạn phí ${n.overdue_days} ngày → hệ thống giới hạn quyền cụm sân.`,
        report_threshold_requires_review: `${n.report_count} báo cáo bởi ${n.unique_reporters} người trong ${n.window_days} ngày → đưa vào chờ kiểm duyệt.`,
        contract_signing_required: 'Hợp đồng chỉ có hiệu lực khi đã có đủ chữ ký.',
        partner_termination_transition_30_days: `Thu quyền chủ sân sau ${n.transition_days} ngày chuyển tiếp.`,
        terms_acceptance_required: 'Bắt buộc chấp nhận điều khoản trước khi sử dụng dịch vụ.',
        venue_policy_override_limit: 'Chính sách riêng của sân không được vượt quá khung hệ thống đã đặt.',
        partner_application_approve_requires_contract: 'Duyệt hồ sơ đối tác xong phải tạo hợp đồng trước khi chính thức..',
      };
      return m[t] || this.selectedTemplate?.business_summary_vi || 'Chọn loại quy tắc để xem mô tả nghiệp vụ.';
    },
    canEditConfig() {
      return this.policy?.status !== 'active';
    },
    configurationType() {
      return this.detail?.configuration_type || this.policy?.configuration_type || 'text_only';
    },
    configurationSummary() {
      return this.detail?.business_summary || this.policy?.business_summary_vi || this.policy?.business_summary || 'Cấu hình cách hệ thống tự xử lý chính sách.';
    },
    cancelRefundConfiguration() {
      return this.detail?.cancel_refund_tiers || null;
    },
    moderationConfiguration() {
      return this.detail?.moderation_thresholds || this.detail?.report_configuration || null;
    },
    genericConfiguration() {
      const byType = {
        platform_fee: this.policy?.configuration_data,
        permission_revoke: this.policy?.configuration_data || this.detail?.permission_revoke_configuration?.config,
        partner_contract: this.policy?.configuration_data || this.detail?.partner_contract_configuration?.config,
        account_policy: this.policy?.configuration_data || this.detail?.account_policy_configuration?.config,
      };
      return byType[this.configurationType] || {};
    },
    configCount() {
      if (this.configurationType === 'cancel_refund_tiers') return this.cancelRefundConfiguration?.tiers?.length || this.rules.length;
      if (this.configurationType === 'moderation_thresholds') return this.reportThresholdRows.length || this.rules.length;
      if (this.configurationType === 'text_only') return 0;
      return Object.keys(this.genericConfiguration || {}).length;
    },
    cancelRefundRows() {
      return (this.cancelRefundConfiguration?.tiers || []).map((tier, index) => ({
        key: tier.key || `tier_${index}`,
        label: tier.label || this.dynamicTierLabel(tier),
        condition: tier.condition_label || this.dynamicTierCondition(tier),
        result: this.cancelRefundResult(tier),
        allow_cancel: !!tier.allow_cancel,
        confirmation: this.confirmationLabel(tier),
      }));
    },
    reportTargetOptions() {
      return this.moderationConfiguration?.target_type_options || [
        { value: 'post', label: 'Bài viết' },
        { value: 'comment', label: 'Bình luận' },
        { value: 'user', label: 'Tài khoản' },
      ];
    },
    reportThresholdRows() {
      if (this.moderationConfiguration?.thresholds?.length) {
        return this.moderationConfiguration.thresholds.map((threshold, index) => this.normalizeThresholdShape(threshold, index));
      }

      const config = this.moderationConfiguration?.config;
      if (!config) return [];

      return [this.normalizeThresholdShape({
        key: 'legacy',
        object_type: this.moderationConfiguration?.target_type || 'post',
        object_type_label: this.moderationConfiguration?.target_type_label || 'Nội dung',
        min_reports: config.minimum_reports,
        min_distinct_reporters: config.minimum_unique_reporters,
        within_days: config.window_days,
        action: (config.actions || [])[0] || 'notify_admin',
        action_label: (this.moderationConfiguration?.action_labels || []).join(', ') || 'Thông báo admin',
        notify_admin: (config.actions || []).includes('notify_admin'),
        notify_reported_user: false,
        is_active: true,
      }, 0)];
    },
    genericConfigTitle() {
      const labels = {
        platform_fee: 'Phí nền tảng',
        permission_revoke: 'Thu hồi quyền',
        partner_contract: 'Hợp đồng đối tác',
        account_policy: 'Tài khoản',
      };
      return labels[this.configurationType] || 'Cấu hình';
    },
    genericConfigHeading() {
      const labels = {
        platform_fee: 'Quy trình nhắc nhở và xử lý nợ phí',
        permission_revoke: 'Quy định thu hồi quyền sử dụng',
        partner_contract: 'Quy định xử lý hợp đồng đối tác',
        account_policy: 'Quy định xử lý vi phạm tài khoản',
      };
      return labels[this.configurationType] || 'Cấu hình xử lý';
    },
    genericConfigSummary() {
      const config = this.genericConfiguration || {};
      return config.summary_vi || config.message_template || this.detail?.permission_revoke_configuration?.summary || this.detail?.partner_contract_configuration?.summary || this.detail?.account_policy_configuration?.summary || 'Cấu hình này được lưu dưới dạng rule của chính sách.';
    },
    genericConfigFields() {
      const fields = {
        platform_fee: [
          ['remind_before_days', 'Thông báo trước hạn (ngày)', 'number'],
          ['warn_overdue_days', 'Thông báo sau quá hạn (ngày)', 'number'],
          ['restrict_overdue_days', 'Hạn chế quyền sau quá hạn (ngày)', 'number'],
          ['termination_review_overdue_days', 'Chuyển Admin xử lý chấm dứt sau (ngày)', 'number'],
        ],
        permission_revoke: [
          ['target_type', 'Đối tượng', 'text'],
          ['reason_type', 'Lý do', 'text'],
          ['revoke_after_days', 'Thu hồi sau vi phạm', 'number'],
          ['revoke_duration_days', 'Số ngày thu hồi', 'number'],
          ['permissions_to_revoke', 'Quyền bị thu hồi', 'array', 'Ví dụ: manage_venue_cluster'],
          ['requires_admin_confirm', 'Yêu cầu admin xác nhận', 'boolean'],
          ['notify_target', 'Thông báo đối tượng', 'boolean'],
          ['notify_admin', 'Thông báo admin', 'boolean'],
          ['message_template', 'Nội dung thông báo', 'textarea'],
        ],
        partner_contract: [
          ['warn_before_days', 'Thông báo trước khi hết hạn (ngày)', 'number'],
          ['lock_after_days', 'Khóa cụm sân sau quá hạn (ngày)', 'number'],
          ['revoke_after_days', 'Chuyển Admin xử lý thu hồi quyền (ngày)', 'number'],
        ],
        account_policy: [
          ['warnings_to_restrict', 'Số cảnh báo trước khi hạn chế', 'number'],
          ['violations_to_lock', 'Số vi phạm trước khi khóa', 'number'],
          ['lock_days', 'Số ngày khóa tài khoản', 'number'],
          ['admin_confirm', 'Yêu cầu admin xác nhận', 'boolean'],
          ['notify_user', 'Thông báo người dùng', 'boolean'],
          ['default_reason', 'Lý do mặc định', 'textarea'],
        ],
      };

      return (fields[this.configurationType] || []).map(([key, label, type, placeholder]) => ({ key, label, type, placeholder }));
    },
    genericConfigEntries() {
      const config = this.genericConfiguration || {};
      return this.genericConfigFields.map((field) => ({
        key: field.key,
        label: field.label,
        value: this.formatConfigValue(config[field.key], field.type),
      }));
    },
    cancelRefundPreview() {
      if (!this.cancelRefundDraft.length) return 'Thêm ít nhất 2 mốc thời gian để lưu chính sách.';
      return this.sortedCancelRefundDraft.map((tier) => `${this.dynamicTierLabel(tier)}: ${this.cancelRefundResult(tier).toLowerCase()}`).join('. ') + '.';
    },
    sortedCancelRefundDraft() {
      return [...this.cancelRefundDraft].sort((a, b) => Number(a.from_hours || 0) - Number(b.from_hours || 0));
    },
    cancelRefundValidation() {
      if (this.cancelRefundDraft.length < 2) return 'Bảng mốc cần ít nhất 2 dòng.';
      const sorted = this.sortedCancelRefundDraft.map((tier) => ({
        from: Number(tier.from_hours),
        to: tier.to_hours === '' || tier.to_hours === null || tier.to_hours === undefined ? null : Number(tier.to_hours),
        allowCancel: !!tier.allow_cancel,
        refundPercent: Number(tier.refund_percent),
      }));
      if (sorted.some((tier) => Number.isNaN(tier.from) || tier.from < 0)) return 'Giờ bắt đầu phải lớn hơn hoặc bằng 0.';
      if (sorted.some((tier) => tier.to !== null && (Number.isNaN(tier.to) || tier.to <= tier.from))) return 'Giờ kết thúc phải lớn hơn giờ bắt đầu.';
      if (sorted.some((tier) => Number.isNaN(tier.refundPercent) || tier.refundPercent < 0 || tier.refundPercent > 100)) return 'Tỷ lệ hoàn phải nằm trong khoảng 0-100%.';
      if (sorted.some((tier) => !tier.allowCancel && tier.refundPercent !== 0)) return 'Mốc không cho hủy phải có tỷ lệ hoàn bằng 0%.';
      if (sorted[0]?.from !== 0) return 'Bảng mốc phải bắt đầu từ 0 giờ.';
      for (let index = 0; index < sorted.length; index += 1) {
        const current = sorted[index];
        const next = sorted[index + 1];
        if (next && (current.to === null || current.to !== next.from)) return 'Các mốc phải liền nhau, không chồng hoặc hở khoảng.';
        if (!next && current.to !== null) return 'Mốc cuối phải để trống giờ kết thúc để phủ đến vô hạn.';
      }
      return '';
    },
    reportPreview() {
      if (!this.reportThresholdDraft.length) return 'Thêm ít nhất một ngưỡng báo cáo.';
      return this.reportThresholdDraft.map((threshold) => {
        const action = this.reportActionOptionsFor(threshold.object_type).find((item) => item.value === threshold.action)?.label || threshold.action;
        return `${this.targetTypeLabel(threshold.object_type)} đạt ${threshold.min_reports} báo cáo bởi ${threshold.min_distinct_reporters} người trong ${threshold.within_days} ngày: ${action}`;
      }).join('. ') + '.';
    },
    reportValidation() {
      if (!this.reportThresholdDraft.length) return 'Vui lòng thêm ít nhất một ngưỡng báo cáo.';
      for (const [index, threshold] of this.reportThresholdDraft.entries()) {
        if (Number(threshold.min_reports) < 1 || Number(threshold.min_distinct_reporters) < 1 || Number(threshold.within_days) < 1) {
          return `Dòng ${index + 1}: các ngưỡng phải lớn hơn 0.`;
        }
        if (Number(threshold.min_distinct_reporters) > Number(threshold.min_reports)) {
          return `Dòng ${index + 1}: số người báo cáo không được lớn hơn số báo cáo.`;
        }
        if (!this.reportActionOptionsFor(threshold.object_type).some((option) => option.value === threshold.action)) {
          return `Dòng ${index + 1}: hành động không phù hợp với đối tượng.`;
        }
      }
      return '';
    },
    genericConfigValidation() {
      if (!this.genericConfigModal) return '';
      if (this.configurationType === 'platform_fee') {
        const order = ['warn_overdue_days', 'restrict_overdue_days', 'lock_overdue_days', 'termination_review_overdue_days'];
        for (const key of ['remind_before_days', ...order]) {
          if (Number(this.genericConfigDraft[key]) < 0) return 'Các mốc ngày phải lớn hơn hoặc bằng 0.';
        }
        for (let index = 0; index < order.length - 1; index += 1) {
          if (Number(this.genericConfigDraft[order[index]]) > Number(this.genericConfigDraft[order[index + 1]])) {
            return 'Các mốc xử lý phí nền tảng phải tăng dần theo thời gian.';
          }
        }
      }
      if (this.configurationType === 'partner_contract' && Number(this.genericConfigDraft.lock_after_days) > Number(this.genericConfigDraft.revoke_after_days)) {
        return 'Ngày khóa cụm sân không được lớn hơn ngày thu hồi quyền.';
      }
      if (this.configurationType === 'account_policy' && Number(this.genericConfigDraft.warnings_to_restrict) > Number(this.genericConfigDraft.violations_to_lock)) {
        return 'Số cảnh báo hạn chế không được lớn hơn số vi phạm khóa tài khoản.';
      }
      return '';
    },
  },
  watch: {
    '$route.params.id'(newId, oldId) {
      if (newId && newId !== oldId) {
        this.loadDetail();
      }
    },
    activeTab(v) {
      this.$router.replace({ query: { ...this.$route.query, tab: v } }).catch(() => {});
    },
  },
  async mounted() {
    await Promise.all([this.loadDetail(), this.loadOptions()]);
  },
  methods: {
    getActionLabel, getPolicyTypeLabel, getRuleSummary, getRuleTypeLabel, getStatusBadgeClass, getStatusLabel,

    buildTabs() {
      const newTabs = [
        { key: 'overview', label: 'Tổng quan', icon: 'eye' },
        { key: 'content', label: 'Nội dung', icon: 'fileText' },
      ];
      if (this.configurationType !== 'text_only') {
        newTabs.push({ key: 'config', label: 'Cấu hình', icon: 'settings', count: this.configCount });
      }
      newTabs.push({ key: 'audit', label: 'Lịch sử', icon: 'history' });
      this.tabs = newTabs;
    },

    async loadDetail() {
      this.loading = true;
      this.error = '';
      try {
        const res = await adminPolicyService.show(this.$route.params.id);
        const d = res.data || {};
        this.detail = d;
        this.policy = d.policy_info || d.policy;
        this.rules = d.rules || [];
        this.venueRules = d.venue_rules || d.venue_overrides || [];
        this.auditLogs = d.audit_logs || [];
        this.contentDraft = this.policy?.content || '';
        this.buildTabs();
        this.activeTab = this.$route.query.tab || 'overview';
      } catch (e) {
        this.error = e.message || 'Không thể tải chi tiết chính sách.';
      } finally {
        this.loading = false;
      }
    },

    async loadOptions() {
      this.loadingOptions = true;
      try {
        const [a, t] = await Promise.all([
          adminPolicyService.actionCodes(),
          adminPolicyService.ruleTemplates(),
        ]);
        this.actionOptions = a.data || [];
        const tData = t.data;
        this.ruleTemplates = Array.isArray(tData) ? tData : (tData ? Object.values(tData) : []);
      } catch (e) {
        console.warn('Options load failed:', e.message);
      } finally {
        this.loadingOptions = false;
      }
    },

    async clonePolicy() {
      this.saving = true;
      try {
        const res = await adminPolicyService.cloneVersion(this.policy.id);
        this.$router.push({ name: 'admin-policy-detail', params: { id: res.data.id }, query: { tab: 'config' } });
      } catch (e) {
        this.error = e.message || 'Không thể tạo phiên bản mới.';
      } finally {
        this.saving = false;
      }
    },

    async publishPolicy() {
      this.saving = true;
      try {
        const res = await adminPolicyService.publish(this.policy.id);
        this.success = res.message || 'Đã áp dụng chính sách.';
        await this.loadDetail();
        this.autoHide();
      } catch (e) {
        this.error = e.message || 'Không thể áp dụng chính sách.';
      } finally {
        this.saving = false;
      }
    },

    async archivePolicy() {
      this.saving = true;
      try {
        const res = await adminPolicyService.updateStatus(this.policy.id, { status: 'archived', reason: 'Ngưng áp dụng.' });
        this.success = res.message || 'Đã ngưng áp dụng.';
        await this.loadDetail();
        this.autoHide();
      } catch (e) {
        this.error = e.message || 'Không thể ngưng áp dụng.';
      } finally {
        this.saving = false;
      }
    },

    async deletePolicy() {
      this.saving = true;
      try {
        await adminPolicyService.delete(this.policy.id);
        this.$router.push({ name: 'admin-policies' });
      } catch (e) {
        this.error = e.message || 'Không thể xóa chính sách.';
        this.confirmDelete.show = false;
      } finally {
        this.saving = false;
      }
    },

    async saveContent() {
      this.savingContent = true;
      this.error = '';
      try {
        const p = this.policy;
        const res = await adminPolicyService.update(p.id, {
          key: p.key, version: p.version, title: p.title,
          content: this.contentDraft, policy_type: p.policy_type,
          priority: p.priority || 0, is_overridable: !!p.is_overridable,
          require_reaccept: !!p.require_reaccept,
          effective_from: p.effective_from, effective_to: p.effective_to,
          change_summary: p.change_summary,
        });
        this.success = res.message || 'Đã lưu nội dung.';
        await this.loadDetail();
        this.autoHide();
      } catch (e) {
        this.error = e.message || 'Không thể lưu nội dung.';
      } finally {
        this.savingContent = false;
      }
    },

    openCancelRefundModal() {
      if (!this.canEditConfig) return;
      const tiers = this.cancelRefundConfiguration?.tiers?.length
        ? this.cancelRefundConfiguration.tiers
        : [
            this.defaultCancelRefundTier(0, 24, true, 50),
            this.defaultCancelRefundTier(24, null, true, 100),
          ];
      this.cancelRefundDraft = JSON.parse(JSON.stringify(tiers)).map((tier, index) => ({
        key: tier.key || `tier_${index}_${Date.now()}`,
        label: tier.label || '',
        from_hours: Number(tier.from_hours ?? 0),
        to_hours: tier.to_hours ?? '',
        allow_cancel: tier.allow_cancel !== false,
        refund_percent: Number(tier.refund_percent ?? 0),
        require_owner_confirm: tier.require_owner_confirm !== false,
        require_admin_confirm: tier.require_admin_confirm !== false,
        customer_message: tier.customer_message || '',
      }));
      this.cancelRefundModal = true;
    },

    addCancelRefundTier() {
      const sorted = this.sortedCancelRefundDraft;
      const last = sorted[sorted.length - 1];
      if (!last) {
        this.cancelRefundDraft = [this.defaultCancelRefundTier(0, null, true, 0)];
        return;
      }

      const splitAt = Number(last.from_hours || 0) + 24;
      last.to_hours = splitAt;
      sorted.push(this.defaultCancelRefundTier(splitAt, null, true, Number(last.refund_percent || 0)));
      this.cancelRefundDraft = sorted;
    },

    removeCancelRefundTier(key) {
      const sorted = this.sortedCancelRefundDraft;
      if (sorted.length <= 2) return;

      const index = sorted.findIndex((tier) => tier.key === key);
      if (index < 0) return;

      const removed = sorted[index];
      const previous = sorted[index - 1];
      const next = sorted[index + 1];
      if (previous) previous.to_hours = removed.to_hours;
      if (!previous && next) next.from_hours = 0;
      sorted.splice(index, 1);
      this.cancelRefundDraft = sorted;
    },

    async saveCancelRefundConfig() {
      if (this.cancelRefundValidation) return;
      this.savingRule = true;
      this.error = '';
      try {
        await adminPolicyService.saveCancelRefundTiers(this.policy.id, {
          tiers: this.sortedCancelRefundDraft.map((tier) => ({
            label: tier.label || this.dynamicTierLabel(tier),
            from_hours: Number(tier.from_hours),
            to_hours: tier.to_hours === '' || tier.to_hours === null || tier.to_hours === undefined ? null : Number(tier.to_hours),
            allow_cancel: !!tier.allow_cancel,
            refund_percent: Number(tier.refund_percent),
            require_owner_confirm: !!tier.require_owner_confirm,
            require_admin_confirm: !!tier.require_admin_confirm,
            customer_message: tier.customer_message || '',
          })),
        });
        this.success = 'Đã lưu bảng mốc hủy & hoàn booking.';
        this.closeConfigModals();
        await this.loadDetail();
        this.activeTab = 'config';
        this.autoHide();
      } catch (e) {
        this.error = e.message || 'Không thể lưu bảng mốc hủy & hoàn.';
      } finally {
        this.savingRule = false;
      }
    },

    openReportModal() {
      if (!this.canEditConfig) return;
      this.reportThresholdDraft = (this.reportThresholdRows.length ? this.reportThresholdRows : [this.defaultReportThreshold()])
        .map((threshold, index) => this.normalizeThresholdShape(threshold, index));
      this.reportModal = true;
    },

    addReportThreshold() {
      this.reportThresholdDraft.push(this.defaultReportThreshold());
    },

    removeReportThreshold(key) {
      if (this.reportThresholdDraft.length <= 1) return;
      this.reportThresholdDraft = this.reportThresholdDraft.filter((threshold) => threshold.key !== key);
    },

    async saveReportConfig() {
      if (this.reportValidation) return;
      this.savingRule = true;
      this.error = '';
      try {
        await adminPolicyService.saveModerationThresholds(this.policy.id, {
          thresholds: this.reportThresholdDraft.map((threshold) => ({
            key: threshold.key,
            object_type: threshold.object_type,
            min_reports: Number(threshold.min_reports),
            min_distinct_reporters: Number(threshold.min_distinct_reporters),
            within_days: Number(threshold.within_days),
            action: threshold.action,
            notify_admin: !!threshold.notify_admin,
            notify_reported_user: !!threshold.notify_reported_user,
            is_active: threshold.is_active !== false,
          })),
        });
        this.success = 'Đã lưu ngưỡng báo cáo.';
        this.closeConfigModals();
        await this.loadDetail();
        this.activeTab = 'config';
        this.autoHide();
      } catch (e) {
        this.error = e.message || 'Không thể lưu ngưỡng báo cáo.';
      } finally {
        this.savingRule = false;
      }
    },

    openGenericConfigModal() {
      if (!this.canEditConfig) return;
      const draft = { ...(this.genericConfiguration || {}) };
      this.genericConfigFields.forEach((field) => {
        if (field.type === 'array' && Array.isArray(draft[field.key])) {
          draft[field.key] = draft[field.key].join(', ');
        }
        if (field.type === 'boolean') {
          draft[field.key] = !!draft[field.key];
        }
      });
      this.genericConfigDraft = draft;
      this.genericConfigModal = true;
    },

    async saveGenericConfig() {
      if (this.genericConfigValidation) return;
      this.savingRule = true;
      this.error = '';
      try {
        const configurationData = {};
        this.genericConfigFields.forEach((field) => {
          const value = this.genericConfigDraft[field.key];
          if (field.type === 'number') {
            configurationData[field.key] = value === '' || value === undefined ? null : Number(value);
            return;
          }
          if (field.type === 'boolean') {
            configurationData[field.key] = !!value;
            return;
          }
          if (field.type === 'array') {
            configurationData[field.key] = String(value || '')
              .split(',')
              .map((item) => item.trim())
              .filter(Boolean);
            return;
          }
          configurationData[field.key] = value ?? '';
        });

        if (this.configurationType === 'platform_fee') {
          configurationData.lock_overdue_days = configurationData.restrict_overdue_days || 0;
          configurationData.notify_owner = true;
          configurationData.notify_admin = true;
          configurationData.message_template = 'Cụm sân của bạn đã đến hoặc quá hạn phí nền tảng.';
        } else if (this.configurationType === 'partner_contract') {
          configurationData.requires_admin_confirm = true;
          configurationData.notify_target = true;
          configurationData.notify_admin = true;
        }

        await adminPolicyService.updatePolicyConfiguration(this.policy.id, {
          configuration_data: configurationData,
        });
        this.success = 'Đã lưu cấu hình nghiệp vụ chính sách.';
        this.closeConfigModals();
        await this.loadDetail();
        this.activeTab = 'config';
        this.autoHide();
      } catch (e) {
        this.error = e.message || 'Không thể lưu cấu hình chính sách.';
      } finally {
        this.savingRule = false;
      }
    },

    closeConfigModals() {
      this.cancelRefundModal = false;
      this.reportModal = false;
      this.genericConfigModal = false;
    },

    defaultCancelRefundTier(fromHours = 0, toHours = null, allowCancel = true, refundPercent = 0) {
      return {
        key: `tier_${Date.now()}_${Math.random().toString(16).slice(2)}`,
        label: '',
        from_hours: fromHours,
        to_hours: toHours ?? '',
        allow_cancel: allowCancel,
        refund_percent: refundPercent,
        require_owner_confirm: true,
        require_admin_confirm: true,
        customer_message: '',
      };
    },

    defaultReportThreshold() {
      const objectType = this.reportTargetOptions[0]?.value || 'post';
      const action = this.reportActionOptionsFor(objectType)[0]?.value || 'notify_admin';
      return {
        key: `threshold_${Date.now()}_${Math.random().toString(16).slice(2)}`,
        object_type: objectType,
        object_type_label: this.targetTypeLabel(objectType),
        min_reports: 5,
        min_distinct_reporters: 2,
        within_days: 14,
        action,
        action_label: this.reportActionOptionsFor(objectType)[0]?.label || action,
        notify_admin: true,
        notify_reported_user: false,
        is_active: true,
      };
    },

    normalizeThresholdShape(threshold, index = 0) {
      const objectType = threshold.object_type || threshold.target_type || 'post';
      const actions = this.reportActionOptionsFor(objectType);
      const action = threshold.action || actions[0]?.value || 'notify_admin';
      return {
        key: threshold.key || `threshold_${index}_${Date.now()}`,
        object_type: objectType,
        object_type_label: threshold.object_type_label || this.targetTypeLabel(objectType),
        min_reports: Number(threshold.min_reports || threshold.minimum_reports || 1),
        min_distinct_reporters: Number(threshold.min_distinct_reporters || threshold.minimum_unique_reporters || 1),
        within_days: Number(threshold.within_days || threshold.window_days || 1),
        action,
        action_label: threshold.action_label || actions.find((item) => item.value === action)?.label || action,
        notify_admin: threshold.notify_admin !== false,
        notify_reported_user: !!threshold.notify_reported_user,
        is_active: threshold.is_active !== false,
      };
    },

    normalizeThresholdAction(threshold) {
      const options = this.reportActionOptionsFor(threshold.object_type);
      if (!options.some((option) => option.value === threshold.action)) {
        threshold.action = options[0]?.value || '';
      }
      threshold.object_type_label = this.targetTypeLabel(threshold.object_type);
      threshold.action_label = options.find((option) => option.value === threshold.action)?.label || threshold.action;
    },

    reportActionOptionsFor(objectType) {
      const options = this.moderationConfiguration?.action_options || [];
      if (Array.isArray(options)) return options;
      return options[objectType] || options.post || [
        { value: 'notify_admin', label: 'Thông báo admin' },
        { value: 'mark_pending_review', label: 'Đưa vào chờ duyệt' },
        { value: 'hide_content', label: 'Ẩn nội dung' },
      ];
    },

    targetTypeLabel(value) {
      return this.reportTargetOptions.find((option) => option.value === value)?.label || value || 'Nội dung';
    },

    dynamicTierLabel(tier) {
      const from = Number(tier.from_hours || 0);
      const to = tier.to_hours === '' || tier.to_hours === null || tier.to_hours === undefined ? null : Number(tier.to_hours);
      if (from === 0 && to !== null) return `Trong ${to} giờ trước giờ chơi`;
      if (to === null) return `Từ ${from} giờ trở lên`;
      return `Từ ${from} đến dưới ${to} giờ`;
    },

    dynamicTierCondition(tier) {
      const from = Number(tier.from_hours || 0);
      const to = tier.to_hours === '' || tier.to_hours === null || tier.to_hours === undefined ? null : Number(tier.to_hours);
      if (to === null) return `Hủy trước giờ chơi từ ${from} giờ trở lên`;
      return `Hủy trước giờ chơi từ ${from} đến dưới ${to} giờ`;
    },

    cancelRefundResult(tier) {
      if (!tier.allow_cancel) return 'Không cho hủy';
      const percent = Number(tier.refund_percent || 0);
      return percent > 0 ? `Cho hủy, hoàn ${percent}%` : 'Cho hủy, không hoàn tiền';
    },

    confirmationLabel(tier) {
      const labels = [];
      if (tier.require_owner_confirm) labels.push('Chủ sân');
      if (tier.require_admin_confirm) labels.push('Admin');
      return labels.length ? labels.join(' + ') : 'Tự động';
    },

    formatConfigValue(value, type) {
      if (type === 'boolean') return value ? 'Có' : 'Không';
      if (type === 'array') return Array.isArray(value) ? value.join(', ') : (value || '-');
      if (value === null || value === undefined || value === '') return '-';
      return String(value);
    },

    openRuleWizard() {
      this.ruleForm = { action_code: '', rule_type: '', rule_name: '' };
      this.wizardStep = 1;
      this.wizardError = '';
      this.showWizard = true;
    },

    closeWizard() {
      this.showWizard = false;
      this.wizardError = '';
    },

    goStep2() {
      if (!this.ruleForm.action_code) { this.wizardError = 'Vui lòng chọn tình huống.'; return; }
      this.wizardError = '';
      this.ruleForm.rule_type = '';
      this.ruleForm.rule_name = '';
      this.wizardStep = 2;
    },

    selectTemplate(tpl) {
      this.ruleForm.rule_type = tpl.rule_type;
      this.ruleForm.rule_name = tpl.label || getRuleTypeLabel(tpl.rule_type);
    },

    needsField(field) {
      const m = {
        cancel_before_hours: ['hours_before_start'],
        refund_percent_by_cancel_time: ['hours_before_start', 'refund_percent'],
        owner_confirm_required_before_admin_transfer: ['owner_confirm_required', 'admin_can_complete_without_owner'],
        platform_fee_overdue_warning: ['days_before_due'],
        platform_fee_overdue_lock: ['overdue_days'],
        report_threshold_requires_review: ['report_count', 'unique_reporters', 'window_days'],
        partner_termination_transition_30_days: ['transition_days'],
      };
      return (m[this.ruleForm.rule_type] || []).includes(field);
    },

    buildPayload(active) {
      const n = this.formNumbers;
      const b = this.formBooleans;
      const condition = {};
      const result = {};
      const t = this.ruleForm.rule_type;
      let decisionKey = this.selectedTemplate?.decision_key || null;

      if (t === 'cancel_before_hours') { condition.hours_before_start = { gte: +n.hours_before_start }; result.can_cancel = true; decisionKey = decisionKey || 'can_cancel'; }
      if (t === 'refund_percent_by_cancel_time') { condition.hours_before_start = { gte: +n.hours_before_start }; result.refund_percent = +n.refund_percent; result.owner_confirm_required = true; decisionKey = decisionKey || 'refund_percent'; }
      if (t === 'owner_confirm_required_before_admin_transfer') { result.owner_confirm_required = !!b.owner_confirm_required; result.admin_can_complete_without_owner = !!b.admin_can_complete_without_owner; decisionKey = decisionKey || 'owner_confirm_required'; }
      if (t === 'platform_fee_overdue_warning') { condition.days_before_due = +n.days_before_due; result.action = 'notify_owner'; decisionKey = decisionKey || 'platform_fee_warning'; }
      if (t === 'platform_fee_overdue_lock') { condition.overdue_days = { gte: +n.overdue_days }; result.action = 'limit_owner_access'; result.access_mode = 'limited'; decisionKey = decisionKey || 'owner_access_mode'; }
      if (t === 'report_threshold_requires_review') { condition.report_count = { gte: +n.report_count }; condition.unique_reporters = { gte: +n.unique_reporters }; condition.window_days = +n.window_days; result.action = 'mark_pending_review'; decisionKey = decisionKey || 'moderation_action'; }
      if (t === 'contract_signing_required') { condition.owner_signed = true; condition.sportgo_signed = true; result.contract_status = 'signed_active'; decisionKey = decisionKey || 'contract_status'; }
      if (t === 'partner_termination_transition_30_days') { result.transition_days = +n.transition_days; result.action = 'revoke_owner_access'; decisionKey = decisionKey || 'owner_access'; }
      if (t === 'terms_acceptance_required') { result.require_reaccept = true; decisionKey = decisionKey || 'require_reaccept'; }
      if (t === 'venue_policy_override_limit') { result.action = 'reject_if_below_system_minimum'; decisionKey = decisionKey || 'venue_policy_constraint'; }
      if (t === 'partner_application_approve_requires_contract') { result.action = 'generate_contract'; result.next_status = 'approved_pending_contract'; decisionKey = decisionKey || 'partner_application_status'; }

      return {
        action_code: this.ruleForm.action_code,
        rule_code: t,
        rule_name: this.ruleForm.rule_name || getRuleTypeLabel(t),
        rule_type: t,
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
      if (!this.ruleForm.rule_type) { this.wizardError = 'Vui lòng chọn loại quy tắc.'; return; }
      this.wizardError = '';
      this.savingRule = true;
      try {
        await adminPolicyService.addBinding(this.policy.id, {
          module: this.policyType,
          action_code: this.ruleForm.action_code,
          description: getActionLabel(this.ruleForm.action_code),
          is_active: true,
        });
        const res = await adminPolicyService.addRule(this.policy.id, this.buildPayload(active));
        this.success = res.message || 'Đã thêm quy tắc xử lý tự động.';
        this.closeWizard();
        await this.loadDetail();
        this.activeTab = 'rules';
        this.autoHide();
      } catch (e) {
        this.wizardError = e.message || 'Không thể lưu quy tắc.';
      } finally {
        this.savingRule = false;
      }
    },

    async toggleRule(rule) {
      try {
        const res = await adminPolicyService.toggleRule(this.policy.id, rule.id);
        this.success = res.message || 'Đã cập nhật trạng thái quy tắc.';
        await this.loadDetail();
        this.autoHide();
      } catch (e) {
        this.error = e.message || 'Không thể cập nhật quy tắc.';
      }
    },

    formatDate(v) {
      if (!v) return '—';
      return new Intl.DateTimeFormat('vi-VN').format(new Date(v));
    },
    formatDateTime(v) {
      if (!v) return '—';
      return new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(v));
    },
    autoHide() {
      setTimeout(() => { this.success = ''; this.error = ''; }, 4000);
    },
  },
};
</script>

<style scoped>
/* ── Reuse system variables ── */
.admin-page { display: flex; flex-direction: column; gap: 20px; }

/* ── Back link ── */
.back-link {
  background: none; border: none; padding: 0; cursor: pointer;
  color: #2563eb; font: inherit; font-size: 13px; font-weight: 600;
}
.back-link:hover { text-decoration: underline; }

/* ── Back action bar ── */
.back-action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.back-action-buttons { display: flex; align-items: center; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }
.policy-info-bar { margin-bottom: 12px; }
.policy-meta { margin: 4px 0 0; color: #64748b; font-size: 14px; }

.policy-title-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.policy-title-row h2 { margin: 0; }

/* ── eyebrow ── */
.eyebrow { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin: 0 0 4px; }

/* ── Badges ── */
.badge {
  display: inline-flex; align-items: center; padding: 3px 9px;
  border-radius: 999px; font-size: 12px; font-weight: 700;
}
.badge-version { background: #f1f5f9; color: #475569; }
.status-draft { background: #dbeafe; color: #1d4ed8; }
.status-active { background: #dcfce7; color: #15803d; }
.status-inactive, .status-archived { background: #f1f5f9; color: #64748b; }
.status-rejected { background: #fee2e2; color: #b91c1c; }
.status-pending { background: #fef9c3; color: #854d0e; }

/* ── Tabs ── */
.tab-nav {
  display: flex; gap: 2px;
  background: #f1f5f9; border-radius: 10px; padding: 4px;
  border: 1px solid #e2e8f0;
  overflow-x: auto;
}
.tab-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px; border-radius: 7px; border: none;
  background: transparent; color: #64748b;
  font: inherit; font-size: 13px; font-weight: 600; cursor: pointer;
  white-space: nowrap; transition: background 0.15s, color 0.15s;
}
.tab-btn:hover { background: #fff; color: #0f172a; }
.tab-btn.active { background: #fff; color: #16a34a; font-weight: 700; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.tab-count {
  background: #e2e8f0; color: #475569;
  border-radius: 999px; padding: 1px 6px; font-size: 11px;
}
.tab-btn.active .tab-count { background: #dcfce7; color: #15803d; }

/* ── Tab body ── */
.tab-body {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
  padding: 24px; display: flex; flex-direction: column; gap: 18px;
}

/* ── Section row ── */
.section-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.section-row strong { display: block; font-size: 16px; margin-bottom: 2px; }
.section-row p { margin: 0; color: #64748b; font-size: 14px; }

/* ── Meta grid ── */
.meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.meta-item { display: flex; flex-direction: column; gap: 4px; }
.meta-item.span2 { grid-column: span 2; }
.meta-label { font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; }
.meta-value { font-size: 14px; font-weight: 600; color: #0f172a; }
.text-green { color: #15803d; }

/* ── Notices ── */
.info-notice {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px; border-radius: 8px;
  background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;
  font-size: 14px; font-weight: 600;
}
.info-notice.warning { background: #fffbeb; color: #92400e; border-color: #fde68a; }

/* ── Content textarea ── */
.content-textarea {
  width: 100%; border: 1px solid #e2e8f0; border-radius: 8px;
  padding: 12px; font: inherit; font-size: 14px; color: #0f172a;
  background: #f8fafc; line-height: 1.7; resize: vertical; box-sizing: border-box;
}
.content-textarea:not([readonly]):focus { outline: none; border-color: #16a34a; background: #fff; }
.content-textarea[readonly] { color: #64748b; }

/* ── Empty state ── */
.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 8px; padding: 32px; color: #94a3b8; text-align: center;
}
.empty-state.small { padding: 20px; font-size: 14px; border: 1px dashed #e2e8f0; border-radius: 10px; }
.empty-state strong { color: #475569; }

/* ── Rule list ── */
.rule-list { display: flex; flex-direction: column; gap: 8px; }
.rule-row {
  display: flex; align-items: flex-start;
  border: 1px solid #e2e8f0; border-radius: 10px;
  background: #fff; transition: box-shadow 0.15s;
}
.rule-row:hover { box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
.rule-row.inactive { opacity: 0.55; }
.rule-row-main { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; width: 100%; padding: 14px 16px; }
.rule-name { font-weight: 700; font-size: 14px; color: #0f172a; margin: 0 0 3px; }
.rule-sub { margin: 0 0 6px; font-size: 13px; color: #64748b; line-height: 1.5; }
.rule-action-tag { font-size: 12px; color: #2563eb; font-weight: 600; background: #eff6ff; padding: 2px 8px; border-radius: 4px; }
.rule-controls { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

/* ── Audit list ── */
.audit-list { display: flex; flex-direction: column; }
.audit-row { display: flex; gap: 12px; padding-bottom: 18px; }
.audit-dot { width: 12px; height: 12px; border-radius: 50%; background: #16a34a; flex-shrink: 0; margin-top: 4px; border: 2px solid #dcfce7; }
.audit-content { flex: 1; }
.audit-content strong { display: block; font-size: 14px; margin-bottom: 2px; }
.audit-meta { display: block; font-size: 12px; color: #94a3b8; margin-bottom: 6px; }
.change-list { margin: 4px 0 0; padding-left: 14px; color: #64748b; font-size: 13px; }

/* ── Buttons ── */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 8px; border: 1px solid transparent;
  font: inherit; font-size: 14px; font-weight: 700; cursor: pointer;
  transition: background 0.15s, transform 0.1s;
  white-space: nowrap;
}
.btn:hover { transform: translateY(-1px); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.btn.primary { background: #16a34a; color: #fff; border-color: #16a34a; }
.btn.primary:hover { background: #15803d; }
.btn.secondary { background: #f1f5f9; color: #334155; border-color: #e2e8f0; }
.btn.secondary:hover { background: #e2e8f0; }
.btn.danger { background: #dc2626; color: #fff; border-color: #dc2626; }
.btn.danger:hover { background: #b91c1c; }
.btn.danger-ghost { background: transparent; color: #dc2626; border-color: #fecaca; }
.btn.danger-ghost:hover { background: #fff1f2; }

.icon-btn {
  width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;
  border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc;
  color: #475569; cursor: pointer; font: inherit; transition: background 0.15s;
}
.icon-btn:hover { background: #e2e8f0; }
.icon-btn:disabled { opacity: 0.45; cursor: not-allowed; }

/* ── Spinner ── */
.spinner { width: 28px; height: 28px; border: 3px solid #e2e8f0; border-top-color: #16a34a; border-radius: 50%; animation: spin 0.7s linear infinite; }
.spinner.small { width: 18px; height: 18px; border-width: 2px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Alert ── */
.alert { display: flex; align-items: center; gap: 8px; padding: 11px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; }
.alert.success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.alert.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.modal-alert { border-radius: 0; border-left: none; border-right: none; }

/* ── Table state ── */
.table-state { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 40px 20px; color: #64748b; }

/* ── Modal ── */
.modal-bg {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(15,23,42,0.55); backdrop-filter: blur(2px);
  display: flex; align-items: center; justify-content: center; padding: 20px;
}
.modal-box {
  width: min(680px, calc(100vw - 32px));
  max-height: calc(100vh - 40px);
  background: #fff; border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  display: flex; flex-direction: column; overflow: hidden;
  animation: fadeUp 0.2s ease;
}
@keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }

.modal-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
  padding: 20px 22px 16px; border-bottom: 1px solid #f1f5f9;
}
.modal-head h3 { margin: 4px 0 0; font-size: 18px; }

/* ── Step bar ── */
.step-bar {
  display: flex; align-items: center;
  padding: 14px 22px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
}
.step-item { display: flex; align-items: center; gap: 8px; flex: none; }
.step-dot {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 800;
  background: #e2e8f0; color: #94a3b8;
}
.step-item.active .step-dot { background: #16a34a; color: #fff; }
.step-item.done .step-dot { background: #16a34a; color: #fff; }
.step-item span:last-child { font-size: 13px; font-weight: 600; color: #94a3b8; }
.step-item.active span:last-child { color: #0f172a; }
.step-item.done span:last-child { color: #16a34a; }
.step-line { flex: 1; height: 2px; background: #e2e8f0; margin: 0 12px; }
.step-line.done { background: #16a34a; }

/* ── Modal body ── */
.modal-body { flex: 1; overflow-y: auto; padding: 20px 22px; display: flex; flex-direction: column; gap: 16px; }
.step-hint { margin: 0; color: #64748b; font-size: 14px; }
.field-label { margin: 0; font-size: 13px; font-weight: 800; color: #334155; text-transform: uppercase; letter-spacing: 0.04em; }

/* ── Option list ── */
.option-list { display: flex; flex-direction: column; gap: 8px; }
.option-list.compact .option-card { border-radius: 8px; }

.option-card {
  display: flex; align-items: flex-start;
  border: 1.5px solid #e2e8f0; border-radius: 10px;
  background: #fff; cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.option-card:hover { border-color: #86efac; }
.option-card.selected { border-color: #16a34a; background: #f0fdf4; }
.option-card input[type="radio"] { display: none; }
.option-card-body {
  display: flex; flex-direction: column; gap: 4px;
  padding: 12px 14px; flex: 1;
}
.option-card-body strong { font-size: 14px; color: #0f172a; }
.option-card-body span { font-size: 13px; color: #64748b; line-height: 1.5; }
.option-card-body code { font-size: 12px; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; width: fit-content; }

/* ── Selected context ── */
.selected-context {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px; border-radius: 8px;
  background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;
  font-size: 14px; font-weight: 600;
}
.link-btn {
  background: none; border: none; padding: 0; cursor: pointer;
  color: #2563eb; font: inherit; font-size: 13px; font-weight: 600; margin-left: auto;
}
.link-btn:hover { text-decoration: underline; }

/* ── Param section ── */
.param-section { display: flex; flex-direction: column; gap: 12px; }
.param-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.param-field { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 700; color: #334155; }
.param-field-check { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #334155; font-weight: 600; grid-column: span 2; }
.param-field-check input { margin-top: 2px; flex-shrink: 0; accent-color: #16a34a; }
.input-unit { display: flex; }
.input-unit input {
  flex: 1; border: 1px solid #e2e8f0; border-right: 0; border-radius: 8px 0 0 8px;
  padding: 9px 11px; font: inherit; font-size: 14px; color: #0f172a; background: #fff;
}
.input-unit input:focus { outline: none; border-color: #16a34a; }
.input-unit span {
  display: flex; align-items: center; padding: 0 12px;
  border: 1px solid #e2e8f0; border-radius: 0 8px 8px 0;
  background: #f8fafc; color: #64748b; font-size: 13px; font-weight: 700;
  white-space: nowrap;
}

/* ── Preview banner ── */
.preview-banner {
  padding: 14px 16px; border-radius: 8px;
  background: #f0fdf4; border: 1px solid #bbf7d0;
}
.preview-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #16a34a; margin: 0 0 6px; }
.preview-banner p:last-child { margin: 0; font-size: 14px; color: #0f172a; line-height: 1.6; font-weight: 500; }

/* ── Name fields ── */
.name-fields .param-field input {
  border: 1px solid #e2e8f0; border-radius: 8px; padding: 9px 11px;
  font: inherit; font-size: 14px; color: #0f172a;
}
.name-fields .param-field input:focus { outline: none; border-color: #16a34a; }

/* ── Modal foot ── */
.modal-foot {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 16px 22px; border-top: 1px solid #f1f5f9; background: #f8fafc;
}
.modal-foot-right { display: flex; gap: 8px; }

/* ── Confirm modal ── */
.confirm-box { width: min(420px, calc(100vw - 32px)); text-align: center; padding: 32px; gap: 10px; }
.confirm-icon { width: 52px; height: 52px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
.confirm-box h3 { margin: 0 0 8px; font-size: 18px; }
.confirm-box p { margin: 0; color: #64748b; font-size: 14px; line-height: 1.5; }
.confirm-warn { color: #dc2626 !important; font-weight: 700 !important; font-size: 13px !important; }
.confirm-actions { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }

/* ── Configuration cards ── */
.config-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 18px;
  background: #fff;
}
.config-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
}
.config-head h3 { margin: 4px 0 6px; font-size: 17px; color: #0f172a; }
.config-head p { margin: 0; color: #64748b; font-size: 14px; line-height: 1.5; }
.config-kicker {
  color: #16a34a;
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.config-table-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 10px; }
.config-table { width: 100%; border-collapse: collapse; min-width: 720px; }
.config-table th, .config-table td {
  padding: 12px 14px;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  font-size: 13px;
}
.config-table th { background: #f8fafc; color: #334155; font-weight: 800; }
.config-table tr:last-child td { border-bottom: 0; }
.modal-box.wide { width: min(980px, calc(100vw - 32px)); }
.edit-toolbar { display: flex; justify-content: flex-end; }
.config-edit-row {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 14px;
  background: #f8fafc;
}
.config-edit-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(120px, 1fr)) auto;
  gap: 12px;
  align-items: end;
}
.config-edit-grid.report-grid { grid-template-columns: 1.2fr repeat(4, minmax(100px, 1fr)) repeat(3, auto) auto; }
.config-edit-grid.generic-grid { grid-template-columns: repeat(2, minmax(220px, 1fr)); align-items: start; }
.config-edit-grid label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  font-weight: 800;
  color: #475569;
}
.config-edit-grid input,
.config-edit-grid select,
.config-edit-grid textarea {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 9px 10px;
  font: inherit;
  font-size: 13px;
  color: #0f172a;
  background: #fff;
  box-sizing: border-box;
}
.config-edit-grid input:focus,
.config-edit-grid select:focus,
.config-edit-grid textarea:focus {
  outline: none;
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
}
.config-edit-grid .wide-field { grid-column: span 2; }
.check-row,
.switch-row {
  display: inline-flex !important;
  flex-direction: row !important;
  align-items: center;
  gap: 8px !important;
  color: #334155;
}
.check-row input,
.switch-row input { width: auto; accent-color: #16a34a; }
.icon-btn.danger { color: #dc2626; border-color: #fecaca; background: #fff1f2; }
.preview-banner.invalid { background: #fff1f2; border-color: #fecaca; }
.preview-banner.invalid .preview-label,
.preview-banner.invalid p:last-child { color: #dc2626; }

/* ── Responsive ── */
@media (max-width: 900px) {
  .meta-grid { grid-template-columns: repeat(2, 1fr); }
  .meta-item.span2 { grid-column: span 2; }
  .param-grid { grid-template-columns: 1fr; }
  .config-head { flex-direction: column; }
  .config-edit-grid,
  .config-edit-grid.report-grid,
  .config-edit-grid.generic-grid { grid-template-columns: 1fr; }
  .config-edit-grid .wide-field { grid-column: span 1; }
  .param-field-check { grid-column: span 1; }
  .back-action-bar { flex-direction: column; align-items: flex-start; gap: 8px; }
  .back-action-buttons { flex-wrap: wrap; }
}
@media (max-width: 600px) {
  .meta-grid { grid-template-columns: 1fr; }
  .meta-item.span2 { grid-column: span 1; }
  .tab-body { padding: 16px; }
  .modal-body { padding: 16px; }
  .modal-foot { padding: 14px 16px; }
}
</style>

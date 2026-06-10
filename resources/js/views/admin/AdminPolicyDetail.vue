<template>
  <section class="policy-page">
    <div v-if="loading" class="state-card">
      <span class="spinner"></span>
      Đang tải chi tiết chính sách...
    </div>

    <template v-else-if="!policy">
      <div class="alert error">{{ error || 'Không tìm thấy chính sách.' }}</div>
      <button class="btn secondary" type="button" @click="backToList">
        <AppIcon name="arrowLeft" size="16" />
        Danh sách chính sách
      </button>
    </template>

    <template v-else>
      <header class="hero-card">
        <div>
          <button class="back-link" type="button" @click="backToList">
            <AppIcon name="arrowLeft" size="16" />
            Danh sách chính sách
          </button>
          <div class="title-row">
            <h2>{{ policy.title }}</h2>
            <span class="badge" :class="statusTone(policy.status)">{{ policy.status_label || getStatusLabel(policy.status) }}</span>
            <span class="badge neutral">v{{ policy.version || 1 }}</span>
          </div>
          <p>{{ policy.policy_type_label || getPolicyTypeLabel(policy.policy_type) }}</p>
        </div>
        <div class="hero-actions">
          <button v-if="isDraft" class="btn danger-ghost" type="button" :disabled="saving" @click="confirmDelete.show = true">
            <AppIcon name="trash" size="16" />
            Xóa bản nháp
          </button>
          <button v-if="isDraft" class="btn primary" type="button" :disabled="saving" @click="confirmPublish.show = true">
            <AppIcon name="check" size="16" />
            Áp dụng
          </button>
          <button v-if="!isDraft" class="btn secondary" type="button" :disabled="saving" @click="clonePolicy">
            <AppIcon name="copy" size="16" />
            Tạo phiên bản mới
          </button>
          <button v-if="policy.status === 'active'" class="btn danger-ghost" type="button" :disabled="saving" @click="confirmArchive.show = true">
            <AppIcon name="power" size="16" />
            Ngưng áp dụng
          </button>
        </div>
      </header>

      <div v-if="success" class="alert success">{{ success }}</div>
      <div v-if="error" class="alert error">{{ error }}</div>

      <nav class="tabs" aria-label="Chi tiết chính sách">
        <button v-for="tab in tabs" :key="tab.key" type="button" :class="{ active: activeTab === tab.key }" @click="activeTab = tab.key">
          <AppIcon :name="tab.icon" size="15" />
          {{ tab.label }}
          <span v-if="tab.count !== undefined" class="tab-count">{{ tab.count }}</span>
        </button>
      </nav>

      <section v-if="activeTab === 'overview'" class="panel">
        <div class="summary-grid">
          <InfoItem label="Nhóm chính sách" :value="policy.policy_type_label || getPolicyTypeLabel(policy.policy_type)" />
          <InfoItem label="Trạng thái" :value="policy.status_label || getStatusLabel(policy.status)" />
          <InfoItem label="Phiên bản" :value="`v${policy.version || 1}`" />
          <InfoItem label="Hiệu lực từ" :value="formatDate(policy.effective_from || policy.published_at)" />
          <InfoItem label="Cho sân cấu hình riêng" :value="policy.is_overridable ? 'Có, trong khung hệ thống' : 'Không'" />
          <InfoItem label="Số cấu hình xử lý" :value="`${rules.length} cấu hình`" />
        </div>
        <article class="business-card">
          <strong>Tóm tắt nghiệp vụ</strong>
          <p>{{ policy.business_summary_vi || policy.business_summary || 'Chưa có tóm tắt nghiệp vụ.' }}</p>
        </article>
      </section>

      <section v-if="activeTab === 'content'" class="panel">
        <div class="panel-head">
          <div>
            <h3>Nội dung chính sách</h3>
            <p>Văn bản để người dùng hoặc chủ sân đọc và chấp nhận khi cần.</p>
          </div>
          <button v-if="canEdit" class="btn primary" type="button" :disabled="savingContent" @click="saveContent">
            <AppIcon name="check" size="15" />
            {{ savingContent ? 'Đang lưu...' : 'Lưu nội dung' }}
          </button>
        </div>
        <div v-if="!canEdit" class="notice warning">
          Chính sách đang áp dụng. Hãy tạo phiên bản mới để chỉnh sửa nội dung.
        </div>
        <textarea v-model="contentDraft" class="content-textarea" rows="16" :readonly="!canEdit" />
      </section>

      <section v-if="activeTab === 'config'" class="panel">
        <div class="panel-head">
          <div>
            <h3>Cấu hình xử lý</h3>
            <p>Chỉ hiển thị bằng nghiệp vụ. Không nhập điều kiện kỹ thuật.</p>
          </div>
        </div>

        <article v-if="cancelRefundConfiguration" class="config-card">
          <ConfigHeader
            kicker="Hủy & hoàn booking"
            title="Bảng mốc hủy và hoàn theo thời gian"
            :summary="cancelRefundConfiguration.summary"
            :can-edit="canEdit"
            edit-label="Sửa bảng mốc"
            @edit="openCancelRefundModal"
            @detail="openDetail('Hủy & hoàn booking', cancelRefundConfiguration.summary, cancelRefundRows)"
          />
          <TierTable :rows="cancelRefundRows" mode="cancel_refund" />
        </article>

        <article v-if="false && !cancelRefundConfiguration && cancellationConfiguration" class="config-card">
          <ConfigHeader
            kicker="Hủy booking"
            title="Bảng mốc thời gian được hủy booking"
            :summary="cancellationConfiguration.summary"
            :can-edit="canEdit"
            edit-label="Sửa bảng hủy"
            @edit="openCancellationModal"
            @detail="openDetail('Hủy booking', cancellationConfiguration.summary, cancellationRows)"
          />
          <TierTable :rows="cancellationRows" mode="cancel" />
        </article>

        <article v-if="false && !cancelRefundConfiguration && refundConfiguration" class="config-card">
          <ConfigHeader
            kicker="Hoàn tiền"
            title="Bảng mốc hoàn tiền sau khi hủy hợp lệ"
            :summary="refundConfiguration.summary"
            :can-edit="canEdit"
            edit-label="Sửa bảng hoàn"
            @edit="openRefundModal"
            @detail="openDetail('Hoàn tiền', refundConfiguration.summary, refundRows)"
          />
          <div class="workflow-line">
            <span :class="refundConfiguration.requires_owner_confirm ? 'on' : 'off'">Chủ sân xác nhận</span>
            <span :class="refundConfiguration.requires_admin_confirm ? 'on' : 'off'">Admin hoàn tất</span>
          </div>
          <TierTable :rows="refundRows" mode="refund" />
        </article>

        <article v-if="reportConfiguration" class="config-card">
          <ConfigHeader
            kicker="Kiểm duyệt & báo cáo"
            title="Ngưỡng báo cáo cần xử lý"
            :summary="reportConfiguration.summary"
            :can-edit="canEdit"
            edit-label="Sửa ngưỡng báo cáo"
            @edit="openReportModal"
            @detail="openReportDetail"
          />
          <div class="tier-table">
            <table>
              <thead>
                <tr>
                  <th>Đối tượng</th>
                  <th>Ngưỡng báo cáo</th>
                  <th>Thời gian xét</th>
                  <th>Hành động</th>
                  <th>Thông báo admin</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="threshold in reportThresholdRows" :key="threshold.key">
                  <td>{{ threshold.object_type_label }}</td>
                  <td>{{ threshold.min_reports }} báo cáo từ {{ threshold.min_distinct_reporters }} người</td>
                  <td>{{ threshold.within_days }} ngày</td>
                  <td>{{ threshold.action_label }}</td>
                  <td>{{ threshold.notify_admin ? 'Có' : 'Không' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>

        <div v-if="otherRules.length" class="rule-grid">
          <article v-for="rule in otherRules" :key="rule.id" class="rule-card">
            <div>
              <strong>{{ rule.rule_label_vi || rule.rule_type_label || rule.rule_name }}</strong>
              <p>{{ rule.business_summary_vi || rule.business_summary }}</p>
            </div>
            <div class="rule-footer">
              <span class="badge" :class="rule.is_active ? 'success' : 'neutral'">{{ rule.is_active ? 'Đang bật' : 'Đang tắt' }}</span>
              <button class="text-btn" type="button" @click="openRuleDetail(rule)">Xem chi tiết</button>
            </div>
          </article>
        </div>

        <div v-if="!cancelRefundConfiguration && !cancellationConfiguration && !refundConfiguration && !reportConfiguration && !otherRules.length" class="empty-state">
          Chưa có cấu hình xử lý cho chính sách này.
        </div>
      </section>

      <section v-if="activeTab === 'venue'" class="panel">
        <div class="panel-head">
          <div>
            <h3>Chính sách sân</h3>
            <p>Các cấu hình riêng của sân đang dùng trong khung hệ thống.</p>
          </div>
        </div>
        <div class="list-box">
          <article v-for="item in venueRules" :key="item.id" class="timeline-item">
            <span class="dot"></span>
            <div>
              <strong>{{ item.venue_cluster?.name || item.venue_cluster_name || 'Cụm sân' }}</strong>
              <p>{{ item.rule_name || 'Cấu hình riêng của sân' }} · {{ getStatusLabel(item.status) }}</p>
              <small v-if="item.reject_reason || item.status_reason">Lý do: {{ item.reject_reason || item.status_reason }}</small>
            </div>
          </article>
          <div v-if="venueRules.length === 0" class="empty-state">Chưa có sân nào cấu hình riêng.</div>
        </div>
      </section>

      <section v-if="activeTab === 'audit'" class="panel">
        <div class="panel-head">
          <div>
            <h3>Lịch sử thay đổi</h3>
            <p>Hiển thị các thay đổi quan trọng bằng ngôn ngữ dễ hiểu.</p>
          </div>
        </div>
        <div class="list-box">
          <article v-for="log in auditLogs" :key="log.id" class="timeline-item">
            <span class="dot"></span>
            <div>
              <strong>{{ log.human_message || 'Đã cập nhật chính sách' }}</strong>
              <p>{{ log.actor_name || 'Hệ thống' }} · {{ formatDateTime(log.created_at) }}</p>
              <ul v-if="(log.changes_summary || []).length" class="change-list">
                <li v-for="item in log.changes_summary" :key="`${log.id}-${item.field || item.summary}`">{{ item.summary || item }}</li>
              </ul>
            </div>
          </article>
          <div v-if="auditLogs.length === 0" class="empty-state">Chưa có lịch sử thay đổi.</div>
        </div>
      </section>
    </template>

    <div v-if="cancelRefundModal" class="modal-backdrop" @click.self="closeModals">
      <form class="modal wide" @submit.prevent="saveCancelRefundConfig">
        <ModalHead title="Sửa bảng mốc hủy & hoàn booking" eyebrow="Hủy & hoàn booking" @close="closeModals" />
        <div class="edit-toolbar">
          <button class="btn secondary" type="button" @click="addCancelRefundTier">
            <AppIcon name="plus" size="15" />
            Thêm mốc
          </button>
        </div>
        <div class="tier-edit-list">
          <article v-for="tier in cancelRefundDraft" :key="tier.key" class="tier-edit-row-combined">
            <header class="tier-header">
              <strong>{{ getDynamicLabel(tier) }}</strong>
              <span>{{ getDynamicConditionLabel(tier) }}</span>
              <small class="tier-badge">{{ cancelRefundResult(tier) }}</small>
              <button class="icon-btn danger" type="button" title="Xóa mốc" :disabled="cancelRefundDraft.length <= 2" @click="removeCancelRefundTier(tier.key)">
                <AppIcon name="trash" size="15" />
              </button>
            </header>
            <div class="tier-body-grid">
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
                Hoàn (%)
                <div class="input-unit">
                  <input v-model.number="tier.refund_percent" type="number" min="0" max="100" />
                  <span>%</span>
                </div>
              </label>
              <div class="confirm-options">
                <label class="check-row">
                  <input v-model="tier.require_owner_confirm" type="checkbox" />
                  <span>Chủ sân xác nhận</span>
                </label>
                <label class="check-row">
                  <input v-model="tier.require_admin_confirm" type="checkbox" />
                  <span>Admin hoàn tất</span>
                </label>
              </div>
              <label class="customer-message-col">
                Nội dung cho khách
                <input v-model.trim="tier.customer_message" maxlength="500" />
              </label>
            </div>
          </article>
        </div>
        <PreviewBox :error="cancelRefundValidation" :text="cancelRefundPreview" />
        <ModalActions :saving="savingRule" save-label="Lưu bảng mốc" @cancel="closeModals" />
      </form>
    </div>

    <div v-if="cancellationModal" class="modal-backdrop" @click.self="closeModals">
      <form class="modal wide" @submit.prevent="saveCancellationConfig">
        <ModalHead title="Sửa bảng hủy booking" eyebrow="Hủy booking" @close="closeModals" />
        <div class="tier-edit-list">
          <article v-for="tier in cancellationDraft" :key="tier.key" class="tier-edit-row cancel">
            <div>
              <strong>{{ tier.label }}</strong>
              <span>{{ tier.condition_label }}</span>
            </div>
            <label>
              Xử lý
              <select v-model="tier.allow_cancel">
                <option :value="true">Cho hủy</option>
                <option :value="false">Không cho hủy</option>
              </select>
            </label>
          </article>
        </div>
        <PreviewBox :error="cancellationValidation" :text="cancellationPreview" />
        <ModalActions :saving="savingRule" save-label="Lưu bảng hủy" @cancel="closeModals" />
      </form>
    </div>

    <div v-if="refundModal" class="modal-backdrop" @click.self="closeModals">
      <form class="modal wide" @submit.prevent="saveRefundConfig">
        <ModalHead title="Sửa bảng hoàn tiền" eyebrow="Hoàn tiền" @close="closeModals" />
        <div class="workflow-edit">
          <label class="check-row">
            <input v-model="refundWorkflow.requires_owner_confirm" type="checkbox" />
            <span>Cần chủ sân xác nhận trước khi admin hoàn tiền</span>
          </label>
          <label class="check-row">
            <input v-model="refundWorkflow.requires_admin_confirm" type="checkbox" />
            <span>Admin xác nhận hoàn tất hoàn tiền</span>
          </label>
        </div>
        <div class="tier-edit-list">
          <article v-for="tier in refundDraft" :key="tier.key" class="tier-edit-row">
            <div>
              <strong>{{ tier.label }}</strong>
              <span>{{ tier.condition_label }}</span>
            </div>
            <label>
              Mức hoàn
              <div class="input-unit">
                <input v-model.number="tier.refund_percent" type="number" min="0" max="100" />
                <span>%</span>
              </div>
            </label>
          </article>
        </div>
        <PreviewBox :error="refundValidation" :text="refundPreview" />
        <ModalActions :saving="savingRule" save-label="Lưu bảng hoàn" @cancel="closeModals" />
      </form>
    </div>

    <div v-if="reportModal" class="modal-backdrop" @click.self="closeModals">
      <form class="modal extra-wide" @submit.prevent="saveReportConfig">
        <ModalHead title="Sửa ngưỡng báo cáo" eyebrow="Kiểm duyệt" @close="closeModals" />
        <div class="edit-toolbar">
          <button class="btn secondary" type="button" @click="addReportThreshold">
            <AppIcon name="plus" size="15" />
            Thêm ngưỡng
          </button>
        </div>
        <div class="threshold-edit-list">
          <article v-for="threshold in reportThresholdDraft" :key="threshold.key" class="threshold-edit-row">
            <div class="threshold-grid">
              <label>
                Đối tượng
                <select v-model="threshold.object_type" @change="normalizeThresholdAction(threshold)">
                  <option v-for="option in reportConfiguration?.target_type_options || []" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>
              <label>
                Số báo cáo
                <input v-model.number="threshold.min_reports" type="number" min="1" />
              </label>
              <label>
                Số người khác nhau
                <input v-model.number="threshold.min_distinct_reporters" type="number" min="1" />
              </label>
              <label>
                Số ngày xét
                <input v-model.number="threshold.within_days" type="number" min="1" />
              </label>
              <label class="threshold-action">
                Hành động
                <select v-model="threshold.action">
                  <option v-for="option in reportActionOptionsFor(threshold.object_type)" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>
            </div>
            <div class="threshold-flags">
              <label class="check-row">
                <input v-model="threshold.notify_admin" type="checkbox" />
                <span>Thông báo admin</span>
              </label>
              <label class="check-row">
                <input v-model="threshold.notify_reported_user" type="checkbox" />
                <span>Thông báo đối tượng bị báo cáo</span>
              </label>
              <label class="check-row">
                <input v-model="threshold.is_active" type="checkbox" />
                <span>Đang áp dụng</span>
              </label>
              <button class="icon-btn danger" type="button" title="Xóa ngưỡng" @click="removeReportThreshold(threshold.key)">
                <AppIcon name="trash" size="15" />
              </button>
            </div>
          </article>
        </div>
        <div v-if="false">
        <label>
          Áp dụng cho
          <select v-model="reportDraft.target_type">
            <option v-for="option in reportConfiguration?.target_type_options || []" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </label>
        <div class="form-grid">
          <label>
            Số báo cáo tối thiểu
            <input v-model.number="reportDraft.minimum_reports" type="number" min="1" />
          </label>
          <label>
            Số người báo cáo khác nhau
            <input v-model.number="reportDraft.minimum_unique_reporters" type="number" min="1" />
          </label>
          <label>
            Trong bao nhiêu ngày
            <input v-model.number="reportDraft.window_days" type="number" min="1" />
          </label>
        </div>
        <div class="check-list">
          <label v-for="option in reportActionOptions" :key="option.value" class="check-row">
            <input v-model="reportDraft.actions" type="checkbox" :value="option.value" />
            <span>{{ option.label }}</span>
          </label>
        </div>
        </div>
        <PreviewBox :error="reportValidation" :text="reportPreview" />
        <ModalActions :saving="savingRule" save-label="Lưu ngưỡng báo cáo" @cancel="closeModals" />
      </form>
    </div>

    <div v-if="detailModal" class="modal-backdrop" @click.self="detailModal = null">
      <article class="modal">
        <ModalHead :title="detailModal.title" eyebrow="Chi tiết cấu hình" @close="detailModal = null" />
        <div class="detail-box">
          <InfoItem label="Chính sách cha" :value="policy?.title" />
          <InfoItem label="Trạng thái" :value="policy?.status_label || getStatusLabel(policy?.status)" />
        </div>
        <p class="detail-summary">{{ detailModal.summary }}</p>
        <div v-if="detailModal.rows?.length" class="detail-list">
          <article v-for="row in detailModal.rows" :key="row.key">
            <strong>{{ row.label }}</strong>
            <span>{{ row.condition }}</span>
            <b>{{ row.result }}</b>
          </article>
        </div>
      </article>
    </div>

    <ConfirmModal
      v-model="confirmPublish.show"
      title="Áp dụng chính sách"
      :message="`Áp dụng chính sách ${policy?.title || ''}?`"
      consequence="Bản nháp sẽ trở thành chính sách đang áp dụng."
      confirm-text="Áp dụng"
      type="warning"
      @confirm="publishPolicy"
    />
    <ConfirmModal
      v-model="confirmArchive.show"
      title="Ngưng áp dụng chính sách"
      :message="`Ngưng áp dụng chính sách ${policy?.title || ''}?`"
      consequence="Các cấu hình xử lý của chính sách này sẽ không còn được áp dụng."
      confirm-text="Ngưng áp dụng"
      type="danger"
      @confirm="archivePolicy"
    />
    <ConfirmModal
      v-model="confirmDelete.show"
      title="Xóa bản nháp"
      :message="`Xóa bản nháp ${policy?.title || ''}?`"
      consequence="Bản nháp và cấu hình xử lý của bản nháp sẽ bị xóa."
      confirm-text="Xóa bản nháp"
      type="danger"
      @confirm="deletePolicy"
    />
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ConfirmModal from '../../components/ConfirmModal.vue';
import { adminPolicyService } from '../../services/adminPolicies.js';
import { getPolicyTypeLabel, getStatusLabel } from '../../utils/labelMaps.js';

const InfoItem = {
  props: { label: String, value: [String, Number] },
  template: '<div class="info-item"><span>{{ label }}</span><strong>{{ value || "-" }}</strong></div>',
};

const ConfigHeader = {
  components: { AppIcon },
  props: { kicker: String, title: String, summary: String, canEdit: Boolean, editLabel: String },
  emits: ['edit', 'detail'],
  template: `
    <header class="config-head">
      <div>
        <span class="card-kicker">{{ kicker }}</span>
        <h4>{{ title }}</h4>
        <p>{{ summary }}</p>
      </div>
      <div class="config-actions">
        <button class="btn secondary" type="button" @click="$emit('detail')">
          <AppIcon name="eye" size="15" />
          Chi tiết
        </button>
        <button class="btn primary" type="button" :disabled="!canEdit" @click="$emit('edit')">
          <AppIcon name="pencil" size="15" />
          {{ editLabel }}
        </button>
      </div>
    </header>
  `,
};

const TierTable = {
  props: { rows: Array, mode: String },
  template: `
    <div class="tier-table">
      <table>
        <thead>
          <tr>
            <th>Mốc thời gian</th>
            <th>Điều kiện</th>
            <th>Kết quả</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.key">
            <td><strong>{{ row.label }}</strong></td>
            <td>{{ row.condition }}</td>
            <td><span class="badge" :class="row.tone">{{ row.result }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  `,
};

const ModalHead = {
  components: { AppIcon },
  props: { title: String, eyebrow: String },
  emits: ['close'],
  template: `
    <header class="modal-head">
      <div>
        <p class="eyebrow">{{ eyebrow }}</p>
        <h3>{{ title }}</h3>
      </div>
      <button class="icon-btn" type="button" title="Đóng" @click="$emit('close')">
        <AppIcon name="x" size="18" />
      </button>
    </header>
  `,
};

const PreviewBox = {
  components: { AppIcon },
  props: { error: String, text: String },
  template: `
    <div class="preview-box" :class="{ invalid: error }">
      <AppIcon :name="error ? 'alert' : 'circleCheck'" size="18" />
      <span>{{ error || text }}</span>
    </div>
  `,
};

const ModalActions = {
  props: { saving: Boolean, saveLabel: String },
  emits: ['cancel'],
  template: `
    <footer class="modal-actions">
      <button class="btn secondary" type="button" @click="$emit('cancel')">Hủy</button>
      <button class="btn primary" type="submit" :disabled="saving">
        {{ saving ? 'Đang lưu...' : saveLabel }}
      </button>
    </footer>
  `,
};

export default {
  name: 'AdminPolicyDetail',
  components: { AppIcon, ConfirmModal, InfoItem, ConfigHeader, TierTable, ModalHead, PreviewBox, ModalActions },
  data() {
    return {
      loading: false,
      saving: false,
      savingContent: false,
      savingRule: false,
      error: '',
      success: '',
      activeTab: this.$route.query.tab === 'rules' ? 'config' : (this.$route.query.tab || 'overview'),
      detail: null,
      contentDraft: '',
      cancelRefundModal: false,
      cancellationModal: false,
      refundModal: false,
      reportModal: false,
      detailModal: null,
      cancelRefundDraft: [],
      cancellationDraft: [],
      refundDraft: [],
      refundWorkflow: { requires_owner_confirm: true, requires_admin_confirm: true },
      reportDraft: { target_type: 'content', minimum_reports: 5, minimum_unique_reporters: 2, window_days: 14, actions: [] },
      reportThresholdDraft: [],
      confirmPublish: { show: false },
      confirmArchive: { show: false },
      confirmDelete: { show: false },
    };
  },
  computed: {
    policy() { return this.detail?.policy || null; },
    rules() { return this.detail?.rules || []; },
    cancelRefundConfiguration() { return this.detail?.cancel_refund_tiers || null; },
    cancellationConfiguration() { return this.detail?.cancellation_configuration || null; },
    refundConfiguration() { return this.detail?.refund_configuration || null; },
    reportConfiguration() { return this.detail?.report_configuration || null; },
    venueRules() { return this.detail?.venue_rules || []; },
    auditLogs() { return this.detail?.audit_logs || []; },
    isDraft() { return this.policy?.status === 'draft'; },
    canEdit() { return this.policy?.status !== 'active'; },
    cancellationRule() { return this.rules.find((rule) => rule.configuration_type === 'cancellation_tier_table' || rule.configuration_type === 'cancel_refund_tier_table') || null; },
    refundRule() { return this.rules.find((rule) => rule.configuration_type === 'refund_tier_table') || null; },
    reportRule() { return this.rules.find((rule) => rule.configuration_type === 'report_threshold') || null; },
    otherRules() {
      return this.rules.filter((rule) => !['cancel_refund_tier_table', 'cancellation_tier_table', 'refund_tier_table', 'report_threshold'].includes(rule.configuration_type));
    },
    tabs() {
      return [
        { key: 'overview', label: 'Tổng quan', icon: 'dashboard' },
        { key: 'content', label: 'Nội dung chính sách', icon: 'fileText' },
        { key: 'config', label: 'Cấu hình xử lý', icon: 'sliders', count: this.rules.length },
        { key: 'venue', label: 'Chính sách sân', icon: 'building', count: this.venueRules.length },
        { key: 'audit', label: 'Lịch sử thay đổi', icon: 'clock', count: this.auditLogs.length },
      ];
    },
    cancelRefundRows() {
      return (this.cancelRefundConfiguration?.tiers || []).map((tier) => ({
        key: tier.key,
        label: tier.label,
        condition: tier.condition_label,
        result: this.cancelRefundResult(tier),
        tone: tier.allow_cancel ? 'success' : 'danger',
      }));
    },
    cancellationRows() {
      return (this.cancellationConfiguration?.tiers || []).map((tier) => ({
        key: tier.key,
        label: tier.label,
        condition: tier.condition_label,
        result: tier.allow_cancel ? 'Cho hủy' : 'Không cho hủy',
        tone: tier.allow_cancel ? 'success' : 'danger',
      }));
    },
    refundRows() {
      return (this.refundConfiguration?.tiers || []).map((tier) => ({
        key: tier.key,
        label: tier.label,
        condition: tier.condition_label,
        result: Number(tier.refund_percent || 0) > 0 ? `Hoàn ${Number(tier.refund_percent)}%` : 'Không hoàn',
        tone: Number(tier.refund_percent || 0) > 0 ? 'success' : 'neutral',
      }));
    },
    reportThresholdRows() {
      if (this.reportConfiguration?.thresholds?.length) {
        return this.reportConfiguration.thresholds;
      }

      const config = this.reportConfiguration?.config || {};
      return this.reportConfiguration ? [{
        key: 'legacy',
        object_type_label: this.reportConfiguration.target_type_label,
        min_reports: config.minimum_reports,
        min_distinct_reporters: config.minimum_unique_reporters,
        within_days: config.window_days,
        action_label: (this.reportConfiguration.action_labels || []).join(', '),
        notify_admin: (config.actions || []).includes('notify_admin'),
      }] : [];
    },
    cancelRefundPreview() {
      return this.cancelRefundDraft.map((tier) => `${this.getDynamicLabel(tier)}: ${this.cancelRefundResult(tier).toLowerCase()}`).join('. ') + '.';
    },
    cancellationPreview() {
      return this.cancellationDraft.map((tier) => `${tier.label}: ${tier.allow_cancel ? 'cho hủy' : 'không cho hủy'}`).join('. ') + '.';
    },
    refundPreview() {
      return this.refundDraft.map((tier) => `${tier.label}: ${Number(tier.refund_percent || 0) > 0 ? `hoàn ${Number(tier.refund_percent)}%` : 'không hoàn'}`).join('. ') + '.';
    },
    reportPreview() {
      if (!this.reportThresholdDraft.length) return 'Thêm ít nhất một ngưỡng báo cáo để hệ thống xử lý.';
      return this.reportThresholdDraft.map((threshold) => {
        const target = this.targetTypeLabel(threshold.object_type).toLowerCase();
        const action = this.actionLabel(threshold.object_type, threshold.action).toLowerCase();
        const notify = threshold.notify_admin ? ' và thông báo admin' : '';
        return `Nếu ${target} nhận từ ${threshold.min_reports} báo cáo hợp lệ bởi ít nhất ${threshold.min_distinct_reporters} người khác nhau trong ${threshold.within_days} ngày, hệ thống ${action}${notify}`;
      }).join('. ') + '.';
      const target = this.reportConfiguration?.target_type_options?.find((item) => item.value === this.reportDraft.target_type)?.label || 'Nội dung';
      const actions = this.reportActionOptions
        .filter((item) => this.reportDraft.actions.includes(item.value))
        .map((item) => item.label.toLowerCase())
        .join(' và ');
      return `Nếu ${target.toLowerCase()} nhận từ ${this.reportDraft.minimum_reports} báo cáo hợp lệ bởi ít nhất ${this.reportDraft.minimum_unique_reporters} người khác nhau trong ${this.reportDraft.window_days} ngày, hệ thống ${actions}.`;
    },
    reportActionOptions() {
      const options = this.reportConfiguration?.action_options || [];
      if (Array.isArray(options)) return options;
      return options[this.reportDraft.target_type] || options.post || [];
    },
    cancelRefundValidation() {
      if (this.cancelRefundDraft.length < 2) return 'Bảng hủy & hoàn phải có ít nhất 2 mốc thời gian.';
      for (const tier of this.cancelRefundDraft) {
        const from = Number(tier.from_hours);
        const to = tier.to_hours === '' || tier.to_hours === null ? null : Number(tier.to_hours);
        const percent = Number(tier.refund_percent);
        if (Number.isNaN(from) || from < 0) return 'Giờ bắt đầu mốc phải lớn hơn hoặc bằng 0.';
        if (to !== null && (Number.isNaN(to) || to <= from)) return 'Giờ kết thúc mốc phải lớn hơn giờ bắt đầu.';
        if (Number.isNaN(percent) || percent < 0 || percent > 100) return 'Tỷ lệ hoàn phải nằm trong khoảng 0 đến 100%.';
        if (!tier.allow_cancel && percent !== 0) return 'Nếu không cho hủy thì tỷ lệ hoàn bắt buộc bằng 0%.';
      }
      const sorted = [...this.cancelRefundDraft]
        .map((tier) => ({ from: Number(tier.from_hours), to: tier.to_hours === '' || tier.to_hours === null ? null : Number(tier.to_hours) }))
        .sort((a, b) => a.from - b.from);
      if (sorted[0]?.from !== 0) return 'Bảng mốc phải bắt đầu từ 0 giờ.';
      for (let index = 0; index < sorted.length; index += 1) {
        const current = sorted[index];
        const next = sorted[index + 1];
        if (next && (current.to === null || current.to !== next.from)) return 'Các mốc phải liền nhau, không chồng hoặc hở khoảng.';
        if (!next && current.to !== null) return 'Mốc cao nhất phải phủ đến vô hạn.';
      }
      return '';
      if (this.cancelRefundDraft.length !== 4) return 'Bảng hủy & hoàn phải có đủ 4 mốc thời gian.';
      for (const tier of this.cancelRefundDraft) {
        const from = Number(tier.from_hours);
        const percent = Number(tier.refund_percent);
        if (Number.isNaN(from) || from < 0) return 'Giờ bắt đầu mốc phải lớn hơn hoặc bằng 0.';
        if (tier.to_hours !== null && tier.to_hours !== '' && Number(tier.to_hours) <= from) return 'Giờ kết thúc mốc phải lớn hơn giờ bắt đầu.';
        if (Number.isNaN(percent) || percent < 0 || percent > 100) return 'Tỷ lệ hoàn phải nằm trong khoảng 0 đến 100%.';
        if (!tier.allow_cancel && percent !== 0) return 'Nếu không cho hủy thì tỷ lệ hoàn bắt buộc bằng 0%.';
      }
      return '';
    },
    cancellationValidation() {
      return this.cancellationDraft.length === 4 ? '' : 'Bảng hủy booking phải có đủ 4 mốc thời gian.';
    },
    refundValidation() {
      if (this.refundDraft.length !== 4) return 'Bảng hoàn tiền phải có đủ 4 mốc thời gian.';
      for (const tier of this.refundDraft) {
        const percent = Number(tier.refund_percent);
        if (Number.isNaN(percent) || percent < 0 || percent > 100) return 'Phần trăm hoàn tiền phải nằm trong khoảng 0 đến 100.';
      }
      if (!this.refundWorkflow.requires_owner_confirm) return 'Luồng hoàn tiền phải có bước chủ sân xác nhận.';
      if (!this.refundWorkflow.requires_admin_confirm) return 'Luồng hoàn tiền phải có bước admin xác nhận hoàn tất.';
      return '';
    },
    reportValidation() {
      if (!this.reportThresholdDraft.length) return 'Vui lòng thêm ít nhất một ngưỡng báo cáo.';
      for (const [index, threshold] of this.reportThresholdDraft.entries()) {
        if (Number(threshold.min_reports) <= 0 || Number(threshold.min_distinct_reporters) <= 0 || Number(threshold.within_days) <= 0) {
          return `Dòng ${index + 1}: các ngưỡng báo cáo phải lớn hơn 0.`;
        }
        if (Number(threshold.min_distinct_reporters) > Number(threshold.min_reports)) {
          return `Dòng ${index + 1}: số người báo cáo khác nhau không được lớn hơn tổng số báo cáo.`;
        }
        if (!threshold.action) return `Dòng ${index + 1}: vui lòng chọn hành động xử lý.`;
        if (!this.reportActionOptionsFor(threshold.object_type).some((option) => option.value === threshold.action)) {
          return `Dòng ${index + 1}: hành động không phù hợp với đối tượng đã chọn.`;
        }
      }
      return '';
      if (this.reportDraft.minimum_reports <= 0 || this.reportDraft.minimum_unique_reporters <= 0 || this.reportDraft.window_days <= 0) {
        return 'Các ngưỡng báo cáo phải lớn hơn 0.';
      }
      if (this.reportDraft.minimum_unique_reporters > this.reportDraft.minimum_reports) {
        return 'Số người báo cáo khác nhau không được lớn hơn tổng số báo cáo.';
      }
      if (!this.reportDraft.actions.length) return 'Vui lòng chọn ít nhất một hành động khi đạt ngưỡng.';
      return '';
    },
  },
  mounted() {
    this.loadDetail();
  },
  methods: {
    getPolicyTypeLabel,
    getStatusLabel,
    async loadDetail() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminPolicyService.show(this.$route.params.id);
        this.detail = response.data || null;
        this.contentDraft = this.policy?.content || '';
      } catch (error) {
        this.error = error.message || 'Không tải được chi tiết chính sách.';
      } finally {
        this.loading = false;
      }
    },
    backToList() {
      this.$router.push({ name: this.$route.query.source === 'platform_fee' ? 'admin-platform-fee-policies' : 'admin-policies' });
    },
    openCancelRefundModal() {
      if (!this.canEdit) return;
      this.cancelRefundDraft = JSON.parse(JSON.stringify(this.cancelRefundConfiguration?.tiers || []));
      this.cancelRefundModal = true;
    },
    openCancellationModal() {
      if (!this.canEdit) return;
      this.cancellationDraft = JSON.parse(JSON.stringify(this.cancellationConfiguration?.tiers || []));
      this.cancellationModal = true;
    },
    openRefundModal() {
      if (!this.canEdit) return;
      this.refundDraft = JSON.parse(JSON.stringify(this.refundConfiguration?.tiers || []));
      this.refundWorkflow = {
        requires_owner_confirm: this.refundConfiguration?.requires_owner_confirm !== false,
        requires_admin_confirm: this.refundConfiguration?.requires_admin_confirm !== false,
      };
      this.refundModal = true;
    },
    openReportModal() {
      if (!this.canEdit) return;
      this.reportThresholdDraft = JSON.parse(JSON.stringify(this.reportThresholdRows.length ? this.reportThresholdRows : [this.defaultReportThreshold()]))
        .map((threshold) => this.normalizeThresholdShape(threshold));
      this.reportModal = true;
    },
    closeModals() {
      this.cancelRefundModal = false;
      this.cancellationModal = false;
      this.refundModal = false;
      this.reportModal = false;
    },
    addCancelRefundTier() {
      const ascending = [...this.cancelRefundDraft]
        .sort((a, b) => Number(a.from_hours || 0) - Number(b.from_hours || 0));
      const top = ascending[ascending.length - 1];
      if (!top) {
        this.cancelRefundDraft = [this.defaultCancelRefundTier(0, null)];
        return;
      }
      const splitAt = Number(top.from_hours || 0) + 24;
      top.to_hours = splitAt;
      ascending.push(this.defaultCancelRefundTier(splitAt, null));
      this.cancelRefundDraft = ascending.sort((a, b) => Number(b.from_hours || 0) - Number(a.from_hours || 0));
    },
    removeCancelRefundTier(key) {
      const ascending = [...this.cancelRefundDraft]
        .sort((a, b) => Number(a.from_hours || 0) - Number(b.from_hours || 0));
      if (ascending.length <= 2) return;
      const index = ascending.findIndex((tier) => tier.key === key);
      if (index < 0) return;
      const removed = ascending[index];
      const previous = ascending[index - 1];
      const next = ascending[index + 1];
      if (previous) previous.to_hours = removed.to_hours;
      if (!previous && next) next.from_hours = 0;
      ascending.splice(index, 1);
      this.cancelRefundDraft = ascending.sort((a, b) => Number(b.from_hours || 0) - Number(a.from_hours || 0));
    },
    defaultCancelRefundTier(fromHours = 0, toHours = null) {
      return {
        key: `tier_${Date.now()}_${Math.random().toString(16).slice(2)}`,
        label: '',
        from_hours: fromHours,
        to_hours: toHours,
        allow_cancel: true,
        refund_percent: 0,
        require_owner_confirm: true,
        require_admin_confirm: true,
        customer_message: '',
      };
    },
    defaultReportThreshold() {
      const objectType = this.reportConfiguration?.target_type_options?.[0]?.value || 'post';
      const action = this.reportActionOptionsFor(objectType)[0]?.value || 'notify_admin';
      return {
        key: `threshold_${Date.now()}_${Math.random().toString(16).slice(2)}`,
        object_type: objectType,
        min_reports: 5,
        min_distinct_reporters: 2,
        within_days: 14,
        action,
        notify_admin: true,
        notify_reported_user: false,
        is_active: true,
      };
    },
    normalizeThresholdShape(threshold) {
      const objectType = threshold.object_type || threshold.target_type || 'post';
      const options = this.reportActionOptionsFor(objectType);
      const action = options.some((option) => option.value === threshold.action)
        ? threshold.action
        : (options[0]?.value || 'notify_admin');
      return {
        key: threshold.key || `threshold_${Date.now()}_${Math.random().toString(16).slice(2)}`,
        object_type: objectType,
        min_reports: Number(threshold.min_reports || threshold.minimum_reports || 5),
        min_distinct_reporters: Number(threshold.min_distinct_reporters || threshold.minimum_unique_reporters || 2),
        within_days: Number(threshold.within_days || threshold.window_days || 14),
        action,
        notify_admin: threshold.notify_admin !== false,
        notify_reported_user: Boolean(threshold.notify_reported_user),
        is_active: threshold.is_active !== false,
      };
    },
    addReportThreshold() {
      this.reportThresholdDraft.push(this.defaultReportThreshold());
    },
    removeReportThreshold(key) {
      this.reportThresholdDraft = this.reportThresholdDraft.filter((threshold) => threshold.key !== key);
    },
    normalizeThresholdAction(threshold) {
      const options = this.reportActionOptionsFor(threshold.object_type);
      if (!options.some((option) => option.value === threshold.action)) {
        threshold.action = options[0]?.value || 'notify_admin';
      }
    },
    reportActionOptionsFor(objectType) {
      const options = this.reportConfiguration?.action_options || [];
      if (Array.isArray(options)) return options;
      return options[objectType] || options.account || options.post || [];
    },
    targetTypeLabel(objectType) {
      return this.reportConfiguration?.target_type_options?.find((item) => item.value === objectType)?.label || objectType || 'Nội dung';
    },
    actionLabel(objectType, action) {
      return this.reportActionOptionsFor(objectType).find((item) => item.value === action)?.label || action || 'xử lý thủ công';
    },
    async saveCancelRefundConfig() {
      if (this.cancelRefundValidation) return;
      this.savingRule = true;
      this.error = '';
      try {
        const tiers = this.cancelRefundDraft.map((tier) => ({
          ...tier,
          from_hours: Number(tier.from_hours || 0),
          to_hours: tier.to_hours === '' || tier.to_hours === null ? null : Number(tier.to_hours),
          allow_cancel: Boolean(tier.allow_cancel),
          refund_percent: Number(tier.refund_percent || 0),
          require_owner_confirm: Boolean(tier.require_owner_confirm),
          require_admin_confirm: Boolean(tier.require_admin_confirm),
        }));
        await adminPolicyService.saveCancelRefundTiers(this.policy.id, { tiers });
        this.success = 'Đã lưu bảng mốc hủy & hoàn booking.';
        this.closeModals();
        await this.loadDetail();
        this.activeTab = 'config';
      } catch (error) {
        this.error = error.message || 'Không thể lưu bảng mốc hủy & hoàn.';
      } finally {
        this.savingRule = false;
      }
    },
    async saveCancellationConfig() {
      this.error = 'Vui lòng cấu hình bằng nhóm Hủy & hoàn booking để hệ thống tính đúng cả bước hủy và hoàn tiền.';
    },
    async saveRefundConfig() {
      this.error = 'Vui lòng cấu hình bằng nhóm Hủy & hoàn booking để mức hoàn luôn đi cùng điều kiện được hủy.';
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
            min_reports: Number(threshold.min_reports || 0),
            min_distinct_reporters: Number(threshold.min_distinct_reporters || 0),
            within_days: Number(threshold.within_days || 0),
            action: threshold.action,
            notify_admin: Boolean(threshold.notify_admin),
            notify_reported_user: Boolean(threshold.notify_reported_user),
            is_active: Boolean(threshold.is_active),
          })),
        });
        this.success = 'Đã lưu ngưỡng báo cáo.';
        this.closeModals();
        await this.loadDetail();
        this.activeTab = 'config';
      } catch (error) {
        this.error = error.message || 'Không thể lưu ngưỡng báo cáo.';
      } finally {
        this.savingRule = false;
      }
    },
    async saveConfigRule({ existingRule, binding, payload, successMessage }) {
      this.savingRule = true;
      this.error = '';
      try {
        await adminPolicyService.addBinding(this.policy.id, binding);
        if (existingRule) {
          await adminPolicyService.updateRule(this.policy.id, existingRule.id, payload);
        } else {
          await adminPolicyService.addRule(this.policy.id, payload);
        }
        this.success = successMessage;
        this.closeModals();
        await this.loadDetail();
        this.activeTab = 'config';
      } catch (error) {
        this.error = error.message || 'Không thể lưu cấu hình xử lý.';
      } finally {
        this.savingRule = false;
      }
    },
    async saveContent() {
      this.savingContent = true;
      this.error = '';
      try {
        await adminPolicyService.update(this.policy.id, {
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
        this.success = 'Đã lưu nội dung chính sách.';
        await this.loadDetail();
      } catch (error) {
        this.error = error.message || 'Không thể lưu nội dung chính sách.';
      } finally {
        this.savingContent = false;
      }
    },
    async clonePolicy() {
      await this.runAction(() => adminPolicyService.cloneVersion(this.policy.id), 'Đã tạo phiên bản mới.');
    },
    async publishPolicy() {
      await this.runAction(() => adminPolicyService.publish(this.policy.id), 'Đã áp dụng chính sách.');
    },
    async archivePolicy() {
      await this.runAction(() => adminPolicyService.updateStatus(this.policy.id, { status: 'archived' }), 'Đã ngưng áp dụng chính sách.');
    },
    async deletePolicy() {
      this.saving = true;
      this.error = '';
      try {
        await adminPolicyService.delete(this.policy.id);
        this.$router.push({ name: 'admin-policies' });
      } catch (error) {
        this.error = error.message || 'Không thể xóa bản nháp.';
      } finally {
        this.saving = false;
      }
    },
    async runAction(action, message) {
      this.saving = true;
      this.error = '';
      try {
        const response = await action();
        this.success = response?.message || message;
        if (this.policy) await this.loadDetail();
      } catch (error) {
        this.error = error.message || 'Thao tác không thành công.';
      } finally {
        this.saving = false;
      }
    },
    openDetail(title, summary, rows) {
      this.detailModal = { title, summary, rows };
    },
    openReportDetail() {
      const config = this.reportConfiguration;
      const thresholds = config?.thresholds || [];
      this.detailModal = {
        title: 'Ngưỡng báo cáo',
        summary: config.summary,
        rows: thresholds.length
          ? thresholds.map((threshold) => ({
              key: threshold.key,
              label: threshold.object_type_label,
              condition: `${threshold.min_reports} báo cáo, ${threshold.min_distinct_reporters} người khác nhau trong ${threshold.within_days} ngày`,
              result: threshold.action_label,
            }))
          : [
              { key: 'target', label: 'Áp dụng cho', condition: config.target_type_label, result: config.action_labels.join(', ') },
              { key: 'count', label: 'Điều kiện', condition: `${config.config.minimum_reports} báo cáo, ${config.config.minimum_unique_reporters} người khác nhau`, result: `Trong ${config.config.window_days} ngày` },
            ],
      };
    },
    openRuleDetail(rule) {
      this.detailModal = {
        title: rule.rule_label_vi || rule.rule_type_label || rule.rule_name,
        summary: rule.business_summary_vi || rule.business_summary,
        rows: [
          { key: 'condition', label: 'Điều kiện', condition: rule.condition_summary_vi || 'Theo mẫu nghiệp vụ', result: rule.result_summary_vi || 'Xử lý theo cấu hình' },
        ],
      };
    },
    statusTone(status) {
      return { active: 'success', draft: 'warning', inactive: 'neutral', archived: 'neutral', rejected: 'danger' }[String(status || '').toLowerCase()] || 'neutral';
    },
    cancelRefundResult(tier) {
      if (!tier?.allow_cancel) return 'Không cho hủy';
      const percent = Number(tier?.refund_percent || 0);
      return percent > 0 ? `Cho hủy, hoàn ${percent}%` : 'Cho hủy nhưng không hoàn';
    },
    getDynamicLabel(tier) {
      const from = tier.from_hours === '' || tier.from_hours === null ? null : Number(tier.from_hours);
      const to = tier.to_hours === '' || tier.to_hours === null ? null : Number(tier.to_hours);
      if ((from === null || from <= 0) && to !== null) {
        return `Dưới ${to} giờ`;
      }
      if (to === null) {
        return `Từ ${from} giờ trở lên`;
      }
      return `Từ ${from} đến dưới ${to} giờ`;
    },
    getDynamicConditionLabel(tier) {
      const from = tier.from_hours === '' || tier.from_hours === null ? null : Number(tier.from_hours);
      const to = tier.to_hours === '' || tier.to_hours === null ? null : Number(tier.to_hours);
      if ((from === null || from <= 0) && to !== null) {
        return `Khách hủy trước giờ chơi dưới ${to} giờ`;
      }
      if (to === null) {
        return `Khách hủy trước giờ chơi từ ${from} giờ trở lên`;
      }
      return `Khách hủy trước giờ chơi từ ${from} đến dưới ${to} giờ`;
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    formatDateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
  },
};
</script>

<style>
.policy-page {
  display: flex; flex-direction: column; gap: 18px;

  .hero-card, .panel, .state-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; }
  .hero-card { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
  .back-link { border: 0; background: transparent; padding: 0; display: inline-flex; gap: 6px; align-items: center; color: #15803d; font-weight: 800; cursor: pointer; }
  .title-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 8px 0 4px; }
  h2, h3, h4, p { margin: 0; }
  .hero-card p, .panel-head p, .config-head p, .rule-card p, .timeline-item p, small { color: #64748b; }
  .hero-actions, .tabs, .modal-actions { display: flex; gap: 8px; flex-wrap: wrap; }
  .config-actions { display: flex; gap: 8px; flex-wrap: nowrap; flex-shrink: 0; }
  .tabs { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; width: fit-content; }
  .tabs button { border: 0; background: transparent; border-radius: 6px; padding: 9px 12px; display: inline-flex; align-items: center; gap: 7px; color: #475569; font-weight: 800; cursor: pointer; }
  .tabs button.active { background: #dcfce7; color: #166534; }
  .tab-count { background: #e2e8f0; border-radius: 999px; padding: 1px 7px; font-size: 12px; }
  .panel { display: flex; flex-direction: column; gap: 14px; }
  .panel-head, .config-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
  .summary-grid, .report-grid, .detail-box { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
  .info-item, .business-card, .config-card, .rule-card, .timeline-item { border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; padding: 12px; }
  .info-item { display: grid; gap: 5px; }
  .info-item span, .card-kicker { color: #64748b; font-size: 12px; font-weight: 800; text-transform: uppercase; }
  .info-item strong { color: #0f172a; }
  .business-card { background: #f0fdf4; border-color: #bbf7d0; display: grid; gap: 6px; color: #14532d; }
  .content-textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px; font: inherit; resize: vertical; }
  .config-card { display: grid; gap: 12px; background: #fff; }
  .tier-table { overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
  table { width: 100%; border-collapse: collapse; min-width: 680px; }
  th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
  th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; }
  .workflow-line, .action-chips { display: flex; gap: 8px; flex-wrap: wrap; }
  .workflow-line span, .action-chips span { border-radius: 999px; padding: 6px 10px; font-weight: 800; font-size: 12px; }
  .workflow-line .on, .action-chips span { background: #dcfce7; color: #166534; }
  .workflow-line .off { background: #fee2e2; color: #991b1b; }
  .rule-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; }
  .rule-card { display: grid; gap: 10px; background: #fff; }
  .rule-footer { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
  .text-btn { border: 0; background: transparent; color: #15803d; font-weight: 900; cursor: pointer; }
  .list-box, .detail-list { display: grid; gap: 10px; }
  .timeline-item { display: grid; grid-template-columns: auto 1fr; gap: 10px; background: #fff; }
  .dot { width: 10px; height: 10px; border-radius: 50%; background: #16a34a; margin-top: 5px; }
  .change-list { margin: 8px 0 0; padding-left: 18px; color: #334155; }
  .badge { display: inline-flex; width: fit-content; border-radius: 999px; padding: 5px 9px; font-size: 12px; font-weight: 800; white-space: nowrap; }
  .badge.success { background: #dcfce7; color: #166534; }
  .badge.warning { background: #fef3c7; color: #92400e; }
  .badge.danger { background: #fee2e2; color: #991b1b; }
  .badge.neutral { background: #f1f5f9; color: #475569; }
  .btn, .icon-btn { border: 0; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 800; cursor: pointer; }
  .btn { padding: 10px 14px; }
  .btn.primary { background: #16a34a; color: #fff; }
  .btn.secondary { background: #f1f5f9; color: #334155; }
  .btn.danger-ghost { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
  .btn:disabled { opacity: .55; cursor: not-allowed; }
  .icon-btn { width: 36px; height: 36px; border: 1px solid #dbe3ef; background: #fff; }
  .alert, .notice, .preview-box { border-radius: 8px; padding: 12px; font-weight: 700; }
  .alert.error, .preview-box.invalid { background: #fef2f2; color: #991b1b; }
  .alert.success, .preview-box { background: #f0fdf4; color: #166534; }
  .notice.warning { background: #fffbeb; color: #92400e; }
  .empty-state { padding: 22px; border: 1px dashed #cbd5e1; border-radius: 8px; color: #64748b; text-align: center; font-weight: 800; }
  .spinner { width: 18px; height: 18px; border: 2px solid #bbf7d0; border-top-color: #16a34a; border-radius: 50%; display: inline-block; margin-right: 8px; animation: spin .8s linear infinite; }
  .modal-backdrop { position: fixed; inset: 0; z-index: 800; display: flex; justify-content: center; align-items: flex-start; padding: 40px 20px; background: rgba(15, 23, 42, .52); overflow-y: auto; }
  .modal { width: min(640px, 100%); max-height: 92vh; overflow: auto; background: #fff; border-radius: 10px; padding: 18px; display: grid; gap: 14px; box-shadow: 0 24px 80px rgba(15, 23, 42, .25); }
  .modal.wide { width: min(900px, 100%); }
  .modal.extra-wide { width: min(1180px, 100%); }
  .modal-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
  .eyebrow { margin: 0 0 4px; color: #15803d; font-size: 12px; font-weight: 900; text-transform: uppercase; }
  .tier-edit-list { display: grid; gap: 10px; }
  .tier-edit-row { display: grid; grid-template-columns: 1fr 180px; gap: 10px; align-items: end; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
  .tier-edit-row.cancel { display: grid; grid-template-columns: 1fr 180px; }
  .tier-edit-row span { display: block; margin-top: 4px; color: #64748b; font-size: 13px; }

  .tier-edit-row-combined { border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; background: #f8fafc; display: flex; flex-direction: column; gap: 12px; }
  .tier-header { display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; flex-wrap: wrap; }
  .tier-header strong { font-size: 15px; color: #0f172a; }
  .tier-header span { font-size: 13px; color: #64748b; }
  .tier-badge { margin-left: auto; background: #eef2f7; padding: 2px 8px; border-radius: 4px; font-weight: 700; color: #334155; }
  .tier-body-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
  .confirm-options { grid-column: span 2; display: flex; flex-direction: column; justify-content: center; gap: 8px; }
  .customer-message-col { grid-column: span 2; }
  @media (max-width: 768px) {
    .tier-body-grid { grid-template-columns: 1fr; }
    .confirm-options, .customer-message-col { grid-column: span 1; }
  }
  .workflow-edit, .check-list { display: grid; gap: 10px; }
  .edit-toolbar { display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
  .threshold-edit-list { display: grid; gap: 12px; }
  .threshold-edit-row { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; background: #f8fafc; display: grid; gap: 12px; }
  .threshold-grid { display: grid; grid-template-columns: minmax(180px, 1.2fr) repeat(3, minmax(120px, .75fr)) minmax(220px, 1.3fr); gap: 10px; align-items: end; }
  .threshold-flags { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: flex-end; }
  .threshold-flags .check-row { width: auto; }
  .check-row { display: flex; gap: 8px; align-items: center; }
  .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
  label { display: grid; gap: 6px; color: #334155; font-weight: 800; }
  input, select, textarea { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; font: inherit; }
  .input-unit { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: center; }
  .input-unit span { color: #64748b; font-weight: 800; }
  .detail-summary { color: #334155; line-height: 1.55; }
  .detail-list article { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; display: grid; gap: 4px; }
  .detail-list span { color: #64748b; }
  .detail-list b { color: #166534; }
}
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 900px) {
  .policy-page {
    .hero-card, .config-head, .panel-head { display: grid; }
    .summary-grid, .report-grid, .detail-box, .tier-edit-row, .form-grid { grid-template-columns: 1fr; }
    .threshold-grid { grid-template-columns: 1fr; }
    .threshold-flags { justify-content: flex-start; }
  }
}
</style>

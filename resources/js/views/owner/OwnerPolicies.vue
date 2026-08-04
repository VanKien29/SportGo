<template>
  <section class="page">
    <!-- Floating Add Button -->
    <div v-if="tab === 'notices'" class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
      <button class="btn-float-add" type="button" @click="openNotice()" title="Thêm quy định">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Thêm quy định</span>
      </button>
    </div>

    <div class="cluster-selection-bar" v-if="clusters.length > 1 || currentCluster">
      <label class="cluster-picker" v-if="clusters.length > 1">
        <span>Cụm sân đang quản lý</span>
        <select v-model="selectedClusterId" @change="changeCluster">
          <option v-for="cluster in clusters" :key="cluster.id" :value="cluster.id">{{ cluster.name }}</option>
        </select>
      </label>
      <div v-else-if="currentCluster" class="cluster-badge">
        <span>Cụm sân đang quản lý</span>
        <strong>{{ currentCluster.name }}</strong>
      </div>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="currentCluster && currentCluster.status !== 'active'" class="alert warning">
      Cụm sân đang ở trạng thái {{ currentCluster.status }}. Một số thay đổi có thể cần admin kiểm tra.
    </div>

    <nav class="tabs">
      <button :class="{ active: tab === 'rules' }" type="button" @click="tab = 'rules'">Quy tắc áp dụng hệ thống</button>
      <button :class="{ active: tab === 'notices' }" type="button" @click="tab = 'notices'">Quy định hiển thị cho khách</button>
    </nav>

    <section v-if="tab === 'rules'" class="policy-section">
      <div class="inheritance-flow">
        <article>
          <span>1</span>
          <strong>Khung hệ thống</strong>
          <p>Admin cấu hình bảng mốc hủy & hoàn làm chuẩn tối thiểu.</p>
        </article>
        <article>
          <span>2</span>
          <strong>Cụm sân đang chọn</strong>
          <p>{{ currentCluster?.name || 'Chọn cụm sân để cấu hình chính sách riêng.' }}</p>
        </article>
        <article>
          <span>3</span>
          <strong>Chính sách sân</strong>
          <p>Chủ sân có thể tạo cấu hình riêng nhưng không được bất lợi hơn khung hệ thống.</p>
        </article>
      </div>

      <div v-if="loading" class="state">Đang tải chính sách sân...</div>

      <article v-for="policy in cancelRefundPolicies" :key="policy.id" class="policy-card refund-card">
        <div class="card-head">
          <div>
            <h3>{{ policy.title }}</h3>
            <span class="type">{{ policy.policy_type_label }}</span>
          </div>
          <span class="badge" :class="policyStatus(policy).className">{{ policyStatus(policy).label }}</span>
        </div>

        <div class="policy-summary-grid">
          <div class="summary-block">
            <span>Khung hệ thống</span>
            <p>{{ policySystemSummary(policy) }}</p>
          </div>
          <div class="summary-block venue">
            <span>Chính sách đang áp dụng cho cụm sân</span>
            <p>{{ policyVenueSummary(policy) }}</p>
          </div>
        </div>

        <div class="tier-preview" v-if="effectiveTiers(policy).length">
          <div class="tier-preview-head">
            <strong>Bảng mốc hủy & hoàn</strong>
            <small>{{ policy.cancel_refund_configuration.effective_source_label }}</small>
          </div>
          <div class="tier-preview-grid">
            <div
              v-for="tier in effectiveTiers(policy)"
              :key="tier.key"
              class="tier-preview-card"
            >
              <span>{{ rangeText(tier) }}</span>
              <strong>{{ tier.allow_cancel ? `Hoàn ${tier.refund_percent}%` : 'Không cho hủy' }}</strong>
              <p>{{ policy.cancel_refund_configuration.effective_source === 'venue' ? 'Chính sách riêng của cụm sân' : 'Đang dùng khung hệ thống' }}</p>
              <small>{{ systemFloorLine(policy, tier) }}</small>
            </div>
          </div>
        </div>

        <footer>
          <button v-if="canEditCancelRefund(policy)" class="btn primary" type="button" @click="openCancelRefund(policy)">
            {{ policy.cancel_refund_configuration?.venue_rule_id ? 'Sửa chính sách sân' : 'Tạo chính sách sân' }}
          </button>
          <button v-if="policy.cancel_refund_configuration?.venue_rule_id" class="btn secondary" type="button" @click="resetPolicy(policy)">
            Dùng lại khung hệ thống
          </button>
        </footer>
      </article>

      <div v-if="!loading && cancelRefundPolicies.length === 0" class="state">
        Chưa có chính sách hủy & hoàn nào đang active và cho phép sân cấu hình riêng.
      </div>
    </section>

    <section v-if="tab === 'notices'" class="panel">
      <div class="section-head">
        <div>
          <h3>Quy định hiển thị cho khách</h3>
          <p>Nội dung này chỉ để khách đọc, không tác động tự động đến hủy, hoàn tiền hoặc booking.</p>
        </div>
      </div>
      <div v-if="customerNotices.length === 0" class="state">Chưa có nội quy hiển thị cho khách.</div>
      <article v-for="notice in customerNotices" :key="notice.id" class="notice-card">
        <div>
          <strong>{{ notice.title }}</strong>
          <p>{{ notice.content }}</p>
        </div>
        <span class="badge" :class="notice.status">{{ notice.status_label }}</span>
        <ActionIconButton icon="pencil" label="Sửa quy định" @click="openNotice(notice)" />
      </article>
    </section>

    <div v-if="cancelRefundModal" class="modal-backdrop" @click.self="closeCancelRefund">
      <form class="modal wide" @submit.prevent="saveCancelRefund">
        <header class="modal-head">
          <div>
            <h3>{{ cancelRefundForm.id ? 'Sửa chính sách sân' : 'Tạo chính sách sân' }}</h3>
            <p>{{ cancelRefundModal.title }} · {{ currentCluster?.name }}</p>
          </div>
        </header>
        <div class="modal-guide">
          <strong>Được tùy chỉnh mốc thời gian</strong>
          <p>Chủ sân có thể thêm, xóa hoặc đổi khoảng giờ. Bảng mốc phải liên tục từ 0 giờ đến vô hạn và tại mọi thời điểm không được bất lợi hơn chính sách hệ thống.</p>
        </div>
        <label class="status-field">
          Trạng thái chính sách sân
          <select v-model="cancelRefundForm.status">
            <option value="active">Áp dụng ngay cho cụm sân</option>
            <option value="draft">Lưu nháp, chưa áp dụng</option>
          </select>
        </label>
        <div class="tier-editor-head">
          <div>
            <strong>Các mốc của cụm sân</strong>
            <p>Chỉnh từ mốc thấp lên cao. Mốc cuối để trống “Đến dưới giờ”.</p>
          </div>
          <button class="btn secondary" type="button" @click="addCancelRefundTier">
            <AppIcon name="plus" size="16" /> Thêm mốc
          </button>
        </div>
        <div class="tier-editor-list" @input="cancelRefundError = ''" @change="cancelRefundError = ''">
          <article v-for="(tier, index) in cancelRefundForm.tiers" :key="tier.key || index" class="tier-editor-card">
            <header class="tier-editor-card-head">
              <div>
                <span>Mốc {{ index + 1 }}</span>
                <strong>{{ rangeText(tier) }}</strong>
              </div>
              <button
                class="remove-tier"
                type="button"
                :disabled="cancelRefundForm.tiers.length <= 2"
                :aria-label="`Xóa mốc ${index + 1}`"
                @click="removeCancelRefundTier(index)"
              >
                <AppIcon name="trash" size="16" />
              </button>
            </header>

            <div class="system-floor" :class="{ invalid: systemRequirementsForTier(tier).mixedAllow }">
              <strong>Ràng buộc hệ thống</strong>
              <span>{{ systemRequirementSummary(tier) }}</span>
            </div>

            <div class="tier-editor-grid">
              <label>
                Từ giờ
                <input v-model.number="tier.from_hours" type="number" min="0" step="0.5" @change="applySystemMinimums(tier)" />
              </label>
              <label>
                Đến dưới giờ
                <input v-model="tier.to_hours" type="number" min="0" step="0.5" placeholder="Không giới hạn" @change="applySystemMinimums(tier)" />
              </label>
              <label>
                Cho khách hủy
                <select v-model="tier.allow_cancel" :disabled="!systemRequirementsForTier(tier).mixedAllow">
                  <option :value="true">Có</option>
                  <option :value="false">Không</option>
                </select>
              </label>
              <label>
                Tỷ lệ hoàn của sân
                <input
                  v-model.number="tier.refund_percent"
                  type="number"
                  :min="systemRequirementsForTier(tier).minimumRefundPercent"
                  max="100"
                  step="1"
                />
              </label>
              <label class="customer-message-field">
                Nội dung hiển thị cho khách
                <textarea v-model.trim="tier.customer_message" rows="2" maxlength="500" placeholder="Giải thích ngắn gọn kết quả hủy và hoàn tiền." />
              </label>
            </div>

            <div class="confirmation-row">
              <span>Luồng xác nhận hoàn tiền</span>
              <label class="check">
                <input v-model="tier.require_owner_confirm" type="checkbox" :disabled="systemRequirementsForTier(tier).requireOwnerConfirm" />
                Chủ sân xác nhận
              </label>
              <label class="check">
                <input v-model="tier.require_admin_confirm" type="checkbox" :disabled="systemRequirementsForTier(tier).requireAdminConfirm" />
                Admin hoàn tất
              </label>
            </div>
          </article>
        </div>
        <p v-if="cancelRefundError || cancelRefundValidation" class="form-error">{{ cancelRefundError || cancelRefundValidation }}</p>
        <p class="preview">{{ cancelRefundPreview }}</p>
        <footer>
          <button class="btn secondary" type="button" @click="fillSystemDefault">Điền lại theo hệ thống</button>
          <button class="btn secondary" type="button" @click="closeCancelRefund">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving || !!cancelRefundValidation">{{ saving ? 'Đang lưu...' : 'Lưu chính sách sân' }}</button>
        </footer>
      </form>
    </div>

    <div v-if="noticeModal" class="modal-backdrop" @click.self="noticeModal = false">
      <form class="modal" @submit.prevent="saveNotice">
        <h3>{{ noticeForm.id ? 'Sửa quy định' : 'Thêm quy định hiển thị cho khách' }}</h3>
        <label>Tiêu đề<input v-model.trim="noticeForm.title" required /></label>
        <label>Nội dung<textarea v-model.trim="noticeForm.content" rows="6" required></textarea></label>
        <label>Trạng thái
          <select v-model="noticeForm.status">
            <option value="draft">Bản nháp</option>
            <option value="active">Hiển thị</option>
            <option value="inactive">Ẩn</option>
          </select>
        </label>
        <footer>
          <button class="btn secondary" type="button" @click="noticeModal = false">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">{{ saving ? 'Đang lưu...' : 'Lưu' }}</button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import { ownerPolicyService } from '../../services/ownerPolicyService.js';
import { venueClusterService } from '../../services/venueClusters.js';

export default {
  name: 'OwnerPolicies',
  components: { ActionIconButton, AppIcon },
  data() {
    return {
      tab: 'rules',
      loading: false,
      saving: false,
      error: '',
      success: '',
      clusters: [],
      selectedClusterId: localStorage.getItem('selected_cluster') || '',
      currentCluster: null,
      systemPolicies: [],
      venueRules: [],
      customerNotices: [],
      cancelRefundModal: null,
      cancelRefundForm: { id: null, base_policy_rule_id: null, status: 'active', tiers: [] },
      cancelRefundError: '',
      noticeModal: false,
      noticeForm: this.emptyNotice(),
      showScrollTop: false,
      loadRequestId: 0,
    };
  },
  computed: {
    cancelRefundPolicies() {
      return this.systemPolicies.filter((policy) => policy.cancel_refund_configuration?.base_rule_id);
    },
    cancelRefundPreview() {
      const tiers = this.sortedCancelRefundDraft;
      if (!tiers.length) return '';
      return tiers.map((tier) => {
        const action = tier.allow_cancel ? `hoàn ${Number(tier.refund_percent || 0)}%` : 'không cho hủy';
        return `${this.rangeText(tier)}: ${action}.`;
      }).join(' ');
    },
    sortedCancelRefundDraft() {
      return [...(this.cancelRefundForm.tiers || [])].sort(
        (first, second) => Number(first.from_hours || 0) - Number(second.from_hours || 0),
      );
    },
    cancelRefundValidation() {
      return this.validateCancelRefund();
    },
  },
  async mounted() {
    window.addEventListener('owner-cluster-changed', this.handleExternalClusterChange);
    window.addEventListener('scroll', this.handleScroll);
    this.loadClusters();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleExternalClusterChange);
    window.removeEventListener('scroll', this.handleScroll);
  },
  methods: {
    emptyNotice() {
      return { id: null, title: '', content: '', status: 'active' };
    },
    async loadClusters() {
      const initialClusterId = this.selectedClusterId;
      const initialLoad = initialClusterId ? this.load() : null;
      try {
        const response = await venueClusterService.getClusters();
        this.clusters = response.data || [];
        const hasSelectedCluster = this.clusters.some(
          (cluster) => String(cluster.id) === String(this.selectedClusterId),
        );
        if (!hasSelectedCluster && this.clusters[0]) {
          this.selectedClusterId = this.clusters[0].id;
          localStorage.setItem('selected_cluster', this.selectedClusterId);
        }
        if (!initialLoad || String(initialClusterId) !== String(this.selectedClusterId)) {
          await this.load();
        } else {
          await initialLoad;
        }
      } catch (error) {
        this.error = error.message || 'Không tải được danh sách cụm sân.';
      }
    },
    async load() {
      if (!this.selectedClusterId) return;
      const requestId = ++this.loadRequestId;
      const clusterId = this.selectedClusterId;
      this.loading = true;
      this.error = '';
      try {
        localStorage.setItem('selected_cluster', clusterId);
        const response = await ownerPolicyService.list(clusterId);
        if (requestId !== this.loadRequestId) return;
        const data = response.data || {};
        this.currentCluster = data.venue_cluster || this.clusters.find((cluster) => String(cluster.id) === String(clusterId)) || null;
        this.systemPolicies = data.system_policies || [];
        this.venueRules = data.venue_rules || [];
        this.customerNotices = data.customer_notices || [];
      } catch (error) {
        if (requestId !== this.loadRequestId) return;
        this.error = error.message || 'Không thể tải chính sách sân.';
      } finally {
        if (requestId === this.loadRequestId) {
          this.loading = false;
        }
      }
    },
    async changeCluster() {
      localStorage.setItem('selected_cluster', this.selectedClusterId);
      window.dispatchEvent(new CustomEvent('owner-cluster-changed', { detail: { id: this.selectedClusterId } }));
      await this.load();
    },
    handleExternalClusterChange(event) {
      const id = event?.detail?.id || localStorage.getItem('selected_cluster') || this.selectedClusterId;
      if (id && id !== this.selectedClusterId) {
        this.selectedClusterId = id;
      }
      this.load();
    },
    canEditCancelRefund(policy) {
      return Boolean(policy.cancel_refund_configuration?.base_rule_id);
    },
    cancelRefundConfig(policy) {
      return policy.cancel_refund_configuration || {};
    },
    policyStatus(policy) {
      const config = this.cancelRefundConfig(policy);
      if (config.venue_rule_id) {
        return {
          label: config.venue_rule_status === 'draft' ? 'Chính sách sân đang nháp' : 'Đang dùng chính sách sân',
          className: config.venue_rule_status === 'draft' ? 'draft' : 'active',
        };
      }

      return { label: 'Kế thừa khung hệ thống', className: 'neutral' };
    },
    policySystemSummary(policy) {
      return this.cancelRefundConfig(policy).system_summary || policy.business_summary || 'Chưa có tóm tắt chính sách hệ thống.';
    },
    policyVenueSummary(policy) {
      const config = this.cancelRefundConfig(policy);
      return config.effective_summary || config.venue_summary || 'Sân đang dùng mặc định hệ thống.';
    },
    effectiveTiers(policy) {
      const config = this.cancelRefundConfig(policy);
      if (Array.isArray(config.effective_tiers) && config.effective_tiers.length) {
        return config.effective_tiers;
      }
      if (config.effective_source === 'venue' && config.venue_tiers?.length) {
        return config.venue_tiers;
      }
      return config.system_tiers || [];
    },
    systemFloorLine(policy, tier) {
      const requirements = this.systemRequirementsForTier(
        tier,
        this.cancelRefundConfig(policy).system_tiers || [],
      );
      if (!requirements.overlappingTiers.length) return 'Không xác định được ràng buộc hệ thống.';
      if (requirements.mixedAllow) return 'Mốc này cần tách tại ranh giới cho phép hủy của hệ thống.';
      return `Tối thiểu hệ thống: ${requirements.allowCancel ? 'cho hủy' : 'không cho hủy'}, hoàn ${requirements.minimumRefundPercent}%.`;
    },
    systemRequirementsForTier(tier, suppliedSystemTiers = null) {
      const systemTiers = suppliedSystemTiers
        || this.cancelRefundModal?.cancel_refund_configuration?.system_tiers
        || [];
      const overlappingTiers = systemTiers.filter((systemTier) => this.timeRangesOverlap(tier, systemTier));
      const allowValues = [...new Set(overlappingTiers.map((systemTier) => Boolean(systemTier.allow_cancel)))];
      return {
        overlappingTiers,
        mixedAllow: allowValues.length > 1,
        allowCancel: allowValues.length === 1 ? allowValues[0] : Boolean(tier.allow_cancel),
        minimumRefundPercent: overlappingTiers.reduce(
          (minimum, systemTier) => Math.max(minimum, Number(systemTier.refund_percent || 0)),
          0,
        ),
        requireOwnerConfirm: overlappingTiers.some((systemTier) => Boolean(systemTier.require_owner_confirm)),
        requireAdminConfirm: overlappingTiers.some((systemTier) => Boolean(systemTier.require_admin_confirm)),
      };
    },
    systemRequirementSummary(tier) {
      const requirements = this.systemRequirementsForTier(tier);
      if (!requirements.overlappingTiers.length) {
        return 'Khoảng giờ chưa giao với khung hệ thống. Hãy kiểm tra lại mốc bắt đầu và kết thúc.';
      }
      if (requirements.mixedAllow) {
        return 'Khoảng này đi qua các vùng có quyền hủy khác nhau. Hãy tách mốc tại ranh giới hệ thống.';
      }
      const confirmations = [];
      if (requirements.requireOwnerConfirm) confirmations.push('chủ sân xác nhận');
      if (requirements.requireAdminConfirm) confirmations.push('admin hoàn tất');
      const confirmationText = confirmations.length ? ` · Bắt buộc ${confirmations.join(', ')}` : '';
      return `${requirements.allowCancel ? 'Cho hủy' : 'Không cho hủy'} · Hoàn tối thiểu ${requirements.minimumRefundPercent}%${confirmationText}`;
    },
    timeRangesOverlap(firstTier, secondTier) {
      const firstFrom = this.nullableHour(firstTier.from_hours, 0);
      const firstTo = this.nullableHour(firstTier.to_hours, Number.POSITIVE_INFINITY);
      const secondFrom = this.nullableHour(secondTier.from_hours, 0);
      const secondTo = this.nullableHour(secondTier.to_hours, Number.POSITIVE_INFINITY);
      return Math.max(firstFrom, secondFrom) < Math.min(firstTo, secondTo);
    },
    nullableHour(value, fallback) {
      if (value === '' || value === null || value === undefined) return fallback;
      const parsed = Number(value);
      return Number.isFinite(parsed) ? parsed : fallback;
    },
    openCancelRefund(policy) {
      const config = policy.cancel_refund_configuration;
      this.cancelRefundModal = policy;
      this.cancelRefundError = '';
      const sourceTiers = config.venue_tiers?.length ? config.venue_tiers : config.system_tiers || [];
      this.cancelRefundForm = {
        id: config.venue_rule_id || null,
        base_policy_rule_id: config.base_rule_id,
        status: config.venue_rule_status || 'active',
        tiers: sourceTiers
          .map((tier, index) => this.editorTier(tier, index))
          .sort((first, second) => Number(first.from_hours || 0) - Number(second.from_hours || 0)),
      };
      for (const tier of this.cancelRefundForm.tiers) {
        this.applySystemMinimums(tier);
      }
    },
    editorTier(tier, index) {
      return {
        key: tier.key || `venue_tier_${Date.now()}_${index}`,
        from_hours: Number(tier.from_hours ?? 0),
        to_hours: tier.to_hours === null || tier.to_hours === undefined ? '' : Number(tier.to_hours),
        allow_cancel: tier.allow_cancel !== false,
        refund_percent: Number(tier.refund_percent || 0),
        require_owner_confirm: tier.require_owner_confirm !== false,
        require_admin_confirm: tier.require_admin_confirm !== false,
        customer_message: tier.customer_message || '',
      };
    },
    closeCancelRefund() {
      this.cancelRefundModal = null;
      this.cancelRefundError = '';
    },
    fillSystemDefault() {
      if (!this.cancelRefundModal) return;
      const systemTiers = this.cancelRefundModal.cancel_refund_configuration?.system_tiers || [];
      this.cancelRefundForm.tiers = systemTiers
        .map((tier, index) => this.editorTier(tier, index))
        .sort((first, second) => Number(first.from_hours || 0) - Number(second.from_hours || 0));
      this.cancelRefundError = '';
    },
    addCancelRefundTier() {
      const sortedTiers = this.sortedCancelRefundDraft;
      const highestTier = sortedTiers[sortedTiers.length - 1];
      if (!highestTier) return;

      const splitAt = Number(highestTier.from_hours || 0) + 24;
      highestTier.to_hours = splitAt;
      const newTier = {
        ...highestTier,
        key: `venue_tier_${Date.now()}_${sortedTiers.length}`,
        from_hours: splitAt,
        to_hours: '',
      };
      this.applySystemMinimums(highestTier);
      this.applySystemMinimums(newTier);
      this.cancelRefundForm.tiers = [...sortedTiers, newTier];
      this.cancelRefundError = '';
    },
    removeCancelRefundTier(index) {
      const sortedTiers = this.sortedCancelRefundDraft;
      if (sortedTiers.length <= 2) return;

      const removedTier = sortedTiers[index];
      const previousTier = sortedTiers[index - 1];
      const nextTier = sortedTiers[index + 1];
      if (previousTier) previousTier.to_hours = removedTier.to_hours;
      if (!previousTier && nextTier) nextTier.from_hours = 0;
      sortedTiers.splice(index, 1);
      sortedTiers.forEach((tier) => this.applySystemMinimums(tier));
      this.cancelRefundForm.tiers = sortedTiers;
      this.cancelRefundError = '';
    },
    applySystemMinimums(tier) {
      const requirements = this.systemRequirementsForTier(tier);
      if (!requirements.overlappingTiers.length) return;
      if (!requirements.mixedAllow) tier.allow_cancel = requirements.allowCancel;
      if (Number(tier.refund_percent || 0) < requirements.minimumRefundPercent) {
        tier.refund_percent = requirements.minimumRefundPercent;
      }
      if (requirements.requireOwnerConfirm) tier.require_owner_confirm = true;
      if (requirements.requireAdminConfirm) tier.require_admin_confirm = true;
    },
    validateCancelRefund() {
      const tiers = this.sortedCancelRefundDraft;
      if (tiers.length < 2) return 'Bảng mốc cần ít nhất 2 khoảng thời gian.';

      const normalized = tiers.map((tier) => ({
        tier,
        from: Number(tier.from_hours),
        to: tier.to_hours === '' || tier.to_hours === null || tier.to_hours === undefined
          ? null
          : Number(tier.to_hours),
        refundPercent: Number(tier.refund_percent),
      }));
      if (normalized.some((item) => Number.isNaN(item.from) || item.from < 0)) {
        return 'Giờ bắt đầu phải lớn hơn hoặc bằng 0.';
      }
      if (normalized.some((item) => item.to !== null && (Number.isNaN(item.to) || item.to <= item.from))) {
        return 'Giờ kết thúc phải lớn hơn giờ bắt đầu.';
      }
      if (normalized.some((item) => Number.isNaN(item.refundPercent) || item.refundPercent < 0 || item.refundPercent > 100)) {
        return 'Tỷ lệ hoàn phải nằm trong khoảng 0-100%.';
      }
      if (normalized[0]?.from !== 0) return 'Bảng mốc phải bắt đầu từ 0 giờ.';

      for (let index = 0; index < normalized.length; index += 1) {
        const current = normalized[index];
        const next = normalized[index + 1];
        if (next && (current.to === null || current.to !== next.from)) {
          return 'Các mốc phải liền nhau, không chồng hoặc hở khoảng.';
        }
        if (!next && current.to !== null) {
          return 'Mốc cuối phải để trống giờ kết thúc để phủ đến vô hạn.';
        }

        const label = this.rangeText(current.tier);
        const requirements = this.systemRequirementsForTier(current.tier);
        if (!requirements.overlappingTiers.length) return `${label}: chưa nằm trong khung thời gian hệ thống.`;
        if (requirements.mixedAllow) return `${label}: hãy tách mốc tại ranh giới quyền hủy của hệ thống.`;
        if (Boolean(current.tier.allow_cancel) !== requirements.allowCancel) {
          return `${label}: quyền hủy phải giữ đúng quy tắc hệ thống.`;
        }
        if (current.refundPercent < requirements.minimumRefundPercent) {
          return `${label}: mức hoàn không được thấp hơn ${requirements.minimumRefundPercent}%.`;
        }
        if (!current.tier.allow_cancel && current.refundPercent !== 0) {
          return `${label}: nếu không cho hủy thì tỷ lệ hoàn phải bằng 0%.`;
        }
        if (requirements.requireOwnerConfirm && !current.tier.require_owner_confirm) {
          return `${label}: không được bỏ bước chủ sân xác nhận hoàn tiền.`;
        }
        if (requirements.requireAdminConfirm && !current.tier.require_admin_confirm) {
          return `${label}: không được bỏ bước admin xác nhận hoàn tất.`;
        }
      }
      return '';
    },
    async saveCancelRefund() {
      this.cancelRefundError = this.validateCancelRefund();
      if (this.cancelRefundError) return;
      this.saving = true;
      try {
        const response = await ownerPolicyService.saveRule({
          venue_cluster_id: this.selectedClusterId,
          base_policy_rule_id: this.cancelRefundForm.base_policy_rule_id,
          tiers: this.sortedCancelRefundDraft.map((tier, index) => ({
            key: this.rangeKeyForTier(tier, index),
            label: this.rangeText(tier),
            from_hours: Number(tier.from_hours),
            to_hours: tier.to_hours === '' || tier.to_hours === null || tier.to_hours === undefined
              ? null
              : Number(tier.to_hours),
            allow_cancel: Boolean(tier.allow_cancel),
            refund_percent: Number(tier.refund_percent || 0),
            require_owner_confirm: Boolean(tier.require_owner_confirm),
            require_admin_confirm: Boolean(tier.require_admin_confirm),
            customer_message: tier.customer_message || '',
          })),
          status: this.cancelRefundForm.status,
        });
        this.success = response.message;
        this.closeCancelRefund();
        await this.load();
      } catch (error) {
        this.cancelRefundError = error.message || 'Không thể lưu chính sách sân.';
      } finally {
        this.saving = false;
      }
    },
    rangeKeyForTier(tier, index) {
      const fromKey = String(Number(tier.from_hours || 0)).replace('.', '_');
      const toKey = tier.to_hours === '' || tier.to_hours === null || tier.to_hours === undefined
        ? 'up'
        : String(Number(tier.to_hours)).replace('.', '_');
      return `venue_${index}_${fromKey}_${toKey}`;
    },
    async resetPolicy(policy) {
      const venueRuleId = policy.cancel_refund_configuration?.venue_rule_id;
      if (!venueRuleId) return;
      this.saving = true;
      try {
        const response = await ownerPolicyService.resetRule(venueRuleId, this.selectedClusterId);
        this.success = response.message;
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể dùng lại mặc định hệ thống.';
      } finally {
        this.saving = false;
      }
    },
    rangeText(tier) {
      if (tier.to_hours === '' || tier.to_hours === null || tier.to_hours === undefined) return `Từ ${tier.from_hours} giờ trở lên`;
      if (Number(tier.from_hours) === 0) return `Dưới ${tier.to_hours} giờ`;
      return `Từ ${tier.from_hours} đến dưới ${tier.to_hours} giờ`;
    },
    openNotice(notice = null) {
      this.noticeForm = notice ? { ...notice } : this.emptyNotice();
      this.noticeModal = true;
    },
    async saveNotice() {
      this.saving = true;
      try {
        const response = this.noticeForm.id
          ? await ownerPolicyService.updateNotice(this.noticeForm.id, this.noticeForm)
          : await ownerPolicyService.createNotice(this.noticeForm);
        this.success = response.message;
        this.noticeModal = false;
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu quy định.';
      } finally {
        this.saving = false;
      }
    },
    handleScroll() {
      this.showScrollTop = window.scrollY > 150;
    },
  },
};
</script>

<style scoped>
.page { display: grid; gap: 16px; }
.cluster-selection-bar { margin-bottom: 8px; }
.section-head, .card-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
.section-head h3, .policy-card h3, .modal h3 { margin: 0 0 6px; }
.section-head p, .summary-block p, .notice-card p, .modal-head p, small { margin: 0; color: var(--admin-muted); }
.cluster-picker, .cluster-badge { display: grid; gap: 6px; min-width: 260px; font-weight: 800; }
.cluster-badge { background: var(--admin-surface-muted); border: 1px solid var(--admin-border); border-radius: 10px; padding: 10px 12px; }
.tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.tabs button { border: 1px solid var(--admin-border); background: var(--admin-surface); border-radius: 8px; padding: 10px 14px; font-weight: 500; cursor: pointer; }
.tabs .active { background: var(--admin-primary); border-color: var(--admin-primary); color: var(--admin-primary-text); }
.policy-section { display: grid; gap: 14px; }
.inheritance-flow { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.inheritance-flow article { display: grid; grid-template-columns: auto 1fr; gap: 5px 10px; align-items: start; background: var(--admin-surface); border: 1px solid #dbeafe; border-radius: 12px; padding: 14px; }
.inheritance-flow span { grid-row: span 2; display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 999px; background: #dcfce7; color: #166534; font-weight: 900; }
.inheritance-flow strong { color: #0f172a; }
.inheritance-flow p { margin: 0; color: var(--admin-muted); line-height: 1.45; }
.policy-summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.policy-card, .panel, .modal, .notice-card { background: var(--admin-surface); border: 1px solid #e2e8f0; border-radius: 12px; }
.policy-card, .panel, .modal { padding: 18px; }
.policy-card { display: grid; gap: 12px; }
.refund-card { grid-template-columns: 1fr; }
.type { color: var(--admin-muted); font-size: 13px; font-weight: 800; }
.summary-block { display: grid; gap: 4px; padding: 12px; background: var(--admin-surface-muted); border-radius: 10px; }
.summary-block.venue { background: #f0fdf4; border: 1px solid #bbf7d0; }
.summary-block span { color: var(--admin-muted); font-weight: 900; font-size: 13px; }
.tier-preview { display: grid; gap: 10px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; }
.tier-preview-head { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
.tier-preview-head strong { color: #0f172a; }
.tier-preview-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.tier-preview-card { display: grid; gap: 4px; padding: 10px; border-radius: 10px; background: var(--admin-surface-muted); }
.tier-preview-card span { color: #0f172a; font-size: 13px; font-weight: 900; }
.tier-preview-card strong { color: #166534; font-size: 13px; }
.tier-preview-card p { margin: 0; color: var(--admin-muted); font-size: 13px; }
.policy-card footer, .modal footer { display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
.notice-card { display: grid; grid-template-columns: 1fr auto auto; align-items: center; gap: 12px; padding: 12px; margin-top: 10px; }
.btn { border: 0; border-radius: 8px; font-weight: 800; cursor: pointer; padding: 10px 14px; display: inline-flex; align-items: center; gap: 8px; }
.primary { background: #16a34a; color: #fff; }
.secondary { background: var(--admin-surface-muted); color: #0f172a; }
.state { padding: 18px; color: var(--admin-muted); background: var(--admin-surface-muted); border-radius: 10px; }
.preview { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px; border-radius: 10px; margin: 0; }
.form-error { background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px; border-radius: 10px; margin: 0; font-weight: 800; }
.badge { border-radius: 999px; padding: 5px 9px; font-size: 12px; font-weight: 800; background: var(--admin-surface-muted); color: var(--admin-muted); white-space: nowrap; }
.badge.active { background: #dcfce7; color: #166534; }
.badge.neutral { background: var(--admin-border); color: var(--admin-text); }
.badge.draft { background: #fef3c7; color: #92400e; }
.badge.inactive { background: #fee2e2; color: #b91c1c; }
.alert { padding: 12px; border-radius: 10px; font-weight: 700; }
.error { background: #fee2e2; color: #b91c1c; }
.success { background: #dcfce7; color: #166534; }
.warning { background: #fef3c7; color: #92400e; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .56); display: grid; place-items: center; z-index: 500; padding: 20px; }
.modal { width: min(680px, calc(100vw - 32px)); display: grid; gap: 14px; max-height: calc(100vh - 40px); overflow: auto; }
.modal.wide { width: min(1080px, calc(100vw - 32px)); }
.modal-guide { display: grid; gap: 4px; border: 1px solid #bbf7d0; border-radius: 10px; background: #f0fdf4; color: #166534; padding: 12px; }
.modal-guide p { margin: 0; color: #166534; }
.status-field { max-width: 360px; }
.tier-editor-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.tier-editor-head div { display: grid; gap: 4px; }
.tier-editor-head p { margin: 0; color: var(--admin-muted); }
.tier-editor-list { display: grid; gap: 12px; }
.tier-editor-card { display: grid; gap: 12px; border: 1px solid #dbe3ef; border-radius: 12px; padding: 14px; background: var(--admin-surface); }
.tier-editor-card-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.tier-editor-card-head div { display: grid; gap: 3px; }
.tier-editor-card-head span { color: var(--admin-muted); font-size: 12px; font-weight: 900; text-transform: uppercase; }
.tier-editor-card-head strong { color: var(--admin-text); }
.remove-tier { width: 36px; height: 36px; display: inline-grid; place-items: center; border: 1px solid #fecaca; border-radius: 8px; background: #fff1f2; color: #be123c; cursor: pointer; }
.remove-tier:disabled { cursor: not-allowed; opacity: .45; }
.system-floor { display: grid; gap: 3px; padding: 10px 12px; border: 1px solid #bbf7d0; border-radius: 9px; background: #f0fdf4; color: #166534; }
.system-floor strong { font-size: 12px; text-transform: uppercase; }
.system-floor span { font-size: 13px; line-height: 1.45; }
.system-floor.invalid { border-color: #fdba74; background: #fff7ed; color: #9a3412; }
.tier-editor-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; align-items: start; }
.customer-message-field { grid-column: span 2; }
.confirmation-row { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding-top: 4px; }
.confirmation-row > span { color: var(--admin-muted); font-size: 13px; font-weight: 900; }
.check { display: flex; gap: 8px; align-items: center; font-weight: 700; }
label { display: grid; gap: 6px; font-weight: 800; }
input, select, textarea { box-sizing: border-box; border: 1px solid #dbe3ef; border-radius: 8px; padding: 10px; font: inherit; width: 100%; min-width: 0; }
input:disabled, select:disabled { background: var(--admin-surface-muted); color: var(--admin-muted); cursor: not-allowed; }
textarea { resize: vertical; }
.modal > footer { position: sticky; bottom: -18px; margin: 0 -18px -18px; padding: 14px 18px 18px; border-top: 1px solid var(--admin-border); background: var(--admin-surface); z-index: 2; }
@media (max-width: 900px) {
  .inheritance-flow, .policy-summary-grid, .tier-preview-grid { grid-template-columns: 1fr; }
  .page-head, .section-head, .notice-card { grid-template-columns: 1fr; flex-direction: column; }
  .cluster-picker, .cluster-badge { width: 100%; min-width: 0; }
  .tier-editor-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .customer-message-field { grid-column: 1 / -1; }
}
@media (max-width: 620px) {
  .modal-backdrop { padding: 8px; align-items: end; }
  .modal, .modal.wide { width: calc(100vw - 16px); max-height: calc(100vh - 16px); border-radius: 14px 14px 0 0; }
  .tier-editor-head, .tier-editor-card-head { align-items: flex-start; }
  .tier-editor-head { flex-direction: column; }
  .tier-editor-grid { grid-template-columns: 1fr; }
  .customer-message-field { grid-column: auto; }
  .confirmation-row { align-items: flex-start; flex-direction: column; gap: 10px; }
  .modal > footer { justify-content: stretch; }
  .modal > footer .btn { justify-content: center; flex: 1 1 100%; }
}
</style>

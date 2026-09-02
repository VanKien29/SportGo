<template>
  <div class="cluster-profile-surface standalone">
    <!-- Floating Add Button -->
    <div v-if="tab === 'notices'" class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
      <button class="btn-float-add" type="button" @click="openNotice()" title="Thêm quy định">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Thêm quy định</span>
      </button>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>
    <div v-if="currentCluster && currentCluster.status !== 'active'" class="alert warning">
      Cụm sân đang ở trạng thái {{ currentCluster.status }}. Một số thay đổi có thể cần admin kiểm tra.
    </div>

    <!-- Integrated AppTabs -->
    <div class="policies-header-hero">
      <div class="hero-integrated-tabs">
        <AppTabs
          :tabs="policyTabsForAppTabs"
          :model-value="tab"
          @update:model-value="selectPolicyTab"
        />
      </div>
    </div>

    <div class="profile-section-card policies-main-content">

      <section v-if="tab === 'rules'" class="policy-section">
        <div class="inheritance-flow">
          <article>
            <span>1</span>
            <div class="flow-content">
              <strong>Khung hệ thống</strong>
              <p>Admin cấu hình bảng mốc hủy & hoàn làm chuẩn tối thiểu.</p>
            </div>
          </article>
          <article>
            <span>2</span>
            <div class="flow-content">
              <strong>Cụm sân đang chọn</strong>
              <p>{{ currentCluster?.name || 'Chọn cụm sân để cấu hình chính sách riêng.' }}</p>
            </div>
          </article>
          <article>
            <span>3</span>
            <div class="flow-content">
              <strong>Chính sách sân</strong>
              <p>Chủ sân có thể tạo cấu hình riêng nhưng không được bất lợi hơn khung hệ thống.</p>
            </div>
          </article>
        </div>

        <div v-if="loading" class="table-state-card">
          <div class="spinner-sm"></div>
          <span>Đang tải chính sách sân...</span>
        </div>

        <div v-else-if="error" class="table-state-card policy-load-error">
          <span>{{ error }}</span>
          <button class="btn secondary" type="button" @click="retryLoad">Tải lại</button>
        </div>

        <article v-for="policy in cancelRefundPolicies" :key="policy.id" class="policy-card refund-card">
          <div class="card-head">
            <div>
              <h3>{{ policy.title }}</h3>
              <span class="type">{{ policy.policy_type_label }}</span>
            </div>
            <span class="status-pill" :class="policyStatus(policy).className">{{ policyStatus(policy).label }}</span>
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

          <div class="tier-preview" v-if="policy.cancel_refund_configuration?.system_tiers?.length">
            <div class="tier-preview-head">
              <strong>Bảng mốc hủy & hoàn</strong>
              <small class="cell-sub">{{ policy.cancel_refund_configuration.effective_source_label }}</small>
            </div>
            <div class="tier-preview-grid">
              <div
                v-for="tier in policy.cancel_refund_configuration.system_tiers"
                :key="tier.key"
                class="tier-preview-card"
              >
                <span>{{ rangeText(tier) }}</span>
                <strong>Hệ thống: {{ tier.allow_cancel ? `hoàn tối thiểu ${tier.refund_percent}%` : 'không cho hủy' }}</strong>
                <p>{{ venueTierLine(policy, tier) }}</p>
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

        <div v-if="!loading && !error && cancelRefundPolicies.length === 0" class="table-state-card">
          <span>Chưa có chính sách hủy & hoàn nào đang active và cho phép sân cấu hình riêng.</span>
        </div>
      </section>

      <section v-if="tab === 'notices'" class="panel">
        <div class="section-head">
          <div>
            <h3>Quy định hiển thị cho khách</h3>
            <p>Nội dung này chỉ để khách đọc, không tác động tự động đến hủy, hoàn tiền hoặc booking.</p>
          </div>
        </div>
        <div v-if="loading" class="table-state-card">
          <div class="spinner-sm"></div>
          <span>Đang tải quy định hiển thị...</span>
        </div>
        <div v-else-if="error" class="table-state-card policy-load-error">
          <span>{{ error }}</span>
          <button class="btn secondary" type="button" @click="retryLoad">Tải lại</button>
        </div>
        <div v-else-if="customerNotices.length === 0" class="table-state-card">
          <span>Chưa có nội quy hiển thị cho khách.</span>
        </div>
        <template v-else>
          <article v-for="notice in customerNotices" :key="notice.id" class="notice-card">
            <div>
              <strong>{{ notice.title }}</strong>
              <p>{{ notice.content }}</p>
              <small v-if="notice.read_only" class="cell-sub">Nội dung kế thừa từ chính sách hệ thống, chỉ đọc.</small>
            </div>
            <span class="status-pill" :class="notice.status">{{ notice.status_label }}</span>
            <ActionIconButton v-if="!notice.read_only" icon="pencil" label="Sửa quy định" @click="openNotice(notice)" />
          </article>
        </template>
      </section>
    </div>

    <!-- Modal Cancel Refund -->
    <div v-if="cancelRefundModal" class="modal-backdrop" @click.self="closeCancelRefund">
      <form class="modal wide" @submit.prevent="saveCancelRefund">
        <header class="modal-head">
          <div>
            <h3>{{ cancelRefundForm.id ? 'Sửa chính sách sân' : 'Tạo chính sách sân' }}</h3>
            <p>{{ cancelRefundModal.title }} · {{ currentCluster?.name }}</p>
          </div>
        </header>
        <div class="policy-modal-scroll">
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
          <div class="services-table-wrapper">
            <table class="services-data-table tiers-table">
              <thead>
                <tr>
                  <th>Mốc thời gian</th>
                  <th>Khung hệ thống</th>
                  <th>Chính sách sân</th>
                  <th>Xác nhận hoàn</th>
                  <th>Nội dung cho khách</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(tier, index) in cancelRefundForm.tiers" :key="tier.key || index">
                  <td>
                    <strong>{{ tier.label }}</strong>
                    <small class="cell-sub">{{ rangeText(tier) }}</small>
                  </td>
                  <td>
                    <span>Hoàn tối thiểu {{ tier.system_refund_percent }}%</span>
                    <small class="cell-sub">{{ tier.system_allow_cancel ? 'Hệ thống cho hủy' : 'Hệ thống không cho hủy' }}</small>
                  </td>
                  <td>
                    <label class="check">
                      <input v-model="tier.allow_cancel" type="checkbox" disabled />
                      <span>{{ tier.allow_cancel ? 'Có' : 'Không' }}</span>
                    </label>
                    <label>
                      Tỷ lệ hoàn của sân
                      <input v-model.number="tier.refund_percent" type="number" min="0" max="100" step="1" />
                    </label>
                  </td>
                  <td class="confirm-cell">
                    <label class="check"><input v-model="tier.require_owner_confirm" type="checkbox" :disabled="tier.system_require_owner_confirm" /> Chủ sân</label>
                  </td>
                  <td>
                    <textarea v-model.trim="tier.customer_message" rows="2" maxlength="500" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="cancelRefundError || cancelRefundValidation" class="form-error">{{ cancelRefundError || cancelRefundValidation }}</p>
          <p class="preview">{{ cancelRefundPreview }}</p>
        </div>
        <footer>
          <button class="btn secondary" type="button" @click="fillSystemDefault">Điền lại theo hệ thống</button>
          <button class="btn secondary" type="button" @click="closeCancelRefund">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving || !!cancelRefundValidation">{{ saving ? 'Đang lưu...' : 'Lưu chính sách sân' }}</button>
        </footer>
      </form>
    </div>

    <!-- Modal Notice -->
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
  </div>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import AppTabs from '../../components/common/AppTabs.vue';
import { ownerPolicyService } from '../../services/ownerPolicyService.js';
import { venueClusterService } from '../../services/venueClusters.js';

export default {
  name: 'OwnerPolicies',
  components: { ActionIconButton, AppIcon, AppTabs },
  data() {
    return {
      tab: 'rules',
      // Start in a pending state so an empty response is never shown before the bootstrap request runs.
      loading: true,
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
    policyTabsForAppTabs() {
      return [
        { key: 'rules', value: 'rules', label: 'Quy tắc áp dụng hệ thống' },
        { key: 'notices', value: 'notices', label: 'Quy định hiển thị cho khách' },
      ];
    },
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
    await this.loadClusters();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleExternalClusterChange);
    window.removeEventListener('scroll', this.handleScroll);
  },
  methods: {
    selectPolicyTab(tabVal) {
      this.tab = String(tabVal || 'rules');
    },
    emptyNotice() {
      return { id: null, title: '', content: '', status: 'active' };
    },
    async loadClusters() {
      try {
        const response = await venueClusterService.getClusters({ compact: 1 });
        const payload = response?.data?.data && Array.isArray(response.data.data)
          ? response.data.data
          : (Array.isArray(response?.data) ? response.data : (Array.isArray(response) ? response : []));
        this.clusters = payload;

        if (!this.clusters.length) {
          this.currentCluster = null;
          this.systemPolicies = [];
          this.venueRules = [];
          this.customerNotices = [];
          this.error = 'Chưa có cụm sân để tải chính sách sân.';
          return;
        }

        const hasSelectedCluster = this.clusters.some(
          (cluster) => String(cluster.id) === String(this.selectedClusterId),
        );
        if (!hasSelectedCluster && this.clusters[0]) {
          this.selectedClusterId = this.clusters[0].id;
          localStorage.setItem('selected_cluster', this.selectedClusterId);
        }
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không tải được danh sách cụm sân.';
        this.currentCluster = null;
        this.systemPolicies = [];
        this.venueRules = [];
        this.customerNotices = [];
      } finally {
        this.loading = false;
      }
    },
    async load() {
      if (!this.selectedClusterId) {
        this.loading = false;
        return;
      }
      const requestId = ++this.loadRequestId;
      const clusterId = this.selectedClusterId;
      this.loading = true;
      this.error = '';
      try {
        localStorage.setItem('selected_cluster', clusterId);
        const response = await ownerPolicyService.list(clusterId);
        if (requestId !== this.loadRequestId) return;
        const data = response?.data?.data && !Array.isArray(response.data.data)
          ? response.data.data
          : (response?.data || response || {});
        this.currentCluster = data.venue_cluster || this.clusters.find((cluster) => String(cluster.id) === String(clusterId)) || null;
        this.systemPolicies = data.system_policies || [];
        this.venueRules = data.venue_rules || [];
        this.customerNotices = data.effective_customer_notices || data.customer_notices || [];
      } catch (error) {
        if (requestId !== this.loadRequestId) return;
        this.error = error.message || 'Không thể tải chính sách sân.';
      } finally {
        if (requestId === this.loadRequestId) {
          this.loading = false;
        }
      }
    },
    async retryLoad() {
      this.error = '';
      if (this.selectedClusterId) {
        await this.load();
        return;
      }

      this.loading = true;
      await this.loadClusters();
    },
    async changeCluster() {
      localStorage.setItem('selected_cluster', this.selectedClusterId);
      window.dispatchEvent(new CustomEvent('owner-cluster-changed', { detail: { id: this.selectedClusterId } }));
      await this.load();
    },
    async handleExternalClusterChange(event) {
      const id = event?.detail?.id || localStorage.getItem('selected_cluster') || this.selectedClusterId;
      if (!id || String(id) === String(this.selectedClusterId)) return;
      this.selectedClusterId = id;
      await this.load();
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
    venueTierLine(policy, systemTier) {
      const config = this.cancelRefundConfig(policy);
      const venueTiers = Array.isArray(config.venue_tiers) ? config.venue_tiers : [];

      if (!config.venue_rule_id || !venueTiers.length) {
        return 'Sân đang dùng khung hệ thống.';
      }

      const overlappingTiers = venueTiers.filter((venueTier) => this.timeRangesOverlap(systemTier, venueTier));
      if (!overlappingTiers.length) {
        return 'Chưa xác định được mốc áp dụng của chính sách sân.';
      }

      const detail = overlappingTiers
        .map((venueTier) => {
          const action = venueTier.allow_cancel
            ? `hoàn ${Number(venueTier.refund_percent || 0)}%`
            : 'không cho hủy';
          return `${this.rangeText(venueTier)}: ${action}`;
        })
        .join(' · ');

      return config.venue_rule_status === 'draft'
        ? `Bản nháp của sân: ${detail}`
        : `Chính sách sân: ${detail}`;
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
.cluster-profile-surface.standalone {
  width: 100%;
  min-width: 0;
  background: transparent;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.policies-header-hero {
  background: var(--admin-surface, #ffffff);
  padding: 10px 10px 0 10px;
  display: flex;
  align-items: center;
}

/* Single unified main surface */
.profile-section-card.policies-main-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 10px;
  background: var(--admin-surface, #ffffff);
  border: none;
  border-radius: 0;
  box-shadow: none;
  margin-top: 0 !important;
}

.hero-integrated-tabs {
  flex: 1;
}

.cluster-selection-bar {
  margin-bottom: 4px;
}

.cluster-picker select {
  height: 38px;
  padding: 0 12px;
  border: 1px solid var(--admin-border, #cbd5e1);
  border-radius: 6px;
  background: var(--admin-surface, #ffffff);
  font: inherit;
  font-size: 13px;
  color: var(--admin-text, #101c15);
}

.cluster-badge {
  padding: 8px 12px;
  border-radius: 6px;
  background: var(--admin-bg-soft, #f7fbf5);
  font-size: 13px;
  color: var(--admin-text, #101c15);
}

.alert {
  border-radius: 8px;
  padding: 14px 16px;
  font-weight: 500;
  font-size: 13px;
}

.alert.error {
  background: var(--admin-danger-soft, rgba(239, 68, 68, 0.08));
  color: var(--admin-danger, #ef4444);
}

.alert.success {
  background: var(--admin-success-soft, rgba(16, 185, 129, 0.08));
  color: var(--admin-primary, #22a653);
}

.alert.warning {
  background: var(--admin-warning-soft, rgba(245, 158, 11, 0.08));
  color: var(--admin-warning, #d97706);
}

.policy-section,
.panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.inheritance-flow {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.inheritance-flow article {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 8px;
  padding: 12px;
}

.inheritance-flow span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 999px;
  background: var(--admin-primary, #22a653);
  color: #ffffff;
  font-size: 12px;
  font-weight: 500;
  flex-shrink: 0;
}

.flow-content strong {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: var(--admin-text, #101c15);
}

.flow-content p {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--admin-muted, #64748b);
  line-height: 1.4;
}

.policy-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 12px 0;
  background: transparent;
  border: none;
  border-radius: 0;
}

.card-head,
.section-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.card-head h3,
.section-head h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 500;
  color: var(--admin-text, #101c15);
}

.type {
  font-size: 12px;
  color: var(--admin-muted, #64748b);
}

.policy-summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.summary-block {
  padding: 12px;
  background: var(--admin-hover, #f8fafc);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 6px;
}

.summary-block.venue {
  background: var(--admin-bg-soft, #f7fbf5);
  border-color: var(--admin-border, #cfded1);
}

.summary-block span {
  display: block;
  font-size: 12px;
  color: var(--admin-muted, #64748b);
}

.summary-block p {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--admin-text, #101c15);
}

.tier-preview {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 0;
  background: transparent;
  border: none;
  border-radius: 0;
}

.tier-preview-head strong {
  font-size: 13px;
  font-weight: 500;
}

.tier-preview-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.tier-preview-card {
  padding: 10px;
  border-radius: 6px;
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  font-size: 12px;
}

.tier-preview-card span {
  display: block;
  color: var(--admin-text, #101c15);
}

.tier-preview-card strong {
  display: block;
  margin-top: 2px;
  color: var(--admin-primary, #22a653);
  font-weight: 500;
}

.tier-preview-card p {
  margin: 2px 0 0;
  color: var(--admin-muted, #64748b);
}

.notice-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 14px;
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border-soft, #e2e8f0);
  border-radius: 8px;
}

.notice-card strong {
  font-size: 14px;
  color: var(--admin-text, #101c15);
}

.notice-card p {
  margin: 3px 0 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
}

/* Status Pills */
.status-pill {
  display: inline-flex;
  border-radius: 999px;
  padding: 3px 9px;
  font-size: 11px;
  font-weight: 400;
  white-space: nowrap;
}

.status-pill.active {
  background: var(--admin-success-soft, rgba(16, 185, 129, 0.08));
  color: var(--admin-primary, #22a653);
}

.status-pill.draft {
  background: var(--admin-warning-soft, rgba(245, 158, 11, 0.08));
  color: var(--admin-warning, #d97706);
}

.status-pill.neutral,
.status-pill.inactive {
  background: var(--admin-surface-muted, #f1f5f9);
  color: var(--admin-muted, #64748b);
}

/* State Cards */
.table-state-card {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 8px;
  padding: 36px 20px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px dashed var(--admin-border, #cfded1);
  border-radius: 8px;
  color: var(--admin-muted, #2f3d34);
  font-size: 13.5px;
  font-weight: 400;
  text-align: center;
}

.spinner-sm {
  width: 18px;
  height: 18px;
  border: 2px solid var(--admin-border, #cfded1);
  border-top-color: var(--admin-primary, #22a653);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Modal Services Data Table */
.services-table-wrapper {
  height: min(520px, calc(100vh - 360px));
  min-height: 0;
  overflow-x: auto !important;
  overflow-y: auto !important;
  border: none;
  border-radius: 10px;
  scrollbar-gutter: stable;
}

.services-data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  text-align: left;
}

.services-data-table th {
  background: var(--admin-bg-soft, #f7fbf5);
  color: var(--admin-text, #101c15);
  font-weight: 400;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 12px 14px;
  border-bottom: none;
}

.services-data-table td {
  padding: 12px 14px;
  border-bottom: none;
  color: var(--admin-text, #101c15);
  font-weight: 400;
  vertical-align: top;
}

.services-data-table tbody tr {
  transition: background-color 0.12s ease;
}

.services-data-table tbody tr:hover {
  background: var(--admin-hover, #edf7ed);
}

.cell-sub {
  display: block;
  margin-top: 3px;
  color: var(--admin-muted, #64748b);
  font-size: 12px;
}

.floating-add-container {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 400;
}

.btn-float-add {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 44px;
  padding: 0 18px;
  border: none;
  border-radius: 999px;
  background: var(--admin-primary, #22a653);
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 500;
  box-shadow: 0 4px 16px rgba(34, 166, 83, 0.35);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-float-add:hover {
  background: var(--admin-primary-dark, #15733a);
  transform: translateY(-1px);
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.58);
  display: grid;
  place-items: center;
  z-index: 500;
  padding: 20px;
}

.modal {
  width: min(680px, calc(100vw - 32px));
  display: flex;
  flex-direction: column;
  gap: 14px;
  box-sizing: border-box;
  max-height: calc(100vh - 40px);
  overflow-y: auto !important;
  overflow-x: hidden;
  padding: 20px;
  background: #ffffff;
  border: 1px solid var(--admin-border, #e2e8f0);
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
}

.modal.wide {
  width: min(1180px, calc(100vw - 32px));
  height: min(900px, calc(100vh - 40px));
  overflow: hidden !important;
}

.policy-modal-scroll {
  min-height: 0;
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow-y: auto;
  overflow-x: hidden;
  overscroll-behavior: contain;
  padding-right: 4px;
}

.modal-head h3 {
  margin: 0;
  font-size: 17px;
  font-weight: 500;
}

.modal-guide {
  padding: 12px;
  border-radius: 6px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px solid var(--admin-border, #cfded1);
  font-size: 12.5px;
}

.modal-guide p {
  margin: 3px 0 0;
  color: var(--admin-muted, #64748b);
}

.status-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 320px;
  font-size: 13px;
}

.check {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
}

input,
select,
textarea {
  border: 1px solid var(--admin-border, #cbd5e1);
  border-radius: 6px;
  padding: 9px 12px;
  font: inherit;
  font-size: 13px;
  background: #ffffff;
  color: var(--admin-text, #101c15);
}

input:focus,
select:focus,
textarea:focus {
  outline: 0;
  border-color: var(--admin-primary, #22a653);
}

footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn {
  height: 36px;
  padding: 0 16px;
  border: 0;
  border-radius: 6px;
  font: inherit;
  font-size: 13px;
  font-weight: 400;
  cursor: pointer;
  white-space: nowrap;
}

.btn.primary {
  background: var(--admin-primary, #22a653);
  color: #ffffff;
}

.btn.secondary {
  background: var(--admin-hover, #f8fafc);
  border: 1px solid var(--admin-border, #e2e8f0);
  color: var(--admin-text, #101c15);
}

.preview {
  padding: 12px;
  border-radius: 6px;
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px solid var(--admin-border, #cfded1);
  color: var(--admin-primary, #22a653);
  font-size: 12.5px;
  margin: 0;
}

.form-error {
  padding: 12px;
  border-radius: 6px;
  background: var(--admin-danger-soft, rgba(239, 68, 68, 0.08));
  color: var(--admin-danger, #ef4444);
  font-size: 12.5px;
  margin: 0;
}

@media (max-width: 900px) {
  .inheritance-flow,
  .policy-summary-grid,
  .tier-preview-grid {
    grid-template-columns: 1fr;
  }
}
</style>

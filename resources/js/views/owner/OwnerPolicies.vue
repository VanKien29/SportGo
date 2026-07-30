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

    <div class="profile-section-card policies-main-content">

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

        <div v-if="!loading && cancelRefundPolicies.length === 0" class="table-state-card">
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
        <div v-if="customerNotices.length === 0" class="table-state-card">
          <span>Chưa có nội quy hiển thị cho khách.</span>
        </div>
        <article v-for="notice in customerNotices" :key="notice.id" class="notice-card">
          <div>
            <strong>{{ notice.title }}</strong>
            <p>{{ notice.content }}</p>
          </div>
          <span class="status-pill" :class="notice.status">{{ notice.status_label }}</span>
          <ActionIconButton icon="pencil" label="Sửa quy định" @click="openNotice(notice)" />
        </article>
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
        <div class="modal-guide">
          <strong>Kế thừa từ hệ thống</strong>
          <p>Khoảng giờ lấy theo khung hệ thống. Chủ sân chỉ được đặt mức hoàn bằng hoặc tốt hơn cho khách, và không được bỏ bước xác nhận bắt buộc.</p>
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
                  <label class="check"><input v-model="tier.require_admin_confirm" type="checkbox" :disabled="tier.system_require_admin_confirm" /> Admin</label>
                </td>
                <td>
                  <textarea v-model.trim="tier.customer_message" rows="2" maxlength="500" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="cancelRefundError" class="form-error">{{ cancelRefundError }}</p>
        <p class="preview">{{ cancelRefundPreview }}</p>
        <footer>
          <button class="btn secondary" type="button" @click="fillSystemDefault">Điền lại theo hệ thống</button>
          <button class="btn secondary" type="button" @click="closeCancelRefund">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">{{ saving ? 'Đang lưu...' : 'Lưu chính sách sân' }}</button>
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
      const tiers = this.cancelRefundForm.tiers || [];
      if (!tiers.length) return '';
      return tiers.map((tier) => {
        const action = tier.allow_cancel ? `hoàn ${Number(tier.refund_percent || 0)}%` : 'không cho hủy';
        return `${tier.label}: ${action}.`;
      }).join(' ');
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
    selectPolicyTab(tabVal) {
      this.tab = String(tabVal || 'rules');
    },
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
    venueTierLine(policy, systemTier) {
      const config = this.cancelRefundConfig(policy);
      const venue = (config.venue_tiers || []).find((tier) => String(tier.key) === String(systemTier.key));
      if (!venue) return 'Sân đang kế thừa đúng mốc hệ thống.';
      const prefix = config.effective_source === 'venue' ? 'Sân đang áp dụng' : 'Bản nháp sân';
      return venue.allow_cancel
        ? `${prefix}: hoàn ${Number(venue.refund_percent || 0)}%.`
        : `${prefix}: không cho hủy.`;
    },
    openCancelRefund(policy) {
      const config = policy.cancel_refund_configuration;
      const systemByKey = new Map((config.system_tiers || []).map((tier) => [tier.key, tier]));
      const venueByKey = new Map((config.venue_tiers || []).map((tier) => [tier.key, tier]));
      this.cancelRefundModal = policy;
      this.cancelRefundError = '';
      this.cancelRefundForm = {
        id: config.venue_rule_id || null,
        base_policy_rule_id: config.base_rule_id,
        status: config.venue_rule_status || 'active',
        tiers: (config.system_tiers || []).map((systemTier) => {
          const venueTier = venueByKey.get(systemTier.key) || systemTier;
          return {
            ...venueTier,
            from_hours: systemTier.from_hours,
            to_hours: systemTier.to_hours,
            system_refund_percent: Number(systemTier.refund_percent || 0),
            system_allow_cancel: Boolean(systemTier.allow_cancel),
            system_require_owner_confirm: Boolean(systemTier.require_owner_confirm),
            system_require_admin_confirm: Boolean(systemTier.require_admin_confirm),
          };
        }),
      };
      for (const tier of this.cancelRefundForm.tiers) {
        const system = systemByKey.get(tier.key);
        if (system?.allow_cancel) tier.allow_cancel = true;
        if (system?.require_owner_confirm) tier.require_owner_confirm = true;
        if (system?.require_admin_confirm) tier.require_admin_confirm = true;
      }
    },
    closeCancelRefund() {
      this.cancelRefundModal = null;
      this.cancelRefundError = '';
    },
    fillSystemDefault() {
      if (!this.cancelRefundModal) return;
      const systemTiers = this.cancelRefundModal.cancel_refund_configuration?.system_tiers || [];
      const systemByKey = new Map(systemTiers.map((tier) => [tier.key, tier]));
      this.cancelRefundForm.tiers = this.cancelRefundForm.tiers.map((tier) => {
        const system = systemByKey.get(tier.key) || tier;
        return {
          ...tier,
          allow_cancel: Boolean(system.allow_cancel),
          refund_percent: Number(system.refund_percent || 0),
          require_owner_confirm: Boolean(system.require_owner_confirm),
          require_admin_confirm: Boolean(system.require_admin_confirm),
          customer_message: system.customer_message || tier.customer_message || '',
        };
      });
    },
    validateCancelRefund() {
      for (const tier of this.cancelRefundForm.tiers) {
        if (Number(tier.refund_percent) < Number(tier.system_refund_percent)) {
          return `${tier.label}: mức hoàn của sân không được thấp hơn ${tier.system_refund_percent}% theo chính sách hệ thống.`;
        }
        if (tier.system_allow_cancel && !tier.allow_cancel) {
          return `${tier.label}: sân không được chặn hủy khi hệ thống đang cho phép hủy.`;
        }
        if (!tier.system_allow_cancel && tier.allow_cancel) {
          return `${tier.label}: sân không được cho hủy khi hệ thống không cho phép hủy.`;
        }
        if (!tier.allow_cancel && Number(tier.refund_percent) !== 0) {
          return `${tier.label}: nếu không cho hủy thì tỷ lệ hoàn phải bằng 0%.`;
        }
        if (tier.system_require_owner_confirm && !tier.require_owner_confirm) {
          return `${tier.label}: sân không được bỏ bước chủ sân xác nhận hoàn tiền.`;
        }
        if (tier.system_require_admin_confirm && !tier.require_admin_confirm) {
          return `${tier.label}: sân không được bỏ bước admin xác nhận hoàn tất.`;
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
          tiers: this.cancelRefundForm.tiers.map((tier) => ({
            key: tier.key,
            label: tier.label,
            from_hours: tier.from_hours,
            to_hours: tier.to_hours,
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
      if (tier.to_hours === null || tier.to_hours === undefined) return `Từ ${tier.from_hours} giờ trở lên`;
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

/* Single unified main surface */
.profile-section-card.policies-main-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 10px;
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border, #e2e8f0);
  border-radius: 0;
  box-shadow: none;
}

.policies-header-hero {
  background: transparent;
  padding: 0;
  display: flex;
  align-items: center;
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
  overflow-x: auto;
  border: none;
  border-radius: 10px;
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
  max-height: calc(100vh - 40px);
  overflow-y: auto;
  padding: 20px;
  background: #ffffff;
  border: 1px solid var(--admin-border, #e2e8f0);
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
}

.modal.wide {
  width: min(1180px, calc(100vw - 32px));
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

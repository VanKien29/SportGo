<template>
  <section class="page">
    <header class="page-head">
      <div>
        <h2>Cấu hình chính sách sân</h2>
        <p>Thiết lập chính sách riêng theo từng cụm sân trong khung hệ thống cho phép.</p>
      </div>
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
    </header>

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

        <div class="tier-preview" v-if="policy.cancel_refund_configuration?.system_tiers?.length">
          <div class="tier-preview-head">
            <strong>Bảng mốc hủy & hoàn</strong>
            <small>{{ policy.cancel_refund_configuration.effective_source_label }}</small>
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
        <button class="btn primary" type="button" @click="openNotice()">
          <AppIcon name="plus" size="16" />
          <span>Thêm quy định</span>
        </button>
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
        <div class="table-wrap">
          <table class="tiers-table">
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
                  <small>{{ rangeText(tier) }}</small>
                </td>
                <td>
                  <span>Hoàn tối thiểu {{ tier.system_refund_percent }}%</span>
                  <small>{{ tier.system_allow_cancel ? 'Hệ thống cho hủy' : 'Hệ thống không cho hủy' }}</small>
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
    };
  },
  computed: {
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
  mounted() {
    window.addEventListener('owner-cluster-changed', this.handleExternalClusterChange);
    this.loadClusters();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleExternalClusterChange);
  },
  methods: {
    emptyNotice() {
      return { id: null, title: '', content: '', status: 'active' };
    },
    async loadClusters() {
      try {
        const response = await venueClusterService.getClusters();
        this.clusters = response.data || [];
        if (!this.selectedClusterId && this.clusters[0]) {
          this.selectedClusterId = this.clusters[0].id;
          localStorage.setItem('selected_cluster', this.selectedClusterId);
        }
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không tải được danh sách cụm sân.';
      }
    },
    async load() {
      if (!this.selectedClusterId) return;
      this.loading = true;
      this.error = '';
      try {
        localStorage.setItem('selected_cluster', this.selectedClusterId);
        const response = await ownerPolicyService.list(this.selectedClusterId);
        const data = response.data || {};
        this.currentCluster = data.venue_cluster || this.clusters.find((cluster) => String(cluster.id) === String(this.selectedClusterId)) || null;
        this.systemPolicies = data.system_policies || [];
        this.venueRules = data.venue_rules || [];
        this.customerNotices = data.customer_notices || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải chính sách sân.';
      } finally {
        this.loading = false;
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
  },
};
</script>

<style scoped>
.page { display: grid; gap: 16px; }
.page-head, .section-head, .card-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
.page-head h2, .section-head h3, .policy-card h3, .modal h3 { margin: 0 0 6px; }
.page-head p, .section-head p, .summary-block p, .notice-card p, .modal-head p, small { margin: 0; color: #64748b; }
.cluster-picker, .cluster-badge { display: grid; gap: 6px; min-width: 260px; font-weight: 800; }
.cluster-badge { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; }
.tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.tabs button { border: 1px solid #dbe3ef; background: #fff; border-radius: 8px; padding: 10px 14px; font-weight: 800; cursor: pointer; }
.tabs .active { background: #dcfce7; border-color: #22c55e; color: #166534; }
.policy-section { display: grid; gap: 14px; }
.inheritance-flow { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.inheritance-flow article { display: grid; grid-template-columns: auto 1fr; gap: 5px 10px; align-items: start; background: #fff; border: 1px solid #dbeafe; border-radius: 12px; padding: 14px; }
.inheritance-flow span { grid-row: span 2; display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 999px; background: #dcfce7; color: #166534; font-weight: 900; }
.inheritance-flow strong { color: #0f172a; }
.inheritance-flow p { margin: 0; color: #64748b; line-height: 1.45; }
.policy-summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.policy-card, .panel, .modal, .notice-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
.policy-card, .panel, .modal { padding: 18px; }
.policy-card { display: grid; gap: 12px; }
.refund-card { grid-template-columns: 1fr; }
.type { color: #64748b; font-size: 13px; font-weight: 800; }
.summary-block { display: grid; gap: 4px; padding: 12px; background: #f8fafc; border-radius: 10px; }
.summary-block.venue { background: #f0fdf4; border: 1px solid #bbf7d0; }
.summary-block span { color: #475569; font-weight: 900; font-size: 13px; }
.tier-preview { display: grid; gap: 10px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; }
.tier-preview-head { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
.tier-preview-head strong { color: #0f172a; }
.tier-preview-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
.tier-preview-card { display: grid; gap: 4px; padding: 10px; border-radius: 10px; background: #f8fafc; }
.tier-preview-card span { color: #0f172a; font-size: 13px; font-weight: 900; }
.tier-preview-card strong { color: #166534; font-size: 13px; }
.tier-preview-card p { margin: 0; color: #64748b; font-size: 13px; }
.policy-card footer, .modal footer { display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
.notice-card { display: grid; grid-template-columns: 1fr auto auto; align-items: center; gap: 12px; padding: 12px; margin-top: 10px; }
.btn { border: 0; border-radius: 8px; font-weight: 800; cursor: pointer; padding: 10px 14px; display: inline-flex; align-items: center; gap: 8px; }
.primary { background: #16a34a; color: #fff; }
.secondary { background: #f1f5f9; color: #0f172a; }
.state { padding: 18px; color: #64748b; background: #f8fafc; border-radius: 10px; }
.preview { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px; border-radius: 10px; margin: 0; }
.form-error { background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px; border-radius: 10px; margin: 0; font-weight: 800; }
.badge { border-radius: 999px; padding: 5px 9px; font-size: 12px; font-weight: 800; background: #f1f5f9; color: #475569; white-space: nowrap; }
.badge.active { background: #dcfce7; color: #166534; }
.badge.neutral { background: #e2e8f0; color: #334155; }
.badge.draft { background: #fef3c7; color: #92400e; }
.badge.inactive { background: #fee2e2; color: #b91c1c; }
.alert { padding: 12px; border-radius: 10px; font-weight: 700; }
.error { background: #fee2e2; color: #b91c1c; }
.success { background: #dcfce7; color: #166534; }
.warning { background: #fef3c7; color: #92400e; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .56); display: grid; place-items: center; z-index: 500; padding: 20px; }
.modal { width: min(680px, calc(100vw - 32px)); display: grid; gap: 14px; max-height: calc(100vh - 40px); overflow: auto; }
.modal.wide { width: min(1180px, calc(100vw - 32px)); }
.modal-guide { display: grid; gap: 4px; border: 1px solid #bbf7d0; border-radius: 10px; background: #f0fdf4; color: #166534; padding: 12px; }
.modal-guide p { margin: 0; color: #166534; }
.status-field { max-width: 360px; }
.table-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 10px; }
.tiers-table { width: 100%; min-width: 1040px; border-collapse: collapse; }
.tiers-table th, .tiers-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.tiers-table th { background: #f8fafc; font-size: 12px; color: #475569; text-transform: uppercase; }
.tiers-table td { background: #fff; }
.tiers-table strong, .tiers-table small, .tiers-table span { display: block; }
.confirm-cell { min-width: 150px; }
.check { display: flex; gap: 8px; align-items: center; font-weight: 700; }
label { display: grid; gap: 6px; font-weight: 800; }
input, select, textarea { border: 1px solid #dbe3ef; border-radius: 8px; padding: 10px; font: inherit; width: 100%; }
textarea { resize: vertical; }
@media (max-width: 900px) {
  .inheritance-flow, .policy-summary-grid, .tier-preview-grid { grid-template-columns: 1fr; }
  .page-head, .section-head, .notice-card { grid-template-columns: 1fr; flex-direction: column; }
  .cluster-picker, .cluster-badge { width: 100%; min-width: 0; }
}
</style>

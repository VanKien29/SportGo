<template>
  <section class="owner-policy-page">
    <header class="hero-card">
      <div>
        <p class="eyebrow">Chính sách sân</p>
        <h2>Cấu hình chính sách sân</h2>
        <p>Chủ sân chỉ cấu hình các giá trị hệ thống cho phép. Nội quy hiển thị cho khách không tác động tự động đến booking hoặc hoàn tiền.</p>
      </div>
      <div class="cluster-chip" :class="clusterTone">
        <AppIcon name="building" size="18" />
        <div v-if="ownerClusters.length <= 1">
          <span>Cụm sân đang chọn</span>
          <strong>{{ venueCluster?.name || 'Chưa chọn cụm sân' }}</strong>
        </div>
        <label v-else class="cluster-select">
          <span>Cụm sân đang chọn</span>
          <select v-model="selectedClusterId" :disabled="loading" @change="changeCluster">
            <option v-for="cluster in ownerClusters" :key="cluster.id" :value="cluster.id">
              {{ cluster.name }}
            </option>
          </select>
        </label>
      </div>
    </header>

    <section class="stat-grid">
      <article class="stat-card">
        <strong>{{ configurablePolicies.length }}</strong>
        <span>Chính sách có thể cấu hình</span>
      </article>
      <article class="stat-card success">
        <strong>{{ customPolicyCount }}</strong>
        <span>Đang dùng cấu hình riêng</span>
      </article>
      <article class="stat-card warning">
        <strong>{{ defaultPolicyCount }}</strong>
        <span>Đang dùng mặc định</span>
      </article>
      <article class="stat-card dark">
        <strong>{{ activeNoticeCount }}</strong>
        <span>Nội quy đang hiển thị</span>
      </article>
    </section>

    <div v-if="venueCluster && venueCluster.status !== 'active'" class="alert warning">
      <AppIcon name="alert" size="18" />
      <span>{{ venueCluster.status_reason || 'Cụm sân không ở trạng thái hoạt động. Một số thao tác có thể bị hạn chế.' }}</span>
    </div>
    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <nav class="tabs">
      <button type="button" :class="{ active: tab === 'rules' }" @click="tab = 'rules'">
        <AppIcon name="sliders" size="16" />
        Quy tắc áp dụng hệ thống
      </button>
      <button type="button" :class="{ active: tab === 'notices' }" @click="tab = 'notices'">
        <AppIcon name="fileText" size="16" />
        Quy định hiển thị cho khách
      </button>
    </nav>

    <section v-if="tab === 'rules'" class="policy-list">
      <div class="section-head">
        <div>
          <h3>Chính sách có thể cấu hình riêng</h3>
          <p>Mỗi cấu hình riêng phải bằng hoặc tốt hơn khung hệ thống đối với khách.</p>
        </div>
        <button class="btn secondary" type="button" :disabled="loading" @click="load">
          <AppIcon name="refresh" size="16" />
          Tải lại
        </button>
      </div>

      <div v-if="loading" class="state-card">
        <span class="spinner"></span>
        Đang tải chính sách sân...
      </div>

      <div v-else-if="configurablePolicies.length === 0" class="empty-state">
        Chưa có chính sách hệ thống nào cho phép sân cấu hình riêng.
      </div>

      <article v-for="policy in configurablePolicies" v-else :key="policy.id" class="policy-card">
        <header class="policy-head">
          <div>
            <span class="policy-type">{{ policy.policy_type_label }}</span>
            <h3>{{ policy.title }}</h3>
            <p>{{ policy.business_summary }}</p>
          </div>
          <span class="badge" :class="policyStatusTone(policy)">{{ policyStatusLabel(policy) }}</span>
        </header>

        <div class="summary-grid">
          <article>
            <small>Khung hệ thống</small>
            <strong>{{ policyConfig(policy).system_summary }}</strong>
          </article>
          <article>
            <small>Sân đang áp dụng</small>
            <strong>{{ policyConfig(policy).venue_summary }}</strong>
          </article>
        </div>

        <div class="tier-table">
          <table>
            <thead>
              <tr>
                <th>Mốc thời gian</th>
                <th>Hệ thống</th>
                <th>Sân đang đặt</th>
                <th>Giới hạn được phép</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in tierRows(policy)" :key="row.key">
                <td><strong>{{ row.label }}</strong><span>{{ row.condition }}</span></td>
                <td>{{ row.systemResult }}</td>
                <td>{{ row.venueResult }}</td>
                <td>{{ row.limit }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer class="card-actions">
          <button class="btn primary" type="button" @click="openConfigModal(policy)">
            <AppIcon name="settings" size="15" />
            {{ policyConfig(policy).venue_rule_id ? 'Sửa cấu hình' : 'Cấu hình riêng' }}
          </button>
          <button
            v-if="policyConfig(policy).venue_rule_id"
            class="btn secondary"
            type="button"
            :disabled="saving"
            @click="confirmReset = { show: true, policy }"
          >
            <AppIcon name="refresh" size="15" />
            Dùng lại mặc định hệ thống
          </button>
        </footer>
      </article>
    </section>

    <section v-else class="notice-section">
      <div class="section-head">
        <div>
          <h3>Quy định hiển thị cho khách</h3>
          <p>Nội quy dạng văn bản, chỉ để khách đọc.</p>
        </div>
        <button class="btn primary" type="button" @click="openNotice()">
          <AppIcon name="plus" size="16" />
          Thêm quy định
        </button>
      </div>

      <div v-if="customerNotices.length === 0" class="empty-state">
        Chưa có nội quy hiển thị cho khách.
      </div>

      <div v-else class="notice-grid">
        <article v-for="notice in customerNotices" :key="notice.id" class="notice-card">
          <header>
            <strong>{{ notice.title }}</strong>
            <span class="badge" :class="noticeTone(notice.status)">{{ notice.status_label }}</span>
          </header>
          <p>{{ notice.content }}</p>
          <footer>
            <span>{{ formatDateTime(notice.updated_at) }}</span>
            <button class="icon-btn" type="button" title="Sửa quy định" @click="openNotice(notice)">
              <AppIcon name="pencil" size="16" />
            </button>
          </footer>
        </article>
      </div>
    </section>

    <div v-if="configModal" class="modal-backdrop" @click.self="closeConfigModal">
      <form class="modal wide" @submit.prevent="saveConfigPolicy">
        <header class="modal-head">
          <div>
            <p class="eyebrow">Cấu hình riêng cho sân</p>
            <h3>{{ selectedPolicy?.title }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeConfigModal">
            <AppIcon name="x" size="18" />
          </button>
        </header>

        <div class="tier-edit-list">
          <article v-for="row in tierDraftRows" :key="row.key" class="tier-edit-row" :class="configType">
            <div>
              <strong>{{ row.label }}</strong>
              <span>{{ row.condition }}</span>
              <small>Hệ thống: {{ row.systemResult }}</small>
            </div>
            <label v-if="configType === 'cancel' || configType === 'cancel_refund'">
              Xử lý của sân
              <select v-model="row.venue.allow_cancel">
                <option :value="true">Cho hủy</option>
                <option :value="false">Không cho hủy</option>
              </select>
            </label>
            <label v-if="configType === 'refund' || configType === 'cancel_refund'">
              Mức hoàn của sân
              <div class="input-unit">
                <input v-model.number="row.venue.refund_percent" type="number" min="0" max="100" />
                <span>%</span>
              </div>
            </label>
          </article>
        </div>

        <div class="live-preview" :class="{ invalid: tierValidationMessage }">
          <AppIcon :name="tierValidationMessage ? 'alert' : 'circleCheck'" size="18" />
          <span>{{ tierValidationMessage || tierPreview }}</span>
        </div>

        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeConfigModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving || !!tierValidationMessage">
            {{ saving ? 'Đang lưu...' : 'Lưu chính sách sân' }}
          </button>
        </footer>
      </form>
    </div>

    <div v-if="noticeModal" class="modal-backdrop" @click.self="closeNotice">
      <form class="modal" @submit.prevent="saveNotice">
        <header class="modal-head">
          <div>
            <p class="eyebrow">Quy định khách đọc</p>
            <h3>{{ noticeForm.id ? 'Sửa quy định' : 'Thêm quy định' }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeNotice">
            <AppIcon name="x" size="18" />
          </button>
        </header>
        <label>
          Tiêu đề
          <input v-model.trim="noticeForm.title" required placeholder="Ví dụ: Quy định gửi xe" />
        </label>
        <label>
          Nội dung
          <textarea v-model.trim="noticeForm.content" rows="6" required placeholder="Viết ngắn gọn để khách dễ đọc." />
        </label>
        <label>
          Trạng thái
          <select v-model="noticeForm.status">
            <option value="draft">Bản nháp</option>
            <option value="active">Hiển thị</option>
            <option value="inactive">Ẩn</option>
          </select>
        </label>
        <div class="notice-note">
          <AppIcon name="shield" size="17" />
          <span>Quy định này chỉ hiển thị cho khách, không ảnh hưởng tự động đến hủy/hoàn/booking.</span>
        </div>
        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeNotice">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">
            Lưu quy định
          </button>
        </footer>
      </form>
    </div>

    <ConfirmModal
      v-model="confirmReset.show"
      title="Dùng lại mặc định hệ thống"
      :message="`Dùng lại mặc định hệ thống cho ${confirmReset.policy?.title || 'chính sách này'}?`"
      consequence="Cấu hình riêng của sân sẽ ngưng áp dụng."
      confirm-text="Dùng mặc định"
      type="warning"
      @confirm="resetPolicy"
    />
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ConfirmModal from '../../components/ConfirmModal.vue';
import { ownerPolicyService } from '../../services/ownerPolicyService.js';

export default {
  name: 'OwnerPolicies',
  components: { AppIcon, ConfirmModal },
  data() {
    return {
      tab: 'rules',
      loading: false,
      saving: false,
      error: '',
      success: '',
      venueCluster: null,
      ownerClusters: [],
      selectedClusterId: localStorage.getItem('selected_cluster') || '',
      systemPolicies: [],
      customerNotices: [],
      configModal: false,
      selectedPolicy: null,
      configType: '',
      tierDraftRows: [],
      noticeModal: false,
      noticeForm: this.emptyNotice(),
      confirmReset: { show: false, policy: null },
    };
  },
  computed: {
    clusterTone() {
      return this.venueCluster?.status === 'active' ? 'active' : 'locked';
    },
    configurablePolicies() {
      return this.systemPolicies.filter((policy) => policy.cancel_refund_configuration || policy.cancellation_configuration || policy.refund_configuration);
    },
    customPolicyCount() {
      return this.configurablePolicies.filter((policy) => this.policyConfig(policy).venue_rule_id).length;
    },
    defaultPolicyCount() {
      return this.configurablePolicies.filter((policy) => !this.policyConfig(policy).venue_rule_id).length;
    },
    activeNoticeCount() {
      return this.customerNotices.filter((notice) => notice.status === 'active').length;
    },
    tierValidationMessage() {
      for (const row of this.tierDraftRows) {
        if (this.configType === 'cancel' || this.configType === 'cancel_refund') {
          if (row.system.allow_cancel && !row.venue.allow_cancel) {
            return `Mốc ${row.label} không được chặn hủy khi chính sách hệ thống đang cho phép hủy.`;
          }
          if (!row.system.allow_cancel && row.venue.allow_cancel) {
            return `Mốc ${row.label} không được cho hủy khi chính sách hệ thống không cho hủy.`;
          }
        }
        if (this.configType === 'refund' || this.configType === 'cancel_refund') {
          const systemPercent = Number(row.system.refund_percent || 0);
          const venuePercent = Number(row.venue.refund_percent || 0);
          if (venuePercent < 0 || venuePercent > 100) {
            return 'Phần trăm hoàn tiền phải nằm trong khoảng 0 đến 100.';
          }
          if (venuePercent < systemPercent) {
            return `Mức hoàn không được thấp hơn ${systemPercent}% theo chính sách hệ thống ở mốc ${row.label}.`;
          }
        }
      }
      return '';
    },
    tierPreview() {
      return this.tierDraftRows.map((row) => `${row.label}: ${this.combinedResult(row.venue).toLowerCase()}`).join('. ') + '.';
    },
  },
  mounted() {
    window.addEventListener('owner-cluster-changed', this.load);
    this.bootstrap();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.load);
  },
  methods: {
    emptyNotice() {
      return { id: null, title: '', content: '', status: 'active' };
    },
    async bootstrap() {
      await this.loadClusters();
      await this.load();
    },
    async loadClusters() {
      try {
        const response = await ownerPolicyService.clusters();
        const clusters = response.data || [];
        this.ownerClusters = Array.isArray(clusters) ? clusters : [];
        const current = this.selectedClusterId || localStorage.getItem('selected_cluster');
        const exists = this.ownerClusters.some((cluster) => cluster.id === current);
        if ((!current || !exists) && this.ownerClusters.length) {
          this.selectedClusterId = this.ownerClusters[0].id;
          localStorage.setItem('selected_cluster', this.selectedClusterId);
        }
      } catch (error) {
        this.error = error.message || 'Không thể tải danh sách cụm sân.';
      }
    },
    async changeCluster() {
      if (!this.selectedClusterId) return;
      localStorage.setItem('selected_cluster', this.selectedClusterId);
      this.success = '';
      await this.load();
      window.dispatchEvent(new CustomEvent('owner-cluster-changed', { detail: { id: this.selectedClusterId } }));
    },
    async load() {
      this.loading = true;
      this.error = '';
      try {
        const response = await ownerPolicyService.list();
        const data = response.data || {};
        this.venueCluster = data.venue_cluster || null;
        this.selectedClusterId = this.venueCluster?.id || this.selectedClusterId;
        this.systemPolicies = data.system_policies || [];
        this.customerNotices = data.customer_notices || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải chính sách sân.';
      } finally {
        this.loading = false;
      }
    },
    policyConfig(policy) {
      return policy.cancel_refund_configuration || policy.cancellation_configuration || policy.refund_configuration || {};
    },
    policyConfigType(policy) {
      if (policy.cancel_refund_configuration) return 'cancel_refund';
      return policy.cancellation_configuration ? 'cancel' : 'refund';
    },
    tierRows(policy) {
      const config = this.policyConfig(policy);
      const type = this.policyConfigType(policy);
      const systemByKey = Object.fromEntries((config.system_tiers || []).map((tier) => [tier.key, tier]));
      const venueByKey = Object.fromEntries(((config.venue_tiers || config.system_tiers) || []).map((tier) => [tier.key, tier]));
      const limitsByKey = Object.fromEntries((config.limits || []).map((limit) => [limit.key, limit.summary]));
      return (config.system_tiers || []).map((system) => {
        const venue = venueByKey[system.key] || system;
        return {
          key: system.key,
          label: system.label,
          condition: system.condition_label,
          from_hours: system.from_hours,
          to_hours: system.to_hours,
          system: systemByKey[system.key],
          venue,
          systemResult: type === 'cancel_refund' ? this.combinedResult(system) : (type === 'cancel' ? this.cancelResult(system) : this.refundResult(system)),
          venueResult: type === 'cancel_refund' ? this.combinedResult(venue) : (type === 'cancel' ? this.cancelResult(venue) : this.refundResult(venue)),
          limit: limitsByKey[system.key] || (type === 'cancel_refund' ? `Không thấp hơn ${system.refund_percent || 0}% và không chặn hủy nếu hệ thống cho hủy.` : (type === 'cancel' ? 'Không được bất lợi hơn hệ thống.' : `Không thấp hơn ${system.refund_percent}%.`)),
        };
      });
    },
    openConfigModal(policy) {
      this.selectedPolicy = policy;
      this.configType = this.policyConfigType(policy);
      this.tierDraftRows = this.tierRows(policy).map((row) => ({
        ...row,
        venue: { ...row.venue },
      }));
      this.configModal = true;
    },
    closeConfigModal() {
      this.configModal = false;
      this.selectedPolicy = null;
      this.configType = '';
      this.tierDraftRows = [];
    },
    async saveConfigPolicy() {
      if (!this.selectedPolicy || this.tierValidationMessage) return;
      this.saving = true;
      this.error = '';
      try {
        const tiers = this.tierDraftRows.map((row) => ({
          key: row.key,
          label: row.label,
          from_hours: row.from_hours,
          to_hours: row.to_hours,
          allow_cancel: Boolean(row.venue.allow_cancel),
          refund_percent: (this.configType === 'refund' || this.configType === 'cancel_refund') ? Number(row.venue.refund_percent || 0) : undefined,
          require_owner_confirm: Boolean(row.venue.require_owner_confirm ?? row.system.require_owner_confirm ?? true),
          require_admin_confirm: Boolean(row.venue.require_admin_confirm ?? row.system.require_admin_confirm ?? true),
          customer_message: row.venue.customer_message || row.system.customer_message || '',
        }));
        const response = await ownerPolicyService.saveRule({
          base_policy_rule_id: this.policyConfig(this.selectedPolicy).base_rule_id,
          tiers,
          status: 'active',
        });
        this.success = response.message || 'Đã lưu chính sách sân.';
        this.closeConfigModal();
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu chính sách sân.';
      } finally {
        this.saving = false;
      }
    },
    async resetPolicy() {
      const policy = this.confirmReset.policy;
      if (!policy) return;
      this.saving = true;
      this.error = '';
      try {
        const response = await ownerPolicyService.resetRule(this.policyConfig(policy).venue_rule_id);
        this.success = response.message || 'Đã dùng lại mặc định hệ thống.';
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể reset chính sách sân.';
      } finally {
        this.saving = false;
        this.confirmReset = { show: false, policy: null };
      }
    },
    openNotice(notice = null) {
      this.noticeForm = notice ? { id: notice.id, title: notice.title, content: notice.content, status: notice.status } : this.emptyNotice();
      this.noticeModal = true;
    },
    closeNotice() {
      this.noticeModal = false;
    },
    async saveNotice() {
      this.saving = true;
      this.error = '';
      try {
        const response = this.noticeForm.id
          ? await ownerPolicyService.updateNotice(this.noticeForm.id, this.noticeForm)
          : await ownerPolicyService.createNotice(this.noticeForm);
        this.success = response.message || 'Đã lưu quy định.';
        this.closeNotice();
        await this.load();
      } catch (error) {
        this.error = error.message || 'Không thể lưu quy định.';
      } finally {
        this.saving = false;
      }
    },
    policyStatusLabel(policy) {
      return this.policyConfig(policy).status_label || 'Đang dùng mặc định hệ thống';
    },
    policyStatusTone(policy) {
      return this.policyConfig(policy).venue_rule_id ? 'success' : 'warning';
    },
    cancelResult(tier) {
      return tier?.allow_cancel ? 'Cho hủy' : 'Không cho hủy';
    },
    refundResult(tier) {
      const percent = Number(tier?.refund_percent || 0);
      return percent > 0 ? `Hoàn ${percent}%` : 'Không hoàn';
    },
    combinedResult(tier) {
      if (!tier?.allow_cancel) return 'Không cho hủy';
      const percent = Number(tier?.refund_percent || 0);
      return percent > 0 ? `Cho hủy, hoàn ${percent}%` : 'Cho hủy nhưng không hoàn';
    },
    noticeTone(status) {
      return { active: 'success', inactive: 'danger', draft: 'neutral' }[status] || 'neutral';
    },
    formatDateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
  },
};
</script>

<style scoped>
.owner-policy-page { display: flex; flex-direction: column; gap: 18px; }
.hero-card { display: flex; justify-content: space-between; gap: 18px; align-items: center; padding: 20px; border-radius: 8px; background: #0f172a; color: #fff; }
.hero-card h2, .section-head h3, .policy-head h3, p { margin: 0; }
.hero-card p:not(.eyebrow) { color: #cbd5e1; max-width: 680px; line-height: 1.5; }
.eyebrow { margin: 0 0 6px; color: #86efac; font-size: 12px; font-weight: 900; text-transform: uppercase; }
.cluster-chip { display: flex; gap: 12px; align-items: center; min-width: 230px; padding: 12px; border-radius: 8px; background: rgba(255,255,255,.08); border-left: 4px solid #22c55e; }
.cluster-chip.locked { border-left-color: #f59e0b; }
.cluster-chip span { display: block; color: #94a3b8; font-size: 12px; }
.cluster-chip strong { display: block; margin-top: 3px; }
.cluster-select { display: grid; gap: 4px; min-width: 220px; }
.cluster-select select { width: 100%; border: 1px solid rgba(255,255,255,.18); border-radius: 8px; padding: 8px 10px; background: #fff; color: #0f172a; font: inherit; font-weight: 800; }
.stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.stat-card { border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: 16px; display: grid; gap: 6px; }
.stat-card strong { font-size: 28px; color: #0f172a; }
.stat-card span { color: #64748b; font-weight: 800; }
.stat-card.success { background: #f0fdf4; border-color: #bbf7d0; }
.stat-card.warning { background: #fffbeb; border-color: #fde68a; }
.stat-card.dark { background: #111827; border-color: #111827; }
.stat-card.dark strong, .stat-card.dark span { color: #fff; }
.tabs, .section-head, .card-actions, .modal-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.tabs { width: fit-content; padding: 6px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; }
.tabs button { border: 0; background: transparent; border-radius: 6px; padding: 10px 14px; display: inline-flex; gap: 8px; align-items: center; color: #475569; font-weight: 900; cursor: pointer; }
.tabs button.active { background: #16a34a; color: #fff; }
.section-head { justify-content: space-between; align-items: flex-start; }
.section-head p, .policy-head p, .notice-card p, small { color: #64748b; }
.policy-list, .notice-section { display: grid; gap: 14px; }
.policy-card, .notice-card, .state-card { border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; padding: 16px; display: grid; gap: 14px; }
.policy-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.policy-type { display: inline-flex; margin-bottom: 6px; color: #15803d; font-size: 12px; font-weight: 900; text-transform: uppercase; }
.summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.summary-grid article { border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; display: grid; gap: 6px; }
.summary-grid small { color: #64748b; font-weight: 900; text-transform: uppercase; }
.summary-grid strong { color: #0f172a; line-height: 1.45; }
.tier-table { overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
table { width: 100%; min-width: 860px; border-collapse: collapse; }
th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
th { background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase; }
td span { display: block; margin-top: 4px; color: #64748b; font-size: 13px; }
.notice-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
.notice-card header, .notice-card footer { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
.notice-note, .alert, .live-preview { display: flex; align-items: flex-start; gap: 10px; border-radius: 8px; padding: 12px; font-weight: 800; }
.notice-note, .live-preview { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.live-preview.invalid, .alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.alert.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.alert.warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.badge { display: inline-flex; width: fit-content; border-radius: 999px; padding: 5px 9px; font-size: 12px; font-weight: 900; white-space: nowrap; }
.badge.success { background: #dcfce7; color: #166534; }
.badge.warning { background: #fef3c7; color: #92400e; }
.badge.danger { background: #fee2e2; color: #991b1b; }
.badge.neutral { background: #f1f5f9; color: #475569; }
.btn, .icon-btn { border: 0; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 900; cursor: pointer; }
.btn { padding: 10px 14px; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #f1f5f9; color: #334155; }
.btn:disabled { opacity: .55; cursor: not-allowed; }
.icon-btn { width: 36px; height: 36px; border: 1px solid #dbe3ef; background: #fff; color: #334155; }
.empty-state { padding: 26px; border: 1px dashed #cbd5e1; border-radius: 8px; color: #64748b; text-align: center; font-weight: 900; background: #f8fafc; }
.spinner { width: 18px; height: 18px; border: 2px solid #bbf7d0; border-top-color: #16a34a; border-radius: 50%; display: inline-block; margin-right: 8px; animation: spin .8s linear infinite; }
.modal-backdrop { position: fixed; inset: 0; z-index: 800; display: flex; justify-content: center; align-items: center; overflow-y: auto; padding: 20px; background: rgba(15, 23, 42, .52); }
.modal { margin: auto; width: min(560px, 100%); max-height: 92vh; overflow-y: auto; background: #fff; border-radius: 10px; padding: 18px; display: flex; flex-direction: column; gap: 14px; box-shadow: 0 24px 80px rgba(15, 23, 42, .25); }
.modal.wide { width: min(900px, 100%); }
.modal-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.tier-edit-list { display: grid; gap: 10px; }
.tier-edit-row { display: grid; gap: 10px; align-items: end; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
.tier-edit-row.cancel { grid-template-columns: 1fr 200px; }
.tier-edit-row.refund { grid-template-columns: 1fr 200px; }
.tier-edit-row.cancel_refund { grid-template-columns: 1fr 180px 180px; }
.tier-edit-row span { display: block; margin-top: 4px; color: #64748b; font-size: 13px; }
.modal-actions { justify-content: flex-end; }
label { display: grid; gap: 6px; color: #334155; font-weight: 900; }
input, select, textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; font: inherit; background: #fff; color: #0f172a; }
.input-unit { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: center; }
.input-unit span { color: #64748b; font-weight: 900; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 900px) {
  .hero-card, .policy-head, .section-head { display: grid; }
  .stat-grid, .summary-grid, .tier-edit-row, .tier-edit-row.cancel, .tier-edit-row.refund, .tier-edit-row.cancel_refund { grid-template-columns: 1fr; }
}
</style>

<template>
  <section class="admin-page">
    <header class="page-head">
      <div>
        <p class="eyebrow">Chính sách vận hành</p>
        <h2>Quản lý chính sách</h2>
        <p>Quản lý văn bản chính sách, phiên bản và quy tắc áp dụng.</p>
      </div>
      <button class="btn primary" type="button" @click="openCreateModal">
        <AppIcon name="plus" size="18" />
        <span>Tạo chính sách</span>
      </button>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <div class="stat-grid">
      <article class="stat-card">
        <strong>{{ summary.total || 0 }}</strong>
        <span>Tổng chính sách</span>
      </article>
      <article class="stat-card">
        <strong>{{ summary.active || 0 }}</strong>
        <span>Đang áp dụng</span>
      </article>
      <article class="stat-card">
        <strong>{{ summary.draft || 0 }}</strong>
        <span>Bản nháp</span>
      </article>
      <article class="stat-card">
        <strong>{{ summary.require_reaccept || 0 }}</strong>
        <span>Cần đồng ý lại</span>
      </article>
    </div>

    <section class="filter-panel">
      <div class="filter-head">
        <strong>Bộ lọc</strong>
        <span>Lọc theo loại, trạng thái và yêu cầu chấp nhận lại.</span>
      </div>
      <div class="filter-bar">
        <label class="search-box">
          <AppIcon name="search" size="18" />
          <input
            v-model.trim="filters.keyword"
            placeholder="Tìm theo tên, mã hoặc nội dung"
            @keyup.enter="loadPolicies"
          />
        </label>
        <select v-model="filters.policy_type" @change="loadPolicies">
          <option value="">Tất cả loại chính sách</option>
          <option v-for="type in policyTypes" :key="type.value" :value="type.value">
            {{ type.label }}
          </option>
        </select>
        <select v-model="filters.status" @change="loadPolicies">
          <option value="">Tất cả trạng thái</option>
          <option value="draft">Bản nháp</option>
          <option value="active">Đang áp dụng</option>
          <option value="inactive">Tạm ngưng</option>
          <option value="archived">Đã lưu trữ</option>
        </select>
        <select v-model="filters.require_reaccept" @change="loadPolicies">
          <option value="">Chấp nhận lại: tất cả</option>
          <option value="1">Có yêu cầu</option>
          <option value="0">Không yêu cầu</option>
        </select>
        <ActionIconButton icon="filter" label="Lọc danh sách" variant="primary" @click="loadPolicies" />
        <ActionIconButton icon="refresh" label="Tải lại" variant="secondary" :disabled="loading" @click="resetFilters" />
      </div>
    </section>

    <div class="table-card">
      <div v-if="loading" class="table-state">Đang tải danh sách chính sách...</div>
      <div v-else-if="policies.length === 0" class="table-state">Chưa có chính sách phù hợp.</div>
      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>
                <button class="sort-btn" type="button" @click="sortBy('title')">
                  Chính sách
                  <AppIcon :name="sortIcon('title')" size="14" />
                </button>
              </th>
              <th>Loại</th>
              <th>
                <button class="sort-btn" type="button" @click="sortBy('version')">
                  Phiên bản
                  <AppIcon :name="sortIcon('version')" size="14" />
                </button>
              </th>
              <th>Trạng thái</th>
              <th>Hiệu lực</th>
              <th>Chấp nhận lại</th>
              <th>Quy tắc</th>
              <th class="actions-col">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="policy in sortedPolicies" :key="policy.id">
              <td class="main-cell">
                <strong>{{ policy.title }}</strong>
                <span>{{ policy.key || policy.code || 'Chưa có mã' }}</span>
              </td>
              <td>{{ policyTypeLabel(policy.policy_type || policy.type) }}</td>
              <td>v{{ policy.version || 1 }}</td>
              <td>
                <span class="status-badge" :class="statusClass(policy.status)">
                  <AppIcon :name="statusIcon(policy.status)" size="14" />
                  {{ statusLabel(policy.status) }}
                </span>
              </td>
              <td>{{ formatDate(policy.effective_from || policy.published_at || policy.updated_at) }}</td>
              <td>
                <span :class="policy.require_reaccept ? 'yes-text' : 'muted-text'">
                  {{ policy.require_reaccept ? 'Có' : 'Không' }}
                </span>
              </td>
              <td>
                <span class="rule-count">{{ policy.rules_count || 0 }}</span>
              </td>
              <td class="actions-col">
                <TableActionGroup>
                  <ActionIconButton icon="eye" label="Xem chi tiết" @click="goDetail(policy)" />
                  <ActionIconButton icon="pencil" label="Sửa chính sách" @click="goDetail(policy, 'document')" />
                  <ActionIconButton icon="copy" label="Tạo phiên bản mới" @click="clonePolicy(policy)" />
                  <ActionIconButton
                    v-if="policy.status !== 'active'"
                    icon="rocket"
                    label="Kích hoạt chính sách"
                    variant="success"
                    @click="openPublishConfirm(policy)"
                  />
                  <ActionIconButton
                    v-if="policy.status === 'active'"
                    icon="archive"
                    label="Ngưng áp dụng"
                    variant="danger"
                    @click="openArchiveConfirm(policy)"
                  />
                  <ActionIconButton icon="history" label="Xem lịch sử thay đổi" @click="goDetail(policy, 'audit')" />
                </TableActionGroup>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <form class="modal" @submit.prevent="savePolicy">
        <header class="modal-head">
          <div>
            <h3>Tạo chính sách mới</h3>
            <p>Chính sách mới được tạo ở trạng thái bản nháp. Sau khi rà soát xong mới kích hoạt.</p>
          </div>
          <ActionIconButton icon="x" label="Đóng" variant="ghost" @click="closeModal" />
        </header>

        <div v-if="modalError" class="alert error">{{ modalError }}</div>

        <div class="form-body">
          <div class="form-grid">
            <label>
              Mã chính sách
              <input v-model.trim="form.key" required placeholder="vd: refund_policy" />
            </label>
            <label>
              Phiên bản
              <input v-model.number="form.version" type="number" min="1" required />
            </label>
            <label>
              Loại chính sách
              <select v-model="form.policy_type" required>
                <option v-for="type in policyTypes" :key="type.value" :value="type.value">
                  {{ type.label }}
                </option>
              </select>
            </label>
            <label>
              Thứ tự ưu tiên
              <input v-model.number="form.priority" type="number" min="0" />
            </label>
          </div>

          <label>
            Tiêu đề
            <input v-model.trim="form.title" required />
          </label>
          <label>
            Nội dung chính sách
            <textarea v-model.trim="form.content" rows="7" required></textarea>
          </label>
          <label>
            Tóm tắt thay đổi
            <textarea v-model.trim="form.change_summary" rows="2"></textarea>
          </label>

          <label class="check-row">
            <input v-model="form.require_reaccept" type="checkbox" />
            <span>Bắt buộc người dùng đồng ý lại khi chính sách được kích hoạt</span>
          </label>
          <label class="check-row">
            <input v-model="form.is_overridable" type="checkbox" />
            <span>Cho phép chủ sân cấu hình ghi đè nếu module hỗ trợ</span>
          </label>
        </div>

        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu chính sách' }}
          </button>
        </footer>
      </form>
    </div>

    <ConfirmModal
      v-model="confirmPublish.show"
      title="Kích hoạt chính sách"
      :message="`Bạn sắp kích hoạt chính sách ${confirmPublish.policy?.title || ''}.`"
      consequence="Chính sách sẽ được áp dụng trên hệ thống. Nếu bắt buộc đồng ý lại, người dùng sẽ nhận thông báo."
      confirm-text="Kích hoạt"
      type="warning"
      @confirm="publishPolicy"
    />

    <ConfirmModal
      v-model="confirmArchive.show"
      title="Ngưng áp dụng chính sách"
      :message="`Bạn sắp ngưng áp dụng chính sách ${confirmArchive.policy?.title || ''}.`"
      consequence="Các quy tắc xử lý tự động của chính sách này sẽ không còn hiệu lực."
      confirm-text="Ngưng áp dụng"
      type="danger"
      @confirm="archivePolicy"
    />
  </section>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import ConfirmModal from '../../components/ConfirmModal.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import { adminPolicyService } from '../../services/adminPolicies.js';
import { STATUS_ICON_MAP } from '../../utils/iconRegistry.js';

const POLICY_TYPE_LABELS = {
  general: 'Chung',
  booking: 'Đặt sân',
  refund: 'Hoàn tiền',
  moderation: 'Kiểm duyệt và báo cáo',
  account: 'Tài khoản',
  platform_fee: 'Phí duy trì cụm sân',
  terms: 'Điều khoản sử dụng',
};

const STATUS_LABELS = {
  draft: 'Bản nháp',
  active: 'Đang áp dụng',
  inactive: 'Tạm ngưng',
  archived: 'Đã lưu trữ',
};

export default {
  name: 'AdminPolicies',
  components: { ActionIconButton, AppIcon, ConfirmModal, TableActionGroup },
  data() {
    return {
      policies: [],
      summary: {},
      filters: { keyword: '', policy_type: '', status: '', require_reaccept: '' },
      form: this.defaultForm(),
      loading: false,
      saving: false,
      showModal: false,
      modalError: '',
      error: '',
      success: '',
      sortKey: 'title',
      sortDir: 'asc',
      confirmPublish: { show: false, policy: null },
      confirmArchive: { show: false, policy: null },
      policyTypes: Object.entries(POLICY_TYPE_LABELS).map(([value, label]) => ({ value, label })),
    };
  },
  computed: {
    sortedPolicies() {
      return [...this.policies].sort((a, b) => {
        const left = this.sortValue(a, this.sortKey);
        const right = this.sortValue(b, this.sortKey);
        const result = String(left).localeCompare(String(right), 'vi', { numeric: true, sensitivity: 'base' });
        return this.sortDir === 'asc' ? result : -result;
      });
    },
  },
  mounted() {
    this.loadPolicies();
  },
  methods: {
    defaultForm() {
      return {
        key: '',
        version: 1,
        title: '',
        content: '',
        policy_type: 'general',
        priority: 0,
        is_overridable: false,
        require_reaccept: false,
        change_summary: '',
      };
    },
    async loadPolicies() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminPolicyService.list(this.filters);
        this.policies = response.data || [];
        this.summary = response.summary || {};
      } catch (error) {
        this.error = error.message || 'Không tải được danh sách chính sách.';
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = { keyword: '', policy_type: '', status: '', require_reaccept: '' };
      this.loadPolicies();
    },
    sortBy(key) {
      if (this.sortKey === key) {
        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
        return;
      }
      this.sortKey = key;
      this.sortDir = 'asc';
    },
    sortIcon(key) {
      if (this.sortKey !== key) return 'chevronDown';
      return this.sortDir === 'asc' ? 'chevronUp' : 'chevronDown';
    },
    sortValue(policy, key) {
      if (key === 'version') return Number(policy.version || 0);
      return policy[key] || '';
    },
    goDetail(policy, tab = 'document') {
      this.$router.push({ name: 'admin-policy-detail', params: { id: policy.id }, query: { tab } });
    },
    openCreateModal() {
      this.form = this.defaultForm();
      this.modalError = '';
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
      this.modalError = '';
    },
    async savePolicy() {
      this.saving = true;
      this.modalError = '';
      try {
        const response = await adminPolicyService.create(this.form);
        this.success = this.safeDisplayText(response.message, 'Đã tạo chính sách.');
        this.closeModal();
        await this.loadPolicies();
        this.autoHide();
      } catch (error) {
        this.modalError = error.message || 'Không lưu được chính sách.';
      } finally {
        this.saving = false;
      }
    },
    openPublishConfirm(policy) {
      this.confirmPublish = { show: true, policy };
    },
    openArchiveConfirm(policy) {
      this.confirmArchive = { show: true, policy };
    },
    async publishPolicy() {
      const policy = this.confirmPublish.policy;
      if (!policy) return;
      await this.runAction(() => adminPolicyService.publish(policy.id), 'Đã kích hoạt chính sách.');
    },
    async archivePolicy() {
      const policy = this.confirmArchive.policy;
      if (!policy) return;
      await this.runAction(() => adminPolicyService.updateStatus(policy.id, { status: 'archived' }), 'Đã ngưng áp dụng chính sách.');
    },
    async clonePolicy(policy) {
      await this.runAction(() => adminPolicyService.cloneVersion(policy.id), 'Đã tạo phiên bản mới.');
    },
    async runAction(action, fallbackMessage) {
      this.error = '';
      this.success = '';
      try {
        const response = await action();
        this.success = this.safeDisplayText(response.message, fallbackMessage);
        await this.loadPolicies();
        this.autoHide();
      } catch (error) {
        this.error = this.safeDisplayText(error.message, 'Thao tác không thành công.');
      }
    },
    policyTypeLabel(type) {
      return POLICY_TYPE_LABELS[type] || type || 'Không xác định';
    },
    statusLabel(status) {
      return STATUS_LABELS[status] || status || 'Không xác định';
    },
    statusClass(status) {
      return `status-${status || 'default'}`;
    },
    statusIcon(status) {
      return STATUS_ICON_MAP[status] || 'alert';
    },
    safeDisplayText(value, fallback = '') {
      if (!value) return fallback;
      return /[ĂÄÂÆ]|áº|á»|â€|â€™|â€œ|â€/.test(String(value)) ? fallback : value;
    },
    formatDate(value) {
      if (!value) return '-';
      return new Date(value).toLocaleDateString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      });
    },
    autoHide() {
      setTimeout(() => { this.success = ''; }, 3500);
    },
  },
};
</script>

<style scoped>
.admin-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.page-head {
  display: flex;
  justify-content: flex-end;
  gap: 18px;
  align-items: flex-start;
}

.eyebrow {
  margin: 0 0 4px;
  color: #16a34a;
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
}

h2,
h3,
p {
  margin: 0;
}

.page-head h2 {
  color: #0f172a;
  font-size: 24px;
}

.page-head p:not(.eyebrow) {
  margin-top: 6px;
  color: #64748b;
  line-height: 1.55;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.stat-card,
.filter-panel,
.table-card,
.modal {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
}

.stat-card {
  padding: 16px;
}

.stat-card strong {
  display: block;
  color: #0f172a;
  font-size: 26px;
}

.stat-card span {
  color: #64748b;
  font-size: 13px;
}

.filter-panel {
  padding: 14px;
}

.filter-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.filter-head strong {
  color: #0f172a;
}

.filter-head span {
  color: #64748b;
  font-size: 13px;
}

.filter-bar {
  display: grid;
  grid-template-columns: minmax(260px, 2fr) repeat(3, minmax(145px, 1fr)) 36px 36px;
  gap: 10px;
  align-items: center;
  min-width: 0;
}

.search-box {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
  min-width: 0;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 0 12px;
  color: #64748b;
  font-weight: normal;
}

.search-box input {
  border: 0;
  padding-left: 0;
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

.search-box:focus-within {
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.12);
}

.search-box:focus-within input {
  box-shadow: none;
}

.table-card {
  overflow: hidden;
}

.table-wrap {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1040px;
}

th,
td {
  border-bottom: 1px solid #e2e8f0;
  padding: 13px 14px;
  text-align: left;
  vertical-align: middle;
}

th {
  background: #f8fafc;
  color: #475569;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

tbody tr:hover {
  background: #f8fafc;
}

.main-cell {
  min-width: 280px;
}

.main-cell strong {
  display: block;
  color: #0f172a;
}

.main-cell span,
.muted-text {
  color: #64748b;
  font-size: 13px;
}

.yes-text {
  color: #166534;
  font-weight: 800;
}

.rule-count {
  display: inline-grid;
  min-width: 32px;
  height: 28px;
  place-items: center;
  border-radius: 999px;
  background: #eef2f7;
  color: #334155;
  font-weight: 900;
}

.actions-col {
  text-align: right;
}

.sort-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
  text-transform: inherit;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border-radius: 999px;
  padding: 5px 9px;
  font-size: 12px;
  font-weight: 900;
  white-space: nowrap;
}

.status-active {
  background: #dcfce7;
  color: #166534;
}

.status-draft {
  background: #e0f2fe;
  color: #075985;
}

.status-inactive {
  background: #fef3c7;
  color: #92400e;
}

.status-archived,
.status-default {
  background: #f1f5f9;
  color: #475569;
}

.table-state {
  padding: 36px;
  color: #64748b;
  text-align: center;
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

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 0;
  border-radius: 8px;
  padding: 10px 14px;
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

.btn:disabled {
  cursor: not-allowed;
  opacity: .55;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 900;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(15, 23, 42, 0.55);
}

.modal {
  width: min(720px, calc(100vw - 32px));
  max-height: calc(100vh - 40px);
  overflow: auto;
}

.modal-head,
.modal-actions {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 22px;
}

.modal-head {
  border-bottom: 1px solid #e2e8f0;
}

.modal-head p {
  margin-top: 4px;
  color: #64748b;
}

.form-body {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px 22px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: #334155;
  font-weight: 800;
}

.check-row {
  flex-direction: row;
  align-items: center;
}

.check-row input {
  width: auto;
}

.modal-actions {
  justify-content: flex-end;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}

@media (max-width: 1120px) {
  .filter-bar {
    grid-template-columns: minmax(240px, 1fr) repeat(3, minmax(135px, 1fr)) 36px 36px;
  }
}

@media (max-width: 760px) {
  .page-head {
    flex-direction: column;
  }

  .stat-grid,
  .form-grid {
    grid-template-columns: 1fr;
  }

  .filter-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .filter-bar {
    grid-template-columns: 1fr 1fr;
  }

  .search-box {
    grid-column: 1 / -1;
  }
}

@media (max-width: 520px) {
  .filter-bar {
    grid-template-columns: 1fr;
  }
}
</style>

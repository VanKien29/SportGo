<template>
  <section class="admin-page">
    <PlatformFeeSubnav v-if="isPlatformFeeScope" />

    <!-- Floating Add Button -->
    <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
      <button class="btn-float-add" type="button" @click="openCreateModal" title="Tạo chính sách">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Tạo chính sách</span>
      </button>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="filter-panel">
      <label class="search-box">
        <AppIcon name="search" size="18" />
        <input
          v-model.trim="filters.keyword"
          placeholder="Tìm theo tên chính sách hoặc mã kỹ thuật"
          @keyup.enter="loadPolicies"
        />
      </label>

      <select v-if="!isPlatformFeeScope" v-model="filters.policy_type" @change="loadPolicies">
        <option value="">Tất cả nhóm chính sách</option>
        <option v-for="type in policyTypes" :key="type.value" :value="type.value">
          {{ type.label }}
        </option>
      </select>

      <select v-model="filters.status" @change="loadPolicies">
        <option value="">Tất cả trạng thái</option>
        <option value="draft">Bản nháp</option>
        <option value="active">Đang áp dụng</option>
        <option value="inactive">Ngưng áp dụng</option>
        <option value="archived">Lưu trữ</option>
        <option value="pending_review">Chờ duyệt</option>
        <option value="rejected">Bị từ chối</option>
      </select>

      <select v-model="filters.require_reaccept" @change="loadPolicies">
        <option value="">Chấp nhận lại: tất cả</option>
        <option value="1">Có yêu cầu</option>
        <option value="0">Không yêu cầu</option>
      </select>

      <button class="icon-btn" type="button" title="Lọc danh sách" @click="loadPolicies">
        <AppIcon name="filter" size="17" />
      </button>
      <button class="icon-btn" type="button" title="Tải lại" :disabled="loading" @click="resetFilters">
        <AppIcon name="refresh" size="17" />
      </button>
    </section>

    <section class="table-card">
      <div v-if="loading" class="table-state">Đang tải danh sách chính sách...</div>
      <div v-else-if="policies.length === 0" class="table-state">Chưa có chính sách phù hợp.</div>
      <div v-else class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Tên chính sách</th>
              <th>Nhóm chính sách</th>
              <th>Phiên bản</th>
              <th>Trạng thái</th>
              <th>Hiệu lực từ</th>
              <th>Cho sân cấu hình riêng</th>
              <th>Số quy tắc</th>
              <th>Cập nhật lần cuối</th>
              <th class="actions-col">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="policy in policies" :key="policy.id">
              <td class="main-cell">
                {{ policy.title }}
                <span>Mã kỹ thuật: {{ policy.key || 'chưa có' }}</span>
              </td>
              <td>{{ policyTypeLabel(policy) }}</td>
              <td>v{{ policy.version || 1 }}</td>
              <td>
                <span class="status-badge" :class="statusClass(policy.status)">
                  {{ statusLabel(policy) }}
                </span>
              </td>
              <td>{{ formatDate(policy.effective_from || policy.published_at) }}</td>
              <td>
                <span :class="policy.is_overridable ? 'yes-text' : 'muted-text'">
                  {{ policy.is_overridable ? 'Có' : 'Không' }}
                </span>
              </td>
              <td>
                <span class="rule-count">{{ policy.rules_count || 0 }}</span>
              </td>
              <td>{{ formatDate(policy.updated_at) }}</td>
              <td class="actions-col">
                <div class="action-row">
                  <button class="icon-action" type="button" title="Xem tổng quan" @click="goDetail(policy, 'overview')">
                    <AppIcon name="eye" size="16" />
                  </button>
                  <button class="icon-action" type="button" title="Xem quy tắc" @click="goDetail(policy, 'rules')">
                    <AppIcon name="sliders" size="16" />
                  </button>
                  <button class="icon-action" type="button" title="Tạo phiên bản mới" @click="clonePolicy(policy)">
                    <AppIcon name="copy" size="16" />
                  </button>
                  <button
                    v-if="policy.status !== 'active'"
                    class="icon-action success"
                    type="button"
                    title="Áp dụng chính sách"
                    @click="openPublishConfirm(policy)"
                  >
                    <AppIcon name="check" size="16" />
                  </button>
                  <button
                    v-else
                    class="icon-action danger"
                    type="button"
                    title="Ngưng áp dụng"
                    @click="openArchiveConfirm(policy)"
                  >
                    <AppIcon name="power" size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <form class="modal" @submit.prevent="savePolicy">
        <header class="modal-head">
          <div>
            <h3>Tạo chính sách mới</h3>
            <p>Tạo bản nháp trước, sau khi có nội dung và quy tắc phù hợp mới đưa vào áp dụng.</p>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeModal">
            <AppIcon name="x" size="18" />
          </button>
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
              Nhóm chính sách
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
            Tên chính sách
            <input v-model.trim="form.title" required />
          </label>
          <label>
            Nội dung chính sách
            <QuillEditor theme="snow" v-model:content="form.content" contentType="html" placeholder="Nhập nội dung chính sách..." />
          </label>
          <label>
            Tóm tắt thay đổi
            <textarea v-model.trim="form.change_summary" rows="2"></textarea>
          </label>

          <label class="check-row">
            <input v-model="form.require_reaccept" type="checkbox" />
            <span>Bắt buộc người dùng/chủ sân xác nhận lại khi chính sách được áp dụng</span>
          </label>
          <label class="check-row">
            <input v-model="form.is_overridable" type="checkbox" />
            <span>Cho phép sân cấu hình riêng nếu vẫn đúng khung hệ thống</span>
          </label>
        </div>

        <footer class="modal-actions">
          <button class="btn secondary" type="button" @click="closeModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu bản nháp' }}
          </button>
        </footer>
      </form>
    </div>

    <ConfirmModal
      v-model="confirmPublish.show"
      title="Áp dụng chính sách"
      :message="`Áp dụng chính sách ${confirmPublish.policy?.title || ''}?`"
      consequence="Chính sách và các quy tắc đang bật sẽ được dùng trên hệ thống."
      confirm-text="Áp dụng"
      type="warning"
      @confirm="publishPolicy"
    />

    <ConfirmModal
      v-model="confirmArchive.show"
      title="Ngưng áp dụng chính sách"
      :message="`Ngưng áp dụng chính sách ${confirmArchive.policy?.title || ''}?`"
      consequence="Các quy tắc xử lý tự động của chính sách này sẽ không còn hiệu lực."
      confirm-text="Ngưng áp dụng"
      type="danger"
      @confirm="archivePolicy"
    />
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import ConfirmModal from '../../components/ConfirmModal.vue';
import PlatformFeeSubnav from '../../components/PlatformFeeSubnav.vue';
import { adminPolicyService } from '../../services/adminPolicies.js';
import { getPolicyTypeLabel, getStatusBadgeClass, getStatusLabel, POLICY_TYPE_LABELS } from '../../utils/labelMaps.js';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

export default {
  name: 'AdminPolicies',
  components: { AppIcon, ConfirmModal, PlatformFeeSubnav, QuillEditor },
  data() {
    const platformFeeScope = this.$route.name === 'admin-platform-fee-policies';
    return {
      policies: [],
      summary: {},
      filters: {
        keyword: '',
        policy_type: platformFeeScope ? 'platform_fee' : '',
        status: '',
        require_reaccept: '',
      },
      form: this.defaultForm(platformFeeScope),
      loading: false,
      saving: false,
      showModal: false,
      modalError: '',
      error: '',
      success: '',
      confirmPublish: { show: false, policy: null },
      confirmArchive: { show: false, policy: null },
      policyTypes: Object.entries(POLICY_TYPE_LABELS)
        .filter(([value]) => !['general', 'booking', 'account'].includes(value))
        .map(([value, label]) => ({ value, label })),
      showScrollTop: false,
    };
  },
  computed: {
    isPlatformFeeScope() {
      return this.$route.name === 'admin-platform-fee-policies';
    },
    policiesNeedAttention() {
      return this.policies.filter((policy) => ['draft', 'pending_review', 'rejected'].includes(policy.status)).length;
    },
  },
  mounted() {
    this.loadPolicies();
    window.addEventListener('scroll', this.handleScroll);
  },
  beforeUnmount() {
    window.removeEventListener('scroll', this.handleScroll);
  },
  methods: {
    defaultForm(platformFeeScope = this.isPlatformFeeScope) {
      return {
        key: '',
        version: 1,
        title: '',
        content: '',
        policy_type: platformFeeScope ? 'platform_fee' : 'terms',
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
      this.filters = {
        keyword: '',
        policy_type: this.isPlatformFeeScope ? 'platform_fee' : '',
        status: '',
        require_reaccept: '',
      };
      this.loadPolicies();
    },
    goDetail(policy, tab = 'overview') {
      this.$router.push({
        name: 'admin-policy-detail',
        params: { id: policy.id },
        query: {
          tab,
          ...(this.isPlatformFeeScope ? { source: 'platform_fee' } : {}),
        },
      });
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
        this.success = response.message || 'Đã tạo chính sách.';
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
      await this.runAction(() => adminPolicyService.publish(policy.id), 'Đã áp dụng chính sách.');
    },
    async archivePolicy() {
      const policy = this.confirmArchive.policy;
      if (!policy) return;
      await this.runAction(() => adminPolicyService.updateStatus(policy.id, { status: 'archived' }), 'Đã ngưng áp dụng chính sách.');
    },
    async clonePolicy(policy) {
      this.error = '';
      this.success = '';
      try {
        const response = await adminPolicyService.cloneVersion(policy.id);
        this.success = response.message || 'Đã tạo phiên bản mới.';
        this.$router.push({
          name: 'admin-policy-detail',
          params: { id: response.data.id },
          query: { tab: 'config' },
        });
      } catch (error) {
        this.error = error.message || 'Thao tác không thành công.';
      }
    },
    async runAction(action, fallbackMessage) {
      this.error = '';
      this.success = '';
      try {
        const response = await action();
        this.success = response.message || fallbackMessage;
        await this.loadPolicies();
        this.autoHide();
      } catch (error) {
        this.error = error.message || 'Thao tác không thành công.';
      }
    },
    policyTypeLabel(policy) {
      return policy.policy_type_label_vi || policy.policy_type_label || getPolicyTypeLabel(policy.policy_type || policy.type);
    },
    statusLabel(policy) {
      return policy.status_label_vi || policy.status_label || getStatusLabel(policy.status);
    },
    statusClass(status) {
      return getStatusBadgeClass(status);
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
    handleScroll() {
      this.showScrollTop = window.scrollY > 250;
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

h2,
h3,
p {
  margin: 0;
}

.filter-panel,
.table-card,
.modal {
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  background: var(--admin-surface, #fff);
}

.filter-panel {
  display: grid;
  grid-template-columns: minmax(260px, 2fr) repeat(3, minmax(145px, 1fr)) 40px 40px;
  gap: 10px;
  padding: 14px;
  align-items: center;
}

.search-box {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 10px;
  min-width: 0;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 0 12px;
  color: var(--admin-muted);
}

.search-box input {
  border: 0;
  padding-left: 0;
}

input,
select,
textarea {
  width: 100%;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  padding: 10px 12px;
  color: var(--admin-text);
  font: inherit;
  background: var(--admin-surface, #fff);
}

input:focus,
select:focus,
textarea:focus,
.search-box:focus-within {
  outline: none;
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
  min-width: 1180px;
}

th,
td {
  border-bottom: 1px solid var(--admin-border);
  padding: 13px 14px;
  text-align: left;
  vertical-align: middle;
}

th {
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

tbody tr:hover {
  background: var(--admin-surface-muted);
}

.main-cell {
  min-width: 280px;
}

.main-cell strong {
  display: block;
  color: var(--admin-text);
}

.main-cell span,
.muted-text {
  color: var(--admin-muted);
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
  color: var(--admin-text);
  font-weight: 900;
}

.actions-col {
  text-align: right;
}

.action-row {
  display: flex;
  flex-wrap: nowrap;
  justify-content: flex-end;
  gap: 4px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
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

.status-draft,
.status-pending {
  background: #e0f2fe;
  color: #075985;
}

.status-inactive {
  background: #fef3c7;
  color: #92400e;
}

.status-rejected {
  background: #fee2e2;
  color: #991b1b;
}

.status-archived,
.status-default {
  background: var(--admin-surface-muted);
  color: var(--admin-faint);
}

.table-state {
  padding: 36px;
  color: var(--admin-muted);
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

.btn,
.mini-btn,
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 0;
  border-radius: 8px;
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.btn {
  padding: 10px 14px;
}

.mini-btn {
  padding: 7px 9px;
  background: var(--admin-surface-muted);
  color: var(--admin-text);
}

.icon-btn {
  width: 40px;
  height: 40px;
  background: var(--admin-surface-muted);
  color: var(--admin-text);
}

.icon-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid var(--admin-border);
  background: var(--admin-surface, #fff);
  color: var(--admin-faint);
  cursor: pointer;
  transition: background 0.15s, color 0.15s, border-color 0.15s, transform 0.1s;
  font: inherit;
}

.icon-action:hover {
  background: var(--admin-surface-muted);
  border-color: var(--admin-border);
  color: var(--admin-text);
  transform: translateY(-1px);
}

.icon-action.success {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #15803d;
}

.icon-action.success:hover {
  background: #dcfce7;
  border-color: #86efac;
}

.icon-action.danger {
  background: #fff1f2;
  border-color: #fecdd3;
  color: #be123c;
}

.icon-action.danger:hover {
  background: #fee2e2;
  border-color: #fca5a5;
}

.btn.primary,
.mini-btn.success {
  background: #16a34a;
  color: #fff;
}

.btn.secondary {
  background: var(--admin-border);
  color: var(--admin-text);
}

.mini-btn.danger {
  background: #fee2e2;
  color: #991b1b;
}

.btn:disabled,
.icon-btn:disabled {
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
  border-bottom: 1px solid var(--admin-border);
}

.modal-head p {
  margin-top: 4px;
  color: var(--admin-muted);
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
  color: var(--admin-text);
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
  border-top: 1px solid var(--admin-border);
  background: var(--admin-surface-muted);
}

@media (max-width: 920px) {
  .page-head {
    flex-direction: column;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .filter-panel {
    grid-template-columns: 1fr 1fr;
  }

  .search-box {
    grid-column: 1 / -1;
  }
}

@media (max-width: 560px) {
  .filter-panel {
    grid-template-columns: 1fr;
  }
}
</style>

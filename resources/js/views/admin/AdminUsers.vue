<template>
  <section class="admin-users">

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="table-card">
      <div v-if="loading" class="state">Đang tải danh sách tài khoản...</div>
      <table v-else>
        <thead>
          <tr>
            <th>Họ tên</th>
            <th>Username</th>
            <th>Email/SĐT</th>
            <th>Vai trò chính</th>
            <th>Trạng thái</th>
            <th>Cảnh báo</th>
            <th>Report/khiếu nại</th>
            <th>Số dư ví</th>
            <th>Ngày tạo</th>
            <th class="actions-col">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="users.length === 0">
            <td colspan="10" class="state">Không có tài khoản phù hợp với bộ lọc hiện tại.</td>
          </tr>
          <tr v-for="user in users" :key="user.id">
            <td>
              <strong>{{ user.full_name || '-' }}</strong>
              <small>{{ user.warning_summary?.message }}</small>
              <span v-if="(user.reports_count_recent || 0) >= 3" class="badge-report">
                <AppIcon name="alert" size="12" class="badge-report-icon" /> {{ user.reports_count_recent }} báo cáo
              </span>
              <span v-if="user.status === 'locked'" class="badge-locked">Đang khóa</span>
            </td>
            <td>{{ user.username }}</td>
            <td>{{ user.email || user.phone || '-' }}</td>
            <td>{{ user.primary_role_label || (user.roles && user.roles[0]) || '-' }}</td>
            <td>
              <span class="status" :class="user.status">{{ user.status_label || getAccountStatusLabel(user.status) }}</span>
            </td>
            <td>
              <span class="warning" :class="user.warning_summary?.level || 'normal'">
                {{ user.warning_summary?.label || 'Bình thường' }}
              </span>
            </td>
            <td>{{ user.reports_count_recent || 0 }} / {{ user.complaints_count_recent || 0 }}</td>
            <td>{{ money(user.wallet_balance) }}</td>
            <td>{{ date(user.created_at) }}</td>
            <td class="actions-col">
              <TableActionGroup>
                <RouterLink class="icon-btn" :to="{ name: 'admin-user-detail', params: { id: user.id } }" title="Xem chi tiết" aria-label="Xem chi tiết">
                  <AppIcon name="eye" size="17" />
                </RouterLink>
                <ActionIconButton
                  v-if="user.status === 'locked'"
                  icon="unlock"
                  label="Mở khóa tài khoản"
                  @click="openUnlockModal(user)"
                />
                <ActionIconButton
                  v-else
                  icon="lock"
                  label="Khóa tài khoản"
                  variant="danger"
                  @click="openLockModal(user)"
                />
              </TableActionGroup>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <footer class="pagination" v-if="meta.total > 0">
      <span>Hiển thị {{ users.length }} / {{ meta.total }} tài khoản</span>
      <div>
        <ActionIconButton icon="chevronLeft" label="Trang trước" :disabled="meta.current_page <= 1 || loading" @click="goPage(meta.current_page - 1)" />
        <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
        <ActionIconButton icon="chevronRight" label="Trang sau" :disabled="meta.current_page >= meta.last_page || loading" @click="goPage(meta.current_page + 1)" />
      </div>
    </footer>

    <div v-if="actionTarget" class="modal-backdrop" @click.self="closeActionModal">
      <form class="modal" @submit.prevent="submitAccountAction">
        <h3>{{ actionType === 'lock' ? 'Khóa tài khoản' : 'Mở khóa tài khoản' }}</h3>
        <p class="muted">
          {{ actionTarget.full_name || actionTarget.username }} · {{ actionTarget.primary_role_label }}
        </p>

        <template v-if="actionType === 'lock'">
          <div class="segmented">
            <button
              v-for="type in lockTypes"
              :key="type.value"
              type="button"
              :class="{ active: actionForm.lock_type === type.value }"
              @click="actionForm.lock_type = type.value"
            >
              {{ type.label }}
            </button>
          </div>
          <label v-if="actionForm.lock_type === 'temporary'">
            <span>Khóa đến</span>
            <input v-model="actionForm.locked_until" type="datetime-local" required />
          </label>
        </template>

        <label>
          <span>{{ actionType === 'lock' ? 'Lý do khóa' : 'Lý do mở khóa' }}</span>
          <textarea v-model.trim="actionForm.reason" rows="4" required placeholder="Nhập lý do để lưu audit log"></textarea>
        </label>

        <footer>
          <button type="button" class="btn secondary" @click="closeActionModal">Hủy</button>
          <button type="submit" class="btn" :class="{ danger: actionType === 'lock' }" :disabled="saving">
            {{ actionType === 'lock' ? 'Xác nhận khóa' : 'Xác nhận mở khóa' }}
          </button>
        </footer>
      </form>
    </div>

    <!-- Modal Cấu hình khóa tự động -->
    <div v-if="showPolicyModal" class="modal-backdrop" @click.self="closePolicyModal">
      <div class="modal policy-modal">
        <h3>Cấu hình khóa tự động</h3>
        <p class="muted policy-modal-subtitle">Cấu hình tự động khóa tài khoản khi bị nhiều người báo cáo.</p>
        
        <div v-if="policyLoading" class="state">Đang tải cấu hình...</div>
        <template v-else-if="policyConfig">
          <!-- Thông tin chính sách (chỉ đọc) -->
          <div class="policy-panel">
            <div class="policy-panel-title">Ngưỡng từ chính sách</div>
            <div class="policy-row">
              <span class="policy-label">Ngưỡng cảnh báo:</span>
              <strong class="policy-value tone-warning">{{ policyConfig.warning_threshold }}</strong>
            </div>
            <div class="policy-row">
              <span class="policy-label">Ngưỡng thực hiện thao tác (Ẩn/Khóa):</span>
              <strong class="policy-value tone-danger">{{ policyConfig.lock_threshold }}</strong>
            </div>
            <div class="policy-row">
              <span class="policy-label">Số người báo cáo khác nhau:</span>
              <strong class="policy-value tone-info">{{ policyConfig.unique_reporters_threshold }} người</strong>
            </div>
            <div class="policy-row">
              <span class="policy-label">Thời gian theo dõi (Ngày):</span>
              <strong class="policy-value tone-neutral">{{ policyConfig.window_days }} ngày</strong>
            </div>
          </div>

          <!-- Cấu hình chỉnh sửa -->
          <div class="policy-panel policy-edit-panel">
            <div class="policy-row policy-toggle-row">
              <span class="policy-toggle-label">Tự động khóa:</span>
              <div class="toggle-slider" :class="{ on: policyConfig.is_auto_lock_enabled }" @click="policyConfig.is_auto_lock_enabled = !policyConfig.is_auto_lock_enabled"></div>
            </div>
            <div v-if="policyConfig.is_auto_lock_enabled" class="policy-auto-fields">
              <label class="policy-field">
                <span class="policy-label">Lý do khóa tự động:</span>
                <input type="text" v-model="policyConfig.reason" class="policy-input" placeholder="Ví dụ: Vi phạm tiêu chuẩn cộng đồng nhiều lần" />
              </label>
              <div class="policy-row">
                <span class="policy-label">Thời hạn khóa:</span>
                <div class="policy-duration-control">
                  <input type="number" v-model.number="policyConfig.duration_days" class="policy-number-input" min="1" />
                  <span class="muted">ngày</span>
                </div>
              </div>
            </div>
          </div>
          
          <div class="policy-info">
            <AppIcon name="info" size="16" class="policy-info-icon" />
            <div>
              Khi số người báo cáo khác nhau đạt <strong>ngưỡng cảnh báo</strong>, tài khoản sẽ hiển thị cảnh báo vàng. Khi đạt <strong>ngưỡng khóa</strong> và tự động khóa đang bật, hệ thống sẽ tự động khóa tài khoản.
            </div>
          </div>
          
          <div class="policy-link-wrap">
            <router-link v-if="policyConfig.policy_id" :to="`/admin/policies/${policyConfig.policy_id}`" class="btn secondary policy-link-button">
              Chỉnh ngưỡng tại Chính sách hệ thống →
            </router-link>
          </div>
        </template>

        <footer class="policy-footer">
          <button type="button" class="btn secondary" @click="closePolicyModal">Hủy</button>
          <button type="button" class="btn primary" @click="savePolicyConfig" :disabled="policySaving">Lưu cấu hình</button>
        </footer>
      </div>
    </div>
  </section>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import { adminUserService } from '../../services/adminUserService.js';
import { getAccountStatusLabel } from '../../utils/labelMaps.js';

export default {
  name: 'AdminUsers',
  components: { ActionIconButton, AppIcon, TableActionGroup },
  data() {
    return {
      users: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
      loading: false,
      saving: false,
      error: '',
      success: '',
      searchTimer: null,
      filters: {
        keyword: '',
        status: '',
        role: '',
        warning_level: '',
        page: 1,
        per_page: 15,
      },
      actionTarget: null,
      actionType: 'lock',
      actionForm: {
        lock_type: 'temporary',
        locked_until: '',
        reason: '',
      },
      tabs: [
        { value: '', label: 'Tất cả tài khoản' },
        { value: 'active', label: 'Đang hoạt động' },
        { value: 'warning', label: 'Tài khoản cảnh báo' },
        { value: 'locked', label: 'Tài khoản đã khóa' },
        { value: 'pending_verify', label: 'Chờ xác thực' },
      ],
      roleOptions: [
        { value: 'super_admin', label: 'Super admin' },
        { value: 'admin', label: 'Quản trị viên' },
        { value: 'system_staff', label: 'Nhân viên hệ thống' },
        { value: 'venue_owner', label: 'Chủ sân' },
        { value: 'venue_staff', label: 'Nhân viên sân' },
        { value: 'user', label: 'Người dùng' },
      ],
      lockTypes: [
        { value: 'temporary', label: 'Tạm thời' },
        { value: 'permanent', label: 'Vĩnh viễn' },
      ],
      showPolicyModal: false,
      policyConfig: null,
      policyLoading: false,
      policySaving: false,
    };
  },
  mounted() {
    this.loadUsers();
  },
  beforeUnmount() {
    clearTimeout(this.searchTimer);
  },
  methods: {
    setStatus(status) {
      this.filters.status = status;
      if (status !== 'warning') this.filters.warning_level = '';
      this.reloadFromFirstPage();
    },
    scheduleSearch() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.reloadFromFirstPage(), 350);
    },
    reloadFromFirstPage() {
      this.filters.page = 1;
      this.loadUsers();
    },
    resetFilters() {
      this.filters = {
        keyword: '',
        status: '',
        role: '',
        warning_level: '',
        page: 1,
        per_page: 15,
      };
      this.loadUsers();
    },
    goPage(page) {
      this.filters.page = page;
      this.loadUsers();
    },
    async loadUsers() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminUserService.list(this.filters);
        this.users = response.data || [];
        this.meta = response.meta || this.meta;
      } catch (error) {
        this.error = error.message || 'Không tải được danh sách tài khoản.';
      } finally {
        this.loading = false;
      }
    },
    openLockModal(user) {
      this.actionTarget = user;
      this.actionType = 'lock';
      this.actionForm = {
        lock_type: 'temporary',
        locked_until: this.inputDate(new Date(Date.now() + 24 * 60 * 60 * 1000)),
        reason: '',
      };
    },
    openUnlockModal(user) {
      this.actionTarget = user;
      this.actionType = 'unlock';
      this.actionForm = {
        lock_type: 'temporary',
        locked_until: '',
        reason: '',
      };
    },
    closeActionModal() {
      this.actionTarget = null;
      this.error = '';
    },
    async submitAccountAction() {
      this.saving = true;
      this.error = '';
      try {
        const response = this.actionType === 'lock'
          ? await adminUserService.lock(this.actionTarget.id, {
              lock_type: this.actionForm.lock_type,
              status_reason: this.actionForm.reason,
              locked_until: this.actionForm.lock_type === 'temporary' ? this.actionForm.locked_until : null,
            })
          : await adminUserService.unlock(this.actionTarget.id, { reason: this.actionForm.reason });
        this.success = response.message;
        this.closeActionModal();
        await this.loadUsers();
      } catch (error) {
        this.error = error.message || 'Không thể cập nhật trạng thái tài khoản.';
      } finally {
        this.saving = false;
      }
    },
    async openPolicyModal() {
      this.showPolicyModal = true;
      await this.fetchPolicy();
    },
    async fetchPolicy() {
      this.policyLoading = true;
      try {
        const res = await adminUserService.getLockPolicy();
        this.policyConfig = res.data;
      } catch (e) {
        this.error = 'Không thể tải cấu hình khóa tự động.';
      } finally {
        this.policyLoading = false;
      }
    },
    async savePolicyConfig() {
      this.policySaving = true;
      this.error = '';
      try {
        await adminUserService.saveLockPolicy(this.policyConfig);
        this.success = 'Lưu cấu hình thành công.';
        this.closePolicyModal();
      } catch (e) {
        this.error = e.message || 'Lỗi khi lưu cấu hình.';
      } finally {
        this.policySaving = false;
      }
    },
    closePolicyModal() {
      this.showPolicyModal = false;
    },
    getAccountStatusLabel,
    money(value) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
    },
    date(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    dateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
    inputDate(value) {
      const date = new Date(value);
      return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    },
  },
};
</script>

<style scoped>
.admin-users {
  display: grid;
  gap: 16px;
}

.action-bar-layout,
.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.head-actions,
.pagination div,
.policy-footer {
  display: flex;
  align-items: center;
  gap: 10px;
}

.tabs,
.segmented {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.tabs button,
.segmented button {
  min-height: 38px;
  border: 1px solid var(--admin-border);
  background: var(--admin-surface);
  color: var(--admin-text);
  border-radius: var(--admin-radius);
  padding: 10px 14px;
  font-weight: 800;
  cursor: pointer;
}

.tabs button.active,
.segmented button.active {
  background: var(--admin-primary);
  border-color: var(--admin-primary);
  color: var(--admin-primary-text);
}

.filters {
  justify-content: flex-start;
  flex-wrap: wrap;
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  padding: 14px;
}

label {
  display: grid;
  gap: 6px;
  font-weight: 800;
  color: var(--admin-text);
}

label span {
  font-size: 13px;
}

input,
select,
textarea {
  min-width: 220px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  padding: 10px;
  background: var(--admin-surface);
  color: var(--admin-text);
  font: inherit;
}

input:focus,
select:focus,
textarea:focus {
  border-color: var(--admin-primary);
  outline: none;
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}

textarea {
  min-width: 100%;
  resize: vertical;
}

.table-card,
.modal {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius-lg);
  box-shadow: var(--admin-shadow-card);
}

.table-card {
  overflow: auto;
}

table {
  width: 100%;
  min-width: 1120px;
  border-collapse: collapse;
}

th,
td {
  padding: 12px;
  border-bottom: 1px solid var(--admin-border-soft);
  text-align: left;
  vertical-align: top;
}

th {
  background: var(--admin-surface);
  color: var(--admin-muted);
  font-weight: 700;
}

td:first-child {
  display: grid;
  gap: 4px;
}

.actions-col {
  position: sticky;
  right: 0;
  background: var(--admin-surface);
  box-shadow: -1px 0 0 var(--admin-border-soft);
  white-space: nowrap;
}

.state {
  padding: 20px;
  color: var(--admin-muted);
  text-align: center;
}

.btn,
.icon-btn {
  border-radius: var(--admin-radius);
  border: 1px solid transparent;
  font-weight: 800;
  cursor: pointer;
  text-decoration: none;
}

.btn {
  padding: 10px 14px;
}

.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 34px;
  padding: 7px 10px;
  margin-right: 6px;
  background: var(--admin-surface-muted);
  color: var(--admin-text);
}

.primary {
  background: var(--admin-primary);
  color: var(--admin-primary-text);
  border-color: var(--admin-primary);
}

.secondary {
  background: var(--admin-surface-muted);
  color: var(--admin-text);
  border-color: var(--admin-border);
}

.danger,
.icon-btn.danger {
  background: var(--admin-danger-soft);
  color: var(--admin-danger-text);
  border-color: color-mix(in srgb, var(--admin-danger) 22%, transparent);
}

.status,
.warning {
  display: inline-flex;
  align-items: center;
  width: fit-content;
  padding: 0;
  border-radius: 0;
  background: transparent;
  color: var(--admin-muted);
  font-size: 13px;
  font-weight: 500;
  line-height: 1.25;
  white-space: nowrap;
}

.status.active,
.warning.normal {
  color: var(--admin-success-text);
}

.status.locked,
.status.deactivated,
.warning.lock_suggested {
  color: var(--admin-danger-text);
}

.status.pending_verify,
.warning.watch,
.warning.near_lock {
  color: var(--admin-warning);
}

.alert {
  padding: 12px;
  border-radius: var(--admin-radius);
  font-weight: 700;
}

.error {
  background: var(--admin-danger-soft);
  color: var(--admin-danger-text);
}

.success {
  background: var(--admin-success-soft);
  color: var(--admin-success-text);
}

.pagination {
  color: var(--admin-muted);
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  display: grid;
  place-items: center;
  z-index: 500;
  padding: 20px;
  background: color-mix(in srgb, var(--admin-bg) 72%, transparent);
}

.modal {
  width: min(640px, calc(100vw - 32px));
  padding: 22px;
  display: grid;
  gap: 16px;
}

.policy-modal {
  max-width: 500px;
}

.modal h3 {
  margin: 0;
  color: var(--admin-text);
}

.modal footer {
  justify-content: flex-end;
}

.muted,
.policy-label {
  color: var(--admin-muted);
}

.policy-modal-subtitle,
.policy-link-wrap,
.policy-footer {
  margin-top: 12px;
}

.policy-panel {
  margin-top: 16px;
  padding: 14px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface-muted);
}

.policy-edit-panel {
  margin-top: 12px;
  padding: 16px;
}

.policy-panel-title {
  margin-bottom: 10px;
  color: var(--admin-text);
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.policy-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.policy-row:last-child {
  margin-bottom: 0;
}

.policy-toggle-row {
  margin-bottom: 12px;
}

.policy-label,
.policy-toggle-label {
  font-size: 0.9rem;
}

.policy-toggle-label {
  color: var(--admin-text);
  font-weight: 600;
}

.policy-value.tone-warning {
  color: var(--admin-warning);
}

.policy-value.tone-danger {
  color: var(--admin-danger);
}

.policy-value.tone-info {
  color: var(--admin-blue);
}

.policy-value.tone-neutral {
  color: var(--admin-text);
}

.policy-auto-fields {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid var(--admin-border);
}

.policy-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.policy-input {
  padding: 8px;
}

.policy-duration-control {
  display: flex;
  align-items: center;
  gap: 8px;
}

.policy-number-input {
  width: 80px;
  min-width: 80px;
  padding: 6px;
}

.policy-info {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 12px;
  padding: 10px 12px;
  border-radius: var(--admin-radius);
  background: var(--admin-blue-soft);
  color: var(--admin-blue);
  font-size: 0.85rem;
}

.policy-info-icon {
  flex-shrink: 0;
  margin-top: 2px;
}

.policy-link-wrap {
  text-align: center;
}

.policy-link-button {
  display: inline-block;
  font-size: 0.85rem;
  text-decoration: none;
}

.toggle-slider {
  position: relative;
  width: 48px;
  height: 26px;
  border-radius: 13px;
  background: var(--admin-surface-muted);
  border: 1px solid var(--admin-border);
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s;
}

.toggle-slider::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--admin-surface);
  box-shadow: var(--admin-shadow-sm);
  transition: transform 0.2s;
}

.toggle-slider.on {
  background: var(--admin-success);
  border-color: var(--admin-success);
}

.toggle-slider.on::after {
  transform: translateX(22px);
}

.badge-report,
.badge-locked,
.lock-until {
  display: inline-flex;
  color: var(--admin-danger-text);
}

.badge-report,
.badge-locked {
  align-items: center;
  gap: 4px;
  border-radius: 999px;
  padding: 3px 8px;
  background: var(--admin-danger-soft);
  font-size: 11px;
  font-weight: 800;
}

.badge-report-icon {
  flex-shrink: 0;
}

.lock-until {
  display: block;
  font-size: 11px;
}

@media (max-width: 720px) {
  .action-bar-layout,
  .filters,
  .pagination,
  .pagination div {
    flex-direction: column;
    align-items: stretch;
  }

  input,
  select {
    min-width: 0;
    width: 100%;
  }
}
</style>

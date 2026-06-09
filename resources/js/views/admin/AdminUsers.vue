<template>
  <section class="page">
    <header class="page-head">
      <div>
        <p class="eyebrow">Quản lý tài khoản</p>
        <h2>Danh sách tài khoản</h2>
        <p>Danh sách chỉ hiển thị thông tin tổng quan. Ví, booking, cảnh báo và audit nằm trong màn chi tiết.</p>
      </div>
      <button class="btn secondary" type="button" :disabled="loading" @click="loadUsers()">
        <AppIcon name="refresh" size="16" />
        Tải lại
      </button>
    </header>

    <section class="stat-grid" aria-label="Tổng quan tài khoản">
      <article class="stat-card">
        <strong>{{ summary.total || 0 }}</strong>
        <span>Tổng tài khoản</span>
      </article>
      <article class="stat-card success">
        <strong>{{ summary.active || 0 }}</strong>
        <span>Đang hoạt động</span>
      </article>
      <article class="stat-card warning">
        <strong>{{ summary.warning || 0 }}</strong>
        <span>Tài khoản cảnh báo</span>
      </article>
      <article class="stat-card danger">
        <strong>{{ summary.locked || 0 }}</strong>
        <span>Đã khóa</span>
      </article>
      <article class="stat-card muted">
        <strong>{{ summary.pending_verify || 0 }}</strong>
        <span>Chờ xác thực</span>
      </article>
    </section>

    <nav class="tabs" aria-label="Bộ lọc nhanh tài khoản">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        :class="{ active: filters.status === tab.value }"
        @click="setStatus(tab.value)"
      >
        {{ tab.label }}
      </button>
    </nav>

    <section class="filters">
      <label class="search-box">
        <AppIcon name="search" size="17" />
        <input
          v-model.trim="filters.keyword"
          placeholder="Tên, username, email hoặc số điện thoại"
          @input="scheduleSearch"
          @keyup.enter="reloadFromFirstPage"
        />
      </label>

      <select v-model="filters.role" @change="reloadFromFirstPage">
        <option value="">Tất cả vai trò</option>
        <option v-for="role in roleOptions" :key="role.value" :value="role.value">{{ role.label }}</option>
      </select>

      <select v-if="filters.status === 'warning'" v-model="filters.warning_level" @change="reloadFromFirstPage">
        <option value="">Tất cả cảnh báo</option>
        <option value="watch">Cần theo dõi</option>
        <option value="near_lock">Gần ngưỡng khóa</option>
        <option value="lock_suggested">Đề xuất khóa</option>
      </select>

      <button class="btn secondary" type="button" @click="resetFilters">Xóa lọc</button>
    </section>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="table-card">
      <div v-if="loading" class="state">
        <span class="spinner"></span>
        Đang tải danh sách tài khoản...
      </div>

      <table v-else>
        <thead>
          <tr>
            <th>Người dùng</th>
            <th>Vai trò chính</th>
            <th>Trạng thái</th>
            <th>Cảnh báo</th>
            <th>Ngày tạo</th>
            <th class="actions-col">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="users.length === 0">
            <td colspan="6" class="empty-cell">Không có tài khoản phù hợp với bộ lọc hiện tại.</td>
          </tr>

          <tr v-for="user in users" :key="user.id">
            <td>
              <div class="user-cell">
                <div class="avatar">{{ initials(user.full_name || user.username) }}</div>
                <div>
                  <strong>{{ user.full_name || 'Chưa cập nhật tên' }}</strong>
                  <span>@{{ user.username || '-' }} · {{ user.email || user.phone || 'Chưa có liên hệ' }}</span>
                </div>
              </div>
            </td>
            <td>{{ user.primary_role_label || 'Chưa gán vai trò' }}</td>
            <td>
              <span class="badge" :class="statusTone(user.status)">
                {{ user.status_label || statusLabel(user.status) }}
              </span>
            </td>
            <td>
              <span class="warning-pill" :class="warningTone(user.warning_summary?.level)">
                {{ user.warning_summary?.label || 'Bình thường' }}
              </span>
              <span>{{ warningSignalCount(user) }} tín hiệu gần đây</span>
            </td>
            <td>{{ formatDate(user.created_at) }}</td>
            <td class="actions">
              <button class="icon-btn" type="button" title="Xem chi tiết" @click="goDetail(user)">
                <AppIcon name="eye" size="16" />
              </button>
              <button
                v-if="user.status === 'locked'"
                class="icon-btn success"
                type="button"
                title="Mở khóa tài khoản"
                @click="openUnlock(user)"
              >
                <AppIcon name="unlock" size="16" />
              </button>
              <button
                v-else
                class="icon-btn danger"
                type="button"
                title="Khóa tài khoản"
                @click="openLock(user)"
              >
                <AppIcon name="lock" size="16" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <footer v-if="meta.total > meta.per_page" class="pagination">
      <button class="btn secondary" type="button" :disabled="meta.current_page <= 1" @click="loadUsers(meta.current_page - 1)">
        Trước
      </button>
      <span>Trang {{ meta.current_page }} / {{ meta.last_page }} - {{ meta.total }} tài khoản</span>
      <button class="btn secondary" type="button" :disabled="meta.current_page >= meta.last_page" @click="loadUsers(meta.current_page + 1)">
        Sau
      </button>
    </footer>

    <div v-if="actionModal.show" class="modal-backdrop" @click.self="closeAction">
      <form class="modal" @submit.prevent="submitAction">
        <header class="modal-head">
          <div>
            <p class="eyebrow">{{ actionModal.type === 'lock' ? 'Khóa tài khoản' : 'Mở khóa tài khoản' }}</p>
            <h3>{{ actionModal.user?.full_name || actionModal.user?.username }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeAction">
            <AppIcon name="x" size="16" />
          </button>
        </header>

        <template v-if="actionModal.type === 'lock'">
          <label>
            Loại khóa
            <select v-model="actionModal.lock_type">
              <option value="temporary">Khóa tạm thời</option>
              <option value="permanent">Khóa vĩnh viễn</option>
              <option value="auto">Khóa tự động</option>
            </select>
          </label>
          <label v-if="actionModal.lock_type === 'temporary'">
            Khóa đến
            <input v-model="actionModal.locked_until" type="datetime-local" required />
          </label>
          <label>
            Lý do khóa
            <textarea v-model.trim="actionModal.reason" rows="4" required placeholder="Nhập lý do để người dùng và audit log hiểu rõ." />
          </label>
        </template>

        <label v-else>
          Lý do mở khóa
          <textarea v-model.trim="actionModal.reason" rows="4" required placeholder="Nhập lý do mở khóa." />
        </label>

        <footer>
          <button class="btn secondary" type="button" @click="closeAction">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">
            {{ saving ? 'Đang xử lý...' : 'Xác nhận' }}
          </button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { adminUserService } from '../../services/adminUserService.js';

export default {
  name: 'AdminUsers',
  components: { AppIcon },
  data() {
    return {
      users: [],
      summary: { total: 0, active: 0, warning: 0, locked: 0, pending_verify: 0 },
      meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 },
      filters: { status: '', keyword: '', role: '', warning_level: '', per_page: 12 },
      tabs: [
        { value: '', label: 'Tất cả' },
        { value: 'active', label: 'Đang hoạt động' },
        { value: 'warning', label: 'Tài khoản cảnh báo' },
        { value: 'locked', label: 'Đã khóa' },
        { value: 'pending_verify', label: 'Chờ xác thực' },
      ],
      roleOptions: [
        { value: 'super_admin', label: 'Super admin' },
        { value: 'admin', label: 'Admin' },
        { value: 'system_staff', label: 'Nhân viên hệ thống' },
        { value: 'venue_owner', label: 'Chủ sân' },
        { value: 'venue_staff', label: 'Nhân viên sân' },
        { value: 'user', label: 'Người dùng' },
      ],
      loading: false,
      saving: false,
      error: '',
      success: '',
      searchTimer: null,
      actionModal: { show: false, type: '', user: null, reason: '', lock_type: 'temporary', locked_until: '' },
    };
  },
  mounted() {
    this.loadUsers();
  },
  beforeUnmount() {
    clearTimeout(this.searchTimer);
  },
  methods: {
    async loadUsers(page = this.meta.current_page || 1) {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminUserService.list({ ...this.filters, page });
        this.users = response.data || [];
        this.summary = response.summary || this.summary;
        this.meta = response.meta || this.meta;
      } catch (error) {
        this.error = error.message || 'Không thể tải danh sách tài khoản.';
      } finally {
        this.loading = false;
      }
    },
    scheduleSearch() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.reloadFromFirstPage(), 350);
    },
    reloadFromFirstPage() {
      this.loadUsers(1);
    },
    setStatus(status) {
      this.filters.status = status;
      if (status !== 'warning') this.filters.warning_level = '';
      this.reloadFromFirstPage();
    },
    resetFilters() {
      this.filters = { status: '', keyword: '', role: '', warning_level: '', per_page: 12 };
      this.reloadFromFirstPage();
    },
    goDetail(user) {
      this.$router.push({ name: 'admin-user-detail', params: { id: user.id } });
    },
    openLock(user) {
      this.actionModal = {
        show: true,
        type: 'lock',
        user,
        reason: '',
        lock_type: 'temporary',
        locked_until: this.inputDate(new Date(Date.now() + 24 * 60 * 60 * 1000)),
      };
    },
    openUnlock(user) {
      this.actionModal = { show: true, type: 'unlock', user, reason: '', lock_type: 'temporary', locked_until: '' };
    },
    closeAction() {
      this.actionModal.show = false;
    },
    async submitAction() {
      if (!this.actionModal.user) return;

      this.saving = true;
      this.error = '';
      try {
        const payload = this.actionModal.type === 'lock'
          ? {
              lock_type: this.actionModal.lock_type,
              status_reason: this.actionModal.reason,
              locked_until: this.actionModal.lock_type === 'temporary' ? this.actionModal.locked_until : null,
            }
          : { reason: this.actionModal.reason };

        const response = this.actionModal.type === 'lock'
          ? await adminUserService.lock(this.actionModal.user.id, payload)
          : await adminUserService.unlock(this.actionModal.user.id, payload);

        this.success = response.message || 'Đã cập nhật trạng thái tài khoản.';
        this.closeAction();
        await this.loadUsers();
      } catch (error) {
        this.error = error.message || 'Không thể xử lý tài khoản.';
      } finally {
        this.saving = false;
      }
    },
    statusLabel(status) {
      return {
        active: 'Đang hoạt động',
        locked: 'Đã khóa',
        pending_verify: 'Chờ xác thực',
        deactivated: 'Đã vô hiệu hóa',
      }[status] || 'Không xác định';
    },
    statusTone(status) {
      return {
        active: 'success',
        locked: 'danger',
        pending_verify: 'warning',
        deactivated: 'muted',
      }[status] || 'muted';
    },
    warningTone(level) {
      return {
        normal: 'normal',
        watch: 'watch',
        near_lock: 'near-lock',
        lock_suggested: 'danger',
      }[level] || 'normal';
    },
    warningSignalCount(user) {
      return (Number(user.reports_count_recent) || 0) + (Number(user.complaints_count_recent) || 0);
    },
    initials(value) {
      return (value || 'U')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleDateString('vi-VN') : '-';
    },
    inputDate(value) {
      const date = new Date(value);
      return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    },
  },
};
</script>

<style scoped>
.page { display: grid; gap: 16px; }
.page-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.page-head h2 { margin: 0 0 6px; }
.page-head p { margin: 0; color: #64748b; }
.eyebrow { margin: 0 0 6px; color: #15803d; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
.tabs, .filters, .pagination { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.stat-grid { display: grid; grid-template-columns: repeat(5, minmax(140px, 1fr)); gap: 12px; }
.stat-card { min-height: 92px; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); display: grid; align-content: center; gap: 4px; }
.stat-card strong { font-size: 28px; line-height: 1; color: #0f172a; }
.stat-card span { color: #64748b; font-weight: 800; }
.stat-card.success { border-color: #bbf7d0; background: #f0fdf4; }
.stat-card.warning { border-color: #fde68a; background: #fffbeb; }
.stat-card.danger { border-color: #fecaca; background: #fef2f2; }
.stat-card.muted { border-color: #dbe3ef; background: #f8fafc; }
.tabs button { border: 1px solid #dbe3ef; background: #fff; border-radius: 8px; padding: 10px 14px; font-weight: 800; cursor: pointer; }
.tabs button.active { background: #dcfce7; border-color: #22c55e; color: #166534; }
.filters { padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
.search-box { flex: 1 1 320px; display: flex; align-items: center; gap: 8px; min-width: 220px; }
.filters input, .filters select, .modal input, .modal select, .modal textarea { width: 100%; border: 1px solid #dbe3ef; border-radius: 8px; padding: 10px 12px; font: inherit; background: #fff; color: #0f172a; }
.filters select { max-width: 230px; }
.table-card { overflow: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; }
table { width: 100%; border-collapse: collapse; min-width: 900px; }
th, td { padding: 14px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
th { color: #334155; font-size: 12px; text-transform: uppercase; background: #f8fafc; }
td strong, td span { display: block; }
td span { margin-top: 4px; color: #64748b; font-size: 13px; }
.user-cell { display: flex; align-items: center; gap: 12px; min-width: 260px; }
.avatar { width: 42px; height: 42px; border-radius: 12px; background: #052e16; color: #bbf7d0; display: grid; place-items: center; font-weight: 900; flex: 0 0 auto; }
.actions-col { width: 120px; text-align: right; }
.actions { display: flex; justify-content: flex-end; gap: 8px; }
.btn, .icon-btn { border: 0; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-weight: 800; cursor: pointer; }
.btn { padding: 10px 14px; }
.btn.primary { background: #16a34a; color: #fff; }
.btn.secondary { background: #f1f5f9; color: #334155; }
.icon-btn { width: 36px; height: 36px; border: 1px solid #dbe3ef; background: #fff; color: #334155; }
.icon-btn.success { color: #15803d; border-color: #bbf7d0; background: #f0fdf4; }
.icon-btn.danger { color: #b91c1c; border-color: #fecaca; background: #fef2f2; }
.badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 900; white-space: nowrap; }
.badge.success { background: #dcfce7; color: #166534; }
.badge.danger { background: #fee2e2; color: #991b1b; }
.badge.warning { background: #fef3c7; color: #92400e; }
.badge.muted { background: #f1f5f9; color: #475569; }
.warning-pill { display: inline-flex; width: fit-content; align-items: center; border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 900; }
.warning-pill.normal { background: #dcfce7; color: #166534; }
.warning-pill.watch { background: #fef3c7; color: #92400e; }
.warning-pill.near-lock { background: #fed7aa; color: #9a3412; }
.warning-pill.danger { background: #fee2e2; color: #991b1b; }
.state, .empty-cell { padding: 28px; text-align: center; color: #64748b; font-weight: 700; }
.spinner { width: 18px; height: 18px; border: 2px solid #bbf7d0; border-top-color: #16a34a; border-radius: 50%; display: inline-block; margin-right: 8px; animation: spin .8s linear infinite; vertical-align: middle; }
.alert { border-radius: 10px; padding: 12px 14px; font-weight: 800; }
.alert.error { background: #fef2f2; color: #991b1b; }
.alert.success { background: #f0fdf4; color: #166534; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .45); display: grid; place-items: center; z-index: 50; padding: 20px; }
.modal { width: min(540px, 100%); background: #fff; border-radius: 14px; padding: 18px; display: grid; gap: 14px; box-shadow: 0 24px 80px rgba(15, 23, 42, .22); }
.modal-head, .modal footer { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.modal h3 { margin: 0; }
.modal label { display: grid; gap: 7px; color: #334155; font-weight: 800; }
.pagination { justify-content: flex-end; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 760px) {
  .page-head { display: grid; }
  .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .filters select { max-width: none; width: 100%; }
  .pagination { justify-content: flex-start; }
}
</style>

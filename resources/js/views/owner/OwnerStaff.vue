<template>
  <section class="page">
    <header class="page-head hero-card">
      <div>
        <p class="eyebrow">Nhân viên sân</p>
        <h2>Quản lý nhân viên theo cụm sân</h2>
        <p>Tạo tài khoản nhân viên sân và gán phạm vi làm việc đúng cụm đang chọn.</p>
      </div>
      <button class="btn primary" type="button" @click="openCreate">
        <AppIcon name="plus" size="16" />
        Thêm nhân viên
      </button>
    </header>

    <section class="stat-grid">
      <article class="stat-card">
        <strong>{{ summary.total || 0 }}</strong>
        <span>Tổng nhân viên</span>
      </article>
      <article class="stat-card success">
        <strong>{{ summary.active || 0 }}</strong>
        <span>Đang hoạt động</span>
      </article>
      <article class="stat-card warning">
        <strong>{{ summary.all_cluster || 0 }}</strong>
        <span>Quản lý toàn cụm</span>
      </article>
      <article class="stat-card danger">
        <strong>{{ (summary.locked || 0) + (summary.deactivated || 0) }}</strong>
        <span>Đã khóa/tạm ngưng</span>
      </article>
    </section>

    <section class="filters">
      <label class="search-box">
        <AppIcon name="search" size="17" />
        <input v-model.trim="filters.keyword" placeholder="Tìm tên, username, email, SĐT" @input="scheduleSearch" />
      </label>
      <select v-model="filters.status" @change="loadStaff">
        <option value="">Tất cả trạng thái</option>
        <option value="active">Đang hoạt động</option>
        <option value="locked">Đã khóa</option>
        <option value="deactivated">Đã tạm ngưng</option>
      </select>
      <button class="btn secondary" type="button" @click="resetFilters">Xóa lọc</button>
    </section>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="staff-panel">
      <div v-if="loading" class="state">
        <span class="spinner"></span>
        Đang tải nhân viên...
      </div>
      <div v-else-if="staff.length === 0" class="state">Chưa có nhân viên sân trong cụm này.</div>
      <div v-else class="staff-list">
        <article v-for="item in staff" :key="item.id" class="staff-card">
          <div class="avatar">{{ initials(item.full_name || item.username) }}</div>
          <div class="staff-main">
            <strong>{{ item.full_name || item.username }}</strong>
            <span>@{{ item.username }} · {{ item.email || item.phone || 'Chưa có liên hệ' }}</span>
          </div>
          <div class="scope-chip">
            <AppIcon name="layers" size="15" />
            {{ assignmentText(item.assignments) }}
          </div>
          <span class="badge" :class="statusTone(item.status)">{{ item.status_label || statusLabel(item.status) }}</span>
          <div class="actions">
            <button class="icon-btn" type="button" title="Sửa nhân viên" @click="openEdit(item)">
              <AppIcon name="pencil" size="16" />
            </button>
            <button class="icon-btn danger" type="button" title="Tạm ngưng nhân viên" @click="openDeactivate(item)">
              <AppIcon name="power" size="16" />
            </button>
          </div>
        </article>
      </div>
    </section>

    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <form class="modal" @submit.prevent="saveStaff">
        <header class="modal-head">
          <div>
            <p class="eyebrow">{{ form.id ? 'Cập nhật nhân viên' : 'Thêm nhân viên' }}</p>
            <h3>{{ form.id ? 'Sửa nhân viên sân' : 'Tạo nhân viên sân' }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="closeModal">
            <AppIcon name="x" size="16" />
          </button>
        </header>

        <div class="grid">
          <label>Họ tên<input v-model.trim="form.full_name" required /></label>
          <label>Username<input v-model.trim="form.username" :disabled="!!form.id" required /></label>
          <label>Email<input v-model.trim="form.email" type="email" /></label>
          <label>Số điện thoại<input v-model.trim="form.phone" /></label>
          <label v-if="!form.id">Mật khẩu tạm<input v-model="form.password" type="password" required minlength="8" /></label>
          <label v-else>
            Trạng thái
            <select v-model="form.status">
              <option value="active">Đang hoạt động</option>
              <option value="locked">Đã khóa</option>
              <option value="deactivated">Đã tạm ngưng</option>
            </select>
          </label>
        </div>

        <section class="scope-box">
          <div class="scope-head">
            <strong>Phạm vi làm việc</strong>
            <span>Không cấp quyền admin hệ thống cho nhân viên sân.</span>
          </div>
          <div class="segmented">
            <button type="button" :class="{ active: form.scope_type === 'all_cluster' }" @click="form.scope_type = 'all_cluster'">
              Toàn bộ cụm sân
            </button>
            <button type="button" :class="{ active: form.scope_type === 'court_type' }" @click="form.scope_type = 'court_type'">
              Theo loại sân
            </button>
          </div>
          <div v-if="form.scope_type === 'court_type'" class="court-type-box">
            <div class="quick-row">
              <span>Chọn loại sân trong cụm</span>
              <button class="link-btn" type="button" @click="selectAllCourtTypes">Chọn tất cả</button>
            </div>
            <label v-for="type in courtTypes" :key="type.id" class="check-card">
              <input v-model="form.court_type_ids" type="checkbox" :value="type.id" />
              {{ type.name }}
            </label>
            <p v-if="courtTypes.length === 0" class="muted">Cụm sân này chưa có loại sân để gán.</p>
          </div>
        </section>

        <footer>
          <button class="btn secondary" type="button" @click="closeModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">
            <AppIcon name="check" size="16" />
            {{ saving ? 'Đang lưu...' : 'Lưu nhân viên' }}
          </button>
        </footer>
      </form>
    </div>

    <div v-if="deactivateTarget" class="modal-backdrop" @click.self="deactivateTarget = null">
      <form class="modal small" @submit.prevent="deactivate">
        <header class="modal-head">
          <div>
            <p class="eyebrow">Tạm ngưng nhân viên</p>
            <h3>{{ deactivateTarget.full_name || deactivateTarget.username }}</h3>
          </div>
          <button class="icon-btn" type="button" title="Đóng" @click="deactivateTarget = null">
            <AppIcon name="x" size="16" />
          </button>
        </header>
        <label>
          Lý do
          <textarea v-model.trim="deactivateReason" rows="3" required />
        </label>
        <footer>
          <button class="btn secondary" type="button" @click="deactivateTarget = null">Hủy</button>
          <button class="btn danger" type="submit" :disabled="saving">Tạm ngưng</button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import { ownerStaffService } from '../../services/ownerStaffService.js';

export default {
  name: 'OwnerStaff',
  components: { AppIcon },
  data() {
    return {
      staff: [],
      summary: {},
      courtTypes: [],
      filters: { keyword: '', status: '' },
      loading: false,
      saving: false,
      error: '',
      success: '',
      showModal: false,
      form: this.emptyForm(),
      searchTimer: null,
      deactivateTarget: null,
      deactivateReason: 'Chủ sân tạm ngưng nhân viên.',
    };
  },
  mounted() {
    window.addEventListener('owner-cluster-changed', this.loadStaff);
    this.loadStaff();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.loadStaff);
    clearTimeout(this.searchTimer);
  },
  methods: {
    emptyForm() {
      return {
        id: null,
        full_name: '',
        username: '',
        email: '',
        phone: '',
        password: '',
        status: 'active',
        scope_type: 'all_cluster',
        court_type_ids: [],
      };
    },
    async loadStaff() {
      this.loading = true;
      this.error = '';
      try {
        const response = await ownerStaffService.list(this.filters);
        this.staff = response.data || [];
        this.summary = response.summary || {};
        this.courtTypes = response.meta?.court_types || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải danh sách nhân viên sân.';
      } finally {
        this.loading = false;
      }
    },
    scheduleSearch() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.loadStaff(), 300);
    },
    resetFilters() {
      this.filters = { keyword: '', status: '' };
      this.loadStaff();
    },
    openCreate() {
      this.form = this.emptyForm();
      this.showModal = true;
    },
    openEdit(item) {
      const courtTypeIds = (item.assignments || [])
        .filter((assignment) => assignment.scope_type === 'court_type' && assignment.status === 'active')
        .map((assignment) => assignment.court_type_id);

      this.form = {
        id: item.id,
        full_name: item.full_name,
        username: item.username,
        email: item.email || '',
        phone: item.phone || '',
        status: item.status,
        scope_type: courtTypeIds.length ? 'court_type' : 'all_cluster',
        court_type_ids: courtTypeIds,
      };
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
    },
    selectAllCourtTypes() {
      this.form.court_type_ids = this.courtTypes.map((type) => type.id);
    },
    async saveStaff() {
      this.saving = true;
      this.error = '';
      try {
        const response = this.form.id
          ? await ownerStaffService.update(this.form.id, this.form)
          : await ownerStaffService.create(this.form);
        this.success = response.message || 'Đã lưu nhân viên sân.';
        this.closeModal();
        await this.loadStaff();
      } catch (error) {
        this.error = error.message || 'Không thể lưu nhân viên sân.';
      } finally {
        this.saving = false;
      }
    },
    openDeactivate(item) {
      this.deactivateTarget = item;
      this.deactivateReason = 'Chủ sân tạm ngưng nhân viên.';
    },
    async deactivate() {
      if (!this.deactivateTarget) return;
      this.saving = true;
      try {
        const response = await ownerStaffService.deactivate(this.deactivateTarget.id, this.deactivateReason);
        this.success = response.message || 'Đã tạm ngưng nhân viên sân.';
        this.deactivateTarget = null;
        await this.loadStaff();
      } catch (error) {
        this.error = error.message || 'Không thể tạm ngưng nhân viên.';
      } finally {
        this.saving = false;
      }
    },
    assignmentText(assignments = []) {
      if (assignments.some((assignment) => assignment.scope_type === 'all_cluster' && assignment.status === 'active')) {
        return 'Toàn bộ cụm sân';
      }
      const names = assignments
        .filter((assignment) => assignment.status === 'active')
        .map((assignment) => assignment.court_type_name)
        .filter(Boolean);
      return names.length ? names.join(', ') : 'Chưa phân công';
    },
    statusLabel(status) {
      return { active: 'Đang hoạt động', locked: 'Đã khóa', deactivated: 'Đã tạm ngưng' }[status] || 'Không xác định';
    },
    statusTone(status) {
      return { active: 'success', locked: 'danger', deactivated: 'danger' }[status] || 'neutral';
    },
    initials(name) {
      return String(name || 'SG').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
    },
  },
};
</script>

<style scoped>
.page { display: grid; gap: 16px; }
.hero-card { background: linear-gradient(135deg, #07130d, #0f2418); color: #fff; border-radius: 16px; padding: 20px; }
.page-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; }
.page-head h2 { margin: 0 0 6px; }
.page-head p { margin: 0; color: #cbd5e1; }
.eyebrow { margin: 0 0 6px; color: #86efac; font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
.stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; display: grid; gap: 6px; }
.stat-card strong { font-size: 28px; color: #0f172a; }
.stat-card span { color: #64748b; font-weight: 800; }
.stat-card.success { border-color: #bbf7d0; background: #f0fdf4; }
.stat-card.warning { border-color: #fde68a; background: #fffbeb; }
.stat-card.danger { border-color: #fecaca; background: #fff7f7; }
.filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px; }
.search-box { flex: 1 1 320px; display: flex; align-items: center; gap: 8px; }
.search-box input, select, input, textarea { width: 100%; border: 1px solid #dbe3ef; border-radius: 10px; padding: 10px 12px; font: inherit; background: #fff; color: #0f172a; }
.filters select { max-width: 220px; }
.staff-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 10px; }
.staff-list { display: grid; gap: 10px; }
.staff-card { display: grid; grid-template-columns: 48px minmax(180px, 1fr) minmax(180px, 1.2fr) auto auto; align-items: center; gap: 12px; padding: 14px; border: 1px solid #e2e8f0; border-radius: 12px; }
.avatar { width: 48px; height: 48px; border-radius: 50%; display: grid; place-items: center; background: #16a34a; color: #fff; font-weight: 900; }
.staff-main { display: grid; gap: 4px; }
.staff-main span, .muted { color: #64748b; }
.scope-chip { display: inline-flex; align-items: center; gap: 7px; color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 999px; padding: 7px 10px; font-weight: 800; width: fit-content; }
.actions { display: flex; gap: 6px; justify-content: flex-end; }
.btn, .icon-btn { border: 0; border-radius: 10px; font-weight: 900; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
.btn { padding: 10px 14px; }
.icon-btn { width: 36px; height: 36px; border: 1px solid #dbe3ef; background: #fff; color: #334155; }
.primary { background: #16a34a; color: #fff; }
.secondary { background: #f1f5f9; color: #0f172a; }
.danger, .icon-btn.danger { background: #fee2e2; color: #b91c1c; }
.badge { border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 900; white-space: nowrap; }
.badge.success { background: #dcfce7; color: #166534; }
.badge.danger { background: #fee2e2; color: #991b1b; }
.badge.neutral { background: #f1f5f9; color: #475569; }
.state { padding: 24px; text-align: center; color: #64748b; font-weight: 800; }
.alert { padding: 12px; border-radius: 10px; font-weight: 800; }
.alert.error { background: #fee2e2; color: #991b1b; }
.alert.success { background: #dcfce7; color: #166534; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, .56); display: grid; place-items: center; z-index: 500; padding: 20px; }
.modal { width: min(760px, calc(100vw - 32px)); max-height: 92vh; overflow: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 22px; display: grid; gap: 16px; }
.modal.small { width: min(520px, calc(100vw - 32px)); }
.modal-head, footer { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
.modal h3 { margin: 0; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
label { display: grid; gap: 6px; font-weight: 800; color: #334155; }
.scope-box { display: grid; gap: 12px; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; }
.scope-head { display: grid; gap: 4px; }
.scope-head span { color: #64748b; font-size: 13px; }
.segmented { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.segmented button { border: 1px solid #dbe3ef; background: #fff; border-radius: 10px; padding: 10px; font-weight: 900; cursor: pointer; }
.segmented button.active { border-color: #22c55e; background: #dcfce7; color: #166534; }
.court-type-box { display: grid; gap: 8px; }
.quick-row { display: flex; justify-content: space-between; align-items: center; color: #64748b; font-weight: 800; }
.link-btn { border: 0; background: transparent; color: #16a34a; font-weight: 900; cursor: pointer; }
.check-card { display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; }
.check-card input { width: auto; }
.spinner { width: 18px; height: 18px; border: 2px solid #bbf7d0; border-top-color: #16a34a; border-radius: 50%; display: inline-block; margin-right: 8px; animation: spin .8s linear infinite; vertical-align: middle; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 920px) {
  .page-head, .modal-head, footer { display: grid; }
  .stat-grid, .grid, .segmented { grid-template-columns: 1fr; }
  .staff-card { grid-template-columns: 48px 1fr; }
  .scope-chip, .badge, .actions { grid-column: 1 / -1; }
  .filters select { max-width: none; }
}
</style>

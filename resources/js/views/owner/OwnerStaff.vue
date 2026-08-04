<template>
  <section class="page">
    <!-- Floating Add Button -->
    <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
      <button class="btn-float-add" type="button" @click="openCreate" title="Thêm nhân viên">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Thêm nhân viên</span>
      </button>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <section class="table-card">
      <div v-if="loading" class="state">Đang tải nhân viên...</div>
      <div v-else-if="staff.length === 0" class="state">Chưa có nhân viên sân trong cụm này.</div>
      <table v-else>
        <thead>
          <tr>
            <th>Họ tên</th>
            <th>Tài khoản</th>
            <th>Email/SĐT</th>
            <th>Phạm vi quản lý</th>
            <th>Quyền chức năng</th>
            <th>Trạng thái</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in staff" :key="item.id">
            <td><strong>{{ item.full_name }}</strong></td>
            <td>{{ item.username }}</td>
            <td>{{ item.email || item.phone || '-' }}</td>
            <td>{{ assignmentText(item.assignments) }}</td>
            <td>
              <div class="permission-chips">
                <span v-for="label in menuLabels(item.menu_permissions)" :key="label" class="permission-chip">
                  {{ label }}
                </span>
                <span v-if="!item.menu_permissions?.length" class="muted">Chưa cấp quyền</span>
              </div>
            </td>
            <td><span class="badge" :class="item.status">{{ statusLabel(item.status) }}</span></td>
            <td>
              <TableActionGroup>
                <ActionIconButton icon="pencil" label="Sửa nhân viên" @click="openEdit(item)" />
                <ActionIconButton icon="power" label="Tạm ngưng nhân viên" variant="danger" @click="deactivate(item)" />
              </TableActionGroup>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <form class="modal" @submit.prevent="saveStaff">
        <h3>{{ form.id ? 'Sửa nhân viên sân' : 'Thêm nhân viên sân' }}</h3>
        <div class="grid">
          <label>Họ tên<input v-model.trim="form.full_name" required /></label>
          <label>Username<input v-model.trim="form.username" :disabled="!!form.id" required /></label>
          <label>Email<input v-model.trim="form.email" type="email" /></label>
          <label>SĐT<input v-model.trim="form.phone" /></label>
          <label v-if="!form.id">Mật khẩu tạm<input v-model="form.password" type="password" required minlength="8" /></label>
          <label v-else>Trạng thái
            <select v-model="form.status">
              <option value="active">Đang hoạt động</option>
              <option value="locked">Đã khóa</option>
              <option value="deactivated">Đã vô hiệu hóa</option>
            </select>
          </label>
        </div>

        <div class="scope-box">
          <span>Phạm vi làm việc</span>
          <label class="check"><input v-model="form.scope_type" type="radio" value="all_cluster" @change="normalizeMenuPermissions" /> Toàn bộ cụm sân</label>
          <label class="check"><input v-model="form.scope_type" type="radio" value="court_type" @change="normalizeMenuPermissions" /> Theo loại sân con</label>
          <div v-if="form.scope_type === 'court_type'" class="court-types">
            <label v-for="type in courtTypes" :key="type.id" class="check">
              <input v-model="form.court_type_ids" type="checkbox" :value="type.id" />
              {{ type.name }}
            </label>
          </div>
        </div>

        <div class="permission-box">
          <div>
            <strong>Quyền chức năng</strong>
            <p>Chọn menu nào thì nhân viên được xem và thao tác toàn bộ chức năng trong menu đó.</p>
          </div>
          <div class="permission-grid">
            <label
              v-for="permission in permissionCatalog"
              :key="permission.key"
              class="permission-option"
              :class="{ disabled: permissionDisabled(permission) }"
            >
              <input
                v-model="form.menu_keys"
                type="checkbox"
                :value="permission.key"
                :disabled="permissionDisabled(permission)"
              />
              <span>
                <strong>{{ permission.label }}</strong>
                <small>{{ permission.description }}</small>
                <em v-if="permission.requires_all_cluster">Chỉ áp dụng khi phụ trách toàn bộ cụm sân</em>
              </span>
            </label>
          </div>
          <p class="owner-only-note">
            Tài chính, số dư, tài liệu, yêu cầu thay đổi, hợp đồng/chấm dứt, cấu hình giá và khóa lịch chỉ dành cho chủ sân.
          </p>
        </div>

        <footer>
          <button class="btn secondary" type="button" @click="closeModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">{{ saving ? 'Đang lưu...' : 'Lưu' }}</button>
        </footer>
      </form>
    </div>
  </section>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import { ownerStaffService } from '../../services/ownerStaffService.js';

export default {
  name: 'OwnerStaff',
  components: { ActionIconButton, AppIcon, TableActionGroup },
  data() {
    return {
      staff: [],
      courtTypes: [],
      permissionCatalog: [],
      loading: false,
      saving: false,
      error: '',
      success: '',
      showModal: false,
      form: this.emptyForm(),
      showScrollTop: false,
    };
  },
  mounted() {
    window.addEventListener('owner-cluster-changed', this.loadStaff);
    window.addEventListener('scroll', this.handleScroll);
    this.loadStaff();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.loadStaff);
    window.removeEventListener('scroll', this.handleScroll);
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
        menu_keys: this.defaultMenuKeys(),
      };
    },
    async loadStaff() {
      this.loading = true;
      try {
        const response = await ownerStaffService.list();
        this.staff = response.data || [];
        this.courtTypes = response.meta?.court_types || [];
        this.permissionCatalog = response.meta?.menu_permissions || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải danh sách nhân viên sân.';
      } finally {
        this.loading = false;
      }
    },
    openCreate() {
      this.form = this.emptyForm();
      this.showModal = true;
    },
    openEdit(item) {
      const courtTypeIds = (item.assignments || [])
        .filter((assignment) => assignment.scope_type === 'court_type')
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
        menu_keys: [...(item.menu_permissions || [])],
      };
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
    },
    async saveStaff() {
      this.saving = true;
      try {
        const response = this.form.id
          ? await ownerStaffService.update(this.form.id, this.form)
          : await ownerStaffService.create(this.form);
        this.success = response.message;
        this.closeModal();
        await this.loadStaff();
      } catch (error) {
        this.error = error.message || 'Không thể lưu nhân viên sân.';
      } finally {
        this.saving = false;
      }
    },
    async deactivate(item) {
      if (!confirm(`Tạm ngưng nhân viên ${item.username}?`)) return;
      const response = await ownerStaffService.deactivate(item.id, 'Chủ sân tạm ngưng nhân viên.');
      this.success = response.message;
      await this.loadStaff();
    },
    assignmentText(assignments = []) {
      if (assignments.some((assignment) => assignment.scope_type === 'all_cluster' && assignment.status === 'active')) {
        return 'Toàn bộ cụm sân';
      }
      const names = assignments
        .filter((assignment) => assignment.status === 'active')
        .map((assignment) => assignment.court_type_name)
        .filter(Boolean);
      return names.length ? names.join(', ') : 'Chưa có phân công';
    },
    defaultMenuKeys() {
      return this.permissionCatalog
        ?.filter((permission) => permission.default)
        .map((permission) => permission.key) || [];
    },
    permissionDisabled(permission) {
      return permission.requires_all_cluster && this.form.scope_type !== 'all_cluster';
    },
    normalizeMenuPermissions() {
      if (this.form.scope_type === 'all_cluster') return;
      const restrictedKeys = this.permissionCatalog
        .filter((permission) => permission.requires_all_cluster)
        .map((permission) => permission.key);
      this.form.menu_keys = this.form.menu_keys.filter((key) => !restrictedKeys.includes(key));
    },
    menuLabels(keys = []) {
      const labels = new Map(this.permissionCatalog.map((permission) => [permission.key, permission.label]));
      return keys.map((key) => labels.get(key) || key);
    },
    statusLabel(status) {
      return { active: 'Đang hoạt động', locked: 'Đã khóa', deactivated: 'Đã vô hiệu hóa' }[status] || status;
    },
    handleScroll() {
      this.showScrollTop = window.scrollY > 250;
    },
  },
};
</script>

<style scoped>
.page{display:grid;gap:16px}.table-card,.modal{background:#fff;border:1px solid #e2e8f0;border-radius:12px}.table-card{overflow:auto}table{width:100%;border-collapse:collapse;min-width:880px}th,td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}.state{padding:24px;color:#64748b}.btn,.mini-btn{border:0;border-radius:8px;font-weight:800;cursor:pointer}.btn{padding:10px 14px}.mini-btn{padding:7px 10px;margin-right:6px;background:#f1f5f9}.primary{background:#16a34a;color:#fff}.secondary{background:#f1f5f9;color:#0f172a}.danger{background:#fee2e2;color:#b91c1c}.badge{border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;background:#e2e8f0}.badge.active{background:#dcfce7;color:#166534}.badge.locked,.badge.deactivated{background:#fee2e2;color:#b91c1c}.alert{padding:12px;border-radius:10px;font-weight:700}.error{background:#fee2e2;color:#b91c1c}.success{background:#dcfce7;color:#166534}.modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.56);display:grid;place-items:center;z-index:500;padding:20px}.modal{width:min(680px,calc(100vw - 32px));padding:22px;display:grid;gap:16px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}label{display:grid;gap:6px;font-weight:800}input,select{border:1px solid #dbe3ef;border-radius:8px;padding:10px;font:inherit}.scope-box{display:grid;gap:10px;border:1px solid #e2e8f0;border-radius:10px;padding:12px}.scope-box>span{font-weight:900}.check{display:flex;align-items:center;gap:8px}.check input{width:auto}.court-types{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}footer{display:flex;justify-content:flex-end;gap:10px}@media(max-width:720px){.grid,.court-types{grid-template-columns:1fr}}
.modal{width:min(820px,calc(100vw - 32px));max-height:calc(100vh - 40px);overflow:auto}
table{min-width:1080px}
.permission-chips{display:flex;flex-wrap:wrap;gap:6px;max-width:320px}
.permission-chip{padding:4px 8px;border-radius:999px;background:#ecfdf5;color:#166534;font-size:12px;font-weight:800}
.muted{color:#94a3b8;font-size:13px}
.permission-box{display:grid;gap:12px;border:1px solid #bbf7d0;border-radius:12px;padding:14px;background:#f7fff9}
.permission-box p{margin:4px 0 0;color:#64748b;font-size:13px;font-weight:600}
.permission-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.permission-option{display:flex;align-items:flex-start;gap:10px;border:1px solid #dbe8df;border-radius:10px;padding:12px;background:#fff;cursor:pointer}
.permission-option input{width:auto;margin-top:3px}
.permission-option span{display:grid;gap:4px}
.permission-option small{color:#64748b;font-weight:600;line-height:1.4}
.permission-option em{color:#b45309;font-size:12px;font-style:normal}
.permission-option.disabled{opacity:.55;cursor:not-allowed;background:#f8fafc}
.owner-only-note{padding:10px 12px;border-radius:8px;background:#fff7ed;color:#9a3412!important}
@media(max-width:720px){.permission-grid{grid-template-columns:1fr}}
</style>

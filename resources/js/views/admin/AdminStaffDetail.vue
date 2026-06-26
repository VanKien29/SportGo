<template>
  <section class="admin-staff-detail">
    <div class="header-section">
      <div class="breadcrumb-nav">
        <BackButton :to="{ name: 'admin-staffs' }" />
      </div>
      <div class="title-row mt-3">
        <div>
          <h2>Chi tiết Nhân sự</h2>
          <p class="muted">Thông tin cá nhân chi tiết và lịch sử tác động hệ thống.</p>
        </div>
        <div class="actions" v-if="detailData.user && detailData.user.id">
          <button 
            v-if="detailData.user.status === 'locked'" 
            class="btn-action unlock" 
            :disabled="!canManageUser(detailData.user) || detailData.user.id === currentUserId"
            @click="unlockUser"
          >
            Mở khóa tài khoản
          </button>
          <button 
            v-else 
            class="btn danger" 
            :disabled="!canManageUser(detailData.user) || detailData.user.id === currentUserId"
            @click="openLockModal"
          >
            Khóa tài khoản
          </button>
        </div>
      </div>
    </div>

    <div v-if="detailLoading" class="alert info">Đang tải dữ liệu...</div>
    <div v-else-if="detailError" class="alert error">{{ detailError }}</div>
    <div v-else-if="successMsg" class="alert success">{{ successMsg }}</div>

    <div v-if="!detailLoading && !detailError && detailData.user" class="detail-container">
      <div class="detail-grid-layout">
        <!-- Panel Thông tin cá nhân -->
        <div class="panel-card personal-info">
          <div class="card-header">
            <h3>Thông tin cơ bản</h3>
          </div>
          <div class="card-body">
            <div class="user-profile-header">
              <div class="avatar-large">{{ getAvatar(detailData.user) }}</div>
              <div class="user-titles">
                <h4>{{ detailData.user.full_name }}</h4>
                <p class="username">@{{ detailData.user.username }}</p>
                <div class="roles-tags mt-2">
                  <span v-for="role in detailData.user.roles" :key="role" class="role-tag" :class="role">
                    {{ getRoleDisplayName(role) }}
                  </span>
                </div>
              </div>
            </div>

            <hr />

            <div class="info-list">
              <div class="info-row">
                <span class="label">Email liên hệ:</span>
                <span class="value">{{ detailData.user.email || '-' }}</span>
              </div>
              <div class="info-row">
                <span class="label">Số điện thoại:</span>
                <span class="value">{{ detailData.user.phone || '-' }}</span>
              </div>
              <div class="info-row">
                <span class="label">Trạng thái:</span>
                <span class="status-badge" :class="detailData.user.status">
                  {{ detailData.user.status === 'locked' ? 'Bị khóa' : 'Hoạt động' }}
                </span>
              </div>
            </div>

            <div v-if="detailData.user.status === 'locked'" class="lock-info-box mt-4">
              <h5>Thông tin khóa tài khoản</h5>
              <div class="info-row">
                <span class="label">Loại khóa:</span>
                <span class="value">{{ detailData.user.lock_type }}</span>
              </div>
              <div class="info-row">
                <span class="label">Lý do:</span>
                <span class="value">{{ detailData.user.status_reason }}</span>
              </div>
              <div v-if="detailData.user.locked_until" class="info-row">
                <span class="label">Khóa đến:</span>
                <span class="value">{{ formatDate(detailData.user.locked_until) }}</span>
              </div>
              <div v-if="detailData.user.locked_by_name" class="info-row">
                <span class="label">Người khóa:</span>
                <span class="value">{{ detailData.user.locked_by_name }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Panel Audit Logs -->
        <div class="panel-card audit-logs">
          <div class="card-header">
            <h3>Lịch sử hoạt động (Audit Logs)</h3>
            <p class="muted">Các thay đổi và tác động liên quan đến tài khoản này.</p>
          </div>
          <div class="card-body">
            <div class="table-wrap">
              <table class="logs-table">
                <thead>
                  <tr>
                    <th style="width: 160px;">Thời gian</th>
                    <th style="width: 180px;">Người thực hiện</th>
                    <th style="width: 140px;">Hành động</th>
                    <th>Chi tiết thay đổi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="detailData.audit_logs.length === 0">
                    <td colspan="4" class="empty">Không tìm thấy nhật ký hoạt động nào.</td>
                  </tr>
                  <tr v-for="log in detailData.audit_logs" :key="log.id">
                    <td class="log-date">{{ formatDate(log.created_at) }}</td>
                    <td>
                      <strong class="actor-name-text">{{ log.actor_name }}</strong>
                    </td>
                    <td>
                      <span class="log-action-badge" :class="log.action">
                        {{ translateAction(log.action) }}
                      </span>
                    </td>
                    <td class="log-diff-cell">
                      <div v-if="log.old_values || log.new_values" class="diff-content">
                        <div v-if="log.action === 'user.locked'">
                          <strong>Lý do:</strong> {{ log.new_values?.status_reason || 'Không ghi rõ' }}
                          <div v-if="log.new_values?.locked_until" class="muted mt-1">
                            Hạn khóa: {{ formatDate(log.new_values.locked_until) }}
                          </div>
                        </div>
                        <div v-else-if="log.action === 'user.created'">
                          Tạo mới nhân sự với vai trò: 
                          <span class="highlight-val">{{ (log.new_values?.roles || []).join(', ') }}</span>
                        </div>
                        <div v-else-if="log.action === 'user.updated'">
                          <div v-if="hasChanges(log.old_values, log.new_values)">
                            <div v-for="field in getChangedFields(log.old_values, log.new_values)" :key="field" class="diff-line">
                              <span class="field-name">{{ getFieldLabel(field) }}:</span> 
                              <span class="old-val">{{ formatVal(log.old_values[field]) }}</span> 
                              <span class="arrow">→</span> 
                              <span class="new-val">{{ formatVal(log.new_values[field]) }}</span>
                            </div>
                          </div>
                          <div v-else class="muted">Không có thông tin thay đổi chi tiết</div>
                        </div>
                        <div v-else-if="log.action === 'user.unlocked'">
                          Mở khóa tài khoản.
                        </div>
                        <div v-else>-</div>
                      </div>
                      <div v-else>-</div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL KHÓA TÀI KHOẢN -->
    <div v-if="showLockModal" class="modal-backdrop" @click.self="closeLockModal">
      <form class="modal" @submit.prevent="submitLock">
        <div class="modal-header">
          <div>
            <h3>Khóa tài khoản</h3>
            <p class="muted">Chặn quyền đăng nhập và thu hồi token hiện tại.</p>
          </div>
          <button type="button" class="icon-btn" @click="closeLockModal">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>

        <div class="field">
          <span class="field-label">Loại khóa</span>
          <div class="segmented">
            <button
              v-for="type in lockTypes"
              :key="type.value"
              type="button"
              :class="{ active: lockForm.lock_type === type.value }"
              @click="lockForm.lock_type = type.value"
            >
              {{ type.label }}
            </button>
          </div>
        </div>

        <label>
          Lý do khóa
          <textarea v-model="lockForm.status_reason" rows="4" required placeholder="Nhập lý do khóa chi tiết..."></textarea>
        </label>

        <div v-if="lockForm.lock_type === 'temporary'" class="field">
          <span class="field-label">Thời hạn khóa</span>
          <div class="duration-grid">
            <button
              v-for="duration in lockDurations"
              :key="duration.value"
              type="button"
              :class="{ active: lockForm.lock_duration === duration.value }"
              @click="lockForm.lock_duration = duration.value"
            >
              {{ duration.label }}
            </button>
          </div>
          <div v-if="lockForm.lock_duration === 'custom'" class="custom-duration">
            <label>
              Số lượng
              <input
                v-model.number="lockForm.custom_amount"
                type="number"
                min="1"
                max="365"
                required
                placeholder="VD: 3"
              />
            </label>
            <label>
              Đơn vị
              <select v-model="lockForm.custom_unit">
                <option value="hours">Giờ</option>
                <option value="days">Ngày</option>
              </select>
            </label>
          </div>
          <p class="hint">{{ lockUntilPreview }}</p>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn secondary" @click="closeLockModal">Hủy</button>
          <button type="submit" class="btn danger" :disabled="saving">Xác nhận khóa</button>
        </div>
      </form>
    </div>
  </section>
</template>

<script>
import { adminUserService } from '../../services/adminUserService.js';
import { adminRoleService } from '../../services/adminRoles.js';
import { getAuth } from '../../stores/auth.js';
import BackButton from '../../components/BackButton.vue';

export default {
  name: 'AdminStaffDetail',
  components: { BackButton },
  data() {
    return {
      userId: this.$route.params.id,
      detailLoading: true,
      detailError: '',
      successMsg: '',
      allRoles: [],
      
      detailData: {
        user: null,
        audit_logs: [],
      },

      currentUserId: null,
      currentUserRoles: [],
      isSuperAdmin: false,

      // Lock feature
      showLockModal: false,
      saving: false,
      lockForm: {
        lock_type: 'temporary',
        lock_duration: '1_day',
        status_reason: '',
        custom_amount: 1,
        custom_unit: 'days',
      },
      lockTypes: [
        { value: 'temporary', label: 'Tạm thời' },
        { value: 'permanent', label: 'Vĩnh viễn' },
      ],
      lockDurations: [
        { value: '1_hour', label: '1 giờ', minutes: 60 },
        { value: '1_day', label: '24 giờ', minutes: 1440 },
        { value: '7_days', label: '7 ngày', minutes: 10080 },
        { value: '30_days', label: '30 ngày', minutes: 43200 },
        { value: 'custom', label: 'Tùy chỉnh', minutes: null },
      ],
    };
  },
  computed: {
    lockUntilPreview() {
      if (this.lockForm.lock_duration === 'custom') {
        const amount = Number(this.lockForm.custom_amount || 0);
        const unitLabel = this.lockForm.custom_unit === 'hours' ? 'giờ' : 'ngày';

        if (amount < 1) {
          return 'Nhập số lượng lớn hơn 0 để tính thời hạn khóa.';
        }

        return `Khóa trong ${amount} ${unitLabel}, đến ngày: ${this.formatDate(this.resolveLockedUntil())}`;
      }

      return `Khóa đến ngày: ${this.formatDate(this.resolveLockedUntil())}`;
    },
  },
  mounted() {
    this.initAuth();
    this.loadRoles();
    this.loadUserDetail();
  },
  methods: {
    initAuth() {
      const auth = getAuth();
      this.currentUserId = auth?.user?.id || auth?.id || null;
      this.currentUserRoles = auth?.roles || [];
      this.isSuperAdmin = this.currentUserRoles.includes('super_admin');
    },
    async loadRoles() {
      try {
        const response = await adminRoleService.list();
        this.allRoles = response.data || [];
      } catch (err) {
        console.error('Không tải được danh sách vai trò:', err);
      }
    },
    async loadUserDetail() {
      this.detailLoading = true;
      this.detailError = '';
      
      try {
        const response = await adminUserService.get(this.userId);
        const data = response.data || {};
        
        let user = data.user || data.profile || {};
        
        this.detailData = {
          user: user,
          audit_logs: data.audit_logs || [],
        };
      } catch (err) {
        this.detailError = err.message || 'Không tải được thông tin nhân sự.';
      } finally {
        this.detailLoading = false;
      }
    },
    getAvatar(user) {
      if (!user) return '';
      if (user.avatar_url) return `<img src="${user.avatar_url}" alt="Avatar" />`;
      if (user.full_name) {
        const parts = user.full_name.trim().split(' ');
        const last = parts[parts.length - 1];
        return last ? last.charAt(0).toUpperCase() : '?';
      }
      if (user.username) return user.username.charAt(0).toUpperCase();
      return '?';
    },
    getRoleDisplayName(roleName) {
      const role = this.allRoles.find(r => r.name === roleName);
      if (role) return role.display_name;
      
      const labels = {
        super_admin: 'Super Admin',
        admin: 'Quản trị viên (Admin)',
        system_staff: 'Nhân viên hệ thống',
        venue_owner: 'Chủ sân (Owner)',
        venue_staff: 'Nhân viên sân',
        user: 'Khách hàng',
      };
      return labels[roleName] || roleName;
    },
    canManageUser(user) {
      const targetRoles = user.roles || [];
      const hasAdminRole = targetRoles.includes('super_admin') || targetRoles.includes('admin');
      if (hasAdminRole) return this.isSuperAdmin;
      return true;
    },
    
    // Formatting methods
    formatDate(dateString) {
      if (!dateString) return '';
      const d = new Date(dateString);
      return d.toLocaleString('vi-VN');
    },
    translateAction(action) {
      const mapping = {
        'user.created': 'Tạo mới',
        'user.updated': 'Cập nhật',
        'user.locked': 'Khóa',
        'user.unlocked': 'Mở khóa',
      };
      return mapping[action] || action;
    },
    hasChanges(oldVal, newVal) {
      if (!oldVal || !newVal) return false;
      return Object.keys(newVal).some(key => {
        if (key === 'roles' || key === 'role_ids' || key === 'password') return false;
        return JSON.stringify(oldVal[key]) !== JSON.stringify(newVal[key]);
      });
    },
    getChangedFields(oldVal, newVal) {
      if (!oldVal || !newVal) return [];
      return Object.keys(newVal).filter(key => {
        if (key === 'roles' || key === 'role_ids' || key === 'password') return false;
        return JSON.stringify(oldVal[key]) !== JSON.stringify(newVal[key]);
      });
    },
    getFieldLabel(field) {
      const labels = {
        full_name: 'Họ tên',
        email: 'Email',
        phone: 'Số điện thoại',
        status: 'Trạng thái',
        lock_type: 'Kiểu khóa',
        status_reason: 'Lý do',
        locked_until: 'Thời hạn',
      };
      return labels[field] || field;
    },
    formatVal(val) {
      if (val === null || val === '') return '(trống)';
      if (typeof val === 'string' && val.match(/^\d{4}-\d{2}-\d{2}/)) {
        return this.formatDate(val);
      }
      return val;
    },

    // Lock Feature
    openLockModal() {
      this.lockForm = {
        lock_type: 'temporary',
        lock_duration: '1_day',
        status_reason: '',
        custom_amount: 1,
        custom_unit: 'days',
      };
      this.showLockModal = true;
      this.successMsg = '';
    },
    closeLockModal() {
      this.showLockModal = false;
    },
    resolveLockedUntil() {
      const now = new Date();
      if (this.lockForm.lock_duration === 'custom') {
        const amount = Number(this.lockForm.custom_amount || 0);
        if (this.lockForm.custom_unit === 'hours') {
          now.setHours(now.getHours() + amount);
        } else {
          now.setDate(now.getDate() + amount);
        }
        return now.toISOString();
      }

      const match = this.lockDurations.find(d => d.value === this.lockForm.lock_duration);
      if (match && match.minutes) {
        now.setMinutes(now.getMinutes() + match.minutes);
      }
      return now.toISOString();
    },
    async submitLock() {
      this.saving = true;
      this.detailError = '';
      this.successMsg = '';
      
      try {
        const payload = {
          lock_type: this.lockForm.lock_type,
          status_reason: this.lockForm.status_reason,
          locked_until: this.lockForm.lock_type === 'temporary' ? this.resolveLockedUntil() : null,
        };
        const response = await adminUserService.lock(this.userId, payload);
        this.successMsg = response.message || 'Khóa tài khoản thành công.';
        this.closeLockModal();
        await this.loadUserDetail();
      } catch (err) {
        this.detailError = err.message || 'Khóa tài khoản không thành công.';
        this.closeLockModal();
      } finally {
        this.saving = false;
      }
    },
    async unlockUser() {
      if (!confirm(`Bạn có chắc chắn muốn mở khóa tài khoản nhân sự này?`)) return;
      this.detailError = '';
      this.successMsg = '';
      
      try {
        const response = await adminUserService.unlock(this.userId, { reason: 'Mở khóa nhân sự theo yêu cầu.' });
        this.successMsg = response.message || 'Mở khóa tài khoản thành công.';
        await this.loadUserDetail();
      } catch (err) {
        this.detailError = err.message || 'Mở khóa không thành công.';
      }
    }
  }
};
</script>

<style scoped>
.admin-staff-detail {
  padding: 24px;
}
.header-section {
  margin-bottom: 24px;
}
.back-link {
  color: var(--primary-color, #3b82f6);
  text-decoration: none;
  font-weight: 500;
}
.back-link:hover {
  text-decoration: underline;
}
.title-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }
.mb-1 { margin-bottom: 4px; }
.mt-2 { margin-top: 8px; }

.detail-grid-layout {
  display: grid;
  grid-template-columns: 350px 1fr;
  gap: 24px;
  align-items: start;
}

.panel-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  overflow: hidden;
}

.card-header {
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}
.card-header h3 {
  margin: 0;
  font-size: 1.1rem;
  color: #111827;
}

.card-body {
  padding: 20px;
}

.user-profile-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.avatar-large {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #eef2ff;
  color: #4f46e5;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: 600;
  margin-bottom: 16px;
}

.user-titles h4 {
  margin: 0 0 4px;
  font-size: 1.25rem;
}
.user-titles .username {
  margin: 0;
  color: #6b7280;
}

.info-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.info-row .label {
  color: #6b7280;
  font-weight: 500;
}
.info-row .value {
  color: #111827;
  font-weight: 500;
}
.status-badge {
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}
.status-badge.active { background: #dcfce7; color: #166534; }
.status-badge.locked { background: #fee2e2; color: #991b1b; }

.lock-info-box {
  background: #fef2f2;
  border: 1px solid #fecaca;
  padding: 16px;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.lock-info-box h5 {
  margin: 0 0 8px;
  color: #991b1b;
}

.logs-table {
  width: 100%;
  border-collapse: collapse;
}
.logs-table th {
  text-align: left;
  padding: 12px;
  background: #f9fafb;
  color: #374151;
  font-weight: 600;
  border-bottom: 1px solid #e5e7eb;
}
.logs-table td {
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
  vertical-align: top;
}
.logs-table tr:last-child td {
  border-bottom: none;
}
.log-date {
  color: #6b7280;
  font-size: 0.875rem;
}
.actor-name-text {
  display: block;
}
.ip-text {
  font-size: 0.75rem;
  margin-top: 4px;
}
.log-action-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  background: #f3f4f6;
  color: #374151;
}
.log-action-badge.user\.created { background: #dcfce7; color: #166534; }
.log-action-badge.user\.updated { background: #e0f2fe; color: #075985; }
.log-action-badge.user\.locked { background: #fee2e2; color: #991b1b; }
.log-action-badge.user\.unlocked { background: #fef3c7; color: #92400e; }

.diff-content {
  font-size: 0.875rem;
}
.diff-line {
  margin-bottom: 4px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.diff-line .field-name { font-weight: 600; }
.diff-line .old-val { text-decoration: line-through; color: #9ca3af; }
.diff-line .arrow { color: #6b7280; }
.diff-line .new-val { color: #059669; font-weight: 500; }

.highlight-val {
  font-weight: 600;
  color: #4f46e5;
}

hr {
  border: 0;
  border-top: 1px solid #e5e7eb;
  margin: 20px 0;
}

/* Modal Lock Form styles inherited from global */
.field-label { display: block; font-weight: 600; margin-bottom: 8px; }
.segmented { display: flex; border-radius: 6px; overflow: hidden; border: 1px solid #d1d5db; margin-bottom: 16px; }
.segmented button { flex: 1; padding: 8px 0; background: #f9fafb; border: none; cursor: pointer; border-right: 1px solid #d1d5db; }
.segmented button:last-child { border-right: none; }
.segmented button.active { background: #4f46e5; color: white; font-weight: 600; }
.duration-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px; }
.duration-grid button { padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; background: white; cursor: pointer; }
.duration-grid button.active { border-color: #4f46e5; background: #eef2ff; color: #4f46e5; font-weight: 600; }
.custom-duration { display: flex; gap: 12px; margin-bottom: 12px; }
.custom-duration label { flex: 1; }
.hint { font-size: 0.875rem; color: #6b7280; }
</style>

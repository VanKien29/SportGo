<template>
  <section class="admin-users">
    <div class="header-section">
      <div>
        <h2>Quản lý Tài khoản</h2>
        <p class="muted">Quản lý tài khoản người dùng, chủ sân, và nhật ký hoạt động.</p>
      </div>
      <button class="btn success" @click="openCreateModal" style="display: none;">
        + Thêm người dùng
      </button>
    </div>

    <!-- Bộ lọc & Tìm kiếm -->
    <div class="filters-panel">
      <div class="search-box">
        <input
          v-model="filters.keyword"
          type="text"
          placeholder="Tìm theo họ tên, username, email, sđt..."
          @input="debounceSearch"
        />
      </div>
      <div class="filter-selects">
        <select v-model="filters.status" @change="loadUsers">
          <option value="">Tất cả trạng thái</option>
          <option value="active">Hoạt động</option>
          <option value="locked">Đang bị khóa</option>
        </select>
        <select v-model="filters.role" @change="loadUsers">
          <option value="">Tất cả vai trò</option>
          <option v-for="role in allRoles" :key="role.id" :value="role.name">
            {{ role.display_name }}
          </option>
        </select>
        <button class="btn secondary" @click="resetFilters">Đặt lại</button>
      </div>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="success" class="alert success">{{ success }}</div>

    <!-- Danh sách tài khoản nhân sự -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Họ tên</th>
            <th>Username</th>
            <th>Email / SĐT</th>
            <th>Vai trò</th>
            <th>Trạng thái</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="empty">Đang tải danh sách...</td>
          </tr>
          <tr v-else-if="filteredUsers.length === 0">
            <td colspan="6" class="empty">Không tìm thấy tài khoản phù hợp.</td>
          </tr>
          <tr v-for="user in filteredUsers" :key="user.id">
            <td>
              <div class="user-info-cell">
                <span class="user-name">{{ user.full_name }}</span>
                <span v-if="user.id === currentUserId" class="badge self">Bạn</span>
              </div>
            </td>
            <td><code>{{ user.username }}</code></td>
            <td>
              <div>{{ user.email || '-' }}</div>
              <div class="muted phone-muted">{{ user.phone || '-' }}</div>
            </td>
            <td>
              <div class="roles-tags">
                <span v-for="role in user.roles" :key="role" class="role-tag" :class="role">
                  {{ getRoleDisplayName(role) }}
                </span>
              </div>
            </td>
            <td>
              <div class="status-cell">
                <span class="status" :class="user.status">
                  {{ user.status === 'locked' ? 'Bị khóa' : 'Hoạt động' }}
                </span>
                <span v-if="user.locked_until" class="lock-until-text">
                  (Đến: {{ formatDate(user.locked_until) }})
                </span>
              </div>
            </td>
            <td>
              <div class="actions-cell">
                <button class="btn-action view" title="Xem chi tiết & Audit Logs" @click="openDetailModal(user.id)">
                  Chi tiết
                </button>
                <button 
                  class="btn-action edit" 
                  title="Chỉnh sửa thông tin và vai trò" 
                  :disabled="!canManageUser(user)"
                  @click="openEditModal(user)"
                >
                  Sửa
                </button>
                <button 
                  v-if="user.status === 'locked'" 
                  class="btn-action unlock" 
                  :disabled="!canManageUser(user) || user.id === currentUserId"
                  @click="unlockUser(user)"
                >
                  Mở khóa
                </button>
                <button 
                  v-else 
                  class="btn-action lock" 
                  :disabled="!canManageUser(user) || user.id === currentUserId"
                  @click="openLockModal(user)"
                >
                  Khóa
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL THÊM MỚI / CHỈNH SỬA TÀI KHOẢN -->
    <div v-if="showFormModal" class="modal-backdrop" @click.self="closeFormModal">
      <form class="modal" @submit.prevent="submitForm">
        <div class="modal-header">
          <div>
            <h3>{{ isEditMode ? 'Chỉnh sửa tài khoản' : 'Thêm nhân sự mới' }}</h3>
            <p class="muted">{{ isEditMode ? 'Cập nhật thông tin tài khoản và gán vai trò.' : 'Tạo mới tài khoản và phân quyền vai trò.' }}</p>
          </div>
          <button type="button" class="icon-btn" @click="closeFormModal">×</button>
        </div>

        <div class="modal-body scrollable">
          <div class="form-grid">
            <label>
              Họ tên <span class="required">*</span>
              <input v-model="form.full_name" type="text" required placeholder="Nhập họ tên nhân sự" />
            </label>

            <label>
              Tên đăng nhập (Username) <span class="required">*</span>
              <input v-model="form.username" type="text" required :disabled="isEditMode" placeholder="Tên đăng nhập viết liền không dấu" />
            </label>

            <label>
              Email <span class="required">*</span>
              <input v-model="form.email" type="email" required placeholder="Nhập địa chỉ email" />
            </label>

            <label>
              Số điện thoại
              <input v-model="form.phone" type="text" placeholder="Nhập số điện thoại" />
            </label>

            <label class="full-width">
              Mật khẩu {{ isEditMode ? '(Bỏ trống nếu không muốn đổi)' : '*' }}
              <div class="password-input-wrapper">
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  :required="!isEditMode"
                  placeholder="Tối thiểu 6 ký tự"
                />
                <button
                  type="button"
                  class="toggle-password-btn"
                  @click="showPassword = !showPassword"
                  title="Ẩn/Hiện mật khẩu"
                >
                  <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
              </div>
            </label>

            <div class="full-width field">
              <span class="field-label">Gán vai trò / Nhóm quyền <span class="required">*</span></span>
              <p class="hint mb-2">Chỉ Super Admin mới có quyền gán hoặc quản lý vai trò Admin / Super Admin.</p>
              <div class="roles-grid">
                <label v-for="role in availableRolesForForm" :key="role.id" class="checkbox-label">
                  <input
                    type="checkbox"
                    :value="role.id"
                    v-model="form.roles"
                  />
                  <div>
                    <strong>{{ role.display_name }}</strong>
                    <span class="role-desc">{{ role.description || 'Không có mô tả chi tiết.' }}</span>
                  </div>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn secondary" @click="closeFormModal">Hủy</button>
          <button type="submit" class="btn success" :disabled="saving">
            {{ isEditMode ? 'Cập nhật' : 'Tạo tài khoản' }}
          </button>
        </div>
      </form>
    </div>

    <!-- MODAL XEM CHI TIẾT & AUDIT LOGS -->
    <div v-if="showDetailModal" class="modal-backdrop" @click.self="closeDetailModal">
      <div class="modal wide">
        <div class="modal-header">
          <div>
            <h3>Chi tiết nhân sự & Lịch sử audit</h3>
            <p class="muted">Thông tin cá nhân chi tiết và lịch sử tác động hệ thống.</p>
          </div>
          <button type="button" class="icon-btn" @click="closeDetailModal">×</button>
        </div>

        <div v-if="detailLoading" class="detail-loading">Đang tải dữ liệu...</div>
        <div v-else-if="detailError" class="alert error">{{ detailError }}</div>
        <div v-else class="modal-body scrollable">
          <div class="detail-grid">
            <!-- Thẻ thông tin cá nhân -->
            <div class="detail-info-card">
              <div class="detail-avatar">{{ detailData.user.full_name?.charAt(0).toUpperCase() }}</div>
              <h4>{{ detailData.user.full_name }}</h4>
              <p class="detail-username">@{{ detailData.user.username }}</p>
              
              <div class="detail-meta-list">
                <div class="detail-meta-item">
                  <span class="label">Email:</span>
                  <span class="value">{{ detailData.user.email || '-' }}</span>
                </div>
                <div class="detail-meta-item">
                  <span class="label">SĐT:</span>
                  <span class="value">{{ detailData.user.phone || '-' }}</span>
                </div>
                <div class="detail-meta-item">
                  <span class="label">Trạng thái:</span>
                  <span class="status" :class="detailData.user.status">
                    {{ detailData.user.status === 'locked' ? 'Bị khóa' : 'Hoạt động' }}
                  </span>
                </div>
                <div v-if="detailData.user.status === 'locked'" class="detail-meta-item nested">
                  <div class="lock-detail-item"><strong>Loại khóa:</strong> {{ detailData.user.lock_type }}</div>
                  <div class="lock-detail-item"><strong>Lý do:</strong> {{ detailData.user.status_reason }}</div>
                  <div v-if="detailData.user.locked_until" class="lock-detail-item">
                    <strong>Đến ngày:</strong> {{ formatDate(detailData.user.locked_until) }}
                  </div>
                </div>
                <div class="detail-meta-item">
                  <span class="label d-block mb-1">Vai trò hệ thống:</span>
                  <div class="roles-tags">
                    <span v-for="role in detailData.user.roles" :key="role" class="role-tag" :class="role">
                      {{ getRoleDisplayName(role) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Panel Audit Logs -->
            <div class="detail-logs-panel">
              <h5>Lịch sử hoạt động liên quan</h5>
              <div class="logs-table-wrap">
                <table class="logs-table">
                  <thead>
                    <tr>
                      <th style="width: 140px;">Thời gian</th>
                      <th style="width: 150px;">Người thực hiện</th>
                      <th style="width: 130px;">Hành động</th>
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
                        <div class="muted ip-text">IP: {{ log.ip_address || 'N/A' }}</div>
                      </td>
                      <td>
                        <span class="log-action-badge" :class="log.action">
                          {{ translateAction(log.action) }}
                        </span>
                      </td>
                      <td class="log-diff-cell">
                        <div v-if="log.old_values || log.new_values" class="diff-content">
                          <div v-if="log.action === 'user.locked'">
                            <strong>Lý do khóa:</strong> {{ log.new_values?.status_reason || 'Không ghi rõ' }}
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
                            <div v-else class="muted">Không có thông tin chi tiết</div>
                          </div>
                          <div v-else-if="log.action === 'user.unlocked'">
                            Mở khóa tài khoản hoạt động bình thường.
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

        <div class="modal-actions">
          <button type="button" class="btn secondary" @click="closeDetailModal">Đóng</button>
        </div>
      </div>
    </div>

    <!-- MODAL KHÓA TÀI KHOẢN -->
    <div v-if="lockTarget" class="modal-backdrop" @click.self="closeLockModal">
      <form class="modal" @submit.prevent="lockUser">
        <div class="modal-header">
          <div>
            <h3>Khóa tài khoản</h3>
            <p class="muted">Chặn quyền đăng nhập và thu hồi token hiện tại.</p>
          </div>
          <button type="button" class="icon-btn" @click="closeLockModal">×</button>
        </div>

        <div class="target-user">
          <div class="target-avatar">{{ lockTarget.full_name?.charAt(0)?.toUpperCase() || '?' }}</div>
          <div>
            <strong>{{ lockTarget.full_name }}</strong>
            <span>{{ lockTarget.username }} · {{ lockTarget.email || 'Chưa có email' }}</span>
          </div>
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

export default {
  name: 'AdminUsers',
  data() {
    return {
      users: [],
      allRoles: [],
      loading: false,
      saving: false,
      error: '',
      success: '',
      
      // Bộ lọc
      filters: {
        keyword: '',
        status: '',
        role: '',
        role_group: 'user',
      },
      searchTimeout: null,

      // Tạo/Sửa nhân sự
      showFormModal: false,
      isEditMode: false,
      editId: null,
      form: {
        full_name: '',
        username: '',
        email: '',
        phone: '',
        password: '',
        roles: [],
      },

      // Xem chi tiết & Logs
      showDetailModal: false,
      detailLoading: false,
      detailError: '',
      detailData: {
        user: {},
        audit_logs: [],
      },

      // Khóa tài khoản
      lockTarget: null,
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
        { value: 'auto', label: 'Tự động' },
      ],
      lockDurations: [
        { value: '1_hour', label: '1 giờ', minutes: 60 },
        { value: '1_day', label: '24 giờ', minutes: 1440 },
        { value: '7_days', label: '7 ngày', minutes: 10080 },
        { value: '30_days', label: '30 ngày', minutes: 43200 },
        { value: 'custom', label: 'Tùy chỉnh', minutes: null },
      ],

      // Phân quyền hiện tại
      currentUserId: null,
      currentUserRoles: [],
      isSuperAdmin: false,
      showPassword: false,
    };
  },
  computed: {
    filteredUsers() {
      // Vì API đã được lọc sơ bộ, ta lọc thêm động ở FE để mượt mà hơn
      let list = this.users;

      if (this.filters.keyword) {
        const kw = this.filters.keyword.toLowerCase();
        list = list.filter(user => 
          (user.full_name || '').toLowerCase().includes(kw) ||
          (user.username || '').toLowerCase().includes(kw) ||
          (user.email || '').toLowerCase().includes(kw) ||
          (user.phone || '').toLowerCase().includes(kw)
        );
      }

      if (this.filters.status) {
        list = list.filter(user => user.status === this.filters.status);
      }

      if (this.filters.role) {
        list = list.filter(user => (user.roles || []).includes(this.filters.role));
      }

      return list;
    },
    availableRolesForForm() {
      if (this.isSuperAdmin) {
        return this.allRoles;
      }
      // Ẩn vai trò Admin hoặc Super Admin nếu người tạo không phải Super Admin
      return this.allRoles.filter(role => role.name !== 'super_admin' && role.name !== 'admin');
    },
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
    this.loadUsers();
    this.loadRoles();
  },
  methods: {
    initAuth() {
      const auth = getAuth();
      this.currentUserId = auth?.user?.id || auth?.id || null;
      this.currentUserRoles = auth?.roles || [];
      this.isSuperAdmin = this.currentUserRoles.includes('super_admin');
    },
    async loadUsers() {
      this.loading = true;
      this.error = '';
      try {
        const response = await adminUserService.list(this.filters);
        this.users = response.data || [];
      } catch (err) {
        this.error = err.message || 'Không tải được danh sách tài khoản.';
      } finally {
        this.loading = false;
      }
    },
    async loadRoles() {
      try {
        const response = await adminRoleService.list();
        this.allRoles = response.data || [];
      } catch (err) {
        console.error('Không tải được danh sách vai trò:', err);
      }
    },
    debounceSearch() {
      // Tìm kiếm động tức thì thông qua computed filteredUsers
    },
    resetFilters() {
      this.filters.keyword = '';
      this.filters.status = '';
      this.filters.role = '';
      this.loadUsers();
    },
    canManageUser(user) {
      const targetRoles = user.roles || [];
      const hasAdminRole = targetRoles.includes('super_admin') || targetRoles.includes('admin');
      
      // Nếu là tài khoản Admin/Super Admin, chỉ Super Admin mới được can thiệp
      if (hasAdminRole) {
        return this.isSuperAdmin;
      }
      return true;
    },
    getRoleDisplayName(roleName) {
      const role = this.allRoles.find(r => r.name === roleName);
      if (role) return role.display_name;
      
      // Fallback dịch một số vai trò mặc định
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
    
    // CRUD Modals
    openCreateModal() {
      this.isEditMode = false;
      this.editId = null;
      this.form = {
        full_name: '',
        username: '',
        email: '',
        phone: '',
        password: '',
        roles: [],
      };
      this.error = '';
      this.success = '';
      this.showPassword = false;
      this.showFormModal = true;
    },
    openEditModal(user) {
      this.isEditMode = true;
      this.editId = user.id;
      this.form = {
        full_name: user.full_name,
        username: user.username,
        email: user.email,
        phone: user.phone || '',
        password: '',
        // Map tên vai trò sang ID vai trò tương ứng để lưu check
        roles: (user.roles || []).map(rName => {
          const matched = this.allRoles.find(r => r.name === rName);
          return matched ? matched.id : null;
        }).filter(id => id !== null),
      };
      this.error = '';
      this.success = '';
      this.showPassword = false;
      this.showFormModal = true;
    },
    closeFormModal() {
      this.showPassword = false;
      this.showFormModal = false;
    },
    async submitForm() {
      this.saving = true;
      this.error = '';
      this.success = '';
      try {
        if (this.form.roles.length === 0) {
          throw new Error('Vui lòng chọn ít nhất một vai trò.');
        }

        const payload = { ...this.form };
        if (this.isEditMode) {
          if (!payload.password) {
            delete payload.password; // Bỏ qua mật khẩu nếu bỏ trống khi edit
          }
          const response = await adminUserService.update(this.editId, payload);
          this.success = response.message || 'Cập nhật nhân sự thành công.';
        } else {
          const response = await adminUserService.create(payload);
          this.success = response.message || 'Tạo tài khoản nhân sự thành công.';
        }
        this.closeFormModal();
        await this.loadUsers();
      } catch (err) {
        this.error = err.message || 'Thao tác không thành công.';
      } finally {
        this.saving = false;
      }
    },

    // Xem chi tiết & Audit Logs
    async openDetailModal(userId) {
      this.showDetailModal = true;
      this.detailLoading = true;
      this.detailError = '';
      this.detailData = { user: {}, audit_logs: [] };

      try {
        const response = await adminUserService.get(userId);
        this.detailData = response.data || { user: {}, audit_logs: [] };
      } catch (err) {
        this.detailError = err.message || 'Không tải được chi tiết tài khoản.';
      } finally {
        this.detailLoading = false;
      }
    },
    closeDetailModal() {
      this.showDetailModal = false;
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

    // Khóa/Mở khóa tài khoản
    openLockModal(user) {
      this.lockTarget = user;
      this.lockForm = {
        lock_type: 'temporary',
        lock_duration: '1_day',
        status_reason: '',
        custom_amount: 1,
        custom_unit: 'days',
      };
      this.error = '';
      this.success = '';
    },
    closeLockModal() {
      this.lockTarget = null;
    },
    async lockUser() {
      if (!this.lockTarget) return;
      this.saving = true;
      this.error = '';
      this.success = '';
      try {
        const payload = {
          lock_type: this.lockForm.lock_type,
          status_reason: this.lockForm.status_reason,
          locked_until: this.lockForm.lock_type === 'temporary' ? this.resolveLockedUntil() : null,
        };
        const response = await adminUserService.lock(this.lockTarget.id, payload);
        this.success = response.message;
        this.closeLockModal();
        await this.loadUsers();
      } catch (err) {
        this.error = err.message || 'Khóa tài khoản không thành công.';
      } finally {
        this.saving = false;
      }
    },
    async unlockUser(user) {
      if (!confirm(`Mở khóa tài khoản nhân sự: ${user.full_name} (@${user.username})?`)) return;
      this.error = '';
      this.success = '';
      try {
        const response = await adminUserService.unlock(user.id);
        this.success = response.message;
        await this.loadUsers();
      } catch (err) {
        this.error = err.message || 'Mở khóa tài khoản không thành công.';
      }
    },
    
    // Tiện ích định dạng
    formatDate(value) {
      if (!value) return '-';
      return new Date(value).toLocaleString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
    resolveLockedUntil() {
      if (this.lockForm.lock_duration === 'custom') {
        const amount = Math.max(1, Number(this.lockForm.custom_amount || 1));
        const date = new Date();
        const minutes = this.lockForm.custom_unit === 'hours' ? amount * 60 : amount * 1440;
        date.setMinutes(date.getMinutes() + minutes);
        return this.formatDateTimeForApi(date);
      }

      const duration = this.lockDurations.find(item => item.value === this.lockForm.lock_duration);
      const date = new Date();
      date.setMinutes(date.getMinutes() + (duration?.minutes || 1440));
      return this.formatDateTimeForApi(date);
    },
    formatDateTimeForApi(date) {
      const pad = (value) => String(value).padStart(2, '0');
      return [
        date.getFullYear(),
        pad(date.getMonth() + 1),
        pad(date.getDate()),
      ].join('-') + ' ' + [
        pad(date.getHours()),
        pad(date.getMinutes()),
        '00',
      ].join(':');
    },
  },
};
</script>

<style scoped>
.admin-users {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.header-section h2 {
  margin: 0;
  font-size: 24px;
  color: var(--sg-dark);
}

.muted {
  color: var(--sg-text-muted);
  font-size: 13px;
}

.alert {
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
}

.alert.error {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fee2e2;
}

.alert.success {
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #d1fae5;
}

/* Panel lọc */
.filters-panel {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  background: #fff;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid var(--sg-border);
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.search-box {
  flex: 1;
  max-width: 400px;
}

.search-box input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  outline: none;
  font-size: 14px;
}

.search-box input:focus {
  border-color: var(--sg-green);
  box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
}

.filter-selects {
  display: flex;
  gap: 12px;
}

.filter-selects select {
  padding: 10px 14px;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  background: #fff;
  font-size: 14px;
  outline: none;
  cursor: pointer;
}

.filter-selects select:focus {
  border-color: var(--sg-green);
}

/* Bảng */
.table-wrap {
  overflow: auto;
  border: 1px solid var(--sg-border);
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 1000px;
}

th, td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--sg-border);
  text-align: left;
  font-size: 14px;
  vertical-align: middle;
}

th {
  background: #f9fafb;
  font-weight: 700;
  color: #374151;
}

tr:last-child td {
  border-bottom: none;
}

.empty {
  text-align: center;
  color: var(--sg-text-muted);
  padding: 40px;
  font-style: italic;
}

.user-info-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-name {
  font-weight: 600;
  color: #111827;
}

.phone-muted {
  font-size: 12px;
}

.badge {
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 700;
}

.badge.self {
  background: #e0f2fe;
  color: #0369a1;
}

.roles-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.role-tag {
  display: inline-flex;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  background: #f3f4f6;
  color: #4b5563;
}

.role-tag.super_admin {
  background: #fef3c7;
  color: #d97706;
}

.role-tag.admin {
  background: #fee2e2;
  color: #dc2626;
}

.role-tag.system_staff, .role-tag.complaint_handler {
  background: #e0e7ff;
  color: #4f46e5;
}

.role-tag.venue_owner {
  background: #dcfce7;
  color: #166534;
}

.status-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.status {
  display: inline-flex;
  width: fit-content;
  padding: 3px 8px;
  border-radius: 999px;
  background: #e5e7eb;
  font-size: 12px;
  font-weight: 700;
}

.status.active {
  background: #dcfce7;
  color: #166534;
}

.status.locked {
  background: #fee2e2;
  color: #991b1b;
}

.lock-until-text {
  font-size: 11px;
  color: #ef4444;
}

.actions-cell {
  display: flex;
  gap: 8px;
}

/* Nút */
.btn {
  border: 0;
  border-radius: 8px;
  padding: 10px 16px;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn.success {
  background: var(--sg-green);
  color: #fff;
}

.btn.success:hover {
  background: var(--sg-green-dark);
}

.btn.secondary {
  background: #f3f4f6;
  color: #111827;
  border: 1px solid #e5e7eb;
}

.btn.secondary:hover {
  background: #e5e7eb;
}

.btn.danger {
  background: #dc2626;
  color: #fff;
}

.btn.danger:hover {
  background: #b91c1c;
}

.btn-action {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 6px 12px;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  background: #fff;
  color: #374151;
  transition: all 0.2s;
}

.btn-action:hover {
  background: #f9fafb;
}

.btn-action.view {
  border-color: #3b82f6;
  color: #2563eb;
}

.btn-action.view:hover {
  background: #eff6ff;
}

.btn-action.edit:hover {
  background: #f3f4f6;
}

.btn-action.edit:disabled, .btn-action.lock:disabled, .btn-action.unlock:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  border-color: #e5e7eb !important;
  color: #9ca3af !important;
  background: #f9fafb !important;
}

.btn-action.lock {
  border-color: #ef4444;
  color: #dc2626;
}

.btn-action.lock:hover {
  background: #fef2f2;
}

.btn-action.unlock {
  border-color: var(--sg-green);
  color: var(--sg-green-dark);
}

.btn-action.unlock:hover {
  background: #f0fdf4;
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, .6);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  display: grid;
  place-items: center;
  z-index: 500;
  padding: 20px;
}

.modal {
  width: min(580px, calc(100vw - 32px));
  max-height: calc(100vh - 40px);
  background: #fff;
  border-radius: 16px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
}

.modal.wide {
  width: min(1000px, calc(100vw - 32px));
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 16px;
}

.modal h3 {
  margin: 0;
  font-size: 20px;
  color: #0f172a;
}

.icon-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #f1f5f9;
  color: #475569;
  font-size: 20px;
  line-height: 1;
  border: none;
  cursor: pointer;
  display: grid;
  place-items: center;
}

.icon-btn:hover {
  background: #e2e8f0;
}

.modal-body {
  flex: 1;
  overflow-y: auto;
  padding-right: 4px;
}

.modal-body.scrollable {
  max-height: 50vh;
}

.modal.wide .modal-body.scrollable {
  max-height: 70vh;
}

.required {
  color: #ef4444;
}

/* Form Grid */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-grid label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-weight: 700;
  font-size: 14px;
  color: #334155;
}

.full-width {
  grid-column: span 2;
}

.modal input,
.modal select,
.modal textarea {
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  padding: 10px 12px;
  font: inherit;
  outline: none;
  background: #fff;
  width: 100%;
}

.modal input:focus,
.modal select:focus,
.modal textarea:focus {
  border-color: var(--sg-green);
  box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
}

.roles-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 8px;
}

.checkbox-label {
  display: flex !important;
  flex-direction: row !important;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  background: #f8fafc;
  transition: all 0.2s;
}

.checkbox-label:hover {
  border-color: #86efac;
  background: #f0fdf4;
}

.checkbox-label input {
  width: 18px;
  height: 18px;
  margin-top: 2px;
  cursor: pointer;
}

.checkbox-label strong {
  display: block;
  font-size: 14px;
  color: #0f172a;
}

.role-desc {
  font-size: 12px;
  color: #64748b;
  font-weight: 400;
}

/* Chi tiết & Audit Grid */
.detail-grid {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 24px;
}

.detail-info-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.detail-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: #22c55e;
  color: #fff;
  font-size: 36px;
  font-weight: 800;
  display: grid;
  place-items: center;
  margin-bottom: 16px;
}

.detail-info-card h4 {
  font-size: 18px;
  color: #0f172a;
  margin: 0 0 4px 0;
}

.detail-username {
  font-size: 14px;
  color: #64748b;
  margin: 0 0 16px 0;
}

.detail-meta-list {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 12px;
  border-top: 1px solid #e2e8f0;
  padding-top: 16px;
}

.detail-meta-item {
  font-size: 13px;
}

.detail-meta-item .label {
  font-weight: 700;
  color: #475569;
  margin-right: 6px;
}

.detail-meta-item .value {
  color: #0f172a;
}

.detail-meta-item.nested {
  margin-left: 14px;
  padding: 8px;
  border-left: 2px solid #ef4444;
  background: #fef2f2;
  border-radius: 0 6px 6px 0;
}

.lock-detail-item {
  font-size: 12px;
  color: #991b1b;
  margin-bottom: 2px;
}

.detail-logs-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow: hidden;
}

.detail-logs-panel h5 {
  margin: 0;
  font-size: 16px;
  color: #1e293b;
}

.logs-table-wrap {
  overflow: auto;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  max-height: 400px;
}

.logs-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 500px;
}

.logs-table th, .logs-table td {
  padding: 10px 12px;
  font-size: 12px;
}

.logs-table th {
  background: #f8fafc;
  font-weight: 700;
}

.log-date {
  color: #475569;
}

.actor-name-text {
  color: #0f172a;
}

.ip-text {
  font-size: 10px;
}

.log-action-badge {
  display: inline-flex;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 700;
  background: #e2e8f0;
  color: #475569;
}

.log-action-badge.user\.created {
  background: #dcfce7;
  color: #15803d;
}

.log-action-badge.user\.updated {
  background: #e0f2fe;
  color: #0369a1;
}

.log-action-badge.user\.locked {
  background: #fee2e2;
  color: #b91c1c;
}

.log-action-badge.user\.unlocked {
  background: #fef3c7;
  color: #b45309;
}

.log-diff-cell {
  max-width: 250px;
}

.diff-content {
  font-size: 11px;
  color: #334155;
  line-height: 1.4;
}

.diff-line {
  margin-bottom: 4px;
}

.diff-line:last-child {
  margin-bottom: 0;
}

.field-name {
  font-weight: 700;
  color: #475569;
  margin-right: 4px;
}

.old-val {
  color: #b91c1c;
  text-decoration: line-through;
}

.arrow {
  margin: 0 4px;
  color: #94a3b8;
}

.new-val {
  color: #15803d;
  font-weight: 600;
}

.highlight-val {
  color: #1d4ed8;
  font-weight: 600;
}

/* Modal khóa target */
.target-user {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
}

.target-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #dcfce7;
  color: #166534;
  display: grid;
  place-items: center;
  font-weight: 800;
}

.target-user strong {
  display: block;
  font-size: 14px;
}

.target-user span {
  font-size: 12px;
  color: #64748b;
}

.segmented, .duration-grid {
  display: grid;
  gap: 8px;
}

.segmented {
  grid-template-columns: repeat(3, 1fr);
}

.duration-grid {
  grid-template-columns: repeat(5, 1fr);
}

.segmented button, .duration-grid button {
  min-height: 38px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
  color: #334155;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
}

.segmented button:hover, .duration-grid button:hover {
  border-color: #86efac;
  background: #f0fdf4;
}

.segmented button.active, .duration-grid button.active {
  border-color: var(--sg-green);
  background: #dcfce7;
  color: #166534;
}

.custom-duration {
  display: grid;
  grid-template-columns: 1fr 140px;
  gap: 10px;
  margin-top: 8px;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  border-top: 1px solid #f1f5f9;
  padding-top: 16px;
}

.mb-2 {
  margin-bottom: 8px;
}

.mt-1 {
  margin-top: 4px;
}

.d-block {
  display: block;
}

.password-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.password-input-wrapper input {
  width: 100%;
  padding-right: 40px !important;
}

.toggle-password-btn {
  position: absolute;
  right: 10px;
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  border-radius: 6px;
  transition: all 0.2s;
}

.toggle-password-btn:hover {
  color: var(--sg-green);
  background-color: #f1f5f9;
}

/* Responsive */
@media (max-width: 768px) {
  .filters-panel {
    flex-direction: column;
  }
  .search-box {
    max-width: 100%;
  }
  .filter-selects {
    width: 100%;
    justify-content: space-between;
  }
  .form-grid, .roles-grid {
    grid-template-columns: 1fr;
  }
  .full-width {
    grid-column: span 1;
  }
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>

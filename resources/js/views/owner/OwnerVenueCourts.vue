<template>
  <div class="venue-courts-container">
    <section class="page-head header-card">
      <div class="header-left">
        <router-link to="/owner/venue-clusters" class="btn-back">
          <AppIcon name="chevronLeft" size="15" />
          <span>Quay lại cụm sân</span>
        </router-link>
        <h2 v-if="cluster">Danh sách sân con: {{ cluster.name }}</h2>
        <h2 v-else>Quản lý sân con</h2>
        <p class="subtitle">Quản lý các sân thi đấu chi tiết trong cụm sân</p>
      </div>
      <button class="btn btn-primary" :disabled="!cluster" @click="openCreateModal">
        <AppIcon name="plus" size="16" />
        <span>Thêm sân con</span>
      </button>
    </section>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state card">
      <div class="spinner"></div>
      <p>Đang tải danh sách sân con...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state card">
      <p class="error-message">{{ error }}</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="courts.length === 0" class="empty-state card">
      <p>Cụm sân này chưa có sân con nào.</p>
      <button class="btn btn-primary" @click="openCreateModal">Thêm sân con ngay</button>
    </div>

    <!-- Grid List of Courts -->
    <div v-else class="courts-grid">
      <div v-for="court in courts" :key="court.id" class="court-card card">
        <div class="court-header">
          <h3 class="court-name">{{ court.name }}</h3>
          <span class="status-badge" :class="court.status">
            {{ formatStatus(court.status) }}
          </span>
        </div>

        <div class="court-body">
          <div class="info-row">
            <span class="label">Loại sân:</span>
            <span class="value">{{ court.court_type?.name }}</span>
          </div>
          <div class="info-row">
            <span class="label">Thứ tự hiển thị:</span>
            <span class="value">{{ court.sort_order }}</span>
          </div>
        </div>

        <div class="court-actions">
          <ActionIconButton icon="pencil" label="Sửa sân con" @click="openEditModal(court)" />
          <ActionIconButton icon="trash" label="Xóa sân con" variant="danger" @click="confirmDelete(court)" />
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
      <div class="modal card">
        <div class="modal-header">
          <h3>{{ editingId ? 'Cập nhật sân con' : 'Thêm sân con mới' }}</h3>
          <button class="btn-close" @click="closeModal">&times;</button>
        </div>
        <form @submit.prevent="handleSubmit">
          <div class="modal-body">
            <div v-if="modalError" class="alert alert-danger">{{ modalError }}</div>

            <div class="form-group">
              <label for="court-name">Tên sân con <span class="required">*</span></label>
              <input
                id="court-name"
                v-model="form.name"
                type="text"
                class="form-control"
                placeholder="Ví dụ: Sân số 1, Sân VIP 2..."
                required
              />
            </div>

            <div class="form-group">
              <label>Loại sân <span class="required">*</span></label>
              <div class="custom-select-wrapper">
                <div 
                  class="custom-select-trigger" 
                  :class="{ active: showTypeDropdown }" 
                  @click.stop="showTypeDropdown = !showTypeDropdown"
                >
                  <span v-if="selectedCourtType">
                    <span class="parent-name">{{ getParentTypeName(selectedCourtType) }}</span>
                    <span class="separator">/</span>
                    <span class="child-name">{{ selectedCourtType.name }} ({{ selectedCourtType.player_count }} người)</span>
                  </span>
                  <span v-else class="placeholder">-- Chọn loại sân --</span>
                  <span class="arrow">&#9662;</span>
                </div>
                <div v-if="showTypeDropdown" class="custom-options-container">
                  <div v-for="group in groupedCourtTypes" :key="group.id" class="custom-optgroup">
                    <div class="custom-optgroup-label">{{ group.name }}</div>
                    <div 
                      v-for="child in group.children" 
                      :key="child.id" 
                      class="custom-option"
                      :class="{ selected: form.court_type_id === child.id }"
                      @click="selectCourtType(child)"
                    >
                      <span class="option-text">{{ child.name }}</span>
                      <span class="option-details">({{ child.player_count }} người)</span>
                      <span v-if="form.court_type_id === child.id" class="check-mark">&#10003;</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="editingId" class="form-group">
              <label for="court-status">Trạng thái sân <span class="required">*</span></label>
              <select id="court-status" v-model="form.status" class="form-control" required>
                <option value="active">Đang hoạt động</option>
                <option value="inactive">Tạm ngưng hoạt động</option>
                <option value="maintenance">Bảo trì</option>
              </select>
            </div>

            <div class="form-group">
              <label for="sort-order">Thứ tự hiển thị</label>
              <input
                id="sort-order"
                v-model.number="form.sort_order"
                type="number"
                min="0"
                class="form-control"
              />
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline" @click="closeModal">Hủy</button>
            <button type="submit" class="btn btn-primary" :disabled="submitting">
              {{ submitting ? 'Đang lưu...' : 'Lưu lại' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import { venueClusterService } from '../../services/venueClusters';
import { courtTypeService } from '../../services/courtTypes';

export default {
  name: 'OwnerVenueCourts',
  components: { ActionIconButton, AppIcon },
  data() {
    return {
      clusterId: this.$route.query.venue_cluster_id || localStorage.getItem('selected_cluster') || '',
      cluster: null,
      courts: [],
      courtTypes: [],
      loading: true,
      error: null,
      showModal: false,
      editingId: null,
      submitting: false,
      modalError: null,
      form: {
        name: '',
        court_type_id: '',
        status: 'active',
        sort_order: 1,
      },
      showTypeDropdown: false,
    };
  },
  computed: {
    selectedCourtType() {
      return this.courtTypes.find(t => t.id === this.form.court_type_id);
    },
    groupedCourtTypes() {
      // Tìm các danh mục cha (parent_id là null)
      const parents = this.courtTypes.filter(t => !t.parent_id);
      
      const groups = parents.map(parent => {
        return {
          id: parent.id,
          name: parent.name,
          // Lọc danh sách con thuộc cha này
          children: this.courtTypes.filter(t => t.parent_id === parent.id)
        };
      });

      // Chỉ hiển thị các nhóm bộ môn có cấu hình sân con
      return groups.filter(g => g.children.length > 0);
    }
  },
  methods: {
    async initData() {
      this.loading = true;
      this.error = null;
      try {
        if (!this.clusterId) {
          const clustersRes = await venueClusterService.getClusters();
          this.clusterId = clustersRes.data?.[0]?.id || '';
        }

        if (!this.clusterId) {
          throw new Error('Thiếu mã cụm sân (venue_cluster_id).');
        }

        localStorage.setItem('selected_cluster', this.clusterId);

        // Tải chi tiết cụm sân
        const clusterRes = await venueClusterService.getClusterDetails(this.clusterId);
        this.cluster = clusterRes.data;

        // Tải danh sách sân con
        const courtsRes = await venueClusterService.getCourts(this.clusterId);
        this.courts = courtsRes.data || [];

        // Tải danh mục loại sân
        const courtTypesRes = await courtTypeService.getAll();
        this.courtTypes = courtTypesRes.data || [];
      } catch (err) {
        this.error = err.message || 'Lỗi khởi tạo dữ liệu.';
      } finally {
        this.loading = false;
      }
    },
    formatStatus(status) {
      const map = {
        active: 'Đang hoạt động',
        inactive: 'Tạm khóa',
        maintenance: 'Bảo trì',
      };
      return map[status] || status;
    },
    openCreateModal() {
      this.editingId = null;
      this.modalError = null;
      this.showTypeDropdown = false;
      this.form = {
        name: '',
        court_type_id: '',
        status: 'active',
        sort_order: this.courts.length + 1,
      };
      this.showModal = true;
    },
    openEditModal(court) {
      this.editingId = court.id;
      this.modalError = null;
      this.showTypeDropdown = false;
      this.form = {
        name: court.name,
        court_type_id: court.court_type_id,
        status: court.status,
        sort_order: court.sort_order,
      };
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
      this.editingId = null;
      this.modalError = null;
      this.showTypeDropdown = false;
    },
    async handleSubmit() {
      this.submitting = true;
      this.modalError = null;
      if (!this.form.court_type_id) {
        this.modalError = 'Vui lòng chọn loại sân.';
        this.submitting = false;
        return;
      }
      try {
        if (this.editingId) {
          await venueClusterService.updateCourt(this.editingId, {
            name: this.form.name,
            court_type_id: this.form.court_type_id,
            status: this.form.status,
            sort_order: this.form.sort_order,
          });
        } else {
          await venueClusterService.createCourt({
            venue_cluster_id: this.clusterId,
            court_type_id: this.form.court_type_id,
            name: this.form.name,
            sort_order: this.form.sort_order,
          });
        }
        await this.initData();
        this.closeModal();
      } catch (err) {
        this.modalError = err.message || 'Lỗi lưu dữ liệu sân con.';
      } finally {
        this.submitting = false;
      }
    },
    getParentTypeName(child) {
      if (!child.parent_id) return '';
      const parent = this.courtTypes.find(t => t.id === child.parent_id);
      return parent ? parent.name : '';
    },
    selectCourtType(child) {
      this.form.court_type_id = child.id;
      this.showTypeDropdown = false;
    },
    handleOutsideClick(e) {
      const el = this.$el.querySelector('.custom-select-wrapper');
      if (el && !el.contains(e.target)) {
        this.showTypeDropdown = false;
      }
    },
    handleOwnerClusterChanged(event) {
      const clusterId = event.detail?.id;
      if (!clusterId || String(clusterId) === String(this.clusterId)) return;
      this.clusterId = clusterId;
      this.initData();
    },
    async confirmDelete(court) {
      if (confirm(`Bạn có chắc chắn muốn xóa sân "${court.name}" không?`)) {
        try {
          await venueClusterService.deleteCourt(court.id);
          await this.initData();
        } catch (err) {
          alert(err.message || 'Không thể xóa sân con.');
        }
      }
    },
  },
  mounted() {
    document.addEventListener('click', this.handleOutsideClick);
    window.addEventListener('owner-cluster-changed', this.handleOwnerClusterChanged);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.handleOutsideClick);
    window.removeEventListener('owner-cluster-changed', this.handleOwnerClusterChanged);
  },
  created() {
    this.initData();
  },
};
</script>

<style scoped>
.venue-courts-container {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid var(--sg-border);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
  padding: 24px;
}

.header-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.header-left {
  display: flex;
  flex-direction: column;
}

.btn-back {
  color: rgba(0, 0, 0, 0.6);
  text-decoration: none;
  font-weight: 700;
  font-size: 13px;
  margin-bottom: 8px;
  transition: color 0.2s ease;
}

.btn-back:hover {
  color: #000000;
}

.header-left h2 {
  font-size: 22px;
  font-weight: 800;
  color: var(--sg-text);
  margin: 0;
}

.subtitle {
  margin-top: 4px;
  color: rgba(15, 23, 42, 0.5);
  font-size: 14px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
}

.btn-primary {
  background: #000000;
  border: 1px solid #000000;
  color: #fff;
}

.btn-primary:hover {
  background: #222222;
  border-color: #222222;
}

.btn-outline {
  border: 1px solid var(--sg-border);
  background: transparent;
  color: var(--sg-text);
}

.btn-outline:hover {
  background: var(--sg-surface);
}

.btn-danger-outline {
  border: 1px solid rgba(0, 0, 0, 0.15);
  background: transparent;
  color: rgba(0, 0, 0, 0.7);
}

.btn-danger-outline:hover {
  background: rgba(0, 0, 0, 0.05);
  border-color: rgba(0, 0, 0, 0.25);
  color: #000000;
}

.courts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}

.court-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.court-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
}

.court-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--sg-border);
  padding-bottom: 12px;
}

.court-name {
  font-size: 16px;
  font-weight: 800;
  color: var(--sg-text);
  margin: 0;
}

.status-badge {
  display: inline-flex;
  padding: 4px 8px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
  border: 1px solid transparent;
}

.status-badge.active {
  background: rgba(0, 0, 0, 0.04);
  color: #000000;
  border-color: rgba(0, 0, 0, 0.15);
}

.status-badge.inactive {
  background: #f3f4f6;
  color: rgba(0, 0, 0, 0.4);
  border-color: rgba(0, 0, 0, 0.08);
}

.status-badge.maintenance {
  background: #f3f4f6;
  color: rgba(0, 0, 0, 0.7);
  border-color: rgba(0, 0, 0, 0.12);
  border-style: dashed;
}

.court-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}

.info-row .label {
  color: rgba(15, 23, 42, 0.5);
  font-weight: 700;
}

.info-row .value {
  color: var(--sg-text);
  font-weight: 700;
}

.court-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  border-top: 1px solid var(--sg-border);
  padding-top: 12px;
}

.court-actions button {
  flex: 0 0 auto;
}

/* Modal Styling */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.4);
  backdrop-filter: blur(4px);
  display: grid;
  place-items: center;
  z-index: 1000;
  padding: 20px;
}

.modal {
  width: 100%;
  max-width: 500px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  padding: 0;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--sg-border);
}

.modal-header h3 {
  font-size: 18px;
  font-weight: 800;
  margin: 0;
  color: var(--sg-text);
}

.btn-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: rgba(15, 23, 42, 0.4);
}

.modal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 13px;
  font-weight: 700;
  color: var(--sg-text);
}

.required {
  color: #ef4444;
}

.form-control {
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid var(--sg-border);
  font-size: 14px;
  color: var(--sg-text);
  outline: none;
  transition: border-color 0.2s ease;
}

.form-control:focus {
  border-color: #000000;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 24px;
  border-top: 1px solid var(--sg-border);
  background: var(--sg-surface);
}

.alert-danger {
  background: #f3f4f6;
  color: #ef4444;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  border: 1px solid #e5e7eb;
}

.loading-state, .error-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  text-align: center;
  gap: 16px;
  color: rgba(15, 23, 42, 0.6);
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(0, 0, 0, 0.1);
  border-top-color: #000000;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Custom Select Dropdown Styling */
.custom-select-wrapper {
  position: relative;
  width: 100%;
}

.custom-select-trigger {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 8px;
  font-size: 14px;
  color: var(--sg-text);
  cursor: pointer;
  user-select: none;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.custom-select-trigger:hover {
  border-color: #000000;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.custom-select-trigger.active {
  border-color: #000000;
  box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.05);
}

.custom-select-trigger .parent-name {
  color: rgba(15, 23, 42, 0.4);
  font-weight: 500;
}

.custom-select-trigger .separator {
  margin: 0 6px;
  color: rgba(15, 23, 42, 0.2);
}

.custom-select-trigger .child-name {
  font-weight: 700;
  color: var(--sg-text);
}

.custom-select-trigger .placeholder {
  color: rgba(15, 23, 42, 0.4);
}

.custom-select-trigger .arrow {
  font-size: 10px;
  color: rgba(15, 23, 42, 0.5);
  transition: transform 0.2s ease;
}

.custom-select-trigger.active .arrow {
  transform: rotate(180deg);
}

/* Dropdown Container */
.custom-options-container {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  background: #ffffff;
  border: 1px solid var(--sg-border);
  border-radius: 10px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
  z-index: 100;
  max-height: 250px;
  overflow-y: auto;
  opacity: 0;
  transform: translateY(-8px);
  animation: slideDown 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes slideDown {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Optgroup Styling */
.custom-optgroup-label {
  padding: 10px 14px 6px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: rgba(15, 23, 42, 0.4);
  background: rgba(15, 23, 42, 0.02);
  border-bottom: 1px solid rgba(0, 0, 0, 0.02);
}

/* Option Styling */
.custom-option {
  display: flex;
  align-items: center;
  padding: 10px 14px;
  cursor: pointer;
  font-size: 13.5px;
  color: var(--sg-text);
  transition: background 0.15s ease, color 0.15s ease;
}

.custom-option:hover {
  background: rgba(0, 0, 0, 0.03);
}

.custom-option.selected {
  background: rgba(0, 0, 0, 0.05);
  font-weight: 700;
}

.custom-option .option-text {
  font-weight: 600;
}

.custom-option .option-details {
  margin-left: 6px;
  font-size: 12px;
  color: rgba(15, 23, 42, 0.4);
}

.custom-option .check-mark {
  margin-left: auto;
  color: #000000;
  font-weight: 900;
}
</style>

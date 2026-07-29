<template>
  <section class="page owner-services-page animate-fade-in">
    <!-- Nút thêm mới nổi -->
    <div class="floating-add-container">
      <button class="btn-float-add" type="button" @click="openFormModal()" :disabled="!selectedCluster" title="Thêm dịch vụ/sản phẩm">
        <AppIcon name="plus" size="20" />
        <span class="btn-float-text">Thêm dịch vụ</span>
      </button>
    </div>

    <!-- Alert Thông báo -->
    <div v-if="error" class="alert error animate-slide-in" style="margin-bottom: 20px;">
      {{ error }}
      <button class="alert-close" @click="error = ''">&times;</button>
    </div>
    <div v-if="success" class="alert success animate-slide-in" style="margin-bottom: 20px;">
      {{ success }}
      <button class="alert-close" @click="success = ''">&times;</button>
    </div>

    <!-- Thanh công cụ tìm kiếm và lọc kiểu SaaS -->
    <SaaSFilterBar
      v-model="filterCategory"
      :tabs="filterTabs"
      v-model:search="searchQuery"
      searchPlaceholder="Tìm kiếm tên dịch vụ, sản phẩm..."
    >
      <template #actions>
        <select v-model="filterStatus" class="form-control status-select" style="width: 180px; height: 38px; border-radius: 8px;">
          <option value="">Tất cả trạng thái</option>
          <option value="active">Đang kinh doanh</option>
          <option value="inactive">Tạm ngưng</option>
          <option value="out_of_stock">Hết hàng</option>
        </select>
      </template>
    </SaaSFilterBar>

    <!-- Danh sách dịch vụ sử dụng SaaSTable -->
    <div class="table-container" style="margin-top: 16px;">
      <div v-if="loading" class="state" style="padding: 48px; text-align: center; color: #64748b; background: var(--admin-surface, #fff); border-radius: 12px; border: 1px solid var(--admin-border, #e2e8f0);">
        <div class="spinner" style="margin: 0 auto 12px; border: 3px solid rgba(0,0,0,0.1); border-top-color: var(--primary-color, #10b981); width: 28px; height: 28px; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        Đang tải danh sách dịch vụ...
      </div>
      <div v-else-if="!selectedCluster" class="state" style="padding: 48px; text-align: center; color: #64748b; background: var(--admin-surface, #fff); border-radius: 12px; border: 1px solid var(--admin-border, #e2e8f0);">
        Vui lòng chọn cụm sân ở thanh bên để quản lý dịch vụ.
      </div>
      
      <SaaSTable v-else :columns="columns" :data="filteredServices">
        <template #name="{ row }">
          <span style="color: var(--admin-text, #1e293b);">{{ row.name }}</span>
        </template>
        
        <template #category="{ row }">
          <span style="color: var(--admin-text, #1e293b);">
            {{ row.category?.name || 'Chưa phân loại' }}
          </span>
        </template>
        
        <template #price="{ row }">
          <span style="color: var(--primary-color, #10b981);">
            {{ formatPrice(row.price) }}
          </span>
        </template>
        
        <template #unit="{ row }">
          <span style="color: var(--admin-muted, #64748b);">{{ row.unit }}</span>
        </template>
        
        <template #status="{ row }">
          <span class="badge" :class="row.status">
            {{ statusLabels[row.status] || row.status }}
          </span>
        </template>
        
        <template #description="{ row }">
          <span class="desc-text" :title="row.description" style="max-width: 250px; display: inline-block; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; color: var(--admin-muted, #64748b);">
            {{ row.description || '-' }}
          </span>
        </template>
        
        <template #actions="{ row }">
          <TableActionGroup>
            <ActionIconButton icon="pencil" label="Chỉnh sửa" @click="openFormModal(row)" />
            <ActionIconButton
              :icon="row.status === 'active' ? 'power' : 'check'"
              :label="row.status === 'active' ? 'Tạm ngưng' : 'Kích hoạt'"
              :variant="row.status === 'active' ? 'danger' : 'success'"
              @click="toggleItemStatus(row)"
            />
            <ActionIconButton icon="trash" label="Xóa dịch vụ" variant="danger" @click="deleteItem(row)" />
          </TableActionGroup>
        </template>

        <template #empty>
          Chưa có dịch vụ hoặc sản phẩm nào khớp với tìm kiếm.
        </template>
      </SaaSTable>
    </div>

    <!-- Modal Form Thêm/Sửa Dịch vụ -->
    <div v-if="showFormModal" class="modal-backdrop" @mousedown="handleBackdropMousedown" @click="handleBackdropClick($event, closeFormModal)">
      <form class="modal card" @submit.prevent="saveForm" @mousedown.stop style="max-width: 500px; width: 100%;">
        <div class="modal-header">
          <h3>{{ form.id ? 'Sửa dịch vụ/sản phẩm tại sân' : 'Thêm dịch vụ/sản phẩm tại sân' }}</h3>
          <button class="btn-close" type="button" @click="closeFormModal">
            <AppIcon name="x" size="18" />
          </button>
        </div>
        <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
          
          <div class="form-group">
            <label class="form-label">Tên sản phẩm/Dịch vụ <span class="text-danger">*</span></label>
            <input
              v-model.trim="form.name"
              type="text"
              class="form-control"
              placeholder="Ví dụ: Nước ngọt Sting dâu, Thuê vợt Yonex Astrox 88D..."
              @input="clearError('name')"
            />
            <small v-if="validationErrors.name" class="field-error">{{ validationErrors.name }}</small>
          </div>

          <div class="row" style="display: flex; gap: 16px;">
            <div class="col" style="flex: 1;">
              <div class="form-group">
                <label class="form-label">Phân loại danh mục <span class="text-danger">*</span></label>
                <select v-model="form.category_id" class="form-control" @change="clearError('category_id')">
                  <option value="">-- Chọn danh mục --</option>
                  <option v-for="cat in dbCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
                <small v-if="validationErrors.category_id" class="field-error">{{ validationErrors.category_id }}</small>
              </div>
            </div>
            <div class="col" style="flex: 1;">
              <div class="form-group">
                <label class="form-label">Trạng thái kinh doanh <span class="text-danger">*</span></label>
                <select v-model="form.status" class="form-control">
                  <option value="active">Đang kinh doanh</option>
                  <option value="inactive">Tạm ngưng</option>
                  <option value="out_of_stock">Hết hàng</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row" style="display: flex; gap: 16px;">
            <div class="col" style="flex: 1;">
              <div class="form-group">
                <label class="form-label">Giá (VNĐ) <span class="text-danger">*</span></label>
                <input
                  v-model.number="form.price"
                  type="number"
                  class="form-control"
                  placeholder="Ví dụ: 15000"
                  min="0"
                  @input="clearError('price')"
                />
                <small v-if="validationErrors.price" class="field-error">{{ validationErrors.price }}</small>
              </div>
            </div>
            <div class="col" style="flex: 1;">
              <div class="form-group">
                <label class="form-label">Đơn vị tính <span class="text-danger">*</span></label>
                <input
                  v-model.trim="form.unit"
                  type="text"
                  class="form-control"
                  list="modal-units-list"
                  placeholder="Ví dụ: chai, lượt, tiếng, quả..."
                  @input="clearError('unit')"
                />
                <datalist id="modal-units-list">
                  <option value="chai"></option>
                  <option value="lượt"></option>
                  <option value="tiếng"></option>
                  <option value="quả"></option>
                  <option value="cái"></option>
                  <option value="buổi"></option>
                  <option value="ngày"></option>
                </datalist>
                <small v-if="validationErrors.unit" class="field-error">{{ validationErrors.unit }}</small>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Mô tả chi tiết</label>
            <textarea
              v-model.trim="form.description"
              class="form-control"
              rows="3"
              placeholder="Nhập mô tả về sản phẩm (Ví dụ: thương hiệu, hương vị, chính sách thuê cọc...)"
            ></textarea>
          </div>

        </div>
        <div class="modal-footer" style="margin-top: 12px;">
          <button class="btn secondary" type="button" @click="closeFormModal">Hủy</button>
          <button class="btn primary" type="submit" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu' }}
          </button>
        </div>
      </form>
    </div>
  </section>
</template>

<script>
import ActionIconButton from '../../components/ActionIconButton.vue';
import AppIcon from '../../components/AppIcon.vue';
import TableActionGroup from '../../components/TableActionGroup.vue';
import SaaSFilterBar from '../../components/ui/SaaSFilterBar.vue';
import SaaSTable from '../../components/ui/SaaSTable.vue';
import { ownerVenueService } from '../../services/ownerVenueService.js';
import { ownerServiceCategoryService } from '../../services/ownerServiceCategory.js';

export default {
  name: 'OwnerServices',
  components: { ActionIconButton, AppIcon, TableActionGroup, SaaSFilterBar, SaaSTable },
  data() {
    return {
      selectedCluster: null,
      services: [],
      dbCategories: [],
      loading: false,
      saving: false,
      showFormModal: false,
      error: '',
      success: '',
      searchQuery: '',
      filterCategory: '',
      filterStatus: '',
      form: this.emptyForm(),
      validationErrors: {},
      mousedownWasOnBackdrop: false,
      statusLabels: {
        active: 'Kinh doanh',
        inactive: 'Tạm ngưng',
        out_of_stock: 'Hết hàng'
      },
      columns: [
        { key: 'name', label: 'Tên sản phẩm/Dịch vụ' },
        { key: 'category', label: 'Phân loại' },
        { key: 'price', label: 'Giá bán/thuê' },
        { key: 'unit', label: 'ĐVT' },
        { key: 'status', label: 'Trạng thái' },
        { key: 'description', label: 'Mô tả' },
        { key: 'actions', label: 'Thao tác', align: 'center', style: { width: '120px' } }
      ]
    };
  },
  computed: {
    filterTabs() {
      return [
        { value: '', label: 'Tất cả' },
        ...this.dbCategories.map(cat => ({ value: cat.id, label: cat.name }))
      ];
    },
    filteredServices() {
      return this.services.filter(item => {
        const matchesSearch = !this.searchQuery || item.name.toLowerCase().includes(this.searchQuery.toLowerCase());
        const matchesCategory = !this.filterCategory || item.category_id === this.filterCategory;
        const matchesStatus = !this.filterStatus || item.status === this.filterStatus;
        return matchesSearch && matchesCategory && matchesStatus;
      });
    }
  },
  mounted() {
    window.addEventListener('owner-cluster-changed', this.handleClusterChange);
    this.initSelectedCluster();
    this.fetchCategories();
  },
  beforeUnmount() {
    window.removeEventListener('owner-cluster-changed', this.handleClusterChange);
  },
  methods: {
    handleBackdropMousedown(event) {
      this.mousedownWasOnBackdrop = event.target === event.currentTarget;
    },
    handleBackdropClick(event, closeFn) {
      if (this.mousedownWasOnBackdrop && event.target === event.currentTarget) {
        closeFn();
      }
      this.mousedownWasOnBackdrop = false;
    },
    async initSelectedCluster() {
      const savedClusterId = localStorage.getItem('selected_cluster');
      if (savedClusterId) {
        try {
          const { venueClusterService } = await import('../../services/venueClusters');
          const res = await venueClusterService.getClusters();
          const list = res.data || [];
          const cluster = list.find(c => String(c.id) === String(savedClusterId));
          if (cluster) {
            this.selectedCluster = cluster;
            this.fetchServices();
          }
        } catch (err) {
          console.error('Lỗi khi tải cụm sân:', err);
        }
      }
    },
    handleClusterChange(event) {
      const cluster = event.detail;
      if (cluster) {
        this.selectedCluster = cluster;
        this.fetchServices();
      } else {
        this.selectedCluster = null;
        this.services = [];
      }
    },
    async fetchCategories() {
      try {
        const res = await ownerServiceCategoryService.listActive();
        this.dbCategories = res.data || [];
      } catch (err) {
        console.error('Lỗi khi tải danh mục dịch vụ:', err);
      }
    },
    async fetchServices() {
      if (!this.selectedCluster) return;
      this.loading = true;
      this.error = '';
      try {
        const res = await ownerVenueService.listForOwner(this.selectedCluster.id);
        this.services = res.data || [];
      } catch (err) {
        this.error = err.message || 'Không thể tải danh sách dịch vụ tại sân.';
      } finally {
        this.loading = false;
      }
    },
    emptyForm() {
      return {
        id: null,
        name: '',
        category_id: '',
        price: '',
        unit: '',
        status: 'active',
        description: ''
      };
    },
    openFormModal(item = null) {
      this.validationErrors = {};
      if (item) {
        this.form = {
          id: item.id,
          name: item.name,
          category_id: item.category_id,
          price: parseFloat(item.price),
          unit: item.unit,
          status: item.status,
          description: item.description || ''
        };
      } else {
        this.form = this.emptyForm();
      }
      this.showFormModal = true;
    },
    closeFormModal() {
      this.showFormModal = false;
      this.form = this.emptyForm();
    },
    clearError(field) {
      if (this.validationErrors[field]) {
        delete this.validationErrors[field];
      }
    },
    validate() {
      const errors = {};
      if (!this.form.name) errors.name = 'Vui lòng nhập tên dịch vụ/sản phẩm';
      if (!this.form.category_id) errors.category_id = 'Vui lòng chọn phân loại danh mục';
      if (this.form.price === '' || this.form.price === null || this.form.price < 0) errors.price = 'Giá tiền không hợp lệ';
      if (!this.form.unit) errors.unit = 'Vui lòng nhập đơn vị tính';
      this.validationErrors = errors;
      return Object.keys(errors).length === 0;
    },
    async saveForm() {
      if (!this.validate() || !this.selectedCluster) return;
      this.saving = true;
      this.error = '';
      this.success = '';
      try {
        if (this.form.id) {
          const res = await ownerVenueService.update(this.form.id, this.form);
          const idx = this.services.findIndex(s => s.id === this.form.id);
          if (idx !== -1) {
            this.services[idx] = res.data;
          }
          this.success = 'Cập nhật sản phẩm/dịch vụ thành công!';
        } else {
          const res = await ownerVenueService.create(this.selectedCluster.id, this.form);
          this.services.unshift(res.data);
          this.success = 'Thêm sản phẩm/dịch vụ mới thành công!';
        }
        this.closeFormModal();
      } catch (err) {
        this.error = err.message || 'Lỗi khi lưu sản phẩm/dịch vụ.';
      } finally {
        this.saving = false;
      }
    },
    async toggleItemStatus(item) {
      this.error = '';
      this.success = '';
      try {
        const res = await ownerVenueService.toggleStatus(item.id);
        const idx = this.services.findIndex(s => s.id === item.id);
        if (idx !== -1) {
          this.services[idx].status = res.data.status;
        }
        this.success = `Đã chuyển đổi trạng thái dịch vụ ${item.name} thành công.`;
      } catch (err) {
        this.error = err.message || 'Lỗi khi cập nhật trạng thái.';
      }
    },
    async deleteItem(item) {
      if (!confirm(`Bạn có chắc chắn muốn xóa dịch vụ "${item.name}"?`)) return;
      this.error = '';
      this.success = '';
      try {
        await ownerVenueService.delete(item.id);
        this.services = this.services.filter(s => s.id !== item.id);
        this.success = `Đã xóa dịch vụ "${item.name}" thành công.`;
      } catch (err) {
        this.error = err.message || 'Lỗi khi xóa dịch vụ.';
      }
    },
    formatPrice(val) {
      if (!val && val !== 0) return '0đ';
      return new Intl.NumberFormat('vi-VN').format(val) + 'đ';
    }
  }
};
</script>

<style scoped>
.owner-services-page {
  padding: 24px;
}

.table-container {
  width: 100%;
  min-width: 0;
}

.badge {
  font-size: 11.5px;
  font-weight: 400;
  padding: 4px 10px;
  border-radius: 4px;
  display: inline-block;
}
.badge.active {
  background: #ecfdf5;
  color: #10b981;
}
.badge.inactive {
  background: #f1f5f9;
  color: #64748b;
}
.badge.out_of_stock {
  background: #ffeae6;
  color: #ef4444;
}

/* Status select custom styles matching high-contrast SaaS filters */
.status-select {
  border: 1px solid var(--admin-border, #cbd5e1);
  background: var(--admin-surface, #fff);
  color: var(--admin-text, #1e293b);
  font-size: 13px;
  font-weight: 400;
  padding: 0 12px;
  cursor: pointer;
  box-sizing: border-box;
}
.status-select:focus {
  outline: none;
  border-color: var(--admin-primary, #10b981);
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Animations */
.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}
.animate-slide-in {
  animation: slideIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes slideIn {
  from { transform: translateY(-10px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

/* Dark mode specific override if parent has class */
:deep(.dark) .badge.active {
  background: rgba(16, 185, 129, 0.15);
}
:deep(.dark) .badge.inactive {
  background: rgba(255, 255, 255, 0.05);
}
:deep(.dark) .badge.out_of_stock {
  background: rgba(239, 68, 68, 0.15);
}
</style>

<template>
  <div class="services-master-workspace">

    <!-- Toast Notifications -->
    <Transition name="fade">
      <div v-if="error" class="global-toast alert-error">
        <span>{{ error }}</span>
        <button type="button" class="toast-close-btn" @click="error = ''">✕</button>
      </div>
    </Transition>

    <Transition name="fade">
      <div v-if="success" class="global-toast alert-success">
        <span>{{ success }}</span>
        <button type="button" class="toast-close-btn" @click="success = ''">✕</button>
      </div>
    </Transition>

    <!-- Master Unified Surface Container -->
    <div class="cluster-profile-surface standalone">

      <!-- PART 1: Top Hero Surface -->
      <ServicesHeaderHero
        :selected-cluster="selectedCluster"
        :tabs="tabsForAppTabs"
        :active-tab="filterCategory"
        @open-create-modal="openFormModal()"
        @tab-change="selectCategoryTab"
      />

      <!-- PART 2: Single Unified Content Surface Card -->
      <div class="profile-section-card services-main-content">
        <ServicesTable
          :services="filteredServices"
          :selected-cluster="selectedCluster"
          :is-loading="loading"
          @open-edit-modal="openFormModal"
          @toggle-status="toggleItemStatus"
          @delete-item="deleteItem"
        />
      </div>

    </div>

    <!-- Teleported Form Modal -->
    <ServiceFormModal
      :show="showFormModal"
      :form="form"
      :db-categories="dbCategories"
      :validation-errors="validationErrors"
      :saving="saving"
      :error-message="modalError"
      @close="closeFormModal"
      @save-form="saveForm"
      @update:form="form = $event"
      @clear-error="clearError"
    />
  </div>
</template>

<script>
import ServicesHeaderHero from '../../components/owner/services/ServicesHeaderHero.vue';
import ServicesTable from '../../components/owner/services/ServicesTable.vue';
import ServiceFormModal from '../../components/owner/services/ServiceFormModal.vue';
import { ownerVenueService } from '../../services/ownerVenueService.js';
import { ownerServiceCategoryService } from '../../services/ownerServiceCategory.js';

export default {
  name: 'OwnerServices',
  components: {
    ServicesHeaderHero,
    ServicesTable,
    ServiceFormModal,
  },
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
      modalError: '',
      filterCategory: '',
      form: this.emptyForm(),
      validationErrors: {},
    };
  },
  computed: {
    tabsForAppTabs() {
      return [
        { key: '', value: '', label: 'Tất cả' },
        ...this.dbCategories.map((cat) => ({
          key: String(cat.id),
          value: String(cat.id),
          label: cat.name,
        })),
      ];
    },
    filteredServices() {
      return this.services.filter((item) => {
        if (!this.filterCategory) return true;
        return String(item.category_id) === String(this.filterCategory);
      });
    },
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
    async initSelectedCluster() {
      const savedClusterId = localStorage.getItem('selected_cluster');
      if (savedClusterId) {
        try {
          const { venueClusterService } = await import('../../services/venueClusters.js');
          const res = await venueClusterService.getClusters();
          const list = res.data || [];
          const cluster = list.find((c) => String(c.id) === String(savedClusterId));
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
    selectCategoryTab(catVal) {
      this.filterCategory = String(catVal || '');
      this.clearMessages();
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
      this.clearMessages();
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
        description: '',
      };
    },
    openFormModal(item = null) {
      this.clearMessages();
      this.modalError = '';
      this.validationErrors = {};
      if (item) {
        this.form = {
          id: item.id,
          name: item.name,
          category_id: item.category_id,
          price: parseFloat(item.price),
          unit: item.unit,
          status: item.status,
          description: item.description || '',
        };
      } else {
        this.form = this.emptyForm();
      }
      this.showFormModal = true;
    },
    closeFormModal() {
      if (this.saving) return;
      this.showFormModal = false;
      this.form = this.emptyForm();
      this.modalError = '';
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
      this.clearMessages();
      this.modalError = '';
      try {
        if (this.form.id) {
          const res = await ownerVenueService.update(this.form.id, this.form);
          const idx = this.services.findIndex((s) => s.id === this.form.id);
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
        this.modalError = err.message || 'Lỗi khi lưu sản phẩm/dịch vụ.';
      } finally {
        this.saving = false;
      }
    },
    async toggleItemStatus(item) {
      this.clearMessages();
      try {
        const res = await ownerVenueService.toggleStatus(item.id);
        const idx = this.services.findIndex((s) => s.id === item.id);
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
      this.clearMessages();
      try {
        await ownerVenueService.delete(item.id);
        this.services = this.services.filter((s) => s.id !== item.id);
        this.success = `Đã xóa dịch vụ "${item.name}" thành công.`;
      } catch (err) {
        this.error = err.message || 'Lỗi khi xóa dịch vụ.';
      }
    },
    clearMessages() {
      this.error = '';
      this.success = '';
    },
  },
};
</script>

<style scoped>
.services-master-workspace {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cluster-profile-surface.standalone {
  background: var(--admin-surface, #ffffff);
  border-radius: 0;
  border: none;
  box-shadow: none;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  gap: 0 !important;
}

.services-main-content {
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 24px;
  background: var(--admin-surface, #ffffff);
  border-radius: 0;
  border: none;
  box-shadow: none;
  margin-top: 0 !important;
}

/* Global Toasts */
.global-toast {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 400;
  color: var(--admin-text, #101c15);
  background: var(--admin-bg-soft, #f7fbf5);
  border: 1px solid var(--admin-border, #cfded1);
  box-shadow: var(--admin-shadow-sm, 0 1px 2px rgba(23, 34, 27, 0.06));
}

.toast-close-btn {
  margin-left: auto;
  background: transparent;
  border: none;
  color: inherit;
  cursor: pointer;
  font-size: 14px;
  opacity: 0.8;
}

.toast-close-btn:hover {
  opacity: 1;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>

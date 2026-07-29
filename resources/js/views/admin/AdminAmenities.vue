<template>
    <div class="amenities-container">

        <!-- Loading State -->
        <div v-if="loading" class="loading-state card">
            <div class="spinner"></div>
            <p>Đang tải danh sách tiện ích...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-state card">
            <p class="error-message">{{ error }}</p>
            <ActionIconButton icon="refresh" label="Thử lại" @click="fetchAmenities" />
        </div>

        <template v-else>
            <!-- Controls Bar -->
            <div class="avc-filters animate-fade-in" v-if="amenities.length > 0 || searchQuery || statusFilter !== 'all'">
                <div class="filter-row">
                    <div class="filter-tabs">
                        <button
                            v-for="option in statusOptions"
                            :key="option.value"
                            class="tab-btn"
                            :class="{ active: statusFilter === option.value }"
                            @click="selectStatus(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <div class="filter-search">
                        <div class="search-box">
                            <AppIcon name="search" size="16" />
                            <input
                                type="text"
                                v-model="searchQuery"
                                placeholder="Tìm kiếm theo tên hoặc mô tả..."
                                class="search-input"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="amenities.length === 0" class="empty-state card">
                <p>Chưa có tiện ích nào được cấu hình trên hệ thống.</p>
                <button class="btn btn-primary" @click="openCreateModal">
                    <AppIcon name="plus" size="17" />
                    <span>Thêm ngay</span>
                </button>
            </div>

            <!-- No Results from Search/Filter -->
            <div v-else-if="filteredAmenities.length === 0" class="empty-state card">
                <p>Không tìm thấy tiện ích nào phù hợp với điều kiện lọc.</p>
                <button class="btn btn-outline" @click="resetFilters">
                    Xóa bộ lọc
                </button>
            </div>

            <!-- Elegant SaaS Table View -->
            <div v-else class="amenities-list-wrapper animate-fade-in">
                <SaaSTable 
                    :columns="tableColumns" 
                    :data="filteredAmenities" 
                    clickable 
                    @row-click="row => openViewModal(row)"
                >
                    <!-- Tên tiện ích -->
                    <template #name="{ row }">
                        <div class="name-col-cell">
                            <span class="amenity-name-text">{{ row.name }}</span>
                            <span class="amenity-desc-text" v-if="row.description">{{ row.description }}</span>
                        </div>
                    </template>

                    <!-- Người tạo -->
                    <template #created_by="{ row }">
                        <div class="owner-col-cell" v-if="row.created_by">
                            <span class="owner-name-text">{{ row.created_by.full_name }}</span>
                            <span class="owner-email-text">{{ row.created_by.email }}</span>
                        </div>
                        <span v-else class="owner-name-text">Hệ thống</span>
                    </template>

                    <!-- Trạng thái -->
                    <template #status="{ row }">
                        <span class="status-badge" :class="'status-is-' + row.status">
                            {{ statusText(row.status) }}
                        </span>
                    </template>

                    <!-- Action Column -->
                    <template #actions="{ row }">
                        <div class="table-actions" @click.stop>
                            <ActionIconButton
                                icon="eye"
                                label="Xem chi tiết"
                                size="sm"
                                @click="openViewModal(row)"
                            />
                            <ActionIconButton
                                icon="pencil"
                                label="Sửa tiện ích"
                                size="sm"
                                @click="openEditModal(row)"
                            />
                            <ActionIconButton
                                icon="trash"
                                label="Xóa tiện ích"
                                variant="danger"
                                size="sm"
                                @click="confirmDelete(row)"
                            />
                        </div>
                    </template>
                </SaaSTable>
            </div>
        </template>

        <!-- View Detail Modal -->
        <div v-if="showViewModal" class="modal-backdrop" @click.self="closeViewModal">
            <div class="modal card">
                <div class="modal-header">
                    <h3>Chi tiết tiện ích</h3>
                    <button class="btn-close" @click="closeViewModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                          <line x1="18" y1="6" x2="6" y2="18"></line>
                          <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="modal-body detail-modal-body">
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Tên tiện ích</span>
                            <span class="detail-value font-normal text-lg">{{ viewItem.name }}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Trạng thái</span>
                            <span class="status-badge" :class="statusClass(viewItem.status)" style="width: fit-content;">
                                {{ statusText(viewItem.status) }}
                            </span>
                        </div>
                        <div class="detail-item full-width">
                            <span class="detail-label">Người gửi</span>
                            <span class="detail-value">{{ viewItem.created_by ? viewItem.created_by.full_name : 'Hệ thống' }}</span>
                        </div>
                        <div class="detail-item full-width">
                            <span class="detail-label">Mô tả</span>
                            <div class="detail-desc-box">
                                {{ viewItem.description || 'Không có mô tả' }}
                            </div>
                        </div>
                        <div v-if="viewItem.status === 'rejected' && viewItem.status_reason" class="detail-item full-width reject-reason-box">
                            <span class="detail-label required">Lý do từ chối</span>
                            <span class="detail-value">{{ viewItem.status_reason }}</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" @click="closeViewModal">
                        Đóng
                    </button>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
            <div class="modal card">
                <div class="modal-header">
                    <h3>
                        {{ editingId ? "Cập nhật tiện ích" : "Thêm tiện ích mới" }}
                    </h3>
                    <button class="btn-close" @click="closeModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                          <line x1="18" y1="6" x2="6" y2="18"></line>
                          <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <form @submit.prevent="handleSubmit">
                    <div class="modal-body">
                        <div v-if="modalError" class="alert alert-danger">
                            {{ modalError }}
                        </div>

                        <div class="form-group">
                            <label for="name">
                                Tên tiện ích
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                placeholder="Ví dụ: Wifi, Gửi xe, Tắm nóng lạnh..."
                                required
                            />
                        </div>

                        <div class="form-group">
                            <label for="description">
                                Mô tả tiện ích
                            </label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="form-control"
                                placeholder="Nhập mô tả chi tiết của tiện ích..."
                                rows="3"
                            ></textarea>
                        </div>

                        <div class="form-group">
                            <label>Trạng thái</label>
                            <div class="custom-select-container">
                                <div 
                                    class="custom-select-trigger" 
                                    :class="{ open: isStatusSelectOpen }"
                                    @click="toggleStatusSelect"
                                >
                                    <span class="option-text">{{ formStatusText }}</span>
                                    <AppIcon name="chevronDown" size="14" class="select-arrow-icon" :class="{ rotated: isStatusSelectOpen }" />
                                </div>
                                <div v-if="isStatusSelectOpen" class="custom-select-options-wrapper">
                                    <div 
                                        class="custom-select-option" 
                                        :class="{ active: form.status === 'active' }"
                                        @click="selectFormStatus('active')"
                                    >
                                        <span class="option-text">Đang hoạt động</span>
                                    </div>
                                    <div 
                                        class="custom-select-option" 
                                        :class="{ active: form.status === 'inactive' }"
                                        @click="selectFormStatus('inactive')"
                                    >
                                        <span class="option-text">Tạm khóa</span>
                                    </div>
                                    <div 
                                        v-if="form.status === 'pending_review'"
                                        class="custom-select-option disabled" 
                                        :class="{ active: true }"
                                    >
                                        <span class="option-text">Chờ duyệt</span>
                                    </div>
                                    <div 
                                        v-if="form.status === 'rejected'"
                                        class="custom-select-option disabled" 
                                        :class="{ active: true }"
                                    >
                                        <span class="option-text">Bị từ chối</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-outline"
                            @click="closeModal"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="submitting"
                        >
                            {{ submitting ? "Đang lưu..." : "Lưu lại" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
         <!-- Floating Add Button -->
        <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
            <button class="btn-float-add" @click="openCreateModal">
                <AppIcon name="plus" size="20" />
                <span class="btn-float-text">Thêm tiện ích</span>
            </button>
        </div>
    </div>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import TableActionGroup from "../../components/TableActionGroup.vue";
import SaaSTable from "../../components/ui/SaaSTable.vue";
import { amenityService } from "../../services/amenityService";

export default {
    name: "AdminAmenities",
    components: { ActionIconButton, AppIcon, TableActionGroup, SaaSTable },
    data() {
        return {
            amenities: [],
            tableColumns: [
                { key: "name", label: "Tên tiện ích" },
                { key: "created_by", label: "Người tạo" },
                { key: "status", label: "Trạng thái" },
                { key: "actions", label: "", align: "right" }
            ],
            searchQuery: "",
            statusFilter: "all",
            isDropdownOpen: false,
            statusOptions: [
                { value: 'all', label: 'Tất cả trạng thái' },
                { value: 'active', label: 'Đang hoạt động' },
                { value: 'inactive', label: 'Tạm khóa' },
                { value: 'pending_review', label: 'Chờ duyệt' },
                { value: 'rejected', label: 'Bị từ chối' }
            ],
            loading: true,
            error: null,
            showModal: false,
            showViewModal: false,
            viewItem: null,
            editingId: null,
            submitting: false,
            modalError: null,
            form: {
                name: "",
                description: "",
                status: "active",
            },
            showScrollTop: false,
            isStatusSelectOpen: false,
        };
    },
    computed: {
        currentStatusLabel() {
            const opt = this.statusOptions.find(o => o.value === this.statusFilter);
            return opt ? opt.label : 'Tất cả trạng thái';
        },
        filteredAmenities() {
            let result = this.amenities;

            if (this.statusFilter !== 'all') {
                result = result.filter(item => item.status === this.statusFilter);
            }

            if (this.searchQuery.trim()) {
                const q = this.searchQuery.trim().toLowerCase();
                result = result.filter(item => 
                    item.name.toLowerCase().includes(q) || 
                    (item.description && item.description.toLowerCase().includes(q))
                );
            }

            return result;
        },
        formStatusText() {
            switch (this.form.status) {
                case 'active': return 'Đang hoạt động';
                case 'inactive': return 'Tạm khóa';
                case 'pending_review': return 'Chờ duyệt';
                case 'rejected': return 'Bị từ chối';
                default: return '';
            }
        }
    },
    methods: {
        selectStatus(val) {
            this.statusFilter = val;
            this.isDropdownOpen = false;
        },
        async fetchAmenities() {
            this.loading = true;
            this.error = null;
            try {
                const res = await amenityService.getAll(false); // lấy tất cả tiện ích
                this.amenities = res.data || [];
            } catch (err) {
                this.error = err.message || "Lỗi khi tải danh sách tiện ích.";
            } finally {
                this.loading = false;
            }
        },
        openViewModal(item) {
            this.viewItem = item;
            this.showViewModal = true;
        },
        closeViewModal() {
            this.showViewModal = false;
            this.viewItem = null;
        },
        openCreateModal() {
            this.editingId = null;
            this.modalError = null;
            this.form = {
                name: "",
                description: "",
                status: "active",
            };
            this.showModal = true;
        },
        openEditModal(item) {
            this.editingId = item.id;
            this.modalError = null;
            this.form = {
                name: item.name,
                description: item.description || "",
                status: item.status || "active",
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.editingId = null;
            this.modalError = null;
            this.isStatusSelectOpen = false;
        },
        toggleStatusSelect() {
            this.isStatusSelectOpen = !this.isStatusSelectOpen;
        },
        selectFormStatus(status) {
            this.form.status = status;
            this.isStatusSelectOpen = false;
        },
        async handleSubmit() {
            this.submitting = true;
            this.modalError = null;
            try {
                if (this.editingId) {
                    await amenityService.update(this.editingId, this.form);
                } else {
                    await amenityService.create(this.form);
                }
                await this.fetchAmenities();
                this.closeModal();
            } catch (err) {
                this.modalError = err.message || "Lỗi lưu thông tin.";
            } finally {
                this.submitting = false;
            }
        },
        async confirmDelete(item) {
            if (confirm(`Bạn có chắc chắn muốn xóa tiện ích "${item.name}" không?`)) {
                try {
                    await amenityService.delete(item.id);
                    await this.fetchAmenities();
                } catch (err) {
                    alert(err.message || "Không thể xóa tiện ích.");
                }
            }
        },
        statusClass(status) {
            switch (status) {
                case 'active': return 'status-active';
                case 'pending_review': return 'status-pending';
                case 'rejected': return 'status-rejected';
                case 'inactive': return 'status-inactive';
                default: return '';
            }
        },
        statusText(status) {
            switch (status) {
                case 'active': return 'Đang hoạt động';
                case 'pending_review': return 'Chờ duyệt';
                case 'rejected': return 'Từ chối';
                case 'inactive': return 'Tạm khóa';
                default: return status;
            }
        },
        async handleApprove(item) {
            if (confirm(`Bạn có chắc chắn muốn duyệt tiện ích "${item.name}" không?`)) {
                try {
                    await amenityService.review(item.id, { status: 'active' });
                    await this.fetchAmenities();
                } catch (err) {
                    alert(err.message || "Không thể duyệt tiện ích.");
                }
            }
        },
        async handleReject(item) {
            const reason = prompt(`Nhập lý do từ chối tiện ích "${item.name}":`);
            if (reason === null) return;
            const trimmed = reason.trim();
            if (!trimmed) {
                alert("Lý do từ chối không được để trống.");
                return;
            }
            try {
                await amenityService.review(item.id, { status: 'rejected', status_reason: trimmed });
                await this.fetchAmenities();
            } catch (err) {
                alert(err.message || "Không thể từ chối tiện ích.");
            }
        },
        resetFilters() {
            this.searchQuery = "";
            this.statusFilter = "all";
        },
        handleScroll() {
            this.showScrollTop = window.scrollY > 250;
        },
    },
    created() {
        this.fetchAmenities();
    },
    mounted() {
        window.addEventListener('scroll', this.handleScroll);
    },
    beforeUnmount() {
        window.removeEventListener('scroll', this.handleScroll);
    },
};
</script>

<style scoped>
.amenities-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 1000px;
    width: 100%;
    min-width: 0;
    margin: 0 auto;
    box-sizing: border-box;
}

.page-title {
    font-size: 20px;
    font-weight: 400;
    margin: 0;
    color: var(--sg-text, #0f172a);
}

.header-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.avc-filters {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
    box-sizing: border-box;
}
.filter-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    width: 100%;
    min-width: 0;
    max-width: 100%;
}
.filter-tabs {
    display: flex;
    gap: 6px;
}
.avc-filters .filter-tabs button.tab-btn {
    height: 38px !important;
    min-height: 38px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 16px !important;
    border-radius: 8px !important;
    border: 1px solid #cbd5e1 !important;
    background: var(--admin-surface) !important;
    color: #475569 !important;
    font-size: 13px !important;
    font-weight: 400 !important;
    cursor: pointer !important;
    transition: all 0.18s !important;
    box-sizing: border-box !important;
}
.avc-filters .filter-tabs button.tab-btn.active {
    background: var(--admin-primary) !important;
    border-color: var(--admin-primary) !important;
    color: #fff !important;
}
.avc-filters .filter-tabs button.tab-btn:not(.active).never-hover-class-placeholder {
    background: var(--admin-hover) !important;
    color: var(--admin-primary-dark) !important;
}
[data-theme="dark"] .avc-filters .filter-tabs button.tab-btn {
    border: 1px solid var(--admin-border) !important;
    color: var(--admin-muted) !important;
}
.filter-search {
    flex: 1;
    min-width: 250px;
}
.avc-filters .filter-row input.search-input {
    width: 100% !important;
    height: 38px !important;
    min-height: 38px !important;
    padding: 0 14px !important;
    border: 1px solid var(--admin-border) !important;
    border-radius: 8px !important;
    font-size: 14px !important;
    outline: none !important;
    color: var(--admin-text) !important;
    background: var(--admin-surface) !important;
    transition: border-color 0.18s !important;
    box-sizing: border-box !important;
}
.avc-filters .filter-row input.search-input:focus {
    border-color: var(--admin-primary) !important;
    box-shadow: 0 0 0 3px var(--admin-primary-ring) !important;
}

.card {
    border-radius: 12px;
    padding: 24px;
}

.loading-state, .error-state, .empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 24px;
    gap: 16px;
    text-align: center;
}

.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid rgba(0, 0, 0, 0.05);
    border-top-color: #0f172a;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 400;
    font-size: 14px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s ease;
}

/* SaaS Table cell custom layout styles */
.name-col-cell, .owner-col-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.amenity-name-text {
    font-size: 13.5px;
    font-weight: 500;
    color: var(--admin-text, #0f172a);
}

.amenity-desc-text {
    font-size: 11.5px;
    color: var(--admin-faint, #64748b);
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.owner-name-text {
    font-size: 13px;
    font-weight: 500;
    color: var(--admin-text, #0f172a);
}

.owner-email-text {
    font-size: 11px;
    color: var(--admin-faint, #64748b);
}

.status-badge {
    display: inline-flex;
    align-items: center;
    font-size: 13px;
    font-weight: 500;
    padding: 0 !important;
    border-radius: 0 !important;
    background-color: transparent !important;
}

/* Status coloring mapping */
.status-is-pending_review {
    color: var(--admin-warning) !important;
}

.status-is-active {
    color: var(--admin-primary-dark) !important;
}

/* Ensure readability/contrast for active status in dark mode */
[data-theme="dark"] .status-is-active {
    color: #34d399 !important;
}

.status-is-inactive, .status-is-rejected {
    color: var(--admin-danger-text, var(--admin-danger)) !important;
}

.table-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.btn-primary {
    background: #0f172a;
    color: #fff;
}

.btn-primary.never-hover-class-placeholder {
    background: #1e293b;
}

.btn-outline {
    border: 1px solid var(--sg-border, #e2e8f0);
    background: transparent;
    color: var(--sg-text, #0f172a);
}

.btn-outline.never-hover-class-placeholder {
    background: #f8fafc;
}

/* SaaS Compact Rows View */
.amenities-list-wrapper {
    display: flex;
    flex-direction: column;
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

.amenities-list-wrapper :deep(.saas-table-container),
.amenities-list-wrapper :deep(.saas-table-scroll) {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

.amenities-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}





/* Modal */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    display: grid;
    place-items: center;
    z-index: 999;
}

.modal {
    width: min(450px, 95vw);
    padding: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--sg-border, #e2e8f0);
}

.modal-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 400;
}

.btn-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #64748b;
}

.modal-body {
    padding: 20px;
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
    font-weight: 400;
}

.form-control {
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid var(--sg-border, #e2e8f0);
    outline: none;
    font-size: 14px;
    box-sizing: border-box;
    height: 42px;
}

.form-control:focus {
    border-color: #0f172a;
}

.checkbox-group {
    margin-top: 4px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 400;
}

.checkbox-label input {
    width: 16px;
    height: 16px;
    accent-color: #0f172a;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 20px;
    border-top: 1px solid var(--sg-border, #e2e8f0);
    background: #f8fafc;
}

.alert-danger {
    padding: 10px 14px;
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 400;
}

.required {
    color: #ef4444;
}

.text-center {
    text-align: center;
}

.detail-modal-body {
    padding: 24px;
}
.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.detail-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.detail-item.full-width {
    grid-column: 1 / -1;
}
.detail-label {
    font-size: 12px;
    font-weight: 400;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.detail-value {
    font-size: 15px;
    color: #0f172a;
    line-height: 1.5;
}
.text-lg {
    font-size: 16px;
}
.detail-desc-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    color: #334155;
    line-height: 1.6;
}
.reject-reason-box {
    background: #fef2f2;
    border: 1px solid #fecaca;
    padding: 12px 16px;
    border-radius: 8px;
    margin-top: 4px;
}
.reject-reason-box .detail-label {
    color: #991b1b;
}
.reject-reason-box .detail-value {
    color: #991b1b;
    font-weight: 500;
}

/* Responsive Styles for SaaS Rows */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .filter-tabs {
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 4px;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        gap: 8px;
        -webkit-overflow-scrolling: touch;
    }
    
    .tab-btn {
        flex: 0 0 auto;
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .search-input {
        width: 100%;
        min-width: 0;
        box-sizing: border-box !important;
    }

    .hide-on-tablet {
        display: none;
    }

    .amenity-row-item {
        height: auto;
        padding: 12px 14px;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .accent-line {
        top: 0;
        bottom: 0;
        width: 3px;
        height: auto;
    }

    .row-middle {
        padding: 0;
        justify-content: space-between;
    }

    .row-right {
        opacity: 1;
        transform: none;
        justify-content: flex-end;
        border-top: 1px dashed rgba(15, 23, 42, 0.05);
        padding-top: 8px;
    }
}

/* Floating Add Button */
.floating-add-container {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 9998;
    transition: right 0.25s ease;
}
.floating-add-container.has-scroll {
    right: 86px;
}
.btn-float-add {
    width: 44px;
    height: 44px;
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #10b981;
    color: #fff;
    border: none;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    white-space: nowrap;
    padding: 0 12px;
}
.btn-float-add .btn-float-text {
    max-width: 0;
    opacity: 0;
    margin-left: 0;
    font-weight: 400;
    font-size: 13px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-block;
}
.btn-float-add.never-hover-class-placeholder {
    width: 145px;
    justify-content: flex-start;
    padding-left: 14px;
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
    background-color: #059669;
}
.btn-float-add.never-hover-class-placeholder .btn-float-text {
    max-width: 100px;
    opacity: 1;
    margin-left: 6px;
}
@media (max-width: 768px) {
    .floating-add-container {
        bottom: 20px;
        right: 20px;
    }
    .floating-add-container.has-scroll {
        right: 72px;
    }
    .btn-float-add {
        width: 40px;
        height: 40px;
        border-radius: 20px;
        padding: 0 10px;
    }
    .btn-float-add.never-hover-class-placeholder {
        width: 130px;
        padding-left: 12px;
    }
    .btn-float-add.never-hover-class-placeholder .btn-float-text {
        max-width: 80px;
    }
}

/* Custom Select Dropdown */
.custom-select-container {
    position: relative;
    width: 100%;
    user-select: none;
}

.custom-select-trigger {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--sg-border, #e2e8f0);
    background: var(--sg-surface, #ffffff);
    font-size: 14px;
    color: var(--sg-text, #101c15);
    cursor: pointer;
    transition: all 0.2s ease;
    height: 42px;
    box-sizing: border-box;
}

.custom-select-trigger:focus-within,
.custom-select-trigger.open {
    border-color: #0f172a;
}

.select-arrow-icon {
    width: 16px;
    height: 16px;
    color: rgba(15, 23, 42, 0.4);
    transition: transform 0.2s ease;
}

.select-arrow-icon.rotated {
    transform: rotate(180deg);
}

.custom-select-options-wrapper {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: var(--sg-surface, #ffffff);
    border: 1px solid var(--sg-border, #e2e8f0);
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    z-index: 1010;
    max-height: 220px;
    overflow-y: auto;
    padding: 4px;
    animation: slideDown 0.15s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.custom-select-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 14px;
    color: var(--sg-text, #101c15) !important;
    cursor: pointer;
    transition: background 0.15s ease;
}

.custom-select-option:hover {
    background: rgba(0, 0, 0, 0.03) !important;
}

.custom-select-option.active {
    background: rgba(15, 23, 42, 0.05) !important;
    color: #0f172a !important;
    font-weight: 400;
}

.custom-select-option.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}
</style>

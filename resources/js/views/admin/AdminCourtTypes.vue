<template>
    <div class="court-types-container">

        <!-- Loading State -->
        <div v-if="loading" class="loading-state card">
            <div class="spinner"></div>
            <p>Đang tải danh sách bộ môn và loại sân...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-state card">
            <p class="error-message">{{ error }}</p>
            <ActionIconButton icon="refresh" label="Thử lại" @click="fetchCourtTypes" />
        </div>

        <template v-else>
            <!-- SaaS Command Bar -->
            <div class="command-bar" v-if="courtTypes.length > 0">
                <div class="search-box">
                    <AppIcon name="search" size="16" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Tìm kiếm nhanh bộ môn hoặc loại sân..."
                        class="search-input"
                    />
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="courtTypes.length === 0" class="empty-state card animate-fade-in">
                <p>Chưa có bộ môn hay loại sân nào được cấu hình trên hệ thống.</p>
                <button class="btn btn-primary" @click="openCreateModal">
                    <AppIcon name="plus" size="17" />
                    <span>Thêm ngay</span>
                </button>
            </div>

            <!-- Views Content Wrapper -->
            <div v-else class="views-content-wrapper animate-fade-in">
                <!-- Grouped Court Types List -->
                <div class="grouped-court-types-list">
                    <div
                        v-for="parent in filteredMainParentTypes"
                        :key="parent.id"
                        class="court-type-group"
                    >
                        <!-- Group Header (Môn thể thao Cha) -->
                        <div class="group-header">
                            <div class="group-header-left">
                                <span class="group-title">{{ parent.name.toUpperCase() }}</span>
                                <span class="group-divider"></span>
                                <span class="group-count">{{ getChildren(parent.id).length }} loại sân</span>
                            </div>
                            <div class="group-header-right">
                                <ActionIconButton
                                    icon="plus"
                                    label="Thêm loại sân con"
                                    size="sm"
                                    @click="openCreateChildModal(parent.id)"
                                />
                                <ActionIconButton
                                    icon="pencil"
                                    label="Sửa bộ môn"
                                    size="sm"
                                    @click="openEditModal(parent)"
                                />
                                <ActionIconButton
                                    icon="trash"
                                    label="Xóa bộ môn"
                                    variant="danger"
                                    size="sm"
                                    @click="confirmDelete(parent)"
                                />
                            </div>
                        </div>

                        <!-- Group Items (Loại sân con) -->
                        <div class="group-items">
                            <div
                                v-for="child in getFilteredChildren(parent.id)"
                                :key="child.id"
                                class="court-type-row-item"
                                :class="{ 'status-inactive': !child.is_active || !parent.is_active }"
                            >
                                <!-- Accent hover line -->
                                <div class="accent-line"></div>

                                <!-- Left: Name & Players count -->
                                <div class="row-left">
                                    <div class="court-type-info">
                                        <span class="court-type-name">{{ child.name }}</span>
                                        <span class="court-type-desc" v-if="child.description">{{ child.description }}</span>
                                    </div>
                                    <span class="players-count-badge">
                                        <AppIcon name="users" size="12" />
                                        <span>{{ child.player_count }} người chơi</span>
                                    </span>
                                </div>

                                <!-- Middle: Default Size & Status -->
                                <div class="row-middle">
                                    <div class="size-spec" v-if="child.default_layout_w && child.default_layout_h">
                                        <AppIcon name="layers" size="13" />
                                        <span>Quy chuẩn: {{ formatToM(child.default_layout_w) }}m x {{ formatToM(child.default_layout_h) }}m</span>
                                    </div>
                                    <span class="row-status-badge" :class="child.is_active ? 'active' : 'inactive'">
                                        {{ child.is_active ? 'Đang hoạt động' : 'Tạm khóa' }}
                                    </span>
                                </div>

                                <!-- Right: Actions -->
                                <div class="row-right">
                                    <ActionIconButton
                                        icon="pencil"
                                        label="Sửa loại sân"
                                        size="sm"
                                        @click="openEditModal(child)"
                                    />
                                    <ActionIconButton
                                        icon="trash"
                                        label="Xóa loại sân"
                                        variant="danger"
                                        size="sm"
                                        @click="confirmDelete(child)"
                                    />
                                </div>
                            </div>

                            <!-- Empty Children State -->
                            <div v-if="getChildren(parent.id).length === 0" class="empty-children-row">
                                Chưa có loại sân con nào thuộc bộ môn này. Click nút "+" ở tiêu đề nhóm để thêm.
                            </div>
                        </div>
                    </div>

                    <!-- Empty Search State -->
                    <div v-if="filteredMainParentTypes.length === 0" class="empty-search-state">
                        <AppIcon name="alert" size="20" />
                        <span>Không tìm thấy bộ môn hoặc loại sân nào phù hợp.</span>
                    </div>
                </div>
            </div>
        </template>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
            <div class="modal card">
                <div class="modal-header">
                    <h3>
                        {{
                            editingId
                                ? (form.parent_id === null ? "Cập nhật môn thể thao" : "Cập nhật loại sân")
                                : (form.parent_id === null ? "Thêm môn thể thao mới" : "Thêm loại sân mới")
                        }}
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
                                {{ form.parent_id === null ? "Tên môn thể thao" : "Tên loại sân" }}
                                <span class="required">*</span>
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                :placeholder="form.parent_id === null ? 'Ví dụ: Bóng đá, Cầu lông, Pickleball...' : 'Ví dụ: Sân 5 người, Sân đơn...'"
                                required
                            />
                        </div>

                        <div v-if="form.parent_id !== null" class="form-group">
                            <label for="parent_id">Thuộc môn thể thao</label>
                            <div class="custom-select-container" ref="customSelect">
                                <div class="custom-select-trigger" @click="toggleDropdown">
                                    <span class="selected-value">{{ selectedParentName }}</span>
                                    <svg 
                                        xmlns="http://www.w3.org/2000/svg" 
                                        viewBox="0 0 24 24" 
                                        fill="none" 
                                        stroke="currentColor" 
                                        stroke-width="2" 
                                        stroke-linecap="round" 
                                        stroke-linejoin="round" 
                                        class="select-arrow-icon"
                                        :class="{ 'rotated': dropdownOpen }"
                                    >
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </div>
                                <div v-if="dropdownOpen" class="custom-select-options-wrapper">
                                    <div 
                                        class="custom-select-option" 
                                        :class="{ active: form.parent_id === null }"
                                        @click="selectOption(null)"
                                    >
                                        <span class="option-badge-root">Bộ môn</span>
                                        <span class="option-text">-- Không chọn (Là môn thể thao độc lập) --</span>
                                    </div>
                                    <div 
                                        v-for="pt in parentTypes" 
                                        :key="pt.id" 
                                        class="custom-select-option" 
                                        :class="{ active: form.parent_id === pt.id }"
                                        @click="selectOption(pt.id)"
                                    >
                                        <span class="option-badge-parent">Môn thể thao</span>
                                        <span class="option-text">{{ pt.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="form.parent_id !== null" class="form-group">
                            <label for="player_count"
                                >Số người chơi tiêu chuẩn
                                <span class="required">*</span></label
                            >
                            <input
                                id="player_count"
                                v-model.number="form.player_count"
                                type="number"
                                min="1"
                                class="form-control"
                                :required="form.parent_id !== null"
                            />
                        </div>

                        <!-- Cấu hình quy chuẩn kích thước sơ đồ trực quan -->
                        <div v-if="form.parent_id !== null" class="form-group">
                            <label>Kích thước sơ đồ quy chuẩn (m)</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input
                                    v-model.number="form.default_layout_w"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    class="form-control"
                                    placeholder="Ngang (ví dụ: 6.1)"
                                    style="flex: 1; min-width: 0;"
                                />
                                <span class="text-muted">x</span>
                                <input
                                    v-model.number="form.default_layout_h"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    class="form-control"
                                    placeholder="Dọc (ví dụ: 13.4)"
                                    style="flex: 1; min-width: 0;"
                                />
                            </div>
                            <small class="text-muted" style="margin-top: 4px; display: block;">
                                Cấu hình kích thước này giúp chủ sân có sẵn thông số chuẩn khi kéo thả sân con vào bản đồ ảo.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="description">Mô tả</label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="form-control"
                                rows="3"
                                :placeholder="form.parent_id === null ? 'Nhập mô tả ngắn về môn thể thao...' : 'Nhập mô tả ngắn về loại sân...'"
                            ></textarea>
                        </div>

                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                />
                                <span>Kích hoạt hoạt động</span>
                            </label>
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
                <span class="btn-float-text">Thêm bộ môn</span>
            </button>
        </div>
    </div>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import { courtTypeService } from "../../services/courtTypes";

export default {
    name: "AdminCourtTypes",
    components: { ActionIconButton, AppIcon },
    data() {
        return {
            courtTypes: [],
            searchQuery: "",
            loading: true,
            error: null,
            showModal: false,
            editingId: null,
            submitting: false,
            modalError: null,
            dropdownOpen: false, // điều khiển custom select dropdown
            form: {
                name: "",
                parent_id: null,
                player_count: 4,
                description: "",
                is_active: true,
                default_layout_w: null,
                default_layout_h: null,
            },
            showScrollTop: false,
        };
    },
    computed: {
        parentTypes() {
            // Lọc danh sách loại sân là gốc (chưa có cha) và không phải chính nó để tránh vòng lặp vô hạn
            return this.courtTypes.filter(
                (type) => !type.parent_id && type.id !== this.editingId
            );
        },
        mainParentTypes() {
            // Chỉ hiển thị các loại sân gốc (Cha) trên bảng chính
            return this.courtTypes.filter(type => !type.parent_id);
        },
        filteredMainParentTypes() {
            if (!this.searchQuery.trim()) {
                return this.mainParentTypes;
            }
            const q = this.searchQuery.trim().toLowerCase();
            return this.mainParentTypes.filter((parent) => {
                const matchParent = parent.name.toLowerCase().includes(q) ||
                    (parent.description && parent.description.toLowerCase().includes(q));
                
                if (matchParent) return true;

                // Kiểm tra xem có loại sân con nào thuộc bộ môn này khớp từ khóa
                const children = this.getChildren(parent.id);
                return children.some((child) => 
                    child.name.toLowerCase().includes(q) || 
                    (child.description && child.description.toLowerCase().includes(q))
                );
            });
        },
        selectedParentName() {
            if (this.form.parent_id === null) {
                return "-- Không chọn (Là môn thể thao độc lập) --";
            }
            const parent = this.courtTypes.find(type => type.id === this.form.parent_id);
            return parent ? parent.name : "-- Không chọn (Là môn thể thao độc lập) --";
        }
    },
    methods: {
        getChildren(parentId) {
            return this.courtTypes.filter(type => type.parent_id === parentId);
        },
        getFilteredChildren(parentId) {
            const children = this.getChildren(parentId);
            if (!this.searchQuery.trim()) {
                return children;
            }
            const q = this.searchQuery.trim().toLowerCase();
            return children.filter((child) => 
                child.name.toLowerCase().includes(q) || 
                (child.description && child.description.toLowerCase().includes(q))
            );
        },
        formatToM(val) {
            if (val === null || val === undefined) return 0;
            return Math.round(val) / 100;
        },
        toggleDropdown() {
            this.dropdownOpen = !this.dropdownOpen;
        },
        selectOption(val) {
            this.form.parent_id = val;
            this.dropdownOpen = false;
        },
        handleClickOutside(event) {
            if (this.$refs.customSelect && !this.$refs.customSelect.contains(event.target)) {
                this.dropdownOpen = false;
            }
        },
        async fetchCourtTypes(silent = false) {
            if (!silent) {
                this.loading = true;
            }
            this.error = null;
            try {
                const res = await courtTypeService.getAll();
                this.courtTypes = res.data || [];
            } catch (err) {
                if (!silent) {
                    this.error = err.message || "Lỗi khi tải danh sách môn thể thao và loại sân.";
                }
            } finally {
                if (!silent) {
                    this.loading = false;
                }
            }
        },
        openCreateModal() {
            this.editingId = null;
            this.modalError = null;
            this.form = {
                name: "",
                parent_id: null,
                player_count: 0,
                description: "",
                is_active: true,
                default_layout_w: null,
                default_layout_h: null,
            };
            this.showModal = true;
        },
        openCreateChildModal(parentId) {
            this.editingId = null;
            this.modalError = null;
            this.form = {
                name: "",
                parent_id: parentId,
                player_count: 4,
                description: "",
                is_active: true,
                default_layout_w: null,
                default_layout_h: null,
            };
            this.showModal = true;
        },
        openEditModal(type) {
            this.editingId = type.id;
            this.modalError = null;
            this.form = {
                name: type.name,
                parent_id: type.parent_id || null,
                player_count: type.player_count,
                description: type.description || "",
                is_active: !!type.is_active,
                default_layout_w: type.default_layout_w ? type.default_layout_w / 100 : null,
                default_layout_h: type.default_layout_h ? type.default_layout_h / 100 : null,
            };
            this.showModal = true;
        },
        closeModal() {
            this.showModal = false;
            this.editingId = null;
            this.modalError = null;
        },
        async handleSubmit() {
            this.submitting = true;
            this.modalError = null;
            
            // Nếu là bộ môn thể thao độc lập (cha), gán player_count mặc định là 0 để qua validation backend
            if (this.form.parent_id === null) {
                this.form.player_count = 0;
            }
            
            const payload = {
                ...this.form,
                default_layout_w: (this.form.parent_id !== null && this.form.default_layout_w) ? parseFloat(this.form.default_layout_w) * 100 : null,
                default_layout_h: (this.form.parent_id !== null && this.form.default_layout_h) ? parseFloat(this.form.default_layout_h) * 100 : null,
            };
            
            try {
                if (this.editingId) {
                    await courtTypeService.update(this.editingId, payload);
                } else {
                    await courtTypeService.create(payload);
                }
                await this.fetchCourtTypes();
                this.closeModal();
            } catch (err) {
                this.modalError = err.message || "Lỗi lưu thông tin.";
            } finally {
                this.submitting = false;
            }
        },
        async confirmDelete(type) {
            const isParent = !type.parent_id;
            const msg = isParent 
                ? `Bạn có chắc chắn muốn xóa môn thể thao "${type.name}" không? Toàn bộ các loại sân con trực thuộc môn thể thao này cũng sẽ bị ảnh hưởng.`
                : `Bạn có chắc chắn muốn xóa loại sân "${type.name}" không?`;
            if (confirm(msg)) {
                try {
                    await courtTypeService.delete(type.id);
                    await this.fetchCourtTypes();
                } catch (err) {
                    alert(err.message || "Không thể xóa.");
                }
            }
        },
        handleScroll() {
            this.showScrollTop = window.scrollY > 250;
        }
    },
    created() {
        this.fetchCourtTypes();
    },
    mounted() {
        document.addEventListener("click", this.handleClickOutside);
        window.addEventListener('scroll', this.handleScroll);
    },
    beforeUnmount() {
        document.removeEventListener("click", this.handleClickOutside);
        window.removeEventListener('scroll', this.handleScroll);
    }
};
</script>

<style scoped>
.court-types-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 1000px;
    width: 100%;
    margin: 0 auto;
    box-sizing: border-box;
}

.command-bar {
    width: 100%;
    margin-bottom: 4px;
}

.views-content-wrapper {
    width: 100%;
}

.grouped-court-types-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.court-type-group {
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.04);
    border-radius: 12px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.01);
}

.group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(15, 23, 42, 0.04);
    padding-bottom: 10px;
}

.group-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
}

.group-title {
    font-size: 13px;
    font-weight: 800;
    color: var(--admin-text, #0f172a);
    letter-spacing: 0.5px;
}

.group-divider {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.15);
}

.group-count {
    font-size: 12px;
    color: var(--admin-faint, #64748b);
    font-weight: 600;
}

.group-header-right {
    display: flex;
    align-items: center;
    gap: 6px;
}

.group-items {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.court-type-row-item {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 52px;
    padding: 10px 16px;
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.03);
    border-radius: 8px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.court-type-row-item:hover {
    background: rgba(15, 23, 42, 0.01);
    border-color: rgba(15, 23, 42, 0.06);
    transform: translateX(2px);
}

.accent-line {
    position: absolute;
    left: 0;
    top: 15%;
    bottom: 15%;
    width: 2.5px;
    background: var(--admin-primary, #10b981);
    border-radius: 0 2px 2px 0;
    opacity: 0;
    transform: scaleY(0.7);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.court-type-row-item:hover .accent-line {
    opacity: 1;
    transform: scaleY(1);
}

.row-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1.2;
    min-width: 0;
}

.court-type-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.court-type-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--admin-text, #0f172a);
    transition: opacity 0.2s ease;
}

.court-type-desc {
    font-size: 12px;
    color: var(--admin-faint, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 320px;
}

.court-type-row-item.status-inactive .court-type-name {
    opacity: 0.5;
}

.players-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    background: rgba(15, 23, 42, 0.04);
    color: rgba(15, 23, 42, 0.6);
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 600;
    white-space: nowrap;
}

.row-middle {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 24px;
    flex: 1;
    padding-right: 16px;
}

.size-spec {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: var(--admin-faint, #64748b);
    font-size: 12.5px;
    font-weight: 500;
}

.row-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
}

.row-status-badge.active {
    background: var(--admin-primary-soft, rgba(16, 185, 129, 0.1)) !important;
    color: var(--admin-primary-dark, #047857) !important;
}

.row-status-badge.inactive {
    background: var(--admin-surface-muted, #f1f5f9) !important;
    color: var(--admin-muted, #475569) !important;
}

.row-right {
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0;
    transform: translateX(6px);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.court-type-row-item:hover .row-right {
    opacity: 1;
    transform: translateX(0);
}

.empty-children-row {
    padding: 14px;
    text-align: center;
    font-size: 13px;
    color: var(--admin-faint, #64748b);
    background: rgba(15, 23, 42, 0.01);
    border: 1px dashed rgba(15, 23, 42, 0.04);
    border-radius: 8px;
}

.empty-search-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 16px;
    gap: 12px;
    color: var(--admin-faint, #64748b);
    font-size: 14px;
    font-weight: 600;
    text-align: center;
}

/* Card base */
.card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid var(--sg-border);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    padding: 24px;
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

/* Modal styling */
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
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
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

.checkbox-group {
    margin-top: 8px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-weight: 700;
    font-size: 14px;
    color: var(--sg-text);
}

.checkbox-label input {
    width: 18px;
    height: 18px;
    accent-color: #000000;
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
    border: 1px solid var(--sg-border);
    background: #fff;
    font-size: 14px;
    color: var(--sg-text);
    cursor: pointer;
    transition: all 0.2s ease;
}

.custom-select-trigger:hover {
    border-color: #000;
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
    background: #fff;
    border: 1px solid var(--sg-border);
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
    color: var(--sg-text);
    cursor: pointer;
    transition: background 0.15s ease;
}

.custom-select-option:hover {
    background: rgba(0, 0, 0, 0.03);
}

.custom-select-option.active {
    background: rgba(0, 0, 0, 0.05);
    font-weight: 700;
}

.option-badge-root,
.option-badge-parent {
    font-size: 10px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
}

.option-badge-root {
    background: rgba(0, 0, 0, 0.06);
    color: rgba(0, 0, 0, 0.6);
}

.option-badge-parent {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}

.option-text {
    flex-grow: 1;
}

/* Animations */
.animate-fade-in {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
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
    font-weight: 700;
    font-size: 13px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-block;
}

.btn-float-add:hover {
    width: 145px;
    justify-content: flex-start;
    padding-left: 14px;
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
    background-color: #059669;
}

.btn-float-add:hover .btn-float-text {
    max-width: 100px;
    opacity: 1;
    margin-left: 6px;
}

/* Theme overrides */
.court-types-container .custom-select-option:hover {
    background: rgba(232, 247, 236, 0.68);
}

.court-types-container .custom-select-option.active {
    border-color: rgba(47, 158, 68, 0.18);
    background: rgba(47, 158, 68, 0.12);
    color: var(--admin-primary-dark);
}

.court-types-container .custom-select-trigger:hover,
.court-types-container .custom-select-trigger:focus-within {
    border-color: rgba(47, 158, 68, 0.62);
    box-shadow: 0 0 0 3px rgba(47, 158, 68, 0.14);
}

.court-types-container .btn-add-child,
.court-types-container .option-badge-parent {
    color: var(--admin-primary-dark);
}

/* Loading & Error States */
.loading-state,
.error-state,
.empty-state {
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
    width: 32px;
    height: 32px;
    border: 3px solid rgba(0, 0, 0, 0.05);
    border-top-color: #000000;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}


/* ==========================================
   RESPONSIVE DESIGN (MOBILE & TABLET)
   ========================================== */

@media (max-width: 1024px) {
    .court-types-container {
        gap: 16px;
        padding: 0 4px;
    }
}

@media (max-width: 768px) {
    .court-type-row-item {
        flex-direction: column;
        align-items: stretch;
        padding: 14px;
        gap: 10px;
    }

    .row-left {
        width: 100%;
        justify-content: space-between;
        gap: 12px;
    }

    .court-type-desc {
        max-width: 100%;
    }

    .row-middle {
        width: 100%;
        justify-content: space-between;
        padding-right: 0;
        gap: 12px;
        border-top: 1px dashed rgba(15, 23, 42, 0.04);
        padding-top: 8px;
    }

    .row-right {
        position: absolute;
        top: 10px;
        right: 10px;
        opacity: 1;
        transform: none;
    }

    .court-type-row-item:hover .row-right {
        transform: none;
    }
}

@media (max-width: 576px) {
    .modal-backdrop {
        padding: 10px;
    }

    .modal {
        max-height: 95vh;
        display: flex;
        flex-direction: column;
    }

    .modal-header,
    .modal-footer {
        padding: 14px 16px;
    }

    .modal-header h3 {
        font-size: 16px;
    }

    .modal-body {
        padding: 16px;
        overflow-y: auto;
        max-height: calc(95vh - 120px);
        gap: 12px;
    }

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

    .btn-float-add:hover {
        width: 130px;
        padding-left: 12px;
    }

    .btn-float-add:hover .btn-float-text {
        max-width: 80px;
    }
}
</style>

<template>
    <div class="venue-courts-container">
        <!-- Floating Add Button -->
        <div class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
            <button class="btn-float-add" type="button" :disabled="!cluster" @click="openCreateModal" title="Thêm sân con">
                <AppIcon name="plus" size="20" />
                <span class="btn-float-text">Thêm sân con</span>
            </button>
        </div>

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
            <button class="btn btn-primary" @click="openCreateModal">
                Thêm sân con ngay
            </button>
        </div>

        <div v-else class="view-content-wrapper">
            <!-- Grid List of Courts (SaaS Table View) -->
            <div class="courts-list-wrapper">
                <!-- ── Bộ lọc & Ô tìm kiếm (SaaS Command Bar) ── -->
                <div class="avc-filters animate-fade-in">
                    <div class="filter-row">
                        <div class="filter-tabs">
                            <button
                                v-for="tab in statusTabs"
                                :key="tab.value"
                                class="tab-btn"
                                :class="{ active: filterStatus === tab.value }"
                                @click="filterStatus = tab.value"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                        <div class="filter-search">
                            <div class="search-box">
                                <AppIcon name="search" size="16" />
                                <input
                                    id="search-court-name"
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Tìm kiếm tên sân con hoặc loại sân..."
                                    class="search-input"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Empty State khi tìm kiếm không ra kết quả ── -->
                <div v-if="filteredCourts.length === 0" class="state-box card animate-fade-in" style="text-align: center; padding: 40px 0; background: transparent; border: none; box-shadow: none;">
                    <p class="empty-msg" style="color: rgba(15, 23, 42, 0.5); font-size: 14px; margin-bottom: 12px;">Không tìm thấy sân con nào phù hợp.</p>
                    <button class="btn btn-outline" @click="searchQuery = ''; filterStatus = ''">
                        Xóa bộ lọc
                    </button>
                </div>

                <!-- ── Elegant SaaS Table View ── -->
                <div v-else class="courts-table-wrapper animate-fade-in">
                    <SaaSTable 
                        :columns="tableColumns" 
                        :data="filteredCourts" 
                        clickable 
                        @row-click="row => openEditModal(row)"
                    >
                        <!-- Tên sân con (hiển thị sort_order + tên) -->
                        <template #name="{ row }">
                            <div class="name-col-cell" style="display: flex; align-items: center; gap: 8px;">
                                <span class="court-order-text" style="font-family: monospace; font-size: 12px; color: var(--admin-faint);">#{{ row.sort_order }}</span>
                                <span class="court-name-text" style="font-weight: 400; color: var(--sg-text);">{{ row.name }}</span>
                            </div>
                        </template>

                        <!-- Loại sân -->
                        <template #court_type="{ row }">
                            <span class="court-type-text" style="font-size: 13px; color: var(--sg-text);">
                                {{ row.court_type?.name || 'Khác' }}
                            </span>
                        </template>

                        <!-- Trạng thái hoạt động -->
                        <template #status="{ row }">
                            <span class="status-badge" :class="'state-is-' + row.status">
                                {{ formatStatus(row.status) }}
                            </span>
                        </template>

                        <!-- Action Column -->
                        <template #actions="{ row }">
                            <div class="table-actions" @click.stop style="display: flex; gap: 6px; justify-content: flex-end;">
                                <ActionIconButton
                                    icon="pencil"
                                    label="Sửa sân con"
                                    size="sm"
                                    @click="openEditModal(row)"
                                />
                                <ActionIconButton
                                    icon="trash"
                                    label="Xóa sân con"
                                    variant="danger"
                                    size="sm"
                                    @click="confirmDelete(row)"
                                />
                            </div>
                        </template>
                    </SaaSTable>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="modal-backdrop" @click.self="closeModal">
            <div class="modal card">
                <div class="modal-header">
                    <h3>
                        {{
                            editingId ? "Cập nhật sân con" : "Thêm sân con mới"
                        }}
                    </h3>
                    <button class="btn-close" @click="closeModal">
                        <AppIcon name="x" size="18" />
                    </button>
                </div>
                <form @submit.prevent="handleSubmit">
                    <div class="modal-body">
                        <div v-if="modalError" class="alert alert-danger">
                            {{ modalError }}
                        </div>

                        <div v-if="!editingId" class="alert alert-info" style="margin-bottom: 15px; font-size: 13px; line-height: 1.5;">
                            💡 <strong>Lưu ý:</strong> Việc thêm sân con mới cần được Admin phê duyệt. Yêu cầu phê duyệt sẽ được gửi tự động sau khi bạn lưu.
                        </div>

                        <div class="form-group">
                            <label for="court-name"
                                >Tên sân con
                                <span class="required">*</span></label
                            >
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
                            <label
                                >Loại sân <span class="required">*</span></label
                            >
                            <div class="custom-select-wrapper">
                                <div
                                    class="custom-select-trigger"
                                    :class="{ active: showTypeDropdown }"
                                    @click.stop="
                                        showTypeDropdown = !showTypeDropdown
                                    "
                                >
                                    <span v-if="selectedCourtType">
                                        <span class="parent-name">{{
                                            getParentTypeName(selectedCourtType)
                                        }}</span>
                                        <span class="separator">/</span>
                                        <span class="child-name"
                                            >{{ selectedCourtType.name }} ({{
                                                selectedCourtType.player_count
                                            }}
                                            người)</span
                                        >
                                    </span>
                                    <span v-else class="placeholder"
                                        >-- Chọn loại sân --</span
                                    >
                                    <span class="arrow">&#9662;</span>
                                </div>
                                <div
                                    v-if="showTypeDropdown"
                                    class="custom-options-container"
                                >
                                    <div
                                        v-for="group in groupedCourtTypes"
                                        :key="group.id"
                                        class="custom-optgroup"
                                    >
                                        <div class="custom-optgroup-label">
                                            {{ group.name }}
                                        </div>
                                        <div
                                            v-for="child in group.children"
                                            :key="child.id"
                                            class="custom-option"
                                            :class="{
                                                selected:
                                                    form.court_type_id ===
                                                    child.id,
                                            }"
                                            @click="selectCourtType(child)"
                                        >
                                            <span class="option-text">{{
                                                child.name
                                            }}</span>
                                            <span class="option-details"
                                                >({{
                                                    child.player_count
                                                }}
                                                người)</span
                                            >
                                            <span
                                                v-if="
                                                    form.court_type_id ===
                                                    child.id
                                                "
                                                class="check-mark"
                                                >&#10003;</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-row-2col" style="margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div v-if="editingId" class="form-group" style="margin: 0;">
                                <label for="court-status"
                                    >Trạng thái sân
                                    <span class="required">*</span></label
                                >
                                <BaseCombobox
                                    id="court-status"
                                    v-model="form.status"
                                    :options="courtStatusOptions"
                                    placeholder="Chọn trạng thái"
                                />
                            </div>

                            <div class="form-group" style="margin: 0;">
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

        <!-- Modal xử lý booking trùng khi đổi trạng thái sân con -->
        <CourtConflictResolutionModal
            v-model="showConflictModal"
            :court-name="form.name"
            :target-status="form.status"
            :conflicts="conflictData"
            :loading="conflictLoading"
            @confirm="handleConfirmConflictResolution"
            @cancel="showConflictModal = false"
        />
    </div>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import BaseCombobox from "../../components/BaseCombobox.vue";
import SaaSTable from "../../components/ui/SaaSTable.vue";
import CourtConflictResolutionModal from "../../components/owner/CourtConflictResolutionModal.vue";
import { venueClusterService } from "../../services/venueClusters";
import { courtTypeService } from "../../services/courtTypes";
import { useToast } from "vue-toastification";

export default {
    name: "OwnerVenueCourts",
    components: { ActionIconButton, AppIcon, SaaSTable, BaseCombobox, CourtConflictResolutionModal },
    data() {
        return {
            clusterId:
                this.$route.query.venue_cluster_id ||
                localStorage.getItem("selected_cluster") ||
                "",
            cluster: null,
            courts: [],
            courtTypes: [],
            loading: true,
            error: null,
            showModal: false,
            editingId: null,
            originalCourtStatus: null,
            showConflictModal: false,
            conflictData: { affected_count: 0, items: [] },
            conflictLoading: false,
            submitting: false,
            modalError: null,
            form: {
                name: "",
                court_type_id: "",
                status: "active",
                sort_order: 1,
            },
            showTypeDropdown: false,
            searchQuery: "",
            filterStatus: "",
            statusTabs: [
                { value: "", label: "Tất cả" },
                { value: "active", label: "Hoạt động" },
                { value: "inactive", label: "Tạm ngưng" },
                { value: "maintenance", label: "Bảo trì" }
            ],
            tableColumns: [
                { key: "name", label: "Tên sân con" },
                { key: "court_type", label: "Loại sân" },
                { key: "status", label: "Trạng thái hoạt động" },
                { key: "actions", label: "", align: "right" }
            ],
            showScrollTop: false,
        };
    },
    computed: {
        courtStatusOptions() {
            return [
                { value: 'active', label: 'Đang hoạt động' },
                { value: 'inactive', label: 'Tạm ngưng hoạt động' },
                { value: 'maintenance', label: 'Bảo trì' },
            ];
        },
        groupedCourts() {
            const filtered = this.courts.filter((c) => {
                if (!this.searchQuery) return true;
                return c.name.toLowerCase().includes(this.searchQuery.toLowerCase());
            });

            const groups = {};
            filtered.forEach((court) => {
                const typeName = court.court_type?.name || "Khác";
                if (!groups[typeName]) {
                    groups[typeName] = [];
                }
                groups[typeName].push(court);
            });

            return Object.keys(groups).map((typeName) => {
                return {
                    typeName,
                    courts: groups[typeName].sort((a, b) => a.sort_order - b.sort_order),
                };
            });
        },
        filteredCourts() {
            let list = this.courts;
            if (this.filterStatus) {
                list = list.filter((c) => c.status === this.filterStatus);
            }
            if (this.searchQuery.trim()) {
                const q = this.searchQuery.trim().toLowerCase();
                list = list.filter(
                    (c) =>
                        c.name.toLowerCase().includes(q) ||
                        (c.court_type?.name || "").toLowerCase().includes(q)
                );
            }
            return list.sort((a, b) => a.sort_order - b.sort_order);
        },
        selectedCourtType() {
            return this.courtTypes.find(
                (t) => t.id === this.form.court_type_id,
            );
        },
        groupedCourtTypes() {
            // Tìm các danh mục cha (parent_id là null)
            const parents = this.courtTypes.filter((t) => !t.parent_id);

            const groups = parents.map((parent) => {
                return {
                    id: parent.id,
                    name: parent.name,
                    // Lọc danh sách con thuộc cha này
                    children: this.courtTypes.filter(
                        (t) => t.parent_id === parent.id,
                    ),
                };
            });

            // Chỉ hiển thị các nhóm bộ môn có cấu hình sân con
            return groups.filter((g) => g.children.length > 0);
        },
    },
    methods: {
        async initData() {
            this.loading = true;
            this.error = null;
            try {
                if (!this.clusterId) {
                    const clustersRes = await venueClusterService.getClusters({ compact: 1 });
                    this.clusterId = clustersRes.data?.[0]?.id || "";
                }

                if (!this.clusterId) {
                    throw new Error("Thiếu mã cụm sân (venue_cluster_id).");
                }

                localStorage.setItem("selected_cluster", this.clusterId);

                // Tải chi tiết cụm sân
                const clusterRes = await venueClusterService.getClusterDetails(
                    this.clusterId,
                );
                this.cluster = clusterRes.data;

                // Tải danh sách sân con
                const courtsRes = await venueClusterService.getCourts(
                    this.clusterId,
                );
                this.courts = courtsRes.data || [];

                // Tải danh mục loại sân
                const courtTypesRes = await courtTypeService.getAll();
                this.courtTypes = courtTypesRes.data || [];
            } catch (err) {
                this.error = err.message || "Lỗi khởi tạo dữ liệu.";
            } finally {
                this.loading = false;
            }
        },
        formatStatus(status) {
            const map = {
                active: "Đang hoạt động",
                inactive: "Tạm khóa",
                maintenance: "Bảo trì",
            };
            return map[status] || status;
        },
        openCreateModal() {
            this.editingId = null;
            this.modalError = null;
            this.showTypeDropdown = false;
            this.form = {
                name: "",
                court_type_id: "",
                status: "active",
                sort_order: this.courts.length + 1,
            };
            this.showModal = true;
        },
        openEditModal(court) {
            this.editingId = court.id;
            this.originalCourtStatus = court.status;
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
            this.originalCourtStatus = null;
            this.modalError = null;
            this.showTypeDropdown = false;
        },
        async handleSubmit() {
            this.submitting = true;
            this.modalError = null;
            if (!this.form.court_type_id) {
                this.modalError = "Vui lòng chọn loại sân.";
                this.submitting = false;
                return;
            }
            try {
                const toast = useToast();
                if (this.editingId) {
                    const statusChanged = this.originalCourtStatus !== this.form.status;
                    const isDeactivating = statusChanged && ["inactive", "maintenance"].includes(this.form.status);

                    if (isDeactivating) {
                        try {
                            const res = await venueClusterService.getCourtConflicts(this.editingId);
                            const data = res.data || { affected_count: 0, items: [] };
                            if ((data.affected_count || 0) > 0) {
                                this.conflictData = data;
                                this.showConflictModal = true;
                                this.submitting = false;
                                return;
                            }
                        } catch (e) {
                            console.warn("Could not check court conflicts:", e);
                        }
                    }

                    await venueClusterService.updateCourt(this.editingId, {
                        name: this.form.name,
                        court_type_id: this.form.court_type_id,
                        status: this.form.status,
                        sort_order: this.form.sort_order,
                    });
                    toast.success("Cập nhật sân con thành công.");
                } else {
                    await venueClusterService.createCourt({
                        venue_cluster_id: this.clusterId,
                        court_type_id: this.form.court_type_id,
                        name: this.form.name,
                        sort_order: this.form.sort_order,
                    });
                    toast.success("Yêu cầu thêm sân con đã được gửi thành công. Vui lòng chờ Admin xét duyệt.");
                }
                await this.initData();
                this.closeModal();
            } catch (err) {
                const conflicts = err.conflicts || err.data?.conflicts;
                if (conflicts) {
                    this.conflictData = conflicts;
                    this.showConflictModal = true;
                } else {
                    this.modalError = err.message || "Lỗi lưu dữ liệu sân con.";
                }
            } finally {
                this.submitting = false;
            }
        },
        async handleConfirmConflictResolution({ resolutions, reason }) {
            this.conflictLoading = true;
            try {
                const toast = useToast();
                await venueClusterService.updateCourt(this.editingId, {
                    name: this.form.name,
                    court_type_id: this.form.court_type_id,
                    status: this.form.status,
                    sort_order: this.form.sort_order,
                    reason,
                    resolutions,
                });
                toast.success("Cập nhật trạng thái sân con và xử lý booking trùng thành công.");
                this.showConflictModal = false;
                await this.initData();
                this.closeModal();
            } catch (err) {
                const toast = useToast();
                toast.error(err.message || "Lỗi xử lý xung đột lịch sân.");
            } finally {
                this.conflictLoading = false;
            }
        },
        getParentTypeName(child) {
            if (!child.parent_id) return "";
            const parent = this.courtTypes.find(
                (t) => t.id === child.parent_id,
            );
            return parent ? parent.name : "";
        },
        selectCourtType(child) {
            this.form.court_type_id = child.id;
            this.showTypeDropdown = false;
        },
        handleOutsideClick(e) {
            const el = this.$el.querySelector(".custom-select-wrapper");
            if (el && !el.contains(e.target)) {
                this.showTypeDropdown = false;
            }
        },
        handleOwnerClusterChanged(event) {
            const clusterId = event.detail?.id;
            if (!clusterId || String(clusterId) === String(this.clusterId))
                return;
            this.clusterId = clusterId;
            this.initData();
        },
        async confirmDelete(court) {
            if (
                confirm(`Bạn có chắc chắn muốn xóa sân "${court.name}" không?`)
            ) {
                try {
                    await venueClusterService.deleteCourt(court.id);
                    await this.initData();
                } catch (err) {
                    alert(err.message || "Không thể xóa sân con.");
                }
            }
        },
        handleScroll() {
            this.showScrollTop = window.scrollY > 250;
        },
    },
    mounted() {
        document.addEventListener("click", this.handleOutsideClick);
        window.addEventListener(
            "owner-cluster-changed",
            this.handleOwnerClusterChanged,
        );
        window.addEventListener("scroll", this.handleScroll);
    },
    beforeUnmount() {
        document.removeEventListener("click", this.handleOutsideClick);
        window.removeEventListener(
            "owner-cluster-changed",
            this.handleOwnerClusterChanged,
        );
        window.removeEventListener("keydown", this.handleCanvasKeydown);
        window.removeEventListener("scroll", this.handleScroll);
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
    font-weight: 400;
    font-size: 13px;
    margin-bottom: 8px;
    transition: color 0.2s ease;
}

.btn-back.never-hover-class-placeholder {
    color: #000000;
}

.header-left h2 {
    font-size: 22px;
    font-weight: 400;
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
    font-weight: 400;
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

.btn-primary.never-hover-class-placeholder {
    background: #222222;
    border-color: #222222;
}

.btn-outline {
    border: 1px solid var(--sg-border);
    background: transparent;
    color: var(--sg-text);
}

.btn-outline.never-hover-class-placeholder {
    background: var(--sg-surface);
}

.btn-danger-outline {
    border: 1px solid rgba(0, 0, 0, 0.15);
    background: transparent;
    color: rgba(0, 0, 0, 0.7);
}

.btn-danger-outline.never-hover-class-placeholder {
    background: rgba(0, 0, 0, 0.05);
    border-color: rgba(0, 0, 0, 0.25);
    color: #000000;
}

/* SaaS Table Layout redesign */
.courts-list-wrapper {
    display: flex;
    flex-direction: column;
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

.view-content-wrapper,
.courts-table-wrapper {
    width: 100%;
    min-width: 0;
    max-width: 100%;
}

:deep(.saas-table-container) {
    background: transparent !important;
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}

/* Filters */
.avc-filters {
    padding: 10px 0 16px 0;
}
.filter-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.filter-tabs {
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--admin-bg, #f8fafc);
    padding: 4px;
    border-radius: 9px;
    border: 1px solid var(--admin-border-soft, #e2e8f0);
}
.avc-filters .filter-tabs button.tab-btn {
    height: 32px !important;
    min-height: 32px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0 14px !important;
    border-radius: 6px !important;
    border: none !important;
    background: transparent !important;
    color: var(--admin-faint, #64748b) !important;
    font-size: 12.5px !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    transition: all 0.14s ease !important;
    box-sizing: border-box !important;
}
.avc-filters .filter-tabs button.tab-btn:hover {
    color: var(--admin-text, #0f172a) !important;
}
.avc-filters .filter-tabs button.tab-btn.active {
    background: var(--admin-surface, #ffffff) !important;
    border: 1px solid var(--admin-border-soft, #cbd5e1) !important;
    color: var(--admin-text, #0f172a) !important;
    font-weight: 400 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
}
[data-theme="dark"] .avc-filters .filter-tabs {
    background: var(--admin-bg, #09090b);
    border-color: var(--admin-border-soft, rgba(255, 255, 255, 0.08));
}
[data-theme="dark"] .avc-filters .filter-tabs button.tab-btn.active {
    background: var(--admin-surface, #18181b) !important;
    border-color: var(--admin-border-soft, rgba(255, 255, 255, 0.12)) !important;
    color: #ffffff !important;
}
.filter-search {
    flex: 1;
    min-width: 240px;
    max-width: 320px;
}
.filter-search .search-box {
    position: relative;
    display: flex;
    align-items: center;
    border: 1px solid var(--admin-border-soft, #cbd5e1) !important;
    border-radius: 8px !important;
    background: var(--admin-surface, #ffffff) !important;
    padding: 0 12px !important;
    height: 38px !important;
}
.filter-search .search-box input {
    border: none !important;
    background: transparent !important;
    outline: none !important;
    box-shadow: none !important;
    width: 100% !important;
    font-size: 13px !important;
    color: var(--admin-text, #0f172a) !important;
    padding-left: 8px !important;
}
.filter-search .search-box input::placeholder {
    color: var(--admin-faint, #94a3b8) !important;
}
.filter-search .search-box svg {
    color: var(--admin-faint, #94a3b8) !important;
    flex-shrink: 0;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    font-size: 12px;
    font-weight: 400;
    white-space: nowrap;
    background: transparent !important;
    padding: 0 !important;
}

/* Status state colors matching admin */
.state-is-active {
    color: #10b981 !important;
}

.state-is-inactive {
    color: #ef4444 !important;
}

.state-is-maintenance {
    color: #f59e0b !important;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    padding: 0 12px;
    width: 100%;
    height: 38px;
    box-sizing: border-box;
    transition: border-color 0.2s ease;
}

.search-box:focus-within {
    border-color: #000000;
}

.search-box svg {
    color: rgba(15, 23, 42, 0.4);
    flex-shrink: 0;
    margin-right: 8px;
}

.search-box input.search-input {
    flex: 1;
    border: none !important;
    background: transparent !important;
    padding: 8px 0 !important;
    font-size: 13px;
    outline: none !important;
    color: var(--sg-text);
    min-width: 0;
    box-shadow: none !important;
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
    font-weight: 400;
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
    font-weight: 400;
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
    font-weight: 400;
    border: 1px solid #e5e7eb;
}

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
    width: 40px;
    height: 40px;
    border: 3px solid rgba(0, 0, 0, 0.1);
    border-top-color: #000000;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
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

.custom-select-trigger.never-hover-class-placeholder {
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
    font-weight: 400;
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
    font-weight: 400;
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
    transition:
        background 0.15s ease,
        color 0.15s ease;
}

.custom-option.never-hover-class-placeholder {
    background: rgba(0, 0, 0, 0.03);
}

.custom-option.selected {
    background: rgba(0, 0, 0, 0.05);
    font-weight: 400;
}

.custom-option .option-text {
    font-weight: 400;
}

.custom-option .option-details {
    margin-left: 6px;
    font-size: 12px;
    color: rgba(15, 23, 42, 0.4);
}

.custom-option .check-mark {
    margin-left: auto;
    color: #000000;
    font-weight: 400;
}

/* The edit form follows the owner theme instead of mixing legacy light tokens. */
.modal-header,
.modal-footer {
    border-color: var(--admin-border, #e2e8f0);
}

.modal-header h3,
.modal .form-group label {
    color: var(--admin-text, #0f172a);
}

.modal .btn-close {
    color: var(--admin-muted, #64748b);
}

.modal-footer {
    background: var(--admin-surface-muted, #f8fafc);
}

.modal .custom-select-trigger,
.modal .custom-options-container {
    border-color: var(--admin-border, #cbd5e1);
    background: var(--admin-surface, #fff);
    color: var(--admin-text, #0f172a);
}

.modal .custom-select-trigger :is(.parent-name, .placeholder, .arrow),
.modal .custom-optgroup-label,
.modal .custom-option .option-details {
    color: var(--admin-muted, #64748b);
}

.modal .custom-optgroup-label {
    border-bottom-color: var(--admin-border-soft, #e2e8f0);
    background: var(--admin-surface-muted, #f8fafc);
}

.modal .custom-option,
.modal .custom-select-trigger .child-name {
    color: var(--admin-text, #0f172a);
}

.modal .custom-option.never-hover-class-placeholder {
    background: var(--admin-hover, #f1f5f9);
}

.modal .custom-option.selected {
    background: var(--admin-primary-soft, #ecfdf5);
}

.modal .custom-option .check-mark {
    color: var(--admin-primary, #059669);
}

/* Layout Editor CSS */
.layout-toggle-tabs {
    display: flex;
    gap: 12px;
}

.tab-btn {
    background: none;
    border: none;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 400;
    color: rgba(15, 23, 42, 0.4);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    outline: none;
}

.tab-btn.never-hover-class-placeholder {
    color: var(--sg-text);
    border-bottom-color: rgba(0, 0, 0, 0.1);
}

.tab-btn.active {
    color: #000000;
    border-bottom-color: #000000;
}

.badge-placed {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
}

.badge-unplaced {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
}

.layout-editor-workspace {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.editor-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    background: #ffffff;
    border: 1px solid var(--sg-border);
    padding: 12px 20px;
    border-radius: 12px;
}

.toolbar-left {
    display: flex;
    gap: 10px;
}

.info-badge {
    font-size: 12.5px;
    font-weight: 400;
    color: rgba(15, 23, 42, 0.5);
}
/* ── Tool Switcher ── */
.tool-switcher {
    display: flex;
    background: #f1f5f9;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    padding: 3px;
    gap: 2px;
}
.tool-btn {
    width: 30px;
    height: 30px;
    border: none;
    background: transparent;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    transition: all 0.15s;
}
.tool-btn.never-hover-class-placeholder {
    background: #e2e8f0;
    color: #1e293b;
}
.tool-btn.active {
    background: #fff;
    color: #3b82f6;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
}
.toolbar-divider {
    width: 1px;
    height: 28px;
    background: #e2e8f0;
    align-self: center;
    margin: 0 2px;
}

.editor-body {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.canvas-viewport {
    position: relative;
    flex: 1;
    min-width: 0;
    height: 600px;
    background-color: #f8fafc;
    border: 1px solid var(--sg-border);
    border-radius: 16px;
    box-shadow:
        inset 0 2px 8px rgba(0, 0, 0, 0.04),
        0 4px 12px rgba(0, 0, 0, 0.02);
    overflow: hidden;
    cursor: default;
    user-select: none;
}
.canvas-viewport.tool-select {
    cursor: default;
}
.canvas-viewport.tool-pan {
    cursor: grab;
}
.canvas-viewport.tool-pan:active,
.canvas-viewport.tool-pan.panning {
    cursor: grabbing;
}
.canvas-viewport.tool-select.panning {
    cursor: grabbing;
}

.canvas-content {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.canvas-court-element {
    pointer-events: auto;
}

.zoom-controls {
    position: absolute;
    bottom: 20px;
    right: 20px;
    display: flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid var(--sg-border);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    z-index: 100;
    overflow: hidden;
}

.btn-zoom {
    background: none;
    border: none;
    padding: 8px 12px;
    font-weight: 400;
    font-size: 16px;
    cursor: pointer;
    color: var(--sg-text);
    transition: background 0.2s;
}

.btn-zoom.never-hover-class-placeholder {
    background: #f1f5f9;
}

.btn-zoom.reset {
    font-size: 13px;
    border-left: 1px solid var(--sg-border);
}

.zoom-level {
    font-size: 13px;
    font-weight: 400;
    padding: 0 10px;
    color: var(--sg-text);
    min-width: 48px;
    text-align: center;
}

.canvas-grid-bg {
    position: absolute;
    width: 10000px;
    height: 10000px;
    left: -5000px;
    top: -5000px;
    background-image:
        linear-gradient(to right, rgba(15, 23, 42, 0.04) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(15, 23, 42, 0.04) 1px, transparent 1px);
    background-size: 30px 30px;
    pointer-events: none;
    z-index: 1;
}

.canvas-guideline {
    position: absolute;
    pointer-events: none;
    z-index: 99;
}
.canvas-guideline.vertical {
    top: -5000px;
    bottom: -5000px;
    width: 1px;
    border-left: 1px dashed #ef4444;
    opacity: 0.8;
}
.canvas-guideline.horizontal {
    left: -5000px;
    right: -5000px;
    height: 1px;
    border-top: 1px dashed #ef4444;
    opacity: 0.8;
}

.canvas-court-element {
    position: absolute;
    z-index: 10;
    border-radius: 10px;
    box-sizing: border-box;
    transition:
        transform 0.1s ease-out,
        outline 0.2s,
        box-shadow 0.2s;
    cursor: pointer;
}

.canvas-court-element.never-hover-class-placeholder {
    cursor: pointer;
}

.canvas-court-element.dragging {
    cursor: move;
    z-index: 50;
    opacity: 0.85;
    transition: none;
}

.canvas-court-element.resizing {
    transition: none;
}

.canvas-court-element.selected {
    outline: 2.5px solid #000000 !important;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12) !important;
}

.canvas-court-element.has-collision {
    outline: 2.5px solid #ef4444 !important;
    box-shadow: 0 0 15px rgba(239, 68, 68, 0.5) !important;
}

.collision-badge {
    position: absolute;
    top: 8px;
    left: 50%;
    transform: translateX(-50%);
    background: #ef4444;
    color: white;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 10.5px;
    font-weight: 400;
    z-index: 30;
    box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
    pointer-events: none;
    animation: pulseWarning 1.5s infinite;
}

@keyframes pulseWarning {
    0% {
        transform: translateX(-50%) scale(1);
    }
    50% {
        transform: translateX(-50%) scale(1.06);
    }
    100% {
        transform: translateX(-50%) scale(1);
    }
}

.canvas-interaction-guide {
    position: absolute;
    top: 20px;
    left: 20px;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(8px);
    border: 1px solid var(--sg-border);
    border-radius: 10px;
    padding: 12px 14px;
    z-index: 99;
    display: flex;
    flex-direction: column;
    gap: 6px;
    pointer-events: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.guide-item {
    font-size: 11.5px;
    color: var(--sg-text);
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-zoom.fit {
    font-size: 12.5px;
    border-left: 1px solid var(--sg-border);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-zoom.fit .btn-icon {
    font-size: 13px;
}

.inspector-warning-box {
    background: #fef2f2;
    border: 1px solid #fee2e2;
    color: #ef4444;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 400;
    margin-bottom: 14px;
    line-height: 1.4;
}

.resize-handle {
    position: absolute;
    width: 10px;
    height: 10px;
    background-color: #ffffff;
    border: 2px solid #000000;
    border-radius: 50%;
    z-index: 25;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.resize-handle.tl {
    top: -5px;
    left: -5px;
    cursor: nwse-resize;
}
.resize-handle.tr {
    top: -5px;
    right: -5px;
    cursor: nesw-resize;
}
.resize-handle.bl {
    bottom: -5px;
    left: -5px;
    cursor: nesw-resize;
}
.resize-handle.br {
    bottom: -5px;
    right: -5px;
    cursor: nwse-resize;
}

.editor-sidebar {
    width: 300px;
    flex: 0 0 300px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.sidebar-section {
    background: var(--admin-surface, #ffffff);
    border: 1px solid var(--admin-border-soft, #e2e8f0);
    border-radius: 10px;
    padding: 12px 14px;
}

.section-title {
    font-size: 12.5px;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 8px;
    color: var(--admin-text, #0f172a);
    border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
    padding-bottom: 6px;
}

.section-desc {
    font-size: 11.5px;
    color: var(--admin-muted, #64748b);
    margin-top: 0;
    margin-bottom: 8px;
}

/* Inspector styles */
.inspector-fields {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.field-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11.5px;
    padding-bottom: 4px;
    border-bottom: 1px dashed var(--admin-border-soft, #e2e8f0);
}

.field-row .label {
    font-weight: 500;
    color: var(--admin-muted, #64748b);
}

.field-row .value {
    font-weight: 500;
    color: var(--admin-text, #0f172a);
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.field-group label {
    font-size: 11.5px;
    font-weight: 400;
    color: var(--admin-muted, #64748b);
}

.input-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.input-row input,
.inspector-panel :is(input, select, textarea, .form-control) {
    width: 100%;
    height: 30px !important;
    padding: 4px 8px !important;
    border-radius: 6px;
    border: 1px solid var(--admin-border, #cbd5e1);
    font-size: 12px !important;
    outline: none;
    font-weight: 400 !important;
    background: var(--admin-surface, #ffffff);
    color: var(--admin-text, #0f172a);
}

.input-row input:focus,
.inspector-panel :is(input, select, textarea, .form-control):focus {
    border-color: #22a653 !important;
}

.input-row .x,
.input-row .comma {
    font-size: 11px;
    font-weight: 400;
    color: var(--admin-muted, #94a3b8);
}

.rotation-control {
    display: flex;
    align-items: center;
    gap: 12px;
}

.rotation-slider {
    flex: 1;
    accent-color: #000000;
    height: 4px;
}

.btn-rotate {
    padding: 6px 10px;
    font-size: 11px;
}

.btn-block {
    width: 100%;
    display: flex;
    justify-content: center;
}

/* Unplaced list styles */
.unplaced-items {
    max-height: 300px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.unplaced-court-item {
    padding: 10px 12px;
    background: var(--sg-surface);
    border: 1px solid var(--sg-border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
}

.unplaced-court-item.never-hover-class-placeholder {
    background: #ffffff;
    border-color: #000000;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04);
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.item-name {
    font-weight: 400;
    font-size: 13.5px;
    color: var(--sg-text);
}

.item-add-hint {
    font-size: 11px;
    font-weight: 400;
    color: #000000;
    opacity: 0;
    transition: opacity 0.15s ease;
}

.unplaced-court-item.never-hover-class-placeholder .item-add-hint {
    opacity: 1;
}

.item-type {
    font-size: 11.5px;
    color: rgba(15, 23, 42, 0.4);
    margin-top: 2px;
}

.empty-unplaced {
    font-size: 12.5px;
    color: rgba(15, 23, 42, 0.4);
    text-align: center;
    padding: 20px 0;
    font-style: italic;
}

@media (max-width: 1024px) {
    .editor-body {
        flex-direction: column;
        align-items: stretch;
    }

    .canvas-viewport {
        width: 100%;
        height: 500px;
    }

    .editor-sidebar {
        width: 100%;
        flex: none;
    }
}

/* ─── Layout Decorations ─── */
.canvas-decor-element {
    position: absolute;
    cursor: pointer;
    box-sizing: border-box;
    transition: box-shadow 0.1s;
    z-index: 20;
    pointer-events: auto;
}
.canvas-decor-element.never-hover-class-placeholder {
    cursor: pointer;
}
.canvas-decor-element.dragging {
    cursor: move;
    z-index: 60;
}
.canvas-decor-element.selected {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
    z-index: 30;
}
.decor-library-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.btn-add-decor {
    padding: 8px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 400;
    color: #475569;
    cursor: pointer;
    text-align: center;
    transition: all 0.2s;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.btn-add-decor.never-hover-class-placeholder {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #1e293b;
}
</style>

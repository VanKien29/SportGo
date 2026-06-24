<template>
    <div class="avc-page">
        <!-- ── Loading ── -->
        <div v-if="loading" class="state-box card animate-fade-in">
            <div class="spinner"></div>
            <p>Đang tải danh sách cụm sân...</p>
        </div>

        <!-- ── Error ── -->
        <div v-else-if="error" class="state-box card error-box animate-fade-in">
            <p>{{ error }}</p>
            <button class="btn btn-outline" @click="loadClusters">
                Thử lại
            </button>
        </div>

        <template v-else>
            <!-- ── Bộ lọc & Ô tìm kiếm (SaaS Command Bar) ── -->
            <div class="avc-filters card animate-fade-in" v-if="clusters.length > 0">
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
                                id="search-venue-cluster"
                                v-model="searchText"
                                type="text"
                                placeholder="Tìm kiếm nhanh tên sân, địa chỉ hoặc chủ sân..."
                                class="search-input"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Empty State khi hệ thống không có cụm sân nào ── -->
            <div v-if="clusters.length === 0" class="state-box card animate-fade-in">
                <p class="empty-msg">Chưa có cụm sân nào được đăng ký trên hệ thống.</p>
            </div>

            <!-- ── Empty State khi tìm kiếm không ra kết quả ── -->
            <div v-else-if="filteredClusters.length === 0" class="state-box card animate-fade-in">
                <p class="empty-msg">Không tìm thấy cụm sân nào phù hợp với điều kiện tìm kiếm.</p>
                <button class="btn btn-outline" @click="searchText = ''; filterStatus = ''">
                    Xóa bộ lọc
                </button>
            </div>

            <!-- ── Compact Rows View ── -->
            <div v-else class="clusters-list-wrapper animate-fade-in">
                <div class="clusters-list">
                    <div
                        v-for="c in filteredClusters"
                        :key="c.id"
                        class="cluster-row-item"
                        :class="{ 'status-locked': c.status === 'locked' }"
                        @click="goDetail(c.id)"
                    >
                        <!-- Accent hover line -->
                        <div class="accent-line"></div>

                        <!-- Left: Cluster Name, Slug & Courts count -->
                        <div class="row-left">
                            <div class="cluster-info">
                                <span class="cluster-name">{{ c.name }}</span>
                                <span class="cluster-meta">
                                    <span class="cluster-slug">{{ c.slug }}</span>
                                    <span class="meta-dot">&bull;</span>
                                    <span class="cluster-address">{{ formatFullAddress(c) }}</span>
                                </span>
                            </div>
                            <span class="courts-count-badge">
                                <AppIcon name="layers" size="12" />
                                <span>{{ c.court_count }} sân con</span>
                            </span>
                        </div>

                        <!-- Middle: Owner & Status Badges -->
                        <div class="row-middle">
                            <div class="owner-info hide-on-tablet">
                                <span class="owner-name">{{ c.owner?.full_name || '—' }}</span>
                                <span class="owner-email" v-if="c.owner?.email">{{ c.owner.email }}</span>
                            </div>
                            <div class="status-badges">
                                <span class="row-status-badge fee-badge" :class="c.fee_status">
                                    Phí: {{ feeStatusLabel(c.fee_status) }}
                                </span>
                                <span class="row-status-badge status-badge" :class="c.status">
                                    {{ statusLabel(c.status) }}
                                </span>
                            </div>
                        </div>

                        <!-- Right: Actions -->
                        <div class="row-right" @click.stop>
                            <ActionIconButton
                                icon="eye"
                                label="Xem chi tiết"
                                size="sm"
                                @click="goDetail(c.id)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import { adminVenueClusterService } from "../../services/adminVenueClusterService.js";

export default {
    name: "AdminVenueClusters",
    components: { ActionIconButton, AppIcon },
    data() {
        return {
            clusters: [],
            loading: true,
            error: "",
            filterStatus: "",
            searchText: "",
            statusTabs: [
                { value: "", label: "Tất cả" },
                { value: "pending", label: "Chờ duyệt" },
                { value: "active", label: "Hoạt động" },
                { value: "locked", label: "Đã khóa" },
            ],
        };
    },
    computed: {
        filteredClusters() {
            let list = this.clusters;
            if (this.filterStatus) {
                list = list.filter((c) => c.status === this.filterStatus);
            }
            if (this.searchText.trim()) {
                const q = this.searchText.trim().toLowerCase();
                list = list.filter(
                    (c) =>
                        c.name.toLowerCase().includes(q) ||
                        (c.address || "").toLowerCase().includes(q) ||
                        (c.owner?.full_name || "").toLowerCase().includes(q),
                );
            }
            return list;
        },
    },
    mounted() {
        this.loadClusters();
    },
    methods: {
        async loadClusters() {
            this.loading = true;
            this.error = "";
            try {
                const res = await adminVenueClusterService.list();
                this.clusters = res.data || [];
            } catch (err) {
                this.error = err.message || "Không tải được danh sách cụm sân.";
            } finally {
                this.loading = false;
            }
        },

        statusLabel(status) {
            const map = {
                pending: "Chờ duyệt",
                active: "Hoạt động",
                locked: "Đã khóa",
            };
            return map[status] || status;
        },

        feeStatusLabel(status) {
            const map = {
                pending: "Chờ thanh toán",
                paid: "Đã thanh toán",
                overdue: "Quá hạn",
                cancelled: "Hủy bỏ",
                no_fee: "Không có phí",
            };
            return map[status] || status;
        },
        goDetail(id) {
            this.$router.push({
                name: "admin-venue-cluster-detail",
                params: { id },
            });
        },
        formatFullAddress(c) {
            if (!c) return "";
            const parts = [
                c.address,
                c.ward,
                c.province
            ].filter(Boolean);
            return parts.join(', ') || '—';
        },
    },
};
</script>

<style scoped>
.avc-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 1000px;
    width: 100%;
    margin: 0 auto;
    box-sizing: border-box;
}

.card {
    background: var(--admin-surface, #fff);
    border-radius: 12px;
    border: 1px solid var(--admin-border);
    padding: 20px 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

/* Filters */
.avc-filters {
    padding: 14px 24px;
}
.filter-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
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
    border: 1px solid var(--admin-border) !important;
    background: var(--admin-surface) !important;
    color: var(--admin-muted) !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.18s !important;
    box-sizing: border-box !important;
}
.avc-filters .filter-tabs button.tab-btn.active {
    background: var(--admin-primary) !important;
    border-color: var(--admin-primary) !important;
    color: #fff !important;
}
.avc-filters .filter-tabs button.tab-btn:not(.active):hover {
    background: var(--admin-hover) !important;
    color: var(--admin-primary-dark) !important;
}
.filter-search {
    flex: 1;
    min-width: 250px;
}

/* State */
.state-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 24px;
    gap: 14px;
    text-align: center;
    color: rgba(15, 23, 42, 0.5);
}
.error-box {
    color: #dc2626;
    background: #fef2f2;
    border-color: #fecaca;
}
.empty-msg {
    font-size: 15px;
    font-weight: 600;
}
.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid rgba(0, 0, 0, 0.05);
    border-top-color: var(--admin-text);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* SaaS Compact Rows View */
.clusters-list-wrapper {
    display: flex;
    flex-direction: column;
    width: 100%;
}

.clusters-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cluster-row-item {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 54px;
    padding: 10px 16px;
    background: var(--admin-surface, #ffffff);
    border: 1px solid rgba(15, 23, 42, 0.04);
    border-radius: 8px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

.cluster-row-item:hover {
    background: rgba(15, 23, 42, 0.015);
    border-color: rgba(15, 23, 42, 0.08);
    transform: translateX(2px);
}

.accent-line {
    position: absolute;
    left: 0;
    top: 15%;
    bottom: 15%;
    width: 2.5px;
    background: var(--admin-primary);
    border-radius: 0 2px 2px 0;
    opacity: 0;
    transform: scaleY(0.7);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.cluster-row-item:hover .accent-line {
    opacity: 1;
    transform: scaleY(1);
}

.row-left {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1.3;
    min-width: 0;
}

.cluster-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.cluster-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--admin-text, #0f172a);
    transition: opacity 0.2s ease;
}

.cluster-row-item.status-locked .cluster-name {
    opacity: 0.5;
}

.cluster-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--admin-faint, #64748b);
    min-width: 0;
}

.cluster-slug {
    font-weight: 600;
    flex-shrink: 0;
}

.meta-dot {
    opacity: 0.5;
}

.cluster-address {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.courts-count-badge {
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
    flex: 1.2;
    padding-right: 16px;
    min-width: 0;
}

.owner-info {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    text-align: right;
    min-width: 0;
    flex: 1;
}

.owner-name {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--admin-text, #0f172a);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

.owner-email {
    font-size: 11px;
    color: var(--admin-faint, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

.status-badges {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.row-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

/* Status styles */
.status-badge.pending {
    background: var(--admin-warning-soft, rgba(245, 158, 11, 0.1)) !important;
    color: var(--admin-warning, #d97706) !important;
}

.status-badge.active {
    background: var(--admin-primary-soft, rgba(16, 185, 129, 0.1)) !important;
    color: var(--admin-primary-dark, #047857) !important;
}

.status-badge.locked {
    background: var(--admin-danger-soft, rgba(239, 68, 68, 0.1)) !important;
    color: var(--admin-danger, #b91c1c) !important;
}

/* Fee styles */
.fee-badge.paid {
    background: var(--admin-primary-soft, rgba(16, 185, 129, 0.1)) !important;
    color: var(--admin-primary-dark, #047857) !important;
}

.fee-badge.pending {
    background: var(--admin-warning-soft, rgba(245, 158, 11, 0.1)) !important;
    color: var(--admin-warning, #d97706) !important;
}

.fee-badge.unpaid,
.fee-badge.overdue {
    background: var(--admin-danger-soft, rgba(239, 68, 68, 0.1)) !important;
    color: var(--admin-danger, #b91c1c) !important;
}

.fee-badge.cancelled {
    background: var(--admin-surface-muted, #f1f5f9) !important;
    color: var(--admin-muted, #475569) !important;
}

.fee-badge.no_fee {
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

.cluster-row-item:hover .row-right {
    opacity: 1;
    transform: translateX(0);
}

/* Buttons */
.btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.18s;
}
.btn-outline {
    background: transparent;
    border-color: var(--sg-border);
    color: var(--admin-text);
}
.btn-outline:hover {
    background: var(--admin-surface-muted);
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

/* ==========================================
   RESPONSIVE DESIGN (MOBILE & TABLET)
   ========================================== */

@media (max-width: 1024px) {
    .avc-page {
        gap: 16px;
        padding: 0 4px;
    }
}

@media (max-width: 900px) {
    .hide-on-tablet {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .cluster-row-item {
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

    .cluster-address {
        max-width: 180px;
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

    .cluster-row-item:hover .row-right {
        transform: none;
    }
}
</style>

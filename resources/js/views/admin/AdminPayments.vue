<template>
    <div class="cluster-profile-surface standalone">
        <div class="profile-section-card payments-main-content">
            <section class="admin-payments">
                <header class="page-header">
                    <div>
                        <h2>Theo dõi thanh toán booking</h2>
                        <p>
                            Đối soát payment attempt, gateway logs và tiền hệ thống thu
                            hộ chủ sân.
                        </p>
                    </div>
                </header>

                <SaaSFilterBar
                    v-model="filters.status"
                    v-model:search="filters.keyword"
                    :tabs="statusTabsUi"
                    search-id="search-admin-payments"
                    search-placeholder="Mã payment, booking, khách, cụm sân..."
                    @update:search="applyFilters"
                    @update:modelValue="applyFilters"
                >
                    <template #actions>
                        <select v-model="filters.payment_kind" @change="applyFilters" class="filter-select">
                            <option value="">Tất cả loại</option>
                            <option value="full">Thanh toán toàn bộ</option>
                            <option value="deposit">Đặt cọc</option>
                            <option value="partial">Thanh toán một phần</option>
                        </select>
                        <select v-model="filters.method" @change="applyFilters" class="filter-select">
                            <option value="">Tất cả phương thức</option>
                            <option value="sepay">SePay</option>
                            <option value="bank_transfer">Chuyển khoản</option>
                            <option value="wallet">Ví</option>
                            <option value="mixed">Kết hợp</option>
                            <option value="cash">Tiền mặt</option>
                        </select>
                        <select v-model="filters.paid_range" @change="applyFilters" class="filter-select">
                            <option value="">Ngày thanh toán</option>
                            <option value="today">Hôm nay</option>
                            <option value="yesterday">Hôm qua</option>
                            <option value="last_3_days">3 ngày gần đây</option>
                            <option value="last_7_days">7 ngày gần đây</option>
                            <option value="last_30_days">30 ngày gần đây</option>
                            <option value="this_month">Tháng này</option>
                            <option value="last_month">Tháng trước</option>
                            <option value="custom">Tùy chỉnh</option>
                        </select>
                    </template>
                </SaaSFilterBar>

                <div v-if="filters.paid_range === 'custom'" class="date-range-fields" aria-label="Khoảng ngày thanh toán tùy chỉnh">
                    <input v-model="filters.paid_from" type="date" aria-label="Từ ngày thanh toán" />
                    <span>đến</span>
                    <input v-model="filters.paid_to" type="date" aria-label="Đến ngày thanh toán" />
                    <button class="btn primary" type="button" @click="applyFilters">Lọc</button>
                </div>

                <div v-if="error" class="alert error">{{ error }}</div>
                <div v-if="success" class="alert success">{{ success }}</div>

                <div class="table-wrap">
                    <div v-if="loading" class="state-box animate-fade-in">
                        <div class="spinner"></div>
                        <p>Đang tải giao dịch...</p>
                    </div>
                    <div v-else-if="payments.length === 0" class="empty">Không có giao dịch phù hợp.</div>
                    <SaaSTable
                        v-else
                        :columns="tableColumns"
                        :data="payments"
                    >
                        <template #payment_code="{ row }">
                            <button
                                class="code-link"
                                type="button"
                                @click="openDetail(row.id)"
                            >
                                {{ row.payment_code }}
                            </button>
                            <span class="sub-line">{{
                                row.booking?.booking_code || "-"
                            }}</span>
                        </template>

                        <template #customer="{ row }">
                            <strong>{{
                                row.customer?.full_name ||
                                row.customer?.username ||
                                "-"
                            }}</strong>
                            <span class="sub-line">{{
                                row.customer?.email ||
                                row.customer?.phone ||
                                "-"
                            }}</span>
                        </template>

                        <template #venue_cluster="{ row }">
                            {{ row.venue_cluster?.name || "-" }}
                        </template>

                        <template #amount="{ row }">
                            <strong>{{
                                formatCurrency(row.amount)
                            }}</strong>
                            <span v-if="['sepay', 'bank_transfer', 'mixed'].includes(row.method)" class="sub-line"
                                >Gateway:
                                {{
                                    formatCurrency(row.gateway_amount)
                                }}</span
                            >
                        </template>

                        <template #kind_method="{ row }">
                            <span>{{ kindLabel(row.payment_kind) }}</span>
                            <span class="sub-line">{{
                                methodLabel(row.method)
                            }}</span>
                        </template>

                        <template #status="{ row }">
                            <span class="status-pill" :class="row.status">{{
                                statusLabel(row.status)
                            }}</span>
                        </template>

                        <template #paid_at="{ row }">
                            {{ formatDate(row.paid_at) }}
                        </template>

                        <template #logs_count="{ row }">
                            {{ row.logs_count }}
                        </template>

                        <template #actions="{ row }">
                            <button
                                class="icon-only"
                                type="button"
                                title="Xem chi tiết"
                                @click="openDetail(row.id)"
                            >
                                <AppIcon name="eye" size="17" />
                            </button>
                        </template>
                    </SaaSTable>
                </div>

        <div class="pagination">
            <ActionIconButton
                icon="chevronLeft"
                label="Trang trước"
                :disabled="meta.current_page <= 1 || loading"
                @click="changePage(meta.current_page - 1)"
            />
            <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
            <ActionIconButton
                icon="chevronRight"
                label="Trang sau"
                :disabled="meta.current_page >= meta.last_page || loading"
                @click="changePage(meta.current_page + 1)"
            />
        </div>

        <div
            v-if="detailOpen"
            class="drawer-backdrop"
            @click.self="closeDetail"
        >
            <aside class="detail-drawer">
                <header class="drawer-header">
                    <div>
                        <span class="eyebrow">Chi tiết thanh toán</span>
                        <h3>
                            {{ detail?.payment?.payment_code || "Đang tải..." }}
                        </h3>
                    </div>
                    <button
                        class="icon-only"
                        type="button"
                        title="Đóng"
                        @click="closeDetail"
                    >
                        <AppIcon name="x" size="19" />
                    </button>
                </header>

                <div v-if="detailLoading" class="drawer-loading">
                    Đang tải chi tiết...
                </div>
                <template v-else-if="detail?.payment">
                    <!-- Status badge -->
                    <div class="detail-status-bar">
                        <span
                            class="status-pill large"
                            :class="detail.payment.status"
                            >{{ statusLabel(detail.payment.status) }}</span
                        >
                        <span v-if="detail.payment.paid_at" class="paid-time">{{
                            formatDate(detail.payment.paid_at)
                        }}</span>
                    </div>

                    <!-- Main info -->
                    <div class="detail-facts">
                        <div>
                            <span>Booking</span
                            ><strong>{{
                                detail.payment.booking?.booking_code || "-"
                            }}</strong>
                        </div>
                        <div>
                            <span>Khách hàng</span
                            ><strong>{{
                                detail.payment.customer?.full_name ||
                                detail.payment.customer?.username ||
                                "-"
                            }}</strong>
                        </div>
                        <div>
                            <span>Số điện thoại</span
                            ><strong>{{
                                detail.payment.customer?.phone || "-"
                            }}</strong>
                        </div>
                        <div>
                            <span>Email</span
                            ><strong>{{
                                detail.payment.customer?.email || "-"
                            }}</strong>
                        </div>
                        <div>
                            <span>Cụm sân</span
                            ><strong>{{
                                detail.payment.venue_cluster?.name || "-"
                            }}</strong>
                        </div>
                        <div>
                            <span>Loại</span
                            ><strong>{{
                                kindLabel(detail.payment.payment_kind)
                            }}</strong>
                        </div>
                    </div>

                    <section v-if="detail.payment.booking" class="booking-flow-panel">
                        <div class="booking-flow-head">
                            <h4>Luồng booking</h4>
                            <span class="status-pill" :class="detail.payment.booking.status">
                                {{ bookingStatusLabel(detail.payment.booking.status) }}
                            </span>
                        </div>
                        <div class="booking-flow-grid">
                            <div>
                                <span>Hình thức ban đầu</span>
                                <strong>{{ paymentOptionLabel(detail.payment.booking.payment_option) }}</strong>
                            </div>
                            <div>
                                <span>Hình thức áp dụng</span>
                                <strong>{{ paymentOptionLabel(detail.payment.booking.effective_payment_option) }}</strong>
                            </div>
                            <div v-if="detail.payment.booking.approval_deadline_at">
                                <span>Hạn chủ sân duyệt</span>
                                <strong>{{ formatDate(detail.payment.booking.approval_deadline_at) }}</strong>
                            </div>
                            <div v-else-if="detail.payment.booking.payment_deadline_at">
                                <span>Hạn giữ chỗ/thanh toán</span>
                                <strong>{{ formatDate(detail.payment.booking.payment_deadline_at) }}</strong>
                            </div>
                            <div v-if="detail.payment.booking.owner_approved_at">
                                <span>Đã duyệt lúc</span>
                                <strong>{{ formatDate(detail.payment.booking.owner_approved_at) }}</strong>
                            </div>
                        </div>
                        <p v-if="detail.payment.booking.payment_fallback_reason" class="booking-flow-note">
                            {{ detail.payment.booking.payment_fallback_reason }}
                        </p>
                    </section>

                    <!-- Payment amounts -->
                    <div class="detail-facts mt-12">
                        <div>
                            <span>Tổng tiền</span
                            ><strong class="amount-highlight">{{
                                formatCurrency(detail.payment.amount)
                            }}</strong>
                        </div>
                        <div>
                            <span>Phương thức</span
                            ><strong>{{
                                methodLabel(detail.payment.method)
                            }}</strong>
                        </div>
                        <div v-if="detail.payment.wallet_amount > 0">
                            <span>Trả từ ví user</span
                            ><strong>{{
                                formatCurrency(detail.payment.wallet_amount)
                            }}</strong>
                        </div>
                        <div v-if="['sepay', 'bank_transfer', 'mixed'].includes(detail.payment.method)">
                            <span>Cổng thanh toán</span>
                            <strong>{{
                                formatCurrency(detail.payment.gateway_amount)
                            }}</strong>
                        </div>
                        <div v-if="['sepay', 'bank_transfer', 'mixed'].includes(detail.payment.method)">
                            <span>Gateway txn</span>
                            <strong>{{
                                detail.payment.gateway_txn_id || "-"
                            }}</strong>
                        </div>
                        <div v-if="detail.payment.system_bank_account">
                            <span>TK nhận hệ thống</span
                            ><strong>{{
                                formatBankAccount(
                                    detail.payment.system_bank_account,
                                )
                            }}</strong>
                        </div>
                        <div v-else>
                            <span>TK nhận hệ thống</span><strong>-</strong>
                        </div>
                        <div>
                            <span>Thời gian tạo</span
                            ><strong>{{
                                formatDate(detail.payment.created_at)
                            }}</strong>
                        </div>
                        <div>
                            <span>Thanh toán lúc</span
                            ><strong>{{
                                formatDate(detail.payment.paid_at)
                            }}</strong>
                        </div>
                    </div>

                    <!-- Owner wallet credit - only show when paid and ledger exists -->
                    <section
                        v-if="detail.payment.status === 'paid' && creditLedger"
                        class="wallet-credit-section"
                    >
                        <div class="wallet-credit-header">
                            <AppIcon name="banknote" size="16" />
                            <span>Ví chủ sân</span>
                        </div>
                        <div class="wallet-credit-body">
                            <div class="wallet-credit-formula">
                                <div class="formula-part">
                                    <span>Trước</span>
                                    <strong>{{
                                        formatCurrency(
                                            creditLedger.balance_before,
                                        )
                                    }}</strong>
                                </div>
                                <span class="formula-op">+</span>
                                <div class="formula-part credited">
                                    <span>Cộng</span>
                                    <strong>{{
                                        formatCurrency(creditLedger.amount)
                                    }}</strong>
                                </div>
                                <span class="formula-op">=</span>
                                <div class="formula-part result">
                                    <span>Sau</span>
                                    <strong>{{
                                        formatCurrency(
                                            creditLedger.balance_after,
                                        )
                                    }}</strong>
                                </div>
                            </div>
                        </div>
                    </section>
                    <div
                        v-else-if="
                            detail.payment.status === 'paid' && !creditLedger
                        "
                        class="wallet-note"
                    >
                        <AppIcon name="banknote" size="14" />
                        <span>Thanh toán tiền mặt / tại quầy</span>
                    </div>

                    <!-- Payment logs -->
                    <section class="logs-section">
                        <h4>Payment logs</h4>
                        <div
                            v-if="detail.logs.length === 0"
                            class="empty-block"
                        >
                            Chưa có log.
                        </div>
                        <article
                            v-for="log in detail.logs"
                            :key="log.id"
                            class="log-row"
                        >
                            <div class="log-head">
                                <strong>{{ log.event_type }}</strong>
                                <time>{{ formatDate(log.created_at) }}</time>
                            </div>
                            <div class="log-meta">
                                <span
                                    >{{ log.status_before || "-" }} →
                                    {{ log.status_after || "-" }}</span
                                >
                                <span v-if="log.gateway_txn_id"
                                    >Txn: {{ log.gateway_txn_id }}</span
                                >
                                <span
                                    v-if="log.error_code"
                                    class="error-text"
                                    >{{ log.error_code }}</span
                                >
                            </div>
                            <details
                                v-if="
                                    log.request_payload || log.response_payload
                                "
                            >
                                <summary>Payload</summary>
                                <pre>{{
                                    prettyJson({
                                        request: log.request_payload,
                                        response: log.response_payload,
                                    })
                                }}</pre>
                            </details>
                        </article>
                    </section>
                </template>
            </aside>
        </div>
        </section>
    </div>
</div>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import SaaSFilterBar from "../../components/ui/SaaSFilterBar.vue";
import SaaSTable from "../../components/ui/SaaSTable.vue";
import { adminPaymentService } from "../../services/adminPayments.js";

export default {
    name: "AdminPayments",
    components: { ActionIconButton, AppIcon, SaaSFilterBar, SaaSTable },
    data() {
        return {
            payments: [],
            summary: {
                total: 0,
                pending: 0,
                paid: 0,
                failed: 0,
                refunded: 0,
                collected_amount: 0,
            },
            meta: { current_page: 1, last_page: 1, total: 0 },
            filters: {
                keyword: "",
                status: "",
                payment_kind: "",
                method: "",
                paid_range: "",
                paid_from: "",
                paid_to: "",
            },
            loading: false,
            error: "",
            success: "",
            detailOpen: false,
            detailLoading: false,
            detail: null,
        };
    },
    computed: {
        statusTabsUi() {
            return [
                { value: '', label: 'Tất cả trạng thái', count: this.summary.total },
                { value: 'pending', label: 'Chờ thanh toán', count: this.summary.pending },
                { value: 'paid', label: 'Đã thanh toán', count: this.summary.paid },
                { value: 'failed', label: 'Thất bại', count: this.summary.failed },
                { value: 'refunded', label: 'Đã hoàn tiền', count: this.summary.refunded },
            ];
        },
        tableColumns() {
            return [
                { key: 'payment_code', label: 'PAYMENT / BOOKING' },
                { key: 'customer', label: 'KHÁCH HÀNG' },
                { key: 'venue_cluster', label: 'CỤM SÂN' },
                { key: 'amount', label: 'SỐ TIỀN' },
                { key: 'kind_method', label: 'LOẠI / PHƯƠNG THỨC' },
                { key: 'status', label: 'TRẠNG THÁI' },
                { key: 'paid_at', label: 'PAID AT' },
                { key: 'logs_count', label: 'LOGS' },
                { key: 'actions', label: 'THAO TÁC', align: 'right' },
            ];
        },
        creditLedger() {
            if (!this.detail?.owner_wallet_ledgers) return null;
            return (
                this.detail.owner_wallet_ledgers.find(
                    (l) => l.type === "credit" && l.direction === "credit",
                ) || null
            );
        },
    },
    mounted() {
        this.loadPayments();
    },
    methods: {
        async loadPayments(page = this.meta.current_page || 1) {
            this.loading = true;
            this.error = "";
            try {
                const response = await adminPaymentService.list(
                    this.paymentFilterParams(page),
                );
                this.payments = response.data || [];
                this.summary = response.summary || this.summary;
                this.meta = response.meta || this.meta;
            } catch (error) {
                this.error =
                    error.message || "Không tải được danh sách thanh toán.";
            } finally {
                this.loading = false;
            }
        },
        applyFilters() {
            this.loadPayments(1);
        },
        resetFilters() {
            this.filters = {
                keyword: "",
                status: "",
                payment_kind: "",
                method: "",
                paid_range: "",
                paid_from: "",
                paid_to: "",
            };
            this.loadPayments(1);
        },
        changePage(page) {
            this.loadPayments(page);
        },
        paymentFilterParams(page) {
            const params = { ...this.filters, page };
            delete params.paid_range;

            if (this.filters.paid_range === "custom") {
                if (!params.paid_from) delete params.paid_from;
                if (!params.paid_to) delete params.paid_to;
                return params;
            }

            delete params.paid_from;
            delete params.paid_to;
            const range = this.resolveDateRange(this.filters.paid_range);

            if (range) {
                params.paid_from = range.from;
                params.paid_to = range.to;
            }

            return params;
        },
        resolveDateRange(value) {
            const today = new Date();
            const from = new Date(today);
            const to = new Date(today);

            if (value === "today") {
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "yesterday") {
                from.setDate(from.getDate() - 1);
                to.setDate(to.getDate() - 1);
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "last_3_days") {
                from.setDate(from.getDate() - 3);
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "last_7_days") {
                from.setDate(from.getDate() - 7);
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "last_30_days") {
                from.setDate(from.getDate() - 30);
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "this_month") {
                from.setDate(1);
                return {
                    from: this.dateInputValue(from),
                    to: this.dateInputValue(to),
                };
            }

            if (value === "last_month") {
                const firstDayThisMonth = new Date(
                    today.getFullYear(),
                    today.getMonth(),
                    1,
                );
                const lastDayLastMonth = new Date(firstDayThisMonth);
                lastDayLastMonth.setDate(0);
                const firstDayLastMonth = new Date(
                    lastDayLastMonth.getFullYear(),
                    lastDayLastMonth.getMonth(),
                    1,
                );
                return {
                    from: this.dateInputValue(firstDayLastMonth),
                    to: this.dateInputValue(lastDayLastMonth),
                };
            }

            return null;
        },
        dateInputValue(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const day = String(date.getDate()).padStart(2, "0");
            return `${year}-${month}-${day}`;
        },
        async openDetail(id) {
            this.detailOpen = true;
            this.detailLoading = true;
            this.detail = null;
            try {
                const response = await adminPaymentService.show(id);
                this.detail = response.data;
            } catch (error) {
                this.error =
                    error.message || "Không tải được chi tiết payment.";
                this.detailOpen = false;
            } finally {
                this.detailLoading = false;
            }
        },
        closeDetail() {
            this.detailOpen = false;
            this.detail = null;
        },
        statusLabel(value) {
            return (
                {
                    pending: "Chờ thanh toán",
                    paid: "Đã thanh toán",
                    failed: "Thất bại",
                    refunded: "Đã hoàn tiền",
                }[value] || value
            );
        },
        bookingStatusLabel(value) {
            return (
                {
                    pending_approval: "Chờ chủ sân duyệt",
                    pending_payment: "Chờ thanh toán",
                    confirmed: "Đã xác nhận",
                    checked_in: "Đang chơi",
                    completed: "Hoàn thành",
                    no_show: "Không check-in",
                    expired: "Hết hạn",
                    rejected: "Bị từ chối",
                    cancelled: "Đã hủy",
                }[value] || value || "—"
            );
        },
        paymentOptionLabel(value) {
            return (
                {
                    full_payment: "Thanh toán đủ",
                    deposit: "Đặt cọc",
                    wallet: "Ví SportGo",
                    no_prepay: "Trả sau",
                    cash: "Tiền mặt",
                    bank_transfer: "Chuyển khoản",
                    sepay: "Chuyển khoản QR",
                }[value] || "Không xác định"
            );
        },
        kindLabel(value) {
            return (
                { full: "Toàn bộ", deposit: "Đặt cọc", partial: "Một phần" }[
                    value
                ] || value
            );
        },
        methodLabel(value) {
            return (
                {
                    sepay: "SePay",
                    bank_transfer: "Chuyển khoản",
                    cash: "Tiền mặt",
                    wallet: "Ví",
                    mixed: "Kết hợp",
                }[value] || value
            );
        },
        formatCurrency(value) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
                maximumFractionDigits: 0,
            }).format(Number(value || 0));
        },
        formatDate(value) {
            return value ? new Date(value).toLocaleString("vi-VN") : "-";
        },
        formatBankAccount(bank) {
            if (!bank) return "-";
            const parts = [bank.bank_name, bank.account_number].filter(Boolean);
            if (bank.account_holder) parts.push(`(${bank.account_holder})`);
            return parts.join(" - ") || "-";
        },
        prettyJson(value) {
            return JSON.stringify(value, null, 2);
        },
    },
};
</script>

<style scoped>
.admin-payments {
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.page-header,
.filters,
.drawer-header,
.log-head,
.log-meta,
.pagination {
    display: flex;
    align-items: center;
}
.page-header {
    justify-content: space-between;
    gap: 16px;
}
.page-header h2 {
    margin: 0 0 4px;
    font-size: 22px;
    color: var(--admin-text);
}
.page-header p {
    margin: 0;
    color: var(--admin-muted);
    font-size: 13px;
}
.sub-line,
.detail-facts span {
    display: block;
    color: var(--admin-muted);
    font-size: 12px;
}
.filters {
    gap: 8px;
    flex-wrap: wrap;
    align-items: stretch;
}
.filters select,
.filters input {
    border: 1px solid var(--admin-border);
    border-radius: 7px;
    background: var(--admin-surface);
    color: var(--admin-text);
    padding: 9px 10px;
    font: inherit;
}
.search-field {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 290px;
    border: 1px solid var(--admin-border);
    border-radius: 7px;
    padding: 0 10px;
    background: var(--admin-surface);
}
.search-field input {
    flex: 1;
    border: 0;
    padding-inline: 0;
    outline: 0;
}
.date-range-fields {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 8px;
    border: 1px solid var(--admin-border);
    border-radius: 7px;
    background: var(--admin-surface-muted);
    color: var(--admin-muted);
}
.date-range-fields input {
    width: 142px;
    border-color: transparent;
    background: var(--admin-surface);
}
.icon-command,
.icon-only {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border-radius: 7px;
    font-weight: 400;
    cursor: pointer;
}
.icon-command {
    border: 1px solid var(--admin-border);
    background: var(--admin-surface-muted);
    color: var(--admin-text);
    padding: 9px 12px;
}
.icon-only {
    width: 34px;
    height: 34px;
    border: 1px solid var(--admin-border);
    background: var(--admin-surface);
    color: var(--admin-muted);
}
button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.alert {
    padding: 11px 13px;
    border-radius: 7px;
    font-size: 13px;
}
.alert.error {
    background: var(--admin-danger-soft);
    color: var(--admin-danger-text);
}
.alert.success {
    background: var(--admin-success-soft);
    color: var(--admin-success-text);
}
.table-wrap {
    overflow: auto;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    background: var(--admin-surface);
}
table {
    width: 100%;
    min-width: 1220px;
    border-collapse: collapse;
}
th,
td {
    padding: 12px 13px;
    border-bottom: 1px solid var(--admin-border);
    text-align: left;
    vertical-align: top;
    font-size: 13px;
}
th {
    background: var(--admin-surface-muted);
    color: var(--admin-text);
    font-weight: 400;
}
.empty {
    padding: 28px;
    text-align: center;
    color: var(--admin-muted);
}
.code-link {
    padding: 0;
    background: transparent;
    color: var(--admin-success-text);
    font-weight: 400;
    text-decoration: underline;
    border: 0;
    cursor: pointer;
}
.status-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 999px;
    background: var(--admin-border);
    color: var(--admin-text);
    font-size: 11px;
    font-weight: 400;
    text-transform: uppercase;
}
.status-pill.pending {
    background: var(--admin-warning-soft);
    color: var(--admin-warning);
}
.status-pill.pending_approval,
.status-pill.pending_payment {
    background: var(--admin-warning-soft);
    color: var(--admin-warning);
}
.status-pill.confirmed,
.status-pill.checked_in,
.status-pill.completed {
    background: var(--admin-success-soft);
    color: var(--admin-success-text);
}
.status-pill.expired,
.status-pill.rejected,
.status-pill.cancelled,
.status-pill.no_show {
    background: var(--admin-danger-soft);
    color: var(--admin-danger-text);
}
.status-pill.paid,
.status-pill.credit {
    background: var(--admin-success-soft);
    color: var(--admin-success-text);
}
.status-pill.failed,
.status-pill.refunded,
.status-pill.debit {
    background: var(--admin-danger-soft);
    color: var(--admin-danger-text);
}
.status-pill.large {
    padding: 6px 14px;
    font-size: 13px;
}
.pagination {
    justify-content: flex-end;
    gap: 12px;
    color: var(--admin-muted);
    font-size: 13px;
}

/* Drawer */
.drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 500;
    background: color-mix(in srgb, var(--admin-bg) 68%, transparent);
}
.detail-drawer {
    position: absolute;
    top: 0;
    right: 0;
    width: min(680px, 100vw);
    height: 100%;
    overflow: auto;
    background: var(--admin-surface-muted);
    box-shadow: -20px 0 50px var(--admin-shadow-sm);
    padding: 24px;
}
.drawer-header {
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
}
.drawer-header h3 {
    margin: 3px 0 0;
    font-size: 22px;
    font-weight: 400;
    color: var(--admin-text);
}
.eyebrow {
    color: var(--admin-muted);
    font-size: 11px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.drawer-loading,
.empty-block {
    padding: 24px;
    text-align: center;
    color: var(--admin-muted);
    font-size: 13px;
}

/* Status bar */
.detail-status-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.paid-time {
    color: var(--admin-muted);
    font-size: 12px;
}

/* Facts grid */
.detail-facts {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    overflow: hidden;
}
.detail-facts div {
    padding: 11px 14px;
    border-bottom: 1px solid var(--admin-surface-muted);
}
.detail-facts div:nth-child(odd) {
    border-right: 1px solid var(--admin-surface-muted);
}
.detail-facts div:nth-last-child(1),
.detail-facts div:nth-last-child(2) {
    border-bottom: 0;
}
.detail-facts span {
    display: block;
    color: var(--admin-faint);
    font-size: 11px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.detail-facts strong {
    display: block;
    margin-top: 3px;
    color: var(--admin-text);
    font-size: 13px;
    word-break: break-word;
}
.amount-highlight {
    color: var(--admin-primary) !important;
    font-size: 15px !important;
}
.mt-12 {
    margin-top: 12px;
}
.booking-flow-panel {
    margin-top: 12px;
    padding: 14px 16px;
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    background: var(--admin-surface);
}
.booking-flow-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}
.booking-flow-head h4 {
    margin: 0;
    color: var(--admin-text);
    font-size: 14px;
}
.booking-flow-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
.booking-flow-grid span {
    display: block;
    color: var(--admin-faint);
    font-size: 11px;
    text-transform: uppercase;
}
.booking-flow-grid strong {
    display: block;
    margin-top: 3px;
    color: var(--admin-text);
    font-size: 13px;
}
.booking-flow-note {
    margin: 12px 0 0;
    color: var(--admin-muted);
    font-size: 12px;
    line-height: 1.5;
}

/* Wallet credit — formula display */
.wallet-credit-section {
    margin-top: 16px;
    background: var(--admin-success-soft);
    border: 1px solid var(--admin-success-soft);
    border-radius: 10px;
    padding: 14px 16px;
}
.wallet-credit-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 12px;
    color: var(--admin-success-text);
    font-size: 14px;
    font-weight: 400;
}
.wallet-credit-body {
    background: var(--admin-surface);
    border-radius: 8px;
    padding: 14px 16px;
    border: 1px solid var(--admin-success-soft);
}
.wallet-credit-formula {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}
.formula-part {
    text-align: center;
    min-width: 90px;
}
.formula-part span {
    display: block;
    font-size: 11px;
    color: var(--admin-muted);
    font-weight: 400;
    text-transform: uppercase;
    margin-bottom: 2px;
}
.formula-part strong {
    font-size: 16px;
    font-weight: 400;
    color: var(--admin-text);
}
.formula-part.credited strong {
    color: var(--admin-primary);
}
.formula-part.result strong {
    color: var(--admin-text);
    font-size: 18px;
}
.formula-op {
    font-size: 20px;
    font-weight: 400;
    color: var(--admin-faint);
    line-height: 1;
    margin-top: 14px;
}
.wallet-note {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 14px;
    padding: 10px 14px;
    background: var(--admin-surface-muted);
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    color: var(--admin-muted);
    font-size: 13px;
}

/* Logs */
.logs-section {
    margin-top: 18px;
}
.logs-section h4 {
    margin: 0 0 9px;
    font-size: 14px;
    font-weight: 400;
    color: var(--admin-text);
}
.log-row {
    border: 1px solid var(--admin-border);
    background: var(--admin-surface);
    padding: 12px;
    margin-bottom: 8px;
    border-radius: 8px;
}
.log-head {
    justify-content: space-between;
    gap: 12px;
}
.log-head time,
.log-meta {
    color: var(--admin-muted);
    font-size: 11px;
}
.log-meta {
    gap: 12px;
    margin-top: 5px;
    display: flex;
    align-items: center;
}
.error-text {
    color: var(--admin-danger-text);
}
details {
    margin-top: 8px;
}
summary {
    cursor: pointer;
    color: var(--admin-muted);
    font-size: 12px;
    font-weight: 400;
}
pre {
    max-height: 250px;
    overflow: auto;
    padding: 10px;
    background: var(--admin-text);
    color: var(--admin-success-soft);
    border-radius: 6px;
    font-size: 11px;
    white-space: pre-wrap;
}

@media (max-width: 600px) {
    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }
    .detail-facts {
        grid-template-columns: 1fr;
    }
    .detail-facts div:nth-child(odd) {
        border-right: 0;
    }
    .wallet-credit-formula {
        flex-direction: column;
        gap: 6px;
    }
    .formula-op {
        margin-top: 0;
    }
    .search-field {
        min-width: 100%;
    }
}

.profile-section-card.payments-main-content {
    background: var(--admin-surface, #ffffff);
    border: 1px solid var(--admin-border-soft, #e2e8f0);
    border-radius: 0;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.table-wrap {
    border: none !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}
</style>

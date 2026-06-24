<template>
    <section class="admin-payments">
        <header class="page-header">
            <div>
                <h2>Theo dõi thanh toán booking</h2>
                <p>
                    Đối soát payment attempt, gateway logs và tiền hệ thống thu
                    hộ chủ sân.
                </p>
            </div>
            <button
                class="icon-command"
                type="button"
                :disabled="loading"
                title="Tải lại"
                aria-label="Tải lại"
                @click="loadPayments"
            >
                <AppIcon name="refresh" size="18" />
            </button>
        </header>

        <form class="filters" @submit.prevent="applyFilters">
            <label class="search-field">
                <AppIcon name="search" size="17" />
                <input
                    v-model.trim="filters.keyword"
                    type="search"
                    placeholder="Mã payment, booking, khách, cụm sân..."
                />
            </label>
            <select v-model="filters.status">
                <option value="">Tất cả trạng thái</option>
                <option value="pending">Chờ thanh toán</option>
                <option value="paid">Đã thanh toán</option>
                <option value="failed">Thất bại</option>
                <option value="refunded">Đã hoàn tiền</option>
            </select>
            <select v-model="filters.payment_kind">
                <option value="">Tất cả loại</option>
                <option value="full">Thanh toán toàn bộ</option>
                <option value="deposit">Đặt cọc</option>
                <option value="partial">Thanh toán một phần</option>
            </select>
            <select v-model="filters.method">
                <option value="">Tất cả phương thức</option>
                <option value="sepay">SePay</option>
                <option value="bank_transfer">Chuyển khoản</option>
                <option value="wallet">Ví</option>
                <option value="mixed">Kết hợp</option>
                <option value="cash">Tiền mặt</option>
            </select>
            <select v-model="filters.paid_range">
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
            <div
                v-if="filters.paid_range === 'custom'"
                class="date-range-fields"
                aria-label="Khoảng ngày thanh toán tùy chỉnh"
            >
                <input
                    v-model="filters.paid_from"
                    type="date"
                    title="Thanh toán từ ngày"
                />
                <span>đến</span>
                <input
                    v-model="filters.paid_to"
                    type="date"
                    title="Thanh toán đến ngày"
                    :min="filters.paid_from"
                />
            </div>
            <ActionIconButton
                icon="filter"
                label="Lọc danh sách"
                variant="primary"
                type="submit"
            />
            <ActionIconButton
                icon="refresh"
                label="Xóa lọc"
                @click="resetFilters"
            />
        </form>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="success" class="alert success">{{ success }}</div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Payment / Booking</th>
                        <th>Khách hàng</th>
                        <th>Cụm sân</th>
                        <th>Số tiền</th>
                        <th>Loại / Phương thức</th>
                        <th>Trạng thái</th>
                        <th>Paid at</th>
                        <th>Logs</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td class="empty" colspan="9">Đang tải giao dịch...</td>
                    </tr>
                    <tr v-else-if="payments.length === 0">
                        <td class="empty" colspan="9">
                            Không có giao dịch phù hợp.
                        </td>
                    </tr>
                    <tr v-for="payment in payments" :key="payment.id">
                        <td>
                            <button
                                class="code-link"
                                type="button"
                                @click="openDetail(payment.id)"
                            >
                                {{ payment.payment_code }}
                            </button>
                            <span class="sub-line">{{
                                payment.booking?.booking_code || "-"
                            }}</span>
                        </td>
                        <td>
                            {{
                                payment.customer?.full_name ||
                                payment.customer?.username ||
                                "-"
                            }}
                            <span class="sub-line">{{
                                payment.customer?.email ||
                                payment.customer?.phone ||
                                "-"
                            }}</span>
                        </td>
                        <td>{{ payment.venue_cluster?.name || "-" }}</td>
                        <td>
                            {{
                                formatCurrency(payment.amount)
                            }}
                            <span v-if="['sepay', 'bank_transfer', 'mixed'].includes(payment.method)" class="sub-line"
                                >Gateway:
                                {{
                                    formatCurrency(payment.gateway_amount)
                                }}</span
                            >
                        </td>
                        <td>
                            <span>{{ kindLabel(payment.payment_kind) }}</span>
                            <span class="sub-line">{{
                                methodLabel(payment.method)
                            }}</span>
                        </td>
                        <td>
                            <span class="status-pill" :class="payment.status">{{
                                statusLabel(payment.status)
                            }}</span>
                        </td>
                        <td>{{ formatDate(payment.paid_at) }}</td>
                        <td>{{ payment.logs_count }}</td>
                        <td>
                            <button
                                class="icon-only"
                                type="button"
                                title="Xem chi tiết"
                                @click="openDetail(payment.id)"
                            >
                                <AppIcon name="eye" size="17" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
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
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import { adminPaymentService } from "../../services/adminPayments.js";

export default {
    name: "AdminPayments",
    components: { ActionIconButton, AppIcon },
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
    border: 1px solid #dbe2ea;
    border-radius: 7px;
    background: var(--admin-surface, #fff);
    color: var(--admin-text);
    padding: 9px 10px;
    font: inherit;
}
.search-field {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 290px;
    border: 1px solid #dbe2ea;
    border-radius: 7px;
    padding: 0 10px;
    background: var(--admin-surface, #fff);
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
    border: 1px solid #dbe2ea;
    border-radius: 7px;
    background: var(--admin-surface-muted);
    color: var(--admin-muted);
}
.date-range-fields input {
    width: 142px;
    border-color: transparent;
    background: var(--admin-surface, #fff);
}
.icon-command,
.icon-only {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border-radius: 7px;
    font-weight: 700;
    cursor: pointer;
}
.icon-command {
    border: 1px solid #dbe2ea;
    background: var(--admin-surface-muted);
    color: var(--admin-text);
    padding: 9px 12px;
}
.icon-only {
    width: 34px;
    height: 34px;
    border: 1px solid #dbe2ea;
    background: var(--admin-surface, #fff);
    color: var(--admin-faint);
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
    background: #fef2f2;
    color: #b91c1c;
}
.alert.success {
    background: #ecfdf5;
    color: #047857;
}
.table-wrap {
    overflow: auto;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    background: var(--admin-surface, #fff);
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
    font-weight: 800;
}
.empty {
    padding: 28px;
    text-align: center;
    color: var(--admin-muted);
}
.code-link {
    padding: 0;
    background: transparent;
    color: #15803d;
    font-weight: 800;
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
    font-weight: 800;
    text-transform: uppercase;
}
.status-pill.pending {
    background: #fef3c7;
    color: #92400e;
}
.status-pill.paid,
.status-pill.credit {
    background: #dcfce7;
    color: #166534;
}
.status-pill.failed,
.status-pill.refunded,
.status-pill.debit {
    background: #fee2e2;
    color: #991b1b;
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
    background: rgba(15, 23, 42, 0.48);
}
.detail-drawer {
    position: absolute;
    top: 0;
    right: 0;
    width: min(680px, 100vw);
    height: 100%;
    overflow: auto;
    background: var(--admin-surface-muted);
    box-shadow: -20px 0 50px rgba(15, 23, 42, 0.18);
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
    font-weight: 800;
    color: var(--admin-text);
}
.eyebrow {
    color: var(--admin-muted);
    font-size: 11px;
    font-weight: 800;
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
    background: var(--admin-surface, #fff);
    border: 1px solid var(--admin-border);
    border-radius: 10px;
    overflow: hidden;
}
.detail-facts div {
    padding: 11px 14px;
    border-bottom: 1px solid #f1f5f9;
}
.detail-facts div:nth-child(odd) {
    border-right: 1px solid #f1f5f9;
}
.detail-facts div:nth-last-child(1),
.detail-facts div:nth-last-child(2) {
    border-bottom: 0;
}
.detail-facts span {
    display: block;
    color: var(--admin-faint);
    font-size: 11px;
    font-weight: 600;
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
    color: #16a34a !important;
    font-size: 15px !important;
}
.mt-12 {
    margin-top: 12px;
}

/* Wallet credit — formula display */
.wallet-credit-section {
    margin-top: 16px;
    background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    padding: 14px 16px;
}
.wallet-credit-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 12px;
    color: #166534;
    font-size: 14px;
    font-weight: 800;
}
.wallet-credit-body {
    background: var(--admin-surface, #fff);
    border-radius: 8px;
    padding: 14px 16px;
    border: 1px solid #d1fae5;
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
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 2px;
}
.formula-part strong {
    font-size: 16px;
    font-weight: 800;
    color: var(--admin-text);
}
.formula-part.credited strong {
    color: #16a34a;
}
.formula-part.result strong {
    color: var(--admin-text);
    font-size: 18px;
}
.formula-op {
    font-size: 20px;
    font-weight: 800;
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
    font-weight: 800;
    color: var(--admin-text);
}
.log-row {
    border: 1px solid var(--admin-border);
    background: var(--admin-surface, #fff);
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
    color: #b91c1c;
}
details {
    margin-top: 8px;
}
summary {
    cursor: pointer;
    color: var(--admin-faint);
    font-size: 12px;
    font-weight: 700;
}
pre {
    max-height: 250px;
    overflow: auto;
    padding: 10px;
    background: #0f172a;
    color: #d1fae5;
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
</style>

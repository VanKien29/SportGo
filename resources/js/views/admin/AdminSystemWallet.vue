<template>
    <section class="system-wallet-page" :class="{ embedded }">
        <header v-if="!embedded" class="page-header">
            <div>
                <h2>Dashboard tài chính hệ thống</h2>
                <p>
                    Theo dõi doanh thu, chi phí khuyến mãi, ngân sách cảnh báo
                    và lịch sử đối soát ATM.
                </p>
            </div>
            <button
                class="sync-button"
                type="button"
                :disabled="loading || syncing"
                @click="syncWallet"
            >
                <AppIcon name="refresh" size="17" />
                <span>{{ syncing ? "Đang đồng bộ" : "Đồng bộ ATM" }}</span>
            </button>
        </header>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="success" class="alert success">{{ success }}</div>

        <section v-if="!embedded" class="account-card">
            <div>
                <span class="eyebrow">Tài khoản hệ thống</span>
                <h3>{{ account?.bank_name || "Chưa có tài khoản" }}</h3>
                <p>
                    {{ account?.account_number || "-" }} ·
                    {{
                        account?.account_holder ||
                        account?.account_holder_name ||
                        "-"
                    }}
                </p>
            </div>
            <div class="sync-meta">
                <span>Đồng bộ ATM gần nhất</span>
                <strong>{{ formatDateTime(wallet?.bank_synced_at) }}</strong>
            </div>
        </section>

        <section class="metric-grid">
            <article class="metric-card primary">
                <span>Doanh thu kỳ này</span>
                <strong>{{ money(revenueSummary?.total_revenue) }}</strong>
            </article>
            <article class="metric-card info">
                <span>Chi phí khuyến mãi</span>
                <strong>{{ money(promotionExpenses?.total) }}</strong>
            </article>
            <article
                class="metric-card"
                :class="{ danger: wallet?.is_low_balance }"
            >
                <span>Lãi ròng tham chiếu</span>
                <strong>{{ money(netRevenue) }}</strong>
            </article>
            <article
                class="metric-card warning"
                :class="{ danger: promotionBudget?.is_over_budget }"
            >
                <span>Ngưỡng cảnh báo</span>
                <strong>{{ money(promotionBudget?.budget) }}</strong>
            </article>
        </section>

        <section class="finance-grid">
            <article class="finance-card">
                <h3>Doanh thu</h3>
                <div class="finance-row">
                    <span>Phí nền tảng đã thu</span>
                    <strong>{{
                        money(revenueSummary?.platform_fees?.total_paid)
                    }}</strong>
                </div>
                <div class="finance-row">
                    <span>Booking online</span>
                    <strong>{{
                        money(revenueSummary?.booking_payments?.total)
                    }}</strong>
                </div>
                <div class="finance-row muted">
                    <span>Phí nền tảng quá hạn</span>
                    <strong>{{
                        money(revenueSummary?.platform_fees?.overdue_amount)
                    }}</strong>
                </div>
            </article>

            <article class="finance-card">
                <h3>Chi phí khuyến mãi</h3>
                <div class="finance-row">
                    <span>Voucher hệ thống</span>
                    <strong>{{
                        money(promotionExpenses?.voucher_total)
                    }}</strong>
                </div>
                <div class="finance-row">
                    <span>Hoàn vào ví</span>
                    <strong>{{
                        money(promotionExpenses?.refund_total)
                    }}</strong>
                </div>
                <div class="finance-row muted">
                    <span>Đã dùng ngân sách</span>
                    <strong>{{ usageText }}</strong>
                </div>
            </article>

            <article class="finance-card">
                <h3>ATM đối soát</h3>
                <div class="finance-row">
                    <span>Số dư ATM</span>
                    <strong>{{ money(wallet?.bank_balance) }}</strong>
                </div>
                <div class="finance-row">
                    <span>Đồng bộ gần nhất</span>
                    <strong>{{
                        formatDateTime(wallet?.bank_synced_at)
                    }}</strong>
                </div>
                <div class="finance-row muted">
                    <span>Số tham chiếu cũ</span>
                    <strong>{{ money(wallet?.reference_balance) }}</strong>
                </div>
            </article>
        </section>

        <section class="settings-card">
            <div>
                <h3>Ngân sách khuyến mãi</h3>
            </div>
            <form class="settings-form" @submit.prevent="saveSettings">
                <label class="toggle-row">
                    <input
                        v-model="settings.is_alert_enabled"
                        type="checkbox"
                    />
                    <span>Bật cảnh báo</span>
                </label>
                <label class="amount-input">
                    <span>Ngân sách</span>
                    <input
                        v-model.number="settings.promotion_budget"
                        type="number"
                        min="0"
                        step="1000"
                    />
                </label>
                <label class="amount-input">
                    <span>Kỳ ngân sách</span>
                    <select v-model="settings.budget_period">
                        <option value="week">Tuần</option>
                        <option value="month">Tháng</option>
                        <option value="year">Năm</option>
                    </select>
                </label>
                <button
                    class="secondary-button"
                    type="submit"
                    :disabled="saving"
                >
                    <AppIcon name="check" size="16" />
                    <span>{{ saving ? "Đang lưu" : "Lưu" }}</span>
                </button>
            </form>
        </section>

        <section class="ledger-card">
            <div class="ledger-head">
                <div>
                    <h3>Lịch sử ví hệ thống</h3>
                    <p>
                        Nạp quỹ, trừ voucher hệ thống và giao dịch ATM dùng để
                        đối soát.
                    </p>
                </div>
                <form class="filters" @submit.prevent="loadWallet(1)">
                    <select v-model="filters.period_type">
                        <option value="week">Tuần này</option>
                        <option value="month">Tháng này</option>
                        <option value="year">Năm nay</option>
                    </select>
                    <select v-model="filters.direction">
                        <option value="">Tất cả chiều tiền</option>
                        <option value="in">Tiền vào</option>
                        <option value="out">Tiền ra</option>
                    </select>
                    <select v-model="filters.entry_kind">
                        <option value="">Tất cả loại ghi nhận</option>
                        <option value="voucher_subsidy">
                            Trừ voucher hệ thống
                        </option>
                        <option value="bank_in">ATM tiền vào</option>
                        <option value="bank_out">ATM tiền ra</option>
                        <option value="manual_out">Điều chỉnh giảm</option>
                    </select>
                    <button class="filter-button" type="submit">
                        <AppIcon name="filter" size="16" />
                        <span>Lọc</span>
                    </button>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Chiều tiền</th>
                            <th>Loại</th>
                            <th>Số tiền</th>
                            <th>Số dư quỹ</th>
                            <th>Tham chiếu</th>
                            <th>Mô tả</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="7" class="empty-row">
                                Đang tải ví hệ thống...
                            </td>
                        </tr>
                        <tr v-else-if="!ledgers.length">
                            <td colspan="7" class="empty-row">
                                Chưa có lịch sử ví hệ thống.
                            </td>
                        </tr>
                        <template v-else>
                            <tr v-for="ledger in ledgers" :key="ledger.id">
                                <td>
                                    {{ formatDateTime(ledger.transacted_at) }}
                                </td>
                                <td>
                                    <span
                                        class="badge"
                                        :class="
                                            ledger.direction === 'in'
                                                ? 'in'
                                                : 'out'
                                        "
                                    >
                                        {{
                                            ledger.direction === "in"
                                                ? "Tiền vào"
                                                : "Tiền ra"
                                        }}
                                    </span>
                                </td>
                                <td>{{ kindLabel(ledger.entry_kind) }}</td>
                                <td class="amount" :class="ledger.direction">
                                    {{ money(ledger.amount) }}
                                </td>
                                <td>
                                    <strong>{{
                                        money(ledger.balance_after)
                                    }}</strong>
                                    <small
                                        >Trước:
                                        {{
                                            money(ledger.balance_before)
                                        }}</small
                                    >
                                </td>
                                <td>
                                    <strong>{{
                                        ledger.transaction_ref || "-"
                                    }}</strong>
                                    <small
                                        >{{ ledger.reference_type || "-" }} ·
                                        {{ ledger.reference_id || "-" }}</small
                                    >
                                </td>
                                <td>{{ ledger.description || "-" }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="pagination" v-if="meta.last_page > 1">
                <button
                    type="button"
                    :disabled="meta.current_page <= 1"
                    @click="loadWallet(meta.current_page - 1)"
                >
                    Trước
                </button>
                <span
                    >Trang {{ meta.current_page }} / {{ meta.last_page }}</span
                >
                <button
                    type="button"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="loadWallet(meta.current_page + 1)"
                >
                    Sau
                </button>
            </div>
        </section>
    </section>
</template>

<script>
import { adminSystemWalletService } from "../../services/adminSystemWallet.js";

export default {
    name: "AdminSystemWallet",
    props: {
        embedded: {
            type: Boolean,
            default: false,
        },
    },
    data() {
        return {
            account: null,
            wallet: null,
            promotionExpenses: null,
            revenueSummary: null,
            promotionBudget: null,
            ledgers: [],
            meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
            filters: {
                direction: "",
                entry_kind: "",
                period_type: "month",
            },
            settings: {
                is_alert_enabled: true,
                promotion_budget: 0,
                budget_period: "month",
            },
            loading: false,
            syncing: false,
            saving: false,
            error: "",
            success: "",
        };
    },
    mounted() {
        this.loadWallet();
    },
    computed: {
        netRevenue() {
            return (
                Number(this.revenueSummary?.total_revenue || 0) -
                Number(this.promotionExpenses?.total || 0)
            );
        },
        usageText() {
            if (
                this.promotionBudget?.usage_percent === null ||
                this.promotionBudget?.usage_percent === undefined
            ) {
                return "Chưa đặt";
            }

            return `${this.promotionBudget.usage_percent}%`;
        },
    },
    methods: {
        async loadWallet(page = 1) {
            this.loading = true;
            this.error = "";
            try {
                const response = await adminSystemWalletService.show({
                    ...this.filters,
                    page,
                    per_page: this.meta.per_page,
                });
                this.account = response.account;
                this.wallet = response.wallet;
                this.promotionExpenses = response.promotion_expenses;
                this.revenueSummary = response.revenue_summary;
                this.promotionBudget = response.promotion_budget;
                this.ledgers = response.ledgers?.data || [];
                this.meta = {
                    current_page: response.ledgers?.current_page || 1,
                    last_page: response.ledgers?.last_page || 1,
                    per_page: response.ledgers?.per_page || 20,
                    total: response.ledgers?.total || 0,
                };
                this.settings = {
                    is_alert_enabled: Boolean(
                        response.wallet?.is_alert_enabled,
                    ),
                    promotion_budget: Number(
                        response.wallet?.promotion_monthly_budget || 0,
                    ),
                    budget_period:
                        response.wallet?.budget_period_type || "month",
                };
            } catch (error) {
                this.error = error.message || "Không tải được ví hệ thống.";
            } finally {
                this.loading = false;
            }
        },
        async syncWallet() {
            this.syncing = true;
            this.error = "";
            this.success = "";
            try {
                const response = await adminSystemWalletService.sync();
                this.success = response.message || "Đã đồng bộ ví hệ thống.";
                await this.loadWallet(this.meta.current_page);
            } catch (error) {
                this.error = error.message || "Không đồng bộ được ví hệ thống.";
            } finally {
                this.syncing = false;
            }
        },
        async saveSettings() {
            this.saving = true;
            this.error = "";
            this.success = "";
            try {
                const response = await adminSystemWalletService.updateSettings(
                    this.settings,
                );
                this.wallet = response.wallet;
                this.success =
                    response.message || "Đã lưu ngân sách khuyến mãi.";
                await this.loadWallet(this.meta.current_page);
            } catch (error) {
                this.error =
                    error.message || "Không lưu được ngân sách khuyến mãi.";
            } finally {
                this.saving = false;
            }
        },
        money(value) {
            return `${Number(value || 0).toLocaleString("vi-VN")} đ`;
        },
        formatDateTime(value) {
            if (!value) return "-";
            return new Intl.DateTimeFormat("vi-VN", {
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
            }).format(new Date(value));
        },
        kindLabel(kind) {
            return (
                {
                    topup: "Nạp quỹ",
                    voucher_subsidy: "Trừ voucher hệ thống",
                    bank_in: "ATM tiền vào",
                    bank_out: "ATM tiền ra",
                    manual_out: "Điều chỉnh giảm",
                    refund_to_wallet: "Hoàn ví",
                }[kind] ||
                kind ||
                "-"
            );
        },
    },
};
</script>

<style scoped>
.system-wallet-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 28px;
    color: #102018;
}

.system-wallet-page.embedded {
    padding: 0;
}

.page-header,
.account-card,
.topup-card,
.settings-card,
.ledger-card {
    border: 1px solid #cfe2d2;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 12px 28px rgba(18, 68, 35, 0.07);
}

.page-header,
.account-card,
.topup-card,
.settings-card,
.ledger-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.page-header {
    padding: 20px 22px;
}

h2,
h3,
p {
    margin: 0;
}

h2 {
    font-size: 24px;
    font-weight: 800;
}

h3 {
    font-size: 18px;
    font-weight: 800;
}

p,
small {
    color: #64756b;
}

button,
select,
input {
    border: 1px solid #cfe2d2;
    border-radius: 8px;
    background: #fff;
    color: #102018;
    font: inherit;
}

button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 0 16px;
    font-weight: 800;
    cursor: pointer;
}

button:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

select,
input {
    min-height: 42px;
    padding: 0 12px;
}

.sync-button,
.filter-button,
.secondary-button {
    border-color: #16a34a;
    background: #16a34a;
    color: #fff;
}

.alert {
    border-radius: 8px;
    padding: 12px 14px;
    font-weight: 700;
}

.alert.error {
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #b91c1c;
}

.alert.success {
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
}

.account-card,
.topup-card,
.settings-card {
    padding: 18px 20px;
}

.eyebrow {
    display: block;
    color: #15803d;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.sync-meta {
    text-align: right;
}

.sync-meta span,
.sync-meta strong {
    display: block;
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.finance-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.finance-card {
    border: 1px solid #dbe7de;
    border-radius: 8px;
    background: #fff;
    padding: 18px;
}

.finance-card h3 {
    margin-bottom: 14px;
}

.finance-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    border-top: 1px solid #eef4ef;
    padding: 12px 0;
}

.finance-row:first-of-type {
    border-top: 0;
}

.finance-row span {
    color: #64756b;
    font-weight: 700;
}

.finance-row strong {
    color: #102018;
    font-weight: 900;
    text-align: right;
}

.finance-row.muted strong {
    color: #15803d;
}

.metric-card {
    min-height: 112px;
    border: 1px solid #dbe7de;
    border-radius: 8px;
    background: #fff;
    padding: 18px;
}

.metric-card span,
.metric-card strong {
    display: block;
}

.metric-card span {
    color: #64756b;
    font-size: 13px;
    font-weight: 700;
}

.metric-card strong {
    margin-top: 16px;
    font-size: 25px;
    font-weight: 900;
}

.metric-card.primary {
    background: #effaf2;
    border-color: #86efac;
}

.metric-card.warning {
    background: #fff8e7;
    border-color: #fde68a;
}

.metric-card.info {
    background: #eff6ff;
    border-color: #bfdbfe;
}

.metric-card.danger {
    background: #fff1f2;
    border-color: #fecaca;
}

.settings-form,
.topup-form,
.filters {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.toggle-row {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 800;
}

.amount-input {
    display: grid;
    gap: 5px;
    color: #64756b;
    font-size: 12px;
    font-weight: 800;
}

.amount-input.note {
    min-width: 320px;
    flex: 1;
}

.ledger-card {
    overflow: hidden;
}

.ledger-head {
    padding: 18px 20px;
    border-bottom: 1px solid #dbe7de;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1080px;
}

th,
td {
    border-bottom: 1px solid #e4ece6;
    padding: 13px 14px;
    text-align: left;
    vertical-align: top;
}

th {
    background: #f4faf5;
    color: #425349;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}

td strong,
td small,
td span {
    display: block;
}

.badge {
    width: fit-content;
    border-radius: 999px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 900;
}

.badge.in {
    background: #dcfce7;
    color: #15803d;
}

.badge.out {
    background: #fee2e2;
    color: #b91c1c;
}

.amount {
    font-weight: 900;
}

.amount.in {
    color: #15803d;
}

.amount.out {
    color: #b91c1c;
}

.empty-row {
    height: 84px;
    text-align: center;
    color: #64756b;
    font-weight: 800;
}

.pagination {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
}

@media (max-width: 1100px) {
    .metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .finance-grid {
        grid-template-columns: 1fr;
    }

    .page-header,
    .account-card,
    .topup-card,
    .settings-card,
    .ledger-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .sync-meta {
        text-align: left;
    }
}
</style>

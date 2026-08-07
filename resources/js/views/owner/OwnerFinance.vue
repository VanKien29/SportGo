<template>
    <div class="cluster-profile-surface standalone">
        <!-- Floating Add Button -->
        <div v-if="activeTab === 'withdrawals' && withdrawableWallets.length && bankAccounts.length" class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
            <button class="btn-float-add" type="button" @click="openWithdrawalModal(withdrawableWallets[0])" title="Yêu cầu rút tiền">
                <AppIcon name="plus" size="20" />
                <span class="btn-float-text">Yêu cầu rút tiền</span>
            </button>
        </div>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="notice" class="alert success">{{ notice }}</div>

        <!-- Top Integrated Tabs Row -->
        <div class="finance-header-hero">
            <div class="hero-integrated-tabs">
                <AppTabs
                    :tabs="financeTabsForAppTabs"
                    :model-value="activeTab"
                    @update:model-value="selectFinanceTab"
                />
            </div>
        </div>

        <div class="profile-section-card finance-main-content">

            <div v-if="loading" class="table-state-card">
                <div class="spinner-sm"></div>
                <span>Đang tải dữ liệu tài chính...</span>
            </div>

            <template v-else-if="activeTab === 'wallets'">
                <div class="services-table-section">
                    <div v-if="wallets.length === 0" class="table-state-card">
                        <span>Chưa có doanh thu online để tạo ví.</span>
                    </div>
                    <div v-else class="services-table-wrapper">
                        <table class="services-data-table wallet-table">
                            <thead>
                                <tr>
                                    <th>Cụm sân</th>
                                    <th>Số dư khả dụng</th>
                                    <th>Đang chờ rút</th>
                                    <th>Tổng thu online</th>
                                    <th>Đã rút</th>
                                    <th class="action-col">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="wallet in wallets" :key="wallet.id">
                                    <td>
                                        <strong class="venue-name">{{
                                            wallet.venue_cluster?.name || "Ví chung"
                                        }}</strong>
                                        <small class="cell-sub">{{
                                            wallet.venue_cluster?.address ||
                                            "Ví theo cụm sân"
                                        }}</small>
                                    </td>
                                    <td class="money positive">
                                        <strong>{{ formatCurrency(wallet.available_balance) }}</strong>
                                    </td>
                                    <td class="money pending">
                                        {{ formatCurrency(wallet.pending_withdrawal_balance) }}
                                    </td>
                                    <td class="money">
                                        {{ formatCurrency(wallet.total_earned) }}
                                    </td>
                                    <td class="money">
                                        {{ formatCurrency(wallet.total_withdrawn) }}
                                    </td>
                                    <td class="action-col">
                                        <TableActionGroup>
                                            <ActionIconButton
                                                icon="history"
                                                label="Xem dòng tiền"
                                                @click="openLedgers(wallet)"
                                            />
                                            <ActionIconButton
                                                icon="banknote"
                                                label="Tạo yêu cầu rút"
                                                variant="primary"
                                                :disabled="
                                                    Number(
                                                        wallet.available_balance,
                                                    ) < minimumWithdrawal ||
                                                    bankAccounts.length === 0
                                                "
                                                @click="openWithdrawalModal(wallet)"
                                            />
                                        </TableActionGroup>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    v-if="bankAccounts.length === 0 && wallets.length"
                    class="alert warning"
                >
                    Chưa có tài khoản ngân hàng đang hoạt động. Hãy hoàn tất thông
                    tin nhận tiền trong Hồ sơ & Hợp đồng trước khi tạo yêu cầu.
                </div>
            </template>

            <template v-else-if="activeTab === 'ledgers'">
                <div class="services-table-section">

                    <div v-if="ledgers.length === 0" class="table-state-card">
                        <span>Chưa có giao dịch dòng tiền.</span>
                    </div>
                    <div v-else class="services-table-wrapper">
                        <table class="services-data-table ledger-table">
                            <thead>
                                <tr>
                                    <th>Ví</th>
                                    <th>Loại giao dịch</th>
                                    <th>Số tiền</th>
                                    <th>Số dư sau GD</th>
                                    <th>Mô tả</th>
                                    <th>Thời gian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="ledger in ledgers" :key="ledger.id">
                                    <td>
                                        <strong>{{
                                            ledger.wallet?.venue_cluster?.name ||
                                            "Ví chung"
                                        }}</strong>
                                    </td>
                                    <td>
                                        <span
                                            class="status-pill"
                                            :class="ledger.type"
                                        >
                                            {{ ledgerType(ledger.type) }}
                                        </span>
                                    </td>
                                    <td class="money" :class="ledger.type">
                                        <strong>{{ ledger.type === 'debit' || ledger.type === 'hold' ? '-' : '+' }}{{ formatCurrency(ledger.amount) }}</strong>
                                    </td>
                                    <td class="money">
                                        {{ formatCurrency(ledger.balance_after) }}
                                    </td>
                                    <td class="cell-desc">
                                        <span class="desc-text" :title="ledger.description">
                                            {{ ledger.description || "-" }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="cell-sub">{{ formatDateTime(ledger.created_at) }}</small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <PaginationBar
                        :meta="ledgerMeta"
                        @change-page="loadLedgers"
                    />
                </div>
            </template>

            <template v-else-if="activeTab === 'withdrawals'">
                <div class="services-table-section">

                    <div v-if="withdrawals.length === 0" class="table-state-card">
                        <span>Chưa có yêu cầu rút tiền.</span>
                    </div>
                    <div v-else class="services-table-wrapper">
                        <table class="services-data-table withdrawal-table">
                            <thead>
                                <tr>
                                    <th>Mã YC</th>
                                    <th>Ví rút</th>
                                    <th>Số tiền</th>
                                    <th>Ngân hàng nhận</th>
                                    <th>Trạng thái</th>
                                    <th>Thời gian</th>
                                    <th class="action-col">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in withdrawals"
                                    :key="item.id"
                                >
                                    <td>
                                        <span class="code">{{ item.code }}</span>
                                    </td>
                                    <td>
                                        <strong>{{
                                            item.wallet?.venue_cluster?.name ||
                                            "Ví chung"
                                        }}</strong>
                                    </td>
                                    <td class="money">
                                        <strong>{{
                                            formatCurrency(item.amount)
                                        }}</strong>
                                    </td>
                                    <td>
                                        <strong>{{ item.bank_name }}</strong>
                                        <small class="cell-sub"
                                            >{{ item.account_holder_name }} ·
                                            {{
                                                maskedAccount(
                                                    item.account_number,
                                                )
                                            }}</small
                                        >
                                    </td>
                                    <td>
                                        <span
                                            class="status-pill"
                                            :class="item.status"
                                        >
                                            {{ withdrawalStatus(item.status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <small class="cell-sub">{{ formatDateTime(item.created_at) }}</small>
                                    </td>
                                    <td class="action-col">
                                        <TableActionGroup>
                                            <ActionIconButton
                                                v-if="canCancelWithdrawal(item)"
                                                icon="x"
                                                label="Hủy yêu cầu"
                                                variant="danger"
                                                :disabled="cancellingId === item.id"
                                                @click="cancelWithdrawal(item)"
                                            />
                                        </TableActionGroup>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <PaginationBar
                        :meta="withdrawalMeta"
                        @change-page="loadWithdrawals"
                    />
                </div>
            </template>
        </div>

        <!-- Withdraw Modal -->
        <div
            v-if="showWithdrawModal"
            class="modal-backdrop"
            @click.self="closeWithdrawalModal"
        >
            <form class="withdraw-modal" @submit.prevent="submitWithdrawal">
                <header class="modal-header">
                    <div>
                        <h2>Tạo yêu cầu rút tiền</h2>
                        <p>SportGo sẽ duyệt và chuyển khoản vào ngân hàng đã đăng ký.</p>
                    </div>
                    <button
                        class="close-btn"
                        type="button"
                        @click="closeWithdrawalModal"
                    >
                        ×
                    </button>
                </header>

                <div v-if="modalError" class="alert error inline-alert">{{ modalError }}</div>

                <div class="modal-body">
                    <label>
                        <span>Ví rút tiền</span>
                        <select
                            v-model="withdrawForm.wallet_id"
                            required
                            @change="onWalletSelectChange"
                        >
                            <option
                                v-for="w in withdrawableWallets"
                                :key="w.id"
                                :value="w.id"
                            >
                                {{ w.venue_cluster?.name || "Ví chung" }} · Số dư {{ formatCurrency(w.available_balance) }}
                            </option>
                        </select>
                    </label>

                    <label>
                        <span>Ngân hàng nhận tiền</span>
                        <select v-model="withdrawForm.bank_account_id" required>
                            <option
                                v-for="account in bankAccounts"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.bank_name }} - {{ account.account_number }} ({{ account.account_holder_name }})
                            </option>
                        </select>
                    </label>

                    <label>
                        <span>Số tiền rút (Tối thiểu {{ formatCurrency(minimumWithdrawal) }})</span>
                        <input
                            v-model.number="withdrawForm.amount"
                            type="number"
                            :min="minimumWithdrawal"
                            :max="selectedWalletBalance"
                            step="1000"
                            required
                        />
                    </label>

                    <label>
                        <span>Ghi chú cho BQT (Không bắt buộc)</span>
                        <textarea
                            v-model.trim="withdrawForm.owner_note"
                            rows="3"
                            placeholder="Ghi chú thêm nếu cần"
                        ></textarea>
                    </label>
                </div>

                <footer class="modal-actions">
                    <button
                        class="secondary-btn"
                        type="button"
                        :disabled="submitting"
                        @click="closeWithdrawalModal"
                    >
                        Đóng
                    </button>
                    <button
                        class="primary-btn"
                        type="submit"
                        :disabled="submitting"
                    >
                        {{ submitting ? "Đang gửi..." : "Gửi yêu cầu" }}
                    </button>
                </footer>
            </form>
        </div>
    </div>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import AppTabs from "../../components/common/AppTabs.vue";
import PaginationBar from "../../components/PaginationBar.vue";
import TableActionGroup from "../../components/TableActionGroup.vue";
import { api } from "../../services/api.js";

export default {
    name: "OwnerFinance",
    components: { ActionIconButton, AppIcon, AppTabs, TableActionGroup, PaginationBar },
    data() {
        return {
            activeTab: "wallets",
            wallets: [],
            ledgers: [],
            withdrawals: [],
            bankAccounts: [],
            ledgerMeta: { current_page: 1, last_page: 1, total: 0 },
            withdrawalMeta: { current_page: 1, last_page: 1, total: 0 },
            ledgerFilters: { wallet_id: "" },
            withdrawalFilters: { wallet_id: "", status: "" },
            minimumWithdrawal: 100000,
            loading: false,
            submitting: false,
            cancellingId: null,
            error: "",
            notice: "",
            modalError: "",
            showWithdrawModal: false,
            withdrawForm: {
                wallet_id: "",
                bank_account_id: "",
                amount: 100000,
                owner_note: "",
            },
            showScrollTop: false,
        };
    },
    computed: {
        financeTabsForAppTabs() {
            return [
                { key: "wallets", value: "wallets", label: "Số dư ví" },
                { key: "ledgers", value: "ledgers", label: "Dòng tiền" },
                { key: "withdrawals", value: "withdrawals", label: "Yêu cầu rút tiền" },
            ];
        },
        withdrawableWallets() {
            return this.wallets.filter(
                (w) => Number(w.available_balance || 0) > 0,
            );
        },
        selectedWalletBalance() {
            const w = this.wallets.find(
                (item) => String(item.id) === String(this.withdrawForm.wallet_id),
            );
            return w ? Number(w.available_balance || 0) : 0;
        },
    },
    async mounted() {
        window.addEventListener("scroll", this.handleScroll);
        await this.loadInitialData();
    },
    beforeUnmount() {
        window.removeEventListener("scroll", this.handleScroll);
    },
    methods: {
        selectFinanceTab(tabKey) {
            const k = String(tabKey || '');
            if (k === 'wallets') {
                this.activeTab = 'wallets';
            } else if (k === 'ledgers') {
                this.openLedgers();
            } else if (k === 'withdrawals') {
                this.openWithdrawals();
            }
        },
        async loadInitialData() {
            this.loading = true;
            this.error = "";
            try {
                const response = await api("/api/owner/finance/wallets");
                this.wallets = response.wallets || [];
                this.bankAccounts = response.bank_accounts || [];
                this.minimumWithdrawal = response.minimum_withdrawal || 100000;
            } catch (error) {
                this.error =
                    error.message || "Không thể tải dữ liệu số dư ví.";
            } finally {
                this.loading = false;
            }
        },
        async openLedgers(wallet = null) {
            this.activeTab = "ledgers";
            if (wallet) {
                this.ledgerFilters.wallet_id = wallet.id;
            }
            await this.loadLedgers(1);
        },
        async loadLedgers(page = 1) {
            this.loading = true;
            this.error = "";
            const params = new URLSearchParams({ page: String(page) });
            if (this.ledgerFilters.wallet_id) {
                params.set("wallet_id", this.ledgerFilters.wallet_id);
            }
            try {
                const response = await api(
                    `/api/owner/finance/ledgers?${params.toString()}`,
                );
                this.ledgers = response.data || [];
                this.ledgerMeta = response.meta || this.ledgerMeta;
            } catch (error) {
                this.error =
                    error.message || "Không thể tải danh sách dòng tiền.";
            } finally {
                this.loading = false;
            }
        },
        async openWithdrawals() {
            this.activeTab = "withdrawals";
            await this.loadWithdrawals(1);
        },
        async loadWithdrawals(page = 1) {
            this.loading = true;
            this.error = "";
            const params = new URLSearchParams({ page: String(page) });
            if (this.withdrawalFilters.wallet_id) {
                params.set("wallet_id", this.withdrawalFilters.wallet_id);
            }
            if (this.withdrawalFilters.status) {
                params.set("status", this.withdrawalFilters.status);
            }
            try {
                const response = await api(
                    `/api/owner/finance/withdrawals?${params.toString()}`,
                );
                this.withdrawals = response.data || [];
                this.withdrawalMeta = response.meta || this.withdrawalMeta;
            } catch (error) {
                this.error =
                    error.message || "Không thể tải yêu cầu rút tiền.";
            } finally {
                this.loading = false;
            }
        },
        openWithdrawalModal(wallet = null) {
            if (!this.withdrawableWallets.length) {
                this.error =
                    "Không có ví nào đủ điều kiện số dư để rút tiền.";
                return;
            }
            if (!this.bankAccounts.length) {
                this.error =
                    "Chưa có tài khoản ngân hàng nhận tiền. Hãy hoàn tất hồ sơ.";
                return;
            }
            const targetWallet = wallet || this.withdrawableWallets[0];
            const defaultBank =
                this.bankAccounts.find((a) => a.is_default) ||
                this.bankAccounts[0];
            this.modalError = "";
            this.withdrawForm = {
                wallet_id: targetWallet.id,
                bank_account_id: defaultBank.id,
                amount: Math.max(
                    this.minimumWithdrawal,
                    Math.min(
                        Number(targetWallet.available_balance || 0),
                        5000000,
                    ),
                ),
                owner_note: "",
            };
            this.showWithdrawModal = true;
        },
        closeWithdrawalModal() {
            if (this.submitting) return;
            this.showWithdrawModal = false;
        },
        onWalletSelectChange() {
            if (this.withdrawForm.amount > this.selectedWalletBalance) {
                this.withdrawForm.amount = this.selectedWalletBalance;
            }
        },
        async submitWithdrawal() {
            this.submitting = true;
            this.modalError = "";
            try {
                const response = await api("/api/owner/finance/withdrawals", {
                    method: "POST",
                    body: JSON.stringify(this.withdrawForm),
                });
                this.notice = response.message;
                this.showWithdrawModal = false;
                await this.loadInitialData();
                if (this.activeTab === "withdrawals") {
                    await this.loadWithdrawals(1);
                }
            } catch (error) {
                this.modalError =
                    error.message || "Không thể gửi yêu cầu rút tiền.";
            } finally {
                this.submitting = false;
            }
        },
        canCancelWithdrawal(item) {
            return item.status === "pending";
        },
        async cancelWithdrawal(item) {
            if (!confirm(`Bạn chắc chắn muốn hủy yêu cầu rút ${this.formatCurrency(item.amount)}?`)) {
                return;
            }
            this.cancellingId = item.id;
            this.error = "";
            try {
                const response = await api(
                    `/api/owner/finance/withdrawals/${item.id}/cancel`,
                    { method: "POST" },
                );
                this.notice = response.message;
                await this.loadInitialData();
                await this.loadWithdrawals(this.withdrawalMeta.current_page);
            } catch (error) {
                this.error = error.message || "Không thể hủy yêu cầu rút tiền.";
            } finally {
                this.cancellingId = null;
            }
        },
        ledgerType(type) {
            return (
                {
                    credit: "Cộng tiền",
                    debit: "Trừ tiền",
                    hold: "Tạm giữ",
                    release: "Giải tỏa",
                }[type] || type
            );
        },
        withdrawalStatus(status) {
            return (
                {
                    pending: "Chờ chuyển khoản",
                    completed: "Đã chuyển",
                    rejected: "Từ chối",
                    cancelled: "Đã hủy",
                }[status] || status
            );
        },
        formatCurrency(value) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
                maximumFractionDigits: 0,
            }).format(value || 0);
        },
        formatDateTime(value) {
            if (!value) return "-";
            return new Date(value).toLocaleString("vi-VN");
        },
        maskedAccount(acc) {
            if (!acc) return "";
            if (acc.length <= 4) return acc;
            return `•••• ${acc.slice(-4)}`;
        },
        handleScroll() {
            this.showScrollTop = window.scrollY > 150;
        },
    },
};
</script>

<style scoped>
.cluster-profile-surface.standalone {
    width: 100%;
    min-width: 0;
    background: transparent;
    display: flex;
    flex-direction: column;
    gap: 16px;
    border-radius: 0;
}

/* Single unified main surface */
.finance-header-hero {
    background: var(--admin-surface, #ffffff);
    padding: 10px 10px 0 10px;
    display: flex;
    align-items: center;
}

.profile-section-card.finance-main-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 10px;
    background: var(--admin-surface, #ffffff);
    border: none;
    border-radius: 0;
    box-shadow: none;
    margin-top: 0 !important;
}

.hero-integrated-tabs {
    flex: 1;
}

.alert {
    border-radius: 8px;
    padding: 14px 16px;
    font-weight: 500;
    font-size: 13px;
}

.alert.error {
    background: var(--admin-danger-soft, rgba(239, 68, 68, 0.08));
    color: var(--admin-danger, #ef4444);
}

.alert.success {
    background: var(--admin-success-soft, rgba(16, 185, 129, 0.08));
    color: var(--admin-primary, #22a653);
}

.alert.warning {
    background: var(--admin-warning-soft, rgba(245, 158, 11, 0.08));
    color: var(--admin-warning, #d97706);
}

/* Filters Bar: Flat resting on main surface */
.filters-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 0;
    background: transparent;
    border: none;
}

.filters-bar select {
    min-width: 210px;
    height: 38px;
    padding: 0 12px;
    border: 1px solid var(--admin-border, #cbd5e1);
    border-radius: 6px;
    background: var(--admin-surface, #ffffff);
    font: inherit;
    font-size: 13px;
    color: var(--admin-text, #101c15);
}

/* Services Table Section (matching ServicesTable.vue) */
.services-table-section {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* State Cards */
.table-state-card {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 8px;
    padding: 36px 20px;
    background: var(--admin-bg-soft, #f7fbf5);
    border: 1px dashed var(--admin-border, #cfded1);
    border-radius: 8px;
    color: var(--admin-muted, #2f3d34);
    font-size: 13.5px;
    font-weight: 400;
    text-align: center;
}

.spinner-sm {
    width: 18px;
    height: 18px;
    border: 2px solid var(--admin-border, #cfded1);
    border-top-color: var(--admin-primary, #22a653);
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Services Table Wrapper & Data Table */
.services-table-wrapper {
    overflow-x: auto;
    border: none;
    border-radius: 10px;
}

.services-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: left;
}

.services-data-table th {
    background: var(--admin-bg-soft, #f7fbf5);
    color: var(--admin-text, #101c15);
    font-weight: 400;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 12px 14px;
    border-bottom: none;
}

.services-data-table td {
    padding: 12px 14px;
    border-bottom: none;
    color: var(--admin-text, #101c15);
    font-weight: 400;
    vertical-align: middle;
}

.services-data-table tbody tr {
    transition: background-color 0.12s ease;
}

.services-data-table tbody tr:hover {
    background: var(--admin-hover, #edf7ed);
}

.venue-name {
    font-weight: 400;
    color: var(--admin-text, #101c15);
}

.cell-sub {
    display: block;
    margin-top: 3px;
    color: var(--admin-muted, #64748b);
    font-size: 12px;
}

.money {
    font-weight: 400;
    white-space: nowrap;
}

.money.positive,
.money.credit {
    color: #216b34;
}

.money.pending {
    color: #9a6700;
}

.money.debit,
.money.hold {
    color: #991b1b;
}

.code {
    font-weight: 400;
    color: #216b34;
}

.cell-desc {
    max-width: 260px;
}

.desc-text {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.45;
    color: var(--admin-muted, #2f3d34);
}

.action-col {
    width: 1%;
    min-width: 90px;
    text-align: right;
}

/* Status Pills */
.status-pill {
    display: inline-flex;
    min-height: 24px;
    align-items: center;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 400;
    white-space: nowrap;
}

.status-pill.credit,
.status-pill.approved,
.status-pill.completed {
    background: #e8f7ec;
    color: #216b34;
}

.status-pill.debit,
.status-pill.rejected,
.status-pill.cancelled {
    background: #fef2f2;
    color: #991b1b;
}

.status-pill.pending,
.status-pill.reviewing,
.status-pill.hold {
    background: #fff4d6;
    color: #8a4b08;
}

.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding-top: 12px;
}

/* Floating Add Button */
.floating-add-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 400;
}

.btn-float-add {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 44px;
    padding: 0 18px;
    border: none;
    border-radius: 999px;
    background: var(--admin-primary, #22a653);
    color: #ffffff;
    font-size: 13.5px;
    font-weight: 500;
    box-shadow: 0 4px 16px rgba(34, 166, 83, 0.35);
    cursor: pointer;
    transition: all 0.15s ease;
}

.btn-float-add:hover {
    background: var(--admin-primary-dark, #15733a);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(34, 166, 83, 0.45);
}

/* Modals System */
.modal-backdrop {
    position: fixed !important;
    inset: 0 !important;
    z-index: 520 !important;
    display: grid !important;
    place-items: center !important;
    width: 100vw !important;
    height: 100vh !important;
    padding: 20px !important;
    overflow-y: auto !important;
    background: rgba(15, 23, 42, 0.58) !important;
}

.withdraw-modal {
    width: min(560px, calc(100vw - 32px));
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    border: 1px solid var(--admin-border, #e2e8f0);
    border-radius: 12px;
    background: var(--admin-surface, #ffffff);
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 16px 18px;
    border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
}

.modal-header h2 {
    margin: 0;
    font-size: 17px;
    font-weight: 400;
    color: var(--admin-text, #101c15);
}

.modal-header p {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--admin-muted, #64748b);
}

.close-btn {
    border: 0;
    padding: 2px 8px;
    background: transparent;
    color: var(--admin-muted, #64748b);
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
    border-radius: 6px;
}

.close-btn:hover {
    background: var(--admin-hover, #edf7ed);
    color: var(--admin-text, #101c15);
}

.inline-alert {
    margin: 12px 18px 0;
}

.modal-body {
    display: grid;
    gap: 14px;
    padding: 16px 18px;
}

.modal-body label {
    display: grid;
    gap: 6px;
}

.modal-body label span {
    font-size: 13px;
    font-weight: 400;
    color: var(--admin-text, #101c15);
}

.modal-body input,
.modal-body select,
.modal-body textarea {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid var(--admin-border, #cbd5e1);
    border-radius: 6px;
    background: var(--admin-surface, #ffffff);
    font: inherit;
    font-size: 13px;
    color: var(--admin-text, #101c15);
}

.modal-body textarea {
    resize: vertical;
}

.modal-body input:focus,
.modal-body select:focus,
.modal-body textarea:focus {
    outline: 0;
    border-color: var(--admin-primary, #22a653);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 14px 18px;
    border-top: 1px solid var(--admin-border-soft, #e2e8f0);
}

.primary-btn,
.secondary-btn {
    height: 36px;
    padding: 0 16px;
    border: 0;
    border-radius: 6px;
    font: inherit;
    font-size: 13px;
    font-weight: 400;
    cursor: pointer;
    white-space: nowrap;
}

.primary-btn {
    background: var(--admin-primary, #22a653);
    color: #ffffff;
}

.secondary-btn {
    background: var(--admin-hover, #f8fafc);
    border: 1px solid var(--admin-border, #e2e8f0);
    color: var(--admin-text, #101c15);
}

button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

@media (max-width: 720px) {
    .modal-backdrop {
        padding: 12px !important;
    }

    .withdraw-modal {
        width: 100%;
        max-height: calc(100vh - 24px);
    }
}
</style>

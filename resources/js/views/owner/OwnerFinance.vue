<template>
    <section class="finance-page">
        <!-- Floating Add Button -->
        <div v-if="activeTab === 'withdrawals' && withdrawableWallets.length && bankAccounts.length" class="floating-add-container" :class="{ 'has-scroll': showScrollTop }">
            <button class="btn-float-add" type="button" @click="openWithdrawalModal(withdrawableWallets[0])" title="Yêu cầu rút tiền">
                <AppIcon name="plus" size="20" />
                <span class="btn-float-text">Yêu cầu rút tiền</span>
            </button>
        </div>

        <div class="tabs-and-actions">
            <div class="tabs">
                <button
                    type="button"
                    :class="{ active: activeTab === 'wallets' }"
                    @click="activeTab = 'wallets'"
                >
                    <AppIcon name="banknote" size="16" />
                    <span>Số dư ví</span>
                </button>
                <button
                    type="button"
                    :class="{ active: activeTab === 'ledgers' }"
                    @click="openLedgers()"
                >
                    <AppIcon name="history" size="16" />
                    <span>Dòng tiền</span>
                </button>
                <button
                    type="button"
                    :class="{ active: activeTab === 'withdrawals' }"
                    @click="openWithdrawals()"
                >
                    <AppIcon name="creditCard" size="16" />
                    <span>Yêu cầu rút tiền</span>
                </button>
            </div>
            <ActionIconButton
                icon="refresh"
                label="Tải lại"
                :disabled="loading"
                @click="refreshCurrentTab"
            />
        </div>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="notice" class="alert success">{{ notice }}</div>

        <div v-if="loading" class="state-card">
            Đang tải dữ liệu tài chính...
        </div>

        <template v-else-if="activeTab === 'wallets'">
            <div class="table-card">
                <div v-if="wallets.length === 0" class="state-card">
                    Chưa có doanh thu online để tạo ví.
                </div>
                <div v-else class="table-scroll">
                    <table class="responsive-table wallet-table">
                        <thead>
                            <tr>
                                <th>Cụm sân</th>
                                <th>Số dư khả dụng</th>
                                <th>Đang chờ rút</th>
                                <th>Tổng thu online</th>
                                <th>Đã rút</th>
                                <th class="actions-col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="wallet in wallets" :key="wallet.id">
                                <td data-label="Cụm sân">
                                    <strong>{{
                                        wallet.venue_cluster?.name || "Ví chung"
                                    }}</strong>
                                    <small>{{
                                        wallet.venue_cluster?.address ||
                                        shortId(wallet.id)
                                    }}</small>
                                </td>
                                <td
                                    class="money positive"
                                    data-label="Số dư khả dụng"
                                >
                                    {{
                                        formatCurrency(wallet.available_balance)
                                    }}
                                </td>
                                <td
                                    class="money pending"
                                    data-label="Đang chờ rút"
                                >
                                    {{
                                        formatCurrency(
                                            wallet.pending_withdrawal_balance,
                                        )
                                    }}
                                </td>
                                <td class="money" data-label="Tổng thu online">
                                    {{ formatCurrency(wallet.total_earned) }}
                                </td>
                                <td class="money" data-label="Đã rút">
                                    {{ formatCurrency(wallet.total_withdrawn) }}
                                </td>
                                <td class="actions-col" data-label="Thao tác">
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
            <form class="filters" @submit.prevent="loadLedgers(1)">
                <select
                    v-model="ledgerFilters.wallet_id"
                    aria-label="Lọc theo ví"
                >
                    <option value="">Tất cả ví</option>
                    <option
                        v-for="wallet in wallets"
                        :key="wallet.id"
                        :value="wallet.id"
                    >
                        {{ wallet.venue_cluster?.name || "Ví chung" }}
                    </option>
                </select>
                <ActionIconButton
                    icon="filter"
                    label="Lọc"
                    variant="primary"
                    type="submit"
                />
                <ActionIconButton
                    icon="x"
                    label="Xóa lọc"
                    @click="clearLedgerFilter"
                />
            </form>

            <div class="table-card">
                <div v-if="ledgers.length === 0" class="state-card">
                    Chưa có giao dịch phù hợp.
                </div>
                <div v-else class="table-scroll">
                    <table class="responsive-table ledger-table">
                        <thead>
                            <tr>
                                <th>Thời gian</th>
                                <th>Loại giao dịch</th>
                                <th>Tham chiếu</th>
                                <th>Nội dung</th>
                                <th>Số tiền</th>
                                <th>Số dư sau giao dịch</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ledger in ledgers" :key="ledger.id">
                                <td data-label="Thời gian">
                                    {{ formatDateTime(ledger.created_at) }}
                                </td>
                                <td data-label="Loại giao dịch">
                                    <span
                                        class="status-pill"
                                        :class="ledger.direction"
                                        >{{ ledgerType(ledger.type) }}</span
                                    >
                                </td>
                                <td data-label="Tham chiếu">
                                    <strong>{{
                                        ledger.booking?.booking_code ||
                                        ledger.reference_code ||
                                        "-"
                                    }}</strong>
                                    <small>{{
                                        ledger.payment?.payment_code ||
                                        ledger.transaction_code
                                    }}</small>
                                </td>
                                <td
                                    class="description-cell"
                                    data-label="Nội dung"
                                >
                                    {{
                                        ledger.description || ledger.note || "-"
                                    }}
                                </td>
                                <td
                                    class="money"
                                    :class="ledger.direction"
                                    data-label="Số tiền"
                                >
                                    {{
                                        ledger.direction === "credit"
                                            ? "+"
                                            : "-"
                                    }}{{ formatCurrency(ledger.amount) }}
                                </td>
                                <td class="money" data-label="Số dư sau">
                                    {{ formatCurrency(ledger.balance_after) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <PaginationBar :meta="ledgerMeta" @change="loadLedgers" />
        </template>

        <template v-else>
            <form
                class="filters withdrawal-filters"
                @submit.prevent="loadWithdrawals(1)"
            >
                <select
                    v-model="withdrawalFilters.wallet_id"
                    aria-label="Lọc theo ví"
                >
                    <option value="">Tất cả ví</option>
                    <option
                        v-for="wallet in wallets"
                        :key="wallet.id"
                        :value="wallet.id"
                    >
                        {{ wallet.venue_cluster?.name || "Ví chung" }}
                    </option>
                </select>
                <select
                    v-model="withdrawalFilters.status"
                    aria-label="Lọc theo trạng thái"
                >
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending">Chờ xử lý</option>
                    <option value="reviewing">Đang kiểm tra</option>
                    <option value="approved">Đã duyệt</option>
                    <option value="rejected">Từ chối</option>
                    <option value="completed">Đã chuyển</option>
                </select>
                <ActionIconButton
                    icon="filter"
                    label="Lọc"
                    variant="primary"
                    type="submit"
                />
                <ActionIconButton
                    icon="x"
                    label="Xóa lọc"
                    @click="clearWithdrawalFilter"
                />
            </form>

            <div class="table-card">
                <div v-if="withdrawals.length === 0" class="state-card">
                    Chưa có yêu cầu rút tiền phù hợp.
                </div>
                <div v-else class="table-scroll">
                    <table class="responsive-table withdrawal-table">
                        <thead>
                            <tr>
                                <th>Mã yêu cầu</th>
                                <th>Ví cụm sân</th>
                                <th>Tài khoản nhận</th>
                                <th>Số tiền</th>
                                <th>Thời gian</th>
                                <th>Trạng thái</th>
                                <th>Ghi chú xử lý</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="withdrawal in withdrawals"
                                :key="withdrawal.id"
                            >
                                <td class="code" data-label="Mã yêu cầu">
                                    {{ withdrawal.request_code }}
                                </td>
                                <td data-label="Ví cụm sân">
                                    {{
                                        withdrawal.wallet?.venue_cluster
                                            ?.name || "Ví chung"
                                    }}
                                </td>
                                <td data-label="Tài khoản nhận">
                                    <strong>{{
                                        withdrawal.bank_account?.bank_name ||
                                        "-"
                                    }}</strong>
                                    <small
                                        >{{
                                            maskedAccount(
                                                withdrawal.bank_account
                                                    ?.account_number,
                                            )
                                        }}
                                        ·
                                        {{
                                            withdrawal.bank_account
                                                ?.account_holder_name
                                        }}</small
                                    >
                                </td>
                                <td class="money" data-label="Số tiền">
                                    {{ formatCurrency(withdrawal.amount) }}
                                </td>
                                <td data-label="Thời gian">
                                    {{
                                        formatDateTime(
                                            withdrawal.requested_at ||
                                                withdrawal.created_at,
                                        )
                                    }}
                                </td>
                                <td data-label="Trạng thái">
                                    <span
                                        class="status-pill"
                                        :class="withdrawal.status"
                                        >{{
                                            withdrawalStatus(withdrawal.status)
                                        }}</span
                                    >
                                </td>
                                <td
                                    class="description-cell"
                                    data-label="Ghi chú xử lý"
                                >
                                    {{
                                        withdrawal.status_reason ||
                                        withdrawal.review_note ||
                                        withdrawal.owner_note ||
                                        "-"
                                    }}
                                    <small v-if="withdrawal.transfer_reference"
                                        >Mã giao dịch:
                                        {{
                                            withdrawal.transfer_reference
                                        }}</small
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <PaginationBar :meta="withdrawalMeta" @change="loadWithdrawals" />
        </template>

        <div
            v-if="showWithdrawModal"
            class="modal-backdrop"
            @click.self="closeWithdrawalModal"
        >
            <form class="withdraw-modal" @submit.prevent="submitWithdrawal">
                <header class="modal-header">
                    <div>
                        <h2>Tạo yêu cầu rút tiền</h2>
                        <p>
                            Số tiền được giữ ngay sau khi gửi để tránh vượt số
                            dư.
                        </p>
                    </div>
                    <ActionIconButton
                        icon="x"
                        label="Đóng"
                        @click="closeWithdrawalModal"
                    />
                </header>

                <label class="field">
                    <span>Ví nguồn</span>
                    <select
                        v-model="withdrawForm.owner_wallet_id"
                        required
                        @change="syncSelectedWallet"
                    >
                        <option
                            v-for="wallet in withdrawableWallets"
                            :key="wallet.id"
                            :value="wallet.id"
                        >
                            {{ wallet.venue_cluster?.name || "Ví chung" }} ·
                            {{ formatCurrency(wallet.available_balance) }}
                        </option>
                    </select>
                </label>

                <label class="field">
                    <span>Tài khoản nhận</span>
                    <select
                        v-model="withdrawForm.owner_bank_account_id"
                        required
                    >
                        <option
                            v-for="account in bankAccounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.bank_name }} ·
                            {{ maskedAccount(account.account_number) }} ·
                            {{ account.account_holder_name }}
                        </option>
                    </select>
                </label>

                <label class="field">
                    <span>Số tiền rút</span>
                    <input
                        v-model.number="withdrawForm.amount"
                        type="number"
                        :min="minimumWithdrawal"
                        :max="selectedWalletBalance"
                        step="1000"
                        required
                    />
                    <small
                        >Khả dụng: {{ formatCurrency(selectedWalletBalance) }}.
                        Tối thiểu
                        {{ formatCurrency(minimumWithdrawal) }}.</small
                    >
                </label>

                <label class="field">
                    <span>Ghi chú</span>
                    <textarea
                        v-model.trim="withdrawForm.owner_note"
                        rows="3"
                        maxlength="1000"
                        placeholder="Thông tin cần lưu ý khi xử lý"
                    />
                </label>

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
    </section>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import PaginationBar from "../../components/PaginationBar.vue";
import TableActionGroup from "../../components/TableActionGroup.vue";
import { api } from "../../services/api.js";

export default {
    name: "OwnerFinance",
    components: { ActionIconButton, AppIcon, TableActionGroup, PaginationBar },
    data() {
        return {
            activeTab: "wallets",
            wallets: [],
            bankAccounts: [],
            ledgers: [],
            withdrawals: [],
            ledgerFilters: { wallet_id: "" },
            withdrawalFilters: { wallet_id: "", status: "" },
            ledgerMeta: { current_page: 1, last_page: 1 },
            withdrawalMeta: { current_page: 1, last_page: 1 },
            loading: false,
            submitting: false,
            error: "",
            notice: "",
            showWithdrawModal: false,
            minimumWithdrawal: 50000,
            withdrawForm: {
                owner_wallet_id: "",
                owner_bank_account_id: "",
                amount: 50000,
                owner_note: "",
            },
            showScrollTop: false,
        };
    },
    computed: {
        withdrawableWallets() {
            return this.wallets.filter(
                (wallet) =>
                    Number(wallet.available_balance) >= this.minimumWithdrawal,
            );
        },
        selectedWallet() {
            return (
                this.wallets.find(
                    (wallet) => wallet.id === this.withdrawForm.owner_wallet_id,
                ) || null
            );
        },
        selectedWalletBalance() {
            return Number(this.selectedWallet?.available_balance || 0);
        },
    },
    mounted() {
        window.addEventListener("scroll", this.handleScroll);
        this.loadWallets();
    },
    beforeUnmount() {
        window.removeEventListener("scroll", this.handleScroll);
    },
    methods: {
        async loadWallets() {
            this.loading = true;
            this.error = "";
            try {
                const response = await api("/api/owner/finance/wallets");
                this.wallets = response.data || [];
                this.bankAccounts = response.bank_accounts || [];
            } catch (error) {
                this.error = error.message || "Không tải được thông tin ví.";
            } finally {
                this.loading = false;
            }
        },
        async loadLedgers(page = 1) {
            this.loading = true;
            this.error = "";
            const params = new URLSearchParams({ page: String(page) });
            if (this.ledgerFilters.wallet_id)
                params.set("wallet_id", this.ledgerFilters.wallet_id);
            try {
                const response = await api(
                    `/api/owner/finance/ledgers?${params.toString()}`,
                );
                this.ledgers = response.data || [];
                this.ledgerMeta = {
                    current_page: response.current_page || 1,
                    last_page: response.last_page || 1,
                };
            } catch (error) {
                this.error =
                    error.message || "Không tải được lịch sử dòng tiền.";
            } finally {
                this.loading = false;
            }
        },
        async loadWithdrawals(page = 1) {
            this.loading = true;
            this.error = "";
            const params = new URLSearchParams({ page: String(page) });
            Object.entries(this.withdrawalFilters).forEach(([key, value]) => {
                if (value) params.set(key, value);
            });
            try {
                const response = await api(
                    `/api/owner/finance/withdrawals?${params.toString()}`,
                );
                this.withdrawals = response.data || [];
                this.withdrawalMeta = {
                    current_page: response.current_page || 1,
                    last_page: response.last_page || 1,
                };
            } catch (error) {
                this.error =
                    error.message || "Không tải được lịch sử rút tiền.";
            } finally {
                this.loading = false;
            }
        },
        openLedgers(wallet = null) {
            this.activeTab = "ledgers";
            if (wallet) this.ledgerFilters.wallet_id = wallet.id;
            this.loadLedgers(1);
        },
        openWithdrawals() {
            this.activeTab = "withdrawals";
            this.loadWithdrawals(1);
        },
        refreshCurrentTab() {
            this.notice = "";
            if (this.activeTab === "ledgers")
                return this.loadLedgers(this.ledgerMeta.current_page);
            if (this.activeTab === "withdrawals")
                return this.loadWithdrawals(this.withdrawalMeta.current_page);
            return this.loadWallets();
        },
        clearLedgerFilter() {
            this.ledgerFilters.wallet_id = "";
            this.loadLedgers(1);
        },
        clearWithdrawalFilter() {
            this.withdrawalFilters = { wallet_id: "", status: "" };
            this.loadWithdrawals(1);
        },
        openWithdrawalModal(wallet) {
            if (!wallet) return;
            const defaultAccount =
                this.bankAccounts.find((account) => account.is_default) ||
                this.bankAccounts[0];
            this.withdrawForm = {
                owner_wallet_id: wallet.id,
                owner_bank_account_id: defaultAccount?.id || "",
                amount: Math.min(
                    Number(wallet.available_balance),
                    Math.max(this.minimumWithdrawal, 100000),
                ),
                owner_note: "",
            };
            this.showWithdrawModal = true;
        },
        closeWithdrawalModal() {
            if (!this.submitting) this.showWithdrawModal = false;
        },
        syncSelectedWallet() {
            this.withdrawForm.amount = Math.min(
                Math.max(
                    Number(this.withdrawForm.amount || 0),
                    this.minimumWithdrawal,
                ),
                this.selectedWalletBalance,
            );
        },
        async submitWithdrawal() {
            this.submitting = true;
            this.error = "";
            try {
                const response = await api("/api/owner/finance/withdrawals", {
                    method: "POST",
                    body: JSON.stringify(this.withdrawForm),
                });
                this.notice = response.message;
                this.showWithdrawModal = false;
                await this.loadWallets();
                this.activeTab = "withdrawals";
                await this.loadWithdrawals(1);
            } catch (error) {
                this.error = error.message || "Không thể tạo yêu cầu rút tiền.";
            } finally {
                this.submitting = false;
            }
        },
        ledgerType(type) {
            return (
                {
                    credit: "Ghi nhận doanh thu",
                    payment: "Thanh toán booking",
                    refund: "Hoàn tiền",
                    hold: "Tạm giữ rút tiền",
                    release: "Hoàn lại tiền giữ",
                    debit: "Đã chuyển tiền",
                }[type] || type
            );
        },
        withdrawalStatus(status) {
            return (
                {
                    pending: "Chờ xử lý",
                    reviewing: "Đang kiểm tra",
                    approved: "Đã duyệt",
                    rejected: "Từ chối",
                    completed: "Đã chuyển",
                    cancelled: "Đã hủy",
                }[status] || status
            );
        },
        formatCurrency(value) {
            return `${Number(value || 0).toLocaleString("vi-VN")} đ`;
        },
        formatDateTime(value) {
            return value ? new Date(value).toLocaleString("vi-VN") : "-";
        },
        maskedAccount(value) {
            const text = String(value || "");
            return text.length > 4 ? `•••• ${text.slice(-4)}` : text || "-";
        },
        shortId(value) {
            return value ? String(value).slice(0, 8).toUpperCase() : "-";
        },
        handleScroll() {
            this.showScrollTop = window.scrollY > 150;
        },
    },
};
</script>

<style scoped>
.finance-page {
    display: grid;
    gap: 16px;
    min-width: 0;
}

.tabs-and-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
}

.tabs button {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 38px;
    padding: 0 14px;
    border: 1px solid #d5e3d6;
    border-radius: 7px;
    background: #fff;
    color: #344238;
    font-weight: 750;
    white-space: nowrap;
    cursor: pointer;
}

.tabs button.active {
    border-color: #2f9e44;
    background: #2f9e44;
    color: #fff;
}

.info-band {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border-left: 3px solid #2f9e44;
    background: #f3faf4;
    color: #294332;
}

.info-band p {
    margin: 0;
    line-height: 1.5;
}

.filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.filters select {
    min-width: 210px;
}

.create-withdrawal {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-left: auto;
}

td small {
    display: block;
    margin-top: 4px;
}

.money {
    font-weight: 800;
    white-space: nowrap;
}

.money.positive,
.money.credit {
    color: #216b34;
}

.money.pending {
    color: #9a6700;
}

.money.debit {
    color: #991b1b;
}

.code {
    font-weight: 800;
    color: #216b34;
}

.finance-page .description-cell {
    max-width: 280px;
    white-space: normal !important;
    overflow-wrap: anywhere !important;
    word-break: break-word;
    line-height: 1.45;
}

.actions-col {
    width: 1%;
    min-width: 100px;
    text-align: right;
}

.status-pill {
    display: inline-flex;
    min-height: 26px;
    align-items: center;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
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
.status-pill.reviewing {
    background: #fff4d6;
    color: #8a4b08;
}

.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.withdraw-modal {
    width: min(560px, calc(100vw - 32px));
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    border: 1px solid #d7e4d7;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 22px 60px rgba(24, 42, 29, 0.18);
}

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
}

.finance-page .withdrawal-table {
    min-width: 0 !important;
    table-layout: fixed !important;
}

.finance-page .withdrawal-table td.description-cell {
    white-space: normal !important;
}

.withdrawal-table th:nth-child(1) {
    width: 13%;
}
.withdrawal-table th:nth-child(2) {
    width: 12%;
}
.withdrawal-table th:nth-child(3) {
    width: 19%;
}
.withdrawal-table th:nth-child(4) {
    width: 11%;
}
.withdrawal-table th:nth-child(5) {
    width: 15%;
}
.withdrawal-table th:nth-child(6) {
    width: 12%;
}
.withdrawal-table th:nth-child(7) {
    width: 18%;
}

.modal-header,
.modal-actions {
    padding: 16px 18px;
}

.modal-header {
    border-bottom: 1px solid #e1eae1;
}

.modal-header h2,
.modal-header p {
    margin: 0;
}

.modal-header p {
    margin-top: 4px;
}

.field {
    display: grid;
    gap: 7px;
    margin: 16px 18px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #e1eae1;
}

.primary-btn,
.secondary-btn {
    min-height: 38px;
    padding: 0 15px;
    border-radius: 7px;
    font-weight: 750;
    cursor: pointer;
}

.primary-btn {
    border: 1px solid #2f9e44;
    background: #2f9e44;
    color: #fff;
}

.secondary-btn {
    border: 1px solid #d5e3d6;
    background: #fff;
    color: #344238;
}

button:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

@media (max-width: 720px) {
    .sg-shell-admin .content-area .finance-page .tabs {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: visible;
    }

    .sg-shell-admin .content-area .finance-page .tabs button {
        width: 100% !important;
        min-width: 0;
        height: auto;
        min-height: 48px;
        justify-content: center;
        padding: 7px 6px;
        font-size: 12px;
        line-height: 1.25;
        white-space: normal !important;
        text-align: center;
    }

    .sg-shell-admin .content-area .finance-page form.filters {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) !important;
        align-items: center !important;
    }

    .sg-shell-admin .content-area .finance-page form.filters > select {
        flex: none !important;
        width: 100% !important;
        min-width: 0 !important;
        height: 42px !important;
        min-height: 42px !important;
        max-height: 42px !important;
    }

    .sg-shell-admin
        .content-area
        .finance-page
        form.filters
        > .action-icon-button {
        justify-self: start;
    }

    .sg-shell-admin .content-area .finance-page .create-withdrawal {
        grid-column: 1 / -1;
        width: 100%;
        justify-content: center;
        margin-left: 0;
    }

    .finance-page .responsive-table,
    .finance-page .responsive-table tbody,
    .finance-page .responsive-table tr,
    .finance-page .responsive-table td {
        display: block !important;
        width: 100% !important;
        min-width: 0 !important;
    }

    .finance-page .responsive-table {
        min-width: 0 !important;
        table-layout: auto !important;
    }

    .finance-page .responsive-table thead {
        display: none;
    }

    .finance-page .responsive-table tr {
        padding: 12px 14px;
        border-bottom: 1px solid #dce8dc;
    }

    .finance-page .responsive-table td {
        display: grid !important;
        grid-template-columns: 118px minmax(0, 1fr);
        gap: 10px;
        max-width: none;
        padding: 7px 0 !important;
        border: 0 !important;
        white-space: normal !important;
        text-align: left !important;
    }

    .finance-page .responsive-table td::before {
        content: attr(data-label);
        color: #536257;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .finance-page .responsive-table .actions-col {
        min-width: 0;
    }

    .finance-page .responsive-table .table-action-group {
        justify-content: flex-start;
    }

    .modal-backdrop {
        padding: 12px !important;
    }

    .withdraw-modal {
        width: 100%;
        max-height: calc(100vh - 24px);
    }
}
</style>

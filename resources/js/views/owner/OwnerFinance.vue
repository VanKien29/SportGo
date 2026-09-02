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
                <section class="wallet-dashboard">
                    <div class="wallet-balance-hero">
                        <div class="wallet-hero-copy">
                            <div class="eyebrow">
                                <span class="eyebrow-icon"><AppIcon name="wallet" size="16" /></span>
                                Ví chủ sân
                            </div>
                            <h1>Tổng quan dòng tiền</h1>
                            <p>Số tiền có thể rút ngay từ tất cả cụm sân của bạn.</p>
                            <div class="wallet-hero-balance">{{ formatCurrency(financeSummary.available_balance) }}</div>
                            <div class="wallet-hero-meta">
                                <span><i class="hero-dot"></i> {{ wallets.length }} ví đang theo dõi</span>
                                <span v-if="financeSummary.pending_withdrawal_balance > 0">
                                    {{ formatCurrency(financeSummary.pending_withdrawal_balance) }} đang tạm giữ
                                </span>
                            </div>
                        </div>
                        <div class="wallet-hero-action">
                            <AppIcon name="trending-up" size="76" />
                            <button
                                v-if="withdrawableWallets.length && bankAccounts.length"
                                class="btn-hero-withdraw"
                                type="button"
                                @click="openWithdrawalModal(withdrawableWallets[0])"
                            >
                                <AppIcon name="banknote" size="17" />
                                Rút tiền
                            </button>
                        </div>
                    </div>

                    <div class="wallet-metrics-grid">
                        <div class="wallet-metric-card available">
                            <span class="metric-icon"><AppIcon name="wallet" size="19" /></span>
                            <span class="metric-label">Số dư khả dụng</span>
                            <strong>{{ formatCurrency(financeSummary.available_balance) }}</strong>
                            <small>Có thể tạo yêu cầu rút</small>
                        </div>
                        <div class="wallet-metric-card pending">
                            <span class="metric-icon"><AppIcon name="clock" size="19" /></span>
                            <span class="metric-label">Đang tạm giữ</span>
                            <strong>{{ formatCurrency(financeSummary.pending_withdrawal_balance) }}</strong>
                            <small>Chờ xử lý yêu cầu rút</small>
                        </div>
                        <div class="wallet-metric-card income">
                            <span class="metric-icon"><AppIcon name="plus" size="19" /></span>
                            <span class="metric-label">Tổng thu online</span>
                            <strong>{{ formatCurrency(financeSummary.total_earned) }}</strong>
                            <small>Tổng tiền đã ghi nhận</small>
                        </div>
                        <div class="wallet-metric-card outgoing">
                            <span class="metric-icon"><AppIcon name="banknote" size="19" /></span>
                            <span class="metric-label">Đã rút về ngân hàng</span>
                            <strong>{{ formatCurrency(financeSummary.total_withdrawn) }}</strong>
                            <small>Tiền đã chuyển thành công</small>
                        </div>
                    </div>

                    <section class="booking-receivables-card">
                        <div class="booking-receivables-head">
                            <div>
                                <span class="section-kicker">KHOẢN PHẢI THU</span>
                                <h2>Tiền booking chưa thu</h2>
                                <p>Booking đã được xác nhận nhưng khách còn chưa thanh toán đủ, gồm cả booking trả sau.</p>
                            </div>
                            <div class="booking-receivables-total">
                                <strong>{{ formatCurrency(financeSummary.uncollected_booking_amount) }}</strong>
                                <small>{{ financeSummary.uncollected_booking_count || 0 }} booking</small>
                            </div>
                        </div>
                        <div v-if="bookingReceivables.length" class="booking-receivables-list">
                            <div v-for="booking in bookingReceivables" :key="booking.id" class="booking-receivable-row">
                                <div>
                                    <strong>#{{ booking.booking_code }}</strong>
                                    <small>{{ booking.venue_cluster_name || 'Cụm sân' }} · {{ booking.venue_court_name || 'Sân' }}</small>
                                </div>
                                <div>
                                    <span class="receivable-status">{{ booking.payment_option_label }}</span>
                                    <small>{{ booking.booking_date }} · {{ booking.start_time }} - {{ booking.end_time }}</small>
                                </div>
                                <strong class="receivable-amount">{{ formatCurrency(booking.outstanding_amount) }}</strong>
                            </div>
                        </div>
                        <div v-else class="booking-receivables-empty">
                            Không có booking đã xác nhận nào còn tiền cần thu.
                        </div>
                    </section>

                    <div class="wallet-dashboard-grid">
                        <section class="flow-card">
                            <div class="flow-card-header">
                                <div>
                                    <span class="section-kicker">Theo dõi biến động</span>
                                    <h2>Dòng tiền 6 tháng gần đây</h2>
                                </div>
                                <span class="flow-period">Đơn vị: VND</span>
                            </div>
                            <div class="flow-legend">
                                <span><i class="legend-dot income"></i> Tiền vào</span>
                                <span><i class="legend-dot outgoing"></i> Tiền ra</span>
                            </div>
                            <div v-if="hasCashflow" class="cashflow-chart">
                                <div class="chart-scale">
                                    <span>{{ compactCurrency(chartMax) }}</span>
                                    <span>{{ compactCurrency(chartMax / 2) }}</span>
                                    <span>0</span>
                                </div>
                                <div class="chart-columns">
                                    <div v-for="point in cashflow" :key="point.period" class="chart-column">
                                        <div class="chart-bars">
                                            <span
                                                class="chart-bar income"
                                                :style="{ height: chartHeight(point.income) }"
                                                :title="'Tiền vào: ' + formatCurrency(point.income)"
                                            ></span>
                                            <span
                                                class="chart-bar outgoing"
                                                :style="{ height: chartHeight(point.outgoing) }"
                                                :title="'Tiền ra: ' + formatCurrency(point.outgoing)"
                                            ></span>
                                        </div>
                                        <small>{{ point.label }}</small>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="chart-empty">
                                Chưa có giao dịch để tạo biểu đồ dòng tiền.
                            </div>
                            <div class="flow-card-foot">
                                <span><strong>{{ formatCurrency(financeSummary.total_balance) }}</strong> tổng giá trị đang quản lý</span>
                                <span>{{ formatCurrency(financeSummary.total_earned - financeSummary.total_withdrawn) }} chênh lệch thu - rút</span>
                            </div>
                        </section>

                        <section class="flow-card recent-flow-card">
                            <div class="flow-card-header">
                                <div>
                                    <span class="section-kicker">Cập nhật mới nhất</span>
                                    <h2>Dòng tiền gần đây</h2>
                                </div>
                                <button type="button" class="text-link" @click="openLedgers()">Xem tất cả</button>
                            </div>
                            <div v-if="recentLedgers.length" class="recent-flow-list">
                                <div v-for="ledger in recentLedgers" :key="'recent-' + ledger.id" class="recent-flow-row">
                                    <span class="recent-flow-icon" :class="ledger.type">
                                        <AppIcon :name="ledger.type === 'credit' || ledger.type === 'release' ? 'plus' : 'banknote'" size="15" />
                                    </span>
                                    <div class="recent-flow-info">
                                        <strong>{{ ledger.description || ledgerType(ledger.type) }}</strong>
                                        <small>{{ ledger.venue_cluster?.name || "Ví chủ sân" }} · {{ formatDateTime(ledger.created_at) }}</small>
                                    </div>
                                    <strong class="recent-flow-amount" :class="ledger.type">
                                        {{ ledgerAmountPrefix(ledger) }}{{ formatCurrency(ledger.amount) }}
                                    </strong>
                                </div>
                            </div>
                            <div v-else class="chart-empty">
                                Chưa có giao dịch dòng tiền.
                            </div>
                        </section>
                    </div>
                </section>

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
                                        <small v-if="Number(wallet.uncollected_booking_amount || 0) > 0" class="cell-sub receivable-cell-sub">
                                            Chưa thu booking: {{ formatCurrency(wallet.uncollected_booking_amount) }}
                                            ({{ wallet.uncollected_booking_count }} đơn)
                                        </small>
                                    </td>
                                    <td class="money positive">
                                        <strong>{{ formatCurrency(walletWithdrawableBalance(wallet)) }}</strong>
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
                                                        walletWithdrawableBalance(wallet),
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
                <section class="bank-accounts-section">
                    <div class="bank-accounts-header">
                        <div>
                            <span class="section-kicker">Tài khoản nhận tiền</span>
                            <h2>Quản lý tài khoản ngân hàng</h2>
                            <p>
                                Tài khoản nhận tiền trong hồ sơ đăng ký là tài khoản mặc định cố định. Bạn có thể thêm tài khoản phụ để sử dụng khi cần.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="primary-btn"
                            @click="openBankAccountModal()"
                        >
                            <AppIcon name="plus" size="15" />
                            Thêm tài khoản
                        </button>
                    </div>

                    <div v-if="managedBankAccounts.length" class="bank-account-grid">
                        <article
                            v-for="account in managedBankAccounts"
                            :key="account.id"
                            class="bank-account-card"
                        >
                            <div class="bank-account-card-head">
                                <strong>{{ account.bank_name }}</strong>
                                <span
                                    class="status-pill"
                                    :class="account.status"
                                >
                                    {{ bankAccountStatus(account.status) }}
                                </span>
                            </div>
                            <div class="bank-account-number">
                                {{ account.account_number }}
                            </div>
                            <div class="bank-account-meta">
                                <span>{{ account.account_holder_name }}</span>
                                <span v-if="account.branch_name">{{ account.branch_name }}</span>
                            </div>
                            <div class="bank-account-card-foot">
                                <span v-if="account.is_default" class="default-account-label">
                                    Mặc định
                                </span>
                                <button
                                    v-if="!account.partner_application_id"
                                    type="button"
                                    class="secondary-btn compact"
                                    @click="openBankAccountModal(account)"
                                >
                                    <AppIcon name="pencil" size="14" />
                                    Sửa
                                </button>
                                <span v-else class="locked-account-label">
                                    Không thể sửa/xóa
                                </span>
                            </div>
                            <small
                                v-if="account.status === 'pending'"
                                class="bank-account-help"
                            >
                                Đang chờ xác minh, chưa thể chọn để rút tiền.
                            </small>
                            <small
                                v-if="account.partner_application_id"
                                class="bank-account-help"
                            >
                                Tài khoản được lấy từ hồ sơ đăng ký đối tác và luôn giữ làm tài khoản mặc định.
                            </small>
                            <small
                                v-if="account.status === 'rejected' && account.rejected_reason"
                                class="bank-account-help is-error"
                            >
                                {{ account.rejected_reason }}
                            </small>
                        </article>
                    </div>
                    <div v-else class="bank-account-empty">
                        Chưa có tài khoản nhận tiền. Hãy thêm tài khoản để có thể rút tiền về ngân hàng.
                    </div>
                </section>

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

        <!-- Bank account modal -->
        <div
            v-if="showBankAccountModal"
            class="modal-backdrop"
            @click.self="closeBankAccountModal"
        >
            <form
                class="withdraw-modal bank-account-modal"
                @submit.prevent="submitBankAccount"
            >
                <header class="modal-header">
                    <div>
                        <h2>{{ editingBankAccount ? "Chỉnh sửa tài khoản nhận tiền" : "Thêm tài khoản nhận tiền" }}</h2>
                        <p>Thông tin này được dùng để SportGo chuyển tiền rút cho bạn.</p>
                    </div>
                    <button
                        class="close-btn"
                        type="button"
                        @click="closeBankAccountModal"
                    >
                        ×
                    </button>
                </header>

                <div v-if="bankAccountModalError" class="alert error inline-alert">
                    {{ bankAccountModalError }}
                </div>

                <div class="modal-body">
                    <label v-if="bankSelectOptions.length">
                        <span>Ngân hàng <em>*</em></span>
                        <select
                            v-model="bankAccountForm.bank_code"
                            required
                            @change="syncBankAccountBank"
                        >
                            <option value="" disabled>-- Chọn ngân hàng --</option>
                            <option
                                v-for="bank in bankSelectOptions"
                                :key="bank.code"
                                :value="bank.code"
                            >
                                {{ bank.short_name || bank.name || bank.code }}
                            </option>
                        </select>
                    </label>
                    <label v-else>
                        <span>Tên ngân hàng <em>*</em></span>
                        <input v-model.trim="bankAccountForm.bank_name" required />
                    </label>

                    <label v-if="!bankSelectOptions.length">
                        <span>Mã ngân hàng <em>*</em></span>
                        <input v-model.trim="bankAccountForm.bank_code" required />
                    </label>

                    <label>
                        <span>Số tài khoản <em>*</em></span>
                        <input
                            v-model.trim="bankAccountForm.account_number"
                            inputmode="numeric"
                            minlength="6"
                            maxlength="19"
                            required
                            @input="normalizeBankAccountNumber"
                        />
                    </label>

                    <label>
                        <span>Tên chủ tài khoản <em>*</em></span>
                        <input
                            v-model="bankAccountForm.account_holder_name"
                            maxlength="150"
                            required
                            placeholder="VD: NGUYEN VAN A"
                            @input="normalizeBankAccountHolder"
                        />
                    </label>

                    <label>
                        <span>Chi nhánh</span>
                        <input v-model.trim="bankAccountForm.branch_name" maxlength="150" />
                    </label>

                    <label v-if="!hasApplicationBankAccount" class="checkbox-field">
                        <input
                            v-model="bankAccountForm.is_default"
                            type="checkbox"
                        />
                        <span>Đặt làm tài khoản mặc định</span>
                    </label>
                    <p v-else class="bank-account-help">
                        Tài khoản mặc định từ hồ sơ đăng ký đối tác không thể thay đổi.
                    </p>
                </div>

                <footer class="modal-actions">
                    <button
                        class="secondary-btn"
                        type="button"
                        :disabled="bankAccountSubmitting"
                        @click="closeBankAccountModal"
                    >
                        Đóng
                    </button>
                    <button
                        class="primary-btn"
                        type="submit"
                        :disabled="bankAccountSubmitting"
                    >
                        {{ bankAccountSubmitting ? "Đang lưu..." : "Lưu tài khoản" }}
                    </button>
                </footer>
            </form>
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
                            v-model="withdrawForm.owner_wallet_id"
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
                        <select v-model="withdrawForm.owner_bank_account_id" required>
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

        <ConfirmModal
            v-model="withdrawalCancelConfirm.open"
            title="Xác nhận hủy yêu cầu rút tiền"
            :message="withdrawalCancelConfirm.message"
            consequence="Số tiền đang tạm giữ sẽ được hoàn lại vào số dư ví."
            confirm-text="Hủy yêu cầu"
            cancel-text="Quay lại"
            type="danger"
            @confirm="confirmCancelWithdrawal"
        />
    </div>
</template>

<script>
import ActionIconButton from "../../components/ActionIconButton.vue";
import AppIcon from "../../components/AppIcon.vue";
import AppTabs from "../../components/common/AppTabs.vue";
import ConfirmModal from "../../components/ConfirmModal.vue";
import PaginationBar from "../../components/PaginationBar.vue";
import TableActionGroup from "../../components/TableActionGroup.vue";
import { api } from "../../services/api.js";

export default {
    name: "OwnerFinance",
    components: {
        ActionIconButton,
        AppIcon,
        AppTabs,
        ConfirmModal,
        TableActionGroup,
        PaginationBar,
    },
    data() {
        return {
            activeTab: "wallets",
            wallets: [],
            summary: {
                available_balance: 0,
                pending_withdrawal_balance: 0,
                total_balance: 0,
                total_earned: 0,
                total_withdrawn: 0,
                wallet_count: 0,
                uncollected_booking_count: 0,
                uncollected_booking_amount: 0,
            },
            cashflow: [],
            bookingReceivables: [],
            ledgers: [],
            withdrawals: [],
            bankAccounts: [],
            managedBankAccounts: [],
            bankOptions: [],
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
            withdrawalCancelConfirm: {
                open: false,
                item: null,
                message: "",
            },
            showBankAccountModal: false,
            editingBankAccount: null,
            bankAccountSubmitting: false,
            bankAccountModalError: "",
            bankAccountForm: {
                bank_name: "",
                bank_code: "",
                account_number: "",
                account_holder_name: "",
                branch_name: "",
                is_default: false,
            },
            showWithdrawModal: false,
            withdrawForm: {
                owner_wallet_id: "",
                owner_bank_account_id: "",
                amount: 100000,
                owner_note: "",
            },
            showScrollTop: false,
        };
    },
    computed: {
        financeSummary() {
            return this.summary;
        },
        recentLedgers() {
            return this.ledgers.slice(0, 6);
        },
        hasCashflow() {
            return this.cashflow.some((item) => Number(item.count || 0) > 0);
        },
        chartMax() {
            const values = this.cashflow.flatMap((item) => [
                Number(item.income || 0),
                Number(item.outgoing || 0),
            ]);
            return Math.max(...values, 1);
        },
        financeTabsForAppTabs() {
            return [
                { key: "wallets", value: "wallets", label: "Số dư ví" },
                { key: "ledgers", value: "ledgers", label: "Dòng tiền" },
                { key: "withdrawals", value: "withdrawals", label: "Yêu cầu rút tiền" },
            ];
        },
        withdrawableWallets() {
            const minimum = Number(this.minimumWithdrawal || 0);
            return this.wallets.filter(
                (w) => this.walletWithdrawableBalance(w) >= minimum,
            );
        },
        selectedWalletBalance() {
            const w = this.wallets.find(
                (item) => String(item.id) === String(this.withdrawForm.owner_wallet_id),
            );
            return w ? this.walletWithdrawableBalance(w) : 0;
        },
        bankSelectOptions() {
            const options = [...this.bankOptions];
            const currentCode = this.bankAccountForm.bank_code;

            if (
                currentCode &&
                !options.some((bank) => String(bank.code) === String(currentCode))
            ) {
                options.unshift({
                    code: currentCode,
                    short_name: this.bankAccountForm.bank_name || currentCode,
                    name: this.bankAccountForm.bank_name || currentCode,
                });
            }

            return options;
        },
        hasApplicationBankAccount() {
            return this.managedBankAccounts.some((account) => Boolean(account.partner_application_id));
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
        async selectFinanceTab(tabKey) {
            const k = String(tabKey || '');
            if (k === 'wallets') {
                this.activeTab = 'wallets';
                await this.loadInitialData();
            } else if (k === 'ledgers') {
                await this.openLedgers();
            } else if (k === 'withdrawals') {
                await this.openWithdrawals();
            }
        },
        async loadInitialData() {
            this.loading = true;
            this.error = "";
            try {
                const [response, ledgerResponse] = await Promise.all([
                    api("/api/owner/finance/wallets"),
                    api("/api/owner/finance/ledgers?page=1"),
                ]);
                this.wallets = response.data || response.wallets || [];
                this.bankAccounts = response.bank_accounts || [];
                this.managedBankAccounts =
                    response.managed_bank_accounts || this.bankAccounts;
                this.minimumWithdrawal = response.minimum_withdrawal || 100000;
                this.summary = response.summary || this.buildFinanceSummary(this.wallets);
                this.bookingReceivables = response.booking_receivables || [];
                this.cashflow = response.cashflow || [];
                this.ledgers = ledgerResponse.data || [];
                this.ledgerMeta = ledgerResponse.meta || this.ledgerMeta;
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
        async loadBankOptions() {
            if (this.bankOptions.length) return;

            try {
                const response = await api("/api/user/partner-application/banks");
                this.bankOptions = response.data || [];
            } catch (error) {
                this.bankOptions = [];
                this.bankAccountModalError =
                    error.message || "Không thể tải danh sách ngân hàng.";
            }
        },
        async openBankAccountModal(account = null) {
            if (account?.partner_application_id) {
                this.bankAccountModalError =
                    "Tài khoản nhận tiền từ hồ sơ đăng ký đối tác là tài khoản mặc định cố định và không thể thay đổi hoặc xóa.";
                return;
            }
            this.editingBankAccount = account;
            this.bankAccountModalError = "";
            this.bankAccountForm = account
                ? {
                      bank_name: account.bank_name || "",
                      bank_code: account.bank_code || "",
                      account_number: account.account_number || "",
                      account_holder_name: account.account_holder_name || "",
                      branch_name: account.branch_name || "",
                      is_default: Boolean(account.is_default),
                  }
                : {
                      bank_name: "",
                      bank_code: "",
                      account_number: "",
                      account_holder_name: "",
                      branch_name: "",
                      is_default: this.managedBankAccounts.length === 0,
                  };
            this.showBankAccountModal = true;
            await this.loadBankOptions();
        },
        closeBankAccountModal() {
            if (this.bankAccountSubmitting) return;
            this.showBankAccountModal = false;
            this.editingBankAccount = null;
            this.bankAccountModalError = "";
        },
        syncBankAccountBank() {
            const bank = this.bankOptions.find(
                (item) => String(item.code) === String(this.bankAccountForm.bank_code),
            );
            if (!bank) return;

            this.bankAccountForm.bank_name =
                bank.short_name || bank.name || bank.code;
        },
        normalizeBankAccountNumber() {
            this.bankAccountForm.account_number = String(
                this.bankAccountForm.account_number || "",
            ).replace(/\D/g, "");
        },
        normalizeBankAccountHolder() {
            this.bankAccountForm.account_holder_name = String(
                this.bankAccountForm.account_holder_name || "",
            )
                .toUpperCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/đ/g, "D")
                .replace(/Đ/g, "D");
        },
        async submitBankAccount() {
            this.bankAccountSubmitting = true;
            this.bankAccountModalError = "";

            try {
                this.normalizeBankAccountNumber();
                this.normalizeBankAccountHolder();

                const account = this.editingBankAccount;
                const path = account
                    ? `/api/owner/finance/bank-accounts/${account.id}`
                    : "/api/owner/finance/bank-accounts";
                const response = await api(path, {
                    method: account ? "PATCH" : "POST",
                    body: JSON.stringify(this.bankAccountForm),
                });

                this.notice = response.message || "Đã lưu tài khoản nhận tiền.";
                this.showBankAccountModal = false;
                this.editingBankAccount = null;
                await this.loadInitialData();
                if (this.activeTab === "withdrawals") {
                    await this.loadWithdrawals(this.withdrawalMeta.current_page);
                }
            } catch (error) {
                this.bankAccountModalError =
                    error.message || "Không thể lưu tài khoản nhận tiền.";
            } finally {
                this.bankAccountSubmitting = false;
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
            const withdrawableBalance = this.walletWithdrawableBalance(
                targetWallet,
            );
            this.modalError = "";
            this.withdrawForm = {
                owner_wallet_id: targetWallet.id,
                owner_bank_account_id: defaultBank.id,
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
                const amount = Number(this.withdrawForm.amount);
                if (
                    !Number.isFinite(amount) ||
                    amount < Number(this.minimumWithdrawal) ||
                    amount > this.selectedWalletBalance
                ) {
                    this.modalError =
                        "Số tiền rút không hợp lệ hoặc vượt quá số dư khả dụng.";
                    return;
                }

                const response = await api("/api/owner/finance/withdrawals", {
                    method: "POST",
                    body: JSON.stringify({
                        owner_wallet_id: Number(this.withdrawForm.owner_wallet_id),
                        owner_bank_account_id: Number(
                            this.withdrawForm.owner_bank_account_id,
                        ),
                        amount,
                        owner_note: this.withdrawForm.owner_note,
                    }),
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
        walletWithdrawableBalance(wallet) {
            return Number(
                wallet?.withdrawable_balance ?? wallet?.available_balance ?? 0,
            );
        },
        canCancelWithdrawal(item) {
            return item.status === "pending";
        },
        cancelWithdrawal(item) {
            if (!this.canCancelWithdrawal(item) || this.cancellingId === item.id) {
                return;
            }
            this.withdrawalCancelConfirm = {
                open: true,
                item,
                message: `Bạn chắc chắn muốn hủy yêu cầu rút ${this.formatCurrency(item.amount)}?`,
            };
        },
        async confirmCancelWithdrawal() {
            const item = this.withdrawalCancelConfirm.item;
            this.withdrawalCancelConfirm = {
                open: false,
                item: null,
                message: "",
            };
            if (!item || !this.canCancelWithdrawal(item)) {
                return;
            }
            this.cancellingId = item.id;
            this.error = "";
            try {
                const response = await api(
                    `/api/owner/finance/withdrawals/${item.id}/cancel`,
                    { method: "PATCH" },
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
        buildFinanceSummary(wallets) {
            const values = (wallets || []).reduce(
                (result, wallet) => {
                    result.available_balance += Number(wallet.available_balance || 0);
                    result.pending_withdrawal_balance += Number(wallet.pending_withdrawal_balance || 0);
                    result.total_earned += Number(wallet.total_earned || 0);
                    result.total_withdrawn += Number(wallet.total_withdrawn || 0);
                    return result;
                },
                {
                    available_balance: 0,
                    pending_withdrawal_balance: 0,
                    total_earned: 0,
                    total_withdrawn: 0,
                    wallet_count: (wallets || []).length,
                    uncollected_booking_count: 0,
                    uncollected_booking_amount: 0,
                },
            );
            values.total_balance = values.available_balance + values.pending_withdrawal_balance;
            return values;
        },
        chartHeight(value) {
            const numericValue = Number(value || 0);
            if (!numericValue) return "3px";
            return Math.max(8, Math.round((numericValue / this.chartMax) * 142)) + "px";
        },
        compactCurrency(value) {
            const amount = Number(value || 0);
            if (amount >= 1000000000) return (amount / 1000000000).toFixed(1) + " tỷ";
            if (amount >= 1000000) return (amount / 1000000).toFixed(1) + " tr";
            if (amount >= 1000) return Math.round(amount / 1000) + "k";
            return Math.round(amount).toString();
        },
        ledgerAmountPrefix(ledger) {
            return ledger.type === "debit" || ledger.type === "hold" ? "-" : "+";
        },
        withdrawalStatus(status) {
            return (
                {
                    pending: "Chờ admin kiểm tra",
                    approved: "Đã duyệt - chờ chuyển khoản",
                    completed: "Đã chuyển",
                    rejected: "Từ chối",
                    cancelled: "Đã hủy",
                }[status] || status
            );
        },
        bankAccountStatus(status) {
            return (
                {
                    active: "Đang sử dụng",
                    pending: "Chờ xác minh",
                    rejected: "Bị từ chối",
                    inactive: "Đã tắt",
                }[status] || status || "-"
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

.bank-accounts-section {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 20px;
    border: 1px solid #e9eef1;
    border-radius: 14px;
    background: #fff;
}

.bank-accounts-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.bank-accounts-header h2 {
    margin: 0;
    color: var(--admin-text, #101c15);
    font-size: 18px;
    font-weight: 700;
}

.bank-accounts-header p {
    max-width: 720px;
    margin: 5px 0 0;
    color: var(--admin-muted, #64748b);
    font-size: 13px;
    line-height: 1.5;
}

.bank-account-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
}

.bank-account-card {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 9px;
    padding: 15px;
    border: 1px solid #e4ece6;
    border-radius: 11px;
    background: #fbfefb;
}

.bank-account-card-head,
.bank-account-card-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.bank-account-card-head strong {
    min-width: 0;
    overflow: hidden;
    color: var(--admin-text, #101c15);
    font-size: 14px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.bank-account-number {
    color: #216b34;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.bank-account-meta {
    display: flex;
    min-height: 34px;
    flex-direction: column;
    gap: 3px;
    color: var(--admin-muted, #64748b);
    font-size: 12px;
}

.default-account-label {
    color: #216b34;
    font-size: 12px;
    font-weight: 600;
}

.locked-account-label {
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
}

.bank-account-help {
    color: #8a4b08;
    font-size: 11px;
    line-height: 1.4;
}

.bank-account-help.is-error {
    color: #991b1b;
}

.bank-account-empty {
    padding: 22px;
    border: 1px dashed var(--admin-border, #cfded1);
    border-radius: 10px;
    color: var(--admin-muted, #64748b);
    font-size: 13px;
    text-align: center;
}

.status-pill.active {
    background: #e8f7ec;
    color: #216b34;
}

.status-pill.inactive {
    background: #f1f5f9;
    color: #64748b;
}

.bank-account-modal {
    width: min(600px, calc(100vw - 32px));
}

.modal-body em {
    color: var(--admin-danger, #dc2626);
    font-style: normal;
}

.checkbox-field {
    display: flex !important;
    align-items: center;
    grid-template-columns: none !important;
    gap: 8px !important;
}

.checkbox-field input {
    width: auto;
}

@media (max-width: 680px) {
    .bank-accounts-header {
        flex-direction: column;
    }

    .bank-accounts-header .primary-btn {
        width: 100%;
    }
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

.wallet-dashboard {
    display: grid;
    gap: 16px;
    margin-bottom: 22px;
}

.wallet-balance-hero {
    position: relative;
    display: flex;
    justify-content: space-between;
    min-height: 214px;
    overflow: hidden;
    padding: 28px 32px;
    border-radius: 16px;
    background:
        radial-gradient(circle at 86% 14%, rgba(129, 238, 168, 0.32), transparent 25%),
        linear-gradient(122deg, #113b2b 0%, #17633d 58%, #268052 100%);
    color: #fff;
    box-shadow: 0 12px 28px rgba(24, 92, 57, 0.18);
}

.wallet-balance-hero::after {
    position: absolute;
    right: -54px;
    bottom: -92px;
    width: 290px;
    height: 290px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 50%;
    box-shadow: 0 0 0 22px rgba(255, 255, 255, 0.03), 0 0 0 44px rgba(255, 255, 255, 0.025);
    content: "";
}

.wallet-hero-copy {
    position: relative;
    z-index: 1;
}

.eyebrow {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 12px;
    color: rgba(255, 255, 255, 0.74);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.eyebrow-icon {
    display: grid;
    width: 26px;
    height: 26px;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 8px;
    color: #b6f3ca;
}

.wallet-hero-copy h1 {
    margin: 0;
    color: #fff;
    font-size: clamp(23px, 3vw, 30px);
    font-weight: 700;
    letter-spacing: -0.03em;
}

.wallet-hero-copy p {
    margin: 7px 0 19px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 13px;
}

.wallet-hero-balance {
    color: #fff;
    font-size: clamp(25px, 3.5vw, 36px);
    font-weight: 700;
    letter-spacing: -0.04em;
}

.wallet-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    margin-top: 9px;
    color: rgba(255, 255, 255, 0.68);
    font-size: 12px;
}

.hero-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    margin-right: 5px;
    border-radius: 50%;
    background: #8ff0b1;
    box-shadow: 0 0 0 3px rgba(143, 240, 177, 0.16);
}

.wallet-hero-action {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: space-between;
    color: rgba(188, 250, 207, 0.55);
}

.btn-hero-withdraw {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 40px;
    padding: 0 17px;
    border: 0;
    border-radius: 9px;
    background: #fff;
    color: #17633d;
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 5px 16px rgba(0, 0, 0, 0.12);
}

.btn-hero-withdraw:hover {
    background: #ecfff2;
    transform: translateY(-1px);
}

.wallet-metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.wallet-metric-card {
    position: relative;
    display: grid;
    min-height: 137px;
    padding: 17px 17px 15px;
    border: 1px solid #e9eef1;
    border-radius: 13px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.025);
}

.wallet-metric-card::before {
    position: absolute;
    top: 0;
    left: 17px;
    width: 28px;
    height: 3px;
    border-radius: 0 0 4px 4px;
    background: #2ba86c;
    content: "";
}

.wallet-metric-card.pending::before {
    background: #e9a83b;
}

.wallet-metric-card.income::before {
    background: #3488e7;
}

.wallet-metric-card.outgoing::before {
    background: #c568e5;
}

.metric-icon {
    display: grid;
    width: 32px;
    height: 32px;
    margin-bottom: 9px;
    place-items: center;
    border-radius: 9px;
    background: #eaf8ef;
    color: #1e8b54;
}

.pending .metric-icon {
    background: #fff6e5;
    color: #bd7b12;
}

.income .metric-icon {
    background: #edf5ff;
    color: #347fd0;
}

.outgoing .metric-icon {
    background: #fbf0ff;
    color: #aa4ec9;
}

.metric-label {
    color: #65757c;
    font-size: 12px;
}

.wallet-metric-card strong {
    margin-top: 4px;
    color: #172b22;
    font-size: 18px;
    font-weight: 700;
}

.wallet-metric-card small {
    margin-top: 3px;
    color: #9aa7ac;
    font-size: 11px;
}

.booking-receivables-card {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 20px;
    border: 1px solid #f0dfc2;
    border-radius: 14px;
    background: linear-gradient(135deg, #fffdf8, #fff8ec);
}

.booking-receivables-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.booking-receivables-head h2 {
    margin: 3px 0 4px;
    color: #172b22;
    font-size: 18px;
}

.booking-receivables-head p {
    margin: 0;
    color: #65757c;
    font-size: 12px;
}

.booking-receivables-total {
    flex: 0 0 auto;
    text-align: right;
}

.booking-receivables-total strong {
    display: block;
    color: #a66b16;
    font-size: 21px;
}

.booking-receivables-total small,
.booking-receivable-row small {
    display: block;
    margin-top: 4px;
    color: #78868b;
    font-size: 12px;
}

.booking-receivables-list {
    display: flex;
    flex-direction: column;
    border-top: 1px solid #f0dfc2;
}

.booking-receivable-row {
    display: grid;
    grid-template-columns: minmax(150px, 1fr) minmax(210px, 1.2fr) auto;
    align-items: center;
    gap: 14px;
    padding: 11px 0;
    border-bottom: 1px solid rgba(240, 223, 194, 0.75);
}

.booking-receivable-row > div:first-child strong {
    color: #263a31;
}

.receivable-status {
    display: inline-flex;
    padding: 3px 8px;
    border-radius: 999px;
    background: #fff0cc;
    color: #986312;
    font-size: 11px;
    font-weight: 600;
}

.receivable-amount {
    color: #a66b16;
    white-space: nowrap;
}

.booking-receivables-empty {
    padding: 12px 0 2px;
    color: #78868b;
    font-size: 13px;
}

.wallet-dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(320px, 0.85fr);
    gap: 16px;
}

.flow-card {
    min-width: 0;
    padding: 20px;
    border: 1px solid #e9eef1;
    border-radius: 14px;
    background: #fff;
}

.flow-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.section-kicker {
    display: block;
    margin-bottom: 4px;
    color: #89979b;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.07em;
    text-transform: uppercase;
}

.flow-card h2 {
    margin: 0;
    color: #1b3026;
    font-size: 17px;
    font-weight: 700;
}

.flow-period {
    padding: 5px 9px;
    border-radius: 6px;
    background: #f5f8f7;
    color: #81908b;
    font-size: 11px;
    white-space: nowrap;
}

.flow-legend {
    display: flex;
    gap: 16px;
    margin-top: 16px;
    color: #778782;
    font-size: 11px;
}

.flow-legend span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.legend-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.legend-dot.income {
    background: #2ca86b;
}

.legend-dot.outgoing {
    background: #e47a77;
}

.cashflow-chart {
    display: flex;
    min-height: 205px;
    margin-top: 14px;
    padding: 10px 0 0;
}

.chart-scale {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    width: 43px;
    padding: 0 7px 19px 0;
    color: #a2adaf;
    font-size: 10px;
    text-align: right;
}

.chart-columns {
    display: grid;
    flex: 1;
    grid-template-columns: repeat(6, minmax(35px, 1fr));
    gap: 10px;
    align-items: end;
    border-bottom: 1px solid #dfe8e4;
    background: repeating-linear-gradient(
        to bottom,
        transparent 0,
        transparent 47px,
        #edf2f0 48px,
        transparent 49px
    );
}

.chart-column {
    display: flex;
    min-width: 0;
    height: 184px;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
}

.chart-bars {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 4px;
    width: 100%;
    height: 150px;
}

.chart-bar {
    display: block;
    width: min(18px, 34%);
    min-height: 3px;
    border-radius: 5px 5px 0 0;
    transition: height 0.3s ease;
}

.chart-bar.income {
    background: linear-gradient(180deg, #59c487, #2da869);
}

.chart-bar.outgoing {
    background: linear-gradient(180deg, #efaaa6, #de7471);
}

.chart-column small {
    width: 100%;
    margin-top: 9px;
    overflow: hidden;
    color: #889793;
    font-size: 10px;
    text-align: center;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chart-empty {
    display: grid;
    min-height: 185px;
    place-items: center;
    color: #98a6a2;
    font-size: 12px;
}

.flow-card-foot {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 18px;
    padding-top: 13px;
    border-top: 1px solid #edf1ef;
    color: #85938e;
    font-size: 11px;
}

.flow-card-foot strong {
    color: #294b3a;
    font-size: 12px;
}

.text-link {
    padding: 0;
    border: 0;
    background: transparent;
    color: #238a56;
    font: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.text-link:hover {
    color: #125b38;
    text-decoration: underline;
}

.recent-flow-list {
    display: grid;
    gap: 1px;
    margin-top: 14px;
}

.recent-flow-row {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
    padding: 9px 0;
    border-bottom: 1px solid #f0f3f2;
}

.recent-flow-row:last-child {
    border-bottom: 0;
}

.recent-flow-icon {
    display: grid;
    flex: 0 0 auto;
    width: 27px;
    height: 27px;
    place-items: center;
    border-radius: 8px;
    background: #eaf8ef;
    color: #238b55;
}

.recent-flow-icon.debit,
.recent-flow-icon.hold {
    background: #fff0ef;
    color: #d16d68;
}

.recent-flow-icon.release {
    background: #edf5ff;
    color: #397fc1;
}

.recent-flow-info {
    display: grid;
    min-width: 0;
    flex: 1;
    gap: 3px;
}

.recent-flow-info strong {
    overflow: hidden;
    color: #33473d;
    font-size: 12px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.recent-flow-info small {
    overflow: hidden;
    color: #9aa6a2;
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.recent-flow-amount {
    flex: 0 0 auto;
    color: #248951;
    font-size: 11px;
    white-space: nowrap;
}

.recent-flow-amount.debit,
.recent-flow-amount.hold {
    color: #d16d68;
}

.recent-flow-amount.release {
    color: #397fc1;
}

@media (max-width: 1060px) {
    .wallet-metrics-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .wallet-dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 620px) {
    .wallet-balance-hero {
        min-height: 0;
        padding: 22px 20px;
    }

    .wallet-hero-action {
        display: none;
    }

    .wallet-metrics-grid {
        grid-template-columns: 1fr;
    }

    .flow-card {
        padding: 15px;
    }

    .booking-receivables-card {
        padding: 15px;
    }

    .booking-receivables-head {
        flex-direction: column;
    }

    .booking-receivables-total {
        text-align: left;
    }

    .booking-receivable-row {
        grid-template-columns: 1fr;
        gap: 5px;
    }

    .flow-card-foot {
        align-items: flex-start;
        flex-direction: column;
    }

    .chart-columns {
        gap: 4px;
    }

    .chart-bar {
        width: 30%;
    }
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

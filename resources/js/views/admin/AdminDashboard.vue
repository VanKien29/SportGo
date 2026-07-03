<template>
    <section class="admin-dashboard accounting-dashboard">
        <section class="dashboard-hero">
            <div>
                <span class="eyebrow">SportGo Admin</span>
                <h1>Kế toán hệ thống</h1>
                <p>
                    Theo dõi tiền hệ thống đang giữ, doanh thu thật, công nợ
                    chủ sân/khách hàng và các dòng tiền cần đối soát.
                </p>
            </div>
            <div class="hero-actions">
                <select v-model="financePeriod" @change="loadDashboard">
                    <option value="week">Tuần này</option>
                    <option value="month">Tháng này</option>
                    <option value="year">Năm nay</option>
                </select>
                <button class="ghost-button" type="button" @click="loadDashboard">
                    <AppIcon name="refresh" size="17" />
                    <span>Tải lại</span>
                </button>
            </div>
        </section>

        <div v-if="error" class="alert error">{{ error }}</div>

        <section class="kpi-grid accounting-kpis">
            <article
                v-for="item in primaryMetrics"
                :key="item.label"
                class="kpi-card"
                :class="item.tone"
            >
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
                <small>{{ item.caption }}</small>
            </article>
        </section>

        <section class="chart-layout accounting-charts">
            <article class="dashboard-panel chart-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Dòng tiền</span>
                        <h2>Tiền vào / tiền ra</h2>
                    </div>
                    <span class="period-label">{{ periodLabel }}</span>
                </div>
                <div class="chart-wrap">
                    <canvas ref="cashFlowChart"></canvas>
                </div>
            </article>

            <article class="dashboard-panel chart-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Cơ cấu</span>
                        <h2>Tổng tiền đang quản lý</h2>
                    </div>
                </div>
                <div class="chart-wrap compact">
                    <canvas ref="compositionChart"></canvas>
                </div>
            </article>
        </section>

        <section class="dashboard-panel accounting-summary">
            <div class="summary-row">
                <span>Booking hệ thống thu hộ</span>
                <strong>{{ money(overview.booking_collected_total) }}</strong>
            </div>
            <div class="summary-row">
                <span>Tiền chi rút ví</span>
                <strong>{{ money(overview.withdrawal_total) }}</strong>
            </div>
            <div class="summary-row">
                <span>Tiền trừ voucher hệ thống</span>
                <strong>{{ money(overview.voucher_cost_total) }}</strong>
            </div>
            <div class="summary-row">
                <span>Phí nền tảng đã thu</span>
                <strong>{{ money(overview.platform_fee_revenue_total) }}</strong>
            </div>
            <div class="summary-row">
                <span>Thanh toán gói hội viên</span>
                <strong>{{ money(overview.membership_revenue_total) }}</strong>
            </div>
        </section>

        <section class="dashboard-panel accounting-ledger">
            <div class="ledger-head">
                <div>
                    <span class="eyebrow">Đối soát</span>
                    <h2>{{ activeTable.title }}</h2>
                    <p>{{ activeTable.caption }}</p>
                </div>
                <div class="ledger-tabs">
                    <button
                        v-for="tab in tableTabs"
                        :key="tab.key"
                        type="button"
                        :class="{ active: currentTab === tab.key }"
                        @click="currentTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div class="accounting-table-wrap">
                <table class="accounting-table">
                    <thead>
                        <tr>
                            <th
                                v-for="column in activeTable.columns"
                                :key="column.key"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading">
                            <td :colspan="activeTable.columns.length">
                                Đang tải dữ liệu kế toán...
                            </td>
                        </tr>
                        <tr v-else-if="!activeRows.length">
                            <td :colspan="activeTable.columns.length">
                                Chưa có dữ liệu trong kỳ này.
                            </td>
                        </tr>
                        <tr v-for="row in activeRows" v-else :key="row.id">
                            <td
                                v-for="column in activeTable.columns"
                                :key="column.key"
                                :class="{ amount: column.type === 'money' }"
                            >
                                {{ formatCell(row, column) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</template>

<script>
import Chart from "chart.js/auto";
import AppIcon from "../../components/AppIcon.vue";
import { api } from "../../services/api.js";

export default {
    name: "AdminDashboard",
    components: { AppIcon },
    data() {
        return {
            financePeriod: "month",
            accounting: null,
            isLoading: true,
            error: null,
            currentTab: "booking_ledgers",
            cashFlowChart: null,
            compositionChart: null,
            tableTabs: [
                {
                    key: "booking_ledgers",
                    label: "Booking thu hộ",
                    title: "Tổng hợp booking",
                    caption: "Các khoản tiền booking online hệ thống đã nhận hộ chủ sân.",
                    columns: [
                        { key: "code", label: "Payment" },
                        { key: "booking_code", label: "Booking" },
                        { key: "customer", label: "Khách" },
                        { key: "venue_cluster", label: "Cụm sân" },
                        { key: "amount", label: "Số tiền", type: "money" },
                        { key: "method", label: "Phương thức" },
                        { key: "paid_at", label: "Paid at", type: "date" },
                    ],
                },
                {
                    key: "withdrawal_ledgers",
                    label: "Yêu cầu rút",
                    title: "Tổng hợp yêu cầu rút",
                    caption: "Các khoản chi ra cho chủ sân và người dùng.",
                    columns: [
                        { key: "code", label: "Mã yêu cầu" },
                        { key: "type", label: "Loại" },
                        { key: "requester", label: "Người nhận" },
                        { key: "scope", label: "Phạm vi" },
                        { key: "amount", label: "Số tiền", type: "money" },
                        { key: "status", label: "Trạng thái", type: "status" },
                        { key: "requested_at", label: "Ngày yêu cầu", type: "date" },
                    ],
                },
                {
                    key: "owner_debts",
                    label: "Công nợ chủ sân",
                    title: "Công nợ chủ sân",
                    caption: "Số tiền hệ thống còn đang giữ cho từng ví chủ sân.",
                    columns: [
                        { key: "owner", label: "Chủ sân" },
                        { key: "venue_cluster", label: "Cụm sân" },
                        { key: "available_balance", label: "Có thể rút", type: "money" },
                        { key: "pending_balance", label: "Đang giữ", type: "money" },
                        { key: "debt_total", label: "Tổng công nợ", type: "money" },
                        { key: "total_withdrawn", label: "Đã chi", type: "money" },
                    ],
                },
                {
                    key: "customer_debts",
                    label: "Công nợ khách",
                    title: "Công nợ khách hàng",
                    caption: "Số dư ví và số dư đang khóa của khách hàng.",
                    columns: [
                        { key: "customer", label: "Khách hàng" },
                        { key: "contact", label: "Liên hệ" },
                        { key: "balance", label: "Số dư", type: "money" },
                        { key: "locked_balance", label: "Đang khóa", type: "money" },
                        { key: "debt_total", label: "Tổng công nợ", type: "money" },
                        { key: "status", label: "Trạng thái", type: "status" },
                    ],
                },
                {
                    key: "voucher_ledgers",
                    label: "Voucher hệ thống",
                    title: "Lịch sử trừ tiền voucher",
                    caption: "Các khoản hệ thống bù voucher cho chủ sân.",
                    columns: [
                        { key: "code", label: "Mã" },
                        { key: "amount", label: "Số tiền", type: "money" },
                        { key: "balance_after", label: "Số dư sau", type: "money" },
                        { key: "reference", label: "Tham chiếu" },
                        { key: "description", label: "Mô tả" },
                        { key: "transacted_at", label: "Thời gian", type: "date" },
                    ],
                },
                {
                    key: "revenue_ledgers",
                    label: "Doanh thu",
                    title: "Lịch sử cộng doanh thu",
                    caption: "Phí nền tảng và thanh toán gói hội viên hệ thống.",
                    columns: [
                        { key: "label", label: "Nguồn thu" },
                        { key: "source", label: "Đối tượng" },
                        { key: "amount", label: "Số tiền", type: "money" },
                        { key: "note", label: "Ghi chú" },
                        { key: "paid_at", label: "Paid at", type: "date" },
                    ],
                },
            ],
        };
    },
    computed: {
        overview() {
            return this.accounting?.overview || {};
        },
        periodLabel() {
            return this.accounting?.period_label || "Kỳ hiện tại";
        },
        primaryMetrics() {
            return [
                {
                    label: "Tiền hệ thống còn lại",
                    value: this.loadingText(this.money(this.overview.system_cash_balance)),
                    caption: "Tổng tiền quản lý trừ công nợ đang giữ",
                    tone: Number(this.overview.system_cash_balance || 0) >= 0 ? "green" : "red",
                },
                {
                    label: "Doanh thu hệ thống",
                    value: this.loadingText(this.money(this.overview.system_revenue)),
                    caption: "Phí nền tảng và gói hội viên",
                    tone: "mint",
                },
                {
                    label: "Công nợ chủ sân",
                    value: this.loadingText(this.money(this.overview.owner_debt_total)),
                    caption: "Ví chủ sân còn phải chi trả",
                    tone: "blue",
                },
                {
                    label: "Công nợ khách hàng",
                    value: this.loadingText(this.money(this.overview.customer_debt_total)),
                    caption: "Số dư ví khách còn đang giữ",
                    tone: "orange",
                },
                {
                    label: "Tổng tiền quản lý",
                    value: this.loadingText(this.money(this.overview.managed_total)),
                    caption: "Tiền còn lại + công nợ chủ sân + công nợ khách",
                    tone: "purple",
                },
            ];
        },
        activeTable() {
            return (
                this.tableTabs.find((tab) => tab.key === this.currentTab) ||
                this.tableTabs[0]
            );
        },
        activeRows() {
            return this.accounting?.tables?.[this.currentTab] || [];
        },
    },
    async mounted() {
        await this.loadDashboard();
    },
    beforeUnmount() {
        this.cashFlowChart?.destroy();
        this.compositionChart?.destroy();
    },
    methods: {
        async loadDashboard() {
            this.isLoading = true;
            this.error = null;
            try {
                const payload = await api(
                    `/api/admin/dashboard?finance_period=${this.financePeriod}`,
                );
                this.accounting = payload.accounting || null;
                await this.$nextTick();
                this.renderCharts();
            } catch (error) {
                this.error = error.message || "Không thể tải dữ liệu kế toán.";
            } finally {
                this.isLoading = false;
            }
        },
        loadingText(value) {
            return this.isLoading ? "..." : value;
        },
        money(amount) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
                maximumFractionDigits: 0,
            }).format(Number(amount || 0));
        },
        shortMoney(amount) {
            const value = Number(amount || 0);
            if (Math.abs(value) >= 1000000) return `${Math.round(value / 1000000)}tr`;
            if (Math.abs(value) >= 1000) return `${Math.round(value / 1000)}k`;
            return value.toLocaleString("vi-VN");
        },
        formatDate(value) {
            if (!value) return "-";
            return new Intl.DateTimeFormat("vi-VN", {
                hour: "2-digit",
                minute: "2-digit",
                day: "2-digit",
                month: "2-digit",
                year: "numeric",
            }).format(new Date(value));
        },
        formatCell(row, column) {
            const value = row[column.key];
            if (column.type === "money") return this.money(value);
            if (column.type === "date") return this.formatDate(value);
            if (column.type === "status") return this.statusLabel(value);
            return value || "-";
        },
        statusLabel(status) {
            const labels = {
                pending: "Chờ xử lý",
                approved: "Đã duyệt",
                paid: "Đã chi",
                completed: "Hoàn tất",
                rejected: "Từ chối",
                cancelled: "Đã hủy",
                active: "Hoạt động",
                locked: "Đang khóa",
                suspended: "Tạm ngưng",
                owner: "Chủ sân",
                user: "Người dùng",
            };
            return labels[status] || status || "-";
        },
        renderCharts() {
            this.renderCashFlowChart();
            this.renderCompositionChart();
        },
        renderCashFlowChart() {
            const canvas = this.$refs.cashFlowChart;
            if (!canvas) return;
            this.cashFlowChart?.destroy();
            const values = this.accounting?.charts?.cash_flow || [];
            const context = canvas.getContext("2d");
            const greenFill = this.chartGradient(context, "#16a34a", 0.18);
            this.cashFlowChart = new Chart(canvas, {
                type: "line",
                data: {
                    labels: values.map((item) => item.label),
                    datasets: [
                        {
                            label: "Tiền vào",
                            data: values.map((item) => item.money_in),
                            borderColor: "#16a34a",
                            backgroundColor: greenFill,
                            fill: true,
                            cubicInterpolationMode: "monotone",
                            tension: 0.22,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            pointBackgroundColor: "#16a34a",
                            pointBorderColor: "#ffffff",
                            pointBorderWidth: 2,
                            borderWidth: 2.5,
                        },
                        {
                            label: "Tiền ra",
                            data: values.map((item) => item.money_out),
                            borderColor: "#f59e0b",
                            backgroundColor: "#f59e0b",
                            fill: false,
                            cubicInterpolationMode: "monotone",
                            tension: 0.22,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            pointBackgroundColor: "#f59e0b",
                            pointBorderColor: "#ffffff",
                            pointBorderWidth: 2,
                            borderWidth: 2.5,
                        },
                        {
                            label: "Chênh lệch",
                            data: values.map((item) => item.net_movement),
                            borderColor: "#64748b",
                            backgroundColor: "#64748b",
                            fill: false,
                            cubicInterpolationMode: "monotone",
                            tension: 0.18,
                            pointRadius: 0,
                            pointHoverRadius: 4,
                            pointBackgroundColor: "#64748b",
                            pointBorderColor: "#ffffff",
                            pointBorderWidth: 2,
                            borderWidth: 2,
                            borderDash: [4, 4],
                        },
                    ],
                },
                options: this.chartOptions(),
            });
        },
        chartGradient(context, color, opacity = 0.16) {
            const gradient = context.createLinearGradient(0, 0, 0, 280);
            const alpha = Math.round(opacity * 255)
                .toString(16)
                .padStart(2, "0");
            gradient.addColorStop(0, `${color}${alpha}`);
            gradient.addColorStop(0.55, `${color}0f`);
            gradient.addColorStop(1, `${color}00`);
            return gradient;
        },
        renderCompositionChart() {
            const canvas = this.$refs.compositionChart;
            if (!canvas) return;
            this.compositionChart?.destroy();
            const slices = (this.accounting?.charts?.managed_composition || [])
                .filter((item) => Number(item.value || 0) > 0);
            const values = slices.length
                ? slices
                : [{ label: "Chưa có dữ liệu", value: 1, group: "empty" }];
            const colors = {
                cash: "#16a34a",
                owner_debt: "#2563eb",
                customer_debt: "#f59e0b",
                empty: "#e2e8f0",
            };
            this.compositionChart = new Chart(canvas, {
                type: "doughnut",
                data: {
                    labels: values.map((item) => item.label),
                    datasets: [
                        {
                            data: values.map((item) => item.value),
                            backgroundColor: values.map((item) => colors[item.group] || "#94a3b8"),
                            borderColor: "#fff",
                            borderWidth: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: "64%",
                    plugins: {
                        legend: {
                            position: "bottom",
                            labels: {
                                boxWidth: 10,
                                color: "#526056",
                                font: { size: 12, weight: 700 },
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) =>
                                    values[context.dataIndex]?.group === "empty"
                                        ? "Chưa có dữ liệu"
                                        : `${context.label}: ${this.money(context.parsed)}`,
                            },
                        },
                    },
                },
            });
        },
        chartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: "index" },
                elements: {
                    line: {
                        capBezierPoints: true,
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            color: "#66756d",
                            maxRotation: 0,
                            font: { size: 12, weight: 700 },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: {
                            color: "rgba(148, 163, 184, 0.12)",
                            drawTicks: false,
                        },
                        ticks: {
                            color: "#66756d",
                            padding: 10,
                            callback: (value) => this.shortMoney(value),
                        },
                    },
                },
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true,
                            color: "#526056",
                            font: { size: 12, weight: 700 },
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) =>
                                `${context.dataset.label}: ${this.money(context.parsed.y)}`,
                        },
                    },
                },
            };
        },
    },
};
</script>

<style src="../../../css/admin/dashboard.css" scoped></style>

<template>
    <section class="admin-dashboard">
        <section class="dashboard-hero">
            <div>
                <span class="eyebrow">SportGo Admin</span>
                <h1>Bảng điều khiển</h1>
                <p>
                    Theo dõi doanh thu hệ thống, tiền booking thu hộ và các
                    khoản phí cần xử lý.
                </p>
            </div>
            <div class="hero-actions">
                <select v-model="financePeriod" @change="loadDashboard">
                    <option value="week">Tuần này</option>
                    <option value="month">Tháng này</option>
                    <option value="year">Năm nay</option>
                </select>
                <button
                    class="ghost-button"
                    type="button"
                    @click="loadDashboard"
                >
                    <AppIcon name="refresh" size="17" />
                    <span>Tải lại</span>
                </button>
            </div>
        </section>

        <div v-if="error" class="alert error">{{ error }}</div>

        <section class="kpi-grid">
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

        <section class="chart-layout">
            <article class="dashboard-panel chart-panel trend-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Dòng tiền</span>
                        <h2>Doanh thu và chi phí</h2>
                    </div>
                    <RouterLink to="/admin/platform-fee-ledgers"
                        >Xem phí nền tảng</RouterLink
                    >
                </div>
                <div class="chart-wrap">
                    <canvas ref="financeComboChart"></canvas>
                </div>
            </article>

            <article class="dashboard-panel chart-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Cơ cấu</span>
                        <h2>Tiền trong kỳ</h2>
                    </div>
                    <span class="period-label">{{
                        finance?.period_label || "Kỳ hiện tại"
                    }}</span>
                </div>
                <div class="chart-wrap compact">
                    <canvas ref="financeDonutChart"></canvas>
                </div>
            </article>
        </section>

        <section class="bottom-layout">
            <article class="dashboard-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Cần xử lý</span>
                        <h2>Việc ưu tiên</h2>
                    </div>
                </div>
                <div class="work-list">
                    <RouterLink
                        v-for="item in actionItems"
                        :key="item.label"
                        class="work-row"
                        :to="item.to"
                    >
                        <span class="work-icon"
                            ><AppIcon :name="item.icon" size="18"
                        /></span>
                        <span>
                            <strong>{{ item.label }}</strong>
                            <small>{{ item.caption }}</small>
                        </span>
                        <b>{{ item.value }}</b>
                    </RouterLink>
                </div>
            </article>

            <article class="dashboard-panel">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow">Truy cập nhanh</span>
                        <h2>Quản trị</h2>
                    </div>
                </div>
                <div class="quick-grid">
                    <RouterLink
                        v-for="item in quickLinks"
                        :key="item.to"
                        class="quick-link"
                        :to="item.to"
                    >
                        <AppIcon :name="item.icon" size="18" />
                        <span>{{ item.label }}</span>
                    </RouterLink>
                </div>
            </article>
        </section>
    </section>
</template>

<script>
import { api } from "../../services/api.js";
import { getPlatformFeeDashboardMetrics } from "../../services/platformFeeLedger.service.js";
import AppIcon from "../../components/AppIcon.vue";
import Chart from "chart.js/auto";

export default {
    name: "AdminDashboard",
    components: { AppIcon },
    data() {
        return {
            stats: {
                finance: null,
            },
            financePeriod: "month",
            financeComboChart: null,
            financeDonutChart: null,
            feeMetrics: {
                pending: 0,
                overdue: 0,
                pending_amount: 0,
                overdue_amount: 0,
                paid_this_month: 0,
                email_failed: 0,
            },
            isLoading: true,
            error: null,
            quickLinks: [
                {
                    label: "Thanh toán booking",
                    icon: "creditCard",
                    to: "/admin/payments",
                },
                {
                    label: "Hoàn tiền & rút tiền",
                    icon: "creditCard",
                    to: "/admin/finance-operations",
                },
                {
                    label: "Voucher hệ thống",
                    icon: "tag",
                    to: "/admin/vouchers",
                },
                {
                    label: "Cụm sân",
                    icon: "building",
                    to: "/admin/venue-clusters",
                },
            ],
        };
    },
    computed: {
        finance() {
            return this.stats.finance || null;
        },
        systemRevenue() {
            return Number(this.finance?.revenue?.total || 0);
        },
        custodyTotal() {
            return Number(this.finance?.revenue?.custody_total || 0);
        },
        expenseTotal() {
            return Number(this.finance?.promotion_expenses?.total || 0);
        },
        netRevenue() {
            return Number(
                this.finance?.net_revenue ||
                    this.systemRevenue - this.expenseTotal,
            );
        },
        primaryMetrics() {
            return [
                {
                    label: "Doanh thu hệ thống",
                    value: this.loadingText(
                        this.formatCurrency(this.systemRevenue),
                    ),
                    caption: "Phí nền tảng và khoản admin thực nhận",
                    tone: "green",
                },
                {
                    label: "Booking thu hộ",
                    value: this.loadingText(
                        this.formatCurrency(this.custodyTotal),
                    ),
                    caption: "Tiền của chủ sân, không tính vào doanh thu admin",
                    tone: "blue",
                },
                {
                    label: "Chi phí hệ thống",
                    value: this.loadingText(
                        this.formatCurrency(this.expenseTotal),
                    ),
                    caption: "Voucher hệ thống và hoàn ví",
                    tone: "orange",
                },
                {
                    label: "Lãi ròng tham chiếu",
                    value: this.loadingText(
                        this.formatCurrency(this.netRevenue),
                    ),
                    caption: "Doanh thu hệ thống trừ chi phí hệ thống",
                    tone: this.netRevenue >= 0 ? "mint" : "red",
                },
            ];
        },
        actionItems() {
            return [
                {
                    label: "Phí nền tảng chờ thu",
                    caption: "Các kỳ phí đang pending",
                    value: this.loadingText(this.feeMetrics.pending),
                    icon: "clock",
                    to: "/admin/platform-fee-ledgers?status=pending",
                },
                {
                    label: "Phí nền tảng quá hạn",
                    caption: this.formatCurrency(
                        this.feeMetrics.overdue_amount,
                    ),
                    value: this.loadingText(this.feeMetrics.overdue),
                    icon: "alert",
                    to: "/admin/platform-fee-ledgers?status=overdue",
                },
                {
                    label: "Email nhắc phí lỗi",
                    caption: "Cần kiểm tra email chủ sân hoặc SMTP",
                    value: this.loadingText(this.feeMetrics.email_failed),
                    icon: "alert",
                    to: "/admin/platform-fee-ledgers?email_status=failed",
                },
                {
                    label: "Thanh toán cần đối soát",
                    caption: "Booking và giao dịch gateway",
                    value: "",
                    icon: "creditCard",
                    to: "/admin/payments",
                },
            ];
        },
        financeChartValues() {
            return this.finance?.charts?.trend || [];
        },
        compositionSlices() {
            return (this.finance?.charts?.composition || []).filter(
                (item) => Number(item.value || 0) > 0,
            );
        },
    },
    async mounted() {
        await this.loadDashboard();
    },
    beforeUnmount() {
        this.financeComboChart?.destroy();
        this.financeDonutChart?.destroy();
    },
    methods: {
        async loadDashboard() {
            this.isLoading = true;
            this.error = null;
            try {
                const [stats, feeMetrics] = await Promise.all([
                    api(
                        `/api/admin/dashboard?finance_period=${this.financePeriod}`,
                    ),
                    getPlatformFeeDashboardMetrics(),
                ]);
                this.stats = stats;
                this.feeMetrics = feeMetrics;
                await this.$nextTick();
                this.renderFinanceCharts();
            } catch (error) {
                this.error =
                    error.message || "Không thể tải dữ liệu dashboard.";
            } finally {
                this.isLoading = false;
            }
        },
        loadingText(value) {
            return this.isLoading ? "..." : value;
        },
        formatCurrency(amount) {
            return new Intl.NumberFormat("vi-VN", {
                style: "currency",
                currency: "VND",
            }).format(amount || 0);
        },
        compactCurrency(amount) {
            const value = Number(amount || 0);
            if (Math.abs(value) >= 1000000)
                return `${Math.round(value / 1000000)}tr`;
            if (Math.abs(value) >= 1000) return `${Math.round(value / 1000)}k`;
            return value.toLocaleString("vi-VN");
        },
        renderFinanceCharts() {
            this.renderFinanceComboChart();
            this.renderFinanceDonutChart();
        },
        renderFinanceComboChart() {
            const canvas = this.$refs.financeComboChart;
            if (!canvas) return;

            this.financeComboChart?.destroy();
            const values = this.financeChartValues;
            this.financeComboChart = new Chart(canvas, {
                type: "bar",
                data: {
                    labels: values.map((item) => item.label),
                    datasets: [
                        {
                            type: "bar",
                            label: "Doanh thu",
                            data: values.map((item) => item.system_revenue),
                            backgroundColor: "#16a34a",
                            borderRadius: 8,
                            maxBarThickness: 30,
                        },
                        {
                            type: "bar",
                            label: "Chi phí",
                            data: values.map((item) => item.system_expense),
                            backgroundColor: "#f59e0b",
                            borderRadius: 8,
                            maxBarThickness: 30,
                        },
                        {
                            type: "line",
                            label: "Booking",
                            data: values.map((item) => item.booking_custody),
                            borderColor: "#2563eb",
                            backgroundColor: "#2563eb",
                            tension: 0.35,
                            pointRadius: 3,
                            borderWidth: 3,
                        },
                    ],
                },
                options: this.chartOptions(),
            });
        },
        renderFinanceDonutChart() {
            const canvas = this.$refs.financeDonutChart;
            if (!canvas) return;

            this.financeDonutChart?.destroy();
            const slices = this.compositionSlices.length
                ? this.compositionSlices
                : [{ label: "Chưa có dữ liệu", value: 1, group: "empty" }];
            this.financeDonutChart = new Chart(canvas, {
                type: "doughnut",
                data: {
                    labels: slices.map((item) => item.label),
                    datasets: [
                        {
                            data: slices.map((item) => item.value),
                            backgroundColor: slices.map(
                                (item) =>
                                    ({
                                        revenue: "#16a34a",
                                        custody: "#2563eb",
                                        expense: "#f59e0b",
                                        empty: "#e2e8f0",
                                    })[item.group] || "#94a3b8",
                            ),
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
                                label: (context) => {
                                    if (
                                        slices[context.dataIndex]?.group ===
                                        "empty"
                                    )
                                        return "Chưa có dữ liệu";
                                    return `${context.label}: ${this.formatCurrency(context.parsed)}`;
                                },
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
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: "#66756d", font: { weight: 700 } },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: "rgba(148, 163, 184, 0.18)" },
                        ticks: {
                            color: "#66756d",
                            callback: (value) => this.compactCurrency(value),
                        },
                    },
                },
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
                                `${context.dataset.label}: ${this.formatCurrency(context.parsed.y)}`,
                        },
                    },
                },
            };
        },
    },
};
</script>

<style src="../../../css/admin/dashboard.css" scoped></style>

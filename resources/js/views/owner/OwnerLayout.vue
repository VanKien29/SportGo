<template>
    <SidebarLayout brand-sub="Quản lý sân" dashboard-route="/owner/dashboard">
        <template #nav-items>
            <div class="owner-nav">
                <router-link
                    to="/owner/dashboard"
                    class="nav-item"
                    active-class="nav-active"
                >
                    <span>Tổng quan</span>
                </router-link>
                <router-link
                    to="/owner/venue-clusters"
                    class="nav-item"
                    active-class="nav-active"
                >
                    <span>Quản lý cụm sân</span>
                </router-link>
                <button class="nav-item nav-muted" type="button">
                    <span>Lịch sân</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Booking Online</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Đặt Sân Cố Định</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Booking Tại Quầy</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Trạng thái Sân</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Yêu cầu thêm sân</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Tin nhắn</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Bài đăng sân</span>
                </button>
                <router-link
                    to="/owner/pricing"
                    class="nav-item"
                    active-class="nav-active"
                >
                    <span>Cấu hình Giá</span>
                </router-link>
                <button class="nav-item nav-muted" type="button">
                    <span>Giao lưu tại sân</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Phí nền tảng</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Đánh giá</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Yêu cầu hủy/hoàn tiền</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Khiếu nại</span>
                </button>
                <button class="nav-item nav-muted" type="button">
                    <span>Cấu hình & Thanh toán</span>
                </button>
            </div>
        </template>
        <template #topbar-title>
            <span>{{ currentTitle }}</span>
        </template>
        <router-view />
    </SidebarLayout>
</template>

<script>
import SidebarLayout from "../../components/SidebarLayout.vue";

export default {
    name: "OwnerLayout",
    components: { SidebarLayout },
    computed: {
        currentTitle() {
            const map = {
                "owner-dashboard": "Dashboard",
                "owner-profile": "Thông tin cá nhân",
                "owner-venue-clusters": "Quản lý cụm sân",
                "owner-venue-courts": "Quản lý sân con",
                "owner-pricing": "Cấu hình Giá",
            };

            return map[this.$route.name] || "Chủ sân";
        },
    },
};
</script>

<style scoped>
.owner-nav {
    display: grid;
    gap: 4px;
}

.owner-nav .nav-item {
    width: 100%;
    min-height: 38px;
    display: flex;
    align-items: center;
    padding: 9px 12px;
    border-radius: 8px;
    color: rgba(255, 255, 255, 0.74);
    font-size: 13px;
    font-weight: 750;
    line-height: 1.25;
    text-align: left;
}

.owner-nav .nav-item:hover,
.owner-nav .nav-active {
    background: #059669;
    color: #fff;
}

.nav-muted {
    opacity: 0.78;
}
</style>

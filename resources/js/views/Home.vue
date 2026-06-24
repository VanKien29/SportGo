<template>
    <div class="page-container">
        <!-- Navbar -->
        <header class="site-header">
            <div class="nav-container">
                <!-- Logo -->
                <router-link to="/" class="logo-link">
                    <div class="logo-icon">
                        <svg
                            width="18"
                            height="18"
                            viewBox="0 0 32 32"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <circle cx="16" cy="16" r="15" />
                            <path
                                d="M16 4 C20 8 22 12 22 16 C22 20 20 24 16 28"
                                fill="none"
                            />
                            <path
                                d="M16 4 C12 8 10 12 10 16 C10 20 12 24 16 28"
                                fill="none"
                            />
                            <line x1="4" y1="12" x2="28" y2="12" />
                            <line x1="4" y1="20" x2="28" y2="20" />
                        </svg>
                    </div>
                    <span class="logo-text"
                        >Sport<span class="logo-accent">Go</span></span
                    >
                </router-link>

                <!-- Menu Links Desktop -->
                <nav class="nav-links">
                    <router-link to="/" class="nav-item active-link"
                        >Trang chủ</router-link
                    >
                    <router-link to="/venues" class="nav-item"
                        >Sân thể thao</router-link
                    >
                    <router-link
                        v-if="user && user.role === 'user'"
                        to="/booking"
                        class="nav-item"
                        >Lịch & Đặt sân</router-link
                    >
                    <router-link to="/become-partner" class="nav-item"
                        >Đối tác chủ sân</router-link
                    >
                </nav>

                <!-- Right Controls Desktop -->
                <div class="nav-right">
                    <template v-if="!user">
                        <router-link to="/login" class="btn-login"
                            >Đăng nhập</router-link
                        >
                        <router-link to="/register" class="btn-register"
                            >Đăng ký</router-link
                        >
                    </template>
                    <template v-else>
                        <div
                            class="user-dropdown-container"
                            @mouseenter="showDropdown = true"
                            @mouseleave="scheduleHide"
                        >
                            <button class="btn-avatar" @click="toggleDropdown">
                                {{ userInitial }}
                            </button>

                            <transition name="dd">
                                <div
                                    v-if="showDropdown"
                                    class="user-dropdown"
                                    @mouseenter="cancelHide"
                                    @mouseleave="scheduleHide"
                                >
                                    <div class="dropdown-header">
                                        <div class="dd-avatar">
                                            {{ userInitial }}
                                        </div>
                                        <div class="dd-info">
                                            <div class="dd-name">
                                                {{ user.fullName }}
                                            </div>
                                            <div class="dd-role">
                                                {{ roleLabel }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dd-divider"></div>
                                    <router-link
                                        :to="profileRoute"
                                        class="dd-item"
                                        @click="showDropdown = false"
                                    >
                                        <svg
                                            width="14"
                                            height="14"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"
                                            />
                                            <circle cx="12" cy="7" r="4" />
                                        </svg>
                                        Thông tin cá nhân
                                    </router-link>
                                    <button
                                        v-if="user.role === 'owner'"
                                        @click="goToManage"
                                        class="dd-item dd-manage"
                                    >
                                        <svg
                                            width="14"
                                            height="14"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"
                                            />
                                            <circle cx="12" cy="10" r="3" />
                                        </svg>
                                        Quản lý sân
                                    </button>
                                    <button
                                        v-if="user.role === 'admin'"
                                        @click="goToManage"
                                        class="dd-item dd-manage"
                                    >
                                        <svg
                                            width="14"
                                            height="14"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <rect
                                                x="3"
                                                y="3"
                                                width="7"
                                                height="7"
                                                rx="1"
                                            />
                                            <rect
                                                x="14"
                                                y="3"
                                                width="7"
                                                height="7"
                                                rx="1"
                                            />
                                            <rect
                                                x="3"
                                                y="14"
                                                width="7"
                                                height="7"
                                                rx="1"
                                            />
                                            <rect
                                                x="14"
                                                y="14"
                                                width="7"
                                                height="7"
                                                rx="1"
                                            />
                                        </svg>
                                        Quản trị hệ thống
                                    </button>
                                    <div class="dd-divider"></div>
                                    <button
                                        @click="handleLogout"
                                        class="dd-item dd-logout"
                                    >
                                        <svg
                                            width="14"
                                            height="14"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"
                                            />
                                            <polyline
                                                points="16 17 21 12 16 7"
                                            />
                                            <line
                                                x1="21"
                                                y1="12"
                                                x2="9"
                                                y2="12"
                                            />
                                        </svg>
                                        Đăng xuất
                                    </button>
                                </div>
                            </transition>
                        </div>
                    </template>
                </div>

                <!-- Mobile Menu Toggle Button -->
                <button
                    @click="menuMobileOpen = !menuMobileOpen"
                    class="btn-menu-mobile"
                    aria-label="Toggle Menu"
                >
                    <svg
                        v-if="!menuMobileOpen"
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <line x1="4" x2="20" y1="12" y2="12" />
                        <line x1="4" x2="20" y1="6" y2="6" />
                        <line x1="4" x2="20" y1="18" y2="18" />
                    </svg>
                    <svg
                        v-else
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M18 6 6 18" />
                        <path d="m6 6 12 12" />
                    </svg>
                </button>
            </div>

            <!-- Mobile Menu Panel -->
            <transition name="slide-down">
                <div v-if="menuMobileOpen" class="mobile-menu-panel">
                    <ul class="mobile-links">
                        <li>
                            <router-link
                                to="/"
                                @click="menuMobileOpen = false"
                                class="m-link"
                                >Trang chủ</router-link
                            >
                        </li>
                        <li>
                            <router-link
                                to="/venues"
                                @click="menuMobileOpen = false"
                                class="m-link"
                                >Sân thể thao</router-link
                            >
                        </li>
                        <li v-if="user && user.role === 'user'">
                            <router-link
                                to="/booking"
                                @click="menuMobileOpen = false"
                                class="m-link"
                                >Lịch & Đặt sân</router-link
                            >
                        </li>
                        <li>
                            <router-link
                                to="/become-partner"
                                @click="menuMobileOpen = false"
                                class="m-link"
                                >Đối tác chủ sân</router-link
                            >
                        </li>
                    </ul>
                    <div class="mobile-auth">
                        <template v-if="!user">
                            <router-link
                                to="/login"
                                @click="menuMobileOpen = false"
                                class="btn-mobile-login"
                                >Đăng nhập</router-link
                            >
                            <router-link
                                to="/register"
                                @click="menuMobileOpen = false"
                                class="btn-mobile-register"
                                >Đăng ký</router-link
                            >
                        </template>
                        <template v-else>
                            <div class="mobile-user-info">
                                <div class="dd-avatar">{{ userInitial }}</div>
                                <div>
                                    <div class="mobile-username">
                                        {{ user.fullName }}
                                    </div>
                                    <div class="mobile-role">
                                        {{ roleLabel }}
                                    </div>
                                </div>
                            </div>
                            <router-link
                                to="/profile"
                                @click="menuMobileOpen = false"
                                class="mobile-menu-btn"
                                >Thông tin cá nhân</router-link
                            >
                            <button
                                v-if="user.role === 'owner'"
                                @click="goToManageMobile"
                                class="mobile-menu-btn text-bold"
                            >
                                Quản lý sân
                            </button>
                            <button
                                v-if="user.role === 'admin'"
                                @click="goToManageMobile"
                                class="mobile-menu-btn text-bold"
                            >
                                Quản trị hệ thống
                            </button>
                            <button
                                @click="handleLogoutMobile"
                                class="btn-mobile-logout"
                            >
                                Đăng xuất
                            </button>
                        </template>
                    </div>
                </div>
            </transition>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Hero Section -->
            <section class="hero-section">
                <!-- Action Buttons (Monochrome: Black & White) -->
                <div class="hero-actions">
                    <router-link to="/venues" class="btn-hero-primary">
                        Đặt sân ngay
                    </router-link>
                    <router-link
                        to="/become-partner"
                        class="btn-hero-secondary"
                    >
                        Trở thành đối tác chủ sân
                    </router-link>
                </div>

                <!-- Mockup Display -->
                <div class="mockup-container">
                    <div class="mockup-mask"></div>
                    <div class="mockup-frame">
                        <img
                            class="mockup-img img-dark"
                            src="https://tailark.com//_next/image?url=%2Fmail2.png&w=3840&q=75"
                            alt="app screen dark"
                        />
                        <img
                            class="mockup-img img-light"
                            src="https://tailark.com/_next/image?url=%2Fmail2-light.png&w=3840&q=75"
                            alt="app screen light"
                        />
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section class="features-section">
                <div class="features-inner">
                    <div class="features-header">
                        <h2 class="features-title">
                            Nền tảng thể thao toàn diện của bạn
                        </h2>
                        <p class="features-subtitle">
                            Cung cấp các công cụ tối ưu cho cả người chơi tìm
                            sân và các chủ doanh nghiệp quản lý sân thể thao một
                            cách thông minh, tự động hóa.
                        </p>
                    </div>

                    <div class="features-grid">
                        <!-- Feature Card 1 -->
                        <div class="feature-card">
                            <div class="feature-icon">
                                <svg
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle cx="11" cy="11" r="8" />
                                    <line
                                        x1="21"
                                        y1="21"
                                        x2="16.65"
                                        y2="16.65"
                                    />
                                </svg>
                            </div>
                            <h3 class="feature-card-title">Tìm sân dễ dàng</h3>
                            <p class="feature-card-desc">
                                Hệ thống tìm kiếm thông minh theo bộ lọc khoảng
                                cách, đánh giá, loại sân và khung giờ trống thực
                                tế. Tiết kiệm thời gian kết nối.
                            </p>
                        </div>

                        <!-- Feature Card 2 -->
                        <div class="feature-card">
                            <div class="feature-icon">
                                <svg
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <rect
                                        x="3"
                                        y="4"
                                        width="18"
                                        height="18"
                                        rx="2"
                                        ry="2"
                                    />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                            </div>
                            <h3 class="feature-card-title">
                                Đặt lịch tức thì 24/7
                            </h3>
                            <p class="feature-card-desc">
                                Xem lịch trực quan, chọn giờ đặt và thanh toán
                                online nhanh chóng bằng nhiều phương thức tiện
                                lợi. Xác nhận lịch tức thì bằng SMS/Email.
                            </p>
                        </div>

                        <!-- Feature Card 3 -->
                        <div class="feature-card">
                            <div class="feature-icon">
                                <svg
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"
                                    />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                            </div>
                            <h3 class="feature-card-title">
                                Quản lý chuyên nghiệp
                            </h3>
                            <p class="feature-card-desc">
                                Hệ thống báo cáo tài chính, quản lý ca làm việc,
                                hóa đơn, thông tin thành viên chi tiết dành cho
                                chủ sân. Đơn giản hóa vận hành.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>

<script>

import { getAuth, logout } from "../stores/auth.js";

export default {
    name: "HomeView",
    components: { },
    data() {
        return {
            user: getAuth(),
            menuMobileOpen: false,
            showDropdown: false,
            hideTimer: null,
            isLoaded: false,
        };
    },
    computed: {
        userInitial() {
            return this.user?.fullName?.charAt(0)?.toUpperCase() || "?";
        },
        roleLabel() {
            const map = {
                admin: "Quản trị viên",
                owner: "Chủ sân",
                user: "Người chơi",
            };
            return map[this.user?.role] || "";
        },
        profileRoute() {
            if (!this.user) return "/login";
            if (this.user.role === "owner") return "/owner/profile";
            return "/profile";
        },
    },
    mounted() {
        setTimeout(() => {
            this.isLoaded = true;
        }, 100);
    },
    beforeUnmount() {
        this.clearTimer();
    },
    methods: {
        toggleDropdown() {
            this.showDropdown = !this.showDropdown;
        },
        scheduleHide() {
            this.hideTimer = setTimeout(() => {
                this.showDropdown = false;
            }, 200);
        },
        cancelHide() {
            if (this.hideTimer) clearTimeout(this.hideTimer);
        },
        goToManage() {
            this.showDropdown = false;
            const role = this.user?.role;
            if (role === "admin") {
                this.$router.push("/admin/dashboard");
            } else if (role === "owner") {
                this.$router.push("/owner/dashboard");
            }
        },
        goToManageMobile() {
            this.menuMobileOpen = false;
            const role = this.user?.role;
            if (role === "admin") {
                this.$router.push("/admin/dashboard");
            } else if (role === "owner") {
                this.$router.push("/owner/dashboard");
            }
        },
        async handleLogout() {
            await logout();
            this.user = null;
            this.showDropdown = false;
            this.$router.push("/login");
        },
        async handleLogoutMobile() {
            await logout();
            this.user = null;
            this.menuMobileOpen = false;
            this.$router.push("/login");
        },
        clearTimer() {
            if (this.hideTimer) {
                clearTimeout(this.hideTimer);
                this.hideTimer = null;
            }
        },
    },
};
</script>

<style scoped>
/* Reset base inside component for maximum isolation */
.page-container {
    min-height: 100vh;
    background-color: #ffffff;
    color: #09090b;
    font-family:
        -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial,
        sans-serif;
    transition:
        background-color 0.3s,
        color 0.3s;
}

:global([data-theme="dark"]) .page-container {
    background-color: #09090b;
    color: #f4f4f5;
}

/* Site Header (Navbar) */
.site-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;
    height: 64px;
    background-color: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid #e4e4e7;
    transition:
        background-color 0.3s,
        border-color 0.3s;
}

:global([data-theme="dark"]) .site-header {
    background-color: rgba(9, 9, 11, 0.9);
    border-bottom-color: #27272a;
}

.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-sizing: border-box;
}

/* Logo */
.logo-link {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: inherit;
}

.logo-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background-color: #09090b;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
}

:global([data-theme="dark"]) .logo-icon {
    background-color: #ffffff;
    color: #09090b;
}

.logo-text {
    font-size: 20px;
    font-weight: 900;
    letter-spacing: -0.5px;
}

.logo-accent {
    color: #71717a;
}

/* Nav Menu Items */
.nav-links {
    display: flex;
    align-items: center;
    gap: 28px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-item {
    font-size: 14px;
    font-weight: 700;
    color: #52525b;
    text-decoration: none;
    transition: color 0.2s;
}

.nav-item:hover,
.nav-item.active-link {
    color: #09090b;
}

:global([data-theme="dark"]) .nav-item {
    color: #a1a1aa;
}

:global([data-theme="dark"]) .nav-item:hover,
:global([data-theme="dark"]) .nav-item.active-link {
    color: #ffffff;
}

/* Nav Right Actions */
.nav-right {
    display: flex;
    align-items: center;
    gap: 16px;
}

.btn-login {
    font-size: 14px;
    font-weight: 700;
    color: #52525b;
    text-decoration: none;
    transition: color 0.2s;
}

.btn-login:hover {
    color: #09090b;
}

:global([data-theme="dark"]) .btn-login {
    color: #a1a1aa;
}

:global([data-theme="dark"]) .btn-login:hover {
    color: #ffffff;
}

.btn-register {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 36px;
    padding: 0 16px;
    background-color: #09090b;
    color: #ffffff !important;
    font-size: 14px;
    font-weight: 700;
    border-radius: 8px;
    text-decoration: none;
    transition: background-color 0.2s;
}

.btn-register:hover {
    background-color: #27272a;
}

:global([data-theme="dark"]) .btn-register {
    background-color: #ffffff;
    color: #09090b !important;
}

:global([data-theme="dark"]) .btn-register:hover {
    background-color: #e4e4e7;
}

/* User Avatar / Dropdown */
.user-dropdown-container {
    position: relative;
}

.btn-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: #09090b;
    color: #ffffff;
    font-weight: 700;
    font-size: 14px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s;
}

.btn-avatar:hover {
    transform: scale(1.05);
}

:global([data-theme="dark"]) .btn-avatar {
    background-color: #ffffff;
    color: #09090b;
}

.user-dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    width: 240px;
    background-color: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    padding: 8px;
    z-index: 110;
    box-sizing: border-box;
}

:global([data-theme="dark"]) .user-dropdown {
    background-color: #18181b;
    border-color: #27272a;
}

.dropdown-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
}

.dd-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: #09090b;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}

:global([data-theme="dark"]) .dd-avatar {
    background-color: #ffffff;
    color: #09090b;
}

.dd-info {
    overflow: hidden;
}

.dd-name {
    font-weight: 700;
    font-size: 13px;
    color: #09090b;
    text-overflow: ellipsis;
    overflow: hidden;
    white-space: nowrap;
}

:global([data-theme="dark"]) .dd-name {
    color: #ffffff;
}

.dd-role {
    font-size: 11px;
    color: #71717a;
    margin-top: 2px;
}

.dd-divider {
    height: 1px;
    background-color: #e4e4e7;
    margin: 6px 0;
}

:global([data-theme="dark"]) .dd-divider {
    background-color: #27272a;
}

.dd-item {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 8px;
    font-size: 12px;
    color: #27272a;
    background: none;
    border: none;
    border-radius: 6px;
    text-align: left;
    text-decoration: none;
    cursor: pointer;
    transition:
        background-color 0.2s,
        color 0.2s;
    box-sizing: border-box;
}

.dd-item:hover {
    background-color: #f4f4f5;
    color: #09090b;
}

:global([data-theme="dark"]) .dd-item {
    color: #d4d4d8;
}

:global([data-theme="dark"]) .dd-item:hover {
    background-color: #27272a;
    color: #ffffff;
}

.dd-manage {
    font-weight: 700;
    color: #09090b;
}

:global([data-theme="dark"]) .dd-manage {
    color: #ffffff;
}

.dd-logout {
    color: #ef4444;
}

.dd-logout:hover {
    background-color: #fef2f2;
}

:global([data-theme="dark"]) .dd-logout:hover {
    background-color: rgba(239, 68, 68, 0.1);
}

/* Mobile Nav Toggle */
.btn-menu-mobile {
    display: none;
    background: none;
    border: none;
    cursor: pointer;
    color: #09090b;
    padding: 4px;
}

:global([data-theme="dark"]) .btn-menu-mobile {
    color: #ffffff;
}

/* Main Content Area */
.main-content {
    padding-top: 64px;
    box-sizing: border-box;
}

/* Hero Section */
.hero-section {
    max-width: 900px;
    margin: 0 auto;
    padding: 40px 24px 0;
    text-align: center;
    box-sizing: border-box;
}

.hero-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-bottom: 48px;
}

.btn-hero-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 48px;
    padding: 0 32px;
    background-color: #09090b;
    color: #ffffff !important;
    font-size: 15px;
    font-weight: 700;
    border-radius: 8px;
    text-decoration: none;
    transition: background-color 0.2s;
    box-shadow: 0 4px 12px rgba(9, 9, 11, 0.1);
}

.btn-hero-primary:hover {
    background-color: #27272a;
}

:global([data-theme="dark"]) .btn-hero-primary {
    background-color: #ffffff;
    color: #09090b !important;
}

:global([data-theme="dark"]) .btn-hero-primary:hover {
    background-color: #e4e4e7;
}

.btn-hero-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 48px;
    padding: 0 28px;
    background-color: #ffffff;
    color: #09090b !important;
    font-size: 15px;
    font-weight: 700;
    border-radius: 8px;
    border: 1px solid #d4d4d8;
    text-decoration: none;
    transition:
        background-color 0.2s,
        border-color 0.2s;
}

.btn-hero-secondary:hover {
    background-color: #f4f4f5;
}

:global([data-theme="dark"]) .btn-hero-secondary {
    background-color: #18181b;
    color: #ffffff !important;
    border-color: #27272a;
}

:global([data-theme="dark"]) .btn-hero-secondary:hover {
    background-color: #27272a;
}

/* Mockup Frame */
.mockup-container {
    position: relative;
    margin-top: 40px;
    box-sizing: border-box;
}

.mockup-mask {
    position: absolute;
    inset: 0;
    z-index: 10;
    background: linear-gradient(to bottom, transparent 40%, #ffffff 95%);
    pointer-events: none;
}

:global([data-theme="dark"]) .mockup-mask {
    background: linear-gradient(to bottom, transparent 40%, #09090b 95%);
}

.mockup-frame {
    position: relative;
    background-color: #f4f4f5;
    border: 1px solid #e4e4e7;
    border-radius: 12px;
    padding: 6px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
}

:global([data-theme="dark"]) .mockup-frame {
    background-color: #18181b;
    border-color: #27272a;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

.mockup-img {
    width: 100%;
    aspect-ratio: 16/9;
    border-radius: 8px;
    object-fit: cover;
    object-position: top;
    display: block;
}

.img-dark {
    display: none;
}

:global([data-theme="dark"]) .img-dark {
    display: block;
}

:global([data-theme="dark"]) .img-light {
    display: none;
}





/* Features Section */
.features-section {
    padding: 80px 24px;
    background-color: #fafafa;
}

:global([data-theme="dark"]) .features-section {
    background-color: #0c0c0e;
}

.features-inner {
    max-width: 1000px;
    margin: 0 auto;
}

.features-header {
    text-align: center;
    margin-bottom: 56px;
}

.features-title {
    font-size: 32px;
    font-weight: 900;
    color: #09090b;
    margin: 0 0 16px;
    letter-spacing: -0.5px;
}

:global([data-theme="dark"]) .features-title {
    color: #ffffff;
}

.features-subtitle {
    font-size: 14px;
    line-height: 1.6;
    color: #71717a;
    max-width: 600px;
    margin: 0 auto;
}

:global([data-theme="dark"]) .features-subtitle {
    color: #a1a1aa;
}

.features-grid {
    display: grid;
    grid-template-cols: repeat(3, 1fr);
    gap: 24px;
}

.feature-card {
    background-color: #ffffff;
    border: 1px solid #e4e4e7;
    border-radius: 12px;
    padding: 32px;
    transition:
        transform 0.3s,
        box-shadow 0.3s;
}

.feature-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

:global([data-theme="dark"]) .feature-card {
    background-color: #18181b;
    border-color: #27272a;
}

:global([data-theme="dark"]) .feature-card:hover {
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.feature-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: #f4f4f5;
    color: #09090b;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

:global([data-theme="dark"]) .feature-icon {
    background-color: #27272a;
    color: #ffffff;
}

.feature-card-title {
    font-size: 16px;
    font-weight: 800;
    color: #09090b;
    margin: 0 0 12px;
}

:global([data-theme="dark"]) .feature-card-title {
    color: #ffffff;
}

.feature-card-desc {
    font-size: 13px;
    line-height: 1.6;
    color: #71717a;
    margin: 0;
}

:global([data-theme="dark"]) .feature-card-desc {
    color: #a1a1aa;
}

/* Animations */
.dd-enter-active,
.dd-leave-active {
    transition:
        opacity 0.15s ease,
        transform 0.15s ease;
}
.dd-enter-from,
.dd-leave-to {
    opacity: 0;
    transform: translateY(-8px) scale(0.95);
}

/* Media Queries */
@media (max-width: 1024px) {
    .nav-links,
    .nav-right {
        display: none;
    }
    .btn-menu-mobile {
        display: block;
    }
    .logos-grid {
        grid-template-cols: repeat(4, 1fr);
    }
}

@media (max-width: 768px) {
    .hero-actions {
        flex-direction: column;
        gap: 12px;
    }
    .btn-hero-primary,
    .btn-hero-secondary {
        width: 100%;
    }
    .features-grid {
        grid-template-cols: 1fr;
    }
    .logos-grid {
        grid-template-cols: repeat(2, 1fr);
    }
    .carousel-section {
        margin-top: -20px;
    }
}

/* Mobile Dropdown Panel */
.mobile-menu-panel {
    position: absolute;
    top: 64px;
    left: 0;
    right: 0;
    background-color: #ffffff;
    border-bottom: 1px solid #e4e4e7;
    padding: 24px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
}

:global([data-theme="dark"]) .mobile-menu-panel {
    background-color: #09090b;
    border-bottom-color: #27272a;
}

.mobile-links {
    list-style: none;
    margin: 0 0 20px;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.m-link {
    font-size: 16px;
    font-weight: 700;
    color: #09090b;
    text-decoration: none;
}

:global([data-theme="dark"]) .m-link {
    color: #ffffff;
}

.mobile-auth {
    border-top: 1px solid #e4e4e7;
    padding-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

:global([data-theme="dark"]) .mobile-auth {
    border-top-color: #27272a;
}

.btn-mobile-login {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 40px;
    border: 1px solid #d4d4d8;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    color: #09090b;
}

:global([data-theme="dark"]) .btn-mobile-login {
    border-color: #27272a;
    color: #ffffff;
}

.btn-mobile-register {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 40px;
    background-color: #09090b;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    color: #ffffff !important;
}

:global([data-theme="dark"]) .btn-mobile-register {
    background-color: #ffffff;
    color: #09090b !important;
}

.mobile-user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.mobile-username {
    font-weight: 700;
    font-size: 14px;
}

.mobile-role {
    font-size: 12px;
    color: #71717a;
    margin-top: 2px;
}

.mobile-menu-btn {
    display: flex;
    align-items: center;
    height: 36px;
    font-size: 14px;
    color: #27272a;
    text-decoration: none;
    background: none;
    border: none;
    text-align: left;
    cursor: pointer;
}

:global([data-theme="dark"]) .mobile-menu-btn {
    color: #d4d4d8;
}

.btn-mobile-logout {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 40px;
    background-color: #fef2f2;
    color: #ef4444;
    border-radius: 8px;
    border: none;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
}

:global([data-theme="dark"]) .btn-mobile-logout {
    background-color: rgba(239, 68, 68, 0.1);
}

.text-bold {
    font-weight: 750;
}

/* Animations for Mobile Menu */
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.25s ease-out;
}
.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>

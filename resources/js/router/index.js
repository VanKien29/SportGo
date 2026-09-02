import { createRouter, createWebHistory } from "vue-router";
import {
    clearAuth,
    consumeGoogleCallback,
    getAuth,
    restoreAdminAuth,
    restoreAuth,
} from "../stores/auth.js";
import { applyThemeModeForPath } from "../utils/themeMode.js";
import { adminNavigationSections } from "../config/adminNavigation.js";
import { staffNavigationSections } from "../config/staffNavigation.js";
import {
    canAccessAdminRoute,
    canAccessStaffRoute,
    firstAccessibleAdminRoute,
    firstAccessibleStaffRoute,
} from "../config/permissionAccess.js";

import Home from "../views/Home.vue";
import About from "../views/About.vue";
import Contact from "../views/Contact.vue";
import Policies from "../views/Policies.vue";
import Login from "../views/Login.vue";
import Register from "../views/Register.vue";
import VerifyEmail from "../views/VerifyEmail.vue";
import ForgotPassword from "../views/ForgotPassword.vue";
import AdminLogin from "../views/admin/AdminLogin.vue";
import AdminForgotPassword from "../views/admin/AdminForgotPassword.vue";
import AdminLayout from "../views/admin/AdminLayout.vue";
import OwnerLayout from "../views/owner/OwnerLayout.vue";
import StaffPOSLayout from "../views/staff/StaffPOSLayout.vue";
import ClientAccountLayout from "../views/clients/ClientAccountLayout.vue";

const routes = [
    { path: "/", name: "home", component: Home },
    { path: "/about", name: "about", component: About, meta: { title: "Về SportGo" } },
    { path: "/contact", name: "contact", component: Contact, meta: { title: "Liên Hệ - SportGo" } },
    { path: "/policies", name: "policies", component: Policies, meta: { title: "Chính Sách & Điều Khoản - SportGo" } },
    { path: "/messages", name: "client-messages", component: () => import("../views/clients/ClientChat.vue"), meta: { requiresAuth: true, title: "Hộp Thư Tin Nhắn - SportGo" } },
    { path: "/venues", name: "venues", component: () => import("../views/clients/VenueList.vue") },
    { path: "/venues/map", name: "client-venues-map", component: () => import("../views/clients/ClientMapView.vue"), meta: { title: "Bản Đồ Cụm Sân Thể Thao - SportGo" } },
    { path: "/map", name: "client-map", component: () => import("../views/clients/ClientMapView.vue"), meta: { title: "Bản Đồ Cụm Sân Thể Thao - SportGo" } },
    { path: "/featured", name: "client-featured", component: () => import("../views/clients/FeaturedVenues.vue") },
    { path: "/offers", name: "client-offers", component: () => import("../views/clients/Offers.vue") },
    { path: "/venues/:id", name: "venue-detail", component: () => import("../views/clients/VenueDetail.vue") },
    { path: "/community/:slug", name: "community-post-detail", component: () => import("../views/clients/community/CommunityDetail.vue") },
    { path: "/login", name: "login", component: Login },
    { path: "/register", name: "register", component: Register },
    { path: "/verify-email", name: "verify-email", component: VerifyEmail },
    {
        path: "/forgot-password",
        name: "forgot-password",
        component: ForgotPassword,
    },
    {
        path: "/auth/google/callback",
        name: "google-callback",
        component: Login,
    },
    {
        path: "/account",
        component: ClientAccountLayout,
        meta: { requiresAuth: true },
        children: [
            {
                path: "",
                redirect: { name: "profile" },
            },
            {
                path: "/profile",
                name: "profile",
                component: () => import("../views/clients/ClientProfile.vue"),
                meta: { requiresAuth: true, title: "Hồ sơ cá nhân - SportGo" },
            },
            {
                path: "/bookings",
                name: "booking-history",
                component: () => import("../views/clients/booking/BookingHistory.vue"),
                meta: { requiresAuth: true, title: "Lịch đặt sân - SportGo" },
            },
            {
                path: "/wallet",
                name: "client-wallet",
                component: () => import("../views/clients/Wallet.vue"),
                meta: { requiresAuth: true, title: "Ví SportGo" },
            },
            {
                path: "/refunds",
                name: "client-refunds",
                component: () => import("../views/clients/Refunds.vue"),
                meta: { requiresAuth: true, title: "Hoàn tiền - SportGo" },
            },
            {
                path: "/notifications",
                name: "client-notifications",
                component: () => import("../views/clients/Notifications.vue"),
                meta: { requiresAuth: true, title: "Thông báo - SportGo" },
            },
            {
                path: "/complaints",
                name: "client-complaints",
                component: () => import("../views/clients/Complaints.vue"),
                meta: { requiresAuth: true, title: "Khiếu nại - SportGo" },
            },
            {
                path: "/complaints/new",
                name: "client-complaint-create",
                component: () => import("../views/clients/ComplaintCreate.vue"),
                meta: { requiresAuth: true, title: "Tạo khiếu nại - SportGo" },
            },
            {
                path: "/complaints/:id",
                name: "client-complaint-detail",
                component: () => import("../views/clients/ComplaintDetail.vue"),
                meta: { requiresAuth: true, title: "Chi tiết khiếu nại - SportGo" },
            },
            {
                path: "/vip-membership",
                name: "vip-membership",
                component: () => import("../views/clients/VipMembership.vue"),
                meta: { requiresAuth: true, title: "Gói VIP - SportGo" },
            },
        ],
    },
    {
        path: "/news",
        name: "ClientNewsList",
        component: () => import("../views/clients/news/NewsList.vue"),
        meta: { requiresAuth: false, title: "Tin tức" },
    },
    {
        path: "/community",
        name: "ClientCommunityList",
        component: () => import("../views/clients/community/CommunityList.vue"),
        meta: { requiresAuth: false, title: "Cộng đồng" },
    },
    {
        path: '/user/:id',
        name: 'user.profile',
        component: () => import('../views/clients/users/UserProfile.vue')
    },
    {
        path: "/news/:slug",
        name: "ClientNewsDetail",
        component: () => import("../views/clients/news/NewsDetail.vue"),
        meta: { requiresAuth: false, title: "Chi tiết tin tức" },
    },
    {
        path: "/matchmaking-posts/:id/manage",
        name: "ClientMatchmakingManage",
        component: () => import("../views/clients/community/MatchmakingManage.vue"),
        meta: { requiresAuth: true, title: "Quản lý bài giao lưu" },
    },
    {
        path: "/matchmaking-requests",
        name: "ClientMatchmakingRequests",
        component: () => import("../views/clients/community/MatchmakingRequests.vue"),
        meta: { requiresAuth: true, title: "Đơn tham gia giao lưu" },
    },
    {
        path: "/matchmaking-requests/:id",
        name: "ClientMatchmakingRequestDetail",
        component: () => import("../views/clients/community/MatchmakingRequestDetail.vue"),
        meta: { requiresAuth: true, title: "Chi tiết đơn giao lưu" },
    },
    {
        path: "/chat",
        name: "chat",
        component: () => import("../views/clients/ClientChat.vue"),
        meta: { requiresAuth: true, title: "Hộp Thư Tin Nhắn - SportGo" },
    },
    {
        path: "/partner-application/:id/documents/:documentId",
        name: "partner-application-document",
        component: () => import('../views/partner/PartnerApplicationDocumentPage.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: "/partner-application/:id",
        name: "partner-application-detail",
        component: () => import('../views/partner/PartnerApplicationDetail.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: "/partner-application",
        name: "partner-application",
        component: () => import('../views/partner/PartnerApplicationPortal.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: "/booking",
        name: "booking-create",
        component: () => import("../views/clients/booking/BookingForm.vue"),
        meta: { requiresAuth: false },
    },
    {
        path: "/booking/:id",
        name: "booking-detail",
        component: () => import("../views/clients/booking/BookingDetail.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/bookings/recurring/:groupCode",
        name: "booking-recurring-group",
        component: () => import("../views/clients/booking/RecurringGroupDetail.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/refunds/:id",
        name: "client-refund-detail",
        component: () => import("../views/clients/RefundDetail.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/become-partner",
        name: "partner-registration",
        component: () => import("../views/partner/PartnerApplicationPortal.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/admin/login",
        name: "admin-login",
        component: AdminLogin,
        meta: { guestAdmin: true },
    },
    {
        path: "/admin/forgot-password",
        name: "admin-forgot-password",
        component: AdminForgotPassword,
        meta: { guestAdmin: true },
    },
    {
        path: "/admin",
        component: AdminLayout,
        meta: { requiresAuth: true, role: "admin" },
        children: [
            {
                path: "dashboard",
                name: "admin-dashboard",
                component: () => import("../views/admin/AdminDashboard.vue"),
            },
            { path: "profile", name: "admin-profile", component: () => import("../views/admin/AdminProfile.vue") },
            { path: "users", name: "admin-users", component: () => import("../views/admin/AdminUsers.vue") },
            { path: "staffs", name: "admin-staffs", component: () => import("../views/admin/AdminStaffs.vue") },
            {
                path: "users/:id",
                name: "admin-user-detail",
                redirect: (to) => ({
                    name: "admin-users",
                    query: { detail: to.params.id },
                }),
            },
            {
                path: "staffs/:id",
                name: "admin-staff-detail",
                redirect: (to) => ({
                    name: "admin-staffs",
                    query: { detail: to.params.id },
                }),
            },
            { path: "vouchers", name: "admin-vouchers", component: () => import("../views/admin/AdminVouchers.vue") },
            { path: "vouchers/:id", name: "admin-voucher-detail", component: () => import("../views/admin/AdminVoucherDetail.vue"), meta: { hideFloatingBack: true } },
            {
                path: "membership-packages",
                name: "admin-membership-packages",
                component: () => import("../views/admin/AdminMembershipPackages.vue"),
            },
            {
                path: "payments",
                name: "admin-payments",
                component: () => import("../views/admin/AdminPayments.vue"),
            },
            {
                path: "finance-operations",
                name: "admin-finance-operations",
                component: () => import("../views/admin/AdminFinanceOperations.vue"),
            },
            {
                path: "partner-applications",
                name: "admin-partner-applications",
                component: () => import("../views/admin/AdminPartnerApplications.vue"),
            },
            {
                path: "partner-applications/:id",
                name: "admin-partner-application-detail",
                component: () => import("../views/admin/AdminPartnerApplicationDetail.vue"),
                meta: { hideFloatingBack: true },
            },
            {
                path: "partner-applications/:id/documents/:documentId",
                name: "admin-partner-application-document",
                component: () => import("../views/admin/AdminPartnerDocumentPage.vue"),
                meta: { hideFloatingBack: true },
            },
            {
                path: "partners/:id",
                name: "admin-partner-detail",
                component: () => import("../views/admin/AdminPartnerApplicationDetail.vue"),
                meta: { hideFloatingBack: true },
            },
            {
                path: "banners",
                name: "admin-banners",
                component: () => import("../views/admin/AdminBanners.vue"),
            },
            {
                path: "moderation",
                name: "admin-moderation",
                component: () => import("../views/admin/AdminModeration.vue"),
            },
            {
                path: "system-posts",
                name: "admin-system-posts",
                component: () => import("../views/admin/AdminSystemPosts.vue"),
            },
            { path: "policies", name: "admin-policies", component: () => import("../views/admin/AdminPolicies.vue") },
            {
                path: "platform-fee-policies",
                name: "admin-platform-fee-policies",
                component: () => import("../views/admin/AdminPolicies.vue"),
            },
            { path: "policies/:id", name: "admin-policy-detail", component: () => import("../views/admin/AdminPolicyDetail.vue"), meta: { hideFloatingBack: true } },
            {
                path: "reports-complaints",
                name: "admin-reports-complaints",
                component: () => import("../views/admin/AdminReportsAndComplaints.vue"),
            },
            {
                path: "reports",
                redirect: { name: "admin-reports-complaints", query: { tab: "reports" } }
            },
            {
                path: "complaints",
                redirect: { name: "admin-reports-complaints", query: { tab: "complaints" } }
            },
            { path: "roles", name: "admin-roles", component: () => import("../views/admin/AdminRoles.vue") },
            { path: "roles/:id", name: "admin-role-detail", component: () => import("../views/admin/AdminRoleDetail.vue"), meta: { hideFloatingBack: true } },

            {
                path: "court-types",
                name: "admin-court-types",
                component: () =>
                    import("../views/admin/AdminCourtTypes.vue"),
            },
            {
                path: "amenities",
                name: "admin-amenities",
                component: () =>
                    import("../views/admin/AdminAmenities.vue"),
            },
            {
                path: "service-categories",
                name: "admin-service-categories",
                component: () =>
                    import("../views/admin/AdminServiceCategories.vue"),
            },
            {
                path: "venue-clusters",
                name: "admin-venue-clusters",
                component: () =>
                    import("../views/admin/AdminVenueClusters.vue"),
            },
            {
                path: "venue-clusters/:id",
                name: "admin-venue-cluster-detail",
                component: () =>
                    import("../views/admin/AdminVenueClusterDetail.vue"),
            },
            {
                path: "platform-fee-tiers",
                name: "admin-platform-fee-tiers",
                component: () =>
                    import("../views/admin/AdminPlatformFeeTiers.vue"),
            },
            {
                path: "platform-fee-tiers/:id",
                name: "admin-platform-fee-plan-detail",
                component: () =>
                    import("../views/admin/AdminPlatformFeePlanDetail.vue"),
                meta: { hideFloatingBack: true },
            },
            {
                path: "platform-fee-ledgers",
                name: "admin-platform-fee-ledgers",
                component: () =>
                    import("../views/admin/AdminPlatformFeeLedgers.vue"),
            },
            {
                path: "platform-fee-arrangements",
                name: "admin-platform-fee-arrangements",
                component: () =>
                    import("../views/admin/AdminPlatformFeeArrangements.vue"),
            },
            {
                path: "platform-fee-promotions",
                name: "admin-platform-fee-promotions",
                component: () =>
                    import("../views/admin/AdminPlatformFeePromotions.vue"),
            },
            {
                path: "platform-fee-ledgers/:id",
                name: "admin-platform-fee-ledger-detail",
                component: () =>
                    import("../views/admin/AdminPlatformFeeLedgerDetail.vue"),
                meta: { hideFloatingBack: true },
            },
            {
                path: "venues/:id/platform-fees",
                name: "admin-venue-platform-fees",
                component: () =>
                    import("../views/admin/AdminVenuePlatformFees.vue"),
                meta: { hideFloatingBack: true },
            },
            {
                path: "settings/platform-fee",
                name: "admin-platform-fee-settings",
                redirect: { name: "admin-platform-fee-policies" },
            },
            {
                path: "system-profile",
                redirect: { name: "admin-settings" },
            },
            {
                path: "settings",
                name: "admin-settings",
                component: () =>
                    import("../views/admin/AdminSettings.vue"),
            },
            {
                path: "posts/:id",
                name: "admin-post-detail",
                component: () =>
                    import("../views/admin/AdminPostDetail.vue"),
                meta: { hideFloatingBack: true },
            },
            {
                path: "chat",
                name: "admin-chat",
                component: () => import("../views/Chat.vue"),
            },
            { path: "", redirect: { name: "admin-venue-clusters" } },
        ],
    },
    {
        path: "/owner",
        component: OwnerLayout,
        meta: { requiresAuth: true, role: "owner" },
        children: [
            {
                path: "dashboard",
                name: "owner-dashboard",
                component: () => import("../views/owner/OwnerDashboard.vue"),
            },
            {
                path: "venue-clusters",
                name: "owner-venue-clusters",
                component: () =>
                    import("../views/owner/OwnerVenueClusters.vue"),
            },
            {
                path: "venue-clusters/:id/termination",
                name: "owner-partner-termination",
                component: () =>
                    import("../views/owner/OwnerPartnerTermination.vue"),
                meta: { hideFloatingBack: true },
            },
            {
                path: "termination-requests/:id",
                name: "owner-partner-termination-request",
                component: () =>
                    import("../views/owner/OwnerPartnerTermination.vue"),
                meta: { hideFloatingBack: true },
            },
            {
                path: "affiliate",
                name: "owner-affiliate",
                component: () =>
                    import("../views/owner/OwnerAffiliate.vue"),
            },
            {
                path: "services",
                name: "owner-services",
                component: () =>
                    import("../views/owner/OwnerServices.vue"),
            },
            {
                path: "venue-courts",
                name: "owner-venue-courts",
                component: () =>
                    import("../views/owner/OwnerVenueCourts.vue"),
            },
            {
                path: "bookings",
                name: "owner-bookings",
                redirect: { name: "owner-booking-list" },
            },
            {
                path: "counter-booking",
                name: "owner-counter-booking",
                component: () => import("../views/owner/OwnerCounterBooking.vue"),
            },
            {
                path: "booking-list",
                name: "owner-booking-list",
                component: () => import("../views/owner/OwnerCounterBooking.vue"),
            },
            { path: "pricing", name: "owner-pricing", component: () => import("../views/owner/OwnerPricing.vue") },
            {
                path: "booking-settings",
                name: "owner-booking-settings",
                component: () => import("../views/owner/OwnerBookingSettings.vue"),
            },
            {
                path: "settings",
                name: "owner-settings",
                component: () => import("../views/owner/OwnerSettings.vue"),
            },
            {
                path: "platform-fees",
                name: "owner-platform-fees",
                component: () => import("../views/owner/OwnerPlatformFees.vue"),
            },
            {
                path: "schedule-locks",
                name: "owner-schedule-locks",
                component: () => import("../views/owner/OwnerScheduleLocks.vue"),
            },
            {
                path: "venue-posts",
                name: "owner-venue-posts",
                component: () => import("../views/owner/OwnerVenuePosts.vue"),
            },
            { path: "staff", name: "owner-staff", component: () => import("../views/owner/OwnerStaff.vue") },
            {
                path: "staff-shifts",
                name: "owner-staff-shifts",
                component: () => import("../views/owner/OwnerStaffShifts.vue"),
            },
            { path: "vouchers", name: "owner-vouchers", component: () => import("../views/owner/OwnerVouchers.vue") },
            { path: "wallet", redirect: { name: "owner-finance" } },
            { path: "policies", name: "owner-policies", component: () => import("../views/owner/OwnerPolicies.vue") },
            {
                path: "matchmaking",
                name: "owner-matchmaking",
                component: () => import("../views/owner/OwnerMatchmaking.vue"),
            },
            {
                path: "complaints",
                name: "owner-complaints",
                component: () => import("../views/owner/OwnerComplaints.vue"),
            },
            {
                path: "complaints/:id",
                name: "owner-complaint-detail",
                component: () => import("../views/owner/OwnerComplaintDetail.vue"),
            },
            { path: "profile", name: "owner-profile", component: () => import("../views/owner/OwnerProfile.vue") },
            {
                path: "partner-profile",
                name: "owner-partner-profile",
                component: () => import("../views/owner/OwnerPartnerProfile.vue"),
            },
            {
                path: "chat",
                name: "owner-chat",
                component: () => import("../views/owner/OwnerChat.vue"),
            },
            {
                path: "partner-documents/:id/:documentId",
                name: "owner-partner-document",
                component: () => import('../views/partner/PartnerApplicationDocumentPage.vue'),
                meta: { ownerDocument: true },
            },
            {
                path: "finance",
                name: "owner-finance",
                component: () => import("../views/owner/OwnerFinance.vue"),
            },
            {
                path: "refunds",
                name: "owner-refunds",
                component: () => import("../views/owner/OwnerRefundRequests.vue"),
            },
            { path: "", redirect: { name: "owner-venue-clusters" } },
        ],
    },
    {
        path: "/staff",
        component: StaffPOSLayout,
        meta: { requiresAuth: true, role: "staff" },
        children: [
            {
                path: "dashboard",
                redirect: { name: "staff-bookings" },
            },
            {
                path: "schedules",
                name: "staff-schedules",
                component: () => import("../views/staff/StaffSchedules.vue"),
            },
            {
                path: "bookings",
                name: "staff-bookings",
                component: () => import("../views/staff/StaffBookings.vue"),
            },
            {
                path: "counter-booking",
                name: "staff-counter-booking",
                component: () => import("../views/staff/StaffCounterBooking.vue"),
            },
            {
                path: "vouchers",
                name: "staff-vouchers",
                component: () => import("../views/owner/OwnerVouchers.vue"),
            },
            {
                path: "settings",
                name: "staff-settings",
                component: () => import("../views/owner/OwnerSettings.vue"),
            },
            {
                path: "chat",
                name: "staff-chat",
                component: () => import("../views/Chat.vue"),
            },
            { path: "profile", name: "staff-profile", component: () => import("../views/owner/OwnerProfile.vue") },
            { path: "", redirect: { name: "staff-bookings" } },
        ],
    },
    { path: "/:pathMatch(.*)*", redirect: "/" },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to) {
        if (to.hash) return { el: to.hash, behavior: "smooth" };
        return { top: 0, left: 0 };
    },
});

const chunkReloadStorageKey = "sportgo:chunk-reload";
const chunkReloadWindowMs = 15_000;

function isChunkLoadError(error) {
    const message = String(error?.message || error || "").toLowerCase();

    return message.includes("failed to fetch dynamically imported module")
        || message.includes("importing a module script failed")
        || message.includes("chunkloaderror")
        || message.includes("loading chunk")
        || message.includes("unable to preload css");
}

function recoverFromChunkError(error, to) {
    if (!isChunkLoadError(error) || typeof window === "undefined") return;

    const marker = `${window.location.pathname}:${to?.fullPath || ""}`;
    let shouldReload = true;

    try {
        const previous = JSON.parse(sessionStorage.getItem(chunkReloadStorageKey) || "null");
        if (previous?.marker === marker && Date.now() - Number(previous.at || 0) < chunkReloadWindowMs) {
            shouldReload = false;
            sessionStorage.removeItem(chunkReloadStorageKey);
        } else {
            sessionStorage.setItem(chunkReloadStorageKey, JSON.stringify({ marker, at: Date.now() }));
        }
    } catch {
        // If storage is unavailable, the current navigation can still show the
        // friendly fallback from the caller below.
    }

    if (!shouldReload) {
        console.error("[SportGo] Lazy-loaded asset is still unavailable after retry", error);
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set("__asset_reload", String(Date.now()));
    window.location.replace(url.toString());
}

// Do not leave the internal cache-busting marker in copied/shared URLs.
if (typeof window !== "undefined" && window.location.search.includes("__asset_reload")) {
    const cleanUrl = new URL(window.location.href);
    cleanUrl.searchParams.delete("__asset_reload");
    window.history.replaceState({}, document.title, cleanUrl.toString());
}

if (typeof window !== "undefined" && "scrollRestoration" in window.history) {
    window.history.scrollRestoration = "manual";
}

function authContextForRoute(route) {
    if (!route?.matched?.some((record) => record.meta.requiresAuth)) {
        return null;
    }

    return route.matched.find((record) => record.meta.role)?.meta.role || "user";
}

function entersNewAuthContext(to, from) {
    const targetContext = authContextForRoute(to);
    if (!targetContext) return false;

    const sourceContext = authContextForRoute(from);
    return sourceContext !== targetContext;
}

router.beforeEach(async (to, from, next) => {
    applyThemeModeForPath(to.path);

    if (to.name === "google-callback") {
        const query = { ...to.query };
        if (query.token || query.code) {
            window.history.replaceState({}, document.title, to.path);
        }

        try {
            const auth = await consumeGoogleCallback(query);
            if (!auth) return next({ name: "login", replace: true });
            return next({ path: auth.redirect_to || "/", replace: true });
        } catch {
            clearAuth();
            return next({ name: "login", replace: true });
        }
    }

    let auth = getAuth();

    if (to.meta.guestAdmin) {
        if (auth?.role_group === "admin") {
            const serverAuth = await restoreAdminAuth();
            if (serverAuth?.role_group === "admin")
                return next(firstAccessibleAdminRoute(serverAuth, adminNavigationSections));
        }
        return next();
    }

    if (to.matched.some((route) => route.meta.requiresAuth)) {
        const requiredRole = to.matched.find((route) => route.meta.role)?.meta
            .role;

        if (!auth) {
            return next(
                requiredRole === "admin"
                    ? { name: "admin-login" }
                    : { name: "login", query: { redirect: to.fullPath } },
            );
        }

        // Validate when entering an authenticated area or switching roles. Internal
        // navigation inside the same client/owner shell keeps the local auth state
        // and does not wait for another /me request on every menu click.
        if (entersNewAuthContext(to, from)) {
            auth = requiredRole === "admin"
                ? await restoreAdminAuth()
                : await restoreAuth();
        }

        if (!auth) {
            return next(
                requiredRole === "admin"
                    ? { name: "admin-login" }
                    : { name: "login", query: { redirect: to.fullPath } },
            );
        }

        if (requiredRole && auth.role_group !== requiredRole) {
            if (auth.role_group === "admin")
                return next(firstAccessibleAdminRoute(auth, adminNavigationSections));
            if (auth.role_group === "owner")
                return next({ name: "owner-dashboard" });
            if (auth.role_group === "staff") {
                const clusterId = localStorage.getItem("selected_cluster")
                    || Object.keys(auth.venue_staff_permissions || {})[0]
                    || "";
                return next(firstAccessibleStaffRoute(auth, clusterId, staffNavigationSections));
            }
            if (requiredRole === "admin") return next({ name: "admin-login" });
            return next({ name: "home" });
        }

        if (requiredRole === "admin" && !canAccessAdminRoute(to.name, auth)) {
            return next(firstAccessibleAdminRoute(auth, adminNavigationSections));
        }

        if (requiredRole === "staff") {
            const clusterId = localStorage.getItem("selected_cluster")
                || Object.keys(auth.venue_staff_permissions || {})[0]
                || "";
            if (clusterId && !localStorage.getItem("selected_cluster")) {
                localStorage.setItem("selected_cluster", clusterId);
            }
            if (!canAccessStaffRoute(to.name, auth, clusterId)) {
                return next(firstAccessibleStaffRoute(auth, clusterId, staffNavigationSections));
            }
        }
    }

    if (["login", "register", "verify-email"].includes(to.name) && auth) {
        auth = await restoreAuth();
        if (!auth) return next();
        if (auth.role_group === "admin")
            return next(firstAccessibleAdminRoute(auth, adminNavigationSections));
        if (auth.role_group === "owner")
            return next({ name: "owner-dashboard" });
        if (auth.role_group === "staff") {
            const clusterId = localStorage.getItem("selected_cluster")
                || Object.keys(auth.venue_staff_permissions || {})[0]
                || "";
            return next(firstAccessibleStaffRoute(auth, clusterId, staffNavigationSections));
        }
        return next({ name: "home" });
    }

    return next();
});

// Dynamic On-Page SEO Navigation Guard
router.afterEach((to) => {
    const routeTitles = {
        home: "SportGo – Nền tảng Đặt lịch & Quản lý Sân Thể Thao Trực Tuyến",
        venues: "Tìm Sân Thể Thao Đạt Chuẩn - Pickleball, Cầu Lông, Bóng Đá | SportGo",
        "venue-detail": "Chi Tiết Cụm Sân & Sơ Đồ Giờ Trống Realtime | SportGo",
        "booking-create": "Đặt Lịch Giữ Chỗ Sân Thể Thao Trực Tuyến | SportGo",
        "booking-history": "Lịch Sử Đặt Sân & Vé QR Code Check-in | SportGo",
        ClientNewsList: "Tin Tức & Kinh Nghiệm Thể Thao | SportGo",
        ClientCommunityList: "Cộng Đồng Thể Thao - Tìm Đối Thủ & Ghép Đội | SportGo",
        "partner-application": "Đăng Ký Đối Tác Chủ Sân Thể Thao | SportGo",
        login: "Đăng Nhập Tài Khoản | SportGo",
        register: "Đăng Ký Tài Khoản Mới | SportGo",
        "verify-email": "Xác Thực Email | SportGo",
    };

    const title = to.meta?.title ? `${to.meta.title} | SportGo` : (routeTitles[to.name] || "SportGo - Đặt Sân Thể Thao Trực Tuyến");
    document.title = title;

    // Update Meta Description
    let metaDesc = document.querySelector('meta[name="description"]');
    if (!metaDesc) {
        metaDesc = document.createElement('meta');
        metaDesc.name = "description";
        document.head.appendChild(metaDesc);
    }
    metaDesc.content = `${title}. Tìm sân gần bạn, xem ma trận giờ trống và giữ chỗ dễ dàng cùng SportGo.`;
});

router.onError((error, to) => {
    console.error('[SportGo] Router navigation error:', error, to?.fullPath);
    recoverFromChunkError(error, to);
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('sportgo:router-error', {
            detail: { error, to: to?.fullPath || null },
        }));
    }
});

export default router;

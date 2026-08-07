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
const Login = () => import("../views/Login.vue");
const Register = () => import("../views/Register.vue");
const ForgotPassword = () => import("../views/ForgotPassword.vue");
const ClientProfile = () => import("../views/clients/ClientProfile.vue");
const AdminLogin = () => import("../views/admin/AdminLogin.vue");
const AdminForgotPassword = () => import("../views/admin/AdminForgotPassword.vue");
const AdminLayout = () => import("../views/admin/AdminLayout.vue");
const AdminProfile = () => import("../views/admin/AdminProfile.vue");
const AdminUsers = () => import("../views/admin/AdminUsers.vue");
const AdminStaffs = () => import("../views/admin/AdminStaffs.vue");
const AdminUserDetail = () => import("../views/admin/AdminUserDetail.vue");
const AdminStaffDetail = () => import("../views/admin/AdminStaffDetail.vue");
const AdminVouchers = () => import("../views/admin/AdminVouchers.vue");
const AdminVoucherDetail = () => import("../views/admin/AdminVoucherDetail.vue");
const AdminPolicies = () => import("../views/admin/AdminPolicies.vue");
const AdminPolicyDetail = () => import("../views/admin/AdminPolicyDetail.vue");
const AdminRoles = () => import("../views/admin/AdminRoles.vue");
const AdminRoleDetail = () => import("../views/admin/AdminRoleDetail.vue");
const OwnerLayout = () => import("../views/owner/OwnerLayout.vue");
const OwnerPricing = () => import("../views/owner/OwnerPricing.vue");
const OwnerStaff = () => import("../views/owner/OwnerStaff.vue");
const OwnerVouchers = () => import("../views/owner/OwnerVouchers.vue");
const OwnerPolicies = () => import("../views/owner/OwnerPolicies.vue");
const StaffLayout = () => import("../views/staff/StaffLayout.vue");
const BookingForm = () => import("../views/clients/booking/BookingForm.vue");
const BookingDetail = () => import("../views/clients/booking/BookingDetail.vue");
const BookingHistory = () => import("../views/clients/booking/BookingHistory.vue");
const ClientWallet = () => import("../views/clients/Wallet.vue");
const PartnerApplicationPortal = () => import("../views/partner/PartnerApplicationPortal.vue");
const PartnerApplicationDetail = () => import("../views/partner/PartnerApplicationDetail.vue");
const PartnerApplicationDocumentPage = () => import("../views/partner/PartnerApplicationDocumentPage.vue");
const UserProfile = () => import('../views/clients/users/UserProfile.vue');
const VenueList = () => import("../views/clients/VenueList.vue");
const VenueDetail = () => import("../views/clients/VenueDetail.vue");
const FavoriteVenues = () => import("../views/clients/FavoriteVenues.vue");
const ClientReports = () => import("../views/clients/Reports.vue");
const ClientReportDetail = () => import("../views/clients/ReportDetail.vue");
const CommunityPostDetail = () => import("../views/clients/community/CommunityDetail.vue");

const routes = [
    { path: "/", name: "home", component: Home },
    { path: "/venues", name: "venues", component: VenueList },
    { path: "/featured", name: "client-featured", component: () => import("../views/clients/FeaturedVenues.vue") },
    { path: "/offers", name: "client-offers", component: () => import("../views/clients/Offers.vue") },
    { path: "/venues/:id", name: "venue-detail", component: VenueDetail },
    {
        path: "/community/matchmaking",
        name: "client-matchmaking",
        component: () => import("../views/clients/community/MatchmakingHub.vue"),
        meta: { requiresAuth: false, title: "Tuyển giao lưu" },
    },
    { path: "/community/:slug", name: "community-post-detail", component: CommunityPostDetail },
    { path: "/login", name: "login", component: Login },
    { path: "/register", name: "register", component: Register },
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
        path: "/profile",
        name: "profile",
        component: ClientProfile,
        meta: { requiresAuth: true },
    },
    {
        path: "/news",
        name: "ClientNewsList",
        component: () => import("@/views/clients/news/NewsList.vue"),
        meta: { requiresAuth: false, title: "Tin tức" },
    },
    {
        path: "/community",
        name: "ClientCommunityList",
        component: () => import("@/views/clients/community/CommunityList.vue"),
        meta: { requiresAuth: false, title: "Cộng đồng" },
    },
    {
        path: "/map",
        name: "client-map",
        component: () => import("@/views/clients/VenueMap.vue"),
        meta: { requiresAuth: false, title: "Bản đồ sân" },
    },
    {
        path: "/favorites/venues",
        name: "client-favorite-venues",
        component: FavoriteVenues,
        meta: { requiresAuth: true, title: "Sân yêu thích" },
    },
    { path: "/matchmaking", redirect: { name: "client-matchmaking" } },
    {
        path: '/user/:id',
        name: 'user.profile',
        component: UserProfile
    },
    {
        path: "/news/:slug",
        name: "ClientNewsDetail",
        component: () => import("@/views/clients/news/NewsDetail.vue"),
        meta: { requiresAuth: false, title: "Chi tiết tin tức" },
    },
    {
        path: "/matchmaking-posts/:id/manage",
        name: "ClientMatchmakingManage",
        component: () => import("@/views/clients/community/MatchmakingManage.vue"),
        meta: { requiresAuth: true, title: "Quản lý bài giao lưu" },
    },
    {
        path: "/chat",
        name: "chat",
        component: () => import("../views/clients/ClientChat.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/partner-application/:id/documents/:documentId",
        name: "partner-application-document",
        component: PartnerApplicationDocumentPage,
        meta: { requiresAuth: true },
    },
    {
        path: "/partner-application/:id",
        name: "partner-application-detail",
        component: PartnerApplicationDetail,
        meta: { requiresAuth: true },
    },
    {
        path: "/partner-application",
        name: "partner-application",
        component: PartnerApplicationPortal,
        meta: { requiresAuth: true },
    },
    {
        path: "/booking",
        name: "booking-create",
        component: BookingForm,
        meta: { requiresAuth: true },
    },
    {
        path: "/booking/:id",
        name: "booking-detail",
        component: BookingDetail,
        meta: { requiresAuth: true },
    },
    {
        path: "/bookings",
        name: "booking-history",
        component: BookingHistory,
        meta: { requiresAuth: true },
    },
    {
        path: "/bookings/recurring/:groupCode",
        name: "booking-recurring-group",
        component: () => import("../views/clients/booking/RecurringGroupDetail.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/wallet",
        name: "client-wallet",
        component: ClientWallet,
        meta: { requiresAuth: true },
    },
    {
        path: "/refunds",
        name: "client-refunds",
        component: () => import("../views/clients/Refunds.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/refunds/:id",
        name: "client-refund-detail",
        component: () => import("../views/clients/RefundDetail.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/notifications",
        name: "client-notifications",
        component: () => import("../views/clients/Notifications.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/complaints",
        name: "client-complaints",
        component: () => import("../views/clients/Complaints.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/complaints/new",
        name: "client-complaint-create",
        component: () => import("../views/clients/ComplaintCreate.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/complaints/:id",
        name: "client-complaint-detail",
        component: () => import("../views/clients/ComplaintDetail.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/reports",
        name: "client-reports",
        component: ClientReports,
        meta: { requiresAuth: true, title: "Báo cáo của tôi" },
    },
    {
        path: "/reports/:id",
        name: "client-report-detail",
        component: ClientReportDetail,
        meta: { requiresAuth: true, title: "Chi tiết báo cáo" },
    },
    {
        path: "/become-partner",
        name: "partner-registration",
        redirect: { name: "partner-application" },
        meta: { requiresAuth: true },
    },
    {
        path: "/vip-membership",
        name: "vip-membership",
        component: () => import("../views/clients/VipMembership.vue"),
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
                redirect: { name: "admin-venue-clusters" },
            },
            { path: "profile", name: "admin-profile", component: AdminProfile },
            { path: "users", name: "admin-users", component: AdminUsers },
            { path: "staffs", name: "admin-staffs", component: AdminStaffs },
            { path: "users/:id", name: "admin-user-detail", component: AdminUserDetail, meta: { hideFloatingBack: true } },
            { path: "staffs/:id", name: "admin-staff-detail", component: AdminStaffDetail },
            { path: "vouchers", name: "admin-vouchers", component: AdminVouchers },
            { path: "vouchers/:id", name: "admin-voucher-detail", component: AdminVoucherDetail, meta: { hideFloatingBack: true } },
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
            { path: "policies", name: "admin-policies", component: AdminPolicies },
            {
                path: "platform-fee-policies",
                name: "admin-platform-fee-policies",
                component: AdminPolicies,
            },
            { path: "policies/:id", name: "admin-policy-detail", component: AdminPolicyDetail, meta: { hideFloatingBack: true } },
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
            { path: "roles", name: "admin-roles", component: AdminRoles },
            { path: "roles/:id", name: "admin-role-detail", component: AdminRoleDetail, meta: { hideFloatingBack: true } },

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
                path: "platform-fee-ledgers",
                name: "admin-platform-fee-ledgers",
                component: () =>
                    import("../views/admin/AdminPlatformFeeLedgers.vue"),
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
                component: () =>
                    import("../views/admin/AdminPlatformFeeSettings.vue"),
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
                redirect: { name: "owner-venue-clusters" },
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
            { path: "pricing", name: "owner-pricing", component: OwnerPricing },
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
            { path: "staff", name: "owner-staff", component: OwnerStaff },
            {
                path: "staff-shifts",
                name: "owner-staff-shifts",
                component: () => import("../views/owner/OwnerStaffShifts.vue"),
            },
            { path: "vouchers", name: "owner-vouchers", component: OwnerVouchers },
            { path: "wallet", redirect: { name: "owner-finance" } },
            { path: "policies", name: "owner-policies", component: OwnerPolicies },
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
                component: PartnerApplicationDocumentPage,
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
        component: StaffLayout,
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
                component: OwnerVouchers,
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

if (typeof window !== "undefined" && "requestIdleCallback" in window) {
    window.requestIdleCallback(() => {
        VenueList();
        VenueDetail();
        BookingForm();
    });
}

if (typeof window !== "undefined" && "scrollRestoration" in window.history) {
    window.history.scrollRestoration = "manual";
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

        auth = requiredRole === "admin"
            ? await restoreAdminAuth()
            : await restoreAuth();

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

    if (["login", "register"].includes(to.name) && auth) {
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

export default router;

import { createRouter, createWebHistory } from "vue-router";
import {
    consumeGoogleCallback,
    getAuth,
    restoreAdminAuth,
    restoreAuth,
} from "../stores/auth.js";

import Home from "../views/Home.vue";
import Login from "../views/Login.vue";
import Register from "../views/Register.vue";
import ForgotPassword from "../views/ForgotPassword.vue";
import Profile from "../views/Profile.vue";
import AdminLogin from "../views/admin/AdminLogin.vue";
import AdminForgotPassword from "../views/admin/AdminForgotPassword.vue";
import AdminLayout from "../views/admin/AdminLayout.vue";
import AdminDashboard from "../views/admin/AdminDashboard.vue";
import AdminProfile from "../views/admin/AdminProfile.vue";
import AdminUsers from "../views/admin/AdminUsers.vue";
import AdminStaffs from "../views/admin/AdminStaffs.vue";
import AdminUserDetail from "../views/admin/AdminUserDetail.vue";
import AdminStaffDetail from "../views/admin/AdminStaffDetail.vue";
import AdminVouchers from "../views/admin/AdminVouchers.vue";
import AdminVoucherDetail from "../views/admin/AdminVoucherDetail.vue";
import AdminPolicies from "../views/admin/AdminPolicies.vue";
import AdminPolicyDetail from "../views/admin/AdminPolicyDetail.vue";
import AdminRoles from "../views/admin/AdminRoles.vue";
import AdminRoleDetail from "../views/admin/AdminRoleDetail.vue";
import OwnerLayout from "../views/owner/OwnerLayout.vue";
import OwnerDashboard from "../views/owner/OwnerDashboard.vue";
import OwnerPricing from "../views/owner/OwnerPricing.vue";
import OwnerStaff from "../views/owner/OwnerStaff.vue";
import OwnerVouchers from "../views/owner/OwnerVouchers.vue";
import OwnerPolicies from "../views/owner/OwnerPolicies.vue";
import BookingForm from "../views/clients/booking/BookingForm.vue";
import BookingDetail from "../views/clients/booking/BookingDetail.vue";
import PartnerRegistration from "../views/clients/PartnerRegistration.vue";
import VenueList from "../views/clients/VenueList.vue";
import VenueDetail from "../views/clients/VenueDetail.vue";

const routes = [
    { path: "/", name: "home", component: Home },
    { path: "/venues", name: "venues", component: VenueList },
    { path: "/venues/:id", name: "venue-detail", component: VenueDetail },
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
        component: Profile,
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
        path: "/become-partner",
        name: "partner-registration",
        component: PartnerRegistration,
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
                component: AdminDashboard,
            },
            { path: "profile", name: "admin-profile", component: AdminProfile },
            { path: "users", name: "admin-users", component: AdminUsers },
            { path: "staffs", name: "admin-staffs", component: AdminStaffs },
            { path: "users/:id", name: "admin-user-detail", component: AdminUserDetail, meta: { hideFloatingBack: true } },
            { path: "staffs/:id", name: "admin-staff-detail", component: AdminStaffDetail },
            { path: "vouchers", name: "admin-vouchers", component: AdminVouchers },
            { path: "vouchers/:id", name: "admin-voucher-detail", component: AdminVoucherDetail, meta: { hideFloatingBack: true } },
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
                path: "partners/:id",
                name: "admin-partner-detail",
                component: () => import("../views/admin/AdminPartnerDetail.vue"),
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
            { path: "policies", name: "admin-policies", component: AdminPolicies },
            {
                path: "platform-fee-policies",
                name: "admin-platform-fee-policies",
                component: AdminPolicies,
            },
            { path: "policies/:id", name: "admin-policy-detail", component: AdminPolicyDetail, meta: { hideFloatingBack: true } },
            {
                path: "reports",
                redirect: { name: "admin-moderation", query: { tab: "reports" } }
            },
            {
                path: "complaints",
                redirect: { name: "admin-moderation", query: { tab: "complaints" } }
            },
            { path: "roles", name: "admin-roles", component: AdminRoles },
            { path: "roles/:id", name: "admin-role-detail", component: AdminRoleDetail, meta: { hideFloatingBack: true } },
            {
                path: "venue-posts",
                name: "admin-venue-posts",
                component: () => import("../views/admin/AdminVenuePosts.vue"),
            },
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
                path: "posts/:id",
                name: "admin-post-detail",
                component: () =>
                    import("../views/admin/AdminPostDetail.vue"),
                meta: { hideFloatingBack: true },
            },
            { path: "", redirect: { name: "admin-dashboard" } },
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
                component: OwnerDashboard,
            },
            {
                path: "venue-clusters",
                name: "owner-venue-clusters",
                component: () =>
                    import("../views/owner/OwnerVenueClusters.vue"),
            },
            {
                path: "affiliate",
                name: "owner-affiliate",
                component: () =>
                    import("../views/owner/OwnerAffiliate.vue"),
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
                redirect: { name: "owner-counter-booking" },
            },
            {
                path: "counter-booking",
                name: "owner-counter-booking",
                component: () => import("../views/owner/OwnerCounterBooking.vue"),
            },
            { path: "pricing", name: "owner-pricing", component: OwnerPricing },
            {
                path: "booking-settings",
                name: "owner-booking-settings",
                component: () => import("../views/owner/OwnerBookingSettings.vue"),
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
            { path: "vouchers", name: "owner-vouchers", component: OwnerVouchers },
            { path: "wallet", redirect: { name: "owner-finance" } },
            { path: "policies", name: "owner-policies", component: OwnerPolicies },
            {
                path: "matchmaking",
                name: "owner-matchmaking",
                component: () => import("../views/owner/OwnerMatchmaking.vue"),
            },
            { path: "profile", name: "owner-profile", component: Profile },
            {
                path: "partner-profile",
                name: "owner-partner-profile",
                component: () => import("../views/owner/OwnerPartnerProfile.vue"),
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
            { path: "", redirect: { name: "owner-dashboard" } },
        ],
    },
    { path: "/:pathMatch(.*)*", redirect: "/" },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    if (to.name === "google-callback") {
        const auth = await consumeGoogleCallback(to.query);
        if (!auth) return next({ name: "login" });
        return next(auth.redirect_to || "/");
    }

    let auth = getAuth();
    if (auth?.token) {
        const isAdminRoute =
            to.matched.some((route) => route.meta.role === "admin") ||
            to.meta.guestAdmin;
        auth = isAdminRoute ? await restoreAdminAuth() : await restoreAuth();
    }

    if (to.meta.guestAdmin) {
        if (auth?.role_group === "admin")
            return next({ name: "admin-dashboard" });
        return next();
    }

    if (to.name === "profile" && auth?.role_group === "owner") {
        return next({ name: "owner-profile" });
    }

    if (to.matched.some((route) => route.meta.requiresAuth)) {
        const requiredRole = to.matched.find((route) => route.meta.role)?.meta
            .role;

        if (!auth) {
            return next(
                requiredRole === "admin"
                    ? { name: "admin-login" }
                    : { name: "login" },
            );
        }

        if (requiredRole && auth.role_group !== requiredRole) {
            if (auth.role_group === "admin")
                return next({ name: "admin-dashboard" });
            if (auth.role_group === "owner")
                return next({ name: "owner-dashboard" });
            if (requiredRole === "admin") return next({ name: "admin-login" });
            return next({ name: "home" });
        }
    }

    if (["login", "register"].includes(to.name) && auth) {
        if (auth.role_group === "admin")
            return next({ name: "admin-dashboard" });
        if (auth.role_group === "owner")
            return next({ name: "owner-dashboard" });
        return next({ name: "home" });
    }

    return next();
});

export default router;

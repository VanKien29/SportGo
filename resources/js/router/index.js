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
import AdminPolicies from "../views/admin/AdminPolicies.vue";
import AdminPolicyDetail from "../views/admin/AdminPolicyDetail.vue";
import AdminRoles from "../views/admin/AdminRoles.vue";
import AdminRoleDetail from "../views/admin/AdminRoleDetail.vue";
import OwnerLayout from "../views/owner/OwnerLayout.vue";
import OwnerDashboard from "../views/owner/OwnerDashboard.vue";
import OwnerPricing from "../views/owner/OwnerPricing.vue";
import BookingForm from "../views/clients/booking/BookingForm.vue";
import BookingDetail from "../views/clients/booking/BookingDetail.vue";

const routes = [
    { path: "/", name: "home", component: Home },
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
        path: "/owner/profile",
        name: "owner-profile",
        component: Profile,
        meta: { requiresAuth: true, role: "owner" },
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
                path: "banners",
                name: "admin-banners",
                component: () => import("../views/admin/AdminBanners.vue"),
            },
            { path: "policies", name: "admin-policies", component: AdminPolicies },
            { path: "policies/:id", name: "admin-policy-detail", component: AdminPolicyDetail },
            {
                path: "reports",
                name: "admin-reports",
                component: () => import("../views/admin/AdminReports.vue"),
            },
            {
                path: "complaints",
                name: "admin-complaints",
                component: () => import("../views/admin/AdminComplaints.vue"),
            },
            { path: "roles", name: "admin-roles", component: AdminRoles },
            { path: "roles/:id", name: "admin-role-detail", component: AdminRoleDetail },
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
            },
            {
                path: "venues/:id/platform-fees",
                name: "admin-venue-platform-fees",
                component: () =>
                    import("../views/admin/AdminVenuePlatformFees.vue"),
            },
            {
                path: "settings/platform-fee",
                name: "admin-platform-fee-settings",
                component: () =>
                    import("../views/admin/AdminPlatformFeeSettings.vue"),
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
                path: "venue-courts",
                name: "owner-venue-courts",
                component: () => import("../views/owner/OwnerVenueCourts.vue"),
            },
            { path: "pricing", name: "owner-pricing", component: OwnerPricing },
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

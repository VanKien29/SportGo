import { createRouter, createWebHistory } from 'vue-router';
import { consumeGoogleCallback, getAuth, restoreAuth } from '../stores/auth.js';

import Home from '../views/Home.vue';
import Login from '../views/Login.vue';
import Register from '../views/Register.vue';
import ForgotPassword from '../views/ForgotPassword.vue';
import Profile from '../views/Profile.vue';
import AdminLayout from '../views/admin/AdminLayout.vue';
import AdminDashboard from '../views/admin/AdminDashboard.vue';
import AdminUsers from '../views/admin/AdminUsers.vue';
import OwnerLayout from '../views/owner/OwnerLayout.vue';
import OwnerDashboard from '../views/owner/OwnerDashboard.vue';

const routes = [
  { path: '/', name: 'home', component: Home },
  { path: '/login', name: 'login', component: Login },
  { path: '/register', name: 'register', component: Register },
  { path: '/forgot-password', name: 'forgot-password', component: ForgotPassword },
  { path: '/auth/google/callback', name: 'google-callback', component: Login },
  { path: '/profile', name: 'profile', component: Profile, meta: { requiresAuth: true } },
  { path: '/admin/profile', name: 'admin-profile', component: Profile, meta: { requiresAuth: true, role: 'admin' } },
  { path: '/owner/profile', name: 'owner-profile', component: Profile, meta: { requiresAuth: true, role: 'owner' } },
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true, role: 'admin' },
    children: [
      { path: 'dashboard', name: 'admin-dashboard', component: AdminDashboard },
      { path: 'users', name: 'admin-users', component: AdminUsers },
      { path: '', redirect: { name: 'admin-dashboard' } },
    ],
  },
  {
    path: '/owner',
    component: OwnerLayout,
    meta: { requiresAuth: true, role: 'owner' },
    children: [
      { path: 'dashboard', name: 'owner-dashboard', component: OwnerDashboard },
      { path: '', redirect: { name: 'owner-dashboard' } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, from, next) => {
  if (to.name === 'google-callback') {
    const auth = await consumeGoogleCallback(to.query);
    if (!auth) return next({ name: 'login' });
    return next(auth.redirect_to || '/');
  }

  let auth = getAuth();
  if (auth?.token) {
    auth = await restoreAuth();
  }

  if (to.matched.some((route) => route.meta.requiresAuth)) {
    if (!auth) return next({ name: 'login' });

    const requiredRole = to.matched.find((route) => route.meta.role)?.meta.role;
    if (requiredRole && auth.role_group !== requiredRole) {
      if (auth.role_group === 'admin') return next({ name: 'admin-dashboard' });
      if (auth.role_group === 'owner') return next({ name: 'owner-dashboard' });
      return next({ name: 'home' });
    }
  }

  if (['login', 'register'].includes(to.name) && auth) {
    if (auth.role_group === 'admin') return next({ name: 'admin-dashboard' });
    if (auth.role_group === 'owner') return next({ name: 'owner-dashboard' });
    return next({ name: 'home' });
  }

  return next();
});

export default router;

import { createRouter, createWebHistory } from 'vue-router';
import { consumeGoogleCallback, getAuth, getSelectedCluster, restoreAuth } from '../stores/auth.js';

import authRoutes from './authRoutes.js';
import adminRoutes from './adminRoutes.js';
import ownerRoutes from './ownerRoutes.js';

import Profile from '../views/Profile.vue';
import Home from '../views/Home.vue';

const routes = [
  {
    path: '/',
    name: 'Home',
    component: Home,
  },
  ...authRoutes,
  ...adminRoutes,
  ...ownerRoutes,
  {
    path: '/profile',
    name: 'Profile',
    component: Profile,
    meta: { requiresAuth: true },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/'
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, from, next) => {
  if (to.name === 'google-callback') {
    const auth = await consumeGoogleCallback(to.query);
    if (!auth) return next({ name: 'Login' });
    if (auth.role_group === 'owner') {
      const cluster = getSelectedCluster();
      return next(cluster ? '/owner/dashboard' : auth.redirect_to);
    }
    return next(auth.redirect_to || '/');
  }

  let auth = getAuth();
  if (auth?.token) {
    auth = await restoreAuth();
  }

  if (to.matched.some((route) => route.meta.requiresAuth)) {
    if (!auth) return next({ name: 'Login' });

    const requiredRole = to.matched.find((route) => route.meta.role)?.meta.role;
    if (requiredRole && auth.role_group !== requiredRole) {
      if (auth.role_group === 'admin') return next({ name: 'AdminDashboard' });
      if (auth.role_group === 'owner') {
        const cluster = getSelectedCluster();
        return next({ name: cluster ? 'OwnerDashboard' : 'OwnerSelectCluster' });
      }
      return next({ name: 'Home' });
    }
  }

  if ((to.name === 'Login' || to.name === 'Register') && auth) {
    if (auth.role_group === 'admin') return next({ name: 'AdminDashboard' });
    if (auth.role_group === 'owner') {
      const cluster = getSelectedCluster();
      return next({ name: cluster ? 'OwnerDashboard' : 'OwnerSelectCluster' });
    }
    return next({ name: 'Home' });
  }

  if (to.name === 'OwnerDashboard' && !getSelectedCluster()) {
    return next({ name: 'OwnerSelectCluster' });
  }

  return next();
});

export default router;

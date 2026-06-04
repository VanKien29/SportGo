import AdminLayout from '../views/admin/AdminLayout.vue';
import AdminDashboard from '../views/admin/AdminDashboard.vue';
import AdminUsers from '../views/admin/AdminUsers.vue';
import PolicyManagement from '../views/admin/PolicyManagement.vue';
import BannerManagement from '../views/admin/BannerManagement.vue';
import PartnerApplicationManagement from '../views/admin/PartnerApplicationManagement.vue';
import PermissionManagement from '../views/admin/PermissionManagement.vue';

export default [
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true, role: 'admin' },
    children: [
      { path: 'dashboard', name: 'AdminDashboard', component: AdminDashboard },
      { path: 'users', name: 'AdminUsers', component: AdminUsers },
      { path: 'banners', name: 'BannerManagement', component: BannerManagement },
      { path: 'partner-applications', name: 'PartnerApplicationManagement', component: PartnerApplicationManagement },
      { path: 'policies', name: 'PolicyManagement', component: PolicyManagement },
      { path: 'permissions', name: 'PermissionManagement', component: PermissionManagement },
    ],
  },
];

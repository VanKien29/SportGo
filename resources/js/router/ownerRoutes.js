import OwnerLayout from '../views/owner/OwnerLayout.vue';
import OwnerDashboard from '../views/owner/OwnerDashboard.vue';
import OwnerSelectCluster from '../views/owner/OwnerSelectCluster.vue';

export default [
  {
    path: '/owner',
    component: OwnerLayout,
    meta: { requiresAuth: true, role: 'owner' },
    children: [
      { path: 'select-cluster', name: 'OwnerSelectCluster', component: OwnerSelectCluster },
      { path: 'dashboard', name: 'OwnerDashboard', component: OwnerDashboard },
    ],
  },
];

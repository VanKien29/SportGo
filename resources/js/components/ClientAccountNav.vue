<template>
  <aside class="an-sidebar" aria-label="Điều hướng tài khoản">
    <div class="an-sidebar-header">
      <span class="an-sidebar-title">QUẢN LÝ TÀI KHOẢN</span>
    </div>
    <nav class="an-sidebar-menu">
      <router-link
        v-for="item in items"
        :key="item.key"
        :to="item.to"
        class="an-link"
        :class="{ active: isActive(item.key) }"
        :aria-current="isActive(item.key) ? 'page' : undefined"
      >
        <span>{{ item.label }}</span>
      </router-link>
    </nav>
  </aside>
</template>

<script setup>
import { useRoute } from 'vue-router';

const route = useRoute();
const items = [
  {
    key: 'profile',
    label: 'Tài khoản',
    to: { name: 'profile' },
  },
  {
    key: 'bookings',
    label: 'Lịch đặt sân',
    to: { name: 'booking-history' },
  },
  {
    key: 'refunds',
    label: 'Hoàn tiền',
    to: { name: 'client-refunds' },
  },
  {
    key: 'wallet',
    label: 'Ví SportGo',
    to: { name: 'client-wallet' },
  },
  {
    key: 'vip',
    label: 'Gói VIP',
    to: { name: 'vip-membership' },
  },
  {
    key: 'matchmaking',
    label: 'Giao lưu của tôi',
    to: { name: 'client-matchmaking-management' },
  },
  {
    key: 'notifications',
    label: 'Thông báo',
    to: { name: 'client-notifications' },
  },
  {
    key: 'complaints',
    label: 'Khiếu nại',
    to: { name: 'client-complaints' },
  },
  {
    key: 'policies',
    label: 'Chính sách',
    to: { name: 'policies' },
  },
];

function isActive(key) {
  if (key === 'bookings') return route.name === 'booking-history';
  if (key === 'refunds') return route.name === 'client-refunds' || route.name === 'client-refund-detail';
  if (key === 'wallet') return route.name === 'client-wallet';
  if (key === 'vip') return route.name === 'vip-membership';
  if (key === 'matchmaking') {
    return [
      'client-matchmaking-management',
      'ClientMatchmakingRequests',
      'ClientMatchmakingRequestDetail',
      'ClientMatchmakingManage',
    ].includes(route.name);
  }
  if (key === 'notifications') return route.name === 'client-notifications';
  if (key === 'complaints') return route.name === 'client-complaints' || route.name === 'client-complaint-detail' || route.name === 'client-complaint-create';
  if (key === 'policies') return route.name === 'policies';
  if (route.name !== 'profile') return false;
  if (key === 'refunds') return route.query.tab === 'refunds';
  return key === 'profile' && route.query.tab !== 'refunds';
}
</script>

<style scoped>
.an-sidebar {
  width: 220px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: #ffffff;
  padding-right: 16px;
  border-right: 1px solid #f1f5f9;
}

.an-sidebar-header {
  padding: 4px 10px;
}

.an-sidebar-title {
  font-size: 11px;
  color: #475569;
  letter-spacing: 0.06em;
  font-weight: 400 !important;
}

.an-sidebar-menu {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.an-link {
  display: flex;
  align-items: center;
  padding: 9px 14px;
  font-size: 14px;
  font-weight: 400 !important;
  color: #334155;
  text-decoration: none;
  border-radius: 6px;
  transition: all 0.15s ease;
  border-left: 3px solid transparent;
}

.an-link:hover {
  color: #0f172a;
  background: #f8fafc;
}

.an-link.active {
  color: #0f172a;
  background: #f1f5f9;
  border-left-color: #0f172a;
  font-weight: 400 !important;
}

@media (max-width: 768px) {
  .an-sidebar {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid #f1f5f9;
    padding-right: 0;
    padding-bottom: 16px;
  }

  .an-sidebar-menu {
    flex-direction: row;
    overflow-x: auto;
  }

  .an-link {
    border-left: none;
    border-bottom: 2px solid transparent;
    white-space: nowrap;
  }

  .an-link.active {
    border-left: none;
    border-bottom-color: #0f172a;
  }
}
</style>

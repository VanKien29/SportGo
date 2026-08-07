<template>
  <nav class="an-nav" aria-label="Điều hướng tài khoản">
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
    key: 'notifications',
    label: 'Thông báo',
    to: { name: 'client-notifications' },
  },
  {
    key: 'complaints',
    label: 'Khiếu nại',
    to: { name: 'client-complaints' },
  },
];

function isActive(key) {
  if (key === 'bookings') return route.name === 'booking-history';
  if (key === 'refunds') return route.name === 'client-refunds' || route.name === 'client-refund-detail';
  if (key === 'wallet') return route.name === 'client-wallet';
  if (key === 'notifications') return route.name === 'client-notifications';
  if (key === 'complaints') return route.name === 'client-complaints' || route.name === 'client-complaint-detail';
  if (route.name !== 'profile') return false;
  if (key === 'refunds') return route.query.tab === 'refunds';
  return key === 'profile' && route.query.tab !== 'refunds';
}
</script>

<style scoped>
.an-nav {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 24px;
  overflow-x: auto;
}

.an-link {
  display: inline-flex;
  align-items: center;
  padding: 8px 14px;
  font-size: 13.5px;
  font-weight: 500;
  color: #1e293b;
  text-decoration: none;
  border: none;
  white-space: nowrap;
  transition: all 0.15s ease;
  border-radius: 4px;
}

.an-link:hover {
  color: #0f172a;
}

.an-link.active {
  color: #15803d;
  font-weight: 500;
}
</style>

<template>
  <nav class="client-account-nav sg-client-card" aria-label="Trung tâm tài khoản">
    <router-link
      v-for="item in items"
      :key="item.key"
      :to="item.to"
      :class="{ active: isActive(item.key) }"
      :aria-current="isActive(item.key) ? 'page' : undefined"
    >
      <span class="account-nav-icon" aria-hidden="true">
        <AppIcon :name="item.icon" :size="18" />
      </span>
      <span>
        <strong>{{ item.label }}</strong>
        <small>{{ item.description }}</small>
      </span>
    </router-link>
  </nav>
</template>

<script setup>
import { useRoute } from 'vue-router';
import AppIcon from './AppIcon.vue';

const route = useRoute();
const items = [
  {
    key: 'profile',
    label: 'Tài khoản',
    description: 'Hồ sơ & thành viên',
    icon: 'users',
    to: { name: 'profile' },
  },
  {
    key: 'bookings',
    label: 'Lịch đặt',
    description: 'Theo dõi booking',
    icon: 'calendar',
    to: { name: 'booking-history' },
  },
  {
    key: 'refunds',
    label: 'Số dư hoàn tiền',
    description: 'Số dư & biến động',
    icon: 'finance',
    to: { name: 'profile', query: { tab: 'refunds' } },
  },
];

function isActive(key) {
  if (key === 'bookings') return route.name === 'booking-history';
  if (route.name !== 'profile') return false;
  if (key === 'refunds') return route.query.tab === 'refunds';
  return key === 'profile' && route.query.tab !== 'refunds';
}
</script>



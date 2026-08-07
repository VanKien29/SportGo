<template>
  <nav class="sg3-account-nav" aria-label="Điều hướng tài khoản">
    <router-link
      v-for="item in items"
      :key="item.key"
      :to="item.to"
      :class="{ active: isActive(item.key) }"
      :aria-current="isActive(item.key) ? 'page' : undefined"
    >
      <span aria-hidden="true">
        <AppIcon :name="item.icon" :size="18" />
      </span>
      <span>
        <strong>{{ item.label }}</strong>
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
    label: 'Hoàn tiền',
    description: 'Theo dõi yêu cầu hoàn',
    icon: 'refresh',
    to: { name: 'client-refunds' },
  },
  {
    key: 'wallet',
    label: 'Ví SportGo',
    description: 'Số dư & biến động',
    icon: 'wallet',
    to: { name: 'client-wallet' },
  },
  {
    key: 'notifications',
    label: 'Thông báo',
    description: 'Cập nhật tài khoản',
    icon: 'bell',
    to: { name: 'client-notifications' },
  },
  {
    key: 'complaints',
    label: 'Khiếu nại',
    description: 'Trao đổi hỗ trợ',
    icon: 'messageSquare',
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


<template>
  <span v-if="showBadges" class="client-author-badges" aria-label="Nhãn tài khoản">
    <span v-if="badges?.is_venue_owner" class="client-author-badge client-author-badge--owner">
      <AppIcon name="shieldCheck" :size="12" />
      Chủ cụm sân
    </span>
    <span
      v-if="badges?.vip"
      class="client-author-badge client-author-badge--vip"
      :title="badges.vip.label || 'VIP SportGo'"
      :aria-label="badges.vip.label || 'VIP SportGo'"
    >
      <AppIcon name="star" :size="12" />
      VIP
    </span>
  </span>
</template>

<script setup>
import { computed } from 'vue';
import AppIcon from '@/components/AppIcon.vue';

const props = defineProps({
  badges: {
    type: Object,
    default: () => ({}),
  },
});

const showBadges = computed(() => Boolean(props.badges?.is_venue_owner || props.badges?.vip));
</script>

<style scoped>
.client-author-badges {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 5px;
  margin-left: 0;
  vertical-align: middle;
}

.client-author-badge {
  display: inline-flex;
  min-height: 22px;
  align-items: center;
  gap: 4px;
  border: 1px solid color-mix(in srgb, var(--sg-client-primary) 25%, var(--sg-client-border));
  border-radius: var(--sg-client-radius-pill, 999px);
  padding: 2px 7px;
  background: var(--sg-client-primary-soft);
  color: var(--sg-client-primary-dark);
  font-size: 12px;
  font-weight: 800;
  line-height: 1;
  white-space: nowrap;
}

.client-author-badge--vip {
  border-color: color-mix(in srgb, #d39a1e 34%, var(--sg-client-border));
  background: #fff8dc;
  color: #8a5a00;
}

</style>

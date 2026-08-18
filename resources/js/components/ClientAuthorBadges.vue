<template>
  <span v-if="showBadges" class="client-author-badges" aria-label="Nhãn tài khoản">
    <span v-if="badges?.is_venue_owner" class="client-author-badge client-author-badge--owner">
      <AppIcon name="shieldCheck" :size="12" />
      Chủ cụm sân
    </span>
    <span
      v-if="badges?.vip"
      :class="[
        'client-author-badge',
        'client-author-badge--vip',
        'client-author-badge--vip-' + (badges.vip.type || 'default')
      ]"
      :title="badges.vip.label || 'VIP SportGo'"
      :aria-label="badges.vip.label || 'VIP SportGo'"
    >
      <AppIcon :name="badges.vip.icon || 'star'" :size="12" />
      VIP
    </span>
    <span
      v-if="badges?.venue_membership"
      :class="[
        'client-author-badge',
        'client-author-badge--membership',
        'client-author-badge--membership-' + (badges.venue_membership.tier_key || 'standard')
      ]"
      :title="membershipTitle"
      :aria-label="membershipTitle"
    >
      <AppIcon :name="badges.venue_membership.icon || 'shieldCheck'" :size="12" />
      {{ badges.venue_membership.label || 'Hội viên sân' }}
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

const showBadges = computed(() => Boolean(
  props.badges?.is_venue_owner
  || props.badges?.vip
  || props.badges?.venue_membership
));
const membershipTitle = computed(() => {
  const membership = props.badges?.venue_membership;
  if (!membership) return '';
  const venue = membership.venue_name ? ' tại ' + membership.venue_name : '';
  const discount = Number(membership.discount_percent || 0);

  return (membership.label || 'Hội viên sân')
    + venue
    + (discount > 0 ? ' · ' + discount + '% ưu đãi' : '');
});
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
  font-weight: 400;
  line-height: 1;
  white-space: nowrap;
}

.client-author-badge--vip {
  border-color: color-mix(in srgb, #d39a1e 34%, var(--sg-client-border));
  background: #fff8dc;
  color: #8a5a00;
}

.client-author-badge--vip-pro {
  border-color: #b9cdf0;
  background: #edf4ff;
  color: #245b9a;
}

.client-author-badge--vip-saving {
  border-color: #b9e2c4;
  background: #effaf1;
  color: #16703a;
}

.client-author-badge--membership {
  border-color: #c8d9ce;
  background: #f1f8f3;
  color: #386047;
}

.client-author-badge--membership-silver {
  border-color: #cbd5e1;
  background: #f3f6f9;
  color: #526273;
}

.client-author-badge--membership-gold {
  border-color: #efd89b;
  background: #fff9e9;
  color: #966b0a;
}

.client-author-badge--membership-diamond {
  border-color: #c5d3e9;
  background: #eef5ff;
  color: #315f9b;
}

</style>

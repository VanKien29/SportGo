<template>
  <main class="fee-detail-page">
    <PlatformFeeSubnav />
    <PlatformFeePlanManager :plan-id="$route.params.id" />
  </main>
</template>

<script>
import PlatformFeeSubnav from '../../components/PlatformFeeSubnav.vue';
import PlatformFeePlanManager from '../../components/admin/PlatformFeePlanManager.vue';

export default {
  name: 'AdminPlatformFeePlanDetail',
  components: { PlatformFeePlanManager, PlatformFeeSubnav },
};
</script>

<style scoped>
.fee-detail-page { display: grid; gap: 18px; padding: 10px; }
@media (max-width: 560px) { .fee-detail-page { padding: 4px; } }
</style>

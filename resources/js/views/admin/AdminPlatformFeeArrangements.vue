<template>
  <main class="arrangements-shell">
    <PlatformFeeSubnav />
    <PlatformFeeArrangementPanel />
  </main>
</template>

<script>
import PlatformFeeSubnav from '../../components/PlatformFeeSubnav.vue';
import PlatformFeeArrangementPanel from '../../components/admin/PlatformFeeArrangementPanel.vue';

export default {
  name: 'AdminPlatformFeeArrangements', components: { PlatformFeeArrangementPanel, PlatformFeeSubnav },
};
</script>

<style scoped>
.arrangements-shell { display: grid; gap: 18px; padding: 10px; }
@media (max-width: 560px) { .arrangements-shell { padding: 4px; } }
</style>

<template>
  <div class="pricing-header-hero-wrapper">
    <section class="cluster-hero-surface">
      <!-- Integrated AppTabs Bar -->
      <div v-if="tabs && tabs.length > 0" class="hero-integrated-tabs">
        <AppTabs
          :tabs="tabs"
          :model-value="activeTab"
          @update:model-value="$emit('tab-change', $event)"
        />
      </div>
    </section>
  </div>
</template>

<script>
import AppTabs from '../../common/AppTabs.vue';

export default {
  name: 'PricingHeaderHero',
  components: { AppTabs },
  props: {
    tabs: { type: Array, default: () => [] },
    activeTab: { type: String, default: 'weekly' },
  },
  emits: ['tab-change'],
};
</script>

<style scoped>
.pricing-header-hero-wrapper {
  display: flex;
  flex-direction: column;
}

.cluster-hero-surface {
  background: var(--admin-surface, #ffffff);
  border-radius: 0;
  display: flex;
  flex-direction: column;
}

.hero-integrated-tabs {
  width: 100%;
}
</style>

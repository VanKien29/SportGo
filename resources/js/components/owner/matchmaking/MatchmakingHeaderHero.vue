<template>
  <div class="matchmaking-header-hero-wrapper">
    <section class="cluster-hero-surface">
      <!-- Top Action & Navigation Row -->
      <div class="matchmaking-hero-row">
        <!-- Integrated AppTabs Bar -->
        <div class="hero-integrated-tabs">
          <AppTabs
            :tabs="tabs"
            :model-value="activeTab"
            @update:model-value="$emit('tab-change', $event)"
          />
        </div>

        <div class="matchmaking-hero-actions">
          <button
            type="button"
            class="btn-primary-add"
            @click="$emit('open-create-modal')"
          >
            + Tạo bài giao lưu
          </button>
        </div>
      </div>
    </section>
  </div>
</template>

<script>
import AppTabs from '../../common/AppTabs.vue';

export default {
  name: 'MatchmakingHeaderHero',
  components: { AppTabs },
  props: {
    tabs: { type: Array, default: () => [] },
    activeTab: { type: String, default: 'all' },
  },
  emits: ['open-create-modal', 'tab-change'],
};
</script>

<style scoped>
.matchmaking-header-hero-wrapper {
  display: flex;
  flex-direction: column;
}

.cluster-hero-surface {
  background: var(--admin-surface, #ffffff);
  border-radius: 0;
  display: flex;
  flex-direction: column;
}

.matchmaking-hero-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.hero-integrated-tabs {
  flex: 1;
}

.matchmaking-hero-actions {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.btn-primary-add {
  height: 38px;
  padding: 0 16px;
  border-radius: 8px;
  border: none;
  background: var(--admin-primary, #22a653);
  color: var(--admin-primary-text, #ffffff);
  font-size: 13px;
  font-weight: 400;
  display: flex;
  align-items: center;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.btn-primary-add:hover:not(:disabled) {
  background: var(--admin-primary-dark, #15733a);
}

@media (max-width: 768px) {
  .matchmaking-hero-row {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>

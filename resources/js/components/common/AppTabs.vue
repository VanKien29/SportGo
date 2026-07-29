<template>
  <div class="sg-app-tabs-wrapper">
    <div class="sg-app-tabs" role="tablist">
      <button
        v-for="tab in tabs"
        :key="getTabKey(tab)"
        type="button"
        role="tab"
        class="sg-tab-btn"
        :class="{ active: isTabActive(tab) }"
        :aria-selected="isTabActive(tab)"
        @click="selectTab(getTabKey(tab))"
      >
        <AppIcon v-if="tab.icon" :name="tab.icon" size="16" />
        <span>{{ tab.label }}</span>
        <span v-if="getTabBadge(tab) !== undefined && getTabBadge(tab) !== null && getTabBadge(tab) !== ''" class="sg-tab-badge">
          {{ getTabBadge(tab) }}
        </span>
      </button>
    </div>
  </div>
</template>

<script>
import AppIcon from '../AppIcon.vue';

export default {
  name: 'AppTabs',
  components: { AppIcon },
  props: {
    tabs: {
      type: Array,
      required: true,
      default: () => [],
    },
    modelValue: {
      type: [String, Number],
      default: '',
    },
  },
  emits: ['update:modelValue', 'change'],
  methods: {
    getTabKey(tab) {
      if (!tab) return '';
      return tab.key !== undefined ? tab.key : (tab.value !== undefined ? tab.value : tab.id);
    },
    getTabBadge(tab) {
      if (!tab) return null;
      return tab.badge !== undefined ? tab.badge : tab.count;
    },
    isTabActive(tab) {
      const k = this.getTabKey(tab);
      return String(this.modelValue ?? '') === String(k ?? '');
    },
    selectTab(key) {
      if (String(key ?? '') !== String(this.modelValue ?? '')) {
        this.$emit('update:modelValue', key);
        this.$emit('change', key);
      }
    },
  },
};
</script>

<style scoped>
.sg-app-tabs-wrapper {
  display: flex;
  align-items: center;
}

.sg-app-tabs {
  display: flex;
  align-items: center;
  gap: var(--sg-space-2xs, 4px);
  overflow-x: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.sg-app-tabs::-webkit-scrollbar {
  display: none;
}

.sg-tab-btn {
  height: 38px;
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--sg-space-xs, 8px);
  padding: 0 var(--sg-space-md, 16px);
  border-radius: var(--admin-radius, 8px);
  border: 1px solid var(--admin-border, #cbd5e1);
  background: var(--admin-surface, #ffffff);
  color: var(--admin-text, #475569);
  font-size: 13px;
  font-weight: 400;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.18s ease;
  user-select: none;
}

.sg-tab-btn:hover:not(.active) {
  background: var(--admin-hover, #f1f5f9);
  color: var(--admin-text, #0f172a);
}

.sg-tab-btn.active {
  background: var(--admin-accent, #10b981);
  color: #ffffff;
  border-color: var(--admin-accent, #10b981);
  font-weight: 400;
}

.sg-tab-badge { 
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 400;
  background: var(--admin-primary-soft, rgba(255, 255, 255, 0.2));
  color: inherit;
}
</style>

<template>
  <div class="sg-app-tabs-wrapper">
    <div class="sg-app-tabs" role="tablist">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        role="tab"
        class="sg-tab-btn"
        :class="{ active: modelValue === tab.key }"
        :aria-selected="modelValue === tab.key"
        @click="selectTab(tab.key)"
      >
        <AppIcon v-if="tab.icon" :name="tab.icon" size="16" />
        <span>{{ tab.label }}</span>
        <span v-if="tab.badge !== undefined && tab.badge !== null" class="sg-tab-badge">
          {{ tab.badge }}
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
      type: String,
      default: '',
    },
  },
  emits: ['update:modelValue', 'change'],
  methods: {
    selectTab(key) {
      if (key !== this.modelValue) {
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
  margin-bottom: var(--sg-space-sm, 12px);
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
  font-weight: 600;
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
  background: var(--admin-primary, #18181b);
  color: var(--admin-primary-text, #ffffff);
  border-color: var(--admin-primary, #18181b);
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
  font-weight: 700;
  background: var(--admin-primary-soft, rgba(255, 255, 255, 0.2));
  color: inherit;
}
</style>

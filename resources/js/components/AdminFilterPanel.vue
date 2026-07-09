<template>
  <div class="admin-filter-panel" :class="panelClass">
    <div class="filter-controls">
      <slot></slot>
    </div>
    <div v-if="showRefresh" class="filter-actions">
      <button class="btn-refresh" type="button" @click="$emit('refresh')" title="Làm mới">
        <AppIcon name="refresh" size="18" />
        <span>Làm mới</span>
      </button>
    </div>
  </div>
</template>

<script>
import AppIcon from './AppIcon.vue';

export default {
  name: 'AdminFilterPanel',
  components: { AppIcon },
  props: {
    panelClass: {
      type: String,
      default: '',
    },
    showRefresh: {
      type: Boolean,
      default: false,
    },
  },
};
</script>

<style scoped>
.admin-filter-panel {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  background: var(--admin-surface, #ffffff);
  border: 1px solid var(--admin-border, #e4e4e7);
  border-radius: var(--admin-radius, 8px);
  padding: 16px;
  margin-bottom: 24px;
}

.filter-controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.filter-actions {
  display: flex;
  align-items: center;
}

.btn-refresh {
  display: flex;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: 1px solid var(--admin-border, #e4e4e7);
  border-radius: var(--admin-radius, 8px);
  padding: 8px 12px;
  color: var(--admin-muted, #71717a);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-refresh.never-hover-class-placeholder {
  background: var(--admin-hover, #f4f4f5);
  color: var(--admin-primary, #18181b);
  border-color: var(--admin-primary, #18181b);
}
</style>

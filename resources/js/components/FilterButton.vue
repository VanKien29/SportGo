<template>
  <button
    class="filter-button"
    :class="{ 
      'is-active': active || count > 0, 
      'has-count': count > 0, 
      'is-floating': floating 
    }"
    :disabled="disabled"
    :type="type"
    :title="title || label"
    :aria-label="title || label"
    @click="$emit('click', $event)"
  >
    <AppIcon :name="icon" :size="iconSize" class="filter-icon" />
    <span v-if="showLabel" class="filter-label">
      <slot>{{ label }}</slot>
    </span>
    <span v-if="count > 0" class="filter-badge">{{ count }}</span>
  </button>
</template>

<script>
import AppIcon from './AppIcon.vue';

export default {
  name: 'FilterButton',
  components: { AppIcon },
  emits: ['click'],
  props: {
    /** Whether the filter button is styled as a floating action button in the corner */
    floating: {
      type: Boolean,
      default: false,
    },
    /** Whether the filter is active/panel is toggled open */
    active: {
      type: Boolean,
      default: false,
    },
    /** Number of active filters applied */
    count: {
      type: Number,
      default: 0,
    },
    /** Button text label */
    label: {
      type: String,
      default: 'Bộ lọc',
    },
    /** Whether to show the text label */
    showLabel: {
      type: Boolean,
      default: true,
    },
    /** Icon name to display */
    icon: {
      type: String,
      default: 'filter',
    },
    /** Icon size */
    iconSize: {
      type: [Number, String],
      default: 18,
    },
    /** Disabled state */
    disabled: {
      type: Boolean,
      default: false,
    },
    /** Button type attribute */
    type: {
      type: String,
      default: 'button',
    },
    /** Tooltip title */
    title: {
      type: String,
      default: '',
    },
  },
};
</script>

<style scoped>
/* Standard Button Style */
.filter-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 38px;
  padding: 8px 14px;
  border: 1px solid var(--admin-border);
  border-radius: 8px;
  background-color: var(--admin-surface);
  color: var(--admin-text);
  font: inherit;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(23, 34, 27, 0.05);
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  user-select: none;
}

.filter-button:hover:not(:disabled) {
  border-color: rgba(47, 158, 68, 0.32);
  background-color: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
  transform: translateY(-1px);
  box-shadow: 0 8px 18px rgba(47, 158, 68, 0.08);
}

.filter-button:active:not(:disabled) {
  transform: translateY(0);
}

.filter-button:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px var(--admin-primary-ring);
}

/* Active State */
.filter-button.is-active {
  border-color: var(--admin-primary);
  background-color: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
}

.filter-button.is-active:hover:not(:disabled) {
  background-color: color-mix(in srgb, var(--admin-primary-soft) 85%, var(--admin-primary));
}

/* Filter Badge */
.filter-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background-color: var(--admin-primary);
  color: #fff;
  font-size: 11px;
  font-weight: 800;
  line-height: 1;
  transition: all 0.2s ease;
}

.filter-button.is-active .filter-badge {
  background-color: var(--admin-primary-dark);
  color: #fff;
}

/* Floating Action Button Style */
.filter-button.is-floating {
  width: auto;
  min-width: 44px;
  max-width: 44px;
  height: 44px;
  border-radius: 22px;
  padding: 0;
  gap: 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  overflow: hidden;
  white-space: nowrap;
  position: relative;
}

.filter-button.is-floating:hover:not(:disabled) {
  max-width: 300px;
  padding-left: 14px;
  padding-right: 16px;
  gap: 8px;
  box-shadow: 0 6px 16px rgba(47, 158, 68, 0.25);
  background-color: var(--admin-primary-soft);
}

.filter-button.is-floating .filter-label {
  max-width: 0;
  opacity: 0;
  margin-left: 0;
  font-weight: 700;
  font-size: 13px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: inline-block;
  white-space: nowrap;
}

.filter-button.is-floating:hover:not(:disabled) .filter-label {
  max-width: 240px;
  opacity: 1;
  margin-left: 6px;
}

.filter-button.is-floating .filter-badge {
  position: absolute;
  top: -2px;
  right: -2px;
  border: 2px solid var(--admin-surface);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.filter-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
  transform: none;
  box-shadow: none;
}

.filter-label {
  white-space: nowrap;
}

.filter-button:hover:not(:disabled) .filter-icon {
  transform: rotate(5deg) scale(1.05);
}

.filter-icon {
  transition: transform 0.2s ease;
}

@media (max-width: 768px) {
  .filter-button.is-floating {
    min-width: 40px;
    max-width: 40px;
    height: 40px;
    border-radius: 20px;
    padding: 0;
    gap: 0;
  }

  .filter-button.is-floating:hover:not(:disabled) {
    max-width: 220px;
    padding-left: 12px;
    padding-right: 14px;
    gap: 8px;
  }

  .filter-button.is-floating:hover:not(:disabled) .filter-label {
    max-width: 160px;
  }
}
</style>

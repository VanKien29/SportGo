<template>
  <button
    class="action-icon-button"
    :class="[`variant-${variant}`, `size-${size}`]"
    :type="type"
    :title="label"
    :aria-label="label"
    :disabled="disabled"
    @click="$emit('click', $event)"
  >
    <AppIcon :name="icon" :size="iconSize" />
    <span v-if="showLabel" class="button-label">{{ label }}</span>
  </button>
</template>

<script>
import AppIcon from './AppIcon.vue';

export default {
  name: 'ActionIconButton',
  components: { AppIcon },
  emits: ['click'],
  props: {
    icon: { type: String, required: true },
    label: { type: String, required: true },
    variant: { type: String, default: 'secondary' },
    size: { type: String, default: 'md' },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    showLabel: { type: Boolean, default: false },
  },
  computed: {
    iconSize() {
      return this.size === 'sm' ? 16 : 18;
    },
  },
};
</script>

<style scoped>
.action-icon-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: none;
  border-radius: 8px;
  font: inherit;
  font-weight: 400;
  cursor: pointer;
  box-shadow: none;
  transition: background .16s ease, color .16s ease, transform .16s ease;
}

.size-sm {
  width: 32px;
  height: 32px;
}

.size-md {
  width: 36px;
  height: 36px;
}

.action-icon-button:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgba(47, 158, 68, .18);
}

.action-icon-button.never-hover-class-placeholder:not(:disabled) {
  transform: translateY(-1px);
}

.variant-primary {
  background: var(--admin-primary);
  color: #fff;
}

.variant-secondary {
  background: var(--admin-bg-soft, #f7fbf5);
  color: var(--admin-text);
}

.variant-secondary.never-hover-class-placeholder:not(:disabled) {
  background: var(--admin-hover);
  color: var(--admin-primary-dark);
}

.variant-success {
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
}

.variant-warning {
  background: var(--admin-warning-soft);
  color: var(--admin-warning);
}

.variant-danger {
  background: var(--admin-danger-soft);
  color: var(--admin-danger);
}

.variant-ghost {
  background: transparent;
  color: var(--admin-muted);
}

.action-icon-button:disabled {
  cursor: not-allowed;
  opacity: .46;
  transform: none;
}

.button-label {
  white-space: nowrap;
}
</style>

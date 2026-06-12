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
  border: 1px solid transparent;
  border-radius: 8px;
  font: inherit;
  font-weight: 750;
  cursor: pointer;
  box-shadow: 0 1px 2px rgba(23, 34, 27, 0.05);
  transition: background .16s ease, border-color .16s ease, color .16s ease, transform .16s ease, box-shadow .16s ease;
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

.action-icon-button:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 8px 18px rgba(47, 158, 68, 0.08);
}

.variant-primary {
  border-color: #2f9e44;
  background: #2f9e44;
  color: #fff;
}

.variant-secondary {
  background: #fff;
  border-color: #dce8dc;
  color: #344238;
}

.variant-secondary:hover:not(:disabled) {
  border-color: rgba(47, 158, 68, 0.3);
  background: #e8f7ec;
  color: #216b34;
}

.variant-success {
  border-color: rgba(47, 158, 68, .2);
  background: #e8f7ec;
  color: #216b34;
}

.variant-warning {
  border-color: rgba(217, 119, 6, .2);
  background: #fef3c7;
  color: #92400e;
}

.variant-danger {
  border-color: rgba(220, 38, 38, .18);
  background: #fef2f2;
  color: #991b1b;
}

.variant-ghost {
  border-color: #dce8dc;
  background: #fff;
  color: #4f5d52;
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

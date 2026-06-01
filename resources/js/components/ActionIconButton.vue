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
  font-weight: 800;
  cursor: pointer;
  transition: background .16s ease, border-color .16s ease, color .16s ease, transform .16s ease;
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
  box-shadow: 0 0 0 3px rgba(22, 163, 74, .18);
}

.action-icon-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.variant-primary {
  background: #16a34a;
  color: #fff;
}

.variant-secondary {
  background: #f8fafc;
  border-color: #dbe3ea;
  color: #334155;
}

.variant-secondary:hover:not(:disabled) {
  background: #eef2f7;
}

.variant-success {
  background: #dcfce7;
  color: #166534;
}

.variant-warning {
  background: #fef3c7;
  color: #92400e;
}

.variant-danger {
  background: #fee2e2;
  color: #991b1b;
}

.variant-ghost {
  background: transparent;
  color: #475569;
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

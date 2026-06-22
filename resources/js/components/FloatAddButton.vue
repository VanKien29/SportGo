<template>
  <!-- Render as router-link if `to` prop is provided, otherwise as button -->
  <component
    :is="to ? 'router-link' : 'button'"
    class="sg-btn-float-add"
    :to="to || undefined"
    :type="to ? undefined : (type || 'button')"
    :disabled="to ? undefined : disabled"
    :title="title"
    :aria-label="title"
    @click="!to ? $emit('click', $event) : undefined"
  >
    <AppIcon name="plus" size="20" />
    <span class="btn-float-text">
      <slot>{{ label }}</slot>
    </span>
  </component>
</template>

<script>
import AppIcon from './AppIcon.vue';

export default {
  name: 'FloatAddButton',
  components: { AppIcon },
  emits: ['click'],
  props: {
    /** Text label shown on hover */
    label: {
      type: String,
      default: 'Thêm mới',
    },
    /** If set, renders as <router-link :to="to"> */
    to: {
      type: [String, Object],
      default: null,
    },
    /** Button type attribute (button | submit | reset) */
    type: {
      type: String,
      default: 'button',
    },
    /** Disables the button (ignored when using `to`) */
    disabled: {
      type: Boolean,
      default: false,
    },
    /** Tooltip / aria-label text */
    title: {
      type: String,
      default: null,
    },
  },
}
</script>

<style scoped>
.sg-btn-float-add {
  width: auto;
  min-width: 44px;
  max-width: 44px;
  height: 44px;
  border-radius: 22px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background-color: #10b981;
  color: #fff;
  border: none;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
  white-space: nowrap;
  padding: 0 12px;
  text-decoration: none;
}

.sg-btn-float-add .btn-float-text {
  max-width: 0;
  opacity: 0;
  margin-left: 0;
  font-weight: 700;
  font-size: 13px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: inline-block;
  color: #fff;
}

.sg-btn-float-add:hover:not(:disabled) {
  max-width: 300px;
  padding-left: 14px;
  padding-right: 16px;
  box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
  background-color: #059669;
}

.sg-btn-float-add:hover:not(:disabled) .btn-float-text {
  max-width: 240px;
  opacity: 1;
  margin-left: 6px;
}

.sg-btn-float-add:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .sg-btn-float-add {
    min-width: 40px;
    max-width: 40px;
    height: 40px;
    border-radius: 20px;
    padding: 0 10px;
  }

  .sg-btn-float-add:hover:not(:disabled) {
    max-width: 220px;
    padding-left: 12px;
    padding-right: 14px;
  }

  .sg-btn-float-add:hover:not(:disabled) .btn-float-text {
    max-width: 160px;
  }
}
</style>

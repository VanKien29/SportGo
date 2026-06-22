<template>
  <!-- Render as router-link if `to` prop is provided, otherwise as button -->
  <component
    :is="to ? 'router-link' : 'button'"
    class="btn-float-add"
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
.btn-float-add {
  width: 44px;
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

.btn-float-add .btn-float-text {
  max-width: 0;
  opacity: 0;
  margin-left: 0;
  font-weight: 700;
  font-size: 13px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: inline-block;
  color: #fff;
}

.btn-float-add:hover:not(:disabled) {
  width: 145px;
  justify-content: flex-start;
  padding-left: 14px;
  box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
  background-color: #059669;
}

.btn-float-add:hover:not(:disabled) .btn-float-text {
  max-width: 100px;
  opacity: 1;
  margin-left: 6px;
}

.btn-float-add:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

@media (max-width: 768px) {
  .btn-float-add {
    width: 40px;
    height: 40px;
    border-radius: 20px;
    padding: 0 10px;
  }

  .btn-float-add:hover:not(:disabled) {
    width: 130px;
    padding-left: 12px;
  }

  .btn-float-add:hover:not(:disabled) .btn-float-text {
    max-width: 80px;
  }
}
</style>

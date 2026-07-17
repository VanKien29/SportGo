<template>
  <button
    :type="nativeType"
    class="btn"
    :class="[type, size, { loading, disabled }]"
    :disabled="disabled || loading"
    @click="$emit('click', $event)"
  >
    <span v-if="loading" class="btn-spinner"></span>
    <span v-if="icon && !loading" class="btn-icon">
      <slot name="icon"></slot>
    </span>
    <span class="btn-text">
      <slot></slot>
    </span>
  </button>
</template>

<script>
export default {
  name: 'SgButton',
  props: {
    type: {
      type: String,
      default: 'secondary', // primary, secondary, danger, success, warning
    },
    size: {
      type: String,
      default: 'md', // sm, md, lg
    },
    loading: {
      type: Boolean,
      default: false,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    icon: {
      type: Boolean,
      default: false,
    },
    nativeType: {
      type: String,
      default: 'button',
    }
  }
}
</script>

<style scoped>
.btn-spinner {
  width: 12px;
  height: 12px;
  border: 2px solid currentColor;
  border-bottom-color: transparent;
  border-radius: 50%;
  display: inline-block;
  animation: rotation 1s linear infinite;
  margin-right: 6px;
  flex-shrink: 0;
}

@keyframes rotation {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
</style>

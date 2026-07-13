<template>
  <div class="sg-input-wrapper" :class="{ 'has-icon': hasIcon }">
    <div v-if="hasIcon" class="sg-input-icon-slot">
      <slot name="icon"></slot>
    </div>
    <input
      :type="type"
      :value="value"
      :placeholder="placeholder"
      :disabled="disabled"
      class="sg-theme-input"
      @input="$emit('input', $event.target.value)"
      @focus="$emit('focus', $event)"
      @blur="$emit('blur', $event)"
    />
  </div>
</template>

<script>
export default {
  name: 'SgInput',
  props: {
    value: {
      type: [String, Number],
      default: '',
    },
    type: {
      type: String,
      default: 'text',
    },
    placeholder: {
      type: String,
      default: '',
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    hasIcon: {
      type: Boolean,
      default: false,
    }
  }
}
</script>

<style scoped>
.sg-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.sg-theme-input {
  width: 100% !important;
  height: 38px !important;
  padding: 8px 12px !important;
  box-sizing: border-box !important;
  border: 1px solid var(--admin-border) !important;
  border-radius: var(--admin-radius) !important;
  background: var(--admin-surface) !important;
  color: var(--admin-text) !important;
  font-size: var(--admin-font-size-base, 14px) !important;
  font-family: inherit !important;
  outline: none !important;
  transition: border-color var(--admin-transition-fast) ease, box-shadow var(--admin-transition-fast) ease !important;
}

.sg-theme-input::placeholder {
  color: var(--admin-muted) !important;
  opacity: 0.7;
}

.sg-theme-input:focus {
  border-color: var(--admin-primary) !important;
  box-shadow: 0 0 0 3px var(--admin-primary-ring) !important;
}

.sg-input-wrapper.has-icon .sg-theme-input {
  padding-left: 36px !important;
}

.sg-input-icon-slot {
  position: absolute;
  left: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--admin-muted);
  pointer-events: none;
  z-index: 1;
}
</style>

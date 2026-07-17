<template>
  <span class="badge" :class="type">
    <slot></slot>
  </span>
</template>

<script>
export default {
  name: 'SgBadge',
  props: {
    type: {
      type: String,
      default: 'info', // success, warning, danger, processing, info
    }
  }
}
</script>

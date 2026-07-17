<template>
  <div class="settings-card">
    <div v-if="$slots.header" class="settings-card-header">
      <slot name="header"></slot>
    </div>
    <div class="settings-card-content">
      <slot></slot>
    </div>
    <div v-if="$slots.footer" class="settings-card-footer">
      <slot name="footer"></slot>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SgCard'
}
</script>

<template>
  <svg
    class="app-icon"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    :stroke-width="strokeWidth"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    <title v-if="title">{{ title }}</title>
    <component
      v-for="(node, index) in iconNodes"
      :key="index"
      :is="node[0]"
      v-bind="node[1]"
    />
  </svg>
</template>

<script>
import { ICON_REGISTRY, resolveIconName } from '../utils/iconRegistry.js';

export default {
  name: 'AppIcon',
  props: {
    name: { type: String, required: true },
    size: { type: [Number, String], default: 18 },
    strokeWidth: { type: [Number, String], default: 2 },
    title: { type: String, default: '' },
  },
  computed: {
    iconNodes() {
      const resolved = resolveIconName(this.name);
      return ICON_REGISTRY[resolved] || ICON_REGISTRY.alert;
    },
  },
};
</script>

<style scoped>
.app-icon {
  display: inline-block;
  flex: 0 0 auto;
}
</style>

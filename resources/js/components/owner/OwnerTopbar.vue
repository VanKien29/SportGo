<template>
  <header class="topbar">
    <div class="topbar-left">
      <button class="hamburger" type="button" title="Mở menu" @click="$emit('toggle-sidebar')">
        <AppIcon name="menu" size="21" />
      </button>

      <div class="admin-crumbs" aria-label="Breadcrumb">
        <span>Owner</span>
        <AppIcon name="chevronRight" size="13" />
        <span>{{ sectionLabel || 'Tổng quan' }}</span>
        <AppIcon name="chevronRight" size="13" />
        <span class="current-crumb">{{ title }}</span>
      </div>
    </div>

    <div class="topbar-actions">
      <ThemeToggle />
    </div>
  </header>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import ThemeToggle from '../ui/ThemeToggle.vue';

export default {
  name: 'OwnerTopbar',
  components: { AppIcon, ThemeToggle },
  props: {
    title: { type: String, required: true },
    sectionLabel: { type: String, default: '' },
  },
  emits: ['toggle-sidebar'],
};
</script>

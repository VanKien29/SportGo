<template>
  <div class="admin-nav-item-wrap">
    <component
      :is="hasChildren ? 'div' : 'RouterLink'"
      v-bind="linkProps"
      class="nav-item"
      :class="{
        'nav-active': isActive && !hasChildren,
        'nav-parent': hasChildren,
        'nav-parent-open': hasChildren && isOpen,
      }"
      :style="{ paddingLeft: `${level * 14 + 12}px` }"
      @click="handleClick"
    >
      <span class="nav-item-left">
        <AppIcon :name="item.icon" size="17" />
        <span class="nav-item-label">{{ item.label }}</span>
      </span>
      <span class="nav-item-right">
        <span v-if="item.badge" class="nav-badge">{{ item.badge }}</span>
        <AppIcon
          v-if="hasChildren"
          name="chevronRight"
          size="14"
          class="nav-chevron"
          :class="{ 'nav-chevron-open': isOpen }"
        />
      </span>
    </component>

    <!-- Children -->
    <div
      v-if="hasChildren"
      class="nav-children"
      :class="{ 'nav-children-open': isOpen }"
    >
      <div class="nav-children-inner">
        <div
          class="nav-children-line"
          :style="{ left: `${level * 14 + 20}px` }"
        ></div>
        <AdminNavItem
          v-for="child in item.children"
          :key="child.to"
          :item="child"
          :active-route-name="activeRouteName"
          :level="level + 1"
          @navigate="$emit('navigate')"
        />
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../AppIcon.vue';

export default {
  name: 'AdminNavItem',
  components: { AppIcon },
  props: {
    item: { type: Object, required: true },
    activeRouteName: { type: String, default: '' },
    level: { type: Number, default: 0 },
  },
  emits: ['navigate'],
  data() {
    return {
      isOpen: false,
    };
  },
  computed: {
    hasChildren() {
      return this.item.children && this.item.children.length > 0;
    },
    isActive() {
      if (this.item.activeNames?.includes(this.activeRouteName)) {
        // Special handling for moderation tabs
        if (this.activeRouteName === 'admin-moderation') {
          const currentTab = this.$route?.query?.tab || 'moderation';
          if (this.item.to.includes('tab=moderation')) return currentTab === 'moderation';
          if (this.item.to.includes('tab=reports')) return currentTab === 'reports';
          if (this.item.to.includes('tab=complaints')) return currentTab === 'complaints';
          if (!this.item.to.includes('tab=')) return currentTab === 'moderation';
        }
        return true;
      }
      return false;
    },
    isChildActive() {
      if (!this.hasChildren) return false;
      return this.item.children.some((child) =>
        child.activeNames?.includes(this.activeRouteName),
      );
    },
    linkProps() {
      if (this.hasChildren) return { role: 'button', tabindex: '0' };
      return { to: this.item.to };
    },
  },
  watch: {
    isChildActive: {
      immediate: true,
      handler(val) {
        if (val) this.isOpen = true;
      },
    },
  },
  methods: {
    handleClick() {
      if (this.hasChildren) {
        this.isOpen = !this.isOpen;
      } else {
        this.$emit('navigate');
      }
    },
  },
};
</script>

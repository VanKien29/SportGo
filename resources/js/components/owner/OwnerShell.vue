<template>
  <div class="sg-shell-admin sg-shell-owner" :class="{ 'nav-open': sidebarOpen }">
    <OwnerSidebar
      :sections="sections"
      :active-route-name="activeRouteName"
      :clusters="clusters"
      :selected-cluster-id="selectedClusterId"
      :selected-cluster="selectedCluster"
      :cluster-loading="clusterLoading"
      @cluster-change="$emit('cluster-change', $event)"
      @navigate="closeSidebar"
    />
    <button
      v-if="sidebarOpen"
      class="admin-shell-backdrop"
      type="button"
      aria-label="Đóng menu"
      @click="closeSidebar"
    ></button>

    <main class="main-content">
      <OwnerTopbar
        :title="title"
        :section-label="sectionLabel"
        @toggle-sidebar="toggleSidebar"
      />
      <div class="content-area">
        <slot />
      </div>
      <footer class="admin-footer">
        <span>SportGo Owner</span>
        <span>Template Adminator · Green UI</span>
      </footer>
    </main>
  </div>
</template>

<script>
import OwnerSidebar from './OwnerSidebar.vue';
import OwnerTopbar from './OwnerTopbar.vue';

export default {
  name: 'OwnerShell',
  components: { OwnerSidebar, OwnerTopbar },
  props: {
    sections: { type: Array, required: true },
    title: { type: String, required: true },
    sectionLabel: { type: String, default: '' },
    activeRouteName: { type: String, default: '' },
    clusters: { type: Array, default: () => [] },
    selectedClusterId: { type: [String, Number], default: '' },
    selectedCluster: { type: Object, default: null },
    clusterLoading: { type: Boolean, default: false },
  },
  emits: ['cluster-change'],
  data() {
    return {
      sidebarOpen: false,
    };
  },
  methods: {
    toggleSidebar() {
      this.sidebarOpen = !this.sidebarOpen;
    },
    closeSidebar() {
      this.sidebarOpen = false;
    },
  },
  watch: {
    $route() {
      this.closeSidebar();
    },
  },
};
</script>

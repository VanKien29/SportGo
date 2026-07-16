<template>
  <div class="floating-actions">
    <BackButton 
      v-if="canGoBack" 
      floating 
    />
    <ScrollToTopButton />
  </div>
</template>

<script>
import ScrollToTopButton from './ScrollToTopButton.vue';
import BackButton from './BackButton.vue';

export default {
  name: 'FloatingActions',
  components: { ScrollToTopButton, BackButton },
  computed: {
    canGoBack() {
      if (!this.$route) return false;
      if (this.$route.meta?.hideFloatingBack) return false;
      const path = this.$route.path;
      if (path === '/' || path === '/login' || path === '/register' || path.startsWith('/auth')) return false;
      
      const segments = path.split('/').filter(Boolean);
      const portal = ['admin', 'owner', 'staff'].includes(segments[0]);
      // Client pages use breadcrumbs or an explicit page action. A global fixed
      // back button covered booking totals, document actions and profile cards
      // on narrow screens, so keep this utility inside management portals only.
      if (!portal) return false;
      const isDetail = Object.keys(this.$route.params).length > 0;
      
      // Portal root pages only show the floating back action on deeper subpages.
      // Otherwise depth > 1 is a subpage
      let isDeep = false;
      isDeep = segments.length > 2;

      return isDetail || isDeep;
    }
  }
}
</script>

<style scoped>
.floating-actions {
  position: fixed;
  bottom: 30px;
  right: 30px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  z-index: 9999;
}

@media (max-width: 768px) {
  .floating-actions {
    bottom: 20px;
    right: 20px;
  }
}
</style>

<style>
body:has(.modal-backdrop, .document-viewer-overlay, [aria-modal="true"]) .floating-actions {
  display: none;
}
</style>

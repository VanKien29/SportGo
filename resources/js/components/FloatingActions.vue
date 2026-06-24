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
      const isDetail = Object.keys(this.$route.params).length > 0;
      
      // If it's under /admin or /owner, depth > 2 means it's a subpage (like /admin/settings/xxx)
      // Otherwise depth > 1 is a subpage
      let isDeep = false;
      if (segments[0] === 'admin' || segments[0] === 'owner') {
        isDeep = segments.length > 2;
      } else {
        isDeep = segments.length > 1;
      }

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

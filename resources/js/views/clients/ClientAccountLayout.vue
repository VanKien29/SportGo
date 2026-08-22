<template>
  <div class="sg-client-page wallet-white-page">
    <PublicNavbar />
    <main class="wallet-white-main">
      <div class="wallet-layout-grid">
        <ClientAccountNav />
        <router-view />
      </div>
    </main>
  </div>
</template>

<script>
import PublicNavbar from '../../components/PublicNavbar.vue';
import ClientAccountNav from '../../components/ClientAccountNav.vue';

export default {
  name: 'ClientAccountLayout',
  components: {
    PublicNavbar,
    ClientAccountNav,
  },
};
</script>

<style scoped>
.sg-client-page.wallet-white-page {
  min-height: 100vh;
  background: #ffffff !important;
  color: #0f172a;
}

.wallet-white-main {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 32px 24px 64px;
}

.wallet-layout-grid {
  display: flex;
  align-items: flex-start;
  gap: 32px;
}

@media (max-width: 900px) {
  .wallet-layout-grid {
    flex-direction: column;
  }
}
</style>

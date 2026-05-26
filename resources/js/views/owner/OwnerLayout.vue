<template>
  <SidebarLayout
    brand-sub="Quản lý sân"
    dashboard-route="/owner/dashboard"
    :cluster-name="clusterName"
    :show-cluster-switch="!!clusterName"
  >
    <template #topbar-title>
      <span>{{ currentTitle }}</span>
    </template>
    <router-view @cluster-changed="refreshCluster" />
  </SidebarLayout>
</template>

<script>
import OwnerLayoutLogic from '../../controllers/owner/OwnerLayout.js';
export default OwnerLayoutLogic;
</script>

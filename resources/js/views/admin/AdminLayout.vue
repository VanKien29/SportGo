<template>
  <SidebarLayout brand-sub="Quản trị hệ thống" dashboard-route="/admin/dashboard">
    <template #nav-items>
      <router-link to="/admin/dashboard" class="nav-item" active-class="nav-active">
        <span>Dashboard</span>
      </router-link>
      <router-link to="/admin/users" class="nav-item" active-class="nav-active">
        <span>Quản lý tài khoản</span>
      </router-link>
      <router-link to="/admin/banners" class="nav-item" active-class="nav-active">
        <span>Quản lý banner</span>
      </router-link>
      <router-link to="/admin/partner-applications" class="nav-item" active-class="nav-active">
        <span>Quản lý đơn đăng kí</span>
      </router-link>
      <router-link to="/admin/policies" class="nav-item" active-class="nav-active">
        <span>Quản lý chính sách</span>
      </router-link>
      <router-link to="/admin/permissions" class="nav-item" active-class="nav-active">
        <span>Phân quyền</span>
      </router-link>
    </template>
    <template #topbar-title>
      <span>{{ currentTitle }}</span>
    </template>
    <router-view />
  </SidebarLayout>
</template>

<script>
import AdminLayoutLogic from '../../controllers/admin/AdminLayout.js';
export default AdminLayoutLogic;
</script>
<style src="../../../css/admin/layout.css" scoped></style>


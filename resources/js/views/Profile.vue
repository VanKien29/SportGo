<template>
  <div class="profile-wrapper">
    <!-- ── Admin layout: sidebar admin ── -->
    <template v-if="role === 'admin'">
      <SidebarLayout brand-sub="Quản trị hệ thống" dashboard-route="/admin/dashboard">
        <template #topbar-title>Thông tin cá nhân</template>
        <div class="profile-content">
          <ProfileCard :user="user" @go-back="goBack" />
        </div>
      </SidebarLayout>
    </template>

    <!-- ── Owner layout: sidebar chủ sân ── -->
    <template v-else-if="role === 'owner'">
      <SidebarLayout
        brand-sub="Quản lý sân"
        dashboard-route="/owner/dashboard"
      >
        <template #topbar-title>Thông tin cá nhân</template>
        <div class="profile-content">
          <ProfileCard :user="user" @go-back="goBack" />
        </div>
      </SidebarLayout>
    </template>

    <!-- ── User / guest layout: public navbar ── -->
    <template v-else>
      <PublicNavbar />
      <div class="profile-public-container">
        <ProfileCard :user="user" @go-back="goBack" />
      </div>
    </template>
  </div>
</template>

<script>
import SidebarLayout from '../components/SidebarLayout.vue';
import PublicNavbar from '../components/PublicNavbar.vue';
import ProfileCard from '../components/ProfileCard.vue';
import { getAuth } from '../stores/auth.js';

export default {
  name: 'ProfileView',
  components: { SidebarLayout, PublicNavbar, ProfileCard },

  data() {
    const user = getAuth();
    return {
      user,
      role: user?.role || 'guest',
    };
  },

  created() {
    if (!this.user) {
      this.$router.replace({ name: 'login' });
    }
  },

  methods: {
    goBack() {
      if (this.role === 'admin') {
        this.$router.push('/admin/dashboard');
      } else if (this.role === 'owner') {
        this.$router.push('/owner/dashboard');
      } else {
        this.$router.push('/');
      }
    },
  },
};
</script>

<style scoped>
/* Single root wrapper — invisible passthrough */
.profile-wrapper {
  min-height: 100vh;
}

/* For admin/owner inside sidebar content area */
.profile-content {
  max-width: 600px;
}

/* For regular user — below public navbar */
.profile-public-container {
  min-height: 100vh;
  background: var(--sg-surface);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 100px 24px 60px;
}
</style>

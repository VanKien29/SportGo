<template>
  <div class="profile-wrapper">
    <template v-if="role === 'owner'">
      <SidebarLayout brand-sub="Quản lý sân" dashboard-route="/owner/dashboard">
        <template #topbar-title>Thông tin cá nhân</template>
        <div class="profile-content">
          <ProfileCard :user="user" @go-back="goBack" />
        </div>
      </SidebarLayout>
    </template>

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
      if (this.role === 'owner') {
        this.$router.push('/owner/dashboard');
        return;
      }

      this.$router.push('/');
    },
  },
};
</script>

<style scoped>
.profile-wrapper {
  min-height: 100vh;
}

.profile-content {
  max-width: 600px;
}

.profile-public-container {
  min-height: 100vh;
  background: var(--sg-surface);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 100px 24px 60px;
}
</style>

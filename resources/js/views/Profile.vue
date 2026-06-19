<template>
  <div class="profile-wrapper">
    <template v-if="role === 'owner'">
      <section class="page-head">
        <div>
          <h2>Thông tin cá nhân</h2>
          <p>Quản lý thông tin tài khoản chủ sân đang đăng nhập.</p>
        </div>
      </section>
      <div class="profile-content owner-profile-content">
        <ProfileCard :user="user" @go-back="goBack" />
      </div>
    </template>

    <template v-else>
      <PublicNavbar />
      <div class="profile-public-container" style="flex-direction: column; align-items: center; gap: 24px;">
        <ProfileCard :user="user" @go-back="goBack" />
        <div class="become-partner-card">
          <h3>Trở thành Đối tác của SportGo</h3>
          <p>Tăng doanh thu và quản lý cụm sân của bạn một cách chuyên nghiệp nhất.</p>
          <button class="btn primary mt-2" @click="$router.push('/become-partner')">Đăng ký làm Chủ sân</button>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import PublicNavbar from '../components/PublicNavbar.vue';
import ProfileCard from '../components/ProfileCard.vue';
import { getAuth } from '../stores/auth.js';

export default {
  name: 'ProfileView',
  components: { PublicNavbar, ProfileCard },
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

.owner-profile-content {
  display: flex;
  align-items: flex-start;
}

.profile-public-container {
  min-height: 100vh;
  background: var(--sg-surface);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 100px 24px 60px;
}

.become-partner-card {
  width: 100%;
  max-width: 600px;
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 12px;
  padding: 24px;
  text-align: center;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

.become-partner-card h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 8px;
  color: #0f172a;
}

.become-partner-card p {
  color: #64748b;
  margin-bottom: 16px;
  font-size: 14px;
}

.mt-2 {
  margin-top: 8px;
}

.btn {
  display: inline-flex;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  border: none;
  cursor: pointer;
}

.btn.primary {
  background: #0f172a;
  color: #fff;
}
</style>

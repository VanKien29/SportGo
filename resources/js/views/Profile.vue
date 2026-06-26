<template>
  <div class="profile-wrapper">
    <template v-if="role === 'owner'">
      <div class="profile-content owner-profile-content">
        <ProfileCard :user="user" @go-back="goBack" />
      </div>
    </template>

    <template v-else>
      <PublicNavbar />
      <VipPromptToast v-if="role === 'user'" :duration="9000" />
      <div class="profile-public-container">
        <ProfileCard :user="user" @go-back="goBack" />
        <div class="profile-secondary-grid">
          <router-link v-if="role === 'user'" class="vip-upgrade-card" to="/vip-membership">
            <span>SportGo VIP</span>
            <h3>{{ vipCtaTitle }}</h3>
            <p>{{ vipCtaText }}</p>
            <strong>{{ vipCtaAction }}</strong>
          </router-link>
          <div class="become-partner-card">
            <h3>Trở thành Đối tác của SportGo</h3>
            <p>Tăng doanh thu và quản lý cụm sân của bạn một cách chuyên nghiệp nhất.</p>
            <button class="btn primary mt-2" @click="$router.push('/become-partner')">Đăng ký làm Chủ sân</button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import PublicNavbar from '../components/PublicNavbar.vue';
import ProfileCard from '../components/ProfileCard.vue';
import VipPromptToast from '../components/VipPromptToast.vue';
import { getAuth } from '../stores/auth.js';

export default {
  name: 'ProfileView',
  components: { PublicNavbar, ProfileCard, VipPromptToast },
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
  computed: {
    hasVip() {
      return Boolean(this.user?.vip_subscription);
    },
    vipCtaTitle() {
      return this.hasVip ? 'Quản lý gói VIP của bạn' : 'Mua gói VIP đi';
    },
    vipCtaText() {
      return this.hasVip
        ? 'Xem thời hạn, quyền lợi cashback và lựa chọn nâng cấp khi cần.'
        : 'Mở cashback, voucher riêng theo gói và quyền lợi ưu tiên khi sử dụng SportGo.';
    },
    vipCtaAction() {
      return this.hasVip ? 'Xem gói VIP' : 'Mua gói VIP';
    },
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
  display: grid;
  justify-items: center;
  gap: 18px;
  padding: 100px 24px 60px;
}

.profile-secondary-grid {
  width: min(760px, 100%);
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.vip-upgrade-card,
.become-partner-card {
  width: 100%;
  background: #fff;
  border: 1px solid var(--sg-border);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

.vip-upgrade-card {
  display: grid;
  gap: 8px;
  border-color: #fbbf24;
  background: #fffbeb;
  color: #78350f;
  text-decoration: none;
}

.vip-upgrade-card span {
  color: #b45309;
  font-size: 11px;
  font-weight: 900;
  text-transform: uppercase;
}

.vip-upgrade-card h3,
.become-partner-card h3 {
  font-size: 18px;
  font-weight: 700;
  margin: 0 0 8px;
  color: #0f172a;
}

.vip-upgrade-card h3 {
  color: #78350f;
}

.vip-upgrade-card p,
.become-partner-card p {
  color: #64748b;
  margin: 0 0 16px;
  font-size: 14px;
}

.vip-upgrade-card p {
  color: #92400e;
  line-height: 1.45;
}

.vip-upgrade-card strong {
  justify-self: start;
  padding: 8px 11px;
  border-radius: 8px;
  background: #16a34a;
  color: #fff;
  font-size: 13px;
}

.become-partner-card {
  text-align: center;
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

@media (max-width: 760px) {
  .profile-secondary-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<template>
  <div class="profile-wrapper">
    <template v-if="role === 'owner' || role === 'staff'">
      <main class="profile-content owner-profile-content" aria-label="Thông tin tài khoản">
        <ProfileCard :user="user" @go-back="goBack" />
      </main>
    </template>

    <template v-else>
      <PublicNavbar />
      <main class="profile-public-container sg-client-page">
        <div class="profile-account-shell" :class="{ 'profile-account-shell--wide': activeSection === 'refunds' }">
          <UserRefundBalancePanel v-if="activeSection === 'refunds'" />

          <template v-else>
            <ProfileCard :user="user" @go-back="goBack" />

            <section class="profile-partner-card sg-client-card" aria-labelledby="partner-heading">
              <span class="profile-partner-icon" aria-hidden="true">
                <AppIcon name="building" :size="22" />
              </span>
              <div>
                <p class="sg-client-eyebrow">Dành cho đơn vị kinh doanh sân</p>
                <h2 id="partner-heading">Đăng ký trở thành đối tác SportGo</h2>
                <p>
                  Gửi hồ sơ đối tác để quản lý cụm sân, lịch đặt và vận hành trên một luồng thống nhất.
                </p>
              </div>
              <router-link class="sg-client-button sg-client-button--primary" to="/become-partner">
                Bắt đầu đăng ký
                <AppIcon name="chevronRight" :size="17" />
              </router-link>
            </section>
          </template>
        </div>
      </main>
    </template>
  </div>
</template>

<script>
import AppIcon from '../components/AppIcon.vue';
import PublicNavbar from '../components/PublicNavbar.vue';
import ProfileCard from '../components/ProfileCard.vue';
import UserRefundBalancePanel from '../components/UserRefundBalancePanel.vue';
import { getAuth } from '../stores/auth.js';

export default {
  name: 'ProfileView',
  components: { AppIcon, PublicNavbar, ProfileCard, UserRefundBalancePanel },
  data() {
    const user = getAuth();
    return {
      user,
      role: user?.role || 'guest',
    };
  },
  created() {
    if (!this.user) {
      this.$router.replace({ name: 'login', query: { redirect: this.$route.fullPath } });
    }
  },
  computed: {
    activeSection() {
      return this.$route.query.tab === 'refunds' ? 'refunds' : 'profile';
    },
  },
  methods: {
    goBack() {
      if (this.role === 'owner') {
        this.$router.push('/owner/venue-clusters');
        return;
      }
      if (this.role === 'staff') {
        this.$router.push('/staff/bookings');
        return;
      }

      this.$router.push('/');
    },
  },
};
</script>



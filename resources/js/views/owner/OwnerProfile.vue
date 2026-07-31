<template>
  <main class="profile-content owner-profile-content" aria-label="Thông tin tài khoản vận hành">
    <ProfileCard :user="user" @go-back="goBack" />
  </main>
</template>

<script>
import ProfileCard from '../../components/ProfileCard.vue';
import { getAuth } from '../../stores/auth.js';

export default {
  name: 'OwnerProfile',
  components: { ProfileCard },
  data() { return { user: getAuth() }; },
  methods: {
    goBack() {
      this.$router.push(this.user?.role_group === 'staff' ? { name: 'staff-dashboard' } : { name: 'owner-dashboard' });
    },
  },
};
</script>

<style scoped src="/resources/css/views/client-profile.css"></style>

<template>
  <div class="admin-profile-page">
    <ProfileCard :user="user" @go-back="$router.push('/admin/dashboard')" />
  </div>
</template>

<script>
import { getAuth } from '../../stores/auth.js';
import ProfileCard from '../../components/ProfileCard.vue';

export default {
  name: 'AdminProfile',
  components: { ProfileCard },
  data() {
    return {
      user: getAuth(),
    };
  },
};
</script>

<style scoped>
.admin-profile-page {
  max-width: 640px;
}
</style>

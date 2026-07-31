<template>
  <div></div>
</template>

<script>
export default {
  name: 'AdminSystemProfile',
  created() {
    this.$router.replace({ name: 'admin-settings' });
  }
}
</script>

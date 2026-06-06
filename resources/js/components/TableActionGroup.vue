<template>
  <div class="table-action-group">
    <slot />
  </div>
</template>

<script>
export default {
  name: 'TableActionGroup',
};
</script>

<style scoped>
.table-action-group {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  white-space: nowrap;
}
</style>

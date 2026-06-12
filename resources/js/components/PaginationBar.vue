<template>
  <nav v-if="meta.last_page > 1" class="pagination-bar" aria-label="Phân trang">
    <ActionIconButton
      icon="chevronLeft"
      label="Trang trước"
      :disabled="meta.current_page <= 1"
      @click="$emit('change', meta.current_page - 1)"
    />
    <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
    <ActionIconButton
      icon="chevronRight"
      label="Trang sau"
      :disabled="meta.current_page >= meta.last_page"
      @click="$emit('change', meta.current_page + 1)"
    />
  </nav>
</template>

<script>
import ActionIconButton from './ActionIconButton.vue';

export default {
  name: 'PaginationBar',
  components: { ActionIconButton },
  emits: ['change'],
  props: {
    meta: {
      type: Object,
      required: true,
    },
  },
};
</script>

<style scoped>
.pagination-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}
</style>

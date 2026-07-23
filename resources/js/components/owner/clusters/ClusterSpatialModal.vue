<template>
  <div v-if="show" class="modal-backdrop spatial-modal-backdrop" @click.self="$emit('close')">
    <div class="spatial-modal-card surface-card animate-fade-in">
      <div class="modal-header">
        <div class="modal-title-group">
          <AppIcon name="map" size="20" />
          <div>
            <h3>Trình thiết kế sơ đồ mặt bằng 2D/3D</h3>
            <p class="text-muted text-xs">Sắp xếp vị trí không gian các sân con và khu vực tiện ích cảnh quan trực quan.</p>
          </div>
        </div>
        <button type="button" class="close-btn" @click="$emit('close')">&times;</button>
      </div>

      <div class="spatial-modal-body">
        <slot />
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" @click="$emit('close')">Đóng</button>
        <button type="button" class="btn btn-primary" :disabled="saving" @click="$emit('save-layout')">
          <AppIcon name="save" size="15" />
          <span>{{ saving ? "Đang lưu sơ đồ..." : "Lưu vị trí sơ đồ" }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../AppIcon.vue';

export default {
  name: 'ClusterSpatialModal',
  components: { AppIcon },
  props: {
    show: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
  },
  emits: ['close', 'save-layout'],
};
</script>

<style scoped>
.spatial-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.spatial-modal-card {
  width: min(1320px, 96vw);
  height: min(880px, 92vh);
  display: flex;
  flex-direction: column;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: var(--admin-surface, #121722);
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4);
}

.spatial-modal-body {
  flex: 1;
  overflow: hidden;
  position: relative;
  background: var(--admin-surface, #121722);
  padding: 14px 20px 20px 20px;
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  background: var(--admin-surface, #121722);
}

.modal-title-group {
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  padding: 14px 24px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  background: var(--admin-surface, #121722);
}
</style>

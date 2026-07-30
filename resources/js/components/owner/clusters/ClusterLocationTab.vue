<template>
  <div class="cluster-location-surface">
    <div class="tab-section-header">
      <div>
        <h2>Vị trí & Tọa độ bản đồ</h2>
        <p class="section-subtitle">Cập nhật vị trí chính xác trên bản đồ để hỗ trợ người chơi tìm đường chỉ dẫn.</p>
      </div>
    </div>

    <!-- Location Form -->
    <form @submit.prevent="$emit('submit-location-request')" class="location-form">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Tỉnh / Thành phố</label>
          <input
            v-model="locationForm.province"
            type="text"
            class="form-control"
            placeholder="VD: Hà Nội..."
            required
            :disabled="isClusterLocked"
          />
        </div>

        <div class="form-group">
          <label class="form-label">Phường / Xã</label>
          <input
            v-model="locationForm.ward"
            type="text"
            class="form-control"
            placeholder="VD: Dịch Vọng..."
            required
            :disabled="isClusterLocked"
          />
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Địa chỉ chi tiết</label>
        <input
          v-model="locationForm.address"
          type="text"
          class="form-control"
          placeholder="VD: Số 18 Cầu Giấy..."
          required
          :disabled="isClusterLocked"
        />
      </div>

      <div class="form-group">
        <label class="form-label">Đường dẫn Google Maps (URL)</label>
        <div class="input-with-button">
          <input
            v-model="locationForm.map_url"
            type="text"
            class="form-control"
            placeholder="https://maps.google.com/..."
            :disabled="isClusterLocked"
          />
          <button
            type="button"
            class="btn btn-outline"
            :disabled="resolvingMap || !locationForm.map_url || isClusterLocked"
            @click="$emit('resolve-map-url')"
          >
            <span>{{ resolvingMap ? "Đang trích xuất..." : "Trích xuất tọa độ" }}</span>
          </button>
        </div>
        <span v-if="mapExtractMsg" class="help-text text-xs text-muted">{{ mapExtractMsg }}</span>
      </div>

      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Vĩ độ (Latitude)</label>
          <input
            v-model.number="locationForm.latitude"
            type="number"
            step="any"
            class="form-control"
            placeholder="21.0285..."
            :disabled="isClusterLocked"
          />
        </div>

        <div class="form-group">
          <label class="form-label">Kinh độ (Longitude)</label>
          <input
            v-model.number="locationForm.longitude"
            type="number"
            step="any"
            class="form-control"
            placeholder="105.8542..."
            :disabled="isClusterLocked"
          />
        </div>
      </div>

      <div class="form-actions">
        <button
          type="submit"
          class="btn btn-primary"
          :disabled="submitting || isClusterLocked"
        >
          <AppIcon name="send" size="15" />
          <span>{{ submitting ? "Đang gửi yêu cầu..." : "Gửi yêu cầu duyệt vị trí mới" }}</span>
        </button>
        <span v-if="requestSuccess" class="text-success text-sm">Gửi yêu cầu vị trí thành công!</span>
        <span v-if="requestError" class="text-danger text-sm">{{ requestError }}</span>
      </div>
    </form>
  </div>
</template>

<script>
import AppIcon from '../../AppIcon.vue';

export default {
  name: 'ClusterLocationTab',
  components: { AppIcon },
  props: {
    locationForm: { type: Object, required: true },
    resolvingMap: { type: Boolean, default: false },
    mapExtractMsg: { type: String, default: null },
    submitting: { type: Boolean, default: false },
    requestSuccess: { type: Boolean, default: false },
    requestError: { type: String, default: null },
    isClusterLocked: { type: Boolean, default: false },
  },
  emits: ['submit-location-request', 'resolve-map-url'],
};
</script>

<style scoped>
.cluster-location-surface {
  background: var(--admin-surface, #ffffff);
  border-radius: 0;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.tab-section-header h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 400;
  color: var(--admin-text, #0f172a);
}

.section-subtitle {
  margin: 4px 0 0;
  font-size: 13px;
  color: var(--admin-muted, #64748b);
}

.location-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--admin-text, #0f172a);
}

.input-with-button {
  display: flex;
  gap: 10px;
}

.input-with-button input {
  flex: 1;
}

.form-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
  border-top: none !important;
  padding-top: 0 !important;
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-color);
  box-shadow: var(--shadow-sm);
  transform: none;
}
</style>

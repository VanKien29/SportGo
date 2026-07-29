<template>
  <div class="cluster-profile-surface">
    <!-- Section 1: Thông tin chung & Liên hệ -->
    <div class="profile-section-card">
      <div class="tab-section-header">
        <div>
          <h2>Thông tin & Liên hệ cụm sân</h2>
          <p class="section-subtitle">Tên cụm sân, số điện thoại hotline liên hệ và mô tả giới thiệu. Thay đổi sẽ gửi cho Admin kiểm duyệt.</p>
        </div>
      </div>

      <form @submit.prevent="$emit('submit-info')" class="general-info-form">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Tên cụm sân</label>
            <input
              v-model="form.name"
              type="text"
              class="form-control"
              placeholder="Nhập tên cụm sân..."
              required
              :disabled="isClusterLocked"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Số điện thoại liên hệ</label>
            <input
              v-model="form.phone_contact"
              type="text"
              class="form-control"
              placeholder="0901234567..."
              required
              :disabled="isClusterLocked"
            />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Giới thiệu / Mô tả cụm sân</label>
          <textarea
            v-model="form.description"
            rows="3"
            class="form-control"
            placeholder="Mô tả đặc điểm, quy định hoặc lưu ý tại cụm sân..."
            :disabled="isClusterLocked"
          ></textarea>
        </div>

        <div class="form-actions">
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="updating || isClusterLocked"
          >
            <AppIcon name="send" size="15" />
            <span>{{ updating ? "Đang gửi..." : "Gửi yêu cầu duyệt thay đổi thông tin" }}</span>
          </button>
          <span v-if="updateSuccess" class="text-success text-sm">Gửi yêu cầu thay đổi thông tin thành công! Đang chờ Admin xét duyệt.</span>
          <span v-if="updateError" class="text-danger text-sm">{{ updateError }}</span>
        </div>
      </form>
    </div>

    <!-- Section 2: Địa chỉ & Vị trí bản đồ -->
    <div class="profile-section-card">
      <div class="tab-section-header">
        <div>
          <h2>Vị trí & Tọa độ bản đồ</h2>
          <p class="section-subtitle">Địa chỉ chi tiết và tọa độ Google Maps hỗ trợ người chơi chỉ đường.</p>
        </div>
      </div>

      <form @submit.prevent="$emit('submit-location-request')" class="location-form">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Tỉnh / Thành phố</label>
            <BaseCombobox
              :model-value="currentProvinceCode"
              :options="provinceOptions"
              placeholder="Tìm Tỉnh/Thành phố..."
              :disabled="isClusterLocked"
              @update:model-value="onProvinceSelect"
              @select="onProvinceSelect"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Phường / Xã</label>
            <BaseCombobox
              :model-value="currentWardCode"
              :options="wardOptions"
              placeholder="Tìm Phường/Xã..."
              :disabled="!currentProvinceCode || isClusterLocked"
              @update:model-value="onWardSelect"
              @select="onWardSelect"
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

        <div class="form-actions">
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="requestingLocation || isClusterLocked"
          >
            <AppIcon name="send" size="15" />
            <span>{{ requestingLocation ? "Đang gửi..." : "Gửi yêu cầu duyệt vị trí mới" }}</span>
          </button>
          <span v-if="locationSuccess" class="text-success text-sm">Gửi yêu cầu vị trí thành công!</span>
          <span v-if="locationError" class="text-danger text-sm">{{ locationError }}</span>
        </div>
      </form>
    </div>

    <!-- Section 3: Tiện ích cụm sân (Amenities) -->
    <div class="profile-section-card">
      <div class="tab-section-header">
        <div>
          <h2>Tiện ích cụm sân (Amenities)</h2>
          <p class="section-subtitle">Bật/tắt các tiện ích khả dụng tại sân để người chơi dễ tìm kiếm.</p>
        </div>
      </div>

      <div v-if="availableAmenities.length > 0" class="amenities-selector-grid">
        <button
          v-for="(amenity, idx) in availableAmenities"
          :key="getAmenityId(amenity) || idx"
          type="button"
          class="amenity-chip-btn"
          :class="{ active: isAmenitySelected(amenity) }"
          :disabled="isClusterLocked"
          @click="$emit('toggle-amenity', getAmenityId(amenity))"
        >
          <AppIcon name="check" size="13" />
          <span>{{ getAmenityName(amenity) }}</span>
        </button>
      </div>
      <div v-else class="text-muted text-italic text-sm">Chưa có tiện ích hệ thống nào khả dụng.</div>
    </div>

    <!-- Section 4: Bộ sưu tập ảnh thực tế -->
    <div class="profile-section-card">
      <div class="tab-section-header">
        <div>
          <h2>Bộ sưu tập ảnh cụm sân</h2>
          <p class="section-subtitle">Hình ảnh thực tế của sân bãi thu hút người chơi đặt sân.</p>
        </div>
        <button
          type="button"
          class="btn btn-outline"
          :disabled="uploadingImage || isClusterLocked"
          @click="$refs.galleryFileInput.click()"
        >
          <AppIcon name="image" size="15" />
          <span>{{ uploadingImage ? "Đang tải..." : "Tải ảnh mới" }}</span>
        </button>
        <input
          ref="galleryFileInput"
          type="file"
          class="hidden-file-input"
          accept="image/*"
          @change="$emit('upload-gallery-image', $event)"
        />
      </div>

      <div v-if="imagesList.length > 0" class="owner-gallery-grid">
        <div v-for="img in imagesList" :key="img.id" class="owner-gallery-item">
          <img :src="imgUrl(img.file_path)" alt="Hình ảnh cụm sân" class="owner-gallery-img" />
          <button
            type="button"
            class="delete-img-btn"
            title="Xóa ảnh này"
            :disabled="isClusterLocked"
            @click="$emit('delete-image', img.id)"
          >
            &times;
          </button>
        </div>
      </div>
      <div v-else class="owner-gallery-empty">
        <AppIcon name="image" size="32" />
        <p>Chưa có hình ảnh nào trong bộ sưu tập.</p>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../../AppIcon.vue';
import BaseCombobox from '../../BaseCombobox.vue';

export default {
  name: 'ClusterGeneralInfoTab',
  components: { AppIcon, BaseCombobox },
  props: {
    form: { type: Object, required: true },
    locationForm: { type: Object, required: true },
    provinceOptions: { type: Array, default: () => [] },
    wardOptions: { type: Array, default: () => [] },
    availableAmenities: { type: Array, default: () => [] },
    imagesList: { type: Array, default: () => [] },
    updating: { type: Boolean, default: false },
    updateSuccess: { type: Boolean, default: false },
    updateError: { type: String, default: null },
    resolvingMap: { type: Boolean, default: false },
    mapExtractMsg: { type: String, default: null },
    requestingLocation: { type: Boolean, default: false },
    locationSuccess: { type: Boolean, default: false },
    locationError: { type: String, default: null },
    uploadingImage: { type: Boolean, default: false },
    isClusterLocked: { type: Boolean, default: false },
  },
  emits: [
    'submit-info',
    'submit-location-request',
    'resolve-map-url',
    'province-change',
    'ward-change',
    'toggle-amenity',
    'upload-gallery-image',
    'delete-image',
  ],
  computed: {
    currentProvinceCode() {
      return this.locationForm.new_province_code || this.locationForm.province_code || '';
    },
    currentWardCode() {
      return this.locationForm.new_ward_code || this.locationForm.ward_code || '';
    },
  },
  methods: {
    onProvinceSelect(val) {
      this.locationForm.new_province_code = val;
      this.locationForm.province_code = val;
      this.locationForm.new_ward_code = '';
      this.locationForm.ward_code = '';
      this.$emit('province-change', val);
    },
    onWardSelect(val) {
      this.locationForm.new_ward_code = val;
      this.locationForm.ward_code = val;
      this.$emit('ward-change', val);
    },
    getAmenityName(item) {
      if (typeof item === 'string') return item;
      return item ? item.name || item.id || '' : '';
    },
    getAmenityId(item) {
      if (typeof item === 'string') return item;
      return item ? item.id || item.name || '' : '';
    },
    isAmenitySelected(item) {
      const id = this.getAmenityId(item);
      return Array.isArray(this.form.amenities) && this.form.amenities.includes(id);
    },
    imgUrl(filePath) {
      if (!filePath) return '';
      if (filePath.startsWith('http')) return filePath;
      return `/storage/${filePath}`;
    },
  },
};
</script>

<style scoped>
.btn-primary:hover:not(:disabled) {
  background: var(--primary-color);
  box-shadow: var(--shadow-sm);
  transform: none;
}

.cluster-profile-surface {
  display: flex;
  flex-direction: column;
  background: var(--admin-surface, #ffffff);
  border-radius: 0;
  overflow: hidden;
}

.profile-section-card {
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.tab-section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
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

.general-info-form, .location-form {
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
  margin-top: 6px;
}

.amenities-selector-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.amenity-chip-btn {
  height: 36px;
  padding: 0 14px;
  border-radius: 999px;
  border: 1px solid var(--admin-border-soft, rgba(255, 255, 255, 0.1));
  background: var(--admin-hover, rgba(255, 255, 255, 0.06));
  color: var(--admin-text, #f8fafc);
  font-size: 13px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: all 0.18s ease;
}

.amenity-chip-btn.active {
  background: #16a34a;
  color: #ffffff;
  border-color: #16a34a;
}

.owner-gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 14px;
}

.owner-gallery-item {
  position: relative;
  aspect-ratio: 4/3;
  border-radius: 8px;
  overflow: hidden;
  background: var(--admin-bg, #f1f5f9);
}

.owner-gallery-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.delete-img-btn {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: none;
  background: rgba(0, 0, 0, 0.6);
  color: #ffffff;
  font-size: 16px;
  line-height: 1;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.owner-gallery-empty {
  padding: 32px;
  text-align: center;
  color: var(--admin-muted, #94a3b8);
  background: var(--admin-bg, #f8fafc);
  border-radius: 8px;
  font-size: 13px;
}

.hidden-file-input {
  display: none;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>

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

      <!-- Hiển thị read-only -->
      <div class="info-readonly-grid">
        <div class="info-readonly-item">
          <span class="info-readonly-label">Tên cụm sân</span>
          <span class="info-readonly-value">{{ cluster?.name || '—' }}</span>
        </div>
        <div class="info-readonly-item">
          <span class="info-readonly-label">Số điện thoại liên hệ</span>
          <span class="info-readonly-value">{{ cluster?.phone_contact || '—' }}</span>
        </div>
        <div class="info-readonly-item info-readonly-full">
          <span class="info-readonly-label">Giới thiệu / Mô tả cụm sân</span>
          <span class="info-readonly-value">{{ cluster?.description || '—' }}</span>
        </div>
      </div>

    </div>

    <!-- Modal: Yêu cầu thay đổi thông tin -->
    <Teleport to="body">
      <div v-if="showInfoModal" class="cgi-modal-backdrop" @click.self="showInfoModal = false">
        <div class="cgi-modal">
          <div class="cgi-modal-header">
            <h3>Yêu cầu thay đổi thông tin cụm sân</h3>
            <button type="button" class="cgi-modal-close" @click="showInfoModal = false">×</button>
          </div>
          <form @submit.prevent="handleSubmitInfo" class="cgi-modal-body">
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Tên cụm sân</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  placeholder="Nhập tên cụm sân..."
                  required
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
                />
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Giới thiệu / Mô tả cụm sân</label>
              <textarea
                v-model="form.description"
                rows="4"
                class="form-control"
                placeholder="Mô tả đặc điểm, quy định hoặc lưu ý tại cụm sân..."
              ></textarea>
            </div>
            <div class="cgi-modal-footer">
              <button
                type="button"
                class="btn btn-outline"
                style="background-color: transparent !important; background: transparent !important; color: var(--admin-text, #475569) !important; border: 1px solid var(--admin-border, #cbd5e1) !important; box-shadow: none !important; transform: none !important;"
                @click="showInfoModal = false"
              >
                Hủy
              </button>
              <button
                type="submit"
                class="btn btn-primary"
                style="background-color: #22a653 !important; background: #22a653 !important; color: #ffffff !important; border: 1px solid #22a653 !important; box-shadow: none !important; transform: none !important;"
                :disabled="updating"
              >
                {{ updating ? 'Đang gửi...' : 'Gửi yêu cầu duyệt' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Section 2: Địa chỉ & Vị trí bản đồ -->
    <div class="profile-section-card">
      <div class="tab-section-header">
        <div>
          <h2>Vị trí & Tọa độ bản đồ</h2>
          <p class="section-subtitle">Địa chỉ chi tiết và tọa độ Google Maps hỗ trợ người chơi chỉ đường.</p>
        </div>
      </div>

      <!-- Hiển thị read-only -->
      <div class="info-readonly-grid">
        <div class="info-readonly-item">
          <span class="info-readonly-label">Tỉnh / Thành phố</span>
          <span class="info-readonly-value">{{ currentProvinceName || '—' }}</span>
        </div>
        <div class="info-readonly-item">
          <span class="info-readonly-label">Phường / Xã</span>
          <span class="info-readonly-value">{{ currentWardName || '—' }}</span>
        </div>
        <div class="info-readonly-item info-readonly-full">
          <span class="info-readonly-label">Địa chỉ chi tiết</span>
          <span class="info-readonly-value">{{ locationForm.address || '—' }}</span>
        </div>
      </div>

      <!-- Bản đồ preview -->
      <div class="map-preview-wrap">
        <div v-if="locationForm.latitude && locationForm.longitude" class="map-preview-embed">
          <iframe
            :src="`https://maps.google.com/maps?q=${locationForm.latitude},${locationForm.longitude}&z=16&output=embed`"
            width="100%"
            height="260"
            style="border:0; border-radius: 8px; pointer-events: none;"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
          ></iframe>
        </div>
        <div v-else class="map-placeholder-empty">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
            <circle cx="12" cy="9" r="2.5"/>
          </svg>
          <p>Chưa có tọa độ bản đồ.</p>
        </div>
      </div>

    </div>

    <!-- Modal: Yêu cầu thay đổi vị trí -->
    <Teleport to="body">
      <div v-if="showLocationModal" class="cgi-modal-backdrop" @click.self="showLocationModal = false">
        <div class="cgi-modal">
          <div class="cgi-modal-header">
            <h3>Yêu cầu thay đổi vị trí cụm sân</h3>
            <button type="button" class="cgi-modal-close" @click="showLocationModal = false">×</button>
          </div>
          <form @submit.prevent="handleSubmitLocation" class="cgi-modal-body">
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Tỉnh / Thành phố</label>
                <BaseCombobox
                  :model-value="currentProvinceCode"
                  :options="provinceOptions"
                  placeholder="Tìm Tỉnh/Thành phố..."
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
                  :disabled="!currentProvinceCode"
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
                />
                <button
                  type="button"
                  class="btn btn-outline"
                  :disabled="resolvingMap || !locationForm.map_url"
                  @click="$emit('resolve-map-url')"
                >
                  <span>{{ resolvingMap ? 'Đang trích xuất...' : 'Trích xuất tọa độ' }}</span>
                </button>
              </div>
              <span
                v-if="mapExtractMsg"
                class="help-text text-xs"
                :class="typeof mapExtractMsg === 'object' && mapExtractMsg.type === 'error' ? 'text-danger' : 'text-success'"
              >
                {{ typeof mapExtractMsg === 'object' ? mapExtractMsg.text : mapExtractMsg }}
              </span>
            </div>
            <!-- Map preview trong modal -->
            <div v-if="locationForm.latitude && locationForm.longitude" class="map-preview-wrap">
              <iframe
                :src="`https://maps.google.com/maps?q=${locationForm.latitude},${locationForm.longitude}&z=16&output=embed`"
                width="100%"
                height="200"
                style="border:0; border-radius: 8px; display:block; pointer-events: none;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
              ></iframe>
            </div>
            <div class="cgi-modal-footer">
              <button
                type="button"
                class="btn btn-outline"
                style="background-color: transparent !important; background: transparent !important; color: var(--admin-text, #475569) !important; border: 1px solid var(--admin-border, #cbd5e1) !important; box-shadow: none !important; transform: none !important;"
                @click="showLocationModal = false"
              >
                Hủy
              </button>
              <button
                type="submit"
                class="btn btn-primary"
                style="background-color: #22a653 !important; background: #22a653 !important; color: #ffffff !important; border: 1px solid #22a653 !important; box-shadow: none !important; transform: none !important;"
                :disabled="requestingLocation"
              >
                {{ requestingLocation ? 'Đang gửi...' : 'Gửi yêu cầu duyệt' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Section 3: Tiện ích cụm sân (Amenities) -->
    <div class="profile-section-card">
      <div class="tab-section-header">
        <div>
          <h2>Tiện ích cụm sân</h2>
          <p class="section-subtitle">Bật/tắt các tiện ích khả dụng tại sân để người chơi dễ tìm kiếm.</p>
        </div>
        <button
          type="button"
          class="btn btn-outline"
          :disabled="isClusterLocked"
          @click="showAmenitiesDrawer = true"
        >
          <span>Quản lý tiện ích ({{ selectedAmenitiesList.length }})</span>
        </button>
      </div>

      <div v-if="selectedAmenitiesList.length > 0" class="amenities-selector-grid">
        <div
          v-for="(amenity, idx) in selectedAmenitiesList"
          :key="getAmenityId(amenity) || idx"
          class="amenity-chip-btn active"
          style="cursor: default;"
        >
          <span>{{ getAmenityName(amenity) }}</span>
        </div>
      </div>
      <div v-else class="text-muted text-italic text-sm">
        Chưa chọn tiện ích nào. Nhấn <strong>"Quản lý tiện ích"</strong> để mở danh sách chọn.
      </div>
    </div>

    <!-- Slide-over Drawer (Giao diện trượt từ bên phải để chọn tiện ích) -->
    <Teleport to="body">
      <div v-if="showAmenitiesDrawer" class="cgi-drawer-backdrop" @click.self="showAmenitiesDrawer = false">
        <div class="cgi-drawer">
          <div class="cgi-drawer-header">
            <div>
              <h3>Quản lý tiện ích cụm sân</h3>
              <p class="section-subtitle text-xs" style="margin-top:2px;">Bật / tắt các tiện ích phục vụ người chơi</p>
            </div>
            <button type="button" class="cgi-modal-close" @click="showAmenitiesDrawer = false">×</button>
          </div>

          <div class="cgi-drawer-search">
            <input
              v-model="amenitySearch"
              type="text"
              class="form-control"
              placeholder="Tìm kiếm tiện ích..."
            />
          </div>

          <div class="cgi-drawer-body">
            <div v-if="filteredAmenities.length > 0" class="amenities-vertical-list">
              <div
                v-for="(amenity, idx) in filteredAmenities"
                :key="getAmenityId(amenity) || idx"
                class="amenity-vertical-item"
                :class="{ selected: isAmenitySelected(amenity) }"
                @click="$emit('toggle-amenity', getAmenityId(amenity))"
              >
                <div class="amenity-item-left">
                  <div class="amenity-icon-box" :class="{ active: isAmenitySelected(amenity) }">
                    <AppIcon :name="isAmenitySelected(amenity) ? 'check' : 'plus'" size="14" />
                  </div>
                  <span class="amenity-vertical-name">{{ getAmenityName(amenity) }}</span>
                </div>
                <div class="amenity-item-right">
                  <span
                    class="amenity-status-tag"
                    :class="isAmenitySelected(amenity) ? 'status-on' : 'status-off'"
                  >
                    {{ isAmenitySelected(amenity) ? 'Đang bật' : 'Chưa chọn' }}
                  </span>
                </div>
              </div>
            </div>
            <div v-else class="text-muted text-center text-sm" style="padding: 24px; text-align: center;">
              Không tìm thấy tiện ích nào.
            </div>
          </div>

          <div class="cgi-drawer-footer">
            <button
              type="button"
              class="btn btn-primary"
              style="background-color: #22a653 !important; background: #22a653 !important; color: #ffffff !important; border: 1px solid #22a653 !important; width: 100%; justify-content: center; transform: none !important; box-shadow: none !important;"
              @click="showAmenitiesDrawer = false"
            >
              Hoàn tất (Đã chọn {{ selectedAmenitiesList.length }})
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Section 4: Bộ sưu tập ảnh thực tế (Qua bước duyệt Admin) -->
    <div class="profile-section-card">
      <div class="tab-section-header">
        <div>
          <h2>Bộ sưu tập ảnh cụm sân</h2>
          <p class="section-subtitle">Hình ảnh thực tế của sân bãi. Mọi thay đổi hình ảnh sẽ được gửi Admin phê duyệt trước khi cập nhật.</p>
        </div>
        <button
          type="button"
          class="btn btn-outline"
          :disabled="isClusterLocked || hasPendingInfoRequest"
          @click="openGalleryModal"
        >
          <AppIcon name="image" size="15" />
          <span>{{ hasPendingInfoRequest ? "Đang chờ Admin duyệt" : "Gửi yêu cầu đổi ảnh" }}</span>
        </button>
      </div>

      <!-- Thông báo nếu có yêu cầu đang chờ duyệt -->
      <div v-if="hasPendingInfoRequest" class="cgi-pending-banner">
        <AppIcon name="clock" size="16" />
        <span>Đang có 1 yêu cầu thay đổi thông tin/hình ảnh đang chờ Admin xét duyệt.</span>
      </div>

      <div v-if="imagesList.length > 0" class="owner-gallery-grid">
        <div v-for="img in imagesList" :key="img.id" class="owner-gallery-item">
          <img :src="imgUrl(img.file_path)" alt="Hình ảnh cụm sân" class="owner-gallery-img" />
        </div>
      </div>
      <div v-else class="owner-gallery-empty">
        <AppIcon name="image" size="32" />
        <p>Chưa có hình ảnh nào trong bộ sưu tập.</p>
      </div>
    </div>

    <!-- Popup Modal: Gửi yêu cầu cập nhật Bộ sưu tập ảnh -->
    <Teleport to="body">
      <div v-if="showGalleryModal" class="cgi-modal-backdrop" @click.self="showGalleryModal = false">
        <div class="cgi-modal" style="max-width: 580px;">
          <div class="cgi-modal-header">
            <div>
              <h3>Yêu cầu cập nhật Bộ sưu tập ảnh</h3>
              <p class="section-subtitle text-xs" style="margin-top:2px;">Tải ảnh mới hoặc điều chỉnh ảnh bộ sưu tập để gửi Admin duyệt</p>
            </div>
            <button type="button" class="cgi-modal-close" @click="showGalleryModal = false">×</button>
          </div>

          <div class="cgi-modal-body">
            <!-- Nút chọn thêm ảnh mới -->
            <div style="margin-bottom: 16px;">
              <label class="form-label font-medium" style="display: block; margin-bottom: 6px; color: #0f172a;">Thêm ảnh mới vào bộ sưu tập đề xuất</label>
              <button
                type="button"
                class="btn btn-outline text-sm"
                :disabled="uploadingTempImage"
                style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; color: #0f172a; font-size: 13px; font-weight: 500; cursor: pointer;"
                @click="$refs.galleryTempInput.click()"
              >
                <AppIcon name="plus" size="14" />
                <span>{{ uploadingTempImage ? 'Đang nén & tải ảnh...' : 'Chọn ảnh mới (Auto WebP)' }}</span>
              </button>
              <input
                ref="galleryTempInput"
                type="file"
                class="hidden-file-input"
                accept="image/*"
                multiple
                @change="handleTempUpload"
              />
            </div>

            <!-- Danh sách ảnh bộ sưu tập đề xuất -->
            <div style="margin-bottom: 16px;">
              <label class="form-label font-medium" style="display: block; margin-bottom: 6px; color: #0f172a;">
                Danh sách ảnh đề xuất ({{ proposedImages.length }} ảnh)
              </label>
              <div v-if="proposedImages.length > 0" class="owner-gallery-grid" style="grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));">
                <div v-for="(img, idx) in proposedImages" :key="idx" class="owner-gallery-item">
                  <img :src="imgUrl(img.file_path || img.url)" alt="Ảnh đề xuất" class="owner-gallery-img" />
                  <button
                    type="button"
                    class="delete-img-btn"
                    title="Loại khỏi yêu cầu"
                    @click="removeProposedImage(idx)"
                  >
                    &times;
                  </button>
                  <span v-if="img.isTemp" class="amenity-status-tag status-on" style="position: absolute; bottom: 4px; left: 4px; font-size: 10px; padding: 2px 6px;">Mới</span>
                </div>
              </div>
              <div v-else style="color: #1e293b; font-size: 13.5px; font-weight: 400; padding: 8px 0;">
                Chưa có ảnh nào trong bộ sưu tập đề xuất.
              </div>
            </div>

            <!-- Lý do gửi Admin -->
            <div class="form-group mb-0">
              <label class="form-label font-medium" style="display: flex; align-items: center; gap: 4px; margin-bottom: 6px; color: #0f172a;">
                Lý do thay đổi hình ảnh
                <span style="color: #dc2626; font-weight: 500;">*</span>
              </label>
              <textarea
                v-model="galleryRequestNote"
                class="form-control"
                rows="3"
                style="width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; color: #0f172a; font-weight: 400; background: #ffffff; outline: none;"
                placeholder="Nhập lý do cập nhật hình ảnh bộ sưu tập (ví dụ: Bổ sung hình ảnh sân mới sửa sang...)"
                required
              ></textarea>
            </div>
          </div>

          <div class="cgi-modal-footer" style="padding: 16px 20px 20px;">
            <button
              type="button"
              class="btn btn-outline"
              style="padding: 8px 18px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; color: #0f172a; font-size: 13.5px; font-weight: 500; cursor: pointer;"
              @click="showGalleryModal = false"
            >
              Hủy
            </button>
            <button
              type="button"
              class="btn btn-primary"
              style="padding: 8px 20px; border: none; border-radius: 6px; background: #16a34a; color: #ffffff; font-size: 13.5px; font-weight: 500; cursor: pointer;"
              :style="{ opacity: (submittingGalleryRequest || uploadingTempImage || !galleryRequestNote.trim()) ? 0.5 : 1 }"
              :disabled="submittingGalleryRequest || uploadingTempImage || !galleryRequestNote.trim()"
              @click="submitGalleryRequest"
            >
              {{ submittingGalleryRequest ? 'Đang gửi...' : 'Gửi yêu cầu duyệt' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script>
import AppIcon from '../../AppIcon.vue';
import BaseCombobox from '../../BaseCombobox.vue';

export default {
  name: 'ClusterGeneralInfoTab',
  components: { AppIcon, BaseCombobox },
  props: {
    cluster: { type: Object, required: true },
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
    hasPendingInfoRequest: { type: Boolean, default: false },
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
    'submit-gallery-request',
    'upload-temp-media',
  ],
  data() {
    return {
      showInfoModal: false,
      showLocationModal: false,
      showAmenitiesDrawer: false,
      showGalleryModal: false,
      amenitySearch: '',
      proposedImages: [],
      galleryRequestNote: '',
      uploadingTempImage: false,
      submittingGalleryRequest: false,
    };
  },
  watch: {
    showInfoModal(newVal) {
      if (newVal && this.cluster) {
        this.form.name = this.cluster.name;
        this.form.phone_contact = this.cluster.phone_contact || '';
        this.form.description = this.cluster.description || '';
      }
    }
  },
  computed: {
    currentProvinceCode() {
      const raw = this.locationForm.new_province_code || this.locationForm.province_code || '';
      return typeof raw === 'object' && raw ? (raw.value || raw.id || raw.code || '') : raw;
    },
    currentWardCode() {
      const raw = this.locationForm.new_ward_code || this.locationForm.ward_code || '';
      return typeof raw === 'object' && raw ? (raw.value || raw.id || raw.code || '') : raw;
    },
    currentProvinceName() {
      const rawCode = this.locationForm.new_province_code || this.locationForm.province_code || '';
      // Nếu là object thì lấy label trực tiếp
      if (typeof rawCode === 'object' && rawCode) return rawCode.label || rawCode.name || '';
      const code = this.currentProvinceCode;
      if (!code) return this.locationForm.province_name || '';
      if (!this.provinceOptions.length) return this.locationForm.province_name || code;
      const opt = this.provinceOptions.find(o => (o.value || o.id || o) === code);
      return opt ? (opt.label || opt.name || '') : (this.locationForm.province_name || code);
    },
    currentWardName() {
      const rawCode = this.locationForm.new_ward_code || this.locationForm.ward_code || '';
      // Nếu là object thì lấy label trực tiếp
      if (typeof rawCode === 'object' && rawCode) return rawCode.label || rawCode.name || '';
      const code = this.currentWardCode;
      if (!code) return this.locationForm.ward_name || '';
      if (!this.wardOptions.length) return this.locationForm.ward_name || code;
      const opt = this.wardOptions.find(o => (o.value || o.id || o) === code);
      return opt ? (opt.label || opt.name || '') : (this.locationForm.ward_name || code);
    },
    filteredAmenities() {
      if (!this.amenitySearch) return this.availableAmenities;
      const q = this.amenitySearch.toLowerCase().trim();
      return this.availableAmenities.filter(item =>
        this.getAmenityName(item).toLowerCase().includes(q)
      );
    },
    selectedAmenitiesList() {
      if (!Array.isArray(this.availableAmenities)) return [];
      return this.availableAmenities.filter(item => this.isAmenitySelected(item));
    },
  },
  methods: {
    handleSubmitInfo() {
      this.$emit('submit-info');
      this.showInfoModal = false;
    },
    handleSubmitLocation() {
      this.$emit('submit-location-request');
      this.showLocationModal = false;
    },
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
      if (typeof filePath === 'object') filePath = filePath.file_path || filePath.url || '';
      if (!filePath) return '';
      if (filePath.startsWith('http://') || filePath.startsWith('https://')) return filePath;
      if (filePath.startsWith('/storage/')) return filePath;
      if (filePath.startsWith('storage/')) return `/${filePath}`;
      return `/storage/${filePath.replace(/^\/+/, '')}`;
    },
    openGalleryModal() {
      this.proposedImages = (this.imagesList || []).map(img => ({
        id: img.id,
        file_path: img.file_path,
        url: img.file_path,
        isTemp: false,
      }));
      this.galleryRequestNote = '';
      this.showGalleryModal = true;
    },
    async compressImageToWebP(file, quality = 0.82, maxWidth = 1920) {
      if (!file || !file.type.startsWith('image/')) return file;
      return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => {
          const img = new Image();
          img.onload = () => {
            let width = img.width;
            let height = img.height;
            if (width > maxWidth) {
              height = Math.round((height * maxWidth) / width);
              width = maxWidth;
            }
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);
            canvas.toBlob(
              (blob) => {
                if (!blob) {
                  resolve(file);
                  return;
                }
                const webpFileName = file.name.replace(/\.[^/.]+$/, '') + '.webp';
                const webpFile = new File([blob], webpFileName, {
                  type: 'image/webp',
                  lastModified: Date.now(),
                });
                resolve(webpFile);
              },
              'image/webp',
              quality
            );
          };
          img.onerror = () => resolve(file);
          img.src = e.target.result;
        };
        reader.onerror = () => resolve(file);
        reader.readAsDataURL(file);
      });
    },
    async handleTempUpload(e) {
      const files = Array.from(e.target.files);
      if (files.length === 0) return;
      this.uploadingTempImage = true;
      try {
        for (const file of files) {
          const compressed = await this.compressImageToWebP(file, 0.82, 1920);
          const formData = new FormData();
          formData.append('image', compressed);
          // Emit upload temp event to parent
          this.$emit('upload-temp-media', {
            formData,
            onSuccess: (resData) => {
              this.proposedImages.push({
                isTemp: true,
                file_path: resData.file_path,
                url: resData.url,
              });
            },
          });
        }
      } catch (err) {
        alert(err.message || 'Tải ảnh tạm thất bại.');
      } finally {
        this.uploadingTempImage = false;
        e.target.value = '';
      }
    },
    removeProposedImage(idx) {
      this.proposedImages.splice(idx, 1);
    },
    submitGalleryRequest() {
      if (!this.galleryRequestNote.trim()) {
        alert('Vui lòng nhập lý do thay đổi hình ảnh.');
        return;
      }
      this.submittingGalleryRequest = true;
      const imagePaths = this.proposedImages.map(img => img.file_path || img.url);
      this.$emit('submit-gallery-request', {
        images: imagePaths,
        note: this.galleryRequestNote.trim(),
      });
      this.submittingGalleryRequest = false;
      this.showGalleryModal = false;
    },
  },
};
</script>

<style scoped>
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

/* Read-only info display */
.info-readonly-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.info-readonly-full {
  grid-column: 1 / -1;
}

.info-readonly-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  background: var(--admin-bg, #f8fafc);
  border-radius: 8px;
}

.info-readonly-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--admin-muted, #94a3b8);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.info-readonly-value {
  font-size: 14px;
  color: var(--admin-text, #0f172a);
  line-height: 1.5;
  word-break: break-word;
}

/* Modal styles */
.cgi-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 16px;
}

.cgi-modal {
  background: var(--admin-surface, #ffffff);
  border-radius: 12px;
  width: 100%;
  max-width: 560px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  animation: cgi-modal-in 0.18s ease;
}

@keyframes cgi-modal-in {
  from { opacity: 0; transform: translateY(-12px) scale(0.98); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

.cgi-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px 14px;
  border-bottom: 1px solid var(--admin-border-soft, #e2e8f0);
}

.cgi-modal-header h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: var(--admin-text, #0f172a);
}

.cgi-modal-close {
  width: 30px;
  height: 30px;
  border: none;
  background: transparent;
  border-radius: 6px;
  font-size: 20px;
  cursor: pointer;
  color: var(--admin-muted, #94a3b8);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;
}

.cgi-modal-close:hover {
  background: var(--admin-hover, #f1f5f9);
  color: var(--admin-text, #0f172a);
}

.cgi-modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cgi-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 8px;
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
  border-top: none !important;
  padding-top: 0 !important;
}

.map-preview-wrap {
  border-radius: 8px;
  overflow: hidden;
}

.map-preview-embed iframe {
  display: block;
  width: 100%;
  border-radius: 8px;
}

.map-placeholder-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 32px 16px;
  background: var(--admin-bg, #f8fafc);
  border-radius: 8px;
  color: var(--admin-muted, #94a3b8);
  text-align: center;
  font-size: 13px;
}

.map-placeholder-empty svg {
  opacity: 0.4;
}

.map-placeholder-empty p {
  margin: 0;
  line-height: 1.5;
}

.map-placeholder-empty strong {
  color: var(--admin-text, #475569);
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

<template>
  <div class="sg-map-view-shell">
    <!-- LEFT SIDEBAR PANEL -->
    <aside class="sg-map-sidebar" :class="{ 'is-collapsed': isSidebarCollapsed }">
      <!-- VENUE DETAIL PANEL (Phẳng, Không Lớp Hộp Đè Hộp, 100% SVG Icon) -->
      <div v-if="selectedVenue" class="sg-venue-detail-panel">
        <!-- Header Top Bar -->
        <div class="sg-map-search-box">
          <div class="sg-map-search-input-wrap">
            <button type="button" class="sg-map-back-btn" @click="closeVenueDetail" title="Quay lại danh sách">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <input
              type="text"
              :value="selectedVenue.name"
              readonly
              class="sg-detail-header-input"
            />
            <button type="button" class="sg-map-clear-btn" @click="closeVenueDetail" title="Đóng">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
        </div>

        <div class="sg-detail-scroll-body">
          <!-- Hero Cover Banner -->
          <div class="sg-detail-hero-banner">
            <img :src="currentHeroImage" class="sg-hero-banner-img" alt="Ảnh sân thể thao" />
            <div class="sg-hero-gradient-overlay"></div>
            
            <button type="button" class="sg-hero-btn-back" @click="closeVenueDetail" title="Quay lại">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
            </button>

            <div class="sg-hero-actions-right">
              <button type="button" class="sg-hero-circle-btn" title="Chia sẻ" @click="openShareModal">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"/></svg>
              </button>
              <button type="button" class="sg-hero-circle-btn" title="Yêu thích">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
              </button>
            </div>

            <div class="sg-hero-bottom-meta">
              <span class="sg-hero-sport-tag">
                {{ selectedVenue.court_types?.[0]?.name || 'Cầu lông' }}
              </span>
              <div class="sg-hero-rating">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="#fbbf24" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <span>{{ selectedVenue.rating_avg ? `${selectedVenue.rating_avg} (${selectedVenue.rating_count || 0})` : 'Mới' }}</span>
              </div>
            </div>
          </div>

          <!-- BỘ SƯU TẬP ẢNH THỰC TẾ SÂN (Chỉ hiện khi có từ 2 ảnh trở lên từ DB) -->
          <div v-if="venueGallery.length > 1" class="sg-gallery-thumb-bar">
            <button
              v-for="(img, idx) in venueGallery"
              :key="idx"
              type="button"
              class="sg-gallery-thumb-btn"
              :class="{ 'is-active': currentHeroImage === img }"
              @click="activeImage = img"
            >
              <img :src="img" class="sg-thumb-img" alt="Ảnh nhỏ sân" />
            </button>
          </div>

          <!-- Venue Information Section (Phẳng, Trực Tiếp, Không Hộp Đè Hộp) -->
          <div class="sg-detail-main-info">
            <h3 class="sg-detail-title">{{ selectedVenue.name }}</h3>

            <div class="sg-detail-meta-list">
              <div class="sg-meta-item">
                <svg class="sg-meta-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <span>{{ selectedVenue.address || selectedVenue.district || 'Hà Nội' }}</span>
              </div>

              <div class="sg-meta-item">
                <svg class="sg-meta-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>{{ selectedVenue.open_time || '05:30' }} - {{ selectedVenue.close_time || '23:00' }}</span>
              </div>

              <div class="sg-meta-item" v-if="selectedVenue.phone_contact">
                <svg class="sg-meta-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                <span>{{ selectedVenue.phone_contact }}</span>
              </div>

              <!-- HÀNG GIÁ THUÊ ĐỒNG BỘ VỚI ĐỊA ĐIỂM & THỜI GIAN -->
              <div class="sg-meta-item">
                <svg class="sg-meta-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                <span>Giá từ: <span class="sg-meta-price-val">{{ priceLabel(selectedVenue) }}</span></span>
              </div>
            </div>

            <!-- NÚT ĐẶT SÂN THIẾT KẾ VỪA VẶN THANH LỊCH -->
            <div class="sg-cta-action-row">
              <RouterLink :to="`/venues/${selectedVenue.id}`" class="sg-btn-primary-cta">
                Đặt sân trực tuyến
              </RouterLink>
            </div>
          </div>

          <!-- Navigation Tabs Bar -->
          <div class="sg-detail-tabs-bar">
            <button
              v-for="tab in detailTabs"
              :key="tab.id"
              type="button"
              class="sg-detail-tab-btn"
              :class="{ 'is-active': activeTab === tab.id }"
              @click="activeTab = tab.id"
            >
              {{ tab.label }}
            </button>
          </div>

          <!-- Tab Content Body (Tối Ưu Bố Cục Thông Tin Tối Đa) -->
          <div class="sg-detail-tab-content">
            <!-- TAB 1: TỔNG QUAN (Mô tả, Tiện ích, Quy định sân) -->
            <div v-if="activeTab === 'info'" class="sg-tab-pane">
              <div class="sg-pane-block">
                <h4 class="sg-pane-subtitle">Mô tả địa điểm</h4>
                <p class="sg-pane-desc-text">
                  {{ selectedVenue.description || 'Sân thể thao chất lượng cao với đầy đủ trang thiết bị hiện đại, hệ thống chiếu sáng đạt chuẩn, đáp ứng nhu cầu tập luyện và thi đấu phong trào lẫn chuyên nghiệp.' }}
                </p>
              </div>

              <!-- Tiện ích nổi bật (Chỉ hiển thị khi sân có cấu hình tiện ích) -->
              <div v-if="venueAmenities.length" class="sg-pane-block" style="margin-top: 16px;">
                <h4 class="sg-pane-subtitle">Tiện ích nổi bật</h4>
                <div class="sg-amenities-flat-grid">
                  <div v-for="(amenity, idx) in venueAmenities" :key="idx" class="sg-amenity-flat-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span>{{ amenity.name || amenity }}</span>
                  </div>
                </div>
              </div>

              <!-- Quy định sử dụng (Căn phẳng lề trái 0px, Zero Padding) -->
              <div class="sg-pane-block" style="margin-top: 16px;">
                <h4 class="sg-pane-subtitle">Quy định chung</h4>
                <div class="sg-rules-list">
                  <div class="sg-rule-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Sử dụng trang phục & giày thể thao tiêu chuẩn.</span>
                  </div>
                  <div class="sg-rule-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Có mặt trước 10 phút để nhận sân đúng giờ đã đặt.</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 2: DỊCH VỤ & BẢNG GIÁ -->
            <div v-else-if="activeTab === 'services'" class="sg-tab-pane">
              <div class="sg-pane-block">
                <h4 class="sg-pane-subtitle">Dịch vụ đi kèm tại sân</h4>
                <div class="sg-services-simple-list">
                  <div class="sg-service-row">
                    <span>Cho thuê vợt & dụng cụ</span>
                    <span class="sg-service-price">từ 20.000 đ</span>
                  </div>
                  <div class="sg-service-row">
                    <span>Nước giải khát & Điện giải</span>
                    <span class="sg-service-price">từ 10.000 đ</span>
                  </div>
                  <div class="sg-service-row">
                    <span>Dịch vụ quấn cán & Bảo trì</span>
                    <span class="sg-service-price">từ 15.000 đ</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 3: ĐÁNH GIÁ (Điểm trung bình + Danh sách bình luận + Xem thêm) -->
            <div v-else-if="activeTab === 'reviews'" class="sg-tab-pane">
              <div class="sg-pane-block">
                <div class="sg-reviews-summary">
                  <span class="sg-review-score">{{ selectedVenue.rating_avg || '4.8' }}</span>
                  <div class="sg-review-score-info">
                    <div class="sg-review-stars">
                      <svg v-for="i in 5" :key="i" width="13" height="13" viewBox="0 0 24 24" fill="#fbbf24" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </div>
                    <span class="sg-review-count">Dựa trên {{ selectedVenue.rating_count || 12 }} đánh giá từ khách hàng</span>
                  </div>
                </div>

                <!-- DANH SÁCH BÌNH LUẬN DẠNG CARD PHẲNG -->
                <div class="sg-reviews-list">
                  <div class="sg-review-comment-item">
                    <div class="sg-comment-user-row">
                      <div class="sg-comment-avatar">NV</div>
                      <div class="sg-comment-meta">
                        <span class="sg-comment-author">Nguyễn Văn An</span>
                        <div class="sg-comment-stars">
                          <svg v-for="i in 5" :key="i" width="11" height="11" viewBox="0 0 24 24" fill="#fbbf24" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                          <span class="sg-comment-time">2 ngày trước</span>
                        </div>
                      </div>
                    </div>
                    <p class="sg-comment-text">Sân đẹp, thảm mới và hệ thống chiếu sáng rất tốt. Nhân viên nhiệt tình hỗ trợ đặt lịch nhanh chóng.</p>
                  </div>

                  <div class="sg-review-comment-item">
                    <div class="sg-comment-user-row">
                      <div class="sg-comment-avatar">TH</div>
                      <div class="sg-comment-meta">
                        <span class="sg-comment-author">Trần Hoàng Minh</span>
                        <div class="sg-comment-stars">
                          <svg v-for="i in 5" :key="i" width="11" height="11" viewBox="0 0 24 24" fill="#fbbf24" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                          <span class="sg-comment-time">1 tuần trước</span>
                        </div>
                      </div>
                    </div>
                    <p class="sg-comment-text">Bãi đỗ xe thoáng mát, có phòng thay đồ sạch sẽ. Đã chơi ở đây nhiều lần rất hài lòng.</p>
                  </div>

                </div>

                <!-- NÚT ĐIỀU HƯỚNG SANG TRANG CHI TIẾT ĐỂ XEM TẤT CẢ ĐÁNH GIÁ -->
                <RouterLink
                  :to="`/venues/${selectedVenue.id}?tab=reviews`"
                  class="sg-more-reviews-btn"
                >
                  <span>Xem tất cả {{ selectedVenue.rating_count || 12 }} đánh giá</span>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 4px;"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </RouterLink>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SEARCH & VENUES LIST PANEL (Hiển thị mặc định khi chưa chọn sân) -->
      <template v-else>
        <!-- Search Header -->
        <div class="sg-map-search-box">
          <div class="sg-map-search-input-wrap">
            <input
              v-model.trim="searchQuery"
              type="search"
              placeholder="Cầu lông, Pickleball, Bóng đá..."
              @input="onSearchInput"
            />
            <button v-if="searchQuery" type="button" class="sg-map-clear-btn" @click="clearSearch">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="sg-map-loading">
          <span>Đang tải dữ liệu bản đồ...</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredVenues.length === 0" class="sg-map-empty">
          <p>Không tìm thấy địa điểm phù hợp</p>
          <button type="button" class="sg-map-reset-btn" @click="resetFilters">Xóa tìm kiếm</button>
        </div>

        <!-- Venue Items List -->
        <div v-else class="sg-map-venue-list">
          <div class="sg-map-list-heading" aria-live="polite">
            <strong>{{ hasUserLocation ? 'Sân gần vị trí của bạn' : 'Sân gần khu vực bản đồ' }}</strong>
            <span>{{ filteredVenues.length }} địa điểm</span>
          </div>
          <article
            v-for="venue in filteredVenues"
            :key="venue.id"
            class="sg-map-sidebar-item"
            :class="{ 'is-selected': selectedVenue?.id === venue.id }"
            @click="selectVenue(venue)"
          >
            <div class="sg-sidebar-item-info">
              <h4 class="sg-sidebar-item-title">
                <span class="sg-sidebar-sport-icon" :style="{ color: getSportColor(venue.court_types?.[0]?.name) }">
                  <AppIcon :name="sportIconKey(venue)" size="16" />
                </span>
                <span>{{ venue.name }}</span>
              </h4>
              <p class="sg-sidebar-item-sub">
                <span class="sg-sidebar-dist" v-if="venue.computedDistance">({{ venue.computedDistance }}km)</span>
                {{ venue.address || venue.district || "Hà Nội" }}
              </p>
            </div>
          </article>
        </div>
      </template>
    </aside>

    <!-- MAIN MAP AREA -->
    <main class="sg-map-main">
      <!-- TOP FLOATING SPORTS CHIPS BAR (MULTI-LINE WRAPPING) -->
      <div class="sg-map-sports-bar">
        <button
          v-for="sport in sportsList"
          :key="sport.id"
          type="button"
          class="sg-map-sport-chip"
          :class="{ 'is-active': selectedSportId === sport.id }"
          @click="filterBySport(sport.id)"
        >
          <AppIcon :name="sport.iconKey || iconKeyFromName(sport.name)" size="16" />
          <span>{{ sport.name }}</span>
        </button>
      </div>

      <!-- LEAFLET MAP CONTAINER -->
      <div ref="mapContainer" class="sg-leaflet-map-canvas"></div>

      <!-- FLOATING ACTION BUTTONS (Góc dưới bên phải) -->
      <div class="sg-map-floating-controls">
        <button type="button" class="sg-map-fab-circle" title="Xem dạng danh sách" @click="goToList">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/>
          </svg>
        </button>
        <button type="button" class="sg-map-fab-circle is-primary" :class="{ 'is-loading': isLocating }" :disabled="isLocating" :aria-label="isLocating ? 'Đang lấy vị trí...' : 'Đưa bản đồ về vị trí của tôi'" :title="isLocating ? 'Đang lấy vị trí...' : 'Đưa bản đồ về vị trí của tôi'" @click="locateUser">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="7"/>
            <line x1="12" y1="2" x2="12" y2="5"/>
            <line x1="12" y1="19" x2="12" y2="22"/>
            <line x1="2" y1="12" x2="5" y2="12"/>
            <line x1="19" y1="12" x2="22" y2="12"/>
            <circle cx="12" cy="12" r="2.5" fill="currentColor"/>
          </svg>
        </button>
      </div>
    </main>

    <!-- SHARE MODAL POPUP (Mã QR + Đường dẫn chia sẻ) -->
    <Teleport to="body">
      <div v-if="showShareModal" class="sg-share-backdrop" @click.self="closeShareModal">
        <div class="sg-share-card">
          <div class="sg-share-card-header">
            <h3 class="sg-share-card-title">Chia sẻ sân thể thao</h3>
            <button type="button" class="sg-share-close-btn" @click="closeShareModal" title="Đóng">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <div class="sg-share-card-body">
            <!-- Mã QR Code -->
            <div class="sg-share-qr-section">
              <div class="sg-qr-frame">
                <img :src="qrCodeUrl" class="sg-qr-image" alt="Mã QR Đặt Sân" />
              </div>
              <p class="sg-qr-subtitle">Quét mã QR để đặt sân trực tiếp trên ứng dụng</p>
            </div>

            <!-- Khối Đường Dẫn & Sao Chép (Phẳng 100%, Không Khung Card) -->
            <div class="sg-share-link-group">
              <span class="sg-share-link-label">Đường dẫn chia sẻ</span>
              <div class="sg-share-link-flex">
                <span class="sg-share-url-text">{{ bookingUrl }}</span>
                <button type="button" class="sg-share-action-btn" :class="{ 'is-copied': isCopied }" @click="copyShareLink">
                  {{ isCopied ? 'Đã sao chép!' : 'Sao chép link' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script>
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import { api } from "../../services/api";
import { courtTypeService } from "../../services/courtTypes.js";
import AppIcon from "../../components/AppIcon.vue";
import { sportIconKeyFromName } from "../../utils/sportIcons.js";

// Default coordinates centered on Hanoi
const DEFAULT_LAT = 21.0285;
const DEFAULT_LNG = 105.8542;

export default {
  name: "ClientMapView",
  components: { AppIcon },
  data() {
    return {
      venues: [],
      courtTypes: [],
      searchQuery: "",
      selectedSportId: "all",
      selectedVenue: null,
      activeTab: "info",
      showShareModal: false,
      isCopied: false,
      activeImage: "",
      detailTabs: [
        { id: "info", label: "Tổng quan" },
        { id: "services", label: "Dịch vụ & Bảng giá" },
        { id: "reviews", label: "Đánh giá" },
      ],
      loading: true,
      isSidebarCollapsed: false,
      userLat: DEFAULT_LAT,
      userLng: DEFAULT_LNG,
      hasUserLocation: false,
      isLocating: false,
      userLocationLayer: null,
      map: null,
      markersGroup: null,
      isDraggingSports: false,
      sportsDragStartX: 0,
      sportsDragScrollLeft: 0,
    };
  },
  computed: {
    venueGallery() {
      if (!this.selectedVenue) return [];

      // Trả về danh sách hình ảnh thực tế của sân lấy từ cơ sở dữ liệu API
      if (Array.isArray(this.selectedVenue.gallery) && this.selectedVenue.gallery.length) {
        return this.selectedVenue.gallery.filter(Boolean);
      }
      if (Array.isArray(this.selectedVenue.images) && this.selectedVenue.images.length) {
        return this.selectedVenue.images.filter(Boolean);
      }

      const cover = this.selectedVenue.cover_image || this.selectedVenue.image_url || this.selectedVenue.avatar_url;
      return cover ? [cover] : [];
    },
    currentHeroImage() {
      if (this.activeImage) return this.activeImage;
      return this.venueGallery[0] || 'https://images.unsplash.com/photo-1546519638-68e109498ffc?w=800&auto=format&fit=crop';
    },
    venueAmenities() {
      if (!this.selectedVenue) return [];
      const list = this.selectedVenue.amenities_detail?.length
        ? this.selectedVenue.amenities_detail
        : (this.selectedVenue.amenities || []);

      if (!list.length) return [];
      return list.map((item) => (typeof item === "string" ? { name: item } : item));
    },
    qrCodeUrl() {
      if (!this.bookingUrl) return "";
      return `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(this.bookingUrl)}`;
    },
    sportsList() {
      const defaultSports = [
        { id: "all", name: "Tất cả", color: "#15803d", iconKey: "activity" },
        { id: "pickleball", name: "Pickleball", color: "#2563eb", iconKey: "pickleball" },
        { id: "badminton", name: "Cầu lông", color: "#16a34a", iconKey: "badminton" },
        { id: "football", name: "Bóng đá", color: "#15803d", iconKey: "football" },
        { id: "basketball", name: "Bóng rổ", color: "#ea580c", iconKey: "basketball" },
        { id: "tennis", name: "Quần vợt", color: "#d97706", iconKey: "tennis" },
        { id: "volleyball", name: "Bóng chuyền", color: "#0284c7", iconKey: "activity" },
      ];
      if (this.courtTypes.length === 0) return defaultSports;

      // Filter only main sports categories (parent_id is null/empty)
      const mainCategories = this.courtTypes.filter((ct) => !ct.parent_id);
      const targetList = mainCategories.length ? mainCategories : this.courtTypes;

      const dynamicSports = [
        { id: "all", name: "Tất cả", color: "#15803d", iconKey: "activity" },
        ...targetList.map((ct) => ({
          id: String(ct.id),
          name: ct.name,
          color: this.getSportColor(ct.name),
          iconKey: ct.icon_key || this.iconKeyFromName(ct.name),
        })),
      ];
      return dynamicSports;
    },
    filteredVenues() {
      let result = [...this.venues];

      // Filter by keyword query
      if (this.searchQuery) {
        const q = this.searchQuery.toLowerCase();
        result = result.filter(
          (v) =>
            v.name?.toLowerCase().includes(q) ||
            v.address?.toLowerCase().includes(q) ||
            v.district?.toLowerCase().includes(q)
        );
      }

      // Filter by sport type
      if (this.selectedSportId && this.selectedSportId !== "all") {
        const targetSport = this.sportsList.find((s) => String(s.id) === String(this.selectedSportId));
        const targetName = targetSport ? targetSport.name.toLowerCase() : String(this.selectedSportId).toLowerCase();

        result = result.filter((v) => {
          if (!v.court_types || v.court_types.length === 0) return true;
          return v.court_types.some((ct) => {
            const ctId = String(ct.id);
            const ctParentId = ct.parent_id ? String(ct.parent_id) : null;
            const ctName = (ct.name || "").toLowerCase();
            return (
              ctId === String(this.selectedSportId) ||
              ctParentId === String(this.selectedSportId) ||
              ctName.includes(targetName)
            );
          });
        });
      }

      // Compute distances and sort nearest
      return result.map((v, index) => {
        const lat = Number(v.latitude) || DEFAULT_LAT + (index % 7) * 0.015 - 0.045;
        const lng = Number(v.longitude) || DEFAULT_LNG + (index % 5) * 0.018 - 0.035;
        const distance = this.calcDistance(this.userLat, this.userLng, lat, lng);
        return {
          ...v,
          mapLat: lat,
          mapLng: lng,
          computedDistanceValue: distance,
          computedDistance: distance.toFixed(1),
        };
      }).sort((left, right) => left.computedDistanceValue - right.computedDistanceValue);
    },
    bookingUrl() {
      if (!this.selectedVenue) return "";
      return `${window.location.origin}/venues/${this.selectedVenue.id}`;
    },
  },
  async mounted() {
    this.initLeafletMap();
    await Promise.all([this.loadCourtTypes(), this.loadVenues()]);
  },
  beforeUnmount() {
    if (this.map) {
      this.map.remove();
      this.map = null;
    }
  },
  methods: {
    startSportsDrag(e) {
      const container = this.$refs.sportsBar;
      if (!container) return;
      this.isDraggingSports = true;
      this.sportsDragStartX = e.pageX - container.offsetLeft;
      this.sportsDragScrollLeft = container.scrollLeft;
    },
    stopSportsDrag() {
      this.isDraggingSports = false;
    },
    onSportsDrag(e) {
      if (!this.isDraggingSports) return;
      const container = this.$refs.sportsBar;
      if (!container) return;
      e.preventDefault();
      const x = e.pageX - container.offsetLeft;
      const walk = (x - this.sportsDragStartX) * 1.6;
      container.scrollLeft = this.sportsDragScrollLeft - walk;
    },
    onSportsWheel(e) {
      const container = this.$refs.sportsBar;
      if (!container) return;
      if (e.deltaY !== 0) {
        container.scrollLeft += e.deltaY * 0.8;
      }
    },
    scrollSportsBar(direction) {
      const container = this.$refs.sportsBar;
      if (!container) return;
      const scrollAmount = direction === "left" ? -220 : 220;
      container.scrollBy({ left: scrollAmount, behavior: "smooth" });
    },
    initLeafletMap() {
      if (!this.$refs.mapContainer) return;
      this.map = L.map(this.$refs.mapContainer, {
        scrollWheelZoom: true,
        zoomControl: false,
        minZoom: 4,
        maxBounds: [[-90, -180], [90, 180]],
        maxBoundsViscosity: 1.0,
      }).setView([this.userLat, this.userLng], 12);

      L.control.zoom({ position: "topright" }).addTo(this.map);

      // OpenStreetMap Tiles
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        minZoom: 4,
        maxZoom: 19,
        noWrap: true,
        bounds: [[-90, -180], [90, 180]],
      }).addTo(this.map);

      this.markersGroup = L.layerGroup().addTo(this.map);
      this.userLocationLayer = L.layerGroup().addTo(this.map);
    },
    async loadCourtTypes() {
      try {
        const res = await courtTypeService.getCourtTypes();
        this.courtTypes = res || [];
      } catch (e) {
        this.courtTypes = [];
      }
    },
    async loadVenues() {
      this.loading = true;
      try {
        const res = await api("/api/venues");
        this.venues = res.data || res || [];
      } catch (e) {
        this.venues = [];
      } finally {
        this.loading = false;
        this.renderMapMarkers();
      }
    },
    renderMapMarkers({ fitBounds = true } = {}) {
      if (!this.map || !this.markersGroup) return;
      this.markersGroup.clearLayers();
      this.userLocationLayer?.clearLayers();

      const bounds = [];

      this.filteredVenues.forEach((venue) => {
        const point = [venue.mapLat, venue.mapLng];
        bounds.push(point);

        const sportColor = this.getSportColor(venue.court_types?.[0]?.name);
        const sportIconSvg = this.getSportIconSvg(this.sportIconKey(venue));
        const logoSrc = this.getVenueLogoSrc(venue);
        const isSelected = this.selectedVenue?.id === venue.id;
        const safeName = this.escapeHtml(venue.name || "Cụm sân");

        const pinHtml = `
          <div class="sg-map-pinned-venue ${isSelected ? 'is-focused' : ''}" style="--pin-color: ${sportColor}">
            <!-- Nhãn Tên Sân Nổi Phía Trên Ghim (Font chữ thường, không dùng chữ đậm) -->
            <div class="sg-pin-label-top">
              ${safeName}
            </div>

            <!-- Ghim Giọt Nước SVG Liền Mạch 100% -->
            <div class="sg-single-teardrop-wrap">
              <svg class="sg-teardrop-svg" viewBox="0 0 44 56" fill="none">
                <path d="M22 2 C11 2 2 11 2 22 C2 33 18 51 21.2 54.4 C21.6 54.8 22.4 54.8 22.8 54.4 C26 51 42 33 42 22 C42 11 33 2 22 2 Z"
                      fill="var(--pin-color, #15803d)"
                      stroke="#ffffff"
                      stroke-width="2.5"
                      stroke-linejoin="round"/>
              </svg>
              <div class="sg-logo-circle-overlay">
                ${logoSrc ? `<img src="${this.escapeHtml(logoSrc)}" class="sg-pin-venue-logo-img" alt="${safeName}" onerror="this.style.display='none';" />` : ''}
                <span class="sg-pin-sport-icon" aria-hidden="true">${sportIconSvg}</span>
              </div>
            </div>
          </div>
        `;

        const customIcon = L.divIcon({
          className: "sg-custom-map-pin",
          html: pinHtml,
          iconSize: [160, 96],
          iconAnchor: [80, 96],
          popupAnchor: [0, -90],
        });

        const marker = L.marker(point, { icon: customIcon });

        marker.on("click", () => {
          this.selectVenue(venue);
        });

        this.markersGroup.addLayer(marker);
      });

      if (this.hasUserLocation && this.userLocationLayer) {
        const userMarker = L.circleMarker([this.userLat, this.userLng], {
          radius: 8,
          color: "#ffffff",
          weight: 3,
          fillColor: "#2563eb",
          fillOpacity: 1,
        }).bindTooltip("Vị trí của bạn", { direction: "top", offset: [0, -8] });
        this.userLocationLayer.addLayer(userMarker);
      }

      if (fitBounds && bounds.length > 0) {
        this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
      }
    },
    getVenueLogoSrc(venue) {
      if (!venue) return "";
      if (venue.logo_url) return venue.logo_url;
      if (venue.avatar_url) return venue.avatar_url;
      if (venue.image_url) return venue.image_url;
      if (venue.cover_image) return venue.cover_image;

      return "";
    },
    selectVenue(venue) {
      this.selectedVenue = venue;
      this.activeImage = "";
      if (this.map && venue.mapLat && venue.mapLng) {
        this.map.setView([venue.mapLat, venue.mapLng], 15, { animate: true });
      }
    },
    filterBySport(sportId) {
      this.selectedSportId = sportId;
      this.renderMapMarkers();
    },
    onSearchInput() {
      this.renderMapMarkers();
    },
    clearSearch() {
      this.searchQuery = "";
      this.renderMapMarkers();
    },
    resetFilters() {
      this.searchQuery = "";
      this.selectedSportId = "all";
      this.renderMapMarkers();
    },
    locateUser() {
      if (!navigator.geolocation || this.isLocating) return;

      this.isLocating = true;
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          this.userLat = pos.coords.latitude;
          this.userLng = pos.coords.longitude;
          this.hasUserLocation = true;
          this.renderMapMarkers({ fitBounds: false });
          if (this.map) {
            this.map.setView([this.userLat, this.userLng], 15, { animate: true });
          }
          this.isLocating = false;
        },
        () => {
          this.isLocating = false;
        },
        { enableHighAccuracy: true, maximumAge: 60000 }
      );
    },
    goToList() {
      this.$router.push({ name: "venues" });
    },
    iconKeyFromName(sportName) {
      return sportIconKeyFromName(sportName);
    },
    sportIconKey(venue) {
      const type = venue?.court_types?.find(Boolean);
      const explicitIcon = type?.icon_key;
      if (explicitIcon && explicitIcon !== "activity") return explicitIcon;
      return this.iconKeyFromName(type?.name || type?.parent?.name);
    },
    getSportIconSvg(iconKey) {
      const icons = {
        activity: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
        badminton: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="17" cy="7" r="3"/><path d="M15 9 5 19M8 22l9-9M3 20l2 2M9 6l4 4"/></svg>',
        pickleball: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="15" r="6"/><path d="M9 9c0-3 2-5 5-6M13 3l2-1M8 14h.01M12 12h.01M16 15h.01M12 17h.01"/></svg>',
        football: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 3 2 4 1 2 4-2 4 1 4-4 3-4-1-4 1-4-3 1-4-2-4 2-4 4-1 3-2z"/><path d="m12 8 2 2-1 3h-2l-1-3 2-2zM12 3v5M7 6l4 4M17 6l-4 4M4 10l6 3M20 10l-6 3M8 20l3-7M16 20l-3-7"/></svg>',
        basketball: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6c4.7 2 8.8 6.1 10.8 10.8M18.4 5.6c-4.7 2-8.8 6.1-10.8 10.8M3 12h18M12 3v18"/></svg>',
        tennis: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M5.5 5.5c2.7 1 4.1 3.1 4.1 6.5s-1.4 5.5-4.1 6.5M18.5 5.5c-2.7 1-4.1 3.1-4.1 6.5s1.4 5.5 4.1 6.5"/></svg>',
        volleyball: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M4.5 8.5c3.5-.4 6.4.9 8.6 3.8s2.6 5.9 1.2 8.2M7 4.2c2.1 2 3.1 4.8 2.8 8.2S8 18.4 5.8 20M15.7 3.8c-1.8 2.6-2.2 5.6-1 8.8s3.8 5 7.1 5.4"/></svg>',
      };
      return icons[iconKey] || icons.activity;
    },
    escapeHtml(value) {
      return String(value ?? "").replace(/[&<>"']/g, (character) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      }[character]));
    },
    getSportColor(sportName) {
      if (!sportName) return "#16a34a";
      const name = sportName.toLowerCase();
      if (name.includes("cầu lông")) return "#16a34a";
      if (name.includes("pickleball")) return "#2563eb";
      if (name.includes("bóng đá")) return "#15803d";
      if (name.includes("bóng rổ")) return "#ea580c";
      if (name.includes("tennis") || name.includes("quần vợt")) return "#d97706";
      if (name.includes("bóng chuyền") || name.includes("volleyball")) return "#7c3aed";
      return "#b91c1c";
    },
    priceLabel(venue) {
      if (!venue || !venue.min_price) return "Liên hệ";
      const formatted = new Intl.NumberFormat("vi-VN").format(venue.min_price);
      return `${formatted} đ/h`;
    },
    openShareModal() {
      this.showShareModal = true;
      this.isCopied = false;
    },
    closeShareModal() {
      this.showShareModal = false;
    },
    closeVenueDetail() {
      this.selectedVenue = null;
      this.activeTab = "info";
      this.showShareModal = false;
    },
    copyShareLink() {
      if (!this.bookingUrl) return;
      navigator.clipboard?.writeText(this.bookingUrl);
      this.isCopied = true;
      setTimeout(() => {
        this.isCopied = false;
      }, 2500);
    },
    getTabLabel(tabId) {
      const found = this.detailTabs.find((t) => t.id === tabId);
      return found ? found.label.toLowerCase() : "";
    },
    calcDistance(lat1, lon1, lat2, lon2) {
      const R = 6371; // km
      const dLat = ((lat2 - lat1) * Math.PI) / 180;
      const dLon = ((lon2 - lon1) * Math.PI) / 180;
      const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos((lat1 * Math.PI) / 180) *
          Math.cos((lat2 * Math.PI) / 180) *
          Math.sin(dLon / 2) *
          Math.sin(dLon / 2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      return R * c;
    },
  },
};
</script>

<style scoped>
.sg-map-view-shell {
  display: flex;
  width: 100%;
  height: calc(100vh - 70px);
  position: relative;
  overflow: hidden;
  background: #ffffff;
}

/* LEFT SIDEBAR */
.sg-map-sidebar {
  width: 380px;
  height: 100%;
  background: #ffffff;
  border-right: 1.5px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  z-index: 20;
  transition: transform 0.25s ease;
}

.sg-map-search-box {
  padding: 12px 16px;
}

.sg-map-search-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  border-radius: 9999px;
  padding: 4px 14px;
  gap: 6px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.sg-map-search-input-wrap:focus-within {
  border-color: #15803d;
  box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.18);
}

.sg-map-search-input-wrap input {
  width: 100%;
  border: none ;
  outline: none ;
  background: transparent;
  font-size: 13.5px;
  font-weight: 400;
  color: #0f172a;
}

.sg-map-clear-btn {
  background: transparent;
  border: none;
  font-size: 14px;
  color: #64748b;
  cursor: pointer;
}

.sg-map-loading,
.sg-map-empty {
  padding: 40px 20px;
  text-align: center;
  color: #475569;
  font-size: 14px;
}

.sg-map-reset-btn {
  margin-top: 12px;
  background: transparent;
  border: 1.5px solid #1e293b;
  border-radius: 6px;
  padding: 6px 14px;
  font-size: 13px;
  color: #0f172a;
  cursor: pointer;
}

.sg-map-reset-btn:focus-visible {
  outline: none;
  border-color: #15803d;
  box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.2);
}

.sg-map-venue-list {
  flex: 1;
  overflow-y: auto;
  padding: 0 12px 16px;
  display: flex;
  flex-direction: column;
}

.sg-map-list-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 8px 8px 6px;
  color: #475569;
  font-size: 12px;
}

.sg-map-list-heading strong {
  color: #0f172a;
  font-weight: 600;
}

.sg-map-list-heading span {
  white-space: nowrap;
  color: #64748b;
}

.sg-map-sidebar-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 8px;
  border-bottom: 1px solid #f1f5f9;
  background: #ffffff;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.sg-map-sidebar-item.is-selected,
.sg-map-sidebar-item:focus,
.sg-map-sidebar-item:focus-visible {
  background: #f8fafc;
  outline: none ;
  box-shadow: none ;
}

.sg-sidebar-logo-badge {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  flex-shrink: 0;
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.sg-sidebar-logo-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sg-sidebar-court-svg {
  width: 17px;
  height: 17px;
}

.sg-sidebar-item-info {
  flex: 1;
  min-width: 0;
}

.sg-sidebar-item-title {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 14px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sg-sidebar-sport-icon {
  width: 24px;
  height: 24px;
  display: inline-grid;
  place-items: center;
  flex: 0 0 auto;
  border-radius: 7px;
  background: #edf7f0;
}

.sg-sidebar-item-sub {
  font-size: 12.5px;
  color: #64748b;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sg-sidebar-dist {
  color: #475569;
  margin-right: 4px;
}

.sg-sidebar-dir-btn {
  background: transparent;
  border: 1px solid #cbd5e1;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #475569;
  cursor: pointer;
  flex-shrink: 0;
  font-size: 14px;
}

/* MAIN MAP */
.sg-map-main {
  flex: 1;
  height: 100%;
  position: relative;
}

.sg-leaflet-map-canvas {
  width: 100%;
  height: 100%;
}

/* TOP SPORTS CHIPS BAR (MULTI-LINE WRAPPING) */
.sg-map-sports-bar {
  position: absolute;
  top: 16px;
  left: 16px;
  right: 16px;
  z-index: 500;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  pointer-events: none;
  max-height: 40vh;
  overflow-y: auto;
  scrollbar-width: thin;
}

.sg-map-sport-chip {
  pointer-events: auto;
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  border-radius: 9999px;
  padding: 5px 16px 5px 6px;
  font-size: 13.5px;
  font-weight: 500;
  color: #334155;
  cursor: pointer;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
  transition: all 0.15s ease;
}

.sg-map-sport-chip:focus,
.sg-map-sport-chip:focus-visible {
  outline: none ;
  border-color: #15803d;
}

.sg-map-sport-chip.is-active {
  border-color: #15803d;
  background: #ffffff;
  color: #15803d;
  font-weight: 500;
  box-shadow: 0 2px 10px rgba(21, 128, 61, 0.15);
}

.sg-map-sport-chip > svg {
  flex: 0 0 auto;
}

.sg-chip-pin-badge {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  flex-shrink: 0;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
}

.sg-chip-svg {
  width: 15px;
  height: 15px;
}

/* FLOATING CONTROLS (Circle FABs bottom right) */
.sg-map-floating-controls {
  position: absolute;
  bottom: 24px;
  right: 24px;
  z-index: 500;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sg-map-fab-circle {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #ffffff;
  border: 1px solid #cbd5e1;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: #0f172a;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.sg-map-fab-circle:focus,
.sg-map-fab-circle:focus-visible {
  outline: none;
  border-color: #15803d;
  box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.25);
}

.sg-map-fab-circle.is-primary {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.sg-map-fab-circle.is-loading {
  cursor: wait;
  opacity: 0.7;
}

/* VENUE DETAIL PANEL STYLES (Single Flat Surface, Zero Bold Fonts, 100% SVG Icons) */
.sg-venue-detail-panel {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: #ffffff;
  overflow: hidden;
}

.sg-detail-header-input {
  width: 100%;
  border: none ;
  outline: none ;
  background: transparent;
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sg-detail-scroll-body {
  flex: 1;
  overflow-y: auto;
  background: #ffffff;
}

.sg-detail-hero-banner {
  position: relative;
  width: 100%;
  height: 180px;
  background: #0f172a;
  overflow: hidden;
}

.sg-hero-banner-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sg-hero-gradient-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(15, 23, 42, 0.4) 0%, rgba(15, 23, 42, 0.1) 40%, rgba(15, 23, 42, 0.75) 100%);
}

.sg-gallery-thumb-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: #ffffff;
  border-bottom: 1px solid #f1f5f9;
  overflow-x: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.sg-gallery-thumb-bar::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.sg-gallery-thumb-btn {
  width: 58px;
  height: 42px;
  border-radius: 6px;
  overflow: hidden;
  border: 2px solid transparent;
  padding: 0;
  background: #f1f5f9;
  cursor: pointer;
  flex-shrink: 0;
  transition: border-color 0.15s ease, opacity 0.15s ease;
  opacity: 0.7;
}

.sg-gallery-thumb-btn:hover {
  opacity: 1;
}

.sg-gallery-thumb-btn.is-active {
  border-color: #15803d;
  opacity: 1;
}

.sg-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sg-hero-btn-back {
  position: absolute;
  top: 12px;
  left: 12px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(4px);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #0f172a;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  line-height: 1;
  z-index: 5;
}

.sg-hero-actions-right {
  position: absolute;
  top: 12px;
  right: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  z-index: 5;
}

.sg-hero-circle-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(4px);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #334155;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  transition: background 0.15s ease;
}

.sg-hero-circle-btn:hover {
  background: #ffffff;
  color: #15803d;
}

.sg-hero-bottom-meta {
  position: absolute;
  bottom: 12px;
  left: 14px;
  right: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  z-index: 5;
}

.sg-hero-sport-tag {
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(4px);
  color: #15803d;
  font-size: 11.5px;
  font-weight: 500;
  padding: 3px 10px;
  border-radius: 9999px;
}

.sg-hero-rating {
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(4px);
  color: #ffffff;
  font-size: 12px;
  font-weight: 400;
  padding: 3px 10px;
  border-radius: 9999px;
}

.sg-detail-main-info {
  padding: 18px 16px 14px;
  background: #ffffff;
}

.sg-detail-title {
  font-size: 16.5px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 12px;
  line-height: 1.3;
}

.sg-detail-meta-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.sg-meta-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  color: #475569;
  line-height: 1.4;
  font-weight: 400;
}

.sg-meta-icon {
  flex-shrink: 0;
  margin-top: 2px;
}

.sg-detail-tabs-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 0 14px;
  background: #ffffff;
  border-top: 1px solid #f1f5f9;
  border-bottom: 1px solid #f1f5f9;
  overflow-x: auto;
  overflow-y: hidden;
  white-space: nowrap;
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none; /* IE 10+ */
}

.sg-detail-tabs-bar::-webkit-scrollbar {
  display: none; /* Chrome, Safari, Edge, Opera */
  width: 0;
  height: 0;
}

.sg-detail-tab-btn {
  background: transparent;
  border: none;
  padding: 10px 0;
  font-size: 12.5px;
  font-weight: 400;
  color: #64748b;
  cursor: pointer;
  position: relative;
  transition: color 0.15s ease;
  flex-shrink: 0;
}

.sg-detail-tab-btn:focus,
.sg-detail-tab-btn:focus-visible {
  outline: none ;
  box-shadow: none ;
}

.sg-detail-tab-btn.is-active {
  color: #15803d;
  font-weight: 400;
}

.sg-detail-tab-btn.is-active::after {
  content: "";
  position: absolute;
  bottom: -1px;
  left: 0;
  right: 0;
  height: 2px;
  background: #15803d;
  border-radius: 2px 2px 0 0;
}

.sg-detail-tab-content {
  padding: 18px 16px;
  background: #ffffff;
}

.sg-meta-price-val {
  color: #15803d;
  font-weight: 500;
}

.sg-cta-action-row {
  margin-top: 14px;
}

.sg-btn-primary-cta {
  display: block;
  width: 100%;
  text-align: center;
  background: linear-gradient(135deg, #15803d 0%, #166534 100%);
  color: #ffffff;
  font-size: 13px;
  font-weight: 500;
  padding: 8px 16px;
  border-radius: 6px;
  text-decoration: none;
  box-shadow: 0 2px 8px rgba(21, 128, 61, 0.18);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.sg-btn-primary-cta:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(21, 128, 61, 0.25);
}

.sg-pane-subtitle {
  font-size: 13px;
  font-weight: 500;
  color: #334155;
  margin: 0 0 8px;
}

.sg-booking-link-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px 12px;
  gap: 8px;
}

.sg-booking-url-link {
  font-size: 12.5px;
  color: #15803d;
  font-weight: 400;
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sg-copy-btn {
  background: transparent;
  border: none;
  font-size: 15px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 4px;
}

.sg-copy-btn:hover {
  background: #e2e8f0;
}

.sg-tab-empty-text {
  font-size: 13px;
  color: #94a3b8;
  text-align: center;
  padding: 30px 0;
  font-weight: 400;
}

.sg-pane-desc-text {
  font-size: 13.5px;
  line-height: 1.6;
  color: #475569;
  margin: 0;
  font-weight: 400;
}

/* OPTIMIZED TAB PANE STYLES */
.sg-amenities-flat-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px 12px;
}

.sg-amenity-flat-item {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12.5px;
  color: #334155;
  font-weight: 400;
}

.sg-rules-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0 ;
}

.sg-rule-item {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 12.5px;
  line-height: 1.5;
  color: #475569;
  font-weight: 400;
}

.sg-rule-item svg {
  flex-shrink: 0;
  margin-top: 2px;
}

.sg-services-simple-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sg-service-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12.5px;
  color: #334155;
  padding: 6px 0;
  border-bottom: 1px dashed #f1f5f9;
}

.sg-service-price {
  color: #15803d;
  font-weight: 500;
}

.sg-reviews-summary {
  display: flex;
  align-items: center;
  gap: 14px;
}

.sg-review-score {
  font-size: 28px;
  font-weight: 500;
  color: #0f172a;
  line-height: 1;
}

.sg-review-score-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.sg-review-stars {
  display: flex;
  align-items: center;
  gap: 2px;
}

.sg-review-count {
  font-size: 12px;
  color: #64748b;
}

.sg-reviews-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid #f1f5f9;
}

.sg-review-comment-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.sg-comment-user-row {
  display: flex;
  align-items: center;
  gap: 9px;
}

.sg-comment-avatar {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #15803d;
  font-size: 11px;
  font-weight: 500;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.sg-comment-meta {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.sg-comment-author {
  font-size: 12.5px;
  font-weight: 500;
  color: #0f172a;
}

.sg-comment-stars {
  display: flex;
  align-items: center;
  gap: 3px;
}

.sg-comment-time {
  font-size: 11px;
  color: #94a3b8;
  margin-left: 4px;
  font-weight: 400;
}

.sg-comment-text {
  font-size: 12.5px;
  color: #475569;
  line-height: 1.45;
  margin: 0;
  font-weight: 400;
}

.sg-more-reviews-btn {
  display: flex ;
  align-items: center ;
  justify-content: center ;
  gap: 6px ;
  box-sizing: border-box ;
  margin-top: 14px;
  width: 100%;
  padding: 8px 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  color: #15803d;
  font-size: 12.5px;
  font-weight: 500;
  text-decoration: none ;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.sg-more-reviews-btn:hover {
  background: #f1f5f9;
  border-color: #cbd5e1;
  color: #166534;
}

/* SHARE MODAL POPUP STYLES */
.sg-share-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  animation: sgFadeIn 0.2s ease;
}

@keyframes sgFadeIn {
  from { opacity: 0; transform: scale(0.96); }
  to { opacity: 1; transform: scale(1); }
}

.sg-share-card {
  width: 100%;
  max-width: 380px;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.25);
  overflow: hidden;
}

.sg-share-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
}

.sg-share-card-title {
  font-size: 15px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
}

.sg-share-close-btn {
  background: #f1f5f9;
  border: none;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  cursor: pointer;
  transition: background 0.15s ease;
}

.sg-share-close-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.sg-share-card-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}

.sg-share-qr-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.sg-qr-frame {
  padding: 12px;
  background: #ffffff;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  margin-bottom: 10px;
}

.sg-qr-image {
  width: 160px;
  height: 160px;
  display: block;
}

.sg-qr-subtitle {
  font-size: 12px;
  color: #64748b;
  margin: 0;
  font-weight: 400;
}

.sg-share-link-group {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sg-share-link-label {
  font-size: 12px;
  font-weight: 400;
  color: #475569;
}

.sg-share-link-flex {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
}

.sg-share-url-text {
  flex: 1;
  font-size: 13px;
  color: #15803d;
  font-weight: 400;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sg-share-action-btn {
  background: #15803d;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  padding: 7px 16px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s ease;
  white-space: nowrap;
  flex-shrink: 0;
}

.sg-share-action-btn.is-copied {
  background: #059669;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .sg-map-sidebar {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    height: 50%;
    border-right: none;
    border-top: 1.5px solid #1e293b;
  }

  .sg-map-sidebar.is-collapsed {
    transform: translateY(calc(100% - 50px));
  }
}
</style>

<style>
.sg-custom-map-pin {
  background: transparent ;
  border: none ;
}

.sg-map-pinned-venue {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  cursor: pointer;
  pointer-events: auto;
}

/* Floating Theme-Color Text Label Above Pin (Font chữ thường, KHÔNG dùng font đậm) */
.sg-pin-label-top {
  font-size: 12.5px;
  font-weight: 400;
  color: var(--pin-color, #15803d);
  text-align: center;
  line-height: 1.25;
  max-width: 160px;
  margin-bottom: 4px;
  white-space: normal;
  word-break: break-word;
  background: rgba(255, 255, 255, 0.94);
  padding: 2px 7px;
  border-radius: 4px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
  text-shadow:
    -1px -1px 0 #ffffff,
     1px -1px 0 #ffffff,
    -1px  1px 0 #ffffff,
     1px  1px 0 #ffffff;
  transition: color 0.18s ease;
}

/* Single Seamless Organic SVG Teardrop Pin (Zero separate triangles/circles) */
.sg-single-teardrop-wrap {
  position: relative;
  width: 44px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.28));
  transition: transform 0.18s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.sg-map-pinned-venue.is-focused .sg-single-teardrop-wrap,
.sg-map-pinned-venue:focus .sg-single-teardrop-wrap {
  transform: scale(1.22) translateY(-4px);
}

.sg-teardrop-svg {
  width: 44px;
  height: 56px;
  display: block;
}

.sg-logo-circle-overlay {
  position: absolute;
  top: 4px;
  left: 4px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: visible;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
}

.sg-pin-sport-icon {
  position: absolute;
  right: -4px;
  bottom: -2px;
  width: 17px;
  height: 17px;
  display: inline-grid;
  place-items: center;
  color: var(--pin-color, #15803d);
  background: #ffffff;
  border: 1px solid var(--pin-color, #15803d);
  border-radius: 50%;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.25);
  z-index: 2;
}

.sg-pin-sport-icon svg {
  width: 11px;
  height: 11px;
}

.sg-pin-venue-logo-img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
}

.sg-pin-fallback-badge {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #ffffff;
}

.sg-pin-fallback-badge svg {
  width: 20px;
  height: 20px;
}

.sg-map-popup-card {
  padding: 4px;
}

.sg-popup-title {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 4px;
}

.sg-popup-address {
  font-size: 12px;
  color: #475569;
  margin: 0 0 8px;
}

.sg-popup-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.sg-popup-price {
  font-size: 13px;
  font-weight: 600;
  color: #15803d;
}

.sg-popup-link {
  background: #15803d;
  color: #ffffff;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  text-decoration: none;
}
</style>

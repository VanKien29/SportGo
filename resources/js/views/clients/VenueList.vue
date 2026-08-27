<template>
  <div class="sg-venue-list-page">
    <PublicNavbar />

    <main class="sg-venue-main">


      <!-- ───── SEARCH & FILTER CONTENT AREA ───── -->
      <section class="sg-venue-body">
        <div class="sg-container-wide sg-venue-layout">
          <!-- FILTER SIDEBAR (FRAMELESS FLAT DESIGN) -->
          <aside class="sg-venue-sidebar">
            <div class="sg-sidebar-header">
              <h2 class="sg-sidebar-title">Bộ Lọc Tìm Kiếm</h2>
              <button type="button" class="sg-btn-reset" @click="resetFilters">Xóa bộ lọc</button>
            </div>

            <div class="sg-filter-group-list">
              <!-- Keyword input -->
              <div class="sg-filter-item">
                <label class="sg-filter-label">Từ khóa / Tên sân</label>
                <div class="sg-input-wrapper">
                  <input
                    v-model.trim="filters.q"
                    type="text"
                    placeholder="Ví dụ: Cầu lông Ba Đình..."
                    class="sg-text-input"
                    @keyup.enter="applyFilters"
                  />
                </div>
              </div>

              <!-- Court Type -->
              <div class="sg-filter-item">
                <label class="sg-filter-label">Môn thể thao</label>
                <ClientCombobox
                  v-model="filters.court_type_id"
                  :options="courtTypeOptions"
                  placeholder="Tất cả bộ môn"
                  @change="applyFilters"
                />
              </div>

              <!-- Province -->
              <div class="sg-filter-item">
                <label class="sg-filter-label">Tỉnh / Thành phố</label>
                <ClientCombobox
                  v-model="filters.province_code"
                  :options="provinceOptions"
                  placeholder="Tất cả Tỉnh/Thành"
                  @change="onProvinceChange"
                />
              </div>

              <!-- Ward -->
              <div class="sg-filter-item">
                <label class="sg-filter-label">Phường / Xã</label>
                <ClientCombobox
                  v-model="filters.ward_code"
                  :options="wardOptions"
                  placeholder="Tất cả Phường/Xã"
                  :disabled="!filters.province_code"
                  @change="onWardChange"
                />
              </div>

              <button type="button" class="sg-btn-apply-filters" @click="applyFilters">
                <span>Áp Dụng Tìm Kiếm</span>
              </button>
            </div>
          </aside>

          <!-- VENUES LIST GRID -->
          <div class="sg-venue-content">
            <div class="sg-content-toolbar">
              <span class="sg-result-count">Hiển thị {{ venues.length }} kết quả</span>
              <div class="sg-toolbar-actions">
                <router-link to="/venues/map" class="sg-toolbar-map-btn" title="Xem bản đồ trực quan">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                    <line x1="8" y1="2" x2="8" y2="18"/>
                    <line x1="16" y1="6" x2="16" y2="22"/>
                  </svg>
                  <span>Bản đồ</span>
                </router-link>

                <button
                  type="button"
                  class="sg-nearby-btn"
                  :class="{ 'is-active': filters.sort === 'distance' }"
                  :disabled="locationLoading"
                  @click="activateNearbySort"
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="3" />
                    <path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
                  </svg>
                  <span>{{ locationLoading ? 'Đang định vị...' : 'Sân gần tôi' }}</span>
                </button>
                <div class="sg-sort-combobox">
                <ClientCombobox
                  v-model="filters.sort"
                  :options="sortOptions"
                  placeholder="Sắp xếp"
                  @change="onSortChange"
                />
                </div>
              </div>
            </div>
            <p v-if="locationMessage" class="sg-location-message" role="status">{{ locationMessage }}</p>

            <!-- Loading Skeleton -->
            <div v-if="loading" class="alb-venue-grid">
              <div v-for="n in 6" :key="n" class="alb-venue-card sg-skeleton-card">
                <div class="alb-venue-card__thumb sg-skeleton-box"></div>
                <div class="alb-venue-card__body">
                  <div class="sg-skeleton-box" style="height: 18px; width: 75%; margin-bottom: 8px; border-radius: 4px;"></div>
                  <div class="sg-skeleton-box" style="height: 14px; width: 90%; margin-bottom: 16px; border-radius: 4px;"></div>
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                    <div class="sg-skeleton-box" style="height: 16px; width: 40%; border-radius: 4px;"></div>
                    <div class="sg-skeleton-box" style="height: 28px; width: 90px; border-radius: 6px;"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="venues.length === 0" class="sg-empty-state-wrapper">
              <div class="sg-empty-anim-container" style="width: 180px; height: 180px;">
                <img :src="'/images/sports_player_search_empty.png'" alt="Không tìm thấy sân" class="sg-empty-img" />
              </div>

              <h3 class="sg-empty-title">Không tìm thấy cụm sân phù hợp</h3>
              <p class="sg-empty-desc">Vui lòng thử điều chỉnh lại từ khóa tìm kiếm hoặc mở rộng phạm vi khu vực.</p>
              <button class="sg-btn-reset-empty" type="button" @click="resetFilters">Xem Tất Cả Cụm Sân</button>
            </div>

            <!-- Real Venues Grid -->
            <div v-else class="alb-venue-grid">
              <article v-for="venue in venues" :key="venue.id" class="alb-venue-card">
                <div class="alb-venue-card__thumb">
                  <img :src="venueImage(venue)" :alt="venue.name" loading="lazy" />
                </div>

                <div class="alb-venue-card__body">
                  <h3 class="alb-venue-card__title">{{ venue.name }}</h3>
                  <div class="alb-venue-card__address">
                    <span>{{ venue.address || venue.province || "Đang cập nhật địa chỉ" }}</span>
                    <span v-if="venue.distance_km !== undefined && venue.distance_km !== null" class="sg-venue-card__distance">
                      · {{ formatDistance(venue.distance_km) }}
                    </span>
                  </div>

                  <div class="alb-venue-card__footer">
                    <div class="alb-venue-card__price">
                      {{ priceLabel(venue) }}
                    </div>
                    <router-link
                      :to="{ name: 'venue-detail', params: { id: venue.slug || venue.id } }"
                      class="alb-venue-card__btn"
                    >
                      <span>Xem Lịch Sân</span>
                    </router-link>
                  </div>
                </div>
              </article>
            </div>
          </div>
        </div>
      </section>

    </main>

  </div>
</template>

<script>
import PublicNavbar from "../../components/PublicNavbar.vue";
import ClientCombobox from "../../components/ClientCombobox.vue";
import ClientDatePicker from "../../components/ClientDatePicker.vue";
import ClientTimeSlots from "../../components/ClientTimeSlots.vue";
import { courtTypeService } from "../../services/courtTypes.js";
import { venueService } from "../../services/venues.js";
import { api } from "../../services/api.js";

const fallbackImage = "/images/home/badminton-cover.webp";

function localDateString(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

export default {
  name: "VenueList",
  components: {
    PublicNavbar,
    ClientCombobox,
    ClientDatePicker,
    ClientTimeSlots,
  },
  data() {
    return {
      today: localDateString(),
      loading: true,
      venues: [],
      courtTypes: [],
      provincesList: [],
      wardsList: [],
      filters: {
        q: "",
        court_type_id: "",
        area: "",
        province_code: "",
        province_name: "",
        ward_code: "",
        ward_name: "",
        booking_date: localDateString(),
        start_time: "18:00:00",
        sort: "recommended",
      },
      userLatitude: null,
      userLongitude: null,
      locationLoading: false,
      locationMessage: "",
      timeOptions: ["06:00", "08:00", "10:00", "14:00", "16:00", "18:00", "20:00"],
    };
  },
  computed: {
    provinceOptions() {
      return [
        { value: "", label: "Tất cả Tỉnh/Thành" },
        ...this.provincesList.map((p) => ({ value: String(p.code), label: p.name })),
      ];
    },
    wardOptions() {
      return [
        { value: "", label: "Tất cả Phường/Xã" },
        ...this.wardsList.map((w) => ({ value: String(w.code), label: w.name })),
      ];
    },
    timeSlotOptions() {
      return this.timeOptions.map((t) => ({ value: `${t}:00`, label: t }));
    },
    courtTypeOptions() {
      return [
        { value: "", label: "Tất cả bộ môn" },
        ...this.courtTypes.map((c) => ({ value: String(c.id), label: c.name })),
      ];
    },
    sortOptions() {
      return [
        { value: "recommended", label: "Gợi ý phù hợp" },
        { value: "distance", label: "Gần tôi nhất" },
        { value: "price", label: "Giá thấp trước" },
        { value: "rating", label: "Đánh giá cao" },
      ];
    },
  },
  mounted() {
    this.loadProvinces();
    this.syncQueryToFilters();
    this.loadCourtTypes();
    if (this.filters.sort === "distance") {
      this.activateNearbySort();
    } else {
      this.loadVenues();
    }
  },
  methods: {
    async loadProvinces() {
      try {
        const res = await api("/api/locations/provinces");
        this.provincesList = res.data || res || [];

        if (this.filters.province_code) {
          this.fetchWardsForProvince(this.filters.province_code);
        }
      } catch (e) {
        this.provincesList = [];
      }
    },
    async fetchWardsForProvince(provinceCode) {
      if (!provinceCode) {
        this.wardsList = [];
        return;
      }
      try {
        const res = await api(`/api/locations/wards?province_code=${provinceCode}`);
        this.wardsList = res.data || res || [];
      } catch (e) {
        this.wardsList = [];
      }
    },
    async onProvinceChange() {
      this.filters.ward_code = "";
      this.filters.ward_name = "";
      this.wardsList = [];
      if (this.filters.province_code) {
        const p = this.provincesList.find((x) => String(x.code) === String(this.filters.province_code));
        this.filters.province_name = p ? p.name : "";
        await this.fetchWardsForProvince(this.filters.province_code);
      } else {
        this.filters.province_name = "";
      }
      this.applyFilters();
    },
    onWardChange() {
      const w = this.wardsList.find((x) => String(x.code) === String(this.filters.ward_code));
      this.filters.ward_name = w ? w.name : "";
      this.applyFilters();
    },
    syncQueryToFilters() {
      const q = this.$route.query;
      if (q.q) this.filters.q = q.q;
      if (q.court_type_id) this.filters.court_type_id = q.court_type_id;
      if (q.province_code) this.filters.province_code = q.province_code;
      if (q.ward_code) this.filters.ward_code = q.ward_code;
      if (q.area) this.filters.area = q.area;
      if (q.booking_date) this.filters.booking_date = q.booking_date;
      if (q.start_time) this.filters.start_time = q.start_time;
      if (q.sort) this.filters.sort = q.sort;
    },
    async loadCourtTypes() {
      try {
        const types = await courtTypeService.getCourtTypes();
        this.courtTypes = types || [];
      } catch (e) {
        this.courtTypes = [];
      }
    },
    async loadVenues() {
      this.loading = true;
      try {
        const params = { ...this.filters };
        if (params.sort === "distance" && this.userLatitude !== null && this.userLongitude !== null) {
          params.latitude = this.userLatitude;
          params.longitude = this.userLongitude;
        }
        const res = await venueService.list(params);
        this.venues = res.data || res || [];
      } catch (e) {
        this.venues = [];
      } finally {
        this.loading = false;
      }
    },
    applyFilters() {
      this.loadVenues();
    },
    onSortChange() {
      if (this.filters.sort === "distance") {
        this.activateNearbySort();
        return;
      }
      this.locationMessage = "";
      this.loadVenues();
    },
    activateNearbySort() {
      this.filters.sort = "distance";

      if (this.userLatitude !== null && this.userLongitude !== null) {
        this.locationMessage = "Đang hiển thị sân theo khoảng cách từ vị trí của bạn.";
        this.loadVenues();
        return;
      }

      if (!navigator.geolocation) {
        this.filters.sort = "recommended";
        this.locationMessage = "Trình duyệt không hỗ trợ định vị. Bạn có thể xem sân trên bản đồ để chọn khu vực.";
        this.loadVenues();
        return;
      }

      this.locationLoading = true;
      this.locationMessage = "Đang lấy vị trí để tìm sân gần nhất...";
      navigator.geolocation.getCurrentPosition(
        (position) => {
          this.userLatitude = position.coords.latitude;
          this.userLongitude = position.coords.longitude;
          this.locationLoading = false;
          this.locationMessage = "Đang hiển thị sân theo khoảng cách từ vị trí của bạn.";
          this.loadVenues();
        },
        () => {
          this.locationLoading = false;
          this.filters.sort = "recommended";
          this.locationMessage = "Không thể lấy vị trí. Hãy cho phép định vị để tìm sân gần nhất.";
          this.loadVenues();
        },
        { enableHighAccuracy: true, maximumAge: 60000 }
      );
    },
    resetFilters() {
      this.filters = {
        q: "",
        court_type_id: "",
        area: "",
        province_code: "",
        province_name: "",
        ward_code: "",
        ward_name: "",
        booking_date: localDateString(),
        start_time: "18:00:00",
        sort: "recommended",
      };
      this.wardsList = [];
      this.locationMessage = "";
      this.loadVenues();
    },
    venueImage(venue) {
      const img = venue.image_path || venue.cover_image || venue.thumbnail;
      if (img && !/kitchen|cabinet|furniture/i.test(img)) {
        if (/^https?:\/\//.test(img)) return img;
        return img.startsWith("/") ? img : `/storage/${img}`;
      }
      const pool = [
        "/images/home/badminton-cover.webp",
        "/images/home/anhbia2.webp",
        "/images/home/sportgo-home-hero-v2.webp",
        "/images/about_hero.png",
      ];
      const index = (venue.id || 0) % pool.length;
      return pool[index];
    },
    formatRating(venue) {
      const rating = Number(venue.rating_avg || venue.average_rating || 4.9);
      return rating.toFixed(1);
    },
    formatDistance(distance) {
      const value = Number(distance);
      if (!Number.isFinite(value)) return "";
      return value < 10 ? `${value.toFixed(1)} km` : `${Math.round(value)} km`;
    },
    priceLabel(venue) {
      if (!venue.min_price) return "Liên hệ";
      const formatted = new Intl.NumberFormat("vi-VN").format(venue.min_price);
      return `${formatted} đ/giờ`;
    },
  },
};
</script>

<style scoped>
/* =========================================================================
   VENUE LISTING PAGE DESIGN SYSTEM (CLEAN LIGHT & SPACE-OPTIMIZED)
   ========================================================================= */

.sg-venue-list-page {
  font-family: var(--sg-font-main, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
  background: #f8fafc;
  color: #0f172a;
  min-height: 100vh;
}

/* ───── HERO SECTION ───── */
.sg-venue-hero {
  padding: 64px 0 52px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.sg-venue-hero .sg-container-wide {
  display: block;
}

.sg-venue-hero-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(360px, 0.72fr);
  align-items: center;
  gap: 56px;
}

.sg-venue-hero-copy {
  min-width: 0;
}

.sg-venue-hero-media {
  position: relative;
  min-height: 248px;
  overflow: hidden;
  border-radius: 18px;
  background: #dbe9e0;
  box-shadow: 0 14px 34px rgba(15, 23, 42, 0.12);
}

.sg-venue-hero-media::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(5, 35, 23, 0.02) 28%, rgba(5, 35, 23, 0.64) 100%);
  pointer-events: none;
}

.sg-venue-hero-media img {
  display: block;
  width: 100%;
  height: 100%;
  min-height: 248px;
  object-fit: cover;
  object-position: center;
}

.sg-venue-hero-media__caption {
  position: absolute;
  z-index: 1;
  right: 18px;
  bottom: 16px;
  left: 18px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  color: #ffffff;
}

.sg-venue-hero-media__caption strong {
  font-size: 17px;
  font-weight: 650;
}

.sg-venue-hero-media__caption span {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.86);
}

.sg-venue-hero__eyebrow {
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin: 0 0 14px;
}

.sg-venue-hero__title {
  font-size: clamp(32px, 4.5vw, 56px);
  font-weight: 400;
  color: #0f172a;
  margin: 0 0 12px;
  letter-spacing: -0.02em;
  line-height: 1.1;
}

.sg-venue-hero__sub {
  font-size: 17px;
  font-weight: 400;
  color: #475569;
  margin: 0 0 36px;
  line-height: 1.6;
}

.sg-venue-search-wrap {
  width: 100%;
  max-width: 640px;
  margin-bottom: 22px;
}

.sg-venue-hero__links {
  display: flex;
  align-items: center;
  gap: 10px;
}

.sg-venue-hero__link {
  font-size: 13.5px;
  color: #64748b;
  text-decoration: none;
}

.sg-venue-hero__link:hover {
  color: #0f172a;
}

.sg-venue-hero__link-sep {
  color: #cbd5e1;
  font-size: 13px;
}

/* ───── MAIN CONTENT LAYOUT ───── */
.sg-venue-body {
  padding: 40px 0 80px;
}

.sg-venue-layout {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 36px;
  align-items: flex-start;
}

/* ───── SIDEBAR ───── */
.sg-venue-sidebar {
  background: transparent;
  padding: 0;
  align-self: flex-start;
}

.sg-sidebar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
}

.sg-sidebar-title {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

.sg-btn-reset {
  background: transparent;
  border: none;
  font-size: 13.5px;
  color: #5c7e6e;
  font-weight: 600;
  cursor: pointer;
}
.sg-btn-reset:hover {
  text-decoration: underline;
}

.sg-filter-group-list {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.sg-filter-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.sg-filter-label {
  font-size: 13.5px;
  font-weight: 600;
  color: #334155;
}

.sg-input-wrapper {
  width: 100%;
}

.sg-text-input {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid #cbd5e1;
  border-radius: 8px;
  font-size: 14px;
  color: #0f172a;
  background: #ffffff;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.sg-text-input:focus {
  border-color: #54656f;
  box-shadow: 0 0 0 3px rgba(84, 101, 111, 0.12);
}

.sg-btn-apply-filters {
  width: 100%;
  padding: 11px;
  background: #54656f;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  border: none;
  border-radius: 999px;
  cursor: pointer;
  margin-top: 6px;
  box-shadow: 0 4px 14px rgba(84, 101, 111, 0.25);
  transition: all 0.2s ease;
}
.sg-btn-apply-filters:hover {
  background: #405059;
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(84, 101, 111, 0.35);
}

/* ───── VENUE GRID TOOLBAR ───── */
.sg-venue-content {
  width: 100%;
}

.sg-content-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.sg-toolbar-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
}

.sg-result-count {
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
}

.sg-sort-combobox {
  min-width: 180px;
}

.sg-nearby-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 44px;
  padding: 0 16px;
  border: 1px solid #cbd5e1;
  border-radius: 9px;
  background: #ffffff;
  color: #334155;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  box-sizing: border-box;
  cursor: pointer;
  transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease;
}

.sg-nearby-btn:hover,
.sg-nearby-btn.is-active {
  border-color: #15803d;
  background: #effaf2;
  color: #166534;
}

.sg-nearby-btn:disabled {
  cursor: wait;
  opacity: 0.65;
}

.sg-location-message {
  margin: -12px 0 18px;
  color: #166534;
  font-size: 13px;
}

.sg-venue-card__distance {
  color: #166534;
  font-weight: 600;
  white-space: nowrap;
}

/* ───── EMPTY STATE ───── */
.sg-empty-state-wrapper {
  background: #ffffff;
  border-radius: 16px;
  padding: 60px 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.03);
}

.sg-empty-title {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin: 16px 0 8px;
}

.sg-empty-desc {
  font-size: 14.5px;
  color: #475569;
  max-width: 420px;
  margin: 0 0 24px;
  line-height: 1.6;
}

.sg-btn-reset-empty {
  padding: 10px 24px;
  background: #54656f;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  border: none;
  border-radius: 999px;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(84, 101, 111, 0.25);
  transition: all 0.2s ease;
}
.sg-btn-reset-empty:hover {
  background: #405059;
  transform: translateY(-1px);
}

/* ───── RESPONSIVE ───── */
@media (max-width: 1024px) {
  .sg-venue-hero-grid {
    grid-template-columns: 1fr;
    gap: 30px;
  }

  .sg-venue-hero-media {
    max-width: 560px;
  }

  .sg-venue-layout {
    grid-template-columns: 1fr;
    gap: 30px;
  }
}

@media (max-width: 640px) {
  .sg-venue-hero {
    padding: 40px 0 36px;
  }

  .sg-venue-hero-media,
  .sg-venue-hero-media img {
    min-height: 190px;
  }

  .sg-content-toolbar {
    align-items: flex-start;
    flex-direction: column;
    gap: 12px;
  }

  .sg-toolbar-actions {
    width: 100%;
  }

  .sg-nearby-btn,
  .sg-sort-combobox {
    flex: 1;
    min-width: 0;
  }
}

.sg-toolbar-map-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 44px;
  padding: 0 16px;
  border: 1px solid #cbd5e1;
  border-radius: 9px;
  background: #ffffff;
  color: #334155;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  text-decoration: none;
  box-sizing: border-box;
  box-shadow: none;
}

.sg-toolbar-map-btn:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  color: #334155;
}

</style>

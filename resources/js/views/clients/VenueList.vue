<template>
  <div class="sg-venue-list-page">
    <PublicNavbar />

    <main class="sg-venue-main">
      <!-- ───── HERO SECTION ───── -->
      <section class="sg-venue-hero">
        <div class="sg-container-wide">
          <h1 class="sg-venue-hero__title">Tìm Sân Thể Thao</h1>
          <p class="sg-venue-hero__sub">Chọn sân, chọn giờ, đặt lịch — nhanh chóng và tiện lợi.</p>
          <div class="sg-venue-search-wrap">
            <PillSearchBar />
          </div>
          <div class="sg-venue-hero__links">
            <router-link to="/bookings" class="sg-venue-hero__link">Lịch đã đặt của tôi</router-link>
            <span class="sg-venue-hero__link-sep">/</span>
            <router-link to="/favorites" class="sg-venue-hero__link">Sân yêu thích</router-link>
            <span class="sg-venue-hero__link-sep">/</span>
            <router-link to="/venues/map" class="sg-venue-hero__link">Xem bản đồ</router-link>
          </div>
        </div>
      </section>

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
              <div class="sg-sort-combobox">
                <ClientCombobox
                  v-model="filters.sort"
                  :options="sortOptions"
                  placeholder="Sắp xếp"
                  @change="applyFilters"
                />
              </div>
            </div>

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
import PillSearchBar from "../../components/PillSearchBar.vue";
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
    PillSearchBar,
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
        { value: "price", label: "Giá thấp trước" },
        { value: "rating", label: "Đánh giá cao" },
      ];
    },
  },
  mounted() {
    this.loadProvinces();
    this.syncQueryToFilters();
    this.loadCourtTypes();
    this.loadVenues();
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
        const res = await venueService.list(this.filters);
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
  display: flex;
  flex-direction: column;
  align-items: flex-start;
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

.sg-result-count {
  font-size: 15px;
  font-weight: 600;
  color: #0f172a;
}

.sg-sort-combobox {
  min-width: 180px;
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
  .sg-venue-layout {
    grid-template-columns: 1fr;
    gap: 30px;
  }
}
</style>

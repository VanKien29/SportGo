<template>
  <div class="venue-market-page">
    <PublicNavbar />

    <main>
      <section class="market-hero">
        <div class="market-container">
          <div class="breadcrumbs">
            <router-link :to="{ name: 'home' }">Trang chủ</router-link>
            <span>/</span>
            <strong>Tìm sân</strong>
          </div>

          <h1>Tìm sân thể thao</h1>
          <p>{{ loading ? "Đang tải cơ sở..." : `Tìm thấy ${sortedVenues.length} cơ sở phù hợp` }}</p>

          <form class="market-search" @submit.prevent="applyFilters">
            <label>
              <span>Từ khóa</span>
              <div class="field-control">
                <svg class="field-leading" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m21 21-4.35-4.35"/>
                  <circle cx="11" cy="11" r="7"/>
                </svg>
                <input
                  v-model.trim="filters.q"
                  type="search"
                  placeholder="Tên sân, khu vực..."
                />
              </div>
            </label>

            <label>
              <span>Loại sân</span>
              <div class="field-control">
                <svg class="field-leading" viewBox="0 0 24 24" aria-hidden="true">
                  <rect x="4" y="4" width="6" height="6" rx="1.5"/>
                  <rect x="14" y="4" width="6" height="6" rx="1.5"/>
                  <rect x="4" y="14" width="6" height="6" rx="1.5"/>
                  <rect x="14" y="14" width="6" height="6" rx="1.5"/>
                </svg>
                <select v-model="filters.court_type_id">
                  <option value="">Tất cả loại sân</option>
                  <option v-for="type in courtTypes" :key="type.id" :value="type.id">
                    {{ type.name }}
                  </option>
                </select>
                <svg class="field-action" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m6 9 6 6 6-6"/>
                </svg>
              </div>
            </label>

            <label>
              <span>Khu vực</span>
              <div class="field-control">
                <svg class="field-leading" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20 10c0 5.2-8 11-8 11S4 15.2 4 10a8 8 0 1 1 16 0Z"/>
                  <circle cx="12" cy="10" r="2.5"/>
                </svg>
                <input
                  v-model.trim="filters.area"
                  type="text"
                  placeholder="Quận, phường, tỉnh thành..."
                />
              </div>
            </label>

            <label>
              <span>Thời gian chơi</span>
              <BookingDateTimePicker
                v-model:date="filters.booking_date"
                v-model:time="filters.start_time"
                :min-date="today"
                :time-options="timeOptions"
                compact
              />
            </label>

            <button type="submit">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35"/><circle cx="11" cy="11" r="7"/></svg>
              Tìm sân trống
            </button>
          </form>
        </div>
      </section>

      <section class="market-container market-layout">
        <aside class="filter-rail">
          <section class="filter-card">
            <div class="filter-card-head">
              <h2>Loại sân</h2>
              <button type="button" @click="clearCourtType">Xóa lọc</button>
            </div>

            <button
              type="button"
              class="sport-filter"
              :class="{ active: !filters.court_type_id }"
              @click="setCourtType('')"
            >
              <img :src="viewAllIcon" alt="" />
              <span>Tất cả</span>
            </button>

            <button
              v-for="type in courtTypes"
              :key="type.id"
              type="button"
              class="sport-filter"
              :class="{ active: String(filters.court_type_id) === String(type.id) }"
              @click="setCourtType(type.id)"
            >
              <img v-if="sportIconFor(type.name)" :src="sportIconFor(type.name)" alt="" />
              <span v-else class="sport-letter">{{ type.name.slice(0, 1) }}</span>
              <span>{{ type.name }}</span>
            </button>
          </section>

          <section class="filter-card">
            <h2>Sắp xếp</h2>
            <div class="field-control">
              <svg class="field-leading" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 7h12M4 12h9M4 17h6"/>
                <path d="m17 15 3 3 3-3"/>
              </svg>
              <select v-model="sortMode">
                <option value="name">Tên A-Z</option>
                <option value="courts">Nhiều sân nhất</option>
                <option value="price">Giá thấp trước</option>
              </select>
              <svg class="field-action" viewBox="0 0 24 24" aria-hidden="true">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </div>
          </section>

          <section class="filter-card compact-card">
            <h2>Bản đồ</h2>
            <p>Xem nhanh các sân theo vị trí khi dữ liệu tọa độ đã sẵn sàng.</p>
            <button type="button" @click="viewMode = 'map'">Xem bản đồ</button>
          </section>
        </aside>

        <section class="results-panel">
          <div class="results-toolbar">
            <div>
              <strong>{{ sortedVenues.length }} cơ sở</strong>
              <span>{{ activeCourtLabel }}</span>
            </div>
            <div class="view-toggle" aria-label="Chế độ xem">
              <button type="button" :class="{ active: viewMode === 'list' }" @click="viewMode = 'list'">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
              </button>
              <button type="button" :class="{ active: viewMode === 'map' }" @click="viewMode = 'map'">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18 3 21V6l6-3 6 3 6-3v15l-6 3z"/><path d="M9 3v15M15 6v15"/></svg>
              </button>
            </div>
          </div>

          <div v-if="viewMode === 'map'" class="map-placeholder">
            <div>
              <h2>Bản đồ sân quanh bạn</h2>
              <p>SportGo đã có dữ liệu danh sách sân. Phần bản đồ nên nối tiếp bằng tọa độ sân và định vị người dùng.</p>
              <button type="button" @click="viewMode = 'list'">Quay lại danh sách</button>
            </div>
          </div>

          <div v-else-if="loading" class="state-card">
            <div class="spinner"></div>
            <p>Đang tải danh sách sân...</p>
          </div>

          <div v-else-if="error" class="state-card error-card">
            <p>{{ error }}</p>
            <button type="button" @click="loadVenues">Thử lại</button>
          </div>

          <div v-else-if="sortedVenues.length === 0" class="state-card">
            <p>Không tìm thấy sân phù hợp với bộ lọc hiện tại.</p>
            <button type="button" @click="resetFilters">Xóa bộ lọc</button>
          </div>

          <div v-else class="venue-list">
            <article v-for="venue in sortedVenues" :key="venue.id" class="venue-row">
              <button type="button" class="venue-thumb" @click="goDetail(venue)" :aria-label="`Xem ${venue.name}`">
                <img :src="venueImage(venue)" :alt="venue.name" @error="hideBrokenImage" />
                <span>{{ initials(venue.name) }}</span>
              </button>

              <div class="venue-main">
                <div class="venue-title-line">
                  <div>
                    <h2>{{ venue.name }}</h2>
                    <p>
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                      {{ venue.address || venue.province || "Đang cập nhật địa chỉ" }}
                    </p>
                  </div>
                  <strong>{{ courtCount(venue) }} sân</strong>
                </div>

                <div class="venue-meta">
                  <span v-for="name in courtTypeNames(venue)" :key="name">{{ name }}</span>
                  <span v-if="venue.min_price">{{ formatCurrency(venue.min_price) }}/giờ</span>
                  <span v-if="venue.rating_avg || venue.average_rating">★ {{ venue.rating_avg || venue.average_rating }}</span>
                </div>

                <div class="venue-actions">
                  <button type="button" class="detail-btn" @click="goDetail(venue)">Chi tiết</button>
                  <button type="button" class="book-btn" @click="goBooking(venue)">Đặt sân</button>
                </div>
              </div>
            </article>
          </div>
        </section>
      </section>
    </main>
  </div>
</template>

<script>
import BookingDateTimePicker from "../../components/BookingDateTimePicker.vue";
import PublicNavbar from "../../components/PublicNavbar.vue";
import { courtTypeService } from "../../services/courtTypes.js";
import { venueService } from "../../services/venues.js";

const sportIconBase = "/images/home/sports-icons";
const fallbackImage = "/images/home/badminton-cover.webp";

export default {
  name: "VenueList",
  components: { BookingDateTimePicker, PublicNavbar },
  data() {
    const today = new Date().toISOString().split("T")[0];
    return {
      venues: [],
      courtTypes: [],
      viewAllIcon: `${sportIconBase}/viewall.webp`,
      today,
      loading: true,
      error: "",
      sortMode: "name",
      viewMode: this.$route.query.view === "map" ? "map" : "list",
      filters: {
        q: "",
        court_type_id: "",
        area: "",
        min_rating: "",
        booking_date: today,
        start_time: "18:00:00",
        end_time: "19:00:00",
      },
      timeOptions: [
        "05:00", "06:00", "07:00", "08:00", "09:00", "10:00",
        "11:00", "12:00", "13:00", "14:00", "15:00", "16:00",
        "17:00", "18:00", "19:00", "20:00", "21:00",
      ],
    };
  },
  computed: {
    sortedVenues() {
      const venues = [...this.venues];
      if (this.sortMode === "courts") {
        return venues.sort((a, b) => this.courtCount(b) - this.courtCount(a));
      }
      if (this.sortMode === "price") {
        return venues.sort((a, b) => Number(a.min_price || 999999999) - Number(b.min_price || 999999999));
      }
      return venues.sort((a, b) => String(a.name || "").localeCompare(String(b.name || ""), "vi"));
    },
    activeCourtLabel() {
      if (!this.filters.court_type_id) return "Tất cả loại sân";
      const type = this.courtTypes.find((item) => String(item.id) === String(this.filters.court_type_id));
      return type?.name || "Loại sân đã chọn";
    },
  },
  async mounted() {
    this.filters = {
      ...this.filters,
      q: this.$route.query.q || "",
      court_type_id: this.$route.query.court_type_id || "",
      area: this.$route.query.area || "",
      min_rating: this.$route.query.min_rating || "",
      booking_date: this.$route.query.booking_date || this.filters.booking_date,
      start_time: this.$route.query.start_time || this.filters.start_time,
      end_time: this.$route.query.end_time || this.filters.end_time,
    };
    await Promise.all([this.loadCourtTypes(), this.loadVenues()]);
  },
  methods: {
    async loadCourtTypes() {
      try {
        const response = await courtTypeService.getAll();
        this.courtTypes = (response.data || []).filter((type) => type.is_active !== false && !type.parent_id);
      } catch {
        this.courtTypes = [];
      }
    },
    async loadVenues() {
      this.loading = true;
      this.error = "";
      try {
        const response = await venueService.list(this.filters);
        this.venues = response.data || [];
      } catch (error) {
        this.error = error.message || "Không thể tải danh sách sân.";
      } finally {
        this.loading = false;
      }
    },
    applyFilters() {
      this.filters.end_time = this.endTimeFromStart(this.filters.start_time);
      this.$router.replace({ name: "venues", query: this.cleanQuery() });
      this.loadVenues();
    },
    endTimeFromStart(time) {
      const [hour, minute] = String(time || "18:00:00").slice(0, 5).split(":").map(Number);
      return `${String(Math.min(hour + 1, 24)).padStart(2, "0")}:${String(minute).padStart(2, "0")}:00`;
    },
    cleanQuery() {
      return Object.fromEntries(
        Object.entries({ ...this.filters, view: this.viewMode === "map" ? "map" : "" })
          .filter(([, value]) => value !== undefined && value !== null && value !== ""),
      );
    },
    setCourtType(id) {
      this.filters.court_type_id = id;
      this.applyFilters();
    },
    clearCourtType() {
      this.setCourtType("");
    },
    resetFilters() {
      const today = new Date().toISOString().split("T")[0];
      this.filters = {
        q: "",
        court_type_id: "",
        area: "",
        min_rating: "",
        booking_date: today,
        start_time: "18:00:00",
        end_time: "19:00:00",
      };
      this.viewMode = "list";
      this.applyFilters();
    },
    goDetail(venue) {
      this.$router.push({ name: "venue-detail", params: { id: venue.slug || venue.id } });
    },
    goBooking(venue) {
      this.$router.push({
        name: "booking-create",
        query: {
          venue_cluster_id: venue.id,
          booking_date: this.filters.booking_date,
          start_time: this.filters.start_time,
          end_time: this.filters.end_time,
        },
      });
    },
    courtCount(venue) {
      return Number(venue.court_count || venue.venue_courts_count || venue.venue_courts?.length || 0);
    },
    courtTypeNames(venue) {
      const names = (venue.court_types || []).map((type) => type.name).filter(Boolean);
      return names.length ? names.slice(0, 3) : ["Đa môn"];
    },
    sportIconFor(name = "") {
      const normalized = name.toLowerCase();
      if (normalized.includes("cầu lông")) return `${sportIconBase}/badminton.webp`;
      if (normalized.includes("bóng đá")) return `${sportIconBase}/football.webp`;
      if (normalized.includes("pickleball")) return `${sportIconBase}/pickleball.webp`;
      if (normalized.includes("tennis") || normalized.includes("quần vợt")) return `${sportIconBase}/tennis.webp`;
      if (normalized.includes("bóng rổ")) return `${sportIconBase}/basketball.webp`;
      if (normalized.includes("bóng bàn")) return `${sportIconBase}/bongban.webp`;
      return "";
    },
    initials(name = "") {
      return String(name).trim().slice(0, 2).toUpperCase() || "SG";
    },
    imageUrl(path) {
      if (!path) return "";
      if (/^https?:\/\//.test(path)) return path;
      return `/storage/${path}`;
    },
    venueImage(venue) {
      return this.imageUrl(venue.image_path || venue.cover_image || venue.thumbnail) || fallbackImage;
    },
    hideBrokenImage(event) {
      event.target.style.display = "none";
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
        maximumFractionDigits: 0,
      }).format(Number(amount || 0));
    },
  },
};
</script>

<style scoped>
.venue-market-page {
  min-height: 100vh;
  background: #f5f7f6;
  color: #101828;
}

main {
  padding-top: 64px;
}

svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 2;
}

.market-container {
  max-width: 1296px;
  margin: 0 auto;
  padding: 0 28px;
}

.market-hero {
  position: relative;
  padding: 46px 0 74px;
  background: linear-gradient(110deg, #102820 0%, #0d7d48 100%);
  color: #fff;
  overflow: visible;
}

.market-hero::after {
  content: "";
  position: absolute;
  left: 0;
  right: 0;
  bottom: -1px;
  height: 38px;
  background: #f5f7f6;
  clip-path: ellipse(70% 55% at 50% 100%);
}

.breadcrumbs {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  color: rgba(255, 255, 255, .75);
  font-size: 14px;
  font-weight: 700;
}

.breadcrumbs a {
  color: inherit;
  text-decoration: none;
}

.market-hero h1 {
  position: relative;
  z-index: 1;
  margin: 18px 0 8px;
  font-size: 36px;
  line-height: 1.15;
  font-weight: 950;
}

.market-hero p {
  position: relative;
  z-index: 1;
  margin: 0 0 24px;
  color: rgba(255, 255, 255, .86);
  font-size: 16px;
  font-weight: 700;
}

.market-search {
  position: relative;
  z-index: 1;
  display: grid;
  grid-template-columns: 1.1fr .95fr 1fr 1fr 150px;
  gap: 12px;
  padding: 16px;
  border: 1px solid rgba(255, 255, 255, .18);
  border-radius: 12px;
  background: rgba(255, 255, 255, .13);
}

.market-search label {
  display: grid;
  gap: 7px;
}

.market-search span {
  color: rgba(255, 255, 255, .78);
  font-size: 12px;
  font-weight: 850;
}

.market-search input,
.market-search select,
.filter-card select {
  width: 100%;
  height: 44px;
  border: 1px solid #d9e1dd;
  border-radius: 8px;
  padding: 0 40px;
  background: #fff;
  color: #111827;
  font-size: 14px;
  font-weight: 700;
  outline: none;
  appearance: none;
  -webkit-appearance: none;
}

.field-control {
  position: relative;
  display: flex;
  align-items: center;
}

.field-leading,
.field-action {
  position: absolute;
  z-index: 2;
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  pointer-events: none;
}

.field-leading {
  left: 13px;
  color: #0b7a46;
}

.field-action {
  right: 13px;
  color: #5e6f64;
}

.market-search input[type="date"]::-webkit-calendar-picker-indicator {
  position: absolute;
  right: 0;
  width: 44px;
  height: 44px;
  opacity: 0;
  cursor: pointer;
}

.market-search input[type="search"]::-webkit-search-decoration,
.market-search input[type="search"]::-webkit-search-cancel-button {
  display: none;
}

.market-search input:focus,
.market-search select:focus,
.filter-card select:focus {
  border-color: #0d8c51;
  box-shadow: 0 0 0 3px rgba(13, 140, 81, .14);
}

.market-search button,
.compact-card button,
.state-card button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 44px;
  border-radius: 8px;
  background: #0d8c51;
  color: #fff;
  font-weight: 900;
}

.market-layout {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  gap: 24px;
  padding-top: 40px;
  padding-bottom: 56px;
}

.filter-rail {
  display: grid;
  align-content: start;
  gap: 16px;
}

.filter-card {
  padding: 18px;
  border: 1px solid #e1e7e4;
  border-radius: 12px;
  background: #fff;
}

.filter-card h2 {
  margin: 0 0 14px;
  color: #111827;
  font-size: 16px;
  font-weight: 950;
}

.filter-card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.filter-card-head h2 {
  margin: 0;
}

.filter-card-head button {
  color: #0d8c51;
  font-size: 12px;
  font-weight: 850;
}

.sport-filter {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  min-height: 42px;
  margin-top: 8px;
  padding: 0 10px;
  border: 1px solid #e0e6e3;
  border-radius: 8px;
  color: #344039;
  font-size: 14px;
  font-weight: 800;
  text-align: left;
}

.sport-filter.active {
  border-color: #17a663;
  background: #dff8e9;
  color: #05603a;
}

.sport-filter img,
.sport-letter {
  width: 22px;
  height: 22px;
  object-fit: contain;
}

.sport-letter {
  display: grid;
  place-items: center;
  border-radius: 7px;
  background: #edf2ef;
  color: #0d8c51;
  font-size: 12px;
}

.compact-card p {
  margin: -4px 0 14px;
  color: #66756d;
  font-size: 13px;
  line-height: 1.5;
}

.compact-card button {
  width: 100%;
}

.results-panel {
  min-width: 0;
}

.results-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 64px;
  margin-bottom: 18px;
  padding: 0 16px 0 20px;
  border: 1px solid #e1e7e4;
  border-radius: 12px;
  background: #fff;
}

.results-toolbar strong {
  display: block;
  font-size: 16px;
  font-weight: 950;
}

.results-toolbar span {
  display: block;
  margin-top: 3px;
  color: #66756d;
  font-size: 13px;
  font-weight: 750;
}

.view-toggle {
  display: flex;
  gap: 8px;
}

.view-toggle button {
  display: grid;
  width: 36px;
  height: 36px;
  place-items: center;
  border: 1px solid #dfe7e3;
  border-radius: 8px;
  color: #66756d;
}

.view-toggle button.active {
  border-color: #0d8c51;
  background: #0d8c51;
  color: #fff;
}

.venue-list {
  display: grid;
  gap: 12px;
}

.venue-row {
  display: grid;
  grid-template-columns: 132px minmax(0, 1fr);
  min-height: 126px;
  overflow: hidden;
  border: 1px solid #e1e7e4;
  border-radius: 12px;
  background: #fff;
}

.venue-thumb {
  position: relative;
  overflow: hidden;
  background: #dfe9e4;
}

.venue-thumb img {
  position: absolute;
  inset: 0;
  z-index: 1;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.venue-thumb span {
  display: grid;
  width: 100%;
  height: 100%;
  place-items: center;
  color: #0d8c51;
  font-size: 26px;
  font-weight: 950;
}

.venue-main {
  display: grid;
  padding: 14px 16px;
}

.venue-title-line {
  display: flex;
  justify-content: space-between;
  gap: 18px;
}

.venue-title-line h2 {
  margin: 0;
  color: #101828;
  font-size: 16px;
  font-weight: 950;
}

.venue-title-line p {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 7px 0 0;
  color: #526159;
  font-size: 13px;
  line-height: 1.4;
}

.venue-title-line p svg {
  width: 15px;
  min-width: 15px;
  color: #0d8c51;
}

.venue-title-line > strong {
  color: #0d8c51;
  font-size: 13px;
  font-weight: 950;
  white-space: nowrap;
}

.venue-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 12px 0;
}

.venue-meta span {
  display: inline-flex;
  align-items: center;
  min-height: 26px;
  padding: 0 9px;
  border: 1px solid #e1e7e4;
  border-radius: 7px;
  color: #46564d;
  font-size: 12px;
  font-weight: 800;
}

.venue-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 12px;
  border-top: 1px solid #edf2ef;
}

.detail-btn,
.book-btn {
  min-height: 32px;
  padding: 0 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 900;
}

.detail-btn {
  border: 1px solid #0d8c51;
  color: #0d8c51;
}

.book-btn {
  margin-left: auto;
  background: #0d8c51;
  color: #fff;
}

.state-card,
.map-placeholder {
  display: grid;
  min-height: 260px;
  place-items: center;
  border: 1px solid #e1e7e4;
  border-radius: 12px;
  background: #fff;
  text-align: center;
}

.state-card p,
.map-placeholder p {
  max-width: 480px;
  margin: 10px auto 18px;
  color: #66756d;
  font-weight: 750;
  line-height: 1.6;
}

.map-placeholder h2 {
  margin: 0;
  color: #111827;
  font-size: 22px;
  font-weight: 950;
}

.error-card {
  border-color: #fecaca;
  background: #fff7f7;
  color: #b42318;
}

.spinner {
  width: 34px;
  height: 34px;
  margin: 0 auto;
  border: 3px solid #dce8e1;
  border-top-color: #0d8c51;
  border-radius: 50%;
  animation: spin .8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 980px) {
  .market-search,
  .market-layout {
    grid-template-columns: 1fr;
  }

  .filter-rail {
    position: static;
  }
}

@media (max-width: 640px) {
  main {
    padding-top: 58px;
  }

  .market-container {
    padding: 0 18px;
  }

  .market-hero {
    padding: 32px 0 58px;
  }

  .market-hero h1 {
    font-size: 30px;
  }

  .market-search {
    padding: 12px;
  }

  .market-layout {
    padding-top: 28px;
    padding-bottom: 36px;
  }

  .venue-row {
    grid-template-columns: 104px minmax(0, 1fr);
  }

  .venue-title-line {
    display: grid;
    gap: 8px;
  }

  .venue-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .book-btn {
    width: 100%;
    margin-left: 0;
  }
}
</style>

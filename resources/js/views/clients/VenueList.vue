<template>
  <div class="venue-market-page">
    <PublicNavbar />

    <main>
      <section class="search-band">
        <div class="market-container">
          <nav class="breadcrumbs" aria-label="Duong dan">
            <router-link :to="{ name: 'home' }">Trang chủ</router-link>
            <span>/</span>
            <strong>Tìm sân</strong>
          </nav>

          <div class="search-heading">
            <div>
              <h1>Tìm sân thể thao</h1>
              <p>{{ loading ? "Đang tải danh sách sân..." : `${venues.length} sân phù hợp với bộ lọc hiện tại` }}</p>
            </div>
            <router-link :to="{ name: 'booking-history' }" class="history-link">Lịch đặt sân</router-link>
          </div>

          <form class="search-form" @submit.prevent="applyFilters">
            <label class="field field-wide">
              <span>Từ khóa</span>
              <input v-model.trim="filters.q" type="search" placeholder="Tên sân, địa chỉ, khu vực" />
            </label>

            <label class="field">
              <span>Loại sân</span>
              <select v-model="filters.court_type_id">
                <option value="">Tất cả loại sân</option>
                <option v-for="type in courtTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
              </select>
            </label>

            <label class="field">
              <span>Khu vực</span>
              <input v-model.trim="filters.area" type="text" placeholder="Quận, phường, tỉnh thành" />
            </label>

            <label class="field date-field">
              <span>Giờ trống</span>
              <BookingDateTimePicker
                v-model:date="filters.booking_date"
                v-model:time="filters.start_time"
                :min-date="today"
                :time-options="timeOptions"
                compact
              />
            </label>

            <button class="search-submit" type="submit">Tìm sân</button>
          </form>
        </div>
      </section>

      <section class="market-container market-layout">
        <aside class="filter-rail" aria-label="Bo loc tim san">
          <section class="filter-panel">
            <div class="filter-head">
              <h2>Bộ lọc</h2>
              <button type="button" @click="resetFilters">Xóa lọc</button>
            </div>

            <div class="quick-types">
              <button
                type="button"
                :class="{ active: !filters.court_type_id }"
                @click="setCourtType('')"
              >
                Tất cả
              </button>
              <button
                v-for="type in courtTypes"
                :key="type.id"
                type="button"
                :class="{ active: String(filters.court_type_id) === String(type.id) }"
                @click="setCourtType(type.id)"
              >
                {{ type.name }}
              </button>
            </div>

            <label class="field">
              <span>Giá từ</span>
              <input v-model.number="filters.min_price" min="0" step="10000" type="number" placeholder="0đ" />
            </label>

            <label class="field">
              <span>Giá đến</span>
              <input v-model.number="filters.max_price" min="0" step="10000" type="number" placeholder="300000đ" />
            </label>

            <label class="field">
              <span>Đánh giá tối thiểu</span>
              <select v-model="filters.min_rating">
                <option value="">Không giới hạn</option>
                <option value="4.5">Từ 4.5 sao</option>
                <option value="4">Từ 4 sao</option>
                <option value="3">Từ 3 sao</option>
              </select>
            </label>

            <button class="apply-side" type="button" @click="applyFilters">Áp dụng bộ lọc</button>
          </section>

        </aside>

        <section class="results-panel">
          <div class="results-toolbar">
            <div>
              <strong>{{ venues.length }} kết quả</strong>
              <span>{{ activeFilterLabel }}</span>
            </div>
            <div class="toolbar-actions">
              <select v-model="filters.sort" @change="applyFilters">
                <option value="recommended">Gợi ý phù hợp</option>
                <option value="price">Giá thấp trước</option>
                <option value="rating">Đánh giá cao</option>
                <option value="courts">Nhiều sân nhất</option>
                <option value="name">Tên A-Z</option>
              </select>
              <div class="view-toggle" aria-label="Che do xem">
                <button type="button" :class="{ active: viewMode === 'list' }" aria-label="Xem danh sách" @click="setViewMode('list')">
                  <AppIcon name="menu" size="17" />
                </button>
                <button type="button" :class="{ active: viewMode === 'map' }" aria-label="Xem bản đồ" @click="setViewMode('map')">
                  <AppIcon name="mapPin" size="17" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="viewMode === 'map'" class="map-panel">
            <VenueResultsMap v-if="venuesWithLocation.length" :venues="venuesWithLocation" @select="goDetail" />
            <div v-else class="map-empty">Chưa có tọa độ cho các sân trong bộ lọc này.</div>
            <button class="back-list" type="button" @click="setViewMode('list')">Quay lại danh sách</button>
          </div>

          <div v-else-if="loading" class="state-panel">
            <div class="spinner"></div>
            <p>Đang tải danh sách sân...</p>
          </div>

          <div v-else-if="error" class="state-panel error-panel">
            <p>{{ error }}</p>
            <button type="button" @click="loadVenues">Thử lại</button>
          </div>

          <div v-else-if="venues.length === 0" class="state-panel">
            <p>Không tìm thấy sân phù hợp. Hãy nới giá, khu vực hoặc đổi khung giờ.</p>
            <button type="button" @click="resetFilters">Xóa bộ lọc</button>
          </div>

          <div v-else class="venue-list">
            <article v-for="venue in venues" :key="venue.id" class="venue-row">
              <button type="button" class="venue-thumb" @click="goDetail(venue)" :aria-label="`Xem ${venue.name}`">
                <img :src="venueImage(venue)" :alt="venue.name" @error="hideBrokenImage" />
                <span>{{ initials(venue.name) }}</span>
              </button>

              <div class="venue-main">
                <div class="venue-title-line">
                  <div>
                    <h2>{{ venue.name }}</h2>
                    <p>{{ venue.address || venue.ward || venue.province || "Đang cập nhật địa chỉ" }}</p>
                  </div>
                  <strong>{{ formatRating(venue) }}</strong>
                </div>

                <div class="venue-meta">
                  <span>{{ courtCount(venue) }} sân hoạt động</span>
                  <span v-for="name in courtTypeNames(venue)" :key="name">{{ name }}</span>
                  <span>{{ priceLabel(venue) }}</span>
                  <span class="available-chip">Có lịch trống theo giờ chọn</span>
                </div>

                <div class="venue-actions">
                  <button type="button" class="detail-btn" @click="goDetail(venue)">Chi tiết sân</button>
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
import AppIcon from "../../components/AppIcon.vue";
import BookingDateTimePicker from "../../components/BookingDateTimePicker.vue";
import PublicNavbar from "../../components/PublicNavbar.vue";
import VenueResultsMap from "../../components/VenueResultsMap.vue";
import { courtTypeService } from "../../services/courtTypes.js";
import { venueService } from "../../services/venues.js";

const fallbackImage = "/images/home/badminton-cover.webp";

export default {
  name: "VenueList",
  components: { AppIcon, BookingDateTimePicker, PublicNavbar, VenueResultsMap },
  data() {
    const today = new Date().toISOString().slice(0, 10);
    return {
      venues: [],
      courtTypes: [],
      today,
      loading: true,
      error: "",
      viewMode: this.$route.query.view === "map" ? "map" : "list",
      filters: {
        q: "",
        court_type_id: "",
        area: "",
        min_price: "",
        max_price: "",
        min_rating: "",
        booking_date: today,
        start_time: "18:00:00",
        end_time: "19:00:00",
        sort: "recommended",
      },
      timeOptions: [
        "05:00", "06:00", "07:00", "08:00", "09:00", "10:00",
        "11:00", "12:00", "13:00", "14:00", "15:00", "16:00",
        "17:00", "18:00", "19:00", "20:00", "21:00", "22:00",
      ],
    };
  },
  computed: {
    activeFilterLabel() {
      const parts = [];
      const type = this.courtTypes.find((item) => String(item.id) === String(this.filters.court_type_id));
      if (type) parts.push(type.name);
      if (this.filters.area) parts.push(this.filters.area);
      if (this.filters.min_price || this.filters.max_price) parts.push(this.priceRangeLabel);
      parts.push(`${this.filters.booking_date} lúc ${String(this.filters.start_time).slice(0, 5)}`);
      return parts.join(" · ");
    },
    priceRangeLabel() {
      const min = this.filters.min_price ? this.formatCurrency(this.filters.min_price) : "0đ";
      const max = this.filters.max_price ? this.formatCurrency(this.filters.max_price) : "không giới hạn";
      return `${min} - ${max}`;
    },
    venuesWithLocation() {
      return this.venues.filter((venue) => venue.latitude && venue.longitude);
    },
  },
  watch: {
    "$route.query": {
      handler() {
        this.applyRouteQuery();
        this.loadVenues();
      },
      deep: true,
    },
  },
  async mounted() {
    this.applyRouteQuery();
    await Promise.all([this.loadCourtTypes(), this.loadVenues()]);
  },
  methods: {
    applyRouteQuery() {
      const query = this.$route.query;
      this.viewMode = query.view === "map" ? "map" : "list";
      this.filters = {
        ...this.filters,
        q: query.q || "",
        court_type_id: query.court_type_id || "",
        area: query.area || "",
        min_price: query.min_price || "",
        max_price: query.max_price || "",
        min_rating: query.min_rating || "",
        booking_date: query.booking_date || query.date || this.today,
        start_time: this.normalizeTime(query.start_time || query.time || "18:00:00"),
        sort: query.sort || "recommended",
      };
      this.filters.end_time = this.endTimeFromStart(this.filters.start_time);
    },
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
        const response = await venueService.list(this.cleanQuery(false));
        this.venues = response.data || [];
      } catch (error) {
        this.error = error.message || "Không thể tải danh sách sân.";
      } finally {
        this.loading = false;
      }
    },
    applyFilters() {
      if (this.filters.min_price !== "" && this.filters.max_price !== ""
        && Number(this.filters.max_price) < Number(this.filters.min_price)) {
        this.error = "Giá tối đa phải lớn hơn hoặc bằng giá tối thiểu.";
        return;
      }
      this.filters.start_time = this.normalizeTime(this.filters.start_time);
      this.filters.end_time = this.endTimeFromStart(this.filters.start_time);
      this.$router.replace({ name: "venues", query: this.cleanQuery() });
    },
    cleanQuery(includeView = true) {
      const params = {
        ...this.filters,
        view: includeView && this.viewMode === "map" ? "map" : "",
      };
      return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== ""),
      );
    },
    normalizeTime(time) {
      const label = String(time || "18:00:00").slice(0, 5);
      return `${label}:00`;
    },
    endTimeFromStart(time) {
      const [hour, minute] = this.normalizeTime(time).slice(0, 5).split(":").map(Number);
      return `${String(Math.min(hour + 1, 24)).padStart(2, "0")}:${String(minute).padStart(2, "0")}:00`;
    },
    setCourtType(id) {
      this.filters.court_type_id = id;
      this.applyFilters();
    },
    setViewMode(mode) {
      this.viewMode = mode;
      this.$router.replace({ name: "venues", query: this.cleanQuery() });
    },
    resetFilters() {
      this.filters = {
        q: "",
        court_type_id: "",
        area: "",
        min_price: "",
        max_price: "",
        min_rating: "",
        booking_date: this.today,
        start_time: "18:00:00",
        end_time: "19:00:00",
        sort: "recommended",
      };
      this.viewMode = "list";
      this.applyFilters();
    },
    bookingQueryFromFilters(venue = null) {
      return {
        venue_cluster_id: venue?.id || "",
        cluster: venue?.id || "",
        booking_date: this.filters.booking_date,
        date: this.filters.booking_date,
        start_time: this.filters.start_time,
        end_time: this.filters.end_time,
        court_type: this.filters.court_type_id || "",
      };
    },
    goDetail(venue) {
      this.$router.push({
        name: "venue-detail",
        params: { id: venue.slug || venue.id },
        query: this.cleanQuery(),
      });
    },
    goBooking(venue) {
      this.$router.push({ name: "booking-create", query: this.bookingQueryFromFilters(venue) });
    },
    courtCount(venue) {
      return Number(venue.court_count || venue.venue_courts_count || venue.venue_courts?.length || 0);
    },
    courtTypeNames(venue) {
      const names = (venue.court_types || []).map((type) => type.name).filter(Boolean);
      return names.length ? names.slice(0, 3) : ["Đa môn"];
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
    priceLabel(venue) {
      return venue.min_price ? `Từ ${this.formatCurrency(venue.min_price)}/giờ` : "Liên hệ giá";
    },
    formatRating(venue) {
      const rating = Number(venue.rating_avg || venue.average_rating || 0);
      return rating > 0 ? `${rating.toFixed(1)} ★` : "Mới";
    },
  },
};
</script>

<style scoped>
.venue-market-page {
  min-height: 100vh;
  background: #f4f7f5;
  color: #111827;
}

main {
  padding-top: 64px;
}

.market-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 28px;
}

.search-band {
  background: #0e3b2c;
  color: #fff;
  padding: 34px 0 40px;
}

.breadcrumbs,
.search-heading,
.venue-title-line,
.venue-actions,
.results-toolbar,
.toolbar-actions,
.filter-head {
  display: flex;
  align-items: center;
}

.breadcrumbs {
  gap: 8px;
  color: rgba(255, 255, 255, .72);
  font-size: 13px;
  font-weight: 800;
}

.breadcrumbs a {
  color: inherit;
  text-decoration: none;
}

.search-heading {
  justify-content: space-between;
  gap: 18px;
  margin: 20px 0 22px;
}

.search-heading h1 {
  margin: 0 0 8px;
  font-size: 36px;
  line-height: 1.1;
  font-weight: 950;
}

.search-heading p {
  margin: 0;
  color: rgba(255, 255, 255, .78);
  font-weight: 750;
}

.history-link,
.search-submit,
.apply-side,
.back-list,
.state-panel button,
.book-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-weight: 900;
  text-decoration: none;
}

.history-link {
  min-height: 40px;
  padding: 0 16px;
  border: 1px solid rgba(255, 255, 255, .22);
  color: #fff;
}

.search-form {
  display: grid;
  grid-template-columns: 1.25fr .8fr .9fr 1fr 120px;
  gap: 12px;
  align-items: end;
}

.field {
  display: grid;
  gap: 7px;
}

.field span {
  font-size: 12px;
  font-weight: 850;
}

.search-form .field span {
  color: rgba(255, 255, 255, .8);
}

.field input,
.field select,
.toolbar-actions select {
  width: 100%;
  height: 44px;
  border: 1px solid #dce5df;
  border-radius: 8px;
  background: #fff;
  color: #101828;
  font-size: 14px;
  font-weight: 750;
  outline: none;
}

.field input,
.field select {
  padding: 0 12px;
}

.toolbar-actions select {
  padding: 0 34px 0 12px;
}

.field input:focus,
.field select:focus,
.toolbar-actions select:focus {
  border-color: #0b8f50;
  box-shadow: 0 0 0 3px rgba(13, 140, 81, .14);
}

.search-submit,
.apply-side,
.state-panel button,
.book-btn {
  min-height: 44px;
  padding: 0 18px;
  background: #0b8f50;
  color: #fff;
}

.market-layout {
  display: grid;
  grid-template-columns: 286px minmax(0, 1fr);
  gap: 24px;
  padding-top: 30px;
  padding-bottom: 54px;
}

.filter-rail {
  display: grid;
  align-content: start;
  gap: 16px;
}

.filter-panel {
  display: grid;
  gap: 16px;
  padding: 18px;
  border: 1px solid #dfe7e2;
  border-radius: 8px;
  background: #fff;
}

.filter-head {
  justify-content: space-between;
}

.filter-panel h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 950;
}

.filter-head button {
  color: #0b8f50;
  font-size: 12px;
  font-weight: 850;
}

.quick-types {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.quick-types button {
  min-height: 32px;
  padding: 0 10px;
  border: 1px solid #dce5df;
  border-radius: 7px;
  color: #415047;
  font-size: 13px;
  font-weight: 800;
}

.quick-types button.active {
  border-color: #0b8f50;
  background: #e3f8ec;
  color: #05603a;
}

.compact-panel a {
  color: #344039;
  font-size: 14px;
  font-weight: 800;
  text-decoration: none;
}

.compact-panel a:hover {
  color: #0b8f50;
}

.results-toolbar {
  justify-content: space-between;
  gap: 16px;
  min-height: 64px;
  margin-bottom: 16px;
  padding: 10px 14px 10px 18px;
  border: 1px solid #dfe7e2;
  border-radius: 8px;
  background: #fff;
}

.results-toolbar strong {
  display: block;
  font-size: 16px;
  font-weight: 950;
}

.results-toolbar span {
  display: block;
  margin-top: 4px;
  color: #66756d;
  font-size: 13px;
  font-weight: 750;
}

.toolbar-actions {
  gap: 10px;
}

.view-toggle {
  display: flex;
  gap: 6px;
}

.view-toggle button {
  width: 36px;
  height: 36px;
  border: 1px solid #dce5df;
  border-radius: 8px;
  color: #66756d;
  font-size: 18px;
}

.view-toggle button.active {
  border-color: #0b8f50;
  background: #0b8f50;
  color: #fff;
}

.venue-list {
  display: grid;
  gap: 12px;
}

.venue-row {
  display: grid;
  grid-template-columns: 146px minmax(0, 1fr);
  min-height: 142px;
  overflow: hidden;
  border: 1px solid #dfe7e2;
  border-radius: 8px;
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
  color: #0b8f50;
  font-size: 28px;
  font-weight: 950;
}

.venue-main {
  display: grid;
  padding: 15px 16px;
}

.venue-title-line {
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
}

.venue-title-line h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 950;
}

.venue-title-line p {
  margin: 6px 0 0;
  color: #526159;
  font-size: 13px;
  line-height: 1.45;
}

.venue-title-line strong {
  color: #0b8f50;
  white-space: nowrap;
}

.venue-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 14px 0;
}

.venue-meta span {
  min-height: 26px;
  padding: 5px 9px;
  border: 1px solid #e1e7e4;
  border-radius: 7px;
  color: #46564d;
  font-size: 12px;
  font-weight: 800;
}

.venue-meta .available-chip {
  border-color: #b7ebc9;
  background: #ecfdf3;
  color: #067647;
}

.venue-actions {
  justify-content: flex-end;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid #edf2ef;
}

.detail-btn {
  min-height: 36px;
  padding: 0 14px;
  border: 1px solid #0b8f50;
  border-radius: 8px;
  color: #0b8f50;
  font-weight: 900;
}

.book-btn {
  min-height: 36px;
}

.state-panel,
.map-panel {
  display: grid;
  min-height: 300px;
  place-items: center;
  border: 1px solid #dfe7e2;
  border-radius: 8px;
  background: #fff;
  text-align: center;
}

.state-panel p {
  margin: 12px 0 18px;
  color: #66756d;
  font-weight: 750;
}

.error-panel {
  border-color: #fecaca;
  background: #fff7f7;
}

.spinner {
  width: 34px;
  height: 34px;
  border: 3px solid #dce8e1;
  border-top-color: #0b8f50;
  border-radius: 50%;
  animation: spin .8s linear infinite;
}

.map-panel {
  padding: 16px;
  gap: 14px;
}

.map-surface {
  position: relative;
  width: 100%;
  min-height: 320px;
  overflow: hidden;
  border-radius: 8px;
  background:
    linear-gradient(90deg, rgba(14, 59, 44, .08) 1px, transparent 1px),
    linear-gradient(rgba(14, 59, 44, .08) 1px, transparent 1px),
    #eaf3ee;
  background-size: 44px 44px;
}

.map-pin {
  position: absolute;
  display: grid;
  width: 42px;
  height: 42px;
  place-items: center;
  border: 3px solid #fff;
  border-radius: 50%;
  background: #0b8f50;
  color: #fff;
  font-size: 12px;
  font-weight: 950;
  box-shadow: 0 12px 24px rgba(14, 59, 44, .2);
}

.map-empty {
  display: grid;
  height: 320px;
  place-items: center;
  color: #66756d;
  font-weight: 850;
}

.back-list {
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid #dce5df;
  color: #344039;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 1040px) {
  .search-form,
  .market-layout {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 700px) {
  main {
    padding-top: 58px;
  }

  .market-container {
    padding: 0 18px;
  }

  .search-heading,
  .results-toolbar,
  .toolbar-actions,
  .venue-title-line,
  .venue-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .search-heading h1 {
    font-size: 30px;
  }

  .venue-row {
    grid-template-columns: 112px minmax(0, 1fr);
  }
}
</style>

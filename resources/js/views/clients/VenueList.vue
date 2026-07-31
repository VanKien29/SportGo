<template>
  <div class="sg-client-page sg3-venue-page">
    <PublicNavbar />
    <main>
      <section class="sg3-venue-hero">
        <div class="sg3-container">
          <div class="sg3-page-head">
            <div>
              <div class="sg3-breadcrumbs"><router-link to="/">Trang chủ</router-link><span>/</span><strong>Tìm sân</strong></div>
              <p class="sg3-kicker">Marketplace SportGo</p>
              <h1>Tìm sân phù hợp để chơi ngay</h1>
              <p>{{ loading ? "Đang tìm lịch trống theo lựa chọn của bạn..." : `${venues.length} cụm sân phù hợp với bộ lọc hiện tại` }}</p>
            </div>
            <router-link class="sg3-button sg3-button--secondary" :to="{ name: 'booking-history' }"><AppIcon name="history" :size="17" />Lịch đặt sân</router-link>
          </div>

          <form class="sg3-search-panel" @submit.prevent="applyFilters">
            <label class="sg3-field"><span>Tên sân hoặc địa điểm</span><input v-model.trim="filters.q" type="search" placeholder="Ví dụ: Green Sport, Ba Đình" /></label>
            <label class="sg3-field"><span>Môn thể thao</span><select v-model="filters.court_type_id"><option value="">Tất cả môn</option><option v-for="type in courtTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select></label>
            <label class="sg3-field"><span>Khu vực</span><input v-model.trim="filters.area" type="text" placeholder="Quận, phường, tỉnh thành" /></label>
            <label class="sg3-field"><span>Ngày và giờ chơi</span><BookingDateTimePicker v-model:date="filters.booking_date" v-model:time="filters.start_time" :min-date="today" :time-options="timeOptions" compact /></label>
            <button class="sg3-search-submit" type="submit"><AppIcon name="search" :size="17" />Tìm sân</button>
          </form>
        </div>
      </section>

      <section class="sg3-container sg3-market-layout">
        <button class="sg3-mobile-filter-toggle" type="button" :aria-expanded="mobileFiltersOpen" @click="mobileFiltersOpen = !mobileFiltersOpen"><span><AppIcon name="filter" :size="17" />Bộ lọc nâng cao</span><AppIcon :name="mobileFiltersOpen ? 'chevronUp' : 'chevronDown'" :size="17" /></button>
        <aside class="sg3-card sg3-filter-card" :class="{ 'is-open': mobileFiltersOpen }" aria-label="Bộ lọc tìm sân">
          <div class="sg3-filter-head"><h2>Bộ lọc</h2><button type="button" @click="resetFilters">Xóa lọc</button></div>
          <div class="sg3-filter-body">
            <div class="sg3-chip-list">
              <button type="button" class="sg3-chip" :class="{ 'is-active': !filters.court_type_id }" @click="setCourtType('')">Tất cả</button>
              <button v-for="type in courtTypes" :key="type.id" type="button" class="sg3-chip" :class="{ 'is-active': String(filters.court_type_id) === String(type.id) }" @click="setCourtType(type.id)">{{ type.name }}</button>
            </div>
            <label class="sg3-field"><span>Giá từ</span><input v-model.number="filters.min_price" min="0" step="10000" type="number" placeholder="0 đ" /></label>
            <label class="sg3-field"><span>Giá đến</span><input v-model.number="filters.max_price" min="0" step="10000" type="number" placeholder="Không giới hạn" /></label>
            <label class="sg3-field"><span>Đánh giá tối thiểu</span><select v-model="filters.min_rating"><option value="">Không giới hạn</option><option value="4.5">Từ 4.5 sao</option><option value="4">Từ 4 sao</option><option value="3">Từ 3 sao</option></select></label>
            <button class="sg3-apply-filter" type="button" @click="applySideFilters"><AppIcon name="filter" :size="16" />Áp dụng bộ lọc</button>
          </div>
        </aside>

        <section class="sg3-results-area">
          <div class="sg3-results-toolbar">
            <div><strong>{{ venues.length }} kết quả</strong><span>{{ activeFilterLabel }}</span></div>
            <div class="sg3-toolbar-actions"><select v-model="filters.sort" aria-label="Sắp xếp" @change="applyFilters"><option value="recommended">Gợi ý phù hợp</option><option value="price">Giá thấp trước</option><option value="rating">Đánh giá cao</option><option value="courts">Nhiều sân nhất</option><option value="name">Tên A-Z</option></select><div class="sg3-view-toggle" aria-label="Chế độ xem"><button type="button" :class="{ 'is-active': viewMode === 'list' }" aria-label="Xem danh sách" @click="setViewMode('list')"><AppIcon name="menu" :size="17" /></button><button type="button" :class="{ 'is-active': viewMode === 'map' }" aria-label="Xem bản đồ" @click="setViewMode('map')"><AppIcon name="mapPin" :size="17" /></button></div></div>
          </div>

          <div v-if="viewMode === 'map'" class="sg3-card sg3-map-panel">
            <VenueResultsMap v-if="venuesWithLocation.length" :venues="venuesWithLocation" @select="goDetail" />
            <div v-else class="sg3-empty"><div><strong>Chưa có điểm sân trên bản đồ</strong><p>Những cụm sân trong kết quả này chưa có tọa độ hiển thị.</p></div></div>
            <button class="sg3-button sg3-button--secondary sg3-map-back" type="button" @click="setViewMode('list')">Quay lại danh sách</button>
          </div>
          <div v-else-if="loading" class="sg3-venue-grid" aria-label="Đang tải sân"><article v-for="item in 3" :key="item" class="sg3-card sg3-venue-card"><div class="sg3-skeleton sg3-venue-card__image"></div><div class="sg3-venue-card__body"><div><div class="sg3-skeleton" style="width: 62%; height: 22px; border-radius: 6px"></div><div class="sg3-skeleton" style="width: 86%; height: 14px; margin-top: 12px; border-radius: 5px"></div></div><div class="sg3-skeleton" style="width: 48%; height: 38px; border-radius: 8px"></div></div></article></div>
          <div v-else-if="error" class="sg3-error"><div><strong>Không tải được danh sách sân</strong><p>{{ error }}</p><button class="sg3-button sg3-button--primary" type="button" @click="loadVenues">Thử lại</button></div></div>
          <div v-else-if="venues.length === 0" class="sg3-empty"><div><strong>Chưa có sân trống ở lựa chọn này</strong><p>Thử đổi giờ chơi, khu vực hoặc nới bộ lọc để SportGo tìm thêm sân cho bạn.</p><button class="sg3-button sg3-button--primary" type="button" @click="resetFilters">Xem tất cả sân</button></div></div>
          <div v-else class="sg3-venue-grid">
            <article v-for="venue in venues" :key="venue.id" class="sg3-card sg3-venue-card">
              <button type="button" class="sg3-venue-card__image" @click="goDetail(venue)" :aria-label="`Xem ${venue.name}`"><img :src="venueImage(venue)" :alt="venue.name" @error="hideBrokenImage" /><span class="sg3-venue-card__fallback">{{ initials(venue.name) }}</span></button>
              <div class="sg3-venue-card__body"><div><div class="sg3-venue-card__title"><div><h2>{{ venue.name }}</h2><p>{{ venue.address || venue.ward || venue.province || "Đang cập nhật địa chỉ" }}</p></div><strong class="sg3-rating"><AppIcon name="star" :size="15" />{{ formatRating(venue) }}</strong></div><div class="sg3-venue-meta"><span>{{ courtCount(venue) }} sân hoạt động</span><span v-for="name in courtTypeNames(venue)" :key="name">{{ name }}</span><span>{{ priceLabel(venue) }}</span><span class="is-available">Có lịch trống</span></div></div><div class="sg3-venue-actions"><button class="sg3-button sg3-button--secondary" type="button" @click="goDetail(venue)"><AppIcon name="eye" :size="16" />Chi tiết</button><button class="sg3-button sg3-button--primary" type="button" @click="goBooking(venue)"><AppIcon name="calendar" :size="16" />Đặt sân</button></div></div>
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
import { getAuth } from "../../stores/auth.js";

const fallbackImage = "/images/home/badminton-cover.webp";

export default {
  name: "VenueList",
  components: { AppIcon, BookingDateTimePicker, PublicNavbar, VenueResultsMap },
  data() {
    const today = new Date().toLocaleDateString("en-CA");
    return { venues: [], courtTypes: [], globalCourtTypes: [], today, loading: true, venuesRequestId: 0, error: "", mobileFiltersOpen: false, viewMode: this.$route.query.view === "map" ? "map" : "list", filters: { q: "", court_type_id: "", area: "", min_price: "", max_price: "", min_rating: "", booking_date: today, start_time: "18:00:00", end_time: "19:00:00", sort: "recommended" }, timeOptions: ["05:00", "06:00", "07:00", "08:00", "09:00", "10:00", "11:00", "12:00", "13:00", "14:00", "15:00", "16:00", "17:00", "18:00", "19:00", "20:00", "21:00", "22:00"] };
  },
  computed: {
    activeFilterLabel() { const parts = []; const type = this.courtTypes.find((item) => String(item.id) === String(this.filters.court_type_id)); if (type) parts.push(type.name); if (this.filters.area) parts.push(this.filters.area); if (this.filters.min_price || this.filters.max_price) parts.push(this.priceRangeLabel); parts.push(`${this.formatDateLabel(this.filters.booking_date)} lúc ${String(this.filters.start_time).slice(0, 5)}`); return parts.join(" · "); },
    priceRangeLabel() { const min = this.filters.min_price ? this.formatCurrency(this.filters.min_price) : "0đ"; const max = this.filters.max_price ? this.formatCurrency(this.filters.max_price) : "không giới hạn"; return `${min} - ${max}`; },
    venuesWithLocation() { return this.venues.filter((venue) => venue.latitude && venue.longitude); },
  },
  watch: { "$route.query": { handler() { this.applyRouteQuery(); this.loadVenues(); }, deep: true } },
  mounted() { this.applyRouteQuery(); this.loadCourtTypes(); this.loadVenues(); },
  methods: {
    applyRouteQuery() { const query = this.$route.query; const requestedDate = String(query.booking_date || query.date || this.today); this.viewMode = query.view === "map" ? "map" : "list"; this.filters = { ...this.filters, q: query.q || "", court_type_id: query.court_type_id || "", area: query.area || "", min_price: query.min_price || "", max_price: query.max_price || "", min_rating: query.min_rating || "", booking_date: requestedDate >= this.today ? requestedDate : this.today, start_time: this.normalizeTime(query.start_time || query.time || "18:00:00"), sort: query.sort || "recommended" }; this.filters.end_time = this.endTimeFromStart(this.filters.start_time); },
    async loadCourtTypes() { try { const response = await courtTypeService.getAll(); this.globalCourtTypes = this.normalizeCourtTypes((response.data || []).filter((type) => type?.id && type?.name && type.is_active !== false && !type.parent_id)); this.syncCourtTypes(this.inferCourtTypes(this.venues)); } catch { this.globalCourtTypes = []; this.syncCourtTypes(this.inferCourtTypes(this.venues)); } },
    async loadVenues() { const requestId = ++this.venuesRequestId; this.loading = true; this.error = ""; try { const response = await venueService.list(this.cleanQuery(false)); if (requestId !== this.venuesRequestId) return; this.venues = response.data || []; this.syncCourtTypes(this.inferCourtTypes(this.venues)); } catch (error) { if (requestId !== this.venuesRequestId) return; this.error = error.message || "Không thể tải danh sách sân."; } finally { if (requestId === this.venuesRequestId) this.loading = false; } },
    inferCourtTypes(venues = []) { const availableTypes = new Map(); venues.forEach((venue) => (venue.court_types || []).forEach((type) => { if (type?.id && type?.name && type.is_active !== false) availableTypes.set(String(type.id), type); })); return this.normalizeCourtTypes(Array.from(availableTypes.values())); },
    syncCourtTypes(inferredTypes = []) { const types = new Map((this.globalCourtTypes.length ? this.globalCourtTypes : inferredTypes).map((type) => [String(type.id), type])); const selectedId = String(this.filters.court_type_id || ""); if (selectedId && !types.has(selectedId)) { const selectedType = [...inferredTypes, ...this.courtTypes].find((type) => String(type.id) === selectedId); if (selectedType) types.set(selectedId, selectedType); } this.courtTypes = this.normalizeCourtTypes(Array.from(types.values())); },
    normalizeCourtTypes(types = []) { return [...types].sort((left, right) => String(left.name).localeCompare(String(right.name), "vi")); },
    applyFilters() { if (this.filters.min_price !== "" && this.filters.max_price !== "" && Number(this.filters.max_price) < Number(this.filters.min_price)) { this.error = "Giá tối đa phải lớn hơn hoặc bằng giá tối thiểu."; return; } this.filters.start_time = this.normalizeTime(this.filters.start_time); this.filters.end_time = this.endTimeFromStart(this.filters.start_time); this.$router.replace({ name: "venues", query: this.cleanQuery() }); },
    applySideFilters() { this.mobileFiltersOpen = false; this.applyFilters(); },
    cleanQuery(includeView = true) { const params = { ...this.filters, view: includeView && this.viewMode === "map" ? "map" : "" }; return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== "")); },
    normalizeTime(time) { const label = String(time || "18:00:00").slice(0, 5); return `${label}:00`; },
    endTimeFromStart(time) { const [hour, minute] = this.normalizeTime(time).slice(0, 5).split(":").map(Number); return `${String(Math.min(hour + 1, 24)).padStart(2, "0")}:${String(minute).padStart(2, "0")}:00`; },
    setCourtType(id) { this.filters.court_type_id = id; this.applyFilters(); },
    setViewMode(mode) { this.viewMode = mode; this.$router.replace({ name: "venues", query: this.cleanQuery() }); },
    resetFilters() { this.filters = { q: "", court_type_id: "", area: "", min_price: "", max_price: "", min_rating: "", booking_date: this.today, start_time: "18:00:00", end_time: "19:00:00", sort: "recommended" }; this.viewMode = "list"; this.applyFilters(); },
    bookingQueryFromFilters(venue = null) { return { venue_cluster_id: venue?.id || "", cluster: venue?.id || "", booking_date: this.filters.booking_date, date: this.filters.booking_date, start_time: this.filters.start_time, end_time: this.filters.end_time, court_type: this.filters.court_type_id || "", return_to: this.$route.fullPath }; },
    goDetail(venue) { this.$router.push({ name: "venue-detail", params: { id: venue.slug || venue.id }, query: this.cleanQuery() }); },
    goBooking(venue) { this.$router.push({ name: "booking-create", query: this.bookingQueryFromFilters(venue) }); },
    courtCount(venue) { return Number(venue.court_count || venue.venue_courts_count || venue.venue_courts?.length || 0); },
    courtTypeNames(venue) { const names = (venue.court_types || []).map((type) => type.name).filter(Boolean); return names.length ? names.slice(0, 3) : ["Đa môn"]; },
    initials(name = "") { return String(name).trim().slice(0, 2).toUpperCase() || "SG"; },
    imageUrl(path) { if (!path) return ""; if (/^https?:\/\//.test(path)) return path; if (path.startsWith("/")) return path; return `/storage/${path}`; },
    venueImage(venue) { return this.imageUrl(venue.image_path || venue.cover_image || venue.thumbnail) || fallbackImage; },
    hideBrokenImage(event) { event.target.style.display = "none"; },
    formatDateLabel(value) { if (!value) return "Chưa chọn ngày"; const [year, month, day] = String(value).split("-").map(Number); if (!year || !month || !day) return String(value); return new Intl.DateTimeFormat("vi-VN").format(new Date(year, month - 1, day)); },
    formatCurrency(amount) { return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(Number(amount || 0)); },
    priceLabel(venue) { return venue.min_price ? `Từ ${this.formatCurrency(venue.min_price)}/giờ` : "Liên hệ giá"; },
    formatRating(venue) { const rating = Number(venue.rating_avg || venue.average_rating || 0); return rating > 0 ? rating.toFixed(1) : "Mới"; },
  },
};
</script>

<style scoped>
.sg3-venue-page :deep(.sg3-venue-card__image) { padding: 0; border: 0; }
.sg3-venue-page :deep(.sg3-search-submit svg), .sg3-venue-page :deep(.sg3-apply-filter svg) { flex: 0 0 auto; }
</style>

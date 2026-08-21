<template>
  <div class="sg-client-page sg-venue-map-page">
    <PublicNavbar />
    <main class="sg3-container sg-venue-map-main">
      <header class="sg3-page-head sg-venue-map-head">
        <div>
          <div class="sg3-breadcrumbs"><router-link to="/">Trang chủ</router-link><span>/</span><strong>Bản đồ sân</strong></div>
          <p class="sg3-kicker">Khám phá theo vị trí</p>
          <h1>Bản đồ sân thể thao</h1>
          <p>Xem nhanh các cụm sân đang hoạt động trên một bản đồ tổng quan, chọn điểm gần bạn rồi mở lịch đặt.</p>
        </div>
        <router-link class="sg3-button sg3-button--primary" to="/venues"><AppIcon name="search" :size="16" /> Danh sách sân</router-link>
      </header>

      <section class="sg-venue-map-toolbar sg3-card" aria-label="Bộ lọc bản đồ">
        <label><span>Tìm theo tên hoặc khu vực</span><input v-model.trim="search" type="search" placeholder="Ví dụ: Cầu Giấy, Pickleball..." @keyup.enter="loadVenues" /></label>
        <button type="button" class="sg3-button sg3-button--primary" :disabled="loading" @click="loadVenues"><AppIcon name="search" :size="16" /> Tìm trên bản đồ</button>
        <span class="sg-venue-map-count"><strong>{{ mapVenues.length }}</strong> sân có tọa độ</span>
      </section>

      <section v-if="error" class="sg3-error"><div><strong>Không tải được bản đồ</strong><p>{{ error }}</p><button type="button" class="sg3-button sg3-button--primary" @click="loadVenues">Thử lại</button></div></section>
      <section v-else class="sg-venue-map-layout">
        <div class="sg3-card sg-venue-map-canvas">
          <div v-if="loading" class="sg-venue-map-loading"><span class="spinner"></span><strong>Đang tải vị trí sân...</strong></div>
          <VenueResultsMap v-else :venues="mapVenues" @select="selectVenue" />
        </div>
        <aside class="sg3-card sg-venue-map-list">
          <header><div><p class="sg3-kicker">Danh sách điểm</p><h2>Sân quanh bạn</h2></div><span>{{ mapVenues.length }} điểm</span></header>
          <div v-if="!mapVenues.length && !loading" class="sg-venue-map-empty"><AppIcon name="mapPin" :size="25" /><strong>Chưa có sân có tọa độ</strong><p>Hãy thử khu vực khác hoặc xem danh sách tất cả sân.</p></div>
          <button v-for="venue in mapVenues" :key="venue.id" type="button" class="sg-venue-map-item" @click="selectVenue(venue)">
            <span class="sg-venue-map-item__image"><img v-if="venue.image_path" :src="assetUrl(venue.image_path)" :alt="venue.name" /><span v-else>{{ initials(venue.name) }}</span></span>
            <span class="sg-venue-map-item__copy"><strong>{{ venue.name }}</strong><small>{{ [venue.ward, venue.province].filter(Boolean).join(', ') || venue.address || 'Đang cập nhật địa chỉ' }}</small><span><AppIcon name="star" :size="13" /> {{ Number(venue.rating_avg || 0).toFixed(1) }} · {{ venue.court_count || 0 }} sân</span></span>
            <AppIcon name="chevronRight" :size="17" />
          </button>
        </aside>
      </section>
    </main>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import VenueResultsMap from '../../components/VenueResultsMap.vue';
import { venueService } from '../../services/venues.js';

export default {
  name: 'VenueMap',
  components: { AppIcon, PublicNavbar, VenueResultsMap },
  data() { return { venues: [], search: '', loading: true, error: '' }; },
  computed: { mapVenues() { return this.venues.filter((venue) => Number.isFinite(Number(venue.latitude)) && Number.isFinite(Number(venue.longitude))); } },
  mounted() { this.loadVenues(); },
  methods: {
    async loadVenues() {
      this.loading = true; this.error = '';
      try {
        const response = await venueService.list({ has_map: 1, limit: 50, q: this.search });
        this.venues = response.data || [];
      } catch (error) { this.error = error.message || 'Vui lòng thử lại.'; }
      finally { this.loading = false; }
    },
    selectVenue(venue) { this.$router.push({ name: 'venue-detail', params: { id: venue.slug || venue.id } }); },
    assetUrl(path) { if (!path) return ''; return /^https?:\/\//.test(path) || path.startsWith('/') ? path : `/storage/${path}`; },
    initials(name) { return String(name || 'SG').split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join(''); },
  },
};
</script>

<style scoped>
.sg-venue-map-main { padding-bottom: 64px; }
.sg-venue-map-head { padding-bottom: 20px; }
.sg-venue-map-toolbar { display:flex; align-items:end; gap:14px; padding:15px; margin-bottom:16px; border: 1.5px solid var(--sg3-line); border-radius: 12px; }
.sg-venue-map-toolbar label { display:grid; gap:6px; flex:1; }
.sg-venue-map-toolbar label span { color:var(--sg3-muted); font-size:11px; font-weight:700; text-transform: uppercase; letter-spacing: 0.05em; }
.sg-venue-map-toolbar input { min-height:42px; padding:0 14px; border:1.5px solid var(--sg3-line); border-radius:8px; outline:0; font-size: 13.5px; transition: border-color 0.15s ease, box-shadow 0.15s ease; }
.sg-venue-map-toolbar input:focus { border-color:#54656f; box-shadow:0 0 0 3px rgba(84,101,111,.12); }
.sg-venue-map-count { padding-bottom:12px; color:var(--sg3-muted); font-size:12px; white-space:nowrap; }
.sg-venue-map-count strong { color:#5c7e6e; font-weight: 700; }
.sg-venue-map-layout { display:grid; grid-template-columns:minmax(0,1.55fr) minmax(300px,.75fr); gap:16px; align-items:start; }
.sg-venue-map-canvas { padding:10px; min-height:490px; border: 1.5px solid var(--sg3-line); border-radius: 12px; }
.sg-venue-map-canvas :deep(.venue-results-map) { min-height:470px; border:0; border-radius:10px; }
.sg-venue-map-loading { display:grid; min-height:470px; place-items:center; align-content:center; gap:10px; color:var(--sg3-muted); }
.sg-venue-map-list { overflow:hidden; border: 1.5px solid var(--sg3-line); border-radius: 12px; }
.sg-venue-map-list > header { display:flex; justify-content:space-between; gap:10px; padding:18px; border-bottom:1px solid var(--sg3-line); }
.sg-venue-map-list h2 { margin:0; font-size:18px; font-weight: 700; }
.sg-venue-map-list > header > span { color:var(--sg3-muted); font-size:12px; }
.sg-venue-map-item { display:grid; grid-template-columns:48px minmax(0,1fr) 17px; align-items:center; gap:11px; width:100%; padding:13px 15px; border:0; border-bottom:1px solid var(--sg3-line); background:#fff; color:var(--sg3-ink); text-align:left; cursor:pointer; transition: background-color 0.15s ease; }
.sg-venue-map-item:hover { background:#edf4f0; }
.sg-venue-map-item__image { display:grid; width:48px; height:48px; place-items:center; overflow:hidden; border-radius:10px; background:#edf4f0; color:#5c7e6e; font-weight:700; }
.sg-venue-map-item__image img { width:100%; height:100%; object-fit:cover; }
.sg-venue-map-item__copy { display:grid; gap:4px; min-width:0; }
.sg-venue-map-item__copy strong,.sg-venue-map-item__copy small,.sg-venue-map-item__copy > span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sg-venue-map-item__copy strong { font-size:13px; }
.sg-venue-map-item__copy small { color:var(--sg3-muted); font-size:11px; }
.sg-venue-map-item__copy > span { display:flex; align-items:center; gap:4px; color:var(--sg3-green-dark); font-size:11px; }
.sg-venue-map-item__copy > span :deep(svg) { color:#d99a22; fill:#d99a22; }
.sg-venue-map-empty { display:grid; place-items:center; gap:8px; min-height:260px; padding:25px; color:var(--sg3-muted); text-align:center; }
.sg-venue-map-empty strong { color:var(--sg3-ink); }
.sg-venue-map-empty p { margin:0; font-size:12px; line-height:1.5; }
@media(max-width:820px){.sg-venue-map-layout{grid-template-columns:1fr}.sg-venue-map-toolbar{align-items:stretch; flex-direction:column}.sg-venue-map-count{padding-bottom:0}.sg-venue-map-canvas{min-height:410px}.sg-venue-map-canvas :deep(.venue-results-map),.sg-venue-map-loading{min-height:390px}}
</style>

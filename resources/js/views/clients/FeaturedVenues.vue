<template>
  <div class="sg-client-page sg3-featured-page">
    <PublicNavbar />
    <main class="sg3-container sg3-profile-main">
      <div class="sg3-page-head"><div><div class="sg3-breadcrumbs"><router-link to="/">Trang chủ</router-link><span>/</span><strong>Sân nổi bật</strong></div><p class="sg3-kicker">Được người chơi yêu thích</p><h1>Sân nổi bật gần bạn</h1><p>Khám phá những cụm sân có đánh giá tốt, nhiều lựa chọn và sẵn sàng cho buổi chơi tiếp theo.</p></div><router-link class="sg3-button sg3-button--primary" to="/venues"><AppIcon name="search" :size="17" />Tìm sân theo giờ</router-link></div>
      <div v-if="loading" class="sg3-venue-grid"><article v-for="item in 6" :key="item" class="sg3-card sg3-venue-card"><div class="sg3-skeleton sg3-venue-card__image"></div><div class="sg3-venue-card__body"><div class="sg3-skeleton" style="width: 70%; height: 22px; border-radius: 6px"></div><div class="sg3-skeleton" style="width: 45%; height: 38px; border-radius: 8px"></div></div></article></div>
      <div v-else-if="error" class="sg3-error"><div><strong>Chưa tải được sân nổi bật</strong><p>{{ error }}</p><button class="sg3-button sg3-button--primary" type="button" @click="load">Thử lại</button></div></div>
      <div v-else-if="venues.length === 0" class="sg3-empty"><div><strong>Chưa có dữ liệu sân nổi bật</strong><p>Hãy quay lại Tìm sân để xem toàn bộ sân đang hoạt động.</p><router-link class="sg3-button sg3-button--primary" to="/venues">Tìm sân</router-link></div></div>
      <div v-else class="sg3-featured-grid"><article v-for="venue in venues" :key="venue.id" class="sg3-card sg3-featured-card"><button class="sg3-featured-image" type="button" @click="open(venue)"><img :src="image(venue)" :alt="venue.name" /><span>{{ initials(venue.name) }}</span></button><div class="sg3-featured-body"><div class="sg3-venue-card__title"><div><h2>{{ venue.name }}</h2><p>{{ venue.address || venue.ward || "Đang cập nhật địa chỉ" }}</p></div><strong class="sg3-rating"><AppIcon name="star" :size="15" />{{ rating(venue) }}</strong></div><div class="sg3-venue-meta"><span>{{ venue.court_count || 0 }} sân</span><span>{{ price(venue) }}</span></div><router-link class="sg3-button sg3-button--secondary" :to="{ name: 'venue-detail', params: { id: venue.slug || venue.id } }">Xem cụm sân <AppIcon name="chevronRight" :size="16" /></router-link></div></article></div>
    </main>
  </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import PublicNavbar from "../../components/PublicNavbar.vue";
import { venueService } from "../../services/venues.js";
const fallbackImage = "/images/home/badminton-cover.webp";
export default {
  name: "FeaturedVenues",
  components: { AppIcon, PublicNavbar },
  data: () => ({ venues: [], loading: true, error: "" }),
  mounted() { this.load(); },
  methods: {
    async load() { this.loading = true; this.error = ""; try { const response = await venueService.list({ limit: 8, sort: "rating" }); this.venues = response.data || []; } catch (error) { this.error = error.message || "Không thể tải dữ liệu."; } finally { this.loading = false; } },
    open(venue) { this.$router.push({ name: "venue-detail", params: { id: venue.slug || venue.id } }); },
    image(venue) { const path = venue.image_path || venue.cover_image || venue.thumbnail; return !path ? fallbackImage : /^https?:\/\//.test(path) || path.startsWith("/") ? path : `/storage/${path}`; },
    initials(name) { return String(name || "SG").trim().slice(0, 2).toUpperCase(); },
    rating(venue) { const value = Number(venue.rating_avg || 0); return value ? value.toFixed(1) : "Mới"; },
    price(venue) { return venue.min_price ? `Từ ${new Intl.NumberFormat("vi-VN").format(venue.min_price)} đ/giờ` : "Liên hệ giá"; },
  },
};
</script>

<style scoped>
.sg3-featured-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px; }
.sg3-featured-card { display: grid; grid-template-columns: 180px minmax(0, 1fr); gap: 16px; padding: 12px; }
.sg3-featured-image { overflow: hidden; min-height: 170px; padding: 0; border: 0; border-radius: 9px; background: #e0eee4; cursor: pointer; }
.sg3-featured-image img { width: 100%; height: 100%; min-height: 170px; object-fit: cover; }
.sg3-featured-image span { display: none; }
.sg3-featured-body { display: grid; align-content: space-between; gap: 13px; padding: 5px 3px 3px 0; }
.sg3-featured-body h2 { margin: 0; font-size: 17px; }
.sg3-featured-body p { margin: 5px 0 0; color: var(--sg3-muted); font-size: 12px; }
@media (max-width: 800px) { .sg3-featured-grid { grid-template-columns: 1fr; } }
@media (max-width: 500px) { .sg3-featured-card { grid-template-columns: 1fr; } .sg3-featured-image, .sg3-featured-image img { min-height: 190px; } }
</style>

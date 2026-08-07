<template>
  <div class="favorite-venues-page sg-client-page">
    <PublicNavbar />

    <main class="sg3-container sg-client-shell">
      <header class="sg3-page-head favorite-page-head">
        <div>
          <p class="sg3-kicker">DANH SÁCH CỦA BẠN</p>
          <h1>Sân yêu thích</h1>
          <p>Những cụm sân bạn đã lưu, kèm hình ảnh, tiện ích và mức giá để đặt lại nhanh hơn.</p>
        </div>
        <router-link class="sg-client-button sg-client-button--primary" :to="{ name: 'venues' }">
          <AppIcon name="search" :size="16" />
          Tìm sân mới
        </router-link>
      </header>

      <section v-if="loading" class="sg-client-card favorite-state" aria-live="polite">
        <span class="favorite-state__spinner" aria-hidden="true"></span>
        <strong>Đang tải danh sách sân đã lưu...</strong>
      </section>
      <section v-else-if="error" class="sg-client-card favorite-state error" role="alert">
        <AppIcon name="alert" :size="28" />
        <strong>Không thể tải sân yêu thích</strong>
        <p>{{ error }}</p>
      </section>
      <section v-else-if="!favorites.length" class="sg-client-card favorite-state">
        <span class="favorite-state__icon"><AppIcon name="heart" :size="28" /></span>
        <strong>Bạn chưa lưu sân nào</strong>
        <p>Nhấn biểu tượng trái tim ở trang chi tiết sân để tạo danh sách riêng cho bạn.</p>
        <router-link class="sg-client-button sg-client-button--primary" :to="{ name: 'venues' }">Khám phá sân</router-link>
      </section>
      <section v-else class="sg3-favorite-list" aria-label="Danh sách sân yêu thích">
        <div class="sg3-favorite-list__summary">
          <div>
            <strong>{{ favorites.length }} cụm sân đã lưu</strong>
            <span>Chọn một sân để xem lịch trống và đặt ngay.</span>
          </div>
          <router-link :to="{ name: 'venues' }">Xem thêm sân <AppIcon name="arrowRight" :size="15" /></router-link>
        </div>

        <article v-for="favorite in favorites" :key="favorite.id" class="sg3-card sg3-favorite-card">
          <router-link class="sg3-favorite-card__image" :to="venueRoute(favorite)" :aria-label="`Xem ${venueOf(favorite).name || 'cụm sân'}`">
            <img :src="venueImage(favorite)" :alt="venueOf(favorite).name || 'Ảnh sân thể thao'" @error="markImageFallback" />
            <span class="sg3-favorite-card__saved"><AppIcon name="heart" :size="14" /> Đã lưu</span>
            <span class="sg3-favorite-card__fallback">{{ initials(venueOf(favorite).name) }}</span>
          </router-link>

          <div class="sg3-favorite-card__body">
            <div class="sg3-favorite-card__heading">
              <div>
                <span class="sg3-favorite-card__eyebrow">Sân yêu thích</span>
                <h2>{{ venueOf(favorite).name || 'Cụm sân' }}</h2>
                <p class="sg3-favorite-card__location"><AppIcon name="mapPin" :size="15" /> {{ locationLabel(favorite) }}</p>
              </div>
              <span class="sg3-favorite-card__rating"><AppIcon name="star" :size="15" /> {{ ratingLabel(favorite) }}</span>
            </div>

            <div class="sg3-favorite-card__meta">
              <span>{{ courtCount(favorite) }} sân hoạt động</span>
              <span v-for="type in courtTypeNames(favorite)" :key="type">{{ type }}</span>
              <span class="sg3-favorite-card__price">{{ priceLabel(favorite) }}</span>
            </div>

            <footer class="sg3-favorite-card__actions">
              <router-link class="sg3-button sg3-button--secondary" :to="venueRoute(favorite)">
                <AppIcon name="eye" :size="16" />
                Xem chi tiết
              </router-link>
              <router-link class="sg3-button sg3-button--primary" :to="bookingRoute(favorite)">
                <AppIcon name="calendar" :size="16" />
                Đặt sân
              </router-link>
            </footer>
          </div>
        </article>
      </section>
    </main>
  </div>
</template>

<script>
import AppIcon from '../../components/AppIcon.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import { favoriteService } from '../../services/favoriteService.js';
import { normalizeMediaUrl } from '../../utils/mediaUrl.js';

const fallbackImage = '/images/home/badminton-cover.webp';

export default {
  name: 'FavoriteVenues',
  components: { AppIcon, PublicNavbar },
  data() {
    return { favorites: [], loading: true, error: '', userLocation: null };
  },
  async mounted() {
    this.requestUserLocation();
    try {
      const response = await favoriteService.list();
      this.favorites = Array.isArray(response.data) ? response.data : [];
    } catch (error) {
      this.error = error.message || 'Không thể tải sân yêu thích.';
    } finally {
      this.loading = false;
    }
  },
  methods: {
    venueOf(favorite) {
      return favorite?.venue_cluster || {};
    },
    venueRoute(favorite) {
      const venue = this.venueOf(favorite);
      return { name: 'venue-detail', params: { id: venue.slug || venue.id } };
    },
    bookingRoute(favorite) {
      const venue = this.venueOf(favorite);
      return { name: 'booking-create', query: { venue_cluster_id: venue.id, cluster: venue.id } };
    },
    venueImage(favorite) {
      return normalizeMediaUrl({ file_path: this.venueOf(favorite).image_path }) || fallbackImage;
    },
    markImageFallback(event) {
      if (event.target.dataset.fallbackApplied) return;
      event.target.dataset.fallbackApplied = 'true';
      event.target.src = fallbackImage;
    },
    locationLabel(favorite) {
      const venue = this.venueOf(favorite);
      const area = venue.ward || venue.province || venue.address || 'Đang cập nhật vị trí';
      const distance = this.distanceToVenue(venue);
      return distance === null ? area : `${area} · cách ${distance} km`;
    },
    requestUserLocation() {
      if (!navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition(({ coords }) => {
        this.userLocation = { latitude: Number(coords.latitude), longitude: Number(coords.longitude) };
      }, () => {}, { enableHighAccuracy: false, maximumAge: 300000, timeout: 5000 });
    },
    distanceToVenue(venue) {
      if (!this.userLocation || venue?.latitude === null || venue?.longitude === null || venue?.latitude === undefined || venue?.longitude === undefined) return null;
      const latitude = Number(venue.latitude);
      const longitude = Number(venue.longitude);
      if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null;
      const radians = (value) => value * Math.PI / 180;
      const deltaLatitude = radians(latitude - this.userLocation.latitude);
      const deltaLongitude = radians(longitude - this.userLocation.longitude);
      const a = Math.sin(deltaLatitude / 2) ** 2 + Math.cos(radians(this.userLocation.latitude)) * Math.cos(radians(latitude)) * Math.sin(deltaLongitude / 2) ** 2;
      return Math.round(6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
    },
    courtCount(favorite) {
      return Number(this.venueOf(favorite).court_count || 0);
    },
    courtTypeNames(favorite) {
      const types = (this.venueOf(favorite).court_types || []).map((type) => type.name).filter(Boolean);
      return types.length ? types.slice(0, 3) : ['Đa môn'];
    },
    priceLabel(favorite) {
      const price = Number(this.venueOf(favorite).min_price);
      return Number.isFinite(price) && price > 0 ? `Từ ${this.formatCurrency(price)}/giờ` : 'Liên hệ giá';
    },
    ratingLabel(favorite) {
      const rating = Number(this.venueOf(favorite).rating_avg || 0);
      return rating > 0 ? rating.toFixed(1) : 'Mới';
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(amount);
    },
    initials(name) {
      return String(name || 'SG').trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
    },
  },
};
</script>

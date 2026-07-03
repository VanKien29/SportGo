<template>
  <div class="venue-detail-page">
    <PublicNavbar />

    <!-- Loading State -->
    <div v-if="loading" class="loading-screen">
      <div class="spinner"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-screen">
      <p class="error-msg">{{ error }}</p>
      <button class="btn-outline" @click="$router.back()">Quay lại</button>
    </div>

    <!-- Content -->
    <div v-else-if="venue" class="venue-content">

      <!-- ─── Hero ─── -->
      <div class="hero">
        <!-- Gallery -->
        <div class="hero-gallery">
          <div class="gallery-main">
            <img
              v-if="activeImage"
              :src="activeImage"
              :alt="venue.name"
              class="gallery-main-img"
              @error="onImgError"
            />
            <div v-else class="gallery-placeholder">
              <span>{{ (venue.name || '').slice(0, 2).toUpperCase() }}</span>
            </div>
          </div>
          <div class="gallery-thumbs" v-if="gallery.length > 1">
            <button
              v-for="(img, i) in gallery"
              :key="i"
              :class="['thumb-btn', { active: activeImage === img }]"
              @click="activeImage = img"
            >
              <img :src="img" :alt="`Ảnh ${i + 1}`" @error="e => e.target.parentNode.style.display='none'" />
            </button>
          </div>
        </div>

        <!-- Hero Info -->
        <div class="hero-info">

          <div class="court-type-badges" v-if="venue.court_types?.length">
            <span v-for="ct in venue.court_types" :key="ct.id" class="type-badge">{{ ct.name }}</span>
          </div>

          <h1 class="venue-name">{{ venue.name }}</h1>

          <div class="venue-meta">
            <div class="meta-item" v-if="venue.address">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
              </svg>
              {{ venue.address }}
            </div>
            <div class="meta-item" v-if="venue.phone_contact">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.84a16 16 0 0 0 6 6l.86-.86a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7a2 2 0 0 1 1.72 2.03z"/>
              </svg>
              {{ venue.phone_contact }}
            </div>
            <div class="meta-item" v-if="venue.rating_count > 0">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
              </svg>
              {{ venue.rating_avg?.toFixed(1) }} ({{ venue.rating_count }} đánh giá)
            </div>
          </div>
        </div>
      </div>

      <!-- ─── Main Layout ─── -->
      <div class="main-layout">

        <!-- LEFT: Detail Sections -->
        <div class="detail-col">

          <!-- Description -->
          <section class="detail-section" v-if="venue.description">
            <h2 class="section-title">Giới thiệu</h2>
            <p class="description-text">{{ venue.description }}</p>
          </section>

          <!-- Amenities -->
          <section class="detail-section" v-if="amenities.length">
            <h2 class="section-title">Tiện ích</h2>
            <div class="amenity-grid">
              <div v-for="amenity in amenities" :key="amenity.id" class="amenity-item">
                <div>
                  <span class="amenity-name">{{ amenity.name }}</span>
                  <span v-if="amenity.description" class="amenity-desc">{{ amenity.description }}</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Court Types & Courts -->
          <section class="detail-section" v-if="venue.venue_courts?.length">
            <h2 class="section-title">Danh sách sân</h2>
            <div class="courts-by-type" v-for="group in courtGroups" :key="group.typeId">
              <h3 class="court-type-label">{{ group.typeName }}</h3>
              <div class="court-chips">
                <span v-for="court in group.courts" :key="court.id" class="court-chip">
                  {{ court.name }}
                </span>
              </div>
            </div>
          </section>

          <!-- Pricing -->
          <section class="detail-section" v-if="priceSlots.length || basePrices.length">
            <h2 class="section-title">Bảng giá</h2>

            <!-- Base Prices -->
            <div v-if="basePrices.length" class="price-block">
              <p class="price-block-label">Giá cơ bản (áp dụng khi không có khung giờ)</p>
              <div class="price-table">
                <div class="price-row header-row">
                  <span>Loại sân</span>
                  <span>Giá / giờ</span>
                </div>
                <div v-for="bp in basePrices" :key="bp.id" class="price-row">
                  <span>{{ bp.court_type?.name || 'Tất cả' }}</span>
                  <span class="price-val">{{ formatPrice(bp.price) }}</span>
                </div>
              </div>
            </div>

            <!-- Time-based Price Slots -->
            <div v-if="priceSlots.length" class="price-block">
              <p class="price-block-label">Giá theo khung giờ</p>
              <div class="price-table">
                <div class="price-row header-row">
                  <span>Loại sân</span>
                  <span>Khung giờ</span>
                  <span>Ngày</span>
                  <span>Giá / giờ</span>
                </div>
                <div v-for="slot in priceSlots" :key="slot.id" class="price-row">
                  <span>{{ slot.court_type?.name || 'Tất cả' }}</span>
                  <span>{{ slot.start_time?.slice(0,5) }} – {{ slot.end_time?.slice(0,5) }}</span>
                  <span>{{ formatDays(slot.days_of_week) }}</span>
                  <span class="price-val">{{ formatPrice(slot.price) }}</span>
                </div>
              </div>
            </div>
          </section>

          <!-- Map -->
          <section class="detail-section" v-if="venue.map_url || (venue.latitude && venue.longitude)">
            <h2 class="section-title">Vị trí</h2>
            <div class="map-embed-wrapper">
              <iframe
                v-if="mapEmbedUrl"
                :src="mapEmbedUrl"
                class="map-iframe"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
              ></iframe>
              <div v-else class="map-text">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                </svg>
                {{ venue.address }}
              </div>
            </div>
          </section>

        </div>

        <!-- RIGHT: Sticky Booking Panel -->
        <div class="booking-col" ref="bookingPanelRef">
          <div class="booking-panel" id="booking-panel">
            <div class="booking-panel-header">
              <span class="panel-title">Đặt sân tại đây</span>
              <span class="panel-price" v-if="venue.min_price">
                Từ {{ formatPrice(venue.min_price) }}<span class="panel-price-unit">/giờ</span>
              </span>
            </div>

            <div class="booking-form">
              <div class="bform-group">
                <label class="bform-label" for="bp-date">Ngày chơi</label>
                <input
                  id="bp-date"
                  type="date"
                  v-model="bookDate"
                  :min="minDate"
                  class="bform-input"
                />
              </div>

              <div class="bform-group" v-if="courtTypes.length > 1">
                <label class="bform-label" for="bp-type">Loại sân</label>
                <select id="bp-type" v-model="bookCourtType" class="bform-input">
                  <option value="">Tất cả loại sân</option>
                  <option v-for="ct in courtTypes" :key="ct.id" :value="ct.id">{{ ct.name }}</option>
                </select>
              </div>

              <button
                id="btn-view-schedule"
                class="btn-primary btn-full"
                :disabled="!bookDate"
                @click="goToBooking"
              >
                Xem lịch trống &amp; Đặt sân
              </button>

              <button
                class="btn-outline btn-full flex items-center justify-center gap-2"
                style="margin-top: 10px; display: inline-flex; width: 100%; align-items: center; justify-content: center; gap: 8px; font-weight: 500;"
                @click="chatWithOwner"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Nhắn tin hỏi chủ sân
              </button>

              <p class="panel-note">Chọn ngày để xem khung giờ còn trống</p>
            </div>

            <!-- Quick Stats in Panel -->
            <div class="panel-info-list">
              <div class="panel-info-item" v-if="venue.booking_config?.allow_no_prepay">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                Có thể đặt không cần trả trước
              </div>
              <div class="panel-info-item" v-if="venue.booking_config?.allow_deposit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                Hỗ trợ đặt cọc giữ chỗ
              </div>
              <div class="panel-info-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
                {{ venue.court_count || venue.venue_courts?.length || 0 }} sân đang hoạt động
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script>
import PublicNavbar from '../../components/PublicNavbar.vue';
import { venueService } from '../../services/venues.js';

const DAY_NAMES = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

export default {
  name: 'VenueDetail',
  components: { PublicNavbar },

  data() {
    return {
      venue: null,
      loading: true,
      error: null,

      gallery: [],
      activeImage: null,

      bookDate: this.todayStr(),
      bookCourtType: '',
    };
  },

  computed: {
    amenities() {
      return this.venue?.amenities_detail || this.venue?.amenities || [];
    },

    courtTypes() {
      return this.venue?.court_types || [];
    },

    courtGroups() {
      const courts = this.venue?.venue_courts || [];
      const groups = {};
      courts.forEach(court => {
        const typeId = court.court_type?.id || 'other';
        const typeName = court.court_type?.name || 'Khác';
        if (!groups[typeId]) groups[typeId] = { typeId, typeName, courts: [] };
        groups[typeId].courts.push(court);
      });
      return Object.values(groups);
    },

    priceSlots() {
      return this.venue?.price_slots || [];
    },

    basePrices() {
      return this.venue?.base_prices || [];
    },

    mapEmbedUrl() {
      if (this.venue?.latitude && this.venue?.longitude) {
        return `https://www.google.com/maps?q=${this.venue.latitude},${this.venue.longitude}&output=embed`;
      }
      if (this.venue?.map_url) {
        // Try to convert share URL to embed URL
        const url = this.venue.map_url;
        if (url.includes('google.com/maps/embed')) return url;
        if (url.includes('google.com/maps')) {
          return url.replace('/maps/', '/maps/embed/v1/place?key=AIzaSyD-9tSrke72PouQMnMX-a7eZSW0jkFMBWY&q=') ;
        }
        return null;
      }
      return null;
    },

    minDate() {
      return this.todayStr();
    },
  },

  mounted() {
    this.fetchVenue();
  },

  methods: {
    async fetchVenue() {
      this.loading = true;
      this.error = null;
      try {
        const id = this.$route.params.id;
        const res = await venueService.show(id);
        this.venue = res.data || res;

        // Build gallery
        const g = this.venue.gallery || [];
        this.gallery = g.map(path => this.imageUrl(path)).filter(Boolean);
        this.activeImage = this.gallery[0] || null;
      } catch (err) {
        this.error = err.message || 'Không thể tải thông tin sân.';
      } finally {
        this.loading = false;
      }
    },

    imageUrl(path) {
      if (!path) return null;
      if (path.startsWith('http')) return path;
      return `/storage/${path}`;
    },

    onImgError(e) {
      e.target.style.display = 'none';
    },

    todayStr() {
      return new Date().toISOString().slice(0, 10);
    },

    formatPrice(val) {
      if (!val) return '';
      return new Intl.NumberFormat('vi-VN').format(val) + 'đ';
    },

    formatDays(days) {
      if (!days || !days.length) return 'Tất cả';
      return days.map(d => DAY_NAMES[d] ?? d).join(', ');
    },

    scrollToBooking() {
      const el = this.$refs.bookingPanelRef;
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    goToBooking() {
      if (!this.bookDate) return;
      const query = {
        cluster: this.venue.id,
        date: this.bookDate,
      };
      if (this.bookCourtType) query.court_type = this.bookCourtType;
      this.$router.push({ name: 'booking-create', query });
    },

    chatWithOwner() {
      if (!this.venue) return;
      this.$router.push({
        path: '/chat',
        query: { venueId: this.venue.id }
      });
    },
  },
};
</script>

<style scoped>
/* ─── Base ─── */
.venue-detail-page {
  min-height: 100vh;
  background: #09090b;
  color: #ffffff;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* ─── Loading / Error ─── */
.loading-screen, .error-screen {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  gap: 16px;
}
.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid rgba(255,255,255,0.1);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.error-msg { font-size: 15px; color: rgba(255,255,255,0.5); }

/* ─── Buttons ─── */
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: #ffffff;
  color: #09090b;
  font-family: inherit;
  font-size: 14px;
  font-weight: 700;
  border: none;
  border-radius: 9999px;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-primary:hover { background: rgba(255,255,255,0.88); transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
.btn-primary.btn-full { width: 100%; justify-content: center; border-radius: 10px; }
.btn-outline {
  padding: 10px 22px;
  background: transparent;
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 9999px;
  color: rgba(255,255,255,0.7);
  font-family: inherit;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-outline:hover { border-color: rgba(255,255,255,0.4); color: #fff; }

/* ─── Hero ─── */
.hero {
  padding-top: 72px;
  max-width: 1280px;
  margin: 0 auto;
  padding-left: 24px;
  padding-right: 24px;
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: 48px;
  align-items: start;
  padding-bottom: 48px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
}

/* Gallery */
.hero-gallery {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.gallery-main {
  width: 100%;
  aspect-ratio: 16/9;
  border-radius: 14px;
  overflow: hidden;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.06);
}
.gallery-main-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.gallery-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 56px;
  font-weight: 900;
  color: rgba(255,255,255,0.06);
  letter-spacing: -2px;
}
.gallery-thumbs {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.thumb-btn {
  width: 72px;
  height: 48px;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid transparent;
  cursor: pointer;
  transition: border-color 0.2s;
  padding: 0;
  background: none;
}
.thumb-btn img { width: 100%; height: 100%; object-fit: cover; }
.thumb-btn.active { border-color: #ffffff; }
.thumb-btn:hover { border-color: rgba(255,255,255,0.4); }

/* Hero Info */
.back-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: rgba(255,255,255,0.45);
  text-decoration: none;
  margin-bottom: 16px;
  transition: color 0.2s;
}
.back-link:hover { color: #ffffff; }

.court-type-badges {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}
.type-badge {
  padding: 3px 10px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  color: rgba(255,255,255,0.7);
}

.venue-name {
  font-size: 36px;
  font-weight: 900;
  letter-spacing: -1px;
  color: #ffffff;
  margin: 0 0 16px;
  line-height: 1.1;
}

.venue-meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 24px;
}
.meta-item {
  display: flex;
  align-items: flex-start;
  gap: 7px;
  font-size: 13.5px;
  color: rgba(255,255,255,0.5);
  line-height: 1.4;
}
.meta-item svg { flex-shrink: 0; margin-top: 1px; }

.hero-stats {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 28px;
  padding: 16px 0;
  border-top: 1px solid rgba(255,255,255,0.06);
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.stat { display: flex; flex-direction: column; gap: 2px; }
.stat-value { font-size: 20px; font-weight: 800; color: #ffffff; }
.stat-label { font-size: 11px; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.5px; }
.stat-divider { width: 1px; height: 32px; background: rgba(255,255,255,0.08); }

/* ─── Main Layout ─── */
.main-layout {
  max-width: 1280px;
  margin: 0 auto;
  padding: 48px 24px 80px;
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 48px;
  align-items: start;
}

/* ─── Detail Sections ─── */
.detail-col { display: flex; flex-direction: column; gap: 0; }
.detail-section {
  padding-bottom: 36px;
  margin-bottom: 36px;
  border-bottom: 1px solid rgba(255,255,255,0.05);
}
.detail-section:last-child { border-bottom: none; }
.section-title {
  font-size: 18px;
  font-weight: 700;
  color: #ffffff;
  margin: 0 0 20px;
  letter-spacing: -0.3px;
}

.description-text {
  font-size: 14.5px;
  color: rgba(255,255,255,0.55);
  line-height: 1.75;
  margin: 0;
  white-space: pre-line;
}

/* Amenities */
.amenity-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
}
.amenity-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.05);
  border-radius: 10px;
}
.amenity-item svg { flex-shrink: 0; color: rgba(255,255,255,0.6); margin-top: 2px; }
.amenity-name { font-size: 13.5px; font-weight: 600; color: rgba(255,255,255,0.8); display: block; }
.amenity-desc { font-size: 12px; color: rgba(255,255,255,0.35); margin-top: 2px; display: block; }

/* Courts */
.courts-by-type { margin-bottom: 20px; }
.courts-by-type:last-child { margin-bottom: 0; }
.court-type-label {
  font-size: 13px;
  font-weight: 600;
  color: rgba(255,255,255,0.4);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0 0 10px;
}
.court-chips { display: flex; gap: 8px; flex-wrap: wrap; }
.court-chip {
  padding: 5px 12px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px;
  font-size: 13px;
  color: rgba(255,255,255,0.65);
}

/* Pricing */
.price-block { margin-bottom: 24px; }
.price-block:last-child { margin-bottom: 0; }
.price-block-label {
  font-size: 12.5px;
  color: rgba(255,255,255,0.35);
  margin: 0 0 10px;
  font-style: italic;
}
.price-table { display: flex; flex-direction: column; gap: 1px; border-radius: 10px; overflow: hidden; }
.price-row {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr 1fr;
  padding: 10px 14px;
  font-size: 13.5px;
  color: rgba(255,255,255,0.6);
  background: rgba(255,255,255,0.02);
}
.price-row.header-row {
  background: rgba(255,255,255,0.04);
  font-size: 12px;
  font-weight: 600;
  color: rgba(255,255,255,0.35);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
/* 2-col base price table */
.price-block:first-child .price-row { grid-template-columns: 1fr 1fr; }
.price-val { font-weight: 700; color: #ffffff; }

/* Map */
.map-embed-wrapper { border-radius: 12px; overflow: hidden; }
.map-iframe { width: 100%; height: 280px; border: none; display: block; }
.map-text {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 20px;
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 12px;
  font-size: 14px;
  color: rgba(255,255,255,0.5);
}

/* ─── Booking Panel ─── */
.booking-col { position: sticky; top: 88px; }
.booking-panel {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 16px;
  overflow: hidden;
}
.booking-panel-header {
  padding: 20px 22px 16px;
  border-bottom: 1px solid rgba(255,255,255,0.06);
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}
.panel-title { font-size: 16px; font-weight: 700; color: #ffffff; }
.panel-price { font-size: 18px; font-weight: 800; color: #ffffff; }
.panel-price-unit { font-size: 12px; font-weight: 400; color: rgba(255,255,255,0.4); }

.booking-form { padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; }
.bform-group { display: flex; flex-direction: column; gap: 6px; }
.bform-label { font-size: 12.5px; font-weight: 600; color: rgba(255,255,255,0.5); }
.bform-input {
  padding: 10px 12px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 8px;
  color: #ffffff;
  font-size: 14px;
  font-family: inherit;
  outline: none;
  transition: border-color 0.2s;
  width: 100%;
  box-sizing: border-box;
  -webkit-appearance: none;
}
.bform-input:focus { border-color: rgba(255,255,255,0.25); }
.bform-input option { background: #1c1c1e; color: #ffffff; }
.panel-note { font-size: 12px; color: rgba(255,255,255,0.3); text-align: center; margin: 0; }

.panel-info-list {
  padding: 16px 22px 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  border-top: 1px solid rgba(255,255,255,0.06);
}
.panel-info-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
  color: rgba(255,255,255,0.45);
}
.panel-info-item svg { color: rgba(255,255,255,0.5); flex-shrink: 0; }

/* ─── Responsive ─── */
@media (max-width: 1024px) {
  .hero { grid-template-columns: 1fr; gap: 28px; }
  .main-layout { grid-template-columns: 1fr; }
  .booking-col { position: static; }
  .venue-name { font-size: 28px; }
}
@media (max-width: 640px) {
  .hero { padding-top: 80px; padding-left: 16px; padding-right: 16px; }
  .main-layout { padding: 32px 16px 60px; }
  .venue-name { font-size: 24px; }
  .price-row { grid-template-columns: 1fr 1fr; font-size: 12px; }
  .price-row.header-row { display: none; }
}
</style>

<template>
  <div class="venue-list-page">
    <PublicNavbar />

    <!-- Search & Filter Bar -->
    <div class="search-bar-wrapper">
      <div class="search-bar-inner">
        <!-- Search Input -->
        <div class="search-input-group">
          <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input
            id="venue-search"
            type="text"
            v-model="searchQuery"
            placeholder="Tìm theo tên sân hoặc địa chỉ..."
            class="search-input"
            @input="onSearchInput"
          />
          <button v-if="searchQuery" class="search-clear" @click="clearSearch" aria-label="Xóa tìm kiếm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <!-- Sport type filter tags -->
        <div class="sport-tags">
          <button
            v-for="sport in sportTypes"
            :key="sport.value"
            :class="['sport-tag', { active: selectedSport === sport.value }]"
            @click="toggleSport(sport.value)"
          >
            {{ sport.label }}
          </button>
        </div>

        <!-- View Toggle & Result Count -->
        <div class="toolbar-right">
          <span class="result-count" v-if="!loading">
            {{ total }} sân
          </span>
          <div class="view-toggle">
            <button
              :class="['toggle-btn', { active: currentView === 'grid' }]"
              @click="currentView = 'grid'"
              title="Xem dạng lưới"
              id="view-grid-btn"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
              </svg>
              Lưới
            </button>
            <button
              :class="['toggle-btn', { active: currentView === 'map' }]"
              @click="currentView = 'map'"
              title="Xem trên bản đồ"
              id="view-map-btn"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                <line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>
              </svg>
              Bản đồ
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Content Area -->
    <div class="content-area">

      <!-- ─── GRID VIEW ─── -->
      <div v-if="currentView === 'grid'" class="grid-view">

        <!-- Loading Skeletons -->
        <div v-if="loading" class="venue-grid">
          <div v-for="n in 9" :key="n" class="venue-card skeleton">
            <div class="skeleton-img"></div>
            <div class="skeleton-body">
              <div class="skeleton-line w-70"></div>
              <div class="skeleton-line w-50"></div>
              <div class="skeleton-line w-40"></div>
            </div>
          </div>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="state-box">
          <svg class="state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <p>{{ error }}</p>
          <button class="retry-btn" @click="fetchVenues">Thử lại</button>
        </div>

        <!-- Empty state -->
        <div v-else-if="venues.length === 0" class="state-box">
          <svg class="state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <p>Không tìm thấy sân nào phù hợp</p>
          <button class="retry-btn" @click="clearAllFilters">Xóa bộ lọc</button>
        </div>

        <!-- Venue Cards -->
        <div v-else class="venue-grid">
          <router-link
            v-for="venue in venues"
            :key="venue.id"
            :to="`/venues/${venue.id}`"
            class="venue-card"
            :id="`venue-card-${venue.id}`"
          >
            <!-- Image -->
            <div class="card-img-wrapper">
              <img
                v-if="getVenueImage(venue)"
                :src="getVenueImage(venue)"
                :alt="venue.name"
                class="card-img"
                loading="lazy"
                @error="e => e.target.style.display='none'"
              />
              <div v-else class="card-img-placeholder">
                <span>{{ venue.name.slice(0, 2).toUpperCase() }}</span>
              </div>
            </div>

            <!-- Info -->
            <div class="card-info">
              <h3 class="card-name">{{ venue.name }}</h3>
              <div class="card-address" v-if="venue.address">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                </svg>
                {{ venue.address }}
              </div>
              <div class="card-footer">
                <div class="card-price" v-if="venue.min_price">
                  <span class="price-from">Từ</span>
                  <span class="price-value">{{ formatPrice(venue.min_price) }}</span>
                  <span class="price-unit">/giờ</span>
                </div>
                <span class="card-book-btn">Đặt sân →</span>
              </div>
            </div>
          </router-link>
        </div>

        <!-- Load More -->
        <div v-if="!loading && hasMore" class="load-more-wrapper">
          <button class="load-more-btn" @click="loadMore" :disabled="loadingMore">
            <span v-if="loadingMore">
              <span class="spinner-sm"></span> Đang tải...
            </span>
            <span v-else>Xem thêm {{ remaining }} sân</span>
          </button>
        </div>
      </div>

      <!-- ─── MAP VIEW ─── -->
      <div v-else class="map-view">
        <div class="map-layout">
          <!-- Left: compact list -->
          <div class="map-sidebar">
            <div class="map-sidebar-header">
              <span class="map-sidebar-count">{{ total }} kết quả</span>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="map-list-skeleton">
              <div v-for="n in 6" :key="n" class="map-list-item-skeleton">
                <div class="sk-img"></div>
                <div class="sk-text">
                  <div class="sk-line w-80"></div>
                  <div class="sk-line w-50"></div>
                </div>
              </div>
            </div>

            <!-- List -->
            <div v-else class="map-list" ref="mapListRef">
              <button
                v-for="venue in venues"
                :key="venue.id"
                :class="['map-list-item', { highlighted: hoveredVenueId === venue.id }]"
                @mouseenter="hoveredVenueId = venue.id"
                @mouseleave="hoveredVenueId = null"
                @click="goToVenue(venue.id)"
                :id="`map-item-${venue.id}`"
              >
                <div class="map-item-img">
                  <img
                    v-if="getVenueImage(venue)"
                    :src="getVenueImage(venue)"
                    :alt="venue.name"
                    @error="e => e.target.style.display='none'"
                  />
                  <span v-else class="map-item-initials">{{ venue.name.slice(0, 2).toUpperCase() }}</span>
                </div>
                <div class="map-item-info">
                  <div class="map-item-name">{{ venue.name }}</div>
                  <div class="map-item-address" v-if="venue.address">{{ venue.address }}</div>
                  <div class="map-item-tags">
                    <span v-for="type in (venue.court_types || []).slice(0, 2)" :key="type.id" class="map-item-tag">
                      {{ type.name }}
                    </span>
                  </div>
                  <div class="map-item-price" v-if="venue.min_price">
                    Từ {{ formatPrice(venue.min_price) }}/giờ
                  </div>
                </div>
                <svg class="map-item-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="9 18 15 12 9 6"/>
                </svg>
              </button>

              <!-- Load more inside map list -->
              <button v-if="hasMore && !loadingMore" class="map-load-more" @click="loadMore">
                Xem thêm {{ remaining }} sân
              </button>
              <div v-if="loadingMore" class="map-loading-more">
                <span class="spinner-sm"></span> Đang tải thêm...
              </div>
            </div>
          </div>

          <!-- Right: Interactive Map -->
          <div class="map-container" ref="mapContainer"></div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import PublicNavbar from '../../components/PublicNavbar.vue';
import { venueService } from '../../services/venues.js';

const SPORT_TYPES = [
  { value: '', label: 'Tất cả' },
  { value: 'bong-da', label: 'Bóng đá' },
  { value: 'tennis', label: 'Tennis' },
  { value: 'cau-long', label: 'Cầu lông' },
  { value: 'bong-ro', label: 'Bóng rổ' },
];

const PER_PAGE = 12;

export default {
  name: 'VenueList',
  components: { PublicNavbar },

  data() {
    return {
      venues: [],
      total: 0,
      page: 1,
      loading: true,
      loadingMore: false,
      error: null,

      searchQuery: '',
      selectedSport: '',
      currentView: 'grid',
      hoveredVenueId: null,

      sportTypes: SPORT_TYPES,
      searchTimer: null,
    };
  },

  computed: {
    hasMore() {
      return this.venues.length < this.total;
    },
    remaining() {
      return Math.min(PER_PAGE, this.total - this.venues.length);
    },
  },

  watch: {
    currentView(newView) {
      if (newView === 'map') {
        this.$nextTick(() => {
          this.initMap();
        });
      } else {
        this.destroyMap();
      }
    },
    venues() {
      if (this.currentView === 'map') {
        this.$nextTick(() => {
          this.updateMapMarkers();
        });
      }
    },
    hoveredVenueId(newId) {
      this.highlightMarker(newId);
    },
  },

  async mounted() {
    window.addEventListener('theme-changed', this.handleThemeChanged);
    await this.fetchVenues();
    if (this.currentView === 'map') {
      this.$nextTick(() => {
        this.initMap();
      });
    }
  },

  beforeUnmount() {
    window.removeEventListener('theme-changed', this.handleThemeChanged);
    this.destroyMap();
  },

  methods: {
    async fetchVenues(append = false) {
      if (!append) {
        this.loading = true;
        this.error = null;
        this.venues = [];
        this.page = 1;
      } else {
        this.loadingMore = true;
      }

      try {
        const params = {
          per_page: PER_PAGE,
          page: this.page,
        };
        if (this.searchQuery.trim()) params.name = this.searchQuery.trim();
        if (this.selectedSport) params.court_type = this.selectedSport;

        const res = await venueService.list(params);

        // Handle both paginated and flat array responses
        const list = res.data || res.venues || res || [];
        const totalCount = res.meta?.total ?? res.total ?? list.length;

        if (append) {
          this.venues = [...this.venues, ...list];
        } else {
          this.venues = list;
        }
        this.total = totalCount;
      } catch (err) {
        this.error = err.message || 'Không thể tải danh sách sân. Vui lòng thử lại.';
      } finally {
        this.loading = false;
        this.loadingMore = false;
      }
    },

    onSearchInput() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => {
        this.fetchVenues();
      }, 400);
    },

    clearSearch() {
      this.searchQuery = '';
      this.fetchVenues();
    },

    toggleSport(value) {
      this.selectedSport = this.selectedSport === value ? '' : value;
      this.fetchVenues();
    },

    clearAllFilters() {
      this.searchQuery = '';
      this.selectedSport = '';
      this.fetchVenues();
    },

    loadMore() {
      this.page += 1;
      this.fetchVenues(true);
    },

    goToVenue(id) {
      this.$router.push(`/venues/${id}`);
    },

    getVenueImage(venue) {
      const media = venue.media || venue.images || [];
      if (Array.isArray(media) && media.length > 0) {
        return media[0]?.url || media[0]?.original_url || media[0];
      }
      return null;
    },

    formatPrice(price) {
      return new Intl.NumberFormat('vi-VN').format(price) + 'đ';
    },

    initMap() {
      const container = this.$refs.mapContainer;
      if (!container) return;

      if (this.map) {
        this.destroyMap();
      }

      this.map = L.map(container, {
        zoomControl: false
      }).setView([21.028511, 105.785], 12);

      L.control.zoom({ position: 'bottomright' }).addTo(this.map);

      const isDark = document.documentElement.classList.contains('dark') || !document.documentElement.classList.contains('light');
      const tileUrl = isDark 
        ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
        : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';

      this.tileLayer = L.tileLayer(tileUrl, {
        attribution: '&copy; OpenStreetMap &copy; CARTO'
      }).addTo(this.map);

      this.markersMap = {};
      this.updateMapMarkers();
    },

    updateMapMarkers() {
      if (!this.map) return;

      if (this.markersGroup) {
        this.map.removeLayer(this.markersGroup);
      }
      this.markersGroup = L.layerGroup().addTo(this.map);
      this.markersMap = {};

      const points = [];

      this.venues.forEach(venue => {
        if (!venue.latitude || !venue.longitude) return;

        const lat = Number(venue.latitude);
        const lng = Number(venue.longitude);
        points.push([lat, lng]);

        const icon = L.divIcon({
          className: 'custom-map-marker',
          html: `<div class="marker-pin"></div>`,
          iconSize: [24, 24],
          iconAnchor: [12, 12]
        });

        const marker = L.marker([lat, lng], { icon })
          .addTo(this.markersGroup);

        marker.bindPopup(`
          <div class="map-popup-content">
            <h4 class="popup-title">${venue.name}</h4>
            <p class="popup-address">${venue.address || ''}</p>
            <a href="/venues/${venue.id}" class="popup-link">Xem chi tiết &rarr;</a>
          </div>
        `, {
          closeButton: false,
          className: 'custom-map-popup'
        });

        marker.on('mouseover', () => {
          this.hoveredVenueId = venue.id;
        });
        marker.on('mouseleave', () => {
          this.hoveredVenueId = null;
        });
        marker.on('click', () => {
          this.goToVenue(venue.id);
        });

        this.markersMap[venue.id] = marker;
      });

      if (points.length > 0) {
        this.map.fitBounds(points, { padding: [50, 50] });
      }
    },

    highlightMarker(id) {
      if (!this.map || !this.markersMap) return;

      Object.keys(this.markersMap).forEach(key => {
        const marker = this.markersMap[key];
        const el = marker.getElement();
        if (el) el.classList.remove('active');
      });

      if (id && this.markersMap[id]) {
        const marker = this.markersMap[id];
        const el = marker.getElement();
        if (el) el.classList.add('active');
        marker.openPopup();
      }
    },

    destroyMap() {
      if (this.map) {
        this.map.remove();
        this.map = null;
        this.tileLayer = null;
        this.markersGroup = null;
        this.markersMap = null;
      }
    },

    handleThemeChanged(e) {
      if (!this.map || !this.tileLayer) return;
      const isDark = e.detail.isDark;
      const tileUrl = isDark
        ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
        : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
      this.tileLayer.setUrl(tileUrl);
    },
  },
};
</script>

<style scoped>
/* ─── Layout Base ─── */
.venue-list-page {
  min-height: 100vh;
  padding-top: 64px;
  background: #09090b;
  color: #ffffff;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* ─── Page Header ─── */
.page-header {
  padding: 100px 24px 0;
  max-width: 1280px;
  margin: 0 auto;
}
.page-title {
  font-size: 36px;
  font-weight: 800;
  letter-spacing: -1px;
  color: #ffffff;
  margin: 0 0 8px;
}
.page-subtitle {
  font-size: 16px;
  color: rgba(255, 255, 255, 0.45);
  margin: 0;
}

/* ─── Search Bar ─── */
.search-bar-wrapper {
  position: sticky;
  top: 64px;
  z-index: 50;
  background: rgba(9, 9, 11, 0.85);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  padding: 14px 24px;
}
.search-bar-inner {
  max-width: 1280px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.search-input-group {
  position: relative;
  flex: 1;
  min-width: 220px;
}
.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 16px;
  height: 16px;
  color: rgba(255, 255, 255, 0.35);
  pointer-events: none;
}
.search-input {
  width: 100%;
  padding: 10px 36px 10px 38px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  color: #ffffff;
  font-size: 14px;
  font-family: inherit;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}
.search-input::placeholder { color: rgba(255, 255, 255, 0.3); }
.search-input:focus { border-color: rgba(255, 255, 255, 0.2); }
.search-clear {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  padding: 2px;
  color: rgba(255, 255, 255, 0.35);
  cursor: pointer;
  transition: color 0.15s;
}
.search-clear svg { width: 14px; height: 14px; }
.search-clear:hover { color: #ffffff; }

/* Sport Tags */
.sport-tags {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.sport-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 9999px;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  font-family: inherit;
}
.sport-tag:hover {
  background: rgba(255, 255, 255, 0.07);
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.15);
}
.sport-tag.active {
  background: #ffffff;
  color: #09090b;
  border-color: #ffffff;
  font-weight: 600;
}
.sport-tag-icon { font-size: 14px; }

/* Toolbar Right */
.toolbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-left: auto;
}
.result-count {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.4);
  white-space: nowrap;
}
.view-toggle {
  display: flex;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 8px;
  overflow: hidden;
}
.toggle-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  font-size: 13px;
  font-family: inherit;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.5);
  cursor: pointer;
  transition: all 0.2s;
}
.toggle-btn svg { width: 14px; height: 14px; }
.toggle-btn:hover { color: rgba(255, 255, 255, 0.8); }
.toggle-btn.active {
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
}

/* ─── Content Area ─── */
.content-area {
  max-width: 1280px;
  margin: 0 auto;
  padding: 32px 24px 64px;
}

/* ─── GRID VIEW ─── */
.venue-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}
.venue-card {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
  overflow: hidden;
  text-decoration: none;
  color: inherit;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
}
.venue-card:hover {
  border-color: rgba(255, 255, 255, 0.18);
  transform: translateY(-3px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
  background: rgba(255, 255, 255, 0.04);
}

/* Card Image */
.card-img-wrapper {
  position: relative;
  width: 100%;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.03);
}
.card-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.4s ease;
}
.venue-card:hover .card-img { transform: scale(1.04); }
.card-img-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.08);
  letter-spacing: -1px;
}
.card-tags {
  position: absolute;
  bottom: 8px;
  left: 8px;
  display: flex;
  gap: 4px;
}
.card-tag {
  padding: 2px 8px;
  background: rgba(9, 9, 11, 0.8);
  backdrop-filter: blur(6px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.8);
}

/* Card Info */
.card-info {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex-grow: 1;
}
.card-name {
  font-size: 15px;
  font-weight: 700;
  color: #ffffff;
  margin: 0;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.card-address {
  display: flex;
  align-items: flex-start;
  gap: 4px;
  font-size: 12.5px;
  color: rgba(255, 255, 255, 0.4);
  line-height: 1.4;
}
.card-address svg { flex-shrink: 0; margin-top: 1px; }
.card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
}
.card-price {
  display: flex;
  align-items: baseline;
  gap: 3px;
}
.price-from { font-size: 11px; color: rgba(255, 255, 255, 0.35); }
.price-value { font-size: 16px; font-weight: 700; color: #ffffff; }
.price-unit { font-size: 11px; color: rgba(255, 255, 255, 0.35); }
.card-book-btn {
  font-size: 12.5px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.5);
  transition: color 0.2s;
}
.venue-card:hover .card-book-btn { color: #ffffff; }

/* ─── Skeleton ─── */
.venue-card.skeleton {
  pointer-events: none;
  cursor: default;
}
.skeleton-img {
  width: 100%;
  aspect-ratio: 16 / 9;
  background: linear-gradient(90deg, rgba(255,255,255,0.03) 25%, rgba(255,255,255,0.06) 50%, rgba(255,255,255,0.03) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
.skeleton-body { padding: 16px; display: flex; flex-direction: column; gap: 8px; }
.skeleton-line {
  height: 12px;
  border-radius: 4px;
  background: linear-gradient(90deg, rgba(255,255,255,0.03) 25%, rgba(255,255,255,0.06) 50%, rgba(255,255,255,0.03) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
.w-70 { width: 70%; }
.w-50 { width: 50%; }
.w-40 { width: 40%; }
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ─── State Box ─── */
.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 24px;
  text-align: center;
  gap: 12px;
}
.state-icon {
  width: 48px;
  height: 48px;
  color: rgba(255, 255, 255, 0.2);
}
.state-box p { font-size: 15px; color: rgba(255, 255, 255, 0.45); margin: 0; }
.retry-btn {
  margin-top: 8px;
  padding: 8px 20px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: #ffffff;
  font-size: 14px;
  font-family: inherit;
  cursor: pointer;
  transition: all 0.2s;
}
.retry-btn:hover { background: rgba(255, 255, 255, 0.12); }

/* ─── Load More ─── */
.load-more-wrapper {
  margin-top: 32px;
  display: flex;
  justify-content: center;
}
.load-more-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 32px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 9999px;
  color: rgba(255, 255, 255, 0.7);
  font-size: 14px;
  font-family: inherit;
  cursor: pointer;
  transition: all 0.2s;
}
.load-more-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
  border-color: rgba(255, 255, 255, 0.15);
}
.load-more-btn:disabled { opacity: 0.5; cursor: default; }

/* ─── MAP VIEW ─── */
.map-view {
  /* Override padding for full height */
  margin: -32px -24px -64px;
}
.map-layout {
  display: grid;
  grid-template-columns: 380px 1fr;
  height: calc(100vh - 64px - 65px); /* viewport minus navbar and search bar */
  overflow: hidden;
}

/* Map Sidebar (List) */
.map-sidebar {
  border-right: 1px solid rgba(255, 255, 255, 0.06);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: rgba(12, 12, 14, 0.95);
}
.map-sidebar-header {
  padding: 16px 20px 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  flex-shrink: 0;
}
.map-sidebar-count {
  font-size: 13px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.5);
}
.map-list {
  overflow-y: auto;
  flex-grow: 1;
}
.map-list::-webkit-scrollbar { width: 4px; }
.map-list::-webkit-scrollbar-track { background: transparent; }
.map-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }

.map-list-item {
  width: 100%;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 20px;
  text-align: left;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  cursor: pointer;
  transition: background 0.15s;
  font-family: inherit;
  color: inherit;
  background: transparent;
  border-left: 3px solid transparent;
}
.map-list-item:hover,
.map-list-item.highlighted {
  background: rgba(255, 255, 255, 0.04);
  border-left-color: rgba(255, 255, 255, 0.3);
}
.map-item-img {
  width: 52px;
  height: 52px;
  border-radius: 8px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.06);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.map-item-img img { width: 100%; height: 100%; object-fit: cover; }
.map-item-initials { font-size: 14px; font-weight: 800; color: rgba(255,255,255,0.2); }
.map-item-info { flex-grow: 1; min-width: 0; }
.map-item-name {
  font-size: 14px;
  font-weight: 600;
  color: #ffffff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.map-item-address {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.35);
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.map-item-tags {
  display: flex;
  gap: 4px;
  margin-top: 4px;
  flex-wrap: wrap;
}
.map-item-tag {
  font-size: 10.5px;
  padding: 1px 6px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 4px;
  color: rgba(255, 255, 255, 0.5);
}
.map-item-price {
  font-size: 12px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.6);
  margin-top: 4px;
}
.map-item-arrow {
  width: 14px;
  height: 14px;
  color: rgba(255, 255, 255, 0.2);
  flex-shrink: 0;
  margin-top: 4px;
  transition: color 0.15s;
}
.map-list-item:hover .map-item-arrow { color: rgba(255, 255, 255, 0.6); }

/* Skeleton in map sidebar */
.map-list-skeleton { padding: 8px 0; }
.map-list-item-skeleton {
  display: flex;
  gap: 12px;
  padding: 14px 20px;
  align-items: flex-start;
}
.sk-img {
  width: 52px;
  height: 52px;
  border-radius: 8px;
  flex-shrink: 0;
  background: linear-gradient(90deg, rgba(255,255,255,0.03) 25%, rgba(255,255,255,0.06) 50%, rgba(255,255,255,0.03) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
.sk-text { flex-grow: 1; display: flex; flex-direction: column; gap: 8px; padding-top: 4px; }
.sk-line {
  height: 12px;
  border-radius: 4px;
  background: linear-gradient(90deg, rgba(255,255,255,0.03) 25%, rgba(255,255,255,0.06) 50%, rgba(255,255,255,0.03) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
.w-80 { width: 80%; }

.map-load-more, .map-loading-more {
  width: 100%;
  padding: 14px 20px;
  text-align: center;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.45);
  border: none;
  background: none;
  cursor: pointer;
  font-family: inherit;
  transition: color 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.map-load-more:hover { color: #ffffff; }

/* Map Area */
.map-container {
  position: relative;
  overflow: hidden;
  background: #111113;
}
.map-iframe {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}

/* Leaflet custom marker */
:deep(.custom-map-marker) {
  background: none;
  border: none;
}
:deep(.marker-pin) {
  width: 24px;
  height: 24px;
  border-radius: 50% 50% 50% 0;
  background: #ffffff;
  position: absolute;
  transform: rotate(-45deg);
  left: 50%;
  top: 50%;
  margin: -12px 0 0 -12px;
  border: 2px solid #09090b;
  box-shadow: 0 4px 10px rgba(0,0,0,0.5);
  transition: all 0.2s ease;
}
:deep(.marker-pin::after) {
  content: '';
  width: 8px;
  height: 8px;
  margin: 6px 0 0 6px;
  background: #09090b;
  position: absolute;
  border-radius: 50%;
  transition: all 0.2s ease;
}
:deep(.custom-map-marker.active .marker-pin) {
  background: #ffffff;
  transform: rotate(-45deg) scale(1.2);
  border-color: #ffffff;
  box-shadow: 0 6px 16px rgba(255,255,255,0.4);
}
:deep(.custom-map-marker.active .marker-pin::after) {
  background: #000000;
}

/* Light mode overrides for map markers and popups */
.light :deep(.marker-pin) {
  background: #0f172a;
  border-color: #ffffff;
}
.light :deep(.marker-pin::after) {
  background: #ffffff;
}
.light :deep(.custom-map-marker.active .marker-pin) {
  background: #0f172a;
  border-color: #0f172a;
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.3);
}
.light :deep(.custom-map-marker.active .marker-pin::after) {
  background: #ffffff;
}

.light :deep(.custom-map-popup .leaflet-popup-content-wrapper) {
  background: #ffffff !important;
  border: 1px solid rgba(0, 0, 0, 0.08) !important;
  color: #0f172a !important;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08) !important;
}
.light :deep(.custom-map-popup .leaflet-popup-tip) {
  background: #ffffff !important;
  border: 1px solid rgba(0, 0, 0, 0.08) !important;
}
.light :deep(.popup-title) {
  color: #0f172a !important;
}
.light :deep(.popup-address) {
  color: rgba(0, 0, 0, 0.5) !important;
}
.light :deep(.popup-link) {
  color: #0f172a !important;
}

/* Custom Popup */
:deep(.custom-map-popup) {
  margin-bottom: 12px;
}
:deep(.custom-map-popup .leaflet-popup-content-wrapper) {
  background: #18181b;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  color: #ffffff;
  padding: 8px 12px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.5);
}
:deep(.custom-map-popup .leaflet-popup-tip) {
  background: #18181b;
  border: 1px solid rgba(255, 255, 255, 0.08);
}
:deep(.map-popup-content) {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
:deep(.popup-title) {
  font-size: 13.5px;
  font-weight: 700;
  margin: 0;
  color: #ffffff;
}
:deep(.popup-address) {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.5);
  margin: 0;
  line-height: 1.3;
}
:deep(.popup-link) {
  font-size: 11.5px;
  font-weight: 600;
  color: #ffffff;
  text-decoration: underline;
  margin-top: 4px;
  display: inline-block;
}
.map-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  gap: 16px;
  padding: 40px;
  text-align: center;
}
.map-ph-icon {
  width: 52px;
  height: 52px;
  color: rgba(255, 255, 255, 0.1);
}
.map-ph-title {
  font-size: 18px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.4);
  margin: 0;
}
.map-ph-desc {
  font-size: 13.5px;
  color: rgba(255, 255, 255, 0.25);
  max-width: 360px;
  margin: 0;
  line-height: 1.6;
}
.map-ph-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: center;
  margin-top: 8px;
}
.map-ph-badge {
  padding: 6px 14px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 9999px;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.5);
  cursor: pointer;
  transition: all 0.2s;
}
.map-ph-badge:hover,
.map-ph-badge.active {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.15);
  color: #ffffff;
}

/* ─── Spinner ─── */
.spinner-sm {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── Responsive ─── */
@media (max-width: 900px) {
  .map-layout {
    grid-template-columns: 1fr;
    grid-template-rows: 1fr 0;
  }
  .map-container { display: none; }
  .map-sidebar { border-right: none; }
  .search-bar-inner { gap: 8px; }
  .sport-tags { order: 3; width: 100%; }
  .toolbar-right { margin-left: 0; }
}
@media (max-width: 640px) {
  .page-title { font-size: 28px; }
  .content-area { padding: 20px 16px 48px; }
  .venue-grid { grid-template-columns: 1fr; gap: 14px; }
  .search-bar-wrapper { padding: 12px 16px; }
  .sport-tag { font-size: 12px; padding: 6px 10px; }
  .toggle-btn { padding: 6px 10px; }
}
</style>

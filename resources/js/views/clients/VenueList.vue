<template>
  <div class="bg-sportgo-bg min-h-screen">
    <PublicNavbar />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12 pt-24 md:pt-28 flex flex-col md:flex-row gap-8">

      <!-- Sidebar Filters -->
      <div class="w-full md:w-72 flex-shrink-0">
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-5 sticky top-24">
          <h3 class="font-black text-gray-900 border-b border-gray-100 pb-4 text-lg">Bộ lọc tìm kiếm</h3>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Từ khóa</label>
            <input
              v-model.trim="filters.q"
              type="text"
              placeholder="Tên sân, địa chỉ..."
              class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-sportgo-accent focus:border-sportgo-accent transition-colors font-medium placeholder:text-gray-400"
              @keyup.enter="loadVenues"
            />
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Loại sân</label>
            <select
              v-model="filters.court_type_id"
              class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-sportgo-accent focus:border-sportgo-accent font-medium text-gray-900"
              @change="loadVenues"
            >
              <option value="">Tất cả các loại</option>
              <option v-for="type in courtTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Khu vực</label>
            <input
              v-model.trim="filters.area"
              type="text"
              placeholder="Cầu Giấy, Mỹ Đình..."
              class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-sportgo-accent focus:border-sportgo-accent transition-colors font-medium placeholder:text-gray-400"
              @keyup.enter="loadVenues"
            />
          </div>

          <div>
            <label class="block text-sm font-bold text-gray-700 mb-2">Rating tối thiểu</label>
            <select
              v-model="filters.min_rating"
              class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm bg-gray-50 focus:bg-white focus:ring-2 focus:ring-sportgo-accent focus:border-sportgo-accent font-medium text-gray-900"
              @change="loadVenues"
            >
              <option value="">Tất cả</option>
              <option value="3">Từ 3 sao</option>
              <option value="4">Từ 4 sao</option>
              <option value="4.5">Từ 4.5 sao</option>
            </select>
          </div>

          <button
            class="w-full py-3 bg-sportgo-accent hover:bg-sportgo-dark text-white rounded-xl font-black transition-colors shadow-sm"
            @click="loadVenues"
          >Lọc sân</button>
        </div>
      </div>

      <!-- Main Content -->
      <div class="flex-1 space-y-6">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-2xl border border-gray-200 shadow-sm">
          <div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Danh sách các sân</h1>
            <p class="text-sm text-gray-500 font-medium">
              {{ loading ? 'Đang tải...' : `Tìm thấy ${venues.length} kết quả` }}
            </p>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-20">
          <div class="w-10 h-10 border-4 border-sportgo-accent border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-2xl p-8 text-center text-red-700 font-bold">
          {{ error }}
        </div>

        <!-- Empty -->
        <div v-else-if="venues.length === 0" class="bg-white p-12 rounded-2xl border border-gray-200 text-center">
          <p class="text-gray-500 font-medium mb-4">Không tìm thấy sân nào phù hợp với bộ lọc.</p>
          <button
            class="px-6 py-2 border border-gray-300 rounded-xl text-sm font-bold hover:bg-gray-50 transition-colors"
            @click="resetFilters"
          >Xoá bộ lọc</button>
        </div>

        <!-- Venue Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="venue in venues" :key="venue.id"
            class="flex flex-col bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden cursor-pointer hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group"
            @click="$router.push({ name: 'venue-detail', params: { id: venue.slug || venue.id } })"
          >
            <div class="h-48 bg-gray-200 relative overflow-hidden">
              <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-emerald-100 to-blue-100 text-4xl font-black text-emerald-600">
                {{ venue.name.slice(0,2).toUpperCase() }}
              </div>
              <img
                v-if="imageUrl(venue.image_path)"
                :src="imageUrl(venue.image_path)"
                :alt="venue.name"
                class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                @error="hideBrokenImage"
              />
              <div class="absolute top-3 left-3 flex flex-wrap gap-2">
                <span
                  v-for="type in (venue.court_types || []).slice(0,2)" :key="type.id"
                  class="bg-white/90 backdrop-blur-md text-gray-900 text-[10px] font-black px-2.5 py-1.5 rounded-lg shadow-sm uppercase tracking-wide"
                >{{ type.name }}</span>
              </div>
              <div v-if="venue.rating_avg" class="absolute top-3 right-3 bg-white/90 backdrop-blur-md text-xs font-black px-2.5 py-1.5 rounded-lg shadow-sm flex items-center gap-1 text-gray-900">
                ⭐ {{ venue.rating_avg }}
              </div>
            </div>

            <div class="p-5 flex-1 flex flex-col justify-between">
              <div>
                <h3 class="font-black text-gray-900 text-lg group-hover:text-sportgo-accent transition-colors line-clamp-1 mb-2">{{ venue.name }}</h3>
                <p class="text-sm text-gray-500 mb-4 flex items-center gap-1.5 font-medium line-clamp-1">
                  <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                  </svg>
                  {{ venue.address }}
                </p>
              </div>

              <div class="flex justify-between items-end border-t border-gray-100 pt-4">
                <div>
                  <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-1">Giá từ</p>
                  <p class="font-black text-sportgo-accent text-lg leading-none">
                    {{ venue.min_price ? formatCurrency(venue.min_price) : 'Liên hệ' }}
                    <span v-if="venue.min_price" class="text-xs font-medium text-gray-500">/h</span>
                  </p>
                </div>
                <button class="text-xs font-bold bg-gray-50 border border-gray-200 text-sportgo-accent group-hover:bg-sportgo-accent group-hover:text-white px-4 py-2 rounded-xl transition-colors">
                  Xem Sân
                </button>
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
import { courtTypeService } from '../../services/courtTypes.js';
import { venueService } from '../../services/venues.js';

export default {
  name: 'VenueList',
  components: { PublicNavbar },
  data() {
    return {
      venues: [],
      courtTypes: [],
      loading: true,
      error: '',
      filters: {
        q: '',
        court_type_id: '',
        area: '',
        min_rating: '',
      },
    };
  },
  async mounted() {
    this.filters = {
      ...this.filters,
      q: this.$route.query.q || '',
      court_type_id: this.$route.query.court_type_id || '',
      area: this.$route.query.area || '',
      min_rating: this.$route.query.min_rating || '',
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
      this.error = '';
      try {
        const response = await venueService.list(this.filters);
        this.venues = response.data || [];
      } catch (error) {
        this.error = error.message || 'Không thể tải danh sách sân.';
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = { q: '', court_type_id: '', area: '', min_rating: '' };
      this.loadVenues();
    },
    imageUrl(path) {
      if (!path) return '';
      if (/^https?:\/\//.test(path)) return path;
      return `/storage/${path}`;
    },
    hideBrokenImage(event) {
      event.target.style.display = 'none';
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
      }).format(Number(amount || 0));
    },
  },
};
</script>

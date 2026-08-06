<template>
  <div class="alb-app sg-client-page">
    <PublicNavbar />

    <main>
      <!-- PAGE HEADER & PILL SEARCH BAR -->
      <section class="alb-section" style="background: #0f172a; color: #ffffff; padding: 48px 0 36px;">
        <div class="sg-container">
          <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px; margin-bottom: 32px;">
            <div>
              <span style="font-size: 12px; font-weight: 600; text-transform: uppercase; color: #4ade80; letter-spacing: 1px; margin-bottom: 8px; display: block;">TÌM KIẾM CỤM SÂN</span>
              <h1 style="font-size: 28px; font-weight: 600; color: #ffffff; margin-bottom: 8px;">Hệ Thống Sân Thể Thao Đạt Chuẩn</h1>
              <p style="font-size: 14.5px; color: #ffffff;">
                {{ loading ? "Đang tìm kiếm sân trống phù hợp..." : `Tìm thấy ${venues.length} cụm sân khả dụng` }}
              </p>
            </div>
            <router-link to="/bookings" class="alb-btn-owner" style="background: #1e293b; border: 1px solid #334155;">
              <span>Lịch đã đặt của tôi</span>
            </router-link>
          </div>

          <!-- Integrated Pill Search Bar -->
          <PillSearchBar />
        </div>
      </section>

      <!-- SEARCH & FILTER CONTENT -->
      <section class="alb-section">
        <div class="sg-container" style="display: grid; grid-template-columns: 280px 1fr; gap: 32px;">
          <!-- FILTER SIDEBAR (Frameless Flat Layout) -->
          <aside style="background: transparent; border: none; padding: 0; align-self: flex-start;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
              <h2 style="font-size: 15px; font-weight: 500; color: #111827;">Bộ Lọc Tìm Kiếm</h2>
              <button type="button" style="background: transparent; border: none; font-size: 13px; color: #15803d; cursor: pointer; font-weight: 400;" @click="resetFilters">Xóa bộ lọc</button>
            </div>

            <div style="display: flex; flex-direction: column; gap: 18px;">
              <!-- Search text -->
              <div>
                <label style="font-size: 13.5px; font-weight: 400; color: #0f172a; display: block; margin-bottom: 6px;">Từ khóa / Tên sân</label>
                <div class="alb-search-input-wrap" style="background: #ffffff; border: 1.5px solid #1e293b; border-radius: 8px; padding: 4px 10px;">
                  <input v-model.trim="filters.q" type="search" placeholder="Ví dụ: Cầu lông Ba Đình..." style="width: 100%; border: none !important; outline: none !important; font-size: 14px; font-weight: 400; color: #0f172a; background: transparent;" @keyup.enter="applyFilters" />
                </div>
              </div>

              <!-- Court Type -->
              <div>
                <label style="font-size: 13.5px; font-weight: 400; color: #0f172a; display: block; margin-bottom: 6px;">Môn thể thao</label>
                <ClientCombobox
                  v-model="filters.court_type_id"
                  :options="courtTypeOptions"
                  placeholder="Tất cả bộ môn"
                  @change="applyFilters"
                />
              </div>

              <!-- Dropdown 1: Tỉnh / Thành phố -->
              <div>
                <label style="font-size: 13.5px; font-weight: 400; color: #0f172a; display: block; margin-bottom: 6px;">Tỉnh / Thành phố</label>
                <ClientCombobox
                  v-model="filters.province_code"
                  :options="provinceOptions"
                  placeholder="Tất cả Tỉnh/Thành"
                  @change="onProvinceChange"
                />
              </div>

              <!-- Dropdown 2: Phường / Xã -->
              <div>
                <label style="font-size: 13.5px; font-weight: 400; color: #0f172a; display: block; margin-bottom: 6px;">Phường / Xã</label>
                <ClientCombobox
                  v-model="filters.ward_code"
                  :options="wardOptions"
                  placeholder="Tất cả Phường/Xã"
                  :disabled="!filters.province_code"
                  @change="onWardChange"
                />
              </div>

              <button class="alb-search-btn" type="button" style="width: 100%; justify-content: center; margin-top: 8px;" @click="applyFilters">
                <span>Áp Dụng Tìm Kiếm</span>
              </button>
            </div>
          </aside>

          <!-- VENUES LIST GRID -->
          <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 4px;">
              <span style="font-size: 15px; font-weight: 500; color: #111827;">Hiển thị {{ venues.length }} kết quả</span>
              <div style="display: flex; gap: 12px; align-items: center;">
                <select v-model="filters.sort" style="background: #ffffff; border: 1.5px solid #1e293b; padding: 6px 12px; border-radius: 6px; font-size: 13.5px; color: #111827; font-weight: 400;" @change="applyFilters">
                  <option value="recommended">Gợi ý phù hợp</option>
                  <option value="price">Giá thấp trước</option>
                  <option value="rating">Đánh giá cao</option>
                </select>
              </div>
            </div>

            <div v-if="loading" style="text-align: center; padding: 60px 0; color: #374151;">
              Đang tải danh sách sân...
            </div>

            <div v-else-if="venues.length === 0" class="sg-empty-state-wrapper">
              <div class="sg-empty-anim-container" style="width: 200px; height: 200px;">
                <!-- Floating Question Marks -->
                <div class="sg-qmark sg-qmark-left" style="top: 10px; left: -10px;">?</div>
                <div class="sg-qmark sg-qmark-right" style="top: 30px; right: -10px;">?</div>

                <!-- Generated Vector Art Illustration -->
                <img :src="'/images/sports_player_search_empty.png'" alt="Không tìm thấy sân" class="sg-empty-img" />
              </div>

              <h3 style="font-size: 17px; font-weight: 500; color: #0f172a; margin-top: 16px; margin-bottom: 6px;">Không tìm thấy cụm sân phù hợp</h3>
              <p style="font-size: 14px; color: #475569; margin-bottom: 20px; max-width: 420px; margin-left: auto; margin-right: auto;">Vui lòng thử điều chỉnh lại từ khóa tìm kiếm hoặc mở rộng phạm vi khu vực.</p>
              <button class="alb-search-btn" type="button" style="display: inline-flex; font-weight: 400; padding: 10px 24px;" @click="resetFilters">Xem Tất Cả Cụm Sân</button>
            </div>

            <div v-else class="alb-venue-grid">
              <article v-for="venue in venues" :key="venue.id" class="alb-venue-card">
                <div class="alb-venue-card__thumb">
                  <img :src="venueImage(venue)" :alt="venue.name" loading="lazy" />
                  <span class="alb-venue-card__badge">{{ venue.court_types?.[0]?.name || "Sân Thể Thao" }}</span>
                  <div class="alb-venue-card__rating">
                    <span>{{ formatRating(venue) }} sao</span>
                  </div>
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

        // If query has province_code, fetch its wards
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
        const res = await venueService.getVenues(this.filters);
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
      if (!img) return fallbackImage;
      if (/^https?:\/\//.test(img)) return img;
      return img.startsWith("/") ? img : `/storage/${img}`;
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

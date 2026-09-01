<template>
  <div class="sg-pill-search-container">
    <!-- Main Floating Pill Search Bar -->
    <div class="sg-pill-bar">
      <!-- Search Input Section -->
      <div class="sg-pill-section sg-pill-search-input-wrap">
        <input
          v-model.trim="searchQuery"
          type="text"
          class="sg-pill-input"
          placeholder="Tìm kiếm..."
          @keyup.enter="handleSearch"
        />
      </div>

      <!-- Divider -->
      <div class="sg-pill-divider"></div>

      <!-- Filter Modal Trigger -->
      <button
        type="button"
        class="sg-pill-btn"
        :class="{ 'is-active': activeFilterCount > 0 }"
        @click="showFilterModal = true"
      >
        <span>Bộ lọc</span>
        <span v-if="activeFilterCount > 0" class="sg-pill-badge">{{ activeFilterCount }}</span>
      </button>

      <!-- Divider -->
      <div class="sg-pill-divider"></div>

      <!-- Map View Button -->
      <button type="button" class="sg-pill-btn" @click="goToMap">
        <span>Bản đồ</span>
      </button>

      <!-- Divider -->
      <div class="sg-pill-divider"></div>

      <!-- Bookings Button -->
      <button type="button" class="sg-pill-btn" @click="goToBookings">
        <span>Sân đã đặt</span>
      </button>

      <!-- Divider -->
      <div class="sg-pill-divider"></div>

      <!-- Favorites Button -->
      <button type="button" class="sg-pill-btn" @click="goToFavorites">
        <span>Yêu thích</span>
      </button>
    </div>

    <!-- Filter Modal Popover -->
    <div v-if="showFilterModal" class="sg-filter-backdrop" @click.self="showFilterModal = false">
      <div class="sg-filter-modal-card">
        <!-- Modal Header -->
        <div class="sg-filter-modal-header">
          <h3 class="sg-filter-modal-title">Bộ Lọc Tìm Kiếm Nâng Cao</h3>
          <button type="button" class="sg-filter-close-btn" @click="showFilterModal = false">Đóng</button>
        </div>

        <!-- Modal Body Form -->
        <div class="sg-filter-modal-body">
          <!-- Dropdown 1: Tỉnh / Thành phố -->
          <div class="sg-filter-field">
            <label>Tỉnh / Thành phố</label>
            <ClientCombobox
              v-model="filters.province_code"
              :options="provinceOptions"
              placeholder="Tất cả Tỉnh/Thành"
              @change="onProvinceChange"
            />
          </div>

          <!-- Dropdown 2: Phường / Xã -->
          <div class="sg-filter-field">
            <label>Phường / Xã</label>
            <ClientCombobox
              v-model="filters.ward_code"
              :options="wardOptions"
              placeholder="Tất cả Phường/Xã"
              :disabled="!filters.province_code"
              @change="onWardChange"
            />
          </div>

          <!-- Field 3: Môn thể thao -->
          <div class="sg-filter-field">
            <label>Môn thể thao</label>
            <ClientCombobox
              v-model="filters.court_type_id"
              :options="courtTypeOptions"
              placeholder="Tất cả bộ môn"
            />
          </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="sg-filter-modal-footer">
          <button type="button" class="sg-btn-reset" @click="resetFilters">
            <span>Xóa bộ lọc</span>
          </button>
          <button type="button" class="sg-btn-apply" @click="applyFilters">
            <span>Áp dụng tìm kiếm</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ClientCombobox from "./ClientCombobox.vue";
import ClientDatePicker from "./ClientDatePicker.vue";
import ClientTimeSlots from "./ClientTimeSlots.vue";
import { api } from "../services/api.js";
import { courtTypeService } from "../services/courtTypes.js";
import { businessDateString } from "../utils/businessTime.js";

export default {
  name: "PillSearchBar",
  components: {
    ClientCombobox,
    ClientDatePicker,
    ClientTimeSlots,
  },
  data() {
    return {
      today: businessDateString(),
      searchQuery: "",
      showFilterModal: false,
      provincesList: [],
      wardsList: [],
      courtTypes: [],
      timeOptions: ["06:00", "08:00", "10:00", "14:00", "16:00", "18:00", "20:00"],
      filters: {
        province_code: "",
        province_name: "",
        ward_code: "",
        ward_name: "",
        booking_date: businessDateString(),
        start_time: "18:00:00",
        court_type_id: "",
      },
    };
  },
  computed: {
    activeFilterCount() {
      let count = 0;
      if (this.filters.province_code) count++;
      if (this.filters.ward_code) count++;
      if (this.filters.court_type_id) count++;
      if (this.filters.booking_date !== this.today) count++;
      return count;
    },
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
    this.loadCourtTypes();
    this.syncFromRoute();
  },
  methods: {
    syncFromRoute() {
      const q = this.$route.query;
      if (q.q) this.searchQuery = q.q;
      if (q.province_code) this.filters.province_code = q.province_code;
      if (q.ward_code) this.filters.ward_code = q.ward_code;
      if (q.court_type_id) this.filters.court_type_id = q.court_type_id;
      if (q.booking_date) this.filters.booking_date = q.booking_date;
      if (q.start_time) this.filters.start_time = q.start_time;
    },
    async loadProvinces() {
      try {
        const res = await api("/api/locations/provinces");
        this.provincesList = res.data || res || [];
        if (this.filters.province_code) {
          this.fetchWards(this.filters.province_code);
        }
      } catch (e) {
        this.provincesList = [];
      }
    },
    async loadCourtTypes() {
      try {
        const types = await courtTypeService.getCourtTypes();
        this.courtTypes = types || [];
      } catch (e) {
        this.courtTypes = [];
      }
    },
    async fetchWards(provinceCode) {
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
        await this.fetchWards(this.filters.province_code);
      } else {
        this.filters.province_name = "";
      }
    },
    onWardChange() {
      const w = this.wardsList.find((x) => String(x.code) === String(this.filters.ward_code));
      this.filters.ward_name = w ? w.name : "";
    },
    handleSearch() {
      this.pushNavigation();
    },
    applyFilters() {
      this.showFilterModal = false;
      this.pushNavigation();
    },
    resetFilters() {
      this.filters = {
        province_code: "",
        province_name: "",
        ward_code: "",
        ward_name: "",
        booking_date: businessDateString(),
        start_time: "18:00:00",
        court_type_id: "",
      };
      this.wardsList = [];
      this.showFilterModal = false;
      this.pushNavigation();
    },
    pushNavigation() {
      const areaParts = [this.filters.ward_name, this.filters.province_name].filter(Boolean);
      this.$router.push({
        name: "venues",
        query: {
          q: this.searchQuery || undefined,
          area: areaParts.join(", ") || undefined,
          province_code: this.filters.province_code || undefined,
          ward_code: this.filters.ward_code || undefined,
          court_type_id: this.filters.court_type_id || undefined,
          booking_date: this.filters.booking_date,
          start_time: this.filters.start_time,
        },
      });
    },
    goToMap() {
      this.$router.push("/map");
    },
    goToBookings() {
      this.$router.push("/bookings");
    },
    goToFavorites() {
      this.$router.push({ name: "venues", query: { filter: "favorites" } });
    },
  },
};
</script>

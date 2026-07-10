<template>
  <div class="venue-detail-page">
    <PublicNavbar />

    <main>
      <div v-if="loading" class="state-screen">
        <div class="spinner"></div>
        <p>Đang tải thông tin sân...</p>
      </div>

      <div v-else-if="error" class="state-screen">
        <p>{{ error }}</p>
        <button type="button" @click="$router.push({ name: 'venues', query: searchQuery })">Quay lại tìm sân</button>
      </div>

      <template v-else-if="venue">
        <section class="hero-band">
          <div class="detail-container">
            <nav class="breadcrumbs" aria-label="Duong dan">
              <router-link :to="{ name: 'home' }">Trang chủ</router-link>
              <span>/</span>
              <router-link :to="{ name: 'venues', query: searchQuery }">Tìm sân</router-link>
              <span>/</span>
              <strong>{{ venue.name }}</strong>
            </nav>

            <div class="hero-grid">
              <div class="gallery">
                <div class="gallery-main">
                  <img v-if="activeImage" :src="activeImage" :alt="venue.name" @error="activeImage = ''" />
                  <div v-else class="gallery-empty">{{ initials(venue.name) }}</div>
                </div>
                <div v-if="gallery.length > 1" class="gallery-thumbs">
                  <button
                    v-for="image in gallery"
                    :key="image"
                    type="button"
                    :class="{ active: image === activeImage }"
                    @click="activeImage = image"
                  >
                    <img :src="image" :alt="venue.name" />
                  </button>
                </div>
              </div>

              <div class="hero-copy">
                <div class="type-row">
                  <span v-for="type in courtTypes" :key="type.id">{{ type.name }}</span>
                  <span v-if="!courtTypes.length">Đa môn</span>
                </div>
                <h1>{{ venue.name }}</h1>
                <p class="address">{{ fullAddress }}</p>

                <div class="hero-stats">
                  <div>
                    <strong>{{ courtCount }}</strong>
                    <span>Sân hoạt động</span>
                  </div>
                  <div>
                    <strong>{{ ratingLabel }}</strong>
                    <span>Đánh giá</span>
                  </div>
                  <div>
                    <strong>{{ priceLabel }}</strong>
                    <span>Giá tham khảo</span>
                  </div>
                </div>

                <div class="hero-actions">
                  <button type="button" class="primary-action" @click="goToBooking">Đặt sân</button>
                  <button type="button" class="ghost-action" @click="chatWithOwner">Nhắn tin</button>
                  <router-link class="ghost-action" :to="{ name: 'venues', query: searchQuery }">Đổi sân</router-link>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="detail-container detail-layout">
          <div class="detail-main">
            <section class="detail-section" v-if="venue.description">
              <h2>Thông tin sân</h2>
              <p class="description">{{ venue.description }}</p>
            </section>

            <section class="detail-section" v-if="amenities.length">
              <h2>Tiện ích</h2>
              <div class="amenity-grid">
                <article v-for="amenity in amenities" :key="amenity.id || amenity.name" class="amenity-item">
                  <strong>{{ amenity.name || amenity }}</strong>
                  <span v-if="amenity.description">{{ amenity.description }}</span>
                </article>
              </div>
            </section>

            <section class="detail-section" v-if="courtGroups.length">
              <h2>Loại sân và sân con</h2>
              <div class="court-groups">
                <article v-for="group in courtGroups" :key="group.typeId" class="court-group">
                  <div>
                    <h3>{{ group.typeName }}</h3>
                    <span>{{ group.courts.length }} sân</span>
                  </div>
                  <p>{{ group.courts.map((court) => court.name).join(", ") }}</p>
                </article>
              </div>
            </section>

            <section class="detail-section">
              <h2>Khung giờ hoạt động</h2>
              <div class="hours-grid">
                <article v-for="item in operatingHours" :key="item.day">
                  <strong>{{ item.day }}</strong>
                  <span>{{ item.value }}</span>
                </article>
              </div>
            </section>

            <section class="detail-section">
              <h2>Chính sách sân</h2>
              <div class="policy-grid">
                <article v-for="policy in policies" :key="policy.label">
                  <strong>{{ policy.label }}</strong>
                  <span>{{ policy.value }}</span>
                </article>
              </div>
            </section>

            <section class="detail-section" v-if="basePrices.length || priceSlots.length">
              <h2>Bảng giá</h2>
              <div class="price-list">
                <article v-for="price in basePrices" :key="`base-${price.id}`">
                  <span>{{ price.court_type?.name || "Tất cả loại sân" }}</span>
                  <strong>{{ formatCurrency(price.price) }}/giờ</strong>
                </article>
                <article v-for="slot in priceSlots" :key="`slot-${slot.id}`">
                  <span>{{ slot.court_type?.name || "Tất cả loại sân" }} · {{ timeLabel(slot.start_time) }} - {{ timeLabel(slot.end_time) }}</span>
                  <strong>{{ formatCurrency(slot.price) }}/giờ</strong>
                </article>
              </div>
            </section>

            <section class="detail-section">
              <h2>Đánh giá</h2>
              <div v-if="reviews.length" class="review-list">
                <article v-for="review in reviews" :key="review.id" class="review-item">
                  <div>
                    <strong>{{ review.author_name || "Khách hàng" }}</strong>
                    <span>{{ Number(review.rating || 0).toFixed(1) }} ★</span>
                  </div>
                  <p>{{ review.content }}</p>
                </article>
              </div>
              <p v-else class="muted-text">Sân chưa có đánh giá công khai.</p>
            </section>

            <section class="detail-section" v-if="venue.map_url || venue.latitude">
              <h2>Vị trí</h2>
              <iframe
                v-if="mapEmbedUrl"
                class="map-frame"
                :src="mapEmbedUrl"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
              ></iframe>
              <p v-else class="muted-text">{{ fullAddress }}</p>
            </section>
          </div>

          <aside class="booking-panel">
            <div class="booking-box">
              <strong>{{ priceLabel }}</strong>
              <span>{{ courtCount }} sân · {{ courtTypeSummary }}</span>

              <label>
                Ngày chơi
                <input v-model="bookDate" type="date" :min="minDate" />
              </label>

              <label v-if="courtTypes.length">
                Loại sân
                <select v-model="bookCourtType">
                  <option value="">Tất cả loại sân</option>
                  <option v-for="type in courtTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                </select>
              </label>

              <button type="button" class="primary-action full" @click="goToBooking">Xem lịch trống và đặt sân</button>
              <button type="button" class="ghost-action full" @click="chatWithOwner">Nhắn tin hỏi chủ sân</button>
            </div>
          </aside>
        </section>
      </template>
    </main>
  </div>
</template>

<script>
import PublicNavbar from "../../components/PublicNavbar.vue";
import { venueService } from "../../services/venues.js";

const fallbackImage = "/images/home/badminton-cover.webp";
const dayLabels = ["Chủ nhật", "Thứ 2", "Thứ 3", "Thứ 4", "Thứ 5", "Thứ 6", "Thứ 7"];

export default {
  name: "VenueDetail",
  components: { PublicNavbar },
  data() {
    return {
      venue: null,
      loading: true,
      error: "",
      gallery: [],
      activeImage: "",
      bookDate: this.todayStr(),
      bookCourtType: "",
    };
  },
  computed: {
    searchQuery() {
      const query = { ...this.$route.query };
      delete query.id;
      return query;
    },
    amenities() {
      return this.venue?.amenities_detail?.length ? this.venue.amenities_detail : (this.venue?.amenities || []);
    },
    courtTypes() {
      return this.venue?.court_types || [];
    },
    courtTypeSummary() {
      const names = this.courtTypes.map((type) => type.name).filter(Boolean);
      return names.length ? names.slice(0, 3).join(", ") : "Đa môn";
    },
    courtCount() {
      return Number(this.venue?.court_count || this.venue?.venue_courts?.length || 0);
    },
    fullAddress() {
      return [this.venue?.address, this.venue?.ward, this.venue?.province].filter(Boolean).join(", ") || "Đang cập nhật địa chỉ";
    },
    ratingLabel() {
      const rating = Number(this.venue?.rating_avg || 0);
      return rating > 0 ? `${rating.toFixed(1)} ★` : "Mới";
    },
    priceLabel() {
      return this.venue?.min_price ? `Từ ${this.formatCurrency(this.venue.min_price)}/giờ` : "Liên hệ giá";
    },
    courtGroups() {
      const groups = {};
      (this.venue?.venue_courts || []).forEach((court) => {
        const typeId = court.court_type?.id || "other";
        const typeName = court.court_type?.name || "Khác";
        if (!groups[typeId]) groups[typeId] = { typeId, typeName, courts: [] };
        groups[typeId].courts.push(court);
      });
      return Object.values(groups);
    },
    operatingHours() {
      const hours = this.venue?.operating_hours || {};
      const weekly = hours.weekly_operating_hours || {};

      if (hours.fixed_open_time && hours.fixed_close_time) {
        return [{ day: "Mỗi ngày", value: `${this.timeLabel(hours.fixed_open_time)} - ${this.timeLabel(hours.fixed_close_time)}` }];
      }

      if (Object.keys(weekly).length) {
        return dayLabels.map((day, index) => {
          const value = weekly[index] || weekly[String(index)] || {};
          if (value.is_open === false) return { day, value: "Đóng cửa" };
          return { day, value: `${this.timeLabel(value.open_time || "05:00:00")} - ${this.timeLabel(value.close_time || "22:00:00")}` };
        });
      }

      return [{ day: "Mỗi ngày", value: "05:00 - 22:00" }];
    },
    policies() {
      const policy = this.venue?.policies || {};
      const payment = [
        policy.allow_full_payment ? "trả đủ" : "",
        policy.allow_deposit ? `đặt cọc${policy.deposit_percent ? ` ${Number(policy.deposit_percent)}%` : ""}` : "",
        policy.allow_no_prepay ? "trả sau" : "",
      ].filter(Boolean).join(", ");

      return [
        { label: "Thanh toán", value: payment || "Theo cấu hình của sân" },
        { label: "Đặt trước tối thiểu", value: policy.min_advance_booking_minutes ? `${policy.min_advance_booking_minutes} phút` : "30 phút" },
        { label: "Giữ chỗ thanh toán", value: policy.slot_hold_minutes ? `${policy.slot_hold_minutes} phút` : "Theo hệ thống" },
        { label: "Hủy sân", value: policy.cancel_before_hours ? `Trước ${policy.cancel_before_hours} giờ, hoàn ${policy.refund_percent || 0}%` : "Theo chính sách sân" },
      ];
    },
    basePrices() {
      return this.venue?.base_prices || [];
    },
    priceSlots() {
      return this.venue?.price_slots || [];
    },
    reviews() {
      return this.venue?.reviews || [];
    },
    minDate() {
      return this.todayStr();
    },
    mapEmbedUrl() {
      if (this.venue?.latitude && this.venue?.longitude) {
        return `https://www.google.com/maps?q=${this.venue.latitude},${this.venue.longitude}&output=embed`;
      }
      if (this.venue?.map_url && this.venue.map_url.includes("google.com/maps/embed")) {
        return this.venue.map_url;
      }
      return "";
    },
  },
  mounted() {
    this.bookDate = this.$route.query.booking_date || this.$route.query.date || this.todayStr();
    this.bookCourtType = this.$route.query.court_type_id || this.$route.query.court_type || "";
    this.fetchVenue();
  },
  methods: {
    async fetchVenue() {
      this.loading = true;
      this.error = "";
      try {
        const response = await venueService.show(this.$route.params.id);
        this.venue = response.data || response;
        const images = (this.venue.gallery || []).map((path) => this.imageUrl(path)).filter(Boolean);
        if (this.venue.image_path) images.unshift(this.imageUrl(this.venue.image_path));
        this.gallery = [...new Set(images.length ? images : [fallbackImage])];
        this.activeImage = this.gallery[0] || "";
      } catch (error) {
        this.error = error.message || "Không thể tải thông tin sân.";
      } finally {
        this.loading = false;
      }
    },
    goToBooking() {
      const query = {
        venue_cluster_id: this.venue.id,
        cluster: this.venue.id,
        booking_date: this.bookDate,
        date: this.bookDate,
        start_time: this.$route.query.start_time || "18:00:00",
        end_time: this.$route.query.end_time || "19:00:00",
      };
      if (this.bookCourtType) query.court_type = this.bookCourtType;
      this.$router.push({ name: "booking-create", query });
    },
    chatWithOwner() {
      this.$router.push({ name: "chat", query: { venueId: this.venue.id } });
    },
    imageUrl(path) {
      if (!path) return "";
      if (/^https?:\/\//.test(path)) return path;
      return `/storage/${path}`;
    },
    initials(name = "") {
      return String(name).trim().slice(0, 2).toUpperCase() || "SG";
    },
    todayStr() {
      return new Date().toISOString().slice(0, 10);
    },
    timeLabel(time) {
      return String(time || "").slice(0, 5);
    },
    formatCurrency(amount) {
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
        maximumFractionDigits: 0,
      }).format(Number(amount || 0));
    },
  },
};
</script>

<style scoped>
.venue-detail-page {
  min-height: 100vh;
  background: #f4f7f5;
  color: #111827;
}

main {
  padding-top: 64px;
}

.detail-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 28px;
}

.hero-band {
  padding: 28px 0 36px;
  background: #0e3b2c;
  color: #fff;
}

.breadcrumbs,
.hero-actions,
.hero-stats,
.venue-detail-page .type-row {
  display: flex;
  align-items: center;
}

.breadcrumbs {
  gap: 8px;
  margin-bottom: 22px;
  color: rgba(255, 255, 255, .72);
  font-size: 13px;
  font-weight: 800;
}

.breadcrumbs a {
  color: inherit;
  text-decoration: none;
}

.hero-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(360px, .75fr);
  gap: 32px;
  align-items: start;
}

.gallery {
  display: grid;
  gap: 10px;
}

.gallery-main {
  aspect-ratio: 16 / 9;
  overflow: hidden;
  border-radius: 8px;
  background: rgba(255, 255, 255, .08);
}

.gallery-main img,
.gallery-thumbs img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.gallery-empty {
  display: grid;
  height: 100%;
  place-items: center;
  color: rgba(255, 255, 255, .35);
  font-size: 60px;
  font-weight: 950;
}

.gallery-thumbs {
  display: flex;
  gap: 8px;
  overflow-x: auto;
}

.gallery-thumbs button {
  width: 82px;
  height: 58px;
  overflow: hidden;
  border: 2px solid transparent;
  border-radius: 8px;
  flex: 0 0 auto;
}

.gallery-thumbs button.active {
  border-color: #fff;
}

.type-row {
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.type-row span {
  padding: 5px 9px;
  border: 1px solid rgba(255, 255, 255, .2);
  border-radius: 7px;
  color: rgba(255, 255, 255, .86);
  font-size: 12px;
  font-weight: 850;
}

.hero-copy h1 {
  margin: 0 0 12px;
  font-size: 38px;
  line-height: 1.1;
  font-weight: 950;
}

.address {
  margin: 0 0 22px;
  color: rgba(255, 255, 255, .76);
  line-height: 1.55;
  font-weight: 750;
}

.hero-stats {
  gap: 14px;
  margin-bottom: 24px;
}

.hero-stats div {
  min-width: 108px;
  padding: 13px;
  border: 1px solid rgba(255, 255, 255, .14);
  border-radius: 8px;
  background: rgba(255, 255, 255, .08);
}

.hero-stats strong,
.hero-stats span {
  display: block;
}

.hero-stats strong {
  font-size: 18px;
  font-weight: 950;
}

.hero-stats span {
  margin-top: 4px;
  color: rgba(255, 255, 255, .68);
  font-size: 12px;
  font-weight: 750;
}

.hero-actions {
  gap: 10px;
  flex-wrap: wrap;
}

.primary-action,
.ghost-action,
.state-screen button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
  padding: 0 16px;
  border-radius: 8px;
  font-weight: 900;
  text-decoration: none;
}

.primary-action {
  background: #0b8f50;
  color: #fff;
}

.hero-band .primary-action {
  background: #fff;
  color: #0e3b2c;
}

.ghost-action {
  border: 1px solid #dce5df;
  color: #344039;
}

.hero-band .ghost-action {
  border-color: rgba(255, 255, 255, .24);
  color: #fff;
}

.detail-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 330px;
  gap: 26px;
  padding-top: 28px;
  padding-bottom: 56px;
}

.detail-main {
  display: grid;
  gap: 18px;
}

.detail-section,
.booking-box {
  border: 1px solid #dfe7e2;
  border-radius: 8px;
  background: #fff;
}

.detail-section {
  padding: 22px;
}

.detail-section h2 {
  margin: 0 0 16px;
  font-size: 18px;
  font-weight: 950;
}

.description {
  margin: 0;
  color: #526159;
  line-height: 1.75;
  white-space: pre-line;
}

.amenity-grid,
.hours-grid,
.policy-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}

.amenity-item,
.hours-grid article,
.policy-grid article,
.court-group,
.price-list article,
.review-item {
  border: 1px solid #e4ebe7;
  border-radius: 8px;
  background: #f8fbf9;
}

.amenity-item,
.hours-grid article,
.policy-grid article {
  display: grid;
  gap: 4px;
  padding: 12px;
}

.amenity-item span,
.hours-grid span,
.policy-grid span,
.muted-text {
  color: #66756d;
  font-size: 13px;
  line-height: 1.5;
}

.court-groups,
.price-list,
.review-list {
  display: grid;
  gap: 10px;
}

.court-group,
.price-list article,
.review-item {
  padding: 14px;
}

.court-group div,
.price-list article,
.review-item div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.court-group h3,
.court-group p,
.review-item p {
  margin: 0;
}

.court-group span,
.court-group p,
.price-list span,
.review-item span,
.review-item p {
  color: #66756d;
  font-size: 13px;
}

.court-group p,
.review-item p {
  margin-top: 8px;
  line-height: 1.55;
}

.price-list strong {
  color: #0b8f50;
  white-space: nowrap;
}

.map-frame {
  width: 100%;
  height: 320px;
  border: 0;
  border-radius: 8px;
}

.booking-panel {
  position: sticky;
  top: 88px;
  align-self: start;
}

.booking-box {
  display: grid;
  gap: 14px;
  padding: 18px;
}

.booking-box > strong {
  color: #0b8f50;
  font-size: 20px;
}

.booking-box > span {
  color: #66756d;
  font-weight: 750;
}

.booking-box label {
  display: grid;
  gap: 7px;
  color: #344039;
  font-size: 13px;
  font-weight: 850;
}

.booking-box input,
.booking-box select {
  height: 42px;
  border: 1px solid #dce5df;
  border-radius: 8px;
  padding: 0 12px;
  color: #111827;
  font-weight: 750;
}

.full {
  width: 100%;
}

.state-screen {
  display: grid;
  min-height: calc(100vh - 64px);
  place-items: center;
  align-content: center;
  gap: 14px;
  color: #526159;
  font-weight: 800;
}

.state-screen button {
  background: #0b8f50;
  color: #fff;
}

.spinner {
  width: 34px;
  height: 34px;
  border: 3px solid #dce8e1;
  border-top-color: #0b8f50;
  border-radius: 50%;
  animation: spin .8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 1000px) {
  .hero-grid,
  .detail-layout {
    grid-template-columns: 1fr;
  }

  .booking-panel {
    position: static;
  }
}

@media (max-width: 640px) {
  main {
    padding-top: 58px;
  }

  .detail-container {
    padding: 0 18px;
  }

  .hero-copy h1 {
    font-size: 30px;
  }

  .hero-stats {
    align-items: stretch;
    flex-direction: column;
  }

  .court-group div,
  .price-list article,
  .review-item div {
    flex-direction: column;
  }
}
</style>

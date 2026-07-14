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
                  <button type="button" class="ghost-action" @click="chatWithVenue">Nhắn tin</button>
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

          <!-- On-site Services & Products -->
          <section class="detail-section" v-if="groupedServices.length">
            <h2 class="section-title">Dịch vụ & Sản phẩm tại sân</h2>
            <div class="services-by-category-container">
              <div v-for="group in groupedServices" :key="group.key" class="service-category-block">
                <h3 class="service-category-label">
                  {{ group.label }}
                </h3>
                <div class="services-list-grid">
                  <div v-for="item in group.items" :key="item.id" class="service-product-item">
                    <div class="product-copy">
                      <span class="product-name">{{ item.name }}</span>
                      <span v-if="item.description" class="product-desc">{{ item.description }}</span>
                    </div>
                    <div class="product-value">
                      <span class="product-price">{{ formatPrice(item.price) }}</span>
                      <span class="product-unit">/ {{ item.unit }}</span>
                    </div>
                  </div>
                </div>
              </div>
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

          <aside class="booking-panel" ref="bookingPanelRef">
            <div class="booking-form">
              <div class="booking-flow">
                <span class="active">1. Chọn ngày</span>
                <span>2. Xem lịch</span>
                <span>3. Xác nhận</span>
              </div>

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

              <label v-if="courtTypes.length">
                Loại sân
                <select v-model="bookCourtType">
                  <option value="">Tất cả loại sân</option>
                  <option v-for="type in courtTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                </select>
              </label>

              <section class="mini-schedule" aria-label="Lịch trống nhanh">
                <div class="mini-schedule-head">
                  <div>
                    <strong>Lịch trống nhanh</strong>
                    <span v-if="!previewLoading && !previewError">
                      {{ previewAvailableCourtCount }} sân còn lịch
                    </span>
                  </div>
                  <button type="button" title="Tải lại lịch" @click="loadMiniSchedule">
                    ↻
                  </button>
                </div>

                <div v-if="previewLoading" class="mini-schedule-state">
                  Đang kiểm tra lịch...
                </div>
                <div v-else-if="previewError" class="mini-schedule-state error">
                  {{ previewError }}
                </div>
                <div v-else-if="miniScheduleSlots.length" class="mini-slot-list">
                  <button
                    v-for="slot in miniScheduleSlots"
                    :key="slot.start_time"
                    type="button"
                    :disabled="slot.available_count === 0 || slot.is_past"
                    :class="{
                      full: slot.available_count === 0,
                      past: slot.is_past,
                    }"
                    @click="goToBooking(slot)"
                  >
                    <strong>{{ shortTime(slot.start_time) }}</strong>
                    <span v-if="slot.is_past">Đã qua</span>
                    <span v-else-if="slot.available_count > 0">
                      {{ slot.available_count }}/{{ slot.total_count }} sân trống
                    </span>
                    <span v-else>Đã kín</span>
                  </button>
                </div>
                <div v-else class="mini-schedule-state">
                  {{ miniScheduleEmptyMessage }}
                </div>
              </section>

              <button
                id="btn-view-schedule"
                class="btn-primary btn-full"
                :disabled="!bookDate"
                @click="goToBooking()"
              >
                Xem toàn bộ lịch &amp; Đặt sân
              </button>

              <button
                class="btn-outline btn-full chat-action"
                @click="chatWithVenue"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                Nhắn tin với cụm sân
              </button>

              <p class="panel-note">Chọn ngày để xem khung giờ còn trống</p>

              <div class="support-actions" aria-label="Hỗ trợ và an toàn">
                <button type="button" @click="openComplaint">
                  <AppIcon name="messageWarning" size="16" />
                  Khiếu nại sân
                </button>
                <button type="button" @click="openReport">
                  <AppIcon name="alert" size="16" />
                  Báo cáo sân
                </button>
              </div>
            </div>
          </aside>
        </section>
      </template>
    </main>

    <ComplaintModal
      :is-open="showComplaintModal"
      initial-type="venue"
      :initial-venue-id="venue?.id || ''"
      :initial-venue-name="venue?.name || ''"
      @close="showComplaintModal = false"
      @success="onComplaintSuccess"
    />
    <ReportModal
      :is-open="showReportModal"
      target-type="venue"
      :target-id="venue?.id || ''"
      :target-name="venue?.name || ''"
      @close="showReportModal = false"
      @success="onReportSuccess"
    />
  </div>
</template>

<script>
import PublicNavbar from "../../components/PublicNavbar.vue";
import AppIcon from "../../components/AppIcon.vue";
import ComplaintModal from "../../components/ComplaintModal.vue";
import ReportModal from "../../components/ReportModal.vue";
import { venueService } from "../../services/venues.js";
import { getAuth } from "../../stores/auth.js";
import { useToast } from "vue-toastification";

export default {
  name: "VenueDetail",
  components: { PublicNavbar, AppIcon, ComplaintModal, ReportModal },
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      venue: null,
      loading: true,
      error: "",
      gallery: [],
      activeImage: "",
      bookDate: this.todayStr(),
      bookCourtType: '',
      previewLoading: false,
      previewError: '',
      previewSchedule: {
        time_slots: [],
        courts: [],
        slot_statuses: [],
      },
      showComplaintModal: false,
      showReportModal: false,
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

    groupedServices() {
      const services = this.venue?.services || [];
      const groups = {};
      
      services.forEach(item => {
        const catId = item.category_id || 'other';
        const catName = item.category?.name || 'Dịch vụ khác';
        if (!groups[catId]) {
          groups[catId] = {
            key: catId,
            label: catName,
            items: []
          };
        }
        groups[catId].items.push(item);
      });
      
      return Object.values(groups);
    },

    priceSlots() {
      return this.venue?.price_slots || [];
    },
    basePrices() {
      return this.venue?.base_prices || [];
    },
    policies() {
      const policy = this.venue?.policies || {};
      const hours = this.venue?.operating_hours || {};
      const paymentMethods = [];
      if (policy.allow_full_payment) paymentMethods.push("Thanh toán toàn bộ");
      if (policy.allow_deposit) {
        paymentMethods.push(policy.deposit_percent
          ? `Đặt cọc ${Number(policy.deposit_percent).toLocaleString("vi-VN")}%`
          : "Đặt cọc");
      }
      if (policy.allow_no_prepay) paymentMethods.push("Thanh toán tại sân");

      return [
        {
          label: "Giờ hoạt động",
          value: hours.fixed_open_time && hours.fixed_close_time
            ? `${this.timeLabel(hours.fixed_open_time)} - ${this.timeLabel(hours.fixed_close_time)}`
            : "Theo lịch từng ngày",
        },
        {
          label: "Đặt trước tối thiểu",
          value: this.durationLabel(policy.min_advance_booking_minutes),
        },
        {
          label: "Hủy và hoàn tiền",
          value: policy.cancel_before_hours !== null && policy.cancel_before_hours !== undefined
            ? `Trước ${policy.cancel_before_hours} giờ · hoàn ${Number(policy.refund_percent || 0).toLocaleString("vi-VN")}%`
            : "Theo chính sách hiện hành",
        },
        {
          label: "Hình thức thanh toán",
          value: paymentMethods.join(", ") || "Theo cấu hình của sân",
        },
      ];
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

    miniScheduleSlots() {
      const slots = this.previewSchedule.time_slots || [];
      const courts = this.previewSchedule.courts || [];
      const statuses = this.previewSchedule.slot_statuses || [];

      return slots.map(slot => {
        const slotStatuses = statuses.filter(
          status => status.start_time === slot.start_time
            && courts.some(court => court.id === status.venue_court_id),
        );
        const available = slotStatuses.filter(status => status.is_available);

        return {
          ...slot,
          available_count: available.length,
          total_count: courts.length,
          venue_court_id: available[0]?.venue_court_id || '',
          is_past: this.isPreviewSlotPast(slot),
        };
      }).filter(slot => !slot.is_past).slice(0, 10);
    },

    previewAvailableCourtCount() {
      const visibleStartTimes = new Set(
        this.miniScheduleSlots.map(slot => slot.start_time),
      );
      const availableCourtIds = new Set(
        (this.previewSchedule.slot_statuses || [])
          .filter(
            status =>
              status.is_available && visibleStartTimes.has(status.start_time),
          )
          .map(status => status.venue_court_id),
      );
      return availableCourtIds.size;
    },

    miniScheduleEmptyMessage() {
      if (!(this.previewSchedule.time_slots || []).length) {
        return 'Cụm sân không mở cửa trong ngày này.';
      }
      return 'Không còn khung giờ nào trong ngày hôm nay.';
    },
  },
  mounted() {
    this.bookDate = this.$route.query.booking_date || this.$route.query.date || this.todayStr();
    this.bookCourtType = this.$route.query.court_type_id || this.$route.query.court_type || "";
    this.fetchVenue();
  },

  watch: {
    bookDate() {
      if (this.venue) this.loadMiniSchedule();
    },
    bookCourtType() {
      if (this.venue) this.loadMiniSchedule();
    },
  },

  methods: {
    initials(name) {
      return String(name || "SG")
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
    },

    formatCurrency(value) {
      const amount = Number(value);
      if (!Number.isFinite(amount)) return "Đang cập nhật";
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
        maximumFractionDigits: 0,
      }).format(amount);
    },

    timeLabel(value) {
      return String(value || "").slice(0, 5) || "--:--";
    },

    durationLabel(minutes) {
      const total = Number(minutes);
      if (!Number.isFinite(total) || total <= 0) return "Không yêu cầu";
      if (total % 1440 === 0) return `${total / 1440} ngày`;
      if (total % 60 === 0) return `${total / 60} giờ`;
      return `${total} phút`;
    },

    async fetchVenue() {
      this.loading = true;
      this.error = "";
      try {
        const id = this.$route.params.id;
        const res = await venueService.show(id);
        this.venue = res.data || res;

        // Build gallery
        const g = this.venue.gallery || [];
        this.gallery = g.map(path => this.imageUrl(path)).filter(Boolean);
        this.activeImage = this.gallery[0] || null;
        await this.loadMiniSchedule();
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

    async loadMiniSchedule() {
      if (!this.venue?.id || !this.bookDate) return;

      this.previewLoading = true;
      this.previewError = '';
      try {
        const params = { booking_date: this.bookDate };
        if (this.bookCourtType) params.court_type_id = this.bookCourtType;
        const response = await venueService.schedule(this.venue.id, params);
        this.previewSchedule = {
          time_slots: response.time_slots || [],
          courts: response.courts || [],
          slot_statuses: response.slot_statuses || [],
        };
      } catch (error) {
        this.previewError = error.message || 'Không thể kiểm tra lịch trống.';
        this.previewSchedule = { time_slots: [], courts: [], slot_statuses: [] };
      } finally {
        this.previewLoading = false;
      }
    },

    isPreviewSlotPast(slot) {
      if (!slot || this.bookDate !== this.todayStr()) return false;
      const [hour, minute] = String(slot.start_time || '00:00')
        .slice(0, 5)
        .split(':')
        .map(Number);
      const now = new Date();
      return hour * 60 + minute <= now.getHours() * 60 + now.getMinutes();
    },

    shortTime(time) {
      return String(time || '').slice(0, 5);
    },

    goToBooking(slot = null) {
      if (!this.bookDate) return;
      const query = {
        venue_cluster_id: this.venue.id,
        cluster: this.venue.id,
        booking_date: this.bookDate,
        date: this.bookDate,
        start_time: this.$route.query.start_time || "18:00:00",
        end_time: this.$route.query.end_time || "19:00:00",
      };
      if (this.bookCourtType) query.court_type = this.bookCourtType;
      if (slot?.venue_court_id) query.venue_court_id = slot.venue_court_id;
      if (slot?.start_time) query.start_time = slot.start_time;
      if (slot?.end_time) query.end_time = slot.end_time;
      this.$router.push({ name: 'booking-create', query });
    },

    chatWithVenue() {
      if (!this.venue) return;
      this.$router.push({
        path: '/chat',
        query: { venueId: this.venue.id }
      });
    },

    requirePlayer() {
      const auth = getAuth();
      if (!auth) {
        this.toast.info("Vui lòng đăng nhập để sử dụng chức năng hỗ trợ.");
        this.$router.push({ name: "login" });
        return false;
      }
      if (auth.role_group !== "user") {
        this.toast.info("Chức năng này dành cho tài khoản người dùng.");
        return false;
      }
      return true;
    },

    openComplaint() {
      if (this.requirePlayer()) this.showComplaintModal = true;
    },

    openReport() {
      if (this.requirePlayer()) this.showReportModal = true;
    },

    onComplaintSuccess() {
      this.showComplaintModal = false;
      this.toast.success("Khiếu nại đã được ghi nhận. SportGo sẽ phản hồi sau khi kiểm tra.");
    },

    onReportSuccess() {
      this.showReportModal = false;
      this.toast.success("Báo cáo đã được ghi nhận để đội ngũ SportGo kiểm tra.");
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
.btn-primary.never-hover-class-placeholder { background: rgba(255,255,255,0.88); transform: translateY(-1px); }
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
.btn-outline.never-hover-class-placeholder { border-color: rgba(255,255,255,0.4); color: #fff; }

/* ─── Hero ─── */
.hero {
  padding-top: 72px;
  max-width: 1280px;
  margin: 0 auto;
  padding-left: 24px;
  padding-right: 24px;
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
  cursor: pointer;
  transition: border-color 0.2s;
  padding: 0;
  background: none;
}
.thumb-btn img { width: 100%; height: 100%; object-fit: cover; }
.thumb-btn.active { border-color: #ffffff; }
.thumb-btn.never-hover-class-placeholder { border-color: rgba(255,255,255,0.4); }

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
.back-link.never-hover-class-placeholder { color: #ffffff; }

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

.mini-schedule {
  display: grid;
  gap: 10px;
  padding: 12px;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px;
  background: rgba(255,255,255,0.025);
}
.mini-schedule-head,
.mini-schedule-head > div {
  display: flex;
  align-items: center;
}
.mini-schedule-head {
  justify-content: space-between;
  gap: 10px;
}
.mini-schedule-head > div {
  min-width: 0;
  gap: 8px;
}
.mini-schedule-head strong {
  color: #fff;
  font-size: 13px;
}
.mini-schedule-head span {
  color: #86efac;
  font-size: 11px;
  font-weight: 700;
}
.mini-schedule-head button {
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 7px;
  color: rgba(255,255,255,0.65);
  cursor: pointer;
}
.mini-slot-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 7px;
  max-height: 174px;
  overflow-y: auto;
  scrollbar-width: thin;
}
.mini-slot-list button {
  min-width: 0;
  display: grid;
  gap: 2px;
  padding: 9px 10px;
  border: 1px solid rgba(34,197,94,0.28);
  border-radius: 7px;
  background: rgba(34,197,94,0.08);
  color: #fff;
  text-align: left;
  cursor: pointer;
}
.mini-slot-list button:hover:not(:disabled) {
  border-color: #22c55e;
  background: rgba(34,197,94,0.16);
}
.mini-slot-list button strong {
  font-size: 12px;
}
.mini-slot-list button span {
  overflow: hidden;
  color: #86efac;
  font-size: 10px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.mini-slot-list button.full,
.mini-slot-list button.past {
  border-color: rgba(255,255,255,0.07);
  background: rgba(255,255,255,0.035);
  color: rgba(255,255,255,0.42);
  cursor: not-allowed;
}
.mini-slot-list button.full span,
.mini-slot-list button.past span {
  color: rgba(255,255,255,0.3);
}
.mini-schedule-state {
  padding: 14px 10px;
  color: rgba(255,255,255,0.42);
  font-size: 11px;
  font-weight: 700;
  text-align: center;
}
.mini-schedule-state.error {
  color: #fca5a5;
}

.panel-info-list {
  padding: 16px 22px 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  border-top: 1px solid rgba(255,255,255,0.06);
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

/* Light redesign for client venue detail */
.venue-detail-page {
  background: #f4f8f5;
  color: #15231a;
}
.loading-screen,
.error-screen {
  background: #f4f8f5;
}
.spinner {
  border-color: #d9e8dd;
  border-top-color: #20a553;
}
.error-msg {
  color: #c2410c;
}
.btn-primary {
  min-height: 44px;
  justify-content: center;
  border: 1px solid #20a553;
  border-radius: 8px;
  background: #20a553;
  color: #fff;
  box-shadow: 0 10px 22px rgba(32, 165, 83, 0.18);
}
.btn-primary:hover {
  background: #188a43;
}
.btn-outline {
  border: 1px solid #cbdccd;
  background: #fff;
  color: #233329;
  border-radius: 8px;
}
.btn-outline:hover {
  border-color: #20a553;
  color: #168447;
}
.hero {
  padding-top: 96px;
  padding-bottom: 26px;
  border-bottom: 0;
}
.gallery-main {
  background: #fff;
  border: 1px solid #d6e3d8;
  box-shadow: 0 16px 36px rgba(28, 61, 37, 0.08);
}
.gallery-placeholder {
  color: #dce6de;
  background: linear-gradient(135deg, #f8fbf8, #eef6f0);
}
.thumb-btn {
  border-color: #d6e3d8;
  background: #fff;
}
.thumb-btn.active,
.thumb-btn:hover {
  border-color: #20a553;
}
.type-badge {
  background: #eef9f1;
  border-color: #cbead3;
  color: #15733a;
}
.venue-name {
  color: #13241a;
  text-shadow: none;
}
.venue-meta,
.meta-item {
  color: #596a60;
}
.meta-item svg {
  color: #168447;
}
.main-layout {
  padding-top: 24px;
  gap: 28px;
}
.detail-section {
  padding: 22px;
  margin-bottom: 16px;
  border: 1px solid #d6e3d8;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 8px 22px rgba(35, 65, 43, 0.04);
}
.section-title {
  color: #14251b;
}
.description-text,
.price-block-label {
  color: #5e7065;
}
.amenity-item,
.court-chip,
.price-table,
.map-text {
  background: #f8fbf8;
  border-color: #dfe9e1;
}
.amenity-name,
.price-val {
  color: #15231a;
}
.amenity-desc,
.court-type-label,
.court-chip,
.price-row {
  color: #64766b;
}
.price-row {
  background: #fff;
  border-bottom: 1px solid #edf2ee;
}
.price-row.header-row {
  background: #eef6f0;
  color: #56685e;
}
.map-text {
  color: #596a60;
}
.booking-col {
  top: 88px;
}
.booking-panel {
  overflow: hidden;
  border: 1px solid #cfe0d2;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 18px 42px rgba(28, 61, 37, 0.1);
}
.booking-panel-header {
  padding: 18px;
  border-bottom: 1px solid #e2ece4;
  background: linear-gradient(135deg, #f7fbf8, #eef9f1);
}
.panel-title {
  color: #14251b;
}
.panel-price {
  color: #168447;
}
.panel-price-unit,
.panel-note {
  color: #6b7c71;
}
.booking-form {
  padding: 16px 18px;
  gap: 12px;
}
.booking-flow {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
}
.booking-flow span {
  min-height: 34px;
  display: grid;
  place-items: center;
  border: 1px solid #d6e3d8;
  border-radius: 7px;
  background: #f7fbf8;
  color: #5d6f64;
  font-size: 11px;
  font-weight: 800;
  text-align: center;
}
.booking-flow .active {
  border-color: #20a553;
  background: #e8f8ed;
  color: #15733a;
}
.bform-label {
  color: #4f6157;
}
.bform-input {
  background: #fff;
  border-color: #cdded0;
  color: #15231a;
}
.bform-input:focus {
  border-color: #20a553;
  box-shadow: 0 0 0 3px rgba(32, 165, 83, 0.12);
}
.bform-input option {
  background: #fff;
  color: #15231a;
}
.mini-schedule {
  border-color: #d8e7db;
  background: #f7fbf8;
}
.mini-schedule-head strong {
  color: #15231a;
}
.mini-schedule-head span {
  color: #168447;
}
.mini-schedule-head button {
  background: #fff;
  border-color: #cfe0d2;
  color: #168447;
}
.mini-slot-list button {
  border-color: #bfe7c9;
  background: #ecfaf0;
  color: #15231a;
}
.mini-slot-list button:hover:not(:disabled) {
  border-color: #20a553;
  background: #ddf5e3;
}
.mini-slot-list button span {
  color: #168447;
}
.mini-slot-list button.full,
.mini-slot-list button.past {
  border-color: #e4e9e5;
  background: #f1f4f2;
  color: #89968e;
}
.mini-slot-list button.full span,
.mini-slot-list button.past span,
.mini-schedule-state {
  color: #7a887f;
}
.mini-schedule-state.error {
  color: #b91c1c;
}
.panel-info-list {
  border-top-color: #e2ece4;
  background: #fbfdfb;
}
.panel-info-item {
  color: #596a60;
}
.panel-info-item svg {
  color: #20a553;
}
.chat-owner-btn {
  display: inline-flex;
  width: 100%;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 2px;
  font-weight: 700;
}

.service-category-block {
  margin-bottom: 20px;
}

.service-category-label {
  margin: 0 0 10px;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.services-list-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 12px;
}

.service-product-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  border: 1px solid var(--admin-border-soft);
  border-radius: var(--admin-radius);
  background: var(--admin-bg-soft);
}

.product-copy {
  flex: 1;
  min-width: 0;
}

.product-name,
.product-desc,
.product-price,
.product-unit {
  display: block;
}

.product-name,
.product-price {
  color: var(--admin-text);
  font-size: var(--admin-font-size-base);
  font-weight: 600;
}

.product-desc,
.product-unit {
  margin-top: 2px;
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
}

.product-value {
  flex: 0 0 auto;
  text-align: right;
  white-space: nowrap;
}

.product-price {
  color: var(--admin-primary-dark);
}

.chat-action {
  display: inline-flex;
  width: 100%;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 10px;
  font-weight: 500;
}

.support-actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--admin-border-soft);
}

.support-actions button {
  display: inline-flex;
  min-height: 38px;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 10px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: var(--admin-muted);
  font-size: var(--admin-font-size-sm);
  font-weight: 500;
  cursor: pointer;
}

.support-actions button:hover {
  border-color: var(--admin-primary);
  background: var(--admin-primary-soft);
  color: var(--admin-primary-dark);
}

@media (max-width: 1024px) {
  .hero {
    padding-top: 82px;
  }
}
@media (max-width: 640px) {
  .booking-flow {
    grid-template-columns: 1fr;
  }
  .detail-section {
    padding: 16px;
  }
  .support-actions {
    grid-template-columns: 1fr;
  }
}
</style>

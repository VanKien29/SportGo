<template>
  <div class="venue-detail-page">
    <PublicNavbar />

    <main>
      <div v-if="loading && !venue" class="venue-shell-skeleton" aria-busy="true" aria-live="polite">
        <section class="hero-band">
          <div class="detail-container">
            <router-link class="venue-back-link" :to="{ name: 'venues', query: searchQuery }">
              <AppIcon name="chevronLeft" size="16" />
              Quay lại tìm sân
            </router-link>
            <div class="skeleton-hero">
              <span class="skeleton-block skeleton-gallery"></span>
              <div class="skeleton-copy">
                <span class="skeleton-block skeleton-line skeleton-line--short"></span>
                <span class="skeleton-block skeleton-line skeleton-line--title"></span>
                <span class="skeleton-block skeleton-line"></span>
                <span class="skeleton-block skeleton-line"></span>
              </div>
            </div>
          </div>
        </section>
        <div class="detail-container skeleton-tabs" aria-hidden="true">
          <span v-for="tab in venueTabs" :key="tab.id" class="skeleton-block"></span>
        </div>
        <section class="detail-container detail-layout skeleton-layout" aria-hidden="true">
          <div class="detail-main skeleton-block"></div>
          <aside class="booking-panel skeleton-block"></aside>
        </section>
        <p class="skeleton-status">Đang tải thông tin cụm sân...</p>
      </div>

      <div v-else-if="error && !venue" class="state-screen">
        <p>{{ error }}</p>
        <button type="button" @click="$router.push({ name: 'venues', query: searchQuery })">Quay lại tìm sân</button>
      </div>

      <template v-else-if="venue">
        <section class="hero-band">
          <div class="detail-container">
            <router-link class="venue-back-link" :to="{ name: 'venues', query: searchQuery }">
              <AppIcon name="chevronLeft" size="16" />
              Quay lại tìm sân
            </router-link>
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
                  <img v-if="activeImage" :src="activeImage" :alt="venue.name" @error="removeGalleryImage(activeImage)" />
                  <div v-else class="gallery-empty">
                    <span><AppIcon name="image" size="34" /></span>
                    <strong>{{ initials(venue.name) }}</strong>
                    <small>Hình ảnh đang được cập nhật</small>
                  </div>
                </div>
                <div v-if="gallery.length > 1" class="gallery-thumbs">
                  <button
                    v-for="image in gallery"
                    :key="image"
                    type="button"
                    :class="{ active: image === activeImage }"
                    @click="activeImage = image"
                  >
                    <img :src="image" :alt="venue.name" @error="removeGalleryImage(image)" />
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
                    <span>{{ ratingCountLabel }}</span>
                  </div>
                  <div>
                    <strong>{{ priceLabel }}</strong>
                    <span>Giá tham khảo</span>
                  </div>
                </div>

                <div class="hero-actions">
                  <button type="button" class="primary-action" @click="scrollToBooking">
                    <AppIcon name="calendar" size="17" />
                    Chọn lịch trống
                  </button>
                  <button type="button" class="ghost-action" @click="chatWithVenue">
                    <AppIcon name="messageSquare" size="17" />
                    Nhắn tin
                  </button>
                  <router-link class="ghost-action" :to="{ name: 'venues', query: searchQuery }">
                    <AppIcon name="search" size="17" />
                    Đổi sân
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </section>

        <nav class="detail-container venue-tabs" aria-label="Nội dung chi tiết sân">
          <button
            v-for="tab in venueTabs"
            :key="tab.id"
            type="button"
            :class="{ active: activeTab === tab.id }"
            :aria-pressed="activeTab === tab.id"
            @click="setActiveTab(tab.id)"
          >
            <AppIcon :name="tab.icon" :size="17" />
            {{ tab.label }}
            <span v-if="tab.id === 'reviews' && reviewCount">{{ reviewCount }}</span>
          </button>
        </nav>

        <section class="detail-container detail-layout">
          <div class="detail-main">
            <section v-if="activeTab === 'overview' && venue.description" class="detail-section">
              <h2>Thông tin sân</h2>
              <p class="description">{{ venue.description }}</p>
            </section>

            <section v-if="activeTab === 'overview' && amenities.length" class="detail-section">
              <h2>Tiện ích</h2>
              <div class="amenity-grid">
                <article v-for="amenity in amenities" :key="amenity.id || amenity.name" class="amenity-item">
                  <strong>{{ amenity.name || amenity }}</strong>
                  <span v-if="amenity.description">{{ amenity.description }}</span>
                </article>
              </div>
            </section>

            <section v-if="activeTab === 'courts' && courtGroups.length" class="detail-section">
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
          <section v-if="activeTab === 'overview' && groupedServices.length" class="detail-section">
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

            <section v-if="activeTab === 'courts'" class="detail-section">
              <h2>Chính sách sân</h2>
              <div class="policy-grid">
                <article v-for="policy in policies" :key="policy.label">
                  <strong>{{ policy.label }}</strong>
                  <span>{{ policy.value }}</span>
                </article>
              </div>
            </section>

            <section v-if="activeTab === 'courts' && (basePrices.length || priceSlots.length || holidayPrices.length)" class="detail-section">
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
                <article v-for="holiday in holidayPrices" :key="`holiday-${holiday.id}`" class="holiday-price-item">
                  <span>
                    Giá ngày {{ formatDate(holiday.holiday_date) }} · {{ holiday.court_type?.name || "Tất cả loại sân" }}
                    <small v-if="holiday.note">{{ holiday.note }}</small>
                  </span>
                  <strong>{{ formatCurrency(holiday.price) }}/giờ</strong>
                </article>
              </div>
            </section>

            <VenuePostsTab
              v-if="activeTab === 'posts'"
              :venue-id="venue.id"
              :venue-name="venue.name"
            />

            <section v-if="activeTab === 'reviews'" class="detail-section">
              <h2>Đánh giá</h2>
              <p v-if="reviews.length && reviewCount > reviews.length" class="review-preview-note">
                Hiển thị {{ reviews.length }} đánh giá gần nhất trong tổng số {{ reviewCount }} lượt.
              </p>
              <div v-if="reviews.length" class="review-list">
                <article v-for="review in reviews" :key="review.id" class="review-item">
                  <div>
                    <strong>{{ review.author_name || "Khách hàng" }}</strong>
                    <span>{{ Number(review.rating || 0).toFixed(1) }} ★</span>
                  </div>
                  <p v-if="review.content">{{ review.content }}</p>
                  <small v-if="review.created_at">{{ formatDate(review.created_at) }}</small>
                  <div v-if="review.reply_content" class="review-reply">
                    <strong>Phản hồi từ sân</strong>
                    <p>{{ review.reply_content }}</p>
                  </div>
                </article>
              </div>
              <p v-else class="muted-text">Sân chưa có đánh giá công khai.</p>
            </section>

            <section v-if="activeTab === 'location' && (mapEmbedUrl || mapExternalUrl)" class="detail-section">
              <h2>Vị trí</h2>
              <div class="location-summary">
                <div>
                  <span class="location-icon"><AppIcon name="mapPin" size="19" /></span>
                  <p>{{ fullAddress }}</p>
                </div>
                <a v-if="mapExternalUrl" :href="mapExternalUrl" target="_blank" rel="noopener noreferrer">
                  Mở Google Maps
                  <AppIcon name="externalLink" size="15" />
                </a>
                <a v-if="phoneUrl" :href="phoneUrl">
                  Gọi {{ venue.phone_contact }}
                </a>
              </div>
              <iframe
                v-if="mapEmbedUrl"
                class="map-frame"
                :src="mapEmbedUrl"
                :title="`Bản đồ ${venue.name}`"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
              ></iframe>
            </section>

            <section v-if="activeTab === 'overview' && !venue.description && !amenities.length && !groupedServices.length" class="detail-section detail-empty-section">
              <AppIcon name="fileText" :size="26" />
              <h2>Thông tin đang được cập nhật</h2>
              <p class="muted-text">Bạn vẫn có thể xem sân, bảng giá và lịch trống để đặt ngay.</p>
            </section>

            <section v-if="activeTab === 'location' && !mapEmbedUrl && !mapExternalUrl" class="detail-section detail-empty-section">
              <AppIcon name="mapPin" :size="26" />
              <h2>Vị trí đang được cập nhật</h2>
              <p class="muted-text">Hãy nhắn tin với cụm sân để được hướng dẫn đường đi.</p>
            </section>
          </div>

          <aside class="booking-panel" ref="bookingPanelRef">
            <div class="booking-form">
              <header class="booking-panel-header">
                <span><AppIcon name="calendar" size="16" /> Đặt sân nhanh</span>
                <strong>{{ priceLabel }}</strong>
                <p>Chọn ngày và khung giờ còn trống trước khi xác nhận.</p>
              </header>

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

              <label v-if="courtTypes.length" class="bform-group">
                <span class="bform-label">Loại sân</span>
                <select v-model="bookCourtType" class="bform-input">
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
                  <button type="button" title="Tải lại lịch" aria-label="Tải lại lịch trống" @click="loadMiniSchedule">
                    <AppIcon name="refresh" size="16" />
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
                <AppIcon name="calendar" size="17" />
                Xem toàn bộ lịch &amp; đặt sân
              </button>

              <button
                class="btn-outline btn-full chat-action"
                @click="chatWithVenue"
              >
                <AppIcon name="messageSquare" size="17" />
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
import VenuePostsTab from "../../components/VenuePostsTab.vue";
import { venueService } from "../../services/venues.js";
import { getAuth } from "../../stores/auth.js";
import { useToast } from "vue-toastification";

export default {
  name: "VenueDetail",
  components: { PublicNavbar, AppIcon, ComplaintModal, ReportModal, VenuePostsTab },
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
      activeTab: 'overview',
      venueRequestId: 0,
      scheduleRequestId: 0,
      venueTabs: [
        { id: 'overview', label: 'Tổng quan', icon: 'fileText' },
        { id: 'courts', label: 'Sân & giá', icon: 'calendar' },
        { id: 'posts', label: 'Bài đăng', icon: 'newspaper' },
        { id: 'reviews', label: 'Đánh giá', icon: 'star' },
        { id: 'location', label: 'Vị trí', icon: 'mapPin' },
      ],
    };
  },
  computed: {
    searchQuery() {
      const query = { ...this.$route.query };
      delete query.id;
      delete query.tab;
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
    ratingCountLabel() {
      const count = Number(this.venue?.rating_count || 0);
      return count > 0 ? `${count.toLocaleString('vi-VN')} lượt đánh giá` : 'Chưa có đánh giá';
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
    holidayPrices() {
      return this.venue?.holiday_prices || [];
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
    reviewCount() {
      return Number(this.venue?.rating_count || this.reviews.length || 0);
    },
    minDate() {
      return this.todayStr();
    },
    mapEmbedUrl() {
      if (this.venue?.map_url && this.venue.map_url.includes("google.com/maps/embed")) {
        return this.venue.map_url;
      }
      if (this.venue?.latitude && this.venue?.longitude) {
        const query = encodeURIComponent(`${this.venue.latitude},${this.venue.longitude}`);
        return `https://maps.google.com/maps?q=${query}&z=15&output=embed`;
      }
      return "";
    },
    mapExternalUrl() {
      if (this.venue?.latitude && this.venue?.longitude) {
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${this.venue.latitude},${this.venue.longitude}`)}`;
      }
      if (this.venue?.map_url && /^https?:\/\//i.test(this.venue.map_url)) {
        return this.venue.map_url;
      }
      if (this.fullAddress && this.fullAddress !== "Đang cập nhật địa chỉ") {
        return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(this.fullAddress)}`;
      }
      return "";
    },
    phoneUrl() {
      const phone = String(this.venue?.phone_contact || '').replace(/[^\d+]/g, '');
      return phone ? `tel:${phone}` : '';
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
    this.activeTab = this.normalizeTab(this.$route.query.tab);
    this.bookDate = this.normalizeBookingDate(this.$route.query.booking_date || this.$route.query.date);
    this.bookCourtType = this.$route.query.court_type_id || this.$route.query.court_type || "";
    this.fetchVenue();
  },

  watch: {
    '$route.params.id'(nextId, previousId) {
      if (nextId !== previousId) {
        this.venue = null;
        this.gallery = [];
        this.activeImage = '';
        this.fetchVenue();
      }
    },
    '$route.query.tab'(tab) {
      this.activeTab = this.normalizeTab(tab);
    },
    bookDate(value) {
      const normalized = this.normalizeBookingDate(value);
      if (value !== normalized) {
        this.bookDate = normalized;
        return;
      }
      if (this.venue) this.loadMiniSchedule();
    },
    bookCourtType() {
      if (this.venue) this.loadMiniSchedule();
    },
  },

  methods: {
    normalizeBookingDate(value) {
      const today = this.todayStr();
      const candidate = String(value || today);
      if (!/^\d{4}-\d{2}-\d{2}$/.test(candidate) || candidate < today) return today;
      const date = new Date(`${candidate}T00:00:00`);
      if (Number.isNaN(date.getTime()) || date.toLocaleDateString('en-CA') !== candidate) return today;
      return candidate;
    },
    normalizeTab(tab) {
      const value = String(tab || 'overview');
      return this.venueTabs.some((item) => item.id === value) ? value : 'overview';
    },

    setActiveTab(tab) {
      const nextTab = this.normalizeTab(tab);
      this.activeTab = nextTab;
      const query = { ...this.$route.query };
      if (nextTab === 'overview') delete query.tab;
      else query.tab = nextTab;
      this.$router.replace({ name: 'venue-detail', params: { id: this.$route.params.id }, query });
    },

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

    formatDate(value) {
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return '';
      return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
      }).format(date);
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
      const requestId = ++this.venueRequestId;
      this.scheduleRequestId += 1;
      this.loading = true;
      this.error = "";
      try {
        const id = this.$route.params.id;
        const res = await venueService.show(id);
        if (requestId !== this.venueRequestId) return;
        this.venue = res.data || res;

        // Build gallery
        const g = [
          this.venue.image_path,
          this.venue.cover_image,
          this.venue.thumbnail,
          ...(this.venue.gallery || []),
        ];
        this.gallery = [...new Set(g.map(path => this.imageUrl(path)).filter(Boolean))];
        this.activeImage = this.gallery[0] || null;
        this.loadMiniSchedule();
      } catch (err) {
        if (requestId !== this.venueRequestId) return;
        this.error = err.message || 'Không thể tải thông tin sân.';
      } finally {
        if (requestId === this.venueRequestId) this.loading = false;
      }
    },

    imageUrl(path) {
      if (!path) return null;
      if (path.startsWith('http')) return path;
      if (path.startsWith('/')) return path;
      return `/storage/${path}`;
    },

    removeGalleryImage(image) {
      this.gallery = this.gallery.filter((item) => item !== image);
      if (this.activeImage === image) this.activeImage = this.gallery[0] || '';
    },

    onImgError(e) {
      e.target.style.display = 'none';
    },

    todayStr() {
      const today = new Date();
      const year = today.getFullYear();
      const month = String(today.getMonth() + 1).padStart(2, '0');
      const day = String(today.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
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

      const requestId = ++this.scheduleRequestId;
      this.previewLoading = true;
      this.previewError = '';
      try {
        const params = { booking_date: this.bookDate };
        if (this.bookCourtType) params.court_type_id = this.bookCourtType;
        const response = await venueService.schedule(this.venue.id, params);
        if (requestId !== this.scheduleRequestId) return;
        this.previewSchedule = {
          time_slots: response.time_slots || [],
          courts: response.courts || [],
          slot_statuses: response.slot_statuses || [],
        };
      } catch (error) {
        if (requestId !== this.scheduleRequestId) return;
        this.previewError = error.message || 'Không thể kiểm tra lịch trống.';
        this.previewSchedule = { time_slots: [], courts: [], slot_statuses: [] };
      } finally {
        if (requestId === this.scheduleRequestId) this.previewLoading = false;
      }
    },

    isPreviewSlotPast(slot) {
      if (!slot || this.bookDate > this.todayStr()) return false;
      if (this.bookDate < this.todayStr()) return true;
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
        return_to: this.$route.fullPath,
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
        this.$router.push({ name: "login", query: { redirect: this.$route.fullPath } });
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

<style scoped src="../../../css/client-venue-detail.css"></style>

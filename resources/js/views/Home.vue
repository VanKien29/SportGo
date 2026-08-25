<template>
  <div class="alb-app sgx-home">
    <PublicNavbar />

    <main>
      <!-- HERO SECTION -->
      <section class="alb-hero">
        <div class="sg-container alb-hero__grid">
          <div>
            <h1 class="alb-hero__headline">
              SportGo – Nền tảng đặt lịch & quản lý <span>sân thể thao</span> trực tuyến
            </h1>
            <p class="alb-hero__subtitle">
              Giải pháp chuyên biệt cho Pickleball, Cầu Lông, Tennis & Bóng Đá. Tìm cụm sân gần bạn, xem sơ đồ giờ trống và giữ chỗ tức thì.
            </p>

            <div class="alb-hero-actions">
              <router-link class="hero-btn primary" to="/venues">
                <AppIcon name="calendar" :size="16" />
                Tìm sân đặt ngay
              </router-link>
              <router-link class="hero-btn secondary" to="/become-partner">
                Dành cho chủ sân
              </router-link>
            </div>
          </div>

          <div class="alb-hero__media">
            <img
              :src="'/images/home/hero_illustration.png'"
              alt="SportGo Sports Platform Illustration"
              class="hero-illustration-img"
            />
          </div>
        </div>
      </section>



      <!-- SECTION 1: ORBITING SPORTS CATEGORIES SHOWCASE -->
      <section class="alb-section">
        <div class="sg-container">
          <header class="alb-section-header" style="text-align: center; margin: 0 auto 32px;">
            <span class="alb-section-tag">HỆ SINH THÁI BỘ MÔN</span>
            <h2 class="alb-section-title">Khám Phá Sân Theo Bộ Môn Thể Thao</h2>
            <p class="alb-section-desc">Hệ thống sân đa dạng được kiểm duyệt chất lượng thảm đấu, hệ thống chiếu sáng và tiện ích chuyên nghiệp</p>
          </header>

          <div class="alb-orbit-container">
            <div class="alb-orbit-wrapper">
              <!-- Center Core 3D Model Illustration (Transparent, No border, No text, No hover) -->
              <div class="alb-orbit-center" title="Hệ sinh thái thể thao SportGo">
                <img
                  :src="'/images/home/sportgo_hub_3d.png'"
                  alt="SportGo 3D Sports Complex Model"
                  class="alb-orbit-center__illus"
                />
              </div>

              <!-- Inner Ring (Ring 1) -->
              <div class="alb-orbit-ring alb-orbit-ring--1">
                <router-link :to="{ name: 'venues', query: { court_type_id: 'pickleball' } }" class="alb-orbit-node">
                  Pickleball Pro
                </router-link>
                <router-link :to="{ name: 'venues', query: { court_type_id: 'badminton' } }" class="alb-orbit-node">
                  Cầu Lông BWF
                </router-link>
              </div>

              <!-- Middle Ring (Ring 2) -->
              <div class="alb-orbit-ring alb-orbit-ring--2">
                <router-link :to="{ name: 'venues', query: { court_type_id: 'football' } }" class="alb-orbit-node">
                  Bóng Đá FIFA
                </router-link>
                <router-link :to="{ name: 'venues', query: { court_type_id: 'tennis' } }" class="alb-orbit-node">
                  Tennis ITF
                </router-link>
                <router-link :to="{ name: 'venues', query: { court_type_id: 'basketball' } }" class="alb-orbit-node">
                  Bóng Rổ FIBA
                </router-link>
                <router-link :to="{ name: 'venues', query: { court_type_id: 'table_tennis' } }" class="alb-orbit-node">
                  Bóng Bàn ITTF
                </router-link>
              </div>

              <!-- Outer Ring (Ring 3) -->
              <div class="alb-orbit-ring alb-orbit-ring--3">
                <router-link :to="{ name: 'venues', query: { court_type_id: 'volleyball' } }" class="alb-orbit-node">
                  Bóng Chuyền
                </router-link>
                <router-link :to="{ name: 'venues', query: { court_type_id: 'swimming' } }" class="alb-orbit-node">
                  Bơi Lội
                </router-link>
                <router-link :to="{ name: 'venues', query: { court_type_id: 'billiards' } }" class="alb-orbit-node">
                  Bida / Billiards
                </router-link>
                <router-link :to="{ name: 'venues', query: { court_type_id: 'golf' } }" class="alb-orbit-node">
                  Golf 3D
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- SECTION 2: DUAL SOLUTION STORYTELLING WITH 3D TRANSPARENT ILLUSTRATIONS -->
      <section class="alb-solution-story">
        <div class="sg-container">
          <header class="alb-section-header alb-section-header--center">
            <span class="alb-section-tag">HỆ SINH THÁI THÔNG MINH</span>
            <h2 class="alb-section-title">Giải Pháp Cho Người Chơi & Chủ Sân</h2>
            <p class="alb-section-desc">Tối ưu trải nghiệm đặt sân cho người chơi và nâng cao hiệu quả kinh doanh cho chủ cụm sân</p>
          </header>

          <div class="alb-story-grid">
            <!-- STORY 1: PLAYERS -->
            <div class="alb-story-card">
              <div class="alb-story-card__header">
                <span class="alb-story-badge">Dành Cho Người Chơi</span>
                <h3 class="alb-story-card__title">Đặt Sân Nhanh - Chọn Đúng Khung Giờ Yêu Thích</h3>
              </div>

              <div class="alb-story-card__media">
                <img
                  :src="'/images/home/player_solution_3d.png'"
                  alt="Giải pháp cho người chơi"
                  class="alb-story-img"
                />
              </div>

              <div class="alb-story-steps">
                <div class="alb-story-step">
                  <span class="alb-story-step__index">01</span>
                  <div class="alb-story-step__content">
                    <h4>Tìm Sân Theo Vị Trí Gần Nhất</h4>
                    <p>Định vị địa lý giúp tìm cụm sân gần bạn, hiển thị khoảng cách và chỉ đường chi tiết.</p>
                  </div>
                </div>

                <div class="alb-story-step">
                  <span class="alb-story-step__index">02</span>
                  <div class="alb-story-step__content">
                    <h4>Sơ Đồ Giờ Trống Realtime</h4>
                    <p>Xem chính xác các ô giờ còn trống, giá tiền theo khung giờ và chọn đúng thảm sân yêu thích.</p>
                  </div>
                </div>

                <div class="alb-story-step">
                  <span class="alb-story-step__index">03</span>
                  <div class="alb-story-step__content">
                    <h4>Ghép Đội & Đăng Bài Xé Vé</h4>
                    <p>Dễ dàng đăng bài tìm đối thủ giao lưu hoặc đăng ký tham gia các suất chơi xé vé lẻ.</p>
                  </div>
                </div>
              </div>

              <div class="alb-story-card__footer">
                <router-link to="/venues" class="alb-story-btn">
                  <span>Khám Phá Cụm Sân Gần Bạn</span>
                </router-link>
              </div>
            </div>

            <!-- STORY 2: OWNERS -->
            <div class="alb-story-card">
              <div class="alb-story-card__header">
                <span class="alb-story-badge alb-story-badge--owner">Dành Cho Chủ Sân Thể Thao</span>
                <h3 class="alb-story-card__title">Quản Lý Thông Minh - Tối Đa Doanh Thu Sân</h3>
              </div>

              <div class="alb-story-card__media">
                <img
                  :src="'/images/home/owner_solution_3d.png'"
                  alt="Giải pháp cho chủ sân"
                  class="alb-story-img"
                />
              </div>

              <div class="alb-story-steps">
                <div class="alb-story-step">
                  <span class="alb-story-step__index">01</span>
                  <div class="alb-story-step__content">
                    <h4>Tối Ưu Tỷ Lệ Lấp Đầy Giờ Trống</h4>
                    <p>Tự động công khai lịch trống lên hệ thống, giảm 90% cuộc gọi hỏi lịch thủ công.</p>
                  </div>
                </div>

                <div class="alb-story-step">
                  <span class="alb-story-step__index">02</span>
                  <div class="alb-story-step__content">
                    <h4>Thu Tiền Giữ Chỗ Qua Mã QR Dynamic</h4>
                    <p>Tích hợp cổng thanh toán tự động đối soát, nhận tiền giữ chỗ ngay lập tức vào tài khoản.</p>
                  </div>
                </div>

                <div class="alb-story-step">
                  <span class="alb-story-step__index">03</span>
                  <div class="alb-story-step__content">
                    <h4>Quản Lý Bán Dịch Vụ & Cho Thuê Vợt</h4>
                    <p>Tích hợp POS bán nước uống, phụ kiện & cho thuê thiết bị và theo dõi doanh thu tổng hợp.</p>
                  </div>
                </div>
              </div>

              <div class="alb-story-card__footer">
                <router-link to="/become-partner" class="alb-story-btn alb-story-btn--owner">
                  <span>Đăng Ký Tham Gia Đối Tác</span>
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- SECTION 3: 3-STEP WORKFLOW STORYTELLING -->
      <section class="alb-workflow-section">
        <div class="sg-container">
          <header class="alb-section-header alb-section-header--center">
            <span class="alb-section-tag">QUY TRÌNH ĐẶT SÂN</span>
            <h2 class="alb-section-title">Đặt Sân Giữ Chỗ Chỉ Trong 3 Bước</h2>
            <p class="alb-section-desc">Trải nghiệm đặt lịch minh bạch, nhanh chóng và an tâm trước mỗi trận đấu</p>
          </header>

          <div class="alb-workflow-grid">
            <div class="alb-workflow-step">
              <div class="alb-workflow-media">
                <span class="alb-workflow-num">01</span>
                <img :src="'/images/home/step1_search_3d.png'" alt="Bước 1: Tìm sân & chọn giờ" class="alb-workflow-img" />
              </div>
              <div class="alb-workflow-body">
                <span class="alb-workflow-phase">BƯỚC 01 / TÌM KIẾM</span>
                <h3 class="alb-workflow-title">Tìm Sân & Chọn Khung Giờ</h3>
                <p class="alb-workflow-desc">Chọn địa điểm gần bạn, xem sơ đồ giờ trống và chọn chính xác thảm sân muốn chơi.</p>
              </div>
            </div>

            <div class="alb-workflow-connector">
              <div class="alb-workflow-line"></div>
            </div>

            <div class="alb-workflow-step">
              <div class="alb-workflow-media">
                <span class="alb-workflow-num">02</span>
                <img :src="'/images/home/step2_pay_3d.png'" alt="Bước 2: Xác nhận & thanh toán" class="alb-workflow-img" />
              </div>
              <div class="alb-workflow-body">
                <span class="alb-workflow-phase">BƯỚC 02 / THANH TOÁN</span>
                <h3 class="alb-workflow-title">Xác Nhận & Thanh Toán</h3>
                <p class="alb-workflow-desc">Quét mã QR thanh toán đặt cọc giữ chỗ an toàn và tiện lợi qua chuyển khoản ngân hàng.</p>
              </div>
            </div>

            <div class="alb-workflow-connector">
              <div class="alb-workflow-line"></div>
            </div>

            <div class="alb-workflow-step">
              <div class="alb-workflow-media">
                <span class="alb-workflow-num">03</span>
                <img :src="'/images/home/step3_ticket_3d.png'" alt="Bước 3: Nhận mã booking" class="alb-workflow-img" />
              </div>
              <div class="alb-workflow-body">
                <span class="alb-workflow-phase">BƯỚC 03 / VÀO SÂN</span>
                <h3 class="alb-workflow-title">Nhận Mã Booking & Vào Sân</h3>
                <p class="alb-workflow-desc">Nhận mã xác nhận tức thì qua SMS/Email và đến sân trải nghiệm trận đấu tuyệt vời.</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- SECTION 4: FEATURED VENUES SHOWCASE -->
      <section class="alb-section alb-section--alt">
        <div class="sg-container">
          <header class="alb-section-header">
            <span class="alb-section-tag">CỤM SÂN HOT</span>
            <h2 class="alb-section-title">Hệ Thống Cụm Sân Nổi Bật</h2>
            <p class="alb-section-desc">Các cụm sân được cộng đồng người chơi đánh giá cao về chất lượng thảm và dịch vụ</p>
          </header>

          <div v-if="loadingVenues" style="text-align: center; padding: 40px; color: #111827;">
            Đang tải dữ liệu cụm sân...
          </div>

          <div v-else class="alb-venue-grid">
            <article v-for="venue in topVenues" :key="venue.id" class="alb-venue-card">
              <div class="alb-venue-card__thumb">
                <img :src="venueImage(venue)" :alt="venue.name" loading="lazy" />
              </div>

              <div class="alb-venue-card__body">
                <h3 class="alb-venue-card__title">{{ venue.name }}</h3>
                <div class="alb-venue-card__address">
                  <span>{{ venue.address || venue.province || "Hà Nội / TP.HCM" }}</span>
                </div>

                <div class="alb-venue-card__footer">
                  <div class="alb-venue-card__price">
                    {{ venue.min_price ? formatCurrency(venue.min_price) : "Liên hệ" }}
                    <small style="font-weight: 400; font-size: 12px; color: #374151;">/giờ</small>
                  </div>
                  <router-link
                    :to="{ name: 'venue-detail', params: { id: venue.slug || venue.id } }"
                    class="alb-venue-card__btn"
                  >
                    <span>Đặt Lịch Ngay</span>
                  </router-link>
                </div>
              </div>
            </article>
          </div>
        </div>
      </section>

      <!-- SECTION 5: STATS BANNER -->
      <section class="alb-stats-banner">
        <div class="sg-container alb-stats-grid">
          <div>
            <div class="alb-stat-val">500+</div>
            <div class="alb-stat-label">Cụm Sân Đối Tác</div>
          </div>
          <div>
            <div class="alb-stat-val">100K+</div>
            <div class="alb-stat-label">Lượt Đặt Thành Công</div>
          </div>
          <div>
            <div class="alb-stat-val">99.8%</div>
            <div class="alb-stat-label">Khách Hàng Hài Lòng</div>
          </div>
          <div>
            <div class="alb-stat-val">24/7</div>
            <div class="alb-stat-label">Hỗ Trợ Kỹ Thuật</div>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script>
import PublicNavbar from "../components/PublicNavbar.vue";
import AppIcon from "../components/AppIcon.vue";
import { api } from "../services/api.js";
import { resolveSystemAsset, systemName, systemProfileState } from "../stores/systemProfile.js";

function localDateString(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

export default {
  name: "HomeView",
  components: {
    PublicNavbar,
    AppIcon,
  },
  data() {
    return {
      today: localDateString(),
      selectedSportTab: "all",
      sportsCategories: [
        { id: "all", name: "Tất cả bộ môn", desc: "Hơn 500+ sân thể thao" },
        { id: "pickleball", name: "Pickleball Pro", desc: "Sân tiêu chuẩn chống trượt", image: "/images/home/sportgo-home-hero-v2.webp" },
        { id: "badminton", name: "Cầu Lông BWF", desc: "Thảm bọt kín chống chói", image: "/images/home/badminton-cover.webp" },
        { id: "football", name: "Bóng Đá FIFA", desc: "Sân cỏ 3D 5, 7, 11 người", image: "/images/home/anhbia2.webp" },
        { id: "tennis", name: "Tennis ITF", desc: "Mặt sân cứng & đất nát", image: "/images/home/badminton-cover.webp" },
      ],
      search: {
        province_code: "",
        province_name: "",
        ward_code: "",
        ward_name: "",
        court_type_id: "",
        booking_date: localDateString(),
        start_time: "18:00:00",
      },
      provincesList: [],
      wardsList: [],
      timeOptions: ["06:00", "08:00", "10:00", "14:00", "16:00", "18:00", "20:00"],
      courtTypes: [],
      featuredVenues: [],
      loadingVenues: true,
    };
  },
  computed: {
    brandName() {
      return systemName() || "SportGo";
    },
    brandLogo() {
      return resolveSystemAsset(systemProfileState.profile.logo_url);
    },
    topVenues() {
      return this.featuredVenues.slice(0, 8);
    },
  },
  mounted() {
    this.loadProvinces();
    this.loadFeaturedVenues();
  },
  methods: {
    async loadProvinces() {
      try {
        const res = await api("/api/locations/provinces");
        this.provincesList = res.data || res || [];
      } catch (e) {
        this.provincesList = [];
      }
    },
    async onProvinceChange() {
      this.search.ward_code = "";
      this.search.ward_name = "";
      this.wardsList = [];
      if (!this.search.province_code) return;

      const selectedProv = this.provincesList.find((p) => String(p.code) === String(this.search.province_code));
      this.search.province_name = selectedProv ? selectedProv.name : "";

      try {
        const res = await api(`/api/locations/wards?province_code=${this.search.province_code}`);
        this.wardsList = res.data || res || [];
      } catch (e) {
        this.wardsList = [];
      }
    },
    onWardChange() {
      const selectedWard = this.wardsList.find((w) => String(w.code) === String(this.search.ward_code));
      this.search.ward_name = selectedWard ? selectedWard.name : "";
    },
    async loadFeaturedVenues() {
      this.loadingVenues = true;
      try {
        const response = await api("/api/venues?limit=8");
        this.featuredVenues = response.data || [];
      } catch (error) {
        this.featuredVenues = [];
      } finally {
        this.loadingVenues = false;
      }
    },
    selectSportTab(tabId) {
      this.selectedSportTab = tabId;
      if (tabId === "all") {
        this.search.court_type_id = "";
      } else {
        const found = this.courtTypes.find(
          (c) =>
            c.name.toLowerCase().includes(tabId.toLowerCase()) ||
            tabId.toLowerCase().includes(c.name.toLowerCase())
        );
        if (found) {
          this.search.court_type_id = found.id;
        }
      }
    },
    resetSearchForm() {
      this.selectedSportTab = "all";
      this.search.province_code = "";
      this.search.province_name = "";
      this.search.ward_code = "";
      this.search.ward_name = "";
      this.search.court_type_id = "";
      this.search.booking_date = localDateString();
      this.search.start_time = "18:00:00";
      this.wardsList = [];
    },
    submitSearch() {
      const areaParts = [this.search.ward_name, this.search.province_name].filter(Boolean);
      this.$router.push({
        name: "venues",
        query: {
          area: areaParts.join(", ") || undefined,
          province_code: this.search.province_code || undefined,
          ward_code: this.search.ward_code || undefined,
          court_type_id: this.search.court_type_id || undefined,
          booking_date: this.search.booking_date,
          start_time: this.search.start_time,
        },
      });
    },
    venueImage(venue) {
      const img = venue.image_path || venue.cover_image || venue.thumbnail;
      if (!img) return "/images/home/sportgo-home-hero-v2.webp";
      if (/^https?:\/\//.test(img)) return img;
      return img.startsWith("/") ? img : `/storage/${img}`;
    },
    ratingLabel(venue) {
      const rating = Number(venue.rating_avg || venue.average_rating || 4.9);
      return rating.toFixed(1);
    },
    formatCurrency(val) {
      return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND", maximumFractionDigits: 0 }).format(val);
    },
  },
};
</script>

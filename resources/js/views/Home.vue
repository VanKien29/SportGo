<template>
  <div class="home-page">
    <PublicNavbar />

    <main>
      <section class="hero">
        <div class="hero-inner">
          <div class="hero-copy">
            <h1>Đặt sân thể thao nhanh, rõ lịch, đúng giờ.</h1>
            <p>
              Tìm sân theo khu vực, loại sân và khung giờ. SportGo ưu tiên lịch trống thật, giá rõ và thao tác đặt sân ít bước nhất.
            </p>

            <div class="hero-actions">
              <router-link :to="{ name: 'venues' }" class="hero-primary">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35"/><circle cx="11" cy="11" r="7"/></svg>
                Tìm sân ngay
              </router-link>
              <a href="#sports" class="hero-secondary">
                Chọn loại sân
              </a>
            </div>

            <div class="hero-stats" aria-label="Tổng quan SportGo">
              <div v-for="stat in heroStats" :key="stat.label">
                <strong>{{ stat.value }}</strong>
                <span>{{ stat.label }}</span>
              </div>
            </div>
          </div>

        </div>
      </section>

      <form class="search-panel" @submit.prevent="submitSearch">
        <div class="search-grid">
          <label>
            <span>Khu vực</span>
            <div class="field-control">
              <svg class="field-leading" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20 10c0 5.2-8 11-8 11S4 15.2 4 10a8 8 0 1 1 16 0Z"/>
                <circle cx="12" cy="10" r="2.5"/>
              </svg>
              <input v-model.trim="search.area" type="text" placeholder="Chọn khu vực" />
            </div>
          </label>
          <label>
            <span>Thời gian chơi</span>
            <BookingDateTimePicker
              v-model:date="search.booking_date"
              v-model:time="search.start_time"
              :min-date="today"
              :time-options="timeOptions"
            />
          </label>
          <label>
            <span>Môn thể thao</span>
            <div class="field-control">
              <svg class="field-leading" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="4" y="4" width="6" height="6" rx="1.5"/>
                <rect x="14" y="4" width="6" height="6" rx="1.5"/>
                <rect x="4" y="14" width="6" height="6" rx="1.5"/>
                <rect x="14" y="14" width="6" height="6" rx="1.5"/>
              </svg>
              <select
                v-model="search.court_type_id"
                @focus="ensureCourtTypesLoaded"
                @pointerdown="ensureCourtTypesLoaded"
              >
                <option value="">Tất cả môn</option>
                <option v-for="type in courtTypes" :key="type.id" :value="type.id">
                  {{ type.name }}
                </option>
              </select>
              <svg class="field-action" viewBox="0 0 24 24" aria-hidden="true">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </div>
          </label>
        </div>

        <div class="pitch-types" aria-label="Loại sân">
          <button
            v-for="type in pitchTypes"
            :key="type"
            type="button"
            :class="{ active: selectedPitchType === type }"
            @click="selectedPitchType = type"
          >
            {{ type }}
          </button>
        </div>

        <button class="search-submit" type="submit">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35"/><circle cx="11" cy="11" r="7"/></svg>
          Tìm sân trống
        </button>
      </form>

      <section id="sports" class="filter-strip" aria-label="Bộ lọc nhanh">
        <button
          v-for="filter in quickFilters"
          :key="filter.label"
          type="button"
          :class="{ active: activeQuickFilter === filter.label }"
          @click="selectSportFilter(filter.label)"
        >
          <img
            class="sport-filter-icon"
            :src="filter.image"
            :alt="`${filter.label} icon`"
            width="64"
            height="64"
            decoding="async"
          />
          {{ filter.label }}
        </button>
      </section>

      <section class="section-block">
        <div class="section-heading">
          <div>
            <p>Sân thể thao nổi bật</p>
            <h2>Cơ sở được đặt nhiều</h2>
          </div>
          <router-link :to="{ name: 'venues' }">
            Xem tất cả
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </router-link>
        </div>

        <div v-if="loadingVenues" class="state">Đang tải cụm sân...</div>
        <div v-else-if="topVenues.length === 0" class="state">Chưa có cụm sân phù hợp.</div>
        <div v-else class="venue-grid">
          <article
            v-for="venue in topVenues"
            :key="venue.id"
            class="venue-card"
          >
            <button class="favorite-btn" type="button" aria-label="Lưu sân yêu thích">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 1 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>
            </button>
            <div class="venue-photo" @click="goVenue(venue)">
              <img :src="venueImage(venue)" :alt="venue.name" loading="lazy" decoding="async" />
              <span class="status-badge">Còn trống</span>
              <strong class="rating-badge">★ {{ ratingValue(venue) }}</strong>
            </div>
            <div class="venue-info">
              <div class="venue-title-row">
                <h3>{{ venue.name }}</h3>
                <span>{{ venue.court_count || 0 }} sân</span>
              </div>
              <p class="venue-address">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ venue.address || venue.province || "Đang cập nhật địa chỉ" }}
              </p>
              <div class="amenity-row">
                <span v-for="amenity in venueAmenities" :key="amenity">{{ amenity }}</span>
              </div>
              <div class="venue-bottom">
                <strong>{{ venue.min_price ? `Từ ${formatCurrency(venue.min_price)}/giờ` : "Liên hệ" }}</strong>
                <router-link :to="{ name: 'venue-detail', params: { id: venue.slug || venue.id } }">
                  Xem chi tiết
                </router-link>
              </div>
            </div>
          </article>
        </div>
      </section>

      <section class="section-block area-section">
        <div class="section-heading compact">
          <div>
            <p>Khám phá nhanh</p>
            <h2>Chọn sân theo khu vực</h2>
          </div>
        </div>
        <div class="area-grid">
          <button
            v-for="area in areaFilters"
            :key="area.name"
            type="button"
            @click="searchArea(area.name)"
          >
            <img :src="area.image" :alt="area.name" loading="lazy" decoding="async" />
            <span>
              <strong>{{ area.name }}</strong>
              <small>{{ area.count }}</small>
            </span>
          </button>
          <router-link :to="{ name: 'venues' }" class="all-area">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>
            <span>
              <strong>Xem tất cả</strong>
              <small>Tất cả khu vực</small>
            </span>
          </router-link>
        </div>
      </section>

      <section id="news" class="section-block news-section">
        <div class="section-heading">
          <div>
            <p>Cập nhật</p>
            <h2>Tin tức mới nhất</h2>
          </div>
          <router-link :to="{ name: 'venues' }">
            Xem tất cả
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </router-link>
        </div>

        <div v-if="!postsRequested || loadingPosts" class="state">Đang tải bài viết...</div>
        <div v-else-if="topPosts.length === 0" class="state">Chưa có bài viết mới.</div>
        <div v-else class="post-grid">
          <article v-for="(post, index) in topPosts" :key="post.id" class="post-card">
            <div class="post-image">
              <img :src="postImage(post, index)" :alt="post.title" loading="lazy" decoding="async" />
              <span>{{ postCategory(index) }}</span>
            </div>
            <div class="post-body">
              <h3>{{ post.title }}</h3>
              <p>{{ post.short_description || plainText(post.content).slice(0, 120) }}</p>
              <router-link
                :to="{ name: 'news-detail', params: { slug: post.slug || post.id } }"
              >
                Đọc bài viết
              </router-link>
            </div>
          </article>
        </div>
      </section>

      <section id="support" class="why-section">
        <div class="why-inner">
          <h2>Vì sao chọn SportGo?</h2>
          <div class="why-grid">
            <article v-for="item in benefits" :key="item.title">
              <span v-html="item.icon"></span>
              <div>
                <h3>{{ item.title }}</h3>
                <p>{{ item.text }}</p>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section id="offers" class="newsletter">
        <div>
          <h2>Không bỏ lỡ ưu đãi hấp dẫn!</h2>
          <p>Đăng ký nhận thông tin khuyến mãi và cập nhật mới nhất từ SportGo.</p>
        </div>
        <form @submit.prevent>
          <input type="email" placeholder="Nhập email của bạn" />
          <button type="submit">Đăng ký ngay</button>
        </form>
      </section>

      <footer class="site-footer">
        <div class="footer-brand">
          <div class="footer-logo">
            <span>Sport<span>Go</span></span>
          </div>
          <p>Nền tảng đặt sân thể thao trực tuyến hàng đầu tại Việt Nam.</p>
        </div>
        <div>
          <h3>Khám phá</h3>
          <router-link :to="{ name: 'venues' }">Cụm sân</router-link>
          <a href="#news">Tin tức</a>
          <a href="#offers">Ưu đãi</a>
        </div>
        <div>
          <h3>Hỗ trợ</h3>
          <a href="#support">Trung tâm trợ giúp</a>
          <a href="#support">Quy định sử dụng</a>
          <a href="#support">Chính sách bảo mật</a>
        </div>
        <div>
          <h3>Liên hệ</h3>
          <p>1900 6789</p>
          <p>info@sportgo.vn</p>
          <p>Hà Nội, Việt Nam</p>
        </div>
      </footer>
    </main>
  </div>
</template>

<script>
import BookingDateTimePicker from "../components/BookingDateTimePicker.vue";
import PublicNavbar from "../components/PublicNavbar.vue";
import { api } from "../services/api.js";

const heroImage = "/images/home/anhbia2.webp";
const sportIconBase = "/images/home/sports-icons";

function toQuery(params = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;
    query.append(key, value);
  });

  return query.toString();
}

function normalizeHomeMediaUrl(media) {
  const raw = [
    media?.url,
    media?.file_url,
    media?.full_url,
    media?.file_path,
    media?.path,
  ].find((value) => typeof value === "string" && value.trim() !== "");

  if (!raw) return "";

  const value = raw.trim().replace(/\\/g, "/");

  if (/^(https?:)?\/\//i.test(value) || value.startsWith("data:") || value.startsWith("blob:")) {
    return value;
  }

  if (value.startsWith("/storage/")) return value;
  if (value.startsWith("storage/")) return `/${value}`;
  if (value.startsWith("/")) return value;

  const publicPath = value.startsWith("public/") ? value.slice("public/".length) : value;
  return `/storage/${publicPath}`;
}

function localDateString(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
}

export default {
  name: "HomeView",
  components: { BookingDateTimePicker, PublicNavbar },
  data() {
    const today = localDateString();
    return {
      today,
      search: {
        q: "",
        area: "",
        court_type_id: "",
        booking_date: today,
        start_time: "18:00:00",
      },
      selectedPitchType: "Cầu lông",
      activeQuickFilter: "Cầu lông",
      timeOptions: [
        "05:00", "06:00", "07:00", "08:00", "09:00", "10:00",
        "11:00", "12:00", "13:00", "14:00", "15:00", "16:00",
        "17:00", "18:00", "19:00", "20:00", "21:00",
      ],
      pitchTypes: ["Cầu lông", "Pickleball", "Tennis"],
      quickFilters: [
        { label: "Cầu lông", image: `${sportIconBase}/badminton.webp` },
        { label: "Bóng đá", image: `${sportIconBase}/football.webp` },
        { label: "Pickleball", image: `${sportIconBase}/pickleball.webp` },
        { label: "Tennis", image: `${sportIconBase}/tennis.webp` },
        { label: "Bóng rổ", image: `${sportIconBase}/basketball.webp` },
        { label: "Bóng bàn", image: `${sportIconBase}/bongban.webp` },
        { label: "Tất cả", image: `${sportIconBase}/viewall.webp` },
      ],
      areaFilters: [
        { name: "Cầu Giấy", count: "12 cụm sân đa môn", image: heroImage },
        { name: "Mỹ Đình", count: "8 cụm sân cầu lông", image: heroImage },
        { name: "Hà Đông", count: "10 cụm sân thể thao", image: heroImage },
        { name: "Thanh Xuân", count: "6 cụm sân trong nhà", image: heroImage },
      ],
      benefits: [
        { title: "Đa dạng môn chơi", text: "Cầu lông, bóng đá, pickleball, tennis, bóng rổ và bóng bàn.", icon: "<svg viewBox='0 0 24 24'><path d='M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'/></svg>" },
        { title: "Đặt sân nhanh chóng", text: "Chỉ vài bước để đặt sân và xác nhận.", icon: "<svg viewBox='0 0 24 24'><path d='M12 6v6l4 2'/><circle cx='12' cy='12' r='9'/></svg>" },
        { title: "Thanh toán an toàn", text: "Nhiều phương thức thanh toán tiện lợi.", icon: "<svg viewBox='0 0 24 24'><rect x='3' y='8' width='18' height='12' rx='2'/><path d='M7 8V6a5 5 0 0 1 10 0v2'/></svg>" },
        { title: "Hỗ trợ 24/7", text: "Đội ngũ SportGo luôn sẵn sàng hỗ trợ bạn.", icon: "<svg viewBox='0 0 24 24'><path d='M4 14v-2a8 8 0 0 1 16 0v2'/><path d='M6 14h3v5H6zM15 14h3v5h-3z'/></svg>" },
      ],
      venueAmenities: ["Đèn", "Wifi", "Gửi xe", "Căng tin"],
      courtTypes: [],
      featuredVenues: [],
      latestPosts: [],
      postsRequested: false,
      loadingVenues: true,
      courtTypesRequested: false,
      loadingCourtTypes: false,
      loadingPosts: false,
      homeDataHandle: null,
      courtTypesHandle: null,
      newsObserver: null,
    };
  },
  computed: {
    heroStats() {
      const venueCount = this.featuredVenues.length;
      const courtCount = this.featuredVenues.reduce((total, venue) => total + this.courtCount(venue), 0);
      return [
        { value: this.loadingVenues ? "..." : venueCount || "0", label: "Cơ sở" },
        { value: this.loadingVenues ? "..." : courtCount || "0", label: "Sân thể thao" },
        { value: !this.courtTypesRequested || this.loadingCourtTypes ? "..." : this.courtTypes.length || "0", label: "Loại sân" },
      ];
    },
    topVenues() {
      return this.featuredVenues.slice(0, 3);
    },
    topPosts() {
      return this.latestPosts.slice(0, 4);
    },
  },
  mounted() {
    this.deferHomeDataLoad();
    this.deferCourtTypeLoad();
    this.observeNewsSection();
  },
  beforeUnmount() {
    if (this.newsObserver) {
      this.newsObserver.disconnect();
      this.newsObserver = null;
    }

    if (this.homeDataHandle) {
      window.clearTimeout(this.homeDataHandle);
      this.homeDataHandle = null;
    }

    if (this.courtTypesHandle) {
      window.clearTimeout(this.courtTypesHandle);
      this.courtTypesHandle = null;
    }
  },
  methods: {
    deferHomeDataLoad() {
      const load = () => {
        this.homeDataHandle = null;
        this.loadFeaturedVenues();
      };

      this.homeDataHandle = window.setTimeout(load, 1800);
    },
    deferCourtTypeLoad() {
      this.courtTypesHandle = window.setTimeout(() => {
        this.courtTypesHandle = null;
        this.ensureCourtTypesLoaded();
      }, 4200);
    },
    ensureCourtTypesLoaded() {
      if (this.courtTypes.length || this.loadingCourtTypes) return;

      if (this.courtTypesHandle) {
        window.clearTimeout(this.courtTypesHandle);
        this.courtTypesHandle = null;
      }

      this.loadCourtTypes();
    },
    observeNewsSection() {
      this.$nextTick(() => {
        const section = this.$el.querySelector("#news");
        if (!section) return;

        if (!("IntersectionObserver" in window)) {
          window.setTimeout(() => this.loadLatestPosts(), 1600);
          return;
        }

        this.newsObserver = new IntersectionObserver((entries) => {
          if (!entries.some((entry) => entry.isIntersecting)) return;
          this.newsObserver?.disconnect();
          this.newsObserver = null;
          this.loadLatestPosts();
        }, { rootMargin: "450px 0px" });

        this.newsObserver.observe(section);
      });
    },
    async loadCourtTypes() {
      this.courtTypesRequested = true;
      this.loadingCourtTypes = true;
      try {
        const response = await api("/api/court-types");
        this.courtTypes = (response.data || []).filter((type) => type.is_active !== false && !type.parent_id);
      } catch {
        this.courtTypes = [];
      } finally {
        this.loadingCourtTypes = false;
      }
    },
    async loadFeaturedVenues() {
      this.loadingVenues = true;
      try {
        const query = toQuery({ min_rating: 0, limit: 3 });
        const response = await api(`/api/venues${query ? `?${query}` : ""}`);
        this.featuredVenues = response.data || [];
      } catch {
        this.featuredVenues = [];
      } finally {
        this.loadingVenues = false;
      }
    },
    async loadLatestPosts() {
      if (this.postsRequested && (this.loadingPosts || this.latestPosts.length > 0)) return;
      this.postsRequested = true;
      this.loadingPosts = true;
      try {
        const response = await api("/api/venue-posts?per_page=4");
        this.latestPosts = response.data || [];
      } catch {
        this.latestPosts = [];
      } finally {
        this.loadingPosts = false;
      }
    },
    submitSearch() {
      const endTime = this.addOneHour(this.search.start_time);
      this.$router.push({
        name: "venues",
        query: {
          q: this.search.q || undefined,
          area: this.search.area || undefined,
          court_type_id: this.search.court_type_id || undefined,
          booking_date: this.search.booking_date,
          start_time: this.search.start_time,
          end_time: endTime,
        },
      });
    },
    searchArea(area) {
      this.search.area = area;
      this.$router.push({ name: "venues", query: { area } });
    },
    selectSportFilter(label) {
      this.activeQuickFilter = label;
      if (label === "Tất cả") {
        this.$router.push({ name: "venues" });
        return;
      }
      const type = this.courtTypes.find((item) => String(item.name || "").toLowerCase().includes(label.toLowerCase()));
      this.$router.push({
        name: "venues",
        query: type ? { court_type_id: type.id } : { q: label },
      });
    },
    courtCount(venue) {
      return Number(venue.court_count || venue.venue_courts_count || venue.venue_courts?.length || 0);
    },
    addOneHour(time) {
      const [hour, minute] = String(time || "18:00:00").slice(0, 5).split(":").map(Number);
      const nextHour = Math.min(hour + 1, 24);
      return `${String(nextHour).padStart(2, "0")}:${String(minute).padStart(2, "0")}:00`;
    },
    goVenue(venue) {
      this.$router.push({ name: "venue-detail", params: { id: venue.slug || venue.id } });
    },
    imageUrl(path) {
      if (!path) return "";
      if (/^https?:\/\//.test(path)) return path;
      if (path.startsWith("/")) return path;
      return `/storage/${path}`;
    },
    venueImage(venue) {
      return this.imageUrl(venue.image_path || venue.cover_image || venue.thumbnail) || heroImage;
    },
    postImage(post, index) {
      const media = Array.isArray(post.media) ? post.media.find((item) => item.collection === "thumbnail") || post.media[0] : null;
      return normalizeHomeMediaUrl(media) || this.imageUrl(post.thumbnail || post.image_path || post.cover_image) || (index % 2 === 0 ? heroImage : this.venueImage(post.venue_cluster || {}));
    },
    ratingValue(venue) {
      return Number(venue.average_rating || venue.rating || 4.8).toFixed(1);
    },
    postCategory(index) {
      return ["Kinh nghiệm", "Sự kiện", "Cụm sân mới", "Ưu đãi"][index % 4];
    },
    plainText(html) {
      const wrapper = document.createElement("div");
      wrapper.innerHTML = html || "";
      return wrapper.innerText || "";
    },
    formatCurrency(value) {
      return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
        maximumFractionDigits: 0,
      }).format(Number(value || 0));
    },
  },
};
</script>

<style scoped>
.home-page {
  min-height: 100vh;
  background: #f7faf8;
  color: #111827;
}

main {
  padding-top: 64px;
}

svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 2;
}

.hero {
  position: relative;
  min-height: 650px;
  overflow: hidden;
  background: #081812;
}

.hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: url("/images/home/anhbia2.webp");
  background-position: center;
  background-size: cover;
  background-repeat: no-repeat;
  filter: saturate(.78) brightness(1.08) contrast(1.02);
}

.hero::after {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, rgba(5, 24, 18, .5), rgba(5, 38, 28, .24) 48%, rgba(5, 38, 28, .04));
}

.hero-inner {
  position: relative;
  z-index: 2;
  display: block;
  max-width: 1320px;
  min-height: 650px;
  margin: 0 auto;
  padding: 110px 28px 150px;
}

.hero-copy {
  max-width: 660px;
}

.hero-copy h1 {
  max-width: 650px;
  margin: 0;
  color: #fff;
  font-size: 58px;
  font-weight: 950;
  line-height: 1.04;
  letter-spacing: 0;
  text-shadow: 0 4px 22px rgba(0, 0, 0, .42);
}

.hero-copy h1::after {
  content: "";
  display: block;
  width: 112px;
  height: 6px;
  margin-top: 20px;
  border-radius: 999px;
  background: linear-gradient(90deg, #0d8c51, #36d17f);
}

.hero-copy p {
  max-width: 560px;
  margin: 22px 0 0;
  color: rgba(255, 255, 255, .94);
  font-size: 18px;
  line-height: 1.75;
  text-shadow: 0 3px 16px rgba(0, 0, 0, .38);
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 30px;
}

.hero-primary,
.hero-secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-height: 46px;
  padding: 0 18px;
  border-radius: 10px;
  font-size: 15px;
  font-weight: 900;
  text-decoration: none;
}

.hero-primary {
  background: #fff;
  color: #05603a;
}

.hero-secondary {
  border: 1px solid rgba(255, 255, 255, .65);
  color: #fff;
}

.hero-stats {
  display: flex;
  gap: 30px;
  margin-top: 28px;
}

.hero-stats strong,
.hero-stats span {
  display: block;
}

.hero-stats strong {
  color: #fff;
  font-size: 28px;
  font-weight: 950;
  line-height: 1;
}

.hero-stats span {
  margin-top: 6px;
  color: rgba(255, 255, 255, .78);
  font-size: 13px;
  font-weight: 750;
}

.search-panel {
  position: sticky;
  top: 76px;
  z-index: 30;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 170px;
  gap: 16px;
  align-items: end;
  max-width: 1296px;
  margin: -64px auto 0;
  padding: 20px 28px;
  border: 1px solid #dbe8e1;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 18px 44px rgba(15, 23, 42, .12);
}

.search-grid {
  display: grid;
  grid-template-columns: 1.15fr 1.05fr 1fr;
  gap: 14px;
}

.search-panel label {
  display: grid;
  gap: 8px;
  color: #233226;
  font-size: 13px;
  font-weight: 850;
}

.search-panel input,
.search-panel select {
  width: 100%;
  height: 46px;
  border: 1px solid #d8e3dc;
  border-radius: 10px;
  padding: 0 42px;
  background: #fff;
  color: #111827;
  font-size: 14px;
  font-weight: 700;
  outline: none;
  appearance: none;
  -webkit-appearance: none;
  transition: border-color .18s ease, box-shadow .18s ease;
}

.field-control {
  position: relative;
  display: flex;
  align-items: center;
}

.field-leading,
.field-action {
  position: absolute;
  z-index: 2;
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  pointer-events: none;
}

.field-leading {
  left: 14px;
  color: #0b7a46;
}

.field-action {
  right: 14px;
  color: #5e6f64;
}

.search-panel input[type="date"]::-webkit-calendar-picker-indicator {
  position: absolute;
  right: 0;
  width: 46px;
  height: 46px;
  opacity: 0;
  cursor: pointer;
}

.search-panel input[type="search"]::-webkit-search-decoration,
.search-panel input[type="search"]::-webkit-search-cancel-button {
  display: none;
}

.search-panel input:focus,
.search-panel select:focus {
  border-color: #12864f;
  box-shadow: 0 0 0 4px rgba(18, 134, 79, .12);
}

.pitch-types {
  display: none;
  gap: 10px;
  margin-top: 18px;
}

.pitch-types button {
  height: 38px;
  padding: 0 18px;
  border: 1px solid #d8e3dc;
  border-radius: 10px;
  background: #fff;
  color: #314138;
  font-weight: 850;
}

.pitch-types button.active {
  border-color: #b7e8cf;
  background: #e7f8ef;
  color: #04733f;
}

.search-submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  height: 52px;
  margin-top: 0;
  border-radius: 10px;
  background: #0d8c51;
  color: #fff;
  font-size: 16px;
  font-weight: 950;
  box-shadow: none;
  transition: transform .18s ease, box-shadow .18s ease;
}

.search-submit:hover {
  transform: translateY(-1px);
  box-shadow: 0 22px 44px rgba(4, 115, 63, .3);
}

.filter-strip {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 16px;
  max-width: 1320px;
  margin: 40px auto 0;
  padding: 0 28px;
  position: relative;
  z-index: 2;
}

.filter-strip button {
  display: grid;
  place-items: center;
  gap: 8px;
  min-height: 96px;
  border: 1px solid #e1e8e4;
  border-radius: 16px;
  background: #fff;
  color: #26332b;
  font-size: 13px;
  font-weight: 900;
  box-shadow: 0 16px 40px rgba(15, 23, 42, .07);
  transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease, color .18s ease;
}

.sport-filter-icon {
  display: block;
  width: 64px;
  height: 64px;
  object-fit: contain;
  filter: drop-shadow(0 7px 12px rgba(15, 23, 42, .16));
  transition: transform .18s ease, filter .18s ease;
}

.filter-strip button.active,
.filter-strip button:hover {
  border-color: #9fe6c0;
  color: #04733f;
  transform: translateY(-2px);
  box-shadow: 0 20px 42px rgba(4, 115, 63, .14);
}

.filter-strip button.active .sport-filter-icon,
.filter-strip button:hover .sport-filter-icon {
  transform: scale(1.06);
}

.section-block {
  max-width: 1320px;
  margin: 0 auto;
  padding: 56px 28px 0;
}

.section-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 22px;
}

.section-heading p {
  margin: 0 0 6px;
  color: #04733f;
  font-size: 13px;
  font-weight: 950;
  letter-spacing: .03em;
}

.section-heading h2 {
  margin: 0;
  color: #111827;
  font-size: 28px;
  font-weight: 950;
}

.section-heading a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  min-height: 42px;
  padding: 0 8px 0 16px;
  border: 1px solid #bfe8d1;
  border-radius: 999px;
  background: #fff;
  color: #04733f;
  font-size: 14px;
  font-weight: 950;
  text-decoration: none;
  box-shadow: 0 12px 30px rgba(4, 115, 63, .08);
  transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease, background .18s ease, color .18s ease;
}

.section-heading a svg {
  display: grid;
  width: 28px;
  height: 28px;
  padding: 6px;
  border-radius: 999px;
  background: #04733f;
  color: #fff;
  box-sizing: border-box;
  transition: transform .18s ease, background .18s ease;
}

.section-heading a:hover {
  border-color: #04733f;
  background: #ecfbf2;
  color: #035f36;
  transform: translateY(-1px);
  box-shadow: 0 16px 34px rgba(4, 115, 63, .14);
}

.section-heading a:hover svg {
  background: #035f36;
  transform: translateX(2px);
}

.state {
  display: grid;
  min-height: 180px;
  place-items: center;
  border: 1px dashed #cbd5d0;
  border-radius: 16px;
  background: #fff;
  color: #66756d;
  font-weight: 750;
}

.venue-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 24px;
}

.venue-card,
.post-card {
  position: relative;
  overflow: hidden;
  border: 1px solid #e1e8e4;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 18px 48px rgba(15, 23, 42, .06);
  transition: transform .18s ease, box-shadow .18s ease;
}

.venue-card:hover,
.post-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 24px 56px rgba(15, 23, 42, .11);
}

.favorite-btn {
  position: absolute;
  z-index: 2;
  top: 14px;
  right: 14px;
  display: grid;
  width: 38px;
  height: 38px;
  place-items: center;
  border-radius: 999px;
  background: rgba(17, 24, 39, .45);
  color: #fff;
  backdrop-filter: blur(10px);
}

.favorite-btn svg {
  width: 19px;
}

.venue-photo {
  position: relative;
  height: 230px;
  cursor: pointer;
}

.venue-photo img,
.post-image img,
.area-grid img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.status-badge,
.rating-badge,
.post-image span {
  position: absolute;
  top: 14px;
  border-radius: 9px;
  color: #fff;
  font-size: 12px;
  font-weight: 950;
}

.status-badge {
  left: 14px;
  padding: 8px 12px;
  background: #079455;
}

.rating-badge {
  right: 60px;
  padding: 8px 10px;
  background: rgba(17, 24, 39, .76);
}

.venue-info {
  padding: 18px 20px 20px;
}

.venue-title-row,
.venue-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.venue-info h3,
.post-body h3 {
  margin: 0;
  color: #111827;
  font-size: 19px;
  font-weight: 950;
}

.venue-title-row span {
  color: #46564d;
  font-size: 13px;
  font-weight: 850;
  white-space: nowrap;
}

.venue-address {
  display: flex;
  align-items: center;
  gap: 7px;
  min-height: 24px;
  margin: 10px 0 14px;
  color: #5f6f66;
  font-size: 14px;
  line-height: 1.45;
}

.venue-address svg {
  width: 16px;
  min-width: 16px;
}

.amenity-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 18px;
}

.amenity-row span {
  display: inline-grid;
  min-height: 28px;
  place-items: center;
  padding: 0 10px;
  border-radius: 999px;
  background: #f1f5f3;
  color: #53645b;
  font-size: 12px;
  font-weight: 850;
}

.venue-bottom {
  padding-top: 16px;
  border-top: 1px solid #edf2ef;
}

.venue-bottom strong {
  color: #04733f;
  font-size: 16px;
  font-weight: 950;
}

.venue-bottom a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 36px;
  padding: 0 16px;
  border: 1px solid #0b8f50;
  border-radius: 10px;
  color: #04733f;
  font-size: 13px;
  font-weight: 950;
  text-decoration: none;
}

.area-section {
  padding-top: 42px;
}

.section-heading.compact {
  margin-bottom: 16px;
}

.area-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px;
}

.area-grid button,
.all-area {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 82px;
  padding: 12px;
  border: 1px solid #e1e8e4;
  border-radius: 15px;
  background: #fff;
  color: #111827;
  text-align: left;
  text-decoration: none;
  box-shadow: 0 14px 36px rgba(15, 23, 42, .04);
}

.area-grid img {
  width: 66px;
  min-width: 66px;
  height: 56px;
  border-radius: 10px;
}

.area-grid strong {
  display: block;
  font-size: 15px;
  font-weight: 950;
}

.area-grid small {
  display: block;
  margin-top: 4px;
  color: #66756d;
  font-size: 13px;
  font-weight: 700;
}

.all-area {
  border-color: #04733f;
  background: #04733f;
  color: #fff;
  box-shadow: 0 18px 38px rgba(4, 115, 63, .18);
  transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
}

.all-area svg {
  width: 46px;
  height: 46px;
  padding: 10px;
  border-radius: 14px;
  background: rgba(255, 255, 255, .14);
  color: #fff;
  box-sizing: border-box;
}

.all-area small {
  color: rgba(255, 255, 255, .76);
}

.all-area:hover {
  background: #035f36;
  transform: translateY(-2px);
  box-shadow: 0 22px 44px rgba(4, 115, 63, .24);
}

.news-section {
  padding-bottom: 50px;
}

.post-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 22px;
}

.post-image {
  position: relative;
  height: 150px;
}

.post-image span {
  left: 14px;
  padding: 7px 11px;
  background: #e95791;
}

.post-body {
  padding: 18px;
}

.post-body h3 {
  font-size: 17px;
  line-height: 1.35;
}

.post-body p {
  min-height: 54px;
  margin: 10px 0 16px;
  color: #66756d;
  font-size: 14px;
  line-height: 1.55;
}

.post-body a {
  color: #04733f;
  font-size: 13px;
  font-weight: 950;
  text-decoration: none;
}

.why-section {
  background: #fff;
  border-top: 1px solid #edf2ef;
  border-bottom: 1px solid #edf2ef;
}

.why-inner {
  max-width: 1320px;
  margin: 0 auto;
  padding: 44px 28px;
}

.why-inner h2 {
  margin: 0 0 22px;
  font-size: 28px;
  font-weight: 950;
}

.why-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 18px;
}

.why-grid article {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 18px;
  border: 1px solid #e1e8e4;
  border-radius: 15px;
  background: #fbfdfc;
}

.why-grid span {
  display: grid;
  width: 44px;
  min-width: 44px;
  height: 44px;
  place-items: center;
  border-radius: 50%;
  background: #e7f8ef;
  color: #04733f;
}

.why-grid :deep(svg) {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 2;
}

.why-grid h3 {
  margin: 0 0 5px;
  font-size: 15px;
  font-weight: 950;
}

.why-grid p {
  margin: 0;
  color: #66756d;
  font-size: 13px;
  line-height: 1.5;
}

.newsletter {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 28px;
  max-width: 1320px;
  margin: 32px auto 58px;
  padding: 28px 34px;
  border-radius: 18px;
  background: linear-gradient(135deg, #0d7d48, #22c55e);
  color: #fff;
  box-shadow: 0 24px 50px rgba(4, 115, 63, .18);
}

.newsletter h2 {
  margin: 0;
  font-size: 27px;
  font-weight: 950;
}

.newsletter p {
  margin: 8px 0 0;
  color: rgba(255, 255, 255, .9);
}

.newsletter form {
  display: flex;
  gap: 10px;
  min-width: 430px;
}

.newsletter input {
  flex: 1;
  height: 48px;
  border: 0;
  border-radius: 10px;
  padding: 0 16px;
  background: #fff;
  color: #111827;
  font-weight: 750;
  outline: none;
}

.newsletter button {
  height: 48px;
  padding: 0 22px;
  border-radius: 10px;
  background: #43d56f;
  color: #fff;
  font-weight: 950;
}

.site-footer {
  display: grid;
  grid-template-columns: 1.6fr repeat(3, 1fr);
  gap: 42px;
  max-width: 1320px;
  margin: 0 auto;
  padding: 0 28px 42px;
}

.footer-logo span {
  color: #111827;
  font-size: 24px;
  font-weight: 950;
}

.footer-logo span span {
  color: #0d8c51;
}

.site-footer h3 {
  margin: 0 0 14px;
  color: #111827;
  font-size: 14px;
  font-weight: 950;
}

.site-footer p,
.site-footer a {
  display: block;
  margin: 0 0 10px;
  color: #66756d;
  font-size: 14px;
  font-weight: 700;
  line-height: 1.6;
  text-decoration: none;
}

.footer-brand p {
  max-width: 280px;
  margin-top: 12px;
}

@media (max-width: 1100px) {
  .hero-inner {
    min-height: auto;
    padding-bottom: 72px;
  }

  .search-panel {
    grid-template-columns: 1fr;
    max-width: 720px;
    margin-top: -36px;
  }

  .search-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .filter-strip {
    margin-top: 36px;
  }

  .filter-strip,
  .venue-grid,
  .post-grid,
  .why-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .area-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .newsletter,
  .newsletter form {
    flex-direction: column;
    align-items: stretch;
    min-width: 0;
  }

  .site-footer {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 700px) {
  main {
    padding-top: 58px;
  }

  .hero,
  .hero-inner {
    min-height: auto;
  }

  .hero-inner {
    padding: 56px 20px 72px;
  }

  .hero-copy h1 {
    font-size: 40px;
  }

  .hero-copy p {
    font-size: 16px;
  }

  .trust-row span {
    width: 100%;
  }

  .search-panel {
    position: relative;
    top: auto;
    padding: 18px;
  }

  .search-grid,
  .filter-strip,
  .venue-grid,
  .post-grid,
  .why-grid,
  .area-grid {
    grid-template-columns: 1fr;
  }

  .filter-strip,
  .section-block,
  .why-inner {
    padding-left: 20px;
    padding-right: 20px;
  }

  .filter-strip {
    margin-top: -28px;
  }

  .section-heading {
    align-items: flex-start;
    flex-direction: column;
  }

  .venue-photo {
    height: 210px;
  }

  .venue-bottom {
    align-items: flex-start;
    flex-direction: column;
  }

  .newsletter {
    margin: 28px 20px 42px;
    padding: 24px;
  }

  .site-footer {
    grid-template-columns: 1fr;
    padding: 0 20px 36px;
  }
}
</style>

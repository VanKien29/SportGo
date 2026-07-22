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
        <div v-else-if="venueError" class="state" role="alert">
          <span>{{ venueError }}</span>
          <button class="sg-client-button" type="button" @click="loadFeaturedVenues">Thử lại</button>
        </div>
        <div v-else-if="topVenues.length === 0" class="state">Chưa có cụm sân phù hợp.</div>
        <div v-else class="venue-grid">
          <article
            v-for="venue in topVenues"
            :key="venue.id"
            class="venue-card"
          >
            <div class="venue-photo" @click="goVenue(venue)">
              <img :src="venueImage(venue)" :alt="venue.name" loading="lazy" decoding="async" />
              <span class="status-badge">Đang hoạt động</span>
              <strong class="rating-badge">{{ ratingLabel(venue) }}</strong>
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
              <div v-if="venue.court_types?.length" class="amenity-row">
                <span v-for="type in venue.court_types.slice(0, 4)" :key="type.id">{{ type.name }}</span>
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

      <section id="community" class="section-block news-section">
        <div class="section-heading">
          <div>
            <p>Cộng đồng</p>
            <h2>Bài đăng mới nhất</h2>
          </div>
          <router-link :to="{ name: 'ClientCommunityList' }">
            Xem tất cả
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </router-link>
        </div>

        <div v-if="!postsRequested || loadingPosts" class="state">Đang tải bài viết...</div>
        <div v-else-if="postsError" class="state" role="alert">
          <span>{{ postsError }}</span>
          <button class="sg-client-button" type="button" @click="loadLatestPosts">Thử lại</button>
        </div>
        <div v-else-if="topPosts.length === 0" class="state">Chưa có bài viết mới.</div>
        <div v-else class="post-grid">
          <article v-for="(post, index) in topPosts" :key="post.id" class="post-card">
            <div class="post-image">
              <img :src="postImage(post, index)" :alt="post.title" loading="lazy" decoding="async" />
              <span>{{ postCategory(post) }}</span>
            </div>
            <div class="post-body">
              <h3>{{ post.title }}</h3>
              <p>{{ post.short_description || plainText(post.content).slice(0, 120) }}</p>
              <router-link
                :to="{ name: 'community-post-detail', params: { slug: post.slug || post.id } }"
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
            <img v-if="brandLogo" :src="brandLogo" :alt="brandName" />
            <span v-else>{{ brandMain }}<span v-if="brandAccent">{{ brandAccent }}</span></span>
          </div>
          <p>Nền tảng đặt sân thể thao trực tuyến hàng đầu tại Việt Nam.</p>
        </div>
        <div>
          <h3>Khám phá</h3>
          <router-link :to="{ name: 'venues' }">Cụm sân</router-link>
          <router-link to="/news">Tin tức</router-link>
          <router-link to="/community">Cộng đồng</router-link>
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
import { resolveSystemAsset, systemName, systemProfileState } from "../stores/systemProfile.js";

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
      benefits: [
        { title: "Đa dạng môn chơi", text: "Cầu lông, bóng đá, pickleball, tennis, bóng rổ và bóng bàn.", icon: "<svg viewBox='0 0 24 24'><path d='M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'/></svg>" },
        { title: "Đặt sân nhanh chóng", text: "Chỉ vài bước để đặt sân và xác nhận.", icon: "<svg viewBox='0 0 24 24'><path d='M12 6v6l4 2'/><circle cx='12' cy='12' r='9'/></svg>" },
        { title: "Thanh toán an toàn", text: "Nhiều phương thức thanh toán tiện lợi.", icon: "<svg viewBox='0 0 24 24'><rect x='3' y='8' width='18' height='12' rx='2'/><path d='M7 8V6a5 5 0 0 1 10 0v2'/></svg>" },
        { title: "Hỗ trợ 24/7", text: "Đội ngũ SportGo luôn sẵn sàng hỗ trợ bạn.", icon: "<svg viewBox='0 0 24 24'><path d='M4 14v-2a8 8 0 0 1 16 0v2'/><path d='M6 14h3v5H6zM15 14h3v5h-3z'/></svg>" },
      ],
      courtTypes: [],
      featuredVenues: [],
      latestPosts: [],
      postsRequested: false,
      loadingVenues: true,
      loadingPosts: false,
      venueError: "",
      postsError: "",
      homeDataHandle: null,
      newsObserver: null,
    };
  },
  computed: {
    brandName() {
      return systemName() || "SportGo";
    },
    brandLogo() {
      return resolveSystemAsset(systemProfileState.profile.logo_url);
    },
    brandMain() {
      const name = this.brandName || "SportGo";
      const match = name.match(/^(.*?)(go)$/i);
      return match ? match[1] : name;
    },
    brandAccent() {
      const match = (this.brandName || "SportGo").match(/^(.*?)(go)$/i);
      return match ? match[2] : "";
    },
    heroStats() {
      const venueCount = this.featuredVenues.length;
      const courtCount = this.featuredVenues.reduce((total, venue) => total + this.courtCount(venue), 0);
      return [
        { value: this.loadingVenues ? "..." : venueCount || "0", label: "Cơ sở" },
        { value: this.loadingVenues ? "..." : courtCount || "0", label: "Sân thể thao" },
        { value: this.loadingVenues ? "..." : this.courtTypes.length || "0", label: "Loại sân" },
      ];
    },
    topVenues() {
      return this.featuredVenues.slice(0, 3);
    },
    topPosts() {
      return this.latestPosts.slice(0, 4);
    },
    areaFilters() {
      const grouped = new Map();
      this.featuredVenues.forEach((venue) => {
        const name = venue.ward || venue.province || this.addressArea(venue.address);
        if (!name) return;
        const current = grouped.get(name) || { name, count: 0, image: this.venueImage(venue) };
        current.count += 1;
        grouped.set(name, current);
      });
      return [...grouped.values()].slice(0, 4).map((area) => ({
        ...area,
        count: `${area.count} cụm sân`,
      }));
    },
  },
  mounted() {
    this.deferHomeDataLoad();
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

  },
  methods: {
    deferHomeDataLoad() {
      const load = () => {
        this.homeDataHandle = null;
        this.loadFeaturedVenues();
      };

      this.homeDataHandle = window.setTimeout(load, 1800);
    },
    observeNewsSection() {
      this.$nextTick(() => {
        const section = this.$el.querySelector("#community");
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
    async loadFeaturedVenues() {
      this.loadingVenues = true;
      this.venueError = "";
      try {
        const query = toQuery({ min_rating: 0, limit: 6 });
        const response = await api(`/api/venues${query ? `?${query}` : ""}`);
        this.featuredVenues = response.data || [];
        const types = new Map();
        this.featuredVenues.forEach((venue) => {
          (venue.court_types || []).forEach((type) => {
            // Venue payloads expose the bookable leaf type (for example
            // "Cầu lông - sân tiêu chuẩn"), not necessarily its parent.
            // Build filters from the values the venue API can actually match.
            if (type?.id) types.set(String(type.id), type);
          });
        });
        this.courtTypes = [...types.values()].sort((left, right) => String(left.name).localeCompare(String(right.name), "vi"));
      } catch (error) {
        this.featuredVenues = [];
        this.courtTypes = [];
        this.venueError = error?.message || "Không thể tải danh sách sân lúc này.";
      } finally {
        this.loadingVenues = false;
      }
    },
    async loadLatestPosts() {
      if (this.postsRequested && (this.loadingPosts || this.latestPosts.length > 0)) return;
      this.postsRequested = true;
      this.loadingPosts = true;
      this.postsError = "";
      try {
        const response = await api("/api/venue-posts?per_page=4&feed_type=community_post");
        this.latestPosts = response.data || [];
      } catch (error) {
        this.latestPosts = [];
        this.postsError = error?.message || "Không thể tải bài viết mới lúc này.";
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
    ratingLabel(venue) {
      const rating = Number(venue.rating_avg || venue.average_rating || venue.rating || 0);
      return rating > 0 ? `★ ${rating.toFixed(1)}` : "Mới";
    },
    postCategory(post) {
      const hashtag = Array.isArray(post.hashtags) ? post.hashtags[0]?.name : "";
      if (hashtag) return hashtag;
      return {
        news: "Bài chia sẻ",
        promotion: "Ưu đãi",
        tournament: "Sự kiện",
        notice: "Thông báo",
        recruitment: "Tìm người chơi",
      }[post.post_type] || "Cộng đồng";
    },
    addressArea(address) {
      const parts = String(address || "").split(",").map((part) => part.trim()).filter(Boolean);
      return parts.length > 1 ? parts.at(-2) : parts[0] || "";
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





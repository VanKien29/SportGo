<template>
  <div class="home-page">
    <PublicNavbar />

    <main>
      <section class="hero">
        <div class="hero-inner">
          <div class="hero-copy">
            <span class="hero-eyebrow">
              <Trophy :size="17" aria-hidden="true" />
              Không gian thể thao dành cho bạn
            </span>
            <h1>Tìm đúng sân.<br />Chơi đúng giờ.</h1>
            <p>
              Chọn môn, khu vực và thời gian. SportGo giúp bạn xem lịch trống thật,
              so sánh giá và hoàn tất đặt sân trong vài phút.
            </p>
            <div class="hero-actions">
              <router-link :to="{ name: 'venues' }" class="hero-primary">
                Khám phá sân gần bạn
                <ArrowRight :size="19" aria-hidden="true" />
              </router-link>
              <a href="#booking-steps" class="hero-secondary">Cách đặt sân</a>
            </div>
          </div>

          <div class="hero-stats" aria-label="Tổng quan SportGo">
            <div v-for="stat in heroStats" :key="stat.label">
              <strong>{{ stat.value }}</strong>
              <span>{{ stat.label }}</span>
            </div>
          </div>
        </div>
      </section>

      <form class="search-panel" aria-label="Tìm sân thể thao" @submit.prevent="submitSearch">
        <div class="search-grid">
          <label>
            <span>Khu vực</span>
            <div class="field-control">
              <MapPin class="field-leading" :size="19" aria-hidden="true" />
              <input v-model.trim="search.area" type="text" placeholder="Quận, huyện hoặc thành phố" />
            </div>
          </label>
          <label>
            <span>Ngày và giờ chơi</span>
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
              <LayoutGrid class="field-leading" :size="19" aria-hidden="true" />
              <select v-model="search.court_type_id">
                <option value="">Tất cả môn thể thao</option>
                <option v-for="type in courtTypes" :key="type.id" :value="type.id">
                  {{ type.name }}
                </option>
              </select>
              <ChevronDown class="field-action" :size="18" aria-hidden="true" />
            </div>
          </label>
        </div>

        <button class="search-submit" type="submit">
          <Search :size="20" aria-hidden="true" />
          Tìm sân trống
        </button>
      </form>

      <section class="trust-strip" aria-label="Lợi ích khi đặt sân">
        <article v-for="item in trustItems" :key="item.title">
          <span class="trust-icon" :class="item.tone">
            <component :is="item.icon" :size="24" aria-hidden="true" />
          </span>
          <div>
            <h2>{{ item.title }}</h2>
            <p>{{ item.text }}</p>
          </div>
        </article>
      </section>

      <section id="sports" class="section-block sport-section">
        <div class="section-title centered">
          <span>Chọn môn bạn yêu thích</span>
          <h2>Một điểm đến, nhiều cuộc chơi</h2>
          <p>Tìm nhanh sân phù hợp cho buổi tập, trận đấu hoặc cuộc hẹn cuối tuần.</p>
        </div>
        <div class="sport-list" aria-label="Bộ lọc môn thể thao">
          <button
            v-for="filter in quickFilters"
            :key="filter.label"
            type="button"
            :class="{ active: activeQuickFilter === filter.label }"
            @click="selectSportFilter(filter.label)"
          >
            <img
              :src="filter.image"
              :alt="filter.label"
              width="72"
              height="72"
              decoding="async"
            />
            <span>{{ filter.label }}</span>
            <ChevronRight :size="17" aria-hidden="true" />
          </button>
        </div>
      </section>

      <section id="booking-steps" class="booking-steps">
        <div class="section-title centered">
          <span>Đặt sân cùng SportGo</span>
          <h2>Ba bước để bắt đầu trận đấu</h2>
          <p>Không cần gọi hỏi từng sân. Lịch trống, giá và xác nhận nằm trong một luồng.</p>
        </div>
        <div class="step-list">
          <article v-for="(step, index) in bookingSteps" :key="step.title">
            <div class="step-visual">
              <span>{{ index + 1 }}</span>
              <component :is="step.icon" :size="34" aria-hidden="true" />
            </div>
            <h3>{{ step.title }}</h3>
            <p>{{ step.text }}</p>
          </article>
        </div>
      </section>

      <section class="section-block featured-section">
        <div class="section-heading">
          <div class="section-title">
            <span>Sân nổi bật</span>
            <h2>Cơ sở được người chơi quan tâm</h2>
            <p>Lựa chọn từ các cụm sân đang hoạt động và xem giá trước khi đặt.</p>
          </div>
          <router-link :to="{ name: 'venues' }" class="text-link">
            Xem tất cả
            <ArrowRight :size="18" aria-hidden="true" />
          </router-link>
        </div>

        <div v-if="loadingVenues" class="venue-grid" aria-label="Đang tải cụm sân">
          <article v-for="item in 3" :key="item" class="venue-card venue-skeleton" aria-hidden="true">
            <div class="skeleton-photo"></div>
            <div class="skeleton-lines">
              <i></i><i></i><i></i>
            </div>
          </article>
        </div>
        <div v-else-if="venueError" class="state" role="alert">
          <span>{{ venueError }}</span>
          <button class="sg-client-button" type="button" @click="loadFeaturedVenues">Thử lại</button>
        </div>
        <div v-else-if="topVenues.length === 0" class="state">Chưa có cụm sân phù hợp.</div>
        <div v-else class="venue-grid">
          <article v-for="venue in topVenues" :key="venue.id" class="venue-card">
            <button class="venue-photo" type="button" :aria-label="`Xem ${venue.name}`" @click="goVenue(venue)">
              <img :src="venueImage(venue)" :alt="venue.name" loading="lazy" decoding="async" />
              <span class="status-badge">Đang mở cửa</span>
              <strong class="rating-badge">
                <Star :size="14" aria-hidden="true" />
                {{ ratingLabel(venue) }}
              </strong>
            </button>
            <div class="venue-info">
              <div class="venue-title-row">
                <h3>{{ venue.name }}</h3>
                <span>{{ courtCount(venue) }} sân</span>
              </div>
              <p class="venue-address">
                <MapPin :size="17" aria-hidden="true" />
                {{ venue.address || venue.province || "Đang cập nhật địa chỉ" }}
              </p>
              <div v-if="venue.court_types?.length" class="amenity-row">
                <span v-for="type in venue.court_types.slice(0, 3)" :key="type.id">{{ type.name }}</span>
              </div>
              <div class="venue-bottom">
                <strong>{{ venue.min_price ? `Từ ${formatCurrency(venue.min_price)}/giờ` : "Liên hệ" }}</strong>
                <router-link :to="{ name: 'venue-detail', params: { id: venue.slug || venue.id } }">
                  Chọn lịch
                  <ArrowRight :size="16" aria-hidden="true" />
                </router-link>
              </div>
            </div>
          </article>
        </div>
      </section>

      <section class="about-section">
        <div class="about-image">
          <img :src="aboutImage" alt="Sân cầu lông trong nhà trên SportGo" loading="lazy" />
          <div class="about-image-note">
            <strong>Lịch trống cập nhật rõ ràng</strong>
            <span>Chọn giờ phù hợp trước khi đến sân</span>
          </div>
        </div>
        <div class="about-copy">
          <div class="section-title">
            <span>Hiểu hơn về SportGo</span>
            <h2>Ít thời gian tìm sân hơn, nhiều thời gian để chơi hơn</h2>
          </div>
          <p>
            SportGo kết nối người chơi với các cụm sân trong một trải nghiệm thống nhất:
            từ tìm kiếm, chọn khung giờ đến thanh toán và quản lý lịch đã đặt.
          </p>
          <ul>
            <li v-for="item in aboutPoints" :key="item">
              <CircleCheck :size="21" aria-hidden="true" />
              {{ item }}
            </li>
          </ul>
          <router-link :to="{ name: 'venues' }" class="solid-link">
            Tìm sân phù hợp
            <ArrowRight :size="18" aria-hidden="true" />
          </router-link>
        </div>
      </section>

      <section class="section-block area-section">
        <div class="section-heading">
          <div class="section-title">
            <span>Khám phá gần bạn</span>
            <h2>Chọn sân theo khu vực</h2>
          </div>
          <router-link :to="{ name: 'venues' }" class="text-link">
            Tất cả khu vực
            <ArrowRight :size="18" aria-hidden="true" />
          </router-link>
        </div>
        <div v-if="loadingVenues" class="area-grid area-grid-loading" aria-hidden="true">
          <div v-for="item in 4" :key="item"></div>
        </div>
        <div v-else-if="areaFilters.length" class="area-grid">
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
            <ArrowRight :size="19" aria-hidden="true" />
          </button>
        </div>
        <div v-else class="state compact-state">Khu vực sẽ hiển thị khi có cụm sân đang hoạt động.</div>
      </section>

      <section id="community" class="community-section">
        <div class="community-inner">
          <div class="section-heading">
            <div class="section-title">
              <span>Cộng đồng SportGo</span>
              <h2>Câu chuyện từ sân đấu</h2>
              <p>Kinh nghiệm chơi, lịch giao lưu và những cập nhật mới từ cộng đồng.</p>
            </div>
            <router-link :to="{ name: 'ClientCommunityList' }" class="text-link">
              Xem cộng đồng
              <ArrowRight :size="18" aria-hidden="true" />
            </router-link>
          </div>

          <div v-if="!postsRequested || loadingPosts" class="post-grid" aria-label="Đang tải bài viết">
            <article v-for="item in 3" :key="item" class="post-card post-skeleton" aria-hidden="true">
              <div></div><i></i><i></i>
            </article>
          </div>
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
                  <ArrowRight :size="16" aria-hidden="true" />
                </router-link>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section id="support" class="partner-cta">
        <div>
          <span>Dành cho chủ sân</span>
          <h2>Đưa sân của bạn đến gần người chơi hơn</h2>
          <p>Quản lý lịch, giá, thanh toán và khách hàng trên cùng một hệ thống.</p>
        </div>
        <router-link to="/become-partner">
          Trở thành đối tác
          <ArrowRight :size="19" aria-hidden="true" />
        </router-link>
      </section>

      <footer class="site-footer">
        <div class="footer-brand">
          <div class="footer-logo">
            <img v-if="brandLogo" :src="brandLogo" :alt="brandName" />
            <span v-else>{{ brandMain }}<span v-if="brandAccent">{{ brandAccent }}</span></span>
          </div>
          <p>Tìm sân, đặt lịch và bắt đầu cuộc chơi theo cách đơn giản hơn.</p>
        </div>
        <div>
          <h3>Khám phá</h3>
          <router-link :to="{ name: 'venues' }">Tìm sân</router-link>
          <a href="#sports">Môn thể thao</a>
          <router-link to="/community">Cộng đồng</router-link>
        </div>
        <div>
          <h3>Dành cho đối tác</h3>
          <router-link to="/become-partner">Đăng ký chủ sân</router-link>
          <router-link to="/login">Đăng nhập quản lý</router-link>
          <a href="#support">Giải pháp vận hành</a>
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
import {
  ArrowRight,
  BadgeDollarSign,
  CalendarCheck2,
  ChevronDown,
  ChevronRight,
  CircleCheck,
  Clock3,
  CreditCard,
  LayoutGrid,
  MapPin,
  MousePointerClick,
  Search,
  ShieldCheck,
  Star,
  Trophy,
} from "lucide-vue-next";
import { markRaw } from "vue";
import BookingDateTimePicker from "../components/BookingDateTimePicker.vue";
import PublicNavbar from "../components/PublicNavbar.vue";
import { api } from "../services/api.js";
import { resolveSystemAsset, systemName, systemProfileState } from "../stores/systemProfile.js";

const heroImage = "/images/home/anhbia2.webp";
const aboutImage = "/images/home/badminton-cover.webp";
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
  components: {
    ArrowRight,
    BookingDateTimePicker,
    ChevronDown,
    ChevronRight,
    CircleCheck,
    LayoutGrid,
    MapPin,
    PublicNavbar,
    Search,
    Star,
    Trophy,
  },
  data() {
    const today = localDateString();
    return {
      today,
      aboutImage,
      search: {
        q: "",
        area: "",
        court_type_id: "",
        booking_date: today,
        start_time: "18:00:00",
      },
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
      trustItems: [
        { title: "Giá rõ trước khi đặt", text: "So sánh và chọn mức giá phù hợp.", icon: markRaw(BadgeDollarSign), tone: "green" },
        { title: "Lịch trống cập nhật", text: "Chọn đúng khung giờ còn khả dụng.", icon: markRaw(Clock3), tone: "gold" },
        { title: "Đặt sân an tâm", text: "Thanh toán và lịch đặt được quản lý tập trung.", icon: markRaw(ShieldCheck), tone: "coral" },
      ],
      bookingSteps: [
        { title: "Tìm sân phù hợp", text: "Chọn khu vực, môn chơi và thời gian bạn muốn.", icon: markRaw(MousePointerClick) },
        { title: "Chọn lịch trống", text: "Xem sân, giá và khung giờ còn trống theo thời gian thực.", icon: markRaw(CalendarCheck2) },
        { title: "Xác nhận đặt sân", text: "Hoàn tất thanh toán và theo dõi lịch ngay trong tài khoản.", icon: markRaw(CreditCard) },
      ],
      aboutPoints: [
        "Lịch sân và mức giá được trình bày rõ trước khi xác nhận.",
        "Nhiều môn thể thao và cụm sân trong cùng một nơi.",
        "Có thể xem lại lịch đặt, thanh toán và thông báo từ chủ sân.",
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
      return this.latestPosts.slice(0, 3);
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

      this.homeDataHandle = window.setTimeout(load, 120);
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
        const query = toQuery({ min_rating: 0, limit: 8 });
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
      return rating > 0 ? rating.toFixed(1) : "Mới";
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

<style src="../../css/client-home-theme.css"></style>

<style scoped src="../../css/client-home.css"></style>

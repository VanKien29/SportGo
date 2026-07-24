<template>
  <div class="sgx-home home-page">
    <PublicNavbar />

    <main>
      <section class="sgx-hero" aria-labelledby="sgx-hero-title">
        <img
          class="sgx-hero__image"
          :src="heroImage"
          alt="Người chơi tại tổ hợp sân thể thao SportGo"
          width="1536"
          height="1024"
          fetchpriority="high"
        />
        <div class="sgx-hero__shade" aria-hidden="true"></div>
        <div class="sgx-shell sgx-hero__content">
          <p class="sgx-hero__eyebrow">
            <span></span>
            Nền tảng đặt sân thể thao
          </p>
          <h1 id="sgx-hero-title">
            Đặt sân thể thao
            <strong>dễ dàng cùng SportGo</strong>
          </h1>
          <p class="sgx-hero__lead">
            Tìm đúng sân, xem lịch trống và chọn giờ chơi phù hợp chỉ trong vài phút.
          </p>
          <div class="sgx-hero__actions">
            <router-link :to="{ name: 'venues' }" class="sgx-button sgx-button--primary">
              Khám phá sân gần bạn
              <ArrowRight :size="19" aria-hidden="true" />
            </router-link>
            <a href="#sgx-how" class="sgx-button sgx-button--outline">Cách đặt sân</a>
          </div>
        </div>
      </section>

      <section class="sgx-search-section" aria-label="Tìm sân thể thao">
        <form class="sgx-shell sgx-search" @submit.prevent="submitSearch">
          <label class="sgx-search__field">
            <span>Khu vực</span>
            <div class="sgx-search__control">
              <MapPin :size="19" aria-hidden="true" />
              <input
                v-model.trim="search.area"
                type="text"
                autocomplete="address-level2"
                placeholder="Quận, huyện hoặc thành phố"
              />
            </div>
          </label>

          <label class="sgx-search__field">
            <span>Ngày chơi</span>
            <div class="sgx-search__control">
              <CalendarDays :size="19" aria-hidden="true" />
              <input v-model="search.booking_date" type="date" :min="today" />
            </div>
          </label>

          <label class="sgx-search__field">
            <span>Giờ bắt đầu</span>
            <div class="sgx-search__control">
              <Clock3 :size="19" aria-hidden="true" />
              <select v-model="search.start_time">
                <option v-for="time in timeOptions" :key="time" :value="`${time}:00`">
                  {{ time }}
                </option>
              </select>
              <ChevronDown class="sgx-search__chevron" :size="17" aria-hidden="true" />
            </div>
          </label>

          <label class="sgx-search__field">
            <span>Môn thể thao</span>
            <div class="sgx-search__control">
              <Trophy :size="19" aria-hidden="true" />
              <select v-model="search.court_type_id">
                <option value="">Tất cả môn thể thao</option>
                <option v-for="type in courtTypes" :key="type.id" :value="type.id">
                  {{ type.name }}
                </option>
              </select>
              <ChevronDown class="sgx-search__chevron" :size="17" aria-hidden="true" />
            </div>
          </label>

          <button type="submit" class="sgx-search__submit">
            <Search :size="21" aria-hidden="true" />
            <span>Tìm sân</span>
          </button>
        </form>
      </section>

      <section class="sgx-why">
        <div class="sgx-shell sgx-why__grid">
          <article v-for="item in trustItems" :key="item.title" class="sgx-why__item">
            <span class="sgx-why__icon">
              <component :is="item.icon" :size="26" aria-hidden="true" />
            </span>
            <div>
              <h2>{{ item.title }}</h2>
              <p>{{ item.text }}</p>
            </div>
          </article>
        </div>
      </section>

      <section id="sgx-how" class="sgx-section sgx-how">
        <div class="sgx-shell">
          <header class="sgx-section-heading sgx-section-heading--center">
            <p>Đặt sân cùng SportGo</p>
            <h2>Ba bước để bắt đầu cuộc chơi</h2>
            <span>Không cần gọi hỏi từng nơi, mọi thông tin cần thiết đều nằm trong một hành trình.</span>
          </header>

          <div class="sgx-how__steps">
            <article v-for="(step, index) in bookingSteps" :key="step.title">
              <div class="sgx-how__visual">
                <span>0{{ index + 1 }}</span>
                <component :is="step.icon" :size="31" aria-hidden="true" />
              </div>
              <h3>{{ step.title }}</h3>
              <p>{{ step.text }}</p>
            </article>
          </div>
        </div>
      </section>

      <section class="sgx-section sgx-about">
        <div class="sgx-shell sgx-about__layout">
          <div class="sgx-about__media">
            <img
              :src="aboutImage"
              alt="Sân cầu lông trong nhà có thể đặt trên SportGo"
              width="1200"
              height="800"
              loading="lazy"
              decoding="async"
            />
            <div class="sgx-about__stamp">
              <CalendarCheck2 :size="25" aria-hidden="true" />
              <span>
                <strong>Lịch trống rõ ràng</strong>
                <small>Chọn giờ trước khi đến sân</small>
              </span>
            </div>
          </div>

          <div class="sgx-about__content">
            <header class="sgx-section-heading">
              <p>Về SportGo</p>
              <h2>Dành nhiều thời gian để chơi, ít thời gian để tìm sân</h2>
            </header>
            <p class="sgx-about__lead">
              SportGo kết nối người chơi với các cụm sân đang hoạt động, giúp việc tìm sân,
              xem giá, đặt lịch và theo dõi booking diễn ra trên cùng một nền tảng.
            </p>
            <ul>
              <li v-for="item in aboutPoints" :key="item">
                <CircleCheck :size="21" aria-hidden="true" />
                <span>{{ item }}</span>
              </li>
            </ul>
            <router-link :to="{ name: 'venues' }" class="sgx-text-action">
              Tìm sân phù hợp
              <ArrowRight :size="18" aria-hidden="true" />
            </router-link>
          </div>
        </div>
      </section>

      <section class="sgx-section sgx-venues">
        <div class="sgx-shell">
          <div class="sgx-section-heading-row">
            <header class="sgx-section-heading">
              <p>Sân được quan tâm</p>
              <h2>Địa điểm nổi bật dành cho bạn</h2>
              <span>Xem thông tin, giá tham khảo và chọn lịch từ các cụm sân đang hoạt động.</span>
            </header>
            <router-link :to="{ name: 'venues' }" class="sgx-view-all">
              Xem tất cả
              <ArrowRight :size="18" aria-hidden="true" />
            </router-link>
          </div>

          <div v-if="loadingVenues" class="sgx-venue-grid" aria-label="Đang tải danh sách sân">
            <article v-for="item in 4" :key="item" class="sgx-venue-card sgx-venue-card--loading" aria-hidden="true">
              <div class="sgx-skeleton sgx-skeleton--image"></div>
              <div class="sgx-venue-card__body">
                <i class="sgx-skeleton"></i>
                <i class="sgx-skeleton"></i>
                <i class="sgx-skeleton"></i>
              </div>
            </article>
          </div>

          <div v-else-if="venueError" class="sgx-state" role="alert">
            <span>{{ venueError }}</span>
            <button type="button" @click="loadFeaturedVenues">Tải lại</button>
          </div>

          <div v-else-if="topVenues.length === 0" class="sgx-state">
            Chưa có cụm sân phù hợp để hiển thị.
          </div>

          <div v-else class="sgx-venue-grid">
            <article v-for="venue in topVenues" :key="venue.id" class="sgx-venue-card">
              <button
                type="button"
                class="sgx-venue-card__media"
                :aria-label="`Xem ${venue.name}`"
                @click="goVenue(venue)"
              >
                <img
                  :src="venueImage(venue)"
                  :alt="venue.name"
                  width="640"
                  height="460"
                  loading="lazy"
                  decoding="async"
                />
                <span class="sgx-venue-card__status">Đang nhận lịch</span>
                <span class="sgx-venue-card__rating">
                  <Star :size="14" aria-hidden="true" />
                  {{ ratingLabel(venue) }}
                </span>
              </button>
              <div class="sgx-venue-card__body">
                <p class="sgx-venue-card__sport">
                  {{ venue.court_types?.[0]?.name || "Sân thể thao" }}
                </p>
                <h3>{{ venue.name }}</h3>
                <p class="sgx-venue-card__address">
                  <MapPin :size="16" aria-hidden="true" />
                  <span>{{ venue.address || venue.province || "Đang cập nhật địa chỉ" }}</span>
                </p>
                <div class="sgx-venue-card__meta">
                  <span>{{ courtCount(venue) }} sân</span>
                  <strong>
                    {{ venue.min_price ? `Từ ${formatCurrency(venue.min_price)}/giờ` : "Liên hệ" }}
                  </strong>
                </div>
                <router-link
                  :to="{ name: 'venue-detail', params: { id: venue.slug || venue.id } }"
                  class="sgx-venue-card__link"
                >
                  Xem sân và lịch trống
                  <ArrowRight :size="17" aria-hidden="true" />
                </router-link>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section id="sgx-sports" class="sgx-section sgx-sports">
        <div class="sgx-shell">
          <header class="sgx-section-heading sgx-section-heading--center sgx-section-heading--light">
            <p>Trải nghiệm thể thao</p>
            <h2>Chọn môn bạn muốn chơi hôm nay</h2>
            <span>Tìm nhanh địa điểm phù hợp theo từng môn thể thao.</span>
          </header>

          <div class="sgx-sports__grid">
            <button
              v-for="filter in quickFilters"
              :key="filter.label"
              type="button"
              :class="{ 'is-active': activeQuickFilter === filter.label }"
              @click="selectSportFilter(filter.label)"
            >
              <img
                :src="filter.image"
                :alt="filter.label"
                width="84"
                height="84"
                loading="lazy"
                decoding="async"
              />
              <span>{{ filter.label }}</span>
              <ChevronRight :size="18" aria-hidden="true" />
            </button>
          </div>
        </div>
      </section>

      <section class="sgx-section sgx-areas">
        <div class="sgx-shell">
          <div class="sgx-section-heading-row">
            <header class="sgx-section-heading">
              <p>Địa điểm yêu thích</p>
              <h2>Khám phá sân theo khu vực</h2>
              <span>Bắt đầu từ nơi gần bạn để rút ngắn thời gian di chuyển.</span>
            </header>
            <router-link :to="{ name: 'venues' }" class="sgx-view-all">
              Tất cả khu vực
              <ArrowRight :size="18" aria-hidden="true" />
            </router-link>
          </div>

          <div v-if="loadingVenues" class="sgx-area-grid" aria-hidden="true">
            <div v-for="item in 4" :key="item" class="sgx-skeleton sgx-area-skeleton"></div>
          </div>
          <div v-else-if="areaFilters.length" class="sgx-area-grid">
            <button
              v-for="area in areaFilters"
              :key="area.name"
              type="button"
              @click="searchArea(area.name)"
            >
              <img
                :src="area.image"
                :alt="area.name"
                width="720"
                height="520"
                loading="lazy"
                decoding="async"
              />
              <span class="sgx-area-grid__shade" aria-hidden="true"></span>
              <span class="sgx-area-grid__content">
                <small>{{ area.count }}</small>
                <strong>{{ area.name }}</strong>
                <em>
                  Khám phá
                  <ArrowRight :size="17" aria-hidden="true" />
                </em>
              </span>
            </button>
          </div>
          <div v-else class="sgx-state">Khu vực sẽ hiển thị khi có cụm sân đang hoạt động.</div>
        </div>
      </section>

      <section id="community" class="sgx-section sgx-community">
        <div class="sgx-shell">
          <div class="sgx-section-heading-row">
            <header class="sgx-section-heading">
              <p>Cộng đồng SportGo</p>
              <h2>Câu chuyện mới từ sân đấu</h2>
              <span>Kinh nghiệm chơi, lịch giao lưu và cập nhật từ cộng đồng thể thao.</span>
            </header>
            <router-link :to="{ name: 'ClientCommunityList' }" class="sgx-view-all">
              Xem cộng đồng
              <ArrowRight :size="18" aria-hidden="true" />
            </router-link>
          </div>

          <div v-if="!postsRequested || loadingPosts" class="sgx-post-grid" aria-label="Đang tải bài viết">
            <article v-for="item in 3" :key="item" class="sgx-post-card sgx-post-card--loading" aria-hidden="true">
              <div class="sgx-skeleton sgx-skeleton--post"></div>
              <i class="sgx-skeleton"></i>
              <i class="sgx-skeleton"></i>
            </article>
          </div>

          <div v-else-if="postsError" class="sgx-state" role="alert">
            <span>{{ postsError }}</span>
            <button type="button" @click="loadLatestPosts">Tải lại</button>
          </div>

          <div v-else-if="topPosts.length === 0" class="sgx-state">Chưa có bài viết mới.</div>

          <div v-else class="sgx-post-grid">
            <article v-for="(post, index) in topPosts" :key="post.id" class="sgx-post-card">
              <router-link
                :to="{ name: 'community-post-detail', params: { slug: post.slug || post.id } }"
                class="sgx-post-card__media"
              >
                <img
                  :src="postImage(post, index)"
                  :alt="post.title"
                  width="720"
                  height="450"
                  loading="lazy"
                  decoding="async"
                />
              </router-link>
              <div class="sgx-post-card__body">
                <span>{{ postCategory(post) }}</span>
                <h3>{{ post.title }}</h3>
                <p>{{ post.short_description || plainText(post.content).slice(0, 120) }}</p>
                <router-link
                  :to="{ name: 'community-post-detail', params: { slug: post.slug || post.id } }"
                >
                  Đọc bài viết
                  <ArrowRight :size="17" aria-hidden="true" />
                </router-link>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section class="sgx-partner" aria-labelledby="sgx-partner-title">
        <img
          :src="heroImage"
          alt=""
          width="1536"
          height="1024"
          loading="lazy"
          decoding="async"
          aria-hidden="true"
        />
        <span class="sgx-partner__shade" aria-hidden="true"></span>
        <div class="sgx-shell sgx-partner__content">
          <p>Dành cho chủ sân</p>
          <h2 id="sgx-partner-title">Đưa sân của bạn đến gần người chơi hơn</h2>
          <span>Quản lý lịch, giá, thanh toán và khách hàng trên cùng một hệ thống.</span>
          <router-link to="/become-partner" class="sgx-button sgx-button--primary">
            Trở thành đối tác
            <ArrowRight :size="19" aria-hidden="true" />
          </router-link>
        </div>
      </section>
    </main>

  </div>
</template>

<script>
import {
  ArrowRight,
  BadgeDollarSign,
  Building2,
  CalendarCheck2,
  CalendarDays,
  ChevronDown,
  ChevronRight,
  CircleCheck,
  Clock3,
  CreditCard,
  LayoutDashboard,
  LogOut,
  Mail,
  MapPin,
  MousePointerClick,
  Menu,
  PhoneCall,
  Search,
  ShieldCheck,
  Star,
  Trophy,
  UserRound,
  X,
} from "lucide-vue-next";
import { markRaw } from "vue";
import PublicNavbar from "../components/PublicNavbar.vue";
import { api } from "../services/api.js";
import { getAuth, logout } from "../stores/auth.js";
import { resolveSystemAsset, systemName, systemProfileState } from "../stores/systemProfile.js";

const heroImage = "/images/home/sportgo-home-hero-v2.webp";
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
    Building2,
    CalendarCheck2,
    CalendarDays,
    ChevronDown,
    ChevronRight,
    CircleCheck,
    Clock3,
    LayoutDashboard,
    LogOut,
    Mail,
    MapPin,
    Menu,
    PhoneCall,
    Search,
    Star,
    Trophy,
    UserRound,
    X,
    PublicNavbar,
  },
  data() {
    const today = localDateString();
    return {
      today,
      currentYear: new Date().getFullYear(),
      heroImage,
      aboutImage,
      user: getAuth(),
      mobileOpen: false,
      accountOpen: false,
      search: {
        q: "",
        area: "",
        court_type_id: "",
        booking_date: today,
        start_time: "18:00:00",
      },
      timeOptions: [
        "05:00",
        "06:00",
        "07:00",
        "08:00",
        "09:00",
        "10:00",
        "11:00",
        "12:00",
        "13:00",
        "14:00",
        "15:00",
        "16:00",
        "17:00",
        "18:00",
        "19:00",
        "20:00",
        "21:00",
      ],
      activeQuickFilter: "",
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
        {
          title: "Giá rõ trước khi đặt",
          text: "Xem mức giá phù hợp trước khi xác nhận.",
          icon: markRaw(BadgeDollarSign),
        },
        {
          title: "Lịch trống cập nhật",
          text: "Chọn đúng ngày và khung giờ còn khả dụng.",
          icon: markRaw(Clock3),
        },
        {
          title: "Đặt sân an tâm",
          text: "Booking và thanh toán được quản lý tập trung.",
          icon: markRaw(ShieldCheck),
        },
      ],
      bookingSteps: [
        {
          title: "Tìm sân phù hợp",
          text: "Chọn khu vực, môn chơi và thời gian bạn muốn.",
          icon: markRaw(MousePointerClick),
        },
        {
          title: "Chọn lịch trống",
          text: "Xem sân, giá và khung giờ còn trống trước khi đặt.",
          icon: markRaw(CalendarCheck2),
        },
        {
          title: "Xác nhận booking",
          text: "Hoàn tất thanh toán và theo dõi lịch trong tài khoản.",
          icon: markRaw(CreditCard),
        },
      ],
      aboutPoints: [
        "Lịch sân và giá được trình bày rõ trước khi xác nhận.",
        "Nhiều môn thể thao và cụm sân trong cùng một nơi.",
        "Theo dõi booking, thanh toán và thông báo ngay trên tài khoản.",
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
      const match = this.brandName.match(/^(.*?)(go)$/i);
      return match ? match[1] : this.brandName;
    },
    brandAccent() {
      const match = this.brandName.match(/^(.*?)(go)$/i);
      return match ? match[2] : "";
    },
    userInitial() {
      return this.user?.fullName?.trim()?.charAt(0)?.toUpperCase() || "?";
    },
    roleLabel() {
      return {
        admin: "Quản trị viên",
        owner: "Chủ sân",
        user: "Người chơi",
      }[this.user?.role] || "";
    },
    profileRoute() {
      return this.user?.role === "owner" ? "/owner/profile" : "/profile";
    },
    topVenues() {
      return this.featuredVenues.slice(0, 4);
    },
    topPosts() {
      return this.latestPosts.slice(0, 3);
    },
    areaFilters() {
      const grouped = new Map();

      this.featuredVenues.forEach((venue) => {
        const name = venue.ward || venue.province || this.addressArea(venue.address);
        if (!name) return;
        const current = grouped.get(name) || {
          name,
          count: 0,
          image: this.venueImage(venue),
        };
        current.count += 1;
        grouped.set(name, current);
      });

      return [...grouped.values()].slice(0, 4).map((area) => ({
        ...area,
        count: `${area.count} cụm sân`,
      }));
    },
  },
  watch: {
    "$route.fullPath"() {
      this.closeMenus();
    },
  },
  mounted() {
    document.addEventListener("pointerdown", this.handleOutside);
    document.addEventListener("keydown", this.handleKeydown);
    this.deferHomeDataLoad();
    this.observeNewsSection();
  },
  beforeUnmount() {
    document.removeEventListener("pointerdown", this.handleOutside);
    document.removeEventListener("keydown", this.handleKeydown);

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
    toggleAccount() {
      this.accountOpen = !this.accountOpen;
      this.mobileOpen = false;
    },
    toggleMobile() {
      this.mobileOpen = !this.mobileOpen;
      this.accountOpen = false;
    },
    closeMenus() {
      this.mobileOpen = false;
      this.accountOpen = false;
    },
    handleOutside(event) {
      if (!event.target.closest?.(".sgx-account")) this.accountOpen = false;
      if (!event.target.closest?.(".sgx-menu-button, .sgx-mobile-nav")) this.mobileOpen = false;
    },
    handleKeydown(event) {
      if (event.key === "Escape") this.closeMenus();
    },
    goToManage() {
      this.closeMenus();
      this.$router.push(this.user?.role === "admin" ? "/admin/dashboard" : "/owner/dashboard");
    },
    async handleLogout() {
      await logout();
      this.user = null;
      this.closeMenus();
      this.$router.push("/login");
    },
    deferHomeDataLoad() {
      this.homeDataHandle = window.setTimeout(() => {
        this.homeDataHandle = null;
        this.loadFeaturedVenues();
      }, 100);
    },
    observeNewsSection() {
      this.$nextTick(() => {
        const section = this.$el.querySelector("#community");
        if (!section) return;

        if (!("IntersectionObserver" in window)) {
          window.setTimeout(() => this.loadLatestPosts(), 1200);
          return;
        }

        this.newsObserver = new IntersectionObserver(
          (entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;
            this.newsObserver?.disconnect();
            this.newsObserver = null;
            this.loadLatestPosts();
          },
          { rootMargin: "420px 0px" },
        );

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
            if (type?.id) types.set(String(type.id), type);
          });
        });

        this.courtTypes = [...types.values()].sort((left, right) =>
          String(left.name).localeCompare(String(right.name), "vi"),
        );
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
      this.$router.push({
        name: "venues",
        query: {
          q: this.search.q || undefined,
          area: this.search.area || undefined,
          court_type_id: this.search.court_type_id || undefined,
          booking_date: this.search.booking_date,
          start_time: this.search.start_time,
          end_time: this.addOneHour(this.search.start_time),
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

      const type = this.courtTypes.find((item) =>
        String(item.name || "").toLowerCase().includes(label.toLowerCase()),
      );

      this.$router.push({
        name: "venues",
        query: type ? { court_type_id: type.id } : { q: label },
      });
    },
    courtCount(venue) {
      return Number(
        venue.court_count || venue.venue_courts_count || venue.venue_courts?.length || 0,
      );
    },
    addOneHour(time) {
      const [hour, minute] = String(time || "18:00:00").slice(0, 5).split(":").map(Number);
      const nextHour = Math.min(hour + 1, 24);
      return `${String(nextHour).padStart(2, "0")}:${String(minute).padStart(2, "0")}:00`;
    },
    goVenue(venue) {
      this.$router.push({
        name: "venue-detail",
        params: { id: venue.slug || venue.id },
      });
    },
    imageUrl(path) {
      if (!path) return "";
      if (/^https?:\/\//.test(path)) return path;
      if (path.startsWith("/")) return path;
      return `/storage/${path}`;
    },
    venueImage(venue) {
      return (
        this.imageUrl(venue.image_path || venue.cover_image || venue.thumbnail) ||
        "/images/home/anhbia2.webp"
      );
    },
    postImage(post, index) {
      const media = Array.isArray(post.media)
        ? post.media.find((item) => item.collection === "thumbnail") || post.media[0]
        : null;

      return (
        normalizeHomeMediaUrl(media) ||
        this.imageUrl(post.thumbnail || post.image_path || post.cover_image) ||
        (index % 2 === 0 ? this.heroImage : this.venueImage(post.venue_cluster || {}))
      );
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
      const parts = String(address || "")
        .split(",")
        .map((part) => part.trim())
        .filter(Boolean);
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

<style src="../../css/sportgo-home-v2.css"></style>

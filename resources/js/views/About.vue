<template>
  <div class="sga-app">
    <PublicNavbar />

    <!-- SECTION 1: PARALLAX HERO -->
    <section class="sga-hero" ref="heroSection">
      <div class="sga-hero__parallax-bg" ref="heroBg"></div>
      <div class="sga-hero__overlay"></div>
      <div class="sga-hero__content" ref="heroContent">
        <span class="sga-eyebrow">Về SportGo</span>
        <h1 class="sga-hero__headline">
          Nơi Thể Thao<br /><em>Kết Nối Con Người</em>
        </h1>
        <p class="sga-hero__sub">
          Chúng tôi xây dựng nền tảng công nghệ kết nối người yêu thể thao với những cụm sân chất lượng — nhanh, minh bạch và đáng tin cậy.
        </p>
        <div class="sga-hero__scroll-hint">
          <span>Cuộn xuống để khám phá</span>
          <div class="sga-scroll-arrow"><span></span><span></span><span></span></div>
        </div>
      </div>
      <div class="sga-hero__chips" ref="heroChips">
        <div class="sga-chip">
          <span class="sga-chip__val">500<sup>+</sup></span>
          <span class="sga-chip__lbl">Cụm sân đối tác</span>
        </div>
        <div class="sga-chip">
          <span class="sga-chip__val">100K<sup>+</sup></span>
          <span class="sga-chip__lbl">Lượt đặt thành công</span>
        </div>
        <div class="sga-chip">
          <span class="sga-chip__val">6</span>
          <span class="sga-chip__lbl">Bộ môn thể thao</span>
        </div>
      </div>
    </section>

    <!-- SECTION 2: MISSION -->
    <section class="sga-mission">
      <div class="sg-container sga-mission__inner">
        <div class="sga-mission__text">
          <span class="sga-eyebrow sga-eyebrow--dark">Sứ Mệnh</span>
          <h2 class="sga-heading">
            Xây dựng hệ sinh thái thể thao<br />thực sự dành cho mọi người
          </h2>
          <p class="sga-body">
            Trước khi có SportGo, việc tìm và đặt sân thể thao đầy trở ngại — gọi điện thủ công, thiếu thông tin giờ trống, thanh toán cồng kềnh. Chúng tôi thay đổi điều đó.
          </p>
          <p class="sga-body">
            SportGo tạo ra một cầu nối minh bạch, tức thì giữa người chơi và chủ sân — trao quyền cho cả hai phía để thể thao trở thành lối sống.
          </p>
          <div class="sga-mission__tags">
            <span class="sga-tag">Minh bạch</span>
            <span class="sga-tag">Tức thì</span>
            <span class="sga-tag">Đáng tin cậy</span>
            <span class="sga-tag">Kết nối</span>
          </div>
        </div>
        <div class="sga-mission__visual">
          <img :src="'/images/about_mission_3d.png'" alt="Sứ mệnh SportGo" class="sga-mission__img" />
          <div class="sga-float-badge sga-float-badge--1">
            <span class="sga-float-badge__num">99.8%</span>
            <span class="sga-float-badge__txt">Hài lòng</span>
          </div>
          <div class="sga-float-badge sga-float-badge--2">
            <span class="sga-float-badge__num">24/7</span>
            <span class="sga-float-badge__txt">Hỗ trợ</span>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 3: PARALLAX QUOTE BAND -->
    <section class="sga-parallax-band" ref="bandSection">
      <div class="sga-parallax-band__bg" ref="bandBg"></div>
      <div class="sga-parallax-band__overlay"></div>
      <blockquote class="sga-parallax-band__quote" ref="bandQuote">
        <p>"Mọi trận đấu đều bắt đầu từ một sân chơi tốt.<br />Chúng tôi ở đây để bạn luôn có điều đó."</p>
        <footer>— Đội ngũ SportGo</footer>
      </blockquote>
    </section>

    <!-- SECTION 4: VALUES -->
    <section class="sga-values">
      <div class="sg-container">
        <header class="sga-values__header">
          <span class="sga-eyebrow sga-eyebrow--dark">Giá Trị Cốt Lõi</span>
          <h2 class="sga-heading">Những điều chúng tôi<br />luôn giữ vững</h2>
        </header>
        <div class="sga-values__grid">
          <div
            v-for="(value, i) in coreValues"
            :key="value.title"
            class="sga-value-item"
            :style="{ '--item-index': i }"
          >
            <div class="sga-value-item__icon-wrap">
              <span class="sga-value-item__icon">{{ value.icon }}</span>
            </div>
            <h3 class="sga-value-item__title">{{ value.title }}</h3>
            <p class="sga-value-item__desc">{{ value.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 5: PARALLAX COURT STATS -->
    <section class="sga-parallax-court" ref="courtSection">
      <div class="sga-parallax-court__bg" ref="courtBg"></div>
      <div class="sga-parallax-court__overlay"></div>
      <div class="sga-parallax-court__content">
        <div class="sga-stat-row">
          <div class="sga-stat-box" v-for="stat in stats" :key="stat.val">
            <span class="sga-stat-box__val">{{ stat.val }}</span>
            <span class="sga-stat-box__lbl">{{ stat.lbl }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 6: TIMELINE -->
    <section class="sga-timeline">
      <div class="sg-container">
        <header class="sga-timeline__header">
          <span class="sga-eyebrow sga-eyebrow--dark">Hành Trình</span>
          <h2 class="sga-heading">Câu chuyện xây dựng<br />SportGo</h2>
        </header>
        <div class="sga-timeline__track">
          <div class="sga-timeline__line"></div>
          <div
            v-for="(event, i) in timeline"
            :key="event.year"
            class="sga-timeline__event"
            :class="i % 2 === 0 ? 'sga-timeline__event--left' : 'sga-timeline__event--right'"
          >
            <div class="sga-timeline__dot">
              <span class="sga-timeline__year">{{ event.year }}</span>
            </div>
            <div class="sga-timeline__card">
              <span class="sga-timeline__emoji">{{ event.icon }}</span>
              <h3 class="sga-timeline__card-title">{{ event.title }}</h3>
              <p class="sga-timeline__card-desc">{{ event.desc }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SECTION 7: CTA FINAL -->
    <section class="sga-cta">
      <div class="sga-cta__bg-shape sga-cta__bg-shape--1"></div>
      <div class="sga-cta__bg-shape sga-cta__bg-shape--2"></div>
      <div class="sg-container sga-cta__inner">
        <h2 class="sga-cta__headline">Sẵn sàng trải nghiệm<br />SportGo hôm nay?</h2>
        <p class="sga-cta__sub">Hàng trăm cụm sân chất lượng đang chờ bạn khám phá. Đặt lịch trong 60 giây.</p>
        <div class="sga-cta__actions">
          <router-link to="/venues" class="sga-cta-btn sga-cta-btn--primary">Tìm sân ngay</router-link>
          <router-link to="/become-partner" class="sga-cta-btn sga-cta-btn--outline">Đăng ký chủ sân</router-link>
        </div>
      </div>
    </section>

    <ClientFooter />
  </div>
</template>

<script>
import PublicNavbar from "../components/PublicNavbar.vue";
import ClientFooter from "../components/ClientFooter.vue";

export default {
  name: "AboutView",
  components: { PublicNavbar, ClientFooter },
  data() {
    return {
      ticking: false,
      scrollY: 0,
      coreValues: [
        { icon: "⚡", title: "Tốc độ", desc: "Đặt sân trong vài giây. Xác nhận tức thì. Không chờ đợi, không gọi điện." },
        { icon: "🔍", title: "Minh bạch", desc: "Giá hiển thị rõ ràng. Sơ đồ giờ trống theo thời gian thực. Không phát sinh ẩn." },
        { icon: "🤝", title: "Kết nối", desc: "Nối liền người chơi và chủ sân trong một hệ sinh thái dùng chung, hai chiều lợi ích." },
        { icon: "🛡️", title: "Tin cậy", desc: "Thanh toán bảo mật, lịch sử rõ ràng, hỗ trợ 24/7 khi cần — chúng tôi luôn ở đây." },
        { icon: "🌱", title: "Phát triển", desc: "Không ngừng lắng nghe cộng đồng để cải tiến nền tảng, thêm tính năng mới mỗi tháng." },
        { icon: "🏆", title: "Chất lượng", desc: "Mỗi cụm sân đối tác đều qua kiểm duyệt tiêu chuẩn chất lượng trước khi lên hệ thống." },
      ],
      stats: [
        { val: "500+", lbl: "Cụm sân đối tác" },
        { val: "100K+", lbl: "Lượt đặt thành công" },
        { val: "99.8%", lbl: "Khách hài lòng" },
        { val: "24/7", lbl: "Hỗ trợ kỹ thuật" },
      ],
      timeline: [
        { year: "2022", icon: "💡", title: "Ý tưởng hình thành", desc: "Nhóm sáng lập nhận ra người chơi thể thao luôn gặp khó khăn khi tìm và đặt sân — quy trình quá thủ công, thiếu minh bạch." },
        { year: "2023", icon: "🔨", title: "Xây dựng sản phẩm đầu tiên", desc: "Phiên bản MVP ra đời với tính năng xem giờ trống và đặt cọc qua QR. Thử nghiệm tại Hà Nội với 20 cụm sân đầu tiên." },
        { year: "2024", icon: "🚀", title: "Mở rộng toàn quốc", desc: "Chạm mốc 100 cụm sân đối tác, ra mắt ứng dụng di động và hệ thống quản lý doanh thu tích hợp cho chủ sân." },
        { year: "2025", icon: "🌐", title: "Hệ sinh thái thể thao", desc: "Tích hợp thêm ghép đội, cộng đồng thể thao và hệ thống cho thuê vợt — SportGo trở thành nền tảng toàn diện nhất Việt Nam." },
      ],
    };
  },
  mounted() {
    this.$nextTick(() => {
      this.initParallax();
      this.initScrollReveal();
      window.addEventListener("scroll", this.onScroll, { passive: true });
    });
  },
  beforeUnmount() {
    window.removeEventListener("scroll", this.onScroll);
  },
  methods: {
    onScroll() {
      this.scrollY = window.scrollY || 0;
      if (!this.ticking) {
        window.requestAnimationFrame(() => {
          this.updateParallax();
          this.ticking = false;
        });
        this.ticking = true;
      }
    },
    initParallax() {
      this.updateParallax();
    },
    updateParallax() {
      const sy = this.scrollY;
      if (this.$refs.heroBg) {
        this.$refs.heroBg.style.transform = `translateY(${sy * 0.45}px) scale(1.08)`;
      }
      if (this.$refs.heroContent) {
        this.$refs.heroContent.style.transform = `translateY(${sy * 0.18}px)`;
        this.$refs.heroContent.style.opacity = String(Math.max(0, 1 - sy / 600));
      }
      if (this.$refs.heroChips) {
        this.$refs.heroChips.style.transform = `translateY(${sy * 0.12}px)`;
        this.$refs.heroChips.style.opacity = String(Math.max(0, 1 - sy / 500));
      }
      if (this.$refs.bandSection && this.$refs.bandBg) {
        const br = this.$refs.bandSection.getBoundingClientRect();
        const off = sy + br.top + this.$refs.bandSection.offsetHeight / 2 - window.innerHeight / 2;
        this.$refs.bandBg.style.transform = `translateY(${off * 0.35}px) scale(1.1)`;
      }
      if (this.$refs.bandQuote && this.$refs.bandSection) {
        const br = this.$refs.bandSection.getBoundingClientRect();
        const progress = 1 - br.top / window.innerHeight;
        const cl = Math.min(Math.max(progress, 0), 1);
        this.$refs.bandQuote.style.transform = `translateY(${(0.5 - cl) * 40}px)`;
        this.$refs.bandQuote.style.opacity = String(Math.min(1, cl * 2));
      }
      if (this.$refs.courtSection && this.$refs.courtBg) {
        const cr = this.$refs.courtSection.getBoundingClientRect();
        const off2 = sy + cr.top + this.$refs.courtSection.offsetHeight / 2 - window.innerHeight / 2;
        this.$refs.courtBg.style.transform = `translateY(${off2 * 0.3}px) scale(1.1)`;
      }
    },
    initScrollReveal() {
      if (typeof IntersectionObserver === "undefined") return;
      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              entry.target.classList.add("sga-revealed");
              observer.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.12, rootMargin: "0px 0px -50px 0px" }
      );
      if (this.$el && this.$el.querySelectorAll) {
        const targets = this.$el.querySelectorAll(
          ".sga-mission__text, .sga-mission__visual, .sga-values__header, .sga-value-item, .sga-timeline__header, .sga-timeline__event, .sga-cta__inner"
        );
        targets.forEach((el) => observer.observe(el));
      }
    },
  },
};
</script>

<style scoped>
.sga-app {
  font-family: var(--sg-font-main, ui-sans-serif, system-ui, sans-serif);
  color: #0f172a;
  overflow-x: hidden;
}
.sga-eyebrow {
  display: inline-block;
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #4ade80;
  margin-bottom: 14px;
}
.sga-eyebrow--dark { color: #15803d; }
.sga-heading {
  font-size: clamp(28px, 4vw, 44px);
  font-weight: 400;
  line-height: 1.22;
  color: #0f172a;
  margin: 0 0 20px;
}
.sga-body {
  font-size: 16px;
  font-weight: 400;
  line-height: 1.7;
  color: #334155;
  margin-bottom: 16px;
}
.sga-tag {
  display: inline-block;
  font-size: 13px;
  font-weight: 400;
  color: #15803d;
  border: 1px solid #bbf7d0;
  background: #f0fdf4;
  border-radius: 100px;
  padding: 4px 14px;
  margin: 4px 4px 0 0;
}

/* HERO */
.sga-hero {
  position: relative;
  height: 100vh;
  min-height: 640px;
  max-height: 900px;
  overflow: hidden;
  display: flex;
  align-items: flex-end;
  padding-bottom: 80px;
}
.sga-hero__parallax-bg {
  position: absolute;
  inset: -10% 0;
  background-image: url('/images/about_hero.png');
  background-size: cover;
  background-position: center top;
  will-change: transform;
  transform-origin: center top;
}
.sga-hero__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, rgba(2,6,23,0.55) 0%, rgba(2,6,23,0.3) 40%, rgba(2,6,23,0.75) 100%);
}
.sga-hero__content {
  position: relative;
  z-index: 2;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 40px;
  width: 100%;
  will-change: transform, opacity;
}
.sga-hero__headline {
  font-size: clamp(36px, 6vw, 80px);
  font-weight: 300;
  line-height: 1.1;
  color: #ffffff;
  margin: 0 0 24px;
  letter-spacing: -0.02em;
}
.sga-hero__headline em { font-style: normal; color: #4ade80; }
.sga-hero__sub {
  font-size: 18px;
  font-weight: 400;
  color: rgba(255,255,255,0.82);
  max-width: 560px;
  line-height: 1.65;
  margin: 0 0 40px;
}
.sga-hero__scroll-hint {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  color: rgba(255,255,255,0.6);
  font-size: 13px;
}
.sga-scroll-arrow { display: flex; flex-direction: column; gap: 3px; }
.sga-scroll-arrow span {
  display: block;
  width: 18px;
  height: 2px;
  background: rgba(255,255,255,0.4);
  border-radius: 2px;
  animation: scrollPulse 1.8s ease-in-out infinite;
}
.sga-scroll-arrow span:nth-child(2) { animation-delay: 0.15s; width: 12px; }
.sga-scroll-arrow span:nth-child(3) { animation-delay: 0.3s; width: 7px; }
@keyframes scrollPulse { 0%, 100% { opacity: 0.3; } 50% { opacity: 1; } }
.sga-hero__chips {
  position: absolute;
  bottom: 80px;
  right: 60px;
  z-index: 3;
  display: flex;
  flex-direction: column;
  gap: 12px;
  will-change: transform, opacity;
}
.sga-chip {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.18);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 12px;
  padding: 12px 20px;
  text-align: right;
  min-width: 150px;
}
.sga-chip__val { display: block; font-size: 24px; font-weight: 300; color: #ffffff; line-height: 1; margin-bottom: 4px; }
.sga-chip__val sup { font-size: 14px; }
.sga-chip__lbl { font-size: 12px; color: rgba(255,255,255,0.65); }

/* MISSION */
.sga-mission { padding: 96px 0; background: #ffffff; }
.sga-mission__inner { display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: center; }
.sga-mission__text { opacity: 0; transform: translateX(-40px); transition: opacity 0.8s ease, transform 0.8s ease; }
.sga-mission__text.sga-revealed { opacity: 1; transform: translateX(0); }
.sga-mission__visual { position: relative; opacity: 0; transform: translateX(40px); transition: opacity 0.8s ease 0.2s, transform 0.8s ease 0.2s; }
.sga-mission__visual.sga-revealed { opacity: 1; transform: translateX(0); }
.sga-mission__img { width: 100%; max-width: 460px; object-fit: contain; filter: drop-shadow(0 20px 40px rgba(15,23,42,0.12)); display: block; margin: 0 auto; }
.sga-float-badge { position: absolute; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 16px; box-shadow: 0 8px 24px rgba(15,23,42,0.08); display: flex; flex-direction: column; gap: 2px; animation: floatBadge 3s ease-in-out infinite; }
.sga-float-badge--1 { top: 20px; right: -10px; }
.sga-float-badge--2 { bottom: 40px; left: -10px; animation-delay: 1.5s; }
@keyframes floatBadge { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
.sga-float-badge__num { font-size: 20px; font-weight: 400; color: #15803d; line-height: 1; }
.sga-float-badge__txt { font-size: 12px; color: #64748b; }

/* PARALLAX QUOTE BAND */
.sga-parallax-band { position: relative; height: 420px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.sga-parallax-band__bg { position: absolute; inset: -15% 0; background-image: url('/images/home/sportgo-home-hero-v2.webp'); background-size: cover; background-position: center; will-change: transform; }
.sga-parallax-band__overlay { position: absolute; inset: 0; background: rgba(5,46,22,0.82); }
.sga-parallax-band__quote { position: relative; z-index: 2; text-align: center; max-width: 700px; padding: 0 40px; margin: 0; opacity: 0; will-change: transform, opacity; }
.sga-parallax-band__quote p { font-size: clamp(20px, 3vw, 32px); font-weight: 300; color: #ffffff; line-height: 1.5; margin-bottom: 20px; font-style: italic; }
.sga-parallax-band__quote footer { font-size: 14px; color: #4ade80; font-weight: 500; }

/* VALUES */
.sga-values { padding: 96px 0; background: #f8fafc; }
.sga-values__header { text-align: center; margin-bottom: 56px; opacity: 0; transform: translateY(30px); transition: opacity 0.7s ease, transform 0.7s ease; }
.sga-values__header.sga-revealed { opacity: 1; transform: translateY(0); }
.sga-values__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; }
.sga-value-item { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 32px 28px; opacity: 0; transform: translateY(40px); transition: opacity 0.6s ease calc(var(--item-index, 0) * 0.1s), transform 0.6s ease calc(var(--item-index, 0) * 0.1s), box-shadow 0.25s ease, border-color 0.25s ease; }
.sga-value-item.sga-revealed { opacity: 1; transform: translateY(0); }
.sga-value-item:hover { border-color: #bbf7d0; box-shadow: 0 8px 30px rgba(21,128,61,0.06); }
.sga-value-item__icon-wrap { margin-bottom: 16px; }
.sga-value-item__icon { font-size: 32px; display: block; line-height: 1; }
.sga-value-item__title { font-size: 18px; font-weight: 500; color: #0f172a; margin: 0 0 10px; }
.sga-value-item__desc { font-size: 14px; color: #475569; line-height: 1.65; margin: 0; font-weight: 400; }

/* PARALLAX COURT STATS */
.sga-parallax-court { position: relative; height: 360px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.sga-parallax-court__bg { position: absolute; inset: -15% 0; background-image: url('/images/home/anhbia2.webp'); background-size: cover; background-position: center; will-change: transform; }
.sga-parallax-court__overlay { position: absolute; inset: 0; background: rgba(2,10,30,0.75); }
.sga-parallax-court__content { position: relative; z-index: 2; width: 100%; max-width: 1200px; padding: 0 40px; }
.sga-stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 32px; text-align: center; }
.sga-stat-box__val { display: block; font-size: clamp(32px, 5vw, 56px); font-weight: 300; color: #4ade80; line-height: 1; margin-bottom: 8px; }
.sga-stat-box__lbl { font-size: 14px; color: rgba(255,255,255,0.75); font-weight: 400; }

/* TIMELINE */
.sga-timeline { padding: 96px 0; background: #ffffff; }
.sga-timeline__header { text-align: center; margin-bottom: 64px; opacity: 0; transform: translateY(30px); transition: opacity 0.7s ease, transform 0.7s ease; }
.sga-timeline__header.sga-revealed { opacity: 1; transform: translateY(0); }
.sga-timeline__track { position: relative; max-width: 900px; margin: 0 auto; }
.sga-timeline__line { position: absolute; left: 50%; top: 0; bottom: 0; width: 1px; background: #e2e8f0; transform: translateX(-50%); }
.sga-timeline__event { display: flex; align-items: flex-start; gap: 40px; margin-bottom: 56px; opacity: 0; transition: opacity 0.7s ease, transform 0.7s ease; }
.sga-timeline__event--left { flex-direction: row; transform: translateX(-40px); }
.sga-timeline__event--right { flex-direction: row-reverse; transform: translateX(40px); }
.sga-timeline__event.sga-revealed { opacity: 1; transform: translateX(0); }
.sga-timeline__dot { position: relative; width: 60px; height: 60px; background: #f0fdf4; border: 2px solid #86efac; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; z-index: 2; margin-top: 6px; }
.sga-timeline__year { font-size: 13px; font-weight: 500; color: #15803d; }
.sga-timeline__card { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px 28px; max-width: 380px; transition: box-shadow 0.25s ease; }
.sga-timeline__card:hover { box-shadow: 0 8px 28px rgba(15,23,42,0.06); }
.sga-timeline__emoji { font-size: 28px; display: block; margin-bottom: 10px; }
.sga-timeline__card-title { font-size: 18px; font-weight: 500; color: #0f172a; margin: 0 0 10px; }
.sga-timeline__card-desc { font-size: 14px; color: #475569; line-height: 1.65; margin: 0; }

/* CTA */
.sga-cta { position: relative; padding: 96px 0; background: #0f172a; overflow: hidden; }
.sga-cta__bg-shape { position: absolute; border-radius: 50%; opacity: 0.06; }
.sga-cta__bg-shape--1 { width: 600px; height: 600px; background: #4ade80; top: -200px; right: -100px; }
.sga-cta__bg-shape--2 { width: 400px; height: 400px; background: #22d3ee; bottom: -150px; left: -80px; }
.sga-cta__inner { position: relative; z-index: 2; text-align: center; opacity: 0; transform: translateY(30px); transition: opacity 0.8s ease, transform 0.8s ease; }
.sga-cta__inner.sga-revealed { opacity: 1; transform: translateY(0); }
.sga-cta__headline { font-size: clamp(28px, 4vw, 48px); font-weight: 300; color: #ffffff; margin: 0 0 20px; line-height: 1.2; }
.sga-cta__sub { font-size: 17px; color: rgba(255,255,255,0.65); max-width: 500px; margin: 0 auto 40px; line-height: 1.65; }
.sga-cta__actions { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
.sga-cta-btn { display: inline-flex; align-items: center; gap: 8px; padding: 14px 32px; border-radius: 8px; font-size: 15px; font-weight: 500; text-decoration: none; transition: all 0.2s ease; }
.sga-cta-btn--primary { background: #15803d; color: #ffffff; }
.sga-cta-btn--primary:hover { background: #166534; transform: translateY(-2px); }
.sga-cta-btn--outline { background: transparent; color: #ffffff; border: 1px solid rgba(255,255,255,0.3); }
.sga-cta-btn--outline:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.6); }

/* RESPONSIVE */
@media (max-width: 1024px) {
  .sga-mission__inner { grid-template-columns: 1fr; gap: 48px; }
  .sga-values__grid { grid-template-columns: repeat(2, 1fr); }
  .sga-hero__chips { right: 24px; bottom: 120px; }
  .sga-timeline__event--left, .sga-timeline__event--right { flex-direction: column; align-items: center; text-align: center; }
  .sga-timeline__line { display: none; }
  .sga-timeline__card { max-width: 100%; }
}
@media (max-width: 640px) {
  .sga-hero__content { padding: 0 24px; }
  .sga-hero__chips { display: none; }
  .sga-values__grid { grid-template-columns: 1fr; }
  .sga-stat-row { grid-template-columns: repeat(2, 1fr); }
  .sga-cta__actions { flex-direction: column; align-items: center; }
}
</style>

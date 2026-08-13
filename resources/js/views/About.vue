<template>
  <div class="sg-visual-about">
    <PublicNavbar />

    <!-- ───── 1. PARALLAX HERO SECTION ───── -->
    <section class="sg-psec sg-psec--hero" ref="psecHero">
      <div
        class="sg-psec__bg"
        :style="{ transform: `translate3d(0, ${scrollY * 0.38}px, 0)` }"
      ></div>
      <div class="sg-psec__overlay"></div>

      <div class="sg-container sg-psec__hero-grid">
        <div class="sg-psec__hero-text">
          <h1 class="sg-psec__title-huge">
            Kỷ Nguyên<br />
            <span class="sg-emerald-glow">Thể Thao Số</span>
          </h1>
          <p class="sg-psec__lead-short">
            Kết nối hàng ngàn thảm đấu & đam mê vận động trên toàn Việt Nam.
          </p>
        </div>

        <div class="sg-psec__hero-media">
          <img
            :src="'/images/about_hero_3d.png'"
            alt="SportGo Isometric Stadium 3D"
            class="sg-psec__3d-float"
            :style="{ transform: `translate3d(0, ${scrollY * -0.12}px, 0)` }"
          />
        </div>
      </div>
    </section>

    <!-- ───── 2. PARALLAX STORY: NGƯỜI CHƠI (IMAGE-RICH 3 STEPS) ───── -->
    <section class="sg-psec sg-psec--light" ref="psecPlayer">
      <div class="sg-container">
        <div class="sg-psec__header text-center">
          <h2 class="sg-psec__title">Giữ Sân Tức Thời</h2>
          <p class="sg-psec__desc">
            Tìm thảm đấu ưng ý, chọn giờ trống thời gian thực và nhận mã QR check-in trong 60 giây.
          </p>
        </div>

        <!-- 3 Step Visual Collage with Parallax Offsets -->
        <div class="sg-step-collage">
          <div class="sg-step-card" :style="{ transform: `translate3d(0, ${(scrollY - playerOffset) * 0.06}px, 0)` }">
            <img :src="'/images/home/step1_search_3d.png'" alt="Tìm kiếm sân 3D" class="sg-step-img" />
            <h3 class="sg-step-title">01. Tìm Kiếm</h3>
            <p class="sg-step-text">Tra cứu cụm sân gần bạn theo thời gian thực.</p>
          </div>

          <div class="sg-step-card" :style="{ transform: `translate3d(0, ${(scrollY - playerOffset) * -0.08}px, 0)` }">
            <img :src="'/images/home/step2_pay_3d.png'" alt="Thanh toán QR 3D" class="sg-step-img" />
            <h3 class="sg-step-title">02. Giữ Chỗ</h3>
            <p class="sg-step-text">Xác nhận cọc tức thì qua mã QR tự động.</p>
          </div>

          <div class="sg-step-card" :style="{ transform: `translate3d(0, ${(scrollY - playerOffset) * 0.05}px, 0)` }">
            <img :src="'/images/home/step3_ticket_3d.png'" alt="Vé check-in 3D" class="sg-step-img" />
            <h3 class="sg-step-title">03. Vào Sân</h3>
            <p class="sg-step-text">Quét mã vé điện tử check-in trực tiếp tại thảm.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ───── 3. PARALLAX STORY: CHỦ SÂN ───── -->
    <section class="sg-psec sg-psec--mint" ref="psecOwner">
      <div class="sg-container sg-psec__owner-grid">
        <div class="sg-psec__owner-media">
          <img
            :src="'/images/home/owner_solution_3d.png'"
            alt="Quản lý chủ sân 3D"
            class="sg-psec__3d-float"
            :style="{ transform: `translate3d(0, ${(scrollY - ownerOffset) * -0.1}px, 0)` }"
          />
        </div>

        <div class="sg-psec__owner-text">
          <h2 class="sg-psec__title">Quản Trị Tự Động</h2>
          <p class="sg-psec__desc">
            Tự động hóa lịch đặt, kiểm soát tiền cọc QR và tối ưu 100% công suất khai thác cụm sân 24/7.
          </p>
        </div>
      </div>
    </section>

    <!-- ───── 4. QUOTE BANNER WITH FULL PARALLAX BACKDROP ───── -->
    <section class="sg-psec sg-psec--quote" ref="psecQuote">
      <div
        class="sg-quote-bg"
        :style="{ transform: `translate3d(0, ${(scrollY - quoteOffset) * 0.3}px, 0) scale(1.1)` }"
      ></div>
      <div class="sg-quote-overlay"></div>

      <div class="sg-container text-center sg-quote-inner">
        <blockquote class="sg-quote-text">
          "Tôn vinh từng khoảnh khắc thi đấu thăng hoa."
        </blockquote>
      </div>
    </section>

    <!-- ───── 5. BỐN GIÁ TRỊ CỐT LÕI (MINIMALIST TYPOGRAPHY) ───── -->
    <section class="sg-psec sg-psec--light">
      <div class="sg-container">
        <div class="sg-psec__header text-center">
          <h2 class="sg-psec__title">Bốn Giá Trị Cốt Lõi</h2>
        </div>

        <div class="sg-values-grid">
          <div class="sg-value-item">
            <span class="sg-value-num">01</span>
            <h3 class="sg-value-heading">Tốc Độ Tức Thời</h3>
            <p class="sg-value-body">Hoàn tất giữ chỗ & nhận vé trong chưa đầy 60 giây.</p>
          </div>

          <div class="sg-value-item">
            <span class="sg-value-num">02</span>
            <h3 class="sg-value-heading">Minh Bạch Tuyệt Đối</h3>
            <p class="sg-value-body">Niêm yết công khai bảng giá & sơ đồ giờ trống realtime.</p>
          </div>

          <div class="sg-value-item">
            <span class="sg-value-num">03</span>
            <h3 class="sg-value-heading">Kết Nối Đa Chiều</h3>
            <p class="sg-value-body">Gắn kết người chơi cá nhân, nhóm ghép đội & cụm sân.</p>
          </div>

          <div class="sg-value-item">
            <span class="sg-value-num">04</span>
            <h3 class="sg-value-heading">An Toàn Bảo Mật</h3>
            <p class="sg-value-body">Thanh toán ngân hàng bảo mật & hỗ trợ đồng hành 24/7.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ───── 6. FINALE CTA ───── -->
    <section class="sg-psec sg-psec--cta">
      <div class="sg-container text-center">
        <h2 class="sg-psec__title sg-psec__title--cta">Sẵn Sàng Trải Nghiệm?</h2>
        <div class="sg-cta-btns">
          <router-link to="/venues" class="sg-pbtn sg-pbtn--primary">
            Tìm Sân Đặt Ngay
          </router-link>
          <router-link to="/become-partner" class="sg-pbtn sg-pbtn--outline">
            Dành Cho Chủ Sân
          </router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<script>
import PublicNavbar from "../components/PublicNavbar.vue";

export default {
  name: "AboutView",
  components: { PublicNavbar },

  data() {
    return {
      ticking: false,
      scrollY: 0,
      playerOffset: 0,
      ownerOffset: 0,
      quoteOffset: 0,
    };
  },

  mounted() {
    this.$nextTick(() => {
      this.calculateOffsets();
      this.onScroll();
      window.addEventListener("scroll", this.onScroll, { passive: true });
      window.addEventListener("resize", this.calculateOffsets, { passive: true });
    });
  },

  beforeUnmount() {
    window.removeEventListener("scroll", this.onScroll);
    window.removeEventListener("resize", this.calculateOffsets);
  },

  methods: {
    calculateOffsets() {
      const sy = window.scrollY || 0;
      if (this.$refs.psecPlayer) {
        this.playerOffset = this.$refs.psecPlayer.getBoundingClientRect().top + sy;
      }
      if (this.$refs.psecOwner) {
        this.ownerOffset = this.$refs.psecOwner.getBoundingClientRect().top + sy;
      }
      if (this.$refs.psecQuote) {
        this.quoteOffset = this.$refs.psecQuote.getBoundingClientRect().top + sy;
      }
    },

    onScroll() {
      this.scrollY = window.scrollY || 0;
      if (!this.ticking) {
        window.requestAnimationFrame(() => {
          this.ticking = false;
        });
        this.ticking = true;
      }
    },
  },
};
</script>

<style scoped>
/* =========================================================================
   PURE PARALLAX VISUAL STORYTELLING SYSTEM
   - Ultra-low text density
   - ZERO Taglines, ZERO Divider Lines
   - ZERO Cards, ZERO Boxes, ZERO Borders
   - Image-Rich 3D Parallax Offsets
   - High Contrast Crisp Light Theme (#f8fafc, #f0fdf4)
   ========================================================================= */

.sg-visual-about {
  font-family: var(--sg-font-main, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
  background: #f8fafc;
  color: #0f172a;
  overflow-x: hidden;
}

/* ───── SECTION BASE ───── */
.sg-psec {
  padding: 100px 0;
  position: relative;
}

.sg-psec--light {
  background: #f8fafc;
}
.sg-psec--mint {
  background: #f0fdf4;
}
.sg-psec--cta {
  padding: 120px 0;
  background: #ecfdf5;
}

/* ───── 1. PARALLAX HERO ───── */
.sg-psec--hero {
  position: relative;
  min-height: 80vh;
  display: flex;
  align-items: center;
  overflow: hidden;
  padding: 120px 0 80px;
}

.sg-psec__bg {
  position: absolute;
  inset: -20% 0;
  background-image: url('/images/about_hero.png');
  background-size: cover;
  background-position: center;
  will-change: transform;
}

.sg-psec__overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to bottom,
    rgba(248, 250, 252, 0.75) 0%,
    rgba(248, 250, 252, 0.95) 100%
  );
}

.sg-psec__hero-grid {
  position: relative;
  z-index: 2;
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  gap: 60px;
  align-items: center;
}

.sg-psec__title-huge {
  font-size: clamp(44px, 6.5vw, 80px);
  font-weight: 400;
  line-height: 1.1;
  color: #0f172a;
  margin: 0 0 20px;
  letter-spacing: -0.02em;
}

.sg-emerald-glow {
  color: #15803d;
}

.sg-psec__lead-short {
  font-size: clamp(18px, 2.2vw, 22px);
  font-weight: 400;
  line-height: 1.6;
  color: #334155;
  margin: 0;
  max-width: 580px;
}

.sg-psec__hero-media {
  display: flex;
  justify-content: center;
  align-items: center;
}

.sg-psec__3d-float {
  width: 100%;
  max-width: 460px;
  object-fit: contain;
  filter: drop-shadow(0 20px 40px rgba(21, 128, 61, 0.15));
  will-change: transform;
}

/* ───── 2. STEP COLLAGE (IMAGE RICH) ───── */
.sg-psec__header {
  margin-bottom: 70px;
}

.sg-psec__title {
  font-size: clamp(32px, 4vw, 52px);
  font-weight: 400;
  line-height: 1.2;
  color: #0f172a;
  margin: 0 0 16px;
}
.sg-psec__title--cta {
  margin-bottom: 36px;
}

.sg-psec__desc {
  font-size: 18px;
  font-weight: 400;
  line-height: 1.65;
  color: #334155;
  max-width: 620px;
  margin: 0 auto;
}

.sg-step-collage {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 40px;
  align-items: flex-start;
  margin-top: 40px;
}

.sg-step-card {
  text-align: center;
  will-change: transform;
}

.sg-step-img {
  width: 100%;
  max-width: 260px;
  height: 220px;
  object-fit: contain;
  margin: 0 auto 20px;
  filter: drop-shadow(0 15px 30px rgba(15, 23, 42, 0.1));
}

.sg-step-title {
  font-size: 20px;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 8px;
}

.sg-step-text {
  font-size: 15px;
  color: #334155;
  margin: 0;
  line-height: 1.6;
}

/* ───── 3. OWNER SECTION ───── */
.sg-psec__owner-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 72px;
  align-items: center;
}

.sg-psec__owner-media {
  display: flex;
  justify-content: center;
  align-items: center;
}

.sg-psec__owner-text {
  max-width: 520px;
}
.sg-psec__owner-text .sg-psec__desc {
  margin: 0;
  text-align: left;
}

/* ───── 4. PARALLAX QUOTE BANNER ───── */
.sg-psec--quote {
  position: relative;
  height: 380px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.sg-quote-bg {
  position: absolute;
  inset: -20% 0;
  background-image: url('/images/home/sportgo-home-hero-v2.webp');
  background-size: cover;
  background-position: center;
  will-change: transform;
}

.sg-quote-overlay {
  position: absolute;
  inset: 0;
  background: rgba(220, 252, 231, 0.92);
}

.sg-quote-inner {
  position: relative;
  z-index: 2;
}

.sg-quote-text {
  font-size: clamp(24px, 3.8vw, 42px);
  font-weight: 300;
  font-style: italic;
  line-height: 1.4;
  color: #064e3b;
  margin: 0;
}

/* ───── 5. VALUES GRID (NO LINES, NO CARDS) ───── */
.sg-values-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 56px 80px;
  margin-top: 30px;
}

.sg-value-item {
  position: relative;
}

.sg-value-num {
  display: block;
  font-size: 26px;
  font-weight: 600;
  color: #15803d;
  margin-bottom: 8px;
  line-height: 1;
}

.sg-value-heading {
  font-size: 22px;
  font-weight: 600;
  color: #0f172a;
  margin: 0 0 8px;
}

.sg-value-body {
  font-size: 15.5px;
  line-height: 1.65;
  color: #334155;
  margin: 0;
}

/* ───── 6. CTA BUTTONS ───── */
.sg-cta-btns {
  display: flex;
  gap: 20px;
  justify-content: center;
  flex-wrap: wrap;
}

.sg-pbtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 16px 40px;
  border-radius: 6px;
  font-size: 15.5px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.25s ease;
}

.sg-pbtn--primary {
  background: #15803d;
  color: #ffffff;
}
.sg-pbtn--primary:hover {
  background: #166534;
  transform: translateY(-2px);
}

.sg-pbtn--outline {
  background: transparent;
  color: #15803d;
  border: 1.5px solid #15803d;
}
.sg-pbtn--outline:hover {
  background: rgba(21, 128, 61, 0.08);
  transform: translateY(-2px);
}

/* ───── RESPONSIVE BREAKPOINTS ───── */
@media (max-width: 1024px) {
  .sg-psec__hero-grid,
  .sg-psec__owner-grid {
    grid-template-columns: 1fr;
    gap: 48px;
  }
  .sg-step-collage {
    grid-template-columns: 1fr;
    gap: 56px;
  }
  .sg-values-grid {
    grid-template-columns: 1fr;
    gap: 40px;
  }
}

@media (max-width: 640px) {
  .sg-psec {
    padding: 70px 0;
  }
  .sg-cta-btns {
    flex-direction: column;
    align-items: stretch;
  }
  .sg-pbtn {
    width: 100%;
  }
}
</style>

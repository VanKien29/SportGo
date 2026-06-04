<template>
  <section v-if="banners.length" class="home-banners" :class="`home-banners--${variant}`">
    <div class="carousel" @mouseenter="pauseAutoPlay" @mouseleave="resumeAutoPlay">
      <div class="slides" :style="{ transform: `translateX(-${activeIndex * 100}%)` }">
        <component
          v-for="(banner, index) in banners"
          :key="banner.id"
          :is="banner.link_url ? 'a' : 'div'"
          :href="banner.link_url || undefined"
          :target="banner.link_url ? '_blank' : undefined"
          :rel="banner.link_url ? 'noopener noreferrer' : undefined"
          class="slide"
          :aria-label="banner.title"
        >
          <img
            :src="banner.image_url || ('/storage/' + banner.image_path)"
            :alt="banner.title"
            loading="lazy"
          />
        </component>
      </div>

      <button
        v-if="banners.length > 1"
        type="button"
        class="nav prev"
        aria-label="Banner trước"
        @click="prev"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
      </button>
      <button
        v-if="banners.length > 1"
        type="button"
        class="nav next"
        aria-label="Banner sau"
        @click="next"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </button>

      <div v-if="banners.length > 1" class="dots">
        <button
          v-for="(_, index) in banners"
          :key="index"
          type="button"
          :class="['dot', { active: index === activeIndex }]"
          :aria-label="`Banner ${index + 1}`"
          @click="activeIndex = index"
        />
      </div>
    </div>
  </section>
</template>

<script>
import { api } from '../services/api.js';

export default {
  name: 'HomeBannerCarousel',
  props: {
    position: {
      type: String,
      required: true,
    },
    variant: {
      type: String,
      default: 'featured',
      validator: (v) => ['featured', 'promo'].includes(v),
    },
  },
  data() {
    return {
      banners: [],
      activeIndex: 0,
      timer: null,
    };
  },
  mounted() {
    this.loadBanners();
  },
  beforeUnmount() {
    this.clearTimer();
  },
  methods: {
    async loadBanners() {
      try {
        const response = await api(`/api/banners/active/${this.position}`);
        this.banners = response.data ?? [];
        if (this.banners.length > 1) {
          this.startAutoPlay();
        }
      } catch {
        this.banners = [];
      }
    },
    startAutoPlay() {
      this.clearTimer();
      this.timer = setInterval(() => this.next(), 5000);
    },
    clearTimer() {
      if (this.timer) {
        clearInterval(this.timer);
        this.timer = null;
      }
    },
    pauseAutoPlay() {
      this.clearTimer();
    },
    resumeAutoPlay() {
      if (this.banners.length > 1) {
        this.startAutoPlay();
      }
    },
    next() {
      this.activeIndex = (this.activeIndex + 1) % this.banners.length;
    },
    prev() {
      this.activeIndex = (this.activeIndex - 1 + this.banners.length) % this.banners.length;
    },
  },
};
</script>

<style scoped>
.home-banners--featured {
  width: 100%;
}

.home-banners--featured .carousel {
  aspect-ratio: 21 / 8;
  min-height: 180px;
  max-height: 380px;
  border-radius: 16px;
  box-shadow: var(--sg-shadow-lg);
}

.home-banners:not(.home-banners--featured) .carousel {
  border-radius: 0;
  max-height: 320px;
  aspect-ratio: 3 / 1;
  box-shadow: none;
}

.carousel {
  position: relative;
  width: 100%;
  overflow: hidden;
  background: #e2e8f0;
}

.slides {
  display: flex;
  height: 100%;
  transition: transform 0.45s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide {
  flex: 0 0 100%;
  display: block;
  height: 100%;
  text-decoration: none;
}

.slide img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 44px;
  height: 44px;
  border: none;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.92);
  color: var(--sg-dark);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 2;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
  transition: var(--sg-transition);
}

.nav:hover {
  background: #fff;
  transform: translateY(-50%) scale(1.05);
}

.nav.prev {
  left: 16px;
}

.nav.next {
  right: 16px;
}

.dots {
  position: absolute;
  bottom: 14px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 6px;
  z-index: 2;
  padding: 6px 10px;
  background: rgba(0, 0, 0, 0.35);
  border-radius: 999px;
  backdrop-filter: blur(4px);
}

.dot {
  width: 8px;
  height: 8px;
  border: none;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.45);
  cursor: pointer;
  padding: 0;
  transition: var(--sg-transition);
}

.dot.active {
  background: #fff;
  width: 22px;
  border-radius: 4px;
}

@media (max-width: 768px) {
  .home-banners--featured .carousel {
    aspect-ratio: 16 / 9;
    min-height: 160px;
    border-radius: 12px;
  }

  .nav {
    width: 36px;
    height: 36px;
  }

  .nav.prev {
    left: 8px;
  }

  .nav.next {
    right: 8px;
  }
}
</style>

<template>
  <section v-if="banners.length" class="home-banners">
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
            :src="imageSrc(banner)"
            :alt="banner.title"
            loading="lazy"
            @error="removeBrokenBanner(index)"
          />
        </component>
      </div>

      <button
        v-if="banners.length > 1"
        class="nav prev"
        type="button"
        aria-label="Banner trước"
        @click="prev"
      >
        <AppIcon name="chevronUp" size="18" />
      </button>
      <button
        v-if="banners.length > 1"
        class="nav next"
        type="button"
        aria-label="Banner sau"
        @click="next"
      >
        <AppIcon name="chevronDown" size="18" />
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
import AppIcon from './AppIcon.vue';
import { api } from '../services/api.js';

export default {
  name: 'HomeBannerCarousel',
  components: { AppIcon },
  props: {
    position: {
      type: String,
      default: 'home',
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
        this.banners = response.data || [];
        if (this.banners.length > 1) {
          this.startAutoPlay();
        }
      } catch {
        this.banners = [];
      }
    },
    imageSrc(banner) {
      if (banner.image_path) return `/storage/${banner.image_path}`;
      if (banner.image_url) return banner.image_url;
      return '';
    },
    removeBrokenBanner(index) {
      this.banners.splice(index, 1);
      if (this.activeIndex >= this.banners.length) {
        this.activeIndex = 0;
      }
      if (this.banners.length <= 1) {
        this.clearTimer();
      }
    },
    startAutoPlay() {
      this.clearTimer();
      this.timer = window.setInterval(() => this.next(), 5000);
    },
    clearTimer() {
      if (this.timer) {
        window.clearInterval(this.timer);
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
.home-banners {
  width: min(1120px, calc(100% - 40px));
  margin: -32px auto 56px;
  position: relative;
  z-index: 2;
}

.carousel {
  position: relative;
  width: 100%;
  aspect-ratio: 21 / 8;
  min-height: 180px;
  max-height: 360px;
  overflow: hidden;
  border-radius: 8px;
  background: #e2e8f0;
  box-shadow: var(--sg-shadow-lg);
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
}

.slide img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}

.nav {
  position: absolute;
  top: 50%;
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  color: var(--sg-dark);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.18);
}

.nav.prev {
  left: 14px;
  transform: translateY(-50%) rotate(-90deg);
}

.nav.next {
  right: 14px;
  transform: translateY(-50%) rotate(-90deg);
}

.dots {
  position: absolute;
  left: 50%;
  bottom: 12px;
  transform: translateX(-50%);
  display: flex;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.45);
}

.dot {
  width: 8px;
  height: 8px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.5);
  cursor: pointer;
}

.dot.active {
  width: 22px;
  background: #fff;
}

@media (max-width: 768px) {
  .home-banners {
    width: calc(100% - 32px);
    margin: -20px auto 40px;
  }

  .carousel {
    aspect-ratio: 16 / 9;
    min-height: 150px;
  }

  .nav {
    width: 34px;
    height: 34px;
  }
}
</style>

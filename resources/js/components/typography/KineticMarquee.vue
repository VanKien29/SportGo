<template>
  <div class="sg-kinetic-wrapper" :class="[`is-${variant}`, { 'is-bordered': bordered }]">
    <div class="sg-kinetic-track" :style="trackStyle">
      <div v-for="iteration in 3" :key="iteration" class="sg-kinetic-group" aria-hidden="true">
        <span
          v-for="(item, index) in items"
          :key="`${iteration}-${index}`"
          class="sg-kinetic-item"
          :class="{
            'is-outlined': item.outlined,
            'is-accent': item.accent,
          }"
        >
          <span class="sg-kinetic-text">{{ item.text }}</span>
          <span class="sg-kinetic-dot">✦</span>
        </span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'KineticMarquee',
  props: {
    items: {
      type: Array,
      default: () => [
        { text: 'SPORTGO POS', outlined: false, accent: false },
        { text: 'REALTIME SLOTS', outlined: true, accent: false },
        { text: 'INSTANT WALK-IN', outlined: false, accent: true },
        { text: 'VIETQR SEPAY', outlined: true, accent: false },
        { text: 'ARENA OPERATIONS', outlined: false, accent: false },
      ],
    },
    duration: {
      type: Number,
      default: 25, // seconds for one loop
    },
    direction: {
      type: String,
      default: 'left', // 'left' | 'right'
    },
    variant: {
      type: String,
      default: 'light', // 'light' | 'dark' | 'sport'
    },
    bordered: {
      type: Boolean,
      default: false,
    },
  },
  computed: {
    trackStyle() {
      return {
        animationDuration: `${this.duration}s`,
        animationDirection: this.direction === 'right' ? 'reverse' : 'normal',
      };
    },
  },
};
</script>

<style scoped>
.sg-kinetic-wrapper {
  position: relative;
  width: 100%;
  overflow: hidden;
  user-select: none;
  padding: 10px 0;
  display: flex;
  align-items: center;
}

.sg-kinetic-wrapper.is-light {
  background: #ffffff;
  color: #0f172a;
}

.sg-kinetic-wrapper.is-dark {
  background: #0f172a;
  color: #f8fafc;
}

.sg-kinetic-wrapper.is-sport {
  background: #087642;
  color: #ffffff;
}

.sg-kinetic-wrapper.is-bordered {
  border-top: 1px solid rgba(15, 23, 42, 0.08);
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.sg-kinetic-track {
  display: flex;
  width: max-content;
  will-change: transform;
  animation: sg-marquee-scroll linear infinite;
}

.sg-kinetic-wrapper:hover .sg-kinetic-track {
  animation-play-state: paused;
}

.sg-kinetic-group {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.sg-kinetic-item {
  display: inline-flex;
  align-items: center;
  gap: 16px;
  padding: 0 18px;
  font-family: inherit;
  font-size: clamp(14px, 1.6vw, 18px);
  font-weight: 900;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  white-space: nowrap;
}

.sg-kinetic-item.is-outlined {
  color: transparent;
  -webkit-text-stroke: 1.2px currentColor;
}

.sg-kinetic-wrapper.is-light .sg-kinetic-item.is-outlined {
  -webkit-text-stroke-color: #64748b;
}

.sg-kinetic-wrapper.is-dark .sg-kinetic-item.is-outlined {
  -webkit-text-stroke-color: #475569;
}

.sg-kinetic-wrapper.is-sport .sg-kinetic-item.is-outlined {
  -webkit-text-stroke-color: rgba(255, 255, 255, 0.6);
}

.sg-kinetic-item.is-accent {
  color: #10b981;
}

.sg-kinetic-wrapper.is-sport .sg-kinetic-item.is-accent {
  color: #d9f99d;
}

.sg-kinetic-dot {
  font-size: 11px;
  opacity: 0.6;
  color: #10b981;
}

@keyframes sg-marquee-scroll {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(-33.33333%);
  }
}
</style>

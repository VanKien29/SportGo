<template>
  <Teleport to="body">
    <transition name="cpt-fade">
      <div v-if="active" class="cpt-root" aria-hidden="true">
        <!-- SVG court lines that draw themselves outward -->
        <svg class="cpt-svg" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
          <!-- Court outer boundary -->
          <rect class="cpt-line cpt-l0" x="180" y="100" width="1080" height="700" fill="none" stroke="var(--cpt-color)" stroke-width="2.5"/>
          <!-- Center line vertical -->
          <line class="cpt-line cpt-l1" x1="720" y1="100" x2="720" y2="800"/>
          <!-- Center line horizontal -->
          <line class="cpt-line cpt-l2" x1="180" y1="450" x2="1260" y2="450"/>
          <!-- Service boxes left -->
          <rect class="cpt-line cpt-l3" x="180" y="190" width="350" height="520" fill="none" stroke="var(--cpt-color)" stroke-width="2"/>
          <!-- Service boxes right -->
          <rect class="cpt-line cpt-l3" x="910" y="190" width="350" height="520" fill="none" stroke="var(--cpt-color)" stroke-width="2"/>
          <!-- Inner service T top left -->
          <line class="cpt-line cpt-l4" x1="355" y1="190" x2="355" y2="450"/>
          <!-- Inner service T top right -->
          <line class="cpt-line cpt-l4" x1="1085" y1="190" x2="1085" y2="450"/>
          <!-- Inner service T bot left -->
          <line class="cpt-line cpt-l4" x1="355" y1="450" x2="355" y2="710"/>
          <!-- Inner service T bot right -->
          <line class="cpt-line cpt-l4" x1="1085" y1="450" x2="1085" y2="710"/>

          <!-- Net line -->
          <line class="cpt-line cpt-l5" x1="180" y1="450" x2="1260" y2="450" stroke="white" stroke-width="4" stroke-dasharray="12 10" opacity="0.25"/>

          <!-- Glow dots at intersections -->
          <circle class="cpt-dot" cx="720" cy="450" r="7" fill="var(--cpt-color)" opacity="0.9"/>
          <circle class="cpt-dot" cx="355" cy="450" r="5" fill="var(--cpt-color)" opacity="0.7"/>
          <circle class="cpt-dot" cx="1085" cy="450" r="5" fill="var(--cpt-color)" opacity="0.7"/>
          <circle class="cpt-dot" cx="720" cy="100" r="5" fill="var(--cpt-color)" opacity="0.7"/>
          <circle class="cpt-dot" cx="720" cy="800" r="5" fill="var(--cpt-color)" opacity="0.7"/>
          <circle class="cpt-dot" cx="180" cy="450" r="5" fill="var(--cpt-color)" opacity="0.7"/>
          <circle class="cpt-dot" cx="1260" cy="450" r="5" fill="var(--cpt-color)" opacity="0.7"/>

          <!-- Radiating glow from center -->
          <circle class="cpt-ring cpt-ring1" cx="720" cy="450" r="60" fill="none" stroke="var(--cpt-color)" stroke-width="1.5" opacity="0.4"/>
          <circle class="cpt-ring cpt-ring2" cx="720" cy="450" r="120" fill="none" stroke="var(--cpt-color)" stroke-width="1" opacity="0.25"/>
          <circle class="cpt-ring cpt-ring3" cx="720" cy="450" r="200" fill="none" stroke="var(--cpt-color)" stroke-width="0.5" opacity="0.15"/>
        </svg>

        <!-- Dark overlay base -->
        <div class="cpt-bg"></div>

        <!-- Shatter grid: 5×4 panels that fly apart -->
        <div v-if="phase === 'shatter'" class="cpt-shatter-grid">
          <div v-for="n in 20" :key="n" class="cpt-panel" :style="panelStyle(n)"></div>
        </div>

        <!-- Center label -->
        <div class="cpt-label">
          <span class="cpt-label-line cpt-ll1">SPORTGO</span>
          <span class="cpt-label-line cpt-ll2">— Đang mở cổng sân —</span>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script>
export default {
  name: "CourtPortalTransition",

  props: {
    to: {
      type: String,
      default: "/venues",
    },
  },

  data() {
    return {
      active: false,
      phase: "draw", // draw | shatter
    };
  },

  methods: {
    /* Call this from outside: this.$refs.portal.trigger() */
    async trigger(destination) {
      const dest = destination || this.to;
      this.active = true;
      this.phase = "draw";

      // Let court lines draw (CSS handles timing)
      await this._wait(1600);

      // Shatter phase: panels fly away revealing next page
      this.phase = "shatter";
      await this._wait(800);

      // Navigate
      this.$router.push(dest);
      await this._wait(300);
      this.active = false;
      this.phase = "draw";
    },

    panelStyle(n) {
      // 5 cols × 4 rows = 20 panels
      const col = (n - 1) % 5;
      const row = Math.floor((n - 1) / 5);
      // Random exit direction per panel
      const dx = (col - 2) * 200 + (Math.random() - 0.5) * 160;
      const dy = (row - 1.5) * 200 + (Math.random() - 0.5) * 120;
      const rot = (Math.random() - 0.5) * 60;
      const delay = Math.random() * 180;
      return {
        "--dx": `${dx}px`,
        "--dy": `${dy}px`,
        "--rot": `${rot}deg`,
        "--delay": `${delay}ms`,
      };
    },

    _wait(ms) {
      return new Promise((r) => setTimeout(r, ms));
    },
  },
};
</script>

<style scoped>
/* ─────────────────────────────────────────
   CSS CUSTOM PROPERTIES
───────────────────────────────────────── */
.cpt-root {
  --cpt-color: #4ade80;  /* Emerald neon glow */
  --cpt-glow: 0 0 12px rgba(74, 222, 128, 0.9), 0 0 40px rgba(74, 222, 128, 0.4);

  position: fixed;
  inset: 0;
  z-index: 9800;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

/* ─────────────────────────────────────────
   DARK BACKGROUND
───────────────────────────────────────── */
.cpt-bg {
  position: absolute;
  inset: 0;
  background: #06090f;
  animation: cpt-bg-in 0.35s ease-out forwards;
}

@keyframes cpt-bg-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}

/* ─────────────────────────────────────────
   COURT SVG — lines draw via stroke-dasharray
───────────────────────────────────────── */
.cpt-svg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

/* Base line style */
.cpt-line {
  stroke: var(--cpt-color);
  filter: var(--cpt-glow);
  fill: none;
}

/* All lines use stroke-dasharray trick to "draw" themselves */
.cpt-l0 {
  stroke-dasharray: 3760;
  stroke-dashoffset: 3760;
  animation: cpt-draw 0.9s cubic-bezier(0.4, 0, 0.2, 1) 0.1s forwards;
}

.cpt-l1 {
  stroke: var(--cpt-color);
  filter: var(--cpt-glow);
  stroke-dasharray: 700;
  stroke-dashoffset: 700;
  animation: cpt-draw 0.5s ease-out 0.7s forwards;
}

.cpt-l2 {
  stroke: var(--cpt-color);
  filter: var(--cpt-glow);
  stroke-dasharray: 1080;
  stroke-dashoffset: 1080;
  animation: cpt-draw 0.5s ease-out 0.7s forwards;
}

.cpt-l3 {
  stroke-dasharray: 1740;
  stroke-dashoffset: 1740;
  animation: cpt-draw 0.7s ease-out 1.0s forwards;
}

.cpt-l4 {
  stroke: var(--cpt-color);
  filter: var(--cpt-glow);
  stroke-dasharray: 260;
  stroke-dashoffset: 260;
  animation: cpt-draw 0.3s ease-out 1.2s forwards;
}

.cpt-l5 {
  stroke-dasharray: 1080;
  stroke-dashoffset: 1080;
  animation: cpt-draw 0.4s ease-out 1.35s forwards;
}

@keyframes cpt-draw {
  to { stroke-dashoffset: 0; }
}

/* Intersection dots */
.cpt-dot {
  opacity: 0;
  animation: cpt-dot-in 0.4s ease-out 1.4s forwards;
}

@keyframes cpt-dot-in {
  0%   { opacity: 0; transform: scale(0); transform-origin: center; }
  60%  { opacity: 1; transform: scale(1.6); }
  100% { opacity: 0.9; transform: scale(1); }
}

/* Radiating rings from center */
.cpt-ring {
  transform-origin: 720px 450px;
  opacity: 0;
}

.cpt-ring1 {
  animation: cpt-ring-pulse 1.2s ease-out 1.5s infinite;
}
.cpt-ring2 {
  animation: cpt-ring-pulse 1.2s ease-out 1.65s infinite;
}
.cpt-ring3 {
  animation: cpt-ring-pulse 1.2s ease-out 1.8s infinite;
}

@keyframes cpt-ring-pulse {
  0%   { opacity: 0.5; transform: scale(1); }
  100% { opacity: 0;   transform: scale(2.2); }
}

/* ─────────────────────────────────────────
   CENTER LABEL
───────────────────────────────────────── */
.cpt-label {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  text-align: center;
}

.cpt-label-line {
  display: block;
  color: var(--cpt-color);
  font-family: var(--sg-font-main, system-ui, sans-serif);
  opacity: 0;
  filter: var(--cpt-glow);
}

.cpt-ll1 {
  font-size: 48px;
  font-weight: 600;
  letter-spacing: 0.25em;
  animation: cpt-label-in 0.6s ease-out 1.1s forwards;
}

.cpt-ll2 {
  font-size: 14px;
  font-weight: 400;
  letter-spacing: 0.15em;
  color: rgba(74, 222, 128, 0.7);
  animation: cpt-label-in 0.5s ease-out 1.3s forwards;
}

@keyframes cpt-label-in {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ─────────────────────────────────────────
   SHATTER PANELS
───────────────────────────────────────── */
.cpt-shatter-grid {
  position: absolute;
  inset: 0;
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  grid-template-rows: repeat(4, 1fr);
  z-index: 3;
}

.cpt-panel {
  background: #06090f;
  border: 1px solid rgba(74, 222, 128, 0.08);
  animation: cpt-panel-fly 0.65s cubic-bezier(0.55, 0, 0.85, 0.06) var(--delay, 0ms) forwards;
}

@keyframes cpt-panel-fly {
  from {
    transform: translate(0, 0) rotate(0deg);
    opacity: 1;
  }
  to {
    transform: translate(var(--dx, 300px), var(--dy, -200px)) rotate(var(--rot, 15deg));
    opacity: 0;
  }
}

/* ─────────────────────────────────────────
   PAGE TRANSITION
───────────────────────────────────────── */
.cpt-fade-enter-active,
.cpt-fade-leave-active {
  transition: opacity 0.3s ease;
}
.cpt-fade-enter-from,
.cpt-fade-leave-to {
  opacity: 0;
}
</style>

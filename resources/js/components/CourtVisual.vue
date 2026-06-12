<template>
  <div 
    class="court-visual-wrapper" 
    :style="wrapperStyle"
    :class="[statusClass, { 'has-hover': interactive }]"
  >
    <svg 
      class="court-svg" 
      :viewBox="`0 0 ${viewBoxWidth} ${viewBoxHeight}`" 
      width="100%" 
      height="100%" 
      xmlns="http://www.w3.org/2000/svg"
    >
      <defs>
        <!-- Smooth sports-themed gradients -->
        <linearGradient id="grad-football" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#166534" />
          <stop offset="100%" stop-color="#14532d" />
        </linearGradient>
        <linearGradient id="grad-badminton" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#0f766e" />
          <stop offset="100%" stop-color="#115e59" />
        </linearGradient>
        <linearGradient id="grad-pickleball" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#1d4ed8" />
          <stop offset="100%" stop-color="#1e40af" />
        </linearGradient>
        <linearGradient id="grad-tennis" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#2563eb" />
          <stop offset="100%" stop-color="#1d4ed8" />
        </linearGradient>
        <linearGradient id="grad-basketball" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#ea580c" />
          <stop offset="100%" stop-color="#c2410c" />
        </linearGradient>
        <linearGradient id="grad-volleyball" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stop-color="#d97706" />
          <stop offset="100%" stop-color="#b45309" />
        </linearGradient>
        <linearGradient id="grad-default" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#475569" />
          <stop offset="100%" stop-color="#334155" />
        </linearGradient>
      </defs>
      
      <!-- Background Turf / Floor -->
      <rect 
        x="0" 
        y="0" 
        :width="viewBoxWidth" 
        :height="viewBoxHeight" 
        :fill="`url(#grad-${sportKey})`" 
        rx="6" 
      />

      <!-- Draw markings based on sport key -->
      <g stroke="#ffffff" :stroke-width="strokeWidth" fill="none" opacity="0.85">
        <!-- Football Pitch -->
        <template v-if="sportKey === 'football'">
          <!-- Outer border -->
          <rect x="6" y="6" :width="viewBoxWidth - 12" :height="viewBoxHeight - 12" />
          <!-- Center line -->
          <line :x1="viewBoxWidth / 2" y1="6" :x2="viewBoxWidth / 2" :y2="viewBoxHeight - 6" />
          <!-- Center Circle -->
          <circle :cx="viewBoxWidth / 2" :cy="viewBoxHeight / 2" r="22" />
          <circle :cx="viewBoxWidth / 2" :cy="viewBoxHeight / 2" r="2" fill="#ffffff" />
          <!-- Penalty Area Left -->
          <rect x="6" :y="viewBoxHeight / 2 - 28" width="28" height="56" />
          <rect x="6" :y="viewBoxHeight / 2 - 14" width="10" height="28" />
          <path :d="`M 34,${viewBoxHeight / 2 - 12} A 16,16 0 0,1 34,${viewBoxHeight / 2 + 12}`" />
          <!-- Penalty Area Right -->
          <rect :x="viewBoxWidth - 34" :y="viewBoxHeight / 2 - 28" width="28" height="56" />
          <rect :x="viewBoxWidth - 16" :y="viewBoxHeight / 2 - 14" width="10" height="28" />
          <path :d="`M ${viewBoxWidth - 34},${viewBoxHeight / 2 - 12} A 16,16 0 0,0 ${viewBoxWidth - 34},${viewBoxHeight / 2 + 12}`" />
          <!-- Corners -->
          <path d="M 6,12 A 6,6 0 0,0 12,6" />
          <path :d="`M 6,${viewBoxHeight - 12} A 6,6 0 0,1 12,${viewBoxHeight - 6}`" />
          <path :d="`M ${viewBoxWidth - 6},12 A 6,6 0 0,1 ${viewBoxWidth - 12},6`" />
          <path :d="`M ${viewBoxWidth - 6},${viewBoxHeight - 12} A 6,6 0 0,0 ${viewBoxWidth - 12},${viewBoxHeight - 6}`" />
        </template>

        <!-- Badminton Court -->
        <template v-else-if="sportKey === 'badminton'">
          <!-- Outer border -->
          <rect x="4" y="4" :width="viewBoxWidth - 8" :height="viewBoxHeight - 8" />
          <!-- Net representation -->
          <line x1="4" :y1="viewBoxHeight / 2" :x2="viewBoxWidth - 4" :y2="viewBoxHeight / 2" stroke-dasharray="3,3" />
          <!-- Short Service Lines -->
          <line x1="4" :y1="viewBoxHeight / 2 - 18" :x2="viewBoxWidth - 4" :y2="viewBoxHeight / 2 - 18" />
          <line x1="4" :y1="viewBoxHeight / 2 + 18" :x2="viewBoxWidth - 4" :y2="viewBoxHeight / 2 + 18" />
          <!-- Long Service Lines for Doubles -->
          <line x1="4" y1="12" :x2="viewBoxWidth - 4" y2="12" />
          <line x1="4" :y2="viewBoxHeight - 12" :x2="viewBoxWidth - 4" :y1="viewBoxHeight - 12" />
          <!-- Center Service Line (Bottom Half) -->
          <line :x1="viewBoxWidth / 2" :y1="viewBoxHeight / 2 - 18" :x2="viewBoxWidth / 2" y2="4" />
          <!-- Center Service Line (Top Half) -->
          <line :x1="viewBoxWidth / 2" :y1="viewBoxHeight / 2 + 18" :x2="viewBoxWidth / 2" :y2="viewBoxHeight - 4" />
          <!-- Singles Sidelines (Inner borders left/right) -->
          <line x1="8" y1="4" x2="8" :y2="viewBoxHeight - 4" />
          <line :x1="viewBoxWidth - 8" y1="4" :x2="viewBoxWidth - 8" :y2="viewBoxHeight - 4" />
        </template>

        <!-- Tennis Court -->
        <template v-else-if="sportKey === 'tennis'">
          <!-- Outer border -->
          <rect x="6" y="6" :width="viewBoxWidth - 12" :height="viewBoxHeight - 12" />
          <!-- Net line -->
          <line x1="6" :y1="viewBoxHeight / 2" :x2="viewBoxWidth - 6" :y2="viewBoxHeight / 2" stroke-dasharray="4,4" />
          <!-- Singles sidelines -->
          <line x1="12" y1="6" x2="12" :y2="viewBoxHeight - 6" />
          <line :x1="viewBoxWidth - 12" y1="6" :x2="viewBoxWidth - 12" :y2="viewBoxHeight - 6" />
          <!-- Service lines -->
          <line x1="12" :y1="viewBoxHeight / 2 - 32" :x2="viewBoxWidth - 12" :y2="viewBoxHeight / 2 - 32" />
          <line x1="12" :y1="viewBoxHeight / 2 + 32" :x2="viewBoxWidth - 12" :y2="viewBoxHeight / 2 + 32" />
          <!-- Center service line -->
          <line :x1="viewBoxWidth / 2" :y1="viewBoxHeight / 2 - 32" :x2="viewBoxWidth / 2" :y2="viewBoxHeight / 2 + 32" />
        </template>

        <!-- Pickleball Court -->
        <template v-else-if="sportKey === 'pickleball'">
          <!-- Outer border -->
          <rect x="4" y="4" :width="viewBoxWidth - 8" :height="viewBoxHeight - 8" />
          <!-- Net representing line -->
          <line x1="4" :y1="viewBoxHeight / 2" :x2="viewBoxWidth - 4" :y2="viewBoxHeight / 2" stroke-dasharray="2,2" />
          <!-- Non-Volley Zone (Kitchen) Lines -->
          <line x1="4" :y1="viewBoxHeight / 2 - 22" :x2="viewBoxWidth - 4" :y2="viewBoxHeight / 2 - 22" />
          <line x1="4" :y1="viewBoxHeight / 2 + 22" :x2="viewBoxWidth - 4" :y2="viewBoxHeight / 2 + 22" />
          <!-- Center Service Lines -->
          <line :x1="viewBoxWidth / 2" :y1="viewBoxHeight / 2 - 22" :x2="viewBoxWidth / 2" y2="4" />
          <line :x1="viewBoxWidth / 2" :y1="viewBoxHeight / 2 + 22" :x2="viewBoxWidth / 2" :y2="viewBoxHeight - 4" />
          <!-- Shading for the Kitchen -->
          <rect x="4.5" :y="viewBoxHeight / 2 - 21.5" :width="viewBoxWidth - 9" height="43" fill="#ffffff" fill-opacity="0.12" stroke="none" />
        </template>

        <!-- Basketball Court -->
        <template v-else-if="sportKey === 'basketball'">
          <rect x="6" y="6" :width="viewBoxWidth - 12" :height="viewBoxHeight - 12" />
          <!-- Center line -->
          <line :x1="viewBoxWidth / 2" y1="6" :x2="viewBoxWidth / 2" :y2="viewBoxHeight - 6" />
          <!-- Center Circle -->
          <circle :cx="viewBoxWidth / 2" :cy="viewBoxHeight / 2" r="18" />
          <!-- Left hoop area -->
          <rect x="6" :y="viewBoxHeight / 2 - 14" width="22" height="28" />
          <path :d="`M 6,${viewBoxHeight / 2 - 28} A 32,32 0 0,0 6,${viewBoxHeight / 2 + 28}`" />
          <circle cx="12" :cy="viewBoxHeight / 2" r="4" />
          <!-- Right hoop area -->
          <rect :x="viewBoxWidth - 28" :y="viewBoxHeight / 2 - 14" width="22" height="28" />
          <path :d="`M ${viewBoxWidth - 6},${viewBoxHeight / 2 - 28} A 32,32 0 0,1 ${viewBoxWidth - 6},${viewBoxHeight / 2 + 28}`" />
          <circle :cx="viewBoxWidth - 12" :cy="viewBoxHeight / 2" r="4" />
        </template>

        <!-- Volleyball Court -->
        <template v-else-if="sportKey === 'volleyball'">
          <rect x="5" y="5" :width="viewBoxWidth - 10" :height="viewBoxHeight - 10" />
          <!-- Center line -->
          <line :x1="viewBoxWidth / 2" y1="5" :x2="viewBoxWidth / 2" :y2="viewBoxHeight - 5" />
          <!-- Attack line left -->
          <line :x1="viewBoxWidth / 2 - 20" y1="5" :x2="viewBoxWidth / 2 - 20" :y2="viewBoxHeight - 5" />
          <!-- Attack line right -->
          <line :x1="viewBoxWidth / 2 + 20" y1="5" :x2="viewBoxWidth / 2 + 20" :y2="viewBoxHeight - 5" />
        </template>

        <!-- Default Generic Court representation -->
        <template v-else>
          <rect x="6" y="6" :width="viewBoxWidth - 12" :height="viewBoxHeight - 12" rx="4" />
          <line :x1="viewBoxWidth / 2" y1="6" :x2="viewBoxWidth / 2" :y2="viewBoxHeight - 6" stroke-dasharray="2,2" />
          <line x1="6" :y1="viewBoxHeight / 2" :x2="viewBoxWidth - 6" :y2="viewBoxHeight / 2" stroke-dasharray="2,2" />
        </template>
      </g>
    </svg>

    <!-- Visual status overlays (Maintenance stripes or Busy dims) -->
    <div v-if="status === 'maintenance'" class="maintenance-overlay">
      <div class="stripe-bg"></div>
      <div class="overlay-badge">
        <span class="icon">🔧</span>
        <span class="label">Bảo trì</span>
      </div>
    </div>

    <div v-if="status === 'inactive'" class="inactive-overlay">
      <div class="overlay-badge">
        <span class="icon">🔒</span>
        <span class="label">Tạm khóa</span>
      </div>
    </div>

    <div v-if="status === 'busy'" class="busy-overlay">
      <div class="overlay-badge">
        <span class="icon">🚫</span>
        <span class="label">Hết chỗ</span>
      </div>
    </div>

    <!-- Inner Label (Court name & type short description) -->
    <div class="court-label-container" :style="labelContainerStyle">
      <div class="court-name-text">{{ name }}</div>
      <div class="court-type-text" v-if="showType">{{ shortTypeName }}</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CourtVisual',
  props: {
    name: {
      type: String,
      required: true
    },
    courtTypeName: {
      type: String,
      default: ''
    },
    status: {
      type: String,
      default: 'active' // active, inactive, maintenance, busy (fully booked), selected
    },
    width: {
      type: Number,
      default: null
    },
    height: {
      type: Number,
      default: null
    },
    rotation: {
      type: Number,
      default: 0
    },
    interactive: {
      type: Boolean,
      default: false
    },
    showType: {
      type: Boolean,
      default: true
    }
  },
  computed: {
    sportKey() {
      const type = (this.courtTypeName || '').toLowerCase();
      if (type.includes('bóng đá') || type.includes('football') || type.includes('sân 7') || type.includes('sân 11')) return 'football';
      if (type.includes('cầu lông') || type.includes('badminton')) return 'badminton';
      if (type.includes('pickleball')) return 'pickleball';
      if (type.includes('bóng rổ') || type.includes('basketball')) return 'basketball';
      if (type.includes('bóng chuyền') || type.includes('volleyball')) return 'volleyball';
      if (type.includes('tennis')) return 'tennis';
      return 'default';
    },
    shortTypeName() {
      const type = this.courtTypeName || '';
      if (type.includes('Sân 11')) return 'Sân 11';
      if (type.includes('Sân 7')) return 'Sân 7';
      if (type.includes('Cầu lông')) return 'Cầu lông';
      if (type.includes('Pickleball')) return 'Pickleball';
      if (type.includes('Bóng rổ')) return 'Bóng rổ';
      if (type.includes('Bóng chuyền')) return 'Bóng chuyền';
      if (type.includes('Tennis')) return 'Tennis';
      return type.split(' ')[0] || '';
    },
    bgColor() {
      const colors = {
        football: '#15803d',     // Green 700
        badminton: '#0f766e',    // Teal 700
        pickleball: '#1d4ed8',   // Blue 700
        tennis: '#1e40af',       // Indigo 700
        basketball: '#c2410c',   // Orange 700
        volleyball: '#b45309',   // Amber 700
        default: '#475569'       // Slate 600
      };
      return colors[this.sportKey] || colors.default;
    },
    viewBoxWidth() {
      // Default viewports matching the aspect ratio
      const viewports = {
        football: 160,
        badminton: 60,
        pickleball: 60,
        tennis: 70,
        basketball: 140,
        volleyball: 120,
        default: 100
      };
      return viewports[this.sportKey] || viewports.default;
    },
    viewBoxHeight() {
      const viewports = {
        football: 100,
        badminton: 130,
        pickleball: 130,
        tennis: 150,
        basketball: 80,
        volleyball: 60,
        default: 100
      };
      return viewports[this.sportKey] || viewports.default;
    },
    strokeWidth() {
      return this.sportKey === 'badminton' || this.sportKey === 'pickleball' ? 1.2 : 1.6;
    },
    wrapperStyle() {
      const style = {};
      if (this.width !== null) {
        style.width = `${this.width}px`;
      }
      if (this.height !== null) {
        style.height = `${this.height}px`;
      }
      if (this.rotation !== 0) {
        style.transform = `rotate(${this.rotation}deg)`;
      }
      return style;
    },
    labelContainerStyle() {
      return {
        transform: `translate(-50%, -50%) rotate(${-this.rotation}deg)`
      };
    },
    statusClass() {
      return `status-${this.status}`;
    }
  }
};
</script>

<style scoped>
.court-visual-wrapper {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  user-select: none;
  background-color: #f1f5f9;
  border: 2px solid transparent;
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s, border-color 0.2s;
  box-sizing: border-box;
}

.court-svg {
  display: block;
}

/* Status colors and borders */
.status-selected {
  border-color: #10b981 !important; /* Green 500 */
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.5) !important;
}

.has-hover:hover {
  transform: scale(1.03) !important;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.15), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  cursor: pointer;
}

/* Rotation styles */
.court-visual-wrapper[style*="rotate"] {
  /* Maintain transform origins */
  transform-origin: center center;
}

/* Status Overlays */
.maintenance-overlay, 
.inactive-overlay, 
.busy-overlay {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(1px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 5;
}

.maintenance-overlay {
  background: rgba(120, 113, 108, 0.75); /* Stone overlay */
}

/* Diagonal stripes for maintenance */
.stripe-bg {
  position: absolute;
  inset: 0;
  opacity: 0.15;
  background-image: linear-gradient(
    45deg,
    #000 25%,
    transparent 25%,
    transparent 50%,
    #000 50%,
    #000 75%,
    transparent 75%,
    transparent
  );
  background-size: 20px 20px;
}

.overlay-badge {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  color: #ffffff;
  z-index: 6;
  text-align: center;
}

.overlay-badge .icon {
  font-size: 18px;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
}

.overlay-badge .label {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  background: rgba(0, 0, 0, 0.6);
  padding: 2px 6px;
  border-radius: 4px;
}

/* Labels */
.court-label-container {
  position: absolute;
  top: 50%;
  left: 50%;
  z-index: 15;
  color: #ffffff;
  text-align: center;
  pointer-events: none;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 6px 10px;
  background: rgba(15, 23, 42, 0.78);
  border-radius: 6px;
  border: 1.5px solid rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(4px);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
  max-width: 90%;
  white-space: nowrap;
}

.court-name-text {
  font-size: 11px;
  font-weight: 900;
  letter-spacing: -0.01em;
  text-transform: uppercase;
}

.court-type-text {
  font-size: 8px;
  font-weight: 700;
  opacity: 0.8;
  margin-top: 1px;
}
</style>

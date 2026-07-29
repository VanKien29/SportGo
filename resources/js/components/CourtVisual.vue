<template>
  <div 
    class="court-visual-wrapper" 
    :style="wrapperStyle"
    :class="[statusClass, { 'has-hover': interactive }]"
  >
    <!-- Custom Uploaded Court Image -->
    <img 
      v-if="imageUrl" 
      :src="imageUrl" 
      class="court-bg-img" 
      alt="Ảnh sân" 
    />

    <!-- Single Uniform Default Studio Surface (No Emojis, No line drawings) -->
    <div 
      v-else 
      class="court-clean-surface"
    >
      <div class="surface-accent-border"></div>
      <div class="sport-badge-watermark">
        <AppIcon name="map-pin" size="24" />
      </div>
    </div>

    <!-- Visual status overlays -->
    <div v-if="status === 'maintenance'" class="maintenance-overlay">
      <div class="stripe-bg"></div>
      <div class="overlay-badge">
        <AppIcon name="tool" size="14" />
        <span class="label">Bảo trì</span>
      </div>
    </div>

    <div v-if="status === 'inactive'" class="inactive-overlay">
      <div class="overlay-badge">
        <AppIcon name="lock" size="14" />
        <span class="label">Tạm khóa</span>
      </div>
    </div>

    <div v-if="status === 'busy'" class="busy-overlay">
      <div class="overlay-badge">
        <AppIcon name="circleX" size="14" />
        <span class="label">Hết chỗ</span>
      </div>
    </div>

    <!-- Inner Label Badge -->
    <div class="court-label-container" :style="labelContainerStyle">
      <div class="court-name-text">{{ name }}</div>
      <div class="court-type-text" v-if="showType">{{ shortTypeName }}</div>
    </div>
  </div>
</template>

<script>
import AppIcon from './AppIcon.vue';

export default {
  name: 'CourtVisual',
  components: { AppIcon },
  props: {
    imageUrl: {
      type: String,
      default: ''
    },
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
  data() {
    return {
      instanceId: Math.random().toString(36).substring(2, 9)
    };
  },
  computed: {
    sportKey() {
      const type = `${this.courtTypeName || ''} ${this.name || ''}`.toLowerCase();
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
    sportSurfaceBg() {
      const bgs = {
        football: 'linear-gradient(135deg, #16a34a 0%, #15803d 60%, #14532d 100%)',
        badminton: 'linear-gradient(135deg, #0d9488 0%, #0f766e 60%, #115e59 100%)',
        pickleball: 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 60%, #1e40af 100%)',
        tennis: 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 60%, #1e40af 100%)',
        basketball: 'linear-gradient(135deg, #f97316 0%, #ea580c 60%, #c2410c 100%)',
        volleyball: 'linear-gradient(135deg, #d97706 0%, #b45309 60%, #78350f 100%)',
        default: 'linear-gradient(135deg, #334155 0%, #1e293b 60%, #0f172a 100%)'
      };
      return bgs[this.sportKey] || bgs.default;
    },
    sportBadgeIcon() {
      const icons = {
        football: '⚽',
        badminton: '🏸',
        pickleball: '🏓',
        tennis: '🎾',
        basketball: '🏀',
        volleyball: '🏐',
        default: '🏟️'
      };
      return icons[this.sportKey] || icons.default;
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
      const style = {
        transform: `translate(-50%, -50%) rotate(${-this.rotation}deg)`
      };
      if (this.width && this.height) {
        const avg = (this.width + this.height) / 2;
        // Dùng căn bậc hai để giảm bớt tốc độ phóng to chữ, giúp nhãn hài hòa hơn
        const scale = Math.sqrt(avg / 95);
        const clampedScale = Math.max(0.6, Math.min(scale, 3.0));
        style.fontSize = `${11 * clampedScale}px`;
        style.padding = `${5 * clampedScale}px ${8 * clampedScale}px`;
        style.borderRadius = `${5 * clampedScale}px`;
        style.borderWidth = `${1.2 * clampedScale}px`;
      }
      return style;
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
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
  user-select: none;
  background-color: transparent;
  border: 2px solid transparent;
  transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s, border-color 0.2s;
  box-sizing: border-box;
}

.court-bg-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.court-clean-surface {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: var(--admin-surface-card, #1e293b);
  border: 1px solid rgba(255, 255, 255, 0.12);
}

.surface-accent-border {
  position: absolute;
  inset: 6px;
  border: 1px dashed rgba(255, 255, 255, 0.15);
  border-radius: 6px;
  pointer-events: none;
}

.sport-badge-watermark {
  color: rgba(255, 255, 255, 0.18);
  display: flex;
  align-items: center;
  justify-content: center;
  user-select: none;
  pointer-events: none;
}

/* Status colors and borders */
.status-selected {
  border-color: #10b981 !important; /* Green 500 */
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.5) !important;
}

.has-hover.never-hover-class-placeholder {
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
  font-weight: 400;
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
  padding: 6px 14px;
  background: rgba(15, 23, 42, 0.85);
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.25);
  backdrop-filter: blur(12px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.45);
  max-width: 88%;
  white-space: nowrap;
  font-size: 11px;
}

.court-name-text {
  font-size: 1.05em;
  font-weight: 400;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
}

.court-type-text {
  font-size: 0.73em;
  font-weight: 400;
  opacity: 0.8;
  margin-top: 0.1em;
}
</style>

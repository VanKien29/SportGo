<template>
  <div 
    class="decoration-visual-wrapper"
    :style="wrapperStyle"
    :class="[typeClass, { 'has-hover': interactive }]"
  >
    <div class="decoration-content">
      <!-- ENTRANCE -->
      <svg v-if="type === 'entrance'" class="decor-svg" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 32V8H28V32" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M28 20H18M18 20L22 16M18 20L22 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>

      <!-- RECEPTION -->
      <svg v-else-if="type === 'reception'" class="decor-svg" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="20" cy="14" r="6" stroke="currentColor" stroke-width="2.5"/>
        <path d="M8 32C8 26.4772 13.3726 22 20 22C26.6274 22 32 26.4772 32 32" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M6 32H34" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
      </svg>

      <!-- RESTROOM -->
      <svg v-else-if="type === 'restroom'" class="decor-svg" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <!-- Man -->
        <circle cx="14" cy="12" r="3.5" stroke="currentColor" stroke-width="2"/>
        <path d="M10 28V19C10 17.5 11 16.5 12.5 16.5H15.5C17 16.5 18 17.5 18 19V28" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
        <path d="M12 28V34H16V28" stroke="currentColor" stroke-width="2"/>
        <!-- Woman -->
        <circle cx="26" cy="12" r="3.5" stroke="currentColor" stroke-width="2"/>
        <path d="M22 28L23.5 17C23.7 16.5 24.3 16 25 16H27C27.7 16 28.3 16.5 28.5 17L30 28H22Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
        <path d="M24 28V34H28V28" stroke="currentColor" stroke-width="2"/>
        <!-- Divider -->
        <line x1="20" y1="8" x2="20" y2="32" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2 2"/>
      </svg>

      <!-- SEATING -->
      <svg v-else-if="type === 'seating'" class="decor-svg" viewBox="0 0 40 40" fill="none" xmlns="http://www/w3.org/2000/svg">
        <path d="M8 20H32" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M8 12V28" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M32 12V28" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M14 20V12H26V20" stroke="currentColor" stroke-dasharray="2 2" stroke-width="2"/>
      </svg>

      <!-- PARKING -->
      <svg v-else-if="type === 'parking'" class="decor-svg" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="8" y="8" width="24" height="24" rx="4" stroke="currentColor" stroke-width="2.5"/>
        <path d="M16 26V14H21C22.5 14 23.5 15 23.5 16.5V17.5C23.5 19 22.5 20 21 20H16" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/>
      </svg>

      <!-- CUSTOM -->
      <svg v-else class="decor-svg" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="20" cy="20" r="12" stroke="currentColor" stroke-width="2" stroke-dasharray="3 3"/>
      </svg>

      <span class="decor-label" :style="labelStyle">{{ name }}</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DecorationVisual',
  props: {
    type: {
      type: String,
      required: true // entrance, reception, restroom, seating, parking, custom
    },
    name: {
      type: String,
      default: ''
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
    }
  },
  computed: {
    typeClass() {
      return `decor-${this.type}`;
    },
    wrapperStyle() {
      const style = {};
      if (this.width !== null) {
        style.width = `${this.width}px`;
      } else {
        style.width = '100%';
      }
      if (this.height !== null) {
        style.height = `${this.height}px`;
      } else {
        style.height = '100%';
      }
      if (this.rotation !== 0) {
        style.transform = `rotate(${this.rotation}deg)`;
      }
      return style;
    },
    labelStyle() {
      return {
        transform: `rotate(${-this.rotation}deg)`
      };
    }
  }
};
</script>

<style scoped>
.decoration-visual-wrapper {
  position: relative;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: 2px dashed #94a3b8;
  background-color: #f8fafc;
  color: #64748b;
  overflow: hidden;
  user-select: none;
  transition: transform 0.1s ease, box-shadow 0.1s ease;
}

.decoration-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  width: 100%;
  height: 100%;
  padding: 6px;
  text-align: center;
}

.decor-svg {
  width: 24px;
  height: 24px;
  opacity: 0.85;
  flex-shrink: 0;
}

.decor-label {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
  max-width: 95%;
  transform-origin: center center;
}

.has-hover:hover {
  cursor: pointer;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
}

.decor-entrance {
  border: 2.5px solid #10b981;
  background-color: #ecfdf5;
  color: #047857;
}

.decor-reception {
  border: 2.5px solid #3b82f6;
  background-color: #eff6ff;
  color: #1d4ed8;
}

.decor-restroom {
  border: 2.5px solid #8b5cf6;
  background-color: #f5f3ff;
  color: #6d28d9;
}

.decor-seating {
  border: 2.5px solid #f97316;
  background-color: #fff7ed;
  color: #c2410c;
}

.decor-parking {
  border: 2.5px solid #06b6d4;
  background-color: #ecfeff;
  color: #0e7490;
}

.decor-custom {
  border: 2px dashed #64748b;
  background-color: #f1f5f9;
  color: #334155;
}
</style>

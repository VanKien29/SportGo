<template>
  <span class="sg-isometric-icon-wrap" :style="{ width: `${size}px`, height: `${size}px` }">
    <!-- CẦU LÔNG (BADMINTON ISOMETRIC 3D) -->
    <svg v-if="normalizedKey === 'badminton'" viewBox="0 0 48 48" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="badmTop" x1="24" y1="6" x2="24" y2="30" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#10b981"/>
          <stop offset="100%" stop-color="#047857"/>
        </linearGradient>
      </defs>
      <polygon points="6,20 24,30 24,36 6,26" fill="#065f46"/>
      <polygon points="24,30 42,20 42,26 24,36" fill="#064e3b"/>
      <polygon points="24,6 42,16 24,26 6,16" fill="url(#badmTop)"/>
      <polygon points="24,8 39,16 24,24 9,16" fill="none" stroke="#ffffff" stroke-width="1.1" stroke-opacity="0.9"/>
      <!-- Lưới 3D giữa sân -->
      <line x1="24" y1="8" x2="24" y2="2" stroke="#ffffff" stroke-width="1.4"/>
      <line x1="24" y1="24" x2="24" y2="18" stroke="#ffffff" stroke-width="1.4"/>
      <polygon points="24,2 24,18 24,24 24,8" fill="#ffffff" fill-opacity="0.35" stroke="#ffffff" stroke-width="0.8"/>
      <!-- Quả cầu lông 3D -->
      <circle cx="29" cy="11" r="2.5" fill="#ffffff"/>
      <polygon points="29,11 34,7 33,6 28,10" fill="#f8fafc"/>
    </svg>

    <!-- PICKLEBALL (PICKLEBALL ISOMETRIC 3D) -->
    <svg v-else-if="normalizedKey === 'pickleball'" viewBox="0 0 48 48" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="pballTop" x1="24" y1="6" x2="24" y2="30" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#3b82f6"/>
          <stop offset="100%" stop-color="#1d4ed8"/>
        </linearGradient>
        <linearGradient id="pballKitchen" x1="24" y1="12" x2="24" y2="20" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#06b6d4"/>
          <stop offset="100%" stop-color="#0891b2"/>
        </linearGradient>
      </defs>
      <polygon points="6,20 24,30 24,36 6,26" fill="#1e40af"/>
      <polygon points="24,30 42,20 42,26 24,36" fill="#172554"/>
      <polygon points="24,6 42,16 24,26 6,16" fill="url(#pballTop)"/>
      <polygon points="24,12 30,16 24,20 18,16" fill="url(#pballKitchen)"/>
      <polygon points="24,8 39,16 24,24 9,16" fill="none" stroke="#ffffff" stroke-width="1.1"/>
      <!-- Lưới 3D -->
      <line x1="24" y1="8" x2="24" y2="3" stroke="#ffffff" stroke-width="1.4"/>
      <line x1="24" y1="24" x2="24" y2="19" stroke="#ffffff" stroke-width="1.4"/>
      <polygon points="24,3 24,19 24,24 24,8" fill="#ffffff" fill-opacity="0.35" stroke="#ffffff" stroke-width="0.8"/>
      <!-- Quả bóng pickleball vàng chanh 3D -->
      <circle cx="15" cy="12" r="3" fill="#facc15" stroke="#ca8a04" stroke-width="0.7"/>
      <circle cx="15" cy="12" r="0.8" fill="#a16207"/>
    </svg>

    <!-- BÓNG ĐÁ (FOOTBALL ISOMETRIC 3D) -->
    <svg v-else-if="normalizedKey === 'football'" viewBox="0 0 48 48" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="grassTop" x1="24" y1="6" x2="24" y2="30" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#22c55e"/>
          <stop offset="100%" stop-color="#15803d"/>
        </linearGradient>
      </defs>
      <polygon points="6,20 24,30 24,36 6,26" fill="#14532d"/>
      <polygon points="24,30 42,20 42,26 24,36" fill="#0f3e22"/>
      <polygon points="24,6 42,16 24,26 6,16" fill="url(#grassTop)"/>
      <polygon points="24,8.5 39,16 24,23.5 9,16" fill="none" stroke="#ffffff" stroke-width="1.1" stroke-opacity="0.9"/>
      <line x1="24" y1="8.5" x2="24" y2="23.5" stroke="#ffffff" stroke-width="1.1" stroke-opacity="0.9"/>
      <ellipse cx="24" cy="16" rx="5.5" ry="2.8" fill="none" stroke="#ffffff" stroke-width="1.1" stroke-opacity="0.9"/>
      <!-- Quả bóng đá 3D -->
      <circle cx="24" cy="11" r="3.6" fill="#ffffff" stroke="#0f172a" stroke-width="0.9"/>
      <polygon points="24,9.5 25.5,10.6 24.9,12.5 23.1,12.5 22.5,10.6" fill="#0f172a"/>
    </svg>

    <!-- BÓNG RỔ (BASKETBALL ISOMETRIC 3D) -->
    <svg v-else-if="normalizedKey === 'basketball'" viewBox="0 0 48 48" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="woodTop" x1="24" y1="6" x2="24" y2="30" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#f97316"/>
          <stop offset="100%" stop-color="#c2410c"/>
        </linearGradient>
      </defs>
      <polygon points="6,20 24,30 24,36 6,26" fill="#9a3412"/>
      <polygon points="24,30 42,20 42,26 24,36" fill="#7c2d12"/>
      <polygon points="24,6 42,16 24,26 6,16" fill="url(#woodTop)"/>
      <polygon points="24,8 39,16 24,24 9,16" fill="none" stroke="#ffffff" stroke-width="1.1"/>
      <line x1="24" y1="8" x2="24" y2="24" stroke="#ffffff" stroke-width="1.1"/>
      <ellipse cx="24" cy="16" rx="4.8" ry="2.4" fill="none" stroke="#ffffff" stroke-width="1.1"/>
      <!-- Quả bóng rổ 3D -->
      <circle cx="28" cy="11" r="3.5" fill="#ea580c" stroke="#ffffff" stroke-width="0.8"/>
      <line x1="25" y1="11" x2="31" y2="11" stroke="#ffffff" stroke-width="0.6"/>
      <line x1="28" y1="8" x2="28" y2="14" stroke="#ffffff" stroke-width="0.6"/>
    </svg>

    <!-- QUẦN VỢT (TENNIS ISOMETRIC 3D) -->
    <svg v-else-if="normalizedKey === 'tennis'" viewBox="0 0 48 48" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="tennisTop" x1="24" y1="6" x2="24" y2="30" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#ea580c"/>
          <stop offset="100%" stop-color="#9a3412"/>
        </linearGradient>
      </defs>
      <polygon points="6,20 24,30 24,36 6,26" fill="#7c2d12"/>
      <polygon points="24,30 42,20 42,26 24,36" fill="#431407"/>
      <polygon points="24,6 42,16 24,26 6,16" fill="url(#tennisTop)"/>
      <polygon points="24,8 39,16 24,24 9,16" fill="none" stroke="#ffffff" stroke-width="1.1"/>
      <!-- Lưới tennis 3D -->
      <line x1="24" y1="8" x2="24" y2="3" stroke="#ffffff" stroke-width="1.4"/>
      <line x1="24" y1="24" x2="24" y2="19" stroke="#ffffff" stroke-width="1.4"/>
      <polygon points="24,3 24,19 24,24 24,8" fill="#ffffff" fill-opacity="0.35" stroke="#ffffff" stroke-width="0.8"/>
      <!-- Quả bóng tennis vàng xanh 3D -->
      <circle cx="18" cy="11" r="2.8" fill="#bef264" stroke="#65a30d" stroke-width="0.7"/>
    </svg>

    <!-- BÓNG CHUYỀN (VOLLEYBALL ISOMETRIC 3D) -->
    <svg v-else-if="normalizedKey === 'volleyball'" viewBox="0 0 48 48" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="vballTop" x1="24" y1="6" x2="24" y2="30" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#0284c7"/>
          <stop offset="100%" stop-color="#0369a1"/>
        </linearGradient>
      </defs>
      <polygon points="6,20 24,30 24,36 6,26" fill="#075985"/>
      <polygon points="24,30 42,20 42,26 24,36" fill="#0c4a6e"/>
      <polygon points="24,6 42,16 24,26 6,16" fill="url(#vballTop)"/>
      <polygon points="24,8 39,16 24,24 9,16" fill="none" stroke="#ffffff" stroke-width="1.1"/>
      <!-- Lưới bóng chuyền 3D cao -->
      <line x1="24" y1="8" x2="24" y2="1" stroke="#ffffff" stroke-width="1.4"/>
      <line x1="24" y1="24" x2="24" y2="17" stroke="#ffffff" stroke-width="1.4"/>
      <polygon points="24,1 24,17 24,24 24,8" fill="#ffffff" fill-opacity="0.38" stroke="#ffffff" stroke-width="0.8"/>
      <!-- Bóng chuyền 3D -->
      <circle cx="24" cy="6" r="3.2" fill="#facc15" stroke="#0284c7" stroke-width="0.8"/>
    </svg>

    <!-- TẤT CẢ / SÂN PHỨC HỢP (ALL SPORTS / TROPHY ISOMETRIC 3D) -->
    <svg v-else viewBox="0 0 48 48" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="stadiumGold" x1="24" y1="4" x2="24" y2="32" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#fbbf24"/>
          <stop offset="100%" stop-color="#d97706"/>
        </linearGradient>
      </defs>
      <polygon points="6,20 24,30 24,36 6,26" fill="#b45309"/>
      <polygon points="24,30 42,20 42,26 24,36" fill="#78350f"/>
      <polygon points="24,6 42,16 24,26 6,16" fill="url(#stadiumGold)"/>
      <!-- Cúp vàng 3D Isometric nổi khối -->
      <polygon points="24,9 31,13 24,17 17,13" fill="#fef08a"/>
      <polygon points="17,13 24,17 24,22 17,18" fill="#eab308"/>
      <polygon points="24,17 31,13 31,18 24,22" fill="#ca8a04"/>
      <!-- Ngôi sao phát sáng 3D -->
      <polygon points="24,2 25.5,5.5 29,6.5 25.5,7.5 24,11 22.5,7.5 19,6.5 22.5,5.5" fill="#ffffff"/>
    </svg>
  </span>
</template>

<script>
export default {
  name: "IsometricSportIcon",
  props: {
    name: {
      type: String,
      default: "all",
    },
    size: {
      type: [Number, String],
      default: 22,
    },
  },
  computed: {
    normalizedKey() {
      const key = String(this.name || "").toLowerCase().trim();
      if (key.includes("badminton") || key.includes("cầu lông")) return "badminton";
      if (key.includes("pickleball")) return "pickleball";
      if (key.includes("football") || key.includes("bóng đá") || key.includes("futsal")) return "football";
      if (key.includes("basketball") || key.includes("bóng rổ")) return "basketball";
      if (key.includes("tennis") || key.includes("quần vợt")) return "tennis";
      if (key.includes("volleyball") || key.includes("bóng chuyền")) return "volleyball";
      return "all";
    },
  },
};
</script>

<style scoped>
.sg-isometric-icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  vertical-align: middle;
}

.sg-isometric-svg {
  width: 100%;
  height: 100%;
  display: block;
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
}
</style>

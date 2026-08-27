<template>
  <span class="sg-isometric-icon-wrap" :style="{ width: `${size}px`, height: `${size}px` }">
    <!-- CẦU LÔNG (BADMINTON: VỢT CẦU LÔNG 3D ĐAN CƯỚC & QUẢ CẦU LÔNG TRẮNG) -->
    <svg v-if="normalizedKey === 'badminton'" viewBox="0 0 64 64" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <!-- Cán vợt cầu lông 3D xanh lá -->
      <line x1="12" y1="54" x2="28" y2="38" stroke="#15803d" stroke-width="4.5" stroke-linecap="round"/>
      <line x1="28" y1="38" x2="38" y2="28" stroke="#94a3b8" stroke-width="3" stroke-linecap="round"/>
      <!-- Khung mặt vợt elip 3D -->
      <ellipse cx="45" cy="21" rx="14" ry="10" transform="rotate(-45 45 21)" stroke="#16a34a" stroke-width="3" fill="#ffffff" fill-opacity="0.15"/>
      <!-- Lưới đan cước trong mặt vợt -->
      <line x1="39" y1="15" x2="51" y2="27" stroke="#86efac" stroke-width="1"/>
      <line x1="36" y1="21" x2="48" y2="33" stroke="#86efac" stroke-width="1"/>
      <line x1="42" y1="9" x2="54" y2="21" stroke="#86efac" stroke-width="1"/>
      <line x1="37" y1="26" x2="49" y2="14" stroke="#86efac" stroke-width="1"/>
      <line x1="42" y1="31" x2="54" y2="19" stroke="#86efac" stroke-width="1"/>
      <line x1="32" y1="21" x2="44" y2="9" stroke="#86efac" stroke-width="1"/>
      <!-- Quả cầu lông 3D xòe lông vũ -->
      <ellipse cx="23" cy="22" rx="4" ry="3.2" fill="#ef4444"/>
      <polygon points="23,20 12,10 14,8 24,19" fill="#f8fafc"/>
      <polygon points="25,23 20,7 23,6 26,22" fill="#ffffff"/>
      <polygon points="26,24 29,9 32,10 27,25" fill="#e2e8f0"/>
      <path d="M12,10 Q22,4 32,10" stroke="#cbd5e1" stroke-width="1" fill="none"/>
      <path d="M16,14 Q23,10 29,14" stroke="#cbd5e1" stroke-width="0.8" fill="none"/>
    </svg>

    <!-- PICKLEBALL (PICKLEBALL: VỢT PADDLE 3D CYAN & QUẢ BÓNG CÓ LỖ VÀNG CHANH) -->
    <svg v-else-if="normalizedKey === 'pickleball'" viewBox="0 0 64 64" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="paddleFace" x1="22" y1="12" x2="48" y2="38" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#38bdf8"/>
          <stop offset="50%" stop-color="#2563eb"/>
          <stop offset="100%" stop-color="#1d4ed8"/>
        </linearGradient>
        <linearGradient id="pballYellow" x1="14" y1="28" x2="30" y2="44" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#fef08a"/>
          <stop offset="60%" stop-color="#facc15"/>
          <stop offset="100%" stop-color="#ca8a04"/>
        </linearGradient>
      </defs>
      <!-- Cán vợt Paddle quấn dải 3D -->
      <line x1="14" y1="52" x2="26" y2="40" stroke="#1e293b" stroke-width="5.5" stroke-linecap="round"/>
      <line x1="16" y1="50" x2="19" y2="47" stroke="#ffffff" stroke-width="1.5"/>
      <line x1="20" y1="46" x2="23" y2="43" stroke="#ffffff" stroke-width="1.5"/>
      <!-- Mặt vợt Paddle chữ nhật bo góc 3D -->
      <rect x="22" y="12" width="26" height="30" rx="9" transform="rotate(-35 35 27)" fill="url(#paddleFace)" stroke="#1e3a8a" stroke-width="2"/>
      <rect x="24" y="14" width="22" height="26" rx="7" transform="rotate(-35 35 27)" stroke="#93c5fd" stroke-width="1" fill="none"/>
      <!-- Quả bóng Pickleball 3D nhiều lỗ tròn đặc trưng -->
      <circle cx="21" cy="33" r="10.5" fill="url(#pballYellow)" stroke="#a16207" stroke-width="1"/>
      <circle cx="21" cy="33" r="1.8" fill="#713f12"/>
      <circle cx="16" cy="30" r="1.6" fill="#713f12"/>
      <circle cx="26" cy="30" r="1.6" fill="#713f12"/>
      <circle cx="17" cy="37" r="1.6" fill="#713f12"/>
      <circle cx="25" cy="37" r="1.6" fill="#713f12"/>
      <circle cx="21" cy="26" r="1.5" fill="#713f12"/>
      <circle cx="21" cy="40" r="1.5" fill="#713f12"/>
    </svg>

    <!-- BÓNG ĐÁ (FOOTBALL: KHUNG THÀNH LƯỚI 3D & QUẢ BÓNG TELSTAR FIFA) -->
    <svg v-else-if="normalizedKey === 'football'" viewBox="0 0 64 64" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="ballShade" x1="16" y1="26" x2="38" y2="48" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#ffffff"/>
          <stop offset="100%" stop-color="#e2e8f0"/>
        </linearGradient>
      </defs>
      <!-- Khung thành & lưới 3D -->
      <polygon points="16,24 36,12 50,20 30,32" fill="#f1f5f9" fill-opacity="0.35"/>
      <line x1="20" y1="22" x2="34" y2="30" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="1.5 1.5"/>
      <line x1="24" y1="20" x2="38" y2="28" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="1.5 1.5"/>
      <line x1="28" y1="17" x2="42" y2="25" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="1.5 1.5"/>
      <line x1="32" y1="14" x2="46" y2="22" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="1.5 1.5"/>
      <!-- Cột & xà ngang khung thành 3D -->
      <line x1="16" y1="24" x2="16" y2="44" stroke="#ffffff" stroke-width="3" stroke-linecap="round"/>
      <line x1="16" y1="24" x2="42" y2="38" stroke="#ffffff" stroke-width="3" stroke-linecap="round"/>
      <line x1="42" y1="38" x2="42" y2="54" stroke="#ffffff" stroke-width="3" stroke-linecap="round"/>
      <line x1="16" y1="24" x2="30" y2="15" stroke="#cbd5e1" stroke-width="2.5"/>
      <line x1="42" y1="38" x2="56" y2="29" stroke="#cbd5e1" stroke-width="2.5"/>
      <line x1="30" y1="15" x2="56" y2="29" stroke="#cbd5e1" stroke-width="2.5"/>
      <!-- Quả bóng đá 3D Telstar to rõ -->
      <circle cx="28" cy="38" r="11" fill="url(#ballShade)" stroke="#0f172a" stroke-width="1.2"/>
      <polygon points="28,33 32,36 30.5,41 25.5,41 24,36" fill="#0f172a"/>
      <polygon points="28,33 27,28 29,28" fill="#0f172a"/>
      <polygon points="32,36 36.5,35 37.5,37" fill="#0f172a"/>
      <polygon points="30.5,41 33.5,45 32,46.5" fill="#0f172a"/>
      <polygon points="25.5,41 22.5,45 24,46.5" fill="#0f172a"/>
      <polygon points="24,36 19.5,35 18.5,37" fill="#0f172a"/>
    </svg>

    <!-- BÓNG RỔ (BASKETBALL: BẢNG RỔ MICA, VÀNH LƯỚI & QUẢ BÓNG RỔ NBA 3D) -->
    <svg v-else-if="normalizedKey === 'basketball'" viewBox="0 0 64 64" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="bballSkin" x1="16" y1="28" x2="38" y2="50" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#fb923c"/>
          <stop offset="60%" stop-color="#ea580c"/>
          <stop offset="100%" stop-color="#9a3412"/>
        </linearGradient>
      </defs>
      <!-- Bảng rổ 3D phía sau -->
      <polygon points="34,8 54,18 54,34 34,24" fill="#f8fafc" stroke="#dc2626" stroke-width="2"/>
      <polygon points="39,15 49,20 49,28 39,23" stroke="#dc2626" stroke-width="1.5" fill="none"/>
      <!-- Vành rổ sắt 3D -->
      <ellipse cx="36" cy="27" rx="10" ry="4.5" stroke="#ea580c" stroke-width="2.5" fill="none"/>
      <!-- Lưới bóng rổ trắng buông xuống 3D -->
      <path d="M27,28 L30,40 L42,40 L45,28" stroke="#ffffff" stroke-width="1.2" stroke-dasharray="2 1" fill="none"/>
      <line x1="30" y1="40" x2="36" y2="28" stroke="#ffffff" stroke-width="1.2" stroke-dasharray="2 1"/>
      <line x1="42" y1="40" x2="36" y2="28" stroke="#ffffff" stroke-width="1.2" stroke-dasharray="2 1"/>
      <!-- Quả bóng rổ 3D da cam nổi bật -->
      <circle cx="24" cy="38" r="11" fill="url(#bballSkin)" stroke="#7c2d12" stroke-width="1.2"/>
      <line x1="13" y1="38" x2="35" y2="38" stroke="#431407" stroke-width="1.2"/>
      <line x1="24" y1="27" x2="24" y2="49" stroke="#431407" stroke-width="1.2"/>
      <path d="M16,30 Q25,38 16,46" stroke="#431407" stroke-width="1.2" fill="none"/>
      <path d="M32,30 Q23,38 32,46" stroke="#431407" stroke-width="1.2" fill="none"/>
    </svg>

    <!-- QUẦN VỢT (TENNIS: VỢT TENNIS ĐỎ ĐEN & BÓNG TENNIS NỈ VÀNG NEON 3D) -->
    <svg v-else-if="normalizedKey === 'tennis'" viewBox="0 0 64 64" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="tballSkin" x1="16" y1="16" x2="32" y2="32" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#d9f99d"/>
          <stop offset="60%" stop-color="#a3e635"/>
          <stop offset="100%" stop-color="#4d7c0f"/>
        </linearGradient>
      </defs>
      <!-- Cán vợt tennis 3D -->
      <line x1="12" y1="54" x2="25" y2="41" stroke="#0f172a" stroke-width="5" stroke-linecap="round"/>
      <line x1="25" y1="41" x2="31" y2="35" stroke="#dc2626" stroke-width="4"/>
      <!-- Khung mặt vợt tennis elip 3D -->
      <ellipse cx="40" cy="25" rx="14" ry="11" transform="rotate(-45 40 25)" stroke="#dc2626" stroke-width="3" fill="#ffffff" fill-opacity="0.15"/>
      <!-- Lưới đan cước mặt vợt -->
      <line x1="35" y1="19" x2="47" y2="31" stroke="#cbd5e1" stroke-width="1"/>
      <line x1="31" y1="24" x2="43" y2="36" stroke="#cbd5e1" stroke-width="1"/>
      <line x1="38" y1="13" x2="50" y2="25" stroke="#cbd5e1" stroke-width="1"/>
      <line x1="33" y1="30" x2="45" y2="18" stroke="#cbd5e1" stroke-width="1"/>
      <line x1="38" y1="35" x2="50" y2="23" stroke="#cbd5e1" stroke-width="1"/>
      <!-- Quả bóng tennis nỉ vàng neon 3D có vệt uốn lượn -->
      <circle cx="24" cy="24" r="8.5" fill="url(#tballSkin)" stroke="#3f6212" stroke-width="1"/>
      <path d="M19,18 Q25,24 19,30" stroke="#ffffff" stroke-width="1.3" fill="none"/>
      <path d="M29,18 Q23,24 29,30" stroke="#ffffff" stroke-width="1.3" fill="none"/>
    </svg>

    <!-- BÓNG CHUYỀN (VOLLEYBALL: LƯỚI BÓNG CHUYỀN 3D & BÓNG CHUYỀN MIKASA 3 MÀU) -->
    <svg v-else-if="normalizedKey === 'volleyball'" viewBox="0 0 64 64" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="vballSkin" x1="24" y1="8" x2="44" y2="28" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#ffffff"/>
          <stop offset="100%" stop-color="#e2e8f0"/>
        </linearGradient>
      </defs>
      <!-- Cột lưới bóng chuyền 3D -->
      <line x1="18" y1="18" x2="18" y2="56" stroke="#0284c7" stroke-width="3" stroke-linecap="round"/>
      <line x1="56" y1="32" x2="56" y2="58" stroke="#0284c7" stroke-width="3" stroke-linecap="round"/>
      <!-- Lưới bóng chuyền 3D căng cao -->
      <polygon points="18,22 56,36 56,48 18,34" fill="#ffffff" fill-opacity="0.35" stroke="#ffffff" stroke-width="1.2"/>
      <line x1="18" y1="22" x2="56" y2="36" stroke="#ef4444" stroke-width="2.5"/>
      <!-- Mắt lưới 3D -->
      <line x1="27" y1="25" x2="27" y2="37" stroke="#ffffff" stroke-width="0.8" stroke-opacity="0.7"/>
      <line x1="36" y1="28" x2="36" y2="40" stroke="#ffffff" stroke-width="0.8" stroke-opacity="0.7"/>
      <line x1="45" y1="32" x2="45" y2="44" stroke="#ffffff" stroke-width="0.8" stroke-opacity="0.7"/>
      <line x1="18" y1="28" x2="56" y2="42" stroke="#ffffff" stroke-width="0.8" stroke-opacity="0.7"/>
      <!-- Quả bóng chuyền 3D bay cao trên lưới -->
      <circle cx="34" cy="18" r="10" fill="url(#vballSkin)" stroke="#0369a1" stroke-width="1.2"/>
      <path d="M28,11 C34,14 36,22 34,28" stroke="#0284c7" stroke-width="2.2" fill="none"/>
      <path d="M38,10 C42,16 40,24 34,28" stroke="#facc15" stroke-width="2.2" fill="none"/>
      <path d="M24,20 C30,22 35,21 44,18" stroke="#0284c7" stroke-width="2.2" fill="none"/>
      <path d="M26,24 C31,26 36,25 42,22" stroke="#facc15" stroke-width="2.2" fill="none"/>
    </svg>

    <!-- TẤT CẢ (ALL SPORTS: CÚP VÀNG VÔ ĐỊCH 3D NGUY NGA & ÁNH SÁNG) -->
    <svg v-else viewBox="0 0 64 64" fill="none" class="sg-isometric-svg" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="goldCup" x1="16" y1="12" x2="48" y2="40" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#fde047"/>
          <stop offset="50%" stop-color="#eab308"/>
          <stop offset="100%" stop-color="#ca8a04"/>
        </linearGradient>
      </defs>
      <!-- Đế cúp 3D đá cẩm thạch -->
      <polygon points="32,46 48,52 32,58 16,52" fill="#475569"/>
      <polygon points="16,52 32,58 32,62 16,56" fill="#1e293b"/>
      <polygon points="32,58 48,52 48,56 32,62" fill="#0f172a"/>
      <!-- Thân trụ cúp 3D -->
      <path d="M30,38 L34,38 L34,47 L30,47 Z" fill="#ca8a04"/>
      <ellipse cx="32" cy="46" rx="6" ry="2.5" fill="#eab308"/>
      <!-- Quai cúp 3D hai bên -->
      <path d="M20,18 C13,18 13,30 22,32 L22,29 C16,28 16,21 20,21 Z" fill="#ca8a04"/>
      <path d="M44,18 C51,18 51,30 42,32 L42,29 C48,28 48,21 44,21 Z" fill="#ca8a04"/>
      <!-- Thân cúp 3D vàng ánh kim -->
      <path d="M19,15 C19,30 25,38 32,38 C39,38 45,30 45,15 Z" fill="url(#goldCup)"/>
      <ellipse cx="32" cy="15" rx="13" ry="4.5" fill="#fef08a"/>
      <!-- Ngôi sao phát sáng trên cúp 3D -->
      <polygon points="32,20 33.5,23.5 37,24 34.5,26.5 35,30 32,28 29,30 29.5,26.5 27,24 30.5,23.5" fill="#ffffff" fill-opacity="0.9"/>
      <polygon points="44,8 45.2,11 48,12 45.2,13 44,16 42.8,13 40,12 42.8,11" fill="#fbbf24"/>
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
      default: 24,
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
}
</style>

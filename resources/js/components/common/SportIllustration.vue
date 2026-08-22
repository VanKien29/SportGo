<template>
  <div class="sg-illust-wrap" :style="{ width: size + 'px', height: size + 'px' }">
    <!-- ISOMETRIC 3D STADIUM MAP -->
    <svg
      v-if="name === 'iso-stadium-map'"
      viewBox="0 0 240 200"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="sg-illust-svg"
    >
      <!-- Base 3D Platform Plinth -->
      <path d="M120 180L20 125V105L120 160L220 105V125L120 180Z" fill="#065f46" />
      <path d="M20 105L120 50L220 105L120 160L20 105Z" fill="#047857" />
      <path d="M30 103L120 55L210 103L120 151L30 103Z" fill="#10b981" />
      <!-- Court Markings -->
      <path d="M50 103L120 65L190 103L120 141L50 103Z" stroke="#ffffff" stroke-width="1.8" stroke-opacity="0.8" fill="none" />
      <path d="M120 65L120 141" stroke="#ffffff" stroke-width="1.5" stroke-opacity="0.7" />
      <!-- Center Circle -->
      <ellipse cx="120" cy="103" rx="20" ry="11" stroke="#ffffff" stroke-width="1.5" stroke-opacity="0.8" fill="none" />
      <!-- 3D Floodlight Pole Left -->
      <path d="M38 90V40" stroke="#047857" stroke-width="3" stroke-linecap="round" />
      <polygon points="32,40 44,40 41,32 35,32" fill="#fbbf24" />
      <circle cx="38" cy="36" r="3" fill="#ffffff" />
      <!-- 3D Floodlight Pole Right -->
      <path d="M202 90V40" stroke="#047857" stroke-width="3" stroke-linecap="round" />
      <polygon points="196,40 208,40 205,32 199,32" fill="#fbbf24" />
      <circle cx="202" cy="36" r="3" fill="#ffffff" />
      <!-- 3D Holographic Status Beam -->
      <ellipse cx="120" cy="40" rx="35" ry="8" fill="#34d399" fill-opacity="0.25" />
      <path d="M120 22L128 32H112L120 22Z" fill="#10b981" />
    </svg>

    <!-- ISOMETRIC 3D CASH DRAWER & VAULT -->
    <svg
      v-else-if="name === 'iso-cash-drawer'"
      viewBox="0 0 240 200"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="sg-illust-svg"
    >
      <!-- Base Plinth -->
      <path d="M120 175L30 125L120 75L210 125L120 175Z" fill="#e2e8f0" />
      <!-- Drawer Chassis -->
      <path d="M40 120L120 75L200 120L120 165L40 120Z" fill="#1e293b" />
      <path d="M40 120L120 165V180L40 135V120Z" fill="#0f172a" />
      <path d="M200 120L120 165V180L200 135V120Z" fill="#334155" />
      <!-- Slide-out Tray -->
      <path d="M55 138L120 102L185 138L120 174L55 138Z" fill="#334155" />
      <!-- Banknotes inside tray -->
      <path d="M70 135L105 115L135 132L100 152L70 135Z" fill="#10b981" />
      <path d="M73 133L105 115L133 131L101 149L73 133Z" fill="#34d399" />
      <path d="M110 142L140 125L170 142L140 159L110 142Z" fill="#3b82f6" />
      <!-- 3D Gold Coins -->
      <ellipse cx="145" cy="120" rx="10" ry="6" fill="#f59e0b" />
      <ellipse cx="145" cy="118" rx="10" ry="6" fill="#fbbf24" />
      <ellipse cx="152" cy="114" rx="10" ry="6" fill="#f59e0b" />
      <ellipse cx="152" cy="112" rx="10" ry="6" fill="#fbbf24" />
      <!-- Floating Shield -->
      <path d="M120 40L145 52V76C145 92 120 104 120 104C120 104 95 92 95 76V52L120 40Z" fill="#087642" />
      <path d="M115 72L128 59" stroke="#ffffff" stroke-width="3" stroke-linecap="round" />
      <path d="M110 66L115 72" stroke="#ffffff" stroke-width="3" stroke-linecap="round" />
    </svg>

    <!-- ISOMETRIC 3D QR TICKET SCANNER -->
    <svg
      v-else-if="name === 'iso-qr-scanner'"
      viewBox="0 0 240 200"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="sg-illust-svg"
    >
      <!-- Base Plinth -->
      <path d="M120 175L35 125L120 75L205 125L120 175Z" fill="#ecfdf5" />
      <!-- Floating Phone Ticket -->
      <path d="M70 105L120 75L170 105L120 135L70 105Z" fill="#ffffff" stroke="#cbd5e1" stroke-width="2" />
      <!-- QR Pattern on Phone -->
      <path d="M95 105L120 90L145 105L120 120L95 105Z" fill="#0f172a" />
      <path d="M103 105L120 95L137 105L120 115L103 105Z" fill="#ffffff" />
      <path d="M111 105L120 100L129 105L120 110L111 105Z" fill="#087642" />
      <!-- Laser Scan Plane -->
      <path d="M60 85L120 50L180 85L120 120L60 85Z" fill="#10b981" fill-opacity="0.2" stroke="#10b981" stroke-width="2" stroke-dasharray="4 4" />
      <!-- 3D Scanner Device -->
      <path d="M110 20L130 20L140 45L120 52L100 45L110 20Z" fill="#1e293b" />
      <circle cx="120" cy="46" r="6" fill="#10b981" />
      <!-- Scan Beam Lines -->
      <line x1="120" y1="52" x2="70" y2="105" stroke="#10b981" stroke-width="1.5" stroke-opacity="0.6" />
      <line x1="120" y1="52" x2="170" y2="105" stroke="#10b981" stroke-width="1.5" stroke-opacity="0.6" />
    </svg>

    <!-- ISOMETRIC 3D F&B RETAIL KIOSK -->
    <svg
      v-else-if="name === 'iso-kiosk-fnb'"
      viewBox="0 0 240 200"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="sg-illust-svg"
    >
      <!-- Base Plinth -->
      <path d="M120 175L35 125L120 75L205 125L120 175Z" fill="#f1f5f9" />
      <!-- Refrigerator Chassis -->
      <path d="M70 125L120 95L170 125V65L120 35L70 65V125Z" fill="#ffffff" stroke="#cbd5e1" stroke-width="2" />
      <path d="M120 95L170 125V65L120 35V95Z" fill="#f8fafc" />
      <!-- Glass Shelves & Beverages -->
      <path d="M80 85L120 60L160 85" stroke="#38bdf8" stroke-width="1.5" stroke-opacity="0.8" />
      <!-- Drinks Row 1 -->
      <rect x="92" y="65" width="8" height="14" rx="2" fill="#ef4444" />
      <rect x="105" y="58" width="8" height="14" rx="2" fill="#10b981" />
      <rect x="118" y="52" width="8" height="14" rx="2" fill="#3b82f6" />
      <rect x="131" y="60" width="8" height="14" rx="2" fill="#f59e0b" />
      <rect x="144" y="68" width="8" height="14" rx="2" fill="#8b5cf6" />
      <!-- POS Tap Scanner on the right -->
      <ellipse cx="178" cy="120" rx="12" ry="7" fill="#087642" />
      <path d="M178 100V120" stroke="#087642" stroke-width="2.5" />
    </svg>

    <!-- EMPTY COURT / NO BOOKINGS -->
    <svg
      v-else-if="name === 'empty-court'"
      viewBox="0 0 200 200"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="sg-illust-svg"
    >
      <rect x="25" y="45" width="150" height="110" rx="12" fill="#ECFDF5" stroke="#10B981" stroke-width="2.5" />
      <line x1="100" y1="45" x2="100" y2="155" stroke="#059669" stroke-width="2" stroke-dasharray="4 4" />
      <circle cx="100" cy="100" r="22" stroke="#059669" stroke-width="2" />
      <circle cx="100" cy="100" r="4" fill="#10B981" />
      <rect x="25" y="70" width="30" height="60" rx="4" stroke="#059669" stroke-width="1.5" />
      <rect x="145" y="70" width="30" height="60" rx="4" stroke="#059669" stroke-width="1.5" />
      <path d="M40 30C80 10 140 15 175 40" stroke="#34D399" stroke-width="3" stroke-linecap="round" />
      <circle cx="175" cy="40" r="6" fill="#FBBF24" />
    </svg>

    <!-- WALK-IN QUICK READY -->
    <svg
      v-else-if="name === 'walk-in'"
      viewBox="0 0 200 200"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="sg-illust-svg"
    >
      <circle cx="100" cy="100" r="80" fill="#F0FDF4" />
      <rect x="60" y="55" width="80" height="95" rx="10" fill="#FFFFFF" stroke="#087642" stroke-width="2.5" />
      <circle cx="100" cy="85" r="14" fill="#D1FAE5" stroke="#087642" stroke-width="2" />
      <path d="M78 125C78 112 88 105 100 105C112 105 122 112 122 125" stroke="#087642" stroke-width="2" stroke-linecap="round" />
      <circle cx="135" cy="65" r="14" fill="#10B981" />
      <path d="M135 58V72M128 65H142" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" />
    </svg>

    <!-- SHIFT COMPLETED / ATTENDANCE DONE -->
    <svg
      v-else-if="name === 'shift-done'"
      viewBox="0 0 200 200"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="sg-illust-svg"
    >
      <circle cx="100" cy="100" r="75" fill="#ECFDF5" />
      <circle cx="100" cy="100" r="55" stroke="#10B981" stroke-width="2.5" stroke-dasharray="6 6" />
      <circle cx="100" cy="100" r="42" fill="#087642" />
      <path d="M86 100L96 110L116 88" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
    </svg>

    <!-- DEFAULT ATHLETIC SHIELD -->
    <svg
      v-else
      viewBox="0 0 200 200"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      class="sg-illust-svg"
    >
      <circle cx="100" cy="100" r="75" fill="#F8FAFC" />
      <path d="M100 40L145 60V110C145 140 100 165 100 165C100 165 55 140 55 110V60L100 40Z" fill="#ECFDF5" stroke="#087642" stroke-width="2.5" />
      <circle cx="100" cy="102" r="18" fill="#10B981" />
      <path d="M93 102L98 107L109 96" stroke="#FFFFFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
  </div>
</template>

<script>
export default {
  name: 'SportIllustration',
  props: {
    name: {
      type: String,
      default: 'empty-court',
    },
    size: {
      type: [Number, String],
      default: 140,
    },
  },
};
</script>

<style scoped>
.sg-illust-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.sg-illust-svg {
  width: 100%;
  height: 100%;
  object-fit: contain;
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.sg-illust-wrap:hover .sg-illust-svg {
  transform: scale(1.04);
}
</style>

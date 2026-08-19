<template>
  <div class="sg-illust-wrap" :style="{ width: size + 'px', height: size + 'px' }">
    <!-- EMPTY COURT / NO BOOKINGS -->
    <svg
      v-if="name === 'empty-court'"
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
      <!-- Subtle Dynamic Orbit Arc -->
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
      <!-- Plus badge -->
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
  transform: scale(1.05);
}
</style>

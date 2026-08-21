<template>
  <div class="sg-auth-canvas flex items-center justify-center min-h-screen p-4 md:p-8 font-sans select-none">
    <!-- Master Floating Card -->
    <div class="sg-auth-master-card w-full max-w-[920px] grid grid-cols-1 md:grid-cols-2 rounded-[24px] overflow-hidden shadow-2xl relative">
      
      <!-- Back to Home Link (Absolute Top Corner) -->
      <router-link :to="backTo" class="sg-auth-back-link absolute top-4 left-4 z-10 inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full backdrop-blur-md transition-all">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        <span>Trang chủ</span>
      </router-link>

      <!-- LEFT PANEL: Sage Green Artistic Showcase -->
      <div class="sg-auth-panel-art hidden md:flex flex-col items-center justify-between p-10 text-center relative overflow-hidden">
        <!-- Artwork Center -->
        <div class="sg-auth-art-frame flex-1 flex items-center justify-center my-auto w-full">
          <img
            :src="imageSrc"
            alt="SportGo Art Showcase"
            class="sg-auth-art-img max-w-[260px] max-h-[260px] object-contain select-none"
            :key="imageSrc"
          />
        </div>

        <!-- Slogan & Subtext -->
        <div class="sg-auth-art-copy w-full max-w-[320px] mt-4 mb-3">
          <h3 class="text-xl font-bold text-white tracking-tight mb-2">
            {{ quoteTitle || 'Khởi động đam mê' }}
          </h3>
          <p class="text-xs text-emerald-50/90 leading-relaxed font-normal">
            {{ quoteText || 'Đặt sân thể thao dễ dàng, kết nối bạn chơi và thỏa sức bứt phá từng điểm số.' }}
          </p>
        </div>

        <!-- Slider Indicator Dots -->
        <div class="sg-auth-art-dots flex items-center justify-center gap-1.5 mt-2">
          <span class="sg-dot-pill"></span>
          <span class="sg-dot-circle"></span>
          <span class="sg-dot-circle"></span>
        </div>
      </div>

      <!-- RIGHT PANEL: Clean White Minimalist Form -->
      <div class="sg-auth-panel-form bg-white flex flex-col justify-center px-8 py-10 md:px-12 md:py-12 relative">
        <!-- Brand Script Logo -->
        <div class="sg-auth-brand-header text-center mb-6">
          <div class="sg-auth-script-logo">SportGo</div>
          <p class="sg-auth-welcome-text text-sm text-zinc-500 mt-1">{{ subtitle || 'Chào mừng đến với SportGo' }}</p>
        </div>

        <!-- Main Form Slot -->
        <slot></slot>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'AuthLayout',
  props: {
    title: {
      type: String,
      default: 'SportGo',
    },
    subtitle: {
      type: String,
      default: 'Chào mừng đến với SportGo',
    },
    quoteTitle: {
      type: String,
      default: 'Khởi động đam mê',
    },
    imageSrc: {
      type: String,
      default: '/images/auth/sportgo_art.png',
    },
    quoteText: {
      type: String,
      default: 'Đặt sân thể thao dễ dàng, kết nối bạn chơi và thỏa sức bứt phá từng điểm số.',
    },
    backTo: {
      type: String,
      default: '/',
    },
  },
};
</script>

<template>
  <div class="auth-layout sg-auth-layout-v3 w-full min-h-screen md:grid md:grid-cols-[minmax(0,0.92fr)_minmax(420px,1.08fr)] flex flex-col font-sans select-none">
    <!-- Mobile Back Link -->
    <div class="sg-auth-mobile-back md:hidden flex items-center p-4">
      <router-link :to="backTo" class="inline-flex items-center gap-2 text-sm font-medium">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Về trang chủ
      </router-link>
    </div>

    <!-- Left Column: Forms -->
    <div class="flex flex-col justify-center items-center px-6 py-8 md:px-16 lg:px-24 relative">
      <!-- Desktop Back to Home (top left) -->
      <div class="sg-auth-desktop-back absolute top-8 left-8 hidden md:block">
        <router-link :to="backTo" class="inline-flex items-center gap-2 text-sm font-medium">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          Về trang chủ
        </router-link>
      </div>

      <div class="sg-auth-form-column w-full max-w-[430px]">
        <!-- Brand / Header -->
        <div class="flex flex-col items-center text-center !mb-8">
          <h1 class="text-2xl font-normal tracking-tight text-white">{{ title }}</h1>
          <p class="text-sm text-zinc-400 !mt-2">{{ subtitle }}</p>
        </div>

        <!-- Main Form Slot -->
        <slot></slot>
      </div>
    </div>

    <!-- Right Column: Visual Showcase -->
    <div class="sg-auth-visual hidden md:flex flex-col justify-center items-center px-12 py-12 relative min-h-screen">
      <div class="sg-auth-visual-grid" aria-hidden="true"></div>
      <div class="sg-auth-visual-copy">
        <span class="sg-auth-visual-kicker">Đặt sân dễ hơn mỗi ngày</span>
        <h2>Tìm đúng sân.<br /><strong>Chơi đúng nhịp.</strong></h2>
        <p>Khám phá cụm sân phù hợp, chọn khung giờ còn trống và giữ lịch trong vài phút.</p>
      </div>
      <div class="sg-auth-image-frame w-full flex items-center justify-center max-h-[52%] my-auto">
        <img
          :src="imageSrc"
          alt="Auth visual showcase"
          class="max-w-[85%] max-h-[85%] object-contain select-none transition-all duration-500"
          :key="imageSrc"
        />
      </div>

      <blockquote class="sg-auth-quote absolute bottom-12 left-1/2 -translate-x-1/2 w-full max-w-[80%] md:max-w-lg text-center space-y-1.5 px-4">
        <p class="text-base md:text-lg font-medium leading-relaxed tracking-tight">
          “<Typewriter :key="quoteText" :text="quoteText" :speed="50" :loop="true" :delay="2000" />”
        </p>
        <cite class="block text-xs not-italic tracking-wide">
          {{ quoteAuthor }}
        </cite>
      </blockquote>
    </div>
  </div>
</template>

<script>
import Typewriter from './Typewriter.vue';

export default {
  name: 'AuthLayout',
  components: {
    Typewriter,
  },
  props: {
    title: {
      type: String,
      required: true,
    },
    subtitle: {
      type: String,
      default: '',
    },
    imageSrc: {
      type: String,
      required: true,
    },
    quoteText: {
      type: String,
      required: true,
    },
    quoteAuthor: {
      type: String,
      default: 'SportGo',
    },
    backTo: {
      type: String,
      default: '/',
    },
  },
};
</script>

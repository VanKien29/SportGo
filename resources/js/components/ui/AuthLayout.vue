<template>
  <div class="auth-layout w-full min-h-screen md:grid md:grid-cols-2 bg-zinc-950 text-zinc-100 flex flex-col font-sans select-none">
    <!-- Mobile Back Link -->
    <div class="md:hidden flex items-center p-4">
      <router-link :to="backTo" class="inline-flex items-center gap-2 text-zinc-500 hover:text-zinc-300 transition-colors text-sm font-medium">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Về trang chủ
      </router-link>
    </div>

    <!-- Left Column: Forms -->
    <div class="flex flex-col justify-center items-center px-6 py-8 md:px-16 lg:px-24 relative">
      <!-- Desktop Back to Home (top left) -->
      <div class="absolute top-8 left-8 hidden md:block">
        <router-link :to="backTo" class="inline-flex items-center gap-2 text-zinc-500 hover:text-zinc-300 transition-colors text-sm font-medium">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          Về trang chủ
        </router-link>
      </div>

      <div class="w-full max-w-[350px]">
        <!-- Brand / Header -->
        <div class="flex flex-col items-center text-center !mb-8">
          <h1 class="text-2xl font-bold tracking-tight text-white">{{ title }}</h1>
          <p class="text-sm text-zinc-400 !mt-2">{{ subtitle }}</p>
        </div>

        <!-- Main Form Slot -->
        <slot></slot>
      </div>
    </div>

    <!-- Right Column: Visual Showcase -->
    <div class="hidden md:flex flex-col justify-center items-center bg-zinc-950 px-12 py-12 border-l border-zinc-900 relative min-h-screen">
      <!-- Centered Astronaut Image Container -->
      <div class="w-full flex items-center justify-center max-h-[60%] my-auto">
        <img
          :src="imageSrc"
          alt="Auth visual showcase"
          class="max-w-[85%] max-h-[85%] object-contain select-none transition-all duration-500"
          :key="imageSrc"
        />
      </div>

      <!-- Bottom Quote (Absolutely Positioned to guarantee bottom spacing) -->
      <blockquote class="absolute bottom-20 left-1/2 -translate-x-1/2 w-full max-w-[80%] md:max-w-sm text-center space-y-1.5 px-4">
        <p class="text-base md:text-lg font-medium text-zinc-100 leading-relaxed tracking-tight">
          “<Typewriter :key="quoteText" :text="quoteText" :speed="50" :loop="true" :delay="2000" />”
        </p>
        <cite class="block text-xs text-zinc-500 not-italic tracking-wide">
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

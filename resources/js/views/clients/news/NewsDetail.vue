<template>
  <div class="sg-client-page sg-news-detail-page">
    <PublicNavbar />

    <main class="sg-client-reading-shell">
      <nav class="sg-community-breadcrumb" aria-label="Điều hướng tin tức">
        <router-link :to="{ name: 'ClientNewsList' }">
          <AppIcon name="chevronLeft" size="16" />
          Quay lại tin tức
        </router-link>
      </nav>

      <div v-if="loading" class="sg-client-state">
        <span class="sg-community-spinner" aria-hidden="true"></span>
        <strong>Đang tải bài viết...</strong>
      </div>
      <div v-else-if="error" class="sg-client-state sg-community-state-error" role="alert">
        <AppIcon name="alert" size="30" />
        <strong>Không thể mở bài viết</strong>
        <p>{{ error }}</p>
        <router-link class="sg-client-button" :to="{ name: 'ClientNewsList' }">Quay lại danh sách</router-link>
      </div>

      <article v-else-if="post" class="sg-client-card sg-news-article">
        <header>
          <span v-if="post.category" class="sg-client-status">{{ categoryLabel(post.category) }}</span>
          <h1>{{ post.title }}</h1>
          <div class="sg-news-author-row">
            <span class="sg-community-avatar sg-community-avatar-small">
              <img v-if="post.author?.avatar_url" :src="post.author.avatar_url" :alt="authorName" />
              <span v-else>{{ authorInitial }}</span>
            </span>
            <div>
              <strong>{{ authorName }}</strong>
              <small>
                {{ formatDate(post.published_at) }}
                <span aria-hidden="true">·</span>
                {{ Number(post.view_count || 0).toLocaleString('vi-VN') }} lượt xem
              </small>
            </div>
          </div>
        </header>

        <img
          v-if="post.thumbnail_path"
          class="sg-news-article-cover"
          :src="mediaUrl(post.thumbnail_path)"
          :alt="post.title"
          @error="post.thumbnail_path = ''"
        />
        <section class="sg-news-article-body" v-html="post.content"></section>
        <footer>
          <router-link class="sg-client-button" :to="{ name: 'ClientNewsList' }">
            <AppIcon name="chevronLeft" size="16" />
            Quay lại danh sách
          </router-link>
        </footer>
      </article>
    </main>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AppIcon from '@/components/AppIcon.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import { api } from '@/services/api.js';
import { normalizeMediaUrl } from '@/utils/mediaUrl.js';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

const route = useRoute();
const post = ref(null);
const loading = ref(true);
const error = ref('');
const originalTitle = document.title;
const authorName = computed(() => post.value?.author?.full_name || post.value?.author?.username || 'Đội ngũ SportGo');
const authorInitial = computed(() => authorName.value.charAt(0).toUpperCase());

async function fetchPostDetail() {
  loading.value = true;
  error.value = '';
  try {
    const response = await api(`/api/system-news/${route.params.slug}`);
    post.value = response.data;
    if (post.value?.title) document.title = `${post.value.title} - SportGo`;
  } catch (requestError) {
    post.value = null;
    error.value = requestError.status === 404
      ? 'Bài viết không tồn tại hoặc đã bị ẩn.'
      : requestError.message || 'Không thể tải bài viết.';
  } finally {
    loading.value = false;
  }
}

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
  }).format(new Date(value));
}

function categoryLabel(value) {
  return {
    announcement: 'Thông báo',
    guide: 'Hướng dẫn',
    news: 'Tin tức',
    event: 'Sự kiện',
  }[value] || value;
}

function mediaUrl(path) {
  return normalizeMediaUrl({ file_path: path });
}

watch(() => route.params.slug, fetchPostDetail, { immediate: true });
onBeforeUnmount(() => { document.title = originalTitle; });
</script>

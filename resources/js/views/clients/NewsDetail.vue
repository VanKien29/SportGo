<template>
  <div class="news-detail-page">
    <PublicNavbar />

    <main class="news-detail-shell">
      <div v-if="loading" class="news-state">Đang tải bài viết...</div>

      <div v-else-if="error" class="news-state news-state--error">
        <p>{{ error }}</p>
        <router-link :to="{ name: 'home', hash: '#news' }">Quay lại tin tức</router-link>
      </div>

      <article v-else-if="post" class="article-layout">
        <nav class="article-breadcrumbs" aria-label="Breadcrumb">
          <router-link :to="{ name: 'home' }">Trang chủ</router-link>
          <span>/</span>
          <router-link :to="{ name: 'home', hash: '#news' }">Tin tức</router-link>
        </nav>

        <header class="article-header">
          <div class="article-meta">
            <span>{{ categoryLabel(post.post_type) }}</span>
            <span>{{ formatDate(post.created_at) }}</span>
            <span>{{ post.view_count || 0 }} lượt xem</span>
          </div>
          <h1>{{ post.title }}</h1>
          <p>{{ post.short_description }}</p>
        </header>

        <figure class="article-cover">
          <img :src="postImage(post)" :alt="post.title" />
        </figure>

        <section class="article-body" v-html="post.content"></section>

        <footer class="article-footer">
          <div>
            <strong>{{ post.venue_cluster?.name || "SportGo" }}</strong>
            <span>{{ post.author?.full_name || post.author?.username || "Ban biên tập SportGo" }}</span>
          </div>
          <router-link
            v-if="post.venue_cluster?.id"
            :to="{ name: 'venue-detail', params: { id: post.venue_cluster.id }, query: { tab: 'posts' } }"
          >
            Xem cụm sân
          </router-link>
        </footer>
      </article>
    </main>
  </div>
</template>

<script>
import PublicNavbar from "../../components/PublicNavbar.vue";
import { api } from "../../services/api.js";
import { normalizeMediaUrl } from "../../utils/mediaUrl.js";

const fallbackImage = "/images/home/badminton-cover.webp";

export default {
  name: "NewsDetail",
  components: { PublicNavbar },
  data() {
    return {
      post: null,
      loading: true,
      error: "",
    };
  },
  watch: {
    "$route.params.slug": {
      immediate: true,
      handler() {
        this.loadPost();
      },
    },
  },
  methods: {
    async loadPost() {
      this.loading = true;
      this.error = "";

      try {
        const response = await api(`/api/venue-posts/${this.$route.params.slug}`);
        this.post = response.data;
      } catch (error) {
        this.post = null;
        this.error = error.message || "Không thể tải bài viết.";
      } finally {
        this.loading = false;
      }
    },
    postImage(post) {
      const media = Array.isArray(post.media) ? post.media.find((item) => item.collection === "thumbnail") || post.media[0] : null;
      return normalizeMediaUrl(media) || normalizeMediaUrl({ file_path: post.thumbnail || post.image_path || post.cover_image }) || fallbackImage;
    },
    categoryLabel(type) {
      const labels = {
        news: "Tin tức",
        promotion: "Ưu đãi",
        tournament: "Giải đấu",
        notice: "Thông báo",
        recruitment: "Tuyển dụng",
      };

      return labels[type] || "Tin tức";
    },
    formatDate(value) {
      if (!value) return "";

      return new Intl.DateTimeFormat("vi-VN", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
      }).format(new Date(value));
    },
  },
};
</script>

<style scoped>
.news-detail-page {
  min-height: 100vh;
  background: #f6f8f7;
  color: #102015;
}

.news-detail-shell {
  max-width: 980px;
  margin: 0 auto;
  padding: 104px 24px 72px;
}

.news-state {
  display: grid;
  min-height: 320px;
  place-items: center;
  border: 1px solid #dfe8e2;
  border-radius: 12px;
  background: #fff;
  color: #425247;
  font-weight: 800;
}

.news-state--error {
  gap: 14px;
}

.news-state a {
  color: #0d8c51;
  font-weight: 900;
  text-decoration: none;
}

.article-layout {
  display: grid;
  gap: 24px;
}

.article-breadcrumbs {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #667568;
  font-size: 14px;
  font-weight: 800;
}

.article-breadcrumbs a {
  color: inherit;
  text-decoration: none;
}

.article-header {
  padding-bottom: 6px;
}

.article-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 18px;
  color: #526156;
  font-size: 13px;
  font-weight: 850;
}

.article-meta span {
  padding-right: 10px;
  border-right: 1px solid #cfdad3;
}

.article-meta span:last-child {
  border-right: 0;
}

.article-header h1 {
  max-width: 820px;
  margin: 0;
  color: #08150e;
  font-size: clamp(34px, 5vw, 58px);
  line-height: 1.05;
  font-weight: 950;
  letter-spacing: 0;
}

.article-header p {
  max-width: 760px;
  margin: 18px 0 0;
  color: #425247;
  font-size: 18px;
  line-height: 1.7;
  font-weight: 700;
}

.article-cover {
  margin: 6px 0 0;
  overflow: hidden;
  border: 1px solid #dfe8e2;
  border-radius: 12px;
  background: #fff;
}

.article-cover img {
  display: block;
  width: 100%;
  aspect-ratio: 16 / 9;
  object-fit: cover;
}

.article-body {
  padding: 30px 36px;
  border: 1px solid #dfe8e2;
  border-radius: 12px;
  background: #fff;
  color: #243229;
  font-size: 17px;
  line-height: 1.8;
}

.article-body :deep(p) {
  margin: 0 0 18px;
}

.article-body :deep(h2),
.article-body :deep(h3) {
  margin: 30px 0 12px;
  color: #102015;
  line-height: 1.25;
}

.article-body :deep(ul),
.article-body :deep(ol) {
  margin: 0 0 20px 22px;
  padding: 0;
}

.article-body :deep(strong) {
  color: #0b7a46;
}

.article-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 24px;
  border: 1px solid #dfe8e2;
  border-radius: 12px;
  background: #fff;
}

.article-footer div {
  display: grid;
  gap: 4px;
}

.article-footer strong {
  color: #102015;
  font-size: 16px;
  font-weight: 950;
}

.article-footer span {
  color: #667568;
  font-size: 14px;
  font-weight: 750;
}

.article-footer a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  padding: 0 16px;
  border: 1px solid #0d8c51;
  border-radius: 8px;
  color: #0d8c51;
  font-weight: 900;
  text-decoration: none;
}

@media (max-width: 700px) {
  .news-detail-shell {
    padding: 92px 18px 48px;
  }

  .article-header h1 {
    font-size: 34px;
  }

  .article-header p {
    font-size: 16px;
  }

  .article-body {
    padding: 22px;
    font-size: 16px;
  }

  .article-footer {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>

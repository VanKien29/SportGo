<template>
  <section class="venue-posts-tab" aria-labelledby="venue-posts-heading">
    <header class="venue-posts-heading">
      <div>
        <p class="sg-client-eyebrow">Bảng tin cụm sân</p>
        <h2 id="venue-posts-heading">Bài đăng từ {{ venueName }}</h2>
        <p>Cập nhật lịch hoạt động, ưu đãi và thông tin mới nhất từ sân.</p>
      </div>
      <button type="button" class="sg-client-button" :disabled="loading" @click="fetchPosts({ page: 1 })">
        <AppIcon name="refresh" :size="16" />
        Làm mới
      </button>
    </header>

    <div v-if="loading" class="venue-post-state" aria-live="polite">
      <span class="venue-post-spinner" aria-hidden="true"></span>
      <span>Đang tải bài đăng của sân...</span>
    </div>

    <div v-else-if="error" class="venue-post-state venue-post-state--error" role="alert">
      <AppIcon name="alert" :size="27" />
      <strong>Không thể tải bài đăng</strong>
      <p>{{ error }}</p>
      <button type="button" class="sg-client-button" @click="fetchPosts({ page: 1 })">Thử lại</button>
    </div>

    <div v-else-if="!posts.length" class="venue-post-state">
      <AppIcon name="newspaper" :size="30" />
      <strong>Sân chưa có bài đăng công khai</strong>
      <p>Các cập nhật về lịch hoạt động và ưu đãi sẽ xuất hiện tại đây.</p>
    </div>

    <div v-else class="venue-post-stream">
      <article v-for="post in posts" :key="post.id" class="venue-post-card sg-client-card">
        <header class="venue-post-card-header">
          <div class="venue-post-author">
            <span class="venue-post-avatar">
              <img
                v-if="post.author?.avatar_url"
                :src="assetUrl(post.author.avatar_url)"
                :alt="post.author.full_name || post.author.username"
              />
              <span v-else>{{ initial(post.author?.full_name || post.author?.username || venueName) }}</span>
            </span>
            <div>
              <strong class="client-author-line">
                {{ post.author?.full_name || post.author?.username || venueName }}
                <ClientAuthorBadges :badges="post.author_badges" />
              </strong>
              <small>{{ timeAgo(post.published_at || post.created_at) }} · {{ venueName }}</small>
            </div>
          </div>

          <div class="venue-post-menu-wrap">
            <button
              type="button"
              class="sg-client-icon-button"
              :aria-expanded="openMenuPostId === post.id"
              aria-label="Tùy chọn bài đăng"
              @click.stop="toggleMenu(post.id)"
            >
              <AppIcon name="moreHorizontal" :size="20" />
            </button>
            <div v-if="openMenuPostId === post.id" class="venue-post-menu" role="menu" @click.stop>
              <button type="button" role="menuitem" @click="openReport(post)">
                <AppIcon name="alert" :size="17" />
                Báo cáo bài đăng
              </button>
            </div>
          </div>
        </header>

        <div class="venue-post-copy">
          <div v-if="post.hashtags?.length" class="venue-post-tags">
            <span v-for="tag in post.hashtags.slice(0, 3)" :key="tag.id || tag.name">#{{ tag.name }}</span>
          </div>
          <h3 v-if="post.title && !titleRepeatsContent(post)">{{ post.title }}</h3>
          <p>{{ plainText(post.content || post.short_description) }}</p>
        </div>

        <div v-if="postMedia(post).length" class="venue-post-media" :class="`venue-post-media--${Math.min(postMedia(post).length, 4)}`">
          <button
            v-for="(image, imageIndex) in postMedia(post).slice(0, 4)"
            :key="`${post.id}-${imageIndex}`"
            type="button"
            @click="openLightbox(postMedia(post), imageIndex)"
          >
            <img :src="image" :alt="`${post.title || 'Ảnh bài đăng'} ${imageIndex + 1}`" @error="handleImageError" />
            <span v-if="imageIndex === 3 && postMedia(post).length > 4">+{{ postMedia(post).length - 4 }}</span>
          </button>
        </div>

        <div class="venue-post-stats">
          <span><AppIcon name="heart" :size="15" /> {{ post.like_count || 0 }}</span>
          <button type="button" @click="toggleComments(post)">{{ post.comment_count || 0 }} bình luận</button>
          <span>{{ post.view_count || 0 }} lượt xem</span>
        </div>

        <div class="venue-post-actions">
          <button
            type="button"
            :class="{ active: Boolean(post.is_liked) }"
            :disabled="likingPostId === post.id"
            @click="toggleLike(post)"
          >
            <AppIcon name="heart" :size="18" />
            {{ post.is_liked ? 'Đã thích' : 'Thích' }}
          </button>
          <button type="button" :class="{ active: commentsOpen[post.id] }" @click="toggleComments(post)">
            <AppIcon name="messageCircle" :size="18" />
            Bình luận
          </button>
        </div>

        <section v-if="commentsOpen[post.id]" class="venue-comments" aria-label="Bình luận bài đăng">
          <div v-if="detailsLoading[post.id]" class="venue-comments-loading">
            <span class="venue-post-spinner venue-post-spinner--small" aria-hidden="true"></span>
            Đang tải bình luận...
          </div>
          <template v-else>
            <div v-if="post.top_level_comments?.length" class="venue-comment-list">
              <article v-for="comment in visibleComments(post)" :key="comment.id">
                <span class="venue-comment-avatar">{{ initial(comment.user?.full_name || comment.user?.username) }}</span>
                <div>
                  <div class="venue-comment-bubble">
                    <strong class="client-author-line">
                      {{ comment.user?.full_name || comment.user?.username || 'Thành viên SportGo' }}
                      <ClientAuthorBadges :badges="comment.user?.author_badges" />
                    </strong>
                    <p>{{ comment.content }}</p>
                  </div>
                  <small>{{ timeAgo(comment.created_at) }}</small>
                </div>
              </article>
              <button
                v-if="post.top_level_comments.length > commentPreviewLimit && !showAllComments[post.id]"
                type="button"
                class="venue-comments-more"
                @click="showAllComments[post.id] = true"
              >
                Xem thêm {{ post.top_level_comments.length - commentPreviewLimit }} bình luận
              </button>
            </div>
            <p v-else class="venue-comments-empty">Chưa có bình luận. Hãy bắt đầu cuộc trò chuyện.</p>

            <form v-if="user" class="venue-comment-form" @submit.prevent="submitComment(post)">
              <span class="venue-comment-avatar">{{ initial(user.fullName) }}</span>
              <label>
                <span class="sg-client-sr-only">Viết bình luận</span>
                <input
                  v-model.trim="commentDrafts[post.id]"
                  type="text"
                  maxlength="1000"
                  placeholder="Viết bình luận..."
                  :disabled="commentSubmitting[post.id]"
                />
              </label>
              <button
                type="submit"
                aria-label="Gửi bình luận"
                :disabled="commentSubmitting[post.id] || !commentDrafts[post.id]?.trim()"
              >
                <AppIcon name="send" :size="17" />
              </button>
            </form>
            <button v-else type="button" class="venue-comment-login" @click="goToLogin">Đăng nhập để bình luận</button>
          </template>
        </section>
      </article>

      <button
        v-if="!loadMoreError && pagination.current_page < pagination.last_page"
        type="button"
        class="venue-post-load-more sg-client-button"
        :disabled="loadingMore"
        @click="loadMore"
      >
        {{ loadingMore ? 'Đang tải thêm...' : 'Xem thêm bài đăng' }}
      </button>
      <div v-if="loadMoreError" class="venue-post-load-more-error" role="alert">
        <span>{{ loadMoreError }}</span>
        <button type="button" @click="loadMore">Thử lại</button>
      </div>
    </div>

    <ReportModal
      :is-open="Boolean(reportTarget)"
      target-type="venue_post"
      :target-id="reportTarget?.entity_id || reportTarget?.id || ''"
      :target-name="reportTarget?.title || `Bài đăng của ${venueName}`"
      @close="reportTarget = null"
      @success="handleReportSuccess"
    />

    <Teleport to="body">
      <div v-if="lightboxImages.length" class="venue-lightbox" role="presentation" @click.self="closeLightbox">
        <section role="dialog" aria-modal="true" aria-label="Xem ảnh bài đăng">
          <button type="button" class="venue-lightbox-close" aria-label="Đóng ảnh" @click="closeLightbox">
            <AppIcon name="close" :size="22" />
          </button>
          <button
            v-if="lightboxImages.length > 1"
            type="button"
            class="venue-lightbox-nav venue-lightbox-nav--prev"
            aria-label="Ảnh trước"
            @click="moveLightbox(-1)"
          >
            <AppIcon name="chevronLeft" :size="24" />
          </button>
          <img :src="lightboxImages[lightboxIndex]" :alt="`Ảnh bài đăng ${lightboxIndex + 1}`" />
          <button
            v-if="lightboxImages.length > 1"
            type="button"
            class="venue-lightbox-nav venue-lightbox-nav--next"
            aria-label="Ảnh sau"
            @click="moveLightbox(1)"
          >
            <AppIcon name="chevronRight" :size="24" />
          </button>
          <span v-if="lightboxImages.length > 1" class="venue-lightbox-count">{{ lightboxIndex + 1 }} / {{ lightboxImages.length }}</span>
        </section>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from './AppIcon.vue';
import ClientAuthorBadges from './ClientAuthorBadges.vue';
import ReportModal from './ReportModal.vue';
import { api } from '../services/api.js';
import { getAuth } from '../stores/auth.js';

const props = defineProps({
  venueId: { type: [String, Number], required: true },
  venueName: { type: String, default: 'cụm sân' },
});

const route = useRoute();
const router = useRouter();
const toast = useToast();
const user = getAuth();
const posts = ref([]);
const loading = ref(true);
const loadingMore = ref(false);
const error = ref('');
const loadMoreError = ref('');
const requestSequence = ref(0);
const pagination = ref({ current_page: 1, last_page: 1 });
const openMenuPostId = ref(null);
const reportTarget = ref(null);
const likingPostId = ref(null);
const commentsOpen = reactive({});
const detailsLoading = reactive({});
const commentDrafts = reactive({});
const commentSubmitting = reactive({});
const showAllComments = reactive({});
const commentPreviewLimit = 3;
const fallbackImage = '/images/home/badminton-cover.webp';
const lightboxImages = ref([]);
const lightboxIndex = ref(0);
const failedImageUrls = reactive(new Set());

async function fetchPosts({ page = 1, append = false } = {}) {
  if (!props.venueId) return;
  const requestId = ++requestSequence.value;
  if (append) loadingMore.value = true;
  else loading.value = true;
  if (append) loadMoreError.value = '';
  else error.value = '';

  try {
    const params = new URLSearchParams({
      venue_cluster_id: String(props.venueId),
      page: String(page),
      per_page: '10',
      feed_type: 'venue_post',
    });
    const response = await api(`/api/venue-posts?${params.toString()}`);
    if (requestId !== requestSequence.value) return;
    const incoming = Array.isArray(response.data) ? response.data : [];
    posts.value = append ? [...posts.value, ...incoming] : incoming;
    pagination.value = {
      current_page: Number(response.current_page || page),
      last_page: Number(response.last_page || 1),
    };
  } catch (requestError) {
    if (requestId !== requestSequence.value) return;
    const message = requestError.message || 'Không thể tải bài đăng của sân.';
    if (append) loadMoreError.value = message;
    else {
      posts.value = [];
      error.value = message;
    }
  } finally {
    if (requestId === requestSequence.value) {
      loading.value = false;
      loadingMore.value = false;
    }
  }
}

function loadMore() {
  if (loadingMore.value || pagination.value.current_page >= pagination.value.last_page) return;
  fetchPosts({ page: pagination.value.current_page + 1, append: true });
}

async function ensurePostDetails(post) {
  if (Array.isArray(post.top_level_comments)) return;
  detailsLoading[post.id] = true;
  try {
    const response = await api(`/api/venue-posts/${post.slug || post.id}`);
    const detail = response.data || {};
    post.top_level_comments = Array.isArray(detail.top_level_comments) ? detail.top_level_comments : [];
    post.comment_count = Number(detail.comment_count || post.comment_count || 0);
    post.like_count = Number(detail.like_count || post.like_count || 0);
    post.is_liked = Boolean(detail.is_liked);
    post.likes_available = detail.likes_available !== false;
  } catch (requestError) {
    commentsOpen[post.id] = false;
    toast.error(requestError.message || 'Không thể tải bình luận của bài đăng.');
  } finally {
    detailsLoading[post.id] = false;
  }
}

async function toggleComments(post) {
  commentsOpen[post.id] = !commentsOpen[post.id];
  if (commentsOpen[post.id]) await ensurePostDetails(post);
}

async function toggleLike(post) {
  if (!user) {
    toast.info('Vui lòng đăng nhập để thích bài đăng.');
    goToLogin();
    return;
  }
  if (post.likes_available === false) {
    toast.info('Tính năng thích đang chờ hệ thống cập nhật dữ liệu.');
    return;
  }
  if (likingPostId.value === post.id) return;
  likingPostId.value = post.id;
  try {
    const response = await api(`/api/venue-posts/${post.entity_id || post.id}/likes`, { method: 'POST' });
    post.is_liked = Boolean(response.data?.is_liked);
    post.like_count = Number(response.data?.like_count ?? post.like_count ?? 0);
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể cập nhật lượt thích.');
  } finally {
    likingPostId.value = null;
  }
}

async function submitComment(post) {
  if (!user) return goToLogin();
  const content = commentDrafts[post.id]?.trim();
  if (!content || commentSubmitting[post.id]) return;
  commentSubmitting[post.id] = true;
  try {
    const response = await api(`/api/venue-posts/${post.entity_id || post.id}/comments`, {
      method: 'POST',
      body: JSON.stringify({ content }),
    });
    if (!Array.isArray(post.top_level_comments)) post.top_level_comments = [];
    post.top_level_comments.unshift({
      id: response.data?.id || `new-${Date.now()}`,
      content,
      created_at: new Date().toISOString(),
      user: response.data?.user || {
        id: user.id,
        full_name: user.fullName,
        username: user.username,
      },
    });
    post.comment_count = Number(post.comment_count || 0) + 1;
    commentDrafts[post.id] = '';
    toast.success('Đã đăng bình luận.');
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể gửi bình luận.');
  } finally {
    commentSubmitting[post.id] = false;
  }
}

function openReport(post) {
  openMenuPostId.value = null;
  if (!user) {
    toast.info('Vui lòng đăng nhập để gửi báo cáo.');
    goToLogin();
    return;
  }
  reportTarget.value = post;
}

function handleReportSuccess() {
  reportTarget.value = null;
  toast.success('Báo cáo đã được gửi để SportGo kiểm tra.');
}

function toggleMenu(postId) {
  openMenuPostId.value = openMenuPostId.value === postId ? null : postId;
}

function closeMenu() {
  openMenuPostId.value = null;
}

function visibleComments(post) {
  const comments = Array.isArray(post.top_level_comments) ? post.top_level_comments : [];
  return showAllComments[post.id] ? comments : comments.slice(0, commentPreviewLimit);
}

function plainText(value) {
  if (!value) return 'Bài đăng từ cụm sân SportGo.';
  const parsed = new DOMParser().parseFromString(String(value), 'text/html');
  return (parsed.body.textContent || '').trim();
}

function titleRepeatsContent(post) {
  const title = plainText(post.title).replace(/\.{3}$/, '').trim().toLocaleLowerCase('vi-VN');
  const content = plainText(post.content).trim().toLocaleLowerCase('vi-VN');
  return Boolean(title && content.startsWith(title));
}

function postMedia(post) {
  const media = Array.isArray(post?.media) ? post.media : [];
  const sorted = [...media].sort((left, right) => {
    if (left.collection === 'thumbnail' && right.collection !== 'thumbnail') return -1;
    if (right.collection === 'thumbnail' && left.collection !== 'thumbnail') return 1;
    return Number(left.sort_order || 0) - Number(right.sort_order || 0);
  });
  return [...new Set(sorted
    .map((item) => assetUrl(item.url || item.file_url || item.full_url || item.file_path || item.path))
    .filter(Boolean)
    .map((url) => failedImageUrls.has(url) ? fallbackImage : url))];
}

function assetUrl(path) {
  if (!path || /^https?:\/\//.test(path) || path.startsWith('/')) return path || '';
  return `/storage/${path}`;
}

function handleImageError(event) {
  const image = event.currentTarget;
  const failedUrl = image?.getAttribute('src');
  if (!failedUrl || failedUrl === fallbackImage) return;
  failedImageUrls.add(failedUrl);
  lightboxImages.value = lightboxImages.value.map((url) => url === failedUrl ? fallbackImage : url);
  image.src = fallbackImage;
}

function openLightbox(images, index) {
  lightboxImages.value = images;
  lightboxIndex.value = index;
}

function closeLightbox() {
  lightboxImages.value = [];
  lightboxIndex.value = 0;
}

function moveLightbox(direction) {
  lightboxIndex.value = (lightboxIndex.value + direction + lightboxImages.value.length) % lightboxImages.value.length;
}

function initial(name) {
  return String(name || 'S').trim().charAt(0).toUpperCase();
}

function formatDate(value) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
}

function timeAgo(value) {
  if (!value) return '';
  const timestamp = new Date(value).getTime();
  if (Number.isNaN(timestamp)) return '';
  const seconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
  if (seconds < 60) return 'Vừa xong';
  if (seconds < 3600) return `${Math.floor(seconds / 60)} phút trước`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} giờ trước`;
  if (seconds < 604800) return `${Math.floor(seconds / 86400)} ngày trước`;
  return formatDate(value);
}

function goToLogin() {
  router.push({ name: 'login', query: { redirect: route.fullPath } });
}

function handleKeydown(event) {
  if (event.key === 'Escape' && lightboxImages.value.length) closeLightbox();
  if (event.key === 'ArrowLeft' && lightboxImages.value.length > 1) moveLightbox(-1);
  if (event.key === 'ArrowRight' && lightboxImages.value.length > 1) moveLightbox(1);
}

watch(() => props.venueId, () => fetchPosts({ page: 1 }));

onMounted(() => {
  fetchPosts({ page: 1 });
  document.addEventListener('click', closeMenu);
  document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', closeMenu);
  document.removeEventListener('keydown', handleKeydown);
});
</script>


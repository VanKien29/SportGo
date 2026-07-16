<template>
  <div class="community-page">
    <PublicNavbar />

    <main class="community-container">
      <header class="community-heading">
        <div>
          <span class="eyebrow">Cộng đồng SportGo</span>
          <h1>Cùng chơi, cùng chia sẻ</h1>
          <p>Theo dõi câu chuyện thể thao, trao đổi kinh nghiệm và tìm đồng đội cho trận đấu tiếp theo.</p>
        </div>
        <button v-if="!user" type="button" class="heading-login" @click="goToLogin">
          Đăng nhập để tham gia
          <AppIcon name="chevronRight" />
        </button>
      </header>

      <div class="community-layout">
        <section class="feed-column" aria-label="Bảng tin cộng đồng">
          <article v-if="canCreateCommunityPost" class="composer-card">
            <div class="composer-start">
              <span class="avatar avatar-current">{{ initial(user?.fullName) }}</span>
              <button type="button" class="composer-prompt" @click="showCommunityModal = true">
                Bạn muốn chia sẻ điều gì với cộng đồng?
              </button>
            </div>
            <div class="composer-actions">
              <button type="button" @click="showCommunityModal = true">
                <AppIcon name="edit" />
                Bài chia sẻ
              </button>
              <button v-if="isPlayer" type="button" @click="showMeetupModal = true">
                <AppIcon name="users" />
                Tìm người chơi cùng
              </button>
            </div>
          </article>

          <section class="feed-toolbar" aria-label="Lọc bảng tin">
            <div class="toolbar-title">
              <div>
                <span class="eyebrow">Dành cho bạn</span>
                <h2>Bảng tin mới nhất</h2>
              </div>
              <button type="button" class="mobile-filter-toggle" @click="showMobileFilters = !showMobileFilters">
                <AppIcon name="filter" />
                Lọc bài
              </button>
            </div>
            <div v-if="showMobileFilters" class="mobile-filters">
              <form class="search-form" @submit.prevent="applyFilters">
                <AppIcon name="search" />
                <input v-model.trim="searchQuery" type="search" placeholder="Tìm trong cộng đồng" aria-label="Tìm trong cộng đồng" />
                <button type="submit">Tìm</button>
              </form>
              <div class="category-list" aria-label="Chủ đề bài viết">
                <button type="button" :class="{ active: !selectedCategory }" @click="setCategory('')">Tất cả</button>
                <button
                  v-for="category in categories"
                  :key="category"
                  type="button"
                  :class="{ active: selectedCategory === category }"
                  @click="setCategory(category)"
                >
                  {{ category }}
                </button>
              </div>
            </div>
          </section>

          <div v-if="loading" class="feed-state" aria-live="polite">
            <span class="spinner" aria-hidden="true"></span>
            <p>Đang tải bảng tin...</p>
          </div>
          <div v-else-if="error" class="feed-state state-error" role="alert">
            <AppIcon name="alert" />
            <strong>Không thể tải bảng tin</strong>
            <p>{{ error }}</p>
            <button type="button" @click="fetchPosts({ page: 1 })">Thử lại</button>
          </div>
          <div v-else-if="!posts.length" class="feed-state">
            <AppIcon name="newspaper" />
            <strong>Chưa có bài viết phù hợp</strong>
            <p>Hãy đổi chủ đề hoặc từ khóa để xem thêm nội dung.</p>
          </div>

          <div v-else class="post-stream">
            <article v-for="post in posts" :key="post.id" class="post-card">
              <header class="post-header">
                <button type="button" class="author-button" @click="goToUser(post.author?.id)">
                  <span class="avatar">
                    <img
                      v-if="post.author?.avatar_url"
                      :src="assetUrl(post.author.avatar_url)"
                      :alt="post.author.full_name || post.author.username"
                    />
                    <span v-else>{{ initial(post.author?.full_name || post.author?.username) }}</span>
                  </span>
                  <span class="author-copy">
                    <strong class="client-author-line">
                      {{ post.author?.full_name || post.author?.username || 'Thành viên SportGo' }}
                      <ClientAuthorBadges :badges="post.author_badges" />
                    </strong>
                    <small>
                      {{ timeAgo(post.published_at || post.created_at) }}
                      <template v-if="post.venue_cluster?.name"> · {{ post.venue_cluster.name }}</template>
                    </small>
                  </span>
                </button>

                <div class="post-menu-wrap">
                  <button
                    type="button"
                    class="icon-button"
                    :aria-expanded="openMenuPostId === post.id"
                    aria-label="Tùy chọn bài viết"
                    @click.stop="togglePostMenu(post.id)"
                  >
                    <AppIcon name="moreHorizontal" />
                  </button>
                  <div v-if="openMenuPostId === post.id" class="post-menu" role="menu" @click.stop>
                    <button type="button" role="menuitem" @click="openReport(post)">
                      <AppIcon name="alert" />
                      Báo cáo bài viết
                    </button>
                  </div>
                </div>
              </header>

              <div class="post-body">
                <div v-if="post.hashtags?.length" class="post-tags">
                  <span v-for="tag in post.hashtags.slice(0, 3)" :key="tag.id || tag.name">#{{ tag.name }}</span>
                </div>
                <button type="button" class="post-copy" @click="goToDetail(post.slug || post.id)">
                  <strong v-if="post.title && !titleRepeatsContent(post)">{{ post.title }}</strong>
                  <span>{{ plainText(post.content || post.short_description) }}</span>
                </button>
              </div>

              <button
                v-if="postMedia(post).length === 1"
                type="button"
                class="post-media media-single"
                @click="goToDetail(post.slug || post.id)"
              >
                <img :src="postMedia(post)[0]" :alt="post.title || 'Ảnh bài viết'" @error="handlePostImageError" />
              </button>
              <button
                v-else-if="postMedia(post).length > 1"
                type="button"
                class="post-media media-grid"
                :class="`media-count-${Math.min(postMedia(post).length, 4)}`"
                @click="goToDetail(post.slug || post.id)"
              >
                <span v-for="(image, imageIndex) in postMedia(post).slice(0, 4)" :key="`${post.id}-${imageIndex}`">
                  <img :src="image" :alt="`${post.title || 'Ảnh bài viết'} ${imageIndex + 1}`" @error="handlePostImageError" />
                  <b v-if="imageIndex === 3 && postMedia(post).length > 4">+{{ postMedia(post).length - 4 }}</b>
                </span>
              </button>

              <div class="post-stats">
                <span><AppIcon name="heart" /> {{ post.like_count || 0 }}</span>
                <button type="button" @click="toggleComments(post)">{{ post.comment_count || 0 }} bình luận</button>
                <span>{{ post.view_count || 0 }} lượt xem</span>
              </div>

              <div class="post-actions">
                <button
                  type="button"
                  :class="{ active: Boolean(post.is_liked) }"
                  :disabled="likingPostIds.has(post.id) || !post.likes_available"
                  :title="post.likes_available ? '' : 'Lượt thích của bài cụm sân đang chờ cập nhật dữ liệu hệ thống'"
                  @click="toggleLike(post)"
                >
                  <AppIcon name="heart" />
                  {{ post.is_liked ? 'Đã thích' : 'Thích' }}
                </button>
                <button type="button" :class="{ active: commentsOpen[post.id] }" @click="toggleComments(post)">
                  <AppIcon name="messageCircle" />
                  Bình luận
                </button>
                <button type="button" @click="sharePost(post)">
                  <AppIcon name="share" />
                  Chia sẻ
                </button>
              </div>

              <section v-if="commentsOpen[post.id]" class="comments-panel" aria-label="Bình luận bài viết">
                <div v-if="detailsLoading[post.id]" class="comments-loading">
                  <span class="spinner spinner-small" aria-hidden="true"></span>
                  Đang tải bình luận...
                </div>
                <template v-else>
                  <div v-if="post.top_level_comments?.length" class="comment-list">
                    <article v-for="comment in visibleComments(post)" :key="comment.id" class="comment-item">
                      <span class="avatar avatar-comment">
                        <img
                          v-if="comment.user?.avatar_url"
                          :src="assetUrl(comment.user.avatar_url)"
                          :alt="comment.user.full_name || comment.user.username"
                        />
                        <span v-else>{{ initial(comment.user?.full_name || comment.user?.username) }}</span>
                      </span>
                      <div>
                        <div class="comment-bubble">
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
                      class="show-comments-button"
                      @click="showAllComments[post.id] = true"
                    >
                      Xem thêm {{ post.top_level_comments.length - commentPreviewLimit }} bình luận
                    </button>
                  </div>
                  <p v-else class="no-comments">Chưa có bình luận. Hãy bắt đầu cuộc trò chuyện.</p>

                  <form v-if="user" class="comment-form" @submit.prevent="submitComment(post)">
                    <span class="avatar avatar-comment">{{ initial(user.fullName) }}</span>
                    <label>
                      <span class="sr-only">Viết bình luận</span>
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
                      class="send-comment"
                      aria-label="Gửi bình luận"
                      :disabled="commentSubmitting[post.id] || !commentDrafts[post.id]?.trim()"
                    >
                      <AppIcon name="send" />
                    </button>
                  </form>
                  <button v-else type="button" class="login-to-comment" @click="goToLogin">
                    Đăng nhập để bình luận
                  </button>
                </template>
              </section>
            </article>

            <button
              v-if="pagination.current_page < pagination.last_page"
              type="button"
              class="load-more-button"
              :disabled="loadingMore"
              @click="loadMorePosts"
            >
              <span v-if="loadingMore" class="spinner spinner-small" aria-hidden="true"></span>
              {{ loadingMore ? 'Đang tải thêm...' : 'Xem thêm bài viết' }}
            </button>
            <p v-else class="end-of-feed">Bạn đã xem hết các bài viết hiện có.</p>
          </div>
        </section>

        <aside class="community-sidebar" aria-label="Khám phá cộng đồng">
          <section class="sidebar-card desktop-filters">
            <h2>Khám phá</h2>
            <form class="search-form" @submit.prevent="applyFilters">
              <AppIcon name="search" />
              <input v-model.trim="searchQuery" type="search" placeholder="Tìm trong cộng đồng" aria-label="Tìm trong cộng đồng" />
              <button type="submit">Tìm</button>
            </form>
            <div class="category-list" aria-label="Chủ đề bài viết">
              <button type="button" :class="{ active: !selectedCategory }" @click="setCategory('')">Tất cả</button>
              <button
                v-for="category in categories"
                :key="category"
                type="button"
                :class="{ active: selectedCategory === category }"
                @click="setCategory(category)"
              >
                {{ category }}
              </button>
            </div>
          </section>

          <section class="sidebar-card meetup-sidebar">
            <header class="sidebar-heading">
              <div>
                <span class="eyebrow">Ghép kèo</span>
                <h2>Kèo sắp tới</h2>
              </div>
              <button v-if="isPlayer" type="button" class="icon-button" aria-label="Tạo bài giao lưu" @click="showMeetupModal = true">
                <AppIcon name="plus" />
              </button>
            </header>

            <div v-if="matchmakingLoading" class="meetup-loading">
              <span class="spinner spinner-small" aria-hidden="true"></span>
              Đang tải kèo...
            </div>
            <div v-else-if="matchmakingPosts.length" class="meetup-list">
              <article v-for="post in matchmakingPosts" :key="post.id" class="meetup-card">
                <header>
                  <button type="button" class="meetup-author" @click="goToUser(post.author?.id)">
                    <span class="avatar avatar-comment">
                      <img v-if="post.author?.avatar" :src="assetUrl(post.author.avatar)" :alt="post.author.name" />
                      <span v-else>{{ initial(post.author?.name) }}</span>
                    </span>
                    <span>
                      <strong class="client-author-line">
                        {{ post.author?.name || 'Người chơi SportGo' }}
                        <ClientAuthorBadges :badges="post.author?.author_badges" />
                      </strong>
                      <small>{{ timeAgo(post.created_at) }}</small>
                    </span>
                  </button>
                  <span class="needed-badge">Cần {{ post.needed_players }} người</span>
                </header>
                <div class="meetup-facts">
                  <span><AppIcon name="mapPin" /> {{ post.booking?.venue_name || 'Cụm sân' }}</span>
                  <span><AppIcon name="clock" /> {{ formatDate(post.booking?.date) }} · {{ post.booking?.time }}</span>
                </div>
                <p v-if="post.description">{{ post.description }}</p>
                <button
                  v-if="!isOwnPost(post)"
                  type="button"
                  class="meetup-action"
                  :disabled="joiningPostId === post.id || Boolean(post.user_status)"
                  @click="joinMatchmaking(post)"
                >
                  {{ joinLabel(post) }}
                </button>
                <router-link v-else class="meetup-action" :to="`/matchmaking-posts/${post.id}/manage`">Quản lý yêu cầu</router-link>
              </article>
            </div>
            <div v-else class="meetup-empty">
              <AppIcon name="users" />
              <p>Chưa có kèo công khai sắp tới.</p>
              <button v-if="isPlayer" type="button" @click="showMeetupModal = true">Tạo kèo đầu tiên</button>
            </div>
          </section>
        </aside>
      </div>
    </main>

    <CommunityPostModal
      :is-open="showCommunityModal"
      @close="showCommunityModal = false"
      @success="handleCommunityPostCreated"
    />
    <MeetupPostModal
      :is-open="showMeetupModal"
      @close="showMeetupModal = false"
      @success="handleMeetupPostCreated"
    />
    <ReportModal
      :is-open="Boolean(reportTarget)"
      :target-type="reportTarget?.feed_type === 'community_post' ? 'community_post' : 'venue_post'"
      :target-id="reportTarget?.entity_id || reportTarget?.id || ''"
      :target-name="reportTarget?.title || 'Bài viết cộng đồng'"
      @close="reportTarget = null"
      @success="handleReportSuccess"
    />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from '@/components/AppIcon.vue';
import ClientAuthorBadges from '@/components/ClientAuthorBadges.vue';
import CommunityPostModal from '@/components/CommunityPostModal.vue';
import MeetupPostModal from '@/components/MeetupPostModal.vue';
import PublicNavbar from '@/components/PublicNavbar.vue';
import ReportModal from '@/components/ReportModal.vue';
import { api } from '@/services/api.js';
import { getAuth } from '@/stores/auth.js';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const user = getAuth();
const isPlayer = computed(() => user?.role_group === 'user');
const canCreateCommunityPost = computed(() => Boolean(user && ['user', 'owner'].includes(user.role_group)));
const posts = ref([]);
const loading = ref(true);
const loadingMore = ref(false);
const error = ref('');
const searchQuery = ref('');
const selectedCategory = ref('');
const showMobileFilters = ref(false);
const showCommunityModal = ref(false);
const showMeetupModal = ref(false);
const openMenuPostId = ref(null);
const reportTarget = ref(null);
const pagination = ref({ current_page: 1, last_page: 1 });
const categories = ['Kinh nghiệm', 'Giao lưu', 'Hỏi đáp', 'Sự kiện', 'Cụm sân mới', 'Ưu đãi'];
const fallbackPostImage = '/images/home/badminton-cover.webp';
const commentPreviewLimit = 3;
const commentsOpen = reactive({});
const detailsLoading = reactive({});
const commentDrafts = reactive({});
const commentSubmitting = reactive({});
const showAllComments = reactive({});
const likingPostIds = reactive(new Set());
const matchmakingPosts = ref([]);
const matchmakingLoading = ref(true);
const joiningPostId = ref(null);

async function fetchPosts({ page = 1, append = false } = {}) {
  if (append) loadingMore.value = true;
  else loading.value = true;
  error.value = '';

  try {
    const params = new URLSearchParams({ page: String(page), per_page: '10', feed_type: 'community_post' });
    if (searchQuery.value) params.set('keyword', searchQuery.value);
    if (selectedCategory.value) params.set('category', selectedCategory.value);
    const response = await api(`/api/venue-posts?${params.toString()}`);
    const incoming = Array.isArray(response.data) ? response.data : [];
    posts.value = append ? [...posts.value, ...incoming] : incoming;
    pagination.value = {
      current_page: Number(response.current_page || page),
      last_page: Number(response.last_page || 1),
    };
  } catch (requestError) {
    if (!append) posts.value = [];
    error.value = requestError.message || 'Không thể tải bài viết cộng đồng.';
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

async function fetchMatchmakingPosts() {
  matchmakingLoading.value = true;
  try {
    const response = await api('/api/matchmaking-posts');
    matchmakingPosts.value = Array.isArray(response.data) ? response.data.slice(0, 5) : [];
  } catch {
    matchmakingPosts.value = [];
  } finally {
    matchmakingLoading.value = false;
  }
}

function applyFilters() {
  showMobileFilters.value = false;
  fetchPosts({ page: 1 });
}

function setCategory(category) {
  selectedCategory.value = category;
  applyFilters();
}

function loadMorePosts() {
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
    toast.error(requestError.message || 'Không thể tải bình luận của bài viết.');
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
    toast.info('Vui lòng đăng nhập để thích bài viết.');
    goToLogin();
    return;
  }
  if (post.likes_available === false) {
    toast.info('Tính năng thích đang tạm thời chưa khả dụng.');
    return;
  }
  if (likingPostIds.has(post.id)) return;

  likingPostIds.add(post.id);
  try {
    const response = await api(`/api/venue-posts/${post.id}/likes`, { method: 'POST' });
    post.is_liked = Boolean(response.data?.is_liked);
    post.like_count = Number(response.data?.like_count ?? post.like_count ?? 0);
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể cập nhật lượt thích.');
  } finally {
    likingPostIds.delete(post.id);
  }
}

async function submitComment(post) {
  if (!user) {
    goToLogin();
    return;
  }
  const content = commentDrafts[post.id]?.trim();
  if (!content || commentSubmitting[post.id]) return;

  commentSubmitting[post.id] = true;
  try {
    const response = await api(`/api/venue-posts/${post.id}/comments`, {
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
        avatar_url: user.user?.avatar_url || null,
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

async function sharePost(post) {
  const href = router.resolve({ name: 'community-post-detail', params: { slug: post.slug || post.id } }).href;
  const url = new URL(href, window.location.origin).toString();
  const shareData = { title: post.title || 'Bài viết SportGo', text: post.short_description || plainText(post.content), url };

  try {
    if (navigator.share) {
      await navigator.share(shareData);
      return;
    }
    if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
    await navigator.clipboard.writeText(url);
    toast.success('Đã sao chép liên kết bài viết.');
  } catch (shareError) {
    if (shareError?.name !== 'AbortError') toast.error('Không thể chia sẻ bài viết trên trình duyệt này.');
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

async function joinMatchmaking(post) {
  if (!user) {
    toast.info('Vui lòng đăng nhập để tham gia giao lưu.');
    goToLogin();
    return;
  }
  if (!isPlayer.value) {
    toast.info('Chức năng này dành cho tài khoản người dùng.');
    return;
  }
  joiningPostId.value = post.id;
  try {
    await api(`/api/matchmaking-posts/${post.id}/join`, { method: 'POST' });
    post.user_status = 'pending';
    toast.success('Đã gửi yêu cầu tham gia.');
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể gửi yêu cầu tham gia.');
  } finally {
    joiningPostId.value = null;
  }
}

function joinLabel(post) {
  if (joiningPostId.value === post.id) return 'Đang gửi...';
  return {
    pending: 'Đang chờ duyệt',
    approved: 'Đã tham gia',
    rejected: 'Đã bị từ chối',
  }[post.user_status] || 'Xin tham gia';
}

function isOwnPost(post) {
  return String(user?.id || '') === String(post.author?.id || '');
}

function handleCommunityPostCreated(response) {
  showCommunityModal.value = false;
  if (response?.data?.status === 'published') {
    toast.success('Bài viết đã được đăng.');
    fetchPosts({ page: 1 });
    return;
  }
  toast.success(response?.message || 'Bài viết đã được gửi và đang chờ kiểm duyệt.');
}

function handleMeetupPostCreated() {
  showMeetupModal.value = false;
  toast.success('Bài giao lưu đã được tạo.');
  fetchMatchmakingPosts();
}

function visibleComments(post) {
  const comments = Array.isArray(post.top_level_comments) ? post.top_level_comments : [];
  return showAllComments[post.id] ? comments : comments.slice(0, commentPreviewLimit);
}

function titleRepeatsContent(post) {
  const title = plainText(post.title).replace(/\.{3}$/, '').trim().toLocaleLowerCase('vi-VN');
  const content = plainText(post.content).trim().toLocaleLowerCase('vi-VN');
  return Boolean(title && content.startsWith(title));
}

function plainText(value) {
  if (!value) return 'Bài chia sẻ từ cộng đồng SportGo.';
  const documentFragment = new DOMParser().parseFromString(String(value), 'text/html');
  return (documentFragment.body.textContent || '').trim();
}

function postMedia(post) {
  const items = Array.isArray(post?.media) ? post.media : [];
  const sorted = [...items].sort((left, right) => {
    if (left.collection === 'thumbnail' && right.collection !== 'thumbnail') return -1;
    if (right.collection === 'thumbnail' && left.collection !== 'thumbnail') return 1;
    return Number(left.sort_order || 0) - Number(right.sort_order || 0);
  });
  return [...new Set(sorted.map((item) => assetUrl(item.url || item.file_url || item.full_url || item.file_path || item.path)).filter(Boolean))];
}

function assetUrl(path) {
  if (!path || /^https?:\/\//.test(path) || path.startsWith('/')) return path || '';
  return `/storage/${path}`;
}

function handlePostImageError(event) {
  const image = event.currentTarget;
  if (image?.getAttribute('src') !== fallbackPostImage) image.src = fallbackPostImage;
}

function initial(name) {
  return String(name || 'S').trim().charAt(0).toUpperCase();
}

function formatDate(value) {
  if (!value) return 'Chưa rõ ngày';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Chưa rõ ngày';
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

function goToDetail(slug) {
  router.push({ name: 'community-post-detail', params: { slug } });
}

function goToUser(id) {
  if (id) router.push(`/user/${id}`);
}

function goToLogin() {
  router.push({ name: 'login', query: { redirect: route.fullPath } });
}

function togglePostMenu(postId) {
  openMenuPostId.value = openMenuPostId.value === postId ? null : postId;
}

function closePostMenu() {
  openMenuPostId.value = null;
}

onMounted(() => {
  fetchPosts();
  fetchMatchmakingPosts();
  document.addEventListener('click', closePostMenu);
});

onBeforeUnmount(() => document.removeEventListener('click', closePostMenu));
</script>

<style scoped src="../../../../css/client-community-feed.css"></style>

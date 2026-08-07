<template>
  <div class="sg-community-page">
    <PublicNavbar />

    <main class="sg-community-main">
      <header class="sg-community-hero">
        <div class="sg-community-hero-copy">
          <p class="sg-community-eyebrow">Cộng đồng SportGo</p>
          <h1>Cùng chơi, cùng chia sẻ</h1>
          <p class="sg-community-hero-description">Tìm kinh nghiệm hữu ích, kết nối người chơi và theo dõi những câu chuyện mới từ các sân thể thao.</p>

          <form class="sg-community-search" role="search" @submit.prevent="applyFilters">
            <AppIcon name="search" class="sg-community-search-icon" />
            <label class="sr-only" for="community-search">Tìm trong cộng đồng</label>
            <input id="community-search" v-model.trim="searchQuery" type="search" placeholder="Tìm bài viết, kinh nghiệm, địa điểm..." autocomplete="off" />
            <button type="submit">Tìm kiếm</button>
          </form>

          <div class="sg-community-quick-topics" aria-label="Chủ đề phổ biến">
            <span>Chủ đề phổ biến</span>
            <button v-for="category in categories.slice(0, 4)" :key="category" type="button" :class="{ active: selectedCategory === category }" @click="setCategory(category)">
              {{ category }}
            </button>
          </div>
        </div>

        <div class="sg-community-hero-action">
          <span>Kết nối ngoài sân</span>
          <router-link to="/community/matchmaking">Xem tuyển giao lưu <AppIcon name="chevronRight" /></router-link>
        </div>
      </header>

      <nav class="sg-community-section-nav" aria-label="Khu vực cộng đồng">
        <router-link to="/community" exact-active-class="is-active"><AppIcon name="newspaper" /> Bảng tin</router-link>
        <router-link to="/community/matchmaking" active-class="is-active"><AppIcon name="users" /> Tuyển giao lưu</router-link>
      </nav>

      <div class="sg-community-mobile-toolbar">
        <span><strong>{{ posts.length }}</strong> bài đang hiển thị</span>
        <button type="button" @click="showMobileFilters = !showMobileFilters">{{ showMobileFilters ? 'Ẩn bộ lọc' : 'Mở bộ lọc' }}</button>
      </div>

      <div class="sg-community-layout">
        <section class="sg-community-feed" aria-label="Bảng tin cộng đồng">
          <article v-if="canCreateCommunityPost" class="sg-community-composer">
            <div class="sg-community-composer-head">
              <span class="sg-community-avatar sg-community-avatar--user">{{ initial(user?.fullName) }}</span>
              <button type="button" class="sg-community-composer-trigger" @click="showCommunityModal = true">Bạn muốn chia sẻ điều gì với cộng đồng?</button>
            </div>
            <div class="sg-community-composer-actions">
              <button type="button" @click="showCommunityModal = true"><AppIcon name="edit" /> Bài chia sẻ</button>
              <button v-if="isPlayer" type="button" @click="showMeetupModal = true"><AppIcon name="users" /> Tạo kèo giao lưu</button>
            </div>
          </article>

          <div class="sg-community-feed-heading">
            <div>
              <p class="sg-community-eyebrow">Khám phá nội dung</p>
              <h2>Bảng tin mới nhất</h2>
              <p v-if="searchQuery || selectedCategory">Kết quả được lọc theo {{ searchQuery ? `“${searchQuery}”` : 'chủ đề đã chọn' }}.</p>
              <p v-else>Những chia sẻ mới nhất từ cộng đồng người chơi SportGo.</p>
            </div>
            <span class="sg-community-result-count">{{ posts.length }} bài</span>
          </div>

          <div v-if="loading" class="sg-community-state" aria-live="polite">
            <span class="sg-community-spinner" aria-hidden="true"></span>
            <p>Đang tải bảng tin...</p>
          </div>
          <div v-else-if="error" class="sg-community-state sg-community-state--error" role="alert">
            <AppIcon name="alert" />
            <strong>Không thể tải bảng tin</strong>
            <p>{{ error }}</p>
            <button type="button" @click="fetchPosts({ page: 1 })">Thử lại</button>
          </div>
          <div v-else-if="!posts.length" class="sg-community-state">
            <AppIcon name="newspaper" />
            <strong>Chưa có bài viết phù hợp</strong>
            <p>Hãy đổi chủ đề hoặc từ khóa để xem thêm nội dung.</p>
            <button v-if="searchQuery || selectedCategory" type="button" @click="clearFilters">Xóa bộ lọc</button>
          </div>

          <div v-else class="sg-community-post-list">
            <article v-for="post in posts" :key="post.id" class="sg-community-post-card">
              <header class="sg-community-post-header">
                <button type="button" class="sg-community-post-author" @click="goToUser(post.author?.id)">
                  <span class="sg-community-avatar">
                    <img v-if="post.author?.avatar_url" :src="assetUrl(post.author.avatar_url)" :alt="post.author.full_name || post.author.username" />
                    <span v-else>{{ initial(post.author?.full_name || post.author?.username) }}</span>
                  </span>
                  <span>
                    <strong>{{ post.author?.full_name || post.author?.username || 'Thành viên SportGo' }} <ClientAuthorBadges :badges="post.author_badges" /></strong>
                    <small>{{ timeAgo(post.published_at || post.created_at) }}<template v-if="post.venue_cluster?.name"> · {{ post.venue_cluster.name }}</template></small>
                  </span>
                </button>

                <div class="sg-community-post-menu">
                  <button type="button" class="sg-community-icon-button" :aria-expanded="openMenuPostId === post.id" aria-label="Tùy chọn bài viết" @click.stop="togglePostMenu(post.id)"><AppIcon name="moreHorizontal" /></button>
                  <div v-if="openMenuPostId === post.id" class="sg-community-menu" role="menu" @click.stop>
                    <button type="button" role="menuitem" @click="openReport(post)"><AppIcon name="alert" /> Báo cáo bài viết</button>
                  </div>
                </div>
              </header>

              <button type="button" class="sg-community-post-copy" @click="goToDetail(post.slug || post.id)">
                <div v-if="post.hashtags?.length" class="sg-community-post-tags"><span v-for="tag in post.hashtags.slice(0, 4)" :key="tag.id || tag.name">#{{ tag.name }}</span></div>
                <strong v-if="post.title && !titleRepeatsContent(post)">{{ post.title }}</strong>
                <span>{{ plainText(post.content || post.short_description) }}</span>
              </button>

              <button v-if="postMedia(post).length === 1" type="button" class="sg-community-post-media sg-community-post-media--single" @click="goToDetail(post.slug || post.id)">
                <img :src="postMedia(post)[0]" :alt="post.title || 'Ảnh bài viết'" @error="handlePostImageError" />
              </button>
              <button v-else-if="postMedia(post).length > 1" type="button" class="sg-community-post-media sg-community-post-media--grid" :class="{ 'is-two': postMedia(post).length === 2 }" @click="goToDetail(post.slug || post.id)">
                <span v-for="(image, imageIndex) in postMedia(post).slice(0, 4)" :key="`${post.id}-${imageIndex}`">
                  <img :src="image" :alt="`${post.title || 'Ảnh bài viết'} ${imageIndex + 1}`" @error="handlePostImageError" />
                  <b v-if="imageIndex === 3 && postMedia(post).length > 4">+{{ postMedia(post).length - 4 }}</b>
                </span>
              </button>

              <div class="sg-community-post-stats">
                <span><AppIcon name="heart" /> {{ post.like_count || 0 }} lượt thích</span>
                <button type="button" @click="toggleComments(post)">{{ post.comment_count || 0 }} bình luận</button>
              </div>

              <div class="sg-community-post-actions">
                <button type="button" :class="{ active: post.is_liked }" :disabled="likingPostIds.has(post.id) || post.likes_available === false" :title="post.likes_available === false ? 'Tính năng thích đang tạm thời chưa khả dụng' : ''" @click="toggleLike(post)"><AppIcon name="heart" /> {{ post.is_liked ? 'Đã thích' : 'Thích' }}</button>
                <button type="button" :class="{ active: commentsOpen[post.id] }" @click="toggleComments(post)"><AppIcon name="messageCircle" /> Bình luận</button>
                <button type="button" @click="sharePost(post)"><AppIcon name="share" /> Chia sẻ</button>
              </div>

              <section v-if="commentsOpen[post.id]" class="sg-community-comments" aria-label="Bình luận bài viết">
                <div v-if="detailsLoading[post.id]" class="sg-community-comments-loading"><span class="sg-community-spinner"></span> Đang tải bình luận...</div>
                <template v-else>
                  <div v-if="post.top_level_comments?.length" class="sg-community-comment-list">
                    <article v-for="comment in visibleComments(post)" :key="comment.id" class="sg-community-comment">
                      <span class="sg-community-avatar sg-community-avatar--small">
                        <img v-if="comment.user?.avatar_url" :src="assetUrl(comment.user.avatar_url)" :alt="comment.user.full_name || comment.user.username" />
                        <span v-else>{{ initial(comment.user?.full_name || comment.user?.username) }}</span>
                      </span>
                      <div class="sg-community-comment-body">
                        <div class="sg-community-comment-bubble"><strong>{{ comment.user?.full_name || comment.user?.username || 'Thành viên SportGo' }} <ClientAuthorBadges :badges="comment.user?.author_badges" /></strong><p>{{ comment.content }}</p></div>
                        <div class="sg-community-comment-meta"><small>{{ timeAgo(comment.created_at) }}</small><button type="button" @click="setReply(post, comment)">Trả lời</button></div>
                        <div v-if="comment.replies?.length" class="sg-community-reply-list">
                          <article v-for="reply in comment.replies" :key="reply.id" class="sg-community-comment sg-community-comment--reply">
                            <span class="sg-community-avatar sg-community-avatar--tiny">
                              <img v-if="reply.user?.avatar_url" :src="assetUrl(reply.user.avatar_url)" :alt="reply.user.full_name || reply.user.username" />
                              <span v-else>{{ initial(reply.user?.full_name || reply.user?.username) }}</span>
                            </span>
                            <div class="sg-community-comment-body"><div class="sg-community-comment-bubble"><strong>{{ reply.user?.full_name || reply.user?.username || 'Thành viên SportGo' }} <ClientAuthorBadges :badges="reply.user?.author_badges" /></strong><p>{{ reply.content }}</p></div><div class="sg-community-comment-meta"><small>{{ timeAgo(reply.created_at) }}</small><button type="button" @click="setReply(post, comment, reply)">Trả lời</button></div></div>
                          </article>
                        </div>
                      </div>
                    </article>
                    <button v-if="post.top_level_comments.length > commentPreviewLimit && !showAllComments[post.id]" type="button" class="sg-community-more-comments" @click="showAllComments[post.id] = true">Xem thêm {{ post.top_level_comments.length - commentPreviewLimit }} bình luận</button>
                  </div>
                  <p v-else class="sg-community-comments-empty">Chưa có bình luận. Hãy bắt đầu cuộc trò chuyện.</p>

                  <form v-if="user" class="sg-community-comment-form" @submit.prevent="submitComment(post)">
                    <div v-if="replyingTo[post.id]" class="sg-community-replying"><span>Đang trả lời <strong>{{ replyingTo[post.id].user?.full_name || replyingTo[post.id].user?.username || 'thành viên' }}</strong></span><button type="button" aria-label="Hủy trả lời" @click="replyingTo[post.id] = null"><AppIcon name="x" /></button></div>
                    <div class="sg-community-comment-input-row"><span class="sg-community-avatar sg-community-avatar--small sg-community-avatar--user">{{ initial(user.fullName) }}</span><label><span class="sr-only">Viết bình luận</span><input :id="`comment-input-${post.id}`" v-model.trim="commentDrafts[post.id]" type="text" maxlength="1000" :placeholder="replyingTo[post.id] ? `Phản hồi ${replyingTo[post.id].user?.full_name || replyingTo[post.id].user?.username || 'thành viên'}...` : 'Viết bình luận...'" :disabled="commentSubmitting[post.id]" /></label><button type="submit" aria-label="Gửi bình luận" :disabled="commentSubmitting[post.id] || !commentDrafts[post.id]?.trim()"><AppIcon name="send" /></button></div>
                  </form>
                  <button v-else type="button" class="sg-community-login-comment" @click="goToLogin">Đăng nhập để bình luận</button>
                </template>
              </section>
            </article>

            <button v-if="pagination.current_page < pagination.last_page" type="button" class="sg-community-load-more" :disabled="loadingMore" @click="loadMorePosts"><span v-if="loadingMore" class="sg-community-spinner"></span>{{ loadingMore ? 'Đang tải thêm...' : 'Xem thêm bài viết' }}</button>
            <p v-else class="sg-community-end-note">Bạn đã xem hết các bài viết hiện có.</p>
          </div>
        </section>

        <aside class="sg-community-sidebar" :class="{ 'is-open': showMobileFilters }" aria-label="Khám phá cộng đồng">
          <section class="sg-community-panel sg-community-filter-panel">
            <div class="sg-community-panel-heading"><div><p class="sg-community-eyebrow">Lọc nội dung</p><h2>Chủ đề</h2></div><button v-if="searchQuery || selectedCategory" type="button" class="sg-community-text-button" @click="clearFilters">Xóa lọc</button></div>
            <div class="sg-community-category-list" aria-label="Chủ đề bài viết">
              <button type="button" :class="{ active: !selectedCategory }" @click="setCategory('')"><span>Tất cả bài viết</span><b v-if="!selectedCategory">✓</b></button>
              <button v-for="category in categories" :key="category" type="button" :class="{ active: selectedCategory === category }" @click="setCategory(category)"><span>{{ category }}</span><b v-if="selectedCategory === category">✓</b></button>
            </div>
            <div v-if="searchQuery" class="sg-community-active-query"><AppIcon name="search" /><span>Đang tìm “{{ searchQuery }}”</span></div>
          </section>

          <section class="sg-community-panel sg-community-matchmaking-panel">
            <header class="sg-community-panel-heading"><div><p class="sg-community-eyebrow">Ghép kèo</p><h2>Kèo sắp tới</h2></div><button v-if="isPlayer" type="button" class="sg-community-icon-button sg-community-icon-button--accent" aria-label="Tạo bài giao lưu" @click="showMeetupModal = true"><AppIcon name="plus" /></button></header>
            <div v-if="matchmakingLoading" class="sg-community-panel-state"><span class="sg-community-spinner"></span> Đang tải kèo...</div>
            <div v-else-if="matchmakingError" class="sg-community-panel-state sg-community-panel-state--error" role="alert"><p>{{ matchmakingError }}</p><button type="button" @click="fetchMatchmakingPosts">Tải lại</button></div>
            <div v-else-if="matchmakingPosts.length" class="sg-community-match-list">
              <article v-for="post in matchmakingPosts" :key="post.id" class="sg-community-match-card">
                <header><button type="button" @click="goToUser(post.author?.id)"><span class="sg-community-avatar sg-community-avatar--tiny">{{ initial(post.author?.name) }}</span><span><strong>{{ post.author?.name || 'Người chơi SportGo' }}</strong><small>{{ timeAgo(post.created_at) }}</small></span></button><b>Cần {{ post.needed_players }}</b></header>
                <p class="sg-community-match-line"><AppIcon name="mapPin" /> {{ post.booking?.venue_name || 'Cụm sân' }}</p>
                <p class="sg-community-match-line"><AppIcon name="clock" /> {{ formatDate(post.booking?.date) }} · {{ post.booking?.time }}</p>
                <p v-if="post.description" class="sg-community-match-description">{{ post.description }}</p>
                <button v-if="!isOwnPost(post)" type="button" class="sg-community-match-action" :class="{ muted: post.user_status }" :disabled="joiningPostId === post.id || Boolean(post.user_status)" @click="joinMatchmaking(post)">{{ joinLabel(post) }}</button>
                <router-link v-else class="sg-community-match-action" to="/community/matchmaking">Quản lý yêu cầu</router-link>
              </article>
            </div>
            <div v-else class="sg-community-panel-state"><AppIcon name="users" /><p>Chưa có kèo công khai sắp tới.</p><button v-if="isPlayer" type="button" @click="showMeetupModal = true">Tạo kèo đầu tiên</button></div>
            <router-link class="sg-community-panel-link" to="/community/matchmaking">Xem tất cả tuyển giao lưu <AppIcon name="chevronRight" /></router-link>
          </section>

          <section class="sg-community-panel sg-community-guideline-panel"><p class="sg-community-eyebrow">Cùng giữ feed hữu ích</p><h2>Chia sẻ điều bạn thật sự muốn trao đổi</h2><p>Ưu tiên kinh nghiệm cụ thể, thông tin trận đấu rõ ràng và bình luận tôn trọng người chơi khác.</p></section>
        </aside>
      </div>
    </main>

    <CommunityPostModal :is-open="showCommunityModal" @close="showCommunityModal = false" @success="handleCommunityPostCreated" />
    <MeetupPostModal :is-open="showMeetupModal" @close="showMeetupModal = false" @success="handleMeetupPostCreated" />
    <ReportModal :is-open="Boolean(reportTarget)" :target-type="reportTarget?.feed_type === 'community_post' ? 'community_post' : 'venue_post'" :target-id="reportTarget?.entity_id || reportTarget?.id || ''" :target-name="reportTarget?.title || 'Bài viết cộng đồng'" @close="reportTarget = null" @success="handleReportSuccess" />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
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
const searchQuery = ref(String(route.query.q || ''));
const selectedCategory = ref(String(route.query.category || ''));
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
const replyingTo = reactive({});
const matchmakingPosts = ref([]);
const matchmakingLoading = ref(true);
const matchmakingError = ref('');
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
    pagination.value = { current_page: Number(response.current_page || page), last_page: Number(response.last_page || 1) };
  } catch (requestError) {
    if (append) toast.error(requestError.message || 'Không thể tải thêm bài viết.');
    else { posts.value = []; error.value = requestError.message || 'Không thể tải bài viết cộng đồng.'; }
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

async function fetchMatchmakingPosts() {
  matchmakingLoading.value = true;
  matchmakingError.value = '';
  try {
    const response = await api('/api/matchmaking-posts');
    matchmakingPosts.value = Array.isArray(response.data) ? response.data.slice(0, 5) : [];
  } catch (requestError) {
    matchmakingPosts.value = [];
    matchmakingError.value = requestError.message || 'Không thể tải các kèo sắp tới.';
  } finally {
    matchmakingLoading.value = false;
  }
}

function applyFilters() {
  showMobileFilters.value = false;
  router.replace({ query: { ...(searchQuery.value ? { q: searchQuery.value } : {}), ...(selectedCategory.value ? { category: selectedCategory.value } : {}) } });
  fetchPosts({ page: 1 });
}

function setCategory(category) {
  selectedCategory.value = category;
  applyFilters();
}

function clearFilters() {
  searchQuery.value = '';
  selectedCategory.value = '';
  applyFilters();
}

function loadMorePosts() {
  if (loadingMore.value || pagination.value.current_page >= pagination.value.last_page) return;
  fetchPosts({ page: pagination.value.current_page + 1, append: true });
}

function postKey(post) {
  return post?.slug || post?.id;
}

async function ensurePostDetails(post) {
  if (Array.isArray(post.top_level_comments)) return;
  detailsLoading[post.id] = true;
  try {
    const response = await api(`/api/venue-posts/${postKey(post)}`);
    const detail = response.data || {};
    post.top_level_comments = Array.isArray(detail.top_level_comments) ? detail.top_level_comments : [];
    post.comment_count = Number(detail.comment_count ?? post.comment_count ?? 0);
    post.like_count = Number(detail.like_count ?? post.like_count ?? 0);
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
  if (!user) { toast.info('Vui lòng đăng nhập để thích bài viết.'); goToLogin(); return; }
  if (post.likes_available === false) { toast.info('Tính năng thích đang tạm thời chưa khả dụng.'); return; }
  if (likingPostIds.has(post.id)) return;

  const previousLiked = Boolean(post.is_liked);
  const previousCount = Number(post.like_count || 0);
  const nextLiked = !previousLiked;

  // Update the feed immediately. The API response below remains authoritative
  // and will reconcile the optimistic state when it arrives.
  post.is_liked = nextLiked;
  post.like_count = Math.max(0, previousCount + (nextLiked ? 1 : -1));
  likingPostIds.add(post.id);
  try {
    const response = await api(`/api/venue-posts/${postKey(post)}/likes`, { method: 'POST' });
    post.is_liked = Boolean(response.data?.is_liked);
    post.like_count = Number(response.data?.like_count ?? post.like_count ?? 0);
  } catch (requestError) {
    post.is_liked = previousLiked;
    post.like_count = previousCount;
    toast.error(requestError.message || 'Không thể cập nhật lượt thích.');
  } finally {
    likingPostIds.delete(post.id);
  }
}

async function submitComment(post) {
  if (!user) { goToLogin(); return; }
  const content = commentDrafts[post.id]?.trim();
  if (!content || content.length < 2 || commentSubmitting[post.id]) return;

  commentSubmitting[post.id] = true;
  const parentComment = replyingTo[post.id];
  try {
    const payload = { content };
    if (parentComment) payload.parent_id = parentComment.id;
    const response = await api(`/api/venue-posts/${postKey(post)}/comments`, { method: 'POST', body: JSON.stringify(payload) });
    const newComment = response.data || { id: `new-${Date.now()}`, content, created_at: new Date().toISOString(), user: { id: user.id, full_name: user.fullName, username: user.username, avatar_url: user.user?.avatar_url || user.avatar_url || null } };

    if (parentComment) {
      if (!Array.isArray(parentComment.replies)) parentComment.replies = [];
      parentComment.replies.push(newComment);
    } else {
      if (!Array.isArray(post.top_level_comments)) post.top_level_comments = [];
      post.top_level_comments.unshift(newComment);
    }

    post.comment_count = Number(response.data?.comment_count ?? Number(post.comment_count || 0) + 1);
    commentDrafts[post.id] = '';
    replyingTo[post.id] = null;
    toast.success('Đã đăng bình luận.');
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể gửi bình luận.');
  } finally {
    commentSubmitting[post.id] = false;
  }
}

function setReply(post, comment, targetReply = null) {
  if (!user) { toast.info('Vui lòng đăng nhập để bình luận.'); goToLogin(); return; }
  const targetUser = targetReply ? (targetReply.user?.full_name || targetReply.user?.username || 'Thành viên SportGo') : null;
  replyingTo[post.id] = comment;
  if (targetUser) commentDrafts[post.id] = `@${targetUser} `;
  nextTick(() => document.getElementById(`comment-input-${post.id}`)?.focus());
}

async function sharePost(post) {
  const href = router.resolve({ name: 'community-post-detail', params: { slug: postKey(post) } }).href;
  const url = new URL(href, window.location.origin).toString();
  try {
    if (navigator.share) { await navigator.share({ title: post.title || 'Bài viết SportGo', text: post.short_description || plainText(post.content), url }); return; }
    if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
    await navigator.clipboard.writeText(url);
    toast.success('Đã sao chép liên kết bài viết.');
  } catch (shareError) {
    if (shareError?.name !== 'AbortError') toast.error('Không thể chia sẻ bài viết trên trình duyệt này.');
  }
}

function openReport(post) {
  openMenuPostId.value = null;
  if (!user) { toast.info('Vui lòng đăng nhập để gửi báo cáo.'); goToLogin(); return; }
  reportTarget.value = post;
}

function handleReportSuccess() {
  reportTarget.value = null;
  toast.success('Báo cáo đã được gửi để SportGo kiểm tra.');
}

async function joinMatchmaking(post) {
  if (!user) { toast.info('Vui lòng đăng nhập để tham gia giao lưu.'); goToLogin(); return; }
  if (!isPlayer.value) { toast.info('Chức năng này dành cho tài khoản người dùng.'); return; }
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
  return { pending: 'Đang chờ duyệt', approved: 'Đã tham gia', rejected: 'Đã bị từ chối' }[post.user_status] || 'Xin tham gia';
}

function isOwnPost(post) { return String(user?.id || '') === String(post.author?.id || ''); }

function handleCommunityPostCreated(response) {
  showCommunityModal.value = false;
  if (response?.data?.status === 'published') { toast.success('Bài viết đã được đăng.'); fetchPosts({ page: 1 }); return; }
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

function initial(name) { return String(name || 'S').trim().charAt(0).toUpperCase(); }

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

function goToDetail(slug) { router.push({ name: 'community-post-detail', params: { slug } }); }
function goToUser(id) { if (id) router.push(`/user/${id}`); }
function goToLogin() { router.push({ name: 'login', query: { redirect: route.fullPath } }); }
function togglePostMenu(postId) { openMenuPostId.value = openMenuPostId.value === postId ? null : postId; }
function closePostMenu() { openMenuPostId.value = null; }

onMounted(() => {
  fetchPosts();
  fetchMatchmakingPosts();
  document.addEventListener('click', closePostMenu);
});

onBeforeUnmount(() => document.removeEventListener('click', closePostMenu));
</script>

<style scoped>
.sg-community-page { min-height: 100vh; background: #f4f8f5; color: #17261c; }
.sg-community-main { width: min(1180px, calc(100% - 40px)); margin: 0 auto; padding: 90px 0 80px; }
.sg-community-hero { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 24px; padding: 26px 30px 21px; border: 1px solid #d6e6da; border-radius: 12px; background: #fff; box-shadow: 0 8px 22px rgba(35, 74, 48, .045); }
.sg-community-hero-copy { min-width: 0; }
.sg-community-eyebrow { margin: 0 0 10px; color: #087f3e; font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.sg-community-hero h1 { max-width: 660px; margin: 0; color: #102118; font-family: "Outfit", "Inter", sans-serif; font-size: clamp(32px, 4vw, 42px); line-height: 1.08; letter-spacing: -.035em; }
.sg-community-hero-description { max-width: 650px; margin: 9px 0 17px; color: #5d7163; font-size: 14px; line-height: 1.55; }
.sg-community-search { display: flex; position: relative; max-width: 700px; align-items: center; gap: 10px; padding: 5px; border: 1px solid #c7ded0; border-radius: 8px; background: #fff; }
.sg-community-search-icon { width: 19px; height: 19px; margin-left: 12px; color: #6b8173; }
.sg-community-search input { min-width: 0; flex: 1; height: 42px; border: 0; outline: 0; color: #17261c; font: inherit; font-size: 14px; }
.sg-community-search input::placeholder { color: #91a197; }
.sg-community-search button, .sg-community-state button, .sg-community-panel-state button { border: 0; border-radius: 7px; background: #087f3e; color: #fff; cursor: pointer; font-size: 13px; font-weight: 800; }
.sg-community-search button { min-height: 42px; padding: 0 18px; }
.sg-community-search button:hover, .sg-community-state button:hover, .sg-community-panel-state button:hover { background: #066d35; }
.sg-community-quick-topics { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 17px; color: #708177; font-size: 12px; }
.sg-community-quick-topics button { padding: 5px 10px; border: 1px solid #d4e4d8; border-radius: 999px; background: rgba(255,255,255,.65); color: #426050; cursor: pointer; font-size: 12px; }
.sg-community-quick-topics button:hover, .sg-community-quick-topics button.active { border-color: #84c99b; background: #dff4e6; color: #087f3e; }
.sg-community-hero-action { align-self: center; min-width: 164px; padding-left: 18px; border-left: 1px solid #e0ebe2; }
.sg-community-hero-action > span { display: block; margin-bottom: 7px; color: #7a8b80; font-size: 11px; }
.sg-community-hero-action a, .sg-community-panel-link { display: inline-flex; align-items: center; gap: 7px; color: #087f3e; font-size: 13px; font-weight: 800; text-decoration: none; }
.sg-community-hero-action a:hover, .sg-community-panel-link:hover { color: #055c2c; }
.sg-community-hero-action a svg, .sg-community-panel-link svg { width: 15px; height: 15px; }
.sg-community-section-nav { display: flex; gap: 8px; margin: 18px 0 30px; padding: 6px; border: 1px solid #d6e6da; border-radius: 10px; background: #fff; }
.sg-community-section-nav a { display: inline-flex; align-items: center; gap: 8px; padding: 11px 18px; border-radius: 7px; color: #64766a; font-size: 13px; font-weight: 700; text-decoration: none; }
.sg-community-section-nav a svg { width: 17px; height: 17px; }
.sg-community-section-nav a:hover, .sg-community-section-nav a.is-active { background: #e5f6eb; color: #087f3e; }
.sg-community-mobile-toolbar { display: none; }
.sg-community-layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 32px; align-items: start; }
.sg-community-feed, .sg-community-sidebar { min-width: 0; }
.sg-community-sidebar { position: sticky; top: 84px; display: grid; gap: 18px; }
.sg-community-composer, .sg-community-feed-heading, .sg-community-post-card, .sg-community-panel, .sg-community-state { border: 1px solid #d6e6da; border-radius: 12px; background: #fff; box-shadow: 0 8px 22px rgba(35, 74, 48, .055); }
.sg-community-composer { padding: 19px; }
.sg-community-composer-head { display: flex; align-items: center; gap: 12px; }
.sg-community-composer-trigger { min-height: 44px; flex: 1; padding: 0 15px; border: 1px solid #d6e6da; border-radius: 8px; background: #f7faf8; color: #829188; cursor: pointer; text-align: left; font-size: 13px; }
.sg-community-composer-trigger:hover { border-color: #8ac99c; background: #f0faf3; color: #4a6655; }
.sg-community-composer-actions { display: flex; gap: 8px; margin-top: 17px; padding-top: 14px; border-top: 1px solid #edf3ee; }
.sg-community-composer-actions button, .sg-community-text-button { display: inline-flex; align-items: center; gap: 7px; border: 0; background: transparent; color: #5d7163; cursor: pointer; font-size: 12px; font-weight: 800; }
.sg-community-composer-actions button { padding: 7px 9px; border-radius: 6px; }
.sg-community-composer-actions button:hover { background: #eaf8ee; color: #087f3e; }
.sg-community-composer-actions svg { width: 16px; height: 16px; }
.sg-community-feed-heading { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin: 18px 0 12px; padding: 20px 22px; }
.sg-community-feed-heading h2 { margin: 0; color: #14241a; font-size: 22px; }
.sg-community-feed-heading p:not(.sg-community-eyebrow) { margin: 6px 0 0; color: #728278; font-size: 13px; }
.sg-community-result-count { align-self: center; padding: 6px 10px; border-radius: 999px; background: #eff8f1; color: #087f3e; font-size: 12px; font-weight: 800; white-space: nowrap; }
.sg-community-state { display: grid; min-height: 250px; place-items: center; align-content: center; gap: 8px; padding: 30px; color: #718075; text-align: center; }
.sg-community-state svg { width: 30px; height: 30px; color: #71a983; }
.sg-community-state strong { color: #34493a; font-size: 15px; }
.sg-community-state p { margin: 0; font-size: 13px; }
.sg-community-state button { min-height: 36px; padding: 0 13px; margin-top: 4px; }
.sg-community-state--error { border-color: #f0caca; background: #fffafa; color: #a33c3c; }
.sg-community-state--error svg { color: #c75b5b; }
.sg-community-state--error strong { color: #8a2d2d; }
.sg-community-post-list { display: grid; gap: 14px; }
.sg-community-post-card { overflow: hidden; }
.sg-community-post-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 20px 12px; }
.sg-community-post-author { display: flex; min-width: 0; align-items: center; gap: 11px; padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; text-align: left; }
.sg-community-post-author > span:last-child { display: grid; min-width: 0; gap: 4px; }
.sg-community-post-author strong { overflow: hidden; color: #1b2b20; font-size: 13px; text-overflow: ellipsis; white-space: nowrap; }
.sg-community-post-author small { color: #7b8d81; font-size: 11px; }
.sg-community-avatar { display: grid; width: 40px; height: 40px; flex: 0 0 auto; place-items: center; overflow: hidden; border-radius: 50%; background: #dff4e6; color: #087f3e; font-size: 13px; font-weight: 800; }
.sg-community-avatar img { width: 100%; height: 100%; object-fit: cover; }
.sg-community-avatar--small { width: 31px; height: 31px; font-size: 10px; }
.sg-community-avatar--tiny { width: 28px; height: 28px; font-size: 10px; }
.sg-community-avatar--user { background: #087f3e; color: #fff; }
.sg-community-post-menu { position: relative; }
.sg-community-icon-button { display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid transparent; border-radius: 7px; background: transparent; color: #78887e; cursor: pointer; }
.sg-community-icon-button:hover { border-color: #d6e6da; background: #f5faf6; color: #087f3e; }
.sg-community-icon-button svg { width: 18px; height: 18px; }
.sg-community-icon-button--accent { border-color: #cbe7d2; background: #eaf8ee; color: #087f3e; }
.sg-community-menu { position: absolute; z-index: 10; top: 38px; right: 0; width: 176px; padding: 5px; border: 1px solid #d6e6da; border-radius: 8px; background: #fff; box-shadow: 0 10px 24px rgba(20, 42, 26, .14); }
.sg-community-menu button { display: flex; width: 100%; align-items: center; gap: 8px; padding: 9px; border: 0; border-radius: 5px; background: transparent; color: #a43b3b; cursor: pointer; font-size: 12px; font-weight: 700; text-align: left; }
.sg-community-menu button:hover { background: #fff2f2; }
.sg-community-menu svg { width: 15px; height: 15px; }
.sg-community-post-copy { display: grid; width: calc(100% - 40px); gap: 8px; margin: 0 20px 17px; padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; text-align: left; }
.sg-community-post-copy strong { color: #16291c; font-size: 17px; line-height: 1.35; }
.sg-community-post-copy > span { display: -webkit-box; overflow: hidden; color: #405449; font-size: 14px; line-height: 1.65; -webkit-box-orient: vertical; -webkit-line-clamp: 4; }
.sg-community-post-tags { display: flex; flex-wrap: wrap; gap: 7px; }
.sg-community-post-tags span { color: #087f3e; font-size: 11px; font-weight: 800; }
.sg-community-post-media { display: block; width: 100%; padding: 0; border: 0; background: #edf4ef; cursor: pointer; overflow: hidden; }
.sg-community-post-media--single { aspect-ratio: 16 / 9; }
.sg-community-post-media--grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 3px; aspect-ratio: 16 / 9; }
.sg-community-post-media--grid.is-two { aspect-ratio: 16 / 8; }
.sg-community-post-media--grid span { position: relative; min-height: 0; overflow: hidden; }
.sg-community-post-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .25s ease; }
.sg-community-post-media:hover img { transform: scale(1.02); }
.sg-community-post-media b { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(16, 33, 24, .48); color: #fff; font-size: 18px; }
.sg-community-post-stats { display: flex; align-items: center; gap: 17px; padding: 12px 20px; border-bottom: 1px solid #edf3ee; color: #7a8b80; font-size: 11px; }
.sg-community-post-stats span { display: inline-flex; align-items: center; gap: 5px; }
.sg-community-post-stats svg { width: 14px; height: 14px; color: #0a9950; }
.sg-community-post-stats button { margin-left: auto; padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; }
.sg-community-post-stats button:hover { color: #087f3e; }
.sg-community-post-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; padding: 7px 12px; }
.sg-community-post-actions button { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; gap: 7px; border: 0; border-radius: 7px; background: transparent; color: #64766a; cursor: pointer; font-size: 12px; font-weight: 800; }
.sg-community-post-actions button:hover, .sg-community-post-actions button.active { background: #eaf8ee; color: #087f3e; }
.sg-community-post-actions button:disabled { cursor: not-allowed; opacity: .45; }
.sg-community-post-actions svg { width: 16px; height: 16px; }
.sg-community-comments { padding: 17px 20px 20px; border-top: 1px solid #e1ede4; background: #f8fbf8; }
.sg-community-comments-loading { display: flex; min-height: 70px; align-items: center; justify-content: center; gap: 8px; color: #7b8d81; font-size: 12px; }
.sg-community-spinner { display: inline-block; width: 17px; height: 17px; border: 2px solid #cbe7d2; border-top-color: #087f3e; border-radius: 50%; animation: sg-community-spin .8s linear infinite; }
@keyframes sg-community-spin { to { transform: rotate(360deg); } }
.sg-community-comment-list { display: grid; gap: 14px; }
.sg-community-comment { display: grid; grid-template-columns: 31px minmax(0, 1fr); gap: 9px; }
.sg-community-comment--reply { grid-template-columns: 28px minmax(0, 1fr); }
.sg-community-comment-body { min-width: 0; }
.sg-community-comment-bubble { display: inline-grid; max-width: 100%; gap: 5px; padding: 10px 12px; border: 1px solid #e0eae2; border-radius: 4px 12px 12px 12px; background: #fff; }
.sg-community-comment-bubble strong { color: #263a2c; font-size: 11px; }
.sg-community-comment-bubble p { margin: 0; color: #46594b; font-size: 13px; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
.sg-community-comment-meta { display: flex; align-items: center; gap: 12px; margin: 4px 0 0 8px; }
.sg-community-comment-meta small, .sg-community-comment-meta button { color: #88978e; font-size: 10px; }
.sg-community-comment-meta button { padding: 0; border: 0; background: transparent; cursor: pointer; font-weight: 800; }
.sg-community-comment-meta button:hover { color: #087f3e; }
.sg-community-reply-list { display: grid; gap: 11px; margin-top: 11px; padding-left: 18px; border-left: 2px solid #dcebe0; }
.sg-community-more-comments { justify-self: start; padding: 0; border: 0; background: transparent; color: #087f3e; cursor: pointer; font-size: 11px; font-weight: 800; }
.sg-community-comments-empty { margin: 0; padding: 12px 0; color: #89988e; font-size: 12px; text-align: center; }
.sg-community-comment-form { display: grid; gap: 9px; margin-top: 15px; }
.sg-community-replying { display: flex; align-items: center; justify-content: space-between; padding: 7px 10px; border-radius: 6px; background: #eaf4ec; color: #627568; font-size: 11px; }
.sg-community-replying button { display: grid; place-items: center; padding: 0; border: 0; background: transparent; color: #718277; cursor: pointer; }
.sg-community-replying svg { width: 14px; height: 14px; }
.sg-community-comment-input-row { display: flex; align-items: center; gap: 8px; }
.sg-community-comment-input-row label { min-width: 0; flex: 1; }
.sg-community-comment-input-row input { width: 100%; height: 38px; padding: 0 13px; border: 1px solid #d6e6da; border-radius: 999px; outline: 0; background: #fff; color: #17261c; font-size: 12px; }
.sg-community-comment-input-row input:focus { border-color: #64b67b; box-shadow: 0 0 0 3px rgba(37, 165, 82, .1); }
.sg-community-comment-input-row > button { display: grid; width: 35px; height: 35px; flex: 0 0 auto; place-items: center; border: 0; border-radius: 50%; background: #087f3e; color: #fff; cursor: pointer; }
.sg-community-comment-input-row > button:disabled { cursor: not-allowed; opacity: .45; }
.sg-community-comment-input-row svg { width: 15px; height: 15px; }
.sg-community-login-comment { width: 100%; min-height: 38px; border: 1px solid #cbe7d2; border-radius: 7px; background: #eaf8ee; color: #087f3e; cursor: pointer; font-size: 12px; font-weight: 800; }
.sg-community-load-more { display: flex; min-height: 43px; align-items: center; justify-content: center; gap: 8px; border: 1px solid #cbe7d2; border-radius: 8px; background: #fff; color: #087f3e; cursor: pointer; font-size: 12px; font-weight: 800; }
.sg-community-load-more:hover { background: #eaf8ee; }
.sg-community-load-more:disabled { cursor: wait; opacity: .65; }
.sg-community-end-note { margin: 2px 0; color: #8b9a90; font-size: 11px; text-align: center; }
.sg-community-panel { padding: 20px; }
.sg-community-panel-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.sg-community-text-button { color: #087f3e; }
.sg-community-category-list { display: grid; gap: 4px; margin-top: 16px; }
.sg-community-category-list button { display: flex; align-items: center; justify-content: space-between; min-height: 36px; padding: 0 10px; border: 0; border-radius: 6px; background: transparent; color: #687a6e; cursor: pointer; font-size: 12px; text-align: left; }
.sg-community-category-list button:hover { background: #f2f8f3; color: #087f3e; }
.sg-community-category-list button.active { background: #e5f6eb; color: #087f3e; font-weight: 800; }
.sg-community-category-list b { font-size: 12px; }
.sg-community-active-query { display: flex; align-items: center; gap: 7px; margin-top: 15px; padding: 9px 10px; border-radius: 6px; background: #f5faf6; color: #6d7e73; font-size: 11px; }
.sg-community-active-query svg { width: 14px; height: 14px; color: #087f3e; }
.sg-community-match-list { display: grid; gap: 10px; margin-top: 16px; }
.sg-community-match-card { padding: 12px; border: 1px solid #e0ebe2; border-radius: 9px; background: #fbfdfb; }
.sg-community-match-card > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 11px; }
.sg-community-match-card > header > button { display: flex; align-items: center; gap: 8px; min-width: 0; padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; text-align: left; }
.sg-community-match-card > header > button > span:last-child { display: grid; gap: 2px; }
.sg-community-match-card strong { overflow: hidden; color: #34493a; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.sg-community-match-card small { color: #91a096; font-size: 10px; }
.sg-community-match-card > header > b { flex: 0 0 auto; padding: 4px 6px; border-radius: 999px; background: #fff1d4; color: #95600a; font-size: 10px; }
.sg-community-match-line { display: flex; align-items: center; gap: 6px; margin: 7px 0 0; overflow: hidden; color: #748379; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; }
.sg-community-match-line svg { width: 13px; height: 13px; flex: 0 0 auto; color: #5e9b6e; }
.sg-community-match-description { display: -webkit-box; overflow: hidden; margin: 10px 0; color: #5b6c60; font-size: 11px; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.sg-community-match-action { display: flex; min-height: 32px; align-items: center; justify-content: center; width: 100%; margin-top: 12px; border: 0; border-radius: 6px; background: #087f3e; color: #fff; cursor: pointer; font-size: 11px; font-weight: 800; text-decoration: none; }
.sg-community-match-action:hover { background: #066d35; }
.sg-community-match-action.muted, .sg-community-match-action:disabled { background: #edf1ee; color: #87948b; cursor: not-allowed; }
.sg-community-panel-state { display: grid; min-height: 100px; place-items: center; align-content: center; gap: 7px; color: #7a8a80; font-size: 11px; text-align: center; }
.sg-community-panel-state p { margin: 0; }
.sg-community-panel-state svg { width: 24px; height: 24px; color: #71a983; }
.sg-community-panel-state button { min-height: 30px; padding: 0 11px; font-size: 11px; }
.sg-community-panel-state--error { color: #a33c3c; }
.sg-community-panel-link { width: 100%; justify-content: center; margin-top: 15px; padding-top: 14px; border-top: 1px solid #edf3ee; font-size: 11px; }
.sg-community-guideline-panel { background: #10271a; border-color: #10271a; }
.sg-community-guideline-panel .sg-community-eyebrow { color: #8de2a4; }
.sg-community-guideline-panel h2 { color: #fff; font-size: 17px; }
.sg-community-guideline-panel > p:last-child { margin: 11px 0 0; color: #b8cdbd; font-size: 12px; line-height: 1.6; }
@media (max-width: 960px) {
  .sg-community-hero { grid-template-columns: 1fr; }
  .sg-community-hero-action { justify-self: start; }
  .sg-community-layout { grid-template-columns: minmax(0, 1fr); }
  .sg-community-sidebar { position: static; grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .sg-community-guideline-panel { grid-column: 1 / -1; }
}
@media (max-width: 680px) {
  .sg-community-main { width: min(100% - 24px, 560px); padding: 82px 0 56px; }
  .sg-community-hero { grid-template-columns: 1fr; gap: 18px; padding: 22px 18px 18px; border-radius: 10px; }
  .sg-community-hero h1 { font-size: 34px; }
  .sg-community-hero-description { margin: 13px 0 19px; font-size: 14px; }
  .sg-community-search { align-items: stretch; flex-wrap: wrap; gap: 4px; padding: 5px; }
  .sg-community-search-icon { align-self: center; }
  .sg-community-search input { height: 38px; }
  .sg-community-search button { width: 100%; min-height: 36px; }
  .sg-community-quick-topics { gap: 6px; }
  .sg-community-quick-topics > span { width: 100%; }
  .sg-community-hero-action { width: 100%; padding: 12px 0 0; border-top: 1px solid #e0ebe2; border-left: 0; }
  .sg-community-section-nav { margin: 12px 0 14px; }
  .sg-community-section-nav a { flex: 1; justify-content: center; padding: 10px 8px; font-size: 12px; }
  .sg-community-mobile-toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; color: #77877c; font-size: 11px; }
  .sg-community-mobile-toolbar button { padding: 7px 10px; border: 1px solid #cbe7d2; border-radius: 6px; background: #fff; color: #087f3e; cursor: pointer; font-size: 11px; font-weight: 800; }
  .sg-community-sidebar { display: none; grid-template-columns: 1fr; }
  .sg-community-sidebar.is-open { display: grid; margin-bottom: 14px; }
  .sg-community-guideline-panel { grid-column: auto; }
  .sg-community-feed-heading { align-items: flex-start; padding: 17px; }
  .sg-community-feed-heading h2 { font-size: 19px; }
  .sg-community-result-count { display: none; }
  .sg-community-post-header { padding: 15px 14px 10px; }
  .sg-community-post-copy { width: calc(100% - 28px); margin-right: 14px; margin-left: 14px; }
  .sg-community-post-stats { padding-right: 14px; padding-left: 14px; }
  .sg-community-post-actions { padding-right: 8px; padding-left: 8px; }
  .sg-community-post-actions button { gap: 4px; font-size: 11px; }
  .sg-community-comments { padding-right: 14px; padding-left: 14px; }
}
</style>

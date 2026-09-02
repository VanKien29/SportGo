<template>
  <div class="venue-posts-tab" aria-labelledby="venue-posts-heading">
    <!-- Header & Flat Filter Bar -->
    <header class="venue-posts-header">
      <div class="venue-posts-header-main">
        <h2 id="venue-posts-heading" class="venue-posts-title">Bài đăng từ {{ venueName }}</h2>
        <p class="venue-posts-subtitle">Cập nhật lịch hoạt động, ưu đãi và thông tin mới nhất từ cụm sân.</p>
      </div>

      <!-- Search Input -->
      <form class="venue-posts-search-form" @submit.prevent="handleSearchSubmit">
        <div class="venue-search-field">
          <AppIcon name="search" :size="15" class="venue-search-icon" />
          <input
            v-model.trim="searchInput"
            type="text"
            placeholder="Tìm kiếm bài viết..."
            aria-label="Tìm kiếm bài viết"
            class="venue-search-input"
          />
          <button
            v-if="searchInput"
            type="button"
            class="venue-search-clear-btn"
            aria-label="Xóa tìm kiếm"
            @click="clearSearch"
          >
            <AppIcon name="x" :size="13" />
          </button>
        </div>
      </form>
    </header>

    <!-- Flat Category Filter Bar (No badges, No pills, No chips) -->
    <div class="venue-posts-filter-bar" role="tablist" aria-label="Bộ lọc chuyên mục">
      <button
        v-for="cat in categoryList"
        :key="cat.value"
        type="button"
        role="tab"
        :aria-selected="selectedCategory === cat.value"
        class="venue-filter-tab-btn"
        :class="{ 'is-active': selectedCategory === cat.value }"
        @click="selectCategory(cat.value)"
      >
        {{ cat.label }}
      </button>
      <button
        v-if="selectedCategory || currentKeyword"
        type="button"
        class="venue-filter-reset-btn"
        @click="resetAllFilters"
      >
        Xóa bộ lọc
      </button>
    </div>

    <!-- Loading State: Minimalist Skeleton Cards -->
    <div v-if="loading" class="venue-posts-loading-list" aria-live="polite">
      <div v-for="n in 2" :key="`skel-${n}`" class="venue-post-skeleton-card">
        <div class="skeleton-header">
          <div class="skeleton-avatar"></div>
          <div class="skeleton-meta">
            <div class="skeleton-line skeleton-line-title"></div>
            <div class="skeleton-line skeleton-line-sub"></div>
          </div>
        </div>
        <div class="skeleton-body">
          <div class="skeleton-line skeleton-line-full"></div>
          <div class="skeleton-line skeleton-line-long"></div>
        </div>
        <div class="skeleton-media"></div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="venue-post-state venue-post-state--error" role="alert">
      <AppIcon name="alert" :size="24" />
      <span class="venue-state-title">Không thể tải bài đăng</span>
      <p class="venue-state-desc">{{ error }}</p>
      <button type="button" class="venue-btn-flat" @click="fetchPosts({ page: 1 })">Thử lại</button>
    </div>

    <!-- Empty State -->
    <div v-else-if="!posts.length" class="venue-post-state">
      <AppIcon name="newspaper" :size="26" />
      <span class="venue-state-title">
        {{ currentKeyword || selectedCategory ? 'Không tìm thấy bài viết phù hợp' : 'Sân chưa có bài đăng công khai' }}
      </span>
      <p class="venue-state-desc">
        {{ currentKeyword || selectedCategory ? 'Hãy thử đổi từ khóa tìm kiếm hoặc chọn danh mục khác.' : 'Các cập nhật về lịch hoạt động và thông báo từ sân sẽ xuất hiện tại đây.' }}
      </p>
      <button
        v-if="currentKeyword || selectedCategory"
        type="button"
        class="venue-btn-flat"
        @click="resetAllFilters"
      >
        Xem tất cả bài đăng
      </button>
    </div>

    <!-- Post Stream -->
    <div v-else class="venue-post-stream">
      <article v-for="post in posts" :key="post.id" class="venue-post-card">
        <!-- Post Header -->
        <header class="venue-post-header">
          <div class="venue-post-author-wrap">
            <div
              class="venue-author-avatar"
              :style="!post.author?.avatar_url ? { backgroundColor: getAvatarColorHex(post.author?.full_name || post.author?.username || venueName) } : {}"
            >
              <img
                v-if="post.author?.avatar_url"
                :src="assetUrl(post.author.avatar_url)"
                :alt="post.author.full_name || post.author.username || 'Tác giả'"
                @error="onAvatarError($event, post)"
              />
              <span v-else>{{ getAvatarInitial(post.author?.full_name || post.author?.username || venueName) }}</span>
            </div>
            <div class="venue-author-info">
              <span class="venue-author-name">{{ post.author?.full_name || post.author?.username || venueName }}</span>
              <span class="venue-post-meta-text">
                {{ formatCategory(post.post_type) }} · {{ timeAgo(post.published_at || post.created_at) }}
              </span>
            </div>
          </div>

          <!-- Options Menu -->
          <div class="venue-post-menu-wrap">
            <button
              type="button"
              class="venue-menu-btn"
              :aria-expanded="openMenuPostId === post.id"
              aria-label="Tùy chọn bài viết"
              @click.stop="toggleMenu(post.id)"
            >
              <AppIcon name="moreHorizontal" :size="18" />
            </button>
            <div v-if="openMenuPostId === post.id" class="venue-post-dropdown" role="menu" @click.stop>
              <button type="button" role="menuitem" class="venue-dropdown-item" @click="copyPostLink(post)">
                <AppIcon name="copy" :size="14" />
                <span>Sao chép liên kết</span>
              </button>
              <button type="button" role="menuitem" class="venue-dropdown-item" @click="openReport(post)">
                <AppIcon name="alert" :size="14" />
                <span>Báo cáo bài viết</span>
              </button>
            </div>
          </div>
        </header>

        <!-- Post Content Body -->
        <div class="venue-post-body">
          <!-- Post Title -->
          <h3
            v-if="post.title && !isTitleDuplicated(post)"
            class="venue-post-card-title"
            @click="openArticleModal(post)"
          >
            {{ post.title }}
          </h3>

          <!-- Hashtags as Flat Text -->
          <div v-if="post.hashtags?.length" class="venue-post-hashtags">
            <span v-for="tag in post.hashtags.slice(0, 5)" :key="tag.id || tag.name" class="venue-hashtag-item">
              #{{ tag.name }}
            </span>
          </div>

          <!-- Description Text Excerpt -->
          <p class="venue-post-excerpt">{{ getExcerpt(post) }}</p>

          <!-- Read Full Article Link -->
          <button
            v-if="hasDetailedContent(post)"
            type="button"
            class="venue-read-more-btn"
            @click="openArticleModal(post)"
          >
            Xem toàn bộ bài viết
          </button>
        </div>

        <!-- Post Media Gallery (Flat, Adaptive Grid) -->
        <div
          v-if="getPostMedia(post).length"
          class="venue-post-media"
          :class="`venue-post-media--${Math.min(getPostMedia(post).length, 4)}`"
        >
          <button
            v-for="(image, imageIndex) in getPostMedia(post).slice(0, 4)"
            :key="`${post.id}-img-${imageIndex}`"
            type="button"
            class="venue-media-item-btn"
            @click="openLightbox(getPostMedia(post), imageIndex)"
          >
            <img
              :src="image"
              :alt="`${post.title || 'Ảnh bài viết'} ${imageIndex + 1}`"
              class="venue-media-img"
              @error="handleImageError"
            />
            <span v-if="imageIndex === 3 && getPostMedia(post).length > 4" class="venue-media-more-overlay">
              +{{ getPostMedia(post).length - 4 }}
            </span>
          </button>
        </div>

        <!-- Post Stats Row (Flat High Contrast Text) -->
        <div class="venue-post-stats-row">
          <span class="venue-stat-item">
            <AppIcon name="heart" :size="13" /> {{ post.like_count || 0 }} lượt thích
          </span>
          <span class="venue-stat-item" @click="toggleComments(post)">
            <AppIcon name="messageCircle" :size="13" /> {{ post.comment_count || 0 }} bình luận
          </span>
          <span class="venue-stat-item">
            <AppIcon name="eye" :size="13" /> {{ post.view_count || 0 }} lượt xem
          </span>
        </div>

        <!-- Post Actions Bar -->
        <div v-if="isPostPublished(post)" class="venue-post-actions-bar">
          <button
            type="button"
            class="venue-action-btn"
            :class="{ 'is-liked': Boolean(post.is_liked) }"
            :disabled="likingPostId === post.id"
            @click="toggleLike(post)"
          >
            <AppIcon name="heart" :size="15" />
            <span>{{ post.is_liked ? 'Đã thích' : 'Thích' }}</span>
          </button>
          <button
            type="button"
            class="venue-action-btn"
            :class="{ 'is-active': commentsOpen[post.id] }"
            @click="toggleComments(post)"
          >
            <AppIcon name="messageCircle" :size="15" />
            <span>Bình luận</span>
          </button>
          <button
            type="button"
            class="venue-action-btn"
            @click="copyPostLink(post)"
          >
            <AppIcon name="share" :size="15" />
            <span>Chia sẻ</span>
          </button>
          <button
            v-if="hasDetailedContent(post)"
            type="button"
            class="venue-action-btn"
            @click="openArticleModal(post)"
          >
            <AppIcon name="fileText" :size="15" />
            <span>Chi tiết</span>
          </button>
        </div>

        <!-- Comments Accordion Section -->
        <section v-if="commentsOpen[post.id]" class="venue-comments-section" aria-label="Bình luận bài viết">
          <div v-if="detailsLoading[post.id]" class="venue-comments-loading">
            <span class="venue-spinner-sm" aria-hidden="true"></span>
            <span>Đang tải bình luận...</span>
          </div>
          <template v-else>
            <!-- Comment List -->
            <div v-if="post.top_level_comments?.length" class="venue-comments-list">
              <article
                v-for="comment in visibleComments(post)"
                :key="comment.id"
                class="venue-comment-row"
              >
                <div
                  class="venue-comment-avatar"
                  :style="!comment.user?.avatar_url ? { backgroundColor: getAvatarColorHex(comment.user?.full_name || comment.user?.username || 'U') } : {}"
                >
                  <img
                    v-if="comment.user?.avatar_url"
                    :src="assetUrl(comment.user.avatar_url)"
                    :alt="comment.user?.full_name || 'U'"
                    class="venue-avatar-img"
                    @error="comment.user.avatar_url = null"
                  />
                  <span v-else>{{ getAvatarInitial(comment.user?.full_name || comment.user?.username || 'U') }}</span>
                </div>
                <div class="venue-comment-main">
                  <div class="venue-comment-bubble">
                    <span class="venue-comment-author">{{ comment.user?.full_name || comment.user?.username || 'Khách hàng' }}</span>
                    <p class="venue-comment-text">{{ comment.content }}</p>
                  </div>
                  <span class="venue-comment-time">{{ timeAgo(comment.created_at) }}</span>
                </div>
              </article>

              <button
                v-if="post.top_level_comments.length > commentPreviewLimit && !showAllComments[post.id]"
                type="button"
                class="venue-comments-more-btn"
                @click="showAllComments[post.id] = true"
              >
                Xem thêm {{ post.top_level_comments.length - commentPreviewLimit }} bình luận khác
              </button>
            </div>
            <p v-else class="venue-comments-empty-text">Chưa có bình luận nào. Hãy bắt đầu cuộc trò chuyện.</p>

            <!-- Add Comment Input Form -->
            <form v-if="user" class="venue-comment-input-form" @submit.prevent="submitComment(post)">
              <div
                class="venue-comment-avatar"
                :style="!currentUserAvatarUrl ? { backgroundColor: getAvatarColorHex(user.fullName || user.username || 'U') } : {}"
              >
                <img
                  v-if="currentUserAvatarUrl"
                  :src="currentUserAvatarUrl"
                  :alt="user.fullName || 'U'"
                  class="venue-avatar-img"
                  @error="onCurrentUserAvatarError"
                />
                <span v-else>{{ getAvatarInitial(user.fullName || user.username || 'U') }}</span>
              </div>
              <div class="venue-comment-input-wrap">
                <input
                  v-model.trim="commentDrafts[post.id]"
                  type="text"
                  maxlength="1000"
                  placeholder="Viết bình luận của bạn..."
                  :disabled="commentSubmitting[post.id]"
                  class="venue-comment-input"
                />
                <button
                  type="submit"
                  class="venue-comment-submit-btn"
                  aria-label="Gửi bình luận"
                  :disabled="commentSubmitting[post.id] || !commentDrafts[post.id]?.trim()"
                >
                  <AppIcon name="send" :size="14" />
                </button>
              </div>
            </form>
            <div v-else class="venue-comment-guest-wrap">
              <button type="button" class="venue-comment-login-btn" @click="goToLogin">
                Đăng nhập để gửi bình luận
              </button>
            </div>
          </template>
        </section>
      </article>

      <!-- Load More Button -->
      <div v-if="pagination.current_page < pagination.last_page" class="venue-load-more-wrap">
        <button
          type="button"
          class="venue-load-more-btn"
          :disabled="loadingMore"
          @click="loadMore"
        >
          <span v-if="loadingMore" class="venue-spinner-sm"></span>
          <span>{{ loadingMore ? 'Đang tải...' : 'Xem thêm bài đăng' }}</span>
        </button>
      </div>

      <div v-if="loadMoreError" class="venue-load-more-error" role="alert">
        <span>{{ loadMoreError }}</span>
        <button type="button" class="venue-retry-link" @click="loadMore">Thử lại</button>
      </div>
    </div>

    <!-- Full Article Modal (Minimalist Article Reader) -->
    <Teleport to="body">
      <div
        v-if="modalPost"
        class="venue-modal-overlay"
        role="presentation"
        @click.self="closeArticleModal"
      >
        <div class="venue-article-modal" role="dialog" aria-modal="true" :aria-label="modalPost.title || 'Chi tiết bài viết'">
          <!-- Modal Header -->
          <header class="venue-modal-header">
            <div class="venue-modal-meta">
              <span class="venue-modal-category">{{ formatCategory(modalPost.post_type) }}</span>
              <span class="venue-modal-date">{{ formatDate(modalPost.published_at || modalPost.created_at) }}</span>
            </div>
            <button
              type="button"
              class="venue-modal-close-btn"
              aria-label="Đóng"
              @click="closeArticleModal"
            >
              <AppIcon name="x" :size="18" />
            </button>
          </header>

          <!-- Modal Scrollable Content -->
          <div class="venue-modal-scroll-body">
            <h2 class="venue-modal-title">{{ modalPost.title }}</h2>

            <!-- Author info in modal -->
            <div class="venue-modal-author-row">
              <div
                class="venue-author-avatar"
                :style="!modalPost.author?.avatar_url ? { backgroundColor: getAvatarColorHex(modalPost.author?.full_name || modalPost.author?.username || venueName) } : {}"
              >
                <img
                  v-if="modalPost.author?.avatar_url"
                  :src="assetUrl(modalPost.author.avatar_url)"
                  :alt="modalPost.author.full_name || 'Tác giả'"
                  @error="onAvatarError($event, modalPost)"
                />
                <span v-else>{{ getAvatarInitial(modalPost.author?.full_name || modalPost.author?.username || venueName) }}</span>
              </div>
              <div class="venue-author-info">
                <span class="venue-author-name">{{ modalPost.author?.full_name || modalPost.author?.username || venueName }}</span>
                <span class="venue-post-meta-text">{{ venueName }}</span>
              </div>
            </div>

            <!-- Rich HTML Article Body -->
            <div
              v-if="modalPost.content"
              class="venue-article-rich-content"
              v-html="modalPost.content"
            ></div>
            <p v-else class="venue-post-excerpt">{{ modalPost.short_description }}</p>

            <!-- Modal Image Gallery (Adaptive Grid) -->
            <div
              v-if="getPostMedia(modalPost).length"
              class="venue-modal-gallery"
              :class="`venue-modal-gallery--${Math.min(getPostMedia(modalPost).length, 4)}`"
            >
              <button
                v-for="(img, idx) in getPostMedia(modalPost)"
                :key="`modal-img-${idx}`"
                type="button"
                class="venue-modal-media-btn"
                aria-label="Xem ảnh bài viết"
                @click="openLightbox(getPostMedia(modalPost), idx)"
              >
                <img
                  :src="img"
                  :alt="`Hình ảnh bài viết ${idx + 1}`"
                  class="venue-modal-gallery-img"
                />
              </button>
            </div>

            <!-- Hashtags -->
            <div v-if="modalPost.hashtags?.length" class="venue-post-hashtags" style="margin-top: 16px;">
              <span v-for="tag in modalPost.hashtags" :key="tag.id || tag.name" class="venue-hashtag-item">
                #{{ tag.name }}
              </span>
            </div>

            <!-- Modal Actions & Stats -->
            <div class="venue-modal-actions-row">
              <button
                type="button"
                class="venue-action-btn"
                :class="{ 'is-liked': Boolean(modalPost.is_liked) }"
                @click="toggleLike(modalPost)"
              >
                <AppIcon name="heart" :size="15" />
                <span>{{ modalPost.is_liked ? 'Đã thích' : 'Thích' }} ({{ modalPost.like_count || 0 }})</span>
              </button>
              <button
                type="button"
                class="venue-action-btn"
                @click="copyPostLink(modalPost)"
              >
                <AppIcon name="copy" :size="15" />
                <span>Sao chép liên kết</span>
              </button>
            </div>

            <!-- Modal Comments Section -->
            <div class="venue-modal-comments">
              <h4 class="venue-modal-comments-heading">Bình luận ({{ modalPost.comment_count || 0 }})</h4>
              
              <div v-if="modalPost.top_level_comments?.length" class="venue-comments-list">
                <article
                  v-for="comment in modalPost.top_level_comments"
                  :key="`modal-cmt-${comment.id}`"
                  class="venue-comment-row"
                >
                  <div
                    class="venue-comment-avatar"
                    :style="!comment.user?.avatar_url ? { backgroundColor: getAvatarColorHex(comment.user?.full_name || comment.user?.username || 'U') } : {}"
                  >
                    <img
                      v-if="comment.user?.avatar_url"
                      :src="assetUrl(comment.user.avatar_url)"
                      :alt="comment.user.full_name || 'U'"
                      class="venue-avatar-img"
                      @error="comment.user.avatar_url = null"
                    />
                    <span v-else>{{ getAvatarInitial(comment.user?.full_name || comment.user?.username || 'U') }}</span>
                  </div>
                  <div class="venue-comment-main">
                    <div class="venue-comment-bubble">
                      <span class="venue-comment-author">{{ comment.user?.full_name || comment.user?.username || 'Khách hàng' }}</span>
                      <p class="venue-comment-text">{{ comment.content }}</p>
                    </div>
                    <span class="venue-comment-time">{{ timeAgo(comment.created_at) }}</span>
                  </div>
                </article>
              </div>
              <p v-else class="venue-comments-empty-text">Chưa có bình luận nào.</p>

              <!-- Modal comment form -->
              <form v-if="user" class="venue-comment-input-form" style="margin-top: 16px;" @submit.prevent="submitComment(modalPost)">
                <div
                  class="venue-comment-avatar"
                  :style="!currentUserAvatarUrl ? { backgroundColor: getAvatarColorHex(user.fullName || user.username || 'U') } : {}"
                >
                  <img
                    v-if="currentUserAvatarUrl"
                    :src="currentUserAvatarUrl"
                    :alt="user.fullName || 'U'"
                    class="venue-avatar-img"
                    @error="onCurrentUserAvatarError"
                  />
                  <span v-else>{{ getAvatarInitial(user.fullName || user.username || 'U') }}</span>
                </div>
                <div class="venue-comment-input-wrap">
                  <input
                    v-model.trim="commentDrafts[modalPost.id]"
                    type="text"
                    maxlength="1000"
                    placeholder="Viết bình luận của bạn..."
                    :disabled="commentSubmitting[modalPost.id]"
                    class="venue-comment-input"
                  />
                  <button
                    type="submit"
                    class="venue-comment-submit-btn"
                    aria-label="Gửi bình luận"
                    :disabled="commentSubmitting[modalPost.id] || !commentDrafts[modalPost.id]?.trim()"
                  >
                    <AppIcon name="send" :size="14" />
                  </button>
                </div>
              </form>
              <div v-else class="venue-comment-guest-wrap" style="margin-top: 16px;">
                <button type="button" class="venue-comment-login-btn" @click="goToLogin">
                  Đăng nhập để gửi bình luận
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Report Modal -->
    <ReportModal
      :is-open="Boolean(reportTarget)"
      target-type="venue_post"
      :target-id="reportTarget?.entity_id || reportTarget?.id || ''"
      :target-name="reportTarget?.title || `Bài đăng của ${venueName}`"
      @close="reportTarget = null"
      @success="handleReportSuccess"
    />

    <!-- Lightbox Modal (Minimalist Image Viewer) -->
    <Teleport to="body">
      <div
        v-if="lightboxImages.length"
        class="venue-lightbox"
        role="presentation"
        @click.self="closeLightbox"
      >
        <section role="dialog" aria-modal="true" aria-label="Xem ảnh bài viết">
          <button type="button" class="venue-lightbox-close" aria-label="Đóng ảnh" @click="closeLightbox">
            <AppIcon name="x" :size="20" />
          </button>
          <button
            v-if="lightboxImages.length > 1"
            type="button"
            class="venue-lightbox-nav venue-lightbox-nav--prev"
            aria-label="Ảnh trước"
            @click="moveLightbox(-1)"
          >
            <AppIcon name="chevronLeft" :size="22" />
          </button>
          <img :src="lightboxImages[lightboxIndex]" :alt="`Ảnh bài viết ${lightboxIndex + 1}`" />
          <button
            v-if="lightboxImages.length > 1"
            type="button"
            class="venue-lightbox-nav venue-lightbox-nav--next"
            aria-label="Ảnh sau"
            @click="moveLightbox(1)"
          >
            <AppIcon name="chevronRight" :size="22" />
          </button>
          <span v-if="lightboxImages.length > 1" class="venue-lightbox-count">
            {{ lightboxIndex + 1 }} / {{ lightboxImages.length }}
          </span>
        </section>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import AppIcon from './AppIcon.vue';
import ReportModal from './ReportModal.vue';
import { api } from '../services/api.js';
import { getAuth } from '../stores/auth.js';
import { getAvatarColorHex, getAvatarInitial } from '../utils/avatar.js';

const props = defineProps({
  venueId: { type: [String, Number], required: true },
  venueName: { type: String, default: 'cụm sân' },
});

const emit = defineEmits(['update:count', 'total-posts']);

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
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

const searchInput = ref('');
const currentKeyword = ref('');
const selectedCategory = ref('');

const openMenuPostId = ref(null);
const reportTarget = ref(null);
const likingPostId = ref(null);
const commentsOpen = reactive({});
const detailsLoading = reactive({});
const commentDrafts = reactive({});
const commentSubmitting = reactive({});
const showAllComments = reactive({});
const commentPreviewLimit = 3;

const modalPost = ref(null);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);
const failedImageUrls = reactive(new Set());

const currentUserAvatarUrl = computed(() => {
  const path = user?.avatar_url || user?.avatarUrl || user?.avatar;
  if (!path) return null;
  return assetUrl(path);
});

function onCurrentUserAvatarError() {
  if (user) {
    user.avatar_url = null;
    user.avatarUrl = null;
    user.avatar = null;
  }
}

const categoryList = [
  { value: '', label: 'Tất cả' },
  { value: 'promotion', label: 'Khuyến mãi' },
  { value: 'tournament', label: 'Giải đấu' },
  { value: 'news', label: 'Tin tức' },
  { value: 'notice', label: 'Thông báo' },
  { value: 'recruitment', label: 'Tuyển dụng' },
];

const categoryLabels = {
  promotion: 'Khuyến mãi',
  tournament: 'Giải đấu',
  news: 'Tin tức',
  notice: 'Thông báo',
  recruitment: 'Tuyển dụng',
};

function formatCategory(type) {
  return categoryLabels[type] || 'Tin tức & Thông báo';
}

function selectCategory(cat) {
  if (selectedCategory.value === cat) return;
  selectedCategory.value = cat;
  fetchPosts({ page: 1 });
}

function handleSearchSubmit() {
  currentKeyword.value = searchInput.value.trim();
  fetchPosts({ page: 1 });
}

function clearSearch() {
  searchInput.value = '';
  if (currentKeyword.value) {
    currentKeyword.value = '';
    fetchPosts({ page: 1 });
  }
}

function resetAllFilters() {
  searchInput.value = '';
  currentKeyword.value = '';
  selectedCategory.value = '';
  fetchPosts({ page: 1 });
}

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

    if (selectedCategory.value) {
      params.append('post_type', selectedCategory.value);
    }

    if (currentKeyword.value) {
      params.append('keyword', currentKeyword.value);
    }

    const response = await api(`/api/venue-posts?${params.toString()}`);
    if (requestId !== requestSequence.value) return;

    const incoming = Array.isArray(response.data) ? response.data : [];
    posts.value = append ? [...posts.value, ...incoming] : incoming;
    pagination.value = {
      current_page: Number(response.current_page || page),
      last_page: Number(response.last_page || 1),
      total: Number(response.total || incoming.length),
    };

    emit('update:count', pagination.value.total);
    emit('total-posts', pagination.value.total);
  } catch (requestError) {
    if (requestId !== requestSequence.value) return;
    const message = requestError.message || 'Không thể tải bài đăng của cụm sân.';
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
    post.content = detail.content || post.content;
  } catch (requestError) {
    commentsOpen[post.id] = false;
    toast.error(requestError.message || 'Không thể tải bình luận của bài viết.');
  } finally {
    detailsLoading[post.id] = false;
  }
}

function isPostPublished(post) {
  if (!post) return false;
  if (post.is_deleted || post.deleted_at) return false;
  return !post.status || post.status === 'published' || post.status === 'approved';
}

async function toggleComments(post) {
  if (!isPostPublished(post)) return;
  commentsOpen[post.id] = !commentsOpen[post.id];
  if (commentsOpen[post.id]) {
    await ensurePostDetails(post);
  }
}

async function toggleLike(post) {
  if (!isPostPublished(post)) return;
  if (!user) {
    toast.info('Vui lòng đăng nhập để thích bài viết.');
    goToLogin();
    return;
  }
  if (likingPostId.value === post.id) return;

  const previousLiked = Boolean(post.is_liked);
  const previousCount = Number(post.like_count || 0);
  const nextLiked = !previousLiked;

  post.is_liked = nextLiked;
  post.like_count = Math.max(0, previousCount + (nextLiked ? 1 : -1));
  likingPostId.value = post.id;

  try {
    const response = await api(`/api/venue-posts/${post.entity_id || post.id}/likes`, { method: 'POST' });
    post.is_liked = Boolean(response.data?.is_liked);
    post.like_count = Number(response.data?.like_count ?? post.like_count ?? 0);
  } catch (requestError) {
    post.is_liked = previousLiked;
    post.like_count = previousCount;
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

    if (!Array.isArray(post.top_level_comments)) {
      post.top_level_comments = [];
    }

    post.top_level_comments.unshift({
      id: response.data?.id || `new-${Date.now()}`,
      content,
      created_at: new Date().toISOString(),
      user: response.data?.user || {
        id: user.id,
        full_name: user.fullName,
        username: user.username,
        avatar_url: user.avatar_url || user.avatarUrl,
      },
    });

    post.comment_count = Number(post.comment_count || 0) + 1;
    commentDrafts[post.id] = '';
    toast.success('Đã gửi bình luận.');
  } catch (requestError) {
    toast.error(requestError.message || 'Không thể gửi bình luận.');
  } finally {
    commentSubmitting[post.id] = false;
  }
}

async function openArticleModal(post) {
  modalPost.value = post;
  await ensurePostDetails(post);
}

function closeArticleModal() {
  modalPost.value = null;
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
  toast.success('Báo cáo của bạn đã được gửi thành công.');
}

function copyPostLink(post) {
  openMenuPostId.value = null;
  const link = `${window.location.origin}/venues/${props.venueId}?tab=posts`;
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(link).then(() => {
      toast.success('Đã sao chép liên kết bài viết.');
    }).catch(() => {
      toast.info('Đã sao chép liên kết.');
    });
  } else {
    toast.success('Đã sao chép liên kết bài viết.');
  }
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
  if (!value) return '';
  const parsed = new DOMParser().parseFromString(String(value), 'text/html');
  return (parsed.body.textContent || '').trim();
}

function getExcerpt(post) {
  const text = post.short_description || plainText(post.content);
  return text || 'Thông tin từ cụm sân SportGo.';
}

function hasDetailedContent(post) {
  if (!post.content) return false;
  const raw = plainText(post.content);
  const desc = plainText(post.short_description);
  return raw.length > desc.length + 30 || post.content.includes('<img') || post.content.includes('<table');
}

function isTitleDuplicated(post) {
  const title = plainText(post.title).replace(/\.{3}$/, '').trim().toLowerCase();
  const desc = plainText(post.short_description || post.content).trim().toLowerCase();
  return Boolean(title && desc.startsWith(title) && title.length > 20);
}

function getPostMedia(post) {
  const media = Array.isArray(post?.media) ? post.media : [];
  const sorted = [...media].sort((left, right) => {
    if (left.collection === 'thumbnail' && right.collection !== 'thumbnail') return -1;
    if (right.collection === 'thumbnail' && left.collection !== 'thumbnail') return 1;
    return Number(left.sort_order || 0) - Number(right.sort_order || 0);
  });
  return [...new Set(sorted
    .map((item) => assetUrl(item.url || item.file_url || item.full_url || item.file_path || item.path))
    .filter(Boolean)
    .filter((url) => !failedImageUrls.has(url)))];
}

function assetUrl(path) {
  if (!path || /^https?:\/\//.test(path) || path.startsWith('/')) return path || '';
  return `/storage/${path}`;
}

function handleImageError(event) {
  const image = event.currentTarget;
  const failedUrl = image?.getAttribute('src');
  if (failedUrl) failedImageUrls.add(failedUrl);
}

function onAvatarError(e, post) {
  if (post?.author) post.author.avatar_url = null;
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
  if (event.key === 'Escape') {
    if (lightboxImages.value.length) closeLightbox();
    else if (modalPost.value) closeArticleModal();
  }
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

<style scoped src="../../css/components/client-venue-posts-tab.css"></style>

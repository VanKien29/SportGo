<template>
  <div class="sg-client-page sg-community-detail-page" @click="closeMenus">
    <PublicNavbar />

    <main class="sg-client-reading-shell">
      <nav class="sg-community-breadcrumb" aria-label="Điều hướng bài viết">
        <router-link :to="{ name: 'ClientCommunityList' }">
          <AppIcon name="chevronLeft" size="16" />
          Quay lại cộng đồng
        </router-link>
      </nav>

      <div v-if="loading" class="sg-client-state" aria-live="polite">
        <span class="sg-community-spinner" aria-hidden="true"></span>
        <strong>Đang tải bài viết...</strong>
      </div>

      <div v-else-if="error" class="sg-client-state sg-community-state-error" role="alert">
        <AppIcon name="alert" size="30" />
        <strong>Không thể mở bài viết</strong>
        <p>{{ error }}</p>
        <button class="sg-client-button" type="button" @click="loadPost">Thử lại</button>
      </div>

      <article v-else-if="post" class="sg-client-card sg-community-post-detail">
        <header class="sg-community-post-header">
          <button class="sg-community-author" type="button" @click="openAuthor">
            <span class="sg-community-avatar">
              <img v-if="post.author?.avatar_url" :src="post.author.avatar_url" :alt="authorName" />
              <span v-else>{{ initials(authorName) }}</span>
            </span>
            <span class="sg-community-author-copy">
              <strong class="client-author-line">
                {{ authorName }}
                <ClientAuthorBadges :badges="post.author_badges" />
              </strong>
              <small>
                {{ timeAgo(post.created_at) }}
                <span aria-hidden="true">·</span>
                {{ categoryLabel(post.post_type) }}
              </small>
            </span>
          </button>

          <div class="sg-community-menu" @click.stop>
            <button
              class="sg-client-icon-button"
              type="button"
              title="Tùy chọn bài viết"
              aria-label="Mở tùy chọn bài viết"
              :aria-expanded="showPostMenu"
              @click="showPostMenu = !showPostMenu"
            >
              <AppIcon name="moreHorizontal" size="20" />
            </button>
            <div v-if="showPostMenu" class="sg-community-menu-panel">
              <button type="button" @click="reportPost">
                <AppIcon name="alert" size="17" />
                Báo cáo bài viết
              </button>
            </div>
          </div>
        </header>

        <section class="sg-community-post-copy">
          <div v-if="post.venueCluster?.name" class="sg-community-venue-chip">
            <AppIcon name="mapPin" size="15" />
            {{ post.venueCluster.name }}
          </div>
          <h1 v-if="post.title">{{ post.title }}</h1>
          <div class="sg-community-rich-text" v-html="post.content"></div>
        </section>

        <button
          v-if="thumbnailImage"
          class="sg-community-cover"
          type="button"
          title="Xem ảnh bài viết"
          @click="openImage(normalizeImage(thumbnailImage))"
        >
          <img :src="normalizeImage(thumbnailImage)" :alt="post.title || 'Ảnh bài viết'" @error="handleImageError" />
        </button>

        <section v-if="galleryImages.length" class="sg-community-gallery" aria-label="Thư viện ảnh bài viết">
          <button
            v-for="(image, index) in galleryImages"
            :key="image.id || image.file_path || index"
            type="button"
            @click="openImage(normalizeImage(image))"
          >
            <img
              :src="normalizeImage(image)"
              :alt="`${post.title || 'Bài viết'} - ảnh ${index + 1}`"
              loading="lazy"
              @error="handleImageError"
            />
          </button>
        </section>

        <div class="sg-community-stats">
          <button type="button" :disabled="!Number(post.like_count)" @click="showLikers = true">
            {{ Number(post.like_count || 0).toLocaleString('vi-VN') }} lượt thích
          </button>
          <div>
            <button type="button" @click="focusComment">
              {{ Number(post.comment_count || 0).toLocaleString('vi-VN') }} bình luận
            </button>
            <span>{{ Number(post.view_count || 0).toLocaleString('vi-VN') }} lượt xem</span>
          </div>
        </div>

        <div class="sg-community-actions" aria-label="Tương tác bài viết">
          <button
            v-if="likesAvailable"
            type="button"
            :class="{ active: isLiked }"
            :disabled="submittingLike"
            @click="toggleLike"
          >
            <AppIcon name="heart" size="19" />
            {{ isLiked ? 'Đã thích' : 'Thích' }}
          </button>
          <button type="button" @click="focusComment">
            <AppIcon name="messageCircle" size="19" />
            Bình luận
          </button>
          <button type="button" @click="copyLink">
            <AppIcon name="share" size="19" />
            Chia sẻ
          </button>
        </div>

        <section class="sg-community-comments" aria-label="Bình luận bài viết">
          <div class="sg-community-comment-composer">
            <span class="sg-community-avatar sg-community-avatar-small">
              <img v-if="currentUser?.avatar_url" :src="currentUser.avatar_url" :alt="currentUserName" />
              <span v-else>{{ initials(currentUserName) }}</span>
            </span>
            <form @submit.prevent="submitComment">
              <label class="sg-client-sr-only" for="community-comment">Viết bình luận</label>
              <textarea
                id="community-comment"
                ref="commentInput"
                v-model="newComment"
                rows="1"
                :placeholder="currentUser ? 'Viết bình luận...' : 'Đăng nhập để bình luận'"
                :disabled="!currentUser || submittingComment"
                @input="resizeComment"
              ></textarea>
              <button
                type="submit"
                title="Gửi bình luận"
                aria-label="Gửi bình luận"
                :disabled="!currentUser || !newComment.trim() || submittingComment"
              >
                <AppIcon name="send" size="18" />
              </button>
            </form>
          </div>

          <p v-if="!currentUser" class="sg-community-login-hint">
            <router-link :to="{ name: 'login', query: { redirect: $route.fullPath } }">Đăng nhập</router-link>
            để thích, bình luận hoặc báo cáo nội dung.
          </p>

          <div v-if="comments.length" class="sg-community-comment-list">
            <article v-for="comment in comments" :key="comment.id" :id="`comment-${comment.id}`">
              <span class="sg-community-avatar sg-community-avatar-small">
                <img v-if="comment.user?.avatar_url" :src="comment.user.avatar_url" :alt="commentAuthor(comment)" />
                <span v-else>{{ initials(commentAuthor(comment)) }}</span>
              </span>
              <div class="sg-community-comment-body">
                <div class="sg-community-comment-bubble">
                  <strong class="client-author-line">
                    {{ commentAuthor(comment) }}
                    <ClientAuthorBadges :badges="comment.user?.author_badges" />
                  </strong>
                  <p>{{ comment.content }}</p>
                </div>
                <small>{{ timeAgo(comment.created_at) }}</small>
              </div>
              <div class="sg-community-menu sg-community-comment-menu" @click.stop>
                <button
                  class="sg-client-icon-button"
                  type="button"
                  title="Tùy chọn bình luận"
                  aria-label="Mở tùy chọn bình luận"
                  @click="activeCommentMenu = activeCommentMenu === comment.id ? null : comment.id"
                >
                  <AppIcon name="moreHorizontal" size="16" />
                </button>
                <div v-if="activeCommentMenu === comment.id" class="sg-community-menu-panel">
                  <button type="button" @click="reportComment(comment.id)">
                    <AppIcon name="alert" size="16" />
                    Báo cáo bình luận
                  </button>
                </div>
              </div>
            </article>
          </div>
          <div v-else class="sg-community-no-comments">
            Chưa có bình luận. Hãy bắt đầu cuộc trò chuyện.
          </div>
        </section>
      </article>
    </main>

    <Teleport to="body">
      <div v-if="previewImage" class="sg-community-lightbox" role="dialog" aria-modal="true" aria-label="Xem ảnh bài viết" @click="previewImage = ''">
        <button type="button" aria-label="Đóng ảnh" @click="previewImage = ''">
          <AppIcon name="x" size="22" />
        </button>
        <img :src="previewImage" alt="Ảnh bài viết phóng to" @click.stop />
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="showLikers" class="sg-community-dialog-backdrop" @click="showLikers = false">
        <section class="sg-community-dialog" role="dialog" aria-modal="true" aria-label="Những người đã thích" @click.stop>
          <header>
            <h2>Những người đã thích</h2>
            <button class="sg-client-icon-button" type="button" aria-label="Đóng" @click="showLikers = false">
              <AppIcon name="x" size="19" />
            </button>
          </header>
          <div class="sg-community-liker-list">
            <p v-if="!likers.length">Chưa có dữ liệu người thích bài viết.</p>
            <article v-for="liker in likers" :key="liker.id">
              <span class="sg-community-avatar sg-community-avatar-small">
                <img v-if="liker.avatar_url" :src="liker.avatar_url" :alt="likerName(liker)" />
                <span v-else>{{ initials(likerName(liker)) }}</span>
              </span>
              <strong class="client-author-line">
                {{ likerName(liker) }}
                <ClientAuthorBadges :badges="liker.author_badges" />
              </strong>
            </article>
          </div>
        </section>
      </div>
    </Teleport>

    <ReportModal
      :is-open="report.open"
      :target-type="report.targetType"
      :target-id="report.targetId"
      @close="report.open = false"
      @success="handleReportSuccess"
    />
  </div>
</template>

<script>
import { useToast } from 'vue-toastification';
import AppIcon from '../../components/AppIcon.vue';
import ClientAuthorBadges from '../../components/ClientAuthorBadges.vue';
import PublicNavbar from '../../components/PublicNavbar.vue';
import ReportModal from '../../components/ReportModal.vue';
import { api } from '../../services/api.js';
import { getAuth } from '../../stores/auth.js';
import { normalizeMediaUrl } from '../../utils/mediaUrl.js';

const fallbackImage = '/images/home/badminton-cover.webp';

export default {
  name: 'CommunityPostDetail',
  components: { AppIcon, ClientAuthorBadges, PublicNavbar, ReportModal },
  setup() {
    return { toast: useToast() };
  },
  data() {
    return {
      post: null,
      loading: true,
      error: '',
      newComment: '',
      submittingComment: false,
      submittingLike: false,
      showPostMenu: false,
      activeCommentMenu: null,
      showLikers: false,
      previewImage: '',
      report: { open: false, targetType: '', targetId: '' },
    };
  },
  computed: {
    currentUser() {
      return getAuth()?.user || null;
    },
    currentUserName() {
      return this.currentUser?.full_name || this.currentUser?.username || 'Bạn';
    },
    authorName() {
      return this.post?.author?.full_name || this.post?.author?.username || 'Cộng đồng SportGo';
    },
    comments() {
      return Array.isArray(this.post?.top_level_comments) ? this.post.top_level_comments : [];
    },
    likers() {
      return Array.isArray(this.post?.likers) ? this.post.likers : [];
    },
    isLiked() {
      if (typeof this.post?.is_liked === 'boolean') return this.post.is_liked;
      if (!this.currentUser) return false;
      return this.likers.some((liker) => String(liker.id) === String(this.currentUser.id));
    },
    likesAvailable() {
      return this.post?.likes_available !== false;
    },
    thumbnailImage() {
      const media = Array.isArray(this.post?.media) ? this.post.media : [];
      return media.find((item) => item.collection === 'thumbnail') || null;
    },
    galleryImages() {
      return Array.isArray(this.post?.media)
        ? this.post.media.filter((item) => item.collection === 'gallery')
        : [];
    },
  },
  watch: {
    '$route.params.slug': {
      immediate: true,
      handler() {
        this.loadPost();
      },
    },
  },
  methods: {
    async loadPost() {
      this.loading = true;
      this.error = '';
      try {
        const response = await api(`/api/venue-posts/${this.$route.params.slug}`);
        this.post = response.data;
        this.$nextTick(() => this.scrollToRequestedComment());
      } catch (error) {
        this.post = null;
        this.error = error.message || 'Không thể tải bài viết.';
      } finally {
        this.loading = false;
      }
    },
    requireUser() {
      if (this.currentUser) return true;
      this.toast.info('Vui lòng đăng nhập để tiếp tục.');
      this.$router.push({ name: 'login', query: { redirect: this.$route.fullPath } });
      return false;
    },
    async toggleLike() {
      if (!this.requireUser() || this.submittingLike || !this.post) return;
      this.submittingLike = true;
      const wasLiked = this.isLiked;
      try {
        await api(`/api/venue-posts/${this.post.id}/likes`, { method: 'POST' });
        this.post.is_liked = !wasLiked;
        this.post.like_count = Math.max(0, Number(this.post.like_count || 0) + (wasLiked ? -1 : 1));
        if (wasLiked) {
          this.post.likers = this.likers.filter((liker) => String(liker.id) !== String(this.currentUser.id));
        } else {
          this.post.likers = [this.currentUser, ...this.likers];
        }
      } catch (error) {
        this.toast.error(error.message || 'Không thể cập nhật lượt thích.');
      } finally {
        this.submittingLike = false;
      }
    },
    async submitComment() {
      if (!this.requireUser() || !this.newComment.trim() || this.submittingComment || !this.post) return;
      this.submittingComment = true;
      const content = this.newComment.trim();
      try {
        const response = await api(`/api/venue-posts/${this.post.id}/comments`, {
          method: 'POST',
          body: JSON.stringify({ content }),
        });
        if (!Array.isArray(this.post.top_level_comments)) this.post.top_level_comments = [];
        this.post.top_level_comments.unshift({
          id: response.data?.id || `local-${Date.now()}`,
          content,
          created_at: new Date().toISOString(),
          user: response.data?.user || this.currentUser,
        });
        this.post.comment_count = Number(this.post.comment_count || 0) + 1;
        this.newComment = '';
        this.$nextTick(() => this.resetCommentHeight());
        this.toast.success('Bình luận đã được đăng.');
      } catch (error) {
        this.toast.error(error.message || 'Không thể gửi bình luận.');
      } finally {
        this.submittingComment = false;
      }
    },
    focusComment() {
      if (!this.requireUser()) return;
      this.$nextTick(() => this.$refs.commentInput?.focus());
    },
    resizeComment(event) {
      const field = event.currentTarget;
      field.style.height = 'auto';
      field.style.height = `${field.scrollHeight}px`;
    },
    resetCommentHeight() {
      if (this.$refs.commentInput) this.$refs.commentInput.style.height = 'auto';
    },
    async copyLink() {
      try {
        await navigator.clipboard.writeText(window.location.href);
        this.toast.success('Đã sao chép liên kết bài viết.');
      } catch {
        this.toast.error('Trình duyệt không cho phép sao chép liên kết.');
      }
    },
    reportPost() {
      if (!this.requireUser()) return;
      const targetType = this.post.feed_type === 'community_post' ? 'community_post' : 'venue_post';
      this.openReport(targetType, this.post.entity_id || this.post.id);
    },
    reportComment(id) {
      if (!this.requireUser()) return;
      const targetType = this.post?.feed_type === 'community_post' ? 'community_post_comment' : 'comment';
      this.openReport(targetType, id);
    },
    openReport(targetType, targetId) {
      this.report = { open: true, targetType, targetId };
      this.closeMenus();
    },
    handleReportSuccess() {
      this.report.open = false;
      this.toast.success('Báo cáo đã được gửi đến SportGo.');
    },
    closeMenus() {
      this.showPostMenu = false;
      this.activeCommentMenu = null;
    },
    openAuthor() {
      if (this.post?.author?.id) this.$router.push(`/user/${this.post.author.id}`);
    },
    openImage(source) {
      if (source) this.previewImage = source;
    },
    normalizeImage(media) {
      return normalizeMediaUrl(media)
        || normalizeMediaUrl({ file_path: this.post?.thumbnail || this.post?.image_path || this.post?.cover_image })
        || fallbackImage;
    },
    handleImageError(event) {
      if (event.currentTarget?.getAttribute('src') !== fallbackImage) event.currentTarget.src = fallbackImage;
    },
    categoryLabel(type) {
      return {
        news: 'Bài chia sẻ',
        promotion: 'Ưu đãi',
        tournament: 'Giải đấu',
        notice: 'Thông báo',
        recruitment: 'Tìm người chơi',
      }[type] || 'Bài chia sẻ';
    },
    commentAuthor(comment) {
      return comment.user?.full_name || comment.user?.username || 'Người dùng';
    },
    likerName(liker) {
      return liker.full_name || liker.username || 'Người dùng';
    },
    initials(value) {
      return String(value || '?')
        .trim()
        .split(/\s+/)
        .map((part) => part.charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase();
    },
    timeAgo(value) {
      if (!value) return 'Vừa xong';
      const seconds = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 1000));
      if (seconds < 60) return 'Vừa xong';
      if (seconds < 3600) return `${Math.floor(seconds / 60)} phút trước`;
      if (seconds < 86400) return `${Math.floor(seconds / 3600)} giờ trước`;
      if (seconds < 604800) return `${Math.floor(seconds / 86400)} ngày trước`;
      return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value));
    },
    scrollToRequestedComment() {
      const commentId = this.$route.query.open_comment;
      if (!commentId) return;
      window.setTimeout(() => {
        document.getElementById(`comment-${commentId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, 150);
    },
  },
};
</script>

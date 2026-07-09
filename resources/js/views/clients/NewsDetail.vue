<template>
  <div class="news-detail-page">
    <PublicNavbar />

    <main class="news-detail-shell">
      <div v-if="loading" class="news-state">Đang tải bài viết...</div>

      <div v-else-if="error" class="news-state news-state--error">
        <p>{{ error }}</p>
        <router-link :to="{ name: 'home', hash: '#community' }">Quay lại cộng đồng</router-link>
      </div>

      <div v-else-if="post" class="fb-modal-container">
        <!-- Breadcrumbs -->
        <nav class="article-breadcrumbs" aria-label="Breadcrumb">
          <router-link :to="{ name: 'home' }">Trang chủ</router-link>
          <span>/</span>
          <router-link :to="{ name: 'home', hash: '#community' }">Cộng đồng</router-link>
        </nav>

        <div class="fb-modal">
          <div class="fb-body">
            <div class="fb-post">
              <!-- Author Header -->
              <div class="fb-post-header">
                <div class="fb-post-avatar cursor-pointer hover:opacity-80 transition-opacity" @click.stop="post.author && post.author.id ? $router.push('/user/' + post.author.id) : null" :title="post.author && post.author.id ? 'Xem trang cá nhân' : ''">
                  <img v-if="post.author?.avatar_url" :src="post.author.avatar_url" />
                  <div v-else class="fb-avatar-text">{{ initials(post.author?.full_name || post.author?.username || '?') }}</div>
                </div>
                <div class="fb-post-meta">
                  <strong class="cursor-pointer hover:underline" @click.stop="post.author && post.author.id ? $router.push('/user/' + post.author.id) : null" :title="post.author && post.author.id ? 'Xem trang cá nhân' : ''">{{ post.author?.full_name || post.author?.username || 'Ban biên tập SportGo' }}</strong>
                  <div class="meta-sub">
                    <span>{{ formatDate(post.created_at) }}</span>
                    <span v-if="post.venueCluster?.name" class="meta-dot">·</span>
                    <span v-if="post.venueCluster?.name" class="meta-venue">{{ post.venueCluster.name }}</span>
                    <span class="meta-dot">·</span>
                    <span>{{ categoryLabel(post.post_type) }}</span>
                  </div>
                </div>

                <!-- Post Options -->
                <div class="fb-post-options" style="margin-left: auto; position: relative;">
                  <button @click="showPostOptions = !showPostOptions" class="action-btn" style="padding: 8px; border-radius: 50%; color: #65676b;" title="Tùy chọn bài viết">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><circle cx="5" cy="12" r="2"/></svg>
                  </button>
                  <div v-if="showPostOptions" class="options-dropdown" style="position: absolute; right: 0; top: 100%; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.15); border-radius: 8px; padding: 8px; z-index: 10; min-width: 180px;">
                    <button @click="openReportModal('post', post.id)" class="dropdown-item" style="display: flex; align-items: center; gap: 8px; width: 100%; padding: 8px; border: none; background: none; cursor: pointer; text-align: left; border-radius: 4px; color: #1c1e21; font-weight: 500;">
                      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg> 
                      Báo cáo bài viết
                    </button>
                  </div>
                </div>
              </div>

              <!-- Post Content -->
              <h1 v-if="post.title" class="fb-post-title">{{ post.title }}</h1>
              
              <div class="fb-post-text article-body" v-html="post.content"></div>

              <!-- Media -->
              <div v-if="post.media && post.media.length" class="fb-media-container">
                <img v-for="m in post.media" :key="m.id" :src="m.file_path || m.url || normalizeImage(m)" loading="lazy" />
              </div>

              <!-- Stats & Interactions -->
              <div class="fb-stats-row">
                <div class="fb-stats-left" @click="post.like_count > 0 ? showLikersModal = true : null" :class="{ 'clickable': post.like_count > 0 }">
                  <div class="like-avatars">
                    <span class="liker-count">{{ post.like_count || 0 }} lượt thích</span>
                  </div>
                </div>
                <div class="fb-stats-right">
                  <span class="clickable" @click="toggleComments">{{ post.comment_count || 0 }} bình luận</span>
                  <span>{{ post.view_count || 0 }} lượt xem</span>
                </div>
              </div>

              <!-- Actions -->
              <div class="fb-actions-row">
                <button class="action-btn" :class="{ 'liked': isLiked }" @click="toggleLike" :disabled="isSubmittingLike">
                  <svg viewBox="0 0 24 24" width="20" height="20" :fill="isLiked ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                  </svg>
                  Thích
                </button>
                <button class="action-btn" @click="focusCommentAction">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                  </svg>
                  Bình luận
                </button>
                <button class="action-btn" @click="copyLink">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line>
                  </svg>
                  Chia sẻ
                </button>
              </div>

              <!-- Comments Section -->
              <div v-show="showComments" class="fb-comments-section">
                <!-- Comment Form -->
                <div class="comment-form-row">
                  <div class="fb-post-avatar comment-avatar">
                    <img v-if="currentUser?.avatar_url" :src="currentUser.avatar_url" />
                    <div v-else class="fb-avatar-text">{{ initials(currentUser?.full_name || currentUser?.username || '?') }}</div>
                  </div>
                  <form @submit.prevent="submitComment" class="comment-form">
                    <textarea 
                      ref="commentInput"
                      v-model="newComment" 
                      placeholder="Viết bình luận..." 
                      rows="1" 
                      @input="autoResizeTextarea"
                      :disabled="isSubmittingComment || !currentUser"
                    ></textarea>
                    <button type="submit" :disabled="!newComment.trim() || isSubmittingComment || !currentUser" class="submit-comment-btn">
                      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    </button>
                  </form>
                </div>
                <div v-if="!currentUser" class="login-prompt">
                  Vui lòng <router-link :to="{ name: 'login' }">đăng nhập</router-link> để tương tác với bài viết.
                </div>

                <!-- Comments List -->
                <div class="comments-list" v-if="post.top_level_comments && post.top_level_comments.length">
                  <div v-for="comment in post.top_level_comments" :key="comment.id" class="comment-item">
                    <div class="fb-post-avatar comment-avatar">
                      <img v-if="comment.user?.avatar_url" :src="comment.user.avatar_url" />
                      <div v-else class="fb-avatar-text">{{ initials(comment.user?.full_name || comment.user?.username || '?') }}</div>
                    </div>
                    <div class="comment-content-wrapper" @mouseenter="hoverComment = comment.id" @mouseleave="hoverComment = null">
                      <div class="comment-bubble">
                        <strong>{{ comment.user?.full_name || comment.user?.username || 'Người dùng' }}</strong>
                        <p>{{ comment.content }}</p>
                      </div>
                      
                      <!-- Comment Options -->
                      <div class="comment-options" v-show="hoverComment === comment.id || activeCommentOptions === comment.id" style="position: absolute; right: -32px; top: 50%; transform: translateY(-50%);">
                        <button @click="toggleCommentOptions(comment.id)" class="action-btn" style="padding: 4px; border-radius: 50%; color: #65676b; border: none; background: transparent; cursor: pointer;">
                          <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><circle cx="5" cy="12" r="2"/></svg>
                        </button>
                        <div v-if="activeCommentOptions === comment.id" class="options-dropdown" style="position: absolute; left: 100%; top: 0; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.15); border-radius: 8px; padding: 8px; z-index: 10; min-width: 160px; margin-left: 8px;">
                          <button @click="openReportModal('comment', comment.id)" class="dropdown-item" style="display: flex; align-items: center; gap: 8px; width: 100%; padding: 8px; border: none; background: none; cursor: pointer; text-align: left; border-radius: 4px; color: #1c1e21; font-weight: 500;">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                            Báo cáo bình luận
                          </button>
                        </div>
                      </div>

                      <div class="comment-actions">
                        <span>{{ timeAgo(comment.created_at) }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Toast Notification -->
    <div class="toast-notification" :class="{ 'show': showToast }">
      {{ toastMessage }}
    </div>

    <!-- Likers Modal -->
    <div v-if="showLikersModal" class="likers-modal-backdrop" @click="showLikersModal = false">
      <div class="likers-modal-content" @click.stop>
        <div class="likers-modal-header">
          <h3>Những người đã thích</h3>
          <button @click="showLikersModal = false" class="close-btn">&times;</button>
        </div>
        <div class="likers-modal-body">
          <div v-for="liker in post?.likers" :key="liker.id" class="liker-list-item">
            <div class="fb-post-avatar">
              <img v-if="liker.avatar_url" :src="liker.avatar_url" />
              <div v-else class="fb-avatar-text">{{ initials(liker.full_name || liker.username) }}</div>
            </div>
            <span>{{ liker.full_name || liker.username }}</span>
          </div>
        </div>
      </div>
    </div>
    
    <ReportModal
      :isOpen="reportModal.open"
      :targetType="reportModal.targetType"
      :targetId="reportModal.targetId"
      @close="reportModal.open = false"
      @success="handleReportSuccess"
    />
  </div>
</template>

<script>
import PublicNavbar from "../../components/PublicNavbar.vue";
import ReportModal from "../../components/ReportModal.vue";
import { api } from "../../services/api.js";
import { normalizeMediaUrl } from "../../utils/mediaUrl.js";
import { getAuth } from "../../stores/auth.js";

const fallbackImage = "/images/home/badminton-cover.webp";

export default {
  name: "NewsDetail",
  components: { PublicNavbar, ReportModal },
  data() {
    return {
      post: null,
      loading: true,
      error: "",
      newComment: "",
      isSubmittingComment: false,
      isSubmittingLike: false,
      showComments: false,
      showLikersModal: false,
      showToast: false,
      toastMessage: "",
      showPostOptions: false,
      hoverComment: null,
      activeCommentOptions: null,
      reportModal: {
        open: false,
        targetType: '',
        targetId: ''
      }
    };
  },
  computed: {
    currentUser() {
      const auth = getAuth();
      return auth ? auth.user : null;
    },
    isLiked() {
      if (!this.currentUser || !this.post || !this.post.likers) return false;
      return this.post.likers.some(liker => liker.id === this.currentUser.id);
    }
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
    async toggleLike() {
      if (!this.currentUser) {
        this.$router.push({ name: 'login' });
        return;
      }
      
      if (this.isSubmittingLike) return;
      this.isSubmittingLike = true;
      
      try {
        await api(`/api/venue-posts/${this.post.id}/likes`, { method: 'POST' });
        // Optimistic update
        if (this.isLiked) {
          this.post.likers = this.post.likers.filter(l => l.id !== this.currentUser.id);
          this.post.like_count--;
        } else {
          if (!this.post.likers) this.post.likers = [];
          this.post.likers.unshift(this.currentUser);
          this.post.like_count++;
        }
      } catch (error) {
        console.error("Failed to toggle like:", error);
      } finally {
        this.isSubmittingLike = false;
      }
    },
    async submitComment() {
      if (!this.newComment.trim() || this.isSubmittingComment || !this.currentUser) return;

      this.isSubmittingComment = true;
      try {
        await api(`/api/venue-posts/${this.post.id}/comments`, {
          method: "POST",
          body: JSON.stringify({ content: this.newComment.trim() }),
        });
        
        this.newComment = "";
        this.$refs.commentInput.style.height = 'auto';
      } catch (error) {
        console.error("Failed to post comment:", error);
        alert(error.response?.data?.message || error.message || "Không thể gửi bình luận.");
      } finally {
        this.isSubmittingComment = false;
      }
    },
    focusCommentAction() {
      this.showComments = true;
      this.$nextTick(() => {
        this.focusComment();
      });
    },
    toggleComments() {
      this.showComments = !this.showComments;
    },
    focusComment() {
      if (this.$refs.commentInput) {
        this.$refs.commentInput.focus();
      }
    },
    copyLink() {
      navigator.clipboard.writeText(window.location.href);
      this.toastMessage = 'Đã sao chép liên kết bài viết!';
      this.showToast = true;
      setTimeout(() => {
        this.showToast = false;
      }, 3000);
    },
    autoResizeTextarea(e) {
      const el = e.target;
      el.style.height = 'auto';
      el.style.height = (el.scrollHeight) + 'px';
    },
    normalizeImage(media) {
      return normalizeMediaUrl(media) || normalizeMediaUrl({ file_path: this.post.thumbnail || this.post.image_path || this.post.cover_image }) || fallbackImage;
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
        day: "2-digit", month: "2-digit", year: "numeric",
        hour: "2-digit", minute: "2-digit"
      }).format(new Date(value));
    },
    timeAgo(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      const now = new Date();
      const diffInSeconds = Math.floor((now - date) / 1000);
      
      if (diffInSeconds < 60) return 'Vừa xong';
      if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} phút trước`;
      if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} giờ trước`;
      if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)} ngày trước`;
      
      return this.formatDate(dateString).split(' ')[0]; // Just the date part
    },
    initials(name) {
      if (!name) return "?";
      return name.split(" ").map((n) => n[0]).join("").substring(0, 2).toUpperCase();
    },
    toggleCommentOptions(id) {
      if (this.activeCommentOptions === id) {
        this.activeCommentOptions = null;
      } else {
        this.activeCommentOptions = id;
      }
    },
    openReportModal(type, id) {
      this.reportModal.targetType = type;
      this.reportModal.targetId = id;
      this.reportModal.open = true;
      this.showPostOptions = false;
      this.activeCommentOptions = null;
    },
    handleReportSuccess() {
      this.showToastMessage('Báo cáo của bạn đã được gửi thành công.');
    },
    showToastMessage(msg) {
      this.toastMessage = msg;
      this.showToast = true;
      setTimeout(() => {
        this.showToast = false;
      }, 3000);
    }
  },
};
</script>

<style scoped>
.news-detail-page {
  min-height: 100vh;
  background: #f0f2f5; /* Facebook style background */
  color: #1c1e21;
}

.news-detail-shell {
  max-width: 740px;
  margin: 0 auto;
  padding: 80px 16px 72px;
}

.news-state {
  display: grid;
  min-height: 320px;
  place-items: center;
  border-radius: 12px;
  background: #fff;
  color: #425247;
  font-weight: 800;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.news-state--error {
  gap: 14px;
}

.news-state a {
  color: #0d8c51;
  font-weight: 900;
  text-decoration: none;
}

.article-breadcrumbs {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #65676B;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 16px;
  padding: 0 8px;
}

.article-breadcrumbs a {
  color: inherit;
  text-decoration: none;
}

.article-breadcrumbs a:hover {
  text-decoration: underline;
}

/* Facebook Modal Style applied to Main View */
.fb-modal-container {
  display: flex;
  flex-direction: column;
}

.fb-modal {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}

.fb-body {
  padding: 0;
}

.fb-post {
  padding: 16px 0 0 0;
}

.fb-post-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 16px 12px;
}

.fb-post-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  overflow: hidden;
  background: #e4e6eb;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.fb-post-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.fb-avatar-text {
  font-size: 16px;
  font-weight: bold;
  color: #65676b;
}

.fb-post-meta {
  display: flex;
  flex-direction: column;
}

.fb-post-meta strong {
  font-size: 15px;
  color: #050505;
  font-weight: 600;
}

.meta-sub {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  color: #65676B;
}

.meta-dot {
  font-size: 10px;
}

.meta-venue {
  font-weight: 600;
  color: #0866FF;
}

.fb-post-title {
  margin: 0 16px 8px;
  font-size: 24px;
  font-weight: 700;
  line-height: 1.3;
  color: #050505;
}

.fb-post-text {
  padding: 0 16px 12px;
  font-size: 15px;
  line-height: 1.5;
  color: #050505;
  white-space: pre-wrap;
  word-break: break-word;
}

.article-body :deep(p) { margin: 0 0 12px; }
.article-body :deep(img) { max-width: 100%; border-radius: 8px; margin: 12px 0; }
.article-body :deep(a) { color: #0866FF; text-decoration: none; }
.article-body :deep(a:hover) { text-decoration: underline; }

.fb-media-container {
  width: 100%;
  border-top: 1px solid #f0f2f5;
  border-bottom: 1px solid #f0f2f5;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.fb-media-container img {
  width: 100%;
  object-fit: contain;
  max-height: 600px;
  background: #f0f2f5;
}

.fb-stats-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 16px;
  color: #65676B;
  font-size: 15px;
  border-bottom: 1px solid #ced0d4;
  margin: 0 16px;
}

.fb-stats-left {
  display: flex;
  align-items: center;
}

.like-avatars {
  display: flex;
  align-items: center;
}

.liker-avatar {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid #fff;
  margin-right: -6px;
  object-fit: cover;
}

.liker-count {
  margin-left: 12px;
  font-size: 15px;
}

.fb-stats-right {
  display: flex;
  gap: 12px;
}

.fb-actions-row {
  display: flex;
  padding: 4px 16px;
  margin: 0 16px;
  border-bottom: 1px solid #ced0d4;
}

.action-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 6px 0;
  margin: 2px;
  background: transparent;
  border: none;
  border-radius: 4px;
  color: #65676B;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s;
}

.action-btn:hover {
  background-color: #f2f2f2;
}

.action-btn.liked {
  color: #0866FF;
}

.fb-comments-section {
  padding: 16px;
  background: #fff;
}

.comment-form-row {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
}

.comment-avatar {
  width: 32px;
  height: 32px;
}

.comment-avatar .fb-avatar-text {
  font-size: 13px;
}

.comment-form {
  flex: 1;
  display: flex;
  align-items: flex-end;
  background: #f0f2f5;
  border-radius: 18px;
  padding: 4px 12px;
}

.comment-form textarea {
  flex: 1;
  background: transparent;
  border: none;
  padding: 6px 0;
  font-size: 15px;
  color: #050505;
  resize: none;
  min-height: 20px;
  max-height: 120px;
  outline: none;
  font-family: inherit;
}

.submit-comment-btn {
  background: transparent;
  border: none;
  color: #0866FF;
  cursor: pointer;
  padding: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.submit-comment-btn:hover:not(:disabled) {
  background: #e4e6eb;
}

.submit-comment-btn:disabled {
  color: #bcc0c4;
  cursor: default;
}

.login-prompt {
  text-align: center;
  color: #65676B;
  font-size: 14px;
  margin-bottom: 16px;
}

.login-prompt a {
  color: #0866FF;
  text-decoration: none;
  font-weight: 600;
}

.comments-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.comment-item {
  display: flex;
  gap: 8px;
}

.comment-content-wrapper {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  max-width: calc(100% - 40px);
}

.comment-bubble {
  background: #f0f2f5;
  border-radius: 18px;
  padding: 8px 12px;
  font-size: 15px;
  color: #050505;
}

.comment-bubble strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 2px;
}

.comment-bubble p {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
}

.comment-actions {
  display: flex;
  gap: 12px;
  margin-left: 12px;
  margin-top: 4px;
  font-size: 12px;
  color: #65676B;
  font-weight: 600;
}

.clickable {
  cursor: pointer;
}
.clickable:hover .liker-count,
.clickable:hover {
  text-decoration: underline;
}

/* Toast */
.toast-notification {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%) translateY(100px);
  background: #333;
  color: #fff;
  padding: 12px 24px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  opacity: 0;
  transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
  z-index: 9999;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  pointer-events: none;
}
.toast-notification.show {
  transform: translateX(-50%) translateY(0);
  opacity: 1;
}

/* Modal */
.likers-modal-backdrop {
  position: fixed;
  top: 0; left: 0; width: 100%; height: 100%;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
}
.likers-modal-content {
  background: #fff;
  border-radius: 8px;
  width: 90%;
  max-width: 400px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 12px 28px rgba(0,0,0,0.2);
}
.likers-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px;
  border-bottom: 1px solid #e4e6eb;
}
.likers-modal-header h3 {
  margin: 0;
  font-size: 20px;
  font-weight: 700;
  color: #050505;
}
.close-btn {
  background: #e4e6eb;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  font-size: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #65676B;
}
.close-btn:hover {
  background: #d8dadf;
}
.likers-modal-body {
  padding: 8px 16px;
  overflow-y: auto;
}
.liker-list-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
}
.liker-list-item span {
  font-weight: 600;
  font-size: 15px;
  color: #050505;
}
</style>

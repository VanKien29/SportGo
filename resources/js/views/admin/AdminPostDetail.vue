<template>
  <section class="post-detail">
    <header class="page-head">
      <div>
        <a class="back-link" href="#" @click.prevent="$router.back()">← Quay lại</a>
        <h2>Chi tiết bài đăng</h2>
      </div>
    </header>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="loading" class="state-card">Đang tải chi tiết bài đăng...</div>

    <template v-else-if="post">
      <article class="post-content-card">
        <div class="post-header">
          <strong>{{ post.author_name || 'Người dùng' }}</strong>
          <span class="status" :class="post.status">{{ statusLabel(post.status) }}</span>
        </div>
        <div class="post-body" v-html="nl2br(post.content)"></div>
        <div class="post-stats">
          <span>💬 {{ post.comment_count || 0 }} bình luận</span>
          <span>❤️ {{ post.like_count || 0 }} lượt thích</span>
          <span>👁 {{ post.view_count || 0 }} lượt xem</span>
          <span>📅 {{ dateTime(post.created_at) }}</span>
        </div>
        <div v-if="post.media && post.media.length" class="post-media">
          <img v-for="m in post.media" :key="m.id" :src="m.url" alt="Media" class="post-media-img" />
        </div>
      </article>

      <section class="comments-section">
        <h3>Bình luận ({{ commentsMeta.total }})</h3>
        <div class="comments-list">
          <article v-for="comment in comments" :key="comment.id" class="comment-item content-card">
            <div class="content-card-body">
              <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 4px;">
                <img v-if="comment.user_avatar" :src="comment.user_avatar" class="avatar-sm" />
                <div v-else class="avatar-sm-placeholder">{{ initials(comment.user_name) }}</div>
                <strong>{{ comment.user_name || 'Người dùng' }}</strong>
              </div>
              <p class="content-text">{{ comment.content }}</p>
              <div v-if="comment.media && comment.media.length" class="content-media-preview">
                <img v-for="m in comment.media" :key="m.id" :src="m.url" class="media-thumb" />
              </div>
              <div class="content-meta">
                <span>📅 {{ dateTime(comment.created_at) }}</span>
                <span v-if="comment.replies_count">· 💬 {{ comment.replies_count }} trả lời</span>
              </div>
            </div>
          </article>
          <p v-if="comments.length === 0" class="muted">Chưa có bình luận.</p>
        </div>
        <footer class="pagination" v-if="commentsMeta.total > 20">
          <span>Trang {{ commentsMeta.current_page }} / {{ commentsMeta.last_page }}</span>
          <div>
            <button class="btn-sm" :disabled="commentsMeta.current_page <= 1" @click="loadPost(commentsMeta.current_page - 1)">‹</button>
            <button class="btn-sm" :disabled="commentsMeta.current_page >= commentsMeta.last_page" @click="loadPost(commentsMeta.current_page + 1)">›</button>
          </div>
        </footer>
      </section>
    </template>
  </section>
</template>

<script>
import { adminUserService } from '../../services/adminUserService.js';

export default {
  name: 'AdminPostDetail',
  data() {
    return {
      post: null,
      comments: [],
      commentsMeta: { current_page: 1, last_page: 1, total: 0 },
      loading: false,
      error: '',
    };
  },
  mounted() {
    this.loadPost(1);
  },
  methods: {
    async loadPost(page = 1) {
      this.loading = this.post === null;
      this.error = '';
      try {
        const response = await adminUserService.postDetail(this.$route.params.id, page);
        this.post = response.data || {};
        this.comments = response.data?.comments || [];
        this.commentsMeta = response.comments_meta || { current_page: 1, last_page: 1, total: 0 };
      } catch (err) {
        this.error = err.message || 'Không tải được chi tiết bài đăng.';
      } finally {
        this.loading = false;
      }
    },
    statusLabel(status) {
      return { published: 'Công khai', draft: 'Nháp', hidden: 'Đã ẩn', visible: 'Công khai', pending: 'Chờ duyệt' }[status] || status || '-';
    },
    nl2br(text) {
      if (!text) return '';
      return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
    },
    dateTime(value) {
      return value ? new Date(value).toLocaleString('vi-VN') : '-';
    },
    initials(name) {
      return String(name || 'SG').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
    },
  },
};
</script>

<style scoped>
.post-detail { display: grid; gap: 16px; }
.page-head { display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; }
.page-head h2 { margin: 6px 0; }
.back-link { color: #15803d; font-weight: 800; text-decoration: none; }
.muted { color: #64748b; }

.post-content-card, .comments-section, .state-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;
}
.post-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.post-body { line-height: 1.7; color: #334155; }
.post-stats { display: flex; gap: 16px; margin-top: 14px; font-size: 13px; color: #64748b; flex-wrap: wrap; }
.post-media { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.post-media-img { max-width: 200px; max-height: 150px; border-radius: 8px; object-fit: cover; }

.comments-section { display: grid; gap: 14px; }
.comments-section h3 { margin: 0; }
.comments-list { display: grid; gap: 12px; }

/* Content Cards */
.content-card { display: flex; justify-content: space-between; gap: 16px; padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; transition: box-shadow 0.2s; }
.content-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-color: #cbd5e1; }
.content-card-body { display: grid; gap: 8px; flex: 1; min-width: 0; }
.content-text { margin: 0; color: #1e293b; font-size: 14px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
.content-media-preview { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
.media-thumb { width: 80px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; }
.content-meta { display: flex; gap: 12px; font-size: 12px; color: #64748b; flex-wrap: wrap; align-items: center; }

.avatar-sm { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
.avatar-sm-placeholder { width: 24px; height: 24px; border-radius: 50%; background: #16a34a; color: #fff; font-size: 10px; font-weight: 800; display: grid; place-items: center; }

.status { border-radius: 999px; padding: 4px 8px; font-size: 12px; font-weight: 800; background: #e2e8f0; }
.status.active, .status.visible, .status.published { background: #dcfce7; color: #166534; }
.status.hidden { background: #fee2e2; color: #b91c1c; }
.status.pending, .status.draft { background: #fef3c7; color: #92400e; }

.alert { padding: 12px; border-radius: 10px; font-weight: 700; }
.error { background: #fee2e2; color: #b91c1c; }
.state-card { color: #64748b; text-align: center; }

.pagination { display: flex; justify-content: space-between; align-items: center; color: #64748b; font-size: 13px; }
.pagination div { display: flex; gap: 8px; }
.btn-sm { border: 1px solid #dbe3ef; background: #fff; border-radius: 6px; padding: 6px 10px; font-size: 12px; font-weight: 700; cursor: pointer; }
</style>

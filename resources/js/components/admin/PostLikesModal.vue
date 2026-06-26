<template>
  <div v-if="show" class="modal-backdrop" @click.self="close">
    <div class="likes-modal">
      <header class="likes-header">
        <h3>Những người đã thích</h3>
        <button class="close-btn" @click="close" title="Đóng">
          <AppIcon name="x" size="20" />
        </button>
      </header>
      
      <div class="likes-body" @scroll="handleScroll">
        <div v-if="loading && likes.length === 0" class="state-loading">
          Đang tải danh sách...
        </div>
        
        <div v-if="!loading && likes.length === 0" class="state-empty">
          Chưa có lượt thích nào.
        </div>
        
        <div class="likes-list">
          <div v-for="user in likes" :key="user.id" class="like-user-row">
            <div class="like-avatar">
              <img v-if="user.user_avatar" :src="user.user_avatar" />
              <div v-else class="like-avatar-text">{{ initials(user.user_name) }}</div>
            </div>
            <div class="like-info">
              <strong>{{ user.user_name }}</strong>
            </div>
          </div>
        </div>

        <div v-if="loading && likes.length > 0" class="state-loading-more">
          Đang tải thêm...
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '../AppIcon.vue';
import { adminUserService } from '../../services/adminUserService';

export default {
  name: 'PostLikesModal',
  components: { AppIcon },
  props: {
    show: {
      type: Boolean,
      default: false
    },
    postId: {
      type: [String, Number],
      default: null
    }
  },
  data() {
    return {
      likes: [],
      loading: false,
      currentPage: 1,
      lastPage: 1,
    };
  },
  watch: {
    show(newVal) {
      if (newVal && this.postId) {
        this.resetAndLoad();
      }
    }
  },
  methods: {
    close() {
      this.$emit('close');
    },
    async resetAndLoad() {
      this.likes = [];
      this.currentPage = 1;
      this.lastPage = 1;
      await this.loadLikes();
    },
    async loadLikes() {
      if (this.loading || this.currentPage > this.lastPage) return;
      
      this.loading = true;
      try {
        const res = await adminUserService.postLikes(this.postId, this.currentPage);
        if (this.currentPage === 1) {
          this.likes = res.data;
        } else {
          this.likes = [...this.likes, ...res.data];
        }
        this.lastPage = res.meta.last_page;
        this.currentPage++;
      } catch (err) {
        console.error('Failed to load likes', err);
      } finally {
        this.loading = false;
      }
    },
    handleScroll(e) {
      const { scrollTop, clientHeight, scrollHeight } = e.target;
      if (scrollHeight - scrollTop <= clientHeight + 50) {
        this.loadLikes();
      }
    },
    initials(name) {
      return String(name || 'SG').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
    }
  }
};
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.56);
  display: grid;
  place-items: center;
  z-index: 1000;
  padding: 20px;
}
.likes-modal {
  width: min(400px, calc(100vw - 32px));
  height: min(500px, 80vh);
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 12px 40px rgba(0,0,0,0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.likes-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  border-bottom: 1px solid #e2e8f0;
}
.likes-header h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
}
.close-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 0;
  background: #f1f5f9;
  display: grid;
  place-items: center;
  cursor: pointer;
  color: #64748b;
  transition: all 0.2s;
}
.close-btn:hover {
  background: #e2e8f0;
  color: #1e293b;
}
.likes-body {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}
.likes-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.like-user-row {
  display: flex;
  align-items: center;
  gap: 12px;
}
.like-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #e2e8f0;
  display: grid;
  place-items: center;
  font-weight: 700;
  color: #475569;
  font-size: 14px;
  overflow: hidden;
}
.like-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.like-info strong {
  font-size: 14px;
  color: #1e293b;
}
.state-loading, .state-loading-more, .state-empty {
  text-align: center;
  padding: 20px;
  color: #64748b;
  font-size: 14px;
}
.state-loading-more {
  padding: 10px;
}
</style>

<template>
  <div class="sg-floating-chat-wrap">
    <!-- Floating Quick Chat Trigger Button -->
    <button
      type="button"
      class="sg-btn-floating-chat"
      title="Mở Hộp Thư Tin Nhắn & Hỗ Trợ"
      @click="openChat"
    >
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
      </svg>
      <span class="sg-floating-chat-label">Nhắn tin</span>
      <span v-if="unreadBadge" class="sg-floating-badge">{{ unreadBadge }}</span>
    </button>
  </div>
</template>

<script>
export default {
  name: "FloatingChatWidget",
  data() {
    return {
      unreadBadge: 0,
    };
  },
  methods: {
    openChat() {
      if (this.$route.path !== "/chat" && this.$route.path !== "/messages") {
        this.$router.push("/chat");
      }
    },
  },
};
</script>

<style scoped>
.sg-floating-chat-wrap {
  position: fixed;
  bottom: 84px;
  right: 28px;
  z-index: 9998;
}

.sg-btn-floating-chat {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: #059669;
  color: #ffffff;
  border: none;
  border-radius: 30px;
  font-size: 14.5px;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 8px 24px rgba(5, 150, 105, 0.3);
  transition: transform 0.25s ease, background 0.25s ease, box-shadow 0.25s ease;
}

.sg-btn-floating-chat:hover {
  background: #047857;
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(5, 150, 105, 0.4);
}

.sg-floating-badge {
  background: #ef4444;
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 10px;
}

@media (max-width: 640px) {
  .sg-floating-chat-wrap {
    bottom: 76px;
    right: 20px;
  }
  .sg-floating-chat-label {
    display: none;
  }
  .sg-btn-floating-chat {
    padding: 14px;
    border-radius: 50%;
  }
}
</style>

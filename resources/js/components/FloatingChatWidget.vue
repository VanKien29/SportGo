<template>
  <div v-if="showWidget" class="sg-floating-chat-wrap">
    <!-- Mini Chat Popup Window -->
    <div v-if="isOpen" class="sg-mini-chat-panel">
      <!-- Panel Header -->
      <header class="sg-mini-chat-header">
        <div class="sg-mini-header-left">
          <button
            v-if="activeConversation"
            type="button"
            class="sg-mini-head-btn"
            title="Quay lại danh sách"
            @click="activeConversation = null"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 12H5M12 19l-7-7 7-7" />
            </svg>
          </button>
          <div class="sg-mini-header-title">
            <strong>{{ headerTitle }}</strong>
            <span v-if="activeConversation" class="sg-mini-header-status">
              {{ activeConversation.is_online ? 'Đang hoạt động' : 'Hộp thư tin nhắn' }}
            </span>
          </div>
        </div>

        <div class="sg-mini-header-right">
          <button
            type="button"
            class="sg-mini-head-btn"
            title="Mở toàn màn hình"
            @click="expandToFullPage"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />
            </svg>
          </button>
          <button
            type="button"
            class="sg-mini-head-btn"
            title="Đóng cửa sổ"
            @click="isOpen = false"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12" />
            </svg>
          </button>
        </div>
      </header>

      <!-- Guest View -->
      <div v-if="!user" class="sg-mini-chat-guest">
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#5c7e6e" stroke-width="1.8">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
        <p>Vui lòng đăng nhập để bắt đầu trò chuyện và nhận hỗ trợ trực tiếp.</p>
        <button type="button" class="sg-mini-btn-login" @click="goToLogin">
          Đăng nhập ngay
        </button>
      </div>

      <!-- Logged-in View -->
      <template v-else>
        <!-- Conversation List View -->
        <div v-if="!activeConversation" class="sg-mini-conversations">
          <!-- Search box -->
          <div class="sg-mini-search-wrap">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Tìm cuộc trò chuyện..."
              class="sg-mini-search-input"
            />
          </div>

          <!-- Loading state -->
          <div v-if="loadingConversations" class="sg-mini-loading">
            <span>Đang tải tin nhắn...</span>
          </div>

          <!-- Empty state -->
          <div v-else-if="filteredConversations.length === 0" class="sg-mini-empty">
            <p>Chưa có cuộc trò chuyện nào.</p>
          </div>

          <!-- List -->
          <div v-else class="sg-mini-list">
            <button
              v-for="conv in filteredConversations"
              :key="conv.id"
              type="button"
              class="sg-mini-item"
              :class="{ 'has-unread': conv.unread_count > 0 }"
              @click="selectConversation(conv)"
            >
              <!-- Avatar / Icon -->
              <div v-if="conv.is_ai" class="sg-mini-avatar sg-mini-avatar--ai">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
                </svg>
              </div>
              <div v-else-if="conv.avatar_url" class="sg-mini-avatar">
                <img :src="conv.avatar_url" class="sg-mini-avatar-img" alt="Avatar" />
              </div>
              <div
                v-else
                class="sg-mini-avatar"
                :style="{ backgroundColor: getAvatarColor(convName(conv)) }"
              >
                {{ getAvatarInitials(convName(conv)) }}
              </div>

              <div class="sg-mini-item-info">
                <div class="sg-mini-item-top">
                  <span class="sg-mini-item-name">{{ convName(conv) }}</span>
                  <time v-if="conv.updated_at || conv.last_message?.created_at" class="sg-mini-item-time">
                    {{ formatShortTime(conv.updated_at || conv.last_message?.created_at) }}
                  </time>
                </div>
                <div class="sg-mini-item-bottom">
                  <span class="sg-mini-item-last">{{ convLastMessage(conv) }}</span>
                  <span v-if="conv.unread_count > 0" class="sg-mini-unread-count">
                    {{ conv.unread_count }}
                  </span>
                </div>
              </div>
            </button>
          </div>
        </div>

        <!-- Chat Message Stream View -->
        <div v-else class="sg-mini-messages-wrap">
          <div ref="msgContainer" class="sg-mini-messages-body">
            <div v-if="loadingMessages" class="sg-mini-loading">
              <span>Đang tải hội thoại...</span>
            </div>

            <div v-else-if="messages.length === 0" class="sg-mini-empty">
              <p>Hãy gửi tin nhắn đầu tiên để bắt đầu trò chuyện!</p>
            </div>

            <template v-else>
              <div
                v-for="msg in messages"
                :key="msg.id || msg.created_at"
                class="sg-mini-msg-row"
                :class="{ 'is-mine': isMyMessage(msg) }"
              >
                <template v-if="!isMyMessage(msg)">
                  <div v-if="activeConversation.is_ai || msg.role === 'assistant' || msg.sender_id === 'ai'" class="sg-mini-msg-avatar sg-mini-avatar--ai">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
                    </svg>
                  </div>
                  <div
                    v-else
                    class="sg-mini-msg-avatar"
                    :style="{ backgroundColor: getAvatarColor(senderName(msg)) }"
                  >
                    {{ getAvatarInitials(senderName(msg)) }}
                  </div>
                </template>
                <div class="sg-mini-msg-bubble">
                  <div v-if="!isMyMessage(msg) && activeConversation.type === 'group'" class="sg-mini-msg-sender">
                    {{ senderName(msg) }}
                  </div>
                  <div class="sg-mini-msg-text" v-html="renderContent(msg)"></div>
                  <div class="sg-mini-msg-time">{{ formatMsgTime(msg.created_at) }}</div>
                </div>
              </div>
            </template>
          </div>

          <!-- Bottom Message Input -->
          <footer class="sg-mini-input-bar">
            <input
              v-model="newMessageText"
              type="text"
              placeholder="Nhập nội dung tin nhắn..."
              class="sg-mini-input"
              @keyup.enter="handleSendMessage"
            />
            <button
              type="button"
              class="sg-mini-btn-send"
              :disabled="!newMessageText.trim() || sending"
              @click="handleSendMessage"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="22" y1="2" x2="11" y2="13" />
                <polygon points="22 2 15 22 11 13 2 9 22 2" />
              </svg>
            </button>
          </footer>
        </div>
      </template>
    </div>

    <!-- Floating Quick Chat Trigger Button -->
    <button
      type="button"
      class="sg-btn-floating-chat"
      title="Mở Hộp Thư Tin Nhắn & Hỗ Trợ"
      aria-label="Mở Hộp Thư Tin Nhắn & Hỗ Trợ"
      @click="togglePopup"
    >
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
      </svg>
      <span v-if="unreadBadge" class="sg-floating-badge">{{ unreadBadge }}</span>
    </button>
  </div>
</template>

<script>
import { chatService } from "../services/chat.service.js";
import { getAuth } from "../stores/auth.js";
import { getAvatarColorHex, getAvatarInitial } from "../utils/avatar.js";
import { businessDateLabel, businessDateString, businessTimeString } from "../utils/businessTime.js";

export default {
  name: "FloatingChatWidget",
  data() {
    return {
      isOpen: false,
      user: getAuth(),
      conversations: [],
      activeConversation: null,
      messages: [],
      searchQuery: "",
      newMessageText: "",
      loadingConversations: false,
      loadingMessages: false,
      sending: false,
      pollTimer: null,
    };
  },
  computed: {
    showWidget() {
      const path = this.$route?.path || "";
      // Chỉ hiển thị ở trang khách, ẩn hoàn toàn trên các trang quản trị & đối tác
      if (/^\/(?:admin|owner|staff|partner)(?:\/|$)/.test(path)) {
        return false;
      }
      // Ẩn khi ở màn hình chat toàn trang, bản đồ hoặc trang đăng nhập/đăng ký
      if (
        path.includes("/chat") ||
        path === "/messages" ||
        path.includes("/venues/map") ||
        /^\/(?:login|register|forgot-password|auth)(?:\/|$)/.test(path)
      ) {
        return false;
      }
      return true;
    },
    headerTitle() {
      if (!this.user) return "Hỗ trợ trực tuyến";
      if (this.activeConversation) return this.convName(this.activeConversation);
      return "Hộp thư tin nhắn";
    },
    filteredConversations() {
      if (!this.searchQuery.trim()) return this.conversations;
      const q = this.searchQuery.toLowerCase();
      return this.conversations.filter((c) =>
        this.convName(c).toLowerCase().includes(q)
      );
    },
    unreadBadge() {
      if (!this.user || !this.conversations.length) return 0;
      return this.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0);
    },
  },
  watch: {
    isOpen(newVal) {
      if (newVal && this.user) {
        this.fetchConversations();
        this.startPolling();
      } else {
        this.stopPolling();
      }
    },
    "$route.path"() {
      this.isOpen = false;
      this.user = getAuth();
    },
  },
  mounted() {
    if (this.user) {
      this.fetchConversations();
    }
  },
  beforeUnmount() {
    this.stopPolling();
  },
  methods: {
    togglePopup() {
      this.user = getAuth();
      this.isOpen = !this.isOpen;
    },
    goToLogin() {
      this.isOpen = false;
      this.$router.push("/login");
    },
    expandToFullPage() {
      this.isOpen = false;
      const auth = getAuth();
      if (!auth) {
        this.$router.push("/login");
        return;
      }
      const role = auth.role || auth.role_group;
      if (role === "admin") {
        if (this.$route.path !== "/admin/chat") this.$router.push("/admin/chat");
      } else if (role === "owner") {
        if (this.$route.path !== "/owner/chat") this.$router.push("/owner/chat");
      } else if (role === "staff" || role === "venue_staff") {
        if (this.$route.path !== "/staff/chat") this.$router.push("/staff/chat");
      } else {
        if (this.$route.path !== "/chat" && this.$route.path !== "/messages") {
          this.$router.push("/chat");
        }
      }
    },
    async fetchConversations() {
      if (!this.user) return;
      this.loadingConversations = true;
      try {
        const res = await chatService.getConversations();
        const baseList = Array.isArray(res) ? res : res?.data || [];
        const aiConv = {
          id: "ai_assistant",
          title: "Trợ lý AI SportGo",
          type: "ai",
          is_ai: true,
          avatar_url: null,
          last_message: {
            content: "Trợ lý AI tư vấn và giải đáp thắc mắc 24/7",
            created_at: new Date().toISOString(),
          },
        };
        this.conversations = [aiConv, ...baseList];
      } catch (err) {
        // silent
      } finally {
        this.loadingConversations = false;
      }
    },
    async selectConversation(conv) {
      this.activeConversation = conv;
      this.messages = [];
      this.loadingMessages = true;

      if (conv.is_ai) {
        try {
          const res = await chatService.getAiHistory();
          const list = res?.messages || res?.data || [];
          this.messages = list.map((m) => {
            const isUser = m.role === "user" || m.sender_id === "me" || String(m.sender_id) === String(this.user?.id);
            return {
              id: m.id || "ai_" + Math.random(),
              content: m.content || m.body || m.text,
              sender_id: isUser ? this.user?.id : "ai",
              role: isUser ? "user" : "assistant",
              sender: { full_name: isUser ? (this.user?.fullName || "Tôi") : "Trợ lý AI SportGo" },
              created_at: m.created_at || new Date().toISOString(),
            };
          });
        } catch (err) {
          // silent
        } finally {
          this.loadingMessages = false;
          this.scrollToBottom();
        }
        return;
      }

      try {
        const res = await chatService.getMessages(conv.id);
        const list = Array.isArray(res) ? res : res?.data || [];
        this.messages = list;
        if (conv.unread_count > 0) {
          conv.unread_count = 0;
          chatService.markAsRead(conv.id).catch(() => {});
        }
      } catch (err) {
        // silent
      } finally {
        this.loadingMessages = false;
        this.scrollToBottom();
      }
    },
    async handleSendMessage() {
      const text = this.newMessageText.trim();
      if (!text || !this.activeConversation || this.sending) return;

      if (this.activeConversation.is_ai) {
        const tempUserMsg = {
          id: "temp_" + Date.now(),
          content: text,
          sender_id: this.user?.id,
          created_at: new Date().toISOString(),
        };
        this.messages.push(tempUserMsg);
        this.newMessageText = "";
        this.scrollToBottom();
        this.sending = true;

        try {
          const res = await chatService.askAiAssistant({ message: text, prompt: text });
          const reply = res?.reply || res?.message || res?.response || "Xin lỗi, tôi chưa thể trả lời câu hỏi này.";
          this.messages.push({
            id: "ai_" + Date.now(),
            content: reply,
            sender_id: "ai",
            sender: { full_name: "Trợ lý AI SportGo" },
            created_at: new Date().toISOString(),
          });
          this.scrollToBottom();
        } catch (err) {
          // silent
        } finally {
          this.sending = false;
        }
        return;
      }

      this.sending = true;
      const tempMsg = {
        id: "temp_" + Date.now(),
        content: text,
        sender_id: this.user?.id,
        created_at: new Date().toISOString(),
      };
      this.messages.push(tempMsg);
      this.newMessageText = "";
      this.scrollToBottom();

      try {
        const res = await chatService.sendMessage(this.activeConversation.id, text);
        const realMsg = res?.data || res;
        const idx = this.messages.findIndex((m) => m.id === tempMsg.id);
        if (idx !== -1 && realMsg) {
          this.messages.splice(idx, 1, realMsg);
        }
        if (this.activeConversation) {
          this.activeConversation.last_message = text;
          this.activeConversation.updated_at = new Date().toISOString();
        }
      } catch (err) {
        // silent
      } finally {
        this.sending = false;
      }
    },
    startPolling() {
      this.stopPolling();
      this.pollTimer = setInterval(() => {
        if (this.isOpen && this.user) {
          if (this.activeConversation && !this.activeConversation.is_ai) {
            chatService.getMessages(this.activeConversation.id).then((res) => {
              const list = Array.isArray(res) ? res : res?.data || [];
              if (list.length > this.messages.length) {
                this.messages = list;
                this.scrollToBottom();
              }
            }).catch(() => {});
          } else if (!this.activeConversation) {
            chatService.getConversations().then((res) => {
              const baseList = Array.isArray(res) ? res : res?.data || [];
              const aiConv = {
                id: "ai_assistant",
                title: "Trợ lý AI SportGo",
                type: "ai",
                is_ai: true,
                avatar_url: null,
                last_message: {
                  content: "Trợ lý AI tư vấn và giải đáp thắc mắc 24/7",
                  created_at: new Date().toISOString(),
                },
              };
              this.conversations = [aiConv, ...baseList];
            }).catch(() => {});
          }
        }
      }, 5000);
    },
    stopPolling() {
      if (this.pollTimer) {
        clearInterval(this.pollTimer);
        this.pollTimer = null;
      }
    },
    scrollToBottom() {
      this.$nextTick(() => {
        setTimeout(() => {
          if (this.$refs.msgContainer) {
            this.$refs.msgContainer.scrollTop = this.$refs.msgContainer.scrollHeight;
          }
        }, 60);
      });
    },
    convName(conv) {
      if (!conv) return "Cuộc trò chuyện";
      return (
        conv.title ||
        conv.display_name ||
        conv.name ||
        conv.other_user?.full_name ||
        conv.other_user?.username ||
        conv.partner?.full_name ||
        conv.partner?.name ||
        "Trò chuyện"
      );
    },
    convLastMessage(conv) {
      if (!conv) return "";
      const last = conv.last_message || conv.latest_message;
      if (typeof last === "string") return last;
      if (last?.content) return last.content;
      return "Bắt đầu cuộc trò chuyện";
    },
    isMyMessage(msg) {
      if (!msg) return false;
      if (msg.role === "user" || msg.sender_id === "me" || msg.is_user === true) return true;
      if (!this.user) return false;
      return String(msg.sender_id) === String(this.user.id) || String(msg.user_id) === String(this.user.id);
    },
    senderName(msg) {
      if (this.isMyMessage(msg)) return this.user?.fullName || "Tôi";
      return msg.sender?.full_name || msg.sender?.name || "Người dùng";
    },
    getAvatarColor(name) {
      return getAvatarColorHex(name);
    },
    getAvatarInitials(name) {
      return getAvatarInitial(name);
    },
    formatShortTime(dateStr) {
      if (!dateStr) return "";
      const date = new Date(dateStr);
      if (Number.isNaN(date.getTime())) return "";
      if (businessDateString(date) === businessDateString()) {
        return businessTimeString(date);
      }
      return businessDateLabel(date).slice(0, 5);
    },
    formatMsgTime(dateStr) {
      if (!dateStr) return "";
      const date = new Date(dateStr);
      return Number.isNaN(date.getTime()) ? "" : businessTimeString(date);
    },
    renderContent(msg) {
      const raw = (msg?.content || msg?.body || "").trim();
      if (!raw) return "";

      const lines = raw.split("\n");
      const outputParts = [];
      let inOrderedList = false;
      let inBulletList = false;

      const escapeLine = (str) =>
        str
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;");

      const applyInline = (str) => {
        str = str.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        str = str.replace(/__([^_]+)__/g, "<strong>$1</strong>");
        str = str.replace(/`([^`]+)`/g, "<code>$1</code>");
        return str;
      };

      const closeLists = () => {
        if (inOrderedList) { outputParts.push("</ol>"); inOrderedList = false; }
        if (inBulletList)  { outputParts.push("</ul>"); inBulletList = false; }
      };

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const numMatch = line.match(/^(\d+)\. (.+)$/);
        if (numMatch) {
          if (inBulletList) { outputParts.push("</ul>"); inBulletList = false; }
          if (!inOrderedList) { outputParts.push('<ol style="margin: 4px 0 4px 18px; padding: 0;">'); inOrderedList = true; }
          outputParts.push('<li style="margin-bottom: 2px;">' + applyInline(escapeLine(numMatch[2])) + "</li>");
          continue;
        }

        const bulletMatch = line.match(/^[-*] (.+)$/);
        if (bulletMatch) {
          if (inOrderedList) { outputParts.push("</ol>"); inOrderedList = false; }
          if (!inBulletList) { outputParts.push('<ul style="margin: 4px 0 4px 18px; padding: 0;">'); inBulletList = true; }
          outputParts.push('<li style="margin-bottom: 2px;">' + applyInline(escapeLine(bulletMatch[1])) + "</li>");
          continue;
        }

        if (line.trim() === "") {
          closeLists();
          outputParts.push("<br>");
          continue;
        }

        closeLists();
        outputParts.push(applyInline(escapeLine(line)) + "<br>");
      }

      closeLists();
      let html = outputParts.join("");
      html = html.replace(/(<br>\s*)+$/, "");
      return html;
    },
  },
};
</script>

<style scoped>
.sg-floating-chat-wrap {
  position: fixed;
  bottom: 78px;
  right: 24px;
  z-index: 9998;
}

/* MINI CHAT PANEL WINDOW */
.sg-mini-chat-panel {
  position: absolute;
  bottom: calc(100% + 12px);
  right: 0;
  width: 360px;
  height: 500px;
  max-height: calc(100vh - 120px);
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  box-shadow: none;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  font-family: inherit;
}

/* HEADER */
.sg-mini-chat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  background: #5c7e6e;
  color: #ffffff;
}

.sg-mini-header-left {
  display: flex;
  align-items: center;
  gap: 10px;
  overflow: hidden;
}

.sg-mini-header-title {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.sg-mini-header-title strong {
  font-size: 14.5px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #ffffff;
}

.sg-mini-header-status {
  font-size: 11px;
  opacity: 0.85;
  color: #f1f5f9;
}

.sg-mini-header-right {
  display: flex;
  align-items: center;
  gap: 4px;
}

.sg-mini-head-btn {
  background: transparent;
  border: none;
  color: #ffffff;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* GUEST VIEW */
.sg-mini-chat-guest {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 32px 24px;
  text-align: center;
  height: 100%;
  gap: 16px;
}

.sg-mini-chat-guest p {
  font-size: 13.5px;
  color: #475569;
  margin: 0;
  line-height: 1.5;
}

.sg-mini-btn-login {
  padding: 9px 22px;
  background: #5c7e6e;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
}

/* CONVERSATION LIST VIEW */
.sg-mini-conversations {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}

.sg-mini-search-wrap {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  background: #f8fafc;
}

.sg-mini-search-input {
  width: 100%;
  padding: 7px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 13px;
  background: #ffffff;
  outline: none;
}

.sg-mini-loading,
.sg-mini-empty {
  padding: 32px 16px;
  text-align: center;
  color: #64748b;
  font-size: 13px;
}

.sg-mini-list {
  flex: 1;
  overflow-y: auto;
  padding: 4px 0;
}

.sg-mini-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 12px;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
  border-bottom: 1px solid #f8fafc;
}

.sg-mini-item.has-unread {
  background: #f0fdf4;
}

.sg-mini-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
}

.sg-mini-avatar--ai {
  background: #0f172a !important;
  color: #38bdf8 !important;
}

.sg-mini-avatar-img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
}

.sg-mini-item-info {
  flex: 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sg-mini-item-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.sg-mini-item-name {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sg-mini-item-time {
  font-size: 11px;
  color: #94a3b8;
}

.sg-mini-item-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.sg-mini-item-last {
  font-size: 12px;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sg-mini-unread-count {
  background: #ef4444;
  color: #ffffff;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 999px;
}

/* MESSAGE STREAM VIEW */
.sg-mini-messages-wrap {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}

.sg-mini-messages-body {
  flex: 1;
  overflow-y: auto;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  background: #f8fafc;
}

.sg-mini-msg-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  max-width: 82%;
}

.sg-mini-msg-row.is-mine {
  align-self: flex-end;
  flex-direction: row-reverse;
}

.sg-mini-msg-avatar {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  color: #ffffff;
  font-size: 11px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.sg-mini-msg-bubble {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  padding: 8px 12px;
  border-radius: 12px;
  border-bottom-left-radius: 2px;
  font-size: 13px;
  color: #0f172a;
  line-height: 1.4;
}

.sg-mini-msg-row.is-mine .sg-mini-msg-bubble {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
  border-bottom-left-radius: 12px;
  border-bottom-right-radius: 2px;
}

.sg-mini-msg-sender {
  font-size: 11px;
  font-weight: 600;
  color: #5c7e6e;
  margin-bottom: 2px;
}

.sg-mini-msg-time {
  font-size: 10px;
  color: #94a3b8;
  text-align: right;
  margin-top: 3px;
}

.sg-mini-msg-row.is-mine .sg-mini-msg-time {
  color: #e2e8f0;
}

/* INPUT BAR */
.sg-mini-input-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  background: #ffffff;
  border-top: 1px solid #e2e8f0;
}

.sg-mini-input {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 20px;
  font-size: 13px;
  outline: none;
}

.sg-mini-btn-send {
  background: #5c7e6e;
  color: #ffffff;
  border: none;
  border-radius: 50%;
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}

.sg-mini-btn-send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* TRIGGER BUTTON */
.sg-btn-floating-chat {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  padding: 0;
  background: #5c7e6e;
  color: #ffffff;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.16);
  transition: transform 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
  position: relative;
}

.sg-btn-floating-chat:hover {
  background: #4a6759;
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

.sg-floating-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #ef4444;
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 10px;
  border: 2px solid #ffffff;
}

@media (max-width: 640px) {
  .sg-floating-chat-wrap {
    bottom: 70px;
    right: 16px;
  }
  .sg-mini-chat-panel {
    right: -10px;
    width: calc(100vw - 32px);
    height: 460px;
  }
  .sg-btn-floating-chat {
    width: 42px;
    height: 42px;
  }
}
</style>

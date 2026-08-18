<template>
  <div class="cc-page">
    <PublicNavbar />

    <main class="cc-main">
      <div class="cc-container">
        <!-- LEFT SIDEBAR: CONVERSATIONS LIST -->
        <aside
          :class="[
            'cc-sidebar',
            mobileActiveView === 'chat' ? 'cc-sidebar--hidden-mobile' : ''
          ]"
        >
          <!-- SIDEBAR HEADER -->
          <div class="cc-sidebar-head">
            <h1 class="cc-sidebar-title">Hộp thư hỗ trợ</h1>

            <!-- SEARCH INPUT -->
            <div class="cc-search-box">
              <AppIcon name="search" size="14" class="cc-search-icon" />
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Tìm trò chuyện, cụm sân..."
                class="cc-search-input"
              />
              <button
                v-if="searchQuery"
                type="button"
                class="cc-search-clear"
                @click="searchQuery = ''"
              >
                ✕
              </button>
            </div>

            <!-- FILTER TABS -->
            <div class="cc-filter-tabs">
              <button
                type="button"
                :class="['cc-tab', activeTab === 'all' ? 'active' : '']"
                @click="activeTab = 'all'"
              >
                Tất cả
              </button>
              <button
                type="button"
                :class="['cc-tab', activeTab === 'ai' ? 'active' : '']"
                @click="activeTab = 'ai'"
              >
                Trợ lý AI
              </button>
              <button
                type="button"
                :class="['cc-tab', activeTab === 'venue' ? 'active' : '']"
                @click="activeTab = 'venue'"
              >
                Sân đấu
              </button>
            </div>
          </div>

          <!-- CONVERSATIONS SCROLL LIST -->
          <div class="cc-conv-list">
            <div v-if="loadingConversations" class="cc-state-msg">
              <span class="cc-spinner"></span>
              <span>Đang tải danh sách hộp thư...</span>
            </div>

            <div v-else-if="filteredConversations.length === 0" class="cc-state-msg">
              <span>Không tìm thấy cuộc trò chuyện nào.</span>
            </div>

            <button
              v-for="conv in filteredConversations"
              :key="conv.id"
              type="button"
              :class="[
                'cc-conv-item',
                activeConversation?.id === conv.id ? 'active' : ''
              ]"
              @click="selectConversation(conv)"
            >
              <!-- AVATAR / ICON -->
              <div class="cc-avatar-wrap">
                <div v-if="conv.is_ai" class="cc-avatar cc-avatar--ai">
                  <AppIcon name="sparkles" size="18" />
                </div>
                <img
                  v-else-if="conv.avatar_url"
                  :src="conv.avatar_url"
                  class="cc-avatar-img"
                  alt="Avatar"
                />
                <div
                  v-else
                  class="cc-avatar"
                  :style="{ backgroundColor: getAvatarColor(conv.title) }"
                >
                  {{ getInitial(conv.title) }}
                </div>
                <span v-if="!conv.is_ai" class="cc-online-dot"></span>
              </div>

              <!-- CONTENT PREVIEW -->
              <div class="cc-conv-info">
                <div class="cc-conv-top">
                  <strong class="cc-conv-title">{{ conv.title }}</strong>
                  <span class="cc-conv-time">{{ formatTime(conv.last_message?.created_at || conv.last_message_at) }}</span>
                </div>
                <div class="cc-conv-sub">
                  <span class="cc-conv-preview">{{ conv.last_message?.content || "Bắt đầu trò chuyện" }}</span>
                  <span v-if="conv.unread_count > 0" class="cc-unread-badge">{{ conv.unread_count }}</span>
                </div>
              </div>
            </button>
          </div>
        </aside>

        <!-- RIGHT CHAT WORKSPACE -->
        <section
          :class="[
            'cc-workspace',
            mobileActiveView === 'list' ? 'cc-workspace--hidden-mobile' : ''
          ]"
        >
          <!-- NO ACTIVE CONVERSATION -->
          <div v-if="!activeConversation" class="cc-empty-workspace">
            <AppIcon name="messageSquare" size="40" class="cc-empty-icon" />
            <h2>Chọn cuộc trò chuyện</h2>
            <p>Chọn từ danh sách bên trái hoặc sử dụng Trợ lý AI để bắt đầu nhắn tin nhờ hỗ trợ.</p>
          </div>

          <!-- ACTIVE CHAT VIEW -->
          <div v-else class="cc-chat-box">
            <!-- CHAT HEADER -->
            <div class="cc-chat-head">
              <button
                type="button"
                class="cc-back-btn"
                @click="mobileActiveView = 'list'"
              >
                ← Danh sách
              </button>

              <div class="cc-head-user">
                <div v-if="activeConversation.is_ai" class="cc-avatar cc-avatar--ai cc-avatar--sm">
                  <AppIcon name="sparkles" size="16" />
                </div>
                <img
                  v-else-if="activeConversation.avatar_url"
                  :src="activeConversation.avatar_url"
                  class="cc-avatar-img cc-avatar--sm"
                  alt="Avatar"
                />
                <div
                  v-else
                  class="cc-avatar cc-avatar--sm"
                  :style="{ backgroundColor: getAvatarColor(activeConversation.title) }"
                >
                  {{ getInitial(activeConversation.title) }}
                </div>

                <div>
                  <h2 class="cc-head-name">{{ activeConversation.title }}</h2>
                  <span class="cc-head-status">
                    {{ conversationStatus(activeConversation) }}
                  </span>
                </div>
                <span v-if="activeConversation.type === 'venue_contact'" class="cc-venue-context">
                  <AppIcon name="mapPin" size="13" />
                  Cụm sân: {{ activeConversation.title }}
                </span>
              </div>

              <div class="cc-head-actions">
                <router-link
                  v-if="attachedBooking"
                  :to="{ name: 'booking-detail', params: { id: attachedBooking.id } }"
                  class="cc-head-btn"
                >
                  Xem đơn #{{ attachedBooking.booking_code }}
                </router-link>
              </div>
            </div>

            <!-- MESSAGES FEED -->
            <div ref="messagesFeed" class="cc-messages-feed">
              <!-- ATTACHED BOOKING INFO CARD -->
              <div v-if="attachedBooking" class="cc-attached-card">
                <div class="cc-att-head">
                  <AppIcon name="calendar" size="16" />
                  <span>Đơn đặt sân liên quan #{{ attachedBooking.booking_code }}</span>
                </div>
                <div class="cc-att-body">
                  <strong>{{ attachedBooking.venueCluster?.name || attachedBooking.venue_court?.name || "Sân thể thao" }}</strong>
                  <p>Ngày {{ formatDate(attachedBooking.booking_date) }} ({{ formatTime(attachedBooking.start_time) }} - {{ formatTime(attachedBooking.end_time) }})</p>
                </div>
              </div>

              <!-- MESSAGES LIST -->
              <div
                v-for="msg in messages"
                :key="msg.id"
                :class="[
                  'cc-msg-row',
                  isMyMessage(msg) ? 'cc-msg-row--me' : 'cc-msg-row--other'
                ]"
              >
                <!-- BOT AVATAR IF AI -->
                <div v-if="!isMyMessage(msg) && activeConversation.is_ai" class="cc-msg-avatar">
                  <AppIcon name="sparkles" size="14" />
                </div>

                <div class="cc-msg-bubble">
                  <div class="cc-msg-text" v-html="renderContent(msg)"></div>
                  <div v-if="msg.image_url" class="cc-msg-img-wrap">
                    <img :src="msg.image_url" class="cc-msg-img" alt="Ảnh gửi" />
                  </div>
                  <span class="cc-msg-time">{{ formatTime(msg.created_at) }}</span>
                </div>
              </div>

              <!-- LOADING AI TYPING -->
              <div v-if="sendingAi" class="cc-msg-row cc-msg-row--other">
                <div class="cc-msg-avatar">
                  <AppIcon name="sparkles" size="14" />
                </div>
                <div class="cc-msg-bubble cc-msg-bubble--typing">
                  <span class="cc-typing-dot"></span>
                  <span class="cc-typing-dot"></span>
                  <span class="cc-typing-dot"></span>
                </div>
              </div>
            </div>

            <!-- SMART QUICK PROMPT CHIPS (NO EMOJIS) -->
            <div class="cc-quick-chips">
              <button
                v-for="(chip, idx) in quickChips"
                :key="idx"
                type="button"
                class="cc-chip-btn"
                @click="sendQuickChip(chip)"
              >
                <span>{{ chip }}</span>
              </button>
            </div>

            <!-- INPUT BAR -->
            <div class="cc-input-bar">
              <label class="cc-attach-btn" title="Đính kèm ảnh">
                <AppIcon name="image" size="18" />
                <input
                  type="file"
                  accept="image/*"
                  class="cc-file-input"
                  @change="handleFileSelect"
                />
              </label>

              <div v-if="selectedFile" class="cc-file-preview">
                <span>{{ selectedFile.name }}</span>
                <button type="button" @click="selectedFile = null">✕</button>
              </div>

              <input
                v-model="inputContent"
                type="text"
                placeholder="Nhập nội dung tin nhắn nhờ hỗ trợ..."
                class="cc-chat-input"
                :disabled="sendingAi"
                @keyup.enter="sendMessage"
              />

              <button
                type="button"
                class="cc-send-btn"
                :disabled="(!inputContent.trim() && !selectedFile) || sendingAi"
                @click="sendMessage"
              >
                <span>Gửi</span>
              </button>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<script>
import AppIcon from "../../components/AppIcon.vue";
import PublicNavbar from "../../components/PublicNavbar.vue";
import { bookingService } from "../../services/bookingService.js";
import { chatService } from "../../services/chat.service.js";

export default {
  name: "ClientChat",
  components: { PublicNavbar, AppIcon },
  data() {
    return {
      activeTab: "all",
      searchQuery: "",
      conversations: [],
      activeConversation: null,
      messages: [],
      inputContent: "",
      selectedFile: null,
      loadingConversations: true,
      sendingAi: false,
      attachedBooking: null,
      mobileActiveView: "list",
      currentUser: null,
    };
  },
  computed: {
    filteredConversations() {
      let list = this.conversations;
      if (this.activeTab === "ai") {
        list = list.filter((c) => c.is_ai);
      } else if (this.activeTab === "venue") {
        list = list.filter((c) => !c.is_ai);
      }

      const q = this.searchQuery.trim().toLowerCase();
      if (q) {
        list = list.filter((c) => (c.title || "").toLowerCase().includes(q));
      }
      return list;
    },
    quickChips() {
      if (this.activeConversation?.is_ai) {
        return [
          "Tìm cụm sân còn trống tối nay",
          "Hướng dẫn quy định hoàn tiền ví",
          "Hỗ trợ đổi khung giờ chơi",
        ];
      }
      return [
        "Hỏi thông tin gửi xe ô tô",
        "Tôi muốn lùi giờ chơi 30 phút",
        "Sân có cho thuê thêm vợt không",
      ];
    },
  },
  async mounted() {
    this.loadCurrentUser();
    await this.fetchConversations();
    await this.handleQueryParams();
  },
  methods: {
    loadCurrentUser() {
      try {
        const raw = localStorage.getItem("user") || localStorage.getItem("auth_user");
        if (raw) this.currentUser = JSON.parse(raw);
      } catch (e) {
        this.currentUser = null;
      }
    },
    conversationStatus(conversation) {
      if (conversation?.is_ai) return "Trợ lý trí tuệ nhân tạo SportGo 24/7";
      if (conversation?.type === "venue_contact") {
        const ownerName = conversation.other_user?.full_name;
        return ownerName ? `Trao đổi với chủ sân · ${ownerName}` : "Trao đổi với chủ sân";
      }
      return "Cuộc trò chuyện trực tuyến";
    },
    async fetchConversations() {
      this.loadingConversations = true;
      try {
        const res = await chatService.getConversations();
        const baseList = res || [];

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
        console.error("Không thể tải danh sách hộp thư", err);
      } finally {
        this.loadingConversations = false;
      }
    },
    async handleQueryParams() {
      const q = this.$route.query;
      if (q.booking_id) {
        try {
          this.attachedBooking = await bookingService.getBooking(q.booking_id);
        } catch (e) {
          console.warn("Không thể tải thông tin đính kèm booking", e);
        }
      }

      if (q.conversation_id) {
        const target = this.conversations.find((c) => String(c.id) === String(q.conversation_id));
        if (target) {
          this.selectConversation(target);
          return;
        }
      }

      if (q.user_id || q.venue_id) {
        try {
          const res = await chatService.startConversation({
            type: q.venue_id ? "venue_contact" : "direct",
            user_id: q.user_id,
            venue_id: q.venue_id,
          });
          await this.fetchConversations();
          const found = this.conversations.find((c) => String(c.id) === String(res.id));
          if (found) this.selectConversation(found);
        } catch (e) {
          console.error("Lỗi tạo cuộc trò chuyện mới", e);
        }
      }

      // Default select AI if nothing specified
      if (!this.activeConversation && this.conversations.length > 0) {
        this.selectConversation(this.conversations[0]);
      }
    },
    selectConversation(conv) {
      this.activeConversation = conv;
      this.mobileActiveView = "chat";
      this.fetchMessages();
    },
    fetchMessages() {
      if (!this.activeConversation) return;

      if (this.activeConversation.is_ai) {
        chatService.getAiHistory()
          .then((res) => {
            if (res.messages && Array.isArray(res.messages) && res.messages.length > 0) {
              this.messages = res.messages.map((m) => ({
                ...m,
                sender_id: (m.sender_id === "me" || m.role === "user") ? "me" : "ai_assistant",
              }));
              this.saveAiMessages();
              this.scrollToBottom();
              return;
            }
            this.loadSavedLocalAiMessages();
          })
          .catch(() => {
            this.loadSavedLocalAiMessages();
          });
        return;
      }

      try {
        chatService.getMessages(this.activeConversation.id).then((res) => {
          this.messages = res.messages || [];
          this.scrollToBottom();
        });
      } catch (err) {
        console.error("Không thể tải danh sách tin nhắn", err);
      }
    },
    loadSavedLocalAiMessages() {
      try {
        const saved = localStorage.getItem("sportgo_ai_messages");
        if (saved) {
          const parsed = JSON.parse(saved);
          if (Array.isArray(parsed) && parsed.length > 0) {
            this.messages = parsed.map((m) => ({
              ...m,
              sender_id: (m.sender_id === "me" || m.role === "user") ? "me" : "ai_assistant",
            }));
            this.scrollToBottom();
            return;
          }
        }
      } catch (e) {
        console.warn("Lỗi đọc lịch sử chat AI local", e);
      }

      this.messages = [
        {
          id: "ai_welcome",
          sender_id: "ai_assistant",
          role: "assistant",
          content: "Xin chào! Tôi là Trợ lý AI của SportGo. Bạn cần hỗ trợ tìm kiếm sân đấu, tư vấn khung giờ chơi hay giải đáp thắc mắc nào hôm nay?",
          created_at: new Date().toISOString(),
        },
      ];
      this.saveAiMessages();
    },
    saveAiMessages() {
      try {
        localStorage.setItem("sportgo_ai_messages", JSON.stringify(this.messages));
      } catch (e) {
        console.warn("Lỗi lưu lịch sử chat AI", e);
      }
    },
    async sendMessage() {
      const text = this.inputContent.trim();
      if (!text && !this.selectedFile) return;

      // Handle AI Conversation
      if (this.activeConversation?.is_ai) {
        const userMsg = {
          id: "user_" + Date.now(),
          sender_id: "me",
          role: "user",
          content: text,
          created_at: new Date().toISOString(),
        };
        this.messages.push(userMsg);
        this.inputContent = "";
        this.saveAiMessages();
        this.scrollToBottom();

        this.sendingAi = true;
        try {
          const res = await chatService.askAiAssistant({
            prompt: text,
            booking_id: this.attachedBooking?.id || null,
          });

          const replyText = res?.reply || res?.data?.reply;
          if (replyText) {
            this.messages.push({
              id: "ai_" + Date.now(),
              sender_id: "ai_assistant",
              role: "assistant",
              content: replyText,
              created_at: new Date().toISOString(),
            });
          }
          this.saveAiMessages();
          this.scrollToBottom();
        } catch (e) {
          console.error("Lỗi kết nối AI:", e);
        } finally {
          this.sendingAi = false;
        }
        return;
      }

      // Handle Regular Chat
      try {
        const res = await chatService.sendMessage(
          this.activeConversation.id,
          text,
          this.selectedFile
        );
        this.messages.push(res);
        this.inputContent = "";
        this.selectedFile = null;
        this.scrollToBottom();
      } catch (err) {
        console.error("Lỗi gửi tin nhắn", err);
      }
    },
    sendQuickChip(chipText) {
      this.inputContent = chipText;
      this.sendMessage();
    },
    handleFileSelect(e) {
      const file = e.target.files[0];
      if (file) this.selectedFile = file;
    },
    isMyMessage(msg) {
      if (!msg) return false;
      if (
        msg.sender_id === "ai_assistant" ||
        msg.role === "assistant" ||
        msg.role === "system" ||
        msg.is_ai
      ) {
        return false;
      }
      if (
        msg.sender_id === "me" ||
        msg.role === "user" ||
        msg.is_user
      ) {
        return true;
      }
      if (this.currentUser?.id && String(msg.sender_id) === String(this.currentUser.id)) {
        return true;
      }
      if (this.activeConversation?.is_ai && msg.sender_id !== "ai_assistant") {
        return true;
      }
      return String(msg.sender_id) === String(this.currentUser?.id);
    },
    renderContent(msg) {
      const raw = msg.content || '';

      // Process line-by-line to correctly handle lists and paragraphs
      const lines = raw.split('\n');
      const outputParts = [];
      let inOrderedList = false;
      let inBulletList = false;

      const escapeLine = (str) =>
        str
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;');

      const applyInline = (str) => {
        // Bold **text**
        str = str.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        // Bold __text__
        str = str.replace(/__([^_]+)__/g, '<strong>$1</strong>');
        // Inline code
        str = str.replace(/`([^`]+)`/g, '<code>$1</code>');
        return str;
      };

      const closeLists = () => {
        if (inOrderedList) { outputParts.push('</ol>'); inOrderedList = false; }
        if (inBulletList)  { outputParts.push('</ul>'); inBulletList = false; }
      };

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

        // Numbered list: "1. " "2. " etc.
        const numMatch = line.match(/^(\d+)\. (.+)$/);
        if (numMatch) {
          if (inBulletList) { outputParts.push('</ul>'); inBulletList = false; }
          if (!inOrderedList) { outputParts.push('<ol class="cc-ordered-list">'); inOrderedList = true; }
          outputParts.push('<li class="cc-list-item">' + applyInline(escapeLine(numMatch[2])) + '</li>');
          continue;
        }

        // Bullet list: "- " or "* "
        const bulletMatch = line.match(/^[-*] (.+)$/);
        if (bulletMatch) {
          if (inOrderedList) { outputParts.push('</ol>'); inOrderedList = false; }
          if (!inBulletList) { outputParts.push('<ul class="cc-unordered-list">'); inBulletList = true; }
          outputParts.push('<li class="cc-list-item">' + applyInline(escapeLine(bulletMatch[1])) + '</li>');
          continue;
        }

        // Empty line = paragraph break
        if (line.trim() === '') {
          closeLists();
          outputParts.push('<br>');
          continue;
        }

        // Normal text line
        closeLists();
        outputParts.push(applyInline(escapeLine(line)) + '<br>');
      }

      closeLists();

      // Trim trailing <br> tags
      let html = outputParts.join('');
      html = html.replace(/(<br>\s*)+$/, '');
      return html;
    },
    scrollToBottom() {
      this.$nextTick(() => {
        const el = this.$refs.messagesFeed;
        if (el) el.scrollTop = el.scrollHeight;
      });
    },
    getInitial(name) {
      if (!name) return "S";
      return String(name).charAt(0).toUpperCase();
    },
    getAvatarColor(name) {
      if (!name) return "#15803d";
      const colors = ["#f97316", "#10b981", "#3b82f6", "#8b5cf6", "#ec4899", "#06b6d4"];
      let hash = 0;
      for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
      }
      return colors[Math.abs(hash) % colors.length];
    },
    formatDate(dateStr) {
      if (!dateStr) return "-";
      return new Date(dateStr).toLocaleDateString("vi-VN");
    },
    formatTime(timeStr) {
      if (!timeStr) return "";
      if (timeStr.includes("T")) {
        const d = new Date(timeStr);
        return d.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit", hour12: false });
      }
      return timeStr.substring(0, 5);
    },
  },
};
</script>

<style scoped>
.cc-page {
  height: 100vh;
  max-height: 100vh;
  overflow: hidden;
  background: #ffffff;
  display: flex;
  flex-direction: column;
}

.cc-main {
  flex: 1;
  max-width: 1200px;
  width: 100%;
  margin: 0 auto;
  padding: 12px 16px;
  display: flex;
  overflow: hidden;
  min-height: 0;
}

.cc-container {
  display: grid;
  grid-template-columns: 340px 1fr;
  width: 100%;
  height: 100%;
  min-height: 0;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  background: #ffffff;
}

@media (max-width: 800px) {
  .cc-container {
    grid-template-columns: 1fr;
  }
  .cc-sidebar--hidden-mobile {
    display: none !important;
  }
  .cc-workspace--hidden-mobile {
    display: none !important;
  }
}

/* SIDEBAR */
.cc-sidebar {
  border-right: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  background: #ffffff;
}

.cc-sidebar-head {
  padding: 16px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cc-sidebar-title {
  font-size: 18px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
}

.cc-search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.cc-search-icon {
  position: absolute;
  left: 10px;
  color: #94a3b8;
}

.cc-search-input {
  width: 100%;
  padding: 8px 10px 8px 32px;
  font-size: 13px;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  outline: none;
  background: #ffffff;
}

.cc-search-clear {
  position: absolute;
  right: 8px;
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
}

.cc-filter-tabs {
  display: flex;
  gap: 6px;
}

.cc-tab {
  padding: 5px 10px;
  font-size: 12px;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
}

.cc-tab.active {
  background: #15803d;
  color: #ffffff;
  border-color: #15803d;
}

.cc-conv-list {
  flex: 1;
  overflow-y: auto;
}

.cc-state-msg {
  padding: 24px;
  text-align: center;
  font-size: 13px;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.cc-conv-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border: none;
  border-bottom: 1px solid #f8fafc;
  background: #ffffff;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s;
}

.cc-conv-item:hover,
.cc-conv-item.active {
  background: #f8fafc;
}

.cc-avatar-wrap {
  position: relative;
  flex-shrink: 0;
}

.cc-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  aspect-ratio: 1 / 1;
  flex-shrink: 0;
  background: #15803d;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 500;
  font-size: 15px;
  box-sizing: border-box;
}

.cc-avatar--ai {
  background: #0f172a;
}

.cc-avatar--sm {
  width: 34px;
  height: 34px;
  font-size: 13px;
  flex-shrink: 0;
}

.cc-avatar-img {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  aspect-ratio: 1 / 1;
  flex-shrink: 0;
  object-fit: cover;
}

.cc-online-dot {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #22c55e;
  border: 2px solid #ffffff;
}

.cc-conv-info {
  flex: 1;
  min-width: 0;
}

.cc-conv-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.cc-conv-title {
  font-size: 13.5px;
  font-weight: 500;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cc-conv-time {
  font-size: 11px;
  color: #94a3b8;
}

.cc-conv-sub {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 2px;
}

.cc-conv-preview {
  font-size: 12.5px;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cc-unread-badge {
  background: #dc2626;
  color: #ffffff;
  font-size: 10px;
  font-weight: 500;
  border-radius: 10px;
  padding: 1px 6px;
}

/* WORKSPACE */
.cc-workspace {
  display: flex;
  flex-direction: column;
  background: #ffffff;
  min-height: 0;
  overflow: hidden;
  height: 100%;
}

.cc-empty-workspace {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px;
  text-align: center;
  color: #64748b;
}

.cc-empty-icon {
  color: #cbd5e1;
  margin-bottom: 12px;
}

.cc-empty-workspace h2 {
  font-size: 16px;
  font-weight: 500;
  color: #0f172a;
  margin: 0 0 6px;
}

.cc-empty-workspace p {
  font-size: 13px;
  margin: 0;
}

.cc-chat-box {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  overflow: hidden;
}

.cc-chat-head {
  padding: 12px 16px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cc-back-btn {
  display: none;
  background: transparent;
  border: none;
  color: #15803d;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}

@media (max-width: 800px) {
  .cc-back-btn {
    display: inline-block;
  }
}

.cc-head-user {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cc-head-name {
  font-size: 15px;
  font-weight: 500;
  color: #0f172a;
  margin: 0;
}

.cc-head-status {
  font-size: 12px;
  color: #64748b;
}

.cc-venue-context {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-left: auto;
  padding: 6px 10px;
  border: 1px solid #bbf7d0;
  border-radius: 999px;
  background: #f0fdf4;
  color: #166534;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

@media (max-width: 640px) {
  .cc-venue-context {
    display: none;
  }
}

.cc-head-btn {
  font-size: 12.5px;
  color: #15803d;
  text-decoration: none;
  font-weight: 500;
}

/* MESSAGES FEED */
.cc-messages-feed {
  flex: 1;
  min-height: 0;
  padding: 16px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: #fafafa;
}

.cc-attached-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 12px;
  font-size: 13px;
}

.cc-att-head {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #15803d;
  font-weight: 500;
  margin-bottom: 6px;
}

.cc-att-body strong {
  color: #0f172a;
}

.cc-att-body p {
  margin: 2px 0 0;
  color: #64748b;
  font-size: 12px;
}

.cc-msg-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  max-width: 80%;
}

.cc-msg-row--me {
  align-self: flex-end;
  flex-direction: row-reverse;
}

.cc-msg-row--other {
  align-self: flex-start;
}

.cc-msg-avatar {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  aspect-ratio: 1 / 1;
  flex-shrink: 0;
  background: #0f172a;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 4px;
}

.cc-msg-bubble {
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 13.5px;
  line-height: 1.45;
  position: relative;
}

.cc-msg-row--me .cc-msg-bubble {
  background: #15803d;
  color: #ffffff;
  border-bottom-right-radius: 2px;
}

.cc-msg-row--other .cc-msg-bubble {
  background: #ffffff;
  color: #0f172a;
  border: 1px solid #e2e8f0;
  border-bottom-left-radius: 2px;
}

.cc-msg-time {
  display: block;
  font-size: 10px;
  margin-top: 4px;
  opacity: 0.7;
  text-align: right;
}

/* Rendered markdown inside AI bubbles */
.cc-msg-text :deep(strong) {
  font-weight: 600;
}

.cc-msg-text :deep(code) {
  background: rgba(0, 0, 0, 0.08);
  border-radius: 3px;
  padding: 1px 5px;
  font-family: monospace;
  font-size: 12.5px;
}

.cc-msg-row--me .cc-msg-text :deep(code) {
  background: rgba(255, 255, 255, 0.2);
}

.cc-msg-text :deep(.cc-ordered-list),
.cc-msg-text :deep(.cc-unordered-list) {
  margin: 6px 0 2px;
  padding-left: 20px;
}

.cc-msg-text :deep(.cc-ordered-list) {
  list-style-type: decimal;
}

.cc-msg-text :deep(.cc-unordered-list) {
  list-style-type: disc;
}

.cc-msg-text :deep(.cc-list-item) {
  margin-bottom: 3px;
  line-height: 1.5;
}

.cc-msg-img-wrap {
  margin-top: 6px;
  border-radius: 6px;
  overflow: hidden;
}

.cc-msg-img {
  max-width: 200px;
  max-height: 200px;
  display: block;
}

.cc-msg-bubble--typing {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 12px 16px;
}

.cc-typing-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #94a3b8;
  animation: typing 1s infinite alternate;
}

.cc-typing-dot:nth-child(2) {
  animation-delay: 0.2s;
}

.cc-typing-dot:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typing {
  from {
    opacity: 0.3;
  }
  to {
    opacity: 1;
  }
}

/* QUICK CHIPS (NO EMOJIS) */
.cc-quick-chips {
  padding: 8px 16px;
  display: flex;
  gap: 8px;
  overflow-x: auto;
  background: #ffffff;
  border-top: 1px solid #f1f5f9;
}

.cc-chip-btn {
  padding: 5px 12px;
  font-size: 12px;
  border-radius: 16px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #334155;
  cursor: pointer;
  white-space: nowrap;
}

.cc-chip-btn:hover {
  border-color: #15803d;
  color: #15803d;
}

/* INPUT BAR */
.cc-input-bar {
  padding: 12px 16px;
  border-top: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  gap: 10px;
  background: #ffffff;
}

.cc-attach-btn {
  color: #64748b;
  cursor: pointer;
  display: flex;
  align-items: center;
}

.cc-file-input {
  display: none;
}

.cc-file-preview {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  background: #f1f5f9;
  padding: 4px 8px;
  border-radius: 4px;
}

.cc-file-preview button {
  background: transparent;
  border: none;
  color: #dc2626;
  cursor: pointer;
}

.cc-chat-input {
  flex: 1;
  padding: 10px 14px;
  font-size: 13.5px;
  border: 1px solid #cbd5e1;
  border-radius: 20px;
  outline: none;
  background: #ffffff;
}

.cc-send-btn {
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 500;
  border-radius: 18px;
  border: none;
  background: #15803d;
  color: #ffffff;
  cursor: pointer;
}

.cc-send-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.cc-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid #cbd5e1;
  border-top-color: #15803d;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>

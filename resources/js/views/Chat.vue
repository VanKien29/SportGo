<template>
  <div
    :class="['chat-page flex flex-col font-sans', isAdmin ? 'admin-chat-page admin-chat' : 'bg-zinc-950 min-h-screen text-zinc-100']"
    :data-admin-chat="isAdmin ? '' : undefined"
  >
    <!-- Navbar -->
    <PublicNavbar v-if="!isAdmin" theme="dark" />

    <!-- Chat Workspace -->
    <div 
      :class="[
        'flex-1 flex overflow-hidden relative',
        isAdmin ? 'bg-zinc-950 admin-chat-workspace' : 'border-t border-zinc-800 h-[calc(100vh-64px)]'
      ]"
    >
      
      <!-- Left Sidebar: Chat List -->
      <div 
        :class="[
          'w-full md:w-[360px] shrink-0 border-r border-zinc-800 flex flex-col h-full bg-zinc-900/50 backdrop-blur-md md:flex relative overflow-hidden',
          mobileShowChat ? 'hidden' : 'flex'
        ]"
      >
        <!-- Telegram Menu Drawer Overlay -->
        <div 
          v-if="showTelegramMenu"
          class="absolute inset-0 bg-transparent z-40"
          @click="closeTelegramMenu"
        ></div>

        <!-- Telegram Menu Drawer Panel -->
        <div 
          :class="[
            'absolute inset-y-0 left-0 w-[280px] z-50 flex flex-col transition-transform duration-300 tg-drawer-panel',
            showTelegramMenu ? 'translate-x-0' : '-translate-x-full'
          ]"
        >
          <!-- Drawer Profile Header -->
          <div class="tg-drawer-header">
            <div class="h-11 w-11 rounded-full bg-orange-500 flex items-center justify-center font-semibold text-sm text-white select-none">
              {{ currentUser.full_name?.charAt(0).toUpperCase() }}
            </div>
            
            <div class="flex items-center justify-between mt-1.5">
              <div class="min-w-0">
                <div class="font-semibold text-[13px] tg-drawer-header-name truncate">{{ currentUser.full_name }}</div>
                <div class="text-[11px] tg-drawer-header-sub mt-0.5">{{ currentUser.email || currentUser.phone || '' }}</div>
              </div>
            </div>
          </div>

          <!-- Drawer Navigation Items -->
          <div class="flex-1 overflow-y-auto tg-drawer-nav">
            <div class="py-2 px-2">
              <a href="/admin/profile" class="tg-drawer-item">
                <svg class="tg-drawer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Hồ sơ của tôi</span>
              </a>

              <button @click="closeTelegramMenu" class="tg-drawer-item text-left">
                <svg class="tg-drawer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>Nhóm mới</span>
              </button>

              <button @click="closeTelegramMenu" class="tg-drawer-item text-left">
                <svg class="tg-drawer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                </svg>
                <span>Kênh mới</span>
              </button>

              <button @click="closeTelegramMenu" class="tg-drawer-item text-left">
                <svg class="tg-drawer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>Danh bạ</span>
              </button>

              <button @click="closeTelegramMenu" class="tg-drawer-item text-left">
                <svg class="tg-drawer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                <span>Cuộc gọi</span>
              </button>

              <button @click="closeTelegramMenu" class="tg-drawer-item text-left">
                <svg class="tg-drawer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                </svg>
                <span>Tin nhắn đã lưu</span>
              </button>

              <a href="/admin/settings" class="tg-drawer-item">
                <svg class="tg-drawer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Cài đặt</span>
              </a>
            </div>

            <!-- Divider -->
            <div class="tg-drawer-divider"></div>

            <!-- Theme Toggling Option -->
            <div class="tg-drawer-toggle-row">
              <div class="flex items-center gap-4 select-none">
                <svg class="tg-drawer-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
                <span class="text-sm">Chế độ tối</span>
              </div>
              
              <!-- Custom Toggle Switch -->
              <button 
                type="button" 
                @click="toggleNightMode"
                class="tg-toggle-switch"
                :class="isNightMode ? 'tg-toggle-on' : 'tg-toggle-off'"
                aria-label="Chế độ tối"
              >
                <span 
                  class="tg-toggle-knob"
                  :class="isNightMode ? 'tg-knob-on' : 'tg-knob-off'"
                ></span>
              </button>
            </div>
          </div>
        </div>

        <!-- Sidebar Header / Search -->
        <div class="p-3 flex items-center gap-2 tg-sidebar-header shrink-0">
          <button 
            @click="toggleTelegramMenu"
            class="p-2 -ml-1 rounded-full text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/40 transition-colors shrink-0"
            aria-label="Menu"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <div class="relative flex-1">
            <input 
              v-model="searchQuery"
              @input="handleSearch"
              type="text" 
              placeholder="Tìm kiếm..." 
              class="w-full pl-9 pr-8 py-1.5 bg-zinc-950/60 border border-zinc-800 rounded-full text-xs placeholder-zinc-400 text-zinc-100 focus:outline-none focus:border-zinc-700 transition-all tg-search-input"
            />
            <button 
              v-if="searchQuery" 
              @click="clearSearch"
              class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-zinc-400 hover:text-zinc-650"
            >
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Search Results List -->
        <div v-if="searchQuery" class="flex-1 overflow-y-auto divide-y divide-zinc-800/40">
          <div v-if="searching" class="p-4 text-center text-xs text-zinc-500">
            Đang tìm kiếm...
          </div>
          <div v-else-if="searchResults.length === 0" class="p-4 text-center text-xs text-zinc-500">
            Không tìm thấy thành viên nào.
          </div>
          <button 
            v-for="user in searchResults" 
            :key="user.id"
            @click="clickSearchResult(user)"
            class="tg-conv-item w-full transition-all"
          >
            <div class="tg-avatar tg-avatar-small">
              {{ user.full_name.charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-medium text-sm text-zinc-200 truncate">{{ user.full_name }}</div>
              <div class="text-xs text-zinc-500 truncate">@{{ user.username || 'user' }}</div>
            </div>
          </button>
        </div>

        <!-- Main Conversations List -->
        <div v-else class="flex-1 overflow-y-auto divide-y divide-zinc-800/30">
          <div v-if="loadingConversations && conversations.length === 0" class="p-4 text-center text-xs text-zinc-500">
            Đang tải hộp thư...
          </div>
          <div v-else-if="filteredConversations.length === 0" class="chat-empty-sidebar">
            <div class="chat-empty-sidebar__title">Chưa có cuộc trò chuyện</div>
            <div class="chat-empty-sidebar__sub">Tìm kiếm thành viên để bắt đầu nhắn tin</div>
          </div>
          
          <button 
            v-for="conv in filteredConversations" 
            :key="conv.id"
            @click="selectConversation(conv)"
            :class="[
              'tg-conv-item w-full transition-all',
              activeConversation?.id === conv.id ? 'active' : ''
            ]"
          >
            <!-- Avatar -->
            <div class="tg-avatar">
              {{ conv.title.charAt(0).toUpperCase() }}
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-1 mb-1">
                <div class="font-medium text-sm text-zinc-200 truncate flex-1">{{ conv.title }}</div>
                <div class="text-[10px] text-zinc-500 shrink-0">{{ formatTime(conv.last_message?.created_at || conv.last_message_at) }}</div>
              </div>
              <div class="flex items-center justify-between gap-2">
                <div class="text-xs text-zinc-400 truncate flex-1">
                  <span v-if="conv.last_message?.sender_id === currentUser.id" class="text-green-500">Bạn: </span>
                  {{ conv.last_message?.content || 'Chưa có tin nhắn' }}
                </div>
                <!-- Badge count -->
                <div v-if="conv.unread_count > 0" class="h-5 min-w-5 px-1.5 bg-green-500 text-zinc-950 font-bold text-[10px] rounded-full flex items-center justify-center shrink-0">
                  {{ conv.unread_count }}
                </div>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- Right Main Chat Workspace -->
      <div 
        :class="[
          'flex-1 flex flex-col h-full bg-zinc-950 relative md:flex',
          mobileShowChat ? 'flex' : 'hidden'
        ]"
      >
        <!-- No Active Conversation state -->
        <div v-if="!activeConversation" class="chat-empty-main">
          <div class="chat-empty-main__title">Chọn cuộc trò chuyện</div>
          <div class="chat-empty-main__sub">Chọn từ danh sách bên trái hoặc tìm kiếm thành viên để bắt đầu nhắn tin</div>
        </div>

        <!-- Active Conversation Area -->
        <div v-else class="flex-1 flex flex-col h-full bg-zinc-950 relative">
          <!-- Active Conversation Header -->
          <div class="tg-chat-header flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3 min-w-0">
              <!-- Back button on Mobile -->
              <button 
                @click="backToList" 
                class="p-2 -ml-2 rounded-lg text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/40 md:hidden transition-colors"
                aria-label="Back to conversations list"
              >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
              </button>

              <div class="tg-avatar tg-avatar-small">
                {{ activeConversation.title.charAt(0).toUpperCase() }}
              </div>
              <div class="min-w-0">
                <div class="font-medium text-sm text-zinc-100 truncate flex items-center gap-2">
                  <span>{{ activeConversation.title }}</span>
                  <span v-if="activeConversation.type === 'venue_contact'" class="px-1.5 py-0.5 bg-green-500/10 text-green-400 text-[9px] font-bold rounded border border-green-500/20 uppercase tracking-wider shrink-0">
                    Sân đấu
                  </span>
                </div>
                <div class="text-[10px] text-zinc-500 flex items-center gap-1">
                  <span class="h-1.5 w-1.5 bg-green-500 rounded-full shrink-0"></span>
                  <span>Trực tuyến</span>
                </div>
              </div>
            </div>

            <!-- Header Action Controls -->
            <div class="flex items-center gap-1">
              <button class="p-2 rounded-lg text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800/30 transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Messages Scroll View Area -->
          <div ref="messageContainer" class="tg-message-container flex-1 overflow-y-auto bg-zinc-950">
            <div v-if="loadingMessages && messages.length === 0" class="text-center text-xs text-zinc-500 py-4">
              Đang tải tin nhắn...
            </div>
            
            <div v-else-if="messages.length === 0" class="text-center text-xs text-zinc-600 py-12 flex flex-col items-center gap-2">
              <div class="text-lg">👋</div>
              <div>Chưa có tin nhắn nào. Hãy gửi lời chào đầu tiên!</div>
            </div>

            <!-- Grouped Messages -->
            <div 
              v-else 
              v-for="group in groupedMessages" 
              :key="group.date" 
              class="space-y-4"
            >
              <!-- Date Divider Separator -->
              <div class="flex justify-center sticky top-0 z-10 py-3 my-2">
                <span class="tg-date-divider">
                  {{ group.date }}
                </span>
              </div>

              <!-- Message Bubbles -->
              <div 
                v-for="msg in group.messages" 
                :key="msg.id" 
                :class="['bubble-row flex', msg.sender_id === currentUser.id ? 'justify-end' : 'justify-start']"
              >
                <div 
                  :class="[
                    'bubble max-w-[70%] px-3 py-2 shadow-sm text-sm break-words',
                    msg.sender_id === currentUser.id ? 'bubble-sent' : 'bubble-received'
                  ]"
                >
                  <!-- Content text -->
                  <div class="bubble-text">
                    {{ msg.content }}
                    <span class="bubble-meta">
                      <span class="bubble-time">{{ formatTimeOnly(msg.created_at) }}</span>
                      
                      <!-- Read checkmarks logic for sent messages -->
                      <span v-if="msg.sender_id === currentUser.id" class="bubble-ticks inline-flex">
                        <!-- Read (Double check) -->
                        <svg v-if="isMessageRead(msg)" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <!-- Sent but unread (Single check) -->
                        <svg v-else class="h-3 w-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                      </span>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Bottom Message Input Editor (Telegram Style) -->
          <div class="tg-input-bar-container p-3 shrink-0 flex justify-center bg-transparent">
            <form @submit.prevent="submitMessage" class="flex gap-2 items-center w-full max-w-3xl">
              
              <!-- Left Action: File Attachment -->
              <button 
                type="button"
                @click="clickAttachment"
                class="tg-attach-btn p-2 rounded-full hover:bg-black/10 transition-colors shrink-0 flex items-center justify-center text-zinc-400 hover:text-zinc-600"
              >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
              </button>

              <!-- Main Input Area (Pill shape) -->
              <div class="flex-1 relative flex items-center">
                <input 
                  v-model="newMessage"
                  type="text" 
                  placeholder="Viết tin nhắn..." 
                  class="tg-input w-full pl-4 pr-10 py-2.5 bg-white border border-zinc-200 text-zinc-900 rounded-2xl text-sm focus:outline-none focus:border-zinc-300 transition-all shadow-sm"
                />
              </div>

              <!-- Right Action: Circular Send Button -->
              <button 
                type="submit"
                :disabled="!newMessage.trim()"
                class="tg-send-btn h-10 w-10 bg-green-500 disabled:opacity-40 disabled:hover:bg-green-500 hover:bg-green-600 text-white rounded-full transition-all shadow-sm shrink-0 flex items-center justify-center"
              >
                <svg class="h-5 w-5 fill-white text-white" viewBox="0 0 24 24">
                  <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
              </button>
            </form>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
import PublicNavbar from '../components/PublicNavbar.vue';
import { getAuth } from '../stores/auth.js';
import { chatService } from '../services/chat.service.js';

export default {
  name: 'Chat',
  components: {
    PublicNavbar
  },
  data() {
    return {
      currentUser: getAuth(),
      conversations: [],
      activeConversation: null,
      messages: [],
      activeConversationParticipants: [],
      newMessage: '',
      searchQuery: '',
      searchResults: [],
      searching: false,
      loadingConversations: false,
      loadingMessages: false,
      selectedTab: 'all', // 'all', 'direct', 'venue_contact'
      mobileShowChat: false,
      conversationsTimer: null,
      messagesTimer: null,
      showTelegramMenu: false,
      isNightMode: false
    };
  },
  computed: {
    filteredConversations() {
      if (this.selectedTab === 'all') return this.conversations;
      return this.conversations.filter(c => c.type === this.selectedTab);
    },
    groupedMessages() {
      return this.groupMessages(this.messages);
    },
    isAdmin() {
      return this.$route.path.startsWith('/admin');
    }
  },
  created() {
    // If not authenticated, redirect to login page
    if (!this.currentUser) {
      this.$router.push('/login');
      return;
    }

    // Chat-local dark mode: check localStorage for chat-specific preference
    const chatThemePref = localStorage.getItem('chat-theme');
    if (chatThemePref === 'dark') {
      this.isNightMode = true;
    } else if (chatThemePref === 'light') {
      this.isNightMode = false;
    } else {
      // Default: follow system admin theme
      this.isNightMode = document.documentElement.getAttribute('data-theme') === 'dark';
    }

    // Load list of conversations on creation
    this.fetchConversations(true);

    // Set polling for conversations list (every 5 seconds)
    this.conversationsTimer = setInterval(() => {
      this.fetchConversations(false);
    }, 5000);
  },
  mounted() {
    // Apply chat-local theme class on mount
    if (this.isNightMode) {
      const chatPage = this.$el?.closest('.chat-page') || this.$el;
      if (chatPage) {
        chatPage.classList.add('chat-dark');
        chatPage.classList.remove('chat-light');
      }
    }

    // If target query parameter exists (e.g. starting chat with a user or venue)
    const targetUserId = this.$route.query.userId;
    const targetVenueId = this.$route.query.venueId;
    if (targetUserId) {
      this.startChat({ type: 'direct', user_id: targetUserId });
    } else if (targetVenueId) {
      this.startChat({ type: 'venue_contact', venue_id: targetVenueId });
    }
  },
  beforeUnmount() {
    // Clear all polling timers
    if (this.conversationsTimer) clearInterval(this.conversationsTimer);
    if (this.messagesTimer) clearInterval(this.messagesTimer);
  },
  watch: {
    // Clear and restart messages polling when active conversation changes
    activeConversation(newVal, oldVal) {
      if (this.messagesTimer) {
        clearInterval(this.messagesTimer);
        this.messagesTimer = null;
      }
      
      if (newVal) {
        this.fetchMessages(true);
        // Poll active conversation messages (every 3 seconds)
        this.messagesTimer = setInterval(() => {
          this.fetchMessages(false);
        }, 3000);
      } else {
        this.messages = [];
        this.activeConversationParticipants = [];
      }
    }
  },
  methods: {
    toggleTelegramMenu() {
      this.showTelegramMenu = !this.showTelegramMenu;
    },
    closeTelegramMenu() {
      this.showTelegramMenu = false;
    },
    toggleNightMode() {
      this.isNightMode = !this.isNightMode;
      const chatTheme = this.isNightMode ? 'dark' : 'light';
      localStorage.setItem('chat-theme', chatTheme);
      // Toggle dark class on the chat-page element only (not the whole admin system)
      this.$nextTick(() => {
        const chatPage = this.$el?.closest('.chat-page') || this.$el;
        if (chatPage) {
          chatPage.classList.toggle('chat-dark', this.isNightMode);
          chatPage.classList.toggle('chat-light', !this.isNightMode);
        }
      });
    },
    async fetchConversations(showLoader = false) {
      if (showLoader) this.loadingConversations = true;
      try {
        const response = await chatService.getConversations();
        this.conversations = response || [];
        
        // If there's an active conversation, update its metadata locally
        if (this.activeConversation) {
          const updated = this.conversations.find(c => c.id === this.activeConversation.id);
          if (updated) {
            this.activeConversation.unread_count = 0; // It's currently active/open
          }
        }
      } catch (err) {
        console.error('Failed to load conversations', err);
      } finally {
        this.loadingConversations = false;
      }
    },

    async fetchMessages(showLoader = false) {
      if (!this.activeConversation) return;
      if (showLoader) this.loadingMessages = true;
      try {
        const response = await chatService.getMessages(this.activeConversation.id);
        
        const previousLength = this.messages.length;
        this.messages = response.messages || [];
        this.activeConversationParticipants = response.participants || [];

        // Scroll to bottom if new messages arrived
        if (this.messages.length > previousLength) {
          this.scrollToBottom();
        }

        // If there are unread messages from the other user, mark the chat as read
        const hasUnread = this.conversations.some(c => c.id === this.activeConversation.id && c.unread_count > 0);
        if (hasUnread || showLoader) {
          this.markConversationAsRead();
        }
      } catch (err) {
        console.error('Failed to load messages', err);
      } finally {
        this.loadingMessages = false;
      }
    },

    async selectConversation(conv) {
      this.activeConversation = conv;
      this.mobileShowChat = true;
      this.newMessage = '';
      
      // Instantly clear unread count for local visual responsiveness
      conv.unread_count = 0;
      this.scrollToBottom();
    },

    async markConversationAsRead() {
      if (!this.activeConversation) return;
      try {
        await chatService.markAsRead(this.activeConversation.id);
        // Sync local list
        const conv = this.conversations.find(c => c.id === this.activeConversation.id);
        if (conv) conv.unread_count = 0;
      } catch (err) {
        console.error('Failed to mark conversation as read', err);
      }
    },

    async submitMessage() {
      if (!this.newMessage.trim() || !this.activeConversation) return;
      
      const content = this.newMessage.trim();
      this.newMessage = ''; // clear input instantly for native feel
      
      try {
        const response = await chatService.sendMessage(this.activeConversation.id, content);
        this.messages.push(response);
        
        // Update last message in the conversations list locally
        const conv = this.conversations.find(c => c.id === this.activeConversation.id);
        if (conv) {
          conv.last_message = {
            content: content,
            created_at: new Date().toISOString(),
            sender_id: this.currentUser.id
          };
          conv.last_message_at = new Date().toISOString();
        }

        // Re-sort conversations list
        this.conversations.sort((a, b) => new Date(b.last_message_at) - new Date(a.last_message_at));
        
        this.scrollToBottom();
      } catch (err) {
        alert(err.message || 'Không thể gửi tin nhắn.');
      }
    },

    async handleSearch() {
      const query = this.searchQuery.trim();
      if (query.length < 2) {
        this.searchResults = [];
        return;
      }
      
      this.searching = true;
      try {
        const response = await chatService.searchUsers(query);
        this.searchResults = response || [];
      } catch (err) {
        console.error('Failed to search members', err);
      } finally {
        this.searching = false;
      }
    },

    clearSearch() {
      this.searchQuery = '';
      this.searchResults = [];
    },

    async clickSearchResult(user) {
      this.clearSearch();
      this.startChat({ type: 'direct', user_id: user.id });
    },

    async startChat(payload) {
      try {
        const response = await chatService.startConversation(payload);
        const conversationId = response.id;
        
        // Refresh conversations list to include new chat if not loaded
        await this.fetchConversations(true);
        
        const conv = this.conversations.find(c => c.id === conversationId);
        if (conv) {
          this.selectConversation(conv);
        } else {
          // Fallback if missing
          this.activeConversation = {
            id: conversationId,
            title: payload.type === 'direct' ? 'Người dùng' : 'Sân đấu',
            type: payload.type
          };
          this.mobileShowChat = true;
        }
      } catch (err) {
        alert(err.message || 'Không thể tạo phòng trò chuyện.');
      }
    },

    backToList() {
      this.mobileShowChat = false;
    },

    clickAttachment() {
      alert('Đính kèm tệp là tính năng cao cấp đang được phát triển!');
    },

    scrollToBottom() {
      this.$nextTick(() => {
        const container = this.$refs.messageContainer;
        if (container) {
          container.scrollTo({
            top: container.scrollHeight,
            behavior: 'smooth'
          });
        }
      });
    },

    isMessageRead(msg) {
      if (!this.activeConversation || msg.sender_id !== this.currentUser.id) return false;
      
      const otherParticipant = this.activeConversationParticipants.find(
        p => p.user_id !== this.currentUser.id
      );
      if (!otherParticipant || !otherParticipant.last_read_at) return false;

      const msgTime = new Date(msg.created_at).getTime();
      const readTime = new Date(otherParticipant.last_read_at).getTime();
      return readTime >= msgTime;
    },

    groupMessages(messages) {
      if (!messages || messages.length === 0) return [];
      const groups = [];
      let currentGroup = null;

      messages.forEach(msg => {
        const dateStr = this.formatGroupDate(msg.created_at);
        if (!currentGroup || currentGroup.date !== dateStr) {
          currentGroup = {
            date: dateStr,
            messages: []
          };
          groups.push(currentGroup);
        }
        currentGroup.messages.push(msg);
      });
      return groups;
    },

    formatGroupDate(dateStr) {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      const today = new Date();
      const yesterday = new Date();
      yesterday.setDate(today.getDate() - 1);

      if (date.toDateString() === today.toDateString()) {
        return 'Hôm nay';
      } else if (date.toDateString() === yesterday.toDateString()) {
        return 'Hôm qua';
      } else {
        return date.toLocaleDateString('vi-VN', {
          day: 'numeric',
          month: 'long',
          year: 'numeric'
        });
      }
    },

    formatTime(timeStr) {
      if (!timeStr) return '';
      const date = new Date(timeStr);
      const today = new Date();
      if (date.toDateString() === today.toDateString()) {
        return date.toLocaleTimeString('vi-VN', {
          hour: '2-digit',
          minute: '2-digit',
          hour12: false
        });
      }
      return date.toLocaleDateString('vi-VN', {
        day: 'numeric',
        month: 'short'
      });
    },

    formatTimeOnly(timeStr) {
      if (!timeStr) return '';
      const date = new Date(timeStr);
      return date.toLocaleTimeString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
      });
    }
  }
};
</script>

<style scoped>
.chat-page {
  /* Default Light Theme Variables */
  --tg-chat-bg: #e7ebf0;
  --tg-sent-bg: #e2f7cb;
  --tg-sent-text: #1a2510;
  --tg-received-bg: #ffffff;
  --tg-received-text: #1f1f1f;
  --tg-meta: #8c9094;
  --tg-meta-sent: #508531;
  --tg-input-bg: #ffffff;
  --tg-input-text: #1f1f1f;
  --tg-sidebar-bg: #ffffff;
  --tg-active-row: #f1f5f9;
  --tg-header-bg: #ffffff;
  --tg-border: #e2e8f0;
  --tg-ticks: #4fae4e;
  --tg-accent: var(--admin-primary, #22a653);
  
  height: 100vh;
  overflow: hidden;
  background-color: var(--tg-sidebar-bg);
}

.chat-page:not(.admin-chat-page) {
  height: 100vh;
  overflow: hidden;
}

.admin-chat-page {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
  height: auto;
}

.admin-chat-workspace {
  flex: 1;
  min-height: 0;
}

/* Group container classes */
.tg-sidebar-header {
  height: 64px;
  background-color: var(--tg-sidebar-bg);
  border-bottom: 1px solid var(--tg-border);
  padding: 0 16px;
  display: flex;
  align-items: center;
  box-sizing: border-box;
}
.tg-search-input {
  background-color: var(--tg-chat-bg) !important;
  border-color: var(--tg-border) !important;
  color: var(--tg-received-text) !important;
}
.tg-search-input::placeholder {
  color: var(--tg-meta) !important;
}

/* Sidebar conversation row spacing */
.tg-conv-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background-color: transparent;
  border-bottom: 1px solid var(--tg-border);
  text-align: left;
}
.tg-conv-item:hover {
  background-color: var(--tg-active-row) !important;
}
.tg-conv-item.active {
  background-color: var(--tg-active-row) !important;
}

/* Chat Header layout spacing */
.tg-chat-header {
  height: 64px;
  background-color: var(--tg-header-bg);
  border-bottom: 1px solid var(--tg-border);
  padding: 0 20px;
  display: flex;
  align-items: center;
}

/* Messages container spacing */
.tg-message-container {
  display: flex;
  flex-direction: column;
  padding: 24px 28px;
  gap: 16px;
  background-color: var(--tg-chat-bg);
}

/* Chat bubble styling exactly like Telegram */
.bubble-row {
  margin-bottom: 12px;
  width: 100%;
}

.bubble {
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  font-size: 14px;
  line-height: 1.5;
  display: inline-block;
  padding: 10px 14px;
}

.bubble-sent {
  background-color: var(--tg-sent-bg) !important;
  color: var(--tg-sent-text) !important;
  border-bottom-right-radius: 4px;
}
.bubble-sent .bubble-text {
  color: var(--tg-sent-text) !important;
}

.bubble-received {
  background-color: var(--tg-received-bg) !important;
  color: var(--tg-received-text) !important;
  border-bottom-left-radius: 4px;
  border: 1px solid var(--tg-border);
}
.bubble-received .bubble-text {
  color: var(--tg-received-text) !important;
}

.bubble-text {
  word-wrap: break-word;
  white-space: pre-wrap;
}

.bubble-meta {
  float: right;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-left: 8px;
  margin-top: 4px;
  position: relative;
  bottom: -2px;
  font-size: 9px;
  user-select: none;
  pointer-events: none;
}

.bubble-sent .bubble-time {
  color: var(--tg-meta-sent) !important;
}
.bubble-sent .bubble-ticks {
  color: var(--tg-ticks) !important;
}

.bubble-received .bubble-time {
  color: var(--tg-meta) !important;
}

/* Input elements spacing */
.tg-input-bar-container {
  background-color: var(--tg-header-bg);
  border-top: 1px solid var(--tg-border);
  padding: 12px 20px;
}

.tg-input {
  background-color: var(--tg-chat-bg) !important;
  border: 1px solid var(--tg-border) !important;
  color: var(--tg-received-text) !important;
}
.tg-input::placeholder {
  color: var(--tg-meta) !important;
}
.tg-input:focus {
  border-color: var(--tg-accent) !important;
}

.tg-send-btn {
  background-color: var(--tg-accent) !important;
}
.tg-send-btn:hover {
  opacity: 0.9;
}

.tg-attach-btn {
  color: var(--tg-meta) !important;
}
.tg-attach-btn:hover {
  color: var(--tg-received-text) !important;
  background-color: var(--tg-active-row) !important;
}

/* Empty States */
.chat-empty-sidebar {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 40px 20px;
  text-align: center;
}
.chat-empty-sidebar__title {
  font-size: 13px;
  font-weight: 600;
  color: var(--tg-meta);
}
.chat-empty-sidebar__sub {
  font-size: 11px;
  color: var(--tg-meta);
  opacity: 0.8;
  line-height: 1.5;
}

.chat-empty-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 48px 32px;
  text-align: center;
  background-color: var(--tg-chat-bg);
}
.chat-empty-main__title {
  font-size: 15px;
  font-weight: 600;
  color: var(--tg-received-text);
}
.chat-empty-main__sub {
  font-size: 12px;
  color: var(--tg-meta);
  max-width: 280px;
  line-height: 1.6;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
  width: 6px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: var(--tg-border);
  border-radius: 9999px;
}
::-webkit-scrollbar-thumb:hover {
  background: var(--tg-meta);
}

/* ── Avatars ────────────────────────────────── */
.tg-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background-color: var(--tg-accent) !important;
  color: #ffffff !important;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  flex-shrink: 0;
  border: 1px solid rgba(0, 0, 0, 0.05);
}

.tg-avatar-small {
  width: 40px;
  height: 40px;
  font-size: 14px;
}

/* ── Date Divider ───────────────────────────── */
.tg-date-divider {
  display: inline-block;
  padding: 4px 14px;
  background-color: transparent !important;
  color: var(--tg-meta, #8c9094) !important;
  font-size: 11px;
  font-weight: 600;
  border-radius: 0;
  box-shadow: none;
  backdrop-filter: none;
}

/* Dark theme overrides for date divider text */
.chat-page.bg-zinc-950 .tg-date-divider,
[data-theme="dark"] .chat-page .tg-date-divider {
  background-color: transparent !important;
  color: var(--tg-meta, #7a8e9e) !important;
}

/* ── Telegram Menu Drawer ────────────────────── */
.tg-drawer-panel {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: none !important;
  background-color: var(--tg-sidebar-bg) !important;
  border-right: 1px solid var(--tg-border) !important;
}

.tg-drawer-header {
  background-color: var(--tg-header-bg) !important;
  border-bottom: 1px solid var(--tg-border) !important;
  padding: 14px 16px 12px !important;
}

.tg-drawer-header-name {
  color: var(--tg-received-text) !important;
}

.tg-drawer-header-sub {
  color: var(--tg-meta) !important;
}

.tg-drawer-nav {
  background-color: var(--tg-sidebar-bg) !important;
}

.tg-drawer-item {
  display: flex !important;
  align-items: center !important;
  justify-content: flex-start !important;
  gap: 16px !important;
  padding: 9px 16px !important;
  color: var(--tg-received-text) !important;
  font-size: 13px !important;
  font-weight: 500 !important;
  transition: background-color 150ms ease !important;
  width: 100% !important;
  min-height: auto !important;
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
  border-radius: 6px !important;
  white-space: nowrap !important;
  cursor: pointer !important;
  text-decoration: none !important;
}

.tg-drawer-item:hover {
  background-color: var(--tg-active-row) !important;
  color: var(--tg-received-text) !important;
}

.tg-drawer-icon {
  color: var(--tg-meta) !important;
  opacity: 0.75 !important;
  flex-shrink: 0 !important;
  width: 18px !important;
  height: 18px !important;
}

.tg-drawer-divider {
  height: 1px !important;
  background-color: var(--tg-border) !important;
  margin: 4px 16px !important;
}

.tg-drawer-toggle-row {
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  padding: 9px 16px !important;
  color: var(--tg-received-text) !important;
  font-size: 13px !important;
}

.tg-toggle-switch {
  position: relative !important;
  width: 34px !important;
  height: 20px !important;
  min-height: auto !important;
  min-width: auto !important;
  border-radius: 10px !important;
  padding: 2px !important;
  border: none !important;
  cursor: pointer !important;
  transition: background-color 200ms ease !important;
  box-shadow: none !important;
}
.tg-toggle-on {
  background-color: #3390ec !important;
}
.tg-toggle-off {
  background-color: #c4c9cc !important;
}
.tg-toggle-knob {
  display: block !important;
  width: 16px !important;
  height: 16px !important;
  border-radius: 50% !important;
  background: #ffffff !important;
  box-shadow: 0 1px 2px rgba(0,0,0,0.15) !important;
  transition: transform 200ms ease !important;
}
.tg-knob-on {
  transform: translateX(14px) !important;
}
.tg-knob-off {
  transform: translateX(0) !important;
}
</style>

<!-- Global style override using [data-admin-chat] for scoping layout values -->
<style>
/* Light Theme Defaults (Client-side and light Admin) */
.chat-page {
  --tg-chat-bg: #e7ebf0;
  --tg-sent-bg: #e2f7cb;
  --tg-sent-text: #1a2510;
  --tg-received-bg: #ffffff;
  --tg-received-text: #1f1f1f;
  --tg-meta: #8c9094;
  --tg-meta-sent: #508531;
  --tg-input-bg: #ffffff;
  --tg-input-text: #1f1f1f;
  --tg-sidebar-bg: #ffffff;
  --tg-active-row: #f1f5f9;
  --tg-header-bg: #ffffff;
  --tg-border: #e2e8f0;
  --tg-ticks: #4fae4e;
  --tg-accent: #3390ec;
}

/* Dark Theme - if class bg-zinc-950 (client dark) or data-theme="dark" (admin dark) */
.chat-page.bg-zinc-950,
[data-theme="dark"] .chat-page,
.chat-page.chat-dark {
  --tg-chat-bg: #0e1621;
  --tg-sent-bg: #2b5278;
  --tg-sent-text: #f5f5f5;
  --tg-received-bg: #182533;
  --tg-received-text: #f5f5f5;
  --tg-meta: #7a8e9e;
  --tg-meta-sent: #a8c4db;
  --tg-input-bg: #17212b;
  --tg-input-text: #f5f5f5;
  --tg-sidebar-bg: #17212b;
  --tg-active-row: #202b36;
  --tg-header-bg: #17212b;
  --tg-border: #101921;
  --tg-ticks: #2481cc;
  --tg-accent: #2481cc;
}

/* Explicit light override when user toggles chat to light on dark admin */
.chat-page.chat-light {
  --tg-chat-bg: #e7ebf0;
  --tg-sent-bg: #e2f7cb;
  --tg-sent-text: #1a2510;
  --tg-received-bg: #ffffff;
  --tg-received-text: #1f1f1f;
  --tg-meta: #8c9094;
  --tg-meta-sent: #508531;
  --tg-input-bg: #ffffff;
  --tg-input-text: #1f1f1f;
  --tg-sidebar-bg: #ffffff;
  --tg-active-row: #f1f5f9;
  --tg-header-bg: #ffffff;
  --tg-border: #e2e8f0;
  --tg-ticks: #4fae4e;
  --tg-accent: #3390ec;
}

/* Dynamic admin custom theme adaptation when no local override is set */
[data-admin-chat] .chat-page:not(.chat-dark):not(.chat-light) {
  --tg-chat-bg: var(--admin-bg) !important;
  --tg-sidebar-bg: var(--admin-surface) !important;
  --tg-header-bg: var(--admin-surface) !important;
  --tg-received-bg: var(--admin-surface) !important;
  --tg-input-bg: var(--admin-surface) !important;
  --tg-border: var(--admin-border) !important;
  --tg-active-row: var(--admin-hover) !important;
  --tg-received-text: var(--admin-text) !important;
  --tg-input-text: var(--admin-text) !important;
  --tg-meta: var(--admin-faint) !important;
  --tg-accent: var(--admin-primary) !important;
  --tg-ticks: var(--admin-primary) !important;
}

[data-admin-chat] {
  /* Override backgrounds dynamically */
  background-color: var(--tg-sidebar-bg) !important;
}

/* Set Left Sidebar styling */
[data-admin-chat] .w-full.md\:w-\[360px\] {
  background-color: var(--tg-sidebar-bg) !important;
  border-right: 1px solid var(--tg-border) !important;
}

/* Left Sidebar buttons styling */
[data-admin-chat] .w-full.p-4.flex.items-center {
  background-color: transparent;
  color: var(--tg-received-text) !important;
}
[data-admin-chat] .w-full.p-4.flex.items-center:hover {
  background-color: var(--tg-active-row) !important;
}
[data-admin-chat] .w-full.p-4.flex.items-center.bg-zinc-800\/40 {
  background-color: var(--tg-active-row) !important;
  border-left-color: var(--tg-accent) !important;
}

/* General items coloring */
[data-admin-chat] .text-zinc-100,
[data-admin-chat] .text-zinc-200,
[data-admin-chat] .text-zinc-300 {
  color: var(--tg-received-text) !important;
}
[data-admin-chat] .text-zinc-400,
[data-admin-chat] .text-zinc-500 {
  color: var(--tg-meta) !important;
}

/* Chat background colors override */
[data-admin-chat] .bg-zinc-950 {
  background-color: var(--tg-chat-bg) !important;
}

/* Active Conversation Header styling */
[data-admin-chat] .h-\[64px\] {
  background-color: var(--tg-header-bg) !important;
  border-bottom: 1px solid var(--tg-border) !important;
  color: var(--tg-received-text) !important;
}

/* Divide list elements */
[data-admin-chat] .divide-zinc-800\/30 > * + *,
[data-admin-chat] .divide-zinc-800\/40 > * + * {
  border-color: var(--tg-border) !important;
}

/* Correct default avatars color to contrast */
[data-admin-chat] .bg-gradient-to-tr.from-zinc-800 {
  background: var(--tg-active-row) !important;
  color: var(--tg-received-text) !important;
}

/* User status online check color */
[data-admin-chat] .bg-green-500 {
  background-color: var(--tg-ticks) !important;
}

/* Force search input theme adaptation overriding general admin styles */
[data-admin-chat] .tg-search-input {
  background-color: var(--tg-input-bg) !important;
  color: var(--tg-input-text) !important;
  border: 1px solid var(--tg-border) !important;
}
[data-admin-chat] .tg-search-input::placeholder {
  color: var(--tg-meta) !important;
}
</style>

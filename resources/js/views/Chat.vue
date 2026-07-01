<template>
  <div class="chat-page bg-zinc-950 min-h-screen text-zinc-100 flex flex-col font-sans">
    <!-- Navbar -->
    <PublicNavbar theme="dark" />

    <!-- Chat Workspace -->
    <div class="flex-1 flex overflow-hidden border-t border-zinc-800 h-[calc(100vh-64px)] relative">
      
      <!-- Left Sidebar: Chat List -->
      <div 
        :class="[
          'w-full md:w-[360px] shrink-0 border-r border-zinc-800 flex flex-col h-full bg-zinc-900/50 backdrop-blur-md transition-all duration-300 md:flex',
          mobileShowChat ? 'hidden' : 'flex'
        ]"
      >
        <!-- Search Area -->
        <div class="p-4 border-b border-zinc-800 flex flex-col gap-3">
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-5 w-5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </span>
            <input 
              v-model="searchQuery"
              @input="handleSearch"
              type="text" 
              placeholder="Tìm kiếm người dùng..." 
              class="w-full pl-10 pr-4 py-2 bg-zinc-950/60 border border-zinc-800 rounded-xl text-sm placeholder-zinc-500 text-zinc-100 focus:outline-none focus:border-zinc-700 transition-all focus:ring-1 focus:ring-zinc-700"
            />
            <button 
              v-if="searchQuery" 
              @click="clearSearch"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-500 hover:text-zinc-300"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Tabs Category Filters -->
          <div v-if="!searchQuery" class="flex p-0.5 bg-zinc-950/80 rounded-xl border border-zinc-800">
            <button 
              @click="selectedTab = 'all'"
              :class="['flex-1 py-1.5 text-xs font-medium rounded-lg transition-all', selectedTab === 'all' ? 'bg-zinc-800 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200']"
            >
              Tất cả
            </button>
            <button 
              @click="selectedTab = 'direct'"
              :class="['flex-1 py-1.5 text-xs font-medium rounded-lg transition-all', selectedTab === 'direct' ? 'bg-zinc-800 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200']"
            >
              Trực tiếp
            </button>
            <button 
              @click="selectedTab = 'venue_contact'"
              :class="['flex-1 py-1.5 text-xs font-medium rounded-lg transition-all', selectedTab === 'venue_contact' ? 'bg-zinc-800 text-white shadow-sm' : 'text-zinc-400 hover:text-zinc-200']"
            >
              Chủ sân
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
            class="w-full p-4 flex items-center gap-3 hover:bg-zinc-800/20 text-left transition-colors"
          >
            <div class="h-10 w-10 rounded-full bg-zinc-800 flex items-center justify-center shrink-0 border border-zinc-700/50 text-sm font-semibold text-zinc-300">
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
          <div v-else-if="filteredConversations.length === 0" class="p-8 text-center text-zinc-500 flex flex-col items-center gap-3">
            <svg class="h-12 w-12 text-zinc-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <div class="text-xs">Không có cuộc trò chuyện nào</div>
          </div>
          
          <button 
            v-for="conv in filteredConversations" 
            :key="conv.id"
            @click="selectConversation(conv)"
            :class="[
              'w-full p-4 flex items-center gap-3 border-l-2 text-left transition-all',
              activeConversation?.id === conv.id 
                ? 'bg-zinc-800/40 border-green-500' 
                : 'border-transparent hover:bg-zinc-800/20'
            ]"
          >
            <!-- Avatar -->
            <div class="h-11 w-11 rounded-full bg-gradient-to-tr from-zinc-800 to-zinc-700 flex items-center justify-center shrink-0 border border-zinc-700/50 text-sm font-bold text-zinc-200">
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
        <div v-if="!activeConversation" class="flex-1 flex flex-col items-center justify-center p-8 text-zinc-500 bg-zinc-950">
          <div class="p-6 bg-zinc-900/30 rounded-full border border-zinc-800/40 mb-4 shadow-xl">
            <svg class="h-16 w-16 text-zinc-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
          </div>
          <h3 class="text-sm font-medium text-zinc-300 mb-1">Hãy chọn một cuộc trò chuyện</h3>
          <p class="text-xs text-zinc-600 text-center max-w-xs leading-relaxed">
            Chọn từ danh sách bên trái hoặc tìm kiếm thành viên mới để bắt đầu trò chuyện giống Telegram.
          </p>
        </div>

        <!-- Active Conversation Area -->
        <div v-else class="flex-1 flex flex-col h-full bg-zinc-950 relative">
          <!-- Active Conversation Header -->
          <div class="h-[64px] border-b border-zinc-800 px-4 flex items-center justify-between bg-zinc-900/40 backdrop-blur-md shrink-0">
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

              <div class="h-10 w-10 rounded-full bg-zinc-800 flex items-center justify-center shrink-0 border border-zinc-700/50 font-bold text-sm text-zinc-200">
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
          <div ref="messageContainer" class="flex-1 overflow-y-auto p-4 space-y-6 bg-zinc-950">
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
              <div class="flex justify-center sticky top-0 z-10 py-1">
                <span class="px-3 py-1 bg-zinc-900/90 text-[10px] font-semibold text-zinc-400 rounded-full border border-zinc-800/60 shadow-md backdrop-blur">
                  {{ group.date }}
                </span>
              </div>

              <!-- Message Bubbles -->
              <div 
                v-for="msg in group.messages" 
                :key="msg.id" 
                :class="['flex', msg.sender_id === currentUser.id ? 'justify-end' : 'justify-start']"
              >
                <!-- Avatar for other user in group direct -->
                <div 
                  v-if="msg.sender_id !== currentUser.id" 
                  class="h-7 w-7 rounded-full bg-zinc-800 flex items-center justify-center shrink-0 mr-2 border border-zinc-700/50 font-bold text-[10px] text-zinc-300 self-end"
                >
                  {{ msg.sender?.full_name?.charAt(0)?.toUpperCase() || 'U' }}
                </div>

                <div 
                  :class="[
                    'max-w-[70%] rounded-2xl px-4 py-2 shadow-sm text-sm relative break-words',
                    msg.sender_id === currentUser.id 
                      ? 'bg-green-600 text-zinc-950 rounded-br-none font-medium' 
                      : 'bg-zinc-900 text-zinc-200 border border-zinc-800 rounded-bl-none'
                  ]"
                >
                  <!-- Sender Name if group/system (optional, not needed for direct) -->
                  
                  <!-- Content text -->
                  <div>{{ msg.content }}</div>

                  <!-- Time and Tick Indicator -->
                  <div 
                    :class="[
                      'text-[9px] text-right mt-1 select-none flex items-center justify-end gap-1',
                      msg.sender_id === currentUser.id ? 'text-zinc-950/60' : 'text-zinc-500'
                    ]"
                  >
                    <span>{{ formatTimeOnly(msg.created_at) }}</span>
                    
                    <!-- Read checkmarks logic for sent messages -->
                    <span v-if="msg.sender_id === currentUser.id" class="inline-flex">
                      <!-- Read (Double check) -->
                      <svg v-if="isMessageRead(msg)" class="h-3 w-3 text-zinc-950" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                      </svg>
                      <!-- Sent but unread (Single check) -->
                      <svg v-else class="h-3 w-3 text-zinc-950/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                      </svg>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Bottom Message Input Editor -->
          <div class="p-4 border-t border-zinc-800 bg-zinc-900/30 shrink-0">
            <form @submit.prevent="submitMessage" class="flex gap-2 items-center">
              
              <!-- File Attachment (Mockup representation) -->
              <button 
                type="button"
                @click="clickAttachment"
                class="p-2.5 bg-zinc-900 border border-zinc-800 text-zinc-400 hover:text-zinc-200 rounded-xl transition-all hover:bg-zinc-800/50 shrink-0"
              >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                </svg>
              </button>

              <!-- Text Input Area -->
              <input 
                v-model="newMessage"
                type="text" 
                placeholder="Viết tin nhắn..." 
                class="flex-1 bg-zinc-950 border border-zinc-800 text-zinc-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-zinc-700 transition-all focus:ring-1 focus:ring-zinc-700"
              />

              <!-- Send Button -->
              <button 
                type="submit"
                :disabled="!newMessage.trim()"
                class="p-2.5 bg-green-500 disabled:opacity-40 disabled:hover:bg-green-500 hover:bg-green-400 text-zinc-950 font-bold rounded-xl transition-all shadow-md shrink-0 flex items-center justify-center"
              >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
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
      messagesTimer: null
    };
  },
  computed: {
    filteredConversations() {
      if (this.selectedTab === 'all') return this.conversations;
      return this.conversations.filter(c => c.type === this.selectedTab);
    },
    groupedMessages() {
      return this.groupMessages(this.messages);
    }
  },
  created() {
    // If not authenticated, redirect to login page
    if (!this.currentUser) {
      this.$router.push('/login');
      return;
    }

    // Load list of conversations on creation
    this.fetchConversations(true);

    // Set polling for conversations list (every 5 seconds)
    this.conversationsTimer = setInterval(() => {
      this.fetchConversations(false);
    }, 5000);
  },
  mounted() {
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
  height: 100vh;
  overflow: hidden;
}

/* Custom Scrollbar Styles for elegant UI */
::-webkit-scrollbar {
  width: 6px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 9999px;
}
::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.2);
}
</style>

import { api } from './api.js';

export const chatService = {
  getConversations() {
    return api('/api/chat/conversations');
  },
  getMessages(conversationId) {
    return api(`/api/chat/conversations/${conversationId}/messages`);
  },
  sendMessage(conversationId, content) {
    return api(`/api/chat/conversations/${conversationId}/messages`, {
      method: 'POST',
      body: JSON.stringify({ content })
    });
  },
  markAsRead(conversationId) {
    return api(`/api/chat/conversations/${conversationId}/read`, {
      method: 'POST'
    });
  },
  searchUsers(query) {
    return api(`/api/chat/users/search?query=${encodeURIComponent(query)}`);
  },
  startConversation(payload) {
    return api('/api/chat/conversations', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }
};

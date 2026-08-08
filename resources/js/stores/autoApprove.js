import { reactive } from 'vue';
import { adminModerationService } from '@/services/adminModeration.js';

export const autoApproveStore = reactive({
  isEnabled: localStorage.getItem('sg_admin_auto_approve') === 'true',
  intervalId: null,
  isProcessing: false,
  lastActionTime: Date.now(),
  visibilityHandler: null,
  
  async toggle() {
    this.isEnabled = !this.isEnabled;
    localStorage.setItem('sg_admin_auto_approve', this.isEnabled.toString());
    
    // Also update backend config to enable instant auto-approve for new posts
    try {
      await adminModerationService.saveConfig({
        auto_approve_community_post: this.isEnabled,
        auto_approve_venue_post: this.isEnabled
      });
    } catch (err) {
      console.error('Failed to sync auto-approve config with backend:', err);
    }
    
    if (this.isEnabled) {
      this.start();
    } else {
      this.stop();
    }
  },
  
  start() {
    if (this.intervalId) return;

    this.visibilityHandler = () => {
      if (document.visibilityState === 'visible' && this.isEnabled && !this.intervalId) {
        this.start();
      }
    };
    document.addEventListener('visibilitychange', this.visibilityHandler);

    this.intervalId = setInterval(async () => {
      if (!this.isEnabled || this.isProcessing || document.visibilityState !== 'visible') return;

      this.isProcessing = true;
      try {
        const allTypes = ['community_posts', 'venue_posts', 'system_posts'];
        const queues = await Promise.all(allTypes.map(async (type) => {
          try {
            const response = await adminModerationService.getQueue({ type, status: 'pending', page: 1 });
            const list = response.data?.data || [];
            return { type, item: list.find(item => ['pending', 'pending_review', 'draft'].includes(item.status)) };
          } catch (err) {
            console.error(`Lỗi quét tự động duyệt cho tab ${type}:`, err);
            return { type, item: null };
          }
        }));
        const target = queues.find(({ item }) => item);
        const targetItem = target?.item || null;
        const targetType = target?.type || null;

        if (targetItem) {
          await adminModerationService.approvePost(targetType, targetItem.id);
          this.lastActionTime = Date.now();
        }
      } catch (err) {
        console.error('Duyệt tự động bài viết thất bại:', err);
      } finally {
        this.isProcessing = false;
      }
    }, 30000);
  },
  
  stop() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = null;
    }
    if (this.visibilityHandler) {
      document.removeEventListener('visibilitychange', this.visibilityHandler);
      this.visibilityHandler = null;
    }
  },
  
  init() {
    if (this.isEnabled) {
      // Sync with backend on startup
      adminModerationService.saveConfig({
        auto_approve_community_post: true,
        auto_approve_venue_post: true
      }).catch(err => console.error('Failed to sync auto-approve config on init:', err));
      
      this.start();
    }
  }
});

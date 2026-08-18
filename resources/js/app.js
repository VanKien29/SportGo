import { createApp } from 'vue';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';
import '../css/toast-custom.css';
import '../css/partner-pages.css';
import '../css/document-viewer.css';
import App from './App.vue';
import router from './router/index.js';
import { loadSystemProfile } from './stores/systemProfile.js';

const app = createApp(App);
app.use(router);

// Keep a component cleanup/render exception from aborting an otherwise valid
// SPA navigation. The original error remains visible in devtools and can be
// reported without leaving the address bar ahead of the rendered page.
app.config.errorHandler = (error, instance, info) => {
  console.error('[SportGo] Vue error:', error, info, instance);
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('sportgo:app-error', {
      detail: { error, info },
    }));
  }
};
app.use(Toast, {
  timeout: 3000,
  closeOnClick: true,
  pauseOnHover: true,
  icon: false,
  hideProgressBar: true,
});

// Mount immediately. Router guards and async route components may need to
// validate a session or load data, but they must not block the application
// shell from rendering while that work is in progress.
document.documentElement.classList.add('app-ready');
app.mount('#app');
if (typeof performance !== 'undefined') {
  performance.mark('sportgo:app-mounted');
}

// Branding is non-critical and can finish independently of the first paint.
router.isReady().finally(() => {
  loadSystemProfile();
});

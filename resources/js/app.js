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

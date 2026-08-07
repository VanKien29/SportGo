import { createApp } from 'vue';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';
import '../css/toast-custom.css';
import App from './App.vue';
import router from './router/index.js';
import { loadSystemProfile } from './stores/systemProfile.js';

const app = createApp(App);
app.use(router);
app.use(Toast, {
  timeout: 3000,
  closeOnClick: true,
  pauseOnHover: true,
});

// Mount after the route is ready. System branding is non-critical and loads
// after the first paint so a slow profile endpoint cannot blank the whole app.
router.isReady().finally(() => {
  document.documentElement.classList.add('app-ready');
  app.mount('#app');
  if (typeof performance !== 'undefined') {
    performance.mark('sportgo:app-mounted');
  }
  loadSystemProfile();
});

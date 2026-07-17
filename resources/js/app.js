import { createApp } from 'vue';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';
import App from './App.vue';
import router from './router/index.js';
import { loadSystemProfile } from './stores/systemProfile.js';

const app = createApp(App);
app.use(router);
app.use(Toast, {
  timeout: 4500,
  closeOnClick: true,
  pauseOnHover: true,
});
app.mount('#app');

loadSystemProfile();

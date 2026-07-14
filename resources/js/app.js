import { createApp } from 'vue';
import App from './App.vue';
import router from './router/index.js';
import { loadSystemProfile } from './stores/systemProfile.js';
import { applyAuthThemeStyles, loadPublicThemeStyles } from './utils/theme.js';

applyAuthThemeStyles();
loadPublicThemeStyles();

const app = createApp(App);
app.use(router);
app.mount('#app');

loadSystemProfile();

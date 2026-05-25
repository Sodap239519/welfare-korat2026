import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import VueApexCharts from 'vue3-apexcharts';

import App from './App.vue';
import router from './router';
import { useThemeStore } from './stores/theme';

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.component('apexchart', VueApexCharts);

// initialize theme + font size from localStorage
const theme = useThemeStore();
theme.init();

app.mount('#app');

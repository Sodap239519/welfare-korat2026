import './bootstrap';
import { createApp, defineAsyncComponent } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import router from './router';
import { useThemeStore } from './stores/theme';

const app = createApp(App);
app.use(createPinia());
app.use(router);
// โหลด apexcharts แบบ lazy — ดาวน์โหลด ~518KB เฉพาะหน้าที่มีกราฟจริง
// (หน้า login/บันทึกงาน/หน้าทั่วไป ไม่ต้องแบกขนาดนี้ → โหลดเร็วขึ้นมาก)
app.component('apexchart', defineAsyncComponent(() => import('vue3-apexcharts')));

// initialize theme + font size from localStorage
const theme = useThemeStore();
theme.init();

app.mount('#app');

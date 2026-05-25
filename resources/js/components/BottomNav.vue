<script setup>
import { RouterLink, useRoute } from 'vue-router';
import { computed } from 'vue';

const route = useRoute();
const items = [
  { name: 'dashboard', icon: 'fi-rr-apps',             label: 'หน้าแรก' },
  { name: 'targets',   icon: 'fi-rr-users-alt',        label: 'รายชื่อ' },
  { name: 'import',    icon: 'fi-rr-cloud-upload-alt', label: 'นำเข้า' },
  { name: 'reports',   icon: 'fi-rr-chart-pie',        label: 'รายงาน' },
];
const isActive = (n) => computed(() => route.name === n);
</script>

<template>
  <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-30 bg-white dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 px-2 pt-1"
       style="padding-bottom: max(env(safe-area-inset-bottom), 0.25rem);">
    <div class="flex items-stretch justify-around">
      <RouterLink v-for="i in items" :key="i.name" :to="{ name: i.name }"
        :class="['flex flex-col items-center justify-center py-1.5 px-3 text-[10px] min-w-[58px]',
                 isActive(i.name).value ? 'text-blue-700' : 'text-slate-500 dark:text-slate-400']">
        <span :class="['w-10 h-7 flex items-center justify-center rounded-full transition-colors',
                       isActive(i.name).value ? 'bg-blue-50 dark:bg-blue-900/30' : '']">
          <i :class="i.icon + ' text-lg'"></i>
        </span>
        <span class="mt-0.5 leading-tight">{{ i.label }}</span>
      </RouterLink>
    </div>
  </nav>
</template>

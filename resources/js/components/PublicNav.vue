<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';
import { ref } from 'vue';

const auth = useAuthStore();
const theme = useThemeStore();
const router = useRouter();
const open = ref(false);

const links = [
  { name: 'home', label: 'หน้าหลัก' },
  { name: 'project-info', label: 'เกี่ยวกับโครงการ' },
  { name: 'student-tracking', label: 'ติดตามงานนักศึกษา' },
];

function goApp() {
  router.push(auth.isStudent ? { name: 'student-worklog' } : { name: 'dashboard' });
}
</script>

<template>
  <header class="sticky top-0 z-30 bg-white/85 dark:bg-slate-900/85 backdrop-blur border-b border-slate-100 dark:border-slate-800">
    <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between gap-3">
      <RouterLink :to="{ name: 'home' }" class="leading-tight shrink-0">
        <div class="text-base font-bold bg-gradient-to-r from-blue-700 to-sky-500 bg-clip-text text-transparent">Welfare Korat 2026</div>
        <div class="text-[10px] text-slate-500 dark:text-slate-400">ระบบสนับสนุนการลงทะเบียนบัตรสวัสดิการแห่งรัฐ 2569</div>
      </RouterLink>

      <!-- เมนูด้านบน (desktop) -->
      <nav class="hidden md:flex items-center gap-1">
        <RouterLink v-for="l in links" :key="l.name" :to="{ name: l.name }"
          class="px-3 py-2 rounded-lg text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
          active-class="!text-blue-700 dark:!text-blue-300 font-medium bg-blue-50 dark:bg-blue-900/30">
          {{ l.label }}
        </RouterLink>
      </nav>

      <div class="flex items-center gap-2 shrink-0">
        <button @click="theme.toggle" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800" title="สลับโหมดสี">
          <i v-if="!theme.isDark" class="fi-rr-brightness"></i><i v-else class="fi-sr-moon text-orange-400"></i>
        </button>
        <button v-if="auth.isAuth" @click="goApp" class="btn-primary text-sm"><i class="fi-rr-apps"></i> <span class="hidden sm:inline">เข้าระบบ</span></button>
        <RouterLink v-else :to="{ name: 'login' }" class="btn-primary text-sm"><i class="fi-rr-sign-in-alt"></i> <span class="hidden sm:inline">เข้าสู่ระบบ</span></RouterLink>
        <!-- hamburger (mobile) -->
        <button @click="open = !open" class="md:hidden btn-icon hover:bg-slate-100 dark:hover:bg-slate-800"><i class="fi-rr-menu-burger"></i></button>
      </div>
    </div>

    <!-- เมนู mobile -->
    <nav v-if="open" class="md:hidden border-t border-slate-100 dark:border-slate-800 px-4 py-2 space-y-1 bg-white dark:bg-slate-900">
      <RouterLink v-for="l in links" :key="l.name" :to="{ name: l.name }" @click="open = false"
        class="block px-3 py-2 rounded-lg text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
        active-class="!text-blue-700 font-medium bg-blue-50 dark:bg-blue-900/30">
        {{ l.label }}
      </RouterLink>
    </nav>
  </header>
</template>

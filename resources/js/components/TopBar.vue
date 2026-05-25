<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useThemeStore } from '@/stores/theme';
import { useAuthStore } from '@/stores/auth';

const theme = useThemeStore();
const auth = useAuthStore();
const router = useRouter();

defineProps({ title: String, subtitle: String, greeting: String });
defineEmits(['openSidebar']);

const initials = computed(() => {
  const n = auth.user?.name || '';
  return n.trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').toUpperCase() || 'U';
});

const roleLabel = computed(() => {
  if (auth.isSuperAdmin) return 'Super Admin';
  if (auth.roles.includes('admin')) return 'Admin';
  return 'Tracker';
});

async function doLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}
</script>

<template>
  <header class="sticky top-0 z-30 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-b border-slate-100 dark:border-slate-800">
    <div class="flex items-center justify-between px-4 h-16">
      <div class="flex items-center gap-3 min-w-0">
        <button class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" @click="$emit('openSidebar')">
          <i class="fi-rr-menu-burger text-lg"></i>
        </button>
        <div class="min-w-0">
          <div v-if="greeting" class="text-xs text-slate-500 dark:text-slate-400 leading-tight">{{ greeting }}</div>
          <div :class="['font-semibold text-slate-800 dark:text-slate-100 leading-tight truncate', greeting ? 'text-base' : 'text-sm']">{{ title }}</div>
          <div v-if="!greeting && subtitle" class="text-xs text-slate-500 dark:text-slate-400 leading-tight truncate">{{ subtitle }}</div>
        </div>
      </div>
      <div class="flex items-center gap-1">
        <button title="ลดตัวอักษร" @click="theme.smaller" class="px-2 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-xs">A−</button>
        <button title="ขนาดมาตรฐาน" @click="theme.reset" class="hidden sm:inline-block px-2 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-sm">A</button>
        <button title="เพิ่มตัวอักษร" @click="theme.bigger" class="px-2 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-base font-semibold">A+</button>
        <button title="สลับโหมดสี" @click="theme.toggle" class="ml-1 p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
          <i v-if="!theme.isDark" class="fi-rr-brightness text-lg"></i>
          <i v-else class="fi-sr-moon text-lg text-orange-400"></i>
        </button>
        <button class="relative p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="แจ้งเตือน">
          <i class="fi-rr-bell text-lg"></i>
          <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-900"></span>
        </button>
        <div v-if="auth.user" class="hidden sm:flex items-center gap-2 ml-2 pl-2 border-l border-slate-100 dark:border-slate-800">
          <div class="w-9 h-9 rounded-full text-white flex items-center justify-center text-xs font-semibold"
               style="background: linear-gradient(135deg,#1d4ed8,#0ea5e9);">{{ initials }}</div>
          <div class="text-xs leading-tight">
            <div class="font-medium text-slate-800 dark:text-slate-100">{{ auth.user.name }}</div>
            <div class="text-slate-500 dark:text-slate-400">{{ roleLabel }}</div>
          </div>
          <button @click="doLogout" title="ออกจากระบบ" class="ml-1 p-1.5 rounded-lg hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30">
            <i class="fi-rr-sign-out-alt text-base"></i>
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

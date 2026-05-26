<script setup>
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

defineProps({ open: Boolean });
defineEmits(['close']);

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

async function doLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}
const allItems = [
  { name: 'dashboard',      icon: 'fi-rr-apps',             label: 'Dashboard · ภาพรวม',  roles: ['*'] },
  { name: 'targets',        icon: 'fi-rr-users-alt',        label: 'รายชื่อเป้าหมาย',     roles: ['*'] },
  { name: 'map',            icon: 'fi-rr-marker',           label: 'แผนที่หมู่บ้าน',     roles: ['*'] },
  { name: 'trackers',       icon: 'fi-rr-user-headset',     label: 'ผู้กำกับติดตาม',     roles: ['*'] },
  { name: 'import',         icon: 'fi-rr-cloud-upload-alt', label: 'นำเข้าข้อมูล',       roles: ['super_admin'] },
  { name: 'reports',        icon: 'fi-rr-chart-pie',        label: 'รายงาน',              roles: ['*'] },
  { name: 'admin-users',    icon: 'fi-rr-user-shield',      label: 'จัดการผู้ใช้',       roles: ['super_admin'] },
  { name: 'admin-activity', icon: 'fi-rr-time-past',        label: 'ประวัติการใช้งาน',   roles: ['super_admin'] },
];

const items = computed(() => allItems.filter(i =>
  i.roles.includes('*') || i.roles.some(r => auth.roles.includes(r))
));

const isActive = (n) => computed(() => route.name === n);
</script>

<template>
  <div v-show="open" class="lg:hidden fixed inset-0 bg-slate-900/50 z-40" @click="$emit('close')"></div>
  <aside
    :class="['fixed top-0 left-0 z-50 lg:z-40 w-72 lg:w-60 h-screen bg-white dark:bg-slate-900 border-r border-slate-100 dark:border-slate-800 transition-transform flex flex-col',
             open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">
    <div class="flex items-center justify-between gap-2 px-4 h-16 border-b border-slate-100 dark:border-slate-800 shrink-0">
      <RouterLink to="/" class="flex items-center gap-2.5">
        <div class="w-10 h-10 rounded-xl text-white flex items-center justify-center shadow-md shadow-blue-500/30"
             style="background: linear-gradient(135deg,#1d4ed8,#0ea5e9);">
          <i class="fi-sr-shield-check"></i>
        </div>
        <div class="leading-tight">
          <div class="text-sm font-semibold">Welfare Korat</div>
          <div class="text-[10px] text-slate-500 dark:text-slate-400">บัตรสวัสดิการ 2569</div>
        </div>
      </RouterLink>
      <button class="lg:hidden p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" @click="$emit('close')">
        <i class="fi-rr-cross-small"></i>
      </button>
    </div>
    <nav class="p-3 space-y-1 flex-1 overflow-y-auto">
      <RouterLink v-for="i in items" :key="i.name" :to="{ name: i.name }"
        @click="$emit('close')"
        :class="['flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm hover:bg-slate-50 dark:hover:bg-slate-800/50',
                 isActive(i.name).value
                   ? 'bg-blue-50 text-blue-700 font-medium dark:bg-blue-900/30 dark:text-blue-200'
                   : 'text-slate-600 dark:text-slate-300']">
        <i :class="i.icon + ' text-lg'"></i>
        <span>{{ i.label }}</span>
      </RouterLink>
    </nav>
    <div v-if="auth.user" class="p-3 border-t border-slate-100 dark:border-slate-800 shrink-0">
      <div class="text-xs text-slate-600 dark:text-slate-300 truncate">
        <i class="fi-rr-user text-slate-400"></i> {{ auth.user.name }}
      </div>
      <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
        <i class="fi-rr-info"></i> ออกจากระบบที่รูปโปรไฟล์ขวาบน
      </div>
    </div>
  </aside>
</template>

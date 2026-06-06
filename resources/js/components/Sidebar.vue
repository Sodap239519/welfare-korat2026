<script setup>
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { computed, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';

defineProps({ open: Boolean });
defineEmits(['close']);

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

async function doLogout() {
  await auth.logout();
  router.push({ name: 'home' });
}
// เจ้าหน้าที่ (ไม่รวมนักศึกษา) — หน้าปฏิบัติงานเดิม
const STAFF = ['super_admin', 'admin', 'tracker', 'bank_staff'];
const allItems = [
  { name: 'dashboard',      icon: 'fi-rr-apps',             label: 'Dashboard · ภาพรวม',  roles: STAFF },
  { name: 'missed-targets', icon: 'fi-rr-search-heart',     label: 'กลุ่มเป้าหมายผู้ตกหล่น', roles: STAFF },
  { name: 'targets',        icon: 'fi-rr-users-alt',        label: 'รายชื่อเป้าหมาย',     roles: STAFF },
  { name: 'batches',        icon: 'fi-rr-box-alt',          label: 'ห่อเอกสารธนาคาร',   roles: STAFF },
  { name: 'map',            icon: 'fi-rr-marker',           label: 'แผนที่หมู่บ้าน',     roles: STAFF },
  { name: 'trackers',       icon: 'fi-rr-user-headset',     label: 'ผู้กำกับติดตาม',     roles: STAFF },
  { name: 'reports',        icon: 'fi-rr-chart-pie',        label: 'รายงาน',              roles: STAFF },
  // ── โมดูลนักศึกษา ──
  { name: 'student-worklog',    icon: 'fi-rr-edit',          label: 'บันทึกการปฏิบัติงาน', roles: ['student'] },
  { name: 'student-assessment', icon: 'fi-rr-clipboard-list', label: 'ประเมินตนเอง',       roles: ['student'] },
  { name: 'student-mydash',     icon: 'fi-rr-stats',         label: 'สรุปงานของฉัน',       roles: ['student'] },
  { name: 'student-documents',  icon: 'fi-rr-folder',        label: 'เอกสารสำหรับนักศึกษา', roles: ['student'] },
  // รายงานนักศึกษา (อาจารย์/ผู้บริหาร)
  { name: 'student-report', icon: 'fi-rr-graduation-cap',   label: 'รายงานนักศึกษา',     roles: ['super_admin', 'admin'] },
  // ข้อมูลโครงการ — ทุกคน
  { name: 'project-info',   icon: 'fi-rr-document',         label: 'ข้อมูลโครงการ',      roles: ['*'] },
  { name: 'admin-users',    icon: 'fi-rr-user-shield',      label: 'จัดการผู้ใช้',       roles: ['super_admin'] },
  { name: 'admin-activity', icon: 'fi-rr-time-past',        label: 'ประวัติการใช้งาน',   roles: ['super_admin'] },
  // เมนูพับได้ — มี children เป็น submenu
  {
    name: 'admin-settings', icon: 'fi-rr-settings', label: 'ตั้งค่าข้อมูล',
    roles: ['super_admin'],
    children: [
      { tab: 'channels', label: 'ช่องทางลงทะเบียน',  icon: 'fi-rr-megaphone' },
      { tab: 'statuses', label: 'สถานะลงทะเบียน',    icon: 'fi-rr-flag' },
      { tab: 'banks',    label: 'ธนาคาร',            icon: 'fi-rr-bank' },
      { tab: 'sop',      label: 'ขั้นตอน SOP',       icon: 'fi-rr-list-check' },
    ],
  },
];

const items = computed(() => allItems.filter(i =>
  i.roles.includes('*') || i.roles.some(r => auth.roles.includes(r))
));

const isActive = (n) => computed(() => route.name === n);

// Submenu open/close state — auto-open ถ้าอยู่ในหน้านั้น
const openMenus = ref({});
function toggleMenu(name) {
  openMenus.value[name] = !openMenus.value[name];
}
// ถ้า user อยู่บนหน้าที่เป็น parent ของ submenu → เปิด submenu อัตโนมัติ
const watchActive = computed(() => {
  for (const item of allItems) {
    if (item.children && route.name === item.name) openMenus.value[item.name] = true;
  }
  return route.name;
});
watchActive.value; // trigger
</script>

<template>
  <div v-show="open" class="lg:hidden fixed inset-0 bg-slate-900/50 z-40" @click="$emit('close')"></div>
  <aside
    :class="['fixed top-0 left-0 z-50 lg:z-40 w-72 lg:w-60 h-screen bg-white dark:bg-slate-900 border-r border-slate-100 dark:border-slate-800 transition-transform flex flex-col',
             open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">
    <div class="flex items-center justify-between gap-2 px-4 h-16 border-b border-slate-100 dark:border-slate-800 shrink-0">
      <RouterLink to="/" class="flex flex-col leading-tight">
        <div class="text-base font-bold bg-gradient-to-r from-blue-700 to-sky-500 bg-clip-text text-transparent">
          Welfare Korat 2026
        </div>
        <div class="text-[10px] text-slate-500 dark:text-slate-400">บัตรสวัสดิการแห่งรัฐ 2569</div>
      </RouterLink>
      <button class="lg:hidden btn-icon hover:bg-slate-100 dark:hover:bg-slate-800 tap-transparent" @click="$emit('close')" title="ปิดเมนู">
        <i class="fi-rr-cross-small"></i>
      </button>
    </div>
    <nav class="p-3 space-y-1 flex-1 overflow-y-auto">
      <template v-for="i in items" :key="i.name">
        <!-- เมนูปกติ (ไม่มี children) -->
        <RouterLink v-if="!i.children" :to="{ name: i.name }"
          @click="$emit('close')"
          :class="['flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm hover:bg-slate-50 dark:hover:bg-slate-800/50',
                   isActive(i.name).value
                     ? 'bg-blue-50 text-blue-700 font-medium dark:bg-blue-900/30 dark:text-blue-200'
                     : 'text-slate-600 dark:text-slate-300']">
          <i :class="i.icon + ' text-lg'"></i>
          <span>{{ i.label }}</span>
        </RouterLink>

        <!-- เมนูที่มี children (expandable submenu) -->
        <div v-else>
          <button @click="toggleMenu(i.name)"
                  :class="['w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm hover:bg-slate-50 dark:hover:bg-slate-800/50',
                           isActive(i.name).value
                             ? 'bg-blue-50 text-blue-700 font-medium dark:bg-blue-900/30 dark:text-blue-200'
                             : 'text-slate-600 dark:text-slate-300']">
            <i :class="i.icon + ' text-lg'"></i>
            <span class="flex-1 text-left">{{ i.label }}</span>
            <i :class="['text-xs transition-transform', openMenus[i.name] ? 'fi-rr-angle-small-up' : 'fi-rr-angle-small-down']"></i>
          </button>

          <!-- Submenu items -->
          <div v-show="openMenus[i.name]" class="ml-3 pl-3 border-l-2 border-slate-100 dark:border-slate-800 mt-1 space-y-0.5">
            <RouterLink v-for="c in i.children" :key="c.tab"
              :to="{ name: i.name, query: { tab: c.tab } }"
              @click="$emit('close')"
              :class="['flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs hover:bg-slate-50 dark:hover:bg-slate-800/50',
                       (route.name === i.name && route.query.tab === c.tab)
                         ? 'bg-blue-50 text-blue-700 font-medium dark:bg-blue-900/30 dark:text-blue-200'
                         : 'text-slate-500 dark:text-slate-400']">
              <i :class="c.icon"></i>
              <span>{{ c.label }}</span>
            </RouterLink>
          </div>
        </div>
      </template>
    </nav>
    <div v-if="auth.user" class="p-3 border-t border-slate-100 dark:border-slate-800 shrink-0">
      <div class="text-sm text-slate-700 dark:text-slate-200 truncate font-medium flex items-center gap-1.5">
        <i class="fi-rr-user text-slate-400 text-xs"></i> {{ auth.user.name }}
      </div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-start gap-1.5 leading-snug">
        <i class="fi-rr-info text-blue-500 mt-0.5"></i>
        <span>ออกจากระบบที่รูปโปรไฟล์มุมขวาบน</span>
      </div>
    </div>
  </aside>
</template>

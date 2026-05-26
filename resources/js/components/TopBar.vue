<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useThemeStore } from '@/stores/theme';
import { useAuthStore } from '@/stores/auth';
import { useNotificationStore } from '@/stores/notifications';
import { shortDate } from '@/composables/useApi';
import Modal from '@/components/Modal.vue';

const theme = useThemeStore();
const auth = useAuthStore();
const notif = useNotificationStore();
const router = useRouter();

defineProps({ title: String, subtitle: String, greeting: String });
defineEmits(['openSidebar']);

const showNotif = ref(false);
const bellRef = ref(null);
const showUserMenu = ref(false);
const userMenuRef = ref(null);

// Profile edit modal
const showProfile = ref(false);
const profileForm = reactive({
  name: '', phone: '', email: '',
  position_type: '', position_other: '',
  current_password: '', password: '',
});
const profileErr = ref({});
const profileFlash = ref('');
const profileSaving = ref(false);

function openProfile() {
  Object.assign(profileForm, {
    name: auth.user?.name || '',
    phone: auth.user?.phone || '',
    email: auth.user?.email || '',
    position_type: auth.user?.position_type || '',
    position_other: auth.user?.position_other || '',
    current_password: '',
    password: '',
  });
  profileErr.value = {};
  profileFlash.value = '';
  showProfile.value = true;
}

async function saveProfile() {
  profileSaving.value = true;
  profileErr.value = {};
  try {
    const res = await auth.updateProfile(profileForm);
    profileFlash.value = res.message;
    profileForm.current_password = '';
    profileForm.password = '';
    setTimeout(() => { showProfile.value = false; profileFlash.value = ''; }, 1500);
  } catch (e) {
    profileErr.value = e.response?.data?.errors || { general: [e.response?.data?.message || 'ผิดพลาด'] };
  } finally { profileSaving.value = false; }
}

const initials = computed(() => {
  const n = auth.user?.name || '';
  return n.trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').toUpperCase() || 'U';
});

const roleLabel = computed(() => {
  if (auth.isSuperAdmin) return 'Super Admin';
  if (auth.roles.includes('admin')) return 'Admin';
  return 'Tracker';
});

const colorMap = {
  blue:   { bg: 'card-tint-blue',   text: 'text-blue-700' },
  sky:    { bg: 'card-tint-sky',    text: 'text-sky-700' },
  green:  { bg: 'card-tint-green',  text: 'text-green-700' },
  orange: { bg: 'card-tint-orange', text: 'text-orange-700' },
  red:    { bg: 'card-tint-red',    text: 'text-red-700' },
};

async function toggleNotif() {
  showNotif.value = !showNotif.value;
  if (showNotif.value) await notif.loadList();
}

async function clickNotif(n) {
  await notif.markRead(n.id);
  showNotif.value = false;
  if (n.data?.url) router.push(n.data.url);
}

function onOutside(e) {
  if (showNotif.value && bellRef.value && !bellRef.value.contains(e.target)) {
    showNotif.value = false;
  }
  if (showUserMenu.value && userMenuRef.value && !userMenuRef.value.contains(e.target)) {
    showUserMenu.value = false;
  }
}

function openProfileFromMenu() {
  showUserMenu.value = false;
  openProfile();
}

async function logoutFromMenu() {
  showUserMenu.value = false;
  await doLogout();
}

async function doLogout() {
  await auth.logout();
  notif.stopPolling();
  router.push({ name: 'login' });
}

onMounted(async () => {
  document.addEventListener('click', onOutside);
  await notif.loadUnreadCount();
  notif.startPolling();
});
onBeforeUnmount(() => {
  document.removeEventListener('click', onOutside);
  notif.stopPolling();
});
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
        <button title="คลิกเพื่อรีเซ็ตเป็น 16pt" @click="theme.reset" class="hidden sm:inline-flex items-baseline gap-0.5 px-2 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-sm font-medium">
          {{ theme.fontSize }}<span class="text-[10px] text-slate-500">pt</span>
        </button>
        <button title="เพิ่มตัวอักษร" @click="theme.bigger" class="px-2 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-base font-semibold">A+</button>
        <button title="สลับโหมดสี" @click="theme.toggle" class="ml-1 p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
          <i v-if="!theme.isDark" class="fi-rr-brightness text-lg"></i>
          <i v-else class="fi-sr-moon text-lg text-orange-400"></i>
        </button>

        <!-- Notification bell -->
        <div ref="bellRef" class="relative">
          <button @click="toggleNotif" class="relative p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="การแจ้งเตือน">
            <i class="fi-rr-bell text-lg"></i>
            <span v-if="notif.hasUnread"
                  class="absolute top-0.5 right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold ring-2 ring-white dark:ring-slate-900 flex items-center justify-center">
              {{ notif.unread > 99 ? '99+' : notif.unread }}
            </span>
          </button>

          <!-- Dropdown -->
          <div v-if="showNotif"
               class="absolute right-0 mt-2 w-80 sm:w-96 max-w-[calc(100vw-2rem)] card shadow-2xl shadow-slate-300/40 dark:shadow-black/40 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-800">
              <div class="font-semibold text-sm">การแจ้งเตือน</div>
              <button v-if="notif.unread > 0" @click="notif.markAllRead" class="text-xs text-blue-700 hover:underline">อ่านทั้งหมด</button>
            </div>

            <div class="max-h-[60vh] overflow-y-auto">
              <div v-if="notif.loading" class="p-6 text-center text-slate-500"><i class="fi-rr-spinner animate-spin text-xl"></i></div>
              <div v-else-if="notif.items.length === 0" class="p-8 text-center text-sm text-slate-500">
                <i class="fi-rr-bell-slash text-2xl text-slate-300"></i>
                <div class="mt-2">ยังไม่มีการแจ้งเตือน</div>
              </div>
              <button v-for="n in notif.items" :key="n.id" @click="clickNotif(n)"
                      :class="['w-full text-left px-4 py-3 border-b border-slate-50 dark:border-slate-800/60 hover:bg-blue-50/40 dark:hover:bg-slate-800/40 flex gap-3',
                               !n.read_at && 'bg-blue-50/30 dark:bg-blue-900/10']">
                <div :class="['w-9 h-9 shrink-0 rounded-xl flex items-center justify-center', (colorMap[n.data?.color] || colorMap.blue).bg, (colorMap[n.data?.color] || colorMap.blue).text]">
                  <i :class="n.data?.icon || 'fi-rr-bell'"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-baseline justify-between gap-2">
                    <div class="text-sm font-medium truncate">{{ n.data?.title || 'แจ้งเตือน' }}</div>
                    <div class="text-[11px] text-slate-500 shrink-0">{{ shortDate(n.created_at) }}</div>
                  </div>
                  <div class="text-xs text-slate-600 dark:text-slate-300 mt-0.5 line-clamp-2">{{ n.data?.message }}</div>
                </div>
                <span v-if="!n.read_at" class="w-2 h-2 mt-1.5 shrink-0 rounded-full bg-blue-600"></span>
              </button>
            </div>
          </div>
        </div>

        <div v-if="auth.user" ref="userMenuRef" class="relative ml-2 pl-2 border-l border-slate-100 dark:border-slate-800">
          <button @click="showUserMenu = !showUserMenu"
                  class="flex items-center gap-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg px-1.5 py-1"
                  title="เมนูผู้ใช้">
            <div class="w-9 h-9 rounded-full text-white flex items-center justify-center text-xs font-semibold"
                 style="background: linear-gradient(135deg,#1d4ed8,#0ea5e9);">{{ initials }}</div>
            <div class="hidden sm:block text-xs leading-tight text-left">
              <div class="font-medium text-slate-800 dark:text-slate-100">{{ auth.user.name }}</div>
              <div class="text-slate-500 dark:text-slate-400">{{ roleLabel }}</div>
            </div>
            <i class="fi-rr-angle-small-down text-slate-400 hidden sm:inline-block"></i>
          </button>

          <!-- Dropdown menu -->
          <div v-if="showUserMenu"
               class="absolute right-0 mt-2 w-60 max-w-[calc(100vw-2rem)] card shadow-2xl shadow-slate-300/40 dark:shadow-black/40 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800">
              <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-full text-white flex items-center justify-center text-sm font-semibold"
                     style="background: linear-gradient(135deg,#1d4ed8,#0ea5e9);">{{ initials }}</div>
                <div class="min-w-0">
                  <div class="font-medium text-sm truncate">{{ auth.user.name }}</div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ auth.user.phone }} · {{ roleLabel }}</div>
                </div>
              </div>
            </div>
            <button @click="openProfileFromMenu"
                    class="w-full text-left px-4 py-2.5 hover:bg-blue-50 dark:hover:bg-slate-800/60 flex items-center gap-2.5 text-sm">
              <i class="fi-rr-edit text-blue-700"></i> แก้ไขข้อมูลส่วนตัว
            </button>
            <button @click="logoutFromMenu"
                    class="w-full text-left px-4 py-2.5 hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 flex items-center gap-2.5 text-sm border-t border-slate-100 dark:border-slate-800">
              <i class="fi-rr-sign-out-alt"></i> ออกจากระบบ
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Profile edit modal -->
    <Modal :show="showProfile" max-width="max-w-md" @close="showProfile = false">
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="font-semibold">แก้ไขข้อมูลส่วนตัว</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">การเปลี่ยนชื่อจะไม่ส่งผลต่อประวัติเก่า (เก็บ snapshot ไว้แล้ว)</div>
          </div>
          <button @click="showProfile = false" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg"><i class="fi-rr-cross-small"></i></button>
        </div>

        <div v-if="profileFlash" class="card-tint-green p-3 text-sm mb-3"><i class="fi-rr-check-circle"></i> {{ profileFlash }}</div>
        <div v-if="profileErr?.general" class="card-tint-red p-3 text-sm mb-3"><i class="fi-rr-cross-circle"></i> {{ profileErr.general[0] }}</div>

        <form @submit.prevent="saveProfile" class="space-y-3">
          <div>
            <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">ชื่อ-สกุล</label>
            <input v-model="profileForm.name" required class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <div v-if="profileErr?.name" class="text-[11px] text-red-600 mt-1">{{ profileErr.name[0] }}</div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">เบอร์โทร</label>
              <input v-model="profileForm.phone" required class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <div v-if="profileErr?.phone" class="text-[11px] text-red-600 mt-1">{{ profileErr.phone[0] }}</div>
            </div>
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">อีเมล</label>
              <input v-model="profileForm.email" type="email" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">ตำแหน่ง</label>
              <select v-model="profileForm.position_type" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
                <option value="">— ไม่ระบุ —</option>
                <option v-for="p in ['ผู้ใหญ่บ้าน','กำนัน','ผู้ช่วยผู้ใหญ่บ้าน','อสม.','ส.อบต.','อื่นๆ']" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div v-if="profileForm.position_type === 'อื่นๆ'">
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">ระบุ</label>
              <input v-model="profileForm.position_other" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            </div>
          </div>
          <div class="card-tint-blue p-3 rounded-xl space-y-2">
            <div class="text-xs font-medium"><i class="fi-rr-lock"></i> เปลี่ยนรหัสผ่าน (ไม่บังคับ)</div>
            <div>
              <label class="block text-[11px] mb-1">รหัสผ่านปัจจุบัน (จำเป็นเมื่อเปลี่ยน)</label>
              <input v-model="profileForm.current_password" type="password" class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
              <div v-if="profileErr?.current_password" class="text-[11px] text-red-600 mt-1">{{ profileErr.current_password[0] }}</div>
            </div>
            <div>
              <label class="block text-[11px] mb-1">รหัสผ่านใหม่ (≥ 6 ตัว)</label>
              <input v-model="profileForm.password" type="password" minlength="6" class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
              <div v-if="profileErr?.password" class="text-[11px] text-red-600 mt-1">{{ profileErr.password[0] }}</div>
            </div>
          </div>
          <div class="flex gap-2 justify-end pt-1">
            <button type="button" @click="showProfile = false" class="btn-outline px-4 py-2 text-sm">ยกเลิก</button>
            <button type="submit" :disabled="profileSaving" class="btn-primary px-4 py-2 text-sm flex items-center gap-1.5">
              <i :class="['fi-rr-disk', profileSaving && 'animate-spin']"></i> บันทึก
            </button>
          </div>
        </form>
    </Modal>
  </header>
</template>

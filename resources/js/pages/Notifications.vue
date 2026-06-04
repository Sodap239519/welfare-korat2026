<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Loader from '@/components/Loader.vue';
import Pagination from '@/components/Pagination.vue';
import { ref, reactive, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useNotificationStore } from '@/stores/notifications';
import { shortDate } from '@/composables/useApi';

const router = useRouter();
const notif = useNotificationStore();

const data = ref({ data: [], total: 0, current_page: 1, last_page: 1 });
const loading = ref(false);
const filters = reactive({ q: '', status: '' });

async function load(page = 1) {
  loading.value = true;
  try {
    const params = { page, per_page: 30 };
    if (filters.q)      params.q = filters.q;
    if (filters.status) params.status = filters.status;
    const res = await axios.get('/api/notifications', { params });
    data.value = res.data;
  } finally { loading.value = false; }
}

async function clickNotif(n) {
  if (!n.read_at) await notif.markRead(n.id);
  // sync local copy
  const local = data.value.data.find(x => x.id === n.id);
  if (local) local.read_at = local.read_at || new Date().toISOString();
  if (n.data?.url) router.push(n.data.url);
}

async function markAll() {
  await notif.markAllRead();
  data.value.data.forEach(n => { n.read_at = n.read_at || new Date().toISOString(); });
}

const colorMap = {
  blue:   { bg: 'card-tint-blue',   text: 'text-blue-700' },
  sky:    { bg: 'card-tint-sky',    text: 'text-sky-700' },
  green:  { bg: 'card-tint-green',  text: 'text-green-700' },
  orange: { bg: 'card-tint-orange', text: 'text-orange-700' },
  red:    { bg: 'card-tint-red',    text: 'text-red-700' },
};

const filtered = computed(() => {
  let list = data.value.data;
  if (filters.status === 'unread')      list = list.filter(n => !n.read_at);
  else if (filters.status === 'read')   list = list.filter(n => n.read_at);
  return list;
});

onMounted(() => load(1));
let timer;
import { watch } from 'vue';
watch(() => filters.q, () => { clearTimeout(timer); timer = setTimeout(() => load(1), 300); });
watch(() => filters.status, () => load(1));
</script>

<template>
  <AppLayout title="การแจ้งเตือนทั้งหมด" :subtitle="`รวม ${data.total} รายการ · ยังไม่อ่าน ${notif.unread}`">
    <div class="space-y-4">

      <div class="card p-3 space-y-2">
        <!-- Search input — full width row -->
        <div class="relative w-full">
          <i class="fi-rr-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <input v-model="filters.q" placeholder="ค้นหาข้อความแจ้งเตือน"
            class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
          <button v-if="filters.q" @click="filters.q = ''" title="ล้าง"
                  class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-slate-200/80 hover:bg-slate-300 flex items-center justify-center tap-transparent">
            <i class="fi-rr-cross-small text-[10px]"></i>
          </button>
        </div>
        <!-- Filter + bulk action row -->
        <div class="flex items-center gap-2">
          <div class="relative flex-1 min-w-0">
            <select v-model="filters.status" class="w-full pl-3 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <option value="">ทุกสถานะ</option>
              <option value="unread">ยังไม่อ่าน</option>
              <option value="read">อ่านแล้ว</option>
            </select>
          </div>
          <button v-if="notif.unread > 0" @click="markAll" class="btn-outline px-3 py-2.5 text-sm flex items-center gap-1.5 shrink-0 tap-transparent">
            <i class="fi-rr-check"></i> <span class="hidden sm:inline">อ่านทั้งหมด</span>
          </button>
        </div>
      </div>

      <Loader v-if="loading" label="กำลังโหลดการแจ้งเตือน..." py="py-8" :size="40" />

      <div v-else-if="filtered.length === 0" class="card p-12 text-center text-sm text-slate-500">
        <i class="fi-rr-bell-slash text-3xl text-slate-300"></i>
        <div class="mt-2">{{ filters.q || filters.status ? 'ไม่พบรายการตามเงื่อนไข' : 'ยังไม่มีการแจ้งเตือน' }}</div>
      </div>

      <div v-else class="card divide-y divide-slate-50 dark:divide-slate-800/60">
        <button v-for="n in filtered" :key="n.id" @click="clickNotif(n)"
                :class="['w-full text-left p-4 hover:bg-blue-50/40 dark:hover:bg-slate-800/40 flex gap-3 items-start',
                         !n.read_at && 'bg-blue-50/30 dark:bg-blue-900/10']">
          <div :class="['w-10 h-10 shrink-0 rounded-xl flex items-center justify-center',
                       (colorMap[n.data?.color] || colorMap.blue).bg, (colorMap[n.data?.color] || colorMap.blue).text]">
            <i :class="n.data?.icon || 'fi-rr-bell'"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-baseline justify-between gap-2 flex-wrap">
              <div class="text-sm font-medium">{{ n.data?.title || 'แจ้งเตือน' }}</div>
              <div class="text-[11px] text-slate-500 shrink-0">{{ shortDate(n.created_at) }}</div>
            </div>
            <div class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">{{ n.data?.message }}</div>
          </div>
          <span v-if="!n.read_at" class="w-2 h-2 mt-2 shrink-0 rounded-full bg-blue-600" title="ยังไม่อ่าน"></span>
        </button>
      </div>

      <!-- Pagination — ใช้ shared Pagination component ให้ consistent -->
      <Pagination :current="data.current_page" :last="data.last_page" :total="data.total" unit="รายการ" @go="load" />

    </div>
  </AppLayout>
</template>

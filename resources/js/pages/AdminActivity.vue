<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Loader from '@/components/Loader.vue';
import Pagination from '@/components/Pagination.vue';
import { ref, reactive, onMounted, watch } from 'vue';
import axios from 'axios';
import { formatNumber, shortDate } from '@/composables/useApi';

const data = ref({ data: [], total: 0, current_page: 1, last_page: 1 });
const stats = ref(null);
const filters = reactive({ q: '', date: '' });
const selectedTypes = ref([]);   // multi-select
const loading = ref(false);

// ประเภทการใช้งานทั้งหมดในระบบ
const typeTabs = [
  { key: 'created',        label: 'เพิ่มข้อมูล',   icon: 'fi-rr-plus',       cls: 'green' },
  { key: 'updated',        label: 'แก้ไขข้อมูล',   icon: 'fi-rr-edit',       cls: 'blue' },
  { key: 'deleted',        label: 'ลบข้อมูล',      icon: 'fi-rr-trash',      cls: 'red' },
  { key: 'settings',       label: 'ตั้งค่าระบบ',    icon: 'fi-rr-settings',   cls: 'indigo' },
  { key: 'sop_phase',      label: 'ขั้น SOP',      icon: 'fi-rr-list-check', cls: 'amber' },
  { key: 'village_coords', label: 'พิกัดหมู่บ้าน',  icon: 'fi-rr-marker',     cls: 'sky' },
];
const colorCls = {
  green:  { on: 'bg-green-600 text-white border-green-600',   off: 'border-green-300 dark:border-green-800 text-green-700 dark:text-green-300 hover:bg-green-50 dark:hover:bg-green-900/30' },
  blue:   { on: 'bg-blue-600 text-white border-blue-600',     off: 'border-blue-300 dark:border-blue-800 text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/30' },
  red:    { on: 'bg-red-600 text-white border-red-600',       off: 'border-red-300 dark:border-red-800 text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30' },
  indigo: { on: 'bg-indigo-600 text-white border-indigo-600', off: 'border-indigo-300 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30' },
  amber:  { on: 'bg-amber-500 text-white border-amber-500',   off: 'border-amber-300 dark:border-amber-800 text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-900/30' },
  sky:    { on: 'bg-sky-600 text-white border-sky-600',       off: 'border-sky-300 dark:border-sky-800 text-sky-700 dark:text-sky-300 hover:bg-sky-50 dark:hover:bg-sky-900/30' },
};
function toggleType(key) {
  const i = selectedTypes.value.indexOf(key);
  if (i >= 0) selectedTypes.value.splice(i, 1);
  else selectedTypes.value.push(key);
}

async function load(page = 1) {
  loading.value = true;
  try {
    const params = { page };
    if (filters.q) params.q = filters.q;
    if (filters.date) params.date = filters.date;
    if (selectedTypes.value.length) params.types = selectedTypes.value;
    const { data: d } = await axios.get('/api/admin/activity', { params });
    data.value = d;
    stats.value = (await axios.get('/api/admin/activity/stats')).data;
  } finally { loading.value = false; }
}

onMounted(() => load());
let timer;
watch(() => filters.q, () => { clearTimeout(timer); timer = setTimeout(() => load(1), 300); });
watch([() => filters.date, selectedTypes], () => load(1), { deep: true });

const eventColor = {
  created:  'card-tint-green text-green-700',
  updated:  'card-tint-blue text-blue-700',
  deleted:  'card-tint-red text-red-700',
};
const eventIcon = {
  created: 'fi-rr-plus',
  updated: 'fi-rr-edit',
  deleted: 'fi-rr-trash',
};
const logNameMeta = {
  settings:       { icon: 'fi-rr-settings',   cls: 'card-tint-blue text-indigo-700' },
  sop_phase:      { icon: 'fi-rr-list-check', cls: 'card-tint-orange text-amber-700' },
  village_coords: { icon: 'fi-rr-marker',     cls: 'card-tint-sky text-sky-700' },
};
function rowCls(a)  { return eventColor[a.event] || logNameMeta[a.log_name]?.cls || 'st-4-1'; }
function rowIcon(a) { return eventIcon[a.event] || logNameMeta[a.log_name]?.icon || 'fi-rr-circle'; }
</script>

<template>
  <AppLayout title="ประวัติการใช้งานระบบ" subtitle="Audit Log · Super Admin only">
    <div class="space-y-4">

      <div v-if="stats" class="grid grid-cols-2 lg:grid-cols-3 gap-3">
        <div class="card-tint-blue p-4"><div class="text-xs opacity-80">เหตุการณ์วันนี้</div><div class="text-2xl font-bold mt-1">{{ formatNumber(stats.today) }}</div></div>
        <div class="card-tint-sky p-4"><div class="text-xs opacity-80">สัปดาห์นี้</div><div class="text-2xl font-bold mt-1 text-sky-800">{{ formatNumber(stats.this_week) }}</div></div>
        <div class="card-tint-red p-4 col-span-2 lg:col-span-1"><div class="text-xs opacity-80">Login ไม่สำเร็จ</div><div class="text-2xl font-bold mt-1 text-red-700">{{ formatNumber(stats.login_fail) }}</div></div>
      </div>

      <div class="card p-3">
        <!-- Desktop: ค้นหา + Tab + วันที่ แถวเดียว · Mobile: (ค้นหา+วันที่) แถว1 · Tab แถว2 เลื่อนได้ -->
        <div class="flex flex-wrap lg:flex-nowrap items-center gap-2">

          <!-- ค้นหา -->
          <div class="relative order-1 flex-1 min-w-[150px] lg:flex-none lg:w-56">
            <i class="fi-rr-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input v-model="filters.q" placeholder="ค้นหาคำในประวัติ"
              class="w-full pl-10 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500">
            <button v-if="filters.q" @click="filters.q = ''" title="ล้าง"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>

          <!-- วันที่ — mobile: ข้างค้นหา (order-2) · desktop: ขวาสุด (order-3) -->
          <div class="relative order-2 lg:order-3 shrink-0">
            <input v-model="filters.date" type="date"
                   class="w-[150px] pl-3 pr-8 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <button v-if="filters.date" @click="filters.date = ''" title="ล้าง"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>

          <!-- Tab ประเภท — mobile: แถวใหม่ (w-full) เลื่อนซ้ายขวา · desktop: กลาง (flex-1) -->
          <div class="order-3 lg:order-2 w-full lg:w-auto lg:flex-1 min-w-0 overflow-x-auto flex flex-nowrap items-center gap-2 py-0.5">
            <button @click="selectedTypes = []"
                    :class="['shrink-0 whitespace-nowrap px-3 py-1.5 rounded-full text-xs font-medium border flex items-center gap-1.5 transition',
                             selectedTypes.length === 0
                               ? 'bg-slate-900 text-white border-slate-900 dark:bg-white dark:text-slate-900 dark:border-white'
                               : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800']">
              <i class="fi-rr-apps"></i> ทั้งหมด
            </button>
            <button v-for="t in typeTabs" :key="t.key" @click="toggleType(t.key)"
                    :class="['shrink-0 whitespace-nowrap px-3 py-1.5 rounded-full text-xs font-medium border flex items-center gap-1.5 transition',
                             selectedTypes.includes(t.key) ? colorCls[t.cls].on : colorCls[t.cls].off]">
              <i :class="t.icon"></i> {{ t.label }}
              <i v-if="selectedTypes.includes(t.key)" class="fi-rr-check text-[9px]"></i>
            </button>
          </div>
        </div>
      </div>

      <Loader v-if="loading" label="กำลังโหลดประวัติ..." py="py-8" :size="40" />

      <div v-else class="card divide-y divide-slate-50 dark:divide-slate-800/60">
        <div v-for="a in data.data" :key="a.id" class="p-4 flex gap-3">
          <div :class="['w-10 h-10 shrink-0 rounded-xl flex items-center justify-center', rowCls(a)]">
            <i :class="rowIcon(a)"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-baseline justify-between gap-2 flex-wrap">
              <div class="text-sm line-clamp-2 min-w-0" :title="(a.causer || 'ระบบ') + ' ' + a.description + (a.subject ? ' · ' + a.subject : '')">
                <strong v-if="a.causer">{{ a.causer }}</strong>
                <span v-else class="text-slate-500">ระบบ</span>
                <span> {{ a.description }}</span>
                <span v-if="a.subject" class="text-slate-500"> · {{ a.subject }}</span>
              </div>
              <div class="text-xs text-slate-500 dark:text-slate-400 shrink-0">{{ shortDate(a.created_at) }}</div>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              <span v-if="a.log_name">[{{ a.log_name }}]</span>
              <span v-if="a.event"> · {{ a.event }}</span>
            </div>
          </div>
        </div>
        <div v-if="data.data.length === 0" class="p-8 text-center text-sm text-slate-500">
          ยังไม่มี Activity Log
          <div class="text-xs mt-1 opacity-70">(จะเริ่มบันทึกอัตโนมัติเมื่อมีการแก้ไขข้อมูลผ่าน UI)</div>
        </div>
      </div>

      <Pagination
        :current="data.current_page"
        :last="data.last_page"
        :total="data.total"
        unit="เหตุการณ์"
        @go="load" />
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { ref, reactive, onMounted, watch } from 'vue';
import axios from 'axios';
import { formatNumber, shortDate } from '@/composables/useApi';

const data = ref({ data: [], total: 0, current_page: 1, last_page: 1 });
const stats = ref(null);
const filters = reactive({ q: '', event: '', date: '' });
const loading = ref(false);

async function load(page = 1) {
  loading.value = true;
  try {
    const params = { ...filters, page };
    Object.keys(params).forEach(k => params[k] === '' && delete params[k]);
    const { data: d } = await axios.get('/api/admin/activity', { params });
    data.value = d;
    stats.value = (await axios.get('/api/admin/activity/stats')).data;
  } finally { loading.value = false; }
}

onMounted(() => load());
let timer;
watch(() => filters.q, () => { clearTimeout(timer); timer = setTimeout(() => load(1), 300); });
watch(() => [filters.event, filters.date], () => load(1));

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
        <div class="flex items-center gap-2 min-w-0">
          <div class="relative flex-1 min-w-0">
            <i class="fi-rr-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input v-model="filters.q" placeholder="ค้นหาคำในประวัติ"
              class="w-full pl-10 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500">
            <button v-if="filters.q" @click="filters.q = ''" title="ล้าง"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2 mt-2">
          <div class="relative min-w-0">
            <select v-model="filters.event" class="w-full min-w-0 pl-3 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <option value="">ทุกประเภท</option>
              <option value="created">created</option>
              <option value="updated">updated</option>
              <option value="deleted">deleted</option>
            </select>
            <button v-if="filters.event" @click="filters.event = ''" title="ล้าง"
                    class="absolute right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>
          <div class="relative min-w-0">
            <input v-model="filters.date" type="date" class="w-full min-w-0 pl-3 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <button v-if="filters.date" @click="filters.date = ''" title="ล้าง"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-8 text-slate-500"><i class="fi-rr-spinner animate-spin text-2xl"></i></div>

      <div v-else class="card divide-y divide-slate-50 dark:divide-slate-800/60">
        <div v-for="a in data.data" :key="a.id" class="p-4 flex gap-3">
          <div :class="['w-10 h-10 shrink-0 rounded-xl flex items-center justify-center', eventColor[a.event] || 'st-4-1']">
            <i :class="eventIcon[a.event] || 'fi-rr-circle'"></i>
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

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { ref, reactive, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { formatNumber, shortDate, statusColorClass } from '@/composables/useApi';

const router = useRouter();

const statuses = ref([]);
const amphurOpts = ref([]);
const tambonOpts = ref([]);
const villageOpts = ref([]);

const filters = reactive({
  q: '',
  status: '',
  amphur_id: '',
  tambon_id: '',
  village_id: '',
});
const filtersOpen = ref(false);
const data = ref({ data: [], total: 0, current_page: 1, last_page: 1, per_page: 50 });
const loading = ref(false);
const statusCounts = ref({}); // by status code

async function loadOpts() {
  const [s, a] = await Promise.all([
    axios.get('/api/ref/statuses'),
    axios.get('/api/ref/amphurs'),
  ]);
  statuses.value = s.data.data;
  amphurOpts.value = a.data.data;
}
async function loadTambons() {
  filters.tambon_id = ''; filters.village_id = ''; tambonOpts.value = []; villageOpts.value = [];
  if (!filters.amphur_id) return;
  tambonOpts.value = (await axios.get('/api/ref/tambons', { params: { amphur_id: filters.amphur_id } })).data.data;
}
async function loadVillages() {
  filters.village_id = ''; villageOpts.value = [];
  if (!filters.tambon_id) return;
  villageOpts.value = (await axios.get('/api/ref/villages', { params: { tambon_id: filters.tambon_id } })).data.data;
}

async function load(page = 1) {
  loading.value = true;
  try {
    const params = { ...filters, page };
    Object.keys(params).forEach(k => params[k] === '' && delete params[k]);
    const { data: d } = await axios.get('/api/targets', { params });
    data.value = d;

    // Also load total count + per-status counts for pill counters
    const { data: stats } = await axios.get('/api/dashboard/stats', {
      params: { amphur_id: filters.amphur_id, tambon_id: filters.tambon_id, village_id: filters.village_id },
    });
    statusCounts.value = stats.by_status;
    statusCounts.value._total = stats.total;
  } finally {
    loading.value = false;
  }
}

onMounted(async () => { await loadOpts(); await load(); });

let searchTimer;
watch(() => filters.q, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => load(1), 300);
});
watch(() => [filters.status, filters.amphur_id, filters.tambon_id, filters.village_id], () => load(1));

const activeFilterCount = computed(() => {
  return ['amphur_id','tambon_id','village_id','status'].filter(k => filters[k]).length;
});

function goDetail(id) { router.push({ name: 'target-detail', params: { id } }); }
function selectStatus(code) { filters.status = filters.status === code ? '' : code; }
</script>

<template>
  <AppLayout title="รายชื่อเป้าหมาย" :subtitle="`${formatNumber(statusCounts._total || 0)} ราย · ปี 2569`">
    <div class="space-y-4">

      <div class="card-hero p-4 flex items-center justify-between">
        <div>
          <div class="text-xs opacity-80">เป้าหมายในขอบเขตที่เลือก</div>
          <div class="text-2xl lg:text-3xl font-bold mt-0.5">{{ formatNumber(statusCounts._total || 0) }} <span class="text-sm font-normal opacity-80">คน</span></div>
        </div>
        <div class="text-right text-xs opacity-90">
          <div>ลงทะเบียนแล้ว <strong class="text-base">{{ formatNumber(Object.entries(statusCounts).filter(([k]) => !['0','4.1','_total'].includes(k)).reduce((a,[,n]) => a + Number(n||0), 0)) }}</strong> ราย</div>
          <div v-if="data.total">หน้านี้ {{ data.from || 0 }}-{{ data.to || 0 }} จาก {{ formatNumber(data.total) }}</div>
        </div>
      </div>

      <!-- Search + actions -->
      <div class="card p-3">
        <div class="flex items-center gap-2 min-w-0">
          <div class="relative flex-1 min-w-0">
            <i class="fi-rr-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input v-model="filters.q" placeholder="ค้นหาด้วย ชื่อ-สกุล"
              class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <button @click="filtersOpen = !filtersOpen" class="btn-outline shrink-0 px-3 py-2.5 text-sm flex items-center gap-1.5">
            <i class="fi-rr-filter"></i> <span class="hidden sm:inline">ตัวกรอง</span>
            <span v-if="activeFilterCount" class="bg-blue-700 text-white text-[10px] rounded-full px-1.5 py-0.5 ml-1">{{ activeFilterCount }}</span>
          </button>
        </div>

        <div v-show="filtersOpen" class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
          <select v-model="filters.amphur_id" @change="loadTambons" class="w-full min-w-0 px-3 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <option value="">ทุกอำเภอ</option>
            <option v-for="a in amphurOpts" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <select v-model="filters.tambon_id" @change="loadVillages" :disabled="!filters.amphur_id" class="w-full min-w-0 px-3 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm disabled:opacity-40">
            <option value="">ทุกตำบล</option>
            <option v-for="t in tambonOpts" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <select v-model="filters.village_id" :disabled="!filters.tambon_id" class="w-full min-w-0 px-3 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm disabled:opacity-40">
            <option value="">ทุกหมู่บ้าน</option>
            <option v-for="v in villageOpts" :key="v.id" :value="v.id">{{ v.name }}{{ v.moo ? ' (หมู่ '+v.moo+')' : '' }}</option>
          </select>
        </div>
      </div>

      <!-- Status pill counters (wrap, no horizontal scroll) -->
      <div class="flex flex-wrap gap-2">
        <button @click="filters.status = ''" :class="['px-3 py-1.5 rounded-full text-xs', !filters.status ? 'bg-blue-700 text-white' : 'border border-slate-100 dark:border-slate-800']">
          ทั้งหมด · {{ formatNumber(statusCounts._total || 0) }}
        </button>
        <button v-for="s in statuses" :key="s.code" @click="selectStatus(s.code)"
                :class="['px-3 py-1.5 rounded-full text-xs', s.color, filters.status === s.code ? 'ring-2 ring-blue-500' : '']">
          {{ s.code }} · {{ formatNumber(statusCounts[s.code] || 0) }}
        </button>
      </div>

      <!-- DESKTOP table -->
      <div class="card hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-xs text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
            <tr>
              <th class="text-left py-3 px-3 w-12">#</th>
              <th class="text-left">ชื่อ - สกุล</th>
              <th class="text-left">หมู่บ้าน / ตำบล</th>
              <th class="text-left">สถานะ</th>
              <th class="text-left">ช่องทาง / หมายเหตุ</th>
              <th class="text-left">อัปเดต</th>
              <th class="text-right pr-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60">
            <tr v-if="loading" v-for="i in 5" :key="`s-${i}`"><td colspan="7" class="py-3 px-3"><div class="h-4 bg-slate-100 dark:bg-slate-800 rounded animate-pulse"></div></td></tr>
            <tr v-else v-for="(t, i) in data.data" :key="t.id" class="hover:bg-blue-50/30 dark:hover:bg-slate-800/30 cursor-pointer" @click="goDetail(t.id)">
              <td class="py-3 px-3 text-slate-500">{{ (data.from || 1) + i - 1 }}</td>
              <td>
                <div class="font-medium">{{ t.name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ t.poverty_level || '—' }} · รายได้ {{ formatNumber(t.annual_income) }} บ./ปี</div>
              </td>
              <td>
                {{ t.village || '—' }}<span v-if="t.moo"> หมู่ {{ t.moo }}</span>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ t.tambon }} · {{ t.amphur }}</div>
              </td>
              <td>
                <span v-if="t.status_code" :class="['inline-block px-2 py-1 rounded-md text-xs', statusColorClass(t.status_code)]">{{ t.status_code }}</span>
                <span v-else class="text-xs text-slate-400">ยังไม่อัปเดต</span>
              </td>
              <td class="text-xs text-slate-500 dark:text-slate-400 max-w-[200px]">
                <div v-if="t.channel" class="truncate">
                  {{ t.channel }}<span v-if="t.sub_channel_label" class="text-slate-700 dark:text-slate-300"> ({{ t.sub_channel_label }})</span>
                </div>
                <div v-if="t.note" class="truncate text-[11px] mt-0.5">{{ t.note }}</div>
                <span v-if="!t.channel && !t.note">—</span>
              </td>
              <td class="text-xs text-slate-500">{{ shortDate(t.updated_at) }}</td>
              <td class="text-right pr-3"><i class="fi-rr-angle-right text-slate-400"></i></td>
            </tr>
            <tr v-if="!loading && data.data.length === 0">
              <td colspan="7" class="py-8 text-center text-slate-500 text-sm">ไม่พบรายชื่อตามเงื่อนไข</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- MOBILE card list -->
      <div class="md:hidden space-y-2">
        <div v-if="loading" v-for="i in 5" :key="`m-${i}`" class="card p-3 animate-pulse">
          <div class="h-4 bg-slate-100 dark:bg-slate-800 rounded w-3/4 mb-2"></div>
          <div class="h-3 bg-slate-100 dark:bg-slate-800 rounded w-1/2"></div>
        </div>
        <button v-else v-for="t in data.data" :key="t.id" @click="goDetail(t.id)" class="card p-3 block w-full text-left active:bg-blue-50 dark:active:bg-slate-800/50">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
              <div class="font-medium truncate">{{ t.name }}</div>
              <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ t.village }}{{ t.moo ? ' หมู่ '+t.moo : '' }} · {{ t.tambon }}</div>
            </div>
            <span v-if="t.status_code" :class="['shrink-0 inline-block px-2 py-1 rounded-md text-[11px] font-medium', statusColorClass(t.status_code)]">{{ t.status_code }}</span>
            <span v-else class="shrink-0 text-[11px] text-slate-400 px-2 py-1">—</span>
          </div>
          <div class="flex items-center justify-between mt-2 text-[11px] text-slate-500 dark:text-slate-400">
            <span class="truncate">{{ t.poverty_level || '—' }} · {{ formatNumber(t.annual_income) }} บ./ปี</span>
            <span class="shrink-0">{{ shortDate(t.updated_at) }}</span>
          </div>
        </button>
        <div v-if="!loading && data.data.length === 0" class="card p-6 text-center text-sm text-slate-500">ไม่พบรายชื่อ</div>
      </div>

      <!-- Pagination -->
      <Pagination
        :current="data.current_page"
        :last="data.last_page"
        :total="data.total"
        unit="ราย"
        @go="load" />


    </div>
  </AppLayout>
</template>

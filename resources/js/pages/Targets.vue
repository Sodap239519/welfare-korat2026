<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { ref, reactive, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { formatNumber, shortDate, statusColorClass, statusShort } from '@/composables/useApi';

const router = useRouter();

const statuses = ref([]);
const channels = ref([]);
const banks = ref([]);
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
const statusCounts = ref({});

// Bulk selection
const selectedIds = ref(new Set());
const showBulkModal = ref(false);
const bulkForm = reactive({ status_code: '', channel_id: '', sub_channel: '', note: '' });
const bulkErrors = ref({});
const bulkSaving = ref(false);
const flashOk = ref('');

const bulkStatusObj = computed(() => statuses.value.find(s => s.code === bulkForm.status_code));
const bulkChannelObj = computed(() => channels.value.find(c => c.id === Number(bulkForm.channel_id)));
const bulkNeedsBank = computed(() => bulkChannelObj.value?.code === 'bank');

const selectedCount = computed(() => selectedIds.value.size);
const pageIds = computed(() => data.value.data.map(t => t.id));
const allPageSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every(id => selectedIds.value.has(id)));
const someSelected = computed(() => pageIds.value.some(id => selectedIds.value.has(id)) && !allPageSelected.value);

function toggleAllPage() {
  const ids = pageIds.value;
  const all = allPageSelected.value;
  const next = new Set(selectedIds.value);
  ids.forEach(id => all ? next.delete(id) : next.add(id));
  selectedIds.value = next;
}
function toggleOne(id) {
  const next = new Set(selectedIds.value);
  next.has(id) ? next.delete(id) : next.add(id);
  selectedIds.value = next;
}
function clearSelection() {
  selectedIds.value = new Set();
}
function openBulkModal() {
  Object.assign(bulkForm, { status_code: '', channel_id: '', sub_channel: '', note: '' });
  bulkErrors.value = {};
  showBulkModal.value = true;
}
async function submitBulk() {
  bulkSaving.value = true;
  bulkErrors.value = {};
  try {
    const payload = {
      target_ids: Array.from(selectedIds.value),
      status_code: bulkForm.status_code,
      channel_id: bulkForm.channel_id || null,
      sub_channel: bulkNeedsBank.value ? (bulkForm.sub_channel || null) : null,
      note: bulkForm.note || null,
    };
    const { data: res } = await axios.post('/api/targets/bulk-status', payload);
    flashOk.value = res.message;
    showBulkModal.value = false;
    clearSelection();
    setTimeout(() => (flashOk.value = ''), 4000);
    await load(data.value.current_page);
  } catch (e) {
    bulkErrors.value = e.response?.data?.errors || { general: [e.response?.data?.message || 'ผิดพลาด'] };
  } finally { bulkSaving.value = false; }
}

async function loadOpts() {
  const [s, a, c, b] = await Promise.all([
    axios.get('/api/ref/statuses'),
    axios.get('/api/ref/amphurs'),
    axios.get('/api/ref/channels'),
    axios.get('/api/ref/banks'),
  ]);
  statuses.value = s.data.data;
  amphurOpts.value = a.data.data;
  channels.value = c.data.data;
  banks.value = b.data.data;
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

      <div v-if="flashOk" class="card-tint-green p-3 text-sm">
        <i class="fi-rr-check-circle"></i> {{ flashOk }}
      </div>

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

      <!-- Status pill counters -->
      <div class="flex flex-wrap gap-2">
        <button @click="filters.status = ''" :class="['px-3 py-1.5 rounded-full text-xs', !filters.status ? 'bg-blue-700 text-white' : 'border border-slate-100 dark:border-slate-800']">
          ทั้งหมด · {{ formatNumber(statusCounts._total || 0) }}
        </button>
        <button v-for="s in statuses" :key="s.code" @click="selectStatus(s.code)"
                :class="['px-3 py-1.5 rounded-full text-xs', s.color, filters.status === s.code ? 'ring-2 ring-blue-500' : '']">
          {{ statusShort(s.code) }} · {{ formatNumber(statusCounts[s.code] || 0) }}
        </button>
      </div>

      <!-- DESKTOP table -->
      <div class="card hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-xs text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
            <tr>
              <th class="text-left py-3 px-3 w-10">
                <input type="checkbox" class="rounded text-blue-600" :checked="allPageSelected" :indeterminate.prop="someSelected" @change="toggleAllPage">
              </th>
              <th class="text-left">#</th>
              <th class="text-left">ชื่อ - สกุล</th>
              <th class="text-left">หมู่บ้าน / ตำบล</th>
              <th class="text-left">สถานะ</th>
              <th class="text-left">ช่องทาง / หมายเหตุ</th>
              <th class="text-left">อัปเดต</th>
              <th class="text-right pr-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60">
            <tr v-if="loading" v-for="i in 5" :key="`s-${i}`"><td colspan="8" class="py-3 px-3"><div class="h-4 bg-slate-100 dark:bg-slate-800 rounded animate-pulse"></div></td></tr>
            <tr v-else v-for="(t, i) in data.data" :key="t.id" :class="['hover:bg-blue-50/30 dark:hover:bg-slate-800/30', selectedIds.has(t.id) && 'bg-blue-50/60 dark:bg-blue-900/20']">
              <td class="py-3 px-3" @click.stop>
                <input type="checkbox" class="rounded text-blue-600" :checked="selectedIds.has(t.id)" @change="toggleOne(t.id)">
              </td>
              <td class="text-slate-500 cursor-pointer" @click="goDetail(t.id)">{{ (data.from || 1) + i - 1 }}</td>
              <td class="cursor-pointer" @click="goDetail(t.id)">
                <div class="font-medium">{{ t.name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ t.poverty_level || '—' }} · รายได้ {{ formatNumber(t.annual_income) }} บ./ปี</div>
              </td>
              <td class="cursor-pointer" @click="goDetail(t.id)">
                {{ t.village || '—' }}<span v-if="t.moo"> หมู่ {{ t.moo }}</span>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ t.tambon }} · {{ t.amphur }}</div>
              </td>
              <td class="cursor-pointer" @click="goDetail(t.id)">
                <span v-if="t.status_code" :class="['inline-block px-2 py-1 rounded-md text-xs whitespace-nowrap', statusColorClass(t.status_code)]">{{ statusShort(t.status_code) }}</span>
                <span v-else class="text-xs text-slate-400">ยังไม่อัปเดต</span>
              </td>
              <td class="text-xs text-slate-500 dark:text-slate-400 max-w-[200px] cursor-pointer" @click="goDetail(t.id)">
                <div v-if="t.channel" class="truncate">
                  {{ t.channel }}<span v-if="t.sub_channel_label" class="text-slate-700 dark:text-slate-300"> ({{ t.sub_channel_label }})</span>
                </div>
                <div v-if="t.note" class="truncate text-[11px] mt-0.5">{{ t.note }}</div>
                <span v-if="!t.channel && !t.note">—</span>
              </td>
              <td class="text-xs text-slate-500 cursor-pointer" @click="goDetail(t.id)">{{ shortDate(t.updated_at) }}</td>
              <td class="text-right pr-3 cursor-pointer" @click="goDetail(t.id)"><i class="fi-rr-angle-right text-slate-400"></i></td>
            </tr>
            <tr v-if="!loading && data.data.length === 0">
              <td colspan="8" class="py-8 text-center text-slate-500 text-sm">ไม่พบรายชื่อตามเงื่อนไข</td>
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
        <div v-else v-for="t in data.data" :key="t.id" :class="['card p-3 flex items-start gap-2', selectedIds.has(t.id) && 'border-blue-300 dark:border-blue-700']">
          <input type="checkbox" class="mt-1 shrink-0 rounded text-blue-600" :checked="selectedIds.has(t.id)" @change="toggleOne(t.id)">
          <button @click="goDetail(t.id)" class="flex-1 min-w-0 text-left">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0 flex-1">
                <div class="font-medium truncate">{{ t.name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ t.village }}{{ t.moo ? ' หมู่ '+t.moo : '' }} · {{ t.tambon }}</div>
              </div>
              <span v-if="t.status_code" :class="['shrink-0 inline-block px-2 py-1 rounded-md text-[11px] font-medium whitespace-nowrap', statusColorClass(t.status_code)]">{{ statusShort(t.status_code) }}</span>
              <span v-else class="shrink-0 text-[11px] text-slate-400 px-2 py-1">—</span>
            </div>
            <div class="flex items-center justify-between mt-2 text-[11px] text-slate-500 dark:text-slate-400">
              <span class="truncate">{{ t.poverty_level || '—' }} · {{ formatNumber(t.annual_income) }} บ./ปี</span>
              <span class="shrink-0">{{ shortDate(t.updated_at) }}</span>
            </div>
          </button>
        </div>
        <div v-if="!loading && data.data.length === 0" class="card p-6 text-center text-sm text-slate-500">ไม่พบรายชื่อ</div>
      </div>

      <!-- Pagination -->
      <Pagination :current="data.current_page" :last="data.last_page" :total="data.total" unit="ราย" @go="load" />

      <!-- Bulk action bar (floating) -->
      <div v-if="selectedCount > 0" class="fixed left-0 right-0 z-40 px-4 pointer-events-none"
           style="bottom: max(env(safe-area-inset-bottom), 5rem);">
        <div class="max-w-3xl mx-auto card-hero p-3 flex items-center gap-3 pointer-events-auto shadow-2xl shadow-blue-900/30">
          <div class="text-sm">
            <div class="font-semibold">เลือก {{ selectedCount }} ราย</div>
            <button @click="clearSelection" class="text-xs opacity-80 hover:opacity-100 underline">ยกเลิกการเลือก</button>
          </div>
          <button @click="openBulkModal" class="ml-auto px-4 py-2 rounded-xl bg-white text-blue-700 font-medium text-sm flex items-center gap-1.5 hover:bg-blue-50">
            <i class="fi-rr-edit"></i> อัปเดตสถานะ
          </button>
        </div>
      </div>

      <!-- Bulk modal -->
      <div v-if="showBulkModal" class="fixed inset-0 z-50 bg-slate-900/50 flex items-end sm:items-center justify-center" @click.self="showBulkModal = false">
        <div class="card w-full sm:max-w-lg p-5 rounded-t-3xl sm:rounded-3xl max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between mb-3">
            <div>
              <div class="font-semibold">อัปเดตสถานะรายกลุ่ม</div>
              <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">จะอัปเดต {{ selectedCount }} ราย</div>
            </div>
            <button @click="showBulkModal = false" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg"><i class="fi-rr-cross-small"></i></button>
          </div>

          <div v-if="bulkErrors.general" class="card-tint-red p-3 text-sm mb-3"><i class="fi-rr-cross-circle"></i> {{ bulkErrors.general[0] }}</div>

          <form @submit.prevent="submitBulk" class="space-y-4">
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">สถานะใหม่ <span class="text-red-600">*</span></label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <label v-for="s in statuses" :key="s.code"
                       :class="['flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer hover:bg-blue-50/30 dark:hover:bg-slate-800/50',
                                bulkForm.status_code === s.code ? 'border-2 border-blue-500 card-tint-blue' : 'border-slate-100 dark:border-slate-800']">
                  <input type="radio" v-model="bulkForm.status_code" :value="s.code" class="text-blue-600">
                  <span :class="['text-sm px-2 py-0.5 rounded whitespace-nowrap', s.color]">{{ statusShort(s.code) }}</span>
                </label>
              </div>
              <div v-if="bulkErrors.status_code" class="text-[11px] text-red-600 mt-1">{{ bulkErrors.status_code[0] }}</div>
            </div>

            <div v-if="bulkStatusObj?.requires_channel || bulkForm.channel_id">
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">
                ช่องทาง <span v-if="bulkStatusObj?.requires_channel" class="text-red-600">*</span>
              </label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <label v-for="c in channels" :key="c.id"
                       :class="['flex flex-col items-center gap-1.5 p-3 rounded-xl border cursor-pointer hover:bg-blue-50/30 dark:hover:bg-slate-800/50',
                                Number(bulkForm.channel_id) === c.id ? 'border-2 border-sky-500 card-tint-sky' : 'border-slate-100 dark:border-slate-800']">
                  <input type="radio" v-model.number="bulkForm.channel_id" :value="c.id" class="hidden">
                  <i :class="[c.icon || 'fi-rr-circle', 'text-lg text-sky-700']"></i>
                  <span class="text-xs text-center">{{ c.name }}</span>
                </label>
              </div>
              <div v-if="bulkErrors.channel_id" class="text-[11px] text-red-600 mt-1">{{ bulkErrors.channel_id[0] }}</div>
            </div>

            <div v-if="bulkNeedsBank" class="card-tint-blue p-3 rounded-xl">
              <label class="block text-xs font-medium mb-2"><i class="fi-rr-bank"></i> ธนาคาร <span class="text-red-600">*</span></label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <label v-for="b in banks" :key="b.code"
                       :class="['flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer hover:bg-white/60 dark:hover:bg-slate-800/50',
                                bulkForm.sub_channel === b.code ? 'border-2 border-blue-600 bg-white dark:bg-slate-800' : 'border-blue-200 dark:border-slate-700 bg-white/40']">
                  <input type="radio" v-model="bulkForm.sub_channel" :value="b.code" class="text-blue-600">
                  <span class="text-sm font-medium">{{ b.name }}</span>
                </label>
              </div>
              <div v-if="bulkErrors.sub_channel" class="text-[11px] text-red-600 mt-2">{{ bulkErrors.sub_channel[0] }}</div>
            </div>

            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">
                หมายเหตุ
                <span v-if="bulkStatusObj?.requires_note" class="text-red-600">*</span>
                <span v-else class="text-slate-400">(ไม่บังคับ)</span>
              </label>
              <textarea v-model="bulkForm.note" rows="2" placeholder="ระบุรายละเอียดเพิ่มเติม..."
                class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500"></textarea>
              <div v-if="bulkErrors.note" class="text-[11px] text-red-600 mt-1">{{ bulkErrors.note[0] }}</div>
            </div>

            <div class="card-tint-orange text-xs p-3 flex items-start gap-2">
              <i class="fi-rr-triangle-warning mt-0.5"></i>
              <div>การกดยืนยันจะอัปเดตสถานะของ <strong>{{ selectedCount }}</strong> รายพร้อมกัน — ย้อนกลับไม่ได้ในขั้นเดียว</div>
            </div>

            <div class="flex gap-2 justify-end">
              <button type="button" @click="showBulkModal = false" class="btn-outline px-4 py-2.5 text-sm">ยกเลิก</button>
              <button type="submit" :disabled="bulkSaving || !bulkForm.status_code" class="btn-green px-4 py-2.5 text-sm flex items-center gap-1.5 disabled:opacity-50">
                <i :class="['fi-rr-check', bulkSaving && 'animate-spin']"></i> ยืนยันอัปเดต {{ selectedCount }} ราย
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

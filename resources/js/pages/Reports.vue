<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import { formatNumber, shortDate, statusShort } from '@/composables/useApi';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const dark = computed(() => theme.isDark);

const reportType = ref('daily');         // 'daily' | 'bottleneck'
const level = ref('village');            // amphur | tambon | village
const amphurOpts = ref([]);
const tambonOpts = ref([]);
const amphurId = ref('');
const tambonId = ref('');
const date = ref(new Date().toISOString().slice(0, 10));

const dailyRows = ref([]);
const bottleneck = ref(null);
const loading = ref(false);

const levelLabel = computed(() => ({ amphur: 'อำเภอ', tambon: 'ตำบล', village: 'หมู่บ้าน' }[level.value]));

const stats = computed(() => {
  const total = dailyRows.value.length;
  const excellent = dailyRows.value.filter(r => r.pct >= 80).length;
  const medium    = dailyRows.value.filter(r => r.pct >= 50 && r.pct < 80).length;
  const low       = dailyRows.value.filter(r => r.pct < 50).length;
  const sumTotal  = dailyRows.value.reduce((a, r) => a + r.total, 0);
  const sumDone   = dailyRows.value.reduce((a, r) => a + r.done, 0);
  const overallPct = sumTotal > 0 ? Math.round((sumDone / sumTotal) * 100) : 0;
  return { total, excellent, medium, low, sumTotal, sumDone, overallPct };
});

const chartOptions = computed(() => {
  const top = dailyRows.value.slice(0, 10);
  return {
    chart: { type: 'bar', height: 360, fontFamily: 'Prompt, sans-serif', toolbar: { show: false } },
    colors: ['#94a3b8', '#2563eb'],
    series: [
      { name: 'เป้าหมาย',      data: top.map(r => r.total) },
      { name: 'ลงทะเบียนแล้ว', data: top.map(r => r.done) },
    ],
    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '70%' } },
    dataLabels: { enabled: false },
    xaxis: { categories: top.map(r => r.name), axisBorder: { show: false }, axisTicks: { show: false } },
    grid: { borderColor: dark.value ? '#1f2937' : '#eef2f7', strokeDashArray: 3, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
    legend: { position: 'top', horizontalAlign: 'right' },
    tooltip: { theme: dark.value ? 'dark' : 'light' },
    theme: { mode: dark.value ? 'dark' : 'light' },
  };
});

async function loadTambons() {
  tambonId.value = '';
  if (!amphurId.value) { tambonOpts.value = []; return; }
  tambonOpts.value = (await axios.get('/api/ref/tambons', { params: { amphur_id: amphurId.value } })).data.data;
}

async function loadDaily() {
  loading.value = true;
  try {
    const params = { level: level.value, date: date.value };
    if (amphurId.value) params.amphur_id = amphurId.value;
    if (tambonId.value) params.tambon_id = tambonId.value;
    const { data } = await axios.get('/api/reports/daily-villages', { params });
    dailyRows.value = data.data;
  } finally { loading.value = false; }
}
async function loadBottleneck() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/reports/bottleneck');
    bottleneck.value = data;
  } finally { loading.value = false; }
}

async function load() {
  if (reportType.value === 'daily') await loadDaily();
  else await loadBottleneck();
}

function exportXlsx() {
  const params = new URLSearchParams({ level: level.value, date: date.value, format: 'xlsx' });
  if (amphurId.value) params.append('amphur_id', amphurId.value);
  if (tambonId.value) params.append('tambon_id', tambonId.value);
  window.location.href = '/api/reports/daily-villages/export?' + params.toString();
}

onMounted(async () => {
  amphurOpts.value = (await axios.get('/api/ref/amphurs')).data.data;
  await load();
});

watch(level, loadDaily);
watch(amphurId, async () => { await loadTambons(); await loadDaily(); });
watch(tambonId, loadDaily);

function levelClass(level) {
  if (level === 'ดีเยี่ยม') return 'card-tint-green text-green-700';
  if (level === 'ปานกลาง') return 'card-tint-orange text-orange-700';
  return 'card-tint-red text-red-700';
}
function pctClass(n) {
  if (n >= 80) return 'text-green-600';
  if (n >= 50) return 'text-orange-600';
  return 'text-red-600';
}
</script>

<template>
  <AppLayout title="รายงาน + Export" subtitle="สรุปยอด · Bottleneck · Excel">
    <div class="space-y-4">

      <!-- Hero -->
      <div class="card-hero p-5 lg:p-6 text-center">
        <div class="text-xs opacity-80">รายงาน{{ reportType === 'daily' ? 'ประจำวัน' : 'รายสัปดาห์' }}</div>
        <h2 class="text-base lg:text-lg font-semibold mt-1">
          {{ reportType === 'daily'
              ? `สรุปยอดการลงทะเบียนราย${levelLabel}`
              : 'วิเคราะห์ Bottleneck รายสัปดาห์' }}
        </h2>
        <div class="text-sm opacity-90 mt-0.5">
          {{ reportType === 'daily' ? new Date(date).toLocaleDateString('th-TH', { day: '2-digit', month: 'long', year: 'numeric' }) : 'จ.นครราชสีมา' }}
        </div>
      </div>

      <!-- Toolbar -->
      <div class="card p-3 space-y-2">
        <div class="flex flex-wrap gap-2 items-center">
          <select v-model="reportType" @change="load" class="w-full sm:w-auto min-w-0 px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <option value="daily">สรุปยอด (รายวัน)</option>
            <option value="bottleneck">วิเคราะห์ Bottleneck (รายสัปดาห์)</option>
          </select>
          <button v-if="reportType === 'daily'" @click="exportXlsx" class="sm:ml-auto btn-green px-3 py-2.5 text-sm flex items-center gap-1.5">
            <i class="fi-rr-file-excel"></i> Export Excel
          </button>
        </div>

        <!-- Level selector (รายอำเภอ / รายตำบล / รายหมู่บ้าน) -->
        <div v-if="reportType === 'daily'" class="flex flex-wrap gap-1.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl p-1">
          <button
            v-for="opt in [
              { v: 'amphur',  label: 'รายอำเภอ',  icon: 'fi-rr-marker' },
              { v: 'tambon',  label: 'รายตำบล',   icon: 'fi-rr-map-marker-home' },
              { v: 'village', label: 'รายหมู่บ้าน', icon: 'fi-rr-home' },
            ]"
            :key="opt.v"
            @click="level = opt.v"
            :class="['flex-1 sm:flex-none px-3 py-2 text-sm font-medium rounded-lg flex items-center justify-center gap-1.5 transition',
                     level === opt.v
                       ? 'bg-blue-700 text-white shadow-sm shadow-blue-500/30'
                       : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700/50']">
            <i :class="opt.icon"></i> {{ opt.label }}
          </button>
        </div>

        <div v-if="reportType === 'daily'" class="grid grid-cols-2 lg:grid-cols-3 gap-2">
          <input v-model="date" type="date" class="w-full min-w-0 px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
          <select v-model="amphurId" :disabled="level === 'amphur'" class="w-full min-w-0 px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm disabled:opacity-40">
            <option value="">ทุกอำเภอ</option>
            <option v-for="a in amphurOpts" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <select v-model="tambonId" :disabled="!amphurId || level !== 'village'" class="w-full min-w-0 px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm disabled:opacity-40">
            <option value="">ทุกตำบล</option>
            <option v-for="t in tambonOpts" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
      </div>

      <div v-if="loading" class="text-center py-8 text-slate-500"><i class="fi-rr-spinner animate-spin text-2xl"></i></div>

      <!-- DAILY -->
      <template v-else-if="reportType === 'daily'">
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
          <div class="card-tint-blue p-4 col-span-2 lg:col-span-1">
            <div class="text-xs opacity-80">รวมเป้าหมาย</div>
            <div class="text-2xl font-bold mt-1">{{ formatNumber(stats.sumTotal) }}</div>
            <div class="text-[11px] opacity-70">จำนวน {{ levelLabel }} {{ stats.total }} แห่ง</div>
          </div>
          <div class="card-tint-blue p-4">
            <div class="text-xs opacity-80">รวม %</div>
            <div class="text-2xl font-bold mt-1 text-blue-900 dark:text-blue-100">{{ stats.overallPct }}%</div>
          </div>
          <div class="card-tint-green p-4">
            <div class="text-xs opacity-80">บรรลุ ≥ 80%</div>
            <div class="text-2xl font-bold mt-1 text-green-700">{{ formatNumber(stats.excellent) }}</div>
          </div>
          <div class="card-tint-orange p-4">
            <div class="text-xs opacity-80">50-79%</div>
            <div class="text-2xl font-bold mt-1 text-orange-700">{{ formatNumber(stats.medium) }}</div>
          </div>
          <div class="card-tint-red p-4">
            <div class="text-xs opacity-80">ต่ำกว่า 50%</div>
            <div class="text-2xl font-bold mt-1 text-red-700">{{ formatNumber(stats.low) }}</div>
          </div>
        </div>

        <div v-if="dailyRows.length" class="card p-4 lg:p-5 min-w-0 overflow-hidden">
          <div class="font-semibold text-sm">10 {{ levelLabel }}ที่ก้าวหน้าสูงสุด</div>
          <div class="w-full overflow-hidden">
            <apexchart type="bar" height="360" :options="chartOptions" :series="chartOptions.series" />
          </div>
        </div>

        <div class="card overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
              <tr>
                <th class="text-left py-2 px-4">{{ levelLabel }}</th>
                <th v-if="level !== 'amphur'" class="text-left">{{ level === 'village' ? 'ตำบล / อำเภอ' : 'อำเภอ' }}</th>
                <th class="text-right">เป้า</th>
                <th class="text-right text-slate-500" title="ยังไม่ถูกติดตาม (ยังไม่มี record สถานะ)">ยังไม่ถูกติดตาม</th>
                <th class="text-right">{{ statusShort('4.7') }}</th>
                <th class="text-right">{{ statusShort('4.6') }}</th>
                <th class="text-right">รวม</th>
                <th class="text-right">%</th>
                <th class="text-left pl-4 pr-3">สถานะ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60">
              <tr v-for="r in dailyRows" :key="r.id">
                <td class="py-2 px-4 font-medium">{{ r.name }}</td>
                <td v-if="level === 'village'" class="text-xs text-slate-500 dark:text-slate-400">{{ r.tambon }} · {{ r.amphur }}</td>
                <td v-else-if="level === 'tambon'" class="text-xs text-slate-500 dark:text-slate-400">{{ r.amphur }}</td>
                <td class="text-right">{{ formatNumber(r.total) }}</td>
                <td class="text-right text-slate-500">{{ formatNumber(r.untracked || 0) }}</td>
                <td class="text-right">{{ formatNumber(r.kyc_done) }}</td>
                <td class="text-right">{{ formatNumber(r.kyc_waiting) }}</td>
                <td class="text-right font-medium">{{ formatNumber(r.done) }}</td>
                <td :class="['text-right font-semibold', pctClass(r.pct)]">{{ r.pct }}%</td>
                <td class="pl-4 pr-3"><span :class="['text-xs px-2 py-0.5 rounded-full font-medium whitespace-nowrap', levelClass(r.level)]">{{ r.level }}</span></td>
              </tr>
              <tr v-if="dailyRows.length === 0"><td :colspan="level === 'village' ? 9 : (level === 'tambon' ? 9 : 8)" class="py-6 text-center text-slate-500 text-sm">ไม่พบข้อมูลตามเงื่อนไข</td></tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- BOTTLENECK -->
      <template v-else-if="bottleneck">
        <div class="card-tint-orange p-4">
          <div class="text-xs opacity-80">สัปดาห์ที่ {{ bottleneck.week.week_num }}</div>
          <div class="text-sm font-medium mt-0.5">
            {{ shortDate(bottleneck.week.start) }} — {{ shortDate(bottleneck.week.end) }}
          </div>
        </div>

        <div class="card p-5">
          <div class="font-semibold mb-3"><i class="fi-sr-bell text-orange-500"></i> ขั้นตอนที่ติดขัด</div>
          <div v-for="s in bottleneck.stuck_stages" :key="s.status_code" class="card-tint-red p-3 mb-2 flex items-center justify-between">
            <div>
              <div class="font-medium text-red-700 dark:text-red-300">ค้างที่ขั้น {{ statusShort(s.status_code) }}</div>
              <div class="text-xs opacity-80">{{ formatNumber(s.count) }} ราย · เฉลี่ย {{ s.avg_days }} วัน</div>
            </div>
            <span class="text-red-600 font-bold">{{ s.avg_days }} วัน</span>
          </div>
          <div v-if="!bottleneck.stuck_stages.length" class="text-sm text-slate-500">ไม่มี Bottleneck — ยอดเยี่ยม 🎉</div>
        </div>

        <div class="card p-5">
          <div class="font-semibold mb-3"><i class="fi-rr-marker text-orange-500"></i> อำเภอที่ลงทะเบียนต่ำกว่าเป้า</div>
          <div v-for="a in bottleneck.lagging_amphurs" :key="a.id" class="card-tint-orange p-3 mb-2 flex items-center justify-between">
            <div>
              <div class="font-medium">อ.{{ a.name }}</div>
              <div class="text-xs opacity-80">เป้า {{ formatNumber(a.total) }} · ทำได้ {{ formatNumber(a.done) }}</div>
            </div>
            <span class="text-orange-700 font-bold">{{ a.pct }}%</span>
          </div>
          <div v-if="!bottleneck.lagging_amphurs.length" class="text-sm text-slate-500">ทุกอำเภอใกล้/เกินเป้าแล้ว</div>
        </div>

        <div class="card p-5">
          <div class="font-semibold mb-3"><i class="fi-rr-user-headset text-orange-500"></i> ผู้ติดตามที่ไม่อัปเดตเกิน 3 วัน</div>
          <div v-if="!bottleneck.inactive_trackers.length" class="text-sm text-slate-500">ทุกคนอัปเดตเป็นประจำ</div>
          <div v-for="t in bottleneck.inactive_trackers" :key="t.id" class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-800 last:border-0">
            <div>
              <div class="font-medium text-sm">{{ t.name }} <span class="text-xs text-slate-500">({{ t.position }})</span></div>
              <div class="text-xs text-slate-500 dark:text-slate-400">{{ t.village }}</div>
            </div>
          </div>
        </div>
      </template>

    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue';
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
const dailySummary = ref(null);
const dailyTotal = ref(0);
const dailyPage = ref(1);
const dailyLastPage = ref(1);
const perPage = 35;
const bottleneck = ref(null);
const loading = ref(false);

// Export dropdown
const showExport = ref(false);
const exportRef = ref(null);

const levelLabel = computed(() => ({ amphur: 'อำเภอ', tambon: 'ตำบล', village: 'หมู่บ้าน' }[level.value]));

function formatThaiDate(d) {
  if (!d) return '';
  const dt = new Date(d);
  if (isNaN(dt.getTime())) return '';
  return dt.toLocaleDateString('th-TH', { day: '2-digit', month: 'long', year: 'numeric' });
}

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

async function loadDaily(page = 1) {
  loading.value = true;
  try {
    const params = { level: level.value, date: date.value, page, per_page: perPage };
    if (amphurId.value) params.amphur_id = amphurId.value;
    if (tambonId.value) params.tambon_id = tambonId.value;
    const { data } = await axios.get('/api/reports/daily-villages', { params });
    dailyRows.value     = data.data;
    dailySummary.value  = data.summary;
    dailyTotal.value    = data.total;
    dailyPage.value     = data.current_page;
    dailyLastPage.value = data.last_page;
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

// Export options
const exportOptions = [
  { key: 'summary',        label: 'สรุปยอดการลงทะเบียนทุกสถานะ', icon: 'fi-rr-chart-pie',     desc: 'รายอำเภอ/ตำบล/หมู่บ้าน + ทุกสถานะ 4.1-4.7' },
  { key: 'targets-raw',    label: 'รายชื่อเป้าหมายต้นฉบับ',     icon: 'fi-rr-users-alt',     desc: 'ข้อมูลตามที่ import เข้าระบบ' },
  { key: 'targets-status', label: 'รายชื่อเป้าหมาย + สถานะ',     icon: 'fi-rr-id-badge',      desc: 'พร้อมสถานะปัจจุบัน · ช่องทาง · ผู้อัปเดต' },
  { key: 'trackers',       label: 'รายชื่อผู้กำกับติดตาม',       icon: 'fi-rr-user-headset',  desc: 'ครบทุกหมู่บ้าน + สถานะบัญชี' },
  { key: 'chart',          label: 'ภาพกราฟแสดงผล',             icon: 'fi-rr-picture',       desc: 'ดาวน์โหลด chart Top 10 เป็น PNG' },
];

function doExport(key) {
  showExport.value = false;
  if (key === 'chart') return downloadChartPng();

  const params = new URLSearchParams();
  if (amphurId.value) params.append('amphur_id', amphurId.value);
  if (tambonId.value) params.append('tambon_id', tambonId.value);

  const urls = {
    'summary':        '/api/reports/daily-villages/export?level=' + level.value + '&',
    'targets-raw':    '/api/reports/export/targets-raw?',
    'targets-status': '/api/reports/export/targets-status?',
    'trackers':       '/api/reports/export/trackers?',
  };
  window.location.href = urls[key] + params.toString();
}

function downloadChartPng() {
  // Apex chart มี API `dataURI()` — ดาวน์โหลด chart Top 10 เป็น PNG
  const el = document.querySelector('.apexcharts-canvas');
  if (!el) { alert('ไม่พบกราฟ — กรุณาโหลดข้อมูลก่อน'); return; }
  // ใช้ chart instance ผ่าน window.Apex หรือ direct render
  if (window.ApexCharts && el.querySelector('svg')) {
    // วาด SVG เป็น canvas → PNG
    const svg = el.querySelector('svg');
    const data = new XMLSerializer().serializeToString(svg);
    const blob = new Blob([data], { type: 'image/svg+xml' });
    const url = URL.createObjectURL(blob);
    const img = new Image();
    img.onload = () => {
      const canvas = document.createElement('canvas');
      canvas.width = img.width; canvas.height = img.height;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0);
      const a = document.createElement('a');
      a.download = 'รายงาน_chart_' + new Date().toISOString().slice(0,10) + '.png';
      a.href = canvas.toDataURL('image/png');
      a.click();
      URL.revokeObjectURL(url);
    };
    img.src = url;
  }
}

function onClickOutside(e) {
  if (showExport.value && exportRef.value && !exportRef.value.contains(e.target)) {
    showExport.value = false;
  }
}

onMounted(async () => {
  amphurOpts.value = (await axios.get('/api/ref/amphurs')).data.data;
  await load();
  document.addEventListener('click', onClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside);
});

watch(level, () => loadDaily(1));
watch(amphurId, async () => { await loadTambons(); await loadDaily(1); });
watch(tambonId, () => loadDaily(1));

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
          <span v-if="reportType === 'daily' && date">{{ formatThaiDate(date) }}</span>
          <span v-else>จ.นครราชสีมา</span>
        </div>
      </div>

      <!-- Toolbar -->
      <div class="card p-3 space-y-2">
        <div class="flex flex-wrap gap-2 items-center">
          <!-- Report type tabs (อยู่ในแถวเดียว) -->
          <div class="flex bg-slate-100 dark:bg-slate-800/60 rounded-xl p-0.5 shrink-0">
            <button @click="reportType = 'daily'; load()"
                    :class="['px-3 py-2 rounded-lg text-xs sm:text-sm font-medium transition flex items-center gap-1.5',
                             reportType === 'daily'
                               ? 'bg-white dark:bg-slate-900 shadow-sm text-blue-700 dark:text-blue-300'
                               : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']">
              <i class="fi-rr-chart-pie"></i> <span>สรุปยอด (รายวัน)</span>
            </button>
            <button @click="reportType = 'bottleneck'; load()"
                    :class="['px-3 py-2 rounded-lg text-xs sm:text-sm font-medium transition flex items-center gap-1.5',
                             reportType === 'bottleneck'
                               ? 'bg-white dark:bg-slate-900 shadow-sm text-blue-700 dark:text-blue-300'
                               : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']">
              <i class="fi-rr-triangle-warning"></i> <span>Bottleneck (สัปดาห์)</span>
            </button>
          </div>
          <div v-if="reportType === 'daily'" ref="exportRef" class="sm:ml-auto relative">
            <button @click="showExport = !showExport"
                    class="btn-green px-3 py-2.5 text-sm flex items-center gap-1.5 whitespace-nowrap">
              <i class="fi-rr-file-export"></i> ส่งออกรายงาน
              <i :class="showExport ? 'fi-rr-angle-small-up' : 'fi-rr-angle-small-down'" class="text-xs"></i>
            </button>
            <div v-if="showExport"
                 class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] card shadow-2xl shadow-slate-300/40 dark:shadow-black/40 overflow-hidden z-30">
              <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-800 text-xs font-semibold text-slate-500">
                เลือกหัวข้อรายงาน
              </div>
              <button v-for="o in exportOptions" :key="o.key" @click="doExport(o.key)"
                      class="w-full text-left px-4 py-3 hover:bg-blue-50 dark:hover:bg-slate-800/60 border-b border-slate-50 dark:border-slate-800/60 flex items-start gap-3">
                <div class="w-9 h-9 shrink-0 rounded-xl card-tint-blue flex items-center justify-center text-blue-700">
                  <i :class="o.icon"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium">{{ o.label }}</div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ o.desc }}</div>
                </div>
              </button>
            </div>
          </div>
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
          <div class="relative min-w-0">
            <input v-model="date" type="date" class="w-full min-w-0 pl-3 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <button v-if="date" @click="date = ''" title="ล้าง"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>
          <div class="relative min-w-0">
            <select v-model="amphurId" :disabled="level === 'amphur'" class="w-full min-w-0 pl-3 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm disabled:opacity-40">
              <option value="">ทุกอำเภอ</option>
              <option v-for="a in amphurOpts" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
            <button v-if="amphurId && level !== 'amphur'" @click="amphurId = ''" title="ล้าง"
                    class="absolute right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>
          <div class="relative min-w-0">
            <select v-model="tambonId" :disabled="!amphurId || level !== 'village'" class="w-full min-w-0 pl-3 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm disabled:opacity-40">
              <option value="">ทุกตำบล</option>
              <option v-for="t in tambonOpts" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
            <button v-if="tambonId" @click="tambonId = ''" title="ล้าง"
                    class="absolute right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>
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
                <th class="text-left py-2 px-3 sticky left-0 bg-white dark:bg-slate-900 z-10">{{ levelLabel }}</th>
                <th v-if="level !== 'amphur'" class="text-left whitespace-nowrap">{{ level === 'village' ? 'ตำบล / อำเภอ' : 'อำเภอ' }}</th>
                <th class="text-right whitespace-nowrap px-2">เป้า</th>
                <th class="text-right whitespace-nowrap px-2 text-slate-500" title="ยังไม่ถูกติดตาม">ยังไม่ติดตาม</th>
                <th class="text-right whitespace-nowrap px-2" title="4.1">ไม่ประสงค์</th>
                <th class="text-right whitespace-nowrap px-2" title="4.2">ลงทะเบียน</th>
                <th class="text-right whitespace-nowrap px-2" title="4.3">เตรียมเอกสาร</th>
                <th class="text-right whitespace-nowrap px-2" title="4.4">ส่งเอกสารเพิ่ม</th>
                <th class="text-right whitespace-nowrap px-2" title="4.5">รออุทธรณ์</th>
                <th class="text-right whitespace-nowrap px-2" title="4.6">รอยืนยันตัวตน</th>
                <th class="text-right whitespace-nowrap px-2" title="4.7">ใช้สิทธิแล้ว</th>
                <th class="text-right whitespace-nowrap px-2">รวมลงทะเบียน</th>
                <th class="text-right whitespace-nowrap px-2">%</th>
                <th class="text-left whitespace-nowrap pl-4 pr-3">สถานะ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60">
              <tr v-for="r in dailyRows" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                <td class="py-2 px-3 font-medium sticky left-0 bg-white dark:bg-slate-900">{{ r.name }}</td>
                <td v-if="level === 'village'" class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ r.tambon }} · {{ r.amphur }}</td>
                <td v-else-if="level === 'tambon'" class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ r.amphur }}</td>
                <td class="text-right px-2">{{ formatNumber(r.total) }}</td>
                <td class="text-right px-2 text-slate-500">{{ formatNumber(r.untracked || 0) }}</td>
                <td class="text-right px-2 text-slate-500">{{ formatNumber(r.s_41 || 0) }}</td>
                <td class="text-right px-2">{{ formatNumber(r.s_42 || 0) }}</td>
                <td class="text-right px-2">{{ formatNumber(r.s_43 || 0) }}</td>
                <td class="text-right px-2">{{ formatNumber(r.s_44 || 0) }}</td>
                <td class="text-right px-2 text-red-600">{{ formatNumber(r.s_45 || 0) }}</td>
                <td class="text-right px-2">{{ formatNumber(r.s_46 || 0) }}</td>
                <td class="text-right px-2 text-green-700">{{ formatNumber(r.s_47 || 0) }}</td>
                <td class="text-right px-2 font-medium">{{ formatNumber(r.done) }}</td>
                <td :class="['text-right px-2 font-semibold whitespace-nowrap', pctClass(r.pct)]">{{ r.pct }}%</td>
                <td class="pl-4 pr-3"><span :class="['text-xs px-2 py-0.5 rounded-full font-medium whitespace-nowrap', levelClass(r.level)]">{{ r.level }}</span></td>
              </tr>
              <tr v-if="dailyRows.length === 0">
                <td :colspan="15" class="py-6 text-center text-slate-500 text-sm">ไม่พบข้อมูลตามเงื่อนไข</td>
              </tr>
            </tbody>
            <!-- Summary row -->
            <tfoot v-if="dailySummary && dailyRows.length" class="bg-blue-50 dark:bg-blue-900/20 border-t-2 border-blue-200 dark:border-blue-800">
              <tr class="text-sm font-bold">
                <td class="py-3 px-3 sticky left-0 bg-blue-50 dark:bg-blue-900/30 text-blue-900 dark:text-blue-100">
                  รวม ({{ formatNumber(dailyTotal) }} {{ levelLabel }})
                </td>
                <td v-if="level !== 'amphur'"></td>
                <td class="text-right px-2">{{ formatNumber(dailySummary.total) }}</td>
                <td class="text-right px-2 text-slate-600">{{ formatNumber(dailySummary.untracked) }}</td>
                <td class="text-right px-2 text-slate-600">{{ formatNumber(dailySummary.s_41) }}</td>
                <td class="text-right px-2">{{ formatNumber(dailySummary.s_42) }}</td>
                <td class="text-right px-2">{{ formatNumber(dailySummary.s_43) }}</td>
                <td class="text-right px-2">{{ formatNumber(dailySummary.s_44) }}</td>
                <td class="text-right px-2 text-red-700">{{ formatNumber(dailySummary.s_45) }}</td>
                <td class="text-right px-2">{{ formatNumber(dailySummary.s_46) }}</td>
                <td class="text-right px-2 text-green-700">{{ formatNumber(dailySummary.s_47) }}</td>
                <td class="text-right px-2">{{ formatNumber(dailySummary.done) }}</td>
                <td :class="['text-right px-2', pctClass(dailySummary.pct)]">{{ dailySummary.pct }}%</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="dailyLastPage > 1" class="flex items-center justify-between gap-2 text-sm">
          <div class="text-xs text-slate-500 dark:text-slate-400">
            หน้า {{ dailyPage }} / {{ dailyLastPage }} · ทั้งหมด {{ formatNumber(dailyTotal) }} {{ levelLabel }} · {{ perPage }} แถว/หน้า
          </div>
          <div class="flex gap-1">
            <button @click="loadDaily(dailyPage - 1)" :disabled="dailyPage <= 1"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 dark:hover:bg-slate-800">
              <i class="fi-rr-angle-small-left"></i> ก่อนหน้า
            </button>
            <button @click="loadDaily(dailyPage + 1)" :disabled="dailyPage >= dailyLastPage"
                    class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 dark:hover:bg-slate-800">
              ถัดไป <i class="fi-rr-angle-small-right"></i>
            </button>
          </div>
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

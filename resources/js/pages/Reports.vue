<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import Loader from '@/components/Loader.vue';
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue';
import axios from 'axios';
import { formatNumber, shortDate, statusShort, STATUS_SHORT, loadStatuses } from '@/composables/useApi';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const dark = computed(() => theme.isDark);

const reportType = ref('daily');         // 'daily' | 'bottleneck'
const level = ref('amphur');             // default = รายอำเภอ (amphur | tambon | village)
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

// Report topic tab (5 ชนิดรายงาน) — แทน dropdown export เดิม
// 'summary'        = สรุปยอดทุกสถานะ (default)
// 'targets-raw'    = รายชื่อเป้าหมายต้นฉบับ
// 'targets-status' = รายชื่อ + สถานะปัจจุบัน
// 'trackers'       = รายชื่อผู้กำกับติดตาม
// 'chart'          = ภาพกราฟแสดงผล
const topic = ref('summary');

// Data for each tab
const targetsList = ref({ data: [], total: 0, current_page: 1, last_page: 1 });
const trackersList = ref({ data: [], total: 0 });
const tabLoading = ref(false);

const levelLabel = computed(() => ({ amphur: 'อำเภอ', tambon: 'ตำบล', village: 'หมู่บ้าน' }[level.value]));

// ─── สถานะที่จะแสดงเป็นคอลัมน์ในตาราง — มาจาก /api/ref/statuses ผ่าน STATUS_SHORT ───
// ❗ ไม่ hardcode 4.1-4.7 — admin ลบ/เพิ่มสถานะ → table columns ปรับตาม
const statusCols = computed(() => {
  return Object.keys(STATUS_SHORT).sort().map(code => ({
    code,
    label: STATUS_SHORT[code],
    field: 's_' + code.replace('.', ''),     // s_41, s_42, s_44, ...
    isFail: code === '4.5',                   // อุทธรณ์ผล → แสดงสีแดง
    isDone: code === '4.7',                   // ใช้สิทธิ → แสดงสีเขียว
  }));
});
// colspan ของแถวเปล่า: 4 fixed (ระดับ, [อำเภอ ถ้าไม่ใช่ amphur], เป้า, ยังไม่ติดตาม) + statusCols + 3 (รวม, %, สถานะ)
const emptyColspan = computed(() => 4 + (level.value !== 'amphur' ? 1 : 0) + statusCols.value.length + 3 - 1);

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

// 4 หัวข้อรายงาน — เป็น tab + meta สำหรับแสดงผลและส่งออก
const topicTabs = [
  { key: 'summary',        label: 'สรุปยอดทุกสถานะ',  icon: 'fi-rr-chart-pie' },
  { key: 'targets-raw',    label: 'รายชื่อต้นฉบับ',      icon: 'fi-rr-users-alt' },
  { key: 'targets-status', label: 'รายชื่อ + สถานะ',     icon: 'fi-rr-id-badge' },
  { key: 'trackers',       label: 'ผู้กำกับติดตาม',     icon: 'fi-rr-user-headset' },
  { key: 'missed',         label: 'ผู้ตกหล่น',          icon: 'fi-rr-search-heart' },
];

// โหลดข้อมูลตาม tab ที่เลือก
async function loadTabData(page = 1) {
  if (topic.value === 'summary') {
    await loadDaily(page);
    return;
  }
  tabLoading.value = true;
  try {
    const params = { per_page: 35, page };
    if (amphurId.value) params.amphur_id = amphurId.value;
    if (tambonId.value) params.tambon_id = tambonId.value;

    if (topic.value === 'targets-raw' || topic.value === 'targets-status') {
      const { data } = await axios.get('/api/targets', { params });
      targetsList.value = data;
    } else if (topic.value === 'trackers') {
      const { data } = await axios.get('/api/trackers', { params });
      trackersList.value = data;
    } else if (topic.value === 'missed') {
      const { data } = await axios.get('/api/missed-targets', { params: { level: 'amphur' } });
      missedRows.value = data.data;
    }
  } finally { tabLoading.value = false; }
}

// Page change handlers สำหรับ Pagination component
function loadTargetsPage(page) { loadTabData(page); }

// ส่งออกตาม tab ปัจจุบัน — export ทุกแถว ไม่จำกัด page
function exportCurrent() {
  // ผู้ตกหล่น — export แยกต่างหาก (ตาม level)
  if (topic.value === 'missed') {
    window.location.href = '/api/missed-targets/export?level=amphur';
    return;
  }
  const params = new URLSearchParams();
  if (amphurId.value) params.append('amphur_id', amphurId.value);
  if (tambonId.value) params.append('tambon_id', tambonId.value);

  const urls = {
    'summary':        '/api/reports/daily-villages/export?level=' + level.value + '&',
    'targets-raw':    '/api/reports/export/targets-raw?',
    'targets-status': '/api/reports/export/targets-status?',
    'trackers':       '/api/reports/export/trackers?',
  };
  window.location.href = urls[topic.value] + params.toString();
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

function onClickOutside() {} // placeholder — ไม่มี dropdown แล้ว แต่เก็บไว้ backward compat

// ─── กลุ่มเป้าหมายผู้ตกหล่น (tab "ผู้ตกหล่น") ───
const missedRows = ref([]);
const missedTotals = computed(() => missedRows.value.reduce((a, r) => ({
  vuln:  a.vuln  + (r.cnt_vulnerable || 0),
  jpt:   a.jpt   + (r.cnt_jpt || 0),
  both:  a.both  + (r.cnt_both || 0),
  total: a.total + (r.cnt_total || 0),
}), { vuln: 0, jpt: 0, both: 0, total: 0 }));

onMounted(async () => {
  loadStatuses();   // โหลดชื่อสถานะ dynamic — column ตารางขึ้นกับสิ่งนี้
  amphurOpts.value = (await axios.get('/api/ref/amphurs')).data.data;
  await load();
  document.addEventListener('click', onClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickOutside);
});

watch(level, () => loadDaily(1));
watch(amphurId, async () => { await loadTambons(); await loadTabData(); });
watch(tambonId, () => loadTabData());
watch(topic, () => loadTabData());

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

      <!-- Toolbar — mobile stack เรียบร้อย / desktop แถวเดียว -->
      <div class="card p-3 space-y-2">
        <div class="flex flex-col lg:flex-row lg:flex-wrap gap-2 lg:items-center">
          <!-- Report type tabs -->
          <div class="flex bg-slate-100 dark:bg-slate-800/60 rounded-xl p-0.5 shrink-0">
            <button @click="reportType = 'daily'; load()"
                    :class="['flex-1 lg:flex-none px-3 py-2 rounded-lg text-sm font-medium transition flex items-center justify-center gap-1.5 tap-transparent min-h-[36px]',
                             reportType === 'daily'
                               ? 'bg-white dark:bg-slate-900 shadow-sm text-blue-700 dark:text-blue-300'
                               : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']">
              <i class="fi-rr-chart-pie"></i> <span>สรุปยอด (รายวัน)</span>
            </button>
            <button @click="reportType = 'bottleneck'; load()"
                    :class="['flex-1 lg:flex-none px-3 py-2 rounded-lg text-sm font-medium transition flex items-center justify-center gap-1.5 tap-transparent min-h-[36px]',
                             reportType === 'bottleneck'
                               ? 'bg-white dark:bg-slate-900 shadow-sm text-blue-700 dark:text-blue-300'
                               : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']">
              <i class="fi-rr-triangle-warning"></i> <span>Bottleneck (สัปดาห์)</span>
            </button>
          </div>
          <!-- เส้นกั้น แยก daily/bottleneck กับ topic tabs (desktop เท่านั้น) -->
          <div v-if="reportType === 'daily'" class="hidden lg:block w-px h-8 bg-slate-200 dark:bg-slate-700 mx-1"></div>

          <!-- Topic tabs · scrollable แนวนอนบน mobile · fade 2 ฝั่ง -->
          <div v-if="reportType === 'daily'" class="flex bg-slate-100 dark:bg-slate-800/60 rounded-xl p-0.5 overflow-x-auto fade-x">
            <button v-for="t in topicTabs" :key="t.key" @click="topic = t.key"
                    :class="['shrink-0 whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1.5 tap-transparent min-h-[36px]',
                             topic === t.key
                               ? 'bg-white dark:bg-slate-900 shadow-sm text-blue-700 dark:text-blue-300'
                               : 'text-slate-600 dark:text-slate-400 hover:text-slate-900']">
              <i :class="t.icon"></i> <span>{{ t.label }}</span>
            </button>
          </div>

          <!-- Export button — full-width บน mobile · ลอยขวาบน desktop -->
          <button v-if="reportType === 'daily'" @click="exportCurrent"
                  class="lg:ml-auto btn-green px-4 py-2.5 text-sm flex items-center justify-center gap-1.5 whitespace-nowrap tap-transparent">
            <i class="fi-rr-file-export"></i> ส่งออก Excel
          </button>
        </div>

        <!-- Level selector — แสดงเฉพาะ tab สรุปยอด ที่ใช้ level -->
        <div v-if="reportType === 'daily' && topic === 'summary'" class="flex flex-wrap gap-1.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl p-1">
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

        <div v-if="reportType === 'daily' && topic !== 'missed'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
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

      <Loader v-if="loading" label="กำลังโหลดรายงาน..." py="py-10" />

      <!-- DAILY -->
      <template v-else-if="reportType === 'daily'">

        <!-- ╔══════════════════════════════════════╗ -->
        <!-- ║ Tab: สรุปยอดทุกสถานะ (summary)         ║ -->
        <!-- ╚══════════════════════════════════════╝ -->
        <div v-if="topic === 'summary'" class="space-y-4">
        <!-- KPI cards · 2 cols xs / 5 cols lg · ไม่ใช้ col-span เพื่อสมดุล -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3">
          <div class="card-tint-blue p-3 sm:p-4">
            <div class="text-xs opacity-80">รวมเป้าหมาย</div>
            <div class="text-xl sm:text-2xl font-bold mt-1 tabular-nums">{{ formatNumber(stats.sumTotal) }}</div>
            <div class="text-[11px] opacity-70 truncate">{{ levelLabel }} {{ stats.total }} แห่ง</div>
          </div>
          <div class="card-tint-blue p-3 sm:p-4">
            <div class="text-xs opacity-80">รวม %</div>
            <div class="text-xl sm:text-2xl font-bold mt-1 tabular-nums text-blue-900 dark:text-blue-100">{{ stats.overallPct }}%</div>
          </div>
          <div class="card-tint-green p-3 sm:p-4">
            <div class="text-xs opacity-80">บรรลุ ≥ 80%</div>
            <div class="text-xl sm:text-2xl font-bold mt-1 tabular-nums text-green-700">{{ formatNumber(stats.excellent) }}</div>
          </div>
          <div class="card-tint-orange p-3 sm:p-4">
            <div class="text-xs opacity-80">50-79%</div>
            <div class="text-xl sm:text-2xl font-bold mt-1 tabular-nums text-orange-700">{{ formatNumber(stats.medium) }}</div>
          </div>
          <div class="card-tint-red p-3 sm:p-4">
            <div class="text-xs opacity-80">ต่ำกว่า 50%</div>
            <div class="text-xl sm:text-2xl font-bold mt-1 tabular-nums text-red-700">{{ formatNumber(stats.low) }}</div>
          </div>
        </div>

        <div v-if="dailyRows.length" class="card p-4 lg:p-5 min-w-0 overflow-hidden">
          <div class="font-semibold text-sm">10 {{ levelLabel }}ที่ก้าวหน้าสูงสุด</div>
          <div class="w-full overflow-hidden">
            <apexchart type="bar" height="360" :options="chartOptions" :series="chartOptions.series" />
          </div>
        </div>

        <!-- Hint: บนมือถือเลื่อนซ้าย-ขวาเพื่อดูคอลัมน์อื่น -->
        <div class="lg:hidden flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 px-1">
          <i class="fi-rr-arrows-h text-blue-500"></i>
          <span>เลื่อนซ้าย-ขวาเพื่อดูคอลัมน์อื่น</span>
        </div>
        <div class="card overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
              <tr>
                <th class="text-left py-2 px-3 sticky left-0 bg-white dark:bg-slate-900 z-10 sticky-col-shadow">{{ levelLabel }}</th>
                <th v-if="level !== 'amphur'" class="text-left whitespace-nowrap">{{ level === 'village' ? 'ตำบล / อำเภอ' : 'อำเภอ' }}</th>
                <th class="text-right whitespace-nowrap px-2">เป้า</th>
                <th class="text-right whitespace-nowrap px-2 text-slate-500" title="ยังไม่ถูกติดตาม">ยังไม่ติดตาม</th>
                <th v-for="s in statusCols" :key="s.code"
                    class="text-right whitespace-nowrap px-2" :title="s.code">
                  {{ s.label }}
                </th>
                <th class="text-right whitespace-nowrap px-2">รวมลงทะเบียน</th>
                <th class="text-right whitespace-nowrap px-2">%</th>
                <th class="text-left whitespace-nowrap pl-4 pr-3">สถานะ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60">
              <tr v-for="r in dailyRows" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                <td class="py-2 px-3 font-medium sticky left-0 bg-white dark:bg-slate-900 sticky-col-shadow">{{ r.name }}</td>
                <td v-if="level === 'village'" class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ r.tambon }} · {{ r.amphur }}</td>
                <td v-else-if="level === 'tambon'" class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ r.amphur }}</td>
                <td class="text-right px-2">{{ formatNumber(r.total) }}</td>
                <td class="text-right px-2 text-slate-500">{{ formatNumber(r.untracked || 0) }}</td>
                <td v-for="s in statusCols" :key="s.code"
                    :class="['text-right px-2', s.code === '4.1' ? 'text-slate-500' : '', s.isFail ? 'text-red-600' : '', s.isDone ? 'text-green-700' : '']">
                  {{ formatNumber(r[s.field] || 0) }}
                </td>
                <td class="text-right px-2 font-medium">{{ formatNumber(r.done) }}</td>
                <td :class="['text-right px-2 font-semibold whitespace-nowrap', pctClass(r.pct)]">{{ r.pct }}%</td>
                <td class="pl-4 pr-3"><span :class="['text-xs px-2 py-0.5 rounded-full font-medium whitespace-nowrap', levelClass(r.level)]">{{ r.level }}</span></td>
              </tr>
              <tr v-if="dailyRows.length === 0">
                <td :colspan="emptyColspan" class="py-6 text-center text-slate-500 text-sm">ไม่พบข้อมูลตามเงื่อนไข</td>
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
                <td v-for="s in statusCols" :key="s.code"
                    :class="['text-right px-2', s.code === '4.1' ? 'text-slate-600' : '', s.isFail ? 'text-red-700' : '', s.isDone ? 'text-green-700' : '']">
                  {{ formatNumber(dailySummary[s.field] || 0) }}
                </td>
                <td class="text-right px-2">{{ formatNumber(dailySummary.done) }}</td>
                <td :class="['text-right px-2', pctClass(dailySummary.pct)]">{{ dailySummary.pct }}%</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Pagination (numbered 1 2 ... N) -->
        <Pagination v-if="dailyLastPage > 1"
                    :current="dailyPage" :last="dailyLastPage" :total="dailyTotal"
                    :unit="levelLabel"
                    @go="loadDaily" />
        </div>
        <!-- ╔══════════════════════════════════════╗ -->
        <!-- ║ Tab: รายชื่อต้นฉบับ / +สถานะ           ║ -->
        <!-- ╚══════════════════════════════════════╝ -->
        <div v-else-if="topic === 'targets-raw' || topic === 'targets-status'">
          <Loader v-if="tabLoading" label="" py="py-8" :size="40" />
          <div v-else class="card overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="text-xs text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                <tr>
                  <th class="text-left py-2 px-3">#</th>
                  <th class="text-left">ชื่อ-สกุล</th>
                  <th class="text-left">หมู่บ้าน</th>
                  <th class="text-left">ตำบล / อำเภอ</th>
                  <th v-if="topic === 'targets-raw'" class="text-right">รายได้/ปี</th>
                  <th v-if="topic === 'targets-raw'" class="text-center">ปี</th>
                  <th v-if="topic === 'targets-raw'" class="text-center">เคยได้รับบัตร</th>
                  <th v-if="topic === 'targets-status'" class="text-center">สถานะ</th>
                  <th v-if="topic === 'targets-status'" class="text-left">ช่องทาง</th>
                  <th v-if="topic === 'targets-status'" class="text-left">อัปเดต</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60">
                <tr v-for="(t, i) in targetsList.data" :key="t.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                  <td class="py-2 px-3 text-slate-500">{{ (targetsList.current_page - 1) * 35 + i + 1 }}</td>
                  <td class="py-2 font-medium">{{ t.name }}</td>
                  <td class="text-xs">{{ t.village }}{{ t.moo ? ' ม.'+t.moo : '' }}</td>
                  <td class="text-xs text-slate-500">{{ t.tambon }} · {{ t.amphur }}</td>
                  <td v-if="topic === 'targets-raw'" class="text-right text-xs">{{ formatNumber(t.annual_income || 0) }}</td>
                  <td v-if="topic === 'targets-raw'" class="text-center text-xs">{{ t.year || '—' }}</td>
                  <td v-if="topic === 'targets-raw'" class="text-center text-xs">
                    <span v-if="t.has_old_welfare" class="text-blue-700">✓</span>
                    <span v-else class="text-slate-300">—</span>
                  </td>
                  <td v-if="topic === 'targets-status'" class="text-center">
                    <span v-if="t.status_code"
                          :class="['text-xs px-2 py-0.5 rounded-full font-medium whitespace-nowrap', `card-tint-${t.status_code === '4.7' ? 'green' : (t.status_code === '4.5' ? 'red' : 'blue')}`]">
                      {{ t.status_code }} {{ statusShort(t.status_code) }}
                    </span>
                    <span v-else class="text-xs text-slate-400">ยังไม่ถูกติดตาม</span>
                  </td>
                  <td v-if="topic === 'targets-status'" class="text-xs">{{ t.channel || '—' }}</td>
                  <td v-if="topic === 'targets-status'" class="text-xs text-slate-500">{{ t.updated_at ? shortDate(t.updated_at) : '—' }}</td>
                </tr>
                <tr v-if="!targetsList.data.length">
                  <td :colspan="topic === 'targets-raw' ? 7 : 7" class="py-6 text-center text-slate-500">ไม่พบรายชื่อ</td>
                </tr>
              </tbody>
            </table>
          </div>
          <Pagination v-if="targetsList.last_page > 1"
                      :current="targetsList.current_page" :last="targetsList.last_page" :total="targetsList.total"
                      unit="คน"
                      @go="loadTargetsPage" />
        </div>

        <!-- ╔══════════════════════════════════════╗ -->
        <!-- ║ Tab: ผู้กำกับติดตาม                     ║ -->
        <!-- ╚══════════════════════════════════════╝ -->
        <div v-else-if="topic === 'trackers'">
          <Loader v-if="tabLoading" label="" py="py-8" :size="40" />
          <div v-else class="card overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="text-xs text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                <tr>
                  <th class="text-left py-2 px-3">#</th>
                  <th class="text-left">ชื่อ-สกุล</th>
                  <th class="text-left">ตำแหน่ง</th>
                  <th class="text-left">เบอร์โทร</th>
                  <th class="text-left">หมู่บ้าน</th>
                  <th class="text-left">ตำบล / อำเภอ</th>
                  <th class="text-center">บัญชี Login</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60">
                <tr v-for="(t, i) in trackersList.data" :key="t.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                  <td class="py-2 px-3 text-slate-500">{{ i + 1 }}</td>
                  <td class="py-2 font-medium">{{ t.full_name }}</td>
                  <td class="text-xs">{{ t.position }}{{ t.position_other ? ' ('+t.position_other+')' : '' }}</td>
                  <td class="text-xs">
                    <a v-if="t.phone" :href="`tel:${t.phone}`" class="text-blue-700 hover:underline">{{ t.phone }}</a>
                    <span v-else class="text-slate-400">—</span>
                  </td>
                  <td class="text-xs">{{ t.village || (t.villages?.[0]?.name) }}{{ t.moo ? ' ม.'+t.moo : '' }}</td>
                  <td class="text-xs text-slate-500">{{ t.tambon }} · {{ t.amphur }}</td>
                  <td class="text-center">
                    <span v-if="t.has_account" class="text-[10px] px-2 py-0.5 rounded bg-green-50 text-green-700 dark:bg-green-900/30">✓ มี</span>
                    <span v-else class="text-[10px] px-2 py-0.5 rounded bg-slate-100 text-slate-500 dark:bg-slate-800">— ไม่มี</span>
                  </td>
                </tr>
                <tr v-if="!trackersList.data.length">
                  <td colspan="7" class="py-6 text-center text-slate-500">ไม่พบผู้กำกับติดตาม</td>
                </tr>
              </tbody>
            </table>
          </div>
          <Pagination v-if="trackersList.last_page > 1"
                      :current="trackersList.current_page" :last="trackersList.last_page" :total="trackersList.total"
                      unit="คน"
                      @go="loadTargetsPage" />
          <div v-else class="text-xs text-slate-500 mt-2 text-center">
            ทั้งหมด {{ formatNumber(trackersList.total) }} คน
          </div>
        </div>

        <!-- ╔══════════════════════════════════════╗ -->
        <!-- ║ Tab: ผู้ตกหล่น (missed)                ║ -->
        <!-- ╚══════════════════════════════════════╝ -->
        <div v-else-if="topic === 'missed'">
          <Loader v-if="tabLoading" label="" py="py-8" :size="40" />
          <div v-else-if="missedRows.length === 0" class="card p-10 text-center text-sm text-slate-500">ยังไม่มีข้อมูล</div>
          <div v-else class="card overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="text-xs text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                <tr>
                  <th class="text-left py-2 px-3 w-10">#</th>
                  <th class="text-left py-2 px-3">อำเภอ</th>
                  <th class="text-right py-2 px-3">เปราะบาง</th>
                  <th class="text-right py-2 px-3">จปฐ.</th>
                  <th class="text-right py-2 px-3">3 กลุ่ม</th>
                  <th class="text-right py-2 px-3">รวม</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="(r, i) in missedRows" :key="i" class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                  <td class="px-3 py-2 text-slate-400 tabular-nums">{{ i + 1 }}</td>
                  <td class="px-3 py-2 font-medium">{{ r.amphur_name }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(r.cnt_vulnerable) }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(r.cnt_jpt) }}</td>
                  <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(r.cnt_both) }}</td>
                  <td class="px-3 py-2 text-right tabular-nums font-semibold text-blue-700 dark:text-blue-300">{{ formatNumber(r.cnt_total) }}</td>
                </tr>
              </tbody>
              <tfoot class="border-t-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 font-semibold">
                <tr>
                  <td class="px-3 py-2.5" colspan="2">รวมทั้งหมด</td>
                  <td class="px-3 py-2.5 text-right tabular-nums">{{ formatNumber(missedTotals.vuln) }}</td>
                  <td class="px-3 py-2.5 text-right tabular-nums">{{ formatNumber(missedTotals.jpt) }}</td>
                  <td class="px-3 py-2.5 text-right tabular-nums">{{ formatNumber(missedTotals.both) }}</td>
                  <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ formatNumber(missedTotals.total) }}</td>
                </tr>
              </tfoot>
            </table>
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
          <div class="font-semibold mb-3 flex items-center gap-2">
            <i class="fi-rr-user-headset text-orange-500"></i>
            ผู้ติดตามที่ไม่อัปเดตเกิน 3 วัน
            <span v-if="bottleneck.inactive_trackers.length"
                  class="text-[10px] px-1.5 py-0.5 rounded-full card-tint-orange font-medium">
              {{ bottleneck.inactive_trackers.length }} คน
            </span>
          </div>
          <div v-if="!bottleneck.inactive_trackers.length" class="text-sm text-slate-500">ทุกคนอัปเดตเป็นประจำ</div>
          <div v-for="t in bottleneck.inactive_trackers" :key="t.id"
               class="flex items-start justify-between gap-3 py-2.5 border-b border-slate-100 dark:border-slate-800 last:border-0">
            <div class="min-w-0 flex-1">
              <div class="font-medium text-sm flex items-center gap-1.5 flex-wrap">
                <span>{{ t.name }}</span>
                <span class="text-xs text-slate-500">({{ t.position }})</span>
              </div>
              <!-- หมู่บ้านที่ดูแล: ถ้ามีหลายหมู่ → แสดงเป็น chip list / ถ้า 1 หมู่ → แสดง text ปกติ -->
              <div v-if="(t.villages?.length || 0) > 1" class="mt-1 flex flex-wrap gap-1">
                <span v-for="(v, i) in t.villages" :key="i"
                      class="text-[11px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                  {{ v }}
                </span>
              </div>
              <div v-else class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ (t.villages && t.villages[0]) || t.village }}
              </div>
            </div>
            <span v-if="(t.village_count || 0) > 1"
                  class="shrink-0 text-[11px] px-2 py-0.5 rounded-full bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-300 font-medium whitespace-nowrap"
                  :title="`ดูแล ${t.village_count} หมู่บ้าน`">
              {{ t.village_count }} หมู่
            </span>
          </div>
        </div>
      </template>

    </div>
  </AppLayout>
</template>

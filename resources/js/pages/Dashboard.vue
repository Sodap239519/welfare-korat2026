<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { useThemeStore } from '@/stores/theme';
import { useAuthStore } from '@/stores/auth';
import { formatNumber, STATUS_SHORT } from '@/composables/useApi';

const theme = useThemeStore();
const auth = useAuthStore();
const dark = computed(() => theme.isDark);

const filters = ref({ amphur_id: '', tambon_id: '', village_id: '' });
const stats = ref(null);
const trend = ref({ labels: [], series: [{ name: 'ยอดสะสม', data: [] }, { name: 'เป้าหมาย', data: [] }] });
const channel = ref({ labels: [], data: [] });
const villages = ref([]);
const phases = ref([]);
const overview = ref(null);
const channelsRef = ref([]);
const amphurOpts = ref([]);
const tambonOpts = ref([]);
const villageOpts = ref([]);
const refreshing = ref(false);
const asOf = ref('—');

async function loadOpts() {
  const [a, p, c] = await Promise.all([
    axios.get('/api/ref/amphurs'),
    axios.get('/api/ref/project-phases'),
    axios.get('/api/ref/channels'),
  ]);
  amphurOpts.value = a.data.data;
  phases.value = p.data.data;
  channelsRef.value = c.data.data;
}
async function loadTambons() {
  filters.value.tambon_id = '';
  filters.value.village_id = '';
  if (!filters.value.amphur_id) { tambonOpts.value = []; villageOpts.value = []; return; }
  tambonOpts.value = (await axios.get('/api/ref/tambons', { params: { amphur_id: filters.value.amphur_id } })).data.data;
}
async function loadVillages() {
  filters.value.village_id = '';
  if (!filters.value.tambon_id) { villageOpts.value = []; return; }
  villageOpts.value = (await axios.get('/api/ref/villages', { params: { tambon_id: filters.value.tambon_id } })).data.data;
}

async function loadAll() {
  refreshing.value = true;
  const params = { ...filters.value };
  Object.keys(params).forEach(k => params[k] === '' && delete params[k]);
  const [s, t, c, v, ov] = await Promise.all([
    axios.get('/api/dashboard/stats',        { params }),
    axios.get('/api/dashboard/trends',       { params: { ...params, days: 14 } }),
    axios.get('/api/dashboard/by-channel',   { params }),
    axios.get('/api/dashboard/top-villages', { params: { ...params, limit: 5 } }),
    axios.get('/api/ref/overview-metrics'),
  ]);
  stats.value = s.data;
  trend.value = t.data;
  channel.value = c.data;
  villages.value = v.data.data;
  overview.value = ov.data;
  asOf.value = new Date(s.data.as_of).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
  refreshing.value = false;
}

onMounted(async () => {
  await loadOpts();
  await loadAll();
});
watch(filters, loadAll, { deep: true });

const trendOptions = computed(() => ({
  chart: { type: 'area', height: 280, fontFamily: 'Prompt, sans-serif', toolbar: { show: false }, zoom: { enabled: false } },
  colors: ['#2563eb', '#94a3b8'],
  stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 5] },
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 90, 100] } },
  dataLabels: { enabled: false },
  grid: { borderColor: dark.value ? '#1f2937' : '#eef2f7', strokeDashArray: 3 },
  xaxis: { categories: trend.value.labels, axisBorder: { show: false }, axisTicks: { show: false } },
  yaxis: { labels: { formatter: v => (v >= 1000 ? (v/1000).toFixed(1)+'k' : String(v)) } },
  legend: { position: 'top', horizontalAlign: 'right' },
  tooltip: { theme: dark.value ? 'dark' : 'light' },
  theme: { mode: dark.value ? 'dark' : 'light' },
}));

const statusOptions = computed(() => {
  const ord = ['4.1','4.2','4.3','4.4','4.5','4.6','4.7'];
  const colorMap = { '4.1':'#94a3b8','4.2':'#2563eb','4.3':'#fb923c','4.4':'#f97316','4.5':'#dc2626','4.6':'#0ea5e9','4.7':'#16a34a' };
  const labels = ord.map(c => STATUS_SHORT[c] || c);
  const series = ord.map(c => Number(stats.value?.by_status?.[c] ?? 0));
  const colors = ord.map(c => colorMap[c]);
  return {
    options: {
      chart: { type: 'donut', height: 280, fontFamily: 'Prompt, sans-serif' },
      colors,
      labels,
      dataLabels: { enabled: false },
      legend: { position: 'bottom', fontSize: '11px' },
      stroke: { width: 2, colors: [dark.value ? '#0f172a' : '#ffffff'] },
      plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: {
        show: true, label: 'รวม',
        formatter: () => formatNumber(series.reduce((a,b) => a+b, 0))
      } } } } },
      tooltip: { theme: dark.value ? 'dark' : 'light', y: { formatter: v => formatNumber(v) + ' ราย' } },
      theme: { mode: dark.value ? 'dark' : 'light' },
    },
    series,
  };
});

const channelOptions = computed(() => ({
  options: {
    chart: { type: 'bar', height: 280, fontFamily: 'Prompt, sans-serif', toolbar: { show: false } },
    colors: ['#2563eb'],
    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '60%' } },
    dataLabels: { enabled: true, offsetX: 28, formatter: v => formatNumber(v), style: { fontSize: '11px', colors: ['#0f172a'] } },
    xaxis: { categories: channel.value.labels, axisBorder: { show: false }, axisTicks: { show: false } },
    grid: { borderColor: dark.value ? '#1f2937' : '#eef2f7', strokeDashArray: 3, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
    fill: { type: 'gradient', gradient: { shade: 'light', type: 'horizontal', gradientToColors: ['#0ea5e9'], stops: [0, 100] } },
    tooltip: { theme: dark.value ? 'dark' : 'light' },
    theme: { mode: dark.value ? 'dark' : 'light' },
  },
  series: [{ name: 'จำนวน', data: channel.value.data }],
}));

const channelCountMap = computed(() => {
  const map = {};
  channel.value.labels?.forEach((label, i) => { map[label] = channel.value.data[i] || 0; });
  return map;
});

const phaseIconMap = { 1:'fi-rr-megaphone', 2:'fi-rr-edit', 3:'fi-rr-folder', 4:'fi-rr-search', 5:'fi-rr-chart-pie' };
const currentPhaseIdx = computed(() => phases.value.findIndex(p => p.is_current));

function pctColor(n) {
  if (n >= 80) return 'text-green-600';
  if (n >= 50) return 'text-orange-600';
  return 'text-red-600';
}
function pctBar(n) {
  if (n >= 80) return 'bg-green-500';
  if (n >= 50) return 'bg-orange-500';
  return 'bg-red-500';
}
</script>

<template>
  <AppLayout :greeting="`สวัสดี, ${auth.user?.name || 'ผู้ใช้'} 👋`" title="Dashboard · ภาพรวมโครงการ">
    <div class="space-y-4">

      <!-- HERO -->
      <div class="card-hero p-5 lg:p-6" v-if="stats">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <div class="text-xs opacity-80">แผนปฏิบัติการส่งต่อข้อมูลให้คนจนเข้าถึงสิทธิของรัฐ</div>
            <h1 class="text-lg lg:text-xl font-semibold mt-1">โครงการลงทะเบียนบัตรสวัสดิการแห่งรัฐ 2569 รอบใหม่</h1>
            <div class="text-xs opacity-80 mt-1 flex items-center gap-1.5">
              <span class="inline-block w-1.5 h-1.5 rounded-full bg-green-300 animate-pulse"></span>
              จ.นครราชสีมา · อัปเดต {{ asOf }}
            </div>
          </div>
          <div class="text-right">
            <div class="text-xs opacity-80">ความคืบหน้ารวม</div>
            <div class="text-3xl lg:text-4xl font-bold leading-none">{{ stats.pct_done }}%</div>
            <div class="text-xs opacity-80 mt-1">{{ formatNumber(stats.registered) }} / {{ formatNumber(stats.total) }}</div>
          </div>
        </div>
        <div class="mt-3 h-2 rounded-full bg-white/20 overflow-hidden">
          <div class="h-full bg-white" :style="{ width: stats.pct_done + '%' }"></div>
        </div>
      </div>

      <!-- Filter — grid on mobile, flex on desktop -->
      <div class="grid grid-cols-3 sm:flex sm:flex-wrap sm:items-center gap-2">
        <select v-model="filters.amphur_id" @change="loadTambons" class="w-full min-w-0 px-2 sm:px-3 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs sm:text-sm">
          <option value="">ทุกอำเภอ</option>
          <option v-for="a in amphurOpts" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
        <select v-model="filters.tambon_id" @change="loadVillages" :disabled="!filters.amphur_id" class="w-full min-w-0 px-2 sm:px-3 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs sm:text-sm disabled:opacity-40">
          <option value="">ทุกตำบล</option>
          <option v-for="t in tambonOpts" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <select v-model="filters.village_id" :disabled="!filters.tambon_id" class="w-full min-w-0 px-2 sm:px-3 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs sm:text-sm disabled:opacity-40">
          <option value="">ทุกหมู่บ้าน</option>
          <option v-for="v in villageOpts" :key="v.id" :value="v.id">{{ v.name }}{{ v.moo ? ' (หมู่ '+v.moo+')' : '' }}</option>
        </select>
        <button @click="loadAll" :disabled="refreshing" class="col-span-3 sm:col-auto sm:ml-auto btn-primary px-3 py-2 text-xs flex items-center justify-center gap-1.5 disabled:opacity-60">
          <i :class="['fi-rr-refresh', refreshing && 'animate-spin']"></i> รีเฟรช
        </button>
      </div>

      <!-- KPI -->
      <div v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="card-tint-blue p-4">
          <div class="flex items-center justify-between"><div class="text-xs opacity-80">เป้าหมาย</div><i class="fi-rr-users-alt text-blue-700"></i></div>
          <div class="text-2xl lg:text-3xl font-bold mt-3 text-blue-900 dark:text-blue-100">{{ formatNumber(stats.total) }}</div>
          <div class="text-[11px] mt-1 opacity-70">คน</div>
        </div>
        <div class="card-tint-green p-4">
          <div class="flex items-center justify-between"><div class="text-xs opacity-80">ลงทะเบียนแล้ว</div><i class="fi-rr-check text-green-700"></i></div>
          <div class="text-2xl lg:text-3xl font-bold mt-3 text-green-900 dark:text-green-100">{{ formatNumber(stats.registered) }}</div>
          <div class="text-[11px] mt-1 opacity-70">{{ stats.pct_done }}% · +{{ stats.today_change }} วันนี้</div>
        </div>
        <div class="card-tint-sky p-4">
          <div class="flex items-center justify-between"><div class="text-xs opacity-80">รอยืนยันตัวตน</div><i class="fi-rr-fingerprint text-sky-700"></i></div>
          <div class="text-2xl lg:text-3xl font-bold mt-3 text-sky-900 dark:text-sky-100">{{ formatNumber(stats.waiting_kyc) }}</div>
        </div>
        <div class="card-tint-red p-4">
          <div class="flex items-center justify-between"><div class="text-xs opacity-80">ติดขัด · รอแก้</div><i class="fi-rr-triangle-warning text-red-700"></i></div>
          <div class="text-2xl lg:text-3xl font-bold mt-3 text-red-700 dark:text-red-300">{{ formatNumber(stats.stuck) }}</div>
        </div>
      </div>

      <!-- SOP stepper (ย้ายมาจาก Overview) -->
      <div v-if="phases.length" class="card p-5">
        <div class="flex items-center justify-between mb-4">
          <div>
            <div class="font-semibold">SOP 5 ชั้น</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
              ขั้นปัจจุบัน: ชั้น {{ phases.find(p => p.is_current)?.sop_level || '—' }} — {{ phases.find(p => p.is_current)?.name }}
            </div>
          </div>
          <span class="text-xs px-2.5 py-1 rounded-full card-tint-blue font-medium">ดำเนินการอยู่</span>
        </div>

        <!-- Desktop horizontal stepper -->
        <div class="hidden md:flex items-start mt-2">
          <div v-for="(p, idx) in phases" :key="p.id"
               :class="['sop-step', p.is_current ? 'current' : (idx < currentPhaseIdx ? 'done' : '')]">
            <div class="sop-circle">
              <i v-if="idx < currentPhaseIdx" class="fi-rr-check"></i>
              <i v-else-if="p.is_current" :class="phaseIconMap[p.sop_level] || 'fi-rr-circle'"></i>
              <span v-else>{{ p.sop_level }}</span>
            </div>
            <div :class="['mt-2 text-sm font-medium', p.is_current ? 'text-blue-700 dark:text-blue-400' : '']">ชั้น {{ p.sop_level }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ p.name }}</div>
          </div>
        </div>

        <!-- Mobile vertical -->
        <div class="md:hidden space-y-2 mt-1">
          <div v-for="(p, idx) in phases" :key="p.id"
               :class="['flex gap-3 items-start', p.is_current ? 'card-tint-blue p-3 rounded-2xl' : '']">
            <div :class="['w-9 h-9 shrink-0 rounded-full flex items-center justify-center',
                         idx < currentPhaseIdx ? 'bg-green-600 text-white' :
                         (p.is_current ? 'bg-blue-700 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-500')]">
              <i v-if="idx < currentPhaseIdx" class="fi-rr-check"></i>
              <span v-else class="font-bold">{{ p.sop_level }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <div :class="['font-medium text-sm', p.is_current ? 'text-blue-700 dark:text-blue-300' : '']">
                ชั้น {{ p.sop_level }} · {{ p.name }} <span v-if="p.is_current">⟵ ปัจจุบัน</span>
              </div>
              <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ p.description }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ชั้น 4 — 3 ฝ่าย (จาก Overview) -->
      <div v-if="overview">
        <div class="text-sm font-semibold mb-2">ชั้น 4 — ดำเนินการ 3 ฝ่าย</div>
        <div class="grid md:grid-cols-3 gap-3">
          <div class="card-tint-blue p-4">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 rounded-xl bg-blue-700 text-white flex items-center justify-center"><i class="fi-rr-search"></i></div>
              <div><div class="font-medium">ตรวจสอบสิทธิ์</div><div class="text-xs opacity-70">กรมบัญชีกลาง</div></div>
            </div>
            <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ formatNumber(overview.level_4.check_rights) }}</div>
          </div>
          <div class="card-tint-orange p-4">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 rounded-xl bg-orange-500 text-white flex items-center justify-center"><i class="fi-rr-paper-plane"></i></div>
              <div><div class="font-medium">ส่งเอกสารเพิ่มเติม</div><div class="text-xs opacity-70">ผ่าน 13 ธนาคาร</div></div>
            </div>
            <div class="text-2xl font-bold text-orange-700 dark:text-orange-300">{{ formatNumber(overview.level_4.extra_docs) }}</div>
          </div>
          <div class="card-tint-sky p-4">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 rounded-xl bg-sky-600 text-white flex items-center justify-center"><i class="fi-rr-fingerprint"></i></div>
              <div><div class="font-medium">ยืนยันตัวตน / ใช้สิทธิ</div><div class="text-xs opacity-70">e-KYC</div></div>
            </div>
            <div class="text-2xl font-bold text-sky-900 dark:text-sky-100">{{ formatNumber(overview.level_4.kyc_waiting + overview.level_4.kyc_done) }}</div>
          </div>
        </div>
      </div>

      <!-- Trend -->
      <div class="card p-4 lg:p-5 min-w-0 overflow-hidden">
        <div class="font-semibold text-sm">แนวโน้มการลงทะเบียน 14 วันล่าสุด</div>
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">ยอดสะสมรายวัน · เทียบเป้าหมาย</div>
        <div class="w-full overflow-hidden">
          <apexchart type="area" height="280" :options="trendOptions" :series="trend.series" />
        </div>
      </div>

      <!-- Status + Channel -->
      <div class="grid lg:grid-cols-2 gap-3">
        <div class="card p-4 lg:p-5 min-w-0 overflow-hidden">
          <div class="font-semibold text-sm">สัดส่วนสถานะการลงทะเบียน</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">7 สถานะ</div>
          <div class="w-full overflow-hidden">
            <apexchart type="donut" height="280" :options="statusOptions.options" :series="statusOptions.series" />
          </div>
        </div>
        <div class="card p-4 lg:p-5 min-w-0 overflow-hidden">
          <div class="font-semibold text-sm">ช่องทางการลงทะเบียน</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">4 ช่องทางหลัก</div>
          <div class="w-full overflow-hidden">
            <apexchart type="bar" height="280" :options="channelOptions.options" :series="channelOptions.series" />
          </div>
        </div>
      </div>

      <!-- 4 ช่องทาง + 5 ธนาคาร (จาก Overview) -->
      <div v-if="channelsRef.length" class="card p-5">
        <div class="font-semibold">4 ช่องทางการลงทะเบียน</div>
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-4">ผู้เข้าระบบเลือกได้ตามความสะดวก</div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div v-for="c in channelsRef" :key="c.id" class="border border-slate-100 dark:border-slate-800 rounded-2xl p-4 text-center">
            <div class="w-12 h-12 mx-auto rounded-xl card-tint-sky flex items-center justify-center text-sky-700 text-xl mb-2"><i :class="c.icon || 'fi-rr-circle'"></i></div>
            <div class="font-medium text-sm">{{ c.name }}</div>
            <div class="text-xl font-bold mt-1">{{ formatNumber(channelCountMap[c.name] || 0) }}</div>
          </div>
        </div>
      </div>

      <!-- 5 ธนาคาร (จาก Overview) -->
      <div v-if="overview?.by_bank?.length" class="card p-5">
        <div class="font-semibold flex items-center gap-2">
          <i class="fi-rr-bank text-green-700"></i> เจาะลึกช่องทางธนาคาร · 5 แห่ง
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-4">รวม {{ formatNumber(overview.by_bank.reduce((a,b) => a + b.count, 0)) }} รายการที่เลือกธนาคาร</div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <div v-for="b in overview.by_bank" :key="b.code" class="border border-slate-100 dark:border-slate-800 rounded-2xl p-3 text-center">
            <div class="w-10 h-10 mx-auto rounded-xl card-tint-green flex items-center justify-center text-green-700 mb-2">
              <i class="fi-rr-bank"></i>
            </div>
            <div class="text-xs font-medium leading-tight">{{ b.name }}</div>
            <div class="text-lg font-bold mt-1">{{ formatNumber(b.count) }}</div>
          </div>
        </div>
      </div>

      <!-- Top villages -->
      <div class="card p-4 lg:p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="font-semibold text-sm">สรุปยอดรายหมู่บ้าน · 5 อันดับ</div>
          <RouterLink to="/targets" class="text-xs text-blue-700 dark:text-blue-400 hover:underline">ดูทั้งหมด →</RouterLink>
        </div>

        <div class="space-y-2">
          <div v-for="v in villages" :key="v.village_id" class="border border-slate-100 dark:border-slate-800 rounded-xl p-3">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0 flex-1">
                <div class="font-medium text-sm truncate">{{ v.village }}{{ v.moo ? ' · หมู่ '+v.moo : '' }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                  {{ v.location }}
                  <span v-if="v.tracker"> · {{ v.tracker.name }} ({{ v.tracker.position }})</span>
                </div>
              </div>
              <span :class="['font-semibold text-sm', pctColor(v.pct)]">{{ v.pct }}%</span>
            </div>
            <div class="mt-2 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
              <div :class="['h-full', pctBar(v.pct)]" :style="{ width: v.pct + '%' }"></div>
            </div>
            <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ formatNumber(v.done) }} / {{ formatNumber(v.total) }}</div>
          </div>
          <div v-if="villages.length === 0" class="text-center text-sm text-slate-500 py-6">ไม่พบข้อมูลหมู่บ้าน</div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

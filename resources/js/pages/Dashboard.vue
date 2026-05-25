<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { computed } from 'vue';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const dark = computed(() => theme.isDark);

const FONT = 'Prompt, sans-serif';

const trendOptions = computed(() => ({
  chart: { type: 'area', height: 280, fontFamily: FONT, toolbar: { show: false }, zoom: { enabled: false } },
  colors: ['#2563eb', '#94a3b8'],
  stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 5] },
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05, stops: [0, 90, 100] } },
  dataLabels: { enabled: false },
  grid: { borderColor: dark.value ? '#1f2937' : '#eef2f7', strokeDashArray: 3 },
  xaxis: { categories: ['12','13','14','15','16','17','18','19','20','21','22','23','24','25'], axisBorder: { show: false }, axisTicks: { show: false } },
  yaxis: { labels: { formatter: v => (v/1000).toFixed(0)+'k' } },
  legend: { position: 'top', horizontalAlign: 'right' },
  tooltip: { theme: dark.value ? 'dark' : 'light' },
  theme: { mode: dark.value ? 'dark' : 'light' },
}));
const trendSeries = [
  { name: 'ยอดสะสม', data: [28100,29230,30540,31800,33020,34150,35290,36470,37650,38800,39620,40380,40900,41289] },
  { name: 'เป้าหมาย', data: [28000,29500,31000,32500,34000,35500,37000,38500,40000,41500,43000,44500,46000,47500] },
];

const statusOptions = computed(() => ({
  chart: { type: 'donut', height: 280, fontFamily: FONT },
  colors: ['#94a3b8', '#2563eb', '#fb923c', '#f97316', '#dc2626', '#0ea5e9', '#16a34a'],
  labels: ['4.1 ไม่ประสงค์','4.2 ระหว่างลงทะเบียน','4.3 เตรียมเอกสาร','4.4 ส่งเอกสารเพิ่ม','4.5 รออุทธรณ์','4.6 รอยืนยันตัวตน','4.7 ใช้สิทธิแล้ว'],
  dataLabels: { enabled: false },
  legend: { position: 'bottom', fontSize: '11px' },
  stroke: { width: 2, colors: [dark.value ? '#0f172a' : '#ffffff'] },
  plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'รวม', formatter: () => '31,903' } } } } },
  tooltip: { theme: dark.value ? 'dark' : 'light', y: { formatter: v => v.toLocaleString() + ' ราย' } },
  theme: { mode: dark.value ? 'dark' : 'light' },
}));
const statusSeries = [1842, 6420, 8230, 1247, 605, 8432, 5127];

const channelOptions = computed(() => ({
  chart: { type: 'bar', height: 280, fontFamily: FONT, toolbar: { show: false } },
  colors: ['#2563eb'],
  plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '60%' } },
  dataLabels: { enabled: true, offsetX: 28, formatter: v => v.toLocaleString(), style: { fontSize: '11px', colors: ['#0f172a'] } },
  xaxis: { categories: ['เว็บไซต์','แอปเป๋าตัง','ATM กรุงไทย','ธนาคาร 5 แห่ง'], axisBorder: { show: false }, axisTicks: { show: false } },
  grid: { borderColor: dark.value ? '#1f2937' : '#eef2f7', strokeDashArray: 3, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
  fill: { type: 'gradient', gradient: { shade: 'light', type: 'horizontal', gradientToColors: ['#0ea5e9'], stops: [0, 100] } },
  tooltip: { theme: dark.value ? 'dark' : 'light' },
  theme: { mode: dark.value ? 'dark' : 'light' },
}));
const channelSeries = [{ name: 'จำนวน', data: [12480, 18230, 4120, 6459] }];
</script>

<template>
  <AppLayout greeting="สวัสดี, สมชาย 👋" title="Dashboard">
    <div class="space-y-4">
      <!-- HERO -->
      <div class="card-hero p-5 lg:p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <div class="text-xs opacity-80">ภาพรวมโครงการ · จ.นครราชสีมา</div>
            <h1 class="text-lg lg:text-xl font-semibold mt-1">บัตรสวัสดิการแห่งรัฐ 2569</h1>
            <div class="text-xs opacity-80 mt-0.5 flex items-center gap-1.5">
              <span class="inline-block w-1.5 h-1.5 rounded-full bg-green-300 animate-pulse"></span>
              อัปเดต 14:32 · ทุก 30 วินาที
            </div>
          </div>
          <div class="text-right">
            <div class="text-xs opacity-80">ความคืบหน้ารวม</div>
            <div class="text-3xl lg:text-4xl font-bold leading-none">66.9%</div>
          </div>
        </div>
        <div class="flex items-end justify-between gap-3 mt-5">
          <div class="text-sm opacity-90">41,289 / 61,743 คน</div>
          <RouterLink to="/overview" class="text-xs bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-full flex items-center gap-1">ภาพรวม SOP <i class="fi-rr-arrow-small-right"></i></RouterLink>
        </div>
        <div class="mt-2 h-2 rounded-full bg-white/20 overflow-hidden">
          <div class="h-full bg-white" style="width:66.9%"></div>
        </div>
      </div>

      <!-- KPI -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="card-tint-blue p-4">
          <div class="flex items-center justify-between"><div class="text-xs opacity-80">เป้าหมาย</div><i class="fi-rr-users-alt text-blue-700"></i></div>
          <div class="text-2xl lg:text-3xl font-bold mt-3 text-blue-900 dark:text-blue-100">61,743</div>
          <div class="text-[11px] mt-1 opacity-70">คน · 32 อำเภอ</div>
        </div>
        <div class="card-tint-green p-4">
          <div class="flex items-center justify-between"><div class="text-xs opacity-80">ลงทะเบียนแล้ว</div><i class="fi-rr-check text-green-700"></i></div>
          <div class="text-2xl lg:text-3xl font-bold mt-3 text-green-900 dark:text-green-100">41,289</div>
          <div class="text-[11px] mt-1 opacity-70"><i class="fi-rr-arrow-up"></i> 66.9% · +312 วันนี้</div>
        </div>
        <div class="card-tint-sky p-4">
          <div class="flex items-center justify-between"><div class="text-xs opacity-80">รอยืนยันตัวตน</div><i class="fi-rr-fingerprint text-sky-700"></i></div>
          <div class="text-2xl lg:text-3xl font-bold mt-3 text-sky-900 dark:text-sky-100">8,432</div>
          <div class="text-[11px] mt-1 opacity-70">13.6% ของเป้า</div>
        </div>
        <div class="card-tint-red p-4">
          <div class="flex items-center justify-between"><div class="text-xs opacity-80">ติดขัด · รอแก้</div><i class="fi-rr-triangle-warning text-red-700"></i></div>
          <div class="text-2xl lg:text-3xl font-bold mt-3 text-red-700 dark:text-red-300">1,847</div>
          <div class="text-[11px] mt-1 opacity-70">รออุทธรณ์ / เอกสารเพิ่ม</div>
        </div>
      </div>

      <!-- Trend -->
      <div class="card p-4 lg:p-5">
        <div class="flex items-center justify-between mb-2">
          <div>
            <div class="font-semibold text-sm">แนวโน้มการลงทะเบียน 14 วันล่าสุด</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">ยอดสะสมรายวัน · เทียบเป้าหมาย</div>
          </div>
        </div>
        <apexchart type="area" height="280" :options="trendOptions" :series="trendSeries" />
      </div>

      <!-- Status + Channel -->
      <div class="grid lg:grid-cols-2 gap-3">
        <div class="card p-4 lg:p-5">
          <div class="font-semibold text-sm">สัดส่วนสถานะ</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">ตามขั้นตอน 4.1 - 4.7</div>
          <apexchart type="donut" height="280" :options="statusOptions" :series="statusSeries" />
        </div>
        <div class="card p-4 lg:p-5">
          <div class="font-semibold text-sm">ช่องทางการลงทะเบียน</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">4 ช่องทางหลัก</div>
          <apexchart type="bar" height="280" :options="channelOptions" :series="channelSeries" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Modal from '@/components/Modal.vue';
import { computed, onMounted, ref, reactive, watch } from 'vue';
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
// trendDays = 1 | 7 | 14 | 30 | 'custom'
const trendDays = ref(14);
const customRange = ref({ from: '', to: '' });
const trendTabs = [
  { label: 'วันนี้',    value: 1 },
  { label: '7 วัน',     value: 7 },
  { label: '14 วัน',    value: 14 },
  { label: '1 เดือน',   value: 30 },
  { label: 'กำหนดเอง', value: 'custom' },
];
const channel = ref({ labels: [], data: [] });
const topData = ref([]);
const topLevel = ref('amphur');          // เริ่มที่อำเภอเป็น default
const topLoading = ref(false);

// ภาพรวมทุกอำเภอ (32) + ทุกตำบล (~312) สำหรับ chart scroll horizontal
const allAmphurs = ref([]);
const allTambons = ref([]);
const allLoading = ref(false);
const topTabs = [
  { key: 'amphur',  label: 'อำเภอ',   icon: 'fi-rr-marker' },
  { key: 'tambon',  label: 'ตำบล',    icon: 'fi-rr-marker' },
  { key: 'village', label: 'หมู่บ้าน', icon: 'fi-rr-home' },
];

// SOP card collapsed state — key=sop_level, value=true/false
const sopExpanded = ref({});
const phases = ref([]);
const overview = ref(null);
const channelsRef = ref([]);
const amphurOpts = ref([]);
const tambonOpts = ref([]);
const villageOpts = ref([]);
const refreshing = ref(false);
const asOf = ref('—');

// SOP details (ตามแผนปฏิบัติการในเอกสารโครงการ)
const sopDetails = {
  1: {
    color: 'blue', title: 'วิเคราะห์เกณฑ์ผู้มีสิทธิ์',
    summary: 'มรก.มม. ส่ง Briefing 1 หน้า · เกณฑ์สิทธิ + เอกสารที่ต้องเตรียม',
    bullets: [
      { icon: 'fi-rr-id-card-clip-alt', text: 'บัตรประชาชน Smart Card' },
      { icon: 'fi-rr-fingerprint',      text: 'Laser ID หลังบัตร' },
      { icon: 'fi-rr-coins',            text: 'ข้อมูลรายได้ / ทรัพย์สิน' },
      { icon: 'fi-rr-list-check',       text: 'ข้อมูลตามเกณฑ์ที่กำหนด' },
    ],
    footer: 'ให้ทุก อปท./รพ.สต./กำนัน ภายใน 10 วัน ผ่านไลน์กลุ่ม "บัตรสวัสดิการแห่งรัฐ โคราช"',
  },
  2: {
    color: 'sky', title: 'ส่งรายชื่อกลุ่มเป้าหมาย',
    summary: 'มรก.มม. + DSS ส่ง "รายชื่อรายอำเภอ/ตำบล/หมู่บ้าน" พร้อมเลข 13 หลัก',
    bullets: [
      { icon: 'fi-rr-id-card-clip-alt', text: 'เลข 13 หลัก' },
      { icon: 'fi-rr-marker',           text: 'พิกัด GPS' },
      { icon: 'fi-rr-phone-call',       text: 'เบอร์ติดต่อ' },
      { icon: 'fi-rr-chart-pie-alt',    text: 'สถานะ MPI' },
    ],
    footer: 'นำเข้าระบบ NOAH + แบบฟอร์มที่ มรก. ออกแบบให้',
  },
  3: {
    color: 'orange', title: 'จัดเวทีชี้แจงกลุ่มเป้าหมาย',
    summary: 'จัด "เวที 1 ตำบล 1 ครั้ง" ภายใน 10 วันของเดือนมิถุนายน',
    bullets: [
      { icon: 'fi-rr-info',          text: 'ให้ความรู้สิทธิประโยชน์ + ขั้นตอน + เอกสาร' },
      { icon: 'fi-rr-comments',      text: 'การให้คำปรึกษาเฉพาะราย' },
      { icon: 'fi-rr-house-chimney', text: 'เยี่ยมบ้านผู้พิการ/ผู้สูงอายุที่มาเวทีไม่ได้' },
    ],
    footer: 'ใช้โค้ชทีมเดินสาย: นักศึกษา · รพ.สต. · อสม. · สภาองค์กรชุมชน',
  },
  4: {
    color: 'purple', title: 'กลไกลงทะเบียน',
    summary: '3 จุดบริการ ครอบคลุมทุกกลุ่ม — ประจำ / เคลื่อนที่ / เยี่ยมบ้าน',
    bullets: [
      { icon: 'fi-rr-building',    text: 'จุดบริการประจำ — เปิดศูนย์ที่ อบต./เทศบาล ทุกวันราชการ' },
      { icon: 'fi-rr-ambulance',   text: 'จุดบริการเคลื่อนที่ — ทีมนักศึกษา + ธนาคารกรุงไทย CRM ลงพื้นที่ "วันลงทะเบียนรวมหมู่บ้าน" สัปดาห์ละ 2 หมู่/ตำบล' },
      { icon: 'fi-rr-hand-holding-heart', text: 'ทีมพิเศษเยี่ยมบ้าน — สำหรับผู้พิการติดเตียง/ผู้สูงอายุ มีเอกสารมอบอำนาจสำเร็จรูป' },
    ],
    footer: '',
  },
  5: {
    color: 'green', title: 'ติดตามและประเมินผล',
    summary: 'Dashboard ของ มรก.มม. รายงานสถานะรายบุคคล เรียลไทม์',
    bullets: [
      { icon: 'fi-rr-chart-pie',    text: 'รายงานสรุปยอดรายหมู่บ้าน · ทุกวัน' },
      { icon: 'fi-rr-calendar',     text: 'ส่งรายงานให้คลังจังหวัด · ทุกศุกร์ 16:30' },
      { icon: 'fi-rr-user-headset', text: 'ทีม CRM ตามผู้ที่ลงทะเบียนแล้วแต่ยังไม่ยืนยันตัวตน' },
      { icon: 'fi-rr-search-alt',   text: 'วิเคราะห์ Bottleneck รายสัปดาห์เพื่อปรับกลไก' },
    ],
    footer: '',
  },
};
const sopTintMap = {
  blue:   'card-tint-blue',
  sky:    'card-tint-sky',
  orange: 'card-tint-orange',
  purple: 'bg-purple-50 dark:bg-purple-900/20 border-purple-100 dark:border-purple-900',
  green:  'card-tint-green',
};
const sopBadgeMap = {
  blue:   'bg-blue-700',
  sky:    'bg-sky-600',
  orange: 'bg-orange-500',
  purple: 'bg-purple-600',
  green:  'bg-green-600',
};

// 7 status colors (เดียวกับ donut chart)
const statusColorMap = {
  '4.1':'#94a3b8','4.2':'#2563eb','4.3':'#fb923c','4.4':'#f97316',
  '4.5':'#dc2626','4.6':'#0ea5e9','4.7':'#16a34a',
};

// Tint class + accent text per status (สำหรับ KPI card)
const statusTintMap = {
  '0':   { tint: 'bg-slate-50 border border-slate-200 dark:bg-slate-800/40 dark:border-slate-700', accent: 'text-slate-700 dark:text-slate-200', icon: 'fi-rr-question', label: 'ยังไม่ถูกติดตาม' },
  '4.1': { tint: 'bg-slate-50 border border-slate-200 dark:bg-slate-800/40 dark:border-slate-700', accent: 'text-slate-700 dark:text-slate-300', icon: 'fi-rr-ban',      label: 'ไม่ประสงค์' },
  '4.2': { tint: 'card-tint-blue',                                                                  accent: 'text-blue-700 dark:text-blue-300',   icon: 'fi-rr-edit',     label: 'ลงทะเบียน' },
  '4.3': { tint: 'card-tint-orange',                                                                accent: 'text-orange-700 dark:text-orange-300', icon: 'fi-rr-folder', label: 'เตรียมเอกสาร' },
  '4.4': { tint: 'card-tint-orange',                                                                accent: 'text-orange-700 dark:text-orange-300', icon: 'fi-rr-paper-plane', label: 'ส่งเอกสารเพิ่ม' },
  '4.5': { tint: 'card-tint-red',                                                                   accent: 'text-red-700 dark:text-red-300',     icon: 'fi-rr-triangle-warning', label: 'รออุทธรณ์' },
  '4.6': { tint: 'card-tint-sky',                                                                   accent: 'text-sky-700 dark:text-sky-300',     icon: 'fi-rr-fingerprint', label: 'รอยืนยันตัวตน' },
  '4.7': { tint: 'card-tint-green',                                                                 accent: 'text-green-700 dark:text-green-300', icon: 'fi-rr-check-circle', label: 'ใช้สิทธิ์แล้ว' },
};

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
  const trendParams = buildTrendParams(params);
  const [s, t, c, ov] = await Promise.all([
    axios.get('/api/dashboard/stats',        { params }),
    axios.get('/api/dashboard/trends',       { params: trendParams }),
    axios.get('/api/dashboard/by-channel',   { params }),
    axios.get('/api/ref/overview-metrics',   { params }),  // ← respect filter
  ]);
  stats.value = s.data;
  trend.value = t.data;
  channel.value = c.data;
  overview.value = ov.data;
  asOf.value = new Date(s.data.as_of).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
  await Promise.all([loadTop(), loadAllLevels()]);
  refreshing.value = false;
}

async function loadAllLevels() {
  allLoading.value = true;
  const baseParams = {};
  if (filters.value.amphur_id) baseParams.amphur_id = filters.value.amphur_id;
  if (filters.value.tambon_id) baseParams.tambon_id = filters.value.tambon_id;
  try {
    const [a, t] = await Promise.all([
      axios.get('/api/dashboard/top', { params: { level: 'amphur', limit: 100, ...baseParams } }),
      axios.get('/api/dashboard/top', { params: { level: 'tambon', limit: 500, ...baseParams } }),
    ]);
    allAmphurs.value = a.data.data;
    allTambons.value = t.data.data;
  } finally { allLoading.value = false; }
}

function buildTrendParams(filterParams) {
  const p = { ...filterParams };
  if (trendDays.value === 'custom' && customRange.value.from && customRange.value.to) {
    p.from = customRange.value.from;
    p.to   = customRange.value.to;
  } else {
    p.days = trendDays.value;
  }
  return p;
}

async function loadTop() {
  topLoading.value = true;
  const params = { level: topLevel.value, limit: 5 };
  if (filters.value.amphur_id) params.amphur_id = filters.value.amphur_id;
  if (filters.value.tambon_id) params.tambon_id = filters.value.tambon_id;
  try {
    const { data } = await axios.get('/api/dashboard/top', { params });
    topData.value = data.data;
  } finally { topLoading.value = false; }
}

async function reloadTrend(daysOrCustom) {
  trendDays.value = daysOrCustom;
  if (daysOrCustom === 'custom') {
    // Init range to last 30 days if not set
    if (!customRange.value.from) {
      const today = new Date();
      const past  = new Date(); past.setDate(past.getDate() - 30);
      customRange.value.from = past.toISOString().slice(0, 10);
      customRange.value.to   = today.toISOString().slice(0, 10);
    }
    return; // wait for user to apply
  }
  await fetchTrend();
}

async function fetchTrend() {
  const params = { ...filters.value };
  Object.keys(params).forEach(k => params[k] === '' && delete params[k]);
  const trendParams = buildTrendParams(params);
  const { data } = await axios.get('/api/dashboard/trends', { params: trendParams });
  trend.value = data;
}

async function applyCustomRange() {
  if (!customRange.value.from || !customRange.value.to) return;
  await fetchTrend();
}

async function setSopCurrent(phase) {
  if (!auth.isSuperAdmin) return;
  if (!confirm(`ตั้งขั้นปัจจุบันเป็น "ชั้น ${phase.sop_level} — ${phase.name}" ?`)) return;
  await axios.post(`/api/admin/phases/${phase.id}/set-current`);
  // Reload หน้าเต็มเพื่ออัปเดต SOP + KPIs + chart ทั้งหมด
  window.location.reload();
}

// Icon options (curated) สำหรับ bullet picker
const ICON_OPTIONS = [
  { i: 'fi-rr-circle',            l: '● จุดทั่วไป' },
  { i: 'fi-rr-id-card-clip-alt',  l: '🆔 บัตรประชาชน' },
  { i: 'fi-rr-fingerprint',       l: '👆 ลายนิ้วมือ / Laser ID' },
  { i: 'fi-rr-coins',             l: '💰 เงิน / รายได้' },
  { i: 'fi-rr-list-check',        l: '☑ รายการตรวจสอบ' },
  { i: 'fi-rr-marker',            l: '📍 พิกัด / สถานที่' },
  { i: 'fi-rr-phone-call',        l: '📞 เบอร์โทร' },
  { i: 'fi-rr-chart-pie',         l: '📊 สถิติ' },
  { i: 'fi-rr-info',              l: 'ℹ ข้อมูล' },
  { i: 'fi-rr-comments',          l: '💬 ปรึกษา' },
  { i: 'fi-rr-house-chimney',     l: '🏠 บ้าน' },
  { i: 'fi-rr-building',          l: '🏢 อาคาร / ศูนย์บริการ' },
  { i: 'fi-rr-ambulance',         l: '🚑 หน่วยเคลื่อนที่' },
  { i: 'fi-rr-hand-holding-heart',l: '💝 เยี่ยมบ้าน' },
  { i: 'fi-rr-calendar',          l: '📅 ปฏิทิน' },
  { i: 'fi-rr-user-headset',      l: '🎧 CRM / call center' },
  { i: 'fi-rr-search-alt',        l: '🔍 วิเคราะห์' },
  { i: 'fi-rr-file-edit',         l: '📝 เอกสาร' },
  { i: 'fi-rr-megaphone',         l: '📢 ประกาศ' },
  { i: 'fi-rr-check-circle',      l: '✓ ตรวจสอบเรียบร้อย' },
  { i: 'fi-rr-paper-plane',       l: '✈ ส่ง' },
  { i: 'fi-rr-folder',            l: '📁 แฟ้ม' },
  { i: 'fi-rr-users-alt',         l: '👥 กลุ่มคน' },
];

// SOP Phase CRUD (Super Admin)
const showSopEdit = ref(false);
const sopForm = reactive({
  id: null, name: '', description: '', sop_level: 1,
  // details = { summary, bullets: [{icon,text}], footer }
  detailsSummary: '', detailsFooter: '', detailsBullets: [],
});
const sopErr = ref({});
const sopSaving = ref(false);

function openSopAdd() {
  if (!auth.isSuperAdmin) return;
  const maxLvl = phases.value.length ? Math.max(...phases.value.map(p => p.sop_level)) : 0;
  Object.assign(sopForm, {
    id: null, name: '', description: '', sop_level: maxLvl + 1,
    detailsSummary: '', detailsFooter: '', detailsBullets: [],
  });
  sopErr.value = {};
  showSopEdit.value = true;
}

function openSopEdit(phase) {
  if (!auth.isSuperAdmin) return;
  // ดึง details จาก DB (phase.details) มาก่อน · ถ้าไม่มีให้ fall back หา hardcoded sopDetails
  const d = phase.details && Object.keys(phase.details).length
    ? phase.details
    : (sopDetails[phase.sop_level] || {});
  Object.assign(sopForm, {
    id: phase.id, name: phase.name || '',
    description: phase.description || '', sop_level: phase.sop_level || 1,
    detailsSummary: d.summary || '',
    detailsFooter:  d.footer  || '',
    detailsBullets: Array.isArray(d.bullets) ? d.bullets.map(b => ({ ...b })) : [],
  });
  sopErr.value = {};
  showSopEdit.value = true;
}

function addBullet() {
  sopForm.detailsBullets.push({ icon: 'fi-rr-circle', text: '' });
}
function removeBullet(idx) {
  sopForm.detailsBullets.splice(idx, 1);
}
function moveBullet(idx, dir) {
  const j = idx + dir;
  if (j < 0 || j >= sopForm.detailsBullets.length) return;
  const tmp = sopForm.detailsBullets[idx];
  sopForm.detailsBullets[idx] = sopForm.detailsBullets[j];
  sopForm.detailsBullets[j] = tmp;
}

async function saveSopPhase() {
  sopSaving.value = true; sopErr.value = {};
  try {
    const bullets = sopForm.detailsBullets
      .filter(b => b.text && b.text.trim())
      .map(b => ({ icon: b.icon || 'fi-rr-circle', text: b.text.trim() }));
    const details = (sopForm.detailsSummary || sopForm.detailsFooter || bullets.length)
      ? { summary: sopForm.detailsSummary || null, footer: sopForm.detailsFooter || null, bullets }
      : null;

    const payload = {
      name: sopForm.name,
      description: sopForm.description,
      sop_level: sopForm.sop_level,
      details,
    };
    if (sopForm.id) {
      await axios.patch(`/api/admin/phases/${sopForm.id}`, payload);
    } else {
      await axios.post('/api/admin/phases', payload);
    }
    showSopEdit.value = false;
    // reload หน้าเพื่ออัปเดต SOP cards + sopDetails fallback ให้แน่ใจ
    window.location.reload();
  } catch (e) {
    sopErr.value = e.response?.data?.errors || { general: [e.response?.data?.message || 'ผิดพลาด'] };
  } finally { sopSaving.value = false; }
}

async function deleteSopPhase(phase) {
  if (!auth.isSuperAdmin) return;
  if (phase.is_current) { alert('ลบขั้นที่เป็นปัจจุบันไม่ได้ — กรุณาตั้งขั้นอื่นเป็นปัจจุบันก่อน'); return; }
  if (!confirm(`ลบ "ชั้น ${phase.sop_level} — ${phase.name}" ?\nการลบไม่สามารถย้อนกลับได้`)) return;
  try {
    await axios.delete(`/api/admin/phases/${phase.id}`);
    phases.value = (await axios.get('/api/ref/project-phases')).data.data;
  } catch (e) {
    alert(e.response?.data?.message || 'ลบไม่สำเร็จ');
  }
}

function toggleSop(level) {
  sopExpanded.value[level] = !sopExpanded.value[level];
}

function selectTopLevel(level) {
  if (topLevel.value === level) return;
  topLevel.value = level;
  loadTop();
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
  dataLabels: {
    enabled: true,
    enabledOnSeries: [0], // เฉพาะ "ยอดสะสม"
    background: { enabled: true, foreColor: '#1d4ed8', padding: 4, borderRadius: 4, borderWidth: 1, borderColor: '#2563eb', opacity: 0.95 },
    style: { fontSize: '10px', fontWeight: 600 },
    formatter: (val, opts) => {
      const series = opts.w.config.series[opts.seriesIndex].data;
      const last = series.length - 1;
      // แสดงแค่ จุดแรก / จุดกลาง / จุดสุดท้าย เพื่อลดความรก
      if (opts.dataPointIndex === 0 || opts.dataPointIndex === last || opts.dataPointIndex === Math.floor(last / 2)) {
        return formatNumber(val);
      }
      return '';
    },
  },
  markers: { size: [4, 0], strokeWidth: 0, hover: { size: 6 } },
  grid: { borderColor: dark.value ? '#1f2937' : '#eef2f7', strokeDashArray: 3 },
  xaxis: { categories: trend.value.labels, axisBorder: { show: false }, axisTicks: { show: false } },
  yaxis: { labels: { formatter: v => (v >= 1000 ? (v/1000).toFixed(1)+'k' : String(v)) } },
  legend: { position: 'top', horizontalAlign: 'right' },
  tooltip: { theme: dark.value ? 'dark' : 'light', y: { formatter: v => formatNumber(v) } },
  theme: { mode: dark.value ? 'dark' : 'light' },
}));

const statusOptions = computed(() => {
  // เพิ่ม '0' = ยังไม่ติดตามการลงทะเบียน (target ที่ยังไม่มี record ใน target_current_status)
  const ord = ['0','4.1','4.2','4.3','4.4','4.5','4.6','4.7'];
  const colorMap = {
    '0':'#cbd5e1',   // slate-300 — อ่อนกว่า 4.1 เพื่อแยกชัด
    '4.1':'#94a3b8','4.2':'#2563eb','4.3':'#fb923c','4.4':'#f97316',
    '4.5':'#dc2626','4.6':'#0ea5e9','4.7':'#16a34a',
  };
  const labelMap = { '0': 'ยังไม่ถูกติดตาม', ...STATUS_SHORT };
  const labels = ord.map(c => labelMap[c] || c);
  const series = ord.map(c => Number(stats.value?.by_status?.[c] ?? 0));
  const colors = ord.map(c => colorMap[c]);
  return {
    options: {
      chart: { type: 'donut', height: 280, fontFamily: 'Prompt, sans-serif' },
      colors,
      labels,
      dataLabels: {
        enabled: true,
        style: { fontSize: '11px', fontWeight: 600 },
        dropShadow: { enabled: true, blur: 2, opacity: 0.6 },
        formatter: (val, opts) => {
          const n = opts.w.globals.seriesTotals[opts.seriesIndex];
          return n > 0 ? formatNumber(n) : '';
        },
      },
      legend: { position: 'bottom', fontSize: '11px' },
      stroke: { width: 2, colors: [dark.value ? '#0f172a' : '#ffffff'] },
      plotOptions: { pie: { donut: { size: '68%', labels: { show: true,
        value: { show: true, fontSize: '20px', fontWeight: 700, formatter: v => formatNumber(v) },
        total: {
          show: true, label: 'รวม', fontSize: '12px',
          formatter: (w) => formatNumber(w.globals.seriesTotals.reduce((a, b) => a + b, 0)),
        }
      } } } },
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

// Stacked column chart — 4 ชั้น (ยังไม่ถูกติดตาม / ไม่ประสงค์ / กำลังลงทะเบียน / เรียบร้อย)
// 100% stacked → เห็นสัดส่วน · tooltip บอกจำนวนจริง
function makeStackedOverviewChart(rows, levelName) {
  const sumInProgress = r => ['4.2','4.3','4.4','4.5','4.6']
    .reduce((a, c) => a + (r.by_status?.[c] || 0), 0);

  return {
    options: {
      chart: {
        type: 'bar', height: 360, fontFamily: 'Prompt, sans-serif',
        stacked: true, stackType: '100%',
        toolbar: { show: false }, animations: { enabled: false },
      },
      colors: ['#cbd5e1', '#94a3b8', '#f97316', '#16a34a'],
      plotOptions: {
        bar: { horizontal: false, borderRadius: 4, columnWidth: '75%', borderRadiusApplication: 'end' },
      },
      dataLabels: {
        enabled: true,
        formatter: (val, opts) => {
          // val คือค่า % ของชิ้นในโหมด stacked 100%
          // ดึงจำนวนจริงจาก series[s][i] มาแสดง
          const count = opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex];
          if (!count) return '';
          // ซ่อน label ถ้าชิ้นเล็กเกินไป (< 7% ของแท่ง) — กันชนกัน
          return val >= 7 ? formatNumber(count) : '';
        },
        style: { fontSize: '10px', fontWeight: 700, colors: ['#1e293b', '#ffffff', '#ffffff', '#ffffff'] },
        dropShadow: { enabled: false },
      },
      legend: {
        position: 'top', horizontalAlign: 'center', fontSize: '11px',
        markers: { width: 12, height: 12, radius: 3 },
      },
      stroke: { width: 1, colors: [dark.value ? '#0f172a' : '#ffffff'] },
      xaxis: {
        categories: rows.map(r => r.name),
        labels: {
          rotate: -55, rotateAlways: true, hideOverlappingLabels: false, trim: false,
          style: { fontSize: '10px', fontFamily: 'Prompt, sans-serif' },
        },
        axisBorder: { show: false }, axisTicks: { show: false },
      },
      yaxis: {
        labels: { formatter: v => v + '%' },
        title: { text: 'สัดส่วน (%)', style: { fontSize: '10px', fontWeight: 500 } },
      },
      grid: { borderColor: dark.value ? '#1f2937' : '#eef2f7', strokeDashArray: 3 },
      tooltip: {
        theme: dark.value ? 'dark' : 'light',
        shared: true, intersect: false,
        x: {
          formatter: (val, opts) => {
            const r = rows[opts.dataPointIndex];
            return `${levelName}: ${r.name}` + (r.location ? ` · ${r.location}` : '') +
                   ` · เป้า ${formatNumber(r.total)} คน`;
          },
        },
        y: { formatter: v => formatNumber(v) + ' ราย' },
      },
      theme: { mode: dark.value ? 'dark' : 'light' },
    },
    series: [
      { name: 'ยังไม่ถูกติดตาม',     data: rows.map(r => r.by_status?.['0']  || 0) },
      { name: 'ไม่ประสงค์ลงทะเบียน',  data: rows.map(r => r.by_status?.['4.1'] || 0) },
      { name: 'กำลังลงทะเบียน',       data: rows.map(r => sumInProgress(r)) },
      { name: 'ลงทะเบียนเรียบร้อยแล้ว', data: rows.map(r => r.by_status?.['4.7'] || 0) },
    ],
  };
}

const amphurChart = computed(() => makeStackedOverviewChart(allAmphurs.value, 'อำเภอ'));
const tambonChart = computed(() => makeStackedOverviewChart(allTambons.value, 'ตำบล'));

// Carousel state
const currentOverviewIdx = ref(0);
const overviewCarouselRef = ref(null);
const overviewCards = [
  { key: 'amphur', label: 'ภาพรวมทุกอำเภอ', icon: 'fi-rr-marker', iconClass: 'text-blue-700' },
  { key: 'tambon', label: 'ภาพรวมทุกตำบล', icon: 'fi-rr-marker', iconClass: 'text-sky-700' },
];

function onOverviewScroll() {
  const el = overviewCarouselRef.value;
  if (!el) return;
  const idx = Math.round(el.scrollLeft / el.clientWidth);
  if (idx !== currentOverviewIdx.value) currentOverviewIdx.value = idx;
}

function goToOverview(idx) {
  const el = overviewCarouselRef.value;
  if (!el) return;
  el.scrollTo({ left: idx * el.clientWidth, behavior: 'smooth' });
}

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

// 8 status KPI cards (ยังไม่อัปเดต + 4.1-4.7) — ใช้ stats.by_status + stats.total
const statusCards = computed(() => {
  const order = ['0', '4.1', '4.2', '4.3', '4.4', '4.5', '4.6', '4.7'];
  const total = Math.max(stats.value?.total || 0, 1);
  return order.map(code => {
    const count = Number(stats.value?.by_status?.[code] ?? 0);
    return {
      code,
      label: statusTintMap[code].label,
      icon:  statusTintMap[code].icon,
      tint:  statusTintMap[code].tint,
      accent: statusTintMap[code].accent,
      dot:   statusColorMap[code] || '#94a3b8',
      count,
      pct: Math.round((count / total) * 1000) / 10,  // 1 decimal
    };
  });
});

// แตกแถบสีตาม by_status เป็น segments — ครบทั้ง 8 กลุ่ม (รวม ยังไม่ถูกติดตาม + ไม่ประสงค์)
function statusSegments(row) {
  const order = ['0','4.1','4.2','4.3','4.4','4.5','4.6','4.7'];
  const labelMap = { '0': 'ยังไม่ถูกติดตาม', ...STATUS_SHORT };
  const colorAll = { '0': '#cbd5e1', ...statusColorMap };
  const total = row.total || 1;
  return order.map(code => ({
    code,
    label: labelMap[code] || code,
    color: colorAll[code],
    count: row.by_status?.[code] || 0,
    width: ((row.by_status?.[code] || 0) / total) * 100,
  })).filter(s => s.count > 0);
}
</script>

<template>
  <AppLayout :greeting="`สวัสดี, ${auth.user?.name || 'ผู้ใช้'}`" title="Dashboard · ภาพรวมโครงการ">
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

      <!-- Filter — 3 selects + button : แถวเดียวบน desktop, grid 3 + ปุ่มแยกแถวบน mobile -->
      <div class="grid grid-cols-3 md:grid-cols-[1fr_1fr_1fr_auto] gap-2 items-center">
        <div class="relative min-w-0">
          <select v-model="filters.amphur_id" @change="loadTambons" class="w-full min-w-0 pl-2 sm:pl-3 pr-9 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs sm:text-sm">
            <option value="">ทุกอำเภอ</option>
            <option v-for="a in amphurOpts" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <button v-if="filters.amphur_id" @click="filters.amphur_id = ''; loadTambons()" title="ล้าง"
                  class="absolute right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <i class="fi-rr-cross-small text-[10px]"></i>
          </button>
        </div>
        <div class="relative min-w-0">
          <select v-model="filters.tambon_id" @change="loadVillages" :disabled="!filters.amphur_id" class="w-full min-w-0 pl-2 sm:pl-3 pr-9 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs sm:text-sm disabled:opacity-40">
            <option value="">ทุกตำบล</option>
            <option v-for="t in tambonOpts" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <button v-if="filters.tambon_id" @click="filters.tambon_id = ''; loadVillages()" title="ล้าง"
                  class="absolute right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <i class="fi-rr-cross-small text-[10px]"></i>
          </button>
        </div>
        <div class="relative min-w-0">
          <select v-model="filters.village_id" :disabled="!filters.tambon_id" class="w-full min-w-0 pl-2 sm:pl-3 pr-9 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs sm:text-sm disabled:opacity-40">
            <option value="">ทุกหมู่บ้าน</option>
            <option v-for="v in villageOpts" :key="v.id" :value="v.id">{{ v.name }}{{ v.moo ? ' (หมู่ '+v.moo+')' : '' }}</option>
          </select>
          <button v-if="filters.village_id" @click="filters.village_id = ''" title="ล้าง"
                  class="absolute right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <i class="fi-rr-cross-small text-[10px]"></i>
          </button>
        </div>
        <button @click="loadAll" :disabled="refreshing" class="col-span-3 md:col-auto md:justify-self-end btn-primary px-3 py-2 text-xs flex items-center justify-center gap-1.5 disabled:opacity-60 whitespace-nowrap">
          <i :class="['fi-rr-refresh', refreshing && 'animate-spin']"></i> รีเฟรช
        </button>
      </div>

      <!-- KPI · ทุกสถานะการลงทะเบียน (ยังไม่อัปเดต + 4.1-4.7) -->
      <div v-if="stats">
        <div class="flex items-baseline justify-between mb-2 px-1">
          <div class="text-sm font-semibold">สถานะการลงทะเบียน · ทั้งหมด {{ formatNumber(stats.total) }} คน</div>
          <div class="text-xs text-slate-500 dark:text-slate-400">
            <span v-if="stats.today_change > 0" class="text-green-600 dark:text-green-400">+{{ stats.today_change }} วันนี้</span>
            <span v-else>ยังไม่มีการอัปเดตวันนี้</span>
          </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-4 xl:grid-cols-8 gap-2 sm:gap-3">
          <div v-for="s in statusCards" :key="s.code"
               @click="s.code !== '0' && $router.push({ name: 'targets', query: { status: s.code } })"
               :class="[s.tint, 'rounded-2xl p-3 min-w-0 transition',
                        s.code !== '0' && 'cursor-pointer hover:shadow-md hover:-translate-y-0.5']">
            <div class="flex items-center justify-between gap-1 mb-2">
              <span class="text-[10px] opacity-70 font-medium whitespace-nowrap">{{ s.code === '0' ? '—' : s.code }}</span>
              <i :class="[s.icon, s.accent, 'text-sm']"></i>
            </div>
            <div class="text-[11px] leading-tight opacity-80 truncate" :title="s.label">{{ s.label }}</div>
            <div :class="['text-xl lg:text-2xl font-bold mt-1 leading-none', s.accent]">{{ formatNumber(s.count) }}</div>
            <div class="mt-1.5">
              <div class="flex items-center justify-between text-[10px] opacity-70">
                <span>{{ s.pct }}%</span>
                <span>{{ formatNumber(s.count) }}/{{ formatNumber(stats.total) }}</span>
              </div>
              <div class="mt-1 h-1 rounded-full bg-white/50 dark:bg-slate-900/40 overflow-hidden">
                <div class="h-full rounded-full" :style="{ width: Math.min(s.pct, 100) + '%', background: s.dot }"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SOP 5 ขั้น — minimal header + collapsible details + color by status -->
      <div v-if="phases.length" class="card p-4 lg:p-5">
        <div class="flex items-center justify-between gap-2 mb-4 flex-wrap">
          <div>
            <div class="font-semibold">SOP {{ phases.length }} ขั้น · กลุ่มเป้าหมาย {{ formatNumber(stats?.total || 0) }} คน</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">Demand → Supply → เตรียมกลุ่มเป้าหมาย → กลไกชุมชน → ติดตาม</div>
          </div>
          <span class="text-xs px-2.5 py-1 rounded-full card-tint-blue font-medium whitespace-nowrap">
            ขั้นปัจจุบัน: ชั้น {{ phases.find(p => p.is_current)?.sop_level || '—' }}
          </span>
        </div>

        <div class="flex lg:grid lg:grid-cols-5 gap-3 overflow-x-auto -mx-1 px-1 pb-1 snap-x">
          <div v-for="(p, idx) in phases" :key="p.id"
               :class="['shrink-0 w-72 sm:w-80 lg:w-auto snap-start rounded-2xl border-2 min-w-0 relative transition flex flex-col',
                        idx < currentPhaseIdx
                          ? 'bg-green-50 dark:bg-green-900/20 border-green-300 dark:border-green-700'
                        : p.is_current
                          ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-400 dark:border-blue-500 shadow-md'
                          : 'bg-slate-50 dark:bg-slate-800/40 border-slate-200 dark:border-slate-700']">

            <!-- Header — แสดงเฉพาะ ขั้นที่ + ชื่อขั้น (font 18pt) -->
            <div class="p-4">
              <div class="flex items-start gap-2.5">
                <div :class="['w-8 h-8 shrink-0 rounded-lg text-white flex items-center justify-center text-sm font-bold shadow-sm',
                              idx < currentPhaseIdx ? 'bg-green-600'
                            : p.is_current          ? 'bg-blue-700'
                                                    : 'bg-slate-400 dark:bg-slate-600']">
                  <i v-if="idx < currentPhaseIdx" class="fi-rr-check text-xs"></i>
                  <span v-else>{{ p.sop_level }}</span>
                </div>
                <div class="min-w-0 flex-1">
                  <div :class="['text-[10px] uppercase tracking-wider font-medium',
                                idx < currentPhaseIdx ? 'text-green-700 dark:text-green-300'
                              : p.is_current          ? 'text-blue-700 dark:text-blue-300'
                                                      : 'text-slate-500 dark:text-slate-400']">
                    ขั้นที่ {{ p.sop_level }} · {{
                      idx < currentPhaseIdx ? 'สำเร็จแล้ว'
                      : p.is_current        ? 'กำลังดำเนินการ'
                                            : 'ยังไม่ถึง'
                    }}
                  </div>
                  <div class="font-semibold leading-snug" style="font-size: 1.125rem;">
                    {{ p.name || sopDetails[p.sop_level]?.title }}
                  </div>
                </div>
                <!-- Edit/Delete (Super Admin) -->
                <div v-if="auth.isSuperAdmin" class="shrink-0 flex items-center gap-0.5 -mr-1 -mt-1">
                  <button @click.stop="openSopEdit(p)" class="p-1.5 rounded-lg hover:bg-white/60 dark:hover:bg-slate-700/60 text-slate-600 dark:text-slate-300" title="แก้ไข">
                    <i class="fi-rr-edit text-xs"></i>
                  </button>
                  <button @click.stop="deleteSopPhase(p)" :disabled="p.is_current"
                          class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30 text-red-600 disabled:opacity-30 disabled:cursor-not-allowed" title="ลบ">
                    <i class="fi-rr-trash text-xs"></i>
                  </button>
                </div>
              </div>

              <!-- Toggle button — เปิดดูรายละเอียด -->
              <button @click="toggleSop(p.sop_level)"
                      class="mt-3 w-full text-xs flex items-center justify-center gap-1.5 py-1.5 rounded-lg hover:bg-white/60 dark:hover:bg-slate-800/60 text-slate-600 dark:text-slate-400">
                <span>{{ sopExpanded[p.sop_level] ? 'ซ่อนรายละเอียด' : 'ดูรายละเอียด' }}</span>
                <i :class="sopExpanded[p.sop_level] ? 'fi-rr-angle-small-up' : 'fi-rr-angle-small-down'"></i>
              </button>
            </div>

            <!-- Collapsible details (prefer DB details ก่อน hardcoded fallback) -->
            <div v-if="sopExpanded[p.sop_level]"
                 class="px-4 pb-4 border-t border-current/10 bg-white/40 dark:bg-slate-900/30 rounded-b-2xl">
              <div class="text-xs leading-snug mt-3 mb-2 text-slate-600 dark:text-slate-300">
                {{ (p.details?.summary) || sopDetails[p.sop_level]?.summary || p.description }}
              </div>
              <ul class="space-y-1.5">
                <li v-for="(b, i) in ((p.details?.bullets?.length ? p.details.bullets : sopDetails[p.sop_level]?.bullets) || [])"
                    :key="i" class="flex items-start gap-2 text-xs leading-snug">
                  <i :class="[b.icon || 'fi-rr-circle', 'mt-0.5 shrink-0 opacity-70']"></i>
                  <span>{{ b.text }}</span>
                </li>
              </ul>
              <div v-if="(p.details?.footer) || sopDetails[p.sop_level]?.footer"
                   class="mt-3 pt-2 border-t border-slate-200 dark:border-slate-700 text-[11px] text-slate-500 dark:text-slate-400 leading-snug">
                {{ (p.details?.footer) || sopDetails[p.sop_level].footer }}
              </div>
            </div>

            <!-- Super Admin: ตั้งเป็นขั้นปัจจุบัน -->
            <div v-if="auth.isSuperAdmin && !p.is_current" class="px-4 pb-4 mt-auto">
              <button @click="setSopCurrent(p)"
                      class="w-full text-[11px] py-1.5 px-2 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 hover:text-blue-700 dark:hover:text-blue-300 text-slate-500 flex items-center justify-center gap-1">
                <i class="fi-rr-pin"></i> ตั้งเป็นขั้นปัจจุบัน
              </button>
            </div>

            <!-- ลูกศรเชื่อม (desktop) -->
            <i v-if="idx < phases.length - 1"
               class="fi-rr-angle-right hidden lg:block absolute -right-3.5 top-1/2 -translate-y-1/2 z-10 text-slate-300 dark:text-slate-600 text-lg"></i>
          </div>

          <!-- + เพิ่มขั้นใหม่ (Super Admin) -->
          <button v-if="auth.isSuperAdmin" @click="openSopAdd"
                  class="shrink-0 w-72 sm:w-80 lg:w-auto snap-start rounded-2xl border-2 border-dashed border-blue-300 dark:border-slate-600 bg-blue-50/30 dark:bg-slate-800/30 hover:bg-blue-50 dark:hover:bg-slate-800 text-blue-700 dark:text-blue-300 flex flex-col items-center justify-center gap-2 min-h-[180px] p-4 transition">
            <i class="fi-rr-add text-2xl"></i>
            <span class="text-sm font-medium">เพิ่มขั้น SOP ใหม่</span>
          </button>
        </div>
      </div>

      <!-- Modal: เพิ่ม / แก้ไข ขั้น SOP -->
      <Modal :show="showSopEdit" max-width="max-w-2xl" @close="showSopEdit = false">
        <div class="flex items-center justify-between mb-3">
          <div class="font-semibold flex items-center gap-2">
            <i :class="sopForm.id ? 'fi-rr-edit' : 'fi-rr-add'" class="text-blue-700"></i>
            {{ sopForm.id ? 'แก้ไขขั้น SOP' : 'เพิ่มขั้น SOP ใหม่' }}
          </div>
          <button @click="showSopEdit = false" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg"><i class="fi-rr-cross-small"></i></button>
        </div>

        <div v-if="sopErr?.general" class="card-tint-red p-3 text-sm mb-3"><i class="fi-rr-cross-circle"></i> {{ sopErr.general[0] }}</div>

        <form @submit.prevent="saveSopPhase" class="space-y-3">
          <div class="grid grid-cols-[80px_1fr] gap-3">
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">ชั้นที่ <span class="text-red-600">*</span></label>
              <input v-model.number="sopForm.sop_level" type="number" min="1" max="99" required
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            </div>
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">ชื่อขั้นตอน <span class="text-red-600">*</span></label>
              <input v-model="sopForm.name" required maxlength="150"
                     placeholder="เช่น วิเคราะห์เกณฑ์ผู้มีสิทธิ์"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <div v-if="sopErr.name" class="text-[11px] text-red-600 mt-1">{{ sopErr.name[0] }}</div>
            </div>
          </div>
          <div>
            <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">คำอธิบายขั้นตอน (สั้น 1 บรรทัด)</label>
            <textarea v-model="sopForm.description" rows="2" maxlength="500"
                      placeholder="เช่น มรก.มม. ส่ง Briefing 1 หน้า · เกณฑ์สิทธิ + เอกสารที่ต้องเตรียม"
                      class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm resize-y"></textarea>
          </div>

          <!-- รายละเอียดย่อย (details) -->
          <div class="border-t border-slate-100 dark:border-slate-800 pt-3">
            <div class="flex items-center justify-between mb-2">
              <div class="text-xs font-semibold flex items-center gap-1.5"><i class="fi-rr-list-check"></i> รายละเอียดย่อย (แสดงในใต้ "ดูรายละเอียด")</div>
            </div>
            <div>
              <label class="block text-[11px] text-slate-500 mb-1">บทสรุปสั้น (summary)</label>
              <input v-model="sopForm.detailsSummary" maxlength="500"
                     placeholder="เช่น มรก.มม. + DSS ส่ง 'รายชื่อรายอำเภอ/ตำบล/หมู่บ้าน' พร้อมเลข 13 หลัก"
                     class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs">
            </div>
            <div class="mt-3">
              <div class="flex items-center justify-between mb-1.5">
                <label class="text-[11px] text-slate-500">หัวข้อย่อย (มีไอคอนนำหน้า)</label>
                <button type="button" @click="addBullet" class="text-[11px] text-blue-700 dark:text-blue-300 hover:underline flex items-center gap-1">
                  <i class="fi-rr-add"></i> เพิ่มหัวข้อ
                </button>
              </div>
              <div v-if="sopForm.detailsBullets.length === 0" class="text-[11px] text-slate-400 text-center py-3 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                ยังไม่มีหัวข้อย่อย — กด "เพิ่มหัวข้อ" เพื่อสร้าง
              </div>
              <div v-for="(b, i) in sopForm.detailsBullets" :key="i"
                   class="flex items-center gap-1.5 mb-1.5 group">
                <select v-model="b.icon"
                        class="px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs w-32 shrink-0">
                  <option v-for="opt in ICON_OPTIONS" :key="opt.i" :value="opt.i">{{ opt.l }}</option>
                </select>
                <i :class="[b.icon, 'text-base text-slate-600 dark:text-slate-300 shrink-0 w-5 text-center']"></i>
                <input v-model="b.text" maxlength="300" placeholder="ข้อความหัวข้อย่อย"
                       class="flex-1 min-w-0 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs">
                <button type="button" @click="moveBullet(i, -1)" :disabled="i === 0" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-700 rounded disabled:opacity-30">
                  <i class="fi-rr-angle-up text-xs"></i>
                </button>
                <button type="button" @click="moveBullet(i, 1)" :disabled="i === sopForm.detailsBullets.length - 1" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-700 rounded disabled:opacity-30">
                  <i class="fi-rr-angle-down text-xs"></i>
                </button>
                <button type="button" @click="removeBullet(i)" class="p-1.5 hover:bg-red-50 dark:hover:bg-red-900/30 rounded text-red-600">
                  <i class="fi-rr-cross-small text-xs"></i>
                </button>
              </div>
            </div>
            <div class="mt-3">
              <label class="block text-[11px] text-slate-500 mb-1">หมายเหตุท้ายขั้น (footer)</label>
              <input v-model="sopForm.detailsFooter" maxlength="500"
                     placeholder="เช่น นำเข้าระบบ NOAH + แบบฟอร์มที่ มรก. ออกแบบให้"
                     class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs">
            </div>
          </div>

          <div class="flex gap-2 justify-end pt-1">
            <button type="button" @click="showSopEdit = false" class="btn-outline px-4 py-2 text-sm">ยกเลิก</button>
            <button type="submit" :disabled="sopSaving" class="btn-primary px-4 py-2 text-sm flex items-center gap-1.5">
              <i :class="['fi-rr-disk', sopSaving && 'animate-spin']"></i> บันทึก
            </button>
          </div>
        </form>
      </Modal>

      <!-- ใต้ SOP — รายละเอียดของขั้นปัจจุบัน + 3 ฝ่ายภาพรวม -->
      <div v-if="overview && phases.length">
        <div class="flex items-end justify-between mb-2 flex-wrap gap-2">
          <div class="text-sm font-semibold">
            <span v-if="phases.find(p => p.is_current)">
              ชั้น {{ phases.find(p => p.is_current).sop_level }} —
              {{ phases.find(p => p.is_current).name }}
            </span>
            <span v-else class="text-slate-500">ยังไม่ได้ตั้งขั้นปัจจุบัน · แสดงภาพรวม 3 ฝ่าย</span>
          </div>
          <div v-if="phases.find(p => p.is_current)?.description"
               class="text-[11px] text-slate-500 dark:text-slate-400 flex-1 sm:text-right">
            {{ phases.find(p => p.is_current).description }}
          </div>
        </div>

        <!-- Bullets ของขั้นปัจจุบัน — ถ้าผู้ใช้ตั้งค่าไว้ใน DB จะแสดงเป็น cards -->
        <div v-if="(phases.find(p => p.is_current)?.details?.bullets || []).length"
             class="grid md:grid-cols-3 gap-3 mb-3">
          <div v-for="(b, i) in phases.find(p => p.is_current).details.bullets" :key="i"
               class="card-tint-blue p-4">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-10 h-10 rounded-xl bg-blue-700 text-white flex items-center justify-center">
                <i :class="b.icon || 'fi-rr-circle'"></i>
              </div>
              <div class="font-medium text-sm leading-snug">{{ b.text }}</div>
            </div>
          </div>
        </div>

        <!-- 3 ฝ่าย (จาก Overview) — ตัวเลขจริงในระบบ — แสดงเสมอ -->
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

      <!-- Trend with day-range tabs + custom date range -->
      <div class="card p-4 lg:p-5 min-w-0 overflow-hidden">
        <div class="flex items-start justify-between gap-2 flex-wrap mb-2">
          <div class="min-w-0">
            <div class="font-semibold text-sm">แนวโน้มการลงทะเบียน</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
              ยอดสะสมรายวัน · เทียบเป้าหมาย
              <span v-if="trend.from && trend.to"> · {{ trend.from }} → {{ trend.to }}</span>
            </div>
          </div>
          <!-- Day range tabs -->
          <div class="flex bg-slate-100 dark:bg-slate-800/60 rounded-xl p-0.5 shrink-0 overflow-x-auto">
            <button v-for="t in trendTabs" :key="t.value" @click="reloadTrend(t.value)"
                    :class="['shrink-0 whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-medium transition',
                             trendDays === t.value
                               ? 'bg-white dark:bg-slate-900 shadow-sm text-blue-700 dark:text-blue-300'
                               : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200']">
              {{ t.label }}
            </button>
          </div>
        </div>

        <!-- Custom date range inputs — แสดงเฉพาะตอนเลือก 'กำหนดเอง' -->
        <div v-if="trendDays === 'custom'" class="card-tint-blue p-3 rounded-xl mb-3 flex items-center gap-2 flex-wrap text-xs">
          <i class="fi-rr-calendar text-blue-700"></i>
          <span class="font-medium">ช่วงวันที่:</span>
          <input v-model="customRange.from" type="date"
                 class="px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs">
          <span>ถึง</span>
          <input v-model="customRange.to" type="date"
                 class="px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs">
          <button @click="applyCustomRange" :disabled="!customRange.from || !customRange.to"
                  class="btn-primary px-3 py-1.5 text-xs flex items-center gap-1 disabled:opacity-50">
            <i class="fi-rr-check"></i> แสดงผล
          </button>
        </div>

        <div class="w-full overflow-hidden">
          <apexchart type="area" height="280" :options="trendOptions" :series="trend.series" />
        </div>
      </div>

      <!-- Status + Channel -->
      <div class="grid lg:grid-cols-2 gap-3">
        <div class="card p-4 lg:p-5 min-w-0 overflow-hidden">
          <div class="font-semibold text-sm">สัดส่วนสถานะการลงทะเบียน</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">8 กลุ่ม · รวม "ยังไม่ถูกติดตาม" ด้วย</div>
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

      <!-- TOP 5 สรุปยอด — สลับระดับ อำเภอ/ตำบล/หมู่บ้าน · breakdown 7 สถานะ -->
      <div class="card p-4 lg:p-5">
        <div class="flex items-start justify-between gap-2 flex-wrap mb-3">
          <div>
            <div class="font-semibold text-sm">TOP 5 สรุปยอด · เรียงตาม % ลงทะเบียน</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">แสดงสัดส่วน 7 สถานะการลงทะเบียน (4.1-4.7)</div>
          </div>
          <RouterLink to="/targets" class="text-xs text-blue-700 dark:text-blue-400 hover:underline shrink-0">ดูทั้งหมด →</RouterLink>
        </div>

        <!-- Level tabs -->
        <div class="flex bg-slate-100 dark:bg-slate-800/60 rounded-xl p-0.5 mb-3 w-full sm:w-fit">
          <button v-for="t in topTabs" :key="t.key" @click="selectTopLevel(t.key)"
                  :class="['flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-xs font-medium transition flex items-center justify-center gap-1.5',
                           topLevel === t.key
                             ? 'bg-white dark:bg-slate-900 shadow-sm text-blue-700 dark:text-blue-300'
                             : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200']">
            <i :class="t.icon"></i> {{ t.label }}
          </button>
        </div>

        <!-- Legend (status colors) — รวม "ยังไม่ถูกติดตาม" -->
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-slate-500 dark:text-slate-400 mb-3">
          <span class="flex items-center gap-1 whitespace-nowrap">
            <span class="w-2.5 h-2.5 rounded-sm" style="background:#cbd5e1"></span>
            — ยังไม่ถูกติดตาม
          </span>
          <span v-for="(c, code) in statusColorMap" :key="code" class="flex items-center gap-1 whitespace-nowrap">
            <span class="w-2.5 h-2.5 rounded-sm" :style="{ background: c }"></span>
            {{ code }} {{ STATUS_SHORT[code] }}
          </span>
        </div>

        <div v-if="topLoading" class="text-center py-8 text-slate-500"><i class="fi-rr-spinner animate-spin text-xl"></i></div>
        <div v-else-if="topData.length === 0" class="text-center text-sm text-slate-500 py-6">ไม่พบข้อมูล</div>

        <div v-else class="space-y-2">
          <div v-for="(row, idx) in topData" :key="row.id"
               class="border border-slate-100 dark:border-slate-800 rounded-xl p-3 hover:border-blue-200 dark:hover:border-slate-700 transition min-w-0">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-start gap-2.5 min-w-0 flex-1">
                <div :class="['w-7 h-7 shrink-0 rounded-lg text-white text-xs font-bold flex items-center justify-center',
                              idx < 3 ? 'bg-blue-700' : 'bg-slate-400']">
                  {{ idx + 1 }}
                </div>
                <div class="min-w-0 flex-1">
                  <div class="font-medium text-sm truncate">{{ row.name }}</div>
                  <div v-if="row.location" class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ row.location }}</div>
                </div>
              </div>
              <div class="text-right shrink-0">
                <div :class="['font-semibold text-sm', pctColor(row.pct)]">{{ row.pct }}%</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ formatNumber(row.done) }} / {{ formatNumber(row.total) }}</div>
              </div>
            </div>

            <!-- Stacked status bar -->
            <div class="mt-2 h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden flex">
              <div v-for="seg in statusSegments(row)" :key="seg.code"
                   :style="{ width: seg.width + '%', background: seg.color }"
                   :title="`${seg.label} · ${formatNumber(seg.count)} ราย`"
                   class="h-full first:rounded-l-full last:rounded-r-full"></div>
            </div>
            <!-- Inline status counts -->
            <div class="mt-1.5 flex flex-wrap gap-x-2 gap-y-0.5 text-[10px]">
              <span v-for="seg in statusSegments(row)" :key="seg.code"
                    class="flex items-center gap-1 text-slate-600 dark:text-slate-400 whitespace-nowrap">
                <span class="w-1.5 h-1.5 rounded-sm" :style="{ background: seg.color }"></span>
                {{ seg.label }} <b class="text-slate-800 dark:text-slate-200">{{ formatNumber(seg.count) }}</b>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- ภาพรวม carousel — 1 card/หน้า เลื่อนซ้าย-ขวา (อำเภอ ↔ ตำบล) -->
      <div class="relative">
        <div ref="overviewCarouselRef" @scroll.passive="onOverviewScroll"
             class="overview-carousel flex overflow-x-auto snap-x snap-mandatory gap-3 -mx-1 px-1 pb-1 scroll-smooth">
          <!-- Card: อำเภอ -->
          <div class="shrink-0 w-full snap-start">
            <div class="card p-4 lg:p-5 min-w-0 overflow-hidden">
              <div class="flex items-start justify-between mb-3 gap-2">
                <div class="min-w-0">
                  <div class="font-semibold text-sm flex items-center gap-1.5">
                    <i class="fi-rr-marker text-blue-700"></i> ภาพรวมทุกอำเภอ
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400">
                    {{ allAmphurs.length }} อำเภอ · เลื่อนภายในกราฟเพื่อดูทั้งหมด
                  </div>
                </div>
                <span class="text-[10px] px-2 py-1 rounded-full card-tint-blue whitespace-nowrap font-medium">1 / 2</span>
              </div>
              <div v-if="allLoading" class="text-center py-12 text-slate-500"><i class="fi-rr-spinner animate-spin text-2xl"></i></div>
              <div v-else-if="allAmphurs.length === 0" class="text-center text-sm text-slate-500 py-12">ไม่พบข้อมูล</div>
              <div v-else class="overflow-x-auto scroll-chart">
                <div :style="{ minWidth: Math.max(allAmphurs.length * 70, 400) + 'px' }">
                  <apexchart type="bar" height="360" :options="amphurChart.options" :series="amphurChart.series" />
                </div>
              </div>
            </div>
          </div>

          <!-- Card: ตำบล -->
          <div class="shrink-0 w-full snap-start">
            <div class="card p-4 lg:p-5 min-w-0 overflow-hidden">
              <div class="flex items-start justify-between mb-3 gap-2">
                <div class="min-w-0">
                  <div class="font-semibold text-sm flex items-center gap-1.5">
                    <i class="fi-rr-marker text-sky-700"></i> ภาพรวมทุกตำบล
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400">
                    {{ allTambons.length }} ตำบล · เลื่อนภายในกราฟเพื่อดูทั้งหมด
                  </div>
                </div>
                <span class="text-[10px] px-2 py-1 rounded-full card-tint-sky whitespace-nowrap font-medium">2 / 2</span>
              </div>
              <div v-if="allLoading" class="text-center py-12 text-slate-500"><i class="fi-rr-spinner animate-spin text-2xl"></i></div>
              <div v-else-if="allTambons.length === 0" class="text-center text-sm text-slate-500 py-12">ไม่พบข้อมูล</div>
              <div v-else class="overflow-x-auto scroll-chart">
                <div :style="{ minWidth: Math.max(allTambons.length * 60, 400) + 'px' }">
                  <apexchart type="bar" height="360" :options="tambonChart.options" :series="tambonChart.series" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Desktop: arrow buttons -->
        <button @click="goToOverview(0)" v-if="currentOverviewIdx > 0"
                class="hidden lg:flex absolute -left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white dark:bg-slate-800 shadow-lg items-center justify-center hover:scale-110 transition border border-slate-100 dark:border-slate-700 z-10">
          <i class="fi-rr-angle-left"></i>
        </button>
        <button @click="goToOverview(overviewCards.length - 1)" v-if="currentOverviewIdx < overviewCards.length - 1"
                class="hidden lg:flex absolute -right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white dark:bg-slate-800 shadow-lg items-center justify-center hover:scale-110 transition border border-slate-100 dark:border-slate-700 z-10">
          <i class="fi-rr-angle-right"></i>
        </button>

        <!-- Dot indicators + label -->
        <div class="flex justify-center items-center gap-3 mt-3">
          <button v-for="(c, i) in overviewCards" :key="c.key"
                  @click="goToOverview(i)"
                  :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs transition',
                           currentOverviewIdx === i
                             ? 'bg-blue-700 text-white shadow-md'
                             : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700']">
            <i :class="c.icon"></i> {{ c.label }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Scrollbar บางๆ สำหรับ chart ที่เลื่อนได้ */
.scroll-chart {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;
}
.scroll-chart::-webkit-scrollbar { height: 6px; }
.scroll-chart::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
.scroll-chart::-webkit-scrollbar-track { background: transparent; }
:global(.dark) .scroll-chart { scrollbar-color: #475569 transparent; }
:global(.dark) .scroll-chart::-webkit-scrollbar-thumb { background: #475569; }
</style>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Loader from '@/components/Loader.vue';
import Modal from '@/components/Modal.vue';
import { computed, onMounted, ref, reactive, watch } from 'vue';
import axios from 'axios';
import { useThemeStore } from '@/stores/theme';
import { useAuthStore } from '@/stores/auth';
import { formatNumber, STATUS_SHORT, statusShort, loadStatuses } from '@/composables/useApi';
import { DEFAULT_SOP_DETAILS, effectiveSopDetails } from '@/composables/sopDefaults';

const theme = useThemeStore();
const auth = useAuthStore();
const dark = computed(() => theme.isDark);

// Responsive chart height — มือถือเตี้ยลงเพื่อไม่ stack สูงเกิน
const isMobile = ref(false);
function updateIsMobile() { isMobile.value = window.matchMedia('(max-width: 1023px)').matches; }
onMounted(() => { updateIsMobile(); window.addEventListener('resize', updateIsMobile); });
const chartHeight = computed(() => isMobile.value ? 240 : 280);

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

// SOP details — ใช้ค่า default จาก composables/sopDefaults.js (shared กับ AdminSettings)
const sopDetails = DEFAULT_SOP_DETAILS;
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

// 4 ช่องทางการลงทะเบียน — สีต่อ Card ตาม code
// website=ฟ้า · paotang=น้ำเงิน · atm_ktb=ฟ้าเข้ม · bank=เขียว
const channelStyleMap = {
  website:  { tint: 'card-tint-sky',    iconBg: 'bg-sky-600',    iconText: 'text-white', accent: 'text-sky-700 dark:text-sky-300',   border: 'border-sky-200/60 dark:border-sky-900/40' },
  paotang:  { tint: 'card-tint-blue',   iconBg: 'bg-blue-700',   iconText: 'text-white', accent: 'text-blue-700 dark:text-blue-300', border: 'border-blue-200/60 dark:border-blue-900/40' },
  atm_ktb:  { tint: 'card-tint-orange', iconBg: 'bg-orange-500', iconText: 'text-white', accent: 'text-orange-700 dark:text-orange-300', border: 'border-orange-200/60 dark:border-orange-900/40' },
  bank:     { tint: 'card-tint-green',  iconBg: 'bg-green-600',  iconText: 'text-white', accent: 'text-green-700 dark:text-green-300', border: 'border-green-200/60 dark:border-green-900/40' },
};
function channelStyle(c) {
  return channelStyleMap[c.code] || channelStyleMap.website;
}

// 5 ธนาคาร — brand colors แบบจาง ๆ + ตัวอักษรย่อ (fallback ถ้าไม่มีไฟล์โลโก้)
// KTB=ฟ้ากรุงไทย · GSB=ชมพูออมสิน · BAAC=เขียว ธ.ก.ส. · GHB=ส้ม ธอส. · IBANK=เขียวเข้มอิสลาม
const bankStyleMap = {
  KTB:   { bg: 'bg-sky-50 dark:bg-sky-900/15 border-sky-200/60 dark:border-sky-900/30',         badge: 'bg-sky-600',     text: 'text-sky-800 dark:text-sky-200',     value: 'text-sky-900 dark:text-sky-100',     initial: 'KTB',  short: 'กรุงไทย' },
  GSB:   { bg: 'bg-pink-50 dark:bg-pink-900/15 border-pink-200/60 dark:border-pink-900/30',     badge: 'bg-pink-600',    text: 'text-pink-800 dark:text-pink-200',   value: 'text-pink-900 dark:text-pink-100',   initial: 'GSB',  short: 'ออมสิน' },
  BAAC:  { bg: 'bg-green-50 dark:bg-green-900/15 border-green-200/60 dark:border-green-900/30', badge: 'bg-green-600',   text: 'text-green-800 dark:text-green-200', value: 'text-green-900 dark:text-green-100', initial: 'ธกส',  short: 'ธ.ก.ส.' },
  GHB:   { bg: 'bg-orange-50 dark:bg-orange-900/15 border-orange-200/60 dark:border-orange-900/30', badge: 'bg-orange-500', text: 'text-orange-800 dark:text-orange-200', value: 'text-orange-900 dark:text-orange-100', initial: 'ธอส', short: 'อาคารสงเคราะห์' },
  IBANK: { bg: 'bg-emerald-50 dark:bg-emerald-900/15 border-emerald-200/60 dark:border-emerald-900/30', badge: 'bg-emerald-700', text: 'text-emerald-800 dark:text-emerald-200', value: 'text-emerald-900 dark:text-emerald-100', initial: 'IBANK', short: 'อิสลาม' },
};
const bankFallback = { bg: 'bg-slate-50 dark:bg-slate-800/40 border-slate-200/60 dark:border-slate-700', badge: 'bg-slate-500', text: 'text-slate-700 dark:text-slate-300', value: 'text-slate-900 dark:text-slate-100', initial: '?', short: '' };
function bankStyle(code) {
  if (!code) return bankFallback;
  return bankStyleMap[String(code).toUpperCase()] || bankFallback;
}
function bankLogoUrl(bank) {
  // bank อาจเป็น object (มี logo_url จาก API) หรือ string (code เก่า — backward compat)
  if (typeof bank === 'object' && bank) {
    if (bank.logo_url) return bank.logo_url;
    if (bank.code)     return `/img/banks/${String(bank.code).toLowerCase()}.png`;
    return null;
  }
  if (!bank) return null;
  return `/img/banks/${String(bank).toLowerCase()}.png`;
}
// per-image error state — กดผิดพลาดเมื่อไหร่ ก็ fallback เป็นตัวอักษรย่อ
const bankLogoFailed = reactive({});
function onLogoError(code) {
  bankLogoFailed[code] = true;
}

// Tint class + accent text + icon ต่อสถานะ (visual design — คงที่ตาม code)
// หมายเหตุ: label ไม่อยู่ใน map นี้แล้ว — ใช้ statusShort(code) ที่อ่านจาก DB แทน
//          (ผู้ใช้แก้ชื่อใน AdminSettings → ทุกหน้าเปลี่ยนตามทันที)
const statusTintMap = {
  '0':   { tint: 'bg-slate-50 border border-slate-200 dark:bg-slate-800/40 dark:border-slate-700', accent: 'text-slate-700 dark:text-slate-200', icon: 'fi-rr-question' },
  '4.1': { tint: 'bg-slate-50 border border-slate-200 dark:bg-slate-800/40 dark:border-slate-700', accent: 'text-slate-700 dark:text-slate-300', icon: 'fi-rr-ban' },
  '4.2': { tint: 'card-tint-blue',                                                                  accent: 'text-blue-700 dark:text-blue-300',   icon: 'fi-rr-edit' },
  '4.3': { tint: 'card-tint-orange',                                                                accent: 'text-orange-700 dark:text-orange-300', icon: 'fi-rr-folder' },
  '4.4': { tint: 'card-tint-orange',                                                                accent: 'text-orange-700 dark:text-orange-300', icon: 'fi-rr-paper-plane' },
  '4.5': { tint: 'card-tint-red',                                                                   accent: 'text-red-700 dark:text-red-300',     icon: 'fi-rr-triangle-warning' },
  '4.6': { tint: 'card-tint-sky',                                                                   accent: 'text-sky-700 dark:text-sky-300',     icon: 'fi-rr-fingerprint' },
  '4.7': { tint: 'card-tint-green',                                                                 accent: 'text-green-700 dark:text-green-300', icon: 'fi-rr-check-circle' },
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
  // OPTIMISTIC: เปลี่ยน UI ทันทีก่อน API ตอบ — สีและ section ใต้ SOP เปลี่ยนทันที
  const snapshot = phases.value.map(p => ({ ...p }));
  phases.value = phases.value.map(p => ({ ...p, is_current: p.id === phase.id }));
  try {
    const { data } = await axios.post(`/api/admin/phases/${phase.id}/set-current`);
    if (data?.phases) phases.value = data.phases;
  } catch (e) {
    phases.value = snapshot;
    alert('เกิดข้อผิดพลาด: ' + (e.response?.data?.message || e.message));
  }
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
  // ใช้ effectiveSopDetails — DB ก่อน · fallback default ตาม sop_level
  const d = effectiveSopDetails(phase);
  Object.assign(sopForm, {
    id: phase.id, name: phase.name || '',
    description: phase.description || '', sop_level: phase.sop_level || 1,
    detailsSummary: d.summary || '',
    detailsFooter:  d.footer  || '',
    detailsBullets: d.bullets.map(b => ({
      icon: b.icon || 'fi-rr-circle',
      text: b.text || '',
      subtitle: b.subtitle || '',
      count: b.count ?? null,
    })),
  });
  sopErr.value = {};
  showSopEdit.value = true;
}

function addBullet() {
  sopForm.detailsBullets.push({ icon: 'fi-rr-circle', text: '', subtitle: '', count: null });
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
      .map(b => {
        const out = { icon: b.icon || 'fi-rr-circle', text: b.text.trim() };
        if (b.subtitle && b.subtitle.trim()) out.subtitle = b.subtitle.trim();
        if (b.count !== null && b.count !== '' && !isNaN(Number(b.count))) out.count = Number(b.count);
        return out;
      });
    const details = (sopForm.detailsSummary || sopForm.detailsFooter || bullets.length)
      ? { summary: sopForm.detailsSummary || null, footer: sopForm.detailsFooter || null, bullets }
      : null;

    const payload = {
      name: sopForm.name,
      description: sopForm.description,
      sop_level: sopForm.sop_level,
      details,
    };
    // Optimistic update for edit
    if (sopForm.id) {
      phases.value = phases.value.map(p => p.id === sopForm.id
        ? { ...p, name: payload.name, description: payload.description, sop_level: payload.sop_level, details: payload.details }
        : p);
    }
    const { data } = sopForm.id
      ? await axios.patch(`/api/admin/phases/${sopForm.id}`, payload)
      : await axios.post('/api/admin/phases', payload);
    showSopEdit.value = false;
    if (data?.phases) phases.value = data.phases;
  } catch (e) {
    sopErr.value = e.response?.data?.errors || { general: [e.response?.data?.message || 'ผิดพลาด'] };
  } finally { sopSaving.value = false; }
}

async function deleteSopPhase(phase) {
  if (!auth.isSuperAdmin) return;
  if (phase.is_current) { alert('ลบขั้นที่เป็นปัจจุบันไม่ได้ — กรุณาตั้งขั้นอื่นเป็นปัจจุบันก่อน'); return; }
  if (!confirm(`ลบ "ชั้น ${phase.sop_level} — ${phase.name}" ?\nการลบไม่สามารถย้อนกลับได้`)) return;
  // optimistic delete
  const snapshot = phases.value.map(p => ({ ...p }));
  phases.value = phases.value.filter(p => p.id !== phase.id);
  try {
    const { data } = await axios.delete(`/api/admin/phases/${phase.id}`);
    if (data?.phases) phases.value = data.phases;
  } catch (e) {
    phases.value = snapshot;
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

// ─── Document Batch dashboard (Phase E) — โหลด lazy ใต้ SOP ───
const batchDashboard = ref(null);
async function loadBatchDashboard() {
  try {
    const { data } = await axios.get('/api/batches/dashboard');
    batchDashboard.value = data;
  } catch (e) {
    batchDashboard.value = null;
  }
}
function statusLabel(s) {
  return { submitted: 'รอ ธ.รับ', received: 'รอบันทึก' }[s] || s;
}

onMounted(async () => {
  loadStatuses();   // โหลดชื่อสถานะ dynamic จาก /api/ref/statuses (fire-and-forget)
  loadBatchDashboard();  // โหลด batch dashboard (fire-and-forget)
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
  // ลำดับสถานะ = '0' (ยังไม่ติดตาม) + สถานะปัจจุบันจาก DB (อ่าน /api/ref/statuses)
  const ord = ['0', ...Object.keys(STATUS_SHORT)];
  const baseColor = {
    '0':'#cbd5e1',   // slate-300 — อ่อนกว่า 4.1 เพื่อแยกชัด
    '4.1':'#94a3b8','4.2':'#2563eb','4.3':'#fb923c','4.4':'#f97316',
    '4.5':'#dc2626','4.6':'#0ea5e9','4.7':'#16a34a',
  };
  // สีสำรองสำหรับสถานะใหม่ที่ไม่มีใน baseColor
  const palette = ['#2563eb','#fb923c','#16a34a','#dc2626','#0ea5e9','#a855f7','#14b8a6','#eab308','#f97316','#64748b'];
  const labelMap = { '0': 'ยังไม่ถูกติดตาม', ...STATUS_SHORT };
  const labels = ord.map(c => labelMap[c] || c);
  const series = ord.map(c => Number(stats.value?.by_status?.[c] ?? 0));
  const colors = ord.map((c, i) => baseColor[c] || palette[i % palette.length]);
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
      { name: statusShort('4.1'),     data: rows.map(r => r.by_status?.['4.1'] || 0) },
      { name: 'กำลังลงทะเบียน',       data: rows.map(r => sumInProgress(r)) },
      { name: statusShort('4.7'),     data: rows.map(r => r.by_status?.['4.7'] || 0) },
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

// status KPI cards: '0' (ยังไม่ติดตาม) + ทุกสถานะที่อยู่ใน DB (เรียงตาม code)
// ❗ ไม่ hardcode 4.1-4.7 — admin ลบ/เพิ่มสถานะ → cards อัปเดตตาม
const DEFAULT_TINT = { tint: 'bg-slate-50 border border-slate-200 dark:bg-slate-800/40 dark:border-slate-700', accent: 'text-slate-700 dark:text-slate-300', icon: 'fi-rr-flag' };
const statusCards = computed(() => {
  const codes = ['0', ...Object.keys(STATUS_SHORT).sort()];
  const total = Math.max(stats.value?.total || 0, 1);
  return codes.map(code => {
    const count = Number(stats.value?.by_status?.[code] ?? 0);
    const meta = statusTintMap[code] || DEFAULT_TINT;
    return {
      code,
      label: code === '0' ? 'ยังไม่ถูกติดตาม' : statusShort(code),
      icon:  meta.icon,
      tint:  meta.tint,
      accent: meta.accent,
      dot:   statusColorMap[code] || '#94a3b8',
      count,
      pct: Math.round((count / total) * 1000) / 10,  // 1 decimal
    };
  });
});

// แตกแถบสีตาม by_status เป็น segments — order ตาม DB จริง (รวม ยังไม่ถูกติดตาม)
function statusSegments(row) {
  const order = ['0', ...Object.keys(STATUS_SHORT).sort()];
  const labelMap = { '0': 'ยังไม่ถูกติดตาม', ...STATUS_SHORT };
  const colorAll = { '0': '#cbd5e1', ...statusColorMap };
  const total = row.total || 1;
  return order.map(code => ({
    code,
    label: labelMap[code] || code,
    color: colorAll[code] || '#94a3b8',
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

      <!-- Filter — 3 dropdowns + refresh ปุ่มเดียว · แถวเดียวทุก breakpoint
           mobile: ปุ่มรีเฟรชเป็นไอคอน-only (square) · desktop: มี text "รีเฟรช" -->
      <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] gap-2 items-stretch">
        <div class="relative min-w-0">
          <select v-model="filters.amphur_id" @change="loadTambons"
                  class="w-full min-w-0 pl-2 sm:pl-3 pr-7 sm:pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <option value="">ทุกอำเภอ</option>
            <option v-for="a in amphurOpts" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <!-- ปุ่มล้างเลื่อนเข้าด้านในเพื่อไม่ทับ chevron บนมือถือ -->
          <button v-if="filters.amphur_id" @click="filters.amphur_id = ''; loadTambons()" title="ล้าง"
                  class="absolute right-6 sm:right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300 tap-transparent">
            <i class="fi-rr-cross-small text-[10px]"></i>
          </button>
        </div>
        <div class="relative min-w-0">
          <select v-model="filters.tambon_id" @change="loadVillages" :disabled="!filters.amphur_id"
                  class="w-full min-w-0 pl-2 sm:pl-3 pr-7 sm:pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm disabled:opacity-40">
            <option value="">ทุกตำบล</option>
            <option v-for="t in tambonOpts" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <button v-if="filters.tambon_id" @click="filters.tambon_id = ''; loadVillages()" title="ล้าง"
                  class="absolute right-6 sm:right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300 tap-transparent">
            <i class="fi-rr-cross-small text-[10px]"></i>
          </button>
        </div>
        <div class="relative min-w-0">
          <select v-model="filters.village_id" :disabled="!filters.tambon_id"
                  class="w-full min-w-0 pl-2 sm:pl-3 pr-7 sm:pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm disabled:opacity-40">
            <option value="">ทุกหมู่บ้าน</option>
            <option v-for="v in villageOpts" :key="v.id" :value="v.id">{{ v.name }}{{ v.moo ? ' (หมู่ '+v.moo+')' : '' }}</option>
          </select>
          <button v-if="filters.village_id" @click="filters.village_id = ''" title="ล้าง"
                  class="absolute right-6 sm:right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300 tap-transparent">
            <i class="fi-rr-cross-small text-[10px]"></i>
          </button>
        </div>
        <!-- รีเฟรช: mobile = ไอคอน square 44px · sm+ = ปุ่มมีข้อความ -->
        <button @click="loadAll" :disabled="refreshing"
                class="btn-primary px-3 sm:px-4 py-2.5 text-sm flex items-center justify-center gap-1.5 disabled:opacity-60 whitespace-nowrap tap-transparent shrink-0"
                title="รีเฟรชข้อมูล">
          <i :class="['fi-rr-refresh', refreshing && 'animate-spin']"></i>
          <span class="hidden sm:inline">รีเฟรช</span>
        </button>
      </div>

      <!-- KPI · ทุกสถานะการลงทะเบียน (ยังไม่ติดตาม + ทุก code ที่อยู่ใน DB) -->
      <div v-if="stats">
        <div class="flex items-baseline justify-between mb-3 px-1">
          <div class="text-sm font-semibold">สถานะการลงทะเบียน · ทั้งหมด {{ formatNumber(stats.total) }} คน</div>
          <div class="text-xs text-slate-500 dark:text-slate-400">
            <span v-if="stats.today_change > 0" class="text-green-600 dark:text-green-400">+{{ stats.today_change }} วันนี้</span>
            <span v-else>ยังไม่มีการอัปเดตวันนี้</span>
          </div>
        </div>
        <!-- auto-fit: ปรับ column count ตามจำนวน card อัตโนมัติ — ไม่เหลือช่องว่าง -->
        <div class="grid gap-3 sm:gap-4"
             :style="{ gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))' }">
          <div v-for="s in statusCards" :key="s.code"
               @click="s.code !== '0' && $router.push({ name: 'targets', query: { status: s.code } })"
               :class="[s.tint, 'rounded-2xl p-4 min-w-0 flex flex-col gap-2.5 transition tap-transparent',
                        s.code !== '0' && 'cursor-pointer hover:shadow-md hover:-translate-y-0.5 active:scale-[0.98]']">
            <!-- header: code + icon -->
            <div class="flex items-center justify-between gap-1">
              <span class="text-[11px] opacity-60 font-mono font-semibold tracking-wide whitespace-nowrap">{{ s.code === '0' ? '—' : s.code }}</span>
              <i :class="[s.icon, s.accent, 'text-base']"></i>
            </div>
            <!-- label: 2 บรรทัดเสมอ ให้ทุก card สูงเท่ากัน -->
            <div class="text-xs leading-snug opacity-80 line-clamp-2 min-h-[2.4em]" :title="s.label">{{ s.label }}</div>
            <!-- count -->
            <div :class="['text-2xl font-bold leading-none tabular-nums', s.accent]">{{ formatNumber(s.count) }}</div>
            <!-- footer: % + bar -->
            <div class="mt-auto pt-1">
              <div class="flex items-center justify-between text-[11px] opacity-70 tabular-nums mb-1">
                <span>{{ s.pct }}%</span>
                <span>{{ formatNumber(s.count) }}/{{ formatNumber(stats.total) }}</span>
              </div>
              <div class="h-1.5 rounded-full bg-white/50 dark:bg-slate-900/40 overflow-hidden">
                <div class="h-full rounded-full transition-all" :style="{ width: Math.min(s.pct, 100) + '%', background: s.dot }"></div>
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
                  <button @click.stop="openSopEdit(p)" class="btn-icon hover:bg-white/60 dark:hover:bg-slate-700/60 text-slate-600 dark:text-slate-300 tap-transparent" title="แก้ไข">
                    <i class="fi-rr-edit text-xs"></i>
                  </button>
                  <button @click.stop="deleteSopPhase(p)" :disabled="p.is_current"
                          class="btn-icon hover:bg-red-50 dark:hover:bg-red-900/30 text-red-600 disabled:opacity-30 disabled:cursor-not-allowed tap-transparent" title="ลบ">
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
          <button @click="showSopEdit = false" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800 tap-transparent" title="ปิด"><i class="fi-rr-cross-small"></i></button>
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
                   class="border border-slate-100 dark:border-slate-700 rounded-lg p-2 mb-2 space-y-1.5">
                <div class="flex items-center gap-1.5">
                  <select v-model="b.icon"
                          class="px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs w-32 shrink-0">
                    <option v-for="opt in ICON_OPTIONS" :key="opt.i" :value="opt.i">{{ opt.l }}</option>
                  </select>
                  <i :class="[b.icon, 'text-base text-slate-600 dark:text-slate-300 shrink-0 w-5 text-center']"></i>
                  <input v-model="b.text" maxlength="300" placeholder="ข้อความหัวข้อ (เช่น ตรวจสอบสิทธิ์)"
                         class="flex-1 min-w-0 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs">
                  <button type="button" @click="moveBullet(i, -1)" :disabled="i === 0" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30 tap-transparent" title="เลื่อนขึ้น">
                    <i class="fi-rr-angle-up text-xs"></i>
                  </button>
                  <button type="button" @click="moveBullet(i, 1)" :disabled="i === sopForm.detailsBullets.length - 1" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-30 tap-transparent" title="เลื่อนลง">
                    <i class="fi-rr-angle-down text-xs"></i>
                  </button>
                  <button type="button" @click="removeBullet(i)" class="btn-icon hover:bg-red-50 dark:hover:bg-red-900/30 text-red-600 tap-transparent" title="ลบ">
                    <i class="fi-rr-cross-small text-xs"></i>
                  </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 sm:pl-[8.5rem] sm:pr-[6rem]">
                  <input v-model="b.subtitle" maxlength="200" placeholder="หมายเหตุย่อย (เช่น กรมบัญชีกลาง) — ไม่บังคับ"
                         class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-[11px] text-slate-500">
                  <input v-model="b.count" type="number" min="0" placeholder="จำนวน (ไม่บังคับ)"
                         class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-[11px]">
                </div>
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

      <!-- ใต้ SOP — แสดง bullets ของขั้นปัจจุบัน (ถ้ามี count แสดงเลขด้วย) -->
      <div v-if="phases.find(p => p.is_current)?.details?.bullets?.length">
        <div class="flex items-end justify-between mb-2 flex-wrap gap-2">
          <div class="text-sm font-semibold">
            ชั้น {{ phases.find(p => p.is_current).sop_level }} —
            {{ phases.find(p => p.is_current).name }}
          </div>
          <div v-if="phases.find(p => p.is_current)?.details?.summary || phases.find(p => p.is_current)?.description"
               class="text-[11px] text-slate-500 dark:text-slate-400 flex-1 sm:text-right">
            {{ phases.find(p => p.is_current)?.details?.summary || phases.find(p => p.is_current)?.description }}
          </div>
        </div>

        <!-- บังคับให้อยู่ในแถวเดียว · ถ้ารวมแล้วเกิน จะ scroll แนวนอน · มี fade mask 2 ฝั่งเป็น hint -->
        <div class="flex gap-3 overflow-x-auto -mx-1 px-1 pb-2 snap-x scroll-chart fade-x">
          <div v-for="(b, i) in phases.find(p => p.is_current).details.bullets" :key="i"
               class="card-tint-blue p-4 shrink-0 snap-start w-[260px] sm:flex-1 sm:min-w-[260px]">
            <div class="flex items-center gap-3 mb-2">
              <div class="w-10 h-10 rounded-xl bg-blue-700 text-white flex items-center justify-center shrink-0">
                <i :class="b.icon || 'fi-rr-circle'"></i>
              </div>
              <div class="min-w-0">
                <div class="font-medium text-sm leading-snug">{{ b.text }}</div>
                <div v-if="b.subtitle" class="text-[11px] opacity-70 truncate">{{ b.subtitle }}</div>
              </div>
            </div>
            <!-- ตัวเลข — แสดงเฉพาะเมื่อมี count -->
            <div v-if="b.count != null && b.count !== ''"
                 class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">
              {{ formatNumber(b.count) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state: ยังไม่ตั้งขั้นปัจจุบัน หรือยังไม่มี bullets -->
      <div v-else-if="phases.length && !phases.find(p => p.is_current)"
           class="card-tint-orange p-4 text-sm flex items-center gap-2">
        <i class="fi-rr-info text-orange-700"></i>
        ยังไม่ได้ตั้งขั้นปัจจุบัน — กดปุ่ม "ตั้งเป็นขั้นปัจจุบัน" บนการ์ดข้างต้น
      </div>

      <!-- ─────────────────────────────────────────────────────── -->
      <!-- 📦 Document Batch Dashboard (Phase E)                   -->
      <!--    KPI 5 ใบ + Bottleneck > 3 วัน + Bank/Tracker leaderboard -->
      <!-- ─────────────────────────────────────────────────────── -->
      <section v-if="batchDashboard" class="space-y-3">
        <div class="flex items-baseline justify-between gap-2 mb-1 px-1">
          <div>
            <div class="font-semibold text-sm flex items-center gap-2">
              <i class="fi-rr-box-alt text-amber-600"></i>
              Document Batch · กระบวนการส่ง → รับ → บันทึก
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">รอบส่งเอกสารลงทะเบียนผ่าน tracker → ธนาคาร</div>
          </div>
          <RouterLink :to="{ name: 'batches' }" class="text-xs text-blue-700 dark:text-blue-300 hover:underline whitespace-nowrap">
            ดูทั้งหมด <i class="fi-rr-angle-small-right"></i>
          </RouterLink>
        </div>

        <!-- KPI 5 ใบ — auto-fit เหมือน status cards -->
        <div class="grid gap-3" :style="{ gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))' }">
          <div class="card-tint-blue rounded-2xl p-4">
            <div class="flex items-center justify-between mb-1.5"><span class="text-[11px] opacity-70 font-semibold">📤 ส่งวันนี้</span><i class="fi-rr-paper-plane text-blue-700"></i></div>
            <div class="text-2xl font-bold tabular-nums text-blue-700 dark:text-blue-300">{{ formatNumber(batchDashboard.kpi.submitted_today) }}</div>
            <div class="text-[11px] opacity-70 mt-1">batch</div>
          </div>
          <div class="card-tint-orange rounded-2xl p-4">
            <div class="flex items-center justify-between mb-1.5"><span class="text-[11px] opacity-70 font-semibold">⏳ รอ ธ.รับ</span><i class="fi-rr-inbox-out text-orange-700"></i></div>
            <div class="text-2xl font-bold tabular-nums text-orange-700 dark:text-orange-300">{{ formatNumber(batchDashboard.kpi.pending_receive) }}</div>
            <div class="text-[11px] opacity-70 mt-1">batch</div>
          </div>
          <div class="card-tint-sky rounded-2xl p-4">
            <div class="flex items-center justify-between mb-1.5"><span class="text-[11px] opacity-70 font-semibold">📥 รอบันทึก</span><i class="fi-rr-inbox-in text-sky-700"></i></div>
            <div class="text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ formatNumber(batchDashboard.kpi.pending_record) }}</div>
            <div class="text-[11px] opacity-70 mt-1">batch</div>
          </div>
          <div class="card-tint-green rounded-2xl p-4">
            <div class="flex items-center justify-between mb-1.5"><span class="text-[11px] opacity-70 font-semibold">✅ บันทึกวันนี้</span><i class="fi-rr-check-double text-green-700"></i></div>
            <div class="text-2xl font-bold tabular-nums text-green-700 dark:text-green-300">{{ formatNumber(batchDashboard.kpi.recorded_today) }}</div>
            <div class="text-[11px] opacity-70 mt-1">batch</div>
          </div>
          <div class="card-tint-red rounded-2xl p-4">
            <div class="flex items-center justify-between mb-1.5"><span class="text-[11px] opacity-70 font-semibold">⛔ ปฏิเสธวันนี้</span><i class="fi-rr-cross-circle text-red-700"></i></div>
            <div class="text-2xl font-bold tabular-nums text-red-700 dark:text-red-300">{{ formatNumber(batchDashboard.kpi.rejected_today) }}</div>
            <div class="text-[11px] opacity-70 mt-1">batch</div>
          </div>
        </div>

        <!-- 3-column grid: Bottleneck · Bank · Tracker — mobile stack, tablet 2col, lg 3col -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">

          <!-- ⏰ Bottleneck (col-span 1 บน mobile, 1 บน lg) -->
          <div class="card p-4 lg:col-span-1">
            <div class="flex items-center justify-between mb-3">
              <div class="font-semibold text-sm flex items-center gap-1.5">
                <i class="fi-rr-time-past text-red-600"></i> คอขวด > 3 วัน
              </div>
              <span class="text-xs text-slate-500 dark:text-slate-400 tabular-nums">{{ batchDashboard.bottleneck.length }} batch</span>
            </div>
            <div v-if="batchDashboard.bottleneck.length === 0" class="text-sm text-slate-400 dark:text-slate-500 text-center py-6">
              <i class="fi-rr-check-circle text-2xl text-green-500 mb-2 block"></i>
              ไม่มี batch ค้าง — ทุก batch อยู่ในกระบวนการปกติ ✨
            </div>
            <ul v-else class="space-y-2 max-h-72 overflow-y-auto">
              <li v-for="b in batchDashboard.bottleneck" :key="b.id"
                  class="flex items-start gap-2 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/40 cursor-pointer"
                  @click="$router.push({ name: 'batch-detail', params: { id: b.id } })">
                <div class="w-9 h-9 shrink-0 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 flex items-center justify-center text-xs font-bold">
                  {{ b.days_stuck }}d
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-xs font-mono font-semibold">#{{ b.batch_no }}</div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                    {{ b.targets_count }} ราย · {{ b.sub_channel }} · {{ statusLabel(b.status) }}
                  </div>
                </div>
              </li>
            </ul>
          </div>

          <!-- 🏆 Bank leaderboard -->
          <div class="card p-4 lg:col-span-1">
            <div class="flex items-center justify-between mb-3">
              <div class="font-semibold text-sm flex items-center gap-1.5">
                <i class="fi-rr-trophy text-amber-500"></i> ธนาคารเร็วสุด (ส่ง→บันทึก)
              </div>
              <span class="text-xs text-slate-500 dark:text-slate-400">TOP 5</span>
            </div>
            <div v-if="batchDashboard.bank_leaderboard.length === 0" class="text-sm text-slate-400 text-center py-6">
              ยังไม่มี batch ที่บันทึกครบ
            </div>
            <ul v-else class="space-y-2">
              <li v-for="(r, i) in batchDashboard.bank_leaderboard" :key="r.sub_channel"
                  class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <div :class="['w-7 h-7 shrink-0 rounded-full text-white text-xs font-bold flex items-center justify-center',
                              i === 0 ? 'bg-amber-500' : i === 1 ? 'bg-slate-400' : i === 2 ? 'bg-amber-700' : 'bg-slate-300']">
                  {{ i + 1 }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium truncate">{{ r.bank_name }}</div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ r.total_batches }} batch บันทึกแล้ว</div>
                </div>
                <div class="text-right">
                  <div class="text-sm font-bold tabular-nums text-green-600">{{ r.avg_days }} <span class="text-[10px] opacity-70">วัน</span></div>
                </div>
              </li>
            </ul>
          </div>

          <!-- 👥 Tracker leaderboard -->
          <div class="card p-4 lg:col-span-1">
            <div class="flex items-center justify-between mb-3">
              <div class="font-semibold text-sm flex items-center gap-1.5">
                <i class="fi-rr-user-headset text-blue-600"></i> Tracker ขยันสุด (7 วัน)
              </div>
              <span class="text-xs text-slate-500 dark:text-slate-400">TOP 5</span>
            </div>
            <div v-if="batchDashboard.tracker_leaderboard.length === 0" class="text-sm text-slate-400 text-center py-6">
              ยังไม่มีการส่ง batch ใน 7 วันที่ผ่านมา
            </div>
            <ul v-else class="space-y-2">
              <li v-for="(r, i) in batchDashboard.tracker_leaderboard" :key="r.tracker_id"
                  class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <div :class="['w-7 h-7 shrink-0 rounded-full text-white text-xs font-bold flex items-center justify-center',
                              i === 0 ? 'bg-amber-500' : i === 1 ? 'bg-slate-400' : i === 2 ? 'bg-amber-700' : 'bg-slate-300']">
                  {{ i + 1 }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium truncate">{{ r.tracker_name }}</div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ r.position }}</div>
                </div>
                <div class="text-right shrink-0">
                  <div class="text-sm font-bold tabular-nums">{{ r.batch_count }}</div>
                  <div class="text-[10px] text-slate-500 dark:text-slate-400">{{ formatNumber(r.target_count) }} ราย</div>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </section>

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

      <!-- Status + Channel · mobile ลด height ลง · lg เพิ่ม -->
      <div class="grid lg:grid-cols-2 gap-3">
        <div class="card p-4 lg:p-5 min-w-0 overflow-hidden">
          <div class="font-semibold text-sm">สัดส่วนสถานะการลงทะเบียน</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">8 กลุ่ม · รวม "ยังไม่ถูกติดตาม" ด้วย</div>
          <div class="w-full overflow-hidden">
            <apexchart type="donut" :height="chartHeight" :options="statusOptions.options" :series="statusOptions.series" />
          </div>
        </div>
        <div class="card p-4 lg:p-5 min-w-0 overflow-hidden">
          <div class="font-semibold text-sm">ช่องทางการลงทะเบียน</div>
          <div class="text-xs text-slate-500 dark:text-slate-400 mb-2">4 ช่องทางหลัก</div>
          <div class="w-full overflow-hidden">
            <apexchart type="bar" :height="chartHeight" :options="channelOptions.options" :series="channelOptions.series" />
          </div>
        </div>
      </div>

      <!-- 4 ช่องทาง + 5 ธนาคาร (จาก Overview) -->
      <div v-if="channelsRef.length" class="card p-5">
        <div class="font-semibold">4 ช่องทางการลงทะเบียน</div>
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-4">ผู้เข้าระบบเลือกได้ตามความสะดวก</div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div v-for="c in channelsRef" :key="c.id"
               :class="[channelStyle(c).tint, channelStyle(c).border, 'border rounded-2xl p-4 text-center transition hover:-translate-y-0.5 hover:shadow-md']">
            <div :class="['w-12 h-12 mx-auto rounded-xl flex items-center justify-center text-xl mb-2 shadow-sm',
                          channelStyle(c).iconBg, channelStyle(c).iconText]">
              <i :class="c.icon || 'fi-rr-circle'"></i>
            </div>
            <div :class="['font-medium text-sm', channelStyle(c).accent]">{{ c.name }}</div>
            <div :class="['text-xl font-bold mt-1', channelStyle(c).accent]">{{ formatNumber(channelCountMap[c.name] || 0) }}</div>
          </div>
        </div>
      </div>

      <!-- 5 ธนาคาร (จาก Overview) — สี Card จาง ๆ ตาม brand + โลโก้ธนาคาร -->
      <div v-if="overview?.by_bank?.length" class="card p-5">
        <div class="font-semibold flex items-center gap-2">
          <i class="fi-rr-bank text-green-700"></i> เจาะลึกช่องทางธนาคาร · 5 แห่ง
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-4">
          รวม {{ formatNumber(overview.by_bank.reduce((a,b) => a + b.count, 0)) }} รายการที่เลือกธนาคาร
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <div v-for="b in overview.by_bank" :key="b.code"
               :class="[bankStyle(b.code).bg, 'border rounded-2xl p-3 text-center transition hover:-translate-y-0.5 hover:shadow-md']">
            <!-- Logo (ภาพจริง /img/banks/{code}.png ถ้ามี · fallback เป็น badge ตัวอักษรย่อสี brand) -->
            <div class="w-12 h-12 mx-auto mb-2 rounded-xl bg-white dark:bg-slate-900 shadow-sm border border-white/60 dark:border-slate-700 flex items-center justify-center overflow-hidden">
              <img v-if="!bankLogoFailed[b.code]"
                   :src="bankLogoUrl(b)"
                   :alt="b.name"
                   @error="onLogoError(b.code)"
                   class="w-full h-full object-contain p-1.5" />
              <div v-else
                   :class="[bankStyle(b.code).badge, 'w-full h-full rounded-xl flex items-center justify-center text-white font-bold text-[11px] tracking-tight']">
                {{ bankStyle(b.code).initial }}
              </div>
            </div>
            <div :class="['text-[13px] font-medium leading-tight line-clamp-2 min-h-[2.4em]', bankStyle(b.code).text]">{{ b.name }}</div>
            <div :class="['text-lg font-bold mt-1 tabular-nums', bankStyle(b.code).value]">{{ formatNumber(b.count) }}</div>
          </div>
        </div>
        <!-- คำใบ้: วางไฟล์โลโก้ที่ public/img/banks/ktb.png, gsb.png, baac.png, ghb.png, ibank.png ระบบจะใช้รูปแทน badge -->
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
                  :class="['flex-1 sm:flex-none px-3 sm:px-4 py-2 rounded-lg text-sm font-medium transition flex items-center justify-center gap-1.5 tap-transparent min-h-[36px]',
                           topLevel === t.key
                             ? 'bg-white dark:bg-slate-900 shadow-sm text-blue-700 dark:text-blue-300'
                             : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200']">
            <i :class="[t.icon, 'hidden sm:inline-block']"></i> {{ t.label }}
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

        <Loader v-if="topLoading" label="" py="py-8" :size="36" />
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
              <Loader v-if="allLoading" label="" py="py-12" :size="40" />
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
              <Loader v-if="allLoading" label="" py="py-12" :size="40" />
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

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Loader from '@/components/Loader.vue';
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { formatNumber } from '@/composables/useApi';
import { useAuthStore } from '@/stores/auth';

// พิกัดศูนย์กลาง 32 อำเภอ จ.นครราชสีมา (โดยประมาณ) — key ต้องตรงชื่อในไฟล์ Excel
const AMPHUR_COORDS = {
  'เมืองนครราชสีมา': [14.9799, 102.0978], 'ครบุรี': [14.5333, 102.2667], 'เสิงสาง': [14.4167, 102.5167],
  'คง': [15.4167, 102.3000], 'บ้านเหลื่อม': [15.5333, 102.1333], 'จักราช': [14.9833, 102.4167],
  'โชคชัย': [14.7333, 102.1667], 'ด่านขุนทด': [15.1833, 101.7333], 'โนนไทย': [15.2167, 101.9667],
  'โนนสูง': [15.1833, 102.2500], 'ขามสะแกแสง': [15.3500, 102.1000], 'บัวใหญ่': [15.5833, 102.4333],
  'ประทาย': [15.5667, 102.7000], 'ปักธงชัย': [14.7000, 101.9333], 'พิมาย': [15.2167, 102.4833],
  'ห้วยแถลง': [15.0333, 102.6333], 'ชุมพวง': [15.3000, 102.8333], 'สูงเนิน': [14.9000, 101.8333],
  'ขามทะเลสอ': [15.0167, 101.9167], 'สีคิ้ว': [14.8833, 101.7167], 'ปากช่อง': [14.7000, 101.4167],
  'หนองบุญมาก': [14.7167, 102.4000], 'แก้งสนามนาง': [15.6500, 102.2333], 'โนนแดง': [15.4000, 102.5833],
  'วังน้ำเขียว': [14.4500, 101.8333], 'เทพารักษ์': [15.3667, 101.6500], 'เมืองยาง': [15.4500, 102.8500],
  'พระทองคำ': [15.3333, 101.8500], 'ลำทะเมนชัย': [15.3333, 102.9167], 'บัวลาย': [15.6833, 102.4333],
  'สีดา': [15.6000, 102.5333], 'เฉลิมพระเกียรติ': [14.8833, 102.2667],
};
function colorForCount(v) {
  return v >= 3000 ? '#991b1b' : v >= 2000 ? '#dc2626' : v >= 1000 ? '#f97316'
       : v >= 500 ? '#f59e0b' : v >= 100 ? '#facc15' : '#86efac';
}

const auth = useAuthStore();
const isSuper = computed(() => auth.roles.includes('super_admin'));

const loading = ref(true);
const summary = ref(null);
const amphurRows = ref([]);
const tambonRows = ref([]);
const imports = ref([]);

const dark = computed(() => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

async function loadAll() {
  loading.value = true;
  try {
    const [s, a, t] = await Promise.all([
      axios.get('/api/missed-targets/summary'),
      axios.get('/api/missed-targets', { params: { level: 'amphur' } }),
      axios.get('/api/missed-targets', { params: { level: 'tambon' } }),
    ]);
    summary.value = s.data;
    amphurRows.value = a.data.data;
    tambonRows.value = t.data.data;
  } finally {
    loading.value = false;
  }
  if (isSuper.value) loadImports();
  await nextTick();
  initMap();
}
async function loadImports() {
  try {
    const { data } = await axios.get('/api/missed-targets/imports');
    imports.value = data.data;
  } catch { /* ignore */ }
}

// ─── แผนที่จังหวัด (Leaflet bubble map) ───
const mapEl = ref(null);
let map = null;
let markerLayer = null;
function initMap() {
  if (!mapEl.value || map) {  // ถ้ามีแล้ว แค่ render ใหม่
    if (map) renderMarkers();
    return;
  }
  map = L.map(mapEl.value, { scrollWheelZoom: false, attributionControl: true }).setView([15.05, 102.1], 8);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 16, attribution: '© OpenStreetMap',
  }).addTo(map);
  renderMarkers();
}
function renderMarkers() {
  if (!map) return;
  if (markerLayer) markerLayer.remove();
  markerLayer = L.layerGroup().addTo(map);
  const rows = summary.value?.by_amphur || [];
  const bounds = [];
  for (const r of rows) {
    const co = AMPHUR_COORDS[r.amphur_name];
    if (!co) continue;
    bounds.push(co);
    const color = colorForCount(r.cnt_total);
    const html = `<div style="background:${color};color:#fff;font-family:Prompt,sans-serif;font-weight:700;
      font-size:11px;padding:3px 7px;border-radius:9px;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.3);
      white-space:nowrap;text-align:center;line-height:1.1;">
      ${formatNumber(r.cnt_total)}<div style="font-size:8px;font-weight:500;opacity:.95">${r.amphur_name}</div></div>`;
    const icon = L.divIcon({ html, className: 'mt-badge', iconSize: null });
    L.marker(co, { icon })
      .bindTooltip(`<b>${r.amphur_name}</b><br>เปราะบาง: ${formatNumber(r.cnt_vulnerable)}<br>จปฐ.: ${formatNumber(r.cnt_jpt)}<br>3 กลุ่ม: ${formatNumber(r.cnt_both)}<br><b>รวม: ${formatNumber(r.cnt_total)}</b>`, { direction: 'top' })
      .addTo(markerLayer);
  }
  if (bounds.length) map.fitBounds(bounds, { padding: [30, 30] });
}

onMounted(loadAll);
onBeforeUnmount(() => { if (map) { map.remove(); map = null; } });

// ─── KPI ───
const kpi = computed(() => summary.value?.province_total || { jpt: 0, vulnerable: 0, both: 0, total: 0 });

// ─── Bar chart: ทุกอำเภอ (เลื่อนแนวนอน) ───
const amphurChart = computed(() => {
  const rows = summary.value?.by_amphur || [];
  return {
    series: [
      { name: 'กลุ่มเปราะบาง', data: rows.map(r => r.cnt_vulnerable) },
      { name: 'ตกเกณฑ์ จปฐ.', data: rows.map(r => r.cnt_jpt) },
      { name: 'ทั้ง 3 กลุ่ม', data: rows.map(r => r.cnt_both) },
    ],
    options: {
      chart: { type: 'bar', stacked: true, height: 360, fontFamily: 'Prompt, sans-serif', toolbar: { show: false }, zoom: { enabled: false } },
      colors: ['#2563eb', '#f59e0b', '#10b981'],
      plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
      dataLabels: { enabled: false },
      xaxis: { categories: rows.map(r => r.amphur_name), labels: { rotate: -55, style: { fontSize: '10px' } }, axisTicks: { show: false } },
      yaxis: { labels: { formatter: v => formatNumber(Math.round(v)) } },
      legend: { position: 'top', fontSize: '12px' },
      grid: { borderColor: dark.value ? '#1e293b' : '#f1f5f9' },
      tooltip: { y: { formatter: v => formatNumber(v) + ' คน' } },
      theme: { mode: dark.value ? 'dark' : 'light' },
    },
  };
});

// ─── Donut: สัดส่วน 3 กลุ่ม ───
const donut = computed(() => ({
  series: [kpi.value.vulnerable, kpi.value.jpt, kpi.value.both],
  options: {
    chart: { type: 'donut', height: 300, fontFamily: 'Prompt, sans-serif', zoom: { enabled: false } },
    labels: ['กลุ่มเปราะบาง + ไม่มีบัตร', 'ตกเกณฑ์ จปฐ. + ไม่มีบัตร', 'ทั้ง 3 กลุ่ม + ไม่มีบัตร'],
    colors: ['#2563eb', '#f59e0b', '#10b981'],
    legend: { position: 'bottom', fontSize: '11px' },
    dataLabels: { enabled: true, formatter: (v) => v.toFixed(1) + '%' },
    stroke: { width: 2, colors: [dark.value ? '#0f172a' : '#ffffff'] },
    plotOptions: { pie: { donut: { size: '66%', labels: { show: true,
      value: { show: true, fontSize: '20px', fontWeight: 700, formatter: v => formatNumber(v) },
      total: { show: true, label: 'รวม', fontSize: '12px', formatter: () => formatNumber(kpi.value.total) },
    } } } },
    theme: { mode: dark.value ? 'dark' : 'light' },
  },
}));

// ─── Swipeable cards ───
const cardIndex = ref(0);
const cards = computed(() => [
  { key: 'amphur', title: 'รายอำเภอ', icon: 'fi-rr-building', rows: amphurRows.value, hasTambon: false },
  { key: 'tambon', title: 'รายตำบล', icon: 'fi-rr-marker', rows: tambonRows.value, hasTambon: true },
]);

// ─── Upload (super_admin) ───
const uploadFile = ref(null);
const uploadNote = ref('');
const uploading = ref(false);
const uploadMsg = ref('');
const uploadErr = ref('');
function onFile(e) { uploadFile.value = e.target.files?.[0] || null; }
async function doUpload() {
  if (!uploadFile.value) { uploadErr.value = 'กรุณาเลือกไฟล์ Excel'; return; }
  uploading.value = true; uploadErr.value = ''; uploadMsg.value = '';
  try {
    const fd = new FormData();
    fd.append('file', uploadFile.value);
    if (uploadNote.value) fd.append('note', uploadNote.value);
    const { data } = await axios.post('/api/missed-targets/upload', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
    uploadMsg.value = data.message;
    uploadFile.value = null; uploadNote.value = '';
    document.getElementById('mt-file-input').value = '';
    await loadAll();
  } catch (e) {
    uploadErr.value = e.response?.data?.message || 'อัปโหลดไม่สำเร็จ';
  } finally {
    uploading.value = false;
  }
}

function exportLevel(level) {
  window.location.href = '/api/missed-targets/export?level=' + level;
}

// ── ลบข้อมูล (เฉพาะ super_admin) ───────────────────────────────
async function deleteOneStat(r) {
  const label = r.tambon_name ? `${r.amphur_name} / ${r.tambon_name}` : r.amphur_name;
  if (!confirm(`ลบรายการ "${label}" ?\n\nย้อนกลับไม่ได้`)) return;
  try {
    await axios.delete(`/api/missed-targets/${r.id}`);
    await loadAll();
  } catch (e) {
    alert('ลบไม่สำเร็จ: ' + (e.response?.data?.message || e.message));
  }
}

async function deleteAllMissed() {
  if (!confirm('⚠️ ลบข้อมูลกลุ่มเป้าหมายผู้ตกหล่น "ทั้งหมด" ?\n\nข้อมูลสถิติและประวัติการนำเข้าจะถูกลบถาวร ย้อนกลับไม่ได้')) return;
  if (!confirm('ยืนยันอีกครั้ง — ต้องการลบทั้งหมดจริงหรือไม่?')) return;
  try {
    const { data: r } = await axios.delete('/api/missed-targets');
    await loadAll();
    await loadImports();
    alert(r.message || 'ลบทั้งหมดแล้ว');
  } catch (e) {
    alert('ลบไม่สำเร็จ: ' + (e.response?.data?.message || e.message));
  }
}

function sumRows(rows) {
  return rows.reduce((a, r) => ({
    vuln:  a.vuln  + (r.cnt_vulnerable || 0),
    jpt:   a.jpt   + (r.cnt_jpt || 0),
    both:  a.both  + (r.cnt_both || 0),
    total: a.total + (r.cnt_total || 0),
  }), { vuln: 0, jpt: 0, both: 0, total: 0 });
}

function fmtDate(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleString('th-TH', { day: '2-digit', month: 'short', year: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
  <AppLayout greeting="ภาพรวมจังหวัดนครราชสีมา" title="กลุ่มเป้าหมายผู้ตกหล่นไม่มีบัตร">
    <div class="space-y-4">

      <Loader v-if="loading" label="กำลังโหลดข้อมูลผู้ตกหล่น..." py="py-24" />

      <template v-else>
        <!-- ═══ SECTION 1 · Charts + KPI ═══ -->
        <div class="card-hero p-4 lg:p-5">
          <div class="text-xs opacity-80 mb-2 flex items-center gap-1.5"><i class="fi-rr-chart-histogram"></i> ผู้ตกหล่นที่ยังไม่มีบัตรสวัสดิการแห่งรัฐ</div>
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
            <div class="bg-white/15 rounded-xl p-3"><div class="text-[11px] opacity-80">รวมทั้งจังหวัด</div><div class="text-2xl font-bold tabular-nums mt-0.5">{{ formatNumber(kpi.total) }}</div></div>
            <div class="bg-white/15 rounded-xl p-3"><div class="text-[11px] opacity-80">กลุ่มเปราะบาง</div><div class="text-2xl font-bold tabular-nums mt-0.5">{{ formatNumber(kpi.vulnerable) }}</div></div>
            <div class="bg-white/15 rounded-xl p-3"><div class="text-[11px] opacity-80">ตกเกณฑ์ จปฐ.</div><div class="text-2xl font-bold tabular-nums mt-0.5">{{ formatNumber(kpi.jpt) }}</div></div>
            <div class="bg-white/15 rounded-xl p-3"><div class="text-[11px] opacity-80">ทั้ง 3 กลุ่ม</div><div class="text-2xl font-bold tabular-nums mt-0.5">{{ formatNumber(kpi.both) }}</div></div>
          </div>
          <div v-if="summary?.last_import" class="text-[11px] opacity-70 mt-3 flex items-center gap-1.5">
            <i class="fi-rr-clock"></i> ข้อมูลล่าสุด: {{ summary.last_import.filename }} · {{ fmtDate(summary.last_import.created_at) }}
          </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-4">
          <!-- Bar: ทุกอำเภอ -->
          <div class="card p-4 lg:col-span-2 min-w-0 overflow-hidden">
            <div class="font-semibold text-sm mb-3 flex items-center gap-1.5"><i class="fi-rr-chart-histogram text-blue-700"></i> จำนวนผู้ตกหล่น · รายอำเภอ ({{ summary?.amphur_count || 0 }} อำเภอ)</div>
            <div class="overflow-x-auto">
              <div :style="{ minWidth: Math.max((summary?.by_amphur?.length || 0) * 42, 400) + 'px' }">
                <apexchart type="bar" height="360" :options="amphurChart.options" :series="amphurChart.series" />
              </div>
            </div>
          </div>
          <!-- Donut: 3 กลุ่ม -->
          <div class="card p-4 min-w-0 overflow-hidden">
            <div class="font-semibold text-sm mb-3 flex items-center gap-1.5"><i class="fi-rr-chart-pie text-blue-700"></i> สัดส่วน 3 กลุ่ม</div>
            <apexchart type="donut" height="300" :options="donut.options" :series="donut.series" />
          </div>
        </div>

        <!-- ═══ SECTION 2 · แผนที่ + ตาราง ═══ -->
        <!-- แผนที่จังหวัด — ตัวเลขผู้ตกหล่นรายอำเภอ -->
        <div class="card p-4 lg:p-5">
          <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
            <div class="font-semibold text-sm flex items-center gap-1.5"><i class="fi-rr-marker text-blue-700"></i> แผนที่จังหวัดนครราชสีมา · จำนวนผู้ตกหล่นรายอำเภอ</div>
            <div class="flex items-center gap-2 text-[10px] text-slate-500 dark:text-slate-400 flex-wrap">
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded" style="background:#86efac"></span>&lt;100</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded" style="background:#facc15"></span>100+</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded" style="background:#f59e0b"></span>500+</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded" style="background:#f97316"></span>1,000+</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded" style="background:#dc2626"></span>2,000+</span>
              <span class="flex items-center gap-1"><span class="w-3 h-3 rounded" style="background:#991b1b"></span>3,000+</span>
            </div>
          </div>
          <div ref="mapEl" class="w-full rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700" style="height: 460px; z-index: 0;"></div>
          <div class="text-[11px] text-slate-400 mt-2">แตะหมุดเพื่อดูรายละเอียด · ตัวเลขบนหมุด = รวมผู้ตกหล่นของอำเภอนั้น</div>
        </div>

        <!-- ตารางข้อมูล -->
        <div>
          <div class="flex items-center justify-between mb-2 gap-2 flex-wrap">
            <div class="font-semibold text-sm flex items-center gap-1.5"><i class="fi-rr-table-list text-blue-700"></i> ตารางข้อมูล</div>
            <div class="flex gap-1.5 items-center">
              <button v-for="(c, i) in cards" :key="c.key" @click="cardIndex = i"
                      :class="['px-3 py-1.5 rounded-lg text-xs flex items-center gap-1.5 border transition',
                               cardIndex === i ? 'bg-blue-700 text-white border-blue-700' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800']">
                <i :class="c.icon"></i> {{ c.title }}
              </button>
              <div class="w-px h-5 bg-slate-200 dark:bg-slate-700 mx-0.5"></div>
              <button @click="exportLevel(cards[cardIndex].key)" :disabled="cards[cardIndex].rows.length === 0"
                      class="px-3 py-1.5 rounded-lg text-xs flex items-center gap-1.5 border border-green-300 dark:border-green-700 text-green-700 dark:text-green-300 hover:bg-green-50 dark:hover:bg-green-900/30 disabled:opacity-40 disabled:cursor-not-allowed">
                <i class="fi-rr-file-excel"></i> Export
              </button>
              <button v-if="isSuper && (amphurRows.length || tambonRows.length)" @click="deleteAllMissed"
                      class="px-3 py-1.5 rounded-lg text-xs flex items-center gap-1.5 border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30">
                <i class="fi-rr-trash"></i> ลบทั้งหมด
              </button>
            </div>
          </div>

          <!-- track -->
          <div class="overflow-hidden">
            <div class="flex transition-transform duration-300" :style="{ transform: `translateX(-${cardIndex * 100}%)` }">
              <div v-for="c in cards" :key="c.key" class="shrink-0 w-full px-0.5">
                <div class="card overflow-hidden">
                  <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
                    <i :class="[c.icon, 'text-blue-700']"></i>
                    <span class="font-semibold text-sm">{{ c.title }}</span>
                    <span class="text-xs text-slate-400">· {{ c.rows.length }} รายการ</span>
                  </div>

                  <div v-if="c.rows.length === 0" class="p-10 text-center text-sm text-slate-500">
                    <i class="fi-rr-inbox text-3xl text-slate-300 dark:text-slate-700 mb-2 block"></i>
                    <template v-if="c.hasTambon">ยังไม่มีข้อมูลระดับตำบล — อัปโหลดไฟล์ตำบลเพิ่มได้ที่ด้านล่าง</template>
                    <template v-else>ยังไม่มีข้อมูล</template>
                  </div>

                  <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead class="text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                          <th class="text-left font-medium px-3 py-2 w-10">#</th>
                          <th class="text-left font-medium px-3 py-2">อำเภอ</th>
                          <th v-if="c.hasTambon" class="text-left font-medium px-3 py-2">ตำบล</th>
                          <th class="text-right font-medium px-3 py-2">เปราะบาง</th>
                          <th class="text-right font-medium px-3 py-2">จปฐ.</th>
                          <th class="text-right font-medium px-3 py-2">3 กลุ่ม</th>
                          <th class="text-right font-medium px-3 py-2">รวม</th>
                          <th v-if="isSuper" class="px-3 py-2 w-10"></th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="(r, i) in c.rows" :key="i" class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                          <td class="px-3 py-2 text-slate-400 tabular-nums">{{ i + 1 }}</td>
                          <td class="px-3 py-2 font-medium">{{ r.amphur_name }}</td>
                          <td v-if="c.hasTambon" class="px-3 py-2">{{ r.tambon_name || '—' }}</td>
                          <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(r.cnt_vulnerable) }}</td>
                          <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(r.cnt_jpt) }}</td>
                          <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(r.cnt_both) }}</td>
                          <td class="px-3 py-2 text-right tabular-nums font-semibold text-blue-700 dark:text-blue-300">{{ formatNumber(r.cnt_total) }}</td>
                          <td v-if="isSuper" class="px-3 py-2 text-center">
                            <button @click="deleteOneStat(r)" title="ลบรายการนี้"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20">
                              <i class="fi-rr-trash text-sm"></i>
                            </button>
                          </td>
                        </tr>
                      </tbody>
                      <tfoot class="border-t-2 border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 font-semibold">
                        <tr>
                          <td class="px-3 py-2.5" :colspan="c.hasTambon ? 3 : 2">รวมทั้งหมด</td>
                          <td class="px-3 py-2.5 text-right tabular-nums">{{ formatNumber(sumRows(c.rows).vuln) }}</td>
                          <td class="px-3 py-2.5 text-right tabular-nums">{{ formatNumber(sumRows(c.rows).jpt) }}</td>
                          <td class="px-3 py-2.5 text-right tabular-nums">{{ formatNumber(sumRows(c.rows).both) }}</td>
                          <td class="px-3 py-2.5 text-right tabular-nums text-blue-700 dark:text-blue-300">{{ formatNumber(sumRows(c.rows).total) }}</td>
                          <td v-if="isSuper"></td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ═══ SECTION 3 · Excel Loader + ประวัติ (super_admin) ═══ -->
        <div v-if="isSuper" class="card p-4 lg:p-5 space-y-4">
          <div class="font-semibold text-sm flex items-center gap-1.5"><i class="fi-rr-cloud-upload-alt text-blue-700"></i> นำเข้าข้อมูล Excel (เฉพาะ Super Admin)</div>

          <div class="card-tint-blue p-3 text-xs leading-snug">
            <i class="fi-rr-info"></i> รองรับไฟล์ .xlsx ที่มีคอลัมน์: อำเภอ (และ ตำบล ถ้ามี) · ตกเกณฑ์ จปฐ. · กลุ่มเปราะบาง · ทั้ง 3 กลุ่ม · รวม
            — ระบบตรวจระดับ (อำเภอ/ตำบล) อัตโนมัติ และ <strong>แทนข้อมูลเดิมของระดับนั้น</strong>
          </div>

          <div class="grid sm:grid-cols-[1fr_auto] gap-3 items-end">
            <div class="space-y-2">
              <div>
                <label class="text-xs text-slate-500 mb-1 block">ไฟล์ Excel</label>
                <input id="mt-file-input" type="file" accept=".xlsx,.xls" @change="onFile"
                       class="block w-full text-sm text-slate-600 dark:text-slate-300 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
              </div>
              <div>
                <label class="text-xs text-slate-500 mb-1 block">หมายเหตุ (ไม่บังคับ)</label>
                <input v-model="uploadNote" type="text" placeholder="เช่น ข้อมูลรอบ มิ.ย. 2569"
                       class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800" />
              </div>
            </div>
            <button @click="doUpload" :disabled="uploading"
                    class="btn-primary px-5 py-2.5 text-sm flex items-center gap-2 disabled:opacity-50">
              <i :class="['fi-rr-cloud-upload-alt', uploading && 'animate-spin']"></i> อัปโหลด
            </button>
          </div>
          <div v-if="uploadMsg" class="card-tint-green p-3 text-sm"><i class="fi-rr-check-circle"></i> {{ uploadMsg }}</div>
          <div v-if="uploadErr" class="card-tint-red p-3 text-sm"><i class="fi-rr-cross-circle"></i> {{ uploadErr }}</div>

          <!-- ประวัติการนำเข้า -->
          <div>
            <div class="text-sm font-medium mb-2 flex items-center gap-1.5"><i class="fi-rr-time-past text-slate-400"></i> ประวัติการนำเข้า</div>
            <div v-if="imports.length === 0" class="text-xs text-slate-500 py-4 text-center">ยังไม่มีประวัติ</div>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50">
                  <tr>
                    <th class="text-left font-medium px-3 py-2">ไฟล์</th>
                    <th class="text-left font-medium px-3 py-2">ระดับ</th>
                    <th class="text-right font-medium px-3 py-2">แถว</th>
                    <th class="text-right font-medium px-3 py-2">รวม (คน)</th>
                    <th class="text-left font-medium px-3 py-2">ผู้นำเข้า</th>
                    <th class="text-left font-medium px-3 py-2">เมื่อ</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr v-for="im in imports" :key="im.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/30">
                    <td class="px-3 py-2 max-w-[200px] truncate" :title="im.filename">{{ im.filename }}</td>
                    <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800">{{ im.level === 'tambon' ? 'ตำบล' : 'อำเภอ' }}</span></td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(im.row_count) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(im.total_count) }}</td>
                    <td class="px-3 py-2 text-slate-500">{{ im.uploaded_by_name || '—' }}</td>
                    <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ fmtDate(im.created_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

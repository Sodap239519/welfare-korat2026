<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const d = ref(null);
const amphurs = ref([]);
const students = ref([]);
const loadingStudents = ref(false);

const filters = reactive({ amphur_id: '', q: '', work_unit_type: '', from: '', to: '' });

// drill-down รายคน
const selected = ref(null);
const studentLogs = ref([]);
const expandedLog = ref(null);
const drillLoading = ref(false);

function qs() {
  const p = new URLSearchParams();
  for (const [k, v] of Object.entries(filters)) if (v) p.append(k, v);
  return p.toString();
}

async function loadOverview() {
  const { data } = await axios.get('/api/student-dashboard');
  d.value = data;
}
async function loadAmphurs() {
  const { data } = await axios.get('/api/ref/amphurs');
  amphurs.value = data.data;
}
async function loadStudents() {
  loadingStudents.value = true;
  try {
    const { data } = await axios.get('/api/student-admin/students?' + qs());
    students.value = data.data;
  } finally {
    loadingStudents.value = false;
  }
}
onMounted(() => { loadOverview(); loadAmphurs(); loadStudents(); loadPreviewPhotos(); });

function exportReport() {
  window.open('/api/student-admin/export?' + qs(), '_blank');
}
const reportGroup = ref('student');
function exportSummary() {
  window.open(`/api/student-admin/report?group_by=${reportGroup.value}&` + qs(), '_blank');
}
const fileUrl = (f) => `/api/files/work/${f.id}`;

// แยกไฟล์ตามหมวด (แสดงเป็นแถวชัดเจน)
const CAT_LABELS = { worklog_doc: 'ใบบันทึกการปฏิบัติงาน', reimburse_doc: 'เอกสารเบิกจ่าย', photo: 'ภาพการปฏิบัติงาน' };
const CAT_ORDER = ['worklog_doc', 'reimburse_doc', 'photo'];
const filesOf = (files, cat) => (files || []).filter(f => f.category === cat);

// ── Gallery ภาพการปฏิบัติงาน ──
const BANKS = [
  { code: 'ktb', name: 'กรุงไทย' }, { code: 'gsb', name: 'ออมสิน' }, { code: 'baac', name: 'ธ.ก.ส.' },
  { code: 'ghb', name: 'อาคารสงเคราะห์' }, { code: 'ibank', name: 'อิสลาม' },
];
const photos = ref([]);
const galleryAll = ref([]);
const showAllPhotos = ref(false);
const galleryLoading = ref(false);
const gFilters = reactive({ date: '', amphur_id: '', bank: '' });
const photoZoom = ref(null);

async function loadPreviewPhotos() {
  const { data } = await axios.get('/api/student-admin/photos?random=1&limit=7');
  photos.value = data.data;
}
async function loadAllPhotos() {
  galleryLoading.value = true;
  try {
    const p = new URLSearchParams({ all: '1' });
    for (const [k, v] of Object.entries(gFilters)) if (v) p.append(k, v);
    const { data } = await axios.get('/api/student-admin/photos?' + p.toString());
    galleryAll.value = data.data;
  } finally { galleryLoading.value = false; }
}
function openAllPhotos() { showAllPhotos.value = true; loadAllPhotos(); }
// ขนาด tile สลับเล็ก/กลาง/ใหญ่ (mosaic)
function tileClass(i) {
  const pat = ['col-span-2 row-span-2', '', '', 'col-span-2', '', 'row-span-2', ''];
  return pat[i % pat.length];
}

async function openStudent(s) {
  selected.value = s;
  expandedLog.value = null;
  drillLoading.value = true;
  try {
    const { data } = await axios.get('/api/student-admin/work-logs?user_id=' + s.id);
    studentLogs.value = data.data;
  } finally {
    drillLoading.value = false;
  }
}
async function viewLog(id) {
  if (expandedLog.value?.id === id) { expandedLog.value = null; return; }
  const { data } = await axios.get('/api/student-admin/work-logs/' + id);
  expandedLog.value = data.data;
}
async function deleteLog(id) {
  if (!confirm('ลบบันทึกนี้? (admin)')) return;
  await axios.delete('/api/student-admin/work-logs/' + id);
  studentLogs.value = studentLogs.value.filter(l => l.id !== id);
  if (expandedLog.value?.id === id) expandedLog.value = null;
  loadStudents(); loadOverview();
}

// charts
const kpis = computed(() => {
  if (!d.value) return [];
  return [
    { label: 'นักศึกษา (active)', value: d.value.students, icon: 'fi-rr-graduation-cap', tint: 'card-tint-blue' },
    { label: 'วัน-ครั้งปฏิบัติงาน', value: d.value.work_days, icon: 'fi-rr-calendar', tint: 'card-tint-sky' },
    { label: 'ผู้รับบริการรวม', value: d.value.service_total, icon: 'fi-rr-users', tint: 'card-tint-sky' },
    { label: 'ลงทะเบียนสำเร็จ', value: d.value.registered_success, icon: 'fi-rr-check', tint: 'card-tint-green' },
    { label: 'ไม่สำเร็จ', value: d.value.registered_fail, icon: 'fi-rr-cross', tint: 'card-tint-red' },
    { label: 'แบบประเมินที่ส่ง', value: d.value.assessments_done, icon: 'fi-rr-clipboard-list', tint: 'card-tint-orange' },
  ];
});
const trendOptions = computed(() => ({
  chart: { toolbar: { show: false } }, stroke: { curve: 'smooth', width: 2 },
  xaxis: { categories: d.value?.trend.labels ?? [] }, colors: ['#2563eb'], dataLabels: { enabled: false },
}));
const trendSeries = computed(() => [{ name: 'ผู้รับบริการ', data: d.value?.trend.data ?? [] }]);
const activityOptions = computed(() => ({
  labels: d.value?.by_activity.labels ?? [], legend: { position: 'bottom' },
  colors: ['#2563eb', '#0ea5e9', '#fb923c', '#16a34a', '#a855f7', '#dc2626'],
}));
const activitySeries = computed(() => d.value?.by_activity.data ?? []);
const hasActivity = computed(() => (d.value?.by_activity.data ?? []).some(n => n > 0));
</script>

<template>
  <AppLayout title="รายงานนักศึกษา" subtitle="ภาพรวม จัดการ และออกรายงานการปฏิบัติงานนักศึกษา มร.นม.">
    <div v-if="!d" class="text-center text-slate-400 py-10">กำลังโหลด…</div>
    <div v-else class="space-y-4">
      <!-- KPI -->
      <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
        <div v-for="k in kpis" :key="k.label" :class="['p-4', k.tint]">
          <i :class="k.icon + ' text-lg opacity-70'"></i>
          <div class="text-2xl font-bold tabular-nums mt-1">{{ formatNumber(k.value) }}</div>
          <div class="text-xs opacity-70">{{ k.label }}</div>
        </div>
      </div>

      <!-- charts -->
      <div class="grid lg:grid-cols-2 gap-4">
        <div class="card p-4">
          <h3 class="font-medium text-sm mb-2">แนวโน้มผู้รับบริการรายวัน</h3>
          <apexchart v-if="d.trend.labels.length" type="area" height="280" :options="trendOptions" :series="trendSeries" />
          <div v-else class="text-center text-slate-400 py-16 text-sm">ยังไม่มีข้อมูล</div>
        </div>
        <div class="card p-4">
          <h3 class="font-medium text-sm mb-2">สัดส่วนตามประเภทกิจกรรม</h3>
          <apexchart v-if="hasActivity" type="donut" height="280" :options="activityOptions" :series="activitySeries" />
          <div v-else class="text-center text-slate-400 py-16 text-sm">ยังไม่มีข้อมูล</div>
        </div>
      </div>

      <!-- Gallery ภาพการปฏิบัติงาน (สุ่มตัวอย่าง mosaic) -->
      <div class="card p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-semibold"><i class="fi-rr-picture text-blue-600"></i> ภาพการปฏิบัติงาน <span class="text-xs text-slate-400 font-normal">(สุ่มตัวอย่าง)</span></h3>
          <button @click="openAllPhotos" class="text-sm text-blue-700">ดูภาพทั้งหมด <i class="fi-rr-angle-small-right"></i></button>
        </div>
        <div v-if="!photos.length" class="text-slate-400 text-sm py-8 text-center"><i class="fi-rr-picture text-2xl"></i><div class="mt-1">ยังไม่มีภาพการปฏิบัติงาน</div></div>
        <div v-else class="grid grid-cols-4 auto-rows-[80px] sm:auto-rows-[120px] gap-2">
          <button v-for="(p, i) in photos" :key="p.id" @click="photoZoom = p" :class="['relative rounded-xl overflow-hidden group bg-slate-100 dark:bg-slate-800', tileClass(i)]">
            <img :src="p.url" referrerpolicy="no-referrer" class="w-full h-full object-cover group-hover:scale-105 transition" :alt="p.name">
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/65 to-transparent px-2 py-1 text-left">
              <div class="text-[10px] text-white truncate">{{ p.student }}</div>
              <div class="text-[9px] text-white/80 truncate">{{ p.work_date?.slice(0, 10) }} · {{ p.unit }}</div>
            </div>
          </button>
        </div>
      </div>

      <!-- จัดการรายบุคคล + รายงาน -->
      <div class="card p-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <h3 class="font-semibold"><i class="fi-rr-users-alt text-blue-600"></i> รายชื่อนักศึกษา & ผลงาน</h3>
          <div class="flex flex-wrap items-center gap-2">
            <select v-model="reportGroup" class="px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
              <option value="student">รายงานสรุป: รายคน</option>
              <option value="amphur">รายงานสรุป: รายอำเภอ</option>
              <option value="bank">รายงานสรุป: รายธนาคาร</option>
              <option value="overall">รายงานสรุป: ภาพรวม</option>
            </select>
            <button @click="exportSummary" class="btn-primary text-sm"><i class="fi-rr-stats"></i> รายงานสรุป</button>
            <button @click="exportReport" class="btn-green text-sm"><i class="fi-rr-file-export"></i> รายละเอียด (Excel)</button>
          </div>
        </div>

        <!-- filter -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-2 mb-3">
          <input v-model="filters.q" @keyup.enter="loadStudents" placeholder="ค้นหาชื่อ/รหัส นศ."
            class="px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <select v-model="filters.amphur_id" class="px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
            <option value="">ทุกอำเภอ</option>
            <option v-for="a in amphurs" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <select v-model="filters.work_unit_type" class="px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
            <option value="">ทุกหน่วย</option>
            <option value="amphur">ที่ว่าการอำเภอ</option>
            <option value="bank">ธนาคาร</option>
          </select>
          <input v-model="filters.from" type="date" class="px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <button @click="loadStudents" class="btn-primary text-sm"><i class="fi-rr-filter"></i> กรอง</button>
        </div>

        <div v-if="loadingStudents" class="text-center text-slate-400 py-6">กำลังโหลด…</div>
        <div v-else-if="!students.length" class="text-slate-400 text-sm py-4">ไม่พบนักศึกษา</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b border-slate-100 dark:border-slate-800">
              <tr>
                <th class="py-2 pr-2 w-8">#</th>
                <th class="py-2 pr-3">นักศึกษา</th>
                <th class="py-2 pr-3">หน่วย</th>
                <th class="py-2 pr-3 text-right">วัน</th>
                <th class="py-2 pr-3 text-right">ผู้รับบริการ</th>
                <th class="py-2 pr-3 text-right">สำเร็จ</th>
                <th class="py-2 pr-3 text-center">ประเมิน</th>
                <th class="py-2"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s, idx) in students" :key="s.id" class="border-b border-slate-50 dark:border-slate-800/50">
                <td class="py-2 pr-2 text-slate-400 tabular-nums">{{ idx + 1 }}</td>
                <td class="py-2 pr-3">
                  <div class="font-medium">{{ s.name }}</div>
                  <div class="text-xs text-slate-400">{{ s.student_id }} · {{ s.faculty }}</div>
                </td>
                <td class="py-2 pr-3 text-xs text-slate-500">{{ s.unit }}</td>
                <td class="py-2 pr-3 text-right tabular-nums">{{ formatNumber(s.days) }}</td>
                <td class="py-2 pr-3 text-right tabular-nums">{{ formatNumber(s.service) }}</td>
                <td class="py-2 pr-3 text-right tabular-nums text-green-600">{{ formatNumber(s.success) }}</td>
                <td class="py-2 pr-3 text-center">
                  <i :class="s.assessed ? 'fi-rr-check text-green-600' : 'fi-rr-minus-small text-slate-300'"></i>
                </td>
                <td class="py-2 text-right">
                  <button @click="openStudent(s)" class="text-blue-700 text-xs hover:underline"><i class="fi-rr-eye"></i> ดูบันทึก</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Drill-down overlay: บันทึกของนักศึกษารายคน -->
    <div v-if="selected" class="fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-0 sm:p-4" @click.self="selected = null">
      <div class="bg-white dark:bg-slate-900 w-full sm:max-w-2xl max-h-[90vh] rounded-t-2xl sm:rounded-2xl overflow-hidden flex flex-col">
        <div class="flex items-center justify-between gap-2 p-4 border-b border-slate-100 dark:border-slate-800">
          <div>
            <div class="font-semibold">{{ selected.name }}</div>
            <div class="text-xs text-slate-400">{{ selected.student_id }} · {{ selected.faculty }} · {{ selected.unit }}</div>
          </div>
          <button @click="selected = null" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800"><i class="fi-rr-cross-small"></i></button>
        </div>
        <div class="p-4 overflow-y-auto space-y-2">
          <div v-if="drillLoading" class="text-center text-slate-400 py-6">กำลังโหลด…</div>
          <div v-else-if="!studentLogs.length" class="text-slate-400 text-sm py-4 text-center">ยังไม่มีบันทึก</div>
          <div v-for="l in studentLogs" :key="l.id" class="card-flat p-3">
            <div class="flex items-center gap-3 flex-wrap">
              <div class="font-medium text-sm min-w-[6rem]">{{ l.work_date?.slice(0, 10) }}</div>
              <div class="text-xs text-slate-500 flex-1">
                ผู้รับบริการ {{ l.service_total || 0 }} · <span class="text-green-600">สำเร็จ {{ l.registered_success }}</span> ·
                <span class="text-red-500">ไม่สำเร็จ {{ l.registered_fail }}</span> · กิจกรรม {{ l.entries_count }} · ปัญหา {{ l.cases_count }}
              </div>
              <button @click="viewLog(l.id)" class="text-blue-700 text-xs"><i class="fi-rr-angle-small-down"></i> ดู</button>
              <button v-if="!auth.isExecutive" @click="deleteLog(l.id)" class="text-red-500 text-xs"><i class="fi-rr-trash"></i> ลบ</button>
            </div>
            <!-- รายละเอียด -->
            <div v-if="expandedLog?.id === l.id" class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2 text-xs">
              <!-- GPS -->
              <div>
                <span class="font-medium">ตำแหน่ง:</span>
                <template v-if="expandedLog.lat">
                  <a :href="`https://www.google.com/maps?q=${expandedLog.lat},${expandedLog.lng}`" target="_blank" rel="noopener" class="text-blue-700 underline">
                    <i class="fi-rr-marker"></i> ดูแผนที่
                  </a>
                  <span class="text-slate-400"> · ~{{ Math.round(expandedLog.location_accuracy || 0) }} ม.
                    <span v-if="expandedLog.location_status === 'low_accuracy'" class="text-amber-600">(สัญญาณอ่อน)</span>
                  </span>
                </template>
                <span v-else class="text-amber-500">ไม่มีพิกัด</span>
              </div>
              <!-- ไฟล์ — แยกตามหมวด -->
              <div v-if="expandedLog.files?.length" class="space-y-2">
                <div v-for="cat in CAT_ORDER" :key="cat" v-show="filesOf(expandedLog.files, cat).length">
                  <div class="font-medium mb-1">{{ CAT_LABELS[cat] }} ({{ filesOf(expandedLog.files, cat).length }})</div>
                  <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                    <a v-for="f in filesOf(expandedLog.files, cat)" :key="f.id" :href="fileUrl(f)" target="_blank" rel="noopener"
                       class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden block">
                      <img v-if="f.is_image" :src="fileUrl(f)" class="w-full h-16 object-cover" :alt="f.original_name">
                      <div v-else class="h-16 flex flex-col items-center justify-center bg-slate-50 dark:bg-slate-800 p-1">
                        <i class="fi-rr-file-pdf text-red-500"></i>
                        <span class="text-[8px] line-clamp-1 break-all">{{ f.original_name }}</span>
                      </div>
                    </a>
                  </div>
                </div>
              </div>
              <div v-if="expandedLog.entries?.length">
                <div class="font-medium mb-1">กิจกรรม</div>
                <div v-for="e in expandedLog.entries" :key="e.id" class="flex gap-2 text-slate-600 dark:text-slate-300">
                  <span class="text-slate-400">{{ e.period }}</span> {{ e.activity_type }}
                  <span v-if="e.detail" class="text-slate-400">— {{ e.detail }}</span>
                  <span class="ml-auto">{{ e.service_count }} ราย</span>
                </div>
              </div>
              <div v-if="expandedLog.cases?.length">
                <div class="font-medium mb-1 mt-2">กรณีปัญหา</div>
                <div v-for="c in expandedLog.cases" :key="c.id" class="text-slate-600 dark:text-slate-300">
                  {{ c.full_name }} <span class="text-slate-400">({{ c.village_tambon }})</span> — {{ c.problem }}
                </div>
              </div>
              <div v-if="expandedLog.supervisor_name" class="text-slate-400">ผู้ควบคุมงาน: {{ expandedLog.supervisor_name }} {{ expandedLog.supervisor_position }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- แกลเลอรีภาพทั้งหมด + ตัวกรอง -->
    <div v-if="showAllPhotos" class="fixed inset-0 z-50 bg-black/50 flex items-stretch sm:items-center justify-center sm:p-4" @click.self="showAllPhotos = false">
      <div class="bg-white dark:bg-slate-900 w-full sm:max-w-4xl max-h-screen sm:max-h-[90vh] sm:rounded-2xl overflow-hidden flex flex-col">
        <div class="flex items-center justify-between gap-2 p-4 border-b border-slate-100 dark:border-slate-800">
          <h3 class="font-semibold"><i class="fi-rr-picture text-blue-600"></i> ภาพการปฏิบัติงานทั้งหมด</h3>
          <button @click="showAllPhotos = false" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800"><i class="fi-rr-cross-small"></i></button>
        </div>
        <!-- ตัวกรอง -->
        <div class="p-3 border-b border-slate-100 dark:border-slate-800 grid grid-cols-2 sm:grid-cols-4 gap-2">
          <input v-model="gFilters.date" type="date" class="px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <select v-model="gFilters.amphur_id" class="px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
            <option value="">ทุกอำเภอ</option>
            <option v-for="a in amphurs" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <select v-model="gFilters.bank" class="px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
            <option value="">ทุกธนาคาร</option>
            <option v-for="b in BANKS" :key="b.code" :value="b.code">{{ b.name }}</option>
          </select>
          <button @click="loadAllPhotos" class="btn-primary text-sm"><i class="fi-rr-filter"></i> กรอง</button>
        </div>
        <div class="p-4 overflow-y-auto">
          <div v-if="galleryLoading" class="text-center text-slate-400 py-8">กำลังโหลด…</div>
          <div v-else-if="!galleryAll.length" class="text-center text-slate-400 py-8">ไม่พบภาพตามเงื่อนไข</div>
          <div v-else class="grid grid-cols-3 sm:grid-cols-4 gap-2">
            <button v-for="p in galleryAll" :key="p.id" @click="photoZoom = p" class="relative aspect-square rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-800 group">
              <img :src="p.url" referrerpolicy="no-referrer" class="w-full h-full object-cover group-hover:scale-105 transition" :alt="p.name">
              <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent px-1.5 py-1 text-left">
                <div class="text-[9px] text-white truncate">{{ p.student }}</div>
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Lightbox ภาพ + รายละเอียดสถานที่/พิกัด -->
    <div v-if="photoZoom" class="fixed inset-0 z-[60] bg-black/85 flex items-center justify-center p-4" @click.self="photoZoom = null">
      <div class="max-w-3xl w-full">
        <img :src="photoZoom.url" referrerpolicy="no-referrer" class="max-h-[68vh] w-auto mx-auto rounded-lg shadow-2xl" :alt="photoZoom.name">
        <div class="bg-white dark:bg-slate-900 rounded-xl mt-2 p-3">
          <div class="font-medium text-sm">{{ photoZoom.student }} <span class="text-slate-400 font-normal">· {{ photoZoom.faculty }}</span></div>
          <div class="text-slate-500 text-xs mt-0.5">{{ photoZoom.work_date?.slice(0, 10) }} · {{ photoZoom.unit }}</div>
          <div class="mt-1.5 text-xs">
            <template v-if="photoZoom.lat">
              <i class="fi-rr-marker text-blue-600"></i> {{ Number(photoZoom.lat).toFixed(5) }}, {{ Number(photoZoom.lng).toFixed(5) }}
              <a :href="`https://www.google.com/maps?q=${photoZoom.lat},${photoZoom.lng}`" target="_blank" rel="noopener" class="text-blue-700 underline ml-1">เปิดแผนที่</a>
            </template>
            <span v-else class="text-amber-500"><i class="fi-rr-exclamation"></i> ไม่มีพิกัด</span>
          </div>
        </div>
      </div>
      <button @click="photoZoom = null" class="absolute top-4 right-4 w-10 h-10 grid place-items-center rounded-full bg-white/15 text-white hover:bg-white/25 text-xl"><i class="fi-rr-cross-small"></i></button>
    </div>
  </AppLayout>
</template>

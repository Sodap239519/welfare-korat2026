<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';

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
onMounted(() => { loadOverview(); loadAmphurs(); loadStudents(); });

function exportReport() {
  window.open('/api/student-admin/export?' + qs(), '_blank');
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

      <!-- จัดการรายบุคคล + รายงาน -->
      <div class="card p-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <h3 class="font-semibold"><i class="fi-rr-users-alt text-blue-600"></i> รายชื่อนักศึกษา & ผลงาน</h3>
          <button @click="exportReport" class="btn-green text-sm"><i class="fi-rr-file-export"></i> ส่งออก Excel</button>
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
              <tr v-for="s in students" :key="s.id" class="border-b border-slate-50 dark:border-slate-800/50">
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
              <button @click="deleteLog(l.id)" class="text-red-500 text-xs"><i class="fi-rr-trash"></i> ลบ</button>
            </div>
            <!-- รายละเอียด -->
            <div v-if="expandedLog?.id === l.id" class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2 text-xs">
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
  </AppLayout>
</template>

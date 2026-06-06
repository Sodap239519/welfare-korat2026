<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';

const d = ref(null);
onMounted(async () => {
  const { data } = await axios.get('/api/student-dashboard');
  d.value = data;
});

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

const amphurOptions = computed(() => ({
  chart: { toolbar: { show: false } }, plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  xaxis: { categories: d.value?.by_amphur.labels ?? [] }, colors: ['#16a34a'], dataLabels: { enabled: false },
}));
const amphurSeries = computed(() => [{ name: 'ลงทะเบียนสำเร็จ', data: d.value?.by_amphur.data ?? [] }]);
const hasAmphur = computed(() => (d.value?.by_amphur.data ?? []).some(n => n > 0));

const problemOptions = computed(() => ({
  chart: { toolbar: { show: false } }, plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  xaxis: { categories: d.value?.problems.labels ?? [] }, colors: ['#dc2626'], dataLabels: { enabled: false },
}));
const problemSeries = computed(() => [{ name: 'จำนวนที่รายงาน', data: d.value?.problems.data ?? [] }]);
const hasProblems = computed(() => (d.value?.problems.data ?? []).length > 0);
</script>

<template>
  <AppLayout title="รายงานนักศึกษา" subtitle="ภาพรวมการปฏิบัติงานหนุนเสริมของนักศึกษา มร.นม.">
    <div v-if="!d" class="text-center text-slate-400 py-10">กำลังโหลด…</div>
    <div v-else class="space-y-4">
      <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
        <div v-for="k in kpis" :key="k.label" :class="['p-4', k.tint]">
          <i :class="k.icon + ' text-lg opacity-70'"></i>
          <div class="text-2xl font-bold tabular-nums mt-1">{{ formatNumber(k.value) }}</div>
          <div class="text-xs opacity-70">{{ k.label }}</div>
        </div>
      </div>

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
        <div class="card p-4">
          <h3 class="font-medium text-sm mb-2">ลงทะเบียนสำเร็จ แยกตามอำเภอ/หน่วย</h3>
          <apexchart v-if="hasAmphur" type="bar" height="320" :options="amphurOptions" :series="amphurSeries" />
          <div v-else class="text-center text-slate-400 py-16 text-sm">ยังไม่มีข้อมูล</div>
        </div>
        <div class="card p-4">
          <h3 class="font-medium text-sm mb-2">ปัญหาที่พบบ่อย (จากแบบประเมิน)</h3>
          <apexchart v-if="hasProblems" type="bar" height="320" :options="problemOptions" :series="problemSeries" />
          <div v-else class="text-center text-slate-400 py-16 text-sm">ยังไม่มีข้อมูล</div>
        </div>
      </div>

      <!-- หน่วยปฏิบัติงาน -->
      <div class="grid grid-cols-2 gap-3 max-w-md">
        <div class="card-tint-blue p-4">
          <div class="text-xs opacity-70">นักศึกษา ณ ที่ว่าการอำเภอ</div>
          <div class="text-2xl font-bold">{{ formatNumber(d.by_unit.amphur) }}</div>
        </div>
        <div class="card-tint-green p-4">
          <div class="text-xs opacity-70">นักศึกษา ณ ธนาคาร</div>
          <div class="text-2xl font-bold">{{ formatNumber(d.by_unit.bank) }}</div>
        </div>
      </div>

      <!-- ตารางนักศึกษา -->
      <div class="card p-4">
        <h3 class="font-medium text-sm mb-3">นักศึกษาที่มีผลงานสูงสุด</h3>
        <div v-if="!d.top_students.length" class="text-slate-400 text-sm py-4">ยังไม่มีข้อมูล</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b border-slate-100 dark:border-slate-800">
              <tr>
                <th class="py-2 pr-3">นักศึกษา</th>
                <th class="py-2 pr-3">คณะ</th>
                <th class="py-2 pr-3 text-right">วัน</th>
                <th class="py-2 pr-3 text-right">ผู้รับบริการ</th>
                <th class="py-2 text-right">สำเร็จ</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in d.top_students" :key="s.id" class="border-b border-slate-50 dark:border-slate-800/50">
                <td class="py-2 pr-3 font-medium">{{ s.name }}</td>
                <td class="py-2 pr-3 text-slate-500">{{ s.faculty || '—' }}</td>
                <td class="py-2 pr-3 text-right tabular-nums">{{ formatNumber(s.days) }}</td>
                <td class="py-2 pr-3 text-right tabular-nums">{{ formatNumber(s.service) }}</td>
                <td class="py-2 text-right tabular-nums text-green-600">{{ formatNumber(s.success) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

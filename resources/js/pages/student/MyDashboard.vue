<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';

const d = ref(null);
onMounted(async () => {
  const { data } = await axios.get('/api/student/my-dashboard');
  d.value = data;
});

const kpis = computed(() => {
  if (!d.value) return [];
  return [
    { label: 'วันที่ปฏิบัติงาน', value: d.value.work_days, icon: 'fi-rr-calendar', tint: 'card-tint-blue' },
    { label: 'ผู้รับบริการรวม', value: d.value.service_total, icon: 'fi-rr-users', tint: 'card-tint-sky' },
    { label: 'ลงทะเบียนสำเร็จ', value: d.value.registered_success, icon: 'fi-rr-check', tint: 'card-tint-green' },
    { label: 'ไม่สำเร็จ', value: d.value.registered_fail, icon: 'fi-rr-cross', tint: 'card-tint-red' },
    { label: 'กรณีปัญหา', value: d.value.case_count, icon: 'fi-rr-exclamation', tint: 'card-tint-orange' },
  ];
});

const trendOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  stroke: { curve: 'smooth', width: 2 },
  xaxis: { categories: d.value?.trend.labels ?? [] },
  colors: ['#2563eb'],
  dataLabels: { enabled: false },
}));
const trendSeries = computed(() => [{ name: 'ผู้รับบริการ', data: d.value?.trend.data ?? [] }]);

const activityOptions = computed(() => ({
  labels: d.value?.by_activity.labels ?? [],
  legend: { position: 'bottom' },
  colors: ['#2563eb', '#0ea5e9', '#fb923c', '#16a34a', '#a855f7', '#dc2626'],
}));
const activitySeries = computed(() => d.value?.by_activity.data ?? []);
const hasActivity = computed(() => (d.value?.by_activity.data ?? []).some(n => n > 0));
</script>

<template>
  <AppLayout title="สรุปงานของฉัน" subtitle="ภาพรวมการปฏิบัติงานหนุนเสริมของคุณ">
    <div v-if="!d" class="text-center text-slate-400 py-10">กำลังโหลด…</div>
    <div v-else class="space-y-4">
      <div v-if="!d.self_assessment_done" class="card-tint-orange p-3 text-sm">
        <i class="fi-rr-info"></i> ยังไม่ได้ทำแบบประเมินตนเอง —
        <RouterLink :to="{ name: 'student-assessment' }" class="underline font-medium">ทำแบบประเมิน</RouterLink>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
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
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import PublicNav from '@/components/PublicNav.vue';
import { formatNumber } from '@/composables/useApi';

const d = ref(null);
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/auth/public/student-dashboard');
    d.value = data;
  } catch (e) { /* public — เงียบไว้ */ }
});

const kpis = computed(() => {
  if (!d.value) return [];
  return [
    { label: 'นักศึกษาลงพื้นที่', value: d.value.students, icon: 'fi-rr-graduation-cap' },
    { label: 'วัน-ครั้งปฏิบัติงาน', value: d.value.work_days, icon: 'fi-rr-calendar' },
    { label: 'ผู้รับบริการรวม', value: d.value.service_total, icon: 'fi-rr-users' },
    { label: 'ลงทะเบียนสำเร็จ', value: d.value.registered_success, icon: 'fi-rr-check' },
  ];
});

const trendOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  stroke: { curve: 'smooth', width: 2 }, fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0 } },
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
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
    <PublicNav />

    <section class="card-hero">
      <div class="max-w-6xl mx-auto px-4 py-10">
        <h1 class="text-2xl lg:text-3xl font-bold">ติดตามงานนักศึกษา มร.นม.</h1>
        <p class="mt-2 opacity-90 text-sm lg:text-base max-w-2xl">
          ภาพรวมการลงพื้นที่หนุนเสริมการลงทะเบียนบัตรสวัสดิการ — อาจารย์และผู้บริหารติดตามได้โดยไม่ต้องเข้าสู่ระบบ
        </p>
      </div>
    </section>

    <main class="max-w-6xl mx-auto px-4 py-8 space-y-6">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div v-for="k in kpis" :key="k.label" class="card p-4">
          <i :class="k.icon + ' text-lg text-blue-600'"></i>
          <div class="text-2xl lg:text-3xl font-bold tabular-nums mt-1">{{ formatNumber(k.value) }}</div>
          <div class="text-xs text-slate-500">{{ k.label }}</div>
        </div>
      </div>

      <div v-if="d" class="grid lg:grid-cols-2 gap-4">
        <div class="card p-4">
          <h3 class="font-medium text-sm mb-2">แนวโน้มผู้รับบริการรายวัน</h3>
          <apexchart v-if="d.trend.labels.length" type="area" height="300" :options="trendOptions" :series="trendSeries" />
          <div v-else class="text-center text-slate-400 py-20 text-sm">ยังไม่มีข้อมูลการปฏิบัติงาน</div>
        </div>
        <div class="card p-4">
          <h3 class="font-medium text-sm mb-2">สัดส่วนตามประเภทกิจกรรม</h3>
          <apexchart v-if="hasActivity" type="donut" height="300" :options="activityOptions" :series="activitySeries" />
          <div v-else class="text-center text-slate-400 py-20 text-sm">ยังไม่มีข้อมูลกิจกรรม</div>
        </div>
      </div>
      <div v-else class="card p-10 text-center text-slate-400">กำลังโหลดข้อมูล…</div>

      <footer class="text-center text-xs text-slate-400 py-6 leading-relaxed">
        © 2569 โครงการการขับเคลื่อนพื้นที่วิจัยเชิงยุทธศาสตร์เพื่อขจัดความยากจน
        และสร้างโอกาสทางสังคมแบบบูรณาการ จังหวัดนครราชสีมา<br>
        ศูนย์ศึกษาและพัฒนาโคราช มหาวิทยาลัยราชภัฏนครราชสีมา
      </footer>
    </main>
  </div>
</template>

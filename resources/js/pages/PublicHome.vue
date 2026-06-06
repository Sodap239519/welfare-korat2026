<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useThemeStore } from '@/stores/theme';
import { formatNumber } from '@/composables/useApi';

const auth = useAuthStore();
const theme = useThemeStore();
const router = useRouter();

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
  chart: { toolbar: { show: false }, sparkline: { enabled: false } },
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

function goLogin() { router.push({ name: 'login' }); }
function goApp() { router.push(auth.isStudent ? { name: 'student-worklog' } : { name: 'dashboard' }); }
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
    <!-- Top bar -->
    <header class="sticky top-0 z-20 bg-white/80 dark:bg-slate-900/80 backdrop-blur border-b border-slate-100 dark:border-slate-800">
      <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
        <div class="leading-tight">
          <div class="text-base font-bold bg-gradient-to-r from-blue-700 to-sky-500 bg-clip-text text-transparent">Welfare Korat 2026</div>
          <div class="text-[10px] text-slate-500 dark:text-slate-400">ติดตามการปฏิบัติงานนักศึกษา · เรียลไทม์</div>
        </div>
        <div class="flex items-center gap-2">
          <button @click="theme.toggle" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800" title="สลับโหมดสี">
            <i v-if="!theme.isDark" class="fi-rr-brightness"></i><i v-else class="fi-sr-moon text-orange-400"></i>
          </button>
          <RouterLink :to="{ name: 'project-info' }" class="hidden sm:inline text-sm text-slate-600 dark:text-slate-300 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">ข้อมูลโครงการ</RouterLink>
          <button v-if="auth.isAuth" @click="goApp" class="btn-primary text-sm"><i class="fi-rr-apps"></i> เข้าระบบ</button>
          <button v-else @click="goLogin" class="btn-primary text-sm"><i class="fi-rr-sign-in-alt"></i> เข้าสู่ระบบ</button>
        </div>
      </div>
    </header>

    <!-- Hero -->
    <section class="card-hero">
      <div class="max-w-6xl mx-auto px-4 py-10 lg:py-14">
        <div class="text-xs uppercase tracking-wide opacity-80">มหาวิทยาลัยราชภัฏนครราชสีมา</div>
        <h1 class="text-2xl lg:text-4xl font-bold leading-snug mt-2">
          ระบบสนับสนุนการลงทะเบียน<br>บัตรสวัสดิการแห่งรัฐ ปี 2569
        </h1>
        <p class="mt-3 opacity-90 max-w-2xl text-sm lg:text-base">
          แดชบอร์ดสาธารณะติดตามการลงพื้นที่หนุนเสริมของนักศึกษา จังหวัดนครราชสีมา —
          อาจารย์และผู้บริหารดูภาพรวมได้ทันที โดยไม่ต้องเข้าสู่ระบบ
        </p>
      </div>
    </section>

    <main class="max-w-6xl mx-auto px-4 py-8 space-y-6">
      <!-- KPI -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div v-for="k in kpis" :key="k.label" class="card p-4">
          <i :class="k.icon + ' text-lg text-blue-600'"></i>
          <div class="text-2xl lg:text-3xl font-bold tabular-nums mt-1">{{ formatNumber(k.value) }}</div>
          <div class="text-xs text-slate-500">{{ k.label }}</div>
        </div>
      </div>

      <!-- Charts -->
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

      <!-- สรุปโครงการ -->
      <div class="card p-6">
        <h2 class="text-lg font-semibold mb-2">เกี่ยวกับโครงการ</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
          โครงการการขับเคลื่อนพื้นที่วิจัยเชิงยุทธศาสตร์เพื่อขจัดความยากจนและสร้างโอกาสทางสังคมแบบบูรณาการ
          จังหวัดนครราชสีมา — บูรณาการความร่วมมือระหว่างมหาวิทยาลัยราชภัฏนครราชสีมา จังหวัดนครราชสีมา
          และหน่วยงานเครือข่าย โดยนักศึกษา มร.นม. ลงพื้นที่ช่วยประชาชนกลุ่มเป้าหมายเข้าถึงสวัสดิการแห่งรัฐ
        </p>
        <RouterLink :to="{ name: 'project-info' }" class="inline-flex items-center gap-1 mt-3 text-sm text-blue-700 font-medium">
          ดูรายละเอียด + เอกสารเผยแพร่ <i class="fi-rr-arrow-small-right"></i>
        </RouterLink>
      </div>

      <footer class="text-center text-xs text-slate-400 py-6">
        © 2569 มหาวิทยาลัยราชภัฏนครราชสีมา · ระบบติดตามการลงทะเบียนบัตรสวัสดิการแห่งรัฐ
      </footer>
    </main>
  </div>
</template>

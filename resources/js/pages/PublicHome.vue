<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import PublicNav from '@/components/PublicNav.vue';
import { useAuthStore } from '@/stores/auth';
import { formatNumber } from '@/composables/useApi';

const auth = useAuthStore();
const router = useRouter();

const stats = ref({ targets: 0, tambons: 0, amphurs: 0 });
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/auth/public-stats');
    stats.value = data;
  } catch (e) { /* public — เงียบไว้ */ }
});

const kpis = computed(() => [
  { label: 'กลุ่มเป้าหมาย', value: stats.value.targets, icon: 'fi-rr-users-alt' },
  { label: 'ตำบล', value: stats.value.tambons, icon: 'fi-rr-marker' },
  { label: 'อำเภอ', value: stats.value.amphurs, icon: 'fi-rr-map' },
  { label: 'ผู้มีสิทธิที่ยังไม่ได้รับบัตร', value: 36606, icon: 'fi-rr-id-badge' },
]);

const steps = [
  { n: 1, title: 'วิเคราะห์สิทธิ เกณฑ์ผู้มีสิทธิ' },
  { n: 2, title: 'วิเคราะห์ฐานข้อมูลและรายชื่อ (DSS)' },
  { n: 3, title: 'เตรียมความพร้อมผ่านเวทีชี้แจง' },
  { n: 4, title: 'กลไกช่วยลงทะเบียน (จุดบริการ/เยี่ยมบ้าน)' },
  { n: 5, title: 'ตรวจสอบ ติดตามและประเมินผล' },
];

function goApp() { router.push(auth.isStudent ? { name: 'student-worklog' } : { name: 'dashboard' }); }
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
    <PublicNav />

    <!-- Hero -->
    <section class="card-hero">
      <div class="max-w-6xl mx-auto px-4 py-12 lg:py-16">
        <div class="text-xs uppercase tracking-wide opacity-80">มหาวิทยาลัยราชภัฏนครราชสีมา · จังหวัดนครราชสีมา</div>
        <h1 class="text-3xl lg:text-5xl font-bold leading-tight mt-2">
          ระบบสนับสนุนการลงทะเบียน<br>บัตรสวัสดิการแห่งรัฐ ปี 2569
        </h1>
        <p class="mt-4 opacity-90 max-w-2xl text-sm lg:text-base">
          บูรณาการความร่วมมือเพื่อช่วยประชาชนกลุ่มเป้าหมายเข้าถึงสวัสดิการแห่งรัฐ
          พร้อมระบบติดตามการลงทะเบียนแบบเรียลไทม์
        </p>
        <div class="flex flex-wrap gap-2 mt-6">
          <button v-if="auth.isAuth" @click="goApp" class="bg-white text-blue-700 rounded-xl px-5 py-2.5 font-medium text-sm hover:bg-blue-50"><i class="fi-rr-apps"></i> เข้าสู่ระบบงาน</button>
          <RouterLink v-else :to="{ name: 'login' }" class="bg-white text-blue-700 rounded-xl px-5 py-2.5 font-medium text-sm hover:bg-blue-50"><i class="fi-rr-sign-in-alt"></i> เข้าสู่ระบบ / ลงทะเบียน</RouterLink>
          <RouterLink :to="{ name: 'student-tracking' }" class="bg-white/15 hover:bg-white/25 rounded-xl px-5 py-2.5 font-medium text-sm"><i class="fi-rr-graduation-cap"></i> ติดตามงานนักศึกษา</RouterLink>
        </div>
      </div>
    </section>

    <main class="max-w-6xl mx-auto px-4 py-8 space-y-8">
      <!-- ตัวเลขภาพรวม -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div v-for="k in kpis" :key="k.label" class="card p-4">
          <i :class="k.icon + ' text-lg text-blue-600'"></i>
          <div class="text-2xl lg:text-3xl font-bold tabular-nums mt-1">{{ formatNumber(k.value) }}</div>
          <div class="text-xs text-slate-500">{{ k.label }}</div>
        </div>
      </div>

      <!-- กระบวนการ 5 ขั้น -->
      <section>
        <h2 class="text-lg font-semibold mb-3">กระบวนการดำเนินงาน</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
          <div v-for="s in steps" :key="s.n" class="card p-4">
            <div class="w-8 h-8 rounded-full bg-blue-700 text-white grid place-items-center font-bold text-sm mb-2">{{ s.n }}</div>
            <div class="text-sm font-medium leading-snug">{{ s.title }}</div>
          </div>
        </div>
      </section>

      <!-- ลิงก์ไปส่วนต่าง ๆ -->
      <div class="grid sm:grid-cols-2 gap-4">
        <RouterLink :to="{ name: 'student-tracking' }" class="card p-6 hover:shadow-md transition flex items-start gap-4">
          <i class="fi-rr-stats text-2xl text-blue-600"></i>
          <div>
            <div class="font-semibold">ติดตามงานนักศึกษา</div>
            <p class="text-sm text-slate-500 mt-1">แดชบอร์ดสาธารณะ ดูภาพรวมการลงพื้นที่หนุนเสริมของนักศึกษา มร.นม. โดยไม่ต้องเข้าสู่ระบบ</p>
          </div>
        </RouterLink>
        <RouterLink :to="{ name: 'project-info' }" class="card p-6 hover:shadow-md transition flex items-start gap-4">
          <i class="fi-rr-document text-2xl text-blue-600"></i>
          <div>
            <div class="font-semibold">เกี่ยวกับโครงการ</div>
            <p class="text-sm text-slate-500 mt-1">รายละเอียดโครงการ หน่วยงานร่วม และเอกสารเผยแพร่จากเว็บไซต์กระทรวงการคลัง</p>
          </div>
        </RouterLink>
      </div>

      <footer class="text-center text-xs text-slate-400 py-6 leading-relaxed">
        © 2569 โครงการการขับเคลื่อนพื้นที่วิจัยเชิงยุทธศาสตร์เพื่อขจัดความยากจน
        และสร้างโอกาสทางสังคมแบบบูรณาการ จังหวัดนครราชสีมา<br>
        ศูนย์ศึกษาและพัฒนาโคราช มหาวิทยาลัยราชภัฏนครราชสีมา
      </footer>
    </main>
  </div>
</template>

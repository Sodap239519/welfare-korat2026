<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const saving = ref(false);
const flashOk = ref('');
const errorMsg = ref('');

const SKILLS = ['Digital literacy (ใช้ระบบ/แอปภาครัฐ)', 'การทำงานกับชุมชน', 'การใช้ข้อมูลเชิงนโยบาย (PPPConnext/คัดกรอง)', 'การสื่อสารกับประชาชน', 'การแก้ปัญหาเฉพาะหน้า'];
const GROUPS = ['ผู้สูงอายุ', 'ผู้ไม่มีสมาร์ทโฟน', 'ผู้ไม่มีเอกสาร', 'กลุ่มตกหล่น (ไม่อยู่ในระบบ)'];
const PROBLEMS = ['ไม่มีอุปกรณ์ (มือถือ/เน็ต)', 'ขาดทักษะดิจิทัล', 'ขั้นตอนระบบซับซ้อน', 'เอกสารไม่ครบ', 'ระบบล่ม/ช้า'];

const TECH = [
  { value: '≥70%', label: 'ส่วนใหญ่ใช้ได้เอง (≥ 70%)' },
  { value: '40-69%', label: 'ใช้ได้บางส่วน (40–69%)' },
  { value: '≤39%', label: 'ส่วนใหญ่ใช้ไม่ได้ (≤ 39%)' },
];
const DEPENDENCY = [
  { value: 'ไม่ต้องช่วย', label: 'ส่วนใหญ่ไม่ต้องช่วย' },
  { value: 'ช่วยบางส่วน', label: 'ส่วนใหญ่ต้องช่วยบางส่วน' },
  { value: 'ทำแทนทั้งหมด', label: 'ส่วนใหญ่ต้องทำแทนทั้งหมด' },
];
const ROLE = [
  { value: 'อธิบายขั้นตอน', label: 'อธิบายขั้นตอน (ประชาชนทำเอง)' },
  { value: 'ช่วยบางส่วน', label: 'ช่วยบางส่วน (กรอกข้อมูล/แนะนำ)' },
  { value: 'ทำแทนทั้งหมด', label: 'ทำแทนทั้งหมด' },
];
const SUCCESS = [
  { value: '>80%', label: 'มากกว่า 80% ลงทะเบียนสำเร็จ' },
  { value: '50-79%', label: 'ประมาณ 50–79% สำเร็จ' },
  { value: '<50%', label: 'น้อยกว่า 50% สำเร็จ' },
];
const WITHOUT = [
  { value: 'ยังเข้าถึงได้', label: 'ประชาชนส่วนใหญ่ยังเข้าถึงได้' },
  { value: 'เข้าถึงไม่ได้', label: 'ประชาชนส่วนใหญ่จะไม่สามารถเข้าถึงได้' },
];

const f = reactive({
  understanding_level: '', skills: [], people_groups: [], lessons: '',
  tech_overall: '', dependency: '', problems: [], problems_other: '',
  top_problem: '', student_role: '', total_people: null, success_rate: '', without_help: '',
});

function toggle(arr, val) {
  const i = arr.indexOf(val);
  if (i >= 0) arr.splice(i, 1); else arr.push(val);
}

onMounted(async () => {
  const { data } = await axios.get('/api/student/self-assessment');
  if (data.data) {
    Object.assign(f, {
      ...data.data,
      skills: data.data.skills ?? [],
      people_groups: data.data.people_groups ?? [],
      problems: data.data.problems ?? [],
    });
  }
});

async function save() {
  saving.value = true;
  errorMsg.value = '';
  flashOk.value = '';
  try {
    await axios.put('/api/student/self-assessment', f);
    flashOk.value = 'บันทึกแบบประเมินตนเองเรียบร้อย';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'บันทึกไม่สำเร็จ';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <AppLayout title="การประเมินตนเอง" subtitle="ประเมินเมื่อดำเนินงานเสร็จสิ้นทั้งหมดแล้ว">
    <div class="max-w-3xl space-y-4">
      <div v-if="flashOk" class="card-tint-green p-3 text-sm"><i class="fi-rr-check-circle"></i> {{ flashOk }}</div>
      <div v-if="errorMsg" class="card-tint-red p-3 text-sm"><i class="fi-rr-cross-circle"></i> {{ errorMsg }}</div>

      <!-- 1 -->
      <div class="card p-4">
        <div class="font-medium text-sm mb-2">1. ระดับความเข้าใจงาน</div>
        <div class="flex flex-wrap gap-4 text-sm">
          <label v-for="o in ['มาก','ปานกลาง','น้อย']" :key="o" class="flex items-center gap-2">
            <input type="radio" v-model="f.understanding_level" :value="o"> {{ o }}
          </label>
        </div>
      </div>

      <!-- 2 -->
      <div class="card p-4">
        <div class="font-medium text-sm mb-2">2. ทักษะที่ได้รับ <span class="text-slate-400 font-normal">(เลือกได้มากกว่า 1)</span></div>
        <div class="grid sm:grid-cols-2 gap-2 text-sm">
          <label v-for="s in SKILLS" :key="s" class="flex items-center gap-2">
            <input type="checkbox" :checked="f.skills.includes(s)" @change="toggle(f.skills, s)"> {{ s }}
          </label>
        </div>
      </div>

      <!-- 3 -->
      <div class="card p-4">
        <div class="font-medium text-sm mb-2">3. ลักษณะกลุ่มประชาชนที่พบส่วนใหญ่ <span class="text-slate-400 font-normal">(เลือกได้มากกว่า 1)</span></div>
        <div class="grid sm:grid-cols-2 gap-2 text-sm">
          <label v-for="g in GROUPS" :key="g" class="flex items-center gap-2">
            <input type="checkbox" :checked="f.people_groups.includes(g)" @change="toggle(f.people_groups, g)"> {{ g }}
          </label>
        </div>
      </div>

      <!-- 4 -->
      <div class="card p-4">
        <div class="font-medium text-sm mb-2">4. บทเรียนที่ได้รับจากการปฏิบัติงาน</div>
        <textarea v-model="f.lessons" rows="4" placeholder="เช่น การให้บริการ การใช้ระบบ การทำงานร่วมกับหน่วยงาน"
          class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm"></textarea>
      </div>

      <!-- 5 -->
      <div class="card p-4 space-y-4">
        <div class="font-medium text-sm">5. ข้อสังเกตการเข้าถึงเทคโนโลยีของประชาชน
          <span class="text-slate-400 font-normal block text-xs mt-0.5">(ประเมินจากการสังเกตขณะให้บริการจริง ไม่ต้องถามโดยตรง)</span>
        </div>

        <div>
          <div class="text-sm mb-1.5">5.1 ระดับภาพรวมการใช้เทคโนโลยี</div>
          <div class="space-y-1 text-sm">
            <label v-for="o in TECH" :key="o.value" class="flex items-center gap-2">
              <input type="radio" v-model="f.tech_overall" :value="o.value"> {{ o.label }}
            </label>
          </div>
        </div>

        <div>
          <div class="text-sm mb-1.5">5.2 การพึ่งพาผู้อื่นในการเข้าถึงสวัสดิการ</div>
          <div class="space-y-1 text-sm">
            <label v-for="o in DEPENDENCY" :key="o.value" class="flex items-center gap-2">
              <input type="radio" v-model="f.dependency" :value="o.value"> {{ o.label }}
            </label>
          </div>
        </div>

        <div>
          <div class="text-sm mb-1.5">5.3 ลักษณะปัญหาที่พบ <span class="text-slate-400 font-normal">(เลือกได้มากกว่า 1)</span></div>
          <div class="grid sm:grid-cols-2 gap-2 text-sm">
            <label v-for="p in PROBLEMS" :key="p" class="flex items-center gap-2">
              <input type="checkbox" :checked="f.problems.includes(p)" @change="toggle(f.problems, p)"> {{ p }}
            </label>
          </div>
          <input v-model="f.problems_other" placeholder="อื่น ๆ (ระบุ)" class="mt-2 w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <div class="mt-3 text-sm mb-1">5.3.1 ปัญหาที่ "พบมากที่สุด" คือ</div>
          <input v-model="f.top_problem" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </div>

        <div>
          <div class="text-sm mb-1.5">5.4 บทบาทของนักศึกษาในการหนุนเสริม (ช่วยมากที่สุด)</div>
          <div class="space-y-1 text-sm">
            <label v-for="o in ROLE" :key="o.value" class="flex items-center gap-2">
              <input type="radio" v-model="f.student_role" :value="o.value"> {{ o.label }}
            </label>
          </div>
        </div>

        <div>
          <div class="text-sm mb-1.5">5.5 ผลลัพธ์การเข้าถึงสวัสดิการ</div>
          <label class="text-sm flex items-center gap-2 mb-2">
            จากประชาชนทั้งหมด
            <input v-model.number="f.total_people" type="number" min="0" class="w-24 px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900"> คน
          </label>
          <div class="space-y-1 text-sm">
            <label v-for="o in SUCCESS" :key="o.value" class="flex items-center gap-2">
              <input type="radio" v-model="f.success_rate" :value="o.value"> {{ o.label }}
            </label>
          </div>
        </div>

        <div>
          <div class="text-sm mb-1.5">5.6 หากไม่มีนักศึกษาช่วย/ไม่มีเจ้าหน้าที่ช่วยลงทะเบียน</div>
          <div class="space-y-1 text-sm">
            <label v-for="o in WITHOUT" :key="o.value" class="flex items-center gap-2">
              <input type="radio" v-model="f.without_help" :value="o.value"> {{ o.label }}
            </label>
          </div>
        </div>
      </div>

      <button @click="save" :disabled="saving" class="btn-primary disabled:opacity-60">
        <i v-if="saving" class="fi-rr-spinner animate-spin"></i> บันทึกแบบประเมิน
      </button>
    </div>
  </AppLayout>
</template>

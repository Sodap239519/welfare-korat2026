<script setup>
import PublicNav from '@/components/PublicNav.vue';

const OFFICIAL = 'https://welfare.mof.go.th/';

// เมนูหลักจากเว็บไซต์กระทรวงการคลัง — สรุป + ลิงก์ออก
const menus = [
  { icon: 'fi-rr-list-check', title: 'คุณสมบัติและข้อกำหนด', desc: 'เกณฑ์คุณสมบัติผู้มีสิทธิ ลงทะเบียนบัตรสวัสดิการแห่งรัฐ ปี 2569' },
  { icon: 'fi-rr-following', title: 'ขั้นตอนการลงทะเบียน', desc: 'วิธีและช่องทางการลงทะเบียน เอกสารที่ต้องใช้' },
  { icon: 'fi-rr-info', title: 'เกี่ยวกับโครงการ', desc: 'ความเป็นมาและวัตถุประสงค์ของโครงการบัตรสวัสดิการแห่งรัฐ' },
  { icon: 'fi-rr-megaphone', title: 'ข่าวประกาศ', desc: 'ข่าวสารและประกาศล่าสุดจากกระทรวงการคลัง' },
  { icon: 'fi-rr-marker', title: 'ค้นหาหน่วยลงทะเบียน', desc: 'ค้นหาจุด/หน่วยรับลงทะเบียนใกล้บ้าน' },
  { icon: 'fi-rr-download', title: 'ดาวน์โหลดคู่มือ/เอกสาร', desc: 'คู่มือการลงทะเบียนและเอกสารที่เกี่ยวข้อง' },
];

// เอกสารเผยแพร่ (ลิงก์ไปเว็บหลัก)
const docs = [
  'ข้อกำหนดและเงื่อนไขการให้บริการ',
  'MOF Privacy Notice — โครงการลงทะเบียนฯ (ฉบับย่อ)',
  'หนังสือมอบอำนาจลงทะเบียน ปี 69',
  'คู่มือการลงทะเบียนเพื่อสวัสดิการแห่งรัฐ ปี 2569',
  'แถลงข่าวกระทรวงการคลัง ฉบับที่ 41/2569',
];

// กระบวนการดำเนินงาน 5 ขั้น (จากอินโฟกราฟิกโครงการ)
const steps = [
  { n: 1, title: 'วิเคราะห์สิทธิ เกณฑ์ผู้มีสิทธิ', desc: 'มร.นม. เตรียมข้อมูลเกณฑ์สิทธิของผู้ลงทะเบียน' },
  { n: 2, title: 'วิเคราะห์ฐานข้อมูลและรายชื่อ', desc: 'ส่งรายชื่อจากฐานข้อมูล DSS สำหรับผู้มีคุณสมบัติ' },
  { n: 3, title: 'เตรียมความพร้อมกลุ่มเป้าหมายผ่านเวทีชี้แจง', desc: 'จัดเวที 1 ตำบล 1 ครั้ง ให้ความรู้สิทธิประโยชน์' },
  { n: 4, title: 'กลไกช่วยลงทะเบียน', desc: 'ตั้งจุดบริการประจำอำเภอ + ทีมพิเศษลงเยี่ยมบ้านผู้ป่วยติดเตียง' },
  { n: 5, title: 'ตรวจสอบ ติดตามและประเมินผล', desc: 'ระบบติดตามการลงทะเบียน Dashboard เรียลไทม์' },
];

const partners = [
  'ศูนย์ศึกษาและพัฒนาโคราช มหาวิทยาลัยราชภัฏนครราชสีมา',
  'สำนักงานจังหวัดนครราชสีมา',
  'สำนักงานคลังจังหวัดนครราชสีมา',
  'สำนักงานพัฒนาชุมชนจังหวัดนครราชสีมา',
  'สำนักงานพัฒนาสังคมและความมั่นคงของมนุษย์จังหวัดนครราชสีมา',
];

function goBack() {
  if (auth.isAuth) router.push(auth.isStudent ? { name: 'student-worklog' } : { name: 'dashboard' });
  else router.push({ name: 'home' });
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
    <PublicNav />

    <main class="max-w-5xl mx-auto px-4 py-8 space-y-6">
      <!-- บริบทโครงการ -->
      <section class="card p-6">
        <h1 class="text-xl font-bold">โครงการขับเคลื่อนพื้นที่วิจัยเชิงยุทธศาสตร์เพื่อขจัดความยากจน จ.นครราชสีมา</h1>
        <p class="text-sm text-slate-600 dark:text-slate-300 mt-2 leading-relaxed">
          บูรณาการความร่วมมือระหว่างมหาวิทยาลัยราชภัฏนครราชสีมา ร่วมกับจังหวัดนครราชสีมาและหน่วยงานเครือข่าย
          เพื่อช่วยประชาชนกลุ่มเป้าหมายเข้าถึงบัตรสวัสดิการแห่งรัฐ ปี 2569
        </p>
        <div class="mt-4 inline-flex items-baseline gap-2 card-tint-blue px-4 py-2">
          <span class="text-2xl font-bold">36,606</span>
          <span class="text-xs">คน — ผู้มีคุณสมบัติได้สิทธิที่ยังไม่ได้รับบัตร</span>
        </div>
      </section>

      <!-- เมนูเว็บหลัก -->
      <section>
        <h2 class="text-lg font-semibold mb-3">ข้อมูลจากเว็บไซต์หลัก (กระทรวงการคลัง)</h2>
        <div class="grid sm:grid-cols-2 gap-3">
          <a v-for="m in menus" :key="m.title" :href="OFFICIAL" target="_blank" rel="noopener"
             class="card p-4 hover:shadow-md transition flex gap-3 items-start">
            <i :class="m.icon + ' text-xl text-blue-600 mt-0.5'"></i>
            <div>
              <div class="font-medium text-sm flex items-center gap-1">{{ m.title }} <i class="fi-rr-arrow-up-right-from-square text-[10px] text-slate-400"></i></div>
              <div class="text-xs text-slate-500 mt-0.5">{{ m.desc }}</div>
            </div>
          </a>
        </div>
      </section>

      <!-- เอกสารเผยแพร่ -->
      <section class="card p-6">
        <h2 class="text-lg font-semibold mb-3"><i class="fi-rr-document text-blue-600"></i> เอกสารเผยแพร่</h2>
        <ul class="space-y-2">
          <li v-for="doc in docs" :key="doc">
            <a :href="OFFICIAL" target="_blank" rel="noopener" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 hover:text-blue-700">
              <i class="fi-rr-file-pdf text-red-500"></i> {{ doc }}
              <i class="fi-rr-arrow-up-right-from-square text-[10px] text-slate-400"></i>
            </a>
          </li>
        </ul>
        <p class="text-xs text-slate-400 mt-3">* ลิงก์เปิดไปยังเว็บไซต์ทางการ welfare.mof.go.th เพื่อดาวน์โหลดเอกสารฉบับล่าสุด</p>
      </section>

      <!-- กระบวนการ 5 ขั้น -->
      <section>
        <h2 class="text-lg font-semibold mb-3">กระบวนการดำเนินงาน</h2>
        <div class="grid gap-3">
          <div v-for="s in steps" :key="s.n" class="card p-4 flex gap-3 items-start">
            <div class="shrink-0 w-8 h-8 rounded-full bg-blue-700 text-white grid place-items-center font-bold text-sm">{{ s.n }}</div>
            <div>
              <div class="font-medium text-sm">{{ s.title }}</div>
              <div class="text-xs text-slate-500 mt-0.5">{{ s.desc }}</div>
            </div>
          </div>
        </div>
      </section>

      <!-- หน่วยงานร่วม -->
      <section class="card p-6">
        <h2 class="text-lg font-semibold mb-3">หน่วยงานร่วมดำเนินการ</h2>
        <ul class="grid sm:grid-cols-2 gap-2 text-sm text-slate-600 dark:text-slate-300">
          <li v-for="p in partners" :key="p" class="flex items-start gap-2"><i class="fi-rr-check text-green-600 mt-0.5"></i> {{ p }}</li>
        </ul>
      </section>

      <footer class="text-center text-xs text-slate-400 py-6 leading-relaxed">
        © 2569 โครงการการขับเคลื่อนพื้นที่วิจัยเชิงยุทธศาสตร์เพื่อขจัดความยากจน
        และสร้างโอกาสทางสังคมแบบบูรณาการ จังหวัดนครราชสีมา<br>
        ศูนย์ศึกษาและพัฒนาโคราช มหาวิทยาลัยราชภัฏนครราชสีมา
      </footer>
    </main>
  </div>
</template>

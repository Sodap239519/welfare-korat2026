<script setup>
import PublicNav from '@/components/PublicNav.vue';

const OFFICIAL = 'https://welfare.mof.go.th/';
const IMG = 'https://welfare.mof.go.th/assets/images/New/';

// เมนูหลักจากเว็บไซต์กระทรวงการคลัง — ใช้เป็น in-page nav (เลื่อนไปหัวข้อ)
const sections = [
  { id: 'period', label: 'ช่วงเวลาลงทะเบียน' },
  { id: 'eligibility', label: 'คุณสมบัติและข้อกำหนด' },
  { id: 'steps', label: 'ขั้นตอนการลงทะเบียน' },
  { id: 'about', label: 'สิทธิประโยชน์' },
  { id: 'news', label: 'ข่าวประกาศ' },
  { id: 'search', label: 'ค้นหาหน่วยลงทะเบียน' },
  { id: 'downloads', label: 'ดาวน์โหลดเอกสาร' },
  { id: 'contact', label: 'ติดต่อ' },
];

// คุณสมบัติผู้มีสิทธิ
const qualifications = [
  'สัญชาติไทย อายุ 18 ปีขึ้นไป',
  'รายได้ไม่เกิน 100,000 บาท/ปี',
  'ไม่เป็นข้าราชการ / พนักงานของรัฐ / นักเรียนนักศึกษา (ตามเกณฑ์)',
  'เป็นไปตามหลักเกณฑ์ด้านทรัพย์สิน หนี้สิน และรายได้ครอบครัว',
];

// 5 ช่องทางลงทะเบียน
const steps = [
  { title: 'เว็บไซต์', img: IMG + 'regstep_02_register_website.webp', icon: 'fi-rr-globe' },
  { title: 'แอปเป๋าตัง', img: IMG + 'regstep_03_register_paotang.webp', icon: 'fi-rr-wallet' },
  { title: 'ธนาคาร 5 แห่ง', img: IMG + 'regstep_04_register_bank.webp', icon: 'fi-rr-bank' },
  { title: 'ตู้ ATM', img: IMG + 'regstep_05_register_atm.webp', icon: 'fi-rr-credit-card' },
  { title: 'แอปทางรัฐ', img: IMG + 'regstep_06_register_gov.webp', icon: 'fi-rr-smartphone' },
];

// สิทธิประโยชน์
const benefits = [
  { label: 'ค่าซื้อสินค้าอุปโภคบริโภค', value: '300', unit: 'บาท/เดือน', icon: 'fi-rr-shopping-cart', tint: 'card-tint-blue' },
  { label: 'ค่าเดินทางรถโดยสารสาธารณะ', value: '750', unit: 'บาท/เดือน', icon: 'fi-rr-bus', tint: 'card-tint-sky' },
  { label: 'ส่วนลดค่าไฟฟ้า', value: '315', unit: 'บาท/เดือน', icon: 'fi-rr-bolt', tint: 'card-tint-orange' },
  { label: 'ส่วนลดค่าน้ำประปา', value: '100', unit: 'บาท/เดือน', icon: 'fi-rr-drop', tint: 'card-tint-green' },
];

// เอกสารเผยแพร่
const docs = [
  'ข้อกำหนดและเงื่อนไขการให้บริการ',
  'MOF Privacy Notice — โครงการลงทะเบียนฯ (ฉบับย่อ)',
  'หนังสือมอบอำนาจลงทะเบียน ฉบับสมบูรณ์ ปี 69',
  'คู่มือการลงทะเบียนเพื่อสวัสดิการแห่งรัฐ ปี 2569',
  'แถลงข่าวกระทรวงการคลัง ฉบับที่ 41-2569',
];

// ช่องทางติดต่อ
const contacts = [
  { name: 'ศูนย์ลูกค้าสัมพันธ์ (จันทร์-ศุกร์ 08:30-17:30)', tel: '02-109-2345' },
  { name: 'สำนักงานปลัดกระทรวงการคลัง', tel: '02-126-5900 ต่อ 30353-30355' },
  { name: 'สำนักบริหารการทะเบียน (มหาดไทย)', tel: '02-791-7517' },
  { name: 'สำนักงานเศรษฐกิจการคลัง', tel: '08-5842-7102 ถึง 7109' },
  { name: 'ศูนย์ช่วยเหลือสังคม (พม.)', tel: '1300' },
];

// หน่วยงานร่วม (โครงการโคราช)
const partners = [
  'ศูนย์ศึกษาและพัฒนาโคราช มหาวิทยาลัยราชภัฏนครราชสีมา',
  'สำนักงานจังหวัดนครราชสีมา',
  'สำนักงานคลังจังหวัดนครราชสีมา',
  'สำนักงานพัฒนาชุมชนจังหวัดนครราชสีมา',
  'สำนักงานพัฒนาสังคมและความมั่นคงของมนุษย์จังหวัดนครราชสีมา',
];

// ซ่อนรูปที่โหลดไม่ได้ (กัน hotlink protection)
function onImgError(e) { e.target.style.display = 'none'; }
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
    <PublicNav />

    <!-- Hero พร้อมรูปแบนเนอร์จริงจากเว็บหลัก -->
    <section class="relative overflow-hidden card-hero">
      <img :src="IMG + 'banner.webp'" referrerpolicy="no-referrer" @error="onImgError"
           class="absolute inset-0 w-full h-full object-cover opacity-25" alt="">
      <div class="relative max-w-5xl mx-auto px-4 py-12 lg:py-16">
        <h1 class="text-2xl lg:text-4xl font-bold leading-tight">โครงการลงทะเบียนเพื่อสวัสดิการแห่งรัฐ ปี 2569</h1>
        <p class="mt-3 opacity-90 text-sm lg:text-base max-w-2xl">
          ข้อมูลโครงการจากกระทรวงการคลัง พร้อมการขับเคลื่อนในพื้นที่จังหวัดนครราชสีมา
          โดยศูนย์ศึกษาและพัฒนาโคราช มหาวิทยาลัยราชภัฏนครราชสีมา
        </p>
        <a :href="OFFICIAL" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1 mt-5 bg-white text-blue-700 rounded-xl px-5 py-2.5 font-medium text-sm hover:bg-blue-50">
          ไปเว็บไซต์ทางการ <i class="fi-rr-arrow-up-right-from-square"></i>
        </a>
      </div>
    </section>

    <!-- in-page nav -->
    <div class="sticky top-16 z-20 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-b border-slate-100 dark:border-slate-800">
      <div class="max-w-5xl mx-auto px-4 py-2 flex gap-2 overflow-x-auto text-sm">
        <a v-for="s in sections" :key="s.id" :href="'#' + s.id"
           class="whitespace-nowrap px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-blue-100 dark:hover:bg-blue-900/40">{{ s.label }}</a>
      </div>
    </div>

    <main class="max-w-5xl mx-auto px-4 py-8 space-y-10">
      <!-- ช่วงเวลา -->
      <section id="period" class="scroll-mt-32">
        <div class="card-tint-blue p-6 flex flex-col sm:flex-row items-center gap-4">
          <i class="fi-rr-calendar-clock text-4xl text-blue-700"></i>
          <div>
            <div class="text-sm opacity-70">ช่วงเวลาลงทะเบียน</div>
            <div class="text-2xl lg:text-3xl font-bold">4 – 21 มิถุนายน 2569</div>
          </div>
        </div>
      </section>

      <!-- คุณสมบัติ -->
      <section id="eligibility" class="scroll-mt-32">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-list-check text-blue-600"></i> คุณสมบัติและข้อกำหนด</h2>
        <div class="grid md:grid-cols-2 gap-4 items-center">
          <ul class="space-y-2">
            <li v-for="q in qualifications" :key="q" class="card p-3 flex items-start gap-2 text-sm">
              <i class="fi-rr-check text-green-600 mt-0.5"></i> {{ q }}
            </li>
          </ul>
          <img :src="IMG + 'registrant_qualifications.webp'" referrerpolicy="no-referrer" @error="onImgError" loading="lazy"
               class="rounded-2xl w-full border border-slate-100 dark:border-slate-800" alt="คุณสมบัติผู้ลงทะเบียน">
        </div>
      </section>

      <!-- ขั้นตอน/ช่องทาง -->
      <section id="steps" class="scroll-mt-32">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-following text-blue-600"></i> ช่องทางการลงทะเบียน (5 ช่องทาง)</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <div v-for="(s, i) in steps" :key="s.title" class="card p-3 text-center">
            <div class="aspect-square rounded-xl overflow-hidden bg-slate-50 dark:bg-slate-800 grid place-items-center mb-2">
              <img :src="s.img" referrerpolicy="no-referrer" @error="onImgError" loading="lazy" class="w-full h-full object-contain" :alt="s.title">
            </div>
            <div class="text-xs font-medium"><i :class="s.icon + ' text-blue-600'"></i> {{ i + 1 }}. {{ s.title }}</div>
          </div>
        </div>
      </section>

      <!-- สิทธิประโยชน์ -->
      <section id="about" class="scroll-mt-32">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-gift text-blue-600"></i> สิทธิประโยชน์ที่ได้รับ</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div v-for="b in benefits" :key="b.label" :class="['p-4', b.tint]">
            <i :class="b.icon + ' text-xl'"></i>
            <div class="mt-1"><span class="text-2xl font-bold">{{ b.value }}</span> <span class="text-xs">{{ b.unit }}</span></div>
            <div class="text-xs opacity-80 mt-0.5 leading-snug">{{ b.label }}</div>
          </div>
        </div>
        <p class="text-xs text-slate-400 mt-2">* เป็นโครงการบรรเทาภาระค่าครองชีพแก่ผู้มีรายได้น้อย</p>
      </section>

      <!-- ข่าวประกาศ -->
      <section id="news" class="scroll-mt-32">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-megaphone text-blue-600"></i> ข่าวประกาศ</h2>
        <a :href="OFFICIAL" target="_blank" rel="noopener" class="card p-4 flex items-center gap-3 hover:shadow-md transition">
          <i class="fi-rr-document text-2xl text-blue-600"></i>
          <div class="flex-1">
            <div class="font-medium text-sm">แถลงข่าวกระทรวงการคลัง ฉบับที่ 41-2569</div>
            <div class="text-xs text-slate-500">ประกาศเปิดลงทะเบียนโครงการบัตรสวัสดิการแห่งรัฐ ปี 2569</div>
          </div>
          <i class="fi-rr-arrow-up-right-from-square text-slate-400"></i>
        </a>
      </section>

      <!-- ค้นหาหน่วยลงทะเบียน -->
      <section id="search" class="scroll-mt-32">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-marker text-blue-600"></i> ค้นหาหน่วยลงทะเบียน</h2>
        <div class="card p-5">
          <p class="text-sm text-slate-600 dark:text-slate-300">ค้นหาจุด/หน่วยรับลงทะเบียนใกล้บ้าน รวมถึงธนาคารตัวแทนรับลงทะเบียนทั้ง 5 แห่ง</p>
          <a :href="OFFICIAL" target="_blank" rel="noopener" class="inline-flex items-center gap-1 mt-3 text-sm text-blue-700 font-medium">
            ค้นหาที่เว็บไซต์ทางการ <i class="fi-rr-arrow-up-right-from-square"></i>
          </a>
        </div>
      </section>

      <!-- ดาวน์โหลด -->
      <section id="downloads" class="scroll-mt-32">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-download text-blue-600"></i> ดาวน์โหลดคู่มือ/เอกสาร</h2>
        <ul class="grid sm:grid-cols-2 gap-2">
          <li v-for="doc in docs" :key="doc">
            <a :href="OFFICIAL" target="_blank" rel="noopener" class="card p-3 flex items-center gap-2 text-sm hover:shadow-md transition">
              <i class="fi-rr-file-pdf text-red-500 text-lg"></i>
              <span class="flex-1">{{ doc }}</span>
              <i class="fi-rr-arrow-up-right-from-square text-[10px] text-slate-400"></i>
            </a>
          </li>
        </ul>
        <p class="text-xs text-slate-400 mt-2">* เปิดไปยังเว็บไซต์ทางการเพื่อดาวน์โหลดไฟล์ฉบับล่าสุด</p>
      </section>

      <!-- ติดต่อ -->
      <section id="contact" class="scroll-mt-32">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-headset text-blue-600"></i> ช่องทางติดต่อ</h2>
        <div class="grid sm:grid-cols-2 gap-2">
          <div v-for="c in contacts" :key="c.name" class="card p-3 flex items-center gap-3">
            <i class="fi-rr-phone-call text-blue-600"></i>
            <div>
              <div class="text-sm font-medium">{{ c.tel }}</div>
              <div class="text-xs text-slate-500">{{ c.name }}</div>
            </div>
          </div>
        </div>
      </section>

      <!-- บริบทโครงการโคราช -->
      <section class="card-tint-sky p-6">
        <h2 class="text-lg font-bold mb-2">การขับเคลื่อนในพื้นที่จังหวัดนครราชสีมา</h2>
        <p class="text-sm opacity-80 leading-relaxed">
          ภายใต้โครงการการขับเคลื่อนพื้นที่วิจัยเชิงยุทธศาสตร์เพื่อขจัดความยากจนและสร้างโอกาสทางสังคมแบบบูรณาการ
          จังหวัดนครราชสีมา — มีผู้มีคุณสมบัติได้สิทธิที่ยังไม่ได้รับบัตร <b>36,606 คน</b>
          โดยนักศึกษา มร.นม. ลงพื้นที่ช่วยเหลือการลงทะเบียน
        </p>
        <div class="mt-3">
          <div class="text-xs font-medium mb-1.5">หน่วยงานร่วมดำเนินการ</div>
          <ul class="grid sm:grid-cols-2 gap-1.5 text-sm opacity-90">
            <li v-for="p in partners" :key="p" class="flex items-start gap-2"><i class="fi-rr-check text-green-600 mt-0.5"></i> {{ p }}</li>
          </ul>
        </div>
      </section>

      <footer class="text-center text-xs text-slate-400 py-6 leading-relaxed">
        © 2569 โครงการการขับเคลื่อนพื้นที่วิจัยเชิงยุทธศาสตร์เพื่อขจัดความยากจน
        และสร้างโอกาสทางสังคมแบบบูรณาการ จังหวัดนครราชสีมา<br>
        ศูนย์ศึกษาและพัฒนาโคราช มหาวิทยาลัยราชภัฏนครราชสีมา
      </footer>
    </main>
  </div>
</template>

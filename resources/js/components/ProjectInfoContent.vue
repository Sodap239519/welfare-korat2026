<script setup>
import { ref } from 'vue';

defineProps({ embedded: { type: Boolean, default: false } });

const IMG = 'https://welfare.mof.go.th/assets/images/New/';

// คลิกภาพเพื่อขยายดูเต็ม (lightbox)
const zoom = ref(null);
function openZoom(src) { zoom.value = src; }

const sections = [
  { id: 'period', label: 'ช่วงเวลาลงทะเบียน' },
  { id: 'eligibility', label: 'คุณสมบัติและข้อกำหนด' },
  { id: 'steps', label: 'ขั้นตอนการลงทะเบียน' },
  { id: 'about', label: 'สิทธิประโยชน์' },
  { id: 'news', label: 'ข่าวประกาศ' },
  { id: 'search', label: 'ค้นหาหน่วยลงทะเบียน' },
  { id: 'downloads', label: 'เอกสาร' },
  { id: 'contact', label: 'ติดต่อ' },
];

const steps = [
  { title: 'เว็บไซต์', img: IMG + 'regstep_02_register_website.webp', icon: 'fi-rr-globe' },
  { title: 'แอปเป๋าตัง', img: IMG + 'regstep_03_register_paotang.webp', icon: 'fi-rr-wallet' },
  { title: 'ธนาคาร 5 แห่ง', img: IMG + 'regstep_04_register_bank.webp', icon: 'fi-rr-bank' },
  { title: 'ตู้ ATM', img: IMG + 'regstep_05_register_atm.webp', icon: 'fi-rr-credit-card' },
  { title: 'แอปทางรัฐ', img: IMG + 'regstep_06_register_gov.webp', icon: 'fi-rr-smartphone' },
];

const benefits = [
  { label: 'ค่าซื้อสินค้าอุปโภคบริโภค', value: '300', unit: 'บาท/เดือน', icon: 'fi-rr-shopping-cart', tint: 'card-tint-blue' },
  { label: 'ค่าเดินทางรถโดยสารสาธารณะ', value: '750', unit: 'บาท/เดือน', icon: 'fi-rr-bus', tint: 'card-tint-sky' },
  { label: 'ส่วนลดค่าไฟฟ้า', value: '315', unit: 'บาท/เดือน', icon: 'fi-rr-bolt', tint: 'card-tint-orange' },
  { label: 'ส่วนลดค่าน้ำประปา', value: '100', unit: 'บาท/เดือน', icon: 'fi-rr-drop', tint: 'card-tint-green' },
];

const docs = [
  'ข้อกำหนดและเงื่อนไขการให้บริการ',
  'MOF Privacy Notice — โครงการลงทะเบียนฯ (ฉบับย่อ)',
  'หนังสือมอบอำนาจลงทะเบียน ฉบับสมบูรณ์ ปี 69',
  'คู่มือการลงทะเบียนเพื่อสวัสดิการแห่งรัฐ ปี 2569',
  'แถลงข่าวกระทรวงการคลัง ฉบับที่ 41-2569',
];

const contacts = [
  { name: 'ศูนย์ลูกค้าสัมพันธ์ (จันทร์-ศุกร์ 08:30-17:30)', tel: '02-109-2345' },
  { name: 'สำนักงานปลัดกระทรวงการคลัง', tel: '02-126-5900 ต่อ 30353-30355' },
  { name: 'สำนักบริหารการทะเบียน (มหาดไทย)', tel: '02-791-7517' },
  { name: 'สำนักงานเศรษฐกิจการคลัง', tel: '08-5842-7102 ถึง 7109' },
  { name: 'ศูนย์ช่วยเหลือสังคม (พม.)', tel: '1300' },
];

const partners = [
  'ศูนย์ศึกษาและพัฒนาโคราช มหาวิทยาลัยราชภัฏนครราชสีมา',
  'สำนักงานจังหวัดนครราชสีมา',
  'สำนักงานคลังจังหวัดนครราชสีมา',
  'สำนักงานพัฒนาชุมชนจังหวัดนครราชสีมา',
  'สำนักงานพัฒนาสังคมและความมั่นคงของมนุษย์จังหวัดนครราชสีมา',
];

function onImgError(e) { e.target.style.display = 'none'; }
</script>

<template>
  <div>
    <!-- Hero แบนเนอร์ (ซ่อนเมื่ออยู่ในแอป เพราะมี title บน topbar แล้ว) -->
    <section v-if="!embedded" class="relative overflow-hidden card-hero">
      <img :src="IMG + 'banner.webp'" referrerpolicy="no-referrer" @error="onImgError"
           class="absolute inset-0 w-full h-full object-cover opacity-25" alt="">
      <div class="relative max-w-4xl mx-auto px-4 py-10 lg:py-14">
        <h1 class="text-2xl lg:text-4xl font-bold leading-tight">โครงการลงทะเบียนเพื่อสวัสดิการแห่งรัฐ ปี 2569</h1>
        <p class="mt-3 opacity-90 text-sm lg:text-base max-w-2xl">
          ข้อมูลโครงการจากกระทรวงการคลัง พร้อมการขับเคลื่อนในพื้นที่จังหวัดนครราชสีมา
          โดยศูนย์ศึกษาและพัฒนาโคราช มหาวิทยาลัยราชภัฏนครราชสีมา
        </p>
      </div>
    </section>

    <div class="max-w-4xl mx-auto px-4">
      <!-- in-page nav (กว้างเท่าเนื้อหา ไม่กางเต็มจอ) -->
      <div :class="['sticky z-20 -mx-4 px-4 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-b border-slate-100 dark:border-slate-800', embedded ? 'top-0' : 'top-16']">
        <div class="py-2 flex gap-2 overflow-x-auto text-sm">
          <a v-for="s in sections" :key="s.id" :href="'#' + s.id"
             class="whitespace-nowrap px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-blue-100 dark:hover:bg-blue-900/40">{{ s.label }}</a>
        </div>
      </div>

      <div class="py-8 space-y-10">
      <!-- ช่วงเวลา -->
      <section id="period" class="scroll-mt-24">
        <div class="card-tint-blue p-6 flex flex-col sm:flex-row items-center gap-4 text-center sm:text-left">
          <i class="fi-rr-calendar-clock text-4xl text-blue-700"></i>
          <div>
            <div class="text-sm opacity-70">ช่วงเวลาลงทะเบียน</div>
            <div class="text-2xl lg:text-3xl font-bold">4 – 21 มิถุนายน 2569</div>
          </div>
        </div>
      </section>

      <!-- คุณสมบัติ -->
      <section id="eligibility" class="scroll-mt-24">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-list-check text-blue-600"></i> คุณสมบัติและข้อกำหนด</h2>
        <img :src="IMG + 'registrant_qualifications.webp'" referrerpolicy="no-referrer" @error="onImgError" loading="lazy"
             @click="openZoom(IMG + 'registrant_qualifications.webp')"
             class="rounded-2xl w-full max-w-3xl mx-auto block border border-slate-100 dark:border-slate-800 cursor-zoom-in hover:opacity-95 transition" alt="คุณสมบัติผู้ลงทะเบียน">
        <p class="text-center text-xs text-slate-400 mt-2"><i class="fi-rr-search"></i> แตะที่ภาพเพื่อดูขนาดเต็ม</p>
      </section>

      <!-- ช่องทาง -->
      <section id="steps" class="scroll-mt-24">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-following text-blue-600"></i> ช่องทางการลงทะเบียน (5 ช่องทาง)</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <div v-for="(s, i) in steps" :key="s.title" class="card p-3 text-center">
            <div class="aspect-[3/4] rounded-xl overflow-hidden bg-slate-50 dark:bg-slate-800 mb-2 cursor-zoom-in" @click="openZoom(s.img)">
              <img :src="s.img" referrerpolicy="no-referrer" @error="onImgError" loading="lazy" class="w-full h-full object-cover hover:scale-105 transition" :alt="s.title">
            </div>
            <div class="text-xs font-medium"><i :class="s.icon + ' text-blue-600'"></i> {{ i + 1 }}. {{ s.title }}</div>
          </div>
        </div>
      </section>

      <!-- สิทธิประโยชน์ -->
      <section id="about" class="scroll-mt-24">
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

      <!-- ข่าว -->
      <section id="news" class="scroll-mt-24">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-megaphone text-blue-600"></i> ข่าวประกาศ</h2>
        <div class="card p-4 flex items-center gap-3">
          <i class="fi-rr-document text-2xl text-blue-600"></i>
          <div>
            <div class="font-medium text-sm">แถลงข่าวกระทรวงการคลัง ฉบับที่ 41-2569</div>
            <div class="text-xs text-slate-500">ประกาศเปิดลงทะเบียนโครงการบัตรสวัสดิการแห่งรัฐ ปี 2569</div>
          </div>
        </div>
      </section>

      <!-- ค้นหาหน่วย -->
      <section id="search" class="scroll-mt-24">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-marker text-blue-600"></i> ค้นหาหน่วยลงทะเบียน</h2>
        <div class="card p-5 text-sm text-slate-600 dark:text-slate-300">
          จุด/หน่วยรับลงทะเบียนใกล้บ้าน รวมถึงธนาคารตัวแทนทั้ง 5 แห่ง (กรุงไทย · ออมสิน · ธ.ก.ส. · อาคารสงเคราะห์ · อิสลาม)
          และจุดบริการประจำอำเภอที่จัดโดยโครงการในจังหวัดนครราชสีมา
        </div>
      </section>

      <!-- เอกสาร -->
      <section id="downloads" class="scroll-mt-24">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-file text-blue-600"></i> เอกสารที่เกี่ยวข้อง</h2>
        <ul class="grid sm:grid-cols-2 gap-2">
          <li v-for="doc in docs" :key="doc" class="card p-3 flex items-center gap-2 text-sm">
            <i class="fi-rr-file-pdf text-red-500 text-lg"></i>
            <span class="flex-1">{{ doc }}</span>
          </li>
        </ul>
      </section>

      <!-- ติดต่อ -->
      <section id="contact" class="scroll-mt-24">
        <h2 class="text-xl font-bold mb-4"><i class="fi-rr-headset text-blue-600"></i> ช่องทางติดต่อ</h2>
        <div class="grid sm:grid-cols-2 gap-2">
          <a v-for="c in contacts" :key="c.name" :href="'tel:' + c.tel.replace(/[^0-9]/g, '')" class="card p-3 flex items-center gap-3">
            <i class="fi-rr-phone-call text-blue-600"></i>
            <div>
              <div class="text-sm font-medium">{{ c.tel }}</div>
              <div class="text-xs text-slate-500">{{ c.name }}</div>
            </div>
          </a>
        </div>
      </section>

      <!-- บริบทโคราช -->
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
      </div>
    </div>

    <!-- Lightbox -->
    <div v-if="zoom" @click="zoom = null"
         class="fixed inset-0 z-[60] bg-black/85 grid place-items-center p-4 cursor-zoom-out">
      <img :src="zoom" referrerpolicy="no-referrer" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl" alt="">
      <button class="absolute top-4 right-4 w-10 h-10 grid place-items-center rounded-full bg-white/15 text-white hover:bg-white/25 text-xl">
        <i class="fi-rr-cross-small"></i>
      </button>
    </div>
  </div>
</template>

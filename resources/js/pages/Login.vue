<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
const router = useRouter();
const tab = ref('login');
const email = ref('');
const password = ref('');

function submit() {
  router.push({ name: 'dashboard' });
}
</script>

<template>
  <div class="min-h-screen lg:flex">

    <!-- Brand hero -->
    <div class="card-hero lg:w-1/2 lg:min-h-screen p-6 lg:p-10 rounded-none lg:rounded-r-[40px] flex flex-col">
      <div class="absolute top-4 right-4 lg:hidden flex gap-1">
        <button @click="theme.smaller" class="px-2 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 text-xs">A−</button>
        <button @click="theme.bigger" class="px-2 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 text-base font-semibold">A+</button>
        <button @click="theme.toggle" class="ml-1 p-2 rounded-lg bg-white/15 hover:bg-white/25">
          <i v-if="!theme.isDark" class="fi-rr-brightness text-base"></i>
          <i v-else class="fi-sr-moon text-base text-orange-300"></i>
        </button>
      </div>

      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur flex items-center justify-center">
          <i class="fi-sr-shield-check text-xl"></i>
        </div>
        <div class="leading-tight">
          <div class="font-semibold">Welfare Korat</div>
          <div class="text-xs opacity-80">บัตรสวัสดิการแห่งรัฐ 2569</div>
        </div>
      </div>

      <div class="flex-1 flex flex-col justify-center py-8 lg:py-0">
        <h1 class="text-2xl lg:text-4xl font-semibold leading-tight">
          ติดตามสถานะ<br>การลงทะเบียน<br><span class="opacity-90">แบบเรียลไทม์</span>
        </h1>
        <p class="text-sm lg:text-base opacity-90 mt-3 max-w-md">
          ระบบติดตามรอบใหม่ปี 2569 สำหรับผู้กำกับติดตามรายหมู่บ้าน · ตำบล · อำเภอ จังหวัดนครราชสีมา
        </p>
        <div class="grid grid-cols-3 gap-3 mt-6 max-w-md">
          <div class="bg-white/10 backdrop-blur rounded-2xl p-3"><div class="text-lg lg:text-2xl font-bold">61,743</div><div class="text-[10px] lg:text-xs opacity-80">เป้าหมาย</div></div>
          <div class="bg-white/10 backdrop-blur rounded-2xl p-3"><div class="text-lg lg:text-2xl font-bold">1,924</div><div class="text-[10px] lg:text-xs opacity-80">หมู่บ้าน</div></div>
          <div class="bg-white/10 backdrop-blur rounded-2xl p-3"><div class="text-lg lg:text-2xl font-bold">32</div><div class="text-[10px] lg:text-xs opacity-80">อำเภอ</div></div>
        </div>
      </div>
      <div class="text-xs opacity-70 hidden lg:block">© 2569 ผู้ว่าราชการจังหวัดนครราชสีมา</div>
    </div>

    <!-- Form -->
    <div class="lg:w-1/2 flex flex-col">
      <div class="hidden lg:flex justify-end gap-1 p-4">
        <button @click="theme.smaller" class="px-2 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-xs">A−</button>
        <button @click="theme.bigger" class="px-2 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-base font-semibold">A+</button>
        <button @click="theme.toggle" class="ml-1 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800">
          <i v-if="!theme.isDark" class="fi-rr-brightness text-lg"></i>
          <i v-else class="fi-sr-moon text-lg text-orange-400"></i>
        </button>
      </div>

      <div class="flex-1 flex items-center justify-center p-5 lg:p-10">
        <div class="w-full max-w-sm">
          <h2 class="text-2xl font-semibold">ยินดีต้อนรับกลับ</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">เข้าสู่ระบบเพื่อเริ่มทำงาน</p>

          <div class="flex gap-1 mt-5 bg-slate-50 dark:bg-slate-800/50 rounded-xl p-1">
            <button @click="tab='login'" :class="['flex-1 py-2 text-sm font-medium rounded-lg', tab==='login' ? 'bg-white dark:bg-slate-700 shadow-sm' : 'text-slate-500']">เข้าสู่ระบบ</button>
            <button @click="tab='register'" :class="['flex-1 py-2 text-sm font-medium rounded-lg', tab==='register' ? 'bg-white dark:bg-slate-700 shadow-sm' : 'text-slate-500']">ลงทะเบียน</button>
          </div>

          <form v-if="tab==='login'" @submit.prevent="submit" class="space-y-4 mt-5">
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">อีเมล</label>
              <div class="relative">
                <i class="fi-rr-envelope absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input v-model="email" type="email" placeholder="name@example.com" class="w-full pl-10 pr-3 py-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500">
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">รหัสผ่าน</label>
              <div class="relative">
                <i class="fi-rr-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input v-model="password" type="password" placeholder="••••••••" class="w-full pl-10 pr-3 py-3 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500">
              </div>
            </div>
            <button type="submit" class="btn-primary block w-full text-center py-3 font-medium">เข้าสู่ระบบ <i class="fi-rr-arrow-small-right"></i></button>
          </form>

          <div v-else class="mt-5 card-tint-blue p-4 text-sm">
            <i class="fi-rr-info"></i> ฟอร์มลงทะเบียนจะใส่ใน Phase 1.5 (auth backend) — ตอนนี้ใช้ปุ่ม "เข้าสู่ระบบ" เพื่อดู Dashboard ก่อน
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { RouterLink } from 'vue-router';
</script>
<template>
  <div class="min-h-screen flex items-center justify-center p-6 bg-gradient-to-br from-blue-50 to-sky-50 dark:from-slate-900 dark:to-slate-800">
    <div class="text-center max-w-md">
      <!-- Illustration: search icon with X -->
      <div class="relative inline-flex">
        <div class="w-24 h-24 rounded-full card-tint-blue flex items-center justify-center mb-4">
          <i class="fi-rr-map text-5xl text-blue-700 dark:text-blue-300"></i>
        </div>
        <div class="absolute -bottom-1 -right-1 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center border-4 border-blue-50 dark:border-slate-900">
          <i class="fi-rr-cross-small text-red-600"></i>
        </div>
      </div>

      <div class="text-6xl font-bold text-blue-700 dark:text-blue-300 tabular-nums">404</div>
      <div class="text-lg text-slate-700 dark:text-slate-200 mt-2 font-medium">ไม่พบหน้าที่คุณเรียก</div>
      <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">หน้านี้อาจถูกย้าย หรือไม่เคยมีอยู่</div>

      <div class="flex flex-col sm:flex-row gap-2 justify-center mt-6">
        <RouterLink to="/dashboard" class="btn-primary inline-flex items-center justify-center gap-1.5 tap-transparent">
          <i class="fi-rr-apps"></i> กลับหน้า Dashboard
        </RouterLink>
        <RouterLink to="/targets" class="btn-outline inline-flex items-center justify-center gap-1.5 tap-transparent">
          <i class="fi-rr-users-alt"></i> ไปหน้ารายชื่อ
        </RouterLink>
      </div>
    </div>
  </div>
</template>

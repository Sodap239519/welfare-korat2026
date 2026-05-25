<script setup>
import { RouterLink } from 'vue-router';
</script>
<template>
  <div class="min-h-screen flex items-center justify-center p-6">
    <div class="text-center">
      <div class="text-6xl font-bold text-blue-700">404</div>
      <div class="text-lg text-slate-600 dark:text-slate-300 mt-2">ไม่พบหน้าที่คุณเรียก</div>
      <RouterLink to="/" class="btn-primary inline-block mt-4">กลับหน้าแรก</RouterLink>
    </div>
  </div>
</template>

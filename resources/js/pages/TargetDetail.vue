<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useRoute } from 'vue-router';
const route = useRoute();
</script>
<template>
  <AppLayout title="รายละเอียดบุคคล" :subtitle="`ID: ${route.params.id}`">
    <div class="card-tint-blue p-5 text-center">
      <i class="fi-rr-id-badge text-3xl text-blue-700"></i>
      <div class="font-semibold mt-2">รายละเอียดบุคคล + อัปเดตสถานะ</div>
      <div class="text-sm opacity-80 mt-1">เนื้อหาจะถูก port จาก mockup/target-detail.html</div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';

// โฟลเดอร์ Google Drive เอกสารสำหรับนักศึกษา
const FOLDER_ID = '1us2wg5tmRWGCaG1B-xqvSZ4Yerd7Kt0v';
const openUrl = `https://drive.google.com/drive/folders/${FOLDER_ID}`;
const embedUrl = `https://drive.google.com/embeddedfolderview?id=${FOLDER_ID}#grid`;

const iframeOk = ref(true);
</script>

<template>
  <AppLayout title="เอกสารสำหรับนักศึกษา" subtitle="แบบฟอร์ม คู่มือ และเอกสารประกอบการปฏิบัติงาน">
    <div class="space-y-4 max-w-4xl">
      <div class="card p-4 flex flex-col sm:flex-row sm:items-center gap-3">
        <i class="fi-rr-folder-open text-3xl text-blue-600"></i>
        <div class="flex-1">
          <div class="font-medium">คลังเอกสาร (Google Drive)</div>
          <p class="text-sm text-slate-500">ดาวน์โหลดแบบฟอร์มและเอกสารที่เกี่ยวข้องกับการปฏิบัติงานหนุนเสริม</p>
        </div>
        <a :href="openUrl" target="_blank" rel="noopener" class="btn-primary text-sm text-center shrink-0">
          <i class="fi-rr-arrow-up-right-from-square"></i> เปิดใน Google Drive
        </a>
      </div>

      <!-- ฝังโฟลเดอร์ Drive -->
      <div class="card p-2 overflow-hidden">
        <iframe v-if="iframeOk" :src="embedUrl" class="w-full rounded-xl" style="height: 70vh; border: 0;"
                @error="iframeOk = false" title="เอกสารสำหรับนักศึกษา"></iframe>
        <div v-else class="p-10 text-center text-slate-400 text-sm">
          ไม่สามารถแสดงตัวอย่างได้ — กรุณากดปุ่ม "เปิดใน Google Drive" ด้านบน
        </div>
      </div>

      <p class="text-xs text-slate-400">
        <i class="fi-rr-info"></i> หากไม่เห็นรายการเอกสาร โฟลเดอร์อาจตั้งค่าการแชร์เป็นส่วนตัว —
        กรุณาตั้งค่าแชร์เป็น "ทุกคนที่มีลิงก์" เพื่อให้นักศึกษาเข้าถึงได้
      </p>
    </div>
  </AppLayout>
</template>

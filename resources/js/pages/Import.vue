<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { formatNumber, shortDate } from '@/composables/useApi';

const fileInput = ref(null);
const selectedFile = ref(null);
const preview = ref(null);          // { summary, autofix, file }
const lastRun = ref(null);          // { summary, file }
const logs = ref([]);
const uploading = ref(false);
const progress = ref(0);
const stage = ref('idle');          // idle | uploading | analyzing | preview-ready | committing | done | error
const errorMsg = ref('');
const dragOver = ref(false);

async function loadLogs() {
  const { data } = await axios.get('/api/import/logs');
  logs.value = data.data;
}
onMounted(loadLogs);

function pickFile(e) {
  const f = e.target.files?.[0];
  if (f) handleFile(f);
}

function onDrop(e) {
  e.preventDefault();
  dragOver.value = false;
  const f = e.dataTransfer.files?.[0];
  if (f) handleFile(f);
}

async function handleFile(f) {
  const validExt = /\.(xlsx|xls|csv)$/i;
  if (!validExt.test(f.name)) {
    errorMsg.value = 'รองรับเฉพาะไฟล์ .xlsx / .xls / .csv';
    return;
  }
  if (f.size > 50 * 1024 * 1024) {
    errorMsg.value = 'ขนาดไฟล์เกิน 50 MB';
    return;
  }
  errorMsg.value = '';
  selectedFile.value = f;
  preview.value = null;
  lastRun.value = null;
  await runPreview();
}

async function runPreview() {
  if (!selectedFile.value) return;
  stage.value = 'uploading';
  progress.value = 0;
  uploading.value = true;
  errorMsg.value = '';

  const fd = new FormData();
  fd.append('file', selectedFile.value);

  try {
    const { data } = await axios.post('/api/import/preview', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (e) => {
        if (e.total) {
          progress.value = Math.round((e.loaded / e.total) * 100);
          if (progress.value >= 100) stage.value = 'analyzing';
        }
      },
    });
    preview.value = data;
    stage.value = 'preview-ready';
  } catch (e) {
    stage.value = 'error';
    errorMsg.value = e.response?.data?.errors?.file?.[0] || e.response?.data?.message || 'อัปโหลดไม่สำเร็จ';
  } finally {
    uploading.value = false;
  }
}

async function commit() {
  if (!selectedFile.value || !preview.value) return;
  stage.value = 'committing';
  progress.value = 0;
  uploading.value = true;
  const fd = new FormData();
  fd.append('file', selectedFile.value);

  try {
    const { data } = await axios.post('/api/import/run', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (e) => {
        if (e.total) progress.value = Math.round((e.loaded / e.total) * 100);
      },
    });
    lastRun.value = data;
    preview.value = null;
    selectedFile.value = null;
    stage.value = 'done';
    await loadLogs();
  } catch (e) {
    stage.value = 'error';
    errorMsg.value = e.response?.data?.message || 'นำเข้าไม่สำเร็จ';
  } finally {
    uploading.value = false;
  }
}

function reset() {
  selectedFile.value = null;
  preview.value = null;
  lastRun.value = null;
  errorMsg.value = '';
  stage.value = 'idle';
  progress.value = 0;
  if (fileInput.value) fileInput.value.value = '';
}

const showingFixes = computed(() => (preview.value?.autofix || []).filter(r => r.fixed));
</script>

<template>
  <AppLayout title="นำเข้าข้อมูล" subtitle="xlsx / csv · Upsert by รหัสบ้าน + ลำดับ">
    <div class="space-y-4">

      <!-- Drop zone -->
      <div class="card p-5 lg:p-7">
        <div
          @dragenter.prevent="dragOver = true"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop="onDrop"
          :class="['border-2 border-dashed rounded-2xl p-6 lg:p-8 text-center transition cursor-pointer',
                   dragOver ? 'border-blue-500 bg-blue-50/40 dark:bg-blue-900/10' : 'border-blue-200 dark:border-slate-700 hover:border-blue-400']"
          @click="fileInput?.click()"
        >
          <div class="w-16 h-16 mx-auto rounded-2xl card-tint-blue flex items-center justify-center text-2xl mb-3 text-blue-700">
            <i class="fi-rr-cloud-upload-alt"></i>
          </div>
          <div class="font-semibold text-base lg:text-lg">{{ selectedFile?.name || 'ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือก' }}</div>
          <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">รองรับ .xlsx · .csv · สูงสุด 50 MB</div>
          <input ref="fileInput" type="file" class="hidden" accept=".xlsx,.xls,.csv" @change="pickFile">
        </div>

        <div v-if="errorMsg" class="card-tint-red text-sm p-3 mt-3">
          <i class="fi-rr-cross-circle"></i> {{ errorMsg }}
        </div>

        <div v-if="uploading" class="mt-4">
          <div class="flex items-center justify-between text-xs text-slate-600 dark:text-slate-400 mb-1.5">
            <span>{{ stage === 'uploading' ? 'กำลังอัปโหลด…' : stage === 'analyzing' ? 'กำลังวิเคราะห์ไฟล์…' : 'กำลังบันทึก…' }}</span>
            <span>{{ progress }}%</span>
          </div>
          <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
            <div class="h-full bg-gradient-to-r from-blue-700 to-sky-500 transition-all" :style="{ width: progress + '%' }"></div>
          </div>
        </div>

        <div class="card-tint-blue text-xs p-3 mt-3">
          <i class="fi-rr-info"></i> คอลัมน์ที่ระบบใช้: <code>รหัสบ้าน, ลำดับสมาชิก, คำนำหน้า, ชื่อ, สกุล, บ้านเลขที่, หมู่ที่, บ้าน, ตำบล, อำเภอ, สถานะ, รายได้เฉลี่ย</code>
          · Upsert by รหัสบ้าน + ลำดับสมาชิก · ระบบจะแปลง "27-ม.ค." → "27/1" ให้อัตโนมัติ
        </div>
      </div>

      <!-- Preview result -->
      <div v-if="preview" class="card-tint-blue p-5">
        <div class="flex items-start gap-3 flex-wrap">
          <div class="w-10 h-10 shrink-0 rounded-xl bg-blue-700 text-white flex items-center justify-center">
            <i class="fi-rr-eye"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-semibold">ตัวอย่างก่อนนำเข้า · {{ preview.file }}</div>
            <div class="text-xs opacity-80 mt-0.5">รวม {{ formatNumber(preview.summary.total) }} แถว · ยังไม่ได้บันทึก กดยืนยันด้านล่าง</div>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 mt-4">
          <div class="bg-white dark:bg-slate-800 rounded-xl p-3 text-center">
            <div class="text-xs opacity-70">แถวรวม</div>
            <div class="text-lg font-bold tabular-nums">{{ formatNumber(preview.summary.total) }}</div>
          </div>
          <div class="bg-white dark:bg-slate-800 rounded-xl p-3 text-center">
            <div class="text-xs opacity-70">เพิ่มใหม่</div>
            <div class="text-lg font-bold tabular-nums text-green-700">{{ formatNumber(preview.summary.created) }}</div>
          </div>
          <div class="bg-white dark:bg-slate-800 rounded-xl p-3 text-center">
            <div class="text-xs opacity-70">อัปเดต</div>
            <div class="text-lg font-bold tabular-nums text-sky-700">{{ formatNumber(preview.summary.updated) }}</div>
          </div>
          <div class="bg-white dark:bg-slate-800 rounded-xl p-3 text-center">
            <div class="text-xs opacity-70">ผิดพลาด</div>
            <div class="text-lg font-bold tabular-nums text-red-600">{{ formatNumber(preview.summary.failed) }}</div>
          </div>
          <div class="bg-white dark:bg-slate-800 rounded-xl p-3 text-center col-span-2 sm:col-span-3 lg:col-span-1">
            <div class="text-xs opacity-70">แก้บ้านเลขที่</div>
            <div class="text-lg font-bold tabular-nums text-orange-700">{{ formatNumber(preview.summary.fixes) }}</div>
          </div>
        </div>

        <!-- Autofix table -->
        <div v-if="showingFixes.length" class="mt-4">
          <div class="text-sm font-medium mb-2">
            <i class="fi-rr-magic-wand text-orange-600"></i> บ้านเลขที่ที่ Excel เข้าใจผิดเป็นวันที่ — แสดง 10 รายการแรก
          </div>
          <div class="bg-white dark:bg-slate-900 rounded-xl overflow-hidden">
            <table class="w-full text-xs">
              <thead class="text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800">
                <tr>
                  <th class="text-left py-2 px-3">แถว</th>
                  <th class="text-left pr-3">ชื่อ</th>
                  <th class="text-left pr-3">ค่าในไฟล์</th>
                  <th class="text-left pr-3">แก้เป็น</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60">
                <tr v-for="r in showingFixes.slice(0, 10)" :key="r.row">
                  <td class="py-2 px-3 text-slate-500">{{ r.row }}</td>
                  <td class="pr-3 truncate max-w-[160px]">{{ r.name }}</td>
                  <td class="pr-3"><span class="card-tint-red px-2 py-0.5 rounded">{{ r.original }}</span></td>
                  <td class="pr-3"><span class="card-tint-green px-2 py-0.5 rounded font-medium">{{ r.fixed }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="showingFixes.length > 10" class="text-[11px] text-slate-500 mt-1">… และอีก {{ showingFixes.length - 10 }} รายการ</div>
        </div>

        <div class="flex flex-wrap items-center gap-2 justify-end mt-4">
          <button @click="reset" class="btn-outline px-4 py-2 text-sm">ยกเลิก</button>
          <button @click="commit" :disabled="uploading" class="btn-green px-4 py-2 text-sm flex items-center gap-1.5 disabled:opacity-50">
            <i :class="['fi-rr-check', uploading && 'animate-spin']"></i> ยืนยัน &amp; นำเข้า DB
          </button>
        </div>
      </div>

      <!-- Last run result -->
      <div v-if="lastRun" class="card-tint-green p-5">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 shrink-0 rounded-xl bg-green-600 text-white flex items-center justify-center">
            <i class="fi-rr-check-circle"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-semibold">นำเข้าสำเร็จ · {{ lastRun.file }}</div>
            <div class="text-xs opacity-80 mt-0.5">
              เพิ่มใหม่ {{ formatNumber(lastRun.summary.created) }} ·
              อัปเดต {{ formatNumber(lastRun.summary.updated) }} ·
              ผิดพลาด {{ formatNumber(lastRun.summary.failed) }} ·
              แก้บ้านเลขที่ {{ formatNumber(lastRun.summary.fixes) }} รายการ
            </div>
            <button @click="reset" class="text-xs text-blue-700 hover:underline mt-2">เริ่มนำเข้าไฟล์อื่น →</button>
          </div>
        </div>
      </div>

      <!-- History -->
      <div class="card">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div class="font-semibold">ประวัติการนำเข้า</div>
          <button @click="loadLogs" class="text-xs text-blue-700 hover:underline">รีเฟรช</button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-slate-500 dark:text-slate-400">
              <tr>
                <th class="text-left py-2 px-4">ไฟล์</th>
                <th class="text-left">ผู้นำเข้า</th>
                <th class="text-left">เวลา</th>
                <th class="text-right">รวม</th>
                <th class="text-right">เพิ่ม</th>
                <th class="text-right">อัปเดต</th>
                <th class="text-right">แก้บ้านเลขที่</th>
                <th class="text-right pr-3">ผิดพลาด</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60">
              <tr v-for="l in logs" :key="l.id">
                <td class="py-2 px-4 font-medium truncate max-w-[200px]">{{ l.filename }}</td>
                <td class="text-xs">{{ l.user || '—' }}</td>
                <td class="text-xs text-slate-500">{{ shortDate(l.started_at) }}</td>
                <td class="text-right">{{ formatNumber(l.total) }}</td>
                <td class="text-right text-green-700">{{ formatNumber(l.success) }}</td>
                <td class="text-right text-sky-700">{{ formatNumber(l.updated) }}</td>
                <td class="text-right text-orange-600">{{ formatNumber(l.fixes) }}</td>
                <td class="text-right pr-3 text-red-600">{{ formatNumber(l.failed) }}</td>
              </tr>
              <tr v-if="logs.length === 0"><td colspan="8" class="py-6 text-center text-slate-500 text-sm">ยังไม่มีประวัติการนำเข้า</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';

const phases = ref([]);
const metrics = ref(null);
const channels = ref([]);

onMounted(async () => {
  const [p, m, c] = await Promise.all([
    axios.get('/api/ref/project-phases'),
    axios.get('/api/ref/overview-metrics'),
    axios.get('/api/ref/channels'),
  ]);
  phases.value = p.data.data;
  metrics.value = m.data;
  channels.value = c.data.data;
});

const channelMap = computed(() => {
  if (!metrics.value?.by_channel) return {};
  return Object.fromEntries(Object.entries(metrics.value.by_channel));
});

const phaseIconMap = { 1:'fi-rr-megaphone', 2:'fi-rr-edit', 3:'fi-rr-folder', 4:'fi-rr-search', 5:'fi-rr-chart-pie' };
</script>

<template>
  <AppLayout title="ภาพรวมโครงการ" subtitle="SOP 5 ชั้น">
    <div class="space-y-5">

      <!-- Hero -->
      <div v-if="metrics" class="card-hero p-5 lg:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <div class="text-xs opacity-80">แผนปฏิบัติการส่งต่อข้อมูลให้คนจนเข้าถึงสิทธิของรัฐ</div>
            <h1 class="text-lg lg:text-xl font-semibold mt-1">โครงการลงทะเบียนบัตรสวัสดิการแห่งรัฐ 2569 รอบใหม่</h1>
            <p class="text-sm opacity-90 mt-1">จ.นครราชสีมา · เป้าหมาย {{ formatNumber(metrics.total) }} คน</p>
          </div>
          <div class="text-right">
            <div class="text-xs opacity-80">ความคืบหน้ารวม</div>
            <div class="text-3xl lg:text-4xl font-bold leading-none">{{ metrics.pct_done }}%</div>
            <div class="text-xs opacity-80 mt-1">{{ formatNumber(metrics.registered) }} / {{ formatNumber(metrics.total) }}</div>
          </div>
        </div>
        <div class="mt-4 h-2 rounded-full bg-white/20 overflow-hidden">
          <div class="h-full bg-white" :style="{ width: metrics.pct_done + '%' }"></div>
        </div>
      </div>

      <!-- SOP stepper -->
      <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
          <div>
            <div class="font-semibold">SOP 5 ชั้น</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
              ขั้นปัจจุบัน: ชั้น {{ phases.find(p => p.is_current)?.sop_level || '—' }} — {{ phases.find(p => p.is_current)?.name }}
            </div>
          </div>
          <span class="text-xs px-2.5 py-1 rounded-full card-tint-blue font-medium">ดำเนินการอยู่</span>
        </div>

        <!-- Desktop stepper -->
        <div class="hidden md:flex items-start mt-2">
          <div v-for="(p, idx) in phases" :key="p.id"
               :class="['sop-step', p.is_current ? 'current' : (idx < phases.findIndex(x => x.is_current) ? 'done' : '')]">
            <div class="sop-circle">
              <i v-if="idx < phases.findIndex(x => x.is_current)" class="fi-rr-check"></i>
              <i v-else-if="p.is_current" :class="phaseIconMap[p.sop_level] || 'fi-rr-circle'"></i>
              <span v-else>{{ p.sop_level }}</span>
            </div>
            <div :class="['mt-2 text-sm font-medium', p.is_current ? 'text-blue-700 dark:text-blue-400' : '']">ชั้น {{ p.sop_level }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ p.name }}</div>
          </div>
        </div>

        <!-- Mobile vertical -->
        <div class="md:hidden space-y-2 mt-1">
          <div v-for="(p, idx) in phases" :key="p.id"
               :class="['flex gap-3 items-start',
                        p.is_current ? 'card-tint-blue p-3 rounded-2xl' : '']">
            <div :class="['w-9 h-9 shrink-0 rounded-full flex items-center justify-center',
                         idx < phases.findIndex(x => x.is_current) ? 'bg-green-600 text-white' :
                         (p.is_current ? 'bg-blue-700 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-500')]">
              <i v-if="idx < phases.findIndex(x => x.is_current)" class="fi-rr-check"></i>
              <span v-else class="font-bold">{{ p.sop_level }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <div :class="['font-medium text-sm', p.is_current ? 'text-blue-700 dark:text-blue-300' : '']">
                ชั้น {{ p.sop_level }} · {{ p.name }} <span v-if="p.is_current">⟵ ปัจจุบัน</span>
              </div>
              <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ p.description }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ชั้น 4 — 3 ฝ่าย -->
      <div v-if="metrics">
        <div class="text-sm font-semibold mb-2">ชั้น 4 — ดำเนินการ 3 ฝ่าย</div>
        <div class="grid md:grid-cols-3 gap-3">
          <div class="card-tint-blue p-4">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 rounded-xl bg-blue-700 text-white flex items-center justify-center"><i class="fi-rr-search"></i></div>
              <div><div class="font-medium">ตรวจสอบสิทธิ์</div><div class="text-xs opacity-70">กรมบัญชีกลาง</div></div>
            </div>
            <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ formatNumber(metrics.level_4.check_rights) }}</div>
          </div>
          <div class="card-tint-orange p-4">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 rounded-xl bg-orange-500 text-white flex items-center justify-center"><i class="fi-rr-paper-plane"></i></div>
              <div><div class="font-medium">ส่งเอกสารเพิ่มเติม</div><div class="text-xs opacity-70">ผ่าน 13 ธนาคาร</div></div>
            </div>
            <div class="text-2xl font-bold text-orange-700 dark:text-orange-300">{{ formatNumber(metrics.level_4.extra_docs) }}</div>
          </div>
          <div class="card-tint-sky p-4">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 rounded-xl bg-sky-600 text-white flex items-center justify-center"><i class="fi-rr-fingerprint"></i></div>
              <div><div class="font-medium">ยืนยันตัวตน / ใช้สิทธิ</div><div class="text-xs opacity-70">e-KYC</div></div>
            </div>
            <div class="text-2xl font-bold text-sky-900 dark:text-sky-100">{{ formatNumber(metrics.level_4.kyc_waiting + metrics.level_4.kyc_done) }}</div>
          </div>
        </div>
      </div>

      <!-- 4 ช่องทาง -->
      <div v-if="channels.length" class="card p-5">
        <div class="font-semibold">4 ช่องทางการลงทะเบียน</div>
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-4">ผู้เข้าระบบเลือกได้ตามความสะดวก</div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div v-for="c in channels" :key="c.id" class="border border-slate-100 dark:border-slate-800 rounded-2xl p-4 text-center">
            <div class="w-12 h-12 mx-auto rounded-xl card-tint-sky flex items-center justify-center text-sky-700 text-xl mb-2"><i :class="c.icon || 'fi-rr-circle'"></i></div>
            <div class="font-medium text-sm">{{ c.name }}</div>
            <div class="text-xl font-bold mt-1">{{ formatNumber(channelMap[c.name] || 0) }}</div>
          </div>
        </div>
      </div>

      <!-- ธนาคารรับลงทะเบียน 5 แห่ง (เจาะลึกช่องทางธนาคาร) -->
      <div v-if="metrics?.by_bank?.length" class="card p-5">
        <div class="font-semibold flex items-center gap-2">
          <i class="fi-rr-bank text-green-700"></i> เจาะลึกช่องทางธนาคาร · 5 แห่ง
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400 mb-4">รวม {{ formatNumber(metrics.by_bank.reduce((a,b) => a + b.count, 0)) }} รายการที่เลือกธนาคาร</div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <div v-for="b in metrics.by_bank" :key="b.code" class="border border-slate-100 dark:border-slate-800 rounded-2xl p-3 text-center">
            <div class="w-10 h-10 mx-auto rounded-xl card-tint-green flex items-center justify-center text-green-700 mb-2">
              <i class="fi-rr-bank"></i>
            </div>
            <div class="text-xs font-medium leading-tight">{{ b.name }}</div>
            <div class="text-lg font-bold mt-1">{{ formatNumber(b.count) }}</div>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

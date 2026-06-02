<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { formatNumber, shortDate } from '@/composables/useApi';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

// scope default ตาม role:
//   - tracker → mine
//   - bank_staff → inbox (เฉพาะธนาคารตัว)
//   - admin อำเภอ (มี amphur_id) → inbox (เฉพาะอำเภอตัว)
//   - super_admin / admin global → all
const isPureBank = auth.roles.includes('bank_staff') && !auth.roles.includes('admin') && !auth.roles.includes('super_admin');
const isDistrictAdmin = auth.roles.includes('admin') && !!auth.user?.amphur_id;
const defaultScope = isPureBank || isDistrictAdmin ? 'inbox' : 'mine';
const scope = ref(defaultScope);
const status = ref('');   // CSV filter: draft,submitted,received,recorded,rejected
const data = ref({ data: [], total: 0, current_page: 1, last_page: 1 });
const loading = ref(false);
const summary = ref({ submitted_today: 0, pending_district: 0, pending_forward: 0, pending_bank_recv: 0, pending_record: 0, recorded_today: 0, rejected_today: 0 });

// statusBadge — รองรับ status เก่า + ใหม่ (Phase G)
const statusMeta = {
  // legacy aliases
  submitted: { label: 'ส่งแล้ว', tint: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200', icon: 'fi-rr-paper-plane' },
  received:  { label: 'ธ.รับแล้ว', tint: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200', icon: 'fi-rr-inbox-in' },
  recorded:  { label: 'บันทึกครบ', tint: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200', icon: 'fi-rr-check-double' },
  // Phase G 5-step lifecycle
  draft:                   { label: 'ร่าง', tint: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200', icon: 'fi-rr-document' },
  submitted_to_district:   { label: 'รออำเภอรับ', tint: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200', icon: 'fi-rr-paper-plane' },
  district_received:       { label: 'อำเภอรับแล้ว · รอส่งต่อ', tint: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200', icon: 'fi-rr-building' },
  forwarded_to_bank:       { label: 'รอธนาคารรับ', tint: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200', icon: 'fi-rr-paper-plane' },
  bank_received:           { label: 'ธ.รับแล้ว · รอบันทึก', tint: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200', icon: 'fi-rr-inbox-in' },
  bank_recorded:           { label: 'บันทึกครบ', tint: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200', icon: 'fi-rr-check-double' },
  rejected:                { label: 'ปฏิเสธ', tint: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300', icon: 'fi-rr-cross-circle' },
};

// scope tabs ขึ้นกับ role
const scopeTabs = computed(() => {
  const tabs = [];
  const isBank = auth.roles.includes('bank_staff');
  const isAdmin = auth.roles.includes('super_admin') || auth.roles.includes('admin');

  // tracker เห็น "ของฉัน" · bank_staff ไม่เห็น (ไม่ใช่ผู้สร้าง batch)
  if (!isBank || isAdmin) tabs.push({ key: 'mine', label: 'ของฉัน', icon: 'fi-rr-user' });
  // bank_staff + admin เห็น inbox
  if (isBank || isAdmin) tabs.push({ key: 'inbox', label: 'รอดำเนินการ', icon: 'fi-rr-inbox' });
  // admin เห็นทั้งหมด
  if (isAdmin) tabs.push({ key: 'all', label: 'ทั้งหมด', icon: 'fi-rr-apps' });

  return tabs;
});

const statusTabs = [
  { key: '',                       label: 'ทั้งหมด' },
  { key: 'draft',                  label: 'ร่าง' },
  { key: 'submitted_to_district',  label: 'รออำเภอ' },
  { key: 'district_received',      label: 'อำเภอรับแล้ว' },
  { key: 'forwarded_to_bank',      label: 'รอธนาคาร' },
  { key: 'bank_received',          label: 'ธ.รับ' },
  { key: 'bank_recorded',          label: 'บันทึกครบ' },
  { key: 'rejected',               label: 'ปฏิเสธ' },
];

async function load(page = 1) {
  loading.value = true;
  try {
    const params = { scope: scope.value, page };
    if (status.value) params.status = status.value;
    const { data: d } = await axios.get('/api/batches', { params });
    data.value = d;
  } finally { loading.value = false; }
}
async function loadSummary() {
  try {
    const { data: s } = await axios.get('/api/batches/summary');
    summary.value = s;
  } catch {}
}

onMounted(async () => {
  await Promise.all([load(), loadSummary()]);
});

watch([scope, status], () => load(1));

// ─── View mode (เฉพาะ district admin) ───
const viewMode = ref('list');  // 'list' | 'date' | 'bank'

// จัดกลุ่ม batch สำหรับ view รายวัน
const batchesByDate = computed(() => {
  const groups = {};
  for (const b of data.value.data) {
    const key = b.batch_date ? b.batch_date.substring(0, 10) : 'ไม่ระบุวัน';
    if (!groups[key]) groups[key] = { date: key, items: [] };
    groups[key].items.push(b);
  }
  return Object.values(groups).sort((a, b) => b.date.localeCompare(a.date));
});

// จัดกลุ่ม batch สำหรับ view แยกธนาคาร
const batchesByBank = computed(() => {
  const groups = {};
  for (const b of data.value.data) {
    const key = b.sub_channel ? b.sub_channel.toUpperCase() : 'ไม่ระบุธนาคาร';
    const label = b.sub_channel ? `${b.channel?.name || 'ธนาคาร'} (${key})` : 'ไม่ระบุธนาคาร';
    if (!groups[key]) groups[key] = { key, label, items: [], totalTargets: 0 };
    groups[key].items.push(b);
    groups[key].totalTargets += b.targets_count || 0;
  }
  return Object.values(groups).sort((a, b) => a.key.localeCompare(b.key));
});

function rowClick(b) {
  router.push({ name: 'batch-detail', params: { id: b.id } });
}
</script>

<template>
  <AppLayout :greeting="`สวัสดี, ${auth.user?.name || 'ผู้ใช้'}`" title="ห่อเอกสารธนาคาร · ติดตามรอบส่ง">
    <div class="space-y-4">

      <!-- HERO Summary — 7 KPI ครอบคลุม Path A 5 จุดยืนยัน -->
      <div class="card-hero p-4 lg:p-5">
        <div class="text-xs opacity-80 mb-1.5 flex items-center gap-1.5">
          <i class="fi-rr-box-alt"></i> Document Batch · สถานะวันนี้
        </div>
        <div class="grid gap-2" :style="{ gridTemplateColumns: 'repeat(auto-fit, minmax(95px, 1fr))' }">
          <div class="bg-white/15 rounded-xl p-3"><div class="text-[11px] opacity-80 leading-tight flex items-center gap-1"><i class="fi-rr-paper-plane"></i> ส่งวันนี้</div><div class="text-xl font-bold tabular-nums mt-0.5">{{ formatNumber(summary.submitted_today) }}</div></div>
          <div class="bg-white/15 rounded-xl p-3"><div class="text-[11px] opacity-80 leading-tight flex items-center gap-1"><i class="fi-rr-building"></i> รออำเภอ</div><div class="text-xl font-bold tabular-nums mt-0.5">{{ formatNumber(summary.pending_district || 0) }}</div></div>
          <div class="bg-white/15 rounded-xl p-3"><div class="text-[11px] opacity-80 leading-tight flex items-center gap-1"><i class="fi-rr-share"></i> รอส่งต่อ</div><div class="text-xl font-bold tabular-nums mt-0.5">{{ formatNumber(summary.pending_forward || 0) }}</div></div>
          <div class="bg-white/15 rounded-xl p-3"><div class="text-[11px] opacity-80 leading-tight flex items-center gap-1"><i class="fi-rr-clock"></i> รอ ธ.</div><div class="text-xl font-bold tabular-nums mt-0.5">{{ formatNumber(summary.pending_bank_recv || summary.pending_receive || 0) }}</div></div>
          <div class="bg-white/15 rounded-xl p-3"><div class="text-[11px] opacity-80 leading-tight flex items-center gap-1"><i class="fi-rr-inbox-in"></i> รอบันทึก</div><div class="text-xl font-bold tabular-nums mt-0.5">{{ formatNumber(summary.pending_record) }}</div></div>
          <div class="bg-white/15 rounded-xl p-3"><div class="text-[11px] opacity-80 leading-tight flex items-center gap-1"><i class="fi-rr-check-double"></i> บันทึก</div><div class="text-xl font-bold tabular-nums mt-0.5">{{ formatNumber(summary.recorded_today) }}</div></div>
          <div class="bg-white/15 rounded-xl p-3"><div class="text-[11px] opacity-80 leading-tight flex items-center gap-1"><i class="fi-rr-cross-circle"></i> ปฏิเสธ</div><div class="text-xl font-bold tabular-nums mt-0.5">{{ formatNumber(summary.rejected_today) }}</div></div>
        </div>
      </div>

      <!-- Scope tabs (mine/inbox/all) -->
      <div class="flex gap-2 overflow-x-auto -mx-1 px-1">
        <button v-for="t in scopeTabs" :key="t.key" @click="scope = t.key"
                :class="['shrink-0 px-4 py-2 rounded-xl text-sm flex items-center gap-2 transition',
                         scope === t.key
                           ? 'bg-blue-700 text-white shadow'
                           : 'border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800']">
          <i :class="t.icon"></i> {{ t.label }}
        </button>
      </div>

      <!-- View mode tabs — เฉพาะ district admin -->
      <div v-if="isDistrictAdmin" class="flex gap-2 overflow-x-auto -mx-1 px-1">
        <button @click="viewMode = 'list'"
                :class="['shrink-0 px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition border',
                         viewMode === 'list' ? 'bg-slate-800 text-white border-slate-800 dark:bg-white dark:text-slate-900 dark:border-white' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800']">
          <i class="fi-rr-list"></i> รายการ
        </button>
        <button @click="viewMode = 'date'"
                :class="['shrink-0 px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition border',
                         viewMode === 'date' ? 'bg-slate-800 text-white border-slate-800 dark:bg-white dark:text-slate-900 dark:border-white' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800']">
          <i class="fi-rr-calendar"></i> แยกรายวัน
        </button>
        <button @click="viewMode = 'bank'"
                :class="['shrink-0 px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition border',
                         viewMode === 'bank' ? 'bg-slate-800 text-white border-slate-800 dark:bg-white dark:text-slate-900 dark:border-white' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800']">
          <i class="fi-rr-bank"></i> แยกธนาคาร
        </button>
      </div>

      <!-- Status filter pills -->
      <div class="flex gap-2 overflow-x-auto -mx-1 px-1 pb-1">
        <button v-for="t in statusTabs" :key="t.key" @click="status = t.key"
                :class="['shrink-0 whitespace-nowrap px-3 py-1.5 rounded-full text-xs',
                         status === t.key
                           ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                           : 'border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800']">
          {{ t.label }}
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center py-12 text-slate-500">
        <i class="fi-rr-spinner animate-spin text-2xl"></i>
      </div>

      <!-- Empty state · ข้อความและ CTA เปลี่ยนตาม role -->
      <div v-else-if="data.data.length === 0" class="card p-12 text-center">
        <i class="fi-rr-box-alt text-5xl text-slate-300 dark:text-slate-700 mb-3"></i>
        <template v-if="auth.roles.includes('bank_staff') && !auth.roles.includes('admin') && !auth.roles.includes('super_admin')">
          <!-- Bank staff: รอ tracker ส่งมา -->
          <div class="text-slate-500 dark:text-slate-400">ยังไม่มี batch รอดำเนินการ</div>
          <div class="text-xs text-slate-400 dark:text-slate-500 mt-2 max-w-md mx-auto">
            🔔 เมื่อมีผู้ติดตามส่งเอกสารมาให้สาขาของคุณ จะปรากฏที่นี่อัตโนมัติ — ระบบกรองเฉพาะธนาคารของคุณแล้ว
          </div>
        </template>
        <template v-else>
          <!-- Tracker/admin: เชิญสร้าง batch -->
          <div class="text-slate-500 dark:text-slate-400">ยังไม่มี batch ในเงื่อนไขนี้</div>
          <RouterLink :to="{ name: 'targets' }" class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-xl btn-primary text-sm">
            <i class="fi-rr-users-alt"></i> ไปเลือกรายชื่อ → ใส่ห่อเอกสาร
          </RouterLink>
        </template>
      </div>

      <!-- ─── View: รายการ (default) ─── -->
      <div v-else-if="viewMode === 'list'" class="space-y-2">
        <div v-for="b in data.data" :key="b.id" @click="rowClick(b)"
             class="card p-4 cursor-pointer active:bg-slate-50 dark:active:bg-slate-800/50 hover:shadow-md hover:border-blue-300 dark:hover:border-blue-700 transition tap-transparent min-h-[80px]">
          <div class="flex items-start gap-3">
            <div :class="['w-11 h-11 shrink-0 rounded-xl flex items-center justify-center text-xl', statusMeta[b.status]?.tint || 'bg-slate-100']">
              <i :class="statusMeta[b.status]?.icon || 'fi-rr-document'"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <div class="font-mono font-semibold text-sm">#{{ b.batch_no }}</div>
                <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusMeta[b.status]?.tint]">{{ statusMeta[b.status]?.label }}</span>
              </div>
              <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 truncate">
                <i class="fi-rr-user-headset mr-1"></i> {{ b.tracker?.name || '—' }}
                <span class="mx-1.5">·</span>
                <i class="fi-rr-bank mr-1"></i> {{ b.channel?.name || '—' }} {{ b.sub_channel ? `(${b.sub_channel.toUpperCase()})` : '' }}
              </div>
              <div class="flex items-center gap-3 mt-2 text-xs">
                <span class="flex items-center gap-1"><i class="fi-rr-users-alt text-slate-400"></i><strong class="text-base tabular-nums">{{ formatNumber(b.targets_count) }}</strong> ราย</span>
                <span class="flex items-center gap-1 text-slate-500"><i class="fi-rr-calendar"></i> {{ shortDate(b.batch_date) }}</span>
              </div>
            </div>
            <i class="fi-rr-angle-right text-slate-300 dark:text-slate-600 mt-2"></i>
          </div>
        </div>
      </div>

      <!-- ─── View: แยกรายวัน ─── -->
      <div v-else-if="viewMode === 'date'" class="space-y-4">
        <div v-for="group in batchesByDate" :key="group.date">
          <div class="flex items-center gap-2 px-1 mb-2">
            <i class="fi-rr-calendar text-slate-400"></i>
            <span class="text-sm font-semibold">{{ shortDate(group.date) }}</span>
            <span class="text-xs text-slate-400">· {{ group.items.length }} ห่อ · {{ group.items.reduce((s,b)=>s+(b.targets_count||0),0) }} ราย</span>
          </div>
          <div class="space-y-2">
            <div v-for="b in group.items" :key="b.id" @click="rowClick(b)"
                 class="card p-4 cursor-pointer hover:shadow-md hover:border-blue-300 dark:hover:border-blue-700 transition tap-transparent">
              <div class="flex items-start gap-3">
                <div :class="['w-10 h-10 shrink-0 rounded-xl flex items-center justify-center text-lg', statusMeta[b.status]?.tint || 'bg-slate-100']">
                  <i :class="statusMeta[b.status]?.icon || 'fi-rr-document'"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <div class="font-mono font-semibold text-sm">#{{ b.batch_no }}</div>
                    <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusMeta[b.status]?.tint]">{{ statusMeta[b.status]?.label }}</span>
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    <i class="fi-rr-user-headset mr-1"></i> {{ b.tracker?.name || '—' }}
                    <span class="mx-1.5">·</span>
                    <i class="fi-rr-bank mr-1"></i> {{ b.channel?.name || '—' }} {{ b.sub_channel ? `(${b.sub_channel.toUpperCase()})` : '' }}
                    <span class="mx-1.5">·</span>
                    <strong>{{ formatNumber(b.targets_count) }}</strong> ราย
                  </div>
                </div>
                <i class="fi-rr-angle-right text-slate-300 dark:text-slate-600 mt-1.5"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ─── View: แยกธนาคาร ─── -->
      <div v-else-if="viewMode === 'bank'" class="space-y-4">
        <div v-for="group in batchesByBank" :key="group.key">
          <div class="flex items-center gap-2 px-1 mb-2">
            <i class="fi-rr-bank text-slate-400"></i>
            <span class="text-sm font-semibold">{{ group.label }}</span>
            <span class="text-xs text-slate-400">· {{ group.items.length }} ห่อ · {{ group.totalTargets }} ราย</span>
          </div>
          <div class="space-y-2">
            <div v-for="b in group.items" :key="b.id" @click="rowClick(b)"
                 class="card p-4 cursor-pointer hover:shadow-md hover:border-blue-300 dark:hover:border-blue-700 transition tap-transparent">
              <div class="flex items-start gap-3">
                <div :class="['w-10 h-10 shrink-0 rounded-xl flex items-center justify-center text-lg', statusMeta[b.status]?.tint || 'bg-slate-100']">
                  <i :class="statusMeta[b.status]?.icon || 'fi-rr-document'"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <div class="font-mono font-semibold text-sm">#{{ b.batch_no }}</div>
                    <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusMeta[b.status]?.tint]">{{ statusMeta[b.status]?.label }}</span>
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    <i class="fi-rr-user-headset mr-1"></i> {{ b.tracker?.name || '—' }}
                    <span class="mx-1.5">·</span>
                    <i class="fi-rr-calendar mr-1"></i> {{ shortDate(b.batch_date) }}
                    <span class="mx-1.5">·</span>
                    <strong>{{ formatNumber(b.targets_count) }}</strong> ราย
                  </div>
                </div>
                <i class="fi-rr-angle-right text-slate-300 dark:text-slate-600 mt-1.5"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <Pagination v-if="data.last_page > 1"
                  :current="data.current_page" :last="data.last_page" :total="data.total"
                  unit="batch" @go="load" />
    </div>
  </AppLayout>
</template>

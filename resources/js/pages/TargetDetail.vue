<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Modal from '@/components/Modal.vue';
import { ref, reactive, onMounted, watch } from 'vue';
import axios from 'axios';
import { useRoute, useRouter } from 'vue-router';
import { formatNumber, shortDate, statusColorClass, statusShort } from '@/composables/useApi';

const route = useRoute();
const router = useRouter();
const id = Number(route.params.id);

const target = ref(null);
const statuses = ref([]);
const channels = ref([]);
const banks = ref([]);
const saving = ref(false);
const errors = ref({});
const flashOk = ref('');

// Mobile: ซ่อน Current + History details เป็นค่าเริ่มต้น · กดเพื่อขยาย
// Desktop (lg+): บังคับแสดงเสมอผ่าน Tailwind `lg:!block`
const currentOpen = ref(false);
const historyOpen = ref(false);
// History sub-dropdown: แสดง 2 รายการล่าสุดเสมอ · ส่วนเกินอยู่ใน "ดูเพิ่มเติม"
const HISTORY_VISIBLE = 2;
const historyShowMore = ref(false);

// Edit modal
const showEdit = ref(false);
const editForm = reactive({
  prefix: '', first_name: '', last_name: '',
  address_no: '', year: '',
  annual_income: '', has_old_welfare: false,
  income_note: '',
});
const editErr = ref({});
const editSaving = ref(false);
const incomeHistory = ref([]);
const showHistory = ref(false);

function openEdit() {
  Object.assign(editForm, {
    prefix: target.value.prefix || '',
    first_name: target.value.first_name || '',
    last_name: target.value.last_name || '',
    address_no: target.value.address_no || '',
    year: target.value.year || '',
    annual_income: target.value.annual_income ?? 0,
    has_old_welfare: !!target.value.has_old_welfare,
    income_note: '',
  });
  editErr.value = {};
  showEdit.value = true;
}

async function saveEdit() {
  editSaving.value = true;
  editErr.value = {};
  try {
    const { data } = await axios.patch(`/api/targets/${id}`, {
      prefix: editForm.prefix || null,
      first_name: editForm.first_name,
      last_name: editForm.last_name || null,
      address_no: editForm.address_no,
      year: editForm.year !== '' ? Number(editForm.year) : null,
      annual_income: Number(editForm.annual_income),
      has_old_welfare: editForm.has_old_welfare ? 1 : 0,
      income_note: editForm.income_note || null,
    });
    target.value = data;
    showEdit.value = false;
    flashOk.value = 'บันทึกข้อมูลเรียบร้อย';
    setTimeout(() => (flashOk.value = ''), 3000);
  } catch (e) {
    editErr.value = e.response?.data?.errors || { general: [e.response?.data?.message || 'ผิดพลาด'] };
  } finally { editSaving.value = false; }
}

async function loadIncomeHistory() {
  const { data } = await axios.get(`/api/targets/${id}/income-history`);
  incomeHistory.value = data.data;
  showHistory.value = true;
}

const form = reactive({
  status_code: '',
  channel_id: '',
  sub_channel: '',
  note: '',
});

const currentStatusObj = () => statuses.value.find(s => s.code === form.status_code);
const selectedChannel = () => channels.value.find(c => c.id === Number(form.channel_id));
const needsBank = () => selectedChannel()?.code === 'bank';

async function load() {
  const [t, s, c, b] = await Promise.all([
    axios.get(`/api/targets/${id}`),
    axios.get('/api/ref/statuses'),
    axios.get('/api/ref/channels'),
    axios.get('/api/ref/banks'),
  ]);
  target.value = t.data;
  statuses.value = s.data.data;
  channels.value = c.data.data;
  banks.value = b.data.data;
  form.status_code = t.data.current?.status_code || '';
  form.channel_id = t.data.current?.channel_id || '';
  form.sub_channel = t.data.current?.sub_channel || '';
  form.note = t.data.current?.note || '';
}

onMounted(load);

async function submit() {
  errors.value = {};
  flashOk.value = '';
  saving.value = true;
  try {
    const payload = {
      status_code: form.status_code,
      channel_id: form.channel_id || null,
      sub_channel: needsBank() ? (form.sub_channel || null) : null,
      note: form.note || null,
    };
    const { data } = await axios.patch(`/api/targets/${id}/status`, payload);
    target.value = data;
    flashOk.value = 'บันทึกสถานะเรียบร้อย';
    setTimeout(() => (flashOk.value = ''), 3000);
  } catch (e) {
    errors.value = e.response?.data?.errors || {};
    if (!Object.keys(errors.value).length) {
      errors.value.general = [e.response?.data?.message || 'เกิดข้อผิดพลาด'];
    }
  } finally {
    saving.value = false;
  }
}

function initials(name) {
  return (name || 'U').trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').replace(/[^ก-๙A-Za-z]/g, '');
}
</script>

<template>
  <AppLayout :title="target?.name || 'รายละเอียดบุคคล'" subtitle="รายละเอียด + อัปเดตสถานะ">
    <div v-if="target" class="space-y-4">

      <RouterLink to="/targets" class="inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400 hover:text-blue-700">
        <i class="fi-rr-arrow-left"></i> รายชื่อเป้าหมาย
      </RouterLink>

      <!-- Hero -->
      <div class="card-hero p-5 lg:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="flex gap-4 min-w-0">
            <div class="w-16 h-16 shrink-0 rounded-2xl bg-white/15 backdrop-blur flex items-center justify-center text-xl font-semibold">
              {{ initials(target.name) }}
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-xl font-semibold">{{ target.name }}</h1>
                <span class="text-xs px-2 py-0.5 rounded-full bg-white/15 backdrop-blur">สมาชิกบ้านลำดับ {{ target.member_seq }}</span>
              </div>
              <div class="text-sm opacity-90 mt-1.5 flex items-start gap-1.5">
                <i class="fi-rr-marker mt-0.5"></i>
                <span>{{ target.address_no }} {{ target.village }} ต.{{ target.tambon }} อ.{{ target.amphur }} จ.นครราชสีมา</span>
              </div>
              <div class="flex flex-wrap items-center gap-3 mt-2 text-xs opacity-90">
                <span v-if="target.year"><i class="fi-rr-calendar"></i> ปี {{ target.year }}</span>
                <span><i class="fi-rr-coins"></i> {{ formatNumber(target.annual_income) }} บ./ปี</span>
                <span v-if="target.has_old_welfare"><i class="fi-rr-credit-card"></i> เคยได้รับบัตรสวัสดิการ</span>
              </div>
            </div>
          </div>
          <button @click="openEdit" class="px-3 py-2 rounded-xl bg-white/15 hover:bg-white/25 text-sm flex items-center gap-1.5">
            <i class="fi-rr-edit"></i> แก้ไขข้อมูล
          </button>
        </div>
      </div>

      <!--
        Grid layout — flat, each item gets explicit lg position:
        Mobile: Tracker → Current → History → Update (order 1-4)
        Desktop: LEFT (col 1-2) = Update | RIGHT (col 3) = Current → History → Tracker
      -->
      <div class="grid lg:grid-cols-3 gap-4">

          <!-- ╔══════════════════════════════════════════════╗ -->
          <!-- ║  สถานะปัจจุบัน — Mobile: dropdown · Desktop: แสดงเลย  ║ -->
          <!-- ╚══════════════════════════════════════════════╝ -->
          <div class="relative card-tint-blue order-2 lg:col-start-3 lg:row-start-1 overflow-hidden shadow-md shadow-blue-500/10 ring-1 ring-blue-200/60 dark:ring-blue-800/40">
            <!-- Accent bar ซ้าย -->
            <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-blue-600 to-sky-500 z-10"></div>

            <!-- Header — mobile = ปุ่มกด · desktop = static (เพราะ lg:pointer-events-none) -->
            <button @click="currentOpen = !currentOpen"
                    class="w-full text-left p-4 lg:cursor-default lg:pointer-events-none tap-transparent">
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 text-xs text-blue-800 dark:text-blue-300 font-semibold uppercase tracking-wide">
                  <i class="fi-sr-flag text-blue-700 dark:text-blue-400"></i> สถานะปัจจุบัน
                </div>
                <i :class="['lg:hidden text-blue-600 dark:text-blue-400 transition-transform', currentOpen ? 'fi-rr-angle-small-up' : 'fi-rr-angle-small-down']"></i>
              </div>

              <!-- Status badge — แสดงตลอด ทั้ง mobile/desktop -->
              <div class="mt-2">
                <span v-if="target.current?.status_code"
                      :class="['inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-base font-semibold shadow-sm', statusColorClass(target.current.status_code)]">
                  <i class="fi-sr-check-circle text-base"></i>
                  <span>{{ statuses.find(s => s.code === target.current.status_code)?.label || statusShort(target.current.status_code) }}</span>
                </span>
                <span v-else class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-base font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                  <i class="fi-rr-question"></i> ยังไม่ถูกติดตาม
                </span>
              </div>
            </button>

            <!-- รายละเอียด — mobile: ซ่อน/แสดงตาม currentOpen · desktop: บังคับแสดงเสมอ -->
            <div :class="[currentOpen ? 'block' : 'hidden', 'lg:!block px-4 pb-4']">
              <div v-if="target.current" class="space-y-1.5 text-xs">
                <div v-if="target.current.channel" class="flex items-center gap-2 text-slate-700 dark:text-slate-100">
                  <i class="fi-rr-route text-blue-600 dark:text-blue-400 w-3.5 text-center"></i>
                  <span class="text-slate-500 dark:text-slate-300">ช่องทาง:</span>
                  <span class="font-medium truncate">{{ target.current.channel }}<span v-if="target.current.sub_channel_label" class="text-slate-500 dark:text-slate-400"> ({{ target.current.sub_channel_label }})</span></span>
                </div>
                <div class="flex items-center gap-2 text-slate-700 dark:text-slate-100">
                  <i class="fi-rr-clock text-blue-600 dark:text-blue-400 w-3.5 text-center"></i>
                  <span class="text-slate-500 dark:text-slate-300">อัปเดต:</span>
                  <span class="font-medium tabular-nums">{{ shortDate(target.current.updated_at) }}</span>
                  <span v-if="target.current.updated_by" class="text-slate-500 dark:text-slate-400 truncate">· {{ target.current.updated_by }}</span>
                </div>
                <div v-if="target.current.note" class="bg-white/70 dark:bg-slate-900/40 border border-blue-100 dark:border-blue-900/40 rounded-lg text-xs p-2.5 flex items-start gap-1.5 mt-2">
                  <i class="fi-rr-info text-blue-700 dark:text-blue-400 mt-0.5 shrink-0"></i>
                  <span class="leading-relaxed text-slate-700 dark:text-slate-200">{{ target.current.note }}</span>
                </div>
              </div>
              <div v-else class="text-xs text-slate-500 dark:text-slate-300 italic">ยังไม่มีการบันทึกสถานะ</div>
            </div>
          </div>

          <!-- ╔══════════════════════════════════════════════╗ -->
          <!-- ║  ประวัติ — Mobile: dropdown · Desktop: แสดง 2 + ดูเพิ่ม ║ -->
          <!-- ╚══════════════════════════════════════════════╝ -->
          <div class="card order-3 lg:col-start-3 lg:row-start-2 overflow-hidden">
            <!-- Header — mobile = ปุ่มกด · desktop = static -->
            <button @click="historyOpen = !historyOpen"
                    class="w-full text-left p-4 lg:cursor-default lg:pointer-events-none tap-transparent flex items-center gap-1.5">
              <i class="fi-rr-time-past text-blue-700"></i>
              <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">ประวัติการเปลี่ยนสถานะ</span>
              <span class="text-[11px] px-1.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-medium">
                {{ target.logs.length }} รายการ
              </span>
              <i :class="['lg:hidden ml-auto text-slate-400 transition-transform', historyOpen ? 'fi-rr-angle-small-up' : 'fi-rr-angle-small-down']"></i>
            </button>

            <!-- Body — mobile: ซ่อน/แสดงตาม historyOpen · desktop: บังคับแสดง -->
            <div :class="[historyOpen ? 'block' : 'hidden', 'lg:!block px-4 pb-4']">
              <!-- Empty state -->
              <div v-if="target.logs.length === 0" class="text-xs text-slate-400 italic text-center py-4">
                <i class="fi-rr-folder-open text-2xl text-slate-300 dark:text-slate-600 block mb-1"></i>
                ยังไม่มีประวัติ
              </div>

              <!-- Visible items (2 รายการล่าสุดเสมอ) -->
              <div v-else class="space-y-2.5 text-xs">
              <div v-for="(l, i) in target.logs.slice(0, HISTORY_VISIBLE)" :key="l.id" class="flex gap-2.5">
                <div class="shrink-0 w-2 h-2 mt-1.5 rounded-full bg-blue-600 ring-4 ring-blue-100 dark:ring-blue-900/30"></div>
                <div :class="['flex-1 min-w-0', i < Math.min(target.logs.length, HISTORY_VISIBLE) - 1 ? 'pb-2.5 border-b border-slate-100 dark:border-slate-800' : '']">
                  <div class="flex items-baseline justify-between gap-2 flex-wrap">
                    <span class="font-medium">
                      เปลี่ยนเป็น
                      <span :class="[statusColorClass(l.status_code), 'px-1.5 py-0.5 rounded text-[11px] whitespace-nowrap']">{{ statusShort(l.status_code) }}</span>
                    </span>
                    <span class="text-[11px] text-slate-500 shrink-0 tabular-nums">{{ shortDate(l.changed_at) }}</span>
                  </div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                    <span v-if="l.user">โดย {{ l.user }}</span>
                    <span v-if="l.channel"> · {{ l.channel }}<span v-if="l.sub_channel_label"> ({{ l.sub_channel_label }})</span></span>
                  </div>
                  <div v-if="l.note" class="text-[11px] text-slate-600 dark:text-slate-300 mt-1 leading-relaxed line-clamp-2">{{ l.note }}</div>
                </div>
              </div>
            </div>

            <!-- "ดูเพิ่มเติม" dropdown · แสดงเฉพาะถ้าเกิน HISTORY_VISIBLE -->
            <template v-if="target.logs.length > HISTORY_VISIBLE">
              <button @click="historyShowMore = !historyShowMore"
                      class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 w-full flex items-center justify-center gap-1.5 text-xs font-medium text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-slate-800/50 rounded-lg py-2 tap-transparent transition">
                <i :class="historyShowMore ? 'fi-rr-angle-small-up' : 'fi-rr-angle-small-down'"></i>
                {{ historyShowMore ? 'ซ่อนรายการที่เก่ากว่า' : `ดูเพิ่มเติม ${target.logs.length - HISTORY_VISIBLE} รายการ` }}
              </button>

              <div v-if="historyShowMore" class="space-y-2.5 text-xs mt-3">
                <div v-for="(l, i) in target.logs.slice(HISTORY_VISIBLE)" :key="l.id" class="flex gap-2.5">
                  <div class="shrink-0 w-2 h-2 mt-1.5 rounded-full bg-slate-400 ring-4 ring-slate-100 dark:ring-slate-800"></div>
                  <div :class="['flex-1 min-w-0', i < target.logs.length - HISTORY_VISIBLE - 1 ? 'pb-2.5 border-b border-slate-100 dark:border-slate-800' : '']">
                    <div class="flex items-baseline justify-between gap-2 flex-wrap">
                      <span class="font-medium">
                        เปลี่ยนเป็น
                        <span :class="[statusColorClass(l.status_code), 'px-1.5 py-0.5 rounded text-[11px] whitespace-nowrap']">{{ statusShort(l.status_code) }}</span>
                      </span>
                      <span class="text-[11px] text-slate-500 shrink-0 tabular-nums">{{ shortDate(l.changed_at) }}</span>
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                      <span v-if="l.user">โดย {{ l.user }}</span>
                      <span v-if="l.channel"> · {{ l.channel }}<span v-if="l.sub_channel_label"> ({{ l.sub_channel_label }})</span></span>
                    </div>
                    <div v-if="l.note" class="text-[11px] text-slate-600 dark:text-slate-300 mt-1 leading-relaxed line-clamp-2">{{ l.note }}</div>
                  </div>
                </div>
              </div>
            </template>
            </div><!-- end body wrapper (mobile collapse · lg:!block) -->
          </div>

          <!-- ╔══════════════════════════════════════════════╗ -->
          <!-- ║  อัปเดตสถานะ (mobile 4 · desktop left big block) ║ -->
          <!-- ╚══════════════════════════════════════════════╝ -->
          <div class="card p-5 order-4 lg:col-start-1 lg:row-start-1 lg:col-span-2 lg:row-span-3">
            <div class="font-semibold mb-3">อัปเดตสถานะ</div>

            <div v-if="flashOk" class="card-tint-green p-3 text-sm mb-3"><i class="fi-rr-check-circle"></i> {{ flashOk }}</div>
            <div v-if="errors.general" class="card-tint-red p-3 text-sm mb-3"><i class="fi-rr-cross-circle"></i> {{ errors.general[0] }}</div>

            <form @submit.prevent="submit" class="space-y-4">
              <div>
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">สถานะใหม่ <span class="text-red-600">*</span></label>
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-2">
                  <label v-for="s in statuses" :key="s.code"
                         :class="['flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer hover:bg-blue-50/30 dark:hover:bg-slate-800/50 tap-transparent min-h-[44px]',
                                  form.status_code === s.code ? 'border-2 border-blue-500 card-tint-blue' : 'border-slate-100 dark:border-slate-800']">
                    <input type="radio" v-model="form.status_code" :value="s.code" class="text-blue-600">
                    <span :class="['text-sm px-2 py-0.5 rounded whitespace-nowrap', s.color]">{{ statusShort(s.code) }}</span>
                  </label>
                </div>
                <div v-if="errors.status_code" class="text-[11px] text-red-600 mt-1">{{ errors.status_code[0] }}</div>
              </div>

              <div v-if="currentStatusObj()?.requires_channel || form.channel_id">
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">
                  ช่องทาง <span v-if="currentStatusObj()?.requires_channel" class="text-red-600">*</span>
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  <label v-for="c in channels" :key="c.id"
                         :class="['flex flex-col items-center gap-1.5 p-3 rounded-xl border cursor-pointer hover:bg-blue-50/30 dark:hover:bg-slate-800/50 tap-transparent min-h-[80px]',
                                  Number(form.channel_id) === c.id ? 'border-2 border-sky-500 card-tint-sky' : 'border-slate-100 dark:border-slate-800']">
                    <input type="radio" v-model.number="form.channel_id" :value="c.id" class="hidden">
                    <i :class="[c.icon || 'fi-rr-circle', 'text-xl text-sky-700']"></i>
                    <span class="text-sm text-center leading-tight">{{ c.name }}</span>
                  </label>
                </div>
                <div v-if="errors.channel_id" class="text-[11px] text-red-600 mt-1">{{ errors.channel_id[0] }}</div>
              </div>

              <!-- Sub-channel: เลือกธนาคาร เมื่อช่องทาง = ธนาคาร -->
              <div v-if="needsBank()" class="card-tint-blue p-3 rounded-xl">
                <label class="block text-xs font-medium mb-2">
                  <i class="fi-rr-bank"></i> เลือกธนาคารที่ใช้ลงทะเบียน <span class="text-red-600">*</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <label v-for="b in banks" :key="b.code"
                         :class="['flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer hover:bg-white/60 dark:hover:bg-slate-800/50',
                                  form.sub_channel === b.code ? 'border-2 border-blue-600 bg-white dark:bg-slate-800' : 'border-blue-200 dark:border-slate-700 bg-white/40']">
                    <input type="radio" v-model="form.sub_channel" :value="b.code" class="text-blue-600">
                    <span class="text-sm font-medium">{{ b.name }}</span>
                  </label>
                </div>
                <div v-if="errors.sub_channel" class="text-[11px] text-red-600 mt-2">{{ errors.sub_channel[0] }}</div>
              </div>

              <div>
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">
                  หมายเหตุ
                  <span v-if="currentStatusObj()?.requires_note" class="text-red-600">*</span>
                  <span v-else class="text-slate-400">(ไม่บังคับ)</span>
                </label>
                <textarea v-model="form.note" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติม..."
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                <div v-if="errors.note" class="text-[11px] text-red-600 mt-1">{{ errors.note[0] }}</div>
              </div>

              <div class="flex gap-2 justify-end pt-1">
                <RouterLink to="/targets" class="btn-outline px-4 py-2.5 text-sm">ยกเลิก</RouterLink>
                <button type="submit" :disabled="saving || !form.status_code" class="btn-primary px-4 py-2.5 text-sm flex items-center gap-1.5 disabled:opacity-50">
                  <i :class="['fi-rr-disk', saving && 'animate-spin']"></i> {{ saving ? 'กำลังบันทึก…' : 'บันทึก' }}
                </button>
              </div>
            </form>
          </div>

          <!-- ╔══════════════════════════════════════════════╗ -->
          <!-- ║  ผู้กำกับติดตามหมู่บ้าน (mobile 1 · desktop right bottom) ║ -->
          <!-- ╚══════════════════════════════════════════════╝ -->
          <div class="card-tint-sky p-5 order-1 lg:col-start-3 lg:row-start-3">
            <div class="text-xs opacity-80 mb-2 font-medium flex items-center gap-1.5">
              <i class="fi-rr-user-headset"></i> ผู้กำกับติดตามหมู่บ้าน
            </div>
            <div v-if="target.tracker" class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-full bg-sky-600 text-white flex items-center justify-center font-semibold shrink-0">
                {{ initials(target.tracker.name) }}
              </div>
              <div class="min-w-0 flex-1">
                <div class="font-medium truncate">{{ target.tracker.name }}</div>
                <div class="text-xs opacity-80 truncate">{{ target.tracker.position }}</div>
                <a v-if="target.tracker.phone" :href="`tel:${target.tracker.phone}`" class="text-xs text-sky-800 dark:text-sky-300 font-medium hover:underline mt-1 inline-flex items-center gap-1 tap-transparent">
                  <i class="fi-rr-phone-call"></i> {{ target.tracker.phone }}
                </a>
              </div>
            </div>
            <div v-else class="text-sm text-slate-500 italic">ยังไม่มีผู้กำกับติดตามที่หมู่บ้านนี้</div>
          </div>
      </div>

      <!-- Edit modal -->
      <Modal :show="showEdit" max-width="max-w-lg" @close="showEdit = false">
          <div class="flex items-center justify-between mb-3">
            <div>
              <div class="font-semibold">แก้ไขข้อมูลส่วนตัว</div>
              <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">การแก้ "รายได้" จะเก็บประวัติ + ค่าเดิมเป็น Baseline</div>
            </div>
            <button @click="showEdit = false" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800 tap-transparent" title="ปิด"><i class="fi-rr-cross-small"></i></button>
          </div>

          <div v-if="editErr.general" class="card-tint-red p-3 text-sm mb-3"><i class="fi-rr-cross-circle"></i> {{ editErr.general[0] }}</div>

          <form @submit.prevent="saveEdit" class="space-y-3">
            <div class="grid grid-cols-[100px_1fr_1fr] gap-2">
              <div>
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">คำนำหน้า</label>
                <select v-model="editForm.prefix" class="w-full px-2 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
                  <option v-for="p in ['นาย','นาง','นางสาว','เด็กชาย','เด็กหญิง','อื่น ๆ']" :key="p" :value="p">{{ p }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">ชื่อ</label>
                <input v-model="editForm.first_name" required class="w-full px-3 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              </div>
              <div>
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">นามสกุล</label>
                <input v-model="editForm.last_name" class="w-full px-3 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">บ้านเลขที่</label>
                <input v-model="editForm.address_no" class="w-full px-3 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              </div>
              <div>
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1">ปีข้อมูล</label>
                <input v-model="editForm.year" type="number" min="2500" max="2700" class="w-full px-3 py-2 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              </div>
            </div>
            <div class="card-tint-orange p-3 rounded-xl">
              <div class="text-xs font-medium mb-2"><i class="fi-rr-coins"></i> รายได้เฉลี่ย (บาท/ปี)</div>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <input v-model="editForm.annual_income" type="number" min="0" class="w-full px-3 py-2 rounded-lg border border-orange-200 dark:border-orange-800 bg-white dark:bg-slate-900 text-sm">
                  <div class="text-[11px] mt-1 opacity-80">ค่าเดิม: {{ formatNumber(target?.annual_income) }} บ./ปี</div>
                </div>
                <div>
                  <input v-model="editForm.income_note" placeholder="หมายเหตุการแก้ไข (ไม่บังคับ)" class="w-full px-3 py-2 rounded-lg border border-orange-200 dark:border-orange-800 bg-white dark:bg-slate-900 text-sm">
                </div>
              </div>
              <button type="button" @click="loadIncomeHistory" class="text-xs text-orange-700 hover:underline mt-2">
                <i class="fi-rr-time-past"></i> ดูประวัติการแก้ไขรายได้
              </button>
            </div>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="editForm.has_old_welfare" type="checkbox" class="rounded text-blue-600"> เคยได้รับบัตรสวัสดิการ
            </label>
            <div class="flex gap-2 justify-end pt-1">
              <button type="button" @click="showEdit = false" class="btn-outline px-4 py-2 text-sm">ยกเลิก</button>
              <button type="submit" :disabled="editSaving" class="btn-primary px-4 py-2 text-sm flex items-center gap-1.5">
                <i :class="['fi-rr-disk', editSaving && 'animate-spin']"></i> บันทึก
              </button>
            </div>
          </form>
      </Modal>

      <!-- Income history modal -->
      <Modal :show="showHistory" max-width="max-w-lg" @close="showHistory = false">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">ประวัติการแก้ไขรายได้</div>
            <button @click="showHistory = false" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800 tap-transparent" title="ปิด"><i class="fi-rr-cross-small"></i></button>
          </div>
          <div v-if="incomeHistory.length === 0" class="text-sm text-slate-500 text-center py-6">ยังไม่มีการแก้ไขรายได้</div>
          <div v-else class="space-y-2">
            <div v-for="h in incomeHistory" :key="h.id"
                 :class="['p-3 rounded-xl border', h.is_baseline ? 'border-blue-200 card-tint-blue' : 'border-slate-100 dark:border-slate-800']">
              <div class="flex items-baseline justify-between gap-2 flex-wrap">
                <div class="font-medium text-sm">
                  <span class="text-slate-500">{{ formatNumber(h.old_income) }}</span>
                  <i class="fi-rr-arrow-right mx-1 text-slate-400 text-xs"></i>
                  <strong>{{ formatNumber(h.new_income) }}</strong>
                  <span :class="['text-xs ml-2', h.diff > 0 ? 'text-green-700' : h.diff < 0 ? 'text-red-600' : 'text-slate-500']">
                    {{ h.diff > 0 ? '+' : '' }}{{ formatNumber(h.diff) }}
                  </span>
                </div>
                <div class="text-xs text-slate-500">{{ shortDate(h.changed_at) }}</div>
              </div>
              <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                <span v-if="h.is_baseline" class="font-medium text-blue-700">📌 Baseline · </span>
                โดย {{ h.changed_by_name }}
                <span v-if="h.note"> · {{ h.note }}</span>
              </div>
            </div>
          </div>
      </Modal>
    </div>

    <div v-else class="card p-8 text-center">
      <i class="fi-rr-spinner animate-spin text-2xl text-slate-400"></i>
      <div class="text-sm text-slate-500 mt-2">กำลังโหลด…</div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import Modal from '@/components/Modal.vue';
import { ref, reactive, onMounted, watch } from 'vue';
import axios from 'axios';
import { formatNumber, shortDate } from '@/composables/useApi';

const data = ref({ data: [], total: 0, current_page: 1, last_page: 1 });
const stats = ref(null);
const filters = reactive({ q: '', role: '', status: '' });
const loading = ref(false);

const editing = ref(null);
const showEdit = ref(false);
const form = reactive({ name: '', phone: '', email: '', role: 'tracker', position_type: '', position_other: '', password: '', active: true, bank_channel_id: '', bank_sub_channel: '', amphur_id: '' });
const formErrors = ref({});
const saving = ref(false);

// + Create user modal
const showCreate = ref(false);
const createForm = reactive({ name: '', phone: '', email: '', role: 'tracker', password: '', active: true, bank_channel_id: '', bank_sub_channel: '', amphur_id: '' });
const createErrors = ref({});

// ─── สำหรับ bank_staff + district admin ───
const channels = ref([]);
const banks = ref([]);
const amphurs = ref([]);
async function loadChannelsBanks() {
  const [c, b, a] = await Promise.all([
    axios.get('/api/ref/channels'),
    axios.get('/api/ref/banks'),
    axios.get('/api/ref/amphurs'),
  ]);
  channels.value = c.data.data;
  banks.value = b.data.data;
  amphurs.value = a.data.data;
}
const createSaving = ref(false);
const createFlash = ref('');

async function load(page = 1) {
  loading.value = true;
  try {
    const params = { ...filters, page };
    Object.keys(params).forEach(k => params[k] === '' && delete params[k]);
    const { data: d } = await axios.get('/api/admin/users', { params });
    data.value = d;
    stats.value = (await axios.get('/api/admin/users/stats')).data;
  } finally { loading.value = false; }
}

onMounted(() => { load(); loadChannelsBanks(); });
let timer;
watch(() => filters.q, () => { clearTimeout(timer); timer = setTimeout(() => load(1), 300); });
watch(() => [filters.role, filters.status], () => load(1));

function openEdit(u) {
  editing.value = u;
  Object.assign(form, {
    name: u.name, phone: u.phone, email: u.email || '',
    role: u.roles[0] || 'tracker',
    position_type: u.position_type || '', position_other: u.position_other || '',
    bank_channel_id: u.bank_channel_id || '',
    amphur_id: u.amphur_id || '',
    bank_sub_channel: u.bank_sub_channel || '',
    password: '', active: u.active,
  });
  formErrors.value = {};
  showEdit.value = true;
}

function openCreate() {
  Object.assign(createForm, { name: '', phone: '', email: '', role: 'tracker', password: '', active: true, bank_channel_id: '', bank_sub_channel: '', amphur_id: '' });
  createErrors.value = {};
  createFlash.value = '';
  showCreate.value = true;
}

async function createUser() {
  createSaving.value = true;
  createErrors.value = {};
  try {
    await axios.post('/api/admin/users', createForm);
    createFlash.value = `สร้างผู้ใช้ ${createForm.name} แล้ว — login เบอร์ ${createForm.phone}`;
    setTimeout(() => { showCreate.value = false; createFlash.value = ''; }, 1800);
    await load(1);
  } catch (e) {
    createErrors.value = e.response?.data?.errors || { general: [e.response?.data?.message || 'ผิดพลาด'] };
  } finally { createSaving.value = false; }
}

async function save() {
  saving.value = true;
  formErrors.value = {};
  try {
    const payload = { ...form };
    if (!payload.password) delete payload.password;
    await axios.patch(`/api/admin/users/${editing.value.id}`, payload);
    showEdit.value = false;
    await load(data.value.current_page);
  } catch (e) {
    formErrors.value = e.response?.data?.errors || {};
  } finally { saving.value = false; }
}

async function approve(u) {
  await axios.post(`/api/admin/users/${u.id}/approve`);
  await load(data.value.current_page);
}
async function suspend(u) {
  if (!confirm(`ระงับบัญชี ${u.name} ใช่หรือไม่?`)) return;
  await axios.post(`/api/admin/users/${u.id}/suspend`);
  await load(data.value.current_page);
}
async function remove(u) {
  if (!confirm(`ลบ ${u.name} ถาวร? การกระทำนี้ย้อนกลับไม่ได้`)) return;
  await axios.delete(`/api/admin/users/${u.id}`);
  await load(data.value.current_page);
}

const roleColor = {
  super_admin: 'card-tint-blue text-blue-700',
  admin: 'card-tint-sky text-sky-700',
  tracker: 'card-tint-green text-green-700',
  bank_staff: 'card-tint-orange text-orange-700',
};
const ROLE_LABEL = {
  super_admin: 'Super Admin',
  admin:       'Admin',
  tracker:     'ผู้กำกับติดตาม',
  bank_staff:  'เจ้าหน้าที่ธนาคาร',
};
const roleLabel = (code) => ROLE_LABEL[code] || code || '—';
function initials(name) {
  return (name || 'U').trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').replace(/[^ก-๙A-Za-z]/g, '');
}
</script>

<template>
  <AppLayout title="จัดการผู้ใช้" subtitle="บัญชี login ของระบบ · อนุมัติ · กำหนด role + scope">
    <div class="space-y-4">

      <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3">
        <div class="card-tint-blue p-3 sm:p-4"><div class="text-xs opacity-80">ผู้ใช้ทั้งหมด</div><div class="text-xl sm:text-2xl font-bold mt-1 tabular-nums">{{ formatNumber(stats.total) }}</div></div>
        <div class="card-tint-sky p-3 sm:p-4"><div class="text-xs opacity-80">Super Admin</div><div class="text-xl sm:text-2xl font-bold mt-1 tabular-nums text-sky-800">{{ formatNumber(stats.super_admin) }}</div></div>
        <div class="card-tint-green p-3 sm:p-4"><div class="text-xs opacity-80">ผู้กำกับติดตาม</div><div class="text-xl sm:text-2xl font-bold mt-1 tabular-nums text-green-700">{{ formatNumber(stats.tracker) }}</div></div>
        <div class="card-tint-orange p-3 sm:p-4"><div class="text-xs opacity-80">เจ้าหน้าที่ธนาคาร</div><div class="text-xl sm:text-2xl font-bold mt-1 tabular-nums text-orange-700">{{ formatNumber(stats.bank_staff || 0) }}</div></div>
        <div class="card-tint-red p-3 sm:p-4 col-span-2 sm:col-span-1"><div class="text-xs opacity-80">รออนุมัติ</div><div class="text-xl sm:text-2xl font-bold mt-1 tabular-nums text-red-700">{{ formatNumber(stats.pending) }}</div></div>
      </div>

      <div class="card p-3">
        <div class="flex items-center gap-2 min-w-0">
          <div class="relative flex-1 min-w-0">
            <i class="fi-rr-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input v-model="filters.q" placeholder="ค้นหาชื่อ / เบอร์โทร / อีเมล"
              class="w-full pl-10 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500">
            <button v-if="filters.q" @click="filters.q = ''" title="ล้าง"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>
          <button @click="openCreate" class="btn-green shrink-0 px-3 py-2.5 text-sm flex items-center gap-1.5">
            <i class="fi-rr-add"></i> <span class="hidden sm:inline">เพิ่มผู้ใช้</span>
          </button>
        </div>
        <div class="grid grid-cols-2 gap-2 mt-2">
          <div class="relative min-w-0">
            <select v-model="filters.role" class="w-full min-w-0 pl-3 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <option value="">ทุก Role</option>
              <option value="super_admin">Super Admin</option>
              <option value="admin">Admin</option>
              <option value="tracker">ผู้กำกับติดตาม</option>
              <option value="bank_staff">เจ้าหน้าที่ธนาคาร</option>
            </select>
            <button v-if="filters.role" @click="filters.role = ''" title="ล้าง"
                    class="absolute right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>
          <div class="relative min-w-0">
            <select v-model="filters.status" class="w-full min-w-0 pl-3 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <option value="">ทุกสถานะ</option>
              <option value="active">ใช้งาน</option>
              <option value="inactive">รออนุมัติ / ระงับ</option>
            </select>
            <button v-if="filters.status" @click="filters.status = ''" title="ล้าง"
                    class="absolute right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>
        </div>
      </div>

      <div v-if="loading" class="text-center py-8 text-slate-500"><i class="fi-rr-spinner animate-spin text-2xl"></i></div>

      <div v-else class="space-y-2">
        <div v-for="u in data.data" :key="u.id" :class="['card p-3', !u.active && 'card-tint-orange']">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 shrink-0 rounded-full text-white flex items-center justify-center text-sm font-semibold"
                 style="background: linear-gradient(135deg,#1d4ed8,#0ea5e9);">{{ initials(u.name) }}</div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <div class="font-medium truncate">{{ u.name }}</div>
                <span :class="['text-[10px] px-2 py-0.5 rounded font-medium', roleColor[u.roles[0]] || 'st-4-1']">{{ roleLabel(u.roles[0]) }}</span>
              </div>
              <div class="text-xs text-slate-500 dark:text-slate-400 truncate">
                <i class="fi-rr-phone-call"></i> {{ u.phone }}
                <span v-if="u.email"> · {{ u.email }}</span>
              </div>
              <div class="text-[11px] text-slate-400 mt-0.5">
                เข้าใช้งานล่าสุด {{ shortDate(u.last_login_at) }}
                <span v-if="!u.active" class="text-orange-600 font-medium ml-1">· รออนุมัติ / ระงับ</span>
              </div>
              <!-- ขอบเขตพื้นที่ — สำคัญสำหรับ tracker -->
              <div v-if="u.scopes && u.scopes.length" class="mt-1 flex flex-wrap gap-1">
                <span v-for="s in u.scopes" :key="s.type + s.id"
                      class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                  <i class="fi-rr-marker"></i> {{ s.label }}
                </span>
              </div>
              <div v-else-if="u.roles.includes('tracker')" class="mt-1">
                <span class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300">
                  <i class="fi-rr-triangle-warning"></i> ยังไม่ได้กำหนดพื้นที่ — จะเห็น 0 ราย
                </span>
              </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-1.5 shrink-0">
              <button v-if="!u.active" @click="approve(u)" class="text-sm px-3 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 tap-transparent min-h-[36px] whitespace-nowrap">อนุมัติ</button>
              <button v-else @click="suspend(u)" class="text-sm px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 tap-transparent min-h-[36px] whitespace-nowrap">ระงับ</button>
              <div class="flex gap-1">
                <button @click="openEdit(u)" class="btn-icon hover:bg-blue-50 dark:hover:bg-slate-800 text-blue-700 tap-transparent" title="แก้ไข"><i class="fi-rr-edit text-sm"></i></button>
                <button @click="remove(u)" class="btn-icon hover:bg-red-50 dark:hover:bg-slate-800 text-red-600 tap-transparent" title="ลบ"><i class="fi-rr-trash text-sm"></i></button>
              </div>
            </div>
          </div>
        </div>
        <div v-if="data.data.length === 0" class="card p-6 text-center text-sm text-slate-500">ไม่พบผู้ใช้</div>
      </div>

      <Pagination
        :current="data.current_page"
        :last="data.last_page"
        :total="data.total"
        unit="คน"
        @go="load" />

      <!-- Edit modal -->
      <Modal :show="showEdit" max-width="max-w-md" @close="showEdit = false">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">แก้ไขผู้ใช้</div>
            <button @click="showEdit = false" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg"><i class="fi-rr-cross-small"></i></button>
          </div>
          <form @submit.prevent="save" class="space-y-3">
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">ชื่อ-สกุล</label>
              <input v-model="form.name" required class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <div v-if="formErrors.name" class="text-[11px] text-red-600 mt-1">{{ formErrors.name[0] }}</div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">เบอร์โทร</label>
                <input v-model="form.phone" required class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
                <div v-if="formErrors.phone" class="text-[11px] text-red-600 mt-1">{{ formErrors.phone[0] }}</div>
              </div>
              <div>
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">Role</label>
                <select v-model="form.role" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
                  <option value="super_admin">Super Admin</option>
                  <option value="admin">Admin</option>
                  <option value="tracker">ผู้กำกับติดตาม</option>
                  <option value="bank_staff">เจ้าหน้าที่ธนาคาร</option>
                </select>
              </div>
            </div>

            <!-- District scope (เฉพาะ role = admin) -->
            <div v-if="form.role === 'admin'" class="card-tint-blue p-3 rounded-xl space-y-2.5">
              <div class="text-xs font-medium text-blue-800 dark:text-blue-200 flex items-center gap-1.5">
                <i class="fi-rr-building"></i> ขอบเขตอำเภอ — admin ของอำเภอนี้เห็น/จัดการ batch ของอำเภอตัวเท่านั้น
              </div>
              <div>
                <label class="block text-[11px] text-slate-600 dark:text-slate-400 mb-1">อำเภอ</label>
                <select v-model="form.amphur_id" class="w-full px-2.5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                  <option value="">— เลือก (ปล่อยว่าง = admin ระดับทุกอำเภอ) —</option>
                  <option v-for="a in amphurs" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
                <div v-if="formErrors.amphur_id" class="text-[11px] text-red-600 mt-1">{{ formErrors.amphur_id[0] }}</div>
              </div>
            </div>

            <!-- Bank scope (เฉพาะ role = bank_staff) -->
            <div v-if="form.role === 'bank_staff'" class="card-tint-orange p-3 rounded-xl space-y-2.5">
              <div class="text-xs font-medium text-orange-800 dark:text-orange-200 flex items-center gap-1.5">
                <i class="fi-rr-bank"></i> ขอบเขตธนาคาร — เห็นแค่ batch ของสาขานี้
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="block text-[11px] text-slate-600 dark:text-slate-400 mb-1">ช่องทาง</label>
                  <select v-model="form.bank_channel_id" class="w-full px-2.5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                    <option value="">— เลือก —</option>
                    <option v-for="c in channels" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                  <div v-if="formErrors.bank_channel_id" class="text-[11px] text-red-600 mt-1">{{ formErrors.bank_channel_id[0] }}</div>
                </div>
                <div>
                  <label class="block text-[11px] text-slate-600 dark:text-slate-400 mb-1">ธนาคารย่อย</label>
                  <select v-model="form.bank_sub_channel" class="w-full px-2.5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                    <option value="">— เลือก —</option>
                    <option v-for="b in banks" :key="b.code" :value="b.code.toLowerCase()">{{ b.name }}</option>
                  </select>
                  <div v-if="formErrors.bank_sub_channel" class="text-[11px] text-red-600 mt-1">{{ formErrors.bank_sub_channel[0] }}</div>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">อีเมล (ไม่บังคับ)</label>
              <input v-model="form.email" type="email" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <div v-if="formErrors.email" class="text-[11px] text-red-600 mt-1">{{ formErrors.email[0] }}</div>
            </div>
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">รหัสผ่านใหม่ (เว้นถ้าไม่เปลี่ยน)</label>
              <input v-model="form.password" type="password" minlength="6" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <div v-if="formErrors.password" class="text-[11px] text-red-600 mt-1">{{ formErrors.password[0] }}</div>
            </div>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="form.active" type="checkbox" class="rounded text-blue-600"> เปิดใช้งานบัญชี
            </label>
            <div class="flex gap-2 justify-end pt-2">
              <button type="button" @click="showEdit = false" class="btn-outline px-4 py-2 text-sm">ยกเลิก</button>
              <button type="submit" :disabled="saving" class="btn-primary px-4 py-2 text-sm flex items-center gap-1.5">
                <i :class="['fi-rr-disk', saving && 'animate-spin']"></i> บันทึก
              </button>
            </div>
          </form>
      </Modal>

      <!-- Create user modal -->
      <Modal :show="showCreate" max-width="max-w-md" @close="showCreate = false">
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="font-semibold">เพิ่มผู้ใช้ใหม่</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">สำหรับ Super Admin · ผู้ใช้ login ได้ทันทีหลังสร้าง</div>
          </div>
          <button @click="showCreate = false" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg"><i class="fi-rr-cross-small"></i></button>
        </div>

        <div v-if="createFlash" class="card-tint-green p-3 text-sm mb-3"><i class="fi-rr-check-circle"></i> {{ createFlash }}</div>
        <div v-if="createErrors?.general" class="card-tint-red p-3 text-sm mb-3"><i class="fi-rr-cross-circle"></i> {{ createErrors.general[0] }}</div>

        <form @submit.prevent="createUser" class="space-y-3">
          <div>
            <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">ชื่อ-สกุล <span class="text-red-600">*</span></label>
            <input v-model="createForm.name" required class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <div v-if="createErrors.name" class="text-[11px] text-red-600 mt-1">{{ createErrors.name[0] }}</div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">เบอร์โทร <span class="text-red-600">*</span></label>
              <input v-model="createForm.phone" required placeholder="0812345678" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <div v-if="createErrors.phone" class="text-[11px] text-red-600 mt-1">{{ createErrors.phone[0] }}</div>
            </div>
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">Role <span class="text-red-600">*</span></label>
              <select v-model="createForm.role" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
                <option value="tracker">ผู้กำกับติดตาม</option>
                <option value="admin">Admin</option>
                <option value="super_admin">Super Admin</option>
                <option value="bank_staff">เจ้าหน้าที่ธนาคาร</option>
              </select>
            </div>
          </div>

          <!-- Bank scope (เฉพาะ role = bank_staff) — Create form -->
          <!-- District scope · Create (เฉพาะ role=admin) -->
          <div v-if="createForm.role === 'admin'" class="card-tint-blue p-3 rounded-xl space-y-2.5">
            <div class="text-xs font-medium text-blue-800 dark:text-blue-200 flex items-center gap-1.5">
              <i class="fi-rr-building"></i> ขอบเขตอำเภอ — admin ของอำเภอนี้เห็น/จัดการ batch ของอำเภอตัวเท่านั้น
            </div>
            <div>
              <label class="block text-[11px] text-slate-600 dark:text-slate-400 mb-1">อำเภอ <span class="text-red-600">*</span></label>
              <select v-model="createForm.amphur_id" class="w-full px-2.5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                <option value="">— เลือก (ปล่อยว่าง = admin ระดับทุกอำเภอ) —</option>
                <option v-for="a in amphurs" :key="a.id" :value="a.id">{{ a.name }}</option>
              </select>
              <div v-if="createErrors.amphur_id" class="text-[11px] text-red-600 mt-1">{{ createErrors.amphur_id[0] }}</div>
            </div>
          </div>

          <div v-if="createForm.role === 'bank_staff'" class="card-tint-orange p-3 rounded-xl space-y-2.5">
            <div class="text-xs font-medium text-orange-800 dark:text-orange-200 flex items-center gap-1.5">
              <i class="fi-rr-bank"></i> ขอบเขตธนาคาร — บัญชีนี้จะเห็นแค่ batch ของสาขา
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[11px] text-slate-600 dark:text-slate-400 mb-1">ช่องทาง <span class="text-red-600">*</span></label>
                <select v-model="createForm.bank_channel_id" class="w-full px-2.5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                  <option value="">— เลือก —</option>
                  <option v-for="c in channels" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <div v-if="createErrors.bank_channel_id" class="text-[11px] text-red-600 mt-1">{{ createErrors.bank_channel_id[0] }}</div>
              </div>
              <div>
                <label class="block text-[11px] text-slate-600 dark:text-slate-400 mb-1">ธนาคารย่อย <span class="text-red-600">*</span></label>
                <select v-model="createForm.bank_sub_channel" class="w-full px-2.5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                  <option value="">— เลือก —</option>
                  <option v-for="b in banks" :key="b.code" :value="b.code.toLowerCase()">{{ b.name }}</option>
                </select>
                <div v-if="createErrors.bank_sub_channel" class="text-[11px] text-red-600 mt-1">{{ createErrors.bank_sub_channel[0] }}</div>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">อีเมล (ไม่บังคับ)</label>
            <input v-model="createForm.email" type="email" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <div v-if="createErrors.email" class="text-[11px] text-red-600 mt-1">{{ createErrors.email[0] }}</div>
          </div>
          <div>
            <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">รหัสผ่านชั่วคราว (≥ 6 ตัว) <span class="text-red-600">*</span></label>
            <input v-model="createForm.password" type="password" minlength="6" required class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <div v-if="createErrors.password" class="text-[11px] text-red-600 mt-1">{{ createErrors.password[0] }}</div>
            <div class="text-[10px] text-slate-500 mt-1">แจ้งให้ผู้ใช้ใหม่เปลี่ยนรหัสผ่านที่หน้าโปรไฟล์หลัง login ครั้งแรก</div>
          </div>
          <label class="flex items-center gap-2 text-sm">
            <input v-model="createForm.active" type="checkbox" class="rounded text-blue-600"> เปิดใช้งานบัญชีทันที (ไม่ต้องอนุมัติ)
          </label>
          <div class="card-tint-blue p-3 text-[11px] leading-snug">
            <i class="fi-rr-info text-blue-700"></i>
            หากต้องการเปิดบัญชีให้ <b>ผู้กำกับติดตาม</b> ที่มีในระบบแล้ว
            แนะนำให้ไปหน้า <b>"ผู้กำกับติดตาม"</b> แล้วกดปุ่ม <b>"เปิดบัญชี"</b>
            เพื่อให้ระบบล็อก scope หมู่บ้านให้อัตโนมัติ
          </div>
          <div class="flex gap-2 justify-end pt-1">
            <button type="button" @click="showCreate = false" class="btn-outline px-4 py-2 text-sm">ยกเลิก</button>
            <button type="submit" :disabled="createSaving" class="btn-green px-4 py-2 text-sm flex items-center gap-1.5">
              <i :class="['fi-rr-add', createSaving && 'animate-spin']"></i> สร้างบัญชี
            </button>
          </div>
        </form>
      </Modal>
    </div>
  </AppLayout>
</template>

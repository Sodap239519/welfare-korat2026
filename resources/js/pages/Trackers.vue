<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Modal from '@/components/Modal.vue';
import { ref, reactive, onMounted, watch } from 'vue';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const data = ref({ data: [], total: 0, current_page: 1, last_page: 1 });
const loading = ref(false);
const filters = reactive({ q: '', position: '' });

const showForm = ref(false);
const editing = ref(null);
const form = reactive({ full_name: '', position: 'ผู้ใหญ่บ้าน', position_other: '', phone: '', village_id: '', amphur_id: '', tambon_id: '' });
const formErrors = ref({});
const saving = ref(false);

// "เปิดบัญชี" modal
const showCreateUser = ref(false);
const cuTracker = ref(null);
const cuPassword = ref('');
const cuSaving = ref(false);
const cuError = ref('');
const cuFlash = ref('');

const amphurOpts = ref([]);
const tambonOpts = ref([]);
const villageOpts = ref([]);
const positions = ['ผู้ใหญ่บ้าน', 'กำนัน', 'ผู้ช่วยผู้ใหญ่บ้าน', 'อสม.', 'ส.อบต.', 'อื่นๆ'];

async function load() {
  loading.value = true;
  try {
    const params = { ...filters };
    Object.keys(params).forEach(k => params[k] === '' && delete params[k]);
    const { data: d } = await axios.get('/api/trackers', { params });
    data.value = d;
  } finally { loading.value = false; }
}

let searchTimer;
watch(() => filters.q, () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => load(), 300); });
watch(() => filters.position, () => load());

onMounted(async () => {
  await load();
  amphurOpts.value = (await axios.get('/api/ref/amphurs')).data.data;
});

async function loadTambons() {
  form.tambon_id = ''; form.village_id = ''; tambonOpts.value = []; villageOpts.value = [];
  if (!form.amphur_id) return;
  tambonOpts.value = (await axios.get('/api/ref/tambons', { params: { amphur_id: form.amphur_id } })).data.data;
}
async function loadVillages() {
  form.village_id = ''; villageOpts.value = [];
  if (!form.tambon_id) return;
  villageOpts.value = (await axios.get('/api/ref/villages', { params: { tambon_id: form.tambon_id } })).data.data;
}

function openCreate() {
  editing.value = null;
  Object.assign(form, { full_name: '', position: 'ผู้ใหญ่บ้าน', position_other: '', phone: '', village_id: '', amphur_id: '', tambon_id: '' });
  formErrors.value = {};
  showForm.value = true;
}

async function openEdit(t) {
  editing.value = t.id;
  Object.assign(form, {
    full_name:      t.full_name      || '',
    position:       t.position       || 'ผู้ใหญ่บ้าน',
    position_other: t.position_other || '',
    phone:          t.phone          || '',
    amphur_id:      t.amphur_id      || '',
    tambon_id:      '',
    village_id:     '',
  });
  formErrors.value = {};

  // โหลด options ตามลำดับ amphur → tambon → village (เพราะ disabled cascading)
  if (t.amphur_id) {
    tambonOpts.value = (await axios.get('/api/ref/tambons', { params: { amphur_id: t.amphur_id } })).data.data;
    if (t.tambon_id) {
      form.tambon_id = t.tambon_id;
      villageOpts.value = (await axios.get('/api/ref/villages', { params: { tambon_id: t.tambon_id } })).data.data;
      form.village_id = t.village_id;
    }
  }
  showForm.value = true;
}

async function save() {
  saving.value = true;
  formErrors.value = {};
  try {
    const payload = {
      full_name: form.full_name,
      position: form.position,
      position_other: form.position_other || null,
      phone: form.phone || null,
      village_id: form.village_id,
    };
    if (editing.value) {
      await axios.patch(`/api/trackers/${editing.value}`, payload);
    } else {
      await axios.post('/api/trackers', payload);
    }
    showForm.value = false;
    await load();
  } catch (e) {
    formErrors.value = e.response?.data?.errors || {};
  } finally { saving.value = false; }
}

async function remove(t) {
  const id = typeof t === 'object' ? t.id : t;
  const msg = (typeof t === 'object' && t.village_count > 1)
    ? `ลบ ${t.full_name} ทั้งกลุ่ม ${t.village_count} หมู่บ้าน + บัญชี login ?\nการลบไม่สามารถย้อนกลับได้`
    : 'ยืนยันการลบผู้ติดตามนี้?';
  if (!confirm(msg)) return;
  await axios.delete(`/api/trackers/${id}`);
  await load();
}

function openCreateUser(t) {
  cuTracker.value = t;
  cuPassword.value = '';
  cuError.value = '';
  cuFlash.value = '';
  showCreateUser.value = true;
}

async function submitCreateUser() {
  if (cuPassword.value.length < 6) { cuError.value = 'รหัสผ่านอย่างน้อย 6 ตัว'; return; }
  cuSaving.value = true;
  cuError.value = '';
  try {
    const { data } = await axios.post(`/api/trackers/${cuTracker.value.id}/create-user`, { password: cuPassword.value });
    cuFlash.value = data.message;
    setTimeout(() => { showCreateUser.value = false; cuFlash.value = ''; }, 2000);
    await load();
  } catch (e) {
    cuError.value = e.response?.data?.message || 'ผิดพลาด';
  } finally { cuSaving.value = false; }
}

const positionColor = {
  'ผู้ใหญ่บ้าน':'bg-blue-700',
  'กำนัน':'bg-sky-600',
  'ผู้ช่วยผู้ใหญ่บ้าน':'bg-red-600',
  'อสม.':'bg-orange-500',
  'ส.อบต.':'bg-green-600',
};
function initials(name) {
  return (name || 'U').trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').replace(/[^ก-๙A-Za-z]/g, '');
}
function pctClass(n) {
  if (n >= 80) return 'text-green-600';
  if (n >= 50) return 'text-orange-600';
  return 'text-red-600';
}

// กรุ๊ปหมู่บ้านที่ tracker ดูแล: ชื่อหมู่บ้านเดียวกัน + ตำบลเดียวกัน → รวมเป็น 1 แถว
function groupVillages(villages) {
  if (!Array.isArray(villages)) return [];
  const map = new Map();
  for (const v of villages) {
    const key = `${v.amphur_id}|${v.tambon_id}|${v.name}`;
    if (!map.has(key)) {
      map.set(key, {
        name: v.name, tambon: v.tambon, amphur: v.amphur,
        moos: [], total: 0, done: 0,
      });
    }
    const g = map.get(key);
    if (v.moo != null && !g.moos.includes(v.moo)) g.moos.push(v.moo);
    g.total += v.total || 0;
    g.done  += v.done || 0;
  }
  return [...map.values()].map(g => ({
    ...g,
    moos_sorted: [...g.moos].sort((a, b) => Number(a) - Number(b)),
    pct: g.total > 0 ? Math.round((g.done / g.total) * 100) : 0,
  }));
}
</script>

<template>
  <AppLayout title="ผู้กำกับติดตาม" :subtitle="`ทะเบียนคนในชุมชนรายหมู่บ้าน · รวม ${formatNumber(data.total)} คน`">
    <div class="space-y-4">

      <div class="card-hero p-4 flex items-center justify-between">
        <div>
          <div class="text-xs opacity-80">รวมผู้กำกับติดตามรายหมู่บ้าน</div>
          <div class="text-2xl lg:text-3xl font-bold mt-0.5">{{ formatNumber(data.total) }} <span class="text-sm font-normal opacity-80">คน</span></div>
        </div>
      </div>

      <div class="card p-3">
        <div class="flex items-center gap-2 min-w-0">
          <div class="relative flex-1 min-w-0">
            <i class="fi-rr-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input v-model="filters.q" placeholder="ค้นหาชื่อ / เบอร์โทร"
              class="w-full pl-10 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500">
            <button v-if="filters.q" @click="filters.q = ''" title="ล้าง"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
              <i class="fi-rr-cross-small text-[10px]"></i>
            </button>
          </div>
          <button v-if="auth.isSuperAdmin" @click="openCreate" class="btn-green shrink-0 px-3 py-2.5 text-sm flex items-center gap-1.5">
            <i class="fi-rr-add"></i> <span class="hidden sm:inline">เพิ่ม</span>
          </button>
        </div>
        <div class="relative w-full min-w-0 mt-2">
          <select v-model="filters.position" class="w-full min-w-0 pl-3 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <option value="">ทุกตำแหน่ง</option>
            <option v-for="p in positions" :key="p" :value="p">{{ p }}</option>
          </select>
          <button v-if="filters.position" @click="filters.position = ''" title="ล้าง"
                  class="absolute right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <i class="fi-rr-cross-small text-[10px]"></i>
          </button>
        </div>
      </div>

      <div v-if="loading" class="text-center py-8 text-slate-500"><i class="fi-rr-spinner animate-spin text-2xl"></i></div>

      <div v-else-if="data.data.length === 0" class="card p-8 text-center text-sm text-slate-500">
        ยังไม่มีผู้กำกับติดตาม
        <span v-if="auth.isSuperAdmin"> — กดปุ่ม "เพิ่ม" เพื่อเริ่ม</span>
        <span v-else> — ติดต่อ Super Admin เพื่อเพิ่มข้อมูล</span>
      </div>

      <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div v-for="t in data.data" :key="t.id" class="card p-4 hover:border-blue-300 transition min-w-0 overflow-hidden">
          <div class="flex items-start justify-between gap-2 min-w-0">
            <div class="flex gap-3 min-w-0 flex-1">
              <div :class="['w-12 h-12 shrink-0 rounded-2xl text-white flex items-center justify-center font-semibold', positionColor[t.position] || 'bg-slate-500']">
                {{ initials(t.full_name) }}
              </div>
              <div class="min-w-0 flex-1">
                <div class="font-medium truncate">{{ t.full_name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ t.position }}{{ t.position_other ? ' ('+t.position_other+')' : '' }}</div>
                <div class="mt-1">
                  <span v-if="t.has_account" class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-md bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300">
                    <i class="fi-rr-check-circle"></i> มีบัญชี · login ได้
                  </span>
                  <span v-else class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                    <i class="fi-rr-lock"></i> ยังไม่มีบัญชี
                  </span>
                </div>
              </div>
            </div>
            <div v-if="auth.isSuperAdmin" class="shrink-0 flex items-center gap-0.5">
              <button @click="openEdit(t)" class="p-1.5 hover:bg-blue-50 dark:hover:bg-slate-800 rounded-lg text-blue-700 dark:text-blue-300" title="แก้ไข"><i class="fi-rr-edit text-sm"></i></button>
              <button @click="remove(t)" class="p-1.5 hover:bg-red-50 dark:hover:bg-slate-800 rounded-lg text-red-600" :title="t.village_count > 1 ? `ลบทั้งกลุ่ม ${t.village_count} หมู่บ้าน` : 'ลบ'"><i class="fi-rr-trash text-sm"></i></button>
            </div>
          </div>
          <div class="mt-3 space-y-1.5 text-sm min-w-0">
            <a v-if="t.phone" :href="`tel:${t.phone}`" class="flex items-center gap-2 text-blue-700 dark:text-blue-400 hover:underline min-w-0">
              <i class="fi-rr-phone-call text-xs shrink-0"></i> <span class="truncate">{{ t.phone }}</span>
            </a>

            <!-- พื้นที่ดูแล (กรุ๊ปตามชื่อหมู่บ้าน + รวม ม.) -->
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-2">
              <i class="fi-rr-marker"></i>
              ดูแล <b class="text-slate-700 dark:text-slate-200">{{ t.village_count || t.villages?.length || 1 }} หมู่บ้าน</b>
            </div>
            <div class="space-y-1">
              <div v-for="(g, gi) in groupVillages(t.villages)" :key="gi"
                   class="flex items-start gap-2 text-xs px-2 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-800/40 min-w-0">
                <i class="fi-rr-home text-slate-400 shrink-0 mt-0.5"></i>
                <div class="flex-1 min-w-0">
                  <div class="font-medium text-slate-700 dark:text-slate-200 truncate">
                    {{ g.name }}
                    <span v-if="g.moos_sorted.length > 1" class="text-[10px] font-normal text-slate-500">
                      · ม.{{ g.moos_sorted.join(', ม.') }}
                    </span>
                    <span v-else-if="g.moos_sorted.length === 1" class="text-[10px] font-normal text-slate-500">
                      · ม.{{ g.moos_sorted[0] }}
                    </span>
                  </div>
                  <div class="text-[10px] text-slate-500 truncate">ต.{{ g.tambon }} · อ.{{ g.amphur }}</div>
                </div>
                <div class="text-right text-[10px] shrink-0">
                  <div class="text-slate-500">{{ formatNumber(g.total) }} ราย</div>
                  <div :class="['font-semibold', pctClass(g.pct)]">{{ g.pct }}%</div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-between gap-2 text-xs min-w-0">
            <div class="truncate"><span class="text-slate-500 dark:text-slate-400">รวมเป้า</span> <span class="font-semibold">{{ formatNumber(t.total) }}</span></div>
            <div class="truncate text-right"><span class="text-slate-500 dark:text-slate-400">อัปเดตรวม</span> <span :class="['font-semibold', pctClass(t.pct)]">{{ formatNumber(t.done) }} ({{ t.pct }}%)</span></div>
          </div>

          <!-- Super Admin: ปุ่มเปิดบัญชี (สำหรับคนที่ยังไม่มี user) -->
          <button v-if="auth.isSuperAdmin && !t.has_account && t.phone"
                  @click="openCreateUser(t)"
                  class="mt-3 w-full text-xs py-2 px-3 rounded-lg border border-dashed border-blue-300 dark:border-slate-600 text-blue-700 dark:text-blue-300 hover:bg-blue-50/50 dark:hover:bg-slate-800/50 flex items-center justify-center gap-1.5">
            <i class="fi-rr-user-add"></i> เปิดบัญชีให้ login ใช้งาน
          </button>
          <div v-else-if="auth.isSuperAdmin && !t.has_account && !t.phone"
               class="mt-3 text-[11px] text-slate-400 text-center">
            <i class="fi-rr-info"></i> ต้องระบุเบอร์โทรก่อนเปิดบัญชี
          </div>
        </div>

        <button v-if="auth.isSuperAdmin" @click="openCreate" class="card p-4 border-dashed border-2 border-blue-200 dark:border-slate-700 text-blue-700 dark:text-blue-400 hover:bg-blue-50/40 dark:hover:bg-slate-800/50 flex flex-col items-center justify-center gap-2 min-h-[180px]">
          <i class="fi-rr-add text-2xl"></i>
          <span class="text-sm">เพิ่มผู้กำกับติดตามใหม่</span>
        </button>
      </div>

      <!-- Modal -->
      <Modal :show="showForm" max-width="max-w-md" @close="showForm = false">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">{{ editing ? 'แก้ไขผู้ติดตาม' : 'เพิ่มผู้กำกับติดตาม' }}</div>
            <button @click="showForm = false" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg"><i class="fi-rr-cross-small"></i></button>
          </div>
          <form @submit.prevent="save" class="space-y-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">ชื่อ-สกุล</label>
              <input v-model="form.full_name" required class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <div v-if="formErrors.full_name" class="text-[11px] text-red-600 mt-1">{{ formErrors.full_name[0] }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">ตำแหน่ง</label>
              <select v-model="form.position" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
                <option v-for="p in positions" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div v-if="form.position === 'อื่นๆ'">
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">ระบุตำแหน่ง</label>
              <input v-model="form.position_other" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">เบอร์โทร</label>
              <input v-model="form.phone" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            </div>
            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="block text-[11px] text-slate-600 dark:text-slate-400 mb-1">อำเภอ</label>
                <select v-model="form.amphur_id" @change="loadTambons" class="w-full px-2 py-2 rounded-lg border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs">
                  <option value="">เลือก</option>
                  <option v-for="a in amphurOpts" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-[11px] text-slate-600 dark:text-slate-400 mb-1">ตำบล</label>
                <select v-model="form.tambon_id" @change="loadVillages" :disabled="!form.amphur_id" class="w-full px-2 py-2 rounded-lg border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs disabled:opacity-40">
                  <option value="">เลือก</option>
                  <option v-for="t in tambonOpts" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-[11px] text-slate-600 dark:text-slate-400 mb-1">หมู่บ้าน</label>
                <select v-model="form.village_id" :disabled="!form.tambon_id" class="w-full px-2 py-2 rounded-lg border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs disabled:opacity-40">
                  <option value="">เลือก</option>
                  <option v-for="v in villageOpts" :key="v.id" :value="v.id">{{ v.name }}{{ v.moo ? ' ('+v.moo+')' : '' }}</option>
                </select>
              </div>
            </div>
            <div v-if="formErrors.village_id" class="text-[11px] text-red-600">{{ formErrors.village_id[0] }}</div>

            <div class="flex gap-2 justify-end pt-2">
              <button type="button" @click="showForm = false" class="btn-outline px-4 py-2 text-sm">ยกเลิก</button>
              <button type="submit" :disabled="saving" class="btn-green px-4 py-2 text-sm flex items-center gap-1.5">
                <i :class="['fi-rr-disk', saving && 'animate-spin']"></i> บันทึก
              </button>
            </div>
          </form>
      </Modal>

      <!-- เปิดบัญชี modal -->
      <Modal :show="showCreateUser" max-width="max-w-md" @close="showCreateUser = false">
        <div class="flex items-center justify-between mb-3">
          <div>
            <div class="font-semibold flex items-center gap-2"><i class="fi-rr-user-add text-blue-700"></i> เปิดบัญชีให้ผู้กำกับติดตาม</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">สร้างบัญชี User · ผูก scope กับหมู่บ้านโดยอัตโนมัติ</div>
          </div>
          <button @click="showCreateUser = false" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg"><i class="fi-rr-cross-small"></i></button>
        </div>

        <div v-if="cuFlash" class="card-tint-green p-3 text-sm mb-3"><i class="fi-rr-check-circle"></i> {{ cuFlash }}</div>
        <div v-if="cuError" class="card-tint-red p-3 text-sm mb-3"><i class="fi-rr-cross-circle"></i> {{ cuError }}</div>

        <div v-if="cuTracker" class="card-tint-blue p-3 text-sm space-y-1 mb-4">
          <div class="font-medium">{{ cuTracker.full_name }}</div>
          <div class="text-xs opacity-80">
            <i class="fi-rr-briefcase"></i> {{ cuTracker.position }}{{ cuTracker.position_other ? ' ('+cuTracker.position_other+')' : '' }}
          </div>
          <div class="text-xs opacity-80"><i class="fi-rr-phone-call"></i> {{ cuTracker.phone }} <span class="text-blue-700 dark:text-blue-300 font-medium">← ใช้เบอร์นี้ login</span></div>
          <div class="text-xs opacity-80"><i class="fi-rr-marker"></i> {{ cuTracker.village }}{{ cuTracker.moo ? ' ม.'+cuTracker.moo : '' }} · {{ cuTracker.tambon }}</div>
        </div>

        <form @submit.prevent="submitCreateUser" class="space-y-3">
          <div>
            <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">รหัสผ่านชั่วคราว (≥ 6 ตัว) <span class="text-red-600">*</span></label>
            <input v-model="cuPassword" type="password" minlength="6" required autofocus class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <div class="text-[10px] text-slate-500 mt-1">
              แจ้งให้ผู้กำกับเปลี่ยนรหัสผ่านครั้งแรกที่ login ผ่านเมนู "แก้ไขข้อมูลส่วนตัว"
            </div>
          </div>
          <div class="card-tint-orange p-3 text-[11px] leading-snug">
            <i class="fi-rr-info text-orange-700"></i>
            หลังเปิดบัญชี ผู้กำกับติดตามจะเห็น <b>เฉพาะรายชื่อกลุ่มเป้าหมายในหมู่บ้านของตัวเอง</b>
            สามารถอัปเดตสถานะลงทะเบียน (4.1-4.7) ของแต่ละคนได้
          </div>
          <div class="flex gap-2 justify-end pt-1">
            <button type="button" @click="showCreateUser = false" class="btn-outline px-4 py-2 text-sm">ยกเลิก</button>
            <button type="submit" :disabled="cuSaving" class="btn-primary px-4 py-2 text-sm flex items-center gap-1.5">
              <i :class="['fi-rr-key', cuSaving && 'animate-spin']"></i> เปิดบัญชี
            </button>
          </div>
        </form>
      </Modal>
    </div>
  </AppLayout>
</template>

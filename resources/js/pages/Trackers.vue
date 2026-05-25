<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, reactive, onMounted, watch } from 'vue';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';

const data = ref({ data: [], total: 0, current_page: 1, last_page: 1 });
const loading = ref(false);
const filters = reactive({ q: '', position: '' });

const showForm = ref(false);
const editing = ref(null);
const form = reactive({ full_name: '', position: 'ผู้ใหญ่บ้าน', position_other: '', phone: '', village_id: '', amphur_id: '', tambon_id: '' });
const formErrors = ref({});
const saving = ref(false);

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

async function remove(id) {
  if (!confirm('ยืนยันการลบผู้ติดตามนี้?')) return;
  await axios.delete(`/api/trackers/${id}`);
  await load();
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
</script>

<template>
  <AppLayout title="ผู้กำกับติดตาม" :subtitle="`รวม ${formatNumber(data.total)} คน`">
    <div class="space-y-4">

      <div class="card-hero p-4 flex items-center justify-between">
        <div>
          <div class="text-xs opacity-80">รวมผู้กำกับติดตามรายหมู่บ้าน</div>
          <div class="text-2xl lg:text-3xl font-bold mt-0.5">{{ formatNumber(data.total) }} <span class="text-sm font-normal opacity-80">คน</span></div>
        </div>
      </div>

      <div class="card p-3 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[200px]">
          <i class="fi-rr-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <input v-model="filters.q" placeholder="ค้นหาชื่อ / เบอร์โทร"
            class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <select v-model="filters.position" class="px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
          <option value="">ทุกตำแหน่ง</option>
          <option v-for="p in positions" :key="p" :value="p">{{ p }}</option>
        </select>
        <button @click="openCreate" class="btn-green px-3 py-2.5 text-sm flex items-center gap-1.5">
          <i class="fi-rr-add"></i> <span class="hidden sm:inline">เพิ่มผู้ติดตาม</span>
        </button>
      </div>

      <div v-if="loading" class="text-center py-8 text-slate-500"><i class="fi-rr-spinner animate-spin text-2xl"></i></div>

      <div v-else-if="data.data.length === 0" class="card p-8 text-center text-sm text-slate-500">
        ยังไม่มีผู้กำกับติดตาม — กดปุ่ม "เพิ่มผู้ติดตาม" เพื่อเริ่ม
      </div>

      <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <div v-for="t in data.data" :key="t.id" class="card p-4 hover:border-blue-300 transition">
          <div class="flex items-start justify-between">
            <div class="flex gap-3 min-w-0">
              <div :class="['w-12 h-12 shrink-0 rounded-2xl text-white flex items-center justify-center font-semibold', positionColor[t.position] || 'bg-slate-500']">
                {{ initials(t.full_name) }}
              </div>
              <div class="min-w-0">
                <div class="font-medium truncate">{{ t.full_name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ t.position }}{{ t.position_other ? ' ('+t.position_other+')' : '' }}</div>
              </div>
            </div>
            <div class="flex">
              <button @click="remove(t.id)" class="p-1.5 hover:bg-red-50 dark:hover:bg-slate-800 rounded-lg text-red-600" title="ลบ"><i class="fi-rr-trash text-sm"></i></button>
            </div>
          </div>
          <div class="mt-3 space-y-1 text-sm">
            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400 truncate">
              <i class="fi-rr-marker text-xs shrink-0"></i>
              <span class="truncate">{{ t.village }}{{ t.moo ? ' หมู่ '+t.moo : '' }} · {{ t.tambon }}</span>
            </div>
            <a v-if="t.phone" :href="`tel:${t.phone}`" class="flex items-center gap-2 text-blue-700 dark:text-blue-400 hover:underline">
              <i class="fi-rr-phone-call text-xs"></i> {{ t.phone }}
            </a>
          </div>
          <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-between text-xs">
            <div><span class="text-slate-500 dark:text-slate-400">เป้า</span> <span class="font-semibold">{{ formatNumber(t.total) }}</span></div>
            <div><span class="text-slate-500 dark:text-slate-400">อัปเดต</span> <span :class="['font-semibold', pctClass(t.pct)]">{{ formatNumber(t.done) }} ({{ t.pct }}%)</span></div>
          </div>
        </div>

        <button @click="openCreate" class="card p-4 border-dashed border-2 border-blue-200 dark:border-slate-700 text-blue-700 dark:text-blue-400 hover:bg-blue-50/40 dark:hover:bg-slate-800/50 flex flex-col items-center justify-center gap-2 min-h-[180px]">
          <i class="fi-rr-add text-2xl"></i>
          <span class="text-sm">เพิ่มผู้กำกับติดตามใหม่</span>
        </button>
      </div>

      <!-- Modal -->
      <div v-if="showForm" class="fixed inset-0 z-50 bg-slate-900/50 flex items-end sm:items-center justify-center p-0 sm:p-4" @click.self="showForm = false">
        <div class="card w-full sm:max-w-md p-5 rounded-t-3xl sm:rounded-3xl max-h-[90vh] overflow-y-auto">
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
        </div>
      </div>
    </div>
  </AppLayout>
</template>

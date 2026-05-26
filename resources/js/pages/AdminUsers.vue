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
const form = reactive({ name: '', phone: '', email: '', role: 'tracker', position_type: '', position_other: '', password: '', active: true });
const formErrors = ref({});
const saving = ref(false);

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

onMounted(() => load());
let timer;
watch(() => filters.q, () => { clearTimeout(timer); timer = setTimeout(() => load(1), 300); });
watch(() => [filters.role, filters.status], () => load(1));

function openEdit(u) {
  editing.value = u;
  Object.assign(form, {
    name: u.name, phone: u.phone, email: u.email || '',
    role: u.roles[0] || 'tracker',
    position_type: u.position_type || '', position_other: u.position_other || '',
    password: '', active: u.active,
  });
  formErrors.value = {};
  showEdit.value = true;
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
};
function initials(name) {
  return (name || 'U').trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').replace(/[^ก-๙A-Za-z]/g, '');
}
</script>

<template>
  <AppLayout title="จัดการผู้ใช้" subtitle="Super Admin · CRUD + อนุมัติ">
    <div class="space-y-4">

      <div v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="card-tint-blue p-4"><div class="text-xs opacity-80">ผู้ใช้ทั้งหมด</div><div class="text-2xl font-bold mt-1">{{ formatNumber(stats.total) }}</div></div>
        <div class="card-tint-sky p-4"><div class="text-xs opacity-80">Super Admin</div><div class="text-2xl font-bold mt-1 text-sky-800">{{ formatNumber(stats.super_admin) }}</div></div>
        <div class="card-tint-green p-4"><div class="text-xs opacity-80">Tracker</div><div class="text-2xl font-bold mt-1 text-green-700">{{ formatNumber(stats.tracker) }}</div></div>
        <div class="card-tint-orange p-4"><div class="text-xs opacity-80">รออนุมัติ</div><div class="text-2xl font-bold mt-1 text-orange-700">{{ formatNumber(stats.pending) }}</div></div>
      </div>

      <div class="card p-3">
        <div class="flex items-center gap-2 min-w-0">
          <div class="relative flex-1 min-w-0">
            <i class="fi-rr-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input v-model="filters.q" placeholder="ค้นหาชื่อ / เบอร์โทร / อีเมล"
              class="w-full pl-10 pr-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2 mt-2">
          <select v-model="filters.role" class="w-full min-w-0 px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <option value="">ทุก Role</option>
            <option value="super_admin">Super Admin</option>
            <option value="admin">Admin</option>
            <option value="tracker">Tracker</option>
          </select>
          <select v-model="filters.status" class="w-full min-w-0 px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <option value="">ทุกสถานะ</option>
            <option value="active">ใช้งาน</option>
            <option value="inactive">รออนุมัติ / ระงับ</option>
          </select>
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
                <span :class="['text-[10px] px-2 py-0.5 rounded font-medium', roleColor[u.roles[0]] || 'st-4-1']">{{ u.roles[0] || '—' }}</span>
              </div>
              <div class="text-xs text-slate-500 dark:text-slate-400 truncate">
                <i class="fi-rr-phone-call"></i> {{ u.phone }}
                <span v-if="u.email"> · {{ u.email }}</span>
              </div>
              <div class="text-[11px] text-slate-400 mt-0.5">
                เข้าใช้งานล่าสุด {{ shortDate(u.last_login_at) }}
                <span v-if="!u.active" class="text-orange-600 font-medium ml-1">· รออนุมัติ / ระงับ</span>
              </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-1">
              <button v-if="!u.active" @click="approve(u)" class="text-xs px-2 py-1 rounded-lg bg-green-600 text-white">อนุมัติ</button>
              <button v-else @click="suspend(u)" class="text-xs px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-700">ระงับ</button>
              <button @click="openEdit(u)" class="p-1.5 hover:bg-blue-50 dark:hover:bg-slate-800 rounded-lg text-blue-700"><i class="fi-rr-edit text-sm"></i></button>
              <button @click="remove(u)" class="p-1.5 hover:bg-red-50 dark:hover:bg-slate-800 rounded-lg text-red-600"><i class="fi-rr-trash text-sm"></i></button>
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
                  <option value="tracker">Tracker</option>
                </select>
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
    </div>
  </AppLayout>
</template>

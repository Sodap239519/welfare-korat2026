<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Modal from '@/components/Modal.vue';
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';

// Tabs
const tabs = [
  { key: 'channels', label: 'ช่องทางลงทะเบียน', icon: 'fi-rr-megaphone' },
  { key: 'statuses', label: 'สถานะลงทะเบียน',  icon: 'fi-rr-flag' },
  { key: 'banks',    label: 'ธนาคาร',          icon: 'fi-rr-bank' },
];
const tab = ref('channels');

// Data
const channels = ref([]);
const statuses = ref([]);
const banks = ref([]);
const loading = ref(false);

// Modal — generic
const showEdit = ref(false);
const editType = ref('');           // 'channel' | 'status' | 'bank'
const editForm = reactive({});
const editErr  = ref({});
const editSaving = ref(false);

// Endpoints map
const endpoints = {
  channel: { list: '/api/admin/channels', base: '/api/admin/channels', store: channels },
  status:  { list: '/api/admin/statuses', base: '/api/admin/statuses', store: statuses },
  bank:    { list: '/api/admin/banks',    base: '/api/admin/banks',    store: banks },
};

async function loadAll() {
  loading.value = true;
  try {
    const [c, s, b] = await Promise.all([
      axios.get(endpoints.channel.list),
      axios.get(endpoints.status.list),
      axios.get(endpoints.bank.list),
    ]);
    channels.value = c.data.data;
    statuses.value = s.data.data;
    banks.value    = b.data.data;
  } finally { loading.value = false; }
}
onMounted(loadAll);

// Open modal — type = 'channel'|'status'|'bank', item = row (null = create)
function openEdit(type, item = null) {
  editType.value = type;
  editErr.value = {};
  // reset form by type
  if (type === 'channel') {
    Object.assign(editForm, {
      id: item?.id ?? null,
      code: item?.code ?? '',
      name: item?.name ?? '',
      icon: item?.icon ?? 'fi-rr-circle',
      sort_order: item?.sort_order ?? null,
    });
  } else if (type === 'status') {
    Object.assign(editForm, {
      id: item?.id ?? null,
      code: item?.code ?? '',
      label: item?.label ?? '',
      color: item?.color ?? 'slate',
      requires_note: item?.requires_note ?? false,
      requires_channel: item?.requires_channel ?? false,
      sort_order: item?.sort_order ?? null,
    });
  } else if (type === 'bank') {
    Object.assign(editForm, {
      id: item?.id ?? null,
      code: item?.code ?? '',
      name: item?.name ?? '',
      sort_order: item?.sort_order ?? null,
    });
  }
  showEdit.value = true;
}

async function save() {
  editSaving.value = true;
  editErr.value = {};
  const ep = endpoints[editType.value];
  const payload = { ...editForm };
  delete payload.id;
  try {
    if (editForm.id) {
      const { data } = await axios.patch(`${ep.base}/${editForm.id}`, payload);
      const idx = ep.store.value.findIndex(x => x.id === editForm.id);
      if (idx >= 0) ep.store.value[idx] = data.data;
    } else {
      const { data } = await axios.post(ep.base, payload);
      ep.store.value.push(data.data);
    }
    showEdit.value = false;
  } catch (e) {
    editErr.value = e.response?.data?.errors || { general: [e.response?.data?.message || 'ผิดพลาด'] };
  } finally { editSaving.value = false; }
}

async function remove(type, item) {
  if (!confirm(`ลบ "${item.name || item.label} (${item.code})" ?\nการลบไม่สามารถย้อนกลับได้`)) return;
  const ep = endpoints[type];
  try {
    await axios.delete(`${ep.base}/${item.id}`);
    ep.store.value = ep.store.value.filter(x => x.id !== item.id);
  } catch (e) {
    alert(e.response?.data?.message || 'ลบไม่สำเร็จ');
  }
}

const editTitle = computed(() => {
  const t = editType.value;
  const action = editForm.id ? 'แก้ไข' : 'เพิ่ม';
  return t === 'channel' ? `${action}ช่องทางลงทะเบียน`
       : t === 'status'  ? `${action}สถานะลงทะเบียน`
       : t === 'bank'    ? `${action}ธนาคาร` : '';
});

const COLOR_OPTIONS = ['slate','blue','sky','green','orange','red','purple','yellow'];
const ICON_OPTIONS = [
  'fi-rr-circle','fi-rr-bank','fi-rr-megaphone','fi-rr-edit','fi-rr-check',
  'fi-rr-paper-plane','fi-rr-fingerprint','fi-rr-id-card-clip-alt',
  'fi-rr-marker','fi-rr-house-chimney','fi-rr-mobile','fi-rr-globe',
];
</script>

<template>
  <AppLayout title="ตั้งค่าข้อมูล" subtitle="Super Admin · ช่องทาง · สถานะ · ธนาคาร">
    <div class="space-y-4">

      <!-- Tabs -->
      <div class="card p-1 flex gap-1">
        <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
                :class="['flex-1 py-2.5 px-3 rounded-xl text-sm font-medium flex items-center justify-center gap-1.5 transition',
                         tab === t.key
                           ? 'bg-blue-700 text-white shadow-sm shadow-blue-500/30'
                           : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50']">
          <i :class="t.icon"></i> {{ t.label }}
        </button>
      </div>

      <div v-if="loading" class="text-center py-12 text-slate-500"><i class="fi-rr-spinner animate-spin text-2xl"></i></div>

      <!-- CHANNELS -->
      <div v-show="!loading && tab === 'channels'" class="space-y-2">
        <div class="card p-3 flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold">ช่องทางการลงทะเบียน</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">เช่น แอป, สาขา/ธนาคาร, ฯลฯ — รวม {{ channels.length }} รายการ</div>
          </div>
          <button @click="openEdit('channel')" class="btn-green px-3 py-2 text-sm flex items-center gap-1.5">
            <i class="fi-rr-add"></i> เพิ่มช่องทาง
          </button>
        </div>
        <div v-for="c in channels" :key="c.id" class="card p-3 flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl card-tint-blue flex items-center justify-center text-blue-700"><i :class="c.icon || 'fi-rr-circle'"></i></div>
          <div class="flex-1 min-w-0">
            <div class="font-medium truncate">{{ c.name }}</div>
            <div class="text-[11px] text-slate-500">code: <code>{{ c.code }}</code> · ลำดับ {{ c.sort_order }}</div>
          </div>
          <button @click="openEdit('channel', c)" class="p-1.5 hover:bg-blue-50 dark:hover:bg-slate-800 rounded-lg text-blue-700"><i class="fi-rr-edit text-sm"></i></button>
          <button @click="remove('channel', c)" class="p-1.5 hover:bg-red-50 dark:hover:bg-slate-800 rounded-lg text-red-600"><i class="fi-rr-trash text-sm"></i></button>
        </div>
        <div v-if="channels.length === 0" class="card p-8 text-center text-sm text-slate-500">ยังไม่มีช่องทาง</div>
      </div>

      <!-- STATUSES -->
      <div v-show="!loading && tab === 'statuses'" class="space-y-2">
        <div class="card p-3 flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold">สถานะการลงทะเบียน</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">รหัส 4.1-4.7 — รวม {{ statuses.length }} รายการ</div>
          </div>
          <button @click="openEdit('status')" class="btn-green px-3 py-2 text-sm flex items-center gap-1.5">
            <i class="fi-rr-add"></i> เพิ่มสถานะ
          </button>
        </div>
        <div v-for="s in statuses" :key="s.id" class="card p-3 flex items-center gap-3">
          <span :class="['text-xs px-2 py-1 rounded font-mono font-bold whitespace-nowrap', `card-tint-${s.color || 'slate'}`]">{{ s.code }}</span>
          <div class="flex-1 min-w-0">
            <div class="font-medium truncate">{{ s.label }}</div>
            <div class="text-[11px] text-slate-500 flex flex-wrap gap-2">
              <span>ลำดับ {{ s.sort_order }}</span>
              <span v-if="s.requires_note" class="text-orange-600">· ต้องระบุเหตุผล</span>
              <span v-if="s.requires_channel" class="text-blue-600">· ต้องเลือกช่องทาง</span>
            </div>
          </div>
          <button @click="openEdit('status', s)" class="p-1.5 hover:bg-blue-50 dark:hover:bg-slate-800 rounded-lg text-blue-700"><i class="fi-rr-edit text-sm"></i></button>
          <button @click="remove('status', s)" class="p-1.5 hover:bg-red-50 dark:hover:bg-slate-800 rounded-lg text-red-600"><i class="fi-rr-trash text-sm"></i></button>
        </div>
        <div v-if="statuses.length === 0" class="card p-8 text-center text-sm text-slate-500">ยังไม่มีสถานะ</div>
      </div>

      <!-- BANKS -->
      <div v-show="!loading && tab === 'banks'" class="space-y-2">
        <div class="card p-3 flex items-center justify-between">
          <div>
            <div class="text-sm font-semibold">ธนาคาร</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">ธนาคารที่รับลงทะเบียน — รวม {{ banks.length }} แห่ง</div>
          </div>
          <button @click="openEdit('bank')" class="btn-green px-3 py-2 text-sm flex items-center gap-1.5">
            <i class="fi-rr-add"></i> เพิ่มธนาคาร
          </button>
        </div>
        <div v-for="b in banks" :key="b.id" class="card p-3 flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl card-tint-green flex items-center justify-center text-green-700"><i class="fi-rr-bank"></i></div>
          <div class="flex-1 min-w-0">
            <div class="font-medium truncate">{{ b.name }}</div>
            <div class="text-[11px] text-slate-500">code: <code>{{ b.code }}</code> · ลำดับ {{ b.sort_order }}</div>
          </div>
          <button @click="openEdit('bank', b)" class="p-1.5 hover:bg-blue-50 dark:hover:bg-slate-800 rounded-lg text-blue-700"><i class="fi-rr-edit text-sm"></i></button>
          <button @click="remove('bank', b)" class="p-1.5 hover:bg-red-50 dark:hover:bg-slate-800 rounded-lg text-red-600"><i class="fi-rr-trash text-sm"></i></button>
        </div>
        <div v-if="banks.length === 0" class="card p-8 text-center text-sm text-slate-500">ยังไม่มีธนาคาร</div>
      </div>

      <!-- Edit Modal -->
      <Modal :show="showEdit" max-width="max-w-md" @close="showEdit = false">
        <div class="flex items-center justify-between mb-4">
          <div class="font-semibold">{{ editTitle }}</div>
          <button @click="showEdit = false" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg"><i class="fi-rr-cross-small"></i></button>
        </div>

        <div v-if="editErr?.general" class="card-tint-red p-3 text-sm mb-3"><i class="fi-rr-cross-circle"></i> {{ editErr.general[0] }}</div>

        <form @submit.prevent="save" class="space-y-3">
          <!-- code + name/label -->
          <div class="grid grid-cols-[100px_1fr] gap-3">
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">รหัส <span class="text-red-600">*</span></label>
              <input v-model="editForm.code" required maxlength="40"
                     :placeholder="editType === 'status' ? '4.x' : 'CODE'"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm font-mono">
              <div v-if="editErr.code" class="text-[11px] text-red-600 mt-1">{{ editErr.code[0] }}</div>
            </div>
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">
                {{ editType === 'status' ? 'ชื่อสถานะ' : 'ชื่อ' }} <span class="text-red-600">*</span>
              </label>
              <input v-if="editType === 'status'" v-model="editForm.label" required maxlength="120"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <input v-else v-model="editForm.name" required maxlength="120"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              <div v-if="editErr.name || editErr.label" class="text-[11px] text-red-600 mt-1">
                {{ (editErr.name || editErr.label)[0] }}
              </div>
            </div>
          </div>

          <!-- icon (channels) -->
          <div v-if="editType === 'channel'">
            <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">ไอคอน</label>
            <div class="grid grid-cols-6 gap-2">
              <button type="button" v-for="ic in ICON_OPTIONS" :key="ic" @click="editForm.icon = ic"
                      :class="['p-2 rounded-lg border flex items-center justify-center transition',
                               editForm.icon === ic
                                 ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700'
                                 : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800']">
                <i :class="ic + ' text-base'"></i>
              </button>
            </div>
          </div>

          <!-- color (statuses) -->
          <div v-if="editType === 'status'">
            <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">สี</label>
            <div class="grid grid-cols-4 gap-2">
              <button type="button" v-for="c in COLOR_OPTIONS" :key="c" @click="editForm.color = c"
                      :class="['py-2 rounded-lg border text-xs font-medium transition',
                               editForm.color === c
                                 ? `card-tint-${c} ring-2 ring-blue-500 ring-offset-1`
                                 : `card-tint-${c}`]">
                {{ c }}
              </button>
            </div>
          </div>

          <!-- flags (statuses) -->
          <div v-if="editType === 'status'" class="card-tint-blue p-3 space-y-2">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" v-model="editForm.requires_note" class="rounded text-blue-600">
              ต้องระบุเหตุผล (note)
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input type="checkbox" v-model="editForm.requires_channel" class="rounded text-blue-600">
              ต้องเลือกช่องทาง (channel)
            </label>
          </div>

          <!-- sort_order -->
          <div>
            <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">ลำดับการแสดง (เว้นว่างให้ระบบเติม)</label>
            <input v-model.number="editForm.sort_order" type="number" min="0"
                   class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
          </div>

          <div class="flex gap-2 justify-end pt-1">
            <button type="button" @click="showEdit = false" class="btn-outline px-4 py-2 text-sm">ยกเลิก</button>
            <button type="submit" :disabled="editSaving" class="btn-primary px-4 py-2 text-sm flex items-center gap-1.5">
              <i :class="['fi-rr-disk', editSaving && 'animate-spin']"></i> บันทึก
            </button>
          </div>
        </form>
      </Modal>

    </div>
  </AppLayout>
</template>

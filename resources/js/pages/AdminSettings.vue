<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Modal from '@/components/Modal.vue';
import { ref, reactive, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';

const route = useRoute();
const router = useRouter();

// Tabs / submenu
const tabs = [
  { key: 'channels', label: 'ช่องทางลงทะเบียน', icon: 'fi-rr-megaphone' },
  { key: 'statuses', label: 'สถานะลงทะเบียน',  icon: 'fi-rr-flag' },
  { key: 'banks',    label: 'ธนาคาร',          icon: 'fi-rr-bank' },
  { key: 'sop',      label: 'ขั้นตอน SOP + กระบวนการ', icon: 'fi-rr-list-check' },
];
// Sync tab กับ query string เพื่อให้ submenu sidebar ลิงก์เจาะตรงได้
const validTabs = ['channels', 'statuses', 'banks', 'sop'];
const initTab = validTabs.includes(route.query.tab) ? route.query.tab : 'channels';
const tab = ref(initTab);

// เมื่อ tab เปลี่ยน → อัปเดต query (ไม่ navigate ใหม่)
watch(tab, (v) => {
  if (route.query.tab !== v) router.replace({ query: { ...route.query, tab: v } });
});
// เมื่อ user คลิก sidebar submenu → query เปลี่ยน → update tab
watch(() => route.query.tab, (v) => {
  if (v && validTabs.includes(v) && tab.value !== v) tab.value = v;
});

// Data
const channels = ref([]);
const statuses = ref([]);
const banks = ref([]);
const phases = ref([]);
const loading = ref(false);

// Icon picker — สำหรับ bullets ใน SOP
const ICON_BULLET_OPTIONS = [
  { i: 'fi-rr-circle',            l: '● จุดทั่วไป' },
  { i: 'fi-rr-id-card-clip-alt',  l: '🆔 บัตรประชาชน' },
  { i: 'fi-rr-fingerprint',       l: '👆 ลายนิ้วมือ' },
  { i: 'fi-rr-coins',             l: '💰 เงิน / รายได้' },
  { i: 'fi-rr-list-check',        l: '☑ รายการตรวจ' },
  { i: 'fi-rr-marker',            l: '📍 พิกัด' },
  { i: 'fi-rr-phone-call',        l: '📞 เบอร์โทร' },
  { i: 'fi-rr-chart-pie',         l: '📊 สถิติ' },
  { i: 'fi-rr-info',              l: 'ℹ ข้อมูล' },
  { i: 'fi-rr-comments',          l: '💬 ปรึกษา' },
  { i: 'fi-rr-house-chimney',     l: '🏠 บ้าน' },
  { i: 'fi-rr-building',          l: '🏢 อาคาร' },
  { i: 'fi-rr-ambulance',         l: '🚑 เคลื่อนที่' },
  { i: 'fi-rr-hand-holding-heart',l: '💝 เยี่ยมบ้าน' },
  { i: 'fi-rr-calendar',          l: '📅 ปฏิทิน' },
  { i: 'fi-rr-user-headset',      l: '🎧 CRM' },
  { i: 'fi-rr-search-alt',        l: '🔍 วิเคราะห์' },
  { i: 'fi-rr-file-edit',         l: '📝 เอกสาร' },
  { i: 'fi-rr-megaphone',         l: '📢 ประกาศ' },
  { i: 'fi-rr-check-circle',      l: '✓ ตรวจเรียบร้อย' },
  { i: 'fi-rr-paper-plane',       l: '✈ ส่ง' },
  { i: 'fi-rr-folder',            l: '📁 แฟ้ม' },
  { i: 'fi-rr-users-alt',         l: '👥 กลุ่มคน' },
];

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
    const [c, s, b, p] = await Promise.all([
      axios.get(endpoints.channel.list),
      axios.get(endpoints.status.list),
      axios.get(endpoints.bank.list),
      axios.get('/api/ref/project-phases', { params: { _: Date.now() } }),
    ]);
    channels.value = c.data.data;
    statuses.value = s.data.data;
    banks.value    = b.data.data;
    phases.value   = p.data.data;
  } finally { loading.value = false; }
}
onMounted(loadAll);

async function refetchPhases() {
  const { data } = await axios.get('/api/ref/project-phases', {
    params: { _: Date.now() },
    headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' },
  });
  phases.value = data.data;
}

// ───── SOP edit modal ─────
const showSopEdit = ref(false);
const sopForm = reactive({
  id: null, name: '', description: '', sop_level: 1,
  detailsSummary: '', detailsFooter: '', detailsBullets: [],
});
const sopErr = ref({});
const sopSaving = ref(false);

function openSopAdd() {
  const maxLvl = phases.value.length ? Math.max(...phases.value.map(p => p.sop_level)) : 0;
  Object.assign(sopForm, {
    id: null, name: '', description: '', sop_level: maxLvl + 1,
    detailsSummary: '', detailsFooter: '', detailsBullets: [],
  });
  sopErr.value = {};
  showSopEdit.value = true;
}

function openSopEdit(phase) {
  const d = phase.details || {};
  Object.assign(sopForm, {
    id: phase.id, name: phase.name || '',
    description: phase.description || '', sop_level: phase.sop_level || 1,
    detailsSummary: d.summary || '',
    detailsFooter:  d.footer  || '',
    detailsBullets: Array.isArray(d.bullets)
      ? d.bullets.map(b => ({ icon: b.icon || 'fi-rr-circle', text: b.text || '', subtitle: b.subtitle || '', count: b.count ?? null }))
      : [],
  });
  sopErr.value = {};
  showSopEdit.value = true;
}

function addSopBullet() { sopForm.detailsBullets.push({ icon: 'fi-rr-circle', text: '', subtitle: '', count: null }); }
function removeSopBullet(i) { sopForm.detailsBullets.splice(i, 1); }
function moveSopBullet(i, dir) {
  const j = i + dir;
  if (j < 0 || j >= sopForm.detailsBullets.length) return;
  [sopForm.detailsBullets[i], sopForm.detailsBullets[j]] = [sopForm.detailsBullets[j], sopForm.detailsBullets[i]];
}

async function saveSop() {
  sopSaving.value = true; sopErr.value = {};
  try {
    const bullets = sopForm.detailsBullets
      .filter(b => b.text?.trim())
      .map(b => {
        const out = { icon: b.icon || 'fi-rr-circle', text: b.text.trim() };
        if (b.subtitle?.trim()) out.subtitle = b.subtitle.trim();
        if (b.count !== null && b.count !== '' && !isNaN(Number(b.count))) out.count = Number(b.count);
        return out;
      });
    const details = (sopForm.detailsSummary || sopForm.detailsFooter || bullets.length)
      ? { summary: sopForm.detailsSummary || null, footer: sopForm.detailsFooter || null, bullets }
      : null;
    const payload = {
      name: sopForm.name, description: sopForm.description,
      sop_level: sopForm.sop_level, details,
    };
    const { data } = sopForm.id
      ? await axios.patch(`/api/admin/phases/${sopForm.id}`, payload)
      : await axios.post('/api/admin/phases', payload);
    showSopEdit.value = false;
    if (data?.phases) phases.value = data.phases;
    else await refetchPhases();
  } catch (e) {
    sopErr.value = e.response?.data?.errors || { general: [e.response?.data?.message || 'ผิดพลาด'] };
  } finally { sopSaving.value = false; }
}

async function setSopCurrentInSettings(phase) {
  if (!confirm(`ตั้งขั้นปัจจุบันเป็น "ชั้น ${phase.sop_level} — ${phase.name}" ?`)) return;
  const { data } = await axios.post(`/api/admin/phases/${phase.id}/set-current`);
  if (data?.phases) phases.value = data.phases;
  else await refetchPhases();
}

async function deleteSop(phase) {
  if (phase.is_current) { alert('ลบขั้นที่เป็นปัจจุบันไม่ได้ — กรุณาตั้งขั้นอื่นเป็นปัจจุบันก่อน'); return; }
  if (!confirm(`ลบ "ชั้น ${phase.sop_level} — ${phase.name}" ?`)) return;
  try {
    const { data } = await axios.delete(`/api/admin/phases/${phase.id}`);
    if (data?.phases) phases.value = data.phases;
    else await refetchPhases();
  } catch (e) { alert(e.response?.data?.message || 'ลบไม่สำเร็จ'); }
}

const sortedPhases = computed(() => [...phases.value].sort((a, b) => (a.sop_level - b.sop_level)));

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

      <!-- Submenu — dropdown -->
      <div class="card p-3 flex items-center gap-3 flex-wrap">
        <label class="text-sm font-medium shrink-0">เลือกเมนูย่อย:</label>
        <div class="relative flex-1 min-w-0 sm:max-w-xs">
          <select v-model="tab"
                  class="w-full pl-3 pr-9 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-medium appearance-none">
            <option v-for="t in tabs" :key="t.key" :value="t.key">{{ t.label }}</option>
          </select>
          <i class="fi-rr-angle-small-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"></i>
        </div>
        <span class="text-[11px] text-slate-500 hidden sm:inline">
          <i :class="tabs.find(t => t.key === tab)?.icon"></i>
          {{ tabs.find(t => t.key === tab)?.label }}
        </span>
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

      <!-- SOP — ขั้นตอน + กระบวนการดำเนินงาน -->
      <div v-show="!loading && tab === 'sop'" class="space-y-2">
        <div class="card p-3 flex items-center justify-between flex-wrap gap-2">
          <div>
            <div class="text-sm font-semibold">ขั้นตอน SOP + กระบวนการดำเนินงาน</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
              {{ phases.length }} ขั้น · ปัจจุบัน: ชั้น {{ phases.find(p => p.is_current)?.sop_level || '—' }}
            </div>
          </div>
          <button @click="openSopAdd" class="btn-green px-3 py-2 text-sm flex items-center gap-1.5">
            <i class="fi-rr-add"></i> เพิ่มขั้นใหม่
          </button>
        </div>

        <div v-for="p in sortedPhases" :key="p.id"
             :class="['card p-4 transition',
                      p.is_current ? 'ring-2 ring-blue-500 bg-blue-50/30 dark:bg-blue-900/10' : '']">
          <div class="flex items-start gap-3">
            <div :class="['w-10 h-10 shrink-0 rounded-xl text-white flex items-center justify-center font-bold',
                          p.is_current ? 'bg-blue-700' : 'bg-slate-400 dark:bg-slate-600']">
              {{ p.sop_level }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold">ชั้น {{ p.sop_level }} · {{ p.name }}</span>
                <span v-if="p.is_current" class="text-[10px] px-2 py-0.5 rounded-full bg-blue-700 text-white">กำลังดำเนินการ</span>
              </div>
              <div v-if="p.description" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ p.description }}</div>
              <!-- กระบวนการดำเนินงาน (bullets) -->
              <div v-if="p.details?.bullets?.length" class="mt-2 flex flex-wrap gap-1.5">
                <span v-for="(b, i) in p.details.bullets" :key="i"
                      class="inline-flex items-center gap-1 text-[10px] px-2 py-1 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                  <i :class="b.icon || 'fi-rr-circle'"></i> {{ b.text }}
                </span>
              </div>
              <div v-else class="mt-2 text-[10px] text-slate-400 italic">— ยังไม่มีกระบวนการดำเนินงานย่อย</div>
            </div>
            <div class="shrink-0 flex flex-col items-end gap-1">
              <div class="flex items-center gap-0.5">
                <button @click="openSopEdit(p)" class="p-1.5 hover:bg-blue-50 dark:hover:bg-slate-800 rounded-lg text-blue-700" title="แก้ไข"><i class="fi-rr-edit text-sm"></i></button>
                <button @click="deleteSop(p)" :disabled="p.is_current"
                        class="p-1.5 hover:bg-red-50 dark:hover:bg-slate-800 rounded-lg text-red-600 disabled:opacity-30 disabled:cursor-not-allowed" title="ลบ">
                  <i class="fi-rr-trash text-sm"></i>
                </button>
              </div>
              <button v-if="!p.is_current" @click="setSopCurrentInSettings(p)"
                      class="text-[10px] px-2 py-1 rounded-lg border border-dashed border-slate-300 dark:border-slate-600 hover:border-blue-400 hover:text-blue-700 text-slate-500 whitespace-nowrap">
                <i class="fi-rr-pin"></i> ตั้งเป็นปัจจุบัน
              </button>
            </div>
          </div>
        </div>
        <div v-if="phases.length === 0" class="card p-8 text-center text-sm text-slate-500">ยังไม่มีขั้น SOP</div>
      </div>

      <!-- SOP Edit Modal -->
      <Modal :show="showSopEdit" max-width="max-w-2xl" @close="showSopEdit = false">
        <div class="flex items-center justify-between mb-3">
          <div class="font-semibold flex items-center gap-2">
            <i :class="sopForm.id ? 'fi-rr-edit' : 'fi-rr-add'" class="text-blue-700"></i>
            {{ sopForm.id ? 'แก้ไขขั้น SOP' : 'เพิ่มขั้น SOP ใหม่' }}
          </div>
          <button @click="showSopEdit = false" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg"><i class="fi-rr-cross-small"></i></button>
        </div>

        <div v-if="sopErr?.general" class="card-tint-red p-3 text-sm mb-3"><i class="fi-rr-cross-circle"></i> {{ sopErr.general[0] }}</div>

        <form @submit.prevent="saveSop" class="space-y-3">
          <div class="grid grid-cols-[80px_1fr] gap-3">
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">ชั้นที่ <span class="text-red-600">*</span></label>
              <input v-model.number="sopForm.sop_level" type="number" min="1" max="99" required
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            </div>
            <div>
              <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">ชื่อขั้นตอน <span class="text-red-600">*</span></label>
              <input v-model="sopForm.name" required maxlength="150"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            </div>
          </div>
          <div>
            <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">คำอธิบายขั้นตอน</label>
            <textarea v-model="sopForm.description" rows="2" maxlength="500"
                      class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm"></textarea>
          </div>

          <!-- กระบวนการดำเนินงาน (bullets) -->
          <div class="border-t border-slate-100 dark:border-slate-800 pt-3">
            <div class="text-xs font-semibold mb-2 flex items-center gap-1.5">
              <i class="fi-rr-list-check"></i> กระบวนการดำเนินงาน
            </div>
            <div>
              <label class="block text-[11px] text-slate-500 mb-1">บทสรุปสั้น</label>
              <input v-model="sopForm.detailsSummary" maxlength="500"
                     class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs">
            </div>
            <div class="mt-3">
              <div class="flex items-center justify-between mb-1.5">
                <label class="text-[11px] text-slate-500">ขั้นตอนย่อย (มีไอคอนนำหน้า)</label>
                <button type="button" @click="addSopBullet" class="text-[11px] text-blue-700 hover:underline flex items-center gap-1">
                  <i class="fi-rr-add"></i> เพิ่มขั้นตอน
                </button>
              </div>
              <div v-if="sopForm.detailsBullets.length === 0" class="text-[11px] text-slate-400 text-center py-3 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                ยังไม่มีขั้นตอนย่อย
              </div>
              <div v-for="(b, i) in sopForm.detailsBullets" :key="i"
                   class="border border-slate-100 dark:border-slate-700 rounded-lg p-2 mb-2 space-y-1.5">
                <div class="flex items-center gap-1.5">
                  <select v-model="b.icon"
                          class="px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs w-32 shrink-0">
                    <option v-for="o in ICON_BULLET_OPTIONS" :key="o.i" :value="o.i">{{ o.l }}</option>
                  </select>
                  <i :class="[b.icon, 'text-base text-slate-600 dark:text-slate-300 shrink-0 w-5 text-center']"></i>
                  <input v-model="b.text" maxlength="300" placeholder="ข้อความขั้นตอน (เช่น ตรวจสอบสิทธิ์)"
                         class="flex-1 min-w-0 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs">
                  <button type="button" @click="moveSopBullet(i, -1)" :disabled="i === 0" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-700 rounded disabled:opacity-30">
                    <i class="fi-rr-angle-up text-xs"></i>
                  </button>
                  <button type="button" @click="moveSopBullet(i, 1)" :disabled="i === sopForm.detailsBullets.length - 1" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-700 rounded disabled:opacity-30">
                    <i class="fi-rr-angle-down text-xs"></i>
                  </button>
                  <button type="button" @click="removeSopBullet(i)" class="p-1.5 hover:bg-red-50 dark:hover:bg-red-900/30 rounded text-red-600">
                    <i class="fi-rr-cross-small text-xs"></i>
                  </button>
                </div>
                <div class="grid grid-cols-2 gap-1.5 pl-[8.5rem] pr-[6rem]">
                  <input v-model="b.subtitle" maxlength="200" placeholder="หมายเหตุย่อย (เช่น กรมบัญชีกลาง) — ไม่บังคับ"
                         class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-[11px] text-slate-500">
                  <input v-model="b.count" type="number" min="0" placeholder="จำนวน (ไม่บังคับ)"
                         class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-[11px]">
                </div>
              </div>
            </div>
            <div class="mt-3">
              <label class="block text-[11px] text-slate-500 mb-1">หมายเหตุท้ายขั้น (footer)</label>
              <input v-model="sopForm.detailsFooter" maxlength="500"
                     class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs">
            </div>
          </div>

          <div class="flex gap-2 justify-end pt-1">
            <button type="button" @click="showSopEdit = false" class="btn-outline px-4 py-2 text-sm">ยกเลิก</button>
            <button type="submit" :disabled="sopSaving" class="btn-primary px-4 py-2 text-sm flex items-center gap-1.5">
              <i :class="['fi-rr-disk', sopSaving && 'animate-spin']"></i> บันทึก
            </button>
          </div>
        </form>
      </Modal>

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

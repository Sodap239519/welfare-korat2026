<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, onMounted, onBeforeUnmount, reactive, watch, nextTick } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { formatNumber } from '@/composables/useApi';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const mapEl = ref(null);
let map = null;
let markerLayer = null;

const villages = ref([]);
const loading = ref(false);
const amphurOpts = ref([]);
const tambonOpts = ref([]);
const filters = reactive({ amphur_id: '', tambon_id: '' });

const stats = ref({ total: 0, done: 0, pct: 0, villages: 0 });

// Edit mode — Super Admin ลากหมุดแก้พิกัด
const editMode = ref(false);
const editFlash = ref('');

// Undo/Redo history — แต่ละ entry = { villageId, oldLat, oldLng, newLat, newLng }
const undoStack = ref([]);
const redoStack = ref([]);
const undoBusy  = ref(false);

function pushHistory(villageId, oldLat, oldLng, newLat, newLng) {
  undoStack.value.push({ villageId, oldLat, oldLng, newLat, newLng });
  redoStack.value = []; // ตัด redo เพราะมีการเปลี่ยนใหม่
  if (undoStack.value.length > 50) undoStack.value.shift(); // จำกัดที่ 50 step
}

async function applyCoordsChange(villageId, lat, lng) {
  await axios.patch(`/api/villages/${villageId}/coords`, { lat, lng });
  const v = villages.value.find(x => x.id === villageId);
  if (v) { v.lat = lat; v.lng = lng; }
  renderMarkers(false);
}

async function undoCoord() {
  if (undoStack.value.length === 0 || undoBusy.value) return;
  undoBusy.value = true;
  const entry = undoStack.value.pop();
  try {
    await applyCoordsChange(entry.villageId, entry.oldLat, entry.oldLng);
    redoStack.value.push(entry);
    const v = villages.value.find(x => x.id === entry.villageId);
    editFlash.value = `ย้อน ${v?.name || '#'+entry.villageId} กลับที่เดิม`;
    setTimeout(() => { editFlash.value = ''; }, 2000);
  } catch (e) {
    undoStack.value.push(entry); // ดันกลับ
    alert('Undo ล้มเหลว: ' + (e.response?.data?.message || e.message));
  } finally { undoBusy.value = false; }
}

async function redoCoord() {
  if (redoStack.value.length === 0 || undoBusy.value) return;
  undoBusy.value = true;
  const entry = redoStack.value.pop();
  try {
    await applyCoordsChange(entry.villageId, entry.newLat, entry.newLng);
    undoStack.value.push(entry);
    const v = villages.value.find(x => x.id === entry.villageId);
    editFlash.value = `ทำซ้ำ ${v?.name || '#'+entry.villageId}`;
    setTimeout(() => { editFlash.value = ''; }, 2000);
  } catch (e) {
    redoStack.value.push(entry);
    alert('Redo ล้มเหลว: ' + (e.response?.data?.message || e.message));
  } finally { undoBusy.value = false; }
}

// Keyboard shortcut: Ctrl+Z / Ctrl+Shift+Z (เฉพาะ edit mode)
function onKeyDown(e) {
  if (!editMode.value) return;
  if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key === 'z') {
    e.preventDefault(); undoCoord();
  } else if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.shiftKey && e.key === 'Z'))) {
    e.preventDefault(); redoCoord();
  }
}

function colorFor(pct) {
  if (pct >= 80) return '#16a34a'; // green
  if (pct >= 50) return '#f97316'; // orange
  return '#dc2626';                 // red
}

function makeIcon(pct) {
  const color = colorFor(pct);
  const label = pct + '%';
  return L.divIcon({
    className: '',
    html: `
      <div style="position: relative;">
        <div style="
          width: 44px; height: 44px;
          background: ${color};
          border: 3px solid white;
          border-radius: 50% 50% 50% 0;
          transform: rotate(-45deg);
          box-shadow: 0 4px 10px rgba(0,0,0,0.25);
          display: flex; align-items: center; justify-content: center;">
          <div style="
            transform: rotate(45deg);
            color: white;
            font-family: 'Prompt', sans-serif;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: -0.3px;">${label}</div>
        </div>
      </div>`,
    iconSize: [44, 44],
    iconAnchor: [22, 44],
    popupAnchor: [0, -42],
  });
}

function popupHtml(v) {
  return `
    <div style="font-family: 'Prompt', sans-serif; min-width: 180px;">
      <div style="font-weight: 600; font-size: 13px;">${v.name}</div>
      <div style="font-size: 11px; color: #64748b; margin-top: 2px;">ต.${v.tambon} · อ.${v.amphur}</div>
      <div style="margin-top: 8px; display: flex; gap: 8px; font-size: 11px;">
        <div style="flex: 1; text-align: center; padding: 6px; background: #eff6ff; border-radius: 8px;">
          <div style="color: #64748b;">เป้า</div>
          <div style="font-weight: 700; font-size: 14px;">${formatNumber(v.total)}</div>
        </div>
        <div style="flex: 1; text-align: center; padding: 6px; background: #f0fdf4; border-radius: 8px;">
          <div style="color: #64748b;">ลงทะเบียน</div>
          <div style="font-weight: 700; font-size: 14px; color: #15803d;">${formatNumber(v.done)}</div>
        </div>
        <div style="flex: 1; text-align: center; padding: 6px; background: ${v.pct >= 80 ? '#f0fdf4' : v.pct >= 50 ? '#fff7ed' : '#fef2f2'}; border-radius: 8px;">
          <div style="color: #64748b;">%</div>
          <div style="font-weight: 700; font-size: 14px; color: ${colorFor(v.pct)};">${v.pct}%</div>
        </div>
      </div>
      <button
        onclick="window.__wkGoVillage(${v.id}, ${v.tambon_id}, ${v.amphur_id})"
        style="margin-top: 10px; width: 100%; padding: 6px 10px; background: #1d4ed8; color: white; border: 0; border-radius: 8px; cursor: pointer; font-size: 12px; font-family: 'Prompt', sans-serif;">
        ดูรายชื่อในหมู่บ้านนี้ →
      </button>
    </div>`;
}

async function loadOpts() {
  amphurOpts.value = (await axios.get('/api/ref/amphurs')).data.data;
}
async function loadTambons() {
  filters.tambon_id = '';
  if (!filters.amphur_id) { tambonOpts.value = []; return; }
  tambonOpts.value = (await axios.get('/api/ref/tambons', { params: { amphur_id: filters.amphur_id } })).data.data;
}

async function loadVillages() {
  loading.value = true;
  try {
    const params = {};
    if (filters.amphur_id) params.amphur_id = filters.amphur_id;
    if (filters.tambon_id) params.tambon_id = filters.tambon_id;
    const { data } = await axios.get('/api/map/villages', { params });
    villages.value = data.data;

    // stats
    const totalT = villages.value.reduce((a, v) => a + v.total, 0);
    const doneT  = villages.value.reduce((a, v) => a + v.done, 0);
    stats.value = {
      total:    totalT,
      done:     doneT,
      pct:      totalT > 0 ? Math.round((doneT / totalT) * 100) : 0,
      villages: villages.value.length,
    };

    renderMarkers();
  } finally { loading.value = false; }
}

function renderMarkers(fitBounds = true) {
  if (!map) return;
  if (markerLayer) { map.removeLayer(markerLayer); }
  markerLayer = L.layerGroup().addTo(map);

  const bounds = [];
  villages.value.forEach(v => {
    const draggable = editMode.value && auth.isSuperAdmin;
    const m = L.marker([v.lat, v.lng], { icon: makeIcon(v.pct), draggable });

    if (!draggable) {
      m.bindPopup(popupHtml(v), { maxWidth: 260 });
    } else {
      m.bindTooltip(`${v.name} (ม.${v.moo || '?'}) · ลากเพื่อย้าย`,
        { permanent: false, direction: 'top', offset: [0, -42] });
      m.on('dragend', async (e) => {
        const { lat, lng } = e.target.getLatLng();
        const oldLat = v.lat, oldLng = v.lng;
        try {
          await axios.patch(`/api/villages/${v.id}/coords`, { lat, lng });
          v.lat = lat; v.lng = lng;
          pushHistory(v.id, oldLat, oldLng, lat, lng);  // record for undo
          editFlash.value = `บันทึก ${v.name} ที่ [${lat.toFixed(5)}, ${lng.toFixed(5)}] แล้ว`;
          setTimeout(() => { editFlash.value = ''; }, 2500);
        } catch (err) {
          alert('บันทึกไม่สำเร็จ: ' + (err.response?.data?.message || err.message));
          e.target.setLatLng([v.lat, v.lng]); // ดีดกลับ
        }
      });
    }
    m.addTo(markerLayer);
    bounds.push([v.lat, v.lng]);
  });

  if (fitBounds && bounds.length > 0) {
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
  }
}

function toggleEditMode() {
  editMode.value = !editMode.value;
  renderMarkers(false);  // re-render โดยไม่ reset bounds
}

function initMap() {
  // Default center ~ Pakchong, Korat
  map = L.map(mapEl.value, { zoomControl: true, attributionControl: true })
    .setView([14.7, 101.4], 11);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map);
}

onMounted(async () => {
  await loadOpts();
  await nextTick();
  initMap();
  await loadVillages();
  // Make popup button navigate
  window.__wkGoVillage = (villageId, tambonId, amphurId) => {
    router.push({
      name: 'targets',
      query: { amphur_id: amphurId, tambon_id: tambonId, village_id: villageId },
    });
  };
  window.addEventListener('keydown', onKeyDown);
});

onBeforeUnmount(() => {
  if (map) { map.remove(); map = null; }
  delete window.__wkGoVillage;
  window.removeEventListener('keydown', onKeyDown);
});

watch(() => filters.amphur_id, async () => { await loadTambons(); await loadVillages(); });
watch(() => filters.tambon_id, loadVillages);
</script>

<template>
  <AppLayout title="แผนที่หมู่บ้าน" subtitle="แสดง % ลงทะเบียนของแต่ละหมู่บ้านบนแผนที่">
    <div class="space-y-4">

      <!-- Hero compact -->
      <div class="card-hero p-4 flex items-center justify-between flex-wrap gap-2">
        <div>
          <div class="text-xs opacity-80">หมู่บ้านในขอบเขตที่เลือก</div>
          <div class="text-2xl lg:text-3xl font-bold mt-0.5">{{ formatNumber(stats.villages) }} <span class="text-sm font-normal opacity-80">หมู่บ้าน</span></div>
        </div>
        <div class="text-right">
          <div class="text-xs opacity-80">รวมความคืบหน้า</div>
          <div class="text-2xl font-bold">{{ stats.pct }}%</div>
          <div class="text-xs opacity-80">{{ formatNumber(stats.done) }} / {{ formatNumber(stats.total) }}</div>
        </div>
      </div>

      <!-- Filter -->
      <div class="card p-3 grid grid-cols-2 gap-2">
        <div class="relative min-w-0">
          <select v-model="filters.amphur_id" class="w-full min-w-0 pl-3 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            <option value="">ทุกอำเภอ</option>
            <option v-for="a in amphurOpts" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <button v-if="filters.amphur_id" @click="filters.amphur_id = ''" title="ล้าง"
                  class="absolute right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <i class="fi-rr-cross-small text-[10px]"></i>
          </button>
        </div>
        <div class="relative min-w-0">
          <select v-model="filters.tambon_id" :disabled="!filters.amphur_id" class="w-full min-w-0 pl-3 pr-9 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm disabled:opacity-40">
            <option value="">ทุกตำบล</option>
            <option v-for="t in tambonOpts" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <button v-if="filters.tambon_id" @click="filters.tambon_id = ''" title="ล้าง"
                  class="absolute right-7 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <i class="fi-rr-cross-small text-[10px]"></i>
          </button>
        </div>
      </div>

      <!-- Legend + Edit mode toggle -->
      <div class="card p-3 flex flex-wrap items-center gap-3 text-xs">
        <span class="font-medium">สีตามความคืบหน้า:</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full" style="background:#16a34a"></span> ≥ 80% ดีเยี่ยม</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full" style="background:#f97316"></span> 50-79% ปานกลาง</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full" style="background:#dc2626"></span> &lt; 50% ต้องเร่งรัด</span>

        <!-- Super Admin only: toggle drag-to-edit -->
        <button v-if="auth.isSuperAdmin" @click="toggleEditMode"
                :class="['ml-auto px-3 py-1.5 rounded-lg text-xs font-medium flex items-center gap-1.5 transition',
                         editMode
                           ? 'bg-orange-500 text-white shadow'
                           : 'border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800']">
          <i :class="editMode ? 'fi-rr-check' : 'fi-rr-edit'"></i>
          {{ editMode ? 'เสร็จสิ้นการแก้พิกัด' : 'แก้พิกัดหมุด (ลากบนแผนที่)' }}
        </button>
      </div>

      <!-- Edit mode banner + Undo/Redo -->
      <div v-if="editMode" class="card-tint-orange p-3 text-xs flex items-center gap-2 flex-wrap">
        <i class="fi-rr-info text-orange-700 shrink-0"></i>
        <div class="flex-1 min-w-0">
          <b>โหมดแก้พิกัด:</b> ลาก ↔ หมุดไปยังตำแหน่งจริง → ระบบบันทึกอัตโนมัติเมื่อปล่อย
          <span class="opacity-80"> · zoom เข้าก่อนเพื่อความแม่นยำ · Ctrl+Z / Ctrl+Y ก็ได้</span>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <button @click="undoCoord" :disabled="undoStack.length === 0 || undoBusy"
                  class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 border border-orange-300 dark:border-slate-600 text-orange-700 dark:text-orange-300 hover:bg-orange-50 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1"
                  :title="`Undo (${undoStack.length})`">
            <i class="fi-rr-undo"></i>
            <span class="hidden sm:inline">Undo</span>
            <span v-if="undoStack.length" class="text-[10px] opacity-70">({{ undoStack.length }})</span>
          </button>
          <button @click="redoCoord" :disabled="redoStack.length === 0 || undoBusy"
                  class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 border border-orange-300 dark:border-slate-600 text-orange-700 dark:text-orange-300 hover:bg-orange-50 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1"
                  :title="`Redo (${redoStack.length})`">
            <i class="fi-rr-redo"></i>
            <span class="hidden sm:inline">Redo</span>
            <span v-if="redoStack.length" class="text-[10px] opacity-70">({{ redoStack.length }})</span>
          </button>
        </div>
      </div>

      <!-- Flash message after save -->
      <div v-if="editFlash" class="card-tint-green p-3 text-xs flex items-center gap-2">
        <i class="fi-rr-check-circle text-green-700"></i> {{ editFlash }}
      </div>

      <!-- Map container — isolate stacking context กัน leaflet panes แทรกขึ้น Modal -->
      <div class="card p-0 overflow-hidden relative" style="isolation: isolate;">
        <div ref="mapEl" class="w-full relative" style="height: 70vh; min-height: 400px; z-index: 0;"></div>
        <div v-if="loading" class="absolute inset-0 bg-white/60 dark:bg-slate-900/60 backdrop-blur-sm flex items-center justify-center">
          <i class="fi-rr-spinner animate-spin text-3xl text-blue-700"></i>
        </div>
      </div>

      <div class="text-xs text-slate-500 text-center">
        ข้อมูลแผนที่ © <a href="https://www.openstreetmap.org/copyright" target="_blank" class="underline">OpenStreetMap</a> contributors
      </div>
    </div>
  </AppLayout>
</template>

<style>
/* Leaflet popup font fix */
.leaflet-popup-content-wrapper { border-radius: 14px; }
.leaflet-popup-content { margin: 12px; }
.dark .leaflet-popup-content-wrapper { background: #1f2937; color: #e2e8f0; }
.dark .leaflet-popup-tip { background: #1f2937; }
</style>

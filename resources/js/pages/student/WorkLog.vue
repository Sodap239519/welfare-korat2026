<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';

const logs = ref([]);
const loading = ref(false);
const saving = ref(false);
const showForm = ref(false);
const editingId = ref(null);
const errorMsg = ref('');
const flashOk = ref('');

const PERIODS = ['เช้า', 'บ่าย', 'เย็น', 'เพิ่มเติม'];
const ACTIVITY_SUGGESTIONS = ['ให้คำแนะนำการลงทะเบียน', 'ช่วยกรอกข้อมูล', 'ตรวจสอบเอกสาร', 'คัดกรองกลุ่มตกหล่น'];

// หมวดไฟล์แนบ
const CATS = [
  { key: 'worklog_doc',   label: 'ใบบันทึกการปฏิบัติงาน', icon: 'fi-rr-file-invoice', accept: '.pdf,image/*,.doc,.docx,.xls,.xlsx', photo: false },
  { key: 'reimburse_doc', label: 'เอกสารเบิกจ่าย',       icon: 'fi-rr-receipt',      accept: '.pdf,image/*,.doc,.docx,.xls,.xlsx', photo: false },
  { key: 'photo',         label: 'ภาพการปฏิบัติงาน',     icon: 'fi-rr-camera',       accept: 'image/*', photo: true },
];

const blankForm = () => ({
  work_date: new Date().toISOString().slice(0, 10),
  time_start: '', time_end: '',
  registered_success: 0, registered_fail: 0,
  supervisor_name: '', supervisor_position: '', supervisor_date: '',
  entries: [{ period: 'เช้า', activity_type: '', detail: '', service_count: null }],
  cases: [],
});
const form = reactive(blankForm());

// เลือก "เพิ่มเติม" → ตั้งช่วงเวลาเป็น "เย็น" อัตโนมัติ (ยังแก้ไขเป็นช่วงอื่นได้)
watch(() => form.entries.map(e => e.period), (periods) => {
  periods.forEach((p, i) => {
    if (p === 'เพิ่มเติม' && form.entries[i]) form.entries[i].period = 'เย็น';
  });
});

// ── GPS ──
const geo = reactive({ lat: null, lng: null, accuracy: null, at: null, status: '', error: '' });
const locating = ref(false);
function resetGeo() { Object.assign(geo, { lat: null, lng: null, accuracy: null, at: null, status: '', error: '' }); }
function getLocation() {
  geo.error = '';
  if (!navigator.geolocation) { geo.error = 'อุปกรณ์ไม่รองรับ GPS'; geo.status = 'none'; return; }
  locating.value = true;
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      geo.lat = +pos.coords.latitude.toFixed(7);
      geo.lng = +pos.coords.longitude.toFixed(7);
      geo.accuracy = Math.round(pos.coords.accuracy);
      geo.at = new Date().toISOString();
      geo.status = geo.accuracy > 100 ? 'low_accuracy' : 'ok';
      locating.value = false;
    },
    (err) => {
      geo.status = 'none';
      geo.error = err.code === 1 ? 'ถูกปฏิเสธการเข้าถึงตำแหน่ง — กรุณาอนุญาตใน Settings เบราว์เซอร์' : 'ระบุตำแหน่งไม่ได้ ลองใหม่อีกครั้ง';
      locating.value = false;
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
  );
}

// ── ไฟล์ ──
const pending = reactive({ worklog_doc: [], reimburse_doc: [], photo: [] });
const existing = ref([]);
function onPick(cat, e) {
  for (const file of Array.from(e.target.files || [])) {
    const isImage = file.type.startsWith('image/');
    pending[cat].push({ file, isImage, failed: false, url: isImage ? URL.createObjectURL(file) : null, name: file.name, size: file.size });
  }
  e.target.value = '';
}
function removePending(cat, i) {
  const p = pending[cat][i];
  if (p?.url) URL.revokeObjectURL(p.url);
  pending[cat].splice(i, 1);
}
function clearPending() {
  for (const c of Object.keys(pending)) { pending[c].forEach(p => p.url && URL.revokeObjectURL(p.url)); pending[c] = []; }
}
const existingByCat = (cat) => existing.value.filter(f => f.category === cat);
const fileUrl = (f) => `/api/files/work/${f.id}`;
async function deleteExisting(f) {
  if (!confirm('ลบไฟล์นี้?')) return;
  await axios.delete(`/api/student/work-files/${f.id}`);
  existing.value = existing.value.filter(x => x.id !== f.id);
}
function fmtSize(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.ceil(b / 1024) + ' KB'; }

function resetForm() {
  Object.assign(form, blankForm());
  editingId.value = null;
  existing.value = [];
  clearPending();
  resetGeo();
}

async function load() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/student/work-logs');
    logs.value = data.data;
  } finally { loading.value = false; }
}
onMounted(load);

function openCreate() {
  resetForm();
  showForm.value = true;
  flashOk.value = '';
  getLocation();
}

async function openEdit(id) {
  errorMsg.value = '';
  resetForm();
  const { data } = await axios.get(`/api/student/work-logs/${id}`);
  const d = data.data;
  Object.assign(form, {
    work_date: d.work_date?.slice(0, 10) ?? '',
    time_start: d.time_start ? d.time_start.slice(0, 5) : '',
    time_end: d.time_end ? d.time_end.slice(0, 5) : '',
    registered_success: d.registered_success,
    registered_fail: d.registered_fail,
    supervisor_name: d.supervisor_name ?? '',
    supervisor_position: d.supervisor_position ?? '',
    supervisor_date: d.supervisor_date?.slice(0, 10) ?? '',
    entries: d.entries.length ? d.entries.map(e => ({ ...e })) : [{ period: 'เช้า', activity_type: '', detail: '', service_count: 0 }],
    cases: d.cases.map(c => ({ ...c })),
  });
  // คงพิกัดเดิม (แก้ไขได้โดยกดระบุใหม่)
  Object.assign(geo, { lat: d.lat, lng: d.lng, accuracy: d.location_accuracy, at: d.location_at, status: d.location_status || (d.lat ? 'ok' : ''), error: '' });
  existing.value = d.files || [];
  editingId.value = id;
  showForm.value = true;
  flashOk.value = '';
}

const addEntry = () => form.entries.push({ period: 'เย็น', activity_type: '', detail: '', service_count: null });
const removeEntry = (i) => form.entries.splice(i, 1);
const addCase = () => form.cases.push({ full_name: '', phone: '', village_tambon: '', problem: '' });
const removeCase = (i) => form.cases.splice(i, 1);

const hasLocation = computed(() => geo.lat != null && geo.lng != null);
const serviceTotal = computed(() => form.entries.reduce((s, e) => s + (Number(e.service_count) || 0), 0));
const resultTotal = computed(() => (Number(form.registered_success) || 0) + (Number(form.registered_fail) || 0));
const totalsMatch = computed(() => serviceTotal.value === resultTotal.value);

async function save() {
  if (!hasLocation.value) {
    errorMsg.value = 'ต้องระบุตำแหน่ง (GPS) ก่อนบันทึก — กดปุ่ม "ระบุตำแหน่ง"';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  // ผลรวมสำเร็จ+ไม่สำเร็จ ต้องเท่ากับจำนวนผู้รับบริการรวม
  if (!totalsMatch.value) {
    errorMsg.value = `ผลรวมลงทะเบียนสำเร็จ + ไม่สำเร็จ (${resultTotal.value}) ต้องเท่ากับจำนวนผู้รับบริการรวม (${serviceTotal.value}) พอดี`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  // เตือน (ไม่บังคับ) ให้ระบุปัญหารายคนตามจำนวนที่ไม่สำเร็จ
  const failN = Number(form.registered_fail) || 0;
  const caseN = form.cases.filter(c => c.full_name?.trim() && c.problem?.trim()).length;
  if (failN > 0 && caseN < failN) {
    if (!confirm(`ลงทะเบียนไม่สำเร็จ ${failN} ราย แต่ระบุปัญหารายกรณีเพียง ${caseN} ราย\nแนะนำให้ระบุปัญหาให้ครบทุกราย\n\nต้องการบันทึกต่อหรือไม่?`)) return;
  }
  saving.value = true;
  errorMsg.value = '';
  const payload = {
    ...form,
    time_start: form.time_start || null,
    time_end: form.time_end || null,
    supervisor_date: form.supervisor_date || null,
    lat: geo.lat, lng: geo.lng, location_accuracy: geo.accuracy, location_at: geo.at, location_status: geo.status,
    entries: form.entries.filter(e => e.activity_type?.trim()),
    cases: form.cases.filter(c => c.full_name?.trim() && c.problem?.trim()),
  };
  try {
    let id = editingId.value;
    if (id) await axios.patch(`/api/student/work-logs/${id}`, payload);
    else { const { data } = await axios.post('/api/student/work-logs', payload); id = data.data.id; }

    // อัปโหลดไฟล์ที่ค้างแต่ละหมวด
    for (const cat of Object.keys(pending)) {
      if (!pending[cat].length) continue;
      const fd = new FormData();
      fd.append('category', cat);
      if (geo.lat != null) { fd.append('lat', geo.lat); fd.append('lng', geo.lng); }
      pending[cat].forEach(p => fd.append('files[]', p.file));
      await axios.post(`/api/student/work-logs/${id}/files`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
    }

    showForm.value = false;
    flashOk.value = 'บันทึกเรียบร้อย';
    resetForm();
    await load();
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'บันทึกไม่สำเร็จ — ตรวจสอบข้อมูล';
  } finally {
    saving.value = false;
  }
}

async function remove(id) {
  if (!confirm('ลบบันทึกนี้?')) return;
  await axios.delete(`/api/student/work-logs/${id}`);
  await load();
}
</script>

<template>
  <AppLayout title="บันทึกการปฏิบัติงาน" subtitle="แบบฟอร์มการปฏิบัติงานหนุนเสริม — รายวัน">
    <div v-if="flashOk" class="card-tint-green p-3 text-sm mb-4"><i class="fi-rr-check-circle"></i> {{ flashOk }}</div>

    <!-- รายการบันทึก -->
    <div v-if="!showForm">
      <div class="flex items-center justify-between mb-4 gap-2">
        <div class="text-sm text-slate-500 dark:text-slate-400">ทั้งหมด {{ logs.length }} วัน</div>
        <button @click="openCreate" class="btn-primary"><i class="fi-rr-plus"></i> เพิ่มบันทึกวันนี้</button>
      </div>

      <div v-if="loading" class="text-center text-slate-400 py-10">กำลังโหลด…</div>
      <div v-else-if="!logs.length" class="card p-10 text-center text-slate-400">
        <i class="fi-rr-edit text-3xl"></i>
        <div class="mt-2">ยังไม่มีบันทึก — กด "เพิ่มบันทึกวันนี้" เพื่อเริ่ม</div>
      </div>

      <div v-else class="grid gap-3">
        <div v-for="l in logs" :key="l.id" class="card p-4 flex flex-wrap items-center gap-4">
          <div class="min-w-[7rem]">
            <div class="text-xs text-slate-400">วันที่</div>
            <div class="font-semibold">{{ l.work_date?.slice(0, 10) }}</div>
            <div v-if="l.lat" class="text-[10px] text-green-600 mt-0.5"><i class="fi-rr-marker"></i> มีพิกัด</div>
            <div v-else class="text-[10px] text-amber-500 mt-0.5"><i class="fi-rr-exclamation"></i> ไม่มีพิกัด</div>
          </div>
          <div class="flex gap-4 text-sm flex-1 flex-wrap">
            <div><span class="text-slate-400">ผู้รับบริการ</span> <b>{{ formatNumber(l.service_total || 0) }}</b></div>
            <div class="text-green-600"><span class="text-slate-400">สำเร็จ</span> <b>{{ formatNumber(l.registered_success) }}</b></div>
            <div class="text-red-500"><span class="text-slate-400">ไม่สำเร็จ</span> <b>{{ formatNumber(l.registered_fail) }}</b></div>
            <div><span class="text-slate-400">ไฟล์</span> <b>{{ l.files_count }}</b></div>
            <div><span class="text-slate-400">กรณีปัญหา</span> <b>{{ l.cases_count }}</b></div>
          </div>
          <div class="flex gap-2">
            <button @click="openEdit(l.id)" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800" title="แก้ไข"><i class="fi-rr-pencil"></i></button>
            <button @click="remove(l.id)" class="btn-icon hover:bg-red-50 text-red-500" title="ลบ"><i class="fi-rr-trash"></i></button>
          </div>
        </div>
      </div>
    </div>

    <!-- ฟอร์ม -->
    <div v-else class="space-y-4 max-w-3xl pb-10">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">{{ editingId ? 'แก้ไขบันทึก' : 'เพิ่มบันทึกการปฏิบัติงาน' }}</h2>
        <button @click="showForm = false" class="text-sm text-slate-500 hover:text-slate-700"><i class="fi-rr-cross-small"></i> ปิด</button>
      </div>
      <div v-if="errorMsg" class="card-tint-red p-3 text-sm"><i class="fi-rr-cross-circle"></i> {{ errorMsg }}</div>

      <!-- GPS -->
      <div :class="['p-4', hasLocation ? 'card-tint-green' : 'card-tint-orange']">
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <div class="flex items-center gap-2 text-sm">
            <i class="fi-rr-marker text-lg"></i>
            <div>
              <div class="font-medium">ตำแหน่งการปฏิบัติงาน (GPS) <span class="text-red-600">*</span></div>
              <div v-if="hasLocation" class="text-xs opacity-80">
                บันทึกแล้ว · ความแม่นยำ ~{{ geo.accuracy }} ม.
                <span v-if="geo.status === 'low_accuracy'" class="text-amber-600">(สัญญาณอ่อน)</span>
              </div>
              <div v-else class="text-xs opacity-80">ต้องระบุตำแหน่งจริงเพื่อยืนยันการลงพื้นที่ (ห้ามปักหมุดเอง)</div>
              <div v-if="geo.error" class="text-xs text-red-600 mt-0.5">{{ geo.error }}</div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <a v-if="hasLocation" :href="`https://www.google.com/maps?q=${geo.lat},${geo.lng}`" target="_blank" rel="noopener" class="text-xs text-blue-700 underline">ดูแผนที่</a>
            <button @click="getLocation" :disabled="locating" class="btn-primary text-sm disabled:opacity-60">
              <i :class="locating ? 'fi-rr-spinner animate-spin' : 'fi-rr-refresh'"></i> {{ hasLocation ? 'ระบุใหม่' : 'ระบุตำแหน่ง' }}
            </button>
          </div>
        </div>
      </div>

      <!-- หัว -->
      <div class="card p-4 grid sm:grid-cols-3 gap-3">
        <label class="block">
          <span class="text-xs text-slate-500">วันที่ปฏิบัติงาน *</span>
          <input v-model="form.work_date" type="date" class="w-full mt-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
        <label class="block">
          <span class="text-xs text-slate-500">เวลาเริ่ม</span>
          <input v-model="form.time_start" type="time" class="w-full mt-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
        <label class="block">
          <span class="text-xs text-slate-500">เวลาสิ้นสุด</span>
          <input v-model="form.time_end" type="time" class="w-full mt-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
      </div>

      <!-- กิจกรรม -->
      <div class="card p-4">
        <div class="flex items-center justify-between mb-2">
          <h3 class="font-medium text-sm"><i class="fi-rr-list"></i> กิจกรรมที่ดำเนินการ</h3>
          <button @click="addEntry" class="text-sm text-blue-700"><i class="fi-rr-plus"></i> เพิ่ม</button>
        </div>
        <datalist id="activity-suggestions">
          <option v-for="a in ACTIVITY_SUGGESTIONS" :key="a" :value="a" />
        </datalist>
        <div v-for="(e, i) in form.entries" :key="i" class="grid sm:grid-cols-12 gap-2 mb-2 items-start p-2 sm:p-0 rounded-xl bg-slate-50 sm:bg-transparent dark:bg-slate-800/40 sm:dark:bg-transparent">
          <select v-model="e.period" class="sm:col-span-2 px-2 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
            <option v-for="p in PERIODS" :key="p" :value="p">{{ p }}</option>
          </select>
          <input v-model="e.activity_type" list="activity-suggestions" placeholder="ประเภทกิจกรรม" class="sm:col-span-3 px-2 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <input v-model="e.detail" placeholder="รายละเอียด" class="sm:col-span-4 px-2 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <input v-model.number="e.service_count" type="number" min="0" placeholder="จำนวนผู้รับบริการ" class="sm:col-span-2 px-2 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <button @click="removeEntry(i)" class="sm:col-span-1 btn-icon text-red-400 hover:bg-red-50" title="ลบ"><i class="fi-rr-cross-small"></i></button>
        </div>
      </div>

      <!-- ผล -->
      <div class="card p-4">
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block">
            <span class="text-xs text-slate-500">ลงทะเบียนสำเร็จ (ราย)</span>
            <input v-model.number="form.registered_success" type="number" min="0" class="w-full mt-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          </label>
          <label class="block">
            <span class="text-xs text-slate-500">ลงทะเบียนไม่สำเร็จ (ราย)</span>
            <input v-model.number="form.registered_fail" type="number" min="0" class="w-full mt-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          </label>
        </div>
        <div :class="['mt-2 text-xs px-3 py-2 rounded-lg', totalsMatch ? 'card-tint-green' : 'card-tint-orange']">
          <i :class="totalsMatch ? 'fi-rr-check-circle' : 'fi-rr-info'"></i>
          ผู้รับบริการรวม (จากกิจกรรม) = <b>{{ serviceTotal }}</b> · สำเร็จ+ไม่สำเร็จ = <b>{{ resultTotal }}</b>
          <span v-if="!totalsMatch"> — ต้องเท่ากันพอดี</span>
        </div>
      </div>

      <!-- ไฟล์แนบ 3 หมวด -->
      <div v-for="c in CATS" :key="c.key" class="card p-4">
        <div class="flex items-center justify-between mb-2">
          <h3 class="font-medium text-sm"><i :class="c.icon + ' text-blue-600'"></i> {{ c.label }}</h3>
          <div class="flex gap-2">
            <label class="btn-primary text-xs cursor-pointer">
              <i :class="c.photo ? 'fi-rr-camera' : 'fi-rr-cloud-upload'"></i> {{ c.photo ? 'ถ่ายรูป' : 'เลือกไฟล์' }}
              <input type="file" :accept="c.accept" :capture="c.photo ? 'environment' : undefined" multiple class="hidden" @change="onPick(c.key, $event)">
            </label>
            <label v-if="c.photo" class="btn-outline text-xs cursor-pointer">
              <i class="fi-rr-picture"></i> คลังภาพ
              <input type="file" accept="image/*" multiple class="hidden" @change="onPick(c.key, $event)">
            </label>
          </div>
        </div>

        <!-- ตัวอย่างไฟล์ที่เลือก (ก่อนบันทึก) -->
        <div v-if="pending[c.key].length" class="grid grid-cols-3 sm:grid-cols-4 gap-2 mb-2">
          <div v-for="(p, i) in pending[c.key]" :key="i" class="relative group border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
            <img v-if="p.isImage && !p.failed" :src="p.url" @error="p.failed = true" class="w-full h-24 object-cover" :alt="p.name">
            <div v-else class="h-24 flex flex-col items-center justify-center p-1 text-center bg-slate-50 dark:bg-slate-800">
              <i :class="[(p.isImage ? 'fi-rr-picture text-blue-400' : 'fi-rr-file-pdf text-red-500'), 'text-2xl']"></i>
              <span class="text-[10px] mt-1 line-clamp-2 break-all">{{ p.name }}</span>
              <span v-if="p.isImage && p.failed" class="text-[8px] text-slate-400">(แสดงตัวอย่างไม่ได้ · บันทึกได้)</span>
            </div>
            <div class="text-[9px] text-slate-400 px-1 truncate">{{ fmtSize(p.size) }}</div>
            <button @click="removePending(c.key, i)" class="absolute top-1 right-1 w-6 h-6 grid place-items-center rounded-full bg-black/60 text-white text-xs"><i class="fi-rr-cross-small"></i></button>
          </div>
        </div>

        <!-- ไฟล์ที่อัปโหลดแล้ว (โหมดแก้ไข) -->
        <div v-if="existingByCat(c.key).length">
          <div class="text-xs text-slate-400 mb-1">อัปโหลดแล้ว</div>
          <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
            <div v-for="f in existingByCat(c.key)" :key="f.id" class="relative border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
              <a :href="fileUrl(f)" target="_blank" rel="noopener">
                <img v-if="f.is_image" :src="fileUrl(f)" class="w-full h-24 object-cover" :alt="f.original_name">
                <div v-else class="h-24 flex flex-col items-center justify-center p-1 text-center bg-slate-50 dark:bg-slate-800">
                  <i class="fi-rr-file-pdf text-red-500 text-2xl"></i>
                  <span class="text-[10px] mt-1 line-clamp-2 break-all">{{ f.original_name }}</span>
                </div>
              </a>
              <button @click="deleteExisting(f)" class="absolute top-1 right-1 w-6 h-6 grid place-items-center rounded-full bg-black/60 text-white text-xs"><i class="fi-rr-trash"></i></button>
            </div>
          </div>
        </div>

        <div v-if="!pending[c.key].length && !existingByCat(c.key).length" class="text-xs text-slate-400">— ยังไม่มีไฟล์ —</div>
      </div>

      <!-- ปัญหารายกรณี -->
      <div class="card p-4">
        <div class="flex items-center justify-between mb-2">
          <h3 class="font-medium text-sm"><i class="fi-rr-exclamation"></i> ปัญหาการลงทะเบียนรายกรณี <span class="text-slate-400 font-normal">(ถ้ามี)</span></h3>
          <button @click="addCase" class="text-sm text-blue-700"><i class="fi-rr-plus"></i> เพิ่ม</button>
        </div>
        <div v-if="!form.cases.length" class="text-xs text-slate-400">— ยังไม่มี —</div>
        <div v-for="(c, i) in form.cases" :key="i" class="grid sm:grid-cols-12 gap-2 mb-2 items-start p-2 sm:p-0 rounded-xl bg-slate-50 sm:bg-transparent dark:bg-slate-800/40 sm:dark:bg-transparent">
          <input v-model="c.full_name" placeholder="ชื่อ-สกุล" class="sm:col-span-3 px-2 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <input v-model="c.phone" placeholder="เบอร์โทร" class="sm:col-span-2 px-2 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <input v-model="c.village_tambon" placeholder="หมู่บ้าน/ตำบล" class="sm:col-span-3 px-2 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <input v-model="c.problem" placeholder="ปัญหาที่เกิดขึ้น" class="sm:col-span-3 px-2 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <button @click="removeCase(i)" class="sm:col-span-1 btn-icon text-red-400 hover:bg-red-50" title="ลบ"><i class="fi-rr-cross-small"></i></button>
        </div>
      </div>

      <!-- ผู้ควบคุมงาน -->
      <div class="card p-4 grid sm:grid-cols-3 gap-3">
        <label class="block">
          <span class="text-xs text-slate-500">ผู้ควบคุมงาน</span>
          <input v-model="form.supervisor_name" placeholder="ชื่อ-สกุล" class="w-full mt-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
        <label class="block">
          <span class="text-xs text-slate-500">ตำแหน่ง</span>
          <input v-model="form.supervisor_position" class="w-full mt-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
        <label class="block">
          <span class="text-xs text-slate-500">วันที่</span>
          <input v-model="form.supervisor_date" type="date" class="w-full mt-1 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
      </div>

      <!-- ปุ่ม -->
      <div class="flex gap-2 sticky bottom-0 bg-slate-50/80 dark:bg-slate-950/80 backdrop-blur py-3 -mx-1 px-1">
        <button @click="save" :disabled="saving" class="btn-primary flex-1 sm:flex-none disabled:opacity-60">
          <i v-if="saving" class="fi-rr-spinner animate-spin"></i> {{ editingId ? 'บันทึกการแก้ไข' : 'บันทึก' }}
        </button>
        <button @click="showForm = false" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-sm">ยกเลิก</button>
      </div>
    </div>
  </AppLayout>
</template>

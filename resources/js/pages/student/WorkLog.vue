<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

// วันที่แบบไทย "7 มิถุนายน 2569" — parse จาก string ตรงๆ (กัน timezone เลื่อนวัน)
const TH_MONTHS = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
function thaiDate(iso) {
  if (!iso) return '';
  const [y, m, d] = String(iso).slice(0, 10).split('-').map(Number);
  if (!y || !m || !d) return String(iso).slice(0, 10);
  return `${d} ${TH_MONTHS[m - 1]} ${y + 543}`;
}

const logs = ref([]);
const loading = ref(false);
const saving = ref(false);
const showForm = ref(false);
const editingId = ref(null);
const errorMsg = ref('');
const flashOk = ref('');

const PERIODS = ['เช้า', 'บ่าย', 'เย็น', 'เพิ่มเติม'];
const ACTIVITY_SUGGESTIONS = ['ให้คำแนะนำการลงทะเบียน', 'ช่วยกรอกข้อมูลลงทะเบียน', 'ตรวจสอบเอกสาร', 'คัดกรองกลุ่มตกหล่น', 'จัดอบรม/ให้ความรู้', 'ประชาสัมพันธ์'];

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
const thumb = (u) => u + (u.includes('?') ? '&' : '?') + 'thumb=1';
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

// ── มุมมองอ่านอย่างเดียว + ออกรายงานรายวัน (ไว้แนบเอกสารเบิกจ่าย) ──
const viewing = ref(null);
const viewLoading = ref(false);
async function openView(id) {
  viewLoading.value = true;
  try {
    const { data } = await axios.get(`/api/student/work-logs/${id}`);
    viewing.value = data.data;
  } finally { viewLoading.value = false; }
}
const closeView = () => { viewing.value = null; };
const viewTime = computed(() => {
  const d = viewing.value; if (!d) return '-';
  const s = (d.time_start || '').slice(0, 5), e = (d.time_end || '').slice(0, 5);
  return (s || e) ? `${s || '—'} - ${e || '—'}` : '-';
});
const viewServiceTotal = computed(() => (viewing.value?.entries || []).reduce((a, e) => a + (+e.service_count || 0), 0));
const viewImages = computed(() => (viewing.value?.files || []).filter(f => f.is_image));

const esc = (s) => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

function printReport() {
  const d = viewing.value; if (!d) return;
  const fmt = (n) => Number(n || 0).toLocaleString('th-TH');
  const name = auth.user?.name || '—';
  const date = (d.work_date || '').slice(0, 10);
  const s = (d.time_start || '').slice(0, 5), e = (d.time_end || '').slice(0, 5);
  const time = (s || e) ? `${s || '—'} - ${e || '—'}` : '-';
  const serviceTotal = (d.entries || []).reduce((a, x) => a + (+x.service_count || 0), 0);
  const entryRows = (d.entries || []).map((x, i) =>
    `<tr><td class="c">${i + 1}</td><td class="c">${esc(x.period)}</td><td>${esc(x.activity_type)}</td><td>${esc(x.detail)}</td><td class="r">${fmt(x.service_count)}</td></tr>`
  ).join('') || '<tr><td colspan="5" class="c muted">— ไม่มีกิจกรรม —</td></tr>';
  const caseRows = (d.cases || []).map((c, i) =>
    `<tr><td class="c">${i + 1}</td><td>${esc(c.full_name)}</td><td>${esc(c.phone)}</td><td>${esc(c.village_tambon)}</td><td>${esc(c.problem)}</td></tr>`
  ).join('');
  const photos = (d.files || []).filter(f => f.is_image).map(f => `<img src="/api/files/work/${f.id}">`).join('');
  const html =
`<!doctype html><html lang="th"><head><meta charset="utf-8"><title>รายงานปฏิบัติงาน ${esc(date)}</title>
<style>
@page{size:A4;margin:14mm}
*{font-family:'Sarabun','TH Sarabun New',Tahoma,sans-serif;box-sizing:border-box}
body{color:#1e293b;font-size:14px;margin:0}
h1{font-size:20px;text-align:center;margin:0 0 2px}
.sub{text-align:center;color:#64748b;font-size:13px;margin-bottom:14px}
.meta{width:100%;border-collapse:collapse;margin-bottom:6px}
.meta td{padding:3px 6px;font-size:13px}.meta .k{color:#64748b;width:84px}
h2{font-size:15px;margin:16px 0 6px;border-left:4px solid #2563eb;padding-left:8px}
table.data{width:100%;border-collapse:collapse;font-size:13px}
table.data th,table.data td{border:1px solid #cbd5e1;padding:5px 7px;vertical-align:top}
table.data th{background:#f1f5f9;text-align:left}
.c{text-align:center}.r{text-align:right}.muted{color:#94a3b8}
.sum{display:flex;gap:18px;margin:8px 2px;font-size:13px}
.photos{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.photos img{height:130px;width:auto;border-radius:6px;border:1px solid #e2e8f0;object-fit:cover}
.sign{margin-top:36px;display:flex;justify-content:flex-end}
.sign .box{text-align:center;font-size:13px;line-height:1.9}
</style></head><body>
<h1>รายงานการปฏิบัติงานรายวัน</h1>
<div class="sub">การเข้าร่วมปฏิบัติงานหนุนเสริมการลงทะเบียนบัตรสวัสดิการแห่งรัฐ ปี 2569 จังหวัดนครราชสีมา</div>
<table class="meta">
<tr><td class="k">นักศึกษา</td><td><b>${esc(name)}</b></td><td class="k">วันที่</td><td><b>${esc(thaiDate(d.work_date))}</b></td></tr>
<tr><td class="k">ช่วงเวลา</td><td>${esc(time)}</td><td class="k">หน่วยบริการ</td><td>${esc(auth.user?.work_unit_label || '—')}</td></tr>
</table>
<h2>กิจกรรมที่ดำเนินการ</h2>
<table class="data"><thead><tr><th class="c">#</th><th class="c">ช่วงเวลา</th><th>ประเภทกิจกรรม</th><th>รายละเอียด</th><th class="r">ผู้รับบริการ</th></tr></thead><tbody>${entryRows}</tbody></table>
<div class="sum"><span>ผู้รับบริการรวม <b>${fmt(serviceTotal)}</b></span><span style="color:#16a34a">ลงทะเบียนสำเร็จ <b>${fmt(d.registered_success)}</b></span><span style="color:#dc2626">ลงทะเบียนไม่สำเร็จ <b>${fmt(d.registered_fail)}</b></span></div>
${caseRows ? `<h2>กรณีปัญหารายบุคคล</h2><table class="data"><thead><tr><th class="c">#</th><th>ชื่อ-สกุล</th><th>โทร</th><th>หมู่บ้าน/ตำบล</th><th>ปัญหา</th></tr></thead><tbody>${caseRows}</tbody></table>` : ''}
${photos ? `<h2>ภาพการปฏิบัติงาน</h2><div class="photos">${photos}</div>` : ''}
<div class="sign"><div class="box"><div>ลงชื่อ ...........................................</div><div>( ${esc(d.supervisor_name || '                              ')} )</div><div>${esc(d.supervisor_position || 'ผู้ควบคุมงาน')}</div>${d.supervisor_date ? `<div>วันที่ ${esc(thaiDate(d.supervisor_date))}</div>` : ''}</div></div>
<script>
window.addEventListener('load',function(){
 var imgs=[].slice.call(document.images),pend=imgs.filter(function(im){return !im.complete});
 function go(){setTimeout(function(){window.print()},200)}
 if(!pend.length){go();return}
 var n=0;pend.forEach(function(im){function c(){if(++n>=pend.length)go()}im.addEventListener('load',c);im.addEventListener('error',c)});
 setTimeout(go,2500);
});
<\/script>
</body></html>`;
  const w = window.open('', '_blank');
  if (!w) { alert('เบราว์เซอร์บล็อกป๊อปอัป — กรุณาอนุญาต popup เพื่อพิมพ์/บันทึก PDF'); return; }
  w.document.write(html); w.document.close(); w.focus();
}

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
// มีกิจกรรมที่เกี่ยวกับการช่วยลงทะเบียนหรือไม่ (เช่น อบรมอย่างเดียว = ไม่เกี่ยว)
const REG_KEYWORDS = ['ลงทะเบียน', 'กรอก'];
const hasRegistrationActivity = computed(() =>
  form.entries.some(e => REG_KEYWORDS.some(k => (e.activity_type || '').includes(k)))
);
// เตือน (ไม่บล็อก) เมื่อมีกิจกรรมช่วยลงทะเบียน แต่ตัวเลขไม่ตรงกัน
const totalsWarn = computed(() => hasRegistrationActivity.value && !totalsMatch.value);

async function save() {
  if (!hasLocation.value) {
    errorMsg.value = 'ต้องระบุตำแหน่ง (GPS) ก่อนบันทึก — กดปุ่ม "ระบุตำแหน่ง"';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  // เตือน (ไม่บังคับ) เฉพาะกรณีมีกิจกรรมช่วยลงทะเบียน แต่ตัวเลขไม่ตรง — บันทึกต่อได้
  if (totalsWarn.value) {
    if (!confirm(`ผู้รับบริการรวม (${serviceTotal.value}) ไม่เท่ากับ ลงทะเบียนสำเร็จ+ไม่สำเร็จ (${resultTotal.value})\nหากกิจกรรมเป็นการช่วยลงทะเบียน ตัวเลขควรเท่ากัน\n\nต้องการบันทึกต่อหรือไม่?`)) return;
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
        <div v-for="l in logs" :key="l.id" class="card p-3.5">
          <!-- หัวการ์ด: วันที่ + ป้ายพิกัด + ปุ่ม -->
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <div class="font-semibold leading-tight">{{ thaiDate(l.work_date) }}</div>
              <span v-if="l.lat" class="inline-flex items-center gap-1 mt-1 text-[10px] text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/30 px-1.5 py-0.5 rounded-full"><i class="fi-rr-marker"></i> มีพิกัด</span>
              <span v-else class="inline-flex items-center gap-1 mt-1 text-[10px] text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-1.5 py-0.5 rounded-full"><i class="fi-rr-exclamation"></i> ไม่มีพิกัด</span>
            </div>
            <div class="flex gap-0.5 shrink-0 -mr-1">
              <button @click="openView(l.id)" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800" title="ดู / ออกรายงาน"><i class="fi-rr-eye"></i></button>
              <button @click="openEdit(l.id)" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800" title="แก้ไข"><i class="fi-rr-pencil"></i></button>
              <button @click="remove(l.id)" class="btn-icon hover:bg-red-50 text-red-500" title="ลบ"><i class="fi-rr-trash"></i></button>
            </div>
          </div>
          <!-- สถิติแบบแถวเดียว คั่นด้วย / -->
          <div class="mt-2 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
            <span>ผู้รับบริการ <b class="text-slate-700 dark:text-slate-200">{{ formatNumber(l.service_total || 0) }}</b> คน</span>
            <span class="text-slate-300 dark:text-slate-600">/</span>
            <span>ลงทะเบียน สำเร็จ <b class="text-green-600">{{ formatNumber(l.registered_success) }}</b> คน ไม่สำเร็จ <b class="text-red-500">{{ formatNumber(l.registered_fail) }}</b> คน</span>
            <span class="text-slate-300 dark:text-slate-600">/</span>
            <span>กรณีปัญหาที่พบ <b class="text-slate-700 dark:text-slate-200">{{ formatNumber(l.cases_count) }}</b> คน</span>
            <span class="text-slate-300 dark:text-slate-600">/</span>
            <span>ไฟล์แนบ <b class="text-slate-700 dark:text-slate-200">{{ formatNumber(l.files_count) }}</b> ไฟล์</span>
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
      <div class="card p-4 space-y-3">
        <div v-if="auth.user?.work_unit_label" class="text-xs text-slate-500 flex items-center gap-1.5">
          <i class="fi-rr-building text-blue-600"></i> หน่วยบริการที่ปฏิบัติงาน: <b class="text-slate-700 dark:text-slate-200">{{ auth.user.work_unit_label }}</b>
        </div>
        <div class="grid sm:grid-cols-3 gap-3">
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
        <div :class="['mt-2 text-xs px-3 py-2 rounded-lg', totalsMatch ? 'card-tint-green' : (totalsWarn ? 'card-tint-orange' : 'card-tint-blue')]">
          <i :class="totalsMatch ? 'fi-rr-check-circle' : 'fi-rr-info'"></i>
          ผู้รับบริการรวม (จากกิจกรรม) = <b>{{ serviceTotal }}</b> · ลงทะเบียนสำเร็จ+ไม่สำเร็จ = <b>{{ resultTotal }}</b>
          <span v-if="totalsMatch"> — ตรงกัน ✓</span>
          <span v-else-if="totalsWarn"> — มีกิจกรรมช่วยลงทะเบียน ปกติควรเท่ากัน (ตรวจสอบอีกครั้ง · ยังบันทึกได้)</span>
          <span v-else> — ไม่จำเป็นต้องเท่ากัน เช่น กิจกรรมอบรม (ผู้รับบริการ = ผู้เข้าอบรม)</span>
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
                <img v-if="f.is_image" :src="thumb(fileUrl(f))" loading="lazy" class="w-full h-24 object-cover" :alt="f.original_name">
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

    <!-- มุมมองอ่านอย่างเดียว + ออกรายงานรายวัน -->
    <div v-if="viewing" class="fixed inset-0 z-50 bg-black/50 flex items-start sm:items-center justify-center sm:p-4 overflow-y-auto" @click.self="closeView">
      <div class="bg-white dark:bg-slate-900 w-full sm:max-w-3xl sm:rounded-2xl shadow-xl sm:my-6">
        <div class="flex items-center justify-between gap-2 p-4 border-b border-slate-100 dark:border-slate-800 sticky top-0 bg-white dark:bg-slate-900 z-10">
          <div class="min-w-0">
            <h2 class="font-semibold truncate"><i class="fi-rr-document text-blue-600"></i> รายงานปฏิบัติงาน · {{ thaiDate(viewing.work_date) }}</h2>
            <div class="text-xs text-slate-500 truncate">{{ auth.user?.name }}</div>
          </div>
          <div class="flex gap-2 shrink-0">
            <button @click="printReport" class="btn-primary text-sm"><i class="fi-rr-print"></i> พิมพ์ / บันทึก PDF</button>
            <button @click="closeView" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800" title="ปิด"><i class="fi-rr-cross-small"></i></button>
          </div>
        </div>

        <div class="p-4 space-y-4 text-sm">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <div><span class="text-slate-400">ช่วงเวลา</span> {{ viewTime }}</div>
            <div class="flex items-center gap-2">
              <span><span class="text-slate-400">หน่วยบริการ</span> {{ auth.user?.work_unit_label || '—' }}</span>
              <a v-if="viewing.lat" :href="`https://www.google.com/maps?q=${viewing.lat},${viewing.lng}`" target="_blank" rel="noopener" class="text-[11px] text-blue-700 underline shrink-0"><i class="fi-rr-marker"></i> แผนที่</a>
            </div>
          </div>

          <div>
            <h3 class="font-medium mb-1"><i class="fi-rr-list text-blue-600"></i> กิจกรรมที่ดำเนินการ</h3>
            <div class="overflow-x-auto rounded-lg border border-slate-100 dark:border-slate-800">
              <table class="w-full text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500">
                  <tr><th class="text-left p-2">ช่วงเวลา</th><th class="text-left p-2">ประเภท</th><th class="text-left p-2">รายละเอียด</th><th class="text-right p-2">ผู้รับบริการ</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60">
                  <tr v-for="(e, i) in viewing.entries" :key="i" class="align-top">
                    <td class="p-2 whitespace-nowrap">{{ e.period }}</td>
                    <td class="p-2">{{ e.activity_type }}</td>
                    <td class="p-2">{{ e.detail }}</td>
                    <td class="p-2 text-right">{{ formatNumber(e.service_count || 0) }}</td>
                  </tr>
                  <tr v-if="!viewing.entries?.length"><td colspan="4" class="p-4 text-center text-slate-400">— ไม่มีกิจกรรม —</td></tr>
                </tbody>
              </table>
            </div>
            <div class="flex flex-wrap gap-4 mt-2 text-xs">
              <span>ผู้รับบริการรวม <b>{{ formatNumber(viewServiceTotal) }}</b></span>
              <span class="text-green-600">ลงทะเบียนสำเร็จ <b>{{ formatNumber(viewing.registered_success) }}</b></span>
              <span class="text-red-500">ลงทะเบียนไม่สำเร็จ <b>{{ formatNumber(viewing.registered_fail) }}</b></span>
            </div>
          </div>

          <div v-if="viewing.cases?.length">
            <h3 class="font-medium mb-1"><i class="fi-rr-user text-blue-600"></i> กรณีปัญหารายบุคคล</h3>
            <div v-for="(c, i) in viewing.cases" :key="i" class="border border-slate-100 dark:border-slate-800 rounded-lg p-2 mb-1">
              <div class="font-medium">{{ c.full_name }} <span v-if="c.phone" class="text-slate-400 text-xs font-normal">· {{ c.phone }}</span></div>
              <div v-if="c.village_tambon" class="text-xs text-slate-500">{{ c.village_tambon }}</div>
              <div class="text-xs mt-0.5">{{ c.problem }}</div>
            </div>
          </div>

          <div v-if="viewImages.length">
            <h3 class="font-medium mb-1"><i class="fi-rr-picture text-blue-600"></i> ภาพการปฏิบัติงาน</h3>
            <div class="flex flex-wrap gap-2">
              <a v-for="f in viewImages" :key="f.id" :href="fileUrl(f)" target="_blank" rel="noopener">
                <img :src="fileUrl(f)" loading="lazy" class="h-24 w-auto rounded-lg border border-slate-200 dark:border-slate-700 object-cover" :alt="f.original_name">
              </a>
            </div>
          </div>

          <div v-if="viewing.supervisor_name" class="text-xs text-slate-500 pt-1">
            ผู้ควบคุมงาน: <b class="text-slate-700 dark:text-slate-200">{{ viewing.supervisor_name }}</b>
            <span v-if="viewing.supervisor_position"> · {{ viewing.supervisor_position }}</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { formatNumber } from '@/composables/useApi';

const logs = ref([]);
const loading = ref(false);
const saving = ref(false);
const showForm = ref(false);
const editingId = ref(null);
const errorMsg = ref('');
const flashOk = ref('');

const PERIODS = ['เช้า', 'บ่าย', 'เพิ่มเติม'];
const ACTIVITY_SUGGESTIONS = [
  'ให้คำแนะนำการลงทะเบียน', 'ช่วยกรอกข้อมูล', 'ตรวจสอบเอกสาร', 'คัดกรองกลุ่มตกหล่น',
];

const blankForm = () => ({
  work_date: new Date().toISOString().slice(0, 10),
  time_start: '', time_end: '',
  registered_success: 0, registered_fail: 0,
  supervisor_name: '', supervisor_position: '', supervisor_date: '',
  entries: [{ period: 'เช้า', activity_type: '', detail: '', service_count: 0 }],
  cases: [],
});
const form = reactive(blankForm());

function resetForm() {
  Object.assign(form, blankForm());
  editingId.value = null;
}

async function load() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/student/work-logs');
    logs.value = data.data;
  } finally {
    loading.value = false;
  }
}
onMounted(load);

function openCreate() {
  resetForm();
  showForm.value = true;
  flashOk.value = '';
}

async function openEdit(id) {
  errorMsg.value = '';
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
  editingId.value = id;
  showForm.value = true;
  flashOk.value = '';
}

const addEntry = () => form.entries.push({ period: 'บ่าย', activity_type: '', detail: '', service_count: 0 });
const removeEntry = (i) => form.entries.splice(i, 1);
const addCase = () => form.cases.push({ full_name: '', phone: '', village_tambon: '', problem: '' });
const removeCase = (i) => form.cases.splice(i, 1);

async function save() {
  saving.value = true;
  errorMsg.value = '';
  // ตัด entry/case ที่ว่างเปล่าออก
  const payload = {
    ...form,
    time_start: form.time_start || null,
    time_end: form.time_end || null,
    supervisor_date: form.supervisor_date || null,
    entries: form.entries.filter(e => e.activity_type?.trim()),
    cases: form.cases.filter(c => c.full_name?.trim() && c.problem?.trim()),
  };
  try {
    if (editingId.value) {
      await axios.patch(`/api/student/work-logs/${editingId.value}`, payload);
    } else {
      await axios.post('/api/student/work-logs', payload);
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
      <div class="flex items-center justify-between mb-4">
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
          </div>
          <div class="flex gap-4 text-sm flex-1 flex-wrap">
            <div><span class="text-slate-400">ผู้รับบริการ</span> <b>{{ formatNumber(l.service_total || 0) }}</b></div>
            <div class="text-green-600"><span class="text-slate-400">สำเร็จ</span> <b>{{ formatNumber(l.registered_success) }}</b></div>
            <div class="text-red-500"><span class="text-slate-400">ไม่สำเร็จ</span> <b>{{ formatNumber(l.registered_fail) }}</b></div>
            <div><span class="text-slate-400">กิจกรรม</span> <b>{{ l.entries_count }}</b></div>
            <div><span class="text-slate-400">กรณีปัญหา</span> <b>{{ l.cases_count }}</b></div>
          </div>
          <div class="flex gap-2">
            <button @click="openEdit(l.id)" class="btn-icon hover:bg-slate-100 dark:hover:bg-slate-800" title="แก้ไข"><i class="fi-rr-pencil"></i></button>
            <button @click="remove(l.id)" class="btn-icon hover:bg-red-50 text-red-500" title="ลบ"><i class="fi-rr-trash"></i></button>
          </div>
        </div>
      </div>
    </div>

    <!-- ฟอร์มเพิ่ม/แก้ไข -->
    <div v-else class="space-y-4 max-w-3xl">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">{{ editingId ? 'แก้ไขบันทึก' : 'เพิ่มบันทึกการปฏิบัติงาน' }}</h2>
        <button @click="showForm = false" class="text-sm text-slate-500 hover:text-slate-700"><i class="fi-rr-cross-small"></i> ปิด</button>
      </div>
      <div v-if="errorMsg" class="card-tint-red p-3 text-sm"><i class="fi-rr-cross-circle"></i> {{ errorMsg }}</div>

      <!-- ส่วนหัว -->
      <div class="card p-4 grid sm:grid-cols-3 gap-3">
        <label class="block">
          <span class="text-xs text-slate-500">วันที่ปฏิบัติงาน *</span>
          <input v-model="form.work_date" type="date" class="w-full mt-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
        <label class="block">
          <span class="text-xs text-slate-500">เวลาเริ่ม</span>
          <input v-model="form.time_start" type="time" class="w-full mt-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
        <label class="block">
          <span class="text-xs text-slate-500">เวลาสิ้นสุด</span>
          <input v-model="form.time_end" type="time" class="w-full mt-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
      </div>

      <!-- กิจกรรม -->
      <div class="card p-4">
        <div class="flex items-center justify-between mb-2">
          <h3 class="font-medium text-sm"><i class="fi-rr-list"></i> กิจกรรมที่ดำเนินการ</h3>
          <button @click="addEntry" class="text-sm text-blue-700"><i class="fi-rr-plus"></i> เพิ่มกิจกรรม</button>
        </div>
        <datalist id="activity-suggestions">
          <option v-for="a in ACTIVITY_SUGGESTIONS" :key="a" :value="a" />
        </datalist>
        <div v-for="(e, i) in form.entries" :key="i" class="grid sm:grid-cols-12 gap-2 mb-2 items-start p-2 sm:p-0 rounded-xl bg-slate-50 sm:bg-transparent dark:bg-slate-800/40 sm:dark:bg-transparent">
          <select v-model="e.period" class="sm:col-span-2 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
            <option v-for="p in PERIODS" :key="p" :value="p">{{ p }}</option>
          </select>
          <input v-model="e.activity_type" list="activity-suggestions" placeholder="ประเภทกิจกรรม" class="sm:col-span-3 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <input v-model="e.detail" placeholder="รายละเอียด" class="sm:col-span-4 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <input v-model.number="e.service_count" type="number" min="0" placeholder="จำนวน" class="sm:col-span-2 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <button @click="removeEntry(i)" class="sm:col-span-1 btn-icon text-red-400 hover:bg-red-50" title="ลบ"><i class="fi-rr-cross-small"></i></button>
        </div>
      </div>

      <!-- ผลการดำเนินงาน -->
      <div class="card p-4 grid sm:grid-cols-2 gap-3">
        <label class="block">
          <span class="text-xs text-slate-500">ลงทะเบียนสำเร็จ (ราย)</span>
          <input v-model.number="form.registered_success" type="number" min="0" class="w-full mt-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
        <label class="block">
          <span class="text-xs text-slate-500">ลงทะเบียนไม่สำเร็จ (ราย)</span>
          <input v-model.number="form.registered_fail" type="number" min="0" class="w-full mt-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
      </div>

      <!-- ปัญหารายกรณี -->
      <div class="card p-4">
        <div class="flex items-center justify-between mb-2">
          <h3 class="font-medium text-sm"><i class="fi-rr-exclamation"></i> ปัญหาการลงทะเบียนรายกรณี <span class="text-slate-400 font-normal">(ถ้ามี)</span></h3>
          <button @click="addCase" class="text-sm text-blue-700"><i class="fi-rr-plus"></i> เพิ่มกรณี</button>
        </div>
        <div v-if="!form.cases.length" class="text-xs text-slate-400">— ยังไม่มี —</div>
        <div v-for="(c, i) in form.cases" :key="i" class="grid sm:grid-cols-12 gap-2 mb-2 items-start p-2 sm:p-0 rounded-xl bg-slate-50 sm:bg-transparent dark:bg-slate-800/40 sm:dark:bg-transparent">
          <input v-model="c.full_name" placeholder="ชื่อ-สกุล" class="sm:col-span-3 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <input v-model="c.phone" placeholder="เบอร์โทร" class="sm:col-span-2 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <input v-model="c.village_tambon" placeholder="หมู่บ้าน/ตำบล" class="sm:col-span-3 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <input v-model="c.problem" placeholder="ปัญหาที่เกิดขึ้น" class="sm:col-span-3 px-2 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
          <button @click="removeCase(i)" class="sm:col-span-1 btn-icon text-red-400 hover:bg-red-50" title="ลบ"><i class="fi-rr-cross-small"></i></button>
        </div>
      </div>

      <!-- ผู้ควบคุมงาน -->
      <div class="card p-4 grid sm:grid-cols-3 gap-3">
        <label class="block">
          <span class="text-xs text-slate-500">ผู้ควบคุมงาน</span>
          <input v-model="form.supervisor_name" placeholder="ชื่อ-สกุล" class="w-full mt-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
        <label class="block">
          <span class="text-xs text-slate-500">ตำแหน่ง</span>
          <input v-model="form.supervisor_position" class="w-full mt-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
        <label class="block">
          <span class="text-xs text-slate-500">วันที่</span>
          <input v-model="form.supervisor_date" type="date" class="w-full mt-1 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
        </label>
      </div>

      <div class="flex gap-2">
        <button @click="save" :disabled="saving" class="btn-primary disabled:opacity-60">
          <i v-if="saving" class="fi-rr-spinner animate-spin"></i> {{ editingId ? 'บันทึกการแก้ไข' : 'บันทึก' }}
        </button>
        <button @click="showForm = false" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-sm">ยกเลิก</button>
      </div>
    </div>
  </AppLayout>
</template>

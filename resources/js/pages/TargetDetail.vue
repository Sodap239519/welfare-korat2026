<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, reactive, onMounted, watch } from 'vue';
import axios from 'axios';
import { useRoute, useRouter } from 'vue-router';
import { formatNumber, shortDate, statusColorClass, statusShort } from '@/composables/useApi';

const route = useRoute();
const router = useRouter();
const id = Number(route.params.id);

const target = ref(null);
const statuses = ref([]);
const channels = ref([]);
const banks = ref([]);
const saving = ref(false);
const errors = ref({});
const flashOk = ref('');

const form = reactive({
  status_code: '',
  channel_id: '',
  sub_channel: '',
  note: '',
});

const currentStatusObj = () => statuses.value.find(s => s.code === form.status_code);
const selectedChannel = () => channels.value.find(c => c.id === Number(form.channel_id));
const needsBank = () => selectedChannel()?.code === 'bank';

async function load() {
  const [t, s, c, b] = await Promise.all([
    axios.get(`/api/targets/${id}`),
    axios.get('/api/ref/statuses'),
    axios.get('/api/ref/channels'),
    axios.get('/api/ref/banks'),
  ]);
  target.value = t.data;
  statuses.value = s.data.data;
  channels.value = c.data.data;
  banks.value = b.data.data;
  form.status_code = t.data.current?.status_code || '';
  form.channel_id = t.data.current?.channel_id || '';
  form.sub_channel = t.data.current?.sub_channel || '';
  form.note = t.data.current?.note || '';
}

onMounted(load);

async function submit() {
  errors.value = {};
  flashOk.value = '';
  saving.value = true;
  try {
    const payload = {
      status_code: form.status_code,
      channel_id: form.channel_id || null,
      sub_channel: needsBank() ? (form.sub_channel || null) : null,
      note: form.note || null,
    };
    const { data } = await axios.patch(`/api/targets/${id}/status`, payload);
    target.value = data;
    flashOk.value = 'บันทึกสถานะเรียบร้อย';
    setTimeout(() => (flashOk.value = ''), 3000);
  } catch (e) {
    errors.value = e.response?.data?.errors || {};
    if (!Object.keys(errors.value).length) {
      errors.value.general = [e.response?.data?.message || 'เกิดข้อผิดพลาด'];
    }
  } finally {
    saving.value = false;
  }
}

function initials(name) {
  return (name || 'U').trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').replace(/[^ก-๙A-Za-z]/g, '');
}
</script>

<template>
  <AppLayout :title="target?.name || 'รายละเอียดบุคคล'" subtitle="รายละเอียด + อัปเดตสถานะ">
    <div v-if="target" class="space-y-4">

      <RouterLink to="/targets" class="inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400 hover:text-blue-700">
        <i class="fi-rr-arrow-left"></i> รายชื่อเป้าหมาย
      </RouterLink>

      <!-- Hero -->
      <div class="card-hero p-5 lg:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="flex gap-4 min-w-0">
            <div class="w-16 h-16 shrink-0 rounded-2xl bg-white/15 backdrop-blur flex items-center justify-center text-xl font-semibold">
              {{ initials(target.name) }}
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h1 class="text-xl font-semibold">{{ target.name }}</h1>
                <span class="text-xs px-2 py-0.5 rounded-full bg-white/15 backdrop-blur">สมาชิกบ้านลำดับ {{ target.member_seq }}</span>
              </div>
              <div class="text-sm opacity-90 mt-1.5 flex items-start gap-1.5">
                <i class="fi-rr-marker mt-0.5"></i>
                <span>{{ target.address_no }} {{ target.village }} ต.{{ target.tambon }} อ.{{ target.amphur }} จ.นครราชสีมา</span>
              </div>
              <div class="flex flex-wrap items-center gap-3 mt-2 text-xs opacity-90">
                <span v-if="target.year"><i class="fi-rr-calendar"></i> ปี {{ target.year }}</span>
                <span><i class="fi-rr-coins"></i> {{ formatNumber(target.annual_income) }} บ./ปี</span>
                <span v-if="target.poverty_level"><i class="fi-rr-home"></i> {{ target.poverty_level }}</span>
                <span v-if="target.has_old_welfare"><i class="fi-rr-credit-card"></i> มีบัตรเดิม</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-4">

        <!-- LEFT: Current + Update -->
        <div class="lg:col-span-2 space-y-4">

          <!-- Current -->
          <div class="card p-5">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
              <div>
                <div class="text-xs text-slate-500 dark:text-slate-400">สถานะปัจจุบัน</div>
                <div class="flex items-center gap-2 mt-1.5">
                  <span v-if="target.current?.status_code" :class="['inline-block px-3 py-1.5 rounded-xl text-sm font-medium', statusColorClass(target.current.status_code)]">
                    <i class="fi-sr-check-circle"></i> {{ statuses.find(s => s.code === target.current.status_code)?.label || statusShort(target.current.status_code) }}
                  </span>
                  <span v-else class="inline-block px-3 py-1.5 rounded-xl text-sm font-medium bg-slate-100 text-slate-600">ยังไม่อัปเดตสถานะ</span>
                </div>
              </div>
              <div v-if="target.current" class="text-right text-xs text-slate-500 dark:text-slate-400">
                อัปเดตล่าสุด<br>
                <span class="text-slate-800 dark:text-slate-100 font-medium">{{ shortDate(target.current.updated_at) }}</span>
                <div v-if="target.current.updated_by">โดย {{ target.current.updated_by }}</div>
              </div>
            </div>
            <div v-if="target.current?.note || target.current?.channel" class="card-tint-blue text-xs p-3">
              <span v-if="target.current.note"><i class="fi-rr-info"></i> {{ target.current.note }}</span>
              <div v-if="target.current.channel" class="mt-1">
                <i class="fi-rr-route"></i> ช่องทาง: <strong>{{ target.current.channel }}</strong>
                <span v-if="target.current.sub_channel_label"> ({{ target.current.sub_channel_label }})</span>
              </div>
            </div>
          </div>

          <!-- Update -->
          <div class="card p-5">
            <div class="font-semibold mb-3">อัปเดตสถานะ</div>

            <div v-if="flashOk" class="card-tint-green p-3 text-sm mb-3"><i class="fi-rr-check-circle"></i> {{ flashOk }}</div>
            <div v-if="errors.general" class="card-tint-red p-3 text-sm mb-3"><i class="fi-rr-cross-circle"></i> {{ errors.general[0] }}</div>

            <form @submit.prevent="submit" class="space-y-4">
              <div>
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">สถานะใหม่ <span class="text-red-600">*</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <label v-for="s in statuses" :key="s.code"
                         :class="['flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer hover:bg-blue-50/30 dark:hover:bg-slate-800/50',
                                  form.status_code === s.code ? 'border-2 border-blue-500 card-tint-blue' : 'border-slate-100 dark:border-slate-800']">
                    <input type="radio" v-model="form.status_code" :value="s.code" class="text-blue-600">
                    <span :class="['text-sm px-2 py-0.5 rounded whitespace-nowrap', s.color]">{{ statusShort(s.code) }}</span>
                  </label>
                </div>
                <div v-if="errors.status_code" class="text-[11px] text-red-600 mt-1">{{ errors.status_code[0] }}</div>
              </div>

              <div v-if="currentStatusObj()?.requires_channel || form.channel_id">
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">
                  ช่องทาง <span v-if="currentStatusObj()?.requires_channel" class="text-red-600">*</span>
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                  <label v-for="c in channels" :key="c.id"
                         :class="['flex flex-col items-center gap-1.5 p-3 rounded-xl border cursor-pointer hover:bg-blue-50/30 dark:hover:bg-slate-800/50',
                                  Number(form.channel_id) === c.id ? 'border-2 border-sky-500 card-tint-sky' : 'border-slate-100 dark:border-slate-800']">
                    <input type="radio" v-model.number="form.channel_id" :value="c.id" class="hidden">
                    <i :class="[c.icon || 'fi-rr-circle', 'text-lg text-sky-700']"></i>
                    <span class="text-xs text-center">{{ c.name }}</span>
                  </label>
                </div>
                <div v-if="errors.channel_id" class="text-[11px] text-red-600 mt-1">{{ errors.channel_id[0] }}</div>
              </div>

              <!-- Sub-channel: เลือกธนาคาร เมื่อช่องทาง = ธนาคาร -->
              <div v-if="needsBank()" class="card-tint-blue p-3 rounded-xl">
                <label class="block text-xs font-medium mb-2">
                  <i class="fi-rr-bank"></i> เลือกธนาคารที่ใช้ลงทะเบียน <span class="text-red-600">*</span>
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <label v-for="b in banks" :key="b.code"
                         :class="['flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer hover:bg-white/60 dark:hover:bg-slate-800/50',
                                  form.sub_channel === b.code ? 'border-2 border-blue-600 bg-white dark:bg-slate-800' : 'border-blue-200 dark:border-slate-700 bg-white/40']">
                    <input type="radio" v-model="form.sub_channel" :value="b.code" class="text-blue-600">
                    <span class="text-sm font-medium">{{ b.name }}</span>
                  </label>
                </div>
                <div v-if="errors.sub_channel" class="text-[11px] text-red-600 mt-2">{{ errors.sub_channel[0] }}</div>
              </div>

              <div>
                <label class="block text-xs text-slate-600 dark:text-slate-400 mb-1.5">
                  หมายเหตุ
                  <span v-if="currentStatusObj()?.requires_note" class="text-red-600">*</span>
                  <span v-else class="text-slate-400">(ไม่บังคับ)</span>
                </label>
                <textarea v-model="form.note" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติม..."
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                <div v-if="errors.note" class="text-[11px] text-red-600 mt-1">{{ errors.note[0] }}</div>
              </div>

              <div class="flex gap-2 justify-end pt-1">
                <RouterLink to="/targets" class="btn-outline px-4 py-2.5 text-sm">ยกเลิก</RouterLink>
                <button type="submit" :disabled="saving || !form.status_code" class="btn-primary px-4 py-2.5 text-sm flex items-center gap-1.5 disabled:opacity-50">
                  <i :class="['fi-rr-disk', saving && 'animate-spin']"></i> {{ saving ? 'กำลังบันทึก…' : 'บันทึก' }}
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- RIGHT: Tracker + Timeline -->
        <div class="space-y-4">

          <div class="card-tint-sky p-5">
            <div class="text-xs opacity-80 mb-2">ผู้กำกับติดตามหมู่บ้าน</div>
            <div v-if="target.tracker" class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-full bg-sky-600 text-white flex items-center justify-center font-semibold">
                {{ initials(target.tracker.name) }}
              </div>
              <div class="min-w-0">
                <div class="font-medium truncate">{{ target.tracker.name }}</div>
                <div class="text-xs opacity-80">{{ target.tracker.position }}</div>
                <a v-if="target.tracker.phone" :href="`tel:${target.tracker.phone}`" class="text-xs text-sky-800 dark:text-sky-300 font-medium hover:underline mt-1 inline-flex items-center gap-1">
                  <i class="fi-rr-phone-call"></i> {{ target.tracker.phone }}
                </a>
              </div>
            </div>
            <div v-else class="text-sm text-slate-500">ยังไม่มีผู้กำกับติดตามที่หมู่บ้านนี้</div>
          </div>

          <div class="card p-5">
            <div class="font-semibold mb-3 text-sm">ประวัติการเปลี่ยนสถานะ</div>
            <div v-if="target.logs.length === 0" class="text-sm text-slate-500">ยังไม่มีประวัติ</div>
            <div v-else class="space-y-3 text-sm">
              <div v-for="(l, i) in target.logs" :key="l.id" class="flex gap-3">
                <div class="shrink-0 w-2.5 h-2.5 mt-1.5 rounded-full bg-blue-600 ring-4 ring-blue-100 dark:ring-blue-900/30"></div>
                <div :class="['flex-1', i < target.logs.length - 1 ? 'pb-3 border-b border-slate-100 dark:border-slate-800' : '']">
                  <div class="flex items-baseline justify-between gap-2">
                    <span class="font-medium">
                      เปลี่ยนเป็น <span :class="[statusColorClass(l.status_code), 'px-1.5 rounded text-xs whitespace-nowrap']">{{ statusShort(l.status_code) }}</span>
                    </span>
                    <span class="text-xs text-slate-500">{{ shortDate(l.changed_at) }}</span>
                  </div>
                  <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    <span v-if="l.user">โดย {{ l.user }}</span>
                    <span v-if="l.channel"> · {{ l.channel }}<span v-if="l.sub_channel_label"> ({{ l.sub_channel_label }})</span></span>
                  </div>
                  <div v-if="l.note" class="text-xs text-slate-600 dark:text-slate-300 mt-1">{{ l.note }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="card p-8 text-center">
      <i class="fi-rr-spinner animate-spin text-2xl text-slate-400"></i>
      <div class="text-sm text-slate-500 mt-2">กำลังโหลด…</div>
    </div>
  </AppLayout>
</template>

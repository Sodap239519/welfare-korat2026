<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import Modal from '@/components/Modal.vue';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useRoute, useRouter } from 'vue-router';
import { formatNumber, shortDate } from '@/composables/useApi';
import { useAuthStore } from '@/stores/auth';

const route  = useRoute();
const router = useRouter();
const auth   = useAuthStore();

const id = computed(() => Number(route.params.id));
const batch = ref(null);
const loading = ref(true);
const acting = ref('');             // 'submit' | 'receive' | 'record' | 'reject' | 'delete'
const flash = ref('');
const flashErr = ref('');

// Reject modal
const showRejectModal = ref(false);
const rejectReason = ref('');
const rejectErr = ref('');

const statusMeta = {
  draft:     { label: 'ร่าง',         tint: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200', icon: 'fi-rr-document' },
  submitted: { label: 'ส่งแล้ว · รอธนาคาร', tint: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200', icon: 'fi-rr-paper-plane' },
  received:  { label: 'ธ.รับเอกสารแล้ว',   tint: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200', icon: 'fi-rr-inbox-in' },
  recorded:  { label: 'ธ.บันทึกครบ',       tint: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200', icon: 'fi-rr-check-double' },
  rejected:  { label: 'ปฏิเสธ',            tint: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300', icon: 'fi-rr-cross-circle' },
};

const submitterRoleLabels = {
  self: 'ลงทะเบียนด้วยตนเอง', kamnan: 'กำนัน', phuyaiban: 'ผู้ใหญ่บ้าน',
  osm: 'อสม.', opt: 'อปท.', other: 'อื่นๆ',
};

// Permission ↔ status flags
const isOwner = computed(() => batch.value?.tracker_user_id === auth.user?.id);
const isAdmin = computed(() => auth.roles.includes('super_admin') || auth.roles.includes('admin'));
// bank_staff รับ action ได้ถ้า batch ตรงธนาคารตัวเอง
const isMyBank = computed(() => auth.roles.includes('bank_staff')
  && batch.value?.channel_id === auth.user?.bank_channel_id
  && batch.value?.sub_channel === auth.user?.bank_sub_channel);
const canBankAction = computed(() => isAdmin.value || isMyBank.value);

const canEditDraft = computed(() => batch.value?.status === 'draft' && (isOwner.value || auth.roles.includes('super_admin')));
const canSubmit    = computed(() => canEditDraft.value && (batch.value?.targets?.length || 0) > 0);
const canReceive   = computed(() => batch.value?.status === 'submitted' && canBankAction.value);
const canRecord    = computed(() => batch.value?.status === 'received'  && canBankAction.value);
const canReject    = computed(() => ['submitted', 'received'].includes(batch.value?.status) && canBankAction.value);
const canDelete    = computed(() => canEditDraft.value);

async function load() {
  loading.value = true;
  try {
    const { data } = await axios.get(`/api/batches/${id.value}`);
    batch.value = data.data;
  } catch (e) {
    flashErr.value = e.response?.data?.message || 'โหลด batch ไม่สำเร็จ';
  } finally {
    loading.value = false;
  }
}
onMounted(load);

async function callAction(action, payload = null) {
  acting.value = action;
  flash.value = ''; flashErr.value = '';
  try {
    const { data } = await axios.post(`/api/batches/${id.value}/${action}`, payload || {});
    flash.value = data.message || 'ทำเรียบร้อย';
    if (data.data) batch.value = { ...batch.value, ...data.data };
    await load();
  } catch (e) {
    flashErr.value = e.response?.data?.message || 'ทำไม่สำเร็จ';
  } finally {
    acting.value = '';
    setTimeout(() => (flash.value = ''), 4000);
  }
}

async function removeTarget(tid) {
  if (!confirm('เอารายนี้ออกจาก batch ?')) return;
  try {
    await axios.delete(`/api/batches/${id.value}/targets/${tid}`);
    flash.value = 'เอาออกแล้ว';
    await load();
    setTimeout(() => (flash.value = ''), 3000);
  } catch (e) {
    flashErr.value = e.response?.data?.message || 'ลบไม่สำเร็จ';
  }
}

async function deleteBatch() {
  if (!confirm(`ลบ batch #${batch.value.batch_no} ?\nรายชื่อในนี้จะกลับสู่สถานะเดิม`)) return;
  try {
    await axios.delete(`/api/batches/${id.value}`);
    router.push({ name: 'batches' });
  } catch (e) {
    flashErr.value = e.response?.data?.message || 'ลบไม่สำเร็จ';
  }
}

function openReject() {
  rejectReason.value = '';
  rejectErr.value = '';
  showRejectModal.value = true;
}
async function submitReject() {
  if (rejectReason.value.trim().length < 5) {
    rejectErr.value = 'ระบุเหตุผลอย่างน้อย 5 ตัวอักษร';
    return;
  }
  await callAction('reject', { reject_reason: rejectReason.value.trim() });
  showRejectModal.value = false;
}
</script>

<template>
  <AppLayout :greeting="batch ? `Batch #${batch.batch_no}` : 'กำลังโหลด...'" title="📦 รายละเอียดห่อเอกสาร">
    <div class="space-y-4">
      <button @click="router.push({ name: 'batches' })" class="text-sm text-blue-700 dark:text-blue-300 hover:underline flex items-center gap-1">
        <i class="fi-rr-arrow-small-left"></i> กลับไปหน้ารายการ
      </button>

      <!-- Loading -->
      <div v-if="loading" class="text-center py-20 text-slate-500">
        <i class="fi-rr-spinner animate-spin text-3xl"></i>
      </div>

      <template v-else-if="batch">
        <!-- Flash -->
        <div v-if="flash" class="card-tint-green p-3 text-sm flex items-center gap-2">
          <i class="fi-rr-check-circle text-green-700 dark:text-green-300"></i> {{ flash }}
        </div>
        <div v-if="flashErr" class="card-tint-red p-3 text-sm flex items-center gap-2">
          <i class="fi-rr-triangle-warning text-red-700 dark:text-red-300"></i> {{ flashErr }}
        </div>

        <!-- Header card -->
        <div class="card p-5">
          <div class="flex items-start gap-3 mb-3">
            <div :class="['w-12 h-12 shrink-0 rounded-xl flex items-center justify-center text-2xl', statusMeta[batch.status]?.tint]">
              <i :class="statusMeta[batch.status]?.icon"></i>
            </div>
            <div class="flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <div class="font-mono font-bold">#{{ batch.batch_no }}</div>
                <span :class="['text-xs px-2 py-1 rounded-full font-medium', statusMeta[batch.status]?.tint]">
                  {{ statusMeta[batch.status]?.label }}
                </span>
              </div>
              <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ batch.targets?.length || 0 }} ราย · วันที่ {{ shortDate(batch.batch_date) }}
              </div>
            </div>
          </div>

          <div class="grid sm:grid-cols-2 gap-3 text-sm mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
            <div>
              <div class="text-xs text-slate-500 dark:text-slate-400">ผู้ส่ง (Tracker)</div>
              <div class="font-medium">{{ batch.tracker?.name || '—' }}</div>
            </div>
            <div>
              <div class="text-xs text-slate-500 dark:text-slate-400">ตำแหน่งผู้ส่ง</div>
              <div class="font-medium">
                {{ submitterRoleLabels[batch.submitter_role] || '—' }}
                <span v-if="batch.submitter_name" class="text-slate-500"> · {{ batch.submitter_name }}</span>
              </div>
            </div>
            <div>
              <div class="text-xs text-slate-500 dark:text-slate-400">ปลายทาง</div>
              <div class="font-medium">{{ batch.channel?.name || '—' }} {{ batch.sub_channel ? `(${batch.sub_channel.toUpperCase()})` : '' }}</div>
            </div>
            <div v-if="batch.submitted_at">
              <div class="text-xs text-slate-500 dark:text-slate-400">ส่งเมื่อ</div>
              <div class="font-medium">{{ shortDate(batch.submitted_at) }}</div>
            </div>
            <div v-if="batch.received_at">
              <div class="text-xs text-slate-500 dark:text-slate-400">ธนาคารรับเมื่อ</div>
              <div class="font-medium">{{ shortDate(batch.received_at) }} · {{ batch.received_by?.name }}</div>
            </div>
            <div v-if="batch.recorded_at">
              <div class="text-xs text-slate-500 dark:text-slate-400">บันทึกเมื่อ</div>
              <div class="font-medium">{{ shortDate(batch.recorded_at) }} · {{ batch.recorded_by?.name }}</div>
            </div>
          </div>

          <div v-if="batch.notes" class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-sm">
            <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">หมายเหตุ</div>
            <div class="whitespace-pre-wrap">{{ batch.notes }}</div>
          </div>

          <div v-if="batch.reject_reason" class="mt-3 pt-3 border-t border-red-100 dark:border-red-900/40 text-sm card-tint-red p-3 rounded-xl">
            <div class="text-xs text-red-700 dark:text-red-300 mb-1 font-semibold">เหตุผลที่ปฏิเสธ</div>
            <div class="whitespace-pre-wrap">{{ batch.reject_reason }}</div>
          </div>
        </div>

        <!-- Action buttons · DESKTOP — แสดงในกรอบ card -->
        <div class="card p-4 hidden lg:flex flex-wrap gap-2">
          <button v-if="canSubmit" @click="callAction('submit')" :disabled="acting"
                  class="btn-primary px-4 py-2.5 text-sm flex items-center gap-2 bg-amber-500 hover:bg-amber-600 min-h-[44px]">
            <i :class="['fi-rr-paper-plane', acting === 'submit' && 'animate-spin']"></i>
            ส่งให้ธนาคาร ({{ batch.targets?.length || 0 }} ราย)
          </button>
          <button v-if="canReceive" @click="callAction('receive')" :disabled="acting"
                  class="btn-primary px-4 py-2.5 text-sm flex items-center gap-2 bg-sky-600 hover:bg-sky-700 min-h-[44px]">
            <i :class="['fi-rr-inbox-in', acting === 'receive' && 'animate-spin']"></i>
            ยืนยันรับเอกสาร
          </button>
          <button v-if="canRecord" @click="callAction('record')" :disabled="acting"
                  class="btn-primary px-4 py-2.5 text-sm flex items-center gap-2 bg-green-600 hover:bg-green-700 min-h-[44px]">
            <i :class="['fi-rr-check-double', acting === 'record' && 'animate-spin']"></i>
            ยืนยันบันทึกครบ
          </button>
          <button v-if="canReject" @click="openReject" :disabled="acting"
                  class="btn-outline px-4 py-2.5 text-sm flex items-center gap-2 text-red-700 dark:text-red-300 border-red-200 dark:border-red-800 hover:bg-red-50 dark:hover:bg-red-900/20 min-h-[44px]">
            <i class="fi-rr-cross-circle"></i> ปฏิเสธ
          </button>
          <button v-if="canDelete" @click="deleteBatch" :disabled="acting"
                  class="btn-outline px-4 py-2.5 text-sm flex items-center gap-2 ml-auto text-red-700 dark:text-red-300 min-h-[44px]">
            <i class="fi-rr-trash"></i> ลบ draft
          </button>
        </div>

        <!-- Action buttons · MOBILE — sticky bottom เหนือ BottomNav (กดง่ายด้วยนิ้วโป้ง) -->
        <div v-if="canSubmit || canReceive || canRecord || canReject || canDelete"
             class="lg:hidden fixed left-0 right-0 z-30 px-3"
             style="bottom: calc(env(safe-area-inset-bottom, 0px) + 4.5rem);">
          <div class="card p-2.5 shadow-2xl shadow-slate-900/20 border-2 border-slate-100 dark:border-slate-700 flex flex-wrap gap-2">
            <button v-if="canSubmit" @click="callAction('submit')" :disabled="acting"
                    class="btn-primary px-3 py-3 text-sm flex-1 min-w-[140px] flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 active:bg-amber-700 min-h-[48px]">
              <i :class="['fi-rr-paper-plane', acting === 'submit' && 'animate-spin']"></i>
              ส่งให้ธนาคาร
            </button>
            <button v-if="canReceive" @click="callAction('receive')" :disabled="acting"
                    class="btn-primary px-3 py-3 text-sm flex-1 min-w-[140px] flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 min-h-[48px]">
              <i :class="['fi-rr-inbox-in', acting === 'receive' && 'animate-spin']"></i>
              ยืนยันรับเอกสาร
            </button>
            <button v-if="canRecord" @click="callAction('record')" :disabled="acting"
                    class="btn-primary px-3 py-3 text-sm flex-1 min-w-[140px] flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 active:bg-green-800 min-h-[48px]">
              <i :class="['fi-rr-check-double', acting === 'record' && 'animate-spin']"></i>
              ยืนยันบันทึกครบ
            </button>
            <button v-if="canReject" @click="openReject" :disabled="acting"
                    class="btn-outline px-3 py-3 text-sm flex items-center justify-center gap-1.5 text-red-700 dark:text-red-300 border-red-200 dark:border-red-800 min-h-[48px]">
              <i class="fi-rr-cross-circle"></i> ปฏิเสธ
            </button>
            <button v-if="canDelete" @click="deleteBatch" :disabled="acting"
                    class="btn-outline px-3 py-3 text-sm flex items-center justify-center gap-1.5 text-red-700 dark:text-red-300 min-h-[48px]" title="ลบ draft">
              <i class="fi-rr-trash"></i>
            </button>
          </div>
        </div>
        <!-- Spacer ใต้ list — เผื่อ floating bar ทับ -->
        <div v-if="canSubmit || canReceive || canRecord || canReject || canDelete" class="lg:hidden h-20"></div>

        <!-- Targets list -->
        <div class="card overflow-hidden">
          <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div class="font-semibold text-sm">รายชื่อใน batch ({{ batch.targets?.length || 0 }})</div>
            <RouterLink v-if="canEditDraft" :to="{ name: 'targets' }"
                        class="text-xs text-blue-700 dark:text-blue-300 hover:underline flex items-center gap-1">
              <i class="fi-rr-plus-small"></i> เพิ่มรายชื่อจากหน้า Targets
            </RouterLink>
          </div>
          <div v-if="!batch.targets || batch.targets.length === 0" class="p-8 text-center text-sm text-slate-500">
            ยังไม่มีรายชื่อใน batch · กลับไปที่หน้า Targets เลือกรายแล้วใส่ห่อ
          </div>
          <ul v-else class="divide-y divide-slate-100 dark:divide-slate-800">
            <li v-for="t in batch.targets" :key="t.id"
                class="p-3 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-800/30">
              <div class="w-9 h-9 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 text-sm">
                <i class="fi-rr-user"></i>
              </div>
              <div class="flex-1 min-w-0">
                <RouterLink :to="{ name: 'target-detail', params: { id: t.id } }"
                            class="font-medium text-sm hover:text-blue-700 truncate block">
                  {{ [t.prefix, t.first_name, t.last_name].filter(Boolean).join(' ') }}
                </RouterLink>
                <div class="text-xs text-slate-500 dark:text-slate-400 truncate">
                  {{ t.village?.name || '—' }}{{ t.village?.moo ? ` ม.${t.village.moo}` : '' }}
                  · {{ t.tambon?.name || '—' }} · {{ t.amphur?.name || '—' }}
                </div>
              </div>
              <button v-if="canEditDraft" @click="removeTarget(t.id)"
                      class="btn-icon hover:bg-red-50 dark:hover:bg-red-900/30 text-red-600 dark:text-red-400"
                      title="เอาออกจาก batch">
                <i class="fi-rr-cross-small"></i>
              </button>
            </li>
          </ul>
        </div>
      </template>
    </div>

    <!-- Reject modal -->
    <Modal :show="showRejectModal" max-width="max-w-md" @close="showRejectModal = false">
      <form @submit.prevent="submitReject" class="space-y-3">
        <div class="flex items-start gap-2 mb-2">
          <i class="fi-rr-cross-circle text-2xl text-red-600"></i>
          <div>
            <div class="font-semibold">ปฏิเสธ batch #{{ batch?.batch_no }}</div>
            <div class="text-xs text-slate-500 mt-0.5">เหตุผลจะแจ้งกลับให้ tracker · รายชื่อจะกลับสถานะเดิม</div>
          </div>
        </div>
        <div>
          <label class="text-xs text-slate-500 mb-1 block">เหตุผล (อย่างน้อย 5 ตัวอักษร)</label>
          <textarea v-model="rejectReason" rows="3"
                    placeholder="เช่น เอกสารไม่ครบ ขาดสำเนาบัตร ปชช. ของ 3 ราย"
                    class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"></textarea>
          <div v-if="rejectErr" class="text-xs text-red-600 mt-1">{{ rejectErr }}</div>
        </div>
        <div class="flex gap-2 justify-end pt-2">
          <button type="button" @click="showRejectModal = false" class="btn-outline px-4 py-2.5 text-sm">ยกเลิก</button>
          <button type="submit" :disabled="acting === 'reject'" class="btn-primary bg-red-600 hover:bg-red-700 px-4 py-2.5 text-sm flex items-center gap-2">
            <i :class="['fi-rr-cross-circle', acting === 'reject' && 'animate-spin']"></i> ยืนยันปฏิเสธ
          </button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { formatNumber } from '@/composables/useApi';

const props = defineProps({
  current: { type: Number, required: true },
  last:    { type: Number, required: true },
  total:   { type: Number, default: 0 },
  unit:    { type: String, default: 'รายการ' },
});
const emit = defineEmits(['go']);

// Build page list: [1, '...', 5, 6, 7, '...', 31]
const pages = computed(() => {
  const cur = props.current;
  const last = props.last;
  const result = [];

  // small total — show all
  if (last <= 7) {
    for (let i = 1; i <= last; i++) result.push(i);
    return result;
  }

  // always show first
  result.push(1);

  // left ellipsis
  if (cur > 4) result.push('...');

  // window around current
  const start = Math.max(2, cur - 1);
  const end   = Math.min(last - 1, cur + 1);
  for (let i = start; i <= end; i++) result.push(i);

  // right ellipsis
  if (cur < last - 3) result.push('...');

  // always show last
  result.push(last);
  return result;
});

function go(n) {
  if (n === '...' || n === props.current || n < 1 || n > props.last) return;
  emit('go', n);
}
</script>

<template>
  <div v-if="last > 0" class="flex flex-wrap items-center justify-between gap-2 text-sm">
    <div class="text-xs text-slate-500 dark:text-slate-400">
      หน้า {{ current }} / {{ last }}
      <span v-if="total > 0"> · {{ formatNumber(total) }} {{ unit }}</span>
    </div>
    <div class="flex items-center gap-1 flex-wrap">
      <button
        @click="go(current - 1)"
        :disabled="current <= 1"
        class="min-w-[40px] h-10 px-3 rounded-lg hover:bg-blue-50 dark:hover:bg-slate-800 disabled:opacity-40 tap-transparent flex items-center justify-center"
        title="ก่อนหน้า">
        <i class="fi-rr-angle-left"></i>
      </button>

      <template v-for="(p, i) in pages" :key="`${p}-${i}`">
        <span v-if="p === '...'" class="px-2 text-slate-400 select-none">…</span>
        <button
          v-else
          @click="go(p)"
          :class="[
            'min-w-[40px] h-10 px-3 rounded-lg text-sm font-medium transition tap-transparent flex items-center justify-center',
            p === current
              ? 'bg-blue-700 text-white shadow-sm shadow-blue-500/30'
              : 'hover:bg-blue-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300'
          ]">
          {{ p }}
        </button>
      </template>

      <button
        @click="go(current + 1)"
        :disabled="current >= last"
        class="min-w-[40px] h-10 px-3 rounded-lg hover:bg-blue-50 dark:hover:bg-slate-800 disabled:opacity-40 tap-transparent flex items-center justify-center"
        title="ถัดไป">
        <i class="fi-rr-angle-right"></i>
      </button>
    </div>
  </div>
</template>

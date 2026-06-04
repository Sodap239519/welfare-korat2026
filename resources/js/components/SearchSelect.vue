<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] },     // [{ value, label }]
  placeholder: { type: String, default: '— เลือก —' },
});
const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const search = ref('');
const rootEl = ref(null);

const selectedLabel = computed(() => {
  const o = props.options.find(o => String(o.value) === String(props.modelValue));
  return o ? o.label : '';
});
const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return props.options;
  return props.options.filter(o => String(o.label).toLowerCase().includes(q));
});

function pick(o) {
  emit('update:modelValue', o.value);
  open.value = false;
  search.value = '';
}
function toggle() {
  open.value = !open.value;
  if (open.value) search.value = '';
}
function onClickOutside(e) {
  if (rootEl.value && !rootEl.value.contains(e.target)) open.value = false;
}
watch(open, (v) => {
  if (v) document.addEventListener('click', onClickOutside);
  else document.removeEventListener('click', onClickOutside);
});
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
  <div ref="rootEl" class="relative">
    <button type="button" @click="toggle"
            class="w-full px-2.5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-left flex items-center justify-between gap-2">
      <span :class="['truncate', selectedLabel ? '' : 'text-slate-400']">{{ selectedLabel || placeholder }}</span>
      <i :class="['fi-rr-angle-small-down text-slate-400 shrink-0 transition-transform', open && 'rotate-180']"></i>
    </button>

    <div v-if="open" class="absolute z-[60] mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xl overflow-hidden">
      <div class="p-1.5 border-b border-slate-100 dark:border-slate-800">
        <div class="relative">
          <i class="fi-rr-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
          <input v-model="search" type="text" placeholder="ค้นหา..." @click.stop
                 class="w-full pl-7 pr-2 py-1.5 text-sm rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 outline-none focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="max-h-48 overflow-y-auto py-1">
        <button v-for="o in filtered" :key="o.value" type="button" @click="pick(o)"
                :class="['w-full text-left px-3 py-2 text-sm hover:bg-blue-50 dark:hover:bg-slate-800 flex items-center gap-2',
                         String(o.value) === String(modelValue) ? 'text-blue-700 dark:text-blue-300 font-medium bg-blue-50/50 dark:bg-slate-800/50' : 'text-slate-700 dark:text-slate-200']">
          <i v-if="String(o.value) === String(modelValue)" class="fi-rr-check text-xs"></i>
          <span :class="String(o.value) === String(modelValue) ? '' : 'pl-5'">{{ o.label }}</span>
        </button>
        <div v-if="filtered.length === 0" class="px-3 py-4 text-xs text-slate-400 text-center">ไม่พบรายการ</div>
      </div>
    </div>
  </div>
</template>

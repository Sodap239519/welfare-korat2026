<script setup>
defineProps({
  label: { type: String, default: 'กำลังโหลด...' },
  size: { type: Number, default: 52 },
  py: { type: String, default: 'py-16' },
});
</script>

<template>
  <div :class="['flex flex-col items-center justify-center gap-3', py]">
    <!-- gradient dual-ring spinner -->
    <div class="loader-wrap" :style="{ width: size + 'px', height: size + 'px' }">
      <div class="loader-ring"></div>
      <div class="loader-ring loader-ring--inner"></div>
      <i class="fi-rr-box-alt loader-core"></i>
    </div>
    <div v-if="label" class="text-sm text-slate-500 dark:text-slate-400 loader-label">{{ label }}</div>
  </div>
</template>

<style scoped>
.loader-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.loader-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: conic-gradient(from 0deg, transparent 8%, #38bdf8 45%, #2563eb 100%);
  -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 5px), #000 calc(100% - 4px));
  mask: radial-gradient(farthest-side, transparent calc(100% - 5px), #000 calc(100% - 4px));
  animation: loader-spin 0.85s cubic-bezier(0.5, 0.15, 0.5, 0.85) infinite;
}
.loader-ring--inner {
  inset: 7px;
  background: conic-gradient(from 180deg, transparent 10%, #93c5fd 60%, #2563eb 100%);
  animation-duration: 1.25s;
  animation-direction: reverse;
}
.loader-core {
  font-size: 0.85rem;
  color: #2563eb;
  animation: loader-pulse 1.1s ease-in-out infinite;
}
.loader-label {
  animation: loader-pulse 1.4s ease-in-out infinite;
}
@keyframes loader-spin { to { transform: rotate(360deg); } }
@keyframes loader-pulse {
  0%, 100% { opacity: 0.45; transform: scale(0.92); }
  50% { opacity: 1; transform: scale(1); }
}
:global(.dark) .loader-core { color: #60a5fa; }
</style>

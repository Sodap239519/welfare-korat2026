<script setup>
import { watch } from 'vue';

const props = defineProps({
  show: { type: Boolean, required: true },
  maxWidth: { type: String, default: 'max-w-md' },
  closable: { type: Boolean, default: true },
});
defineEmits(['close']);

// lock body scroll while modal is open
watch(() => props.show, (val) => {
  if (typeof document === 'undefined') return;
  document.body.style.overflow = val ? 'hidden' : '';
});
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0">
      <div v-if="show"
           class="fixed inset-0 z-[1000] overflow-y-auto bg-slate-900/50 backdrop-blur-[1px]"
           @click.self="closable && $emit('close')">
        <div class="min-h-full flex items-end sm:items-center justify-center p-0 sm:p-4">
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
            enter-to-class="translate-y-0 opacity-100 sm:scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="translate-y-0 opacity-100 sm:scale-100"
            leave-to-class="translate-y-4 opacity-0 sm:scale-95">
            <div v-if="show"
                 :class="['card w-full p-5 rounded-t-3xl sm:rounded-3xl max-h-[95vh] overflow-y-auto', maxWidth]"
                 @click.stop>
              <slot />
            </div>
          </Transition>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

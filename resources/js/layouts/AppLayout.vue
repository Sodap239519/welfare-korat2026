<script setup>
import { ref } from 'vue';
import Sidebar from '@/components/Sidebar.vue';
import TopBar from '@/components/TopBar.vue';
import BottomNav from '@/components/BottomNav.vue';

defineProps({ title: String, subtitle: String, greeting: String });
const sidebarOpen = ref(false);
</script>

<template>
  <div class="min-h-screen overflow-x-hidden">
    <!-- Sidebar — fixed บนทุก breakpoint (ล็อกตำแหน่ง ไม่เลื่อนตาม scroll) -->
    <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />

    <!-- Content wrapper:
         lg:pl-60  → padding-left 240px (เผื่อพื้นที่ sidebar) แทน ml-60 + w-full
                     (เพราะ ml-60 + w-full ทำให้ overflow ขวา 240px → avatar ถูก clip)
         min-w-0 + min-h-screen — ให้ flex shrink ได้ + ความสูงเต็ม
    -->
    <div class="lg:pl-60 min-w-0 min-h-screen">
      <TopBar :title="title" :subtitle="subtitle" :greeting="greeting" @open-sidebar="sidebarOpen = true" />
      <main class="p-4 lg:p-6 max-w-7xl mx-auto pb-24 lg:pb-6">
        <slot />
      </main>
    </div>
  </div>
  <BottomNav />
</template>

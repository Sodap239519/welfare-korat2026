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
    <!-- Sidebar — fixed บน desktop (lg) เพื่อล็อกตำแหน่ง ไม่เลื่อนตาม scroll -->
    <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />

    <!-- Content wrapper — เผื่อ margin ขวาของ sidebar บน desktop -->
    <div class="lg:ml-60 min-w-0 w-full max-w-full">
      <!-- TopBar — sticky top บนทุก device (มี z-index สูงกว่า content) -->
      <TopBar :title="title" :subtitle="subtitle" :greeting="greeting" @open-sidebar="sidebarOpen = true" />
      <main class="p-4 lg:p-6 max-w-7xl mx-auto pb-24 lg:pb-6 min-w-0 w-full">
        <slot />
      </main>
    </div>
  </div>
  <BottomNav />
</template>

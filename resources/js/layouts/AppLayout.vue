<script setup>
import { ref } from 'vue';
import Sidebar from '@/components/Sidebar.vue';
import TopBar from '@/components/TopBar.vue';
import BottomNav from '@/components/BottomNav.vue';

defineProps({ title: String, subtitle: String, greeting: String });
const sidebarOpen = ref(false);
</script>

<template>
  <div class="lg:flex min-h-screen">
    <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />
    <div class="flex-1 min-w-0">
      <TopBar :title="title" :subtitle="subtitle" :greeting="greeting" @open-sidebar="sidebarOpen = true" />
      <main class="p-4 lg:p-6 max-w-7xl mx-auto pb-24 lg:pb-6">
        <slot />
      </main>
    </div>
  </div>
  <BottomNav />
</template>

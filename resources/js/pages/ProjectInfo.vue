<script setup>
import { useAuthStore } from '@/stores/auth';
import AppLayout from '@/layouts/AppLayout.vue';
import PublicNav from '@/components/PublicNav.vue';
import ProjectInfoContent from '@/components/ProjectInfoContent.vue';

const auth = useAuthStore();
</script>

<template>
  <!-- ผู้ใช้ที่ login แล้ว — แสดงในแอป (มี sidebar/topbar) -->
  <AppLayout v-if="auth.isAuth" title="ข้อมูลโครงการ" subtitle="บัตรสวัสดิการแห่งรัฐ ปี 2569">
    <ProjectInfoContent :embedded="true" />
  </AppLayout>

  <!-- ผู้ใช้ทั่วไป (ยังไม่ login) — แสดงแบบ public -->
  <div v-else class="min-h-screen bg-slate-50 dark:bg-slate-950">
    <PublicNav />
    <ProjectInfoContent />
    <footer class="text-center text-xs text-slate-400 py-6 leading-relaxed">
      © 2569 โครงการการขับเคลื่อนพื้นที่วิจัยเชิงยุทธศาสตร์เพื่อขจัดความยากจน
      และสร้างโอกาสทางสังคมแบบบูรณาการ จังหวัดนครราชสีมา<br>
      ศูนย์ศึกษาและพัฒนาโคราช มหาวิทยาลัยราชภัฏนครราชสีมา
    </footer>
  </div>
</template>

import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

// เจ้าหน้าที่ (ไม่รวมนักศึกษา) — ใช้จำกัดหน้าปฏิบัติงานเดิมไม่ให้นักศึกษาเห็น
const STAFF = ['super_admin', 'admin', 'tracker', 'bank_staff'];

/** หน้าแรกหลัง login ตาม role — นักศึกษาเข้าโมดูลตัวเอง, ที่เหลือเข้า dashboard */
export function homeFor(auth) {
  return auth.roles.includes('student') ? { name: 'student-worklog' } : { name: 'dashboard' };
}

const routes = [
  // หน้าแรก public — ติดตามงานนักศึกษาได้โดยไม่ต้อง login
  { path: '/',        name: 'home',          component: () => import('@/pages/PublicHome.vue'),  meta: { public: true } },
  { path: '/project-info', name: 'project-info', component: () => import('@/pages/ProjectInfo.vue'), meta: { public: true } },
  { path: '/student-tracking', name: 'student-tracking', component: () => import('@/pages/StudentTracking.vue'), meta: { public: true } },
  { path: '/login',   name: 'login',         component: () => import('@/pages/Login.vue'),       meta: { guest: true } },

  { path: '/dashboard',     name: 'dashboard',     component: () => import('@/pages/Dashboard.vue'),     meta: { auth: true, roles: STAFF } },
  { path: '/missed-targets', name: 'missed-targets', component: () => import('@/pages/MissedTargets.vue'), meta: { auth: true, roles: STAFF } },
  { path: '/overview',      redirect: '/dashboard' },   // รวมเป็นหน้าเดียวแล้ว
  { path: '/targets',       name: 'targets',       component: () => import('@/pages/Targets.vue'),       meta: { auth: true, roles: STAFF } },
  { path: '/targets/:id',   name: 'target-detail', component: () => import('@/pages/TargetDetail.vue'),  meta: { auth: true, roles: STAFF } },
  { path: '/batches',       name: 'batches',       component: () => import('@/pages/Batches.vue'),       meta: { auth: true, roles: STAFF } },
  { path: '/batches/:id',   name: 'batch-detail',  component: () => import('@/pages/BatchDetail.vue'),   meta: { auth: true, roles: STAFF } },
  { path: '/map',           name: 'map',           component: () => import('@/pages/Map.vue'),           meta: { auth: true, roles: STAFF } },
  { path: '/trackers',      name: 'trackers',      component: () => import('@/pages/Trackers.vue'),      meta: { auth: true, roles: STAFF } },
  { path: '/import',        name: 'import',        component: () => import('@/pages/Import.vue'),        meta: { auth: true, roles: ['super_admin'] } },
  { path: '/reports',       name: 'reports',       component: () => import('@/pages/Reports.vue'),       meta: { auth: true, roles: STAFF } },

  // ── โมดูลนักศึกษา ──
  { path: '/student/work-log',   name: 'student-worklog',    component: () => import('@/pages/student/WorkLog.vue'),        meta: { auth: true, roles: ['student'] } },
  { path: '/student/assessment', name: 'student-assessment', component: () => import('@/pages/student/SelfAssessment.vue'), meta: { auth: true, roles: ['student'] } },
  { path: '/student/my-dashboard', name: 'student-mydash',   component: () => import('@/pages/student/MyDashboard.vue'),    meta: { auth: true, roles: ['student'] } },
  { path: '/student/documents',  name: 'student-documents',  component: () => import('@/pages/student/Documents.vue'),      meta: { auth: true, roles: ['student', 'super_admin', 'admin'] } },
  // รายงานภาพรวมนักศึกษา (อาจารย์/ผู้บริหารในระบบ)
  { path: '/student-report',     name: 'student-report',     component: () => import('@/pages/StudentDashboard.vue'),       meta: { auth: true, roles: ['super_admin', 'admin'] } },

  { path: '/admin/users',    name: 'admin-users',    component: () => import('@/pages/AdminUsers.vue'),    meta: { auth: true, roles: ['super_admin'] } },
  { path: '/admin/activity', name: 'admin-activity', component: () => import('@/pages/AdminActivity.vue'), meta: { auth: true, roles: ['super_admin'] } },
  { path: '/admin/settings', name: 'admin-settings', component: () => import('@/pages/AdminSettings.vue'), meta: { auth: true, roles: ['super_admin'] } },
  { path: '/notifications',  name: 'notifications',  component: () => import('@/pages/Notifications.vue'),  meta: { auth: true } }, // ซ่อนจาก sidebar — เข้าจากไอคอนแจ้งเตือน

  { path: '/:pathMatch(.*)*', component: () => import('@/pages/NotFound.vue') },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() { return { top: 0 }; },
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!auth.isReady) await auth.fetchMe();

  // หน้า public — เข้าได้ทุกกรณี (ทั้งก่อน/หลัง login)
  if (to.meta.public) return true;

  if (to.meta.auth && !auth.isAuth) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }
  if (to.meta.guest && auth.isAuth) {
    return homeFor(auth);
  }
  if (to.meta.roles && !to.meta.roles.some(r => auth.roles.includes(r))) {
    return homeFor(auth);
  }
});

export default router;

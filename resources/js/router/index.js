import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/login', name: 'login', component: () => import('@/pages/Login.vue'), meta: { guest: true } },

  { path: '/dashboard',     name: 'dashboard',     component: () => import('@/pages/Dashboard.vue'),     meta: { auth: true } },
  { path: '/overview',      name: 'overview',      component: () => import('@/pages/Overview.vue'),      meta: { auth: true } },
  { path: '/targets',       name: 'targets',       component: () => import('@/pages/Targets.vue'),       meta: { auth: true } },
  { path: '/targets/:id',   name: 'target-detail', component: () => import('@/pages/TargetDetail.vue'),  meta: { auth: true } },
  { path: '/trackers',      name: 'trackers',      component: () => import('@/pages/Trackers.vue'),      meta: { auth: true } },
  { path: '/import',        name: 'import',        component: () => import('@/pages/Import.vue'),        meta: { auth: true } },
  { path: '/reports',       name: 'reports',       component: () => import('@/pages/Reports.vue'),       meta: { auth: true } },
  { path: '/admin/users',    name: 'admin-users',    component: () => import('@/pages/AdminUsers.vue'),    meta: { auth: true, roles: ['super_admin'] } },
  { path: '/admin/activity', name: 'admin-activity', component: () => import('@/pages/AdminActivity.vue'), meta: { auth: true, roles: ['super_admin'] } },

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

  if (to.meta.auth && !auth.isAuth) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }
  if (to.meta.guest && auth.isAuth) {
    return { name: 'dashboard' };
  }
  if (to.meta.roles && !to.meta.roles.some(r => auth.roles.includes(r))) {
    return { name: 'dashboard' };
  }
});

export default router;

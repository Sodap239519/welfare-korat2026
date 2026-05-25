import { createRouter, createWebHistory } from 'vue-router';

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/login', name: 'login', component: () => import('@/pages/Login.vue'), meta: { layout: 'guest' } },
  { path: '/dashboard', name: 'dashboard', component: () => import('@/pages/Dashboard.vue') },
  { path: '/overview', name: 'overview', component: () => import('@/pages/Overview.vue') },
  { path: '/targets', name: 'targets', component: () => import('@/pages/Targets.vue') },
  { path: '/targets/:id', name: 'target-detail', component: () => import('@/pages/TargetDetail.vue') },
  { path: '/trackers', name: 'trackers', component: () => import('@/pages/Trackers.vue') },
  { path: '/import', name: 'import', component: () => import('@/pages/Import.vue') },
  { path: '/reports', name: 'reports', component: () => import('@/pages/Reports.vue') },
  { path: '/admin/users', name: 'admin-users', component: () => import('@/pages/AdminUsers.vue') },
  { path: '/admin/activity', name: 'admin-activity', component: () => import('@/pages/AdminActivity.vue') },
  { path: '/:pathMatch(.*)*', component: () => import('@/pages/NotFound.vue') },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() { return { top: 0 }; },
});

export default router;

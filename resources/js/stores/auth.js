import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import axios from 'axios';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const isReady = ref(false); // true after first fetchMe()
  const loading = ref(false);
  const error = ref(null);

  const isAuth = computed(() => !!user.value);
  const roles = computed(() => user.value?.roles ?? []);
  const isSuperAdmin = computed(() => roles.value.includes('super_admin'));
  const isAdmin      = computed(() => roles.value.includes('admin') || isSuperAdmin.value);
  const isTracker    = computed(() => roles.value.includes('tracker') || isAdmin.value);
  const isStudent    = computed(() => roles.value.includes('student'));

  async function csrf() {
    await axios.get('/sanctum/csrf-cookie');
  }

  async function fetchMe() {
    try {
      const { data } = await axios.get('/api/auth/me');
      user.value = data.user;
    } catch (e) {
      user.value = null;
    } finally {
      isReady.value = true;
    }
    return user.value;
  }

  async function login(phone, password, remember = false) {
    loading.value = true;
    error.value = null;
    try {
      await csrf();
      const { data } = await axios.post('/api/auth/login', { phone, password, remember });
      user.value = data.user;
      return user.value;
    } catch (e) {
      error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function register(payload) {
    loading.value = true;
    error.value = null;
    try {
      await csrf();
      const { data } = await axios.post('/api/auth/register', payload);
      return data;
    } catch (e) {
      error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function logout() {
    try {
      await axios.post('/api/auth/logout');
    } catch (e) { /* ignore */ }
    user.value = null;
  }

  async function updateProfile(payload) {
    loading.value = true;
    error.value = null;
    try {
      const { data } = await axios.patch('/api/auth/profile', payload);
      user.value = data.user;
      return data;
    } catch (e) {
      error.value = e.response?.data?.message || 'เกิดข้อผิดพลาด';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  return {
    user, isReady, loading, error,
    isAuth, roles, isSuperAdmin, isAdmin, isTracker, isStudent,
    fetchMe, login, register, logout, updateProfile,
  };
});

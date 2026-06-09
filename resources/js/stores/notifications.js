import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useNotificationStore = defineStore('notifications', () => {
  const items = ref([]);
  const unread = ref(0);
  const loading = ref(false);
  const lastFetched = ref(null);

  const hasUnread = computed(() => unread.value > 0);

  async function loadUnreadCount() {
    try {
      const { data } = await axios.get('/api/notifications/unread-count');
      unread.value = data.count;
    } catch (e) { /* silent */ }
  }

  async function loadList() {
    loading.value = true;
    try {
      const { data } = await axios.get('/api/notifications', { params: { per_page: 15 } });
      items.value = data.data;
      lastFetched.value = new Date();
      // update unread count
      unread.value = items.value.filter(n => !n.read_at).length;
    } catch (e) { /* silent */ }
    finally { loading.value = false; }
  }

  async function markRead(id) {
    try {
      await axios.post(`/api/notifications/${id}/read`);
      const n = items.value.find(x => x.id === id);
      if (n && !n.read_at) {
        n.read_at = new Date().toISOString();
        unread.value = Math.max(0, unread.value - 1);
      }
    } catch (e) { /* silent */ }
  }

  async function markAllRead() {
    try {
      await axios.post('/api/notifications/read-all');
      items.value.forEach(n => { n.read_at = n.read_at || new Date().toISOString(); });
      unread.value = 0;
    } catch (e) { /* silent */ }
  }

  // auto-refresh: ทุก 20 วินาที + เมื่อกลับมาที่แท็บ (ไม่ต้องกด F5)
  let poll = null;
  let visHandler = null;
  function tick() {
    loadUnreadCount();
    if (items.value.length) loadList(); // ถ้าเคยเปิดลิสต์แล้ว รีเฟรชรายการด้วย
  }
  function startPolling() {
    if (poll) return;
    poll = setInterval(tick, 60_000);
    visHandler = () => { if (document.visibilityState === 'visible') tick(); };
    document.addEventListener('visibilitychange', visHandler);
  }
  function stopPolling() {
    if (poll) { clearInterval(poll); poll = null; }
    if (visHandler) { document.removeEventListener('visibilitychange', visHandler); visHandler = null; }
  }

  return {
    items, unread, loading, hasUnread,
    loadUnreadCount, loadList, markRead, markAllRead,
    startPolling, stopPolling,
  };
});

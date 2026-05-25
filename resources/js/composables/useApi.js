import axios from 'axios';

export function useApi() {
  return axios;
}

// Simple lookup cache (in-memory per page load)
const cache = new Map();
export async function lookup(path) {
  if (cache.has(path)) return cache.get(path);
  const p = axios.get(path).then(r => r.data?.data ?? r.data);
  cache.set(path, p);
  return p;
}

export function statusColorClass(code) {
  return code ? `st-${code.replace('.', '-')}` : 'st-4-1';
}

export function formatNumber(n) {
  return new Intl.NumberFormat('th-TH').format(n ?? 0);
}

export function shortDate(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit' }) +
         ' ' + d.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
}

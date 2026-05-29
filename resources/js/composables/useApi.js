import axios from 'axios';
import { reactive } from 'vue';

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

// ─────────────────────────────────────────────────────────────
// ชื่อสถานะ dynamic — อ่านจาก /api/ref/statuses
// ผู้ใช้แก้ชื่อใน AdminSettings → ทุกหน้าที่ใช้ statusShort() จะอัปเดตตามทันที
// (เพราะ STATUS_SHORT เป็น reactive — Vue track การเข้าถึงในแม่แบบ)
// ─────────────────────────────────────────────────────────────
export const STATUS_SHORT = reactive({});

let _statusLoadPromise = null;

/** โหลด statuses จาก API แล้ว populate STATUS_SHORT — idempotent + cache */
export async function loadStatuses(force = false) {
  if (!force && _statusLoadPromise) return _statusLoadPromise;
  _statusLoadPromise = axios.get('/api/ref/statuses').then(r => {
    const list = r.data?.data ?? r.data ?? [];
    // เคลียร์ key เก่าก่อน (เผื่อโดนลบใน admin)
    Object.keys(STATUS_SHORT).forEach(k => { delete STATUS_SHORT[k]; });
    list.forEach(s => { STATUS_SHORT[s.code] = s.label; });
    return STATUS_SHORT;
  }).catch(err => {
    console.warn('[useApi] loadStatuses failed:', err);
    _statusLoadPromise = null;  // ให้ลองใหม่ครั้งหน้าได้
    return STATUS_SHORT;
  });
  return _statusLoadPromise;
}

/** แปลง code "4.1" → label สั้น (อ่านจาก DB) — auto-trigger load ครั้งแรก */
export function statusShort(code) {
  if (!_statusLoadPromise) loadStatuses();   // lazy-load ครั้งแรกที่ถูกเรียก
  return STATUS_SHORT[code] || code || '—';
}

/** Invalidate cache — เรียกหลังจาก admin แก้ statuses ใน Settings */
export function invalidateStatuses() {
  _statusLoadPromise = null;
  return loadStatuses(true);
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

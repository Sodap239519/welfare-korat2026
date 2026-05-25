import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useThemeStore = defineStore('theme', () => {
  const isDark = ref(false);
  const fontSize = ref(16);

  function init() {
    const savedTheme = localStorage.getItem('wk_theme') || 'light';
    isDark.value = savedTheme === 'dark';
    if (isDark.value) document.documentElement.classList.add('dark');

    const savedFz = parseFloat(localStorage.getItem('wk_fz')) || 16;
    fontSize.value = savedFz;
    document.documentElement.style.setProperty('--fz', savedFz + 'px');
  }

  function toggle() {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
    localStorage.setItem('wk_theme', isDark.value ? 'dark' : 'light');
  }

  function bigger() {
    fontSize.value = Math.min(fontSize.value + 1, 22);
    document.documentElement.style.setProperty('--fz', fontSize.value + 'px');
    localStorage.setItem('wk_fz', fontSize.value);
  }

  function smaller() {
    fontSize.value = Math.max(fontSize.value - 1, 12);
    document.documentElement.style.setProperty('--fz', fontSize.value + 'px');
    localStorage.setItem('wk_fz', fontSize.value);
  }

  function reset() {
    fontSize.value = 16;
    document.documentElement.style.setProperty('--fz', '16px');
    localStorage.setItem('wk_fz', 16);
  }

  return { isDark, fontSize, init, toggle, bigger, smaller, reset };
});

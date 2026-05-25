// welfare-korat2026 mockup shared JS
// Theme toggle, font size, sidebar (desktop), bottom nav (mobile)
(function () {
  const root = document.documentElement;

  const savedTheme = localStorage.getItem('wk_theme') || 'light';
  if (savedTheme === 'dark') root.classList.add('dark');

  const savedFz = parseFloat(localStorage.getItem('wk_fz')) || 16;
  document.documentElement.style.setProperty('--fz', savedFz + 'px');

  window.wkTheme = {
    toggle() {
      root.classList.toggle('dark');
      localStorage.setItem('wk_theme', root.classList.contains('dark') ? 'dark' : 'light');
      window.dispatchEvent(new Event('wk-theme-changed'));
    },
    bigger() {
      const cur = parseFloat(getComputedStyle(root).fontSize);
      const next = Math.min(cur + 1, 22);
      document.documentElement.style.setProperty('--fz', next + 'px');
      localStorage.setItem('wk_fz', next);
    },
    smaller() {
      const cur = parseFloat(getComputedStyle(root).fontSize);
      const next = Math.max(cur - 1, 12);
      document.documentElement.style.setProperty('--fz', next + 'px');
      localStorage.setItem('wk_fz', next);
    },
    reset() {
      document.documentElement.style.setProperty('--fz', '16px');
      localStorage.setItem('wk_fz', 16);
    }
  };

  window.wkSidebar = {
    open() {
      const s = document.getElementById('wk-sidebar');
      const b = document.getElementById('wk-backdrop');
      if (s) s.classList.remove('-translate-x-full');
      if (b) b.classList.remove('hidden');
    },
    close() {
      const s = document.getElementById('wk-sidebar');
      const b = document.getElementById('wk-backdrop');
      if (s) s.classList.add('-translate-x-full');
      if (b) b.classList.add('hidden');
    }
  };
})();

window.wkLayout = {
  render({ active = '', title = '', subtitle = '', greeting = '' } = {}) {
    const items = [
      { key: 'dashboard',       icon: 'fi-rr-apps',            label: 'Dashboard',         href: 'dashboard.html',       bottom: true  },
      { key: 'overview',        icon: 'fi-rr-flag-alt',        label: 'ภาพรวมโครงการ',     href: 'overview.html',        bottom: false },
      { key: 'targets',         icon: 'fi-rr-users-alt',       label: 'รายชื่อเป้าหมาย',   href: 'targets.html',         bottom: true  },
      { key: 'trackers',        icon: 'fi-rr-user-headset',    label: 'ผู้กำกับติดตาม',   href: 'trackers.html',        bottom: false },
      { key: 'import',          icon: 'fi-rr-cloud-upload-alt',label: 'นำเข้าข้อมูล',     href: 'import.html',          bottom: true, bottomLabel: 'นำเข้า' },
      { key: 'reports',         icon: 'fi-rr-chart-pie',       label: 'รายงาน',            href: 'reports.html',         bottom: true, bottomLabel: 'รายงาน' },
      { key: 'admin-users',     icon: 'fi-rr-user-shield',     label: 'จัดการผู้ใช้',     href: 'admin-users.html',     bottom: false },
      { key: 'admin-activity',  icon: 'fi-rr-time-past',       label: 'ประวัติการใช้งาน', href: 'admin-activity.html',  bottom: false },
    ];

    // ---------- Sidebar (desktop ≥ lg) ----------
    const navHtml = items.map(i => `
      <a href="${i.href}" class="nav-item ${active === i.key ? 'active' : ''} flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50">
        <i class="${i.icon} text-lg"></i>
        <span>${i.label}</span>
      </a>`).join('');

    const sidebarEl = document.getElementById('wk-sidebar-slot');
    if (sidebarEl) {
      sidebarEl.innerHTML = `
        <div id="wk-backdrop" class="hidden lg:hidden fixed inset-0 bg-slate-900/50 z-40" onclick="wkSidebar.close()"></div>
        <aside id="wk-sidebar"
          class="fixed lg:sticky top-0 left-0 z-50 lg:z-10 w-72 lg:w-60 h-screen bg-white dark:bg-slate-900 border-r border-slate-100 dark:border-slate-800 transform -translate-x-full lg:translate-x-0 transition-transform flex flex-col">
          <div class="flex items-center justify-between gap-2 px-4 h-16 border-b border-slate-100 dark:border-slate-800 shrink-0">
            <a href="index.html" class="flex items-center gap-2.5">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-700 to-sky-500 text-white flex items-center justify-center shadow-md shadow-blue-500/30">
                <i class="fi-sr-shield-check"></i>
              </div>
              <div class="leading-tight">
                <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">Welfare Korat</div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400">บัตรสวัสดิการ 2569</div>
              </div>
            </a>
            <button class="lg:hidden p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" onclick="wkSidebar.close()">
              <i class="fi-rr-cross-small"></i>
            </button>
          </div>
          <nav class="p-3 space-y-1 flex-1 overflow-y-auto">
            ${navHtml}
          </nav>
          <div class="p-3 border-t border-slate-100 dark:border-slate-800 shrink-0">
            <a href="login.html" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 hover:text-red-600">
              <i class="fi-rr-sign-out-alt"></i> ออกจากระบบ
            </a>
          </div>
        </aside>`;
    }

    // ---------- Top header (mobile + desktop) ----------
    const topbarEl = document.getElementById('wk-topbar-slot');
    if (topbarEl) {
      topbarEl.innerHTML = `
        <header class="sticky top-0 z-30 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center justify-between px-4 h-16">
            <div class="flex items-center gap-3 min-w-0">
              <button class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" onclick="wkSidebar.open()">
                <i class="fi-rr-menu-burger text-lg"></i>
              </button>
              <div class="min-w-0">
                ${greeting ? `<div class="text-xs text-slate-500 dark:text-slate-400 leading-tight">${greeting}</div>` : ''}
                <div class="font-semibold text-slate-800 dark:text-slate-100 ${greeting ? 'text-base' : 'text-sm'} leading-tight truncate">${title}</div>
                ${!greeting && subtitle ? `<div class="text-xs text-slate-500 dark:text-slate-400 leading-tight truncate">${subtitle}</div>` : ''}
              </div>
            </div>
            <div class="flex items-center gap-1">
              <button title="ลดตัวอักษร" onclick="wkTheme.smaller()" class="px-2 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-xs">A−</button>
              <button title="ขนาดมาตรฐาน" onclick="wkTheme.reset()" class="hidden sm:inline-block px-2 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-sm">A</button>
              <button title="เพิ่มตัวอักษร" onclick="wkTheme.bigger()" class="px-2 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-base font-semibold">A+</button>
              <button title="สลับโหมดสี" onclick="wkTheme.toggle()" class="ml-1 p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                <i class="fi-rr-brightness text-lg dark:hidden"></i>
                <i class="fi-sr-moon text-lg hidden dark:inline-block text-orange-400"></i>
              </button>
              <button class="relative p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="การแจ้งเตือน">
                <i class="fi-rr-bell text-lg"></i>
                <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white dark:ring-slate-900"></span>
              </button>
              <div class="hidden sm:flex items-center gap-2 ml-2 pl-2 border-l border-slate-100 dark:border-slate-800">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-700 to-sky-500 text-white flex items-center justify-center text-xs font-semibold">SA</div>
                <div class="text-xs leading-tight">
                  <div class="font-medium text-slate-800 dark:text-slate-100">สมชาย ผู้ดูแล</div>
                  <div class="text-slate-500 dark:text-slate-400">Super Admin</div>
                </div>
              </div>
            </div>
          </div>
        </header>`;
    }

    // ---------- Bottom nav (mobile only) ----------
    const bottomItems = items.filter(i => i.bottom);
    const bottomEl = document.getElementById('wk-bottomnav-slot');
    if (bottomEl) {
      bottomEl.innerHTML = `
        <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-30 bg-white dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 px-2 pb-[max(env(safe-area-inset-bottom),0.25rem)] pt-1">
          <div class="flex items-stretch justify-around">
            ${bottomItems.map(i => `
              <a href="${i.href}" class="bottom-nav-item ${active === i.key ? 'active' : ''} flex flex-col items-center justify-center py-1.5 px-3 text-[10px] text-slate-500 dark:text-slate-400 min-w-[58px]">
                <span class="bn-icon-wrap w-10 h-7 flex items-center justify-center rounded-full transition-colors">
                  <i class="${i.icon} text-lg"></i>
                </span>
                <span class="mt-0.5 leading-tight">${i.bottomLabel || i.label}</span>
              </a>`).join('')}
          </div>
        </nav>`;
    }
  }
};

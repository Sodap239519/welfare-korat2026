<script setup>
import { ref, reactive, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useThemeStore } from '@/stores/theme';
import { useAuthStore } from '@/stores/auth';

const theme = useThemeStore();
const auth  = useAuthStore();
const route = useRoute();
const router = useRouter();

const tab = ref('login');

const loginForm = reactive({ phone: '', password: '', remember: false });
const registerForm = reactive({
  name: '', phone: '', position_type: 'ผู้ใหญ่บ้าน', position_other: '',
  password: '', password_confirmation: '', email: '',
  // ขอบเขตที่รับผิดชอบ — บังคับเลือก หมู่บ้าน (ครอบทุก ม.)
  amphur_id: '', tambon_id: '', village_name: '',
});
const showPassword = ref(false);

const fieldErrors = ref({});
const flashOk = ref('');

// Geography options for register (load on mount)
const amphurOpts = ref([]);
const tambonOpts = ref([]);
const villageOpts = ref([]);

// Public stats สำหรับแสดงในหน้า Login (ดึงจาก DB จริง · cache 5 นาที)
const publicStats = ref({ targets: 0, tambons: 0, amphurs: 0 });

onMounted(async () => {
  // โหลด amphur options + public stats พร้อมกัน
  const [a, s] = await Promise.all([
    axios.get('/api/auth/geo/amphurs'),
    axios.get('/api/auth/public-stats'),
  ]);
  amphurOpts.value = a.data.data;
  publicStats.value = s.data;
});

// formatNumber helper (ในนี้ ไม่อยากดึงจาก composables เพราะ Login เป็น standalone)
const fmt = (n) => Number(n || 0).toLocaleString('th-TH');
watch(() => registerForm.amphur_id, async (id) => {
  registerForm.tambon_id = ''; registerForm.village_name = '';
  tambonOpts.value = []; villageOpts.value = [];
  if (!id) return;
  tambonOpts.value = (await axios.get('/api/auth/geo/tambons', { params: { amphur_id: id } })).data.data;
});
watch(() => registerForm.tambon_id, async (id) => {
  registerForm.village_name = '';
  villageOpts.value = [];
  if (!id) return;
  // ใช้ village-names ที่ group หมู่บ้าน + รวม ม. — ไม่ลงลึกถึงหมู่ที่
  villageOpts.value = (await axios.get('/api/auth/geo/village-names', { params: { tambon_id: id } })).data.data;
});

async function submitLogin() {
  fieldErrors.value = {};
  flashOk.value = '';
  try {
    await auth.login(loginForm.phone, loginForm.password, loginForm.remember);
    const redirect = route.query.redirect || { name: 'dashboard' };
    router.push(redirect);
  } catch (e) {
    fieldErrors.value = e.response?.data?.errors || {};
  }
}

async function submitRegister() {
  fieldErrors.value = {};
  flashOk.value = '';
  try {
    const res = await auth.register({ ...registerForm });
    flashOk.value = res.message;
    tab.value = 'login';
    loginForm.phone = registerForm.phone;
    Object.assign(registerForm, {
      name: '', phone: '', position_other: '', password: '', password_confirmation: '', email: '',
      amphur_id: '', tambon_id: '', village_name: '',
    });
  } catch (e) {
    fieldErrors.value = e.response?.data?.errors || {};
  }
}
</script>

<template>
  <div class="min-h-screen lg:flex">

    <!-- Brand hero -->
    <div class="card-hero lg:w-1/2 lg:min-h-screen p-6 lg:p-10 rounded-none lg:rounded-r-[40px] flex flex-col relative overflow-hidden">
      <!-- ╔══════════════════════════════════════════════════════╗ -->
      <!-- ║  พื้นหลัง: Landmarks สถานที่สำคัญของจังหวัดนครราชสีมา   ║ -->
      <!-- ║  ย่าโม+ประตูชุมพล · ปราสาทหินพิมาย · เขาใหญ่ · ด่านเกวียน ║ -->
      <!-- ╚══════════════════════════════════════════════════════╝ -->
      <svg class="absolute inset-x-0 bottom-0 w-full pointer-events-none"
           viewBox="0 0 800 480" preserveAspectRatio="xMidYEnd slice"
           xmlns="http://www.w3.org/2000/svg" aria-hidden="true"
           style="height: 65%;">
        <defs>
          <linearGradient id="fadeIn" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="white" stop-opacity="0"/>
            <stop offset="100%" stop-color="white" stop-opacity="0.35"/>
          </linearGradient>
        </defs>

        <!-- ── Layer 1: เขาใหญ่ (Mountains · ไกลสุด) ────────────────── -->
        <g stroke="#67e8f9" stroke-width="1.2" fill="none" opacity="0.22">
          <path d="M 0,340 L 60,260 L 130,310 L 210,220 L 290,290 L 370,200 L 450,280 L 540,210 L 620,290 L 710,240 L 800,310"/>
          <path d="M 0,380 L 90,310 L 180,360 L 280,270 L 380,340 L 480,260 L 580,330 L 680,280 L 800,360"/>
        </g>

        <!-- ── Layer 2: ปราสาทหินพิมาย (Phimai · กลางขวา) ───────────── -->
        <g stroke="#a5f3fc" stroke-width="1.6" fill="none" opacity="0.55"
           transform="translate(540,180)">
          <!-- ปรางค์ใหญ่กลาง (ลักษณะดอกบัวตูม) -->
          <path d="M -3,200 L -3,80 Q -3,30 0,15 Q 3,30 3,80 L 3,200 Z"
                fill="rgba(165,243,252,0.08)"/>
          <path d="M -20,200 L -20,100 Q -20,40 -10,25 Q 0,15 10,25 Q 20,40 20,100 L 20,200 Z"
                fill="rgba(165,243,252,0.05)"/>
          <path d="M -35,200 L -35,140 Q -35,90 -25,75 Q -15,65 0,60 Q 15,65 25,75 Q 35,90 35,140 L 35,200 Z"
                fill="rgba(165,243,252,0.04)"/>
          <!-- ฐานชั้น -->
          <rect x="-50" y="200" width="100" height="15"/>
          <rect x="-58" y="215" width="116" height="20"/>
          <rect x="-65" y="235" width="130" height="25"/>
          <!-- ปลายยอด -->
          <line x1="0" y1="15" x2="0" y2="-5"/>
          <circle cx="0" cy="-10" r="3" fill="rgba(165,243,252,0.4)"/>
          <!-- เส้นแบ่งชั้น (ตามแบบขอม) -->
          <line x1="-20" y1="80" x2="20" y2="80"/>
          <line x1="-25" y1="110" x2="25" y2="110"/>
          <line x1="-30" y1="140" x2="30" y2="140"/>
          <line x1="-35" y1="170" x2="35" y2="170"/>
        </g>

        <!-- ── Layer 3: ประตูชุมพล + อนุสาวรีย์ย่าโม (กลางซ้าย) ─────── -->
        <g stroke="#a5f3fc" stroke-width="1.6" fill="none" opacity="0.55"
           transform="translate(220,200)">
          <!-- อนุสาวรีย์ย่าโม (บนสุด) -->
          <g transform="translate(0,-30)">
            <!-- หัว -->
            <ellipse cx="0" cy="-10" rx="6" ry="8" fill="rgba(165,243,252,0.15)"/>
            <!-- ลำตัว+ผ้าซิ่น (ทรงสามเหลี่ยม) -->
            <path d="M -3,-2 L 3,-2 L 11,35 L -11,35 Z" fill="rgba(165,243,252,0.12)"/>
            <!-- มือถือดาบ -->
            <line x1="11" y1="5" x2="20" y2="-15"/>
            <line x1="20" y1="-15" x2="20" y2="-25"/>
            <!-- ฐานอนุสาวรีย์ (ทรงสูง) -->
            <rect x="-18" y="35" width="36" height="55" fill="rgba(165,243,252,0.06)"/>
            <line x1="-18" y1="50" x2="18" y2="50"/>
            <line x1="-18" y1="75" x2="18" y2="75"/>
            <rect x="-25" y="90" width="50" height="8"/>
            <rect x="-30" y="98" width="60" height="12"/>
          </g>
          <!-- ประตูชุมพล (ป้อมประตูเมือง 2 ป้อม) -->
          <g transform="translate(0,90)">
            <!-- ป้อมซ้าย -->
            <rect x="-90" y="0" width="40" height="80" fill="rgba(165,243,252,0.08)"/>
            <!-- หลังคาทรงไทย ซ้าย -->
            <path d="M -95,0 L -70,-25 L -45,0 Z" fill="rgba(165,243,252,0.1)"/>
            <line x1="-70" y1="-25" x2="-70" y2="-32"/>
            <circle cx="-70" cy="-35" r="2" fill="rgba(165,243,252,0.3)"/>
            <!-- หน้าต่างป้อมซ้าย -->
            <rect x="-80" y="20" width="20" height="25"/>
            <line x1="-70" y1="20" x2="-70" y2="45"/>
            <!-- ป้อมขวา (mirror) -->
            <rect x="50" y="0" width="40" height="80" fill="rgba(165,243,252,0.08)"/>
            <path d="M 45,0 L 70,-25 L 95,0 Z" fill="rgba(165,243,252,0.1)"/>
            <line x1="70" y1="-25" x2="70" y2="-32"/>
            <circle cx="70" cy="-35" r="2" fill="rgba(165,243,252,0.3)"/>
            <rect x="60" y="20" width="20" height="25"/>
            <line x1="70" y1="20" x2="70" y2="45"/>
            <!-- ซุ้มประตูตรงกลาง -->
            <path d="M -50,80 L -50,40 Q -50,15 0,15 Q 50,15 50,40 L 50,80 Z"
                  fill="rgba(165,243,252,0.06)"/>
            <line x1="-50" y1="40" x2="50" y2="40"/>
            <!-- หลังคาทรงไทยสูงตรงกลาง (ใต้ย่าโม) -->
            <path d="M -55,0 L 0,-15 L 55,0 Z" fill="rgba(165,243,252,0.1)"/>
          </g>
        </g>

        <!-- ── Layer 4: ตึกเมือง stylized (ขวาสุด · station/city) ─── -->
        <g stroke="#bae6fd" stroke-width="1.2" fill="none" opacity="0.4"
           transform="translate(700,280)">
          <rect x="-30" y="0" width="20" height="90" fill="rgba(186,230,253,0.06)"/>
          <rect x="-30" y="0" width="20" height="6"/>
          <line x1="-30" y1="25" x2="-10" y2="25"/>
          <line x1="-30" y1="45" x2="-10" y2="45"/>
          <line x1="-30" y1="65" x2="-10" y2="65"/>
          <!-- หอนาฬิกาสถานี -->
          <rect x="-5" y="-20" width="18" height="110" fill="rgba(186,230,253,0.06)"/>
          <circle cx="4" cy="-5" r="6"/>
          <line x1="4" y1="-9" x2="4" y2="-5"/>
          <line x1="4" y1="-5" x2="7" y2="-5"/>
        </g>

        <!-- ── Layer 5: หม้อด่านเกวียน (ซ้ายสุด) ─────────────────── -->
        <g stroke="#bae6fd" stroke-width="1.4" fill="none" opacity="0.35"
           transform="translate(80,330)">
          <!-- หม้อใหญ่ -->
          <ellipse cx="0" cy="0" rx="28" ry="6"/>
          <path d="M -28,0 Q -38,40 -20,75 L 20,75 Q 38,40 28,0"/>
          <ellipse cx="0" cy="75" rx="20" ry="4"/>
          <!-- เส้นลวดลายบนหม้อ -->
          <path d="M -32,20 Q 0,28 32,20" stroke-dasharray="2 3"/>
          <path d="M -34,40 Q 0,48 34,40" stroke-dasharray="2 3"/>
        </g>

        <!-- ── Layer 6: คลื่น/อ่างเก็บน้ำลำตะคอง (พื้นล่างสุด) ────── -->
        <g stroke="#ffffff" stroke-width="1" fill="none" opacity="0.18">
          <path d="M 0,430 Q 200,415 400,430 T 800,430"/>
          <path d="M 0,450 Q 250,470 500,450 T 800,450"/>
          <path d="M 0,470 Q 200,460 400,475 T 800,470"/>
        </g>

        <!-- ── Layer 7: ดาว/ดวงเล็กๆ (เพิ่มมิติ) ─────────────────── -->
        <g fill="#ffffff" opacity="0.4">
          <circle cx="100" cy="70" r="1"/>
          <circle cx="180" cy="40" r="1.2"/>
          <circle cx="350" cy="60" r="0.8"/>
          <circle cx="500" cy="80" r="1"/>
          <circle cx="630" cy="50" r="0.8"/>
          <circle cx="730" cy="100" r="1"/>
        </g>
      </svg>

      <!-- Top row: brand + theme tools (mobile · ขนานกัน ไม่ทับกัน) -->
      <div class="relative z-10 flex items-start justify-between gap-3">
        <div class="leading-tight">
          <div class="text-xl font-bold tracking-tight">Welfare Korat 2026</div>
          <div class="text-xs opacity-80 mt-0.5">บัตรสวัสดิการแห่งรัฐ 2569</div>
        </div>
        <div class="lg:hidden flex gap-1 shrink-0">
          <button @click="theme.smaller" class="px-2 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 text-xs tap-transparent">A−</button>
          <button @click="theme.bigger" class="px-2 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 text-base font-semibold tap-transparent">A+</button>
          <button @click="theme.toggle" class="p-2 rounded-lg bg-white/15 hover:bg-white/25 tap-transparent" title="สลับโหมดสี">
            <i v-if="!theme.isDark" class="fi-rr-brightness text-base"></i>
            <i v-else class="fi-sr-moon text-base text-orange-300"></i>
          </button>
        </div>
      </div>

      <div class="relative z-10 flex-1 flex flex-col justify-center py-8 lg:py-0">
        <h1 class="text-2xl lg:text-[2.1rem] font-semibold leading-snug">
          ระบบติดตามการลงทะเบียน<br>
          บัตรสวัสดิการแห่งรัฐ 2569<br>
          <span class="opacity-90">แบบเรียลไทม์ จังหวัดนครราชสีมา</span>
        </h1>
        <div class="grid grid-cols-3 gap-3 mt-6 max-w-md">
          <div class="bg-white/10 backdrop-blur rounded-2xl p-3">
            <div class="text-lg lg:text-2xl font-bold tabular-nums">{{ fmt(publicStats.targets) }}</div>
            <div class="text-[11px] lg:text-xs opacity-80">กลุ่มเป้าหมาย</div>
          </div>
          <div class="bg-white/10 backdrop-blur rounded-2xl p-3">
            <div class="text-lg lg:text-2xl font-bold tabular-nums">{{ fmt(publicStats.tambons) }}</div>
            <div class="text-[11px] lg:text-xs opacity-80">ตำบล</div>
          </div>
          <div class="bg-white/10 backdrop-blur rounded-2xl p-3">
            <div class="text-lg lg:text-2xl font-bold tabular-nums">{{ fmt(publicStats.amphurs) }}</div>
            <div class="text-[11px] lg:text-xs opacity-80">อำเภอ</div>
          </div>
        </div>
      </div>
      <!-- Footer (desktop เท่านั้น) -->
      <div class="relative z-10 text-[11px] lg:text-xs opacity-80 hidden lg:block leading-relaxed">
        © 2569 โครงการการขับเคลื่อนพื้นที่วิจัยเชิงยุทธศาสตร์เพื่อขจัดความยากจน
        และสร้างโอกาสทางสังคมแบบบูรณาการ จังหวัดนครราชสีมา<br>
        <span class="opacity-90">มหาวิทยาลัยราชภัฏนครราชสีมา</span>
      </div>
    </div>

    <!-- Form -->
    <div class="lg:w-1/2 flex flex-col">
      <div class="hidden lg:flex justify-end gap-1 p-4">
        <button @click="theme.smaller" class="px-2 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-xs">A−</button>
        <button @click="theme.bigger" class="px-2 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-base font-semibold">A+</button>
        <button @click="theme.toggle" class="ml-1 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800">
          <i v-if="!theme.isDark" class="fi-rr-brightness text-lg"></i>
          <i v-else class="fi-sr-moon text-lg text-orange-400"></i>
        </button>
      </div>

      <div class="flex-1 flex items-center justify-center p-5 lg:p-10">
        <div class="w-full max-w-sm">
          <h2 class="text-2xl font-semibold">ยินดีต้อนรับกลับ</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">เข้าสู่ระบบด้วยเบอร์โทรของคุณ</p>

          <div class="flex gap-1 mt-5 bg-slate-50 dark:bg-slate-800/50 rounded-xl p-1">
            <button @click="tab='login'" :class="['flex-1 py-2 text-sm font-medium rounded-lg', tab==='login' ? 'bg-white dark:bg-slate-700 shadow-sm' : 'text-slate-500']">เข้าสู่ระบบ</button>
            <button @click="tab='register'" :class="['flex-1 py-2 text-sm font-medium rounded-lg', tab==='register' ? 'bg-white dark:bg-slate-700 shadow-sm' : 'text-slate-500']">ลงทะเบียน</button>
          </div>

          <div v-if="flashOk" class="mt-4 card-tint-green p-3 text-sm">
            <i class="fi-rr-check-circle"></i> {{ flashOk }}
          </div>
          <div v-if="auth.error && !Object.keys(fieldErrors).length" class="mt-4 card-tint-red p-3 text-sm">
            <i class="fi-rr-cross-circle"></i> {{ auth.error }}
          </div>

          <!-- LOGIN -->
          <form v-if="tab==='login'" @submit.prevent="submitLogin" class="space-y-4 mt-5">
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">เบอร์โทรศัพท์</label>
              <div class="relative">
                <i class="fi-rr-phone-call absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input v-model="loginForm.phone" type="tel" inputmode="tel" placeholder="08x-xxx-xxxx" autocomplete="username"
                  class="w-full pl-10 pr-3 py-3 rounded-xl border bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                  :class="fieldErrors.phone ? 'border-red-300' : 'border-slate-100 dark:border-slate-800'">
              </div>
              <div v-if="fieldErrors.phone" class="text-[11px] text-red-600 mt-1">{{ fieldErrors.phone[0] }}</div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">รหัสผ่าน</label>
              <div class="relative">
                <i class="fi-rr-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input v-model="loginForm.password" :type="showPassword ? 'text' : 'password'" placeholder="••••••••" autocomplete="current-password"
                  class="w-full pl-10 pr-10 py-3 rounded-xl border bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                  :class="fieldErrors.password ? 'border-red-300' : 'border-slate-100 dark:border-slate-800'">
                <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                  <i :class="showPassword ? 'fi-rr-eye-crossed' : 'fi-rr-eye'"></i>
                </button>
              </div>
              <div v-if="fieldErrors.password" class="text-[11px] text-red-600 mt-1">{{ fieldErrors.password[0] }}</div>
            </div>
            <div class="flex items-center justify-between text-xs">
              <label class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                <input v-model="loginForm.remember" type="checkbox" class="rounded text-blue-600"> จดจำการเข้าสู่ระบบ
              </label>
            </div>
            <button type="submit" :disabled="auth.loading" class="btn-primary block w-full text-center py-3 font-medium disabled:opacity-60">
              <i v-if="auth.loading" class="fi-rr-spinner animate-spin"></i>
              <span v-else>เข้าสู่ระบบ <i class="fi-rr-arrow-small-right"></i></span>
            </button>

            <div class="card-tint-sky p-3 text-xs">
              <div class="font-medium mb-1"><i class="fi-rr-info"></i> บัญชี Demo สำหรับทดลอง</div>
              <div>Super Admin: <code>0900000001</code> / <code>123456</code></div>
              <div>Admin: <code>0900000002</code> / <code>123456</code></div>
              <div>Tracker: <code>0812345678</code> / <code>123456</code></div>
            </div>
          </form>

          <!-- REGISTER -->
          <form v-else @submit.prevent="submitRegister" class="space-y-3 mt-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">ชื่อ-สกุล</label>
                <input v-model="registerForm.name" type="text" placeholder="นายสมชาย ใจดี"
                  class="w-full px-3 py-2.5 rounded-xl border bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                  :class="fieldErrors.name ? 'border-red-300' : 'border-slate-100 dark:border-slate-800'">
                <div v-if="fieldErrors.name" class="text-[11px] text-red-600 mt-1">{{ fieldErrors.name[0] }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">เบอร์โทร (Username)</label>
                <input v-model="registerForm.phone" type="tel" inputmode="tel" placeholder="08xxxxxxxx"
                  class="w-full px-3 py-2.5 rounded-xl border bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                  :class="fieldErrors.phone ? 'border-red-300' : 'border-slate-100 dark:border-slate-800'">
                <div v-if="fieldErrors.phone" class="text-[11px] text-red-600 mt-1">{{ fieldErrors.phone[0] }}</div>
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">ตำแหน่ง</label>
              <select v-model="registerForm.position_type" class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
                <option>ผู้ใหญ่บ้าน</option><option>กำนัน</option><option>ผู้ช่วยผู้ใหญ่บ้าน</option>
                <option>อสม.</option><option>ส.อบต.</option><option>อื่นๆ</option>
              </select>
            </div>
            <div v-if="registerForm.position_type === 'อื่นๆ'">
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">ระบุตำแหน่ง</label>
              <input v-model="registerForm.position_other" type="text"
                class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
            </div>

            <!-- พื้นที่รับผิดชอบ — จำเป็นต้องเลือก -->
            <div class="card-tint-orange p-3 rounded-xl">
              <div class="text-xs font-medium mb-2 flex items-center gap-1.5">
                <i class="fi-rr-marker text-orange-700"></i>
                หมู่บ้านที่รับผิดชอบ <span class="text-red-600">*</span>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <select v-model="registerForm.amphur_id" required
                  class="w-full min-w-0 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm">
                  <option value="">เลือกอำเภอ</option>
                  <option v-for="a in amphurOpts" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
                <select v-model="registerForm.tambon_id" :disabled="!registerForm.amphur_id" required
                  class="w-full min-w-0 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm disabled:opacity-40">
                  <option value="">เลือกตำบล</option>
                  <option v-for="t in tambonOpts" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <select v-model="registerForm.village_name" :disabled="!registerForm.tambon_id" required
                  class="w-full min-w-0 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm disabled:opacity-40">
                  <option value="">เลือกหมู่บ้าน</option>
                  <option v-for="v in villageOpts" :key="v.name" :value="v.name">
                    {{ v.name }}{{ v.moo_count > 1 ? ' · ครอบคลุม '+v.moo_count+' หมู่ ('+v.moo_label+')' : (v.moo_label ? ' · '+v.moo_label : '') }}
                  </option>
                </select>
              </div>
              <div v-if="fieldErrors.village_name" class="text-[11px] text-red-600 mt-2">{{ fieldErrors.village_name[0] }}</div>
              <div class="text-[10px] text-slate-600 dark:text-slate-400 mt-2 leading-snug">
                <i class="fi-rr-info"></i> ผู้กำกับติดตามดูแล <b>ทั้งหมู่บ้าน</b> — หากชื่อหมู่บ้านเดียวกันมีหลายหมู่ ระบบจะให้คุณดูแลครบทุกหมู่ที่
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">อีเมล <span class="text-slate-400">(ไม่บังคับ)</span></label>
              <input v-model="registerForm.email" type="email" placeholder="name@example.com"
                class="w-full px-3 py-2.5 rounded-xl border bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                :class="fieldErrors.email ? 'border-red-300' : 'border-slate-100 dark:border-slate-800'">
              <div v-if="fieldErrors.email" class="text-[11px] text-red-600 mt-1">{{ fieldErrors.email[0] }}</div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">รหัสผ่าน (≥ 6 ตัว)</label>
                <input v-model="registerForm.password" type="password" minlength="6"
                  class="w-full px-3 py-2.5 rounded-xl border bg-white dark:bg-slate-900 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                  :class="fieldErrors.password ? 'border-red-300' : 'border-slate-100 dark:border-slate-800'">
                <div v-if="fieldErrors.password" class="text-[11px] text-red-600 mt-1">{{ fieldErrors.password[0] }}</div>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1.5">ยืนยันรหัสผ่าน</label>
                <input v-model="registerForm.password_confirmation" type="password" minlength="6"
                  class="w-full px-3 py-2.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm">
              </div>
            </div>
            <div class="card-tint-blue text-xs p-3">
              <i class="fi-rr-info"></i> บัญชีต้องได้รับการอนุมัติจาก Super Admin ก่อนเข้าใช้งาน
            </div>
            <button type="submit" :disabled="auth.loading" class="btn-primary w-full py-3 font-medium disabled:opacity-60">
              <i v-if="auth.loading" class="fi-rr-spinner animate-spin"></i>
              <span v-else>ลงทะเบียน</span>
            </button>
          </form>
        </div>
      </div>

      <!-- Mobile footer — แสดงเฉพาะมือถือ (desktop ใช้ footer ใน hero แทน) -->
      <div class="lg:hidden px-5 pb-5 text-[10px] text-slate-500 dark:text-slate-400 leading-relaxed text-center">
        © 2569 โครงการการขับเคลื่อนพื้นที่วิจัยเชิงยุทธศาสตร์เพื่อขจัดความยากจน
        และสร้างโอกาสทางสังคมแบบบูรณาการ จังหวัดนครราชสีมา ·
        <span class="font-medium">มหาวิทยาลัยราชภัฏนครราชสีมา</span>
      </div>
    </div>
  </div>
</template>

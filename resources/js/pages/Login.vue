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
onMounted(async () => {
  amphurOpts.value = (await axios.get('/api/auth/geo/amphurs')).data.data;
});
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
    <div class="card-hero lg:w-1/2 lg:min-h-screen p-6 lg:p-10 rounded-none lg:rounded-r-[40px] flex flex-col">
      <div class="absolute top-4 right-4 lg:hidden flex gap-1">
        <button @click="theme.smaller" class="px-2 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 text-xs">A−</button>
        <button @click="theme.bigger" class="px-2 py-1.5 rounded-lg bg-white/15 hover:bg-white/25 text-base font-semibold">A+</button>
        <button @click="theme.toggle" class="ml-1 p-2 rounded-lg bg-white/15 hover:bg-white/25">
          <i v-if="!theme.isDark" class="fi-rr-brightness text-base"></i>
          <i v-else class="fi-sr-moon text-base text-orange-300"></i>
        </button>
      </div>

      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur flex items-center justify-center">
          <i class="fi-sr-shield-check text-xl"></i>
        </div>
        <div class="leading-tight">
          <div class="font-semibold">Welfare Korat</div>
          <div class="text-xs opacity-80">บัตรสวัสดิการแห่งรัฐ 2569</div>
        </div>
      </div>

      <div class="flex-1 flex flex-col justify-center py-8 lg:py-0">
        <h1 class="text-2xl lg:text-4xl font-semibold leading-tight">
          ติดตามสถานะ<br>การลงทะเบียน<br><span class="opacity-90">แบบเรียลไทม์</span>
        </h1>
        <p class="text-sm lg:text-base opacity-90 mt-3 max-w-md">
          ระบบติดตามรอบใหม่ปี 2569 สำหรับผู้กำกับติดตามรายหมู่บ้าน · ตำบล · อำเภอ จังหวัดนครราชสีมา
        </p>
        <div class="grid grid-cols-3 gap-3 mt-6 max-w-md">
          <div class="bg-white/10 backdrop-blur rounded-2xl p-3"><div class="text-lg lg:text-2xl font-bold">61,743</div><div class="text-[10px] lg:text-xs opacity-80">เป้าหมาย</div></div>
          <div class="bg-white/10 backdrop-blur rounded-2xl p-3"><div class="text-lg lg:text-2xl font-bold">1,924</div><div class="text-[10px] lg:text-xs opacity-80">หมู่บ้าน</div></div>
          <div class="bg-white/10 backdrop-blur rounded-2xl p-3"><div class="text-lg lg:text-2xl font-bold">32</div><div class="text-[10px] lg:text-xs opacity-80">อำเภอ</div></div>
        </div>
      </div>
      <div class="text-xs opacity-70 hidden lg:block">© 2569 ผู้ว่าราชการจังหวัดนครราชสีมา</div>
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
            <div class="grid grid-cols-2 gap-3">
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
    </div>
  </div>
</template>

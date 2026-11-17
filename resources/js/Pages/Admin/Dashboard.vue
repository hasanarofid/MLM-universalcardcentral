<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BirthDatePicker from '@/Components/BirthDatePicker.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
  Copy, 
  Wallet, 
  TrendingUp, 
  Users, 
  ArrowUpRight, 
  Check, 
  CreditCard, 
  HelpCircle,
  UserPlus,
  KeyRound,
  ShieldCheck,
  User,
  Mail,
  Phone,
  Calendar,
  AlertCircle,
  UploadCloud,
  Lock,
  Eye,
  EyeOff,
  FileText,
  Crown,
  Sparkles,
  AlertTriangle,
  X
} from '@lucide/vue';

const props = defineProps({
  is_admin: Boolean,
  activation_status: {
    type: String,
    default: 'active'
  },
  pending_pin_request: {
    type: Object,
    default: null
  },
  current_user_username: String,
  vouchers: Array,
  banks: Array,
  registration_fee: Number,
  all_sponsors: Array,
  referral_links: Object,
  wallet: Object,
  premi_info: Object,
  ucc_summary: Object,
  direct_referrals: Array
});

const pinForm = useForm({
  pin_code: '',
});

const pinOrderForm = useForm({
  tier: 'bronze',
  quantity: 1,
  transfer_proof: null,
});

const submitSelfPinActivation = () => {
  pinForm.post(route('admin.pin.activate-self'), {
    preserveScroll: true,
    onSuccess: () => pinForm.reset(),
  });
};

const submitPinOrder = () => {
  pinOrderForm.post(route('admin.pin.request'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => pinOrderForm.reset(),
  });
};

const activateDownlineModal = ref({ open: false, member: null });
const activateDownlineForm = useForm({
  member_id: '',
  pin_code: '',
  position: 'left',
});

const openActivateDownlineModal = (refMember) => {
  activateDownlineForm.member_id = refMember.id;
  activateDownlineForm.pin_code = props.vouchers?.[0]?.code || '';
  activateDownlineForm.position = 'left';
  activateDownlineModal.value = { open: true, member: refMember };
};

const submitActivateDownline = () => {
  activateDownlineForm.post(route('admin.pin.activate-downline'), {
    preserveScroll: true,
    onSuccess: () => {
      activateDownlineModal.value = { open: false, member: null };
      activateDownlineForm.reset();
    },
  });
};

const copySuccessMsg = ref('');
const isAddMitraModalOpen = ref(false);
const isPriorityModalOpen = ref(false);
const isUpgradeConfirmModalOpen = ref(false);
const isUpgrading = ref(false);
const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const ktpPreview = ref(null);
const fileInput = ref(null);
const transferProofPreview = ref(null);
const transferProofFileInput = ref(null);

const handleUpgradePremier = () => {
  if (props.wallet?.is_premier) {
    alert('Akun Anda sudah berstatus PREMIER Member aktif!');
    return;
  }
  const totalDownlines = props.wallet?.total_downlines ?? 0;
  if (totalDownlines < 1000) {
    isPriorityModalOpen.value = true;
  } else {
    isUpgradeConfirmModalOpen.value = true;
  }
};

const executeUpgradePremier = () => {
  isUpgrading.value = true;
  router.post(route('admin.upgrade-premier'), {}, {
    preserveScroll: true,
    onSuccess: () => {
      isUpgradeConfirmModalOpen.value = false;
      isUpgrading.value = false;
    },
    onError: () => {
      isUpgrading.value = false;
    }
  });
};

const bankOptions = [
  'Bank BCA',
  'Bank BRI',
  'Bank BNI',
  'Bank Mandiri',
  'Bank BSI',
  'Bank CIMB Niaga',
  'Bank Permata',
  'Bank Danamon',
  'Bank Tabungan Negara (BTN)',
  'Bank Jago',
  'Seabank',
  'DANA (E-Wallet)',
  'OVO (E-Wallet)',
  'GoPay (E-Wallet)',
  'Lainnya',
];

const addMitraForm = useForm({
  name: '',
  phone: '',
  email: '',
  is_left_handed: 'Tidak',
  beneficiary_name: '',
  beneficiary_birth_date: '',
  beneficiary_relation: '',
  emergency_phone: '',
  bank_name: 'Bank BRI',
  bank_account_number: '',
  bank_account_name: '',
  ktp_image: null,
  transfer_proof: null,
  password: '',
  password_confirmation: '',
  sponsor_username: props.current_user_username || 'admin',
  source: 'dashboard',
});

const handleFileUpload = (e) => {
  const file = e.target.files[0];
  if (!file) return;

  if (file.size > 10 * 1024 * 1024) {
    alert('Ukuran file maksimal adalah 10 MB');
    return;
  }

  addMitraForm.ktp_image = file;
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (event) => {
      ktpPreview.value = event.target.result;
    };
    reader.readAsDataURL(file);
  } else {
    ktpPreview.value = 'document';
  }
};

const removeFile = () => {
  addMitraForm.ktp_image = null;
  ktpPreview.value = null;
  if (fileInput.value) {
    fileInput.value.value = '';
  }
};

const handleTransferProofUpload = (e) => {
  const file = e.target.files[0];
  if (!file) return;

  if (file.size > 10 * 1024 * 1024) {
    alert('Ukuran file maksimal adalah 10 MB');
    return;
  }

  addMitraForm.transfer_proof = file;
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (event) => {
      transferProofPreview.value = event.target.result;
    };
    reader.readAsDataURL(file);
  } else {
    transferProofPreview.value = 'document';
  }
};

const removeTransferProof = () => {
  addMitraForm.transfer_proof = null;
  transferProofPreview.value = null;
  if (transferProofFileInput.value) {
    transferProofFileInput.value.value = '';
  }
};

const submitAddMitra = () => {
  if (!addMitraForm.transfer_proof) {
    addMitraForm.setError('transfer_proof', 'Bukti transfer pembayaran wajib diunggah.');
    return;
  }
  addMitraForm.post(route('admin.activation.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      isAddMitraModalOpen.value = false;
      addMitraForm.reset();
      addMitraForm.is_left_handed = 'Tidak';
      addMitraForm.bank_name = 'Bank BRI';
      addMitraForm.sponsor_username = props.current_user_username || 'admin';
      addMitraForm.source = 'dashboard';
      ktpPreview.value = null;
      transferProofPreview.value = null;
    }
  });
};

const copyToClipboard = (text, type) => {
  navigator.clipboard.writeText(text);
  copySuccessMsg.value = `Link Referral ${type} berhasil disalin!`;
  setTimeout(() => {
    copySuccessMsg.value = '';
  }, 3000);
};

const copyBankNumber = (accNo) => {
  navigator.clipboard.writeText(accNo);
  copySuccessMsg.value = `Nomor rekening ${accNo} berhasil disalin!`;
  setTimeout(() => {
    copySuccessMsg.value = '';
  }, 3000);
};

const formatRupiah = (val) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val || 0);
};
</script>

<template>
  <Head title="Dashboard Member Area - UNIVERSAL CARD CENTRAL" />

  <AdminLayout>
    <div class="space-y-6">
      <!-- Copy Link Toast Alert Banner -->
      <div v-if="copySuccessMsg" class="p-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 rounded-xl text-xs font-semibold flex items-center gap-2 animate-bounce">
        <Check class="w-4 h-4 text-emerald-500" />
        <span>{{ copySuccessMsg }}</span>
      </div>

      <!-- 0. Panel Aktivasi ID Member (Untuk Akun Free / Belum Aktif) -->
      <div
        v-if="activation_status !== 'active' && !is_admin"
        class="bg-gradient-to-br from-[#0F172A] via-[#1E293B] to-[#09090B] border border-[#D4AF37]/50 rounded-3xl p-6 shadow-xl text-white space-y-5"
      >
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-white/10 pb-4">
          <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-[#D4AF37]/20 border border-[#D4AF37]/40 text-[#E5C07B] flex items-center justify-center shrink-0">
              <KeyRound class="w-5 h-5" />
            </div>
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="text-base font-black tracking-tight text-white">Status ID: Belum Aktif (Akun Free)</h3>
                <span class="px-2.5 py-0.5 text-[10px] font-black uppercase rounded-full bg-amber-500/20 text-amber-300 border border-amber-400/40">
                  {{ activation_status === 'pending' ? 'Menunggu Verifikasi Admin' : 'Perlu Aktivasi PIN' }}
                </span>
              </div>
              <p class="text-xs text-slate-300 mt-1">
                Masukkan <strong>Kode PIN Aktivasi</strong> (Bronze Rp 100.000 / Silver Rp 400.000 • Cashback Saldo Belanja Rp 50.000) dari Admin/Sponsor, atau unggah bukti pembayaran untuk mendapatkan PIN.
              </p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <!-- Form Input PIN Aktivasi -->
          <form @submit.prevent="submitSelfPinActivation" class="bg-white/5 border border-white/10 rounded-2xl p-4 space-y-3">
            <h4 class="text-xs font-black uppercase tracking-wider text-[#E5C07B]">1. Sudah Punya Kode PIN? Aktifkan ID Sekarang</h4>
            <div class="flex flex-col sm:flex-row gap-2.5">
              <input
                v-model="pinForm.pin_code"
                type="text"
                required
                placeholder="Masukkan Kode PIN (Contoh: UCCB-XXXXXXXX)"
                class="flex-1 px-3.5 py-2.5 bg-slate-900/80 border border-slate-700 rounded-xl text-xs font-mono font-bold text-white placeholder-slate-400 focus:outline-none focus:border-[#D4AF37]"
              />
              <button
                type="submit"
                :disabled="pinForm.processing"
                class="bg-gradient-to-r from-[#B8922E] via-[#D4AF37] to-[#F3E5AB] hover:opacity-95 text-slate-950 font-black text-xs uppercase tracking-wider py-2.5 px-5 rounded-xl shadow-md transition-all cursor-pointer disabled:opacity-50 shrink-0"
              >
                {{ pinForm.processing ? 'Memproses...' : 'Aktifkan ID' }}
              </button>
            </div>
            <p v-if="pinForm.errors.pin_code" class="text-[11px] font-bold text-rose-400">{{ pinForm.errors.pin_code }}</p>
          </form>

          <!-- Form Beli PIN & Upload Bukti Bayar ke Admin -->
          <form @submit.prevent="submitPinOrder" class="bg-white/5 border border-white/10 rounded-2xl p-4 space-y-3">
            <div class="flex items-center justify-between">
              <h4 class="text-xs font-black uppercase tracking-wider text-[#E5C07B]">2. Belum Punya PIN? Beli PIN &amp; Upload Bukti Bayar</h4>
              <span v-if="pending_pin_request" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-400/30">
                Order #{{ pending_pin_request.id }} Menunggu Admin
              </span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <select
                v-model="pinOrderForm.tier"
                class="px-3 py-2 bg-slate-900/80 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-[#D4AF37]"
              >
                <option value="bronze">PIN Bronze — Rp 100.000 (Non Cashback)</option>
                <option value="silver">PIN Silver — Rp 400.000 (Cashback Belanja Rp 50rb)</option>
              </select>
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp,application/pdf"
                required
                @change="(e) => pinOrderForm.transfer_proof = e.target.files[0]"
                class="px-2.5 py-1.5 bg-slate-900/80 border border-slate-700 rounded-xl text-[11px] text-slate-300 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-[#D4AF37] file:text-slate-950 cursor-pointer"
              />
            </div>
            <button
              type="submit"
              :disabled="pinOrderForm.processing"
              class="w-full bg-[#0F172A] hover:bg-slate-800 text-[#D4AF37] font-extrabold text-xs py-2.5 px-4 rounded-xl border border-[#D4AF37]/40 transition-all cursor-pointer disabled:opacity-50"
            >
              {{ pinOrderForm.processing ? 'Mengirim Bukti...' : 'Kirim Bukti Transfer ke Admin' }}
            </button>
            <p v-if="pinOrderForm.errors.transfer_proof" class="text-[11px] font-bold text-rose-400">{{ pinOrderForm.errors.transfer_proof }}</p>
          </form>
        </div>
      </div>

      <!-- 1. Pendaftaran Member Banner Card -->
      <div class="bg-gradient-to-r from-[#fdfbf7] via-[#faf6eb] to-[#f7f3e8] border border-[#D4AF37]/40 rounded-3xl p-5 md:p-6 shadow-sm relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start gap-4">
          <div class="p-3 bg-[#D4AF37]/15 text-[#B8922E] rounded-2xl shrink-0 hidden sm:block">
            <span class="text-xl font-bold">🔗</span>
          </div>
          <div>
            <h3 class="text-sm font-extrabold text-[#0F172A] tracking-tight">Pendaftaran Member</h3>
            <p class="text-xs text-slate-600 mt-1 font-medium">Daftarkan member baru secara langsung dari dashboard.</p>
          </div>
        </div>

        <div class="flex items-center flex-wrap gap-2.5 shrink-0">
          <button
            @click="copyToClipboard(referral_links?.default || referral_links?.url, 'Referral')"
            class="px-4 py-2.5 bg-gradient-to-r from-[#D4AF37] to-[#B8922E] hover:from-[#E5C07B] hover:to-[#D4AF37] text-slate-950 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 cursor-pointer shadow-sm"
          >
            <Copy class="w-3.5 h-3.5" />
            <span>Copy Link Referral</span>
          </button>
          
          <Link
            :href="route('admin.voucher-wallet.index')"
            class="px-4 py-2.5 bg-white hover:bg-slate-50 text-slate-800 border border-[#D4AF37]/40 text-xs font-extrabold rounded-xl transition-all flex items-center gap-1.5 shadow-xs"
          >
            <KeyRound class="w-3.5 h-3.5 text-[#B8922E]" />
            <span>Stok PIN ({{ wallet?.voucher_aktif ?? 0 }})</span>
          </Link>

          <Link
            :href="route('admin.activation.index')"
            class="px-4 py-2.5 bg-[#0F172A] hover:bg-[#1E293B] text-[#D4AF37] border border-[#D4AF37]/40 text-xs font-black rounded-xl transition-all flex items-center gap-2 shadow-md hover:shadow-lg"
          >
            <UserPlus class="w-4 h-4 stroke-[2.5]" />
            <span>+ Tambah Member</span>
          </Link>
        </div>
      </div>

      <!-- 2. Main Dashboard Cards Grid (TOTAL SALDO MEMBER & TEAM MEMBER) -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Total Saldo Card -->
        <div class="bg-gradient-to-br from-[#0F172A] via-[#1E293B] to-[#0F172A] text-white rounded-3xl p-6 relative overflow-hidden shadow-xl space-y-4 border border-[#D4AF37]/40 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#D4AF37] flex items-center gap-1.5">
                <Wallet class="w-3.5 h-3.5 text-[#D4AF37]" />
                SALDO TERSEDIA SAAT INI
              </span>
            </div>

            <div class="mt-2">
              <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight">{{ formatRupiah(wallet?.total_saldo ?? 0) }}</h2>
            </div>
          </div>

          <div class="pt-3 border-t border-white/10 flex items-center justify-between gap-2">
            <div class="text-[11px]">
              <span class="text-slate-300 block font-medium">Status Wallet</span>
              <span class="font-bold text-[#D4AF37]">Terverifikasi</span>
            </div>
            <Link 
              :href="route('admin.withdrawals.index')" 
              class="px-4 py-2 bg-gradient-to-r from-[#D4AF37] to-[#B8922E] hover:from-[#E5C07B] hover:to-[#D4AF37] text-slate-950 text-xs font-black rounded-xl shadow-md flex items-center gap-1.5 transition-all cursor-pointer"
            >
              <span>Penarikan</span>
              <ArrowUpRight class="w-4 h-4" />
            </Link>
          </div>
        </div>

        <!-- Network Summary Card -->
        <div class="bg-white border border-slate-200/80 rounded-3xl p-6 shadow-sm flex flex-col justify-between space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
              <Users class="w-5 h-5 text-[#0F172A]" />
              <h3 class="text-sm font-extrabold text-slate-900 tracking-tight">Team Member</h3>
            </div>
            <Link :href="route('admin.team')" class="text-xs font-bold text-[#B8922E] hover:text-[#0F172A] hover:underline flex items-center gap-1 transition-colors">
              <span>Rincian Team</span>
              <ArrowUpRight class="w-3.5 h-3.5" />
            </Link>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
            <div class="p-4 rounded-2xl border border-slate-100 bg-[#fdfbf7]">
              <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Member Direct (Gen 1)</p>
              <h4 class="text-2xl font-black text-[#0F172A] mt-1">{{ wallet?.direct_downlines ?? 0 }} <span class="text-xs font-normal text-slate-500">Orang</span></h4>
            </div>

            <div class="p-4 rounded-2xl border border-slate-100 bg-[#faf6eb]">
              <p class="text-[10px] font-extrabold text-[#B8922E] uppercase tracking-wider">Total Team (Gen 1-10)</p>
              <h4 class="text-2xl font-black text-[#B8922E] mt-1">{{ wallet?.total_downlines ?? 0 }} <span class="text-xs font-normal text-slate-500">Orang</span></h4>
            </div>
          </div>
        </div>

      </div>

      <!-- 3. Ringkasan UCC: Tier, Progress 1.400 Point & 4 Post Saldo -->
      <div class="bg-gradient-to-br from-[#09090B] via-[#121318] to-[#1A1B22] border border-[#D4AF37]/35 rounded-3xl p-5 md:p-6 shadow-xl text-white space-y-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-white/10 pb-4">
          <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-[#D4AF37]/15 border border-[#D4AF37]/40 text-[#E5C07B] flex items-center justify-center shrink-0">
              <Crown class="w-5 h-5" />
            </div>
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <h3 class="text-base font-black tracking-tight text-white">
                  Tier {{ ucc_summary?.member_tier_label || 'Bronze' }}
                </h3>
                <span v-if="ucc_summary?.has_silver_visa_card" class="px-2.5 py-0.5 text-[10px] font-black bg-[#D4AF37]/20 text-[#F3E5AB] border border-[#D4AF37]/50 rounded-full uppercase tracking-wider">
                  Card Visa Belanja Silver
                </span>
                <span v-if="ucc_summary?.has_nfc_card" class="px-2.5 py-0.5 text-[10px] font-black bg-slate-800 text-[#D4AF37] border border-[#D4AF37]/30 rounded-full uppercase tracking-wider">
                  NFC Card Aktif
                </span>
              </div>
              <p class="text-xs text-slate-400 mt-0.5">
                Poin Saat Ini: <strong class="text-[#E5C07B]">{{ ucc_summary?.points ?? 0 }}</strong> / {{ ucc_summary?.upgrade_target_points ?? 1400 }} Point (Auto-Upgrade Silver)
              </p>
            </div>
          </div>

          <div class="w-full md:w-64 space-y-1.5">
            <div class="flex items-center justify-between text-[11px] font-bold">
              <span class="text-slate-300">Progress Auto-Upgrade Silver</span>
              <span class="text-[#E5C07B]">{{ ucc_summary?.upgrade_progress ?? 0 }}%</span>
            </div>
            <div class="w-full bg-white/10 rounded-full h-2 overflow-hidden">
              <div
                class="bg-gradient-to-r from-[#B8922E] via-[#D4AF37] to-[#F3E5AB] h-2 rounded-full transition-all duration-500"
                :style="{ width: Math.min(100, ucc_summary?.upgrade_progress ?? 0) + '%' }"
              ></div>
            </div>
          </div>
        </div>

        <!-- 4 Post Saldo Mitra (Rp 100.000 per kelipatan 200 point; Rp 100.000 lainnya dikelola Post Induk) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="bg-white/5 border border-white/10 rounded-2xl p-3.5">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Saldo Tunai (40%)</span>
            <p class="text-base sm:text-lg font-black text-white mt-1">{{ formatRupiah(ucc_summary?.saldo_tunai ?? 0) }}</p>
          </div>
          <div class="bg-white/5 border border-white/10 rounded-2xl p-3.5">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Saldo Belanja (30%)</span>
            <p class="text-base sm:text-lg font-black text-white mt-1">{{ formatRupiah(ucc_summary?.saldo_belanja ?? 0) }}</p>
          </div>
          <div class="bg-white/5 border border-white/10 rounded-2xl p-3.5">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Saldo Upgrade / NFC (20%)</span>
            <p class="text-base sm:text-lg font-black text-[#E5C07B] mt-1">{{ formatRupiah(ucc_summary?.saldo_upgrade ?? 0) }}</p>
          </div>
          <div class="bg-white/5 border border-white/10 rounded-2xl p-3.5">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Saldo Reward (10%)</span>
            <p class="text-base sm:text-lg font-black text-white mt-1">{{ formatRupiah(ucc_summary?.saldo_reward ?? 0) }}</p>
          </div>
        </div>
      </div>

      <!-- 4. Catatan Referral Langsung -->
      <div class="bg-white border border-slate-200/80 rounded-3xl p-5 md:p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-sm font-black text-slate-900 tracking-tight">Catatan Referral Langsung</h3>
            <p class="text-xs text-slate-500">Daftar mitra yang mendaftar langsung menggunakan kode referral Anda.</p>
          </div>
          <span class="bg-[#faf6eb] text-[#B8922E] border border-[#D4AF37]/30 text-[11px] font-bold px-3 py-1 rounded-full">
            {{ (direct_referrals || []).length }} Referral
          </span>
        </div>

        <div v-if="(direct_referrals || []).length > 0" class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400">
                <th class="py-2.5 pr-4">Nama Member</th>
                <th class="py-2.5 pr-4">Username</th>
                <th class="py-2.5 pr-4">Status ID</th>
                <th class="py-2.5 pr-4">Tier / Kaki</th>
                <th class="py-2.5 pr-4">Nilai Aktivasi</th>
                <th class="py-2.5 pr-4">Tanggal Gabung</th>
                <th class="py-2.5 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="ref in direct_referrals" :key="ref.id" class="hover:bg-slate-50/70">
                <td class="py-2.5 pr-4 font-bold text-slate-900">{{ ref.name }}</td>
                <td class="py-2.5 pr-4 text-slate-600">@{{ ref.username }}</td>
                <td class="py-2.5 pr-4">
                  <span
                    v-if="ref.activation_status === 'active'"
                    class="bg-[#0F172A] text-[#D4AF37] border border-[#D4AF37]/30 text-[10px] font-bold px-2.5 py-0.5 rounded-full"
                  >
                    Aktif
                  </span>
                  <span
                    v-else
                    class="bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold px-2.5 py-0.5 rounded-full"
                  >
                    {{ ref.activation_status === 'pending' ? 'Menunggu' : 'Belum Aktif' }}
                  </span>
                </td>
                <td class="py-2.5 pr-4">
                  <span class="bg-[#faf6eb] text-[#B8922E] border border-[#D4AF37]/30 text-[10px] font-bold px-2.5 py-0.5 rounded-full">
                    {{ ref.member_tier }}<template v-if="ref.position"> • {{ ref.position === 'left' ? 'Kiri' : 'Kanan' }}</template>
                  </span>
                </td>
                <td class="py-2.5 pr-4 font-bold text-slate-800">{{ formatRupiah(ref.activation_amount) }}</td>
                <td class="py-2.5 pr-4 text-slate-500">{{ ref.joined_at }}</td>
                <td class="py-2.5 text-right">
                  <button
                    v-if="ref.activation_status !== 'active' && (vouchers || []).length > 0"
                    type="button"
                    @click="openActivateDownlineModal(ref)"
                    class="px-3 py-1 bg-gradient-to-r from-[#B8922E] via-[#D4AF37] to-[#F3E5AB] text-slate-950 font-black text-[10px] uppercase rounded-lg cursor-pointer shadow-xs hover:opacity-95"
                  >
                    Aktifkan dgn PIN
                  </button>
                  <span v-else class="text-[11px] text-slate-400">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-6 text-xs text-slate-400 font-medium">
          Belum ada catatan referral langsung. Bagikan link referral Anda di atas untuk mulai merekrut mitra.
        </div>
      </div>

    </div>

    <!-- Modal Form Tambah Member Baru (Quick Add Member from Dashboard) -->
    <div v-if="isAddMitraModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in overflow-y-auto">
      <div class="bg-white rounded-3xl p-6 md:p-8 max-w-2xl w-full shadow-2xl space-y-5 border border-slate-100 my-8 max-h-[90vh] overflow-y-auto">
        
        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div class="flex items-center gap-2.5">
            <div class="p-2.5 bg-[#D4AF37]/15 text-[#B8922E] rounded-2xl">
              <UserPlus class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-base font-extrabold text-slate-900">Tambah Member Baru</h3>
            </div>
          </div>
          <button @click="isAddMitraModalOpen = false" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition-colors cursor-pointer">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Add Mitra Form -->
        <form @submit.prevent="submitAddMitra" class="space-y-4">
          
          <!-- SECTION 1: DATA PRIBADI & KONTAK -->
          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-4 space-y-3.5">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
              <User class="w-3.5 h-3.5" />
              <span>1. Data Pribadi</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Nama Lengkap -->
              <div class="form-group sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Nama Lengkap <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><User class="w-4 h-4 text-slate-400" /></span>
                  <input type="text" v-model="addMitraForm.name" required placeholder="Nama Lengkap Member" class="w-full text-sm" />
                </div>
                <p v-if="addMitraForm.errors.name" class="text-xs text-rose-500 font-medium mt-1">{{ addMitraForm.errors.name }}</p>
              </div>

              <!-- Whatsapp -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Whatsapp <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><Phone class="w-4 h-4 text-slate-400" /></span>
                  <input type="tel" v-model="addMitraForm.phone" required placeholder="Contoh: 081234567890" class="w-full text-sm" />
                </div>
                <p v-if="addMitraForm.errors.phone" class="text-xs text-rose-500 font-medium mt-1">{{ addMitraForm.errors.phone }}</p>
              </div>

              <!-- Email -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Email <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><Mail class="w-4 h-4 text-slate-400" /></span>
                  <input type="email" v-model="addMitraForm.email" required placeholder="nama@email.com" class="w-full text-sm" />
                </div>
                <p v-if="addMitraForm.errors.email" class="text-xs text-rose-500 font-medium mt-1">{{ addMitraForm.errors.email }}</p>
              </div>

              <!-- Kidal -->
              <div class="form-group sm:col-span-2 pt-1">
                <label class="block text-xs font-bold text-slate-700 mb-2">
                  Apakah dominan tangan kiri (Kidal) ? <span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                  <label :class="['flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all', addMitraForm.is_left_handed === 'Iya' ? 'border-[#D4AF37] bg-[#D4AF37]/10 font-bold text-slate-900 shadow-sm' : 'border-slate-200 bg-white text-slate-700']">
                    <input type="radio" value="Iya" v-model="addMitraForm.is_left_handed" class="w-4 h-4 accent-[#0F172A]" />
                    <span class="text-xs">Iya</span>
                  </label>
                  <label :class="['flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all', addMitraForm.is_left_handed === 'Tidak' ? 'border-[#D4AF37] bg-[#D4AF37]/10 font-bold text-slate-900 shadow-sm' : 'border-slate-200 bg-white text-slate-700']">
                    <input type="radio" value="Tidak" v-model="addMitraForm.is_left_handed" class="w-4 h-4 accent-[#0F172A]" />
                    <span class="text-xs">Tidak</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- SECTION 2: DATA AHLI WARIS & KONTAK DARURAT -->
          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-4 space-y-3.5">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
              <Users class="w-3.5 h-3.5" />
              <span>2. Data Ahli Waris & Kontak Darurat</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Nama Ahli Waris -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Nama Ahli Waris <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><User class="w-4 h-4 text-slate-400" /></span>
                  <input type="text" v-model="addMitraForm.beneficiary_name" required placeholder="Nama Lengkap Ahli Waris" class="w-full text-sm" />
                </div>
                <p v-if="addMitraForm.errors.beneficiary_name" class="text-xs text-rose-500 font-medium mt-1">{{ addMitraForm.errors.beneficiary_name }}</p>
              </div>

              <!-- Tanggal Lahir Ahli Waris -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Tanggal Lahir Ahli Waris <span class="text-rose-500">*</span>
                </label>
                <BirthDatePicker
                  v-model="addMitraForm.beneficiary_birth_date"
                  :required="true"
                />
                <p v-if="addMitraForm.errors.beneficiary_birth_date" class="text-xs text-rose-500 font-medium mt-1">{{ addMitraForm.errors.beneficiary_birth_date }}</p>
              </div>

              <!-- Hubungan dengan Ahli Waris -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Hubungan dengan Ahli Waris <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><Users class="w-4 h-4 text-slate-400" /></span>
                  <input type="text" v-model="addMitraForm.beneficiary_relation" required placeholder="Contoh: Anak / Pasangan / Orang Tua" class="w-full text-sm" />
                </div>
                <p v-if="addMitraForm.errors.beneficiary_relation" class="text-xs text-rose-500 font-medium mt-1">{{ addMitraForm.errors.beneficiary_relation }}</p>
              </div>

              <!-- Nomor Whatsapp Kontak Darurat -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Whatsapp Kontak Darurat <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><AlertCircle class="w-4 h-4 text-slate-400" /></span>
                  <input type="tel" v-model="addMitraForm.emergency_phone" required placeholder="Nomor Kontak Darurat" class="w-full text-sm" />
                </div>
                <p v-if="addMitraForm.errors.emergency_phone" class="text-xs text-rose-500 font-medium mt-1">{{ addMitraForm.errors.emergency_phone }}</p>
              </div>
            </div>
          </div>

          <!-- SECTION 3: IDENTITAS -->
          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-4 space-y-4">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
              <FileText class="w-3.5 h-3.5" />
              <span>3. Identitas</span>
            </div>

            <!-- ID KTP Upload -->
            <div class="form-group pt-1">
              <div class="flex items-center justify-between mb-1">
                <label class="text-xs font-bold text-slate-700">ID KTP <span class="text-rose-500">*</span></label>
                <span class="text-[10px] text-slate-400 font-medium">Maks 10 MB (JPG, PNG, PDF)</span>
              </div>

              <div 
                v-if="!addMitraForm.ktp_image"
                @click="$refs.fileInput.click()"
                class="border-2 border-dashed border-slate-300 hover:border-[#D4AF37] rounded-xl p-4 text-center cursor-pointer transition-colors bg-white group flex flex-col items-center justify-center gap-2"
              >
                <div class="w-9 h-9 rounded-full bg-slate-100 group-hover:bg-[#D4AF37]/10 flex items-center justify-center text-slate-500 group-hover:text-[#D4AF37] transition-colors">
                  <UploadCloud class="w-4.5 h-4.5" />
                </div>
                <div class="text-xs font-bold text-slate-700 group-hover:text-slate-900">Tambahkan file KTP</div>
                <div class="text-[11px] text-slate-400">Klik untuk memilih file foto KTP member</div>
              </div>

              <!-- Preview KTP -->
              <div v-else class="p-3 bg-white border border-[#D4AF37]/40 rounded-xl flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3 overflow-hidden">
                  <div v-if="ktpPreview && ktpPreview !== 'document'" class="w-10 h-10 rounded-lg overflow-hidden border border-slate-200 flex-shrink-0">
                    <img :src="ktpPreview" alt="KTP Preview" class="w-full h-full object-cover" />
                  </div>
                  <div v-else class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                    <FileText class="w-5 h-5" />
                  </div>
                  <div class="overflow-hidden">
                    <p class="text-xs font-bold text-slate-800 truncate max-w-[180px]">{{ addMitraForm.ktp_image.name }}</p>
                    <p class="text-[10px] text-emerald-600 font-medium flex items-center gap-1">
                      <Check class="w-3 h-3" /> Siap diunggah ({{ (addMitraForm.ktp_image.size / 1024 / 1024).toFixed(2) }} MB)
                    </p>
                  </div>
                </div>
                <button type="button" @click="removeFile" class="p-1.5 text-slate-400 hover:text-rose-500 rounded-lg hover:bg-rose-50 transition-colors cursor-pointer">
                  <X class="w-4 h-4" />
                </button>
              </div>

              <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" @change="handleFileUpload" class="hidden" />
              <p v-if="addMitraForm.errors.ktp_image" class="text-xs text-rose-500 font-medium mt-1">{{ addMitraForm.errors.ktp_image }}</p>
            </div>

            <!-- Bukti Transfer Upload -->
            <div class="form-group pt-2 border-t border-slate-200/60">
              <div class="flex items-center justify-between mb-1">
                <label class="text-xs font-bold text-slate-700">
                  Bukti Transfer <span class="text-rose-500">*</span>
                </label>
                <span class="text-[10px] text-slate-400 font-medium">Maks 10 MB (JPG, PNG, PDF)</span>
              </div>

              <div class="mb-3 p-4 sm:p-5 bg-gradient-to-r from-[#fffbeb] via-[#faf6eb] to-[#fffbeb] border-2 border-[#D4AF37] rounded-2xl shadow-sm space-y-2.5">
                <div class="flex items-center justify-between gap-2 border-b border-[#D4AF37]/30 pb-2">
                  <span class="text-xs sm:text-base font-black text-slate-950 uppercase tracking-tight">Rekening Perusahaan:</span>
                  <span class="text-sm sm:text-lg font-black text-[#B8922E] font-mono whitespace-nowrap shrink-0">Rp 500.000</span>
                </div>
                <div class="text-sm sm:text-base text-slate-900 font-black flex flex-wrap items-center gap-x-2.5 gap-y-1.5 pt-0.5">
                  <span class="font-black text-slate-900">Bank BCA:</span>
                  <span class="font-mono font-black text-base sm:text-lg text-slate-950 px-2.5 py-1 bg-white border-2 border-[#D4AF37]/70 rounded-lg shadow-2xs tracking-wider">172-666-2020</span>
                  <span class="text-slate-700 font-bold text-xs sm:text-sm">a/n</span>
                  <strong class="font-black text-slate-950 uppercase tracking-wide text-sm sm:text-base">UNIVERSAL CARD CENTRAL</strong>
                </div>
              </div>

              <div 
                v-if="!addMitraForm.transfer_proof"
                @click="$refs.transferProofFileInput.click()"
                class="border-2 border-dashed border-slate-300 hover:border-[#D4AF37] rounded-xl p-4 text-center cursor-pointer transition-colors bg-white group flex flex-col items-center justify-center gap-2"
              >
                <div class="w-9 h-9 rounded-full bg-slate-100 group-hover:bg-[#D4AF37]/10 flex items-center justify-center text-slate-500 group-hover:text-[#D4AF37] transition-colors">
                  <CreditCard class="w-4.5 h-4.5" />
                </div>
                <div class="text-xs font-bold text-slate-700 group-hover:text-slate-900">Tambahkan Bukti Transfer</div>
                <div class="text-[11px] text-slate-400">Klik untuk memilih foto bukti transfer pembayaran</div>
              </div>

              <!-- Preview Bukti Transfer -->
              <div v-else class="p-3 bg-white border border-[#D4AF37]/40 rounded-xl flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3 overflow-hidden">
                  <div v-if="transferProofPreview && transferProofPreview !== 'document'" class="w-10 h-10 rounded-lg overflow-hidden border border-slate-200 flex-shrink-0">
                    <img :src="transferProofPreview" alt="Transfer Proof Preview" class="w-full h-full object-cover" />
                  </div>
                  <div v-else class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                    <CreditCard class="w-5 h-5" />
                  </div>
                  <div class="overflow-hidden">
                    <p class="text-xs font-bold text-slate-800 truncate max-w-[180px]">{{ addMitraForm.transfer_proof.name }}</p>
                    <p class="text-[10px] text-emerald-600 font-medium flex items-center gap-1">
                      <Check class="w-3 h-3" /> Bukti transfer siap diunggah ({{ (addMitraForm.transfer_proof.size / 1024 / 1024).toFixed(2) }} MB)
                    </p>
                  </div>
                </div>
                <button type="button" @click="removeTransferProof" class="p-1.5 text-slate-400 hover:text-rose-500 rounded-lg hover:bg-rose-50 transition-colors cursor-pointer">
                  <X class="w-4 h-4" />
                </button>
              </div>

              <input ref="transferProofFileInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" @change="handleTransferProofUpload" class="hidden" />
              <p v-if="addMitraForm.errors.transfer_proof" class="text-xs text-rose-500 font-medium mt-1">{{ addMitraForm.errors.transfer_proof }}</p>
            </div>
          </div>

          <!-- SECTION 4: KEAMANAN -->
          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-4 space-y-3.5">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
              <Lock class="w-3.5 h-3.5" />
              <span>4. Keamanan</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- Password -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Password <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><Lock class="w-4 h-4 text-slate-400" /></span>
                  <input :type="showPassword ? 'text' : 'password'" v-model="addMitraForm.password" required placeholder="Minimal 8 karakter" class="w-full text-sm" />
                  <button type="button" @click="showPassword = !showPassword" class="auth-password-toggle flex items-center justify-center">
                    <Eye v-if="!showPassword" class="w-4 h-4" />
                    <EyeOff v-else class="w-4 h-4" />
                  </button>
                </div>
                <p v-if="addMitraForm.errors.password" class="text-xs text-rose-500 font-medium mt-1">{{ addMitraForm.errors.password }}</p>
              </div>

              <!-- Konfirmasi Password -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Konfirmasi Password <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><Lock class="w-4 h-4 text-slate-400" /></span>
                  <input :type="showPasswordConfirm ? 'text' : 'password'" v-model="addMitraForm.password_confirmation" required placeholder="Ulangi password" class="w-full text-sm" />
                  <button type="button" @click="showPasswordConfirm = !showPasswordConfirm" class="auth-password-toggle flex items-center justify-center">
                    <Eye v-if="!showPasswordConfirm" class="w-4 h-4" />
                    <EyeOff v-else class="w-4 h-4" />
                  </button>
                </div>
                <p v-if="addMitraForm.errors.password_confirmation" class="text-xs text-rose-500 font-medium mt-1">{{ addMitraForm.errors.password_confirmation }}</p>
              </div>
            </div>
          </div>

          <!-- SECTION 5: DIREFERENSI OLEH -->
          <div class="p-4 bg-[#faf6eb] border border-[#D4AF37]/40 rounded-2xl space-y-2">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-[#D4AF37]/30 pb-2">
              <KeyRound class="w-3.5 h-3.5" />
              <span>Direferensi oleh:</span>
            </div>
            <div v-if="is_admin && all_sponsors && all_sponsors.length > 0">
              <select v-model="addMitraForm.sponsor_username" class="w-full bg-white border border-[#D4AF37]/40 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:border-[#D4AF37]">
                <option v-for="s in all_sponsors" :key="s.username" :value="s.username">{{ s.label }}</option>
              </select>
            </div>
            <div v-else class="flex items-center justify-between bg-white px-3.5 py-2.5 border border-[#D4AF37]/30 rounded-xl">
              <span class="text-xs font-extrabold text-[#0F172A]">@{{ addMitraForm.sponsor_username }}</span>
            </div>
          </div>

          <!-- Modal Actions -->
          <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
            <button type="button" @click="isAddMitraModalOpen = false" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl cursor-pointer">
              Batal
            </button>
            <button type="submit" :disabled="addMitraForm.processing" class="auth-primary-btn !w-auto !h-auto px-6 py-2.5 text-xs font-black uppercase tracking-wider rounded-xl shadow-md cursor-pointer disabled:opacity-50 flex items-center gap-2">
              <span v-if="addMitraForm.processing">Mendaftarkan...</span>
              <span v-else>Daftarkan Member Sekarang</span>
              <Check class="w-4 h-4 stroke-[3]" />
            </button>
          </div>

        </form>
      </div>
    </div>

    <!-- Modal Aktifkan Downline Free dengan Stok PIN -->
    <div v-if="activateDownlineModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-sm">
      <div class="bg-white border border-[#D4AF37]/40 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden">
        <div class="bg-gradient-to-r from-[#0F172A] to-[#1E293B] px-6 py-4 flex items-center justify-between text-white border-b border-[#D4AF37]/30">
          <div>
            <h3 class="text-sm font-black tracking-tight">Aktifkan Member dengan PIN</h3>
            <p class="text-[11px] text-[#E5C07B] mt-0.5">{{ activateDownlineModal.member?.name }} (@{{ activateDownlineModal.member?.username }})</p>
          </div>
          <button type="button" @click="activateDownlineModal.open = false" class="p-1.5 rounded-lg hover:bg-white/10 text-slate-300 cursor-pointer">
            <X class="w-4 h-4" />
          </button>
        </div>

        <form @submit.prevent="submitActivateDownline" class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilih Stok PIN Anda (Bronze / Silver)</label>
            <select
              v-model="activateDownlineForm.pin_code"
              required
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:border-[#D4AF37]"
            >
              <option v-for="v in vouchers" :key="v.code" :value="v.code">{{ v.label }}</option>
            </select>
            <p v-if="activateDownlineForm.errors.pin_code" class="text-[11px] text-rose-600 font-bold mt-1">{{ activateDownlineForm.errors.pin_code }}</p>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Penempatan Jaringan 2 Kaki</label>
            <div class="grid grid-cols-2 gap-2.5">
              <label
                class="cursor-pointer rounded-xl border p-3 text-center text-xs font-black transition-all"
                :class="activateDownlineForm.position === 'left' ? 'border-[#D4AF37] bg-[#faf6eb] text-slate-900' : 'border-slate-200 bg-white text-slate-600'"
              >
                <input type="radio" v-model="activateDownlineForm.position" value="left" class="sr-only" />
                Kaki Kiri
              </label>
              <label
                class="cursor-pointer rounded-xl border p-3 text-center text-xs font-black transition-all"
                :class="activateDownlineForm.position === 'right' ? 'border-[#D4AF37] bg-[#faf6eb] text-slate-900' : 'border-slate-200 bg-white text-slate-600'"
              >
                <input type="radio" v-model="activateDownlineForm.position" value="right" class="sr-only" />
                Kaki Kanan
              </label>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
            <button type="button" @click="activateDownlineModal.open = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl cursor-pointer">
              Batal
            </button>
            <button
              type="submit"
              :disabled="activateDownlineForm.processing"
              class="bg-gradient-to-r from-[#B8922E] via-[#D4AF37] to-[#F3E5AB] text-slate-950 font-black text-xs uppercase tracking-wider px-5 py-2.5 rounded-xl shadow-md cursor-pointer disabled:opacity-50"
            >
              {{ activateDownlineForm.processing ? 'Mengaktifkan...' : 'Aktifkan Sekarang' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

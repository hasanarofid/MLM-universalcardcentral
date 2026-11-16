<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import BirthDatePicker from '@/Components/BirthDatePicker.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
  UserPlus, 
  Check, 
  AlertCircle,
  User,
  Mail,
  Phone,
  Calendar,
  Users,
  CreditCard,
  UploadCloud,
  Lock,
  Eye,
  EyeOff,
  FileText,
  X,
  KeyRound
} from '@lucide/vue';

const props = defineProps({
  is_admin: Boolean,
  current_user_name: String,
  vouchers: {
    type: Array,
    default: () => []
  },
  pin_stocks: {
    type: Object,
    default: () => ({ bronze: 0, silver: 0 })
  },
  free_downlines: {
    type: Array,
    default: () => []
  },
  users: Array,
  default_sponsor: String,
});

const activationMode = ref('pin_quick'); // 'pin_quick' | 'activate_free' | 'ktp_full'

const pinQuickForm = useForm({
  pin_code: props.vouchers?.[0]?.code || '',
  position: 'left',
  name: '',
  email: '',
  phone: '',
  address: '',
  password: '',
  password_confirmation: '',
  sponsor_username: props.default_sponsor || 'admin',
});

const submitPinQuick = () => {
  pinQuickForm.post(route('admin.activation.store'), {
    preserveScroll: true,
    onSuccess: () => {
      pinQuickForm.reset('name', 'email', 'phone', 'address', 'password', 'password_confirmation');
    },
  });
};

const activateFreeForm = useForm({
  member_id: props.free_downlines?.[0]?.id || '',
  pin_code: props.vouchers?.[0]?.code || '',
  position: 'left',
});

const submitActivateFree = () => {
  activateFreeForm.post(route('admin.pin.activate-downline'), {
    preserveScroll: true,
    onSuccess: () => {
      activateFreeForm.reset('member_id');
    },
  });
};

const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const ktpPreview = ref(null);
const fileInput = ref(null);
const transferProofPreview = ref(null);
const transferProofFileInput = ref(null);

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

const form = useForm({
  phone: '',
  email: '',
  postal_code: '',
  member_tier: 'bronze',
  nik: '',
  name: '',
  birth_place: '',
  birth_date: '',
  gender: 'Laki-laki',
  blood_type: '-',
  address: '',
  rt_rw: '',
  village: '',
  district: '',
  city: '',
  province: '',
  religion: 'Islam',
  marital_status: 'Belum Kawin',
  beneficiary_name: '',
  beneficiary_birth_date: '',
  beneficiary_relation: '',
  emergency_phone: '',
  bank_name: '',
  bank_account_number: '',
  bank_account_name: '',
  ktp_image: null,
  transfer_proof: null,
  password: '',
  password_confirmation: '',
  sponsor_username: props.default_sponsor || 'admin',
});

const compressImage = (file, maxWidth = 1600, maxHeight = 1600, quality = 0.82) => {
  return new Promise((resolve) => {
    if (!file || !file.type.startsWith('image/')) {
      resolve(file);
      return;
    }

    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (e) => {
      const img = new Image();
      img.src = e.target.result;
      img.onload = () => {
        let width = img.width;
        let height = img.height;

        if (width > maxWidth || height > maxHeight) {
          if (width > height) {
            height = Math.round((height * maxWidth) / width);
            width = maxWidth;
          } else {
            width = Math.round((width * maxHeight) / height);
            width = maxHeight;
          }
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);

        canvas.toBlob(
          (blob) => {
            if (!blob) {
              resolve(file);
              return;
            }
            const compressedFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", {
              type: 'image/jpeg',
              lastModified: Date.now(),
            });
            resolve(compressedFile);
          },
          'image/jpeg',
          quality
        );
      };
      img.onerror = () => resolve(file);
    };
    reader.onerror = () => resolve(file);
  });
};

const handleFileUpload = async (e) => {
  const rawFile = e.target.files[0];
  if (!rawFile) return;

  if (rawFile.size > 15 * 1024 * 1024) {
    alert('Ukuran file maksimal adalah 15 MB');
    return;
  }

  const file = await compressImage(rawFile);
  form.ktp_image = file;
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
  form.ktp_image = null;
  ktpPreview.value = null;
  if (fileInput.value) {
    fileInput.value.value = '';
  }
};

const handleTransferProofUpload = async (e) => {
  const rawFile = e.target.files[0];
  if (!rawFile) return;

  if (rawFile.size > 15 * 1024 * 1024) {
    alert('Ukuran file maksimal adalah 15 MB');
    return;
  }

  const file = await compressImage(rawFile);
  form.transfer_proof = file;
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
  form.transfer_proof = null;
  transferProofPreview.value = null;
  if (transferProofFileInput.value) {
    transferProofFileInput.value.value = '';
  }
};

const submitForm = () => {
  if (!form.transfer_proof) {
    form.setError('transfer_proof', 'Bukti transfer pembayaran wajib diunggah.');
    return;
  }
  form.post(route('admin.activation.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      form.member_tier = 'bronze';
      form.gender = 'Laki-laki';
      form.blood_type = '-';
      form.religion = 'Islam';
      form.marital_status = 'Belum Kawin';
      form.bank_name = 'Bank BRI';
      form.sponsor_username = props.default_sponsor || 'admin';
      ktpPreview.value = null;
      transferProofPreview.value = null;
    }
  });
};
</script>

<template>
  <Head title="Pendaftaran Member Baru - UNIVERSAL CARD CENTRAL" />

  <AdminLayout>
    <div class="space-y-6 max-w-4xl mx-auto">
      
      <!-- Main Card Container -->
      <div class="bg-white border border-slate-200/80 rounded-3xl p-6 md:p-8 shadow-sm space-y-6">
        
        <!-- Header & Stok PIN -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
          <div class="space-y-1">
            <div class="flex items-center gap-2.5">
              <div class="p-2.5 bg-[#D4AF37]/15 text-[#B8922E] rounded-2xl">
                <UserPlus class="w-5 h-5" />
              </div>
              <div>
                <h2 class="text-lg font-black text-slate-900 tracking-tight">Pendaftaran &amp; Aktivasi PIN Member</h2>
                <p class="text-xs text-slate-500">Gunakan stok PIN Bronze / Silver untuk mendaftarkan member baru atau mengaktifkan member Free.</p>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2 flex-wrap">
            <span class="bg-[#faf6eb] text-[#B8922E] border border-[#D4AF37]/30 text-[11px] font-black px-3 py-1.5 rounded-xl">
              PIN Bronze: {{ pin_stocks?.bronze ?? 0 }}
            </span>
            <span class="bg-[#0F172A] text-[#D4AF37] border border-[#D4AF37]/30 text-[11px] font-black px-3 py-1.5 rounded-xl">
              PIN Silver: {{ pin_stocks?.silver ?? 0 }}
            </span>
            <Link
              :href="route('admin.voucher-wallet.index')"
              class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold rounded-xl transition-colors"
            >
              + Beli / Kelola PIN
            </Link>
          </div>
        </div>

        <!-- Mode Switcher Tabs -->
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            @click="activationMode = 'pin_quick'"
            :class="[
              'px-4 py-2 rounded-xl text-xs font-black transition-all cursor-pointer border',
              activationMode === 'pin_quick'
                ? 'bg-[#0F172A] text-[#D4AF37] border-[#D4AF37]/40 shadow-sm'
                : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
            ]"
          >
            1. Daftarkan Member Pakai Stok PIN
          </button>
          <button
            type="button"
            @click="activationMode = 'activate_free'"
            :class="[
              'px-4 py-2 rounded-xl text-xs font-black transition-all cursor-pointer border',
              activationMode === 'activate_free'
                ? 'bg-[#0F172A] text-[#D4AF37] border-[#D4AF37]/40 shadow-sm'
                : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
            ]"
          >
            2. Aktifkan Member Free ({{ free_downlines.length }})
          </button>
          <button
            type="button"
            @click="activationMode = 'ktp_full'"
            :class="[
              'px-4 py-2 rounded-xl text-xs font-black transition-all cursor-pointer border',
              activationMode === 'ktp_full'
                ? 'bg-[#0F172A] text-[#D4AF37] border-[#D4AF37]/40 shadow-sm'
                : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
            ]"
          >
            3. Input Lengkap 14 Field KTP
          </button>
        </div>

        <!-- TAB 1: DAFTAR PAKAI STOK PIN (RINGKAS + 2 KAKI) -->
        <form v-if="activationMode === 'pin_quick'" @submit.prevent="submitPinQuick" class="space-y-5 pt-1">
          <div v-if="vouchers.length === 0" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <span>Anda belum memiliki stok PIN aktif (Bronze / Silver). Silakan beli atau minta Admin mengirim PIN terlebih dahulu.</span>
            <Link
              :href="route('admin.voucher-wallet.index')"
              class="px-4 py-2 bg-[#0F172A] text-[#D4AF37] font-black text-[11px] uppercase rounded-xl shrink-0 text-center"
            >
              Buka Stok PIN &amp; Order
            </Link>
          </div>

          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-5 space-y-4">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
              <KeyRound class="w-3.5 h-3.5" />
              <span>PIN Aktivasi &amp; Posisi Jaringan 2 Kaki</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Stok PIN Aktivasi <span class="text-rose-500">*</span></label>
                <select
                  v-model="pinQuickForm.pin_code"
                  required
                  class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:border-[#D4AF37]"
                >
                  <option value="" disabled>-- Pilih PIN Bronze / Silver --</option>
                  <option v-for="v in vouchers" :key="v.code" :value="v.code">{{ v.label }}</option>
                </select>
                <InputError class="mt-1" :message="pinQuickForm.errors.pin_code" />
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Penempatan 2 Kaki <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-2 gap-2.5">
                  <label
                    class="cursor-pointer rounded-xl border p-2.5 text-center text-xs font-black transition-all"
                    :class="pinQuickForm.position === 'left' ? 'border-[#D4AF37] bg-[#faf6eb] text-slate-900' : 'border-slate-200 bg-white text-slate-600'"
                  >
                    <input type="radio" v-model="pinQuickForm.position" value="left" class="sr-only" />
                    Kaki Kiri
                  </label>
                  <label
                    class="cursor-pointer rounded-xl border p-2.5 text-center text-xs font-black transition-all"
                    :class="pinQuickForm.position === 'right' ? 'border-[#D4AF37] bg-[#faf6eb] text-slate-900' : 'border-slate-200 bg-white text-slate-600'"
                  >
                    <input type="radio" v-model="pinQuickForm.position" value="right" class="sr-only" />
                    Kaki Kanan
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-5 space-y-4">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
              <User class="w-3.5 h-3.5" />
              <span>Data Dasar Member Baru (Profil KTP &amp; Bank Dilengkapi Setelah Login)</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                <input v-model="pinQuickForm.name" type="text" required placeholder="Nama Lengkap" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-[#D4AF37]" />
                <InputError class="mt-1" :message="pinQuickForm.errors.name" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">No. HP / WhatsApp <span class="text-rose-500">*</span></label>
                <input v-model="pinQuickForm.phone" type="tel" required placeholder="081234567890" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-[#D4AF37]" />
                <InputError class="mt-1" :message="pinQuickForm.errors.phone" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Email <span class="text-rose-500">*</span></label>
                <input v-model="pinQuickForm.email" type="email" required placeholder="email@contoh.com" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-[#D4AF37]" />
                <InputError class="mt-1" :message="pinQuickForm.errors.email" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Alamat Lengkap <span class="text-rose-500">*</span></label>
                <input v-model="pinQuickForm.address" type="text" required placeholder="Alamat domisili saat ini" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-[#D4AF37]" />
                <InputError class="mt-1" :message="pinQuickForm.errors.address" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Password Login <span class="text-rose-500">*</span></label>
                <input v-model="pinQuickForm.password" type="password" required minlength="8" placeholder="Minimal 8 karakter" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-[#D4AF37]" />
                <InputError class="mt-1" :message="pinQuickForm.errors.password" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Konfirmasi Password <span class="text-rose-500">*</span></label>
                <input v-model="pinQuickForm.password_confirmation" type="password" required minlength="8" placeholder="Ulangi password" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-[#D4AF37]" />
              </div>
            </div>

            <div v-if="is_admin">
              <label class="block text-xs font-bold text-slate-700 mb-1">Sponsor Langsung</label>
              <select v-model="pinQuickForm.sponsor_username" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-[#D4AF37]">
                <option v-for="u in users" :key="u.username" :value="u.username">{{ u.label }}</option>
              </select>
            </div>
          </div>

          <button
            type="submit"
            :disabled="pinQuickForm.processing || vouchers.length === 0"
            class="w-full bg-gradient-to-r from-[#B8922E] via-[#D4AF37] to-[#F3E5AB] hover:opacity-95 text-slate-950 font-black text-xs uppercase tracking-wider py-3.5 px-6 rounded-2xl shadow-md transition-all cursor-pointer disabled:opacity-50"
          >
            {{ pinQuickForm.processing ? 'Memproses Aktivasi PIN...' : 'Daftarkan & Aktifkan Member dengan PIN' }}
          </button>
        </form>

        <!-- TAB 2: AKTIFKAN MEMBER FREE -->
        <form v-else-if="activationMode === 'activate_free'" @submit.prevent="submitActivateFree" class="space-y-5 pt-1">
          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-5 space-y-4">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
              <KeyRound class="w-3.5 h-3.5" />
              <span>Aktifkan Akun Member Free Menggunakan Stok PIN Anda</span>
            </div>

            <div v-if="free_downlines.length === 0" class="text-xs text-slate-500 py-4 text-center">
              Tidak ada member berstatus Belum Aktif di jaringan Anda saat ini.
            </div>
            <div v-else class="space-y-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Member Free (Belum Aktif) <span class="text-rose-500">*</span></label>
                <select v-model="activateFreeForm.member_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-[#D4AF37]">
                  <option v-for="m in free_downlines" :key="m.id" :value="m.id">{{ m.label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Stok PIN Anda <span class="text-rose-500">*</span></label>
                <select v-model="activateFreeForm.pin_code" required class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-[#D4AF37]">
                  <option v-for="v in vouchers" :key="v.code" :value="v.code">{{ v.label }}</option>
                </select>
                <InputError class="mt-1" :message="activateFreeForm.errors.pin_code" />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Penempatan 2 Kaki</label>
                <div class="grid grid-cols-2 gap-2.5">
                  <label class="cursor-pointer rounded-xl border p-2.5 text-center text-xs font-black" :class="activateFreeForm.position === 'left' ? 'border-[#D4AF37] bg-[#faf6eb] text-slate-900' : 'border-slate-200 bg-white text-slate-600'">
                    <input type="radio" v-model="activateFreeForm.position" value="left" class="sr-only" />
                    Kaki Kiri
                  </label>
                  <label class="cursor-pointer rounded-xl border p-2.5 text-center text-xs font-black" :class="activateFreeForm.position === 'right' ? 'border-[#D4AF37] bg-[#faf6eb] text-slate-900' : 'border-slate-200 bg-white text-slate-600'">
                    <input type="radio" v-model="activateFreeForm.position" value="right" class="sr-only" />
                    Kaki Kanan
                  </label>
                </div>
              </div>
              <button
                type="submit"
                :disabled="activateFreeForm.processing || vouchers.length === 0"
                class="w-full bg-gradient-to-r from-[#B8922E] via-[#D4AF37] to-[#F3E5AB] hover:opacity-95 text-slate-950 font-black text-xs uppercase tracking-wider py-3.5 px-6 rounded-2xl shadow-md transition-all cursor-pointer disabled:opacity-50"
              >
                {{ activateFreeForm.processing ? 'Mengaktifkan...' : 'Aktifkan Member Sekarang' }}
              </button>
            </div>
          </div>
        </form>

        <!-- TAB 3: FORM LENGKAP 14 FIELD KTP -->
        <form v-else @submit.prevent="submitForm" class="space-y-5 pt-1">
          
          <!-- SECTION 0: KONTAK & PAKET AKTIVASI (PALING ATAS) -->
          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-5 space-y-4">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
              <Phone class="w-3.5 h-3.5" />
              <span>Kontak & Paket Aktivasi</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Nomor WhatsApp / HP -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Nomor WhatsApp / HP <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><Phone class="w-4 h-4 text-slate-400" /></span>
                  <input type="tel" v-model="form.phone" required placeholder="Contoh: 081234567890" class="w-full text-sm" />
                </div>
                <InputError class="mt-1" :message="form.errors.phone" />
              </div>

              <!-- Email -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Email <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><Mail class="w-4 h-4 text-slate-400" /></span>
                  <input type="email" v-model="form.email" required placeholder="nama@email.com" class="w-full text-sm" />
                </div>
                <InputError class="mt-1" :message="form.errors.email" />
              </div>

              <!-- Kode Pos -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  Kode Pos <span class="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  inputmode="numeric"
                  maxlength="10"
                  v-model="form.postal_code"
                  required
                  placeholder="Contoh: 10110"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                />
                <InputError class="mt-1" :message="form.errors.postal_code" />
              </div>

              <!-- Paket Aktivasi -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  Paket Aktivasi Non-Card Member <span class="text-rose-500">*</span>
                </label>
                <select
                  v-model="form.member_tier"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                >
                  <option value="bronze">Bronze — Rp 100.000</option>
                  <option value="silver">Silver — Rp 350.000</option>
                </select>
                <InputError class="mt-1" :message="form.errors.member_tier" />
              </div>
            </div>
          </div>

          <!-- SECTION 1: DATA DIRI SESUAI KTP (14 FIELD STANDARD) -->
          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-5 space-y-4">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
              <User class="w-3.5 h-3.5" />
              <span>1. Data Diri Sesuai KTP</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- 1. NIK (16 digit) -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  1. NIK (16 Digit) <span class="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  inputmode="numeric"
                  maxlength="16"
                  v-model="form.nik"
                  required
                  placeholder="16 Digit NIK Sesuai KTP"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                />
                <InputError class="mt-1" :message="form.errors.nik" />
              </div>

              <!-- 2. Nama Lengkap -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  2. Nama Lengkap <span class="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  v-model="form.name"
                  required
                  placeholder="Nama Lengkap Sesuai KTP"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                />
                <InputError class="mt-1" :message="form.errors.name" />
              </div>

              <!-- 3. Tempat Lahir -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  3. Tempat Lahir <span class="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  v-model="form.birth_place"
                  required
                  placeholder="Contoh: Surabaya"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                />
                <InputError class="mt-1" :message="form.errors.birth_place" />
              </div>

              <!-- 4. Tanggal Lahir -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  4. Tanggal Lahir <span class="text-rose-500">*</span>
                </label>
                <BirthDatePicker v-model="form.birth_date" />
                <InputError class="mt-1" :message="form.errors.birth_date" />
              </div>

              <!-- 5. Jenis Kelamin -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  5. Jenis Kelamin <span class="text-rose-500">*</span>
                </label>
                <select
                  v-model="form.gender"
                  required
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                >
                  <option value="Laki-laki">Laki-laki</option>
                  <option value="Perempuan">Perempuan</option>
                </select>
                <InputError class="mt-1" :message="form.errors.gender" />
              </div>

              <!-- 6. Golongan Darah -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  6. Golongan Darah
                </label>
                <select
                  v-model="form.blood_type"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                >
                  <option value="-">-</option>
                  <option value="A">A</option>
                  <option value="B">B</option>
                  <option value="AB">AB</option>
                  <option value="O">O</option>
                </select>
                <InputError class="mt-1" :message="form.errors.blood_type" />
              </div>

              <!-- 7. Alamat -->
              <div class="form-group sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  7. Alamat <span class="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  v-model="form.address"
                  required
                  placeholder="Jalan / Dusun / Nomor Rumah sesuai KTP"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                />
                <InputError class="mt-1" :message="form.errors.address" />
              </div>

              <!-- 8. RT / RW -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  8. RT / RW <span class="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  v-model="form.rt_rw"
                  required
                  placeholder="Contoh: 001/002"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                />
                <InputError class="mt-1" :message="form.errors.rt_rw" />
              </div>

              <!-- 9. Kelurahan / Desa -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  9. Kelurahan / Desa <span class="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  v-model="form.village"
                  required
                  placeholder="Nama Kelurahan / Desa"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                />
                <InputError class="mt-1" :message="form.errors.village" />
              </div>

              <!-- 10. Kecamatan -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  10. Kecamatan <span class="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  v-model="form.district"
                  required
                  placeholder="Nama Kecamatan"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                />
                <InputError class="mt-1" :message="form.errors.district" />
              </div>

              <!-- 11. Kabupaten / Kota -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  11. Kabupaten / Kota <span class="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  v-model="form.city"
                  required
                  placeholder="Nama Kabupaten / Kota"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                />
                <InputError class="mt-1" :message="form.errors.city" />
              </div>

              <!-- 12. Provinsi -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  12. Provinsi <span class="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  v-model="form.province"
                  required
                  placeholder="Nama Provinsi"
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                />
                <InputError class="mt-1" :message="form.errors.province" />
              </div>

              <!-- 13. Agama -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  13. Agama <span class="text-rose-500">*</span>
                </label>
                <select
                  v-model="form.religion"
                  required
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                >
                  <option value="Islam">Islam</option>
                  <option value="Kristen">Kristen</option>
                  <option value="Katolik">Katolik</option>
                  <option value="Hindu">Hindu</option>
                  <option value="Buddha">Buddha</option>
                  <option value="Konghucu">Konghucu</option>
                  <option value="Lainnya">Lainnya</option>
                </select>
                <InputError class="mt-1" :message="form.errors.religion" />
              </div>

              <!-- 14. Status Perkawinan -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                  14. Status Perkawinan <span class="text-rose-500">*</span>
                </label>
                <select
                  v-model="form.marital_status"
                  required
                  class="w-full px-3.5 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#D4AF37]/40 focus:border-[#D4AF37]"
                >
                  <option value="Belum Kawin">Belum Kawin</option>
                  <option value="Kawin">Kawin</option>
                  <option value="Cerai Hidup">Cerai Hidup</option>
                  <option value="Cerai Mati">Cerai Mati</option>
                </select>
                <InputError class="mt-1" :message="form.errors.marital_status" />
              </div>
            </div>
          </div>

          <!-- SECTION 2: DATA AHLI WARIS & KONTAK DARURAT -->
          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-5 space-y-4">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
              <Users class="w-3.5 h-3.5" />
              <span>2. Data Ahli Waris & Kontak Darurat</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Nama Ahli Waris -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Nama Ahli Waris <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><User class="w-4 h-4 text-slate-400" /></span>
                  <input type="text" v-model="form.beneficiary_name" required placeholder="Nama Lengkap Ahli Waris" class="w-full text-sm" />
                </div>
                <InputError class="mt-1" :message="form.errors.beneficiary_name" />
              </div>

              <!-- Tanggal Lahir Ahli Waris -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Tanggal Lahir Ahli Waris <span class="text-rose-500">*</span>
                </label>
                <BirthDatePicker
                  v-model="form.beneficiary_birth_date"
                  :required="true"
                />
                <InputError class="mt-1" :message="form.errors.beneficiary_birth_date" />
              </div>

              <!-- Hubungan dengan Ahli Waris -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Hubungan dengan Ahli Waris <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><Users class="w-4 h-4 text-slate-400" /></span>
                  <input type="text" v-model="form.beneficiary_relation" required placeholder="Contoh: Anak / Pasangan / Orang Tua" class="w-full text-sm" />
                </div>
                <InputError class="mt-1" :message="form.errors.beneficiary_relation" />
              </div>

              <!-- Nomor Whatsapp Kontak Darurat -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Whatsapp Kontak Darurat <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><AlertCircle class="w-4 h-4 text-slate-400" /></span>
                  <input type="tel" v-model="form.emergency_phone" required placeholder="Nomor Kontak Darurat" class="w-full text-sm" />
                </div>
                <InputError class="mt-1" :message="form.errors.emergency_phone" />
              </div>
            </div>
          </div>

          <!-- SECTION 3: IDENTITAS -->
          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-5 space-y-5">
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
                v-if="!form.ktp_image"
                @click="$refs.fileInput.click()"
                class="border-2 border-dashed border-slate-300 hover:border-[#D4AF37] rounded-xl p-5 text-center cursor-pointer transition-colors bg-white group flex flex-col items-center justify-center gap-2"
              >
                <div class="w-10 h-10 rounded-full bg-slate-100 group-hover:bg-[#D4AF37]/10 flex items-center justify-center text-slate-500 group-hover:text-[#D4AF37] transition-colors">
                  <UploadCloud class="w-5 h-5" />
                </div>
                <div class="text-xs font-bold text-slate-700 group-hover:text-slate-900">Tambahkan file KTP</div>
                <div class="text-[11px] text-slate-400">Klik untuk memilih file foto KTP member</div>
              </div>

              <!-- Preview KTP -->
              <div v-else class="p-3.5 bg-white border border-[#D4AF37]/40 rounded-xl flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3 overflow-hidden">
                  <div v-if="ktpPreview && ktpPreview !== 'document'" class="w-12 h-12 rounded-lg overflow-hidden border border-slate-200 flex-shrink-0">
                    <img :src="ktpPreview" alt="KTP Preview" class="w-full h-full object-cover" />
                  </div>
                  <div v-else class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                    <FileText class="w-5 h-5" />
                  </div>
                  <div class="overflow-hidden">
                    <p class="text-xs font-bold text-slate-800 truncate max-w-[220px]">{{ form.ktp_image.name }}</p>
                    <p class="text-[10px] text-emerald-600 font-medium flex items-center gap-1">
                      <Check class="w-3 h-3" /> Siap diunggah ({{ (form.ktp_image.size / 1024 / 1024).toFixed(2) }} MB)
                    </p>
                  </div>
                </div>
                <button type="button" @click="removeFile" class="p-1.5 text-slate-400 hover:text-rose-500 rounded-lg hover:bg-rose-50 transition-colors cursor-pointer">
                  <X class="w-4 h-4" />
                </button>
              </div>

              <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" @change="handleFileUpload" class="hidden" />
              <InputError class="mt-1" :message="form.errors.ktp_image" />
            </div>

            <!-- Bukti Transfer Upload -->
            <div class="form-group pt-3 border-t border-slate-200/60">
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
                v-if="!form.transfer_proof"
                @click="$refs.transferProofFileInput.click()"
                class="border-2 border-dashed border-slate-300 hover:border-[#D4AF37] rounded-xl p-5 text-center cursor-pointer transition-colors bg-white group flex flex-col items-center justify-center gap-2"
              >
                <div class="w-10 h-10 rounded-full bg-slate-100 group-hover:bg-[#D4AF37]/10 flex items-center justify-center text-slate-500 group-hover:text-[#D4AF37] transition-colors">
                  <CreditCard class="w-5 h-5" />
                </div>
                <div class="text-xs font-bold text-slate-700 group-hover:text-slate-900">Tambahkan Bukti Transfer</div>
                <div class="text-[11px] text-slate-400">Klik untuk memilih foto bukti transfer pembayaran</div>
              </div>

              <!-- Preview Bukti Transfer -->
              <div v-else class="p-3.5 bg-white border border-[#D4AF37]/40 rounded-xl flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3 overflow-hidden">
                  <div v-if="transferProofPreview && transferProofPreview !== 'document'" class="w-12 h-12 rounded-lg overflow-hidden border border-slate-200 flex-shrink-0">
                    <img :src="transferProofPreview" alt="Transfer Proof Preview" class="w-full h-full object-cover" />
                  </div>
                  <div v-else class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                    <CreditCard class="w-5 h-5" />
                  </div>
                  <div class="overflow-hidden">
                    <p class="text-xs font-bold text-slate-800 truncate max-w-[220px]">{{ form.transfer_proof.name }}</p>
                    <p class="text-[10px] text-emerald-600 font-medium flex items-center gap-1">
                      <Check class="w-3 h-3" /> Bukti transfer siap diunggah ({{ (form.transfer_proof.size / 1024 / 1024).toFixed(2) }} MB)
                    </p>
                  </div>
                </div>
                <button type="button" @click="removeTransferProof" class="p-1.5 text-slate-400 hover:text-rose-500 rounded-lg hover:bg-rose-50 transition-colors cursor-pointer">
                  <X class="w-4 h-4" />
                </button>
              </div>

              <input ref="transferProofFileInput" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" @change="handleTransferProofUpload" class="hidden" />
              <InputError class="mt-1" :message="form.errors.transfer_proof" />
            </div>
          </div>

          <!-- SECTION 4: KEAMANAN -->
          <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-5 space-y-4">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
              <Lock class="w-3.5 h-3.5" />
              <span>4. Keamanan</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Password -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Password <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><Lock class="w-4 h-4 text-slate-400" /></span>
                  <input :type="showPassword ? 'text' : 'password'" v-model="form.password" required placeholder="Minimal 8 karakter" class="w-full text-sm" />
                  <button type="button" @click="showPassword = !showPassword" class="auth-password-toggle flex items-center justify-center">
                    <Eye v-if="!showPassword" class="w-4 h-4" />
                    <EyeOff v-else class="w-4 h-4" />
                  </button>
                </div>
                <InputError class="mt-1" :message="form.errors.password" />
              </div>

              <!-- Konfirmasi Password -->
              <div class="form-group">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Konfirmasi Password <span class="text-rose-500">*</span>
                </label>
                <div class="auth-input-wrap">
                  <span class="auth-input-icon"><Lock class="w-4 h-4 text-slate-400" /></span>
                  <input :type="showPasswordConfirm ? 'text' : 'password'" v-model="form.password_confirmation" required placeholder="Ulangi password" class="w-full text-sm" />
                  <button type="button" @click="showPasswordConfirm = !showPasswordConfirm" class="auth-password-toggle flex items-center justify-center">
                    <Eye v-if="!showPasswordConfirm" class="w-4 h-4" />
                    <EyeOff v-else class="w-4 h-4" />
                  </button>
                </div>
                <InputError class="mt-1" :message="form.errors.password_confirmation" />
              </div>
            </div>
          </div>

          <!-- SECTION 5: DIREFERENSI OLEH -->
          <div class="p-4 bg-[#faf6eb] border border-[#D4AF37]/40 rounded-2xl space-y-2">
            <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-[#D4AF37]/30 pb-2">
              <KeyRound class="w-3.5 h-3.5" />
              <span>Direferensi oleh:</span>
            </div>
            <div v-if="is_admin && users && users.length > 0">
              <select v-model="form.sponsor_username" class="w-full bg-white border border-[#D4AF37]/40 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:border-[#D4AF37]">
                <option v-for="u in users" :key="u.username" :value="u.username">{{ u.label }}</option>
              </select>
            </div>
            <div v-else class="flex items-center justify-between bg-white px-3.5 py-2.5 border border-[#D4AF37]/30 rounded-xl">
              <span class="text-xs font-extrabold text-[#0F172A]">@{{ form.sponsor_username }} <span v-if="current_user_name" class="font-bold text-slate-600">({{ current_user_name }})</span></span>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="pt-3">
            <button
              type="submit"
              :disabled="form.processing"
              class="auth-primary-btn w-full flex items-center justify-center gap-2 text-xs font-extrabold uppercase tracking-wider text-slate-950 py-3.5 rounded-2xl shadow-md transition-all cursor-pointer disabled:opacity-50"
            >
              <span v-if="form.processing">Memproses Pendaftaran...</span>
              <span v-else>Daftarkan Member Sekarang</span>
              <Check class="w-4 h-4 stroke-[3]" />
            </button>
          </div>

        </form>

      </div>

    </div>
  </AdminLayout>
</template>

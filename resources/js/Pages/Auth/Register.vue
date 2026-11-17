<script setup>
import { ref, watch, onMounted } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import BirthDatePicker from '@/Components/BirthDatePicker.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    User, Mail, Phone, Calendar, Users, AlertCircle, 
    CreditCard, UploadCloud, Lock, Eye, EyeOff, ArrowRight, 
    Check, FileText, X, KeyRound
} from '@lucide/vue';

const props = defineProps({
    referral_code: {
        type: String,
        default: '',
    },
    initial_sponsor: {
        type: Object,
        default: null,
    },
});

const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const ktpPreview = ref(null);
const fileInput = ref(null);
const transferProofPreview = ref(null);
const transferProofFileInput = ref(null);

const sponsorStatus = ref({
    checked: !!props.initial_sponsor,
    valid: !!props.initial_sponsor,
    loading: false,
    sponsor: props.initial_sponsor,
    message: props.initial_sponsor ? `Sponsor Valid: ${props.initial_sponsor.name} (@${props.initial_sponsor.username})` : '',
});

let sponsorCheckTimeout = null;

const checkSponsorLive = async (val) => {
    const rawVal = (val || '').trim();
    if (!rawVal) {
        sponsorStatus.value = {
            checked: false,
            valid: false,
            loading: false,
            sponsor: null,
            message: '',
        };
        return;
    }

    sponsorStatus.value.loading = true;
    try {
        const res = await fetch(`/register/check-sponsor?username=${encodeURIComponent(rawVal)}`);
        const data = await res.json();
        sponsorStatus.value = {
            checked: true,
            valid: data.valid,
            loading: false,
            sponsor: data.sponsor || null,
            message: data.message || '',
        };
    } catch (e) {
        sponsorStatus.value.loading = false;
    }
};

const form = useForm({
    nik: '',
    name: '',
    phone: '',
    email: '',
    address: '',
    member_tier: 'bronze',
    is_left_handed: 'Tidak',
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
    referral: props.referral_code || (typeof window !== 'undefined' ? (new URLSearchParams(window.location.search).get('referral') || new URLSearchParams(window.location.search).get('sponsor') || new URLSearchParams(window.location.search).get('ref') || new URLSearchParams(window.location.search).get('reff') || '') : ''),
    terms: true,
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
                        height = maxHeight;
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

const triggerKtpUpload = () => {
    if (fileInput.value) {
        fileInput.value.click();
    }
};

const triggerTransferProofUpload = () => {
    if (transferProofFileInput.value) {
        transferProofFileInput.value.click();
    }
};

const removeTransferProof = () => {
    form.transfer_proof = null;
    transferProofPreview.value = null;
    if (transferProofFileInput.value) {
        transferProofFileInput.value.value = '';
    }
};

watch(() => form.referral, (newVal) => {
    if (sponsorCheckTimeout) clearTimeout(sponsorCheckTimeout);
    sponsorCheckTimeout = setTimeout(() => {
        checkSponsorLive(newVal);
    }, 400);
});

onMounted(() => {
    if (form.referral && !sponsorStatus.value.checked) {
        checkSponsorLive(form.referral);
    }
});

const submit = () => {
    if (sponsorStatus.value.checked && !sponsorStatus.value.valid && form.referral) {
        form.setError('referral', `Sponsor "${form.referral}" tidak ditemukan. Pendaftaran tidak dapat diproses.`);
        return;
    }
    form.post(route('register'), {
        forceFormData: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Form Pendaftaran | Universal Card Central" />

        <div class="panel-heading">
            <span class="eyebrow">FORMULIR PENDAFTARAN</span>
            <h2>Daftar Member Universal Card Central</h2>
            <p>Isi data dasar di bawah ini untuk membuat akun. Data verifikasi KTP dapat dilengkapi setelah login.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            
            <!-- SECTION 1: DATA PENDAFTARAN -->
            <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-4 space-y-3.5">
                <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
                    <User class="w-3.5 h-3.5" />
                    <span>1. Data Pendaftaran Member</span>
                </div>

                <!-- Nama Lengkap -->
                <div class="form-group">
                    <label for="name" class="block text-xs font-bold text-slate-700 mb-1">
                        Nama Lengkap <span class="text-rose-500">*</span>
                    </label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon">
                            <User class="w-4.5 h-4.5 text-slate-400" />
                        </span>
                        <input
                            id="name"
                            type="text"
                            v-model="form.name"
                            required
                            autofocus
                            placeholder="Nama Lengkap Anda"
                            class="w-full text-sm"
                        />
                    </div>
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>

                <!-- Whatsapp -->
                <div class="form-group">
                    <label for="phone" class="block text-xs font-bold text-slate-700 mb-1">
                        No. HP / WhatsApp <span class="text-rose-500">*</span>
                    </label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon">
                            <Phone class="w-4.5 h-4.5 text-slate-400" />
                        </span>
                        <input
                            id="phone"
                            type="tel"
                            v-model="form.phone"
                            required
                            placeholder="Contoh: 081234567890"
                            class="w-full text-sm"
                        />
                    </div>
                    <InputError class="mt-1" :message="form.errors.phone" />
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="email" class="block text-xs font-bold text-slate-700 mb-1">
                        Email <span class="text-rose-500">*</span>
                    </label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon">
                            <Mail class="w-4.5 h-4.5 text-slate-400" />
                        </span>
                        <input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            autocomplete="email"
                            placeholder="nama@email.com"
                            class="w-full text-sm"
                        />
                    </div>
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>

                <!-- Alamat Lengkap -->
                <div class="form-group">
                    <label for="address" class="block text-xs font-bold text-slate-700 mb-1">
                        Alamat Lengkap <span class="text-rose-500">*</span>
                    </label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon">
                            <FileText class="w-4.5 h-4.5 text-slate-400" />
                        </span>
                        <input
                            id="address"
                            type="text"
                            v-model="form.address"
                            required
                            placeholder="Alamat domisili saat ini"
                            class="w-full text-sm"
                        />
                    </div>
                    <InputError class="mt-1" :message="form.errors.address" />
                </div>

            </div>

            <div class="rounded-2xl border border-[#D4AF37]/30 bg-[#faf6eb] px-4 py-3 text-xs text-slate-700 leading-relaxed">
                <span class="font-bold text-[#9A7B2C]">Daftar Free (Belum Aktif ID):</span> Pendaftaran akun gratis. Untuk mengaktifkan ID (Bronze Rp 100.000 / Silver Rp 400.000 • Cashback Saldo Belanja Rp 50.000), silakan beli atau masukkan <strong>Kode PIN Aktivasi</strong> di Dashboard setelah login. Data KTP &amp; rekening bank dapat dilengkapi setelah aktivasi PIN.
            </div>

            <!-- SECTION 2: KEAMANAN PASSWORD -->
            <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-4 space-y-3.5">
                <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
                    <Lock class="w-3.5 h-3.5" />
                    <span>2. Keamanan Akun Login</span>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password" class="block text-xs font-bold text-slate-700 mb-1">
                        Password <span class="text-rose-500">*</span>
                    </label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon">
                            <Lock class="w-4.5 h-4.5 text-slate-400" />
                        </span>
                        <input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            v-model="form.password"
                            required
                            autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                            class="w-full text-sm"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="auth-password-toggle flex items-center justify-center"
                        >
                            <Eye v-if="!showPassword" class="w-4.5 h-4.5" />
                            <EyeOff v-else class="w-4.5 h-4.5" />
                        </button>
                    </div>
                    <InputError class="mt-1" :message="form.errors.password" />
                </div>

                <!-- Konfirmasi Password -->
                <div class="form-group">
                    <label for="password_confirmation" class="block text-xs font-bold text-slate-700 mb-1">
                        Konfirmasi Password <span class="text-rose-500">*</span>
                    </label>
                    <div class="auth-input-wrap">
                        <span class="auth-input-icon">
                            <Lock class="w-4.5 h-4.5 text-slate-400" />
                        </span>
                        <input
                            id="password_confirmation"
                            :type="showPasswordConfirm ? 'text' : 'password'"
                            v-model="form.password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="Ulangi password Anda"
                            class="w-full text-sm"
                        />
                        <button
                            type="button"
                            @click="showPasswordConfirm = !showPasswordConfirm"
                            class="auth-password-toggle flex items-center justify-center"
                        >
                            <Eye v-if="!showPasswordConfirm" class="w-4.5 h-4.5" />
                            <EyeOff v-else class="w-4.5 h-4.5" />
                        </button>
                    </div>
                    <InputError class="mt-1" :message="form.errors.password_confirmation" />
                </div>
            </div>

            <!-- SECTION 3: KODE REFERRAL / SPONSOR -->
            <div class="bg-slate-50/70 border border-slate-200/80 rounded-2xl p-4 space-y-3.5">
                <div class="text-[11px] font-black tracking-wider text-[#D4AF37] uppercase flex items-center gap-1.5 border-b border-slate-200/60 pb-2">
                    <KeyRound class="w-3.5 h-3.5" />
                    <span>3. Kode Referral</span>
                </div>

                <!-- Input Kode Referral -->
                <div class="form-group">
                    <div class="flex items-center justify-between mb-1">
                        <label for="referral" class="block text-xs font-bold text-slate-700">
                            Kode Referral <span class="text-rose-500">*</span>
                        </label>
                        <span v-if="sponsorStatus.loading" class="text-[10px] text-amber-600 font-bold flex items-center gap-1 animate-pulse">
                            Memeriksa referral...
                        </span>
                    </div>

                    <div class="auth-input-wrap">
                        <span class="auth-input-icon">
                            <KeyRound class="w-4.5 h-4.5 text-slate-400" />
                        </span>
                        <input
                            id="referral"
                            type="text"
                            v-model="form.referral"
                            @blur="checkSponsorLive(form.referral)"
                            required
                            placeholder="Masukkan kode referral"
                            class="w-full text-sm font-semibold text-slate-800"
                        />
                    </div>

                    <!-- Live Sponsor Status Feedback -->
                    <div v-if="sponsorStatus.checked && form.referral" class="mt-2.5">
                        <!-- Valid Sponsor Banner -->
                        <div v-if="sponsorStatus.valid" class="p-3 bg-emerald-50/90 border border-emerald-300 rounded-xl flex items-center justify-between gap-2 shadow-2xs">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                                    <Check class="w-3.5 h-3.5 stroke-[3]" />
                                </div>
                                <div>
                                    <span class="text-[9px] font-black text-emerald-800 uppercase tracking-wider block">REFERRAL VALID</span>
                                    <p class="text-xs font-bold text-emerald-950">
                                        {{ sponsorStatus.sponsor?.name }} <span class="font-mono text-emerald-700">(@{{ sponsorStatus.sponsor?.username }})</span>
                                    </p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 text-[9px] font-extrabold bg-[#faf6eb] text-[#B8922E] border border-[#D4AF37]/50 rounded-md uppercase">
                                {{ sponsorStatus.sponsor?.package_name || 'STANDARD' }}
                            </span>
                        </div>

                        <!-- Invalid / Typo Sponsor Banner -->
                        <div v-else class="p-3 bg-rose-50 border border-rose-300 rounded-xl flex items-center gap-2.5 text-rose-800 shadow-2xs">
                            <div class="w-6 h-6 rounded-lg bg-rose-600 text-white flex items-center justify-center shrink-0">
                                <X class="w-3.5 h-3.5 stroke-[3]" />
                            </div>
                            <div>
                                <span class="text-[9px] font-black text-rose-800 uppercase tracking-wider block">REFERRAL TIDAK DITEMUKAN</span>
                                <p class="text-xs font-semibold text-rose-700 leading-tight">
                                    Username "<strong>{{ form.referral }}</strong>" tidak terdaftar. Pendaftaran tidak dapat diproses jika kode referral salah / typo.
                                </p>
                            </div>
                        </div>
                    </div>

                    <p v-if="!sponsorStatus.checked || !form.referral" class="text-[11px] text-slate-500 mt-1">Wajib diisi dengan username atau kode referral sponsor yang mengundang Anda.</p>
                    <InputError class="mt-1" :message="form.errors.referral" />
                </div>
            </div>

            <!-- Terms -->
            <label class="flex items-start cursor-pointer text-xs text-slate-600 my-2 pt-1">
                <input
                    type="checkbox"
                    v-model="form.terms"
                    required
                    class="mt-0.5 rounded border-slate-300 text-[#D4AF37] focus:ring-[#D4AF37] accent-[#0F172A]"
                />
                <span class="ms-2 leading-tight">Saya menyatakan data yang saya isi adalah benar dan menyetujui syarat & ketentuan yang berlaku.</span>
            </label>

            <!-- Submit Button -->
            <button
                type="submit"
                :disabled="form.processing"
                class="auth-primary-btn w-full flex items-center justify-center gap-2 text-xs font-extrabold uppercase tracking-wider text-white disabled:opacity-50 mt-4 py-3"
            >
                <span v-if="form.processing">Memproses Pendaftaran...</span>
                <span v-else>Daftar Sekarang</span>
                <ArrowRight class="w-4 h-4" />
            </button>
        </form>

        <div class="mt-5 text-center text-xs text-slate-500 font-medium">
            Sudah punya akun?
            <Link :href="route('login')" class="ms-1 font-bold text-[#B8922E] hover:text-[#0F172A] transition-colors">
                Masuk ke Akun
            </Link>
        </div>
    </GuestLayout>
</template>


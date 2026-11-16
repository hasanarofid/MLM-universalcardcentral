<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Voucher;
use App\Services\UccBonusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MemberActivationController extends Controller
{
    /**
     * Display member activation form.
     */
    public function index()
    {
        $currentUser = auth()->user() ?: User::first();

        // Get user's active vouchers
        $activeVouchersRaw = Voucher::where('user_id', $currentUser->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('voucher_type')->orWhere('voucher_type', 'activation');
            })
            ->get();

        $vouchers = $activeVouchersRaw->map(function ($v) {
            $pkgName = $v->package_name ?: 'Seller';
            $pkgName = str_replace(
                ['Starter (Rp 125.000)', 'Starter', 'Basic (Rp 550.000)', 'Basic', 'Medium (Rp 2.100.000)', 'Medium', 'Pro (Rp 4.300.000)', 'Pro', 'Ultimate (Rp 10.500.000)', 'Ultimate'],
                ['Seller (Rp 125.000)', 'Seller', 'Star Seller (Rp 550.000)', 'Star Seller', 'Affiliate (Rp 2.100.000)', 'Affiliate', 'Business (Rp 4.300.000)', 'Business', 'Partner (Rp 10.500.000)', 'Partner'],
                $pkgName
            );
            return [
                'code' => $v->code,
                'package_name' => $pkgName,
                'label' => $v->code . ' (Paket ' . $pkgName . ')',
            ];
        });

        $pinStocks = [
            'bronze' => 0,
            'silver' => 0,
        ];

        foreach ($activeVouchersRaw as $v) {
            if (UccBonusService::resolveTierFromPin($v) === 'silver') {
                $pinStocks['silver']++;
            } else {
                $pinStocks['bronze']++;
            }
        }

        $isAdmin = ($currentUser->username === 'admin' || $currentUser->email === 'admin@universalcardcentral.com' || (method_exists($currentUser, 'hasRole') && $currentUser->hasRole('admin')));

        $freeDownlinesQuery = User::where('activation_status', '!=', 'active')->latest();
        if (!$isAdmin) {
            $freeDownlinesQuery->where('parent_id', $currentUser->id);
        }
        $freeDownlines = $freeDownlinesQuery->take(50)->get(['id', 'name', 'username', 'email', 'phone', 'activation_status'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'phone' => $u->phone,
                'activation_status' => $u->activation_status,
                'label' => $u->name . ' (@' . $u->username . ') — ' . ($u->activation_status === 'pending' ? 'Menunggu' : 'Belum Aktif'),
            ]);

        // List of all active users to choose as Sponsor Langsung (Khusus Admin)
        $allUsers = $isAdmin ? User::select('id', 'name', 'username', 'email')->get()->map(function ($u) {
            return [
                'username' => $u->username ?: strtolower(explode(' ', $u->name)[0]),
                'name' => $u->name,
                'label' => '@' . ($u->username ?: strtolower(explode(' ', $u->name)[0])) . ' (' . $u->name . ')',
            ];
        }) : [];

        // Company Banks for Direct Transfer Payment
        $settings = \App\Models\Setting::all()->pluck('value', 'key');
        $companyBanks = json_decode($settings['company_banks'] ?? '[]', true);
        $banks = (is_array($companyBanks) && count($companyBanks) > 0)
            ? $companyBanks
            : [
                [
                    'bank_name' => 'Bank BRI',
                    'bank_account_number' => '806401000095564',
                    'account_number' => '806401000095564',
                    'bank_account_name' => 'Universal Card Central',
                    'account_name' => 'Universal Card Central',
                ]
            ];

        return Inertia::render('Admin/Activation/Index', [
            'is_admin' => $isAdmin,
            'current_user_name' => $currentUser->name,
            'vouchers' => $vouchers,
            'pin_stocks' => $pinStocks,
            'free_downlines' => $freeDownlines,
            'banks' => $banks,
            'registration_fee' => 100000,
            'users' => $allUsers,
            'default_sponsor' => $currentUser->username ?: 'admin',
        ]);
    }

    /**
     * Process member activation in Matahari System (Direct Sponsor).
     */
    public function store(Request $request)
    {
        $currentUser = auth()->user() ?: User::first();
        $isAdmin = ($currentUser->username === 'admin' || $currentUser->email === 'admin@universalcardcentral.com' || (method_exists($currentUser, 'hasRole') && $currentUser->hasRole('admin')));

        if (!$isAdmin) {
            $request->merge(['sponsor_username' => $currentUser->username]);
        }

        $usingPin = $request->filled('pin_code');
        $pin = null;
        if ($usingPin) {
            $pinQuery = Voucher::where('code', strtoupper(trim($request->pin_code)))
                ->where('status', 'active');
            if (!$isAdmin) {
                $pinQuery->where('user_id', $currentUser->id);
            }
            $pin = $pinQuery->first();
            if (!$pin) {
                throw ValidationException::withMessages([
                    'pin_code' => 'Kode PIN tidak ditemukan, bukan milik Anda, atau sudah digunakan.',
                ]);
            }
        }

        $reqRule = $usingPin ? 'nullable' : 'required';

        $request->validate([
            'pin_code' => 'nullable|string|max:50',
            'position' => 'nullable|in:left,right',
            'phone' => 'required|string|max:25',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'postal_code' => $reqRule . '|string|max:10',
            'member_tier' => 'nullable|string|in:bronze,silver',
            'nik' => $reqRule . '|digits:16|unique:users,nik',
            'name' => 'required|string|max:255',
            'birth_place' => $reqRule . '|string|max:100',
            'birth_date' => $reqRule . '|date|before_or_equal:today|after:1900-01-01',
            'gender' => $reqRule . '|string|in:Laki-laki,Perempuan',
            'blood_type' => 'nullable|string|in:A,B,AB,O,-',
            'address' => 'required|string|max:500',
            'rt_rw' => $reqRule . '|string|max:20',
            'village' => $reqRule . '|string|max:100',
            'district' => $reqRule . '|string|max:100',
            'city' => $reqRule . '|string|max:100',
            'province' => $reqRule . '|string|max:100',
            'religion' => $reqRule . '|string|max:50',
            'marital_status' => $reqRule . '|string|max:50',
            'is_left_handed' => 'nullable|string|in:Iya,Tidak',
            'beneficiary_name' => $reqRule . '|string|max:255',
            'beneficiary_birth_date' => $reqRule . '|date|before_or_equal:today|after:1900-01-01',
            'beneficiary_relation' => $reqRule . '|string|max:100',
            'emergency_phone' => $reqRule . '|string|max:25',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:100',
            'ktp_image' => $reqRule . '|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'transfer_proof' => $reqRule . '|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'password' => 'required|string|min:8|confirmed',
            'sponsor_username' => 'required|string|exists:users,username',
        ], [
            'nik.required' => 'NIK (16 digit) wajib diisi.',
            'nik.digits' => 'NIK harus terdiri dari tepat 16 digit angka.',
            'nik.unique' => 'NIK sudah terdaftar di sistem.',
            'transfer_proof.required' => 'Bukti transfer pembayaran wajib diunggah.',
            'ktp_image.required' => 'Foto KTP wajib diunggah.',
        ]);

        $sponsorUser = User::where('username', $request->sponsor_username)->first();
        if (!$sponsorUser) {
            throw ValidationException::withMessages([
                'sponsor_username' => 'Username Sponsor Langsung tidak ditemukan.',
            ]);
        }

        $sponsorCheck = $sponsorUser->canSponsorNewMember();
        if (!$sponsorCheck['allowed']) {
            throw ValidationException::withMessages([
                'sponsor_username' => $sponsorCheck['message'],
            ]);
        }

        $ktpPath = null;
        if ($request->hasFile('ktp_image')) {
            $file = $request->file('ktp_image');
            $filename = 'ktp_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $destination = public_path('images/ktp');
            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }
            $file->move($destination, $filename);
            $ktpPath = '/images/ktp/' . $filename;
        }

        $transferProofPath = null;
        if ($request->hasFile('transfer_proof')) {
            $file = $request->file('transfer_proof');
            $filename = 'transfer_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $destination = public_path('images/transfer_proofs');
            if (!file_exists($destination)) {
                mkdir($destination, 0755, true);
            }
            $file->move($destination, $filename);
            $transferProofPath = '/images/transfer_proofs/' . $filename;
        }

        // Generate username unik dari email
        $usernameBase = strtolower(explode('@', $request->email)[0]);
        $usernameBase = preg_replace('/[^a-z0-9_]/', '', $usernameBase) ?: 'member';
        $username = $usernameBase;
        $counter = 1;
        while (User::where('username', $username)->exists()) {
            $username = $usernameBase . $counter++;
        }

        $plainPassword = $request->password;
        $tier = $pin ? UccBonusService::resolveTierFromPin($pin) : strtolower((string) $request->input('member_tier', 'bronze'));

        DB::transaction(function () use ($request, $currentUser, $sponsorUser, $username, $ktpPath, $transferProofPath, $plainPassword, $tier, $pin) {
            // Create new member in UCC system (parent_id = sponsor_id)
            $newUser = User::create([
                'nik' => $request->nik,
                'name' => $request->name,
                'username' => $username,
                'email' => $request->email,
                'phone' => $request->phone,
                'postal_code' => $request->postal_code,
                'birth_place' => $request->birth_place,
                'birth_date' => $request->birth_date,
                'gender' => $request->gender,
                'blood_type' => $request->input('blood_type', '-'),
                'address' => $request->address,
                'rt_rw' => $request->rt_rw,
                'village' => $request->village,
                'district' => $request->district,
                'city' => $request->city,
                'province' => $request->province,
                'religion' => $request->religion,
                'marital_status' => $request->marital_status,
                'member_tier' => $tier,
                'activation_status' => 'active',
                'activated_at' => now(),
                'activated_by' => $currentUser->id,
                'activation_amount' => UccBonusService::getActivationAmount($tier),
                'is_left_handed' => $request->input('is_left_handed', 'Tidak'),
                'beneficiary_name' => $request->beneficiary_name,
                'beneficiary_birth_date' => $request->beneficiary_birth_date,
                'beneficiary_relation' => $request->beneficiary_relation,
                'beneficiary_phone' => $request->emergency_phone,
                'emergency_phone' => $request->emergency_phone,
                'bank_name' => $request->bank_name ?: 'Bank BRI',
                'bank_account_number' => $request->bank_account_number,
                'bank_account_name' => $request->bank_account_name ?: $request->name,
                'ktp_image' => $ktpPath,
                'transfer_proof' => $transferProofPath,
                'password' => bcrypt($plainPassword),
                'parent_id' => $sponsorUser->id,
                'position' => $request->position,
            ]);
            try {
                $newUser->assignRole('client');
            } catch (\Throwable $e) {
                // Role fallback
            }

            // Email 1: Pendaftaran Member Berhasil (Welcome Email)
            try {
                $newUser->notify(new \App\Notifications\WelcomeRegisterNotification($newUser, $plainPassword));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Gagal mengirim email aktivasi member: ' . $e->getMessage());
            }

            if ($pin) {
                app(UccBonusService::class)->activateMemberWithPin($newUser, $pin, $currentUser, $request->position);
            } else {
                app(UccBonusService::class)->processActivation($newUser, $sponsorUser, $tier);
            }
        });

        $successMsg = "Mitra baru {$request->name} (@{$username}) berhasil didaftarkan di bawah Sponsor @{$sponsorUser->username}!";

        if ($request->input('source') === 'dashboard') {
            return redirect()->route('admin.dashboard')->with('success', $successMsg);
        }

        return redirect()->route('admin.team', ['focus_id' => $sponsorUser->id])
            ->with('success', $successMsg);
    }

    /**
     * Calculate Package Allocation placeholder.
     */
    private function calculatePackageAllocation(string $packageName): array
    {
        return [
            'gen_1' => 7000,
            'gen_2_10' => 7000,
            'team_points' => 0,
        ];
    }
}

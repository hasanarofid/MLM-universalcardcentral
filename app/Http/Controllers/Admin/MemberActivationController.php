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

        // Calculate voucher stock count per package type
        $voucherStocks = [
            'seller' => 0,
            'star_seller' => 0,
            'affiliate' => 0,
            'business' => 0,
            'partner' => 0,
        ];

        foreach ($activeVouchersRaw as $v) {
            $pkg = strtolower($v->package_name ?? '');
            if (str_contains($pkg, 'partner') || str_contains($pkg, 'ultimate') || str_contains($pkg, '10.500') || str_contains($pkg, '10500')) {
                $voucherStocks['partner']++;
            } elseif (str_contains($pkg, 'business') || str_contains($pkg, 'pro') || str_contains($pkg, '4.300') || str_contains($pkg, '4300')) {
                $voucherStocks['business']++;
            } elseif (str_contains($pkg, 'affiliate') || str_contains($pkg, 'medium') || str_contains($pkg, '2.100') || str_contains($pkg, '2100')) {
                $voucherStocks['affiliate']++;
            } elseif (str_contains($pkg, 'star') || str_contains($pkg, 'basic') || str_contains($pkg, '550')) {
                $voucherStocks['star_seller']++;
            } else {
                $voucherStocks['seller']++;
            }
        }

        $currentUser = auth()->user() ?: User::first();
        $isAdmin = ($currentUser->username === 'admin' || $currentUser->email === 'admin@nexuscommunity.id' || (method_exists($currentUser, 'hasRole') && $currentUser->hasRole('admin')));

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
                    'bank_account_name' => 'PT.Nexus Community Punya Kita',
                    'account_name' => 'PT.Nexus Community Punya Kita',
                ]
            ];

        return Inertia::render('Admin/Activation/Index', [
            'is_admin' => $isAdmin,
            'current_user_name' => $currentUser->name,
            'vouchers' => $vouchers,
            'voucher_stocks' => $voucherStocks,
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
        $isAdmin = ($currentUser->username === 'admin' || $currentUser->email === 'admin@nexuscommunity.id' || (method_exists($currentUser, 'hasRole') && $currentUser->hasRole('admin')));

        if (!$isAdmin) {
            $request->merge(['sponsor_username' => $currentUser->username]);
        }

        $request->validate([
            'nik' => 'required|digits:16|unique:users,nik',
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'phone' => 'required|string|max:25',
            'address' => 'nullable|string|max:500',
            'member_tier' => 'nullable|string|in:bronze,silver',
            'is_left_handed' => 'nullable|string|in:Iya,Tidak',
            'beneficiary_name' => 'required|string|max:255',
            'beneficiary_birth_date' => 'required|date|before_or_equal:today|after:1900-01-01',
            'beneficiary_relation' => 'required|string|max:100',
            'emergency_phone' => 'required|string|max:25',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:100',
            'ktp_image' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'transfer_proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'password' => 'required|string|min:8|confirmed',
            'sponsor_username' => 'required|string|exists:users,username',
        ], [
            'nik.required' => 'ID KTP (NIK 16 digit) wajib diisi.',
            'nik.digits' => 'ID KTP (NIK) harus terdiri dari tepat 16 digit angka.',
            'nik.unique' => 'ID KTP (NIK) sudah terdaftar di sistem.',
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
        $tier = strtolower((string) $request->input('member_tier', 'bronze'));

        DB::transaction(function () use ($request, $sponsorUser, $username, $ktpPath, $transferProofPath, $plainPassword, $tier) {
            // Create new member in UCC system (parent_id = sponsor_id)
            $newUser = User::create([
                'nik' => $request->nik,
                'name' => $request->name,
                'username' => $username,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'member_tier' => $tier,
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

            app(UccBonusService::class)->processActivation($newUser, $sponsorUser, $tier);
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

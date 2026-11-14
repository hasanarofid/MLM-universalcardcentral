<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UccBonusService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        $ref = $request->query('referral') 
            ?? $request->query('sponsor') 
            ?? $request->query('ref') 
            ?? $request->query('reff') 
            ?? '';

        $cleanRef = ltrim(trim($ref), '@');
        $sponsorInfo = null;
        if (!empty($cleanRef)) {
            $sponsor = User::where('username', $cleanRef)
                ->orWhere('username', $ref)
                ->orWhere('email', $ref)
                ->first();
            if ($sponsor) {
                $sponsorInfo = [
                    'name' => $sponsor->name,
                    'username' => $sponsor->username,
                    'package_name' => $sponsor->package_name ?: 'Standard',
                ];
            }
        }

        return Inertia::render('Auth/Register', [
            'referral_code' => (string) $ref,
            'initial_sponsor' => $sponsorInfo,
        ]);
    }

    /**
     * Check if sponsor username/referral is valid (for live validation).
     */
    public function checkSponsor(Request $request)
    {
        $username = trim($request->query('username', ''));
        $cleanUsername = ltrim($username, '@');

        if (empty($username)) {
            return response()->json([
                'valid' => false,
                'message' => 'Username sponsor wajib diisi.',
            ]);
        }

        $sponsor = User::where('username', $cleanUsername)
            ->orWhere('username', $username)
            ->orWhere('email', $username)
            ->first();

        if ($sponsor) {
            $sponsorCheck = $sponsor->canSponsorNewMember();
            if (!$sponsorCheck['allowed']) {
                return response()->json([
                    'valid' => false,
                    'message' => $sponsorCheck['message'],
                ]);
            }

            return response()->json([
                'valid' => true,
                'sponsor' => [
                    'name' => $sponsor->name,
                    'username' => $sponsor->username,
                    'package_name' => $sponsor->package_name ?: 'Standard',
                ],
                'message' => 'Sponsor valid: ' . $sponsor->name . ' (@' . $sponsor->username . ')',
            ]);
        }

        return response()->json([
            'valid' => false,
            'message' => 'Username/Kode Sponsor "' . $username . '" tidak ditemukan. Mohon periksa kembali.',
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nik' => 'required|digits:16|unique:users,nik',
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone' => 'required|string|max:25',
            'postal_code' => 'nullable|string|max:10',
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date|before_or_equal:today|after:1900-01-01',
            'gender' => 'nullable|string|in:Laki-laki,Perempuan',
            'blood_type' => 'nullable|string|in:A,B,AB,O,-',
            'address' => 'nullable|string|max:500',
            'rt_rw' => 'nullable|string|max:20',
            'village' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'religion' => 'nullable|string|max:50',
            'marital_status' => 'nullable|string|max:50',
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
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'referral' => 'required|string|max:255',
        ], [
            'nik.required' => 'ID KTP (NIK 16 digit) wajib diisi.',
            'nik.digits' => 'ID KTP (NIK) harus terdiri dari tepat 16 digit angka.',
            'nik.unique' => 'ID KTP (NIK) sudah terdaftar di sistem.',
            'transfer_proof.required' => 'Bukti transfer pembayaran wajib diunggah.',
            'referral.required' => 'Kode Referral / Username Sponsor wajib diisi.',
        ]);

        $rawReferral = trim($request->referral);
        $cleanReferral = ltrim($rawReferral, '@');

        $sponsor = User::where('username', $cleanReferral)
            ->orWhere('username', $rawReferral)
            ->orWhere('email', $rawReferral)
            ->first();

        if (!$sponsor) {
            throw ValidationException::withMessages([
                'referral' => 'Kode Referral / Username Sponsor "' . $rawReferral . '" tidak ditemukan dalam sistem. Pendaftaran tidak dapat diproses jika kode sponsor salah.',
            ]);
        }

        $sponsorCheck = $sponsor->canSponsorNewMember();
        if (!$sponsorCheck['allowed']) {
            throw ValidationException::withMessages([
                'referral' => $sponsorCheck['message'],
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

        $tier = strtolower((string) $request->input('member_tier', 'bronze'));
        $user = null;
        DB::transaction(function () use ($request, $sponsor, $username, $ktpPath, $transferProofPath, $tier, &$user) {
            $user = User::create([
                'nik' => $request->nik,
                'name' => $request->name,
                'email' => $request->email,
                'username' => $username,
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
                'activation_amount' => UccBonusService::getActivationAmount($tier),
                'is_left_handed' => $request->input('is_left_handed', 'Tidak'),
                'beneficiary_name' => $request->beneficiary_name,
                'beneficiary_birth_date' => $request->beneficiary_birth_date,
                'beneficiary_relation' => $request->beneficiary_relation,
                'beneficiary_phone' => $request->emergency_phone,
                'emergency_phone' => $request->emergency_phone,
                'bank_name' => $request->bank_name,
                'bank_account_number' => $request->bank_account_number,
                'bank_account_name' => $request->bank_account_name ?: $request->name,
                'ktp_image' => $ktpPath,
                'transfer_proof' => $transferProofPath,
                'password' => Hash::make($request->password),
                'parent_id' => $sponsor->id,
            ]);

            try {
                $user->assignRole('client');
            } catch (\Throwable $e) {
                // Role fallback
            }

            // Email 1: Pendaftaran Member Berhasil (Welcome Email)
            try {
                $user->notify(new \App\Notifications\WelcomeRegisterNotification($user, $request->password));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Gagal mengirim email pendaftaran member: ' . $e->getMessage());
            }

            app(UccBonusService::class)->processActivation($user, $sponsor, $tier);
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}

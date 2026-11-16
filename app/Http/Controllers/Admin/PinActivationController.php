<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PinRequest;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\SystemActivityNotification;
use App\Services\UccBonusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PinActivationController extends Controller
{
    public function activateSelfWithPin(Request $request, UccBonusService $uccBonusService): RedirectResponse
    {
        $user = $request->user();
        if ($user->isActiveMember()) {
            return back()->with('error', 'Akun Anda sudah aktif.');
        }

        $validated = $request->validate([
            'pin_code' => 'required|string|max:50',
        ]);

        $code = strtoupper(trim($validated['pin_code']));
        $pin = Voucher::where('code', $code)
            ->where('status', 'active')
            ->first();

        if (!$pin) {
            return back()->withErrors(['pin_code' => 'Kode PIN tidak ditemukan atau sudah digunakan.']);
        }

        $tier = $uccBonusService->activateMemberWithPin($user, $pin, $user);

        return back()->with('success', 'Selamat! ID Member Anda resmi aktif sebagai Paket ' . ucfirst($tier) . '.');
    }

    public function requestPin(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'tier' => 'required|in:bronze,silver',
            'quantity' => 'required|integer|min:1|max:100',
            'transfer_proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $unitPrice = UccBonusService::getActivationAmount($validated['tier']);
        $totalAmount = $unitPrice * (int) $validated['quantity'];

        $file = $request->file('transfer_proof');
        $filename = 'pinproof_' . $user->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $destination = public_path('images/transfer_proofs');
        if (!file_exists($destination)) {
            mkdir($destination, 0755, true);
        }
        $file->move($destination, $filename);
        $proofPath = '/images/transfer_proofs/' . $filename;

        $pinRequest = PinRequest::create([
            'user_id' => $user->id,
            'tier' => $validated['tier'],
            'quantity' => (int) $validated['quantity'],
            'unit_price' => $unitPrice,
            'total_amount' => $totalAmount,
            'transfer_proof' => $proofPath,
            'status' => 'pending',
        ]);

        if (($user->activation_status ?? 'inactive') === 'inactive') {
            $user->update([
                'activation_status' => 'pending',
                'transfer_proof' => $proofPath,
            ]);
        }

        SystemActivityNotification::notifyAdmins(
            category: 'finance',
            title: 'Order PIN Aktivasi Baru (' . ucfirst($validated['tier']) . ')',
            message: "{$user->name} (@{$user->username}) mengunggah bukti bayar untuk {$validated['quantity']}x PIN " . ucfirst($validated['tier']) . ' (Rp ' . number_format($totalAmount, 0, ',', '.') . ').',
            link: '/admin/voucher-wallet'
        );

        return back()->with('success', 'Bukti transfer pembelian ' . $validated['quantity'] . 'x PIN ' . ucfirst($validated['tier']) . ' berhasil dikirim ke Admin untuk verifikasi.');
    }

    public function approvePinRequest(Request $request, PinRequest $pinRequest): RedirectResponse
    {
        $admin = $request->user();
        if (!$admin->hasRole('admin') && $admin->username !== 'admin') {
            return back()->with('error', 'Hanya Admin yang dapat menyetujui order PIN.');
        }

        if ($pinRequest->status !== 'pending') {
            return back()->with('error', 'Permohonan PIN ini sudah diproses sebelumnya.');
        }

        $generatedCodes = [];
        DB::transaction(function () use ($pinRequest, $admin, &$generatedCodes) {
            $prefix = $pinRequest->tier === 'silver' ? 'UCCS-' : 'UCCB-';
            $packageLabel = $pinRequest->tier === 'silver'
                ? 'Silver (Rp 350.000)'
                : 'Bronze (Rp 100.000)';

            for ($i = 0; $i < $pinRequest->quantity; $i++) {
                do {
                    $code = $prefix . strtoupper(Str::random(8));
                } while (Voucher::where('code', $code)->exists());

                Voucher::create([
                    'code' => $code,
                    'user_id' => $pinRequest->user_id,
                    'package_name' => $packageLabel,
                    'voucher_type' => 'activation',
                    'status' => 'active',
                ]);
                $generatedCodes[] = $code;
            }

            $pinRequest->update([
                'status' => 'approved',
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);
        });

        $recipient = $pinRequest->user;
        SystemActivityNotification::sendSafe(
            $recipient,
            new SystemActivityNotification(
                category: 'system',
                title: 'PIN Aktivasi Anda Telah Dikirim!',
                message: 'Pembayaran disetujui. Kode PIN ' . ucfirst($pinRequest->tier) . ' Anda: ' . implode(', ', $generatedCodes) . '. Gunakan di Dashboard atau untuk mengaktifkan mitra.',
                link: '/admin/voucher-wallet',
                roleTarget: 'member'
            )
        );

        return back()->with('success', 'Order PIN #' . $pinRequest->id . ' disetujui! ' . count($generatedCodes) . ' PIN berhasil dikirim ke dashboard member.');
    }

    public function rejectPinRequest(Request $request, PinRequest $pinRequest): RedirectResponse
    {
        $admin = $request->user();
        if (!$admin->hasRole('admin') && $admin->username !== 'admin') {
            return back()->with('error', 'Hanya Admin yang dapat menolak order PIN.');
        }

        if ($pinRequest->status !== 'pending') {
            return back()->with('error', 'Permohonan PIN ini sudah diproses sebelumnya.');
        }

        $pinRequest->update([
            'status' => 'rejected',
            'admin_notes' => $request->input('notes', 'Bukti transfer tidak valid.'),
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        SystemActivityNotification::sendSafe(
            $pinRequest->user,
            new SystemActivityNotification(
                category: 'finance',
                title: 'Order PIN Ditolak',
                message: 'Pengajuan pembelian PIN #' . $pinRequest->id . ' ditolak: ' . ($pinRequest->admin_notes ?: 'Silakan periksa kembali bukti bayar Anda.'),
                link: '/admin/voucher-wallet',
                roleTarget: 'member'
            )
        );

        return back()->with('success', 'Order PIN #' . $pinRequest->id . ' telah ditolak.');
    }

    public function adminGeneratePin(Request $request): RedirectResponse
    {
        $admin = $request->user();
        if (!$admin->hasRole('admin') && $admin->username !== 'admin') {
            return back()->with('error', 'Hanya Admin yang dapat generate PIN.');
        }

        $validated = $request->validate([
            'recipient_username' => 'nullable|string|exists:users,username',
            'tier' => 'required|in:bronze,silver',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $recipient = !empty($validated['recipient_username'])
            ? User::where('username', $validated['recipient_username'])->firstOrFail()
            : $admin;

        $generatedCodes = [];
        DB::transaction(function () use ($validated, $recipient, &$generatedCodes) {
            $prefix = $validated['tier'] === 'silver' ? 'UCCS-' : 'UCCB-';
            $packageLabel = $validated['tier'] === 'silver'
                ? 'Silver (Rp 350.000)'
                : 'Bronze (Rp 100.000)';

            for ($i = 0; $i < (int) $validated['quantity']; $i++) {
                do {
                    $code = $prefix . strtoupper(Str::random(8));
                } while (Voucher::where('code', $code)->exists());

                Voucher::create([
                    'code' => $code,
                    'user_id' => $recipient->id,
                    'package_name' => $packageLabel,
                    'voucher_type' => 'activation',
                    'status' => 'active',
                ]);
                $generatedCodes[] = $code;
            }
        });

        if ($recipient->id !== $admin->id) {
            SystemActivityNotification::sendSafe(
                $recipient,
                new SystemActivityNotification(
                    category: 'system',
                    title: 'Anda Menerima Stok PIN Baru',
                    message: "Admin mengirimkan {$validated['quantity']}x PIN " . ucfirst($validated['tier']) . ' (' . implode(', ', $generatedCodes) . ') ke akun Anda.',
                    link: '/admin/voucher-wallet',
                    roleTarget: 'member'
                )
            );
        }

        return back()->with('success', $validated['quantity'] . 'x PIN ' . ucfirst($validated['tier']) . ' berhasil di-generate untuk @' . $recipient->username . '.');
    }

    public function activateFreeDownline(Request $request, UccBonusService $uccBonusService): RedirectResponse
    {
        $recruiter = $request->user();

        $validated = $request->validate([
            'member_id' => 'required|integer|exists:users,id',
            'pin_code' => 'required|string|max:50',
            'position' => 'nullable|in:left,right',
        ]);

        $downline = User::findOrFail($validated['member_id']);
        if ($downline->isActiveMember()) {
            return back()->with('error', 'Member tersebut sudah berstatus aktif.');
        }

        $pin = Voucher::where('code', strtoupper(trim($validated['pin_code'])))
            ->where('user_id', $recruiter->id)
            ->where('status', 'active')
            ->first();

        if (!$pin) {
            return back()->withErrors(['pin_code' => 'Stok PIN Anda tidak ditemukan atau sudah terpakai.']);
        }

        $tier = $uccBonusService->activateMemberWithPin(
            $downline,
            $pin,
            $recruiter,
            $validated['position'] ?? null
        );

        return back()->with('success', "Member {$downline->name} (@{$downline->username}) berhasil diaktifkan sebagai Paket " . ucfirst($tier) . '!');
    }
}

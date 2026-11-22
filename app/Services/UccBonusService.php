<?php

namespace App\Services;

use App\Models\BonusLog;
use App\Models\UccPointLedger;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WalletTransaction;
use App\Notifications\SystemActivityNotification;
use Illuminate\Support\Facades\DB;

class UccBonusService
{
    public const TIER_ACTIVATION_AMOUNTS = [
        'bronze' => 100000,
        'silver' => 400000,
    ];

    public const UPGRADE_TO_SILVER_COST = 400000;
    public const SILVER_CASHBACK_BELANJA = 50000;
    public const STOKIS_PACKAGE_PRICE = 5000000;       // 5 juta -> 50 PIN Bronze
    public const MINI_STOKIS_PACKAGE_PRICE = 1000000; // 1 juta -> 1 PIN Silver + 6 PIN Bronze
    public const STOKIS_FEE_RATE = 0.03;              // 3% x nilai aktivasi (10 stokis tercepat s.d. 1 Des 2026)
    public const MINI_STOKIS_FEE_RATE = 0.02;         // 2% x nilai aktivasi (100 mini stokis s.d. 1 Des 2026)
    public const STOKIS_PROMO_LIMIT = 10;
    public const MINI_STOKIS_PROMO_LIMIT = 100;
    public const STOKIS_PROMO_DEADLINE = '2026-12-01 23:59:59';
    public const UPGRADE_TRIGGER_POINTS = 1400;
    public const POINT_BLOCK_SIZE = 200;
    public const POINT_BLOCK_TOTAL_VALUE_IDR = 200000; // Prinsip dasar: 1 point = Rp 1.000, 200 point = Rp 200.000
    public const POINT_BLOCK_MITRA_VALUE_IDR = 100000; // Post Mitra per 200 point = Rp 100.000 (4 post saldo)
    public const POINT_BLOCK_INDUK_VALUE_IDR = 100000; // Post Induk per 200 point = Rp 100.000 (Lumbung Anggaran Kompleks & sniper cost)
    public const POINT_BLOCK_VALUE_IDR = self::POINT_BLOCK_MITRA_VALUE_IDR;
    public const IDR_PER_POINT = 1000;

    public const CLUSTER_ROYALTY_RATES = [
        'platinum' => 0.01,
        'gold' => 0.02,
        'silver' => 0.03,
        'bronze' => 0.04,
    ];

    public const SALDO_SPLIT_RATES = [
        'tunai' => 0.40,
        'belanja' => 0.30,
        'upgrade' => 0.20,
        'reward' => 0.10,
    ];

    /**
     * Resolve official activation fee for a given tier.
     */
    public static function getActivationAmount(string $tier): int
    {
        $normalized = strtolower(trim($tier));

        return self::TIER_ACTIVATION_AMOUNTS[$normalized] ?? self::TIER_ACTIVATION_AMOUNTS['bronze'];
    }

    /**
     * Process new member activation bonuses & cluster royalties (Tahap 1 UCC).
     */
    public function processActivation(User $newMember, ?User $sponsor, string $tier = 'bronze'): void
    {
        $normalizedTier = in_array(strtolower($tier), ['bronze', 'silver', 'gold', 'platinum'], true)
            ? strtolower($tier)
            : 'bronze';
        $activationAmount = self::getActivationAmount($normalizedTier);
        $refCode = 'ACT-' . $newMember->id . '-' . time();

        DB::transaction(function () use ($newMember, $sponsor, $normalizedTier, $activationAmount, $refCode) {
            $newMember->update([
                'member_tier' => $normalizedTier,
                'activation_amount' => $activationAmount,
                'package_name' => ucfirst($normalizedTier) . ' (Rp ' . number_format($activationAmount, 0, ',', '.') . ')',
                'has_silver_visa_card' => $normalizedTier === 'silver' ? true : (bool) $newMember->has_silver_visa_card,
            ]);

            // Cashback Saldo Belanja Rp 50.000 khusus Aktivasi Silver (Bronze non cashback)
            if ($normalizedTier === 'silver') {
                $newMember->increment('saldo_belanja', self::SILVER_CASHBACK_BELANJA);

                UccPointLedger::create([
                    'user_id' => $newMember->id,
                    'source_user_id' => $newMember->id,
                    'event_type' => 'silver_cashback',
                    'gross_amount' => self::SILVER_CASHBACK_BELANJA,
                    'points_added' => 0,
                    'saldo_belanja_added' => self::SILVER_CASHBACK_BELANJA,
                    'description' => 'Cashback Saldo Belanja Aktivasi Silver (Rp 50.000)',
                    'reference_code' => $refCode,
                ]);
            }

            // 1. Bonus Rekrut Aktivasi: 10% x nilai aktivasi untuk sponsor langsung
            if ($sponsor) {
                $recruitBonus = round($activationAmount * 0.10, 2);
                $this->creditMemberIncome(
                    recipient: $sponsor,
                    amount: $recruitBonus,
                    eventType: 'recruit_activation',
                    description: "Bonus Rekrut Aktivasi {$normalizedTier} dari @{$newMember->username}",
                    sourceUser: $newMember,
                    referenceCode: $refCode
                );
            }

            // 2. Bonus Royalty Aktivasi: 10% x nilai aktivasi dibagi 4 cluster (1% Plat, 2% Gold, 3% Silver, 4% Bronze)
            $this->distributeClusterRoyalty(
                baseAmount: $activationAmount,
                eventType: 'royalty_activation',
                descriptionPrefix: "Royalty Aktivasi {$normalizedTier} (@{$newMember->username})",
                sourceUser: $newMember,
                excludeUserId: $newMember->id,
                referenceCode: $refCode
            );
        });
    }

    /**
     * Distribute 10% cluster royalty across Platinum (1%), Gold (2%), Silver (3%), Bronze (4%).
     */
    public function distributeClusterRoyalty(
        float $baseAmount,
        string $eventType,
        string $descriptionPrefix,
        ?User $sourceUser = null,
        ?int $excludeUserId = null,
        string $referenceCode = ''
    ): void {
        foreach (self::CLUSTER_ROYALTY_RATES as $cluster => $rate) {
            $clusterPool = round($baseAmount * $rate, 2);
            if ($clusterPool <= 0) {
                continue;
            }

            $query = User::where('member_tier', $cluster);
            if ($excludeUserId !== null) {
                $query->where('id', '!=', $excludeUserId);
            }
            $members = $query->get();

            if ($members->isEmpty()) {
                continue;
            }

            $perMember = round($clusterPool / $members->count(), 2);
            if ($perMember <= 0) {
                continue;
            }

            foreach ($members as $member) {
                $this->creditMemberIncome(
                    recipient: $member,
                    amount: $perMember,
                    eventType: $eventType,
                    description: "{$descriptionPrefix} - Cluster " . ucfirst($cluster),
                    sourceUser: $sourceUser,
                    referenceCode: $referenceCode
                );
            }
        }
    }

    /**
     * Process transaction profit sharing:
     * 50% Post Induk, 50% Post Mitra (40% Bronze, 30% Silver).
     */
    public function processTransactionProfit(float $profitAmount, ?User $buyer = null, string $referenceCode = ''): array
    {
        if ($profitAmount <= 0) {
            return ['post_induk' => 0, 'post_mitra' => 0, 'bronze_each' => 0, 'silver_each' => 0];
        }

        $refCode = $referenceCode ?: 'TRX-' . time();
        $result = [];

        DB::transaction(function () use ($profitAmount, $buyer, $refCode, &$result) {
            $postInduk = round($profitAmount * 0.50, 2);
            $postMitra = round($profitAmount * 0.50, 2);

            $bronzePool = round($postMitra * 0.40, 2);
            $silverPool = round($postMitra * 0.30, 2);

            $bronzeMembers = User::where('member_tier', 'bronze')->get();
            $silverMembers = User::where('member_tier', 'silver')->get();

            $bronzeEach = $bronzeMembers->isNotEmpty() ? round($bronzePool / $bronzeMembers->count(), 2) : 0;
            $silverEach = $silverMembers->isNotEmpty() ? round($silverPool / $silverMembers->count(), 2) : 0;

            foreach ($bronzeMembers as $member) {
                if ($bronzeEach > 0) {
                    $this->creditMemberIncome(
                        recipient: $member,
                        amount: $bronzeEach,
                        eventType: 'royalty_transaction',
                        description: "Royalty Belanja Transaksi (40% Post Mitra - Cluster Bronze)",
                        sourceUser: $buyer,
                        referenceCode: $refCode
                    );
                }
            }

            foreach ($silverMembers as $member) {
                if ($silverEach > 0) {
                    $this->creditMemberIncome(
                        recipient: $member,
                        amount: $silverEach,
                        eventType: 'royalty_transaction',
                        description: "Royalty Belanja Transaksi (30% Post Mitra - Cluster Silver)",
                        sourceUser: $buyer,
                        referenceCode: $refCode
                    );
                }
            }

            $result = [
                'post_induk' => $postInduk,
                'post_mitra' => $postMitra,
                'bronze_each' => $bronzeEach,
                'silver_each' => $silverEach,
            ];
        });

        return $result;
    }

    /**
     * Credit income to member, convert to points, split every 200-point block (Rp 100.000)
     * into 4 post saldo (40% Tunai, 30% Belanja, 20% Upgrade, 10% Reward),
     * and evaluate Auto-Upgrade Bronze -> Silver at 1.400 points.
     */
    public function creditMemberIncome(
        User $recipient,
        float $amount,
        string $eventType,
        string $description,
        ?User $sourceUser = null,
        string $referenceCode = ''
    ): void {
        if ($amount <= 0) {
            return;
        }

        $pointsEarned = (int) floor($amount / self::IDR_PER_POINT);

        $recipient->increment('total_bonus', $amount);
        if ($pointsEarned > 0) {
            $recipient->increment('points', $pointsEarned);
        }
        $recipient->refresh();

        // Langsung distribusikan setiap bonus masuk ke 4 Post Saldo:
        // 40% Saldo Tunai (masuk E-Wallet), 30% Saldo Belanja, 20% Saldo Upgrade, 10% Saldo Reward
        $tunaiAdded = round($amount * self::SALDO_SPLIT_RATES['tunai'], 2);
        $belanjaAdded = round($amount * self::SALDO_SPLIT_RATES['belanja'], 2);
        $upgradeAdded = round($amount * self::SALDO_SPLIT_RATES['upgrade'], 2);
        $rewardAdded = round($amount - $tunaiAdded - $belanjaAdded - $upgradeAdded, 2);
        $indukAdded = 0.0;

        $recipient->increment('saldo_tunai', $tunaiAdded);
        $recipient->increment('saldo', $tunaiAdded);
        $recipient->increment('saldo_belanja', $belanjaAdded);
        $recipient->increment('saldo_upgrade', $upgradeAdded);
        $recipient->increment('saldo_reward', $rewardAdded);
        if ((int) $recipient->pending_points > 0) {
            $recipient->update(['pending_points' => 0]);
        }

        UccPointLedger::create([
            'user_id' => $recipient->id,
            'source_user_id' => $sourceUser?->id,
            'event_type' => $eventType,
            'gross_amount' => $amount,
            'points_added' => $pointsEarned,
            'saldo_tunai_added' => $tunaiAdded,
            'saldo_belanja_added' => $belanjaAdded,
            'saldo_upgrade_added' => $upgradeAdded,
            'saldo_reward_added' => $rewardAdded,
            'saldo_induk_added' => $indukAdded,
            'description' => $description,
            'reference_code' => $referenceCode,
        ]);

        BonusLog::create([
            'transaction_code' => 'UCC' . sprintf('%04d', BonusLog::count() + 1),
            'user_id' => $recipient->id,
            'category' => in_array($eventType, ['recruit_activation', 'recruit_upgrade'], true) ? 'sponsor' : 'tier',
            'source_user_id' => $sourceUser?->id,
            'description' => $description,
            'amount' => $amount,
        ]);

        if ($tunaiAdded > 0) {
            WalletTransaction::create([
                'user_id' => $recipient->id,
                'type' => 'in',
                'category' => $eventType,
                'amount' => $tunaiAdded,
                'description' => "{$description} (40% Saldo Tunai)",
            ]);
        }

        SystemActivityNotification::sendSafe(
            $recipient,
            new SystemActivityNotification(
                category: 'bonus',
                title: 'Bonus & Poin Masuk',
                message: "{$description} sebesar Rp " . number_format($amount, 0, ',', '.') . ($pointsEarned > 0 ? " (+{$pointsEarned} point)" : ''),
                link: '/admin/laporan?tab=bonus',
                roleTarget: 'member',
                meta: ['amount' => $amount, 'points' => $pointsEarned, 'event_type' => $eventType]
            )
        );

        $this->checkAndProcessAutoUpgrade($recipient, $referenceCode);
    }

    /**
     * Auto-upgrade Bronze member to Silver when reaching 1.400 points (or Rp 350.000 saldo_upgrade).
     * Grants Silver Visa Card and distributes 10% sponsor upgrade bonus + 10% cluster royalty upgrade.
     */
    public function checkAndProcessAutoUpgrade(User $member, string $referenceCode = ''): bool
    {
        $member->refresh();

        if (strtolower((string) $member->member_tier) !== 'bronze') {
            return false;
        }

        $reachedPoints = (int) $member->points >= self::UPGRADE_TRIGGER_POINTS;
        $reachedSaldoUpgrade = (float) $member->saldo_upgrade >= self::UPGRADE_TO_SILVER_COST;

        if (!$reachedPoints && !$reachedSaldoUpgrade) {
            return false;
        }

        $upgradeCost = self::UPGRADE_TO_SILVER_COST;
        $saldoUpgradeUsed = min((float) $member->saldo_upgrade, $upgradeCost);
        $sniperCostRequired = round($upgradeCost - $saldoUpgradeUsed, 2);
        $sniperCostDeducted = min((float) $member->saldo_induk, $sniperCostRequired);

        if ($saldoUpgradeUsed > 0) {
            $member->decrement('saldo_upgrade', $saldoUpgradeUsed);
        }
        if ($sniperCostDeducted > 0) {
            $member->decrement('saldo_induk', $sniperCostDeducted);
        }

        $member->update([
            'member_tier' => 'silver',
            'package_name' => 'Silver (Auto Upgrade Rp 350.000)',
            'has_silver_visa_card' => true,
            'upgraded_to_silver_at' => now(),
        ]);

        UccPointLedger::create([
            'user_id' => $member->id,
            'source_user_id' => $member->id,
            'event_type' => 'auto_upgrade',
            'gross_amount' => $upgradeCost,
            'points_added' => 0,
            'saldo_upgrade_added' => $saldoUpgradeUsed > 0 ? -$saldoUpgradeUsed : 0,
            'saldo_induk_added' => $sniperCostDeducted > 0 ? -$sniperCostDeducted : 0,
            'description' => 'Auto Upgrade Bronze ke Silver (Saldo Upgrade: Rp '
                . number_format($saldoUpgradeUsed, 0, ',', '.')
                . ', Sniper Cost Post Induk: Rp '
                . number_format($sniperCostRequired, 0, ',', '.')
                . ')',
            'reference_code' => $referenceCode ?: 'UPG-' . $member->id . '-' . time(),
        ]);

        // Ketika referral upgrade: yang mereferensikan dapat 10% x nilai upgrade
        if ($member->parent_id) {
            $sponsor = User::find($member->parent_id);
            if ($sponsor) {
                $upgradeSponsorBonus = round($upgradeCost * 0.10, 2);
                $this->creditMemberIncome(
                    recipient: $sponsor,
                    amount: $upgradeSponsorBonus,
                    eventType: 'recruit_upgrade',
                    description: "Bonus Aktivasi Upgrade Silver (10%) dari @{$member->username}",
                    sourceUser: $member,
                    referenceCode: $referenceCode ?: 'UPG-' . $member->id
                );
            }
        }

        // 10% dibagi lagi ke seluruh pemegang card sebagai royalty upgrade
        $this->distributeClusterRoyalty(
            baseAmount: $upgradeCost,
            eventType: 'royalty_upgrade',
            descriptionPrefix: "Royalty Upgrade Silver (@{$member->username})",
            sourceUser: $member,
            excludeUserId: $member->id,
            referenceCode: $referenceCode ?: 'UPG-' . $member->id
        );

        SystemActivityNotification::sendSafe(
            $member,
            new SystemActivityNotification(
                category: 'upgrade',
                title: 'Auto-Upgrade Silver Berhasil',
                message: 'Selamat! Anda telah mencapai 1.400 point dan otomatis naik ke tier Silver serta berhak menerima Card Visa Belanja Silver.',
                link: '/admin/dashboard',
                roleTarget: 'member'
            )
        );

        SystemActivityNotification::notifyAdmins(
            category: 'upgrade',
            title: 'Member Auto-Upgrade ke Silver',
            message: "{$member->name} (@{$member->username}) mencapai 1.400 point dan otomatis upgrade ke Silver.",
            link: '/admin/data-member'
        );

        return true;
    }

    /**
     * Resolve UCC activation tier ('bronze' or 'silver') from PIN package_name.
     */
    public static function resolveTierFromPin(Voucher $pin): string
    {
        $pkg = strtolower((string) $pin->package_name);
        if (str_contains($pkg, 'silver') || str_contains($pkg, '400') || str_contains($pkg, '350')) {
            return 'silver';
        }

        return 'bronze';
    }

    /**
     * Activate a free/inactive member using an active PIN (Bronze/Silver),
     * place them in the 2-leg binary tree (left/right), mark PIN as used,
     * and trigger UCC Tahap 1 activation bonus & cluster royalty.
     */
    public function activateMemberWithPin(
        User $member,
        Voucher $pin,
        ?User $activatedBy = null,
        ?string $preferredPosition = null
    ): string {
        $tier = self::resolveTierFromPin($pin);

        DB::transaction(function () use ($member, $pin, $activatedBy, $preferredPosition, $tier) {
            $lockedPin = Voucher::where('id', $pin->id)->lockForUpdate()->firstOrFail();
            if ($lockedPin->status !== 'active') {
                throw new \RuntimeException('Kode PIN sudah digunakan atau tidak aktif.');
            }

            $sponsor = $member->parent_id ? User::find($member->parent_id) : null;
            $position = $member->position;

            // Place in 2-leg binary tree (left/right) under sponsor/upline if not yet assigned
            if (empty($position) && $sponsor) {
                $position = in_array($preferredPosition, ['left', 'right'], true)
                    ? $preferredPosition
                    : (($sponsor->left_count <= $sponsor->right_count) ? 'left' : 'right');
            }

            $pinOwner = $lockedPin->user_id ? User::find($lockedPin->user_id) : $activatedBy;

            $lockedPin->update([
                'status' => 'used',
                'used_by_id' => $member->id,
                'used_at' => now(),
            ]);

            $member->update([
                'position' => $position ?: $member->position,
                'activation_status' => 'active',
                'activated_at' => now(),
                'activated_by' => $activatedBy?->id ?? $member->id,
            ]);

            if ($sponsor && $position === 'left') {
                $sponsor->increment('left_count');
            } elseif ($sponsor && $position === 'right') {
                $sponsor->increment('right_count');
            }

            $this->processActivation($member->fresh(), $sponsor, $tier);

            // Promo Fee Stokis (3%) & Mobile/Mini Stokis (2%) s.d. 1 Desember 2026
            if ($pinOwner && $pinOwner->stokis_fee_eligible && now()->lte(self::STOKIS_PROMO_DEADLINE)) {
                $activationAmount = self::getActivationAmount($tier);
                $feeRate = match ($pinOwner->stokis_type) {
                    'stokis' => self::STOKIS_FEE_RATE,
                    'mini_stokis' => self::MINI_STOKIS_FEE_RATE,
                    default => 0.0,
                };
                if ($feeRate > 0) {
                    $feeAmount = round($activationAmount * $feeRate, 2);
                    $feeLabel = $pinOwner->stokis_type === 'stokis' ? 'Fee Stokis (3%)' : 'Fee Mini Stokis (2%)';
                    $this->creditMemberIncome(
                        recipient: $pinOwner,
                        amount: $feeAmount,
                        eventType: $pinOwner->stokis_type === 'stokis' ? 'stokis_fee' : 'mini_stokis_fee',
                        description: "{$feeLabel} aktivasi {$tier} (@{$member->username})",
                        sourceUser: $member,
                        referenceCode: 'STOKIS-' . $member->id . '-' . time()
                    );
                }
            }
        });

        SystemActivityNotification::sendSafe(
            $member,
            new SystemActivityNotification(
                category: 'system',
                title: 'ID Member Resmi Aktif!',
                message: 'Selamat! Akun Anda telah aktif dengan Paket ' . ucfirst($tier) . ' menggunakan PIN ' . $pin->code . '. Silakan lengkapi data KTP & rekening Anda di menu Profil.',
                link: '/profile',
                roleTarget: 'member'
            )
        );

        if ($member->parent_id) {
            $sponsor = User::find($member->parent_id);
            SystemActivityNotification::sendSafe(
                $sponsor,
                new SystemActivityNotification(
                    category: 'network',
                    title: 'Downline Resmi Aktif (' . ucfirst($tier) . ')',
                    message: "{$member->name} (@{$member->username}) telah mengaktifkan ID Paket " . ucfirst($tier) . '.',
                    link: '/admin/team',
                    roleTarget: 'member'
                )
            );
        }

        SystemActivityNotification::notifyAdmins(
            category: 'network',
            title: 'Aktivasi PIN Member (' . ucfirst($tier) . ')',
            message: "{$member->name} (@{$member->username}) resmi aktif menggunakan PIN {$pin->code}.",
            link: '/admin/data-member'
        );

        return $tier;
    }
}

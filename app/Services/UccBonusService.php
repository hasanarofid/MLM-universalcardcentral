<?php

namespace App\Services;

use App\Models\BonusLog;
use App\Models\UccPointLedger;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\SystemActivityNotification;
use Illuminate\Support\Facades\DB;

class UccBonusService
{
    public const TIER_ACTIVATION_AMOUNTS = [
        'bronze' => 100000,
        'silver' => 350000,
    ];

    public const UPGRADE_TO_SILVER_COST = 350000;
    public const UPGRADE_TRIGGER_POINTS = 1400;
    public const POINT_BLOCK_SIZE = 200;
    public const POINT_BLOCK_VALUE_IDR = 100000; // 200 point = Rp 100.000 (Rp 500/point)
    public const IDR_PER_POINT = 500;

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
            $recipient->increment('pending_points', $pointsEarned);
        }
        $recipient->refresh();

        $tunaiAdded = 0.0;
        $belanjaAdded = 0.0;
        $upgradeAdded = 0.0;
        $rewardAdded = 0.0;

        // Pecah ke 4 post saldo setiap kelipatan 200 point (senilai Rp 100.000)
        $blocks = intdiv((int) $recipient->pending_points, self::POINT_BLOCK_SIZE);
        if ($blocks > 0) {
            $splitAmount = $blocks * self::POINT_BLOCK_VALUE_IDR;
            $tunaiAdded = round($splitAmount * self::SALDO_SPLIT_RATES['tunai'], 2);
            $belanjaAdded = round($splitAmount * self::SALDO_SPLIT_RATES['belanja'], 2);
            $upgradeAdded = round($splitAmount * self::SALDO_SPLIT_RATES['upgrade'], 2);
            $rewardAdded = round($splitAmount * self::SALDO_SPLIT_RATES['reward'], 2);

            $recipient->decrement('pending_points', $blocks * self::POINT_BLOCK_SIZE);
            $recipient->increment('saldo_tunai', $tunaiAdded);
            $recipient->increment('saldo', $tunaiAdded);
            $recipient->increment('saldo_belanja', $belanjaAdded);
            $recipient->increment('saldo_upgrade', $upgradeAdded);
            $recipient->increment('saldo_reward', $rewardAdded);
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
                'description' => "{$description} (40% Saldo Tunai dari pencairan 200 point)",
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
        if ((float) $member->saldo_upgrade >= $upgradeCost) {
            $member->decrement('saldo_upgrade', $upgradeCost);
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
            'description' => 'Auto Upgrade Bronze ke Silver (Berhak Card Visa Belanja Silver)',
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
}

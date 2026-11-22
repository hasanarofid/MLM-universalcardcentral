<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->where('total_bonus', '>', 0)
            ->get();

        foreach ($users as $user) {
            $alreadyDistributed = (float) ($user->saldo_tunai ?? 0)
                + (float) ($user->saldo_belanja ?? 0)
                + (float) ($user->saldo_upgrade ?? 0)
                + (float) ($user->saldo_reward ?? 0);

            $undistributed = round((float) $user->total_bonus - $alreadyDistributed, 2);
            if ($undistributed <= 0.01) {
                continue;
            }

            $tunai = round($undistributed * 0.40, 2);
            $belanja = round($undistributed * 0.30, 2);
            $upgrade = round($undistributed * 0.20, 2);
            $reward = round($undistributed - $tunai - $belanja - $upgrade, 2);

            DB::table('users')->where('id', $user->id)->update([
                'saldo_tunai' => (float) $user->saldo_tunai + $tunai,
                'saldo' => (float) $user->saldo + $tunai,
                'saldo_belanja' => (float) $user->saldo_belanja + $belanja,
                'saldo_upgrade' => (float) $user->saldo_upgrade + $upgrade,
                'saldo_reward' => (float) $user->saldo_reward + $reward,
                'pending_points' => 0,
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('wallet_transactions') && $tunai > 0) {
                DB::table('wallet_transactions')->insert([
                    'user_id' => $user->id,
                    'type' => 'in',
                    'category' => 'bonus_distribution',
                    'amount' => $tunai,
                    'description' => 'Distribusi otomatis 40% Saldo Tunai dari akumulasi bonus',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data backfill migration is non-reversible
    }
};


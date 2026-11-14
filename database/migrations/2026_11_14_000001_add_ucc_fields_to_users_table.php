<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'member_tier')) {
                $table->string('member_tier', 20)->default('bronze')->after('package_name')->index();
            }
            if (!Schema::hasColumn('users', 'activation_amount')) {
                $table->unsignedBigInteger('activation_amount')->default(100000)->after('member_tier');
            }
            if (!Schema::hasColumn('users', 'points')) {
                $table->unsignedInteger('points')->default(0)->after('activation_amount');
            }
            if (!Schema::hasColumn('users', 'pending_points')) {
                $table->unsignedInteger('pending_points')->default(0)->after('points');
            }
            if (!Schema::hasColumn('users', 'saldo_tunai')) {
                $table->decimal('saldo_tunai', 15, 2)->default(0)->after('saldo');
            }
            if (!Schema::hasColumn('users', 'saldo_belanja')) {
                $table->decimal('saldo_belanja', 15, 2)->default(0)->after('saldo_tunai');
            }
            if (!Schema::hasColumn('users', 'saldo_upgrade')) {
                $table->decimal('saldo_upgrade', 15, 2)->default(0)->after('saldo_belanja');
            }
            if (!Schema::hasColumn('users', 'saldo_reward')) {
                $table->decimal('saldo_reward', 15, 2)->default(0)->after('saldo_upgrade');
            }
            if (!Schema::hasColumn('users', 'saldo_induk')) {
                $table->decimal('saldo_induk', 15, 2)->default(0)->after('saldo_reward');
            }
            if (!Schema::hasColumn('users', 'has_silver_visa_card')) {
                $table->boolean('has_silver_visa_card')->default(false)->after('saldo_induk');
            }
            if (!Schema::hasColumn('users', 'has_nfc_card')) {
                $table->boolean('has_nfc_card')->default(false)->after('has_silver_visa_card');
            }
            if (!Schema::hasColumn('users', 'upgraded_to_silver_at')) {
                $table->timestamp('upgraded_to_silver_at')->nullable()->after('has_nfc_card');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'member_tier',
                'activation_amount',
                'points',
                'pending_points',
                'saldo_tunai',
                'saldo_belanja',
                'saldo_upgrade',
                'saldo_reward',
                'saldo_induk',
                'has_silver_visa_card',
                'has_nfc_card',
                'upgraded_to_silver_at',
            ];

            $existing = array_filter($columns, fn ($col) => Schema::hasColumn('users', $col));
            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};


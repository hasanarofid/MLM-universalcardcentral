<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users',
        'settings',
        'categories',
        'pages',
        'posts',
        'sections',
        'vouchers',
        'voucher_transfers',
        'wallet_transactions',
        'withdrawals',
        'bonus_logs',
        'products',
        'purchase_orders',
        'repeat_orders',
        'premi_payments',
        'team_point_redemptions',
        'tpr_requests',
        'ucc_point_ledgers',
        'notifications',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};


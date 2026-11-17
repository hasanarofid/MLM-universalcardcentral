<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'stokis_type')) {
                $table->string('stokis_type', 20)->default('none')->after('member_tier');
            }
            if (!Schema::hasColumn('users', 'stokis_activated_at')) {
                $table->timestamp('stokis_activated_at')->nullable()->after('stokis_type');
            }
            if (!Schema::hasColumn('users', 'stokis_fee_eligible')) {
                $table->boolean('stokis_fee_eligible')->default(false)->after('stokis_activated_at');
            }
        });

        if (Schema::hasTable('pin_requests') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE pin_requests MODIFY COLUMN tier VARCHAR(30) NOT NULL DEFAULT 'bronze'");
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['stokis_fee_eligible', 'stokis_activated_at', 'stokis_type'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

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
            if (!Schema::hasColumn('users', 'activation_status')) {
                $table->enum('activation_status', ['inactive', 'pending', 'active'])
                    ->default('inactive')
                    ->after('member_tier');
            }
            if (!Schema::hasColumn('users', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('activation_status');
            }
            if (!Schema::hasColumn('users', 'activated_by')) {
                $table->unsignedBigInteger('activated_by')->nullable()->after('activated_at');
            }
        });

        DB::table('users')->where('activation_status', 'inactive')->update([
            'activation_status' => 'active',
            'activated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['activated_by', 'activated_at', 'activation_status'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

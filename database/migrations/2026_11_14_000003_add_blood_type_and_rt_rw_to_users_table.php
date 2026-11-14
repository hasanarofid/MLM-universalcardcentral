<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'blood_type')) {
                $table->string('blood_type', 10)->nullable()->after('gender');
            }
            if (!Schema::hasColumn('users', 'rt_rw')) {
                $table->string('rt_rw', 20)->nullable()->after('address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = array_filter(['blood_type', 'rt_rw'], fn ($col) => Schema::hasColumn('users', $col));
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};


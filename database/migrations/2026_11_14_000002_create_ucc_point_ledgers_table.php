<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ucc_point_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50)->index();
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->integer('points_added')->default(0);
            $table->decimal('saldo_tunai_added', 15, 2)->default(0);
            $table->decimal('saldo_belanja_added', 15, 2)->default(0);
            $table->decimal('saldo_upgrade_added', 15, 2)->default(0);
            $table->decimal('saldo_reward_added', 15, 2)->default(0);
            $table->decimal('saldo_induk_added', 15, 2)->default(0);
            $table->string('description')->nullable();
            $table->string('reference_code', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ucc_point_ledgers');
    }
};


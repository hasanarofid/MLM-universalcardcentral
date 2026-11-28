<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $bcaPinBank = [
            'type' => 'bank',
            'bank_name' => 'Bank BCA',
            'account_number' => '4371382238',
            'bank_account_number' => '4371382238',
            'account_name' => 'Arief Lukman Halim',
            'bank_account_name' => 'Arief Lukman Halim',
        ];

        $existing = DB::table('settings')->where('key', 'company_banks')->first();
        $banks = [];
        if ($existing && !empty($existing->value)) {
            $decoded = json_decode($existing->value, true);
            if (is_array($decoded)) {
                $banks = array_values(array_filter($decoded, function ($b) {
                    return ($b['account_number'] ?? $b['bank_account_number'] ?? '') !== '4371382238'
                        && ($b['account_number'] ?? $b['bank_account_number'] ?? '') !== '806401000095564';
                }));
            }
        }

        array_unshift($banks, $bcaPinBank);

        if ($existing) {
            DB::table('settings')->where('key', 'company_banks')->update([
                'value' => json_encode($banks),
                'type' => 'json',
                'updated_at' => now(),
            ]);
        } else {
            DB::table('settings')->insert([
                'key' => 'company_banks',
                'value' => json_encode($banks),
                'type' => 'json',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep existing company_banks setting intact
    }
};

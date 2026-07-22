<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE wallets MODIFY currency VARCHAR(3) NOT NULL DEFAULT 'XOF'");
        }

        DB::table('wallets')->where('currency', 'INR')->update(['currency' => 'XOF']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE wallets MODIFY currency VARCHAR(3) NOT NULL DEFAULT 'INR'");
        }

        DB::table('wallets')->where('currency', 'XOF')->update(['currency' => 'INR']);
    }
};

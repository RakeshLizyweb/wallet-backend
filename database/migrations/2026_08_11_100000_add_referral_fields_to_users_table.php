<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable so existing users aren't broken by this migration —
            // ReferralService lazily generates and backfills one the first
            // time an existing user asks for their own code.
            $table->string('referral_code', 10)->nullable()->unique()->after('upi_handle');
            $table->foreignId('referred_by_id')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_id');
            $table->dropColumn('referral_code');
        });
    }
};

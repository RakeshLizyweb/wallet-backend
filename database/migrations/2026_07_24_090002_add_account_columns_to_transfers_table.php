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
        Schema::table('transfers', function (Blueprint $table) {
            $table->foreignId('sender_account_id')->nullable()->after('sender_wallet_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('receiver_account_id')->nullable()->after('receiver_wallet_id')->constrained('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sender_account_id');
            $table->dropConstrainedForeignId('receiver_account_id');
        });
    }
};

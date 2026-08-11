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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
            // The phone the referred person registered with, kept here
            // permanently and separately from users.phone — deleting an
            // account mangles users.phone (see AuthService::deleteAccount),
            // which would otherwise let someone delete + re-register with a
            // fresh users row and redeem a second referral code on the same
            // real phone number. This column is the actual anti-abuse check
            // and is intentionally unique: a phone can redeem at most once,
            // ever, regardless of how many user rows it has been through.
            $table->string('referred_phone')->unique();
            $table->string('code');
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};

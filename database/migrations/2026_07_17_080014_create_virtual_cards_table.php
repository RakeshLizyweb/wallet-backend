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
        Schema::create('virtual_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('identity_verification_id')->nullable()->constrained()->nullOnDelete();
            // Laravel's Crypt::encryptString() output (base64 JSON envelope with
            // iv/value/mac/tag) regularly exceeds 200+ chars — text() keeps this
            // immune to whatever the app's global default string length is.
            $table->text('card_number_encrypted');
            $table->string('card_number_last4', 4);
            $table->date('expiry_date');
            $table->string('status', 20)->default('inactive');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_cards');
    }
};

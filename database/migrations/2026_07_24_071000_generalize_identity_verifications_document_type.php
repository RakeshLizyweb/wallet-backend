<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('identity_verifications', function (Blueprint $table) {
            $table->string('document_type', 20)->default('passport');
            $table->string('document_number', 20)->nullable();
            $table->date('document_expiry')->nullable();
            $table->string('document_image_path')->nullable();
        });

        DB::statement(
            'UPDATE identity_verifications SET document_number = passport_number, '.
            'document_expiry = passport_expiry, document_image_path = passport_image_path'
        );

        Schema::table('identity_verifications', function (Blueprint $table) {
            $table->dropColumn(['passport_number', 'passport_expiry', 'passport_image_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('identity_verifications', function (Blueprint $table) {
            $table->string('passport_number')->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('passport_image_path')->nullable();
        });

        DB::statement(
            'UPDATE identity_verifications SET passport_number = document_number, '.
            'passport_expiry = document_expiry, passport_image_path = document_image_path'
        );

        Schema::table('identity_verifications', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'document_number', 'document_expiry', 'document_image_path']);
        });
    }
};

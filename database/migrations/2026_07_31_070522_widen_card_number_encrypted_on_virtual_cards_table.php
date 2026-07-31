<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widens card_number_encrypted from its original varchar length (which
     * shrank to 191 once Schema::defaultStringLength(191) was introduced) to
     * TEXT — Crypt::encryptString() output regularly exceeds 191/255 chars,
     * which was truncating inserts with a "Data too long" SQL error in
     * production. Raw SQL avoids needing doctrine/dbal for a column change;
     * SQLite (used in tests) doesn't enforce varchar length limits, so it
     * has nothing to fix here.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE virtual_cards MODIFY card_number_encrypted TEXT NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE virtual_cards MODIFY card_number_encrypted VARCHAR(191) NOT NULL');
    }
};

<?php

use App\Models\User;
use App\Services\AccountService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $service = app(AccountService::class);

        User::whereDoesntHave('account')->each(function (User $user) use ($service) {
            $service->createForUser($user);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank — dropping the accounts table (in the
        // create_accounts_table migration's down()) already removes this data.
    }
};

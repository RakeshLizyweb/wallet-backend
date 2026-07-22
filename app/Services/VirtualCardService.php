<?php

namespace App\Services;

use App\Enums\VirtualCardStatus;
use App\Models\IdentityVerification;
use App\Models\User;
use App\Models\VirtualCard;
use App\Repositories\Contracts\VirtualCardRepositoryInterface;
use Illuminate\Support\Facades\Crypt;

class VirtualCardService
{
    public function __construct(protected VirtualCardRepositoryInterface $virtualCards)
    {
    }

    public function getForUser(User $user): ?VirtualCard
    {
        return $this->virtualCards->findByUser($user);
    }

    public function activateForUser(User $user, IdentityVerification $verification): VirtualCard
    {
        $existing = $this->virtualCards->findByUser($user);

        if ($existing) {
            return $this->virtualCards->update($existing, [
                'identity_verification_id' => $verification->id,
                'status' => VirtualCardStatus::Active->value,
                'activated_at' => now(),
            ]);
        }

        $cardNumber = $this->generateMockCardNumber();

        return $this->virtualCards->create([
            'user_id' => $user->id,
            'identity_verification_id' => $verification->id,
            'card_number_encrypted' => Crypt::encryptString($cardNumber),
            'card_number_last4' => substr($cardNumber, -4),
            'expiry_date' => now()->addYears(3)->endOfMonth(),
            'status' => VirtualCardStatus::Active->value,
            'activated_at' => now(),
        ]);
    }

    protected function generateMockCardNumber(): string
    {
        $number = '4'.str_pad((string) random_int(0, 999999999999999), 15, '0', STR_PAD_LEFT);

        return substr($number, 0, 16);
    }
}

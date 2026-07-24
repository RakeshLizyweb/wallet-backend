<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\UserTier;
use App\Enums\VerificationStatus;
use App\Exceptions\ApiException;
use App\Models\IdentityVerification;
use App\Models\User;
use App\Repositories\Contracts\IdentityVerificationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VerificationService
{
    public function __construct(
        protected IdentityVerificationRepositoryInterface $verifications,
        protected VirtualCardService $virtualCardService,
        protected NotificationService $notificationService,
        protected CloudinaryService $cloudinary,
    ) {
    }

    public function submit(User $user, array $data, UploadedFile $documentImage, UploadedFile $selfieImage): IdentityVerification
    {
        $latest = $this->verifications->latestForUser($user);

        if ($latest && $latest->status === VerificationStatus::Approved) {
            throw new ApiException('Your identity has already been verified.', 422);
        }

        if ($latest && $latest->status === VerificationStatus::Pending) {
            throw new ApiException('Your previous verification request is still pending review.', 422);
        }

        $folder = "verifications/{$user->id}";
        $documentPublicId = $this->cloudinary->uploadPrivateImage($documentImage, $folder);
        $selfiePublicId = $this->cloudinary->uploadPrivateImage($selfieImage, $folder);

        return $this->verifications->create([
            'user_id' => $user->id,
            'document_type' => $this->documentTypeFor($user)->value,
            'document_number' => $data['document_number'],
            'document_expiry' => $data['document_expiry'],
            'document_image_path' => $documentPublicId,
            'selfie_image_path' => $selfiePublicId,
            'status' => VerificationStatus::Pending->value,
        ]);
    }

    /**
     * Ivory Coast nationals verify with their national citizen ID; everyone
     * else verifies with a passport. Decided server-side from the user's
     * stored nationality, not from client input, so it can't be spoofed.
     */
    public function documentTypeFor(User $user): DocumentType
    {
        return strcasecmp(trim((string) $user->nationality), 'Ivory Coast') === 0
            ? DocumentType::CitizenId
            : DocumentType::Passport;
    }

    public function latestForUser(User $user): ?IdentityVerification
    {
        return $this->verifications->latestForUser($user);
    }

    public function historyForUser(User $user): Collection
    {
        return $this->verifications->historyForUser($user);
    }

    public function approve(IdentityVerification $verification, User $admin): IdentityVerification
    {
        if ($verification->status !== VerificationStatus::Pending) {
            throw new ApiException('Only pending verifications can be approved.', 422);
        }

        return DB::transaction(function () use ($verification, $admin) {
            $verification->update([
                'status' => VerificationStatus::Approved->value,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $user = $verification->user;
            $user->update(['tier' => UserTier::Verified->value]);

            $this->virtualCardService->activateForUser($user, $verification);

            $this->notificationService->send(
                $user,
                'Identity verified',
                'Your identity has been verified. Your limits are increased and your virtual card is active.',
                'verification_approved'
            );

            return $verification->fresh();
        });
    }

    public function reject(IdentityVerification $verification, User $admin, string $reason): IdentityVerification
    {
        if ($verification->status !== VerificationStatus::Pending) {
            throw new ApiException('Only pending verifications can be rejected.', 422);
        }

        $verification->update([
            'status' => VerificationStatus::Rejected->value,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->notificationService->send(
            $verification->user,
            'Verification rejected',
            "Your identity verification was rejected: {$reason}",
            'verification_rejected'
        );

        return $verification->fresh();
    }

    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->verifications->paginateAll($filters, $perPage);
    }
}

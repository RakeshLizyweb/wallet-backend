<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectRequest;
use App\Http\Resources\Admin\AdminVerificationResource;
use App\Models\IdentityVerification;
use App\Services\VerificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationController extends Controller
{
    use ApiResponse;

    public function __construct(protected VerificationService $verificationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            AdminVerificationResource::collection($this->verificationService->paginateAll(
                $request->only(['status']),
                (int) $request->input('per_page', 20)
            ))
        );
    }

    public function approve(Request $request, IdentityVerification $verification): JsonResponse
    {
        $verification = $this->verificationService->approve($verification, $request->user());

        return $this->success(new AdminVerificationResource($verification), 'Verification approved.');
    }

    public function reject(RejectRequest $request, IdentityVerification $verification): JsonResponse
    {
        $verification = $this->verificationService->reject($verification, $request->user(), $request->reason);

        return $this->success(new AdminVerificationResource($verification), 'Verification rejected.');
    }

    public function passportImage(IdentityVerification $verification): StreamedResponse
    {
        return Storage::disk('local')->response($verification->passport_image_path);
    }

    public function selfieImage(IdentityVerification $verification): StreamedResponse
    {
        return Storage::disk('local')->response($verification->selfie_image_path);
    }
}

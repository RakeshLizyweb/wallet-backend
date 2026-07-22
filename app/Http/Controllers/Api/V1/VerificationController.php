<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Verification\SubmitVerificationRequest;
use App\Http\Resources\VerificationResource;
use App\Services\VerificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    use ApiResponse;

    public function __construct(protected VerificationService $verificationService)
    {
    }

    public function store(SubmitVerificationRequest $request): JsonResponse
    {
        $verification = $this->verificationService->submit(
            $request->user(),
            $request->only(['passport_number', 'passport_expiry']),
            $request->file('passport_image'),
            $request->file('selfie_image')
        );

        return $this->success(new VerificationResource($verification), 'Verification submitted for review.', 201);
    }

    public function show(Request $request): JsonResponse
    {
        $verification = $this->verificationService->latestForUser($request->user());

        return $this->success($verification ? new VerificationResource($verification) : null);
    }

    public function history(Request $request): JsonResponse
    {
        return $this->success(
            VerificationResource::collection($this->verificationService->historyForUser($request->user()))
        );
    }
}

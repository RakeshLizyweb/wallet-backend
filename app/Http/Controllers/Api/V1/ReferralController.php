<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReferralService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    use ApiResponse;

    public function __construct(protected ReferralService $referralService)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->success($this->referralService->summaryForUser($request->user()));
    }
}

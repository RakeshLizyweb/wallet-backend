<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LimitService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LimitController extends Controller
{
    use ApiResponse;

    public function __construct(protected LimitService $limitService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return $this->success($this->limitService->usageFor($request->user()));
    }
}

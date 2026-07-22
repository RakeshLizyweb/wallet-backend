<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminScratchCardResource;
use App\Services\RewardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardController extends Controller
{
    use ApiResponse;

    public function __construct(protected RewardService $rewardService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            AdminScratchCardResource::collection($this->rewardService->paginateAll(
                $request->only(['reward_type', 'is_redeemed']),
                (int) $request->input('per_page', 20)
            ))
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScratchCardResource;
use App\Models\ScratchCard;
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
            ScratchCardResource::collection($this->rewardService->listForUser(
                $request->user(),
                (int) $request->input('per_page', 20)
            ))
        );
    }

    public function summary(Request $request): JsonResponse
    {
        return $this->success($this->rewardService->summaryForUser($request->user()));
    }

    public function scratch(Request $request, ScratchCard $scratchCard): JsonResponse
    {
        $this->authorize('scratch', $scratchCard);

        $card = $this->rewardService->scratch($request->user(), $scratchCard);

        return $this->success(new ScratchCardResource($card), 'Scratch card revealed!');
    }

    public function redeem(Request $request, ScratchCard $scratchCard): JsonResponse
    {
        $this->authorize('redeem', $scratchCard);

        $card = $this->rewardService->redeem($request->user(), $scratchCard);

        return $this->success(new ScratchCardResource($card), 'Reward redeemed successfully.');
    }
}

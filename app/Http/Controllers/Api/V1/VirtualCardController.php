<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VirtualCardResource;
use App\Services\VirtualCardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VirtualCardController extends Controller
{
    use ApiResponse;

    public function __construct(protected VirtualCardService $virtualCardService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $card = $this->virtualCardService->getForUser($request->user());

        if (! $card) {
            return $this->error('You do not have an active virtual card yet. Complete identity verification to unlock one.', 404);
        }

        return $this->success(new VirtualCardResource($card));
    }
}

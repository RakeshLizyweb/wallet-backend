<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePinRequest;
use App\Http\Requests\Auth\SetPinRequest;
use App\Services\PinService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PinController extends Controller
{
    use ApiResponse;

    public function __construct(protected PinService $pinService)
    {
    }

    public function store(SetPinRequest $request): JsonResponse
    {
        $this->pinService->setPin($request->user(), $request->pin);

        return $this->success(null, 'PIN created successfully.');
    }

    public function update(ChangePinRequest $request): JsonResponse
    {
        $this->pinService->changePin($request->user(), $request->current_pin, $request->pin);

        return $this->success(null, 'PIN changed successfully.');
    }
}

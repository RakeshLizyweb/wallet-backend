<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReverseTransferRequest;
use App\Http\Resources\Admin\AdminTransferResource;
use App\Services\TransferService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use ApiResponse;

    public function __construct(protected TransferService $transferService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            AdminTransferResource::collection($this->transferService->paginateAll(
                $request->only(['type', 'status', 'from', 'to', 'search']),
                (int) $request->input('per_page', 20)
            ))
        );
    }

    public function show(string $reference): JsonResponse
    {
        return $this->success(new AdminTransferResource($this->transferService->findByReference($reference)));
    }

    public function reverse(ReverseTransferRequest $request, string $reference): JsonResponse
    {
        $transfer = $this->transferService->findByReference($reference);
        $transfer = $this->transferService->reverse($transfer, $request->reason);

        return $this->success(new AdminTransferResource($transfer), 'Transaction reversed.');
    }
}

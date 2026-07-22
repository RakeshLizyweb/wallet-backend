<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(protected AdminReportService $reportService)
    {
    }

    public function transactionsSummary(Request $request): JsonResponse
    {
        return $this->success($this->reportService->transactionsSummary($request->only(['from', 'to', 'status'])));
    }
}

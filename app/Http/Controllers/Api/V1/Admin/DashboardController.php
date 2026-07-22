<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(protected AdminDashboardService $dashboardService)
    {
    }

    public function index(): JsonResponse
    {
        return $this->success($this->dashboardService->stats());
    }
}

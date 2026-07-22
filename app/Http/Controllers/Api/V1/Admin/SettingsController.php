<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success([
            'wallet' => config('wallet'),
            'note' => 'These values are environment-driven (.env). Update the corresponding env vars and clear config cache to change them.',
        ]);
    }

    public function limits(): JsonResponse
    {
        return $this->success(config('wallet.limits'));
    }
}

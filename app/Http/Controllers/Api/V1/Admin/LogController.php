<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('super-admin')) {
            throw new ApiException('Only super admins can view application logs.', 403);
        }

        $path = storage_path('logs/laravel.log');

        if (! file_exists($path)) {
            return $this->success(['lines' => []]);
        }

        $lines = (int) $request->input('lines', 200);
        $content = file($path, FILE_IGNORE_NEW_LINES);
        $tail = array_slice($content, -$lines);

        return $this->success(['lines' => array_values($tail)]);
    }
}

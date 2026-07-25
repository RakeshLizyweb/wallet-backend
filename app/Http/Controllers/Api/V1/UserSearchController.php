<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\SearchUsersRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserSearchController extends Controller
{
    use ApiResponse;

    public function search(SearchUsersRequest $request): JsonResponse
    {
        $matches = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where('status', UserStatus::Active->value)
            ->where('phone', 'like', '%'.$request->phone.'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'upi_handle']);

        return $this->success($matches);
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ApiResponse;

    public function roles(): JsonResponse
    {
        return $this->success(Role::with('permissions:id,name')->get(['id', 'name']));
    }

    public function permissions(): JsonResponse
    {
        return $this->success(Permission::all(['id', 'name']));
    }
}

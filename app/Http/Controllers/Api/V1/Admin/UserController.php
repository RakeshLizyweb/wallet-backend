<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Enums\UserTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRoleRequest;
use App\Http\Requests\Admin\SetAdminCredentialsRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Requests\Admin\UpdateUserTierRequest;
use App\Http\Resources\UserResource;
use App\Services\AdminAuthService;
use App\Services\AdminUserService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AdminUserService $adminUserService,
        protected AdminAuthService $adminAuthService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'tier', 'nationality', 'search']);

        return $this->success(
            UserResource::collection($this->adminUserService->paginate($filters, (int) $request->input('per_page', 20)))
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(new UserResource($this->adminUserService->find($id)));
    }

    public function updateStatus(UpdateUserStatusRequest $request, int $id): JsonResponse
    {
        $user = $this->adminUserService->updateStatus(
            $this->adminUserService->find($id),
            UserStatus::from($request->status)
        );

        return $this->success(new UserResource($user), 'User status updated.');
    }

    public function updateTier(UpdateUserTierRequest $request, int $id): JsonResponse
    {
        $user = $this->adminUserService->updateTier(
            $this->adminUserService->find($id),
            UserTier::from($request->tier)
        );

        return $this->success(new UserResource($user), 'User tier updated.');
    }

    public function assignRole(AssignRoleRequest $request, int $id): JsonResponse
    {
        $user = $this->adminUserService->assignRole($this->adminUserService->find($id), $request->role);

        return $this->success(new UserResource($user), 'Role assigned.');
    }

    public function setCredentials(SetAdminCredentialsRequest $request, int $id): JsonResponse
    {
        $user = $this->adminAuthService->setCredentials(
            $this->adminUserService->find($id),
            $request->username,
            $request->password
        );

        return $this->success(new UserResource($user), 'Admin credentials set.');
    }
}

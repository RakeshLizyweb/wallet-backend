<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\LookupContactsRequest;
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

    /**
     * Given a batch of phone numbers (e.g. from the caller's device contacts), return which of
     * them belong to an active Zemapay user. Matching is done on the last 10 digits so contacts
     * saved with or without a country code / formatting (spaces, dashes, parentheses) still
     * match a phone number stored with its country code.
     */
    public function lookupContacts(LookupContactsRequest $request): JsonResponse
    {
        $normalized = collect($request->input('phones'))
            ->map(fn ($phone) => preg_replace('/\D/', '', (string) $phone))
            ->filter(fn ($digits) => strlen($digits) >= 6)
            ->map(fn ($digits) => substr($digits, -10))
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return $this->success([]);
        }

        $placeholders = implode(',', array_fill(0, $normalized->count(), '?'));

        $matches = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where('status', UserStatus::Active->value)
            ->whereRaw("RIGHT(phone, 10) IN ({$placeholders})", $normalized->all())
            ->get(['name', 'phone', 'upi_handle']);

        return $this->success($matches);
    }
}

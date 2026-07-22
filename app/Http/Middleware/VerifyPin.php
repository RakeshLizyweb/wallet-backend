<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\PinService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPin
{
    public function __construct(protected PinService $pinService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $pin = $request->header('X-Pin') ?? $request->input('pin');

        if (! $pin) {
            throw new ApiException('PIN is required for this action.', 422);
        }

        $this->pinService->verifyPin($request->user(), $pin);

        return $next($request);
    }
}

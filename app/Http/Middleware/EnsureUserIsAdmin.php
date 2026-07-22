<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasAnyRole(['super-admin', 'admin', 'support'])) {
            throw new ApiException('You are not authorized to access the admin panel.', 403);
        }

        return $next($request);
    }
}

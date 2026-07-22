<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * This is a JSON-only API; never redirect unauthenticated requests to a
     * "login" route (which doesn't exist here) regardless of the request's
     * Accept header. Returning null lets the AuthenticationException bubble
     * up to our JSON exception handler in bootstrap/app.php.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}

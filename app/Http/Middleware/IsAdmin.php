<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        if ($user && ($user->role == 1 || $user->is_admin == 1)) {
            return $next($request);
        }

        abort(403, 'Access Denied: Admins only.');
    }
}

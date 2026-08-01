<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckVendorActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->status == 0) {
            // Revoke the current token so it can't be used again
            $user->currentAccessToken()->delete();

            return response()->json([
                'status' => false,
                'message' => 'Your account has been deactivated. Please contact admin.',
                'account_inactive' => true,
            ], 403);
        }

        return $next($request);
    }
}

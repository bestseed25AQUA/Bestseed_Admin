<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Farmer;

class UpdateFarmerLastActive
{
    /**
     * Update last_active_at for authenticated farmer.
     * Only updates once every 2 minutes to avoid excessive DB writes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user && $user instanceof Farmer) {
            // Only update if last_active_at is null or older than 2 minutes
            if (!$user->last_active_at || $user->last_active_at->diffInMinutes(now()) >= 2) {
                $user->timestamps = false; // don't update updated_at
                $user->update(['last_active_at' => now()]);
                $user->timestamps = true;
            }
        }

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Log as AppLog;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

class isAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = Auth::user();

        if ($user->role_id == 1) {
            // Admin user: log their access and allow access to all routes
            if (!Session::has('admin_data_inserted')) {
                $loginTime = Carbon::now()->format('d F Y h:i A');
                Log::info('Admin action performed', [
                    'user_id' => $user->id,
                    'ip' => $request->getClientIp(),
                ]);
                AppLog::create([
                    'user' => $user->name,
                    'ipaddress' => $request->getClientIp(),
                    'login_time' => $loginTime,
                ]);
                Session::put('admin_data_inserted', true);
            }
            return $next($request);
        } elseif ($user->role_id > 1) {
            $role_id = User::where('id', Auth::user()->id)->first();
            $allowed_routes1 = Role::where('id', $role_id->role_id)->first();
            $allowed_routes = explode(',', $allowed_routes1->privileges);
         
            $current_route = $request->route()->getName();

            $resource_routes = $allowed_routes;
            $hasAccess = false;

            foreach ($resource_routes as $resource) {
                if (strpos($current_route, $resource) === 0) { 
                    $hasAccess = true;
                    break;
                }
            }

            if (in_array($current_route, $allowed_routes) || $hasAccess) {
                return $next($request);
            }
            else{
                return abort('403',"You don't have permission to access this page");
            }
        } else {
            abort('403');
        }
  







    }
}


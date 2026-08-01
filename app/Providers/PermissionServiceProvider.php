<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class PermissionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // @permission('vendors.view') ... @endpermission
        Blade::directive('permission', function ($permission) {
            return "<?php if(auth()->check() && (auth()->user()->isSuperAdmin() || auth()->user()->hasPermission({$permission}))): ?>";
        });

        Blade::directive('endpermission', function () {
            return "<?php endif; ?>";
        });

        // @role('super-admin') ... @endrole
        Blade::directive('role', function ($role) {
            return "<?php if(auth()->check() && auth()->user()->hasRole({$role})): ?>";
        });

        Blade::directive('endrole', function () {
            return "<?php endif; ?>";
        });

        // @superadmin ... @endsuperadmin
        Blade::directive('superadmin', function () {
            return "<?php if(auth()->check() && auth()->user()->isSuperAdmin()): ?>";
        });

        Blade::directive('endsuperadmin', function () {
            return "<?php endif; ?>";
        });
    }
}

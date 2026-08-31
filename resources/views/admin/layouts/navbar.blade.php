<!-- partial:partials/_sidebar.html -->
<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        @php
            $currentRoute = Route::currentRouteName();
            $currentAction = request()->segment(2); // Get the action part of the URL
            $isEditOrCreate = in_array($currentAction, ['create', 'edit']);

            // Function to check if current route matches a pattern
            function isActiveRoute($pattern, $currentRoute, $isEditOrCreate = false)
            {
                // If current route is exactly the pattern
                if ($currentRoute === $pattern) {
                    return true;
                }

                // Check if the pattern exists in the current route
                $isActive = str_contains($currentRoute, $pattern);

                // If it's an edit or create action, check the base route
   if ($isEditOrCreate) {
       $baseRoute = explode('.', $currentRoute)[0]; // Get the base route name
       return str_contains($baseRoute, $pattern);
   }

                return $isActive;
            }
        @endphp

        <li class="nav-item {{ $currentRoute == 'admin' ? 'active' : '' }}">
            <a class="nav-link {{ $currentRoute == 'admin' ? 'active' : '' }}" href="{{ route('admin') }}">
                <i class="fa fa-home menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>

        @if(auth()->user()->isSuperAdmin() || auth()->user()->hasAnyPermission(['vendors.view','hatcheries.view','hatchery-categories.view','hatchery-locations.view','hatchery-seeds.view','broad-stocks.view','spot-hatcheries.view','vehicle-availability.view']))
        <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#page-layouts"
                aria-expanded="{{ isActiveRoute('vendors', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('hatcheries', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('hatchery-categories', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('hatchery-locations', $currentRoute, $isEditOrCreate) ||
                 isActiveRoute('hatchery-seeds', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('spot-hatcheries', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('vehicle-availability', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('broad-stocks', $currentRoute, $isEditOrCreate)
                    ? 'true'
                    : 'false' }}"
                aria-controls="page-layouts">
                <i class="fas fa-store menu-icon"></i>
                <span class="menu-title">Vendors</span>
                <i class="menu-arrow"></i>
            </a>

            <div id="page-layouts"
                class="collapse {{ isActiveRoute('vendors', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('hatcheries', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('hatchery-categories', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('hatchery-locations', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('hatchery-seeds', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('spot-hatcheries', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('vehicle-availability', $currentRoute, $isEditOrCreate) ||
                isActiveRoute('broad-stocks', $currentRoute, $isEditOrCreate)
                    ? 'show'
                    : '' }}">
                <ul class="nav flex-column sub-menu">
                    @permission('vendors.view')
                    <li
                        class="nav-item {{ isActiveRoute('vendors', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('vendors.index') }}">Vendor Management</a>
                    </li>
                    @endpermission
                    @permission('hatcheries.view')
                    <li
                        class="nav-item {{ isActiveRoute('hatcheries', $currentRoute, $isEditOrCreate) && !str_contains($currentRoute, 'hatchery-') && !str_contains($currentRoute, 'spot-hatcheries') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('hatcheries.index') }}">Hatcheries</a>
                    </li>
                    @endpermission
                    @permission('hatchery-categories.view')
                    <li
                        class="nav-item {{ isActiveRoute('hatchery-categories', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('hatchery-categories.index') }}">Hatchery Categories</a>
                    </li>
                    @endpermission
                    @permission('hatchery-locations.view')
                    <li
                        class="nav-item {{ isActiveRoute('hatchery-locations', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('hatchery-locations.index') }}">Hatchery Locations</a>
                    </li>
                    @endpermission
                    {{-- Hatchery Seeds removed --}}
                    <li
                        class="nav-item {{ isActiveRoute('admin.seed-requests', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('admin.seed-requests.index') }}">Seed Requests (App)</a>
                    </li>
                    @permission('broad-stocks.view')
                    <li
                        class="nav-item {{ isActiveRoute('broad-stocks', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('broad-stocks.index') }}">Brood Stock</a>
                    </li>
                    @endpermission
                    @permission('spot-hatcheries.view')
                    <li
                        class="nav-item {{ isActiveRoute('spot-hatcheries', $currentRoute, $isEditOrCreate) && !str_contains($currentRoute, 'hatchery-') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('spot-hatcheries.index') }}">Spot Hatcheries</a>
                    </li>
                    @endpermission
                    @permission('vehicle-availability.view')
                    <li
                        class="nav-item {{ isActiveRoute('vehicle-availability', $currentRoute, $isEditOrCreate) && !str_contains($currentRoute, 'hatchery-') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('vehicle-availability.index') }}">Vehicle Availability</a>
                    </li>
                    @endpermission
                </ul>
            </div>

            <!-- spot hatcheries 11nov end -->
        </li>
        @endif

        @permission('farm-management.view')
        @php
            $farmMgmtActive = str_starts_with($currentRoute ?? '', 'farm-management.');
        @endphp
        <li class="nav-item {{ $farmMgmtActive ? 'active' : '' }}">
            <a class="nav-link" data-toggle="collapse" href="#farmManagement"
                aria-expanded="{{ $farmMgmtActive ? 'true' : 'false' }}" aria-controls="farmManagement">
                <i class="fas fa-water menu-icon"></i>
                <span class="menu-title">Farm Management</span>
                <i class="menu-arrow"></i>
            </a>
            <div id="farmManagement" class="collapse {{ $farmMgmtActive ? 'show' : '' }}">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item {{ str_starts_with($currentRoute ?? '', 'farm-management.farms') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('farm-management.farms.index') }}">Farms</a>
                    </li>
                    <li class="nav-item {{ str_starts_with($currentRoute ?? '', 'farm-management.team') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('farm-management.team.index') }}">Managers &amp; Partners</a>
                    </li>
                    <li class="nav-item {{ str_starts_with($currentRoute ?? '', 'farm-management.members') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('farm-management.members.index') }}">Who Has Access</a>
                    </li>
                </ul>
            </div>
        </li>
        @endpermission

        @permission('banners.view')
        <li class="nav-item {{ isActiveRoute('banners', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('banners.index') }}">
                <i class="fas fa-image menu-icon"></i>
                <span class="menu-title">Banners</span>
            </a>
        </li>
        @endpermission

        @permission('settings.view')
        <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#appConfig"
                aria-expanded="{{ isActiveRoute('contacts', $currentRoute, $isEditOrCreate) || isActiveRoute('app-config', $currentRoute, $isEditOrCreate) ? 'true' : 'false' }}"
                aria-controls="appConfig">
                <i class="fas fa-cogs menu-icon"></i>
                <span class="menu-title">App Configuration</span>
                <i class="menu-arrow"></i>
            </a>
            <div id="appConfig"
                class="collapse {{ isActiveRoute('contacts', $currentRoute, $isEditOrCreate) || isActiveRoute('app-config', $currentRoute, $isEditOrCreate) ? 'show' : '' }}">
                <ul class="nav flex-column sub-menu">
                    <li
                        class="nav-item {{ isActiveRoute('contacts', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('contacts.index') }}">Contacts</a>
                    </li>
                    <li
                        class="nav-item {{ $currentRoute === 'app-config.broodstock' ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('app-config.broodstock') }}">Broodstock</a>
                    </li>
                    <li
                        class="nav-item {{ $currentRoute === 'app-config.seed-price' ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('app-config.seed-price') }}">Price Page</a>
                    </li>
                    <li
                        class="nav-item {{ $currentRoute === 'app-config.booking-description' ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('app-config.booking-description') }}">Booking Status</a>
                    </li>
                </ul>
            </div>
        </li>
        @endpermission

        @permission('drivers.view')
        <li class="nav-item {{ isActiveRoute('drivers', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('drivers.index') }}">
                <i class="fas fa-id-card menu-icon"></i>
                <span class="menu-title">Drivers</span>
            </a>
        </li>
        @endpermission

        @permission('market-prices.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('market_prices.index') }}">
                <i class="fas fa-chart-bar menu-icon"></i>
                <span class="menu-title">Today Prices</span>
            </a>
        </li>
        @endpermission

        @permission('users.view')
        <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#customerManagement"
                aria-expanded="{{ isActiveRoute('user_profile', $currentRoute, $isEditOrCreate) ? 'true' : 'false' }}"
                aria-controls="customerManagement">
                <i class="fas fa-user-tie menu-icon"></i>
                <span class="menu-title">Customer Management</span>
                <i class="menu-arrow"></i>
            </a>

            <div id="customerManagement"
                class="collapse {{ isActiveRoute('user_profile', $currentRoute, $isEditOrCreate) ? 'show' : '' }}">
                <ul class="nav flex-column sub-menu">
                    <li
                        class="nav-item {{ isActiveRoute('user_profile', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('user_profile.index') }}">User Profile</a>
                    </li>
                </ul>
            </div>
        </li>
        @endpermission

        @permission('news.view')
        <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#updates-news-layouts"
                aria-expanded="{{ isActiveRoute('news', $currentRoute, $isEditOrCreate) || isActiveRoute('best-deals', $currentRoute, $isEditOrCreate) ? 'true' : 'false' }}"
                aria-controls="updates-news-layouts">
                <i class="fas fa-newspaper menu-icon"></i>
                <span class="menu-title">Ads & News</span>
                <i class="menu-arrow"></i>
            </a>

            <div id="updates-news-layouts"
                class="collapse {{ isActiveRoute('news', $currentRoute, $isEditOrCreate) || isActiveRoute('best-deals', $currentRoute, $isEditOrCreate) ? 'show' : '' }}">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item {{ isActiveRoute('news', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('news.index') }}">News</a>
                    </li>
                    <li class="nav-item {{ isActiveRoute('best-deals', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('best-deals.index') }}">Best Deals</a>
                    </li>
                </ul>
            </div>
        </li>
        @endpermission

        @permission('hatcheries.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('hatchery-updates.index') }}">
                <i class="fas fa-bullhorn menu-icon"></i>
                <span class="menu-title">Updates</span>
            </a>
        </li>
        @endpermission

        @permission('bookings.view')
        <li class="nav-item">
            <a class="nav-link" href="{{ route('bookings.index') }}">
                <i class="fas fa-calendar-check menu-icon"></i>
                <span class="menu-title">Bookings</span>
            </a>
        </li>
        @endpermission

        <li class="nav-item {{ isActiveRoute('seed-wanted-listings', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('seed-wanted-listings.index') }}">
                <i class="fas fa-seedling menu-icon"></i>
                <span class="menu-title">Wanted Stock</span>
            </a>
        </li>

        @permission('announcements.view')
        <li class="nav-item {{ isActiveRoute('announcements', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('announcements.index') }}">
                <i class="fas fa-bullhorn menu-icon"></i>
                <span class="menu-title">Announcements</span>
            </a>
        </li>
        @endpermission

        @permission('settings.view')
        <li class="nav-item {{ isActiveRoute('admin.notifications', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
            <a class="nav-link" href="{{ route('admin.notifications.index') }}">
                <i class="fas fa-bell menu-icon"></i>
                <span class="menu-title">Push Notifications</span>
            </a>
        </li>
        @endpermission

        @if (auth()->user()->isSuperAdmin() ||
                auth()->user()->hasPermission('roles.view') ||
                auth()->user()->hasPermission('sub-admins.view'))
            <li class="nav-item">
                <a class="nav-link" data-toggle="collapse" href="#admin-management"
                    aria-expanded="{{ isActiveRoute('admin.roles', $currentRoute, $isEditOrCreate) || isActiveRoute('admin.sub-admins', $currentRoute, $isEditOrCreate) ? 'true' : 'false' }}"
                    aria-controls="admin-management">
                    <i class="fas fa-user-shield menu-icon"></i>
                    <span class="menu-title">Admin Management</span>
                    <i class="menu-arrow"></i>
                </a>
                <div id="admin-management"
                    class="collapse {{ isActiveRoute('admin.roles', $currentRoute, $isEditOrCreate) || isActiveRoute('admin.sub-admins', $currentRoute, $isEditOrCreate) ? 'show' : '' }}">
                    <ul class="nav flex-column sub-menu">
                        @if (auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('roles.view'))
                            <li
                                class="nav-item {{ isActiveRoute('admin.roles', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.roles.index') }}">Roles</a>
                            </li>
                        @endif
                        @if (auth()->user()->isSuperAdmin() || auth()->user()->hasPermission('sub-admins.view'))
                            <li
                                class="nav-item {{ isActiveRoute('admin.sub-admins', $currentRoute, $isEditOrCreate) ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.sub-admins.index') }}">Sub Admins</a>
                            </li>
                        @endif
                    </ul>
                </div>
            </li>
        @endif

    </ul>
</nav>
<div class="main-panel">

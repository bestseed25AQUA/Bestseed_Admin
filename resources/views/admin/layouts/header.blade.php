<!DOCTYPE html>
<html lang="en">


<head>
  <!-- Required meta tags -->
   @php
        $site_settings = \App\Models\Setting::find(1);
    @endphp
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Best Seed</title>
  <!-- plugins:css -->
  <link rel="stylesheet" href="{{asset('admin_assets/vendors/iconfonts/font-awesome/css/all.min.css')}}">
  <link rel="stylesheet" href="{{asset('admin_assets/vendors/css/vendor.bundle.base.css')}}">
  <link rel="stylesheet" href="{{asset('admin_assets/vendors/css/vendor.bundle.addons.css')}}">


<!-- FilePond CSS -->


<link href="{{ asset('admin_assets/ravindra/css/filepond/dist/filepond-plugin-image-preview.css') }}" rel="stylesheet">
<link href="{{ asset('admin_assets/ravindra/css/filepond/dist/filepond.css') }}" rel="stylesheet">

{{-- <link href="https://unpkg.com/filepond/dist/filepond.css" rel="stylesheet">
<link href="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css" rel="stylesheet"> --}}


  <!-- endinject -->
  <!-- plugin css for this page -->
  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="{{asset('admin_assets/css/style.css')}}">

  <!-- Sea Theme - Glassmorphism -->
  {{-- <link rel="stylesheet" href="{{asset('admin_assets/css/sea-theme.css')}}"> --}}
  
<style>
/* PT Sans draws the wrong glyph at U+20B9, so the rupee came out as a peso.
   This maps that one codepoint to a system font and leaves every other
   character on the theme font. */
@font-face {
  font-family: 'RupeeGlyph';
  src: local('Segoe UI'), local('Noto Sans'), local('Nirmala UI'),
       local('Roboto'), local('Arial Unicode MS'), local('Arial');
  unicode-range: U+20B9;
}

body,
body .content-wrapper,
.table, .btn, .badge, .form-control, .swal2-container {
  font-family: 'RupeeGlyph', 'PTSans', sans-serif;
}

/* Summary cards that link to a filter */
a.stat-card-link,
a.stat-card-link:hover,
a.stat-card-link:focus,
a.stat-card-link:active,
a.stat-card-link * {
  text-decoration: none !important;
}

a.stat-card-link .card {
  transition: box-shadow .15s ease-in-out, transform .15s ease-in-out;
}

a.stat-card-link:hover .card {
  box-shadow: 0 4px 12px rgba(0, 0, 0, .12);
  transform: translateY(-1px);
}

/* Farmer autocomplete on the subscription form */
.farmer-suggestions {
  position: absolute;
  z-index: 1050;
  left: 15px;
  right: 15px;
  top: calc(100% - 20px);
  max-height: 320px;
  overflow-y: auto;
  background: #fff;
  border: 1px solid #dfe3e8;
  border-radius: 6px;
  box-shadow: 0 6px 18px rgba(0, 0, 0, .12);
}

.farmer-suggestion {
  display: block;
  width: 100%;
  text-align: left;
  padding: 9px 12px;
  border: 0;
  border-bottom: 1px solid #f1f3f5;
  background: #fff;
  cursor: pointer;
}

.farmer-suggestion:last-child { border-bottom: 0; }
.farmer-suggestion:hover,
.farmer-suggestion.is-active { background: #eef5ff; }

.farmer-suggestion-main { display: block; font-size: 14px; }
.farmer-suggestion-meta { display: block; font-size: 11.5px; color: #7b8794; margin-top: 2px; }
.farmer-suggestion-empty { padding: 12px; font-size: 13px; color: #7b8794; }

/* Count pill on a sidebar item */
.sidebar .nav .nav-item .nav-link .sidebar-count {
  margin-left: auto;
  margin-right: 8px;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: #e74c3c;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
  box-shadow: 0 1px 3px rgba(231, 76, 60, .35);
}

/* Navbar search container */
.navbar .navbar-nav .nav-search {
  position: relative;
  margin-left: 1rem;
}

/* Search input */
.navbar .navbar-nav .nav-search .search-input {
  border: 1px solid #a7e4ea;
  border-radius: 25px;
  padding: 0.5rem 1rem 0.5rem 2.6rem; /* leaves space for icon */
  width: 240px;
  background-color: #f1f9fa;
  transition: all 0.3s ease;
  box-shadow: 0 0 6px rgba(0,188,212,0.25); /* aqua glow always visible */
  font-size: 0.9rem;
  color: #333;
}

/* Placeholder color for visibility */
.navbar .navbar-nav .nav-search .search-input::placeholder {
  color: #6c757d;
  font-weight: 500;
}

/* On focus – stronger aqua glow */
.navbar .navbar-nav .nav-search .search-input:focus {
  border-color: #00bcd4;
  box-shadow: 0 0 10px rgba(0,188,212,0.45);
  background-color: #fff;
  width: 280px;
  outline: none;
}

/* Hover effect – slightly brighter aqua */
.navbar .navbar-nav .nav-search .search-input:hover {
  background-color: #eaf8f9;
  border-color: #00acc1;
  box-shadow: 0 0 8px rgba(0,188,212,0.35);
}

/* Search icon */
.navbar .navbar-nav .nav-search .search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #00acc1; /* Aqua color for clarity */
  z-index: 10;
  pointer-events: none;
  font-size: 1rem;
  opacity: 0.9;
}

/* Responsive view */
@media (max-width: 991px) {
  .navbar .navbar-nav .nav-search {
    width: 100%;
    margin: 10px 0;
  }

  .navbar .navbar-nav .nav-search .search-input {
    width: 100%;
  }

  .navbar .navbar-nav .nav-search .search-input:focus {
    width: 100%;
  }
}

/* Confirmation dialogs on the subscription screens */
.swal-tidy {
  border-radius: 10px;
  padding: 26px 30px 24px;
}

.swal-tidy .swal2-icon {
  width: 52px;
  height: 52px;
  margin: 4px auto 14px;
  border-width: 3px;
}

.swal-tidy .swal2-icon .swal2-icon-content {
  font-size: 2.1rem;
}

.swal-tidy-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #2f3542;
  padding: 0;
  margin-bottom: 4px;
}

.swal-tidy .swal2-html-container {
  margin: 14px 0 0;
  font-size: .9rem;
  color: #4b5563;
}

.swal-tidy .swal2-actions {
  margin-top: 22px;
  gap: 10px;
}

.swal-change {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  text-align: left;
  padding: 9px 0;
  border-bottom: 1px solid #eef0f4;
}

.swal-change:last-child {
  border-bottom: 0;
}

.swal-change-label {
  flex: 0 0 132px;
  font-weight: 600;
  font-size: .82rem;
  color: #6b7280;
}

.swal-change-from {
  font-size: .85rem;
  color: #9ca3af;
  text-decoration: line-through;
}

.swal-change-arrow {
  margin: 0 10px;
  color: #c3c9d4;
  font-size: .75rem;
}

.swal-change-to {
  font-size: .85rem;
  font-weight: 700;
  color: #14804a;
  background: #e7f6ed;
  border-radius: 4px;
  padding: 2px 9px;
}

.swal-note {
  text-align: left;
  font-size: .78rem;
  line-height: 1.5;
  color: #6b7280;
  background: #fbf8ef;
  border-left: 3px solid #e0a800;
  border-radius: 0 4px 4px 0;
  padding: 10px 12px;
  margin: 16px 0 0;
}

</style>

  @yield('csscodes')
  @stack('styles')

  @push('scripts')
  <script>
  // ── Global Inactive Driver Bell ──────────────────────────────────────
  (function () {
    var _dropdownOpen = false;

    window.toggleGlobalInactiveDropdown = function () {
      var dd = document.getElementById('globalInactiveDropdown');
      _dropdownOpen = !_dropdownOpen;
      dd.style.display = _dropdownOpen ? 'block' : 'none';
    };

    document.addEventListener('click', function (e) {
      if (!document.getElementById('globalInactiveDriverBell').contains(e.target)) {
        document.getElementById('globalInactiveDropdown').style.display = 'none';
        _dropdownOpen = false;
      }
    });

    function fetchGlobalInactiveDrivers() {
      $.get('{{ route("bookings.inactive-drivers") }}', { page: 1 }, function (data) {
        var badge  = document.getElementById('globalInactiveBadge');
        var list   = document.getElementById('globalInactiveDriverList');
        var count  = data.count || 0;

        badge.textContent = count > 9 ? '9+' : count;
        badge.style.display = count > 0 ? 'inline-block' : 'none';

        if (count === 0) {
          list.innerHTML = '<p class="text-muted text-center mb-0 py-3" style="font-size:13px;">No inactive drivers</p>';
          return;
        }

        var html = '';
        (data.drivers || []).forEach(function (d) {
          html += '<div style="padding:10px 14px;border-bottom:1px solid #f5f5f5;">'
                + '<div style="font-weight:600;font-size:13px;color:#333;">' + (d.driver_name || 'N/A') + '</div>'
                + '<div style="font-size:12px;color:#888;margin-top:2px;">'
                +   'Booking #' + d.booking_id
                +   ' &nbsp;|&nbsp; ' + (d.driver_mobile || '')
                + '</div>'
                + '<div style="font-size:12px;color:#e53935;margin-top:2px;">'
                +   '<i class="fas fa-exclamation-circle mr-1"></i>' + (d.reason || 'Unknown')
                + '</div>'
                + '<div style="font-size:11px;color:#aaa;margin-top:2px;">'
                +   'Last seen: ' + (d.driver_location_name || 'Unknown')
                +   ' &nbsp;(' + (d.minutes_inactive || 0) + ' min ago)'
                + '</div>'
                + '<a href="{{ url("admin/bookings") }}/' + d.booking_id + '/tracking" '
                +   'style="font-size:11px;color:#1976d2;" target="_blank">View Tracking &rarr;</a>'
                + '</div>';
        });
        list.innerHTML = html;
      }).fail(function () {
        // silently ignore if route not accessible
      });
    }

    // Start polling after DOM is ready
    $(document).ready(function () {
      fetchGlobalInactiveDrivers();
      setInterval(fetchGlobalInactiveDrivers, 60000); // every 60 seconds
    });
  })();
  </script>
  @endpush
  <!-- endinject -->
  {{-- <link rel="shortcut icon" href="http://www.urbanui.com/" /> --}}
</head>
<body>
  <div class="container-scroller">
    <!-- partial:partials/_navbar.html -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row default-layout-navbar">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center" style="background-color: #e6f0fa; padding: 0 15px;">
        <a class="navbar-brand brand-logo" href="{{ route('admin') }}">
            <span style="color: #0B94F7; font-weight: bold; font-size: 1.2rem;">Best Seed</span>
        </a>
        <a class="navbar-brand brand-logo-mini" href="{{ route('admin') }}">
            <img src="{{ asset('uploads/logo_68e5f26e3952d.png') }}" alt="{{ $site_settings->site_name ?? 'Best Seed' }}" style="height: 30px; width: auto;">
        </a>
      </div>
      <div class="navbar-menu-wrapper d-flex align-items-stretch">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
          <span class="fas fa-bars"></span>
        </button>
        {{-- <ul class="navbar-nav">
          <li class="nav-item nav-search d-none d-md-flex align-items-center">
            <div class="position-relative">
              <i class="fas fa-search search-icon"></i>
              <input type="text" class="form-control" placeholder="Search...">
            </div>
          </li>
        </ul> --}}
        <ul class="navbar-nav">
  <li class="nav-item nav-search d-none d-md-flex align-items-center">
    <div class="position-relative">
      {{-- <i class="fas fa-search search-icon"></i> --}}
      <input type="text" class="form-control search-input" placeholder="Search...">
    </div>
  </li>
</ul>


        <!-- Right side menu items -->
        <ul class="navbar-nav navbar-nav-right ml-auto">

          {{-- <li class="nav-item dropdown d-none d-lg-flex">
            <div class="nav-link">
              <span class="dropdown-toggle btn btn-outline-dark" id="languageDropdown" data-toggle="dropdown">English</span>
              <div class="dropdown-menu navbar-dropdown" aria-labelledby="languageDropdown">
                <a class="dropdown-item font-weight-medium" href="#">
                  French
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item font-weight-medium" href="#">
                  Espanol
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item font-weight-medium" href="#">
                  Latin
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item font-weight-medium" href="#">
                  Arabic
                </a>
              </div>
            </div>
          </li> --}}
          {{-- <li class="nav-item dropdown">
            <a class="nav-link count-indicator dropdown-toggle" id="notificationDropdown" href="#" data-toggle="dropdown">
              <i class="fas fa-bell mx-0"></i>
              <span class="count">16</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list" aria-labelledby="notificationDropdown">
              <a class="dropdown-item">
                <p class="mb-0 font-weight-normal float-left">You have 16 new notifications
                </p>
                <span class="badge badge-pill badge-warning float-right">View all</span>
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                  <div class="preview-icon bg-danger">
                    <i class="fas fa-exclamation-circle mx-0"></i>
                  </div>
                </div>
                <div class="preview-item-content">
                  <h6 class="preview-subject font-weight-medium">Application Error</h6>
                  <p class="font-weight-light small-text">
                    Just now
                  </p>
                </div>
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                  <div class="preview-icon bg-warning">
                    <i class="fas fa-wrench mx-0"></i>
                  </div>
                </div>
                <div class="preview-item-content">
                  <h6 class="preview-subject font-weight-medium">Settings</h6>
                  <p class="font-weight-light small-text">
                    Private message
                  </p>
                </div>
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                  <div class="preview-icon bg-info">
                    <i class="far fa-envelope mx-0"></i>
                  </div>
                </div>
                <div class="preview-item-content">
                  <h6 class="preview-subject font-weight-medium">New user registration</h6>
                  <p class="font-weight-light small-text">
                    2 days ago
                  </p>
                </div>
              </a>
            </div>
          </li> --}}
          {{-- <li class="nav-item dropdown">
            <a class="nav-link count-indicator dropdown-toggle" id="messageDropdown" href="#" data-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-envelope mx-0"></i>
              <span class="count">25</span>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list" aria-labelledby="messageDropdown">
              <div class="dropdown-item">
                <p class="mb-0 font-weight-normal float-left">You have 7 unread mails
                </p>
                <span class="badge badge-info badge-pill float-right">View all</span>
              </div>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                    <img src="images/faces/face4.jpg" alt="image" class="profile-pic">
                </div>
                <div class="preview-item-content flex-grow">
                  <h6 class="preview-subject ellipsis font-weight-medium">David Grey
                    <span class="float-right font-weight-light small-text">1 Minutes ago</span>
                  </h6>
                  <p class="font-weight-light small-text">
                    The meeting is cancelled
                  </p>
                </div>
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                    <img src="images/faces/face2.jpg" alt="image" class="profile-pic">
                </div>
                <div class="preview-item-content flex-grow">
                  <h6 class="preview-subject ellipsis font-weight-medium">Tim Cook
                    <span class="float-right font-weight-light small-text">15 Minutes ago</span>
                  </h6>
                  <p class="font-weight-light small-text">
                    New product launch
                  </p>
                </div>
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                    <img src="images/faces/face3.jpg" alt="image" class="profile-pic">
                </div>
                <div class="preview-item-content flex-grow">
                  <h6 class="preview-subject ellipsis font-weight-medium"> Johnson
                    <span class="float-right font-weight-light small-text">18 Minutes ago</span>
                  </h6>
                  <p class="font-weight-light small-text">
                    Upcoming board meeting
                  </p>
                </div>
              </a>
            </div>
          </li> --}}
            {{-- <li class="nav-item nav-settings d-none d-lg-block">
            <a class="nav-link" href="#">
              <i class="fas fa-ellipsis-h"></i>
            </a>
          </li> --}}
          {{-- ── Global Inactive Driver Bell ─────────────────────────── --}}
          @permission('bookings.view')
          <li class="nav-item d-flex align-items-center mr-2" id="globalInactiveDriverBell" style="position:relative;">
            <div class="bell-wrapper-global" onclick="toggleGlobalInactiveDropdown()" style="cursor:pointer;position:relative;padding:6px 10px;">
              <i class="fas fa-bell" style="font-size:1.2rem;color:#555;"></i>
              <span class="bell-badge-global" id="globalInactiveBadge"
                style="display:none;position:absolute;top:2px;right:4px;background:#e53935;color:#fff;
                       border-radius:50%;width:18px;height:18px;font-size:10px;font-weight:700;
                       line-height:18px;text-align:center;">0</span>
            </div>
            <div id="globalInactiveDropdown"
              style="display:none;position:absolute;top:48px;right:0;width:340px;max-height:420px;
                     background:#fff;border-radius:10px;box-shadow:0 6px 24px rgba(0,0,0,0.15);
                     z-index:9999;overflow:hidden;border:1px solid #eee;">
              <div style="background:#e53935;color:#fff;padding:10px 14px;font-weight:600;font-size:13px;">
                <i class="fas fa-exclamation-triangle mr-1"></i> Inactive Drivers
              </div>
              <div id="globalInactiveDriverList" style="max-height:340px;overflow-y:auto;padding:8px 0;">
                <p class="text-muted text-center mb-0 py-3" style="font-size:13px;">No inactive drivers</p>
              </div>
              <div style="padding:8px 14px;border-top:1px solid #eee;text-align:center;">
                <a href="{{ route('bookings.index') }}" style="font-size:12px;color:#1976d2;">View All Bookings</a>
              </div>
            </div>
          </li>
          @endpermission

          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
              <img src="{{asset('uploads/man.png')}}" alt="profile"/>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
              <a class="dropdown-item {{ request()->is('site-settings*') ? 'active' : '' }}" href="{{ route('site-settings.edit', 1) }}">
                <i class="fas fa-cog text-primary"></i>
               Site Settings
              </a>
 {{-- <a class="dropdown-item {{ request()->is('site-settings*') ? 'active' : '' }}" href="{{ route('site-settings.edit', 1) }}">
                <i class="fas fa-cog text-primary"></i>
                Site Settings
              </a> --}}
              <a class="dropdown-item {{ request()->is('admin/profile*') ? 'active' : '' }}" href="{{ route('admin_profile') }}">
                <i class="fas fa-user text-primary"></i>
                My Profile
              </a>
              <div class="dropdown-divider"></div>
             <a href="{{ route('logout') }}" class="dropdown-item" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
    <i class="fas fa-power-off text-primary"></i>
    Logout
</a>

<!-- This form is needed for the logout POST request -->
<form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
    @csrf
</form>
            </div>
          </li>

        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="fas fa-bars"></span>
        </button>
      </div>
    </nav>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_settings-panel.html -->
      <div class="theme-setting-wrapper">
        <div id="settings-trigger"><i class="fas fa-fill-drip"></i></div>
        <div id="theme-settings" class="settings-panel">
          <i class="settings-close fa fa-times"></i>
          <p class="settings-heading">SIDEBAR SKINS</p>
          <div class="sidebar-bg-options selected" id="sidebar-light-theme"><div class="img-ss rounded-circle bg-light border mr-3"></div>Light</div>
          <div class="sidebar-bg-options" id="sidebar-dark-theme"><div class="img-ss rounded-circle bg-dark border mr-3"></div>Dark</div>
          <p class="settings-heading mt-2">HEADER SKINS</p>
          <div class="color-tiles mx-0 px-4">
            <div class="tiles primary"></div>
            <div class="tiles success"></div>
            <div class="tiles warning"></div>
            <div class="tiles danger"></div>
            <div class="tiles info"></div>
            <div class="tiles dark"></div>
            <div class="tiles default"></div>
          </div>
        </div>
      </div>
      <div id="right-sidebar" class="settings-panel">
        <i class="settings-close fa fa-times"></i>
        <ul class="nav nav-tabs" id="setting-panel" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="todo-tab" data-toggle="tab" href="#todo-section" role="tab" aria-controls="todo-section" aria-expanded="true">TO DO LIST</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="chats-tab" data-toggle="tab" href="#chats-section" role="tab" aria-controls="chats-section">CHATS</a>
          </li>
        </ul>
        <div class="tab-content" id="setting-content">
          <div class="tab-pane fade show active scroll-wrapper" id="todo-section" role="tabpanel" aria-labelledby="todo-section">
            <div class="add-items d-flex px-3 mb-0">
              <form class="form w-100">
                <div class="form-group d-flex">
                  <input type="text" class="form-control todo-list-input" placeholder="Add To-do">
                  <button type="submit" class="add btn btn-primary todo-list-add-btn" id="add-task-todo">Add</button>
                </div>
              </form>
            </div>
            <div class="list-wrapper px-3">
              <ul class="d-flex flex-column-reverse todo-list">
                <li>
                  <div class="form-check">
                    <label class="form-check-label">
                      <input class="checkbox" type="checkbox">
                      Team review meeting at 3.00 PM
                    </label>
                  </div>
                  <i class="remove fa fa-times-circle"></i>
                </li>
                <li>
                  <div class="form-check">
                    <label class="form-check-label">
                      <input class="checkbox" type="checkbox">
                      Prepare for presentation
                    </label>
                  </div>
                  <i class="remove fa fa-times-circle"></i>
                </li>
                <li>
                  <div class="form-check">
                    <label class="form-check-label">
                      <input class="checkbox" type="checkbox">
                      Resolve all the low priority tickets due today
                    </label>
                  </div>
                  <i class="remove fa fa-times-circle"></i>
                </li>
                <li class="completed">
                  <div class="form-check">
                    <label class="form-check-label">
                      <input class="checkbox" type="checkbox" checked>
                      Schedule meeting for next week
                    </label>
                  </div>
                  <i class="remove fa fa-times-circle"></i>
                </li>
                <li class="completed">
                  <div class="form-check">
                    <label class="form-check-label">
                      <input class="checkbox" type="checkbox" checked>
                      Project review
                    </label>
                  </div>
                  <i class="remove fa fa-times-circle"></i>
                </li>
              </ul>
            </div>
            <div class="events py-4 border-bottom px-3">
              <div class="wrapper d-flex mb-2">
                <i class="fa fa-times-circle text-primary mr-2"></i>
                <span>Feb 11 2018</span>
              </div>
              <p class="mb-0 font-weight-thin text-gray">Creating component page</p>
              <p class="text-gray mb-0">build a js based app</p>
            </div>
            <div class="events pt-4 px-3">
              <div class="wrapper d-flex mb-2">
                <i class="fa fa-times-circle text-primary mr-2"></i>
                <span>Feb 7 2018</span>
              </div>
              <p class="mb-0 font-weight-thin text-gray">Meeting with Alisa</p>
              <p class="text-gray mb-0 ">Call Sarah Graves</p>
            </div>
          </div>
          <!-- To do section tab ends -->
          <div class="tab-pane fade" id="chats-section" role="tabpanel" aria-labelledby="chats-section">
            <div class="d-flex align-items-center justify-content-between border-bottom">
              <p class="settings-heading border-top-0 mb-3 pl-3 pt-0 border-bottom-0 pb-0">Friends</p>
              <small class="settings-heading border-top-0 mb-3 pt-0 border-bottom-0 pb-0 pr-3 font-weight-normal">See All</small>
            </div>
            <ul class="chat-list">
              <li class="list active">
                <div class="profile"><img src="images/faces/face1.jpg" alt="image"><span class="online"></span></div>
                <div class="info">
                  <p>Thomas Douglas</p>
                  <p>Available</p>
                </div>
                <small class="text-muted my-auto">19 min</small>
              </li>
              <li class="list">
                <div class="profile"><img src="images/faces/face2.jpg" alt="image"><span class="offline"></span></div>
                <div class="info">
                  <div class="wrapper d-flex">
                    <p>Catherine</p>
                  </div>
                  <p>Away</p>
                </div>
                <div class="badge badge-success badge-pill my-auto mx-2">4</div>
                <small class="text-muted my-auto">23 min</small>
              </li>
              <li class="list">
                <div class="profile"><img src="images/faces/face3.jpg" alt="image"><span class="online"></span></div>
                <div class="info">
                  <p>Daniel Russell</p>
                  <p>Available</p>
                </div>
                <small class="text-muted my-auto">14 min</small>
              </li>
              <li class="list">
                <div class="profile"><img src="images/faces/face4.jpg" alt="image"><span class="offline"></span></div>
                <div class="info">
                  <p>James Richardson</p>
                  <p>Away</p>
                </div>
                <small class="text-muted my-auto">2 min</small>
              </li>
              <li class="list">
                <div class="profile"><img src="images/faces/face5.jpg" alt="image"><span class="online"></span></div>
                <div class="info">
                  <p>Madeline Kennedy</p>
                  <p>Available</p>
                </div>
                <small class="text-muted my-auto">5 min</small>
              </li>
              <li class="list">
                <div class="profile"><img src="images/faces/face6.jpg" alt="image"><span class="online"></span></div>
                <div class="info">
                  <p>Sarah Graves</p>
                  <p>Available</p>
                </div>
                <small class="text-muted my-auto">47 min</small>
              </li>
            </ul>
          </div>
          <!-- chat tab ends -->
        </div>
      </div>
   



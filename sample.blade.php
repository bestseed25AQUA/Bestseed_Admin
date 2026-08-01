<!DOCTYPE html>
<html lang="en">


<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Best Seeds</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="http://127.0.0.1:8000/admin_assets/vendors/iconfonts/font-awesome/css/all.min.css">
    <link rel="stylesheet" href="http://127.0.0.1:8000/admin_assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="http://127.0.0.1:8000/admin_assets/vendors/css/vendor.bundle.addons.css">


    <!-- FilePond CSS -->


    <link href="http://127.0.0.1:8000/admin_assets/ravindra/css/filepond/dist/filepond-plugin-image-preview.css"
        rel="stylesheet">
    <link href="http://127.0.0.1:8000/admin_assets/ravindra/css/filepond/dist/filepond.css" rel="stylesheet">




    <!-- endinject -->
    <!-- plugin css for this page -->
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="http://127.0.0.1:8000/admin_assets/css/style.css">
    <style>
        /* Navbar search container */
        .navbar .navbar-nav .nav-search {
            position: relative;
            margin-left: 1rem;
        }

        /* Search input */
        .navbar .navbar-nav .nav-search .search-input {
            border: 1px solid #a7e4ea;
            border-radius: 25px;
            padding: 0.5rem 1rem 0.5rem 2.6rem;
            /* leaves space for icon */
            width: 240px;
            background-color: #f1f9fa;
            transition: all 0.3s ease;
            box-shadow: 0 0 6px rgba(0, 188, 212, 0.25);
            /* aqua glow always visible */
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
            box-shadow: 0 0 10px rgba(0, 188, 212, 0.45);
            background-color: #fff;
            width: 280px;
            outline: none;
        }

        /* Hover effect – slightly brighter aqua */
        .navbar .navbar-nav .nav-search .search-input:hover {
            background-color: #eaf8f9;
            border-color: #00acc1;
            box-shadow: 0 0 8px rgba(0, 188, 212, 0.35);
        }

        /* Search icon */
        .navbar .navbar-nav .nav-search .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #00acc1;
            /* Aqua color for clarity */
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
    </style>

    <!-- endinject -->

</head>

<body>
    <div class="container-scroller">
        <!-- partial:partials/_navbar.html -->
        <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row default-layout-navbar">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center"
                style="background-color: #e6f0fa; padding: 0 15px;">
                <a class="navbar-brand brand-logo" href="http://127.0.0.1:8000/admin">
                    <span style="color: #0B94F7; font-weight: bold; font-size: 1.2rem;">Best Seeds</span>
                </a>
                <a class="navbar-brand brand-logo-mini" href="http://127.0.0.1:8000/admin">
                    <img src="http://127.0.0.1:8000/uploads/logo_68e5f26e3952d.png" alt="Best Seeds"
                        style="height: 30px; width: auto;">
                </a>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-stretch">
                <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                    <span class="fas fa-bars"></span>
                </button>

                <ul class="navbar-nav">
                    <li class="nav-item nav-search d-none d-md-flex align-items-center">
                        <div class="position-relative">

                            <input type="text" class="form-control search-input" placeholder="Search...">
                        </div>
                    </li>
                </ul>


                <!-- Right side menu items -->
                <ul class="navbar-nav navbar-nav-right ml-auto">





                    <li class="nav-item nav-profile dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
                            <img src="http://127.0.0.1:8000/uploads/man.png" alt="profile" />
                        </a>
                        <div class="dropdown-menu dropdown-menu-right navbar-dropdown"
                            aria-labelledby="profileDropdown">
                            <a class="dropdown-item " href="http://127.0.0.1:8000/admin/site-settings/1/edit">
                                <i class="fas fa-cog text-primary"></i>
                                Site Settings
                            </a>

                            <a class="dropdown-item " href="http://127.0.0.1:8000/admin/profile">
                                <i class="fas fa-user text-primary"></i>
                                My Profile
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="http://127.0.0.1:8000/logout" class="dropdown-item"
                                onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="fas fa-power-off text-primary"></i>
                                Logout
                            </a>

                            <!-- This form is needed for the logout POST request -->
                            <form id="logout-form" action="http://127.0.0.1:8000/logout" method="POST"
                                style="display: none;">
                                <input type="hidden" name="_token" value="AZD3Q9b5BBHGWbAJluS64faCZTirx0Ilm6ISpN5l"
                                    autocomplete="off">
                            </form>
                        </div>
                    </li>

                </ul>
                <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button"
                    data-toggle="offcanvas">
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
                    <div class="sidebar-bg-options selected" id="sidebar-light-theme">
                        <div class="img-ss rounded-circle bg-light border mr-3"></div>Light
                    </div>
                    <div class="sidebar-bg-options" id="sidebar-dark-theme">
                        <div class="img-ss rounded-circle bg-dark border mr-3"></div>Dark
                    </div>
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
                        <a class="nav-link active" id="todo-tab" data-toggle="tab" href="#todo-section"
                            role="tab" aria-controls="todo-section" aria-expanded="true">TO DO LIST</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="chats-tab" data-toggle="tab" href="#chats-section" role="tab"
                            aria-controls="chats-section">CHATS</a>
                    </li>
                </ul>
                <div class="tab-content" id="setting-content">
                    <div class="tab-pane fade show active scroll-wrapper" id="todo-section" role="tabpanel"
                        aria-labelledby="todo-section">
                        <div class="add-items d-flex px-3 mb-0">
                            <form class="form w-100">
                                <div class="form-group d-flex">
                                    <input type="text" class="form-control todo-list-input"
                                        placeholder="Add To-do">
                                    <button type="submit" class="add btn btn-primary todo-list-add-btn"
                                        id="add-task-todo">Add</button>
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
                            <small
                                class="settings-heading border-top-0 mb-3 pt-0 border-bottom-0 pb-0 pr-3 font-weight-normal">See
                                All</small>
                        </div>
                        <ul class="chat-list">
                            <li class="list active">
                                <div class="profile"><img src="images/faces/face1.jpg" alt="image"><span
                                        class="online"></span></div>
                                <div class="info">
                                    <p>Thomas Douglas</p>
                                    <p>Available</p>
                                </div>
                                <small class="text-muted my-auto">19 min</small>
                            </li>
                            <li class="list">
                                <div class="profile"><img src="images/faces/face2.jpg" alt="image"><span
                                        class="offline"></span></div>
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
                                <div class="profile"><img src="images/faces/face3.jpg" alt="image"><span
                                        class="online"></span></div>
                                <div class="info">
                                    <p>Daniel Russell</p>
                                    <p>Available</p>
                                </div>
                                <small class="text-muted my-auto">14 min</small>
                            </li>
                            <li class="list">
                                <div class="profile"><img src="images/faces/face4.jpg" alt="image"><span
                                        class="offline"></span></div>
                                <div class="info">
                                    <p>James Richardson</p>
                                    <p>Away</p>
                                </div>
                                <small class="text-muted my-auto">2 min</small>
                            </li>
                            <li class="list">
                                <div class="profile"><img src="images/faces/face5.jpg" alt="image"><span
                                        class="online"></span></div>
                                <div class="info">
                                    <p>Madeline Kennedy</p>
                                    <p>Available</p>
                                </div>
                                <small class="text-muted my-auto">5 min</small>
                            </li>
                            <li class="list">
                                <div class="profile"><img src="images/faces/face6.jpg" alt="image"><span
                                        class="online"></span></div>
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




            <!-- partial:partials/_sidebar.html -->
            <nav class="sidebar sidebar-offcanvas" id="sidebar">
                <ul class="nav">


                    <li class="nav-item active">
                        <a class="nav-link" href="http://127.0.0.1:8000/admin">
                            <i class="fa fa-home menu-icon"></i>
                            <span class="menu-title">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="collapse" href="#page-layouts" aria-expanded="false"
                            aria-controls="page-layouts">
                            <i class="fas fa-store menu-icon"></i>

                            <span class="menu-title">Vendors</span>
                            <i class="menu-arrow"></i>
                        </a>
                        <div class="collapse" id="page-layouts">
                            <ul class="nav flex-column sub-menu">

                                <li class="nav-item ">
                                    <a class="nav-link" href="http://127.0.0.1:8000/admin/vendors">Vendor
                                        Management</a>
                                </li>
                                <li class="nav-item ">
                                    <a class="nav-link" href="http://127.0.0.1:8000/admin/hatcheries">Hatcheries</a>
                                </li>
                                <li class="nav-item ">
                                    <a class="nav-link"
                                        href="http://127.0.0.1:8000/admin/hatchery-categories">Hatchery Categories</a>
                                </li>
                                <li class="nav-item ">
                                    <a class="nav-link" href="http://127.0.0.1:8000/admin/hatchery-locations">Hatchery
                                        Locations</a>
                                </li>
                                <li class="nav-item ">
                                    <a class="nav-link" href="http://127.0.0.1:8000/admin/hatchery-updates">Hatchery
                                        Updates</a>
                                </li>
                                <li class="nav-item ">
                                    <a class="nav-link" href="http://127.0.0.1:8000/admin/hatchery-seeds">Hatchery
                                        Seeds</a>
                                </li>
                                <li class="nav-item ">
                                    <a class="nav-link" href="http://127.0.0.1:8000/admin/broad-stocks">Broad
                                        Stock</a>
                                </li>
                                <li class="nav-item ">
                                    <a class="nav-link" href="http://127.0.0.1:8000/admin/bookings">Bookings</a>
                                </li>


                                <li class="nav-item"> <a class="nav-link" href="pages/layout/rtl-layout.html">RTL</a>
                                </li>

                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="http://127.0.0.1:8000/admin/banners">
                            <i class="fas fa-image menu-icon"></i>
                            <span class="menu-title">Banners</span>
                        </a>
                    </li>




                </ul>
            </nav>
            <div class="main-panel">

                <!-- partial -->

                <div class="content-wrapper">
                    <div class="page-header">
                        <h3 class="page-title">
                            Dashboard
                        </h3>
                    </div>

                    <div class="row">
                        <div class="col-12 grid-margin">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">
                                        <i class="fas fa-envelope"></i>
                                        Latest Vendors (5)
                                    </h4>
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="vendorsTable">
                                            <thead>
                                                <tr>
                                                    <th>Photo</th>
                                                    <th>ID</th>
                                                    <th>Name</th>
                                                    <th>Contact</th>
                                                    <th>Email</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td class="py-1">
                                                        <div class="img-sm rounded-circle bg-info d-flex align-items-center justify-content-center text-white"
                                                            style="width: 36px; height: 36px;">
                                                            T
                                                        </div>
                                                    </td>
                                                    <td class="font-weight-bold text-primary">
                                                        #BS202505
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm mr-3">
                                                                <span
                                                                    class="avatar-title bg-light rounded-circle text-primary d-flex align-items-center justify-content-center"
                                                                    style="width: 40px; height: 40px;">
                                                                    T
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <div class="font-weight-medium">testing</div>
                                                                <small class="text-muted">Vendor</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-phone-alt text-muted mr-2"></i>
                                                            6302216384
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="far fa-envelope text-muted mr-2"></i>
                                                            N/A
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <label class="badge badge-pill badge-danger">
                                                            Inactive
                                                        </label>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1">
                                                        <div class="img-sm rounded-circle bg-info d-flex align-items-center justify-content-center text-white"
                                                            style="width: 36px; height: 36px;">
                                                            T
                                                        </div>
                                                    </td>
                                                    <td class="font-weight-bold text-primary">
                                                        #BS202504
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm mr-3">
                                                                <span
                                                                    class="avatar-title bg-light rounded-circle text-primary d-flex align-items-center justify-content-center"
                                                                    style="width: 40px; height: 40px;">
                                                                    T
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <div class="font-weight-medium">test</div>
                                                                <small class="text-muted">Vendor</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-phone-alt text-muted mr-2"></i>
                                                            6302216097
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="far fa-envelope text-muted mr-2"></i>
                                                            N/A
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <label class="badge badge-pill badge-danger">
                                                            Inactive
                                                        </label>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1">
                                                        <div class="img-sm rounded-circle bg-info d-flex align-items-center justify-content-center text-white"
                                                            style="width: 36px; height: 36px;">
                                                            N
                                                        </div>
                                                    </td>
                                                    <td class="font-weight-bold text-primary">
                                                        #BS202503
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm mr-3">
                                                                <span
                                                                    class="avatar-title bg-light rounded-circle text-primary d-flex align-items-center justify-content-center"
                                                                    style="width: 40px; height: 40px;">
                                                                    N
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <div class="font-weight-medium">Nabeela</div>
                                                                <small class="text-muted">Vendor</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-phone-alt text-muted mr-2"></i>
                                                            6302216091
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="far fa-envelope text-muted mr-2"></i>
                                                            N/A
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <label class="badge badge-pill badge-danger">
                                                            Inactive
                                                        </label>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1">
                                                        <div class="img-sm rounded-circle bg-info d-flex align-items-center justify-content-center text-white"
                                                            style="width: 36px; height: 36px;">
                                                            M
                                                        </div>
                                                    </td>
                                                    <td class="font-weight-bold text-primary">
                                                        #BS202502
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm mr-3">
                                                                <span
                                                                    class="avatar-title bg-light rounded-circle text-primary d-flex align-items-center justify-content-center"
                                                                    style="width: 40px; height: 40px;">
                                                                    M
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <div class="font-weight-medium">Madhu</div>
                                                                <small class="text-muted">Vendor</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-phone-alt text-muted mr-2"></i>
                                                            6302216092
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="far fa-envelope text-muted mr-2"></i>
                                                            N/A
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <label class="badge badge-pill badge-danger">
                                                            Inactive
                                                        </label>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="py-1">
                                                        <img src="http://127.0.0.1:8000/storage/http://127.0.0.1:8000/storage/vendor_profiles/26092025_nabeela_hatchery_68d6225002523.jpeg"
                                                            alt="profile" class="img-sm rounded-circle" />
                                                    </td>
                                                    <td class="font-weight-bold text-primary">
                                                        #BS202501
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm mr-3">
                                                                <span
                                                                    class="avatar-title bg-light rounded-circle text-primary d-flex align-items-center justify-content-center"
                                                                    style="width: 40px; height: 40px;">
                                                                    N
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <div class="font-weight-medium">nabeela_Hatchery</div>
                                                                <small class="text-muted">Vendor</small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-phone-alt text-muted mr-2"></i>
                                                            +919700912007
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="far fa-envelope text-muted mr-2"></i>
                                                            N/A
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <label class="badge badge-pill badge-danger">
                                                            Inactive
                                                        </label>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">
                                        <i class="fas fa-gift"></i>
                                        Orders
                                    </h4>
                                    <canvas id="orders-chart"></canvas>
                                    <div id="orders-chart-legend" class="orders-chart-legend"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">
                                        <i class="fas fa-chart-line"></i>
                                        Sales
                                    </h4>
                                    <h2 class="mb-5">56000 <span
                                            class="text-muted h4 font-weight-normal">Sales</span></h2>
                                    <canvas id="sales-chart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>



                </div>
                <!-- content-wrapper ends -->
                <!-- partial:partials/_footer.html -->


                <footer class="footer">
                    <div class="d-sm-flex justify-content-center justify-content-sm-between">
                        <span class="text-muted text-center text-sm-left d-block d-sm-inline-block">
                            © 2025
                        </span>
                        <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">
                            Hand-crafted by Saiprakash
                        </span>
                    </div>
                </footer>
                <!-- partial -->

            </div> <!-- end content-wrapper -->
        </div> <!-- end main-panel -->
    </div> <!-- end page-body-wrapper -->


    <!-- plugins:js -->
    <script src="http://127.0.0.1:8000/admin_assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/vendors/js/vendor.bundle.addons.js"></script>
    <!-- endinject -->

    <!-- inject:js -->
    <script src="http://127.0.0.1:8000/admin_assets/js/off-canvas.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/js/hoverable-collapse.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/js/misc.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/js/settings.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/js/todolist.js"></script>
    <!-- endinject -->

    <!-- Custom js for this page -->
    <script src="http://127.0.0.1:8000/admin_assets/js/file-upload.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/js/typeahead.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/js/select2.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/js/dashboard.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/js/data-table.js"></script>
    <!-- End custom js -->
    <!-- FilePond JS -->

    <script src="http://127.0.0.1:8000/admin_assets/ravindra/js/filepond/filepond-plugin-file-validate-type.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/ravindra/js/filepond/filepond-plugin-image-preview.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/ravindra/js/filepond/filepond.js"></script>





    <script src="http://127.0.0.1:8000/admin_assets/ravindra/js/suneditor/suneditor.min.js"></script>
    <script src="http://127.0.0.1:8000/admin_assets/ravindra/js/suneditor/en.js"></script>

    <link href="http://127.0.0.1:8000/admin_assets/ravindra/css/filepond/filepond.css" rel="stylesheet">
    <link href="http://127.0.0.1:8000/admin_assets/ravindra/css/filepond/filepond-plugin-image-preview.css"
        rel="stylesheet">
    <link href="http://127.0.0.1:8000/admin_assets/ravindra/css/suneditor/suneditor.min.css" rel="stylesheet">









    <script>
        document.addEventListener('DOMContentLoaded', function() {
            FilePond.registerPlugin(FilePondPluginFileValidateType, FilePondPluginImagePreview);

            document.querySelectorAll('input.filepond').forEach(input => {
                const existingImage = input.getAttribute('data-existing');
                FilePond.create(input, {
                    acceptedFileTypes: ['image/png', 'image/jpeg'],
                    storeAsFile: true,
                    files: existingImage ? [{
                        source: existingImage,
                        options: {
                            type: 'local',
                            file: {
                                name: existingImage.split('/').pop(),
                                type: 'image/' + existingImage.split('.').pop()
                            },
                            metadata: {
                                poster: existingImage
                            }
                        }
                    }] : []
                });

            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sunEditorElements = document.querySelectorAll('textarea.sun-editor');
            const editors = [];

            sunEditorElements.forEach((element) => {
                const editor = SUNEDITOR.create(element, {
                    width: '100%',
                    height: 300,
                    charCounter: true,
                    lang: SUNEDITOR_LANG['en'],
                    charCounterLabel: 'Characters:',
                    buttonList: [
                        ['undo', 'redo', 'font', 'fontSize', 'formatBlock'],
                        ['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'],
                        ['fontColor', 'hiliteColor', 'textStyle'],
                        ['removeFormat'],
                        ['outdent', 'indent'],
                        ['align', 'horizontalRule', 'list', 'lineHeight'],
                        ['table'],
                        ['link', 'image', 'video'],
                        ['showBlocks', 'fullScreen', 'codeView', 'preview', 'print']
                    ]
                });

                editors.push({
                    editor,
                    element
                });
            });


            document.querySelectorAll('form').forEach((form) => {
                form.addEventListener('submit', function() {
                    editors.forEach(({
                        editor,
                        element
                    }) => {
                        element.value = editor.getContents();
                    });
                });
            });
        });
    </script>

    <style>
        .sun-editor .se-btn {
            width: 30px;
            height: 30px;
        }

        .sun-editor .se-btn-select {
            padding: 0px 6px;
        }

        .sun-editor {
            font-family: inherit;
        }

        .sun-editor-editable {
            font-family: inherit;
        }

        .sun-editor .se-toolbar {
            font-family: inherit;
        }

        .mini-suneditor .se-btn {
            width: 20px;
            height: 20px;
        }

        .mini-suneditor .se-btn-select {
            padding: 0px 4px;
        }

        .mini-suneditor .se-toolbar .se-btn-group {
            display: flex;
            flex-wrap: nowrap;
        }

        .mini-suneditor .se-toolbar .se-btn-group .se-btn {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mini-suneditor .se-toolbar .se-btn-group .se-btn:hover .se-dropdown-content {
            display: none !important;
            /* Prevent dropdown on hover */
        }

        .sun-editor .se-toolbar {
            z-index: unset;
        }
    </style>



    <script>
        $(document).ready(function() {
            $('#vendorsTable').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true,
                "language": {
                    "search": "_INPUT_",
                    "searchPlaceholder": "Search vendors...",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ vendors",
                    "infoEmpty": "Showing 0 to 0 of 0 vendors",
                    "infoFiltered": "(filtered from _MAX_ total vendors)",
                    "paginate": {
                        "previous": "&laquo;",
                        "next": "&raquo;"
                    }
                },
                "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                    "<'row'<'col-sm-12'tr>>" +
                    "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
            });
        });
    </script>

</body>

</html>

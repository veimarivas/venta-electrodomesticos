<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-layout="{{ config('velzon.layout') }}"
    data-topbar="{{ config('velzon.topbar') }}"
    data-sidebar="{{ config('velzon.sidebar') }}"
    data-sidebar-size="{{ config('velzon.sidebar_size') }}"
    data-sidebar-image="{{ config('velzon.sidebar_image') }}"
    data-preloader="{{ config('velzon.preloader') }}"
    data-bs-theme="{{ config('velzon.mode') }}"
    data-theme="{{ config('velzon.theme') }}"
    data-theme-colors="{{ config('velzon.theme_colors') }}">

<head>
    @include('backend.layouts.partials.head')
    @include('backend.layouts.partials.head-css')
</head>

<body>

    <!-- Begin page -->
    <div id="layout-wrapper">

        @include('backend.layouts.partials.topbar')
        @include('backend.layouts.partials.sidebar')

        <div class="vertical-overlay"></div>

        <!-- ============================================================== -->
        <!-- Start right Content here -->
        <!-- ============================================================== -->
        <div class="main-content">

            <div class="page-content">
                <div class="container-fluid">

                    <x-page-title :title="$title ?? ''" :breadcrumbs="$breadcrumbs ?? []" />

                    @yield('content')

                </div>
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->

            @include('backend.layouts.partials.footer')
        </div>
        <!-- end main content-->

    </div>
    <!-- END layout-wrapper -->

    <!--start back-to-top-->
    <button onclick="topFunction()" class="btn btn-danger btn-icon" id="back-to-top">
        <i class="ri-arrow-up-line"></i>
    </button>
    <!--end back-to-top-->

    <!--preloader-->
    <div id="preloader">
        <div id="status">
            <div class="spinner-border text-primary avatar-sm" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    </div>

    @include('backend.layouts.partials.customizer')

    @include('backend.layouts.partials.scripts')
</body>

</html>

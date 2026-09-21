{{--
    Centralized Vendor Asset Manager for ZeoSync
    Loads local vendor assets conditionally according to the current route.
--}}
@php
    $type = $type ?? 'all';
    $nonceAttr = !empty($cspNonce) ? ' nonce="' . $cspNonce . '"' : '';

    $isDataTablesPage = request()->routeIs([
        'shopify.products*',
        'user.product.showProducts*',
        'shopify.inventory.*',
        'inventory.*',
        'view-all-amazon-low-inventory',
        'shopify.logs',
        'admin.orders*',
        'admin.products*',
        'admin.category*',
        'admin.plans*',
        'admin.mailtemplates*',
        'admin.contact-requests*',
        'admin.shops*'
    ]);

   $isSelect2Page = request()->routeIs([
    'user.addProductCategory',
    'shopify.product.create*',
    'shopify.product.edit*',
    'admin.product.*',
    'admin.shops.*',
    'user.product.amazonView'
]);

    $isChartJsPage = request()->routeIs([
        'dashboard*',
        'crm.entry',
        'shopify.app.launch.dashboard',
        'shopify.app.launch.store.dashboard'
    ]);

    $isSummernotePage = request()->routeIs([
        'admin.mailtemplates.create*',
        'admin.mailtemplates.edit*'
    ]);

    $isAdminPage = request()->routeIs('admin.*');
@endphp

{{-- ======================================================== --}}
{{-- 1. CSS STYLESHEETS                                       --}}
{{-- ======================================================== --}}
@if ($type === 'css' || $type === 'all')
    {{-- Global Vendor Styles --}}
    @if (!isset($includeGlobal) || $includeGlobal)
        <link{!! $nonceAttr !!} rel="stylesheet" href="{{ asset('assets/vendor/fontawesome/css/all.min.css') }}">
    @endif

    {{-- DataTables Styles --}}
    @if ($isDataTablesPage)
        <link{!! $nonceAttr !!} rel="stylesheet" href="{{ asset('assets/vendor/datatables/dataTables.bootstrap5.min.css') }}">
        <link{!! $nonceAttr !!} rel="stylesheet" href="{{ asset('assets/vendor/datatables/responsive/responsive.bootstrap5.min.css') }}">
        @if ($isAdminPage)
            <link{!! $nonceAttr !!} rel="stylesheet" href="{{ asset('assets/vendor/datatables/buttons/buttons.bootstrap5.min.css') }}">
        @endif
    @endif

    {{-- Select2 Styles --}}
    @if ($isSelect2Page)
        <link{!! $nonceAttr !!} rel="stylesheet" href="{{ asset('assets/vendor/select2/css/select2.min.css') }}">
        <link{!! $nonceAttr !!} rel="stylesheet" href="{{ asset('assets/vendor/select2/css/select2-bootstrap-5-theme.min.css') }}">
    @endif

    {{-- Summernote Styles (Admin) --}}
    @if ($isSummernotePage)
        <link{!! $nonceAttr !!} rel="stylesheet" href="{{ asset('assets/vendor/summernote/summernote-bs5.min.css') }}">
    @endif
@endif

{{-- ======================================================== --}}
{{-- 2. JAVASCRIPT LIBRARIES                                  --}}
{{-- ======================================================== --}}
@if ($type === 'js' || $type === 'all')
    {{-- Global jQuery is loaded first --}}
    @if (!isset($includeGlobal) || $includeGlobal)
        <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
    @endif

    {{-- DataTables Scripts (jQuery must precede) --}}
    @if ($isDataTablesPage)
        <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/datatables/jquery.dataTables.min.js') }}"></script>
        <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/datatables/dataTables.bootstrap5.min.js') }}"></script>
        <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/datatables/responsive/dataTables.responsive.min.js') }}"></script>
        <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/datatables/responsive/responsive.bootstrap5.min.js') }}"></script>
       @if ($isAdminPage)
    {{-- DataTables Buttons dependencies must load first --}}
    <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/jszip/jszip.min.js') }}"></script>
    <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/pdfmake/pdfmake.min.js') }}"></script>
    <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/pdfmake/vfs_fonts.js') }}"></script>

    {{-- DataTables Buttons --}}
    <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/datatables/buttons/dataTables.buttons.min.js') }}"></script>
    <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/datatables/buttons/buttons.bootstrap5.min.js') }}"></script>
    <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/datatables/buttons/buttons.html5.min.js') }}"></script>
    <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/datatables/buttons/buttons.print.min.js') }}"></script>
    <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/datatables/buttons/buttons.colVis.min.js') }}"></script>
@endif
    @endif

    {{-- Select2 Scripts --}}
    @if ($isSelect2Page)
        <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/select2/js/select2.min.js') }}"></script>
    @endif

    {{-- Chart.js Scripts --}}
    @if ($isChartJsPage)
        <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
    @endif

    {{-- Summernote Scripts (Admin) --}}
    @if ($isSummernotePage)
        <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/summernote/summernote-bs5.min.js') }}"></script>
    @endif

    {{-- Global SweetAlert2 --}}
    @if (!isset($includeGlobal) || $includeGlobal)
        <script{!! $nonceAttr !!} src="{{ asset('assets/vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
    @endif
@endif

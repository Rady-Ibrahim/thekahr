<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'نظام إدارة الموارد البشرية')</title>

    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts Arabic -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        :root {
            --primary: #2c3e8c;
            --secondary: #1abc9c;
            --accent: #e74c3c;
            --sidebar-w: 260px;
            --header-h: 64px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: #f4f6fb;
            color: #2d3748;
        }

        body.sidebar-open {
            overflow: hidden;
        }

        /* SIDEBAR */
        html,
        body {
            max-width: 100%;
            overflow-x: hidden;
        }

        .sidebar {
            position: fixed;
            top: 0;
            right: 0;
            width: var(--sidebar-w);
            height: 100vh;
            height: 100dvh;
            max-height: 100dvh;
            background: linear-gradient(180deg, #1a237e 0%, #283593 60%, #3949ab 100%);
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            z-index: 1000;
            transition: width .3s ease;
            box-shadow: -4px 0 20px rgba(0, 0, 0, .18);
            padding-bottom: 20px;
        }

        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .48);
            z-index: 998;
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease;
        }

        .sidebar-backdrop.show {
            opacity: 1;
            pointer-events: auto;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, .06);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .32);
            border-radius: 6px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, .46);
        }

        .sidebar .brand {
            height: var(--header-h);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 20px;
            border-bottom: 1px solid rgba(255, 255, 255, .12);
        }

        .sidebar .brand-logo {
            width: 36px;
            height: 36px;
            background: var(--secondary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #fff;
        }

        .sidebar .brand-name {
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
        }

        .sidebar .brand-sub {
            color: rgba(255, 255, 255, .55);
            font-size: .7rem;
        }

        .sidebar .nav-section {
            padding: 16px 12px 4px;
        }

        .sidebar .nav-section-title {
            color: rgba(255, 255, 255, .4);
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 0 8px;
            margin-bottom: 6px;
        }

        .sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            color: rgba(255, 255, 255, .75);
            font-size: .875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all .2s;
            margin-bottom: 2px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }

        .sidebar .nav-link .icon {
            width: 22px;
            text-align: center;
            font-size: .95rem;
        }

        .sidebar .nav-link .badge-count {
            margin-right: auto;
            background: var(--accent);
            color: #fff;
            font-size: .65rem;
            padding: 2px 6px;
            border-radius: 10px;
        }

        /* TOPBAR */
        .topbar {
            position: fixed;
            top: 0;
            right: var(--sidebar-w);
            left: 0;
            height: var(--header-h);
            background: #fff;
            display: flex;
            align-items: center;
            padding: 0 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .06);
            z-index: 999;
            gap: 16px;
        }

        .topbar .page-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: #1a237e;
        }

        .topbar .spacer {
            flex: 1;
        }

        .topbar .topbar-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: none;
            background: #f4f6fb;
            color: #5a6782;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .2s;
        }

        .topbar .topbar-btn:hover {
            background: #e8ebf5;
            color: var(--primary);
        }

        .topbar .topbar-btn .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 16px;
            height: 16px;
            background: var(--accent);
            border-radius: 8px;
            font-size: .6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .topbar .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 12px;
            border-radius: 10px;
            cursor: pointer;
            transition: background .2s;
        }

        .topbar .user-info:hover {
            background: #f4f6fb;
        }

        .topbar .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: .85rem;
        }

        .topbar .user-name {
            font-weight: 600;
            font-size: .85rem;
            color: #2d3748;
        }

        .topbar .user-role {
            font-size: .7rem;
            color: #8892a4;
        }

        /* MAIN */
        .main-wrapper {
            margin-right: var(--sidebar-w);
            padding-top: var(--header-h);
            min-height: 100vh;
        }

        .page-content {
            padding: 28px 28px 40px;
        }

        /* CARDS */
        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
            border: 1px solid rgba(0, 0, 0, .04);
            transition: transform .2s, box-shadow .2s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, .1);
        }

        .stat-card .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 14px;
        }

        .stat-card .stat-value {
            font-size: 1.9rem;
            font-weight: 800;
            color: #1a237e;
            line-height: 1;
        }

        .stat-card .stat-label {
            font-size: .8rem;
            color: #8892a4;
            margin-top: 4px;
        }

        .stat-card .stat-change {
            font-size: .75rem;
            margin-top: 8px;
        }

        .stat-card .stat-change.up {
            color: #27ae60;
        }

        .stat-card .stat-change.down {
            color: #e74c3c;
        }

        /* SECTION CARD */
        .section-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
            border: 1px solid rgba(0, 0, 0, .04);
            overflow: hidden;
        }

        .section-card .section-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f0f2f8;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-card .section-title {
            font-weight: 700;
            font-size: 1rem;
            color: #1a237e;
            margin: 0;
        }

        .section-card .section-body {
            padding: 20px 22px;
        }

        /* TABLE */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead th {
            background: #f8f9fe;
            padding: 11px 14px;
            font-size: .78rem;
            font-weight: 600;
            color: #5a6782;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 2px solid #edf0f9;
        }

        .data-table tbody td {
            padding: 12px 14px;
            font-size: .875rem;
            border-bottom: 1px solid #f4f6fb;
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table tbody tr:hover td {
            background: #fafbff;
        }

        /* BADGES */
        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
        }

        .badge-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-inactive {
            background: #fff3e0;
            color: #e65100;
        }

        .badge-pending {
            background: #fff8e1;
            color: #f57f17;
        }

        .badge-approved {
            background: #e3f2fd;
            color: #1565c0;
        }

        .badge-rejected {
            background: #fce4ec;
            color: #c62828;
        }

        .badge-paid {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-draft {
            background: #f3e5f5;
            color: #6a1b9a;
        }

        /* BUTTONS */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), #3949ab);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 9px 20px;
            font-weight: 600;
            font-size: .875rem;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-primary-custom:hover {
            box-shadow: 0 4px 14px rgba(44, 62, 140, .35);
            transform: translateY(-1px);
            color: #fff;
        }

        /* PAGE HEADER */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-weight: 800;
            font-size: 1.6rem;
            color: #1a237e;
            margin: 0;
        }

        .page-header .breadcrumb {
            font-size: .8rem;
            color: #8892a4;
            margin: 2px 0 0;
        }

        /* ALERT */
        .alert-float {
            position: fixed;
            bottom: 24px;
            left: 24px;
            z-index: 9999;
            min-width: 300px;
        }

        /* FORM */
        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1.5px solid #e8ebf5;
            font-size: .875rem;
            padding: 9px 14px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(44, 62, 140, .1);
        }

        .form-label {
            font-weight: 600;
            font-size: .83rem;
            color: #4a5568;
        }

        .nav-tabs {
            overflow-x: auto;
            overflow-y: hidden;
            flex-wrap: nowrap;
            -webkit-overflow-scrolling: touch;
        }

        .nav-tabs .nav-link {
            white-space: nowrap;
        }

        /* RESPONSIVE */
        @media (max-width: 1199.98px) {
            :root {
                --sidebar-w: 236px;
            }

            .page-content {
                padding: 22px 20px 34px;
            }

            .stat-card {
                padding: 18px;
            }

            .section-card .section-body {
                padding: 18px;
            }

            .modal-xl {
                --bs-modal-width: calc(100vw - 40px);
            }
        }

        @media (max-width: 991.98px) {
            .sidebar {
                width: min(86vw, 300px);
                height: 100dvh;
                max-height: 100dvh;
                overflow-y: auto;
                transform: translateX(105%);
                transition: transform .25s ease;
                z-index: 1001;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .topbar {
                right: 0;
                padding: 0 14px;
            }

            .main-wrapper {
                margin-right: 0;
            }

            .page-content {
                padding: 18px 14px 30px;
            }

            .page-header {
                align-items: stretch;
                gap: 12px;
                flex-direction: column;
            }

            .page-header h1 {
                font-size: 1.35rem;
                line-height: 1.35;
            }

            .page-header .btn-primary-custom,
            .page-header .btn {
                width: 100%;
            }

            .section-card {
                border-radius: 12px;
            }

            .section-card .section-header {
                padding: 14px 16px;
                flex-wrap: wrap;
            }

            .section-card .section-body {
                padding: 16px;
            }

            .stat-card {
                border-radius: 12px;
                padding: 16px;
            }

            .stat-card .stat-value {
                font-size: 1.55rem;
            }

            .data-table {
                min-width: 760px;
            }

            .data-table thead th,
            .data-table tbody td {
                padding: 10px 12px;
                white-space: nowrap;
            }

            .modal-dialog {
                margin: 10px;
            }

            .modal-lg,
            .modal-xl {
                --bs-modal-width: calc(100vw - 20px);
            }

            .modal-content {
                border-radius: 12px;
            }

            .modal-header,
            .modal-footer {
                padding: 12px 16px;
            }

            .modal-body {
                padding: 16px;
            }

            .modal-footer {
                gap: 8px;
            }

            .modal-footer .btn,
            .modal-footer .btn-primary-custom {
                flex: 1 1 auto;
            }

            .alert-float {
                left: 12px;
                right: 12px;
                bottom: 12px;
                min-width: 0;
            }
        }

        @media (max-width: 575.98px) {
            :root {
                --header-h: 58px;
            }

            .topbar {
                gap: 8px;
            }

            .topbar .page-title {
                font-size: .95rem;
                max-width: 45vw;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .topbar .topbar-btn {
                width: 34px;
                height: 34px;
                border-radius: 9px;
            }

            .topbar .user-info {
                padding: 0;
                gap: 0;
            }

            .topbar .user-info>div:not(.user-avatar) {
                display: none;
            }

            .topbar .user-avatar {
                width: 34px;
                height: 34px;
            }

            .page-content {
                padding: 14px 10px 24px;
            }

            .page-header {
                margin-bottom: 16px;
            }

            .page-header h1 {
                font-size: 1.2rem;
            }

            .page-header .breadcrumb {
                font-size: .75rem;
            }

            .section-card .section-header {
                padding: 12px 14px;
                gap: 8px;
            }

            .section-card .section-title {
                font-size: .93rem;
            }

            .section-card .section-body {
                padding: 14px;
            }

            .stat-card {
                padding: 14px 12px;
            }

            .stat-card .stat-icon {
                width: 42px;
                height: 42px;
                border-radius: 12px;
                font-size: 1.15rem;
                margin-bottom: 10px;
            }

            .stat-card .stat-value {
                font-size: 1.35rem;
            }

            .btn-primary-custom,
            .btn {
                min-height: 38px;
            }

            .form-control,
            .form-select {
                min-height: 40px;
                font-size: .85rem;
            }

            .modal-dialog {
                margin: 0;
                min-height: 100vh;
                width: 100%;
                max-width: none;
                display: flex;
                align-items: stretch;
            }

            .modal-content {
                border-radius: 0;
                min-height: 100vh;
            }

            .modal-body {
                overflow-y: auto;
            }

            .modal-footer {
                position: sticky;
                bottom: 0;
                background: #fff;
                z-index: 2;
            }

            .modal-footer .btn,
            .modal-footer .btn-primary-custom {
                width: 100%;
            }

            .data-table {
                min-width: 680px;
            }

            .badge-status {
                padding: 4px 9px;
            }

            .chart-container {
                height: 220px;
            }
        }

        /* LOADER */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, .8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e8ebf5;
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .chart-container {
            position: relative;
            height: 280px;
        }

        .ac-dropdown {
            position: absolute;
            z-index: 9999;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1.5px solid #e8ebf5;
            border-radius: 10px;
            max-height: 320px;
            overflow-y: auto;
            box-shadow: 0 6px 20px rgba(0, 0, 0, .12);
            display: none;
            margin-top: 2px;
        }

        .ac-dropdown.show {
            display: block;
        }

        .ac-option {
            padding: 8px 14px;
            cursor: pointer;
            font-size: .875rem;
            transition: background .15s;
        }

        .ac-option:hover,
        .ac-option.ac-active {
            background: #f0f2ff;
            color: var(--primary);
        }

        .ac-option:first-child {
            border-radius: 10px 10px 0 0;
        }

        .ac-option:last-child {
            border-radius: 0 0 10px 10px;
        }

        .ac-container {
            position: relative;
        }

        .ac-container .form-control {
            padding-left: 32px;
        }

        .ac-container .ac-toggle {
            position: absolute;
            left: 6px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #8892a4;
            cursor: pointer;
            padding: 4px 6px;
            font-size: .7rem;
            z-index: 2;
            transition: color .2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ac-container .ac-toggle:hover {
            color: var(--primary);
        }
    </style>
    @stack('styles')
</head>

<body>

    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

    <!-- SIDEBAR -->
    <nav class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-logo"><i class="fas fa-users-cog"></i></div>
            <div>
                <div class="brand-name">HR System</div>
                <div class="brand-sub">نظام إدارة الموارد البشرية</div>
            </div>
        </div>

        @php
            $u = auth()->user();
            $isSA = $u && $u->hasRole('super_admin');
            $hasP = fn($p) => $isSA || ($u && $u->hasPermission($p));
        @endphp

        <!-- Main Menu -->
        <div class="nav-section">
            <div class="nav-section-title">القائمة الرئيسية</div>
            @if ($hasP('view_dashboard'))
                <a href="/dashboard" class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-tachometer-alt"></i></span> لوحة التحكم
                </a>
            @endif
        </div>

        <div class="nav-section">
            <div class="nav-section-title">الموارد البشرية</div>
            @if ($hasP('view_employees'))
                <a href="/employees" class="nav-link {{ request()->is('employees*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-user-tie"></i></span> الموظفين
                </a>
            @endif
            @if ($hasP('view_attendance'))
                <a href="/attendance" class="nav-link {{ request()->is('attendance*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-fingerprint"></i></span> الحضور والانصراف
                </a>
            @endif
            @if ($hasP('view_shifts'))
                <a href="/shifts" class="nav-link {{ request()->is('shifts*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-clock"></i></span> الورديات
                </a>
            @endif
            @if ($hasP('view_salaries'))
                <a href="/salaries" class="nav-link {{ request()->is('salaries*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-money-bill-wave"></i></span> الرواتب
                </a>
            @endif
            @if ($hasP('view_incentives'))
                <a href="/incentives" class="nav-link {{ request()->is('incentives*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-star"></i></span> الحوافز
                </a>
            @endif
            @if ($hasP('view_deductions'))
                <a href="/deductions" class="nav-link {{ request()->is('deductions*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-minus-circle"></i></span> الخصومات
                </a>
            @endif
            @if ($hasP('view_advances'))
                <a href="/advances" class="nav-link {{ request()->is('advances*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-hand-holding-usd"></i></span> السلف
                </a>
            @endif
            @if ($hasP('view_allowances'))
                <a href="/allowances" class="nav-link {{ request()->is('allowances*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-gift"></i></span> البدلات
                </a>
            @endif
            @if ($hasP('view_employee_points'))
                <a href="/employee-points" class="nav-link {{ request()->is('employee-points*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-star"></i></span> نقاط الموظفين
                </a>
            @endif
            @if ($hasP('view_ideal_employees'))
                <a href="/ideal-employee" class="nav-link {{ request()->is('ideal-employee*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-trophy"></i></span> الموظف المثالي
                </a>
            @endif
            @if ($hasP('view_salaries'))
                <a href="/financial-statement"
                    class="nav-link {{ request()->is('financial-statement*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-file-invoice"></i></span> كشف حساب
                </a>
            @endif
        </div>

        <div class="nav-section">
            <div class="nav-section-title">الإدارة</div>
            @if ($hasP('view_chat_groups'))
                <a href="/chat-groups" class="nav-link {{ request()->is('chat-groups*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-comments"></i></span> مجموعات الدردشة
                </a>
            @endif
            @if ($hasP('view_notifications'))
                <a href="/notifications" class="nav-link {{ request()->is('notifications*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-bell"></i></span> الإشعارات
                </a>
            @endif
            @if ($hasP('view_reports'))
                <a href="/reports" class="nav-link {{ request()->is('reports*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-chart-bar"></i></span> التقارير
                </a>
            @endif
            @if ($hasP('view_work_locations'))
                <a href="/locations" class="nav-link {{ request()->is('locations*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-map-marker-alt"></i></span> مواقع العمل
                </a>
            @endif
            @if ($hasP('view_tab_permissions'))
                <a href="/employee-tab-permissions"
                    class="nav-link {{ request()->is('employee-tab-permissions*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-layer-group"></i></span> صلاحيات التابات
                </a>
            @endif
            @if ($hasP('view_users'))
                <a href="/users" class="nav-link {{ request()->is('users*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-users-cog"></i></span> المستخدمين
                </a>
            @endif
        </div>
    </nav>

    <!-- TOPBAR -->
    <header class="topbar">
        <button class="topbar-btn d-lg-none" onclick="toggleSidebar()" aria-label="فتح القائمة"><i
                class="fas fa-bars"></i></button>
        <div class="page-title">@yield('page-title', 'الرئيسية')</div>
        <div class="spacer"></div>
        <div class="position-relative">
            <button class="topbar-btn" onclick="window.location='/notifications'" title="الإشعارات">
                <i class="fas fa-bell"></i>
                <span class="badge" id="notifBadge" style="display:none">0</span>
            </button>
        </div>
        <div class="dropdown">
            <div class="user-info dropdown-toggle" data-bs-toggle="dropdown">
                <div class="user-avatar">م</div>
                <div>
                    <div class="user-name">المدير</div>
                    <div class="user-role">مدير النظام</div>
                </div>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> الملف الشخصي</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item text-danger" href="/logout"><i class="fas fa-sign-out-alt me-2"></i>
                        تسجيل الخروج</a></li>
            </ul>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <div class="main-wrapper">
        <div class="page-content">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>
    </div>

    <!-- ALERT FLOAT -->
    <div class="alert-float" id="alertFloat" style="display:none"></div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const API_BASE = '/api';
        @if (session('api_token'))
            localStorage.setItem('api_token', '{{ session('api_token') }}');
        @endif
        const TOKEN = localStorage.getItem('api_token') || '';

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            const isOpen = sidebar.classList.toggle('open');
            backdrop.classList.toggle('show', isOpen);
            document.body.classList.toggle('sidebar-open', isOpen);
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarBackdrop').classList.remove('show');
            document.body.classList.remove('sidebar-open');
        }

        function showAlert(msg, type = 'success') {
            const el = document.getElementById('alertFloat');
            el.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show shadow">
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i> ${msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
            el.style.display = 'block';
            setTimeout(() => {
                el.style.display = 'none';
            }, 5000);
        }

        function printHTML(title, bodyHtml) {
            const w = window.open('', '_blank', 'width=980,height=720');
            if (!w) {
                showAlert('يرجى السماح بالنوافذ المنبثقة لطباعة PDF', 'danger');
                return false;
            }
            w.document.write(`<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${title}</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'Cairo',sans-serif;color:#1f2937;padding:28px;font-size:14px;background:#fff;}
    .ph{text-align:center;border-bottom:2px solid #1a237e;padding-bottom:12px;margin-bottom:18px;}
    .ph h1{font-size:20px;color:#1a237e;margin:0 0 4px;}
    .ph .meta{font-size:12px;color:#6b7280;}
    .sum{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:18px;}
    .sum .box{border:1px solid #cbd5e1;border-radius:10px;padding:8px 18px;text-align:center;background:#f8fafc;}
    .sum .lbl{font-size:11px;color:#6b7280;}
    .sum .val{font-size:16px;font-weight:800;color:#1a237e;}
    table{width:100%;border-collapse:collapse;font-size:12.5px;}
    th,td{border:1px solid #94a3b8;padding:6px 8px;text-align:right;vertical-align:top;}
    th{background:#e8eaf6;color:#1a237e;font-weight:700;}
    tbody tr:nth-child(even){background:#f8fafc;}
    .pos{color:#15803d;font-weight:700;}
    .neg{color:#b91c1c;font-weight:700;}
    .h2{font-size:15px;font-weight:700;color:#1a237e;margin:18px 0 8px;border-right:4px solid #1a237e;padding-right:8px;}
    .foot{display:flex;justify-content:space-between;margin-top:20px;padding-top:8px;border-top:1px dashed #cbd5e1;font-size:11px;color:#9ca3af;}
    @page{size:A4;margin:12mm;}
    @media print{body{padding:0;}}
</style>
</head>
<body>
${bodyHtml}
<div class="foot"><span>نظام إدارة الموارد البشرية</span><span>تم الإنشاء: ${new Date().toLocaleString('ar-EG')}</span></div>
<script>window.addEventListener('load',function(){setTimeout(function(){window.print();window.onafterprint=function(){window.close();};},200);});<\/script>
</body>
</html>`);
            w.document.close();
            return true;
        }

        function formatApiError(payload) {
            if (!payload) return 'حدث خطأ غير متوقع';
            if (payload.errors && typeof payload.errors === 'object') {
                const list = Object.values(payload.errors).flat().filter(Boolean);
                if (list.length) return list.join('<br>');
            }
            if (payload.message && !String(payload.message).includes('SQLSTATE')) {
                return payload.message;
            }
            if (payload.message && String(payload.message).includes('Duplicate entry')) {
                if (payload.message.includes('phone')) return 'رقم الهاتف مستخدم من قبل.';
                if (payload.message.includes('email')) return 'البريد الإلكتروني مستخدم من قبل.';
                if (payload.message.includes('national_id')) return 'الرقم القومي مستخدم من قبل.';
                if (payload.message.includes('employee_code')) return 'كود الموظف مستخدم من قبل.';
                return 'هذه البيانات مسجّلة من قبل. راجع الحقول المكررة.';
            }
            return 'تعذر إتمام العملية. راجع البيانات وحاول مرة أخرى.';
        }

        async function apiFetch(url, options = {}) {
            const defaults = {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + TOKEN,
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
            };
            try {
                const res = await fetch(API_BASE + url, {
                    ...defaults,
                    ...options,
                    headers: {
                        ...defaults.headers,
                        ...(options.headers || {})
                    },

                });

                let data = {};
                const text = await res.text();
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (_) {
                    return {
                        success: false,
                        message: res.ok ? 'استجابة غير متوقعة من الخادم' : formatApiError({
                            message: text
                        }),
                    };
                }

                if (data.success === undefined) {
                    if (res.ok) data.success = true;
                    else data.success = false;
                }

                if (!data.success) {
                    data.message = formatApiError(data);
                }

                return data;
            } catch (err) {
                return {
                    success: false,
                    message: 'تعذر الاتصال بالخادم. تحقق من الشبكة وحاول مرة أخرى.'
                };
            }
        }

        const lookupCache = {};
        const lookupConfig = {
            employees: {
                url: '/employees?per_page=1000',
                rows: r => r.data?.data ?? r.data ?? [],
                label: e => `${e.name ?? '-'}${e.employee_code ? ' - ' + e.employee_code : ''}`,
            },
            drivers: {
                url: '/employees?per_page=1000&employee_type=driver_representative',
                rows: r => {
                    const all = r.data?.data ?? r.data ?? [];
                    const filtered = all.filter(e => e.sub_role === 'driver');
                    return filtered.length ? filtered : all;
                },
                label: e => `${e.name ?? '-'}${e.employee_code ? ' - ' + e.employee_code : ''}`,
            },
            delegates: {
                url: '/employees?per_page=1000&employee_type=driver_representative',
                rows: r => {
                    const all = r.data?.data ?? r.data ?? [];
                    const filtered = all.filter(e => e.sub_role === 'delegate');
                    return filtered.length ? filtered : all;
                },
                label: e => `${e.name ?? '-'}${e.employee_code ? ' - ' + e.employee_code : ''}`,
            },
        };

        async function getLookupRows(type) {
            if (lookupCache[type]) return lookupCache[type];
            const config = lookupConfig[type];
            if (!config) return [];
            const response = await apiFetch(config.url);
            lookupCache[type] = response.success ? config.rows(response) : [];
            return lookupCache[type];
        }

        async function hydrateLookupSelect(select) {
            if (!select || select.dataset.lookupReady === '1') return;
            const type = select.dataset.lookup;
            const rows = await getLookupRows(type);
            const config = lookupConfig[type];
            const placeholder = select.dataset.placeholder || 'اختر';
            const current = select.dataset.selected || select.value || '';

            select.innerHTML = `<option value="">${placeholder}</option>` + rows.map(row => {
                const selected = String(row.id) === String(current) ? 'selected' : '';
                return `<option value="${row.id}" ${selected}>${escapeLookupHtml(config.label(row))}</option>`;
            }).join('');
            select.dataset.lookupReady = '1';
            if (current) select.value = current;
        }

        function hydrateLookupSelects(root = document) {
            root.querySelectorAll('select[data-lookup]').forEach(select => hydrateLookupSelect(select));
        }

        function resetLookupSelect(select, value = '') {
            if (!select) return;
            select.dataset.selected = value ?? '';
            select.dataset.lookupReady = '0';
            hydrateLookupSelect(select);
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [char]));
        }

        function escapeJs(value) {
            return String(value ?? '').replace(/['\\]/g, '\\$&').replace(/\n/g, '\\n');
        }

        function escapeLookupHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [char]));
        }

        function createSearchableSelect(input, lookupType, {
            onSelect
        } = {}) {
            const container = input.parentElement;
            container.classList.add('ac-container');
            const hidden = container.querySelector('input[type="hidden"]') || (() => {
                const h = document.createElement('input');
                h.type = 'hidden';
                h.name = input.name;
                container.appendChild(h);
                return h;
            })();
            input.removeAttribute('name');

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'ac-toggle';
            toggle.innerHTML = '<i class="fas fa-chevron-down"></i>';
            container.appendChild(toggle);

            let items = [];
            let dropdown = null;
            let selectedValue = '';
            let open = false;

            getLookupRows(lookupType).then(rows => {
                items = rows;
            });

            function showAll() {
                if (items.length) showDropdown(items);
            }

            input.addEventListener('input', () => {
                const val = input.value.trim();
                if (!val) {
                    selectedValue = '';
                    hidden.value = '';
                    if (open) showAll();
                    else hideDropdown();
                    return;
                }
                const filtered = items.filter(item =>
                    lookupConfig[lookupType].label(item).toLowerCase().includes(val.toLowerCase())
                );
                open = true;
                showDropdown(filtered);
            });

            input.addEventListener('focus', () => showAll());

            toggle.addEventListener('click', e => {
                e.stopPropagation();
                if (open) {
                    hideDropdown();
                } else {
                    showAll();
                }
            });

            input.addEventListener('blur', () => setTimeout(() => {
                open = false;
                hideDropdown();
            }, 200));

            input.addEventListener('keydown', e => {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!open) {
                        showAll();
                        return;
                    }
                }
                if (!open) return;
                const options = dropdown?.querySelectorAll('.ac-option');
                if (!options?.length) return;
                let idx = Array.from(options).findIndex(o => o.classList.contains('ac-active'));
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    idx = Math.min(idx + 1, options.length - 1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    idx = Math.max(idx - 1, 0);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (idx >= 0) options[idx].click();
                    return;
                } else return;
                options.forEach(o => o.classList.remove('ac-active'));
                if (idx >= 0) options[idx].classList.add('ac-active');
            });

            function showDropdown(filtered) {
                if (!dropdown) {
                    dropdown = document.createElement('div');
                    dropdown.className = 'ac-dropdown';
                    container.appendChild(dropdown);
                }
                if (!filtered.length) {
                    hideDropdown();
                    return;
                }
                dropdown.innerHTML = filtered.map((item, i) =>
                    `<div class="ac-option${i === 0 ? ' ac-active' : ''}" data-value="${item.id}">${escapeLookupHtml(lookupConfig[lookupType].label(item))}</div>`
                ).join('');
                positionDropdown();
                dropdown.classList.add('show');
                open = true;
                dropdown.querySelectorAll('.ac-option').forEach(opt => {
                    opt.addEventListener('mousedown', e => {
                        e.preventDefault();
                        selectedValue = opt.dataset.value;
                        hidden.value = selectedValue;
                        input.value = opt.textContent;
                        if (onSelect) onSelect(selectedValue);
                        hideDropdown();
                    });
                });
            }

            function positionDropdown() {
                if (!dropdown) return;
                const rect = container.getBoundingClientRect();
                const vw = window.innerWidth;
                const vh = window.innerHeight;
                const ddMaxHeight = 320;
                let width = Math.max(rect.width, 300);
                if (width > vw - 16) width = vw - 16;

                const isRTL = getComputedStyle(document.documentElement).direction === 'rtl';

                if (isRTL) {
                    let right = vw - rect.right;
                    if (right + width > vw - 8) right = Math.max(8, vw - width - 8);
                    if (right < 8) right = 8;
                    dropdown.style.right = right + 'px';
                    dropdown.style.left = 'auto';
                } else {
                    let left = rect.left;
                    if (left + width > vw - 8) left = Math.max(8, vw - width - 8);
                    if (left < 8) left = 8;
                    dropdown.style.left = left + 'px';
                    dropdown.style.right = 'auto';
                }

                const spaceBelow = vh - rect.bottom;
                const spaceAbove = rect.top;
                const openUp = spaceBelow < 180 && spaceAbove > spaceBelow;

                dropdown.style.position = 'fixed';
                dropdown.style.width = width + 'px';
                dropdown.style.maxHeight = Math.max(120, Math.min(ddMaxHeight, (openUp ? spaceAbove : spaceBelow) - 8)) + 'px';
                dropdown.style.top = openUp ? 'auto' : (rect.bottom + 2) + 'px';
                dropdown.style.bottom = openUp ? (vh - rect.top + 2) + 'px' : 'auto';
            }

            window.addEventListener('scroll', e => {
                if (open && dropdown && e.target !== dropdown) positionDropdown();
            }, true);
            window.addEventListener('resize', () => {
                if (open && dropdown) positionDropdown();
            });

            function hideDropdown() {
                if (dropdown) dropdown.classList.remove('show');
                open = false;
            }

            return {
                getValue: () => hidden.value,
                setValue: (id, label) => {
                    hidden.value = id;
                    input.value = label || '';
                    selectedValue = id;
                },
                setItems: (rows) => {
                    items = rows || [];
                    open = false;
                    hideDropdown();
                },
                reset: () => {
                    hidden.value = '';
                    input.value = '';
                    selectedValue = '';
                    open = false;
                    hideDropdown();
                },
            };
        }

        // Load pending approvals count
        async function loadNotifCount() {
            try {
                const r = await apiFetch('/notifications/unread-count');
                if (r.success && r.count > 0) {
                    const b = document.getElementById('notifBadge');
                    b.textContent = r.count;
                    b.style.display = 'flex';
                }
            } catch (e) {}
        }

        document.addEventListener('DOMContentLoaded', () => {
            hydrateLookupSelects();

            if (TOKEN) {
                loadNotifCount();
            }

            document.querySelectorAll('#sidebar .nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 992) closeSidebar();
                });
            });
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeSidebar();
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 992) closeSidebar();
        });
    </script>
    @stack('scripts')
</body>

</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - @yield('title', 'Dashboard')</title>

    <!-- CSS - Bootstrap 4 -->
    <link href="{{ asset('assets/css/sb-admin-2.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link href="https://cdn.jsdelivr.net/npm/gridstack@9.4.0/dist/gridstack.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --primary-green: #10b981;
            --primary-green-dark: #059669;
            --primary-green-light: #34d399;
            --dark-bg-1: #0a0e27;
            --dark-bg-2: #151b3d;
            --dark-bg-3: #1e2749;
            --dark-surface: rgba(30, 39, 73, 0.6);
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --text-primary: #ffffff;
            --text-secondary: #a0aec0;
            --text-muted: #718096;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #0f1117;
            color: var(--text-primary);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Remove default wrapper */
        #wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* =====================
           TOP HEADER BAR
        ===================== */
        .topbar-glass {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: #161b27;
            border-bottom: 1px solid #2a3044;
            box-shadow: 0 1px 8px rgba(0,0,0,0.4);
            z-index: 1040;
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            gap: 1rem;
        }

        /* Sidebar toggle button */
        .sidebar-toggle-btn {
            width: 38px;
            height: 38px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .sidebar-toggle-btn:hover {
            background: rgba(16, 185, 129, 0.15);
            border-color: var(--primary-green);
            color: var(--primary-green-light);
        }

        /* Brand Logo */
        .brand-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .brand-logo {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            flex-shrink: 0;
        }

        .brand-logo img {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }

        .brand-text {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-green-light), var(--primary-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            white-space: nowrap;
        }

        /* Device selector in topbar */
        .device-selector-wrapper {
            display: flex;
            align-items: center;
        }

        .device-selector select {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--text-primary);
            padding: 0.45rem 1rem;
            font-weight: 500;
            min-width: 180px;
            transition: all 0.3s ease;
        }

        .device-selector select:focus {
            background: rgba(255, 255, 255, 0.08);
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }

        .device-selector select option {
            background: var(--dark-bg-2);
            color: var(--text-primary);
        }

        /* =====================
           LEFT SIDEBAR
        ===================== */
        :root {
            --sidebar-width: 240px;
            --sidebar-collapsed-width: 62px;
            --topbar-height: 60px;
        }

        .sidebar {
            position: fixed;
            top: var(--topbar-height);
            left: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--topbar-height));
            background: #161b27;
            border-right: 1px solid #2a3044;
            box-shadow: 2px 0 8px rgba(0,0,0,0.3);
            z-index: 1035;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1),
                        transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Collapsed state (desktop) - icon only */
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        /* Hidden state (desktop) - completely off-screen */
        .sidebar.hidden {
            transform: translateX(-100%);
            width: var(--sidebar-width);
        }

        /* Mobile: slide out by default */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-width);
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            /* On mobile, ignore desktop states */
            .sidebar.collapsed {
                width: var(--sidebar-width);
                transform: translateX(-100%);
            }
            .sidebar.hidden {
                transform: translateX(-100%);
            }
        }

        /* Sidebar Nav */
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1rem 0;
        }

        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, 0.2);
            border-radius: 4px;
        }

        .nav-section-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            padding: 0.75rem 1.2rem 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            opacity: 1;
            transition: opacity 0.2s;
        }

        .sidebar.collapsed .nav-section-label {
            opacity: 0;
        }

        /* Nav Menu Items */
        .nav-menu {
            list-style: none;
            margin: 0;
            padding: 0 0.6rem;
        }

        .nav-menu-item {
            margin-bottom: 2px;
        }

        .nav-menu-link {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.7rem 0.85rem;
            border-radius: 10px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
            position: relative;
        }

        .nav-menu-link span {
            opacity: 1;
            transition: opacity 0.2s;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar.collapsed .nav-menu-link span {
            opacity: 0;
            width: 0;
        }

        .nav-menu-link:hover {
            background: rgba(255, 255, 255, 0.07);
            color: var(--text-primary);
            text-decoration: none;
        }

        .nav-menu-link.active {
            background: linear-gradient(135deg, rgba(16,185,129,0.25), rgba(5,150,105,0.25));
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--primary-green-light);
            box-shadow: 0 2px 10px rgba(16, 185, 129, 0.15);
        }

        .nav-menu-icon {
            font-size: 1rem;
            flex-shrink: 0;
            width: 20px;
            text-align: center;
        }

        /* Tooltip on collapsed sidebar */
        .nav-menu-link[data-tip]::after {
            content: attr(data-tip);
            position: absolute;
            left: calc(var(--sidebar-collapsed-width) - 4px);
            background: rgba(16, 185, 129, 0.9);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.78rem;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s;
            z-index: 9999;
        }

        .sidebar.collapsed .nav-menu-link[data-tip]:hover::after {
            opacity: 1;
        }

        /* Nav divider */
        .nav-divider {
            height: 1px;
            background: rgba(255,255,255,0.06);
            margin: 0.5rem 1rem;
        }

        /* =====================
           OVERLAY (mobile)
        ===================== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            top: var(--topbar-height);
            background: rgba(0,0,0,0.55);
            backdrop-filter: blur(2px);
            z-index: 1034;
        }

        .sidebar-overlay.active {
            display: block;
        }

        /* =====================
           MAIN CONTENT AREA
        ===================== */
        #content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: var(--sidebar-width);
            margin-top: var(--topbar-height);
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: calc(100vh - var(--topbar-height));
        }

        #content-wrapper.sidebar-collapsed {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Hidden: sidebar fully off-screen, full width content */
        #content-wrapper.sidebar-hidden {
            margin-left: 0;
        }

        @media (max-width: 991px) {
            #content-wrapper {
                margin-left: 0 !important;
            }
        }

        #content {
            flex: 1;
            padding: 2rem;
        }

        /* Container Fluid Adjustments */
        .container-fluid {
            max-width: 100%;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        /* User Menu */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-profile-btn {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            background: #1e2436;
            border: 1px solid #2a3044;
            border-radius: 50px;
            padding: 0.35rem 0.9rem 0.35rem 0.35rem;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .user-profile-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            text-decoration: none;
            color: var(--text-primary);
            border-color: var(--primary-green);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        @media (max-width: 576px) {
            .user-name { display: none; }
            .brand-text { display: none; }
        }

        /* Dropdown Menu */
        .dropdown-menu-glass {
            background: #161b27;
            border: 1px solid #2a3044;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            padding: 0.5rem;
            margin-top: 0.5rem;
        }

        .dropdown-item-glass {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1rem;
            border-radius: 8px;
            color: var(--text-secondary);
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .dropdown-item-glass:hover {
            background: rgba(16, 185, 129, 0.15);
            color: var(--primary-green-light);
            text-decoration: none;
        }

        .dropdown-divider {
            margin: 0.4rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            #content { padding: 1rem; }
            .widget-wrapper {
                padding: 5px;
                height: calc(100% - 10px);
            }
            .value-large { font-size: 2rem; }
            .gauge-modern-container { width: 120px; height: 120px; }
        }

        @media (max-width: 576px) {
            .brand-logo { width: 36px; height: 36px; }
            .brand-logo img { width: 26px; height: 26px; }
            .user-avatar { width: 32px; height: 32px; font-size: 0.9rem; }
        }

        /* Hide original sidebar toggle */
        #sidebarToggleTop { display: none; }

        /* Clean Card Components */
        .glass-card {
            background: #1a1f2e;
            border: 1px solid #2a3044;
            border-radius: var(--radius-lg);
            box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }

        .glass-modal-content {
            background: #161b27;
            border: 1px solid #2a3044;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }

        .glass-input, .form-control, select.form-control, textarea.form-control {
            background: #1e2436 !important;
            border: 1px solid #2a3044 !important;
            color: var(--text-primary) !important;
            border-radius: 10px !important;
            padding: 0.75rem 1rem !important;
            transition: border-color 0.2s ease !important;
        }

        .glass-input:focus, .form-control:focus {
            background: rgba(255, 255, 255, 0.08) !important;
            border-color: var(--primary-green) !important;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2) !important;
            outline: none;
            color: white !important;
        }

        /* Fix for Webkit browsers (Chrome, Safari, Edge) specialized inputs */
        input::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: var(--text-secondary) !important;
            border-radius: 0 10px 10px 0 !important;
        }

        .input-group-append .input-group-text {
            border-left: none !important;
        }

        .input-group-prepend .input-group-text {
            border-radius: 10px 0 0 10px !important;
            border-right: none !important;
        }

        /* Dark Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn {
            border-radius: 10px !important;
            font-weight: 500;
            padding: 0.6rem 1.2rem;
            transition: all 0.3s ease;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: var(--text-secondary) !important;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }

        select option {
            background: #0a0e27 !important;
            color: white !important;
        }

        .glass-label {
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .glass-button {
            border-radius: 10px !important;
            padding: 0.75rem 1.5rem !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }

        .glass-button-primary {
            background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark)) !important;
            border: none !important;
            color: white !important;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3) !important;
        }

        .glass-button-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4) !important;
        }

        /* Widget Cards - clean solid surface */
        .widget-card-modern {
            background: #1a1f2e;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            transition: border-color 0.2s ease;
            border: 1px solid #2a3044;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .widget-card-modern:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(16, 185, 129, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .card-header-modern {
            background: transparent;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 12px 16px;
            flex-shrink: 0;
            cursor: grab;
        }

        .widget-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .widget-icon {
            color: var(--primary-green);
            font-size: 1rem;
            opacity: 0.8;
        }

        .widget-actions {
            display: flex;
            gap: 6px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .widget-card-modern:hover .widget-actions {
            opacity: 1;
        }

        .btn-widget-action {
            width: 32px;
            height: 32px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            color: var(--text-secondary);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-widget-action:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-green);
            color: var(--primary-green-light);
            transform: scale(1.1);
        }

        .btn-widget-action.btn-delete:hover {
            background: rgba(220, 38, 38, 0.2);
            border-color: rgba(220, 38, 38, 0.5);
            color: #f87171;
        }

        .card-body-modern {
            padding: 1rem;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .widget-content-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .toggle-modern-wrapper {
            margin-bottom: 1rem;
        }

        .toggle-modern-input {
            display: none;
        }

        .toggle-modern-label {
            display: block;
            width: 60px;
            height: 30px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .toggle-modern-button {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 24px;
            height: 24px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transition: all 0.3s;
        }

        .toggle-modern-input:checked+.toggle-modern-label {
            background: var(--primary-green);
        }

        .toggle-modern-input:checked+.toggle-modern-label .toggle-modern-button {
            transform: translateX(30px);
            background: white;
        }

        .status-text {
            font-size: 0.8rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-on {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: var(--primary-green-light);
        }

        .status-off {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
        }

        .value-large {
            font-size: 2.5rem; /* Slightly smaller */
            font-weight: 300; /* Lighter font for modern look */
            color: #ffffff;
            line-height: 1;
            margin: 10px 0;
            letter-spacing: -1px;
        }

        .value-unit {
            font-size: 1rem;
            font-weight: 400;
            color: var(--primary-green);
            margin-left: 4px;
        }

        .slider-modern-container {
            width: 100%;
            position: relative;
            padding: 15px 0;
        }

        .slider-track {
            position: absolute;
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            top: 50%;
            transform: translateY(-50%);
        }

        .slider-progress {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-green), var(--primary-green-light));
            border-radius: 10px;
            transition: width 0.3s;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.5);
        }

        .slider-modern {
            -webkit-appearance: none;
            width: 100%;
            height: 6px;
            background: transparent;
            outline: none;
            position: relative;
            z-index: 2;
            cursor: pointer;
        }

        .slider-modern::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: white;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4), 0 0 10px rgba(16, 185, 129, 0.3);
            border: 3px solid var(--primary-green);
        }

        .slider-modern::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: white;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4), 0 0 10px rgba(16, 185, 129, 0.3);
            border: 3px solid var(--primary-green);
        }

        /* Global Card Override to Glass */
        .card {
            background: rgba(255, 255, 255, 0.03) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: var(--radius-lg);
            color: white !important;
        }

        /* Table Overrides */
        .table {
            color: white !important;
        }
        .table thead th {
            border-bottom: 2px solid rgba(255, 255, 255, 0.1) !important;
            color: var(--text-muted) !important;
        }
        .table td {
            border-top: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.02) !important;
            color: white !important;
        }

        /* Pagination Overrides */
        .pagination .page-link {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s;
        }
        .pagination .page-link:hover {
            background: var(--primary-green);
            color: white;
        }
        .pagination .page-item.active .page-link {
            background: var(--primary-green-dark);
            border-color: var(--primary-green);
        }
        .pagination .page-item.disabled .page-link {
            background: rgba(255, 255, 255, 0.02);
            color: rgba(255, 255, 255, 0.2);
        }

        /* Alert Overrides */
        .alert {
            border-radius: 12px;
            border: 1px solid transparent;
            backdrop-filter: blur(10px);
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.1) !important;
            border-color: rgba(16, 185, 129, 0.2) !important;
            color: var(--primary-green-light) !important;
        }
        .alert-danger {
            background: rgba(239, 68, 68, 0.1) !important;
            border-color: rgba(239, 68, 68, 0.2) !important;
            color: #f87171 !important;
        }

        /* General Modal fixes */
        .modal-content {
            background: rgba(15, 21, 49, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            color: white;
        }

        .modal-header, .modal-footer {
            border: none;
        }

        .close {
            color: white;
            text-shadow: none;
            opacity: 0.8;
        }
        .close:hover {
            color: white;
            opacity: 1;
        }

        .gauge-svg {
            width: 100%;
            height: 100%;
        }

        .gauge-value-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .gauge-value {
            font-size: 1.8rem;
            font-weight: 300;
            color: #ffffff;
            line-height: 1;
        }

        .gauge-unit {
            font-size: 0.75rem;
            color: var(--primary-green);
            margin-top: 2px;
            text-transform: uppercase;
        }

        .text-widget-icon {
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(16, 185, 129, 0.15);
            color: var(--primary-green-light);
            border-radius: var(--radius-md);
            font-size: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .mqtt-topic-info {
            margin-top: 12px;
            padding: 6px 12px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: var(--radius-sm);
            text-align: center;
        }

        .mqtt-topic-info small {
            font-family: monospace;
            font-size: 10px;
            color: var(--primary-green-light);
            font-weight: 500;
        }

        .card-footer-modern {
            background: rgba(255, 255, 255, 0.03);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 10px 16px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
        }

        .widget-type-badge {
            background: rgba(16, 185, 129, 0.2);
            color: var(--primary-green-light);
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 9px;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .widget-id-badge {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 9px;
            font-family: monospace;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .update-time {
            color: var(--text-muted);
            font-size: 10px;
        }

        .grid-stack {
            background: transparent;
        }

        .grid-stack-item-content {
            inset: 0 ! important;
            overflow: visible !important;
        }

        .widget-wrapper {
            padding: 10px;
            height: calc(100% - 20px);
            box-sizing: border-box;
        }

        .device-online {
            color: var(--primary-green-light);
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
        }

        .device-offline {
            color: #f87171;
            text-shadow: 0 0 10px rgba(248, 113, 113, 0.5);
        }

        /* Alert Messages with Dark Glass */
        .alert {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border-color: rgba(16, 185, 129, 0.3);
            color: var(--primary-green-light);
        }

        .alert-danger {
            background: rgba(220, 38, 38, 0.15);
            border-color: rgba(220, 38, 38, 0.3);
            color: #f87171;
        }

        .alert .close {
            color: inherit;
            opacity: 0.8;
        }

        .alert .close:hover {
            opacity: 1;
        }

        /* Footer */
        .sticky-footer {
            background: rgba(10, 14, 39, 0.6);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 0;
        }

        .copyright {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, 0.3);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(16, 185, 129, 0.5);
        }


        /* Gap utility for older Bootstrap 4 */
        .gap-2 > * { margin-right: 0.5rem !important; }
        .gap-2 > *:last-child { margin-right: 0 !important; }

        /* Smooth transitions */
        .widget-value-display {
            transition: all 0.3s ease;
        }

        /* Glow effects for interactive elements */
        .nav-menu-link.active,
        .user-profile-btn:hover,
        .widget-card-modern:hover {
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
        }

        /* Loading state animation */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .loading {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* SweetAlert2 Glassmorphism Customization */
        .swal2-popup {
            background: rgba(15, 21, 49, 0.85) !important;
            backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 24px !important;
            color: white !important;
        }
        .swal2-title, .swal2-html-container {
            color: white !important;
        }
        .swal2-confirm {
            background: linear-gradient(135deg, var(--primary-green), var(--primary-green-dark)) !important;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3) !important;
            border-radius: 12px !important;
            padding: 10px 30px !important;
        }
        .swal2-cancel {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 12px !important;
            padding: 10px 30px !important;
        }
        .swal2-icon.swal2-success [class^=swal2-success-line] {
            background-color: var(--primary-green-light) !important;
        }
        .swal2-icon.swal2-success {
            border-color: var(--primary-green-light) !important;
        }
    </style>
    <style>
        /* FORCE DARK BACKGROUND - Priority Override */
        body, html {
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 25%, #2d1b4e 50%, #1e3a5f 75%, #0f2167 100%) !important;
            background-size: 400% 400% !important;
            animation: gradientShift 15s ease infinite !important;
            background-attachment: fixed !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        #page-top, #wrapper, #content-wrapper, #content {
            background: transparent !important;
        }
    </style>

    @stack('styles')
</head>

<body style="background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 25%, #2d1b4e 50%, #1e3a5f 75%, #0f2167 100%); background-size: 400% 400%; animation: gradientShift 15s ease infinite; background-attachment: fixed;">
    <div id="wrapper" style="display:flex; flex-direction:column; min-height:100vh;">

        <!-- ========== TOP HEADER ========== -->
        <nav class="topbar-glass">
            <!-- Sidebar Toggle Button -->
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Toggle Menu">
                <i class="fas fa-bars" id="sidebarToggleIcon"></i>
            </button>

            <!-- Brand -->
            <div class="brand-section">
                <div class="brand-logo">
                    <img src="{{ asset('assets/img/logo_tewe.png') }}" alt="{{ config('app.name') }} Logo">
                </div>
                <div class="brand-text">{{ config('app.name') }}</div>
            </div>

            <!-- Device Selector (center in topbar) -->
            @if (request()->routeIs('dashboard') && isset($devices) && $devices->count() > 0)
                <div class="device-selector-wrapper">
                    {{-- [SECURITY FIX] POST form so device_id never appears in the browser URL bar --}}
                    <form action="{{ route('dashboard.select') }}" method="POST" class="device-selector">
                        @csrf
                        <select name="device_id" class="form-control" onchange="this.form.submit()">
                            @foreach ($devices as $device)
                                <option value="{{ $device->id }}"
                                    {{ isset($selectedDevice) && $selectedDevice && $selectedDevice->id == $device->id ? 'selected' : '' }}>
                                    {{ $device->name }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            @endif

            <!-- User Menu -->
            <div class="user-menu">
                <div class="dropdown">
                    <a href="#" class="user-profile-btn" id="userDropdown" data-toggle="dropdown">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="user-name">{{ Auth::user()->name }}</span>
                        <i class="fas fa-chevron-down" style="font-size: 0.7rem;"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right dropdown-menu-glass">
                        <a class="dropdown-item-glass" href="{{ route('profile.edit') }}">
                            <i class="fas fa-user"></i>
                            <span>Profile</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display: none;">
                            @csrf
                        </form>
                        <a class="dropdown-item-glass" href="#" onclick="event.preventDefault(); confirmLogout();">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- ========== SIDEBAR OVERLAY (mobile) ========== -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebarMobile()"></div>

        <!-- ========== LEFT SIDEBAR ========== -->
        <aside class="sidebar" id="mainSidebar">
            <nav class="sidebar-nav">
                <!-- Main Navigation -->
                <p class="nav-section-label">Main</p>
                <ul class="nav-menu">
                    <li class="nav-menu-item">
                        <a href="{{ route('dashboard') }}" class="nav-menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" data-tip="Dashboard">
                            <i class="fas fa-tachometer-alt nav-menu-icon"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-menu-item">
                        <a href="{{ route('devices.index') }}" class="nav-menu-link {{ request()->routeIs('devices.*') && !request()->is('admin/*') ? 'active' : '' }}" data-tip="Devices">
                            <i class="fas fa-microchip nav-menu-icon"></i>
                            <span>Devices</span>
                        </a>
                    </li>
                    <li class="nav-menu-item">
                        <a href="{{ route('schedules.index') }}" class="nav-menu-link {{ request()->routeIs('schedules.*') ? 'active' : '' }}" data-tip="Schedules">
                            <i class="fas fa-calendar-alt nav-menu-icon"></i>
                            <span>Schedules</span>
                        </a>
                    </li>
                </ul>

                @if(session('selected_device_id'))
                <div class="nav-divider"></div>
                <p class="nav-section-label">Device</p>
                <ul class="nav-menu">
                    <li class="nav-menu-item">
                        <a href="{{ route('widgets.index') }}" class="nav-menu-link {{ request()->routeIs('widgets.index') ? 'active' : '' }}" data-tip="Widget Keys">
                            <i class="fas fa-key nav-menu-icon"></i>
                            <span>Widget Keys</span>
                        </a>
                    </li>
                    <li class="nav-menu-item">
                        <a href="{{ route('logs.index') }}" class="nav-menu-link {{ request()->routeIs('logs.index') ? 'active' : '' }}" data-tip="Data Logs">
                            <i class="fas fa-list-alt nav-menu-icon"></i>
                            <span>Data Logs</span>
                        </a>
                    </li>
                </ul>
                @endif

                @if(auth()->user()->isAdmin())
                <div class="nav-divider"></div>
                <p class="nav-section-label">Admin</p>
                <ul class="nav-menu">
                    <li class="nav-menu-item">
                        <a href="{{ route('admin.dashboard') }}" class="nav-menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" data-tip="Gov">
                            <i class="fas fa-shield-alt nav-menu-icon"></i>
                            <span>Gov</span>
                        </a>
                    </li>
                    <li class="nav-menu-item">
                        <a href="{{ route('admin.users.index') }}" class="nav-menu-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" data-tip="Users">
                            <i class="fas fa-users-cog nav-menu-icon"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <li class="nav-menu-item">
                        <a href="{{ route('admin.devices.index') }}" class="nav-menu-link {{ request()->routeIs('admin.devices.*') ? 'active' : '' }}" data-tip="Hardware">
                            <i class="fas fa-server nav-menu-icon"></i>
                            <span>Hardware</span>
                        </a>
                    </li>
                    <li class="nav-menu-item">
                        <a href="{{ route('admin.plans.index') }}" class="nav-menu-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}" data-tip="Plans">
                            <i class="fas fa-crown nav-menu-icon"></i>
                            <span>Plans</span>
                        </a>
                    </li>
                    <li class="nav-menu-item">
                        <a href="{{ route('admin.protocols.index') }}" class="nav-menu-link {{ request()->routeIs('admin.protocols.*') ? 'active' : '' }}" data-tip="Protocols">
                            <i class="fas fa-network-wired nav-menu-icon"></i>
                            <span>Protocols</span>
                        </a>
                    </li>
                    <li class="nav-menu-item">
                        <a href="{{ route('admin.settings.index') }}" class="nav-menu-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" data-tip="Settings">
                            <i class="fas fa-cog nav-menu-icon"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>
                @endif
            </nav>
        </aside>

        <!-- ========== CONTENT WRAPPER ========== -->
        <div id="content-wrapper">
            <div id="content">
                <div class="container-fluid">
@yield('content')
                </div>
            </div>

            <!-- Footer -->
            <footer class="sticky-footer">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; {{ config('app.name') }} {{ date('Y') }}</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        if (typeof jQuery === 'undefined') {
            console.error('❌ jQuery failed to load! ');
        } else {
            console.log('✅ jQuery loaded, version:', $.fn.jquery);
        }
    </script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gridstack@9.4.0/dist/gridstack-all.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // =====================
        // SIDEBAR LOGIC
        // =====================
        var isMobile = function() { return window.innerWidth <= 991; };

        // Desktop states: 'expanded' | 'hidden'
        var desktopState = localStorage.getItem('sidebarState') || 'expanded';

        function applyDesktopState(state) {
            var sidebar = document.getElementById('mainSidebar');
            var wrapper = document.getElementById('content-wrapper');

            // Remove all desktop state classes
            sidebar.classList.remove('collapsed', 'hidden');
            wrapper.classList.remove('sidebar-collapsed', 'sidebar-hidden');

            if (state === 'hidden') {
                sidebar.classList.add('hidden');
                wrapper.classList.add('sidebar-hidden');
            }
            // 'expanded' = no extra classes

            localStorage.setItem('sidebarState', state);
        }

        // Restore state on page load
        if (!isMobile()) {
            applyDesktopState(desktopState);
        }

        function toggleSidebar() {
            var sidebar = document.getElementById('mainSidebar');
            var overlay = document.getElementById('sidebarOverlay');

            if (isMobile()) {
                // Mobile: toggle slide-in/out
                var isOpen = sidebar.classList.contains('mobile-open');
                if (isOpen) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                } else {
                    sidebar.classList.add('mobile-open');
                    overlay.classList.add('active');
                }
            } else {
                // Desktop: toggle expanded ↔ hidden
                desktopState = (desktopState === 'hidden') ? 'expanded' : 'hidden';
                applyDesktopState(desktopState);
            }
        }

        function closeSidebarMobile() {
            document.getElementById('mainSidebar').classList.remove('mobile-open');
            document.getElementById('sidebarOverlay').classList.remove('active');
        }

        // On resize: fix state
        window.addEventListener('resize', function() {
            var sidebar = document.getElementById('mainSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (!isMobile()) {
                // Switching to desktop: remove mobile open, restore desktop state
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
                applyDesktopState(desktopState);
            } else {
                // Switching to mobile: remove desktop classes
                sidebar.classList.remove('collapsed', 'hidden');
                document.getElementById('content-wrapper').classList.remove('sidebar-collapsed', 'sidebar-hidden');
            }
        });
    </script>

    <script>
        // Global AJAX setup
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Global helper: Update gauge widget
        window.updateGaugeModern = function(widgetKey, value, min, max) {
            const circle = document.getElementById('gauge-circle-' + widgetKey);
            if (!circle) {
                console.warn(`Gauge circle not found: gauge-circle-${widgetKey}`);
                return;
            }

            min = Number(min);
            max = Number(max);
            value = Number(value);

            if (isNaN(min)) min = 0;
            if (isNaN(max)) max = min + 100;
            if (isNaN(value)) value = min;
            if (max === min) max = min + 1;

            const radius = 80;
            const circumference = 2 * Math.PI * radius;
            circle.setAttribute('stroke-dasharray', String(circumference));

            const pct = Math.max(0, Math.min(1, (value - min) / (max - min)));
            const offset = circumference * (1 - pct);

            circle.style.transition = 'stroke-dashoffset 500ms ease';
            circle.style.strokeDashoffset = String(offset);

            console.debug(`Gauge[${widgetKey}] value=${value} min=${min} max=${max} pct=${(pct*100).toFixed(1)}%`);
        };

        // Global Logout Confirmation
        window.confirmLogout = function() {
            Swal.fire({
                title: 'Sign Out?',
                text: "Are you sure you want to end your current session?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: 'rgba(255,255,255,0.05)',
                confirmButtonText: 'Yes, Sign Out',
                cancelButtonText: 'Stay',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('logout-form').submit();
                }
            });
        };

        // Flash Messages with SweetAlert2
        @if (session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: "{{ session('success') }}",
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        @endif

        @if (session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Operation Failed',
                text: "{{ session('error') }}",
                background: 'rgba(20, 15, 45, 0.95)',
                confirmButtonColor: '#ef4444'
            });
        @endif

        console.log('✅ Global scripts initialized - Dark Mode with SweetAlert2');
    </script>

    @stack('scripts')
</body>

</html>
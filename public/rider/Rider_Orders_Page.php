<?php
/**
 * Rider Orders page (converted to PHP): the job board + the rider's own jobs.
 * The <?php ... ?> block runs on the SERVER before any HTML is sent:
 *   1. bootstrap.php  = config + session + helpers (same line every page starts with)
 *   2. guard_...()    = redirect away unless the visitor is the right, approved kind of user
 *   3. $lvConfig / $lvSession = values handed to JavaScript further down (CSRF token + who is signed in)
 */
require __DIR__ . '/../../src/bootstrap.php';
require_once LV_ROOT . '/src/auth/guards.php';
$user = guard_rider_page();
$lvConfig  = ['loginUrl' => config('login_url'), 'logoutUrl' => '../auth/logout.php', 'csrf' => csrf_token()];
$lvSession = ['id' => $user['id'], 'role' => 'rider', 'name' => $user['name'], 'email' => $user['email']];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAVANDERA - Rider Orders</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


    <style>
        :root {
            --color-primary: #e58db5;
            --color-dark: #5a003c;
            --color-darker: #800055;
            --color-text: #45002e;
            --color-text-light: #666;
            --color-text-muted: #999;
            --color-border: #f3d3e3;
            --color-bg: #fff7fb;
            --color-bg-light: #f8f3f7;
            --color-accent: #3bb2b8;
            --color-success: #4ade80;
            --color-warning: #ffb800;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.15);
            --shadow-md: 0 4px 12px rgba(90, 0, 60, 0.12);
            --shadow-lg: 0 8px 20px rgba(90, 0, 60, 0.2);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--color-bg);
            color: var(--color-text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, Helvetica, sans-serif;
            line-height: 1.6;
        }

        /* ===== HEADER ===== */
        header {
            background-color: var(--color-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 5%;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-sm);
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo-img {
            height: 80px;
            width: auto;
            display: block;
            filter: brightness(0.6) contrast(1.2);
            margin: -10px 0;
        }

        .menu-container {
            position: relative;
        }

        .menu-btn {
            background-color: #5a003c;
            color: white;
            border: none;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .menu-btn:hover {
            background-color: #800055;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 56px;
            background-color: white;
            width: 280px;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            z-index: 1001;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-header {
            background-color: #f8f3f7;
            padding: 20px 18px;
            border-bottom: 1px solid #e8d4e0;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e58db5, #5a003c);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .user-name {
            font-size: 16px;
            font-weight: bold;
            color: #45002e;
            margin-bottom: 4px;
        }

        .user-email {
            font-size: 12px;
            color: #999;
            word-break: break-word;
        }

        .dropdown-divider {
            height: 1px;
            background-color: #e8d4e0;
            margin: 12px 0;
        }

        .dropdown-menu a {
            display: block;
            color: #45002e;
            padding: 14px 18px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-bottom: 1px solid #f3d3e3;
            transition: all 0.3s ease;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }

        .dropdown-menu a:hover {
            background-color: #fff5fa;
            color: #5a003c;
            padding-left: 24px;
        }

        .dropdown-menu a i {
            margin-right: 8px;
            color: #e58db5;
            width: 16px;
        }

        /* MAIN DASHBOARD CONTAINER */
        .dashboard-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 5%;
            padding-top: 30px;
            padding-bottom: 40px;
        }

        .dropdown-header {
            background-color: var(--color-bg-light);
            padding: 20px 18px;
            border-bottom: 1px solid var(--color-border);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-primary), var(--color-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .user-name {
            font-size: 16px;
            font-weight: bold;
            color: var(--color-text);
            margin-bottom: 4px;
        }

        .user-email {
            font-size: 12px;
            color: var(--color-text-muted);
            word-break: break-word;
        }

        .dropdown-menu a {
            display: block;
            color: var(--color-text);
            padding: 14px 18px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-bottom: 1px solid var(--color-border);
            transition: all 0.3s ease;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
        }

        .dropdown-menu a:hover,
        .dropdown-menu a:focus {
            background-color: #fff5fa;
            color: var(--color-dark);
            padding-left: 24px;
        }

        .dropdown-menu a i {
            margin-right: 8px;
            color: var(--color-primary);
            width: 16px;
        }

        /* ===== TAB NAVIGATION ===== */
        .tab-navigation {
            background-color: white;
            border-bottom: 2px solid var(--color-border);
            padding: 0;
            margin: 0;
            display: flex;
            position: sticky;
            top: 65px;
            z-index: 999;
            box-shadow: var(--shadow-sm);
        }

        .tab-btn {
            flex: 1;
            padding: 16px 20px;
            border: none;
            background: none;
            color: var(--color-text-muted);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: var(--color-text);
            background-color: var(--color-bg-light);
        }

        .tab-btn.active {
            color: var(--color-dark);
            border-bottom-color: var(--color-primary);
            background-color: #fffbfe;
        }

        .tab-btn i {
            font-size: 18px;
        }

        /* ===== PAGE WRAPPER ===== */
        .page-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 5% 40px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            margin-bottom: 35px;
        }

        .page-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .page-title {
            font-size: 36px;
            color: var(--color-text);
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: var(--color-primary);
        }

        .online-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background-color: var(--color-bg-light);
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--color-success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .page-subtitle {
            font-size: 15px;
            color: var(--color-text-light);
        }

        /* ===== FILTERS & SEARCH ===== */
        .filter-search-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            background-color: white;
            color: var(--color-dark);
            border: 2px solid var(--color-primary);
            border-radius: 30px;
            padding: 10px 20px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn:hover,
        .filter-btn:focus {
            background-color: #ffe3f1;
            outline: 2px solid var(--color-dark);
            outline-offset: 2px;
        }

        .filter-btn.active {
            background-color: var(--color-dark);
            color: white;
            border-color: var(--color-dark);
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 350px;
            min-width: 200px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border-radius: 30px;
            border: 2px solid var(--color-primary);
            background-color: white;
            outline: none;
            font-size: 15px;
            transition: border-color 0.3s ease;
        }

        .search-box input:focus {
            border-color: var(--color-dark);
        }

        .search-box i {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-dark);
            pointer-events: none;
        }

        /* ===== STATS BAR ===== */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-badge {
            background: linear-gradient(135deg, #ffe3f1, #f8e5ff);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            border-left: 4px solid var(--color-primary);
        }

        .stat-badge-value {
            font-size: 24px;
            font-weight: 900;
            color: var(--color-text);
            margin-bottom: 4px;
        }

        .stat-badge-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ===== ORDERS GRID ===== */
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        /* ===== ORDER CARD ===== */
        .order-card {
            background-color: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            border: 2px solid transparent;
        }

        .order-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
            border-color: var(--color-primary);
        }

        /* ORDER HEADER */
        .order-header {
            background: linear-gradient(135deg, var(--color-dark), var(--color-darker));
            color: white;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }

        .order-number {
            font-size: 13px;
            opacity: 0.85;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .order-id {
            font-size: 16px;
            font-weight: bold;
        }

        .order-status,
        .job-type-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-indicator,
        .job-type-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-indicator.in-transit,
        .job-type-dot.send {
            background-color: var(--color-accent);
            animation: pulse 2s infinite;
        }

        .status-indicator.pending,
        .job-type-dot.return {
            background-color: var(--color-warning);
            animation: pulse 2s infinite;
        }

        .status-indicator.completed {
            background-color: var(--color-success);
        }

        /* SHOP INFO */
        .shop-info {
            padding: 16px;
            border-bottom: 1px solid var(--color-border);
        }

        .shop-name {
            font-size: 16px;
            font-weight: bold;
            color: var(--color-text);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .shop-icon {
            font-size: 18px;
            color: var(--color-primary);
        }

        .shop-type {
            font-size: 12px;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ROUTE INFO */
        .route-info {
            padding: 16px;
            background-color: var(--color-bg-light);
            border-bottom: 1px solid var(--color-border);
        }

        .route-step {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }

        .route-step:last-child {
            margin-bottom: 0;
        }

        .route-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .route-icon i {
            color: var(--color-accent);
            font-size: 18px;
        }

        .route-connector {
            width: 2px;
            height: 16px;
            background-color: var(--color-primary);
            margin: -8px 0;
        }

        .route-content {
            flex: 1;
        }

        .route-label {
            color: var(--color-text-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .route-address {
            color: var(--color-text);
            font-weight: 600;
            font-size: 13px;
            line-height: 1.4;
        }

        /* ORDER DETAILS */
        .order-details {
            padding: 16px;
            flex-grow: 1;
        }

        .detail-row {
            display: flex;
            align-items: start;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 13px;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .detail-icon {
            color: var(--color-accent);
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
            min-width: 20px;
        }

        .detail-content {
            flex: 1;
        }

        .detail-label {
            color: var(--color-text-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .detail-value {
            color: var(--color-text);
            font-weight: 600;
            line-height: 1.4;
        }

        /* DELIVERY INFO BOX */
        .delivery-info,
        .earnings-info {
            background-color: var(--color-bg-light);
            padding: 12px;
            border-radius: 10px;
            margin-top: 12px;
        }

        .earnings-info {
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
        }

        .delivery-info-row,
        .earnings-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .delivery-info-row:last-child,
        .earnings-row:last-child {
            margin-bottom: 0;
        }

        .delivery-info-label,
        .earnings-label {
            color: #888;
            font-weight: 600;
        }

        .earnings-label {
            color: #6c3c00;
        }

        .delivery-info-value,
        .earnings-value {
            font-weight: bold;
            color: var(--color-text);
        }

        .earnings-value {
            color: #6c3c00;
            font-size: 16px;
        }

        /* PROGRESS TRACKER */
        .progress-tracker {
            margin-top: 12px;
            padding: 12px;
            background-color: #f0f9ff;
            border-radius: 10px;
            border-left: 4px solid var(--color-accent);
        }

        .progress-label {
            font-size: 11px;
            font-weight: bold;
            color: var(--color-accent);
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background-color: #e0f2fe;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background-color: var(--color-accent);
            transition: width 0.3s ease;
        }

        /* ORDER FOOTER */
        .order-footer {
            padding: 16px;
            border-top: 1px solid var(--color-border);
            display: flex;
            gap: 10px;
        }

        .btn-action {
            flex: 1;
            padding: 10px 12px;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-primary-action,
        .btn-accept {
            background-color: var(--color-primary);
            color: var(--color-text);
        }

        .btn-accept {
            background-color: var(--color-success);
            color: white;
        }

        .btn-primary-action:hover,
        .btn-primary-action:focus,
        .btn-accept:hover,
        .btn-accept:focus {
            background-color: var(--color-dark);
            color: white;
            transform: translateY(-2px);
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        .btn-accept:hover,
        .btn-accept:focus {
            background-color: #22c55e;
        }

        .btn-secondary-action,
        .btn-view-details {
            background-color: white;
            color: var(--color-dark);
            border: 2px solid var(--color-primary);
        }

        .btn-secondary-action:hover,
        .btn-secondary-action:focus,
        .btn-view-details:hover,
        .btn-view-details:focus {
            background-color: #ffe3f1;
            border-color: var(--color-dark);
            outline: 2px solid var(--color-dark);
            outline-offset: 2px;
        }

        /* ===== MODAL ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow-y: auto;
            padding: 20px;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            border-radius: 18px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--color-dark), var(--color-darker));
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--color-border);
        }

        .modal-label {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 4px;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }

        .close-btn:hover,
        .close-btn:focus {
            transform: scale(1.1);
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-section {
            margin-bottom: 25px;
        }

        .modal-section:last-child {
            margin-bottom: 0;
        }

        .modal-section-title {
            font-size: 16px;
            font-weight: bold;
            color: var(--color-text);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-section-title i {
            color: var(--color-primary);
        }

        .detail-item {
            display: flex;
            align-items: start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--color-border);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-item-label {
            color: #888;
            font-size: 13px;
            min-width: 100px;
            font-weight: 600;
        }

        .detail-item-value {
            color: var(--color-text);
            flex: 1;
            font-size: 14px;
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            padding-top: 20px;
            border-top: 2px solid var(--color-border);
        }

        .modal-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-btn-primary {
            background-color: var(--color-dark);
            color: white;
        }

        .modal-btn-primary:hover,
        .modal-btn-primary:focus {
            background-color: var(--color-darker);
            transform: translateY(-2px);
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        .modal-btn-secondary {
            background-color: white;
            color: var(--color-dark);
            border: 2px solid var(--color-primary);
        }

        .modal-btn-secondary:hover,
        .modal-btn-secondary:focus {
            background-color: #ffe3f1;
            outline: 2px solid var(--color-dark);
            outline-offset: 2px;
        }

        /* ===== LOGOUT MODAL SPECIFIC STYLES ===== */
        #logoutModal .modal-content {
            max-width: 420px;
        }

        #logoutModal .modal-header {
            flex-direction: column;
            text-align: center;
            gap: 0;
        }

        #logoutModal .modal-title {
            justify-content: center;
            font-size: 22px;
            width: 100%;
        }

        #logoutModal .close-btn {
            position: absolute;
            right: 25px;
            top: 25px;
        }

        #logoutModal .modal-body {
            text-align: center;
            padding: 35px 30px 30px;
        }

        #logoutModal .modal-body p {
            font-size: 16px;
            color: var(--color-text);
            margin-bottom: 28px;
            font-weight: 500;
            line-height: 1.5;
        }

        #logoutModal .modal-actions {
            flex-direction: column;
            gap: 10px;
            border-top: none;
            padding-top: 0;
        }

        #logoutModal .modal-btn {
            padding: 14px;
            font-size: 15px;
        }

        /* ===== HISTORY PANEL ===== */
        .deliveries-panel {
            background-color: white;
            border-radius: 18px;
            margin: 10px auto 30px;
            padding: 25px;
            max-width: 1200px;
            box-shadow: var(--shadow-md);
        }

        .panel-title {
            font-size: 20px;
            font-weight: 900;
            color: var(--color-text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-title i {
            color: var(--color-primary);
            font-size: 24px;
        }

        .delivery-item {
            background-color: var(--color-bg-light);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 15px;
            border-left: 4px solid var(--color-primary);
            transition: all 0.3s ease;
        }

        .delivery-item:hover {
            background-color: #fff5fa;
            box-shadow: 0 4px 12px rgba(229, 141, 181, 0.2);
        }

        .delivery-item:last-child {
            margin-bottom: 0;
        }

        .delivery-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
            gap: 15px;
        }

        .delivery-shop {
            font-weight: bold;
            color: var(--color-text);
            font-size: 15px;
        }

        .delivery-distance {
            background-color: var(--color-primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .delivery-address {
            font-size: 13px;
            color: var(--color-text-light);
            margin-bottom: 8px;
            display: flex;
            align-items: start;
            gap: 6px;
        }

        .delivery-address i {
            color: var(--color-accent);
            margin-top: 2px;
            flex-shrink: 0;
        }

        .delivery-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #888;
            flex-wrap: wrap;
        }

        .delivery-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .delivery-meta i {
            color: var(--color-accent);
        }

        .see-more-container {
            text-align: center;
            padding-top: 20px;
            margin-top: 20px;
        }

        .see-more-btn {
            display: inline-block;
            padding: 10px 32px;
            background-color: var(--color-primary);
            color: var(--color-text);
            border-radius: 24px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .see-more-btn:hover,
        .see-more-btn:focus {
            background-color: var(--color-dark);
            color: white;
            transform: translateY(-2px);
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        /* ===== FOOTER ===== */
        footer {
            background-color: var(--color-text);
            color: white;
            padding: 30px 8%;
            text-align: center;
            margin-top: 50px;
            font-size: 13px;
        }

        footer p {
            opacity: 0.85;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .page-wrapper {
                padding: 20px 4% 40px;
            }

            .page-title {
                font-size: 28px;
            }

            .page-header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .filter-search-container {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .search-box {
                max-width: 100%;
            }

            .stats-bar {
                grid-template-columns: repeat(2, 1fr);
            }

            .orders-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                max-width: 95%;
            }

            .tab-btn {
                font-size: 13px;
                padding: 12px 10px;
            }

            .tab-btn i {
                font-size: 16px;
            }

            .delivery-header {
                flex-direction: column;
                gap: 8px;
            }
        }

        @media (max-width: 480px) {
            header {
                padding: 12px 4%;
            }

            .logo-img {
                height: 60px;
            }

            .page-title {
                font-size: 22px;
            }

            .online-status {
                width: 100%;
                justify-content: center;
                font-size: 12px;
            }

            .order-header {
                flex-direction: column;
                gap: 10px;
            }

            .order-footer {
                flex-direction: column;
                gap: 8px;
            }

            .stats-bar {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .filter-buttons {
                width: 100%;
                flex-direction: column;
                gap: 8px;
            }

            .filter-btn {
                width: 100%;
                font-size: 13px;
                padding: 8px 16px;
            }

            .modal-header {
                flex-direction: column;
                align-items: center;
                gap: 12px;
                padding: 20px;
            }

            .modal-header .modal-label {
                display: none;
            }

            .modal-title {
                font-size: 20px;
            }

            .close-btn {
                position: absolute;
                right: 15px;
                top: 15px;
                font-size: 24px;
            }

            .modal-body {
                padding: 20px;
            }

            .modal-actions {
                flex-direction: column;
                gap: 8px;
            }

            .modal-btn {
                padding: 11px;
                font-size: 13px;
            }

            #logoutModal .modal-body {
                padding: 25px 20px 20px;
            }

            #logoutModal .modal-body p {
                font-size: 15px;
                margin-bottom: 20px;
            }

            .delivery-header {
                flex-direction: column;
                gap: 8px;
            }

            .tab-btn {
                font-size: 12px;
                padding: 10px 8px;
                gap: 4px;
            }

            .tab-btn i {
                font-size: 16px;
            }

            .route-step {
                margin-bottom: 16px;
            }

            .page-wrapper {
                padding: 20px 3% 40px;
            }
        }

        /* ===== ADDED IN THE REWORK ===== */
        .tab-navigation { top: 90px; }
        @media (max-width: 600px) { .tab-navigation { top: 76px; } }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 18px;
            color: var(--color-text-light);
            box-shadow: var(--shadow-md);
        }
        .empty-state i { font-size: 34px; color: var(--color-primary); margin-bottom: 10px; display: block; }
        .empty-state strong { display: block; color: var(--color-text); font-size: 16px; margin-bottom: 4px; }

        .flash {
            position: fixed; left: 50%; bottom: 24px; transform: translate(-50%, 20px);
            z-index: 3000; max-width: calc(100vw - 32px);
            padding: 14px 20px; border-radius: 12px; font-size: 14px; font-weight: 600;
            background: var(--color-dark); color: white; box-shadow: var(--shadow-lg);
            opacity: 0; pointer-events: none; transition: opacity .25s ease, transform .25s ease;
        }
        .flash.show { opacity: 1; transform: translate(-50%, 0); }
        .flash.error { background: #b3261e; }

        .btn-action[disabled], .btn-action.is-disabled { opacity: .5; cursor: not-allowed; pointer-events: none; }
        a.btn-action { text-decoration: none; }
        .modal-note { font-size: 13px; color: var(--color-text-light); padding: 4px 0; }
    </style>
    <link rel="stylesheet" href="../css/rider_header.css">
    <script>
        window.LV_CONFIG = <?= js_json($lvConfig) ?>;
        window.LV_SESSION = <?= js_json($lvSession) ?>;
    </script>
    <script src="../js/lavandera_demo.js"></script>
    <script src="../js/lavandera_api.js"></script>
</head>

<body>

    <!-- HEADER -->
    <header class="rider-site-header">
        <a href="lavandera_rider_dashboard.php" class="logo">
            <img src="../images/LAVANDERA.svg" alt="Lavandera Logo" class="logo-img">
        </a>

        <div class="menu-container">
            <button class="menu-btn" type="button" onclick="toggleMenu()" aria-label="Toggle navigation menu"
                aria-expanded="false" aria-controls="navDropdown">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="dropdown-menu" id="navDropdown" role="navigation" aria-label="Rider navigation">
                <div class="dropdown-header">
                    <div class="user-avatar"><?= h(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
                    <div class="user-name"><?= h($user['name']) ?></div>
                    <div class="user-email"><?= h($user['email']) ?></div>
                </div>
                <a href="lavandera_rider_dashboard.php"><i class="fa-solid fa-house"></i>Dashboard</a>
                <a href="Rider_Orders_Page.php" aria-current="page"><i class="fa-solid fa-boxes-stacked"></i>Current Orders</a>
                <a href="Rider_Profile_Page.php"><i class="fa-solid fa-user"></i>Profile</a>
                <a href="../auth/logout.php"><i class="fa-solid fa-sign-out-alt"></i>Logout</a>
            </div>
        </div>
    </header>

    <!-- TAB NAVIGATION -->
    <div class="tab-navigation">
        <button class="tab-btn active" onclick="switchTab('available')" aria-label="Available Orders tab">
            <i class="fa-solid fa-clipboard-list"></i>
            <span>Available Orders</span>
        </button>
        <button class="tab-btn" onclick="switchTab('current')" aria-label="Current Orders tab">
            <i class="fa-solid fa-boxes-stacked"></i>
            <span>Current Orders</span>
        </button>
    </div>

    <!-- ===== AVAILABLE ORDERS TAB ===== -->
    <div id="available" class="tab-content active">
        <div class="page-wrapper">

            <div class="page-header">
                <h1 class="page-title">
                    <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                    Available Orders
                </h1>
                <p class="page-subtitle">Browse and accept available delivery jobs</p>
            </div>

            <div class="filter-search-container">
                <div class="filter-buttons" role="group" aria-label="Filter orders">
                    <button class="filter-btn active" data-filter="all">All Jobs</button>
                    <button class="filter-btn" data-filter="send">Send Orders</button>
                    <button class="filter-btn" data-filter="return">Return Orders</button>
                </div>
                <div class="search-box">
                    <label for="searchAvailable" style="display: none;">Search orders</label>
                    <input type="text" id="searchAvailable" placeholder="Search by shop or address..."
                        aria-label="Search available orders">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </div>
            </div>

            <div class="stats-bar">
                <div class="stat-badge">
                    <div class="stat-badge-value" id="availableTotalCount">0</div>
                    <div class="stat-badge-label">Available Jobs</div>
                </div>
                <div class="stat-badge">
                    <div class="stat-badge-value" id="availableSendCount">0</div>
                    <div class="stat-badge-label">Send Orders</div>
                </div>
                <div class="stat-badge">
                    <div class="stat-badge-value" id="availableReturnCount">0</div>
                    <div class="stat-badge-label">Return Orders</div>
                </div>
                <div class="stat-badge">
                    <div class="stat-badge-value" id="maxEarnings">&#8369;0.00</div>
                    <div class="stat-badge-label">Potential Earnings</div>
                </div>
            </div>

            <div class="orders-grid" id="availableOrdersGrid">
                <!-- Orders generated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- ===== CURRENT ORDERS TAB ===== -->
    <div id="current" class="tab-content">
        <div class="page-wrapper">

            <div class="page-header">
                <h1 class="page-title">
                    <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i>
                    Current Orders
                </h1>
                <p class="page-subtitle">Jobs you accepted. The store confirms a send delivery and the customer confirms a return, so there is nothing to tick off here.</p>
            </div>

            <div class="filter-search-container">
                <div class="filter-buttons" role="group" aria-label="Filter orders">
                    <button class="filter-btn active" data-filter="all">All Orders</button>
                    <button class="filter-btn" data-filter="send">Send</button>
                    <button class="filter-btn" data-filter="return">Return</button>
                </div>
                <div class="search-box">
                    <label for="searchCurrent" style="display: none;">Search orders</label>
                    <input type="text" id="searchCurrent" placeholder="Search by order ID, shop or customer..."
                        aria-label="Search current orders">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </div>
            </div>

            <div class="stats-bar">
                <div class="stat-badge">
                    <div class="stat-badge-value" id="currentTotalCount">0</div>
                    <div class="stat-badge-label">Total Orders</div>
                </div>
                <div class="stat-badge">
                    <div class="stat-badge-value" id="currentSendCount">0</div>
                    <div class="stat-badge-label">Send</div>
                </div>
                <div class="stat-badge">
                    <div class="stat-badge-value" id="currentReturnCount">0</div>
                    <div class="stat-badge-label">Return</div>
                </div>
                <div class="stat-badge">
                    <div class="stat-badge-value" id="currentPay">&#8369;0.00</div>
                    <div class="stat-badge-label">Pay Pending</div>
                </div>
            </div>

            <div class="orders-grid" id="currentOrdersGrid">
                <!-- Orders generated by JavaScript -->
            </div>

            <!-- HISTORY OF ORDERS (latest 3 from the database) -->
            <div class="deliveries-panel">
                <div class="panel-title">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                    History of Orders
                </div>
                <div id="historyPreview"></div>
                <div class="see-more-container">
                    <button class="see-more-btn" onclick="window.location.href = 'Rider_Order_History_Page.php'">View Full History</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ORDER DETAILS MODAL (filled from the job that was clicked) -->
    <div class="modal" id="orderModal" role="dialog" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="modal-label">Order Details</div>
                    <div class="modal-title" id="modalTitle">#LV-0</div>
                </div>
                <button class="close-btn" onclick="closeModal()" aria-label="Close order details">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="modal-section">
                    <div class="modal-section-title"><i class="fa-solid fa-route" aria-hidden="true"></i> Route &amp; Distance</div>
                    <div class="detail-item"><div class="detail-item-label">Job Type</div><div class="detail-item-value" id="modalType"></div></div>
                    <div class="detail-item"><div class="detail-item-label">Total Distance</div><div class="detail-item-value" id="modalDistance"></div></div>
                    <div class="detail-item"><div class="detail-item-label">Est. Duration</div><div class="detail-item-value" id="modalDuration"></div></div>
                    <div class="detail-item"><div class="detail-item-label">Pickup Address</div><div class="detail-item-value" id="modalPickup"></div></div>
                    <div class="detail-item"><div class="detail-item-label">Delivery Address</div><div class="detail-item-value" id="modalDelivery"></div></div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title"><i class="fa-solid fa-store" aria-hidden="true"></i> Shop Information</div>
                    <div class="detail-item"><div class="detail-item-label">Shop Name</div><div class="detail-item-value" id="modalShop"></div></div>
                    <div class="detail-item" id="modalShopPhoneRow"><div class="detail-item-label">Shop Phone</div><div class="detail-item-value" id="modalShopPhone"></div></div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title"><i class="fa-solid fa-user" aria-hidden="true"></i> Customer Information</div>
                    <div id="modalCustomerHidden" class="modal-note">The customer's name and phone are shown after you accept this job.</div>
                    <div id="modalCustomerBlock">
                        <div class="detail-item"><div class="detail-item-label">Name</div><div class="detail-item-value" id="modalCustomer"></div></div>
                        <div class="detail-item"><div class="detail-item-label">Phone</div><div class="detail-item-value" id="modalCustomerPhone"></div></div>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title"><i class="fa-solid fa-money-bill" aria-hidden="true"></i> Earnings</div>
                    <div class="detail-item">
                        <div class="detail-item-label">Your Pay</div>
                        <div class="detail-item-value" style="font-weight: bold; font-size: 16px; color: var(--color-dark);" id="modalTotal"></div>
                    </div>
                </div>

                <div class="modal-actions" id="modalActions"></div>
            </div>
        </div>
    </div>

    <div class="flash" id="flash" role="status" aria-live="polite"></div>

    <!-- FOOTER -->
    <footer>
        <p>&copy; 2026 LAVANDERA. Rider Orders Manager. All Rights Reserved.</p>
    </footer>

    <script>
        // ---- small helpers -------------------------------------------------------------------------------
        var flashTimer = null;
        function flash(message, type) {
            var el = document.getElementById('flash');
            el.textContent = message;
            el.className = 'flash show' + (type === 'error' ? ' error' : '');
            clearTimeout(flashTimer);
            flashTimer = setTimeout(function () { el.classList.remove('show'); }, 4000);
        }

        function toggleMenu() {
            var menu = document.getElementById('navDropdown');
            var isOpen = menu.classList.toggle('show');
            document.querySelector('.menu-btn').setAttribute('aria-expanded', String(isOpen));
        }
        document.addEventListener('click', function (event) {
            if (!event.target.closest('.menu-container')) {
                document.getElementById('navDropdown').classList.remove('show');
                document.querySelector('.menu-btn').setAttribute('aria-expanded', 'false');
            }
        });

        function switchTab(name) {
            document.querySelectorAll('.tab-content, .tab-btn').forEach(function (el) { el.classList.remove('active'); });
            document.getElementById(name).classList.add('active');
            document.querySelectorAll('.tab-btn')[name === 'available' ? 0 : 1].classList.add('active');
            try { history.replaceState(null, '', '#' + name); } catch (e) { /* ignore */ }
        }

        // A phone number only goes into a tel: link after everything but digits and + is removed.
        function telHref(number) { return 'tel:' + String(number || '').replace(/[^0-9+]/g, ''); }

        var availableJobs = [];   // jobs from the server (the real objects, not copies in HTML attributes)
        var currentJobs = [];
        var historyJobs = [];
        var openJob = null;       // the job shown in the details popup

        function jobLabel(job) { return '#LV-' + job.orderId + (job.type === 'send' ? '-S' : '-R'); }
        function typeLabel(job) { return job.type === 'send' ? 'Send order (customer to store)' : 'Return order (store to customer)'; }
        function searchText(job) {
            return [jobLabel(job), job.shop, job.pickup, job.dropoff, job.customer || ''].join(' ').toLowerCase();
        }
        function emptyState(icon, title, text) {
            return '<div class="empty-state"><i class="fa-solid ' + icon + '"></i><strong>' + title + '</strong>' + text + '</div>';
        }

        // ---- rendering (every value a person typed goes through LV.esc) ------------------------------------
        function routeBlock(job) {
            return '<div class="route-info">' +
                '<div class="route-step"><div class="route-icon"><i class="fa-solid fa-' + (job.type === 'send' ? 'home' : 'store') + '"></i></div>' +
                    '<div class="route-content"><div class="route-label">Pickup From</div><div class="route-address">' + LV.esc(job.pickup) + '</div></div></div>' +
                '<div class="route-step"><div class="route-icon"><div class="route-connector"></div><i class="fa-solid fa-' + (job.type === 'send' ? 'store' : 'home') + '"></i></div>' +
                    '<div class="route-content"><div class="route-label">' + (job.type === 'send' ? 'Deliver To' : 'Return To') + '</div><div class="route-address">' + LV.esc(job.dropoff) + '</div></div></div>' +
            '</div>';
        }

        function renderAvailable() {
            document.getElementById('availableOrdersGrid').innerHTML = availableJobs.map(function (o) {
                return '<div class="order-card" data-type="' + o.type + '" data-pay="' + o.pay + '" data-search="' + LV.esc(searchText(o)) + '">' +
                    '<div class="order-header">' +
                        '<div><div class="order-number">Job ID</div><div class="order-id">' + jobLabel(o) + '</div></div>' +
                        '<div class="job-type-badge"><span class="job-type-dot ' + o.type + '"></span>' + (o.type === 'send' ? 'Send Order' : 'Return Order') + '</div>' +
                    '</div>' +
                    '<div class="shop-info"><div class="shop-name"><i class="fa-solid fa-location-dot shop-icon"></i> ' + LV.esc(o.shop) + '</div><div class="shop-type">Delivery Service</div></div>' +
                    routeBlock(o) +
                    '<div class="order-details">' +
                        '<div class="detail-row"><i class="fa-solid fa-map detail-icon"></i><div class="detail-content"><div class="detail-label">Distance</div><div class="detail-value">' + o.distanceKm.toFixed(1) + ' km</div></div></div>' +
                        '<div class="detail-row"><i class="fa-solid fa-hourglass-end detail-icon"></i><div class="detail-content"><div class="detail-label">Est. Time</div><div class="detail-value">' + o.minutes + ' minutes</div></div></div>' +
                        '<div class="earnings-info"><div class="earnings-row"><span class="earnings-label">Your Pay</span><span class="earnings-value">' + LV.peso(o.pay) + '</span></div></div>' +
                    '</div>' +
                    '<div class="order-footer">' +
                        '<button class="btn-action btn-accept" data-job-id="' + o.id + '"><i class="fa-solid fa-check"></i> Accept Order</button>' +
                        '<button class="btn-action btn-view-details" data-job-id="' + o.id + '" data-list="available"><i class="fa-solid fa-circle-info"></i> Details</button>' +
                    '</div></div>';
            }).join('') || emptyState('fa-clipboard-check', 'No jobs available right now', 'New jobs appear here as stores confirm orders. This list refreshes by itself.');
        }

        function renderCurrent() {
            document.getElementById('currentOrdersGrid').innerHTML = currentJobs.map(function (o) {
                var heading = o.type === 'send' ? 'Heading to the store' : 'Heading to the customer';
                return '<div class="order-card" data-type="' + o.type + '" data-pay="' + o.pay + '" data-search="' + LV.esc(searchText(o)) + '">' +
                    '<div class="order-header">' +
                        '<div><div class="order-number">Order</div><div class="order-id">' + jobLabel(o) + '</div></div>' +
                        '<div class="order-status"><span class="status-indicator in-transit"></span>' + heading + '</div>' +
                    '</div>' +
                    '<div class="shop-info"><div class="shop-name"><i class="fa-solid fa-location-dot shop-icon"></i> ' + LV.esc(o.shop) + '</div><div class="shop-type">' + (o.type === 'send' ? 'Send order' : 'Return order') + '</div></div>' +
                    routeBlock(o) +
                    '<div class="order-details">' +
                        '<div class="detail-row"><i class="fa-solid fa-user detail-icon"></i><div class="detail-content"><div class="detail-label">Customer</div><div class="detail-value">' + LV.esc(o.customer) + '</div></div></div>' +
                        '<div class="delivery-info">' +
                            '<div class="delivery-info-row"><span class="delivery-info-label">Distance</span><span class="delivery-info-value">' + o.distanceKm.toFixed(1) + ' km</span></div>' +
                            '<div class="delivery-info-row"><span class="delivery-info-label">Est. Time</span><span class="delivery-info-value">' + o.minutes + ' mins</span></div>' +
                            '<div class="delivery-info-row"><span class="delivery-info-label">Your Pay</span><span class="delivery-info-value">' + LV.peso(o.pay) + '</span></div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="order-footer">' +
                        '<button class="btn-action btn-primary-action btn-view-details" data-job-id="' + o.id + '" data-list="current"><i class="fa-solid fa-circle-info"></i> View Details</button>' +
                        (o.customerContact
                            ? '<a class="btn-action btn-secondary-action" href="' + LV.esc(telHref(o.customerContact)) + '"><i class="fa-solid fa-phone"></i> Call Customer</a>'
                            : '<span class="btn-action btn-secondary-action is-disabled"><i class="fa-solid fa-phone"></i> No phone</span>') +
                    '</div></div>';
            }).join('') || emptyState('fa-box-open', 'No current orders', 'Accept a job from the Available Orders tab and it will show up here.');
        }

        function renderHistoryPreview() {
            document.getElementById('historyPreview').innerHTML = historyJobs.slice(0, 3).map(function (job) {
                return '<div class="delivery-item">' +
                    '<div class="delivery-header"><div class="delivery-shop">' + LV.esc(job.shop) + '</div><div class="delivery-distance">' + job.distanceKm.toFixed(1) + ' km</div></div>' +
                    '<div class="delivery-address"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>' + LV.esc(job.pickup) + ' &rarr; ' + LV.esc(job.dropoff) + '</span></div>' +
                    '<div class="delivery-meta"><span><i class="fa-solid fa-clock" aria-hidden="true"></i> Completed ' + LV.when(job.completedAt) + '</span>' +
                    '<span><i class="fa-solid fa-money-bill" aria-hidden="true"></i> ' + LV.peso(job.pay) + '</span></div></div>';
            }).join('') || '<div class="delivery-item"><div class="delivery-shop">No completed deliveries yet</div></div>';
        }

        // ---- filter, search and the numbers above each list ------------------------------------------------
        function applyFilters(section) {
            var root = document.getElementById(section);
            var activeBtn = root.querySelector('.filter-btn.active');
            var filter = activeBtn ? activeBtn.getAttribute('data-filter') : 'all';
            var query = root.querySelector('input[type="text"]').value.trim().toLowerCase();
            var total = 0, send = 0, ret = 0, pay = 0;
            root.querySelectorAll('.order-card').forEach(function (card) {
                var type = card.getAttribute('data-type');
                var show = (filter === 'all' || filter === type) && (!query || card.getAttribute('data-search').indexOf(query) > -1);
                card.style.display = show ? 'flex' : 'none';
                if (show) {
                    total++; pay += parseFloat(card.getAttribute('data-pay')) || 0;
                    if (type === 'send') send++; else ret++;
                }
            });
            if (section === 'available') {
                document.getElementById('availableTotalCount').textContent = total;
                document.getElementById('availableSendCount').textContent = send;
                document.getElementById('availableReturnCount').textContent = ret;
                document.getElementById('maxEarnings').textContent = LV.peso(pay);
            } else {
                document.getElementById('currentTotalCount').textContent = total;
                document.getElementById('currentSendCount').textContent = send;
                document.getElementById('currentReturnCount').textContent = ret;
                document.getElementById('currentPay').textContent = LV.peso(pay);
            }
        }

        document.querySelectorAll('.filter-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var root = btn.closest('.tab-content');
                root.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                applyFilters(root.id);
            });
        });
        ['available', 'current'].forEach(function (section) {
            document.getElementById(section).querySelector('input[type="text"]').addEventListener('input', function () { applyFilters(section); });
        });

        function showBoard(data) {
            availableJobs = data.available;
            currentJobs = data.current;
            historyJobs = data.history;
            renderAvailable(); renderCurrent(); renderHistoryPreview();
            applyFilters('available'); applyFilters('current');
        }

        async function loadJobs(quiet) {
            try {
                showBoard(await LVApi.get('../api/rider/jobs.php?history_limit=3'));
            } catch (error) {
                if (!quiet) flash(error.message, 'error');
            }
        }

        // ---- details popup ---------------------------------------------------------------------------------
        function closeModal() {
            document.getElementById('orderModal').classList.remove('show');
            document.getElementById('orderModal').setAttribute('aria-hidden', 'true');
            document.body.style.overflow = 'auto';
            openJob = null;
        }

        function openDetails(job, isCurrent) {
            openJob = job;
            document.getElementById('modalTitle').textContent = jobLabel(job);
            document.getElementById('modalType').textContent = typeLabel(job);
            document.getElementById('modalDistance').textContent = job.distanceKm.toFixed(1) + ' km';
            document.getElementById('modalDuration').textContent = job.minutes + ' minutes';
            document.getElementById('modalPickup').textContent = job.pickup;
            document.getElementById('modalDelivery').textContent = job.dropoff;
            document.getElementById('modalShop').textContent = job.shop;
            document.getElementById('modalTotal').textContent = LV.peso(job.pay);

            var shopRow = document.getElementById('modalShopPhoneRow');
            shopRow.style.display = job.shopContact ? '' : 'none';
            document.getElementById('modalShopPhone').innerHTML = job.shopContact
                ? '<a href="' + LV.esc(telHref(job.shopContact)) + '">' + LV.esc(job.shopContact) + '</a>' : '';

            document.getElementById('modalCustomerHidden').style.display = isCurrent ? 'none' : '';
            document.getElementById('modalCustomerBlock').style.display = isCurrent ? '' : 'none';
            document.getElementById('modalCustomer').textContent = job.customer || '';
            document.getElementById('modalCustomerPhone').innerHTML = job.customerContact
                ? '<a href="' + LV.esc(telHref(job.customerContact)) + '">' + LV.esc(job.customerContact) + '</a>' : 'Not provided';

            document.getElementById('modalActions').innerHTML = isCurrent
                ? '<button class="modal-btn modal-btn-primary" onclick="closeModal()"><i class="fa-solid fa-xmark"></i> Close</button>'
                : '<button class="modal-btn modal-btn-primary" data-job-id="' + job.id + '" id="modalAccept"><i class="fa-solid fa-check"></i> Accept Order</button>' +
                  '<button class="modal-btn modal-btn-secondary" onclick="closeModal()"><i class="fa-solid fa-xmark"></i> Cancel</button>';

            document.getElementById('orderModal').classList.add('show');
            document.getElementById('orderModal').setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        // ---- accept ----------------------------------------------------------------------------------------
        var accepting = false;
        async function acceptJob(jobId) {
            var job = availableJobs.find(function (j) { return j.id === jobId; });
            if (!job || accepting) return;
            if (!confirm('Accept ' + jobLabel(job) + ' from ' + job.shop + '? You will be paid ' + LV.peso(job.pay) + '.')) return;
            accepting = true;
            try {
                var data = await LVApi.postJson('../api/rider/jobs.php?history_limit=3', { action: 'accept', job_id: jobId });
                closeModal();
                showBoard(data);
                flash(jobLabel(job) + ' accepted. It is now in your Current Orders.');
                switchTab('current');
            } catch (error) {
                flash(error.message, 'error');   // e.g. "Another rider already took this job."
                closeModal();
                loadJobs(true);                   // refresh so the taken job disappears
            } finally {
                accepting = false;
            }
        }

        document.addEventListener('click', function (event) {
            var accept = event.target.closest('.btn-accept, #modalAccept');
            if (accept) { acceptJob(parseInt(accept.getAttribute('data-job-id'), 10)); return; }
            var view = event.target.closest('.btn-view-details');
            if (view) {
                var isCurrent = view.getAttribute('data-list') === 'current';
                var id = parseInt(view.getAttribute('data-job-id'), 10);
                var job = (isCurrent ? currentJobs : availableJobs).find(function (j) { return j.id === id; });
                if (job) openDetails(job, isCurrent);
                return;
            }
            if (event.target.id === 'orderModal') closeModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { closeModal(); document.getElementById('navDropdown').classList.remove('show'); }
        });

        // ---- start, then keep the board fresh ----------------------------------------------------------------
        if (location.hash === '#current') switchTab('current');
        loadJobs(false);
        setInterval(function () { if (!document.hidden && !openJob && !accepting) loadJobs(true); }, 30000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden && !openJob) loadJobs(true); });
    </script>

</body>

</html>

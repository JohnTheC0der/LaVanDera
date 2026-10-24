<?php
/**
 * Rider Profile (converted to PHP): name and phone are real and saved to the database.
 * Runs on the SERVER before any HTML is sent:
 *   1. bootstrap.php = config + session + helpers
 *   2. guard_rider_page() = redirects away unless the visitor is the right, approved kind of user
 *   3. $lvConfig / $lvSession = handed to JavaScript below (CSRF token + who is really signed in)
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
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>LAVANDERA - Account Settings</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
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
            --color-error: #ef4444;
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
            background-color: var(--color-dark);
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
            transition: background-color 0.3s ease;
        }

        .menu-btn:hover,
        .menu-btn:focus {
            background-color: var(--color-darker);
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
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
            box-shadow: var(--shadow-lg);
            z-index: 1001;
        }

        .dropdown-menu.show {
            display: block;
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

        /* ===== PAGE WRAPPER ===== */
        .page-wrapper {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 5% 40px;
        }

        /* ===== PAGE HEADER ===== */
        .page-header {
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 36px;
            color: var(--color-text);
            font-weight: 900;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: var(--color-primary);
        }

        .page-subtitle {
            font-size: 15px;
            color: var(--color-text-light);
        }

        /* ===== PROFILE CARD - TOP SECTION ===== */
        .profile-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--color-dark), var(--color-darker));
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-primary), var(--color-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: bold;
            margin: 0 auto 20px;
            border: 4px solid white;
            box-shadow: var(--shadow-lg);
        }

        .profile-name {
            font-size: 28px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .profile-email {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 4px;
        }

        .profile-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background-color: rgba(74, 222, 128, 0.2);
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 12px;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
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

        /* PROFILE STATS */
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            padding: 30px;
            background-color: var(--color-bg-light);
            border-bottom: 1px solid var(--color-border);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 900;
            color: var(--color-primary);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* PROFILE INFO */
        .profile-info {
            padding: 30px;
        }

        .info-section {
            margin-bottom: 30px;
        }

        .info-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 16px;
            font-weight: 900;
            color: var(--color-text);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i {
            color: var(--color-primary);
            font-size: 18px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            background-color: var(--color-bg-light);
            padding: 16px;
            border-radius: 12px;
            border-left: 4px solid var(--color-primary);
        }

        .info-label {
            font-size: 12px;
            color: var(--color-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .info-value {
            font-size: 15px;
            color: var(--color-text);
            font-weight: 600;
            word-break: break-word;
        }

        .info-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background-color: var(--color-bg);
            border-radius: 6px;
            font-size: 12px;
            margin-top: 6px;
        }

        .status-badge {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: var(--color-success);
        }

        /* ===== EDIT SECTION ===== */
        .edit-section {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }

        .edit-header {
            background-color: var(--color-bg-light);
            padding: 20px 30px;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .edit-title {
            font-size: 18px;
            font-weight: 900;
            color: var(--color-text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .edit-title i {
            color: var(--color-primary);
            font-size: 20px;
        }

        .edit-toggle {
            background-color: var(--color-primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .edit-toggle:hover,
        .edit-toggle:focus {
            background-color: var(--color-dark);
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        /* FORM SECTION */
        .form-section {
            display: none;
            padding: 30px;
        }

        .form-section.active {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input,
        .form-select,
        .form-textarea {
            padding: 12px 14px;
            border: 2px solid var(--color-border);
            border-radius: 10px;
            font-size: 14px;
            color: var(--color-text);
            background-color: white;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(229, 141, 181, 0.1);
        }

        .form-input::placeholder {
            color: var(--color-text-muted);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-hint {
            font-size: 12px;
            color: var(--color-text-muted);
            margin-top: 6px;
        }

        /* FORM ACTIONS */
        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 25px;
            border-top: 1px solid var(--color-border);
        }

        .btn-save,
        .btn-cancel {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-save {
            background-color: var(--color-success);
            color: white;
        }

        .btn-save:hover,
        .btn-save:focus {
            background-color: #22c55e;
            transform: translateY(-2px);
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        .btn-cancel {
            background-color: white;
            color: var(--color-dark);
            border: 2px solid var(--color-border);
        }

        .btn-cancel:hover,
        .btn-cancel:focus {
            background-color: var(--color-bg-light);
            border-color: var(--color-dark);
            outline: 2px solid var(--color-dark);
            outline-offset: 2px;
        }

        /* DANGER ZONE */
        .danger-zone {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 2px solid var(--color-error);
            margin-bottom: 30px;
        }

        .danger-header {
            background-color: #fee2e2;
            padding: 20px 30px;
            border-bottom: 1px solid var(--color-error);
        }

        .danger-title {
            font-size: 16px;
            font-weight: 900;
            color: var(--color-error);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .danger-content {
            padding: 30px;
        }

        .danger-text {
            color: var(--color-text-light);
            margin-bottom: 20px;
            font-size: 14px;
        }

        .btn-delete {
            background-color: var(--color-error);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-delete:hover,
        .btn-delete:focus {
            background-color: #dc2626;
            transform: translateY(-2px);
            outline: 2px solid var(--color-error);
            outline-offset: 2px;
        }

        /* NOTIFICATION */
        .notification {
            display: none;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification.show {
            display: block;
        }

        .notification.success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 4px solid var(--color-success);
        }

        .notification.error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--color-error);
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

            .profile-header {
                padding: 30px 20px;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }

            .profile-name {
                font-size: 24px;
            }

            .profile-stats {
                padding: 20px;
                gap: 15px;
            }

            .profile-info {
                padding: 20px;
            }

            .form-section {
                padding: 20px;
            }

            .edit-header {
                padding: 15px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .danger-zone {
                margin-bottom: 30px;
            }

            .danger-header {
                padding: 15px 20px;
            }

            .danger-content {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
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

            .profile-header {
                padding: 25px 15px;
            }

            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 32px;
                margin-bottom: 15px;
            }

            .profile-name {
                font-size: 20px;
            }

            .stat-value {
                font-size: 24px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
                padding: 15px;
                gap: 10px;
            }

            .form-group {
                width: 100%;
            }
        }
    </style>
<link href="../css/rider_header.css" rel="stylesheet"/>


    <script>
        window.LV_CONFIG = <?= js_json($lvConfig) ?>;
        window.LV_SESSION = <?= js_json($lvSession) ?>;
    </script>
    <script src="../js/lavandera_demo.js"></script>
    <script src="../js/lavandera_api.js"></script>
    <script src="../js/lavandera_links.js"></script></head>
<body>
<!-- HEADER -->
<header class="rider-site-header">
<a aria-label="Lavandera Home" class="logo" href="lavandera_rider_dashboard.php">
<img alt="Lavandera Logo" class="logo-img" src="../images/LAVANDERA.svg"/>
</a>
<div class="menu-container">
<button aria-controls="navDropdown" aria-expanded="false" aria-label="Toggle menu" class="menu-btn" onclick="toggleMenu()">
<i class="fa-solid fa-bars"></i>
</button>
<div aria-label="Main navigation" class="dropdown-menu" id="navDropdown" role="navigation">
<div class="dropdown-header">
<div aria-label="User avatar" class="user-avatar"><?= h(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
<div class="user-name"><?= h($user['name']) ?></div>
<div class="user-email"><?= h($user['email']) ?></div>
</div>
<a href="lavandera_rider_dashboard.php"><i class="fa-solid fa-house"></i>Dashboard</a>
<a href="Rider_Orders_Page.php"><i class="fa-solid fa-boxes-stacked"></i>Current Orders</a>
<a aria-current="page" href="Rider_Profile_Page.php"><i class="fa-solid fa-user"></i>Profile</a>
<a href="../auth/logout.php"><i class="fa-solid fa-sign-out-alt"></i>Logout</a>
</div>
</div>
</header>
<!-- PAGE WRAPPER -->
<div class="page-wrapper">
<!-- PAGE HEADER -->
<div class="page-header">
<h1 class="page-title">
<i aria-hidden="true" class="fa-solid fa-user-circle"></i>
                Account Settings
            </h1>
<p class="page-subtitle">Manage your profile and personal information</p>
</div>
<!-- NOTIFICATION -->
<div class="notification" id="notification">
<span id="notificationText"></span>
</div>
<!-- ===== PROFILE CARD (TOP) ===== -->
<div class="profile-card">
<!-- PROFILE HEADER -->
<div class="profile-header">
<div class="profile-avatar" id="profileAvatar">A</div>
<div class="profile-name" id="profileName">Alex Rodriguez</div>
<div class="profile-email" id="profileEmail">alex.rodriguez@email.com</div>
<div class="profile-status">
<span class="status-indicator"></span>
                    Verified Account
                </div>
</div>
<!-- PROFILE STATS -->
<div class="profile-stats">
<div class="stat-item">
<div class="stat-value" id="statCompletedOrders">187</div>
<div class="stat-label">Completed Orders</div>
</div>
<div class="stat-item">
<div class="stat-value" id="statTotalEarnings">₱18,540</div>
<div class="stat-label">Total Earnings</div>
</div>
</div>
<!-- PROFILE INFO -->
<div class="profile-info">
<!-- PERSONAL INFORMATION -->
<div class="info-section">
<div class="section-title">
<i class="fa-solid fa-id-card"></i>
                        Personal Information
                    </div>
<div class="info-grid">
<div class="info-item">
<div class="info-label">Full Name</div>
<div class="info-value" id="infoFullName">Alex Rodriguez</div>
</div>
<div class="info-item">
<div class="info-label">Email Address</div>
<div class="info-value" id="infoEmail">alex.rodriguez@email.com</div>
</div>
<div class="info-item">
<div class="info-label">Phone Number</div>
<div class="info-value" id="infoPhone">+63 917 123 4567</div>
</div>
</div>
</div>
<!-- ADDRESS INFORMATION -->
<!-- VEHICLE INFORMATION -->
<!-- BANK INFORMATION -->
</div>
</div>
<!-- ===== EDIT SECTION ===== -->
<div class="edit-section">
<div class="edit-header">
<div class="edit-title">
<i class="fa-solid fa-pen-to-square"></i>
                    Edit Profile Information
                </div>
<button class="edit-toggle" onclick="toggleEditForm()">
<i class="fa-solid fa-pencil"></i>
<span id="toggleText">Edit</span>
</button>
</div>
<!-- EDIT FORM -->
<div class="form-section" id="editForm">
<div class="form-grid">
<!-- PERSONAL INFO -->
<div class="form-group">
<label class="form-label" for="editFullName">Full Name</label>
<input class="form-input" id="editFullName" placeholder="Enter full name" type="text" value="Alex Rodriguez"/>
</div>
<div class="form-group">
<label class="form-label" for="editEmail">Email Address</label>
<input class="form-input" id="editEmail" placeholder="Enter email" readonly="readonly" type="email" value="alex.rodriguez@email.com"/>
</div>
<div class="form-group">
<label class="form-label" for="editPhone">Phone Number</label>
<input class="form-input" id="editPhone" placeholder="Enter phone number" type="tel" value="+63 917 123 4567"/>
</div>
<!-- ADDRESS INFO -->
<!-- VEHICLE INFO -->
<!-- BANK INFO -->
</div>
<!-- BIO/DESCRIPTION -->
<!-- FORM ACTIONS -->
<div class="form-actions">
<button class="btn-save" onclick="saveChanges()">
<i class="fa-solid fa-check"></i>
                        Save Changes
                    </button>
<button class="btn-cancel" onclick="cancelEdit()">
<i class="fa-solid fa-xmark"></i>
                        Cancel
                    </button>
</div>
</div>
</div>
<!-- ===== DANGER ZONE ===== -->
</div>
<!-- FOOTER -->
<footer>
<p>© 2026 LAVANDERA. Account Settings. All Rights Reserved.</p>
</footer>
<script>
        // Toggle menu
        function toggleMenu() {
            const menu = document.getElementById("navDropdown");
            const btn = document.querySelector('.menu-btn');
            menu.classList.toggle("show");
            btn.setAttribute('aria-expanded', menu.classList.contains('show'));
        }

        // Close menu when clicking elsewhere
        document.addEventListener('click', function (event) {
            const menuBtn = document.querySelector('.menu-btn');
            const menu = document.getElementById("navDropdown");

            if (!event.target.closest('.menu-container')) {
                menu.classList.remove('show');
                menuBtn.setAttribute('aria-expanded', 'false');
            }
        });

        // Toggle Edit Form
        function toggleEditForm() {
            const editForm = document.getElementById('editForm');
            const toggleText = document.getElementById('toggleText');
            const editToggle = document.querySelector('.edit-toggle');

            editForm.classList.toggle('active');

            if (editForm.classList.contains('active')) {
                toggleText.textContent = 'Cancel';
                editToggle.style.backgroundColor = 'var(--color-text-muted)';
            } else {
                toggleText.textContent = 'Edit';
                editToggle.style.backgroundColor = 'var(--color-primary)';
            }
        }

        // Cancel Edit
        function cancelEdit() {
            const editForm = document.getElementById('editForm');
            const toggleText = document.getElementById('toggleText');
            const editToggle = document.querySelector('.edit-toggle');

            editForm.classList.remove('active');
            toggleText.textContent = 'Edit';
            editToggle.style.backgroundColor = 'var(--color-primary)';
        }

        // Real profile data: loaded from and saved to the PHP API (only name + phone exist in the database).
        let rider = null;

        function fillProfile(stats) {
            document.getElementById('profileAvatar').textContent = rider.name.charAt(0).toUpperCase();
            document.getElementById('profileName').textContent = rider.name;
            document.getElementById('profileEmail').textContent = rider.email;
            document.getElementById('infoFullName').textContent = rider.name;
            document.getElementById('infoEmail').textContent = rider.email;
            document.getElementById('infoPhone').textContent = rider.contact;
            document.getElementById('editFullName').value = rider.name;
            document.getElementById('editEmail').value = rider.email;
            document.getElementById('editPhone').value = rider.contact;
            document.getElementById('statCompletedOrders').textContent = stats.completed;
            document.getElementById('statTotalEarnings').textContent = LV.peso(stats.earned);
        }

        async function loadProfile() {
            try {
                const data = await LVApi.get('../api/rider/profile.php');
                rider = data.rider;
                fillProfile(data.stats);
            } catch (error) {
                showNotification(error.message, 'error');
            }
        }

        async function saveChanges() {
            const fullName = document.getElementById('editFullName').value.trim();
            const phone = document.getElementById('editPhone').value.trim();
            if (!fullName || !phone) { showNotification('Name and phone number are required.', 'error'); return; }
            try {
                const data = await LVApi.postJson('../api/rider/profile.php', { name: fullName, contact: phone });
                rider = data.rider;
                fillProfile(data.stats);
                cancelEdit();
                showNotification('Profile updated successfully!', 'success');
            } catch (error) {
                showNotification(error.message, 'error');      // e.g. "Enter a valid contact number."
            }
        }

        // Show Notification
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            const notificationText = document.getElementById('notificationText');

            notificationText.textContent = message;
            notification.className = 'notification show ' + type;

            setTimeout(() => {
                notification.classList.remove('show');
            }, 4000);
        }

        window.addEventListener('load', loadProfile);
    </script>
</body>
</html>
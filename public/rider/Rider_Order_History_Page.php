<?php
/**
 * Rider Order History (converted to PHP): completed jobs from the database.
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
<title>LAVANDERA - Order History</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
<style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background-color: #fff7fb;
            color: #333333;
            min-height: 100vh;
        }

        /* HEADER */
        header {
            background-color: #e58db5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 5%;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
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
            margin-top: -10px;
            margin-bottom: -10px;
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

        /* PAGE WRAPPER */
        .page-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 5%;
            padding-top: 30px;
            padding-bottom: 40px;
        }


        /* BACK BUTTON */
        .back-btn {
            margin-bottom: 20px;
        }

        .back-btn a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: #5a003c;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.2s ease;
        }

        .back-btn a:hover {
            color: #e58db5;
        }

        /* PAGE HEADER */
        .page-header {
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 30px;
            color: #45002e;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title i {
            color: #e58db5;
        }

        .page-subtitle {
            font-size: 14px;
            color: #666;
            margin-top: 4px;
        }

        /* DELIVERIES PANEL */
        .deliveries-panel {
            background-color: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(90, 0, 60, 0.08);
        }

        .delivery-item {
            background-color: #f8f3f7;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 16px;
            border-left: 4px solid #e58db5;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .delivery-item:hover {
            background-color: #fff5fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(229, 141, 181, 0.2);
        }

        .delivery-item:last-child {
            margin-bottom: 0;
        }

        .delivery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .delivery-shop {
            font-weight: bold;
            color: #45002e;
            font-size: 16px;
        }

        .delivery-distance {
            background-color: #e58db5;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .delivery-address {
            font-size: 13px;
            color: #555;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .delivery-address i {
            color: #3bb2b8;
            flex-shrink: 0;
        }

        .delivery-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #777;
        }

        .delivery-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .delivery-meta i {
            color: #3bb2b8;
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 600px) {
            .page-title {
                font-size: 24px;
            }

            .deliveries-panel {
                padding: 16px;
            }

            .delivery-meta {
                flex-direction: column;
                gap: 6px;
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
<a class="logo" href="lavandera_rider_dashboard.php">
<img alt="Lavandera Logo" class="logo-img" src="../images/LAVANDERA.svg"/>
</a>
<div class="menu-container">
<button aria-controls="navDropdown" aria-expanded="false" aria-label="Toggle navigation menu" class="menu-btn" onclick="toggleMenu()" type="button">
<i class="fa-solid fa-bars"></i>
</button>
<div aria-label="Rider navigation" class="dropdown-menu" id="navDropdown" role="navigation">
<div class="dropdown-header">
<div class="user-avatar"><?= h(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
<div class="user-name"><?= h($user['name']) ?></div>
<div class="user-email"><?= h($user['email']) ?></div>
</div>
<a href="lavandera_rider_dashboard.php"><i class="fa-solid fa-house"></i>Dashboard</a>
<a href="Rider_Orders_Page.php"><i class="fa-solid fa-boxes-stacked"></i>Current Orders</a>
<a href="Rider_Profile_Page.php"><i class="fa-solid fa-user"></i>Profile</a>
<a href="../auth/logout.php"><i class="fa-solid fa-sign-out-alt"></i>Logout</a>
</div>
</div>
</header>
<div class="page-wrapper">
<!-- BACK BUTTON -->
<div class="back-btn">
<a href="Rider_Orders_Page.php">
<i class="fa-solid fa-arrow-left"></i> Back to Current Orders
            </a>
</div>
<!-- PAGE HEADER -->
<div class="page-header">
<h1 class="page-title">
<i class="fa-solid fa-clock-rotate-left"></i>
                Order History
            </h1>
<p class="page-subtitle">View past completed delivery requests</p>
</div>
<!-- DELIVERIES PANEL -->
<div class="deliveries-panel"><p id="histError" style="color:#b3261e;"></p><div id="historyList"></div></div>
</div>
<script>
        function toggleMenu() {
            const menu = document.getElementById('navDropdown');
            const button = document.querySelector('.menu-btn');
            const isOpen = menu.classList.toggle('show');
            button.setAttribute('aria-expanded', String(isOpen));
        }

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.menu-container')) {
                document.getElementById('navDropdown').classList.remove('show');
                document.querySelector('.menu-btn').setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.getElementById('navDropdown').classList.remove('show');
                document.querySelector('.menu-btn').setAttribute('aria-expanded', 'false');
            }
        });
    
        // One delivery card. Every value that a USER typed (shop, addresses, customer) goes through LV.esc() first.
        function jobCard(job, withLink) {
            const route = job.type === 'send' ? 'Pick up from the customer, deliver to the store' : 'Pick up at the store, deliver to the customer';
            return '<div class="delivery-item">' +
                '<div class="delivery-header"><div class="delivery-shop">' + LV.esc(job.shop) + '</div>' +
                    '<div class="delivery-distance">' + job.distanceKm.toFixed(1) + ' km</div></div>' +
                '<div class="delivery-address"><i class="fa-solid fa-location-dot"></i><span>' + LV.esc(job.pickup) + ' &rarr; ' + LV.esc(job.dropoff) + '</span></div>' +
                '<div class="delivery-meta"><span><i class="fa-solid fa-clock"></i> Completed ' + LV.when(job.completedAt) + '</span><span><i class="fa-solid fa-route"></i> ' + route + '</span>' +
                    '<span><i class="fa-solid fa-money-bill"></i> ' + LV.peso(job.pay) + '</span></div>' +
                (withLink ? '<div class="delivery-actions"><a class="btn-accept" href="Rider_Orders_Page.php" style="text-decoration:none; text-align:center;">Open order</a></div>' : '') +
            '</div>';
        }

        async function loadHistory() {
            try {
                const data = await LVApi.get('../api/rider/jobs.php');
                document.getElementById('historyList').innerHTML = data.history.length
                    ? data.history.map(function (job) { return jobCard(job, false); }).join('')
                    : '<div class="delivery-item"><div class="delivery-shop">No completed deliveries yet</div></div>';
            } catch (error) {
                document.getElementById('histError').textContent = error.message;
            }
        }
        loadHistory();
</script>
</body>
</html>
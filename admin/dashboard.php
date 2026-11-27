
<?php
session_start();
require_once "../database.php";

// Check admin login
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

// Total active cakes (exclude archived)
$cake_query = $conn->query("
    SELECT COUNT(*) AS total
    FROM cake_options
    WHERE archived_at IS NULL
");
$total_cakes = (int)$cake_query->fetch_assoc()["total"];

// Total customers
$customer_query = $conn->query("SELECT COUNT(*) AS total FROM customers");
$total_customers = (int)$customer_query->fetch_assoc()["total"];

// Total active orders (exclude archived)
$order_query = $conn->query("SELECT
    (SELECT COUNT(*) FROM orders WHERE archived_at IS NULL) +
    (SELECT COUNT(*) FROM custom_cake_requests WHERE archived_at IS NULL) AS total");
$total_orders = (int)$order_query->fetch_assoc()["total"];

// Pending
$pending_query = $conn->query("SELECT
    (SELECT COUNT(*) FROM orders WHERE order_status = 'Pending' AND archived_at IS NULL) +
    (SELECT COUNT(*) FROM custom_cake_requests WHERE request_status = 'Pending' AND archived_at IS NULL) AS total");
$total_pending = (int)$pending_query->fetch_assoc()["total"];

// Approved
$approved_query = $conn->query("SELECT
    (SELECT COUNT(*) FROM orders WHERE order_status = 'Approved' AND archived_at IS NULL) +
    (SELECT COUNT(*) FROM custom_cake_requests WHERE request_status = 'Approved' AND archived_at IS NULL) AS total");
$total_approved = (int)$approved_query->fetch_assoc()["total"];

// Ready for Pickup
$pickup_query = $conn->query("SELECT
    (SELECT COUNT(*) FROM orders WHERE order_status = 'Ready for Pickup' AND archived_at IS NULL) +
    (SELECT COUNT(*) FROM custom_cake_requests WHERE request_status = 'Ready for Pickup' AND archived_at IS NULL) AS total");
$total_ready_pickup = (int)$pickup_query->fetch_assoc()["total"];

// Out for Delivery
$delivery_query = $conn->query("SELECT
    (SELECT COUNT(*) FROM orders WHERE order_status = 'Out for Delivery' AND archived_at IS NULL) +
    (SELECT COUNT(*) FROM custom_cake_requests WHERE request_status = 'Out for Delivery' AND archived_at IS NULL) AS total");
$total_out_delivery = (int)$delivery_query->fetch_assoc()["total"];

// Completed
$completed_query = $conn->query("SELECT
    (SELECT COUNT(*) FROM orders WHERE order_status = 'Completed' AND archived_at IS NULL) +
    (SELECT COUNT(*) FROM custom_cake_requests WHERE request_status = 'Completed' AND archived_at IS NULL) AS total");
$total_completed = (int)$completed_query->fetch_assoc()["total"];

// Cancelled
$cancelled_query = $conn->query("SELECT
    (SELECT COUNT(*) FROM orders WHERE order_status = 'Cancelled' AND archived_at IS NULL) +
    (SELECT COUNT(*) FROM custom_cake_requests WHERE request_status = 'Cancelled' AND archived_at IS NULL) AS total");
$total_cancelled = (int)$cancelled_query->fetch_assoc()["total"];

// Pending payments
$payment_query = $conn->query("SELECT
    (SELECT COUNT(*) FROM orders WHERE payment_status = 'Pending Verification' AND archived_at IS NULL) +
    (SELECT COUNT(*) FROM custom_cake_requests WHERE payment_status = 'Pending Verification' AND archived_at IS NULL) AS total");
$total_pending_payments = (int)$payment_query->fetch_assoc()["total"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CakeCraft</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/brand-system.css">

    <style>
        :root {
            --bg: #fffaf7;
            --bg-soft: #f7eeea;
            --bg-tint: #f4e5df;
            --surface: #ffffff;
            --ink: #3b2724;
            --ink-soft: #6f5d57;
            --rose: #c47c78;
            --rose-deep: #a65a56;
            --rose-darker: #8c4845;
            --craft-color: #a65a56;
            --line: #efe0da;
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;
            --gutter: max(6%, calc((100vw - 1180px) / 2));
            --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
            --shadow-lift: 0 14px 34px rgba(73, 48, 45, 0.14);
            --ease: cubic-bezier(.2, .7, .2, 1);
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.6;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        :focus-visible {
            outline: 3px solid var(--rose);
            outline-offset: 3px;
            border-radius: 4px;
        }

        /* HEADER */
        header {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 14px var(--gutter);
            background: var(--bg);
            color: var(--ink);
            border-bottom: 1px solid var(--line);
            box-shadow: 0 2px 14px rgba(59, 39, 36, 0.05);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        header .brand { color: var(--ink); }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            line-height: 1;
        }

        .brand-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--bg-soft);
            border: 1px solid var(--line);
            color: var(--craft-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .brand-icon svg {
            width: 18px;
            height: 18px;
        }

        .brand h2 {
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .brand h2 span.brand-craft { color: var(--craft-color); }

        .brand .badge {
            padding: 3px 11px;
            border-radius: 999px;
            background: var(--bg-soft);
            border: 1px solid var(--line);
            color: var(--rose-deep);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 22px;
        }

        /* SETTINGS DROPDOWN */
        .settings-dropdown { position: relative; }

        .settings-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 10px 22px;
            border: 1.5px solid var(--rose);
            border-radius: 999px;
            color: var(--ink);
            background: transparent;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s var(--ease), border-color .2s var(--ease), color .2s var(--ease);
        }

        .settings-toggle:hover,
        .settings-toggle[aria-expanded="true"] {
            color: var(--rose-deep);
            border-color: var(--rose-deep);
            background: var(--bg-soft);
        }

        .settings-toggle svg {
            width: 17px;
            height: 17px;
            transition: transform .2s var(--ease);
        }

        .settings-toggle[aria-expanded="true"] svg {
            transform: rotate(180deg);
        }

        .settings-menu {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            width: 220px;
            padding: 8px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: var(--shadow-lift);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: opacity .2s var(--ease), visibility .2s var(--ease), transform .2s var(--ease);
            z-index: 100;
        }

        .settings-dropdown.open .settings-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        header .settings-menu a {
            display: flex;
            align-items: center;
            gap: 11px;
            width: 100%;
            padding: 12px;
            border-radius: 9px;
            color: var(--ink);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background .2s var(--ease), color .2s var(--ease);
        }

        header .settings-menu a:hover {
            color: var(--rose-deep);
            background: var(--bg-soft);
        }

        .settings-menu svg {
            width: 18px;
            height: 18px;
            color: var(--rose-deep);
            flex: none;
        }

        header .header-actions .logout-btn {
            padding: 10px 22px;
            border: 1.5px solid var(--rose-deep);
            border-radius: 999px;
            color: #fff;
            background: var(--rose-deep);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s var(--ease), border-color .2s var(--ease);
        }

        header .header-actions .logout-btn:hover {
            color: #fff;
            border-color: var(--rose-darker);
            background: var(--rose-darker);
        }

        header :focus-visible { outline-color: var(--rose-deep); }

        /* MAIN */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 48px var(--gutter) 64px;
        }

        .welcome { margin-bottom: 38px; }

        .welcome h1 {
            font-family: var(--font-display);
            font-size: clamp(1.9rem, 4vw, 2.6rem);
            font-weight: 600;
            line-height: 1.15;
            letter-spacing: -.015em;
            margin-bottom: 10px;
        }

        .welcome p {
            color: var(--ink-soft);
            font-size: 16px;
        }

        /* GENERAL STATISTICS */
        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
            margin-bottom: 46px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 20px;
            background: var(--surface);
            padding: 28px;
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: var(--shadow);
            transition: transform .25s var(--ease), box-shadow .25s var(--ease);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lift);
        }

        .stat-card .icon {
            flex: none;
            display: grid;
            place-items: center;
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: var(--bg-soft);
            color: var(--rose-deep);
        }

        .stat-card .icon svg {
            width: 27px;
            height: 27px;
        }

        .stat-card h3 {
            color: var(--ink-soft);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .stat-card p {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 600;
            line-height: 1.1;
        }

        /* ORDERS OVERVIEW */
        .orders-overview { margin-bottom: 52px; }

        .orders-heading { margin-bottom: 30px; }

        .orders-heading h2 {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .orders-heading p {
            color: var(--ink-soft);
            font-size: 14px;
        }

        .order-group { margin-bottom: 30px; }
        .order-group:last-child { margin-bottom: 0; }

        .group-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .group-heading h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--ink);
        }

        .group-line {
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        /* ORDER CARDS */
        .order-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .order-stats.two-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .order-card {
            display: flex;
            align-items: center;
            gap: 16px;
            background: var(--surface);
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: var(--shadow);
            transition: transform .25s var(--ease), box-shadow .25s var(--ease);
        }

        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lift);
        }

        .order-card .order-icon {
            flex: none;
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: var(--bg-soft);
            color: var(--rose-deep);
        }

        .order-card .order-icon svg {
            width: 24px;
            height: 24px;
        }

        .order-card h3 {
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-soft);
            margin-bottom: 5px;
        }

        .order-card p {
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 600;
            line-height: 1.1;
        }

        /* MANAGEMENT */
        .management { margin-top: 10px; }

        .section-title {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .management-intro {
            color: var(--ink-soft);
            font-size: 14px;
            margin-bottom: 28px;
        }

        .management-group {
            margin-bottom: 34px;
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: var(--shadow);
        }

        .management-group:last-child { margin-bottom: 0; }

        .management-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .management-heading h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--ink);
            white-space: nowrap;
        }

        .management-heading .heading-line {
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        .management-heading-icon {
            display: grid;
            place-items: center;
            width: 36px;
            height: 36px;
            flex: none;
            border-radius: 11px;
            background: var(--bg-soft);
            color: var(--rose-deep);
        }

        .management-heading-icon svg {
            width: 19px;
            height: 19px;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .menu-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            background: var(--bg);
            padding: 26px;
            border: 1px solid var(--line);
            border-radius: 15px;
            box-shadow: 0 3px 14px rgba(73, 48, 45, 0.045);
            min-height: 245px;
            transition: transform .3s var(--ease), box-shadow .3s var(--ease), border-color .3s var(--ease);
        }

        .menu-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lift);
            border-color: #dfc2bc;
        }

        .menu-icon {
            display: grid;
            place-items: center;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: var(--bg-soft);
            color: var(--rose-deep);
            margin-bottom: 18px;
        }

        .menu-icon svg {
            width: 26px;
            height: 26px;
        }

        .menu-card h3 {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .menu-card p {
            color: var(--ink-soft);
            font-size: 15px;
            line-height: 1.65;
            margin-bottom: 24px;
        }

        .menu-card a {
            margin-top: auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--rose-deep);
            color: #fff;
            padding: 12px 24px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background .2s var(--ease);
        }

        .menu-card a:hover { background: var(--rose-darker); }

        .menu-card a span {
            font-size: 17px;
            line-height: 1;
            transition: transform .2s var(--ease);
        }

        .menu-card a:hover span { transform: translateX(3px); }

        .payment-note {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-soft);
            color: var(--rose-darker);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .payment-note svg {
            width: 17px;
            height: 17px;
            flex: none;
        }

        /* RESPONSIVE */
        @media (max-width: 1000px) {
            .order-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .menu { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 760px) {
            .stats { grid-template-columns: 1fr; }

            .order-stats,
            .order-stats.two-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            header {
                padding: 14px 6%;
                flex-wrap: wrap;
            }

            .header-actions {
                margin-left: auto;
                gap: 14px;
            }

            .container { padding: 36px 6% 48px; }
            .management-group { padding: 18px; }
            .menu-card { min-height: 225px; }
        }

        @media (max-width: 520px) {
            .order-stats,
            .order-stats.two-cards,
            .menu {
                grid-template-columns: 1fr;
            }

            .stat-card,
            .order-card {
                padding: 22px;
            }

            .orders-heading {
                align-items: flex-start;
                flex-direction: column;
                gap: 4px;
            }

            .header-actions {
                width: 100%;
                justify-content: flex-start;
                gap: 10px;
            }

            .settings-toggle {
                padding: 9px 16px;
                font-size: 13px;
            }

            header .header-actions .logout-btn {
                padding: 9px 16px;
                font-size: 13px;
            }

            .settings-menu {
                right: auto;
                left: 0;
            }

            .group-heading h3,
            .management-heading h3 {
                font-size: 15px;
            }

            .management-group {
                padding: 15px;
                margin-bottom: 24px;
            }

            .management-heading { gap: 9px; }

            .management-heading-icon {
                width: 32px;
                height: 32px;
            }

            .menu-card {
                padding: 23px;
                min-height: auto;
            }
        }

        @media (max-width: 420px) {
            .brand .badge { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="brand">
        <div class="brand-mark">
            <span class="brand-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3v3" />
                    <path d="M12 6c-1.1 0-2 .7-2 1.6 0 .5.2.9.5 1.4" />
                    <path d="M5 12h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" />
                    <path d="M3 16c1.5 0 2.5-1 4-1s2.5 1 4 1 2.5-1 4-1 2.5 1 4 1 2-.5 2-1" />
                    <path d="M3 19h18" />
                </svg>
            </span>
            <h2>Cake<span class="brand-craft">Craft</span></h2>
        </div>
        <span class="badge">ADMIN</span>
    </div>

    <div class="header-actions">
        <div class="settings-dropdown" id="settingsDropdown">
            <button type="button" class="settings-toggle" id="settingsToggle"
                    aria-expanded="false" aria-haspopup="true" aria-controls="settingsMenu">
                Settings
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m6 9 6 6 6-6"/>
                </svg>
            </button>

            <div class="settings-menu" id="settingsMenu">
                <a href="contact.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <path d="m3 7 9 6 9-6"/>
                    </svg>
                    Contact Settings
                </a>

                <a href="account_settings.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M5 21a7 7 0 0 1 14 0"/>
                        <path d="m19 8 1.5 1.5L23 7"/>
                    </svg>
                    Account Settings
                </a>
            </div>
        </div>

        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<div class="container">

    <!-- WELCOME -->
    <div class="welcome">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION["admin_name"]); ?></h1>
        <p>Manage your CakeCraft website from your admin dashboard.</p>
    </div>

    <!-- GENERAL STATISTICS -->
    <div class="stats">

        <div class="stat-card">
            <div class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 20v-7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v7"/>
                    <path d="M4 16c1.5 1.2 3 1.2 4 0 1 1.2 3 1.2 4 0 1.2 1.2 3 0 4 0 .5.5 1 .8 2 .8"/>
                    <path d="M12 11V8"/>
                    <path d="M12 5.5c.9-.9.9-1.6 0-2.5-.9.9-.9 1.6 0 2.5z"/>
                </svg>
            </div>
            <div>
                <h3>Total cakes</h3>
                <p><?php echo number_format($total_cakes); ?></p>
            </div>
        </div>

        <div class="stat-card">
            <div class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="8" r="3.5"/>
                    <path d="M2.5 20a6.5 6.5 0 0 1 13 0"/>
                    <path d="M16 4.7a3.5 3.5 0 0 1 0 6.6M18 14.2a6.5 6.5 0 0 1 3.5 5.8"/>
                </svg>
            </div>
            <div>
                <h3>Total customers</h3>
                <p><?php echo number_format($total_customers); ?></p>
            </div>
        </div>

    </div>

    <!-- ORDERS OVERVIEW -->
    <div class="orders-overview">
        <div class="orders-heading">
            <h2>Orders Overview</h2>
            <p>Regular orders and customized cake requests</p>
        </div>

        <!-- ORDER SUMMARY -->
        <div class="order-group">
            <div class="group-heading">
                <h3>Order Summary</h3>
                <div class="group-line"></div>
            </div>

            <div class="order-stats">

                <div class="order-card">
                    <div class="order-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="4" width="14" height="17" rx="2"/>
                            <path d="M9 4.5h6v2H9z"/>
                            <path d="M9 12h6M9 16h4"/>
                        </svg>
                    </div>
                    <div>
                        <h3>Total Orders</h3>
                        <p><?php echo number_format($total_orders); ?></p>
                    </div>
                </div>

                <div class="order-card">
                    <div class="order-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 7v5l3 2"/>
                        </svg>
                    </div>
                    <div>
                        <h3>Pending</h3>
                        <p><?php echo number_format($total_pending); ?></p>
                    </div>
                </div>

                <div class="order-card">
                    <div class="order-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="m8 12 2.5 2.5L16 9"/>
                        </svg>
                    </div>
                    <div>
                        <h3>Approved</h3>
                        <p><?php echo number_format($total_approved); ?></p>
                    </div>
                </div>

            </div>
        </div>

        <!-- IN PROGRESS -->
        <div class="order-group">
            <div class="group-heading">
                <h3>In Progress</h3>
                <div class="group-line"></div>
            </div>

            <div class="order-stats two-cards">

                <div class="order-card">
                    <div class="order-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7h18l-2 13H5L3 7z"/>
                            <path d="M8 7V5a4 4 0 0 1 8 0v2"/>
                            <path d="M9 13h6"/>
                        </svg>
                    </div>
                    <div>
                        <h3>Ready for Pickup</h3>
                        <p><?php echo number_format($total_ready_pickup); ?></p>
                    </div>
                </div>

                <div class="order-card">
                    <div class="order-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 5h11v12H3z"/>
                            <path d="M14 9h4l3 4v4h-7z"/>
                            <circle cx="7.5" cy="18" r="2"/>
                            <circle cx="17.5" cy="18" r="2"/>
                        </svg>
                    </div>
                    <div>
                        <h3>Out for Delivery</h3>
                        <p><?php echo number_format($total_out_delivery); ?></p>
                    </div>
                </div>

            </div>
        </div>

        <!-- ORDER RESULTS -->
        <div class="order-group">
            <div class="group-heading">
                <h3>Order Results</h3>
                <div class="group-line"></div>
            </div>

            <div class="order-stats two-cards">

                <div class="order-card">
                    <div class="order-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12.5 9.5 17 19 7"/>
                            <path d="M21 12a9 9 0 1 1-9-9"/>
                        </svg>
                    </div>
                    <div>
                        <h3>Completed</h3>
                        <p><?php echo number_format($total_completed); ?></p>
                    </div>
                </div>

                <div class="order-card">
                    <div class="order-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="m9 9 6 6m0-6-6 6"/>
                        </svg>
                    </div>
                    <div>
                        <h3>Cancelled</h3>
                        <p><?php echo number_format($total_cancelled); ?></p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- MANAGEMENT -->
    <section class="management" aria-labelledby="managementTitle">
        <h2 class="section-title" id="managementTitle">Management</h2>
        <p class="management-intro">Access and manage the main areas of your CakeCraft website.</p>

        <!-- WEBSITE MANAGEMENT -->
        <div class="management-group">
            <div class="management-heading">
                <span class="management-heading-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="16" rx="2"/>
                        <path d="M3 9h18M8 4v5"/>
                    </svg>
                </span>
                <h3>Website Management</h3>
                <div class="heading-line"></div>
            </div>

            <div class="menu">

                <article class="menu-card">
                    <div class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 20v-7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v7"/>
                            <path d="M4 16c1.5 1.2 3 1.2 4 0 1 1.2 3 1.2 4 0 1.2 1.2 3 0 4 0 .5.5 1 .8 2 .8"/>
                            <path d="M12 11V8"/>
                            <path d="M12 5.5c.9-.9.9-1.6 0-2.5-.9.9-.9 1.6 0 2.5z"/>
                        </svg>
                    </div>
                    <h3>Manage Cakes</h3>
                    <p>Add new cakes, update cake information, and manage the cakes available on your website.</p>
                    <a href="manage_cakes.php">Manage cakes <span aria-hidden="true">&rarr;</span></a>
                </article>

                <article class="menu-card">
                    <div class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="8" r="3.5"/>
                            <path d="M2.5 20a6.5 6.5 0 0 1 13 0"/>
                            <path d="M16 4.7a3.5 3.5 0 0 1 0 6.6M18 14.2a6.5 6.5 0 0 1 3.5 5.8"/>
                        </svg>
                    </div>
                    <h3>View Customers</h3>
                    <p>View customer accounts and information of users registered on CakeCraft.</p>
                    <a href="customers.php">View customers <span aria-hidden="true">&rarr;</span></a>
                </article>

            </div>
        </div>

        <!-- ORDER MANAGEMENT -->
        <div class="management-group">
            <div class="management-heading">
                <span class="management-heading-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="3" width="14" height="18" rx="2"/>
                        <path d="M9 8h6M9 12h6M9 16h3"/>
                    </svg>
                </span>
                <h3>Order Management</h3>
                <div class="heading-line"></div>
            </div>

            <div class="menu">

                <article class="menu-card">
                    <div class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="4" width="14" height="17" rx="2"/>
                            <path d="M9 4.5h6v2H9z"/>
                            <path d="M9 12h6M9 16h4"/>
                        </svg>
                    </div>
                    <h3>Manage Orders</h3>
                    <p>View regular orders and customized cake requests. Manage order details, delivery information, and order status.</p>
                    <a href="orders.php">View orders <span aria-hidden="true">&rarr;</span></a>
                </article>

                <article class="menu-card">
                    <div class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7h18M5 7V4h14v3M5 7v13h14V7"/>
                            <path d="M9 11h6M9 15h6"/>
                            <path d="m9 18 2 2 4-4"/>
                        </svg>
                    </div>
                    <h3>Archived Orders</h3>
                    <p>View archived regular orders and custom cake requests, and restore them when needed.</p>
                    <a href="archive_orders.php">View archive <span aria-hidden="true">&rarr;</span></a>
                </article>

            </div>
        </div>

        <!-- PAYMENT MANAGEMENT -->
        <div class="management-group">
            <div class="management-heading">
                <span class="management-heading-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2.5" y="5" width="19" height="14" rx="2"/>
                        <path d="M2.5 10h19M7 15h3"/>
                    </svg>
                </span>
                <h3>Payment Management</h3>
                <div class="heading-line"></div>
            </div>

            <div class="menu">

                <article class="menu-card">
                    <div class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M7 7h4v4H7zM16 7h1M16 10h1M7 16h1M10 16h1M14 14h3v3h-3z"/>
                        </svg>
                    </div>
                    <h3>Verify Payments</h3>
                    <p>Review uploaded payment proofs for regular orders and custom cake requests, and confirm or reject payments.</p>

                    <div class="payment-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 8v4m0 4h.01"/>
                        </svg>
                        <span><?php echo number_format($total_pending_payments); ?> Pending Verification</span>
                    </div>

                    <a href="verify_payments.php">Verify payments <span aria-hidden="true">&rarr;</span></a>
                </article>

                <article class="menu-card">
                    <div class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2.5" y="5" width="19" height="14" rx="2"/>
                            <path d="M2.5 10h19M15 15h3"/>
                        </svg>
                    </div>
                    <h3>Payment Settings</h3>
                    <p>Update GCash account details, QR code, and payment instructions shown to customers during checkout.</p>
                    <a href="payment_settings.php">Payment settings <span aria-hidden="true">&rarr;</span></a>
                </article>

            </div>
        </div>
    </section>

</div>

<script>
    (function () {
        const dropdown = document.getElementById("settingsDropdown");
        const toggle = document.getElementById("settingsToggle");
        const menu = document.getElementById("settingsMenu");

        if (dropdown && toggle && menu) {
            toggle.addEventListener("click", function (event) {
                event.stopPropagation();

                const isOpen = dropdown.classList.toggle("open");

                toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
            });

            document.addEventListener("click", function (event) {
                if (!dropdown.contains(event.target)) {
                    dropdown.classList.remove("open");
                    toggle.setAttribute("aria-expanded", "false");
                }
            });

            document.addEventListener("keydown", function (event) {
                if (event.key === "Escape" && dropdown.classList.contains("open")) {
                    dropdown.classList.remove("open");
                    toggle.setAttribute("aria-expanded", "false");
                    toggle.focus();
                }
            });
        }
    })();
</script>

</body>
</html>

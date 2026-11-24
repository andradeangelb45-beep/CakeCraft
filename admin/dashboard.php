
<?php
session_start();
require_once "../database.php";

// Check admin login
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

// Total cakes
$cake_query = $conn->query("SELECT COUNT(*) AS total FROM cake_options");
$total_cakes = (int)$cake_query->fetch_assoc()["total"];

// Total customers
$customer_query = $conn->query("SELECT COUNT(*) AS total FROM customers");
$total_customers = (int)$customer_query->fetch_assoc()["total"];

// Total active orders (exclude archived)
$order_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders WHERE archived_at IS NULL) +
        (SELECT COUNT(*) FROM custom_cake_requests WHERE archived_at IS NULL) AS total
");
$total_orders = (int)$order_query->fetch_assoc()["total"];

// Pending
$pending_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders
         WHERE order_status = 'Pending' AND archived_at IS NULL) +
        (SELECT COUNT(*) FROM custom_cake_requests
         WHERE request_status = 'Pending' AND archived_at IS NULL) AS total
");
$total_pending = (int)$pending_query->fetch_assoc()["total"];

// Approved
$approved_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders
         WHERE order_status = 'Approved' AND archived_at IS NULL) +
        (SELECT COUNT(*) FROM custom_cake_requests
         WHERE request_status = 'Approved' AND archived_at IS NULL) AS total
");
$total_approved = (int)$approved_query->fetch_assoc()["total"];

// Ready for Pickup
$pickup_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders
         WHERE order_status = 'Ready for Pickup' AND archived_at IS NULL) +
        (SELECT COUNT(*) FROM custom_cake_requests
         WHERE request_status = 'Ready for Pickup' AND archived_at IS NULL) AS total
");
$total_ready_pickup = (int)$pickup_query->fetch_assoc()["total"];

// Out for Delivery
$delivery_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders
         WHERE order_status = 'Out for Delivery' AND archived_at IS NULL) +
        (SELECT COUNT(*) FROM custom_cake_requests
         WHERE request_status = 'Out for Delivery' AND archived_at IS NULL) AS total
");
$total_out_delivery = (int)$delivery_query->fetch_assoc()["total"];

// Completed
$completed_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders
         WHERE order_status = 'Completed' AND archived_at IS NULL) +
        (SELECT COUNT(*) FROM custom_cake_requests
         WHERE request_status = 'Completed' AND archived_at IS NULL) AS total
");
$total_completed = (int)$completed_query->fetch_assoc()["total"];

// Cancelled
$cancelled_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders
         WHERE order_status = 'Cancelled' AND archived_at IS NULL) +
        (SELECT COUNT(*) FROM custom_cake_requests
         WHERE request_status = 'Cancelled' AND archived_at IS NULL) AS total
");
$total_cancelled = (int)$cancelled_query->fetch_assoc()["total"];

// Pending payments
$payment_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders
         WHERE payment_status = 'Pending Verification' AND archived_at IS NULL) +
        (SELECT COUNT(*) FROM custom_cake_requests
         WHERE payment_status = 'Pending Verification' AND archived_at IS NULL) AS total
");
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
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #fffaf7;
            --bg-soft: #f7eeea;
            --surface: #ffffff;
            --ink: #3b2724;
            --ink-soft: #6f5d57;
            --rose: #c47c78;
            --rose-deep: #a65a56;
            --rose-darker: #8c4845;
            --line: #efe0da;
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;
            --gutter: max(6%, calc((100vw - 1180px) / 2));
            --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
            --shadow-lift: 0 14px 34px rgba(73, 48, 45, 0.14);
            --ease: cubic-bezier(.2, .7, .2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body);
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
            padding: 16px var(--gutter);
            background: var(--ink);
            color: #fff;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand h2 {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .brand h2 span {
            color: #e3a9a5;
        }

        .brand .badge {
            padding: 3px 11px;
            border-radius: 999px;
            background: rgba(255,255,255,.14);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .04em;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        header a {
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 9px 20px;
            border: 1.5px solid rgba(255,255,255,.45);
            border-radius: 999px;
            transition: background .2s var(--ease), border-color .2s var(--ease);
        }

        header a:hover {
            background: rgba(255,255,255,.12);
            border-color: #fff;
        }

        .contact-settings-btn {
            background: var(--rose-deep);
            border-color: var(--rose-deep);
        }

        .contact-settings-btn:hover {
            background: var(--rose-darker);
            border-color: var(--rose-darker);
        }

        .account-settings-btn {
            background: rgba(255,255,255,.12);
            border-color: rgba(255,255,255,.55);
        }

        .account-settings-btn:hover {
            background: rgba(255,255,255,.22);
            border-color: #fff;
        }

        header :focus-visible {
            outline-color: #fff;
        }

        /* MAIN */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 48px var(--gutter) 64px;
        }

        .welcome {
            margin-bottom: 38px;
        }

        .welcome h1 {
            font-family: var(--font-display);
            font-size: clamp(1.9rem,4vw,2.6rem);
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
            grid-template-columns: repeat(2,minmax(0,1fr));
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
        .orders-overview {
            margin-bottom: 52px;
        }

        .orders-heading {
            margin-bottom: 30px;
        }

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

        .order-group {
            margin-bottom: 30px;
        }

        .order-group:last-child {
            margin-bottom: 0;
        }

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
            grid-template-columns: repeat(3,minmax(0,1fr));
            gap: 18px;
        }

        .order-stats.two-cards {
            grid-template-columns: repeat(2,minmax(0,1fr));
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
        .management {
            margin-top: 10px;
        }

        .section-title {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 26px;
        }

        .management-group {
            margin-bottom: 32px;
        }

        .management-group:last-child {
            margin-bottom: 0;
        }

        .management-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .management-heading h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--ink);
        }

        .management-heading .heading-line {
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(2,minmax(0,1fr));
            gap: 22px;
        }

        .menu-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            background: var(--surface);
            padding: 30px;
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: var(--shadow);
            min-height: 230px;
            transition: transform .3s var(--ease), box-shadow .3s var(--ease);
        }

        .menu-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lift);
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
            display: inline-block;
            background: var(--rose-deep);
            color: #fff;
            padding: 12px 24px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background .2s var(--ease);
        }

        .menu-card a:hover {
            background: var(--rose-darker);
        }

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
            .order-stats {
                grid-template-columns: repeat(2,minmax(0,1fr));
            }

            .menu {
                grid-template-columns: repeat(2,minmax(0,1fr));
            }
        }

        @media (max-width: 760px) {
            .stats {
                grid-template-columns: 1fr;
            }

            .order-stats,
            .order-stats.two-cards {
                grid-template-columns: repeat(2,minmax(0,1fr));
            }

            header {
                padding: 14px 6%;
                flex-wrap: wrap;
            }

            .header-actions {
                margin-left: auto;
            }

            .container {
                padding: 36px 6% 48px;
            }

            .menu-card {
                min-height: 220px;
            }
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
            }

            .header-actions a {
                flex: 1;
                text-align: center;
                padding: 9px 12px;
                font-size: 13px;
            }

            .group-heading h3,
            .management-heading h3 {
                font-size: 15px;
            }

            .menu-card {
                padding: 25px;
                min-height: auto;
            }
        }

        @media (max-width: 420px) {
            .brand .badge {
                display: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="brand">
        <h2>Cake<span>Craft</span></h2>
        <span class="badge">ADMIN</span>
    </div>

    <div class="header-actions">
        <a href="contact.php" class="contact-settings-btn">Contact Settings</a>
        <a href="account_settings.php" class="account-settings-btn">Account Settings</a>
        <a href="logout.php">Logout</a>
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

        <!-- TOTAL CAKES -->
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

        <!-- TOTAL CUSTOMERS -->
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

                <!-- TOTAL ORDERS -->
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

                <!-- PENDING -->
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

                <!-- APPROVED -->
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

                <!-- READY FOR PICKUP -->
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

                <!-- OUT FOR DELIVERY -->
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

                <!-- COMPLETED -->
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

                <!-- CANCELLED -->
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
    <div class="management">

        <h2 class="section-title">Management</h2>

        <!-- WEBSITE MANAGEMENT -->
        <div class="management-group">

            <div class="management-heading">
                <h3>Website Management</h3>
                <div class="heading-line"></div>
            </div>

            <div class="menu">

                <!-- MANAGE CAKES -->
                <div class="menu-card">
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
                    <a href="manage_cakes.php">Manage cakes</a>
                </div>

                <!-- CUSTOMERS -->
                <div class="menu-card">
                    <div class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="8" r="3.5"/>
                            <path d="M2.5 20a6.5 6.5 0 0 1 13 0"/>
                            <path d="M16 4.7a3.5 3.5 0 0 1 0 6.6M18 14.2a6.5 6.5 0 0 1 3.5 5.8"/>
                        </svg>
                    </div>
                    <h3>View Customers</h3>
                    <p>View customer accounts and information of users registered on CakeCraft.</p>
                    <a href="customers.php">View customers</a>
                </div>

            </div>
        </div>

        <!-- ORDER AND PAYMENT MANAGEMENT -->
        <div class="management-group">

            <div class="management-heading">
                <h3>Order &amp; Payment Management</h3>
                <div class="heading-line"></div>
            </div>

            <div class="menu">

                <!-- MANAGE ORDERS -->
                <div class="menu-card">
                    <div class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="4" width="14" height="17" rx="2"/>
                            <path d="M9 4.5h6v2H9z"/>
                            <path d="M9 12h6M9 16h4"/>
                        </svg>
                    </div>
                    <h3>Manage Orders</h3>
                    <p>View regular orders and customized cake requests. Manage order details, delivery information, and order status.</p>
                    <a href="orders.php">View orders</a>
                </div>

                <!-- GCASH QR SETTINGS -->
                <div class="menu-card">
                    <div class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M7 7h4v4H7z"/>
                            <path d="M16 7h1M16 10h1M7 16h4v1H7z"/>
                            <path d="M16 15v2M19 15v2"/>
                        </svg>
                    </div>
                    <h3>GCash QR Settings</h3>
                    <p>Upload, preview, and replace the GCash QR code displayed on the customer payment page.</p>
                    <a href="payment_settings.php">Manage GCash QR</a>
                </div>

                <!-- PAYMENT VERIFICATION -->
                <div class="menu-card">
                    <div class="menu-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="M3 10h18"/>
                            <path d="M7 15h4"/>
                        </svg>
                    </div>
                    <h3>Payment Verification</h3>
                    <p>Review GCash payment receipts, verify customer payments, and manage payment verification status.</p>

                    <?php if ($total_pending_payments > 0): ?>
                        <div class="payment-note">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 8v4"/>
                                <path d="M12 16h.01"/>
                            </svg>
                            <?php echo number_format($total_pending_payments); ?> payment(s) awaiting verification
                        </div>
                    <?php endif; ?>

                    <a href="verify_payments.php">Verify payments</a>
                </div>

            </div>
        </div>

    </div>

</div>

</body>
</html>

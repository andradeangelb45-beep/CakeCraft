
<?php
session_start();
require_once "../database.php";

// Check if admin is logged in
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

// Count total cakes
$cake_query = $conn->query("SELECT COUNT(*) AS total FROM cake_options");
$total_cakes = $cake_query->fetch_assoc()["total"];

// Count total customers
$customer_query = $conn->query("SELECT COUNT(*) AS total FROM customers");
$total_customers = $customer_query->fetch_assoc()["total"];

// Count ALL orders (regular + customized)
$order_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders) +
        (SELECT COUNT(*) FROM custom_cake_requests) AS total
");
$total_orders = $order_query->fetch_assoc()["total"];

// Count Pending orders
$pending_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders
         WHERE order_status = 'Pending') +
        (SELECT COUNT(*) FROM custom_cake_requests
         WHERE request_status = 'Pending') AS total
");
$total_pending = $pending_query->fetch_assoc()["total"];

// Count Approved orders
$approved_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders
         WHERE order_status = 'Approved') +
        (SELECT COUNT(*) FROM custom_cake_requests
         WHERE request_status = 'Approved') AS total
");
$total_approved = $approved_query->fetch_assoc()["total"];

// Count Completed orders
$completed_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders
         WHERE order_status = 'Completed') +
        (SELECT COUNT(*) FROM custom_cake_requests
         WHERE request_status = 'Completed') AS total
");
$total_completed = $completed_query->fetch_assoc()["total"];

// Count Cancelled orders
$cancelled_query = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM orders
         WHERE order_status = 'Cancelled') +
        (SELECT COUNT(*) FROM custom_cake_requests
         WHERE request_status = 'Cancelled') AS total
");
$total_cancelled = $cancelled_query->fetch_assoc()["total"];
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

        /* =========================
           HEADER
        ========================= */

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

            background: rgba(255, 255, 255, 0.14);

            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
        }

        /* HEADER BUTTONS */

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
            border: 1.5px solid rgba(255, 255, 255, 0.45);
            border-radius: 999px;

            transition:
                background .2s var(--ease),
                border-color .2s var(--ease);
        }

        header a:hover {
            background: rgba(255, 255, 255, 0.12);
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

        header :focus-visible {
            outline-color: #fff;
        }

        /* =========================
           MAIN CONTENT
        ========================= */

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
            font-size: clamp(1.9rem, 4vw, 2.6rem);
            font-weight: 600;
            line-height: 1.15;
            letter-spacing: -0.015em;
            margin-bottom: 10px;
        }

        .welcome p {
            color: var(--ink-soft);
            font-size: 16px;
        }

        /* =========================
           GENERAL STATISTICS
        ========================= */

        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
            margin-bottom: 42px;
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

            transition:
                transform .25s var(--ease),
                box-shadow .25s var(--ease);
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

        /* =========================
           ORDERS OVERVIEW
        ========================= */

        .orders-overview {
            margin-bottom: 52px;
        }

        .orders-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .orders-heading h2 {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 600;
        }

        .orders-heading p {
            color: var(--ink-soft);
            font-size: 14px;
        }

        .order-stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 18px;
        }

        .order-card {
            background: var(--surface);
            padding: 22px;

            border: 1px solid var(--line);
            border-radius: 14px;

            box-shadow: var(--shadow);

            transition:
                transform .25s var(--ease),
                box-shadow .25s var(--ease);
        }

        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lift);
        }

        .order-card .order-icon {
            width: 44px;
            height: 44px;

            display: grid;
            place-items: center;

            border-radius: 12px;
            margin-bottom: 16px;

            background: var(--bg-soft);
            color: var(--rose-deep);
        }

        .order-card .order-icon svg {
            width: 23px;
            height: 23px;
        }

        .order-card h3 {
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-soft);
            margin-bottom: 7px;
        }

        .order-card p {
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 600;
            line-height: 1.1;
        }

        /* =========================
           MANAGEMENT SECTION
        ========================= */

        .section-title {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 22px;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
        }

        .menu-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;

            background: var(--surface);
            padding: 30px;

            border: 1px solid var(--line);
            border-radius: 14px;

            box-shadow: var(--shadow);

            transition:
                transform .3s var(--ease),
                box-shadow .3s var(--ease);
        }

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lift);
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
            margin-bottom: 26px;
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

        /* =========================
           RESPONSIVE DESIGN
        ========================= */

        @media (max-width: 1100px) {
            .order-stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .menu {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .stats {
                grid-template-columns: 1fr;
            }

            .order-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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
        }

        @media (max-width: 480px) {
            .order-stats,
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
                transition-duration: 0.01ms !important;
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

    <!-- HEADER BUTTONS -->
    <div class="header-actions">
        <a href="contact.php" class="contact-settings-btn">
            Contact Settings
        </a>

        <a href="logout.php">
            Logout
        </a>
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
                    <path d="M4 16c1.5 1.2 3 1.2 4 0 1 1.2 3 1.2 4 0 1.2 1.2 3 1.2 4 0 .5.5 1 .8 2 .8"/>
                    <path d="M12 11V8"/>
                    <path d="M12 5.5c.9-.9.9-1.6 0-2.5-.9.9-.9 1.6 0 2.5z"/>
                </svg>
            </div>

            <div>
                <h3>Total cakes</h3>
                <p><?php echo number_format((int)$total_cakes); ?></p>
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
                <p><?php echo number_format((int)$total_customers); ?></p>
            </div>
        </div>

    </div>

    <!-- ORDERS OVERVIEW -->
    <div class="orders-overview">

        <div class="orders-heading">
            <h2>Orders Overview</h2>
            <p>Regular orders and customized cake requests</p>
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

                <h3>Total Orders</h3>
                <p><?php echo number_format((int)$total_orders); ?></p>
            </div>

            <!-- PENDING -->
            <div class="order-card">
                <div class="order-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 7v5l3 2"/>
                    </svg>
                </div>

                <h3>Pending</h3>
                <p><?php echo number_format((int)$total_pending); ?></p>
            </div>

            <!-- APPROVED -->
            <div class="order-card">
                <div class="order-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="m8 12 2.5 2.5L16 9"/>
                    </svg>
                </div>

                <h3>Approved</h3>
                <p><?php echo number_format((int)$total_approved); ?></p>
            </div>

            <!-- COMPLETED -->
            <div class="order-card">
                <div class="order-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12.5 9.5 17 19 7"/>
                        <path d="M21 12a9 9 0 1 1-9-9"/>
                    </svg>
                </div>

                <h3>Completed</h3>
                <p><?php echo number_format((int)$total_completed); ?></p>
            </div>

            <!-- CANCELLED -->
            <div class="order-card">
                <div class="order-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="m9 9 6 6m0-6-6 6"/>
                    </svg>
                </div>

                <h3>Cancelled</h3>
                <p><?php echo number_format((int)$total_cancelled); ?></p>
            </div>

        </div>
    </div>

    <!-- MANAGEMENT -->
    <h2 class="section-title">Management</h2>

    <div class="menu">

        <!-- MANAGE CAKES -->
        <div class="menu-card">
            <h3>Manage cakes</h3>
            <p>Add, view, and manage the cakes available on your website.</p>
            <a href="manage_cakes.php">Manage cakes</a>
        </div>

        <!-- CUSTOMERS -->
        <div class="menu-card">
            <h3>View customers</h3>
            <p>View the customers who have registered on CakeCraft.</p>
            <a href="customers.php">View customers</a>
        </div>

        <!-- ORDERS -->
        <div class="menu-card">
            <h3>Manage orders</h3>
            <p>View customer cake orders and their order information.</p>
            <a href="orders.php">View orders</a>
        </div>

    </div>

</div>

</body>
</html>

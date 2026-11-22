
<?php
session_start();
require_once "../database.php";

// Check if customer is logged in
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

$customer_id = (int) $_SESSION["customer_id"];
$customer_name = $_SESSION["customer_name"] ?? "Customer";

// Get unread notification count
$notification_count = 0;

$stmt_notifications = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE customer_id = ? AND is_read = 0
");

if ($stmt_notifications) {
    $stmt_notifications->bind_param("i", $customer_id);
    $stmt_notifications->execute();

    $notification_result = $stmt_notifications->get_result();
    $notification_data = $notification_result->fetch_assoc();

    $notification_count = (int) ($notification_data["unread_count"] ?? 0);

    $stmt_notifications->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Dashboard | CakeCraft</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        /* =========================
           VARIABLES
        ========================= */

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
            --footer: #3b2724;

            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;

            --gutter: max(6%, calc((100vw - 1180px) / 2));

            --radius: 14px;

            --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
            --shadow-lift: 0 14px 34px rgba(73, 48, 45, 0.14);

            --ease: cubic-bezier(.2, .7, .2, 1);
        }

        /* =========================
           RESET
        ========================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.6;
            color: var(--ink);
            background: var(--bg);

            -webkit-font-smoothing: antialiased;

            min-height: 100vh;

            display: flex;
            flex-direction: column;
        }

        img {
            display: block;
            max-width: 100%;
        }

        :focus-visible {
            outline: 3px solid var(--rose);
            outline-offset: 3px;
            border-radius: 4px;
        }

        /* =========================
           NAVIGATION
        ========================= */

        .navbar {
            position: sticky;
            top: 0;
            z-index: 50;

            background: rgba(255, 250, 247, 0.88);

            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);

            border-bottom: 1px solid var(--line);

            padding: 16px var(--gutter);
        }

        .nav-container {
            max-width: 1400px;
            margin: auto;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 30px;
        }

        .logo {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 700;

            color: var(--ink);
            text-decoration: none;

            letter-spacing: -0.01em;

            white-space: nowrap;
        }

        .logo span {
            color: var(--rose);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-links a {
            position: relative;

            text-decoration: none;
            color: var(--ink);

            font-size: 15px;
            font-weight: 500;

            padding: 6px 0;

            transition: color .2s var(--ease);
        }

        .nav-links a::after {
            content: "";

            position: absolute;
            left: 0;
            bottom: 0;

            width: 100%;
            height: 2px;

            background: var(--rose);

            transform: scaleX(0);
            transform-origin: left;

            transition: transform .25s var(--ease);
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--rose-deep);
        }

        .nav-links a:hover::after,
        .nav-links a.active::after {
            transform: scaleX(1);
        }

        /* Logout Button */

        .nav-links a.logout {
            padding: 10px 22px;

            border: 1.5px solid var(--rose);
            border-radius: 999px;

            color: var(--ink);

            font-size: 14px;
            font-weight: 600;

            transition:
                background .2s var(--ease),
                color .2s var(--ease);
        }

        .nav-links a.logout::after {
            display: none;
        }

        .nav-links a.logout:hover {
            background: var(--bg-soft);
            color: var(--rose-deep);
        }

        /* Notification Bell */

        .notification-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            position: relative;

            width: 38px;
            height: 38px;

            border-radius: 50%;

            text-decoration: none;
            color: var(--ink);

            font-size: 21px;

            transition: background .2s ease, color .2s ease;
        }

        .nav-links a.notification-link::after {
            display: none;
        }

        .notification-link:hover {
            background: var(--bg-soft);
            color: var(--rose-deep);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;

            min-width: 18px;
            height: 18px;

            padding: 0 5px;

            display: flex;
            justify-content: center;
            align-items: center;

            background: #dc3545;
            color: #fff;

            border-radius: 999px;

            font-size: 10px;
            font-weight: 700;
            line-height: 1;

            border: 2px solid var(--bg);
        }

        /* =========================
           MAIN CONTENT
        ========================= */

        .main-container {
            width: 100%;
            max-width: 1250px;

            margin: 45px auto;

            padding: 0 25px;

            flex: 1;
        }

        /* =========================
           WELCOME BANNER
        ========================= */

        .welcome-section {
            background: linear-gradient(
                135deg,
                #965c50,
                #bd8374
            );

            border-radius: 18px;

            padding: 42px 45px;

            color: #fff;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 30px;

            margin-bottom: 45px;

            box-shadow: 0 8px 25px rgba(139, 81, 70, 0.12);
        }

        .welcome-text {
            max-width: 720px;
        }

        .welcome-text .subtitle {
            display: inline-flex;
            align-items: center;

            gap: 12px;

            font-size: 13px;
            text-transform: uppercase;

            letter-spacing: 2px;

            opacity: 0.85;

            margin-bottom: 15px;
        }

        .welcome-text .subtitle::before {
            content: "";

            width: 30px;
            height: 2px;

            background: rgba(255, 255, 255, 0.7);
        }

        .welcome-text h1 {
            font-family: var(--font-display);

            font-size: clamp(27px, 3.5vw, 36px);

            font-weight: 600;

            margin-bottom: 15px;

            line-height: 1.3;
        }

        .welcome-text p {
            font-size: 15px;

            line-height: 1.8;

            opacity: 0.92;

            max-width: 680px;
        }

        .welcome-icon {
            width: 110px;
            height: 110px;

            border-radius: 50%;

            background: rgba(255, 255, 255, 0.15);

            display: flex;
            justify-content: center;
            align-items: center;

            font-size: 53px;

            flex-shrink: 0;
        }

        /* =========================
           QUICK ACCESS HEADING
           ========================= */

        .section-heading {
            margin-bottom: 25px;
        }

        .section-heading h2 {
            font-family: var(--font-display);

            font-size: 28px;
            font-weight: 600;

            color: var(--ink);

            margin-bottom: 8px;
        }

        .section-heading p {
            color: var(--ink-soft);

            font-size: 15px;
        }

        /* =========================
           DASHBOARD CARDS
        ========================= */

        .dashboard-grid {
            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 24px;
        }

        .dashboard-card {
            background: var(--surface);

            border: 1px solid var(--line);

            border-radius: var(--radius);

            padding: 27px;

            display: flex;
            flex-direction: column;

            min-height: 270px;

            box-shadow: var(--shadow);

            transition:
                transform .3s var(--ease),
                box-shadow .3s var(--ease),
                border-color .3s var(--ease);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);

            box-shadow: var(--shadow-lift);

            border-color: #dfb9ad;
        }

        /* Card Icons */

        .card-icon {
            width: 55px;
            height: 55px;

            border-radius: 12px;

            display: flex;
            justify-content: center;
            align-items: center;

            font-size: 27px;

            margin-bottom: 22px;
        }

        .icon-cakes {
            background: #f8e7e3;
        }

        .icon-customize {
            background: #f4eadc;
        }

        .icon-orders {
            background: #e9e9f5;
        }

        /* Card Text */

        .dashboard-card h3 {
            font-family: var(--font-display);

            font-size: 21px;
            font-weight: 600;

            color: var(--ink);

            margin-bottom: 10px;
        }

        .dashboard-card p {
            color: var(--ink-soft);

            font-size: 15px;

            line-height: 1.7;

            margin-bottom: 24px;
        }

        /* Card Links */

        .card-link {
            margin-top: auto;

            display: inline-flex;
            align-items: center;

            gap: 8px;

            text-decoration: none;

            color: var(--rose-deep);

            font-size: 14px;
            font-weight: 600;

            transition: color .2s var(--ease);
        }

        .card-link:hover {
            color: var(--rose-darker);
        }

        .card-link span {
            display: inline-block;

            transition: transform .2s var(--ease);
        }

        .card-link:hover span {
            transform: translateX(4px);
        }

        /* =========================
           HELP / CONTACT SECTION
        ========================= */

        .bottom-section {
            margin-top: 35px;

            background: var(--surface);

            border: 1px solid var(--line);

            border-radius: var(--radius);

            padding: 25px 28px;

            display: flex;
            align-items: center;

            gap: 18px;

            box-shadow: var(--shadow);
        }

        .bottom-icon {
            width: 52px;
            height: 52px;

            background: #f8e7e3;

            border-radius: 50%;

            display: flex;
            justify-content: center;
            align-items: center;

            font-size: 24px;

            flex-shrink: 0;
        }

        .bottom-text h3 {
            font-family: var(--font-display);

            font-size: 19px;
            font-weight: 600;

            margin-bottom: 7px;
        }

        .bottom-text p {
            font-size: 14px;

            line-height: 1.7;

            color: var(--ink-soft);
        }

        .bottom-text a {
            color: var(--rose-deep);

            text-decoration: none;

            font-weight: 600;

            transition: color .2s;
        }

        .bottom-text a:hover {
            color: var(--rose-darker);
            text-decoration: underline;
        }

        /* =========================
           FOOTER
        ========================= */

        footer {
            margin-top: 40px;

            padding: 28px 20px 35px;

            text-align: center;

            color: var(--ink-soft);

            font-size: 14px;

            border-top: 1px solid var(--line);

            background: var(--bg);
        }

        /* =========================
           RESPONSIVE - TABLET
        ========================= */

        @media (max-width: 1000px) {

            .nav-container {
                gap: 20px;
            }

            .nav-links {
                gap: 20px;
            }

            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .welcome-text h1 {
                font-size: 29px;
            }
        }

        /* =========================
           RESPONSIVE - MOBILE
        ========================= */

        @media (max-width: 700px) {

            .navbar {
                padding: 14px 6%;
            }

            .nav-container {
                flex-direction: column;

                gap: 15px;
            }

            .nav-links {
                width: 100%;

                justify-content: center;

                flex-wrap: wrap;

                gap: 12px 20px;
            }

            .nav-links a {
                font-size: 13px;
            }

            .nav-links a.logout {
                padding: 8px 16px;
            }

            .main-container {
                margin: 30px auto;

                padding: 0 6%;
            }

            .welcome-section {
                padding: 30px 25px;

                border-radius: 15px;

                margin-bottom: 35px;
            }

            .welcome-text h1 {
                font-size: 25px;
            }

            .welcome-text p {
                font-size: 14px;
            }

            .welcome-icon {
                display: none;
            }

            .section-heading h2 {
                font-size: 24px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;

                gap: 18px;
            }

            .dashboard-card {
                min-height: 240px;

                padding: 25px;
            }

            .dashboard-card h3 {
                font-size: 20px;
            }

            .bottom-section {
                padding: 20px;

                align-items: flex-start;
            }

            .bottom-icon {
                width: 45px;
                height: 45px;

                font-size: 21px;
            }

            .bottom-text h3 {
                font-size: 17px;
            }
        }

        /* =========================
           SMALL MOBILE
        ========================= */

        @media (max-width: 420px) {

            .nav-links {
                gap: 8px;
            }

            .nav-links a {
                padding: 5px 0;
                font-size: 12px;
            }

            .nav-links a.logout {
                padding: 8px 14px;
            }

            .welcome-section {
                padding: 25px 20px;
            }

            .welcome-text h1 {
                font-size: 23px;
            }

            .dashboard-card {
                padding: 22px;
            }
        }

        /* =========================
           REDUCED MOTION
        ========================= */

        @media (prefers-reduced-motion: reduce) {

            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

<!-- =========================
     NAVBAR
========================= -->

<header class="navbar">

    <div class="nav-container">

        <a href="dashboard.php" class="logo">
            Cake<span>Craft</span>
        </a>

        <nav class="nav-links">

            <a href="dashboard.php" class="active" aria-current="page">
                Dashboard
            </a>

            <a href="cakes.php">
                Cakes
            </a>

            <a href="my_orders.php">
                My Orders
            </a>

            <a href="profile.php">
                My Profile
            </a>

            <!-- Notification Bell -->
            <a href="notifications.php"
               class="notification-link"
               aria-label="Notifications<?php echo $notification_count > 0 ? ', ' . $notification_count . ' unread' : ''; ?>"
               title="Notifications">

                🔔

                <?php if ($notification_count > 0): ?>

                    <span class="notification-badge">
                        <?php echo $notification_count > 99 ? "99+" : $notification_count; ?>
                    </span>

                <?php endif; ?>

            </a>

            <a href="logout.php" class="logout">
                Logout
            </a>

        </nav>

    </div>

</header>

<!-- =========================
     MAIN CONTENT
========================= -->

<main class="main-container">

    <!-- WELCOME BANNER -->

    <section class="welcome-section">

        <div class="welcome-text">

            <div class="subtitle">
                Customer Dashboard
            </div>

            <h1>
                Welcome back,
                <?php echo htmlspecialchars($customer_name); ?>!
            </h1>

            <p>
                We're happy to have you here. Explore our delicious cakes,
                create your own customized design, and keep track of your orders
                all in one place.
            </p>

        </div>

        <div class="welcome-icon">
            🎂
        </div>

    </section>

    <!-- QUICK ACCESS -->

    <section>

        <div class="section-heading">

            <h2>Quick Access</h2>

            <p>
                Choose what you would like to do today.
            </p>

        </div>

        <div class="dashboard-grid">

            <!-- BROWSE CAKES -->

            <div class="dashboard-card">

                <div class="card-icon icon-cakes">
                    🎂
                </div>

                <h3>Browse Cakes</h3>

                <p>
                    Explore our available cakes, discover different flavors,
                    and find the perfect cake for your special occasion.
                </p>

                <a href="cakes.php" class="card-link">
                    Explore Cakes <span>→</span>
                </a>

            </div>

            <!-- CUSTOMIZE CAKE -->

            <div class="dashboard-card">

                <div class="card-icon icon-customize">
                    🎨
                </div>

                <h3>Customize Your Cake</h3>

                <p>
                    Create your own cake request by choosing your preferred
                    size, flavor, design, and other details.
                </p>

                <a href="customize_request.php" class="card-link">
                    Create Custom Cake <span>→</span>
                </a>

            </div>

            <!-- MY ORDERS -->

            <div class="dashboard-card">

                <div class="card-icon icon-orders">
                    📦
                </div>

                <h3>My Orders</h3>

                <p>
                    View your orders, check their current status, and review
                    the details of your cake purchases and requests.
                </p>

                <a href="my_orders.php" class="card-link">
                    View My Orders <span>→</span>
                </a>

            </div>

        </div>

    </section>

    <!-- HELP SECTION -->

    <section class="bottom-section">

        <div class="bottom-icon">
            💌
        </div>

        <div class="bottom-text">

            <h3>
                Need assistance with your order?
            </h3>

            <p>
                If you have questions or need help with your cake order,
                you may contact us through the contact details available
                on the CakeCraft homepage.

                <a href="../index.php">
                    Visit Homepage
                </a>
            </p>

        </div>

    </section>

</main>

<!-- FOOTER -->

<footer>

    &copy; <?php echo date("Y"); ?> CakeCraft.
    All rights reserved.

</footer>

</body>
</html>

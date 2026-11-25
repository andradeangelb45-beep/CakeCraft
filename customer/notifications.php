<?php
session_start();
require_once "../database.php";

// Check customer login
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

$customer_id = (int) $_SESSION["customer_id"];

// Mark notification as read when opened
if (isset($_GET["read"])) {
    $notification_id = (int) $_GET["read"];

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ?
          AND customer_id = ?
    ");
    $stmt->bind_param("ii", $notification_id, $customer_id);
    $stmt->execute();
    $stmt->close();

    // Get notification link
    $stmt = $conn->prepare("
        SELECT link
        FROM notifications
        WHERE notification_id = ?
          AND customer_id = ?
    ");
    $stmt->bind_param("ii", $notification_id, $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notification = $result->fetch_assoc();
    $stmt->close();

    $redirect_link = "my_orders.php";

    if (!empty($notification["link"])) {
        $stored_link = trim($notification["link"]);

        // Allow safe internal PHP links only
        if (!preg_match('/^[a-zA-Z0-9_-]+\.php(?:\?[a-zA-Z0-9_&=-]*)?$/', $stored_link)) {
            $stored_link = "my_orders.php";
        }

        $redirect_link = $stored_link;
    }

    header("Location: " . $redirect_link);
    exit();
}

// Mark all notifications as read
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_all_read"])) {
    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE customer_id = ?
          AND is_read = 0
    ");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $stmt->close();

    header("Location: notifications.php");
    exit();
}

// Get unread count
$stmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE customer_id = ?
      AND is_read = 0
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$unread_result = $stmt->get_result();
$unread_count = (int) ($unread_result->fetch_assoc()["unread_count"] ?? 0);
$stmt->close();

// Get customer notifications
$stmt = $conn->prepare("
    SELECT *
    FROM notifications
    WHERE customer_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | CakeCraft</title>

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
            --line-strong: #dfc9c1;
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;
            --gutter: max(6%, calc((100vw - 1180px) / 2));
            --radius: 14px;
            --radius-pill: 999px;
            --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
            --shadow-hover: 0 14px 30px rgba(73, 48, 45, 0.10);
            --ease: cubic-bezier(.2, .7, .2, 1);
        }

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.6;
            color: var(--ink);
            background: var(--bg);
            -webkit-font-smoothing: antialiased;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid var(--rose);
            outline-offset: 3px;
            border-radius: 4px;
        }

        /* NAVIGATION */
        header {
            position: sticky;
            top: 0;
            z-index: 50;
            width: 100%;
            background: rgba(255, 250, 247, 0.96);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
            box-shadow: 0 2px 14px rgba(59, 39, 36, 0.05);
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            width: 100%;
            padding: 14px var(--gutter);
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--ink);
            line-height: 1;
            white-space: nowrap;
            flex: none;
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

        .logo {
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .logo span.brand-craft {
            color: var(--craft-color);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
            list-style: none;
        }

        .nav-links a {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 0;
            color: var(--ink);
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
            transition: color .2s var(--ease), background .2s var(--ease), border-color .2s var(--ease);
        }

        .nav-links a:not(.logout)::after {
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

        .nav-links a.logout {
            padding: 9px 20px;
            border: 1.5px solid var(--rose);
            border-radius: var(--radius-pill);
            font-size: 14px;
            font-weight: 600;
        }

        .nav-links a.logout::after {
            display: none;
        }

        .nav-links a.logout:hover {
            color: var(--rose-deep);
            border-color: var(--rose-deep);
            background: var(--bg-soft);
        }

        /* PAGE HEADER */
        .container {
            flex: 1;
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            padding: 48px 24px 72px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: var(--ink);
            font-family: var(--font-display);
            font-size: clamp(2rem, 4.4vw, 2.6rem);
            font-weight: 700;
            line-height: 1.2;
        }

        .page-header p {
            margin-top: 7px;
            color: var(--ink-soft);
            font-size: 16px;
        }

        .mark-all-btn {
            flex: none;
            border: 1.5px solid var(--rose-deep);
            border-radius: var(--radius-pill);
            background: var(--rose-deep);
            color: #ffffff;
            padding: 11px 20px;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s var(--ease), border-color .2s var(--ease), transform .2s var(--ease), box-shadow .2s var(--ease);
        }

        .mark-all-btn:hover {
            background: var(--rose-darker);
            border-color: var(--rose-darker);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        /* NOTIFICATION CARDS */
        .notification-card {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 16px;
            padding: 22px 24px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--surface);
            color: inherit;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(73, 48, 45, 0.035);
            transition: transform .25s var(--ease), box-shadow .25s var(--ease), border-color .25s var(--ease), background .25s var(--ease);
        }

        .notification-card:hover {
            transform: translateY(-3px);
            border-color: var(--line-strong);
            box-shadow: var(--shadow-hover);
        }

        .notification-card.unread {
            border-left: 4px solid var(--rose-deep);
            background: var(--bg-soft);
        }

        .notification-icon {
            display: flex;
            flex: none;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--bg-soft);
            font-size: 22px;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-content h3 {
            margin-bottom: 6px;
            color: var(--ink);
            font-family: var(--font-body);
            font-size: 16px;
            font-weight: 700;
            line-height: 1.45;
        }

        .notification-content p {
            color: var(--ink-soft);
            font-size: 14px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .notification-date {
            margin-top: 10px;
            color: var(--ink-soft);
            opacity: 0.85;
            font-size: 12px;
            font-weight: 400;
        }

        .unread-dot {
            flex: none;
            width: 10px;
            height: 10px;
            margin-top: 5px;
            border-radius: 50%;
            background: var(--rose-deep);
        }

        /* EMPTY STATE */
        .empty-state {
            padding: 60px 25px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--surface);
            text-align: center;
            box-shadow: var(--shadow);
        }

        .empty-state .icon {
            margin-bottom: 15px;
            font-size: 52px;
        }

        .empty-state h2 {
            margin-bottom: 10px;
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 700;
        }

        .empty-state p {
            color: var(--ink-soft);
            font-size: 15px;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .navbar {
                gap: 16px;
            }

            .nav-links {
                gap: 18px;
            }
        }

        @media (max-width: 700px) {
            .navbar {
                flex-direction: column;
                justify-content: center;
                gap: 12px;
                padding: 14px 6%;
            }

            .logo {
                font-size: 26px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 8px 18px;
            }

            .nav-links a {
                font-size: 14px;
            }

            .container {
                width: 94%;
                padding: 34px 0 56px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
                gap: 16px;
                margin-bottom: 24px;
            }

            .page-header h1 {
                font-size: 30px;
            }

            .page-header p {
                font-size: 15px;
            }

            .mark-all-btn {
                width: 100%;
            }

            .notification-card {
                gap: 13px;
                padding: 18px 16px;
            }
        }

        @media (max-width: 420px) {
            .nav-links {
                column-gap: 13px;
            }

            .notification-icon {
                width: 42px;
                height: 42px;
                font-size: 20px;
            }

            .notification-content h3 {
                font-size: 15px;
            }

            .notification-content p {
                font-size: 13px;
            }
        }

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
    <!-- NAVIGATION -->
    <header>
        <nav class="navbar" aria-label="Main navigation">
            <a href="dashboard.php" class="brand-mark" aria-label="CakeCraft Home">
                <span class="brand-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3v3" />
                        <path d="M12 6c-1.1 0-2 .7-2 1.6 0 .5.2.9.5 1.4" />
                        <path d="M5 12h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" />
                        <path d="M3 16c1.5 0 2.5-1 4-1s2.5 1 4 1 2.5-1 4-1 2.5 1 4 1 2-.5 2-1" />
                        <path d="M3 19h18" />
                    </svg>
                </span>
                <span class="logo">Cake<span class="brand-craft">Craft</span></span>
            </a>

            <div class="nav-links">
                <a href="dashboard.php">Home</a>
                <a href="cakes.php">Cakes</a>
                <a href="my_orders.php">My Orders</a>
                <a href="notifications.php" class="active" aria-current="page">🔔 Notifications</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php" class="logout">Logout</a>
            </div>
        </nav>
    </header>

    <!-- MAIN CONTENT -->
    <main class="container">
        <div class="page-header">
            <div>
                <h1>Notifications 🔔</h1>
                <p>
                    <?php if ($unread_count > 0): ?>
                        You have <?= $unread_count ?> unread notification(s).
                    <?php else: ?>
                        You're all caught up!
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($unread_count > 0): ?>
                <form method="POST">
                    <button type="submit" name="mark_all_read" class="mark-all-btn">
                        Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- NOTIFICATIONS LIST -->
        <?php if (!empty($notifications)): ?>
            <?php foreach ($notifications as $notification): ?>
                <?php
                    $is_unread = (int) $notification["is_read"] === 0;
                    $notification_link = "notifications.php?read=" . (int) $notification["notification_id"];

                    $title = htmlspecialchars($notification["title"], ENT_QUOTES, "UTF-8");
                    $message = htmlspecialchars($notification["message"], ENT_QUOTES, "UTF-8");
                    $date = date("M d, Y - h:i A", strtotime($notification["created_at"]));
                ?>

                <a href="<?= htmlspecialchars($notification_link, ENT_QUOTES, "UTF-8") ?>"
                   class="notification-card <?= $is_unread ? 'unread' : '' ?>">
                    <div class="notification-icon" aria-hidden="true">📦</div>

                    <div class="notification-content">
                        <h3><?= $title ?></h3>
                        <p><?= nl2br($message) ?></p>
                        <div class="notification-date"><?= htmlspecialchars($date, ENT_QUOTES, "UTF-8") ?></div>
                    </div>

                    <?php if ($is_unread): ?>
                        <div class="unread-dot" aria-label="Unread notification"></div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon" aria-hidden="true">🔔</div>
                <h2>No Notifications Yet</h2>
                <p>You'll see updates about your cake orders here.</p>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
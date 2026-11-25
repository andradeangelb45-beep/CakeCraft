<?php
session_start();
require_once "../database.php";

// Check if admin is logged in
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

// Get all registered customers
$sql = "SELECT id, full_name, email, phone, created_at
        FROM customers
        ORDER BY created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Database error: " . htmlspecialchars($conn->error));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customers | CakeCraft Admin</title>

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
            --line-strong: #e0cbc3;
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;
            --gutter: max(6%, calc((100vw - 1180px) / 2));
            --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
            --shadow-hover: 0 12px 28px rgba(73, 48, 45, 0.12);
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
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.6;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        :focus-visible {
            outline: 3px solid var(--rose);
            outline-offset: 3px;
            border-radius: 4px;
        }

        /* HEADER / BRAND */
        header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255, 250, 247, .96);
            border-bottom: 1px solid var(--line);
            box-shadow: 0 3px 16px rgba(59, 39, 36, .045);
            padding: 14px var(--gutter);
            backdrop-filter: blur(12px);
        }

        .header-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
        }

        .brand-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--ink);
            text-decoration: none;
            white-space: nowrap;
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

        .brand-name {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -.01em;
            line-height: 1;
        }

        .brand-name span.brand-craft {
            color: var(--craft-color);
        }

        .admin-badge {
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--bg-soft);
            border: 1px solid var(--line);
            color: var(--rose-deep);
            font-family: var(--font-body);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-actions a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 19px;
            border: 1.5px solid var(--rose);
            border-radius: 999px;
            color: var(--ink);
            background: transparent;
            text-decoration: none;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            transition: color .2s var(--ease), background .2s var(--ease), border-color .2s var(--ease), transform .2s var(--ease);
        }

        .header-actions a:hover {
            background: var(--bg-soft);
            border-color: var(--rose-deep);
            color: var(--rose-deep);
        }

        .header-actions a.logout {
            color: #fff;
            background: var(--rose-deep);
            border-color: var(--rose-deep);
        }

        .header-actions a.logout:hover {
            background: var(--rose-darker);
            border-color: var(--rose-darker);
        }

        /* PAGE CONTENT */
        .container {
            max-width: 1180px;
            margin: 0 auto;
            padding: 44px var(--gutter) 64px;
        }

        .back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 24px;
            color: var(--rose-deep);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color .2s ease;
        }

        .back:hover {
            color: var(--rose-darker);
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        h1 {
            font-family: var(--font-display);
            font-size: clamp(2rem, 4vw, 2.6rem);
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -.01em;
            margin-bottom: 8px;
            color: var(--ink);
        }

        .subtitle {
            color: var(--ink-soft);
            font-size: 16px;
            margin-bottom: 28px;
        }

        /* CUSTOMER TABLE */
        .table-container {
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: var(--shadow);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th,
        td {
            padding: 17px 20px;
            text-align: left;
            border-bottom: 1px solid var(--line);
            font-family: var(--font-body);
            font-size: 14px;
        }

        th {
            background: var(--bg-soft);
            color: var(--ink);
            font-weight: 700;
            white-space: nowrap;
        }

        td {
            color: var(--ink-soft);
            font-weight: 400;
        }

        td:nth-child(2) {
            color: var(--ink);
            font-weight: 600;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tbody tr {
            transition: background-color .15s ease;
        }

        tbody tr:hover {
            background: var(--bg);
        }

        .empty {
            text-align: center;
            padding: 48px 20px;
            color: var(--ink-soft);
        }

        .empty h3 {
            font-family: var(--font-display);
            font-size: 23px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 7px;
        }

        .empty p {
            font-size: 14px;
        }

        @media (max-width: 700px) {
            header {
                padding: 14px 5%;
            }

            .header-inner {
                flex-wrap: wrap;
                justify-content: center;
                gap: 14px;
            }

            .header-actions {
                width: 100%;
                justify-content: center;
            }

            .container {
                padding: 32px 5% 48px;
            }

            h1 {
                font-size: 29px;
            }

            .subtitle {
                font-size: 15px;
            }
        }

        @media (max-width: 420px) {
            .brand-name {
                font-size: 25px;
            }

            .admin-badge {
                font-size: 10px;
                padding: 3px 8px;
            }

            .header-actions a {
                padding: 9px 15px;
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
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="header-inner">
        <div class="brand-wrap">
            <a href="dashboard.php" class="brand" aria-label="CakeCraft Admin Dashboard">
                <span class="brand-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3v3" />
                        <path d="M12 6c-1.1 0-2 .7-2 1.6 0 .5.2.9.5 1.4" />
                        <path d="M5 12h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" />
                        <path d="M3 16c1.5 0 2.5-1 4-1s2.5 1 4 1 2.5-1 4-1 2.5 1 4 1 2-.5 2-1" />
                        <path d="M3 19h18" />
                    </svg>
                </span>
                <span class="brand-name">Cake<span class="brand-craft">Craft</span></span>
            </a>
            <span class="admin-badge">ADMIN</span>
        </div>

        <div class="header-actions">
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </div>
</header>

<div class="container">

    <a href="dashboard.php" class="back">
        <span aria-hidden="true">←</span> Back to Dashboard
    </a>

    <h1>Registered Customers</h1>

    <p class="subtitle">
        View the customers who have registered on CakeCraft.
    </p>

    <div class="table-container">

        <?php if ($result->num_rows > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Contact Number</th>
                        <th>Registration Date</th>
                    </tr>
                </thead>

                <tbody>

                    <?php while ($customer = $result->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo (int)$customer["id"]; ?></td>
                            <td><?php echo htmlspecialchars($customer["full_name"]); ?></td>
                            <td><?php echo htmlspecialchars($customer["email"]); ?></td>
                            <td><?php echo htmlspecialchars($customer["phone"]); ?></td>
                            <td><?php echo htmlspecialchars($customer["created_at"]); ?></td>
                        </tr>

                    <?php endwhile; ?>

                </tbody>
            </table>

        <?php else: ?>

            <div class="empty">
                <h3>No Registered Customers Yet</h3>
                <p>Customer accounts will appear here after registration.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
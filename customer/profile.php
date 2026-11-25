<?php
session_start();
require_once "../database.php";

// Check if customer is logged in
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

$customer_id = (int) $_SESSION["customer_id"];

$message = "";
$message_type = "";

if (!isset($_SESSION["profile_token"])) {
    $_SESSION["profile_token"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["profile_token"];

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF validation
    if (
        !isset($_POST["profile_token"]) ||
        !hash_equals($_SESSION["profile_token"], $_POST["profile_token"])
    ) {
        $message = "Invalid request. Please try again.";
        $message_type = "error";

    } else {

        $action = $_POST["action"] ?? "";

        // UPDATE PERSONAL INFORMATION
        if ($action === "update_profile") {

            $full_name = trim($_POST["full_name"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $phone = trim($_POST["phone"] ?? "");

            if (empty($full_name) || empty($email) || empty($phone)) {

                $message = "Please fill in all personal information fields.";
                $message_type = "error";

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $message = "Please enter a valid email address.";
                $message_type = "error";

            } else {

                // Check if email is already used by another customer
                $check_email = $conn->prepare(
                    "SELECT id FROM customers WHERE email = ? AND id != ?"
                );

                $check_email->bind_param("si", $email, $customer_id);
                $check_email->execute();

                $email_result = $check_email->get_result();

                if ($email_result->num_rows > 0) {

                    $message = "This email address is already being used.";
                    $message_type = "error";

                } else {

                    // Update customer information
                    $update = $conn->prepare(
                        "UPDATE customers
                         SET full_name = ?, email = ?, phone = ?
                         WHERE id = ?"
                    );

                    $update->bind_param(
                        "sssi",
                        $full_name,
                        $email,
                        $phone,
                        $customer_id
                    );

                    if ($update->execute()) {

                        $_SESSION["customer_name"] = $full_name;
                        $_SESSION["customer_email"] = $email;

                        $message = "Your personal information has been updated successfully.";
                        $message_type = "success";

                    } else {

                        $message = "Unable to update your information. Please try again.";
                        $message_type = "error";
                    }

                    $update->close();
                }

                $check_email->close();
            }
        }

        // CHANGE PASSWORD
        elseif ($action === "change_password") {

            $current_password = $_POST["current_password"] ?? "";
            $new_password = $_POST["new_password"] ?? "";
            $confirm_password = $_POST["confirm_password"] ?? "";

            if (
                empty($current_password) ||
                empty($new_password) ||
                empty($confirm_password)
            ) {

                $message = "Please fill in all password fields.";
                $message_type = "error";

            } elseif (strlen($new_password) < 8) {

                $message = "Your new password must be at least 8 characters.";
                $message_type = "error";

            } elseif ($new_password !== $confirm_password) {

                $message = "The new password and confirmation do not match.";
                $message_type = "error";

            } else {

                // Get current password from database
                $password_query = $conn->prepare(
                    "SELECT password FROM customers WHERE id = ?"
                );

                $password_query->bind_param("i", $customer_id);
                $password_query->execute();

                $password_result = $password_query->get_result();
                $customer_password = $password_result->fetch_assoc();

                if (
                    !$customer_password ||
                    !password_verify(
                        $current_password,
                        $customer_password["password"]
                    )
                ) {

                    $message = "Your current password is incorrect.";
                    $message_type = "error";

                } else {

                    // Hash new password
                    $hashed_password = password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                    $password_update = $conn->prepare(
                        "UPDATE customers SET password = ? WHERE id = ?"
                    );

                    $password_update->bind_param(
                        "si",
                        $hashed_password,
                        $customer_id
                    );

                    if ($password_update->execute()) {

                        $message = "Your password has been changed successfully.";
                        $message_type = "success";

                    } else {

                        $message = "Unable to change password. Please try again.";
                        $message_type = "error";
                    }

                    $password_update->close();
                }

                $password_query->close();
            }
        }
    }
}

// Get latest customer information
$query = $conn->prepare(
    "SELECT full_name, email, phone, created_at
     FROM customers
     WHERE id = ?"
);

$query->bind_param("i", $customer_id);
$query->execute();

$result = $query->get_result();
$customer = $result->fetch_assoc();

$query->close();

if (!$customer) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Refresh session values
$_SESSION["customer_name"] = $customer["full_name"];
$_SESSION["customer_email"] = $customer["email"];

$customer_name = $customer["full_name"];
$customer_email = $customer["email"];
$customer_phone = $customer["phone"];

$initial = strtoupper(substr($customer_name, 0, 1));

$joined_date = !empty($customer["created_at"])
    ? date("F Y", strtotime($customer["created_at"]))
    : "Customer";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | CakeCraft</title>

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
            --line-strong: #dfcec7;
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;
            --gutter: max(6%, calc((100vw - 1180px) / 2));
            --radius: 14px;
            --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
            --shadow-hover: 0 14px 34px rgba(73, 48, 45, 0.14);
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
            color: var(--ink);
            background: var(--bg);
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4 {
            color: var(--ink);
            font-family: var(--font-display);
        }

        button, input, select, textarea {
            font: inherit;
        }

        :focus-visible {
            outline: 3px solid var(--rose);
            outline-offset: 3px;
            border-radius: 4px;
        }

        /* NAVIGATION */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            width: 100%;
            background: rgba(255, 250, 247, 0.96);
            border-bottom: 1px solid var(--line);
            box-shadow: 0 2px 14px rgba(59, 39, 36, 0.05);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
        }

        .nav-container {
            width: 100%;
            max-width: 1180px;
            min-height: 74px;
            margin: 0 auto;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
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
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px 25px;
        }

        .nav-links a {
            position: relative;
            padding: 6px 0;
            color: var(--ink);
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            white-space: nowrap;
            transition: color 0.2s var(--ease);
        }

        .nav-links a::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 2px;
            border-radius: 2px;
            background: var(--rose);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.25s var(--ease);
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
            border: 1.5px solid var(--rose-deep);
            border-radius: 999px;
            color: var(--rose-deep);
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s var(--ease), background 0.2s var(--ease), border-color 0.2s var(--ease);
        }

        .nav-links a.logout::after {
            display: none;
        }

        .nav-links a.logout:hover {
            color: #ffffff;
            background: var(--rose-deep);
            border-color: var(--rose-deep);
        }

        /* MAIN CONTAINER */
        .main-container {
            width: 100%;
            max-width: 1250px;
            margin: 0 auto;
            padding: 44px 25px 64px;
            flex: 1;
        }

        /* PAGE HEADING */
        .page-heading {
            margin-bottom: 28px;
        }

        .page-heading h1 {
            margin-bottom: 8px;
            font-family: var(--font-display);
            font-size: clamp(2rem, 4.4vw, 2.6rem);
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.01em;
        }

        .page-heading p {
            color: var(--ink-soft);
            font-size: 16px;
            line-height: 1.6;
        }

        /* NOTIFICATION MESSAGE */
        .message {
            margin-bottom: 25px;
            padding: 15px 18px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
        }

        .message.success {
            color: #216b3a;
            background: #edf8f0;
            border: 1px solid #bfe3cb;
            border-left: 4px solid #278348;
        }

        .message.error {
            color: #9b1c1c;
            background: #fdecea;
            border: 1px solid #f3c7c2;
            border-left: 4px solid #9b1c1c;
        }

        /* MAIN PROFILE LAYOUT */
        .profile-layout {
            display: grid;
            grid-template-columns: 290px minmax(0, 1fr);
            gap: 25px;
            align-items: start;
        }

        /* LEFT PROFILE SIDEBAR */
        .profile-sidebar {
            padding: 30px 24px;
            text-align: center;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100px;
            height: 100px;
            margin: 0 auto 18px;
            border: 5px solid var(--bg);
            border-radius: 50%;
            color: var(--rose-deep);
            background: var(--bg-soft);
            font-family: var(--font-display);
            font-size: 38px;
            font-weight: 700;
        }

        .profile-sidebar h2 {
            margin-bottom: 8px;
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 21px;
            font-weight: 700;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .profile-sidebar .email {
            color: var(--ink-soft);
            font-size: 13px;
            overflow-wrap: anywhere;
            line-height: 1.6;
        }

        .profile-divider {
            height: 1px;
            margin: 25px 0;
            background: var(--line);
        }

        .profile-details {
            text-align: left;
        }

        .detail-item {
            margin-bottom: 20px;
        }

        .detail-item:last-child {
            margin-bottom: 0;
        }

        .detail-label {
            margin-bottom: 7px;
            color: var(--ink-soft);
            opacity: 0.85;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .detail-value {
            color: var(--ink-soft);
            font-size: 14px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        /* RIGHT SIDE */
        .profile-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
            min-width: 0;
        }

        /* CARDS */
        .content-card {
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: box-shadow 0.25s var(--ease);
        }

        .content-card:hover {
            box-shadow: var(--shadow-hover);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 23px 27px;
            border-bottom: 1px solid var(--line);
            background: var(--bg);
        }

        .header-icon {
            display: flex;
            flex: none;
            align-items: center;
            justify-content: center;
            width: 43px;
            height: 43px;
            border-radius: 12px;
            color: var(--rose-deep);
            background: var(--bg-soft);
            font-size: 21px;
        }

        .header-icon.password-icon {
            background: var(--bg-tint);
        }

        .header-title h2 {
            margin-bottom: 5px;
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 19px;
            font-weight: 700;
            line-height: 1.3;
        }

        .header-title p {
            color: var(--ink-soft);
            font-size: 13px;
            line-height: 1.5;
        }

        .card-body {
            padding: 27px;
        }

        /* FORM */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            margin-bottom: 9px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--line-strong);
            border-radius: 10px;
            outline: none;
            color: var(--ink);
            background: var(--surface);
            font-family: var(--font-body);
            font-size: 15px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            border-color: var(--rose);
            box-shadow: 0 0 0 3px rgba(196, 124, 120, 0.16);
        }

        .form-group input::placeholder {
            color: var(--ink-soft);
            opacity: 0.7;
        }

        .form-note {
            margin-top: 7px;
            color: var(--ink-soft);
            font-size: 12px;
            line-height: 1.5;
        }

        /* BUTTONS */
        .form-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 25px;
            padding-top: 22px;
            border-top: 1px solid var(--line);
        }

        .save-button {
            padding: 13px 25px;
            border: 1.5px solid var(--rose-deep);
            border-radius: 999px;
            color: #ffffff;
            background: var(--rose-deep);
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s var(--ease), border-color 0.2s var(--ease), transform 0.2s var(--ease), box-shadow 0.2s var(--ease);
        }

        .save-button:hover {
            background: var(--rose-darker);
            border-color: var(--rose-darker);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(166, 90, 86, 0.2);
        }

        /* PASSWORD SECTION */
        .password-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        /* BACK LINK */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 28px;
            color: var(--rose-deep);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--rose-darker);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        /* FOOTER */
        footer {
            margin-top: 45px;
            padding: 28px 20px;
            border-top: 1px solid var(--line);
            color: var(--ink-soft);
            background: var(--bg-soft);
            font-size: 13px;
            text-align: center;
        }

        /* RESPONSIVE */
        @media (max-width: 950px) {
            .profile-layout {
                grid-template-columns: 250px minmax(0, 1fr);
                gap: 18px;
            }

            .card-header {
                padding: 20px;
            }

            .card-body {
                padding: 22px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .form-group.full-width {
                grid-column: auto;
            }
        }

        @media (max-width: 760px) {
            .nav-container {
                flex-direction: column;
                justify-content: center;
                gap: 12px;
                padding: 14px 6%;
            }

            .nav-links {
                justify-content: center;
                gap: 8px 20px;
            }

            .main-container {
                padding: 36px 6% 52px;
            }
        }

        @media (max-width: 700px) {
            .page-heading h1 {
                font-size: 27px;
            }

            .profile-layout {
                grid-template-columns: 1fr;
            }

            .profile-sidebar {
                padding: 25px;
            }

            .profile-content {
                gap: 18px;
            }

            .card-header {
                padding: 18px;
            }

            .card-body {
                padding: 20px;
            }

            .header-title h2 {
                font-size: 17px;
            }

            .form-footer {
                margin-top: 20px;
            }

            .save-button {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .nav-links {
                gap: 6px 14px;
            }

            .nav-links a {
                font-size: 13px;
            }

            .nav-links a.logout {
                padding: 8px 15px;
            }

            .main-container {
                padding-right: 16px;
                padding-left: 16px;
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

<!-- NAVBAR -->
<header class="navbar">
    <div class="nav-container">
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

        <nav class="nav-links" aria-label="Customer navigation">
            <a href="dashboard.php">Home</a>
            <a href="cakes.php">Cakes</a>
            <a href="my_orders.php">My Orders</a>
            <a href="notifications.php">🔔 Notifications</a>
            <a href="profile.php" class="active" aria-current="page">Profile</a>
            <a href="logout.php" class="logout">Logout</a>
        </nav>
    </div>
</header>

<!-- MAIN CONTENT -->
<main class="main-container">

    <div class="page-heading">
        <h1>My Profile</h1>
        <p>Manage your personal information and account security.</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>" role="status">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="profile-layout">

        <!-- LEFT SIDEBAR -->
        <aside class="profile-sidebar">
            <div class="avatar">
                <?php echo htmlspecialchars($initial); ?>
            </div>

            <h2><?php echo htmlspecialchars($customer_name); ?></h2>
            <p class="email"><?php echo htmlspecialchars($customer_email); ?></p>

            <div class="profile-divider"></div>

            <div class="profile-details">
                <div class="detail-item">
                    <div class="detail-label">Full Name</div>
                    <div class="detail-value"><?php echo htmlspecialchars($customer_name); ?></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Email Address</div>
                    <div class="detail-value"><?php echo htmlspecialchars($customer_email); ?></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Contact Number</div>
                    <div class="detail-value"><?php echo htmlspecialchars($customer_phone); ?></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">Customer Since</div>
                    <div class="detail-value"><?php echo htmlspecialchars($joined_date); ?></div>
                </div>
            </div>
        </aside>

        <!-- RIGHT CONTENT -->
        <div class="profile-content">

            <!-- PERSONAL INFORMATION -->
            <section class="content-card">
                <div class="card-header">
                    <div class="header-icon" aria-hidden="true">👤</div>
                    <div class="header-title">
                        <h2>Personal Information</h2>
                        <p>Update your name, email, and contact number.</p>
                    </div>
                </div>

                <div class="card-body">
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="profile_token" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="full_name">Full Name</label>
                                <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    value="<?php echo htmlspecialchars($customer_name); ?>"
                                    placeholder="Enter your full name"
                                    maxlength="100"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="<?php echo htmlspecialchars($customer_email); ?>"
                                    placeholder="Enter your email"
                                    maxlength="100"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="phone">Contact Number</label>
                                <input
                                    type="text"
                                    id="phone"
                                    name="phone"
                                    value="<?php echo htmlspecialchars($customer_phone); ?>"
                                    placeholder="Enter your contact number"
                                    maxlength="20"
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="save-button">Save Changes</button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- CHANGE PASSWORD -->
            <section class="content-card">
                <div class="card-header">
                    <div class="header-icon password-icon" aria-hidden="true">🔒</div>
                    <div class="header-title">
                        <h2>Change Password</h2>
                        <p>Keep your account secure with a strong password.</p>
                    </div>
                </div>

                <div class="card-body">
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="profile_token" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="password-grid">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input
                                    type="password"
                                    id="current_password"
                                    name="current_password"
                                    placeholder="Enter your current password"
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input
                                    type="password"
                                    id="new_password"
                                    name="new_password"
                                    placeholder="Enter your new password"
                                    minlength="8"
                                    required
                                >
                                <span class="form-note">Your password must contain at least 8 characters.</span>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Re-enter your new password"
                                    minlength="8"
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="save-button">Update Password</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
</main>

<footer>
    &copy; <?php echo date("Y"); ?> CakeCraft. All rights reserved.
</footer>

</body>
</html>
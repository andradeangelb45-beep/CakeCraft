<?php
session_start();
require_once "../database.php";

// ADMIN LOGIN CHECK
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

$admin_id = (int) $_SESSION["admin_id"];
$message = "";
$message_type = "";

// CSRF TOKEN
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION["csrf_token"];

// HANDLE VERIFY / REJECT
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        die("Invalid request.");
    }

    $type = $_POST["type"] ?? "";
    $id = (int) ($_POST["id"] ?? 0);
    $action = $_POST["action"] ?? "";
    $reason = trim($_POST["rejection_reason"] ?? "");

    if ($id <= 0 || !in_array($type, ["regular", "custom"], true)) {
        $message = "Invalid payment information.";
        $message_type = "error";
    } else {

        if ($type === "regular") {
            $stmt = $conn->prepare("\n                SELECT customer_id, payment_status\n                FROM orders\n                WHERE id = ?\n            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $payment = $result->fetch_assoc();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("\n                SELECT customer_id, payment_status\n                FROM custom_cake_requests\n                WHERE id = ?\n            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $payment = $result->fetch_assoc();
            $stmt->close();
        }

        if (!$payment) {
            $message = "Payment record not found.";
            $message_type = "error";

        } elseif ($payment["payment_status"] !== "Pending Verification") {
            $message = "This payment is no longer awaiting verification.";
            $message_type = "error";

        } elseif ($action === "verify") {

            if ($type === "regular") {
                $stmt = $conn->prepare("\n                    UPDATE orders\n                    SET payment_status = 'Paid',\n                        payment_verified_at = NOW(),\n                        payment_verified_by = ?,\n                        payment_rejection_reason = NULL\n                    WHERE id = ?\n                    AND payment_status = 'Pending Verification'\n                ");
                $stmt->bind_param("ii", $admin_id, $id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("\n                    UPDATE custom_cake_requests\n                    SET payment_status = 'Paid',\n                        payment_verified_at = NOW(),\n                        payment_verified_by = ?,\n                        payment_rejection_reason = NULL\n                    WHERE id = ?\n                    AND payment_status = 'Pending Verification'\n                ");
                $stmt->bind_param("ii", $admin_id, $id);
                $stmt->execute();
                $stmt->close();
            }

            // CUSTOMER NOTIFICATION
            $customer_id = (int) $payment["customer_id"];
            $title = "Payment Verified";
            $notification_message = ($type === "regular")
                ? "Your payment for Order #$id has been verified. Your payment is now marked as Paid."
                : "Your payment for Custom Cake Request #$id has been verified. Your payment is now marked as Paid.";
            $link = "my_orders.php";

            $stmt = $conn->prepare("\n                INSERT INTO notifications\n                (customer_id, order_id, title, message, link)\n                VALUES (?, ?, ?, ?, ?)\n            ");
            $stmt->bind_param(
                "iisss",
                $customer_id,
                $id,
                $title,
                $notification_message,
                $link
            );
            $stmt->execute();
            $stmt->close();

            $message = "Payment successfully verified!";
            $message_type = "success";

        } elseif ($action === "reject") {

            if ($reason === "") {
                $message = "Please provide a reason for rejecting the payment.";
                $message_type = "error";
            } else {

                if ($type === "regular") {
                    $stmt = $conn->prepare("\n                        UPDATE orders\n                        SET payment_status = 'Rejected',\n                            payment_rejection_reason = ?,\n                            payment_verified_at = NULL,\n                            payment_verified_by = NULL\n                        WHERE id = ?\n                        AND payment_status = 'Pending Verification'\n                    ");
                    $stmt->bind_param("si", $reason, $id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("\n                        UPDATE custom_cake_requests\n                        SET payment_status = 'Rejected',\n                            payment_rejection_reason = ?,\n                            payment_verified_at = NULL,\n                            payment_verified_by = NULL\n                        WHERE id = ?\n                        AND payment_status = 'Pending Verification'\n                    ");
                    $stmt->bind_param("si", $reason, $id);
                    $stmt->execute();
                    $stmt->close();
                }

                // CUSTOMER NOTIFICATION
                $customer_id = (int) $payment["customer_id"];
                $title = "Payment Rejected";
                $notification_message = ($type === "regular")
                    ? "Your payment for Order #$id was rejected. Reason: $reason Please submit your payment again."
                    : "Your payment for Custom Cake Request #$id was rejected. Reason: $reason Please submit your payment again.";
                $link = "my_orders.php";

                $stmt = $conn->prepare("\n                    INSERT INTO notifications\n                    (customer_id, order_id, title, message, link)\n                    VALUES (?, ?, ?, ?, ?)\n                ");
                $stmt->bind_param(
                    "iisss",
                    $customer_id,
                    $id,
                    $title,
                    $notification_message,
                    $link
                );
                $stmt->execute();
                $stmt->close();

                $message = "Payment rejected. Customer has been notified.";
                $message_type = "success";
            }

        } else {
            $message = "Invalid action.";
            $message_type = "error";
        }
    }
}

// FETCH REGULAR PAYMENTS
$regular_payments = [];
$sql = "\n    SELECT *\n    FROM orders\n    WHERE payment_status = 'Pending Verification'\n    ORDER BY payment_submitted_at DESC\n";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $regular_payments[] = $row;
    }
}

// FETCH CUSTOM CAKE PAYMENTS
$custom_payments = [];
$sql = "\n    SELECT *\n    FROM custom_cake_requests\n    WHERE payment_status = 'Pending Verification'\n    ORDER BY created_at DESC\n";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $custom_payments[] = $row;
    }
}

$total_pending = count($regular_payments) + count($custom_payments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification | CakeCraft Admin</title>

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
            --shadow-hover: 0 14px 34px rgba(73, 48, 45, 0.13);
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
            background: var(--bg);
            color: var(--ink);
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        button,
        input,
        textarea {
            font: inherit;
        }

        :focus-visible {
            outline: 3px solid var(--rose);
            outline-offset: 3px;
            border-radius: 5px;
        }

        /* HEADER / BRAND */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 14px var(--gutter);
            background: rgba(255, 250, 247, .96);
            border-bottom: 1px solid var(--line);
            box-shadow: 0 3px 16px rgba(59, 39, 36, .045);
            backdrop-filter: blur(12px);
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
            text-decoration: none;
            color: var(--ink);
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
            line-height: 1.1;
            letter-spacing: -.01em;
        }

        .brand-name span.brand-craft {
            color: var(--craft-color);
        }

        .admin-badge {
            padding: 4px 11px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--bg-soft);
            color: var(--rose-deep);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 19px;
            border: 1.5px solid var(--rose);
            border-radius: 999px;
            background: var(--surface);
            color: var(--ink);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background .2s var(--ease), border-color .2s var(--ease), color .2s var(--ease), transform .2s var(--ease);
        }

        .back-btn:hover {
            background: var(--bg-soft);
            border-color: var(--rose-deep);
            color: var(--rose-deep);
            transform: translateY(-1px);
        }

        /* PAGE LAYOUT */
        .container {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 44px 24px 72px;
        }

        .page-heading {
            margin-bottom: 28px;
        }

        .eyebrow {
            margin-bottom: 8px;
            color: var(--rose-deep);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .page-heading h1 {
            margin-bottom: 9px;
            font-family: var(--font-display);
            font-size: clamp(2rem, 4vw, 2.7rem);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -.015em;
        }

        .page-heading p {
            color: var(--ink-soft);
            font-size: 16px;
        }

        /* SUMMARY */
        .summary {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 22px 25px;
            margin-bottom: 30px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .summary-icon {
            display: grid;
            flex: none;
            place-items: center;
            width: 54px;
            height: 54px;
            border-radius: 15px;
            background: var(--bg-soft);
            border: 1px solid var(--line);
            color: var(--rose-deep);
            font-size: 25px;
        }

        .summary h2 {
            color: var(--rose-deep);
            font-family: var(--font-display);
            font-size: 32px;
            font-weight: 700;
            line-height: 1.1;
        }

        .summary p {
            margin-top: 4px;
            color: var(--ink-soft);
            font-size: 14px;
        }

        /* ALERTS */
        .alert {
            padding: 14px 17px;
            margin-bottom: 22px;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.6;
        }

        .alert.success {
            border-color: #d7e8d8;
            border-left: 4px solid #426c4e;
            background: #edf5ed;
            color: #426c4e;
        }

        .alert.error {
            border-color: #efd1cd;
            border-left: 4px solid #963f3b;
            background: #f9e9e7;
            color: #963f3b;
        }

        /* SECTION HEADINGS */
        .section-title {
            margin: 38px 0 18px;
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 27px;
            font-weight: 700;
            line-height: 1.25;
        }

        .section-description {
            margin: -10px 0 18px;
            color: var(--ink-soft);
            font-size: 14px;
        }

        /* PAYMENT CARDS */
        .payment-card {
            padding: 24px;
            margin-bottom: 22px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--surface);
            box-shadow: var(--shadow);
            transition: transform .25s var(--ease), box-shadow .25s var(--ease);
        }

        .payment-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            flex-wrap: wrap;
            padding-bottom: 17px;
            margin-bottom: 19px;
            border-bottom: 1px solid var(--line);
        }

        .payment-header h3 {
            margin-bottom: 4px;
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 700;
            line-height: 1.3;
        }

        .payment-header p {
            color: var(--ink-soft);
            font-size: 13px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            height: fit-content;
            padding: 7px 13px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--bg-soft);
            color: var(--rose-darker);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .detail {
            min-width: 0;
            padding: 14px 15px;
            border: 1px solid var(--line);
            border-radius: 11px;
            background: var(--bg);
        }

        .detail small {
            display: block;
            margin-bottom: 5px;
            color: var(--ink-soft);
            font-size: 12px;
            font-weight: 500;
        }

        .detail strong {
            display: block;
            color: var(--ink);
            font-size: 15px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        /* PAYMENT PROOF */
        .proof-section {
            margin: 18px 0 20px;
        }

        .proof-section h4 {
            margin-bottom: 11px;
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 700;
        }

        .proof-image {
            display: block;
            width: 400px;
            max-width: 100%;
            max-height: 500px;
            object-fit: contain;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--surface);
        }

        .proof-link {
            display: inline-block;
            margin-top: 10px;
            color: var(--rose-deep);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .proof-link:hover {
            color: var(--rose-darker);
            text-decoration: underline;
        }

        .no-proof {
            color: var(--ink-soft);
            font-size: 14px;
        }

        /* ACTIONS */
        .actions {
            display: flex;
            align-items: center;
            gap: 11px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .actions form {
            margin: 0;
        }

        .actions button,
        .reject-form button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 42px;
            padding: 10px 19px;
            border: 1px solid transparent;
            border-radius: 999px;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s var(--ease), transform .2s var(--ease), box-shadow .2s var(--ease);
        }

        .actions button:hover,
        .reject-form button:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(73, 48, 45, .12);
        }

        .verify-btn {
            background: #426c4e;
            color: #fff;
        }

        .verify-btn:hover {
            background: #35583f;
        }

        .reject-btn,
        .confirm-reject {
            background: var(--rose-deep);
            color: #fff;
        }

        .reject-btn:hover,
        .confirm-reject:hover {
            background: var(--rose-darker);
        }

        .reject-form {
            display: none;
            width: 100%;
            padding: 17px;
            margin-top: 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--bg);
        }

        .reject-form textarea {
            display: block;
            width: 100%;
            min-height: 100px;
            padding: 13px 14px;
            margin-bottom: 12px;
            border: 1px solid var(--line-strong);
            border-radius: 10px;
            background: var(--surface);
            color: var(--ink);
            font-family: var(--font-body);
            font-size: 14px;
            line-height: 1.5;
            resize: vertical;
        }

        .reject-form textarea:focus {
            outline: 2px solid var(--rose);
            outline-offset: 2px;
        }

        .cancel-reject {
            margin-left: 7px;
            background: var(--bg-soft);
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .cancel-reject:hover {
            background: var(--bg-tint);
        }

        /* EMPTY STATE */
        .empty {
            padding: 48px 24px;
            border: 1px dashed var(--rose);
            border-radius: 16px;
            background: var(--surface);
            color: var(--ink-soft);
            text-align: center;
        }

        .empty-icon {
            display: grid;
            place-items: center;
            width: 58px;
            height: 58px;
            margin: 0 auto 15px;
            border-radius: 16px;
            background: var(--bg-soft);
            color: var(--rose-deep);
            font-size: 27px;
        }

        .empty h3 {
            margin-bottom: 7px;
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 23px;
            font-weight: 700;
        }

        .empty p {
            font-size: 14px;
        }

        @media (max-width: 700px) {
            .topbar {
                padding: 13px 5%;
            }

            .brand-name {
                font-size: 25px;
            }

            .container {
                padding: 32px 16px 55px;
            }

            .page-heading h1 {
                font-size: 32px;
            }

            .payment-card {
                padding: 20px 17px;
            }

            .summary {
                padding: 19px;
            }
        }

        @media (max-width: 480px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .back-btn {
                width: 100%;
            }

            .payment-header {
                flex-direction: column;
            }

            .actions {
                align-items: stretch;
                flex-direction: column;
            }

            .actions form,
            .actions button {
                width: 100%;
            }

            .reject-form button {
                width: 100%;
            }

            .cancel-reject {
                margin: 8px 0 0;
            }

            .summary h2 {
                font-size: 28px;
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

<header class="topbar">
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

    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
</header>

<main class="container">
    <div class="page-heading">
        <p class="eyebrow">Order &amp; Payment Management</p>
        <h1>Payment Verification</h1>
        <p>Review submitted GCash payments and verify or reject customer payment proofs.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert <?php echo htmlspecialchars($message_type); ?>" role="<?php echo $message_type === "success" ? "status" : "alert"; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="summary">
        <div class="summary-icon" aria-hidden="true">₱</div>
        <div>
            <h2><?php echo (int) $total_pending; ?></h2>
            <p>Payments awaiting verification</p>
        </div>
    </div>

    <?php if ($total_pending === 0): ?>
        <div class="empty">
            <div class="empty-icon" aria-hidden="true">✓</div>
            <h3>No pending payments</h3>
            <p>There are currently no payments waiting for verification.</p>
        </div>
    <?php else: ?>

        <!-- REGULAR ORDERS -->
        <?php if (!empty($regular_payments)): ?>
            <h2 class="section-title">Regular Cake Orders</h2>
            <p class="section-description">GCash payments submitted for regular cake orders.</p>

            <?php foreach ($regular_payments as $payment): ?>
                <?php
                $order_id = (int) $payment["id"];
                $customer_id = (int) $payment["customer_id"];
                $delivery_fee = (float) ($payment["delivery_fee"] ?? 0);
                $cake_total = (float) $payment["total_price"];

                if (($payment["delivery_method"] ?? "") === "Pickup") {
                    $delivery_fee = 0;
                }

                $payable = $cake_total + $delivery_fee;
                $proof_name = basename($payment["payment_proof"] ?? "");
                $proof_path = "../assets/images/payment_proofs/" . rawurlencode($proof_name);
                ?>

                <article class="payment-card">
                    <div class="payment-header">
                        <div>
                            <h3>Order #<?php echo $order_id; ?></h3>
                            <p>Customer ID: <?php echo $customer_id; ?></p>
                        </div>
                        <span class="badge">Pending Verification</span>
                    </div>

                    <div class="details">
                        <div class="detail">
                            <small>Amount to Pay</small>
                            <strong>₱<?php echo number_format($payable, 2); ?></strong>
                        </div>
                        <div class="detail">
                            <small>GCash Reference Number</small>
                            <strong><?php echo htmlspecialchars($payment["gcash_reference_number"] ?? ""); ?></strong>
                        </div>
                        <div class="detail">
                            <small>Delivery Method</small>
                            <strong><?php echo htmlspecialchars($payment["delivery_method"] ?? ""); ?></strong>
                        </div>
                        <div class="detail">
                            <small>Payment Submitted</small>
                            <strong><?php echo htmlspecialchars($payment["payment_submitted_at"] ?? "N/A"); ?></strong>
                        </div>
                    </div>

                    <div class="proof-section">
                        <h4>Payment Screenshot</h4>
                        <?php if ($proof_name !== ""): ?>
                            <a href="<?php echo htmlspecialchars($proof_path); ?>" target="_blank" rel="noopener">
                                <img src="<?php echo htmlspecialchars($proof_path); ?>" class="proof-image" alt="Payment proof">
                            </a>
                            <a href="<?php echo htmlspecialchars($proof_path); ?>" target="_blank" rel="noopener" class="proof-link">Open Full Image</a>
                        <?php else: ?>
                            <p class="no-proof">No payment screenshot found.</p>
                        <?php endif; ?>
                    </div>

                    <div class="actions">
                        <form method="POST" onsubmit="return confirm('Verify this payment and mark it as Paid?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="type" value="regular">
                            <input type="hidden" name="id" value="<?php echo $order_id; ?>">
                            <input type="hidden" name="action" value="verify">
                            <button type="submit" class="verify-btn">✓ Verify Payment</button>
                        </form>

                        <button type="button" class="reject-btn" onclick="toggleReject('regular-<?php echo $order_id; ?>')">✕ Reject Payment</button>
                    </div>

                    <form method="POST" class="reject-form" id="regular-<?php echo $order_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="type" value="regular">
                        <input type="hidden" name="id" value="<?php echo $order_id; ?>">
                        <input type="hidden" name="action" value="reject">
                        <textarea name="rejection_reason" placeholder="Enter the reason for rejecting this payment..." required></textarea>
                        <button type="submit" class="confirm-reject">Confirm Rejection</button>
                        <button type="button" class="cancel-reject" onclick="toggleReject('regular-<?php echo $order_id; ?>')">Cancel</button>
                    </form>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- CUSTOM CAKE REQUESTS -->
        <?php if (!empty($custom_payments)): ?>
            <h2 class="section-title">Custom Cake Payments</h2>
            <p class="section-description">GCash payments submitted for customized cake requests.</p>

            <?php foreach ($custom_payments as $payment): ?>
                <?php
                $request_id = (int) $payment["id"];
                $customer_id = (int) $payment["customer_id"];
                $payable = (float) ($payment["quoted_price"] ?? 0);
                $proof_name = basename($payment["payment_proof"] ?? "");
                $proof_path = "../assets/images/payment_proofs/" . rawurlencode($proof_name);
                ?>

                <article class="payment-card">
                    <div class="payment-header">
                        <div>
                            <h3>Custom Request #<?php echo $request_id; ?></h3>
                            <p>Customer ID: <?php echo $customer_id; ?></p>
                        </div>
                        <span class="badge">Pending Verification</span>
                    </div>

                    <div class="details">
                        <div class="detail">
                            <small>Amount to Pay</small>
                            <strong>₱<?php echo number_format($payable, 2); ?></strong>
                        </div>
                        <div class="detail">
                            <small>GCash Reference Number</small>
                            <strong><?php echo htmlspecialchars($payment["gcash_reference"] ?? $payment["gcash_reference_number"] ?? ""); ?></strong>
                        </div>
                        <div class="detail">
                            <small>Cake Size</small>
                            <strong><?php echo htmlspecialchars($payment["cake_size"] ?? "Custom Cake"); ?></strong>
                        </div>
                        <div class="detail">
                            <small>Submitted On</small>
                            <strong><?php echo htmlspecialchars($payment["payment_submitted_at"] ?? $payment["created_at"] ?? "N/A"); ?></strong>
                        </div>
                    </div>

                    <div class="proof-section">
                        <h4>Payment Screenshot</h4>
                        <?php if ($proof_name !== ""): ?>
                            <a href="<?php echo htmlspecialchars($proof_path); ?>" target="_blank" rel="noopener">
                                <img src="<?php echo htmlspecialchars($proof_path); ?>" class="proof-image" alt="Custom payment proof">
                            </a>
                            <a href="<?php echo htmlspecialchars($proof_path); ?>" target="_blank" rel="noopener" class="proof-link">Open Full Image</a>
                        <?php else: ?>
                            <p class="no-proof">No payment screenshot found.</p>
                        <?php endif; ?>
                    </div>

                    <div class="actions">
                        <form method="POST" onsubmit="return confirm('Verify this payment and mark it as Paid?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="type" value="custom">
                            <input type="hidden" name="id" value="<?php echo $request_id; ?>">
                            <input type="hidden" name="action" value="verify">
                            <button type="submit" class="verify-btn">✓ Verify Payment</button>
                        </form>

                        <button type="button" class="reject-btn" onclick="toggleReject('custom-<?php echo $request_id; ?>')">✕ Reject Payment</button>
                    </div>

                    <form method="POST" class="reject-form" id="custom-<?php echo $request_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="type" value="custom">
                        <input type="hidden" name="id" value="<?php echo $request_id; ?>">
                        <input type="hidden" name="action" value="reject">
                        <textarea name="rejection_reason" placeholder="Enter the reason for rejecting this payment..." required></textarea>
                        <button type="submit" class="confirm-reject">Confirm Rejection</button>
                        <button type="button" class="cancel-reject" onclick="toggleReject('custom-<?php echo $request_id; ?>')">Cancel</button>
                    </form>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>
</main>

<script>
function toggleReject(id) {
    const form = document.getElementById(id);
    if (!form) return;
    const isVisible = form.style.display === "block";
    form.style.display = isVisible ? "none" : "block";
    if (!isVisible) {
        const textarea = form.querySelector("textarea");
        if (textarea) textarea.focus();
    }
}
</script>

</body>
</html>
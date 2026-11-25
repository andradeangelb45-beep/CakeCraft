<?php
session_start();
require_once "../database.php";

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}
$customer_id = (int)$_SESSION["customer_id"];

/* CSRF TOKEN */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

/* HANDLE CANCELLATION */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST["csrf_token"] ?? "";
    if (!hash_equals($_SESSION["csrf_token"], $csrf_token)) {
        header("Location: my_orders.php?cancel_result=invalid");
        exit();
    }

    if (isset($_POST["cancel_order"])) {
        $order_id = (int)($_POST["order_id"] ?? 0);
        $cancel_stmt = $conn->prepare("UPDATE orders SET order_status = 'Cancelled' WHERE id = ? AND customer_id = ? AND order_status = 'Pending'");
        $cancel_stmt->bind_param("ii", $order_id, $customer_id);
        $cancel_stmt->execute();
        $result = $cancel_stmt->affected_rows > 0 ? "success" : "not_allowed";
        $cancel_stmt->close();
        header("Location: my_orders.php?cancel_result=" . $result);
        exit();
    }

    if (isset($_POST["cancel_custom_request"])) {
        $request_id = (int)($_POST["request_id"] ?? 0);
        $cancel_stmt = $conn->prepare("UPDATE custom_cake_requests SET request_status = 'Cancelled' WHERE id = ? AND customer_id = ? AND request_status = 'Pending'");
        $cancel_stmt->bind_param("ii", $request_id, $customer_id);
        $cancel_stmt->execute();
        $result = $cancel_stmt->affected_rows > 0 ? "success" : "not_allowed";
        $cancel_stmt->close();
        header("Location: my_orders.php?cancel_result=" . $result);
        exit();
    }
}
$cancel_result = $_GET["cancel_result"] ?? "";

/* GET REGULAR ORDERS */
$stmt = $conn->prepare("SELECT o.id, o.customer_name, o.contact_number, o.delivery_address, o.delivery_method, o.delivery_fee, o.total_price, o.quantity, o.preferred_date, o.special_instructions, o.order_status, o.created_at, o.payment_method, o.payment_status, o.gcash_reference_number, o.payment_proof, o.payment_submitted_at, o.payment_verified_at, o.payment_rejection_reason, c.cake_name, s.size_name, s.price FROM orders o INNER JOIN cake_options c ON o.cake_id = c.id LEFT JOIN cake_sizes s ON o.cake_size_id = s.id WHERE o.customer_id = ? ORDER BY o.created_at DESC");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$orders = $stmt->get_result();

/* GET CUSTOM CAKE REQUESTS */
$custom_stmt = $conn->prepare("SELECT custom_cake_requests.*, customers.full_name, customers.phone FROM custom_cake_requests INNER JOIN customers ON custom_cake_requests.customer_id = customers.id WHERE custom_cake_requests.customer_id = ? ORDER BY custom_cake_requests.created_at DESC");
$custom_stmt->bind_param("i", $customer_id);
$custom_stmt->execute();
$custom_requests = $custom_stmt->get_result();

function order_status_class($status) {
    $key = strtolower(trim((string)$status));
    $key = str_replace([" ", "_"], "-", $key);
    return in_array($key, ["pending", "approved", "ready-for-pickup", "out-for-delivery", "completed", "cancelled"], true) ? $key : "pending";
}
function order_status_label($status) {
    $status = trim((string)$status);
    if (strcasecmp($status, "ready for pickup") === 0) return "Ready for Pickup";
    if (strcasecmp($status, "out for delivery") === 0) return "Out for Delivery";
    if (strcasecmp($status, "pick-up") === 0) return "Pick-up";
    return $status !== "" ? $status : "Pending";
}
function is_pickup_method($method) {
    $method = strtolower(trim((string)$method));
    return in_array($method, ["pick-up", "pickup", "pick up"], true);
}
function is_delivery_method($method) {
    return strtolower(trim((string)$method)) === "delivery";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders | CakeCraft</title>
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
    display: flex;
    flex-direction: column;
    -webkit-font-smoothing: antialiased;
}

h1, h2, h3, h4 {
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

header {
    position: sticky;
    top: 0;
    z-index: 50;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px var(--gutter);
    background: rgba(255, 250, 247, .96);
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--line);
    box-shadow: 0 2px 14px rgba(59, 39, 36, 0.05);
}

.brand-mark {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: var(--ink);
    line-height: 1;
    white-space: nowrap;
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
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -.01em;
    color: var(--ink);
}

.logo span.brand-craft {
    color: var(--craft-color);
}

nav {
    display: flex;
    align-items: center;
    gap: 30px;
}

nav a {
    position: relative;
    color: var(--ink);
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    padding: 6px 0;
    transition: color .2s;
}

nav a::after {
    content: "";
    position: absolute;
    left: 0;
    bottom: 0;
    width: 100%;
    height: 2px;
    background: var(--rose);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform .25s;
}

nav a:hover,
nav a.active {
    color: var(--rose-deep);
}

nav a:hover::after,
nav a.active::after {
    transform: scaleX(1);
}

nav a.logout {
    padding: 9px 20px;
    border: 1.5px solid var(--rose);
    border-radius: 999px;
    font-size: 14px;
    font-weight: 600;
    transition: background .2s, color .2s, border-color .2s;
}

nav a.logout::after {
    display: none;
}

nav a.logout:hover {
    background: var(--bg-soft);
    color: var(--rose-deep);
    border-color: var(--rose-deep);
}

.page-heading {
    padding: 56px var(--gutter) 48px;
    background: var(--bg-soft);
    border-bottom: 1px solid var(--line);
}

.page-heading h1 {
    font-family: var(--font-display);
    font-size: clamp(2rem, 4.4vw, 3rem);
    font-weight: 700;
    line-height: 1.15;
    margin-bottom: 12px;
}

.subtitle {
    color: var(--ink-soft);
    font-size: 16px;
}

.jump-links {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 24px;
}

.jump-links a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    border: 1.5px solid var(--line-strong);
    border-radius: 999px;
    background: var(--surface);
    color: var(--ink);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: border-color .2s, background .2s;
}

.jump-links a:hover {
    border-color: var(--rose);
    background: var(--bg);
}

.count {
    min-width: 24px;
    padding: 0 7px;
    border-radius: 999px;
    background: var(--bg-soft);
    color: var(--ink-soft);
    font-size: 12px;
    text-align: center;
}

.container {
    flex: 1;
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;
    padding: 44px 24px 72px;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-family: var(--font-display);
    font-size: 27px;
    font-weight: 700;
    margin-bottom: 24px;
    scroll-margin-top: 96px;
}

.section-title.spaced {
    margin-top: 56px;
}

.section-title .count {
    font-family: var(--font-body);
    font-size: 13px;
    font-weight: 600;
    background: var(--surface);
    border: 1px solid var(--line-strong);
    padding: 1px 9px;
}

.order-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 28px 30px;
    margin-bottom: 26px;
    box-shadow: var(--shadow);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    padding-bottom: 20px;
    margin-bottom: 22px;
    border-bottom: 1px solid var(--line);
}

.order-header h2 {
    font-family: var(--font-display);
    font-size: 24px;
    font-weight: 700;
    line-height: 1.25;
}

.order-number {
    margin-top: 3px;
    color: var(--ink-soft);
    font-size: 14px;
    font-weight: 500;
}

.status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: none;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    background: #fff1d6;
    color: #8a5a00;
}

.status::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
}

.status.approved { background: #e5f3e8; color: #1f6a37; }
.status.completed { background: #e3efff; color: #1f559a; }
.status.cancelled { background: #fde8e6; color: #9b1c1c; }
.status.ready-for-pickup { background: #fff0dc; color: #9b641c; }
.status.out-for-delivery { background: #e8e8ff; color: #5454a0; }

.status-notice {
    margin: 0 0 22px;
    padding: 15px 18px;
    border: 1px solid var(--line);
    border-left: 4px solid var(--rose-deep);
    background: var(--bg);
    color: var(--ink);
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.6;
}

.status-notice strong {
    display: block;
    margin-bottom: 3px;
    font-size: 15px;
}

.status-notice.ready { border-color: #bfe3cb; border-left-color: #278348; background: #edf8f0; color: #216b3a; }
.status-notice.delivery { border-color: #c9c9f1; border-left-color: #6262a8; background: #f0f0ff; color: #4d4d91; }
.status-notice.completed { border-color: #b9d4ef; border-left-color: #2c6bb0; background: #edf5ff; color: #1f559a; }

.details-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 22px 24px;
}

.span-2 { grid-column: span 2; }

.detail-label {
    color: var(--ink-soft);
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 4px;
}

.detail-value {
    font-size: 15px;
    font-weight: 600;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.price-breakdown {
    margin-top: 26px;
    padding: 18px 22px;
    background: var(--bg-soft);
    border: 1px solid var(--line);
    border-radius: 12px;
}

.price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    padding: 7px 0;
    font-size: 14px;
}

.price-row strong { font-weight: 600; text-align: right; }
.price-pending { color: var(--ink-soft); font-size: 14px !important; font-weight: 600 !important; }

.total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    background: var(--bg-soft);
    border: 1px solid var(--line);
    padding: 18px 22px;
    border-radius: 12px;
    margin-top: 26px;
    font-weight: 500;
}

.price-breakdown .total {
    margin: 14px -22px -18px;
    border: none;
    border-top: 1px solid var(--line-strong);
    border-radius: 0 0 12px 12px;
}

.total span { font-size: 15px; }
.total strong { font-family: var(--font-display); font-size: 28px; font-weight: 700; color: var(--rose-deep); }
.total strong.price-pending { font-family: var(--font-body); font-size: 15px; color: var(--ink-soft); }

.order-actions {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
}

.cancel-btn {
    border: 1.5px solid #f0c3be;
    background: var(--surface);
    color: #9b1c1c;
    padding: 10px 22px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s, border-color .2s;
}

.cancel-btn:hover { background: #fde8e6; border-color: #e6a8a2; }

.payment-section {
    margin-top: 20px;
    padding: 20px;
    border: 1px solid var(--line);
    background: var(--bg);
    border-radius: 12px;
}

.payment-section h3 {
    font-family: var(--font-display);
    font-size: 21px;
    font-weight: 700;
    margin-bottom: 8px;
}

.payment-section p { font-size: 14px; color: var(--ink-soft); margin: 10px 0 12px; }

.payment-status {
    display: inline-flex;
    align-items: center;
    padding: 6px 13px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 4px;
}

.payment-status.unpaid { background: #fff1d6; color: #8a5a00; }
.payment-status.verification { background: #e3efff; color: #1f559a; }
.payment-status.paid { background: #e5f3e8; color: #1f6a37; }
.payment-status.rejected { background: #fde8e6; color: #9b1c1c; }

.pay-btn {
    display: inline-block;
    text-decoration: none;
    text-align: center;
    background: var(--rose-deep);
    color: #fff;
    padding: 12px 24px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 600;
    transition: background .2s, transform .2s;
}

.pay-btn:hover { background: var(--rose-darker); transform: translateY(-1px); }

.payment-rejection {
    margin: 10px 0 15px;
    padding: 12px 15px;
    border: 1px solid #f3c7c2;
    background: #fdecea;
    color: #9b1c1c;
    border-radius: 10px;
    font-size: 14px;
}

.payment-reminder {
    margin: 12px 0 0;
    padding: 13px 15px;
    border: 1px solid #f1d28b;
    background: #fff5dc;
    color: #805500;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.6;
}

.payment-reminder strong { display: block; margin-bottom: 3px; }

.alert {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 13px 16px;
    border-radius: 10px;
    margin-bottom: 28px;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.6;
}

.alert svg { flex: none; width: 18px; height: 18px; margin-top: 2px; }
.alert-success { background: #e8f6ec; color: #17602f; border: 1px solid #bfe3cb; border-left: 4px solid #17602f; }
.alert-error { background: #fdecea; color: #9b1c1c; border: 1px solid #f3c7c2; border-left: 4px solid #9b1c1c; }

.empty {
    background: var(--surface);
    border: 1px dashed var(--rose);
    border-radius: 16px;
    text-align: center;
    padding: 56px 24px;
    margin-bottom: 30px;
}

.empty h2 {
    font-family: var(--font-display);
    font-size: 26px;
    font-weight: 700;
    margin-bottom: 10px;
}

.empty p { color: var(--ink-soft); font-size: 15px; max-width: 420px; margin: 0 auto; }

.browse-btn {
    display: inline-block;
    margin-top: 24px;
    background: var(--rose-deep);
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    padding: 14px 30px;
    border-radius: 999px;
    box-shadow: 0 8px 20px rgba(166, 90, 86, .28);
    transition: background .2s, transform .2s;
}

.browse-btn:hover { background: var(--rose-darker); transform: translateY(-1px); }

.custom-content-grid {
    display: grid;
    grid-template-columns: 240px minmax(0, 1fr);
    gap: 26px;
    align-items: start;
}

.image-container { margin: 0; }
.image-container .detail-label { margin-bottom: 10px; }

.design-image {
    display: block;
    width: 100%;
    height: 250px;
    object-fit: contain;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--bg-soft);
    transition: box-shadow .25s;
}

.image-container a { display: block; }
.image-container a:hover .design-image { box-shadow: 0 8px 20px rgba(73, 48, 45, .14); }
.custom-details-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 20px; }
.custom-details-grid .span-2 { grid-column: 1 / -1; }
.custom-content-grid + .total { margin-top: 22px; }

.notice {
    margin-top: 18px;
    padding: 14px 16px;
    background: var(--bg-soft);
    border: 1px solid var(--line);
    border-radius: 10px;
    color: var(--ink-soft);
    font-size: 14px;
    line-height: 1.6;
}

@media (max-width: 800px) {
    .details-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .custom-content-grid { grid-template-columns: 210px minmax(0, 1fr); gap: 20px; }
    .design-image { height: 220px; }
}

@media (max-width: 700px) {
    header { flex-direction: column; gap: 12px; padding: 14px 6%; }
    .logo { font-size: 26px; }
    nav { flex-wrap: wrap; justify-content: center; gap: 8px 20px; }
    nav a { font-size: 14px; }
    .page-heading { padding: 40px 6% 36px; }
    .page-heading h1 { font-size: 36px; }
    .subtitle { font-size: 15px; }
    .container { padding: 32px 16px 56px; }
    .order-card { padding: 22px 18px; }
    .custom-content-grid { grid-template-columns: 1fr; gap: 22px; }
    .image-container { max-width: 320px; width: 100%; }
    .design-image { height: 260px; }
    .section-title { font-size: 24px; }
    .order-header h2 { font-size: 22px; }
}

@media (max-width: 480px) {
    .details-grid { grid-template-columns: 1fr; }
    .span-2 { grid-column: auto; }
    .custom-details-grid .span-2 { grid-column: 1 / -1; }
    .order-header { flex-direction: column; }
    .status { align-self: flex-start; }
    .order-actions .cancel-btn { width: 100%; }
    .total { padding: 16px; }
    .price-breakdown { padding: 16px; }
    .price-breakdown .total { margin: 14px -16px -16px; }
    .total strong { font-size: 24px; }
    .jump-links a { font-size: 13px; padding: 8px 14px; }
    .pay-btn { width: 100%; }
}

@media (prefers-reduced-motion: reduce) {
    html { scroll-behavior: auto; }
    *, *::before, *::after { transition-duration: .01ms !important; }
}
</style>
</head>
<body>
<header>
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
    <nav>
        <a href="dashboard.php">Home</a>
        <a href="cakes.php">Cakes</a>
        <a href="my_orders.php" class="active" aria-current="page">My Orders</a>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</header>
<section class="page-heading">
    <h1>My orders</h1>
    <p class="subtitle">View and monitor your CakeCraft orders.</p>
    <div class="jump-links">
        <a href="#orders">Regular orders <span class="count"><?php echo (int)$orders->num_rows; ?></span></a>
        <a href="#custom">Custom requests <span class="count"><?php echo (int)$custom_requests->num_rows; ?></span></a>
    </div>
</section>
<div class="container">
    <?php if($cancel_result === "success"): ?>
        <div class="alert alert-success" role="status"><span>✓</span><span>Your order/request has been successfully cancelled.</span></div>
    <?php elseif($cancel_result === "not_allowed"): ?>
        <div class="alert alert-error" role="alert"><span>!</span><span>This order/request can no longer be cancelled.</span></div>
    <?php elseif($cancel_result === "invalid"): ?>
        <div class="alert alert-error" role="alert"><span>!</span><span>Invalid request. Please try again.</span></div>
    <?php endif; ?>

    <h2 class="section-title" id="orders">Regular cake orders</h2>
    <?php if($orders->num_rows > 0): ?>
        <?php while($order = $orders->fetch_assoc()): ?>
            <?php
            $status_raw = trim((string)($order["order_status"] ?? "Pending"));
            $status_lower = strtolower($status_raw);
            $status_class = order_status_class($status_raw);
            $display_status = order_status_label($status_raw);
            $method = $order["delivery_method"] ?? "";
            $pickup = is_pickup_method($method);
            $delivery = is_delivery_method($method);
            $completed = $status_lower === "completed";
            $payment_method = $order["payment_method"] ?? "";
            $payment_status = $order["payment_status"] ?? "Unpaid";
            // Show Cash as Paid to the customer once the admin marks the order Completed.
            if($payment_method === "Cash" && $completed) $payment_status = "Paid";
            $cake_subtotal = (float)($order["total_price"] ?? 0);
            $delivery_fee = $order["delivery_fee"];
            $fee_is_set = $delivery_fee !== null && $delivery_fee !== "";
            if($pickup){$delivery_fee=0;$fee_is_set=true;}
            $grand_total = $fee_is_set ? $cake_subtotal + (float)$delivery_fee : null;
            $can_pay = ($payment_method === "GCash" && $status_lower === "approved" && $fee_is_set && in_array($order["payment_status"] ?? "Unpaid", ["Unpaid","Rejected"], true));
            ?>
            <div class="order-card">
                <div class="order-header"><div><h2><?php echo htmlspecialchars($order["cake_name"]); ?></h2><p class="order-number">Order #<?php echo (int)$order["id"]; ?></p></div><span class="status <?php echo htmlspecialchars($status_class); ?>"><?php echo htmlspecialchars($display_status); ?></span></div>

                <?php if($status_lower === "ready for pickup"): ?>
                    <div class="status-notice ready"><strong>🎂 Your cake is ready for pickup!</strong>Your order is ready. You may now collect your cake at CakeCraft.</div>
                <?php elseif($status_lower === "out for delivery"): ?>
                    <div class="status-notice delivery"><strong>🚚 Your cake is out for delivery!</strong>Your order is on its way to your provided delivery address.</div>
                <?php elseif($completed): ?>
                    <div class="status-notice completed"><strong>🎉 Your order is completed!</strong><?php echo $pickup ? "Your cake has been marked as picked up. Thank you for ordering from CakeCraft!" : "Your cake order has been marked as successfully delivered. Thank you for ordering from CakeCraft!"; ?></div>
                <?php endif; ?>

                <div class="details-grid">
                    <div><p class="detail-label">Cake size</p><p class="detail-value"><?php echo htmlspecialchars($order["size_name"] ?? "Not specified"); ?></p></div>
                    <div><p class="detail-label">Quantity</p><p class="detail-value"><?php echo (int)$order["quantity"]; ?></p></div>
                    <div><p class="detail-label">Preferred date</p><p class="detail-value"><?php echo !empty($order["preferred_date"]) ? date("F d, Y",strtotime($order["preferred_date"])) : "Not specified"; ?></p></div>
                    <div><p class="detail-label">Order date</p><p class="detail-value"><?php echo date("F d, Y",strtotime($order["created_at"])); ?></p></div>
                    <div class="span-2"><p class="detail-label">Customer name</p><p class="detail-value"><?php echo htmlspecialchars($order["customer_name"]); ?></p></div>
                    <div class="span-2"><p class="detail-label">Contact number</p><p class="detail-value"><?php echo htmlspecialchars($order["contact_number"]); ?></p></div>
                    <div><p class="detail-label">Delivery method</p><p class="detail-value"><?php echo htmlspecialchars($method ?: "Not specified"); ?></p></div>
                    <div><p class="detail-label">Payment method</p><p class="detail-value"><?php echo htmlspecialchars($payment_method ?: "Not specified"); ?></p></div>
                    <div class="span-2"><p class="detail-label">Delivery address</p><p class="detail-value"><?php echo $pickup ? "No delivery address required — Customer will pick up the order." : nl2br(htmlspecialchars($order["delivery_address"] ?? "Not specified")); ?></p></div>
                    <div class="span-2"><p class="detail-label">Special instructions</p><p class="detail-value"><?php echo !empty($order["special_instructions"]) ? nl2br(htmlspecialchars($order["special_instructions"])) : "None"; ?></p></div>
                </div>

                <div class="price-breakdown">
                    <div class="price-row"><span>Cake subtotal</span><strong>₱<?php echo number_format($cake_subtotal,2); ?></strong></div>
                    <div class="price-row"><span>Delivery fee</span><strong><?php echo $fee_is_set ? "₱".number_format((float)$delivery_fee,2) : "<span class=\"price-pending\">Pending delivery fee</span>"; ?></strong></div>
                    <div class="total"><span>Grand total</span><strong<?php echo !$fee_is_set ? ' class="price-pending"' : ''; ?>><?php echo $fee_is_set ? "₱".number_format((float)$grand_total,2) : "Awaiting delivery fee"; ?></strong></div>
                </div>

                <?php if($payment_method === "Cash"): ?>
                    <div class="payment-section"><h3>Payment</h3><span class="payment-status <?php echo $payment_status === "Paid" ? "paid" : "unpaid"; ?>"><?php echo htmlspecialchars($payment_status); ?></span><p><?php echo $payment_status === "Paid" ? "Your cash payment has been marked as received because your order is completed." : "You selected cash payment. Please pay upon delivery or pick-up of your order."; ?></p></div>
                <?php elseif($payment_method === "GCash" && $status_lower === "approved" && $fee_is_set): ?>
                    <div class="payment-section"><h3>Payment</h3>
                        <?php if($payment_status === "Paid"): ?><span class="payment-status paid">Paid</span><p>Your payment has been verified by the admin.</p>
                        <?php elseif($payment_status === "Pending Verification"): ?><span class="payment-status verification">Pending Verification</span><p>Your payment proof has been submitted. Please wait while the admin verifies your payment.</p>
                        <?php elseif($payment_status === "Rejected"): ?><span class="payment-status rejected">Payment Rejected</span><?php if(!empty($order["payment_rejection_reason"])): ?><div class="payment-rejection"><strong>Reason:</strong><br><?php echo nl2br(htmlspecialchars($order["payment_rejection_reason"])); ?></div><?php endif; ?><p>Please submit a new payment proof to continue.</p>
                        <?php else: ?><span class="payment-status unpaid">Unpaid</span><p>Your order has been approved and the final amount has been confirmed. You may now proceed with your GCash payment.</p><?php endif; ?>
                        <?php if($can_pay): ?><a href="payment.php?type=regular&amp;id=<?php echo (int)$order["id"]; ?>" class="pay-btn"><?php echo ($order["payment_status"] ?? "") === "Rejected" ? "Resubmit Payment" : "Pay via GCash"; ?></a><?php endif; ?>
                        <?php if($payment_status !== "Paid"): ?><div class="payment-reminder"><strong>Payment Reminder</strong>Your order will not be processed or marked as completed until your GCash payment has been submitted and verified by the admin.</div><?php endif; ?>
                    </div>
                <?php elseif($payment_method === "GCash"): ?>
                    <div class="payment-section"><h3>Payment</h3><span class="payment-status <?php echo $payment_status === "Paid" ? "paid" : ($payment_status === "Pending Verification" ? "verification" : "unpaid"); ?>"><?php echo htmlspecialchars($payment_status); ?></span><p>GCash payment will be available once your order is approved and the final amount is set.</p></div>
                <?php endif; ?>

                <?php if($status_lower === "pending"): ?><div class="order-actions"><form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');"><input type="hidden" name="order_id" value="<?php echo (int)$order["id"]; ?>"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"><button type="submit" name="cancel_order" class="cancel-btn">Cancel order</button></form></div><?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty"><h2>No regular orders yet</h2><p>You haven't ordered any ready-made cakes yet.</p><a href="cakes.php" class="browse-btn">Browse cakes</a></div>
    <?php endif; ?>

    <h2 class="section-title spaced" id="custom">Custom cake requests</h2>
    <?php if($custom_requests->num_rows > 0): ?>
        <?php while($custom = $custom_requests->fetch_assoc()): ?>
            <?php
            $custom_status_raw = trim((string)($custom["request_status"] ?? "Pending"));
            $custom_status = strtolower($custom_status_raw);
            $custom_status_class = order_status_class($custom_status_raw);
            $custom_payment_method = $custom["payment_method"] ?? "";
            $custom_payment_status = $custom["payment_status"] ?? "Unpaid";
            // Display Cash as Paid for completed custom requests, even if DB payment_status was not updated.
            if($custom_payment_method === "Cash" && $custom_status === "completed") $custom_payment_status = "Paid";
            $has_quote = ($custom_status === "approved" && $custom["quoted_price"] !== null && $custom["quoted_price"] !== "");
            $custom_can_pay = ($custom_payment_method === "GCash" && $custom_status === "approved" && $has_quote && in_array($custom["payment_status"] ?? "Unpaid", ["Unpaid","Rejected"], true));
            $image_name = basename((string)($custom["design_image"] ?? ""));
            $image_path = "../assets/images/custom_requests/".rawurlencode($image_name);
            $custom_method = $custom["delivery_method"] ?? "";
            $custom_pickup = is_pickup_method($custom_method);
            ?>
            <div class="order-card">
                <div class="order-header"><div><h2>Custom cake request</h2><p class="order-number">Request #<?php echo (int)$custom["id"]; ?></p></div><span class="status <?php echo htmlspecialchars($custom_status_class); ?>"><?php echo htmlspecialchars(order_status_label($custom_status_raw)); ?></span></div>
                <?php if($custom_status === "ready for pickup"): ?><div class="status-notice ready"><strong>🎂 Your custom cake is ready for pickup!</strong>You may now collect your customized cake at CakeCraft.</div>
                <?php elseif($custom_status === "out for delivery"): ?><div class="status-notice delivery"><strong>🚚 Your custom cake is out for delivery!</strong>Your customized cake is on its way to your provided address.</div>
                <?php elseif($custom_status === "completed"): ?><div class="status-notice completed"><strong>🎉 Your custom order is completed!</strong><?php echo $custom_pickup ? "Your custom cake has been marked as picked up. Thank you for choosing CakeCraft!" : "Your custom cake has been marked as successfully delivered. Thank you for choosing CakeCraft!"; ?></div><?php endif; ?>

                <div class="custom-content-grid">
                    <div class="image-container"><p class="detail-label">Your uploaded cake design</p><?php if($image_name !== ""): ?><a href="<?php echo htmlspecialchars($image_path); ?>" target="_blank" rel="noopener"><img src="<?php echo htmlspecialchars($image_path); ?>" alt="Your custom cake design" class="design-image" loading="lazy"></a><?php else: ?><p class="detail-value">No design image uploaded.</p><?php endif; ?></div>
                    <div class="details-grid custom-details-grid">
                        <div><p class="detail-label">Customer name</p><p class="detail-value"><?php echo htmlspecialchars($custom["full_name"] ?? "Not available"); ?></p></div>
                        <div><p class="detail-label">Contact number</p><p class="detail-value"><?php echo htmlspecialchars($custom["phone"] ?? "Not available"); ?></p></div>
                        <div><p class="detail-label">Payment method</p><p class="detail-value"><?php echo htmlspecialchars($custom_payment_method ?: "Not specified"); ?></p></div>
                        <div><p class="detail-label">Cake size</p><p class="detail-value"><?php echo htmlspecialchars($custom["cake_size"] ?? "Not specified"); ?></p></div>
                        <div><p class="detail-label">Flavor</p><p class="detail-value"><?php echo htmlspecialchars($custom["flavor"] ?? "Not specified"); ?></p></div>
                        <div><p class="detail-label">Preferred color</p><p class="detail-value"><?php echo htmlspecialchars($custom["color"] ?? "Not specified"); ?></p></div>
                        <div><p class="detail-label">Quantity</p><p class="detail-value"><?php echo (int)$custom["quantity"]; ?></p></div>
                        <div><p class="detail-label">Preferred date</p><p class="detail-value"><?php echo !empty($custom["preferred_date"]) ? date("F d, Y",strtotime($custom["preferred_date"])) : "Not specified"; ?></p></div>
                        <div><p class="detail-label">Request date</p><p class="detail-value"><?php echo !empty($custom["created_at"]) ? date("F d, Y",strtotime($custom["created_at"])) : "Not available"; ?></p></div>
                        <div class="span-2"><p class="detail-label">Delivery address</p><p class="detail-value"><?php echo $custom_pickup ? "No delivery address required — Customer will pick up the order." : nl2br(htmlspecialchars($custom["delivery_address"] ?? "Not specified")); ?></p></div>
                        <div class="span-2"><p class="detail-label">Additional instructions</p><p class="detail-value"><?php echo !empty($custom["special_instructions"]) ? nl2br(htmlspecialchars($custom["special_instructions"])) : "None"; ?></p></div>
                    </div>
                </div>

                <div class="total"><span>Quoted price</span><?php if($has_quote): ?><strong>₱<?php echo number_format((float)$custom["quoted_price"],2); ?></strong><?php else: ?><strong class="price-pending">Awaiting quotation</strong><?php endif; ?></div>

                <?php if($custom_payment_method === "Cash"): ?>
                    <div class="payment-section"><h3>Payment</h3><span class="payment-status <?php echo $custom_payment_status === "Paid" ? "paid" : "unpaid"; ?>"><?php echo htmlspecialchars($custom_payment_status); ?></span><p><?php echo $custom_payment_status === "Paid" ? "Your cash payment has been marked as received because your custom order is completed." : "You selected cash payment. Please pay upon delivery or pick-up of your custom cake."; ?></p></div>
                <?php elseif($custom_payment_method === "GCash" && $has_quote): ?>
                    <div class="payment-section"><h3>Payment</h3>
                        <?php if($custom_payment_status === "Paid"): ?><span class="payment-status paid">Paid</span><p>Your custom cake payment has been verified.</p>
                        <?php elseif($custom_payment_status === "Pending Verification"): ?><span class="payment-status verification">Pending Verification</span><p>Your payment proof has been submitted. Please wait for admin verification.</p>
                        <?php elseif($custom_payment_status === "Rejected"): ?><span class="payment-status rejected">Payment Rejected</span><?php if(!empty($custom["payment_rejection_reason"])): ?><div class="payment-rejection"><strong>Reason:</strong><br><?php echo nl2br(htmlspecialchars($custom["payment_rejection_reason"])); ?></div><?php endif; ?><p>Please submit a new payment proof.</p>
                        <?php else: ?><span class="payment-status unpaid">Unpaid</span><p>Your custom cake request has been approved and the quotation is finalized.</p><?php endif; ?>
                        <?php if($custom_can_pay): ?><a href="payment.php?type=custom&amp;id=<?php echo (int)$custom["id"]; ?>" class="pay-btn"><?php echo ($custom["payment_status"] ?? "") === "Rejected" ? "Resubmit Payment" : "Pay via GCash"; ?></a><?php endif; ?>
                        <?php if($custom_payment_status !== "Paid"): ?><div class="payment-reminder"><strong>Payment Reminder</strong>Your customized order will not be processed or marked as completed until your GCash payment has been submitted and verified by the admin.</div><?php endif; ?>
                    </div>
                <?php elseif($custom_payment_method === "GCash"): ?>
                    <div class="payment-section"><h3>Payment</h3><span class="payment-status <?php echo $custom_payment_status === "Paid" ? "paid" : ($custom_payment_status === "Pending Verification" ? "verification" : "unpaid"); ?>"><?php echo htmlspecialchars($custom_payment_status); ?></span><p>GCash payment will be available once your request is approved and the quotation is set.</p></div>
                <?php endif; ?>

                <?php if($custom_status === "pending"): ?><div class="order-actions"><form method="POST" onsubmit="return confirm('Are you sure you want to cancel this custom cake request?');"><input type="hidden" name="request_id" value="<?php echo (int)$custom["id"]; ?>"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>"><button type="submit" name="cancel_custom_request" class="cancel-btn">Cancel request</button></form></div>
                <?php elseif($custom_status === "approved"): ?><div class="notice">Your custom cake request has been approved by the admin. Please check the quoted price above.</div>
                <?php elseif($custom_status === "cancelled"): ?><div class="notice">This custom cake request has been cancelled.</div><?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty"><h2>No custom cake requests yet</h2><p>You haven't submitted any custom cake designs.</p><a href="customize_request.php" class="browse-btn">Customize your cake</a></div>
    <?php endif; ?>
</div>
</body>
</html>
<?php
$stmt->close();
$custom_stmt->close();
?>
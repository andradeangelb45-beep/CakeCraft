
<?php
session_start();
require_once "../database.php";

// Check customer login
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

$customer_id = (int) $_SESSION["customer_id"];

$cake_id = (int) ($_POST["cake_id"] ?? $_GET["cake_id"] ?? 0);
$size_id = (int) ($_POST["size_id"] ?? $_GET["size_id"] ?? 0);

if ($cake_id <= 0 || $size_id <= 0) {
    header("Location: cakes.php");
    exit();
}

// Get customer information
$stmt = $conn->prepare(
    "SELECT full_name, phone
     FROM customers
     WHERE id = ?"
);

$stmt->bind_param("i", $customer_id);
$stmt->execute();

$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    exit("Customer account not found.");
}

// Get cake information
// Only allow active (non-archived) cakes
$stmt = $conn->prepare(
    "SELECT id, cake_name, category, description, image
     FROM cake_options
     WHERE id = ? AND archived_at IS NULL"
);

$stmt->bind_param("i", $cake_id);
$stmt->execute();

$cake = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Redirect if cake does not exist or is archived
if (!$cake) {
    header("Location: cakes.php");
    exit();
}

// Get selected size and price
// Accept any size saved under the selected cake
$stmt = $conn->prepare(
    "SELECT id, size_name, price
     FROM cake_sizes
     WHERE id = ? AND cake_id = ?"
);

$stmt->bind_param("ii", $size_id, $cake_id);
$stmt->execute();

$size = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Make sure selected size belongs to the selected cake
if (!$size) {
    header("Location: customize.php?cake_id=" . $cake_id);
    exit();
}

// Initialize form values
$error = "";
$success = false;
$order_id = 0;

$customer_name = $customer["full_name"];
$contact_number = $customer["phone"];
$delivery_method = "Delivery";
$delivery_address = "";
$payment_method = "";
$quantity = 1;
$preferred_date = "";
$special_instructions = "";

// Process order submission
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["place_order"])
) {

    $customer_name = trim($_POST["customer_name"] ?? "");
    $contact_number = trim($_POST["contact_number"] ?? "");

    $delivery_method = trim($_POST["delivery_method"] ?? "");
    $delivery_address = trim($_POST["delivery_address"] ?? "");

    $payment_method = trim($_POST["payment_method"] ?? "");

    $quantity = (int) ($_POST["quantity"] ?? 1);
    $preferred_date = trim($_POST["preferred_date"] ?? "");

    $special_instructions = trim(
        $_POST["special_instructions"] ?? ""
    );

    // Validate delivery method
    if (!in_array($delivery_method, ["Delivery", "Pick-up"], true)) {
        $error = "Please select a valid delivery method.";
    }

    // Validate payment method
    if (
        $error === "" &&
        !in_array($payment_method, ["Cash", "GCash"], true)
    ) {
        $error = "Please select a valid payment method.";
    }

    // Clear address if Pick-up was selected
    if ($delivery_method === "Pick-up") {
        $delivery_address = "";
    }

    // Validate required fields
    if ($error === "") {

        if (
            $customer_name === "" ||
            $contact_number === "" ||
            ($delivery_method === "Delivery" && $delivery_address === "") ||
            $quantity < 1 ||
            $quantity > 100
        ) {

            $error = "Please complete all required fields and enter a valid quantity.";

        } elseif ($preferred_date !== "") {

            // Validate date
            $date_object = DateTime::createFromFormat(
                "Y-m-d",
                $preferred_date
            );

            $date_errors = DateTime::getLastErrors();

            $valid_date = $date_object !== false &&
                $date_object->format("Y-m-d") === $preferred_date &&
                (
                    $date_errors === false ||
                    (
                        $date_errors["warning_count"] === 0 &&
                        $date_errors["error_count"] === 0
                    )
                );

            if (!$valid_date || $preferred_date < date("Y-m-d")) {
                $error = "Please select a valid future date.";
            }
        }
    }

    // Continue only if validation passed
    if ($error === "") {

        $unit_price = (float) $size["price"];
        $total_price = $unit_price * $quantity;

        $date_value = $preferred_date === ""
            ? null
            : $preferred_date;

        $instructions_value = $special_instructions === ""
            ? null
            : $special_instructions;

        // Delivery fee
        // Delivery = NULL (admin assigns fee later)
        // Pick-up = 0.00
        $delivery_fee = ($delivery_method === "Pick-up")
            ? 0.00
            : null;

        // Insert order including selected payment method
        $stmt = $conn->prepare(
            "INSERT INTO orders
            (
                customer_id,
                customer_name,
                contact_number,
                delivery_method,
                delivery_address,
                cake_id,
                cake_size_id,
                customization,
                total_price,
                quantity,
                delivery_fee,
                preferred_date,
                special_instructions,
                payment_method,
                payment_status,
                order_status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid', 'Pending')"
        );

        $stmt->bind_param(
            "issssiisdidsss",
            $customer_id,
            $customer_name,
            $contact_number,
            $delivery_method,
            $delivery_address,
            $cake_id,
            $size_id,
            $instructions_value,
            $total_price,
            $quantity,
            $delivery_fee,
            $date_value,
            $instructions_value,
            $payment_method
        );

        if ($stmt->execute()) {

            $order_id = $conn->insert_id;
            $success = true;

        } else {

            $error = "Unable to place your order. Please try again.";

        }

        $stmt->close();
    }
}

$image_path = "../assets/images/" . $cake["image"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Order Details | CakeCraft</title>

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
    --line-strong: #e6d3cc;
    --error-bg: #f9e9e7;
    --error-ink: #963f3b;
    --ok-bg: #edf5ed;
    --ok-ink: #426c4e;

    --font-body: 'DM Sans', system-ui, sans-serif;
    --font-display: 'Playfair Display', Georgia, serif;

    --gutter: max(6%, calc((100vw - 1180px) / 2));

    --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
    --ease: cubic-bezier(.2, .7, .2, 1);
}

*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

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

    padding: 14px var(--gutter);

    background: rgba(255, 250, 247, 0.96);
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
    letter-spacing: -0.01em;
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
    transition: color .2s var(--ease);
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
    transition: transform .25s var(--ease);
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
    transition: background .2s var(--ease), color .2s var(--ease), border-color .2s var(--ease);
}

nav a.logout::after {
    display: none;
}

nav a.logout:hover {
    background: var(--bg-soft);
    color: var(--rose-deep);
    border-color: var(--rose-deep);
}

/* CONTENT */

.container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 44px 24px 72px;
}

.back {
    display: inline-block;
    margin-bottom: 22px;
    color: var(--rose-deep);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}

.back:hover {
    color: var(--rose-darker);
    text-decoration: underline;
    text-underline-offset: 3px;
}

h1 {
    font-family: var(--font-display);
    font-size: clamp(1.9rem, 4vw, 2.6rem);
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: -0.015em;
    margin-bottom: 10px;
}

h2 {
    font-family: var(--font-display);
    font-weight: 700;
}

.subtitle {
    color: var(--ink-soft);
    font-size: 17px;
    margin-bottom: 32px;
}

.layout {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 32px;
    align-items: start;
}

.card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 24px;
    box-shadow: var(--shadow);
}

.card > h2 {
    font-size: 22px;
    margin-bottom: 22px;
}

/* ORDER SUMMARY */

.summary {
    position: sticky;
    top: 96px;
    padding: 0;
    overflow: hidden;
    margin-bottom: 0;
}

.summary img {
    display: block;
    width: 100%;
    height: 220px;
    object-fit: cover;
}

.summary-body {
    padding: 24px;
}

.summary-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: var(--rose-deep);
    margin-bottom: 4px;
}

.summary h2 {
    font-size: 24px;
    line-height: 1.25;
    margin-bottom: 14px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 0;
    border-top: 1px solid var(--line);
    font-size: 15px;
    color: var(--ink-soft);
}

.summary-row strong {
    color: var(--ink);
    font-weight: 600;
}

/* FORM */

.form-group {
    margin-bottom: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

label {
    display: block;
    margin-bottom: 7px;
    font-size: 14px;
    font-weight: 600;
}

.optional {
    font-weight: 400;
    color: var(--ink-soft);
}

input,
textarea,
select {
    width: 100%;
    padding: 13px 14px;
    font-family: inherit;
    font-size: 16px;
    color: var(--ink);
    background: var(--bg);
    border: 1.5px solid var(--line-strong);
    border-radius: 10px;

    transition:
        border-color .2s var(--ease),
        box-shadow .2s var(--ease),
        background .2s var(--ease);
}

input::placeholder,
textarea::placeholder {
    color: var(--ink-soft);
    opacity: 0.7;
}

input:hover,
textarea:hover,
select:hover {
    border-color: #dfc2bc;
}

input:focus,
textarea:focus,
select:focus {
    outline: none;
    background: var(--surface);
    border-color: var(--rose-deep);
    box-shadow: 0 0 0 4px rgba(196, 124, 120, 0.22);
}

textarea {
    min-height: 110px;
    resize: vertical;
    line-height: 1.6;
}

/* PAYMENT OPTIONS */

.payment-note {
    margin-top: 10px;
    padding: 13px 15px;
    background: var(--bg-soft);
    border: 1px solid var(--line);
    border-radius: 10px;
    color: var(--ink-soft);
    font-size: 13px;
    line-height: 1.6;
}

.payment-note strong {
    color: var(--ink);
}

.total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-soft);
    border: 1px solid var(--line);
    padding: 20px 22px;
    border-radius: 12px;
    font-weight: 500;
}

.total strong {
    font-family: var(--font-display);
    font-size: 30px;
    font-weight: 700;
    color: var(--rose-deep);
}

button,
.btn {
    display: block;
    width: 100%;
    padding: 15px;
    font-family: inherit;
    font-size: 16px;
    font-weight: 600;
    text-align: center;
    text-decoration: none;
    color: #fff;
    background: var(--rose-deep);
    border: none;
    border-radius: 999px;
    cursor: pointer;

    box-shadow: 0 8px 20px rgba(166, 90, 86, 0.28);

    transition:
        background .2s var(--ease),
        transform .2s var(--ease),
        box-shadow .2s var(--ease);
}

button {
    margin-top: 20px;
}

button:hover,
.btn:hover {
    background: var(--rose-darker);
    transform: translateY(-1px);
    box-shadow: 0 12px 26px rgba(166, 90, 86, 0.34);
}

.btn.secondary {
    color: var(--ink);
    background: var(--bg-soft);
    border: 1.5px solid var(--rose);
    box-shadow: none;
}

.btn.secondary:hover {
    background: var(--bg-tint);
    color: var(--rose-deep);
    transform: none;
}

/* MESSAGES */

.error {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    background: var(--error-bg);
    color: var(--error-ink);
    border: 1px solid #efd1cd;
    border-left: 4px solid var(--error-ink);
    padding: 12px 14px;
    margin-bottom: 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
}

.error svg {
    flex: none;
    width: 18px;
    height: 18px;
    margin-top: 2px;
}

/* SUCCESS */

.success {
    max-width: 520px;
    margin: 40px auto 0;
    text-align: center;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 20px;
    padding: 44px 36px 36px;
    box-shadow: 0 14px 40px rgba(73, 48, 45, 0.10);
}

.success .check {
    display: grid;
    place-items: center;
    width: 68px;
    height: 68px;
    margin: 0 auto 22px;
    border-radius: 50%;
    background: var(--ok-bg);
    color: var(--ok-ink);
}

.success .check svg {
    width: 34px;
    height: 34px;
}

.success h1 {
    font-size: 30px;
}

.success > p {
    color: var(--ink-soft);
}

.order-meta {
    margin: 26px 0 28px;
    padding: 18px 20px;
    background: var(--bg-soft);
    border: 1px solid var(--line);
    border-radius: 12px;
    text-align: left;
}

.order-meta div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 6px 0;
}

.order-meta span {
    color: var(--ink-soft);
    font-size: 15px;
}

.order-meta strong {
    font-weight: 600;
}

.status {
    padding: 3px 12px;
    border-radius: 999px;
    background: var(--bg-tint);
    color: var(--rose-darker);
    font-size: 13px;
    font-weight: 700;
}

.success .btn + .btn {
    margin-top: 12px;
}

/* RESPONSIVE */

@media (max-width: 900px) {
    .layout {
        grid-template-columns: 1fr;
    }

    .summary {
        position: static;
        order: -1;
        margin-bottom: 24px;
    }

    .summary img {
        height: 200px;
    }
}

@media (max-width: 700px) {
    header {
        flex-direction: column;
        gap: 12px;
        padding: 14px 6%;
    }

    nav {
        flex-wrap: wrap;
        justify-content: center;
        gap: 8px 20px;
    }

    .container {
        padding: 32px 16px 56px;
    }

    .card {
        padding: 22px 18px;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .success {
        padding: 36px 22px 28px;
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
        <a href="cakes.php" class="active">Cakes</a>
        <a href="my_orders.php">My Orders</a>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</header>

<div class="container">

<?php if ($success): ?>

    <div class="success" role="status">

        <div class="check" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12.5l4.5 4.5L19 7.5"/>
            </svg>
        </div>

        <h1>Order placed successfully!</h1>

        <p>Your order has been saved. We'll review it and be in touch.</p>

        <div class="order-meta">

            <div>
                <span>Order number</span>
                <strong>#<?php echo $order_id; ?></strong>
            </div>

            <div>
                <span>Delivery method</span>
                <strong><?php echo htmlspecialchars($delivery_method); ?></strong>
            </div>

            <div>
                <span>Payment method</span>
                <strong><?php echo htmlspecialchars($payment_method); ?></strong>
            </div>

            <div>
                <span>Status</span>
                <strong class="status">Pending</strong>
            </div>

        </div>

        <a href="my_orders.php" class="btn">View my orders</a>
        <a href="dashboard.php" class="btn secondary">Back to dashboard</a>

    </div>

<?php else: ?>

    <a href="customize.php?cake_id=<?php echo $cake_id; ?>" class="back">
        ← Back to size selection
    </a>

    <h1>Order details</h1>

    <p class="subtitle">
        Please complete your information to place your cake order.
    </p>

    <?php if ($error !== ""): ?>

        <div class="error" role="alert">
            <svg viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 7.5v5M12 16h.01"/>
            </svg>

            <span><?php echo htmlspecialchars($error); ?></span>
        </div>

    <?php endif; ?>

    <div class="layout">

        <form method="POST" action="order_details.php">

            <input type="hidden" name="cake_id"
                   value="<?php echo $cake_id; ?>">

            <input type="hidden" name="size_id"
                   value="<?php echo $size_id; ?>">

            <!-- CUSTOMER INFORMATION -->

            <div class="card">

                <h2>Customer information</h2>

                <div class="form-row">

                    <div class="form-group">
                        <label for="customer_name">Customer name</label>

                        <input
                            type="text"
                            id="customer_name"
                            name="customer_name"
                            autocomplete="name"
                            value="<?php echo htmlspecialchars($customer_name); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="contact_number">Contact number</label>

                        <input
                            type="tel"
                            id="contact_number"
                            name="contact_number"
                            autocomplete="tel"
                            value="<?php echo htmlspecialchars($contact_number); ?>"
                            required
                        >
                    </div>

                </div>

                <!-- DELIVERY METHOD -->

                <div class="form-group">

                    <label for="delivery_method">Delivery method</label>

                    <select
                        id="delivery_method"
                        name="delivery_method"
                        required
                    >
                        <option value="Delivery"
                            <?php echo $delivery_method === "Delivery" ? "selected" : ""; ?>>
                            Delivery
                        </option>

                        <option value="Pick-up"
                            <?php echo $delivery_method === "Pick-up" ? "selected" : ""; ?>>
                            Pick-up
                        </option>
                    </select>

                </div>

                <!-- DELIVERY ADDRESS -->

                <div class="form-group" id="addressGroup">

                    <label for="delivery_address">
                        Delivery location / address
                    </label>

                    <textarea
                        id="delivery_address"
                        name="delivery_address"
                        autocomplete="street-address"
                        placeholder="Enter your complete delivery address"
                    ><?php echo htmlspecialchars($delivery_address); ?></textarea>

                </div>

                <!-- PAYMENT METHOD -->

                <div class="form-group">

                    <label for="payment_method">Payment method</label>

                    <select
                        id="payment_method"
                        name="payment_method"
                        required
                    >
                        <option value="" disabled
                            <?php echo $payment_method === "" ? "selected" : ""; ?>>
                            Select payment method
                        </option>

                        <option value="Cash"
                            <?php echo $payment_method === "Cash" ? "selected" : ""; ?>>
                            Cash
                        </option>

                        <option value="GCash"
                            <?php echo $payment_method === "GCash" ? "selected" : ""; ?>>
                            GCash
                        </option>
                    </select>

                    <div class="payment-note" id="paymentNote">
                        Please select your preferred payment method.
                    </div>

                </div>

            </div>

            <!-- ORDER INFORMATION -->

            <div class="card">

                <h2>Order information</h2>

                <div class="form-row">

                    <div class="form-group">

                        <label for="quantity">Quantity</label>

                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            min="1"
                            max="100"
                            inputmode="numeric"
                            value="<?php echo htmlspecialchars((string)$quantity); ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="preferred_date">
                            Preferred date
                            <span class="optional">(optional)</span>
                        </label>

                        <input
                            type="date"
                            id="preferred_date"
                            name="preferred_date"
                            min="<?php echo date('Y-m-d'); ?>"
                            value="<?php echo htmlspecialchars($preferred_date); ?>"
                        >

                    </div>

                </div>

                <div class="form-group">

                    <label for="special_instructions">
                        Special instructions
                        <span class="optional">(optional)</span>
                    </label>

                    <textarea
                        id="special_instructions"
                        name="special_instructions"
                        placeholder="Example: Write Happy Birthday on the cake..."
                    ><?php echo htmlspecialchars($special_instructions); ?></textarea>

                </div>

                <!-- CAKE SUBTOTAL -->

                <div class="total">

                    <span>Cake subtotal</span>

                    <strong id="totalPrice" aria-live="polite">
                        ₱<?php echo number_format(
                            (float)$size["price"] * $quantity,
                            2
                        ); ?>
                    </strong>

                </div>

                <!-- DELIVERY FEE NOTICE -->

                <p id="deliveryFeeNote"
                   style="font-size:13px;color:var(--ink-soft);margin-top:10px;">

                    Delivery fee will be determined by the admin upon approval of your order.

                </p>

                <button type="submit" name="place_order">
                    Place order
                </button>

            </div>

        </form>

        <!-- CAKE SUMMARY -->

        <aside class="card summary" aria-label="Order summary">

            <?php if (!empty($cake["image"]) && file_exists($image_path)): ?>

                <img
                    src="<?php echo htmlspecialchars($image_path); ?>"
                    alt="<?php echo htmlspecialchars($cake["cake_name"]); ?>"
                >

            <?php endif; ?>

            <div class="summary-body">

                <p class="summary-label">YOUR CAKE</p>

                <h2><?php echo htmlspecialchars($cake["cake_name"]); ?></h2>

                <div class="summary-row">
                    <span>Size</span>
                    <strong><?php echo htmlspecialchars($size["size_name"]); ?></strong>
                </div>

                <div class="summary-row">
                    <span>Price each</span>
                    <strong>₱<?php echo number_format((float)$size["price"], 2); ?></strong>
                </div>

            </div>

        </aside>

    </div>

<?php endif; ?>

</div>

<script>

// QUANTITY PRICE CALCULATION

const quantityInput = document.querySelector('input[name="quantity"]');
const totalPrice = document.getElementById("totalPrice");

if (quantityInput && totalPrice) {

    const unitPrice = <?php echo json_encode((float)$size["price"]); ?>;

    quantityInput.addEventListener("input", function () {

        let quantity = parseInt(this.value) || 0;

        if (quantity < 0) {
            quantity = 0;
        }

        totalPrice.textContent = "₱" +
            (unitPrice * quantity).toLocaleString("en-PH", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

    });
}

// DELIVERY / PICK-UP TOGGLE

const deliveryMethod = document.getElementById("delivery_method");
const addressGroup = document.getElementById("addressGroup");
const deliveryAddress = document.getElementById("delivery_address");
const deliveryFeeNote = document.getElementById("deliveryFeeNote");

function toggleDeliveryAddress() {

    if (!deliveryMethod || !addressGroup || !deliveryAddress) {
        return;
    }

    if (deliveryMethod.value === "Delivery") {

        addressGroup.style.display = "block";
        deliveryAddress.required = true;
        deliveryAddress.disabled = false;

        if (deliveryFeeNote) {
            deliveryFeeNote.textContent =
                "Delivery fee will be determined by the admin upon approval of your order.";
        }

    } else {

        addressGroup.style.display = "none";
        deliveryAddress.required = false;
        deliveryAddress.disabled = true;
        deliveryAddress.value = "";

        if (deliveryFeeNote) {
            deliveryFeeNote.textContent =
                "No delivery fee for pick-up orders.";
        }

    }
}

if (deliveryMethod) {
    deliveryMethod.addEventListener("change", toggleDeliveryAddress);
    toggleDeliveryAddress();
}

// PAYMENT METHOD NOTE

const paymentMethod = document.getElementById("payment_method");
const paymentNote = document.getElementById("paymentNote");

function updatePaymentNote() {

    if (!paymentMethod || !paymentNote) {
        return;
    }

    if (paymentMethod.value === "Cash") {

        paymentNote.innerHTML =
            "<strong>Cash:</strong> You can pay in cash upon delivery or pick-up of your order.";

    } else if (paymentMethod.value === "GCash") {

        paymentNote.innerHTML =
            "<strong>GCash:</strong> Payment will be made after your order is approved and the final amount is confirmed. You will then be able to submit your payment proof.";

    } else {

        paymentNote.textContent =
            "Please select your preferred payment method.";

    }
}

if (paymentMethod) {
    paymentMethod.addEventListener("change", updatePaymentNote);
    updatePaymentNote();
}

</script>

</body>
</html>

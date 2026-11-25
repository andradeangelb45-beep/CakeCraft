
<?php
session_start();
require_once "../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

$allowed_statuses = ["Pending", "Approved", "Ready for Pickup", "Out for Delivery", "Completed", "Cancelled"];
$filter = $_GET["status"] ?? "";
$search = trim($_GET["search"] ?? "");

if (!in_array($filter, $allowed_statuses, true)) {
    $filter = "";
}

function order_return_url($filter, $search, $anchor = "") {
    $params = [];
    $valid_statuses = ["Pending", "Approved", "Ready for Pickup", "Out for Delivery", "Completed", "Cancelled"];

    if (in_array($filter, $valid_statuses, true)) {
        $params["status"] = $filter;
    }

    if ($search !== "") {
        $params["search"] = $search;
    }

    $url = "orders.php" . (!empty($params) ? "?" . http_build_query($params) : "");

    if ($anchor !== "") {
        $url .= "#" . $anchor;
    }

    return $url;
}

function normalize_delivery_method($value) {
    $value = strtolower(trim((string)$value));

    if (in_array($value, ["pickup", "pick-up", "pick up"], true)) {
        return "Pick-up";
    }

    return "Delivery";
}

function status_class_name($status) {
    return strtolower(str_replace(" ", "-", trim((string)$status)));
}

/* ARCHIVE REGULAR ORDER (COMPLETED/CANCELLED ONLY) */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["archive_order"])) {
    $order_id = (int)($_POST["order_id"] ?? 0);

    if ($order_id > 0) {
        $stmt = $conn->prepare("
            UPDATE orders
            SET archived_at = NOW()
            WHERE id = ?
            AND order_status IN ('Completed', 'Cancelled')
            AND archived_at IS NULL
        ");

        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: " . order_return_url(
        $_POST["filter"] ?? "",
        trim($_POST["search"] ?? "")
    ));

    exit();
}

/* ARCHIVE CUSTOM REQUEST (COMPLETED/CANCELLED ONLY) */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["archive_custom_request"])) {
    $request_id = (int)($_POST["request_id"] ?? 0);

    if ($request_id > 0) {
        $stmt = $conn->prepare("
            UPDATE custom_cake_requests
            SET archived_at = NOW()
            WHERE id = ?
            AND request_status IN ('Completed', 'Cancelled')
            AND archived_at IS NULL
        ");

        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: " . order_return_url(
        $_POST["filter"] ?? "",
        trim($_POST["search"] ?? ""),
        "custom-requests"
    ));

    exit();
}

/* UPDATE REGULAR ORDER STATUS + DELIVERY FEE + PAYMENT RESTRICTION + NOTIFICATION */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $order_id = (int)($_POST["order_id"] ?? 0);
    $status = $_POST["order_status"] ?? "";
    $delivery_fee_raw = trim($_POST["delivery_fee"] ?? "");

    $fee_error = false;
    $payment_error = false;

    $processing_statuses = ["Ready for Pickup", "Out for Delivery", "Completed"];

    if ($order_id > 0 && in_array($status, $allowed_statuses, true)) {

        $get_order = $conn->prepare("
            SELECT customer_id, delivery_method, delivery_fee,
                   order_status, payment_method, payment_status
            FROM orders
            WHERE id = ?
            AND archived_at IS NULL
        ");

        $get_order->bind_param("i", $order_id);
        $get_order->execute();

        $existing_order = $get_order->get_result()->fetch_assoc();

        $get_order->close();

        if ($existing_order && $existing_order["order_status"] !== "Cancelled") {

            $method = normalize_delivery_method($existing_order["delivery_method"] ?? "Delivery");
            $payment_method = $existing_order["payment_method"] ?? "Cash";
            $payment_status = $existing_order["payment_status"] ?? "Unpaid";

            // GCash must be submitted and verified as Paid before processing.
            if (
                strtolower(trim((string)$payment_method)) === "gcash" &&
                strtolower(trim((string)$payment_status)) !== "paid" &&
                in_array($status, $processing_statuses, true)
            ) {
                $payment_error = true;
            }

            $fee_to_save = null;

            if ($method === "Pick-up") {

                $fee_to_save = 0.00;

            } else {

                if ($delivery_fee_raw !== "") {

                    if (is_numeric($delivery_fee_raw) && (float)$delivery_fee_raw >= 0) {
                        $fee_to_save = (float)$delivery_fee_raw;
                    } else {
                        $fee_error = true;
                    }

                } else {

                    $fee_to_save = $existing_order["delivery_fee"] !== null
                        ? (float)$existing_order["delivery_fee"]
                        : null;
                }

                if ($status === "Approved" && $fee_to_save === null) {
                    $fee_error = true;
                }
            }

            if (!$fee_error && !$payment_error) {

                $old_status = $existing_order["order_status"];

                $stmt = $conn->prepare("
                    UPDATE orders
                    SET order_status = ?,
                        delivery_fee = ?,
                        payment_status = CASE
                            WHEN ? = 'Completed' AND payment_method = 'Cash'
                            THEN 'Paid'
                            ELSE payment_status
                        END
                    WHERE id = ?
                    AND order_status <> 'Cancelled'
                    AND archived_at IS NULL
                ");

                $stmt->bind_param("sdsi", $status, $fee_to_save, $status, $order_id);
                $stmt->execute();
                $stmt->close();

                if ($old_status !== $status) {

                    $customer_id = (int)$existing_order["customer_id"];

                    $title = "Order #" . $order_id . " Status Updated";

                    switch ($status) {

                        case "Approved":
                            $message = $method === "Delivery"
                                ? "Your cake order #$order_id has been approved! Your delivery fee is ₱" . number_format((float)$fee_to_save, 2) . "."
                                : "Your cake order #$order_id has been approved! Your order is for pick-up.";

                            if ($payment_method === "GCash" && $payment_status !== "Paid") {
                                $message .= " Please complete your GCash payment and wait for verification before your order can be processed.";
                            }
                            break;

                        case "Ready for Pickup":
                            $message = "Your cake order #$order_id is ready for pickup! You may now collect your order.";
                            break;

                        case "Out for Delivery":
                            $message = "Your cake order #$order_id is now out for delivery.";
                            break;

                        case "Completed":
                            $message = "Your cake order #$order_id has been marked as completed. Thank you for ordering from CakeCraft!";
                            break;

                        case "Cancelled":
                            $message = "Your cake order #$order_id has been cancelled.";
                            break;

                        case "Pending":
                            $message = "Your cake order #$order_id is now pending.";
                            break;

                        default:
                            $message = "The status of your cake order #$order_id has been updated to $status.";
                    }

                    $notification_link = "my_orders.php";

                    $notify = $conn->prepare("
                        INSERT INTO notifications
                        (customer_id, order_id, title, message, link)
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    $notify->bind_param(
                        "iisss",
                        $customer_id,
                        $order_id,
                        $title,
                        $message,
                        $notification_link
                    );

                    $notify->execute();
                    $notify->close();
                }
            }
        }
    }

    /*
       REDIRECT FIX:
       If update succeeds, show the newly selected status filter.
       If there's an error, retain the previous filter.
       Completed orders are NOT automatically archived.
    */

    $return_filter = ($fee_error || $payment_error)
        ? ($_POST["filter"] ?? "")
        : $status;

    $return_url = order_return_url(
        $return_filter,
        trim($_POST["search"] ?? "")
    );

    if ($fee_error) {
        $return_url .= (strpos($return_url, "?") === false ? "?" : "&") . "fee_error=1";
    }

    if ($payment_error) {
        $return_url .= (strpos($return_url, "?") === false ? "?" : "&") . "payment_error=1";
    }

    header("Location: " . $return_url);
    exit();
}

/* UPDATE CUSTOM REQUEST QUOTE + STATUS + PAYMENT RESTRICTION + NOTIFICATION */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_custom_request"])) {

    $request_id = (int)($_POST["request_id"] ?? 0);
    $request_status = $_POST["request_status"] ?? "";
    $quote_raw = trim($_POST["quoted_price"] ?? "");

    $quote = $quote_raw !== "" ? (float)$quote_raw : null;

    $valid_quote = $quote_raw === "" ||
        (is_numeric($quote_raw) && (float)$quote_raw >= 0);

    $payment_error = false;

    $processing_statuses = ["Ready for Pickup", "Out for Delivery", "Completed"];

    if (
        $request_id > 0 &&
        in_array($request_status, $allowed_statuses, true) &&
        $valid_quote
    ) {

        if ($request_status !== "Approved" || ($quote !== null && $quote > 0)) {

            $get_request = $conn->prepare("
                SELECT customer_id, request_status, quoted_price,
                       payment_method, payment_status
                FROM custom_cake_requests
                WHERE id = ?
                AND archived_at IS NULL
            ");

            $get_request->bind_param("i", $request_id);
            $get_request->execute();

            $existing_request = $get_request->get_result()->fetch_assoc();

            $get_request->close();

            if ($existing_request) {

                $custom_payment_method = $existing_request["payment_method"] ?? "Cash";
                $custom_payment_status = $existing_request["payment_status"] ?? "Unpaid";

                if (
                    strtolower(trim((string)$custom_payment_method)) === "gcash" &&
                    strtolower(trim((string)$custom_payment_status)) !== "paid" &&
                    in_array($request_status, $processing_statuses, true)
                ) {
                    $payment_error = true;
                }

                if (!$payment_error) {

                    $old_status = $existing_request["request_status"];
                    $old_quote = $existing_request["quoted_price"];

                    $stmt = $conn->prepare("
                        UPDATE custom_cake_requests
                        SET quoted_price = ?,
                            request_status = ?,
                            payment_status = CASE
                                WHEN ? = 'Completed' AND payment_method = 'Cash'
                                THEN 'Paid'
                                ELSE payment_status
                            END
                        WHERE id = ?
                        AND archived_at IS NULL
                    ");

                    $stmt->bind_param("dssi", $quote, $request_status, $request_status, $request_id);
                    $stmt->execute();
                    $stmt->close();

                    $customer_id = (int)$existing_request["customer_id"];

                    $status_changed = $old_status !== $request_status;

                    $quote_changed = $quote !== null &&
                        ($old_quote === null || (float)$old_quote !== (float)$quote);

                    if (
                        $customer_id > 0 &&
                        ($status_changed || ($request_status === "Approved" && $quote_changed))
                    ) {

                        $title = "Custom Cake Request #$request_id Updated";

                        if ($request_status === "Approved") {

                            $message = "Your customized cake request #$request_id has been approved!";

                            if ($quote !== null) {
                                $message .= " Your quotation is ₱" . number_format($quote, 2) . ".";
                            }

                            if ($custom_payment_method === "GCash" && $custom_payment_status !== "Paid") {
                                $message .= " Please complete your GCash payment and wait for verification before your order can be processed.";
                            }

                        } elseif ($request_status === "Ready for Pickup") {

                            $message = "Your customized cake request #$request_id is ready for pickup! You may now collect your order.";

                        } elseif ($request_status === "Out for Delivery") {

                            $message = "Your customized cake request #$request_id is now out for delivery.";

                        } elseif ($request_status === "Completed") {

                            $message = "Your customized cake request #$request_id has been marked as completed. Thank you for choosing CakeCraft!";

                        } elseif ($request_status === "Cancelled") {

                            $message = "Your customized cake request #$request_id has been cancelled.";

                        } elseif ($request_status === "Pending") {

                            $message = "Your customized cake request #$request_id is now pending.";

                        } else {

                            $message = "Your customized cake request #$request_id status has been updated to $request_status.";
                        }

                        $notification_link = "my_orders.php";

                        $notify = $conn->prepare("
                            INSERT INTO notifications
                            (customer_id, order_id, title, message, link)
                            VALUES (?, NULL, ?, ?, ?)
                        ");

                        $notify->bind_param(
                            "isss",
                            $customer_id,
                            $title,
                            $message,
                            $notification_link
                        );

                        $notify->execute();
                        $notify->close();
                    }
                }
            }
        }
    }

    /*
       REDIRECT FIX FOR CUSTOM REQUESTS:
       Show the newly selected status after a successful update.
       Keep the previous filter if GCash payment verification is required.
    */

    $return_filter = $payment_error
        ? ($_POST["filter"] ?? "")
        : $request_status;

    $return_url = order_return_url(
        $return_filter,
        trim($_POST["search"] ?? "")
    );

    if ($payment_error) {
        $return_url .= (strpos($return_url, "?") === false ? "?" : "&") . "payment_error=1";
    }

    $return_url .= "#custom-requests";

    header("Location: " . $return_url);
    exit();
}

/* COUNTS */
$counts = array_fill_keys($allowed_statuses, 0);
$total_count = 0;

$regular_count_result = $conn->query("
    SELECT order_status, COUNT(*) AS total
    FROM orders
    WHERE archived_at IS NULL
    GROUP BY order_status
");

if ($regular_count_result) {
    while ($row = $regular_count_result->fetch_assoc()) {

        $n = (int)$row["total"];

        $total_count += $n;

        if (isset($counts[$row["order_status"]])) {
            $counts[$row["order_status"]] += $n;
        }
    }
}

$custom_count_result = $conn->query("
    SELECT request_status, COUNT(*) AS total
    FROM custom_cake_requests
    WHERE archived_at IS NULL
    GROUP BY request_status
");

if ($custom_count_result) {
    while ($row = $custom_count_result->fetch_assoc()) {

        $n = (int)$row["total"];

        $total_count += $n;

        if (isset($counts[$row["request_status"]])) {
            $counts[$row["request_status"]] += $n;
        }
    }
}

/*
========================================================
REGULAR ORDERS QUERY - UPDATED
========================================================
LEFT JOIN is used so restored orders remain visible even
if their customer or cake reference is missing.
*/

$base_sql = "
    SELECT orders.*,
           customers.email,
           cake_options.cake_name,
           cake_sizes.size_name
    FROM orders
    LEFT JOIN customers
        ON orders.customer_id = customers.id
    LEFT JOIN cake_options
        ON orders.cake_id = cake_options.id
    LEFT JOIN cake_sizes
        ON orders.cake_size_id = cake_sizes.id
";

$conditions = ["orders.archived_at IS NULL"];
$params = [];
$types = "";

if ($filter !== "") {
    $conditions[] = "orders.order_status = ?";
    $params[] = $filter;
    $types .= "s";
}

if ($search !== "") {

    $conditions[] = "(
        CAST(orders.id AS CHAR) LIKE ?
        OR orders.customer_name LIKE ?
        OR cake_options.cake_name LIKE ?
        OR orders.contact_number LIKE ?
        OR customers.email LIKE ?
    )";

    $sv = "%" . $search . "%";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $sv;
    }

    $types .= "sssss";
}

$sql = $base_sql .
    " WHERE " . implode(" AND ", $conditions) .
    " ORDER BY orders.created_at DESC";

if (!empty($params)) {

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $result = $stmt->get_result();

} else {

    $result = $conn->query($sql);
}

/* CUSTOM REQUESTS QUERY */
$custom_conditions = ["custom_cake_requests.archived_at IS NULL"];
$custom_params = [];
$custom_types = "";

if ($filter !== "") {
    $custom_conditions[] = "custom_cake_requests.request_status = ?";
    $custom_params[] = $filter;
    $custom_types .= "s";
}

if ($search !== "") {

    $custom_conditions[] = "(
        CAST(custom_cake_requests.id AS CHAR) LIKE ?
        OR customers.full_name LIKE ?
        OR customers.email LIKE ?
        OR customers.phone LIKE ?
        OR custom_cake_requests.cake_size LIKE ?
        OR custom_cake_requests.flavor LIKE ?
        OR custom_cake_requests.color LIKE ?
    )";

    $sv = "%" . $search . "%";

    for ($i = 0; $i < 7; $i++) {
        $custom_params[] = $sv;
    }

    $custom_types .= "sssssss";
}

$custom_sql = "
    SELECT custom_cake_requests.*,
           customers.full_name,
           customers.email,
           customers.phone
    FROM custom_cake_requests
    LEFT JOIN customers
    ON custom_cake_requests.customer_id = customers.id
" .
(!empty($custom_conditions) ? " WHERE " . implode(" AND ", $custom_conditions) : "") .
" ORDER BY custom_cake_requests.created_at DESC";

if (!empty($custom_params)) {

    $custom_stmt = $conn->prepare($custom_sql);
    $custom_stmt->bind_param($custom_types, ...$custom_params);
    $custom_stmt->execute();

    $custom_result = $custom_stmt->get_result();

} else {

    $custom_result = $conn->query($custom_sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Orders | CakeCraft</title>

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
    --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
    --shadow-lift: 0 14px 34px rgba(73, 48, 45, 0.14);
    --ease: cubic-bezier(.2, .7, .2, 1);
}

*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
    scroll-padding-top: 100px;
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

button, input, select, textarea {
    font: inherit;
}

:focus-visible {
    outline: 3px solid var(--rose);
    outline-offset: 3px;
    border-radius: 4px;
}

/* HEADER / ADMIN BRAND */
header {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 15px 6%;
    background: rgba(255, 250, 247, .96);
    color: var(--ink);
    border-bottom: 1px solid var(--line);
    box-shadow: 0 3px 16px rgba(59, 39, 36, .045);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
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

.brand {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.brand h2 {
    font-family: var(--font-display);
    font-size: 27px;
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -.01em;
    color: var(--ink);
}

.brand h2 span.brand-craft {
    color: var(--craft-color);
}

.badge {
    margin-left: 4px;
    padding: 3px 10px;
    border-radius: 999px;
    background: var(--bg-soft);
    color: var(--rose-darker);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .05em;
}

.header-links {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.header-links a {
    color: var(--ink);
    background: var(--surface);
    text-decoration: none;
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 600;
    padding: 9px 18px;
    border: 1.5px solid var(--line-strong);
    border-radius: 999px;
    transition: color .2s var(--ease), background .2s var(--ease), border-color .2s var(--ease), transform .2s var(--ease);
}

.header-links a:hover {
    color: var(--rose-darker);
    background: var(--bg-soft);
    border-color: var(--rose);
    transform: translateY(-1px);
}

.container {
    max-width: 1180px;
    margin: auto;
    padding: 42px 24px 70px;
}

.back {
    display: inline-block;
    margin-bottom: 18px;
    color: var(--rose-deep);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}

.back:hover {
    color: var(--rose-darker);
    text-decoration: underline;
}

h1, h2, h3, h4 {
    font-family: var(--font-display);
    color: var(--ink);
}

h1 {
    font-size: clamp(1.9rem, 4vw, 2.5rem);
    font-weight: 700;
    line-height: 1.15;
    margin-bottom: 10px;
}

.subtitle {
    color: var(--ink-soft);
    font-size: 16px;
    margin-bottom: 28px;
}

.section-heading {
    margin: 42px 0 8px;
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
}

.section-subtitle {
    color: var(--ink-soft);
    font-size: 15px;
    margin-bottom: 20px;
}

/* SEARCH AND FILTERS */
.search-form {
    display: flex;
    gap: 10px;
    margin-bottom: 18px;
    max-width: 720px;
}

.search-form input {
    flex: 1;
    min-width: 0;
    padding: 12px 16px;
    font-family: var(--font-body);
    font-size: 14px;
    border: 1.5px solid var(--line-strong);
    border-radius: 10px;
    background: var(--surface);
    color: var(--ink);
}

.search-form input:focus,
select:focus,
input[type=number]:focus {
    outline: 2px solid var(--rose);
    outline-offset: 2px;
    border-color: var(--rose);
}

button {
    padding: 11px 20px;
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    background: var(--rose-deep);
    border: 0;
    border-radius: 999px;
    cursor: pointer;
    transition: background .2s var(--ease), transform .2s var(--ease), box-shadow .2s var(--ease);
}

button:hover {
    background: var(--rose-darker);
    box-shadow: 0 5px 14px rgba(73, 48, 45, .12);
    transform: translateY(-1px);
}

.clear-search {
    align-self: center;
    color: var(--rose-deep);
    text-decoration: none;
    font-weight: 600;
    padding: 0 8px;
    font-size: 14px;
}

.clear-search:hover {
    color: var(--rose-darker);
    text-decoration: underline;
}

.filters {
    display: flex;
    gap: 10px;
    margin-bottom: 26px;
    overflow-x: auto;
    padding: 4px 2px 8px;
}

.filters a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    padding: 9px 16px;
    border: 1.5px solid var(--line-strong);
    border-radius: 999px;
    background: var(--surface);
    color: var(--ink);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: color .2s, background .2s, border-color .2s;
}

.filters a:hover {
    border-color: var(--rose);
    background: var(--bg-soft);
    color: var(--rose-darker);
}

.filters a.active {
    background: var(--rose-deep);
    border-color: var(--rose-deep);
    color: #fff;
}

.count {
    min-width: 24px;
    padding: 0 7px;
    border-radius: 999px;
    background: var(--bg-soft);
    color: var(--ink-soft);
    font-size: 12px;
    font-weight: 600;
    text-align: center;
}

.filters a.active .count {
    background: rgba(255,255,255,.22);
    color: #fff;
}

/* ORDER TYPE AND DELIVERY LABELS */
.type-label {
    display: inline-block;
    padding: 5px 11px;
    background: var(--bg-soft);
    color: var(--rose-deep);
    border-radius: 7px;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 9px;
    letter-spacing: .02em;
}

.delivery-method {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
}

.delivery-method.delivery {
    background: #fce8e5;
    color: #a65a56;
}

.delivery-method.pickup {
    background: #e8f3e8;
    color: #387345;
}

/* ORDER CARDS */
.card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 22px;
    box-shadow: var(--shadow);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    padding-bottom: 16px;
    margin-bottom: 18px;
    border-bottom: 1px solid var(--line);
}

.card-header h3 {
    font-size: 22px;
    font-weight: 700;
    line-height: 1.3;
}

.sub {
    color: var(--ink-soft);
    font-size: 14px;
}

.status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
    padding: 6px 13px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    background: #fff1d6;
    color: #8a5a00;
}

.status:before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
}

.status.approved {
    background: #e5f3e8;
    color: #1f6a37;
}

.status.ready-for-pickup {
    background: #fff0dc;
    color: #9b641c;
}

.status.out-for-delivery {
    background: #e8e8ff;
    color: #5454a0;
}

.status.completed {
    background: #e3efff;
    color: #1f559a;
}

.status.cancelled {
    background: #fde8e6;
    color: #9b1c1c;
}

.details {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px 22px;
}

.span2 {
    grid-column: span 2;
}

.span3 {
    grid-column: 1 / -1;
}

.label {
    color: var(--ink-soft);
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 2px;
}

.value {
    font-size: 15px;
    font-weight: 600;
    overflow-wrap: anywhere;
    line-height: 1.5;
}

/* PRICE AND STATUS CONTROLS */
.footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-top: 24px;
    padding: 17px 18px;
    background: var(--bg-soft);
    border-radius: 12px;
}

.price small {
    display: block;
    color: var(--ink-soft);
    font-size: 13px;
}

.price strong {
    font-family: var(--font-display);
    font-size: 26px;
    font-weight: 700;
    color: var(--rose-deep);
}

.status-form {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.money-summary {
    min-width: 220px;
}

.money-summary small {
    margin-top: 5px;
}

.money-summary strong {
    display: block;
    font-size: 20px;
}

.money-summary strong:last-child {
    font-size: 26px;
}

.status-form input[type=number] {
    width: 135px;
}

.status-form label {
    font-size: 14px;
    font-weight: 600;
}

select,
input[type=number] {
    padding: 10px 12px;
    font-family: var(--font-body);
    font-size: 14px;
    color: var(--ink);
    border: 1.5px solid var(--line-strong);
    border-radius: 9px;
    background: var(--surface);
}

.empty {
    background: var(--surface);
    border: 1px dashed var(--rose);
    border-radius: 16px;
    padding: 42px 22px;
    text-align: center;
    color: var(--ink-soft);
}

.empty h2 {
    font-size: 23px;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 7px;
}

.empty a {
    display: inline-block;
    margin-top: 12px;
    color: var(--rose-deep);
    font-weight: 600;
}

/* CUSTOM REQUEST */
.custom-request-content {
    display: grid;
    grid-template-columns: minmax(220px, 270px) minmax(0, 1fr);
    gap: 26px;
    align-items: start;
}

.design-panel {
    min-width: 0;
}

.design-frame {
    width: 100%;
    aspect-ratio: 4/5;
    max-height: 340px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: var(--bg-soft);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 7px;
}

.design {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
    border-radius: 9px;
    background: var(--surface);
}

.design-link {
    display: inline-block;
    color: var(--rose-deep);
    font-weight: 600;
    margin-top: 10px;
    font-size: 14px;
}

.custom-details {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px 22px;
}

.custom-details .span2,
.custom-details .span3 {
    grid-column: 1 / -1;
}

.quote-form {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.quote-form label {
    font-weight: 600;
    font-size: 14px;
}

.quote-form input {
    width: 150px;
}

.cancel-note {
    color: #9b1c1c;
    font-weight: 600;
    font-size: 14px;
}

.archive-form {
    margin-left: auto;
}

.archive-button {
    background: var(--ink-soft);
}

.archive-button:hover {
    background: var(--ink);
}

/* PAYMENT DETAILS - COMPACT LAYOUT */
.payment-section {
    margin-top: 22px;
    padding: 17px 20px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--bg);
}

.payment-section h3 {
    font-size: 19px;
    font-weight: 700;
    margin-bottom: 13px;
}

.payment-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px 20px;
    align-items: start;
}

.payment-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.payment-unpaid {
    background: #fff1d6;
    color: #8a5a00;
}

.payment-pending {
    background: #e8efff;
    color: #315a9b;
}

.payment-paid {
    background: #e5f3e8;
    color: #1f6a37;
}

.payment-rejected {
    background: #fde8e6;
    color: #9b1c1c;
}

.payment-proof-item {
    grid-column: 1 / -1;
}

.payment-proof-container {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 7px;
}

.payment-proof {
    display: block;
    width: auto;
    max-width: 120px;
    max-height: 155px;
    object-fit: contain;
    border: 1px solid var(--line);
    border-radius: 9px;
    background: var(--surface);
}

.payment-link {
    display: inline-block;
    color: var(--rose-deep);
    font-weight: 600;
    font-size: 14px;
}

.payment-link:hover {
    color: var(--rose-darker);
    text-decoration: underline;
}

.verify-payment-button {
    display: inline-block;
    margin-top: 12px;
    padding: 11px 18px;
    border: 0;
    border-radius: 999px;
    background: var(--rose-deep);
    color: #fff;
    font-weight: 700;
    text-decoration: none;
    transition: background .2s;
}

.verify-payment-button:hover {
    background: var(--rose-darker);
}

.page-payment-verification {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 18px;
    padding: 11px 17px;
    border-radius: 999px;
    background: var(--rose-deep);
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    box-shadow: 0 2px 5px rgba(0,0,0,.08);
}

.page-payment-verification:hover {
    background: var(--rose-darker);
}

.payment-reason {
    padding: 12px;
    border-radius: 9px;
    background: #fde8e6;
    color: #9b1c1c;
    margin-top: 10px;
    font-size: 14px;
}

@media (max-width: 760px) {
    .container {
        padding: 30px 16px 55px;
    }

    .details {
        grid-template-columns: 1fr 1fr;
    }

    .span2,
    .span3 {
        grid-column: 1 / -1;
    }

    .card {
        padding: 20px 17px;
    }

    .custom-request-content {
        grid-template-columns: minmax(180px, 220px) minmax(0, 1fr);
        gap: 18px;
    }
}

@media (max-width: 600px) {
    .custom-request-content {
        grid-template-columns: 1fr;
    }

    .design-panel {
        max-width: 320px;
    }

    .custom-details {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    header {
        align-items: flex-start;
        gap: 12px;
        flex-direction: column;
        padding: 14px 5%;
    }

    .header-links {
        width: 100%;
    }

    .header-links a {
        padding: 8px 14px;
    }

    .brand .badge {
        display: none;
    }

    .archive-form {
        margin-left: 0;
        width: 100%;
    }

    .archive-form button {
        width: 100%;
    }

    .details,
    .custom-details,
    .payment-grid {
        grid-template-columns: 1fr;
    }

    .custom-details .span2,
    .custom-details .span3 {
        grid-column: 1 / -1;
    }

    .card-header {
        flex-direction: column;
    }

    .footer,
    .status-form,
    .quote-form {
        align-items: stretch;
        flex-direction: column;
    }

    .status-form button,
    .quote-form button {
        width: 100%;
    }

    .search-form {
        flex-wrap: wrap;
    }

    .search-form input {
        flex-basis: 100%;
    }

    .quote-form input,
    .status-form input[type=number],
    .status-form select,
    .quote-form select {
        width: 100%;
    }

    .section-heading {
        font-size: 25px;
    }

    .payment-proof-container {
        align-items: flex-start;
        gap: 12px;
    }

    .payment-proof {
        max-width: 105px;
        max-height: 140px;
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
    <div class="brand">
        <a href="dashboard.php" class="brand-mark" aria-label="CakeCraft Admin Home">

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
        </a>

        <span class="badge">ADMIN</span>
    </div>

    <nav class="header-links" aria-label="Admin navigation">
        <a href="archive_orders.php">Archived Orders</a>
        <a href="dashboard.php">Dashboard</a>
    </nav>
</header>

<div class="container">

<a class="back" href="dashboard.php">&larr; Back to dashboard</a>

<h1>Manage Customer Orders</h1>

<p class="subtitle">
    View and manage regular orders and customized cake requests in CakeCraft.
    Set delivery fees before approving Delivery orders.
</p>

<form method="GET" action="orders.php" class="search-form">

    <?php if ($filter !== ""): ?>
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>">
    <?php endif; ?>

    <input
        type="text"
        name="search"
        placeholder="Search order ID, customer, cake, phone, email, flavor..."
        value="<?php echo htmlspecialchars($search); ?>"
    >

    <button type="submit">Search</button>

    <?php if ($search !== ""): ?>
        <a
            class="clear-search"
            href="orders.php<?php echo $filter !== "" ? "?status=" . urlencode($filter) : ""; ?>"
        >Clear</a>
    <?php endif; ?>

</form>

<nav class="filters" aria-label="Filter all orders">

    <?php
        $all_params = [];

        if ($search !== "") {
            $all_params["search"] = $search;
        }

        $all_url = "orders.php" . ($all_params ? "?" . http_build_query($all_params) : "");
    ?>

    <a
        href="<?php echo htmlspecialchars($all_url); ?>"
        class="<?php echo $filter === "" ? "active" : ""; ?>"
    >
        All Orders
        <span class="count"><?php echo (int)$total_count; ?></span>
    </a>

    <?php foreach ($allowed_statuses as $option): ?>

        <?php
            $filter_params = ["status" => $option];

            if ($search !== "") {
                $filter_params["search"] = $search;
            }

            $filter_url = "orders.php?" . http_build_query($filter_params);
        ?>

        <a
            href="<?php echo htmlspecialchars($filter_url); ?>"
            class="<?php echo $filter === $option ? "active" : ""; ?>"
        >
            <?php echo htmlspecialchars($option); ?>

            <span class="count">
                <?php echo (int)$counts[$option]; ?>
            </span>
        </a>

    <?php endforeach; ?>

</nav>

<?php if (isset($_GET["fee_error"])): ?>

    <div class="empty" style="border-style:solid;margin-bottom:20px;color:#9b1c1c;">
        Please enter a valid delivery fee (₱0.00 or higher) before approving a Delivery order.
    </div>

<?php endif; ?>

<?php if (isset($_GET["payment_error"])): ?>

    <div class="empty" style="border-style:solid;margin-bottom:20px;color:#9b1c1c;background:#fde8e6;">
        <strong>Payment Required!</strong>
        GCash payment must be submitted and verified as Paid before the order can be marked as Ready for Pickup, Out for Delivery, or Completed.
    </div>

<?php endif; ?>

<a href="verify_payments.php" class="page-payment-verification">
    💳 Verify GCash Payments
</a>

<h2 class="section-heading">Regular Cake Orders</h2>

<p class="section-subtitle">
    Standard catalog orders placed by customers.
</p>

<?php if ($result && $result->num_rows > 0): ?>

    <?php while ($order = $result->fetch_assoc()): ?>

        <?php
            $order_status = $order["order_status"] ?? "Pending";

            $is_cancelled = $order_status === "Cancelled";

            $is_archivable = in_array(
                $order_status,
                ["Completed", "Cancelled"],
                true
            );

            $delivery_method = normalize_delivery_method(
                $order["delivery_method"] ?? "Delivery"
            );

            $subtotal = (float)($order["total_price"] ?? 0);

            $delivery_fee_val = $order["delivery_fee"] !== null
                ? (float)$order["delivery_fee"]
                : null;

            $total_amount = $subtotal + ($delivery_fee_val ?? 0);

            $payment_method = $order["payment_method"] ?? "Cash";
            $payment_status = $order["payment_status"] ?? "Unpaid";

            $payment_class = "payment-unpaid";

            if (strtolower($payment_status) === "pending verification") {
                $payment_class = "payment-pending";
            } elseif (strtolower($payment_status) === "paid") {
                $payment_class = "payment-paid";
            } elseif (strtolower($payment_status) === "rejected") {
                $payment_class = "payment-rejected";
            }

            $display_cake_name = !empty($order["cake_name"])
                ? $order["cake_name"]
                : "Cake Order";

            $display_customer_email = !empty($order["email"])
                ? $order["email"]
                : "Email not available";
        ?>

        <div class="card">

            <div class="card-header">

                <div>

                    <span class="type-label">REGULAR ORDER</span>

                    <h3>
                        Order #<?php echo (int)$order["id"]; ?>
                        &mdash;
                        <?php echo htmlspecialchars($display_cake_name); ?>
                    </h3>

                    <div class="sub">
                        Placed on
                        <?php echo htmlspecialchars(date("M d, Y h:i A", strtotime($order["created_at"]))); ?>
                    </div>

                </div>

                <span class="status <?php echo htmlspecialchars(status_class_name($order_status)); ?>">
                    <?php echo htmlspecialchars($order_status); ?>
                </span>

            </div>

            <div class="details">

                <div>
                    <div class="label">Customer Name</div>
                    <div class="value">
                        <?php echo htmlspecialchars($order["customer_name"] ?? "Customer"); ?>
                    </div>
                </div>

                <div>
                    <div class="label">Contact Number</div>
                    <div class="value">
                        <?php echo htmlspecialchars($order["contact_number"] ?? ""); ?>
                    </div>
                </div>

                <div>
                    <div class="label">Email Address</div>
                    <div class="value">
                        <?php echo htmlspecialchars($display_customer_email); ?>
                    </div>
                </div>

                <div>
                    <div class="label">Size</div>
                    <div class="value">
                        <?php echo htmlspecialchars($order["size_name"] ?? "Standard"); ?>
                    </div>
                </div>

                <div>
                    <div class="label">Quantity</div>
                    <div class="value">
                        <?php echo (int)$order["quantity"]; ?>
                    </div>
                </div>

                <div>
                    <div class="label">Delivery Method</div>

                    <div class="value">

                        <span class="delivery-method <?php echo $delivery_method === "Pick-up" ? "pickup" : "delivery"; ?>">
                            <?php echo htmlspecialchars($delivery_method); ?>
                        </span>

                    </div>
                </div>

                <?php if ($delivery_method === "Delivery"): ?>

                    <div class="span3">

                        <div class="label">Delivery Address</div>

                        <div class="value">
                            <?php echo htmlspecialchars($order["delivery_address"] ?? ""); ?>
                        </div>

                    </div>

                <?php endif; ?>

                <?php if (!empty($order["special_instructions"])): ?>

                    <div class="span3">

                        <div class="label">Special Instructions</div>

                        <div class="value">
                            <?php echo nl2br(htmlspecialchars($order["special_instructions"])); ?>
                        </div>

                    </div>

                <?php endif; ?>

            </div>

            <div class="payment-section">

                <h3>Payment Details</h3>

                <div class="payment-grid">

                    <div>

                        <div class="label">Payment Method</div>

                        <div class="value">
                            <?php echo htmlspecialchars($payment_method); ?>
                        </div>

                    </div>

                    <div>

                        <div class="label">Payment Status</div>

                        <div class="value">

                            <span class="payment-badge <?php echo $payment_class; ?>">
                                <?php echo htmlspecialchars($payment_status); ?>
                            </span>

                        </div>

                    </div>

                    <?php if (!empty($order["gcash_reference_number"])): ?>

                        <div>

                            <div class="label">GCash Reference Number</div>

                            <div class="value">
                                <?php echo htmlspecialchars($order["gcash_reference_number"]); ?>
                            </div>

                        </div>

                    <?php endif; ?>

                    <?php if (!empty($order["payment_rejection_reason"])): ?>

                        <div class="span2">

                            <div class="payment-reason">

                                <strong>Rejection Reason:</strong>

                                <?php echo nl2br(htmlspecialchars($order["payment_rejection_reason"])); ?>

                            </div>

                        </div>

                    <?php endif; ?>

                    <?php if (!empty($order["payment_proof"])): ?>

                        <div class="payment-proof-item">

                            <div class="label">Payment Proof</div>

                            <div class="payment-proof-container">

                                <img
                                    src="../<?php echo htmlspecialchars($order["payment_proof"]); ?>"
                                    alt="Payment proof for order #<?php echo (int)$order["id"]; ?>"
                                    class="payment-proof"
                                >

                                <a
                                    href="../<?php echo htmlspecialchars($order["payment_proof"]); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="payment-link"
                                >View Full Image</a>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

                <?php if (strtolower($payment_method) === "gcash" && strtolower($payment_status) === "pending verification"): ?>

                    <a href="verify_payments.php" class="verify-payment-button">
                        Verify Payment
                    </a>

                <?php endif; ?>

            </div>

            <div class="footer">

                <div class="price money-summary">

                    <small>
                        Subtotal: ₱<?php echo number_format($subtotal, 2); ?>
                    </small>

                    <small>

                        Delivery Fee:

                        <?php if ($delivery_method === "Pick-up"): ?>

                            ₱0.00 (Pick-up)

                        <?php elseif ($delivery_fee_val !== null): ?>

                            ₱<?php echo number_format($delivery_fee_val, 2); ?>

                        <?php else: ?>

                            Not set yet

                        <?php endif; ?>

                    </small>

                    <strong>
                        Total: ₱<?php echo number_format($total_amount, 2); ?>
                    </strong>

                </div>

                <?php if ($is_cancelled): ?>

                    <span class="cancel-note">
                        This order is cancelled and its status can no longer be changed.
                    </span>

                <?php else: ?>

                    <form method="POST" action="orders.php" class="status-form">

                        <input
                            type="hidden"
                            name="order_id"
                            value="<?php echo (int)$order["id"]; ?>"
                        >

                        <input
                            type="hidden"
                            name="filter"
                            value="<?php echo htmlspecialchars($filter); ?>"
                        >

                        <input
                            type="hidden"
                            name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                        >

                        <?php if ($delivery_method === "Delivery"): ?>

                            <label for="fee_<?php echo (int)$order["id"]; ?>">
                                Delivery Fee (₱):
                            </label>

                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="fee_<?php echo (int)$order["id"]; ?>"
                                name="delivery_fee"
                                value="<?php echo $delivery_fee_val !== null ? htmlspecialchars((string)$delivery_fee_val) : ""; ?>"
                                placeholder="0.00"
                            >

                        <?php endif; ?>

                        <label for="status_<?php echo (int)$order["id"]; ?>">
                            Status:
                        </label>

                        <select
                            id="status_<?php echo (int)$order["id"]; ?>"
                            name="order_status"
                        >

                            <?php foreach ($allowed_statuses as $st): ?>

                                <option
                                    value="<?php echo htmlspecialchars($st); ?>"
                                    <?php echo $order_status === $st ? "selected" : ""; ?>
                                >
                                    <?php echo htmlspecialchars($st); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <button type="submit" name="update_status">
                            Update Order
                        </button>

                    </form>

                <?php endif; ?>

                <?php if ($is_archivable): ?>

                    <form method="POST" action="orders.php" class="archive-form">

                        <input
                            type="hidden"
                            name="order_id"
                            value="<?php echo (int)$order["id"]; ?>"
                        >

                        <input
                            type="hidden"
                            name="filter"
                            value="<?php echo htmlspecialchars($filter); ?>"
                        >

                        <input
                            type="hidden"
                            name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                        >

                        <button type="submit" name="archive_order" class="archive-button">
                            Archive
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <div class="empty">

        <h2>No regular cake orders found</h2>

        <p>Try adjusting your search or status filter.</p>

        <?php if ($filter !== "" || $search !== ""): ?>

            <a href="orders.php">Reset filters</a>

        <?php endif; ?>

    </div>

<?php endif; ?>


<h2 class="section-heading" id="custom-requests">
    Customized Cake Requests
</h2>

<p class="section-subtitle">
    Custom design requests submitted by customers. Set a quotation before approving.
</p>

<?php if ($custom_result && $custom_result->num_rows > 0): ?>

    <?php while ($req = $custom_result->fetch_assoc()): ?>

        <?php
            $req_status = $req["request_status"] ?? "Pending";

            $is_req_cancelled = $req_status === "Cancelled";

            $is_req_archivable = in_array(
                $req_status,
                ["Completed", "Cancelled"],
                true
            );

            $req_delivery = normalize_delivery_method(
                $req["delivery_method"] ?? "Delivery"
            );

            $quoted_val = $req["quoted_price"] !== null
                ? (float)$req["quoted_price"]
                : null;

            $req_payment_method = $req["payment_method"] ?? "Cash";
            $req_payment_status = $req["payment_status"] ?? "Unpaid";

            $req_payment_class = "payment-unpaid";

            if (strtolower($req_payment_status) === "pending verification") {
                $req_payment_class = "payment-pending";
            } elseif (strtolower($req_payment_status) === "paid") {
                $req_payment_class = "payment-paid";
            } elseif (strtolower($req_payment_status) === "rejected") {
                $req_payment_class = "payment-rejected";
            }
        ?>

        <div class="card">

            <div class="card-header">

                <div>

                    <span class="type-label">CUSTOM REQUEST</span>

                    <h3>
                        Request #<?php echo (int)$req["id"]; ?>
                        &mdash;
                        <?php echo htmlspecialchars($req["cake_size"] ?? "Custom Cake"); ?>
                    </h3>

                    <div class="sub">
                        Submitted on
                        <?php echo htmlspecialchars(date("M d, Y h:i A", strtotime($req["created_at"]))); ?>
                    </div>

                </div>

                <span class="status <?php echo htmlspecialchars(status_class_name($req_status)); ?>">
                    <?php echo htmlspecialchars($req_status); ?>
                </span>

            </div>

            <div class="custom-request-content">

                <div class="design-panel">

                    <?php if (!empty($req["design_image"])): ?>

                        <div class="design-frame">

                            <img
                                src="../assets/images/custom_requests/<?php echo htmlspecialchars($req["design_image"]); ?>"
                                alt="Design inspiration for request #<?php echo (int)$req["id"]; ?>"
                                class="design"
                            >

                        </div>

                        <a
                            href="../assets/images/custom_requests/<?php echo htmlspecialchars($req["design_image"]); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="design-link"
                        >View Full Design</a>

                    <?php else: ?>

                        <div class="design-frame">
                            <span class="sub">No design image</span>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="details custom-details">

                    <div>
                        <div class="label">Customer Name</div>
                        <div class="value">
                            <?php echo htmlspecialchars($req["full_name"] ?? "Customer"); ?>
                        </div>
                    </div>

                    <div>
                        <div class="label">Contact Number</div>
                        <div class="value">
                            <?php echo htmlspecialchars($req["phone"] ?? ""); ?>
                        </div>
                    </div>

                    <div>
                        <div class="label">Email Address</div>
                        <div class="value">
                            <?php echo htmlspecialchars($req["email"] ?? ""); ?>
                        </div>
                    </div>

                    <div>
                        <div class="label">Flavor</div>
                        <div class="value">
                            <?php echo htmlspecialchars($req["flavor"] ?? ""); ?>
                        </div>
                    </div>

                    <div>
                        <div class="label">Preferred Color</div>
                        <div class="value">
                            <?php echo htmlspecialchars($req["color"] ?? ""); ?>
                        </div>
                    </div>

                    <div>
                        <div class="label">Quantity</div>
                        <div class="value">
                            <?php echo (int)($req["quantity"] ?? 1); ?>
                        </div>
                    </div>

                    <div>
                        <div class="label">Preferred Date</div>
                        <div class="value">
                            <?php echo htmlspecialchars(!empty($req["preferred_date"]) ? date("M d, Y", strtotime($req["preferred_date"])) : ""); ?>
                        </div>
                    </div>

                    <div>
                        <div class="label">Delivery Method</div>

                        <div class="value">

                            <span class="delivery-method <?php echo $req_delivery === "Pick-up" ? "pickup" : "delivery"; ?>">
                                <?php echo htmlspecialchars($req_delivery); ?>
                            </span>

                        </div>
                    </div>

                    <?php if ($req_delivery === "Delivery" && !empty($req["delivery_address"])): ?>

                        <div class="span3">

                            <div class="label">Delivery Address</div>

                            <div class="value">
                                <?php echo htmlspecialchars($req["delivery_address"]); ?>
                            </div>

                        </div>

                    <?php endif; ?>

                    <?php if (!empty($req["special_instructions"])): ?>

                        <div class="span3">

                            <div class="label">Special Instructions</div>

                            <div class="value">
                                <?php echo nl2br(htmlspecialchars($req["special_instructions"])); ?>
                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <div class="payment-section">

                <h3>Payment Details</h3>

                <div class="payment-grid">

                    <div>

                        <div class="label">Payment Method</div>

                        <div class="value">
                            <?php echo htmlspecialchars($req_payment_method); ?>
                        </div>

                    </div>

                    <div>

                        <div class="label">Payment Status</div>

                        <div class="value">

                            <span class="payment-badge <?php echo $req_payment_class; ?>">
                                <?php echo htmlspecialchars($req_payment_status); ?>
                            </span>

                        </div>

                    </div>

                    <?php if (!empty($req["gcash_reference"])): ?>

                        <div>

                            <div class="label">GCash Reference Number</div>

                            <div class="value">
                                <?php echo htmlspecialchars($req["gcash_reference"]); ?>
                            </div>

                        </div>

                    <?php endif; ?>

                    <?php if (!empty($req["payment_rejection_reason"])): ?>

                        <div class="span2">

                            <div class="payment-reason">

                                <strong>Rejection Reason:</strong>

                                <?php echo nl2br(htmlspecialchars($req["payment_rejection_reason"])); ?>

                            </div>

                        </div>

                    <?php endif; ?>

                    <?php if (!empty($req["payment_proof"])): ?>

                        <div class="payment-proof-item">

                            <div class="label">Payment Proof</div>

                            <div class="payment-proof-container">

                                <img
                                    src="../<?php echo htmlspecialchars($req["payment_proof"]); ?>"
                                    alt="Payment proof for request #<?php echo (int)$req["id"]; ?>"
                                    class="payment-proof"
                                >

                                <a
                                    href="../<?php echo htmlspecialchars($req["payment_proof"]); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="payment-link"
                                >View Full Image</a>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

                <?php if (strtolower($req_payment_method) === "gcash" && strtolower($req_payment_status) === "pending verification"): ?>

                    <a href="verify_payments.php" class="verify-payment-button">
                        Verify Payment
                    </a>

                <?php endif; ?>

            </div>

            <div class="footer">

                <div class="price">

                    <small>Quoted Price</small>

                    <strong>
                        <?php echo $quoted_val !== null ? "₱" . number_format($quoted_val, 2) : "Not quoted yet"; ?>
                    </strong>

                </div>

                <?php if ($is_req_cancelled): ?>

                    <span class="cancel-note">
                        This request is cancelled and its status can no longer be changed.
                    </span>

                <?php else: ?>

                    <form method="POST" action="orders.php" class="quote-form">

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?php echo (int)$req["id"]; ?>"
                        >

                        <input
                            type="hidden"
                            name="filter"
                            value="<?php echo htmlspecialchars($filter); ?>"
                        >

                        <input
                            type="hidden"
                            name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                        >

                        <label for="quote_<?php echo (int)$req["id"]; ?>">
                            Quote (₱):
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            id="quote_<?php echo (int)$req["id"]; ?>"
                            name="quoted_price"
                            value="<?php echo $quoted_val !== null ? htmlspecialchars((string)$quoted_val) : ""; ?>"
                            placeholder="0.00"
                        >

                        <label for="req_status_<?php echo (int)$req["id"]; ?>">
                            Status:
                        </label>

                        <select
                            id="req_status_<?php echo (int)$req["id"]; ?>"
                            name="request_status"
                        >

                            <?php foreach ($allowed_statuses as $st): ?>

                                <option
                                    value="<?php echo htmlspecialchars($st); ?>"
                                    <?php echo $req_status === $st ? "selected" : ""; ?>
                                >
                                    <?php echo htmlspecialchars($st); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <button type="submit" name="update_custom_request">
                            Save Request
                        </button>

                    </form>

                <?php endif; ?>

                <?php if ($is_req_archivable): ?>

                    <form method="POST" action="orders.php" class="archive-form">

                        <input
                            type="hidden"
                            name="request_id"
                            value="<?php echo (int)$req["id"]; ?>"
                        >

                        <input
                            type="hidden"
                            name="filter"
                            value="<?php echo htmlspecialchars($filter); ?>"
                        >

                        <input
                            type="hidden"
                            name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                        >

                        <button
                            type="submit"
                            name="archive_custom_request"
                            class="archive-button"
                        >
                            Archive
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    <?php endwhile; ?>

<?php else: ?>

    <div class="empty">

        <h2>No customized cake requests found</h2>

        <p>Try adjusting your search or status filter.</p>

        <?php if ($filter !== "" || $search !== ""): ?>

            <a href="orders.php#custom-requests">Reset filters</a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</body>
</html>

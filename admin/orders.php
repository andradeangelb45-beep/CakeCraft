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
if (!in_array($filter, $allowed_statuses, true)) $filter = "";

function order_return_url($filter, $search, $anchor = "") {
    $params = [];
    $valid_statuses = ["Pending", "Approved", "Ready for Pickup", "Out for Delivery", "Completed", "Cancelled"];
    if (in_array($filter, $valid_statuses, true)) $params["status"] = $filter;
    if ($search !== "") $params["search"] = $search;
    $url = "orders.php" . (!empty($params) ? "?" . http_build_query($params) : "");
    if ($anchor !== "") $url .= "#" . $anchor;
    return $url;
}

function normalize_delivery_method($value) {
    $value = strtolower(trim((string)$value));
    if (in_array($value, ["pickup", "pick-up", "pick up"], true)) return "Pick-up";
    return "Delivery";
}

function status_class_name($status) {
    return strtolower(str_replace(" ", "-", trim((string)$status)));
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
        $get_order = $conn->prepare("SELECT customer_id, delivery_method, delivery_fee, order_status, payment_method, payment_status FROM orders WHERE id = ?");
        $get_order->bind_param("i", $order_id);
        $get_order->execute();
        $existing_order = $get_order->get_result()->fetch_assoc();
        $get_order->close();

        if ($existing_order && $existing_order["order_status"] !== "Cancelled") {
            $method = normalize_delivery_method($existing_order["delivery_method"] ?? "Delivery");
            $payment_method = $existing_order["payment_method"] ?? "Cash";
            $payment_status = $existing_order["payment_status"] ?? "Unpaid";

            // GCash must be submitted and verified as Paid before processing.
            if (strtolower(trim((string)$payment_method)) === "gcash" && strtolower(trim((string)$payment_status)) !== "paid" && in_array($status, $processing_statuses, true)) {
                $payment_error = true;
            }

            $fee_to_save = null;
            if ($method === "Pick-up") {
                $fee_to_save = 0.00;
            } else {
                if ($delivery_fee_raw !== "") {
                    if (is_numeric($delivery_fee_raw) && (float)$delivery_fee_raw >= 0) $fee_to_save = (float)$delivery_fee_raw;
                    else $fee_error = true;
                } else {
                    $fee_to_save = $existing_order["delivery_fee"] !== null ? (float)$existing_order["delivery_fee"] : null;
                }
                if ($status === "Approved" && $fee_to_save === null) $fee_error = true;
            }

            if (!$fee_error && !$payment_error) {
                $old_status = $existing_order["order_status"];
                $stmt = $conn->prepare("UPDATE orders SET order_status = ?, delivery_fee = ?, payment_status = CASE WHEN ? = 'Completed' AND payment_method = 'Cash' THEN 'Paid' ELSE payment_status END WHERE id = ? AND order_status <> 'Cancelled'");
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
                        case "Ready for Pickup": $message = "Your cake order #$order_id is ready for pickup! You may now collect your order."; break;
                        case "Out for Delivery": $message = "Your cake order #$order_id is now out for delivery."; break;
                        case "Completed": $message = "Your cake order #$order_id has been marked as completed. Thank you for ordering from CakeCraft!"; break;
                        case "Cancelled": $message = "Your cake order #$order_id has been cancelled."; break;
                        case "Pending": $message = "Your cake order #$order_id is now pending."; break;
                        default: $message = "The status of your cake order #$order_id has been updated to $status.";
                    }
                    $notification_link = "my_orders.php";
                    $notify = $conn->prepare("INSERT INTO notifications (customer_id, order_id, title, message, link) VALUES (?, ?, ?, ?, ?)");
                    $notify->bind_param("iisss", $customer_id, $order_id, $title, $message, $notification_link);
                    $notify->execute();
                    $notify->close();
                }
            }
        }
    }
    $return_url = order_return_url($_POST["filter"] ?? "", trim($_POST["search"] ?? ""));
    if ($fee_error) $return_url .= (strpos($return_url, "?") === false ? "?" : "&") . "fee_error=1";
    if ($payment_error) $return_url .= (strpos($return_url, "?") === false ? "?" : "&") . "payment_error=1";
    header("Location: " . $return_url);
    exit();
}

/* UPDATE CUSTOM REQUEST QUOTE + STATUS + PAYMENT RESTRICTION + NOTIFICATION */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_custom_request"])) {
    $request_id = (int)($_POST["request_id"] ?? 0);
    $request_status = $_POST["request_status"] ?? "";
    $quote_raw = trim($_POST["quoted_price"] ?? "");
    $quote = $quote_raw !== "" ? (float)$quote_raw : null;
    $valid_quote = $quote_raw === "" || (is_numeric($quote_raw) && (float)$quote_raw >= 0);
    $payment_error = false;
    $processing_statuses = ["Ready for Pickup", "Out for Delivery", "Completed"];

    if ($request_id > 0 && in_array($request_status, $allowed_statuses, true) && $valid_quote) {
        if ($request_status !== "Approved" || ($quote !== null && $quote > 0)) {
            $get_request = $conn->prepare("SELECT customer_id, request_status, quoted_price, payment_method, payment_status FROM custom_cake_requests WHERE id = ?");
            $get_request->bind_param("i", $request_id);
            $get_request->execute();
            $existing_request = $get_request->get_result()->fetch_assoc();
            $get_request->close();

            if ($existing_request) {
                $custom_payment_method = $existing_request["payment_method"] ?? "Cash";
                $custom_payment_status = $existing_request["payment_status"] ?? "Unpaid";
                if (strtolower(trim((string)$custom_payment_method)) === "gcash" && strtolower(trim((string)$custom_payment_status)) !== "paid" && in_array($request_status, $processing_statuses, true)) {
                    $payment_error = true;
                }

                if (!$payment_error) {
                    $old_status = $existing_request["request_status"];
                    $old_quote = $existing_request["quoted_price"];
                    $stmt = $conn->prepare("UPDATE custom_cake_requests SET quoted_price = ?, request_status = ?, payment_status = CASE WHEN ? = 'Completed' AND payment_method = 'Cash' THEN 'Paid' ELSE payment_status END WHERE id = ?");
                    $stmt->bind_param("dssi", $quote, $request_status, $request_status, $request_id);
                    $stmt->execute();
                    $stmt->close();

                    $customer_id = (int)$existing_request["customer_id"];
                    $status_changed = $old_status !== $request_status;
                    $quote_changed = $quote !== null && ($old_quote === null || (float)$old_quote !== (float)$quote);
                    if ($customer_id > 0 && ($status_changed || ($request_status === "Approved" && $quote_changed))) {
                        $title = "Custom Cake Request #$request_id Updated";
                        if ($request_status === "Approved") {
                            $message = "Your customized cake request #$request_id has been approved!";
                            if ($quote !== null) $message .= " Your quotation is ₱" . number_format($quote, 2) . ".";
                            if ($custom_payment_method === "GCash" && $custom_payment_status !== "Paid") {
                                $message .= " Please complete your GCash payment and wait for verification before your order can be processed.";
                            }
                        } elseif ($request_status === "Ready for Pickup") $message = "Your customized cake request #$request_id is ready for pickup! You may now collect your order.";
                        elseif ($request_status === "Out for Delivery") $message = "Your customized cake request #$request_id is now out for delivery.";
                        elseif ($request_status === "Completed") $message = "Your customized cake request #$request_id has been marked as completed. Thank you for choosing CakeCraft!";
                        elseif ($request_status === "Cancelled") $message = "Your customized cake request #$request_id has been cancelled.";
                        elseif ($request_status === "Pending") $message = "Your customized cake request #$request_id is now pending.";
                        else $message = "Your customized cake request #$request_id status has been updated to $request_status.";
                        $notification_link = "my_orders.php";
                        $notify = $conn->prepare("INSERT INTO notifications (customer_id, order_id, title, message, link) VALUES (?, NULL, ?, ?, ?)");
                        $notify->bind_param("isss", $customer_id, $title, $message, $notification_link);
                        $notify->execute();
                        $notify->close();
                    }
                }
            }
        }
    }
    $return_url = order_return_url($_POST["filter"] ?? "", trim($_POST["search"] ?? ""));
    if ($payment_error) $return_url .= (strpos($return_url, "?") === false ? "?" : "&") . "payment_error=1";
    $return_url .= "#custom-requests";
    header("Location: " . $return_url);
    exit();
}

/* COUNTS */
$counts = array_fill_keys($allowed_statuses, 0);
$total_count = 0;
$regular_count_result = $conn->query("SELECT order_status, COUNT(*) AS total FROM orders GROUP BY order_status");
if ($regular_count_result) while ($row = $regular_count_result->fetch_assoc()) {
    $n = (int)$row["total"];
    $total_count += $n;
    if (isset($counts[$row["order_status"]])) $counts[$row["order_status"]] += $n;
}
$custom_count_result = $conn->query("SELECT request_status, COUNT(*) AS total FROM custom_cake_requests GROUP BY request_status");
if ($custom_count_result) while ($row = $custom_count_result->fetch_assoc()) {
    $n = (int)$row["total"];
    $total_count += $n;
    if (isset($counts[$row["request_status"]])) $counts[$row["request_status"]] += $n;
}

/* REGULAR ORDERS QUERY */
$base_sql = "SELECT orders.*, customers.email, cake_options.cake_name, cake_sizes.size_name FROM orders INNER JOIN customers ON orders.customer_id = customers.id INNER JOIN cake_options ON orders.cake_id = cake_options.id LEFT JOIN cake_sizes ON orders.cake_size_id = cake_sizes.id";
$conditions = []; $params = []; $types = "";
if ($filter !== "") { $conditions[] = "orders.order_status = ?"; $params[] = $filter; $types .= "s"; }
if ($search !== "") {
    $conditions[] = "(CAST(orders.id AS CHAR) LIKE ? OR orders.customer_name LIKE ? OR cake_options.cake_name LIKE ? OR orders.contact_number LIKE ? OR customers.email LIKE ?)";
    $sv = "%" . $search . "%";
    for ($i=0;$i<5;$i++) $params[] = $sv;
    $types .= "sssss";
}
$sql = $base_sql . (!empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "") . " ORDER BY orders.created_at DESC";
if (!empty($params)) { $stmt = $conn->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute(); $result = $stmt->get_result(); }
else $result = $conn->query($sql);

/* CUSTOM REQUESTS QUERY */
$custom_conditions = []; $custom_params = []; $custom_types = "";
if ($filter !== "") { $custom_conditions[] = "custom_cake_requests.request_status = ?"; $custom_params[] = $filter; $custom_types .= "s"; }
if ($search !== "") {
    $custom_conditions[] = "(CAST(custom_cake_requests.id AS CHAR) LIKE ? OR customers.full_name LIKE ? OR customers.email LIKE ? OR customers.phone LIKE ? OR custom_cake_requests.cake_size LIKE ? OR custom_cake_requests.flavor LIKE ? OR custom_cake_requests.color LIKE ?)";
    $sv = "%" . $search . "%";
    for ($i=0;$i<7;$i++) $custom_params[] = $sv;
    $custom_types .= "sssssss";
}
$custom_sql = "SELECT custom_cake_requests.*, customers.full_name, customers.email, customers.phone FROM custom_cake_requests LEFT JOIN customers ON custom_cake_requests.customer_id = customers.id" . (!empty($custom_conditions) ? " WHERE " . implode(" AND ", $custom_conditions) : "") . " ORDER BY custom_cake_requests.created_at DESC";
if (!empty($custom_params)) { $custom_stmt = $conn->prepare($custom_sql); $custom_stmt->bind_param($custom_types, ...$custom_params); $custom_stmt->execute(); $custom_result = $custom_stmt->get_result(); }
else $custom_result = $conn->query($custom_sql);
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Orders | CakeCraft</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#fffaf7;--bg-soft:#f7eeea;--surface:#fff;--ink:#3b2724;--ink-soft:#6f5d57;--rose:#c47c78;--rose-deep:#a65a56;--rose-darker:#8c4845;--line:#efe0da;--line-strong:#e0cbc3;--shadow:0 6px 24px rgba(73,48,45,.08)}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'DM Sans',system-ui,sans-serif;line-height:1.6;background:var(--bg);color:var(--ink);min-height:100vh}
header{position:sticky;top:0;z-index:20;display:flex;justify-content:space-between;align-items:center;padding:16px 6%;background:var(--ink);color:#fff}.brand{display:flex;align-items:center;gap:12px}.brand h2{font-family:'Playfair Display',Georgia,serif;font-size:26px}.brand h2 span{color:#e3a9a5}.badge{padding:3px 11px;border-radius:999px;background:#ffffff24;font-size:12px;font-weight:600}header a{color:#fff;text-decoration:none;font-size:14px;font-weight:600;padding:9px 20px;border:1.5px solid #ffffff70;border-radius:999px}
.container{max-width:1180px;margin:auto;padding:42px 24px 70px}.back{display:inline-block;margin-bottom:18px;color:var(--rose-deep);text-decoration:none;font-size:14px;font-weight:600}h1,h2,h3{font-family:'Playfair Display',Georgia,serif}h1{font-size:clamp(1.9rem,4vw,2.5rem);line-height:1.15;margin-bottom:10px}.subtitle{color:var(--ink-soft);font-size:16px;margin-bottom:28px}.section-heading{margin:42px 0 8px;font-size:28px}.section-subtitle{color:var(--ink-soft);margin-bottom:20px}
.search-form{display:flex;gap:10px;margin-bottom:18px;max-width:720px}.search-form input{flex:1;min-width:0;padding:12px 16px;font:inherit;font-size:14px;border:1.5px solid var(--line-strong);border-radius:10px;background:#fff}.search-form input:focus,select:focus,input[type=number]:focus{outline:2px solid var(--rose);outline-offset:2px}button{padding:11px 20px;font:600 14px 'DM Sans',sans-serif;color:white;background:var(--rose-deep);border:0;border-radius:999px;cursor:pointer}button:hover{background:var(--rose-darker)}.clear-search{align-self:center;color:var(--rose-deep);text-decoration:none;font-weight:600;padding:0 8px}
.filters{display:flex;gap:10px;margin-bottom:26px;overflow-x:auto;padding:4px 2px 8px}.filters a{display:inline-flex;align-items:center;gap:8px;white-space:nowrap;padding:9px 16px;border:1.5px solid var(--line-strong);border-radius:999px;background:#fff;color:var(--ink);text-decoration:none;font-size:14px;font-weight:600}.filters a.active{background:var(--ink);border-color:var(--ink);color:white}.count{min-width:24px;padding:0 7px;border-radius:999px;background:var(--bg-soft);color:var(--ink-soft);font-size:12px;text-align:center}.active .count{background:#ffffff2c;color:white}
.type-label{display:inline-block;padding:5px 11px;background:var(--bg-soft);color:var(--rose-deep);border-radius:7px;font-size:12px;font-weight:700;margin-bottom:9px;letter-spacing:.02em}.delivery-method{display:inline-block;padding:5px 12px;border-radius:8px;font-size:13px;font-weight:700}.delivery-method.delivery{background:#fce8e5;color:#a65a56}.delivery-method.pickup{background:#e8f3e8;color:#387345}
.card{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:24px;margin-bottom:22px;box-shadow:var(--shadow)}.card-header{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;padding-bottom:16px;margin-bottom:18px;border-bottom:1px solid var(--line)}.card-header h3{font-size:22px;line-height:1.3}.sub{color:var(--ink-soft);font-size:14px}.status{display:inline-flex;align-items:center;gap:7px;white-space:nowrap;padding:6px 13px;border-radius:999px;font-size:13px;font-weight:600;background:#fff1d6;color:#8a5a00}.status:before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}.status.approved{background:#e5f3e8;color:#1f6a37}.status.ready-for-pickup{background:#fff0dc;color:#9b641c}.status.out-for-delivery{background:#e8e8ff;color:#5454a0}.status.completed{background:#e3efff;color:#1f559a}.status.cancelled{background:#fde8e6;color:#9b1c1c}
.details{display:grid;grid-template-columns:repeat(3,1fr);gap:18px 22px}.span2{grid-column:span 2}.span3{grid-column:1/-1}.label{color:var(--ink-soft);font-size:13px;margin-bottom:2px}.value{font-weight:600;overflow-wrap:anywhere;line-height:1.5}
.footer{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-top:24px;padding:17px 18px;background:var(--bg-soft);border-radius:12px}.price small{display:block;color:var(--ink-soft);font-size:13px}.price strong{font-family:'Playfair Display',Georgia,serif;font-size:26px;color:var(--rose-deep)}.status-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.money-summary{min-width:220px}.money-summary small{margin-top:5px}.money-summary strong{display:block;font-size:20px}.money-summary strong:last-child{font-size:26px}.status-form input[type=number]{width:135px}.status-form label{font-size:14px;font-weight:600}select,input[type=number]{padding:10px 12px;font:inherit;color:var(--ink);border:1.5px solid var(--line-strong);border-radius:9px;background:#fff}
.empty{background:white;border:1px dashed var(--rose);border-radius:16px;padding:42px 22px;text-align:center;color:var(--ink-soft)}.empty h2{font-size:23px;color:var(--ink);margin-bottom:7px}.empty a{display:inline-block;margin-top:12px;color:var(--rose-deep);font-weight:600}
.custom-request-content{display:grid;grid-template-columns:minmax(220px,270px) minmax(0,1fr);gap:26px;align-items:start}.design-panel{min-width:0}.design-frame{width:100%;aspect-ratio:4/5;max-height:340px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:var(--bg-soft);border:1px solid var(--line);border-radius:14px;padding:7px}.design{width:100%;height:100%;object-fit:contain;display:block;border-radius:9px;background:#fff}.design-link{display:inline-block;color:var(--rose-deep);font-weight:600;margin-top:10px}.custom-details{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 22px}.custom-details .span2,.custom-details .span3{grid-column:1/-1}.quote-form{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.quote-form label{font-weight:600;font-size:14px}.quote-form input{width:150px}.cancel-note{color:#9b1c1c;font-weight:600;font-size:14px}
.payment-section{margin-top:22px;padding:20px;border:1px solid var(--line);border-radius:12px;background:#fffaf7}.payment-section h3{font-size:19px;margin-bottom:15px}.payment-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.payment-badge{display:inline-block;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:700}.payment-unpaid{background:#fff1d6;color:#8a5a00}.payment-pending{background:#e8efff;color:#315a9b}.payment-paid{background:#e5f3e8;color:#1f6a37}.payment-rejected{background:#fde8e6;color:#9b1c1c}.payment-proof{display:block;max-width:280px;max-height:350px;object-fit:contain;border:1px solid var(--line);border-radius:10px;margin-top:10px;background:#fff}.payment-link{display:inline-block;color:var(--rose-deep);font-weight:600;margin-top:8px}.verify-payment-button{display:inline-block;margin-top:12px;padding:11px 18px;border:0;border-radius:8px;background:#9b554e;color:#fff;font-weight:700;text-decoration:none;transition:background .2s}.verify-payment-button:hover{background:#7f423c}.page-payment-verification{display:inline-flex;align-items:center;gap:8px;margin:0 0 18px;padding:11px 17px;border-radius:8px;background:#9b554e;color:#fff;text-decoration:none;font-weight:700;box-shadow:0 2px 5px rgba(0,0,0,.08)}.page-payment-verification:hover{background:#7f423c}.payment-reason{padding:12px;border-radius:9px;background:#fde8e6;color:#9b1c1c;margin-top:10px}
@media(max-width:760px){.container{padding:30px 16px 55px}.details{grid-template-columns:1fr 1fr}.span2,.span3{grid-column:1/-1}.card{padding:20px 17px}.custom-request-content{grid-template-columns:minmax(180px,220px) minmax(0,1fr);gap:18px}}@media(max-width:600px){.custom-request-content{grid-template-columns:1fr}.design-panel{max-width:320px}.custom-details{grid-template-columns:1fr 1fr}}@media(max-width:480px){.details,.custom-details,.payment-grid{grid-template-columns:1fr}.custom-details .span2,.custom-details .span3{grid-column:1/-1}.card-header{flex-direction:column}.footer,.status-form,.quote-form{align-items:stretch;flex-direction:column}.status-form button,.quote-form button{width:100%}.search-form{flex-wrap:wrap}.search-form input{flex-basis:100%}.brand .badge{display:none}}
</style></head><body>
<header><div class="brand"><h2>Cake<span>Craft</span></h2><span class="badge">ADMIN</span></div><a href="dashboard.php">Dashboard</a></header>
<div class="container">
<a class="back" href="dashboard.php">&larr; Back to dashboard</a>
<h1>Manage Customer Orders</h1>
<p class="subtitle">View and manage regular orders and customized cake requests in CakeCraft. Set delivery fees before approving Delivery orders.</p>
<form method="GET" action="orders.php" class="search-form">
<?php if ($filter !== ""): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>"><?php endif; ?>
<input type="text" name="search" placeholder="Search order ID, customer, cake, phone, email, flavor..." value="<?php echo htmlspecialchars($search); ?>"><button type="submit">Search</button>
<?php if ($search !== ""): ?><a class="clear-search" href="orders.php<?php echo $filter !== "" ? "?status=" . urlencode($filter) : ""; ?>">Clear</a><?php endif; ?></form>
<nav class="filters" aria-label="Filter all orders">
<?php $all_params=[]; if($search!=="")$all_params["search"]=$search; $all_url="orders.php".($all_params?"?".http_build_query($all_params):""); ?>
<a href="<?php echo htmlspecialchars($all_url); ?>" class="<?php echo $filter===""?"active":""; ?>">All Orders <span class="count"><?php echo (int)$total_count; ?></span></a>
<?php foreach($allowed_statuses as $option): $filter_params=["status"=>$option];if($search!=="")$filter_params["search"]=$search;$filter_url="orders.php?".http_build_query($filter_params); ?>
<a href="<?php echo htmlspecialchars($filter_url); ?>" class="<?php echo $filter===$option?"active":""; ?>"><?php echo htmlspecialchars($option); ?><span class="count"><?php echo (int)$counts[$option]; ?></span></a>
<?php endforeach; ?></nav>
<?php if(isset($_GET["fee_error"])): ?><div class="empty" style="border-style:solid;margin-bottom:20px;color:#9b1c1c;">Please enter a valid delivery fee (₱0.00 or higher) before approving a Delivery order.</div><?php endif; ?>
<?php if(isset($_GET["payment_error"])): ?><div class="empty" style="border-style:solid;margin-bottom:20px;color:#9b1c1c;background:#fde8e6;"><strong>Payment Required!</strong> GCash payment must be submitted and verified as Paid before the order can be marked as Ready for Pickup, Out for Delivery, or Completed.</div><?php endif; ?>
<h2 class="section-heading">Regular Cake Orders</h2><p class="section-subtitle">Orders placed using the available cakes on the website.</p>
<?php if($result && $result->num_rows>0): while($order=$result->fetch_assoc()):
$status_key=status_class_name($order["order_status"]??"Pending");$status_class=in_array($status_key,["pending","approved","ready-for-pickup","out-for-delivery","completed","cancelled"],true)?$status_key:"pending";
$delivery_method=normalize_delivery_method($order["delivery_method"]??"Delivery");$delivery_class=$delivery_method==="Pick-up"?"pickup":"delivery";
$cake_subtotal=(float)($order["total_price"]??0);$saved_delivery_fee=$delivery_method==="Pick-up"?0.00:($order["delivery_fee"]!==null?(float)$order["delivery_fee"]:null);$grand_total=$saved_delivery_fee!==null?$cake_subtotal+$saved_delivery_fee:null;
$payment_method=$order["payment_method"]??"Cash";$payment_status=$order["payment_status"]??"Unpaid";
$payment_status_class=match($payment_status){"Paid"=>"payment-paid","Pending Verification"=>"payment-pending","Rejected"=>"payment-rejected",default=>"payment-unpaid"};
$proof_name=basename((string)($order["payment_proof"]??""));$proof_path="../assets/images/payment_proofs/".$proof_name;
?>
<div class="card"><div class="card-header"><div><span class="type-label">Regular Order</span><h3>Order #<?php echo (int)$order["id"]; ?></h3><p class="sub">Placed <?php echo date("M d, Y g:i A",strtotime($order["created_at"])); ?></p></div><span class="status <?php echo $status_class; ?>"><?php echo htmlspecialchars($order["order_status"]); ?></span></div>
<div class="details">
<div><p class="label">Cake</p><p class="value"><?php echo htmlspecialchars($order["cake_name"]); ?></p></div><div><p class="label">Cake size</p><p class="value"><?php echo htmlspecialchars($order["size_name"]??"Not specified"); ?></p></div><div><p class="label">Quantity</p><p class="value"><?php echo (int)$order["quantity"]; ?></p></div>
<div><p class="label">Customer name</p><p class="value"><?php echo htmlspecialchars($order["customer_name"]); ?></p></div><div><p class="label">Email</p><p class="value"><?php echo htmlspecialchars($order["email"]??"Not available"); ?></p></div><div><p class="label">Contact number</p><p class="value"><?php echo htmlspecialchars($order["contact_number"]??"Not available"); ?></p></div>
<div><p class="label">Delivery method</p><p class="value"><span class="delivery-method <?php echo $delivery_class; ?>"><?php echo htmlspecialchars($delivery_method); ?></span></p></div>
<div class="span2"><p class="label"><?php echo $delivery_method==="Delivery"?"Delivery address":"Address"; ?></p><p class="value"><?php if($delivery_method==="Delivery")echo !empty($order["delivery_address"])?nl2br(htmlspecialchars($order["delivery_address"])):"No delivery address provided.";else echo "No delivery address required — Customer will pick up the order."; ?></p></div>
<div><p class="label">Preferred date</p><p class="value"><?php echo !empty($order["preferred_date"])?date("M d, Y",strtotime($order["preferred_date"])):"Not specified"; ?></p></div><div class="span3"><p class="label">Special instructions</p><p class="value"><?php echo nl2br(htmlspecialchars($order["special_instructions"]??"None")); ?></p></div></div>
<div class="payment-section"><h3>Payment Details</h3><div class="payment-grid"><div><p class="label">Payment method</p><p class="value"><?php echo htmlspecialchars($payment_method); ?></p></div><div><p class="label">Payment status</p><p class="value"><span class="payment-badge <?php echo $payment_status_class; ?>"><?php echo htmlspecialchars($payment_status); ?></span></p></div>
<?php if($payment_method==="GCash"): ?><div><p class="label">GCash reference number</p><p class="value"><?php echo htmlspecialchars($order["gcash_reference_number"]??"Not submitted"); ?></p></div><div><p class="label">Payment submitted</p><p class="value"><?php echo !empty($order["payment_submitted_at"])?date("M d, Y g:i A",strtotime($order["payment_submitted_at"])):"Not submitted"; ?></p></div><div class="span2"><p class="label">Payment proof</p><?php if($proof_name!==""): ?><a href="<?php echo htmlspecialchars($proof_path); ?>" target="_blank" rel="noopener"><img class="payment-proof" src="<?php echo htmlspecialchars($proof_path); ?>" alt="GCash payment proof"></a><a class="payment-link" href="<?php echo htmlspecialchars($proof_path); ?>" target="_blank" rel="noopener">View full payment proof</a><?php else: ?><p class="value">No payment proof uploaded.</p><?php endif; ?></div><?php if($payment_status==="Rejected"&&!empty($order["payment_rejection_reason"])): ?><div class="span2 payment-reason"><p class="label">Payment rejection reason</p><p class="value"><?php echo nl2br(htmlspecialchars($order["payment_rejection_reason"])); ?></p></div><?php endif; ?><?php if($payment_status==="Pending Verification"): ?><div class="span2"><a class="verify-payment-button" href="verify_payments.php">Verify Payment</a></div><?php endif; ?><?php endif; ?></div></div>
<?php if($payment_method==="GCash" && $payment_status!=="Paid" && $order["order_status"]==="Approved"): ?><div class="payment-reason" style="background:#fff1d6;color:#8a5a00;"><strong>Payment Reminder:</strong> This order is approved, but it must not be processed or marked as completed until the customer's GCash payment has been submitted and verified.</div><?php endif; ?>
<div class="footer"><div class="price money-summary"><small>Cake subtotal</small><strong>₱<?php echo number_format($cake_subtotal,2); ?></strong><small>Delivery fee</small><strong><?php echo $saved_delivery_fee!==null?"₱".number_format($saved_delivery_fee,2):"Not set yet"; ?></strong><small>Grand total</small><strong><?php echo $grand_total!==null?"₱".number_format($grand_total,2):"Waiting for delivery fee"; ?></strong></div>
<?php if($order["order_status"]==="Cancelled"): ?><p class="cancel-note">This order has been cancelled. No further status updates are available.</p><?php else: ?><form method="POST" action="orders.php" class="status-form"><input type="hidden" name="order_id" value="<?php echo (int)$order["id"]; ?>"><input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>"><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
<?php if($delivery_method==="Delivery"): ?><label for="fee-<?php echo (int)$order["id"]; ?>">Delivery fee (₱)</label><input type="number" name="delivery_fee" id="fee-<?php echo (int)$order["id"]; ?>" min="0" step="0.01" placeholder="Enter fee" value="<?php echo $order["delivery_fee"]!==null?htmlspecialchars((string)$order["delivery_fee"]):""; ?>"><?php else: ?><input type="hidden" name="delivery_fee" value="0"><span class="label">Pick-up delivery fee: ₱0.00</span><?php endif; ?><label for="status-<?php echo (int)$order["id"]; ?>">Update status</label><select name="order_status" id="status-<?php echo (int)$order["id"]; ?>" required><?php foreach($allowed_statuses as $option): ?><option value="<?php echo htmlspecialchars($option); ?>" <?php echo $order["order_status"]===$option?"selected":""; ?>><?php echo htmlspecialchars($option); ?></option><?php endforeach; ?></select><button type="submit" name="update_status">Update</button></form><?php endif; ?></div></div>
<?php endwhile; else: ?><div class="empty"><h2>No regular orders found</h2><p>No regular cake orders matched your current search or filter.</p></div><?php endif; ?>
<section id="custom-requests"><h2 class="section-heading">Customized Cake Orders</h2><p class="section-subtitle">Review customer designs, provide quotations, and update request status.</p>
<?php if($custom_result && $custom_result->num_rows>0): while($request=$custom_result->fetch_assoc()):
$request_status=$request["request_status"]??"Pending";$status_key=status_class_name($request_status);$status_class=in_array($status_key,["pending","approved","ready-for-pickup","out-for-delivery","completed","cancelled"],true)?$status_key:"pending";
$image_name=basename((string)($request["design_image"]??""));$image_path="../assets/images/custom_requests/".$image_name;
$custom_delivery_method=normalize_delivery_method($request["delivery_method"]??"Delivery");$custom_delivery_class=$custom_delivery_method==="Pick-up"?"pickup":"delivery";
$custom_payment_method=$request["payment_method"]??"Cash";$custom_payment_status=$request["payment_status"]??"Unpaid";
$custom_payment_status_class=match($custom_payment_status){"Paid"=>"payment-paid","Pending Verification"=>"payment-pending","Rejected"=>"payment-rejected",default=>"payment-unpaid"};
$custom_proof_name=basename((string)($request["payment_proof"]??""));$custom_proof_path="../assets/images/payment_proofs/".$custom_proof_name;
?>
<div class="card"><div class="card-header"><div><span class="type-label">Customized Order</span><h3>Custom Request #<?php echo (int)$request["id"]; ?></h3><p class="sub">Submitted <?php echo !empty($request["created_at"])?date("M d, Y g:i A",strtotime($request["created_at"])):"Date unavailable"; ?></p></div><span class="status <?php echo $status_class; ?>"><?php echo htmlspecialchars($request_status); ?></span></div>
<div class="custom-request-content"><div class="design-panel"><p class="label">Submitted design</p><?php if($image_name!==""): ?><a class="design-frame" href="<?php echo htmlspecialchars($image_path); ?>" target="_blank" rel="noopener" aria-label="View submitted cake design in full size"><img class="design" src="<?php echo htmlspecialchars($image_path); ?>" alt="Customer's cake design"></a><a class="design-link" href="<?php echo htmlspecialchars($image_path); ?>" target="_blank" rel="noopener">View full image</a><?php else: ?><div class="design-frame"><p class="value">No design image uploaded.</p></div><?php endif; ?></div>
<div class="details custom-details"><div><p class="label">Customer name</p><p class="value"><?php echo htmlspecialchars($request["full_name"]??"Unavailable"); ?></p></div><div><p class="label">Contact number</p><p class="value"><?php echo htmlspecialchars($request["phone"]??"Unavailable"); ?></p></div><div><p class="label">Customer email</p><p class="value"><?php echo htmlspecialchars($request["email"]??"Unavailable"); ?></p></div><div><p class="label">Cake size</p><p class="value"><?php echo htmlspecialchars($request["cake_size"]??"Not specified"); ?></p></div><div><p class="label">Flavor</p><p class="value"><?php echo htmlspecialchars($request["flavor"]??"Not specified"); ?></p></div><div><p class="label">Color</p><p class="value"><?php echo htmlspecialchars($request["color"]??"Not specified"); ?></p></div><div><p class="label">Quantity</p><p class="value"><?php echo (int)$request["quantity"]; ?></p></div><div><p class="label">Preferred date</p><p class="value"><?php echo !empty($request["preferred_date"])?date("M d, Y",strtotime($request["preferred_date"])):"Not specified"; ?></p></div><div><p class="label">Delivery method</p><p class="value"><span class="delivery-method <?php echo $custom_delivery_class; ?>"><?php echo htmlspecialchars($custom_delivery_method); ?></span></p></div><div class="span2"><p class="label"><?php echo $custom_delivery_method==="Delivery"?"Delivery address":"Address"; ?></p><p class="value"><?php if($custom_delivery_method==="Delivery")echo !empty($request["delivery_address"])?nl2br(htmlspecialchars($request["delivery_address"])):"No delivery address provided.";else echo "No delivery address required — Customer will pick up the order."; ?></p></div><div class="span3"><p class="label">Special instructions</p><p class="value"><?php echo nl2br(htmlspecialchars($request["special_instructions"]??"None")); ?></p></div></div></div>
<div class="payment-section"><h3>Payment Details</h3><div class="payment-grid"><div><p class="label">Payment method</p><p class="value"><?php echo htmlspecialchars($custom_payment_method); ?></p></div><div><p class="label">Payment status</p><p class="value"><span class="payment-badge <?php echo $custom_payment_status_class; ?>"><?php echo htmlspecialchars($custom_payment_status); ?></span></p></div>
<?php if($custom_payment_method==="GCash"): ?><div><p class="label">GCash reference number</p><p class="value"><?php echo htmlspecialchars($request["gcash_reference"]??"Not submitted"); ?></p></div><div><p class="label">Payment verified at</p><p class="value"><?php echo !empty($request["payment_verified_at"])?date("M d, Y g:i A",strtotime($request["payment_verified_at"])):"Not yet verified"; ?></p></div><div class="span2"><p class="label">Payment proof</p><?php if($custom_proof_name!==""): ?><a href="<?php echo htmlspecialchars($custom_proof_path); ?>" target="_blank" rel="noopener"><img class="payment-proof" src="<?php echo htmlspecialchars($custom_proof_path); ?>" alt="Custom cake GCash payment proof"></a><a class="payment-link" href="<?php echo htmlspecialchars($custom_proof_path); ?>" target="_blank" rel="noopener">View full payment proof</a><?php else: ?><p class="value">No payment proof uploaded.</p><?php endif; ?></div><?php if($custom_payment_status==="Rejected"&&!empty($request["payment_rejection_reason"])): ?><div class="span2 payment-reason"><p class="label">Payment rejection reason</p><p class="value"><?php echo nl2br(htmlspecialchars($request["payment_rejection_reason"])); ?></p></div><?php endif; ?><?php if($custom_payment_status==="Pending Verification"): ?><div class="span2"><a class="verify-payment-button" href="verify_payments.php">Verify Payment</a></div><?php endif; ?><?php endif; ?></div></div>
<?php if($custom_payment_method==="GCash" && $custom_payment_status!=="Paid" && $request_status==="Approved"): ?><div class="payment-reason" style="background:#fff1d6;color:#8a5a00;"><strong>Payment Reminder:</strong> This customized order is approved, but it must not be processed or marked as completed until the customer's GCash payment has been submitted and verified.</div><?php endif; ?>
<div class="footer"><div class="price"><small>Quotation price</small><strong><?php echo isset($request["quoted_price"])&&$request["quoted_price"]!==null?"₱".number_format((float)$request["quoted_price"],2):"Not quoted yet"; ?></strong></div>
<?php if($request_status==="Cancelled"): ?><p class="cancel-note">This request was cancelled.</p><?php else: ?><form method="POST" action="<?php echo htmlspecialchars(order_return_url($filter,$search,"custom-requests")); ?>" class="quote-form"><input type="hidden" name="request_id" value="<?php echo (int)$request["id"]; ?>"><input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>"><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><label for="quote-<?php echo (int)$request["id"]; ?>">Quote (₱)</label><input type="number" name="quoted_price" id="quote-<?php echo (int)$request["id"]; ?>" min="0.01" step="0.01" placeholder="Enter price" value="<?php echo $request["quoted_price"]!==null?htmlspecialchars((string)$request["quoted_price"]):""; ?>"><label for="request-status-<?php echo (int)$request["id"]; ?>">Status</label><select name="request_status" id="request-status-<?php echo (int)$request["id"]; ?>" required><?php foreach($allowed_statuses as $option): ?><option value="<?php echo htmlspecialchars($option); ?>" <?php echo $request_status===$option?"selected":""; ?>><?php echo htmlspecialchars($option); ?></option><?php endforeach; ?></select><button type="submit" name="update_custom_request">Save</button></form><?php endif; ?></div></div>
<?php endwhile; else: ?><div class="empty"><h2>No customized orders found</h2><p>No custom cake requests matched your current search or filter.</p></div><?php endif; ?></section></div></body></html>


<?php
session_start();
require_once "../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

$allowed_statuses = ["Pending", "Approved", "Completed", "Cancelled"];
$filter = $_GET["status"] ?? "";
$search = trim($_GET["search"] ?? "");

if (!in_array($filter, $allowed_statuses, true)) {
    $filter = "";
}

/* ==========================================
   HELPER: PRESERVE SEARCH AND FILTER
========================================== */

function order_return_url($filter, $search, $anchor = "") {
    $params = [];

    if (in_array($filter, ["Pending", "Approved", "Completed", "Cancelled"], true)) {
        $params["status"] = $filter;
    }

    if ($search !== "") {
        $params["search"] = $search;
    }

    $url = "orders.php";

    if (!empty($params)) {
        $url .= "?" . http_build_query($params);
    }

    if ($anchor !== "") {
        $url .= "#" . $anchor;
    }

    return $url;
}

/* ==========================================
   UPDATE REGULAR ORDER STATUS
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {

    $order_id = (int)($_POST["order_id"] ?? 0);
    $status = $_POST["order_status"] ?? "";

    if ($order_id > 0 && in_array($status, $allowed_statuses, true)) {

        $stmt = $conn->prepare("
            UPDATE orders
            SET order_status = ?
            WHERE id = ?
            AND order_status <> 'Cancelled'
        ");

        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();
        $stmt->close();
    }

    $return_filter = $_POST["filter"] ?? "";
    $return_search = trim($_POST["search"] ?? "");

    header("Location: " . order_return_url($return_filter, $return_search));
    exit();
}

/* ==========================================
   UPDATE CUSTOM REQUEST QUOTATION AND STATUS
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_custom_request"])) {

    $request_id = (int)($_POST["request_id"] ?? 0);
    $request_status = $_POST["request_status"] ?? "";
    $quote_raw = trim($_POST["quoted_price"] ?? "");

    $quote = null;

    $valid_quote = (
        $quote_raw === "" ||
        (is_numeric($quote_raw) && (float)$quote_raw >= 0)
    );

    if ($quote_raw !== "") {
        $quote = (float)$quote_raw;
    }

    if (
        $request_id > 0 &&
        in_array($request_status, $allowed_statuses, true) &&
        $valid_quote
    ) {

        // Approval requires a valid quotation.
        if ($request_status !== "Approved" || ($quote !== null && $quote > 0)) {

            $stmt = $conn->prepare("
                UPDATE custom_cake_requests
                SET quoted_price = ?,
                    request_status = ?
                WHERE id = ?
            ");

            $stmt->bind_param("dsi", $quote, $request_status, $request_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $return_filter = $_POST["filter"] ?? "";
    $return_search = trim($_POST["search"] ?? "");

    header("Location: " . order_return_url($return_filter, $return_search, "custom-requests"));
    exit();
}

/* ==========================================
   COMBINED ORDER COUNTS
   REGULAR + CUSTOMIZED
========================================== */

$counts = array_fill_keys($allowed_statuses, 0);
$total_count = 0;

/* Count regular orders */
$regular_count_result = $conn->query("
    SELECT order_status, COUNT(*) AS total
    FROM orders
    GROUP BY order_status
");

if ($regular_count_result) {
    while ($row = $regular_count_result->fetch_assoc()) {

        $status_name = $row["order_status"];
        $total = (int)$row["total"];

        $total_count += $total;

        if (isset($counts[$status_name])) {
            $counts[$status_name] += $total;
        }
    }
}

/* Count custom cake requests */
$custom_count_result = $conn->query("
    SELECT request_status, COUNT(*) AS total
    FROM custom_cake_requests
    GROUP BY request_status
");

if ($custom_count_result) {
    while ($row = $custom_count_result->fetch_assoc()) {

        $status_name = $row["request_status"];
        $total = (int)$row["total"];

        $total_count += $total;

        if (isset($counts[$status_name])) {
            $counts[$status_name] += $total;
        }
    }
}

/* ==========================================
   REGULAR ORDERS QUERY
========================================== */

$base_sql = "
    SELECT
        orders.*,
        customers.email,
        cake_options.cake_name,
        cake_sizes.size_name
    FROM orders
    INNER JOIN customers
        ON orders.customer_id = customers.id
    INNER JOIN cake_options
        ON orders.cake_id = cake_options.id
    LEFT JOIN cake_sizes
        ON orders.cake_size_id = cake_sizes.id
";

$conditions = [];
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

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sssss";
}

$sql = $base_sql;

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY orders.created_at DESC";

if (!empty($params)) {

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $result = $stmt->get_result();

} else {

    $result = $conn->query($sql);
}

/* ==========================================
   CUSTOM CAKE REQUESTS QUERY
   FILTER AND SEARCH INCLUDED
========================================== */

$custom_conditions = [];
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

    $search_value = "%" . $search . "%";

    $custom_params[] = $search_value;
    $custom_params[] = $search_value;
    $custom_params[] = $search_value;
    $custom_params[] = $search_value;
    $custom_params[] = $search_value;
    $custom_params[] = $search_value;
    $custom_params[] = $search_value;

    $custom_types .= "sssssss";
}

$custom_sql = "
    SELECT
        custom_cake_requests.*,
        customers.full_name,
        customers.email,
        customers.phone
    FROM custom_cake_requests
    LEFT JOIN customers
        ON custom_cake_requests.customer_id = customers.id
";

if (!empty($custom_conditions)) {
    $custom_sql .= " WHERE " . implode(" AND ", $custom_conditions);
}

$custom_sql .= " ORDER BY custom_cake_requests.created_at DESC";

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

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

<style>

:root {
    --bg: #fffaf7;
    --bg-soft: #f7eeea;
    --surface: #fff;
    --ink: #3b2724;
    --ink-soft: #6f5d57;
    --rose: #c47c78;
    --rose-deep: #a65a56;
    --rose-darker: #8c4845;
    --line: #efe0da;
    --line-strong: #e0cbc3;
    --shadow: 0 6px 24px rgba(73,48,45,.08);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', system-ui, sans-serif;
    line-height: 1.6;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
}

header {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 6%;
    background: var(--ink);
    color: #fff;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand h2 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 26px;
}

.brand h2 span {
    color: #e3a9a5;
}

.badge {
    padding: 3px 11px;
    border-radius: 999px;
    background: #ffffff24;
    font-size: 12px;
    font-weight: 600;
}

header a {
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    padding: 9px 20px;
    border: 1.5px solid #ffffff70;
    border-radius: 999px;
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

h1, h2, h3 {
    font-family: 'Playfair Display', Georgia, serif;
}

h1 {
    font-size: clamp(1.9rem, 4vw, 2.5rem);
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
}

.section-subtitle {
    color: var(--ink-soft);
    margin-bottom: 20px;
}

/* SEARCH */

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
    font: inherit;
    font-size: 14px;
    border: 1.5px solid var(--line-strong);
    border-radius: 10px;
    background: #fff;
}

.search-form input:focus,
select:focus,
input[type=number]:focus {
    outline: 2px solid var(--rose);
    outline-offset: 2px;
}

/* BUTTONS */

button {
    padding: 11px 20px;
    font: 600 14px 'DM Sans', sans-serif;
    color: white;
    background: var(--rose-deep);
    border: 0;
    border-radius: 999px;
    cursor: pointer;
}

button:hover {
    background: var(--rose-darker);
}

.clear-search {
    align-self: center;
    color: var(--rose-deep);
    text-decoration: none;
    font-weight: 600;
    padding: 0 8px;
}

/* FILTERS */

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
    background: #fff;
    color: var(--ink);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}

.filters a.active {
    background: var(--ink);
    border-color: var(--ink);
    color: white;
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

.active .count {
    background: #ffffff2c;
    color: white;
}

/* ORDER TYPE LABEL */

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

/* CARDS */

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

.status.completed {
    background: #e3efff;
    color: #1f559a;
}

.status.cancelled {
    background: #fde8e6;
    color: #9b1c1c;
}

/* DETAILS */

.details {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px 22px;
}

.span2 {
    grid-column: span 2;
}

.span3 {
    grid-column: 1/-1;
}

.label {
    color: var(--ink-soft);
    font-size: 13px;
    margin-bottom: 2px;
}

.value {
    font-weight: 600;
    overflow-wrap: anywhere;
    line-height: 1.5;
}

/* FOOTER */

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
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 26px;
    color: var(--rose-deep);
}

.status-form {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.status-form label {
    font-size: 14px;
    font-weight: 600;
}

select,
input[type=number] {
    padding: 10px 12px;
    font: inherit;
    color: var(--ink);
    border: 1.5px solid var(--line-strong);
    border-radius: 9px;
    background: #fff;
}

/* EMPTY */

.empty {
    background: white;
    border: 1px dashed var(--rose);
    border-radius: 16px;
    padding: 42px 22px;
    text-align: center;
    color: var(--ink-soft);
}

.empty h2 {
    font-size: 23px;
    color: var(--ink);
    margin-bottom: 7px;
}

.empty a {
    display: inline-block;
    margin-top: 12px;
    color: var(--rose-deep);
    font-weight: 600;
}

/* CUSTOM REQUEST DESIGN + DETAILS */

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
    aspect-ratio: 4 / 5;
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
    background: #fff;
}

.design-link {
    display: inline-block;
    color: var(--rose-deep);
    font-weight: 600;
    margin-top: 10px;
}

.custom-details {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px 22px;
}

.custom-details .span2,
.custom-details .span3 {
    grid-column: 1 / -1;
}

/* QUOTATION FORM */

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

/* RESPONSIVE */

@media(max-width:760px) {
    .container {
        padding: 30px 16px 55px;
    }

    .details {
        grid-template-columns: 1fr 1fr;
    }

    .span2,
    .span3 {
        grid-column: 1/-1;
    }

    .card {
        padding: 20px 17px;
    }

    .custom-request-content {
        grid-template-columns: minmax(180px, 220px) minmax(0, 1fr);
        gap: 18px;
    }
}

@media(max-width:600px) {
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

@media(max-width:480px) {
    .details {
        grid-template-columns: 1fr;
    }

    .custom-details {
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

    .brand .badge {
        display: none;
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

    <a href="dashboard.php">Dashboard</a>
</header>

<div class="container">

<a class="back" href="dashboard.php">&larr; Back to dashboard</a>

<h1>Manage Customer Orders</h1>

<p class="subtitle">
    View and manage regular orders and customized cake requests in CakeCraft.
</p>

<!-- SEARCH -->

<form method="GET" action="orders.php" class="search-form">

    <?php if ($filter !== ""): ?>
        <input type="hidden" name="status"
               value="<?php echo htmlspecialchars($filter); ?>">
    <?php endif; ?>

    <input
        type="text"
        name="search"
        placeholder="Search order ID, customer, cake, phone, email, flavor..."
        value="<?php echo htmlspecialchars($search); ?>"
    >

    <button type="submit">Search</button>

    <?php if ($search !== ""): ?>
        <a class="clear-search"
           href="orders.php<?php echo $filter !== "" ? "?status=" . urlencode($filter) : ""; ?>">
            Clear
        </a>
    <?php endif; ?>

</form>

<!-- COMBINED STATUS FILTERS -->

<nav class="filters" aria-label="Filter all orders">

<?php
$all_params = [];

if ($search !== "") {
    $all_params["search"] = $search;
}

$all_url = "orders.php" . ($all_params ? "?" . http_build_query($all_params) : "");
?>

<a href="<?php echo htmlspecialchars($all_url); ?>"
   class="<?php echo $filter === "" ? "active" : ""; ?>">

    All Orders
    <span class="count"><?php echo (int)$total_count; ?></span>

</a>

<?php foreach ($allowed_statuses as $option):

    $filter_params = ["status" => $option];

    if ($search !== "") {
        $filter_params["search"] = $search;
    }

    $filter_url = "orders.php?" . http_build_query($filter_params);

?>

<a href="<?php echo htmlspecialchars($filter_url); ?>"
   class="<?php echo $filter === $option ? "active" : ""; ?>">

    <?php echo htmlspecialchars($option); ?>

    <span class="count"><?php echo (int)$counts[$option]; ?></span>

</a>

<?php endforeach; ?>

</nav>

<!-- ==========================================
     REGULAR CAKE ORDERS
========================================== -->

<h2 class="section-heading">Regular Cake Orders</h2>

<p class="section-subtitle">
    Orders placed using the available cakes on the website.
</p>

<?php if ($result && $result->num_rows > 0): ?>

<?php while ($order = $result->fetch_assoc()):

    $status_key = strtolower($order["order_status"] ?? "pending");

    $status_class = in_array(
        $status_key,
        ["pending", "approved", "completed", "cancelled"],
        true
    ) ? $status_key : "pending";

?>

<div class="card">

    <div class="card-header">

        <div>
            <span class="type-label">Regular Order</span>

            <h3>Order #<?php echo (int)$order["id"]; ?></h3>

            <p class="sub">
                Placed
                <?php echo date("M d, Y g:i A", strtotime($order["created_at"])); ?>
            </p>
        </div>

        <span class="status <?php echo $status_class; ?>">
            <?php echo htmlspecialchars($order["order_status"]); ?>
        </span>

    </div>

    <div class="details">

        <div>
            <p class="label">Cake</p>
            <p class="value">
                <?php echo htmlspecialchars($order["cake_name"]); ?>
            </p>
        </div>

        <div>
            <p class="label">Cake size</p>
            <p class="value">
                <?php echo htmlspecialchars($order["size_name"] ?? "Not specified"); ?>
            </p>
        </div>

        <div>
            <p class="label">Quantity</p>
            <p class="value">
                <?php echo (int)$order["quantity"]; ?>
            </p>
        </div>

        <div>
            <p class="label">Customer name</p>
            <p class="value">
                <?php echo htmlspecialchars($order["customer_name"]); ?>
            </p>
        </div>

        <div>
            <p class="label">Email</p>
            <p class="value">
                <?php echo htmlspecialchars($order["email"] ?? "Not available"); ?>
            </p>
        </div>

        <div>
            <p class="label">Contact number</p>
            <p class="value">
                <?php echo htmlspecialchars($order["contact_number"] ?? "Not available"); ?>
            </p>
        </div>

        <div class="span2">
            <p class="label">Delivery address</p>
            <p class="value">
                <?php echo nl2br(htmlspecialchars($order["delivery_address"])); ?>
            </p>
        </div>

        <div>
            <p class="label">Preferred date</p>
            <p class="value">
                <?php
                echo !empty($order["preferred_date"])
                    ? date("M d, Y", strtotime($order["preferred_date"]))
                    : "Not specified";
                ?>
            </p>
        </div>

        <div class="span3">
            <p class="label">Special instructions</p>
            <p class="value">
                <?php echo nl2br(htmlspecialchars($order["special_instructions"] ?? "None")); ?>
            </p>
        </div>

    </div>

    <div class="footer">

        <div class="price">
            <small>Total price</small>
            <strong>
                ₱<?php echo number_format((float)$order["total_price"], 2); ?>
            </strong>
        </div>

        <?php if ($order["order_status"] === "Cancelled"): ?>

            <p class="cancel-note">
                This order has been cancelled. No further status updates are available.
            </p>

        <?php else: ?>

        <form method="POST" action="orders.php" class="status-form">

            <input type="hidden" name="order_id"
                   value="<?php echo (int)$order["id"]; ?>">

            <input type="hidden" name="filter"
                   value="<?php echo htmlspecialchars($filter); ?>">

            <input type="hidden" name="search"
                   value="<?php echo htmlspecialchars($search); ?>">

            <label for="status-<?php echo (int)$order["id"]; ?>">
                Update status
            </label>

            <select name="order_status"
                    id="status-<?php echo (int)$order["id"]; ?>"
                    required>

                <?php foreach ($allowed_statuses as $option): ?>

                    <option value="<?php echo htmlspecialchars($option); ?>"
                        <?php echo $order["order_status"] === $option ? "selected" : ""; ?>>

                        <?php echo htmlspecialchars($option); ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" name="update_status">
                Update
            </button>

        </form>

        <?php endif; ?>

    </div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="empty">

    <h2>No regular orders found</h2>

    <p>
        No regular cake orders matched your current search or filter.
    </p>

</div>

<?php endif; ?>


<!-- ==========================================
     CUSTOM CAKE REQUESTS
========================================== -->

<section id="custom-requests">

<h2 class="section-heading">Customized Cake Orders</h2>

<p class="section-subtitle">
    Review customer designs, provide quotations, and update request status.
</p>

<?php if ($custom_result && $custom_result->num_rows > 0): ?>

<?php while ($request = $custom_result->fetch_assoc()):

    $request_status = $request["request_status"] ?? "Pending";
    $status_key = strtolower($request_status);

    $status_class = in_array(
        $status_key,
        ["pending", "approved", "completed", "cancelled"],
        true
    ) ? $status_key : "pending";

    $image_name = basename((string)($request["design_image"] ?? ""));
    $image_path = "../assets/images/custom_requests/" . $image_name;

?>

<div class="card">

    <div class="card-header">

        <div>
            <span class="type-label">Customized Order</span>

            <h3>Custom Request #<?php echo (int)$request["id"]; ?></h3>

            <p class="sub">
                Submitted
                <?php
                echo !empty($request["created_at"])
                    ? date("M d, Y g:i A", strtotime($request["created_at"]))
                    : "Date unavailable";
                ?>
            </p>
        </div>

        <span class="status <?php echo $status_class; ?>">
            <?php echo htmlspecialchars($request_status); ?>
        </span>

    </div>

    <div class="custom-request-content">

        <!-- DESIGN IMAGE -->
        <div class="design-panel">
            <p class="label">Submitted design</p>

            <?php if ($image_name !== ""): ?>
                <a class="design-frame"
                   href="<?php echo htmlspecialchars($image_path); ?>"
                   target="_blank" rel="noopener"
                   aria-label="View submitted cake design in full size">
                    <img
                        class="design"
                        src="<?php echo htmlspecialchars($image_path); ?>"
                        alt="Customer's cake design"
                    >
                </a>

                <a class="design-link"
                   href="<?php echo htmlspecialchars($image_path); ?>"
                   target="_blank" rel="noopener">
                    View full image
                </a>
            <?php else: ?>
                <div class="design-frame">
                    <p class="value">No design image uploaded.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="details custom-details">

        <!-- CUSTOMER NAME -->

        <div>
            <p class="label">Customer name</p>

            <p class="value">
                <?php echo htmlspecialchars($request["full_name"] ?? "Unavailable"); ?>
            </p>
        </div>

        <!-- CUSTOMER PHONE -->

        <div>
            <p class="label">Contact number</p>

            <p class="value">
                <?php echo htmlspecialchars($request["phone"] ?? "Unavailable"); ?>
            </p>
        </div>

        <!-- CUSTOMER EMAIL -->

        <div>
            <p class="label">Customer email</p>

            <p class="value">
                <?php echo htmlspecialchars($request["email"] ?? "Unavailable"); ?>
            </p>
        </div>

        <!-- CAKE DETAILS -->

        <div>
            <p class="label">Cake size</p>

            <p class="value">
                <?php echo htmlspecialchars($request["cake_size"] ?? "Not specified"); ?>
            </p>
        </div>

        <div>
            <p class="label">Flavor</p>

            <p class="value">
                <?php echo htmlspecialchars($request["flavor"] ?? "Not specified"); ?>
            </p>
        </div>

        <div>
            <p class="label">Color</p>

            <p class="value">
                <?php echo htmlspecialchars($request["color"] ?? "Not specified"); ?>
            </p>
        </div>

        <div>
            <p class="label">Quantity</p>

            <p class="value">
                <?php echo (int)$request["quantity"]; ?>
            </p>
        </div>

        <div>
            <p class="label">Preferred date</p>

            <p class="value">
                <?php
                echo !empty($request["preferred_date"])
                    ? date("M d, Y", strtotime($request["preferred_date"]))
                    : "Not specified";
                ?>
            </p>
        </div>

        <!-- ADDRESS -->

        <div class="span2">
            <p class="label">Delivery address</p>

            <p class="value">
                <?php echo nl2br(htmlspecialchars($request["delivery_address"] ?? "Not specified")); ?>
            </p>
        </div>

        <!-- INSTRUCTIONS -->

        <div class="span3">
            <p class="label">Special instructions</p>

            <p class="value">
                <?php echo nl2br(htmlspecialchars($request["special_instructions"] ?? "None")); ?>
            </p>
        </div>

        </div><!-- /.details.custom-details -->

    </div><!-- /.custom-request-content -->

    <!-- QUOTATION AND STATUS -->

    <div class="footer">

        <div class="price">

            <small>Quotation price</small>

            <strong>
                <?php
                echo isset($request["quoted_price"]) && $request["quoted_price"] !== null
                    ? "₱" . number_format((float)$request["quoted_price"], 2)
                    : "Not quoted yet";
                ?>
            </strong>

        </div>

        <?php if ($request_status === "Cancelled"): ?>

            <p class="cancel-note">
                This request was cancelled.
            </p>

        <?php else: ?>

        <form method="POST"
              action="<?php echo htmlspecialchars(order_return_url($filter, $search, "custom-requests")); ?>"
              class="quote-form">

            <input type="hidden"
                   name="request_id"
                   value="<?php echo (int)$request["id"]; ?>">

            <input type="hidden"
                   name="filter"
                   value="<?php echo htmlspecialchars($filter); ?>">

            <input type="hidden"
                   name="search"
                   value="<?php echo htmlspecialchars($search); ?>">

            <label for="quote-<?php echo (int)$request["id"]; ?>">
                Quote (₱)
            </label>

            <input
                type="number"
                name="quoted_price"
                id="quote-<?php echo (int)$request["id"]; ?>"
                min="0.01"
                step="0.01"
                placeholder="Enter price"
                value="<?php echo $request["quoted_price"] !== null ? htmlspecialchars((string)$request["quoted_price"]) : ""; ?>"
            >

            <label for="request-status-<?php echo (int)$request["id"]; ?>">
                Status
            </label>

            <select
                name="request_status"
                id="request-status-<?php echo (int)$request["id"]; ?>"
                required
            >

                <?php foreach ($allowed_statuses as $option): ?>

                    <option
                        value="<?php echo htmlspecialchars($option); ?>"
                        <?php echo $request_status === $option ? "selected" : ""; ?>
                    >

                        <?php echo htmlspecialchars($option); ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" name="update_custom_request">
                Save
            </button>

        </form>

        <?php endif; ?>

    </div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="empty">

    <h2>No customized orders found</h2>

    <p>
        No custom cake requests matched your current search or filter.
    </p>

</div>

<?php endif; ?>

</section>

</div>

</body>
</html>

<?php
session_start();
require_once "../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

/* CSRF TOKEN */
if (empty($_SESSION["archive_csrf"])) {
    $_SESSION["archive_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$message_type = "success";

/* ==========================================
   RESTORE REGULAR ORDER
========================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["restore_order"])) {
    if (!hash_equals($_SESSION["archive_csrf"], $_POST["csrf_token"] ?? "")) {
        $message = "Invalid request. Please try again.";
        $message_type = "error";
    } else {
        $order_id = (int)($_POST["order_id"] ?? 0);

        if ($order_id > 0) {
            $stmt = $conn->prepare("
                UPDATE orders
                SET archived_at = NULL
                WHERE id = ?
                AND archived_at IS NOT NULL
                AND admin_removed_at IS NULL
            ");

            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = "Order #{$order_id} restored successfully.";
            } else {
                $message = "Order not found or already restored.";
                $message_type = "error";
            }

            $stmt->close();
        } else {
            $message = "Invalid order ID.";
            $message_type = "error";
        }
    }
}

/* ==========================================
   RESTORE CUSTOM CAKE REQUEST
========================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["restore_custom_request"])) {
    if (!hash_equals($_SESSION["archive_csrf"], $_POST["csrf_token"] ?? "")) {
        $message = "Invalid request. Please try again.";
        $message_type = "error";
    } else {
        $request_id = (int)($_POST["request_id"] ?? 0);

        if ($request_id > 0) {
            $stmt = $conn->prepare("
                UPDATE custom_cake_requests
                SET archived_at = NULL
                WHERE id = ?
                AND archived_at IS NOT NULL
                AND admin_removed_at IS NULL
            ");

            $stmt->bind_param("i", $request_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = "Custom cake request #{$request_id} restored successfully.";
            } else {
                $message = "Request not found or already restored.";
                $message_type = "error";
            }

            $stmt->close();
        } else {
            $message = "Invalid request ID.";
            $message_type = "error";
        }
    }
}

/* ==========================================
   PERMANENT DELETE REGULAR ORDER
========================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["permanent_delete_order"])) {
    if (!hash_equals($_SESSION["archive_csrf"], $_POST["csrf_token"] ?? "")) {
        $message = "Invalid request. Please try again.";
        $message_type = "error";
    } else {
        $order_id = (int)($_POST["order_id"] ?? 0);

        if ($order_id > 0) {
            try {
                $stmt = $conn->prepare("
                    DELETE FROM orders
                    WHERE id = ?
                    AND archived_at IS NOT NULL
                    AND admin_removed_at IS NULL
                    AND order_status IN ('Completed', 'Cancelled')
                ");

                $stmt->bind_param("i", $order_id);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    $message = "Order #{$order_id} was permanently deleted.";
                } else {
                    $message = "Order could not be permanently deleted. It may not be archived or its status is not Completed or Cancelled.";
                    $message_type = "error";
                }

                $stmt->close();

            } catch (Throwable $e) {
                $message = "Unable to permanently delete this order. It may be connected to other records.";
                $message_type = "error";
            }
        } else {
            $message = "Invalid order ID.";
            $message_type = "error";
        }
    }
}

/* ==========================================
   PERMANENT DELETE CUSTOM CAKE REQUEST
   Also deletes its uploaded design image
========================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["permanent_delete_custom_request"])) {
    if (!hash_equals($_SESSION["archive_csrf"], $_POST["csrf_token"] ?? "")) {
        $message = "Invalid request. Please try again.";
        $message_type = "error";
    } else {
        $request_id = (int)($_POST["request_id"] ?? 0);

        if ($request_id > 0) {
            try {
                /* GET IMAGE FILE NAME FIRST */
                $image_stmt = $conn->prepare("
                    SELECT design_image
                    FROM custom_cake_requests
                    WHERE id = ?
                    AND archived_at IS NOT NULL
                    AND admin_removed_at IS NULL
                    AND request_status IN ('Completed', 'Cancelled')
                    LIMIT 1
                ");

                $image_stmt->bind_param("i", $request_id);
                $image_stmt->execute();

                $image_result = $image_stmt->get_result();
                $request_data = $image_result->fetch_assoc();

                $image_stmt->close();

                if (!$request_data) {
                    $message = "Request could not be permanently deleted. It may not be archived or its status is not Completed or Cancelled.";
                    $message_type = "error";
                } else {
                    $design_image = trim((string)($request_data["design_image"] ?? ""));

                    /* DELETE DATABASE RECORD */
                    $delete_stmt = $conn->prepare("
                        DELETE FROM custom_cake_requests
                        WHERE id = ?
                        AND archived_at IS NOT NULL
                        AND admin_removed_at IS NULL
                        AND request_status IN ('Completed', 'Cancelled')
                    ");

                    $delete_stmt->bind_param("i", $request_id);
                    $delete_stmt->execute();

                    $deleted = $delete_stmt->affected_rows > 0;
                    $delete_stmt->close();

                    if ($deleted) {
                        $image_deleted = true;

                        /* DELETE UPLOADED IMAGE AFTER DB DELETION */
                        if ($design_image !== "") {
                            $filename = basename(str_replace("\\", "/", $design_image));

                            $image_path = __DIR__ . "/../assets/images/custom_requests/" . $filename;

                            if (is_file($image_path)) {
                                $image_deleted = unlink($image_path);
                            }
                        }

                        if ($image_deleted) {
                            $message = "Custom cake request #{$request_id} and its uploaded image were permanently deleted.";
                        } else {
                            $message = "Custom cake request #{$request_id} was deleted, but its uploaded image could not be removed. Please check the image folder.";
                            $message_type = "error";
                        }
                    } else {
                        $message = "Request could not be permanently deleted.";
                        $message_type = "error";
                    }
                }

            } catch (Throwable $e) {
                $message = "Unable to permanently delete this custom cake request. Please check its database relationships and image column.";
                $message_type = "error";
            }
        } else {
            $message = "Invalid request ID.";
            $message_type = "error";
        }
    }
}

/* ==========================================
   SEARCH
========================================== */
$search = trim($_GET["search"] ?? "");
$search_like = "%" . $search . "%";

/* ==========================================
   FETCH ARCHIVED REGULAR ORDERS
========================================== */
$orders = [];

if ($search !== "") {
    $stmt = $conn->prepare("
        SELECT
            orders.*,
            COALESCE(cake_options.cake_name, orders.cake_name_snapshot) AS cake_name,
            COALESCE(cake_sizes.size_name, orders.cake_size_snapshot) AS size_name
        FROM orders
        LEFT JOIN cake_options
            ON orders.cake_id = cake_options.id
        LEFT JOIN cake_sizes
            ON orders.cake_size_id = cake_sizes.id
        WHERE orders.archived_at IS NOT NULL
        AND orders.admin_removed_at IS NULL
        AND (
            CAST(orders.id AS CHAR) LIKE ?
            OR CAST(orders.customer_id AS CHAR) LIKE ?
            OR COALESCE(cake_options.cake_name, orders.cake_name_snapshot) LIKE ?
        )
        ORDER BY orders.archived_at DESC
    ");

    $stmt->bind_param("sss", $search_like, $search_like, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    $stmt->close();

} else {
    $result = $conn->query("
        SELECT
            orders.*,
            COALESCE(cake_options.cake_name, orders.cake_name_snapshot) AS cake_name,
            COALESCE(cake_sizes.size_name, orders.cake_size_snapshot) AS size_name
        FROM orders
        LEFT JOIN cake_options
            ON orders.cake_id = cake_options.id
        LEFT JOIN cake_sizes
            ON orders.cake_size_id = cake_sizes.id
        WHERE orders.archived_at IS NOT NULL
        AND orders.admin_removed_at IS NULL
        ORDER BY orders.archived_at DESC
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}

/* ==========================================
   FETCH ARCHIVED CUSTOM CAKE REQUESTS
========================================== */
$custom_requests = [];

if ($search !== "") {
    $stmt = $conn->prepare("
        SELECT *
        FROM custom_cake_requests
        WHERE archived_at IS NOT NULL
        AND admin_removed_at IS NULL
        AND (
            CAST(id AS CHAR) LIKE ?
            OR CAST(customer_id AS CHAR) LIKE ?
        )
        ORDER BY archived_at DESC
    ");

    $stmt->bind_param("ss", $search_like, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $custom_requests[] = $row;
    }

    $stmt->close();

} else {
    $result = $conn->query("
        SELECT *
        FROM custom_cake_requests
        WHERE archived_at IS NOT NULL
        AND admin_removed_at IS NULL
        ORDER BY archived_at DESC
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $custom_requests[] = $row;
        }
    }
}

/* ==========================================
   COUNTS
========================================== */
$regular_count = count($orders);
$custom_count = count($custom_requests);
$total_archived = $regular_count + $custom_count;

/* ==========================================
   HELPER FUNCTIONS
========================================== */
function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function get_first_value(array $row, array $keys, $default = "—") {
    foreach ($keys as $key) {
        if (
            array_key_exists($key, $row) &&
            $row[$key] !== null &&
            $row[$key] !== ""
        ) {
            return $row[$key];
        }
    }

    return $default;
}

function format_money($value) {
    if ($value === "—" || $value === null || $value === "") {
        return "—";
    }

    if (is_numeric($value)) {
        return "₱" . number_format((float)$value, 2);
    }

    return e($value);
}

function display_order_details(array $row, $is_custom = false) {
    $details = [];

    if ($is_custom) {
        $possible_fields = [
            "Design" => ["design_description", "description", "cake_description", "custom_design", "design"],
            "Size" => ["size", "cake_size", "size_name"],
            "Flavor" => ["flavor", "cake_flavor"],
            "Quantity" => ["quantity", "qty"],
            "Quoted Price" => ["quoted_price", "quote_price", "total_price", "price"]
        ];
    } else {
        $possible_fields = [
            "Cake" => ["cake_name", "item_name", "product_name", "name"],
            "Size" => ["size_name", "cake_size", "size"],
            "Flavor" => ["flavor", "cake_flavor"],
            "Quantity" => ["quantity", "qty"],
            "Total" => ["total_amount", "total_price", "amount", "grand_total"]
        ];
    }

    foreach ($possible_fields as $label => $keys) {
        $value = get_first_value($row, $keys, "");

        if ($value !== "") {
            $details[] = "<span><b>" . e($label) . ":</b> " . e($value) . "</span>";
        }
    }

    if (!$details) {
        return "<span>Additional details are unavailable.</span>";
    }

    return implode("", $details);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Archived Orders | CakeCraft Admin</title>
<link rel="stylesheet" href="../assets/css/brand-system.css">

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap');

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
    --brand-cream: #fffaf7;
    --brand-blush: #f7eeea;
    --brand-tint: #f4e5df;
    --brand-rose: #a65a56;
    --brand-rose-dark: #8c4845;
    --brand-ink: #3b2724;
    --brand-muted: #6f5d57;
    --brand-line: #efe0da;
    --brand-white: #ffffff;
    --brand-green: #426c4e;
    --brand-green-bg: #edf5ed;
    --brand-red: #963f3b;
    --brand-red-bg: #f9e9e7;
    --brand-shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
    --ease: cubic-bezier(.2, .7, .2, 1);
}

*, *::before, *::after { box-sizing: border-box; }

body {
    margin: 0;
    min-height: 100vh;
    background: var(--bg);
    color: var(--ink);
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 16px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}

:focus-visible {
    outline: 3px solid var(--rose);
    outline-offset: 3px;
    border-radius: 4px;
}

header {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
    padding: 14px 5%;
    background: rgba(255, 250, 247, 0.96);
    border-bottom: 1px solid var(--line);
    box-shadow: 0 2px 14px rgba(59, 39, 36, 0.05);
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
    color: var(--ink);
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 24px;
    font-weight: 800;
    line-height: 1;
    text-decoration: none;
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

.brand-name span.brand-craft { color: var(--craft-color); }

.admin-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border: 1px solid var(--line);
    border-radius: 999px;
    background: var(--bg-soft);
    color: var(--rose-deep);
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.header-links {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.header-links a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 8px 16px;
    border: 1.5px solid var(--rose);
    border-radius: 999px;
    color: var(--ink);
    background: transparent;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: background .2s var(--ease), border-color .2s var(--ease), color .2s var(--ease);
}

.header-links a:hover {
    border-color: var(--rose-deep);
    background: var(--bg-soft);
    color: var(--rose-deep);
}

main {
    width: min(1200px, 92%);
    margin: 34px auto 48px;
}

.page-heading { margin-bottom: 24px; }

h1, .section-title {
    color: var(--ink);
    font-family: 'Playfair Display', Georgia, serif;
    font-weight: 700;
}

h1 {
    margin: 0 0 6px;
    font-size: clamp(27px, 3vw, 34px);
    line-height: 1.25;
}

.subtitle {
    max-width: 760px;
    margin: 0;
    color: var(--ink-soft);
    font-size: 14px;
}

.notice {
    margin: 0 0 20px;
    padding: 12px 15px;
    border: 1px solid #d7e8d8;
    border-radius: 10px;
    background: var(--brand-green-bg);
    color: var(--brand-green);
    font-size: 14px;
    font-weight: 600;
}

.notice.error {
    border-color: #efd1cd;
    background: var(--brand-red-bg);
    color: var(--brand-red);
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 22px;
}

.stat {
    min-width: 0;
    padding: 19px 20px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: var(--surface);
    box-shadow: var(--brand-shadow);
}

.stat .label {
    color: var(--ink-soft);
    font-size: 12px;
    font-weight: 600;
}

.stat .number {
    margin-top: 4px;
    color: var(--rose-deep);
    font-size: 28px;
    font-weight: 700;
    line-height: 1.3;
    font-variant-numeric: tabular-nums;
}

.toolbar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 27px;
    padding: 14px;
    border: 1px solid var(--line);
    border-radius: 13px;
    background: var(--surface);
    box-shadow: var(--brand-shadow);
}

.toolbar input {
    flex: 1;
    min-width: 220px;
    min-height: 43px;
    padding: 10px 13px;
    border: 1px solid var(--line);
    border-radius: 9px;
    outline: none;
    background: var(--bg);
    color: var(--ink);
    font: inherit;
    font-size: 14px;
    transition: border-color .2s ease, box-shadow .2s ease;
}

.toolbar input:focus {
    border-color: var(--rose-deep);
    background: var(--surface);
    box-shadow: 0 0 0 3px rgba(196, 124, 120, 0.16);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 40px;
    padding: 9px 16px;
    border: 0;
    border-radius: 999px;
    cursor: pointer;
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.3;
    text-decoration: none;
    transition: background .2s var(--ease), transform .2s var(--ease);
}

.btn:hover { transform: translateY(-1px); }

.btn-primary {
    background: var(--rose-deep);
    color: #fff;
}

.btn-primary:hover { background: var(--rose-darker); }

.btn-secondary {
    background: var(--bg-soft);
    color: var(--ink);
    border: 1px solid var(--line);
}

.btn-secondary:hover { background: var(--bg-tint); }

.btn-restore {
    background: var(--brand-green-bg);
    color: var(--brand-green);
    border: 1px solid #d7e8d8;
}

.btn-restore:hover { background: #dcecdc; }

.btn-delete {
    background: var(--brand-red-bg);
    color: var(--brand-red);
    border: 1px solid #efd1cd;
}

.btn-delete:hover {
    background: #f3d9d6;
    border-color: var(--brand-red);
}

.section-title {
    margin: 28px 0 13px;
    font-size: 23px;
    line-height: 1.3;
}

.cards {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.card {
    min-width: 0;
    padding: 18px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: var(--surface);
    box-shadow: var(--brand-shadow);
    transition: border-color .2s ease, box-shadow .2s ease;
}

.card:hover {
    border-color: #dfc2bc;
    box-shadow: 0 10px 26px rgba(73, 48, 45, 0.08);
}

.card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.card h3 {
    margin: 0;
    color: var(--ink);
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 18px;
    font-weight: 700;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.tag {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    background: var(--bg-soft);
    color: var(--rose-darker);
    font-size: 11px;
    font-weight: 700;
    line-height: 1.4;
    text-transform: capitalize;
}

.tag.status-completed {
    background: var(--brand-green-bg);
    color: var(--brand-green);
}

.tag.status-cancelled {
    background: var(--brand-red-bg);
    color: var(--brand-red);
}

.meta {
    display: grid;
    gap: 7px;
    margin: 12px 0;
    color: var(--ink-soft);
    font-size: 13px;
    overflow-wrap: anywhere;
}

.meta b, .details b {
    color: var(--ink);
    font-weight: 700;
}

.details {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 11px 0 0;
    padding: 12px 13px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: var(--bg);
    color: var(--ink-soft);
    font-size: 13px;
    overflow-wrap: anywhere;
}

.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 14px;
    padding-top: 13px;
    border-top: 1px solid var(--line);
}

.card-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.card-actions form { margin: 0; }

.archived-date {
    color: var(--ink-soft);
    font-size: 11px;
}

.empty {
    padding: 27px 20px;
    border: 1px dashed var(--line);
    border-radius: 13px;
    background: var(--surface);
    color: var(--ink-soft);
    font-size: 14px;
    text-align: center;
}

@media (max-width: 760px) {
    header { padding: 14px 4%; }
    main { width: 92%; margin-top: 27px; }
    .stats { grid-template-columns: 1fr; gap: 10px; }
    .stat { padding: 15px 17px; }
    .stat .number { font-size: 25px; }
    .cards { grid-template-columns: 1fr; }
}

@media (max-width: 480px) {
    .brand { font-size: 21px; }
    .header-links { width: 100%; }
    .header-links a { flex: 1; }
    .toolbar { padding: 11px; }
    .toolbar input { min-width: 100%; }
    .toolbar .btn { flex: 1; }
    .card { padding: 15px; }
    .card-head { flex-wrap: wrap; }
    .card-footer { align-items: flex-start; }
    .card-actions { width: 100%; }
    .card-actions form { flex: 1; }
    .card-actions .btn { width: 100%; }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { transition-duration: .01ms !important; }
}
</style>
</head>

<body>

<header>
    <div class="brand-wrap">
        <a class="brand" href="dashboard.php" aria-label="CakeCraft Admin Dashboard">
            <span class="brand-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3v3" />
                    <path d="M12 6c-1.1 0-2 .7-2 1.6.2.5.2.9.5 1.4" />
                    <path d="M5 12h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" />
                    <path d="M3 16c1.5 0 2.5-1 4-1s2.5 1 4 1 2.5-1 4-1 2.5 1 4 1 2-.5 2-1" />
                    <path d="M3 19h18" />
                </svg>
            </span>
            <span class="brand-name">Cake<span class="brand-craft">Craft</span></span>
        </a>

        <span class="admin-badge">ADMIN</span>
    </div>

    <nav class="header-links" aria-label="Admin navigation">
        <a href="orders.php">Manage Orders</a>
        <a href="dashboard.php">Dashboard</a>
    </nav>
</header>

<main>
    <div class="page-heading">
        <h1>Archived Orders</h1>
        <p class="subtitle">
            View archived regular orders and custom cake requests.
            Restore an item to return it to Manage Orders, or permanently delete it.
            Uploaded design images for custom requests will also be deleted.
        </p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="notice <?= $message_type === "error" ? "error" : "" ?>" role="status">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <section class="stats" aria-label="Archive summary">
        <div class="stat">
            <div class="label">Total Archived</div>
            <div class="number"><?= $total_archived ?></div>
        </div>

        <div class="stat">
            <div class="label">Regular Orders</div>
            <div class="number"><?= $regular_count ?></div>
        </div>

        <div class="stat">
            <div class="label">Custom Cake Requests</div>
            <div class="number"><?= $custom_count ?></div>
        </div>
    </section>

    <form class="toolbar" method="GET" role="search">
        <input
            type="text"
            name="search"
            value="<?= e($search) ?>"
            placeholder="Search by order ID or customer ID..."
            aria-label="Search by order ID or customer ID"
        >

        <button class="btn btn-primary" type="submit">Search</button>

        <?php if ($search !== ""): ?>
            <a class="btn btn-secondary" href="archive_orders.php">Clear</a>
        <?php endif; ?>
    </form>

    <!-- REGULAR ORDERS -->
    <h2 class="section-title">Regular Orders</h2>

    <?php if (!$orders): ?>
        <div class="empty">No archived regular orders found.</div>
    <?php else: ?>

        <div class="cards">

        <?php foreach ($orders as $order):

            $order_id = (int)($order["id"] ?? 0);
            $status = get_first_value($order, ["order_status", "status"]);

            $status_class = strtolower($status) === "completed"
                ? "status-completed"
                : (strtolower($status) === "cancelled" ? "status-cancelled" : "");

            $customer = get_first_value(
                $order,
                ["customer_name", "name"],
                "Customer ID: " . get_first_value($order, ["customer_id"], "—")
            );

            $payment_method = get_first_value($order, ["payment_method"]);
            $payment_status = get_first_value($order, ["payment_status"]);
            $archived_at = get_first_value($order, ["archived_at"]);

            $total = get_first_value(
                $order,
                ["total_amount", "total_price", "amount", "grand_total"]
            );

            $order_date = get_first_value(
                $order,
                ["order_date", "created_at", "date_ordered"]
            );

            $can_remove = in_array($status, ["Completed", "Cancelled"], true)
                && !empty($order["archived_at"]);

        ?>

            <article class="card">

                <div class="card-head">
                    <h3>Order #<?= $order_id ?></h3>
                    <span class="tag <?= e($status_class) ?>">
                        <?= e($status) ?>
                    </span>
                </div>

                <div class="meta">
                    <span><b>Customer:</b> <?= e($customer) ?></span>
                    <span><b>Order Date:</b> <?= e($order_date) ?></span>
                    <span><b>Payment:</b> <?= e($payment_method) ?> — <?= e($payment_status) ?></span>
                    <span><b>Total:</b> <?= format_money($total) ?></span>
                </div>

                <div class="details">
                    <?= display_order_details($order, false) ?>
                </div>

                <div class="card-footer">

                    <span class="archived-date">
                        Archived: <?= e($archived_at) ?>
                    </span>

                    <div class="card-actions">

                        <!-- RESTORE -->
                        <form
                            method="POST"
                            onsubmit="return confirm('Restore order #<?= $order_id ?> to Manage Orders?');"
                        >
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["archive_csrf"]) ?>">
                            <input type="hidden" name="order_id" value="<?= $order_id ?>">

                            <button class="btn btn-restore" type="submit" name="restore_order">
                                Restore
                            </button>
                        </form>

                        <!-- PERMANENT DELETE -->
                        <?php if ($can_remove): ?>
                            <form
                                method="POST"
                                onsubmit="return confirm('PERMANENTLY DELETE order #<?= $order_id ?>? This action cannot be undone.');"
                            >
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["archive_csrf"]) ?>">
                                <input type="hidden" name="order_id" value="<?= $order_id ?>">

                                <button class="btn btn-delete" type="submit" name="permanent_delete_order">
                                    Permanent Delete
                                </button>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>

            </article>

        <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!-- CUSTOM CAKE REQUESTS -->
    <h2 class="section-title">Custom Cake Requests</h2>

    <?php if (!$custom_requests): ?>
        <div class="empty">No archived custom cake requests found.</div>
    <?php else: ?>

        <div class="cards">

        <?php foreach ($custom_requests as $request):

            $request_id = (int)($request["id"] ?? 0);
            $status = get_first_value($request, ["request_status", "status"]);

            $status_class = strtolower($status) === "completed"
                ? "status-completed"
                : (strtolower($status) === "cancelled" ? "status-cancelled" : "");

            $customer = get_first_value(
                $request,
                ["customer_name", "name"],
                "Customer ID: " . get_first_value($request, ["customer_id"], "—")
            );

            $payment_method = get_first_value($request, ["payment_method"]);
            $payment_status = get_first_value($request, ["payment_status"]);
            $archived_at = get_first_value($request, ["archived_at"]);

            $price = get_first_value(
                $request,
                ["quoted_price", "quote_price", "total_price", "price"]
            );

            $request_date = get_first_value(
                $request,
                ["request_date", "created_at", "date_requested"]
            );

            $can_remove = in_array($status, ["Completed", "Cancelled"], true)
                && !empty($request["archived_at"]);

        ?>

            <article class="card">

                <div class="card-head">
                    <h3>Custom Request #<?= $request_id ?></h3>

                    <span class="tag <?= e($status_class) ?>">
                        <?= e($status) ?>
                    </span>
                </div>

                <div class="meta">
                    <span><b>Customer:</b> <?= e($customer) ?></span>
                    <span><b>Request Date:</b> <?= e($request_date) ?></span>
                    <span><b>Payment:</b> <?= e($payment_method) ?> — <?= e($payment_status) ?></span>
                    <span><b>Quoted Price:</b> <?= format_money($price) ?></span>
                </div>

                <div class="details">
                    <?= display_order_details($request, true) ?>
                </div>

                <div class="card-footer">

                    <span class="archived-date">
                        Archived: <?= e($archived_at) ?>
                    </span>

                    <div class="card-actions">

                        <!-- RESTORE -->
                        <form
                            method="POST"
                            onsubmit="return confirm('Restore custom request #<?= $request_id ?> to Manage Orders?');"
                        >
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["archive_csrf"]) ?>">
                            <input type="hidden" name="request_id" value="<?= $request_id ?>">

                            <button class="btn btn-restore" type="submit" name="restore_custom_request">
                                Restore
                            </button>
                        </form>

                        <!-- PERMANENT DELETE -->
                        <?php if ($can_remove): ?>
                            <form
                                method="POST"
                                onsubmit="return confirm('PERMANENTLY DELETE custom request #<?= $request_id ?> AND its uploaded design image? This action cannot be undone.');"
                            >
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION["archive_csrf"]) ?>">
                                <input type="hidden" name="request_id" value="<?= $request_id ?>">

                                <button class="btn btn-delete" type="submit" name="permanent_delete_custom_request">
                                    Permanent Delete
                                </button>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>

            </article>

        <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

</body>
</html>
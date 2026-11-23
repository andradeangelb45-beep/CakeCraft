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
            $stmt = $conn->prepare("UPDATE orders SET archived_at = NULL WHERE id = ? AND archived_at IS NOT NULL");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = "Order #{$order_id} restored successfully.";
            } else {
                $message = "Order not found or already restored.";
                $message_type = "error";
            }
            $stmt->close();
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
            $stmt = $conn->prepare("UPDATE custom_cake_requests SET archived_at = NULL WHERE id = ? AND archived_at IS NOT NULL");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = "Custom cake request #{$request_id} restored successfully.";
            } else {
                $message = "Request not found or already restored.";
                $message_type = "error";
            }
            $stmt->close();
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
    $stmt = $conn->prepare("SELECT * FROM orders WHERE archived_at IS NOT NULL AND (CAST(id AS CHAR) LIKE ? OR CAST(customer_id AS CHAR) LIKE ?) ORDER BY archived_at DESC");
    $stmt->bind_param("ss", $search_like, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
} else {
    $result = $conn->query("SELECT * FROM orders WHERE archived_at IS NOT NULL ORDER BY archived_at DESC");

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
    $stmt = $conn->prepare("SELECT * FROM custom_cake_requests WHERE archived_at IS NOT NULL AND (CAST(id AS CHAR) LIKE ? OR CAST(customer_id AS CHAR) LIKE ?) ORDER BY archived_at DESC");
    $stmt->bind_param("ss", $search_like, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $custom_requests[] = $row;
    }
    $stmt->close();
} else {
    $result = $conn->query("SELECT * FROM custom_cake_requests WHERE archived_at IS NOT NULL ORDER BY archived_at DESC");

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
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== "") {
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
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap');
:root{--rose:#b85c70;--rose-dark:#964354;--cream:#fff8f3;--pink:#f6e5e8;--text:#49343a;--muted:#88747a;--line:#ead8dc;--white:#fff;--green:#557b62;--red:#a54646}
*{box-sizing:border-box}
body{margin:0;background:var(--cream);color:var(--text);font-family:'DM Sans',sans-serif}
header{background:#fff;padding:18px 5%;display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;border-bottom:1px solid var(--line)}
.brand{font-size:22px;font-weight:700}.brand span{color:var(--rose)}.badge{font-size:11px;letter-spacing:1px;background:var(--pink);padding:5px 9px;border-radius:20px;margin-left:8px}
.header-links{display:flex;gap:10px;flex-wrap:wrap}
.header-links a{padding:9px 14px;border-radius:9px;text-decoration:none;color:var(--text);border:1px solid var(--line);font-weight:600;font-size:14px}
.header-links a:hover{background:var(--pink)}
main{width:min(1200px,92%);margin:30px auto}
h1{font-family:'Playfair Display',serif;margin:0 0 7px;font-size:32px}
.subtitle{color:var(--muted);margin:0 0 24px}
.stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px;margin-bottom:22px}
.stat{background:#fff;border:1px solid var(--line);border-radius:15px;padding:19px}
.stat .label{color:var(--muted);font-size:13px}.stat .number{font-size:27px;font-weight:700;margin-top:5px;color:var(--rose-dark)}
.toolbar{background:#fff;border:1px solid var(--line);border-radius:14px;padding:15px;margin-bottom:22px;display:flex;gap:10px;flex-wrap:wrap}
.toolbar input{flex:1;min-width:200px;padding:11px 13px;border:1px solid var(--line);border-radius:9px;font:inherit}
.btn{display:inline-block;border:0;border-radius:9px;padding:10px 14px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none}
.btn-primary{background:var(--rose);color:#fff}.btn-primary:hover{background:var(--rose-dark)}
.btn-secondary{background:#f3e6e8;color:var(--text)}.btn-restore{background:#e6f0e8;color:var(--green)}
.section-title{font-family:'Playfair Display',serif;font-size:23px;margin:26px 0 13px}
.cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.card{background:#fff;border:1px solid var(--line);border-radius:15px;padding:18px;box-shadow:0 3px 10px rgba(92,50,60,.04)}
.card-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:12px}
.card h3{margin:0;font-size:18px}
.tag{display:inline-block;padding:5px 9px;border-radius:20px;background:var(--pink);font-size:12px;font-weight:700;color:var(--rose-dark)}
.tag.status-completed{background:#dbeafe;color:#1d4ed8}
.tag.status-cancelled{background:#f6e5e8;color:#964354}
.meta{display:grid;gap:7px;font-size:14px;margin:12px 0}
.details{display:flex;flex-direction:column;gap:6px;font-size:14px;background:#fffaf8;padding:12px;border-radius:10px;margin:10px 0}
.card-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:14px;padding-top:13px;border-top:1px solid var(--line)}
.archived-date{font-size:12px;color:var(--muted)}
.notice{padding:12px 15px;border-radius:10px;margin-bottom:18px;background:#e8f2e9;color:#426c4e}.notice.error{background:#f8e5e5;color:var(--red)}
.empty{background:#fff;border:1px dashed var(--line);border-radius:14px;padding:28px;text-align:center;color:var(--muted)}
@media(max-width:760px){.stats{grid-template-columns:1fr}.cards{grid-template-columns:1fr}h1{font-size:27px}}
</style>
</head>
<body>
<header>
    <div class="brand">Cake<span>Craft</span> <span class="badge">ADMIN</span></div>
    <nav class="header-links">
        <a href="orders.php">Manage Orders</a>
        <a href="dashboard.php">Dashboard</a>
    </nav>
</header>
<main>
    <h1>Archived Orders</h1>
    <p class="subtitle">View archived regular orders and custom cake requests. Restore an item to return it to Manage Orders.</p>

    <?php if ($message !== ""): ?>
        <div class="notice <?= $message_type === "error" ? "error" : "" ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat"><div class="label">Total Archived</div><div class="number"><?= $total_archived ?></div></div>
        <div class="stat"><div class="label">Regular Orders</div><div class="number"><?= $regular_count ?></div></div>
        <div class="stat"><div class="label">Custom Cake Requests</div><div class="number"><?= $custom_count ?></div></div>
    </div>

    <form class="toolbar" method="GET">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by order ID or customer ID...">
        <button class="btn btn-primary" type="submit">Search</button>
        <?php if ($search !== ""): ?><a class="btn btn-secondary" href="archive_orders.php">Clear</a><?php endif; ?>
    </form>

    <h2 class="section-title">Regular Orders</h2>
    <?php if (!$orders): ?>
        <div class="empty">No archived regular orders found.</div>
    <?php else: ?>
        <div class="cards">
        <?php foreach ($orders as $order):
            $order_id = (int)($order["id"] ?? 0);
            $status = get_first_value($order, ["order_status", "status"]);
            $status_class = strtolower($status) === "completed" ? "status-completed" : (strtolower($status) === "cancelled" ? "status-cancelled" : "");
            $customer = get_first_value($order, ["customer_name", "name"], "Customer ID: " . get_first_value($order, ["customer_id"], "—"));
            $payment_method = get_first_value($order, ["payment_method"]);
            $payment_status = get_first_value($order, ["payment_status"]);
            $archived_at = get_first_value($order, ["archived_at"]);
            $total = get_first_value($order, ["total_amount", "total_price", "amount", "grand_total"]);
            $order_date = get_first_value($order, ["order_date", "created_at", "date_ordered"]);
        ?>
            <article class="card">
                <div class="card-head"><h3>Order #<?= $order_id ?></h3><span class="tag <?= e($status_class) ?>"><?= e($status) ?></span></div>
                <div class="meta">
                    <span><b>Customer:</b> <?= e($customer) ?></span>
                    <span><b>Order Date:</b> <?= e($order_date) ?></span>
                    <span><b>Payment:</b> <?= e($payment_method) ?> — <?= e($payment_status) ?></span>
                    <span><b>Total:</b> <?= format_money($total) ?></span>
                </div>
                <div class="details"><?= display_order_details($order, false) ?></div>
                <div class="card-footer">
                    <span class="archived-date">Archived: <?= e($archived_at) ?></span>
                    <form method="POST" onsubmit="return confirm('Restore order #<?= $order_id ?> to Manage Orders?');">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION["archive_csrf"]) ?>">
                        <input type="hidden" name="order_id" value="<?= $order_id ?>">
                        <button class="btn btn-restore" type="submit" name="restore_order">Restore</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="section-title">Custom Cake Requests</h2>
    <?php if (!$custom_requests): ?>
        <div class="empty">No archived custom cake requests found.</div>
    <?php else: ?>
        <div class="cards">
        <?php foreach ($custom_requests as $request):
            $request_id = (int)($request["id"] ?? 0);
            $status = get_first_value($request, ["request_status", "status"]);
            $status_class = strtolower($status) === "completed" ? "status-completed" : (strtolower($status) === "cancelled" ? "status-cancelled" : "");
            $customer = get_first_value($request, ["customer_name", "name"], "Customer ID: " . get_first_value($request, ["customer_id"], "—"));
            $payment_method = get_first_value($request, ["payment_method"]);
            $payment_status = get_first_value($request, ["payment_status"]);
            $archived_at = get_first_value($request, ["archived_at"]);
            $price = get_first_value($request, ["quoted_price", "quote_price", "total_price", "price"]);
            $request_date = get_first_value($request, ["request_date", "created_at", "date_requested"]);
        ?>
            <article class="card">
                <div class="card-head"><h3>Custom Request #<?= $request_id ?></h3><span class="tag <?= e($status_class) ?>"><?= e($status) ?></span></div>
                <div class="meta">
                    <span><b>Customer:</b> <?= e($customer) ?></span>
                    <span><b>Request Date:</b> <?= e($request_date) ?></span>
                    <span><b>Payment:</b> <?= e($payment_method) ?> — <?= e($payment_status) ?></span>
                    <span><b>Quoted Price:</b> <?= format_money($price) ?></span>
                </div>
                <div class="details"><?= display_order_details($request, true) ?></div>
                <div class="card-footer">
                    <span class="archived-date">Archived: <?= e($archived_at) ?></span>
                    <form method="POST" onsubmit="return confirm('Restore custom request #<?= $request_id ?> to Manage Orders?');">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION["archive_csrf"]) ?>">
                        <input type="hidden" name="request_id" value="<?= $request_id ?>">
                        <button class="btn btn-restore" type="submit" name="restore_custom_request">Restore</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

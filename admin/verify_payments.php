
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

    if (!isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        die("Invalid request.");
    }

    $type = $_POST["type"] ?? "";
    $id = (int) ($_POST["id"] ?? 0);
    $action = $_POST["action"] ?? "";
    $reason = trim($_POST["rejection_reason"] ?? "");

    if ($id <= 0 || !in_array($type, ["regular", "custom"])) {
        $message = "Invalid payment information.";
        $message_type = "error";
    } else {

        if ($type === "regular") {

            $stmt = $conn->prepare("
                SELECT customer_id, payment_status
                FROM orders
                WHERE id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $payment = $result->fetch_assoc();
            $stmt->close();

        } else {

            $stmt = $conn->prepare("
                SELECT customer_id, payment_status
                FROM custom_cake_requests
                WHERE id = ?
            ");
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

                $stmt = $conn->prepare("
                    UPDATE orders
                    SET payment_status = 'Paid',
                        payment_verified_at = NOW(),
                        payment_verified_by = ?,
                        payment_rejection_reason = NULL
                    WHERE id = ?
                    AND payment_status = 'Pending Verification'
                ");
                $stmt->bind_param("ii", $admin_id, $id);
                $stmt->execute();
                $stmt->close();

            } else {

                $stmt = $conn->prepare("
                    UPDATE custom_cake_requests
                    SET payment_status = 'Paid',
                        payment_verified_at = NOW(),
                        payment_verified_by = ?,
                        payment_rejection_reason = NULL
                    WHERE id = ?
                    AND payment_status = 'Pending Verification'
                ");
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

            $stmt = $conn->prepare("
                INSERT INTO notifications
                (customer_id, order_id, title, message, link)
                VALUES (?, ?, ?, ?, ?)
            ");
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

                    $stmt = $conn->prepare("
                        UPDATE orders
                        SET payment_status = 'Rejected',
                            payment_rejection_reason = ?,
                            payment_verified_at = NULL,
                            payment_verified_by = NULL
                        WHERE id = ?
                        AND payment_status = 'Pending Verification'
                    ");
                    $stmt->bind_param("si", $reason, $id);
                    $stmt->execute();
                    $stmt->close();

                } else {

                    $stmt = $conn->prepare("
                        UPDATE custom_cake_requests
                        SET payment_status = 'Rejected',
                            payment_rejection_reason = ?,
                            payment_verified_at = NULL,
                            payment_verified_by = NULL
                        WHERE id = ?
                        AND payment_status = 'Pending Verification'
                    ");
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

                $stmt = $conn->prepare("
                    INSERT INTO notifications
                    (customer_id, order_id, title, message, link)
                    VALUES (?, ?, ?, ?, ?)
                ");
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

$sql = "
    SELECT *
    FROM orders
    WHERE payment_status = 'Pending Verification'
    ORDER BY payment_submitted_at DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $regular_payments[] = $row;
    }
}

// FETCH CUSTOM CAKE PAYMENTS
$custom_payments = [];

$sql = "
    SELECT *
    FROM custom_cake_requests
    WHERE payment_status = 'Pending Verification'
    ORDER BY created_at DESC
";

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
<title>Payment Verification | CakeCraft</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, sans-serif;
    background: #fff8f6;
    color: #49352f;
    padding: 30px;
}

.container {
    max-width: 1200px;
    margin: auto;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.header h1 {
    color: #8b4d5c;
    margin-bottom: 8px;
}

.header p {
    color: #806b65;
}

.back-btn {
    display: inline-block;
    text-decoration: none;
    background: #8b4d5c;
    color: white;
    padding: 11px 18px;
    border-radius: 8px;
}

.summary {
    background: white;
    padding: 22px;
    border-radius: 12px;
    border: 1px solid #f0ded9;
    margin-bottom: 25px;
}

.summary h2 {
    font-size: 28px;
    color: #8b4d5c;
}

.alert {
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert.success {
    background: #e8f6e9;
    color: #276738;
}

.alert.error {
    background: #ffe9e7;
    color: #a3332a;
}

.section-title {
    margin: 30px 0 18px;
    color: #8b4d5c;
}

.payment-card {
    background: white;
    border: 1px solid #f0ded9;
    border-radius: 14px;
    padding: 22px;
    margin-bottom: 22px;
}

.payment-header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.payment-header h3 {
    margin-bottom: 6px;
}

.badge {
    background: #fff0d5;
    color: #986a1c;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 13px;
    height: fit-content;
}

.details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.detail {
    background: #fff8f6;
    border-radius: 9px;
    padding: 13px;
}

.detail small {
    display: block;
    color: #907c75;
    margin-bottom: 6px;
}

.detail strong {
    word-break: break-word;
}

.proof-section {
    margin: 15px 0 20px;
}

.proof-section h4 {
    margin-bottom: 10px;
}

.proof-image {
    display: block;
    max-width: 100%;
    width: 400px;
    max-height: 500px;
    object-fit: contain;
    border: 1px solid #ead8d3;
    border-radius: 10px;
    background: #fff;
}

.proof-link {
    display: inline-block;
    margin-top: 10px;
    color: #8b4d5c;
}

.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 20px;
}

.actions button {
    border: none;
    border-radius: 8px;
    padding: 12px 18px;
    cursor: pointer;
    font-weight: bold;
}

.verify-btn {
    background: #478254;
    color: white;
}

.reject-btn {
    background: #b64c4c;
    color: white;
}

.reject-form {
    width: 100%;
    margin-top: 12px;
    display: none;
}

.reject-form textarea {
    width: 100%;
    min-height: 90px;
    padding: 12px;
    border: 1px solid #d9c4be;
    border-radius: 8px;
    resize: vertical;
    margin-bottom: 10px;
    font-family: Arial, sans-serif;
}

.confirm-reject {
    background: #b64c4c;
    color: white;
}

.cancel-reject {
    background: #e8e0dc;
    color: #49352f;
}

.empty {
    text-align: center;
    background: white;
    border: 1px solid #f0ded9;
    border-radius: 12px;
    padding: 40px 20px;
    color: #806b65;
}

@media(max-width:600px) {
    body {
        padding: 15px;
    }

    .payment-card {
        padding: 15px;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <h1>Payment Verification</h1>
            <p>Review submitted GCash payments.</p>
        </div>

        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert <?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="summary">
        <h2><?= $total_pending ?></h2>
        <p>Payments awaiting verification</p>
    </div>

    <?php if ($total_pending === 0): ?>

        <div class="empty">
            <h3>No pending payments</h3>
            <p>There are currently no payments waiting for verification.</p>
        </div>

    <?php else: ?>

        <!-- REGULAR ORDERS -->
        <?php if (!empty($regular_payments)): ?>

            <h2 class="section-title">Regular Cake Orders</h2>

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

                <div class="payment-card">

                    <div class="payment-header">
                        <div>
                            <h3>Order #<?= $order_id ?></h3>
                            <p>Customer ID: <?= $customer_id ?></p>
                        </div>

                        <span class="badge">Pending Verification</span>
                    </div>

                    <div class="details">

                        <div class="detail">
                            <small>Amount to Pay</small>
                            <strong>₱<?= number_format($payable, 2) ?></strong>
                        </div>

                        <div class="detail">
                            <small>GCash Reference Number</small>
                            <strong><?= htmlspecialchars($payment["gcash_reference_number"] ?? "") ?></strong>
                        </div>

                        <div class="detail">
                            <small>Delivery Method</small>
                            <strong><?= htmlspecialchars($payment["delivery_method"] ?? "") ?></strong>
                        </div>

                        <div class="detail">
                            <small>Payment Submitted</small>
                            <strong><?= htmlspecialchars($payment["payment_submitted_at"] ?? "N/A") ?></strong>
                        </div>

                    </div>

                    <div class="proof-section">
                        <h4>Payment Screenshot</h4>

                        <?php if ($proof_name !== ""): ?>

                            <a href="<?= htmlspecialchars($proof_path) ?>" target="_blank">
                                <img
                                    src="<?= htmlspecialchars($proof_path) ?>"
                                    class="proof-image"
                                    alt="Payment proof"
                                >
                            </a>

                            <a href="<?= htmlspecialchars($proof_path) ?>"
                               target="_blank"
                               class="proof-link">
                                Open Full Image
                            </a>

                        <?php else: ?>
                            <p>No payment screenshot found.</p>
                        <?php endif; ?>
                    </div>

                    <div class="actions">

                        <form method="POST"
                              onsubmit="return confirm('Verify this payment and mark it as Paid?');">

                            <input type="hidden" name="csrf_token"
                                   value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="type" value="regular">
                            <input type="hidden" name="id" value="<?= $order_id ?>">
                            <input type="hidden" name="action" value="verify">

                            <button type="submit" class="verify-btn">
                                ✓ Verify Payment
                            </button>
                        </form>

                        <button type="button"
                                class="reject-btn"
                                onclick="toggleReject('regular-<?= $order_id ?>')">
                            ✕ Reject Payment
                        </button>

                    </div>

                    <form method="POST"
                          class="reject-form"
                          id="regular-<?= $order_id ?>">

                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="type" value="regular">
                        <input type="hidden" name="id" value="<?= $order_id ?>">
                        <input type="hidden" name="action" value="reject">

                        <textarea
                            name="rejection_reason"
                            placeholder="Enter the reason for rejecting this payment..."
                            required
                        ></textarea>

                        <button type="submit" class="confirm-reject">
                            Confirm Rejection
                        </button>

                        <button type="button"
                                class="cancel-reject"
                                onclick="toggleReject('regular-<?= $order_id ?>')">
                            Cancel
                        </button>
                    </form>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>


        <!-- CUSTOM CAKE REQUESTS -->
        <?php if (!empty($custom_payments)): ?>

            <h2 class="section-title">Custom Cake Payments</h2>

            <?php foreach ($custom_payments as $payment): ?>

                <?php
                $request_id = (int) $payment["id"];
                $customer_id = (int) $payment["customer_id"];
                $payable = (float) $payment["quoted_price"];

                $proof_name = basename($payment["payment_proof"] ?? "");
                $proof_path = "../assets/images/payment_proofs/" . rawurlencode($proof_name);
                ?>

                <div class="payment-card">

                    <div class="payment-header">
                        <div>
                            <h3>Custom Request #<?= $request_id ?></h3>
                            <p>Customer ID: <?= $customer_id ?></p>
                        </div>

                        <span class="badge">Pending Verification</span>
                    </div>

                    <div class="details">

                        <div class="detail">
                            <small>Amount to Pay</small>
                            <strong>₱<?= number_format($payable, 2) ?></strong>
                        </div>

                        <div class="detail">
                            <small>GCash Reference Number</small>
                            <strong><?= htmlspecialchars($payment["gcash_reference"] ?? "") ?></strong>
                        </div>

                        <div class="detail">
                            <small>Cake Size</small>
                            <strong><?= htmlspecialchars($payment["cake_size"] ?? "") ?></strong>
                        </div>

                        <div class="detail">
                            <small>Payment Submitted</small>
                            <strong><?= htmlspecialchars($payment["payment_submitted_at"] ?? "N/A") ?></strong>
                        </div>

                    </div>

                    <div class="proof-section">
                        <h4>Payment Screenshot</h4>

                        <?php if ($proof_name !== ""): ?>

                            <a href="<?= htmlspecialchars($proof_path) ?>" target="_blank">
                                <img
                                    src="<?= htmlspecialchars($proof_path) ?>"
                                    class="proof-image"
                                    alt="Payment proof"
                                >
                            </a>

                            <a href="<?= htmlspecialchars($proof_path) ?>"
                               target="_blank"
                               class="proof-link">
                                Open Full Image
                            </a>

                        <?php else: ?>
                            <p>No payment screenshot found.</p>
                        <?php endif; ?>
                    </div>

                    <div class="actions">

                        <form method="POST"
                              onsubmit="return confirm('Verify this payment and mark it as Paid?');">

                            <input type="hidden" name="csrf_token"
                                   value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="type" value="custom">
                            <input type="hidden" name="id" value="<?= $request_id ?>">
                            <input type="hidden" name="action" value="verify">

                            <button type="submit" class="verify-btn">
                                ✓ Verify Payment
                            </button>
                        </form>

                        <button type="button"
                                class="reject-btn"
                                onclick="toggleReject('custom-<?= $request_id ?>')">
                            ✕ Reject Payment
                        </button>

                    </div>

                    <form method="POST"
                          class="reject-form"
                          id="custom-<?= $request_id ?>">

                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="type" value="custom">
                        <input type="hidden" name="id" value="<?= $request_id ?>">
                        <input type="hidden" name="action" value="reject">

                        <textarea
                            name="rejection_reason"
                            placeholder="Enter the reason for rejecting this payment..."
                            required
                        ></textarea>

                        <button type="submit" class="confirm-reject">
                            Confirm Rejection
                        </button>

                        <button type="button"
                                class="cancel-reject"
                                onclick="toggleReject('custom-<?= $request_id ?>')">
                            Cancel
                        </button>
                    </form>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
function toggleReject(id) {
    const form = document.getElementById(id);

    if (form.style.display === "block") {
        form.style.display = "none";
    } else {
        form.style.display = "block";
    }
}
</script>

</body>
</html>

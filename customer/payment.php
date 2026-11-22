<?php
session_start();
require_once "../database.php";

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

$customer_id = (int) $_SESSION["customer_id"];

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$type = $_GET["type"] ?? "";
$id = (int) ($_GET["id"] ?? 0);

if (!in_array($type, ["regular", "custom"], true) || $id <= 0) {
    header("Location: my_orders.php");
    exit();
}

$error = "";
$success = isset($_GET["success"]);
$order = null;
$amount = 0;
$description = "";
$payment_status = "Unpaid";
$can_submit = false;

/* ==========================================
   GET ORDER INFORMATION
========================================== */

if ($type === "regular") {

    $stmt = $conn->prepare("
        SELECT
            id,
            cake_id,
            customer_id,
            delivery_method,
            delivery_fee,
            total_price,
            quantity,
            order_status,
            payment_status,
            gcash_reference_number,
            payment_proof,
            payment_rejection_reason
        FROM orders
        WHERE id = ?
        AND customer_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $id, $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    $stmt->close();

    if (!$order) {
        header("Location: my_orders.php");
        exit();
    }

    $order_status = strtolower(trim($order["order_status"] ?? ""));
    $payment_status = $order["payment_status"] ?? "Unpaid";

    $delivery_method = strtolower(
        trim($order["delivery_method"] ?? "")
    );

    $is_pickup = (
        $delivery_method === "pickup" ||
        $delivery_method === "pick-up"
    );

    $fee_is_set = (
        $order["delivery_fee"] !== null &&
        $order["delivery_fee"] !== ""
    );

    if ($is_pickup) {
        $delivery_fee = 0;
        $fee_is_set = true;
    } else {
        $delivery_fee = (float) ($order["delivery_fee"] ?? 0);
    }

    $subtotal = (float) ($order["total_price"] ?? 0);

    $amount = $subtotal + $delivery_fee;

    $description = "Regular Cake Order #" . $id;

    $can_submit = (
        $order_status === "approved" &&
        $fee_is_set &&
        in_array($payment_status, ["Unpaid", "Rejected"], true)
    );

} else {

    /* CUSTOM CAKE REQUEST */

    $stmt = $conn->prepare("
        SELECT
            id,
            customer_id,
            request_status,
            quoted_price,
            payment_method,
            payment_status,
            gcash_reference,
            payment_proof,
            payment_rejection_reason
        FROM custom_cake_requests
        WHERE id = ?
        AND customer_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $id, $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    $stmt->close();

    if (!$order) {
        header("Location: my_orders.php");
        exit();
    }

    $order_status = strtolower(trim($order["request_status"] ?? ""));
    $payment_status = $order["payment_status"] ?? "Unpaid";

    $has_quote = (
        $order["quoted_price"] !== null &&
        $order["quoted_price"] !== ""
    );

    $amount = (float) ($order["quoted_price"] ?? 0);

    $description = "Custom Cake Request #" . $id;

    $can_submit = (
        $order_status === "approved" &&
        $has_quote &&
        $amount > 0 &&
        in_array($payment_status, ["Unpaid", "Rejected"], true)
    );
}

/* ==========================================
   HANDLE PAYMENT SUBMISSION
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $submitted_token)) {

        $error = "Invalid request. Please refresh the page and try again.";

    } elseif (!$can_submit) {

        $error = "This order is not currently eligible for payment.";

    } else {

        $reference_number = trim(
            $_POST["reference_number"] ?? ""
        );

        if ($reference_number === "") {

            $error = "Please enter your GCash reference number.";

        } elseif (strlen($reference_number) < 6) {

            $error = "Please enter a valid reference number.";

        } elseif (strlen($reference_number) > 100) {

            $error = "Reference number is too long.";

        } elseif (
            !isset($_FILES["payment_proof"]) ||
            $_FILES["payment_proof"]["error"] !== UPLOAD_ERR_OK
        ) {

            $error = "Please upload your payment screenshot.";

        } else {

            $file = $_FILES["payment_proof"];

            $max_size = 5 * 1024 * 1024; // 5 MB

            if ($file["size"] > $max_size) {

                $error = "The image must not exceed 5 MB.";

            } elseif ($file["size"] <= 0) {

                $error = "The uploaded file is empty.";

            } else {

                /* VERIFY IMAGE MIME TYPE */

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($file["tmp_name"]);

                $allowed_types = [
                    "image/jpeg" => "jpg",
                    "image/png"  => "png",
                    "image/webp" => "webp"
                ];

                if (!isset($allowed_types[$mime_type])) {

                    $error = "Only JPG, PNG, and WEBP images are allowed.";

                } else {

                    /* CREATE UPLOAD DIRECTORY */

                    $upload_directory =
                        "../assets/images/payment_proofs/";

                    if (!is_dir($upload_directory)) {

                        if (!mkdir($upload_directory, 0755, true)) {
                            $error = "Unable to create payment upload folder.";
                        }

                    }

                    /* PREVENT DIRECTORY LISTING AND PHP EXECUTION */

                    $htaccess_path = $upload_directory . ".htaccess";

                    if (
                        !$error &&
                        !file_exists($htaccess_path)
                    ) {

                        $htaccess_content = "
Options -Indexes
<FilesMatch \"\\.(php|phtml|phar)$\">
Require all denied
</FilesMatch>
";

                        @file_put_contents(
                            $htaccess_path,
                            $htaccess_content
                        );
                    }

                    if (!$error && !is_writable($upload_directory)) {

                        $error = "The payment upload folder is not writable.";

                    }

                    if (!$error) {

                        /* GENERATE SAFE FILE NAME */

                        $extension = $allowed_types[$mime_type];

                        $new_filename =
                            "payment_" .
                            $customer_id . "_" .
                            $type . "_" .
                            $id . "_" .
                            bin2hex(random_bytes(12)) .
                            "." . $extension;

                        $destination =
                            $upload_directory . $new_filename;

                        $database_path =
                            "assets/images/payment_proofs/" .
                            $new_filename;

                        if (
                            !move_uploaded_file(
                                $file["tmp_name"],
                                $destination
                            )
                        ) {

                            $error = "Unable to upload payment proof.";

                        } else {

                            /* ==================================
                               SAVE REGULAR ORDER PAYMENT
                            ================================== */

                            if ($type === "regular") {

                                $stmt = $conn->prepare("
                                    UPDATE orders
                                    SET
                                        payment_method = 'GCash',
                                        payment_status = 'Pending Verification',
                                        gcash_reference_number = ?,
                                        payment_proof = ?,
                                        payment_submitted_at = NOW(),
                                        payment_verified_at = NULL,
                                        payment_verified_by = NULL,
                                        payment_rejection_reason = NULL
                                    WHERE id = ?
                                    AND customer_id = ?
                                    AND order_status = 'Approved'
                                    AND payment_status IN ('Unpaid', 'Rejected')
                                ");

                                $stmt->bind_param(
                                    "ssii",
                                    $reference_number,
                                    $database_path,
                                    $id,
                                    $customer_id
                                );

                            } else {

                                /* ==================================
                                   SAVE CUSTOM REQUEST PAYMENT
                                ================================== */

                                $stmt = $conn->prepare("
                                    UPDATE custom_cake_requests
                                    SET
                                        payment_method = 'GCash',
                                        payment_status = 'Pending Verification',
                                        gcash_reference = ?,
                                        payment_proof = ?,
                                        payment_verified_at = NULL,
                                        payment_verified_by = NULL,
                                        payment_rejection_reason = NULL
                                    WHERE id = ?
                                    AND customer_id = ?
                                    AND request_status = 'Approved'
                                    AND payment_status IN ('Unpaid', 'Rejected')
                                ");

                                $stmt->bind_param(
                                    "ssii",
                                    $reference_number,
                                    $database_path,
                                    $id,
                                    $customer_id
                                );

                            }

                            if ($stmt->execute()) {

                                if ($stmt->affected_rows > 0) {

                                    $stmt->close();

                                    header(
                                        "Location: payment.php?type=" .
                                        urlencode($type) .
                                        "&id=" . $id .
                                        "&success=1"
                                    );

                                    exit();

                                } else {

                                    $error = "Your payment could not be submitted. Please check your order status.";

                                    $stmt->close();

                                    /* REMOVE UNUSED UPLOAD */

                                    if (file_exists($destination)) {
                                        @unlink($destination);
                                    }

                                }

                            } else {

                                $error = "Something went wrong while saving your payment.";

                                $stmt->close();

                                if (file_exists($destination)) {
                                    @unlink($destination);
                                }

                            }

                        }

                    }

                }

            }

        }

    }

}

/* ==========================================
   REFRESH PAYMENT STATUS AFTER SUBMISSION
========================================== */

if ($type === "regular") {

    $stmt = $conn->prepare("
        SELECT
            payment_status,
            gcash_reference_number,
            payment_rejection_reason
        FROM orders
        WHERE id = ?
        AND customer_id = ?
    ");

} else {

    $stmt = $conn->prepare("
        SELECT
            payment_status,
            gcash_reference,
            payment_rejection_reason
        FROM custom_cake_requests
        WHERE id = ?
        AND customer_id = ?
    ");

}

$stmt->bind_param("ii", $id, $customer_id);
$stmt->execute();

$payment_result = $stmt->get_result()->fetch_assoc();

$stmt->close();

if ($payment_result) {

    $payment_status = $payment_result["payment_status"] ?? "Unpaid";

    if ($type === "regular") {
        $saved_reference =
            $payment_result["gcash_reference_number"] ?? "";
    } else {
        $saved_reference =
            $payment_result["gcash_reference"] ?? "";
    }

    $rejection_reason =
        $payment_result["payment_rejection_reason"] ?? "";

} else {

    $saved_reference = "";
    $rejection_reason = "";

}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>GCash Payment | CakeCraft</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

<style>

:root {
    --bg: #fffaf7;
    --bg-soft: #f7eeea;
    --surface: #ffffff;
    --ink: #3b2724;
    --ink-soft: #6f5d57;
    --rose: #c47c78;
    --rose-deep: #a65a56;
    --rose-darker: #8c4845;
    --line: #efe0da;
    --line-strong: #e0cbc3;
    --body: 'DM Sans', sans-serif;
    --display: 'Playfair Display', Georgia, serif;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: var(--body);
}

body {
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
    line-height: 1.6;
}

header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 6%;
    background: rgba(255,250,247,.95);
    border-bottom: 1px solid var(--line);
}

.logo {
    font-family: var(--display);
    font-size: 28px;
    font-weight: 700;
}

.logo span {
    color: var(--rose);
}

header a {
    text-decoration: none;
    color: var(--ink);
    font-weight: 600;
}

.page-heading {
    padding: 40px 6%;
    background: var(--bg-soft);
    border-bottom: 1px solid var(--line);
}

.page-heading h1 {
    font-family: var(--display);
    font-size: clamp(30px, 4vw, 42px);
}

.page-heading p {
    color: var(--ink-soft);
}

.container {
    max-width: 850px;
    margin: auto;
    padding: 35px 20px 70px;
}

.card {
    background: white;
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 22px;
    box-shadow: 0 6px 24px rgba(73,48,45,.06);
}

.card h2 {
    font-family: var(--display);
    font-size: 24px;
    margin-bottom: 15px;
}

.order-info {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--line);
    margin-bottom: 18px;
}

.order-info p {
    color: var(--ink-soft);
    font-size: 14px;
}

.amount-box {
    background: var(--bg-soft);
    padding: 20px;
    border-radius: 12px;
    margin-top: 20px;
}

.amount-box .label {
    color: var(--ink-soft);
    font-size: 14px;
}

.amount {
    font-family: var(--display);
    font-size: 36px;
    font-weight: 700;
    color: var(--rose-deep);
}

.qr-placeholder {
    width: 100%;
    max-width: 320px;
    min-height: 320px;
    margin: 20px auto;
    border: 2px dashed var(--line-strong);
    border-radius: 16px;
    background: #fffaf7;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 30px;
}

.qr-placeholder-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.qr-placeholder .qr-icon {
    width: 90px;
    height: 90px;
    border: 5px solid var(--rose);
    border-radius: 12px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 35px;
    color: var(--rose-deep);
    font-weight: 700;
}

.qr-placeholder strong {
    font-size: 17px;
}

.qr-placeholder p {
    color: var(--ink-soft);
    font-size: 13px;
}

.instructions {
    padding-left: 22px;
    color: var(--ink-soft);
}

.instructions li {
    margin-bottom: 8px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.form-group input[type="text"],
.form-group input[type="file"] {
    width: 100%;
    padding: 13px 15px;
    border: 1px solid var(--line-strong);
    border-radius: 10px;
    background: white;
    font-size: 15px;
}

.form-group input:focus {
    outline: 2px solid var(--rose);
    outline-offset: 2px;
}

.hint {
    color: var(--ink-soft);
    font-size: 13px;
    margin-top: 6px;
}

.preview {
    display: none;
    width: 100%;
    max-width: 350px;
    max-height: 400px;
    object-fit: contain;
    margin-top: 15px;
    border-radius: 12px;
    border: 1px solid var(--line);
}

.btn {
    display: inline-block;
    width: 100%;
    border: none;
    border-radius: 999px;
    padding: 14px 22px;
    background: var(--rose-deep);
    color: white;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: .2s;
}

.btn:hover {
    background: var(--rose-darker);
}

.btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.back-btn {
    display: inline-block;
    text-decoration: none;
    color: var(--rose-deep);
    font-weight: 600;
    margin-top: 18px;
}

.alert {
    padding: 15px 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert.error {
    background: #fdecea;
    border: 1px solid #f3c7c2;
    color: #9b1c1c;
}

.alert.success {
    background: #e8f6ec;
    border: 1px solid #bfe3cb;
    color: #17602f;
}

.status {
    display: inline-block;
    padding: 6px 13px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
}

.status.unpaid {
    background: #fff1d6;
    color: #8a5a00;
}

.status.verification {
    background: #e3efff;
    color: #1f559a;
}

.status.paid {
    background: #e5f3e8;
    color: #1f6a37;
}

.status.rejected {
    background: #fde8e6;
    color: #9b1c1c;
}

.rejection {
    padding: 13px;
    background: #fdecea;
    color: #9b1c1c;
    border: 1px solid #f3c7c2;
    border-radius: 10px;
    margin: 15px 0;
}

@media (max-width: 600px) {

    header {
        padding: 14px 5%;
    }

    .container {
        padding: 25px 15px 50px;
    }

    .card {
        padding: 20px;
    }

    .amount {
        font-size: 30px;
    }

    .qr-placeholder {
        min-height: 260px;
    }

}

</style>
</head>

<body>

<header>

    <div class="logo">Cake<span>Craft</span></div>

    <a href="my_orders.php">← My Orders</a>

</header>

<section class="page-heading">

    <h1>GCash Payment</h1>

    <p>Complete your payment and submit your proof of payment.</p>

</section>

<div class="container">

    <?php if ($success): ?>

        <div class="alert success">

            <strong>Payment submitted successfully!</strong><br>

            Your payment proof has been sent to the admin
            for verification. You can check your payment status
            in My Orders.

        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="alert error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>

    <!-- ORDER SUMMARY -->

    <div class="card">

        <h2>Order Summary</h2>

        <div class="order-info">

            <div>
                <p>Order type</p>
                <strong>
                    <?php echo $type === "regular"
                        ? "Regular Cake Order"
                        : "Custom Cake Request"; ?>
                </strong>
            </div>

            <div>
                <p>Order number</p>
                <strong>#<?php echo $id; ?></strong>
            </div>

        </div>

        <p>
            <?php echo htmlspecialchars($description); ?>
        </p>

        <div class="amount-box">

            <p class="label">Total amount to pay</p>

            <div class="amount">
                ₱<?php echo number_format($amount, 2); ?>
            </div>

        </div>

    </div>

    <!-- QR PLACEHOLDER -->

    <div class="card">

        <h2>Pay using GCash</h2>

        <div class="qr-placeholder">

            <div class="qr-placeholder-inner">

                <div class="qr-icon">
                    ₱
                </div>

                <strong>GCash QR Placeholder</strong>

                <p>
                    Your actual GCash QR code<br>
                    will be displayed here.
                </p>

            </div>

        </div>

        <ol class="instructions">

            <li>Open your GCash application.</li>

            <li>Select Scan QR.</li>

            <li>Scan the CakeCraft GCash QR code.</li>

            <li>Enter the exact amount shown above.</li>

            <li>Complete your payment.</li>

            <li>Save a screenshot of your successful transaction.</li>

        </ol>

    </div>

    <!-- PAYMENT SUBMISSION -->

    <div class="card">

        <h2>Submit Payment Proof</h2>

        <?php if ($payment_status === "Pending Verification"): ?>

            <span class="status verification">
                Pending Verification
            </span>

            <p style="margin-top:12px;">
                Your payment proof has already been submitted.
                Please wait for the admin to verify it.
            </p>

        <?php elseif ($payment_status === "Paid"): ?>

            <span class="status paid">
                Paid
            </span>

            <p style="margin-top:12px;">
                Your payment has already been verified.
            </p>

        <?php elseif (!$can_submit): ?>

            <p>
                This order is not currently eligible for payment.
                Please return to My Orders to check its status.
            </p>

        <?php else: ?>

            <?php if ($payment_status === "Rejected"): ?>

                <span class="status rejected">
                    Payment Rejected
                </span>

                <?php if (!empty($rejection_reason)): ?>

                    <div class="rejection">

                        <strong>Reason for rejection:</strong><br>

                        <?php echo nl2br(htmlspecialchars($rejection_reason)); ?>

                    </div>

                <?php endif; ?>

                <p style="margin:12px 0;">
                    Please correct the issue and submit a new payment proof.
                </p>

            <?php else: ?>

                <span class="status unpaid">
                    Unpaid
                </span>

            <?php endif; ?>

            <form method="POST"
                  enctype="multipart/form-data"
                  style="margin-top:20px;">

                <input type="hidden"
                       name="csrf_token"
                       value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

                <div class="form-group">

                    <label for="reference_number">
                        GCash Reference Number
                    </label>

                    <input
                        type="text"
                        name="reference_number"
                        id="reference_number"
                        maxlength="100"
                        value="<?php echo htmlspecialchars($saved_reference ?? ""); ?>"
                        placeholder="Enter your GCash reference number"
                        required>

                    <p class="hint">
                        Enter the reference number shown in your GCash transaction.
                    </p>

                </div>

                <div class="form-group">

                    <label for="payment_proof">
                        Upload Payment Screenshot
                    </label>

                    <input
                        type="file"
                        name="payment_proof"
                        id="payment_proof"
                        accept="image/jpeg,image/png,image/webp"
                        required>

                    <p class="hint">
                        JPG, PNG, or WEBP only. Maximum file size: 5 MB.
                    </p>

                    <img
                        id="image_preview"
                        class="preview"
                        alt="Payment proof preview">

                </div>

                <button type="submit" class="btn">
                    Submit Payment
                </button>

            </form>

        <?php endif; ?>

    </div>

    <a href="my_orders.php" class="back-btn">
        ← Back to My Orders
    </a>

</div>

<script>

/* PAYMENT PROOF PREVIEW */

const fileInput = document.getElementById("payment_proof");
const preview = document.getElementById("image_preview");

if (fileInput && preview) {

    fileInput.addEventListener("change", function () {

        const file = this.files[0];

        if (!file) {
            preview.style.display = "none";
            preview.src = "";
            return;
        }

        const allowedTypes = [
            "image/jpeg",
            "image/png",
            "image/webp"
        ];

        if (!allowedTypes.includes(file.type)) {

            alert("Please select a JPG, PNG, or WEBP image.");

            this.value = "";
            preview.style.display = "none";

            return;
        }

        if (file.size > 5 * 1024 * 1024) {

            alert("The image must not exceed 5 MB.");

            this.value = "";
            preview.style.display = "none";

            return;
        }

        preview.src = URL.createObjectURL(file);
        preview.style.display = "block";

    });

}

</script>

</body>
</html>

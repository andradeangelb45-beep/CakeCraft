
<?php
session_start();
require_once "../database.php";

// ==========================================
// CHECK ADMIN LOGIN
// ==========================================

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

// ==========================================
// CSRF TOKEN
// ==========================================

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";

// ==========================================
// GET CURRENT GCash QR
// ==========================================

$stmt = $conn->prepare("
    SELECT gcash_qr_image
    FROM payment_settings
    WHERE id = 1
    LIMIT 1
");

$stmt->execute();
$result = $stmt->get_result();
$settings = $result->fetch_assoc();
$stmt->close();

$current_qr = $settings["gcash_qr_image"] ?? null;

// ==========================================
// HANDLE QR UPLOAD
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $submitted_token)) {

        $error = "Invalid request. Please refresh the page and try again.";

    } elseif (
        !isset($_FILES["gcash_qr"]) ||
        $_FILES["gcash_qr"]["error"] !== UPLOAD_ERR_OK
    ) {

        $error = "Please select a GCash QR image to upload.";

    } else {

        $file = $_FILES["gcash_qr"];

        $max_size = 5 * 1024 * 1024; // 5 MB

        if ($file["size"] > $max_size) {

            $error = "The image must not exceed 5 MB.";

        } elseif ($file["size"] <= 0) {

            $error = "The uploaded file is empty.";

        } else {

            // ==========================================
            // VALIDATE IMAGE TYPE
            // ==========================================

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file["tmp_name"]);

            $allowed_types = [
                "image/jpeg" => "jpg",
                "image/png"  => "png",
                "image/webp" => "webp"
            ];

            if (!isset($allowed_types[$mime_type])) {

                $error = "Only JPG, PNG, and WEBP images are allowed.";

            } elseif (getimagesize($file["tmp_name"]) === false) {

                $error = "The uploaded file is not a valid image.";

            } else {

                // ==========================================
                // UPLOAD DIRECTORY
                // ==========================================

                $upload_directory = "../assets/images/payment/";

                if (!is_dir($upload_directory)) {

                    if (!mkdir($upload_directory, 0755, true)) {
                        $error = "Unable to create payment image folder.";
                    }

                }

                // ==========================================
                // PREVENT DIRECTORY LISTING / PHP EXECUTION
                // ==========================================

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

                if (
                    !$error &&
                    !is_writable($upload_directory)
                ) {

                    $error = "The payment image folder is not writable.";

                }

                // ==========================================
                // SAVE IMAGE
                // ==========================================

                if (!$error) {

                    $extension = $allowed_types[$mime_type];

                    $new_filename =
                        "gcash_qr_" .
                        bin2hex(random_bytes(16)) .
                        "." . $extension;

                    $destination =
                        $upload_directory . $new_filename;

                    $database_path =
                        "assets/images/payment/" .
                        $new_filename;

                    if (
                        !move_uploaded_file(
                            $file["tmp_name"],
                            $destination
                        )
                    ) {

                        $error = "Unable to upload GCash QR image.";

                    } else {

                        // ==========================================
                        // GET OLD IMAGE
                        // ==========================================

                        $old_qr = null;

                        $stmt = $conn->prepare("
                            SELECT gcash_qr_image
                            FROM payment_settings
                            WHERE id = 1
                            LIMIT 1
                        ");

                        $stmt->execute();

                        $old_result = $stmt->get_result();
                        $old_settings = $old_result->fetch_assoc();

                        if ($old_settings) {
                            $old_qr = $old_settings["gcash_qr_image"];
                        }

                        $stmt->close();

                        // ==========================================
                        // SAVE NEW IMAGE PATH TO DATABASE
                        // ==========================================

                        $stmt = $conn->prepare("
                            INSERT INTO payment_settings
                                (id, gcash_qr_image)
                            VALUES
                                (1, ?)
                            ON DUPLICATE KEY UPDATE
                                gcash_qr_image = VALUES(gcash_qr_image)
                        ");

                        $stmt->bind_param("s", $database_path);

                        if ($stmt->execute()) {

                            $stmt->close();

                            // ==========================================
                            // DELETE OLD QR AFTER SUCCESSFUL UPDATE
                            // ==========================================

                            if (
                                !empty($old_qr) &&
                                $old_qr !== $database_path &&
                                strpos(
                                    $old_qr,
                                    "assets/images/payment/"
                                ) === 0
                            ) {

                                $old_filename = basename($old_qr);

                                $old_file =
                                    $upload_directory . $old_filename;

                                if (
                                    is_file($old_file) &&
                                    $old_filename !== "."
                                ) {
                                    @unlink($old_file);
                                }

                            }

                            // Refresh current QR
                            $current_qr = $database_path;

                            $success = "GCash QR code uploaded successfully!";

                        } else {

                            $error = "Failed to save GCash QR settings.";

                            $stmt->close();

                            // Remove uploaded file if database update failed
                            if (is_file($destination)) {
                                @unlink($destination);
                            }

                        }

                    }

                }

            }

        }

    }

}

// ==========================================
// REFRESH CURRENT QR AFTER POST
// ==========================================

$stmt = $conn->prepare("
    SELECT gcash_qr_image
    FROM payment_settings
    WHERE id = 1
    LIMIT 1
");

$stmt->execute();

$result = $stmt->get_result();
$settings = $result->fetch_assoc();

$stmt->close();

$current_qr = $settings["gcash_qr_image"] ?? null;

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>GCash QR Settings | CakeCraft Admin</title>

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
    padding: 18px 6%;
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
    padding: 35px 6%;
    background: var(--bg-soft);
    border-bottom: 1px solid var(--line);
}

.page-heading h1 {
    font-family: var(--display);
    font-size: clamp(28px, 4vw, 40px);
}

.page-heading p {
    color: var(--ink-soft);
}

.container {
    max-width: 900px;
    margin: auto;
    padding: 35px 20px 70px;
}

.card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 22px;
    box-shadow: 0 6px 24px rgba(73,48,45,.06);
}

.card h2 {
    font-family: var(--display);
    font-size: 24px;
    margin-bottom: 10px;
}

.description {
    color: var(--ink-soft);
    font-size: 14px;
    margin-bottom: 20px;
}

.qr-container {
    width: 100%;
    max-width: 380px;
    min-height: 300px;
    margin: 20px auto;
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: #fffaf7;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qr-image {
    width: 100%;
    max-width: 340px;
    max-height: 420px;
    object-fit: contain;
    display: block;
    border-radius: 10px;
}

.qr-empty {
    text-align: center;
    color: var(--ink-soft);
    padding: 30px 15px;
}

.qr-empty .icon {
    font-size: 65px;
    margin-bottom: 10px;
}

.qr-empty strong {
    display: block;
    color: var(--ink);
    font-size: 17px;
}

.qr-empty p {
    font-size: 13px;
    margin-top: 6px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
}

.form-group input[type="file"] {
    width: 100%;
    padding: 13px;
    border: 1px solid var(--line-strong);
    border-radius: 10px;
    background: white;
    font-size: 14px;
}

.form-group input:focus {
    outline: 2px solid var(--rose);
    outline-offset: 2px;
}

.hint {
    color: var(--ink-soft);
    font-size: 13px;
    margin-top: 7px;
}

.preview {
    display: none;
    width: 100%;
    max-width: 340px;
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

.back-btn {
    display: inline-block;
    text-decoration: none;
    color: var(--rose-deep);
    font-weight: 600;
    margin-top: 10px;
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

    .qr-container {
        min-height: 250px;
    }

}

</style>
</head>

<body>

<header>

    <div class="logo">Cake<span>Craft</span></div>

    <a href="dashboard.php">← Dashboard</a>

</header>

<section class="page-heading">

    <h1>GCash QR Settings</h1>

    <p>Upload and manage the GCash QR code displayed to your customers.</p>

</section>

<div class="container">

    <?php if ($success !== ""): ?>

        <div class="alert success">
            <?php echo htmlspecialchars($success); ?>
        </div>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <div class="alert error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>

    <!-- CURRENT QR CODE -->

    <div class="card">

        <h2>Current GCash QR Code</h2>

        <p class="description">
            This QR code will be displayed on the customer payment page.
        </p>

        <div class="qr-container">

            <?php if (!empty($current_qr)): ?>

                <img
                    src="../<?php echo htmlspecialchars($current_qr); ?>"
                    alt="Current GCash QR Code"
                    class="qr-image">

            <?php else: ?>

                <div class="qr-empty">

                    <div class="icon">▦</div>

                    <strong>No GCash QR uploaded yet</strong>

                    <p>Upload your QR code below to make it visible to customers.</p>

                </div>

            <?php endif; ?>

        </div>

    </div>

    <!-- UPLOAD / REPLACE QR -->

    <div class="card">

        <h2><?php echo !empty($current_qr)
            ? "Replace GCash QR Code"
            : "Upload GCash QR Code"; ?></h2>

        <p class="description">
            Select the image of your GCash QR code.
            Uploading a new image will replace the current QR code.
        </p>

        <form method="POST" enctype="multipart/form-data">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

            <div class="form-group">

                <label for="gcash_qr">
                    GCash QR Image
                </label>

                <input
                    type="file"
                    name="gcash_qr"
                    id="gcash_qr"
                    accept="image/jpeg,image/png,image/webp"
                    required>

                <p class="hint">
                    JPG, PNG, or WEBP only. Maximum file size: 5 MB.
                </p>

                <img
                    id="image_preview"
                    class="preview"
                    alt="Selected GCash QR preview">

            </div>

            <button type="submit" class="btn">
                <?php echo !empty($current_qr)
                    ? "Update GCash QR Code"
                    : "Upload GCash QR Code"; ?>
            </button>

        </form>

    </div>

    <a href="dashboard.php" class="back-btn">
        ← Back to Dashboard
    </a>

</div>

<script>

// ==========================================
// IMAGE PREVIEW
// ==========================================

const fileInput = document.getElementById("gcash_qr");
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
            preview.src = "";

            return;
        }

        if (file.size > 5 * 1024 * 1024) {

            alert("The image must not exceed 5 MB.");

            this.value = "";
            preview.style.display = "none";
            preview.src = "";

            return;
        }

        preview.src = URL.createObjectURL(file);
        preview.style.display = "block";

    });

}

</script>

</body>
</html>

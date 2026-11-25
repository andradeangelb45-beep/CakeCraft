<?php
session_start();
require_once "../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: manage_cakes.php");
    exit();
}

$cake_id = (int)$_GET["id"];
$message = "";

$allowed_categories = [
    "Birthday Cake",
    "Wedding Cake",
    "Christening Cake",
    "Cupcakes",
    "Bento Cake",
    "All-Occasion Cakes"
];

$regular_options = [];

foreach ([6, 8, 10] as $diameter) {
    foreach ([1, 2, 3] as $layer) {
        $regular_options[] = $diameter . " inches - " . $layer . ($layer === 1 ? " Layer" : " Layers");
    }
}

$bento_options = [
    "4 inches - 1 Layer",
    "5 inches - 1 Layer"
];

$cupcake_options = ["6 Pieces", "12 Pieces", "24 Pieces"];

function loadCake(mysqli $conn, int $cake_id): ?array {
    $stmt = $conn->prepare("SELECT * FROM cake_options WHERE id = ?");
    $stmt->bind_param("i", $cake_id);
    $stmt->execute();
    $cake = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $cake ?: null;
}

function loadCakeOptions(mysqli $conn, int $cake_id): array {
    $stmt = $conn->prepare("SELECT id, size_name, price FROM cake_sizes WHERE cake_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $cake_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $options = [];

    while ($row = $result->fetch_assoc()) {
        $options[$row["size_name"]] = (float)$row["price"];
    }

    $stmt->close();
    return $options;
}

$cake = loadCake($conn, $cake_id);

if (!$cake) {
    die("Cake not found.");
}

$sizes = loadCakeOptions($conn, $cake_id);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $cake_name = trim($_POST["cake_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $option_names = $_POST["option_name"] ?? [];
    $option_prices = $_POST["option_price"] ?? [];

    $valid_options = [];
    $valid = true;

    if ($category === "Cupcakes") {
        $allowed_options = $cupcake_options;
    } elseif ($category === "Bento Cake") {
        $allowed_options = $bento_options;
    } else {
        $allowed_options = $regular_options;
    }

    if ($cake_name === "" || !in_array($category, $allowed_categories, true)) {

        $message = "Please enter a cake name and select a valid category.";
        $valid = false;

    } elseif (
        !is_array($option_names) ||
        !is_array($option_prices) ||
        count($option_names) === 0 ||
        count($option_names) !== count($option_prices)
    ) {

        $message = "Please select at least one available option and enter its price.";
        $valid = false;

    } else {

        $seen = [];

        foreach ($option_names as $index => $option_name) {

            $option_name = trim((string)$option_name);
            $price = $option_prices[$index] ?? "";

            if (
                !in_array($option_name, $allowed_options, true) ||
                isset($seen[$option_name]) ||
                !is_numeric($price) ||
                (float)$price <= 0
            ) {
                $valid = false;
                break;
            }

            $seen[$option_name] = true;

            $valid_options[] = [
                "name" => $option_name,
                "price" => (float)$price
            ];
        }

        if (!$valid) {
            $message = "Please select valid options and enter a price greater than zero for every selected option.";
        }
    }

    $image_name = $cake["image"] ?? "";
    $new_image_path = null;

    if ($valid && isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {

            $message = "Image upload failed. Please try again.";
            $valid = false;

        } else {

            $image_info = getimagesize($_FILES["image"]["tmp_name"]);

            $allowed_mimes = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            if ($image_info === false || !isset($allowed_mimes[$image_info["mime"]])) {

                $message = "Only valid JPG, PNG, and WEBP images are allowed.";
                $valid = false;

            } elseif ($_FILES["image"]["size"] > 5 * 1024 * 1024) {

                $message = "Image must not exceed 5MB.";
                $valid = false;

            } else {

                $upload_dir = "../assets/images/";

                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {

                    $message = "Unable to create the image folder.";
                    $valid = false;

                } else {

                    $extension = $allowed_mimes[$image_info["mime"]];
                    $new_image = "cake_" . uniqid("", true) . "." . $extension;
                    $new_image_path = $upload_dir . $new_image;

                    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $new_image_path)) {

                        $message = "Unable to save the uploaded image.";
                        $valid = false;
                        $new_image_path = null;

                    } else {

                        $image_name = $new_image;
                    }
                }
            }
        }
    }

    if ($valid) {

        try {

            $conn->begin_transaction();

            $base_price = min(array_column($valid_options, "price"));

            $update = $conn->prepare("
                UPDATE cake_options
                SET cake_name = ?, category = ?, description = ?, base_price = ?, image = ?
                WHERE id = ?
            ");

            $update->bind_param(
                "sssdsi",
                $cake_name,
                $category,
                $description,
                $base_price,
                $image_name,
                $cake_id
            );

            if (!$update->execute()) {
                throw new Exception("Cake update failed.");
            }

            $update->close();

            foreach ($valid_options as $option) {

                $option_name = $option["name"];
                $price = $option["price"];

                $check = $conn->prepare("
                    SELECT id
                    FROM cake_sizes
                    WHERE cake_id = ? AND size_name = ?
                    LIMIT 1
                ");

                $check->bind_param("is", $cake_id, $option_name);
                $check->execute();

                $existing = $check->get_result()->fetch_assoc();
                $check->close();

                if ($existing) {

                    $option_id = (int)$existing["id"];

                    $update_option = $conn->prepare("
                        UPDATE cake_sizes
                        SET price = ?
                        WHERE id = ? AND cake_id = ?
                    ");

                    $update_option->bind_param(
                        "dii",
                        $price,
                        $option_id,
                        $cake_id
                    );

                    if (!$update_option->execute()) {
                        throw new Exception("Option update failed.");
                    }

                    $update_option->close();

                } else {

                    $insert_option = $conn->prepare("
                        INSERT INTO cake_sizes (cake_id, size_name, price)
                        VALUES (?, ?, ?)
                    ");

                    $insert_option->bind_param(
                        "isd",
                        $cake_id,
                        $option_name,
                        $price
                    );

                    if (!$insert_option->execute()) {
                        throw new Exception("Option insert failed.");
                    }

                    $insert_option->close();
                }
            }

            $selected_names = array_column($valid_options, "name");

            $old_options_stmt = $conn->prepare("
                SELECT id, size_name
                FROM cake_sizes
                WHERE cake_id = ?
            ");

            $old_options_stmt->bind_param("i", $cake_id);
            $old_options_stmt->execute();

            $old_options_result = $old_options_stmt->get_result();

            while ($old_option = $old_options_result->fetch_assoc()) {

                if (in_array($old_option["size_name"], $selected_names, true)) {
                    continue;
                }

                $old_option_id = (int)$old_option["id"];

                $order_check = $conn->prepare("
                    SELECT id
                    FROM orders
                    WHERE cake_size_id = ?
                    LIMIT 1
                ");

                $order_check->bind_param("i", $old_option_id);
                $order_check->execute();

                $is_used = (bool)$order_check->get_result()->fetch_assoc();
                $order_check->close();

                if (!$is_used) {

                    $delete_option = $conn->prepare("
                        DELETE FROM cake_sizes
                        WHERE id = ? AND cake_id = ?
                    ");

                    $delete_option->bind_param(
                        "ii",
                        $old_option_id,
                        $cake_id
                    );

                    if (!$delete_option->execute()) {
                        throw new Exception("Unused option cleanup failed.");
                    }

                    $delete_option->close();
                }
            }

            $old_options_stmt->close();

            $conn->commit();

            header("Location: manage_cakes.php?updated=1");
            exit();

        } catch (Throwable $e) {

            $conn->rollback();

            if ($new_image_path && file_exists($new_image_path)) {
                unlink($new_image_path);
            }

            $message = "Unable to update cake. Please try again.";
        }

    } elseif ($new_image_path && file_exists($new_image_path)) {

        unlink($new_image_path);
    }

    $cake["cake_name"] = $cake_name;
    $cake["category"] = $category;
    $cake["description"] = $description;

    $sizes = [];

    if (is_array($option_names) && is_array($option_prices)) {
        foreach ($option_names as $i => $name) {
            if (isset($option_prices[$i])) {
                $sizes[(string)$name] = $option_prices[$i];
            }
        }
    }
}

$display_sizes = $sizes;

$legacy_map = [
    "Small (6 inches)" => "6 inches - 1 Layer",
    "Medium (8 inches)" => "8 inches - 1 Layer",
    "Large (10 inches)" => "10 inches - 1 Layer"
];

foreach ($legacy_map as $legacy => $new_name) {
    if (isset($display_sizes[$legacy]) && !isset($display_sizes[$new_name])) {
        $display_sizes[$new_name] = $display_sizes[$legacy];
    }
}

$display_names = array_keys($display_sizes);
$display_prices = array_values($display_sizes);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Cake | CakeCraft Admin</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/brand-system.css">

<style>
:root {
    --bg: #fffaf7;
    --bg-soft: #f7eeea;
    --bg-tint: #f4e5df;
    --surface: #ffffff;
    --ink: #3b2724;
    --ink-soft: #6f5d57;
    --soft: #6f5d57;
    --rose: #c47c78;
    --rose-deep: #a65a56;
    --rose-dark: #8c4845;
    --rose-darker: #8c4845;
    --craft-color: #a65a56;
    --line: #efe0da;
    --display: 'Playfair Display', Georgia, serif;
    --body: 'DM Sans', system-ui, sans-serif;
    --shadow: 0 8px 24px rgba(59, 39, 36, 0.07);
    --shadow-lift: 0 12px 28px rgba(59, 39, 36, 0.11);
}

*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: var(--body);
    line-height: 1.6;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
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
    justify-content: space-between;
    align-items: center;
    padding: 14px 6%;
    background: var(--bg);
    color: var(--ink);
    border-bottom: 1px solid var(--line);
    box-shadow: 0 2px 14px rgba(59, 39, 36, 0.05);
}

.brand-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-mark {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    line-height: 1;
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

header h2 {
    font-family: var(--display);
    font-size: 25px;
    font-weight: 700;
    letter-spacing: -0.5px;
    color: var(--ink);
}

header h2 span.brand-craft {
    color: var(--craft-color);
}

header .badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    background: var(--bg-soft);
    border: 1px solid var(--line);
    color: var(--rose-deep);
    border-radius: 999px;
    font-family: var(--body);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    vertical-align: middle;
}

header a {
    color: var(--ink);
    text-decoration: none;
    padding: 9px 20px;
    border: 1.5px solid var(--rose);
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    transition: 0.2s ease;
}

header a:hover {
    background: var(--bg-soft);
    border-color: var(--rose-deep);
    color: var(--rose-deep);
}

.container {
    max-width: 800px;
    margin: auto;
    padding: 38px 20px 70px;
}

.back {
    display: inline-flex;
    align-items: center;
    margin-bottom: 24px;
    color: var(--rose-deep);
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    transition: 0.2s ease;
}

.back:hover {
    color: var(--rose-dark);
    transform: translateX(-3px);
}

h1 {
    font-family: var(--display);
    font-size: 36px;
    line-height: 1.2;
    margin-bottom: 8px;
    color: var(--ink);
}

.subtitle {
    color: var(--ink-soft);
    font-size: 14px;
    margin-bottom: 26px;
}

.form-card {
    background: var(--surface);
    padding: 32px;
    border: 1px solid var(--line);
    border-radius: 18px;
    box-shadow: var(--shadow);
}

.field {
    margin-bottom: 22px;
}

label {
    display: block;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--ink);
}

input,
textarea,
select {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid var(--line);
    border-radius: 10px;
    font-family: var(--body);
    font-size: 14px;
    background: var(--bg);
    color: var(--ink);
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

input:focus,
textarea:focus,
select:focus {
    border-color: var(--rose-deep);
    background: var(--surface);
    box-shadow: 0 0 0 3px rgba(196, 124, 120, 0.16);
}

textarea {
    min-height: 110px;
    resize: vertical;
}

input[type="file"] {
    padding: 10px;
    background: var(--bg);
    cursor: pointer;
}

input[type="file"]::file-selector-button {
    border: none;
    background: var(--rose);
    color: white;
    padding: 8px 13px;
    margin-right: 12px;
    border-radius: 7px;
    font-family: var(--body);
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

input[type="file"]::file-selector-button:hover {
    background: var(--rose-deep);
}

.preview {
    display: block;
    width: 190px;
    height: 145px;
    object-fit: cover;
    border-radius: 12px;
    margin: 8px 0 15px;
    background: var(--bg-soft);
    border: 1px solid var(--line);
}

.hint {
    display: block;
    font-size: 12px;
    color: var(--ink-soft);
    margin-top: 7px;
}

.section-title {
    font-family: var(--display);
    font-size: 23px;
    margin: 28px 0 8px;
    padding-top: 23px;
    border-top: 1px solid var(--line);
    color: var(--ink);
}

.options-note {
    font-size: 13px;
    color: var(--ink-soft);
    margin-bottom: 17px;
}

.size-card {
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 18px;
    margin-bottom: 14px;
    background: var(--bg);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.size-card:hover {
    border-color: #dfc2bc;
    box-shadow: 0 5px 15px rgba(59, 39, 36, 0.04);
}

.size-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
}

.size-heading h3 {
    font: 600 18px var(--display);
    color: var(--ink);
}

.size-sub {
    font-size: 11px;
    color: var(--rose-deep);
    background: var(--bg-soft);
    border: 1px solid var(--line);
    padding: 5px 10px;
    border-radius: 20px;
}

.layer-options {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.layer-choice {
    border: 1px solid var(--line);
    background: var(--surface);
    border-radius: 11px;
    padding: 12px;
    min-width: 0;
    transition: 0.2s ease;
}

.layer-choice.selected {
    border-color: var(--rose-deep);
    background: var(--bg-soft);
}

.layer-choice label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 13px;
    cursor: pointer;
}

.layer-choice input[type="checkbox"] {
    width: auto;
    accent-color: var(--rose-deep);
    cursor: pointer;
}

.layer-price {
    margin-top: 10px;
    display: none;
}

.layer-choice.selected .layer-price {
    display: block;
}

.price-wrap {
    position: relative;
}

.price-wrap span {
    position: absolute;
    left: 11px;
    top: 9px;
    color: var(--ink-soft);
    font-weight: 700;
    font-size: 13px;
}

.price-wrap input {
    padding: 9px 10px 9px 29px;
    font-size: 13px;
}

.cupcake-row {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 12px;
    align-items: center;
    margin-bottom: 12px;
    padding: 13px;
    border: 1px solid var(--line);
    border-radius: 11px;
    background: var(--bg);
}

.cupcake-row label {
    display: flex;
    align-items: center;
    margin: 0;
    font-size: 13px;
    cursor: pointer;
}

.cupcake-row input[type="checkbox"] {
    width: auto;
    margin-right: 9px;
    accent-color: var(--rose-deep);
}

.save-button {
    width: 100%;
    padding: 14px 20px;
    background: var(--rose);
    color: white;
    border: none;
    border-radius: 999px;
    font-family: var(--body);
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 18px;
    box-shadow: 0 5px 13px rgba(166, 90, 86, 0.18);
    transition: 0.2s ease;
}

.save-button:hover {
    background: var(--rose-deep);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lift);
}

.message {
    padding: 13px 16px;
    background: #f9e9e7;
    color: #963f3b;
    border: 1px solid #efd1cd;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 13px;
}

@media (max-width: 600px) {
    header {
        padding: 14px 5%;
        flex-wrap: wrap;
    }

    header h2 {
        font-size: 21px;
    }

    header a {
        padding: 8px 14px;
        font-size: 12px;
    }

    .container {
        padding: 28px 15px 50px;
    }

    h1 {
        font-size: 30px;
    }

    .form-card {
        padding: 20px;
        border-radius: 14px;
    }

    .layer-options {
        grid-template-columns: 1fr;
    }

    .cupcake-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .size-heading {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { transition-duration: .01ms !important; }
}
</style>
</head>

<body>

<header>
    <div class="brand-wrap">
        <div class="brand-mark">
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
        </div>
        <span class="badge">ADMIN</span>
    </div>
    <a href="logout.php">Logout</a>
</header>

<div class="container">

    <a href="manage_cakes.php" class="back">
        &larr; Back to Manage Cakes
    </a>

    <h1>Edit Cake</h1>

    <p class="subtitle">
        Update cake information and choose only the sizes, layers, or cupcake packs available.
    </p>

    <?php if ($message !== ""): ?>
        <div class="message" role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="form-card">

        <form method="POST" enctype="multipart/form-data">

            <div class="field">
                <label for="cake_name">Cake Name</label>
                <input
                    type="text"
                    id="cake_name"
                    name="cake_name"
                    value="<?php echo htmlspecialchars($cake["cake_name"] ?? ""); ?>"
                    required>
            </div>

            <div class="field">
                <label for="category">Category</label>

                <select id="category" name="category" required>
                    <?php foreach ($allowed_categories as $cat): ?>
                        <option
                            value="<?php echo htmlspecialchars($cat); ?>"
                            <?php echo (($cake["category"] ?? "") === $cat) ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($cake["description"] ?? ""); ?></textarea>
            </div>

            <div class="field">
                <label>Current Cake Image</label>

                <br>

                <?php if (!empty($cake["image"])): ?>
                    <img
                        class="preview"
                        src="../assets/images/<?php echo htmlspecialchars(basename($cake["image"])); ?>"
                        alt="Current cake image">
                <?php else: ?>
                    <p>No image available.</p>
                <?php endif; ?>

                <label for="image">Upload New Image (Optional)</label>

                <input
                    type="file"
                    id="image"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp">

                <small class="hint">Maximum file size: 5MB.</small>
            </div>

            <h2 class="section-title">
                Available sizes, layers & prices
            </h2>

            <p class="options-note" id="option-help">
                Selected options and prices are loaded from this cake. Uncheck options you no longer offer.
            </p>

            <div id="options-container"></div>

            <button type="submit" class="save-button">
                Save Changes
            </button>

        </form>

    </div>

</div>

<script>
const category = document.getElementById('category');
const container = document.getElementById('options-container');
const help = document.getElementById('option-help');

const existingNames = <?php echo json_encode(
    $display_names,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
); ?>;

const existingPrices = <?php echo json_encode(
    $display_prices,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
); ?>;

function priceFor(name) {
    const index = existingNames.indexOf(name);
    return index >= 0 ? (existingPrices[index] ?? '') : '';
}

function legacyNameFor(name) {
    const map = {
        '6 inches - 1 Layer': 'Small (6 inches)',
        '8 inches - 1 Layer': 'Medium (8 inches)',
        '10 inches - 1 Layer': 'Large (10 inches)'
    };

    return map[name] || name;
}

function isOptionExisting(name) {
    return existingNames.includes(name) ||
           existingNames.includes(legacyNameFor(name));
}

function addLayerChoice(card, diameter, layer) {

    const name = diameter + ' inches - ' + layer +
        (layer === 1 ? ' Layer' : ' Layers');

    const choice = document.createElement('div');
    choice.className = 'layer-choice';

    const label = document.createElement('label');

    const check = document.createElement('input');
    check.type = 'checkbox';
    check.value = name;
    check.checked = isOptionExisting(name);

    label.append(
        check,
        document.createTextNode(
            layer + ' ' + (layer === 1 ? 'Layer' : 'Layers')
        )
    );

    const price = document.createElement('div');
    price.className = 'layer-price price-wrap';

    const peso = document.createElement('span');
    peso.textContent = '₱';

    const input = document.createElement('input');
    input.type = 'number';
    input.name = 'option_price[]';
    input.min = '0.01';
    input.step = '0.01';
    input.placeholder = 'Price';
    input.value = priceFor(name) || priceFor(legacyNameFor(name));
    input.required = check.checked;

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'option_name[]';
    hidden.value = name;
    hidden.disabled = !check.checked;

    check.addEventListener('change', () => {
        choice.classList.toggle('selected', check.checked);
        input.required = check.checked;
        input.disabled = !check.checked;
        hidden.disabled = !check.checked;
    });

    input.disabled = !check.checked;

    price.append(peso, input);
    choice.append(label, hidden, price);
    choice.classList.toggle('selected', check.checked);

    card.append(choice);
}

function buildRegular() {

    [6, 8, 10].forEach(d => {

        const card = document.createElement('section');
        card.className = 'size-card';

        const heading = document.createElement('div');
        heading.className = 'size-heading';

        const h = document.createElement('h3');

        h.textContent =
            (d === 6 ? 'Small' : d === 8 ? 'Medium' : 'Large') +
            ' — ' + d + ' inches';

        const note = document.createElement('span');
        note.className = 'size-sub';
        note.textContent = 'Select available layers';

        heading.append(h, note);
        card.append(heading);

        const options = document.createElement('div');
        options.className = 'layer-options';

        [1, 2, 3].forEach(l => {
            addLayerChoice(options, d, l);
        });

        card.append(options);
        container.append(card);
    });
}

function buildBento() {

    help.textContent =
        'Bento Cakes have dedicated 4-inch and 5-inch sizes, single layer only. Select the sizes available and update their prices.';

    const bentoSizes = [
        {
            name: '4 inches - 1 Layer',
            label: '4-inch Bento Cake',
            description: 'Small · Single layer'
        },
        {
            name: '5 inches - 1 Layer',
            label: '5-inch Bento Cake',
            description: 'Medium · Single layer'
        }
    ];

    bentoSizes.forEach(option => {

        const card = document.createElement('section');
        card.className = 'size-card';

        const heading = document.createElement('div');
        heading.className = 'size-heading';

        const h = document.createElement('h3');
        h.textContent = option.label;

        const note = document.createElement('span');
        note.className = 'size-sub';
        note.textContent = option.description;

        heading.append(h, note);
        card.append(heading);

        const choice = document.createElement('div');
        choice.className = 'layer-choice';

        const label = document.createElement('label');

        const check = document.createElement('input');
        check.type = 'checkbox';
        check.value = option.name;
        check.checked = existingNames.includes(option.name);

        label.append(
            check,
            document.createTextNode('Available')
        );

        const price = document.createElement('div');
        price.className = 'layer-price price-wrap';

        const peso = document.createElement('span');
        peso.textContent = '₱';

        const input = document.createElement('input');
        input.type = 'number';
        input.name = 'option_price[]';
        input.min = '0.01';
        input.step = '0.01';
        input.placeholder = 'Price';
        input.value = priceFor(option.name);
        input.required = check.checked;
        input.disabled = !check.checked;

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'option_name[]';
        hidden.value = option.name;
        hidden.disabled = !check.checked;

        check.addEventListener('change', () => {
            choice.classList.toggle('selected', check.checked);
            input.required = check.checked;
            input.disabled = !check.checked;
            hidden.disabled = !check.checked;
        });

        price.append(peso, input);
        choice.append(label, hidden, price);
        choice.classList.toggle('selected', check.checked);

        card.append(choice);
        container.append(card);
    });
}

function buildCupcakes() {

    help.textContent =
        'Check the cupcake pack sizes offered and edit their prices. Unchecked packs will not be saved (unless they are tied to an existing order).';

    ['6 Pieces', '12 Pieces', '24 Pieces'].forEach(name => {

        const row = document.createElement('div');
        row.className = 'cupcake-row';

        const left = document.createElement('div');

        const label = document.createElement('label');

        const check = document.createElement('input');
        check.type = 'checkbox';
        check.value = name;
        check.checked = existingNames.includes(name);

        label.append(
            check,
            document.createTextNode(name)
        );

        left.append(label);

        const priceField = document.createElement('div');
        priceField.className = 'price-wrap';

        const peso = document.createElement('span');
        peso.textContent = '₱';

        const input = document.createElement('input');
        input.type = 'number';
        input.name = 'option_price[]';
        input.min = '0.01';
        input.step = '0.01';
        input.placeholder = 'Price';
        input.value = priceFor(name);
        input.required = check.checked;
        input.disabled = !check.checked;

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'option_name[]';
        hidden.value = name;
        hidden.disabled = !check.checked;

        check.addEventListener('change', () => {
            input.required = check.checked;
            input.disabled = !check.checked;
            hidden.disabled = !check.checked;
        });

        priceField.append(peso, input);
        row.append(left, hidden, priceField);

        container.append(row);
    });
}

function buildOptions() {

    container.innerHTML = '';

    if (category.value === 'Cupcakes') {

        buildCupcakes();

    } else if (category.value === 'Bento Cake') {

        buildBento();

    } else if (category.value) {

        help.textContent =
            'Select only the layer options offered for each cake size. Unselected layers do not need a price.';

        buildRegular();

    } else {

        help.textContent =
            'Select a category to show the available options.';
    }
}

category.addEventListener('change', buildOptions);

buildOptions();
</script>

</body>
</html>
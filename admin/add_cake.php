<?php
session_start();
require_once "../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$is_success = false;

$allowed_categories = [
    "Birthday Cake",
    "Wedding Cake",
    "Christening Cake",
    "Cupcakes",
    "Bento Cake",
    "All-Occasion Cakes"
];

// Regular cake sizes are now flexible. The admin may add custom diameters
// and the selected options will be saved in cake_sizes.size_name.

$bento_option_names = [
    "4 inches - 1 Layer",
    "5 inches - 1 Layer"
];

$cupcake_option_names = ["6 Pieces", "12 Pieces", "24 Pieces"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $cake_name = trim($_POST["cake_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $option_names = $_POST["option_name"] ?? [];
    $option_prices = $_POST["option_price"] ?? [];

    if ($cake_name === "" || $description === "" || !in_array($category, $allowed_categories, true)) {

        $message = "Please complete the cake name, category, and description.";

    } elseif (
        !is_array($option_names) ||
        !is_array($option_prices) ||
        count($option_names) === 0 ||
        count($option_names) !== count($option_prices)
    ) {

        $message = "Please select at least one available option and enter its price.";

    } else {

        $options = [];
        $valid_options = true;
        $seen_options = [];

        foreach ($option_names as $i => $name) {

            $name = trim((string)$name);
            $price = $option_prices[$i] ?? "";

            if ($category === "Cupcakes") {
                $is_allowed_option = in_array($name, $cupcake_option_names, true);
            } elseif ($category === "Bento Cake") {
                $is_allowed_option = in_array($name, $bento_option_names, true);
            } else {
                // Accept any positive cake diameter with 1 to 3 layers.
                // Example: 12 inches - 2 Layers
                $is_allowed_option = (bool) preg_match(
                    '/^[1-9][0-9]*(?:\\.[0-9]+)? inches - [1-3] Layers?$/',
                    $name
                );
            }

            if (
                !$is_allowed_option ||
                isset($seen_options[$name]) ||
                !is_numeric($price) ||
                (float)$price <= 0
            ) {
                $valid_options = false;
                break;
            }

            $seen_options[$name] = true;

            $options[] = [
                "name" => $name,
                "price" => (float)$price
            ];
        }

        if (!$valid_options) {

            $message = "Please select valid options and enter a price greater than zero for each selected option.";

        } elseif (
            !isset($_FILES["image"]) ||
            $_FILES["image"]["error"] !== UPLOAD_ERR_OK
        ) {

            $message = "Please select a cake image.";

        } else {

            $upload_dir = "../assets/images/";

            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {

                $message = "Unable to create the image folder.";

            } else {

                $image_info = getimagesize($_FILES["image"]["tmp_name"]);
                $allowed_types = ["image/jpeg", "image/png", "image/webp"];

                if (
                    $image_info === false ||
                    !in_array($image_info["mime"], $allowed_types, true)
                ) {

                    $message = "Only valid JPG, PNG, and WEBP images are allowed.";

                } elseif ($_FILES["image"]["size"] > 5 * 1024 * 1024) {

                    $message = "Image must not exceed 5MB.";

                } else {

                    $extension = [
                        "image/jpeg" => "jpg",
                        "image/png" => "png",
                        "image/webp" => "webp"
                    ][$image_info["mime"]];

                    $image_name = uniqid("cake_", true) . "." . $extension;
                    $image_path = $upload_dir . $image_name;

                    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $image_path)) {

                        $message = "Image upload failed.";

                    } else {

                        try {

                            $conn->begin_transaction();

                            $base_price = min(array_column($options, "price"));

                            $stmt = $conn->prepare("
                                INSERT INTO cake_options
                                (cake_name, category, description, base_price, image)
                                VALUES (?, ?, ?, ?, ?)
                            ");

                            $stmt->bind_param(
                                "sssds",
                                $cake_name,
                                $category,
                                $description,
                                $base_price,
                                $image_name
                            );

                            if (!$stmt->execute()) {
                                throw new Exception("Cake insert failed.");
                            }

                            $cake_id = $conn->insert_id;
                            $stmt->close();

                            $size_stmt = $conn->prepare("
                                INSERT INTO cake_sizes
                                (cake_id, size_name, price)
                                VALUES (?, ?, ?)
                            ");

                            foreach ($options as $option) {

                                $option_name = $option["name"];
                                $option_price = $option["price"];

                                $size_stmt->bind_param(
                                    "isd",
                                    $cake_id,
                                    $option_name,
                                    $option_price
                                );

                                if (!$size_stmt->execute()) {
                                    throw new Exception("Option insert failed.");
                                }
                            }

                            $size_stmt->close();

                            $conn->commit();

                            $message = "Cake added successfully!";
                            $is_success = true;

                        } catch (Throwable $e) {

                            $conn->rollback();

                            if (file_exists($image_path)) {
                                unlink($image_path);
                            }

                            $message = "Unable to save cake and its options. Please try again.";
                        }
                    }
                }
            }
        }
    }
}

$is_post_error = ($_SERVER["REQUEST_METHOD"] === "POST" && !$is_success);

$old_name = $is_post_error ? trim($_POST["cake_name"] ?? "") : "";
$old_description = $is_post_error ? trim($_POST["description"] ?? "") : "";
$old_category = $is_post_error ? trim($_POST["category"] ?? "") : "";

$old_option_names = (
    $is_post_error && is_array($_POST["option_name"] ?? null)
) ? array_values($_POST["option_name"]) : [];

$old_option_prices = (
    $is_post_error && is_array($_POST["option_price"] ?? null)
) ? array_values($_POST["option_price"]) : [];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Add Cake | CakeCraft Admin</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

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

/* HEADER */
header {
    position: sticky;
    top: 0;
    z-index: 5;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 6%;
    background: var(--bg);
    color: var(--ink);
    border-bottom: 1px solid var(--line);
    box-shadow: 0 2px 14px rgba(59, 39, 36, 0.05);
}

.brand {
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

.brand h2 {
    font-family: var(--display);
    font-size: 25px;
    font-weight: 700;
    letter-spacing: -0.5px;
    color: var(--ink);
}

.brand h2 span.brand-craft {
    color: var(--craft-color);
}

.badge {
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--bg-soft);
    border: 1px solid var(--line);
    color: var(--rose-deep);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
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

/* PAGE */
.container {
    max-width: 800px;
    margin: auto;
    padding: 38px 20px 70px;
}

.back {
    display: inline-flex;
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

/* FORM */
form {
    background: var(--surface);
    padding: 32px;
    border: 1px solid var(--line);
    border-radius: 18px;
    box-shadow: var(--shadow);
}

.form-group {
    margin-bottom: 22px;
}

label,
.label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 700;
    color: var(--ink);
}

input[type="text"],
input[type="number"],
input[type="file"],
textarea,
select {
    width: 100%;
    padding: 12px 14px;
    font-family: var(--body);
    font-size: 14px;
    color: var(--ink);
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: 10px;
    outline: none;
    transition: 0.2s ease;
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

.hint {
    font-size: 12px;
    color: var(--ink-soft);
    margin-top: 5px;
}

/* OPTIONS */
.section-title {
    font: 600 23px var(--display);
    margin: 28px 0 8px;
    padding-top: 23px;
    border-top: 1px solid var(--line);
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
    transition: 0.2s ease;
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

/* ADD CUSTOM SIZE */
.add-size-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin: 18px 0 24px;
    padding: 16px;
    border: 1px dashed var(--rose);
    border-radius: 12px;
    background: var(--bg-soft);
}

.add-size-controls input {
    flex: 1 1 180px;
    min-width: 0;
}

.add-size-button {
    border: 0;
    border-radius: 999px;
    padding: 11px 18px;
    background: var(--rose-deep);
    color: #fff;
    font: 700 13px var(--body);
    cursor: pointer;
    transition: .2s ease;
}

.add-size-button:hover {
    background: var(--rose-dark);
    transform: translateY(-1px);
}

.size-remove {
    border: 1px solid #e7c7c2;
    background: #fff;
    color: var(--rose-dark);
    border-radius: 999px;
    padding: 5px 10px;
    font: 600 11px var(--body);
    cursor: pointer;
}

.size-remove:hover {
    background: #f9e9e7;
}

/* CUPCAKES */
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

/* IMAGE UPLOAD */
.dropzone {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 165px;
    padding: 22px;
    text-align: center;
    background: var(--bg);
    border: 2px dashed var(--rose);
    border-radius: 14px;
    overflow: hidden;
    transition: 0.2s ease;
}

.dropzone:hover {
    border-color: var(--rose-deep);
    background: var(--bg-soft);
}

.dropzone input {
    max-width: 100%;
}

.dropzone img {
    display: none;
    max-width: 100%;
    max-height: 180px;
    object-fit: contain;
    border-radius: 10px;
}

.dropzone.has-image img {
    display: block;
}

.dropzone.has-image .drop-hint {
    display: none;
}

/* BUTTON */
.primary {
    width: 100%;
    margin-top: 10px;
    padding: 14px 24px;
    font-family: var(--body);
    font-size: 14px;
    font-weight: 700;
    color: white;
    background: var(--rose);
    border: 0;
    border-radius: 999px;
    cursor: pointer;
    box-shadow: 0 5px 13px rgba(166, 90, 86, 0.18);
    transition: 0.2s ease;
}

.primary:hover {
    background: var(--rose-deep);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lift);
}

/* MESSAGES */
.message {
    padding: 13px 16px;
    margin-bottom: 20px;
    border-radius: 10px;
    font-size: 13px;
}

.success {
    background: #edf5ed;
    color: #426c4e;
    border: 1px solid #d7e8d8;
}

.error {
    background: #f9e9e7;
    color: #963f3b;
    border: 1px solid #efd1cd;
}

.hidden {
    display: none;
}

/* RESPONSIVE */
@media (max-width: 600px) {

    header {
        padding: 14px 5%;
    }

    .brand {
        gap: 8px;
    }

    .brand h2 {
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

    form {
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

</style>

</head>

<body>

<header>

    <div class="brand">
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

    <a href="dashboard.php">Dashboard</a>

</header>

<div class="container">

    <a class="back" href="manage_cakes.php">
        &larr; Back to Manage Cakes
    </a>

    <h1>Add New Cake</h1>

    <p class="subtitle">
        Enter the cake details and choose only the sizes and layers available for this cake.
    </p>

    <?php if ($message !== ""): ?>

        <div class="message <?php echo $is_success ? "success" : "error"; ?>"
             role="<?php echo $is_success ? "status" : "alert"; ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <div class="form-group">

            <label for="cake_name">Cake Name</label>

            <input
                type="text"
                id="cake_name"
                name="cake_name"
                placeholder="e.g. Strawberry Dream"
                value="<?php echo htmlspecialchars($old_name); ?>"
                required>

        </div>

        <div class="form-group">

            <label for="category">Cake Category</label>

            <select id="category" name="category" required>

                <option value="">Select a category</option>

                <?php foreach ($allowed_categories as $cat): ?>

                    <option
                        value="<?php echo htmlspecialchars($cat); ?>"
                        <?php echo $old_category === $cat ? "selected" : ""; ?>>

                        <?php echo htmlspecialchars($cat); ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="form-group">

            <label for="description">Description</label>

            <textarea
                id="description"
                name="description"
                placeholder="Describe the flavor, layers, and what makes this cake special"
                required><?php echo htmlspecialchars($old_description); ?></textarea>

        </div>

        <h2 class="section-title">
            Available Sizes, Layers & Prices
        </h2>

        <p class="options-note" id="option-help">
            Select only the layer options you offer for each size. Unselected options will not be saved.
        </p>

        <div id="options-container"></div>

        <div class="form-group">

            <label for="image">Cake Image</label>

            <div class="dropzone" id="dropzone">

                <div class="drop-hint">

                    <strong>Choose a Cake Photo</strong>

                    <p class="hint">
                        JPG, PNG, or WEBP, up to 5MB
                    </p>

                </div>

                <input
                    type="file"
                    id="image"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp"
                    required>

                <img id="preview" alt="Selected cake image preview">

            </div>

        </div>

        <button class="primary" type="submit">
            Save Cake
        </button>

    </form>

</div>

<script>

const category = document.getElementById('category');
const container = document.getElementById('options-container');
const help = document.getElementById('option-help');

const oldNames = <?php echo json_encode(
    $old_option_names,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
); ?>;

const oldPrices = <?php echo json_encode(
    $old_option_prices,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
); ?>;

function oldPriceFor(name) {

    const i = oldNames.indexOf(name);

    return i >= 0 ? (oldPrices[i] || '') : '';
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
    check.checked = oldNames.includes(name);

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
    input.value = oldPriceFor(name);
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

function createRegularSizeCard(diameter, isCustom = false) {

    const card = document.createElement('section');
    card.className = 'size-card';
    card.dataset.diameter = String(diameter);

    const heading = document.createElement('div');
    heading.className = 'size-heading';

    const h = document.createElement('h3');
    const standardNames = {'6': 'Small', '8': 'Medium', '10': 'Large'};
    const standardName = standardNames[String(diameter)];
    h.textContent = (standardName ? standardName + ' — ' : '') + diameter + ' inches';

    const headingRight = document.createElement('div');
    headingRight.style.display = 'flex';
    headingRight.style.alignItems = 'center';
    headingRight.style.gap = '8px';

    const note = document.createElement('span');
    note.className = 'size-sub';
    note.textContent = 'Choose available layers';
    headingRight.append(note);

    if (isCustom) {
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'size-remove';
        remove.textContent = 'Remove size';
        remove.addEventListener('click', () => card.remove());
        headingRight.append(remove);
    }

    heading.append(h, headingRight);
    card.append(heading);

    const options = document.createElement('div');
    options.className = 'layer-options';
    [1, 2, 3].forEach(layer => addLayerChoice(options, diameter, layer));
    card.append(options);

    return card;
}

function buildRegular() {

    help.textContent =
        'Choose the available layers and enter a price for each selected option. You can also add your own cake sizes below.';

    const diameters = ['6', '8', '10'];

    // Recreate custom sizes if the form was submitted with an error.
    oldNames.forEach(name => {
        const match = String(name).match(/^([1-9][0-9]*(?:\.[0-9]+)?) inches - [1-3] Layers?$/);
        if (match && !diameters.includes(match[1])) diameters.push(match[1]);
    });

    diameters.forEach(diameter => {
        container.append(createRegularSizeCard(
            diameter,
            !['6', '8', '10'].includes(String(diameter))
        ));
    });

    const controls = document.createElement('div');
    controls.className = 'add-size-controls';

    const sizeInput = document.createElement('input');
    sizeInput.type = 'number';
    sizeInput.min = '1';
    sizeInput.max = '100';
    sizeInput.step = '0.5';
    sizeInput.placeholder = 'Enter size in inches (e.g. 12)';
    sizeInput.setAttribute('aria-label', 'New cake size in inches');

    const addButton = document.createElement('button');
    addButton.type = 'button';
    addButton.className = 'add-size-button';
    addButton.textContent = '+ Add New Size';

    function addCustomSize() {
        const diameter = Number(sizeInput.value);

        if (!Number.isFinite(diameter) || diameter <= 0 || diameter > 100) {
            alert('Please enter a valid cake size between 1 and 100 inches.');
            sizeInput.focus();
            return;
        }

        const exists = Array.from(
            container.querySelectorAll('.size-card[data-diameter]')
        ).some(card => Number(card.dataset.diameter) === diameter);

        if (exists) {
            alert('This cake size is already listed.');
            sizeInput.focus();
            return;
        }

        container.insertBefore(createRegularSizeCard(String(diameter), true), controls);
        sizeInput.value = '';
        sizeInput.focus();
    }

    addButton.addEventListener('click', addCustomSize);
    sizeInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addCustomSize();
        }
    });

    controls.append(sizeInput, addButton);
    container.append(controls);
}

function buildBento() {

    help.textContent =
        'Bento Cakes are available in 4-inch and 5-inch sizes, single layer only. Select the sizes you offer and enter their prices.';

    [
        {
            name: '4 inches - 1 Layer',
            label: '4-inch Bento Cake',
            description: 'Small size · Single layer'
        },
        {
            name: '5 inches - 1 Layer',
            label: '5-inch Bento Cake',
            description: 'Larger size · Single layer'
        }
    ].forEach(option => {

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
        check.checked = oldNames.includes(option.name);

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
        input.value = oldPriceFor(option.name);
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
        'Choose the cupcake pack options you offer and enter a price for each selected pack.';

    ['6 Pieces', '12 Pieces', '24 Pieces'].forEach(name => {

        const row = document.createElement('div');
        row.className = 'cupcake-row';

        const left = document.createElement('div');

        const label = document.createElement('label');

        label.textContent = name;

        const check = document.createElement('input');
        check.type = 'checkbox';
        check.value = name;
        check.checked = oldNames.includes(name);
        check.style.marginRight = '8px';

        label.prepend(check);

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
        input.value = oldPriceFor(name);
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
            'Select only the layer options offered for each cake size. You do not need to enter prices for unselected layers.';

        buildRegular();

    } else {

        help.textContent =
            'Select a category first to display its available options.';

    }

}

category.addEventListener('change', buildOptions);

buildOptions();

/* IMAGE PREVIEW */
const fileInput = document.getElementById('image');
const preview = document.getElementById('preview');
const zone = document.getElementById('dropzone');

let previewUrl = null;

fileInput.addEventListener('change', () => {

    const file = fileInput.files[0];

    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    }

    if (file && file.type.startsWith('image/')) {

        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;

        zone.classList.add('has-image');

    } else {

        preview.removeAttribute('src');
        zone.classList.remove('has-image');

    }

});

</script>

</body>
</html>

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

$regular_option_names = [];

foreach ([6, 8, 10] as $diameter) {
    foreach ([1, 2, 3] as $layers) {
        $regular_option_names[] = $diameter . " inches - " . $layers . ($layers === 1 ? " Layer" : " Layers");
    }
}

// Dedicated Bento Cake sizes (single layer only)
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

        if ($category === "Cupcakes") {
            $allowed_options = $cupcake_option_names;
        } elseif ($category === "Bento Cake") {
            $allowed_options = $bento_option_names;
        } else {
            $allowed_options = $regular_option_names;
        }

        $options = [];
        $valid_options = true;
        $seen_options = [];

        foreach ($option_names as $i => $name) {

            $name = trim((string)$name);
            $price = $option_prices[$i] ?? "";

            if (
                !in_array($name, $allowed_options, true) ||
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
    --surface: #fff;
    --ink: #3b2724;
    --ink-soft: #6f5d57;
    --rose: #c47c78;
    --rose-deep: #a65a56;
    --rose-dark: #8c4845;
    --line: #e6d3cc;
    --font-body: 'DM Sans', system-ui, sans-serif;
    --font-display: 'Playfair Display', Georgia, serif;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: var(--font-body);
    line-height: 1.6;
    background: var(--bg);
    color: var(--ink);
    min-height: 100vh;
}

header {
    position: sticky;
    top: 0;
    z-index: 5;
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
    font-family: var(--font-display);
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
    padding: 9px 20px;
    border: 1px solid #ffffff73;
    border-radius: 999px;
}

.container {
    max-width: 760px;
    margin: auto;
    padding: 42px 24px 70px;
}

.back {
    display: inline-block;
    margin-bottom: 22px;
    color: var(--rose-deep);
    text-decoration: none;
    font-weight: 600;
}

h1 {
    font-family: var(--font-display);
    font-size: clamp(1.9rem, 4vw, 2.5rem);
    line-height: 1.2;
    margin-bottom: 10px;
}

.subtitle {
    color: var(--ink-soft);
    font-size: 16px;
    margin-bottom: 28px;
}

form {
    background: #fff;
    padding: 34px;
    border: 1px solid var(--line);
    border-radius: 18px;
    box-shadow: 0 14px 40px #49302d14;
}

.form-group {
    margin-bottom: 21px;
}

label,
.label {
    display: block;
    margin-bottom: 7px;
    font-size: 14px;
    font-weight: 600;
}

input[type=text],
input[type=number],
input[type=file],
textarea,
select {
    width: 100%;
    padding: 12px 14px;
    font: inherit;
    font-size: 15px;
    color: var(--ink);
    background: var(--bg);
    border: 1.5px solid var(--line);
    border-radius: 10px;
}

textarea {
    min-height: 115px;
    resize: vertical;
}

.hint {
    font-size: 13px;
    color: var(--ink-soft);
    margin-top: 5px;
}

.section-title {
    font: 600 22px var(--font-display);
    margin: 26px 0 8px;
}

.options-note {
    font-size: 13px;
    color: var(--ink-soft);
    margin-bottom: 15px;
}

.size-card {
    border: 1px solid var(--line);
    border-radius: 13px;
    padding: 16px;
    margin-bottom: 13px;
    background: #fff;
}

.size-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 12px;
}

.size-heading h3 {
    font: 600 18px var(--font-display);
}

.size-sub {
    font-size: 12px;
    color: var(--ink-soft);
}

.layer-options {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 9px;
}

.layer-choice {
    border: 1px solid var(--line);
    background: var(--bg);
    border-radius: 10px;
    padding: 10px;
    min-width: 0;
}

.layer-choice label {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0;
    font-size: 13px;
    cursor: pointer;
}

.layer-choice input[type=checkbox] {
    accent-color: var(--rose-deep);
}

.layer-price {
    margin-top: 9px;
    display: none;
}

.layer-choice.selected .layer-price {
    display: block;
}

.layer-price input {
    width: 100%;
    padding: 9px 10px 9px 27px;
    font-size: 14px;
}

.price-wrap {
    position: relative;
}

.price-wrap span {
    position: absolute;
    left: 10px;
    top: 8px;
    color: var(--ink-soft);
    font-weight: 600;
    font-size: 14px;
}

.cupcake-row {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 12px;
    align-items: end;
    margin-bottom: 12px;
}

.cupcake-row label {
    font-size: 13px;
}

.dropzone {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 155px;
    padding: 22px;
    text-align: center;
    background: var(--bg);
    border: 2px dashed var(--rose);
    border-radius: 14px;
    overflow: hidden;
}

.dropzone input {
    max-width: 100%;
}

.dropzone img {
    display: none;
    max-width: 100%;
    max-height: 180px;
    object-fit: contain;
}

.dropzone.has-image img {
    display: block;
}

.dropzone.has-image .drop-hint {
    display: none;
}

.primary {
    width: 100%;
    margin-top: 8px;
    padding: 14px 24px;
    font: 600 16px var(--font-body);
    color: white;
    background: var(--rose-deep);
    border: 0;
    border-radius: 999px;
    cursor: pointer;
}

.primary:hover {
    background: var(--rose-dark);
}

.message {
    padding: 13px 15px;
    margin-bottom: 20px;
    border-radius: 10px;
    font-size: 14px;
}

.success {
    background: #e8f6ec;
    color: #17602f;
    border: 1px solid #bfe3cb;
}

.error {
    background: #fdecea;
    color: #9b1c1c;
    border: 1px solid #f3c7c2;
}

.hidden {
    display: none;
}

@media(max-width:600px) {

    form {
        padding: 24px 18px;
    }

    .layer-options {
        grid-template-columns: 1fr;
    }

    .cupcake-row {
        grid-template-columns: 1fr;
        gap: 7px;
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

    <a class="back" href="manage_cakes.php">
        &larr; Back to Manage Cakes
    </a>

    <h1>Add new cake</h1>

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

            <label for="cake_name">Cake name</label>

            <input
                type="text"
                id="cake_name"
                name="cake_name"
                placeholder="e.g. Strawberry Dream"
                value="<?php echo htmlspecialchars($old_name); ?>"
                required>

        </div>

        <div class="form-group">

            <label for="category">Cake category</label>

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
            Available sizes, layers & prices
        </h2>

        <p class="options-note" id="option-help">
            Select only the layer options you offer for each size. Unselected options will not be saved.
        </p>

        <div id="options-container"></div>

        <div class="form-group">

            <label for="image">Cake image</label>

            <div class="dropzone" id="dropzone">

                <div class="drop-hint">

                    <strong>Choose a cake photo</strong>

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
            Save cake
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

function buildRegular() {

    [6, 8, 10].forEach(diameter => {

        const card = document.createElement('section');
        card.className = 'size-card';

        const heading = document.createElement('div');
        heading.className = 'size-heading';

        const h = document.createElement('h3');

        h.textContent =
            (diameter === 6 ? 'Small' :
            diameter === 8 ? 'Medium' : 'Large') +
            ' — ' + diameter + ' inches';

        const note = document.createElement('span');
        note.className = 'size-sub';
        note.textContent = 'Choose available layers';

        heading.append(h, note);
        card.append(heading);

        const options = document.createElement('div');
        options.className = 'layer-options';

        [1, 2, 3].forEach(layer => {
            addLayerChoice(options, diameter, layer);
        });

        card.append(options);

        container.append(card);

    });

}

// Dedicated Bento Cake options
function buildBento() {

    help.textContent =
        'Bento Cakes are available in 4-inch and 5-inch sizes, single layer only. Select the sizes you offer and enter their prices.';

    [
        { name: '4 inches - 1 Layer', label: '4-inch Bento Cake', description: 'Small size · Single layer' },
        { name: '5 inches - 1 Layer', label: '5-inch Bento Cake', description: 'Larger size · Single layer' }
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

const fileInput = document.getElementById('image');
const preview = document.getElementById('preview');
const zone = document.getElementById('dropzone');

fileInput.addEventListener('change', () => {

    const file = fileInput.files[0];

    if (file && file.type.startsWith('image/')) {

        preview.src = URL.createObjectURL(file);
        zone.classList.add('has-image');

    } else {

        preview.removeAttribute('src');
        zone.classList.remove('has-image');

    }

});

</script>

</body>
</html>

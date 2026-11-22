<?php
session_start();
require_once "../database.php";

if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$is_success = false;
$customer_id = (int) $_SESSION["customer_id"];

// Cake options
$regular_sizes = ["6 inches", "8 inches", "10 inches"];
$bento_sizes = ["4 inches", "5 inches"];
$cupcake_sizes = ["6 Pieces", "12 Pieces", "24 Pieces"];
$layer_options = ["1", "2", "3"];

$allowed_flavors = [
    "Vanilla",
    "Chocolate",
    "Red Velvet",
    "Strawberry",
    "Ube",
    "Mocha",
    "Other"
];

$allowed_delivery_methods = ["Pickup", "Delivery"];
$allowed_payment_methods = ["Cash", "GCash"];

// Generate valid cake size values
$allowed_sizes = [];

foreach ($regular_sizes as $size) {
    foreach ($layer_options as $layer) {
        $allowed_sizes[] = $size . " - " . $layer . " " .
            ($layer === "1" ? "Layer" : "Layers");
    }
}

foreach ($bento_sizes as $size) {
    $allowed_sizes[] = $size . " - 1 Layer";
}

foreach ($cupcake_sizes as $size) {
    $allowed_sizes[] = $size;
}

// Old form values
$old_type = "";
$old_base_size = "";
$old_layers = "";
$old_size = "";
$old_flavor = "";
$old_color = "";
$old_quantity = "1";
$old_date = "";
$old_delivery_method = "Delivery";
$old_address = "";
$old_payment_method = "Cash";
$old_instructions = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $old_type = trim($_POST["cake_type"] ?? "");
    $old_base_size = trim($_POST["base_size"] ?? "");
    $old_layers = trim($_POST["layers"] ?? "");

    $old_flavor = trim($_POST["flavor"] ?? "");
    $old_color = trim($_POST["color"] ?? "");
    $old_quantity = trim($_POST["quantity"] ?? "1");
    $old_date = trim($_POST["preferred_date"] ?? "");

    $old_delivery_method = trim($_POST["delivery_method"] ?? "Delivery");
    $old_address = trim($_POST["delivery_address"] ?? "");

    // Payment method
    $old_payment_method = trim($_POST["payment_method"] ?? "");

    $old_instructions = trim($_POST["special_instructions"] ?? "");

    // Construct cake size value
    if ($old_type === "regular") {

        $old_size = $old_base_size . " - " . $old_layers . " " .
            ($old_layers === "1" ? "Layer" : "Layers");

    } elseif ($old_type === "bento") {

        $old_size = $old_base_size . " - 1 Layer";

    } elseif ($old_type === "cupcakes") {

        $old_size = $old_base_size;
    }

    // Pickup does not require a delivery address
    if ($old_delivery_method === "Pickup") {
        $old_address = "";
    }

    // Validate size and flavor
    if (
        !in_array($old_size, $allowed_sizes, true) ||
        !in_array($old_flavor, $allowed_flavors, true)
    ) {

        $message = "Please select a valid cake size and flavor.";

    } elseif (
        $old_type === "regular" &&
        (
            !in_array($old_base_size, $regular_sizes, true) ||
            !in_array($old_layers, $layer_options, true)
        )
    ) {

        $message = "Please select a valid regular cake size and layer.";

    } elseif (
        $old_type === "bento" &&
        !in_array($old_base_size, $bento_sizes, true)
    ) {

        $message = "Please select a valid Bento cake size.";

    } elseif (
        $old_type === "cupcakes" &&
        !in_array($old_base_size, $cupcake_sizes, true)
    ) {

        $message = "Please select a valid cupcake quantity.";

    } elseif ($old_color === "") {

        $message = "Please enter your preferred cake color.";

    } elseif (
        !filter_var($old_quantity, FILTER_VALIDATE_INT) ||
        (int)$old_quantity < 1 ||
        (int)$old_quantity > 50
    ) {

        $message = "Quantity must be between 1 and 50.";

    } elseif (
        $old_date === "" ||
        $old_date < date("Y-m-d")
    ) {

        $message = "Please choose a valid preferred date (today or a future date).";

    } elseif (
        !in_array($old_delivery_method, $allowed_delivery_methods, true)
    ) {

        $message = "Please select a valid delivery method.";

    } elseif (
        $old_delivery_method === "Delivery" &&
        $old_address === ""
    ) {

        $message = "Please enter your delivery address.";

    } elseif (
        !in_array($old_payment_method, $allowed_payment_methods, true)
    ) {

        $message = "Please select a valid payment method.";

    } elseif (
        !isset($_FILES["design_image"]) ||
        $_FILES["design_image"]["error"] !== UPLOAD_ERR_OK
    ) {

        $message = "Please upload a design inspiration image.";

    } elseif ($_FILES["design_image"]["size"] > 5 * 1024 * 1024) {

        $message = "Image must not exceed 5MB.";

    } else {

        $image_info = getimagesize($_FILES["design_image"]["tmp_name"]);

        $allowed_mimes = [
            "image/jpeg",
            "image/png",
            "image/webp"
        ];

        if (
            $image_info === false ||
            !in_array($image_info["mime"], $allowed_mimes, true)
        ) {

            $message = "Please upload a valid JPG, PNG, or WEBP image.";

        } else {

            $mime_to_extension = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            $extension = $mime_to_extension[$image_info["mime"]];
            $upload_dir = "../assets/images/custom_requests/";

            if (
                !is_dir($upload_dir) &&
                !mkdir($upload_dir, 0755, true)
            ) {

                $message = "Unable to create the upload folder.";

            } else {

                $image_name = "custom_" . bin2hex(random_bytes(16)) . "." . $extension;
                $image_path = $upload_dir . $image_name;

                if (!move_uploaded_file(
                    $_FILES["design_image"]["tmp_name"],
                    $image_path
                )) {

                    $message = "Image upload failed. Please try again.";

                } else {

                    try {

                        // Insert custom cake request including payment method
                        $stmt = $conn->prepare(
                            "INSERT INTO custom_cake_requests
                            (
                                customer_id,
                                design_image,
                                cake_size,
                                flavor,
                                color,
                                quantity,
                                preferred_date,
                                delivery_method,
                                delivery_address,
                                special_instructions,
                                payment_method,
                                request_status
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')"
                        );

                        $quantity = (int) $old_quantity;

                        $stmt->bind_param(
                            "issssisssss",
                            $customer_id,
                            $image_name,
                            $old_size,
                            $old_flavor,
                            $old_color,
                            $quantity,
                            $old_date,
                            $old_delivery_method,
                            $old_address,
                            $old_instructions,
                            $old_payment_method
                        );

                        if (!$stmt->execute()) {
                            throw new Exception("Insert failed");
                        }

                        $stmt->close();

                        $message = "Your custom cake request has been submitted! The admin will review your design and provide a quotation.";

                        $is_success = true;

                        // Reset form
                        $old_type = "";
                        $old_base_size = "";
                        $old_layers = "";
                        $old_size = "";
                        $old_flavor = "";
                        $old_color = "";
                        $old_date = "";
                        $old_delivery_method = "Delivery";
                        $old_address = "";
                        $old_payment_method = "Cash";
                        $old_instructions = "";
                        $old_quantity = "1";

                    } catch (Throwable $e) {

                        if (
                            isset($image_path) &&
                            file_exists($image_path)
                        ) {
                            unlink($image_path);
                        }

                        $message = "Unable to submit your request right now. Please try again.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Custom Cake Request | CakeCraft</title>

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
    --body: 'DM Sans', system-ui, sans-serif;
    --display: 'Playfair Display', Georgia, serif;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: var(--body);
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
    font-family: var(--display);
    font-size: 27px;
    font-weight: 700;
}

.brand span {
    color: #e3a9a5;
}

header nav {
    display: flex;
    gap: 22px;
    align-items: center;
}

header nav a {
    color: white;
    text-decoration: none;
    font-size: 14px;
}

header nav a:hover {
    color: #e3a9a5;
}

.container {
    max-width: 800px;
    margin: auto;
    padding: 40px 22px 70px;
}

.back {
    display: inline-block;
    color: var(--rose-deep);
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 20px;
}

.back:hover {
    text-decoration: underline;
}

h1 {
    font-family: var(--display);
    font-size: clamp(2rem, 4vw, 2.6rem);
    line-height: 1.2;
    margin-bottom: 8px;
}

.subtitle {
    color: var(--ink-soft);
    margin-bottom: 26px;
}

.form-card {
    background: white;
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 32px;
    box-shadow: 0 12px 35px #49302d12;
}

.field {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 7px;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid var(--line);
    border-radius: 10px;
    background: var(--bg);
    font: inherit;
    color: var(--ink);
}

input:focus,
select:focus,
textarea:focus {
    outline: 2px solid var(--rose);
    outline-offset: 2px;
}

input[type=file] {
    background: white;
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

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.notice {
    background: var(--bg-soft);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 22px;
    font-size: 14px;
    color: var(--ink-soft);
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

.primary {
    width: 100%;
    margin-top: 5px;
    padding: 14px 24px;
    font: 700 16px var(--body);
    color: white;
    background: var(--rose-deep);
    border: 0;
    border-radius: 999px;
    cursor: pointer;
}

.primary:hover {
    background: var(--rose-dark);
}

/* Delivery and Payment Options */

.delivery-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.delivery-option {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    background: var(--bg);
    border: 1.5px solid var(--line);
    border-radius: 12px;
    cursor: pointer;
    transition: border-color .2s, background .2s;
}

.delivery-option:has(input:checked) {
    border-color: var(--rose-deep);
    background: #fff5f3;
}

.delivery-option input {
    width: 18px;
    height: 18px;
    margin-top: 3px;
    accent-color: var(--rose-deep);
    flex-shrink: 0;
}

.delivery-option strong {
    display: block;
    font-size: 15px;
}

.delivery-option span {
    display: block;
    font-size: 13px;
    color: var(--ink-soft);
    margin-top: 3px;
}

#delivery_address_field {
    margin-top: 18px;
}

.payment-notice {
    background: #fff5f3;
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 13px 15px;
    font-size: 13px;
    color: var(--ink-soft);
    margin-top: 12px;
}

@media(max-width:600px) {

    header {
        flex-direction: column;
        gap: 10px;
    }

    header nav {
        flex-wrap: wrap;
        justify-content: center;
        gap: 12px;
    }

    .form-card {
        padding: 22px 18px;
    }

    .grid,
    .delivery-options {
        grid-template-columns: 1fr;
    }
}

</style>
</head>

<body>

<header>

    <div class="brand">Cake<span>Craft</span></div>

    <nav>
        <a href="dashboard.php">Home</a>
        <a href="cakes.php">Browse Cakes</a>
        <a href="my_orders.php">My Orders</a>
        <a href="logout.php">Logout</a>
    </nav>

</header>

<div class="container">

    <a class="back" href="dashboard.php">&larr; Back to Dashboard</a>

    <h1>Customize your cake</h1>

    <p class="subtitle">
        Tell us what you have in mind. Our admin will review your request and provide a quotation.
    </p>

    <div class="notice">
        <strong>Price notice:</strong>
        No price is shown when you submit your request. The admin will provide a quotation after reviewing your design.
        Your delivery fee, if applicable, will also be determined by the admin after approval.
    </div>

    <?php if ($message !== ""): ?>

        <div
            class="message <?php echo $is_success ? "success" : "error"; ?>"
            role="<?php echo $is_success ? "status" : "alert"; ?>"
        >
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <div class="form-card">

        <form method="POST" enctype="multipart/form-data">

            <!-- Cake Design Image -->

            <div class="field">

                <label for="design_image">
                    Cake design inspiration *
                </label>

                <input
                    type="file"
                    id="design_image"
                    name="design_image"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    required
                >

                <p class="hint">
                    Upload a photo of the cake design you want (JPG, PNG, or WEBP; max 5MB).
                </p>

            </div>

            <!-- Cake Type -->

            <div class="field">

                <label for="cake_type">
                    Cake type *
                </label>

                <select id="cake_type" name="cake_type" required>

                    <option value="">Select cake type</option>

                    <option value="regular" <?php echo $old_type === "regular" ? "selected" : ""; ?>>
                        Regular Cake
                    </option>

                    <option value="bento" <?php echo $old_type === "bento" ? "selected" : ""; ?>>
                        Bento Cake
                    </option>

                    <option value="cupcakes" <?php echo $old_type === "cupcakes" ? "selected" : ""; ?>>
                        Cupcakes
                    </option>

                </select>

            </div>

            <!-- Size and Layers -->

            <div class="grid">

                <div class="field">

                    <label for="base_size">
                        Size *
                    </label>

                    <select id="base_size" name="base_size" required>

                        <option value="">Select size</option>

                    </select>

                </div>

                <div class="field" id="layers_field">

                    <label for="layers">
                        Number of Layers *
                    </label>

                    <select id="layers" name="layers" required>

                        <option value="">Select layers</option>
                        <option value="1">1 Layer</option>
                        <option value="2">2 Layers</option>
                        <option value="3">3 Layers</option>

                    </select>

                </div>

            </div>

            <!-- Flavor -->

            <div class="field">

                <label for="flavor">
                    Flavor *
                </label>

                <select id="flavor" name="flavor" required>

                    <option value="">Select flavor</option>

                    <?php foreach ($allowed_flavors as $flavor): ?>

                        <option
                            value="<?php echo htmlspecialchars($flavor); ?>"
                            <?php echo $old_flavor === $flavor ? "selected" : ""; ?>
                        >
                            <?php echo htmlspecialchars($flavor); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <!-- Color and Quantity -->

            <div class="grid">

                <div class="field">

                    <label for="color">
                        Preferred color *
                    </label>

                    <input
                        type="text"
                        id="color"
                        name="color"
                        placeholder="e.g. pastel pink and white"
                        value="<?php echo htmlspecialchars($old_color); ?>"
                        required
                    >

                </div>

                <div class="field">

                    <label for="quantity">
                        Quantity *
                    </label>

                    <input
                        type="number"
                        id="quantity"
                        name="quantity"
                        min="1"
                        max="50"
                        value="<?php echo htmlspecialchars($old_quantity); ?>"
                        required
                    >

                </div>

            </div>

            <!-- Preferred Date -->

            <div class="field">

                <label for="preferred_date">
                    Preferred date *
                </label>

                <input
                    type="date"
                    id="preferred_date"
                    name="preferred_date"
                    min="<?php echo date("Y-m-d"); ?>"
                    value="<?php echo htmlspecialchars($old_date); ?>"
                    required
                >

            </div>

            <!-- Pickup or Delivery -->

            <div class="field">

                <label>
                    How would you like to receive your cake? *
                </label>

                <div class="delivery-options">

                    <label class="delivery-option">

                        <input
                            type="radio"
                            name="delivery_method"
                            value="Pickup"
                            <?php echo $old_delivery_method === "Pickup" ? "checked" : ""; ?>
                            required
                        >

                        <div>
                            <strong>Pick-up</strong>
                            <span>Pick up your cake at the shop. No delivery fee.</span>
                        </div>

                    </label>

                    <label class="delivery-option">

                        <input
                            type="radio"
                            name="delivery_method"
                            value="Delivery"
                            <?php echo $old_delivery_method === "Delivery" ? "checked" : ""; ?>
                            required
                        >

                        <div>
                            <strong>Delivery</strong>
                            <span>Admin will determine the delivery fee after reviewing your request.</span>
                        </div>

                    </label>

                </div>

            </div>

            <!-- Delivery Address -->

            <div
                class="field"
                id="delivery_address_field"
                <?php echo $old_delivery_method !== "Delivery" ? 'style="display:none;"' : ""; ?>
            >

                <label for="delivery_address">
                    Delivery address *
                </label>

                <textarea
                    id="delivery_address"
                    name="delivery_address"
                    placeholder="Enter your complete delivery address"
                    <?php echo $old_delivery_method === "Delivery" ? "required" : ""; ?>
                ><?php echo htmlspecialchars($old_address); ?></textarea>

                <p class="hint">
                    Please provide your complete address. The delivery fee will be added after Admin approves your request.
                </p>

            </div>

            <!-- Payment Method -->

            <div class="field">

                <label>
                    Payment method *
                </label>

                <div class="delivery-options">

                    <label class="delivery-option">

                        <input
                            type="radio"
                            name="payment_method"
                            value="Cash"
                            <?php echo $old_payment_method === "Cash" ? "checked" : ""; ?>
                            required
                        >

                        <div>
                            <strong>Cash</strong>
                            <span>Pay in cash upon pick-up or delivery.</span>
                        </div>

                    </label>

                    <label class="delivery-option">

                        <input
                            type="radio"
                            name="payment_method"
                            value="GCash"
                            <?php echo $old_payment_method === "GCash" ? "checked" : ""; ?>
                            required
                        >

                        <div>
                            <strong>GCash</strong>
                            <span>Pay through GCash after your request is approved.</span>
                        </div>

                    </label>

                </div>

                <div class="payment-notice">
                    <strong>Payment reminder:</strong>
                    Your custom cake request will first be reviewed by the admin.
                    If you choose GCash, payment instructions will be provided after your request is approved and the final quotation is set.
                </div>

            </div>

            <!-- Additional Instructions -->

            <div class="field">

                <label for="special_instructions">
                    Additional instructions
                </label>

                <textarea
                    id="special_instructions"
                    name="special_instructions"
                    placeholder="Cake message, details, decorations, or other requests..."
                ><?php echo htmlspecialchars($old_instructions); ?></textarea>

            </div>

            <button type="submit" class="primary">
                Submit Custom Cake Request
            </button>

        </form>

    </div>

</div>

<script>

// Available sizes by cake type
const sizeOptions = {
    regular: ["6 inches", "8 inches", "10 inches"],
    bento: ["4 inches", "5 inches"],
    cupcakes: ["6 Pieces", "12 Pieces", "24 Pieces"]
};

const cakeTypeSelect = document.getElementById("cake_type");
const sizeSelect = document.getElementById("base_size");
const layersSelect = document.getElementById("layers");
const layersField = document.getElementById("layers_field");

const previousType = <?php echo json_encode($old_type); ?>;
const previousSize = <?php echo json_encode($old_base_size); ?>;
const previousLayers = <?php echo json_encode($old_layers); ?>;

// Delivery fields
const deliveryRadios = document.querySelectorAll('input[name="delivery_method"]');
const deliveryAddressField = document.getElementById("delivery_address_field");
const deliveryAddressInput = document.getElementById("delivery_address");

// Toggle address field based on selected delivery method
function updateDeliveryFields() {

    const selectedMethod = document.querySelector(
        'input[name="delivery_method"]:checked'
    );

    if (selectedMethod && selectedMethod.value === "Delivery") {

        deliveryAddressField.style.display = "";
        deliveryAddressInput.required = true;

    } else {

        deliveryAddressField.style.display = "none";
        deliveryAddressInput.required = false;
        deliveryAddressInput.value = "";

    }
}

deliveryRadios.forEach(radio => {
    radio.addEventListener("change", updateDeliveryFields);
});

// Initialize delivery fields on page load
updateDeliveryFields();

// Update size choices based on cake type
function updateSizes(selectedSize = "") {

    const cakeType = cakeTypeSelect.value;

    sizeSelect.innerHTML = '<option value="">Select size</option>';

    if (!cakeType || !sizeOptions[cakeType]) {

        sizeSelect.disabled = true;
        layersField.style.display = "none";
        layersSelect.required = false;
        layersSelect.value = "";

        return;
    }

    sizeSelect.disabled = false;

    sizeOptions[cakeType].forEach(size => {

        const option = document.createElement("option");

        option.value = size;
        option.textContent = size;

        if (size === selectedSize) {
            option.selected = true;
        }

        sizeSelect.appendChild(option);

    });

    // Regular cakes require layer selection
    if (cakeType === "regular") {

        layersField.style.display = "";
        layersSelect.required = true;

    } else {

        layersField.style.display = "none";
        layersSelect.required = false;
        layersSelect.value = "";

    }
}

// Initialize form
cakeTypeSelect.addEventListener("change", function () {

    updateSizes();
    layersSelect.value = "";

});

// Restore values after validation error
if (previousType) {

    cakeTypeSelect.value = previousType;
    updateSizes(previousSize);

    if (previousType === "regular") {
        layersSelect.value = previousLayers;
    }

} else {

    sizeSelect.disabled = true;
    layersField.style.display = "none";
    layersSelect.required = false;

}

</script>

</body>
</html>
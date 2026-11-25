<?php
session_start();
require_once "../database.php";

// Check customer login
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

// Get selected cake ID
$cake_id = isset($_GET["cake_id"]) ? (int)$_GET["cake_id"] : 0;

if ($cake_id <= 0) {
    header("Location: cakes.php");
    exit();
}

// Get cake information including category
// Exclude archived cakes
$stmt = $conn->prepare(
    "SELECT id, cake_name, category, description, image
     FROM cake_options
     WHERE id = ? AND archived_at IS NULL"
);

$stmt->bind_param("i", $cake_id);
$stmt->execute();

$cake = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cake) {
    header("Location: cakes.php");
    exit();
}

// Get ALL available sizes and prices for this cake
$size_stmt = $conn->prepare(
    "SELECT id, size_name, price
     FROM cake_sizes
     WHERE cake_id = ?
     ORDER BY id ASC"
);

$size_stmt->bind_param("i", $cake_id);
$size_stmt->execute();

$sizes = $size_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$size_stmt->close();

// Re-index array
$sizes = array_values($sizes);

$image_path = "../assets/images/" . $cake["image"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customize Cake | CakeCraft</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/brand-system.css">

    <style>
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
            --line-strong: #e0cbc3;
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;
            --gutter: max(6%, calc((100vw - 1180px) / 2));
            --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
            --ease: cubic-bezier(.2, .7, .2, 1);
        }

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-body);
            line-height: 1.6;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
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
            z-index: 50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px var(--gutter);
            background: rgba(255, 250, 247, 0.96);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
            box-shadow: 0 2px 14px rgba(59, 39, 36, 0.05);
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--ink);
            line-height: 1;
            white-space: nowrap;
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

        .logo {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: var(--ink);
        }

        .logo span.brand-craft {
            color: var(--craft-color);
        }

        nav {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        nav a {
            position: relative;
            color: var(--ink);
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            padding: 6px 0;
            transition: color .2s var(--ease);
        }

        nav a::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 2px;
            background: var(--rose);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .25s var(--ease);
        }

        nav a:hover,
        nav a.active {
            color: var(--rose-deep);
        }

        nav a:hover::after,
        nav a.active::after {
            transform: scaleX(1);
        }

        nav a.logout {
            padding: 9px 20px;
            border: 1.5px solid var(--rose);
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            transition: background .2s var(--ease), color .2s var(--ease), border-color .2s var(--ease);
        }

        nav a.logout::after {
            display: none;
        }

        nav a.logout:hover {
            background: var(--bg-soft);
            color: var(--rose-deep);
            border-color: var(--rose-deep);
        }

        /* CONTENT */

        .container {
            max-width: 1180px;
            margin: 0 auto;
            padding: 40px 24px 72px;
        }

        .back {
            display: inline-block;
            margin-bottom: 22px;
            color: var(--rose-deep);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .back:hover {
            color: var(--rose-darker);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .content {
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            gap: 56px;
            align-items: start;
            background: var(--surface);
            padding: 36px;
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: 0 14px 40px rgba(73, 48, 45, 0.08);
        }

        .image-col {
            position: sticky;
            top: 96px;
        }

        .cake-image {
            width: 100%;
            height: 480px;
            object-fit: cover;
            border-radius: 14px;
            display: block;
        }

        .image-col > p {
            display: grid;
            place-items: center;
            height: 320px;
            border-radius: 14px;
            background: var(--bg-soft);
            color: var(--rose-deep);
            font-weight: 500;
        }

        .cake-category-pill {
            display: inline-block;
            margin-bottom: 10px;
            padding: 5px 12px;
            border-radius: 999px;
            background: var(--bg-soft);
            border: 1px solid var(--line);
            color: var(--rose-deep);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .cake-info h1 {
            font-family: var(--font-display);
            font-size: clamp(1.9rem, 3.4vw, 2.6rem);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.015em;
            margin-bottom: 14px;
        }

        .description {
            color: var(--ink-soft);
            font-size: 16px;
            line-height: 1.75;
            margin-bottom: 32px;
            padding-bottom: 28px;
            border-bottom: 1px solid var(--line);
        }

        .section-title {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        /* SIZE OPTIONS */

        .size-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            background: var(--bg);
            border: 1.5px solid var(--line-strong);
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: border-color .2s var(--ease), background .2s var(--ease), box-shadow .2s var(--ease);
        }

        .size-option:hover {
            border-color: var(--rose);
        }

        .size-option:has(input:checked) {
            border-color: var(--rose-deep);
            background: var(--bg-soft);
            box-shadow: 0 0 0 3px rgba(196, 124, 120, 0.2);
        }

        .size-option:has(input:focus-visible) {
            outline: 3px solid var(--rose);
            outline-offset: 3px;
        }

        .size-option > span:first-child {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .size-option input {
            width: 20px;
            height: 20px;
            accent-color: var(--rose-deep);
            margin: 0;
            cursor: pointer;
        }

        .size-name {
            font-weight: 600;
            font-size: 16px;
        }

        .size-price {
            color: var(--rose-deep);
            font-weight: 700;
            font-size: 16px;
            white-space: nowrap;
        }

        /* SUMMARY */

        .selected-price {
            background: var(--bg-soft);
            border: 1px solid var(--line);
            padding: 20px 22px;
            border-radius: 12px;
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
        }

        .selected-price strong {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 700;
            color: var(--rose-deep);
        }

        .continue-btn {
            display: block;
            width: 100%;
            margin-top: 20px;
            padding: 15px;
            font-family: inherit;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            background: var(--rose-deep);
            border: none;
            border-radius: 999px;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(166, 90, 86, 0.28);
            transition: background .2s var(--ease), transform .2s var(--ease), box-shadow .2s var(--ease);
        }

        .continue-btn:hover {
            background: var(--rose-darker);
            transform: translateY(-1px);
            box-shadow: 0 12px 26px rgba(166, 90, 86, 0.34);
        }

        .no-sizes {
            padding: 20px;
            background: var(--bg-soft);
            border: 1px dashed var(--rose);
            border-radius: 12px;
            color: var(--ink-soft);
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {
            .content {
                grid-template-columns: 1fr;
                gap: 32px;
                padding: 24px;
            }

            .image-col {
                position: static;
            }

            .cake-image {
                height: 320px;
            }
        }

        @media (max-width: 700px) {
            header {
                flex-direction: column;
                gap: 12px;
                padding: 14px 6%;
            }

            nav {
                flex-wrap: wrap;
                justify-content: center;
                gap: 8px 20px;
            }

            .container {
                padding: 28px 16px 56px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *, *::before, *::after {
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

<header>
    <a href="dashboard.php" class="brand-mark" aria-label="CakeCraft Home">
        <span class="brand-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3v3" />
                <path d="M12 6c-1.1 0-2 .7-2 1.6 0 .5.2.9.5 1.4" />
                <path d="M5 12h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" />
                <path d="M3 16c1.5 0 2.5-1 4-1s2.5 1 4 1 2.5-1 4-1 2.5 1 4 1 2-.5 2-1" />
                <path d="M3 19h18" />
            </svg>
        </span>
        <span class="logo">Cake<span class="brand-craft">Craft</span></span>
    </a>

    <nav>
        <a href="dashboard.php">Home</a>
        <a href="cakes.php" class="active">Cakes</a>
        <a href="my_orders.php">My Orders</a>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</header>

<div class="container">

    <a href="cakes.php" class="back">← Back to cakes</a>

    <div class="content">

        <div class="image-col">
            <?php if (!empty($cake["image"]) && file_exists($image_path)): ?>
                <img
                    src="<?php echo htmlspecialchars($image_path); ?>"
                    alt="<?php echo htmlspecialchars($cake["cake_name"]); ?>"
                    class="cake-image"
                >
            <?php else: ?>
                <p>Image not available.</p>
            <?php endif; ?>
        </div>

        <div class="cake-info">

            <?php if (!empty($cake["category"])): ?>
                <span class="cake-category-pill">
                    <?php echo htmlspecialchars($cake["category"]); ?>
                </span>
            <?php endif; ?>

            <h1><?php echo htmlspecialchars($cake["cake_name"]); ?></h1>

            <p class="description">
                <?php echo nl2br(htmlspecialchars($cake["description"])); ?>
            </p>

            <h3 class="section-title">Choose your cake size</h3>

            <?php if (count($sizes) > 0): ?>

                <form method="POST" action="order_details.php">

                    <input type="hidden" name="cake_id" value="<?php echo $cake_id; ?>">

                    <?php foreach ($sizes as $index => $size): ?>

                        <label class="size-option">

                            <span>
                                <input
                                    type="radio"
                                    name="size_id"
                                    value="<?php echo (int)$size["id"]; ?>"
                                    data-price="<?php echo (float)$size["price"]; ?>"
                                    <?php echo $index === 0 ? "checked" : ""; ?>
                                    required
                                >

                                <span class="size-name">
                                    <?php echo htmlspecialchars($size["size_name"]); ?>
                                </span>
                            </span>

                            <span class="size-price">
                                ₱<?php echo number_format((float)$size["price"], 2); ?>
                            </span>

                        </label>

                    <?php endforeach; ?>

                    <div class="selected-price">
                        <span>Selected price</span>
                        <strong id="selectedPrice" aria-live="polite">
                            ₱<?php echo number_format((float)$sizes[0]["price"], 2); ?>
                        </strong>
                    </div>

                    <button type="submit" class="continue-btn">
                        Confirm size
                    </button>

                </form>

            <?php else: ?>

                <div class="no-sizes">
                    No available sizes for this cake yet.
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<script>
    const sizeInputs = document.querySelectorAll('input[name="size_id"]');
    const selectedPrice = document.getElementById("selectedPrice");

    sizeInputs.forEach(input => {
        input.addEventListener("change", function () {

            const price = Number(this.dataset.price);

            selectedPrice.textContent = "₱" + price.toLocaleString(
                "en-PH",
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            );

        });
    });
</script>

</body>
</html>
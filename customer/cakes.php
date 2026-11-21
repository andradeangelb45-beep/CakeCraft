<?php
session_start();
require_once "../database.php";

// Check customer login
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

// Cake categories available for filtering
$categories = [
    "Birthday Cake",
    "Wedding Cake",
    "Christening Cake",
    "Cupcakes",
    "Bento Cake"
];

$selected_category = trim($_GET["category"] ?? "");
if (!in_array($selected_category, $categories, true)) {
    $selected_category = "";
}

// Get available cakes (optionally filtered by category)
if ($selected_category !== "") {
    $stmt = $conn->prepare("SELECT * FROM cake_options WHERE category = ? ORDER BY id DESC");
    $stmt->bind_param("s", $selected_category);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM cake_options ORDER BY id DESC";
    $result = $conn->query($sql);
}

if (!$result) {
    die("Error loading cakes: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Our Cakes | CakeCraft</title>

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
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;
            --gutter: max(6%, calc((100vw - 1180px) / 2));
            --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
            --shadow-lift: 0 14px 34px rgba(73, 48, 45, 0.14);
            --ease: cubic-bezier(.2, .7, .2, 1);
        }

        * {
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
            display: flex;
            flex-direction: column;
        }

        :focus-visible {
            outline: 3px solid var(--rose);
            outline-offset: 3px;
            border-radius: 4px;
        }

        /* Navigation */
        header {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px var(--gutter);
            background: rgba(255, 250, 247, 0.88);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }

        .logo {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .logo span {
            color: var(--rose);
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
            transition: background .2s var(--ease);
        }

        nav a.logout::after {
            display: none;
        }

        nav a.logout:hover {
            background: var(--bg-soft);
        }

        /* Page heading */
        .page-heading {
            padding: 64px var(--gutter) 56px;
            background: var(--bg-soft);
            border-bottom: 1px solid var(--line);
        }

        .page-heading h1 {
            font-family: var(--font-display);
            font-size: clamp(2rem, 4.4vw, 3rem);
            font-weight: 600;
            line-height: 1.15;
            letter-spacing: -0.015em;
            margin-bottom: 14px;
        }

        .page-heading p {
            color: var(--ink-soft);
            font-size: 17px;
            line-height: 1.7;
            max-width: 540px;
        }

        /* Catalog */
        .catalog {
            flex: 1;
            padding: 56px var(--gutter) 24px;
        }

        .catalog h2 {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .catalog-description {
            color: var(--ink-soft);
            margin-bottom: 24px;
        }

        .category-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
        }

        .category-filter {
            display: inline-block;
            padding: 9px 18px;
            border: 1.5px solid var(--line);
            border-radius: 999px;
            background: var(--surface);
            color: var(--ink-soft);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background .2s var(--ease), color .2s var(--ease), border-color .2s var(--ease);
        }

        .category-filter:hover,
        .category-filter.active {
            background: var(--rose-deep);
            border-color: var(--rose-deep);
            color: #fff;
        }

        .cake-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .cake-card {
            display: flex;
            flex-direction: column;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform .3s var(--ease), box-shadow .3s var(--ease);
        }

        .cake-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lift);
        }

        .cake-image {
            width: 100%;
            height: 250px;
            background: var(--bg-soft);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .cake-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .5s var(--ease);
        }

        .cake-card:hover .cake-image img {
            transform: scale(1.04);
        }

        .no-image {
            color: var(--rose-deep);
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            padding: 0 20px;
        }

        .cake-info {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 26px;
        }

        .cake-info h3 {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: 8px;
        }

        /* Category label shown below cake name */
        .cake-category {
            display: inline-block;
            align-self: flex-start;
            margin-bottom: 12px;
            padding: 5px 12px;
            border-radius: 999px;
            background: var(--bg-soft);
            border: 1px solid var(--line);
            color: var(--rose-deep);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .02em;
        }

        .cake-description {
            color: var(--ink-soft);
            font-size: 15px;
            line-height: 1.65;
            margin-bottom: 18px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .cake-price {
            margin-top: auto;
            margin-bottom: 20px;
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 600;
            color: var(--rose-deep);
        }

        .cake-price small {
            display: block;
            font-family: var(--font-body);
            font-size: 12px;
            font-weight: 500;
            color: var(--ink-soft);
            margin-bottom: 2px;
        }

        .cake-button {
            display: block;
            text-align: center;
            background: var(--rose-deep);
            color: #fff;
            padding: 13px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background .2s var(--ease);
        }

        .cake-button:hover {
            background: var(--rose-darker);
        }

        /* Empty state */
        .empty-state {
            background: var(--surface);
            border: 1px dashed var(--rose);
            border-radius: 14px;
            padding: 64px 24px;
            text-align: center;
            margin-top: 25px;
        }

        .empty-state h3 {
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--ink-soft);
            max-width: 420px;
            margin: 0 auto;
        }

        /* Footer */
        footer {
            padding: 28px 20px 36px;
            margin-top: 40px;
            border-top: 1px solid var(--line);
            text-align: center;
            color: var(--ink-soft);
            font-size: 14px;
        }

        @media (max-width: 1000px) {
            .cake-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .page-heading {
                padding: 44px 6% 40px;
            }

            .catalog {
                padding: 40px 6% 16px;
            }

            .cake-grid {
                grid-template-columns: 1fr;
            }

            .cake-image {
                height: 230px;
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

    <div class="logo">Cake<span>Craft</span></div>

    <nav>
        <a href="dashboard.php">Home</a>
        <a href="cakes.php" class="active" aria-current="page">Cakes</a>
        <a href="my_orders.php">My Orders</a>
        <a href="logout.php" class="logout">Logout</a>
    </nav>

</header>

<section class="page-heading">

    <h1>Our cakes</h1>

    <p>
        Discover our delicious cake selections
        and find the perfect cake for your celebration.
    </p>

</section>

<section class="catalog">

    <h2>Available cakes</h2>

    <p class="catalog-description">
        Choose from our collection of cakes made for your special moments.
    </p>

    <div class="category-filters" aria-label="Filter cakes by category">

        <a class="category-filter <?php echo $selected_category === "" ? "active" : ""; ?>"
           href="cakes.php"
           <?php echo $selected_category === "" ? 'aria-current="page"' : ""; ?>>
            All Cakes
        </a>

        <?php foreach ($categories as $filter_category): ?>

            <a class="category-filter <?php echo $selected_category === $filter_category ? "active" : ""; ?>"
               href="cakes.php?category=<?php echo urlencode($filter_category); ?>"
               <?php echo $selected_category === $filter_category ? 'aria-current="page"' : ""; ?>>

                <?php echo htmlspecialchars($filter_category); ?>

            </a>

        <?php endforeach; ?>

    </div>

    <?php if ($result->num_rows > 0): ?>

        <div class="cake-grid">

            <?php while ($cake = $result->fetch_assoc()): ?>

                <?php
                    $image = !empty($cake["image"])
                        ? "../assets/images/" . rawurlencode(basename($cake["image"]))
                        : "";

                    $category = trim($cake["category"] ?? "");

                    if ($category === "") {
                        $category = "Uncategorized";
                    }
                ?>

                <div class="cake-card">

                    <div class="cake-image">

                        <?php if (!empty($image)): ?>

                            <img
                                src="<?php echo htmlspecialchars($image); ?>"
                                alt="<?php echo htmlspecialchars($cake["cake_name"]); ?>"
                                loading="lazy"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">

                            <div class="no-image" style="display:none;">
                                Cake image unavailable
                            </div>

                        <?php else: ?>

                            <div class="no-image">
                                Cake image coming soon
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="cake-info">

                        <h3>
                            <?php echo htmlspecialchars($cake["cake_name"]); ?>
                        </h3>

                        <span class="cake-category">
                            <?php echo htmlspecialchars($category); ?>
                        </span>

                        <p class="cake-description">
                            <?php echo htmlspecialchars(
                                $cake["description"] ?? "A delicious cake for your special occasion."
                            ); ?>
                        </p>

                        <div class="cake-price">
                            <small>Base price</small>
                            ₱<?php echo number_format((float)$cake["base_price"], 2); ?>
                        </div>

                        <a href="customize.php?cake_id=<?php echo (int)$cake["id"]; ?>"
                           class="cake-button">
                            Customize this cake
                        </a>

                    </div>

                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty-state">

            <h3>
                <?php echo $selected_category !== "" ? "No cakes in this category yet" : "No cakes available yet"; ?>
            </h3>

            <p>

                <?php if ($selected_category !== ""): ?>

                    There are currently no <?php echo htmlspecialchars($selected_category); ?> options available. Please check another category.

                <?php else: ?>

                    Our cake collection is currently being prepared. Please check back soon!

                <?php endif; ?>

            </p>

        </div>

    <?php endif; ?>

</section>

<footer>

    <p>&copy; <?php echo date("Y"); ?> CakeCraft. All rights reserved.</p>

</footer>

</body>
</html>

<?php
require_once "database.php";

// Get updated admin contact details
$admin_query = $conn->query(
    "SELECT contact_name, email, contact_number, location
     FROM site_contact
     WHERE id = 1
     LIMIT 1"
);

$admin = $admin_query ? $admin_query->fetch_assoc() : null;

// ==========================================
// GET LATEST UPLOADED CAKE IMAGE PER CATEGORY
// ==========================================

$categories = [
    "Birthday Cake",
    "Wedding Cake",
    "Christening Cake",
    "Cupcakes",
    "Bento Cake",
    "All-Occasion Cakes"
];

$category_images = [];

foreach ($categories as $category) {
    $category_images[$category] = "";
}

$cakes_query = $conn->query(
    "SELECT category, image
     FROM cake_options
     ORDER BY id DESC"
);

if ($cakes_query) {
    while ($cake = $cakes_query->fetch_assoc()) {
        $category = trim($cake["category"] ?? "");
        $image = trim($cake["image"] ?? "");

        if (
            isset($category_images[$category]) &&
            $category_images[$category] === "" &&
            $image !== ""
        ) {
            $category_images[$category] = "assets/images/" . basename($image);
        }
    }
}

// ==========================================
// CATEGORY LOGIN LINKS
// ==========================================

function categoryLoginLink($category) {
    return "customer/login.php?redirect=cakes.php&category=" . urlencode($category);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CakeCraft | Custom Cakes Made With Love</title>

    <link rel="stylesheet" href="assets/css/style.css?v=12">

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
            --craft-color: #a65a56; /* Baguhin ito para tumugma sa "Craft." sa dati mong gawa */
            --line: #efe0da;
            --radius-sm: 8px;
            --radius-md: 16px;
            --radius-lg: 18px;
            --shadow-sm: 0 5px 20px rgba(74, 45, 36, 0.04);
            --shadow-md: 0 8px 28px rgba(74, 45, 36, 0.07);
            --shadow-lg: 0 14px 30px rgba(74, 45, 36, 0.10);
        }

        body {
            background: var(--bg) !important;
            color: var(--ink) !important;
        }

        header,
        .navbar {
            background: var(--bg) !important;
        }

        /* Brand mark with icon beside CakeCraft. */
        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            line-height: 1;
        }

        .brand-wordmark {
            color: var(--ink);
        }

        .brand-craft {
            color: var(--craft-color) !important;
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

        footer .brand-mark {
            justify-content: center;
            margin-bottom: 10px;
        }

        footer .brand-mark h2 {
            margin: 0;
        }

        footer .brand-icon {
            background: var(--surface);
            border-color: rgba(166, 90, 86, 0.18);
            color: var(--craft-color);
        }

        .hero {
            background: var(--bg-soft) !important;
        }

        .about-section {
            background: var(--bg-soft) !important;
        }

        .how-section {
            background: var(--bg) !important;
        }

        footer {
            background: var(--bg-tint) !important;
            color: var(--ink) !important;
        }

        .primary-btn,
        .signup-btn {
            background: var(--rose) !important;
            color: #ffffff !important;
        }

        .primary-btn:hover,
        .signup-btn:hover {
            background: var(--rose-deep) !important;
        }

        .login-btn {
            border-color: var(--line) !important;
            color: var(--ink) !important;
        }

        .login-btn:hover {
            background: var(--bg-soft) !important;
        }

        /* ==========================================
           CAKE CATEGORIES
        ========================================== */

        .cakes-section {
            padding: 85px 8%;
            background: var(--bg) !important;
        }

        .cakes-section .section-heading {
            margin-bottom: 45px;
        }

        .cakes-section .section-heading > p {
            color: var(--rose-deep) !important;
            letter-spacing: 3px;
            font-weight: 600;
        }

        .cakes-section .section-heading h2 {
            color: var(--ink) !important;
        }

        .cakes-section .section-heading span {
            color: var(--ink-soft) !important;
        }

        .cake-container {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 28px !important;
            max-width: 1200px;
            margin: 0 auto;
        }

        .cake-card {
            background: var(--surface) !important;
            border: 1px solid var(--line) !important;
            border-radius: var(--radius-md) !important;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .cake-card:hover {
            transform: translateY(-7px);
            box-shadow: var(--shadow-lg);
        }

        .cake-card img {
            display: block;
            width: 100%;
            height: 250px !important;
            object-fit: cover;
            object-position: center;
            transition: transform 0.4s ease;
        }

        .cake-card:hover img {
            transform: scale(1.04);
        }

        .category-image-placeholder {
            width: 100%;
            height: 250px;
            background: var(--bg-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 25px;
            color: var(--rose-deep);
            font-size: 14px;
        }

        .cake-info {
            padding: 24px !important;
            display: flex;
            flex-direction: column;
        }

        .cake-info h3 {
            font-family: 'Playfair Display', serif;
            font-size: 23px;
            color: var(--ink) !important;
            margin: 0 0 10px;
        }

        .cake-info p {
            color: var(--ink-soft) !important;
            font-size: 14px;
            line-height: 1.7;
            min-height: 48px;
            margin-bottom: 20px;
        }

        .cake-info a {
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--rose) !important;
            color: #ffffff !important;
            text-decoration: none !important;
            padding: 13px 18px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            transition: background 0.25s ease, transform 0.25s ease;
        }

        .cake-info a:hover {
            background: var(--rose-deep) !important;
            transform: translateY(-2px);
        }

        .cake-info a span {
            transition: transform 0.25s ease;
        }

        .cake-info a:hover span {
            transform: translateX(4px);
        }

        .step {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
        }

        .step h3 {
            color: var(--rose) !important;
        }

        .step-design-note {
            background: var(--bg-soft) !important;
            border-radius: 10px;
        }

        /* ==========================================
           CONTACT US CARD
        ========================================== */

        .contact-details {
            width: 420px;
            max-width: 100%;
            margin: 30px auto;
            padding: 28px 30px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            box-sizing: border-box;
            text-align: left;
        }

        .contact-details h3 {
            font-family: 'Playfair Display', serif;
            font-size: 23px;
            color: var(--ink);
            margin: 0 0 22px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--line);
        }

        .contact-details p {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0 0 16px;
            font-size: 14px;
            line-height: 1.7;
            color: var(--ink-soft);
        }

        .contact-details p:last-child {
            margin-bottom: 0;
        }

        .contact-details strong {
            color: var(--ink);
            font-weight: 600;
        }

        .contact-details a {
            color: var(--rose-deep);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .contact-details a:hover {
            color: var(--rose-darker);
            text-decoration: underline;
        }

        .contact-icon {
            width: 30px;
            height: 30px;
            min-width: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-soft);
            color: var(--rose-deep);
            border-radius: 50%;
            font-size: 14px;
            margin-top: 1px;
        }

        .contact-location {
            align-items: flex-start !important;
        }

        .contact-location .location-icon {
            width: 30px;
            height: 30px;
            min-width: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-soft);
            border-radius: 50%;
            margin-top: 1px;
            font-size: 14px;
        }

        .contact-location .location-content {
            flex: 1;
        }

        .contact-location .maps-link {
            display: inline-block;
            margin-top: 5px;
            font-size: 13px;
            font-weight: 600;
        }

        .footer-copyright {
            font-size: 13px;
            opacity: 0.8;
        }

        /* ==========================================
           TABLET
        ========================================== */

        @media (max-width: 950px) {
            .cake-container {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 22px !important;
            }

            .cake-card img,
            .category-image-placeholder {
                height: 230px !important;
            }
        }

        /* ==========================================
           MOBILE
        ========================================== */

        @media (max-width: 600px) {
            .cakes-section {
                padding: 60px 5%;
            }

            .cakes-section .section-heading {
                margin-bottom: 30px;
            }

            .cake-container {
                grid-template-columns: 1fr !important;
                gap: 22px !important;
            }

            .cake-card img,
            .category-image-placeholder {
                height: 240px !important;
            }

            .cake-info {
                padding: 20px !important;
            }

            .cake-info h3 {
                font-size: 21px;
            }

            .cake-info p {
                min-height: unset;
            }

            .contact-details {
                width: 100%;
                padding: 23px 20px;
                margin: 25px 0;
                border-radius: 15px;
            }

            .contact-details h3 {
                font-size: 21px;
                margin-bottom: 18px;
            }

            .contact-details p {
                font-size: 13px;
                gap: 9px;
                margin-bottom: 14px;
            }

            .contact-icon,
            .contact-location .location-icon {
                width: 28px;
                height: 28px;
                min-width: 28px;
            }
        }
    </style>
</head>

<body>

    <!-- Navigation -->
    <header>
        <nav class="navbar">

            <div class="logo brand-mark">
                <span class="brand-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3v3" />
                        <path d="M12 6c-1.1 0-2 .7-2 1.6 0 .5.2.9.5 1.4" />
                        <path d="M5 12h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" />
                        <path d="M3 16c1.5 0 2.5-1 4-1s2.5 1 4 1 2.5-1 4-1 2.5 1 4 1 2-.5 2-1" />
                        <path d="M3 19h18" />
                    </svg>
                </span>
                <span class="brand-wordmark">Cake<span class="brand-craft">Craft.</span></span>
            </div>

            <ul class="nav-links">
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="#cakes">Cakes</a></li>
                <li><a href="#about">About Us</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>

            <div class="nav-buttons">
                <a href="customer/login.php" class="login-btn">Login</a>
                <a href="customer/register.php" class="signup-btn">Sign Up</a>
            </div>

        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">

        <div class="hero-content">

            <p class="subtitle">
                MADE WITH LOVE, BAKED FOR YOU
            </p>

            <h1>
                Make Every Celebration <br>
                <span>A Little Sweeter.</span>
            </h1>

            <p class="hero-description">
                Create your perfect cake with your favorite flavors,
                sizes, and personalized messages. Let us make your
                special moments more memorable.
            </p>

            <a href="#cakes" class="primary-btn">
                Explore Our Cakes
            </a>

        </div>

        <div class="hero-image">

            <img
                src="assets/images/Elegant Blush Bakery Dessert Spread.png"
                alt="Beautiful decorated cakes and desserts"
            >

        </div>

    </section>

    <!-- Cake Categories -->
    <section class="cakes-section" id="cakes">

        <div class="section-heading">

            <p>OUR COLLECTION</p>

            <h2>Explore Our Cake Collections</h2>

            <span>
                Find the perfect cake for every celebration and special moment.
            </span>

        </div>

        <div class="cake-container">

            <!-- Birthday Cakes -->
            <div class="cake-card">
                <?php if (!empty($category_images["Birthday Cake"])): ?>
                    <img
                        src="<?php echo htmlspecialchars($category_images["Birthday Cake"]); ?>"
                        alt="Latest Birthday Cake uploaded by admin"
                        loading="lazy"
                    >
                <?php else: ?>
                    <div class="category-image-placeholder">
                        No Birthday Cake image uploaded yet.
                    </div>
                <?php endif; ?>

                <div class="cake-info">
                    <h3>Birthday Cakes</h3>
                    <p>
                        Make birthdays extra special with cakes made for celebrating.
                    </p>
                    <a href="<?php echo htmlspecialchars(categoryLoginLink("Birthday Cake")); ?>">
                        Explore Collection <span>→</span>
                    </a>
                </div>
            </div>

            <!-- Wedding Cakes -->
            <div class="cake-card">
                <?php if (!empty($category_images["Wedding Cake"])): ?>
                    <img
                        src="<?php echo htmlspecialchars($category_images["Wedding Cake"]); ?>"
                        alt="Latest Wedding Cake uploaded by admin"
                        loading="lazy"
                    >
                <?php else: ?>
                    <div class="category-image-placeholder">
                        No Wedding Cake image uploaded yet.
                    </div>
                <?php endif; ?>

                <div class="cake-info">
                    <h3>Wedding Cakes</h3>
                    <p>
                        Sweet beginnings deserve a beautiful cake for your big day.
                    </p>
                    <a href="<?php echo htmlspecialchars(categoryLoginLink("Wedding Cake")); ?>">
                        Explore Collection <span>→</span>
                    </a>
                </div>
            </div>

            <!-- Christening Cakes -->
            <div class="cake-card">
                <?php if (!empty($category_images["Christening Cake"])): ?>
                    <img
                        src="<?php echo htmlspecialchars($category_images["Christening Cake"]); ?>"
                        alt="Latest Christening Cake uploaded by admin"
                        loading="lazy"
                    >
                <?php else: ?>
                    <div class="category-image-placeholder">
                        No Christening Cake image uploaded yet.
                    </div>
                <?php endif; ?>

                <div class="cake-info">
                    <h3>Christening Cakes</h3>
                    <p>
                        Celebrate precious milestones with beautiful and sweet creations.
                    </p>
                    <a href="<?php echo htmlspecialchars(categoryLoginLink("Christening Cake")); ?>">
                        Explore Collection <span>→</span>
                    </a>
                </div>
            </div>

            <!-- Cupcakes -->
            <div class="cake-card">
                <?php if (!empty($category_images["Cupcakes"])): ?>
                    <img
                        src="<?php echo htmlspecialchars($category_images["Cupcakes"]); ?>"
                        alt="Latest Cupcakes uploaded by admin"
                        loading="lazy"
                    >
                <?php else: ?>
                    <div class="category-image-placeholder">
                        No Cupcakes image uploaded yet.
                    </div>
                <?php endif; ?>

                <div class="cake-info">
                    <h3>Cupcakes</h3>
                    <p>
                        Little treats made to bring sweetness to every occasion.
                    </p>
                    <a href="<?php echo htmlspecialchars(categoryLoginLink("Cupcakes")); ?>">
                        Explore Collection <span>→</span>
                    </a>
                </div>
            </div>

            <!-- Bento Cakes -->
            <div class="cake-card">
                <?php if (!empty($category_images["Bento Cake"])): ?>
                    <img
                        src="<?php echo htmlspecialchars($category_images["Bento Cake"]); ?>"
                        alt="Latest Bento Cake uploaded by admin"
                        loading="lazy"
                    >
                <?php else: ?>
                    <div class="category-image-placeholder">
                        No Bento Cake image uploaded yet.
                    </div>
                <?php endif; ?>

                <div class="cake-info">
                    <h3>Bento Cakes</h3>
                    <p>
                        Small cakes, sweet surprises, and cute personalized designs.
                    </p>
                    <a href="<?php echo htmlspecialchars(categoryLoginLink("Bento Cake")); ?>">
                        Explore Collection <span>→</span>
                    </a>
                </div>
            </div>

            <!-- All-Occasion Cakes -->
            <div class="cake-card">
                <?php if (!empty($category_images["All-Occasion Cakes"])): ?>
                    <img
                        src="<?php echo htmlspecialchars($category_images["All-Occasion Cakes"]); ?>"
                        alt="Latest All-Occasion Cake uploaded by admin"
                        loading="lazy"
                    >
                <?php else: ?>
                    <div class="category-image-placeholder">
                        No All-Occasion Cake image uploaded yet.
                    </div>
                <?php endif; ?>

                <div class="cake-info">
                    <h3>All-Occasion Cakes</h3>
                    <p>
                        Delicious cakes perfect for any celebration or gathering.
                    </p>
                    <a href="<?php echo htmlspecialchars(categoryLoginLink("All-Occasion Cakes")); ?>">
                        Explore Collection <span>→</span>
                    </a>
                </div>
            </div>

        </div>

    </section>

    <!-- How to Order -->
    <section class="how-section">

        <div class="section-heading">

            <p>SIMPLE AND EASY</p>

            <h2>How to Order</h2>

            <span>
                Ordering your dream cake is easy with CakeCraft.
                Just follow these three simple steps.
            </span>

        </div>

        <div class="steps-container">

            <!-- Step 1 -->
            <div class="step">
                <h3>01</h3>
                <h4>Choose Your Cake</h4>
                <p>
                    Browse our available cake selections and choose
                    the perfect cake for your special occasion.
                </p>
            </div>

            <!-- Step 2 -->
            <div class="step step-custom">
                <h3>02</h3>
                <h4>Customize Your Cake</h4>
                <p>
                    Select your preferred size, flavor, color,
                    and personalized message.
                </p>

                <div class="step-design-note">
                    <strong>Have a design in mind?</strong>
                    <p>
                        You can upload an inspiration photo of the cake
                        you want us to create. Share your preferred design,
                        details, and ideas for a personalized cake.
                    </p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="step">
                <h3>03</h3>
                <h4>Place Your Order</h4>
                <p>
                    Submit your order or custom cake request
                    and wait for confirmation from CakeCraft.
                </p>
            </div>

        </div>

    </section>

    <!-- About Us -->
    <section class="about-section" id="about">

        <div>
            <p class="subtitle">
                ABOUT CAKECRAFT
            </p>

            <h2>
                Every Cake Tells a Story.
            </h2>

            <p>
                At CakeCraft, we believe that every celebration deserves
                something special. We make cake ordering easier by
                allowing customers to choose their preferred cake options
                and personalize their orders online.
            </p>
        </div>

    </section>

    <!-- Footer / Contact -->
    <footer id="contact">

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
            <h2 class="brand-wordmark">Cake<span class="brand-craft">Craft.</span></h2>
        </div>

        <p>
            Making every celebration sweeter.
        </p>

        <div class="contact-details">

            <h3>Contact Us</h3>

            <?php if ($admin): ?>

                <!-- CONTACT PERSON -->
                <p>
                    <span class="contact-icon" aria-hidden="true">♙</span>
                    <span>
                        <strong>Contact Person:</strong>
                        <?php echo htmlspecialchars($admin["contact_name"]); ?>
                    </span>
                </p>

                <!-- EMAIL -->
                <p>
                    <span class="contact-icon" aria-hidden="true">✉</span>
                    <span>
                        <strong>Email:</strong>
                        <a href="mailto:<?php echo htmlspecialchars($admin["email"]); ?>">
                            <?php echo htmlspecialchars($admin["email"]); ?>
                        </a>
                    </span>
                </p>

                <!-- CONTACT NUMBER -->
                <?php if (!empty($admin["contact_number"])): ?>
                    <p>
                        <span class="contact-icon" aria-hidden="true">☎</span>
                        <span>
                            <strong>Contact Number:</strong>
                            <a href="tel:<?php echo htmlspecialchars($admin["contact_number"]); ?>">
                                <?php echo htmlspecialchars($admin["contact_number"]); ?>
                            </a>
                        </span>
                    </p>
                <?php endif; ?>

                <!-- LOCATION -->
                <?php if (!empty($admin["location"])): ?>
                    <p class="contact-location">
                        <span class="location-icon" aria-hidden="true">📍</span>
                        <span class="location-content">
                            <strong>Location:</strong>
                            <?php echo htmlspecialchars($admin["location"]); ?>
                            <br>
                            <a
                                class="maps-link"
                                href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($admin["location"]); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                View on Google Maps →
                            </a>
                        </span>
                    </p>
                <?php endif; ?>

            <?php else: ?>

                <p>
                    Contact details are currently unavailable.
                </p>

            <?php endif; ?>

        </div>

        <p class="footer-copyright">
            &copy; <?php echo date("Y"); ?> CakeCraft. All Rights Reserved.
        </p>

    </footer>

    <!-- JavaScript -->
    <script src="assets/js/script.js"></script>

</body>
</html>
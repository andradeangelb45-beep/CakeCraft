
<?php
require_once "database.php";

// Get updated admin contact details from site_contact table
$admin_query = $conn->query(
    "SELECT contact_name, email, contact_number
     FROM site_contact
     WHERE id = 1
     LIMIT 1"
);

$admin = $admin_query ? $admin_query->fetch_assoc() : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CakeCraft | Custom Cakes Made With Love</title>

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Navigation Bar -->
    <header>
        <nav class="navbar">

            <div class="logo">
                Cake<span>Craft.</span>
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
                src="https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=900"
                alt="Beautiful decorated birthday cake"
            >

        </div>

    </section>


    <!-- Featured Cakes -->
    <section class="cakes-section" id="cakes">

        <div class="section-heading">

            <p>OUR COLLECTION</p>

            <h2>Explore Our Cakes</h2>

            <span>
                Discover delicious cakes for every special occasion.
            </span>

        </div>

        <div class="cake-container">

            <!-- Chocolate Cake -->
            <div class="cake-card">

                <img
                    src="https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=600"
                    alt="Chocolate cake"
                >

                <div class="cake-info">

                    <h3>Chocolate Cake</h3>

                    <p>
                        Rich and delicious chocolate goodness.
                    </p>

                    <a href="customer/login.php">
                        Customize Cake
                    </a>

                </div>

            </div>


            <!-- Strawberry Cake -->
            <div class="cake-card">

                <img
                    src="https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600"
                    alt="Strawberry cake"
                >

                <div class="cake-info">

                    <h3>Strawberry Cake</h3>

                    <p>
                        Sweet strawberry flavor in every bite.
                    </p>

                    <a href="customer/login.php">
                        Customize Cake
                    </a>

                </div>

            </div>


            <!-- Vanilla Cake -->
            <div class="cake-card">

                <img
                    src="https://images.unsplash.com/photo-1571115177098-24ec42ed204d?w=600"
                    alt="Vanilla cake"
                >

                <div class="cake-info">

                    <h3>Vanilla Cake</h3>

                    <p>
                        Classic, soft, and perfectly creamy.
                    </p>

                    <a href="customer/login.php">
                        Customize Cake
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


    <!-- Footer / Contact Section -->
    <footer id="contact">

        <h2>CakeCraft.</h2>

        <p>
            Making every celebration sweeter.
        </p>

        <div class="contact-details">

            <h3>Contact Us</h3>

            <?php if ($admin): ?>

                <!-- Contact Person -->
                <p>
                    <strong>Contact Person:</strong>
                    <?php echo htmlspecialchars($admin["contact_name"]); ?>
                </p>

                <!-- Contact Email -->
                <p>
                    <strong>Email:</strong>

                    <a href="mailto:<?php echo htmlspecialchars($admin["email"]); ?>">
                        <?php echo htmlspecialchars($admin["email"]); ?>
                    </a>
                </p>

                <!-- Contact Number -->
                <?php if (!empty($admin["contact_number"])): ?>

                    <p>
                        <strong>Contact Number:</strong>

                        <a href="tel:<?php echo htmlspecialchars($admin["contact_number"]); ?>">
                            <?php echo htmlspecialchars($admin["contact_number"]); ?>
                        </a>
                    </p>

                <?php endif; ?>

            <?php else: ?>

                <p>
                    Contact details are currently unavailable.
                </p>

            <?php endif; ?>

        </div>

        <p>
            &copy; <?php echo date("Y"); ?> CakeCraft. All Rights Reserved.
        </p>

    </footer>


    <!-- JavaScript -->
    <script src="assets/js/script.js"></script>

</body>
</html>

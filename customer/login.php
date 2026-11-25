<?php
session_start();
require_once "../database.php";

$error = "";
$email = "";

// Allowed cake categories
$allowed_categories = [
    "Birthday Cake",
    "Wedding Cake",
    "Christening Cake",
    "Cupcakes",
    "Bento Cake",
    "All-Occasion Cakes"
];

// ==========================================
// STORE INTENDED REDIRECT
// ==========================================

if (isset($_GET["redirect"]) && $_GET["redirect"] === "cakes.php") {

    $_SESSION["login_redirect"] = "cakes.php";

    if (
        isset($_GET["category"]) &&
        in_array($_GET["category"], $allowed_categories, true)
    ) {
        $_SESSION["login_category"] = $_GET["category"];
    } else {
        unset($_SESSION["login_category"]);
    }
}

// ==========================================
// LOGIN PROCESS
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        // ==========================================
        // CHECK ADMIN ACCOUNT FIRST
        // ==========================================

        $stmt = $conn->prepare(
            "SELECT id, full_name, email, password
             FROM admins
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $admin = $result->fetch_assoc();

            if (password_verify($password, $admin["password"])) {

                session_regenerate_id(true);

                // Clear customer session
                unset(
                    $_SESSION["customer_id"],
                    $_SESSION["customer_name"],
                    $_SESSION["customer_email"]
                );

                // Admin session
                $_SESSION["admin_id"] = $admin["id"];
                $_SESSION["admin_name"] = $admin["full_name"];
                $_SESSION["admin_email"] = $admin["email"];

                // Admin always goes to dashboard
                unset(
                    $_SESSION["login_redirect"],
                    $_SESSION["login_category"]
                );

                $stmt->close();

                header("Location: ../admin/dashboard.php");
                exit();
            }
        }

        $stmt->close();


        // ==========================================
        // CHECK CUSTOMER ACCOUNT
        // ==========================================

        $stmt = $conn->prepare(
            "SELECT id, full_name, email, password
             FROM customers
             WHERE email = ?"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $customer = $result->fetch_assoc();

            if (password_verify($password, $customer["password"])) {

                session_regenerate_id(true);

                // Clear admin session
                unset(
                    $_SESSION["admin_id"],
                    $_SESSION["admin_name"],
                    $_SESSION["admin_email"]
                );

                // Customer session
                $_SESSION["customer_id"] = $customer["id"];
                $_SESSION["customer_name"] = $customer["full_name"];
                $_SESSION["customer_email"] = $customer["email"];

                $stmt->close();

                // ==========================================
                // REDIRECT TO SELECTED CAKE CATEGORY
                // ==========================================

                if (
                    isset($_SESSION["login_redirect"]) &&
                    $_SESSION["login_redirect"] === "cakes.php"
                ) {

                    $destination = "cakes.php";

                    if (
                        isset($_SESSION["login_category"]) &&
                        in_array(
                            $_SESSION["login_category"],
                            $allowed_categories,
                            true
                        )
                    ) {

                        $destination .= "?category=" .
                            urlencode($_SESSION["login_category"]);
                    }

                    // Clear redirect information
                    unset(
                        $_SESSION["login_redirect"],
                        $_SESSION["login_category"]
                    );

                    header("Location: " . $destination);
                    exit();
                }

                // Default customer login destination
                header("Location: dashboard.php");
                exit();
            }
        }

        $stmt->close();

        // ==========================================
        // INVALID LOGIN
        // ==========================================

        $error = "Incorrect email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | CakeCraft</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700;800&display=swap"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="../assets/css/brand-system.css">

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
            --craft-color: #a65a56;
            --line: #e6d3cc;
            --error-bg: #fdecea;
            --error-ink: #9b1c1c;

            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;

            --ease: cubic-bezier(.2, .7, .2, 1);
        }

        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body);
            line-height: 1.6;
            color: var(--ink);
            background: var(--bg);

            -webkit-font-smoothing: antialiased;

            display: flex;
            justify-content: center;
            align-items: center;

            min-height: 100vh;
            padding: 24px;

            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;

            top: -20vmin;
            right: -15vmin;

            width: 60vmin;
            height: 60vmin;

            border-radius: 50%;
            background: var(--bg-soft);

            z-index: -1;
        }

        :focus-visible {
            outline: 3px solid var(--rose);
            outline-offset: 3px;
            border-radius: 4px;
        }

        /* Login Container */

        .login-container {
            background: var(--surface);

            width: 100%;
            max-width: 440px;

            padding: 44px 40px 36px;

            border: 1px solid var(--line);
            border-radius: 18px;

            box-shadow: 0 14px 40px rgba(73, 48, 45, 0.10);
        }

        /* Brand */

        .brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .brand {
            display: flex;
            justify-content: center;

            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;

            letter-spacing: -0.01em;

            color: var(--ink);
            text-decoration: none;

            margin-bottom: 26px;
        }

        .brand span.brand-craft {
            color: var(--craft-color);
        }

        /* Heading */

        h1 {
            text-align: center;

            font-family: var(--font-display);
            font-size: 30px;
            font-weight: 600;

            line-height: 1.2;

            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: var(--ink-soft);

            margin-bottom: 28px;
        }

        /* Form */

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;

            margin-bottom: 7px;

            font-size: 14px;
            font-weight: 600;
        }

        input {
            width: 100%;

            padding: 13px 14px;

            font-family: inherit;
            font-size: 16px;

            color: var(--ink);
            background: var(--bg);

            border: 1.5px solid var(--line);
            border-radius: 10px;

            transition:
                border-color .2s var(--ease),
                box-shadow .2s var(--ease),
                background .2s var(--ease);
        }

        input::placeholder {
            color: #a8978f;
        }

        input:hover {
            border-color: #d4bdb4;
        }

        input:focus {
            outline: none;

            background: #fff;
            border-color: var(--rose-deep);

            box-shadow: 0 0 0 4px rgba(196, 124, 120, 0.22);
        }

        /* Login Button */

        button {
            width: 100%;

            margin-top: 4px;
            padding: 14px;

            font-family: inherit;
            font-size: 16px;
            font-weight: 600;

            color: #fff;
            background: var(--rose-deep);

            border: none;
            border-radius: 999px;

            cursor: pointer;

            box-shadow: 0 8px 20px rgba(166, 90, 86, 0.28);

            transition:
                background .2s var(--ease),
                transform .2s var(--ease),
                box-shadow .2s var(--ease);
        }

        button:hover {
            background: var(--rose-darker);

            transform: translateY(-1px);

            box-shadow: 0 12px 26px rgba(166, 90, 86, 0.34);
        }

        button:active {
            transform: translateY(0);
        }

        /* Error Message */

        .error {
            display: flex;
            gap: 10px;
            align-items: flex-start;

            background: var(--error-bg);
            color: var(--error-ink);

            border: 1px solid #f3c7c2;
            border-left: 4px solid var(--error-ink);

            padding: 12px 14px;
            margin-bottom: 22px;

            border-radius: 10px;

            font-size: 14px;
            font-weight: 500;
        }

        .error svg {
            flex: none;

            width: 18px;
            height: 18px;

            margin-top: 2px;
        }

        /* Register Link */

        .register-link {
            text-align: center;

            margin-top: 26px;
            padding-top: 22px;

            border-top: 1px solid var(--line);

            color: var(--ink-soft);
            font-size: 15px;
        }

        .register-link a,
        .back-link {
            color: var(--rose-deep);
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover,
        .back-link:hover {
            color: var(--rose-darker);

            text-decoration: underline;
            text-underline-offset: 3px;
        }

        /* Back to Home */

        .back-link {
            display: block;
            text-align: center;

            margin-top: 16px;

            font-size: 14px;
        }

        /* Mobile */

        @media (max-width: 480px) {

            .login-container {
                padding: 36px 24px 28px;
            }

        }

        /* Reduced Motion */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
            }

        }

    </style>

</head>

<body>

    <div class="login-container">

        <!-- Brand -->
        <div class="brand">
            <a href="../index.php" class="brand-mark" aria-label="CakeCraft Home">
                <span class="brand-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3v3" />
                        <path d="M12 6c-1.1 0-2 .7-2 1.6 0 .5.2.9.5 1.4" />
                        <path d="M5 12h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" />
                        <path d="M3 16c1.5 0 2.5-1 4-1s2.5 1 4 1 2.5-1 4-1 2.5 1 4 1 2-.5 2-1" />
                        <path d="M3 19h18" />
                    </svg>
                </span>
                <span>Cake<span class="brand-craft">Craft</span></span>
            </a>
        </div>

        <!-- Heading -->
        <h1>Welcome back</h1>

        <p class="subtitle">
            Log in to order and track your cakes.
        </p>


        <!-- Error Message -->
        <?php if (!empty($error)): ?>

            <div class="error" role="alert">

                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >

                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7.5v5M12 16h.01"/>

                </svg>

                <span>
                    <?php echo htmlspecialchars($error); ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- Login Form -->
        <form method="POST" action="">

            <div class="form-group">

                <label for="email">
                    Email address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@example.com"
                    autocomplete="email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                    <?php echo empty($error) ? "autofocus" : ""; ?>
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                    <?php echo !empty($error) ? "autofocus" : ""; ?>
                >

            </div>


            <button type="submit">
                Log in
            </button>

        </form>


        <!-- Register Link -->
        <p class="register-link">

            Don't have an account?

            <a href="register.php">
                Register here
            </a>

        </p>


        <!-- Back to Home -->
        <a href="../index.php" class="back-link">
            Back to home
        </a>

    </div>

</body>
</html>
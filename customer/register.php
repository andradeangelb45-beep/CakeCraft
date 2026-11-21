<?php
session_start();
require_once "../database.php";

$full_name = "";
$email = "";
$phone = "";
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get form data
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // Check if fields are empty
    if (empty($full_name) || empty($email) ||
        empty($phone) || empty($password) ||
        empty($confirm_password)) {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        // Check if email already exists
        $check = $conn->prepare(
            "SELECT id FROM customers WHERE email = ?"
        );

        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $error = "Email address is already registered.";

        } else {

            // Secure password
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Insert customer into database
            $stmt = $conn->prepare(
                "INSERT INTO customers
                (full_name, email, password, phone)
                VALUES (?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssss",
                $full_name,
                $email,
                $hashed_password,
                $phone
            );

            if ($stmt->execute()) {

                $success = "Registration successful! You can now log in.";

                // Clear form fields
                $full_name = "";
                $email = "";
                $phone = "";

            } else {

                $error = "Something went wrong. Please try again.";

            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Registration | CakeCraft</title>

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
            --line: #e6d3cc;
            --error-bg: #fdecea;
            --error-ink: #9b1c1c;
            --ok-bg: #e8f6ec;
            --ok-ink: #17602f;
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;
            --ease: cubic-bezier(.2, .7, .2, 1);
        }

        * {
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
            overflow-x: hidden;
        }

        /* soft blush shape behind the card */
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

        .register-container {
            background: var(--surface);
            width: 100%;
            max-width: 480px;
            padding: 44px 40px 36px;
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 14px 40px rgba(73, 48, 45, 0.10);
        }

        .brand {
            display: block;
            text-align: center;
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--ink);
            text-decoration: none;
            margin-bottom: 26px;
        }

        .brand span {
            color: var(--rose);
        }

        .register-container h1 {
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

        .form-group {
            margin-bottom: 18px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: 600;
        }

        .hint {
            margin-top: 6px;
            font-size: 13px;
            color: var(--ink-soft);
        }

        input {
            width: 100%;
            padding: 13px 14px;
            font-family: inherit;
            font-size: 16px; /* 16px stops iOS zooming on focus */
            color: var(--ink);
            background: var(--bg);
            border: 1.5px solid var(--line);
            border-radius: 10px;
            transition: border-color .2s var(--ease), box-shadow .2s var(--ease), background .2s var(--ease);
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

        button,
        .btn {
            display: block;
            width: 100%;
            margin-top: 6px;
            padding: 14px;
            font-family: inherit;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            color: #fff;
            background: var(--rose-deep);
            border: none;
            border-radius: 999px;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(166, 90, 86, 0.28);
            transition: background .2s var(--ease), transform .2s var(--ease), box-shadow .2s var(--ease);
        }

        button:hover,
        .btn:hover {
            background: var(--rose-darker);
            transform: translateY(-1px);
            box-shadow: 0 12px 26px rgba(166, 90, 86, 0.34);
        }

        button:active,
        .btn:active {
            transform: translateY(0);
        }

        .message {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 12px 14px;
            margin-bottom: 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
        }

        .message svg {
            flex: none;
            width: 18px;
            height: 18px;
            margin-top: 2px;
        }

        .error {
            background: var(--error-bg);
            color: var(--error-ink);
            border: 1px solid #f3c7c2;
            border-left: 4px solid var(--error-ink);
        }

        .success {
            background: var(--ok-bg);
            color: var(--ok-ink);
            border: 1px solid #bfe3cb;
            border-left: 4px solid var(--ok-ink);
        }

        .login-link {
            text-align: center;
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid var(--line);
            color: var(--ink-soft);
            font-size: 15px;
        }

        .login-link a,
        .back-link {
            color: var(--rose-deep);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover,
        .back-link:hover {
            color: var(--rose-darker);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 16px;
            font-size: 14px;
        }

        @media (max-width: 520px) {
            .register-container {
                padding: 36px 24px 28px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>

<body>

    <div class="register-container">

        <a href="../index.php" class="brand">Cake<span>Craft</span></a>

        <h1>Create your account</h1>

        <p class="subtitle">Order, customize, and track your cakes in one place.</p>

        <?php if (!empty($error)): ?>
            <div class="message error" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M12 7.5v5M12 16h.01"/>
                </svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="message success" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M8 12.5l2.6 2.6L16 9.5"/>
                </svg>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>

            <a href="login.php" class="btn">Go to login</a>
        <?php else: ?>

        <form method="POST" action="">

            <div class="form-group">
                <label for="full_name">Full name</label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?php echo htmlspecialchars($full_name); ?>"
                    placeholder="Enter your full name"
                    autocomplete="name"
                    required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php echo htmlspecialchars($email); ?>"
                        placeholder="you@example.com"
                        autocomplete="email"
                        required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone number</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        value="<?php echo htmlspecialchars($phone); ?>"
                        placeholder="Enter your phone number"
                        autocomplete="tel"
                        required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="At least 8 characters"
                    minlength="8"
                    autocomplete="new-password"
                    aria-describedby="password-hint"
                    required>
                <p class="hint" id="password-hint">Use at least 8 characters.</p>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Re-enter your password"
                    autocomplete="new-password"
                    required>
            </div>

            <button type="submit">Create account</button>

        </form>

        <?php endif; ?>

        <p class="login-link">
            Already have an account?
            <a href="login.php">Log in here</a>
        </p>

        <a href="../index.php" class="back-link">
            Back to home
        </a>

    </div>

</body>
</html>
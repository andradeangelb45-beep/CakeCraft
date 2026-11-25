<?php
session_start();
require_once "../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

$result = $conn->query("SELECT * FROM site_contact WHERE id = 1");
$contact = $result ? $result->fetch_assoc() : null;

// Initialize contact settings if not yet created
if (!$contact) {
    $default_name = $_SESSION["admin_name"] ?? "CakeCraft Admin";
    $default_email = $_SESSION["admin_email"] ?? "";

    $stmt = $conn->prepare(
        "INSERT INTO site_contact
         (id, contact_name, email, contact_number, location)
         VALUES (1, ?, ?, '', '')"
    );

    $stmt->bind_param("ss", $default_name, $default_email);
    $stmt->execute();
    $stmt->close();

    $result = $conn->query("SELECT * FROM site_contact WHERE id = 1");
    $contact = $result ? $result->fetch_assoc() : null;
}

// Save contact information
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $contact_name = trim($_POST["contact_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $contact_number = trim($_POST["contact_number"] ?? "");
    $location = trim($_POST["location"] ?? "");

    if ($contact_name === "" || $email === "") {

        $message = "Please complete the name and email address.";
        $message_type = "error";

        // Keep entered values
        $contact["contact_name"] = $contact_name;
        $contact["email"] = $email;
        $contact["contact_number"] = $contact_number;
        $contact["location"] = $location;

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

        // Keep entered values
        $contact["contact_name"] = $contact_name;
        $contact["email"] = $email;
        $contact["contact_number"] = $contact_number;
        $contact["location"] = $location;

    } else {

        $stmt = $conn->prepare(
            "UPDATE site_contact
             SET contact_name = ?,
                 email = ?,
                 contact_number = ?,
                 location = ?
             WHERE id = 1"
        );

        $stmt->bind_param(
            "ssss",
            $contact_name,
            $email,
            $contact_number,
            $location
        );

        if ($stmt->execute()) {

            $message = "Contact details updated successfully!";
            $message_type = "success";

        } else {

            $message = "Unable to update contact details.";
            $message_type = "error";
        }

        $stmt->close();

        // Reload updated contact settings
        $result = $conn->query("SELECT * FROM site_contact WHERE id = 1");
        $contact = $result ? $result->fetch_assoc() : null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Admin Contact | CakeCraft</title>

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
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;
            --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
            --shadow-lift: 0 14px 34px rgba(73, 48, 45, 0.14);
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg);
            color: var(--ink);
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        :focus-visible {
            outline: 3px solid var(--rose);
            outline-offset: 3px;
            border-radius: 4px;
        }

        /* NAVIGATION — aligned with the CakeCraft homepage */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 50;
            width: 100%;
            background: rgba(255, 250, 247, 0.96);
            border-bottom: 1px solid var(--line);
            box-shadow: 0 2px 14px rgba(59, 39, 36, 0.05);
            backdrop-filter: blur(12px);
        }

        .nav-container {
            width: 100%;
            max-width: 1180px;
            min-height: 76px;
            margin: 0 auto;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
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
            color: var(--ink);
            text-decoration: none;
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.01em;
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

        .brand-mark span.brand-craft {
            color: var(--craft-color);
        }

        .admin-badge {
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--bg-soft);
            border: 1px solid var(--line);
            color: var(--rose-deep);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-dashboard {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 22px;
            border: 1.5px solid var(--rose);
            border-radius: 999px;
            background: transparent;
            color: var(--ink);
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: color .2s var(--ease), background .2s var(--ease), border-color .2s var(--ease), transform .2s var(--ease);
        }

        .back-dashboard:hover {
            background: var(--bg-soft);
            border-color: var(--rose-deep);
            color: var(--rose-deep);
            transform: translateY(-1px);
        }

        /* PAGE */
        .container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            padding: 54px 24px 72px;
            flex: 1;
        }

        .page-heading {
            margin-bottom: 30px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            color: var(--rose-deep);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 28px;
            height: 2px;
            border-radius: 2px;
            background: var(--rose-deep);
        }

        .page-heading h1 {
            color: var(--ink);
            font-family: var(--font-display);
            font-size: clamp(2rem, 4.4vw, 2.6rem);
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.01em;
            margin-bottom: 10px;
        }

        .page-heading p {
            color: var(--ink-soft);
            font-size: 16px;
            line-height: 1.7;
        }

        /* FORM CARD */
        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 24px 30px;
            background: var(--bg);
            border-bottom: 1px solid var(--line);
        }

        .card-icon {
            width: 46px;
            height: 46px;
            flex: none;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: var(--bg-soft);
            border: 1px solid var(--line);
            color: var(--rose-deep);
        }

        .card-icon svg {
            width: 23px;
            height: 23px;
        }

        .card-heading h2 {
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 21px;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 3px;
        }

        .card-heading p {
            color: var(--ink-soft);
            font-size: 13px;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 23px;
        }

        .form-group:last-of-type {
            margin-bottom: 0;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
        }

        input {
            display: block;
            width: 100%;
            min-height: 48px;
            padding: 12px 15px;
            border: 1.5px solid var(--line);
            border-radius: 10px;
            background: var(--bg);
            color: var(--ink);
            font-family: var(--font-body);
            font-size: 15px;
            transition: border-color .2s var(--ease), box-shadow .2s var(--ease), background .2s var(--ease);
        }

        input::placeholder {
            color: var(--ink-soft);
            opacity: 0.7;
        }

        input:focus {
            outline: none;
            border-color: var(--rose-deep);
            box-shadow: 0 0 0 3px rgba(196, 124, 120, .16);
            background: var(--surface);
        }

        .help {
            margin-top: 7px;
            color: var(--ink-soft);
            font-size: 13px;
            line-height: 1.5;
        }

        /* FEEDBACK */
        .message {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 24px;
            padding: 14px 17px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
        }

        .message.success {
            background: #edf5ed;
            color: #426c4e;
            border: 1px solid #d7e8d8;
            border-left: 4px solid #426c4e;
        }

        .message.error {
            background: #f9e9e7;
            color: #963f3b;
            border: 1px solid #efd1cd;
            border-left: 4px solid #963f3b;
        }

        /* ACTIONS */
        .buttons {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 30px;
            padding-top: 24px;
            border-top: 1px solid var(--line);
        }

        .save-button,
        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 12px 25px;
            border: 1.5px solid transparent;
            border-radius: 999px;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s var(--ease), color .2s var(--ease), border-color .2s var(--ease), transform .2s var(--ease), box-shadow .2s var(--ease);
        }

        .save-button {
            background: var(--rose-deep);
            color: #fff;
            box-shadow: 0 7px 18px rgba(166, 90, 86, .18);
        }

        .save-button:hover {
            background: var(--rose-darker);
            box-shadow: 0 10px 22px rgba(140, 72, 69, .22);
            transform: translateY(-1px);
        }

        .back-button {
            background: var(--bg-soft);
            border-color: var(--line);
            color: var(--ink);
        }

        .back-button:hover {
            background: var(--bg-tint);
            border-color: #dfc2bc;
            color: var(--rose-darker);
        }

        /* FOOTER */
        footer {
            margin-top: auto;
            padding: 24px;
            border-top: 1px solid var(--line);
            background: var(--bg-soft);
            color: var(--ink-soft);
            font-size: 13px;
            text-align: center;
        }

        @media (max-width: 650px) {
            .nav-container {
                min-height: auto;
                padding: 15px 6%;
                flex-direction: column;
                gap: 12px;
            }

            .brand-mark {
                font-size: 26px;
            }

            .back-dashboard {
                padding: 9px 18px;
            }

            .container {
                padding: 38px 18px 55px;
            }

            .page-heading h1 {
                font-size: 2rem;
            }

            .page-heading p {
                font-size: 15px;
            }

            .card-header {
                padding: 20px;
            }

            .card-body {
                padding: 22px 20px;
            }
        }

        @media (max-width: 480px) {
            .buttons {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .save-button,
            .back-button {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="nav-container">
        <div class="brand-wrap">
            <a href="dashboard.php" class="brand-mark" aria-label="CakeCraft Admin Dashboard">
                <span class="brand-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3v3" />
                        <path d="M12 6c-1.1 0-2 .7-2 1.6 0 .5.2.9.5 1.4" />
                        <path d="M5 12h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" />
                        <path d="M3 16c1.5 0 2.5-1 4-1s2.5 1 4 1 2.5-1 4-1 2.5 1 4 1 2-.5 2-1" />
                        <path d="M3 19h18" />
                    </svg>
                </span>
                Cake<span class="brand-craft">Craft</span>
            </a>
            <span class="admin-badge">ADMIN</span>
        </div>

        <div class="header-actions">
            <a href="dashboard.php" class="back-dashboard">← Back to Dashboard</a>
        </div>
    </div>
</header>

<main class="container">
    <div class="page-heading">
        <div class="eyebrow">Website Information</div>
        <h1>Manage Admin Contact</h1>
        <p>Update the contact information displayed on the CakeCraft homepage.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>" role="<?php echo $message_type === "success" ? "status" : "alert"; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <div class="card-header">
            <div class="card-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="3.5"/>
                    <path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>
                </svg>
            </div>
            <div class="card-heading">
                <h2>Contact Information</h2>
                <p>These details are visible to customers on the homepage.</p>
            </div>
        </div>

        <div class="card-body">
            <form method="POST">
                <!-- CONTACT PERSON -->
                <div class="form-group">
                    <label for="contact_name">Contact Person Name</label>
                    <input
                        type="text"
                        id="contact_name"
                        name="contact_name"
                        value="<?php echo htmlspecialchars($contact["contact_name"] ?? ""); ?>"
                        maxlength="150"
                        placeholder="Enter contact person's name"
                        required
                    >
                    <p class="help">Name shown to customers on the homepage.</p>
                </div>

                <!-- EMAIL -->
                <div class="form-group">
                    <label for="email">Contact Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php echo htmlspecialchars($contact["email"] ?? ""); ?>"
                        maxlength="150"
                        placeholder="Enter contact email address"
                        required
                    >
                    <p class="help">Customers can click this email to contact you.</p>
                </div>

                <!-- CONTACT NUMBER -->
                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input
                        type="text"
                        id="contact_number"
                        name="contact_number"
                        value="<?php echo htmlspecialchars($contact["contact_number"] ?? ""); ?>"
                        maxlength="20"
                        placeholder="e.g. 09XXXXXXXXX"
                    >
                    <p class="help">Optional. Customers can click this number to call.</p>
                </div>

                <!-- BUSINESS LOCATION -->
                <div class="form-group">
                    <label for="location">Business Location</label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        value="<?php echo htmlspecialchars($contact["location"] ?? ""); ?>"
                        maxlength="255"
                        placeholder="e.g. Poblacion, Lopez, Quezon"
                    >
                    <p class="help">Enter the CakeCraft business address or location displayed on the homepage.</p>
                </div>

                <div class="buttons">
                    <a href="dashboard.php" class="back-button">Cancel</a>
                    <button type="submit" class="save-button">Save Changes</button>
                </div>
            </form>
        </div>
    </section>
</main>

<footer>
    &copy; <?php echo date("Y"); ?> CakeCraft. All rights reserved.
</footer>

</body>
</html>
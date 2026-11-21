
<?php
session_start();
require_once "../database.php";

// Check if customer is logged in
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

$customer_id = (int) $_SESSION["customer_id"];

$message = "";
$message_type = "";

if (!isset($_SESSION["profile_token"])) {
    $_SESSION["profile_token"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["profile_token"];

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CSRF validation
    if (
        !isset($_POST["profile_token"]) ||
        !hash_equals($_SESSION["profile_token"], $_POST["profile_token"])
    ) {
        $message = "Invalid request. Please try again.";
        $message_type = "error";

    } else {

        $action = $_POST["action"] ?? "";

        // UPDATE PERSONAL INFORMATION
        if ($action === "update_profile") {

            $full_name = trim($_POST["full_name"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $phone = trim($_POST["phone"] ?? "");

            if (empty($full_name) || empty($email) || empty($phone)) {

                $message = "Please fill in all personal information fields.";
                $message_type = "error";

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $message = "Please enter a valid email address.";
                $message_type = "error";

            } else {

                // Check if email is already used by another customer
                $check_email = $conn->prepare(
                    "SELECT id FROM customers WHERE email = ? AND id != ?"
                );

                $check_email->bind_param("si", $email, $customer_id);
                $check_email->execute();

                $email_result = $check_email->get_result();

                if ($email_result->num_rows > 0) {

                    $message = "This email address is already being used.";
                    $message_type = "error";

                } else {

                    // Update customer information
                    $update = $conn->prepare(
                        "UPDATE customers
                         SET full_name = ?, email = ?, phone = ?
                         WHERE id = ?"
                    );

                    $update->bind_param(
                        "sssi",
                        $full_name,
                        $email,
                        $phone,
                        $customer_id
                    );

                    if ($update->execute()) {

                        $_SESSION["customer_name"] = $full_name;
                        $_SESSION["customer_email"] = $email;

                        $message = "Your personal information has been updated successfully.";
                        $message_type = "success";

                    } else {

                        $message = "Unable to update your information. Please try again.";
                        $message_type = "error";
                    }

                    $update->close();
                }

                $check_email->close();
            }
        }

        // CHANGE PASSWORD
        elseif ($action === "change_password") {

            $current_password = $_POST["current_password"] ?? "";
            $new_password = $_POST["new_password"] ?? "";
            $confirm_password = $_POST["confirm_password"] ?? "";

            if (
                empty($current_password) ||
                empty($new_password) ||
                empty($confirm_password)
            ) {

                $message = "Please fill in all password fields.";
                $message_type = "error";

            } elseif (strlen($new_password) < 8) {

                $message = "Your new password must be at least 8 characters.";
                $message_type = "error";

            } elseif ($new_password !== $confirm_password) {

                $message = "The new password and confirmation do not match.";
                $message_type = "error";

            } else {

                // Get current password from database
                $password_query = $conn->prepare(
                    "SELECT password FROM customers WHERE id = ?"
                );

                $password_query->bind_param("i", $customer_id);
                $password_query->execute();

                $password_result = $password_query->get_result();
                $customer_password = $password_result->fetch_assoc();

                if (
                    !$customer_password ||
                    !password_verify(
                        $current_password,
                        $customer_password["password"]
                    )
                ) {

                    $message = "Your current password is incorrect.";
                    $message_type = "error";

                } else {

                    // Hash new password
                    $hashed_password = password_hash(
                        $new_password,
                        PASSWORD_DEFAULT
                    );

                    $password_update = $conn->prepare(
                        "UPDATE customers SET password = ? WHERE id = ?"
                    );

                    $password_update->bind_param(
                        "si",
                        $hashed_password,
                        $customer_id
                    );

                    if ($password_update->execute()) {

                        $message = "Your password has been changed successfully.";
                        $message_type = "success";

                    } else {

                        $message = "Unable to change password. Please try again.";
                        $message_type = "error";
                    }

                    $password_update->close();
                }

                $password_query->close();
            }
        }
    }
}

// Get latest customer information
$query = $conn->prepare(
    "SELECT full_name, email, phone, created_at
     FROM customers
     WHERE id = ?"
);

$query->bind_param("i", $customer_id);
$query->execute();

$result = $query->get_result();
$customer = $result->fetch_assoc();

$query->close();

if (!$customer) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Refresh session values
$_SESSION["customer_name"] = $customer["full_name"];
$_SESSION["customer_email"] = $customer["email"];

$customer_name = $customer["full_name"];
$customer_email = $customer["email"];
$customer_phone = $customer["phone"];

$initial = strtoupper(substr($customer_name, 0, 1));

$joined_date = !empty($customer["created_at"])
    ? date("F Y", strtotime($customer["created_at"]))
    : "Customer";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | CakeCraft</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #faf7f5;
            color: #49352f;
        }

        /* NAVBAR */

        .navbar {
            background: #fff;
            border-bottom: 1px solid #eee4df;
            padding: 0 7%;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1400px;
            margin: auto;
            height: 75px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 25px;
            font-weight: bold;
            color: #8b5146;
            text-decoration: none;
        }

        .logo span {
            color: #d69a91;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links a {
            text-decoration: none;
            color: #6b5750;
            font-size: 14px;
            padding: 11px 16px;
            border-radius: 8px;
            transition: 0.2s;
        }

        .nav-links a:hover {
            background: #f8eeeb;
            color: #92574b;
        }

        .nav-links a.active {
            background: #f5e6e1;
            color: #8b5146;
            font-weight: bold;
        }

        .nav-links a.logout {
            background: #8b5146;
            color: white;
            margin-left: 5px;
        }

        .nav-links a.logout:hover {
            background: #704037;
        }

        /* MAIN CONTAINER */

        .main-container {
            max-width: 1250px;
            margin: 35px auto;
            padding: 0 25px;
        }

        /* PAGE HEADING */

        .page-heading {
            margin-bottom: 28px;
        }

        .page-heading h1 {
            font-size: 28px;
            color: #49352f;
            margin-bottom: 8px;
        }

        .page-heading p {
            font-size: 14px;
            color: #88766f;
            line-height: 1.6;
        }

        /* NOTIFICATION */

        .message {
            padding: 15px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            line-height: 1.5;
        }

        .message.success {
            background: #eaf5ec;
            color: #2f6d3c;
            border: 1px solid #cce5d1;
        }

        .message.error {
            background: #fbeaea;
            color: #a33b3b;
            border: 1px solid #edcccc;
        }

        /* MAIN PROFILE LAYOUT */

        .profile-layout {
            display: grid;
            grid-template-columns: 290px 1fr;
            gap: 25px;
            align-items: start;
        }

        /* LEFT PROFILE SIDEBAR */

        .profile-sidebar {
            background: #fff;
            border: 1px solid #eee5e0;
            border-radius: 15px;
            padding: 30px 24px;
            text-align: center;
        }

        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 18px;
            background: #f2dcd5;
            color: #8b5146;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            font-weight: bold;
            border: 5px solid #fbf4f1;
        }

        .profile-sidebar h2 {
            font-size: 20px;
            color: #49352f;
            margin-bottom: 8px;
            word-break: break-word;
        }

        .profile-sidebar .email {
            font-size: 13px;
            color: #88766f;
            word-break: break-word;
            line-height: 1.6;
        }

        .profile-divider {
            height: 1px;
            background: #eee5e0;
            margin: 25px 0;
        }

        .profile-details {
            text-align: left;
        }

        .detail-item {
            margin-bottom: 20px;
        }

        .detail-item:last-child {
            margin-bottom: 0;
        }

        .detail-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #a18c84;
            margin-bottom: 7px;
        }

        .detail-value {
            font-size: 14px;
            color: #5b453d;
            line-height: 1.6;
            word-break: break-word;
        }

        /* RIGHT SIDE */

        .profile-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* CARDS */

        .content-card {
            background: white;
            border: 1px solid #eee5e0;
            border-radius: 15px;
            overflow: hidden;
        }

        .card-header {
            padding: 23px 27px;
            border-bottom: 1px solid #f0e8e4;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-icon {
            width: 43px;
            height: 43px;
            background: #f8e7e3;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
            flex-shrink: 0;
        }

        .header-icon.password-icon {
            background: #f4eadc;
        }

        .header-title h2 {
            font-size: 18px;
            color: #49352f;
            margin-bottom: 5px;
        }

        .header-title p {
            color: #96827a;
            font-size: 12px;
            line-height: 1.5;
        }

        .card-body {
            padding: 27px;
        }

        /* FORM */

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 13px;
            font-weight: bold;
            color: #614a42;
            margin-bottom: 9px;
        }

        .form-group input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #e6dcd6;
            border-radius: 9px;
            font-size: 14px;
            background: #fff;
            color: #49352f;
            outline: none;
            transition: 0.2s;
        }

        .form-group input:focus {
            border-color: #b77c6d;
            box-shadow: 0 0 0 3px rgba(183, 124, 109, 0.12);
        }

        .form-group input::placeholder {
            color: #b6a49c;
        }

        .form-note {
            font-size: 12px;
            color: #99867e;
            margin-top: 7px;
            line-height: 1.5;
        }

        /* BUTTONS */

        .form-footer {
            margin-top: 25px;
            padding-top: 22px;
            border-top: 1px solid #f0e8e4;
            display: flex;
            justify-content: flex-end;
        }

        .save-button {
            background: #8b5146;
            color: white;
            border: none;
            padding: 13px 22px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .save-button:hover {
            background: #704037;
        }

        /* PASSWORD SECTION */

        .password-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        /* BACK LINK - ONLY ONE */

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 28px;
            color: #92574b;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .back-link:hover {
            color: #704037;
        }

        /* FOOTER */

        footer {
            margin-top: 45px;
            padding: 25px;
            text-align: center;
            color: #9b8982;
            font-size: 13px;
            border-top: 1px solid #eee4df;
        }

        /* RESPONSIVE */

        @media (max-width: 950px) {

            .profile-layout {
                grid-template-columns: 250px 1fr;
                gap: 18px;
            }

            .card-header {
                padding: 20px;
            }

            .card-body {
                padding: 22px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .form-group.full-width {
                grid-column: auto;
            }
        }

        @media (max-width: 700px) {

            .navbar {
                padding: 0 15px;
            }

            .nav-container {
                height: auto;
                padding: 17px 0;
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
                gap: 4px;
            }

            .nav-links a {
                padding: 9px 11px;
                font-size: 12px;
            }

            .nav-links a.logout {
                margin-left: 0;
            }

            .main-container {
                margin: 25px auto;
                padding: 0 15px;
            }

            .page-heading h1 {
                font-size: 24px;
            }

            .profile-layout {
                grid-template-columns: 1fr;
            }

            .profile-sidebar {
                padding: 25px;
            }

            .profile-content {
                gap: 18px;
            }

            .card-header {
                padding: 18px;
            }

            .card-body {
                padding: 20px;
            }

            .header-title h2 {
                font-size: 16px;
            }

            .form-footer {
                margin-top: 20px;
            }

            .save-button {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<!-- NAVBAR -->
<header class="navbar">

    <div class="nav-container">

        <a href="dashboard.php" class="logo">
            Cake<span>Craft</span>
        </a>

        <nav class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="cakes.php">Cakes</a>
            <a href="my_orders.php">My Orders</a>
            <a href="profile.php" class="active">My Profile</a>
            <a href="logout.php" class="logout">Logout</a>
        </nav>

    </div>

</header>

<!-- MAIN CONTENT -->
<main class="main-container">

    <!-- PAGE HEADING -->
    <div class="page-heading">

        <h1>My Profile</h1>

        <p>
            Manage your personal information and account security.
        </p>

    </div>

    <!-- MESSAGE -->
    <?php if (!empty($message)): ?>

        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <!-- PROFILE LAYOUT -->
    <div class="profile-layout">

        <!-- LEFT SIDEBAR -->
        <aside class="profile-sidebar">

            <div class="avatar">
                <?php echo htmlspecialchars($initial); ?>
            </div>

            <h2>
                <?php echo htmlspecialchars($customer_name); ?>
            </h2>

            <p class="email">
                <?php echo htmlspecialchars($customer_email); ?>
            </p>

            <div class="profile-divider"></div>

            <div class="profile-details">

                <div class="detail-item">

                    <div class="detail-label">
                        Full Name
                    </div>

                    <div class="detail-value">
                        <?php echo htmlspecialchars($customer_name); ?>
                    </div>

                </div>

                <div class="detail-item">

                    <div class="detail-label">
                        Email Address
                    </div>

                    <div class="detail-value">
                        <?php echo htmlspecialchars($customer_email); ?>
                    </div>

                </div>

                <div class="detail-item">

                    <div class="detail-label">
                        Contact Number
                    </div>

                    <div class="detail-value">
                        <?php echo htmlspecialchars($customer_phone); ?>
                    </div>

                </div>

                <div class="detail-item">

                    <div class="detail-label">
                        Customer Since
                    </div>

                    <div class="detail-value">
                        <?php echo htmlspecialchars($joined_date); ?>
                    </div>

                </div>

            </div>

        </aside>

        <!-- RIGHT CONTENT -->
        <div class="profile-content">

            <!-- PERSONAL INFORMATION -->
            <section class="content-card">

                <div class="card-header">

                    <div class="header-icon">
                        👤
                    </div>

                    <div class="header-title">

                        <h2>Personal Information</h2>

                        <p>
                            Update your name, email, and contact number.
                        </p>

                    </div>

                </div>

                <div class="card-body">

                    <form method="POST" action="profile.php">

                        <input
                            type="hidden"
                            name="profile_token"
                            value="<?php echo htmlspecialchars($token); ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="update_profile"
                        >

                        <div class="form-grid">

                            <div class="form-group full-width">

                                <label for="full_name">
                                    Full Name
                                </label>

                                <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    value="<?php echo htmlspecialchars($customer_name); ?>"
                                    placeholder="Enter your full name"
                                    maxlength="100"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label for="email">
                                    Email Address
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="<?php echo htmlspecialchars($customer_email); ?>"
                                    placeholder="Enter your email"
                                    maxlength="100"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label for="phone">
                                    Contact Number
                                </label>

                                <input
                                    type="text"
                                    id="phone"
                                    name="phone"
                                    value="<?php echo htmlspecialchars($customer_phone); ?>"
                                    placeholder="Enter your contact number"
                                    maxlength="20"
                                    required
                                >

                            </div>

                        </div>

                        <div class="form-footer">

                            <button type="submit" class="save-button">
                                Save Changes
                            </button>

                        </div>

                    </form>

                </div>

            </section>

            <!-- CHANGE PASSWORD -->
            <section class="content-card">

                <div class="card-header">

                    <div class="header-icon password-icon">
                        🔒
                    </div>

                    <div class="header-title">

                        <h2>Change Password</h2>

                        <p>
                            Keep your account secure with a strong password.
                        </p>

                    </div>

                </div>

                <div class="card-body">

                    <form method="POST" action="profile.php">

                        <input
                            type="hidden"
                            name="profile_token"
                            value="<?php echo htmlspecialchars($token); ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="change_password"
                        >

                        <div class="password-grid">

                            <div class="form-group">

                                <label for="current_password">
                                    Current Password
                                </label>

                                <input
                                    type="password"
                                    id="current_password"
                                    name="current_password"
                                    placeholder="Enter your current password"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label for="new_password">
                                    New Password
                                </label>

                                <input
                                    type="password"
                                    id="new_password"
                                    name="new_password"
                                    placeholder="Enter your new password"
                                    minlength="8"
                                    required
                                >

                                <span class="form-note">
                                    Your password must contain at least 8 characters.
                                </span>

                            </div>

                            <div class="form-group">

                                <label for="confirm_password">
                                    Confirm New Password
                                </label>

                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Re-enter your new password"
                                    minlength="8"
                                    required
                                >

                            </div>

                        </div>

                        <div class="form-footer">

                            <button type="submit" class="save-button">
                                Update Password
                            </button>

                        </div>

                    </form>

                </div>

            </section>

        </div>

    </div>

    <!-- ONLY ONE BACK TO DASHBOARD -->
    <a href="dashboard.php" class="back-link">
        ← Back to Dashboard
    </a>

</main>

<!-- FOOTER -->
<footer>
    &copy; <?php echo date("Y"); ?> CakeCraft.
    All rights reserved.
</footer>

</body>
</html>

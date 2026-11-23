
<?php
session_start();
require_once "../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

$admin_id = (int) $_SESSION["admin_id"];

$message = "";
$message_type = "";

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Fetch current admin details
$stmt = $conn->prepare(
    "SELECT id, full_name, email, contact_number, password
     FROM admins
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $admin_id);
$stmt->execute();

$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if (!$admin) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        $message = "Invalid request. Please refresh the page and try again.";
        $message_type = "error";

    } else {

        $action = $_POST["action"] ?? "";

        // ==========================================
        // CHANGE EMAIL
        // ==========================================
        if ($action === "change_email") {

            $new_email = trim($_POST["new_email"] ?? "");
            $confirm_email = trim($_POST["confirm_email"] ?? "");
            $current_password = $_POST["current_password"] ?? "";

            if (
                empty($new_email) ||
                empty($confirm_email) ||
                empty($current_password)
            ) {
                $message = "Please complete all email fields.";
                $message_type = "error";

            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid email address.";
                $message_type = "error";

            } elseif ($new_email !== $confirm_email) {
                $message = "Email addresses do not match.";
                $message_type = "error";

            } elseif ($new_email === $admin["email"]) {
                $message = "This is already your current email address.";
                $message_type = "error";

            } elseif (!password_verify($current_password, $admin["password"])) {
                $message = "Your current password is incorrect.";
                $message_type = "error";

            } else {

                // Check if email is already being used
                $check = $conn->prepare(
                    "SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1"
                );

                $check->bind_param("si", $new_email, $admin_id);
                $check->execute();

                $check_result = $check->get_result();

                if ($check_result->num_rows > 0) {

                    $message = "This email address is already being used.";
                    $message_type = "error";

                } else {

                    $update = $conn->prepare(
                        "UPDATE admins SET email = ? WHERE id = ?"
                    );

                    $update->bind_param("si", $new_email, $admin_id);

                    if ($update->execute()) {

                        $_SESSION["admin_email"] = $new_email;
                        $admin["email"] = $new_email;

                        $message = "Email address successfully updated!";
                        $message_type = "success";

                    } else {

                        $message = "Unable to update email. Please try again.";
                        $message_type = "error";
                    }

                    $update->close();
                }

                $check->close();
            }
        }

        // ==========================================
        // CHANGE PASSWORD
        // ==========================================
        elseif ($action === "change_password") {

            $current_password = $_POST["current_password"] ?? "";
            $new_password = $_POST["new_password"] ?? "";
            $confirm_password = $_POST["confirm_password"] ?? "";

            if (
                empty($current_password) ||
                empty($new_password) ||
                empty($confirm_password)
            ) {
                $message = "Please complete all password fields.";
                $message_type = "error";

            } elseif (!password_verify($current_password, $admin["password"])) {
                $message = "Your current password is incorrect.";
                $message_type = "error";

            } elseif (strlen($new_password) < 8) {
                $message = "Your new password must be at least 8 characters.";
                $message_type = "error";

            } elseif ($new_password !== $confirm_password) {
                $message = "New passwords do not match.";
                $message_type = "error";

            } elseif (password_verify($new_password, $admin["password"])) {
                $message = "Your new password must be different from your current password.";
                $message_type = "error";

            } else {

                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                $update = $conn->prepare(
                    "UPDATE admins SET password = ? WHERE id = ?"
                );

                $update->bind_param("si", $hashed_password, $admin_id);

                if ($update->execute()) {

                    $admin["password"] = $hashed_password;

                    $message = "Password successfully updated!";
                    $message_type = "success";

                } else {

                    $message = "Unable to update password. Please try again.";
                    $message_type = "error";
                }

                $update->close();
            }
        }

        else {
            $message = "Invalid action.";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Account Settings | CakeCraft</title>

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
            --success-bg: #eaf7ed;
            --success-ink: #236b39;
            --error-bg: #fdecea;
            --error-ink: #9b1c1c;
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
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .page-container {
            width: 100%;
            max-width: 850px;
            margin: auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--rose-deep);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .back-link:hover {
            color: var(--rose-darker);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-family: var(--font-display);
            font-size: 34px;
            margin-bottom: 8px;
        }

        .page-header p {
            color: var(--ink-soft);
        }

        .profile-card {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 22px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            margin-bottom: 25px;
        }

        .avatar {
            width: 58px;
            height: 58px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: var(--bg-soft);
            color: var(--rose-deep);
            font-size: 23px;
            font-weight: 700;
        }

        .profile-info h2 {
            font-size: 18px;
            margin-bottom: 3px;
        }

        .profile-info p {
            font-size: 14px;
            color: var(--ink-soft);
            word-break: break-word;
        }

        .settings-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 22px;
            box-shadow: 0 5px 20px rgba(73, 48, 45, 0.04);
        }

        .card-heading {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 25px;
        }

        .card-icon {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-soft);
            border-radius: 12px;
            color: var(--rose-deep);
        }

        .card-icon svg {
            width: 22px;
            height: 22px;
        }

        .card-heading h2 {
            font-family: var(--font-display);
            font-size: 22px;
            margin-bottom: 4px;
        }

        .card-heading p {
            font-size: 14px;
            color: var(--ink-soft);
        }

        .form-group {
            margin-bottom: 19px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 7px;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--line);
            border-radius: 10px;
            background: var(--bg);
            color: var(--ink);
            font-family: inherit;
            font-size: 15px;
            transition: .2s;
        }

        input:hover {
            border-color: #d4bdb4;
        }

        input:focus {
            outline: none;
            border-color: var(--rose-deep);
            box-shadow: 0 0 0 4px rgba(196, 124, 120, .18);
            background: #fff;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 75px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: var(--rose-deep);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .toggle-password:hover {
            color: var(--rose-darker);
        }

        .hint {
            color: var(--ink-soft);
            font-size: 12px;
            margin-top: 6px;
        }

        .submit-btn {
            width: 100%;
            padding: 13px 18px;
            border: none;
            border-radius: 999px;
            background: var(--rose-deep);
            color: white;
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        .submit-btn:hover {
            background: var(--rose-darker);
            transform: translateY(-1px);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert.success {
            background: var(--success-bg);
            color: var(--success-ink);
            border: 1px solid #c5e7ce;
        }

        .alert.error {
            background: var(--error-bg);
            color: var(--error-ink);
            border: 1px solid #f3c7c2;
        }

        @media (max-width: 600px) {
            body {
                padding: 25px 14px;
            }

            .page-header h1 {
                font-size: 28px;
            }

            .settings-card {
                padding: 22px 18px;
            }

            .profile-card {
                padding: 17px;
            }
        }
    </style>
</head>

<body>

<div class="page-container">

    <a href="dashboard.php" class="back-link">
        ← Back to Dashboard
    </a>

    <div class="page-header">
        <h1>Account Settings</h1>
        <p>Manage your administrator email and password.</p>
    </div>

    <!-- ADMIN PROFILE -->
    <div class="profile-card">

        <div class="avatar">
            <?php echo strtoupper(substr($admin["full_name"], 0, 1)); ?>
        </div>

        <div class="profile-info">
            <h2><?php echo htmlspecialchars($admin["full_name"]); ?></h2>
            <p><?php echo htmlspecialchars($admin["email"]); ?></p>
        </div>

    </div>

    <?php if (!empty($message)): ?>

        <div class="alert <?php echo $message_type; ?>" role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <!-- CHANGE EMAIL -->
    <div class="settings-card">

        <div class="card-heading">

            <div class="card-icon">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                    <path d="m3 7 9 6 9-6"/>
                </svg>
            </div>

            <div>
                <h2>Change Email Address</h2>
                <p>Update the email you use to log in to your admin account.</p>
            </div>

        </div>

        <form method="POST" action="">

            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

            <input type="hidden" name="action" value="change_email">

            <div class="form-group">
                <label for="new_email">New Email Address</label>

                <input type="email"
                       id="new_email"
                       name="new_email"
                       placeholder="Enter your new email"
                       maxlength="100"
                       required>
            </div>

            <div class="form-group">
                <label for="confirm_email">Confirm New Email Address</label>

                <input type="email"
                       id="confirm_email"
                       name="confirm_email"
                       placeholder="Confirm your new email"
                       maxlength="100"
                       required>
            </div>

            <div class="form-group">
                <label for="email_current_password">Current Password</label>

                <div class="password-wrapper">
                    <input type="password"
                           id="email_current_password"
                           name="current_password"
                           placeholder="Enter your current password"
                           autocomplete="current-password"
                           required>

                    <button type="button"
                            class="toggle-password"
                            onclick="togglePassword('email_current_password', this)">
                        Show
                    </button>
                </div>

                <p class="hint">Required to confirm this account change.</p>
            </div>

            <button type="submit" class="submit-btn">
                Update Email Address
            </button>

        </form>

    </div>


    <!-- CHANGE PASSWORD -->
    <div class="settings-card">

        <div class="card-heading">

            <div class="card-icon">
                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="10" rx="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    <circle cx="12" cy="16" r="1"/>
                </svg>
            </div>

            <div>
                <h2>Change Password</h2>
                <p>Choose a new password to keep your admin account secure.</p>
            </div>

        </div>

        <form method="POST" action="">

            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION["csrf_token"]); ?>">

            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label for="password_current">Current Password</label>

                <div class="password-wrapper">
                    <input type="password"
                           id="password_current"
                           name="current_password"
                           placeholder="Enter your current password"
                           autocomplete="current-password"
                           required>

                    <button type="button"
                            class="toggle-password"
                            onclick="togglePassword('password_current', this)">
                        Show
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>

                <div class="password-wrapper">
                    <input type="password"
                           id="new_password"
                           name="new_password"
                           placeholder="Enter your new password"
                           minlength="8"
                           autocomplete="new-password"
                           required>

                    <button type="button"
                            class="toggle-password"
                            onclick="togglePassword('new_password', this)">
                        Show
                    </button>
                </div>

                <p class="hint">Must be at least 8 characters long.</p>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>

                <div class="password-wrapper">
                    <input type="password"
                           id="confirm_password"
                           name="confirm_password"
                           placeholder="Re-enter your new password"
                           minlength="8"
                           autocomplete="new-password"
                           required>

                    <button type="button"
                            class="toggle-password"
                            onclick="togglePassword('confirm_password', this)">
                        Show
                    </button>
                </div>
            </div>

            <button type="submit" class="submit-btn">
                Update Password
            </button>

        </form>

    </div>

</div>

<script>
function togglePassword(inputId, button) {

    const input = document.getElementById(inputId);

    if (input.type === "password") {
        input.type = "text";
        button.textContent = "Hide";
    } else {
        input.type = "password";
        button.textContent = "Show";
    }
}
</script>

</body>
</html>

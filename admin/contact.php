
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
         (id, contact_name, email, contact_number)
         VALUES (1, ?, ?, '')"
    );

    $stmt->bind_param("ss", $default_name, $default_email);
    $stmt->execute();
    $stmt->close();

    $result = $conn->query("SELECT * FROM site_contact WHERE id = 1");
    $contact = $result->fetch_assoc();
}

// Save contact information
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $contact_name = trim($_POST["contact_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $contact_number = trim($_POST["contact_number"] ?? "");

    if ($contact_name === "" || $email === "") {

        $message = "Please complete the name and email address.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    } else {

        $stmt = $conn->prepare(
            "UPDATE site_contact
             SET contact_name = ?,
                 email = ?,
                 contact_number = ?
             WHERE id = 1"
        );

        $stmt->bind_param(
            "sss",
            $contact_name,
            $email,
            $contact_number
        );

        if ($stmt->execute()) {

            $message = "Contact details updated successfully!";
            $message_type = "success";

        } else {

            $message = "Unable to update contact details.";
            $message_type = "error";
        }

        $stmt->close();

        $result = $conn->query("SELECT * FROM site_contact WHERE id = 1");
        $contact = $result->fetch_assoc();
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

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --bg: #fffaf7;
    --surface: #ffffff;
    --ink: #3b2724;
    --ink-soft: #6f5d57;
    --rose: #c47c78;
    --rose-deep: #a65a56;
    --rose-darker: #8c4845;
    --line: #efe0da;
    --bg-soft: #f7eeea;
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
    line-height: 1.6;
}

header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--ink);
    color: white;
    padding: 16px 6%;
}

header h2 {
    font-family: var(--font-display);
    font-size: 26px;
}

header h2 span {
    color: #e3a9a5;
}

header a {
    text-decoration: none;
    color: white;
    border: 1px solid rgba(255,255,255,.45);
    padding: 8px 18px;
    border-radius: 30px;
    font-size: 14px;
}

.container {
    width: 90%;
    max-width: 850px;
    margin: 45px auto;
}

.page-heading {
    margin-bottom: 28px;
}

.page-heading h1 {
    font-family: var(--font-display);
    font-size: clamp(28px, 4vw, 38px);
    margin-bottom: 8px;
}

.page-heading p {
    color: var(--ink-soft);
}

.card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 16px;
    box-shadow: 0 6px 24px rgba(73,48,45,.08);
    padding: 32px;
}

.form-group {
    margin-bottom: 22px;
}

label {
    display: block;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
}

input {
    width: 100%;
    padding: 13px 14px;
    border: 1.5px solid var(--line);
    border-radius: 10px;
    background: var(--bg);
    color: var(--ink);
    font-family: inherit;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: var(--rose-deep);
    box-shadow: 0 0 0 3px rgba(196,124,120,.2);
}

.help {
    color: var(--ink-soft);
    font-size: 13px;
    margin-top: 6px;
}

.message {
    padding: 13px 16px;
    border-radius: 10px;
    margin-bottom: 22px;
    font-size: 14px;
}

.message.success {
    background: #eaf7ed;
    color: #276738;
    border: 1px solid #c9e8d0;
}

.message.error {
    background: #fdecea;
    color: #9b1c1c;
    border: 1px solid #f3c7c2;
}

.buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 28px;
}

button, .back-btn {
    display: inline-block;
    padding: 12px 24px;
    border: none;
    border-radius: 999px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}

button {
    background: var(--rose-deep);
    color: white;
}

button:hover {
    background: var(--rose-darker);
}

.back-btn {
    background: var(--bg-soft);
    color: var(--ink);
}

.back-btn:hover {
    background: #eadbd5;
}

@media(max-width: 500px) {
    .card {
        padding: 22px;
    }

    .buttons {
        flex-direction: column;
    }

    button, .back-btn {
        text-align: center;
        width: 100%;
    }
}
</style>
</head>

<body>

<header>
    <h2>Cake<span>Craft</span></h2>
    <a href="dashboard.php">Back to Dashboard</a>
</header>

<div class="container">

    <div class="page-heading">
        <h1>Manage Admin Contact</h1>
        <p>Update the contact information displayed on the CakeCraft homepage.</p>
    </div>

    <div class="card">

        <?php if ($message !== ""): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-group">
                <label for="contact_name">Contact Person Name</label>
                <input
                    type="text"
                    id="contact_name"
                    name="contact_name"
                    value="<?php echo htmlspecialchars($contact["contact_name"] ?? ""); ?>"
                    maxlength="150"
                    required
                >
                <p class="help">Name shown to customers on the homepage.</p>
            </div>

            <div class="form-group">
                <label for="email">Contact Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo htmlspecialchars($contact["email"] ?? ""); ?>"
                    maxlength="150"
                    required
                >
                <p class="help">Customers can click this email to contact you.</p>
            </div>

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

            <div class="buttons">
                <button type="submit">Save Changes</button>
                <a href="dashboard.php" class="back-btn">Cancel</a>
            </div>

        </form>

    </div>

</div>

</body>
</html>

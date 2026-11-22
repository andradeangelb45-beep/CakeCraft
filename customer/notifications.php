
<?php
session_start();
require_once "../database.php";

// Check customer login
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit();
}

$customer_id = (int) $_SESSION["customer_id"];

// Mark notification as read when opened
if (isset($_GET["read"])) {
    $notification_id = (int) $_GET["read"];

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ?
        AND customer_id = ?
    ");

    $stmt->bind_param("ii", $notification_id, $customer_id);
    $stmt->execute();
    $stmt->close();

    // Get notification link
    $stmt = $conn->prepare("
        SELECT link
        FROM notifications
        WHERE notification_id = ?
        AND customer_id = ?
    ");

    $stmt->bind_param("ii", $notification_id, $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $notification = $result->fetch_assoc();
    $stmt->close();

    $redirect_link = "my_orders.php";

    if (!empty($notification["link"])) {
        $stored_link = trim($notification["link"]);

        // Allow safe internal links only
        if (
            !preg_match('/^[a-zA-Z0-9_\-]+\.php(?:\?[a-zA-Z0-9_\-=&]*)?$/', $stored_link)
        ) {
            $stored_link = "my_orders.php";
        }

        $redirect_link = $stored_link;
    }

    header("Location: " . $redirect_link);
    exit();
}

// Mark all notifications as read
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_all_read"])) {

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE customer_id = ?
        AND is_read = 0
    ");

    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $stmt->close();

    header("Location: notifications.php");
    exit();
}

// Get unread count
$stmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE customer_id = ?
    AND is_read = 0
");

$stmt->bind_param("i", $customer_id);
$stmt->execute();

$unread_result = $stmt->get_result();
$unread_count = $unread_result->fetch_assoc()["unread_count"] ?? 0;

$stmt->close();

// Get customer notifications
$stmt = $conn->prepare("
    SELECT *
    FROM notifications
    WHERE customer_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param("i", $customer_id);
$stmt->execute();

$result = $stmt->get_result();

$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Notifications | CakeCraft</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #fff8f5;
    color: #4b2929;
}

/* NAVBAR */
.navbar {
    background: #8b3f50;
    padding: 17px 50px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    color: white;
    font-size: 25px;
    font-weight: bold;
    text-decoration: none;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 25px;
}

.nav-links a {
    color: white;
    text-decoration: none;
    font-size: 15px;
}

.nav-links a:hover {
    opacity: 0.8;
}

/* PAGE */
.container {
    width: 90%;
    max-width: 900px;
    margin: 40px auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.page-header h1 {
    font-size: 30px;
    color: #8b3f50;
}

.page-header p {
    color: #8b7070;
    margin-top: 7px;
}

.mark-all-btn {
    border: none;
    background: #8b3f50;
    color: white;
    padding: 11px 18px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 14px;
}

.mark-all-btn:hover {
    background: #703140;
}

/* NOTIFICATION CARD */
.notification-card {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    background: white;
    padding: 22px;
    border-radius: 12px;
    margin-bottom: 15px;
    border: 1px solid #f0dddd;
    text-decoration: none;
    color: inherit;
    transition: 0.2s;
}

.notification-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 63, 80, 0.10);
}

.notification-card.unread {
    background: #fff0f2;
    border-left: 5px solid #8b3f50;
}

.notification-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    background: #f8e1e5;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 23px;
}

.notification-content {
    flex: 1;
}

.notification-content h3 {
    font-size: 17px;
    margin-bottom: 7px;
    color: #713343;
}

.notification-content p {
    font-size: 14px;
    line-height: 1.6;
    color: #705858;
}

.notification-date {
    font-size: 12px;
    color: #a58c8c;
    margin-top: 10px;
}

.unread-dot {
    width: 10px;
    height: 10px;
    background: #c44660;
    border-radius: 50%;
    margin-top: 5px;
}

/* EMPTY STATE */
.empty-state {
    text-align: center;
    background: white;
    padding: 60px 25px;
    border-radius: 12px;
    border: 1px solid #f0dddd;
}

.empty-state .icon {
    font-size: 55px;
    margin-bottom: 15px;
}

.empty-state h2 {
    color: #8b3f50;
    margin-bottom: 10px;
}

.empty-state p {
    color: #947d7d;
    font-size: 14px;
}

/* MOBILE */
@media (max-width: 650px) {

    .navbar {
        padding: 15px 20px;
        flex-direction: column;
        gap: 15px;
    }

    .nav-links {
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
    }

    .container {
        width: 94%;
        margin: 25px auto;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .page-header h1 {
        font-size: 25px;
    }

    .notification-card {
        padding: 16px;
    }
}
</style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar">

    <a href="dashboard.php" class="logo">
        CakeCraft
    </a>

    <div class="nav-links">
        <a href="dashboard.php">Home</a>
        <a href="cakes.php">Cakes</a>
        <a href="my_orders.php">My Orders</a>
        <a href="notifications.php">🔔 Notifications</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </div>

</nav>

<!-- MAIN CONTENT -->
<div class="container">

    <div class="page-header">

        <div>
            <h1>Notifications 🔔</h1>

            <p>
                <?php if ($unread_count > 0): ?>
                    You have <?= (int)$unread_count ?> unread notification(s).
                <?php else: ?>
                    You're all caught up!
                <?php endif; ?>
            </p>
        </div>

        <?php if ($unread_count > 0): ?>

            <form method="POST">
                <button
                    type="submit"
                    name="mark_all_read"
                    class="mark-all-btn"
                >
                    Mark All as Read
                </button>
            </form>

        <?php endif; ?>

    </div>

    <!-- NOTIFICATIONS LIST -->
    <?php if (!empty($notifications)): ?>

        <?php foreach ($notifications as $notification): ?>

            <?php
                $is_unread = (int)$notification["is_read"] === 0;

                $notification_link =
                    "notifications.php?read=" .
                    (int)$notification["notification_id"];

                $title = htmlspecialchars(
                    $notification["title"],
                    ENT_QUOTES,
                    "UTF-8"
                );

                $message = htmlspecialchars(
                    $notification["message"],
                    ENT_QUOTES,
                    "UTF-8"
                );

                $date = date(
                    "M d, Y - h:i A",
                    strtotime($notification["created_at"])
                );
            ?>

            <a
                href="<?= htmlspecialchars($notification_link) ?>"
                class="notification-card <?= $is_unread ? 'unread' : '' ?>"
            >

                <div class="notification-icon">
                    📦
                </div>

                <div class="notification-content">

                    <h3><?= $title ?></h3>

                    <p><?= nl2br($message) ?></p>

                    <div class="notification-date">
                        <?= $date ?>
                    </div>

                </div>

                <?php if ($is_unread): ?>
                    <div class="unread-dot"></div>
                <?php endif; ?>

            </a>

        <?php endforeach; ?>

    <?php else: ?>

        <div class="empty-state">

            <div class="icon">🔔</div>

            <h2>No Notifications Yet</h2>

            <p>
                You'll see updates about your cake orders here.
            </p>

        </div>

    <?php endif; ?>

</div>

</body>
</html>

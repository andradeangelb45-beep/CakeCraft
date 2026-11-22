<?php
if (!isset($conn)) {
    require_once __DIR__ . "/../database.php";
}

$unread_notifications = 0;

$customer_id = (int) ($_SESSION["customer_id"] ?? 0);

if ($customer_id > 0) {
    $stmt_notifications = $conn->prepare("
        SELECT COUNT(*) AS unread_count
        FROM notifications
        WHERE customer_id = ? AND is_read = 0
    ");

    if ($stmt_notifications) {
        $stmt_notifications->bind_param("i", $customer_id);
        $stmt_notifications->execute();

        $notification_result = $stmt_notifications->get_result();
        $notification_data = $notification_result->fetch_assoc();

        $unread_notifications = (int) ($notification_data["unread_count"] ?? 0);

        $stmt_notifications->close();
    }
}
?>

<style>
    .notification-nav {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        position: relative;
        text-decoration: none;
        width: 38px;
        height: 38px;
        font-size: 21px;
        border-radius: 50%;
        transition: background .2s ease;
    }

    .notification-nav::after {
        display: none !important;
    }

    .notification-nav:hover {
        background: #f7eeea;
    }

    .notification-badge {
        position: absolute;
        top: -3px;
        right: -4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #c43d46;
        color: #ffffff;
        border-radius: 999px;
        font-family: Arial, sans-serif;
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
        border: 2px solid #fffaf7;
    }
</style>

<a href="notifications.php"
   class="notification-nav"
   title="Notifications"
   aria-label="Notifications<?php echo $unread_notifications > 0 ? ', ' . $unread_notifications . ' unread' : ''; ?>">

    <span aria-hidden="true">🔔</span>

    <?php if ($unread_notifications > 0): ?>

        <span class="notification-badge">
            <?php echo $unread_notifications > 99 ? "99+" : $unread_notifications; ?>
        </span>

    <?php endif; ?>

</a>
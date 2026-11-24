
<?php
session_start();
require_once "../database.php";

// Check admin login
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

// ==========================================
// RESTORE CAKE
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["restore_cake"])) {

    $cake_id = (int)($_POST["cake_id"] ?? 0);

    if ($cake_id <= 0) {

        $message = "Invalid cake selected.";
        $message_type = "error";

    } else {

        $restore = $conn->prepare("
            UPDATE cake_options
            SET archived_at = NULL
            WHERE id = ?
            AND archived_at IS NOT NULL
        ");

        $restore->bind_param("i", $cake_id);
        $restore->execute();

        if ($restore->affected_rows > 0) {
            $message = "Cake successfully restored to Manage Cakes.";
            $message_type = "success";
        } else {
            $message = "Unable to restore cake. It may already be active or no longer exist.";
            $message_type = "error";
        }

        $restore->close();
    }
}

// ==========================================
// GET ARCHIVED CAKES
// ==========================================
$result = $conn->query("
    SELECT *
    FROM cake_options
    WHERE archived_at IS NOT NULL
    ORDER BY archived_at DESC
");

// ==========================================
// GET CAKE SIZES
// ==========================================
$all_sizes = [];

if ($result && $result->num_rows > 0) {

    $size_result = $conn->query("
        SELECT cake_id, size_name, price
        FROM cake_sizes
        ORDER BY id ASC
    ");

    while ($size = $size_result->fetch_assoc()) {
        $all_sizes[$size["cake_id"]][] = $size;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Archived Cakes | CakeCraft Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg: #fffaf7;
            --surface: #ffffff;
            --ink: #3b2724;
            --ink-soft: #6f5d57;
            --rose: #a65a56;
            --line: #efe0da;
            --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
        }

        header {
            background: var(--ink);
            color: white;
            padding: 18px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
        }

        header h2 span {
            color: #e3a9a5;
        }

        header a {
            color: white;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,.5);
            padding: 9px 20px;
            border-radius: 30px;
            font-size: 14px;
        }

        .container {
            max-width: 1180px;
            margin: auto;
            padding: 45px 6%;
        }

        .back {
            display: inline-block;
            margin-bottom: 25px;
            color: var(--rose);
            text-decoration: none;
            font-weight: 600;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--ink-soft);
            margin-bottom: 25px;
        }

        .top-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 25px;
        }

        .manage-link {
            background: #70452f;
            color: white;
            padding: 12px 22px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .manage-link:hover {
            background: #523222;
        }

        .cake-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .cake-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .cake-image {
            width: 100%;
            height: 230px;
            object-fit: cover;
            background: #f7eeea;
        }

        .no-image {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f7eeea;
            color: var(--ink-soft);
        }

        .cake-content {
            padding: 23px;
        }

        .cake-content h2 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .category {
            display: inline-block;
            background: #f8e9e6;
            color: #8c4845;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 13px;
        }

        .description {
            color: var(--ink-soft);
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .price {
            font-weight: 700;
            margin-bottom: 15px;
        }

        .sizes {
            border-top: 1px solid var(--line);
            padding-top: 14px;
            margin-bottom: 18px;
        }

        .sizes p {
            font-size: 14px;
            margin: 7px 0;
        }

        .archived-date {
            font-size: 13px;
            color: var(--ink-soft);
            margin-bottom: 18px;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
        }

        .restore-button {
            background: var(--rose);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
        }

        .restore-button:hover {
            background: #8c4845;
        }

        .message {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 22px;
        }

        .success {
            background: #e8f5e9;
            color: #256029;
        }

        .error {
            background: #fdecea;
            color: #a3261e;
        }

        .empty {
            background: white;
            padding: 40px;
            border: 1px solid var(--line);
            border-radius: 14px;
            text-align: center;
            color: var(--ink-soft);
        }

        @media (max-width: 700px) {
            .cake-grid {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 29px;
            }
        }
    </style>
</head>

<body>

<header>
    <h2>Cake<span>Craft</span> <small style="font-family:Arial;font-size:12px;">ADMIN</small></h2>
    <a href="logout.php">Logout</a>
</header>

<div class="container">

    <a href="manage_cakes.php" class="back">← Back to Manage Cakes</a>

    <h1>Archived Cakes</h1>

    <p class="subtitle">
        View and restore cakes that have been moved to the archive.
    </p>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="top-actions">
        <a href="manage_cakes.php" class="manage-link">Manage Active Cakes</a>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>

        <div class="cake-grid">

            <?php while ($cake = $result->fetch_assoc()): ?>

                <div class="cake-card">

                    <?php if (!empty($cake["image"])): ?>
                        <img
                            class="cake-image"
                            src="../assets/images/<?php echo htmlspecialchars(basename($cake["image"])); ?>"
                            alt="<?php echo htmlspecialchars($cake["cake_name"]); ?>"
                        >
                    <?php else: ?>
                        <div class="no-image">No cake image available</div>
                    <?php endif; ?>

                    <div class="cake-content">

                        <h2>
                            <?php echo htmlspecialchars($cake["cake_name"]); ?>
                        </h2>

                        <span class="category">
                            <?php echo htmlspecialchars($cake["category"] ?? "Uncategorized"); ?>
                        </span>

                        <p class="description">
                            <?php echo htmlspecialchars($cake["description"]); ?>
                        </p>

                        <p class="price">
                            Base Price: ₱<?php echo number_format((float)$cake["base_price"], 2); ?>
                        </p>

                        <div class="sizes">
                            <strong>Available Sizes and Prices</strong>

                            <?php $cake_sizes = $all_sizes[$cake["id"]] ?? []; ?>

                            <?php if (!empty($cake_sizes)): ?>

                                <?php foreach ($cake_sizes as $size): ?>
                                    <p>
                                        <?php echo htmlspecialchars($size["size_name"]); ?>
                                        — ₱<?php echo number_format((float)$size["price"], 2); ?>
                                    </p>
                                <?php endforeach; ?>

                            <?php else: ?>
                                <p>No sizes added yet.</p>
                            <?php endif; ?>

                        </div>

                        <p class="archived-date">
                            Archived on:
                            <?php echo htmlspecialchars(date("M d, Y h:i A", strtotime($cake["archived_at"]))); ?>
                        </p>

                        <div class="actions">

                            <form method="POST"
                                  onsubmit="return confirm('Restore this cake to Manage Cakes?');">

                                <input
                                    type="hidden"
                                    name="cake_id"
                                    value="<?php echo (int)$cake["id"]; ?>"
                                >

                                <button
                                    type="submit"
                                    name="restore_cake"
                                    class="restore-button">
                                    Restore Cake
                                </button>

                            </form>

                        </div>

                    </div>
                </div>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty">
            <h2>No Archived Cakes</h2>
            <p>There are currently no cakes in the archive.</p>
        </div>

    <?php endif; ?>

</div>

</body>
</html>

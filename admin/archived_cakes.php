
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
        try {
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

        } catch (Throwable $e) {
            $message = "An error occurred while restoring the cake.";
            $message_type = "error";
        }
    }
}

// ==========================================
// PERMANENTLY DELETE CAKE
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["permanent_delete_cake"])) {

    $cake_id = (int)($_POST["cake_id"] ?? 0);

    if ($cake_id <= 0) {
        $message = "Invalid cake selected.";
        $message_type = "error";
    } else {

        $image_to_delete = "";
        $transaction_started = false;

        try {
            $conn->begin_transaction();
            $transaction_started = true;

            // 1. Make sure the selected cake is archived
            $check = $conn->prepare("
                SELECT cake_name, image
                FROM cake_options
                WHERE id = ?
                AND archived_at IS NOT NULL
                FOR UPDATE
            ");

            $check->bind_param("i", $cake_id);
            $check->execute();

            $cake_result = $check->get_result();
            $cake_data = $cake_result->fetch_assoc();
            $check->close();

            if (!$cake_data) {
                throw new Exception("The archived cake could not be found.");
            }

            $image_to_delete = $cake_data["image"] ?? "";

            // 2. Save cake name and size snapshots
            // Existing snapshots will not be overwritten.
            $save_snapshots = $conn->prepare("
                UPDATE orders o
                INNER JOIN cake_options co
                    ON o.cake_id = co.id
                LEFT JOIN cake_sizes cs
                    ON cs.id = o.cake_size_id
                    AND cs.cake_id = o.cake_id
                SET
                    o.cake_name_snapshot = COALESCE(
                        NULLIF(TRIM(o.cake_name_snapshot), ''),
                        co.cake_name
                    ),
                    o.cake_size_snapshot = COALESCE(
                        NULLIF(TRIM(o.cake_size_snapshot), ''),
                        cs.size_name
                    )
                WHERE o.cake_id = ?
            ");

            $save_snapshots->bind_param("i", $cake_id);
            $save_snapshots->execute();
            $save_snapshots->close();

            // 3. Verify that order details were saved
            $verify = $conn->prepare("
                SELECT id
                FROM orders
                WHERE cake_id = ?
                AND (
                    cake_name_snapshot IS NULL
                    OR TRIM(cake_name_snapshot) = ''
                    OR (
                        cake_size_id IS NOT NULL
                        AND (
                            cake_size_snapshot IS NULL
                            OR TRIM(cake_size_snapshot) = ''
                        )
                    )
                )
                LIMIT 1
            ");

            $verify->bind_param("i", $cake_id);
            $verify->execute();

            $verify_result = $verify->get_result();
            $missing_snapshot = $verify_result->fetch_assoc();
            $verify->close();

            if ($missing_snapshot) {
                throw new Exception("Some order details could not be saved.");
            }

            // 4. Disconnect existing orders from the cake
            // The order records themselves will remain.
            $detach_orders = $conn->prepare("
                UPDATE orders
                SET
                    cake_id = NULL,
                    cake_size_id = NULL
                WHERE cake_id = ?
            ");

            $detach_orders->bind_param("i", $cake_id);
            $detach_orders->execute();
            $detach_orders->close();

            // 5. Delete associated cake sizes
            $delete_sizes = $conn->prepare("
                DELETE FROM cake_sizes
                WHERE cake_id = ?
            ");

            $delete_sizes->bind_param("i", $cake_id);
            $delete_sizes->execute();
            $delete_sizes->close();

            // 6. Permanently delete the archived cake
            $delete_cake = $conn->prepare("
                DELETE FROM cake_options
                WHERE id = ?
                AND archived_at IS NOT NULL
            ");

            $delete_cake->bind_param("i", $cake_id);
            $delete_cake->execute();

            if ($delete_cake->affected_rows <= 0) {
                $delete_cake->close();
                throw new Exception("The cake could not be deleted.");
            }

            $delete_cake->close();

            // 7. Commit database changes
            $conn->commit();
            $transaction_started = false;

            // 8. Delete the associated image after successful commit
            if (!empty($image_to_delete)) {

                $image_filename = basename($image_to_delete);
                $image_path = __DIR__ . "/../assets/images/" . $image_filename;

                if (is_file($image_path) && !@unlink($image_path)) {
                    $message = "Cake permanently deleted, but its image file could not be removed.";
                    $message_type = "error";
                }
            }

            if ($message === "") {
                $message = "Cake permanently deleted successfully.";
                $message_type = "success";
            }

        } catch (Throwable $e) {

            if ($transaction_started) {
                $conn->rollback();
            }

            $message = "Unable to permanently delete this cake. Its order details were protected. Please try again or check the database records.";
            $message_type = "error";
        }
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

    if ($size_result) {
        while ($size = $size_result->fetch_assoc()) {
            $all_sizes[$size["cake_id"]][] = $size;
        }
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
            --shadow: 0 8px 26px rgba(73, 48, 45, 0.07);
            --shadow-lift: 0 14px 32px rgba(73, 48, 45, 0.12);
            --font-body: 'DM Sans', system-ui, sans-serif;
            --font-display: 'Playfair Display', Georgia, serif;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: var(--bg);
            color: var(--ink);
            font-family: var(--font-body);
            font-size: 15px;
            line-height: 1.6;
        }

        :focus-visible {
            outline: 3px solid var(--rose);
            outline-offset: 3px;
            border-radius: 4px;
        }

        .admin-header {
            position: sticky;
            top: 0;
            z-index: 20;
            min-height: 76px;
            padding: 14px 6%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            background: rgba(255, 250, 247, 0.96);
            border-bottom: 1px solid var(--line);
            box-shadow: 0 3px 14px rgba(73, 48, 45, 0.04);
            backdrop-filter: blur(12px);
        }

        .brand-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--ink);
            text-decoration: none;
            font-family: var(--font-display);
            font-size: 27px;
            font-weight: 700;
            letter-spacing: -0.6px;
            line-height: 1;
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

        .brand span.brand-craft { color: var(--craft-color); }

        .admin-label {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--bg-soft);
            color: var(--rose-deep);
            font-family: var(--font-body);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            vertical-align: middle;
        }

        .header-actions { display: flex; align-items: center; gap: 10px; }

        .header-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 8px 17px;
            border: 1.5px solid var(--rose);
            border-radius: 999px;
            color: var(--ink);
            background: transparent;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: .2s ease;
        }

        .header-link:hover {
            border-color: var(--rose-deep);
            color: var(--rose-deep);
            background: var(--bg-soft);
        }

        .container {
            width: min(1180px, 88%);
            margin: 0 auto;
            padding: 38px 0 64px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            color: var(--rose-deep);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }

        .back-link:hover { color: var(--rose-darker); }

        .page-heading { margin-bottom: 25px; }

        h1 {
            margin-bottom: 7px;
            color: var(--ink);
            font-family: var(--font-display);
            font-size: clamp(30px, 4vw, 39px);
            font-weight: 600;
            line-height: 1.2;
        }

        .subtitle { color: var(--ink-soft); font-size: 14px; }

        .toolbar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 23px;
        }

        .primary-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 43px;
            padding: 10px 20px;
            border: 1px solid var(--rose-deep);
            border-radius: 999px;
            background: var(--rose-deep);
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: .2s ease;
        }

        .primary-link:hover {
            background: var(--rose-darker);
            border-color: var(--rose-darker);
            transform: translateY(-1px);
        }

        .message {
            padding: 13px 16px;
            margin-bottom: 22px;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .message.success {
            background: #edf5ed;
            border-color: #d7e8d8;
            color: #426c4e;
        }

        .message.error {
            background: #f9e9e7;
            border-color: #efd1cd;
            color: #963f3b;
        }

        .cake-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }

        .cake-card {
            min-width: 0;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 17px;
            background: var(--surface);
            box-shadow: var(--shadow);
            transition: transform .22s ease, box-shadow .22s ease;
        }

        .cake-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lift);
        }

        .cake-image, .no-image {
            display: block;
            width: 100%;
            height: 235px;
            background: var(--bg-soft);
        }

        .cake-image { object-fit: cover; }

        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ink-soft);
            font-size: 13px;
        }

        .cake-content { padding: 22px 23px 23px; }

        .cake-content h2 {
            margin-bottom: 9px;
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 600;
            line-height: 1.25;
        }

        .category {
            display: inline-flex;
            margin-bottom: 13px;
            padding: 5px 11px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--bg-soft);
            color: var(--rose-darker);
            font-size: 11px;
            font-weight: 700;
        }

        .description {
            margin-bottom: 14px;
            color: var(--ink-soft);
            font-size: 13px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .price {
            margin-bottom: 15px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
        }

        .sizes {
            padding-top: 14px;
            margin-bottom: 16px;
            border-top: 1px solid var(--line);
        }

        .sizes strong {
            display: block;
            margin-bottom: 7px;
            color: var(--ink);
            font-size: 12px;
        }

        .sizes p {
            margin: 5px 0;
            color: var(--ink-soft);
            font-size: 13px;
        }

        .archived-date {
            margin-bottom: 18px;
            color: var(--ink-soft);
            font-size: 12px;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 9px;
            flex-wrap: wrap;
            padding-top: 15px;
            border-top: 1px solid var(--line);
        }

        .actions form { margin: 0; }

        .restore-button,
        .permanent-delete-button {
            min-height: 40px;
            padding: 9px 17px;
            border-radius: 999px;
            cursor: pointer;
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 700;
            transition: .2s ease;
        }

        .restore-button {
            border: 1px solid var(--rose-deep);
            background: var(--rose-deep);
            color: #fff;
        }

        .restore-button:hover {
            background: var(--rose-darker);
            border-color: var(--rose-darker);
        }

        .permanent-delete-button {
            border: 1px solid #b94d49;
            background: #fff;
            color: #a33e3a;
        }

        .permanent-delete-button:hover {
            background: #f9e9e7;
            border-color: #963f3b;
            color: #963f3b;
        }

        .empty {
            padding: 52px 24px;
            border: 1px dashed var(--line);
            border-radius: 16px;
            background: var(--surface);
            text-align: center;
        }

        .empty h2 {
            margin-bottom: 7px;
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 600;
        }

        .empty p { color: var(--ink-soft); font-size: 13px; }

        @media (max-width: 760px) {
            .container {
                width: min(100% - 36px, 600px);
                padding-top: 28px;
            }

            .cake-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .admin-header {
                padding: 13px 18px;
                flex-wrap: wrap;
            }

            .brand { font-size: 23px; }
        }

        @media (max-width: 440px) {
            .admin-header { align-items: flex-start; }

            .admin-label {
                padding: 3px 8px;
                font-size: 10px;
            }

            .header-link {
                min-height: 36px;
                padding: 7px 12px;
                font-size: 12px;
            }

            .cake-image, .no-image { height: 205px; }
            .cake-content { padding: 19px; }

            .toolbar { justify-content: stretch; }

            .primary-link { width: 100%; }

            .actions { justify-content: flex-start; }

            .restore-button,
            .permanent-delete-button {
                padding: 9px 13px;
                font-size: 12px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>
<body>

<header class="admin-header">
    <div class="brand-wrap">
        <a class="brand" href="dashboard.php">
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
        <span class="admin-label">ADMIN</span>
    </div>

    <div class="header-actions">
        <a class="header-link" href="dashboard.php">Dashboard</a>
        <a class="header-link" href="logout.php">Logout</a>
    </div>
</header>

<main class="container">

    <a href="manage_cakes.php" class="back-link">← Back to Manage Cakes</a>

    <div class="page-heading">
        <h1>Archived Cakes</h1>
        <p class="subtitle">View, restore, or permanently delete cakes that have been moved to the archive.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>" role="status">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="toolbar">
        <a href="manage_cakes.php" class="primary-link">Manage Active Cakes</a>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>

        <div class="cake-grid">

            <?php while ($cake = $result->fetch_assoc()): ?>

                <article class="cake-card">

                    <?php if (!empty($cake["image"])): ?>

                        <img
                            class="cake-image"
                            src="../assets/images/<?php echo htmlspecialchars(basename($cake["image"]), ENT_QUOTES, 'UTF-8'); ?>"
                            alt="<?php echo htmlspecialchars($cake["cake_name"], ENT_QUOTES, 'UTF-8'); ?>"
                        >

                    <?php else: ?>

                        <div class="no-image">No cake image available</div>

                    <?php endif; ?>

                    <div class="cake-content">

                        <h2>
                            <?php echo htmlspecialchars($cake["cake_name"], ENT_QUOTES, 'UTF-8'); ?>
                        </h2>

                        <span class="category">
                            <?php echo htmlspecialchars($cake["category"] ?? "Uncategorized", ENT_QUOTES, 'UTF-8'); ?>
                        </span>

                        <p class="description">
                            <?php echo htmlspecialchars($cake["description"] ?? "", ENT_QUOTES, 'UTF-8'); ?>
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
                                        <?php echo htmlspecialchars($size["size_name"], ENT_QUOTES, 'UTF-8'); ?>
                                        — ₱<?php echo number_format((float)$size["price"], 2); ?>
                                    </p>
                                <?php endforeach; ?>

                            <?php else: ?>

                                <p>No sizes added yet.</p>

                            <?php endif; ?>
                        </div>

                        <p class="archived-date">
                            Archived on:
                            <?php
                                echo htmlspecialchars(
                                    date("M d, Y h:i A", strtotime($cake["archived_at"])),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>
                        </p>

                        <div class="actions">

                            <!-- RESTORE CAKE -->
                            <form
                                method="POST"
                                onsubmit="return confirm('Restore this cake to Manage Cakes?');"
                            >
                                <input
                                    type="hidden"
                                    name="cake_id"
                                    value="<?php echo (int)$cake["id"]; ?>"
                                >

                                <button
                                    type="submit"
                                    name="restore_cake"
                                    class="restore-button"
                                >
                                    Restore Cake
                                </button>
                            </form>

                            <!-- PERMANENTLY DELETE CAKE -->
                            <form
                                method="POST"
                                onsubmit="return confirm('WARNING: This will permanently delete this cake and its associated sizes and image. Existing orders will be preserved. This action cannot be undone. Are you sure you want to continue?');"
                            >
                                <input
                                    type="hidden"
                                    name="cake_id"
                                    value="<?php echo (int)$cake["id"]; ?>"
                                >

                                <button
                                    type="submit"
                                    name="permanent_delete_cake"
                                    class="permanent-delete-button"
                                >
                                    Delete Permanently
                                </button>
                            </form>

                        </div>
                    </div>
                </article>

            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty">
            <h2>No Archived Cakes</h2>
            <p>There are currently no cakes in the archive.</p>
        </div>

    <?php endif; ?>

</main>

</body>
</html>

<?php
session_start();
require_once "../database.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

$message = "";
$message_type = "";

// ARCHIVE CAKE (SOFT DELETE)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_cake"])) {
    $cake_id = (int)($_POST["cake_id"] ?? 0);

    if ($cake_id <= 0) {
        $message = "Invalid cake selected.";
        $message_type = "error";
    } else {
        try {
            $archive_cake = $conn->prepare(
                "UPDATE cake_options SET archived_at = NOW() WHERE id = ? AND archived_at IS NULL"
            );
            $archive_cake->bind_param("i", $cake_id);
            $archive_cake->execute();

            if ($archive_cake->affected_rows > 0) {
                $message = "Cake successfully moved to Archived Cakes.";
                $message_type = "success";
            } else {
                $message = "Unable to archive cake. It may already be archived or does not exist.";
                $message_type = "error";
            }
            $archive_cake->close();
        } catch (Exception $e) {
            $message = "Unable to archive cake. Please try again.";
            $message_type = "error";
        }
    }
}

// GET ACTIVE CAKES ONLY
$sql = "SELECT * FROM cake_options WHERE archived_at IS NULL ORDER BY id DESC";
$result = $conn->query($sql);

// GET ALL SIZES IN ONE QUERY
$all_sizes = [];
if ($result && $result->num_rows > 0) {
    $size_result = $conn->query(
        "SELECT cake_id, size_name, price FROM cake_sizes ORDER BY id ASC"
    );
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
    <title>Manage Cakes | CakeCraft Admin</title>

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
            --gutter: max(6%, calc((100vw - 1180px) / 2));
            --shadow: 0 6px 24px rgba(73, 48, 45, 0.08);
            --shadow-lift: 0 14px 34px rgba(73, 48, 45, 0.14);
            --ease: cubic-bezier(.2, .7, .2, 1);
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.6;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }
        :focus-visible { outline: 3px solid var(--rose); outline-offset: 3px; border-radius: 4px; }

        header {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 14px var(--gutter);
            background: var(--bg);
            color: var(--ink);
            border-bottom: 1px solid var(--line);
            box-shadow: 0 2px 14px rgba(59, 39, 36, 0.05);
        }
        .brand { display: flex; align-items: center; gap: 12px; position: relative; }
        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 10px;
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
        .brand h2 {
            color: var(--ink);
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.01em;
        }
        .brand h2 span.brand-craft { color: var(--craft-color); }
        .brand .badge {
            padding: 3px 11px;
            border-radius: 999px;
            background: var(--bg-soft);
            border: 1px solid var(--line);
            color: var(--rose-deep);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
        }
        .header-actions { display: flex; align-items: center; gap: 12px; }
        .header-actions a {
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 9px 19px;
            border-radius: 999px;
            transition: background .2s var(--ease), border-color .2s var(--ease);
        }
        .dashboard-btn { color: var(--ink); border: 1.5px solid var(--rose); background: transparent; }
        .dashboard-btn:hover { background: var(--bg-soft); border-color: var(--rose-deep); }
        .logout-btn { color: #fff; border: 1.5px solid var(--rose-deep); background: var(--rose-deep); }
        .logout-btn:hover { background: var(--rose-darker); border-color: var(--rose-darker); }

        .container { max-width: 1400px; margin: 0 auto; padding: 42px var(--gutter) 64px; }
        .back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            color: var(--rose-deep);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        .back:hover { color: var(--rose-darker); }
        h1 {
            font-family: var(--font-display);
            font-size: clamp(1.9rem, 4vw, 2.6rem);
            font-weight: 600;
            line-height: 1.15;
            letter-spacing: -.015em;
            margin-bottom: 9px;
        }
        .subtitle { color: var(--ink-soft); margin-bottom: 26px; }
        .message { padding: 13px 17px; border-radius: 11px; margin-bottom: 22px; border: 1px solid transparent; }
        .message.success { background: #edf5ed; color: #426c4e; border-color: #d7e8d8; }
        .message.error { background: #f9e9e7; color: #963f3b; border-color: #efd1cd; }

        .top-actions { display: flex; justify-content: flex-end; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 25px; }
        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 20px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: background .2s var(--ease), transform .2s var(--ease);
        }
        .action-button:hover { transform: translateY(-1px); }
        .archive-button { background: var(--bg-soft); color: var(--rose-darker); border: 1px solid var(--line); }
        .archive-button:hover { background: #f0e2dc; }
        .add-button { background: var(--rose-deep); color: #fff; border: 1px solid var(--rose-deep); }
        .add-button:hover { background: var(--rose-darker); border-color: var(--rose-darker); }

        .cake-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
        .cake-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 17px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform .25s var(--ease), box-shadow .25s var(--ease);
        }
        .cake-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lift); }
        .cake-image { display: block; width: 100%; height: 245px; object-fit: cover; background: var(--bg-soft); }
        .no-image {
            height: 210px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-soft);
            color: var(--ink-soft);
            font-size: 14px;
        }
        .cake-content { padding: 24px; }
        .cake-content h2 { font-family: var(--font-display); font-size: 24px; font-weight: 600; margin-bottom: 9px; }
        .category {
            display: inline-block;
            background: var(--bg-soft);
            color: var(--rose-darker);
            border: 1px solid var(--line);
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 13px;
        }
        .description { color: var(--ink-soft); line-height: 1.65; margin-bottom: 15px; }
        .price { color: var(--ink); font-weight: 700; margin-bottom: 16px; }
        .sizes { border-top: 1px solid var(--line); padding-top: 14px; margin-bottom: 19px; }
        .sizes strong { display: block; margin-bottom: 8px; font-size: 14px; }
        .sizes p { color: var(--ink-soft); font-size: 14px; margin: 6px 0; }
        .actions { display: flex; justify-content: flex-end; align-items: center; gap: 10px; flex-wrap: wrap; }
        .edit-button, .delete-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 999px;
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background .2s var(--ease), border-color .2s var(--ease);
        }
        .edit-button { color: var(--rose-darker); background: var(--bg-soft); border: 1px solid var(--line); }
        .edit-button:hover { background: #f0e2dc; }
        .delete-button { color: #fff; background: var(--rose-deep); border: 1px solid var(--rose-deep); }
        .delete-button:hover { background: var(--rose-darker); border-color: var(--rose-darker); }
        .delete-button:focus-visible, .edit-button:focus-visible { outline-color: var(--rose-deep); }
        .empty {
            background: var(--surface);
            padding: 42px 25px;
            border: 1px dashed var(--line);
            border-radius: 16px;
            text-align: center;
            color: var(--ink-soft);
        }
        .empty h2 { font-family: var(--font-display); color: var(--ink); font-size: 23px; margin-bottom: 8px; }

        @media (max-width: 760px) {
            header { padding: 14px 6%; flex-wrap: wrap; }
            .container { padding: 34px 6% 48px; }
            .cake-grid { grid-template-columns: 1fr; }
            .top-actions { justify-content: flex-start; }
        }
        @media (max-width: 520px) {
            .brand h2 { font-size: 25px; }
            .brand .badge { font-size: 10px; }
            .header-actions { width: 100%; justify-content: flex-start; }
            .cake-content { padding: 20px; }
            .cake-content h2 { font-size: 22px; }
            .actions { justify-content: flex-start; }
            .action-button { padding: 10px 15px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
<header>
    <div class="brand">
        <div class="brand-mark">
            <span class="brand-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3v3" />
                    <path d="M12 6c-1.1 0-2 .7-2 1.6 0 .5.2.9.5 1.4" />
                    <path d="M5 12h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2Z" />
                    <path d="M3 16c1.5 0 2.5-1 4-1s2.5 1 4 1 2.5-1 4-1 2.5 1 4 1 2-.5 2-1" />
                    <path d="M3 19h18" />
                </svg>
            </span>
            <h2>Cake<span class="brand-craft">Craft</span></h2>
        </div>
        <span class="badge">ADMIN</span>
    </div>
    <nav class="header-actions" aria-label="Admin navigation">
        <a href="dashboard.php" class="dashboard-btn">Dashboard</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </nav>
</header>

<main class="container">
    <a href="dashboard.php" class="back">← Back to Dashboard</a>

    <h1>Manage Cakes</h1>
    <p class="subtitle">View and manage the cakes available on your CakeCraft website.</p>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>" role="status">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="top-actions">
        <a href="archived_cakes.php" class="action-button archive-button">♲ Archived Cakes</a>
        <a href="add_cake.php" class="action-button add-button">+ Add New Cake</a>
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
                        <h2><?php echo htmlspecialchars($cake["cake_name"], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <span class="category"><?php echo htmlspecialchars($cake["category"] ?? "Uncategorized", ENT_QUOTES, 'UTF-8'); ?></span>
                        <p class="description"><?php echo htmlspecialchars($cake["description"] ?? "", ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="price">Base Price: ₱<?php echo number_format((float)$cake["base_price"], 2); ?></p>

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

                        <div class="actions">
                            <a href="edit_cake.php?id=<?php echo (int)$cake["id"]; ?>" class="edit-button">Edit Cake</a>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to move this cake to Archived Cakes?');">
                                <input type="hidden" name="cake_id" value="<?php echo (int)$cake["id"]; ?>">
                                <button type="submit" name="delete_cake" class="delete-button">Archive Cake</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty">
            <h2>No Active Cakes Available</h2>
            <p>Click "Add New Cake" to add a new cake or visit Archived Cakes to restore an existing one.</p>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
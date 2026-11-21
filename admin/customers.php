
<?php
session_start();
require_once "../database.php";

// Check if admin is logged in
if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

// Get all registered customers
$sql = "SELECT id, full_name, email, phone, created_at
        FROM customers
        ORDER BY created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Database error: " . htmlspecialchars($conn->error));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customers | CakeCraft Admin</title>

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

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--ink-soft);
            margin-bottom: 28px;
        }

        .table-container {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th, td {
            padding: 17px 20px;
            text-align: left;
            border-bottom: 1px solid var(--line);
            font-size: 14px;
        }

        th {
            background: #f7eeea;
            color: var(--ink);
            font-weight: 700;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #fffaf7;
        }

        .empty {
            text-align: center;
            padding: 45px 20px;
            color: var(--ink-soft);
        }

        .back {
            display: inline-block;
            margin-bottom: 25px;
            color: var(--rose);
            text-decoration: none;
            font-weight: 600;
        }

        .back:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            h1 {
                font-size: 29px;
            }

            .container {
                padding: 30px 5%;
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

    <a href="dashboard.php" class="back">
        ← Back to Dashboard
    </a>

    <h1>Registered Customers</h1>

    <p class="subtitle">
        View the customers who have registered on CakeCraft.
    </p>

    <div class="table-container">

        <?php if ($result->num_rows > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Contact Number</th>
                        <th>Registration Date</th>
                    </tr>
                </thead>

                <tbody>

                    <?php while ($customer = $result->fetch_assoc()): ?>

                        <tr>
                            <td>
                                <?php echo (int)$customer["id"]; ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($customer["full_name"]); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($customer["email"]); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($customer["phone"]); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($customer["created_at"]); ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                </tbody>
            </table>

        <?php else: ?>

            <div class="empty">
                <h3>No Registered Customers Yet</h3>
                <p>Customer accounts will appear here after registration.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>

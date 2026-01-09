<?php
/*
File: admin/dashboard.php
Purpose: Main Stats & Menu
*/
session_start();
require_once '../includes/db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

// --- Fetch Stats ---
// 1. Total Users
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$total_users = $stmt->fetchColumn();

// 2. Pending Orders
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'");
$pending_orders = $stmt->fetchColumn();

// 3. Total Deposit (Completed)
$stmt = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type IN ('buy','deposit') AND status = 'approved'");
$total_deposit = $stmt->fetchColumn() ?: '0.00';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Admin Specific Styles */
        body { padding-top: 80px; padding-bottom: 20px; }
        .admin-nav {
            position: fixed; top: 0; left: 0; width: 100%; height: 60px;
            background: #111; border-bottom: 1px solid gold; z-index: 1000;
            display: flex; align-items: center; justify-content: space-between; padding: 0 20px;
        }
        .stat-card { background: #222; border: 1px solid #444; padding: 20px; border-radius: 10px; margin-bottom: 15px; }
        .menu-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 20px; }
        .menu-item { background: #333; padding: 20px; border-radius: 10px; text-align: center; color: white; text-decoration: none; border: 1px solid transparent; transition: 0.3s; }
        .menu-item:hover { border-color: gold; background: #222; }
        .menu-item i { font-size: 24px; margin-bottom: 10px; color: gold; }
    </style>
</head>
<body>

    <div class="admin-nav">
        <h3 style="color: gold;">Admin Panel</h3>
        <a href="logout.php" style="color: #ff4d4d; text-decoration: none;"><i class="fa-solid fa-power-off"></i> Logout</a>
    </div>

    <div class="container">
        
        <h3 style="margin-bottom: 15px;">Dashboard</h3>
        
        <div class="stat-card" style="border-left: 5px solid gold;">
            <div style="color: #aaa;">Pending Orders</div>
            <h1 style="color: #fff;"><?php echo $pending_orders; ?></h1>
        </div>

        <div style="display: flex; gap: 15px;">
            <div class="stat-card" style="flex: 1;">
                <div style="color: #aaa;">Total Users</div>
                <h3><?php echo $total_users; ?></h3>
            </div>
            <div class="stat-card" style="flex: 1;">
                <div style="color: #aaa;">Total Volume</div>
                <h3>$<?php echo number_format($total_deposit); ?></h3>
            </div>
        </div>

        <h3 style="margin: 15px 0;">Management</h3>
        <div class="menu-grid">
            <a href="orders.php" class="menu-item">
                <i class="fa-solid fa-list-check"></i>
                <div>Orders</div>
            </a>
            <a href="users.php" class="menu-item">
                <i class="fa-solid fa-users"></i>
                <div>Users</div>
            </a>
            <a href="referral.php" class="menu-item">
                <i class="fa-solid fa-share-nodes"></i>
                <div>Referral & Settings</div>
            </a>
            <a href="users.php" class="menu-item" style="opacity: 0.5;">
                <i class="fa-brands fa-bitcoin"></i>
                <div>Assets (Coming Soon)</div>
            </a>
        </div>

    </div>

</body>
</html>

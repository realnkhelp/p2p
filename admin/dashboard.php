<?php
/*
File: admin/dashboard.php
Purpose: Main Stats, Live Feed & Quick Menu
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
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// 2. Pending Orders (Action Required)
$pending_orders = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn();

// 3. Total Deposit (Money IN: Buy + Deposit)
$stmt = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type IN ('buy','deposit') AND status = 'approved'");
$total_in = $stmt->fetchColumn() ?: '0.00';

// 4. Total Withdraw (Money OUT: Sell + Withdraw)
$stmt = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type IN ('sell','withdraw') AND status IN ('approved','completed')");
$total_out = $stmt->fetchColumn() ?: '0.00';

// 5. Fetch Recent 5 Activities (Live Feed)
$recent_tx = $pdo->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

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
        body { padding-top: 80px; padding-bottom: 20px; background: #000; color: #ddd; }
        .admin-nav {
            position: fixed; top: 0; left: 0; width: 100%; height: 60px;
            background: #111; border-bottom: 1px solid gold; z-index: 1000;
            display: flex; align-items: center; justify-content: space-between; padding: 0 20px;
        }
        
        /* Cards */
        .stat-grid { display: flex; gap: 15px; flex-wrap: wrap; }
        .stat-card { background: #1a1a1a; border: 1px solid #333; padding: 20px; border-radius: 10px; flex: 1; min-width: 140px; }
        
        /* Menu */
        .menu-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 20px; }
        .menu-item { background: #222; padding: 20px; border-radius: 10px; text-align: center; color: white; text-decoration: none; border: 1px solid #333; transition: 0.3s; }
        .menu-item:hover { border-color: gold; background: #111; transform: translateY(-2px); }
        .menu-item i { font-size: 24px; margin-bottom: 10px; color: gold; }

        /* Recent Table */
        .recent-table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #1a1a1a; border-radius: 8px; overflow: hidden; }
        .recent-table td { padding: 10px; border-bottom: 1px solid #333; font-size: 13px; color: #ccc; }
        .badge { padding: 3px 6px; border-radius: 4px; font-size: 10px; text-transform: uppercase; font-weight: bold; }
        
        .bg-pending { background: #ffc107; color: black; }
        .bg-approved { background: #28a745; color: white; }
        .bg-rejected { background: #ff4d4d; color: white; }
    </style>
</head>
<body>

    <div class="admin-nav">
        <h3 style="color: gold;">Admin Panel</h3>
        <a href="logout.php" style="color: #ff4d4d; text-decoration: none; font-size: 14px;">
            <i class="fa-solid fa-power-off"></i> Logout
        </a>
    </div>

    <div class="container">
        
        <h3 style="margin-bottom: 15px;">Overview</h3>
        
        <div class="stat-card" style="border-left: 5px solid gold; margin-bottom: 15px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="color: #aaa; font-size: 12px;">Pending Requests</div>
                    <h1 style="color: #fff; margin: 5px 0;"><?php echo $pending_orders; ?></h1>
                </div>
                <i class="fa-solid fa-bell" style="font-size: 30px; color: gold; opacity: 0.5;"></i>
            </div>
            <?php if($pending_orders > 0): ?>
                <a href="orders.php?view=pending" style="color: gold; font-size: 12px; text-decoration: none;">View All &rarr;</a>
            <?php endif; ?>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <div style="color: #aaa; font-size: 11px;">Total Users</div>
                <h3><?php echo $total_users; ?></h3>
            </div>
            <div class="stat-card">
                <div style="color: #aaa; font-size: 11px;">Deposits (IN)</div>
                <h3 style="color: #28a745;">$<?php echo number_format($total_in); ?></h3>
            </div>
            <div class="stat-card">
                <div style="color: #aaa; font-size: 11px;">Withdraws (OUT)</div>
                <h3 style="color: #ff4d4d;">$<?php echo number_format($total_out); ?></h3>
            </div>
        </div>

        <h3 style="margin: 20px 0 10px 0;">Quick Actions</h3>
        <div class="menu-grid">
            <a href="orders.php" class="menu-item">
                <i class="fa-solid fa-list-check"></i>
                <div>Manage Orders</div>
            </a>
            <a href="users.php" class="menu-item">
                <i class="fa-solid fa-users"></i>
                <div>Manage Users</div>
            </a>
            <a href="referral.php" class="menu-item">
                <i class="fa-solid fa-sliders"></i>
                <div>Settings & Rates</div>
            </a>
            <a href="generate_pass.php" class="menu-item" target="_blank">
                <i class="fa-solid fa-key"></i>
                <div>Pass Tool</div>
            </a>
        </div>

        <h3 style="margin: 25px 0 10px 0;">Recent Activity</h3>
        <div style="overflow-x: auto;">
            <table class="recent-table">
                <?php foreach($recent_tx as $tx): ?>
                <tr>
                    <td>#<?php echo $tx['id']; ?></td>
                    <td>
                        <span style="font-weight: bold; text-transform: uppercase; color: <?php echo ($tx['type']=='buy'||$tx['type']=='deposit')?'#28a745':'#ff4d4d'; ?>">
                            <?php echo $tx['type']; ?>
                        </span>
                    </td>
                    <td style="font-family: monospace;">$<?php echo number_format($tx['amount'], 2); ?></td>
                    <td><span class="badge bg-<?php echo $tx['status']; ?>"><?php echo $tx['status']; ?></span></td>
                    <td style="color: #666; font-size: 10px;"><?php echo date('H:i', strtotime($tx['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

    </div>

</body>
</html>

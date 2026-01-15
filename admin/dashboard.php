<?php
/*
File: admin/dashboard.php
Purpose: Main Dashboard with Responsive Sidebar Layout
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

// 2. Pending Orders
$pending_orders = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn();

// 3. Total Deposit (Approved)
$stmt = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type IN ('buy','deposit') AND status = 'approved'");
$total_in = $stmt->fetchColumn() ?: '0.00';

// 4. Total Withdraw (Approved)
$stmt = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type IN ('sell','withdraw') AND status IN ('approved','completed')");
$total_out = $stmt->fetchColumn() ?: '0.00';

// 5. Recent Activity
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
        /* --- RESET & LAYOUT --- */
        body { margin: 0; padding: 0; background: #000; color: #ddd; font-family: sans-serif; display: flex; height: 100vh; overflow: hidden; }
        
        /* --- SIDEBAR STYLE --- */
        .sidebar {
            width: 250px;
            background: #111;
            border-right: 1px solid #333;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100%;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #333;
            text-align: center;
        }
        
        .sidebar-header h3 { margin: 0; color: gold; text-transform: uppercase; letter-spacing: 1px; }

        .sidebar-menu {
            padding: 20px 10px;
            flex: 1;
            overflow-y: auto;
        }

        .menu-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 5px;
            color: #aaa;
            text-decoration: none;
            border-radius: 8px;
            transition: 0.2s;
            font-size: 14px;
        }

        .menu-link i { margin-right: 10px; width: 20px; text-align: center; }
        
        .menu-link:hover, .menu-link.active {
            background: #222;
            color: gold;
            border-left: 3px solid gold;
        }

        /* --- MAIN CONTENT STYLE --- */
        .main-content {
            flex: 1;
            margin-left: 250px; /* Sidebar width */
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
            height: 100vh;
            overflow-y: auto;
        }

        /* --- TOP HEADER STYLE --- */
        .top-header {
            background: #111;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #333;
            position: sticky;
            top: 0;
            z-index: 900;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: gold;
            font-size: 24px;
            cursor: pointer;
            display: none; /* Hidden on Desktop */
        }

        /* --- DASHBOARD CARDS --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
        }

        .stat-card {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #333;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card h2 { margin: 10px 0 0 0; color: white; }
        .stat-card p { margin: 0; font-size: 12px; color: #888; text-transform: uppercase; }
        .stat-icon { position: absolute; right: 15px; top: 15px; font-size: 40px; color: gold; opacity: 0.1; }

        /* --- TABLE STYLE --- */
        .recent-section { padding: 0 20px 20px 20px; }
        .table-wrapper { background: #1a1a1a; border-radius: 12px; border: 1px solid #333; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #333; color: #ccc; font-size: 13px; }
        th { background: #222; color: gold; font-size: 11px; text-transform: uppercase; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .bg-pending { background: #ffc107; color: black; }
        .bg-approved { background: #28a745; color: white; }
        .bg-rejected { background: #ff4d4d; color: white; }

        /* --- RESPONSIVE MEDIA QUERY --- */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%); /* Hide sidebar by default on mobile */
            }
            
            .sidebar.open {
                transform: translateX(0); /* Show when active */
            }

            .main-content {
                margin-left: 0; /* Full width on mobile */
            }

            .toggle-btn {
                display: block; /* Show hamburger on mobile */
            }
            
            /* Overlay for mobile when sidebar is open */
            .overlay {
                display: none;
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.5); z-index: 999;
            }
            .overlay.active { display: block; }
        }
    </style>
</head>
<body>

    <div class="overlay" onclick="toggleSidebar()"></div>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Admin Panel</h3>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-link active">
                <i class="fa-solid fa-gauge"></i> Dashboard
            </a>
            <a href="users.php" class="menu-link">
                <i class="fa-solid fa-users"></i> Users Manager
            </a>
            <a href="orders.php" class="menu-link">
                <i class="fa-solid fa-list-check"></i> Manage Orders
            </a>
            <a href="referral.php" class="menu-link">
                <i class="fa-solid fa-sliders"></i> Settings & Rates
            </a>
            <a href="generate_pass.php" class="menu-link">
                <i class="fa-solid fa-key"></i> Password Tool
            </a>
            <div style="margin-top: 20px; border-top: 1px solid #333; padding-top: 10px;">
                <a href="logout.php" class="menu-link" style="color: #ff4d4d;">
                    <i class="fa-solid fa-power-off"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="toggle-btn" onclick="toggleSidebar()">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h3 style="margin: 0; color: white;">Overview</h3>
            </div>
            <div style="font-size: 12px; color: #888;">
                Welcome, Admin
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <p>Pending Orders</p>
                <h2 style="color: gold;"><?php echo $pending_orders; ?></h2>
                <i class="fa-solid fa-clock stat-icon"></i>
            </div>
            <div class="stat-card">
                <p>Total Users</p>
                <h2><?php echo $total_users; ?></h2>
                <i class="fa-solid fa-users stat-icon"></i>
            </div>
            <div class="stat-card">
                <p>Total Deposit (In)</p>
                <h2 style="color: #28a745;">$<?php echo number_format($total_in); ?></h2>
                <i class="fa-solid fa-arrow-down stat-icon" style="color: #28a745;"></i>
            </div>
            <div class="stat-card">
                <p>Total Withdraw (Out)</p>
                <h2 style="color: #ff4d4d;">$<?php echo number_format($total_out); ?></h2>
                <i class="fa-solid fa-arrow-up stat-icon" style="color: #ff4d4d;"></i>
            </div>
        </div>

        <div class="recent-section">
            <h4 style="color: gold; margin-bottom: 15px;">Live Activity Feed</h4>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
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
                            <td><?php echo date('d M, H:i', strtotime($tx['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recent_tx)): ?>
                            <tr><td colspan="5" style="text-align: center;">No activity yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.overlay');
            
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }
    </script>

</body>
</html>

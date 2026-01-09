<?php
/*
File: admin/users.php
Purpose: Manage Users (Block/Unblock)
*/
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

// --- Block/Unblock Logic ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $uid = intval($_GET['id']);
    $status = ($_GET['action'] == 'block') ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
    $stmt->execute([$status, $uid]);
    
    header("Location: users.php"); // Refresh page
    exit;
}

// Fetch Users (Newest First)
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 80px; background: #000; }
        .admin-nav { position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: #111; border-bottom: 1px solid gold; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 100; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #222; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #444; color: #ddd; font-size: 13px; }
        th { background: #333; color: gold; }
    </style>
</head>
<body>

    <div class="admin-nav">
        <a href="dashboard.php" style="color: gold; text-decoration: none;"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h3 style="color: white;">All Users</h3>
        <div></div>
    </div>

    <div class="container">
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Telegram ID</th>
                        <th>Blocked?</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                        <tr style="<?php echo $u['is_blocked'] ? 'opacity: 0.5;' : ''; ?>">
                            <td><?php echo $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['first_name']); ?></td>
                            <td><?php echo $u['telegram_id']; ?></td>
                            <td>
                                <?php if($u['is_blocked']): ?>
                                    <span style="color: red; font-weight: bold;">YES</span>
                                <?php else: ?>
                                    <span style="color: green;">NO</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($u['is_blocked']): ?>
                                    <a href="?action=unblock&id=<?php echo $u['id']; ?>" class="btn" style="padding: 5px 10px; width: auto; background: #28a745; color: white;">Unblock</a>
                                <?php else: ?>
                                    <a href="?action=block&id=<?php echo $u['id']; ?>" class="btn" style="padding: 5px 10px; width: auto; background: #ff4d4d; color: white;" onclick="return confirm('Block this user?');">Block</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>

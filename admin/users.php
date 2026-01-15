<?php
/*
File: admin/users.php
Purpose: Manage Users (Block, Unblock, & UPDATE BALANCE)
*/
session_start();
require_once '../includes/functions.php'; 

// 1. Admin Login Check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$message = "";

// 2. HANDLE BALANCE UPDATE (Credit/Debit)
if (isset($_POST['btn_update_balance'])) {
    $target_tg_id = cleanInput($_POST['target_tg_id']); // User ka Telegram ID
    $asset = cleanInput($_POST['asset']); // USDT, TON, etc.
    $action = cleanInput($_POST['action']); // credit or debit
    $amount = floatval($_POST['amount']);

    if ($amount > 0) {
        // Functions.php wala updateBalance use karenge
        if (updateBalance($pdo, $target_tg_id, $asset, $amount, $action)) {
            
            // Log bhi create kar lete hain taaki history rahe
            $desc = "Admin $action: $amount $asset";
            // Type 'admin_adjust' maan lete hain
            logTransaction($pdo, $target_tg_id, 'admin_adjust', $amount, $asset, $desc, 'completed');
            
            $message = "<div class='alert success'>Success! Balance Updated.</div>";
        } else {
            $message = "<div class='alert error'>Failed! Insufficient balance for debit?</div>";
        }
    } else {
        $message = "<div class='alert error'>Invalid Amount</div>";
    }
}

// 3. Block/Unblock Logic
if (isset($_GET['action']) && isset($_GET['id'])) {
    $uid = intval($_GET['id']);
    $status = ($_GET['action'] == 'block') ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
    $stmt->execute([$status, $uid]);
    header("Location: users.php"); 
    exit;
}

// 4. Search Logic
$search_query = "";
$sql = "SELECT * FROM users ORDER BY id DESC";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = cleanInput($_GET['search']);
    $search_query = "WHERE first_name LIKE '%$search%' OR telegram_id LIKE '%$search%' OR username LIKE '%$search%'";
    $sql = "SELECT * FROM users $search_query ORDER BY id DESC";
}

$users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 80px; background: #000; color: #ddd; }
        .admin-nav { position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: #111; border-bottom: 1px solid gold; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 100; }
        
        .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 10px; border: 1px solid #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #333; font-size: 13px; }
        th { background: #222; color: gold; text-transform: uppercase; font-size: 11px; }
        tr:hover { background: #222; }

        .user-info { display: flex; align-items: center; gap: 10px; }
        .u-pic { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .u-initials { width: 35px; height: 35px; border-radius: 50%; background: #0ecb81; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; }

        /* Alerts */
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .alert.success { background: #28a745; color: white; }
        .alert.error { background: #ff4d4d; color: white; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; justify-content: center; align-items: center; }
        .modal-content { background: #222; padding: 20px; border-radius: 10px; border: 1px solid gold; width: 90%; max-width: 400px; text-align: center; }
        .modal input, .modal select { width: 100%; padding: 10px; margin: 10px 0; background: #333; border: 1px solid #555; color: white; border-radius: 5px; }
    </style>
</head>
<body>

    <div class="admin-nav">
        <a href="dashboard.php" style="color: gold; text-decoration: none;"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h3 style="color: white;">Users Manager</h3>
        <div style="width: 50px;"></div>
    </div>

    <div class="container">
        
        <?php echo $message; ?>

        <form method="GET" style="display: flex; gap: 10px; margin-bottom: 20px;">
            <input type="text" name="search" placeholder="Search by Name or ID..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" style="padding: 10px; background: #222; border: 1px solid #444; color: white; width: 100%; border-radius: 5px;">
            <button type="submit" class="btn" style="width: auto; background: gold; color: black;"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User Profile</th>
                        <th>USDT Bal</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($users) > 0): ?>
                        <?php foreach($users as $u): 
                            $initials = strtoupper(substr($u['first_name'], 0, 1));
                            $full_name = htmlspecialchars($u['first_name'] . ' ' . $u['last_name']);
                            $balance = getUserBalance($pdo, $u['telegram_id'], 'USDT');
                        ?>
                        <tr style="<?php echo $u['is_blocked'] ? 'opacity: 0.5; background: #331111;' : ''; ?>">
                            <td>#<?php echo $u['id']; ?></td>
                            
                            <td>
                                <div class="user-info">
                                    <?php if(!empty($u['photo_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($u['photo_url']); ?>" class="u-pic">
                                    <?php else: ?>
                                        <div class="u-initials"><?php echo $initials; ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: bold; color: white;"><?php echo $full_name; ?></div>
                                        <div style="font-size: 10px; color: #888;">ID: <?php echo $u['telegram_id']; ?></div>
                                    </div>
                                </div>
                            </td>

                            <td style="color: gold; font-family: monospace;">
                                $<?php echo $balance; ?>
                            </td>

                            <td>
                                <?php echo $u['is_blocked'] ? '<span style="color:red">BLOCKED</span>' : '<span style="color:green">ACTIVE</span>'; ?>
                            </td>

                            <td>
                                <button onclick="openBalanceModal('<?php echo $u['telegram_id']; ?>', '<?php echo $full_name; ?>')" class="btn" style="padding: 5px 10px; width: auto; background: #007bff; color: white; font-size: 11px; margin-bottom: 5px;">
                                    <i class="fa-solid fa-coins"></i> Balance
                                </button>
                                
                                <br>

                                <?php if($u['is_blocked']): ?>
                                    <a href="?action=unblock&id=<?php echo $u['id']; ?>" style="color: #28a745; font-size: 11px;">Unblock</a>
                                <?php else: ?>
                                    <a href="?action=block&id=<?php echo $u['id']; ?>" style="color: #ff4d4d; font-size: 11px;" onclick="return confirm('Block this user?');">Block</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 30px;">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="balModal" class="modal">
        <div class="modal-content">
            <h3 style="color: gold; margin-bottom: 10px;">Manage Balance</h3>
            <p id="modalUserName" style="color: #aaa; margin-bottom: 15px;"></p>
            
            <form method="POST">
                <input type="hidden" name="target_tg_id" id="modalTgId">
                
                <label style="color:white; font-size: 12px; float: left;">Select Coin</label>
                <select name="asset">
                    <option value="USDT">USDT</option>
                    <option value="TON">TON</option>
                    <option value="BTC">BTC</option>
                    <option value="BNB">BNB</option>
                    <option value="TRX">TRX</option>
                </select>

                <label style="color:white; font-size: 12px; float: left;">Action</label>
                <select name="action">
                    <option value="credit">Credit (Add Money)</option>
                    <option value="debit">Debit (Remove Money)</option>
                </select>

                <label style="color:white; font-size: 12px; float: left;">Amount</label>
                <input type="number" name="amount" step="any" placeholder="Enter Amount" required>

                <button type="submit" name="btn_update_balance" class="btn btn-primary" style="margin-top: 10px;">Update Balance</button>
                <button type="button" class="btn" onclick="document.getElementById('balModal').style.display='none'" style="background: #333; margin-top: 5px;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openBalanceModal(tgId, name) {
            document.getElementById('modalTgId').value = tgId;
            document.getElementById('modalUserName').innerText = "User: " + name;
            document.getElementById('balModal').style.display = 'flex';
        }
    </script>

</body>
</html>

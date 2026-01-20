<?php
/*
File: admin/users.php
Purpose: Advanced User Manager with Dynamic Balance Modal, Invite Count & UI Fixes
*/
session_start();
require_once '../includes/functions.php'; 

// 1. Admin Login Check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$message = "";

// 2. HANDLE BALANCE UPDATE
if (isset($_POST['btn_update_balance'])) {
    $target_tg_id = cleanInput($_POST['target_tg_id']); 
    $asset = cleanInput($_POST['asset']); 
    $action = cleanInput($_POST['action']); 
    $amount = floatval($_POST['amount']);

    if ($amount > 0) {
        if (updateBalance($pdo, $target_tg_id, $asset, $amount, $action)) {
            $desc = "Admin $action: $amount $asset";
            logTransaction($pdo, $target_tg_id, 'admin_adjust', $amount, $asset, $desc, 'completed');
            $message = "<div class='alert success'><i class='fa-solid fa-check-circle'></i> Success! Balance Updated.</div>";
        } else {
            $message = "<div class='alert error'><i class='fa-solid fa-circle-xmark'></i> Failed! Insufficient wallet balance.</div>";
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

// Define Supported Assets (Isse Modal me list banegi)
$supported_assets = ['USDT', 'TON', 'BTC', 'BNB', 'TRX'];
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
        body { padding-top: 80px; background: #000; color: #ddd; font-family: 'Segoe UI', sans-serif; }
        
        /* Navbar */
        .admin-nav { position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: #111; border-bottom: 1px solid gold; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 100; }
        
        /* Table Styles */
        .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 12px; border: 1px solid #333; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        
        th { background: #222; color: gold; text-transform: uppercase; font-size: 11px; padding: 15px; letter-spacing: 1px; }
        td { padding: 12px 15px; border-bottom: 1px solid #2a2a2a; font-size: 13px; color: #ccc; vertical-align: middle; }
        tr:hover { background: #222; transition: 0.2s; }

        /* User Info Column */
        .user-info { display: flex; align-items: center; gap: 12px; }
        .u-pic { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 1px solid gold; }
        .u-initials { width: 35px; height: 35px; border-radius: 50%; background: #0ecb81; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; border: 1px solid #fff; }
        
        /* Single Line Name Fix with Ellipsis (...) */
        .name-box {
            max-width: 140px; 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis;
            font-weight: bold; 
            color: white;
        }

        /* Action Buttons (Pill Shape & Inline) */
        .action-group { display: flex; gap: 8px; align-items: center; }
        .btn-pill {
            padding: 6px 12px;
            border-radius: 50px; /* Pill Shape */
            font-size: 11px;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            border: none;
            transition: 0.3s;
        }
        .btn-blue { background: rgba(0, 123, 255, 0.2); color: #4dabf7; border: 1px solid #4dabf7; }
        .btn-blue:hover { background: #007bff; color: white; }
        
        .btn-red { background: rgba(255, 77, 77, 0.2); color: #ff6b6b; border: 1px solid #ff6b6b; }
        .btn-red:hover { background: #ff4d4d; color: white; }

        .btn-green { background: rgba(40, 167, 69, 0.2); color: #51cf66; border: 1px solid #51cf66; }
        .btn-green:hover { background: #28a745; color: white; }

        /* Alerts */
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-size: 14px; }
        .alert.success { background: rgba(40, 167, 69, 0.2); color: #51cf66; border: 1px solid #28a745; }
        .alert.error { background: rgba(255, 77, 77, 0.2); color: #ff6b6b; border: 1px solid #ff4d4d; }

        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .modal-content { background: #1a1a1a; padding: 25px; border-radius: 16px; border: 1px solid gold; width: 90%; max-width: 380px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .modal input, .modal select { width: 100%; padding: 12px; margin: 10px 0; background: #252525; border: 1px solid #444; color: white; border-radius: 8px; outline: none; }
        .modal input:focus, .modal select:focus { border-color: gold; }
    </style>
</head>
<body>

    <div class="admin-nav">
        <a href="dashboard.php" style="color: gold; text-decoration: none; font-size: 14px;">
            <i class="fa-solid fa-arrow-left"></i> Dashboard
        </a>
        <h3 style="color: white; margin: 0;">Users Manager</h3>
        <div style="width: 80px;"></div>
    </div>

    <div class="container">
        
        <?php echo $message; ?>

        <form method="GET" style="display: flex; gap: 10px; margin-bottom: 20px;">
            <input type="text" name="search" placeholder="Search by Name, ID or Username..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" style="padding: 12px; background: #1a1a1a; border: 1px solid #333; color: white; width: 100%; border-radius: 8px; outline: none;">
            <button type="submit" class="btn" style="width: auto; background: gold; color: black; border-radius: 8px; padding: 0 20px;">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User Profile</th>
                        <th>Total Fund</th>
                        <th>Invite</th> <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($users) > 0): ?>
                        <?php foreach($users as $u): 
                            // Initials
                            $initials = strtoupper(substr($u['first_name'], 0, 1));
                            $full_name = htmlspecialchars($u['first_name'] . ' ' . $u['last_name']);
                            
                            // Fetch USDT Balance for "Total Fund" Display
                            // Format: Standard number_format without extra padding
                            $main_bal_raw = getUserBalance($pdo, $u['telegram_id'], 'USDT');
                            $main_bal = number_format((float)$main_bal_raw, 2, '.', ''); 

                            // --- COUNT REFERRALS ---
                            $stmt_ref = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
                            $stmt_ref->execute([$u['telegram_id']]);
                            $invite_count = $stmt_ref->fetchColumn();

                            // --- DYNAMIC BALANCES FETCHING FOR MODAL ---
                            $stmt_w = $pdo->prepare("SELECT asset_symbol, balance FROM user_wallets WHERE user_id = ?");
                            $stmt_w->execute([$u['telegram_id']]);
                            $wallet_rows = $stmt_w->fetchAll(PDO::FETCH_ASSOC);
                            
                            $user_balances = [];
                            foreach($wallet_rows as $row) {
                                $user_balances[$row['asset_symbol']] = $row['balance'];
                            }
                            $balances_json = htmlspecialchars(json_encode($user_balances), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr style="<?php echo $u['is_blocked'] ? 'opacity: 0.5; background: rgba(50,0,0,0.3);' : ''; ?>">
                            <td style="color: #666;">#<?php echo $u['id']; ?></td>
                            
                            <td>
                                <div class="user-info">
                                    <?php if(!empty($u['photo_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($u['photo_url']); ?>" class="u-pic">
                                    <?php else: ?>
                                        <div class="u-initials"><?php echo $initials; ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="name-box" title="<?php echo $full_name; ?>">
                                            <?php echo $full_name; ?>
                                        </div>
                                        <div style="font-size: 10px; color: #888;">
                                            ID: <?php echo $u['telegram_id']; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td style="color: gold; font-family: monospace; font-size: 14px;">
                                $<?php echo $main_bal; ?>
                            </td>

                            <td style="color: #007bff; font-weight: bold; font-size: 14px;">
                                <?php echo $invite_count; ?>
                            </td>

                            <td>
                                <?php if($u['is_blocked']): ?>
                                    <span style="background: rgba(255,77,77,0.1); color: #ff4d4d; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold;">BLOCKED</span>
                                <?php else: ?>
                                    <span style="background: rgba(40,167,69,0.1); color: #28a745; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold;">ACTIVE</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="action-group">
                                    <button onclick="openBalanceModal('<?php echo $u['telegram_id']; ?>', '<?php echo htmlspecialchars($u['first_name']); ?>', '<?php echo $balances_json; ?>')" class="btn-pill btn-blue">
                                        <i class="fa-solid fa-wallet"></i> Balance
                                    </button>
                                    
                                    <?php if($u['is_blocked']): ?>
                                        <a href="?action=unblock&id=<?php echo $u['id']; ?>" class="btn-pill btn-green">
                                            <i class="fa-solid fa-lock-open"></i> Unblock
                                        </a>
                                    <?php else: ?>
                                        <a href="?action=block&id=<?php echo $u['id']; ?>" class="btn-pill btn-red" onclick="return confirm('Block this user?');">
                                            <i class="fa-solid fa-ban"></i> Block
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 30px; color: #666;">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="balModal" class="modal">
        <div class="modal-content">
            <h3 style="color: gold; margin-bottom: 5px;">Manage Wallet</h3>
            <p style="color: #aaa; font-size: 12px; margin-bottom: 20px;">
                User: <span id="modalUserName" style="color: white; font-weight: bold;"></span>
            </p>
            
            <form method="POST">
                <input type="hidden" name="target_tg_id" id="modalTgId">
                
                <div style="text-align: left; margin-bottom: 5px;">
                    <label style="color:#888; font-size: 11px;">Select Currency</label>
                </div>
                <select name="asset" id="assetSelect">
                    </select>

                <div style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <div style="text-align: left;"><label style="color:#888; font-size: 11px;">Action</label></div>
                        <select name="action">
                            <option value="credit">Add (+)</option>
                            <option value="debit">Deduct (-)</option>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <div style="text-align: left;"><label style="color:#888; font-size: 11px;">Amount</label></div>
                        <input type="number" name="amount" step="any" placeholder="0.00" required>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="button" class="btn" onclick="document.getElementById('balModal').style.display='none'" style="background: #333; color: white;">Cancel</button>
                    <button type="submit" name="btn_update_balance" class="btn" style="background: gold; color: black; font-weight: bold;">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Assets List (From PHP)
        const allAssets = <?php echo json_encode($supported_assets); ?>;

        function openBalanceModal(tgId, name, balancesJson) {
            document.getElementById('modalTgId').value = tgId;
            document.getElementById('modalUserName').innerText = name;
            
            // 1. User ke balances parse karein
            let userBals = {};
            try {
                userBals = JSON.parse(balancesJson);
            } catch(e) { console.error("JSON Error", e); }

            // 2. Select Dropdown ko Empty karein
            const select = document.getElementById('assetSelect');
            select.innerHTML = '';

            // 3. Har Coin ke liye Option banayein
            allAssets.forEach(coin => {
                let bal = userBals[coin] ? parseFloat(userBals[coin]).toFixed(4) : "0.0000";
                
                let option = document.createElement('option');
                option.value = coin;
                // Format: USDT (50.00 USDT)
                option.innerText = `${coin} (${bal} ${coin})`;
                
                select.appendChild(option);
            });

            // Show Modal
            document.getElementById('balModal').style.display = 'flex';
        }
    </script>

</body>
</html>

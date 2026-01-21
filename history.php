<?php
/*
File: history.php
Purpose: Transaction History (Supports Swap, Bank Details & Rejection)
*/

// 1. Security & Database Connection (Auto Block/Maintenance Check)
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$settings = getSettings($pdo);

// 2. Browser Testing ID
$tg_id = 123456789; 
if (isset($_GET['tg_id'])) $tg_id = cleanInput($_GET['tg_id']);

// 3. Get User (Updates Name/Photo automatically due to functions.php update)
$user = getOrCreateUser($pdo, $tg_id, "Guest", "guest");

// 4. Fetch User Transactions
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 50");
$stmt->execute([$user['telegram_id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function for badge colors
function getStatusColor($status) {
    if($status == 'approved' || $status == 'completed' || $status == 'success') return '#28a745'; // Green
    if($status == 'rejected' || $status == 'failed') return '#ff4d4d'; // Red
    return '#ffc107'; // Yellow (Pending)
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>History</title>
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    
    <style>
        .tx-card {
            background: #1e1e1e;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 12px;
            border: 1px solid #333;
        }
        .tx-details-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 8px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 11px;
            color: #ccc;
        }
        .tx-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>

    <div class="sticky-header">
        <div class="profile-section">
            <img src="<?php echo !empty($user['photo_url']) ? htmlspecialchars($user['photo_url']) : 'assets/images/user.png'; ?>" 
                 onerror="this.src='https://via.placeholder.com/40'" class="profile-pic" id="headerProfileImg">
            <div>
                <div class="user-name" id="headerUserName"><?php echo htmlspecialchars($user['first_name']); ?></div>
                <div style="font-size: 10px; color: gold;">Activity Log</div>
            </div>
        </div>
        <button class="settings-btn"><i class="fa-solid fa-file-invoice"></i></button>
    </div>

    <div class="container" style="margin-top: 20px; margin-bottom: 80px;">

        <div class="card" style="background: linear-gradient(135deg, #111 0%, #222 100%); border-left: 5px solid gold; padding: 20px;">
            <div style="font-size: 12px; color: #aaa;">Estimated Total Balance</div>
            <h2 style="color: #fff;">
                $<?php echo getUserBalance($pdo, $user['telegram_id'], 'USDT'); ?> 
            </h2>
        </div>

        <h3 style="margin-bottom: 15px; color: gold;">Recent Transactions</h3>

        <?php if(count($transactions) > 0): ?>
            <?php foreach($transactions as $tx): ?>
                <div class="tx-card">
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <div style="font-weight: bold; text-transform: uppercase; font-size: 14px; color: white;">
                            <?php 
                                // Icons based on type
                                if($tx['type'] == 'buy' || $tx['type'] == 'deposit') 
                                    echo '<i class="fa-solid fa-arrow-down" style="color:#28a745;"></i> ' . ucfirst($tx['type']);
                                elseif($tx['type'] == 'sell' || $tx['type'] == 'withdraw') 
                                    echo '<i class="fa-solid fa-arrow-up" style="color:#ff4d4d;"></i> ' . ucfirst($tx['type']);
                                elseif($tx['type'] == 'swap') 
                                    echo '<i class="fa-solid fa-repeat" style="color:#007bff;"></i> Swap';
                                elseif($tx['type'] == 'referral_bonus') 
                                    echo '<i class="fa-solid fa-gift" style="color:gold;"></i> Referral Reward';
                                else 
                                    echo ucfirst($tx['type']); 
                            ?>
                        </div>
                        <div style="font-size: 10px; color: #777;">
                            <?php echo date('d M, h:i A', strtotime($tx['created_at'])); ?>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-size: 16px; color: #fff; font-weight: bold;">
                            <?php echo number_format($tx['amount'], 4); ?> 
                            <span style="font-size: 11px; color: gold;"><?php echo $tx['asset_symbol']; ?></span>
                        </div>
                        <div style="padding: 3px 8px; border-radius: 5px; font-size: 10px; font-weight: bold; background: <?php echo getStatusColor($tx['status']); ?>; color: #000;">
                            <?php echo strtoupper($tx['status']); ?>
                        </div>
                    </div>

                    <?php if($tx['type'] == 'swap' && !empty($tx['description'])): ?>
                        <div class="tx-details-box">
                            <?php echo htmlspecialchars($tx['description']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if($tx['type'] == 'withdraw' && !empty($tx['wallet_address'])): ?>
                        <div class="tx-details-box">
                            <span style="color:#888;">To:</span> <?php echo substr($tx['wallet_address'], 0, 15) . '...'; ?>
                        </div>
                    <?php endif; ?>

                    <?php if($tx['type'] == 'sell'): ?>
                        <div class="tx-details-box">
                            <?php if($tx['payment_method'] == 'UPI'): ?>
                                <div><span style="color:#888;">Method:</span> UPI</div>
                                <div><span style="color:#888;">ID:</span> <?php echo htmlspecialchars($tx['upi_id']); ?></div>
                            <?php elseif($tx['payment_method'] == 'BANK'): ?>
                                <div><span style="color:#888;">Method:</span> Bank Transfer</div>
                                <div><span style="color:#888;">Bank:</span> <?php echo htmlspecialchars($tx['bank_name']); ?></div>
                                <div><span style="color:#888;">Acc:</span> <?php echo htmlspecialchars($tx['account_number']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if($tx['status'] == 'rejected' && !empty($tx['reject_reason'])): ?>
                        <div style="margin-top: 8px; border-top: 1px dashed #444; padding-top: 5px;">
                            <small style="color: #ff4d4d; font-size: 11px;">
                                <i class="fa-solid fa-circle-exclamation"></i> 
                                <b>Reason:</b> <?php echo htmlspecialchars($tx['reject_reason']); ?>
                            </small>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 5px; font-size: 9px; color: #555; display: flex; justify-content: space-between;">
                        <span>ID: #<?php echo $tx['id']; ?></span>
                        <?php if($tx['tx_hash']): ?> 
                            <span onclick="navigator.clipboard.writeText('<?php echo $tx['tx_hash']; ?>'); alert('Hash Copied')" style="cursor: pointer;">
                                Hash: <?php echo substr($tx['tx_hash'], 0, 6).'...'; ?> <i class="fa-regular fa-copy"></i>
                            </span>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center" style="padding: 40px; color: #666;">
                <i class="fa-solid fa-receipt" style="font-size: 40px; margin-bottom: 10px;"></i>
                <p>No transactions found.</p>
            </div>
        <?php endif; ?>

    </div>

    <div class="bottom-nav">
        <a href="index.php" class="nav-item">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>
        <a href="wallet.php" class="nav-item">
            <i class="fa-solid fa-wallet"></i>
            <span>Wallet</span>
        </a>
        <a href="invite.php" class="nav-item">
            <i class="fa-solid fa-user-plus"></i>
            <span>Invite</span>
        </a>
        <a href="history.php" class="nav-item active">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>History</span>
        </a>
    </div>

    <script>
        // TG Data Sync Logic
        const tg = window.Telegram.WebApp;
        tg.expand();
        
        if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
            const user = tg.initDataUnsafe.user;
            const fullName = user.first_name + (user.last_name ? ' ' + user.last_name : '');
            
            const nameEl = document.getElementById('headerUserName');
            const imgEl = document.getElementById('headerProfileImg');
            
            if(nameEl) nameEl.innerText = fullName;
            if(user.photo_url && imgEl) imgEl.src = user.photo_url;
        }
    </script>
</body>
</html>

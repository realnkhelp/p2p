<?php
/*
File: history.php
Purpose: Transaction History (With Rejection Reason)
*/
require_once 'includes/functions.php';
$settings = getSettings($pdo);

// Browser Testing ID
$tg_id = 123456789; 
if (isset($_GET['tg_id'])) $tg_id = cleanInput($_GET['tg_id']);

$user = getOrCreateUser($pdo, $tg_id, "Guest", "guest");

// --- Fetch User Transactions ---
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user['telegram_id']]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function for badge colors
function getStatusColor($status) {
    if($status == 'approved' || $status == 'completed') return '#28a745'; // Green
    if($status == 'rejected') return '#ff4d4d'; // Red
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
</head>
<body>

    <div class="sticky-header">
        <div class="profile-section">
            <img src="assets/images/user.png" onerror="this.src='https://via.placeholder.com/40'" class="profile-pic">
            <div>
                <div class="user-name"><?php echo $user['first_name']; ?></div>
                <div style="font-size: 10px; color: gold;">History</div>
            </div>
        </div>
        <button class="settings-btn"><i class="fa-solid fa-file-invoice"></i></button>
    </div>

    <div class="container" style="margin-top: 20px;">

        <div class="card" style="background: linear-gradient(135deg, #111 0%, #222 100%); border-left: 5px solid gold; padding: 20px;">
            <div style="font-size: 12px; color: #aaa;">Available Balance</div>
            <h2 style="color: #fff;">
                <?php echo getUserBalance($pdo, $user['id'], 'USDT'); ?> 
                <span style="font-size: 14px; color: gold;">USDT</span>
            </h2>
        </div>

        <h3 style="margin-bottom: 15px; color: gold;">Recent Transactions</h3>

        <?php if(count($transactions) > 0): ?>
            <?php foreach($transactions as $tx): ?>
                <div class="card" style="padding: 15px; margin-bottom: 10px; border: 1px solid #333;">
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <div style="font-weight: bold; text-transform: uppercase; font-size: 14px;">
                            <?php 
                                // Icons based on type
                                if($tx['type'] == 'buy' || $tx['type'] == 'deposit') echo '<i class="fa-solid fa-arrow-down text-green"></i> ';
                                elseif($tx['type'] == 'sell' || $tx['type'] == 'withdraw') echo '<i class="fa-solid fa-arrow-up text-red"></i> ';
                                elseif($tx['type'] == 'referral_bonus') echo '<i class="fa-solid fa-gift text-gold"></i> ';
                                echo $tx['type']; 
                            ?>
                        </div>
                        <div style="font-size: 10px; color: #777;">
                            <?php echo date('d M, h:i A', strtotime($tx['created_at'])); ?>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="font-size: 16px; color: #fff;">
                            <?php echo number_format($tx['amount'], 2); ?> <span style="font-size: 10px; color: #aaa;"><?php echo $tx['asset_symbol']; ?></span>
                        </div>
                        <div style="padding: 2px 8px; border-radius: 5px; font-size: 10px; font-weight: bold; background: <?php echo getStatusColor($tx['status']); ?>; color: #000;">
                            <?php echo strtoupper($tx['status']); ?>
                        </div>
                    </div>

                    <?php if($tx['status'] == 'rejected' && !empty($tx['reject_reason'])): ?>
                        <div style="margin-top: 10px; border-top: 1px dashed #444; padding-top: 5px;">
                            <small style="color: #ff4d4d; font-size: 11px;">
                                <b>Reason:</b> <?php echo htmlspecialchars($tx['reject_reason']); ?>
                            </small>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 5px; font-size: 9px; color: #555;">
                        ID: #<?php echo $tx['id']; ?> 
                        <?php if($tx['tx_hash']): ?> | Hash: <?php echo substr($tx['tx_hash'], 0, 8).'...'; ?><?php endif; ?>
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

</body>
</html>

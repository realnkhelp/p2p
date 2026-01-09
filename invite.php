<?php
/*
File: invite.php
Purpose: Referral System (Viral Growth)
*/
require_once 'includes/functions.php';
$settings = getSettings($pdo);

// Browser Testing ID
$tg_id = 123456789; 
if (isset($_GET['tg_id'])) $tg_id = cleanInput($_GET['tg_id']);

$user = getOrCreateUser($pdo, $tg_id, "Guest", "guest");

// --- 1. Referral Link Generate Karein ---
// Bot URL settings table se aayega
$bot_url = $settings['bot_url']; 
// Final Link: https://t.me/BotName?startapp=123456
$invite_link = $bot_url . "?startapp=" . $user['telegram_id'];

// --- 2. Stats Calculate Karein ---
// Total Referrals Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
$stmt->execute([$user['telegram_id']]);
$total_refs = $stmt->fetchColumn();

// Total Earnings (Referral Bonus)
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'referral_bonus'");
$stmt->execute([$user['telegram_id']]);
$total_earned = $stmt->fetchColumn();
if(!$total_earned) $total_earned = '0.00';

// --- 3. Referral List Fetch Karein (Recent 10) ---
$stmt = $pdo->prepare("SELECT first_name, created_at FROM users WHERE referred_by = ? ORDER BY id DESC LIMIT 10");
$stmt->execute([$user['telegram_id']]);
$my_referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Invite Friends</title>
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
                <div style="font-size: 10px; color: gold;">Refer & Earn</div>
            </div>
        </div>
        <button class="settings-btn"><i class="fa-solid fa-share-nodes"></i></button>
    </div>

    <div class="container" style="margin-top: 20px;">

        <div class="card" style="text-align: center; background: linear-gradient(135deg, #1a1a1a 0%, #333 100%); border: 1px solid gold;">
            <i class="fa-solid fa-users" style="font-size: 40px; color: gold; margin-bottom: 10px;"></i>
            
            <h2 style="color: #fff;">Invite Friends!</h2>
            <p style="color: #aaa; font-size: 13px; margin: 10px 0;">
                Share your link and earn 
                <span style="color: gold; font-weight: bold;"><?php echo $settings['referral_bonus_amount']; ?> USDT</span> 
                for every friend who joins & deposits.
            </p>

            <div class="invite-link-box" id="myLink">
                <?php echo $invite_link; ?>
            </div>

            <button class="btn btn-primary" onclick="copyLink()">
                <i class="fa-regular fa-copy"></i> Copy Link
            </button>
        </div>

        <div style="display: flex; gap: 15px; margin-bottom: 20px;">
            <div class="card" style="flex: 1; text-align: center; margin-bottom: 0; padding: 15px;">
                <div style="font-size: 12px; color: #aaa;">Total Referrals</div>
                <h2 style="color: #fff;"><?php echo $total_refs; ?></h2>
            </div>
            <div class="card" style="flex: 1; text-align: center; margin-bottom: 0; padding: 15px;">
                <div style="font-size: 12px; color: #aaa;">Total Earned</div>
                <h2 style="color: gold;"><?php echo number_format($total_earned, 2); ?></h2>
                <span style="font-size: 10px;">USDT</span>
            </div>
        </div>

        <h3 style="margin-bottom: 15px; color: gold;">My Referrals</h3>
        
        <?php if(count($my_referrals) > 0): ?>
            <?php foreach($my_referrals as $ref): ?>
                <div class="asset-row">
                    <div class="asset-left">
                        <div style="width: 35px; height: 35px; background: #333; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: gold; font-weight: bold;">
                            <?php echo strtoupper(substr($ref['first_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <div style="font-weight: bold;"><?php echo htmlspecialchars($ref['first_name']); ?></div>
                            <div style="font-size: 10px; color: #aaa;">Joined: <?php echo date('d M Y', strtotime($ref['created_at'])); ?></div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span class="text-green" style="font-size: 12px; font-weight: bold;">
                            +<?php echo $settings['referral_bonus_amount']; ?> USDT
                        </span>
                        <div style="font-size: 9px; color: #666;">(Pending Deposit)</div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center" style="color: #666; padding: 20px;">
                <i class="fa-regular fa-folder-open" style="font-size: 30px; margin-bottom: 10px;"></i>
                <p>No referrals yet. Share your link!</p>
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
        <a href="invite.php" class="nav-item active">
            <i class="fa-solid fa-user-plus"></i>
            <span>Invite</span>
        </a>
        <a href="history.php" class="nav-item">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>History</span>
        </a>
    </div>

    <script>
        function copyLink() {
            let linkText = document.getElementById("myLink").innerText;
            navigator.clipboard.writeText(linkText);
            
            // Telegram Popup
            if(window.Telegram.WebApp) {
                window.Telegram.WebApp.showPopup({
                    title: 'Copied!',
                    message: 'Referral link copied to clipboard.',
                    buttons: [{type: 'ok'}]
                });
            } else {
                alert("Link Copied!");
            }
        }
    </script>
</body>
</html>

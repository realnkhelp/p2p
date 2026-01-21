<?php
/*
File: invite.php
Purpose: Referral System (Protected with Block & Maintenance Check)
*/

// 1. Security & Database Connection (Auto Block/Maintenance Check)
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$settings = getSettings($pdo);

// 2. Settings & Limits
$referral_reward = isset($settings['referral_bonus_amount']) ? $settings['referral_bonus_amount'] : 0.50; 
$min_trade_req = isset($settings['referral_min_trade']) ? $settings['referral_min_trade'] : 50; 

// 3. User Setup (Browser Fallback)
$tg_id = 123456789; 
if (isset($_GET['tg_id'])) $tg_id = cleanInput($_GET['tg_id']);

$user = getOrCreateUser($pdo, $tg_id, "Guest", "guest");

// 4. Referral Link
$bot_url = isset($settings['bot_url']) ? $settings['bot_url'] : 'https://t.me/YourBot'; 
$invite_link = $bot_url . "?startapp=" . $user['telegram_id'];

// 5. Stats Calculation
// Total Referrals
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
$stmt->execute([$user['telegram_id']]);
$total_refs = $stmt->fetchColumn();

// Total Earned
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'referral_bonus'");
$stmt->execute([$user['telegram_id']]);
$total_earned = $stmt->fetchColumn();
if(!$total_earned) $total_earned = 0.00;

// 6. Fetch Referral List
$stmt = $pdo->prepare("
    SELECT first_name, last_name, photo_url, created_at, telegram_id
    FROM users 
    WHERE referred_by = ? 
    ORDER BY id DESC LIMIT 20
");
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
    
    <style>
        /* Stats Card Styling */
        .stats-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            margin-top: 20px;
        }
        .stat-card {
            flex: 1;
            background: #1e1e1e; 
            border: 1px solid #333;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .stat-label { font-size: 13px; color: #aaa; margin-bottom: 5px; }
        .stat-value { font-size: 22px; font-weight: bold; color: #007bff; }
        .stat-value.gold { color: gold; }

        /* Referral List Styling */
        .ref-list-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            background: rgba(255,255,255,0.02);
            margin-bottom: 10px;
            border-radius: 12px;
        }
        
        /* Avatar Logic */
        .ref-avatar-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
            border: 1px solid #444;
        }
        
        /* Fallback Initials Circle */
        .ref-avatar-initials {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin-right: 15px;
            background: #0ecb81; 
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
            border: 1px solid #fff;
        }

        .ref-info { flex: 1; }
        .ref-name { font-weight: bold; font-size: 15px; color: white; }
        .ref-date { font-size: 11px; color: #888; margin-top: 2px; }
        
        .reward-badge {
            background: #007bff;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,123,255,0.3);
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
                <div style="font-size: 10px; color: gold;">Refer & Earn</div>
            </div>
        </div>
        <button class="settings-btn"><i class="fa-solid fa-share-nodes"></i></button>
    </div>

    <div class="container" style="margin-top: 20px; margin-bottom: 80px;">

        <div class="card" style="text-align: center; border: 1px solid gold; background: linear-gradient(135deg, #222 0%, #111 100%);">
            <i class="fa-solid fa-gift" style="font-size: 40px; color: gold; margin-bottom: 10px;"></i>
            
            <h2 style="color: #fff; margin-bottom: 5px;">Invite Friends!</h2>
            
            <p style="color: #ccc; font-size: 13px; line-height: 1.6; margin: 10px 0;">
                Share your link and earn <b style="color: gold;"><?php echo $referral_reward; ?> USDT</b> for every friend who joins & deposits.
                <br>
                <span style="font-size: 11px; color: #888; display: block; margin-top: 5px;">
                    (<?php echo $min_trade_req; ?>$ buy sell anything reward claim)
                </span>
            </p>

            <div class="invite-link-box" id="myLink">
                <?php echo $invite_link; ?>
            </div>

            <button class="btn btn-primary" onclick="copyLink()">
                <i class="fa-regular fa-copy"></i> Copy Link
            </button>
        </div>

        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total Referrals</div>
                <div class="stat-value gold"><?php echo $total_refs; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Reward</div>
                <div class="stat-value">
                    <?php echo number_format($total_earned, 2); ?> USDT
                </div>
            </div>
        </div>

        <h3 style="margin: 20px 0 10px 0; color: gold;">Referrals List</h3>

        <div id="referralList">
            <?php if(count($my_referrals) > 0): ?>
                <?php foreach($my_referrals as $ref): 
                    // Data Setup
                    $full_name = htmlspecialchars($ref['first_name'] . ' ' . $ref['last_name']);
                    $join_date = date('d/m/Y', strtotime($ref['created_at']));
                    $display_amount = "0.00"; 
                    
                    // Avatar Logic
                    $photo_url = $ref['photo_url']; 
                    $show_image = !empty($photo_url); 
                    
                    $initials = strtoupper(substr($ref['first_name'], 0, 1));
                    if(!empty($ref['last_name'])) {
                        $initials .= strtoupper(substr($ref['last_name'], 0, 1));
                    }
                ?>
                <div class="ref-list-item">
                    
                    <?php if($show_image): ?>
                        <img src="<?php echo htmlspecialchars($photo_url); ?>" class="ref-avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="ref-avatar-initials" style="display:none;"><?php echo $initials; ?></div>
                    <?php else: ?>
                        <div class="ref-avatar-initials"><?php echo $initials; ?></div>
                    <?php endif; ?>
                    
                    <div class="ref-info">
                        <div class="ref-name"><?php echo $full_name; ?></div>
                        <div class="ref-date">Joined <?php echo $join_date; ?></div>
                    </div>
                    
                    <div class="reward-badge">
                        +<?php echo $display_amount; ?> USDT
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center" style="padding: 30px; opacity: 0.5;">
                    <i class="fa-solid fa-user-group" style="font-size: 30px; margin-bottom: 10px;"></i>
                    <p>No referrals yet.</p>
                </div>
            <?php endif; ?>
        </div>

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
        // TG WebApp Setup
        const tg = window.Telegram.WebApp;
        tg.expand();

        // Sync Header with TG Data
        if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
            const user = tg.initDataUnsafe.user;
            const fullName = user.first_name + (user.last_name ? ' ' + user.last_name : '');
            
            const nameEl = document.getElementById('headerUserName');
            const imgEl = document.getElementById('headerProfileImg');
            
            if(nameEl) nameEl.innerText = fullName;
            if(user.photo_url && imgEl) imgEl.src = user.photo_url;
        }

        function copyLink() {
            let linkText = document.getElementById("myLink").innerText;
            navigator.clipboard.writeText(linkText).then(() => {
                if(tg.showPopup) {
                    tg.showPopup({
                        title: 'Success',
                        message: 'Referral link copied!',
                        buttons: [{type: 'ok'}]
                    });
                } else {
                    alert("Link Copied!");
                }
            }).catch(err => {
                console.error('Copy failed', err);
                alert("Please copy manually.");
            });
        }
    </script>
</body>
</html>

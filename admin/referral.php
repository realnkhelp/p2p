<?php
/*
File: admin/referral.php
Purpose: Master Settings (Referral, Wallets, P2P Rates, Limits)
*/
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = "";

// --- Update Settings Logic ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. General & Referral
    $bonus = $_POST['referral_bonus'];
    $ref_min_trade = $_POST['referral_min_trade'];
    $bot_url = $_POST['bot_url'];
    $support_url = $_POST['support_url'];
    
    // 2. P2P & UPI
    $upi = $_POST['admin_upi'];
    $buy_margin = $_POST['p2p_buy_rate_margin'];
    $sell_margin = $_POST['p2p_sell_rate_margin'];
    
    // 3. Limits
    $min_dep = $_POST['min_deposit'];
    $min_wd = $_POST['min_withdraw'];
    $min_swap = $_POST['min_swap'];

    // 4. Admin Wallets (Save as JSON)
    $wallets = [
        'USDT_TRC20' => $_POST['w_trc20'],
        'USDT_BEP20' => $_POST['w_bep20'],
        'TON' => $_POST['w_ton'],
        'BTC' => $_POST['w_btc']
    ];
    $wallets_json = json_encode($wallets);

    // Update Query
    $sql = "UPDATE settings SET 
            referral_bonus_amount=?, referral_min_trade=?, bot_url=?, support_url=?,
            admin_upi=?, p2p_buy_rate_margin=?, p2p_sell_rate_margin=?,
            min_deposit_limit=?, min_withdraw_limit=?, min_swap_limit=?,
            admin_wallets_json=? 
            WHERE id=1";
            
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$bonus, $ref_min_trade, $bot_url, $support_url, $upi, $buy_margin, $sell_margin, $min_dep, $min_wd, $min_swap, $wallets_json])) {
        $msg = "✅ All Settings Updated Successfully!";
    } else {
        $msg = "❌ Error Updating Settings.";
    }
}

// Fetch Current Settings
$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);

// Decode Wallets JSON
$admin_wallets = json_decode($settings['admin_wallets_json'], true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Settings</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 80px; background: #000; color: #ddd; }
        .admin-nav { position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: #111; border-bottom: 1px solid gold; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 100; }
        
        .form-card { background: #1a1a1a; padding: 25px; border-radius: 10px; border: 1px solid #333; max-width: 800px; margin: 0 auto 50px auto; }
        .section-title { color: gold; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 15px; margin-top: 25px; font-size: 18px; }
        .row { display: flex; gap: 20px; }
        .col { flex: 1; }
        
        input { background: #222; border: 1px solid #444; color: white; padding: 10px; width: 100%; border-radius: 5px; margin-top: 5px; }
        label { font-size: 12px; color: #aaa; }
    </style>
</head>
<body>

    <div class="admin-nav">
        <a href="dashboard.php" style="color: gold; text-decoration: none;"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h3 style="color: white;">System Settings</h3>
        <div></div>
    </div>

    <div class="container">
        
        <?php if($msg): ?>
            <div style="background: #28a745; color: white; padding: 15px; margin-bottom: 20px; text-align: center; border-radius: 8px; font-weight: bold;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST">
                
                <div class="section-title" style="margin-top: 0;">Referral & General Links</div>
                <div class="row">
                    <div class="col">
                        <label>Referral Bonus (USDT)</label>
                        <input type="text" name="referral_bonus" value="<?php echo $settings['referral_bonus_amount']; ?>" required>
                    </div>
                    <div class="col">
                        <label>Min Trade to Unlock Reward ($)</label>
                        <input type="text" name="referral_min_trade" value="<?php echo $settings['referral_min_trade']; ?>" required>
                    </div>
                </div>
                <div class="row" style="margin-top: 10px;">
                    <div class="col">
                        <label>Bot Link (Start URL)</label>
                        <input type="text" name="bot_url" value="<?php echo $settings['bot_url']; ?>" required>
                    </div>
                    <div class="col">
                        <label>Support Username/URL</label>
                        <input type="text" name="support_url" value="<?php echo $settings['support_url']; ?>">
                    </div>
                </div>

                <div class="section-title">P2P Rates & Payment</div>
                <div class="form-group">
                    <label>Admin UPI ID (Recv Payment)</label>
                    <input type="text" name="admin_upi" value="<?php echo $settings['admin_upi']; ?>" required>
                </div>
                <div class="row">
                    <div class="col">
                        <label>Buy Margin (+ INR)</label>
                        <input type="number" step="0.01" name="p2p_buy_rate_margin" value="<?php echo $settings['p2p_buy_rate_margin']; ?>" required>
                        <small style="color: #666;">Ex: If 2, Rate = 92 (90+2)</small>
                    </div>
                    <div class="col">
                        <label>Sell Margin (- INR)</label>
                        <input type="number" step="0.01" name="p2p_sell_rate_margin" value="<?php echo $settings['p2p_sell_rate_margin']; ?>" required>
                        <small style="color: #666;">Ex: If 2, Rate = 88 (90-2)</small>
                    </div>
                </div>

                <div class="section-title">Admin Wallet Addresses (To Receive Funds)</div>
                <div class="row">
                    <div class="col">
                        <label>USDT (TRC20)</label>
                        <input type="text" name="w_trc20" value="<?php echo $admin_wallets['USDT_TRC20'] ?? ''; ?>" placeholder="TRC20 Address">
                    </div>
                    <div class="col">
                        <label>USDT (BEP20)</label>
                        <input type="text" name="w_bep20" value="<?php echo $admin_wallets['USDT_BEP20'] ?? ''; ?>" placeholder="BEP20 Address">
                    </div>
                </div>
                <div class="row" style="margin-top: 10px;">
                    <div class="col">
                        <label>TON Address</label>
                        <input type="text" name="w_ton" value="<?php echo $admin_wallets['TON'] ?? ''; ?>" placeholder="TON Address">
                    </div>
                    <div class="col">
                        <label>BTC Address</label>
                        <input type="text" name="w_btc" value="<?php echo $admin_wallets['BTC'] ?? ''; ?>" placeholder="BTC Address">
                    </div>
                </div>

                <div class="section-title">Global Limits ($/USDT)</div>
                <div class="row">
                    <div class="col">
                        <label>Min Deposit</label>
                        <input type="number" step="0.1" name="min_deposit" value="<?php echo $settings['min_deposit_limit']; ?>" required>
                    </div>
                    <div class="col">
                        <label>Min Withdraw</label>
                        <input type="number" step="0.1" name="min_withdraw" value="<?php echo $settings['min_withdraw_limit']; ?>" required>
                    </div>
                    <div class="col">
                        <label>Min Swap</label>
                        <input type="number" step="0.1" name="min_swap" value="<?php echo $settings['min_swap_limit']; ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 25px; width: 100%; padding: 15px; font-size: 16px;">
                    <i class="fa-solid fa-save"></i> Save All Settings
                </button>
            </form>
        </div>
    </div>

</body>
</html>

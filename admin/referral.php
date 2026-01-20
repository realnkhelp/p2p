<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = "";

if (isset($_POST['btn_update_settings'])) {
    $bonus = $_POST['referral_bonus'];
    $ref_min_trade = $_POST['referral_min_trade'];
    $bot_url = $_POST['bot_url'];
    $support_url = $_POST['support_url'];
    
    $m_mode = $_POST['maintenance_mode'];
    $m_msg = $_POST['maintenance_message'];
    $m_date = $_POST['maintenance_date'];
    $m_time = $_POST['maintenance_time'];

    $upi = $_POST['admin_upi'];
    $buy_rate = $_POST['p2p_buy_rate'];   
    $sell_rate = $_POST['p2p_sell_rate']; 
    
    $min_dep = $_POST['min_deposit'];
    $min_wd = $_POST['min_withdraw'];
    $min_swap = $_POST['min_swap'];

    $sql = "UPDATE settings SET 
            referral_bonus_amount=?, referral_min_trade=?, bot_url=?, support_url=?,
            maintenance_mode=?, maintenance_message=?, maintenance_end_date=?, maintenance_end_time=?,
            admin_upi=?, p2p_buy_rate=?, p2p_sell_rate=?,
            min_deposit_limit=?, min_withdraw_limit=?, min_swap_limit=? 
            WHERE id=1";
            
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([
        $bonus, $ref_min_trade, $bot_url, $support_url, 
        $m_mode, $m_msg, $m_date, $m_time,
        $upi, $buy_rate, $sell_rate, 
        $min_dep, $min_wd, $min_swap
    ])) {
        $msg = "<div class='alert success'>✅ Global Settings Updated!</div>";
    } else {
        $msg = "<div class='alert error'>❌ Error Updating Settings.</div>";
    }
}

if (isset($_POST['btn_add_asset'])) {
    $net = htmlspecialchars(strip_tags(trim($_POST['network_name'])));
    $addr = htmlspecialchars(strip_tags(trim($_POST['wallet_address'])));
    
    if(!empty($net) && !empty($addr)){
        $pdo->prepare("INSERT INTO admin_wallets (network_name, wallet_address) VALUES (?, ?)")->execute([$net, $addr]);
        $msg = "<div class='alert success'>New Asset Added Successfully!</div>";
    }
}

if (isset($_POST['btn_edit_asset'])) {
    $id = intval($_POST['edit_id']);
    $net = htmlspecialchars(strip_tags(trim($_POST['edit_network'])));
    $addr = htmlspecialchars(strip_tags(trim($_POST['edit_address'])));
    
    $pdo->prepare("UPDATE admin_wallets SET network_name=?, wallet_address=? WHERE id=?")->execute([$net, $addr, $id]);
    $msg = "<div class='alert success'>Asset Updated Successfully!</div>";
}

if (isset($_GET['del_asset'])) {
    $id = intval($_GET['del_asset']);
    $pdo->prepare("DELETE FROM admin_wallets WHERE id=?")->execute([$id]);
    header("Location: referral.php"); exit;
}

if (isset($_GET['toggle_asset'])) {
    $id = intval($_GET['toggle_asset']);
    $curr = intval($_GET['s']); 
    $new = $curr ? 0 : 1; 
    $pdo->prepare("UPDATE admin_wallets SET is_active=? WHERE id=?")->execute([$new, $id]);
    header("Location: referral.php"); exit;
}

$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$assets = $pdo->query("SELECT * FROM admin_wallets ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
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
        
        .container { max-width: 1200px; margin: auto; padding: 15px; }
        .grid-layout { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; }
        @media(max-width: 900px) { .grid-layout { grid-template-columns: 1fr; } }

        .card { background: #1a1a1a; padding: 20px; border-radius: 12px; border: 1px solid #333; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        .section-title { color: gold; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 15px; font-size: 16px; display: flex; align-items: center; gap: 10px; font-weight: bold; }
        
        .row { display: flex; gap: 15px; }
        .col { flex: 1; }
        
        input, select { background: #252525; border: 1px solid #444; color: white; padding: 12px; width: 100%; border-radius: 8px; margin-top: 5px; outline: none; transition: 0.3s; }
        input:focus, select:focus { border-color: gold; }
        label { font-size: 12px; color: #aaa; font-weight: 500; }

        .alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-weight: bold; }
        .alert.success { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
        .alert.error { background: rgba(255, 77, 77, 0.2); color: #ff4d4d; border: 1px solid #ff4d4d; }

        .asset-item { display: flex; justify-content: space-between; align-items: center; background: #222; padding: 15px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #333; transition: 0.2s; }
        .asset-item:hover { border-color: #555; }
        
        .asset-info h4 { margin: 0; color: #fff; font-size: 14px; }
        .asset-addr { font-size: 11px; color: #888; font-family: monospace; display: block; margin-top: 5px; word-break: break-all; }

        .action-group { display: flex; gap: 8px; }
        .circle-btn {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            border: none; cursor: pointer; color: white;
            text-decoration: none; font-size: 14px;
            transition: 0.2s;
        }
        .circle-btn:hover { transform: scale(1.1); }
        .btn-edit { background: #007bff; }
        .btn-del { background: #dc3545; }
        .btn-on { background: #28a745; }
        .btn-off { background: #6c757d; }

        .m-active { border: 1px solid #ff4d4d !important; background: rgba(255, 77, 77, 0.1) !important; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
    </style>
</head>
<body>

    <div class="admin-nav">
        <a href="dashboard.php" style="color: gold; text-decoration: none;"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h3 style="color: white;">Master Settings</h3>
        <div></div>
    </div>

    <div class="container">
        <?php echo $msg; ?>

        <div class="grid-layout">
            
            <div>
                <form method="POST">
                    
                    <div class="card">
                        <div class="section-title"><i class="fa-solid fa-money-bill-transfer"></i> P2P Rates (Direct Price)</div>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Admin UPI ID (Recv Payment)</label>
                            <input type="text" name="admin_upi" value="<?php echo htmlspecialchars($settings['admin_upi']); ?>" required>
                        </div>
                        <div class="row">
                            <div class="col">
                                <label style="color: #28a745;">Buy Price (INR)</label>
                                <input type="number" step="0.01" name="p2p_buy_rate" value="<?php echo htmlspecialchars($settings['p2p_buy_rate']); ?>" required>
                            </div>
                            <div class="col">
                                <label style="color: #ff4d4d;">Sell Price (INR)</label>
                                <input type="number" step="0.01" name="p2p_sell_rate" value="<?php echo htmlspecialchars($settings['p2p_sell_rate']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="section-title"><i class="fa-solid fa-sliders"></i> Global Limits</div>
                        <div class="row">
                            <div class="col"><label>Min Deposit</label><input type="number" step="0.1" name="min_deposit" value="<?php echo htmlspecialchars($settings['min_deposit_limit']); ?>"></div>
                            <div class="col"><label>Min Withdraw</label><input type="number" step="0.1" name="min_withdraw" value="<?php echo htmlspecialchars($settings['min_withdraw_limit']); ?>"></div>
                            <div class="col"><label>Min Swap</label><input type="number" step="0.1" name="min_swap" value="<?php echo htmlspecialchars($settings['min_swap_limit']); ?>"></div>
                        </div>
                    </div>

                    <div class="card <?php echo $settings['maintenance_mode'] ? 'm-active' : ''; ?>">
                        <div class="section-title"><i class="fa-solid fa-screwdriver-wrench"></i> Maintenance Mode</div>
                        <div class="row">
                            <div class="col" style="flex: 0 0 100px;">
                                <label>Mode</label>
                                <select name="maintenance_mode" style="border-color: <?php echo $settings['maintenance_mode'] ? '#ff4d4d' : '#444'; ?>;">
                                    <option value="0" <?php echo $settings['maintenance_mode']==0?'selected':''; ?>>OFF</option>
                                    <option value="1" <?php echo $settings['maintenance_mode']==1?'selected':''; ?>>ON</option>
                                </select>
                            </div>
                            <div class="col"><label>Message (Wait Text)</label><input type="text" name="maintenance_message" value="<?php echo htmlspecialchars($settings['maintenance_message']); ?>"></div>
                        </div>
                        <div class="row" style="margin-top: 10px;">
                            <div class="col"><label>End Date</label><input type="date" name="maintenance_date" value="<?php echo htmlspecialchars($settings['maintenance_end_date']); ?>"></div>
                            <div class="col"><label>End Time</label><input type="time" name="maintenance_time" value="<?php echo htmlspecialchars($settings['maintenance_end_time']); ?>"></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="section-title"><i class="fa-solid fa-users"></i> Referral System</div>
                        <div class="row">
                            <div class="col"><label>Bonus (USDT)</label><input type="text" name="referral_bonus" value="<?php echo htmlspecialchars($settings['referral_bonus_amount']); ?>"></div>
                            <div class="col"><label>Min Trade ($)</label><input type="text" name="referral_min_trade" value="<?php echo htmlspecialchars($settings['referral_min_trade']); ?>"></div>
                        </div>
                        <div style="margin-top: 10px;"><label>Bot Link</label><input type="text" name="bot_url" value="<?php echo htmlspecialchars($settings['bot_url']); ?>"></div>
                        <div style="margin-top: 10px;"><label>Support Link</label><input type="text" name="support_url" value="<?php echo htmlspecialchars($settings['support_url']); ?>"></div>
                    </div>

                    <button type="submit" name="btn_update_settings" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 16px; margin-bottom: 30px;">
                        <i class="fa-solid fa-save"></i> Update Settings
                    </button>
                </form>
            </div>

            <div>
                <div class="card">
                    <div class="section-title">
                        <i class="fa-solid fa-wallet"></i> Manage Assets (Deposit & Withdraw)
                    </div>
                    
                    <form method="POST" style="background: #222; padding: 15px; border-radius: 8px; border: 1px dashed #444; margin-bottom: 20px;">
                        <label style="color: gold;">Add New Asset</label>
                        <input type="text" name="network_name" placeholder="Name (e.g. USDT BEP20, TON)" required>
                        <input type="text" name="wallet_address" placeholder="Admin Address (For Deposit)" required>
                        
                        <button type="submit" name="btn_add_asset" class="btn" style="background: gold; color: black; margin-top: 10px; width: 100%; padding: 10px; font-weight: bold;">
                            <i class="fa-solid fa-plus"></i> Add Asset
                        </button>
                    </form>

                    <div style="max-height: 600px; overflow-y: auto;">
                        <?php if(count($assets) > 0): ?>
                            <?php foreach($assets as $a): ?>
                            <div class="asset-item" style="opacity: <?php echo $a['is_active'] ? '1' : '0.5'; ?>">
                                <div class="asset-info">
                                    <h4><?php echo htmlspecialchars($a['network_name']); ?></h4>
                                    <span class="asset-addr"><?php echo htmlspecialchars($a['wallet_address']); ?></span>
                                </div>
                                
                                <div class="action-group">
                                    <a href="?toggle_asset=<?php echo $a['id']; ?>&s=<?php echo $a['is_active']; ?>" 
                                       class="circle-btn <?php echo $a['is_active'] ? 'btn-on' : 'btn-off'; ?>" 
                                       title="<?php echo $a['is_active'] ? 'Disable' : 'Enable'; ?>">
                                       <i class="fa-solid fa-power-off"></i>
                                    </a>
                                    
                                    <button onclick="openEditModal(<?php echo $a['id']; ?>, '<?php echo $a['network_name']; ?>', '<?php echo $a['wallet_address']; ?>')" class="circle-btn btn-edit" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    
                                    <a href="?del_asset=<?php echo $a['id']; ?>" class="circle-btn btn-del" onclick="return confirm('Delete this asset?');" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; color: #666; padding: 20px;">No assets added yet.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="alert" style="background: rgba(0, 123, 255, 0.1); border: 1px solid #007bff; color: #4dabf7; text-align: left; font-size: 11px; font-weight: normal;">
                    <i class="fa-solid fa-circle-info"></i> <b>Note:</b> These assets will appear in both <b>Deposit</b> and <b>Withdraw</b> dropdowns.
                </div>
            </div>

        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="card" style="width: 350px; text-align: center; border: 1px solid gold;">
            <h3 style="color: gold; margin-bottom: 15px;">Edit Asset</h3>
            <form method="POST">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <label style="text-align: left; display: block;">Network Name</label>
                <input type="text" name="edit_network" id="edit_network" required>
                
                <label style="text-align: left; display: block; margin-top: 10px;">Wallet Address</label>
                <input type="text" name="edit_address" id="edit_address" required>

                <button type="submit" name="btn_edit_asset" class="btn btn-primary" style="margin-top: 15px; width: 100%;">Update Asset</button>
                <button type="button" class="btn" style="background: #333; margin-top: 10px; width: 100%;" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, name, addr) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_network').value = name;
            document.getElementById('edit_address').value = addr;
            document.getElementById('editModal').style.display = 'flex';
        }
    </script>

</body>
</html>

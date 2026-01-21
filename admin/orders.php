<?php
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = cleanInput($_POST['action']);
    $tx_id = intval($_POST['tx_id']);

    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$tx_id]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tx) {
        if ($action == 'approve' && $tx['status'] == 'pending') {
            if ($tx['type'] == 'buy' || $tx['type'] == 'deposit') {
                updateBalance($pdo, $tx['user_id'], $tx['asset_symbol'], $tx['amount'], 'credit');
                checkReferralReward($pdo, $tx['user_id'], $tx['amount']);
            }
            $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?")->execute([$tx_id]);
            $msg = "<div class='alert success'>Order #$tx_id Approved!</div>";
        } 
        elseif ($action == 'reject' && $tx['status'] == 'pending') {
            $reason = cleanInput($_POST['reason']);
            if ($tx['type'] == 'sell' || $tx['type'] == 'withdraw' || $tx['type'] == 'swap') {
                updateBalance($pdo, $tx['user_id'], $tx['asset_symbol'], $tx['amount'], 'credit'); 
            }
            $pdo->prepare("UPDATE transactions SET status = 'rejected', reject_reason = ? WHERE id = ?")->execute([$reason, $tx_id]);
            $msg = "<div class='alert error'>Order #$tx_id Rejected!</div>";
        }
        elseif ($action == 'delete') {
            $pdo->prepare("DELETE FROM transactions WHERE id = ?")->execute([$tx_id]);
            $msg = "<div class='alert error'>Transaction #$tx_id Deleted Permanently!</div>";
        }
    }
}

function checkReferralReward($pdo, $user_tg_id, $deposit_amount) {
    $settings = getSettings($pdo);
    $min_trade = $settings['referral_min_trade'];
    $bonus_amt = $settings['referral_bonus_amount'];

    if ($deposit_amount >= $min_trade) {
        $u = $pdo->prepare("SELECT referred_by FROM users WHERE telegram_id = ?");
        $u->execute([$user_tg_id]);
        $referrer_id = $u->fetchColumn();

        if ($referrer_id) {
            $chk = $pdo->prepare("SELECT id FROM transactions WHERE user_id = ? AND type = 'referral_bonus' AND description LIKE ?");
            $chk->execute([$referrer_id, "%$user_tg_id%"]);
            if (!$chk->fetch()) {
                updateBalance($pdo, $referrer_id, 'USDT', $bonus_amt, 'credit');
                $desc = "Referral Bonus for User: $user_tg_id";
                logTransaction($pdo, $referrer_id, 'referral_bonus', $bonus_amt, 'USDT', $desc, 'completed');
            }
        }
    }
}

$view = isset($_GET['view']) ? cleanInput($_GET['view']) : 'pending';
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';

$sql = "SELECT * FROM transactions WHERE 1=1 ";

if ($view == 'pending') {
    $sql .= "AND status = 'pending' ";
} elseif ($view == 'rejected') {
    $sql .= "AND status = 'rejected' ";
} elseif ($view == 'history') {
    $sql .= "AND status != 'pending' ";
}

if (!empty($search)) {
    $sql .= "AND (user_id LIKE '%$search%' OR tx_hash LIKE '%$search%' OR amount LIKE '%$search%') ";
}

$sql .= "ORDER BY id DESC";
$orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 80px; background: #000; color: #ddd; font-family: 'Segoe UI', sans-serif; }
        .admin-nav { position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: #111; border-bottom: 1px solid gold; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 100; }
        
        .container { max-width: 100%; padding: 15px; }
        
        .filters { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
        .tab-group { display: flex; gap: 5px; background: #222; padding: 5px; border-radius: 8px; border: 1px solid #333; }
        .tab-btn { padding: 8px 16px; border-radius: 6px; text-decoration: none; color: #aaa; font-size: 13px; font-weight: bold; transition: 0.3s; }
        .tab-btn.active { background: gold; color: black; }
        
        .search-box { display: flex; gap: 5px; }
        .search-input { background: #222; border: 1px solid #333; padding: 8px; color: white; border-radius: 6px; outline: none; }
        .search-btn { background: #333; border: 1px solid #444; color: white; padding: 8px 12px; border-radius: 6px; cursor: pointer; }

        .table-container { overflow-x: auto; background: #1a1a1a; border-radius: 10px; border: 1px solid #333; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        table { width: 100%; border-collapse: collapse; white-space: nowrap; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #2a2a2a; font-size: 13px; }
        th { background: #222; color: gold; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; }
        tr:hover { background: #222; }

        .limit-text { max-width: 150px; overflow: hidden; text-overflow: ellipsis; display: inline-block; vertical-align: middle; }
        .copy-btn { color: gold; cursor: pointer; margin-left: 8px; font-size: 14px; transition: 0.2s; }
        .copy-btn:hover { transform: scale(1.2); color: white; }

        .badge { padding: 5px 10px; border-radius: 50px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .bg-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid #ffc107; }
        .bg-approved { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
        .bg-rejected { background: rgba(255, 77, 77, 0.2); color: #ff4d4d; border: 1px solid #ff4d4d; }

        .action-group { display: flex; gap: 8px; align-items: center; }
        .circle-btn { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; color: white; font-size: 14px; transition: 0.2s; }
        .circle-btn:hover { transform: scale(1.1); }
        .btn-check { background: #28a745; }
        .btn-cross { background: #ff4d4d; }
        .btn-trash { background: #444; border: 1px solid #666; }
        .btn-trash:hover { background: #ff0000; border-color: red; }

        .alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; text-align: center; font-weight: bold; }
        .alert.success { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
        .alert.error { background: rgba(255, 77, 77, 0.2); color: #ff4d4d; border: 1px solid #ff4d4d; }
    </style>
</head>
<body>

    <div class="admin-nav">
        <a href="dashboard.php" style="color: gold; text-decoration: none;"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h3 style="color: white;">Orders Manager</h3>
        <div style="width: 50px;"></div>
    </div>

    <div class="container">
        
        <?php echo $msg; ?>

        <div class="filters">
            <div class="tab-group">
                <a href="?view=pending" class="tab-btn <?php echo $view=='pending'?'active':''; ?>">Pending</a>
                <a href="?view=history" class="tab-btn <?php echo $view=='history'?'active':''; ?>">History</a>
                <a href="?view=rejected" class="tab-btn <?php echo $view=='rejected'?'active':''; ?>">Rejected</a>
            </div>
            
            <form method="GET" class="search-box">
                <input type="hidden" name="view" value="<?php echo $view; ?>">
                <input type="text" name="search" class="search-input" placeholder="Search ID, Hash..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID / UID</th>
                        <th>Type / Asset</th>
                        <th>Amount</th>
                        <th>Details (Hash/Addr/Bank)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($orders) > 0): ?>
                        <?php foreach($orders as $row): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="color: white; font-weight: bold;">#<?php echo $row['id']; ?></span>
                                    <span style="color: #666;">|</span>
                                    <span style="color: gold; font-family: monospace;"><?php echo $row['user_id']; ?></span>
                                </div>
                            </td>
                            
                            <td>
                                <span style="font-weight: bold; text-transform: uppercase; color: <?php echo ($row['type']=='buy'||$row['type']=='deposit')?'#28a745':'#ff4d4d'; ?>">
                                    <?php echo $row['type']; ?>
                                </span>
                                <span style="font-size: 11px; background: #333; padding: 2px 6px; border-radius: 4px; margin-left: 5px;"><?php echo $row['asset_symbol']; ?></span>
                            </td>

                            <td style="font-weight: bold; font-family: monospace; color: white;">
                                <?php echo number_format($row['amount'], 4); ?>
                            </td>

                            <td>
                                <?php 
                                    $details = "";
                                    if(($row['type'] == 'buy' || $row['type'] == 'deposit') && $row['tx_hash']) $details = $row['tx_hash'];
                                    elseif($row['type'] == 'withdraw') $details = $row['wallet_address'];
                                    elseif($row['type'] == 'sell') {
                                        if($row['payment_method'] == 'UPI') $details = $row['upi_id'];
                                        else $details = "Acc: " . $row['account_number'] . " | IFSC: " . $row['ifsc_code'];
                                    }
                                ?>
                                <?php if($details): ?>
                                    <span class="limit-text" title="<?php echo htmlspecialchars($details); ?>"><?php echo htmlspecialchars($details); ?></span>
                                    <i class="fa-regular fa-copy copy-btn" onclick="copyData('<?php echo htmlspecialchars($details); ?>')"></i>
                                <?php else: ?>
                                    <span style="color: #444;">-</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge bg-<?php echo $row['status']; ?>">
                                    <?php echo strtoupper($row['status']); ?>
                                </span>
                            </td>

                            <td>
                                <div class="action-group">
                                    <?php if($row['status'] == 'pending'): ?>
                                        <form method="POST" onsubmit="return confirm('Approve Order?');" style="display:inline;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="tx_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="circle-btn btn-check" title="Approve"><i class="fa-solid fa-check"></i></button>
                                        </form>

                                        <button class="circle-btn btn-cross" onclick="openRejectModal(<?php echo $row['id']; ?>)" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                                    <?php endif; ?>

                                    <form method="POST" onsubmit="return confirm('⚠️ DELETE PERMANENTLY? This will remove it from user history too.');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="tx_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="circle-btn btn-trash" title="Delete Forever"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 30px; color: #666;">No Orders Found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="rejectModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:2000; align-items:center; justify-content:center;">
        <div class="card" style="width: 300px; padding: 25px; border: 1px solid #ff4d4d; background: #1a1a1a; border-radius: 12px; text-align: center;">
            <i class="fa-solid fa-circle-exclamation" style="font-size: 40px; color: #ff4d4d; margin-bottom: 15px;"></i>
            <h3 style="color: white; margin-bottom: 5px;">Reject Order</h3>
            <p style="color: #888; font-size: 12px; margin-bottom: 15px;">Amount will be refunded to user wallet.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="tx_id" id="rejectTxId">
                
                <input type="text" name="reason" placeholder="Reason (e.g. Invalid Hash)" required 
                       style="width: 100%; padding: 12px; margin-bottom: 15px; background: #222; border: 1px solid #444; color: white; border-radius: 8px; outline: none;">

                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn" onclick="document.getElementById('rejectModal').style.display='none'" style="flex: 1; background: #333; color: white; padding: 10px; border: none; border-radius: 8px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn" style="flex: 1; background: #ff4d4d; color: white; padding: 10px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(id) {
            document.getElementById('rejectTxId').value = id;
            document.getElementById('rejectModal').style.display = 'flex';
        }

        function copyData(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert("Copied: " + text);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }
    </script>
</body>
</html>

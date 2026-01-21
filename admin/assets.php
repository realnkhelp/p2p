<?php
/*
File: admin/assets.php
Purpose: Manage Wallet Assets (Coins, Icons, Networks, Status)
*/
session_start();
require_once '../includes/db_connect.php';

// 1. Security Check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = "";

// --- FUNCTION: Clean Input ---
function cleanInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// --- A. ADD NEW ASSET ---
if (isset($_POST['btn_add_asset'])) {
    $name = cleanInput($_POST['name']);     // e.g. Bitcoin
    $symbol = cleanInput($_POST['symbol']); // e.g. BTC (For Binance API)
    $network = cleanInput($_POST['network']); // e.g. BEP20
    $icon = cleanInput($_POST['icon_url']);
    
    if(!empty($name) && !empty($symbol)) {
        $stmt = $pdo->prepare("INSERT INTO assets (name, symbol, network, icon_url, is_active) VALUES (?, ?, ?, ?, 1)");
        if($stmt->execute([$name, $symbol, $network, $icon])) {
            $msg = "<div class='alert success'>Asset Added Successfully!</div>";
        } else {
            $msg = "<div class='alert error'>Failed to add asset.</div>";
        }
    }
}

// --- B. EDIT ASSET ---
if (isset($_POST['btn_edit_asset'])) {
    $id = intval($_POST['edit_id']);
    $name = cleanInput($_POST['edit_name']);
    $symbol = cleanInput($_POST['edit_symbol']);
    $network = cleanInput($_POST['edit_network']);
    $icon = cleanInput($_POST['edit_icon_url']);
    
    $stmt = $pdo->prepare("UPDATE assets SET name=?, symbol=?, network=?, icon_url=? WHERE id=?");
    if($stmt->execute([$name, $symbol, $network, $icon, $id])) {
        $msg = "<div class='alert success'>Asset Updated Successfully!</div>";
    }
}

// --- C. DELETE ASSET ---
if (isset($_GET['del'])) {
    $id = intval($_GET['del']);
    $stmt = $pdo->prepare("DELETE FROM assets WHERE id=?");
    $stmt->execute([$id]);
    header("Location: assets.php"); exit;
}

// --- D. TOGGLE STATUS (Active/Inactive) ---
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $curr = intval($_GET['s']);
    $new = $curr ? 0 : 1;
    
    $stmt = $pdo->prepare("UPDATE assets SET is_active=? WHERE id=?");
    $stmt->execute([$new, $id]);
    header("Location: assets.php"); exit;
}

// --- FETCH ALL ASSETS ---
$assets = $pdo->query("SELECT * FROM assets ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Wallet Assets</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-top: 80px; background: #000; color: #ddd; }
        .admin-nav { position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: #111; border-bottom: 1px solid gold; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 100; }
        
        .container { max-width: 1000px; margin: auto; padding: 15px; }
        
        /* Form Card */
        .card { background: #1a1a1a; padding: 20px; border-radius: 12px; border: 1px solid #333; margin-bottom: 20px; }
        .row { display: flex; gap: 15px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 200px; }
        
        input { background: #252525; border: 1px solid #444; color: white; padding: 12px; width: 100%; border-radius: 8px; margin-top: 5px; outline: none; }
        input:focus { border-color: gold; }
        label { font-size: 12px; color: #aaa; }

        /* Asset List Table */
        .asset-list { display: flex; flex-direction: column; gap: 10px; }
        .asset-item { display: flex; justify-content: space-between; align-items: center; background: #222; padding: 15px; border-radius: 10px; border: 1px solid #333; transition: 0.2s; }
        .asset-item:hover { border-color: #555; }
        
        .coin-info { display: flex; align-items: center; gap: 15px; }
        .coin-icon { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #444; }
        
        /* Action Buttons */
        .action-group { display: flex; gap: 10px; align-items: center; }
        .circle-btn { width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer; color: white; text-decoration: none; font-size: 14px; transition: 0.2s; }
        .circle-btn:hover { transform: scale(1.1); }
        
        .btn-edit { background: #007bff; }
        .btn-del { background: #dc3545; }
        .btn-on { background: #28a745; }
        .btn-off { background: #6c757d; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; text-align: center; }
        .alert.success { background: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
        .alert.error { background: rgba(255, 77, 77, 0.2); color: #ff4d4d; border: 1px solid #ff4d4d; }
    </style>
</head>
<body>

    <div class="admin-nav">
        <a href="dashboard.php" style="color: gold; text-decoration: none;"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <h3 style="color: white;">Wallet Assets</h3>
        <div></div>
    </div>

    <div class="container">
        <?php echo $msg; ?>

        <div class="card">
            <h3 style="color: gold; margin-bottom: 15px;">Add New Currency</h3>
            <form method="POST">
                <div class="row">
                    <div class="col">
                        <label>Currency Name</label>
                        <input type="text" name="name" placeholder="e.g. Bitcoin" required>
                    </div>
                    <div class="col">
                        <label>Symbol (Binance Ticker)</label>
                        <input type="text" name="symbol" placeholder="e.g. BTC" required>
                        <small style="color: #666; font-size: 10px;">Used for live price (BTCUSDT)</small>
                    </div>
                </div>
                <div class="row" style="margin-top: 10px;">
                    <div class="col">
                        <label>Network</label>
                        <input type="text" name="network" placeholder="e.g. BEP20 / Native" required>
                    </div>
                    <div class="col">
                        <label>Icon URL</label>
                        <input type="text" name="icon_url" placeholder="https://example.com/btc.png" required>
                    </div>
                </div>
                <button type="submit" name="btn_add_asset" class="btn" style="background: gold; color: black; margin-top: 20px; width: 100%; padding: 12px; font-weight: bold; border: none; border-radius: 8px; cursor: pointer;">
                    <i class="fa-solid fa-plus"></i> Add Currency
                </button>
            </form>
        </div>

        <h3 style="color: #fff; margin-bottom: 15px;">Active Assets List</h3>
        <div class="asset-list">
            <?php if(count($assets) > 0): ?>
                <?php foreach($assets as $coin): ?>
                <div class="asset-item" style="opacity: <?php echo $coin['is_active'] ? '1' : '0.5'; ?>">
                    
                    <div class="coin-info">
                        <img src="<?php echo htmlspecialchars($coin['icon_url']); ?>" onerror="this.src='https://via.placeholder.com/40'" class="coin-icon">
                        <div>
                            <div style="font-weight: bold; color: white; font-size: 16px;">
                                <?php echo htmlspecialchars($coin['symbol']); ?> 
                                <span style="font-size: 10px; background: #333; padding: 2px 5px; border-radius: 4px; color: gold;">
                                    <?php echo htmlspecialchars($coin['network']); ?>
                                </span>
                            </div>
                            <div style="font-size: 12px; color: #888;"><?php echo htmlspecialchars($coin['name']); ?></div>
                        </div>
                    </div>

                    <div class="action-group">
                        <a href="?toggle=<?php echo $coin['id']; ?>&s=<?php echo $coin['is_active']; ?>" 
                           class="circle-btn <?php echo $coin['is_active'] ? 'btn-on' : 'btn-off'; ?>"
                           title="<?php echo $coin['is_active'] ? 'Disable' : 'Enable'; ?>">
                           <i class="fa-solid fa-power-off"></i>
                        </a>

                        <button onclick='openEditModal(<?php echo json_encode($coin); ?>)' class="circle-btn btn-edit">
                            <i class="fa-solid fa-pen"></i>
                        </button>

                        <a href="?del=<?php echo $coin['id']; ?>" class="circle-btn btn-del" onclick="return confirm('Are you sure you want to delete this currency?');">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </div>

                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 30px; color: #666;">No assets found. Add one above.</div>
            <?php endif; ?>
        </div>

    </div>

    <div id="editModal" class="modal">
        <div class="card" style="width: 90%; max-width: 400px; border: 1px solid gold;">
            <h3 style="color: gold; margin-bottom: 15px; text-align: center;">Edit Currency</h3>
            <form method="POST">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <label>Name</label>
                <input type="text" name="edit_name" id="edit_name" required>
                
                <label style="margin-top: 10px; display:block;">Symbol</label>
                <input type="text" name="edit_symbol" id="edit_symbol" required>
                
                <label style="margin-top: 10px; display:block;">Network</label>
                <input type="text" name="edit_network" id="edit_network" required>
                
                <label style="margin-top: 10px; display:block;">Icon URL</label>
                <input type="text" name="edit_icon_url" id="edit_icon_url" required>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn" onclick="document.getElementById('editModal').style.display='none'" style="flex: 1; background: #333; color: white; padding: 10px; border: none; border-radius: 8px; cursor: pointer;">Cancel</button>
                    <button type="submit" name="btn_edit_asset" style="flex: 1; background: gold; color: black; padding: 10px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_symbol').value = data.symbol;
            document.getElementById('edit_network').value = data.network;
            document.getElementById('edit_icon_url').value = data.icon_url;
            document.getElementById('editModal').style.display = 'flex';
        }
    </script>

</body>
</html>

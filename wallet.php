<?php
/*
File: wallet.php
Purpose: Crypto Wallet (Deposit, Withdraw, Swap)
*/
require_once 'includes/functions.php';
$settings = getSettings($pdo);

// Testing ke liye user ID (Browser mode)
$tg_id = 123456789; 
if (isset($_GET['tg_id'])) $tg_id = cleanInput($_GET['tg_id']);

$user = getOrCreateUser($pdo, $tg_id, "Guest", "guest");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>My Wallet</title>
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
                <div style="font-size: 10px; color: gold;">Wallet Access</div>
            </div>
        </div>
        <button class="settings-btn"><i class="fa-solid fa-shield-halved"></i></button>
    </div>

    <div class="container" style="margin-top: 20px;">

        <div class="balance-card">
            <p style="font-size: 14px; opacity: 0.8;">Total Estimated Balance</p>
            <h1 style="margin: 10px 0;">$<span id="totalUsdBalance">0.00</span></h1>
            
            <div style="display: flex; justify-content: center; gap: 15px; margin-top: 15px;">
                <button class="btn" onclick="openModal('depositModal')" style="background: rgba(0,0,0,0.3); width: auto; color: #fff;">
                    <i class="fa-solid fa-arrow-down"></i> Deposit
                </button>
                <button class="btn" onclick="openModal('withdrawModal')" style="background: rgba(0,0,0,0.3); width: auto; color: #fff;">
                    <i class="fa-solid fa-arrow-up"></i> Withdraw
                </button>
                <button class="btn" onclick="openModal('swapModal')" style="background: rgba(0,0,0,0.3); width: auto; color: #fff;">
                    <i class="fa-solid fa-rotate"></i> Swap
                </button>
            </div>
        </div>

        <h3 style="margin-bottom: 15px; color: gold;">Your Assets</h3>
        
        <div class="asset-row">
            <div class="asset-left">
                <img src="https://cryptologos.cc/logos/tether-usdt-logo.png" class="asset-icon">
                <div>
                    <div style="font-weight: bold;">USDT</div>
                    <div style="font-size: 12px; color: #aaa;">Tether</div>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-weight: bold;"><?php echo getUserBalance($pdo, $user['id'], 'USDT'); ?></div>
                <div style="font-size: 12px; color: #28a745;">$1.00</div>
            </div>
        </div>

        <div class="asset-row">
            <div class="asset-left">
                <img src="https://cryptologos.cc/logos/toncoin-ton-logo.png" class="asset-icon">
                <div>
                    <div style="font-weight: bold;">TON</div>
                    <div style="font-size: 12px; color: #aaa;">Toncoin</div>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-weight: bold;"><?php echo getUserBalance($pdo, $user['id'], 'TON'); ?></div>
                <div style="font-size: 12px; color: gold;" id="priceTON">Loading...</div>
            </div>
        </div>

        <div class="asset-row">
            <div class="asset-left">
                <img src="https://cryptologos.cc/logos/bitcoin-btc-logo.png" class="asset-icon">
                <div>
                    <div style="font-weight: bold;">BTC</div>
                    <div style="font-size: 12px; color: #aaa;">Bitcoin</div>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-weight: bold;"><?php echo getUserBalance($pdo, $user['id'], 'BTC'); ?></div>
                <div style="font-size: 12px; color: gold;" id="priceBTC">Loading...</div>
            </div>
        </div>

    </div>

    <div id="depositModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:2000; padding: 20px; overflow-y: auto;">
        <div style="text-align: right;">
            <button onclick="closeModal('depositModal')" style="background:none; border:none; color:white; font-size: 24px;">&times;</button>
        </div>
        <h2 class="text-center text-gold">Deposit Crypto</h2>
        
        <form style="margin-top: 20px;" onsubmit="alert('Deposit Requested! Admin will verify.'); return false;">
            <div class="form-group">
                <label>Select Asset</label>
                <select id="depAsset" onchange="updateDepAddress()">
                    <option value="USDT">USDT (TRC20)</option>
                    <option value="TON">TON (The Open Network)</option>
                    <option value="BTC">Bitcoin</option>
                </select>
            </div>

            <div class="card" style="text-align: center; border: 1px dashed gold; padding: 15px;">
                <p style="font-size: 12px; color: #aaa;">Send only selected coin to this address:</p>
                <div class="invite-link-box" id="depAddress">
                    Select Coin First
                </div>
                <button type="button" class="btn btn-primary" onclick="copyText('depAddress')">Copy Address</button>
            </div>

            <div class="form-group">
                <label>Amount Sent</label>
                <input type="number" placeholder="Example: 10.5" required>
            </div>
            <div class="form-group">
                <label>Transaction Hash</label>
                <input type="text" placeholder="Paste TXID" required>
            </div>

            <button class="btn btn-success">Submit Deposit</button>
        </form>
    </div>

    <div id="swapModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:2000; padding: 20px;">
        <div style="text-align: right;">
            <button onclick="closeModal('swapModal')" style="background:none; border:none; color:white; font-size: 24px;">&times;</button>
        </div>
        <h2 class="text-center text-gold">Instant Swap</h2>
        
        <div class="card" style="margin-top: 20px;">
            <div class="form-group">
                <label>From</label>
                <select id="swapFrom">
                    <option value="USDT">USDT</option>
                    <option value="TON">TON</option>
                </select>
            </div>
            <div class="form-group">
                <label>Amount</label>
                <input type="number" id="swapAmount" placeholder="0.00">
            </div>
            
            <div class="text-center" style="margin: 10px 0; color: gold;"><i class="fa-solid fa-arrow-down"></i></div>

            <div class="form-group">
                <label>To</label>
                <select id="swapTo">
                    <option value="TON">TON</option>
                    <option value="USDT">USDT</option>
                </select>
            </div>
            <div class="form-group">
                <label>You Receive (Approx)</label>
                <input type="text" id="swapReceive" readonly style="background: #111; color: gold;">
            </div>

            <button class="btn btn-primary" onclick="alert('Swap Successful! (Demo)')">Swap Now (0% Fee)</button>
        </div>
    </div>

    <div id="withdrawModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:2000; padding: 20px;">
        <div style="text-align: right;">
            <button onclick="closeModal('withdrawModal')" style="background:none; border:none; color:white; font-size: 24px;">&times;</button>
        </div>
        <h2 class="text-center text-red">Withdraw</h2>
        <p class="text-center" style="color:#aaa;">Min Withdraw: $<?php echo $settings['min_withdraw_limit']; ?></p>
        
        <form style="margin-top: 20px;">
            <div class="form-group">
                <label>Select Asset</label>
                <select><option>USDT</option><option>TON</option></select>
            </div>
            <div class="form-group">
                <label>Destination Address</label>
                <input type="text" placeholder="Wallet Address" required>
            </div>
            <div class="form-group">
                <label>Amount</label>
                <input type="number" placeholder="Amount" required>
            </div>
            <button class="btn btn-danger">Request Withdrawal</button>
        </form>
    </div>


    <div class="bottom-nav">
        <a href="index.php" class="nav-item">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>
        <a href="wallet.php" class="nav-item active">
            <i class="fa-solid fa-wallet"></i>
            <span>Wallet</span>
        </a>
        <a href="invite.php" class="nav-item">
            <i class="fa-solid fa-user-plus"></i>
            <span>Invite</span>
        </a>
        <a href="history.php" class="nav-item">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>History</span>
        </a>
    </div>

    <script>
        // --- 1. Fetch Real-time Prices from Binance ---
        const prices = { USDT: 1.00, TON: 0, BTC: 0 };

        async function fetchPrices() {
            try {
                // Fetch TON Price
                let resTon = await fetch('https://api.binance.com/api/v3/ticker/price?symbol=TONUSDT');
                let dataTon = await resTon.json();
                prices.TON = parseFloat(dataTon.price).toFixed(2);
                document.getElementById('priceTON').innerText = '$' + prices.TON;

                // Fetch BTC Price
                let resBtc = await fetch('https://api.binance.com/api/v3/ticker/price?symbol=BTCUSDT');
                let dataBtc = await resBtc.json();
                prices.BTC = parseFloat(dataBtc.price).toFixed(2);
                document.getElementById('priceBTC').innerText = '$' + prices.BTC;

                // Update Total Balance (Example Logic)
                // Real implementation me PHP se user ka balance lana padega aur JS me multiply karna padega
                updateTotalEstimate();

            } catch (err) {
                console.log("Price Fetch Error", err);
            }
        }
        
        // Load prices on start
        fetchPrices();

        function updateTotalEstimate() {
            // Demo calculation
            let total = (<?php echo getUserBalance($pdo, $user['id'], 'USDT'); ?> * 1) + 
                        (<?php echo getUserBalance($pdo, $user['id'], 'TON'); ?> * prices.TON) +
                        (<?php echo getUserBalance($pdo, $user['id'], 'BTC'); ?> * prices.BTC);
            document.getElementById('totalUsdBalance').innerText = total.toFixed(2);
        }

        // --- 2. Modal Logic ---
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        // --- 3. Deposit Address Logic ---
        // Admin addresses JSON (PHP se pass kar sakte hain, abhi demo static)
        const adminWallets = {
            'USDT': 'TRC20_ADDRESS_FROM_ADMIN_PANEL',
            'TON': 'TON_ADDRESS_FROM_ADMIN_PANEL',
            'BTC': 'BTC_ADDRESS_FROM_ADMIN_PANEL'
        };

        function updateDepAddress() {
            let coin = document.getElementById('depAsset').value;
            document.getElementById('depAddress').innerText = adminWallets[coin] || 'Select Coin';
        }

        // --- 4. Copy Text ---
        function copyText(id) {
            navigator.clipboard.writeText(document.getElementById(id).innerText);
            alert("Address Copied!");
        }

    </script>
</body>
</html>

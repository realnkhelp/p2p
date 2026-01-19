<?php
/*
File: wallet.php
Purpose: Wallet with Balance Checks, Swap Logic & History Saving
*/
require_once 'includes/functions.php';

// 1. Fetch Settings & Limits
$settings = getSettings($pdo);
$min_deposit = $settings['min_deposit_limit'] ?? 1.0;
$min_withdraw = $settings['min_withdraw_limit'] ?? 10.0;
$min_swap = $settings['min_swap_limit'] ?? 5.0;

// 2. User Setup (Telegram ID se login)
$tg_id = 123456789; // Default testing
if (isset($_GET['tg_id'])) $tg_id = cleanInput($_GET['tg_id']);
$user = getOrCreateUser($pdo, $tg_id, "Guest", "guest");

// 3. Dynamic Assets from DB
// (Admin panel se add kiye gaye coins yahan dikhenge)
$assets = $pdo->query("SELECT * FROM assets WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);

// Fallback agar DB khali ho (Demo ke liye)
if(empty($assets)) {
    $assets = [
        ['symbol' => 'USDT', 'name' => 'Tether', 'network' => 'TRC20', 'icon_url' => 'https://cryptologos.cc/logos/tether-usdt-logo.png'],
        ['symbol' => 'TON', 'name' => 'Toncoin', 'network' => 'TON', 'icon_url' => 'https://cryptologos.cc/logos/toncoin-ton-logo.png'],
        ['symbol' => 'BTC', 'name' => 'Bitcoin', 'network' => 'BTC', 'icon_url' => 'https://cryptologos.cc/logos/bitcoin-btc-logo.png']
    ];
}

// Helper: Get Balance
function getBal($pdo, $uid, $symbol) {
    return getUserBalance($pdo, $uid, $symbol);
}
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
    <style>
        /* Custom Styles specifically for Wallet Page */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(10px); z-index: 2000;
            padding: 20px; overflow-y: auto;
        }

        /* Asset Row Styling (Requested: Name ke bagal me network) */
        .asset-row {
            display: flex; justify-content: space-between; align-items: center;
            background: rgba(255, 255, 255, 0.05); margin-bottom: 10px; padding: 15px;
            border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .asset-info { display: flex; align-items: center; gap: 12px; }
        .asset-icon { width: 40px; height: 40px; border-radius: 50%; }
        
        .coin-title { font-weight: bold; font-size: 16px; color: #fff; display: flex; align-items: center; gap: 8px; }
        .network-badge { font-size: 10px; background: gold; color: #000; padding: 2px 5px; border-radius: 4px; font-weight: bold; }
        
        .coin-price { font-size: 12px; color: #aaa; margin-top: 3px; }
        .price-up { color: #0ecb81; }
        .price-down { color: #ff4d4d; }

        /* Swap Modal Spacing (Requested Clean Look) */
        .swap-spacer { margin-bottom: 20px; }
        .swap-input-group { background: #222; padding: 15px; border-radius: 12px; border: 1px solid #333; display: flex; justify-content: space-between; align-items: center; }
        .swap-input { background: transparent; border: none; color: white; text-align: right; font-size: 18px; width: 120px; outline: none; }
        
        /* Token Selector */
        .token-sel { display: flex; align-items: center; gap: 8px; background: #333; padding: 5px 10px; border-radius: 20px; cursor: pointer; }
        .token-sel img { width: 24px; height: 24px; border-radius: 50%; }

        /* Notification Toast */
        #toast {
            visibility: hidden; min-width: 250px; background-color: #ff4d4d; color: #fff; text-align: center;
            border-radius: 8px; padding: 16px; position: fixed; z-index: 3000; left: 50%; bottom: 80px;
            transform: translateX(-50%); font-size: 14px;
        }
        #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 80px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 80px; opacity: 1;} to {bottom: 0; opacity: 0;} }
    </style>
</head>
<body>

    <div class="sticky-header">
        <div class="profile-section">
            <img src="assets/images/user.png" id="userAvatar" class="profile-pic">
            <div>
                <div class="user-name" id="userName"><?php echo $user['first_name']; ?></div>
                <div style="font-size: 10px; color: gold;">Wallet</div>
            </div>
        </div>
        <button class="settings-btn" onclick="location.reload()"><i class="fa-solid fa-rotate"></i></button>
    </div>

    <div class="container" style="margin-top: 20px; margin-bottom: 80px;">

        <div class="balance-card">
            <p style="font-size: 12px; opacity: 0.7; color: black;">Total Portfolio Value</p>
            <h1 style="margin: 5px 0; color: black;">$<span id="totalUsdBalance">0.00</span></h1>
            
            <div style="display: flex; justify-content: center; gap: 10px; margin-top: 15px;">
                <button class="btn" onclick="openModal('depositModal')" style="background: rgba(0,0,0,0.8); color: gold; width: auto; font-size: 12px;">
                    <i class="fa-solid fa-arrow-down"></i> Deposit
                </button>
                <button class="btn" onclick="openModal('withdrawModal')" style="background: rgba(0,0,0,0.8); color: white; width: auto; font-size: 12px;">
                    <i class="fa-solid fa-arrow-up"></i> Withdraw
                </button>
                <button class="btn" onclick="openModal('swapModal')" style="background: rgba(0,0,0,0.8); color: #0ecb81; width: auto; font-size: 12px;">
                    <i class="fa-solid fa-repeat"></i> Swap
                </button>
            </div>
        </div>

        <h4 style="margin-bottom: 10px; color: gold;">Crypto Assets</h4>
        <div id="assetsList">
            <?php foreach ($assets as $coin): 
                $balance = getBal($pdo, $user['telegram_id'], $coin['symbol']);
            ?>
            <div class="asset-row">
                <div class="asset-info">
                    <img src="<?php echo $coin['icon_url']; ?>" class="asset-icon">
                    <div>
                        <div class="coin-title">
                            <?php echo $coin['name']; ?> 
                            <span class="network-badge"><?php echo $coin['network']; ?></span>
                        </div>
                        <div class="coin-price">
                            $<span class="live-price" data-symbol="<?php echo $coin['symbol']; ?>">0.00</span>
                            <span class="price-change" data-symbol="<?php echo $coin['symbol']; ?>">0.00%</span>
                        </div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: bold; color: white; font-size: 15px;">
                        <span id="bal_qty_<?php echo $coin['symbol']; ?>"><?php echo number_format($balance, 6); ?></span>
                    </div>
                    <div style="font-size: 12px; color: #888;">
                        $<span class="balance-usd" data-symbol="<?php echo $coin['symbol']; ?>" data-bal="<?php echo $balance; ?>">0.00</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="depositModal" class="modal-overlay">
        <div style="text-align: right;"><i class="fa-solid fa-xmark" onclick="closeModal('depositModal')" style="font-size: 24px; color: #fff;"></i></div>
        <h2 class="text-center text-gold mb-3">Deposit Crypto</h2>
        
        <label>Select Asset</label>
        <select id="depAsset" class="form-control mb-3" onchange="updateDepAddress()">
            <?php foreach($assets as $c) echo "<option value='{$c['symbol']}' data-addr='{$settings['admin_wallets_json']}'>{$c['symbol']} ({$c['network']})</option>"; ?>
        </select>

        <div class="card" style="text-align: center; border: 1px dashed gold; background: #222; padding: 15px;">
            <p style="color: #aaa; font-size: 11px;">Send selected coin to this address:</p>
            <div id="depAddressText" style="color: gold; font-family: monospace; word-break: break-all; font-size: 12px; margin: 10px 0;">Select Coin...</div>
            <button class="btn" onclick="copyText('depAddressText')" style="background: gold; color: black; padding: 5px 10px; width: auto; font-size: 12px;">Copy Address</button>
        </div>

        <label class="mt-3">Amount</label>
        <input type="number" id="depAmount" placeholder="Min <?php echo $min_deposit; ?>" class="mb-3">

        <label>Transaction Hash</label>
        <input type="text" id="depTxid" placeholder="Paste TXID" class="mb-3">

        <button class="btn btn-success" onclick="submitDeposit()">Submit Deposit</button>
    </div>

    <div id="withdrawModal" class="modal-overlay">
        <div style="text-align: right;"><i class="fa-solid fa-xmark" onclick="closeModal('withdrawModal')" style="font-size: 24px; color: #fff;"></i></div>
        <h2 class="text-center text-red mb-3">Withdraw</h2>
        
        <label>Select Asset</label>
        <select id="wdAsset" class="form-control mb-3" onchange="updateWdBal()">
            <?php foreach($assets as $c) echo "<option value='{$c['symbol']}'>{$c['symbol']} ({$c['network']})</option>"; ?>
        </select>
        <p style="text-align: right; font-size: 11px; color: #aaa; margin-top: -10px;">Available: <span id="wdAvailBal">0.00</span></p>

        <label>Wallet Address</label>
        <input type="text" id="wdAddress" placeholder="Paste Wallet Address" class="mb-3">

        <label>Amount (Min $<?php echo $min_withdraw; ?>)</label>
        <input type="number" id="wdAmount" placeholder="0.00" class="mb-3">

        <button class="btn btn-danger" onclick="submitWithdraw()">Request Withdrawal</button>
    </div>

    <div id="swapModal" class="modal-overlay">
        <div style="text-align: right;"><i class="fa-solid fa-xmark" onclick="closeModal('swapModal')" style="font-size: 24px; color: #fff;"></i></div>
        
        <div id="swapStep1">
            <h2 class="text-center text-gold" style="margin-bottom: 25px;">Swap Crypto</h2>

            <label style="color: #aaa; font-size: 12px;">From</label>
            <div class="swap-input-group swap-spacer">
                <div class="token-sel" onclick="cycleToken('from')">
                    <img src="" id="swapFromIcon">
                    <span id="swapFromSym">USDT</span>
                    <i class="fa-solid fa-caret-down"></i>
                </div>
                <input type="number" id="swapFromInput" placeholder="0" class="swap-input" oninput="calcSwap()">
            </div>
            <div style="text-align: right; font-size: 11px; color: #aaa; margin-top: -15px; margin-bottom: 20px;">
                Available: <span id="swapFromBal" style="color: white;">0.00</span>
            </div>

            <div style="text-align: center; margin-bottom: 20px;">
                <div style="background: #333; width: 40px; height: 40px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; border: 1px solid gold;">
                    <i class="fa-solid fa-arrow-down" style="color: gold;"></i>
                </div>
            </div>

            <label style="color: #aaa; font-size: 12px;">To (Estimate)</label>
            <div class="swap-input-group swap-spacer">
                <div class="token-sel" onclick="cycleToken('to')">
                    <img src="" id="swapToIcon">
                    <span id="swapToSym">TON</span>
                    <i class="fa-solid fa-caret-down"></i>
                </div>
                <input type="number" id="swapToInput" placeholder="0" class="swap-input" readonly>
            </div>

            <div style="display: flex; gap: 10px; margin-bottom: 25px;">
                <button class="btn" onclick="setPercent(0.25)" style="background: #333; font-size: 12px; padding: 8px;">25%</button>
                <button class="btn" onclick="setPercent(0.50)" style="background: #333; font-size: 12px; padding: 8px;">50%</button>
                <button class="btn" onclick="setPercent(1.00)" style="background: #333; font-size: 12px; padding: 8px;">MAX</button>
            </div>

            <button class="btn btn-primary" onclick="checkSwapRequirement()">Review Swap</button>
        </div>

        <div id="swapStep2" style="display: none;">
            <h2 class="text-center text-gold mb-3">Review Swap</h2>
            <div class="card" style="border: 1px solid gold;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 20px; color: #ff4d4d;">- <span id="revPay">0</span> <span id="revPaySym"></span></div>
                    <i class="fa-solid fa-arrow-right" style="color: #aaa;"></i>
                    <div style="font-size: 20px; color: #0ecb81;">+ <span id="revGet">0</span> <span id="revGetSym"></span></div>
                </div>
                <hr style="border-color: #333; margin: 15px 0;">
                <p style="font-size: 12px; color: #aaa; text-align: center;">Transaction will be recorded in history immediately.</p>
                <button class="btn btn-success mt-3" onclick="confirmSwap()">Confirm & Swap</button>
                <button class="btn mt-2" onclick="document.getElementById('swapStep2').style.display='none'; document.getElementById('swapStep1').style.display='block';" style="background: transparent; border: 1px solid #555;">Cancel</button>
            </div>
        </div>
    </div>

    <div id="toast">Error Message</div>

    <div class="bottom-nav">
        <a href="index.php" class="nav-item"><i class="fa-solid fa-house"></i><span>Home</span></a>
        <a href="wallet.php" class="nav-item active"><i class="fa-solid fa-wallet"></i><span>Wallet</span></a>
        <a href="invite.php" class="nav-item"><i class="fa-solid fa-user-plus"></i><span>Invite</span></a>
        <a href="history.php" class="nav-item"><i class="fa-solid fa-clock-rotate-left"></i><span>History</span></a>
    </div>

    <script>
        // --- DATA SETUP ---
        const assets = <?php echo json_encode($assets); ?>;
        const limits = { dep: <?php echo $min_deposit; ?>, wd: <?php echo $min_withdraw; ?>, swap: <?php echo $min_swap; ?> };
        const prices = {}; 
        let currentTgId = <?php echo $tg_id; ?>;

        // --- TELEGRAM AUTO SYNC ---
        const tg = window.Telegram.WebApp;
        tg.expand();
        if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
            const u = tg.initDataUnsafe.user;
            currentTgId = u.id;
            document.getElementById('userName').innerText = u.first_name;
            if(u.photo_url) document.getElementById('userAvatar').src = u.photo_url;
        }

        // --- 1. LIVE PRICES & BALANCES ---
        async function fetchPrices() {
            // Demo logic: USDT=1. Others random variation for demo feel
            prices['USDT'] = 1.00;
            
            // Binance API for real prices
            try {
                const pairs = assets.filter(a => a.symbol !== 'USDT').map(a => a.symbol + 'USDT');
                for(let p of pairs) {
                    let res = await fetch(`https://api.binance.com/api/v3/ticker/24hr?symbol=${p}`);
                    let d = await res.json();
                    let sym = p.replace('USDT','');
                    prices[sym] = parseFloat(d.lastPrice);
                    
                    // UI Update
                    document.querySelector(`.live-price[data-symbol='${sym}']`).innerText = parseFloat(d.lastPrice).toFixed(4);
                    let changeEl = document.querySelector(`.price-change[data-symbol='${sym}']`);
                    let chg = parseFloat(d.priceChangePercent);
                    changeEl.innerText = (chg>0?'+':'') + chg.toFixed(2) + '%';
                    changeEl.className = `price-change ${chg>=0?'price-up':'price-down'}`;
                }
                updateTotal();
            } catch(e) { console.log(e); }
        }
        
        function updateTotal() {
            let total = 0;
            assets.forEach(a => {
                let qty = parseFloat(document.getElementById(`bal_qty_${a.symbol}`).innerText.replace(/,/g,''));
                let pr = prices[a.symbol] || 0;
                let val = qty * pr;
                total += val;
                document.querySelector(`.balance-usd[data-symbol='${a.symbol}']`).innerText = val.toFixed(2);
            });
            document.getElementById('totalUsdBalance').innerText = total.toFixed(2);
        }
        setInterval(fetchPrices, 10000); fetchPrices();

        // --- 2. SWAP LOGIC ---
        let swapFrom = assets[0]; // Default
        let swapTo = assets[1] || assets[0];

        function initSwapUI() {
            document.getElementById('swapFromIcon').src = swapFrom.icon_url;
            document.getElementById('swapFromSym').innerText = swapFrom.symbol;
            document.getElementById('swapToIcon').src = swapTo.icon_url;
            document.getElementById('swapToSym').innerText = swapTo.symbol;
            
            // Get Available Balance from DOM
            let balText = document.getElementById(`bal_qty_${swapFrom.symbol}`).innerText;
            document.getElementById('swapFromBal').innerText = balText;
        }

        function cycleToken(type) {
            // Simple cycle for demo. Can be modal for full list
            let curr = type === 'from' ? swapFrom : swapTo;
            let idx = assets.findIndex(a => a.symbol === curr.symbol);
            let next = assets[(idx + 1) % assets.length];
            
            if(type === 'from') swapFrom = next;
            else swapTo = next;

            // Prevent same token
            if(swapFrom.symbol === swapTo.symbol) cycleToken('to');
            
            initSwapUI();
            calcSwap();
        }

        function setPercent(p) {
            let bal = parseFloat(document.getElementById('swapFromBal').innerText.replace(/,/g,''));
            document.getElementById('swapFromInput').value = (bal * p).toFixed(6);
            calcSwap();
        }

        function calcSwap() {
            let qty = parseFloat(document.getElementById('swapFromInput').value);
            if(!qty) { document.getElementById('swapToInput').value = ''; return; }
            
            let p1 = prices[swapFrom.symbol] || 1;
            let p2 = prices[swapTo.symbol] || 1;
            let out = (qty * p1) / p2;
            document.getElementById('swapToInput').value = out.toFixed(6);
        }

        function checkSwapRequirement() {
            let qty = parseFloat(document.getElementById('swapFromInput').value);
            let bal = parseFloat(document.getElementById('swapFromBal').innerText.replace(/,/g,''));

            // Validation 1: Min Limit
            if(!qty || qty * (prices[swapFrom.symbol]||1) < limits.swap) {
                showToast(`Minimum swap value is $${limits.swap}`);
                return;
            }

            // Validation 2: Insufficient Funds
            if(qty > bal) {
                showToast("Insufficient Balance!");
                return;
            }

            // Show Review
            document.getElementById('revPay').innerText = qty;
            document.getElementById('revPaySym').innerText = swapFrom.symbol;
            document.getElementById('revGet').innerText = document.getElementById('swapToInput').value;
            document.getElementById('revGetSym').innerText = swapTo.symbol;

            document.getElementById('swapStep1').style.display='none';
            document.getElementById('swapStep2').style.display='block';
        }

        function confirmSwap() {
            let data = {
                tg_id: currentTgId,
                type: 'swap',
                from_coin: swapFrom.symbol,
                to_coin: swapTo.symbol,
                amount: document.getElementById('swapFromInput').value,
                receive_amount: document.getElementById('swapToInput').value
            };
            sendApiRequest(data, 'swapModal');
        }

        // --- 3. WITHDRAW LOGIC ---
        function updateWdBal() {
            let sym = document.getElementById('wdAsset').value;
            let bal = document.getElementById(`bal_qty_${sym}`).innerText;
            document.getElementById('wdAvailBal').innerText = bal + ' ' + sym;
        }
        
        function submitWithdraw() {
            let sym = document.getElementById('wdAsset').value;
            let amt = parseFloat(document.getElementById('wdAmount').value);
            let addr = document.getElementById('wdAddress').value;
            let bal = parseFloat(document.getElementById(`bal_qty_${sym}`).innerText.replace(/,/g,''));

            if(!amt || amt * (prices[sym]||1) < limits.wd) { showToast(`Min withdraw is $${limits.wd}`); return; }
            if(amt > bal) { showToast("Insufficient Balance!"); return; }
            if(!addr) { showToast("Enter Address"); return; }

            let data = {
                tg_id: currentTgId,
                type: 'withdraw',
                asset: sym,
                amount: amt,
                address: addr
            };
            sendApiRequest(data, 'withdrawModal');
        }

        // --- 4. DEPOSIT LOGIC ---
        function updateDepAddress() {
            let sym = document.getElementById('depAsset').value;
            // Parse Admin Wallets JSON
            let opt = document.querySelector(`#depAsset option[value='${sym}']`);
            let json = JSON.parse(opt.getAttribute('data-addr') || '{}');
            
            // Logic to find address. Example assumes network keys like 'USDT_TRC20'
            // For simplicity in this demo, showing general or matching key
            let net = assets.find(a=>a.symbol===sym).network;
            let key = sym + '_' + net; // e.g., USDT_TRC20
            
            document.getElementById('depAddressText').innerText = json[key] || json[sym] || "Contact Admin";
        }

        function submitDeposit() {
            let amt = document.getElementById('depAmount').value;
            let tx = document.getElementById('depTxid').value;
            if(!amt || amt < limits.dep) { showToast(`Min deposit is $${limits.dep}`); return; }
            if(!tx) { showToast("Enter TXID"); return; }

            let data = {
                tg_id: currentTgId,
                type: 'deposit',
                asset: document.getElementById('depAsset').value,
                amount: amt,
                tx_hash: tx
            };
            sendApiRequest(data, 'depositModal');
        }

        // --- API HANDLER ---
        function sendApiRequest(data, modalId) {
            fetch('api/submit_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    showToast("Success! Transaction Recorded.");
                    closeModal(modalId);
                    setTimeout(() => location.reload(), 1500); // Reload to update balances
                } else {
                    showToast(res.message || "Error Occurred");
                }
            })
            .catch(err => showToast("Network Error"));
        }

        // --- UTILS ---
        function showToast(msg) {
            let x = document.getElementById("toast");
            x.innerText = msg;
            x.className = "show";
            setTimeout(function(){ x.className = x.className.replace("show", ""); }, 3000);
        }
        function openModal(id) { document.getElementById(id).style.display='block'; if(id==='swapModal') initSwapUI(); if(id==='withdrawModal') updateWdBal(); if(id==='depositModal') updateDepAddress(); }
        function closeModal(id) { document.getElementById(id).style.display='none'; }
        function copyText(id) { navigator.clipboard.writeText(document.getElementById(id).innerText); showToast("Copied!"); }

    </script>
</body>
</html>
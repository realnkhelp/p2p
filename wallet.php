<?php
/*
File: wallet.php
Purpose: Advanced Crypto Wallet (Deposit, Withdraw, Real-time Swap)
*/

require_once 'includes/functions.php';

// 1. Fetch Settings & Limits from DB
$settings = getSettings($pdo);

// Limits (Fallback to defaults if not set in DB)
$min_deposit = $settings['min_deposit_limit'] ?? 1.0;
$min_withdraw = $settings['min_withdraw_limit'] ?? 10.0;
$min_swap = $settings['min_swap_limit'] ?? 5.0;

// 2. User Setup
$tg_id = 123456789; // Default for testing
if (isset($_GET['tg_id'])) $tg_id = cleanInput($_GET['tg_id']);
$user = getOrCreateUser($pdo, $tg_id, "Guest", "guest");

// 3. Dynamic Assets List (Simulated from Admin Panel DB)
// In real usage, fetch this via: $assets = $pdo->query("SELECT * FROM assets WHERE status='active'")->fetchAll();
$assets = [
    ['symbol' => 'USDT', 'name' => 'Tether', 'network' => 'TRC20', 'icon' => 'https://cryptologos.cc/logos/tether-usdt-logo.png', 'type' => 'stable'],
    ['symbol' => 'TON', 'name' => 'Toncoin', 'network' => 'TON', 'icon' => 'https://cryptologos.cc/logos/toncoin-ton-logo.png', 'type' => 'crypto'],
    ['symbol' => 'BTC', 'name' => 'Bitcoin', 'network' => 'BTC', 'icon' => 'https://cryptologos.cc/logos/bitcoin-btc-logo.png', 'type' => 'crypto'],
    ['symbol' => 'BNB', 'name' => 'BNB', 'network' => 'BEP20', 'icon' => 'https://cryptologos.cc/logos/bnb-bnb-logo.png', 'type' => 'crypto'],
    ['symbol' => 'TRX', 'name' => 'Tron', 'network' => 'TRC20', 'icon' => 'https://cryptologos.cc/logos/tron-trx-logo.png', 'type' => 'crypto']
];

// Helper function to get user balance
function getBal($pdo, $uid, $symbol) {
    return number_format(getUserBalance($pdo, $uid, $symbol), 4);
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
        /* --- BLUR MODAL STYLE --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6); /* Dark see-through */
            backdrop-filter: blur(12px); /* STRONG BLUR EFFECT */
            -webkit-backdrop-filter: blur(12px);
            z-index: 2000;
            padding: 20px;
            overflow-y: auto;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* --- ASSET ROW STYLE --- */
        .asset-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.03);
            margin-bottom: 12px;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .asset-left { display: flex; align-items: center; gap: 12px; }
        .asset-icon { width: 40px; height: 40px; border-radius: 50%; }
        .asset-name { font-weight: bold; font-size: 15px; color: #fff; }
        .asset-network { font-size: 10px; background: #333; padding: 2px 6px; border-radius: 4px; color: #aaa; margin-left: 5px; }
        .asset-price { font-size: 12px; color: #aaa; margin-top: 2px; }
        .price-up { color: #0ecb81; }
        .price-down { color: #f6465d; }
        .asset-balance-val { font-size: 12px; color: #aaa; text-align: right; }

        /* --- SWAP UI STYLE --- */
        .swap-container {
            background: #1a1a1a;
            border-radius: 16px;
            padding: 20px;
            position: relative;
            border: 1px solid #333;
        }
        .swap-box {
            background: #252525;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        .swap-label { font-size: 12px; color: #888; margin-bottom: 5px; }
        .token-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #333;
            padding: 5px 12px;
            border-radius: 20px;
            cursor: pointer;
            transition: 0.2s;
        }
        .token-selector:active { transform: scale(0.95); }
        .token-img { width: 24px; height: 24px; border-radius: 50%; }
        .swap-input {
            background: transparent;
            border: none;
            color: white;
            font-size: 20px;
            text-align: right;
            width: 120px;
            outline: none;
        }
        .swap-divider {
            display: flex;
            justify-content: center;
            margin: -15px 0;
            position: relative;
            z-index: 10;
        }
        .swap-icon-btn {
            background: #333;
            border: 2px solid #1a1a1a;
            color: gold;
            width: 35px; height: 35px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
        }
        
        .percentage-row {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .percent-btn {
            flex: 1;
            background: #333;
            border: none;
            color: #aaa;
            font-size: 12px;
            padding: 5px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        /* Token Selection Modal */
        .token-list-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #333;
            cursor: pointer;
        }
        .token-list-item:hover { background: #222; }

        /* Review Page Details */
        .review-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 10px;
            color: #ccc;
        }
    </style>
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

    <div class="container" style="margin-top: 20px; margin-bottom: 80px;">

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
        
        <div id="assetsList">
            <?php foreach ($assets as $coin): 
                $balance = getBal($pdo, $user['id'], $coin['symbol']);
            ?>
            <div class="asset-row" id="row_<?php echo $coin['symbol']; ?>">
                <div class="asset-left">
                    <img src="<?php echo $coin['icon']; ?>" class="asset-icon">
                    <div>
                        <div class="asset-name">
                            <?php echo $coin['symbol']; ?> 
                            <span class="asset-network"><?php echo $coin['network']; ?></span>
                        </div>
                        <div class="asset-price">
                            $<span class="live-price" data-symbol="<?php echo $coin['symbol']; ?>">0.00</span>
                            <span class="price-change" data-symbol="<?php echo $coin['symbol']; ?>"></span>
                        </div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: bold; font-size: 15px;"><?php echo $balance; ?></div>
                    <div class="asset-balance-val">$<span class="balance-usd" data-symbol="<?php echo $coin['symbol']; ?>" data-bal="<?php echo $balance; ?>">0.00</span></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>

    <div id="depositModal" class="modal-overlay">
        <div style="text-align: right;">
            <button onclick="closeModal('depositModal')" style="background:none; border:none; color:white; font-size: 24px;">&times;</button>
        </div>
        <h2 class="text-center text-gold">Deposit Crypto</h2>
        
        <form style="margin-top: 20px;">
            <div class="form-group">
                <label>Select Asset</label>
                <select id="depAsset" class="form-control" onchange="updateDepAddress()">
                    <?php foreach($assets as $c) echo "<option value='{$c['symbol']}'>{$c['symbol']} ({$c['network']})</option>"; ?>
                </select>
            </div>

            <div class="card" style="text-align: center; border: 1px dashed gold; padding: 15px; background: rgba(0,0,0,0.4);">
                <p style="font-size: 12px; color: #aaa;">Send only selected coin to this address:</p>
                <div class="invite-link-box" id="depAddressText" style="font-size: 12px; word-break: break-all;">
                    Loading Address...
                </div>
                <button type="button" class="btn btn-sm mt-2" onclick="copyText('depAddressText')" style="background: gold; color: black;">Copy Address</button>
            </div>

            <div class="form-group mt-3">
                <label>Amount Sent</label>
                <input type="number" id="depAmount" placeholder="Min <?php echo $min_deposit; ?>" required>
            </div>
            <div class="form-group">
                <label>Transaction Hash (TXID)</label>
                <input type="text" id="depTxid" placeholder="Paste Transaction Hash" required>
            </div>

            <button type="button" class="btn btn-success" onclick="submitDeposit()">Submit Deposit</button>
        </form>
    </div>

    <div id="withdrawModal" class="modal-overlay">
        <div style="text-align: right;">
            <button onclick="closeModal('withdrawModal')" style="background:none; border:none; color:white; font-size: 24px;">&times;</button>
        </div>
        <h2 class="text-center text-red">Withdraw</h2>
        
        <form style="margin-top: 20px;">
            <div class="form-group">
                <label>Select Asset</label>
                <select id="wdAsset" class="form-control">
                    <?php foreach($assets as $c) echo "<option value='{$c['symbol']}'>{$c['symbol']} ({$c['network']})</option>"; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Destination Address</label>
                <input type="text" id="wdAddress" placeholder="Enter Wallet Address" required>
            </div>
            <div class="form-group">
                <label>Amount (Min $<?php echo $min_withdraw; ?>)</label>
                <input type="number" id="wdAmount" placeholder="0.00" required>
            </div>
            <button type="button" class="btn btn-danger" onclick="submitWithdraw()">Request Withdrawal</button>
        </form>
    </div>

    <div id="swapModal" class="modal-overlay">
        <div style="text-align: right;">
            <button onclick="closeModal('swapModal')" style="background:none; border:none; color:white; font-size: 24px;">&times;</button>
        </div>
        
        <div id="swapStep1">
            <h2 class="text-center text-gold mb-3">Swap Crypto</h2>
            
            <div class="swap-container">
                <div class="swap-label">From</div>
                <div class="swap-box">
                    <div class="token-selector" onclick="openTokenSelect('from')">
                        <img src="" id="swapFromIcon" class="token-img">
                        <span id="swapFromSymbol" style="font-weight: bold;">--</span>
                        <i class="fa-solid fa-angle-down" style="font-size: 10px;"></i>
                    </div>
                    <input type="number" id="swapFromInput" class="swap-input" placeholder="0" oninput="calcSwapOutput()">
                </div>
                <div style="text-align: right; font-size: 11px; color: #aaa; margin-top: -5px; margin-bottom: 10px;">
                    Available: <span id="swapFromBal">0.00</span>
                </div>

                <div class="swap-divider">
                    <div class="swap-icon-btn" onclick="flipSwap()">
                        <i class="fa-solid fa-arrow-down"></i>
                    </div>
                </div>

                <div class="swap-label" style="margin-top: 5px;">To (Estimate)</div>
                <div class="swap-box">
                    <div class="token-selector" onclick="openTokenSelect('to')">
                        <img src="" id="swapToIcon" class="token-img">
                        <span id="swapToSymbol" style="font-weight: bold;">--</span>
                        <i class="fa-solid fa-angle-down" style="font-size: 10px;"></i>
                    </div>
                    <input type="number" id="swapToInput" class="swap-input" placeholder="0" readonly>
                </div>

                <div class="percentage-row">
                    <button class="percent-btn" onclick="setSwapPercent(0.25)">25%</button>
                    <button class="percent-btn" onclick="setSwapPercent(0.50)">50%</button>
                    <button class="percent-btn" onclick="setSwapPercent(1.00)">MAX</button>
                </div>

                <button class="btn btn-primary" onclick="goToReview()">Review and Swap</button>
            </div>
        </div>

        <div id="swapStep2" style="display: none;">
            <h2 class="text-center text-gold mb-3">Review and Swap</h2>
            
            <div class="card" style="border: 1px solid gold;">
                <div class="text-center mb-3">
                    <h1 style="color: #f6465d;">- <span id="reviewSendAmt">0</span> <span id="reviewSendSym">USDT</span></h1>
                    <i class="fa-solid fa-arrow-down" style="color: #aaa;"></i>
                    <h1 style="color: #0ecb81;">+ <span id="reviewGetAmt">0</span> <span id="reviewGetSym">TON</span></h1>
                </div>

                <hr style="border-color: #333;">

                <div class="review-row">
                    <span>Order Type</span>
                    <span>Market Order</span>
                </div>
                <div class="review-row">
                    <span>Rate</span>
                    <span id="reviewRate">1 USDT = ...</span>
                </div>
                <div class="review-row">
                    <span>Fee</span>
                    <span>0%</span>
                </div>
                
                <button class="btn btn-success mt-3" onclick="submitSwap()">Swap Now</button>
                <button class="btn btn-outline mt-2" onclick="backToSwapInput()" style="background:transparent; border:1px solid #444; color: #aaa;">Cancel</button>
            </div>
        </div>

    </div>

    <div id="tokenModal" class="modal-overlay" style="z-index: 2100;">
        <div style="background: #1a1a1a; min-height: 50vh; border-radius: 20px 20px 0 0; position: absolute; bottom: 0; width: 100%; left:0;">
            <div style="padding: 15px; border-bottom: 1px solid #333; display: flex; justify-content: space-between;">
                <h3 style="color: white;">Select Token</h3>
                <span onclick="document.getElementById('tokenModal').style.display='none'" style="font-size: 20px; cursor: pointer;">&times;</span>
            </div>
            
            <div style="padding: 10px;">
                <input type="text" placeholder="Search..." style="width: 100%; background: #222; border: none; padding: 10px; border-radius: 8px; color: white;">
            </div>

            <div style="max-height: 400px; overflow-y: auto;">
                <?php foreach($assets as $c): 
                     $bal = getBal($pdo, $user['id'], $c['symbol']);
                ?>
                <div class="token-list-item" onclick="selectToken('<?php echo $c['symbol']; ?>', '<?php echo $c['icon']; ?>', <?php echo $bal; ?>)">
                    <img src="<?php echo $c['icon']; ?>" style="width: 32px; height: 32px; border-radius: 50%; margin-right: 15px;">
                    <div style="flex: 1;">
                        <div style="font-weight: bold; color: white;"><?php echo $c['symbol']; ?></div>
                        <div style="font-size: 11px; color: #aaa;"><?php echo $c['name']; ?></div>
                    </div>
                    <div style="text-align: right; font-size: 12px; color: gold;">
                        <?php echo $bal; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
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
        // --- DATA & CONFIG ---
        const limits = {
            deposit: <?php echo $min_deposit; ?>,
            withdraw: <?php echo $min_withdraw; ?>,
            swap: <?php echo $min_swap; ?>
        };

        // Real-time prices object
        const prices = {}; 
        
        // Load PHP assets into JS
        const assetData = <?php echo json_encode($assets); ?>;
        
        // --- 1. PRICE FETCHING (BINANCE API) ---
        async function fetchPrices() {
            try {
                // Fetch basic prices (USDT base)
                // Note: For demo, we assume USDT = 1.
                prices['USDT'] = 1.00; 

                // Fetch others
                const symbols = assetData.filter(a => a.symbol !== 'USDT').map(a => a.symbol + 'USDT');
                
                for(let pair of symbols) {
                    let res = await fetch('https://api.binance.com/api/v3/ticker/24hr?symbol=' + pair);
                    let data = await res.json();
                    
                    let symbol = pair.replace('USDT', '');
                    let price = parseFloat(data.lastPrice);
                    let change = parseFloat(data.priceChangePercent);
                    
                    prices[symbol] = price;
                    
                    // Update DOM
                    updateAssetRow(symbol, price, change);
                }

                updateTotalBalance();

            } catch (e) {
                console.error("Price fetch error", e);
            }
        }

        function updateAssetRow(symbol, price, change) {
            // Price Text
            let priceEls = document.querySelectorAll(`.live-price[data-symbol='${symbol}']`);
            priceEls.forEach(el => el.innerText = price.toFixed(4));

            // Change Arrow & Color
            let changeEls = document.querySelectorAll(`.price-change[data-symbol='${symbol}']`);
            changeEls.forEach(el => {
                let icon = change >= 0 ? '<i class="fa fa-caret-up"></i>' : '<i class="fa fa-caret-down"></i>';
                let colorClass = change >= 0 ? 'price-up' : 'price-down';
                el.innerHTML = `${icon} ${change.toFixed(2)}%`;
                el.className = `price-change ${colorClass}`;
            });

            // USD Balance Value
            let balEls = document.querySelectorAll(`.balance-usd[data-symbol='${symbol}']`);
            balEls.forEach(el => {
                let amt = parseFloat(el.getAttribute('data-bal'));
                el.innerText = (amt * price).toFixed(2);
            });
        }

        function updateTotalBalance() {
            let total = 0;
            document.querySelectorAll('.balance-usd').forEach(el => {
                let val = parseFloat(el.innerText);
                if(!isNaN(val)) total += val;
            });
            // Add USDT base value manually if not in loop above
            let usdtBal = parseFloat(document.querySelector(`.balance-usd[data-symbol='USDT']`).getAttribute('data-bal'));
            if(usdtBal) total += usdtBal; // USDT is 1:1

            document.getElementById('totalUsdBalance').innerText = total.toFixed(2);
        }

        fetchPrices();
        setInterval(fetchPrices, 10000); // Refresh every 10s

        // --- 2. MODAL LOGIC ---
        function openModal(id) { 
            document.getElementById(id).style.display = 'block'; 
            if(id === 'swapModal') initSwap();
        }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function copyText(id) {
            let text = document.getElementById(id).innerText;
            navigator.clipboard.writeText(text);
            alert("Copied!");
        }

        // --- 3. SWAP LOGIC ---
        let swapState = { from: 'USDT', to: 'TON', selectorMode: null };

        function initSwap() {
            // Set defaults
            setTokenUI('from', assetData[0]); // USDT
            setTokenUI('to', assetData[1]); // TON
            document.getElementById('swapStep1').style.display = 'block';
            document.getElementById('swapStep2').style.display = 'none';
        }

        function openTokenSelect(mode) {
            swapState.selectorMode = mode; // 'from' or 'to'
            document.getElementById('tokenModal').style.display = 'block';
        }

        function selectToken(symbol, icon, bal) {
            let mode = swapState.selectorMode;
            let tokenObj = assetData.find(a => a.symbol === symbol);
            
            if (mode === 'from') {
                swapState.from = symbol;
                document.getElementById('swapFromBal').innerText = bal;
            } else {
                swapState.to = symbol;
            }
            
            setTokenUI(mode, tokenObj);
            document.getElementById('tokenModal').style.display = 'none';
            calcSwapOutput();
        }

        function setTokenUI(mode, token) {
            document.getElementById(`swap${mode.charAt(0).toUpperCase() + mode.slice(1)}Symbol`).innerText = token.symbol;
            document.getElementById(`swap${mode.charAt(0).toUpperCase() + mode.slice(1)}Icon`).src = token.icon;
        }

        function flipSwap() {
            // Swap symbols
            let temp = swapState.from;
            swapState.from = swapState.to;
            swapState.to = temp;
            
            // Re-render UI
            let t1 = assetData.find(a => a.symbol === swapState.from);
            let t2 = assetData.find(a => a.symbol === swapState.to);
            setTokenUI('from', t1);
            setTokenUI('to', t2);
            
            // Update Balance for new 'from'
            let balEl = document.querySelector(`.balance-usd[data-symbol='${t1.symbol}']`); 
            // Need actual balance quantity, not USD. Fetch from DOM logic or passed variable.
            // Simplified: just reset input
            document.getElementById('swapFromInput').value = '';
            document.getElementById('swapToInput').value = '';
        }

        function setSwapPercent(pct) {
            let bal = parseFloat(document.getElementById('swapFromBal').innerText);
            if(bal > 0) {
                document.getElementById('swapFromInput').value = (bal * pct).toFixed(4);
                calcSwapOutput();
            }
        }

        function calcSwapOutput() {
            let qty = parseFloat(document.getElementById('swapFromInput').value);
            if(!qty) { document.getElementById('swapToInput').value = ''; return; }

            let pFrom = prices[swapState.from] || 1;
            let pTo = prices[swapState.to] || 1;

            // Logic: (Qty * PriceFrom) / PriceTo
            let valUsd = qty * pFrom;
            let qtyTo = valUsd / pTo;

            document.getElementById('swapToInput').value = qtyTo.toFixed(6);
        }

        function goToReview() {
            let qty = parseFloat(document.getElementById('swapFromInput').value);
            if(!qty || qty < limits.swap) {
                alert(`Minimum swap amount is $${limits.swap} value.`);
                return;
            }
            
            // Populate Review
            document.getElementById('reviewSendAmt').innerText = qty;
            document.getElementById('reviewSendSym').innerText = swapState.from;
            document.getElementById('reviewGetAmt').innerText = document.getElementById('swapToInput').value;
            document.getElementById('reviewGetSym').innerText = swapState.to;
            
            // Rate
            let pFrom = prices[swapState.from] || 1;
            let pTo = prices[swapState.to] || 1;
            let rate = pFrom / pTo;
            document.getElementById('reviewRate').innerText = `1 ${swapState.from} ≈ ${rate.toFixed(4)} ${swapState.to}`;

            // Switch Screen
            document.getElementById('swapStep1').style.display = 'none';
            document.getElementById('swapStep2').style.display = 'block';
        }

        function backToSwapInput() {
            document.getElementById('swapStep1').style.display = 'block';
            document.getElementById('swapStep2').style.display = 'none';
        }

        // --- 4. SUBMIT FUNCTIONS (MOCK) ---
        function submitDeposit() {
            alert("Deposit request submitted! Admin will approve.");
            closeModal('depositModal');
        }
        function submitWithdraw() {
            let amt = document.getElementById('wdAmount').value;
            if(amt < limits.withdraw) { alert("Below min withdraw limit!"); return; }
            alert("Withdraw request sent.");
            closeModal('withdrawModal');
        }
        function submitSwap() {
            alert("Swap Successful!");
            closeModal('swapModal');
            location.reload();
        }
        
        // Mock Address Update for Deposit
        function updateDepAddress() {
            // In real app, fetch from DB via API based on selected coin
            document.getElementById('depAddressText').innerText = "T9yX....(Dynamic Address from Admin)...7z";
        }
        updateDepAddress(); // Init

    </script>
</body>
</html>
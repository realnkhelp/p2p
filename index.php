<?php
/*
File: index.php
Purpose: Home Page - With Auto Database Sync & Dynamic Admin Wallets
*/

require_once 'includes/functions.php';

// 1. Settings Fetch
$settings = getSettings($pdo);

// 2. Minimum Limit
$min_limit = isset($settings['min_withdraw_limit']) ? floatval($settings['min_withdraw_limit']) : 10.00;

// 3. Admin Wallet Logic (Dynamic Parsing)
$admin_wallets = [];
$raw_wallets = $settings['admin_wallets_json'];

// JSON decode karke dekhte hain ki kya data hai
$decoded = json_decode($raw_wallets, true);

if (is_array($decoded)) {
    // Agar JSON sahi hai (New Format: {"USDT_TRC20":"Tx..", "USDT_BEP20":"0x.."})
    $admin_wallets = $decoded;
} else {
    // Agar purana format hai (Sirf string)
    $admin_wallets['USDT (Default)'] = $raw_wallets;
}

// 4. Initial PHP User Setup (Fallback)
$tg_id = 123456789; 
$first_name = "Guest";
$username = "guest_user";

if (isset($_GET['tg_id'])) {
    $tg_id = cleanInput($_GET['tg_id']);
    $first_name = cleanInput($_GET['first_name'] ?? 'User');
}

// Note: Asli login ab JavaScript se hoga taaki photo update ho sake
$user = getOrCreateUser($pdo, $tg_id, $first_name, $username);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>P2P Exchange</title>
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="assets/js/main.js"></script>
    
    <style>
        #errorAlert {
            display: none;
            background-color: #ff4d4d;
            color: white;
            padding: 10px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            border: 1px solid #ff0000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .profile-pic {
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid gold;
        }
        .mt-3 { margin-top: 15px; }
        .mb-2 { margin-bottom: 10px; }
        
        .payment-details-box {
            background: rgba(255, 255, 255, 0.05); padding: 10px; border-radius: 5px; border: 1px solid #444; margin-top: 5px;
        }
        
        /* Network Selector Box */
        .network-box {
            background: #222; padding: 10px; border-radius: 8px; border: 1px dashed #555; margin-bottom: 10px;
        }
    </style>
</head>
<body>

    <div class="sticky-header">
        <div class="profile-section">
            <img src="assets/images/user.png" onerror="this.src='https://via.placeholder.com/40'" class="profile-pic" id="userDataImg">
            <div>
                <div class="user-name" id="userDataName"><?php echo htmlspecialchars($user['first_name']); ?></div>
                <div style="font-size: 10px; color: gold;">Verified User</div>
            </div>
        </div>
        <button class="settings-btn" onclick="openSettings()"><i class="fa-solid fa-gear"></i></button>
    </div>

    <div class="container" style="margin-top: 20px;">
        
        <div class="text-center" style="margin-bottom: 20px;">
            <h1 style="font-family: 'Comic Sans MS', cursive; font-size: 32px;">P2P</h1>
            <p style="color: #aaa;">Secure & Fast Exchange</p>
        </div>

        <div id="errorAlert"></div>

        <div class="btn-group">
            <button class="btn" id="btnBuy" onclick="switchTab('buy')" style="background: #28a745; color: white;">
                BUY ₹<?php echo $settings['p2p_buy_rate_margin'] + 90; ?>
            </button>
            <button class="btn" id="btnSell" onclick="switchTab('sell')" style="background: #333; color: white; border: 1px solid #ff4d4d;">
                SELL ₹<?php echo 90 - $settings['p2p_sell_rate_margin']; ?>
            </button>
        </div>

        <div id="buySection" class="card card-gold-border mt-3">
            <h3 class="text-center mb-2">Buy USDT</h3>
            <form id="buyForm" onsubmit="submitOrder(event, 'buy')">
                
                <div class="form-group">
                    <label>1. Send Payment to this UPI</label>
                    <div class="invite-link-box" style="display: flex; justify-content: space-between; align-items: center;">
                        <span id="adminUpi"><?php echo $settings['admin_upi']; ?></span>
                        <i class="fa-regular fa-copy" onclick="copyText('adminUpi')" style="cursor: pointer;"></i>
                    </div>
                    <small style="color: #aaa;">Send money via PhonePe/GPay/Paytm.</small>
                </div>

                <div class="form-group">
                    <label>2. Receiver Wallet Address</label>
                    <input type="text" name="receiver_wallet" placeholder="Paste YOUR Wallet Address" required>
                    <small style="color: gold; font-size: 10px;">We will send USDT to this address.</small>
                </div>

                <div class="form-group">
                    <label>3. Select Network</label>
                    <select name="network" required>
                        <option value="TRC20">USDT (TRC20)</option>
                        <option value="BEP20">USDT (BEP20)</option>
                        <option value="TON">USDT (TON)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>4. Amount (USDT)</label>
                    <input type="number" id="buyAmount" name="amount" placeholder="Min <?php echo $min_limit; ?> USDT" step="0.01" required oninput="calcInr('buy')">
                    <div class="mt-2" style="font-size: 12px; color: gold;">
                        You Pay: ₹<span id="payInr">0.00</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>5. Transaction ID / UTR</label>
                    <input type="text" name="tx_hash" placeholder="Enter 12-digit UTR" required>
                </div>

                <button type="submit" class="btn btn-success mt-3">Submit Buy Request</button>
                <p class="text-center mt-2" style="font-size: 10px; color: #888;">Credited within 15-30 mins.</p>
            </form>
        </div>

        <div id="sellSection" class="card card-gold-border mt-3" style="display: none;">
            <h3 class="text-center mb-2">Sell USDT</h3>
            <form id="sellForm" onsubmit="submitOrder(event, 'sell')">
                
                <div class="form-group">
                    <label>1. Select Network & Send USDT</label>
                    <select id="sellNetworkSelect" onchange="updateAdminWallet()" class="form-control mb-2">
                        <?php foreach($admin_wallets as $net => $addr): ?>
                            <option value="<?php echo $net; ?>" data-addr="<?php echo $addr; ?>">
                                <?php echo str_replace('_', ' ', $net); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="invite-link-box" style="display: flex; justify-content: space-between; align-items: center;">
                        <span id="adminWalletDisplay" style="font-size: 12px; word-break: break-all;">Loading...</span> 
                        <i class="fa-regular fa-copy" onclick="copyText('adminWalletDisplay')" style="cursor: pointer; margin-left:5px;"></i>
                    </div>
                    <small style="color: #ff4d4d; font-size: 11px;">⚠️ Send only to the address shown above matching the network.</small>
                </div>

                <div class="form-group">
                    <label>2. Your Payment Details (To Receive INR)</label>
                    <select name="payment_method" id="sellPaymentMethod" onchange="toggleSellPaymentFields()" required>
                        <option value="UPI">UPI</option>
                        <option value="BANK">Bank Transfer</option>
                    </select>

                    <div class="payment-details-box">
                        <div id="upiInputSection">
                            <label style="font-size: 12px;">Enter UPI ID</label>
                            <input type="text" name="upi_id" placeholder="e.g. user@ybl" style="margin-top:5px;">
                        </div>

                        <div id="bankInputSection" style="display: none;">
                            <label style="font-size: 12px;">Bank Name</label>
                            <input type="text" name="bank_name" placeholder="e.g. SBI, HDFC" style="margin-top:5px; margin-bottom:10px;">
                            <label style="font-size: 12px;">Account Holder Name</label>
                            <input type="text" name="account_holder" placeholder="Name on Passbook" style="margin-bottom:10px;">
                            <label style="font-size: 12px;">Account Number</label>
                            <input type="text" name="account_number" placeholder="Account No." style="margin-bottom:10px;">
                            <label style="font-size: 12px;">IFSC Code</label>
                            <input type="text" name="ifsc_code" placeholder="IFSC Code" style="margin-bottom:5px;">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>3. Amount (USDT)</label>
                    <input type="number" id="sellAmount" name="amount" placeholder="Min <?php echo $min_limit; ?> USDT" step="0.01" required oninput="calcInr('sell')">
                    <div class="mt-2" style="font-size: 12px; color: gold;">
                        You Receive: ₹<span id="getInr">0.00</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>4. Transaction Hash (Proof)</label>
                    <input type="text" name="tx_hash" placeholder="Paste TX Hash" required>
                </div>

                <button type="submit" class="btn btn-danger mt-3">Submit Sell Request</button>
            </form>
        </div>

    </div>

    <div class="bottom-nav">
        <a href="index.php" class="nav-item active"><i class="fa-solid fa-house"></i><span>Home</span></a>
        <a href="wallet.php" class="nav-item"><i class="fa-solid fa-wallet"></i><span>Wallet</span></a>
        <a href="invite.php" class="nav-item"><i class="fa-solid fa-user-plus"></i><span>Invite</span></a>
        <a href="history.php" class="nav-item"><i class="fa-solid fa-clock-rotate-left"></i><span>History</span></a>
    </div>

    <div id="settingsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:2000; align-items:center; justify-content:center;">
        <div class="card" style="width: 80%; text-align: center; border: 1px solid gold;">
            <h3>Settings</h3>
            <hr style="border-color: #333; margin: 10px 0;">
            <button class="btn mt-2" onclick="alert('Version 1.0.0')">About Us</button>
            <button class="btn mt-2" onclick="window.location.href='<?php echo $settings['support_url']; ?>'">Support</button>
            <button class="btn mt-2 btn-danger" onclick="document.getElementById('settingsModal').style.display='none'">Close</button>
        </div>
    </div>

    <script>
        // --- 1. TELEGRAM AUTO LOGIN (BACKEND SYNC) ---
        const tg = window.Telegram.WebApp;
        tg.expand();
        
        let currentTgId = <?php echo $tg_id; ?>;
        
        if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
            const user = tg.initDataUnsafe.user;
            
            // A. Visual Update (Frontend)
            currentTgId = user.id;
            const fullName = user.first_name + (user.last_name ? ' ' + user.last_name : '');
            document.getElementById('userDataName').innerText = fullName;
            if (user.photo_url) {
                document.getElementById('userDataImg').src = user.photo_url;
            }

            // B. SERVER SYNC (Send Data to Database via API) -> Ye hai wo MAGIC Logic
            const startParam = tg.initDataUnsafe.start_param || ''; // Referral Code
            
            const loginData = new FormData();
            loginData.append('tg_id', user.id);
            loginData.append('first_name', user.first_name);
            loginData.append('last_name', user.last_name || '');
            loginData.append('username', user.username || '');
            loginData.append('photo_url', user.photo_url || '');
            loginData.append('ref_id', startParam);

            fetch('api/login.php', {
                method: 'POST',
                body: loginData
            })
            .then(res => res.json())
            .then(data => {
                console.log('User Synced with DB:', data);
            })
            .catch(err => console.error('Login Sync Failed', err));
        }

        // --- 2. DYNAMIC SELL WALLET ---
        function updateAdminWallet() {
            const select = document.getElementById('sellNetworkSelect');
            if(select.options.length > 0) {
                const selectedOption = select.options[select.selectedIndex];
                const address = selectedOption.getAttribute('data-addr');
                document.getElementById('adminWalletDisplay').innerText = address;
            } else {
                document.getElementById('adminWalletDisplay').innerText = "No Admin Wallet Set";
            }
        }
        // Run once on load
        updateAdminWallet();

        // --- 3. OTHER LOGIC (Calculations, Tabs, Forms) ---
        const minLimit = <?php echo $min_limit; ?>;
        const buyRate = <?php echo $settings['p2p_buy_rate_margin'] + 90; ?>;
        const sellRate = <?php echo 90 - $settings['p2p_sell_rate_margin']; ?>;

        function showError(message) {
            const alertBox = document.getElementById('errorAlert');
            alertBox.innerText = message;
            alertBox.style.display = 'block';
            setTimeout(() => { alertBox.style.display = 'none'; }, 3000);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function switchTab(type) {
            document.getElementById('errorAlert').style.display = 'none';
            if(type === 'buy') {
                document.getElementById('buySection').style.display = 'block';
                document.getElementById('sellSection').style.display = 'none';
                document.getElementById('btnBuy').style.background = '#28a745';
                document.getElementById('btnSell').style.background = '#333';
            } else {
                document.getElementById('buySection').style.display = 'none';
                document.getElementById('sellSection').style.display = 'block';
                document.getElementById('btnBuy').style.background = '#333';
                document.getElementById('btnSell').style.background = '#ff4d4d';
            }
        }

        function toggleSellPaymentFields() {
            const method = document.getElementById('sellPaymentMethod').value;
            const upiSection = document.getElementById('upiInputSection');
            const bankSection = document.getElementById('bankInputSection');
            const upiInput = upiSection.querySelector('input');
            const bankInputs = bankSection.querySelectorAll('input');

            if (method === 'UPI') {
                upiSection.style.display = 'block'; bankSection.style.display = 'none';
                upiInput.setAttribute('required', 'true');
                bankInputs.forEach(input => input.removeAttribute('required'));
            } else {
                upiSection.style.display = 'none'; bankSection.style.display = 'block';
                bankInputs.forEach(input => input.setAttribute('required', 'true'));
                upiInput.removeAttribute('required');
            }
        }
        toggleSellPaymentFields();

        function calcInr(type) {
            if(type === 'buy') {
                let amt = document.getElementById('buyAmount').value;
                document.getElementById('payInr').innerText = (amt * buyRate).toFixed(2);
            } else {
                let amt = document.getElementById('sellAmount').value;
                document.getElementById('getInr').innerText = (amt * sellRate).toFixed(2);
            }
        }

        function openSettings() { document.getElementById('settingsModal').style.display = 'flex'; }
        
        function copyText(elementId) {
            let text = document.getElementById(elementId).innerText;
            navigator.clipboard.writeText(text).then(() => alert('Copied!')).catch(() => prompt("Copy:", text));
        }

        // Submit Order
        function submitOrder(e, type) {
            e.preventDefault();
            let btn = e.target.querySelector('button');
            let originalText = btn.innerText;
            
            let form = document.getElementById(type === 'buy' ? 'buyForm' : 'sellForm');
            let formData = new FormData(form);
            let inputAmount = parseFloat(formData.get('amount'));

            if (isNaN(inputAmount) || inputAmount < minLimit) {
                showError("❌ Minimum Amount is " + minLimit + " USDT"); return; 
            }

            btn.innerText = "Processing..."; btn.disabled = true;
            formData.append('type', type);
            formData.append('tg_id', currentTgId); 
            formData.append('asset', 'USDT');

            fetch('api/submit_order.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) { // Note: 'success' key based on new api/submit_order.php
                    alert("✅ " + data.message);
                    form.reset();
                    setTimeout(() => { window.location.href = 'history.php'; }, 1000);
                } else {
                    showError("⚠️ " + (data.message || data.status));
                }
            })
            .catch(err => showError("Network Error"))
            .finally(() => { btn.innerText = originalText; btn.disabled = false; });
        }
        
        document.addEventListener('contextmenu', event => event.preventDefault());
    </script>
</body>
</html>

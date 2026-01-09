<?php
require_once 'includes/functions.php';

$settings = getSettings($pdo);

$tg_id = 123456789; 
$first_name = "Guest";
$username = "guest_user";

if (isset($_GET['tg_id'])) {
    $tg_id = cleanInput($_GET['tg_id']);
    $first_name = cleanInput($_GET['first_name'] ?? 'User');
}

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
</head>
<body>

    <div class="sticky-header">
        <div class="profile-section">
            <img src="assets/images/user.png" onerror="this.src='https://via.placeholder.com/40'" class="profile-pic" id="userDataImg">
            <div>
                <div class="user-name" id="userDataName"><?php echo $user['first_name']; ?></div>
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

        <div class="btn-group">
            <button class="btn" id="btnBuy" onclick="switchTab('buy')" style="background: #28a745; color: white;">
                BUY ₹<?php echo $settings['p2p_buy_rate_margin'] + 90; ?>
            </button>
            <button class="btn" id="btnSell" onclick="switchTab('sell')" style="background: #333; color: white; border: 1px solid #ff4d4d;">
                SELL ₹<?php echo 90 - $settings['p2p_sell_rate_margin']; ?>
            </button>
        </div>

        <div id="buySection" class="card card-gold-border">
            <h3 class="text-center mb-2">Buy USDT</h3>
            
            <form id="buyForm" onsubmit="submitOrder(event, 'buy')">
                
                <div class="form-group">
                    <label>Send Payment to this UPI/Bank</label>
                    <div class="invite-link-box" style="display: flex; justify-content: space-between; align-items: center;">
                        <span id="adminUpi"><?php echo $settings['admin_upi']; ?></span>
                        <i class="fa-regular fa-copy" onclick="copyText('adminUpi')" style="cursor: pointer;"></i>
                    </div>
                    <small style="color: #aaa;">Copy UPI ID and send money via PhonePe/GPay.</small>
                </div>

                <div class="form-group">
                    <label>Select Network</label>
                    <select name="network" required>
                        <option value="TRC20">USDT (TRC20)</option>
                        <option value="BEP20">USDT (BEP20)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Amount (USDT)</label>
                    <input type="number" id="buyAmount" name="amount" placeholder="Min 10 USDT" required oninput="calcInr('buy')">
                    <div class="mt-2" style="font-size: 12px; color: gold;">
                        You Pay: ₹<span id="payInr">0.00</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Transaction ID / UTR</label>
                    <input type="text" name="tx_hash" placeholder="Enter 12-digit UTR" required>
                </div>

                <button type="submit" class="btn btn-success">Submit Buy Request</button>
                <p class="text-center mt-2" style="font-size: 10px; color: #888;">Credited within 15-30 mins.</p>
            </form>
        </div>

        <div id="sellSection" class="card card-gold-border" style="display: none;">
            <h3 class="text-center mb-2">Sell USDT</h3>
            
            <form id="sellForm" onsubmit="submitOrder(event, 'sell')">
                
                <div class="form-group">
                    <label>Send USDT to Admin Wallet</label>
                    <div class="invite-link-box" style="display: flex; justify-content: space-between; align-items: center;">
                        <span id="adminWallet">0x123...abc (TRC20)</span> 
                        <i class="fa-regular fa-copy" onclick="copyText('adminWallet')" style="cursor: pointer;"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Your Payment Details (To Receive INR)</label>
                    <select name="payment_method" required>
                        <option value="UPI">UPI</option>
                        <option value="BANK">Bank Transfer</option>
                    </select>
                    <input type="text" name="user_payment_details" placeholder="Enter your UPI ID or Bank Details" class="mt-2" required>
                </div>

                <div class="form-group">
                    <label>Amount (USDT)</label>
                    <input type="number" id="sellAmount" name="amount" placeholder="Min 10 USDT" required oninput="calcInr('sell')">
                    <div class="mt-2" style="font-size: 12px; color: gold;">
                        You Receive: ₹<span id="getInr">0.00</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Transaction Hash (Proof)</label>
                    <input type="text" name="tx_hash" placeholder="Paste TX Hash" required>
                </div>

                <button type="submit" class="btn btn-danger">Submit Sell Request</button>
            </form>
        </div>

    </div>

    <div class="bottom-nav">
        <a href="index.php" class="nav-item active">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>
        <a href="wallet.php" class="nav-item">
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
        const tg = window.Telegram.WebApp;
        tg.expand();

        if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
            document.getElementById('userDataName').innerText = tg.initDataUnsafe.user.first_name;
        }

        const buyRate = <?php echo $settings['p2p_buy_rate_margin'] + 90; ?>;
        const sellRate = <?php echo 90 - $settings['p2p_sell_rate_margin']; ?>;

        function calcInr(type) {
            if(type === 'buy') {
                let amt = document.getElementById('buyAmount').value;
                document.getElementById('payInr').innerText = (amt * buyRate).toFixed(2);
            } else {
                let amt = document.getElementById('sellAmount').value;
                document.getElementById('getInr').innerText = (amt * sellRate).toFixed(2);
            }
        }

        function submitOrder(e, type) {
            e.preventDefault();
            
            let btn = e.target.querySelector('button');
            let originalText = btn.innerText;
            btn.innerText = "Processing...";
            btn.disabled = true;

            let formId = type === 'buy' ? 'buyForm' : 'sellForm';
            let form = document.getElementById(formId);
            let formData = new FormData(form);
            
            formData.append('type', type);
            formData.append('tg_id', <?php echo $tg_id; ?>);
            formData.append('asset', 'USDT');

            fetch('api/submit_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    if(window.Telegram.WebApp) {
                        window.Telegram.WebApp.showPopup({
                            title: 'Success',
                            message: data.message,
                            buttons: [{type: 'ok'}]
                        });
                    } else {
                        alert(data.message);
                    }
                    form.reset();
                    setTimeout(() => { window.location.href = 'history.php'; }, 1000);
                } else {
                    alert("Error: " + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("Something went wrong!");
            })
            .finally(() => {
                btn.innerText = originalText;
                btn.disabled = false;
            });
        }
        
        document.addEventListener('contextmenu', event => event.preventDefault());
    </script>
</body>
</html>

<?php
/*
File: maintenance.php
Purpose: Dynamic Maintenance Page with Auto-Redirect (React Design Replica)
*/
require_once 'includes/db_connect.php';

// 1. Double Check: Agar Maintenance OFF ho chuka hai, to Index par bhejo
$stmt = $pdo->query("SELECT maintenance_mode, maintenance_message, maintenance_end_date, maintenance_end_time FROM settings WHERE id=1");
$s = $stmt->fetch();

if ($s['maintenance_mode'] == 0) {
    header("Location: index.php"); // Auto Open Site
    exit;
}

// 2. Set Values
$msg = !empty($s['maintenance_message']) ? htmlspecialchars($s['maintenance_message']) : "We are currently performing scheduled maintenance.";

// Combine Date & Time for JavaScript
$targetDate = "";
if (!empty($s['maintenance_end_date']) && !empty($s['maintenance_end_time'])) {
    $targetDate = $s['maintenance_end_date'] . ' ' . $s['maintenance_end_time'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #34495e; color: #ffffff; height: 100vh;
            display: flex; align-items: center; justify-content: center; overflow: hidden;
            transition: background-color 0.5s;
        }

        .maintenance-container {
            background-color: #2c3e50; padding: 40px 30px; border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5); max-width: 90%; width: 400px;
            border: 2px solid rgba(255,255,255,0.1); text-align: center;
        }

        .icon-box { color: #63b5e4; font-size: 70px; margin-bottom: 20px; display: inline-block; }
        .spin { animation: spin 4s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        .title { color: #ffffff; font-size: 24px; margin: 0 0 15px 0; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; }
        .description { color: #bdc3c7; font-size: 16px; margin-bottom: 25px; line-height: 1.5; }

        .completion-label { color: #f39c12; font-weight: bold; font-size: 15px; margin-bottom: 15px; display: block; text-transform: uppercase; }

        .countdown { display: flex; justify-content: space-between; gap: 10px; }
        .countdown-item { display: flex; flex-direction: column; background-color: #34495e; padding: 10px 5px; border-radius: 5px; flex-grow: 1; min-width: 60px; border-bottom: 3px solid #f39c12; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .countdown-number { font-size: 26px; font-weight: bold; color: #ffffff; line-height: 1.1; font-family: monospace; }
        .countdown-label { font-size: 11px; color: #bdc3c7; text-transform: uppercase; margin-top: 5px; font-weight: 600; }

        /* Auto-Redirect Message Style */
        .loading-text {
            display: none; /* Hidden by default */
            color: #2ecc71;
            font-size: 18px;
            font-weight: bold;
            margin-top: 20px;
            animation: pulse 1s infinite;
        }
        @keyframes pulse { 0% { opacity: 0.6; } 50% { opacity: 1; } 100% { opacity: 0.6; } }
    </style>
</head>
<body>

    <div class="maintenance-container">
        
        <div class="icon-box"><i class="fa-solid fa-gear spin"></i></div>
        <h1 class="title">Maintenance Mode</h1>
        <p class="description"><?php echo $msg; ?></p>
        
        <div id="timerSection">
            <?php if($targetDate): ?>
                <span class="completion-label">Site opening in:</span>
                <div class="countdown">
                    <div class="countdown-item"><span id="days" class="countdown-number">00</span><span class="countdown-label">Days</span></div>
                    <div class="countdown-item"><span id="hours" class="countdown-number">00</span><span class="countdown-label">Hrs</span></div>
                    <div class="countdown-item"><span id="minutes" class="countdown-number">00</span><span class="countdown-label">Mins</span></div>
                    <div class="countdown-item"><span id="seconds" class="countdown-number">00</span><span class="countdown-label">Secs</span></div>
                </div>
            <?php endif; ?>
        </div>

        <div id="redirectMsg" class="loading-text">
            <i class="fa-solid fa-rocket"></i> Launching Site...
        </div>

    </div>

    <script>
        const targetDateStr = "<?php echo $targetDate; ?>";

        if (targetDateStr) {
            const countDownDate = new Date(targetDateStr).getTime();

            const x = setInterval(function() {
                const now = new Date().getTime();
                const distance = countDownDate - now;

                if (distance < 0) {
                    // --- TIME IS UP ---
                    clearInterval(x);
                    
                    // 1. Hide Timer
                    document.getElementById("timerSection").style.display = "none";
                    
                    // 2. Show Launching Message
                    document.getElementById("redirectMsg").style.display = "block";
                    
                    // 3. Reload Page to trigger DB Update & Redirect
                    setTimeout(function() {
                        window.location.href = "index.php";
                    }, 2000); // 2 second delay for effect
                    
                } else {
                    // --- UPDATE TIMER ---
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    document.getElementById("days").innerText = days.toString().padStart(2, '0');
                    document.getElementById("hours").innerText = hours.toString().padStart(2, '0');
                    document.getElementById("minutes").innerText = minutes.toString().padStart(2, '0');
                    document.getElementById("seconds").innerText = seconds.toString().padStart(2, '0');
                }
            }, 1000);
        }
    </script>
</body>
</html>

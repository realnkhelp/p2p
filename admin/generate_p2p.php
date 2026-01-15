<?php
/*
File: admin/generate_pass.php
Purpose: Tool to generate Hash for manual database update
*/

$output = "";

if (isset($_POST['password'])) {
    $pass = $_POST['password'];
    // Ye code password ko encrypt kar dega
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    
    $output = "
    <div style='background: #e1f5fe; padding: 15px; border: 1px solid #0288d1; color: #01579b; margin-top: 20px;'>
        <strong>Password:</strong> $pass <br><br>
        <strong>Encrypted Hash (Database me ye dalein):</strong><br>
        <textarea style='width:100%; height: 60px; margin-top:5px;'>$hash</textarea>
        <br><br>
        <small>Copy this long code and paste it into the 'password' column in your database.</small>
    </div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; }
        .card { background: white; padding: 20px; max-width: 500px; margin: 0 auto; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        input { width: 90%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; }
        button { background: #28a745; color: white; border: none; padding: 10px 20px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <h3>Generate Admin Password Hash</h3>
        <p>Enter the password you want to use (e.g. 12345)</p>
        
        <form method="POST">
            <input type="text" name="password" placeholder="Enter new password" required>
            <button type="submit">Generate Hash</button>
        </form>

        <?php echo $output; ?>
    </div>
</body>
</html>

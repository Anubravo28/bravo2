<?php
// Start a secure session to remember the admin login state
session_start();

$error = '';

// Check if the login form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Set your master password restriction here
    if ($password === '6028') {
        $_SESSION['admin_logged_in'] = true;
        header("Location: orders.php");
        exit();
    } else {
        $error = "❌ Invalid Password. Access Denied.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal — Login</title>
    <style>
        :root {
            --bg: #f4f6f9;
            --surface: #ffffff;
            --text: #1e293b;
            --primary: #1d9e75;
            --error: #e11d48;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background-color: var(--bg); color: var(--text); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        
        .login-card {
            background: var(--surface);
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        
        .login-card h2 { font-size: 1.6rem; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .login-card p { color: #64748b; font-size: 0.95rem; margin-bottom: 24px; }
        
        .form-group { text-align: left; margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.88rem; font-weight: 600; color: #475569; margin-bottom: 8px; }
        
        .password-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 1rem;
            outline: none;
            background: #f8fafc;
            transition: border 0.2s ease;
            text-align: center;
            letter-spacing: 4px;
        }
        .password-input:focus { border-color: var(--primary); background: #fff; }
        
        .btn-login {
            width: 100%;
            padding: 13px;
            border: none;
            background: #0f172a;
            color: #fff;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.2s ease;
        }
        .btn-login:hover { background: var(--primary); }
        
        .error-message {
            background: #fff1f2;
            color: var(--error);
            border: 1px solid #ffe4e6;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <h2>The White Angel Kitchen</h2>
    <p>Admin Dashboard Gatekeeper</p>
    
    <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" action="login.php">
        <div class="form-group">
            <label for="password">Enter System Master Password</label>
            <input type="password" id="password" name="password" class="password-input" placeholder="••••" required autocomplete="off">
        </div>
        <button type="submit" class="btn-login">Unlock Dashboard</button>
    </form>
</div>

</body>
</html>
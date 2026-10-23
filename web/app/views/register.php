<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create ID | ZeroThink</title>
    <link rel="stylesheet" href="/assets/css/zerothink_titan.css?v=150">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #000 url('/assets/img/zerothinkmain.png') no-repeat center center fixed;
            background-size: cover;
            display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0;
        }
        .auth-box {
            background: rgba(10, 10, 10, 0.85);
            padding: 40px; border-radius: 12px; border: 1px solid #333;
            width: 100%; max-width: 400px; text-align: center;
            backdrop-filter: blur(10px); box-shadow: 0 0 50px rgba(0,0,0,0.8);
        }
        .auth-logo { height: 60px; margin-bottom: 20px; }
        h2 { color: #fff; margin: 0 0 20px 0; letter-spacing: 2px; text-transform: uppercase; }
        input {
            width: 100%; padding: 12px; margin-bottom: 15px; background: #050505;
            border: 1px solid #333; color: #fff; border-radius: 4px; box-sizing: border-box;
        }
        input:focus { border-color: #a855f7; outline: none; }
        button {
            width: 100%; padding: 12px; background: #a855f7; color: #fff; font-weight: bold;
            border: none; border-radius: 4px; cursor: pointer; text-transform: uppercase;
        }
        button:hover { background: #be7cff; box-shadow: 0 0 15px rgba(168, 85, 247, 0.4); }
        .links { margin-top: 20px; font-size: 0.9rem; }
        .links a { color: #888; text-decoration: none; }
        .links a:hover { color: #fff; }
    </style>
</head>
<body>
    <div class="auth-box">
        <img src="/assets/img/logo_nobg.png" alt="Z" class="auth-logo">
        <h2>Operator Access</h2>
        
        <?php if(isset($error)): ?>
            <div style="color: #f55; margin-bottom: 15px; font-size: 0.9rem;"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="/register">
            <input type="text" name="username" placeholder="OPERATOR NAME" required>
            <input type="email" name="email" placeholder="EMAIL ADDRESS" required>
            <input type="password" name="password" placeholder="SECURE KEY (PASSWORD)" required>
            <button type="submit">CREATE ID</button>
        </form>

        <div class="links">
            <a href="/login">Return to Login</a>
        </div>
    </div>
</body>
</html>

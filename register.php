<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new SQLite3(__DIR__ . '/ntbfq.sqlite');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$username || !$password) {
        $error = '用户名和密码不能为空';
    } else {
        $stmt = $db->prepare('SELECT id FROM users WHERE username = :username');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result->fetchArray()) {
            $error = '用户名已存在';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (:username, :password)');
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':password', $hash, SQLITE3_TEXT);
            $stmt->execute();
            $user_id = $db->lastInsertRowID();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>注册</title>
    <style>
        body { background: #f4f6fb; font-family: 'Segoe UI', Arial, sans-serif; }
        .reg-box { max-width: 350px; margin: 80px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 16px rgba(33,150,243,0.10); padding: 32px 28px; }
        h2 { text-align: center; color: #1976d2; margin-bottom: 24px; }
        input[type=text], input[type=password] { width: 100%; padding: 10px; margin: 10px 0 18px 0; border: 1px solid #b0bec5; border-radius: 6px; background: #f8fafc; }
        button { width: 100%; background: linear-gradient(90deg, #1976d2 60%, #42a5f5 100%); color: #fff; border: none; border-radius: 6px; padding: 10px; font-size: 1rem; cursor: pointer; }
        button:hover { background: linear-gradient(90deg, #1565c0 60%, #1976d2 100%); }
        .error { color: #d32f2f; text-align: center; margin-bottom: 12px; }
        .login-link { text-align: center; margin-top: 18px; }
        .login-link a { color: #1976d2; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="reg-box">
        <h2>用户注册</h2>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="text" name="username" placeholder="用户名" required autofocus>
            <input type="password" name="password" placeholder="密码" required>
            <button type="submit">注册</button>
        </form>
        <div class="login-link">已有账号？<a href="login.php">登录</a></div>
    </div>
</body>
</html> 
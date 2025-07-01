<?php
session_start();
header('Content-Type: application/json');
if (isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>true,'redirect'=>'index.php']);
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new SQLite3(__DIR__ . '/ntbfq.sqlite');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    if (!$username || !$password) {
        $error = '用户名和密码不能为空';
    } elseif (!$email || !$phone || !$gender) {
        $error = '邮箱、电话和性别不能为空';
    } else {
        $stmt = $db->prepare('SELECT id FROM users WHERE username = :username');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result->fetchArray()) {
            $error = '用户名已存在';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password, email, phone, gender) VALUES (:username, :password, :email, :phone, :gender)');
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':password', $hash, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
            $stmt->bindValue(':gender', $gender, SQLITE3_TEXT);
            $stmt->execute();
            // 注册成功后不自动登录，返回 success 并提示前端跳转到登录弹框
            echo json_encode(['success'=>true,'next'=>'login']);
            exit;
        }
    }
}
echo json_encode(['success'=>false,'error'=>$error]); 
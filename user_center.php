<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$db = new SQLite3(__DIR__ . '/ntbfq.sqlite');
$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'] ?? '';
$username = $_SESSION['username'] ?? '';
$email = $_SESSION['email'] ?? '';
$gender = $_SESSION['gender'] ?? '';
$phone = $_SESSION['phone'] ?? '';
$bio = $_SESSION['bio'] ?? '';
$create_date = $_SESSION['create_date'] ?? $_SESSION['created_at'] ?? '';
// 分页参数
$page_fav = max(1, intval($_GET['page_fav'] ?? 1));
$page_like = max(1, intval($_GET['page_like'] ?? 1));
$page_his = max(1, intval($_GET['page_his'] ?? 1));
$page_later = max(1, intval($_GET['page_later'] ?? 1));
$page_size = 5;
// 处理头像上传、信息修改
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 修改信息
    $fields = [];
    $params = [];
    if (isset($_POST['nickname'])) {
        $fields[] = 'nickname = :nickname';
        $params[':nickname'] = trim($_POST['nickname']);
    }
    if (isset($_POST['email'])) {
        $fields[] = 'email = :email';
        $params[':email'] = trim($_POST['email']);
    }
    if (isset($_POST['gender'])) {
        $fields[] = 'gender = :gender';
        $params[':gender'] = trim($_POST['gender']);
    }
    if (isset($_POST['bio'])) {
        $fields[] = 'bio = :bio';
        $params[':bio'] = trim($_POST['bio']);
    }
    if (isset($_POST['phone'])) {
        $fields[] = 'phone = :phone';
        $params[':phone'] = trim($_POST['phone']);
    }
    if ($fields) {
        $sql = 'UPDATE users SET ' . implode(',', $fields) . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, SQLITE3_TEXT);
        }
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        $msg = '信息已更新';
    }
    // 修改密码
    if (isset($_POST['old_password'], $_POST['new_password'])) {
        $old = $_POST['old_password'];
        $new = $_POST['new_password'];
        $user = $db->querySingle("SELECT password FROM users WHERE id = $user_id", true);
        if ($user && password_verify($old, $user['password'])) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->exec("UPDATE users SET password = '$hash', updated_at = CURRENT_TIMESTAMP WHERE id = $user_id");
            $msg = '密码已重置';
        } else {
            $msg = '原密码错误';
        }
    }
    // 上传头像
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif'])) {
            $avatar_dir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($avatar_dir)) mkdir($avatar_dir, 0777, true);
            $avatar_name = 'user_' . $user_id . '.' . $ext;
            $avatar_path = $avatar_dir . $avatar_name;
            move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path);
            $avatar_url = 'uploads/avatars/' . $avatar_name;
            $db->exec("UPDATE users SET avatar_url = '$avatar_url', updated_at = CURRENT_TIMESTAMP WHERE id = $user_id");
            $msg = '头像已更新';
        } else {
            $msg = '仅支持jpg/png/gif格式';
        }
    }
}
// 获取用户所有信息
$user = $db->querySingle("SELECT * FROM users WHERE id = $user_id", true);
$avatar_url = $user['avatar_url'] ?? '';
$username = $user['username'] ?? '';
$nickname = $user['nickname'] ?? '';
$email = $user['email'] ?? '';
$gender = $user['gender'] ?? '';
$phone = $user['phone'] ?? '';
$bio = $user['bio'] ?? '';
$create_date = $user['create_date'] ?? $user['created_at'] ?? '';
$update_date = $user['updated_at'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>个人中心</title>
    <style>
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    background: linear-gradient(135deg, #f3e8ff 0%, #e0c3fc 100%);
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #a259e6;
}
.center-layout {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 60px);
    padding: 20px;
}
.center-box {
    background: rgba(243,232,255,0.85) !important;
    border-radius: 22px !important;
    box-shadow: 0 8px 48px 0 rgba(224,195,252,0.18),0 1.5px 8px rgba(224,195,252,0.10) !important;
    padding: 38px 34px 32px 34px !important;
    max-width: 480px !important;
    width: 100% !important;
    margin: 32px 0 !important;
    border: 1.5px solid #e0c3fc !important;
    backdrop-filter: blur(8px) !important;
    transition: box-shadow 0.2s, border 0.2s !important;
}
.center-box:hover {
    box-shadow: 0 16px 64px 0 rgba(224,195,252,0.28),0 2px 12px rgba(224,195,252,0.16) !important;
    border: 1.5px solid #a259e6 !important;
}
.center-box h2 {
    text-align: center !important;
    color: #a259e6 !important;
    margin-bottom: 18px !important;
    letter-spacing: 1px !important;
    font-size: 1.45em !important;
    font-weight: 700 !important;
}
.center-box .msg {
    background: #d0ffd6 !important;
    color: #256029 !important;
    padding: 10px !important;
    border-radius: 8px !important;
    text-align: center !important;
    margin-bottom: 16px !important;
    font-weight: 600 !important;
}
.center-box .avatar-img, .center-box .avatar-img-empty {
    width: 100px !important;
    height: 100px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    background: #e0c3fc !important;
    box-shadow: 0 4px 10px rgba(162, 89, 230, 0.1) !important;
    border: 3px solid #fff !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 2.5em !important;
    color: #a259e6 !important;
    margin: 0 auto 16px !important;
    font-weight: bold !important;
}
.center-box label {
    display: block !important;
    margin-bottom: 6px !important;
    font-weight: 600 !important;
    color: #a259e6 !important;
}
.center-box input,
.center-box select,
.center-box textarea {
    width: 100% !important;
    padding: 12px 16px !important;
    border-radius: 12px !important;
    border: 1.5px solid #e0c3fc !important;
    background: #f8f3ff !important;
    font-size: 1.08em !important;
    color: #a259e6 !important;
    margin-bottom: 12px !important;
    transition: border 0.18s, box-shadow 0.18s, background 0.18s !important;
    box-shadow: 0 1px 4px rgba(162,89,230,0.08) !important;
}
.center-box input:focus,
.center-box select:focus,
.center-box textarea:focus {
    border: 1.5px solid #a259e6 !important;
    outline: none !important;
    box-shadow: 0 2px 8px rgba(224,195,252,0.13) !important;
    background: #f3e8ff !important;
    color: #a259e6 !important;
}
.center-box button {
    background: linear-gradient(90deg,#a259e6 60%,#e0c3fc 100%) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 12px !important;
    padding: 14px 0 !important;
    font-size: 1.12em !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    margin-top: 6px !important;
    box-shadow: 0 2px 8px rgba(224,195,252,0.10) !important;
    transition: background 0.18s, transform 0.13s !important;
}
.center-box button:hover {
    background: linear-gradient(90deg,#8f5fe8 60%,#a259e6 100%) !important;
    color: #fff !important;
    transform: scale(1.03) !important;
    border: 1.5px solid #a259e6 !important;
}
.userbar {
    position: fixed;
    top: 16px;
    right: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #a259e6 60%, #e0c3fc 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    box-shadow: 0 2px 8px rgba(162, 89, 230, 0.1);
}
.userbar a {
    color: #a259e6;
    text-decoration: none;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 6px;
    transition: background 0.2s;
}
.userbar a:hover {
    background: #e0c3fc;
    color: #fff;
}
.form-section-title {
    color: #6b38b1;
    font-size: 1.2em;
    font-weight: bold;
    margin: 24px 0 12px;
    text-align: center;
}
.readonly {
    background: #f3e8ff;
    color: #888;
}
.center-box .uc-username {
    font-size: 1.25em !important;
    font-weight: 700 !important;
    background: #f3e8ff !important;
    color: #a259e6 !important;
    border-radius: 12px !important;
    padding: 10px 18px !important;
    margin: 0 0 12px 0 !important;
    box-shadow: 0 2px 8px rgba(162,89,230,0.08) !important;
    display: inline-block !important;
}
.center-box .uc-regtime {
    display: inline-block !important;
    background: #e0c3fc !important;
    color: #a259e6 !important;
    border-radius: 8px !important;
    padding: 4px 12px !important;
    font-size: 0.98em !important;
    margin: 0 0 12px 0 !important;
}
.center-box .uc-save-btn {
    background: linear-gradient(90deg,#a259e6 60%,#e0c3fc 100%) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 16px !important;
    padding: 16px 0 !important;
    font-size: 1.18em !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    margin-top: 12px !important;
    box-shadow: 0 4px 16px rgba(162,89,230,0.13) !important;
    transition: background 0.18s, transform 0.13s !important;
}
.center-box .uc-save-btn:hover {
    background: linear-gradient(90deg,#8f5fe8 60%,#a259e6 100%) !important;
    color: #fff !important;
    transform: scale(1.04) !important;
    border: 1.5px solid #a259e6 !important;
}
@media (max-width: 600px) {
    .center-box {
        padding: 20px;
    }
    .avatar-img, .avatar-img-empty {
        width: 80px;
        height: 80px;
        font-size: 2em;
    }
}
    </style>
</head>
<body>
    <div class="userbar" style="position:absolute;top:24px;right:40px;display:flex;align-items:center;gap:14px;z-index:10;">
        <span class="avatar"><?= htmlspecialchars(strtoupper(mb_substr($username, 0, 1, 'UTF-8'))) ?></span>
        <a href="index.php">首页</a>
        <a href="logout.php">退出</a>
    </div>
    <div id="main-root-full">
        <div class="center-layout">
            <div class="center-box">
                <h2>个人中心</h2>
                <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
                <form method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:18px;">
                    <div style="display:flex;align-items:center;gap:18px;">
                        <?php if ($avatar_url): ?>
                            <img src="<?= htmlspecialchars($avatar_url) ?>" class="avatar-img" alt="头像">
                        <?php else: ?>
                            <span class="avatar-img-empty">
                                <?= htmlspecialchars(strtoupper(mb_substr($username, 0, 1, 'UTF-8'))) ?>
                            </span>
                        <?php endif; ?>
                        <div>
                            <input type="file" name="avatar" accept="image/*">
                            <div style="font-size:0.98em;color:#888;">支持jpg/png/gif</div>
                        </div>
                    </div>
                    <div class="uc-username"><i class="fa fa-user"></i> <?= htmlspecialchars($username) ?></div>
                    <div>
                        <label>昵称：</label>
                        <input type="text" name="nickname" value="<?= htmlspecialchars($nickname) ?>" maxlength="32" required>
                    </div>
                    <div>
                        <label>邮箱：</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" maxlength="64">
                    </div>
                    <div>
                        <label>性别：</label>
                        <select name="gender">
                            <option value="">请选择</option>
                            <option value="male" <?= $gender=="male"?'selected':'' ?>>男</option>
                            <option value="female" <?= $gender=="female"?'selected':'' ?>>女</option>
                            <option value="other" <?= $gender=="other"?'selected':'' ?>>保密</option>
                        </select>
                    </div>
                    <div>
                        <label>电话：</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>" maxlength="20">
                    </div>
                    <div>
                        <label>简介：</label>
                        <textarea name="bio" rows="2" maxlength="200" style="width:100%;resize:vertical;"><?= htmlspecialchars($bio) ?></textarea>
                    </div>
                    <div class="uc-regtime">注册时间：<?= htmlspecialchars($create_date) ?></div>
                    <div><b>更新时间：</b><?= htmlspecialchars($update_date) ?></div>
                    <div style="margin-top:10px;">
                        <button type="submit" class="uc-save-btn">保存修改</button>
                    </div>
                </form>
                <form method="post" style="margin-top:32px;display:flex;flex-direction:column;gap:12px;">
                    <h3>修改密码</h3>
                    <input type="password" name="old_password" placeholder="原密码" required>
                    <input type="password" name="new_password" placeholder="新密码" required>
                    <button type="submit">重置密码</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 
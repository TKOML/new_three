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
// 分页参数
$page_fav = max(1, intval($_GET['page_fav'] ?? 1));
$page_like = max(1, intval($_GET['page_like'] ?? 1));
$page_his = max(1, intval($_GET['page_his'] ?? 1));
$page_later = max(1, intval($_GET['page_later'] ?? 1));
$page_size = 5;
// 处理头像上传、昵称修改、密码重置
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 修改昵称
    if (isset($_POST['nickname'])) {
        $new_nick = trim($_POST['nickname']);
        $stmt = $db->prepare('UPDATE users SET nickname = :nick WHERE id = :id');
        $stmt->bindValue(':nick', $new_nick, SQLITE3_TEXT);
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        $_SESSION['nickname'] = $new_nick;
        $nickname = $new_nick;
        $msg = '昵称已更新';
    }
    // 重置密码
    if (isset($_POST['old_password'], $_POST['new_password'])) {
        $old = $_POST['old_password'];
        $new = $_POST['new_password'];
        $user = $db->querySingle("SELECT password FROM users WHERE id = $user_id", true);
        if ($user && password_verify($old, $user['password'])) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->exec("UPDATE users SET password = '$hash' WHERE id = $user_id");
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
            $db->exec("UPDATE users SET avatar = '$avatar_name' WHERE id = $user_id");
            $msg = '头像已更新';
        } else {
            $msg = '仅支持jpg/png/gif格式';
        }
    }
}
// 获取头像
$user = $db->querySingle("SELECT avatar FROM users WHERE id = $user_id", true);
$avatar_file = $user['avatar'] ?? '';
$avatar_url = $avatar_file && file_exists(__DIR__ . '/uploads/avatars/' . $avatar_file) ? 'uploads/avatars/' . $avatar_file : '';
// 收藏
$fav_count = $db->querySingle('SELECT COUNT(*) FROM favorites WHERE user_id = ' . intval($user_id));
$fav_stmt = $db->prepare('SELECT filename, favorited_at FROM favorites WHERE user_id = :uid ORDER BY favorited_at DESC LIMIT :limit OFFSET :offset');
$fav_stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
$fav_stmt->bindValue(':limit', $page_size, SQLITE3_INTEGER);
$fav_stmt->bindValue(':offset', ($page_fav-1)*$page_size, SQLITE3_INTEGER);
$fav_res = $fav_stmt->execute();
$favorites = [];
while ($row = $fav_res->fetchArray(SQLITE3_ASSOC)) {
    $favorites[] = $row;
}
// likes表
$like_count = $db->querySingle('SELECT COUNT(*) FROM likes WHERE user_id = ' . intval($user_id));
$db->exec("CREATE TABLE IF NOT EXISTS likes (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, filename TEXT, liked_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, filename))");
$likes = [];
$like_stmt = $db->prepare('SELECT filename, liked_at FROM likes WHERE user_id = :uid ORDER BY liked_at DESC LIMIT :limit OFFSET :offset');
$like_stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
$like_stmt->bindValue(':limit', $page_size, SQLITE3_INTEGER);
$like_stmt->bindValue(':offset', ($page_like-1)*$page_size, SQLITE3_INTEGER);
$like_res = $like_stmt->execute();
while ($row = $like_res->fetchArray(SQLITE3_ASSOC)) {
    $likes[] = $row;
}
// 播放历史
$his_count = $db->querySingle('SELECT COUNT(*) FROM history WHERE user_id = ' . intval($user_id));
$his_stmt = $db->prepare('SELECT filename, played_at FROM history WHERE user_id = :uid ORDER BY played_at DESC LIMIT :limit OFFSET :offset');
$his_stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
$his_stmt->bindValue(':limit', $page_size, SQLITE3_INTEGER);
$his_stmt->bindValue(':offset', ($page_his-1)*$page_size, SQLITE3_INTEGER);
$his_res = $his_stmt->execute();
$history = [];
while ($row = $his_res->fetchArray(SQLITE3_ASSOC)) {
    $history[] = $row;
}
// 稍后再看（分页）
$later_count = $db->querySingle('SELECT COUNT(*) FROM watch_later WHERE user_id = ' . intval($user_id));
$later_stmt = $db->prepare('SELECT filename, added_at FROM watch_later WHERE user_id = :uid ORDER BY added_at DESC LIMIT :limit OFFSET :offset');
$later_stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
$later_stmt->bindValue(':limit', $page_size, SQLITE3_INTEGER);
$later_stmt->bindValue(':offset', ($page_later-1)*$page_size, SQLITE3_INTEGER);
$later_res = $later_stmt->execute();
$watch_later = [];
while ($row = $later_res->fetchArray(SQLITE3_ASSOC)) {
    $watch_later[] = $row;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>用户中心</title>
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        body { min-height: 100vh; min-width: 100vw; width: 100vw; height: 100vh; box-sizing: border-box; background: #f4f6fb; font-family: 'Segoe UI', Arial, sans-serif; }
        #main-root-full {
            min-height: 100vh;
            min-width: 100vw;
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
            background: #f4f6fb;
            padding-top: 80px;
        }
        .center-layout { flex: 1; display: flex; width: 100vw; margin: 0; align-items: flex-start; }
        .sidebar { width: 180px; background: #f8fafc; border-radius: 14px; box-shadow: 0 2px 8px rgba(33,150,243,0.06); padding: 32px 0 24px 0; margin-right: 32px; display: flex; flex-direction: column; align-items: center; gap: 18px; position: sticky; top: 32px; height: fit-content; min-width: 120px; }
        .sidebar a { display: block; width: 100%; text-align: center; color: #1976d2; font-weight: 500; text-decoration: none; padding: 10px 0; border-radius: 6px; transition: background 0.18s; font-size: 1.08em; }
        .sidebar a:hover, .sidebar a.active { background: #e3eaf2; }
        .center-box { flex: 1; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(33,150,243,0.08), 0 1.5px 4px rgba(0,0,0,0.04); padding: 32px 28px 24px 28px; min-width: 0; }
        h2 { color: #1976d2; margin-bottom: 18px; }
        .section { margin-bottom: 36px; }
        ul { list-style: none; padding: 0; }
        li { background: #f0f7ff; margin-bottom: 8px; border-radius: 6px; padding: 8px 14px; transition: background 0.2s; }
        li:hover { background: #e3eaf2; }
        .filename { color: #1976d2; font-weight: 500; }
        .time { color: #888; font-size: 0.96em; margin-left: 8px; }
        .empty { color: #aaa; text-align: center; margin: 18px 0; }
        .user-info-wrap { display: flex; flex-direction: column; align-items: center; gap: 32px; margin-bottom: 40px; width: 100%; }
        .user-info-row { display: flex; flex-direction: row; gap: 32px; width: 100%; justify-content: center; }
        .user-info-row2 { display: flex; flex-direction: row; gap: 32px; width: 100%; justify-content: center; }
        .user-card { background: #f8fafc; border-radius: 12px; box-shadow: 0 2px 8px rgba(33,150,243,0.06); padding: 28px 32px 22px 32px; min-width: 220px; max-width: 400px; width: 100%; display: flex; flex-direction: column; align-items: center; box-sizing: border-box; }
        .user-card h3 { color: #1976d2; margin-bottom: 18px; font-size: 1.15em; }
        .avatar-img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; background: #e3eaf2; margin-bottom: 0; }
        .avatar-img-empty { width: 80px; height: 80px; border-radius: 50%; background: #e3eaf2; display: flex; align-items: center; justify-content: center; font-size: 2.2em; color: #1976d2; margin-bottom: 0; }
        .user-card-avatar-row { display: flex; flex-direction: row; align-items: center; gap: 24px; width: 100%; justify-content: center; }
        .user-card-avatar-upload { display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
        .user-card form { width: 100%; display: flex; flex-direction: column; align-items: center; gap: 12px; }
        .user-card input[type=text], .user-card input[type=password], .user-card input[type=file] { padding: 8px 12px; border: 1px solid #b0bec5; border-radius: 6px; background: #fff; width: 100%; box-sizing: border-box; }
        .user-card button { background: linear-gradient(90deg, #1976d2 60%, #42a5f5 100%); color: #fff; border: none; border-radius: 6px; padding: 8px 0; width: 100%; font-size: 1em; cursor: pointer; margin-top: 4px; transition: background 0.2s; }
        .user-card button:hover { background: linear-gradient(90deg, #1565c0 60%, #1976d2 100%); }
        .userbar { position: absolute; top: 24px; right: 40px; display: flex; align-items: center; gap: 14px; z-index: 10; }
        .avatar { width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #1976d2 60%, #42a5f5 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.3em; font-weight: bold; box-shadow: 0 2px 8px rgba(33,150,243,0.10); user-select: none; }
        .userbar .username { font-weight: 500; color: #1976d2; font-size: 1.08em; margin-right: 8px; }
        .userbar a { color: #1976d2; background: none; border: none; font-size: 1em; cursor: pointer; text-decoration: none; margin-left: 2px; padding: 4px 8px; border-radius: 4px; transition: background 0.2s; }
        .userbar a:hover { background: #e3eaf2; }
        .msg { color: #388e3c; text-align: center; margin-bottom: 12px; font-weight: bold; }
        @media (max-width: 1200px) {
            .center-layout { max-width: 98vw; }
        }
        @media (max-width: 900px) {
            .center-layout { flex-direction: column; max-width: 100vw; }
            .sidebar { flex-direction: row; width: 100%; margin: 0 0 24px 0; border-radius: 10px; justify-content: center; position: static; min-width: 0; }
            .user-info-row2 { flex-direction: column; gap: 24px; }
        }
        @media (max-width: 700px) {
            .center-box { padding: 12px 2vw; }
            .user-card { padding: 14px 4vw; min-width: 0; }
            .sidebar { padding: 12px 0 10px 0; }
        }
        @media (max-width: 500px) {
            .sidebar a { font-size: 0.98em; padding: 7px 0; }
            .user-card { padding: 8px 2vw; }
        }
        html { scroll-behavior: smooth; }
    </style>
</head>
<body>
    <div class="userbar">
        <span class="avatar"><?= htmlspecialchars(strtoupper(mb_substr($username, 0, 1, 'UTF-8'))) ?></span>
        <a href="index.php">首页</a>
        <a href="logout.php">退出</a>
    </div>
    <div id="main-root-full">
        <div class="center-layout">
            <div class="center-box" style="display:flex;justify-content:center;align-items:center;min-height:60vh;">
                <?php if ($avatar_url): ?>
                    <img src="<?= $avatar_url ?>" class="avatar-img" alt="头像" style="width:120px;height:120px;">
                <?php else: ?>
                    <span class="avatar-img-empty" style="width:120px;height:120px;font-size:3em;display:flex;align-items:center;justify-content:center;">
                        <?= htmlspecialchars(strtoupper(mb_substr($username, 0, 1, 'UTF-8'))) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
    // 侧边栏高亮
    const navs = document.querySelectorAll('.sidebar a');
    window.addEventListener('scroll', function() {
      let fromTop = window.scrollY + 120;
      navs.forEach(link => {
        let section = document.querySelector(link.getAttribute('href'));
        if (section && section.offsetTop <= fromTop && section.offsetTop + section.offsetHeight > fromTop) {
          navs.forEach(l => l.classList.remove('active'));
          link.classList.add('active');
        }
      });
    });
    </script>
</body>
</html> 
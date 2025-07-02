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
    if (isset($_POST['username'])) {
        $fields[] = 'username = :username';
        $params[':username'] = trim($_POST['username']);
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
// 获取播放记录（最新20条）
$play_records = [];
$play_stmt = $db->prepare('SELECT pr.created_at as played_at, m.media_url, m.description, m.tags, m.media_type, m.id as media_id FROM play_records pr JOIN media m ON pr.media_id = m.id WHERE pr.user_id = :uid ORDER BY pr.created_at DESC LIMIT 20');
$play_stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
$play_res = $play_stmt->execute();
while ($row = $play_res->fetchArray(SQLITE3_ASSOC)) {
    $play_records[] = $row;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>个人中心</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        body { background:linear-gradient(135deg,#f3e8ff 0%,#e0c3fc 100%); font-family:'Segoe UI',Arial,sans-serif; }
        .dy-pc-main {
            display:flex;flex-direction:row;justify-content:center;align-items:flex-start;
            width:100vw;max-width:100vw;margin-top:280px;height:calc(100vh - 80px);
            transition:all 0.4s cubic-bezier(.4,0,.2,1);
        }
        .dy-pc-center {
            flex:1 1 0%; min-width:0; height:100%; display:flex;flex-direction:column;align-items:center;justify-content:center;
            width:100vw; max-width:100vw;
        }
        .dy-pc-sidenav {
            width:90px;min-width:90px;display:flex;flex-direction:column;align-items:center;gap:8px;padding-top:20px;background:rgba(255,255,255,0.92);border-radius:18px;margin-right:24px;box-shadow:0 2px 12px rgba(162,89,230,0.06);backdrop-filter:blur(4px);margin-top:64px;
        }
        .dy-pc-sidenav-item {display:flex;flex-direction:column;align-items:center;gap:6px;color:#a259e6;font-size:1.3em;cursor:pointer;padding:14px 0;width:100%;border-radius:12px;transition:color 0.18s,background 0.18s; text-decoration:none;}
        .dy-pc-sidenav-item.active,.dy-pc-sidenav-item:hover {background:linear-gradient(135deg,#a259e6 60%,#e0c3fc 100%);color:#fff;}
        .dy-pc-sidenav-item span {font-size:0.98em;}
        .dy-pc-header {
            height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 48px;background:rgba(255,255,255,0.95);box-shadow:0 2px 12px rgba(162,89,230,0.08);border-bottom:1.5px solid #e0c3fc;position:fixed;top:0;left:0;width:100vw;z-index:100;backdrop-filter:blur(8px);
        }
        .dy-pc-logo {font-size:1.7em;font-weight:700;color:#a259e6;letter-spacing:2px;display:flex;align-items:center;gap:10px;}
        .dy-pc-search {position:relative;flex:1;display:flex;justify-content:center;}
        .dy-pc-search input {width:340px;padding:10px 40px 10px 18px;border-radius:22px;border:none;font-size:1.08em;background:#f3e8ff;color:#a259e6;box-shadow:0 1px 4px rgba(162,89,230,0.08);outline:none;transition:box-shadow 0.18s;}
        .dy-pc-search input:focus {box-shadow:0 2px 12px rgba(162,89,230,0.18);}
        .dy-pc-search .search-icon {position:absolute;right:18px;top:50%;transform:translateY(-50%);color:#a259e6;font-size:1.15em;pointer-events:none;}
        .dy-pc-header-actions {display:flex;align-items:center;gap:18px;}
        .dy-pc-header-btn {background:linear-gradient(90deg,#a259e6 60%,#e0c3fc 100%);color:#fff;border-radius:18px;padding:7px 22px;font-weight:600;font-size:1.08em;border:none;cursor:pointer;box-shadow:0 1px 4px rgba(162,89,230,0.08);transition:background 0.18s,color 0.18s;}
        .dy-pc-header-btn:hover {background:#e0c3fc;color:#fff;}
        .dy-pc-avatar {width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#a259e6 60%,#e0c3fc 100%);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2em;font-weight:bold;box-shadow:0 2px 8px rgba(162,89,230,0.10);user-select:none;border:2px solid #fff;margin-right:32px;}
        .dy-pc-login {color:#a259e6;font-weight:600;text-decoration:none;}
        @media (max-width: 1545px) {
            .dy-pc-header {padding:0 12px;}
            .dy-pc-search input {width:220px;}
            .dy-pc-avatar {margin-right:12px;}
        }
        @media (max-width: 700px) {
            .dy-pc-main {margin-top:60px;height:auto;}
            .dy-pc-center {padding:0;}
        }
        /* 个人中心原有样式 */
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
        .uc-flex-layout {
            display: flex;
            flex-direction: row;
            gap: 48px;
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            align-items: flex-start;
            justify-content: center;
        }
        .uc-profile-card {
            background: rgba(255,255,255,0.95);
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(162,89,230,0.10);
            padding: 38px 32px 32px 32px;
            min-width: 260px;
            max-width: 320px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
        }
        .uc-avatar-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .uc-avatar-img, .uc-avatar-img-empty {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            background: #e0c3fc;
            box-shadow: 0 4px 10px rgba(162, 89, 230, 0.1);
            border: 3px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8em;
            color: #a259e6;
            font-weight: bold;
        }
        .uc-avatar-form {
            margin: 0;
        }
        .uc-avatar-upload-btn {
            display: inline-block;
            background: linear-gradient(90deg,#a259e6 60%,#e0c3fc 100%);
            color: #fff;
            border-radius: 12px;
            padding: 7px 18px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(162,89,230,0.10);
            transition: background 0.18s, color 0.18s;
            border: none;
            margin-top: 32px;
        }
        .uc-avatar-upload-btn:hover {
            background: linear-gradient(90deg,#8f5fe8 60%,#a259e6 100%);
            color: #fff;
        }
        .uc-profile-info {
            width: 100%;
            text-align: center;
        }
        .uc-username {
            font-size: 1.18em;
            font-weight: 700;
            color: #a259e6;
            margin-bottom: 8px;
        }
        .uc-nickname {
            color: #6b38b1;
            margin-bottom: 8px;
        }
        .uc-regtime, .uc-updatetime {
            display: block;
            background: #e0c3fc;
            color: #a259e6;
            border-radius: 8px;
            padding: 4px 12px;
            font-size: 0.98em;
            margin: 0 0 8px 0;
        }
        .uc-detail-card {
            background: rgba(255,255,255,0.98);
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(162,89,230,0.10);
            padding: 38px 38px 32px 38px;
            flex: 1 1 0%;
            min-width: 320px;
            max-width: 1545px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .uc-detail-card h2 {
            color: #a259e6;
            font-size: 1.35em;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: 1px;
        }
        .uc-info-form, .uc-password-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .uc-form-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 16px;
        }
        .uc-form-row label {
            min-width: 60px;
            color: #a259e6;
            font-weight: 600;
        }
        .uc-info-form input,
        .uc-info-form select,
        .uc-info-form textarea,
        .uc-password-form input {
            flex: 1 1 0%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1.5px solid #e0c3fc;
            background: #f8f3ff;
            font-size: 1.08em;
            color: #a259e6;
            margin-bottom: 0;
            transition: border 0.18s, box-shadow 0.18s, background 0.18s;
            box-shadow: 0 1px 4px rgba(162,89,230,0.08);
        }
        .uc-info-form input:focus,
        .uc-info-form select:focus,
        .uc-info-form textarea:focus,
        .uc-password-form input:focus {
            border: 1.5px solid #a259e6;
            outline: none;
            box-shadow: 0 2px 8px rgba(224,195,252,0.13);
            background: #f3e8ff;
            color: #a259e6;
        }
        .uc-save-btn {
            background: linear-gradient(90deg,#a259e6 60%,#e0c3fc 100%);
            color: #fff;
            border: none;
            border-radius: 16px;
            padding: 14px 0;
            font-size: 1.12em;
            font-weight: 700;
            cursor: pointer;
            margin-top: 8px;
            box-shadow: 0 4px 16px rgba(162,89,230,0.13);
            transition: background 0.18s, transform 0.13s;
        }
        .uc-save-btn:hover {
            background: linear-gradient(90deg,#8f5fe8 60%,#a259e6 100%);
            color: #fff;
            transform: scale(1.04);
            border: 1.5px solid #a259e6;
        }
        .msg {
            background: #d0ffd6;
            color: #256029;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 10px;
            font-weight: 600;
        }
        @media (max-width: 900px) {
            .uc-flex-layout {
                flex-direction: column;
                gap: 24px;
                align-items: center;
            }
            .uc-profile-card, .uc-detail-card {
                max-width: 98vw;
                min-width: 0;
            }
        }
        .uc-flex-layout.uc-3col-layout {
            display: flex;
            flex-direction: row;
            gap: 40px;
            width: 100%;
            max-width: 1545px;
            margin: 0 auto;
            align-items: flex-start;
            justify-content: center;
        }
        .uc-profile-card, .uc-detail-card, .uc-password-card {
            background: rgba(255,255,255,0.98);
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(162,89,230,0.10);
            padding: 38px 32px 32px 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
        }
        .uc-profile-card {
            min-width: 260px;
            max-width: 320px;
        }
        .uc-detail-card {
            min-width: 320px;
            max-width: 420px;
            flex: 1 1 0%;
            align-items: stretch;
        }
        .uc-password-card {
            min-width: 260px;
            max-width: 320px;
        }
        .uc-password-card h2 {
            color: #a259e6;
            font-size: 1.18em;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: 1px;
        }
        .uc-password-form {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .uc-password-form .uc-form-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 12px;
        }
        .uc-password-form label {
            min-width: 60px;
            color: #a259e6;
            font-weight: 600;
        }
        @media (max-width: 1545px) {
            .uc-flex-layout.uc-3col-layout {
                gap: 18px;
                max-width: 98vw;
            }
        }
        @media (max-width: 900px) {
            .uc-flex-layout.uc-3col-layout {
                flex-direction: column;
                gap: 24px;
                align-items: center;
            }
            .uc-profile-card, .uc-detail-card, .uc-password-card {
                max-width: 1545px;  
                min-width: 0;
            }
        }
        .uc-history-section {
            margin: 0px auto 0 auto;
            max-width: 1545px;
            padding: 0 24px 48px 24px;
        }
        .uc-history-title {
            color: #a259e6;
            font-size: 1.35em;
            font-weight: 700;
            margin-bottom: 24px;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .uc-history-list {
            display: flex;
            flex-wrap: wrap;
            gap: 28px 24px;
            justify-content: flex-start;
        }
        .uc-history-card {
            display: flex;
            flex-direction: row;
            width: 365px;
            min-height: 110px;
            background: linear-gradient(135deg,#f3e8ff 0%,#e0c3fc 100%);
            border-radius: 18px;
            box-shadow: 0 2px 12px rgba(162,89,230,0.08);
            text-decoration: none;
            color: #6b38b1;
            transition: box-shadow 0.18s, transform 0.13s;
            overflow: hidden;
            border: 1.5px solid #e0c3fc;
            margin-bottom: 0;
        }
        .uc-history-card:hover {
            box-shadow: 0 8px 32px rgba(162,89,230,0.18);
            transform: translateY(-4px) scale(1.03);
            border: 1.5px solid #a259e6;
        }
        .uc-history-thumb-wrap {
            width: 140px;
            height: 100px;
            background: #e0c3fc;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            overflow: hidden;
            margin: 10px 0 10px 10px;
            flex-shrink: 0;
        }
        .uc-history-thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
            background: #e0c3fc;
        }
        .uc-history-info {
            flex: 1 1 0%;
            padding: 14px 18px 10px 18px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
        }
        .uc-history-title-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .uc-history-video-title {
            font-size: 1.08em;
            font-weight: 700;
            color: #a259e6;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        .uc-history-desc {
            color: #6b38b1;
            font-size: 0.98em;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        .uc-history-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 0.96em;
            color: #a259e6;
        }
        .uc-history-tags {
            background: #e0c3fc;
            color: #a259e6;
            border-radius: 8px;
            padding: 2px 10px;
            font-size: 0.95em;
            margin-right: 8px;
        }
        .uc-history-time {
            color: #a259e6;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .uc-history-empty {
            color: #bbb;
            font-size: 1.1em;
            padding: 48px 0;
            text-align: center;
            width: 100%;
        }
        @media (max-width: 900px) {
            .uc-history-list {
                flex-direction: column;
                gap: 18px;
            }
            .uc-history-card {
                width: 98vw;
                min-width: 0;
            }
        }
        .profile-toast {
            position: fixed;
            left: 50%;
            top: 18%;
            transform: translate(-50%, 0);
            background: rgba(162,89,230,0.95);
            color: #fff;
            padding: 14px 32px;
            border-radius: 18px;
            font-size: 1.15em;
            font-weight: 600;
            z-index: 99999;
            box-shadow: 0 2px 16px rgba(162,89,230,0.13);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .profile-toast i {
            font-size: 1.2em;
        }
        .uc-info-flex-wrap {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 38px;
            width: 100%;
        }
        .uc-info-avatar-side {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 140px;
            max-width: 180px;
        }
        .uc-info-main-side {
            flex: 1 1 0%;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .uc-info-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px 28px;
            width: 100%;
            align-items: end;
        }
        .uc-info-form-grid .uc-form-row-bio,
        .uc-info-form-grid .uc-info-form-actions {
            grid-column: 1 / span 2;
        }
        .uc-avatar-img, .uc-avatar-img-empty {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            background: #e0c3fc;
            box-shadow: 0 4px 10px rgba(162, 89, 230, 0.1);
            border: 3px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8em;
            color: #a259e6;
            font-weight: bold;
        }
        .uc-avatar-upload-btn {
            margin-top: 32px;
        }
        .uc-password-form-3col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 18px 24px;
            width: 100%;
            max-width: 1545px;
            align-items: end;
        }
        .uc-password-form-3col .uc-form-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 10px;
            margin-bottom: 0;
        }
        .uc-password-form-3col label {
            min-width: 70px;
            color: #a259e6;
            font-weight: 600;
            margin-bottom: 0;
        }
        .uc-password-form-3col input {
            flex: 1 1 0%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1.5px solid #e0c3fc;
            background: #f8f3ff;
            font-size: 1.08em;
            color: #a259e6;
        }
        .uc-password-form-3col button {
            height: 44px;
            margin-top: 0;
            min-width: 120px;
        }
    </style>
</head>
<body style="background:#f8f3ff;min-height:100vh;">
    <!-- 顶部导航栏 -->
    <div class="dy-pc-header">
        <div class="dy-pc-logo"><i class="fa-solid fa-music"></i> 媒体播放器</div>
        <div class="dy-pc-search">
            <input type="text" placeholder="搜索用户、视频、音乐" />
            <i class="fa-solid fa-search search-icon"></i>
        </div>
        <div class="dy-pc-header-actions">
            <button class="dy-pc-header-btn" id="upload-btn"><i class="fa-solid fa-cloud-arrow-up"></i> 上传</button>
            <button class="dy-pc-header-btn"><i class="fa-solid fa-gem"></i> 创作者中心</button>
            <span class="dy-pc-avatar"><?= htmlspecialchars(strtoupper(mb_substr($username, 0, 1, 'UTF-8'))) ?></span>
            <a href="logout.php" class="dy-pc-login">退出</a>
        </div>
    </div>
    <div class="dy-pc-main">
        <!-- 左侧导航 -->
        <div class="dy-pc-sidenav">
            <a class="dy-pc-sidenav-item" href="index.php"><i class="fa-solid fa-film"></i><span>播放</span></a>
            <a class="dy-pc-sidenav-item active" href="user_center.php" style="cursor:pointer;"><i class="fa-solid fa-user"></i><span>我的</span></a>
            <a href="logout.php" class="dy-pc-sidenav-item" style="color:#a259e6;"><i class="fa-solid fa-sign-out-alt"></i><span>退出</span></a>
        </div>
        <!-- 主内容区 -->
        <div class="dy-pc-center" id="main-content">
            <div class="uc-flex-layout uc-3col-layout">
                <!-- 合并后的个人信息卡片 -->
                <div class="uc-detail-card" style="max-width:1545px;width:100%;margin:0 auto;align-items:stretch;">
                    <h2>个人信息</h2>
                    <div class="uc-info-flex-wrap">
                        <div class="uc-info-avatar-side">
                            <?php if ($avatar_url): ?>
                                <img src="<?= htmlspecialchars($avatar_url) ?>" class="uc-avatar-img" alt="头像">
                            <?php else: ?>
                                <span class="uc-avatar-img-empty">
                                    <?= htmlspecialchars(strtoupper(mb_substr($username, 0, 1, 'UTF-8'))) ?>
                                </span>
                            <?php endif; ?>
                            <form id="avatar-upload-form" enctype="multipart/form-data" method="post" style="margin:0;padding:0;">
                                <input type="file" name="avatar" accept="image/*" id="avatar-input" style="display:none;">
                                <label for="avatar-input" class="uc-avatar-upload-btn"><i class="fa fa-camera"></i> 更换头像</label>
                            </form>
                        </div>
                        <div class="uc-info-main-side">
                            <form method="post" enctype="multipart/form-data" class="uc-info-form uc-info-form-grid">
                                <div class="uc-form-row uc-form-row-bio">
                                    <label>个人说明：</label>
                                    <textarea name="bio" rows="2" maxlength="200" style="width:100%;resize:vertical;"><?= htmlspecialchars($bio) ?></textarea>
                                </div>
                                <div class="uc-form-row">
                                    <label>用户名：</label>
                                    <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" maxlength="32" required>
                                </div>
                                <div class="uc-form-row">
                                    <label>邮箱：</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" maxlength="64">
                                </div>
                                <div class="uc-form-row">
                                    <label>性别：</label>
                                    <select name="gender">
                                        <option value="">请选择</option>
                                        <option value="male" <?= $gender=="male"?'selected':'' ?>>男</option>
                                        <option value="female" <?= $gender=="female"?'selected':'' ?>>女</option>
                                        <option value="other" <?= $gender=="other"?'selected':'' ?>>保密</option>
                                    </select>
                                </div>
                                <div class="uc-form-row">
                                    <label>电话：</label>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>" maxlength="20">
                                </div>
                                <div style="display:flex;gap:18px;width:100%;justify-content:space-between;align-items:center;grid-column:1/span 2;">
                                    <span class="uc-regtime">注册时间：<?= htmlspecialchars($create_date) ?></span>
                                    <button type="submit" class="uc-save-btn" style="max-width:180px;min-width:120px;">保存修改</button>
                                    <span class="uc-updatetime">更新时间：<?= htmlspecialchars($update_date) ?></span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!-- 重置密码卡片 -->
            <div class="uc-detail-card" style="max-width:1545px;width:93%;align-items:center;margin:32px auto 0 auto;">
                <form method="post" class="uc-password-form uc-password-form-3col" style="max-width:1545px">
                    <div class="uc-form-row">
                        <label>原密码</label>
                        <input type="password" name="old_password" placeholder="原密码" required>
                    </div>
                    <div class="uc-form-row">
                        <label>新密码</label>
                        <input type="password" name="new_password" placeholder="新密码" required>
                    </div>
                    <button type="submit" class="uc-save-btn">重置密码</button>
                </form>
            </div>
            <!-- 播放记录区域 -->
            <div class="uc-history-section">
                <h2 class="uc-history-title"><i class="fa fa-history"></i> 播放记录</h2>
                <div class="uc-history-list">
                    <?php if (empty($play_records)): ?>
                        <div class="uc-history-empty">暂无播放记录</div>
                    <?php else: ?>
                        <?php foreach ($play_records as $rec): ?>
                        <a class="uc-history-card" href="index.php?file=<?= urlencode(basename($rec['media_url'])) ?>" target="_blank">
                            <div class="uc-history-thumb-wrap">
                                <video class="uc-history-thumb" src="<?= htmlspecialchars($rec['media_url']) ?>" preload="metadata"></video>
                            </div>
                            <div class="uc-history-info">
                                <div class="uc-history-title-row">
                                    <span class="uc-history-video-title"><?= htmlspecialchars(basename($rec['media_url'])) ?></span>
                                </div>
                                <div class="uc-history-desc"><?= htmlspecialchars($rec['description']) ?></div>
                                <div class="uc-history-meta">
                                    <span class="uc-history-tags">#<?= htmlspecialchars($rec['tags']) ?></span>
                                    <span class="uc-history-time"><i class="fa fa-clock"></i> <?= htmlspecialchars($rec['played_at']) ?></span>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div id="profile-toast" class="profile-toast" style="display:none;"><i class="fa-solid fa-circle-check"></i> 保存成功</div>
    <script>
    function showProfileToast(msg) {
        var toast = document.getElementById('profile-toast');
        toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + msg;
        toast.style.display = 'flex';
        toast.style.opacity = 1;
        setTimeout(function(){
            toast.style.opacity = 0;
            setTimeout(function(){ toast.style.display = 'none'; }, 300);
        }, 1200);
    }
    // 个人信息和个人说明表单AJAX提交
    document.querySelectorAll('.uc-info-form').forEach(function(form){
        form.addEventListener('submit', function(e){
            e.preventDefault();
            var fd = new FormData(form);
            fetch(window.location.pathname, {
                method: 'POST',
                body: fd
            }).then(function(r){
                if(r.ok) return r.text();
                throw new Error('网络错误');
            }).then(function(){
                showProfileToast('保存成功');
            }).catch(function(){
                showProfileToast('保存失败');
            });
        });
    });
    // 重置密码表单AJAX提交
    document.querySelectorAll('.uc-password-form').forEach(function(form){
        form.addEventListener('submit', function(e){
            e.preventDefault();
            var fd = new FormData(form);
            fetch(window.location.pathname, {
                method: 'POST',
                body: fd
            }).then(function(r){
                if(r.ok) return r.text();
                throw new Error('网络错误');
            }).then(function(){
                showProfileToast('重置密码成功');
            }).catch(function(){
                showProfileToast('重置失败');
            });
        });
    });
    // 更换头像表单AJAX提交
    var avatarForm = document.getElementById('avatar-upload-form');
    if (avatarForm) {
        var avatarInput = document.getElementById('avatar-input');
        avatarInput.addEventListener('change', function(e){
            if (!avatarInput.files.length) return;
            var fd = new FormData(avatarForm);
            fetch(window.location.pathname, {
                method: 'POST',
                body: fd
            }).then(function(r){
                if(r.ok) return r.text();
                throw new Error('网络错误');
            }).then(function(){
                showProfileToast('更换头像成功');
                setTimeout(function(){ location.reload(); }, 1200);
            }).catch(function(){
                showProfileToast('更换头像失败');
            });
        });
    }
    </script>
</body>
</html> 
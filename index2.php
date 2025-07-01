<?php
session_start();
// 数据库连接
$db = null;
if (isset($_SESSION['user_id'])) {
    $db = new SQLite3(__DIR__ . '/ntbfq.sqlite');
}
// 处理文件上传和播放历史记录
if (!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}
// 上传视频时插入 media 表
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media'])) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileName = basename($_FILES['media']['name']);
    $targetFile = $uploadDir . $fileName;
    $description = trim($_POST['description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg'];
    $maxSize = 200 * 1024 * 1024; // 200MB
    if (!in_array($_FILES['media']['type'], $allowedTypes)) {
        $error = '仅支持mp4/webm/ogg格式视频';
    } elseif ($_FILES['media']['size'] > $maxSize) {
        $error = '文件过大，最大支持200MB';
    } elseif (move_uploaded_file($_FILES['media']['tmp_name'], $targetFile)) {
        if ($db && isset($_SESSION['user_id'])) {
            // 只在上传时插入 media 表
            $media_url = 'uploads/' . $fileName;
            $creator_id = $_SESSION['user_id'];
            $media_type = pathinfo($fileName, PATHINFO_EXTENSION);
            $stmt = $db->prepare('INSERT INTO media (media_url, creator_id, description, media_type, tags) VALUES (:url, :creator, :desc, :type, :tags)');
            $stmt->bindValue(':url', $media_url, SQLITE3_TEXT);
            $stmt->bindValue(':creator', $creator_id, SQLITE3_INTEGER);
            $stmt->bindValue(':desc', $description, SQLITE3_TEXT);
            $stmt->bindValue(':type', $media_type, SQLITE3_TEXT);
            $stmt->bindValue(':tags', $tags, SQLITE3_TEXT);
            $result = $stmt->execute();
            if ($result) {
                header('Location: ?file=' . urlencode($fileName));
                exit;
            } else {
                $err = $db->lastErrorMsg();
                echo "<div style='color:red'>lastErrorMsg: $err</div>";
            }
        }
    } else {
        $error = '文件上传失败';
    }
}
$mediaFile = isset($_GET['file']) ? $_GET['file'] : '';
$mediaUrl = $mediaFile ? 'uploads/' . $mediaFile : '';
// 记录播放历史（GET 方式访问文件时）
if ($mediaFile) {
    if ($db && isset($_SESSION['user_id'])) {
        $db->exec("CREATE TABLE IF NOT EXISTS play_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            media_id INTEGER,
            played_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        // 只查找 media_id，不插入 media 表
        $stmt = $db->prepare('SELECT id FROM media WHERE media_url = :url');
        $stmt->bindValue(':url', 'uploads/' . $mediaFile, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['id'])) {
            $media_id = $row['id'];
            $stmt = $db->prepare('INSERT INTO play_records (user_id, media_id) VALUES (:uid, :media_id)');
            $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':media_id', $media_id, SQLITE3_INTEGER);
            $stmt->execute();
        }
    } else {
        if (!in_array($mediaFile, $_SESSION['history'])) {
            $_SESSION['history'][] = $mediaFile;
        }
    }
}
// 收藏和喜欢处理
if ($db && isset($_SESSION['user_id'])) {
    // 先查 media_id
    $media_id = null;
    if ($mediaFile) {
        $stmt = $db->prepare('SELECT id FROM media WHERE media_url = :url');
        $stmt->bindValue(':url', 'uploads/' . $mediaFile, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['id'])) {
            $media_id = $row['id'];
        }
    }
    if (isset($_GET['fav']) && isset($_GET['file']) && $media_id) {
        if ($_GET['fav'] === '1') {
            $stmt = $db->prepare('INSERT OR IGNORE INTO favorites (user_id, media_id) VALUES (:uid, :media_id)');
            $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':media_id', $media_id, SQLITE3_INTEGER);
            $stmt->execute();
        } elseif ($_GET['fav'] === '0') {
            $stmt = $db->prepare('DELETE FROM favorites WHERE user_id = :uid AND media_id = :media_id');
            $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':media_id', $media_id, SQLITE3_INTEGER);
            $stmt->execute();
        }
        header('Location: index.php?file=' . urlencode($mediaFile));
        exit;
    }
    if (isset($_GET['like']) && isset($_GET['file']) && $media_id) {
        $db->exec("CREATE TABLE IF NOT EXISTS likes (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, media_id INTEGER, liked_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, media_id))");
        if ($_GET['like'] === '1') {
            $stmt = $db->prepare('INSERT OR IGNORE INTO likes (user_id, media_id) VALUES (:uid, :media_id)');
            $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':media_id', $media_id, SQLITE3_INTEGER);
            $stmt->execute();
        } elseif ($_GET['like'] === '0') {
            $stmt = $db->prepare('DELETE FROM likes WHERE user_id = :uid AND media_id = :media_id');
            $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':media_id', $media_id, SQLITE3_INTEGER);
            $stmt->execute();
        }
        header('Location: index.php?file=' . urlencode($mediaFile));
        exit;
    }
}
// 稍后再看处理
if ($db && isset($_SESSION['user_id'])) {
    $db->exec("CREATE TABLE IF NOT EXISTS watch_later (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, media_id INTEGER, added_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, media_id))");
    // 先查 media_id
    $media_id = null;
    if ($mediaFile) {
        $stmt = $db->prepare('SELECT id FROM media WHERE media_url = :url');
        $stmt->bindValue(':url', 'uploads/' . $mediaFile, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if ($row && isset($row['id'])) {
            $media_id = $row['id'];
        }
    }
    if (isset($_GET['later']) && isset($_GET['file']) && $media_id) {
        if ($_GET['later'] === '1') {
            $stmt = $db->prepare('INSERT OR IGNORE INTO watch_later (user_id, media_id) VALUES (:uid, :media_id)');
            $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':media_id', $media_id, SQLITE3_INTEGER);
            $stmt->execute();
        } elseif ($_GET['later'] === '0') {
            $stmt = $db->prepare('DELETE FROM watch_later WHERE user_id = :uid AND media_id = :media_id');
            $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':media_id', $media_id, SQLITE3_INTEGER);
            $stmt->execute();
        }
        header('Location: index.php?file=' . urlencode($mediaFile));
        exit;
    }
}
// 当前播放文件的收藏/喜欢状态
$is_fav = false;
$is_like = false;
if ($db && isset($_SESSION['user_id']) && $mediaFile) {
    // 查 media_id
    $media_id = null;
    $stmt = $db->prepare('SELECT id FROM media WHERE media_url = :url');
    $stmt->bindValue(':url', 'uploads/' . $mediaFile, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    if ($row && isset($row['id'])) {
        $media_id = $row['id'];
    }
    if ($media_id) {
        $fav_res = $db->querySingle("SELECT 1 FROM favorites WHERE user_id = " . intval($_SESSION['user_id']) . " AND media_id = " . intval($media_id));
        $is_fav = $fav_res ? true : false;
        $db->exec("CREATE TABLE IF NOT EXISTS likes (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, media_id INTEGER, liked_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, media_id))");
        $like_res = $db->querySingle("SELECT 1 FROM likes WHERE user_id = " . intval($_SESSION['user_id']) . " AND media_id = " . intval($media_id));
        $is_like = $like_res ? true : false;
    }
}
// 评论处理
if ($db && isset($_SESSION['user_id']) && $mediaFile) {
    // 查 media_id
    $media_id = null;
    $stmt = $db->prepare('SELECT id FROM media WHERE media_url = :url');
    $stmt->bindValue(':url', 'uploads/' . $mediaFile, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res->fetchArray(SQLITE3_ASSOC);
    if ($row && isset($row['id'])) {
        $media_id = $row['id'];
    }
    $db->exec("CREATE TABLE IF NOT EXISTS comments (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, media_id INTEGER, content TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    if (isset($_POST['comment_content']) && trim($_POST['comment_content']) && $media_id) {
        $stmt = $db->prepare('INSERT INTO comments (user_id, media_id, content) VALUES (:uid, :media_id, :content)');
        $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':media_id', $media_id, SQLITE3_INTEGER);
        $stmt->bindValue(':content', trim($_POST['comment_content']), SQLITE3_TEXT);
        $stmt->execute();
        header('Location: ?file=' . urlencode($mediaFile));
        exit;
    }
    // 获取评论
    $comments = [];
    if ($media_id) {
        $stmt = $db->prepare('SELECT c.*, u.username FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.media_id = :media_id ORDER BY c.created_at DESC');
        $stmt->bindValue(':media_id', $media_id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $comments[] = $row;
        }
    }
}
// 用户信息展示
$user_nickname = $_SESSION['nickname'] ?? '';
$user_name = $_SESSION['username'] ?? '';
$user_display = $user_nickname ?: $user_name;
$user_avatar = strtoupper(mb_substr($user_name, 0, 1, 'UTF-8'));
$is_login = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>媒体播放器</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        body { background:linear-gradient(135deg,#f3e8ff 0%,#e0c3fc 100%); font-family:'Segoe UI',Arial,sans-serif; }
        .dy-pc-main {
            display:flex;flex-direction:row;justify-content:center;align-items:flex-start;
            width:100vw;max-width:100vw;margin-top:80px;height:calc(100vh - 80px);
            transition:all 0.4s cubic-bezier(.4,0,.2,1);
        }
        .dy-pc-center {
            flex:1 1 0%; min-width:0; height:100%; display:flex;flex-direction:column;align-items:center;justify-content:center;
            width:100vw; max-width:100vw;
        }
        .dy-pc-video-card {position:relative;margin:0;background:none;box-shadow:none;padding:0;width:100%;max-width:none;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;}
        .dy-pc-video-wrap {position:relative;width:100%;height:90vh;max-height:90vh;display:flex;align-items:center;justify-content:center;}
        .dy-pc-video-wrap video {
            width:100%;height:100%;object-fit:cover;
            border-radius:18px;
            box-shadow:0 8px 48px 0 rgba(162,89,230,0.18),0 1.5px 8px rgba(162,89,230,0.10);
            background:#000;display:block;
            transition:box-shadow 0.3s,transform 0.3s;
        }
        .dy-pc-video-wrap video:hover {
            box-shadow:0 16px 64px 0 rgba(162,89,230,0.28),0 2px 12px rgba(162,89,230,0.16);
            transform:scale(1.01);
        }
        .dy-pc-actions {
            position:absolute;right:42px;top:50%;transform:translateY(-50%);
            display:flex;flex-direction:column;gap:22px;z-index:2;
            filter:drop-shadow(0 4px 16px rgba(162,89,230,0.18));
            align-items:center;
        }
        .dy-pc-action-btn {
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            width:58px;height:58px;border-radius:50%;
            background:linear-gradient(135deg,#a259e6 60%,#e0c3fc 100%);
            color:#fff;font-size:1.45em;border:none;
            box-shadow:0 2px 12px rgba(162,89,230,0.18);
            margin-bottom:0;cursor:pointer;
            transition:background 0.18s,box-shadow 0.18s,transform 0.18s;
            text-decoration:none;position:relative;
            outline:none;
        }
        .dy-pc-action-btn + .dy-pc-action-btn {margin-top:18px;}
        .dy-pc-action-btn span {font-size:1em;margin-top:2px;}
        .dy-pc-action-btn:hover, .dy-pc-action-btn:focus {
            background:linear-gradient(135deg,#8f5fe8 60%,#a259e6 100%);
            transform:scale(1.12) translateY(-2px);
            box-shadow:0 8px 32px rgba(162,89,230,0.22);
            z-index:2;
        }
        .dy-pc-tooltip {
            visibility:hidden;opacity:0;min-width:70px;
            background:rgba(162,89,230,0.98);color:#fff;text-align:center;
            border-radius:10px;padding:7px 14px;position:absolute;z-index:10;
            left:50%;bottom:110%;transform:translateX(-50%) translateY(8px);
            font-size:1em;font-weight:500;box-shadow:0 2px 8px rgba(162,89,230,0.13);
            pointer-events:none;transition:opacity 0.18s,transform 0.18s;white-space:nowrap;
            backdrop-filter:blur(4px);
        }
        .dy-pc-action-btn:hover .dy-pc-tooltip, .dy-pc-action-btn:focus .dy-pc-tooltip {
            visibility:visible;opacity:1;transform:translateX(-50%) translateY(0);
        }
        .dy-pc-tooltip::after {
            content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);
            border-width:7px;border-style:solid;border-color:rgba(162,89,230,0.98) transparent transparent transparent;
        }
        .dy-pc-video-info {
            position:absolute;left:38px;bottom:38px;
            color:#fff;text-shadow:0 2px 8px rgba(0,0,0,0.18);
            background:rgba(243,232,255,0.38);
            border-radius:18px;padding:18px 28px;max-width:60vw;
            backdrop-filter:blur(12px);
            box-shadow:0 2px 16px rgba(162,89,230,0.10);
            font-size:1.18em;
            animation:fadein 0.7s;
        }
        .dy-pc-title {font-size:1.25em;font-weight:700;letter-spacing:1px;}
        .dy-pc-desc {color:#eee;font-size:1.08em;margin-top:6px;line-height:1.6;}
        .dy-pc-music {color:#e0c3fc;font-size:1.08em;margin-top:8px;display:flex;align-items:center;gap:8px;}
        @keyframes fadein {from{opacity:0;transform:translateY(30px);}to{opacity:1;transform:none;}}
        .dy-pc-comment-panel {
            position:fixed;right:0;top:0;z-index:10001;
            width:420px;max-width:90vw;height:100vh;min-height:100vh;max-height:100vh;
            background:rgba(255,255,255,0.98);backdrop-filter:blur(8px);
            border-radius:18px 0 0 18px;box-shadow:-8px 0 32px rgba(162,89,230,0.13);
            overflow:hidden;
            transition:transform 0.35s cubic-bezier(.4,0,.2,1),opacity 0.25s;
            transform:translateX(100%);opacity:0;pointer-events:none;
            display:flex;flex-direction:column;
        }
        .dy-pc-comment-panel.active {
            transform:translateX(0);opacity:1;pointer-events:auto;
        }
        .dy-pc-comment-title {
            font-weight:700;color:#a259e6;font-size:1.18em;
            padding:22px 22px 0 22px;border-bottom:1.5px solid #e0c3fc;
            background:rgba(243,232,255,0.85);backdrop-filter:blur(4px);
            border-radius:18px 0 0 0;
        }
        .dy-pc-comment-list {flex:1;overflow-y:auto;padding:0 18px;max-height:50vh;}
        .dy-pc-comment-item {background:rgba(243,232,255,0.7);border-radius:12px;padding:12px 16px;margin-bottom:12px;box-shadow:0 1px 6px rgba(162,89,230,0.08);}
        .dy-pc-comment-user {color:#a259e6;font-weight:600;}
        .dy-pc-comment-time {color:#888;font-size:0.98em;margin-left:10px;}
        .dy-pc-comment-content {margin-top:6px;white-space:pre-line;word-break:break-all;}
        .dy-pc-comment-empty {color:#bbb;text-align:center;margin:18px 0;}
        .dy-pc-comment-form {
            display:flex;gap:10px;align-items:flex-end;padding:16px 22px 22px 22px;
            border-top:1.5px solid #e0c3fc;background:rgba(243,232,255,0.85);backdrop-filter:blur(4px);
            position:sticky;bottom:0;z-index:2;
            border-radius:0 0 0 18px;
        }
        .dy-pc-comment-form textarea {
            flex:1;resize:vertical;border-radius:10px;border:1.5px solid #d1b3ff;
            padding:12px 14px;font-size:1.08em;background:rgba(255,255,255,0.85);
            transition:border 0.2s,box-shadow 0.2s;
            box-shadow:0 1px 4px rgba(162,89,230,0.08);
        }
        .dy-pc-comment-form textarea:focus {
            border:1.5px solid #a259e6;outline:none;box-shadow:0 2px 8px rgba(162,89,230,0.13);
        }
        .dy-pc-comment-form button {
            background:linear-gradient(90deg,#a259e6 60%,#e0c3fc 100%);color:#fff;
            border:none;border-radius:10px;padding:12px 26px;font-size:1.08em;font-weight:600;cursor:pointer;
            transition:background 0.2s,transform 0.15s;box-shadow:0 2px 8px rgba(162,89,230,0.10);
        }
        .dy-pc-comment-form button:hover {
            background:linear-gradient(90deg,#8f5fe8 60%,#a259e6 100%);transform:scale(1.06);
        }
        .dy-pc-comment-close {
            background:none;border:none;font-size:1.5em;color:#a259e6;cursor:pointer;transition:color 0.18s;float:right;
        }
        .dy-pc-comment-close:hover {color:#8f5fe8;}
        .dy-pc-sidenav {
            width:90px;min-width:90px;display:flex;flex-direction:column;align-items:center;gap:8px;padding-top:20px;background:rgba(255,255,255,0.92);border-radius:18px;margin-right:24px;box-shadow:0 2px 12px rgba(162,89,230,0.06);backdrop-filter:blur(4px);}
        .dy-pc-sidenav-item {display:flex;flex-direction:column;align-items:center;gap:6px;color:#a259e6;font-size:1.3em;cursor:pointer;padding:14px 0;width:100%;border-radius:12px;transition:color 0.18s,background 0.18s;}
        .dy-pc-sidenav-item.active,.dy-pc-sidenav-item:hover {background:linear-gradient(135deg,#a259e6 60%,#e0c3fc 100%);color:#fff;}
        .dy-pc-sidenav-item span {font-size:0.98em;}
        .dy-pc-header {height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 48px;background:rgba(255,255,255,0.95);box-shadow:0 2px 12px rgba(162,89,230,0.08);border-bottom:1.5px solid #e0c3fc;position:fixed;top:0;left:0;width:100vw;z-index:100;backdrop-filter:blur(8px);}
        .dy-pc-logo {font-size:1.7em;font-weight:700;color:#a259e6;letter-spacing:2px;display:flex;align-items:center;gap:10px;}
        .dy-pc-search {position:relative;flex:1;display:flex;justify-content:center;}
        .dy-pc-search input {width:340px;padding:10px 40px 10px 18px;border-radius:22px;border:none;font-size:1.08em;background:#f3e8ff;color:#a259e6;box-shadow:0 1px 4px rgba(162,89,230,0.08);outline:none;transition:box-shadow 0.18s;}
        .dy-pc-search input:focus {box-shadow:0 2px 12px rgba(162,89,230,0.18);}
        .dy-pc-search .search-icon {position:absolute;right:18px;top:50%;transform:translateY(-50%);color:#a259e6;font-size:1.15em;}
        .dy-pc-header-actions {display:flex;align-items:center;gap:18px;}
        .dy-pc-header-btn {background:linear-gradient(90deg,#a259e6 60%,#e0c3fc 100%);color:#fff;border-radius:18px;padding:7px 22px;font-weight:600;font-size:1.08em;border:none;cursor:pointer;box-shadow:0 1px 4px rgba(162,89,230,0.08);transition:background 0.18s,color 0.18s;}
        .dy-pc-header-btn:hover {background:#e0c3fc;color:#fff;}
        .dy-pc-avatar {width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#a259e6 60%,#e0c3fc 100%);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2em;font-weight:bold;box-shadow:0 2px 8px rgba(162,89,230,0.10);user-select:none;border:2px solid #fff;}
        .dy-pc-login {color:#a259e6;font-weight:600;text-decoration:none;}
        @media (max-width: 1200px) {
            .dy-pc-main {flex-direction:column;align-items:center;height:auto;}
            .dy-pc-center {height:auto;width:100vw;max-width:100vw;}
            .dy-pc-video-wrap {height:60vw;max-height:70vh;}
            .dy-pc-video-info {left:12px;bottom:12px;max-width:90vw;}
            .dy-pc-actions {right:12px;}
            .dy-pc-comment-panel {width:100vw;max-width:100vw;position:fixed;left:0;top:0;border-radius:0;box-shadow:none;}
        }
        @media (max-width: 700px) {
            .dy-pc-main {margin-top:60px;height:auto;}
            .dy-pc-center {padding:0;}
            .dy-pc-video-wrap {height:60vw;max-height:60vw;}
            .dy-pc-video-info {padding:10px 8px;left:2vw;bottom:2vw;max-width:96vw;}
            .dy-pc-actions {right:2vw;}
            .dy-comment-fab {right:12px;bottom:18px;width:48px;height:48px;font-size:1.3em;}
        }
        .dy-comment-fab {
            position:fixed;right:32px;bottom:48px;z-index:10002;
            width:62px;height:62px;border-radius:50%;
            background:linear-gradient(135deg,#a259e6 60%,#e0c3fc 100%);
            color:#fff;font-size:2em;border:none;box-shadow:0 4px 24px rgba(162,89,230,0.18);
            display:flex;align-items:center;justify-content:center;cursor:pointer;
            transition:background 0.18s,transform 0.18s,box-shadow 0.18s;
            outline:none;
            opacity:1;
            animation:fabIn 0.5s;
        }
        .dy-comment-fab:hover {background:linear-gradient(135deg,#8f5fe8 60%,#a259e6 100%);transform:scale(1.08);box-shadow:0 8px 32px rgba(162,89,230,0.22);}
        @keyframes fabIn {from{opacity:0;transform:scale(0.7);}to{opacity:1;transform:none;}}
        .custom-controls {
            position:absolute;left:50%;bottom:32px;transform:translateX(-50%);
            display:flex;align-items:center;gap:18px;z-index:10;
            background:rgba(243,232,255,0.85);backdrop-filter:blur(8px);
            border-radius:12px;padding:10px 18px;box-shadow:0 2px 8px rgba(162,89,230,0.13);
        }
        .play-pause-btn {
            width:44px;height:44px;border-radius:50%;border:none;
            background:linear-gradient(135deg,#a259e6 60%,#e0c3fc 100%);color:#fff;font-size:1.3em;display:flex;align-items:center;justify-content:center;cursor:pointer;
            transition:background 0.18s,transform 0.12s;outline:none;
        }
        .play-pause-btn:hover {background:linear-gradient(135deg,#8f5fe8 60%,#a259e6 100%);transform:scale(1.08);}
        .progress-bar-wrap {display:flex;align-items:center;gap:10px;}
        #progress-bar {
            width:320px;height:6px;border-radius:4px;background:#e0c3fc;outline:none;
            accent-color:#a259e6;
        }
        #current-time,#duration {font-family:monospace;font-size:1em;color:#a259e6;font-weight:600;}
        @media (max-width:700px){.custom-controls{bottom:10px;padding:6px 6px;}.progress-bar-wrap{gap:4px;}#progress-bar{width:120px;}}
    </style>
</head>
<body style="background:#f8f3ff;min-height:100vh;">
    <!-- 顶部导航栏 -->
    <div class="dy-pc-header">
        <div class="dy-pc-logo"><i class="fa-solid fa-music"></i> 媒体播放器</div>
        <div class="dy-pc-search"><input type="text" placeholder="搜索用户、视频、音乐" /><i class="fa-solid fa-search search-icon"></i></div>
        <div class="dy-pc-header-actions">
            <button class="dy-pc-header-btn" id="upload-btn"><i class="fa-solid fa-cloud-arrow-up"></i> 上传</button>
            <button class="dy-pc-header-btn"><i class="fa-regular fa-message"></i> 消息</button>
            <button class="dy-pc-header-btn"><i class="fa-solid fa-gem"></i> 创作者中心</button>
            <?php if ($is_login): ?>
                <span class="dy-pc-avatar"><?= htmlspecialchars($user_avatar) ?></span>
                <a href="logout.php" class="dy-pc-login">退出</a>
            <?php else: ?>
                <a href="login.php" class="dy-pc-login">登录</a> | <a href="register.php" class="dy-pc-login">注册</a>
            <?php endif; ?>
        </div>
    </div>
    <!-- 上传弹窗，仅登录用户可见 -->
    <?php if ($is_login): ?>
    <div id="upload-modal" style="display:none;position:fixed;z-index:10000;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.35);align-items:center;justify-content:center;">
        <form id="upload-form" method="post" enctype="multipart/form-data" style="background:linear-gradient(135deg,#f3e8ff 0%,#e0c3fc 100%);padding:32px 28px;border-radius:18px;box-shadow:0 8px 48px 0 rgba(162,89,230,0.18),0 1.5px 8px rgba(162,89,230,0.10);min-width:340px;max-width:96vw;display:flex;flex-direction:column;gap:18px;position:relative;">
            <span style="position:absolute;right:18px;top:12px;font-size:1.5em;cursor:pointer;color:#a259e6;transition:color 0.18s;" onmouseover="this.style.color='#1976d2'" onmouseout="this.style.color='#a259e6'" onclick="closeUploadModal()">&times;</span>
            <h2 style="text-align:center;color:#a259e6;margin-bottom:8px;letter-spacing:1px;">上传视频</h2>
            <input type="file" name="media" accept="video/mp4,video/webm,video/ogg" required style="padding:10px 0 10px 0;border-radius:8px;border:1.5px solid #b0bec5;background:#fff;">
            <input type="text" name="description" placeholder="视频描述（可选）" maxlength="100" style="padding:10px 14px;border-radius:8px;border:1.5px solid #b0bec5;background:#f8fafc;">
            <input type="text" name="tags" placeholder="标签（逗号分隔，可选）" maxlength="50" style="padding:10px 14px;border-radius:8px;border:1.5px solid #b0bec5;background:#f8fafc;">
            <button type="submit" style="background:linear-gradient(90deg,#a259e6 60%,#42a5f5 100%);color:#fff;border:none;border-radius:8px;padding:12px 0;font-size:1.08em;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(162,89,230,0.10);transition:background 0.18s;">上传</button>
        </form>
    </div>
    <script>
    document.getElementById('upload-btn').onclick = function(){
        document.getElementById('upload-modal').style.display = 'flex';
    };
    function closeUploadModal(){
        document.getElementById('upload-modal').style.display = 'none';
    }
    // 点击弹窗外关闭
    window.addEventListener('mousedown', function(e) {
        var modal = document.getElementById('upload-modal');
        var form = document.getElementById('upload-form');
        if(modal && modal.style.display!=='none' && !form.contains(e.target) && e.target.id!=='upload-btn'){
            closeUploadModal();
        }
    });
    </script>
    <?php endif; ?>
    <div class="dy-pc-main">
        <!-- 左侧导航 -->
        <div class="dy-pc-sidenav">
            <div class="dy-pc-sidenav-item active"><i class="fa-solid fa-compass"></i><span>推荐</span></div>
            <div class="dy-pc-sidenav-item"><i class="fa-solid fa-user-friends"></i><span>关注</span></div>
            <div class="dy-pc-sidenav-item"><i class="fa-solid fa-tv"></i><span>直播</span></div>
            <div class="dy-pc-sidenav-item"><i class="fa-solid fa-fire"></i><span>热点</span></div>
            <div class="dy-pc-sidenav-item"><i class="fa-solid fa-location-dot"></i><span>同城</span></div>
            <?php if ($is_login): ?>
                <span class="dy-pc-avatar"><?= htmlspecialchars($user_avatar) ?></span>
                <a href="logout.php" class="dy-pc-login">退出</a>
            <?php else: ?>
                <a href="login.php" class="dy-pc-login">登录</a> | <a href="register.php" class="dy-pc-login">注册</a>
            <?php endif; ?>
        </div>
        <!-- 中间视频区 -->
        <div class="dy-pc-center">
            <?php if ($mediaFile): ?>
                <div class="dy-pc-video-card">
                    <div class="dy-pc-video-wrap">
                        <?php if (preg_match('/\.(mp4|webm|ogg)$/i', $mediaFile)): ?>
                            <video id="media" src="<?= htmlspecialchars($mediaUrl) ?>" playsinline></video>
                            <div class="custom-controls" id="custom-controls">
                                <button id="play-pause-btn" class="play-pause-btn"><i class="fa-solid fa-play"></i></button>
                                <div class="progress-bar-wrap">
                                    <input type="range" id="progress-bar" value="0" min="0" max="100" step="0.1">
                                    <span id="current-time">00:00</span> / <span id="duration">00:00</span>
                                </div>
                            </div>
                            <script>
                            const video = document.getElementById('media');
                            const playBtn = document.getElementById('play-pause-btn');
                            const progressBar = document.getElementById('progress-bar');
                            const currentTimeSpan = document.getElementById('current-time');
                            const durationSpan = document.getElementById('duration');
                            function formatTime(t) {
                                t = Math.floor(t);
                                const m = String(Math.floor(t/60)).padStart(2,'0');
                                const s = String(t%60).padStart(2,'0');
                                return m+':'+s;
                            }
                            video.addEventListener('loadedmetadata', function() {
                                progressBar.max = video.duration;
                                durationSpan.textContent = formatTime(video.duration);
                            });
                            video.addEventListener('timeupdate', function() {
                                progressBar.value = video.currentTime;
                                currentTimeSpan.textContent = formatTime(video.currentTime);
                            });
                            progressBar.addEventListener('input', function() {
                                video.currentTime = progressBar.value;
                            });
                            playBtn.onclick = function() {
                                if (video.paused) {
                                    video.play();
                                    playBtn.innerHTML = '<i class="fa-solid fa-pause"></i>';
                                    // 记录播放历史
                                    fetch('play_record.php', {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                        body: 'file=<?= urlencode($mediaFile) ?>'
                                    });
                                } else {
                                    video.pause();
                                    playBtn.innerHTML = '<i class="fa-solid fa-play"></i>';
                                }
                            };
                            video.addEventListener('play', function(){ playBtn.innerHTML = '<i class="fa-solid fa-pause"></i>'; });
                            video.addEventListener('pause', function(){ playBtn.innerHTML = '<i class="fa-solid fa-play"></i>'; });
                            </script>
                        <?php elseif (preg_match('/\.(mp3|wav|aac|m4a)$/i', $mediaFile)): ?>
                            <audio id="media" src="<?= htmlspecialchars($mediaUrl) ?>" style="width:100%;display:block;margin:0 auto;"></audio>
                        <?php else: ?>
                            <p>不支持的文件类型。</p>
                        <?php endif; ?>
                        <!-- 视频右侧浮动操作按钮 -->
                        <div class="dy-pc-actions">
                            <button class="dy-pc-action-btn" title="点赞"><i class="fa-solid fa-heart"></i><span>99</span><div class="dy-pc-tooltip">点赞</div></button>
                            <button class="dy-pc-action-btn" id="dy-comment-btn" onclick="toggleDyComment()" title="评论"><i class="fa-solid fa-comment"></i><span>88</span><div class="dy-pc-tooltip">评论</div></button>
                            <button class="dy-pc-action-btn" title="收藏"><i class="fa-solid fa-star"></i><span>77</span><div class="dy-pc-tooltip">收藏</div></button>
                            <button class="dy-pc-action-btn" onclick="navigator.clipboard.writeText(location.href)" title="分享"><i class="fa-solid fa-share"></i><span>99</span><div class="dy-pc-tooltip">分享</div></button>
                        </div>
                        <!-- 视频下方信息 -->
                        <div class="dy-pc-video-info">
                            <div class="dy-pc-title"> <?= htmlspecialchars($mediaFile) ?> </div>
                            <div class="dy-pc-desc">这里是视频描述和标签 #标签1 #标签2</div>
                            <div class="dy-pc-music"><i class="fa-solid fa-music"></i> 配乐：热门音乐</div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="dy-pc-empty">请上传或选择一个视频进行播放</div>
            <?php endif; ?>
            <!-- 上下切换视频 -->
            <div class="dy-pc-switch-bar">
                <?php
                $files = array_values(array_filter(scandir(__DIR__ . '/uploads'), function($f) {
                    return preg_match('/\.(mp4|webm|ogg)$/i', $f);
                }));
                $currentIndex = array_search($mediaFile, $files);
                $prevFile = ($currentIndex !== false && $currentIndex > 0) ? $files[$currentIndex-1] : null;
                $nextFile = ($currentIndex !== false && $currentIndex < count($files)-1) ? $files[$currentIndex+1] : null;
                ?>
                <a href="?file=<?= urlencode($prevFile) ?>" class="dy-pc-switch-btn" <?= $prevFile?'':'style="opacity:.3;pointer-events:none;"' ?>><i class="fa-solid fa-arrow-up"></i> 上一条</a>
                <a href="?file=<?= urlencode($nextFile) ?>" class="dy-pc-switch-btn" <?= $nextFile?'':'style="opacity:.3;pointer-events:none;"' ?>><i class="fa-solid fa-arrow-down"></i> 下一条</a>
            </div>
        </div>
        <!-- 右侧评论区 -->
        <div class="dy-pc-comment-panel" id="dy-pc-comment-panel">
            <div class="dy-pc-comment-title" style="display:flex;justify-content:space-between;align-items:center;">
                <span>评论区</span>
                <button class="dy-pc-comment-close" onclick="hideDyComment()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="dy-pc-comment-list">
                <?php if (!empty($comments)): ?>
                    <?php foreach ($comments as $c): ?>
                        <div class="dy-pc-comment-item">
                            <span class="dy-pc-comment-user"><i class="fa-solid fa-user"></i> <?= htmlspecialchars($c['nickname'] ?: $c['username']) ?></span>
                            <span class="dy-pc-comment-time"> <?= htmlspecialchars($c['created_at']) ?></span>
                            <div class="dy-pc-comment-content"> <?= nl2br(htmlspecialchars($c['content'])) ?> </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="dy-pc-comment-empty">暂无评论</div>
                <?php endif; ?>
            </div>
            <?php if ($db && isset($_SESSION['user_id'])): ?>
            <form method="post" class="dy-pc-comment-form">
                <textarea name="comment_content" rows="2" placeholder="发表你的评论..."></textarea>
                <button type="submit">发表评论</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function toggleDyComment() {
            var panel = document.getElementById('dy-pc-comment-panel');
            if (panel.classList.contains('active')) {
                hideDyComment();
            } else {
                showDyComment();
            }
        }
        function showDyComment() {
            document.getElementById('dy-pc-comment-panel').classList.add('active');
        }
        function hideDyComment() {
            document.getElementById('dy-pc-comment-panel').classList.remove('active');
        }
        // 点击弹窗外关闭
        window.addEventListener('mousedown', function(e) {
            var panel = document.getElementById('dy-pc-comment-panel');
            if (panel.classList.contains('active')) {
                if (!panel.contains(e.target) && !e.target.closest('.dy-pc-action-btn')) {
                    hideDyComment();
                }
            }
        });
        // 初始隐藏评论区
        window.onload = function(){
            document.getElementById('dy-pc-comment-panel').classList.remove('active');
        };
    </script>
</body>
</html> 
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('未登录');
}
if (!isset($_POST['file'])) {
    http_response_code(400);
    exit('缺少参数');
}
$db = new SQLite3(__DIR__ . '/ntbfq.sqlite');
$file = $_POST['file'];
$stmt = $db->prepare('SELECT id FROM media WHERE media_url = :url');
$stmt->bindValue(':url', 'uploads/' . $file, SQLITE3_TEXT);
$res = $stmt->execute();
$row = $res->fetchArray(SQLITE3_ASSOC);
if ($row && isset($row['id'])) {
    $media_id = $row['id'];
    $stmt = $db->prepare('INSERT INTO play_records (user_id, media_id) VALUES (:uid, :media_id)');
    $stmt->bindValue(':uid', $_SESSION['user_id'], SQLITE3_INTEGER);
    $stmt->bindValue(':media_id', $media_id, SQLITE3_INTEGER);
    $stmt->execute();
    echo 'ok';
} else {
    http_response_code(404);
    exit('未找到视频');
} 
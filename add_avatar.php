<?php
// 修复 users 表无 avatar 字段问题
$db = new SQLite3(__DIR__ . '/ntbfq.sqlite');
$cols = $db->query("PRAGMA table_info(users)");
$has_avatar = false;
while ($col = $cols->fetchArray(SQLITE3_ASSOC)) {
    if ($col['name'] === 'avatar') {
        $has_avatar = true;
        break;
    }
}
if (!$has_avatar) {
    $db->exec('ALTER TABLE users ADD COLUMN avatar TEXT');
    echo "已添加 avatar 字段\n";
} else {
    echo "avatar 字段已存在\n";
} 
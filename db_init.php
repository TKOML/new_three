<?php
// db_init.php
// 用于初始化 SQLite 数据库和所有表结构

$dbFile = __DIR__ . '/ntbfq.sqlite';

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 创建 users 表，添加 phone 和 create_date 字段
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        avatar_url TEXT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        bio TEXT,
        email TEXT UNIQUE,
        gender TEXT,
        phone TEXT,
        create_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    // 创建 media 表，添加 description、media_type、tags 字段
    $db->exec('CREATE TABLE IF NOT EXISTS media (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        media_url TEXT NOT NULL,
        creator_id INTEGER NOT NULL,
        description TEXT,
        media_type TEXT,
        tags TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (creator_id) REFERENCES users(id)
    )');

    // 创建 comments 表
    $db->exec('CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content TEXT NOT NULL,
        user_id INTEGER NOT NULL,
        media_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (media_id) REFERENCES media(id)
    )');

    // 创建 likes 表
    $db->exec('CREATE TABLE IF NOT EXISTS likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        media_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (media_id) REFERENCES media(id),
        UNIQUE (user_id, media_id)
    )');

    // 创建 favorites 表
    $db->exec('CREATE TABLE IF NOT EXISTS favorites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        media_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (media_id) REFERENCES media(id),
        UNIQUE (user_id, media_id)
    )');

    // 创建 follows 表
    $db->exec('CREATE TABLE IF NOT EXISTS follows (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        follower_id INTEGER NOT NULL,
        following_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (follower_id) REFERENCES users(id),
        FOREIGN KEY (following_id) REFERENCES users(id),
        UNIQUE (follower_id, following_id)
    )');

    // 创建 play_records 表
    $db->exec('CREATE TABLE IF NOT EXISTS play_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        media_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (media_id) REFERENCES media(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    echo "数据库和表结构初始化完成！\n";
} catch (PDOException $e) {
    echo "数据库初始化失败: " . $e->getMessage() . "\n";
    exit(1);
} 
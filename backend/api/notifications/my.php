<?php
require_once __DIR__ . '/../../core/bootstrap.php';
$user = require_user();
$db = lm_db();
$stmt = $db->prepare('SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$stmt->execute([$user['id']]);
respond(['notifications' => $stmt->fetchAll()]);

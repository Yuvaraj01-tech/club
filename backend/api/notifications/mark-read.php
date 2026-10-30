<?php
require_once __DIR__ . '/../../core/bootstrap.php';
$user = require_user();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
$in = json_input();

$db = lm_db();
if (!empty($in['id'])) {
  $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$in['id'], $user['id']]);
} else {
  $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
}
respond(['message' => 'Updated.']);

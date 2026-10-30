<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../services/EmailService.php';
$admin = require_admin();

// Admin issues a fresh temporary password for an approved user (per spec step 4).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
$in = json_input();
require_fields($in, ['user_id']);

$db = lm_db();
$stmt = $db->prepare('SELECT id, username, email FROM users WHERE id = ?');
$stmt->execute([$in['user_id']]);
$user = $stmt->fetch();
if (!$user) fail('User not found.', 404);

$tempPassword = strtoupper(bin2hex(random_bytes(4))); // e.g. "A1B2C3D4"
$db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
   ->execute([password_hash($tempPassword, PASSWORD_BCRYPT), $user['id']]);

notify_user((int) $user['id'], 'Temporary Login Issued', 'Your admin-issued temporary password is ready. Please change it after logging in.', 'system');
if ($user['email']) {
  EmailService::send($user['email'], "Hello Hobnobbers — Your temporary password", "Username: {$user['username']}<br>Temporary password: <b>$tempPassword</b><br>Please change it after logging in.");
}

log_activity('admin', $admin['id'], 'issued_temp_password', ['user_id' => $user['id']]);
respond(['message' => 'Temporary password issued and sent to the user.', 'temp_password' => $tempPassword]);

<?php
require_once __DIR__ . '/../../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
$in = json_input();
require_fields($in, ['username', 'password']);

$db = lm_db();
$stmt = $db->prepare('SELECT id, username, password_hash, full_name, role FROM admins WHERE username = ?');
$stmt->execute([$in['username']]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($in['password'], $admin['password_hash'])) {
  fail('Invalid admin credentials.', 401);
}

$db->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);

start_admin_session();
$_SESSION['admin'] = [
  'id' => (int) $admin['id'],
  'username' => $admin['username'],
  'full_name' => $admin['full_name'],
  'role' => $admin['role'],
];

log_activity('admin', (int) $admin['id'], 'login');
respond(['admin' => $_SESSION['admin']]);

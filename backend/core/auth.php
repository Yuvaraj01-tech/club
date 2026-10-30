<?php
/**
 * Session-based auth. Users and admins each get their own session cookie so a
 * browser can (in theory) be logged into both at once without collisions.
 */

function lm_cookie_params(): array {
  return [
    'lifetime' => 0,
    'path' => '/',
    'samesite' => 'Lax',
    'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
    'httponly' => true,
  ];
}

function start_user_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $cfg = lm_config();
  session_name($cfg['app']['session_cookie_name_user']);
  session_set_cookie_params(lm_cookie_params());
  session_start();
}

function start_admin_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $cfg = lm_config();
  session_name($cfg['app']['session_cookie_name_admin']);
  session_set_cookie_params(lm_cookie_params());
  session_start();
}

function current_user(): ?array {
  start_user_session();
  return $_SESSION['user'] ?? null;
}

function current_admin(): ?array {
  start_admin_session();
  return $_SESSION['admin'] ?? null;
}

function require_user(): array {
  $u = current_user();
  if (!$u) fail('Not authenticated. Please sign in.', 401);
  return $u;
}

function require_admin(): array {
  $a = current_admin();
  if (!$a) fail('Admin authentication required.', 401);
  return $a;
}

function require_super_admin(): array {
  $a = require_admin();
  if (($a['role'] ?? '') !== 'super_admin') {
    fail('Super admin privileges required.', 403);
  }
  return $a;
}

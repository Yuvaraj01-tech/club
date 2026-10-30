<?php
/**
 * Every api/**.php file starts with:  require_once __DIR__.'/../../core/bootstrap.php';
 * (adjust ../../ depth to reach backend/core from the endpoint's folder)
 */

require_once __DIR__ . '/../config/db.php';

$cfg = lm_config();
date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Kolkata');

// ---------------- Error handling: never leak PHP errors as HTML into a JSON API ----------------
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_exception_handler(function ($e) {
  error_log('[HELLOHOBNOBBERS] Uncaught: ' . $e->getMessage());
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Server error. Please try again.']);
  exit;
});

// ---------------- CORS ----------------
$allowedOrigins = $cfg['app']['cors_allowed_origins'] ?? [];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowedOrigins, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Headers: Content-Type, Authorization');
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ---------------- JSON helpers ----------------
function json_input(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return $_POST ?: [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function respond($data, int $code = 200): void {
  http_response_code($code);
  echo json_encode(['ok' => true] + (is_array($data) ? $data : ['data' => $data]));
  exit;
}

function fail(string $message, int $code = 400, array $extra = []): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $message] + $extra);
  exit;
}

function require_fields(array $input, array $fields): void {
  $missing = [];
  foreach ($fields as $f) {
    if (!isset($input[$f]) || (is_string($input[$f]) && trim($input[$f]) === '')) {
      $missing[] = $f;
    }
  }
  if ($missing) {
    fail('Missing required field(s): ' . implode(', ', $missing), 422);
  }
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

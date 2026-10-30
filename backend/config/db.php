<?php
/**
 * PDO connection singleton. Include via require_once __DIR__.'/../config/db.php';
 */

function lm_config(): array {
  static $config = null;
  if ($config === null) {
    $path = __DIR__ . '/config.php';
    if (!file_exists($path)) {
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['ok' => false, 'error' => 'Missing config.php. Copy config.sample.php to config.php and fill in your credentials.']);
      exit;
    }
    $config = require $path;
  }
  return $config;
}

function lm_db(): PDO {
  static $pdo = null;
  if ($pdo === null) {
    $cfg = lm_config()['db'];
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";
    try {
      $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);
    } catch (PDOException $e) {
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode(['ok' => false, 'error' => 'Database connection failed.']);
      exit;
    }
  }
  return $pdo;
}

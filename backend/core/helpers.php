<?php

/** Generate a human-readable unique booking code, e.g. LM-2026-A1B2C3 */
function generate_booking_code(): string {
  return 'LM-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/** Generate a unique event-pass code */
function generate_pass_code(): string {
  return 'PASS-' . strtoupper(bin2hex(random_bytes(5)));
}

function log_activity(string $actorType, ?int $actorId, string $action, array $meta = []): void {
  try {
    $stmt = lm_db()->prepare(
      'INSERT INTO activity_logs (actor_type, actor_id, action, meta) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$actorType, $actorId, $action, json_encode($meta)]);
  } catch (Throwable $e) {
    // Logging must never break the request.
    error_log('[HELLOHOBNOBBERS] activity log failed: ' . $e->getMessage());
  }
}

function notify_user(
    int $userId,
    string $title,
    string $message,
    string $type = 'system'
): void {

    $stmt = lm_db()->prepare(
        'INSERT INTO notifications
        (user_id, title, message)
        VALUES (?, ?, ?)'
    );

    $stmt->execute([
        $userId,
        $title,
        $message
    ]);
}

/**
 * Save an uploaded file (from $_FILES[$field]) into backend/uploads/$subdir.
 * Returns the relative path (to store in DB) or null if no file was sent.
 */
function save_uploaded_file(string $field, string $subdir, array $allowedExt = ['jpg','jpeg','png','pdf']): ?string {
  if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
    return null;
  }
  $file = $_FILES[$field];
  if ($file['error'] !== UPLOAD_ERR_OK) {
    fail('File upload failed for ' . $field, 422);
  }
  if ($file['size'] > 8 * 1024 * 1024) {
    fail('File too large (max 8MB): ' . $field, 422);
  }
  $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, $allowedExt, true)) {
    fail('Unsupported file type for ' . $field . '. Allowed: ' . implode(', ', $allowedExt), 422);
  }
  $dir = __DIR__ . '/../uploads/' . trim($subdir, '/');
  if (!is_dir($dir)) mkdir($dir, 0755, true);
  $filename = bin2hex(random_bytes(12)) . '.' . $ext;
  $dest = $dir . '/' . $filename;
  if (!move_uploaded_file($file['tmp_name'], $dest)) {
    fail('Could not save uploaded file: ' . $field, 500);
  }
  return 'uploads/' . trim($subdir, '/') . '/' . $filename;
}

/** Basic Indian mobile number validation: exactly 10 digits */
function is_valid_mobile(string $mobile): bool {
  return (bool) preg_match('/^[6-9]\d{9}$/', $mobile);
}

function calculate_age(string $dob): int {
  $d = new DateTime($dob);
  $now = new DateTime('now');
  return $now->diff($d)->y;
}

<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_admin();
$db = lm_db();
$stmt = $db->query(
  'SELECT v.id, v.scheduled_at, v.status, b.booking_code, e.title AS event_title, up.full_name, up.mobile_number
   FROM ai_voice_calls v
   JOIN bookings b ON b.id = v.booking_id
   JOIN events e ON e.id = b.event_id
   JOIN users u ON u.id = b.user_id
   LEFT JOIN user_profiles up ON up.user_id = u.id
   ORDER BY v.scheduled_at ASC'
);
respond(['calls' => $stmt->fetchAll()]);

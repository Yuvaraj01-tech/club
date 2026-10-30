<?php
require_once __DIR__ . '/../../core/bootstrap.php';
$db = lm_db();
$stmt = $db->query(
  'SELECT a.id, a.title, a.message, a.created_at, e.title AS event_title
   FROM announcements a LEFT JOIN events e ON e.id = a.event_id
   ORDER BY a.created_at DESC LIMIT 50'
);
respond(['announcements' => $stmt->fetchAll()]);

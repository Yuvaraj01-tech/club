<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_admin();
$db = lm_db();
$stmt = $db->query('SELECT a.*, e.title AS event_title FROM announcements a LEFT JOIN events e ON e.id = a.event_id ORDER BY a.created_at DESC');
respond(['announcements' => $stmt->fetchAll()]);

<?php
require_once __DIR__ . '/../../core/bootstrap.php';
$eventId = $_GET['event_id'] ?? null;
if (!$eventId) fail('Missing event_id', 422);

$db = lm_db();
$stmt = $db->prepare('SELECT id, slot_label, start_time, end_time, capacity, booked_count, status FROM event_slots WHERE event_id = ?');
$stmt->execute([$eventId]);
respond(['slots' => $stmt->fetchAll()]);

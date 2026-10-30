<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
$admin = require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);

$in = json_input();
require_fields($in, ['event_id', 'slot_label', 'start_time', 'end_time', 'capacity']);

$db = lm_db();
$stmt = $db->prepare('INSERT INTO event_slots (event_id, slot_label, start_time, end_time, capacity) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$in['event_id'], $in['slot_label'], $in['start_time'], $in['end_time'], $in['capacity']]);

log_activity('admin', $admin['id'], 'slot_created', ['event_id' => $in['event_id']]);
respond(['message' => 'Slot created.', 'slot_id' => (int) $db->lastInsertId()], 201);

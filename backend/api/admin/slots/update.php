<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
$admin = require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);

$in = json_input();
require_fields($in, ['id']);

$fields = ['slot_label','start_time','end_time','capacity','status'];
$sets = []; $params = [];
foreach ($fields as $f) if (isset($in[$f])) { $sets[] = "$f = ?"; $params[] = $in[$f]; }
if (!$sets) fail('Nothing to update.', 422);
$params[] = $in['id'];

$db = lm_db();
$db->prepare('UPDATE event_slots SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

log_activity('admin', $admin['id'], 'slot_updated', ['slot_id' => $in['id']]);
respond(['message' => 'Slot updated.']);

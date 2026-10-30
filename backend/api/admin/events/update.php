<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
$admin = require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);

$in = $_POST ?: json_input();
require_fields($in, ['id']);

$db = lm_db();
$stmt = $db->prepare('SELECT id FROM events WHERE id = ?');
$stmt->execute([$in['id']]);
if (!$stmt->fetch()) fail('Event not found.', 404);

$fields = ['title','category','description','venue','organizer_name','event_date','start_time','end_time',
           'ticket_price','total_capacity','eligibility_notes','important_info','booking_closing_at','status'];
$sets = []; $params = [];
foreach ($fields as $f) {
  if (isset($in[$f])) { $sets[] = "$f = ?"; $params[] = $in[$f]; }
}
$coverPath = save_uploaded_file('cover_image', 'events', ['jpg', 'jpeg', 'png']);
if ($coverPath) { $sets[] = 'cover_image_path = ?'; $params[] = $coverPath; }

if (!$sets) fail('Nothing to update.', 422);
$params[] = $in['id'];
$db->prepare('UPDATE events SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

log_activity('admin', $admin['id'], 'event_updated', ['event_id' => $in['id']]);
respond(['message' => 'Event updated.']);

<?php
/**
 * Run once a day via cPanel > Cron Jobs, e.g.:
 *   php /home/USER/backend/api/cron/send-voice-reminders.php
 * (No HTTP session needed — this is a CLI/cron-only script.)
 */
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../services/VoiceService.php';

$db = lm_db();
$stmt = $db->query(
  "SELECT v.id, v.booking_id, b.booking_code, e.title AS event_title, e.event_date, e.venue,
          up.full_name, up.mobile_number
   FROM ai_voice_calls v
   JOIN bookings b ON b.id = v.booking_id
   JOIN events e ON e.id = b.event_id
   JOIN users u ON u.id = b.user_id
   LEFT JOIN user_profiles up ON up.user_id = u.id
   WHERE v.status = 'scheduled' AND v.scheduled_at <= NOW() AND b.status = 'confirmed'"
);

$due = $stmt->fetchAll();
$sent = 0;
foreach ($due as $call) {
  $script = "Hi {$call['full_name']}, this is a reminder from Hello Hobnobbers about {$call['event_title']} "
    . "on {$call['event_date']} at {$call['venue']}. Your booking {$call['booking_code']} is confirmed. "
    . "Please carry your digital event pass. See you there!";

  $ok = VoiceService::placeCall('+91' . $call['mobile_number'], $script);
  $db->prepare('UPDATE ai_voice_calls SET status = ? WHERE id = ?')
     ->execute([$ok ? 'completed' : 'failed', $call['id']]);
  if ($ok) $sent++;
}

echo "Processed " . count($due) . " reminder(s), $sent placed successfully.\n";

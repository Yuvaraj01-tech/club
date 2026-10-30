<?php
/**
 * Schedules and places the "4-5 days before event" female AI voice reminder call.
 * Uses Twilio + Amazon Polly TTS (female, Indian-English voice) once
 * backend/config/config.php has voice.enabled = true and real Twilio
 * credentials. With voice.enabled = false (the default), calls are still
 * recorded as 'scheduled'/'completed' in ai_voice_calls and logged only —
 * nothing dials out, so the rest of the platform stays fully functional.
 */

class VoiceService {
  /** Insert a scheduled reminder row for a confirmed booking. */
  public static function scheduleReminder(int $bookingId, string $eventDate): void {
    $cfg = lm_config()['voice'];
    $daysBefore = $cfg['days_before_event'] ?? 5;
    $callDate = (new DateTime($eventDate))->modify("-{$daysBefore} days")->format('Y-m-d H:i:s');

    $stmt = lm_db()->prepare(
      'INSERT INTO ai_voice_calls (booking_id, scheduled_at, status) VALUES (?, ?, "scheduled")'
    );
    $stmt->execute([$bookingId, $callDate]);
  }

  /**
   * Actually places the call. Intended to be invoked from a daily cron
   * (see api/cron/send-voice-reminders.php). Returns true if dispatched.
   */
  public static function placeCall(string $mobileWithCountryCode, string $script): bool {
    $cfg = lm_config()['voice'];
    if (empty($cfg['enabled'])) {
      error_log("[HELLOHOBNOBBERS][voice:disabled] to=$mobileWithCountryCode script=$script");
      return false;
    }
    if (empty($cfg['account_sid']) || empty($cfg['auth_token']) || empty($cfg['from_number'])) {
      error_log('[HELLOHOBNOBBERS][voice] enabled but account_sid/auth_token/from_number missing.');
      return false;
    }

    $voice = $cfg['tts_voice'] ?? 'Polly.Raveena';
    $twiml = '<Response><Say voice="' . htmlspecialchars($voice) . '" language="en-IN">'
           . htmlspecialchars($script) . '</Say></Response>';

    $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$cfg['account_sid']}/Calls.json");
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_USERPWD => "{$cfg['account_sid']}:{$cfg['auth_token']}",
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query([
        'To' => $mobileWithCountryCode,
        'From' => $cfg['from_number'],
        'Twiml' => $twiml,
      ]),
      CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || $code >= 400) {
      error_log('[HELLOHOBNOBBERS][voice] Twilio call failed: ' . ($err ?: $resp));
      return false;
    }
    return true;
  }
}

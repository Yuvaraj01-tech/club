<?php
/**
 * Sends WhatsApp messages via Meta's WhatsApp Business Cloud API.
 * Requires a Meta developer app + verified business number.
 * https://developers.facebook.com/docs/whatsapp/cloud-api
 *
 * Until real credentials are configured (whatsapp.enabled = false), this
 * silently no-ops and logs the message so the rest of the app keeps working.
 */

class WhatsAppService {
  public static function send(string $mobileWithCountryCode, string $message): bool {
    $cfg = lm_config()['whatsapp'];
    if (empty($cfg['enabled'])) {
      error_log("[HELLOHOBNOBBERS][whatsapp:disabled] to=$mobileWithCountryCode msg=$message");
      return false;
    }

    $url = "https://graph.facebook.com/{$cfg['api_version']}/{$cfg['phone_number_id']}/messages";
    $payload = [
      'messaging_product' => 'whatsapp',
      'to' => $mobileWithCountryCode,
      'type' => 'text',
      'text' => ['body' => $message],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($payload),
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $cfg['access_token'],
        'Content-Type: application/json',
      ],
      CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
  }
}

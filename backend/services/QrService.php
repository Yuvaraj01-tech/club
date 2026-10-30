<?php
/**
 * Generates a QR PNG for a pass payload. Uses the free api.qrserver.com service
 * (no key needed) so no extra PHP extensions are required on shared hosting.
 * If 'qr.use_remote_api' is false, only the payload string is stored and the
 * frontend can render the QR client-side instead (e.g. with a JS QR library).
 */

class QrService {
  public static function generate(string $payload, string $passCode): ?string {
    $cfg = lm_config()['qr'];
    if (empty($cfg['use_remote_api'])) return null;

    $url = $cfg['remote_api_url'] . '?size=300x300&data=' . urlencode($payload);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $imageData = curl_exec($ch);
    $ok = !curl_errno($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    if (!$ok || !$imageData) return null;

    $dir = __DIR__ . '/../uploads/passes';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = $passCode . '.png';
    file_put_contents($dir . '/' . $filename, $imageData);
    return 'uploads/passes/' . $filename;
  }
}

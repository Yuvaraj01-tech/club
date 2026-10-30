<?php
class EmailService {
  public static function send(string $toEmail, string $subject, string $htmlBody): bool {
    $cfg = lm_config()['mail'];
    if (!$toEmail) return false;
    if (!empty($cfg['smtp']['enabled'])) return self::sendViaSmtp($toEmail, $subject, $htmlBody, $cfg);
    $from = $cfg['from_name'] . ' <' . $cfg['from_address'] . '>';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $from\r\n";
    return @mail($toEmail, $subject, $htmlBody, $headers);
  }
  private static function sendViaSmtp(string $toEmail, string $subject, string $htmlBody, array $cfg): bool {
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($autoload)) {
      error_log('[HELLOHOBNOBBERS] SMTP is enabled but PHPMailer is not installed. Run: composer install');
      return false;
    }
    require_once $autoload;
    try {
      $mail = new PHPMailer\PHPMailer\PHPMailer(true);
      $mail->isSMTP();
      $mail->SMTPDebug = 2;
      $mail->Host = $cfg['smtp']['host'];
      $mail->SMTPAuth = true;
      $mail->Username = $cfg['smtp']['username'];
      $mail->Password = $cfg['smtp']['password'];
      $mail->SMTPSecure = $cfg['smtp']['encryption'];
      $mail->Port = (int)$cfg['smtp']['port'];
      $mail->CharSet = 'UTF-8';
      $mail->setFrom($cfg['from_address'], $cfg['from_name']);
      $mail->addAddress($toEmail);
      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body = $htmlBody;
      $mail->send();
      return true;
    } catch (Throwable $e) {
      error_log('[HELLOHOBNOBBERS] SMTP send failed: ' . $e->getMessage());
      return false;
    }
  }
}

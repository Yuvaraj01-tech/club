<?php
/**
 * Minimal Razorpay integration using raw cURL calls to their REST API.
 * No Composer/SDK required — works on shared cPanel hosting out of the box.
 * Docs: https://razorpay.com/docs/api/orders/ and /payments/#verify-payment-signature
 */

class RazorpayService {
  private string $keyId;
  private string $keySecret;

  public function __construct() {
    $cfg = lm_config()['razorpay'];
    $this->keyId = $cfg['key_id'];
    $this->keySecret = $cfg['key_secret'];
  }

  private function request(string $method, string $path, array $body = []): array {
    $ch = curl_init("https://api.razorpay.com/v1/$path");
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_USERPWD => "{$this->keyId}:{$this->keySecret}",
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS => $body ? json_encode($body) : null,
      CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
      throw new RuntimeException("Razorpay request failed: $err");
    }
    $decoded = json_decode($raw, true) ?? [];
    if ($httpCode >= 400) {
      throw new RuntimeException('Razorpay error: ' . ($decoded['error']['description'] ?? $raw));
    }
    return $decoded;
  }

  /** Create an order for a booking amount (in rupees). Amount is converted to paise. */
  public function createOrder(float $amountRupees, ?string $receipt = null): array {
    return $this->request('POST', 'orders', [
      'amount' => (int) round($amountRupees * 100),
      'currency' => 'INR',
      'receipt' => $receipt ?? uniqid('lm_'),
      'payment_capture' => 1,
    ]);
  }

  /** Verify the signature returned by Razorpay Checkout after a successful payment. */
  public function verifySignature(string $orderId, string $paymentId, string $signature): bool {
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->keySecret);
    return hash_equals($expected, $signature);
  }

  public function fetchPayment(string $paymentId): array {
    return $this->request('GET', "payments/$paymentId");
  }

  public function publicKeyId(): string {
    return $this->keyId;
  }
}

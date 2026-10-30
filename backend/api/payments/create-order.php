<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../services/RazorpayService.php';

$user = require_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST required', 405);
}

$in = json_input();

require_fields($in, ['booking_id']);

$bookingId = (int)$in['booking_id'];

if ($bookingId <= 0) {
    fail('Invalid booking ID.', 422);
}

$db = lm_db();


/*
|--------------------------------------------------------------------------
| Get booking
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare(
    'SELECT
        id,
        booking_id,
        amount,
        booking_status,
        payment_status
     FROM bookings
     WHERE id = ?
       AND user_id = ?'
);

$stmt->execute([
    $bookingId,
    $user['id']
]);

$booking = $stmt->fetch();

if (!$booking) {
    fail('Booking not found.', 404);
}


/*
|--------------------------------------------------------------------------
| Check booking status
|--------------------------------------------------------------------------
*/

if ($booking['booking_status'] !== 'PENDING') {
    fail(
        'This booking is not awaiting payment.',
        409
    );
}


/*
|--------------------------------------------------------------------------
| Check payment status
|--------------------------------------------------------------------------
*/

if ($booking['payment_status'] === 'PAID') {
    fail(
        'This booking has already been paid.',
        409
    );
}


/*
|--------------------------------------------------------------------------
| Create Razorpay order
|--------------------------------------------------------------------------
*/

try {

    $razorpay = new RazorpayService();

    $order = $razorpay->createOrder(
        (float)$booking['amount'],
        $booking['booking_id']
    );

} catch (Throwable $e) {

    error_log(
        '[HELLOHOBNOBBERS] Razorpay order failed: '
        . $e->getMessage()
    );

    fail(
        'Could not initiate payment right now. Please try again shortly.',
        502
    );
}


/*
|--------------------------------------------------------------------------
| Save payment record
|--------------------------------------------------------------------------
*/

$paymentStmt = $db->prepare(
    'INSERT INTO payments
    (
        booking_id,
        razorpay_order_id,
        amount,
        currency,
        status
    )
    VALUES
    (
        ?,
        ?,
        ?,
        ?,
        ?
    )'
);

$paymentStmt->execute([
    $booking['id'],
    $order['id'],
    $booking['amount'],
    $order['currency'],
    'created'
]);


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond([
    'razorpay_key_id' => $razorpay->publicKeyId(),
    'order_id' => $order['id'],
    'amount' => $order['amount'],
    'currency' => $order['currency'],
    'booking_code' => $booking['booking_id']
]);
<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../services/RazorpayService.php';
require_once __DIR__ . '/../../services/EmailService.php';

$user = require_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST required', 405);
}

$in = json_input();

require_fields($in, [
    'razorpay_order_id',
    'razorpay_payment_id',
    'razorpay_signature'
]);

$db = lm_db();


/*
|--------------------------------------------------------------------------
| Find payment record
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare(
    'SELECT *
     FROM payments
     WHERE razorpay_order_id = ?'
);

$stmt->execute([
    $in['razorpay_order_id']
]);

$payment = $stmt->fetch();

if (!$payment) {
    fail('Payment record not found.', 404);
}


/*
|--------------------------------------------------------------------------
| Find booking
|--------------------------------------------------------------------------
*/

$bookingStmt = $db->prepare(
    'SELECT *
     FROM bookings
     WHERE id = ?
       AND user_id = ?'
);

$bookingStmt->execute([
    $payment['booking_id'],
    $user['id']
]);

$booking = $bookingStmt->fetch();

if (!$booking) {
    fail('Booking not found.', 404);
}


/*
|--------------------------------------------------------------------------
| Verify Razorpay signature
|--------------------------------------------------------------------------
*/

$razorpay = new RazorpayService();

$isValid = $razorpay->verifySignature(
    $in['razorpay_order_id'],
    $in['razorpay_payment_id'],
    $in['razorpay_signature']
);

if (!$isValid) {

    $db->prepare(
        'UPDATE payments
         SET
            status = ?,
            razorpay_payment_id = ?
         WHERE id = ?'
    )->execute([
        'failed',
        $in['razorpay_payment_id'],
        $payment['id']
    ]);

    fail(
        'Payment verification failed.',
        400
    );
}


/*
|--------------------------------------------------------------------------
| Finalize payment + booking + pass
|--------------------------------------------------------------------------
*/

$db->beginTransaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Update payment
    |--------------------------------------------------------------------------
    */

    $paymentUpdate = $db->prepare(
        'UPDATE payments
         SET
            status = ?,
            razorpay_payment_id = ?,
            razorpay_signature = ?,
            paid_at = NOW()
         WHERE id = ?'
    );

    $paymentUpdate->execute([
        'paid',
        $in['razorpay_payment_id'],
        $in['razorpay_signature'],
        $payment['id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | Update booking
    |--------------------------------------------------------------------------
    */

    $bookingUpdate = $db->prepare(
        'UPDATE bookings
         SET
            payment_status = ?,
            booking_status = ?,
            payment_reference = ?
         WHERE id = ?'
    );

    $bookingUpdate->execute([
        'PAID',
        'CONFIRMED',
        $in['razorpay_payment_id'],
        $booking['id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | Create event pass if it does not already exist
    |--------------------------------------------------------------------------
    */

    $passCheck = $db->prepare(
        'SELECT id, pass_token, status
         FROM event_passes
         WHERE booking_id = ?'
    );

    $passCheck->execute([
        $booking['id']
    ]);

    $existingPass = $passCheck->fetch();

    if (!$existingPass) {

        $passToken = generate_pass_code();

        $passInsert = $db->prepare(
            'INSERT INTO event_passes
            (
                booking_id,
                pass_token,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?
            )'
        );

        $passInsert->execute([
            $booking['id'],
            $passToken,
            'VALID'
        ]);

    } else {

        /*
        |--------------------------------------------------------------------------
        | If an old cancelled/expired pass exists, make it valid again
        |--------------------------------------------------------------------------
        */

        if (in_array(
            $existingPass['status'],
            ['CANCELLED', 'EXPIRED'],
            true
        )) {

            $db->prepare(
                'UPDATE event_passes
                 SET
                    status = ?,
                    used_at = NULL
                 WHERE id = ?'
            )->execute([
                'VALID',
                $existingPass['id']
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Commit transaction
    |--------------------------------------------------------------------------
    */

    $db->commit();

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log(
        '[HELLOHOBNOBBERS] Payment finalization error: '
        . $e->getMessage()
    );

    fail(
        'Payment succeeded but booking finalization failed. Please contact support.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| Get event details
|--------------------------------------------------------------------------
*/

$eventStmt = $db->prepare(
    'SELECT
        event_name,
        event_date,
        start_time,
        end_time,
        venue
     FROM events
     WHERE id = ?'
);

$eventStmt->execute([
    $booking['event_id']
]);

$event = $eventStmt->fetch();


/*
|--------------------------------------------------------------------------
| Notification
|--------------------------------------------------------------------------
*/

if ($event) {

    notify_user(
        $user['id'],
        'PAYMENT SUCCESSFUL ✓',
        'Booking ' . $booking['booking_id']
        . ' confirmed for '
        . $event['event_name']
        . '. Your event pass is ready.',
        'payment'
    );

}


/*
|--------------------------------------------------------------------------
| Email
|--------------------------------------------------------------------------
|
| Actual users table does not have an email column.
| username contains the registered email.
|
*/

try {

    $emailStmt = $db->prepare(
        'SELECT username
         FROM users
         WHERE id = ?'
    );

    $emailStmt->execute([
        $user['id']
    ]);

    $email = $emailStmt->fetchColumn();

    if ($email && $event) {

        EmailService::send(
            $email,
            'Your Hello Hobnobbers Event Pass — '
            . $event['event_name'],
            'Payment successful.<br>'
            . 'Booking ID: '
            . htmlspecialchars($booking['booking_id'])
            . '<br>'
            . 'Event: '
            . htmlspecialchars($event['event_name'])
            . '<br>'
            . 'Date: '
            . htmlspecialchars($event['event_date'])
            . '<br>'
            . 'Venue: '
            . htmlspecialchars($event['venue'])
            . '<br><br>'
            . 'Your event pass is available in your account.'
        );
    }

} catch (Throwable $e) {

    error_log(
        '[HELLOHOBNOBBERS] Payment email error: '
        . $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

respond([
    'message' => 'PAYMENT SUCCESSFUL',
    'booking_code' => $booking['booking_id'],
    'payment_status' => 'PAID',
    'booking_status' => 'CONFIRMED'
]);
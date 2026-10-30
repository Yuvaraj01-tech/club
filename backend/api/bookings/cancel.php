<?php

require_once __DIR__ . '/../../core/bootstrap.php';

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

$db->beginTransaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Get booking
    |--------------------------------------------------------------------------
    */

    $stmt = $db->prepare(
        'SELECT
            id,
            slot_id,
            booking_status,
            payment_status
         FROM bookings
         WHERE id = ?
           AND user_id = ?
         FOR UPDATE'
    );

    $stmt->execute([
        $bookingId,
        $user['id']
    ]);

    $booking = $stmt->fetch();

    if (!$booking) {
        throw new RuntimeException(
            'Booking not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check cancellation status
    |--------------------------------------------------------------------------
    */

    if ($booking['booking_status'] === 'CANCELLED') {
        throw new RuntimeException(
            'Booking already cancelled.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Do not cancel already paid booking here
    |--------------------------------------------------------------------------
    |
    | A paid booking may require a payment/refund process.
    | That will be handled through the payment module.
    |
    */

    if ($booking['payment_status'] === 'PAID') {
        throw new RuntimeException(
            'This booking has already been paid. Please use the refund process.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Cancel booking
    |--------------------------------------------------------------------------
    */

    $updateBooking = $db->prepare(
        'UPDATE bookings
         SET booking_status = ?
         WHERE id = ?'
    );

    $updateBooking->execute([
        'CANCELLED',
        $booking['id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | Release slot capacity
    |--------------------------------------------------------------------------
    */

    $updateSlot = $db->prepare(
        'UPDATE event_slots
         SET
            booked_count = GREATEST(booked_count - 1, 0),
            status = CASE
                WHEN booked_count - 1 < capacity
                THEN "active"
                ELSE status
            END
         WHERE id = ?'
    );

    $updateSlot->execute([
        $booking['slot_id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | Cancel event pass if one exists
    |--------------------------------------------------------------------------
    */

    $updatePass = $db->prepare(
        'UPDATE event_passes
         SET status = ?
         WHERE booking_id = ?'
    );

    $updatePass->execute([
        'CANCELLED',
        $booking['id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    */

    $db->commit();


    /*
    |--------------------------------------------------------------------------
    | Activity log
    |--------------------------------------------------------------------------
    */

    log_activity(
        'user',
        $user['id'],
        'booking_cancelled',
        [
            'booking_id' => $booking['id']
        ]
    );


    respond([
        'message' => 'Booking cancelled successfully.'
    ]);


} catch (RuntimeException $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    fail(
        $e->getMessage(),
        409
    );
}
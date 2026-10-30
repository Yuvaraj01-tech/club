<?php

require_once __DIR__ . '/../../core/bootstrap.php';

$user = require_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST required', 405);
}


/*
|--------------------------------------------------------------------------
| Check user approval
|--------------------------------------------------------------------------
*/

$db = lm_db();

$stmt = $db->prepare(
    'SELECT approval_status
     FROM users
     WHERE id = ?'
);

$stmt->execute([$user['id']]);

$approvalStatus = $stmt->fetchColumn();

if ($approvalStatus !== 'approved') {
    fail(
        'Your profile must be approved before you can book an event.',
        403
    );
}


/*
|--------------------------------------------------------------------------
| Read request
|--------------------------------------------------------------------------
*/

$in = json_input();

require_fields($in, [
    'event_id',
    'slot_id'
]);

$eventId = (int)$in['event_id'];
$slotId  = (int)$in['slot_id'];

if ($eventId <= 0 || $slotId <= 0) {
    fail('Invalid event or slot.', 422);
}


/*
|--------------------------------------------------------------------------
| Start transaction
|--------------------------------------------------------------------------
*/

$db->beginTransaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Lock slot
    |--------------------------------------------------------------------------
    */

    $slotStmt = $db->prepare(
        'SELECT
            id,
            event_id,
            capacity,
            booked_count,
            status
         FROM event_slots
         WHERE id = ?
           AND event_id = ?
         FOR UPDATE'
    );

    $slotStmt->execute([
        $slotId,
        $eventId
    ]);

    $slot = $slotStmt->fetch();

    if (!$slot) {
        throw new RuntimeException('Slot not found.');
    }


    /*
    |--------------------------------------------------------------------------
    | Check slot status
    |--------------------------------------------------------------------------
    */

    if ($slot['status'] !== 'open') {
        throw new RuntimeException(
            'This slot is not available.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check capacity
    |--------------------------------------------------------------------------
    */

    if ((int)$slot['booked_count'] >= (int)$slot['capacity']) {
        throw new RuntimeException('SLOT_FULL');
    }


    /*
    |--------------------------------------------------------------------------
    | Get event details
    |--------------------------------------------------------------------------
    */

    $eventStmt = $db->prepare(
        'SELECT
            ticket_price,
            status,
            booking_closing
         FROM events
         WHERE id = ?'
    );

    $eventStmt->execute([
        $eventId
    ]);

    $event = $eventStmt->fetch();

    if (!$event) {
        throw new RuntimeException(
            'Event not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Event must be published
    |--------------------------------------------------------------------------
    */

    if ($event['status'] !== 'published') {
        throw new RuntimeException(
            'Event is not available for booking.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check booking closing time
    |--------------------------------------------------------------------------
    */

    if (
        !empty($event['booking_closing']) &&
        strtotime($event['booking_closing']) < time()
    ) {
        throw new RuntimeException(
            'Booking is closed for this event.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate booking
    |--------------------------------------------------------------------------
    */

    $dupStmt = $db->prepare(
        'SELECT id
         FROM bookings
         WHERE user_id = ?
           AND event_id = ?
           AND booking_status != ?'
    );

    $dupStmt->execute([
        $user['id'],
        $eventId,
        'CANCELLED'
    ]);

    if ($dupStmt->fetch()) {
        throw new RuntimeException('DUPLICATE');
    }


    /*
    |--------------------------------------------------------------------------
    | Generate booking ID
    |--------------------------------------------------------------------------
    */

    $bookingCode = generate_booking_code();


    /*
    |--------------------------------------------------------------------------
    | Create booking
    |--------------------------------------------------------------------------
    */

    $bookingStmt = $db->prepare(
        'INSERT INTO bookings
        (
            booking_id,
            user_id,
            event_id,
            slot_id,
            amount,
            payment_status,
            booking_status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )'
    );

    $bookingStmt->execute([
        $bookingCode,
        $user['id'],
        $eventId,
        $slotId,
        $event['ticket_price'],
        'PENDING',
        'PENDING'
    ]);

    $bookingId = (int)$db->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Increase booked count
    |--------------------------------------------------------------------------
    */

    $newCount =
        (int)$slot['booked_count'] + 1;


    $newStatus =
        $newCount >= (int)$slot['capacity']
            ? 'full'
            : 'active';


    $updateSlot = $db->prepare(
        'UPDATE event_slots
         SET
            booked_count = ?,
            status = ?
         WHERE id = ?'
    );

    $updateSlot->execute([
        $newCount,
        $newStatus,
        $slotId
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
        'booking_created',
        [
            'booking_id' => $bookingId,
            'booking_code' => $bookingCode,
            'event_id' => $eventId,
            'slot_id' => $slotId
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    respond([
        'message' => 'Slot reserved. Proceed to payment.',
        'booking_id' => $bookingId,
        'booking_code' => $bookingCode,
        'amount' => $event['ticket_price'],
        'payment_status' => 'PENDING',
        'booking_status' => 'PENDING'
    ], 201);


} catch (RuntimeException $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }


    $map = [

        'SLOT_FULL' =>
            'That slot is full. Please choose another.',

        'DUPLICATE' =>
            'You already have a booking for this event.'

    ];


    fail(
        $map[$e->getMessage()]
            ?? $e->getMessage(),
        409
    );
}
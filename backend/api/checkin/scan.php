<?php

require_once __DIR__ . '/../../core/bootstrap.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST required', 405);
}

$in = json_input();

require_fields($in, ['pass_code']);

$code = trim($in['pass_code']);

if ($code === '') {
    fail('Pass code is required.', 422);
}

$db = lm_db();

/*
|--------------------------------------------------------------------------
| Mark passes for past events as expired
|--------------------------------------------------------------------------
*/
$db->exec(
    "UPDATE event_passes ep
     JOIN bookings b ON b.id = ep.booking_id
     JOIN events e ON e.id = b.event_id
     SET ep.status = 'EXPIRED'
     WHERE ep.status = 'VALID'
       AND e.event_date < CURDATE()"
);

/*
|--------------------------------------------------------------------------
| Find pass
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare(
    'SELECT
        ep.id AS pass_id,
        ep.booking_id,
        ep.pass_token,
        ep.status AS pass_status,
        ep.used_at,

        b.booking_id AS booking_code,
        b.booking_status,
        b.payment_status,
        b.amount,

        e.id AS event_id,
        e.event_name AS event_title,
        e.event_date,
        e.venue,
        e.organizer,
        e.start_time AS event_start_time,
        e.end_time AS event_end_time,

        s.slot_name,
        s.start_time AS slot_start_time,
        s.end_time AS slot_end_time,

        u.username,
        up.full_name,
        up.mobile_number

     FROM event_passes ep

     JOIN bookings b
        ON b.id = ep.booking_id

     JOIN events e
        ON e.id = b.event_id

     JOIN event_slots s
        ON s.id = b.slot_id

     JOIN users u
        ON u.id = b.user_id

     LEFT JOIN user_profiles up
        ON up.user_id = u.id

     WHERE ep.pass_token = ?'
);

$stmt->execute([$code]);

$pass = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| Invalid pass
|--------------------------------------------------------------------------
*/
if (!$pass) {

    $log = $db->prepare(
        'INSERT INTO checkins
        (pass_id, scanned_by, result, device_info)
        VALUES (NULL, ?, ?, ?)'
    );

    $log->execute([
        $admin['id'],
        'invalid',
        $in['device_info'] ?? null
    ]);

    fail(
        'INVALID PASS',
        404,
        ['result' => 'invalid']
    );
}

/*
|--------------------------------------------------------------------------
| Optional event validation
|--------------------------------------------------------------------------
*/
if (
    !empty($in['event_id']) &&
    (int)$in['event_id'] !== (int)$pass['event_id']
) {

    $log = $db->prepare(
        'INSERT INTO checkins
        (pass_id, scanned_by, result, device_info)
        VALUES (?, ?, ?, ?)'
    );

    $log->execute([
        $pass['pass_id'],
        $admin['id'],
        'wrong_event',
        $in['device_info'] ?? null
    ]);

    fail(
        'INVALID PASS — WRONG EVENT',
        409,
        ['result' => 'wrong_event']
    );
}

/*
|--------------------------------------------------------------------------
| Already used
|--------------------------------------------------------------------------
*/
if ($pass['pass_status'] === 'USED') {

    $log = $db->prepare(
        'INSERT INTO checkins
        (pass_id, scanned_by, result, device_info)
        VALUES (?, ?, ?, ?)'
    );

    $log->execute([
        $pass['pass_id'],
        $admin['id'],
        'already_used',
        $in['device_info'] ?? null
    ]);

    fail(
        'PASS ALREADY USED',
        409,
        ['result' => 'already_used']
    );
}

/*
|--------------------------------------------------------------------------
| Cancelled / expired
|--------------------------------------------------------------------------
*/
if (
    in_array(
        $pass['pass_status'],
        ['CANCELLED', 'EXPIRED'],
        true
    )
) {

    $log = $db->prepare(
        'INSERT INTO checkins
        (pass_id, scanned_by, result, device_info)
        VALUES (?, ?, ?, ?)'
    );

    $log->execute([
        $pass['pass_id'],
        $admin['id'],
        'expired',
        $in['device_info'] ?? null
    ]);

    fail(
        'INVALID PASS — ' . $pass['pass_status'],
        409,
        ['result' => 'expired']
    );
}

/*
|--------------------------------------------------------------------------
| Check payment and booking status
|--------------------------------------------------------------------------
*/
if ($pass['payment_status'] !== 'PAID') {

    $log = $db->prepare(
        'INSERT INTO checkins
        (pass_id, scanned_by, result, device_info)
        VALUES (?, ?, ?, ?)'
    );

    $log->execute([
        $pass['pass_id'],
        $admin['id'],
        'invalid',
        $in['device_info'] ?? null
    ]);

    fail(
        'PAYMENT NOT COMPLETED',
        409,
        ['result' => 'invalid']
    );
}

if ($pass['booking_status'] !== 'CONFIRMED') {

    $log = $db->prepare(
        'INSERT INTO checkins
        (pass_id, scanned_by, result, device_info)
        VALUES (?, ?, ?, ?)'
    );

    $log->execute([
        $pass['pass_id'],
        $admin['id'],
        'invalid',
        $in['device_info'] ?? null
    ]);

    fail(
        'BOOKING IS NOT CONFIRMED',
        409,
        ['result' => 'invalid']
    );
}

/*
|--------------------------------------------------------------------------
| Approve entry
|--------------------------------------------------------------------------
*/
$db->beginTransaction();

try {

    $updatePass = $db->prepare(
        'UPDATE event_passes
         SET status = ?, used_at = NOW()
         WHERE id = ? AND status = ?'
    );

    $updatePass->execute([
        'USED',
        $pass['pass_id'],
        'VALID'
    ]);

    if ($updatePass->rowCount() !== 1) {
        throw new RuntimeException('PASS_ALREADY_USED');
    }

    $checkin = $db->prepare(
        'INSERT INTO checkins
        (pass_id, scanned_by, result, device_info)
        VALUES (?, ?, ?, ?)'
    );

    $checkin->execute([
        $pass['pass_id'],
        $admin['id'],
        'approved',
        $in['device_info'] ?? null
    ]);

    $db->commit();

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    if ($e->getMessage() === 'PASS_ALREADY_USED') {
        fail(
            'PASS ALREADY USED',
            409,
            ['result' => 'already_used']
        );
    }

    error_log(
        '[HELLOHOBNOBBERS] Check-in error: ' .
        $e->getMessage()
    );

    fail(
        'Could not complete check-in.',
        500
    );
}

/*
|--------------------------------------------------------------------------
| Activity log
|--------------------------------------------------------------------------
*/
log_activity(
    'admin',
    $admin['id'],
    'checkin_approved',
    [
        'pass_id' => $pass['pass_id'],
        'booking_id' => $pass['booking_id']
    ]
);

/*
|--------------------------------------------------------------------------
| Success response
|--------------------------------------------------------------------------
*/
respond([
    'result' => 'approved',
    'message' => 'PASS VERIFIED ✓ — ENTRY APPROVED',

    'guest_name' => $pass['full_name'] ?: $pass['username'],

    'username' => $pass['username'],

    'mobile_number' => $pass['mobile_number'],

    'event_title' => $pass['event_title'],

    'event_date' => $pass['event_date'],

    'venue' => $pass['venue'],

    'organizer' => $pass['organizer'],

    'booking_code' => $pass['booking_code'],

    'slot_name' => $pass['slot_name'],

    'slot_start_time' => $pass['slot_start_time'],

    'slot_end_time' => $pass['slot_end_time']
]);
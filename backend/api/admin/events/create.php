<?php

require_once __DIR__ . '/../../../core/bootstrap.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST required', 405);
}

$in = $_POST ?: json_input();

require_fields($in, [
    'title',
    'venue',
    'event_date',
    'start_time',
    'end_time',
    'ticket_price',
    'total_capacity'
]);

$bookingClosing = $in['booking_closing_at'] ?? null;

if (!empty($bookingClosing)) {
    $bookingClosing = str_replace('T', ' ', $bookingClosing);
}

$db = lm_db();

$db->beginTransaction();

try {

    // Create event
    $stmt = $db->prepare(
        'INSERT INTO events
        (
            event_name,
            event_date,
            venue,
            organizer,
            description,
            category,
            start_time,
            end_time,
            eligibility,
            ticket_price,
            booking_closing,
            important_information,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        trim($in['title']),
        $in['event_date'],
        trim($in['venue']),
        $in['organizer_name'] ?? 'Hello Hobnobbers Events',
        $in['description'] ?? null,
        $in['category'] ?? null,
        $in['start_time'],
        $in['end_time'],
        $in['eligibility_notes'] ?? null,
        (float)$in['ticket_price'],
        $bookingClosing,
        $in['important_info'] ?? null,
        $in['status'] ?? 'draft'
    ]);

    $eventId = (int)$db->lastInsertId();

    // Create default slot
    $slot = $db->prepare(
        'INSERT INTO event_slots
        (
            event_id,
            slot_name,
            start_time,
            end_time,
            capacity,
            booked_count,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $slot->execute([
        $eventId,
        'General Entry',
        $in['start_time'],
        $in['end_time'],
        (int)$in['total_capacity'],
        0,
        'active'
    ]);

    $db->commit();

    log_activity(
        'admin',
        $admin['id'],
        'event_created',
        ['event_id' => $eventId]
    );

    respond([
        'message' => 'Event created successfully.',
        'event_id' => $eventId
    ], 201);

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    fail(
        'Could not create event: ' . $e->getMessage(),
        500
    );
}
<?php

require_once __DIR__ . '/../../core/bootstrap.php';

$user = require_user();
$db = lm_db();

/* Expire passes for past events */
$expire = $db->prepare(
    "UPDATE event_passes ep
     JOIN bookings b ON b.id = ep.booking_id
     JOIN events e ON e.id = b.event_id
     SET ep.status = 'EXPIRED'
     WHERE ep.status = 'VALID'
       AND e.event_date < CURDATE()"
);
$expire->execute();

/* Get user's event passes */
$stmt = $db->prepare(
    'SELECT
        ep.id AS pass_id,
        ep.booking_id,
        ep.pass_token,
        ep.status AS pass_status,
        ep.used_at,
        ep.created_at AS pass_created_at,

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
        e.important_information,
        e.eligibility,

        s.id AS slot_id,
        s.slot_name,
        s.start_time AS slot_start_time,
        s.end_time AS slot_end_time,

        up.full_name

     FROM event_passes ep
     JOIN bookings b ON b.id = ep.booking_id
     JOIN events e ON e.id = b.event_id
     JOIN event_slots s ON s.id = b.slot_id
     LEFT JOIN user_profiles up ON up.user_id = b.user_id
     WHERE b.user_id = ?
     ORDER BY ep.created_at DESC'
);

$stmt->execute([$user['id']]);

respond([
    'passes' => $stmt->fetchAll()
]);
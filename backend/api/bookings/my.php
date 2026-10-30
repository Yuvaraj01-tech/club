<?php

require_once __DIR__ . '/../../core/bootstrap.php';

$user = require_user();

$db = lm_db();

$stmt = $db->prepare(
    'SELECT
        b.id,
        b.booking_id AS booking_code,
        b.booking_status AS status,
        b.amount AS booking_amount,
        b.payment_status,
        b.payment_reference,
        b.paid_at,
        b.created_at,

        e.id AS event_id,
        e.event_name AS event_title,
        e.event_date,
        e.venue,
        e.organizer,
        e.start_time AS event_start_time,
        e.end_time AS event_end_time,

        s.id AS slot_id,
        s.slot_name AS slot_label,
        s.start_time AS slot_start_time,
        s.end_time AS slot_end_time,

        p.status AS payment_gateway_status,
        p.razorpay_order_id,
        p.razorpay_payment_id,
        p.method AS payment_method,

        ep.pass_token AS pass_code,
        ep.status AS pass_status,
        ep.used_at AS pass_used_at

     FROM bookings b

     JOIN events e
        ON e.id = b.event_id

     JOIN event_slots s
        ON s.id = b.slot_id

     LEFT JOIN payments p
        ON p.booking_id = b.id

     LEFT JOIN event_passes ep
        ON ep.booking_id = b.id

     WHERE b.user_id = ?

     ORDER BY b.created_at DESC'
);

$stmt->execute([
    $user['id']
]);

$bookings = $stmt->fetchAll();

respond([
    'bookings' => $bookings
]);
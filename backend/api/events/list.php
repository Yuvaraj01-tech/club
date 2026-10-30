<?php

require_once __DIR__ . '/../../core/bootstrap.php';

$db = lm_db();

$sql = "
    SELECT
        e.id,
        e.event_name AS title,
        e.category,
        e.description,
        e.venue,
        e.organizer,
        e.event_date,
        e.start_time,
        e.end_time,
        e.ticket_price,
        COALESCE(SUM(s.capacity), 0) AS total_capacity,
        COALESCE(SUM(s.booked_count), 0) AS booked_count,
        e.eligibility,
        e.important_information,
        e.booking_closing,
        e.status
    FROM events e
    LEFT JOIN event_slots s
        ON s.event_id = e.id
    WHERE e.status = 'published'
      AND e.event_date >= CURDATE()
    GROUP BY e.id
    ORDER BY e.event_date ASC, e.start_time ASC
";

$rows = $db->query($sql)->fetchAll();

foreach ($rows as &$row) {
    $row['total_capacity'] = (int)$row['total_capacity'];
    $row['booked_count'] = (int)$row['booked_count'];
}

respond([
    'events' => $rows
]);
<?php

require_once __DIR__ . '/../../../core/bootstrap.php';

require_admin();

$db = lm_db();

$status = $_GET['status'] ?? '';

$sql = '
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
        e.eligibility,
        e.important_information,
        e.booking_closing,
        e.status,
        COALESCE(SUM(s.capacity), 0) AS total_capacity,
        COALESCE(SUM(s.booked_count), 0) AS booked_count
    FROM events e
    LEFT JOIN event_slots s
        ON s.event_id = e.id
';

$params = [];

if (in_array($status, ['draft', 'published', 'completed', 'cancelled'], true)) {
    $sql .= ' WHERE e.status = ?';
    $params[] = $status;
}

$sql .= '
    GROUP BY e.id
    ORDER BY e.event_date DESC, e.start_time DESC
';

$stmt = $db->prepare($sql);
$stmt->execute($params);

$events = $stmt->fetchAll();

foreach ($events as &$event) {
    $event['total_capacity'] = (int)$event['total_capacity'];
    $event['booked_count'] = (int)$event['booked_count'];
}

respond([
    'events' => $events
]);
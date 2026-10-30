<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_admin();

$type = $_GET['type'] ?? '';
$db = lm_db();

$queries = [
  'users' => "SELECT u.id, u.username, u.email, u.status, up.full_name, up.mobile_number, u.created_at
              FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id ORDER BY u.created_at DESC",
  'events' => "SELECT id, title, category, venue, event_date, ticket_price, total_capacity, status FROM events ORDER BY event_date DESC",
  'bookings' => "SELECT b.booking_code, u.username, e.title AS event_title, s.slot_label, b.status, b.booking_amount, b.created_at
                 FROM bookings b JOIN users u ON u.id=b.user_id JOIN events e ON e.id=b.event_id JOIN event_slots s ON s.id=b.slot_id
                 ORDER BY b.created_at DESC",
  'payments' => "SELECT p.razorpay_order_id, p.razorpay_payment_id, b.booking_code, u.username, p.amount, p.status, p.paid_at
                 FROM payments p JOIN bookings b ON b.id=p.booking_id JOIN users u ON u.id=b.user_id ORDER BY p.created_at DESC",
  'cancellations' => "SELECT booking_code, user_id, event_id, updated_at FROM bookings WHERE status='cancelled' ORDER BY updated_at DESC",
  'waitlists' => "SELECT w.id, u.username, e.title AS event_title, s.slot_label, w.status, w.created_at
                  FROM waitlist w JOIN users u ON u.id=w.user_id JOIN events e ON e.id=w.event_id JOIN event_slots s ON s.id=w.slot_id
                  ORDER BY w.created_at DESC",
  'attendance' => "SELECT c.scanned_at, c.result, ep.pass_code, b.booking_code, up.full_name
                   FROM checkins c JOIN event_passes ep ON ep.id=c.pass_id JOIN bookings b ON b.id=ep.booking_id
                   JOIN users u ON u.id=b.user_id LEFT JOIN user_profiles up ON up.user_id=u.id
                   ORDER BY c.scanned_at DESC",
];

if (!isset($queries[$type])) fail('Unknown report type. Valid: ' . implode(', ', array_keys($queries)), 422);

$rows = $db->query($queries[$type])->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '-report-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
if ($rows) {
  fputcsv($out, array_keys($rows[0]));
  foreach ($rows as $row) fputcsv($out, $row);
} else {
  fputcsv($out, ['No data']);
}
fclose($out);
exit;

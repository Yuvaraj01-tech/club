<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../services/EmailService.php';
$db=lm_db();
$s=$db->query("SELECT b.id booking_id,b.booking_code,u.id user_id,u.email,p.full_name,e.title,e.event_date,e.start_time,e.end_time,e.venue FROM bookings b JOIN users u ON u.id=b.user_id LEFT JOIN user_profiles p ON p.user_id=u.id JOIN events e ON e.id=b.event_id WHERE b.status='confirmed' AND e.event_date=DATE_ADD(CURDATE(),INTERVAL 2 DAY)");
$sent=0;
foreach($s->fetchAll() as $r){
 $x=$db->prepare("SELECT id FROM activity_logs WHERE actor_type='system' AND action='event_reminder_2d' AND JSON_EXTRACT(meta,'$.booking_id')=? LIMIT 1");$x->execute([(int)$r['booking_id']]);
 if($x->fetch())continue;
 notify_user((int)$r['user_id'],'Event Reminder — '.$r['title'],"Your event is in 2 days: {$r['event_date']} at {$r['venue']}. Booking ID: {$r['booking_code']}.",'event');
 if($r['email']) EmailService::send($r['email'],'Reminder: '.$r['title'].' is in 2 days',"Hi ".htmlspecialchars($r['full_name']??'').",<br>Your event <b>".htmlspecialchars($r['title'])."</b> is in 2 days.<br>Date: {$r['event_date']}<br>Time: {$r['start_time']} - {$r['end_time']}<br>Venue: ".htmlspecialchars($r['venue'])."<br>Booking ID: {$r['booking_code']}.");
 log_activity('system',null,'event_reminder_2d',['booking_id'=>(int)$r['booking_id']]);$sent++;
}
respond(['sent'=>$sent]);

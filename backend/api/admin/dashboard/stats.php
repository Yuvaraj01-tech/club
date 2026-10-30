<?php
require_once __DIR__ . '/../../../core/bootstrap.php'; require_admin(); $db=lm_db();
$q=fn($s)=>(int)$db->query($s)->fetchColumn();
respond(['stats'=>[
'total_users'=>$q('SELECT COUNT(*) FROM users'),
'pending_verification'=>$q("SELECT COUNT(*) FROM users WHERE status='pending_review'"),
'approved_users'=>$q("SELECT COUNT(*) FROM users WHERE status='approved'"),
'rejected_users'=>$q("SELECT COUNT(*) FROM users WHERE status='rejected'"),
'blocked_users'=>$q("SELECT COUNT(*) FROM users WHERE status='blocked'"),
'male_users'=>$q("SELECT COUNT(*) FROM user_profiles WHERE gender='male'"),
'female_users'=>$q("SELECT COUNT(*) FROM user_profiles WHERE gender='female'"),
'total_events'=>$q('SELECT COUNT(*) FROM events'),
'upcoming_events'=>$q("SELECT COUNT(*) FROM events WHERE status='published' AND event_date>=CURDATE()"),
'total_bookings'=>$q('SELECT COUNT(*) FROM bookings'),
'confirmed_bookings'=>$q("SELECT COUNT(*) FROM bookings WHERE status='confirmed'"),
'pending_bookings'=>$q("SELECT COUNT(*) FROM bookings WHERE status='pending_payment'"),
'cancelled_bookings'=>$q("SELECT COUNT(*) FROM bookings WHERE status='cancelled'"),
'total_revenue'=>(float)$db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")->fetchColumn(),
'attendance'=>$q("SELECT COUNT(*) FROM checkins WHERE result='approved'")
]]);

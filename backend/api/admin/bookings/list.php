<?php
require_once __DIR__ . '/../../../core/bootstrap.php';require_admin();$db=lm_db();$status=$_GET['status']??'';
$sql='SELECT b.id,b.booking_code,b.status,b.booking_amount,b.created_at,u.username,up.full_name,up.mobile_number,e.title AS event_title,e.event_date,s.slot_label,p.status AS payment_status FROM bookings b JOIN users u ON u.id=b.user_id LEFT JOIN user_profiles up ON up.user_id=u.id JOIN events e ON e.id=b.event_id JOIN event_slots s ON s.id=b.slot_id LEFT JOIN payments p ON p.booking_id=b.id';
$params=[];if(in_array($status,['confirmed','pending_payment','cancelled'],true)){$sql.=' WHERE b.status=?';$params[]=$status;}$sql.=' ORDER BY b.created_at DESC';$s=$db->prepare($sql);$s->execute($params);respond(['bookings'=>$s->fetchAll()]);

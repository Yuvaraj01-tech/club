<?php
require_once __DIR__ . '/../../../core/bootstrap.php';require_admin();$db=lm_db();$status=$_GET['status']??'';
$sql='SELECT p.id,p.razorpay_order_id,p.razorpay_payment_id,p.amount,p.currency,p.status,p.paid_at,b.booking_code,u.username,e.title AS event_title FROM payments p JOIN bookings b ON b.id=p.booking_id JOIN users u ON u.id=b.user_id JOIN events e ON e.id=b.event_id';
$params=[];if(in_array($status,['paid','failed','created','refunded'],true)){$sql.=' WHERE p.status=?';$params[]=$status;}$sql.=' ORDER BY p.created_at DESC';$s=$db->prepare($sql);$s->execute($params);respond(['payments'=>$s->fetchAll()]);

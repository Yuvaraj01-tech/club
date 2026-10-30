<?php
require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../services/EmailService.php';
require_once __DIR__ . '/../../../services/WhatsAppService.php';
$admin=require_admin();if($_SERVER['REQUEST_METHOD']!=='POST')fail('POST required',405);
$in=json_input();require_fields($in,['title','message']);$aud=$in['audience']??'all';$eventId=$in['event_id']??null;
if(!in_array($aud,['all','event_attendees'],true))fail('Invalid audience.',422);
$db=lm_db();$db->prepare('INSERT INTO announcements(title,message,event_id,audience,sent_via_email,created_by) VALUES(?,?,?,?,1,?)')->execute([$in['title'],$in['message'],$eventId,$aud,$admin['id']]);
if($aud==='event_attendees'&&$eventId){$s=$db->prepare('SELECT DISTINCT u.id,u.email,up.whatsapp_number FROM bookings b JOIN users u ON u.id=b.user_id LEFT JOIN user_profiles up ON up.user_id=u.id WHERE b.event_id=? AND b.status="confirmed"');$s->execute([$eventId]);}
else{$s=$db->query("SELECT u.id,u.email,up.whatsapp_number FROM users u LEFT JOIN user_profiles up ON up.user_id=u.id WHERE u.status='approved'");}
foreach($s->fetchAll() as $r){notify_user((int)$r['id'],$in['title'],$in['message'],'announcement');if($r['email'])EmailService::send($r['email'],$in['title'],nl2br(htmlspecialchars($in['message'])));if($r['whatsapp_number'])WhatsAppService::send('+91'.$r['whatsapp_number'],$in['title'].': '.$in['message']);}
log_activity('admin',$admin['id'],'announcement_created');respond(['message'=>'Announcement sent.'],201);

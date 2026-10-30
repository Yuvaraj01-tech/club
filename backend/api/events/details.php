<?php
require_once __DIR__ . '/../../core/bootstrap.php';$id=(int)($_GET['id']??0);if(!$id)fail('Event id required.',422);$db=lm_db();
$s=$db->prepare("SELECT * FROM events WHERE id=? AND status IN ('published','completed')");$s->execute([$id]);$e=$s->fetch();if(!$e)fail('Event not found.',404);
$ss=$db->prepare('SELECT * FROM event_slots WHERE event_id=? ORDER BY start_time');$ss->execute([$id]);
respond(['event'=>$e,'slots'=>$ss->fetchAll()]);

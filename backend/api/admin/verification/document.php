<?php
require_once __DIR__ . '/../../../core/bootstrap.php';require_admin();
$id=(int)($_GET['id']??0);if(!$id)fail('Document id required.',422);$db=lm_db();
$s=$db->prepare('SELECT doc_file_path FROM user_verifications WHERE id=?');$s->execute([$id]);$path=$s->fetchColumn();if(!$path)fail('Document not found.',404);
$file=realpath(__DIR__.'/../../../'.$path);$base=realpath(__DIR__.'/../../../uploads/verification_docs');
if(!$file||!$base||strpos($file,$base)!==0||!is_file($file))fail('File unavailable.',404);
$finfo=finfo_open(FILEINFO_MIME_TYPE);$mime=finfo_file($finfo,$file);finfo_close($finfo);
header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Content-Disposition: inline; filename="'.basename($file).'"');readfile($file);exit;

<?php
require_once __DIR__ . '/../../../core/bootstrap.php';

require_admin();

$db = lm_db();
$status = $_GET['status'] ?? null;

$sql = 'SELECT
            u.id,
            u.username,
            NULL AS email,
            u.approval_status AS status,
            NULL AS rejection_reason,
            u.created_at,
            p.full_name,
            p.mobile_number,
            p.whatsapp_same,
            p.gender,
            p.dob,
            p.marital_status
        FROM users u
        LEFT JOIN user_profiles p ON p.user_id = u.id';

$params = [];

if ($status) {
    $sql .= ' WHERE u.approval_status = ?';
    $params[] = $status;
}

$sql .= ' ORDER BY u.created_at DESC';

$s = $db->prepare($sql);
$s->execute($params);

respond(['users' => $s->fetchAll()]);
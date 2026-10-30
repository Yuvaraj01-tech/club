<?php

require_once __DIR__ . '/../../../core/bootstrap.php';

require_admin();

$db = lm_db();

$status = $_GET['status'] ?? 'pending';

if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
    fail('Invalid verification status.', 422);
}

$sql = '
    SELECT
        v.id AS verification_id,
        v.document_type AS doc_type,
        v.document_path AS doc_file_path,
        v.status,
        NULL AS admin_notes,
        v.created_at AS submitted_at,
        v.reviewed_at,

        u.id AS user_id,
        u.username,
        u.username AS email,
        u.approval_status AS user_status,
        NULL AS rejection_reason,

        p.full_name,
        p.mobile_number,
        p.whatsapp_same,
        p.whatsapp_number,
        p.gender,
        p.dob,
        p.marital_status,
        p.instagram_handle

    FROM verifications v

    JOIN users u
        ON u.id = v.user_id

    LEFT JOIN user_profiles p
        ON p.user_id = u.id

    WHERE v.status = ?

    ORDER BY v.created_at ASC
';

$s = $db->prepare($sql);

$s->execute([
    $status
]);

respond([
    'verifications' => $s->fetchAll()
]);
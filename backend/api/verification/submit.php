<?php

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../services/EmailService.php';

$user = require_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST required', 405);
}

$in = $_POST;

require_fields($in, ['doc_type']);

if (!in_array(
    $in['doc_type'],
    ['aadhaar', 'pan', 'voter_id', 'dl'],
    true
)) {
    fail('Invalid document type.', 422);
}

$doc = save_uploaded_file(
    'doc_file',
    'verification_docs',
    ['jpg', 'jpeg', 'png', 'pdf', 'webp']
);

if (!$doc) {
    fail('Please upload your identity document.', 422);
}

$db = lm_db();

/*
 * Convert frontend document names
 * to database document names.
 */
$documentType = $in['doc_type'];

if ($documentType === 'voter_id') {
    $documentType = 'voterid';
}

/*
 * Save verification record
 */
$db->prepare(
    'INSERT INTO verifications
    (
        user_id,
        document_type,
        document_path,
        status
    )
    VALUES (?, ?, ?, ?)'
)->execute([
    $user['id'],
    $documentType,
    $doc,
    'pending'
]);

/*
 * Update user approval status
 */
$db->prepare(
    'UPDATE users
     SET approval_status = ?
     WHERE id = ?'
)->execute([
    'pending',
    $user['id']
]);

start_user_session();

$_SESSION['user']['status'] = 'pending';

notify_user(
    $user['id'],
    'Profile submitted for review',
    'Your profile has been submitted and is under admin review. You will receive an email when it is approved or needs correction.',
    'system'
);

/*
 * Send review email
 */
$emailSent = false;

try {

    /*
     * Username is being used as the email
     * because users table has no separate email column.
     */
    $s = $db->prepare(
        'SELECT username FROM users WHERE id = ?'
    );

    $s->execute([$user['id']]);

    $email = $s->fetchColumn();

    if ($email) {

        $emailSent = EmailService::send(
            $email,
            'Hello Hobnobbers — Profile under review',
            'Your profile has been submitted and is under admin review. We will email you after admin review.'
        );
    }

} catch (Throwable $e) {

    error_log($e->getMessage());
}

log_activity(
    'user',
    $user['id'],
    'verification_submitted',
    [
        'doc_type' => $documentType
    ]
);

respond([
    'message' => 'Your profile has been submitted and is under admin review.',
    'email_sent' => $emailSent
]);
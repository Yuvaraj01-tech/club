<?php

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../services/EmailService.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST required', 405);
}

$in = json_input();

require_fields($in, [
    'verification_id',
    'decision'
]);

if (!in_array($in['decision'], ['approved', 'rejected'], true)) {
    fail('Invalid decision.', 422);
}

$note = trim($in['notes'] ?? '');

if ($in['decision'] === 'rejected' && $note === '') {
    fail('Rejection reason is required.', 422);
}

$db = lm_db();

/*
 * Get verification record and user.
 * Actual database tables:
 *   verifications
 *   users
 */
$s = $db->prepare(
    'SELECT
        v.id,
        v.user_id,
        v.document_type,
        v.document_path,
        v.status,
        u.username
     FROM verifications v
     JOIN users u
        ON u.id = v.user_id
     WHERE v.id = ?'
);

$s->execute([
    (int)$in['verification_id']
]);

$v = $s->fetch();

if (!$v) {
    fail('Verification record not found.', 404);
}

$userId = (int)$v['user_id'];

/*
 * Update verification status.
 */
$db->prepare(
    'UPDATE verifications
     SET
        status = ?,
        reviewed_by = ?,
        reviewed_at = NOW()
     WHERE id = ?'
)->execute([
    $in['decision'],
    $admin['id'],
    $v['id']
]);

/*
 * Update user's approval status.
 */
if ($in['decision'] === 'approved') {

    $db->prepare(
        'UPDATE users
         SET approval_status = ?
         WHERE id = ?'
    )->execute([
        'approved',
        $userId
    ]);

    $title = 'Profile Approved ✓';

    $msg =
        'Your Hello Hobnobbers profile has been approved. ' .
        'You can now sign in and book eligible events.';

} else {

    /*
     * The current database does not have a rejection_reason
     * column in users, so only the approval status is updated.
     */
    $db->prepare(
        'UPDATE users
         SET approval_status = ?
         WHERE id = ?'
    )->execute([
        'rejected',
        $userId
    ]);

    $title = 'Profile Rejected — Correction Required';

    $msg =
        'Please correct the following information and resubmit: ' .
        $note;
}

/*
 * Create user notification.
 */
notify_user(
    $userId,
    $title,
    $msg,
    'system'
);

/*
 * Send email.
 */
$emailSent = false;

try {

    $cfg = lm_config();

    $loginLink =
        rtrim(
            $cfg['app']['frontend_url'],
            '/'
        ) . '/signin.html';

    $emailBody =
        nl2br(
            htmlspecialchars($msg)
        );

    if ($in['decision'] === 'approved') {

        $emailBody .=
            '<br><br>' .
            '<b>Username:</b> ' .
            htmlspecialchars($v['username']) .
            '<br>' .
            '<b>Password:</b> Use the password you created during registration.' .
            '<br>' .
            '<b>Sign in:</b> ' .
            '<a href="' .
            htmlspecialchars($loginLink) .
            '">' .
            'Hello Hobnobbers Sign In' .
            '</a>';

    } else {

        $emailBody .=
            '<br><br>' .
            '<b>Sign in:</b> ' .
            '<a href="' .
            htmlspecialchars($loginLink) .
            '">' .
            'Hello Hobnobbers Sign In' .
            '</a>';
    }

    /*
     * username currently stores the user's email address.
     */
    $emailSent = EmailService::send(
        $v['username'],
        'Hello Hobnobbers — ' . $title,
        $emailBody
    );

} catch (Throwable $e) {

    error_log(
        'Verification review email error: ' .
        $e->getMessage()
    );
}

/*
 * Log admin action.
 */
log_activity(
    'admin',
    $admin['id'],
    'verification_reviewed',
    [
        'user_id' => $userId,
        'decision' => $in['decision']
    ]
);

respond([
    'message' =>
        'Verification ' .
        $in['decision'] .
        '.',

    'email_sent' => $emailSent
]);
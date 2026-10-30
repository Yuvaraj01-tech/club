<?php

require_once __DIR__ . '/../../core/bootstrap.php';

$user = require_user();
$db = lm_db();

/*
 * Get user account details
 */
$s = $db->prepare(
    'SELECT
        id,
        username,
        approval_status,
        created_at
     FROM users
     WHERE id = ?'
);

$s->execute([
    $user['id']
]);

$u = $s->fetch();

if (!$u) {
    fail('User account not found.', 404);
}

/*
 * Get profile details
 */
$p = $db->prepare(
    'SELECT *
     FROM user_profiles
     WHERE user_id = ?'
);

$p->execute([
    $user['id']
]);

$profile = $p->fetch() ?: null;

/*
 * Get latest verification
 */
$v = $db->prepare(
    'SELECT
        id,
        document_type,
        document_path,
        status,
        reviewed_by,
        reviewed_at,
        created_at
     FROM verifications
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 1'
);

$v->execute([
    $user['id']
]);

$verification = $v->fetch() ?: null;

/*
 * Use username as email because
 * users table has no separate email column.
 */
$u['email'] = $u['username'];

/*
 * Frontend expects "status"
 */
$u['status'] = $u['approval_status'];

start_user_session();

$_SESSION['user']['status'] = $u['status'];

respond([
    'user' => $u,
    'profile' => $profile,
    'verification' => $verification,
    'age' => $profile
        ? calculate_age($profile['dob'])
        : null
]);
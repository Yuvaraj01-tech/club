<?php

require_once __DIR__ . '/../../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST required', 405);
}

$in = json_input();

require_fields($in, [
    'email',
    'password'
]);

$email = strtolower(trim($in['email']));

$db = lm_db();

$s = $db->prepare(
    'SELECT
        id,
        username,
        password_hash,
        approval_status
     FROM users
     WHERE username = ?'
);

$s->execute([
    $email
]);

$u = $s->fetch();

if (
    !$u ||
    !password_verify(
        $in['password'],
        $u['password_hash']
    )
) {
    fail('Invalid email or password.', 401);
}

start_user_session();

$_SESSION['user'] = [
    'id'       => (int)$u['id'],
    'username' => $u['username'],
    'email'    => $u['username'],
    'status'   => $u['approval_status']
];

log_activity(
    'user',
    (int)$u['id'],
    'login'
);

respond([
    'user' => $_SESSION['user']
]);

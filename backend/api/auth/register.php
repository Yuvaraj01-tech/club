<?php

require_once __DIR__ . '/../../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST required', 405);
}

$in = json_input();

require_fields($in, [
    'email',
    'password',
    'confirm_password'
]);

$email = strtolower(trim($in['email']));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Enter a valid email address.', 422);
}

$password = (string)$in['password'];

if (
    strlen($password) < 8 ||
    !preg_match('/[A-Za-z]/', $password) ||
    !preg_match('/[0-9]/', $password)
) {
    fail(
        'Password must be at least 8 characters and contain a letter and a number.',
        422
    );
}

if ($password !== $in['confirm_password']) {
    fail('Passwords do not match.', 422);
}

$db = lm_db();

/*
 * Email address is used as the username.
 * The users table does not contain a separate email column.
 */
$s = $db->prepare(
    'SELECT id FROM users WHERE username = ?'
);

$s->execute([$email]);

if ($s->fetch()) {
    fail(
        'An account already exists for this email.',
        409
    );
}

/*
 * Create the user account.
 *
 * users table:
 * username
 * password_hash
 * role
 * approval_status
 */
$st = $db->prepare(
    'INSERT INTO users
        (username, password_hash, role, approval_status)
     VALUES
        (?, ?, ?, ?)'
);

$st->execute([
    $email,
    password_hash($password, PASSWORD_BCRYPT),
    'user',
    'pending'
]);

$id = (int)$db->lastInsertId();

/*
 * Start user session.
 */
start_user_session();

$_SESSION['user'] = [
    'id'       => $id,
    'username' => $email,
    'email'    => $email,
    'status'   => 'pending'
];

/*
 * Activity log.
 */
log_activity(
    'user',
    $id,
    'register'
);

/*
 * Welcome notification.
 */
notify_user(
    $id,
    'Welcome to Hello Hobnobbers',
    'Your account was created. Complete your profile and identity verification.',
    'system'
);

respond(
    [
        'user' => $_SESSION['user']
    ],
    201
);
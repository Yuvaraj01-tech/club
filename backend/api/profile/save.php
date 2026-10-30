<?php

require_once __DIR__ . '/../../core/bootstrap.php';

$user = require_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('POST required', 405);
}

$in = $_POST;

require_fields($in, [
    'full_name',
    'mobile_number',
    'gender',
    'dob',
    'marital_status'
]);

if (!is_valid_mobile(trim($in['mobile_number']))) {
    fail('Mobile number must be exactly 10 digits.', 422);
}

if (!in_array($in['gender'], ['male', 'female', 'other'], true)) {
    fail('Invalid gender.', 422);
}

if (!in_array(
    $in['marital_status'],
    ['never_married', 'separated', 'divorced', 'widowed'],
    true
)) {
    fail('Invalid marital status.', 422);
}

try {
    $age = calculate_age($in['dob']);
} catch (Throwable $e) {
    fail('Invalid date of birth.', 422);
}

if ($age < 18) {
    fail('You must be at least 18 years old.', 422);
}

$same = !empty($in['whatsapp_same']) ? 1 : 0;

$wa = $same
    ? trim($in['mobile_number'])
    : trim($in['whatsapp_number'] ?? '');

if (!$same && !is_valid_mobile($wa)) {
    fail('WhatsApp number must be exactly 10 digits.', 422);
}

$db = lm_db();

$check = $db->prepare(
    'SELECT id FROM user_profiles WHERE user_id = ?'
);

$check->execute([$user['id']]);

if ($check->fetch()) {

    $db->prepare(
        'UPDATE user_profiles
         SET
            full_name = ?,
            mobile_number = ?,
            whatsapp_same = ?,
            whatsapp_number = ?,
            gender = ?,
            dob = ?,
            marital_status = ?,
            instagram_handle = ?
         WHERE user_id = ?'
    )->execute([
        trim($in['full_name']),
        trim($in['mobile_number']),
        $same,
        $wa,
        $in['gender'],
        $in['dob'],
        $in['marital_status'],
        trim($in['instagram_handle'] ?? '') ?: null,
        $user['id']
    ]);

} else {

    $db->prepare(
        'INSERT INTO user_profiles
        (
            user_id,
            full_name,
            mobile_number,
            whatsapp_same,
            whatsapp_number,
            gender,
            dob,
            marital_status,
            instagram_handle
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $user['id'],
        trim($in['full_name']),
        trim($in['mobile_number']),
        $same,
        $wa,
        $in['gender'],
        $in['dob'],
        $in['marital_status'],
        trim($in['instagram_handle'] ?? '') ?: null
    ]);
}

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

log_activity(
    'user',
    $user['id'],
    'profile_saved'
);

respond([
    'message' => 'Profile saved.',
    'age' => $age
]);
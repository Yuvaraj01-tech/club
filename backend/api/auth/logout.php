<?php
require_once __DIR__ . '/../../core/bootstrap.php';
start_user_session();
$_SESSION = [];
session_destroy();
respond(['message' => 'Logged out.']);

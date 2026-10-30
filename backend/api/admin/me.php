<?php
require_once __DIR__ . '/../../core/bootstrap.php';
$admin = require_admin();
respond(['admin' => $admin]);

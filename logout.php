<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$_SESSION = [];
session_regenerate_id(true);
set_flash_message('info', 'You have been successfully logged out.');
redirect('/login.php');

<?php
declare(strict_types=1);

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/init.php';
}

$pageTitle = $pageTitle ?? APP_NAME;
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="app-container">
<?php 
if (is_logged_in()) {
    require_once __DIR__ . '/nav.php'; 
}
?>
<div class="main-wrapper">
<?php if (is_logged_in()): ?>
    <header class="top-header">
        <button type="button" class="mobile-toggle" id="mobileSidebarToggle" aria-label="Toggle navigation">
            &#9776;
        </button>
        <div class="header-title-area">
            <strong><?= e(APP_NAME) ?></strong>
        </div>
        <div class="user-profile">
            <div class="user-info">
                <div class="user-name"><?= e($currentUser['full_name']) ?></div>
                <div class="user-role"><?= e($currentUser['role']) ?></div>
            </div>
            <a href="<?= url('logout.php') ?>" class="btn btn-secondary btn-sm" title="Sign out">
                Logout
            </a>
        </div>
    </header>
<?php endif; ?>

<main class="content-area">
<?php
// Render flash messages if available
$flashMessages = get_flash_messages();
foreach ($flashMessages as $msg): ?>
    <div class="alert alert-<?= e($msg['type']) ?>">
        <span><?= e($message = $msg['message']) ?></span>
    </div>
<?php endforeach; ?>

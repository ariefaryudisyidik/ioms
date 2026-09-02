<?php
/** @var array{id:int,name:string,email:string,role:string}|null $auth_user */
$auth_user = $auth_user ?? null;
$pageTitle = $pageTitle ?? 'IOMS';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - IOMS</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <header class="app-header">
        <button type="button" class="hamburger" aria-label="Toggle navigation">&#9776;</button>
        <div class="brand">IOMS</div>
        <?php if ($auth_user): ?>
            <div class="user-box">
                <span><?= e($auth_user['name']) ?> &middot; <?= e($auth_user['role']) ?></span>
                <form method="post" action="/logout" style="margin:0;">
                    <button type="submit" class="btn btn-secondary btn-sm">Logout</button>
                </form>
            </div>
        <?php endif; ?>
    </header>
    <div class="app-body">
        <?php if ($auth_user): ?>
            <?php include __DIR__ . '/nav.php'; ?>
        <?php endif; ?>
        <main class="app-main">
            <?php include __DIR__ . '/flash.php'; ?>

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/helpers.php';
require_once dirname(__DIR__, 2) . '/app/Auth.php';

if (!is_installed()) {
    redirect('/install.php');
}

Auth::requireLogin();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="/admin/dashboard.php">MediaHost Dashboard</a>
        <div class="navbar-nav">
            <a class="nav-link" href="/admin/dashboard.php">Dashboard</a>
            <a class="nav-link" href="/admin/servers.php">Servers</a>
            <a class="nav-link" href="/admin/updates.php">Updates</a>
            <a class="nav-link" href="/admin/logout.php">Logout</a>
        </div>
    </div>
</nav>
<div class="container">

<?php
declare(strict_types=1);

$rootDir = dirname(dirname(__DIR__));

require_once $rootDir . '/config/config.php';
require_once $rootDir . '/includes/session_helper.php';
require_once $rootDir . '/includes/functions.php';
require_once $rootDir . '/includes/Database.php';

require_admin_login();

if (!isset($db) || !($db instanceof Database)) {
    $db = new Database();
}

if (!isset($page_title) || $page_title === '') {
    $page_title = isset($pageTitle) && $pageTitle !== '' ? $pageTitle : 'Admin Panel';
}

if (!isset($pageTitle) || $pageTitle === '') {
    $pageTitle = $page_title;
}

require __DIR__ . '/includes/header.php';

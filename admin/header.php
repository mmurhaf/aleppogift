<?php
$root_dir = dirname(dirname(__DIR__));

require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/functions.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

if (!isset($db) || !($db instanceof Database)) {
    $db = new Database();
}

if (!isset($page_title) && isset($pageTitle)) {
    $page_title = $pageTitle;
}

require __DIR__ . '/includes/header.php';

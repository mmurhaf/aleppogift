<?php
require_once __DIR__ . '/../includes/bootstrap.php';
unset($_SESSION['customer_id'], $_SESSION['customer_email'], $_SESSION['customer_name'], $_SESSION['guest_wishlist']);
header('Location: ' . store_url('login.php'));
exit;
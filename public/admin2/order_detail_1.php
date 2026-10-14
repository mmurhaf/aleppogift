<?php
$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$target = 'order_detail.php';
if ($order_id > 0) {
    $target .= '?id=' . $order_id;
}

header('Location: ' . $target, true, 302);
exit;
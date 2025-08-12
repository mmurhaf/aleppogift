<?php
// admin/index.php

if (!headers_sent()) {
    header("Location: dashboard.php");
    exit;
} else {
    echo '<script>window.location.href = "dashboard.php";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=dashboard.php"></noscript>';
    exit;
}

<?php
/**
 * Brand Edit - Alternative naming convention redirect
 * Redirects to edit_brand.php for consistency
 */

// Get the brand ID from the request
$brand_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Redirect to the standard edit_brand.php file
if ($brand_id > 0) {
    header("Location: edit_brand.php?id=" . $brand_id);
} else {
    header("Location: brands.php");
}
exit;
?>

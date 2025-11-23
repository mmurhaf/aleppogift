<?php
/**
 * Category Edit - Alternative naming convention redirect
 * Redirects to edit_category.php for consistency
 */

// Get the category ID from the request
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Redirect to the standard edit_category.php file
if ($category_id > 0) {
    header("Location: edit_category.php?id=" . $category_id);
} else {
    header("Location: categories.php");
}
exit;
?>

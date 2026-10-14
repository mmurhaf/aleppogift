<?php
$root_dir = dirname(dirname(__DIR__));
require_once $root_dir . '/includes/session_helper.php';
require_once $root_dir . '/config/config.php';
require_once $root_dir . '/includes/generate_invoice_pdf.php';
require_admin_login();
$orderId = (int)($_GET['id'] ?? 0);
$generator = new PDFInvoiceGenerator();
$path = $generator->generatePackingSlipPDF($orderId);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="packing-' . $orderId . '.pdf"');
readfile($path);
exit;
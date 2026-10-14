<?php
// File: admin/export_products_pdf.php
// Works both locally (public_html/public/admin/) and on production (public_html/admin/)
$root_dir = dirname(__DIR__);
if (!file_exists($root_dir . '/config/config.php')) {
  $root_dir = dirname(dirname(__DIR__));
}
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/Database.php');
require_once($root_dir . '/vendor/autoload.php');

require_admin_login();

use Dompdf\Dompdf;
use Dompdf\Options;

$db = new Database();

// Get filter parameters
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$brand_filter    = isset($_GET['brand'])    ? (int)$_GET['brand']    : 0;
$search_query    = isset($_GET['search'])   ? trim($_GET['search'])  : '';

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = :category_id";
    $params['category_id'] = $category_filter;
}
if ($brand_filter > 0) {
    $where_conditions[] = "p.brand_id = :brand_id";
    $params['brand_id'] = $brand_filter;
}
if (!empty($search_query)) {
    $where_conditions[] = "(p.name_en LIKE :search OR p.name_ar LIKE :search OR p.sku LIKE :search OR p.id LIKE :search)";
    $params['search'] = '%' . $search_query . '%';
}

$where_clause = empty($where_conditions) ? '' : ' WHERE ' . implode(' AND ', $where_conditions);

// Fetch ALL matching products (no pagination limit for export)
$products = $db->query("
    SELECT p.id, p.name_en, p.sku, p.price, p.stock, p.weight, p.status, p.featured,
           COALESCE(c.name_en, 'Uncategorized') AS category_name,
           COALESCE(b.name_en, b.name_ar, '') AS brand_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    {$where_clause}
    ORDER BY p.id DESC
", $params)->fetchAll(PDO::FETCH_ASSOC);

// Resolve category/brand label for the PDF title
$filter_label_parts = [];
if ($category_filter > 0) {
    $cat = $db->query("SELECT COALESCE(name_en, name_ar, CONCAT('ID #', id)) AS n FROM categories WHERE id = :id", ['id' => $category_filter])->fetch(PDO::FETCH_ASSOC);
    if ($cat) $filter_label_parts[] = 'Category: ' . htmlspecialchars($cat['n']);
}
if ($brand_filter > 0) {
    $br = $db->query("SELECT COALESCE(name_en, name_ar, CONCAT('ID #', id)) AS n FROM brands WHERE id = :id", ['id' => $brand_filter])->fetch(PDO::FETCH_ASSOC);
    if ($br) $filter_label_parts[] = 'Brand: ' . htmlspecialchars($br['n']);
}
if (!empty($search_query)) {
    $filter_label_parts[] = 'Search: &ldquo;' . htmlspecialchars($search_query) . '&rdquo;';
}
$filter_label = empty($filter_label_parts) ? 'All Products' : implode(' &nbsp;|&nbsp; ', $filter_label_parts);

$generated_at = date('d M Y H:i');
$total = count($products);

// Build rows
$rows_html = '';
foreach ($products as $i => $p) {
    $row_bg = ($i % 2 === 0) ? '#ffffff' : '#f7f9fc';
    $status  = $p['status']   ? '<span style="color:#1a7a4a;font-weight:700;">Active</span>'   : '<span style="color:#888;">Inactive</span>';
    $featured = $p['featured'] ? '<span style="color:#b8860b;font-weight:700;">&#9733; Yes</span>' : 'No';
    $rows_html .= sprintf(
        '<tr style="background:%s;">
            <td style="text-align:center;color:#888;font-size:11px;">%d</td>
            <td>%s</td>
            <td style="font-family:monospace;font-size:11px;">%s</td>
            <td>%s</td>
            <td>%s</td>
            <td style="text-align:right;">AED&nbsp;%s</td>
            <td style="text-align:center;">%s</td>
            <td style="text-align:center;font-size:11px;">%s&nbsp;kg</td>
            <td style="text-align:center;">%s</td>
            <td style="text-align:center;">%s</td>
        </tr>',
        $row_bg,
        $i + 1,
        htmlspecialchars(substr($p['name_en'], 0, 50)) . (strlen($p['name_en']) > 50 ? '…' : ''),
        htmlspecialchars($p['sku']),
        htmlspecialchars($p['category_name']),
        htmlspecialchars($p['brand_name']),
        number_format($p['price'], 2),
        $p['stock'],
        number_format($p['weight'] ?? 0, 2),
        $status,
        $featured
    );
}

if (empty($products)) {
    $rows_html = '<tr><td colspan="10" style="text-align:center;padding:30px;color:#888;">No products found.</td></tr>';
}

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body      { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #2e3338; margin: 0; padding: 0; }
  .header   { background: linear-gradient(135deg, #4e73df 0%, #3662bf 100%); color: #fff; padding: 18px 24px; margin-bottom: 16px; border-radius: 4px; }
  .header h1 { margin: 0 0 4px 0; font-size: 20px; font-weight: 700; }
  .header p  { margin: 0; font-size: 11px; opacity: 0.85; }
  .meta     { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 11px; color: #666; padding: 0 2px; }
  table     { width: 100%; border-collapse: collapse; font-size: 11px; }
  thead tr  { background: #4e73df; color: #fff; }
  thead th  { padding: 8px 6px; text-align: left; font-weight: 700; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
  tbody td  { padding: 7px 6px; border-bottom: 1px solid #e3e6f0; vertical-align: middle; }
  .footer   { margin-top: 20px; font-size: 10px; color: #aaa; text-align: center; border-top: 1px solid #e3e6f0; padding-top: 10px; }
</style>
</head>
<body>
  <div class="header">
    <h1>&#128230; Product List &mdash; AleppoGift</h1>
    <p>Filter: {$filter_label}</p>
  </div>
  <div class="meta">
    <span>Total: <strong>{$total}</strong> product(s)</span>
    <span>Generated: {$generated_at}</span>
  </div>
  <table>
    <thead>
      <tr>
        <th style="width:28px;">#</th>
        <th>Product Name</th>
        <th style="width:90px;">SKU</th>
        <th style="width:90px;">Category</th>
        <th style="width:70px;">Brand</th>
        <th style="width:72px;text-align:right;">Price</th>
        <th style="width:42px;text-align:center;">Stock</th>
        <th style="width:48px;text-align:center;">Weight</th>
        <th style="width:52px;text-align:center;">Status</th>
        <th style="width:42px;text-align:center;">Feat.</th>
      </tr>
    </thead>
    <tbody>
      {$rows_html}
    </tbody>
  </table>
  <div class="footer">AleppoGift Admin &bull; {$generated_at} &bull; Confidential</div>
</body>
</html>
HTML;

// Generate PDF with Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'products_' . date('Ymd_His') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');

echo $dompdf->output();
exit;

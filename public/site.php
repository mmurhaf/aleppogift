<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = t('pages.site.meta_title');
$pageKicker = 'AleppoGift';
$pageHeading = t('footer.sitemap');
$pageLead = t('pages.site.lead');
require __DIR__ . '/../includes/visitor_open.php';
?>
<article class="prose-panel">
    <ul>
        <li><a href="index.php"><?= te('home') ?></a></li>
        <li><a href="products.php"><?= te('all_products') ?></a></li>
        <li><a href="about.php"><?= te('about') ?></a></li>
        <li><a href="contact.php"><?= te('contact') ?></a></li>
        <li><a href="cart.php"><?= te('cart') ?></a></li>
        <li><a href="checkout.php"><?= te('checkout') ?></a></li>
        <li><a href="shipping.php"><?= te('shipping') ?></a></li>
        <li><a href="testimonials.php"><?= te('pages.site.reviews') ?></a></li>
        <li><a href="customer_gallery.php"><?= te('pages.site.gallery') ?></a></li>
        <li><a href="faq.php"><?= te('footer.faqs') ?></a></li>
        <li><a href="returns.php"><?= te('footer.returns') ?></a></li>
        <li><a href="privacy_policy.php"><?= te('footer.privacy') ?></a></li>
        <li><a href="terms_of_service.php"><?= te('footer.terms') ?></a></li>
        <li><a href="sitemap.xml"><?= te('pages.site.xml_sitemap') ?></a></li>
    </ul>
</article>
<?php require __DIR__ . '/../includes/visitor_close.php'; ?>

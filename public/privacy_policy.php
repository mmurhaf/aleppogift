<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = t('pages.privacy.meta_title');
$pageKicker = t('pages.privacy.kicker');
$pageHeading = t('footer.privacy');
$pageLead = t('pages.privacy.lead');
$pageDescription = t('pages.privacy.meta_description');
require __DIR__ . '/../includes/visitor_open.php';
?>
<article class="prose-panel">
    <h2><?= te('pages.privacy.s1_heading') ?></h2>
    <p><?= te('pages.privacy.s1_text') ?></p>
    <h2><?= te('pages.privacy.s2_heading') ?></h2>
    <h3><?= te('pages.privacy.identity_heading') ?></h3>
    <p><?= te('pages.privacy.identity_text') ?></p>
    <h3><?= te('pages.privacy.contact_heading') ?></h3>
    <p><?= te('pages.privacy.contact_text') ?></p>
    <h3><?= te('pages.privacy.technical_heading') ?></h3>
    <p><?= te('pages.privacy.technical_text') ?></p>
    <h2><?= te('pages.privacy.s3_heading') ?></h2>
    <ul>
        <li><?= te('pages.privacy.use_li1') ?></li>
        <li><?= te('pages.privacy.use_li2') ?></li>
        <li><?= te('pages.privacy.use_li3') ?></li>
        <li><?= te('pages.privacy.use_li4') ?></li>
        <li><?= te('pages.privacy.use_li5') ?></li>
    </ul>
    <h2><?= te('pages.privacy.s4_heading') ?></h2>
    <p><?= te('pages.privacy.s4_text') ?></p>
    <h2><?= te('pages.privacy.s5_heading') ?></h2>
    <ul>
        <li><?= te('pages.privacy.rights_li1') ?></li>
        <li><?= te('pages.privacy.rights_li2') ?></li>
        <li><?= te('pages.privacy.rights_li3') ?></li>
        <li><?= te('pages.privacy.rights_li4') ?></li>
        <li><?= te('pages.privacy.rights_li5') ?></li>
    </ul>
    <h2><?= te('pages.privacy.s6_heading') ?></h2>
    <p><?= t('pages.privacy.s6_text', ['email' => '<a href="mailto:info@aleppogift.com">info@aleppogift.com</a>', 'phone' => '<a href="tel:+971561125320">+971 56 112 5320</a>']) ?></p>
    <h2><?= te('pages.privacy.s7_heading') ?></h2>
    <p><?= te('pages.privacy.s7_text') ?></p>
</article>
<?php require __DIR__ . '/../includes/visitor_close.php'; ?>

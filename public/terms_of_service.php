<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = t('pages.terms.meta_title');
$pageKicker = t('pages.terms.kicker');
$pageHeading = t('footer.terms');
$pageLead = t('pages.terms.lead');
$pageDescription = t('pages.terms.meta_description');
require __DIR__ . '/../includes/visitor_open.php';
?>
<article class="prose-panel legal-copy">
    <div class="highlight">
        <p class="mb-0"><?= te('pages.terms.agree') ?></p>
    </div>
    <h2><?= te('pages.terms.s1_heading') ?></h2>
    <p><?= t('pages.terms.s1_text') ?></p>
    <h2><?= te('pages.terms.s2_heading') ?></h2>
    <ul>
        <li><?= t('pages.terms.s2_li1') ?></li>
        <li><?= t('pages.terms.s2_li2') ?></li>
        <li><?= te('pages.terms.s2_li3') ?></li>
        <li><?= te('pages.terms.s2_li4') ?></li>
    </ul>
    <h2><?= te('pages.terms.s3_heading') ?></h2>
    <ul>
        <li><?= t('pages.terms.s3_li1') ?></li>
        <li><?= t('pages.terms.s3_li2') ?></li>
        <li><?= te('pages.terms.s3_li3') ?></li>
    </ul>
    <h2><?= te('pages.terms.s4_heading') ?></h2>
    <h3><?= te('pages.terms.uae_heading') ?></h3>
    <ul>
        <li><?= t('pages.terms.uae_li1') ?></li>
        <li><?= t('pages.terms.uae_li2') ?></li>
    </ul>
    <h3><?= te('pages.terms.intl_heading') ?></h3>
    <ul>
        <li><?= te('pages.terms.intl_li1') ?></li>
        <li><?= t('pages.terms.intl_li2') ?></li>
    </ul>
    <h2><?= te('pages.terms.s5_heading') ?></h2>
    <ul>
        <li><?= t('pages.terms.s5_li1') ?></li>
        <li><?= t('pages.terms.s5_li2') ?></li>
    </ul>
    <h2><?= te('pages.terms.s6_heading') ?></h2>
    <ul>
        <li><?= te('pages.terms.s6_li1') ?></li>
        <li><?= te('pages.terms.s6_li2') ?></li>
    </ul>
    <h2><?= te('pages.terms.s7_heading') ?></h2>
    <p><?= te('pages.terms.s7_text') ?></p>
    <div class="contact-info">
        <h2><?= te('pages.terms.s8_heading') ?></h2>
        <div class="contact-methods">
            <div class="contact-method"><strong><?= te('pages.terms.phone_label') ?></strong><a href="tel:+971561125320">+971 56 112 5320</a></div>
            <div class="contact-method"><strong><?= te('pages.terms.email_label') ?></strong><a href="mailto:info@aleppogift.com">info@aleppogift.com</a></div>
            <div class="contact-method"><strong><?= te('pages.terms.address_label') ?></strong><span>17 Ettihad Road, Deira, Dubai, UAE</span></div>
        </div>
    </div>
    <p class="text-muted mt-4 mb-0"><strong><?= te('pages.terms.last_updated_label') ?></strong> <?= te('pages.terms.last_updated_date') ?></p>
</article>
<?php require __DIR__ . '/../includes/visitor_close.php'; ?>

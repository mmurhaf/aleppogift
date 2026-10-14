<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = t('pages.returns.meta_title');
$pageKicker = t('footer.support');
$pageHeading = t('pages.returns.heading');
$pageLead = t('pages.returns.lead');
$pageDescription = t('pages.returns.meta_description');
require __DIR__ . '/../includes/visitor_open.php';
?>
<article class="prose-panel">
    <h2><?= te('pages.returns.policy_heading') ?></h2>
    <p><?= te('pages.returns.policy_text') ?></p>
    <div class="highlight-box"><strong><?= te('pages.returns.important_label') ?></strong> <?= te('pages.returns.important_text') ?></div>
    <h3><?= te('pages.returns.eligible_heading') ?></h3>
    <ul>
        <li><?= te('pages.returns.eligible_li1') ?></li>
        <li><?= te('pages.returns.eligible_li2') ?></li>
        <li><?= te('pages.returns.eligible_li3') ?></li>
        <li><?= te('pages.returns.eligible_li4') ?></li>
    </ul>
    <h3><?= te('pages.returns.non_heading') ?></h3>
    <ul>
        <li><?= te('pages.returns.non_li1') ?></li>
        <li><?= te('pages.returns.non_li2') ?></li>
        <li><?= te('pages.returns.non_li3') ?></li>
        <li><?= te('pages.returns.non_li4') ?></li>
    </ul>
</article>
<article class="prose-panel">
    <h2><?= te('pages.returns.how_heading') ?></h2>
    <div class="process-steps">
        <div class="process-step">
            <h3><?= te('pages.returns.step1_title') ?></h3>
            <p><?= t('pages.returns.step1_text', ['email' => '<a href="mailto:info@aleppogift.com">info@aleppogift.com</a>', 'phone' => '<a href="https://wa.me/971561125320">+971 56 112 5320</a>']) ?></p>
        </div>
        <div class="process-step">
            <h3><?= te('pages.returns.step2_title') ?></h3>
            <p><?= te('pages.returns.step2_text') ?></p>
        </div>
        <div class="process-step">
            <h3><?= te('pages.returns.step3_title') ?></h3>
            <p><?= te('pages.returns.step3_text') ?></p>
        </div>
        <div class="process-step">
            <h3><?= te('pages.returns.step4_title') ?></h3>
            <p><?= te('pages.returns.step4_text') ?></p>
        </div>
        <div class="process-step">
            <h3><?= te('pages.returns.step5_title') ?></h3>
            <p><?= te('pages.returns.step5_text') ?></p>
        </div>
    </div>
</article>
<article class="prose-panel">
    <h2><?= te('pages.returns.refunds_heading') ?></h2>
    <p><?= te('pages.returns.refunds_p1') ?></p>
    <p><?= te('pages.returns.refunds_p2') ?></p>
    <p><?= te('pages.returns.refunds_p3') ?></p>
    <div class="info-box"><?= te('pages.returns.intl_note') ?></div>
</article>
<?php require __DIR__ . '/../includes/visitor_close.php'; ?>

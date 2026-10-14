<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = t('pages.faq.meta_title');
$pageKicker = t('pages.faq.kicker');
$pageHeading = t('pages.faq.heading');
$pageLead = t('pages.faq.lead');
$pageDescription = t('pages.faq.meta_description');
require __DIR__ . '/../includes/visitor_open.php';
?>
<div class="accordion" id="faqAccordion">
    <div class="accordion-item faq-item">
        <h2 class="accordion-header">
            <button class="accordion-button faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq1"><?= te('pages.faq.q1') ?></button>
        </h2>
        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
            <div class="accordion-body"><?= te('pages.faq.a1') ?></div>
        </div>
    </div>
    <div class="accordion-item faq-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq2"><?= te('pages.faq.q2') ?></button>
        </h2>
        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body"><?= t('pages.faq.a2') ?></div>
        </div>
    </div>
    <div class="accordion-item faq-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq3"><?= te('pages.faq.q3') ?></button>
        </h2>
        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body"><?= te('pages.faq.a3') ?></div>
        </div>
    </div>
    <div class="accordion-item faq-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq4"><?= te('pages.faq.q4') ?></button>
        </h2>
        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
                <ul class="mb-0">
                    <li><?= te('pages.faq.a4_uae') ?></li>
                    <li><?= te('pages.faq.a4_gcc') ?></li>
                    <li><?= te('pages.faq.a4_intl') ?></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="accordion-item faq-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq5"><?= te('pages.faq.q5') ?></button>
        </h2>
        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body"><?= te('pages.faq.a5') ?></div>
        </div>
    </div>
    <div class="accordion-item faq-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq6"><?= te('pages.faq.q6') ?></button>
        </h2>
        <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body"><?= t('pages.faq.a6') ?></div>
        </div>
    </div>
    <div class="accordion-item faq-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq7"><?= te('pages.faq.q7') ?></button>
        </h2>
        <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body"><?= te('pages.faq.a7') ?></div>
        </div>
    </div>
    <div class="accordion-item faq-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq8"><?= te('pages.faq.q8') ?></button>
        </h2>
        <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body"><?= t('pages.faq.a8') ?></div>
        </div>
    </div>
    <div class="accordion-item faq-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq9"><?= te('pages.faq.q9') ?></button>
        </h2>
        <div id="faq9" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
            <div class="accordion-body">
                <ul class="mb-0">
                    <li><?= te('pages.faq.a9_email') ?> <a href="mailto:info@aleppogift.com">info@aleppogift.com</a></li>
                    <li><?= te('pages.faq.a9_phone') ?> <a href="https://wa.me/971561125320">+971 56 112 5320</a></li>
                    <li><a href="contact.php"><?= te('pages.faq.a9_form') ?></a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/visitor_close.php'; ?>

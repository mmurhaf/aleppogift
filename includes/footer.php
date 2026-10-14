<footer class="tg-footer">
    <section class="tg-footer-newsletter" aria-label="<?= te('footer.newsletter') ?>">
        <h2><?= te('theme.newsletter_title') ?></h2>
        <p><?= te('theme.newsletter_text') ?></p>
        <?php if (isset($_GET['subscribed']) && $_GET['subscribed'] === '1'): ?>
            <p class="footer-subscribe-status" role="status"><?= te('footer.subscribed') ?></p>
        <?php endif; ?>
        <form action="<?= htmlspecialchars(store_url('ajax/subscribe_newsletter.php')) ?>" method="post" class="tg-newsletter-form" id="footer-subscribe-form">
            <label for="footer-email" class="visually-hidden"><?= te('footer.email_address') ?></label>
            <input id="footer-email" type="email" name="email" placeholder="<?= te('footer.email_address') ?>" required>
            <button type="submit" class="tg-btn"><?= te('footer.subscribe') ?></button>
        </form>
        <p class="footer-subscribe-status" id="footer-subscribe-status" role="status" hidden></p>
    </section>

    <div class="tg-footer-columns">
        <details class="tg-footer-col" open>
            <summary><?= te('theme.footer_about') ?></summary>
            <ul>
                <li><a href="<?= htmlspecialchars(store_url('about.php')) ?>"><?= te('theme.about_store') ?></a></li>
                <li><a href="<?= htmlspecialchars(store_url('testimonials.php')) ?>"><?= te('theme.testimonials') ?></a></li>
                <li><a href="<?= htmlspecialchars(store_url('customer_gallery.php')) ?>"><?= te('catalog.customer_gallery') ?></a></li>
                <li><a href="<?= htmlspecialchars(store_url('site.php')) ?>"><?= te('footer.sitemap') ?></a></li>
            </ul>
        </details>
        <details class="tg-footer-col" open>
            <summary><?= te('theme.footer_customer_care') ?></summary>
            <ul>
                <li><a href="<?= htmlspecialchars(store_url('track_order.php')) ?>"><?= te('track') ?></a></li>
                <li><a href="<?= htmlspecialchars(store_url('shipping.php')) ?>"><?= te('shipping') ?></a></li>
                <li><a href="<?= htmlspecialchars(store_url('returns.php')) ?>"><?= te('footer.returns') ?></a></li>
                <li><a href="<?= htmlspecialchars(store_url('faq.php')) ?>"><?= te('footer.faqs') ?></a></li>
                <li><a href="<?= htmlspecialchars(store_url('contact.php')) ?>"><?= te('footer.contact_us') ?></a></li>
            </ul>
        </details>
        <details class="tg-footer-col" open>
            <summary><?= te('theme.footer_services') ?></summary>
            <ul>
                <li><?= te('footer.cod') ?></li>
                <li><?= te('catalog.promise_delivery') ?></li>
                <li><?= te('catalog.promise_currency') ?></li>
                <li class="tg-footer-pay" aria-label="<?= te('footer.payment_methods') ?>">
                    <i class="fab fa-cc-visa" title="Visa" aria-hidden="true"></i>
                    <i class="fab fa-cc-mastercard" title="Mastercard" aria-hidden="true"></i>
                    <i class="fas fa-money-bill-wave" title="<?= te('footer.cod') ?>" aria-hidden="true"></i>
                </li>
            </ul>
        </details>
        <details class="tg-footer-col" open>
            <summary><?= te('footer.contact_us') ?></summary>
            <ul>
                <li><a href="https://wa.me/971561125320" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp</a></li>
                <li><a href="tel:+971561125320" dir="ltr">+971 56 112 5320</a></li>
                <li><a href="https://wa.me/971561125320?text=<?= rawurlencode(t('footer.whatsapp_message')) ?>" target="_blank" rel="noopener noreferrer"><?= te('footer.ask_products') ?></a></li>
                <li><a href="<?= htmlspecialchars(store_url('contact.php')) ?>"><?= te('footer.contact_form') ?></a></li>
            </ul>
        </details>
    </div>

    <div class="tg-footer-bottom">
        <a href="<?= htmlspecialchars(store_url()) ?>" class="tg-footer-logo" aria-label="<?= te('footer.go_home') ?>">
            <img src="<?= htmlspecialchars(store_url('uploads/logo.png')) ?>" alt="<?= te('header.logo_alt') ?>" width="186" height="72" loading="lazy">
        </a>
        <div class="tg-footer-social" aria-label="<?= te('footer.social') ?>">
            <a href="https://www.facebook.com/dubai.gift.2025/" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fab fa-facebook-f" aria-hidden="true"></i></a>
            <a href="https://www.instagram.com/best_deal_in_uae/" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a>
            <a href="https://www.tiktok.com/@aleppogift" target="_blank" rel="noopener noreferrer" aria-label="TikTok"><i class="fab fa-tiktok" aria-hidden="true"></i></a>
            <a href="https://www.pinterest.com/Krmeencom" target="_blank" rel="noopener noreferrer" aria-label="Pinterest"><i class="fab fa-pinterest-p" aria-hidden="true"></i></a>
        </div>
        <p class="tg-footer-legal">
            &copy; <?= date('Y'); ?> <?= te('footer.rights') ?>
            <a href="<?= htmlspecialchars(store_url('terms_of_service.php')) ?>"><?= te('footer.terms') ?></a>
            <a href="<?= htmlspecialchars(store_url('privacy_policy.php')) ?>"><?= te('footer.privacy') ?></a>
        </p>
    </div>
</footer>
<script src="<?= htmlspecialchars(store_url('assets/js/storefront.js')) ?>?v=20261014a"></script>
